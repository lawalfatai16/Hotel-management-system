<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireModuleAccess('invoices');
$db = Database::connect();

$id = (int) ($_GET['id'] ?? 0);
$stmt = $db->prepare(
    "SELECT inv.*, g.full_name AS guest_name, g.phone AS guest_phone, g.email AS guest_email,
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
$phone = setting('phone', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Invoice <?= e($invoice['invoice_number']) ?> | <?= e($hotelName) ?></title>
<style>
  body { font-family: Georgia, serif; color: #241812; max-width: 720px; margin: 40px auto; padding: 0 20px; }
  h1 { font-size: 22px; margin-bottom: 2px; }
  .muted { color: #8A7A6C; font-size: 13px; }
  table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 14px; }
  th, td { text-align: left; padding: 8px 4px; border-bottom: 1px solid #eee; }
  th { font-size: 11px; text-transform: uppercase; color: #8A7A6C; }
  .totals td { border: none; padding: 4px; }
  .totals .label { color: #8A7A6C; }
  .grand { font-weight: bold; font-size: 16px; border-top: 2px solid #241812; }
  .no-print { margin-top: 24px; }
  @media print { .no-print { display: none; } }
  .btn { display: inline-block; padding: 10px 20px; background: #7A4A2B; color: white; text-decoration: none; border-radius: 6px; font-family: sans-serif; font-size: 14px; }
</style>
</head>
<body>
  <h1><?= e($hotelName) ?></h1>
  <div class="muted"><?= e($address) ?><?= $phone ? ' · ' . e($phone) : '' ?></div>
  <div class="muted" style="margin-top:12px;">Invoice <strong><?= e($invoice['invoice_number']) ?></strong> · Issued <?= formatDate($invoice['issued_at']) ?></div>

  <div style="margin-top:20px;">
    <strong>Billed to:</strong> <?= e($invoice['guest_name']) ?><br>
    <?= $invoice['guest_phone'] ? e($invoice['guest_phone']) . '<br>' : '' ?>
    <?= $invoice['guest_email'] ? e($invoice['guest_email']) . '<br>' : '' ?>
    <?= $invoice['reservation_code'] ? 'Reservation: ' . e($invoice['reservation_code']) . ($invoice['room_number'] ? ', Room ' . e($invoice['room_number']) : '') : '' ?>
  </div>

  <table>
    <thead><tr><th>Description</th><th>Qty</th><th>Unit Price</th><th>Amount</th></tr></thead>
    <tbody>
      <?php foreach ($items as $item): ?>
        <tr>
          <td><?= e($item['description']) ?></td>
          <td><?= (int) $item['quantity'] ?></td>
          <td><?= formatCurrency((float) $item['unit_price']) ?></td>
          <td><?= formatCurrency((float) $item['line_total']) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <table class="totals" style="max-width:300px; margin-left:auto;">
    <tr><td class="label">Subtotal</td><td style="text-align:right;"><?= formatCurrency((float) $invoice['subtotal']) ?></td></tr>
    <tr><td class="label">Tax</td><td style="text-align:right;"><?= formatCurrency((float) $invoice['tax']) ?></td></tr>
    <tr><td class="label">Discount</td><td style="text-align:right;">−<?= formatCurrency((float) $invoice['discount']) ?></td></tr>
    <tr class="grand"><td>Total</td><td style="text-align:right;"><?= formatCurrency((float) $invoice['total']) ?></td></tr>
    <tr><td class="label">Amount Paid</td><td style="text-align:right;"><?= formatCurrency((float) $invoice['amount_paid']) ?></td></tr>
    <tr><td class="label">Balance Due</td><td style="text-align:right;"><?= formatCurrency((float) $invoice['balance']) ?></td></tr>
  </table>

  <div class="no-print">
    <a href="#" onclick="window.print(); return false;" class="btn">Print / Save as PDF</a>
  </div>
</body>
</html>
