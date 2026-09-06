<?php
$currentUser = Auth::user();
$hotelName = setting('hotel_name', 'GMT Hotel and Events Centre');
?>
<header class="sticky top-0 z-20 flex items-center justify-between px-4 md:px-8 py-4 bg-[--brand-cream]/85 backdrop-blur border-b border-[--brand-ink]/5">
  <div class="flex items-center gap-4">
    <button id="sidebarToggle" class="neu-icon-btn p-2">
      <i data-lucide="menu" class="w-5 h-5 text-[--brand-ink]"></i>
    </button>
    <div>
      <div class="text-sm font-semibold"><?= e($pageTitle ?? 'Dashboard') ?></div>
      <div class="text-xs text-[--brand-muted]" id="clockDisplay"></div>
    </div>
  </div>

  <div class="flex items-center gap-4">
    <div class="relative">
      <button id="notifBell" onclick="toggleNotifDropdown()" class="neu-icon-btn relative p-2">
        <i data-lucide="bell" class="w-5 h-5 text-[--brand-ink]"></i>
        <span id="notifBadge" class="hidden absolute -top-0.5 -right-0.5 min-w-[16px] h-4 px-1 rounded-full bg-[--brand-cognac] text-white text-[10px] flex items-center justify-center"></span>
      </button>
      <div id="notifDropdown" class="hidden absolute right-0 mt-2 w-80 max-h-96 overflow-y-auto bg-white rounded-xl shadow-lg border border-[--brand-ink]/10 z-30">
        <div class="flex items-center justify-between px-4 py-3 border-b border-[--brand-ink]/5">
          <div class="text-sm font-medium">Notifications</div>
          <button onclick="markAllNotifsRead()" class="text-xs text-[--brand-cognac] hover:underline">Mark all read</button>
        </div>
        <div id="notifList" class="divide-y divide-[--brand-ink]/5"></div>
        <div id="notifEmpty" class="hidden px-4 py-8 text-center text-xs text-[--brand-muted]">You're all caught up.</div>
      </div>
    </div>
    <div class="w-px h-6 bg-[--brand-ink]/10"></div>
    <div class="flex items-center gap-2">
      <div class="w-8 h-8 rounded-full bg-[--brand-coffee] text-[--brand-cream] flex items-center justify-center text-xs font-semibold">
        <?= e(strtoupper(substr($currentUser['username'] ?? 'U', 0, 1))) ?>
      </div>
      <div class="hidden md:block text-sm">
        <div class="font-medium leading-none"><?= e($currentUser['username'] ?? '') ?></div>
        <div class="text-xs text-[--brand-muted] mt-0.5"><?= e($currentUser['role'] ?? '') ?></div>
      </div>
    </div>
  </div>
</header>
<script>
  function updateClock() {
    const el = document.getElementById('clockDisplay');
    if (!el) return;
    el.textContent = new Date().toLocaleString('en-NG', { weekday: 'short', day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
  }
  updateClock();
  setInterval(updateClock, 30000);

  const NOTIF_CSRF = <?= json_encode(Auth::csrfToken()) ?>;

  function toggleNotifDropdown() {
    document.getElementById('notifDropdown').classList.toggle('hidden');
  }
  document.addEventListener('click', (e) => {
    if (!e.target.closest('#notifBell') && !e.target.closest('#notifDropdown')) {
      document.getElementById('notifDropdown')?.classList.add('hidden');
    }
  });

  const notifIcon = { checkin:'log-in', checkout:'log-out', payment:'credit-card', maintenance:'wrench', event:'party-popper' };

  async function loadNotifications() {
    if (typeof GMT === 'undefined') return;
    const res = await GMT.api('api/notifications.php');
    if (!res.success) return;
    const badge = document.getElementById('notifBadge');
    if (res.unread_count > 0) { badge.textContent = res.unread_count; badge.classList.remove('hidden'); }
    else badge.classList.add('hidden');

    const list = document.getElementById('notifList');
    document.getElementById('notifEmpty').classList.toggle('hidden', res.data.length > 0);
    list.innerHTML = res.data.map(n => `
      <div class="px-4 py-3 flex gap-3 ${!n.is_read ? 'bg-[--brand-cream-2]/60' : ''}">
        <div class="w-8 h-8 rounded-lg bg-[--brand-cream-2] flex items-center justify-center shrink-0">
          <i data-lucide="${notifIcon[n.type] || 'bell'}" class="w-4 h-4 text-[--brand-cognac]"></i>
        </div>
        <div class="flex-1 min-w-0">
          <div class="text-sm font-medium">${n.title}</div>
          <div class="text-xs text-[--brand-muted]">${n.message}</div>
        </div>
        ${n.source === 'stored' && !n.is_read ? `<button onclick="markNotifRead(${n.id})" class="text-[10px] text-[--brand-cognac] shrink-0">Mark read</button>` : ''}
      </div>
    `).join('');
    lucide.createIcons();
  }

  async function markNotifRead(id) {
    await GMT.api('api/notifications.php', { method: 'PUT', body: JSON.stringify({ id, csrf_token: NOTIF_CSRF }) });
    loadNotifications();
  }
  async function markAllNotifsRead() {
    await GMT.api('api/notifications.php', { method: 'PUT', body: JSON.stringify({ mark_all: true, csrf_token: NOTIF_CSRF }) });
    loadNotifications();
  }

  document.addEventListener('DOMContentLoaded', () => {
    loadNotifications();
    setInterval(loadNotifications, 60000);
  });
</script>
