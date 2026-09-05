<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::requireModuleAccess('housekeeping', true);
$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$user = Auth::user();

switch ($method) {

    case 'GET':
        safeExecute(function () use ($db) {
            // Room status summary counts for the board header
            if (!empty($_GET['summary'])) {
                $counts = ['available' => 0, 'cleaning' => 0, 'maintenance' => 0, 'occupied' => 0, 'reserved' => 0, 'out_of_service' => 0];
                foreach ($db->query("SELECT status, COUNT(*) c FROM rooms WHERE deleted_at IS NULL GROUP BY status") as $row) {
                    if (isset($counts[$row['status']])) $counts[$row['status']] = (int) $row['c'];
                }
                jsonResponse(['success' => true, 'data' => $counts]);
            }

            // Rooms needing attention (cleaning/maintenance) with their latest task
            if (!empty($_GET['rooms'])) {
                $stmt = $db->query(
                    "SELECT rm.id, rm.room_number, rm.floor, rm.status,
                            ht.id AS task_id, ht.task, ht.priority, ht.status AS task_status, ht.assigned_staff_id, s.full_name AS staff_name
                     FROM rooms rm
                     LEFT JOIN housekeeping_tasks ht ON ht.room_id = rm.id AND ht.status != 'inspected'
                        AND ht.id = (SELECT MAX(id) FROM housekeeping_tasks WHERE room_id = rm.id AND status != 'inspected')
                     LEFT JOIN staff s ON s.id = ht.assigned_staff_id
                     WHERE rm.deleted_at IS NULL AND rm.status IN ('cleaning','maintenance')
                     ORDER BY rm.floor, rm.room_number"
                );
                jsonResponse(['success' => true, 'data' => $stmt->fetchAll()]);
            }

            // Task list (filterable)
            $status = $_GET['status'] ?? '';
            $priority = $_GET['priority'] ?? '';
            $where = ['1=1'];
            $params = [];
            if ($status !== '') { $where[] = 'ht.status = :status'; $params[':status'] = $status; }
            if ($priority !== '') { $where[] = 'ht.priority = :priority'; $params[':priority'] = $priority; }
            $whereSql = implode(' AND ', $where);

            $stmt = $db->prepare(
                "SELECT ht.*, rm.room_number, s.full_name AS staff_name
                 FROM housekeeping_tasks ht
                 JOIN rooms rm ON rm.id = ht.room_id
                 LEFT JOIN staff s ON s.id = ht.assigned_staff_id
                 WHERE {$whereSql}
                 ORDER BY FIELD(ht.priority,'urgent','high','normal','low'), ht.created_at DESC
                 LIMIT 100"
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
            if (empty($input['room_id']) || empty($input['task'])) {
                jsonResponse(['success' => false, 'message' => 'Room and task description are required.'], 422);
            }

            $stmt = $db->prepare(
                "INSERT INTO housekeeping_tasks (room_id, assigned_staff_id, task, priority, status, time_assigned)
                 VALUES (:room_id, :staff_id, :task, :priority, 'pending', NOW())"
            );
            $stmt->execute([
                ':room_id' => $input['room_id'],
                ':staff_id' => $input['assigned_staff_id'] ?: null,
                ':task' => $input['task'],
                ':priority' => $input['priority'] ?? 'normal',
            ]);

            $room = $db->prepare("SELECT room_number FROM rooms WHERE id = :id");
            $room->execute([':id' => $input['room_id']]);
            $roomNumber = $room->fetchColumn();

            Auth::logAudit($user['id'], "{$user['username']} assigned housekeeping task for Room {$roomNumber}", 'housekeeping', null, json_encode($input));
            jsonResponse(['success' => true, 'message' => 'Task assigned successfully.', 'id' => $db->lastInsertId()]);
        });
        break;

    case 'PUT':
        safeExecute(function () use ($db, $user) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            if (!Auth::verifyCsrf($input['csrf_token'] ?? null)) {
                jsonResponse(['success' => false, 'message' => 'Your session has expired. Please refresh and try again.'], 419);
            }

            // Quick room-status update (e.g. mark a room "Available" directly from the board)
            if (($input['target'] ?? 'task') === 'room') {
                $roomId = (int) ($input['room_id'] ?? 0);
                $status = $input['status'] ?? '';
                $valid = ['available','reserved','occupied','cleaning','maintenance','out_of_service'];
                if (!$roomId || !in_array($status, $valid, true)) jsonResponse(['success' => false, 'message' => 'Invalid room status update.'], 422);

                $db->prepare("UPDATE rooms SET status = :s WHERE id = :id")->execute([':s' => $status, ':id' => $roomId]);
                Auth::logAudit($user['id'], "{$user['username']} set room status to {$status}", 'housekeeping');
                jsonResponse(['success' => true, 'message' => 'Room status updated.']);
            }

            $id = (int) ($input['id'] ?? 0);
            if (!$id) jsonResponse(['success' => false, 'message' => 'Missing task id.'], 422);

            $existing = $db->prepare("SELECT * FROM housekeeping_tasks WHERE id = :id");
            $existing->execute([':id' => $id]);
            $task = $existing->fetch();
            if (!$task) jsonResponse(['success' => false, 'message' => 'Task not found.'], 404);

            $newStatus = $input['status'] ?? $task['status'];
            $validStatuses = ['pending', 'in_progress', 'completed', 'inspected'];
            if (!in_array($newStatus, $validStatuses, true)) jsonResponse(['success' => false, 'message' => 'Invalid status.'], 422);

            $completedAt = $task['time_completed'];
            if ($newStatus === 'completed' && !$completedAt) $completedAt = date('Y-m-d H:i:s');

            $db->prepare(
                "UPDATE housekeeping_tasks SET status = :status, assigned_staff_id = :staff_id, priority = :priority, time_completed = :completed_at
                 WHERE id = :id"
            )->execute([
                ':status' => $newStatus,
                ':staff_id' => $input['assigned_staff_id'] ?? $task['assigned_staff_id'],
                ':priority' => $input['priority'] ?? $task['priority'],
                ':completed_at' => $completedAt,
                ':id' => $id,
            ]);

            // Sync room status: cleaning while in progress, available once inspected
            if ($newStatus === 'inspected') {
                $db->prepare("UPDATE rooms SET status = 'available' WHERE id = :id")->execute([':id' => $task['room_id']]);
            } elseif (in_array($newStatus, ['pending', 'in_progress'], true)) {
                $db->prepare("UPDATE rooms SET status = 'cleaning' WHERE id = :id AND status NOT IN ('occupied','out_of_service')")->execute([':id' => $task['room_id']]);
            }

            Auth::logAudit($user['id'], "{$user['username']} updated housekeeping task #{$id} to {$newStatus}", 'housekeeping', $task['status'], $newStatus);
            jsonResponse(['success' => true, 'message' => 'Task updated successfully.']);
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
            if (!$id) jsonResponse(['success' => false, 'message' => 'Missing task id.'], 422);

            $stmt = $db->prepare("SELECT * FROM housekeeping_tasks WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $task = $stmt->fetch();
            if (!$task) jsonResponse(['success' => false, 'message' => 'Task not found.'], 404);

            $db->prepare("DELETE FROM housekeeping_tasks WHERE id = :id")->execute([':id' => $id]);
            Auth::logAudit($user['id'], "{$user['username']} removed housekeeping task #{$id}", 'housekeeping', json_encode($task), null);
            jsonResponse(['success' => true, 'message' => 'Task removed successfully.']);
        });
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}
