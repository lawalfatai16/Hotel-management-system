<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireModuleAccess('events');
$db = Database::connect();
$pageTitle = 'Events';
$activeNav = 'events';
$csrfToken = Auth::csrfToken();
$currencySymbol = setting('currency_symbol', '₦');
$packages = $db->query("SELECT id, name, price FROM event_packages ORDER BY name")->fetchAll();
$eventTypes = ['wedding'=>'Wedding','conference'=>'Conference','birthday'=>'Birthday','meeting'=>'Meeting','seminar'=>'Seminar','party'=>'Party','corporate'=>'Corporate','other'=>'Other'];
$eventStatuses = ['inquiry'=>'Inquiry','confirmed'=>'Confirmed','completed'=>'Completed','cancelled'=>'Cancelled'];
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

      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <div class="font-display text-xl">Events</div>
          <div class="text-sm text-[--brand-muted]">Weddings, conferences, and every booking on the Events Centre calendar</div>
        </div>
        <div class="flex items-center gap-2">
          <div class="neu-toggle-group">
            <button id="tabTable" onclick="switchView('table')" class="neu-toggle-btn is-active">Table</button>
            <button id="tabCalendar" onclick="switchView('calendar')" class="neu-toggle-btn">Calendar</button>
          </div>
          <button onclick="openEventModal()" class="btn-brand px-5 py-2.5 text-sm font-semibold flex items-center gap-2">
            <i data-lucide="plus" class="w-4 h-4"></i> New Event
          </button>
        </div>
      </div>

      <!-- TABLE VIEW -->
      <div id="viewTable">
        <div class="card-surface p-4 flex flex-wrap gap-3 items-center">
          <div class="relative flex-1 min-w-[200px]">
            <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[--brand-muted]"></i>
            <input id="searchInput" type="text" placeholder="Search event, client, or code…"
              class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-[--brand-ink]/10 focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
          </div>
          <select id="typeFilter" class="text-sm rounded-lg border border-[--brand-ink]/10 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
            <option value="">All types</option>
            <?php foreach ($eventTypes as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
          </select>
          <select id="statusFilter" class="text-sm rounded-lg border border-[--brand-ink]/10 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
            <option value="">All statuses</option>
            <?php foreach ($eventStatuses as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
          </select>
        </div>

        <div class="card-surface overflow-hidden mt-4">
          <div class="table-scroll">
            <table class="w-full text-sm">
              <thead class="bg-[--brand-cream-2] text-left text-xs uppercase tracking-wide text-[--brand-muted]">
                <tr>
                  <th class="px-5 py-3">Code</th><th class="px-5 py-3">Event</th><th class="px-5 py-3">Client</th>
                  <th class="px-5 py-3">Date</th><th class="px-5 py-3">Venue</th><th class="px-5 py-3">Price</th>
                  <th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody id="eventTableBody" class="divide-y divide-[--brand-ink]/5"></tbody>
            </table>
          </div>
          <div id="emptyState" class="hidden text-center py-16">
            <i data-lucide="party-popper" class="w-10 h-10 mx-auto text-[--brand-muted] mb-3"></i>
            <div class="font-medium">No events found</div>
            <div class="text-sm text-[--brand-muted] mt-1">Try adjusting your filters, or create a new event.</div>
          </div>
        </div>
        <div id="pagination" class="flex items-center justify-center gap-2 text-sm mt-4"></div>
      </div>

      <!-- CALENDAR VIEW -->
      <div id="viewCalendar" class="hidden">
        <div class="card-surface p-4 flex items-center justify-between mb-4">
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

<!-- Event modal -->
<div id="eventModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
  <div class="glass-panel rounded-2xl w-full max-w-2xl p-6 md:p-8 max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between mb-6">
      <div class="font-display text-lg" id="eventModalTitle">New Event</div>
      <button onclick="GMT.closeModal('eventModal')" class="text-[--brand-muted] hover:text-[--brand-ink]"><i data-lucide="x" class="w-5 h-5"></i></button>
    </div>
    <form id="eventForm" class="space-y-4">
      <input type="hidden" id="eventId" name="id">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Client Name</label>
          <input name="client_name" id="client_name" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Event Name</label>
          <input name="event_name" id="event_name" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Client Phone</label>
          <input name="client_phone" id="client_phone" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Client Email</label>
          <input name="client_email" id="client_email" type="email" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Event Type</label>
          <select name="event_type" id="event_type" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
            <?php foreach ($eventTypes as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Package</label>
          <select id="package_id" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
            <option value="">Custom (no package)</option>
            <?php foreach ($packages as $p): ?><option value="<?= (int)$p['id'] ?>" data-price="<?= (float)$p['price'] ?>"><?= e($p['name']) ?>: <?= formatCurrency((float)$p['price']) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="grid grid-cols-3 gap-4">
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Event Date</label>
          <input type="date" name="event_date" id="event_date" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Start Time</label>
          <input type="time" name="start_time" id="start_time" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">End Time</label>
          <input type="time" name="end_time" id="end_time" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Venue</label>
          <input name="venue" id="venue" required placeholder="e.g. Main Events Hall" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5"># of Guests</label>
          <input type="number" name="number_of_guests" id="number_of_guests" min="1" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
      </div>
      <div class="grid grid-cols-3 gap-4">
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Price (<?= e($currencySymbol) ?>)</label>
          <input type="number" name="price" id="price" min="0" step="0.01" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Deposit (<?= e($currencySymbol) ?>)</label>
          <input type="number" name="deposit" id="deposit" min="0" step="0.01" value="0" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Status</label>
          <select name="status" id="status" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
            <?php foreach ($eventStatuses as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
      <div>
        <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Special Requirements</label>
        <textarea name="special_requirements" id="special_requirements" rows="2" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]"></textarea>
      </div>
      <div class="card-surface p-4 flex items-center justify-between bg-[--brand-cream-2]">
        <div class="text-sm text-[--brand-muted]">Price − Deposit</div>
        <div class="text-lg font-semibold">Balance = <span id="calcBalance"><?= e($currencySymbol) ?>0.00</span></div>
      </div>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="GMT.closeModal('eventModal')" class="px-5 py-2.5 text-sm rounded-lg border border-[--brand-ink]/10">Cancel</button>
        <button type="submit" class="btn-brand px-5 py-2.5 text-sm font-semibold">Save Event</button>
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
let calendarMonth = new Date();

function switchView(view) {
  document.getElementById('viewTable').classList.toggle('hidden', view !== 'table');
  document.getElementById('viewCalendar').classList.toggle('hidden', view !== 'calendar');
  document.getElementById('tabTable').classList.toggle('is-active', view === 'table');
  document.getElementById('tabCalendar').classList.toggle('is-active', view === 'calendar');
  if (view === 'calendar') loadCalendar();
}

function statusBadge(status) {
  const map = { inquiry:'reserved', confirmed:'available', completed:'cleaning', cancelled:'out_of_service' };
  return `<span class="badge badge-${map[status]||'maintenance'}">${status.charAt(0).toUpperCase()+status.slice(1)}</span>`;
}

async function loadEvents(page = 1) {
  currentPage = page;
  const params = new URLSearchParams({
    search: document.getElementById('searchInput').value,
    type: document.getElementById('typeFilter').value,
    status: document.getElementById('statusFilter').value,
    page,
  });
  const body = document.getElementById('eventTableBody');
  body.innerHTML = `<tr><td colspan="8" class="px-5 py-6"><div class="skeleton h-6 rounded"></div></td></tr>`;

  const res = await GMT.api(`api/events.php?${params.toString()}`);
  if (!res.success) { GMT.toast(res.message || 'Unable to load events.', 'error'); body.innerHTML=''; return; }

  document.getElementById('emptyState').classList.toggle('hidden', res.data.length > 0);
  body.innerHTML = res.data.map(ev => `
    <tr class="hover:bg-black/[0.02]">
      <td class="px-5 py-3 font-medium">${ev.event_code}</td>
      <td class="px-5 py-3">${ev.event_name}</td>
      <td class="px-5 py-3">${ev.client_name}</td>
      <td class="px-5 py-3">${new Date(ev.event_date).toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'})}</td>
      <td class="px-5 py-3">${ev.venue}</td>
      <td class="px-5 py-3">${ev.price_display}</td>
      <td class="px-5 py-3">${statusBadge(ev.status)}</td>
      <td class="px-5 py-3 text-right space-x-1 whitespace-nowrap">
        ${ev.status === 'inquiry' ? `<button onclick="quickAction(${ev.id},'confirm','${ev.event_code}')" class="text-xs px-2.5 py-1.5 rounded-lg border border-green-200 text-green-700 hover:bg-green-50">Confirm</button>` : ''}
        ${ev.status === 'confirmed' ? `<button onclick="quickAction(${ev.id},'complete','${ev.event_code}')" class="text-xs px-2.5 py-1.5 rounded-lg border border-[--brand-ink]/10 hover:bg-black/5">Complete</button>` : ''}
        ${['inquiry','confirmed'].includes(ev.status) ? `<button onclick="quickAction(${ev.id},'cancel','${ev.event_code}')" class="text-xs px-2.5 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50">Cancel</button>` : ''}
        <button onclick="editEvent(${ev.id})" class="text-xs px-2.5 py-1.5 rounded-lg border border-[--brand-ink]/10 hover:bg-black/5">Edit</button>
        <button onclick="deleteEvent(${ev.id}, '${ev.event_code}')" class="text-xs px-2.5 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50">Delete</button>
      </td>
    </tr>
  `).join('');
  GMT.entrance('#eventTableBody tr');
  renderPagination(res.pagination);
}

function renderPagination(p) {
  const el = document.getElementById('pagination');
  if (p.totalPages <= 1) { el.innerHTML = ''; return; }
  let html = '';
  for (let i = 1; i <= p.totalPages; i++) {
    html += `<button onclick="loadEvents(${i})" class="w-8 h-8 rounded-lg ${i===p.page ? 'btn-brand' : 'border border-[--brand-ink]/10'}">${i}</button>`;
  }
  el.innerHTML = html;
}

async function quickAction(id, action, code) {
  GMT.confirmAction(`Mark event ${code} as ${action}ed?`, async () => {
    const res = await GMT.api('api/events.php', { method: 'PUT', body: JSON.stringify({ id, action, csrf_token: CSRF_TOKEN }) });
    GMT.toast(res.message, res.success ? 'success' : 'error');
    if (res.success) loadEvents(currentPage);
  });
}

function deleteEvent(id, code) {
  GMT.confirmAction(`Delete event ${code}? This cannot be undone.`, async () => {
    const res = await GMT.api(`api/events.php?id=${id}`, { method: 'DELETE', body: JSON.stringify({ id, csrf_token: CSRF_TOKEN }) });
    GMT.toast(res.message, res.success ? 'success' : 'error');
    if (res.success) loadEvents(currentPage);
  });
}

document.getElementById('package_id').addEventListener('change', (e) => {
  const opt = e.target.selectedOptions[0];
  const price = opt.dataset.price;
  if (price) document.getElementById('price').value = parseFloat(price).toFixed(2);
  recalcBalance();
});

function recalcBalance() {
  const price = parseFloat(document.getElementById('price').value) || 0;
  const deposit = parseFloat(document.getElementById('deposit').value) || 0;
  const balance = Math.max(0, price - deposit);
  document.getElementById('calcBalance').textContent = `${CURRENCY}${balance.toLocaleString(undefined,{minimumFractionDigits:2})}`;
}
['price','deposit'].forEach(id => document.getElementById(id).addEventListener('input', recalcBalance));

function openEventModal() {
  document.getElementById('eventForm').reset();
  document.getElementById('eventId').value = '';
  document.getElementById('eventModalTitle').textContent = 'New Event';
  recalcBalance();
  GMT.openModal('eventModal');
}

async function editEvent(id) {
  const res = await GMT.api(`api/events.php?id=${id}`);
  if (!res.success) { GMT.toast(res.message, 'error'); return; }
  const ev = res.data;
  document.getElementById('eventModalTitle').textContent = `Edit ${ev.event_code}`;
  document.getElementById('eventId').value = ev.id;
  for (const key of ['client_name','client_phone','client_email','event_name','event_type','event_date','start_time','end_time','venue','number_of_guests','price','deposit','status','special_requirements']) {
    const field = document.getElementById(key);
    if (field) field.value = ev[key] ?? '';
  }
  document.getElementById('package_id').value = ev.package_id || '';
  recalcBalance();
  GMT.openModal('eventModal');
}

document.getElementById('eventForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const id = document.getElementById('eventId').value;
  const payload = Object.fromEntries(new FormData(e.target).entries());
  payload.package_id = document.getElementById('package_id').value;
  payload.csrf_token = CSRF_TOKEN;
  if (!id) delete payload.id;
  const res = await GMT.api('api/events.php', { method: id ? 'PUT' : 'POST', body: JSON.stringify(payload) });
  GMT.toast(res.message, res.success ? 'success' : 'error');
  if (res.success) { GMT.closeModal('eventModal'); loadEvents(currentPage); }
});

function shiftMonth(delta) {
  calendarMonth.setMonth(calendarMonth.getMonth() + delta);
  loadCalendar();
}

async function loadCalendar() {
  const monthStr = `${calendarMonth.getFullYear()}-${String(calendarMonth.getMonth()+1).padStart(2,'0')}`;
  document.getElementById('calendarMonthLabel').textContent = calendarMonth.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

  const res = await GMT.api(`api/events.php?view=calendar&month=${monthStr}`);
  const grid = document.getElementById('calendarGrid');
  if (!res.success) { grid.innerHTML = ''; return; }

  const firstDay = new Date(calendarMonth.getFullYear(), calendarMonth.getMonth(), 1);
  const daysInMonth = new Date(calendarMonth.getFullYear(), calendarMonth.getMonth()+1, 0).getDate();
  const startOffset = firstDay.getDay();

  let cells = '';
  for (let i = 0; i < startOffset; i++) cells += `<div></div>`;
  for (let d = 1; d <= daysInMonth; d++) {
    const dateStr = `${calendarMonth.getFullYear()}-${String(calendarMonth.getMonth()+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
    const dayEvents = res.data.filter(ev => ev.event_date === dateStr);
    const pills = dayEvents.slice(0,3).map(ev => {
      const color = { confirmed:'bg-green-100 text-green-800', inquiry:'bg-amber-100 text-amber-800' }[ev.status] || 'bg-gray-100';
      return `<div class="${color} rounded px-1.5 py-0.5 text-[10px] truncate" title="${ev.event_name}: ${ev.client_name}">${ev.event_name}</div>`;
    }).join('');
    const more = dayEvents.length > 3 ? `<div class="text-[10px] text-[--brand-muted]">+${dayEvents.length - 3} more</div>` : '';
    cells += `
      <div class="border border-[--brand-ink]/5 rounded-lg p-1.5 min-h-[86px] bg-white">
        <div class="text-xs text-[--brand-muted] mb-1">${d}</div>
        <div class="space-y-0.5">${pills}${more}</div>
      </div>`;
  }
  grid.innerHTML = cells;
  GMT.motionReady.then((m) => { if (m) m.animate(grid, { opacity: [0, 1] }, { duration: 0.3 }); });
}

document.getElementById('searchInput').addEventListener('input', GMT.debounce(() => loadEvents(1), 350));
['typeFilter','statusFilter'].forEach(id => document.getElementById(id).addEventListener('change', () => loadEvents(1)));

lucide.createIcons();
loadEvents();
</script>
</body>
</html>
