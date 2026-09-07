<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::requireModuleAccess('expenses', true);
$db = Database::connect();
$method = $_SERVER['REQUEST_METHOD'];
$user = Auth::user();
$categories = ['utilities','maintenance','salaries','supplies','food','events','transportation','other'];

switch ($method) {

    case 'GET':
        safeExecute(function () use ($db) {
            if (!empty($_GET['summary'])) {
                $from = $_GET['date_from'] ?? date('Y-m-01');
                $to = $_GET['date_to'] ?? date('Y-m-d');
                $stmt = $db->prepare(
                    "SELECT category, COALESCE(SUM(amount),0) AS total FROM expenses
                     WHERE expense_date BETWEEN :from AND :to GROUP BY category"
                );
                $stmt->execute([':from' => $from, ':to' => $to]);
                $byCategory = [];
                foreach ($stmt->fetchAll() as $row) $byCategory[$row['category']] = (float) $row['total'];

                $totalStmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM expenses WHERE expense_date BETWEEN :from AND :to");
                $totalStmt->execute([':from' => $from, ':to' => $to]);

                jsonResponse(['success' => true, 'data' => ['by_category' => $byCategory, 'total' => (float) $totalStmt->fetchColumn()]]);
            }

            $search = trim($_GET['search'] ?? '');
            $category = $_GET['category'] ?? '';
            $dateFrom = $_GET['date_from'] ?? '';
            $dateTo = $_GET['date_to'] ?? '';
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = 12;
            $offset = ($page - 1) * $perPage;

            $where = ['1=1'];
            $params = [];
            if ($search !== '') { $where[] = 'e.description LIKE :s'; $params[':s'] = "%{$search}%"; }
            if ($category !== '') { $where[] = 'e.category = :cat'; $params[':cat'] = $category; }
            if ($dateFrom !== '') { $where[] = 'e.expense_date >= :from'; $params[':from'] = $dateFrom; }
            if ($dateTo !== '') { $where[] = 'e.expense_date <= :to'; $params[':to'] = $dateTo; }
            $whereSql = implode(' AND ', $where);

            $countStmt = $db->prepare("SELECT COUNT(*) FROM expenses e WHERE {$whereSql}");
            $countStmt->execute($params);
            $total = (int) $countStmt->fetchColumn();

            $stmt = $db->prepare(
                "SELECT e.*, s.full_name AS staff_name FROM expenses e LEFT JOIN staff s ON s.id = e.staff_id
                 WHERE {$whereSql} ORDER BY e.expense_date DESC LIMIT {$perPage} OFFSET {$offset}"
            );
            $stmt->execute($params);
            $rows = $stmt->fetchAll();
            foreach ($rows as &$r) $r['amount_display'] = formatCurrency((float) $r['amount']);

            jsonResponse([
                'success' => true, 'data' => $rows,
                'pagination' => ['page' => $page, 'perPage' => $perPage, 'total' => $total, 'totalPages' => (int) ceil($total / $perPage)],
            ]);
        });
        break;

    case 'POST':
        safeExecute(function () use ($db, $user, $categories) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            if (!Auth::verifyCsrf($input['csrf_token'] ?? null)) {
                jsonResponse(['success' => false, 'message' => 'Your session has expired. Please refresh and try again.'], 419);
            }
            $errors = validateExpense($input, $categories);
            if ($errors) jsonResponse(['success' => false, 'message' => implode(' ', $errors)], 422);

            $stmt = $db->prepare(
                "INSERT INTO expenses (category, description, amount, expense_date, payment_method, staff_id, receipt_reference, notes)
                 VALUES (:cat, :desc, :amount, :date, :method, :staff_id, :ref, :notes)"
            );
            $stmt->execute([
                ':cat' => $input['category'], ':desc' => $input['description'], ':amount' => $input['amount'],
                ':date' => $input['expense_date'], ':method' => $input['payment_method'],
                ':staff_id' => $input['staff_id'] ?: null, ':ref' => $input['receipt_reference'] ?? null, ':notes' => $input['notes'] ?? null,
            ]);

            Auth::logAudit($user['id'], "{$user['username']} recorded expense: {$input['description']} (" . formatCurrency((float)$input['amount']) . ")", 'expenses', null, json_encode($input));
            jsonResponse(['success' => true, 'message' => 'Expense recorded successfully.', 'id' => $db->lastInsertId()]);
        });
        break;

    case 'PUT':
        safeExecute(function () use ($db, $user, $categories) {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            if (!Auth::verifyCsrf($input['csrf_token'] ?? null)) {
                jsonResponse(['success' => false, 'message' => 'Your session has expired. Please refresh and try again.'], 419);
            }
            $id = (int) ($input['id'] ?? 0);
            if (!$id) jsonResponse(['success' => false, 'message' => 'Missing expense id.'], 422);
            $errors = validateExpense($input, $categories);
            if ($errors) jsonResponse(['success' => false, 'message' => implode(' ', $errors)], 422);

            $existing = $db->prepare("SELECT * FROM expenses WHERE id = :id");
            $existing->execute([':id' => $id]);
            $before = $existing->fetch();
            if (!$before) jsonResponse(['success' => false, 'message' => 'Expense not found.'], 404);

            $db->prepare(
                "UPDATE expenses SET category=:cat, description=:desc, amount=:amount, expense_date=:date,
                 payment_method=:method, staff_id=:staff_id, receipt_reference=:ref, notes=:notes WHERE id=:id"
            )->execute([
                ':cat' => $input['category'], ':desc' => $input['description'], ':amount' => $input['amount'],
                ':date' => $input['expense_date'], ':method' => $input['payment_method'],
                ':staff_id' => $input['staff_id'] ?: null, ':ref' => $input['receipt_reference'] ?? null, ':notes' => $input['notes'] ?? null, ':id' => $id,
            ]);

            Auth::logAudit($user['id'], "{$user['username']} updated expense #{$id}", 'expenses', json_encode($before), json_encode($input));
            jsonResponse(['success' => true, 'message' => 'Expense updated successfully.']);
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
            if (!$id) jsonResponse(['success' => false, 'message' => 'Missing expense id.'], 422);

            $stmt = $db->prepare("SELECT * FROM expenses WHERE id = :id");
            $stmt->execute([':id' => $id]);
            $expense = $stmt->fetch();
            if (!$expense) jsonResponse(['success' => false, 'message' => 'Expense not found.'], 404);

            $db->prepare("DELETE FROM expenses WHERE id = :id")->execute([':id' => $id]);
            Auth::logAudit($user['id'], "{$user['username']} deleted expense #{$id}", 'expenses', json_encode($expense), null);
            jsonResponse(['success' => true, 'message' => 'Expense removed successfully.']);
        });
        break;

    default:
        jsonResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

function validateExpense(array $input, array $categories): array
{
    $errors = [];
    if (empty($input['category']) || !in_array($input['category'], $categories, true)) $errors[] = 'Select a valid category.';
    if (empty($input['description'])) $errors[] = 'Description is required.';
    if (!isset($input['amount']) || (float) $input['amount'] <= 0) $errors[] = 'Enter a valid amount.';
    if (empty($input['expense_date'])) $errors[] = 'Expense date is required.';
    $validMethods = ['cash','bank_transfer','pos','card','other'];
    if (empty($input['payment_method']) || !in_array($input['payment_method'], $validMethods, true)) $errors[] = 'Select a valid payment method.';
    return $errors;
}
