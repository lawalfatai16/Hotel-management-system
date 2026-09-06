<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireModuleAccess('guests');
$pageTitle = 'Guests';
$activeNav = 'guests';
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
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
          <div class="font-display text-xl">Guest Directory</div>
          <div class="text-sm text-[--brand-muted]">Profiles, stay history, and spending for every guest</div>
        </div>
        <button onclick="openGuestModal()" class="btn-brand px-5 py-2.5 text-sm font-semibold flex items-center gap-2">
          <i data-lucide="user-plus" class="w-4 h-4"></i> Add Guest
        </button>
      </div>

      <div class="card-surface p-4">
        <div class="relative max-w-md">
          <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[--brand-muted]"></i>
          <input id="searchInput" type="text" placeholder="Search name, phone, or email…"
            class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-[--brand-ink]/10 focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
      </div>

      <div class="card-surface overflow-hidden">
        <div class="table-scroll">
          <table class="w-full text-sm">
            <thead class="bg-[--brand-cream-2] text-left text-xs uppercase tracking-wide text-[--brand-muted]">
              <tr>
                <th class="px-5 py-3 font-medium">Guest</th>
                <th class="px-5 py-3 font-medium">Contact</th>
                <th class="px-5 py-3 font-medium">Country</th>
                <th class="px-5 py-3 font-medium">Stays</th>
                <th class="px-5 py-3 font-medium">Total Spent</th>
                <th class="px-5 py-3 font-medium text-right">Actions</th>
              </tr>
            </thead>
            <tbody id="guestTableBody" class="divide-y divide-[--brand-ink]/5"></tbody>
          </table>
        </div>
        <div id="emptyState" class="hidden text-center py-16">
          <i data-lucide="users" class="w-10 h-10 mx-auto text-[--brand-muted] mb-3"></i>
          <div class="font-medium">No guests found</div>
          <div class="text-sm text-[--brand-muted] mt-1">Try a different search, or add a new guest.</div>
        </div>
      </div>

      <div id="pagination" class="flex items-center justify-center gap-2 text-sm"></div>
    </main>
  </div>
</div>

<!-- Guest modal -->
<div id="guestModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
  <div class="glass-panel rounded-2xl w-full max-w-xl p-6 md:p-8 max-h-[90vh] overflow-y-auto">
    <div class="flex items-center justify-between mb-6">
      <div class="font-display text-lg" id="guestModalTitle">Add Guest</div>
      <button onclick="GMT.closeModal('guestModal')" class="text-[--brand-muted] hover:text-[--brand-ink]"><i data-lucide="x" class="w-5 h-5"></i></button>
    </div>
    <form id="guestForm" class="space-y-4">
      <input type="hidden" id="guestId" name="id">
      <div class="grid grid-cols-2 gap-4">
        <div class="col-span-2">
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Full Name</label>
          <input name="full_name" id="full_name" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Phone</label>
          <input name="phone" id="phone" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Email</label>
          <input name="email" id="email" type="email" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
        <div class="col-span-2">
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Address</label>
          <input name="address" id="address" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Country</label>
          <input name="country" id="country" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Date of Birth</label>
          <input name="date_of_birth" id="date_of_birth" type="date" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">ID Type</label>
          <select name="id_type" id="id_type" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
            <option value="">None</option>
            <option>Passport</option>
            <option>Driver's Licence</option>
            <option>National ID</option>
            <option>Voter's Card</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">ID Number</label>
          <input name="id_number" id="id_number" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Emergency Contact Name</label>
          <input name="emergency_contact_name" id="emergency_contact_name" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Emergency Contact Phone</label>
          <input name="emergency_contact_phone" id="emergency_contact_phone" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
        <div class="col-span-2">
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Notes</label>
          <textarea name="notes" id="notes" rows="2" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]"></textarea>
        </div>
      </div>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="GMT.closeModal('guestModal')" class="px-5 py-2.5 text-sm rounded-lg border border-[--brand-ink]/10">Cancel</button>
        <button type="submit" class="btn-brand px-5 py-2.5 text-sm font-semibold">Save Guest</button>
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
let currentPage = 1;

async function loadGuests(page = 1) {
  currentPage = page;
  const params = new URLSearchParams({ search: document.getElementById('searchInput').value, page });
  const body = document.getElementById('guestTableBody');
  body.innerHTML = `<tr><td colspan="6" class="px-5 py-6"><div class="skeleton h-6 rounded"></div></td></tr>`;

  const res = await GMT.api(`api/guests.php?${params.toString()}`);
  if (!res.success) { GMT.toast(res.message || 'Unable to load guests.', 'error'); body.innerHTML=''; return; }

  document.getElementById('emptyState').classList.toggle('hidden', res.data.length > 0);
  body.innerHTML = res.data.map(g => `
    <tr class="hover:bg-black/[0.02]">
      <td class="px-5 py-3">
        <a href="guest_profile.php?id=${g.id}" class="font-medium hover:text-[--brand-cognac]">${g.full_name}</a>
      </td>
      <td class="px-5 py-3 text-[--brand-muted]">${g.phone || ''}${g.phone && g.email ? ' · ' : ''}${g.email || ''}</td>
      <td class="px-5 py-3 text-[--brand-muted]">${g.country || 'N/A'}</td>
      <td class="px-5 py-3">${g.stays}</td>
      <td class="px-5 py-3">${'<?= e(setting("currency_symbol","₦")) ?>' + Number(g.total_spent).toLocaleString(undefined,{minimumFractionDigits:2})}</td>
      <td class="px-5 py-3 text-right space-x-2 whitespace-nowrap">
        <button onclick='editGuest(${JSON.stringify(g)})' class="text-xs px-3 py-1.5 rounded-lg border border-[--brand-ink]/10 hover:bg-black/5">Edit</button>
        <button onclick="deleteGuest(${g.id}, '${g.full_name.replace(/'/g,"\\'")}')" class="text-xs px-3 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50">Delete</button>
      </td>
    </tr>
  `).join('');
  GMT.entrance('#guestTableBody tr');
  renderPagination(res.pagination);
}

function renderPagination(p) {
  const el = document.getElementById('pagination');
  if (p.totalPages <= 1) { el.innerHTML = ''; return; }
  let html = '';
  for (let i = 1; i <= p.totalPages; i++) {
    html += `<button onclick="loadGuests(${i})" class="w-8 h-8 rounded-lg ${i===p.page ? 'btn-brand' : 'border border-[--brand-ink]/10'}">${i}</button>`;
  }
  el.innerHTML = html;
}

function openGuestModal() {
  document.getElementById('guestForm').reset();
  document.getElementById('guestId').value = '';
  document.getElementById('guestModalTitle').textContent = 'Add Guest';
  GMT.openModal('guestModal');
}

function editGuest(g) {
  document.getElementById('guestModalTitle').textContent = `Edit ${g.full_name}`;
  for (const key of ['id','full_name','phone','email','address','country','date_of_birth','id_type','id_number','emergency_contact_name','emergency_contact_phone','notes']) {
    const field = document.getElementById(key === 'id' ? 'guestId' : key);
    if (field) field.value = g[key] ?? '';
  }
  GMT.openModal('guestModal');
}

function deleteGuest(id, name) {
  GMT.confirmAction(`Delete guest "${name}"? This cannot be undone.`, async () => {
    const res = await GMT.api(`api/guests.php?id=${id}`, { method: 'DELETE', body: JSON.stringify({ id, csrf_token: CSRF_TOKEN }) });
    GMT.toast(res.message, res.success ? 'success' : 'error');
    if (res.success) loadGuests(currentPage);
  });
}

document.getElementById('guestForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = e.target;
  const payload = Object.fromEntries(new FormData(form).entries());
  payload.csrf_token = CSRF_TOKEN;
  if (!payload.id) delete payload.id;
  const isEdit = !!payload.id;
  const res = await GMT.api('api/guests.php', { method: isEdit ? 'PUT' : 'POST', body: JSON.stringify(payload) });
  GMT.toast(res.message, res.success ? 'success' : 'error');
  if (res.success) { GMT.closeModal('guestModal'); loadGuests(currentPage); }
});

document.getElementById('searchInput').addEventListener('input', GMT.debounce(() => loadGuests(1), 350));

lucide.createIcons();
loadGuests();
</script>
</body>
</html>
