<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireModuleAccess('dashboard');
$db = Database::connect();
$pageTitle = 'Dashboard';
$activeNav = 'dashboard';

// ---- KPI queries (safe defaults if tables are empty on a fresh install) ----
function scalar(PDO $db, string $sql, array $params = []): float
{
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return (float) ($stmt->fetchColumn() ?: 0);
}

$todayRevenue      = scalar($db, "SELECT COALESCE(SUM(amount),0) FROM payments WHERE DATE(paid_at) = CURDATE()");
$occupiedRooms      = scalar($db, "SELECT COUNT(*) FROM rooms WHERE status = 'occupied' AND deleted_at IS NULL");
$availableRooms      = scalar($db, "SELECT COUNT(*) FROM rooms WHERE status = 'available' AND deleted_at IS NULL");
$totalRooms      = scalar($db, "SELECT COUNT(*) FROM rooms WHERE deleted_at IS NULL");
$todaysCheckins      = scalar($db, "SELECT COUNT(*) FROM reservations WHERE check_in_date = CURDATE() AND deleted_at IS NULL");
$todaysCheckouts      = scalar($db, "SELECT COUNT(*) FROM reservations WHERE check_out_date = CURDATE() AND deleted_at IS NULL");
$pendingReservations  = scalar($db, "SELECT COUNT(*) FROM reservations WHERE status = 'pending' AND deleted_at IS NULL");
$totalGuests      = scalar($db, "SELECT COUNT(*) FROM guests WHERE deleted_at IS NULL");
$eventBookings      = scalar($db, "SELECT COUNT(*) FROM events WHERE event_date >= CURDATE() AND deleted_at IS NULL");

$occupancyRate = $totalRooms > 0 ? round(($occupiedRooms / $totalRooms) * 100) : 0;

// ---- Chart data: last 7 days revenue ----
$revenueLabels = [];
$revenueValues = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $revenueLabels[] = date('D', strtotime($date));
    $revenueValues[] = scalar($db, "SELECT COALESCE(SUM(amount),0) FROM payments WHERE DATE(paid_at) = ?", [$date]);
}

// ---- Chart data: booking statistics ----
$bookingStats = ['confirmed' => 0, 'pending' => 0, 'cancelled' => 0, 'checked_in' => 0, 'checked_out' => 0];
foreach ($db->query("SELECT status, COUNT(*) c FROM reservations WHERE deleted_at IS NULL GROUP BY status") as $row) {
    if (isset($bookingStats[$row['status']])) $bookingStats[$row['status']] = (int) $row['c'];
}

// ---- Revenue sources ----
$accomRevenue = scalar($db, "SELECT COALESCE(SUM(amount),0) FROM payments WHERE reservation_id IS NOT NULL");
$eventRevenue = scalar($db, "SELECT COALESCE(SUM(amount),0) FROM payments WHERE event_id IS NOT NULL");
$restaurantRevenue = scalar($db, "SELECT COALESCE(SUM(total),0) FROM orders WHERE status = 'paid'");
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
<div id="toastHost" class="fixed top-4 right-4 z-50 w-80"></div>

<div class="flex min-h-screen">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="flex-1 min-w-0">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <main class="p-4 md:p-8 space-y-8">

      <!-- Welcome / hero strip -->
      <div class="relative rounded-2xl overflow-hidden">
        <div class="hero-rotator h-40">
          <div class="hero-slide is-active" style="background:linear-gradient(120deg,#2b1b12,#7a4a2b);"></div>
          <div class="hero-overlay"></div>
        </div>
        <div class="absolute inset-0 flex items-center px-8">
          <div class="glass-panel-dark rounded-xl px-6 py-4 text-[--brand-cream]">
            <div class="font-display text-xl">Welcome back, <?= e($currentUser['username'] ?? '') ?></div>
            <div class="text-sm text-[--brand-cream]/70 mt-1"><?= (int) $occupancyRate ?>% occupancy today · <?= (int) $todaysCheckins ?> check-ins expected</div>
          </div>
        </div>
      </div>

      <!-- KPI cards -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
        <?php
        $kpis = [
            ['label' => "Today's Revenue",     'value' => formatCurrency($todayRevenue), 'icon' => 'banknote'],
            ['label' => 'Occupied Rooms',        'value' => (int)$occupiedRooms . ' / ' . (int)$totalRooms, 'icon' => 'bed-double'],
            ['label' => 'Available Rooms',        'value' => (int) $availableRooms, 'icon' => 'door-open'],
            ['label' => "Today's Check-ins",        'value' => (int) $todaysCheckins, 'icon' => 'log-in'],
            ['label' => "Today's Check-outs",        'value' => (int) $todaysCheckouts, 'icon' => 'log-out'],
            ['label' => 'Pending Reservations',        'value' => (int) $pendingReservations, 'icon' => 'clock'],
            ['label' => 'Total Guests',        'value' => (int) $totalGuests, 'icon' => 'users'],
            ['label' => 'Event Bookings',        'value' => (int) $eventBookings, 'icon' => 'party-popper'],
        ];
        foreach ($kpis as $kpi): ?>
          <div class="kpi-card p-5 opacity-0">
            <div class="flex items-center justify-between mb-3">
              <div class="w-9 h-9 rounded-lg bg-[--brand-cream-2] flex items-center justify-center">
                <i data-lucide="<?= e($kpi['icon']) ?>" class="w-4 h-4 text-[--brand-cognac]"></i>
              </div>
            </div>
            <div class="text-xl font-semibold"><?= $kpi['value'] ?></div>
            <div class="text-xs text-[--brand-muted] mt-1"><?= e($kpi['label']) ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Charts -->
      <div class="grid lg:grid-cols-3 gap-6">
        <div class="card-surface p-6 lg:col-span-2">
          <div class="font-medium mb-4">Revenue — last 7 days</div>
          <canvas id="revenueChart" height="110"></canvas>
        </div>
        <div class="card-surface p-6">
          <div class="font-medium mb-4">Booking Statistics</div>
          <canvas id="bookingChart" height="200"></canvas>
        </div>
      </div>

      <div class="grid lg:grid-cols-3 gap-6">
        <div class="card-surface p-6">
          <div class="font-medium mb-4">Revenue Sources</div>
          <canvas id="revenueSourceChart" height="200"></canvas>
        </div>
        <div class="card-surface p-6 lg:col-span-2">
          <div class="font-medium mb-4">Occupancy</div>
          <div class="flex items-center gap-6">
            <div class="relative w-32 h-32 shrink-0">
              <canvas id="occupancyChart"></canvas>
              <div class="absolute inset-0 flex items-center justify-center font-display text-2xl"><?= (int) $occupancyRate ?>%</div>
            </div>
            <div class="text-sm text-[--brand-muted] space-y-1">
              <div><span class="font-medium text-[--brand-ink]"><?= (int) $occupiedRooms ?></span> rooms occupied</div>
              <div><span class="font-medium text-[--brand-ink]"><?= (int) $availableRooms ?></span> rooms available</div>
              <div><span class="font-medium text-[--brand-ink]"><?= (int) $totalRooms ?></span> total rooms</div>
            </div>
          </div>
        </div>
      </div>

    </main>
  </div>
</div>

<script src="assets/js/app.js"></script>
<script>
  lucide.createIcons();
  GMT.entrance('.kpi-card');
  GMT.motionReady.then((m) => {
    if (!m) return;
    const heroStrip = document.querySelector('.hero-rotator').closest('.relative');
    m.animate(heroStrip, { opacity: [0, 1], y: [-10, 0] }, { duration: 0.5, easing: [0.22, 1, 0.36, 1] });
  });

  const brandGold = '#C89B5A', brandCognac = '#7A4A2B', brandCoffee = '#4A2E1F', brandCream2 = '#EFE6D6';

  new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: {
      labels: <?= json_encode($revenueLabels) ?>,
      datasets: [{
        label: 'Revenue',
        data: <?= json_encode($revenueValues) ?>,
        borderColor: brandCognac,
        backgroundColor: 'rgba(122,74,43,0.08)',
        tension: 0.4,
        fill: true,
        pointRadius: 3,
      }]
    },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
  });

  new Chart(document.getElementById('bookingChart'), {
    type: 'doughnut',
    data: {
      labels: ['Confirmed', 'Pending', 'Cancelled', 'Checked-in', 'Checked-out'],
      datasets: [{
        data: <?= json_encode(array_values($bookingStats)) ?>,
        backgroundColor: [brandCognac, brandGold, '#A33B2E', brandCoffee, '#8A7A6C'],
        borderWidth: 0,
      }]
    },
    options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } } }
  });

  new Chart(document.getElementById('revenueSourceChart'), {
    type: 'bar',
    data: {
      labels: ['Accommodation', 'Events', 'Restaurant'],
      datasets: [{
        data: [<?= $accomRevenue ?>, <?= $eventRevenue ?>, <?= $restaurantRevenue ?>],
        backgroundColor: [brandCognac, brandGold, brandCoffee],
        borderRadius: 6,
      }]
    },
    options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
  });

  new Chart(document.getElementById('occupancyChart'), {
    type: 'doughnut',
    data: {
      datasets: [{
        data: [<?= $occupancyRate ?>, <?= 100 - $occupancyRate ?>],
        backgroundColor: [brandCognac, brandCream2],
        borderWidth: 0,
      }]
    },
    options: { cutout: '75%', plugins: { legend: { display: false }, tooltip: { enabled: false } } }
  });
</script>
</body>
</html>
