<?php
/**
 * Shared helper functions.
 */

function setting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $db = Database::connect();
        $cache = [];
        foreach ($db->query("SELECT setting_key, setting_value FROM settings") as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    }
    return $cache[$key] ?? $default;
}

function formatCurrency(float $amount): string
{
    $symbol = setting('currency_symbol', '₦');
    return $symbol . number_format($amount, 2);
}

function formatDate(?string $date, string $format = null): string
{
    if (!$date) return '';
    $format = $format ?? setting('date_format', 'd M Y');
    return date($format, strtotime($date));
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function flash(string $key, ?string $message = null)
{
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function jsonResponse($data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/** Generates the next sequential code for a prefix, e.g. GMT-000452 */
function nextSequenceCode(PDO $db, string $table, string $column, string $prefix, int $padding = 6): string
{
    $stmt = $db->query("SELECT MAX(CAST(SUBSTRING({$column}, " . (strlen($prefix) + 1) . ") AS UNSIGNED)) AS max_n FROM {$table}");
    $max = (int) ($stmt->fetch()['max_n'] ?? 0);
    return $prefix . str_pad((string) ($max + 1), $padding, '0', STR_PAD_LEFT);
}

/** Formats a locally-stored phone number into WhatsApp's required international digits-only format. */
function formatWhatsAppNumber(?string $phone): ?string
{
    if (!$phone) return null;
    $digits = preg_replace('/\D/', '', $phone);
    if ($digits === '') return null;

    $countryCode = setting('whatsapp_country_code', '234');
    if (str_starts_with($digits, $countryCode)) return $digits;
    if (str_starts_with($digits, '0')) return $countryCode . substr($digits, 1);
    return $countryCode . $digits;
}

/** Insert a notification. user_id = null means broadcast to everyone. */
function notify(?int $userId, string $type, string $title, string $message): void
{
    try {
        $db = Database::connect();
        $db->prepare("INSERT INTO notifications (user_id, type, title, message) VALUES (:uid, :type, :title, :message)")
           ->execute([':uid' => $userId, ':type' => $type, ':title' => $title, ':message' => $message]);
    } catch (Throwable $e) {
        error_log('notify() failed: ' . $e->getMessage());
    }
}

/** Friendly wrapper for try/catch blocks in controllers/API endpoints. */
function safeExecute(callable $fn)
{
    try {
        return $fn();
    } catch (Throwable $e) {
        error_log($e->getMessage() . "\n" . $e->getTraceAsString());
        jsonResponse(['success' => false, 'message' => 'Unable to complete this request. Please try again.'], 500);
    }
}
