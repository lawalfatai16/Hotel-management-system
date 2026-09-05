<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::requireLogin();
$user = Auth::user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}
if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
    jsonResponse(['success' => false, 'message' => 'Your session has expired. Please refresh and try again.'], 419);
}
if (empty($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    jsonResponse(['success' => false, 'message' => 'No valid image was uploaded.'], 422);
}

$allowed = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp'];
$mime = mime_content_type($_FILES['image']['tmp_name']);
if (!isset($allowed[$mime])) {
    jsonResponse(['success' => false, 'message' => 'Image must be PNG, JPG, or WEBP.'], 422);
}
if ($_FILES['image']['size'] > 3 * 1024 * 1024) {
    jsonResponse(['success' => false, 'message' => 'Image must be under 3MB.'], 422);
}

// Restrict context to a safe folder name — this is the only user input touching the filesystem path
$context = preg_replace('/[^a-z0-9_-]/i', '', $_POST['context'] ?? 'misc') ?: 'misc';
$uploadDir = ROOT_PATH . '/public/assets/uploads/' . $context;
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

$filename = bin2hex(random_bytes(8)) . '.' . $allowed[$mime];
move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . '/' . $filename);

$relativePath = "assets/uploads/{$context}/{$filename}";
Auth::logAudit($user['id'], "{$user['username']} uploaded an image ({$context})", 'uploads');
jsonResponse(['success' => true, 'message' => 'Image uploaded.', 'path' => $relativePath]);
