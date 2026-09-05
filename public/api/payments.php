<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::requireModuleAccess('payments', true);
$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$user = Auth::user();

switch ($method) {

    case 'GET':
        safeExecute(function () use ($db) {
            $search = trim($_GET['search'] ?? '');
            $methodFilter = $_GET['method'] ?? '';
            $dateFrom = $_GET['date_from'] ?? '';
            $dateTo = $_GET['date_to'] ?? '';
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = 12;
            $offset = ($page - 1) * $perPage;

            $where = ['1=1'];
            $params = [];
            if ($search !== '') {
                $where[] = '(g.full_name LIKE :s1 OR r.reservation_code LIKE :s2 OR ev.event_name LIKE :s3 OR p.reference_number LIKE :s4)';
                $params[':s1'] = $params[':s2'] = $params[':s3'] = $params[':s4'] = "%{$search}%";
            }
            if ($methodFilter !== '') { $where[] = 'p.payment_method = :method'; $params[':method'] = $methodFilter; }
            if ($dateFrom !== '') { $where[] = 'DATE(p.paid_at) >= :from'; $params[':from'] = $dateFrom; }
            if ($dateTo !== '') { $where[] = 'DATE(p.paid_at) <= :to'; $params[':to'] = $dateTo; }
            $whereSql = implode(' AND ', $where);

            $countStmt = $db->prepare(
                "SELECT COUNT(*) FROM payments p
                 LEFT JOIN guests g ON g.id = p.guest_id
                 LEFT JOIN reservations r ON r.id = p.reservation_id
                 LEFT JOIN events ev ON ev.id = p.event_id
                 WHERE {$whereSql}"
            );
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $stmt = $db->prepare(
                "SELECT p.*, g.full_name AS guest_name, r.reservation_code, ev.event_name, s.full_name AS staff_name
                 FROM payments p
                 LEFT JOIN guests g ON g.id = p.guest_id
                 LEFT JOIN reservations r ON r.id = p.reservation_id
                 LEFT JOIN events ev ON ev.id = p.event_id
                 LEFT JOIN staff s ON s.id = p.staff_id
                 WHERE {$whereSql}
                 ORDER BY p.paid_at DESC
                 LIMIT {$perPage} OFFSET {$offset}"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$r) $r['amount_display'] = formatCurrency((float) $r['amount']);

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

            $reservationId = !empty($input['reservation_id']) ? (int) $input['reservation_id'] : null;
            $eventId = !empty($input['event_id']) ? (int) $input['event_id'] : null;
            $amount = (float) ($input['amount'] ?? 0);
            $validMethods = ['cash', 'bank_transfer', 'pos', 'card', 'other'];

            $errors = [];
            if (!$reservationId && !$eventId) $errors[] = 'Select a reservation or an event to attach this payment to.';
            if ($amount <= 0) $errors[] = 'Enter a valid payment amount.';
            if (empty($input['payment_method']) || !in_array($input['payment_method'], $validMethods, true)) $errors[] = 'Select a valid payment method.';
            if ($errors) jsonResponse(['success' => false, 'message' => implode(' ', $errors)], 422);

            $guestId = null;
            if ($reservationId) {
                $resStmt = $db->prepare("SELECT guest_id FROM reservations WHERE id = :id AND deleted_at IS NULL");
                $resStmt->execute([':id' => $reservationId]);
                $guestId = $resStmt->fetchColumn();
                if (!$guestId) jsonResponse(['success' => false, 'message' => 'Reservation not found.'], 404);
            }

            $db->beginTransaction();
            try {
                $db->prepare(
                    "INSERT INTO payments (guest_id, reservation_id, event_id, amount, payment_method, reference_number, staff_id, notes, paid_at)
                     VALUES (:guest_id, :reservation_id, :event_id, :amount, :method, :ref, :staff_id, :notes, NOW())"
                )->execute([
                    ':guest_id' => $guestId ?: null,
                    ':reservation_id' => $reservationId,
                    ':event_id' => $eventId,
                    ':amount' => $amount,
                    ':method' => $input['payment_method'],
                    ':ref' => $input['reference_number'] ?? null,
                    ':staff_id' => null,
                    ':notes' => $input['notes'] ?? null,
                ]);

                if ($reservationId) {
                    syncReservationPaymentStatus($db, $reservationId);
                }
                if ($eventId) {
                    syncEventBalance($db, $eventId);
                }

                $db->commit();
            } catch (Throwable $e) {
                $db->rollBack();
                throw $e;
            }

            Auth::logAudit(
                $user['id'],
                "{$user['username']} recorded a payment of " . formatCurrency($amount) . ($reservationId ? " for reservation #{$reservationId}" : " for event #{$eventId}"),
                'payments'
            );
            notify(null, 'payment', 'Payment received', formatCurrency($amount) . ' recorded via ' . str_replace('_', ' ', $input['payment_method']) . '.');
            jsonResponse(['success' => true, 'message' => 'Payment recorded successfully.', 'id' => $db->lastInsertId()]);
        });
        break;

    case 'PUT':
        safeExecute(function () use ($db, $user) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            if (!Auth::verifyCsrf($input['csrf_token'] ?? null)) {
                jsonResponse(['success' => false, 'message' => 'Your session has expired. Please refresh and try again.'], 419);
            }
            $id = (int) ($input['id'] ?? 0);
            if (!$id) jsonResponse(['success' => false, 'message' => 'Missing payment id.'], 422);

            $existing = $db->prepare("SELECT * FROM payments WHERE id = :id");
            $existing->execute([':id' => $id]);
            $before = $existing->fetch();
            if (!$before) jsonResponse(['success' => false, 'message' => 'Payment not found.'], 404);

            $amount = (float) ($input['amount'] ?? 0);
            $validMethods = ['cash', 'bank_transfer', 'pos', 'card', 'other'];
            $errors = [];
            if ($amount <= 0) $errors[] = 'Enter a valid payment amount.';
            if (empty($input['payment_method']) || !in_array($input['payment_method'], $validMethods, true)) $errors[] = 'Select a valid payment method.';
            if (empty($input['paid_at'])) $errors[] = 'Payment date is required.';
            if ($errors) jsonResponse(['success' => false, 'message' => implode(' ', $errors)], 422);

            // The reservation/event this payment is attached to is intentionally not editable —
            // to move a payment, delete it and record a new one against the correct booking.
            $db->beginTransaction();
            try {
                $db->prepare(
                    "UPDATE payments SET amount = :amount, payment_method = :method, reference_number = :ref, notes = :notes, paid_at = :paid_at WHERE id = :id"
                )->execute([
                    ':amount' => $amount,
                    ':method' => $input['payment_method'],
                    ':ref' => $input['reference_number'] ?? null,
                    ':notes' => $input['notes'] ?? null,
                    ':paid_at' => $input['paid_at'],
                    ':id' => $id,
                ]);

                if ($before['reservation_id']) syncReservationPaymentStatus($db, (int) $before['reservation_id']);
                if ($before['event_id']) syncEventBalance($db, (int) $before['event_id']);

                $db->commit();
            } catch (Throwable $e) {
                $db->rollBack();
                throw $e;
            }

            Auth::logAudit($user['id'], "{$user['username']} updated payment #{$id}", 'payments', json_encode($before), json_encode($input));
            jsonResponse(['success' => true, 'message' => 'Payment updated successfully.']);
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
            if (!$id) jsonResponse(['success' => false, 'message' => 'Missing payment id.'], 422);

            $stmt = $db->prepare("SELECT * FROM payments WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $payment = $stmt->fetch();
            if (!$payment) jsonResponse(['success' => false, 'message' => 'Payment not found.'], 404);

            $db->beginTransaction();
            try {
                $db->prepare("DELETE FROM payments WHERE id = :id")->execute([':id' => $id]);
                if ($payment['reservation_id']) syncReservationPaymentStatus($db, (int) $payment['reservation_id']);
                if ($payment['event_id']) syncEventBalance($db, (int) $payment['event_id']);
                $db->commit();
            } catch (Throwable $e) {
                $db->rollBack();
                throw $e;
            }

            Auth::logAudit($user['id'], "{$user['username']} deleted payment #{$id} (" . formatCurrency((float) $payment['amount']) . ")", 'payments', json_encode($payment), null);
            jsonResponse(['success' => true, 'message' => 'Payment removed successfully.']);
        });
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

function syncEventBalance(PDO $db, int $eventId): void
{
    $stmt = $db->prepare("SELECT price, deposit FROM events WHERE id = :id");
    $stmt->execute([':id' => $eventId]);
    $event = $stmt->fetch();
    if (!$event) return;

    $paidStmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE event_id = :id");
    $paidStmt->execute([':id' => $eventId]);
    $paid = (float) $paidStmt->fetchColumn();

    $balance = max(0, (float) $event['price'] - (float) $event['deposit'] - $paid);
    $db->prepare("UPDATE events SET balance = :b WHERE id = :id")->execute([':b' => $balance, ':id' => $eventId]);
}

function syncReservationPaymentStatus(PDO $db, int $reservationId): void
{
    $stmt = $db->prepare("SELECT total_amount FROM reservations WHERE id = :id");
    $stmt->execute([':id' => $reservationId]);
    $total = (float) $stmt->fetchColumn();

    $paidStmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE reservation_id = :id");
    $paidStmt->execute([':id' => $reservationId]);
    $paid = (float) $paidStmt->fetchColumn();

    $status = 'unpaid';
    if ($paid >= $total && $total > 0) $status = 'paid';
    elseif ($paid > 0) $status = 'partial';

    $db->prepare("UPDATE reservations SET payment_status = :s WHERE id = :id")->execute([':s' => $status, ':id' => $reservationId]);
}
