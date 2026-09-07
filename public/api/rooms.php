<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::requireModuleAccess('rooms', true);
$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$user = Auth::user();

switch ($method) {

    case 'GET':
        safeExecute(function () use ($db) {
            $search = trim($_GET['search'] ?? '');
            $floor  = $_GET['floor'] ?? '';
            $type   = $_GET['type'] ?? '';
            $status = $_GET['status'] ?? '';
            $page   = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = 12;
            $offset = ($page - 1) * $perPage;

            $where = ['r.deleted_at IS NULL'];
            $params = [];

            if ($search !== '') {
                $where[] = '(r.room_number LIKE :search1 OR rt.name LIKE :search2)';
                $params[':search1'] = $params[':search2'] = "%{$search}%";
            }
            if ($floor !== '') { $where[] = 'r.floor = :floor'; $params[':floor'] = $floor; }
            if ($type !== '')  { $where[] = 'r.room_type_id = :type'; $params[':type'] = $type; }
            if ($status !== ''){ $where[] = 'r.status = :status'; $params[':status'] = $status; }

            $whereSql = implode(' AND ', $where);

            $countStmt = $db->prepare("SELECT COUNT(*) FROM rooms r JOIN room_types rt ON rt.id = r.room_type_id WHERE {$whereSql}");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $stmt = $db->prepare(
                "SELECT r.*, rt.name AS room_type_name
                 FROM rooms r JOIN room_types rt ON rt.id = r.room_type_id
                 WHERE {$whereSql}
                 ORDER BY r.floor, r.room_number
                 LIMIT {$perPage} OFFSET {$offset}"
            );
            $stmt->execute($params);
            $rooms = $stmt->fetchAll();

            foreach ($rooms as &$room) {
                $room['price_display'] = formatCurrency((float) $room['price_per_night']);
            }

            jsonResponse([
                'success' => true,
                'data' => $rooms,
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

            $errors = validateRoom($input);
            if ($errors) jsonResponse(['success' => false, 'message' => implode(' ', $errors)], 422);

            $dupe = $db->prepare("SELECT id FROM rooms WHERE room_number = :n AND deleted_at IS NULL");
            $dupe->execute([':n' => $input['room_number']]);
            if ($dupe->fetch()) jsonResponse(['success' => false, 'message' => 'A room with this number already exists.'], 422);

            $stmt = $db->prepare(
                "INSERT INTO rooms (room_number, room_type_id, floor, price_per_night, capacity, description, amenities, status, image_path)
                 VALUES (:room_number, :room_type_id, :floor, :price, :capacity, :description, :amenities, :status, :image)"
            );
            $stmt->execute([
                ':room_number' => $input['room_number'],
                ':room_type_id' => $input['room_type_id'],
                ':floor' => $input['floor'],
                ':price' => $input['price_per_night'],
                ':capacity' => $input['capacity'],
                ':description' => $input['description'] ?? null,
                ':amenities' => $input['amenities'] ?? null,
                ':status' => $input['status'] ?? 'available',
                ':image' => $input['image_path'] ?? null,
            ]);

            Auth::logAudit($user['id'], "{$user['username']} created Room {$input['room_number']}", 'rooms', null, json_encode($input));
            jsonResponse(['success' => true, 'message' => 'Room created successfully.', 'id' => $db->lastInsertId()]);
        });
        break;

    case 'PUT':
        safeExecute(function () use ($db, $user) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            if (!Auth::verifyCsrf($input['csrf_token'] ?? null)) {
                jsonResponse(['success' => false, 'message' => 'Your session has expired. Please refresh and try again.'], 419);
            }
            $id = (int) ($input['id'] ?? 0);
            if (!$id) jsonResponse(['success' => false, 'message' => 'Missing room id.'], 422);

            $errors = validateRoom($input);
            if ($errors) jsonResponse(['success' => false, 'message' => implode(' ', $errors)], 422);

            $existing = $db->prepare("SELECT * FROM rooms WHERE id = :id AND deleted_at IS NULL");
            $existing->execute([':id' => $id]);
            $before = $existing->fetch();
            if (!$before) jsonResponse(['success' => false, 'message' => 'Room not found.'], 404);

            $dupe = $db->prepare("SELECT id FROM rooms WHERE room_number = :n AND id != :id AND deleted_at IS NULL");
            $dupe->execute([':n' => $input['room_number'], ':id' => $id]);
            if ($dupe->fetch()) jsonResponse(['success' => false, 'message' => 'Another room already uses this number.'], 422);

            $stmt = $db->prepare(
                "UPDATE rooms SET room_number=:room_number, room_type_id=:room_type_id, floor=:floor,
                 price_per_night=:price, capacity=:capacity, description=:description, amenities=:amenities, status=:status, image_path=:image
                 WHERE id = :id"
            );
            $stmt->execute([
                ':room_number' => $input['room_number'],
                ':room_type_id' => $input['room_type_id'],
                ':floor' => $input['floor'],
                ':price' => $input['price_per_night'],
                ':capacity' => $input['capacity'],
                ':description' => $input['description'] ?? null,
                ':amenities' => $input['amenities'] ?? null,
                ':status' => $input['status'] ?? 'available',
                ':image' => $input['image_path'] ?? null,
                ':id' => $id,
            ]);

            Auth::logAudit($user['id'], "{$user['username']} updated Room {$input['room_number']}", 'rooms', json_encode($before), json_encode($input));
            jsonResponse(['success' => true, 'message' => 'Room updated successfully.']);
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
            if (!$id) jsonResponse(['success' => false, 'message' => 'Missing room id.'], 422);

            $stmt = $db->prepare("SELECT room_number, status FROM rooms WHERE id = :id AND deleted_at IS NULL");
            $stmt->execute([':id' => $id]);
            $room = $stmt->fetch();
            if (!$room) jsonResponse(['success' => false, 'message' => 'Room not found.'], 404);
            if (in_array($room['status'], ['occupied', 'reserved'], true)) {
                jsonResponse(['success' => false, 'message' => 'This room cannot be removed while occupied or reserved.'], 422);
            }

            $db->prepare("UPDATE rooms SET deleted_at = NOW() WHERE id = :id")->execute([':id' => $id]);
            Auth::logAudit($user['id'], "{$user['username']} deleted Room {$room['room_number']}", 'rooms', json_encode($room), null);
            jsonResponse(['success' => true, 'message' => 'Room removed successfully.']);
        });
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

function validateRoom(array $input): array
{
    $errors = [];
    if (empty($input['room_number'])) $errors[] = 'Room number is required.';
    if (empty($input['room_type_id'])) $errors[] = 'Room type is required.';
    if (!isset($input['floor']) || $input['floor'] === '') $errors[] = 'Floor is required.';
    if (!isset($input['price_per_night']) || (float) $input['price_per_night'] <= 0) $errors[] = 'A valid price per night is required.';
    if (!isset($input['capacity']) || (int) $input['capacity'] <= 0) $errors[] = 'Capacity must be at least 1.';
    $validStatuses = ['available','reserved','occupied','cleaning','maintenance','out_of_service'];
    if (!empty($input['status']) && !in_array($input['status'], $validStatuses, true)) $errors[] = 'Invalid room status.';
    return $errors;
}
