<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/report_queries.php';

Auth::requireModuleAccess('reports', true);
$db = Database::connect();
$user = Auth::user();

$type = $_GET['type'] ?? 'revenue';
if (!array_key_exists($type, REPORT_TYPES)) { http_response_code(422); die('Unknown report type.'); }
$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-d');
$format = $_GET['format'] ?? 'csv';

$report = buildReport($db, $type, $from, $to);
$hotelName = setting('hotel_name', 'GMT Hotel and Events Centre');
$titleSlug = preg_replace('/[^A-Za-z0-9]+/', '_', $report['title']);
$dateStamp = date('Y-m-d');

function formatCell($value, string $type)
{
    if ($value === null) return '';
    return match ($type) {
        'currency' => number_format((float) $value, 2),
        'date' => date('d M Y', strtotime((string) $value)),
        'datetime' => date('d M Y H:i', strtotime((string) $value)),
        'number' => (string) $value,
        default => (string) $value,
    };
}

if ($format === 'xls') {
    $filename = "GMT_Hotel_{$titleSlug}_{$dateStamp}.xls";
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "<table border='1'>";
    echo "<tr><td colspan='" . count($report['columns']) . "'><b>" . e($hotelName) . "</b></td></tr>";
    echo "<tr><td colspan='" . count($report['columns']) . "'>" . e($report['title']) . " · {$from} to {$to} · Generated " . date('d M Y H:i') . "</td></tr><tr></tr>";
    echo "<tr>" . implode('', array_map(fn($c) => "<th>" . e($c['label']) . "</th>", $report['columns'])) . "</tr>";
    foreach ($report['rows'] as $row) {
        echo "<tr>" . implode('', array_map(fn($c) => "<td>" . e(formatCell($row[$c['key']] ?? '', $c['type'])) . "</td>", $report['columns'])) . "</tr>";
    }
    if (!empty($report['totals'])) {
        echo "<tr></tr>";
        foreach ($report['totals'] as $label => $value) {
            echo "<tr><td><b>" . e($label) . "</b></td><td>" . (is_numeric($value) ? number_format((float) $value, 2) : e($value)) . "</td></tr>";
        }
    }
    echo "</table>";
} else {
    $filename = "GMT_Hotel_{$titleSlug}_{$dateStamp}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, [$hotelName]);
    fputcsv($out, [$report['title'] . " · {$from} to {$to} · Generated " . date('d M Y H:i')]);
    fputcsv($out, []);
    fputcsv($out, array_column($report['columns'], 'label'));
    foreach ($report['rows'] as $row) {
        fputcsv($out, array_map(fn($c) => formatCell($row[$c['key']] ?? '', $c['type']), $report['columns']));
    }
    if (!empty($report['totals'])) {
        fputcsv($out, []);
        foreach ($report['totals'] as $label => $value) {
            fputcsv($out, [$label, is_numeric($value) ? number_format((float) $value, 2) : $value]);
        }
    }
    fclose($out);
}

Auth::logAudit($user['id'], "{$user['username']} exported {$report['title']} ({$format})", 'reports');
exit;
