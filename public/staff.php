<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireModuleAccess('staff');
$db = Database::connect();
$pageTitle = 'Staff';
$activeNav = 'staff';
$csrfToken = Auth::csrfToken();
$roles = $db->query("SELECT id, name FROM roles ORDER BY name")->fetchAll();
$departments = ['Front Office','Housekeeping','Accounts','Events','Restaurant','Management','Maintenance'];
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
          <div class="font-display text-xl">Staff</div>
          <div class="text-sm text-[--brand-muted]">Everyone on the GMT Hotel team</div>
        </div>
        <button onclick="openStaffModal()" class="btn-brand px-5 py-2.5 text-sm font-semibold flex items-center gap-2">
          <i data-lucide="user-plus" class="w-4 h-4"></i> Add Staff
        </button>
      </div>

      <div class="card-surface p-4 flex flex-wrap gap-3 items-center">
        <div class="relative flex-1 min-w-[200px]">
          <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[--brand-muted]"></i>
          <input id="searchInput" type="text" placeholder="Search name, phone, or email…" class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-[--brand-ink]/10 focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
        <select id="departmentFilter" class="text-sm rounded-lg border border-[--brand-ink]/10 px-3 py-2">
          <option value="">All departments</option>
          <?php foreach ($departments as $d): ?><option value="<?= e($d) ?>"><?= e($d) ?></option><?php endforeach; ?>
        </select>
        <select id="statusFilter" class="text-sm rounded-lg border border-[--brand-ink]/10 px-3 py-2">
          <option value="">All statuses</option>
          <option value="active">Active</option>
          <option value="on_leave">On Leave</option>
          <option value="terminated">Terminated</option>
        </select>
      </div>

      <div class="card-surface overflow-hidden">
        <div class="table-scroll">
          <table class="w-full text-sm">
            <thead class="bg-[--brand-cream-2] text-left text-xs uppercase tracking-wide text-[--brand-muted]">
              <tr><th class="px-5 py-3">Name</th><th class="px-5 py-3">Department</th><th class="px-5 py-3">Position</th><th class="px-5 py-3">Contact</th><th class="px-5 py-3">System Role</th><th class="px-5 py-3">Status</th><th class="px-5 py-3 text-right">Actions</th></tr>
            </thead>
            <tbody id="staffTableBody" class="divide-y divide-[--brand-ink]/5"></tbody>
          </table>
        </div>
        <div id="emptyState" class="hidden text-center py-16">
          <i data-lucide="id-card" class="w-10 h-10 mx-auto text-[--brand-muted] mb-3"></i>
          <div class="font-medium">No staff found</div>
        </div>
      </div>
      <div id="pagination" class="flex items-center justify-center gap-2 text-sm"></div>
    </main>
  </div>
</div>

<div id="staffModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
  <div class="glass-panel rounded-2xl w-full max-w-lg p-6 md:p-8">
    <div class="flex items-center justify-between mb-6">
      <div class="font-display text-lg" id="staffModalTitle">Add Staff</div>
      <button onclick="GMT.closeModal('staffModal')" class="text-[--brand-muted] hover:text-[--brand-ink]"><i data-lucide="x" class="w-5 h-5"></i></button>
    </div>
    <form id="staffForm" class="space-y-4">
      <input type="hidden" id="staffId" name="id">
      <div>
        <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Full Name</label>
        <input name="full_name" id="full_name" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm">
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Department</label>
          <select name="department" id="department" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm">
            <?php foreach ($departments as $d): ?><option value="<?= e($d) ?>"><?= e($d) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Position</label>
          <input name="position" id="position" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Phone</label>
          <input name="phone" id="phone" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm">
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Email</label>
          <input name="email" id="email" type="email" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm">
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">System Role</label>
          <select name="role_id" id="role_id" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm">
            <option value="">None (no login access)</option>
            <?php foreach ($roles as $r): ?><option value="<?= (int)$r['id'] ?>"><?= e($r['name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Date Employed</label>
          <input type="date" name="date_employed" id="date_employed" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm">
        </div>
      </div>
      <div>
        <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Status</label>
        <select name="status" id="status" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm">
          <option value="active">Active</option>
          <option value="on_leave">On Leave</option>
          <option value="terminated">Terminated</option>
        </select>
      </div>
      <p class="text-xs text-[--brand-muted]">Assigning a system role records who they are for permission purposes. To actually let them log in, create a matching account in the <code>users</code> table with that role.</p>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="GMT.closeModal('staffModal')" class="px-5 py-2.5 text-sm rounded-lg border border-[--brand-ink]/10">Cancel</button>
        <button type="submit" class="btn-brand px-5 py-2.5 text-sm font-semibold">Save Staff</button>
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

function statusBadge(status) {
  const map = { active:'available', on_leave:'reserved', terminated:'out_of_service' };
  const labels = { active:'Active', on_leave:'On Leave', terminated:'Terminated' };
  return `<span class="badge badge-${map[status]||'maintenance'}">${labels[status]||status}</span>`;
}

async function loadStaff(page = 1) {
  currentPage = page;
  const params = new URLSearchParams({
    search: document.getElementById('searchInput').value,
    department: document.getElementById('departmentFilter').value,
    status: document.getElementById('statusFilter').value,
    page,
  });
  const body = document.getElementById('staffTableBody');
  body.innerHTML = `<tr><td colspan="7" class="px-5 py-6"><div class="skeleton h-6 rounded"></div></td></tr>`;
  const res = await GMT.api(`api/staff.php?${params.toString()}`);
  if (!res.success) { GMT.toast(res.message || 'Unable to load staff.', 'error'); body.innerHTML=''; return; }
  document.getElementById('emptyState').classList.toggle('hidden', res.data.length > 0);
  body.innerHTML = res.data.map(s => `
    <tr class="hover:bg-black/[0.02]">
      <td class="px-5 py-3 font-medium">${s.full_name}</td>
      <td class="px-5 py-3">${s.department}</td>
      <td class="px-5 py-3">${s.position}</td>
      <td class="px-5 py-3 text-[--brand-muted]">${s.phone || ''}${s.phone && s.email ? ' · ' : ''}${s.email || ''}</td>
      <td class="px-5 py-3">${s.role_name || '—'}</td>
      <td class="px-5 py-3">${statusBadge(s.status)}</td>
      <td class="px-5 py-3 text-right space-x-1 whitespace-nowrap">
        <button onclick='editStaff(${JSON.stringify(s)})' class="text-xs px-2.5 py-1.5 rounded-lg border border-[--brand-ink]/10 hover:bg-black/5">Edit</button>
        <button onclick="deleteStaff(${s.id}, '${s.full_name.replace(/'/g,"\\'")}')" class="text-xs px-2.5 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50">Delete</button>
      </td>
    </tr>
  `).join('');
  GMT.entrance('#staffTableBody tr');
  renderPagination(res.pagination);
}

function renderPagination(p) {
  const el = document.getElementById('pagination');
  if (p.totalPages <= 1) { el.innerHTML = ''; return; }
  let html = '';
  for (let i = 1; i <= p.totalPages; i++) html += `<button onclick="loadStaff(${i})" class="w-8 h-8 rounded-lg ${i===p.page ? 'btn-brand' : 'border border-[--brand-ink]/10'}">${i}</button>`;
  el.innerHTML = html;
}

function openStaffModal() {
  document.getElementById('staffForm').reset();
  document.getElementById('staffId').value = '';
  document.getElementById('staffModalTitle').textContent = 'Add Staff';
  GMT.openModal('staffModal');
}

function editStaff(s) {
  document.getElementById('staffModalTitle').textContent = `Edit ${s.full_name}`;
  for (const key of ['id','full_name','department','position','phone','email','role_id','status','date_employed']) {
    const field = document.getElementById(key === 'id' ? 'staffId' : key);
    if (field) field.value = s[key] ?? '';
  }
  GMT.openModal('staffModal');
}

function deleteStaff(id, name) {
  GMT.confirmAction(`Remove ${name} from staff records?`, async () => {
    const res = await GMT.api(`api/staff.php?id=${id}`, { method: 'DELETE', body: JSON.stringify({ id, csrf_token: CSRF_TOKEN }) });
    GMT.toast(res.message, res.success ? 'success' : 'error');
    if (res.success) loadStaff(currentPage);
  });
}

document.getElementById('staffForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const payload = Object.fromEntries(new FormData(e.target).entries());
  payload.csrf_token = CSRF_TOKEN;
  if (!payload.id) delete payload.id;
  const res = await GMT.api('api/staff.php', { method: payload.id ? 'PUT' : 'POST', body: JSON.stringify(payload) });
  GMT.toast(res.message, res.success ? 'success' : 'error');
  if (res.success) { GMT.closeModal('staffModal'); loadStaff(currentPage); }
});

document.getElementById('searchInput').addEventListener('input', GMT.debounce(() => loadStaff(1), 350));
['departmentFilter','statusFilter'].forEach(id => document.getElementById(id).addEventListener('change', () => loadStaff(1)));

lucide.createIcons();
loadStaff();
</script>
</body>
</html>
