<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::requireModuleAccess('guests', true);
$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$user = Auth::user();

switch ($method) {

    case 'GET':
        safeExecute(function () use ($db) {
            // Single guest profile fetch
            if (!empty($_GET['id'])) {
                $id = (int) $_GET['id'];
                $stmt = $db->prepare("SELECT * FROM guests WHERE id = :id AND deleted_at IS NULL");
                $stmt->execute([':id' => $id]);
                $guest = $stmt->fetch();
                if (!$guest) jsonResponse(['success' => false, 'message' => 'Guest not found.'], 404);

                $stats = $db->prepare(
                    "SELECT COUNT(*) AS stays,
                            COALESCE(SUM(total_amount),0) AS total_spent,
                            COALESCE(SUM(CASE WHEN payment_status != 'paid' THEN total_amount ELSE 0 END),0) AS outstanding
                     FROM reservations WHERE guest_id = :id AND deleted_at IS NULL AND status != 'cancelled'"
                );
                $stats->execute([':id' => $id]);
                $guest['stats'] = $stats->fetch();

                $resStmt = $db->prepare(
                    "SELECT r.*, rm.room_number FROM reservations r
                     JOIN rooms rm ON rm.id = r.room_id
                     WHERE r.guest_id = :id AND r.deleted_at IS NULL
                     ORDER BY r.check_in_date DESC"
                );
                $resStmt->execute([':id' => $id]);
                $guest['reservations'] = $resStmt->fetchAll();

                $payStmt = $db->prepare("SELECT * FROM payments WHERE guest_id = :id ORDER BY paid_at DESC");
                $payStmt->execute([':id' => $id]);
                $guest['payments'] = $payStmt->fetchAll();

                jsonResponse(['success' => true, 'data' => $guest]);
            }

            // List with search + pagination
            $search = trim($_GET['search'] ?? '');
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = 10;
            $offset = ($page - 1) * $perPage;

            $where = 'g.deleted_at IS NULL';
            $params = [];
            if ($search !== '') {
                $where .= ' AND (g.full_name LIKE :s1 OR g.phone LIKE :s2 OR g.email LIKE :s3)';
                $params[':s1'] = $params[':s2'] = $params[':s3'] = "%{$search}%";
            }

            $countStmt = $db->prepare("SELECT COUNT(*) FROM guests g WHERE {$where}");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $stmt = $db->prepare(
                "SELECT g.*,
                        (SELECT COUNT(*) FROM reservations r WHERE r.guest_id = g.id AND r.deleted_at IS NULL AND r.status != 'cancelled') AS stays,
                        (SELECT COALESCE(SUM(total_amount),0) FROM reservations r WHERE r.guest_id = g.id AND r.deleted_at IS NULL AND r.status != 'cancelled') AS total_spent
                 FROM guests g
                 WHERE {$where}
                 ORDER BY g.created_at DESC
                 LIMIT {$perPage} OFFSET {$offset}"
            );
            $stmt->execute($params);
            $guests = $stmt->fetchAll();

            jsonResponse([
                'success' => true,
                'data' => $guests,
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
            $errors = validateGuest($input);
            if ($errors) jsonResponse(['success' => false, 'message' => implode(' ', $errors)], 422);

            $stmt = $db->prepare(
                "INSERT INTO guests (full_name, phone, email, address, country, id_type, id_number, date_of_birth, emergency_contact_name, emergency_contact_phone, notes)
                 VALUES (:full_name, :phone, :email, :address, :country, :id_type, :id_number, :dob, :ec_name, :ec_phone, :notes)"
            );
            $stmt->execute(guestParams($input));

            Auth::logAudit($user['id'], "{$user['username']} added guest {$input['full_name']}", 'guests', null, json_encode($input));
            jsonResponse(['success' => true, 'message' => 'Guest added successfully.', 'id' => $db->lastInsertId()]);
        });
        break;

    case 'PUT':
        safeExecute(function () use ($db, $user) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            if (!Auth::verifyCsrf($input['csrf_token'] ?? null)) {
                jsonResponse(['success' => false, 'message' => 'Your session has expired. Please refresh and try again.'], 419);
            }
            $id = (int) ($input['id'] ?? 0);
            if (!$id) jsonResponse(['success' => false, 'message' => 'Missing guest id.'], 422);
            $errors = validateGuest($input);
            if ($errors) jsonResponse(['success' => false, 'message' => implode(' ', $errors)], 422);

            $existing = $db->prepare("SELECT * FROM guests WHERE id = :id AND deleted_at IS NULL");
            $existing->execute([':id' => $id]);
            $before = $existing->fetch();
            if (!$before) jsonResponse(['success' => false, 'message' => 'Guest not found.'], 404);

            $params = guestParams($input);
            $params[':id'] = $id;
            $stmt = $db->prepare(
                "UPDATE guests SET full_name=:full_name, phone=:phone, email=:email, address=:address, country=:country,
                 id_type=:id_type, id_number=:id_number, date_of_birth=:dob, emergency_contact_name=:ec_name,
                 emergency_contact_phone=:ec_phone, notes=:notes WHERE id = :id"
            );
            $stmt->execute($params);

            Auth::logAudit($user['id'], "{$user['username']} updated guest {$input['full_name']}", 'guests', json_encode($before), json_encode($input));
            jsonResponse(['success' => true, 'message' => 'Guest updated successfully.']);
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
            if (!$id) jsonResponse(['success' => false, 'message' => 'Missing guest id.'], 422);

            $active = $db->prepare("SELECT COUNT(*) FROM reservations WHERE guest_id = :id AND status IN ('confirmed','checked_in','pending') AND deleted_at IS NULL");
            $active->execute([':id' => $id]);
            if ((int) $active->fetchColumn() > 0) {
                jsonResponse(['success' => false, 'message' => 'This guest has active or upcoming reservations and cannot be removed.'], 422);
            }

            $stmt = $db->prepare("SELECT full_name FROM guests WHERE id = :id AND deleted_at IS NULL");
            $stmt->execute([':id' => $id]);
            $guest = $stmt->fetch();
            if (!$guest) jsonResponse(['success' => false, 'message' => 'Guest not found.'], 404);

            $db->prepare("UPDATE guests SET deleted_at = NOW() WHERE id = :id")->execute([':id' => $id]);
            Auth::logAudit($user['id'], "{$user['username']} deleted guest {$guest['full_name']}", 'guests', json_encode($guest), null);
            jsonResponse(['success' => true, 'message' => 'Guest removed successfully.']);
        });
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

function validateGuest(array $input): array
{
    $errors = [];
    if (empty($input['full_name'])) $errors[] = 'Full name is required.';
    if (empty($input['phone']) && empty($input['email'])) $errors[] = 'Provide at least a phone number or email address.';
    if (!empty($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    return $errors;
}

function guestParams(array $input): array
{
    return [
        ':full_name' => $input['full_name'],
        ':phone' => $input['phone'] ?: null,
        ':email' => $input['email'] ?: null,
        ':address' => $input['address'] ?? null,
        ':country' => $input['country'] ?? null,
        ':id_type' => $input['id_type'] ?? null,
        ':id_number' => $input['id_number'] ?? null,
        ':dob' => $input['date_of_birth'] ?: null,
        ':ec_name' => $input['emergency_contact_name'] ?? null,
        ':ec_phone' => $input['emergency_contact_phone'] ?? null,
        ':notes' => $input['notes'] ?? null,
    ];
}
