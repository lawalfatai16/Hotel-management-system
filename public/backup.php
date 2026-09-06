<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireModuleAccess('backup');
$pageTitle = 'Backup & Restore';
$activeNav = 'backup';
$csrfToken = Auth::csrfToken();
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
<div id="toastHost" class="fixed top-4 right-4 z-50 w-80"></div>
<div class="flex min-h-screen">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>
  <div class="flex-1 min-w-0">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>
    <main class="p-4 md:p-8 space-y-6 max-w-2xl">
      <div>
        <div class="font-display text-xl">Backup &amp; Restore</div>
        <div class="text-sm text-[--brand-muted]">Full database backup and restore. Super Admin only</div>
      </div>

      <div class="card-surface p-6">
        <div class="font-medium mb-2">Download Backup</div>
        <p class="text-sm text-[--brand-muted] mb-4">Exports every table, structure and data, as a single .sql file you can store safely or use to restore later.</p>
        <a href="api/backup.php?action=export" class="btn-brand inline-flex items-center gap-2 px-5 py-2.5 text-sm font-semibold">
          <i data-lucide="download" class="w-4 h-4"></i> Download Full Backup
        </a>
      </div>

      <div class="card-surface p-6 border-2 border-red-200">
        <div class="font-medium mb-2 text-[--brand-danger]">Restore from Backup</div>
        <p class="text-sm text-[--brand-muted] mb-4">
          <strong>This replaces existing data with what's in the backup file.</strong>
          Only restore a file you exported from this exact system. This cannot be undone;
          take a fresh backup first if you're unsure.
        </p>
        <form id="restoreForm" class="space-y-3">
          <input type="file" name="backup_file" id="backupFile" accept=".sql" required class="text-sm">
          <button type="submit" class="px-5 py-2.5 text-sm font-semibold rounded-lg bg-[--brand-danger] text-white">Restore Database</button>
        </form>
      </div>

      <div class="card-surface overflow-hidden">
        <div class="px-6 py-4 font-medium border-b border-[--brand-ink]/5">Recent Backup Activity</div>
        <div id="historyList" class="divide-y divide-[--brand-ink]/5"></div>
        <div id="historyEmpty" class="hidden px-6 py-8 text-center text-sm text-[--brand-muted]">No backup or restore activity yet.</div>
      </div>
    </main>
  </div>
</div>

<div id="confirmModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
  <div class="glass-panel rounded-2xl w-full max-w-sm p-6 text-center">
    <i data-lucide="alert-triangle" class="w-8 h-8 mx-auto text-[--brand-danger] mb-3"></i>
    <p id="confirmModalMessage" class="text-sm mb-6">Are you sure?</p>
    <div class="flex justify-center gap-3">
      <button onclick="GMT.closeModal('confirmModal')" class="px-5 py-2 text-sm rounded-lg border border-[--brand-ink]/10">Cancel</button>
      <button id="confirmModalYes" class="px-5 py-2 text-sm rounded-lg bg-[--brand-danger] text-white">Yes, Restore</button>
    </div>
  </div>
</div>

<script src="assets/js/app.js"></script>
<script>
const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;

async function loadHistory() {
  const res = await GMT.api('api/backup.php?action=history');
  const list = document.getElementById('historyList');
  if (!res.success) { list.innerHTML = ''; return; }
  document.getElementById('historyEmpty').classList.toggle('hidden', res.data.length > 0);
  list.innerHTML = res.data.map(h => `
    <div class="px-6 py-3 text-sm">
      <div>${h.action}</div>
      <div class="text-xs text-[--brand-muted]">${h.username || 'System'} · ${new Date(h.created_at).toLocaleString('en-GB',{day:'numeric',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'})}</div>
    </div>
  `).join('');
}

document.getElementById('restoreForm').addEventListener('submit', (e) => {
  e.preventDefault();
  const file = document.getElementById('backupFile').files[0];
  if (!file) return;
  GMT.confirmAction(`Restore the database from "${file.name}"? This will overwrite existing data and cannot be undone.`, async () => {
    const fd = new FormData();
    fd.append('backup_file', file);
    fd.append('csrf_token', CSRF_TOKEN);
    fd.append('action', 'restore');
    GMT.toast('Restoring. This may take a moment.', 'info');
    const res = await fetch('api/backup.php?action=restore', { method: 'POST', body: fd });
    const data = await res.json();
    GMT.toast(data.message, data.success ? 'success' : 'error');
    if (data.success) loadHistory();
  });
});

lucide.createIcons();
loadHistory();
</script>
</body>
</html>
