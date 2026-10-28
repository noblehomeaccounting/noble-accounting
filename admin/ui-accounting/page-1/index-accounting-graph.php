<?php
// index-accounting-graph.php
// Accounting: dami ng budget request bawat requestor — buong taon (bawat buwan) o isang buwan (bawat araw)

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_ACCOUNTING];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$currentYear  = (int) date('Y');
$currentMonth = (int) date('n');

$selectedYear  = isset($_GET['year'])  ? (int) $_GET['year']  : $currentYear;
$selectedMonth = isset($_GET['month']) ? (int) $_GET['month'] : 0;
if ($selectedMonth < 0 || $selectedMonth > 12) $selectedMonth = 0;
if ($selectedYear < 2000 || $selectedYear > 2100) $selectedYear = $currentYear;

$yearsResult = $conn->query("SELECT DISTINCT YEAR(date_requested) AS yr FROM noblebudgetrequest WHERE date_requested IS NOT NULL ORDER BY yr DESC");
$availableYears = [];
while ($row = $yearsResult->fetch_assoc()) {
    if ($row['yr'] !== null) $availableYears[] = (int) $row['yr'];
}
if (!in_array($selectedYear, $availableYears, true)) $availableYears[] = $selectedYear;
rsort($availableYears);

$monthNames = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];
$monthShort = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

if ($selectedMonth > 0) {
    $sql = "SELECT requestor_name, DAY(date_requested) AS bucket, COUNT(*) AS request_count
            FROM noblebudgetrequest
            WHERE YEAR(date_requested) = ? AND MONTH(date_requested) = ?
            GROUP BY requestor_name, DAY(date_requested)
            ORDER BY requestor_name, bucket";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ii', $selectedYear, $selectedMonth);
} else {
    $sql = "SELECT requestor_name, MONTH(date_requested) AS bucket, COUNT(*) AS request_count
            FROM noblebudgetrequest
            WHERE YEAR(date_requested) = ?
            GROUP BY requestor_name, MONTH(date_requested)
            ORDER BY requestor_name, bucket";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $selectedYear);
}
$stmt->execute();
$result = $stmt->get_result();

if ($selectedMonth > 0) {
    $bucketCount = cal_days_in_month(CAL_GREGORIAN, $selectedMonth, $selectedYear);
    $labels = array_map(fn($d) => (string) $d, range(1, $bucketCount));
} else {
    $bucketCount = 12;
    $labels = $monthShort;
}

$chartData = [];
while ($row = $result->fetch_assoc()) {
    $name = trim((string) ($row['requestor_name'] ?? '')) ?: 'Unknown';
    $idx = (int) $row['bucket'] - 1;
    if ($idx < 0 || $idx >= $bucketCount) continue;
    if (!isset($chartData[$name])) $chartData[$name] = array_fill(0, $bucketCount, 0);
    $chartData[$name][$idx] += (int) $row['request_count'];
}
$stmt->close();

// Pinakamarami muna (ito rin ang pagkakasunod ng kulay)
uasort($chartData, fn($a, $b) => array_sum($b) <=> array_sum($a));

$totalRequests = 0;
$bucketTotals = array_fill(0, $bucketCount, 0);
$users = [];
foreach ($chartData as $name => $counts) {
    $t = array_sum($counts);
    $totalRequests += $t;
    foreach ($counts as $k => $v) $bucketTotals[$k] += $v;
    $users[] = ['name' => (string) $name, 'counts' => array_values($counts), 'total' => $t];
}
$totalUsers = count($users);
$topUser = $users[0]['name'] ?? '';
$topUserCount = $users[0]['total'] ?? 0;
$avgPerUser = $totalUsers ? $totalRequests / $totalUsers : 0;

// Pinaka-abalang buwan / araw
$peakIdx = $totalRequests ? array_search(max($bucketTotals), $bucketTotals, true) : null;
$peakLabel = $peakIdx === null ? '—'
    : ($selectedMonth > 0 ? $monthShort[$selectedMonth - 1] . ' ' . ($peakIdx + 1) : $monthNames[$peakIdx + 1]);
$peakCount = $peakIdx === null ? 0 : $bucketTotals[$peakIdx];

$periodLabel = $selectedMonth > 0 ? $monthNames[$selectedMonth] . ' ' . $selectedYear : 'Year ' . $selectedYear;
$unitWord = $selectedMonth > 0 ? 'day' : 'month';

// Prev / next na period (para sa arrows)
if ($selectedMonth > 0) {
    $prev = $selectedMonth === 1 ? ['year' => $selectedYear - 1, 'month' => 12] : ['year' => $selectedYear, 'month' => $selectedMonth - 1];
    $next = $selectedMonth === 12 ? ['year' => $selectedYear + 1, 'month' => 1] : ['year' => $selectedYear, 'month' => $selectedMonth + 1];
} else {
    $prev = ['year' => $selectedYear - 1, 'month' => 0];
    $next = ['year' => $selectedYear + 1, 'month' => 0];
}
$isFuture = fn($p) => $p['year'] > $currentYear || ($p['year'] === $currentYear && $p['month'] > $currentMonth);
$url = fn($y, $m) => '?' . http_build_query(['year' => $y, 'month' => $m]);

// Highlight ng kasalukuyang buwan / araw sa chart
$todayIdx = null;
if ($selectedYear === $currentYear) {
    if ($selectedMonth === 0) $todayIdx = $currentMonth - 1;
    elseif ($selectedMonth === $currentMonth) $todayIdx = (int) date('j') - 1;
}

$payload = json_encode([
    'labels' => $labels,
    'users' => $users,
    'monthMode' => $selectedMonth > 0,
    'monthShort' => $selectedMonth > 0 ? $monthShort[$selectedMonth - 1] : null,
    'year' => $selectedYear,
    'period' => $periodLabel,
    'todayIdx' => $todayIdx,
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

$e = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Request Graph</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>

<body class="bg-slate-100">
    <main id="main-content" class="md:ml-56 pt-20 md:pt-5 min-h-screen p-4 md:p-8 transition-all duration-300">
        <div class="max-w-7xl mx-auto min-w-0">

            <!-- ═══ HEADER ═══ -->
            <section class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden mb-4">
                <div class="bg-[repeating-linear-gradient(-45deg,#f59e0b_0_10px,#1f2937_10px_20px)] h-1.5"></div>
                <div class="p-5 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-chart-column text-lg"></i></span>
                        <div class="min-w-0">
                            <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase">Accounting · Reports</p>
                            <h1 class="text-gray-900 text-lg font-semibold leading-tight">Budget Requests</h1>
                            <p class="text-gray-400 text-[11px] mt-0.5">
                                <?= $selectedMonth > 0
                                    ? 'Requests per requestor, per day — ' . $e($periodLabel)
                                    : 'Requests per requestor, per month — ' . $selectedYear ?>
                            </p>
                        </div>
                    </div>

                    <!-- Period: prev · year · next -->
                    <div class="flex items-center gap-1.5">
                        <a href="<?= $e($url($prev['year'], $prev['month'])) ?>" aria-label="Previous period"
                            class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-gray-200 bg-white text-gray-500 hover:text-amber-700 hover:border-amber-300"><i class="fa-solid fa-chevron-left text-xs"></i></a>
                        <form method="GET" class="relative">
                            <input type="hidden" name="month" value="<?= $selectedMonth ?>">
                            <select name="year" onchange="this.form.submit()" aria-label="Year"
                                class="h-9 pl-3 pr-8 text-sm font-semibold text-gray-800 bg-white border border-gray-200 rounded-lg outline-none appearance-none cursor-pointer focus:border-amber-600 focus:ring-2 focus:ring-amber-100">
                                <?php foreach ($availableYears as $yr): ?>
                                    <option value="<?= $yr ?>" <?= $yr === $selectedYear ? 'selected' : '' ?>><?= $yr ?></option>
                                <?php endforeach; ?>
                            </select>
                            <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-[9px] text-gray-400 pointer-events-none"></i>
                        </form>
                        <?php if ($isFuture($next)): ?>
                            <span class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-gray-100 bg-gray-50 text-gray-300 cursor-not-allowed" aria-hidden="true"><i class="fa-solid fa-chevron-right text-xs"></i></span>
                        <?php else: ?>
                            <a href="<?= $e($url($next['year'], $next['month'])) ?>" aria-label="Next period"
                                class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-gray-200 bg-white text-gray-500 hover:text-amber-700 hover:border-amber-300"><i class="fa-solid fa-chevron-right text-xs"></i></a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Month pills (sariling horizontal scroll sa maliit na screen) -->
                <nav class="px-5 py-3 border-t border-gray-100 bg-gray-50/60 overflow-x-auto [scrollbar-width:thin]" aria-label="Month">
                    <div class="flex items-center gap-1.5 w-max">
                        <?php
                        $pill = 'inline-flex items-center h-8 px-3 rounded-lg text-xs font-semibold whitespace-nowrap transition';
                        $on = 'bg-amber-700 text-white shadow-sm';
                        $off = 'bg-white text-gray-600 border border-gray-200 hover:border-amber-300 hover:text-amber-800';
                        ?>
                        <a href="<?= $e($url($selectedYear, 0)) ?>" class="<?= $pill ?> <?= $selectedMonth === 0 ? $on : $off ?>"><i class="fa-regular fa-calendar mr-1.5"></i>Whole year</a>
                        <span class="w-px h-5 bg-gray-200 mx-1"></span>
                        <?php foreach ($monthShort as $i => $m):
                            $num = $i + 1;
                            $future = $selectedYear > $currentYear || ($selectedYear === $currentYear && $num > $currentMonth); ?>
                            <?php if ($future && $num !== $selectedMonth): ?>
                                <span class="<?= $pill ?> text-gray-300 border border-dashed border-gray-200 cursor-not-allowed"><?= $m ?></span>
                            <?php else: ?>
                                <a href="<?= $e($url($selectedYear, $num)) ?>" class="<?= $pill ?> <?= $num === $selectedMonth ? $on : $off ?>"><?= $m ?></a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </nav>
            </section>

            <!-- ═══ STAT TILES ═══ -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                <div class="bg-white border border-gray-200 rounded-xl px-4 py-3.5 min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400"><i class="fa-solid fa-file-invoice-dollar mr-1 text-amber-700"></i>Total requests</p>
                    <p class="text-2xl font-bold text-gray-900 tabular-nums leading-tight mt-1"><?= number_format($totalRequests) ?></p>
                    <p class="text-[11px] text-gray-400 truncate"><?= $e($periodLabel) ?></p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl px-4 py-3.5 min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400"><i class="fa-solid fa-users mr-1 text-amber-700"></i>Requestors</p>
                    <p class="text-2xl font-bold text-gray-900 tabular-nums leading-tight mt-1"><?= $totalUsers ?></p>
                    <p class="text-[11px] text-gray-400 truncate"><?= $totalUsers ? 'avg ' . number_format($avgPerUser, 1) . ' requests each' : 'No requests yet' ?></p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl px-4 py-3.5 min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400"><i class="fa-solid fa-trophy mr-1 text-amber-700"></i>Top requestor</p>
                    <p class="text-[15px] font-bold text-gray-900 leading-tight mt-1.5 truncate" title="<?= $e($topUser) ?>"><?= $topUser !== '' ? $e($topUser) : '—' ?></p>
                    <p class="text-[11px] text-gray-400 truncate">
                        <?= $topUserCount ?> request<?= $topUserCount === 1 ? '' : 's' ?>
                        <?php if ($totalRequests): ?> · <?= round($topUserCount / $totalRequests * 100) ?>% of total<?php endif; ?>
                    </p>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl px-4 py-3.5 min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400"><i class="fa-solid fa-fire mr-1 text-amber-700"></i>Busiest <?= $unitWord ?></p>
                    <p class="text-[15px] font-bold text-gray-900 leading-tight mt-1.5 truncate"><?= $e($peakLabel) ?></p>
                    <p class="text-[11px] text-gray-400 truncate"><?= $peakCount ?> request<?= $peakCount === 1 ? '' : 's' ?></p>
                </div>
            </div>

            <?php if (!$users): ?>
                <!-- ═══ EMPTY ═══ -->
                <div class="bg-white border-2 border-dashed border-gray-200 rounded-2xl px-6 py-16 text-center">
                    <div class="w-14 h-14 mx-auto rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center mb-3"><i class="fa-solid fa-chart-column text-xl"></i></div>
                    <p class="text-sm font-semibold text-gray-700">No budget requests in <?= $e($periodLabel) ?></p>
                    <p class="text-xs text-gray-400 mt-1">Pick another month or year above.</p>
                </div>
            <?php else: ?>

                <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_300px] gap-4 items-start">

                    <!-- ═══ CHART / TABLE ═══ -->
                    <section class="bg-white border border-gray-200 rounded-2xl shadow-sm min-w-0">
                        <div class="px-5 py-3.5 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="text-sm font-semibold text-gray-900">Requests per <?= $unitWord ?></h2>
                                <p id="agShowingNote" class="text-[11px] text-gray-400"></p>
                            </div>
                            <div class="flex items-center gap-2">
                                <div class="inline-flex p-0.5 rounded-lg bg-gray-100 border border-gray-200" role="tablist">
                                    <button type="button" data-view="chart" class="ag-view inline-flex items-center gap-1.5 h-7 px-3 rounded-md text-xs font-semibold"><i class="fa-solid fa-chart-column text-[10px]"></i>Chart</button>
                                    <button type="button" data-view="table" class="ag-view inline-flex items-center gap-1.5 h-7 px-3 rounded-md text-xs font-semibold"><i class="fa-solid fa-table text-[10px]"></i>Table</button>
                                </div>
                                <button type="button" onclick="agDownloadCsv()" title="Download CSV"
                                    class="inline-flex items-center gap-1.5 h-8 px-3 rounded-lg text-xs font-semibold text-gray-600 bg-white border border-gray-200 hover:border-amber-300 hover:text-amber-800"><i class="fa-solid fa-download text-[10px]"></i><span class="hidden sm:inline">CSV</span></button>
                            </div>
                        </div>

                        <!-- Chart: sariling horizontal scroll sa cellphone para hindi siksik ang bars -->
                        <div id="agChartView" class="p-4 sm:p-5">
                            <div class="overflow-x-auto [scrollbar-width:thin] -mx-1 px-1">
                                <div class="relative h-[340px]" style="min-width: <?= $selectedMonth > 0 ? max(560, $bucketCount * 18) : 420 ?>px">
                                    <canvas id="agChart" aria-label="Budget requests per <?= $unitWord ?>, stacked by requestor" role="img"></canvas>
                                </div>
                            </div>
                            <!-- Legend (laging nakikita; kulay + pangalan) -->
                            <div id="agLegend" class="mt-3 flex flex-wrap gap-x-4 gap-y-1.5"></div>
                        </div>

                        <!-- Table -->
                        <div id="agTableView" class="hidden">
                            <div class="overflow-auto max-h-[460px] [scrollbar-width:thin]">
                                <table class="min-w-full text-xs tabular-nums">
                                    <thead id="agTableHead" class="sticky top-0 z-10 bg-gray-50 text-gray-500"></thead>
                                    <tbody id="agTableBody" class="divide-y divide-gray-100"></tbody>
                                    <tfoot id="agTableFoot" class="sticky bottom-0 bg-amber-50 text-amber-900 font-semibold"></tfoot>
                                </table>
                            </div>
                        </div>
                    </section>

                    <!-- ═══ REQUESTORS ═══ -->
                    <aside class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden min-w-0">
                        <div class="px-4 py-3.5 border-b border-gray-100">
                            <div class="flex items-center justify-between gap-2">
                                <h2 class="text-sm font-semibold text-gray-900">Requestors <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[10px] font-bold"><?= $totalUsers ?></span></h2>
                                <div class="flex items-center gap-1 text-[11px] font-semibold">
                                    <button type="button" onclick="agSetAll(true)" class="px-1.5 py-0.5 rounded text-gray-500 hover:text-amber-700 hover:bg-amber-50">All</button>
                                    <span class="text-gray-200">|</span>
                                    <button type="button" onclick="agSetAll(false)" class="px-1.5 py-0.5 rounded text-gray-500 hover:text-amber-700 hover:bg-amber-50">None</button>
                                </div>
                            </div>
                            <p class="text-[11px] text-gray-400 mt-0.5">Tap a name to show or hide it in the chart.</p>
                            <?php if ($totalUsers > 6): ?>
                                <div class="relative mt-2.5">
                                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[11px]"></i>
                                    <input id="agSearch" type="text" placeholder="Search requestor…" autocomplete="off"
                                        class="w-full h-8 bg-white border border-gray-200 rounded-lg pl-8 pr-3 text-xs outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-100">
                                </div>
                            <?php endif; ?>
                        </div>
                        <ul id="agUserList" class="divide-y divide-gray-50 max-h-[420px] overflow-y-auto overscroll-contain [scrollbar-width:thin]"></ul>
                    </aside>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php if ($users): ?>
        <script>
            (() => {
                const DATA = <?= $payload ?>;

                // Kulay: fixed na pagkakasunod (pinakamaraming request = slot 1). Lampas sa 7 → "Others" (gray).
                const SERIES = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#6250d6'];
                const OTHER = '#9ca3af';
                const MAX_COLORED = SERIES.length;

                const users = DATA.users.map((u, i) => ({ ...u, color: i < MAX_COLORED ? SERIES[i] : OTHER, own: i < MAX_COLORED, on: true }));
                const grandTotal = users.reduce((s, u) => s + u.total, 0);
                const plural = n => `${n} request${n === 1 ? '' : 's'}`;
                const bucketName = i => DATA.monthMode ? `${DATA.monthShort} ${DATA.labels[i]}, ${DATA.year}` : `${DATA.labels[i]} ${DATA.year}`;

                const el = id => document.getElementById(id);
                const make = (tag, cls, text) => { const n = document.createElement(tag); if (cls) n.className = cls; if (text != null) n.textContent = text; return n; };

                // ── Datasets (binubuo ulit kapag may tinoggle) ──
                function buildDatasets() {
                    const sets = users.filter(u => u.own && u.on).map(u => ({ label: u.name, data: u.counts, backgroundColor: u.color }));
                    const rest = users.filter(u => !u.own && u.on);
                    if (rest.length) {
                        const sum = DATA.labels.map((_, i) => rest.reduce((s, u) => s + u.counts[i], 0));
                        sets.push({ label: `Others (${rest.length})`, data: sum, backgroundColor: OTHER, others: rest });
                    }
                    // 2px puting gap sa pagitan ng mga segment; bilugan lang ang pinakataas
                    return sets.map((d, idx) => ({
                        ...d,
                        borderColor: '#ffffff',
                        borderWidth: { top: 2, bottom: 0, left: 0, right: 0 },
                        borderSkipped: 'start',
                        borderRadius: idx === sets.length - 1 ? { topLeft: 4, topRight: 4 } : 0,
                        maxBarThickness: DATA.monthMode ? 18 : 40,
                    }));
                }

                // Kasalukuyang buwan/araw: bahagyang highlight sa likod ng bar
                const todayBand = {
                    id: 'agToday',
                    beforeDatasetsDraw(chart) {
                        if (DATA.todayIdx == null) return;
                        const x = chart.scales.x, { top, bottom } = chart.chartArea;
                        const w = x.getPixelForValue(1) - x.getPixelForValue(0);
                        const cx = x.getPixelForValue(DATA.todayIdx);
                        const c = chart.ctx; c.save(); c.fillStyle = 'rgba(245,158,11,0.08)';
                        c.fillRect(cx - w / 2, top, w, bottom - top); c.restore();
                    }
                };

                // Walang request sa buwan/araw na iyon → huwag magpakita ng (walang lamang) tooltip
                const hideEmptyTooltip = {
                    id: 'agHideEmptyTooltip',
                    beforeTooltipDraw: (chart, args) => args.tooltip.dataPoints.length > 0,
                };

                const chart = new Chart(el('agChart'), {
                    type: 'bar',
                    data: { labels: DATA.labels, datasets: buildDatasets() },
                    plugins: [todayBand, hideEmptyTooltip],
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: { duration: 250 },
                        interaction: { mode: 'index', intersect: false },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#111827',
                                padding: 10,
                                boxPadding: 4,
                                usePointStyle: true,
                                titleFont: { size: 12, weight: '600' },
                                bodyFont: { size: 11 },
                                footerFont: { size: 11, weight: '600' },
                                filter: item => item.parsed.y > 0,
                                itemSort: (a, b) => b.parsed.y - a.parsed.y,
                                callbacks: {
                                    title: items => items.length ? bucketName(items[0].dataIndex) : '',
                                    label: c => ` ${c.dataset.label}: ${plural(c.parsed.y)}`,
                                    footer: items => {
                                        const t = items.reduce((s, i) => s + i.parsed.y, 0);
                                        return t ? `Total: ${plural(t)}` : 'No requests';
                                    },
                                },
                            },
                        },
                        scales: {
                            x: {
                                stacked: true,
                                grid: { display: false },
                                border: { color: '#e5e7eb' },
                                ticks: {
                                    font: { size: 10 },
                                    autoSkip: false,
                                    color: ctx => ctx.index === DATA.todayIdx ? '#b45309' : '#6b7280',
                                },
                            },
                            y: {
                                stacked: true,
                                beginAtZero: true,
                                border: { display: false },
                                grid: { color: '#f1f5f9' },
                                ticks: { precision: 0, font: { size: 10 }, color: '#9ca3af' },
                            },
                        },
                    },
                });

                // ── Legend sa ilalim ng chart ──
                function renderLegend() {
                    const wrap = el('agLegend'); wrap.innerHTML = '';
                    chart.data.datasets.forEach(d => {
                        const item = make('span', 'inline-flex items-center gap-1.5 text-[11px] text-gray-600 max-w-[180px]');
                        const dot = make('span', 'w-2.5 h-2.5 rounded-sm shrink-0'); dot.style.background = d.backgroundColor;
                        item.append(dot, make('span', 'truncate', d.label));
                        wrap.appendChild(item);
                    });
                    if (!chart.data.datasets.length) wrap.appendChild(make('span', 'text-[11px] text-gray-400', 'All requestors are hidden. Tap a name on the list to show it.'));
                }

                // ── Listahan ng requestors (kanan) ──
                function renderList() {
                    const ul = el('agUserList'); ul.innerHTML = '';
                    const q = (el('agSearch')?.value || '').trim().toLowerCase();
                    const max = Math.max(...users.map(u => u.total), 1);
                    let shown = 0;
                    users.forEach((u, i) => {
                        if (q && !u.name.toLowerCase().includes(q)) return;
                        shown++;
                        const li = make('li');
                        const btn = make('button', `w-full text-left flex items-center gap-3 px-4 py-2.5 transition hover:bg-gray-50 ${u.on ? '' : 'opacity-45'}`);
                        btn.type = 'button';
                        btn.setAttribute('aria-pressed', u.on ? 'true' : 'false');
                        btn.title = u.on ? 'Hide from chart' : 'Show in chart';

                        const rank = make('span', 'w-5 text-[10px] font-bold text-gray-300 tabular-nums text-right shrink-0', String(i + 1));
                        const sw = make('span', 'w-3 h-3 rounded-sm shrink-0 ring-1 ring-black/5'); sw.style.background = u.on ? u.color : 'transparent';
                        if (!u.on) sw.className += ' border-2 border-gray-300';

                        const body = make('span', 'flex-1 min-w-0');
                        const top = make('span', 'flex items-baseline justify-between gap-2');
                        top.append(make('span', 'text-[12.5px] font-medium text-gray-800 truncate', u.name), make('span', 'text-[12.5px] font-bold text-gray-900 tabular-nums', String(u.total)));
                        const bar = make('span', 'mt-1 block h-1.5 rounded-full bg-gray-100 overflow-hidden');
                        const fill = make('span', 'block h-full rounded-full'); fill.style.width = (u.total / max * 100) + '%'; fill.style.background = u.color;
                        bar.appendChild(fill);
                        const pct = make('span', 'mt-0.5 block text-[10px] text-gray-400', `${grandTotal ? Math.round(u.total / grandTotal * 100) : 0}% of requests${u.own ? '' : ' · grouped in Others'}`);
                        body.append(top, bar, pct);

                        btn.append(rank, sw, body);
                        btn.addEventListener('click', () => { u.on = !u.on; refresh(); });
                        li.appendChild(btn); ul.appendChild(li);
                    });
                    if (!shown) ul.appendChild(make('li', 'px-4 py-6 text-center text-xs text-gray-400', 'No requestor matches your search.'));
                }

                // ── Table view ──
                function renderTable() {
                    const vis = users.filter(u => u.on);
                    const th = 'px-3 py-2 font-semibold text-[10.5px] uppercase tracking-wide whitespace-nowrap';
                    el('agTableHead').innerHTML = '';
                    const hr = make('tr');
                    hr.appendChild(make('th', `${th} text-left sticky left-0 bg-gray-50 min-w-[160px]`, 'Requestor'));
                    DATA.labels.forEach((l, i) => hr.appendChild(make('th', `${th} text-right ${i === DATA.todayIdx ? 'text-amber-700' : ''}`, l)));
                    hr.appendChild(make('th', `${th} text-right text-gray-700`, 'Total'));
                    el('agTableHead').appendChild(hr);

                    const body = el('agTableBody'); body.innerHTML = '';
                    vis.forEach(u => {
                        const tr = make('tr', 'hover:bg-amber-50/40');
                        const name = make('td', 'px-3 py-2 sticky left-0 bg-white');
                        const wrap = make('span', 'flex items-center gap-2 min-w-0');
                        const sw = make('span', 'w-2.5 h-2.5 rounded-sm shrink-0'); sw.style.background = u.color;
                        wrap.append(sw, make('span', 'truncate max-w-[180px] text-gray-800 font-medium', u.name));
                        name.appendChild(wrap); tr.appendChild(name);
                        u.counts.forEach(v => tr.appendChild(make('td', `px-3 py-2 text-right ${v ? 'text-gray-800' : 'text-gray-300'}`, v ? String(v) : '·')));
                        tr.appendChild(make('td', 'px-3 py-2 text-right font-bold text-gray-900', String(u.total)));
                        body.appendChild(tr);
                    });
                    if (!vis.length) {
                        const tr = make('tr'); const td = make('td', 'px-3 py-8 text-center text-gray-400', 'All requestors are hidden.');
                        td.colSpan = DATA.labels.length + 2; tr.appendChild(td); body.appendChild(tr);
                    }

                    const foot = el('agTableFoot'); foot.innerHTML = '';
                    const fr = make('tr');
                    fr.appendChild(make('td', 'px-3 py-2 sticky left-0 bg-amber-50', 'Total'));
                    let all = 0;
                    DATA.labels.forEach((_, i) => { const t = vis.reduce((s, u) => s + u.counts[i], 0); all += t; fr.appendChild(make('td', 'px-3 py-2 text-right', t ? String(t) : '·')); });
                    fr.appendChild(make('td', 'px-3 py-2 text-right', String(all)));
                    foot.appendChild(fr);
                }

                function renderNote() {
                    const on = users.filter(u => u.on);
                    const t = on.reduce((s, u) => s + u.total, 0);
                    el('agShowingNote').textContent = on.length === users.length
                        ? `${DATA.period} · ${plural(t)} from ${users.length} requestor${users.length === 1 ? '' : 's'}`
                        : `${DATA.period} · showing ${on.length} of ${users.length} requestors (${plural(t)})`;
                }

                function refresh() {
                    chart.data.datasets = buildDatasets();
                    chart.update();
                    renderLegend(); renderList(); renderTable(); renderNote();
                }

                window.agSetAll = on => { users.forEach(u => u.on = on); refresh(); };

                // ── CSV ──
                window.agDownloadCsv = () => {
                    const q = v => `"${String(v).replace(/"/g, '""')}"`;
                    const rows = [['Requestor', ...DATA.labels, 'Total'].map(q).join(',')];
                    users.forEach(u => rows.push([q(u.name), ...u.counts, u.total].join(',')));
                    rows.push([q('Total'), ...DATA.labels.map((_, i) => users.reduce((s, u) => s + u.counts[i], 0)), grandTotal].join(','));
                    const blob = new Blob(['﻿' + rows.join('\r\n')], { type: 'text/csv;charset=utf-8' });
                    const a = make('a'); a.href = URL.createObjectURL(blob);
                    a.download = `budget-requests-${DATA.period.toLowerCase().replace(/\s+/g, '-')}.csv`;
                    document.body.appendChild(a); a.click(); a.remove();
                    setTimeout(() => URL.revokeObjectURL(a.href), 1000);
                };

                // ── Chart / Table toggle (naaalala) ──
                function setView(v) {
                    el('agChartView').classList.toggle('hidden', v !== 'chart');
                    el('agTableView').classList.toggle('hidden', v !== 'table');
                    document.querySelectorAll('.ag-view').forEach(b => {
                        const on = b.dataset.view === v;
                        b.classList.toggle('bg-white', on); b.classList.toggle('text-gray-900', on); b.classList.toggle('shadow-sm', on);
                        b.classList.toggle('text-gray-500', !on);
                        b.setAttribute('aria-selected', on ? 'true' : 'false');
                    });
                    try { localStorage.setItem('agGraphView', v); } catch (e) { }
                }
                document.querySelectorAll('.ag-view').forEach(b => b.addEventListener('click', () => setView(b.dataset.view)));
                let saved = 'chart';
                try { saved = localStorage.getItem('agGraphView') === 'table' ? 'table' : 'chart'; } catch (e) { }

                el('agSearch')?.addEventListener('input', renderList);

                setView(saved);
                refresh();
            })();
        </script>
    <?php endif; ?>
</body>

</html>