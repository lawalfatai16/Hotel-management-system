<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::requireModuleAccess('staff', true);
$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$user = Auth::user();

// Creating or managing a staff login is a separate, more sensitive concern than
// editing the HR record, since it hands out real system access and a role.
// Restricted to Super Admin regardless of who else can reach the Staff module.
if (($_GET['resource'] ?? null) === 'login') {
    if ($user['role'] !== 'Super Admin') {
        jsonResponse(['success' => false, 'message' => 'Only a Super Admin can create or manage staff login access.'], 403);
    }
    handleStaffLogin($db, $user, $method);
    exit;
}

switch ($method) {

    case 'GET':
        safeExecute(function () use ($db) {
            $search = trim($_GET['search'] ?? '');
            $department = $_GET['department'] ?? '';
            $status = $_GET['status'] ?? '';
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = 10;
            $offset = ($page - 1) * $perPage;

            $where = ['s.deleted_at IS NULL'];
            $params = [];
            if ($search !== '') { $where[] = '(s.full_name LIKE :s1 OR s.email LIKE :s2 OR s.phone LIKE :s3)'; $params[':s1'] = $params[':s2'] = $params[':s3'] = "%{$search}%"; }
            if ($department !== '') { $where[] = 's.department = :dept'; $params[':dept'] = $department; }
            if ($status !== '') { $where[] = 's.status = :status'; $params[':status'] = $status; }
            $whereSql = implode(' AND ', $where);

            $countStmt = $db->prepare("SELECT COUNT(*) FROM staff s WHERE {$whereSql}");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $stmt = $db->prepare(
                "SELECT s.*, r.name AS role_name, u.id AS user_id, u.username AS login_username, u.status AS login_status
                 FROM staff s
                 LEFT JOIN roles r ON r.id = s.role_id
                 LEFT JOIN users u ON u.staff_id = s.id
                 WHERE {$whereSql} ORDER BY s.full_name LIMIT {$perPage} OFFSET {$offset}"
            );
            $stmt->execute($params);

            jsonResponse([
                'success' => true, 'data' => $stmt->fetchAll(),
                'pagination' => ['page' => $page, 'perPage' => $perPage, 'total' => $total, 'totalPages' => (int) ceil($total / $perPage)],
            ]);
        });
        break;

    case 'POST':
        safeExecute(function () use ($db, $user) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            if (!Auth::verifyCsrf($input['csrf_token'] ?? null)) {
                jsonResponse(['success' => false, 'message' => 'Your session has expired. Please refresh and try again.'], 419);
            }
            $errors = validateStaff($input);
            if ($errors) jsonResponse(['success' => false, 'message' => implode(' ', $errors)], 422);

            $stmt = $db->prepare(
                "INSERT INTO staff (full_name, department, position, phone, email, role_id, status, date_employed)
                 VALUES (:name, :dept, :position, :phone, :email, :role_id, :status, :employed)"
            );
            $stmt->execute([
                ':name' => $input['full_name'], ':dept' => $input['department'], ':position' => $input['position'],
                ':phone' => $input['phone'] ?? null, ':email' => $input['email'] ?? null,
                ':role_id' => $input['role_id'] ?: null, ':status' => $input['status'] ?? 'active',
                ':employed' => $input['date_employed'],
            ]);

            Auth::logAudit($user['id'], "{$user['username']} added staff member {$input['full_name']}", 'staff', null, json_encode($input));
            jsonResponse(['success' => true, 'message' => 'Staff member added successfully.', 'id' => $db->lastInsertId()]);
        });
        break;

    case 'PUT':
        safeExecute(function () use ($db, $user) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            if (!Auth::verifyCsrf($input['csrf_token'] ?? null)) {
                jsonResponse(['success' => false, 'message' => 'Your session has expired. Please refresh and try again.'], 419);
            }
            $id = (int) ($input['id'] ?? 0);
            if (!$id) jsonResponse(['success' => false, 'message' => 'Missing staff id.'], 422);
            $errors = validateStaff($input);
            if ($errors) jsonResponse(['success' => false, 'message' => implode(' ', $errors)], 422);

            $existing = $db->prepare("SELECT * FROM staff WHERE id = :id AND deleted_at IS NULL");
            $existing->execute([':id' => $id]);
            $before = $existing->fetch();
            if (!$before) jsonResponse(['success' => false, 'message' => 'Staff member not found.'], 404);

            $db->prepare(
                "UPDATE staff SET full_name=:name, department=:dept, position=:position, phone=:phone, email=:email,
                 role_id=:role_id, status=:status, date_employed=:employed WHERE id=:id"
            )->execute([
                ':name' => $input['full_name'], ':dept' => $input['department'], ':position' => $input['position'],
                ':phone' => $input['phone'] ?? null, ':email' => $input['email'] ?? null,
                ':role_id' => $input['role_id'] ?: null, ':status' => $input['status'] ?? $before['status'],
                ':employed' => $input['date_employed'], ':id' => $id,
            ]);

            Auth::logAudit($user['id'], "{$user['username']} updated staff member {$input['full_name']}", 'staff', json_encode($before), json_encode($input));
            jsonResponse(['success' => true, 'message' => 'Staff member updated successfully.']);
        });
        break;

    case 'DELETE':
        safeExecute(function () use ($db, $user) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $id = (int) ($input['id'] ?? $_GET['id'] ?? 0);
            $csrf = $input['csrf_token'] ?? $_GET['csrf_token'] ?? null;
            if (!Auth::verifyCsrf($csrf)) {
                jsonResponse(['success' => false, 'message' => 'Your session has expired. Please refresh and try again.'], 419);
            }
            if (!$id) jsonResponse(['success' => false, 'message' => 'Missing staff id.'], 422);

            $refs = $db->prepare(
                "SELECT (SELECT COUNT(*) FROM housekeeping_tasks WHERE assigned_staff_id = :id1)
                       + (SELECT COUNT(*) FROM orders WHERE staff_id = :id2) AS refs"
            );
            $refs->execute([':id1' => $id, ':id2' => $id]);
            if ((int) $refs->fetchColumn() > 0) {
                jsonResponse(['success' => false, 'message' => 'This staff member has task or order history and cannot be removed. Set their status to inactive instead.'], 422);
            }

            $stmt = $db->prepare("SELECT full_name FROM staff WHERE id = :id AND deleted_at IS NULL");
            $stmt->execute([':id' => $id]);
            $staff = $stmt->fetch();
            if (!$staff) jsonResponse(['success' => false, 'message' => 'Staff member not found.'], 404);

            $db->prepare("UPDATE staff SET deleted_at = NOW() WHERE id = :id")->execute([':id' => $id]);
            Auth::logAudit($user['id'], "{$user['username']} removed staff member {$staff['full_name']}", 'staff', json_encode($staff), null);
            jsonResponse(['success' => true, 'message' => 'Staff member removed successfully.']);
        });
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

function validateStaff(array $input): array
{
    $errors = [];
    if (empty($input['full_name'])) $errors[] = 'Full name is required.';
    if (empty($input['department'])) $errors[] = 'Department is required.';
    if (empty($input['position'])) $errors[] = 'Position is required.';
    if (empty($input['date_employed'])) $errors[] = 'Date employed is required.';
    return $errors;
}

function handleStaffLogin(PDO $db, array $user, string $method): void
{
    switch ($method) {

        case 'POST':
            safeExecute(function () use ($db, $user) {
                $input = json_decode(file_get_contents('php://input'), true) ?? [];
                if (!Auth::verifyCsrf($input['csrf_token'] ?? null)) {
                    jsonResponse(['success' => false, 'message' => 'Your session has expired. Please refresh and try again.'], 419);
                }

                $staffId = (int) ($input['staff_id'] ?? 0);
                $username = trim($input['username'] ?? '');
                $email = trim($input['email'] ?? '');
                $password = (string) ($input['password'] ?? '');
                $roleId = (int) ($input['role_id'] ?? 0);

                $errors = [];
                if (!$staffId) $errors[] = 'Staff member is required.';
                if ($username === '') $errors[] = 'Username is required.';
                if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
                if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
                if (!$roleId) $errors[] = 'A system role is required.';
                if ($errors) jsonResponse(['success' => false, 'message' => implode(' ', $errors)], 422);

                $staffStmt = $db->prepare("SELECT full_name FROM staff WHERE id = :id AND deleted_at IS NULL");
                $staffStmt->execute([':id' => $staffId]);
                $staff = $staffStmt->fetch();
                if (!$staff) jsonResponse(['success' => false, 'message' => 'Staff member not found.'], 404);

                $existingLogin = $db->prepare("SELECT id FROM users WHERE staff_id = :id");
                $existingLogin->execute([':id' => $staffId]);
                if ($existingLogin->fetch()) jsonResponse(['success' => false, 'message' => 'This staff member already has a login. Use manage login to update it instead.'], 422);

                $roleStmt = $db->prepare("SELECT name FROM roles WHERE id = :id");
                $roleStmt->execute([':id' => $roleId]);
                $roleName = $roleStmt->fetchColumn();
                if (!$roleName) jsonResponse(['success' => false, 'message' => 'Invalid role selected.'], 422);

                $dupeStmt = $db->prepare("SELECT id FROM users WHERE username = :u OR email = :e");
                $dupeStmt->execute([':u' => $username, ':e' => $email]);
                if ($dupeStmt->fetch()) jsonResponse(['success' => false, 'message' => 'That username or email is already in use by another account.'], 422);

                $db->beginTransaction();
                try {
                    $db->prepare(
                        "INSERT INTO users (staff_id, username, email, password_hash, role_id, status)
                         VALUES (:staff_id, :username, :email, :hash, :role_id, 'active')"
                    )->execute([
                        ':staff_id' => $staffId, ':username' => $username, ':email' => $email,
                        ':hash' => password_hash($password, PASSWORD_DEFAULT), ':role_id' => $roleId,
                    ]);
                    $db->prepare("UPDATE staff SET role_id = :role_id WHERE id = :id")->execute([':role_id' => $roleId, ':id' => $staffId]);
                    $db->commit();
                } catch (Throwable $e) {
                    $db->rollBack();
                    throw $e;
                }

                Auth::logAudit($user['id'], "{$user['username']} created a {$roleName} login for {$staff['full_name']} ({$username})", 'staff');
                jsonResponse(['success' => true, 'message' => "Login created for {$staff['full_name']}."]);
            });
            break;

        case 'PUT':
            safeExecute(function () use ($db, $user) {
                $input = json_decode(file_get_contents('php://input'), true) ?? [];
                if (!Auth::verifyCsrf($input['csrf_token'] ?? null)) {
                    jsonResponse(['success' => false, 'message' => 'Your session has expired. Please refresh and try again.'], 419);
                }

                $userId = (int) ($input['user_id'] ?? 0);
                if (!$userId) jsonResponse(['success' => false, 'message' => 'Missing login id.'], 422);

                $existing = $db->prepare("SELECT u.*, s.full_name FROM users u JOIN staff s ON s.id = u.staff_id WHERE u.id = :id");
                $existing->execute([':id' => $userId]);
                $login = $existing->fetch();
                if (!$login) jsonResponse(['success' => false, 'message' => 'This login is not linked to a staff record and cannot be managed here.'], 404);

                $changes = [];

                if (!empty($input['password'])) {
                    if (strlen($input['password']) < 8) jsonResponse(['success' => false, 'message' => 'Password must be at least 8 characters.'], 422);
                    $db->prepare("UPDATE users SET password_hash = :hash, failed_login_attempts = 0, locked_until = NULL WHERE id = :id")
                       ->execute([':hash' => password_hash($input['password'], PASSWORD_DEFAULT), ':id' => $userId]);
                    $changes[] = 'reset the password';
                }

                if (!empty($input['role_id'])) {
                    $roleStmt = $db->prepare("SELECT name FROM roles WHERE id = :id");
                    $roleStmt->execute([':id' => $input['role_id']]);
                    $roleName = $roleStmt->fetchColumn();
                    if (!$roleName) jsonResponse(['success' => false, 'message' => 'Invalid role selected.'], 422);
                    $db->prepare("UPDATE users SET role_id = :role_id WHERE id = :id")->execute([':role_id' => $input['role_id'], ':id' => $userId]);
                    $db->prepare("UPDATE staff SET role_id = :role_id WHERE id = :id")->execute([':role_id' => $input['role_id'], ':id' => $login['staff_id']]);
                    $changes[] = "changed the role to {$roleName}";
                }

                if (!empty($input['status']) && in_array($input['status'], ['active', 'suspended'], true)) {
                    $db->prepare("UPDATE users SET status = :status WHERE id = :id")->execute([':status' => $input['status'], ':id' => $userId]);
                    $changes[] = "set the login to {$input['status']}";
                }

                if (!$changes) jsonResponse(['success' => false, 'message' => 'No changes were provided.'], 422);

                Auth::logAudit($user['id'], "{$user['username']} updated login for {$login['full_name']}: " . implode(', ', $changes), 'staff');
                jsonResponse(['success' => true, 'message' => 'Login updated successfully.']);
            });
            break;

        default:
            jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
}
