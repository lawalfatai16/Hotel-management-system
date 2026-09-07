<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::requireModuleAccess('events', true);
$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$user = Auth::user();

switch ($method) {

    case 'GET':
        safeExecute(function () use ($db) {

            if (!empty($_GET['id'])) {
                $stmt = $db->prepare(
                    "SELECT ev.*, ep.name AS package_name FROM events ev
                     LEFT JOIN event_packages ep ON ep.id = ev.package_id
                     WHERE ev.id = :id AND ev.deleted_at IS NULL"
                );
                $stmt->execute([':id' => (int) $_GET['id']]);
                $ev = $stmt->fetch();
                if (!$ev) jsonResponse(['success' => false, 'message' => 'Event not found.'], 404);
                jsonResponse(['success' => true, 'data' => $ev]);
            }

            if (!empty($_GET['view']) && $_GET['view'] === 'calendar') {
                $month = $_GET['month'] ?? date('Y-m');
                $start = $month . '-01';
                $end = date('Y-m-t', strtotime($start));
                $stmt = $db->prepare(
                    "SELECT id, event_code, event_name, client_name, event_type, event_date, start_time, end_time, venue, status
                     FROM events WHERE deleted_at IS NULL AND status != 'cancelled' AND event_date BETWEEN :start AND :end
                     ORDER BY event_date, start_time"
                );
                $stmt->execute([':start' => $start, ':end' => $end]);
                jsonResponse(['success' => true, 'data' => $stmt->fetchAll(), 'month' => $month]);
            }

            $search = trim($_GET['search'] ?? '');
            $type = $_GET['type'] ?? '';
            $status = $_GET['status'] ?? '';
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = 10;
            $offset = ($page - 1) * $perPage;

            $where = ['ev.deleted_at IS NULL'];
            $params = [];
            if ($search !== '') {
                $where[] = '(ev.event_name LIKE :s1 OR ev.client_name LIKE :s2 OR ev.event_code LIKE :s3)';
                $params[':s1'] = $params[':s2'] = $params[':s3'] = "%{$search}%";
            }
            if ($type !== '') { $where[] = 'ev.event_type = :type'; $params[':type'] = $type; }
            if ($status !== '') { $where[] = 'ev.status = :status'; $params[':status'] = $status; }
            $whereSql = implode(' AND ', $where);

            $countStmt = $db->prepare("SELECT COUNT(*) FROM events ev WHERE {$whereSql}");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $stmt = $db->prepare(
                "SELECT ev.*, ep.name AS package_name
                 FROM events ev LEFT JOIN event_packages ep ON ep.id = ev.package_id
                 WHERE {$whereSql}
                 ORDER BY ev.event_date DESC
                 LIMIT {$perPage} OFFSET {$offset}"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$r) $r['price_display'] = formatCurrency((float) $r['price']);

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
            $errors = validateEvent($input);
            if ($errors) jsonResponse(['success' => false, 'message' => implode(' ', $errors)], 422);

            $price = (float) $input['price'];
            $deposit = (float) ($input['deposit'] ?? 0);
            $balance = max(0, $price - $deposit);
            $code = nextSequenceCode($db, 'events', 'event_code', 'EVT-');

            $stmt = $db->prepare(
                "INSERT INTO events (event_code, client_name, client_phone, client_email, event_name, event_type, event_date,
                 start_time, end_time, venue, number_of_guests, package_id, price, deposit, balance, status, special_requirements, created_by)
                 VALUES (:code, :client_name, :client_phone, :client_email, :event_name, :event_type, :event_date,
                 :start_time, :end_time, :venue, :num_guests, :package_id, :price, :deposit, :balance, :status, :requirements, :created_by)"
            );
            $stmt->execute([
                ':code' => $code,
                ':client_name' => $input['client_name'],
                ':client_phone' => $input['client_phone'] ?? null,
                ':client_email' => $input['client_email'] ?? null,
                ':event_name' => $input['event_name'],
                ':event_type' => $input['event_type'],
                ':event_date' => $input['event_date'],
                ':start_time' => $input['start_time'],
                ':end_time' => $input['end_time'],
                ':venue' => $input['venue'],
                ':num_guests' => $input['number_of_guests'],
                ':package_id' => $input['package_id'] ?: null,
                ':price' => $price,
                ':deposit' => $deposit,
                ':balance' => $balance,
                ':status' => $input['status'] ?? 'inquiry',
                ':requirements' => $input['special_requirements'] ?? null,
                ':created_by' => $user['id'],
            ]);

            Auth::logAudit($user['id'], "{$user['username']} created event {$code} ({$input['event_name']})", 'events', null, json_encode($input));
            jsonResponse(['success' => true, 'message' => "Event {$code} created successfully.", 'id' => $db->lastInsertId()]);
        });
        break;

    case 'PUT':
        safeExecute(function () use ($db, $user) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            if (!Auth::verifyCsrf($input['csrf_token'] ?? null)) {
                jsonResponse(['success' => false, 'message' => 'Your session has expired. Please refresh and try again.'], 419);
            }
            $id = (int) ($input['id'] ?? 0);
            if (!$id) jsonResponse(['success' => false, 'message' => 'Missing event id.'], 422);

            $existing = $db->prepare("SELECT * FROM events WHERE id = :id AND deleted_at IS NULL");
            $existing->execute([':id' => $id]);
            $before = $existing->fetch();
            if (!$before) jsonResponse(['success' => false, 'message' => 'Event not found.'], 404);

            if (!empty($input['action'])) {
                $map = ['confirm' => 'confirmed', 'complete' => 'completed', 'cancel' => 'cancelled'];
                $newStatus = $map[$input['action']] ?? null;
                if (!$newStatus) jsonResponse(['success' => false, 'message' => 'Unknown action.'], 422);
                $db->prepare("UPDATE events SET status = :s WHERE id = :id")->execute([':s' => $newStatus, ':id' => $id]);
                Auth::logAudit($user['id'], "{$user['username']} marked event {$before['event_code']} as {$newStatus}", 'events', $before['status'], $newStatus);
                jsonResponse(['success' => true, 'message' => "Event {$newStatus}."]);
            }

            $errors = validateEvent($input);
            if ($errors) jsonResponse(['success' => false, 'message' => implode(' ', $errors)], 422);

            $price = (float) $input['price'];
            $deposit = (float) ($input['deposit'] ?? 0);
            $balance = max(0, $price - $deposit);

            $stmt = $db->prepare(
                "UPDATE events SET client_name=:client_name, client_phone=:client_phone, client_email=:client_email,
                 event_name=:event_name, event_type=:event_type, event_date=:event_date, start_time=:start_time, end_time=:end_time,
                 venue=:venue, number_of_guests=:num_guests, package_id=:package_id, price=:price, deposit=:deposit, balance=:balance,
                 status=:status, special_requirements=:requirements WHERE id=:id"
            );
            $stmt->execute([
                ':client_name' => $input['client_name'], ':client_phone' => $input['client_phone'] ?? null, ':client_email' => $input['client_email'] ?? null,
                ':event_name' => $input['event_name'], ':event_type' => $input['event_type'], ':event_date' => $input['event_date'],
                ':start_time' => $input['start_time'], ':end_time' => $input['end_time'], ':venue' => $input['venue'],
                ':num_guests' => $input['number_of_guests'], ':package_id' => $input['package_id'] ?: null,
                ':price' => $price, ':deposit' => $deposit, ':balance' => $balance,
                ':status' => $input['status'] ?? $before['status'], ':requirements' => $input['special_requirements'] ?? null, ':id' => $id,
            ]);

            Auth::logAudit($user['id'], "{$user['username']} updated event {$before['event_code']}", 'events', json_encode($before), json_encode($input));
            jsonResponse(['success' => true, 'message' => "Event {$before['event_code']} updated successfully."]);
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
            if (!$id) jsonResponse(['success' => false, 'message' => 'Missing event id.'], 422);

            $stmt = $db->prepare("SELECT * FROM events WHERE id = :id AND deleted_at IS NULL");
            $stmt->execute([':id' => $id]);
            $ev = $stmt->fetch();
            if (!$ev) jsonResponse(['success' => false, 'message' => 'Event not found.'], 404);
            if ($ev['status'] === 'confirmed' && strtotime($ev['event_date']) >= strtotime(date('Y-m-d'))) {
                jsonResponse(['success' => false, 'message' => 'This event is confirmed and upcoming. Cancel it first before removing.'], 422);
            }

            $db->prepare("UPDATE events SET deleted_at = NOW() WHERE id = :id")->execute([':id' => $id]);
            Auth::logAudit($user['id'], "{$user['username']} deleted event {$ev['event_code']}", 'events', json_encode($ev), null);
            jsonResponse(['success' => true, 'message' => 'Event removed successfully.']);
        });
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

function validateEvent(array $input): array
{
    $errors = [];
    if (empty($input['client_name'])) $errors[] = 'Client name is required.';
    if (empty($input['event_name'])) $errors[] = 'Event name is required.';
    $validTypes = ['wedding','conference','birthday','meeting','seminar','party','corporate','other'];
    if (empty($input['event_type']) || !in_array($input['event_type'], $validTypes, true)) $errors[] = 'Select a valid event type.';
    if (empty($input['event_date'])) $errors[] = 'Event date is required.';
    if (empty($input['start_time']) || empty($input['end_time'])) $errors[] = 'Start and end time are required.';
    elseif ($input['end_time'] <= $input['start_time']) $errors[] = 'End time must be after start time.';
    if (empty($input['venue'])) $errors[] = 'Venue is required.';
    if (empty($input['number_of_guests']) || (int) $input['number_of_guests'] <= 0) $errors[] = 'Number of guests must be at least 1.';
    if (!isset($input['price']) || (float) $input['price'] <= 0) $errors[] = 'A valid price is required.';
    if (isset($input['deposit']) && (float) $input['deposit'] < 0) $errors[] = 'Deposit cannot be negative.';
    return $errors;
}
