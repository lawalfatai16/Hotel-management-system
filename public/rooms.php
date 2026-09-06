<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireModuleAccess('rooms');
$db = Database::connect();
$pageTitle = 'Room Management';
$activeNav = 'rooms';
$roomTypes = $db->query("SELECT id, name FROM room_types WHERE deleted_at IS NULL ORDER BY name")->fetchAll();
$floors = $db->query("SELECT DISTINCT floor FROM rooms WHERE deleted_at IS NULL ORDER BY floor")->fetchAll(PDO::FETCH_COLUMN);
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
          <div class="font-display text-xl">Room Management</div>
          <div class="text-sm text-[--brand-muted]">Manage inventory, pricing, and live status across all floors</div>
        </div>
        <button onclick="openRoomModal()" class="btn-brand px-5 py-2.5 text-sm font-semibold flex items-center gap-2">
          <i data-lucide="plus" class="w-4 h-4"></i> Add Room
        </button>
      </div>

      <!-- Filters -->
      <div class="card-surface p-4 flex flex-wrap gap-3 items-center">
        <div class="relative flex-1 min-w-[200px]">
          <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[--brand-muted]"></i>
          <input id="searchInput" type="text" placeholder="Search room number or type…"
            class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-[--brand-ink]/10 focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
        <select id="floorFilter" class="text-sm rounded-lg border border-[--brand-ink]/10 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
          <option value="">All floors</option>
          <?php foreach ($floors as $f): ?><option value="<?= e((string)$f) ?>">Floor <?= e((string)$f) ?></option><?php endforeach; ?>
        </select>
        <select id="typeFilter" class="text-sm rounded-lg border border-[--brand-ink]/10 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
          <option value="">All room types</option>
          <?php foreach ($roomTypes as $t): ?><option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?>
        </select>
        <select id="statusFilter" class="text-sm rounded-lg border border-[--brand-ink]/10 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
          <option value="">All statuses</option>
          <?php foreach (['available','reserved','occupied','cleaning','maintenance','out_of_service'] as $s): ?>
            <option value="<?= $s ?>"><?= ucwords(str_replace('_',' ',$s)) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <!-- Room grid -->
      <div id="roomGrid" class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4"></div>
      <div id="emptyState" class="hidden text-center py-16 card-surface">
        <i data-lucide="bed" class="w-10 h-10 mx-auto text-[--brand-muted] mb-3"></i>
        <div class="font-medium">No rooms found</div>
        <div class="text-sm text-[--brand-muted] mt-1">Try adjusting your filters, or add a new room.</div>
        <button onclick="openRoomModal()" class="btn-brand px-5 py-2 text-sm font-semibold mt-4">Add Room</button>
      </div>

      <div id="pagination" class="flex items-center justify-center gap-2 text-sm"></div>
    </main>
  </div>
</div>

<!-- Room modal -->
<div id="roomModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
  <div class="glass-panel rounded-2xl w-full max-w-lg p-6 md:p-8">
    <div class="flex items-center justify-between mb-6">
      <div class="font-display text-lg" id="roomModalTitle">Add Room</div>
      <button onclick="GMT.closeModal('roomModal')" class="text-[--brand-muted] hover:text-[--brand-ink]"><i data-lucide="x" class="w-5 h-5"></i></button>
    </div>
    <form id="roomForm" class="space-y-4">
      <input type="hidden" id="roomId" name="id">
      <input type="hidden" id="image_path" name="image_path">
      <div>
        <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Room Photo</label>
        <div class="flex items-center gap-4">
          <div id="imagePreview" class="w-20 h-20 rounded-lg bg-[--brand-cream-2] flex items-center justify-center overflow-hidden shrink-0">
            <i data-lucide="image" class="w-6 h-6 text-[--brand-muted]"></i>
          </div>
          <input type="file" id="imageInput" accept="image/png,image/jpeg,image/webp" class="text-sm">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Room Number</label>
          <input name="room_number" id="room_number" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Floor</label>
          <input name="floor" id="floor" type="number" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Room Type</label>
          <select name="room_type_id" id="room_type_id" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
            <option value="">Select type</option>
            <?php foreach ($roomTypes as $t): ?><option value="<?= (int)$t['id'] ?>"><?= e($t['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Capacity</label>
          <input name="capacity" id="capacity" type="number" min="1" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Price / Night (<?= e(setting('currency_symbol','₦')) ?>)</label>
          <input name="price_per_night" id="price_per_night" type="number" step="0.01" min="0" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Status</label>
          <select name="status" id="status" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
            <?php foreach (['available','reserved','occupied','cleaning','maintenance','out_of_service'] as $s): ?>
              <option value="<?= $s ?>"><?= ucwords(str_replace('_',' ',$s)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>
      <div>
        <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Description</label>
        <textarea name="description" id="description" rows="2" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]"></textarea>
      </div>
      <div>
        <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Amenities (comma separated)</label>
        <input name="amenities" id="amenities" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]" placeholder="Wi-Fi, AC, Mini-bar, Smart TV">
      </div>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="GMT.closeModal('roomModal')" class="px-5 py-2.5 text-sm rounded-lg border border-[--brand-ink]/10">Cancel</button>
        <button type="submit" class="btn-brand px-5 py-2.5 text-sm font-semibold">Save Room</button>
      </div>
    </form>
  </div>
</div>

<!-- Confirm modal -->
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

document.getElementById('imageInput').addEventListener('change', async (e) => {
  const file = e.target.files[0];
  if (!file) return;
  const fd = new FormData();
  fd.append('image', file);
  fd.append('context', 'rooms');
  fd.append('csrf_token', CSRF_TOKEN);
  const preview = document.getElementById('imagePreview');
  preview.innerHTML = `<div class="skeleton w-full h-full"></div>`;
  const res = await fetch('api/upload_image.php', { method: 'POST', body: fd });
  const data = await res.json();
  if (data.success) {
    document.getElementById('image_path').value = data.path;
    preview.innerHTML = `<img src="${data.path}" class="w-full h-full object-cover">`;
  } else {
    GMT.toast(data.message, 'error');
    preview.innerHTML = `<i data-lucide="image" class="w-6 h-6 text-[--brand-muted]"></i>`;
    lucide.createIcons();
  }
});

function resetImagePreview(path) {
  const preview = document.getElementById('imagePreview');
  document.getElementById('image_path').value = path || '';
  document.getElementById('imageInput').value = '';
  preview.innerHTML = path ? `<img src="${path}" class="w-full h-full object-cover">` : `<i data-lucide="image" class="w-6 h-6 text-[--brand-muted]"></i>`;
  lucide.createIcons();
}

function statusBadge(status) {
  const labels = { available:'Available', reserved:'Reserved', occupied:'Occupied', cleaning:'Cleaning', maintenance:'Maintenance', out_of_service:'Out of Service' };
  return `<span class="badge badge-${status}">${labels[status] || status}</span>`;
}

async function loadRooms(page = 1) {
  currentPage = page;
  const params = new URLSearchParams({
    search: document.getElementById('searchInput').value,
    floor: document.getElementById('floorFilter').value,
    type: document.getElementById('typeFilter').value,
    status: document.getElementById('statusFilter').value,
    page,
  });
  const grid = document.getElementById('roomGrid');
  grid.innerHTML = Array.from({length:4}).map(() => `<div class="skeleton h-48 rounded-xl"></div>`).join('');

  const res = await GMT.api(`api/rooms.php?${params.toString()}`);
  if (!res.success) { GMT.toast(res.message || 'Unable to load rooms.', 'error'); grid.innerHTML=''; return; }

  document.getElementById('emptyState').classList.toggle('hidden', res.data.length > 0);
  grid.innerHTML = res.data.map(room => `
    <div class="kpi-card overflow-hidden">
      <div class="h-32 bg-[--brand-cream-2] flex items-center justify-center">
        ${room.image_path ? `<img src="${room.image_path}" class="w-full h-full object-cover">` : `<i data-lucide="bed-double" class="w-8 h-8 text-[--brand-muted]"></i>`}
      </div>
      <div class="p-5">
      <div class="flex items-start justify-between mb-3">
        <div>
          <div class="font-display text-lg">Room ${room.room_number}</div>
          <div class="text-xs text-[--brand-muted]">${room.room_type_name} · Floor ${room.floor}</div>
        </div>
        ${statusBadge(room.status)}
      </div>
      <div class="text-sm text-[--brand-muted] mb-1">Capacity: ${room.capacity} guest(s)</div>
      <div class="text-lg font-semibold mb-4">${room.price_display}<span class="text-xs font-normal text-[--brand-muted]"> / night</span></div>
      <div class="flex gap-2">
        <button onclick='editRoom(${JSON.stringify(room)})' class="flex-1 py-2 text-xs rounded-lg border border-[--brand-ink]/10 hover:bg-black/5">Edit</button>
        <button onclick="deleteRoom(${room.id}, '${room.room_number}')" class="flex-1 py-2 text-xs rounded-lg border border-red-200 text-red-600 hover:bg-red-50">Delete</button>
      </div>
      </div>
    </div>
  `).join('');
  lucide.createIcons();
  GMT.entrance('#roomGrid > div');
  renderPagination(res.pagination);
}

function renderPagination(p) {
  const el = document.getElementById('pagination');
  if (p.totalPages <= 1) { el.innerHTML = ''; return; }
  let html = '';
  for (let i = 1; i <= p.totalPages; i++) {
    html += `<button onclick="loadRooms(${i})" class="w-8 h-8 rounded-lg ${i===p.page ? 'btn-brand' : 'border border-[--brand-ink]/10'}">${i}</button>`;
  }
  el.innerHTML = html;
}

function openRoomModal() {
  document.getElementById('roomForm').reset();
  document.getElementById('roomId').value = '';
  document.getElementById('roomModalTitle').textContent = 'Add Room';
  resetImagePreview(null);
  GMT.openModal('roomModal');
}

function editRoom(room) {
  document.getElementById('roomModalTitle').textContent = `Edit Room ${room.room_number}`;
  for (const key of ['id','room_number','floor','room_type_id','capacity','price_per_night','status','description','amenities']) {
    const field = document.getElementById(key === 'id' ? 'roomId' : key);
    if (field) field.value = room[key] ?? '';
  }
  resetImagePreview(room.image_path);
  GMT.openModal('roomModal');
}

function deleteRoom(id, number) {
  GMT.confirmAction(`Delete Room ${number}? This cannot be undone.`, async () => {
    const res = await GMT.api(`api/rooms.php?id=${id}`, { method: 'DELETE', body: JSON.stringify({ id, csrf_token: CSRF_TOKEN }) });
    GMT.toast(res.message, res.success ? 'success' : 'error');
    if (res.success) loadRooms(currentPage);
  });
}

document.getElementById('roomForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const form = e.target;
  const payload = {
    id: form.id.value || undefined,
    room_number: form.room_number.value.trim(),
    floor: form.floor.value,
    room_type_id: form.room_type_id.value,
    capacity: form.capacity.value,
    price_per_night: form.price_per_night.value,
    status: form.status.value,
    description: form.description.value,
    amenities: form.amenities.value,
    image_path: document.getElementById('image_path').value,
    csrf_token: CSRF_TOKEN,
  };
  const isEdit = !!payload.id;
  const res = await GMT.api('api/rooms.php', { method: isEdit ? 'PUT' : 'POST', body: JSON.stringify(payload) });
  GMT.toast(res.message, res.success ? 'success' : 'error');
  if (res.success) { GMT.closeModal('roomModal'); loadRooms(currentPage); }
});

['searchInput'].forEach(id => document.getElementById(id).addEventListener('input', GMT.debounce(() => loadRooms(1), 350)));
['floorFilter','typeFilter','statusFilter'].forEach(id => document.getElementById(id).addEventListener('change', () => loadRooms(1)));

lucide.createIcons();
loadRooms();
</script>
</body>
</html>
