<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::requireModuleAccess('invoices', true);
$db = Database::connect();

$search = trim($_GET['search'] ?? '');
$status = $_GET['status'] ?? '';
$format = $_GET['format'] ?? 'csv';

$where = ['1=1'];
$params = [];
if ($search !== '') { $where[] = '(inv.invoice_number LIKE :s1 OR g.full_name LIKE :s2 OR r.reservation_code LIKE :s3)'; $params[':s1'] = $params[':s2'] = $params[':s3'] = "%{$search}%"; }
if ($status !== '') { $where[] = 'inv.status = :status'; $params[':status'] = $status; }
$whereSql = implode(' AND ', $where);

$stmt = $db->prepare(
    "SELECT inv.invoice_number, g.full_name AS guest_name, r.reservation_code, inv.subtotal, inv.discount, inv.tax,
            inv.total, inv.amount_paid, inv.balance, inv.status, inv.issued_at
     FROM invoices inv
     JOIN guests g ON g.id = inv.guest_id
     LEFT JOIN reservations r ON r.id = inv.reservation_id
     WHERE {$whereSql}
     ORDER BY inv.issued_at DESC"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$hotelName = setting('hotel_name', 'GMT Hotel and Events Centre');
$dateStamp = date('Y-m-d');
$headers = ['Invoice #', 'Guest', 'Reservation', 'Subtotal', 'Discount', 'Tax', 'Total', 'Amount Paid', 'Balance', 'Status', 'Issued'];

if ($format === 'xls') {
    // Excel-compatible export: a simple HTML table served with an .xls extension —
    // Excel opens this natively. Swap for PhpSpreadsheet at build time for a native
    // .xlsx binary; see desktop/packaging-instructions.md.
    $filename = 'GMT_Hotel_Invoices_' . $dateStamp . '.xls';
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo "<table border='1'>";
    echo "<tr><td colspan='11'><b>" . e($hotelName) . "</b></td></tr>";
    echo "<tr><td colspan='11'>Generated: " . date('d M Y H:i') . "</td></tr><tr></tr>";
    echo "<tr>" . implode('', array_map(fn($h) => "<th>" . e($h) . "</th>", $headers)) . "</tr>";
    foreach ($rows as $r) {
        echo "<tr>"
            . "<td>" . e($r['invoice_number']) . "</td>"
            . "<td>" . e($r['guest_name']) . "</td>"
            . "<td>" . e($r['reservation_code'] ?: '') . "</td>"
            . "<td>" . number_format((float) $r['subtotal'], 2) . "</td>"
            . "<td>" . number_format((float) $r['discount'], 2) . "</td>"
            . "<td>" . number_format((float) $r['tax'], 2) . "</td>"
            . "<td>" . number_format((float) $r['total'], 2) . "</td>"
            . "<td>" . number_format((float) $r['amount_paid'], 2) . "</td>"
            . "<td>" . number_format((float) $r['balance'], 2) . "</td>"
            . "<td>" . e(ucfirst($r['status'])) . "</td>"
            . "<td>" . e(date('d M Y', strtotime($r['issued_at']))) . "</td>"
            . "</tr>";
    }
    echo "</table>";
} else {
    $filename = 'GMT_Hotel_Invoices_' . $dateStamp . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fputcsv($out, [$hotelName]);
    fputcsv($out, ['Generated: ' . date('d M Y H:i')]);
    fputcsv($out, []);
    fputcsv($out, $headers);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['invoice_number'], $r['guest_name'], $r['reservation_code'] ?: '',
            $r['subtotal'], $r['discount'], $r['tax'], $r['total'], $r['amount_paid'], $r['balance'],
            ucfirst($r['status']), date('d M Y', strtotime($r['issued_at'])),
        ]);
    }
    fclose($out);
}

Auth::logAudit(Auth::user()['id'], Auth::user()['username'] . " exported invoices ({$format})", 'invoices');
exit;
