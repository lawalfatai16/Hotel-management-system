<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireModuleAccess('room_types');
$pageTitle = 'Room Types';
$activeNav = 'room_types';
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
          <div class="font-display text-xl">Room Types</div>
          <div class="text-sm text-[--brand-muted]">Categories rooms are built from: pricing, capacity, and amenities</div>
        </div>
        <button onclick="openTypeModal()" class="btn-brand px-5 py-2.5 text-sm font-semibold flex items-center gap-2">
          <i data-lucide="plus" class="w-4 h-4"></i> Add Room Type
        </button>
      </div>

      <div class="card-surface p-4">
        <div class="relative max-w-md">
          <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[--brand-muted]"></i>
          <input id="searchInput" type="text" placeholder="Search room types…"
            class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-[--brand-ink]/10 focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
      </div>

      <div id="typeGrid" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4"></div>
      <div id="emptyState" class="hidden text-center py-16 card-surface">
        <i data-lucide="layers" class="w-10 h-10 mx-auto text-[--brand-muted] mb-3"></i>
        <div class="font-medium">No room types found</div>
        <div class="text-sm text-[--brand-muted] mt-1">Add a room type to start building your room inventory.</div>
      </div>

    </main>
  </div>
</div>

<!-- Room type modal -->
<div id="typeModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
  <div class="glass-panel rounded-2xl w-full max-w-lg p-6 md:p-8">
    <div class="flex items-center justify-between mb-6">
      <div class="font-display text-lg" id="typeModalTitle">Add Room Type</div>
      <button onclick="GMT.closeModal('typeModal')" class="text-[--brand-muted] hover:text-[--brand-ink]"><i data-lucide="x" class="w-5 h-5"></i></button>
    </div>
    <form id="typeForm" class="space-y-4">
      <input type="hidden" id="typeId" name="id">
      <div>
        <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Name</label>
        <input name="name" id="name" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]" placeholder="e.g. Deluxe Room">
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Base Price (<?= e($currencySymbol) ?>)</label>
          <input type="number" name="base_price" id="base_price" min="0" step="0.01" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Capacity</label>
          <input type="number" name="capacity" id="capacity" min="1" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
      </div>
      <div>
        <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Description</label>
        <textarea name="description" id="description" rows="2" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]"></textarea>
      </div>
      <div>
        <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Amenities (comma separated)</label>
        <input name="amenities" id="amenities" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]" placeholder="Wi-Fi, AC, Smart TV, Mini-bar">
      </div>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="GMT.closeModal('typeModal')" class="px-5 py-2.5 text-sm rounded-lg border border-[--brand-ink]/10">Cancel</button>
        <button type="submit" class="btn-brand px-5 py-2.5 text-sm font-semibold">Save Room Type</button>
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
let allTypes = [];

async function loadTypes() {
  const search = document.getElementById('searchInput').value;
  const grid = document.getElementById('typeGrid');
  grid.innerHTML = Array.from({length:3}).map(() => `<div class="skeleton h-40 rounded-xl"></div>`).join('');

  const res = await GMT.api(`api/room_types.php?search=${encodeURIComponent(search)}`);
  if (!res.success) { GMT.toast(res.message || 'Unable to load room types.', 'error'); grid.innerHTML=''; return; }
  allTypes = res.data;

  document.getElementById('emptyState').classList.toggle('hidden', res.data.length > 0);
  grid.innerHTML = res.data.map(t => `
    <div class="kpi-card p-5">
      <div class="flex items-start justify-between mb-2">
        <div class="font-display text-lg">${t.name}</div>
        <span class="badge badge-available">${t.room_count} room${t.room_count == 1 ? '' : 's'}</span>
      </div>
      <div class="text-sm text-[--brand-muted] mb-2">${t.description || 'No description'}</div>
      <div class="text-xs text-[--brand-muted] mb-3">${t.amenities || 'N/A'}</div>
      <div class="text-lg font-semibold mb-4">${t.base_price_display}<span class="text-xs font-normal text-[--brand-muted]"> / night · up to ${t.capacity} guests</span></div>
      <div class="flex gap-2">
        <button onclick="editType(${t.id})" class="flex-1 py-2 text-xs rounded-lg border border-[--brand-ink]/10 hover:bg-black/5">Edit</button>
        <button onclick="deleteType(${t.id}, '${t.name.replace(/'/g,"\\'")}')" class="flex-1 py-2 text-xs rounded-lg border border-red-200 text-red-600 hover:bg-red-50">Delete</button>
      </div>
    </div>
  `).join('');
  GMT.entrance('#typeGrid > div');
  lucide.createIcons();
}

function openTypeModal() {
  document.getElementById('typeForm').reset();
  document.getElementById('typeId').value = '';
  document.getElementById('typeModalTitle').textContent = 'Add Room Type';
  GMT.openModal('typeModal');
}

function editType(id) {
  const t = allTypes.find(x => x.id === id);
  if (!t) return;
  document.getElementById('typeModalTitle').textContent = `Edit ${t.name}`;
  for (const key of ['id','name','base_price','capacity','description','amenities']) {
    const field = document.getElementById(key === 'id' ? 'typeId' : key);
    if (field) field.value = t[key] ?? '';
  }
  GMT.openModal('typeModal');
}

function deleteType(id, name) {
  GMT.confirmAction(`Delete room type "${name}"? This cannot be undone.`, async () => {
    const res = await GMT.api(`api/room_types.php?id=${id}`, { method: 'DELETE', body: JSON.stringify({ id, csrf_token: CSRF_TOKEN }) });
    GMT.toast(res.message, res.success ? 'success' : 'error');
    if (res.success) loadTypes();
  });
}

document.getElementById('typeForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = e.target;
  const payload = {
    id: form.id.value || undefined,
    name: form.name.value.trim(),
    base_price: form.base_price.value,
    capacity: form.capacity.value,
    description: form.description.value,
    amenities: form.amenities.value,
    csrf_token: CSRF_TOKEN,
  };
  const isEdit = !!payload.id;
  const res = await GMT.api('api/room_types.php', { method: isEdit ? 'PUT' : 'POST', body: JSON.stringify(payload) });
  GMT.toast(res.message, res.success ? 'success' : 'error');
  if (res.success) { GMT.closeModal('typeModal'); loadTypes(); }
});

document.getElementById('searchInput').addEventListener('input', GMT.debounce(loadTypes, 350));

lucide.createIcons();
loadTypes();
</script>
</body>
</html>
