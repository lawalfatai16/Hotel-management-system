<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireModuleAccess('analytics');
$db = Database::connect();
$pageTitle = 'Analytics';
$activeNav = 'analytics';

$revenueLabels = []; $revenueValues = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM payments WHERE DATE(paid_at) = :d");
    $stmt->execute([':d' => $date]);
    $revenueLabels[] = date('j M', strtotime($date));
    $revenueValues[] = (float) $stmt->fetchColumn();
}

$totalRooms = (int) $db->query("SELECT COUNT(*) FROM rooms WHERE deleted_at IS NULL")->fetchColumn();
$occLabels = []; $occValues = [];
for ($i = 29; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $stmt = $db->prepare("SELECT COUNT(DISTINCT room_id) FROM reservations WHERE deleted_at IS NULL AND status != 'cancelled' AND check_in_date <= :d1 AND check_out_date > :d2");
    $stmt->execute([':d1' => $date, ':d2' => $date]);
    $occ = (int) $stmt->fetchColumn();
    $occLabels[] = date('j M', strtotime($date));
    $occValues[] = $totalRooms > 0 ? round(($occ / $totalRooms) * 100, 1) : 0;
}

$roomTypeRevenue = $db->query(
    "SELECT rt.name, COALESCE(SUM(r.total_amount),0) AS revenue
     FROM room_types rt LEFT JOIN rooms rm ON rm.room_type_id = rt.id
     LEFT JOIN reservations r ON r.room_id = rm.id AND r.status != 'cancelled' AND r.deleted_at IS NULL AND r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
     WHERE rt.deleted_at IS NULL GROUP BY rt.id ORDER BY revenue DESC"
)->fetchAll();

$topGuests = $db->query(
    "SELECT g.full_name, COALESCE(SUM(r.total_amount),0) AS spent
     FROM guests g JOIN reservations r ON r.guest_id = g.id
     WHERE r.status != 'cancelled' AND r.deleted_at IS NULL
     GROUP BY g.id ORDER BY spent DESC LIMIT 5"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?> — GMT Hotel and Events Centre</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-[--brand-cream]">
<div class="flex min-h-screen">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="flex-1 min-w-0">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>
    <main class="p-4 md:p-8 space-y-6">
      <div>
        <div class="font-display text-xl">Analytics</div>
        <div class="text-sm text-[--brand-muted]">30-day trends across revenue, occupancy, and guests</div>
      </div>

      <div class="card-surface p-6">
        <div class="font-medium mb-4">Revenue — Last 30 Days</div>
        <canvas id="revenueTrend" height="80"></canvas>
      </div>

      <div class="grid lg:grid-cols-2 gap-6">
        <div class="card-surface p-6">
          <div class="font-medium mb-4">Occupancy Trend</div>
          <canvas id="occupancyTrend" height="140"></canvas>
        </div>
        <div class="card-surface p-6">
          <div class="font-medium mb-4">Revenue by Room Type (30 days)</div>
          <canvas id="roomTypeChart" height="140"></canvas>
        </div>
      </div>

      <div class="card-surface overflow-hidden">
        <div class="px-6 py-4 font-medium border-b border-[--brand-ink]/5">Top Guests by Spend</div>
        <table class="w-full text-sm">
          <tbody class="divide-y divide-[--brand-ink]/5">
            <?php foreach ($topGuests as $g): ?>
              <tr><td class="px-6 py-3"><?= e($g['full_name']) ?></td><td class="px-6 py-3 text-right font-medium"><?= formatCurrency((float) $g['spent']) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$topGuests): ?><tr><td class="px-6 py-8 text-center text-[--brand-muted]" colspan="2">No guest activity yet.</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </main>
  </div>
</div>

<script src="assets/js/app.js"></script>
<script>
lucide.createIcons();
new Chart(document.getElementById('revenueTrend'), {
  type: 'line',
  data: { labels: <?= json_encode($revenueLabels) ?>, datasets: [{ data: <?= json_encode($revenueValues) ?>, borderColor: '#7A4A2B', backgroundColor: 'rgba(122,74,43,0.08)', fill: true, tension: 0.4, pointRadius: 0 }] },
  options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
});
new Chart(document.getElementById('occupancyTrend'), {
  type: 'line',
  data: { labels: <?= json_encode($occLabels) ?>, datasets: [{ data: <?= json_encode($occValues) ?>, borderColor: '#C89B5A', backgroundColor: 'rgba(200,155,90,0.1)', fill: true, tension: 0.4, pointRadius: 0 }] },
  options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, max: 100 } } }
});
new Chart(document.getElementById('roomTypeChart'), {
  type: 'doughnut',
  data: { labels: <?= json_encode(array_column($roomTypeRevenue, 'name')) ?>, datasets: [{ data: <?= json_encode(array_map('floatval', array_column($roomTypeRevenue, 'revenue'))) ?>, backgroundColor: ['#7A4A2B','#C89B5A','#4A2E1F','#A33B2E'], borderWidth: 0 }] },
  options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } } }
});
</script>
</body>
</html>
