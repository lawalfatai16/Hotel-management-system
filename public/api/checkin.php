<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::requireModuleAccess('checkin', true);
$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$user = Auth::user();

switch ($method) {

    case 'GET':
        safeExecute(function () use ($db) {
            // Single reservation detail, for the confirm step
            if (!empty($_GET['id'])) {
                $stmt = $db->prepare(
                    "SELECT r.*, g.full_name AS guest_name, g.phone AS guest_phone, g.email AS guest_email,
                            g.id_type, g.id_number, rm.room_number, rm.status AS room_status, rt.name AS room_type_name
                     FROM reservations r
                     JOIN guests g ON g.id = r.guest_id
                     JOIN rooms rm ON rm.id = r.room_id
                     JOIN room_types rt ON rt.id = rm.room_type_id
                     WHERE r.id = :id AND r.deleted_at IS NULL"
                );
                $stmt->execute([':id' => (int) $_GET['id']]);
                $res = $stmt->fetch();
                if (!$res) jsonResponse(['success' => false, 'message' => 'Reservation not found.'], 404);

                $payStmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE reservation_id = :id");
                $payStmt->execute([':id' => $res['id']]);
                $res['amount_paid'] = (float) $payStmt->fetchColumn();
                $res['balance'] = max(0, (float) $res['total_amount'] - $res['amount_paid']);
                $res['guest_whatsapp'] = formatWhatsAppNumber($res['guest_phone']);

                jsonResponse(['success' => true, 'data' => $res]);
            }

            // List reservations awaiting check-in: pending/confirmed, check-in date today or already due
            $search = trim($_GET['search'] ?? '');
            $where = ["r.deleted_at IS NULL", "r.status IN ('pending','confirmed')", "r.check_in_date <= CURDATE()"];
            $params = [];
            if ($search !== '') {
                $where[] = '(r.reservation_code LIKE :s1 OR g.full_name LIKE :s2 OR rm.room_number LIKE :s3)';
                $params[':s1'] = $params[':s2'] = $params[':s3'] = "%{$search}%";
            }
            $whereSql = implode(' AND ', $where);

            $stmt = $db->prepare(
                "SELECT r.id, r.reservation_code, r.check_in_date, r.check_out_date, r.status, r.payment_status, r.total_amount,
                        g.full_name AS guest_name, rm.room_number
                 FROM reservations r
                 JOIN guests g ON g.id = r.guest_id
                 JOIN rooms rm ON rm.id = r.room_id
                 WHERE {$whereSql}
                 ORDER BY r.check_in_date"
            );
            $stmt->execute($params);
            jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
        });
        break;

    case 'POST':
        safeExecute(function () use ($db, $user) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            if (!Auth::verifyCsrf($input['csrf_token'] ?? null)) {
                jsonResponse(['success' => false, 'message' => 'Your session has expired. Please refresh and try again.'], 419);
            }
            $reservationId = (int) ($input['reservation_id'] ?? 0);
            if (!$reservationId) jsonResponse(['success' => false, 'message' => 'Missing reservation.'], 422);

            $stmt = $db->prepare("SELECT * FROM reservations WHERE id = :id AND deleted_at IS NULL");
            $stmt->execute([':id' => $reservationId]);
            $res = $stmt->fetch();
            if (!$res) jsonResponse(['success' => false, 'message' => 'Reservation not found.'], 404);
            if (!in_array($res['status'], ['pending', 'confirmed'], true)) {
                jsonResponse(['success' => false, 'message' => 'This reservation has already been checked in, checked out, or cancelled.'], 422);
            }

            $roomStmt = $db->prepare("SELECT status, room_number FROM rooms WHERE id = :id");
            $roomStmt->execute([':id' => $res['room_id']]);
            $room = $roomStmt->fetch();
            if ($room['status'] === 'occupied') {
                jsonResponse(['success' => false, 'message' => "Room {$room['room_number']} is already occupied."], 422);
            }

            $db->beginTransaction();
            try {
                $db->prepare(
                    "INSERT INTO checkins (reservation_id, checked_in_by, check_in_time, notes) VALUES (:rid, :uid, NOW(), :notes)"
                )->execute([':rid' => $reservationId, ':uid' => $user['id'], ':notes' => $input['notes'] ?? null]);

                $db->prepare("UPDATE reservations SET status = 'checked_in' WHERE id = :id")->execute([':id' => $reservationId]);
                $db->prepare("UPDATE rooms SET status = 'occupied' WHERE id = :id")->execute([':id' => $res['room_id']]);

                $db->commit();
            } catch (Throwable $e) {
                $db->rollBack();
                throw $e;
            }

            Auth::logAudit($user['id'], "{$user['username']} checked in reservation {$res['reservation_code']} (Room {$room['room_number']})", 'checkin');
            jsonResponse(['success' => true, 'message' => "Guest checked in to Room {$room['room_number']} successfully.", 'checkin_id' => $db->lastInsertId()]);
        });
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}
