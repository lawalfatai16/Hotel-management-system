<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/report_queries.php';

Auth::requireModuleAccess('reports', true);
$db = Database::connect();

safeExecute(function () use ($db) {
    $type = $_GET['type'] ?? 'revenue';
    if (!array_key_exists($type, REPORT_TYPES)) {
        jsonResponse(['success' => false, 'message' => 'Unknown report type.'], 422);
    }
    $from = $_GET['from'] ?? date('Y-m-01');
    $to = $_GET['to'] ?? date('Y-m-d');
    if (strtotime($to) < strtotime($from)) {
        jsonResponse(['success' => false, 'message' => 'The end date must be after the start date.'], 422);
    }

    $report = buildReport($db, $type, $from, $to);
    jsonResponse(['success' => true] + $report);
});
