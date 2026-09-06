<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireModuleAccess('reservations');
$pageTitle = 'Reservations';
$activeNav = 'reservations';
$csrfToken = Auth::csrfToken();
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
    #sidebar, header, #toolbar, #pagination, .no-print { display: none !important; }
    main { padding: 0 !important; }
    body { background: white !important; }
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

      <div class="flex flex-wrap items-center justify-between gap-3 no-print">
        <div>
          <div class="font-display text-xl">Reservations</div>
          <div class="text-sm text-[--brand-muted]">Bookings across all rooms, with automatic totals and overlap protection</div>
        </div>
        <div class="flex items-center gap-2">
          <div class="neu-toggle-group">
            <button id="tabTable" onclick="switchView('table')" class="neu-toggle-btn is-active">Table</button>
            <button id="tabCalendar" onclick="switchView('calendar')" class="neu-toggle-btn">Calendar</button>
          </div>
          <button onclick="openReservationModal()" class="btn-brand px-5 py-2.5 text-sm font-semibold flex items-center gap-2">
            <i data-lucide="plus" class="w-4 h-4"></i> New Reservation
          </button>
        </div>
      </div>

      <!-- ===== TABLE VIEW ===== -->
      <div id="viewTable">
        <div id="toolbar" class="card-surface p-4 flex flex-wrap gap-3 items-center no-print">
          <div class="relative flex-1 min-w-[200px]">
            <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[--brand-muted]"></i>
            <input id="searchInput" type="text" placeholder="Search code, guest, or room…"
              class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-[--brand-ink]/10 focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
          </div>
          <select id="statusFilter" class="text-sm rounded-lg border border-[--brand-ink]/10 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
            <option value="">All statuses</option>
            <?php foreach (['pending'=>'Pending','confirmed'=>'Confirmed','checked_in'=>'Checked In','checked_out'=>'Checked Out','cancelled'=>'Cancelled'] as $k=>$v): ?>
              <option value="<?= $k ?>"><?= $v ?></option>
            <?php endforeach; ?>
          </select>
          <select id="paymentFilter" class="text-sm rounded-lg border border-[--brand-ink]/10 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
            <option value="">All payment statuses</option>
            <option value="unpaid">Unpaid</option>
            <option value="partial">Partial</option>
            <option value="paid">Paid</option>
          </select>
          <button onclick="exportCsv()" class="px-4 py-2 text-sm rounded-lg border border-[--brand-ink]/10 hover:bg-black/5 flex items-center gap-2">
            <i data-lucide="download" class="w-4 h-4"></i> Export CSV
          </button>
          <button onclick="window.print()" class="px-4 py-2 text-sm rounded-lg border border-[--brand-ink]/10 hover:bg-black/5 flex items-center gap-2">
            <i data-lucide="printer" class="w-4 h-4"></i> Print
          </button>
        </div>

        <div class="card-surface overflow-hidden mt-4">
          <div class="table-scroll">
            <table class="w-full text-sm">
              <thead class="bg-[--brand-cream-2] text-left text-xs uppercase tracking-wide text-[--brand-muted]">
                <tr>
                  <th class="px-5 py-3">Code</th><th class="px-5 py-3">Guest</th><th class="px-5 py-3">Room</th>
                  <th class="px-5 py-3">Check-in</th><th class="px-5 py-3">Check-out</th><th class="px-5 py-3">Total</th>
                  <th class="px-5 py-3">Payment</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right no-print">Actions</th>
                </tr>
              </thead>
              <tbody id="reservationTableBody" class="divide-y divide-[--brand-ink]/5"></tbody>
            </table>
          </div>
          <div id="emptyState" class="hidden text-center py-16">
            <i data-lucide="calendar-x" class="w-10 h-10 mx-auto text-[--brand-muted] mb-3"></i>
            <div class="font-medium">No reservations found</div>
            <div class="text-sm text-[--brand-muted] mt-1">Try adjusting your filters, or create a new reservation.</div>
          </div>
        </div>
        <div id="pagination" class="flex items-center justify-center gap-2 text-sm mt-4"></div>
      </div>

      <!-- ===== CALENDAR VIEW ===== -->
      <div id="viewCalendar" class="hidden">
        <div class="card-surface p-4 flex items-center justify-between mb-4 no-print">
          <button onclick="shiftMonth(-1)" class="p-2 rounded-lg border border-[--brand-ink]/10 hover:bg-black/5"><i data-lucide="chevron-left" class="w-4 h-4"></i></button>
          <div id="calendarMonthLabel" class="font-display text-lg"></div>
          <button onclick="shiftMonth(1)" class="p-2 rounded-lg border border-[--brand-ink]/10 hover:bg-black/5"><i data-lucide="chevron-right" class="w-4 h-4"></i></button>
        </div>
        <div class="card-surface p-4">
          <div class="grid grid-cols-7 gap-1 text-xs font-medium text-[--brand-muted] mb-2">
            <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?><div class="text-center"><?= $d ?></div><?php endforeach; ?>
          </div>
          <div id="calendarGrid" class="grid grid-cols-7 gap-1"></div>
        </div>
      </div>

    </main>
  </div>
</div>

<!-- Reservation modal -->
<div id="reservationModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
  <div class="glass-panel rounded-2xl w-full max-w-2xl p-6 md:p-8 max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between mb-6">
      <div class="font-display text-lg" id="reservationModalTitle">New Reservation</div>
      <button onclick="GMT.closeModal('reservationModal')" class="text-[--brand-muted] hover:text-[--brand-ink]"><i data-lucide="x" class="w-5 h-5"></i></button>
    </div>
    <form id="reservationForm" class="space-y-4">
      <input type="hidden" id="reservationId" name="id">

      <div class="grid grid-cols-2 gap-4">
        <div class="relative">
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Guest</label>
          <input id="guestSearch" autocomplete="off" required placeholder="Search guest by name or phone…"
            class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
          <input type="hidden" id="guest_id" name="guest_id">
          <div id="guestResults" class="hidden absolute z-10 w-full bg-white rounded-lg shadow-lg border border-[--brand-ink]/10 mt-1 max-h-40 overflow-y-auto"></div>
        </div>
        <div class="relative">
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Room</label>
          <input id="roomSearch" autocomplete="off" required placeholder="Search room number…"
            class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
          <input type="hidden" id="room_id" name="room_id">
          <div id="roomResults" class="hidden absolute z-10 w-full bg-white rounded-lg shadow-lg border border-[--brand-ink]/10 mt-1 max-h-40 overflow-y-auto"></div>
        </div>
      </div>

      <div class="grid grid-cols-3 gap-4">
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Check-in</label>
          <input type="date" name="check_in_date" id="check_in_date" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Check-out</label>
          <input type="date" name="check_out_date" id="check_out_date" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5"># Guests</label>
          <input type="number" name="number_of_guests" id="number_of_guests" min="1" value="1" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
      </div>

      <div class="grid grid-cols-3 gap-4">
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Discount (<?= e($currencySymbol) ?>)</label>
          <input type="number" name="discount" id="discount" min="0" step="0.01" value="0" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Tax (<?= e($currencySymbol) ?>)</label>
          <input type="number" name="tax" id="tax" min="0" step="0.01" value="0" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Payment Status</label>
          <select name="payment_status" id="payment_status" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
            <option value="unpaid">Unpaid</option>
            <option value="partial">Partial</option>
            <option value="paid">Paid</option>
          </select>
        </div>
      </div>

      <div>
        <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Special Requests</label>
        <textarea name="special_requests" id="special_requests" rows="2" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]"></textarea>
      </div>

      <div class="card-surface p-4 flex items-center justify-between bg-[--brand-cream-2]">
        <div class="text-sm text-[--brand-muted]">
          <span id="calcRate"><?= e($currencySymbol) ?>0</span>/night ×
          <span id="calcNights">0</span> night(s) + tax − discount
        </div>
        <div class="text-lg font-semibold">= <span id="calcTotal"><?= e($currencySymbol) ?>0.00</span></div>
      </div>

      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="GMT.closeModal('reservationModal')" class="px-5 py-2.5 text-sm rounded-lg border border-[--brand-ink]/10">Cancel</button>
        <button type="submit" class="btn-brand px-5 py-2.5 text-sm font-semibold">Save Reservation</button>
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
      <button id="confirmModalYes" class="px-5 py-2 text-sm rounded-lg bg-[--brand-danger] text-white">Confirm</button>
    </div>
  </div>
</div>

<script src="assets/js/app.js"></script>
<script>
const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
const CURRENCY = <?= json_encode($currencySymbol) ?>;
let currentPage = 1;
let selectedRoomRate = 0;
let calendarMonth = new Date();

// View switching
function switchView(view) {
  document.getElementById('viewTable').classList.toggle('hidden', view !== 'table');
  document.getElementById('viewCalendar').classList.toggle('hidden', view !== 'calendar');
  document.getElementById('tabTable').classList.toggle('is-active', view === 'table');
  document.getElementById('tabCalendar').classList.toggle('is-active', view === 'calendar');
  if (view === 'calendar') loadCalendar();
}

// Table view
function statusBadge(status) {
  const map = { pending:'reserved', confirmed:'available', checked_in:'occupied', checked_out:'cleaning', cancelled:'out_of_service' };
  const labels = { pending:'Pending', confirmed:'Confirmed', checked_in:'Checked In', checked_out:'Checked Out', cancelled:'Cancelled' };
  return `<span class="badge badge-${map[status]||'maintenance'}">${labels[status]||status}</span>`;
}
function paymentBadge(status) {
  const map = { unpaid:'out_of_service', partial:'reserved', paid:'available' };
  return `<span class="badge badge-${map[status]||'maintenance'}">${status.charAt(0).toUpperCase()+status.slice(1)}</span>`;
}

async function loadReservations(page = 1) {
  currentPage = page;
  const params = new URLSearchParams({
    search: document.getElementById('searchInput').value,
    status: document.getElementById('statusFilter').value,
    payment_status: document.getElementById('paymentFilter').value,
    page,
  });
  const body = document.getElementById('reservationTableBody');
  body.innerHTML = `<tr><td colspan="9" class="px-5 py-6"><div class="skeleton h-6 rounded"></div></td></tr>`;

  const res = await GMT.api(`api/reservations.php?${params.toString()}`);
  if (!res.success) { GMT.toast(res.message || 'Unable to load reservations.', 'error'); body.innerHTML=''; return; }

  document.getElementById('emptyState').classList.toggle('hidden', res.data.length > 0);
  body.innerHTML = res.data.map(r => `
    <tr class="hover:bg-black/[0.02]">
      <td class="px-5 py-3 font-medium">${r.reservation_code}</td>
      <td class="px-5 py-3">${r.guest_name}</td>
      <td class="px-5 py-3">${r.room_number}</td>
      <td class="px-5 py-3">${new Date(r.check_in_date).toLocaleDateString('en-GB',{day:'numeric',month:'short'})}</td>
      <td class="px-5 py-3">${new Date(r.check_out_date).toLocaleDateString('en-GB',{day:'numeric',month:'short'})}</td>
      <td class="px-5 py-3">${r.total_display}</td>
      <td class="px-5 py-3">${paymentBadge(r.payment_status)}</td>
      <td class="px-5 py-3">${statusBadge(r.status)}</td>
      <td class="px-5 py-3 text-right no-print space-x-1 whitespace-nowrap">
        ${r.status === 'pending' ? `<button onclick="quickAction(${r.id},'confirm','${r.reservation_code}')" class="text-xs px-2.5 py-1.5 rounded-lg border border-green-200 text-green-700 hover:bg-green-50">Confirm</button>` : ''}
        ${['pending','confirmed'].includes(r.status) ? `<button onclick="quickAction(${r.id},'cancel','${r.reservation_code}')" class="text-xs px-2.5 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50">Cancel</button>` : ''}
        <button onclick="editReservation(${r.id})" class="text-xs px-2.5 py-1.5 rounded-lg border border-[--brand-ink]/10 hover:bg-black/5">Edit</button>
        <button onclick="deleteReservation(${r.id}, '${r.reservation_code}')" class="text-xs px-2.5 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50">Delete</button>
      </td>
    </tr>
  `).join('');
  GMT.entrance('#reservationTableBody tr');
  renderPagination(res.pagination);
  lucide.createIcons();
}

function renderPagination(p) {
  const el = document.getElementById('pagination');
  if (p.totalPages <= 1) { el.innerHTML = ''; return; }
  let html = '';
  for (let i = 1; i <= p.totalPages; i++) {
    html += `<button onclick="loadReservations(${i})" class="w-8 h-8 rounded-lg ${i===p.page ? 'btn-brand' : 'border border-[--brand-ink]/10'}">${i}</button>`;
  }
  el.innerHTML = html;
}

async function quickAction(id, action, code) {
  const verb = action === 'confirm' ? 'confirm' : 'cancel';
  GMT.confirmAction(`${verb.charAt(0).toUpperCase()+verb.slice(1)} reservation ${code}?`, async () => {
    const res = await GMT.api('api/reservations.php', { method: 'PUT', body: JSON.stringify({ id, action, csrf_token: CSRF_TOKEN }) });
    GMT.toast(res.message, res.success ? 'success' : 'error');
    if (res.success) loadReservations(currentPage);
  });
}

function deleteReservation(id, code) {
  GMT.confirmAction(`Delete reservation ${code}? This cannot be undone.`, async () => {
    const res = await GMT.api(`api/reservations.php?id=${id}`, { method: 'DELETE', body: JSON.stringify({ id, csrf_token: CSRF_TOKEN }) });
    GMT.toast(res.message, res.success ? 'success' : 'error');
    if (res.success) loadReservations(currentPage);
  });
}

function exportCsv() {
  const params = new URLSearchParams({
    search: document.getElementById('searchInput').value,
    status: document.getElementById('statusFilter').value,
    payment_status: document.getElementById('paymentFilter').value,
  });
  window.location.href = `api/reservations_export.php?${params.toString()}`;
}

// Guest / Room typeahead
document.getElementById('guestSearch').addEventListener('input', GMT.debounce(async (e) => {
  const q = e.target.value.trim();
  const box = document.getElementById('guestResults');
  if (q.length < 2) { box.classList.add('hidden'); return; }
  const res = await GMT.api(`api/guests.php?search=${encodeURIComponent(q)}`);
  if (!res.success || !res.data.length) { box.innerHTML = `<div class="px-3 py-2 text-sm text-[--brand-muted]">No guests found</div>`; box.classList.remove('hidden'); return; }
  box.innerHTML = res.data.map(g => `<div class="px-3 py-2 text-sm hover:bg-black/5 cursor-pointer" onclick="selectGuest(${g.id}, '${g.full_name.replace(/'/g,"\\'")}')">${g.full_name} ${g.phone ? '· '+g.phone : ''}</div>`).join('');
  box.classList.remove('hidden');
}, 300));

function selectGuest(id, name) {
  document.getElementById('guest_id').value = id;
  document.getElementById('guestSearch').value = name;
  document.getElementById('guestResults').classList.add('hidden');
}

document.getElementById('roomSearch').addEventListener('input', GMT.debounce(async (e) => {
  const q = e.target.value.trim();
  const box = document.getElementById('roomResults');
  const res = await GMT.api(`api/rooms.php?search=${encodeURIComponent(q)}`);
  if (!res.success || !res.data.length) { box.innerHTML = `<div class="px-3 py-2 text-sm text-[--brand-muted]">No rooms found</div>`; box.classList.remove('hidden'); return; }
  box.innerHTML = res.data.map(r => `<div class="px-3 py-2 text-sm hover:bg-black/5 cursor-pointer" onclick='selectRoom(${r.id}, "${r.room_number}", ${r.price_per_night})'>Room ${r.room_number}: ${r.price_display}/night</div>`).join('');
  box.classList.remove('hidden');
}, 300));

function selectRoom(id, number, rate) {
  document.getElementById('room_id').value = id;
  document.getElementById('roomSearch').value = `Room ${number}`;
  document.getElementById('roomResults').classList.add('hidden');
  selectedRoomRate = rate;
  recalcTotal();
}

document.addEventListener('click', (e) => {
  if (!e.target.closest('#guestSearch') && !e.target.closest('#guestResults')) document.getElementById('guestResults').classList.add('hidden');
  if (!e.target.closest('#roomSearch') && !e.target.closest('#roomResults')) document.getElementById('roomResults').classList.add('hidden');
});

// Live total calculation
function recalcTotal() {
  const checkIn = document.getElementById('check_in_date').value;
  const checkOut = document.getElementById('check_out_date').value;
  const discount = parseFloat(document.getElementById('discount').value) || 0;
  const tax = parseFloat(document.getElementById('tax').value) || 0;
  let nights = 0;
  if (checkIn && checkOut) {
    nights = Math.max(1, Math.round((new Date(checkOut) - new Date(checkIn)) / 86400000));
  }
  const total = Math.max(0, (selectedRoomRate * nights) + tax - discount);
  document.getElementById('calcRate').textContent = `${CURRENCY}${selectedRoomRate.toLocaleString()}`;
  document.getElementById('calcNights').textContent = nights;
  document.getElementById('calcTotal').textContent = `${CURRENCY}${total.toLocaleString(undefined,{minimumFractionDigits:2})}`;
}
['check_in_date','check_out_date','discount','tax'].forEach(id => document.getElementById(id).addEventListener('input', recalcTotal));

// Modal open/edit
function openReservationModal() {
  document.getElementById('reservationForm').reset();
  document.getElementById('reservationId').value = '';
  document.getElementById('guest_id').value = '';
  document.getElementById('room_id').value = '';
  document.getElementById('reservationModalTitle').textContent = 'New Reservation';
  selectedRoomRate = 0;
  recalcTotal();
  GMT.openModal('reservationModal');
}

async function editReservation(id) {
  const res = await GMT.api(`api/reservations.php?id=${id}`);
  if (!res.success) { GMT.toast(res.message, 'error'); return; }
  const r = res.data;
  document.getElementById('reservationModalTitle').textContent = `Edit ${r.reservation_code}`;
  document.getElementById('reservationId').value = r.id;
  document.getElementById('guest_id').value = r.guest_id;
  document.getElementById('guestSearch').value = r.guest_name;
  document.getElementById('room_id').value = r.room_id;
  document.getElementById('roomSearch').value = `Room ${r.room_number}`;
  document.getElementById('check_in_date').value = r.check_in_date;
  document.getElementById('check_out_date').value = r.check_out_date;
  document.getElementById('number_of_guests').value = r.number_of_guests;
  document.getElementById('discount').value = r.discount;
  document.getElementById('tax').value = r.tax;
  document.getElementById('payment_status').value = r.payment_status;
  document.getElementById('special_requests').value = r.special_requests || '';
  selectedRoomRate = parseFloat(r.room_rate);
  recalcTotal();
  GMT.openModal('reservationModal');
}

document.getElementById('reservationForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const id = document.getElementById('reservationId').value;
  const payload = {
    id: id || undefined,
    guest_id: document.getElementById('guest_id').value,
    room_id: document.getElementById('room_id').value,
    check_in_date: document.getElementById('check_in_date').value,
    check_out_date: document.getElementById('check_out_date').value,
    number_of_guests: document.getElementById('number_of_guests').value,
    discount: document.getElementById('discount').value,
    tax: document.getElementById('tax').value,
    payment_status: document.getElementById('payment_status').value,
    special_requests: document.getElementById('special_requests').value,
    csrf_token: CSRF_TOKEN,
  };
  if (!payload.guest_id || !payload.room_id) { GMT.toast('Please select a guest and a room.', 'error'); return; }
  const isEdit = !!id;
  const res = await GMT.api('api/reservations.php', { method: isEdit ? 'PUT' : 'POST', body: JSON.stringify(payload) });
  GMT.toast(res.message, res.success ? 'success' : 'error');
  if (res.success) { GMT.closeModal('reservationModal'); loadReservations(currentPage); }
});

// Calendar view
function shiftMonth(delta) {
  calendarMonth.setMonth(calendarMonth.getMonth() + delta);
  loadCalendar();
}

async function loadCalendar() {
  const monthStr = `${calendarMonth.getFullYear()}-${String(calendarMonth.getMonth()+1).padStart(2,'0')}`;
  document.getElementById('calendarMonthLabel').textContent = calendarMonth.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

  const res = await GMT.api(`api/reservations.php?view=calendar&month=${monthStr}`);
  const grid = document.getElementById('calendarGrid');
  if (!res.success) { grid.innerHTML = ''; return; }

  const firstDay = new Date(calendarMonth.getFullYear(), calendarMonth.getMonth(), 1);
  const daysInMonth = new Date(calendarMonth.getFullYear(), calendarMonth.getMonth()+1, 0).getDate();
  const startOffset = firstDay.getDay();

  let cells = '';
  for (let i = 0; i < startOffset; i++) cells += `<div></div>`;

  for (let d = 1; d <= daysInMonth; d++) {
    const dateStr = `${calendarMonth.getFullYear()}-${String(calendarMonth.getMonth()+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    const dayReservations = res.data.filter(r => dateStr >= r.check_in_date && dateStr < r.check_out_date);
    const pills = dayReservations.slice(0,3).map(r => {
      const color = { pending:'bg-amber-100 text-amber-800', confirmed:'bg-green-100 text-green-800', checked_in:'bg-orange-100 text-orange-800' }[r.status] || 'bg-gray-100';
      return `<div class="${color} rounded px-1.5 py-0.5 text-[10px] truncate" title="${r.guest_name}: Room ${r.room_number}">${r.room_number}: ${r.guest_name}</div>`;
    }).join('');
    const more = dayReservations.length > 3 ? `<div class="text-[10px] text-[--brand-muted]">+${dayReservations.length - 3} more</div>` : '';
    cells += `
      <div class="border border-[--brand-ink]/5 rounded-lg p-1.5 min-h-[86px] bg-white">
        <div class="text-xs text-[--brand-muted] mb-1">${d}</div>
        <div class="space-y-0.5">${pills}${more}</div>
      </div>`;
  }
  grid.innerHTML = cells;
  GMT.motionReady.then((m) => { if (m) m.animate(grid, { opacity: [0, 1] }, { duration: 0.3 }); });
}

// Filters
document.getElementById('searchInput').addEventListener('input', GMT.debounce(() => loadReservations(1), 350));
['statusFilter','paymentFilter'].forEach(id => document.getElementById(id).addEventListener('change', () => loadReservations(1)));

lucide.createIcons();
loadReservations();
</script>
</body>
</html>
