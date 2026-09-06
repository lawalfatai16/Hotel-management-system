<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::requireLogin();
$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$user = Auth::user();

switch ($method) {

    case 'GET':
        safeExecute(function () use ($db, $user) {
            // Stored notifications: this user's own + broadcasts, most recent first
            $stmt = $db->prepare(
                "SELECT id, type, title, message, is_read, created_at, 'stored' AS source
                 FROM notifications WHERE user_id = :uid OR user_id IS NULL
                 ORDER BY created_at DESC LIMIT 30"
            );
            $stmt->execute([':uid' => $user['id']]);
            $stored = $stmt->fetchAll();

            // Live computed alerts: not stored, so they never go stale or duplicate
            $computed = [];

            $checkins = $db->query("SELECT COUNT(*) FROM reservations WHERE status IN ('pending','confirmed') AND check_in_date <= CURDATE() AND deleted_at IS NULL")->fetchColumn();
            if ($checkins > 0) $computed[] = ['type' => 'checkin', 'title' => 'Arrivals waiting', 'message' => "{$checkins} reservation(s) are due for check-in.", 'is_read' => 0, 'created_at' => date('Y-m-d H:i:s'), 'source' => 'live'];

            $checkouts = $db->query("SELECT COUNT(*) FROM reservations WHERE status = 'checked_in' AND check_out_date <= CURDATE()")->fetchColumn();
            if ($checkouts > 0) $computed[] = ['type' => 'checkout', 'title' => 'Check-outs due', 'message' => "{$checkouts} guest(s) are due to check out today or earlier.", 'is_read' => 0, 'created_at' => date('Y-m-d H:i:s'), 'source' => 'live'];

            $outstanding = $db->query("SELECT COUNT(*) FROM reservations WHERE payment_status != 'paid' AND status IN ('checked_in','confirmed') AND deleted_at IS NULL")->fetchColumn();
            if ($outstanding > 0) $computed[] = ['type' => 'payment', 'title' => 'Outstanding balances', 'message' => "{$outstanding} active reservation(s) have unpaid or partial balances.", 'is_read' => 0, 'created_at' => date('Y-m-d H:i:s'), 'source' => 'live'];

            $maintenance = $db->query("SELECT COUNT(*) FROM rooms WHERE status = 'maintenance' AND deleted_at IS NULL")->fetchColumn();
            if ($maintenance > 0) $computed[] = ['type' => 'maintenance', 'title' => 'Rooms under maintenance', 'message' => "{$maintenance} room(s) are currently marked for maintenance.", 'is_read' => 0, 'created_at' => date('Y-m-d H:i:s'), 'source' => 'live'];

            $eventsSoon = $db->query("SELECT COUNT(*) FROM events WHERE status = 'confirmed' AND event_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY) AND deleted_at IS NULL")->fetchColumn();
            if ($eventsSoon > 0) $computed[] = ['type' => 'event', 'title' => 'Events approaching', 'message' => "{$eventsSoon} confirmed event(s) in the next 3 days.", 'is_read' => 0, 'created_at' => date('Y-m-d H:i:s'), 'source' => 'live'];

            $all = array_merge($computed, $stored);
            $unreadCount = count($computed) + count(array_filter($stored, fn($n) => !$n['is_read']));

            jsonResponse(['success' => true, 'data' => $all, 'unread_count' => $unreadCount]);
        });
        break;

    case 'PUT':
        safeExecute(function () use ($db, $user) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            if (!Auth::verifyCsrf($input['csrf_token'] ?? null)) {
                jsonResponse(['success' => false, 'message' => 'Your session has expired. Please refresh and try again.'], 419);
            }
            if (!empty($input['mark_all'])) {
                $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = :uid OR user_id IS NULL")->execute([':uid' => $user['id']]);
                jsonResponse(['success' => true, 'message' => 'All notifications marked as read.']);
            }
            $id = (int) ($input['id'] ?? 0);
            if (!$id) jsonResponse(['success' => false, 'message' => 'Missing notification id.'], 422);
            $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = :id")->execute([':id' => $id]);
            jsonResponse(['success' => true, 'message' => 'Marked as read.']);
        });
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}
