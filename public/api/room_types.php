<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::requireModuleAccess('room_types', true);
$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$user = Auth::user();

switch ($method) {

    case 'GET':
        safeExecute(function () use ($db) {
            $search = trim($_GET['search'] ?? '');
            $where = 'rt.deleted_at IS NULL';
            $params = [];
            if ($search !== '') { $where .= ' AND rt.name LIKE :s'; $params[':s'] = "%{$search}%"; }

            $stmt = $db->prepare(
                "SELECT rt.*, (SELECT COUNT(*) FROM rooms r WHERE r.room_type_id = rt.id AND r.deleted_at IS NULL) AS room_count
                 FROM room_types rt WHERE {$where} ORDER BY rt.base_price"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$r) $r['base_price_display'] = formatCurrency((float) $r['base_price']);
            jsonResponse(['success' => true, 'data' => $rows]);
        });
        break;

    case 'POST':
        safeExecute(function () use ($db, $user) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            if (!Auth::verifyCsrf($input['csrf_token'] ?? null)) {
                jsonResponse(['success' => false, 'message' => 'Your session has expired. Please refresh and try again.'], 419);
            }
            $errors = validateRoomType($input);
            if ($errors) jsonResponse(['success' => false, 'message' => implode(' ', $errors)], 422);

            $stmt = $db->prepare(
                "INSERT INTO room_types (name, description, base_price, capacity, amenities) VALUES (:name, :desc, :price, :capacity, :amenities)"
            );
            $stmt->execute([
                ':name' => $input['name'], ':desc' => $input['description'] ?? null,
                ':price' => $input['base_price'], ':capacity' => $input['capacity'], ':amenities' => $input['amenities'] ?? null,
            ]);

            Auth::logAudit($user['id'], "{$user['username']} created room type {$input['name']}", 'room_types', null, json_encode($input));
            jsonResponse(['success' => true, 'message' => 'Room type created successfully.', 'id' => $db->lastInsertId()]);
        });
        break;

    case 'PUT':
        safeExecute(function () use ($db, $user) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            if (!Auth::verifyCsrf($input['csrf_token'] ?? null)) {
                jsonResponse(['success' => false, 'message' => 'Your session has expired. Please refresh and try again.'], 419);
            }
            $id = (int) ($input['id'] ?? 0);
            if (!$id) jsonResponse(['success' => false, 'message' => 'Missing room type id.'], 422);
            $errors = validateRoomType($input);
            if ($errors) jsonResponse(['success' => false, 'message' => implode(' ', $errors)], 422);

            $existing = $db->prepare("SELECT * FROM room_types WHERE id = :id AND deleted_at IS NULL");
            $existing->execute([':id' => $id]);
            $before = $existing->fetch();
            if (!$before) jsonResponse(['success' => false, 'message' => 'Room type not found.'], 404);

            $db->prepare(
                "UPDATE room_types SET name=:name, description=:desc, base_price=:price, capacity=:capacity, amenities=:amenities WHERE id=:id"
            )->execute([
                ':name' => $input['name'], ':desc' => $input['description'] ?? null,
                ':price' => $input['base_price'], ':capacity' => $input['capacity'], ':amenities' => $input['amenities'] ?? null, ':id' => $id,
            ]);

            Auth::logAudit($user['id'], "{$user['username']} updated room type {$input['name']}", 'room_types', json_encode($before), json_encode($input));
            jsonResponse(['success' => true, 'message' => 'Room type updated successfully.']);
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
            if (!$id) jsonResponse(['success' => false, 'message' => 'Missing room type id.'], 422);

            $inUse = $db->prepare("SELECT COUNT(*) FROM rooms WHERE room_type_id = :id AND deleted_at IS NULL");
            $inUse->execute([':id' => $id]);
            if ((int) $inUse->fetchColumn() > 0) {
                jsonResponse(['success' => false, 'message' => 'This room type is assigned to existing rooms and cannot be removed.'], 422);
            }

            $stmt = $db->prepare("SELECT name FROM room_types WHERE id = :id AND deleted_at IS NULL");
            $stmt->execute([':id' => $id]);
            $type = $stmt->fetch();
            if (!$type) jsonResponse(['success' => false, 'message' => 'Room type not found.'], 404);

            $db->prepare("UPDATE room_types SET deleted_at = NOW() WHERE id = :id")->execute([':id' => $id]);
            Auth::logAudit($user['id'], "{$user['username']} deleted room type {$type['name']}", 'room_types', json_encode($type), null);
            jsonResponse(['success' => true, 'message' => 'Room type removed successfully.']);
        });
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

function validateRoomType(array $input): array
{
    $errors = [];
    if (empty($input['name'])) $errors[] = 'Room type name is required.';
    if (!isset($input['base_price']) || (float) $input['base_price'] <= 0) $errors[] = 'A valid base price is required.';
    if (!isset($input['capacity']) || (int) $input['capacity'] <= 0) $errors[] = 'Capacity must be at least 1.';
    return $errors;
}
