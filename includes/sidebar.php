<?php
/**
 * Included inside every authenticated page.
 * Expects $activeNav to be set by the parent view (e.g. 'dashboard', 'rooms').
 */
$activeNav = $activeNav ?? '';
$navItems = [
    ['key' => 'dashboard',     'label' => 'Dashboard',        'href' => 'dashboard.php',     'icon' => 'layout-dashboard'],
    ['key' => 'reservations',  'label' => 'Reservations',     'href' => 'reservations.php',  'icon' => 'calendar-check'],
    ['key' => 'guests',        'label' => 'Guests',           'href' => 'guests.php',        'icon' => 'users'],
    ['key' => 'rooms',         'label' => 'Rooms',            'href' => 'rooms.php',         'icon' => 'bed-double'],
    ['key' => 'room_types',    'label' => 'Room Types',       'href' => 'room_types.php',    'icon' => 'layers'],
    ['key' => 'checkin',       'label' => 'Check-In',         'href' => 'checkin.php',       'icon' => 'log-in'],
    ['key' => 'checkout',      'label' => 'Check-Out',        'href' => 'checkout.php',      'icon' => 'log-out'],
    ['key' => 'housekeeping',  'label' => 'Housekeeping',     'href' => 'housekeeping.php',  'icon' => 'sparkles'],
    ['key' => 'payments',      'label' => 'Payments',         'href' => 'payments.php',      'icon' => 'credit-card'],
    ['key' => 'invoices',      'label' => 'Invoices',         'href' => 'invoices.php',      'icon' => 'receipt'],
    ['key' => 'expenses',      'label' => 'Expenses',         'href' => 'expenses.php',      'icon' => 'wallet'],
    ['key' => 'staff',         'label' => 'Staff',            'href' => 'staff.php',         'icon' => 'id-card'],
    ['key' => 'events',        'label' => 'Events',           'href' => 'events.php',        'icon' => 'party-popper'],
    ['key' => 'restaurant',    'label' => 'Restaurant',       'href' => 'restaurant.php',    'icon' => 'utensils'],
    ['key' => 'reports',       'label' => 'Reports',          'href' => 'reports.php',       'icon' => 'bar-chart-3'],
    ['key' => 'analytics',     'label' => 'Analytics',        'href' => 'analytics.php',     'icon' => 'trending-up'],
    ['key' => 'settings',      'label' => 'Settings',         'href' => 'settings.php',      'icon' => 'settings'],
    ['key' => 'audit_logs',    'label' => 'Audit Logs',       'href' => 'audit_logs.php',    'icon' => 'history'],
    ['key' => 'backup',        'label' => 'Backup & Restore', 'href' => 'backup.php',        'icon' => 'database'],
];
?>
<div id="drawerOverlay" class="fixed inset-0 bg-black/40 z-30 hidden lg:hidden"></div>

<aside id="sidebar" class="sidebar fixed lg:sticky top-0 h-screen z-40 w-64 flex flex-col -translate-x-full lg:translate-x-0 transition-all duration-300">
  <div class="flex items-center gap-3 px-5 py-6">
    <div class="w-9 h-9 rounded-lg bg-[--brand-gold] flex items-center justify-center font-display text-[--brand-espresso] font-bold">G</div>
    <div class="sidebar-label">
      <div class="font-display text-sm leading-none">GMT Hotel</div>
      <div class="text-[10px] uppercase tracking-wider text-[--brand-gold]/80 mt-1">&amp; Events Centre</div>
    </div>
  </div>

  <nav class="flex-1 overflow-y-auto px-3 space-y-1 pb-4">
    <?php foreach ($navItems as $item): if (!Auth::moduleAllowed($item['key'])) continue; ?>
      <a href="<?= e($item['href']) ?>"
         class="sidebar-link flex items-center gap-3 px-3 py-2.5 text-sm <?= $activeNav === $item['key'] ? 'is-active' : '' ?>">
        <i data-lucide="<?= e($item['icon']) ?>" class="w-4 h-4 shrink-0"></i>
        <span class="sidebar-label"><?= e($item['label']) ?></span>
      </a>
    <?php endforeach; ?>
  </nav>

  <div class="px-3 pb-5">
    <a href="logout.php" class="sidebar-link flex items-center gap-3 px-3 py-2.5 text-sm text-red-300 hover:!text-red-200 hover:!bg-red-500/10">
      <i data-lucide="log-out" class="w-4 h-4 shrink-0"></i>
      <span class="sidebar-label">Logout</span>
    </a>
  </div>
</aside>
