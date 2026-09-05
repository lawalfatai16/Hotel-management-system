<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::requireModuleAccess('staff', true);
$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$user = Auth::user();

switch ($method) {

    case 'GET':
        safeExecute(function () use ($db) {
            $search = trim($_GET['search'] ?? '');
            $department = $_GET['department'] ?? '';
            $status = $_GET['status'] ?? '';
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = 10;
            $offset = ($page - 1) * $perPage;

            $where = ['s.deleted_at IS NULL'];
            $params = [];
            if ($search !== '') { $where[] = '(s.full_name LIKE :s1 OR s.email LIKE :s2 OR s.phone LIKE :s3)'; $params[':s1'] = $params[':s2'] = $params[':s3'] = "%{$search}%"; }
            if ($department !== '') { $where[] = 's.department = :dept'; $params[':dept'] = $department; }
            if ($status !== '') { $where[] = 's.status = :status'; $params[':status'] = $status; }
            $whereSql = implode(' AND ', $where);

            $countStmt = $db->prepare("SELECT COUNT(*) FROM staff s WHERE {$whereSql}");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $stmt = $db->prepare(
                "SELECT s.*, r.name AS role_name FROM staff s LEFT JOIN roles r ON r.id = s.role_id
                 WHERE {$whereSql} ORDER BY s.full_name LIMIT {$perPage} OFFSET {$offset}"
            );
            $stmt->execute($params);

            jsonResponse([
                'success' => true, 'data' => $stmt->fetchAll(),
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
            $errors = validateStaff($input);
            if ($errors) jsonResponse(['success' => false, 'message' => implode(' ', $errors)], 422);

            $stmt = $db->prepare(
                "INSERT INTO staff (full_name, department, position, phone, email, role_id, status, date_employed)
                 VALUES (:name, :dept, :position, :phone, :email, :role_id, :status, :employed)"
            );
            $stmt->execute([
                ':name' => $input['full_name'], ':dept' => $input['department'], ':position' => $input['position'],
                ':phone' => $input['phone'] ?? null, ':email' => $input['email'] ?? null,
                ':role_id' => $input['role_id'] ?: null, ':status' => $input['status'] ?? 'active',
                ':employed' => $input['date_employed'],
            ]);

            Auth::logAudit($user['id'], "{$user['username']} added staff member {$input['full_name']}", 'staff', null, json_encode($input));
            jsonResponse(['success' => true, 'message' => 'Staff member added successfully.', 'id' => $db->lastInsertId()]);
        });
        break;

    case 'PUT':
        safeExecute(function () use ($db, $user) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            if (!Auth::verifyCsrf($input['csrf_token'] ?? null)) {
                jsonResponse(['success' => false, 'message' => 'Your session has expired. Please refresh and try again.'], 419);
            }
            $id = (int) ($input['id'] ?? 0);
            if (!$id) jsonResponse(['success' => false, 'message' => 'Missing staff id.'], 422);
            $errors = validateStaff($input);
            if ($errors) jsonResponse(['success' => false, 'message' => implode(' ', $errors)], 422);

            $existing = $db->prepare("SELECT * FROM staff WHERE id = :id AND deleted_at IS NULL");
            $existing->execute([':id' => $id]);
            $before = $existing->fetch();
            if (!$before) jsonResponse(['success' => false, 'message' => 'Staff member not found.'], 404);

            $db->prepare(
                "UPDATE staff SET full_name=:name, department=:dept, position=:position, phone=:phone, email=:email,
                 role_id=:role_id, status=:status, date_employed=:employed WHERE id=:id"
            )->execute([
                ':name' => $input['full_name'], ':dept' => $input['department'], ':position' => $input['position'],
                ':phone' => $input['phone'] ?? null, ':email' => $input['email'] ?? null,
                ':role_id' => $input['role_id'] ?: null, ':status' => $input['status'] ?? $before['status'],
                ':employed' => $input['date_employed'], ':id' => $id,
            ]);

            Auth::logAudit($user['id'], "{$user['username']} updated staff member {$input['full_name']}", 'staff', json_encode($before), json_encode($input));
            jsonResponse(['success' => true, 'message' => 'Staff member updated successfully.']);
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
            if (!$id) jsonResponse(['success' => false, 'message' => 'Missing staff id.'], 422);

            $refs = $db->prepare(
                "SELECT (SELECT COUNT(*) FROM housekeeping_tasks WHERE assigned_staff_id = :id1)
                       + (SELECT COUNT(*) FROM orders WHERE staff_id = :id2) AS refs"
            );
            $refs->execute([':id1' => $id, ':id2' => $id]);
            if ((int) $refs->fetchColumn() > 0) {
                jsonResponse(['success' => false, 'message' => 'This staff member has task or order history and cannot be removed. Set their status to inactive instead.'], 422);
            }

            $stmt = $db->prepare("SELECT full_name FROM staff WHERE id = :id AND deleted_at IS NULL");
            $stmt->execute([':id' => $id]);
            $staff = $stmt->fetch();
            if (!$staff) jsonResponse(['success' => false, 'message' => 'Staff member not found.'], 404);

            $db->prepare("UPDATE staff SET deleted_at = NOW() WHERE id = :id")->execute([':id' => $id]);
            Auth::logAudit($user['id'], "{$user['username']} removed staff member {$staff['full_name']}", 'staff', json_encode($staff), null);
            jsonResponse(['success' => true, 'message' => 'Staff member removed successfully.']);
        });
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

function validateStaff(array $input): array
{
    $errors = [];
    if (empty($input['full_name'])) $errors[] = 'Full name is required.';
    if (empty($input['department'])) $errors[] = 'Department is required.';
    if (empty($input['position'])) $errors[] = 'Position is required.';
    if (empty($input['date_employed'])) $errors[] = 'Date employed is required.';
    return $errors;
}
