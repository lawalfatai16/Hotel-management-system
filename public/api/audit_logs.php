<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::requireModuleAccess('audit_logs', true);
$db = Database::connect();

safeExecute(function () use ($db) {
    $search = trim($_GET['search'] ?? '');
    $module = $_GET['module'] ?? '';
    $dateFrom = $_GET['date_from'] ?? '';
    $dateTo = $_GET['date_to'] ?? '';
    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = 20;
    $offset = ($page - 1) * $perPage;

    $where = ['1=1'];
    $params = [];
    if ($search !== '') { $where[] = '(al.action LIKE :s1 OR u.username LIKE :s2)'; $params[':s1'] = $params[':s2'] = "%{$search}%"; }
    if ($module !== '') { $where[] = 'al.module = :module'; $params[':module'] = $module; }
    if ($dateFrom !== '') { $where[] = 'DATE(al.created_at) >= :from'; $params[':from'] = $dateFrom; }
    if ($dateTo !== '') { $where[] = 'DATE(al.created_at) <= :to'; $params[':to'] = $dateTo; }
    $whereSql = implode(' AND ', $where);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id WHERE {$whereSql}");
    $countStmt->execute($params);
    $total = (int) $countStmt->fetchColumn();

    $stmt = $db->prepare(
        "SELECT al.*, u.username FROM audit_logs al LEFT JOIN users u ON u.id = al.user_id
         WHERE {$whereSql} ORDER BY al.created_at DESC LIMIT {$perPage} OFFSET {$offset}"
    );
    $stmt->execute($params);

    $modulesStmt = $db->query("SELECT DISTINCT module FROM audit_logs ORDER BY module");

    jsonResponse([
        'success' => true,
        'data' => $stmt->fetchAll(),
        'modules' => $modulesStmt->fetchAll(PDO::FETCH_COLUMN),
        'pagination' => ['page' => $page, 'perPage' => $perPage, 'total' => $total, 'totalPages' => (int) ceil($total / $perPage)],
    ]);
});
