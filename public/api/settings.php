<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::requireModuleAccess('settings', true);
$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$user = Auth::user();

$editableKeys = [
    'hotel_name', 'address', 'phone', 'email', 'currency_code', 'currency_symbol',
    'tax_rate', 'invoice_prefix', 'reservation_prefix', 'date_format', 'time_format', 'logo_path',
    'whatsapp_country_code',
];

switch ($method) {

    case 'GET':
        safeExecute(function () use ($db) {
            $rows = $db->query("SELECT setting_key, setting_value FROM settings")->fetchAll();
            $data = [];
            foreach ($rows as $r) $data[$r['setting_key']] = $r['setting_value'];
            jsonResponse(['success' => true, 'data' => $data]);
        });
        break;

    case 'POST': // logo upload (multipart/form-data)
        safeExecute(function () use ($db, $user) {
            if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
                jsonResponse(['success' => false, 'message' => 'Your session has expired. Please refresh and try again.'], 419);
            }
            if (empty($_FILES['logo']) || $_FILES['logo']['error'] !== UPLOAD_ERR_OK) {
                jsonResponse(['success' => false, 'message' => 'No valid logo file was uploaded.'], 422);
            }
            $allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
            $mime = mime_content_type($_FILES['logo']['tmp_name']);
            if (!isset($allowed[$mime])) jsonResponse(['success' => false, 'message' => 'Logo must be a PNG, JPG, or WEBP image.'], 422);
            if ($_FILES['logo']['size'] > 2 * 1024 * 1024) jsonResponse(['success' => false, 'message' => 'Logo must be under 2MB.'], 422);

            $uploadDir = ROOT_PATH . '/public/assets/uploads';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $filename = 'logo.' . $allowed[$mime];
            move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . '/' . $filename);

            $relativePath = 'assets/uploads/' . $filename;
            $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES ('logo_path', :v1) ON DUPLICATE KEY UPDATE setting_value = :v2")
               ->execute([':v1' => $relativePath, ':v2' => $relativePath]);

            Auth::logAudit($user['id'], "{$user['username']} updated the hotel logo", 'settings');
            jsonResponse(['success' => true, 'message' => 'Logo updated successfully.', 'path' => $relativePath]);
        });
        break;

    case 'PUT':
        safeExecute(function () use ($db, $user, $editableKeys) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            if (!Auth::verifyCsrf($input['csrf_token'] ?? null)) {
                jsonResponse(['success' => false, 'message' => 'Your session has expired. Please refresh and try again.'], 419);
            }
            if (empty($input['hotel_name'])) jsonResponse(['success' => false, 'message' => 'Hotel name cannot be empty.'], 422);
            if (isset($input['tax_rate']) && (float) $input['tax_rate'] < 0) jsonResponse(['success' => false, 'message' => 'Tax rate cannot be negative.'], 422);

            $stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v1) ON DUPLICATE KEY UPDATE setting_value = :v2");
            foreach ($editableKeys as $key) {
                if (!array_key_exists($key, $input)) continue;
                $stmt->execute([':k' => $key, ':v1' => $input[$key], ':v2' => $input[$key]]);
            }

            Auth::logAudit($user['id'], "{$user['username']} updated system settings", 'settings');
            jsonResponse(['success' => true, 'message' => 'Settings saved successfully.']);
        });
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}
