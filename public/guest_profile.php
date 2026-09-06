<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireModuleAccess('guests');
$db = Database::connect();
$activeNav = 'guests';

$id = (int) ($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM guests WHERE id = :id AND deleted_at IS NULL");
$stmt->execute([':id' => $id]);
$guest = $stmt->fetch();
if (!$guest) { http_response_code(404); die('Guest not found.'); }

$pageTitle = $guest['full_name'];

$statsStmt = $db->prepare(
    "SELECT COUNT(*) AS stays,
            COALESCE(SUM(total_amount),0) AS total_spent,
            COALESCE(SUM(CASE WHEN payment_status != 'paid' THEN total_amount ELSE 0 END),0) AS outstanding
     FROM reservations WHERE guest_id = :id AND deleted_at IS NULL AND status != 'cancelled'"
);
$statsStmt->execute([':id' => $id]);
$stats = $statsStmt->fetch();

$currentStmt = $db->prepare(
    "SELECT r.*, rm.room_number FROM reservations r JOIN rooms rm ON rm.id = r.room_id
     WHERE r.guest_id = :id AND r.status IN ('confirmed','checked_in') AND r.deleted_at IS NULL
     ORDER BY r.check_in_date LIMIT 1"
);
$currentStmt->execute([':id' => $id]);
$current = $currentStmt->fetch();

$historyStmt = $db->prepare(
    "SELECT r.*, rm.room_number FROM reservations r JOIN rooms rm ON rm.id = r.room_id
     WHERE r.guest_id = :id AND r.deleted_at IS NULL ORDER BY r.check_in_date DESC"
);
$historyStmt->execute([':id' => $id]);
$history = $historyStmt->fetchAll();

$paymentsStmt = $db->prepare("SELECT * FROM payments WHERE guest_id = :id ORDER BY paid_at DESC");
$paymentsStmt->execute([':id' => $id]);
$payments = $paymentsStmt->fetchAll();

function statusBadgeGuest($status) {
    $map = ['pending'=>'reserved','confirmed'=>'available','checked_in'=>'occupied','checked_out'=>'cleaning','cancelled'=>'out_of_service'];
    $labels = ['pending'=>'Pending','confirmed'=>'Confirmed','checked_in'=>'Checked In','checked_out'=>'Checked Out','cancelled'=>'Cancelled'];
    return '<span class="badge badge-' . ($map[$status] ?? 'maintenance') . '">' . ($labels[$status] ?? $status) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> | GMT Hotel and Events Centre</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-[--brand-cream]">
<div class="flex min-h-screen">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="flex-1 min-w-0">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <main class="p-4 md:p-8 space-y-6">
      <a href="guests.php" class="text-sm text-[--brand-muted] hover:text-[--brand-cognac] flex items-center gap-1">
        <i data-lucide="arrow-left" class="w-4 h-4"></i> Back to Guests
      </a>

      <div class="card-surface p-6 flex flex-wrap items-center gap-6 justify-between">
        <div class="flex items-center gap-4">
          <div class="w-14 h-14 rounded-full bg-[--brand-coffee] text-[--brand-cream] flex items-center justify-center font-display text-xl">
            <?= e(strtoupper(substr($guest['full_name'], 0, 1))) ?>
          </div>
          <div>
            <div class="font-display text-xl"><?= e($guest['full_name']) ?></div>
            <div class="text-sm text-[--brand-muted]"><?= e($guest['phone'] ?: '') ?><?= $guest['phone'] && $guest['email'] ? ' · ' : '' ?><?= e($guest['email'] ?: '') ?></div>
            <div class="text-sm text-[--brand-muted]"><?= e($guest['country'] ?: '') ?></div>
          </div>
        </div>
        <div class="flex gap-6">
          <div class="text-center">
            <div class="text-lg font-semibold"><?= (int) $stats['stays'] ?></div>
            <div class="text-xs text-[--brand-muted]">Stays</div>
          </div>
          <div class="text-center">
            <div class="text-lg font-semibold"><?= formatCurrency((float) $stats['total_spent']) ?></div>
            <div class="text-xs text-[--brand-muted]">Total Spending</div>
          </div>
          <div class="text-center">
            <div class="text-lg font-semibold <?= $stats['outstanding'] > 0 ? 'text-[--brand-danger]' : '' ?>"><?= formatCurrency((float) $stats['outstanding']) ?></div>
            <div class="text-xs text-[--brand-muted]">Outstanding Balance</div>
          </div>
        </div>
      </div>

      <?php if ($current): ?>
      <div class="card-surface p-6">
        <div class="font-medium mb-3">Current Reservation</div>
        <div class="flex flex-wrap gap-6 text-sm">
          <div><span class="text-[--brand-muted]">Room</span><br><span class="font-medium"><?= e($current['room_number']) ?></span></div>
          <div><span class="text-[--brand-muted]">Check-in</span><br><span class="font-medium"><?= formatDate($current['check_in_date']) ?></span></div>
          <div><span class="text-[--brand-muted]">Check-out</span><br><span class="font-medium"><?= formatDate($current['check_out_date']) ?></span></div>
          <div><span class="text-[--brand-muted]">Status</span><br><?= statusBadgeGuest($current['status']) ?></div>
          <div><span class="text-[--brand-muted]">Total</span><br><span class="font-medium"><?= formatCurrency((float) $current['total_amount']) ?></span></div>
        </div>
      </div>
      <?php endif; ?>

      <div class="card-surface overflow-hidden">
        <div class="px-6 py-4 font-medium border-b border-[--brand-ink]/5">Reservation History</div>
        <div class="table-scroll">
          <table class="w-full text-sm">
            <thead class="bg-[--brand-cream-2] text-left text-xs uppercase tracking-wide text-[--brand-muted]">
              <tr><th class="px-5 py-3">Code</th><th class="px-5 py-3">Room</th><th class="px-5 py-3">Check-in</th><th class="px-5 py-3">Check-out</th><th class="px-5 py-3">Total</th><th class="px-5 py-3">Status</th></tr>
            </thead>
            <tbody class="divide-y divide-[--brand-ink]/5">
              <?php foreach ($history as $r): ?>
                <tr>
                  <td class="px-5 py-3 font-medium"><?= e($r['reservation_code']) ?></td>
                  <td class="px-5 py-3"><?= e($r['room_number']) ?></td>
                  <td class="px-5 py-3"><?= formatDate($r['check_in_date']) ?></td>
                  <td class="px-5 py-3"><?= formatDate($r['check_out_date']) ?></td>
                  <td class="px-5 py-3"><?= formatCurrency((float) $r['total_amount']) ?></td>
                  <td class="px-5 py-3"><?= statusBadgeGuest($r['status']) ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$history): ?>
                <tr><td colspan="6" class="px-5 py-8 text-center text-[--brand-muted]">No reservations yet.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card-surface overflow-hidden">
        <div class="px-6 py-4 font-medium border-b border-[--brand-ink]/5">Payment History</div>
        <div class="table-scroll">
          <table class="w-full text-sm">
            <thead class="bg-[--brand-cream-2] text-left text-xs uppercase tracking-wide text-[--brand-muted]">
              <tr><th class="px-5 py-3">Date</th><th class="px-5 py-3">Amount</th><th class="px-5 py-3">Method</th><th class="px-5 py-3">Reference</th></tr>
            </thead>
            <tbody class="divide-y divide-[--brand-ink]/5">
              <?php foreach ($payments as $p): ?>
                <tr>
                  <td class="px-5 py-3"><?= formatDate($p['paid_at']) ?></td>
                  <td class="px-5 py-3"><?= formatCurrency((float) $p['amount']) ?></td>
                  <td class="px-5 py-3"><?= e(ucwords(str_replace('_',' ',$p['payment_method']))) ?></td>
                  <td class="px-5 py-3 text-[--brand-muted]"><?= e($p['reference_number'] ?: 'N/A') ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$payments): ?>
                <tr><td colspan="4" class="px-5 py-8 text-center text-[--brand-muted]">No payments recorded.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
