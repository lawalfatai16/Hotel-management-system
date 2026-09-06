<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::requireModuleAccess('reservations', true);
$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$user = Auth::user();

switch ($method) {

    case 'GET':
        safeExecute(function () use ($db) {

            // Single reservation (for edit prefill)
            if (!empty($_GET['id'])) {
                $stmt = $db->prepare(
                    "SELECT r.*, g.full_name AS guest_name, rm.room_number
                     FROM reservations r
                     JOIN guests g ON g.id = r.guest_id
                     JOIN rooms rm ON rm.id = r.room_id
                     WHERE r.id = :id AND r.deleted_at IS NULL"
                );
                $stmt->execute([':id' => (int) $_GET['id']]);
                $res = $stmt->fetch();
                if (!$res) jsonResponse(['success' => false, 'message' => 'Reservation not found.'], 404);
                jsonResponse(['success' => true, 'data' => $res]);
            }

            // Calendar view: all reservations overlapping a given month
            if (!empty($_GET['view']) && $_GET['view'] === 'calendar') {
                $month = $_GET['month'] ?? date('Y-m');
                $start = $month . '-01';
                $end = date('Y-m-t', strtotime($start));
                $stmt = $db->prepare(
                    "SELECT r.id, r.reservation_code, r.check_in_date, r.check_out_date, r.status,
                            g.full_name AS guest_name, rm.room_number
                     FROM reservations r
                     JOIN guests g ON g.id = r.guest_id
                     JOIN rooms rm ON rm.id = r.room_id
                     WHERE r.deleted_at IS NULL AND r.status != 'cancelled'
                       AND r.check_in_date <= :end AND r.check_out_date >= :start
                     ORDER BY r.check_in_date"
                );
                $stmt->execute([':start' => $start, ':end' => $end]);
                jsonResponse(['success' => true, 'data' => $stmt->fetchAll(), 'month' => $month]);
            }

            // Table view: filtered + paginated list
            $search  = trim($_GET['search'] ?? '');
            $status  = $_GET['status'] ?? '';
            $payment = $_GET['payment_status'] ?? '';
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = 10;
            $offset = ($page - 1) * $perPage;

            $where = ['r.deleted_at IS NULL'];
            $params = [];
            if ($search !== '') {
                $where[] = '(r.reservation_code LIKE :s1 OR g.full_name LIKE :s2 OR rm.room_number LIKE :s3)';
                $params[':s1'] = $params[':s2'] = $params[':s3'] = "%{$search}%";
            }
            if ($status !== '') { $where[] = 'r.status = :status'; $params[':status'] = $status; }
            if ($payment !== '') { $where[] = 'r.payment_status = :payment'; $params[':payment'] = $payment; }
            $whereSql = implode(' AND ', $where);

            $countStmt = $db->prepare("SELECT COUNT(*) FROM reservations r JOIN guests g ON g.id=r.guest_id JOIN rooms rm ON rm.id=r.room_id WHERE {$whereSql}");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $stmt = $db->prepare(
                "SELECT r.*, g.full_name AS guest_name, rm.room_number
                 FROM reservations r
                 JOIN guests g ON g.id = r.guest_id
                 JOIN rooms rm ON rm.id = r.room_id
                 WHERE {$whereSql}
                 ORDER BY r.check_in_date DESC
                 LIMIT {$perPage} OFFSET {$offset}"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$r) $r['total_display'] = formatCurrency((float) $r['total_amount']);

            jsonResponse([
                'success' => true,
                'data' => $rows,
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

            $errors = validateReservation($input);
            if ($errors) jsonResponse(['success' => false, 'message' => implode(' ', $errors)], 422);

            $calc = calculateTotals($db, $input);

            if (!roomIsAvailable($db, (int) $input['room_id'], $input['check_in_date'], $input['check_out_date'])) {
                jsonResponse(['success' => false, 'message' => 'This room is already booked for part of the selected date range.'], 422);
            }

            $prefix = setting('reservation_prefix', 'GMT-');
            $code = nextSequenceCode($db, 'reservations', 'reservation_code', $prefix);

            $stmt = $db->prepare(
                "INSERT INTO reservations (reservation_code, guest_id, room_id, check_in_date, check_out_date, number_of_guests,
                 room_rate, number_of_nights, discount, tax, total_amount, payment_status, status, special_requests, created_by)
                 VALUES (:code, :guest_id, :room_id, :check_in, :check_out, :num_guests, :rate, :nights, :discount, :tax, :total, :payment_status, :status, :notes, :created_by)"
            );
            $stmt->execute([
                ':code' => $code,
                ':guest_id' => $input['guest_id'],
                ':room_id' => $input['room_id'],
                ':check_in' => $input['check_in_date'],
                ':check_out' => $input['check_out_date'],
                ':num_guests' => $input['number_of_guests'] ?: 1,
                ':rate' => $calc['rate'],
                ':nights' => $calc['nights'],
                ':discount' => $calc['discount'],
                ':tax' => $calc['tax'],
                ':total' => $calc['total'],
                ':payment_status' => $input['payment_status'] ?? 'unpaid',
                ':status' => $input['status'] ?? 'pending',
                ':notes' => $input['special_requests'] ?? null,
                ':created_by' => $user['id'],
            ]);

            $newId = $db->lastInsertId();
            markRoomStatusFromReservation($db, (int) $input['room_id'], $input['status'] ?? 'pending');

            $guestName = $db->prepare("SELECT full_name FROM guests WHERE id = :id");
            $guestName->execute([':id' => $input['guest_id']]);
            notify(null, 'reservation', 'New reservation', "{$code} created for " . $guestName->fetchColumn() . ".");

            Auth::logAudit($user['id'], "{$user['username']} created reservation {$code}", 'reservations', null, json_encode($input));
            jsonResponse(['success' => true, 'message' => "Reservation {$code} created successfully.", 'id' => $newId, 'code' => $code]);
        });
        break;

    case 'PUT':
        safeExecute(function () use ($db, $user) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            if (!Auth::verifyCsrf($input['csrf_token'] ?? null)) {
                jsonResponse(['success' => false, 'message' => 'Your session has expired. Please refresh and try again.'], 419);
            }
            $id = (int) ($input['id'] ?? 0);
            if (!$id) jsonResponse(['success' => false, 'message' => 'Missing reservation id.'], 422);

            $existing = $db->prepare("SELECT * FROM reservations WHERE id = :id AND deleted_at IS NULL");
            $existing->execute([':id' => $id]);
            $before = $existing->fetch();
            if (!$before) jsonResponse(['success' => false, 'message' => 'Reservation not found.'], 404);

            // Quick status-only transitions (confirm / cancel / check-in / check-out shortcuts)
            if (!empty($input['action'])) {
                $map = ['confirm' => 'confirmed', 'cancel' => 'cancelled'];
                $newStatus = $map[$input['action']] ?? null;
                if (!$newStatus) jsonResponse(['success' => false, 'message' => 'Unknown action.'], 422);

                $db->prepare("UPDATE reservations SET status = :s WHERE id = :id")->execute([':s' => $newStatus, ':id' => $id]);
                markRoomStatusFromReservation($db, (int) $before['room_id'], $newStatus);
                Auth::logAudit($user['id'], "{$user['username']} marked reservation {$before['reservation_code']} as {$newStatus}", 'reservations', $before['status'], $newStatus);
                jsonResponse(['success' => true, 'message' => "Reservation {$newStatus}."]);
            }

            $errors = validateReservation($input);
            if ($errors) jsonResponse(['success' => false, 'message' => implode(' ', $errors)], 422);

            $calc = calculateTotals($db, $input);

            if (!roomIsAvailable($db, (int) $input['room_id'], $input['check_in_date'], $input['check_out_date'], $id)) {
                jsonResponse(['success' => false, 'message' => 'This room is already booked for part of the selected date range.'], 422);
            }

            $stmt = $db->prepare(
                "UPDATE reservations SET guest_id=:guest_id, room_id=:room_id, check_in_date=:check_in, check_out_date=:check_out,
                 number_of_guests=:num_guests, room_rate=:rate, number_of_nights=:nights, discount=:discount, tax=:tax,
                 total_amount=:total, payment_status=:payment_status, status=:status, special_requests=:notes WHERE id=:id"
            );
            $stmt->execute([
                ':guest_id' => $input['guest_id'],
                ':room_id' => $input['room_id'],
                ':check_in' => $input['check_in_date'],
                ':check_out' => $input['check_out_date'],
                ':num_guests' => $input['number_of_guests'] ?: 1,
                ':rate' => $calc['rate'],
                ':nights' => $calc['nights'],
                ':discount' => $calc['discount'],
                ':tax' => $calc['tax'],
                ':total' => $calc['total'],
                ':payment_status' => $input['payment_status'] ?? 'unpaid',
                ':status' => $input['status'] ?? $before['status'],
                ':notes' => $input['special_requests'] ?? null,
                ':id' => $id,
            ]);

            markRoomStatusFromReservation($db, (int) $input['room_id'], $input['status'] ?? $before['status']);
            Auth::logAudit($user['id'], "{$user['username']} updated reservation {$before['reservation_code']}", 'reservations', json_encode($before), json_encode($input));
            jsonResponse(['success' => true, 'message' => "Reservation {$before['reservation_code']} updated successfully."]);
        });
        break;

    case 'DELETE':
        safeExecute(function () use ($db, $user) {
            parse_str(file_get_contents('php://input'), $input);
            $id = (int) ($input['id'] ?? $_GET['id'] ?? 0);
            $csrf = $input['csrf_token'] ?? $_GET['csrf_token'] ?? null;
            if (!Auth::verifyCsrf($csrf)) {
                jsonResponse(['success' => false, 'message' => 'Your session has expired. Please refresh and try again.'], 419);
            }
            if (!$id) jsonResponse(['success' => false, 'message' => 'Missing reservation id.'], 422);

            $stmt = $db->prepare("SELECT * FROM reservations WHERE id = :id AND deleted_at IS NULL");
            $stmt->execute([':id' => $id]);
            $res = $stmt->fetch();
            if (!$res) jsonResponse(['success' => false, 'message' => 'Reservation not found.'], 404);
            if ($res['status'] === 'checked_in') {
                jsonResponse(['success' => false, 'message' => 'A checked-in reservation cannot be deleted. Cancel or check out the guest instead.'], 422);
            }

            $db->prepare("UPDATE reservations SET deleted_at = NOW(), status='cancelled' WHERE id = :id")->execute([':id' => $id]);
            markRoomStatusFromReservation($db, (int) $res['room_id'], 'cancelled');
            Auth::logAudit($user['id'], "{$user['username']} deleted reservation {$res['reservation_code']}", 'reservations', json_encode($res), null);
            jsonResponse(['success' => true, 'message' => 'Reservation removed successfully.']);
        });
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

// 
function validateReservation(array $input): array
{
    $errors = [];
    if (empty($input['guest_id'])) $errors[] = 'Guest is required.';
    if (empty($input['room_id'])) $errors[] = 'Room is required.';
    if (empty($input['check_in_date']) || empty($input['check_out_date'])) {
        $errors[] = 'Check-in and check-out dates are required.';
    } elseif (strtotime($input['check_out_date']) <= strtotime($input['check_in_date'])) {
        $errors[] = 'Check-out date must be after check-in date.';
    }
    if (isset($input['discount']) && (float) $input['discount'] < 0) $errors[] = 'Discount cannot be negative.';
    if (isset($input['tax']) && (float) $input['tax'] < 0) $errors[] = 'Tax cannot be negative.';
    return $errors;
}

/** Total = Room Rate x Nights + Tax - Discount */
function calculateTotals(PDO $db, array $input): array
{
    $stmt = $db->prepare("SELECT price_per_night FROM rooms WHERE id = :id");
    $stmt->execute([':id' => $input['room_id']]);
    $rate = (float) ($stmt->fetchColumn() ?: 0);

    $nights = (strtotime($input['check_out_date']) - strtotime($input['check_in_date'])) / 86400;
    $nights = max(1, (int) $nights);

    $discount = (float) ($input['discount'] ?? 0);
    $tax = (float) ($input['tax'] ?? 0);
    $total = ($rate * $nights) + $tax - $discount;

    return ['rate' => $rate, 'nights' => $nights, 'discount' => $discount, 'tax' => $tax, 'total' => max(0, $total)];
}

function roomIsAvailable(PDO $db, int $roomId, string $checkIn, string $checkOut, ?int $excludeId = null): bool
{
    $sql = "SELECT COUNT(*) FROM reservations
            WHERE room_id = :room_id AND deleted_at IS NULL AND status != 'cancelled'
              AND check_in_date < :check_out AND check_out_date > :check_in";
    $params = [':room_id' => $roomId, ':check_in' => $checkIn, ':check_out' => $checkOut];
    if ($excludeId) { $sql .= ' AND id != :exclude_id'; $params[':exclude_id'] = $excludeId; }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return ((int) $stmt->fetchColumn()) === 0;
}

function markRoomStatusFromReservation(PDO $db, int $roomId, string $reservationStatus): void
{
    $map = ['pending' => 'reserved', 'confirmed' => 'reserved', 'checked_in' => 'occupied', 'checked_out' => 'cleaning', 'cancelled' => 'available'];
    $roomStatus = $map[$reservationStatus] ?? null;
    if ($roomStatus) {
        $db->prepare("UPDATE rooms SET status = :s WHERE id = :id")->execute([':s' => $roomStatus, ':id' => $roomId]);
    }
}
