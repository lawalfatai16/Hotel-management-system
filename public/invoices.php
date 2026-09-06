<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireModuleAccess('invoices');
$pageTitle = 'Invoices';
$activeNav = 'invoices';
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

    <main class="p-4 md:p-8 space-y-6">

      <div>
        <div class="font-display text-xl">Invoices</div>
        <div class="text-sm text-[--brand-muted]">Every invoice generated from check-out, with export and void</div>
      </div>

      <div class="card-surface p-4 flex flex-wrap gap-3 items-center">
        <div class="relative flex-1 min-w-[200px]">
          <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[--brand-muted]"></i>
          <input id="searchInput" type="text" placeholder="Search invoice #, guest, or reservation…"
            class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-[--brand-ink]/10 focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
        <select id="statusFilter" class="text-sm rounded-lg border border-[--brand-ink]/10 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
          <option value="">All statuses</option>
          <option value="issued">Issued</option>
          <option value="paid">Paid</option>
          <option value="void">Void</option>
        </select>
        <button onclick="exportFile('csv')" class="px-4 py-2 text-sm rounded-lg border border-[--brand-ink]/10 hover:bg-black/5 flex items-center gap-2">
          <i data-lucide="download" class="w-4 h-4"></i> CSV
        </button>
        <button onclick="exportFile('xls')" class="px-4 py-2 text-sm rounded-lg border border-[--brand-ink]/10 hover:bg-black/5 flex items-center gap-2">
          <i data-lucide="file-spreadsheet" class="w-4 h-4"></i> Excel
        </button>
      </div>

      <div class="card-surface overflow-hidden">
        <div class="table-scroll">
          <table class="w-full text-sm">
            <thead class="bg-[--brand-cream-2] text-left text-xs uppercase tracking-wide text-[--brand-muted]">
              <tr><th class="px-5 py-3">Invoice #</th><th class="px-5 py-3">Guest</th><th class="px-5 py-3">Reservation</th><th class="px-5 py-3">Total</th><th class="px-5 py-3">Balance</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Issued</th><th class="px-5 py-3 text-right">Actions</th></tr>
            </thead>
            <tbody id="invoiceTableBody" class="divide-y divide-[--brand-ink]/5"></tbody>
          </table>
        </div>
        <div id="emptyState" class="hidden text-center py-16">
          <i data-lucide="receipt" class="w-10 h-10 mx-auto text-[--brand-muted] mb-3"></i>
          <div class="font-medium">No invoices found</div>
          <div class="text-sm text-[--brand-muted] mt-1">Invoices are generated automatically when a guest checks out.</div>
        </div>
      </div>
      <div id="pagination" class="flex items-center justify-center gap-2 text-sm"></div>
    </main>
  </div>
</div>

<div id="confirmModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
  <div class="glass-panel rounded-2xl w-full max-w-sm p-6 text-center">
    <i data-lucide="alert-triangle" class="w-8 h-8 mx-auto text-[--brand-danger] mb-3"></i>
    <p id="confirmModalMessage" class="text-sm mb-6">Are you sure?</p>
    <div class="flex justify-center gap-3">
      <button onclick="GMT.closeModal('confirmModal')" class="px-5 py-2 text-sm rounded-lg border border-[--brand-ink]/10">Cancel</button>
      <button id="confirmModalYes" class="px-5 py-2 text-sm rounded-lg bg-[--brand-danger] text-white">Void Invoice</button>
    </div>
  </div>
</div>

<script src="assets/js/app.js"></script>
<script>
const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
let currentPage = 1;

function statusBadge(status) {
  const map = { issued:'reserved', paid:'available', void:'out_of_service' };
  return `<span class="badge badge-${map[status]||'maintenance'}">${status.charAt(0).toUpperCase()+status.slice(1)}</span>`;
}

async function loadInvoices(page = 1) {
  currentPage = page;
  const params = new URLSearchParams({
    search: document.getElementById('searchInput').value,
    status: document.getElementById('statusFilter').value,
    page,
  });
  const body = document.getElementById('invoiceTableBody');
  body.innerHTML = `<tr><td colspan="8" class="px-5 py-6"><div class="skeleton h-6 rounded"></div></td></tr>`;

  const res = await GMT.api(`api/invoices.php?${params.toString()}`);
  if (!res.success) { GMT.toast(res.message || 'Unable to load invoices.', 'error'); body.innerHTML=''; return; }

  document.getElementById('emptyState').classList.toggle('hidden', res.data.length > 0);
  body.innerHTML = res.data.map(inv => `
    <tr class="hover:bg-black/[0.02]">
      <td class="px-5 py-3 font-medium">${inv.invoice_number}</td>
      <td class="px-5 py-3">${inv.guest_name}</td>
      <td class="px-5 py-3">${inv.reservation_code || 'N/A'}</td>
      <td class="px-5 py-3">${inv.total_display}</td>
      <td class="px-5 py-3">${inv.balance_display}</td>
      <td class="px-5 py-3">${statusBadge(inv.status)}</td>
      <td class="px-5 py-3">${new Date(inv.issued_at).toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'})}</td>
      <td class="px-5 py-3 text-right space-x-1 whitespace-nowrap">
        <a href="invoice_print.php?id=${inv.id}" target="_blank" class="text-xs px-2.5 py-1.5 rounded-lg border border-[--brand-ink]/10 hover:bg-black/5 inline-block">View / Print</a>
        <button onclick='sendInvoiceWhatsApp(${inv.id}, "${inv.guest_whatsapp || ''}", "${inv.guest_name.replace(/"/g,'&quot;')}", "${inv.invoice_number}", "${inv.total_display}", "${inv.balance_display}")' class="text-xs px-2.5 py-1.5 rounded-lg border border-green-200 text-green-700 hover:bg-green-50">WhatsApp</button>
        ${inv.status !== 'void' ? `<button onclick="voidInvoice(${inv.id}, '${inv.invoice_number}')" class="text-xs px-2.5 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50">Void</button>` : ''}
      </td>
    </tr>
  `).join('');
  GMT.entrance('#invoiceTableBody tr');
  renderPagination(res.pagination);
}

function renderPagination(p) {
  const el = document.getElementById('pagination');
  if (p.totalPages <= 1) { el.innerHTML = ''; return; }
  let html = '';
  for (let i = 1; i <= p.totalPages; i++) {
    html += `<button onclick="loadInvoices(${i})" class="w-8 h-8 rounded-lg ${i===p.page ? 'btn-brand' : 'border border-[--brand-ink]/10'}">${i}</button>`;
  }
  el.innerHTML = html;
}

function sendInvoiceWhatsApp(id, phone, guestName, invoiceNumber, totalDisplay, balanceDisplay) {
  const message = `Hi ${guestName}, here is your invoice ${invoiceNumber} from <?= e(setting('hotel_name','GMT Hotel and Events Centre')) ?> (Total: ${totalDisplay}, Balance: ${balanceDisplay}). PDF attached.`;
  GMT.sendWhatsApp(phone, message, `api/invoice_pdf.php?id=${id}`);
}

function voidInvoice(id, number) {
  GMT.confirmAction(`Void invoice ${number}? This cannot be undone.`, async () => {
    const res = await GMT.api('api/invoices.php', { method: 'PUT', body: JSON.stringify({ id, action: 'void', csrf_token: CSRF_TOKEN }) });
    GMT.toast(res.message, res.success ? 'success' : 'error');
    if (res.success) loadInvoices(currentPage);
  });
}

function exportFile(format) {
  const params = new URLSearchParams({
    search: document.getElementById('searchInput').value,
    status: document.getElementById('statusFilter').value,
    format,
  });
  window.location.href = `api/invoices_export.php?${params.toString()}`;
}

document.getElementById('searchInput').addEventListener('input', GMT.debounce(() => loadInvoices(1), 350));
document.getElementById('statusFilter').addEventListener('change', () => loadInvoices(1));

lucide.createIcons();
loadInvoices();
</script>
</body>
</html>
