/**
 * GMT Hotel: shared front-end utilities.
 * No build step required; works directly in the packaged desktop shell.
 *
 * Animation engine: Motion (https://motion.dev), the vanilla-JS sibling of
 * Framer Motion, built by the same team on the same spring-physics engine.
 * Framer Motion itself is React-only and cannot run in this PHP/vanilla-JS
 * stack; Motion is the correct equivalent here. Loaded lazily via dynamic
 * import so a slow/offline first load never blocks the UI. Everything
 * degrades to instant show/hide if it can't fetch.
 *
 * NOTE for desktop packaging: this pulls Motion from a CDN at runtime, same
 * as Tailwind/Chart.js/Lucide elsewhere in this project. For a fully
 * offline .exe, vendor `motion.js` locally and swap the import path below.
 * See desktop/packaging-instructions.md.
 */

const GMT = (() => {
  const EASE = [0.22, 1, 0.36, 1]; // soft, expensive-feeling deceleration, matching the brand's restrained motion language

  let motionLib = null;
  const motionReady = import('https://cdn.jsdelivr.net/npm/motion@latest/+esm')
    .then((m) => { motionLib = m; return m; })
    .catch(() => null); // offline or blocked; every caller below falls back gracefully

  // Sidebar
  function initSidebar() {
    const sidebar = document.getElementById('sidebar');
    const toggleBtn = document.getElementById('sidebarToggle');
    const drawerOverlay = document.getElementById('drawerOverlay');
    if (!sidebar || !toggleBtn) return;

    toggleBtn.addEventListener('click', async () => {
      if (window.innerWidth < 1024) {
        const opening = sidebar.classList.contains('-translate-x-full');
        sidebar.classList.toggle('translate-x-0');
        sidebar.classList.toggle('-translate-x-full');
        drawerOverlay?.classList.toggle('hidden');
        await motionReady;
        if (motionLib && opening) {
          motionLib.animate(sidebar, { x: [-40, 0], opacity: [0.6, 1] }, { duration: 0.32, easing: EASE });
        }
        return;
      }

      const collapsing = sidebar.classList.contains('w-64');
      await motionReady;
      if (motionLib) {
        motionLib.animate(sidebar, { width: collapsing ? [256, 80] : [80, 256] }, { duration: 0.28, easing: EASE });
      }
      sidebar.classList.toggle('w-64');
      sidebar.classList.toggle('w-20');
      document.querySelectorAll('.sidebar-label').forEach(el => el.classList.toggle('hidden'));
    });

    drawerOverlay?.addEventListener('click', () => {
      sidebar.classList.add('-translate-x-full');
      sidebar.classList.remove('translate-x-0');
      drawerOverlay.classList.add('hidden');
    });
  }

  // Toasts
  async function toast(message, type = 'success') {
    const host = document.getElementById('toastHost');
    if (!host) return;
    const colors = {
      success: 'bg-white border-l-4 border-green-600 text-[--brand-ink]',
      error:   'bg-white border-l-4 border-red-600 text-[--brand-ink]',
      info:    'bg-white border-l-4 border-amber-600 text-[--brand-ink]',
    };
    const el = document.createElement('div');
    el.className = `${colors[type] || colors.info} rounded-md px-4 py-3 mb-2 shadow-lg text-sm`;
    el.textContent = message;
    host.appendChild(el);

    await motionReady;
    if (motionLib) {
      motionLib.animate(el, { opacity: [0, 1], x: [24, 0], scale: [0.96, 1] }, { duration: 0.32, easing: EASE });
    } else {
      el.style.opacity = '1';
    }

    setTimeout(async () => {
      await motionReady;
      if (motionLib) {
        await motionLib.animate(el, { opacity: [1, 0], x: [0, 16] }, { duration: 0.25, easing: 'ease-in' }).finished;
      }
      el.remove();
    }, 3200);
  }

  // Modal helpers
  async function openModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    el.classList.remove('hidden');
    el.classList.add('flex');
    document.body.classList.add('overflow-hidden');

    const panel = el.querySelector(':scope > div');
    await motionReady;
    if (motionLib) {
      motionLib.animate(el, { opacity: [0, 1] }, { duration: 0.2 });
      if (panel) motionLib.animate(panel, { opacity: [0, 1], scale: [0.94, 1], y: [16, 0] }, { duration: 0.34, easing: EASE });
    }
  }

  async function closeModal(id) {
    const el = document.getElementById(id);
    if (!el) return;
    const panel = el.querySelector(':scope > div');

    await motionReady;
    if (motionLib) {
      const anims = [motionLib.animate(el, { opacity: [1, 0] }, { duration: 0.18 }).finished];
      if (panel) anims.push(motionLib.animate(panel, { opacity: [1, 0], scale: [1, 0.96], y: [0, 10] }, { duration: 0.2, easing: 'ease-in' }).finished);
      await Promise.all(anims);
    }
    el.classList.add('hidden');
    el.classList.remove('flex');
    document.body.classList.remove('overflow-hidden');
  }

  // Confirm dialog (replaces window.confirm with branded modal)
  function confirmAction(message, onConfirm) {
    const modal = document.getElementById('confirmModal');
    if (!modal) { if (confirm(message)) onConfirm(); return; }
    document.getElementById('confirmModalMessage').textContent = message;
    openModal('confirmModal');
    const yesBtn = document.getElementById('confirmModalYes');
    const newYesBtn = yesBtn.cloneNode(true);
    yesBtn.parentNode.replaceChild(newYesBtn, yesBtn);
    newYesBtn.addEventListener('click', () => {
      closeModal('confirmModal');
      onConfirm();
    });
  }

  // List / card entrance (dashboard KPIs, room grid, table rows)
  // Call after rendering dynamic content: GMT.entrance('#roomGrid > *')
  async function entrance(selector, opts = {}) {
    await motionReady;
    const els = typeof selector === 'string' ? document.querySelectorAll(selector) : selector;
    if (!els || !els.length) return;
    if (!motionLib) { els.forEach(el => el.style.opacity = '1'); return; }
    motionLib.animate(
      els,
      { opacity: [0, 1], y: [14, 0] },
      { duration: 0.4, easing: EASE, delay: motionLib.stagger(0.05) }
    );
  }

  // WhatsApp send-assist
  // WhatsApp's wa.me links can only pre-fill text, not attach files. There's no
  // way to auto-attach a PDF without the paid/gated WhatsApp Business API. This
  // downloads the real PDF and opens WhatsApp with a message ready; the file
  // just needs to be attached manually in the chat that opens.
  function sendWhatsApp(phone, message, pdfUrl) {
    if (!phone) {
      toast('This guest has no phone number on file. Add one before sending via WhatsApp.', 'error');
      return;
    }
    if (pdfUrl) window.open(pdfUrl, '_blank');
    const waUrl = `https://wa.me/${phone}?text=${encodeURIComponent(message)}`;
    setTimeout(() => window.open(waUrl, '_blank'), 300);
    toast('PDF downloading. Attach it in the WhatsApp chat that just opened.', 'info');
  }

  // Debounce
  function debounce(fn, delay = 350) {
    let t;
    return (...args) => {
      clearTimeout(t);
      t = setTimeout(() => fn(...args), delay);
    };
  }

  // Fetch wrapper
  async function api(url, options = {}) {
    const res = await fetch(url, {
      headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      ...options,
    });
    let data;
    try { data = await res.json(); } catch { data = { success: false, message: 'Unexpected server response.' }; }
    if (!res.ok && !data.message) data.message = 'Something went wrong. Please try again.';
    return data;
  }

  document.addEventListener('DOMContentLoaded', initSidebar);

  return { toast, openModal, closeModal, confirmAction, entrance, debounce, api, motionReady, sendWhatsApp };
})();
