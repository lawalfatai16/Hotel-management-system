<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/SimplePdf.php';

Auth::requireModuleAccess('invoices');
$db = Database::connect();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $db->prepare(
    "SELECT inv.*, g.full_name AS guest_name, g.phone AS guest_phone,
            r.reservation_code, rm.room_number
     FROM invoices inv
     JOIN guests g ON g.id = inv.guest_id
     LEFT JOIN reservations r ON r.id = inv.reservation_id
     LEFT JOIN rooms rm ON rm.id = r.room_id
     WHERE inv.id = :id"
);
$stmt->execute([':id' => $id]);
$invoice = $stmt->fetch();
if (!$invoice) { http_response_code(404); die('Invoice not found.'); }

$itemsStmt = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = :id");
$itemsStmt->execute([':id' => $id]);
$items = $itemsStmt->fetchAll();

$hotelName = setting('hotel_name', 'GMT Hotel and Events Centre');
$address = setting('address', '');

$pdf = new SimplePdf();
$pdf->text(50, 60, $hotelName, 18, true);
$pdf->text(50, 78, $address, 9);
$pdf->line(50, 90, 545, 90);

$pdf->text(50, 115, 'Invoice: ' . $invoice['invoice_number'], 11, true);
$pdf->text(50, 132, 'Issued: ' . formatDate($invoice['issued_at']), 10);
$pdf->text(50, 148, 'Billed to: ' . $invoice['guest_name'], 10);
if ($invoice['reservation_code']) {
    $pdf->text(50, 164, 'Reservation: ' . $invoice['reservation_code'] . ($invoice['room_number'] ? ' - Room ' . $invoice['room_number'] : ''), 10);
}

$rows = [];
foreach ($items as $item) {
    $rows[] = [$item['description'], (int) $item['quantity'], formatCurrency((float) $item['unit_price']), formatCurrency((float) $item['line_total'])];
}
$afterTableY = $pdf->table(50, 195, [['Description', 260], ['Qty', 50], ['Unit Price', 100], ['Amount', 100]], $rows);

$totalsY = $afterTableY + 15;
$pdf->text(350, $totalsY, 'Subtotal:', 10); $pdf->text(470, $totalsY, formatCurrency((float) $invoice['subtotal']), 10); $totalsY += 16;
$pdf->text(350, $totalsY, 'Tax:', 10); $pdf->text(470, $totalsY, formatCurrency((float) $invoice['tax']), 10); $totalsY += 16;
$pdf->text(350, $totalsY, 'Discount:', 10); $pdf->text(470, $totalsY, '-' . formatCurrency((float) $invoice['discount']), 10); $totalsY += 16;
$pdf->text(350, $totalsY, 'Total:', 11, true); $pdf->text(470, $totalsY, formatCurrency((float) $invoice['total']), 11, true); $totalsY += 18;
$pdf->text(350, $totalsY, 'Amount Paid:', 10); $pdf->text(470, $totalsY, formatCurrency((float) $invoice['amount_paid']), 10); $totalsY += 16;
$pdf->text(350, $totalsY, 'Balance Due:', 10, true); $pdf->text(470, $totalsY, formatCurrency((float) $invoice['balance']), 10, true);

$pdf->text(50, 780, 'Thank you for staying with ' . $hotelName . '.', 9);

Auth::logAudit(Auth::user()['id'], Auth::user()['username'] . " downloaded PDF invoice {$invoice['invoice_number']}", 'invoices');
$pdf->stream('GMT_Hotel_Invoice_' . $invoice['invoice_number'] . '.pdf');
