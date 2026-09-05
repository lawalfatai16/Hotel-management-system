<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/SimplePdf.php';

Auth::requireModuleAccess('checkin');
$db = Database::connect();

$reservationId = (int) ($_GET['reservation_id'] ?? 0);
$stmt = $db->prepare(
    "SELECT r.*, g.full_name AS guest_name, g.phone AS guest_phone, rm.room_number, rt.name AS room_type_name,
            c.check_in_time
     FROM reservations r
     JOIN guests g ON g.id = r.guest_id
     JOIN rooms rm ON rm.id = r.room_id
     JOIN room_types rt ON rt.id = rm.room_type_id
     LEFT JOIN checkins c ON c.reservation_id = r.id
     WHERE r.id = :id AND r.deleted_at IS NULL
     ORDER BY c.id DESC LIMIT 1"
);
$stmt->execute([':id' => $reservationId]);
$res = $stmt->fetch();
if (!$res) { http_response_code(404); die('Reservation not found.'); }

$hotelName = setting('hotel_name', 'GMT Hotel and Events Centre');
$address = setting('address', '');

$pdf = new SimplePdf();
$pdf->text(50, 60, $hotelName, 18, true);
$pdf->text(50, 78, $address, 9);
$pdf->line(50, 90, 545, 90);

$pdf->text(50, 115, 'Check-In Confirmation', 13, true);
$pdf->text(50, 140, 'Reservation: ' . $res['reservation_code'], 10);
$pdf->text(50, 158, 'Guest: ' . $res['guest_name'], 10);
$pdf->text(50, 176, 'Room: ' . $res['room_number'] . ' (' . $res['room_type_name'] . ')', 10);
$pdf->text(50, 194, 'Number of Guests: ' . $res['number_of_guests'], 10);
$pdf->text(50, 212, 'Check-in Date: ' . formatDate($res['check_in_date']), 10);
$pdf->text(50, 230, 'Check-out Date: ' . formatDate($res['check_out_date']), 10);
$pdf->text(50, 248, 'Checked In At: ' . ($res['check_in_time'] ? date('d M Y H:i', strtotime($res['check_in_time'])) : '-'), 10);

$pdf->line(50, 290, 545, 290);
$pdf->text(50, 320, 'We hope you enjoy your stay at ' . $hotelName . '.', 10);
$pdf->text(50, 780, 'This is a system-generated confirmation.', 9);

Auth::logAudit(Auth::user()['id'], Auth::user()['username'] . " downloaded check-in PDF for {$res['reservation_code']}", 'checkin');
$pdf->stream('GMT_Hotel_CheckIn_' . $res['reservation_code'] . '.pdf');
