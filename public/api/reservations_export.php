<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

Auth::requireModuleAccess('reservations', true);
$db = Database::connect();

$search  = trim($_GET['search'] ?? '');
$status  = $_GET['status'] ?? '';
$payment = $_GET['payment_status'] ?? '';

$where = ['r.deleted_at IS NULL'];
$params = [];
if ($search !== '') { $where[] = '(r.reservation_code LIKE :s1 OR g.full_name LIKE :s2 OR rm.room_number LIKE :s3)'; $params[':s1'] = $params[':s2'] = $params[':s3'] = "%{$search}%"; }
if ($status !== '') { $where[] = 'r.status = :status'; $params[':status'] = $status; }
if ($payment !== '') { $where[] = 'r.payment_status = :payment'; $params[':payment'] = $payment; }
$whereSql = implode(' AND ', $where);

$stmt = $db->prepare(
    "SELECT r.reservation_code, g.full_name AS guest_name, rm.room_number, r.check_in_date, r.check_out_date,
            r.number_of_nights, r.room_rate, r.discount, r.tax, r.total_amount, r.payment_status, r.status
     FROM reservations r
     JOIN guests g ON g.id = r.guest_id
     JOIN rooms rm ON rm.id = r.room_id
     WHERE {$whereSql}
     ORDER BY r.check_in_date DESC"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$hotelName = setting('hotel_name', 'GMT Hotel and Events Centre');
$filename = 'GMT_Hotel_Reservations_' . date('Y-m-d') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fputcsv($out, [$hotelName]);
fputcsv($out, ['Generated: ' . date('d M Y H:i')]);
fputcsv($out, []);
fputcsv($out, ['Reservation Code','Guest','Room','Check-in','Check-out','Nights','Room Rate','Discount','Tax','Total','Payment Status','Status']);
foreach ($rows as $r) {
    fputcsv($out, [
        $r['reservation_code'], $r['guest_name'], $r['room_number'],
        $r['check_in_date'], $r['check_out_date'], $r['number_of_nights'],
        $r['room_rate'], $r['discount'], $r['tax'], $r['total_amount'],
        ucfirst($r['payment_status']), ucwords(str_replace('_',' ',$r['status'])),
    ]);
}
fclose($out);
Auth::logAudit(Auth::user()['id'], Auth::user()['username'] . ' exported reservations CSV', 'reservations');
exit;
