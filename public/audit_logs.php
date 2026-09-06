<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireModuleAccess('audit_logs');
$pageTitle = 'Audit Logs';
$activeNav = 'audit_logs';
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
    <main class="p-4 md:p-8 space-y-6">
      <div>
        <div class="font-display text-xl">Audit Logs</div>
        <div class="text-sm text-[--brand-muted]">Every sensitive action taken in the system, in order</div>
      </div>

      <div class="card-surface p-4 flex flex-wrap gap-3 items-center">
        <div class="relative flex-1 min-w-[200px]">
          <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[--brand-muted]"></i>
          <input id="searchInput" type="text" placeholder="Search action or user…" class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-[--brand-ink]/10 focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
        <select id="moduleFilter" class="text-sm rounded-lg border border-[--brand-ink]/10 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[--brand-gold]"><option value="">All modules</option></select>
        <input type="date" id="dateFrom" class="text-sm rounded-lg border border-[--brand-ink]/10 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        <input type="date" id="dateTo" class="text-sm rounded-lg border border-[--brand-ink]/10 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
      </div>

      <div class="card-surface overflow-hidden">
        <div class="table-scroll">
          <table class="w-full text-sm">
            <thead class="bg-[--brand-cream-2] text-left text-xs uppercase tracking-wide text-[--brand-muted]">
              <tr><th class="px-5 py-3">Date/Time</th><th class="px-5 py-3">User</th><th class="px-5 py-3">Module</th><th class="px-5 py-3">Action</th><th class="px-5 py-3">IP</th></tr>
            </thead>
            <tbody id="logTableBody" class="divide-y divide-[--brand-ink]/5"></tbody>
          </table>
        </div>
        <div id="emptyState" class="hidden text-center py-16">
          <i data-lucide="history" class="w-10 h-10 mx-auto text-[--brand-muted] mb-3"></i>
          <div class="font-medium">No log entries found</div>
        </div>
      </div>
      <div id="pagination" class="flex items-center justify-center gap-2 text-sm"></div>
    </main>
  </div>
</div>

<script src="assets/js/app.js"></script>
<script>
let currentPage = 1;

async function loadLogs(page = 1) {
  currentPage = page;
  const params = new URLSearchParams({
    search: document.getElementById('searchInput').value,
    module: document.getElementById('moduleFilter').value,
    date_from: document.getElementById('dateFrom').value,
    date_to: document.getElementById('dateTo').value,
    page,
  });
  const body = document.getElementById('logTableBody');
  body.innerHTML = `<tr><td colspan="5" class="px-5 py-6"><div class="skeleton h-6 rounded"></div></td></tr>`;
  const res = await GMT.api(`api/audit_logs.php?${params.toString()}`);
  if (!res.success) { GMT.toast(res.message || 'Unable to load logs.', 'error'); body.innerHTML=''; return; }

  const moduleFilter = document.getElementById('moduleFilter');
  if (moduleFilter.children.length <= 1) {
    moduleFilter.innerHTML += res.modules.map(m => `<option value="${m}">${m.replace('_',' ').replace(/\b\w/g, c => c.toUpperCase())}</option>`).join('');
  }

  document.getElementById('emptyState').classList.toggle('hidden', res.data.length > 0);
  body.innerHTML = res.data.map(l => `
    <tr class="hover:bg-black/[0.02]">
      <td class="px-5 py-3 text-[--brand-muted]">${new Date(l.created_at).toLocaleString('en-GB',{day:'numeric',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'})}</td>
      <td class="px-5 py-3">${l.username || 'System'}</td>
      <td class="px-5 py-3"><span class="badge badge-reserved">${l.module.replace('_',' ')}</span></td>
      <td class="px-5 py-3">${l.action}</td>
      <td class="px-5 py-3 text-[--brand-muted]">${l.ip_address || 'N/A'}</td>
    </tr>
  `).join('');
  GMT.entrance('#logTableBody tr');
  renderPagination(res.pagination);
}

function renderPagination(p) {
  const el = document.getElementById('pagination');
  if (p.totalPages <= 1) { el.innerHTML = ''; return; }
  let html = '';
  const maxBtns = 10;
  const start = Math.max(1, p.page - 4);
  const end = Math.min(p.totalPages, start + maxBtns - 1);
  for (let i = start; i <= end; i++) html += `<button onclick="loadLogs(${i})" class="w-8 h-8 rounded-lg ${i===p.page ? 'btn-brand' : 'border border-[--brand-ink]/10'}">${i}</button>`;
  el.innerHTML = html;
}

document.getElementById('searchInput').addEventListener('input', GMT.debounce(() => loadLogs(1), 350));
['moduleFilter','dateFrom','dateTo'].forEach(id => document.getElementById(id).addEventListener('change', () => loadLogs(1)));

lucide.createIcons();
loadLogs();
</script>
</body>
</html>
