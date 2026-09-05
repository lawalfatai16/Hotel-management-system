<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/report_queries.php';

Auth::requireModuleAccess('reports');
$pageTitle = 'Reports';
$activeNav = 'reports';
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
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<link rel="stylesheet" href="assets/css/style.css">
<style>
  @media print {
    #sidebar, header, .no-print { display: none !important; }
    main { padding: 0 !important; }
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
        <div class="font-display text-xl">Reports Centre</div>
        <div class="text-sm text-[--brand-muted]">Every report the hotel needs, with Print, CSV, and Excel export</div>
      </div>

      <div class="card-surface p-4 flex flex-wrap gap-3 items-center no-print">
        <select id="reportType" class="text-sm rounded-lg border border-[--brand-ink]/10 px-3 py-2 min-w-[220px]">
          <?php foreach (REPORT_TYPES as $key => $label): ?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?>
        </select>
        <input type="date" id="fromDate" class="text-sm rounded-lg border border-[--brand-ink]/10 px-3 py-2">
        <span class="text-xs text-[--brand-muted]">to</span>
        <input type="date" id="toDate" class="text-sm rounded-lg border border-[--brand-ink]/10 px-3 py-2">
        <button onclick="loadReport()" class="btn-brand px-5 py-2 text-sm font-semibold">Generate</button>
        <div class="ml-auto flex gap-2">
          <button onclick="exportReport('csv')" class="px-4 py-2 text-sm rounded-lg border border-[--brand-ink]/10 hover:bg-black/5 flex items-center gap-2"><i data-lucide="download" class="w-4 h-4"></i> CSV</button>
          <button onclick="exportReport('xls')" class="px-4 py-2 text-sm rounded-lg border border-[--brand-ink]/10 hover:bg-black/5 flex items-center gap-2"><i data-lucide="file-spreadsheet" class="w-4 h-4"></i> Excel</button>
          <button onclick="window.print()" class="px-4 py-2 text-sm rounded-lg border border-[--brand-ink]/10 hover:bg-black/5 flex items-center gap-2"><i data-lucide="printer" class="w-4 h-4"></i> Print</button>
        </div>
      </div>

      <div class="font-display text-lg" id="reportTitle"></div>

      <div id="totalsRow" class="grid grid-cols-2 lg:grid-cols-4 gap-3"></div>

      <div id="chartWrap" class="card-surface p-6 hidden no-print">
        <canvas id="reportChart" height="90"></canvas>
      </div>

      <div class="card-surface overflow-hidden">
        <div class="table-scroll">
          <table class="w-full text-sm">
            <thead class="bg-[--brand-cream-2] text-left text-xs uppercase tracking-wide text-[--brand-muted]"><tr id="tableHead"></tr></thead>
            <tbody id="tableBody" class="divide-y divide-[--brand-ink]/5"></tbody>
          </table>
        </div>
        <div id="emptyState" class="hidden text-center py-16">
          <i data-lucide="bar-chart-3" class="w-10 h-10 mx-auto text-[--brand-muted] mb-3"></i>
          <div class="font-medium">No data in this range</div>
        </div>
      </div>

    </main>
  </div>
</div>

<script src="assets/js/app.js"></script>
<script>
let currentChart = null;
let currentType = 'revenue';

function formatCell(value, type) {
  if (value === null || value === undefined) return '';
  if (type === 'currency') return '<?= e(setting("currency_symbol","₦")) ?>' + Number(value).toLocaleString(undefined,{minimumFractionDigits:2});
  if (type === 'date') return new Date(value).toLocaleDateString('en-GB',{day:'numeric',month:'short',year:'numeric'});
  if (type === 'datetime') return new Date(value).toLocaleString('en-GB',{day:'numeric',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'});
  return value;
}

async function loadReport() {
  currentType = document.getElementById('reportType').value;
  const params = new URLSearchParams({ type: currentType, from: document.getElementById('fromDate').value, to: document.getElementById('toDate').value });
  const res = await GMT.api(`api/reports.php?${params.toString()}`);
  if (!res.success) { GMT.toast(res.message || 'Unable to generate report.', 'error'); return; }

  document.getElementById('reportTitle').textContent = res.title;

  document.getElementById('totalsRow').innerHTML = Object.entries(res.totals || {}).map(([label, value]) => `
    <div class="kpi-card p-4">
      <div class="text-lg font-semibold">${typeof value === 'number' ? (label.toLowerCase().includes('revenue') || label.toLowerCase().includes('total') || label.toLowerCase().includes('outstanding') || label.toLowerCase().includes('sales') || label.toLowerCase().includes('value') || label.toLowerCase().includes('expenses') || label.toLowerCase().includes('collected') ? formatCell(value,'currency') : value) : value}</div>
      <div class="text-xs text-[--brand-muted] mt-1">${label}</div>
    </div>
  `).join('');
  GMT.entrance('#totalsRow > div');

  document.getElementById('tableHead').innerHTML = res.columns.map(c => `<th class="px-5 py-3">${c.label}</th>`).join('');
  document.getElementById('emptyState').classList.toggle('hidden', res.rows.length > 0);
  document.getElementById('tableBody').innerHTML = res.rows.map(row => `
    <tr>${res.columns.map(c => `<td class="px-5 py-3">${formatCell(row[c.key], c.type)}</td>`).join('')}</tr>
  `).join('');
  GMT.entrance('#tableBody tr');

  const chartWrap = document.getElementById('chartWrap');
  if (res.chart && res.rows.length > 0) {
    chartWrap.classList.remove('hidden');
    if (currentChart) currentChart.destroy();
    currentChart = new Chart(document.getElementById('reportChart'), {
      type: res.chart.type,
      data: { labels: res.chart.labels, datasets: [{ data: res.chart.values, backgroundColor: ['#7A4A2B','#C89B5A','#4A2E1F','#A33B2E','#B4813C','#8A7A6C','#356487','#3C6A3F'], borderColor: '#7A4A2B', fill: res.chart.type === 'line' }] },
      options: { plugins: { legend: { display: res.chart.type === 'doughnut', position: 'bottom' } } },
    });
  } else {
    chartWrap.classList.add('hidden');
  }
}

function exportReport(format) {
  const params = new URLSearchParams({ type: currentType, from: document.getElementById('fromDate').value, to: document.getElementById('toDate').value, format });
  window.location.href = `api/reports_export.php?${params.toString()}`;
}

document.getElementById('toDate').value = new Date().toISOString().split('T')[0];
document.getElementById('fromDate').value = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];

lucide.createIcons();
loadReport();
</script>
</body>
</html>
