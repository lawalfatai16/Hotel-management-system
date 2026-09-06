<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

Auth::requireModuleAccess('checkin');
$pageTitle = 'Check-In';
$activeNav = 'checkin';
$csrfToken = Auth::csrfToken();
$hotelName = setting('hotel_name', 'GMT Hotel and Events Centre');
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
    #sidebar, header, .no-print { display: none !important; }
    main { padding: 0 !important; }
    #checkinSlip { display: block !important; }
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
      <div class="no-print">
        <div class="font-display text-xl">Guest Check-In</div>
        <div class="text-sm text-[--brand-muted]">Reservations due for arrival: verify the guest, confirm the room, and check them in</div>
      </div>

      <div class="card-surface p-4 no-print">
        <div class="relative max-w-md">
          <i data-lucide="search" class="w-4 h-4 absolute left-3 top-1/2 -translate-y-1/2 text-[--brand-muted]"></i>
          <input id="searchInput" type="text" placeholder="Search reservation code, guest, or room…"
            class="w-full pl-9 pr-3 py-2 text-sm rounded-lg border border-[--brand-ink]/10 focus:outline-none focus:ring-2 focus:ring-[--brand-gold]">
        </div>
      </div>

      <div id="pendingList" class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4 no-print"></div>
      <div id="emptyState" class="hidden text-center py-16 card-surface no-print">
        <i data-lucide="calendar-check" class="w-10 h-10 mx-auto text-[--brand-muted] mb-3"></i>
        <div class="font-medium">No arrivals waiting</div>
        <div class="text-sm text-[--brand-muted] mt-1">Reservations due for check-in today or overdue will appear here.</div>
      </div>
    </main>
  </div>
</div>

<!-- Check-in confirm modal -->
<div id="checkinModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4 no-print">
  <div class="glass-panel rounded-2xl w-full max-w-lg p-6 md:p-8">
    <div class="flex items-center justify-between mb-6">
      <div class="font-display text-lg">Confirm Check-In</div>
      <button onclick="GMT.closeModal('checkinModal')" class="text-[--brand-muted] hover:text-[--brand-ink]"><i data-lucide="x" class="w-5 h-5"></i></button>
    </div>
    <div id="checkinDetail" class="space-y-4 text-sm"></div>
    <div class="mt-4">
      <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Notes (optional)</label>
      <textarea id="checkinNotes" rows="2" class="w-full rounded-lg border border-[--brand-ink]/10 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]"></textarea>
    </div>
    <div class="flex justify-end gap-3 pt-6">
      <button type="button" onclick="GMT.closeModal('checkinModal')" class="px-5 py-2.5 text-sm rounded-lg border border-[--brand-ink]/10">Cancel</button>
      <button id="confirmCheckinBtn" class="btn-brand px-5 py-2.5 text-sm font-semibold">Confirm Check-In</button>
    </div>
    <div id="checkinPostActions" class="hidden flex justify-end gap-3 pt-3 border-t border-[--brand-ink]/10 mt-3">
      <button onclick="printSlip()" class="px-4 py-2 text-xs rounded-lg border border-[--brand-ink]/10 hover:bg-black/5">Print Slip</button>
      <button onclick="sendCheckinWhatsApp()" class="px-4 py-2 text-xs rounded-lg border border-green-200 text-green-700 hover:bg-green-50 flex items-center gap-1.5"><i data-lucide="message-circle" class="w-3.5 h-3.5"></i> Send via WhatsApp</button>
    </div>
  </div>
</div>

<!-- Printable check-in slip (hidden on screen, shown on print) -->
<div id="checkinSlip" class="hidden">
  <div style="padding:40px;font-family:Georgia,serif;">
    <h1 style="font-size:22px;margin-bottom:4px;"><?= e($hotelName) ?></h1>
    <div style="font-size:13px;color:#555;margin-bottom:24px;">Check-In Record</div>
    <div id="slipContent" style="font-size:14px;line-height:1.8;"></div>
  </div>
</div>

<script src="assets/js/app.js"></script>
<script>
const CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
let selectedReservationId = null;

async function loadPending() {
  const params = new URLSearchParams({ search: document.getElementById('searchInput').value });
  const list = document.getElementById('pendingList');
  list.innerHTML = Array.from({length:3}).map(() => `<div class="skeleton h-32 rounded-xl"></div>`).join('');

  const res = await GMT.api(`api/checkin.php?${params.toString()}`);
  if (!res.success) { GMT.toast(res.message || 'Unable to load arrivals.', 'error'); list.innerHTML=''; return; }

  document.getElementById('emptyState').classList.toggle('hidden', res.data.length > 0);
  list.innerHTML = res.data.map(r => `
    <div class="kpi-card p-5">
      <div class="flex items-start justify-between mb-2">
        <div class="font-display text-lg">${r.guest_name}</div>
        <span class="badge badge-reserved">${r.reservation_code}</span>
      </div>
      <div class="text-sm text-[--brand-muted] mb-1">Room ${r.room_number} · ${new Date(r.check_in_date).toLocaleDateString('en-GB',{day:'numeric',month:'short'})} → ${new Date(r.check_out_date).toLocaleDateString('en-GB',{day:'numeric',month:'short'})}</div>
      <div class="text-sm mb-4">Payment: <span class="font-medium">${r.payment_status.charAt(0).toUpperCase()+r.payment_status.slice(1)}</span></div>
      <button onclick="openCheckin(${r.id})" class="btn-brand w-full py-2 text-sm font-semibold">Check In</button>
    </div>
  `).join('');
  GMT.entrance('#pendingList > div');
  lucide.createIcons();
}

async function openCheckin(id) {
  const res = await GMT.api(`api/checkin.php?id=${id}`);
  if (!res.success) { GMT.toast(res.message, 'error'); return; }
  const r = res.data;
  selectedReservationId = id;

  document.getElementById('checkinDetail').innerHTML = `
    <div class="grid grid-cols-2 gap-4">
      <div><span class="text-[--brand-muted] text-xs">Guest</span><br><span class="font-medium">${r.guest_name}</span></div>
      <div><span class="text-[--brand-muted] text-xs">Phone</span><br><span class="font-medium">${r.guest_phone || 'N/A'}</span></div>
      <div><span class="text-[--brand-muted] text-xs">Room</span><br><span class="font-medium">${r.room_number} (${r.room_type_name})</span></div>
      <div><span class="text-[--brand-muted] text-xs">Guests</span><br><span class="font-medium">${r.number_of_guests}</span></div>
      <div><span class="text-[--brand-muted] text-xs">Check-in</span><br><span class="font-medium">${r.check_in_date}</span></div>
      <div><span class="text-[--brand-muted] text-xs">Check-out</span><br><span class="font-medium">${r.check_out_date}</span></div>
      <div><span class="text-[--brand-muted] text-xs">ID Verification</span><br><span class="font-medium">${r.id_type ? r.id_type + ': ' + r.id_number : 'Not on file'}</span></div>
      <div><span class="text-[--brand-muted] text-xs">Balance Due</span><br><span class="font-medium ${r.balance > 0 ? 'text-[--brand-danger]' : ''}">${formatMoney(r.balance)}</span></div>
    </div>
    ${r.balance > 0 ? `<div class="rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-xs px-3 py-2">This reservation has an outstanding balance. You can still check the guest in and settle payment via the Payments module.</div>` : ''}
  `;
  window.__checkinSlipData = r;
  document.getElementById('checkinNotes').value = '';
  document.getElementById('confirmCheckinBtn').classList.remove('hidden');
  document.getElementById('checkinPostActions').classList.add('hidden');
  GMT.openModal('checkinModal');
}

function formatMoney(n) { return '<?= e(setting("currency_symbol","₦")) ?>' + Number(n).toLocaleString(undefined,{minimumFractionDigits:2}); }

document.getElementById('confirmCheckinBtn').addEventListener('click', async () => {
  if (!selectedReservationId) return;
  const res = await GMT.api('api/checkin.php', {
    method: 'POST',
    body: JSON.stringify({ reservation_id: selectedReservationId, notes: document.getElementById('checkinNotes').value, csrf_token: CSRF_TOKEN }),
  });
  GMT.toast(res.message, res.success ? 'success' : 'error');
  if (res.success) {
    document.getElementById('confirmCheckinBtn').classList.add('hidden');
    document.getElementById('checkinPostActions').classList.remove('hidden');
    lucide.createIcons();
    loadPending();
  }
});

function printSlip() {
  const r = window.__checkinSlipData;
  if (!r) return;
  document.getElementById('slipContent').innerHTML = `
    <p><strong>Reservation:</strong> ${r.reservation_code}</p>
    <p><strong>Guest:</strong> ${r.guest_name}</p>
    <p><strong>Room:</strong> ${r.room_number} (${r.room_type_name})</p>
    <p><strong>Check-in Date:</strong> ${r.check_in_date} &nbsp; <strong>Check-out Date:</strong> ${r.check_out_date}</p>
    <p><strong>Number of Guests:</strong> ${r.number_of_guests}</p>
    <p><strong>Checked in:</strong> ${new Date().toLocaleString()}</p>
    <p style="margin-top:24px;">_____________________________<br>Front Desk Signature</p>
  `;
  setTimeout(() => window.print(), 150);
}

function sendCheckinWhatsApp() {
  const r = window.__checkinSlipData;
  if (!r) return;
  const message = `Hi ${r.guest_name}, welcome to <?= e(setting('hotel_name','GMT Hotel and Events Centre')) ?>! You're checked into Room ${r.room_number}. Your check-in confirmation PDF is attached. We hope you enjoy your stay.`;
  GMT.sendWhatsApp(r.guest_whatsapp, message, `api/checkin_pdf.php?reservation_id=${r.id}`);
}

document.getElementById('searchInput').addEventListener('input', GMT.debounce(() => loadPending(), 350));

lucide.createIcons();
loadPending();
</script>
</body>
</html>
