<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireModuleAccess('housekeeping');
$db = Database::connect();
$pageTitle = 'Housekeeping';
$activeNav = 'housekeeping';
$csrfToken = Auth::csrfToken();
$staffList = $db->query("SELECT id, full_name, department FROM staff WHERE deleted_at IS NULL AND status = 'active' ORDER BY full_name")->fetchAll();
$roomsList = $db->query("SELECT id, room_number FROM rooms WHERE deleted_at IS NULL ORDER BY floor, room_number")->fetchAll();
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
          <div class="font-display text-xl">Housekeeping</div>
          <div class="text-sm text-[--brand-muted]">Room readiness across the property, and staff task assignments</div>
        </div>
        <button onclick="openTaskModal()" class="btn-brand px-5 py-2.5 text-sm font-semibold flex items-center gap-2">
          <i data-lucide="plus" class="w-4 h-4"></i> Assign Task
        </button>
      </div>

      <!-- Room status summary -->
      <div id="summaryCards" class="grid grid-cols-2 lg:grid-cols-6 gap-3"></div>

      <!-- Rooms needing attention -->
      <div class="card-surface p-6">
        <div class="font-medium mb-4">Rooms Needing Attention</div>
        <div id="attentionGrid" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4"></div>
        <div id="attentionEmpty" class="hidden text-center py-8 text-sm text-[--brand-muted]">All rooms are clean and ready. Nothing needs attention right now.</div>
      </div>

      <!-- Task board -->
      <div>
        <div class="flex items-center justify-between mb-4">
          <div class="font-medium">Task Board</div>
          <select id="priorityFilter" class="text-sm rounded-lg border border-[--brand-ink]/10 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
            <option value="">All priorities</option>
            <option value="urgent">Urgent</option>
            <option value="high">High</option>
            <option value="normal">Normal</option>
            <option value="low">Low</option>
          </select>
        </div>
        <div class="grid md:grid-cols-4 gap-4">
          <?php foreach (['pending'=>'Pending','in_progress'=>'In Progress','completed'=>'Completed','inspected'=>'Inspected'] as $status=>$label): ?>
            <div class="card-surface p-4">
              <div class="text-xs font-semibold uppercase tracking-wide text-[--brand-muted] mb-3"><?= $label ?></div>
              <div id="col-<?= $status ?>" class="space-y-3 min-h-[80px]"></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

    </main>
  </div>
</div>

<!-- Assign task modal -->
<div id="taskModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
  <div class="glass-panel rounded-2xl w-full max-w-md p-6 md:p-8">
    <div class="flex items-center justify-between mb-6">
      <div class="font-display text-lg">Assign Housekeeping Task</div>
      <button onclick="GMT.closeModal('taskModal')" class="text-[--brand-muted] hover:text-[--brand-ink]"><i data-lucide="x" class="w-5 h-5"></i></button>
    </div>
    <form id="taskForm" class="space-y-4">
      <div>
        <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Room</label>
        <select name="room_id" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
          <option value="">Select room</option>
          <?php foreach ($roomsList as $r): ?><option value="<?= (int)$r['id'] ?>">Room <?= e($r['room_number']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Task</label>
        <input name="task" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]" placeholder="e.g. Full room cleaning, Linen change">
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Assign To</label>
          <select name="assigned_staff_id" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
            <option value="">Unassigned</option>
            <?php foreach ($staffList as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['full_name']) ?>: <?= e($s['department']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Priority</label>
          <select name="priority" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
            <option value="low">Low</option>
            <option value="normal" selected>Normal</option>
            <option value="high">High</option>
            <option value="urgent">Urgent</option>
          </select>
        </div>
      </div>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="GMT.closeModal('taskModal')" class="px-5 py-2.5 text-sm rounded-lg border border-[--brand-ink]/10">Cancel</button>
        <button type="submit" class="btn-brand px-5 py-2.5 text-sm font-semibold">Assign Task</button>
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
      <button id="confirmModalYes" class="px-5 py-2 text-sm rounded-lg bg-[--brand-danger] text-white">Remove</button>
    </div>
  </div>
</div>

<script src="assets/js/app.js"></script>
<script>
const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;

const STATUS_META = {
  available: { label: 'Available', class: 'badge-available' },
  cleaning: { label: 'Cleaning', class: 'badge-cleaning' },
  maintenance: { label: 'Maintenance', class: 'badge-maintenance' },
  occupied: { label: 'Occupied', class: 'badge-occupied' },
  reserved: { label: 'Reserved', class: 'badge-reserved' },
  out_of_service: { label: 'Out of Service', class: 'badge-out_of_service' },
};

async function loadSummary() {
  const res = await GMT.api('api/housekeeping.php?summary=1');
  if (!res.success) return;
  const el = document.getElementById('summaryCards');
  el.innerHTML = Object.entries(res.data).map(([status, count]) => `
    <div class="kpi-card p-4 text-center">
      <div class="text-xl font-semibold">${count}</div>
      <div class="text-xs mt-1"><span class="badge ${STATUS_META[status]?.class || ''}">${STATUS_META[status]?.label || status}</span></div>
    </div>
  `).join('');
  GMT.entrance('#summaryCards > div');
}

async function loadAttention() {
  const res = await GMT.api('api/housekeeping.php?rooms=1');
  const grid = document.getElementById('attentionGrid');
  if (!res.success) { grid.innerHTML = ''; return; }
  document.getElementById('attentionEmpty').classList.toggle('hidden', res.data.length > 0);
  grid.innerHTML = res.data.map(r => `
    <div class="card-surface p-4">
      <div class="flex items-center justify-between mb-2">
        <div class="font-medium">Room ${r.room_number}</div>
        <span class="badge ${STATUS_META[r.status]?.class || ''}">${STATUS_META[r.status]?.label || r.status}</span>
      </div>
      <div class="text-xs text-[--brand-muted] mb-3">${r.task ? r.task + (r.staff_name ? ' · ' + r.staff_name : ' · Unassigned') : 'No active task'}</div>
      <div class="flex gap-2">
        <button onclick="quickRoomStatus(${r.id}, 'available')" class="flex-1 text-xs py-1.5 rounded-lg border border-green-200 text-green-700 hover:bg-green-50">Mark Available</button>
        <button onclick="quickRoomStatus(${r.id}, 'maintenance')" class="flex-1 text-xs py-1.5 rounded-lg border border-[--brand-ink]/10 hover:bg-black/5">Maintenance</button>
      </div>
    </div>
  `).join('');
  GMT.entrance('#attentionGrid > div');
}

async function quickRoomStatus(roomId, status) {
  const res = await GMT.api('api/housekeeping.php', { method: 'PUT', body: JSON.stringify({ target: 'room', room_id: roomId, status, csrf_token: CSRF_TOKEN }) });
  GMT.toast(res.message, res.success ? 'success' : 'error');
  if (res.success) { loadAttention(); loadSummary(); }
}

async function loadTasks() {
  const priority = document.getElementById('priorityFilter').value;
  const res = await GMT.api(`api/housekeeping.php?priority=${priority}`);
  ['pending','in_progress','completed','inspected'].forEach(s => document.getElementById(`col-${s}`).innerHTML = '');
  if (!res.success) return;

  const priorityColor = { urgent: 'text-red-600', high: 'text-amber-600', normal: 'text-[--brand-muted]', low: 'text-[--brand-muted]' };

  res.data.forEach(t => {
    const col = document.getElementById(`col-${t.status}`);
    if (!col) return;
    const card = document.createElement('div');
    card.className = 'rounded-lg border border-[--brand-ink]/10 p-3 text-sm bg-white';
    card.innerHTML = `
      <div class="flex items-center justify-between mb-1">
        <div class="font-medium">Room ${t.room_number}</div>
        <span class="text-[10px] font-semibold uppercase ${priorityColor[t.priority] || ''}">${t.priority}</span>
      </div>
      <div class="text-[--brand-muted] text-xs mb-2">${t.task}</div>
      <div class="text-xs text-[--brand-muted] mb-3">${t.staff_name || 'Unassigned'}</div>
      <div class="flex gap-1.5 flex-wrap">
        ${nextStatusButtons(t)}
        <button onclick="deleteTask(${t.id})" class="text-xs px-2 py-1 rounded border border-red-200 text-red-600 hover:bg-red-50 inline-flex items-center"><i data-lucide="x" class="w-3 h-3"></i></button>
      </div>
    `;
    col.appendChild(card);
  });
  GMT.entrance('[id^="col-"] > div');
  lucide.createIcons();
}

function nextStatusButtons(t) {
  const flow = { pending: 'in_progress', in_progress: 'completed', completed: 'inspected' };
  const next = flow[t.status];
  if (!next) return '';
  const labels = { in_progress: 'Start', completed: 'Complete', inspected: 'Inspect' };
  return `<button onclick="advanceTask(${t.id}, '${next}')" class="text-xs px-2.5 py-1 rounded border border-[--brand-ink]/10 hover:bg-black/5">${labels[next]}</button>`;
}

async function advanceTask(id, status) {
  const res = await GMT.api('api/housekeeping.php', { method: 'PUT', body: JSON.stringify({ id, status, csrf_token: CSRF_TOKEN }) });
  GMT.toast(res.message, res.success ? 'success' : 'error');
  if (res.success) { loadTasks(); loadSummary(); loadAttention(); }
}

function deleteTask(id) {
  GMT.confirmAction('Remove this housekeeping task?', async () => {
    const res = await GMT.api(`api/housekeeping.php?id=${id}`, { method: 'DELETE', body: JSON.stringify({ id, csrf_token: CSRF_TOKEN }) });
    GMT.toast(res.message, res.success ? 'success' : 'error');
    if (res.success) loadTasks();
  });
}

function openTaskModal() {
  document.getElementById('taskForm').reset();
  GMT.openModal('taskModal');
}

document.getElementById('taskForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const payload = Object.fromEntries(new FormData(e.target).entries());
  payload.csrf_token = CSRF_TOKEN;
  const res = await GMT.api('api/housekeeping.php', { method: 'POST', body: JSON.stringify(payload) });
  GMT.toast(res.message, res.success ? 'success' : 'error');
  if (res.success) { GMT.closeModal('taskModal'); loadTasks(); loadSummary(); loadAttention(); }
});

document.getElementById('priorityFilter').addEventListener('change', loadTasks);

lucide.createIcons();
loadSummary();
loadAttention();
loadTasks();
</script>
</body>
</html>
