<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireModuleAccess('settings');
$pageTitle = 'Settings';
$activeNav = 'settings';
$csrfToken = Auth::csrfToken();
$logoPath = setting('logo_path', '');
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
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="bg-[--brand-cream]">
<div id="toastHost" class="fixed top-4 right-4 z-50 w-80"></div>
<div class="flex min-h-screen">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="flex-1 min-w-0">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>
    <main class="p-4 md:p-8 space-y-6 max-w-3xl">
      <div>
        <div class="font-display text-xl">Settings</div>
        <div class="text-sm text-[--brand-muted]">Hotel identity, currency, and system defaults — Super Admin only</div>
      </div>

      <div class="card-surface p-6">
        <div class="font-medium mb-4">Hotel Logo</div>
        <div class="flex items-center gap-4">
          <div id="logoPreview" class="w-16 h-16 rounded-lg bg-[--brand-cream-2] flex items-center justify-center overflow-hidden">
            <?php if ($logoPath): ?><img src="<?= e($logoPath) ?>" class="w-full h-full object-cover"><?php else: ?><i data-lucide="image" class="w-6 h-6 text-[--brand-muted]"></i><?php endif; ?>
          </div>
          <form id="logoForm" class="flex items-center gap-3">
            <input type="file" name="logo" id="logoInput" accept="image/png,image/jpeg,image/webp" class="text-sm">
            <button type="submit" class="px-4 py-2 text-sm rounded-lg border border-[--brand-ink]/10 hover:bg-black/5">Upload</button>
          </form>
        </div>
      </div>

      <form id="settingsForm" class="card-surface p-6 space-y-5">
        <div>
          <div class="font-medium mb-3">Hotel Identity</div>
          <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2">
              <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Hotel Name</label>
              <input name="hotel_name" id="hotel_name" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm">
            </div>
            <div class="col-span-2">
              <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Address</label>
              <input name="address" id="address" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm">
            </div>
            <div>
              <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Phone</label>
              <input name="phone" id="phone" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm">
            </div>
            <div>
              <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Email</label>
              <input name="email" id="email" type="email" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm">
            </div>
          </div>
        </div>

        <div class="border-t border-[--brand-ink]/10 pt-5">
          <div class="font-medium mb-3">Currency &amp; Tax</div>
          <div class="grid grid-cols-3 gap-4">
            <div>
              <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Currency Code</label>
              <input name="currency_code" id="currency_code" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm" placeholder="NGN">
            </div>
            <div>
              <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Currency Symbol</label>
              <input name="currency_symbol" id="currency_symbol" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm" placeholder="₦">
            </div>
            <div>
              <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Tax Rate (%)</label>
              <input type="number" name="tax_rate" id="tax_rate" min="0" step="0.01" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm">
            </div>
          </div>
        </div>

        <div class="border-t border-[--brand-ink]/10 pt-5">
          <div class="font-medium mb-3">Document Prefixes</div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Invoice Prefix</label>
              <input name="invoice_prefix" id="invoice_prefix" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm">
            </div>
            <div>
              <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Reservation Prefix</label>
              <input name="reservation_prefix" id="reservation_prefix" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm">
            </div>
          </div>
        </div>

        <div class="border-t border-[--brand-ink]/10 pt-5">
          <div class="font-medium mb-3">Formats</div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Date Format (PHP format string)</label>
              <input name="date_format" id="date_format" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm" placeholder="d M Y">
            </div>
            <div>
              <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Time Format (PHP format string)</label>
              <input name="time_format" id="time_format" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm" placeholder="H:i">
            </div>
          </div>
        </div>

        <div class="border-t border-[--brand-ink]/10 pt-5">
          <div class="font-medium mb-3">WhatsApp</div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Country Code (digits only, no +)</label>
              <input name="whatsapp_country_code" id="whatsapp_country_code" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm" placeholder="234">
              <p class="text-xs text-[--brand-muted] mt-1">Used to convert local numbers like 0805... into WhatsApp's international format when sending invoices/slips.</p>
            </div>
          </div>
        </div>

        <div class="flex justify-end pt-2">
          <button type="submit" class="btn-brand px-6 py-2.5 text-sm font-semibold">Save Settings</button>
        </div>
      </form>
    </main>
  </div>
</div>

<script src="assets/js/app.js"></script>
<script>
const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;

async function loadSettings() {
  const res = await GMT.api('api/settings.php');
  if (!res.success) return;
  for (const key in res.data) {
    const field = document.getElementById(key);
    if (field) field.value = res.data[key];
  }
}

document.getElementById('settingsForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const payload = Object.fromEntries(new FormData(e.target).entries());
  payload.csrf_token = CSRF_TOKEN;
  const res = await GMT.api('api/settings.php', { method: 'PUT', body: JSON.stringify(payload) });
  GMT.toast(res.message, res.success ? 'success' : 'error');
});

document.getElementById('logoForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const fd = new FormData();
  fd.append('logo', document.getElementById('logoInput').files[0]);
  fd.append('csrf_token', CSRF_TOKEN);
  const res = await fetch('api/settings.php', { method: 'POST', body: fd });
  const data = await res.json();
  GMT.toast(data.message, data.success ? 'success' : 'error');
  if (data.success) document.getElementById('logoPreview').innerHTML = `<img src="${data.path}" class="w-full h-full object-cover">`;
});

lucide.createIcons();
loadSettings();
</script>
</body>
</html>
