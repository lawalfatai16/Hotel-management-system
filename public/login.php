<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (Auth::user()) { header('Location: dashboard.php'); exit; }

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Session expired. Please try again.';
    } else {
        $result = Auth::attempt(trim($_POST['username'] ?? ''), $_POST['password'] ?? '', !empty($_POST['remember']));
        if ($result['ok']) {
            header('Location: dashboard.php');
            exit;
        }
        $error = $result['error'];
    }
}

$timeout = isset($_GET['timeout']);
$hotelName = setting('hotel_name', 'GMT Hotel and Events Centre');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($hotelName) ?> — Sign In</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="h-screen w-screen overflow-hidden">

  <!-- Boot / loading sequence -->
  <div id="bootScreen" class="fixed inset-0 z-50 flex flex-col items-center justify-center bg-[--brand-espresso] text-[--brand-cream] transition-opacity duration-700">
    <div class="font-display text-3xl tracking-wide mb-3">GMT</div>
    <div class="text-xs tracking-[0.35em] uppercase text-[--brand-gold] mb-8">Hotel &amp; Events Centre</div>
    <div class="w-40 h-[2px] bg-white/10 overflow-hidden rounded-full">
      <div id="bootBar" class="h-full bg-[--brand-gold]" style="width:0%; transition: width 1.1s ease;"></div>
    </div>
  </div>

  <div class="hero-rotator h-full w-full relative">
    <div class="hero-slide is-active" style="background:linear-gradient(135deg,#3a2618,#5c3a24);"></div>
    <div class="hero-slide" style="background:linear-gradient(135deg,#2b1b12,#7a4a2b);"></div>
    <div class="hero-slide" style="background:linear-gradient(135deg,#4a2e1f,#c89b5a);"></div>
    <div class="hero-slide" style="background:linear-gradient(135deg,#241812,#4a2e1f);"></div>
    <!--
      Production note: replace the gradient backgrounds above with real photography, e.g.
      style="background-image:url('assets/img/hero-1.jpg')" — exterior, room, lobby,
      restaurant, event hall, wedding setup, per the brand spec.
    -->
    <div class="hero-overlay"></div>

    <div class="relative z-10 h-full w-full flex items-center justify-between px-6 md:px-16">

      <!-- Left: brand statement -->
      <div id="brandPanel" class="hidden lg:flex flex-col text-[--brand-cream] max-w-md opacity-0">
        <div class="font-display text-5xl leading-tight mb-4"><?= e($hotelName) ?></div>
        <p class="text-[--brand-cream]/75 text-sm leading-relaxed">
          Effortless management for every reservation, event, and guest experience —
          designed for the standard GMT is known for.
        </p>
        <div class="glass-panel-dark mt-10 rounded-xl px-5 py-4 inline-flex gap-8 w-fit">
          <div>
            <div class="text-[--brand-gold] text-xl font-semibold">98%</div>
            <div class="text-xs text-[--brand-cream]/60">Guest satisfaction</div>
          </div>
          <div>
            <div class="text-[--brand-gold] text-xl font-semibold">24/7</div>
            <div class="text-xs text-[--brand-cream]/60">Front desk uptime</div>
          </div>
        </div>
      </div>

      <!-- Right: login card -->
      <div id="loginCard" class="w-full max-w-md ml-auto glass-panel rounded-2xl p-8 md:p-10 opacity-0 translate-y-3 transition-all duration-700">
        <div class="mb-8">
          <div class="font-display text-2xl text-[--brand-espresso]">Welcome back</div>
          <p class="text-sm text-[--brand-muted] mt-1">Sign in to manage <?= e($hotelName) ?></p>
        </div>

        <?php if ($timeout): ?>
          <div class="mb-4 text-sm rounded-md px-4 py-3 bg-amber-50 text-amber-800 border border-amber-200">
            Your session expired due to inactivity. Please sign in again.
          </div>
        <?php endif; ?>
        <?php if ($error): ?>
          <div class="mb-4 text-sm rounded-md px-4 py-3 bg-red-50 text-red-700 border border-red-200"><?= e($error) ?></div>
        <?php endif; ?>

        <form method="POST" class="space-y-5">
          <input type="hidden" name="csrf_token" value="<?= e(Auth::csrfToken()) ?>">

          <div>
            <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Username or email</label>
            <input type="text" name="username" required autofocus
              class="w-full rounded-lg border border-[--brand-ink]/10 bg-white/70 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold]"
              placeholder="e.g. admin">
          </div>

          <div>
            <label class="block text-xs font-medium text-[--brand-muted] mb-1.5">Password</label>
            <div class="relative">
              <input type="password" id="passwordInput" name="password" required
                class="w-full rounded-lg border border-[--brand-ink]/10 bg-white/70 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[--brand-gold] pr-12"
                placeholder="••••••••">
              <button type="button" id="togglePassword" class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-[--brand-muted] hover:text-[--brand-cognac]">Show</button>
            </div>
          </div>

          <div class="flex items-center justify-between text-sm">
            <label class="flex items-center gap-2 text-[--brand-muted]">
              <input type="checkbox" name="remember" class="rounded border-[--brand-ink]/20">
              Remember me
            </label>
            <a href="#" class="text-[--brand-cognac] hover:underline">Forgot password?</a>
          </div>

          <button type="submit" class="btn-brand w-full py-3 text-sm font-semibold">Sign In</button>
        </form>

        <div class="mt-6 flex items-center gap-2 text-xs text-[--brand-muted]">
          <span class="w-2 h-2 rounded-full bg-green-500 inline-block"></span>
          System status: All services operational
        </div>
      </div>
    </div>
  </div>

<script src="assets/js/app.js"></script>
<script>
  // Boot sequence — Motion drives the handoff from boot screen to login card
  window.addEventListener('load', async () => {
    const bar = document.getElementById('bootBar');
    requestAnimationFrame(() => bar.style.width = '100%');

    setTimeout(async () => {
      const boot = document.getElementById('bootScreen');
      const card = document.getElementById('loginCard');
      const brandPanel = document.getElementById('brandPanel');
      const motion = await import('https://cdn.jsdelivr.net/npm/motion@latest/+esm').catch(() => null);

      if (motion) {
        await motion.animate(boot, { opacity: [1, 0] }, { duration: 0.5, easing: 'ease-in' }).finished;
        boot.remove();
        card.classList.remove('opacity-0', 'translate-y-3');
        motion.animate(card, { opacity: [0, 1], y: [24, 0], scale: [0.97, 1] }, { duration: 0.55, easing: [0.22, 1, 0.36, 1] });
        if (brandPanel) motion.animate(brandPanel, { opacity: [0, 1], x: [-16, 0] }, { duration: 0.6, delay: 0.15, easing: [0.22, 1, 0.36, 1] });
      } else {
        boot.style.opacity = '0';
        setTimeout(() => boot.remove(), 700);
        card.classList.remove('opacity-0', 'translate-y-3');
      }
    }, 1200);
  });

  // Rotating hero
  const slides = document.querySelectorAll('.hero-slide');
  let current = 0;
  setInterval(() => {
    slides[current].classList.remove('is-active');
    current = (current + 1) % slides.length;
    slides[current].classList.add('is-active');
  }, 7000);

  // Show/hide password
  document.getElementById('togglePassword').addEventListener('click', function () {
    const input = document.getElementById('passwordInput');
    const show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    this.textContent = show ? 'Hide' : 'Show';
  });
</script>
</body>
</html>
