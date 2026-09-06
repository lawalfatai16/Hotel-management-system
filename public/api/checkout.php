<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::requireModuleAccess('checkout', true);
$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$user = Auth::user();

switch ($method) {

    case 'GET':
        safeExecute(function () use ($db) {
            // Final invoice preview for a specific reservation
            if (!empty($_GET['id'])) {
                $summary = buildCheckoutSummary($db, (int) $_GET['id']);
                if (!$summary) jsonResponse(['success' => false, 'message' => 'Reservation not found or not checked in.'], 404);
                jsonResponse(['success' => true, 'data' => $summary]);
            }

            // List of currently checked-in reservations
            $search = trim($_GET['search'] ?? '');
            $where = ["r.deleted_at IS NULL", "r.status = 'checked_in'"];
            $params = [];
            if ($search !== '') {
                $where[] = '(r.reservation_code LIKE :s1 OR g.full_name LIKE :s2 OR rm.room_number LIKE :s3)';
                $params[':s1'] = $params[':s2'] = $params[':s3'] = "%{$search}%";
            }
            $whereSql = implode(' AND ', $where);

            $stmt = $db->prepare(
                "SELECT r.id, r.reservation_code, r.check_in_date, r.check_out_date, r.payment_status,
                        g.full_name AS guest_name, rm.room_number
                 FROM reservations r
                 JOIN guests g ON g.id = r.guest_id
                 JOIN rooms rm ON rm.id = r.room_id
                 WHERE {$whereSql}
                 ORDER BY r.check_out_date"
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

            $summary = buildCheckoutSummary($db, $reservationId);
            if (!$summary) jsonResponse(['success' => false, 'message' => 'Reservation not found or not checked in.'], 404);

            $db->beginTransaction();
            try {
                $db->prepare(
                    "INSERT INTO checkouts (reservation_id, checked_out_by, check_out_time, additional_charges, outstanding_balance, notes)
                     VALUES (:rid, :uid, NOW(), :extra, :balance, :notes)"
                )->execute([
                    ':rid' => $reservationId,
                    ':uid' => $user['id'],
                    ':extra' => $summary['additional_nights_charge'] + $summary['restaurant_charges'],
                    ':balance' => $summary['balance'],
                    ':notes' => $input['notes'] ?? null,
                ]);

                $db->prepare("UPDATE reservations SET status = 'checked_out' WHERE id = :id")->execute([':id' => $reservationId]);
                $db->prepare("UPDATE rooms SET status = 'cleaning' WHERE id = :id")->execute([':id' => $summary['room_id']]);

                // Auto-create a housekeeping task for the vacated room
                $db->prepare(
                    "INSERT INTO housekeeping_tasks (room_id, task, priority, status, time_assigned)
                     VALUES (:room_id, 'Post-checkout cleaning', 'normal', 'pending', NOW())"
                )->execute([':room_id' => $summary['room_id']]);

                $prefix = setting('invoice_prefix', 'GMT-INV-');
                $invoiceNumber = nextSequenceCode($db, 'invoices', 'invoice_number', $prefix);
                $status = $summary['balance'] <= 0 ? 'paid' : 'issued';

                $db->prepare(
                    "INSERT INTO invoices (invoice_number, reservation_id, guest_id, subtotal, discount, tax, total, amount_paid, balance, status, issued_by, issued_at)
                     VALUES (:num, :rid, :gid, :subtotal, :discount, :tax, :total, :paid, :balance, :status, :uid, NOW())"
                )->execute([
                    ':num' => $invoiceNumber, ':rid' => $reservationId, ':gid' => $summary['guest_id'],
                    ':subtotal' => $summary['subtotal'], ':discount' => $summary['discount'], ':tax' => $summary['tax'],
                    ':total' => $summary['total'], ':paid' => $summary['amount_paid'], ':balance' => $summary['balance'],
                    ':status' => $status, ':uid' => $user['id'],
                ]);
                $invoiceId = $db->lastInsertId();

                $itemStmt = $db->prepare(
                    "INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, line_total) VALUES (:iid, :desc, :qty, :unit, :total)"
                );
                foreach ($summary['line_items'] as $item) {
                    $itemStmt->execute([':iid' => $invoiceId, ':desc' => $item['label'], ':qty' => $item['qty'], ':unit' => $item['unit_price'], ':total' => $item['amount']]);
                }

                $db->commit();
            } catch (Throwable $e) {
                $db->rollBack();
                throw $e;
            }

            Auth::logAudit($user['id'], "{$user['username']} checked out reservation {$summary['reservation_code']}, invoice {$invoiceNumber}", 'checkout');
            jsonResponse(['success' => true, 'message' => "Guest checked out. Invoice {$invoiceNumber} generated.", 'invoice_id' => $invoiceId, 'invoice_number' => $invoiceNumber]);
        });
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

function buildCheckoutSummary(PDO $db, int $reservationId): ?array
{
    $stmt = $db->prepare(
        "SELECT r.*, g.full_name AS guest_name, g.phone AS guest_phone, rm.room_number
         FROM reservations r
         JOIN guests g ON g.id = r.guest_id
         JOIN rooms rm ON rm.id = r.room_id
         WHERE r.id = :id AND r.deleted_at IS NULL AND r.status = 'checked_in'"
    );
    $stmt->execute([':id' => $reservationId]);
    $r = $stmt->fetch();
    if (!$r) return null;

    $roomRate = (float) $r['room_rate'];
    $plannedNights = (int) $r['number_of_nights'];
    $today = date('Y-m-d');
    $extraNights = max(0, (int) ((strtotime($today) - strtotime($r['check_out_date'])) / 86400));
    $additionalNightsCharge = $extraNights * $roomRate;

    $restaurantStmt = $db->prepare("SELECT COALESCE(SUM(total),0) FROM orders WHERE reservation_id = :id AND status != 'cancelled'");
    $restaurantStmt->execute([':id' => $reservationId]);
    $restaurantCharges = (float) $restaurantStmt->fetchColumn();

    $paidStmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE reservation_id = :id");
    $paidStmt->execute([':id' => $reservationId]);
    $amountPaid = (float) $paidStmt->fetchColumn();

    $lineItems = [
        ['label' => "Room {$r['room_number']}, {$plannedNights} night(s) @ " . formatCurrency($roomRate), 'qty' => $plannedNights, 'unit_price' => $roomRate, 'amount' => $roomRate * $plannedNights],
    ];
    if ($extraNights > 0) {
        $lineItems[] = ['label' => "Additional night(s) beyond planned check-out", 'qty' => $extraNights, 'unit_price' => $roomRate, 'amount' => $additionalNightsCharge];
    }
    if ($restaurantCharges > 0) {
        $lineItems[] = ['label' => 'Restaurant charges', 'qty' => 1, 'unit_price' => $restaurantCharges, 'amount' => $restaurantCharges];
    }

    $subtotal = ($roomRate * $plannedNights) + $additionalNightsCharge + $restaurantCharges;
    $discount = (float) $r['discount'];
    $tax = (float) $r['tax'];
    $total = max(0, $subtotal + $tax - $discount);
    $balance = max(0, $total - $amountPaid);

    return [
        'reservation_id' => $r['id'], 'reservation_code' => $r['reservation_code'], 'guest_id' => $r['guest_id'],
        'guest_name' => $r['guest_name'], 'guest_whatsapp' => formatWhatsAppNumber($r['guest_phone'] ?? null), 'room_id' => $r['room_id'], 'room_number' => $r['room_number'],
        'check_in_date' => $r['check_in_date'], 'check_out_date' => $r['check_out_date'], 'planned_nights' => $plannedNights,
        'extra_nights' => $extraNights, 'additional_nights_charge' => $additionalNightsCharge, 'restaurant_charges' => $restaurantCharges,
        'subtotal' => $subtotal, 'discount' => $discount, 'tax' => $tax, 'total' => $total,
        'amount_paid' => $amountPaid, 'balance' => $balance, 'line_items' => $lineItems,
    ];
}
