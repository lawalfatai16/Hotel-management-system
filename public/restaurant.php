<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireModuleAccess('restaurant');
$pageTitle = 'Restaurant';
$activeNav = 'restaurant';
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

      <div>
        <div class="font-display text-xl">Restaurant</div>
        <div class="text-sm text-[--brand-muted]">Point of sale, orders, menu, and tables</div>
      </div>

      <div class="neu-toggle-group w-fit">
        <button id="tab-pos" onclick="switchTab('pos')" class="neu-toggle-btn is-active">New Order</button>
        <button id="tab-orders" onclick="switchTab('orders')" class="neu-toggle-btn">Orders</button>
        <button id="tab-menu" onclick="switchTab('menu')" class="neu-toggle-btn">Menu</button>
        <button id="tab-tables" onclick="switchTab('tables')" class="neu-toggle-btn">Tables</button>
      </div>

      <!-- POS / New Order -->
      <div id="panel-pos" class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 card-surface p-6">
          <div class="font-medium mb-4">Menu</div>
          <input id="menuSearch" type="text" placeholder="Search menu…" class="w-full mb-4 rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
          <div id="menuPicker" class="grid sm:grid-cols-2 gap-3"></div>
        </div>
        <div class="card-surface p-6 h-fit sticky top-24">
          <div class="font-medium mb-4">Order Details</div>
          <div class="neu-toggle-group mb-4 w-full">
            <button type="button" onclick="setOrderType('dine_in')" id="type-dine_in" class="neu-toggle-btn is-active flex-1 text-xs">Dine-in</button>
            <button type="button" onclick="setOrderType('room_charge')" id="type-room_charge" class="neu-toggle-btn flex-1 text-xs">Room Charge</button>
            <button type="button" onclick="setOrderType('takeaway')" id="type-takeaway" class="neu-toggle-btn flex-1 text-xs">Takeaway</button>
          </div>
          <div id="tablePickerWrap" class="mb-4">
            <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Table</label>
            <select id="tableSelect" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]"></select>
          </div>
          <div id="reservationPickerWrap" class="mb-4 hidden relative">
            <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Reservation (Room)</label>
            <input id="reservationSearch" autocomplete="off" placeholder="Search reservation or room…" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
            <input type="hidden" id="reservation_id">
            <div id="reservationResults" class="hidden absolute z-10 w-full bg-white rounded-lg shadow-lg border border-[--brand-ink]/10 mt-1 max-h-40 overflow-y-auto"></div>
          </div>

          <div id="cartItems" class="space-y-2 mb-4 max-h-52 overflow-y-auto"></div>
          <div id="cartEmpty" class="text-xs text-[--brand-muted] mb-4">No items added yet.</div>

          <div class="border-t border-[--brand-ink]/10 pt-3 space-y-1 text-sm mb-4">
            <div class="flex justify-between"><span class="text-[--brand-muted]">Subtotal</span><span id="cartSubtotal">N/A</span></div>
            <div class="flex justify-between"><span class="text-[--brand-muted]">Tax</span><span id="cartTax">N/A</span></div>
            <div class="flex justify-between font-semibold"><span>Total</span><span id="cartTotal">N/A</span></div>
          </div>
          <button onclick="submitOrder()" class="btn-brand w-full py-2.5 text-sm font-semibold">Create Order</button>
        </div>
      </div>

      <!-- Orders -->
      <div id="panel-orders" class="hidden space-y-4">
        <div class="card-surface p-4 flex flex-wrap gap-3 items-center">
          <input id="orderSearch" type="text" placeholder="Search order, guest, or reservation…" class="flex-1 min-w-[200px] text-sm rounded-lg border border-[--brand-ink]/10 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
          <select id="orderStatusFilter" class="text-sm rounded-lg border border-[--brand-ink]/10 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
            <option value="">All statuses</option>
            <option value="open">Open</option><option value="served">Served</option><option value="paid">Paid</option><option value="cancelled">Cancelled</option>
          </select>
        </div>
        <div id="ordersGrid" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4"></div>
      </div>

      <!-- Menu management -->
      <div id="panel-menu" class="hidden space-y-4">
        <div class="flex justify-between items-center">
          <div class="font-medium">Categories &amp; Items</div>
          <div class="flex gap-2">
            <button onclick="openCategoryModal()" class="px-4 py-2 text-sm rounded-lg border border-[--brand-ink]/10 hover:bg-black/5">+ Category</button>
            <button onclick="openItemModal()" class="btn-brand px-4 py-2 text-sm font-semibold">+ Menu Item</button>
          </div>
        </div>
        <div class="card-surface overflow-hidden">
          <table class="w-full text-sm">
            <thead class="bg-[--brand-cream-2] text-left text-xs uppercase tracking-wide text-[--brand-muted]">
              <tr><th class="px-5 py-3">Item</th><th class="px-5 py-3">Category</th><th class="px-5 py-3">Price</th><th class="px-5 py-3">Available</th><th class="px-5 py-3 text-right">Actions</th></tr>
            </thead>
            <tbody id="itemsTableBody" class="divide-y divide-[--brand-ink]/5"></tbody>
          </table>
        </div>
      </div>

      <!-- Tables management -->
      <div id="panel-tables" class="hidden space-y-4">
        <div class="flex justify-between items-center">
          <div class="font-medium">Restaurant Tables</div>
          <button onclick="openTableModal()" class="btn-brand px-4 py-2 text-sm font-semibold">+ Table</button>
        </div>
        <div id="tablesGrid" class="grid sm:grid-cols-3 lg:grid-cols-4 gap-4"></div>
      </div>

    </main>
  </div>
</div>

<!-- Category modal -->
<div id="categoryModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
  <div class="glass-panel rounded-2xl w-full max-w-sm p-6">
    <div class="font-display text-lg mb-4">Add Category</div>
    <form id="categoryForm" class="space-y-4">
      <input name="name" required placeholder="e.g. Main Course" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
      <div class="flex justify-end gap-3"><button type="button" onclick="GMT.closeModal('categoryModal')" class="px-4 py-2 text-sm rounded-lg border border-[--brand-ink]/10">Cancel</button><button class="btn-brand px-4 py-2 text-sm font-semibold">Save</button></div>
    </form>
  </div>
</div>

<!-- Item modal -->
<div id="itemModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
  <div class="glass-panel rounded-2xl w-full max-w-md p-6">
    <div class="font-display text-lg mb-4" id="itemModalTitle">Add Menu Item</div>
    <form id="itemForm" class="space-y-4">
      <input type="hidden" id="itemId" name="id">
      <select id="item_category_id" name="category_id" required class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]"></select>
      <input name="name" id="item_name" required placeholder="Item name" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
      <input type="number" name="price" id="item_price" min="0" step="0.01" required placeholder="Price" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
      <textarea name="description" id="item_description" rows="2" placeholder="Description (optional)" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]"></textarea>
      <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="is_available" id="item_available" checked> Available</label>
      <div class="flex justify-end gap-3"><button type="button" onclick="GMT.closeModal('itemModal')" class="px-4 py-2 text-sm rounded-lg border border-[--brand-ink]/10">Cancel</button><button class="btn-brand px-4 py-2 text-sm font-semibold">Save</button></div>
    </form>
  </div>
</div>

<!-- Table modal -->
<div id="tableModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4">
  <div class="glass-panel rounded-2xl w-full max-w-sm p-6">
    <div class="font-display text-lg mb-4">Add Table</div>
    <form id="tableForm" class="space-y-4">
      <input name="table_number" required placeholder="Table number, e.g. T-01" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
      <input type="number" name="capacity" min="1" value="4" placeholder="Capacity" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
      <div class="flex justify-end gap-3"><button type="button" onclick="GMT.closeModal('tableModal')" class="px-4 py-2 text-sm rounded-lg border border-[--brand-ink]/10">Cancel</button><button class="btn-brand px-4 py-2 text-sm font-semibold">Save</button></div>
    </form>
  </div>
</div>

<script src="assets/js/app.js"></script>
<script>
const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
const CURRENCY = <?= json_encode($currencySymbol) ?>;
let cart = [];
let orderType = 'dine_in';
let selectedReservationId = null;
let menuItemsCache = [];

function money(n) { return CURRENCY + Number(n).toLocaleString(undefined,{minimumFractionDigits:2}); }

function switchTab(tab) {
  ['pos','orders','menu','tables'].forEach(t => {
    document.getElementById(`panel-${t}`).classList.toggle('hidden', t !== tab);
    document.getElementById(`tab-${t}`).classList.toggle('is-active', t === tab);
  });
  if (tab === 'orders') loadOrders();
  if (tab === 'menu') { loadCategoriesInto('item_category_id'); loadItemsTable(); }
  if (tab === 'tables') loadTables();
}

// POS
async function loadMenuPicker() {
  const q = document.getElementById('menuSearch').value;
  const res = await GMT.api(`api/restaurant.php?resource=items&search=${encodeURIComponent(q)}`);
  if (!res.success) return;
  menuItemsCache = res.data;
  document.getElementById('menuPicker').innerHTML = res.data.filter(i => i.is_available == 1).map(i => `
    <button type="button" onclick="addToCart(${i.id})" class="text-left card-surface p-3 hover:shadow-md transition">
      <div class="text-sm font-medium">${i.name}</div>
      <div class="text-xs text-[--brand-muted]">${i.category_name}</div>
      <div class="text-sm font-semibold mt-1">${i.price_display}</div>
    </button>
  `).join('');
}

function addToCart(id) {
  const item = menuItemsCache.find(i => i.id === id);
  if (!item) return;
  const existing = cart.find(c => c.menu_item_id === id);
  if (existing) existing.quantity++;
  else cart.push({ menu_item_id: id, name: item.name, price: parseFloat(item.price), quantity: 1 });
  renderCart();
}

function renderCart() {
  const el = document.getElementById('cartItems');
  document.getElementById('cartEmpty').classList.toggle('hidden', cart.length > 0);
  el.innerHTML = cart.map((c, i) => `
    <div class="flex items-center justify-between text-sm">
      <div class="flex-1">${c.name}</div>
      <div class="flex items-center gap-2">
        <button onclick="changeQty(${i},-1)" class="w-6 h-6 rounded border border-[--brand-ink]/10">−</button>
        <span>${c.quantity}</span>
        <button onclick="changeQty(${i},1)" class="w-6 h-6 rounded border border-[--brand-ink]/10">+</button>
      </div>
    </div>
  `).join('');
  const subtotal = cart.reduce((s,c) => s + c.price * c.quantity, 0);
  const taxRate = <?= (float) setting('tax_rate', 0) ?>;
  const tax = subtotal * taxRate / 100;
  document.getElementById('cartSubtotal').textContent = money(subtotal);
  document.getElementById('cartTax').textContent = money(tax);
  document.getElementById('cartTotal').textContent = money(subtotal + tax);
}

function changeQty(i, delta) {
  cart[i].quantity += delta;
  if (cart[i].quantity <= 0) cart.splice(i, 1);
  renderCart();
}

function setOrderType(type) {
  orderType = type;
  ['dine_in','room_charge','takeaway'].forEach(t => {
    document.getElementById(`type-${t}`).classList.toggle('is-active', t === type);
  });
  document.getElementById('tablePickerWrap').classList.toggle('hidden', type !== 'dine_in');
  document.getElementById('reservationPickerWrap').classList.toggle('hidden', type !== 'room_charge');
}

async function loadTableSelect() {
  const res = await GMT.api('api/restaurant.php?resource=tables');
  if (!res.success) return;
  document.getElementById('tableSelect').innerHTML = res.data.map(t => `<option value="${t.id}" ${t.status!=='available'?'disabled':''}>${t.table_number} (${t.status})</option>`).join('');
}

document.getElementById('reservationSearch').addEventListener('input', GMT.debounce(async (e) => {
  const q = e.target.value.trim();
  const box = document.getElementById('reservationResults');
  if (q.length < 2) { box.classList.add('hidden'); return; }
  const res = await GMT.api(`api/reservations.php?search=${encodeURIComponent(q)}&status=checked_in`);
  const list = res.success ? res.data : [];
  box.innerHTML = list.length ? list.map(r => `<div class="px-3 py-2 text-sm hover:bg-black/5 cursor-pointer" onclick='pickReservation(${r.id}, "${r.reservation_code} (Room ${r.room_number})")'>${r.reservation_code}: ${r.guest_name} (Room ${r.room_number})</div>`).join('') : `<div class="px-3 py-2 text-sm text-[--brand-muted]">No checked-in reservations found</div>`;
  box.classList.remove('hidden');
}, 300));

function pickReservation(id, label) {
  selectedReservationId = id;
  document.getElementById('reservationSearch').value = label;
  document.getElementById('reservationResults').classList.add('hidden');
}

async function submitOrder() {
  if (cart.length === 0) { GMT.toast('Add at least one item.', 'error'); return; }
  const payload = {
    order_type: orderType,
    items: cart.map(c => ({ menu_item_id: c.menu_item_id, quantity: c.quantity })),
    csrf_token: CSRF_TOKEN,
  };
  if (orderType === 'dine_in') payload.table_id = document.getElementById('tableSelect').value;
  if (orderType === 'room_charge') payload.reservation_id = selectedReservationId;

  const res = await GMT.api('api/restaurant.php', { method: 'POST', body: JSON.stringify(payload) });
  GMT.toast(res.message, res.success ? 'success' : 'error');
  if (res.success) {
    cart = []; renderCart();
    loadTableSelect();
    switchTab('orders');
  }
}

// Orders
function orderStatusBadge(status) {
  const map = { open:'reserved', served:'occupied', paid:'available', cancelled:'out_of_service' };
  return `<span class="badge badge-${map[status]||'maintenance'}">${status.charAt(0).toUpperCase()+status.slice(1)}</span>`;
}

async function loadOrders() {
  const params = new URLSearchParams({ search: document.getElementById('orderSearch').value, status: document.getElementById('orderStatusFilter').value });
  const res = await GMT.api(`api/restaurant.php?${params.toString()}`);
  const grid = document.getElementById('ordersGrid');
  if (!res.success) { grid.innerHTML = ''; return; }
  grid.innerHTML = res.data.map(o => `
    <div class="card-surface p-4">
      <div class="flex justify-between items-start mb-2">
        <div class="font-medium">${o.order_code}</div>
        ${orderStatusBadge(o.status)}
      </div>
      <div class="text-xs text-[--brand-muted] mb-2">${o.table_number ? 'Table ' + o.table_number : (o.reservation_code ? 'Room charge: ' + o.reservation_code : 'Takeaway')}</div>
      <div class="text-lg font-semibold mb-3">${o.total_display}</div>
      <div class="flex gap-2 flex-wrap">
        ${o.status === 'open' ? `<button onclick="orderAction(${o.id},'served')" class="text-xs px-2.5 py-1.5 rounded-lg border border-[--brand-ink]/10 hover:bg-black/5">Mark Served</button>` : ''}
        ${o.status !== 'paid' && o.status !== 'cancelled' ? `<button onclick="orderAction(${o.id},'paid')" class="text-xs px-2.5 py-1.5 rounded-lg border border-green-200 text-green-700 hover:bg-green-50">Mark Paid</button>` : ''}
        ${o.status !== 'paid' && o.status !== 'cancelled' ? `<button onclick="orderAction(${o.id},'cancelled')" class="text-xs px-2.5 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50">Cancel</button>` : ''}
      </div>
    </div>
  `).join('');
  GMT.entrance('#ordersGrid > div');
}

async function orderAction(id, status) {
  const res = await GMT.api('api/restaurant.php', { method: 'PUT', body: JSON.stringify({ id, status, csrf_token: CSRF_TOKEN }) });
  GMT.toast(res.message, res.success ? 'success' : 'error');
  if (res.success) loadOrders();
}

document.getElementById('orderSearch').addEventListener('input', GMT.debounce(loadOrders, 350));
document.getElementById('orderStatusFilter').addEventListener('change', loadOrders);

// Menu management
async function loadCategoriesInto(selectId) {
  const res = await GMT.api('api/restaurant.php?resource=categories');
  if (!res.success) return;
  document.getElementById(selectId).innerHTML = res.data.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
}

async function loadItemsTable() {
  const res = await GMT.api('api/restaurant.php?resource=items');
  const body = document.getElementById('itemsTableBody');
  if (!res.success) { body.innerHTML = ''; return; }
  body.innerHTML = res.data.map(i => `
    <tr><td class="px-5 py-3">${i.name}</td><td class="px-5 py-3">${i.category_name}</td><td class="px-5 py-3">${i.price_display}</td>
    <td class="px-5 py-3">${i.is_available == 1 ? '<span class="badge badge-available">Yes</span>' : '<span class="badge badge-out_of_service">No</span>'}</td>
    <td class="px-5 py-3 text-right space-x-1">
      <button onclick='editItem(${JSON.stringify(i)})' class="text-xs px-2.5 py-1.5 rounded-lg border border-[--brand-ink]/10 hover:bg-black/5">Edit</button>
      <button onclick="deleteItem(${i.id})" class="text-xs px-2.5 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50">Delete</button>
    </td></tr>
  `).join('');
}

function openCategoryModal() { document.getElementById('categoryForm').reset(); GMT.openModal('categoryModal'); }
document.getElementById('categoryForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const name = e.target.name.value;
  const res = await GMT.api('api/restaurant.php?resource=categories', { method: 'POST', body: JSON.stringify({ name, csrf_token: CSRF_TOKEN }) });
  GMT.toast(res.message, res.success ? 'success' : 'error');
  if (res.success) { GMT.closeModal('categoryModal'); loadCategoriesInto('item_category_id'); }
});

function openItemModal() {
  document.getElementById('itemForm').reset();
  document.getElementById('itemId').value = '';
  document.getElementById('itemModalTitle').textContent = 'Add Menu Item';
  document.getElementById('item_available').checked = true;
  loadCategoriesInto('item_category_id');
  GMT.openModal('itemModal');
}

function editItem(i) {
  document.getElementById('itemModalTitle').textContent = 'Edit Menu Item';
  document.getElementById('itemId').value = i.id;
  document.getElementById('item_name').value = i.name;
  document.getElementById('item_price').value = i.price;
  document.getElementById('item_description').value = i.description || '';
  document.getElementById('item_available').checked = i.is_available == 1;
  loadCategoriesInto('item_category_id').then(() => document.getElementById('item_category_id').value = i.category_id);
  GMT.openModal('itemModal');
}

function deleteItem(id) {
  GMT.confirmAction('Delete this menu item?', async () => {
    const res = await GMT.api(`api/restaurant.php?resource=items&id=${id}`, { method: 'DELETE', body: JSON.stringify({ id, csrf_token: CSRF_TOKEN }) });
    GMT.toast(res.message, res.success ? 'success' : 'error');
    if (res.success) loadItemsTable();
  });
}

document.getElementById('itemForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const id = document.getElementById('itemId').value;
  const payload = {
    id: id || undefined,
    category_id: document.getElementById('item_category_id').value,
    name: document.getElementById('item_name').value,
    price: document.getElementById('item_price').value,
    description: document.getElementById('item_description').value,
    is_available: document.getElementById('item_available').checked ? 1 : 0,
    csrf_token: CSRF_TOKEN,
  };
  const res = await GMT.api(`api/restaurant.php?resource=items`, { method: id ? 'PUT' : 'POST', body: JSON.stringify(payload) });
  GMT.toast(res.message, res.success ? 'success' : 'error');
  if (res.success) { GMT.closeModal('itemModal'); loadItemsTable(); }
});

// Tables management
function tableStatusBadge(status) {
  const map = { available:'available', occupied:'occupied', reserved:'reserved' };
  return `<span class="badge badge-${map[status]||'maintenance'}">${status.charAt(0).toUpperCase()+status.slice(1)}</span>`;
}

async function loadTables() {
  const res = await GMT.api('api/restaurant.php?resource=tables');
  const grid = document.getElementById('tablesGrid');
  if (!res.success) { grid.innerHTML = ''; return; }
  grid.innerHTML = res.data.map(t => `
    <div class="card-surface p-4 text-center">
      <div class="font-display text-lg mb-1">${t.table_number}</div>
      <div class="text-xs text-[--brand-muted] mb-2">Seats ${t.capacity}</div>
      ${tableStatusBadge(t.status)}
      <button onclick="deleteTable(${t.id})" class="block mx-auto mt-3 text-xs px-2.5 py-1.5 rounded-lg border border-red-200 text-red-600 hover:bg-red-50">Delete</button>
    </div>
  `).join('');
  GMT.entrance('#tablesGrid > div');
}

function openTableModal() { document.getElementById('tableForm').reset(); GMT.openModal('tableModal'); }
document.getElementById('tableForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const payload = Object.fromEntries(new FormData(e.target).entries());
  payload.csrf_token = CSRF_TOKEN;
  const res = await GMT.api('api/restaurant.php?resource=tables', { method: 'POST', body: JSON.stringify(payload) });
  GMT.toast(res.message, res.success ? 'success' : 'error');
  if (res.success) { GMT.closeModal('tableModal'); loadTables(); loadTableSelect(); }
});

function deleteTable(id) {
  GMT.confirmAction('Delete this table?', async () => {
    const res = await GMT.api(`api/restaurant.php?resource=tables&id=${id}`, { method: 'DELETE', body: JSON.stringify({ id, csrf_token: CSRF_TOKEN }) });
    GMT.toast(res.message, res.success ? 'success' : 'error');
    if (res.success) { loadTables(); loadTableSelect(); }
  });
}

document.getElementById('menuSearch').addEventListener('input', GMT.debounce(loadMenuPicker, 300));

lucide.createIcons();
setOrderType('dine_in');
loadMenuPicker();
loadTableSelect();
</script>
</body>
</html>
