<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireModuleAccess('expenses');
$db = Database::connect();
$pageTitle = 'Expenses';
$activeNav = 'expenses';
$csrfToken = Auth::csrfToken();
$currencySymbol = setting('currency_symbol', '₦');
$staffList = $db->query("SELECT id, full_name FROM staff WHERE deleted_at IS NULL AND status = 'active' ORDER BY full_name")->fetchAll();
$categories = ['utilities'=>'Utilities','maintenance'=>'Maintenance','salaries'=>'Salaries','supplies'=>'Supplies','food'=>'Food','events'=>'Events','transportation'=>'Transportation','other'=>'Other'];
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
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
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
          <div class="font-display text-xl">Expenses</div>
          <div class="text-sm text-[--brand-muted]">Every outgoing cost, categorized</div>
        </div>
        <button onclick="openExpenseModal()" class="btn-brand px-5 py-2.5 text-sm font-semibold flex items-center gap-2">
          <i data-lucide="plus" class="w-4 h-4"></i> Record Expense
        </button>
      </div>

      <div class="grid lg:grid-cols-3 gap-6">
        <div class="neu-panel p-6 lg:col-span-1">
          <div class="font-medium mb-4">This Month by Category</div>
          <canvas id="categoryChart" height="220"></canvas>
        </div>
        <div class="neu-panel p-6 lg:col-span-2 flex flex-col justify-center items-center">
          <div class="text-xs text-[--brand-muted] uppercase tracking-wide mb-2">Total This Month</div>
          <div id="monthTotal" class="font-display text-4xl"><?= e($currencySymbol) ?>0.00</div>
        </div>
      </div>

      <div class="card-surface p-4 flex flex-wrap gap-3 items-center">
        <div class="relative flex-1 min-w-[200px]">
          <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[--brand-muted]"></i>
          <input id="searchInput" type="text" placeholder="Search description…" class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-[--brand-ink]/10 focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
        <select id="categoryFilter" class="text-sm rounded-lg border border-[--brand-ink]/10 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
          <option value="">All categories</option>
          <?php foreach ($categories as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
        </select>
        <input type="date" id="dateFrom" class="text-sm rounded-lg border border-[--brand-ink]/10 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        <input type="date" id="dateTo" class="text-sm rounded-lg border border-[--brand-ink]/10 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
      </div>

      <div class="card-surface overflow-hidden">
        <div class="table-scroll">
          <table class="w-full text-sm">
            <thead class="bg-[--brand-cream-2] text-left text-xs uppercase tracking-wide text-[--brand-muted]">
              <tr><th class="px-5 py-3">Date</th><th class="px-5 py-3">Category</th><th class="px-5 py-3">Description</th><th class="px-5 py-3">Amount</th><th class="px-5 py-3">Method</th><th class="px-5 py-3">Recorded By</th><th class="px-5 py-3 text-right">Actions</th></tr>
            </thead>
            <tbody id="expenseTableBody" class="divide-y divide-[--brand-ink]/5"></tbody>
          </table>
        </div>
        <div id="emptyState" class="hidden text-center py-16">
          <i data-lucide="wallet" class="w-10 h-10 mx-auto text-[--brand-muted] mb-3"></i>
          <div class="font-medium">No expenses found</div>
        </div>
      </div>
      <div id="pagination" class="flex items-center justify-center gap-2 text-sm"></div>
    </main>
  </div>
</div>

<div id="expenseModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
  <div class="glass-panel rounded-2xl w-full max-w-lg p-6 md:p-8">
    <div class="flex items-center justify-between mb-6">
      <div class="font-display text-lg" id="expenseModalTitle">Record Expense</div>
      <button onclick="GMT.closeModal('expenseModal')" class="text-[--brand-muted] hover:text-[--brand-ink]"><i data-lucide="x" class="w-5 h-5"></i></button>
    </div>
    <form id="expenseForm" class="space-y-4">
      <input type="hidden" id="expenseId" name="id">
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Category</label>
          <select name="category" id="category" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
            <?php foreach ($categories as $k=>$v): ?><option value="<?= $k ?>"><?= $v ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Amount (<?= e($currencySymbol) ?>)</label>
          <input type="number" name="amount" id="amount" min="0.01" step="0.01" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
      </div>
      <div>
        <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Description</label>
        <input name="description" id="description" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Date</label>
          <input type="date" name="expense_date" id="expense_date" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Payment Method</label>
          <select name="payment_method" id="payment_method" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
            <option value="cash">Cash</option><option value="bank_transfer">Bank Transfer</option>
            <option value="pos">POS</option><option value="card">Card</option><option value="other">Other</option>
          </select>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Staff Member</label>
          <select name="staff_id" id="staff_id" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
            <option value="">Unspecified</option>
            <?php foreach ($staffList as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['full_name']) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Receipt / Reference</label>
          <input name="receipt_reference" id="receipt_reference" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
      </div>
      <div>
        <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Notes</label>
        <textarea name="notes" id="notes" rows="2" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]"></textarea>
      </div>
      <div class="flex justify-end gap-3 pt-2">
        <button type="button" onclick="GMT.closeModal('expenseModal')" class="px-5 py-2.5 text-sm rounded-lg border border-[--brand-ink]/10">Cancel</button>
        <button type="submit" class="btn-brand px-5 py-2.5 text-sm font-semibold">Save Expense</button>
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
let categoryChart = null;

async function loadSummary() {
  const res = await GMT.api('api/expenses.php?summary=1');
  if (!res.success) return;
  document.getElementById('monthTotal').textContent = CURRENCY + Number(res.data.total).toLocaleString(undefined,{minimumFractionDigits:2});
  const labels = Object.keys(res.data.by_category).map(k => k.charAt(0).toUpperCase()+k.slice(1));
  const values = Object.values(res.data.by_category);
  if (categoryChart) categoryChart.destroy();
  categoryChart = new Chart(document.getElementById('categoryChart'), {
    type: 'doughnut',
    data: { labels, datasets: [{ data: values, backgroundColor: ['#7A4A2B','#C89B5A','#4A2E1F','#A33B2E','#B4813C','#8A7A6C','#356487','#3C6A3F'], borderWidth: 0 }] },
    options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } } }
  });
}

function methodLabel(m) { return m.replace('_',' ').replace(/\b\w/g, c => c.toUpperCase()); }

async function loadExpenses(page = 1) {
  currentPage = page;
  const params = new URLSearchParams({
    search: document.getElementById('searchInput').value,
    category: document.getElementById('categoryFilter').value,
    date_from: document.getElementById('dateFrom').value,
    date_to: document.getElementById('dateTo').value,
    page,
  });
  const body = document.getElementById('expenseTableBody');
  body.innerHTML = `<tr><td colspan="7" class="px-5 py-6"><div class="skeleton h-6 rounded"></div></td></tr>`;
  const res = await GMT.api(`api/expenses.php?${params.toString()}`);
  if (!res.success) { GMT.toast(res.message || 'Unable to load expenses.', 'error'); body.innerHTML=''; return; }
  document.getElementById('emptyState').classList.toggle('hidden', res.data.length > 0);
  body.innerHTML = res.data.map(x => `
    <tr class="hover:bg-black/[0.02]">
      <td class="px-5 py-3">${new Date(x.expense_date).toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'})}</td>
      <td class="px-5 py-3">${x.category.charAt(0).toUpperCase()+x.category.slice(1)}</td>
      <td class="px-5 py-3">${x.description}</td>
      <td class="px-5 py-3 font-medium">${x.amount_display}</td>
      <td class="px-5 py-3">${methodLabel(x.payment_method)}</td>
      <td class="px-5 py-3 text-[--brand-muted]">${x.staff_name || 'N/A'}</td>
      <td class="px-5 py-3 text-right space-x-1 whitespace-nowrap">
        <button onclick='editExpense(${JSON.stringify(x)})' class="text-xs px-2.5 py-1.5 rounded-lg border border-[--brand-ink]/10 hover:bg-black/5">Edit</button>
        <button onclick="deleteExpense(${x.id})" class="text-xs px-2.5 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50">Delete</button>
      </td>
    </tr>
  `).join('');
  GMT.entrance('#expenseTableBody tr');
  renderPagination(res.pagination);
}

function renderPagination(p) {
  const el = document.getElementById('pagination');
  if (p.totalPages <= 1) { el.innerHTML = ''; return; }
  let html = '';
  for (let i = 1; i <= p.totalPages; i++) html += `<button onclick="loadExpenses(${i})" class="w-8 h-8 rounded-lg ${i===p.page ? 'btn-brand' : 'border border-[--brand-ink]/10'}">${i}</button>`;
  el.innerHTML = html;
}

function openExpenseModal() {
  document.getElementById('expenseForm').reset();
  document.getElementById('expenseId').value = '';
  document.getElementById('expenseModalTitle').textContent = 'Record Expense';
  document.getElementById('expense_date').value = new Date().toISOString().split('T')[0];
  GMT.openModal('expenseModal');
}

function editExpense(x) {
  document.getElementById('expenseModalTitle').textContent = 'Edit Expense';
  for (const key of ['id','category','description','amount','expense_date','payment_method','staff_id','receipt_reference','notes']) {
    const field = document.getElementById(key === 'id' ? 'expenseId' : key);
    if (field) field.value = x[key] ?? '';
  }
  GMT.openModal('expenseModal');
}

function deleteExpense(id) {
  GMT.confirmAction('Delete this expense record?', async () => {
    const res = await GMT.api(`api/expenses.php?id=${id}`, { method: 'DELETE', body: JSON.stringify({ id, csrf_token: CSRF_TOKEN }) });
    GMT.toast(res.message, res.success ? 'success' : 'error');
    if (res.success) { loadExpenses(currentPage); loadSummary(); }
  });
}

document.getElementById('expenseForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const payload = Object.fromEntries(new FormData(e.target).entries());
  payload.csrf_token = CSRF_TOKEN;
  if (!payload.id) delete payload.id;
  const res = await GMT.api('api/expenses.php', { method: payload.id ? 'PUT' : 'POST', body: JSON.stringify(payload) });
  GMT.toast(res.message, res.success ? 'success' : 'error');
  if (res.success) { GMT.closeModal('expenseModal'); loadExpenses(currentPage); loadSummary(); }
});

document.getElementById('searchInput').addEventListener('input', GMT.debounce(() => loadExpenses(1), 350));
['categoryFilter','dateFrom','dateTo'].forEach(id => document.getElementById(id).addEventListener('change', () => loadExpenses(1)));

lucide.createIcons();
loadSummary();
loadExpenses();
</script>
</body>
</html>
