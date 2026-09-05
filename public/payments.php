<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireModuleAccess('payments');
$pageTitle = 'Payments';
$activeNav = 'payments';
$csrfToken = Auth::csrfToken();
$currencySymbol = setting('currency_symbol', '₦');
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

    <main class="p-4 md:p-8 space-y-6">

      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <div class="font-display text-xl">Payments</div>
          <div class="text-sm text-[--brand-muted]">Every payment recorded against a reservation or event</div>
        </div>
        <button onclick="openPaymentModal()" class="btn-brand px-5 py-2.5 text-sm font-semibold flex items-center gap-2">
          <i data-lucide="plus" class="w-4 h-4"></i> Record Payment
        </button>
      </div>

      <div class="card-surface p-4 flex flex-wrap gap-3 items-center">
        <div class="relative flex-1 min-w-[200px]">
          <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[--brand-muted]"></i>
          <input id="searchInput" type="text" placeholder="Search guest, reservation, event, or reference…"
            class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-[--brand-ink]/10 focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
        <select id="methodFilter" class="text-sm rounded-lg border border-[--brand-ink]/10 px-3 py-2">
          <option value="">All methods</option>
          <option value="cash">Cash</option>
          <option value="bank_transfer">Bank Transfer</option>
          <option value="pos">POS</option>
          <option value="card">Card</option>
          <option value="other">Other</option>
        </select>
        <input type="date" id="dateFrom" class="text-sm rounded-lg border border-[--brand-ink]/10 px-3 py-2">
        <input type="date" id="dateTo" class="text-sm rounded-lg border border-[--brand-ink]/10 px-3 py-2">
      </div>

      <div class="card-surface overflow-hidden">
        <div class="table-scroll">
          <table class="w-full text-sm">
            <thead class="bg-[--brand-cream-2] text-left text-xs uppercase tracking-wide text-[--brand-muted]">
              <tr><th class="px-5 py-3">Date</th><th class="px-5 py-3">For</th><th class="px-5 py-3">Guest</th><th class="px-5 py-3">Amount</th><th class="px-5 py-3">Method</th><th class="px-5 py-3">Reference</th><th class="px-5 py-3 text-right">Actions</th></tr>
            </thead>
            <tbody id="paymentTableBody" class="divide-y divide-[--brand-ink]/5"></tbody>
          </table>
        </div>
        <div id="emptyState" class="hidden text-center py-16">
          <i data-lucide="credit-card" class="w-10 h-10 mx-auto text-[--brand-muted] mb-3"></i>
          <div class="font-medium">No payments found</div>
          <div class="text-sm text-[--brand-muted] mt-1">Try adjusting your filters, or record a new payment.</div>
        </div>
      </div>
      <div id="pagination" class="flex items-center justify-center gap-2 text-sm"></div>
    </main>
  </div>
</div>

<!-- Payment modal -->
<div id="paymentModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
  <div class="glass-panel rounded-2xl w-full max-w-lg p-6 md:p-8 max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between mb-6">
      <div class="font-display text-lg">Record Payment</div>
      <button onclick="GMT.closeModal('paymentModal')" class="text-[--brand-muted] hover:text-[--brand-ink]"><i data-lucide="x" class="w-5 h-5"></i></button>
    </div>
    <form id="paymentForm" class="space-y-4">
      <div class="flex rounded-lg border border-[--brand-ink]/10 overflow-hidden w-fit">
        <button type="button" id="typeReservation" onclick="setPaymentType('reservation')" class="px-4 py-2 text-sm font-medium bg-[--brand-coffee] text-[--brand-cream]">Reservation</button>
        <button type="button" id="typeEvent" onclick="setPaymentType('event')" class="px-4 py-2 text-sm font-medium">Event</button>
      </div>

      <div id="reservationPicker" class="relative">
        <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Reservation</label>
        <input id="reservationSearch" autocomplete="off" placeholder="Search reservation code, guest, or room…"
          class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm">
        <div id="reservationResults" class="hidden absolute z-10 w-full bg-white rounded-lg shadow-lg border border-[--brand-ink]/10 mt-1 max-h-40 overflow-y-auto"></div>
        <div id="reservationBalance" class="text-xs text-[--brand-muted] mt-1.5"></div>
      </div>

      <div id="eventPicker" class="relative hidden">
        <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Event</label>
        <input id="eventSearch" autocomplete="off" placeholder="Search event name or client…"
          class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm">
        <div id="eventResults" class="hidden absolute z-10 w-full bg-white rounded-lg shadow-lg border border-[--brand-ink]/10 mt-1 max-h-40 overflow-y-auto"></div>
        <div id="eventBalance" class="text-xs text-[--brand-muted] mt-1.5"></div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Amount (<?= e($currencySymbol) ?>)</label>
          <input type="number" name="amount" id="amount" min="0.01" step="0.01" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Payment Method</label>
          <select name="payment_method" id="payment_method" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm">
            <option value="cash">Cash</option>
            <option value="bank_transfer">Bank Transfer</option>
            <option value="pos">POS</option>
            <option value="card">Card</option>
            <option value="other">Other</option>
          </select>
        </div>
      </div>
      <div>
        <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Reference Number</label>
        <input name="reference_number" id="reference_number" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm" placeholder="Transaction / POS reference">
      </div>
      <div>
        <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Notes</label>
        <textarea name="notes" id="notes" rows="2" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm"></textarea>
      </div>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="GMT.closeModal('paymentModal')" class="px-5 py-2.5 text-sm rounded-lg border border-[--brand-ink]/10">Cancel</button>
        <button type="submit" class="btn-brand px-5 py-2.5 text-sm font-semibold">Save Payment</button>
      </div>
    </form>
  </div>
</div>

<!-- Edit payment modal -->
<div id="editPaymentModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
  <div class="glass-panel rounded-2xl w-full max-w-md p-6 md:p-8">
    <div class="flex items-center justify-between mb-6">
      <div class="font-display text-lg">Edit Payment</div>
      <button onclick="GMT.closeModal('editPaymentModal')" class="text-[--brand-muted] hover:text-[--brand-ink]"><i data-lucide="x" class="w-5 h-5"></i></button>
    </div>
    <div id="editPaymentContext" class="text-sm text-[--brand-muted] mb-4"></div>
    <form id="editPaymentForm" class="space-y-4">
      <input type="hidden" id="editPaymentId">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Amount (<?= e($currencySymbol) ?>)</label>
          <input type="number" id="editAmount" min="0.01" step="0.01" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Payment Method</label>
          <select id="editMethod" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm">
            <option value="cash">Cash</option><option value="bank_transfer">Bank Transfer</option>
            <option value="pos">POS</option><option value="card">Card</option><option value="other">Other</option>
          </select>
        </div>
      </div>
      <div>
        <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Date &amp; Time</label>
        <input type="datetime-local" id="editPaidAt" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Reference Number</label>
        <input id="editReference" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm">
      </div>
      <div>
        <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Notes</label>
        <textarea id="editNotes" rows="2" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm"></textarea>
      </div>
      <p class="text-xs text-[--brand-muted]">To move a payment to a different reservation or event, delete it and record a new one — the linked booking itself isn't editable here.</p>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="GMT.closeModal('editPaymentModal')" class="px-5 py-2.5 text-sm rounded-lg border border-[--brand-ink]/10">Cancel</button>
        <button type="submit" class="btn-brand px-5 py-2.5 text-sm font-semibold">Save Changes</button>
      </div>
    </form>
  </div>
</div>

<div id="confirmModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
  <div class="glass-panel rounded-2xl w-full max-w-sm p-6 text-center">
    <i data-lucide="alert-triangle" class="w-8 h-8 mx-auto text-[--brand-danger] mb-3"></i>
    <p id="confirmModalMessage" class="text-sm mb-6">Are you sure?</p>
    <div class="flex justify-center gap-3">
      <button onclick="GMT.closeModal('confirmModal')" class="px-5 py-2 text-sm rounded-lg border border-[--brand-ink]/10">Cancel</button>
      <button id="confirmModalYes" class="px-5 py-2 text-sm rounded-lg bg-[--brand-danger] text-white">Delete</button>
    </div>
  </div>
</div>

<script src="assets/js/app.js"></script>
<script>
const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
const CURRENCY = <?= json_encode($currencySymbol) ?>;
let currentPage = 1;
let paymentType = 'reservation';
let selectedReservationId = null, selectedEventId = null;

function money(n) { return CURRENCY + Number(n).toLocaleString(undefined,{minimumFractionDigits:2}); }

async function loadPayments(page = 1) {
  currentPage = page;
  const params = new URLSearchParams({
    search: document.getElementById('searchInput').value,
    method: document.getElementById('methodFilter').value,
    date_from: document.getElementById('dateFrom').value,
    date_to: document.getElementById('dateTo').value,
    page,
  });
  const body = document.getElementById('paymentTableBody');
  body.innerHTML = `<tr><td colspan="6" class="px-5 py-6"><div class="skeleton h-6 rounded"></div></td></tr>`;

  const res = await GMT.api(`api/payments.php?${params.toString()}`);
  if (!res.success) { GMT.toast(res.message || 'Unable to load payments.', 'error'); body.innerHTML=''; return; }

  document.getElementById('emptyState').classList.toggle('hidden', res.data.length > 0);
  body.innerHTML = res.data.map(p => `
    <tr class="hover:bg-black/[0.02]">
      <td class="px-5 py-3">${new Date(p.paid_at).toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'})}</td>
      <td class="px-5 py-3">${p.reservation_code ? 'Reservation ' + p.reservation_code : (p.event_name ? 'Event: ' + p.event_name : '—')}</td>
      <td class="px-5 py-3">${p.guest_name || '—'}</td>
      <td class="px-5 py-3 font-medium">${p.amount_display}</td>
      <td class="px-5 py-3">${p.payment_method.replace('_',' ').replace(/\b\w/g, c => c.toUpperCase())}</td>
      <td class="px-5 py-3 text-[--brand-muted]">${p.reference_number || '—'}</td>
      <td class="px-5 py-3 text-right space-x-1 whitespace-nowrap">
        <button onclick='editPayment(${JSON.stringify(p)})' class="text-xs px-2.5 py-1.5 rounded-lg border border-[--brand-ink]/10 hover:bg-black/5">Edit</button>
        <button onclick="deletePayment(${p.id})" class="text-xs px-2.5 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50">Delete</button>
      </td>
    </tr>
  `).join('');
  GMT.entrance('#paymentTableBody tr');
  renderPagination(res.pagination);
}

function editPayment(p) {
  document.getElementById('editPaymentId').value = p.id;
  document.getElementById('editAmount').value = p.amount;
  document.getElementById('editMethod').value = p.payment_method;
  document.getElementById('editReference').value = p.reference_number || '';
  document.getElementById('editNotes').value = p.notes || '';
  // datetime-local needs "YYYY-MM-DDTHH:MM"
  const d = new Date(p.paid_at);
  const pad = n => String(n).padStart(2, '0');
  document.getElementById('editPaidAt').value = `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
  document.getElementById('editPaymentContext').textContent = p.reservation_code ? `Reservation ${p.reservation_code} — ${p.guest_name}` : (p.event_name ? `Event: ${p.event_name}` : 'Unlinked payment');
  GMT.openModal('editPaymentModal');
}

document.getElementById('editPaymentForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const payload = {
    id: document.getElementById('editPaymentId').value,
    amount: document.getElementById('editAmount').value,
    payment_method: document.getElementById('editMethod').value,
    paid_at: document.getElementById('editPaidAt').value.replace('T', ' ') + ':00',
    reference_number: document.getElementById('editReference').value,
    notes: document.getElementById('editNotes').value,
    csrf_token: CSRF_TOKEN,
  };
  const res = await GMT.api('api/payments.php', { method: 'PUT', body: JSON.stringify(payload) });
  GMT.toast(res.message, res.success ? 'success' : 'error');
  if (res.success) { GMT.closeModal('editPaymentModal'); loadPayments(currentPage); }
});

function deletePayment(id) {
  GMT.confirmAction('Delete this payment? The linked reservation or event balance will be recalculated.', async () => {
    const res = await GMT.api(`api/payments.php?id=${id}`, { method: 'DELETE', body: JSON.stringify({ id, csrf_token: CSRF_TOKEN }) });
    GMT.toast(res.message, res.success ? 'success' : 'error');
    if (res.success) loadPayments(currentPage);
  });
}

function renderPagination(p) {
  const el = document.getElementById('pagination');
  if (p.totalPages <= 1) { el.innerHTML = ''; return; }
  let html = '';
  for (let i = 1; i <= p.totalPages; i++) {
    html += `<button onclick="loadPayments(${i})" class="w-8 h-8 rounded-lg ${i===p.page ? 'btn-brand' : 'border border-[--brand-ink]/10'}">${i}</button>`;
  }
  el.innerHTML = html;
}

function setPaymentType(type) {
  paymentType = type;
  document.getElementById('typeReservation').className = `px-4 py-2 text-sm font-medium ${type==='reservation' ? 'bg-[--brand-coffee] text-[--brand-cream]' : ''}`;
  document.getElementById('typeEvent').className = `px-4 py-2 text-sm font-medium ${type==='event' ? 'bg-[--brand-coffee] text-[--brand-cream]' : ''}`;
  document.getElementById('reservationPicker').classList.toggle('hidden', type !== 'reservation');
  document.getElementById('eventPicker').classList.toggle('hidden', type !== 'event');
}

function openPaymentModal() {
  document.getElementById('paymentForm').reset();
  selectedReservationId = null; selectedEventId = null;
  document.getElementById('reservationSearch').value = '';
  document.getElementById('eventSearch').value = '';
  document.getElementById('reservationBalance').textContent = '';
  document.getElementById('eventBalance').textContent = '';
  setPaymentType('reservation');
  GMT.openModal('paymentModal');
}

document.getElementById('reservationSearch').addEventListener('input', GMT.debounce(async (e) => {
  const q = e.target.value.trim();
  const box = document.getElementById('reservationResults');
  if (q.length < 2) { box.classList.add('hidden'); return; }
  const res = await GMT.api(`api/reservations.php?search=${encodeURIComponent(q)}`);
  if (!res.success || !res.data.length) { box.innerHTML = `<div class="px-3 py-2 text-sm text-[--brand-muted]">No reservations found</div>`; box.classList.remove('hidden'); return; }
  box.innerHTML = res.data.map(r => `<div class="px-3 py-2 text-sm hover:bg-black/5 cursor-pointer" onclick='selectReservation(${r.id}, "${r.reservation_code}", "${r.guest_name.replace(/"/g,'&quot;')}", ${r.total_amount})'>${r.reservation_code} — ${r.guest_name} (Room ${r.room_number})</div>`).join('');
  box.classList.remove('hidden');
}, 300));

async function selectReservation(id, code, guestName, total) {
  selectedReservationId = id;
  document.getElementById('reservationSearch').value = `${code} — ${guestName}`;
  document.getElementById('reservationResults').classList.add('hidden');
  const detail = await GMT.api(`api/reservations.php?id=${id}`);
  if (detail.success) {
    const paidRes = await GMT.api(`api/payments.php?search=${encodeURIComponent(code)}`);
    const paid = paidRes.success ? paidRes.data.filter(p => p.reservation_code === code).reduce((s,p) => s + Number(p.amount), 0) : 0;
    const balance = Math.max(0, total - paid);
    document.getElementById('reservationBalance').textContent = `Total ${money(total)} · Balance due ${money(balance)}`;
    document.getElementById('amount').value = balance > 0 ? balance.toFixed(2) : '';
  }
}

document.getElementById('eventSearch').addEventListener('input', GMT.debounce(async (e) => {
  const q = e.target.value.trim();
  const box = document.getElementById('eventResults');
  if (q.length < 2) { box.classList.add('hidden'); return; }
  const res = await GMT.api(`api/events.php?search=${encodeURIComponent(q)}`);
  if (!res.success || !res.data.length) { box.innerHTML = `<div class="px-3 py-2 text-sm text-[--brand-muted]">No events found</div>`; box.classList.remove('hidden'); return; }
  box.innerHTML = res.data.map(ev => `<div class="px-3 py-2 text-sm hover:bg-black/5 cursor-pointer" onclick='selectEvent(${ev.id}, "${ev.event_name.replace(/"/g,'&quot;')}", ${ev.balance})'>${ev.event_name} — ${ev.client_name}</div>`).join('');
  box.classList.remove('hidden');
}, 300));

function selectEvent(id, name, balance) {
  selectedEventId = id;
  document.getElementById('eventSearch').value = name;
  document.getElementById('eventResults').classList.add('hidden');
  document.getElementById('eventBalance').textContent = `Balance due ${money(balance)}`;
  document.getElementById('amount').value = balance > 0 ? Number(balance).toFixed(2) : '';
}

document.addEventListener('click', (e) => {
  if (!e.target.closest('#reservationSearch') && !e.target.closest('#reservationResults')) document.getElementById('reservationResults').classList.add('hidden');
  if (!e.target.closest('#eventSearch') && !e.target.closest('#eventResults')) document.getElementById('eventResults').classList.add('hidden');
});

document.getElementById('paymentForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  if (paymentType === 'reservation' && !selectedReservationId) { GMT.toast('Please select a reservation.', 'error'); return; }
  if (paymentType === 'event' && !selectedEventId) { GMT.toast('Please select an event.', 'error'); return; }

  const payload = {
    reservation_id: paymentType === 'reservation' ? selectedReservationId : null,
    event_id: paymentType === 'event' ? selectedEventId : null,
    amount: document.getElementById('amount').value,
    payment_method: document.getElementById('payment_method').value,
    reference_number: document.getElementById('reference_number').value,
    notes: document.getElementById('notes').value,
    csrf_token: CSRF_TOKEN,
  };
  const res = await GMT.api('api/payments.php', { method: 'POST', body: JSON.stringify(payload) });
  GMT.toast(res.message, res.success ? 'success' : 'error');
  if (res.success) { GMT.closeModal('paymentModal'); loadPayments(currentPage); }
});

document.getElementById('searchInput').addEventListener('input', GMT.debounce(() => loadPayments(1), 350));
['methodFilter','dateFrom','dateTo'].forEach(id => document.getElementById(id).addEventListener('change', () => loadPayments(1)));

lucide.createIcons();
loadPayments();
</script>
</body>
</html>
