<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireModuleAccess('checkout');
$pageTitle = 'Check-Out';
$activeNav = 'checkout';
$csrfToken = Auth::csrfToken();
$hotelName = setting('hotel_name', 'GMT Hotel and Events Centre');
$currencySymbol = setting('currency_symbol', '₦');
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
<style>
  @media print {
    #sidebar, header, .no-print { display: none !important; }
    main { padding: 0 !important; }
    #invoiceSlip { display: block !important; }
  }
</style>
</head>
<body class="bg-[--brand-cream]">
<div id="toastHost" class="fixed top-4 right-4 z-50 w-80 no-print"></div>

<div class="flex min-h-screen">
  <?php include __DIR__ . '/../includes/sidebar.php'; ?>

  <div class="flex-1 min-w-0">
    <?php include __DIR__ . '/../includes/topbar.php'; ?>

    <main class="p-4 md:p-8 space-y-6">
      <div class="no-print">
        <div class="font-display text-xl">Guest Check-Out</div>
        <div class="text-sm text-[--brand-muted]">Currently checked-in guests. Review the final bill before checking out</div>
      </div>

      <div class="card-surface p-4 no-print">
        <div class="relative max-w-md">
          <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[--brand-muted]"></i>
          <input id="searchInput" type="text" placeholder="Search reservation code, guest, or room…"
            class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-[--brand-ink]/10 focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
      </div>

      <div id="activeList" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 no-print"></div>
      <div id="emptyState" class="hidden text-center py-16 card-surface no-print">
        <i data-lucide="door-open" class="w-10 h-10 mx-auto text-[--brand-muted] mb-3"></i>
        <div class="font-medium">No guests currently checked in</div>
        <div class="text-sm text-[--brand-muted] mt-1">Guests who have checked in will appear here for check-out.</div>
      </div>
    </main>
  </div>
</div>

<!-- Checkout / invoice preview modal -->
<div id="checkoutModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4 no-print">
  <div class="glass-panel rounded-2xl w-full max-w-xl p-6 md:p-8 max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between mb-6">
      <div class="font-display text-lg">Final Invoice</div>
      <button onclick="GMT.closeModal('checkoutModal')" class="text-[--brand-muted] hover:text-[--brand-ink]"><i data-lucide="x" class="w-5 h-5"></i></button>
    </div>
    <div id="checkoutDetail" class="space-y-4 text-sm"></div>
    <div class="mt-4">
      <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Notes (optional)</label>
      <textarea id="checkoutNotes" rows="2" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]"></textarea>
    </div>
    <div class="flex justify-end gap-3 pt-6">
      <button type="button" onclick="GMT.closeModal('checkoutModal')" class="px-5 py-2.5 text-sm rounded-lg border border-[--brand-ink]/10">Cancel</button>
      <button id="confirmCheckoutBtn" class="btn-brand px-5 py-2.5 text-sm font-semibold">Confirm Check-Out</button>
    </div>
    <div id="checkoutPostActions" class="hidden flex justify-end gap-3 pt-3 border-t border-[--brand-ink]/10 mt-3">
      <button onclick="printInvoice(window.__lastInvoiceNumber)" class="px-4 py-2 text-xs rounded-lg border border-[--brand-ink]/10 hover:bg-black/5">Print Invoice</button>
      <button onclick="sendCheckoutWhatsApp()" class="px-4 py-2 text-xs rounded-lg border border-green-200 text-green-700 hover:bg-green-50 flex items-center gap-1.5"><i data-lucide="message-circle" class="w-3.5 h-3.5"></i> Send via WhatsApp</button>
    </div>
  </div>
</div>

<!-- Printable invoice -->
<div id="invoiceSlip" class="hidden">
  <div style="padding:40px;font-family:Georgia,serif;">
    <h1 style="font-size:22px;margin-bottom:4px;"><?= e($hotelName) ?></h1>
    <div style="font-size:13px;color:#555;margin-bottom:24px;">Guest Invoice</div>
    <div id="invoiceContent" style="font-size:14px;line-height:1.8;"></div>
  </div>
</div>

<script src="assets/js/app.js"></script>
<script>
const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
const CURRENCY = <?= json_encode($currencySymbol) ?>;
let selectedReservationId = null;

function money(n) { return CURRENCY + Number(n).toLocaleString(undefined,{minimumFractionDigits:2}); }

async function loadActive() {
  const params = new URLSearchParams({ search: document.getElementById('searchInput').value });
  const list = document.getElementById('activeList');
  list.innerHTML = Array.from({length:3}).map(() => `<div class="skeleton h-32 rounded-xl"></div>`).join('');

  const res = await GMT.api(`api/checkout.php?${params.toString()}`);
  if (!res.success) { GMT.toast(res.message || 'Unable to load guests.', 'error'); list.innerHTML=''; return; }

  document.getElementById('emptyState').classList.toggle('hidden', res.data.length > 0);
  list.innerHTML = res.data.map(r => `
    <div class="kpi-card p-5">
      <div class="flex items-start justify-between mb-2">
        <div class="font-display text-lg">${r.guest_name}</div>
        <span class="badge badge-occupied">${r.reservation_code}</span>
      </div>
      <div class="text-sm text-[--brand-muted] mb-4">Room ${r.room_number} · Due out ${new Date(r.check_out_date).toLocaleDateString('en-GB',{day:'numeric',month:'short'})}</div>
      <button onclick="openCheckout(${r.id})" class="btn-brand w-full py-2 text-sm font-semibold">Review &amp; Check Out</button>
    </div>
  `).join('');
  GMT.entrance('#activeList > div');
  lucide.createIcons();
}

async function openCheckout(id) {
  const res = await GMT.api(`api/checkout.php?id=${id}`);
  if (!res.success) { GMT.toast(res.message, 'error'); return; }
  const s = res.data;
  selectedReservationId = id;
  window.__invoiceData = s;

  const lineRows = s.line_items.map(li => `
    <tr><td class="py-1.5">${li.label}</td><td class="py-1.5 text-right">${money(li.amount)}</td></tr>
  `).join('');

  document.getElementById('checkoutDetail').innerHTML = `
    <div class="grid grid-cols-2 gap-2 text-xs text-[--brand-muted] mb-2">
      <div>Guest: <span class="font-medium text-[--brand-ink]">${s.guest_name}</span></div>
      <div>Room: <span class="font-medium text-[--brand-ink]">${s.room_number}</span></div>
      <div>Check-in: <span class="font-medium text-[--brand-ink]">${s.check_in_date}</span></div>
      <div>Planned check-out: <span class="font-medium text-[--brand-ink]">${s.check_out_date}</span></div>
    </div>
    ${s.extra_nights > 0 ? `<div class="rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-xs px-3 py-2">${s.extra_nights} night(s) beyond the planned check-out date have been added.</div>` : ''}
    <table class="w-full text-sm mt-2">
      <tbody>${lineRows}</tbody>
      <tfoot class="border-t border-[--brand-ink]/10">
        <tr><td class="py-1.5 text-[--brand-muted]">Subtotal</td><td class="py-1.5 text-right">${money(s.subtotal)}</td></tr>
        <tr><td class="py-1.5 text-[--brand-muted]">Tax</td><td class="py-1.5 text-right">${money(s.tax)}</td></tr>
        <tr><td class="py-1.5 text-[--brand-muted]">Discount</td><td class="py-1.5 text-right">−${money(s.discount)}</td></tr>
        <tr class="font-semibold"><td class="py-1.5">Total</td><td class="py-1.5 text-right">${money(s.total)}</td></tr>
        <tr><td class="py-1.5 text-[--brand-muted]">Amount Paid</td><td class="py-1.5 text-right">${money(s.amount_paid)}</td></tr>
        <tr class="font-semibold ${s.balance > 0 ? 'text-[--brand-danger]' : 'text-green-700'}"><td class="py-1.5">Balance Due</td><td class="py-1.5 text-right">${money(s.balance)}</td></tr>
      </tfoot>
    </table>
    ${s.balance > 0 ? `<div class="rounded-lg bg-red-50 border border-red-200 text-red-700 text-xs px-3 py-2">There is an outstanding balance. Record the remaining payment in the Payments module before or after check-out.</div>` : ''}
  `;
  document.getElementById('checkoutNotes').value = '';
  document.getElementById('confirmCheckoutBtn').classList.remove('hidden');
  document.getElementById('checkoutPostActions').classList.add('hidden');
  GMT.openModal('checkoutModal');
}

document.getElementById('confirmCheckoutBtn').addEventListener('click', async () => {
  if (!selectedReservationId) return;
  const res = await GMT.api('api/checkout.php', {
    method: 'POST',
    body: JSON.stringify({ reservation_id: selectedReservationId, notes: document.getElementById('checkoutNotes').value, csrf_token: CSRF_TOKEN }),
  });
  GMT.toast(res.message, res.success ? 'success' : 'error');
  if (res.success) {
    window.__lastInvoiceNumber = res.invoice_number;
    window.__lastInvoiceId = res.invoice_id;
    document.getElementById('confirmCheckoutBtn').classList.add('hidden');
    document.getElementById('checkoutPostActions').classList.remove('hidden');
    lucide.createIcons();
    loadActive();
  }
});

function sendCheckoutWhatsApp() {
  const s = window.__invoiceData;
  if (!s || !window.__lastInvoiceId) return;
  const message = `Hi ${s.guest_name}, thank you for staying with <?= e(setting('hotel_name','GMT Hotel and Events Centre')) ?>! Your invoice ${window.__lastInvoiceNumber} (total ${money(s.total)}, balance ${money(s.balance)}) is attached as a PDF.`;
  GMT.sendWhatsApp(s.guest_whatsapp, message, `api/invoice_pdf.php?id=${window.__lastInvoiceId}`);
}

function printInvoice(invoiceNumber) {
  const s = window.__invoiceData;
  if (!s) return;
  const rows = s.line_items.map(li => `<p>${li.label}: ${money(li.amount)}</p>`).join('');
  document.getElementById('invoiceContent').innerHTML = `
    <p><strong>Invoice:</strong> ${invoiceNumber || ''}</p>
    <p><strong>Reservation:</strong> ${s.reservation_code}</p>
    <p><strong>Guest:</strong> ${s.guest_name} &nbsp; <strong>Room:</strong> ${s.room_number}</p>
    <hr style="margin:12px 0;">
    ${rows}
    <p><strong>Subtotal:</strong> ${money(s.subtotal)}</p>
    <p><strong>Tax:</strong> ${money(s.tax)} &nbsp; <strong>Discount:</strong> −${money(s.discount)}</p>
    <p><strong>Total:</strong> ${money(s.total)}</p>
    <p><strong>Amount Paid:</strong> ${money(s.amount_paid)} &nbsp; <strong>Balance:</strong> ${money(s.balance)}</p>
    <p style="margin-top:24px;">Issued: ${new Date().toLocaleString()}</p>
  `;
  setTimeout(() => window.print(), 150);
}

document.getElementById('searchInput').addEventListener('input', GMT.debounce(() => loadActive(), 350));

lucide.createIcons();
loadActive();
</script>
</body>
</html>
