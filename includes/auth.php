<?php
/**
 * Authentication and access-control helpers.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

class Auth
{
    /** Attempt to log a user in. Returns ['ok'=>bool, 'error'=>?string] */
    public static function attempt(string $username, string $password, bool $remember = false): array
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            "SELECT u.*, r.name AS role_name FROM users u
             JOIN roles r ON r.id = u.role_id
             WHERE (u.username = :u1 OR u.email = :u2) AND u.deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute([':u1' => $username, ':u2' => $username]);
        $user = $stmt->fetch();

        if (!$user) {
            self::logAudit(null, 'Failed login attempt (unknown user: ' . $username . ')', 'auth');
            return ['ok' => false, 'error' => 'Invalid username or password.'];
        }

        if ($user['status'] !== 'active') {
            return ['ok' => false, 'error' => 'This account has been suspended. Contact an administrator.'];
        }

        if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
            $mins = ceil((strtotime($user['locked_until']) - time()) / 60);
            return ['ok' => false, 'error' => "Too many failed attempts. Try again in {$mins} minute(s)."];
        }

        if (!password_verify($password, $user['password_hash'])) {
            self::registerFailedAttempt($user['id'], (int) $user['failed_login_attempts']);
            self::logAudit($user['id'], 'Failed login attempt (wrong password)', 'auth');
            return ['ok' => false, 'error' => 'Invalid username or password.'];
        }

        // Success: reset attempts, rotate session id, set session
        $db->prepare("UPDATE users SET failed_login_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = :id")
           ->execute([':id' => $user['id']]);

        session_regenerate_id(true);
        $_SESSION['user_id']    = $user['id'];
        $_SESSION['username']   = $user['username'];
        $_SESSION['role']       = $user['role_name'];
        $_SESSION['role_id']    = $user['role_id'];
        $_SESSION['last_active'] = time();

        if ($remember) {
            $token = bin2hex(random_bytes(32));
            $db->prepare("UPDATE users SET remember_token = :t WHERE id = :id")
               ->execute([':t' => hash('sha256', $token), ':id' => $user['id']]);
            setcookie('gmt_remember', $user['id'] . ':' . $token, [
                'expires'  => time() + 60 * 60 * 24 * 30,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        self::logAudit($user['id'], $user['username'] . ' logged in', 'auth');
        return ['ok' => true, 'error' => null];
    }

    private static function registerFailedAttempt(int $userId, int $currentAttempts): void
    {
        $db = Database::connect();
        $attempts = $currentAttempts + 1;
        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $lockUntil = date('Y-m-d H:i:s', time() + LOGIN_LOCKOUT_MINUTES * 60);
            $db->prepare("UPDATE users SET failed_login_attempts = :a, locked_until = :l WHERE id = :id")
               ->execute([':a' => $attempts, ':l' => $lockUntil, ':id' => $userId]);
        } else {
            $db->prepare("UPDATE users SET failed_login_attempts = :a WHERE id = :id")
               ->execute([':a' => $attempts, ':id' => $userId]);
        }
    }

    public static function logout(): void
    {
        if (isset($_SESSION['user_id'])) {
            self::logAudit($_SESSION['user_id'], ($_SESSION['username'] ?? 'user') . ' logged out', 'auth');
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path']);
        }
        setcookie('gmt_remember', '', time() - 42000, '/');
        session_destroy();
    }

    /** Require an authenticated session; redirect to login otherwise. */
    public static function requireLogin(): void
    {
        if (empty($_SESSION['user_id'])) {
            header('Location: login.php');
            exit;
        }
        // idle timeout
        if (isset($_SESSION['last_active']) && (time() - $_SESSION['last_active']) > SESSION_TIMEOUT_MINUTES * 60) {
            self::logout();
            header('Location: login.php?timeout=1');
            exit;
        }
        $_SESSION['last_active'] = time();
    }

    /** True if the current session's role has access to the given module. Super Admin always true. */
    public static function moduleAllowed(string $module): bool
    {
        $role = $_SESSION['role'] ?? null;
        if (!$role) return false;
        static $matrix = null;
        if ($matrix === null) $matrix = require __DIR__ . '/permissions.php';
        $allowed = $matrix[$role] ?? [];
        return in_array('*', $allowed, true) || in_array($module, $allowed, true);
    }

    /**
     * Require login AND that the current role has access to $module.
     * Use $isApi = true in api/*.php files to get a JSON 403 instead of an HTML page.
     * Super Admin bypasses this check entirely, on every module, always.
     */
    public static function requireModuleAccess(string $module, bool $isApi = false): void
    {
        self::requireLogin();
        if (self::moduleAllowed($module)) return;

        self::logAudit($_SESSION['user_id'] ?? null, "Blocked access attempt to '{$module}' module", 'access_control');

        if ($isApi) {
            jsonResponse(['success' => false, 'message' => 'You do not have permission to access this module.'], 403);
        }
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Access Restricted</title>'
           . '<style>body{font-family:system-ui,sans-serif;background:#F5EFE4;color:#241812;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;text-align:center;}'
           . 'a{color:#7A4A2B;font-weight:600;text-decoration:none;}</style></head><body><div>'
           . '<h2>Access Restricted</h2><p>Your role does not have permission to view this module.</p>'
           . '<a href="dashboard.php">← Return to Dashboard</a></div></body></html>';
        exit;
    }

    /** Require the current user to hold one of the given roles. */
    public static function requireRole(array $allowedRoles): void
    {
        self::requireLogin();
        if (!in_array($_SESSION['role'], $allowedRoles, true)) {
            http_response_code(403);
            die('You do not have permission to access this module.');
        }
    }

    public static function user(): ?array
    {
        return isset($_SESSION['user_id']) ? [
            'id'       => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'role'     => $_SESSION['role'],
        ] : null;
    }

    // CSRF
    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(?string $token): bool
    {
        return !empty($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    // Audit
    public static function logAudit(?int $userId, string $action, string $module, ?string $old = null, ?string $new = null): void
    {
        try {
            $db = Database::connect();
            $db->prepare(
                "INSERT INTO audit_logs (user_id, action, module, old_value, new_value, ip_address, user_agent)
                 VALUES (:uid, :action, :module, :old, :new, :ip, :ua)"
            )->execute([
                ':uid'    => $userId,
                ':action' => $action,
                ':module' => $module,
                ':old'    => $old,
                ':new'    => $new,
                ':ip'     => $_SERVER['REMOTE_ADDR'] ?? null,
                ':ua'     => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ]);
        } catch (Throwable $e) {
            error_log('Audit log failed: ' . $e->getMessage());
        }
    }
}
