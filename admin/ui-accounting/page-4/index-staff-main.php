<?php
// index-staff-main.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_ACCOUNTING];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting Dashboard</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <style>
        .scrollbar-thin::-webkit-scrollbar { width: 4px; }
        .scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
        .scrollbar-thin::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 999px; }

        .day-chip { background:#fff; border-color:#e5e7eb; color:#4b5563; cursor:pointer; }
        .day-chip:hover { border-color:#d97706; color:#b45309; }
        .day-chip[data-empty="1"] { color:#9ca3af; border-style:dashed; }
        .day-chip[data-active="1"] { background:#b45309; border-color:#b45309; color:#fff; }

        @keyframes dayRowFlash { 0%,100% { background: transparent; } 50% { background: #fef3c7; } }
        .day-row-flash { animation: dayRowFlash .6s ease-in-out 5; }
    </style>
</head>

<body class="bg-gray-100">
    <main id="main-content" class="md:ml-56 pt-20 md:pt-5 min-h-screen p-4 md:p-8 transition-all duration-300">

        <!-- ═══ HEADER ═══ -->
        <div class="mb-5 flex items-start sm:items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-11 h-11 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-hand-holding-dollar text-lg"></i>
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl font-bold text-gray-800 leading-tight">For Receiving</h1>
                    <p class="text-sm text-gray-500">Approved requests waiting to be received</p>
                </div>
            </div>
            <div class="flex items-center gap-2 text-[11px] text-gray-500 bg-white border border-gray-200 rounded-full px-3 py-1.5 shadow-sm">
                <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
                <span id="last-updated">Loading…</span>
            </div>
        </div>

        <!-- ═══ STAT CARDS (buwan na nakabukas) ═══ -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
            <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3 shadow-sm min-w-0">
                <div class="w-10 h-10 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-layer-group"></i></div>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide truncate">This month</p>
                    <p id="stat-total" class="text-2xl font-bold text-gray-800 leading-none mt-1">0</p>
                </div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3 shadow-sm min-w-0">
                <div class="w-10 h-10 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center shrink-0"><i class="fa-solid fa-inbox"></i></div>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide truncate">For receive</p>
                    <p id="stat-for" class="text-2xl font-bold text-gray-800 leading-none mt-1">0</p>
                </div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3 shadow-sm min-w-0">
                <div class="w-10 h-10 rounded-lg bg-green-50 text-green-600 flex items-center justify-center shrink-0"><i class="fa-solid fa-circle-check"></i></div>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide truncate">Received</p>
                    <p id="stat-received" class="text-2xl font-bold text-gray-800 leading-none mt-1">0</p>
                </div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3 shadow-sm min-w-0 col-span-2 lg:col-span-1">
                <div class="w-10 h-10 rounded-lg bg-orange-50 text-orange-600 flex items-center justify-center shrink-0"><i class="fa-solid fa-peso-sign"></i></div>
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide truncate">Amount to receive</p>
                    <p id="stat-amount" class="text-xl font-bold text-gray-800 leading-none mt-1 truncate">₱ 0.00</p>
                </div>
            </div>
        </div>

        <!-- ═══ CALENDAR CARD ═══ -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-5">
            <div class="flex items-center justify-between flex-wrap gap-3 px-4 py-3 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <button onclick="prevMonth()" title="Previous month"
                        class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600 transition">
                        <i class="fa-solid fa-chevron-left text-xs"></i>
                    </button>
                    <span id="cal-label" class="text-base font-bold text-gray-800 min-w-[140px] text-center"></span>
                    <button onclick="nextMonth()" title="Next month"
                        class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600 transition">
                        <i class="fa-solid fa-chevron-right text-xs"></i>
                    </button>
                    <button onclick="goToday()"
                        class="ml-1 h-9 text-xs font-semibold text-amber-700 border border-amber-200 bg-amber-50 hover:bg-amber-100 px-3 rounded-lg transition">
                        Today
                    </button>
                </div>

                <div class="relative" id="dates-dropdown-wrap">
                    <button onclick="toggleDatesDropdown(event)"
                        class="h-9 flex items-center gap-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 px-3 rounded-lg transition">
                        <i class="fa-solid fa-calendar-check text-amber-600"></i>
                        <span class="hidden sm:inline">Jump to date</span>
                        <span class="sm:hidden">Dates</span>
                        <i class="fa-solid fa-chevron-down text-[9px] text-gray-400"></i>
                    </button>
                    <div id="dates-dropdown"
                        class="hidden absolute right-0 mt-2 w-72 max-w-[calc(100vw-2rem)] bg-white border border-gray-200 rounded-xl shadow-xl z-50 overflow-hidden">
                        <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                            <p class="text-xs font-bold text-gray-600 uppercase tracking-wide">Dates with requests</p>
                            <span id="dates-dropdown-count" class="text-[10px] text-gray-400"></span>
                        </div>
                        <div class="px-3 py-2 border-b border-gray-100">
                            <div class="relative">
                                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[10px]"></i>
                                <input type="text" id="dates-search" placeholder="Search date..." oninput="filterDatesDropdown()"
                                    class="w-full text-xs border border-gray-200 rounded-lg pl-8 pr-3 py-2 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition">
                            </div>
                        </div>
                        <ul id="dates-list" class="max-h-64 overflow-y-auto py-1 scrollbar-thin"></ul>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-7 bg-gray-50 border-b border-gray-100">
                <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $d): ?>
                    <div class="py-2 text-center text-[10px] sm:text-[11px] font-bold uppercase tracking-wide <?= $d === 'Sun' ? 'text-red-400' : ($d === 'Sat' ? 'text-blue-400' : 'text-gray-500') ?>">
                        <span class="sm:hidden"><?= substr($d, 0, 1) ?></span><span class="hidden sm:inline"><?= $d ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div id="calendar-grid" class="grid grid-cols-7"></div>

            <div class="flex items-center gap-4 flex-wrap px-4 py-2.5 border-t border-gray-100 text-[11px] text-gray-500">
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>For receive</span>
                <span class="flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-full bg-green-500"></span>Received</span>
                <span class="ml-auto hidden sm:inline text-gray-400">Click a day to see its requests</span>
            </div>
        </div>

        <!-- ═══ DAY PANEL ═══ -->
        <div id="day-panel" class="hidden bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-4 sm:px-5 py-3.5 border-b border-gray-100">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center shrink-0">
                        <i class="fa-regular fa-calendar"></i>
                    </div>
                    <div class="min-w-0">
                        <p id="day-panel-title" class="text-sm font-bold text-gray-800 truncate"></p>
                        <p id="day-panel-sub" class="text-[11px] text-gray-500 truncate"></p>
                    </div>
                </div>
                <button onclick="closeDayPanel()" title="Close"
                    class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition shrink-0">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div id="day-filter" class="flex items-center gap-2 flex-wrap px-4 sm:px-5 py-2.5 border-b border-gray-100 bg-gray-50/60"></div>
            <div id="day-requests-cards" class="max-h-[560px] overflow-y-auto scrollbar-thin divide-y divide-gray-100"></div>
        </div>

    </main>
    <!-- View Modal (read-only) -->
    <div id="view-modal"
        class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4 py-8 overflow-y-auto">
        <div class="bg-white w-full max-w-5xl rounded-sm shadow-xl border border-gray-300 my-auto">

            <!-- Header -->
            <div class="grid grid-cols-1 md:grid-cols-[1fr_auto] border-b-2 border-gray-800">
                <div class="flex items-center gap-4 px-3 py-3 border-b-2 md:border-b-0 md:border-r-2 border-gray-800 min-w-0">
                    <div class="w-14 h-14 shrink-0">
                        <img src="<?= BASE_URL ?>/icon/logo.webp" alt="Logo" class="w-full h-full object-contain">
                    </div>
                    <div class="w-px h-12 bg-gray-400"></div>
                    <div class="flex-1">
                        <h1 class="font-bold text-sm uppercase tracking-wide leading-tight">Noblehome Construction
                            Corporation</h1>
                        <p class="text-[10px] text-gray-500 mt-1 leading-relaxed">
                            1181 MC Premiere Bldg., EDSA Balintawak Quezon City<br>
                            noblehomeconsl.ph@gmail.com | Tel. No. 02-88221295 | Cell. No. 0968-591-6544
                        </p>
                    </div>
                </div>
                <div class="flex flex-col">
                    <div class="flex items-center justify-between px-4 py-2 border-b-2 border-gray-800 gap-4">
                        <h2 class="font-bold text-xs md:text-sm uppercase tracking-widest">Budget Request Form
                        </h2>
                        <div class="flex items-center gap-2">

                            <button onclick="printRequest(currentViewRow)"
                                class="flex items-center gap-1.5 text-[10px] font-semibold text-white bg-amber-600 hover:bg-amber-700 px-3 py-1.5 rounded-lg transition">
                                <i class="fa-solid fa-print"></i> Print
                            </button>
                            <button onclick="closeViewModal()"
                                class="text-gray-400 hover:text-red-500 transition-colors p-1">
                                <i class="fa-solid fa-xmark"></i>
                            </button>
                        </div>
                    </div>
                    <div class="flex flex-row flex-1 text-[10px]">
                        <div class="flex flex-col border-r-2 border-gray-800 flex-1">
                            <span
                                class="bg-orange-500 text-white font-bold px-4 py-1 uppercase tracking-wider text-center border-b-2 border-gray-800">Control
                                No.</span>
                            <p id="view-control-no"
                                class="flex-1 px-4 py-1 font-mono text-xs text-center bg-gray-50 md:min-w-[180px]"></p>
                        </div>
                        <div class="flex flex-col flex-1">
                            <span
                                class="bg-orange-500 text-white font-bold px-4 py-1 uppercase tracking-wider text-center border-b-2 border-gray-800">Date:</span>
                            <p id="view-date"
                                class="flex-1 px-4 py-1 font-mono text-xs text-center bg-gray-50 md:min-w-[150px]"></p>
                        </div>
                    </div>
                </div>
            </div>


            <!-- Requestor + Purpose + Category -->
            <div class="grid grid-cols-1 md:grid-cols-3 border-b-2 border-gray-800">
                <div class="flex items-center gap-2 px-4 md:px-6 py-3 border-b md:border-b-0 md:border-r-2 border-gray-800 min-w-0">
                    <span
                        class="text-[10px] font-bold uppercase tracking-widest text-gray-600 whitespace-nowrap">Requestor
                        Name:</span>
                    <p id="view-requestor" class="text-sm text-gray-800 break-words min-w-0"></p>
                </div>
                <div class="flex items-center gap-2 px-4 md:px-6 py-3 border-b md:border-b-0 md:border-r-2 border-gray-800 min-w-0">
                    <span
                        class="text-[10px] font-bold uppercase tracking-widest text-gray-600 whitespace-nowrap">Purpose
                        of Request:</span>
                    <p id="view-purpose" class="text-sm text-gray-800 break-words min-w-0"></p>
                </div>
                <div class="flex items-center gap-2 px-4 md:px-6 py-3 min-w-0">
                    <span
                        class="text-[10px] font-bold uppercase tracking-widest text-gray-600 whitespace-nowrap">Category:</span>
                    <p id="view-category" class="text-sm text-gray-800"></p>
                </div>
            </div>

            <!-- Items Table -->
            <div class="overflow-x-auto max-h-[280px] overflow-y-auto">
                <table class="w-full text-sm border-collapse">
                    <thead class="sticky top-0 z-10">
                        <tr class="bg-orange-500 text-white text-[11px] font-bold uppercase tracking-wider">
                            <th class="w-10 px-3 py-2 border-r border-orange-400 text-center">No.</th>
                            <th class="px-4 py-2 border-r border-orange-400 text-left">Items / Description</th>
                            <th class="px-4 py-2 border-r border-orange-400 text-left">Purpose</th>
                            <th class="w-24 px-4 py-2 border-r border-orange-400 text-center">Quantity</th>
                            <th class="w-28 px-4 py-2 border-r border-orange-400 text-center">Unit Price</th>
                            <th class="w-28 px-4 py-2 border-r border-orange-400 text-center">Amount</th>
                            <th class="px-4 py-2 text-left">Notes</th>
                        </tr>
                    </thead>
                    <tbody id="view-items-tbody"></tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-800 bg-gray-50">
                            <td colspan="5"
                                class="px-4 py-2 font-bold text-xs uppercase tracking-widest text-right border-r border-gray-300">
                                Total:</td>
                            <td id="view-total" class="px-4 py-2 font-bold font-mono text-right"></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Attachments -->
            <div id="view-attachments" class="hidden px-6 py-3 border-t-2 border-gray-800">
                <p class="text-[10px] font-bold uppercase tracking-widest text-gray-600 mb-2">
                    <i class="fa-solid fa-paperclip mr-1"></i> Attachments
                </p>
                <div id="view-attachments-grid" class="flex flex-wrap gap-2"></div>
            </div>

            <!-- Signatures -->
            <div class="grid grid-cols-1 sm:grid-cols-2 border-t-2 border-gray-800">
                <div class="px-5 py-4 border-b-2 sm:border-b-0 sm:border-r-2 border-gray-800">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-gray-600">Approved By:</span>
                        <div id="view-status-badge"></div>
                    </div>
                    <div id="view-approved-by" class="text-center"></div>
                    <div class="border-b-2 border-gray-400 mb-1 mt-6"></div>
                    <p class="text-[10px] text-center text-gray-500 font-medium uppercase tracking-wider">Head</p>
                </div>
                <div class="px-5 py-4">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-gray-600">Received By:</span>
                    </div>
                    <div id="view-received-by" class="text-center"></div>
                    <div class="border-b-2 border-gray-400 mb-1 mt-6"></div>
                    <p class="text-[10px] text-center text-gray-500 font-medium uppercase tracking-wider">&nbsp;</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Lightbox -->
    <div id="lightbox" class="hidden fixed inset-0 z-[200] bg-black/90 flex items-center justify-center px-4"
        onclick="closeLightbox()">
        <button class="absolute top-4 right-4 text-white text-2xl hover:text-gray-300 transition-colors">
            <i class="fa-solid fa-xmark"></i>
        </button>
        <img id="lightbox-img" src="" class="max-w-full max-h-[90vh] object-contain rounded-lg shadow-2xl">
    </div>

    <div id="cat-tooltip" class="fixed z-[9999] pointer-events-none hidden" style="visibility:hidden">
        <div id="cat-tooltip-box"
            class="bg-gray-800 text-white text-xs font-medium px-3 py-2 rounded-lg shadow-lg max-w-[220px] break-words leading-relaxed">
            <span id="cat-tooltip-label" class="text-gray-400 text-[10px] block mb-0.5 uppercase tracking-wider"></span>
            <span id="cat-tooltip-ref"></span>
        </div>
        <div class="w-2 h-2 bg-gray-800 rotate-45 mx-auto -mt-1"></div>
    </div>
    <?php
    // ── Realtime (Supabase): iisang channel para sa lahat ng Accounting Staff ──
    include_once ROOT_PATH . '/network/realtime-helper.php';
    $staffRealtime = [
        'url' => NH_SUPABASE_URL,
        'key' => NH_SUPABASE_ANON_KEY,
        'topic' => nhRealtimeGroupTopic(ROLE_ACCOUNTING, POSITION_STAFF),   // lahat ng Accounting Staff
        'enabled' => !str_contains(NH_SUPABASE_URL, 'YOUR-PROJECT-REF') && NH_SUPABASE_ANON_KEY !== '',
    ];
    ?>
    <?php if (!defined('NH_RT_LOADED')): ?>
    <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2.45.4/dist/umd/supabase.js"></script>
    <?php endif; ?>
    <script>
        const API = '<?= BASE_URL ?>';

        const escHtml = s => String(s ?? '').replace(/[&<>"']/g,
            c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const fmtMoney = n => (parseFloat(n) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const toDate = v => (v ? new Date(String(v).replace(' ', 'T')) : null);
        function fmtShort(v) {
            const d = toDate(v); if (!d || isNaN(d)) return '';
            return d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) + ' · ' +
                d.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', hour12: true });
        }
        function fmtLong(v) {
            const d = toDate(v); if (!d || isNaN(d)) return '';
            return d.toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) + ' ' +
                d.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', hour12: true });
        }
        const rowTotal = r => (r.items ?? []).reduce((s, i) => s + (parseFloat(i.amount) || 0), 0);
        const isReceived = r => !!r.receiver_name;

        // ─── State ───────────────────────────────────────────
        let allRequests = [];
        let lastSig = '';
        let isFetching = false;
        let calYear = new Date().getFullYear();
        let calMonth = new Date().getMonth();
        let selectedDate = null;
        let highlightDone = false;
        let dayFilter = 'all';
        let dayRequests = [];
        let confirmId = null, confirmTimer = null;   // inline confirm ng Mark Received
        const savingIds = new Set();

        const MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'];

        // ─── Stats (buwan na nakabukas) ──────────────────────
        function updateStats() {
            const prefix = calYear + '-' + String(calMonth + 1).padStart(2, '0');
            const month = allRequests.filter(r => r.date_requested?.startsWith(prefix));
            const pend = month.filter(r => !isReceived(r));
            document.getElementById('stat-total').textContent = month.length;
            document.getElementById('stat-for').textContent = pend.length;
            document.getElementById('stat-received').textContent = month.length - pend.length;
            document.getElementById('stat-amount').textContent = '₱ ' + fmtMoney(pend.reduce((s, r) => s + rowTotal(r), 0));
        }

        // ─── Calendar ────────────────────────────────────────
        function renderCalendar() {
            document.getElementById('cal-label').textContent = MONTH_NAMES[calMonth] + ' ' + calYear;
            updateStats();
            const grid = document.getElementById('calendar-grid');
            grid.innerHTML = '';
            const firstDay = new Date(calYear, calMonth, 1).getDay();
            const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();
            const today = new Date();
            const isMobile = window.innerWidth < 768;
            const cellH = isMobile ? '56px' : '104px';

            const byDate = {};
            allRequests.forEach(r => {
                const d = r.date_requested ? r.date_requested.substring(0, 10) : null;
                if (d) (byDate[d] = byDate[d] || []).push(r);
            });

            const blank = () => {
                const b = document.createElement('div');
                b.className = 'border-b border-r border-gray-100 bg-gray-50/60';
                b.style.minHeight = cellH;
                grid.appendChild(b);
            };
            for (let i = 0; i < firstDay; i++) blank();

            for (let day = 1; day <= daysInMonth; day++) {
                const dateStr = `${calYear}-${String(calMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                const requests = byDate[dateStr] ?? [];
                const isToday = today.getFullYear() === calYear && today.getMonth() === calMonth && today.getDate() === day;
                const isSelected = selectedDate === dateStr;
                const dow = (firstDay + day - 1) % 7;
                const has = requests.length > 0;
                const forRec = requests.filter(r => !isReceived(r)).length;
                const rec = requests.length - forRec;

                const cell = document.createElement('div');
                cell.style.minHeight = cellH;
                cell.className = [
                    'border-b border-r border-gray-100 cursor-pointer transition-colors relative min-w-0',
                    isMobile ? 'flex flex-col items-center pt-1.5 pb-1 gap-1' : 'p-1.5 flex flex-col gap-1',
                    isSelected ? 'bg-amber-50 ring-2 ring-inset ring-amber-500' : (has ? 'hover:bg-amber-50/60' : 'hover:bg-gray-50'),
                ].join(' ');

                const numCls = isToday
                    ? 'w-6 h-6 bg-amber-600 text-white rounded-full flex items-center justify-center'
                    : 'w-6 h-6 flex items-center justify-center ' + (dow === 0 ? 'text-red-400' : dow === 6 ? 'text-blue-400' : 'text-gray-700');

                if (isMobile) {
                    cell.innerHTML = `
                        <span class="text-xs font-semibold ${numCls}">${day}</span>
                        ${has ? `<div class="flex items-center gap-0.5">
                            ${forRec ? '<span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>' : ''}
                            ${rec ? '<span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>' : ''}</div>
                        <span class="text-[9px] font-bold text-amber-700 leading-none">${requests.length}</span>` : ''}`;
                } else {
                    cell.innerHTML = `
                        <div class="flex items-center justify-between gap-1">
                            <span class="text-xs font-semibold ${numCls}">${day}</span>
                            ${has ? `<span class="text-[10px] font-bold px-1.5 py-0.5 rounded-md ${forRec ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'}"
                                title="${forRec} for receive · ${rec} received">${forRec ? forRec + ' to receive' : 'All done'}</span>` : ''}
                        </div>
                        ${has ? `<div class="flex flex-col gap-0.5 min-w-0">
                            ${requests.slice(0, 2).map(r => `
                                <div class="text-[10px] leading-tight truncate pl-1.5 pr-1 py-0.5 rounded border-l-2 font-medium
                                    ${isReceived(r) ? 'bg-green-50 text-green-800 border-green-500' : 'bg-blue-50 text-blue-800 border-blue-500'}">
                                    ${escHtml(r.requestor_name)}
                                </div>`).join('')}
                            ${requests.length > 2 ? `<div class="text-[10px] text-gray-400 pl-1">+${requests.length - 2} more</div>` : ''}
                        </div>` : ''}`;
                }

                cell.onclick = () => { dayFilter = 'all'; openDayPanel(dateStr, requests); };
                grid.appendChild(cell);
            }

            const total = firstDay + daysInMonth;
            const trailing = total % 7 === 0 ? 0 : 7 - (total % 7);
            for (let i = 0; i < trailing; i++) blank();
        }

        function prevMonth() {
            calMonth--; if (calMonth < 0) { calMonth = 11; calYear--; }
            closeDayPanel();
        }
        function nextMonth() {
            calMonth++; if (calMonth > 11) { calMonth = 0; calYear++; }
            closeDayPanel();
        }
        function goToday() {
            const now = new Date();
            calYear = now.getFullYear(); calMonth = now.getMonth();
            closeDayPanel();
        }

        // ─── Day Panel ───────────────────────────────────────
        function setDayFilter(f) {
            dayFilter = f;
            if (selectedDate) openDayPanel(selectedDate, dayRequests, true);
        }

        function receiveBtnHtml(id) {
            if (savingIds.has(id)) {
                return `<button type="button" disabled
                    class="inline-flex items-center gap-1.5 bg-blue-400 text-white text-xs font-semibold px-3 py-1.5 rounded-lg cursor-wait">
                    <i class="fa-solid fa-spinner fa-spin"></i> Saving…</button>`;
            }
            if (confirmId === id) {
                return `<span class="inline-flex items-center gap-1.5 flex-wrap">
                    <span class="text-[11px] font-semibold text-gray-600">Mark as received?</span>
                    <button type="button" onclick="event.stopPropagation(); markReceived(${id})"
                        class="inline-flex items-center gap-1 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition">
                        <i class="fa-solid fa-check"></i> Yes</button>
                    <button type="button" onclick="event.stopPropagation(); cancelReceive()"
                        class="bg-white border border-gray-200 hover:bg-gray-50 text-gray-600 text-xs font-semibold px-3 py-1.5 rounded-lg transition">Cancel</button>
                </span>`;
            }
            return `<button type="button" onclick="event.stopPropagation(); askReceive(${id})"
                class="inline-flex items-center gap-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-semibold px-3 py-1.5 rounded-lg transition">
                <i class="fa-solid fa-hand-holding-dollar"></i> Mark Received</button>`;
        }

        function dayRowHtml(row) {
            const id = Number(row.id);
            const received = isReceived(row);
            const followUp = (row.attachment_status ?? 'attached') === 'follow_up';
            const itemCount = (row.items ?? []).filter(i => i.description?.trim()).length;
            const status = received
                ? `<span class="bg-green-100 text-green-800 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wide">Received</span>`
                : `<span class="bg-blue-100 text-blue-800 text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wide">For receive</span>`;

            return `
            <div data-id="${id}" class="day-row flex items-stretch gap-3 px-4 sm:px-5 py-3.5 hover:bg-gray-50 transition-colors">
                <div class="w-1 rounded-full shrink-0 ${received ? 'bg-green-500' : 'bg-blue-500'}"></div>
                <div class="flex-1 min-w-0 flex flex-col lg:flex-row lg:items-center gap-2.5 lg:gap-5">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <button type="button" onclick="viewRequestById(${id})"
                                class="notranslate font-mono text-[11px] font-bold text-amber-800 bg-amber-50 border border-amber-200 rounded px-1.5 py-0.5 hover:bg-amber-100 transition">
                                ${escHtml(row.control_no)}</button>
                            ${status}
                            ${followUp ? `<span class="inline-flex items-center gap-1 bg-yellow-50 text-yellow-700 text-[10px] font-semibold px-2 py-0.5 rounded-full border border-yellow-200 whitespace-nowrap">
                                <i class="fa-solid fa-paperclip text-[9px]"></i> Attachment pending</span>` : ''}
                        </div>
                        <p class="mt-1.5 text-sm font-semibold text-gray-800 truncate">${escHtml(row.requestor_name)}
                            ${row.sender_email ? `<span class="font-normal text-[11px] text-gray-400 ml-1 hidden sm:inline">${escHtml(row.sender_email)}</span>` : ''}</p>
                        <p class="text-[13px] text-gray-600 break-words line-clamp-2">${escHtml(row.purpose)}</p>
                        <div class="flex items-center gap-x-3 gap-y-1 mt-1.5 flex-wrap text-[11px] text-gray-500">
                            ${categoryBadge(row.request_category, row.request_reference)}
                            <span><i class="fa-solid fa-list-ul text-[9px] text-gray-400 mr-1"></i>${itemCount} item${itemCount !== 1 ? 's' : ''}</span>
                            ${row.approver_name ? `<span class="truncate"><i class="fa-solid fa-user-check text-[9px] text-gray-400 mr-1"></i>Approved by ${escHtml(row.approver_name)}${row.approved_at ? ' · ' + escHtml(fmtShort(row.approved_at)) : ''}</span>` : ''}
                            ${received ? `<span class="truncate text-green-700"><i class="fa-solid fa-circle-check text-[9px] mr-1"></i>Received by ${escHtml(row.receiver_name)}${row.received_at ? ' · ' + escHtml(fmtShort(row.received_at)) : ''}</span>` : ''}
                        </div>
                    </div>
                    <div class="flex items-center justify-between lg:justify-end gap-3 shrink-0 flex-wrap">
                        <div class="lg:text-right">
                            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Total</p>
                            <p class="font-mono text-sm font-bold text-gray-800">₱ ${fmtMoney(rowTotal(row))}</p>
                        </div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <button type="button" onclick="viewRequestById(${id})"
                                class="inline-flex items-center gap-1.5 bg-white border border-gray-200 hover:bg-gray-50 text-gray-700 text-xs font-semibold px-3 py-1.5 rounded-lg transition">
                                <i class="fa-solid fa-eye"></i> View</button>
                            ${received ? '' : receiveBtnHtml(id)}
                        </div>
                    </div>
                </div>
            </div>`;
        }

        function openDayPanel(dateStr, requests, keepScroll = false) {
            selectedDate = dateStr;
            dayRequests = requests;
            renderCalendar();

            const panel = document.getElementById('day-panel');
            const d = new Date(dateStr + 'T00:00:00');
            document.getElementById('day-panel-title').textContent =
                d.toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

            const cnt = { all: requests.length, for: 0, received: 0 };
            requests.forEach(r => isReceived(r) ? cnt.received++ : cnt.for++);
            document.getElementById('day-panel-sub').textContent = requests.length
                ? `${requests.length} request${requests.length !== 1 ? 's' : ''} · ${cnt.for} to receive`
                : 'No requests';

            const chip = (key, label) => `
                <button type="button" onclick="setDayFilter('${key}')" data-active="${dayFilter === key ? 1 : 0}" data-empty="${cnt[key] ? 0 : 1}"
                    class="day-chip text-[11px] font-semibold px-3 py-1 rounded-full border transition">
                    ${label} <span class="ml-0.5 opacity-70">${cnt[key]}</span></button>`;
            const bar = document.getElementById('day-filter');
            bar.innerHTML = requests.length ? chip('all', 'All') + chip('for', 'For receive') + chip('received', 'Received') : '';
            bar.classList.toggle('hidden', !requests.length);

            const shown = dayFilter === 'all' ? requests
                : requests.filter(r => dayFilter === 'received' ? isReceived(r) : !isReceived(r));
            const label = { all: '', for: 'for-receive ', received: 'received ' }[dayFilter] ?? '';
            document.getElementById('day-requests-cards').innerHTML = shown.length ? shown.map(dayRowHtml).join('') : `
                <div class="px-4 py-10 text-center">
                    <div class="w-12 h-12 mx-auto rounded-full bg-gray-100 text-gray-400 flex items-center justify-center mb-2">
                        <i class="fa-regular fa-calendar-xmark"></i></div>
                    <p class="text-sm text-gray-500">No ${label}requests on this day.</p>
                    ${dayFilter !== 'all' && requests.length ? `<button type="button" onclick="setDayFilter('all')" class="mt-2 text-xs font-semibold text-amber-700 hover:underline">Show all ${requests.length}</button>` : ''}
                </div>`;

            const wasHidden = panel.classList.contains('hidden');
            panel.classList.remove('hidden');
            if (wasHidden && !keepScroll) setTimeout(() => panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 50);
        }

        function refreshDayPanel() {
            if (selectedDate) openDayPanel(selectedDate, allRequests.filter(r => r.date_requested?.startsWith(selectedDate)), true);
        }

        function closeDayPanel() {
            selectedDate = null;
            document.getElementById('day-panel').classList.add('hidden');
            renderCalendar();
        }

        // ─── Category badge + tooltip ────────────────────────
        function categoryBadge(category, reference) {
            if (!category) return '<span class="text-gray-300 text-xs">—</span>';
            const map = {
                project: { label: 'Project', icon: 'fa-helmet-safety', color: 'bg-blue-100 text-blue-700 border-blue-200' },
                client: { label: 'Client', icon: 'fa-user-tie', color: 'bg-purple-100 text-purple-700 border-purple-200' },
                nhcc: { label: 'NHCC', icon: 'fa-building', color: 'bg-orange-100 text-orange-700 border-orange-200' },
            };
            const cfg = map[category] ?? { label: category, icon: 'fa-tag', color: 'bg-gray-100 text-gray-600 border-gray-200' };
            const pill = (info) => `<span class="inline-flex items-center gap-1 border rounded-full px-2 py-0.5 text-[10px] font-semibold ${info ? 'cursor-default' : ''} ${cfg.color}">
                <i class="fa-solid ${cfg.icon} text-[9px]"></i> ${escHtml(cfg.label)}
                ${info ? '<i class="fa-solid fa-circle-info text-[8px] opacity-50"></i>' : ''}</span>`;
            if (!reference) return pill(false);
            return `<span class="relative inline-block" data-label="${escHtml(cfg.label)}" data-ref="${escHtml(reference)}"
                onmouseenter="showCatTooltip(event, this.dataset.label, this.dataset.ref)" onmouseleave="hideCatTooltip()">${pill(true)}</span>`;
        }

        function showCatTooltip(e, label, reference) {
            const tip = document.getElementById('cat-tooltip');
            document.getElementById('cat-tooltip-label').textContent = label;
            document.getElementById('cat-tooltip-ref').textContent = reference;
            tip.style.visibility = 'hidden';
            tip.classList.remove('hidden');
            const rect = e.currentTarget.getBoundingClientRect();
            const tipW = tip.offsetWidth, tipH = tip.offsetHeight;
            let left = rect.left + (rect.width / 2) - (tipW / 2);
            left = Math.max(8, Math.min(left, window.innerWidth - tipW - 8));
            let top = rect.top - tipH - 8;
            if (top < 8) top = rect.bottom + 8;
            tip.style.left = left + 'px';
            tip.style.top = top + 'px';
            tip.style.visibility = 'visible';
        }
        function hideCatTooltip() { document.getElementById('cat-tooltip').classList.add('hidden'); }

        // ─── Fetch ───────────────────────────────────────────
        function fetchApproved(force = false) {
            if (isFetching && !force) return Promise.resolve();
            isFetching = true;
            const url = API + '/fetchapproved' + (force ? '?_=' + Date.now() : '');
            return fetch(url, force ? { cache: 'no-store' } : {})
                .then(res => res.json())
                .then(data => {
                    if (!Array.isArray(data)) return;
                    const sig = data.map(r =>
                        r.id + '|' + (r.receiver_name ?? '') + '|' + (r.received_at ?? '') + '|' + (r.approved_at ?? '')).join(',');
                    if (sig !== lastSig || force || !highlightDone) {
                        lastSig = sig;
                        allRequests = data;
                        renderCalendar();
                        buildDatesDropdown();
                        refreshDayPanel();
                    }
                    document.getElementById('last-updated').textContent = 'Updated ' + new Date().toLocaleTimeString('en-PH');
                    if (!highlightDone) { highlightDone = true; checkHighlight(); }
                })
                .catch(err => console.error('Fetch error:', err))
                .finally(() => { isFetching = false; });
        }

        // ─── Mark Received (inline confirm, walang confirm()) ─
        function askReceive(id) {
            confirmId = id;
            clearTimeout(confirmTimer);
            confirmTimer = setTimeout(() => { confirmId = null; refreshDayPanel(); }, 6000);
            refreshDayPanel();
        }
        function cancelReceive() {
            confirmId = null; clearTimeout(confirmTimer); refreshDayPanel();
        }

        function markReceived(id) {
            if (savingIds.has(id)) return;
            confirmId = null; clearTimeout(confirmTimer);
            savingIds.add(id);
            refreshDayPanel();
            fetch(API + '/markreceived', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('Request marked as received!', 'received');
                        return fetchApproved(true);
                    }
                    showToast(data.message || 'Could not mark as received.', 'error');
                    return fetchApproved(true);   // hal. na-receive na ng ibang staff
                })
                .catch(() => showToast('Network error. Please try again.', 'error'))
                .finally(() => { savingIds.delete(id); refreshDayPanel(); });
        }

        let currentViewRow = null;

        // ─── View Modal ──────────────────────────────────────
        function viewRequestById(id) {
            const row = allRequests.find(r => Number(r.id) === Number(id));
            if (row) viewRequest(row);
        }

        function sigBlock(name, at, sig) {
            if (!name) return '';
            return `<div class="relative inline-block">
                ${sig ? `<img src="${API}/${escHtml(sig)}" alt="Signature"
                    style="position:absolute; bottom:0; left:50%; transform:translateX(-50%); height:80px; max-width:220px; z-index:10; pointer-events:none;">` : ''}
                <p class="text-sm font-semibold text-gray-800">${escHtml(name)}</p>
                <p class="text-[10px] text-gray-400">${escHtml(fmtLong(at))}</p>
            </div>`;
        }

        function viewRequest(row) {
            currentViewRow = row;
            document.getElementById('view-control-no').textContent = row.control_no;
            document.getElementById('view-date').textContent = row.date_requested;
            document.getElementById('view-requestor').textContent = row.requestor_name;
            document.getElementById('view-purpose').textContent = row.purpose;
            document.getElementById('view-category').innerHTML = categoryBadge(row.request_category, row.request_reference);

            let total = 0, rowNum = 0;
            document.getElementById('view-items-tbody').innerHTML = (row.items ?? [])
                .filter(item => item.description && item.description.trim() !== '')
                .map(item => {
                    rowNum++;
                    const amount = parseFloat(item.amount) || 0;
                    total += amount;
                    return `
                    <tr class="border-t border-gray-200">
                        <td class="px-3 py-2 text-center text-xs text-gray-400 font-mono border-r border-gray-200 w-10">${rowNum}</td>
                        <td class="px-4 py-2 border-r border-gray-200 min-w-[180px]">${escHtml(item.description)}</td>
                        <td class="px-4 py-2 border-r border-gray-200 min-w-[150px]">${escHtml(item.purpose)}</td>
                        <td class="px-4 py-2 border-r border-gray-200 text-center w-24">${escHtml(item.quantity || 0)}</td>
                        <td class="px-4 py-2 border-r border-gray-200 text-right font-mono w-32">${fmtMoney(item.unit_price)}</td>
                        <td class="px-4 py-2 border-r border-gray-200 text-right font-mono w-32">₱ ${fmtMoney(amount)}</td>
                        <td class="px-4 py-2 min-w-[120px]">${escHtml(item.notes)}</td>
                    </tr>`;
                }).join('');
            document.getElementById('view-total').textContent = '₱ ' + fmtMoney(total);

            const received = isReceived(row);
            document.getElementById('view-status-badge').innerHTML = received
                ? `<span class="bg-blue-100 text-blue-700 text-[10px] font-semibold px-2 py-1 rounded-full uppercase tracking-wide">received</span>`
                : `<span class="bg-green-100 text-green-800 text-[10px] font-semibold px-2 py-1 rounded-full uppercase tracking-wide">approved</span>`;

            document.getElementById('view-approved-by').innerHTML = sigBlock(row.approver_name, row.approved_at, row.approver_signature);
            document.getElementById('view-received-by').innerHTML = sigBlock(row.receiver_name, row.received_at, row.receiver_signature);

            let attachments = [];
            try {
                const raw = row.attachments;
                attachments = Array.isArray(raw) ? raw : (typeof raw === 'string' && raw.trim() ? JSON.parse(raw) : []);
            } catch (e) { attachments = []; }

            const attachSection = document.getElementById('view-attachments');
            const oldBadge = document.getElementById('view-attachment-status-badge');
            if (oldBadge) oldBadge.remove();
            if ((row.attachment_status ?? 'attached') === 'follow_up') {
                const badge = document.createElement('div');
                badge.id = 'view-attachment-status-badge';
                badge.className = 'flex items-center gap-2 bg-yellow-50 border border-yellow-200 rounded-lg px-3 py-2 mb-2 mx-6 mt-3';
                badge.innerHTML = `<i class="fa-solid fa-clock text-yellow-500 text-xs"></i>
                    <span class="text-xs font-semibold text-yellow-700">Attachment pending — requestor will follow up</span>`;
                attachSection.before(badge);
            }
            if (attachments.length) {
                attachSection.classList.remove('hidden');
                document.getElementById('view-attachments-grid').innerHTML = attachments.map(path => {
                    const fullUrl = escHtml(`${API}/${path}`);
                    if (String(path).toLowerCase().endsWith('.pdf')) {
                        return `<a href="${fullUrl}" target="_blank" rel="noopener"
                            class="relative flex flex-col items-center justify-center w-20 h-20 rounded-lg border border-gray-200 shadow-sm hover:shadow-md hover:scale-105 transition-all bg-red-50 gap-1">
                            <i class="fa-solid fa-file-pdf text-red-500 text-2xl"></i>
                            <span class="text-[9px] text-red-400 font-semibold uppercase tracking-wide">PDF</span></a>`;
                    }
                    return `<div class="relative group/thumb cursor-pointer" data-src="${fullUrl}" onclick="openLightbox(this.dataset.src, event)">
                        <img src="${fullUrl}" class="w-20 h-20 object-cover rounded-lg border border-gray-200 shadow-sm hover:shadow-md hover:scale-105 transition-all">
                        <div class="absolute inset-0 bg-black/0 group-hover/thumb:bg-black/20 rounded-lg transition-all flex items-center justify-center">
                            <i class="fa-solid fa-magnifying-glass text-white opacity-0 group-hover/thumb:opacity-100 transition-all text-xs"></i>
                        </div></div>`;
                }).join('');
            } else {
                attachSection.classList.add('hidden');
            }

            document.getElementById('view-modal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeViewModal() {
            document.getElementById('view-modal').classList.add('hidden');
            document.body.style.overflow = '';
        }
        document.getElementById('view-modal').addEventListener('click', function (e) { if (e.target === this) closeViewModal(); });

        // ─── Print ───────────────────────────────────────────
        function printRequest(row) {
            if (!row) return;
            const E = escHtml;
            let total = 0, itemRows = '', filledCount = 0;
            (row.items ?? []).forEach(item => {
                if (!item.description) return;
                filledCount++;
                const amount = parseFloat(item.amount || 0);
                total += amount;
                itemRows += `<tr>
    <td style="text-align:center;border:1px solid #ccc;padding:5px;">${filledCount}</td>
    <td style="border:1px solid #ccc;padding:5px;">${E(item.description)}</td>
    <td style="border:1px solid #ccc;padding:5px;">${E(item.purpose)}</td>
    <td style="text-align:center;border:1px solid #ccc;padding:5px;">${E(item.quantity || 0)}</td>
    <td style="text-align:right;border:1px solid #ccc;padding:5px;font-family:monospace;">${fmtMoney(item.unit_price)}</td>
    <td style="text-align:right;border:1px solid #ccc;padding:5px;font-family:monospace;">₱ ${fmtMoney(amount)}</td>
    <td style="border:1px solid #ccc;padding:5px;">${E(item.notes)}</td></tr>`;
            });
            for (let e = filledCount; e < 5; e++) {
                itemRows += `<tr>
    <td style="text-align:center;border:1px solid #ccc;padding:5px;color:#ccc;">${e + 1}</td>
    <td style="border:1px solid #ccc;padding:5px;height:22px;"></td>
    <td style="border:1px solid #ccc;padding:5px;"></td>
    <td style="text-align:center;border:1px solid #ccc;padding:5px;">0</td>
    <td style="text-align:right;border:1px solid #ccc;padding:5px;font-family:monospace;">0.00</td>
    <td style="text-align:right;border:1px solid #ccc;padding:5px;font-family:monospace;">₱ 0.00</td>
    <td style="border:1px solid #ccc;padding:5px;"></td></tr>`;
            }
            const approverName = E(row.approver_name ?? '');
            const approvedAt = E(fmtLong(row.approved_at));
            const receiverName = E(row.receiver_name ?? '');
            const receivedAt = E(fmtLong(row.received_at));
            const approverSigPath = row.approver_signature ? E(`${API}/${row.approver_signature}`) : '';
            const receiverSigPath = row.receiver_signature ? E(`${API}/${row.receiver_signature}`) : '';

            const html = `<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Budget Request - ${E(row.control_no)}</title>
<style>*{box-sizing:border-box;}body{font-family:Arial,sans-serif;font-size:11px;margin:0;padding:15px;}table{width:100%;border-collapse:collapse;}th{background:#f97316;color:white;padding:6px 8px;font-size:10px;text-transform:uppercase;-webkit-print-color-adjust:exact;print-color-adjust:exact;}.label{font-size:9px;font-weight:bold;text-transform:uppercase;letter-spacing:1px;color:#555;}.control-box{background:#f97316;color:white;font-weight:bold;text-align:center;padding:4px 8px;font-size:9px;text-transform:uppercase;-webkit-print-color-adjust:exact;print-color-adjust:exact;}.sig-line{border-top:1px solid #999;margin-top:40px;}@page{size:A4 landscape;margin:1cm;}</style></head><body>
<div style="border:1px solid #111;">
<table style="border-bottom:1px solid #111;"><tr>
<td style="width:60%;border-right:1px solid #111;padding:10px;"><table><tr>
<td style="width:60px;"><img src="${API}/icon/logo.webp" style="width:50px;height:50px;object-fit:contain;"></td>
<td style="border-left:1px solid #aaa;padding-left:10px;"><strong style="font-size:11px;text-transform:uppercase;">Noblehome Construction Corporation</strong><br>
<span style="font-size:9px;color:#666;">1181 MC Premiere Bldg., EDSA Balintawak Quezon City<br>noblehomeconsl.ph@gmail.com | Tel. No. 02-88221295 | Cell. No. 0968-591-6544</span></td></tr></table></td>
<td style="width:40%;padding:0;vertical-align:top;"><div style="font-weight:bold;font-size:12px;text-transform:uppercase;letter-spacing:1px;padding:8px;border-bottom:1px solid #111;">Budget Request Form</div>
<table style="width:100%;"><tr>
<td style="width:50%;border-right:1px solid #111;padding:0;vertical-align:top;"><div class="control-box">Control No.</div><div style="text-align:center;font-family:monospace;font-size:10px;padding:6px;background:#f9fafb;">${E(row.control_no)}</div></td>
<td style="width:50%;padding:0;vertical-align:top;"><div class="control-box">Date</div><div style="text-align:center;font-family:monospace;font-size:10px;padding:6px;background:#f9fafb;">${E(row.date_requested)}</div></td>
</tr></table></td></tr></table>
<table style="border-bottom:1px solid #111;"><tr>
<td style="width:34%;border-right:1px solid #111;padding:8px;"><span class="label">Requestor Name:</span><span style="font-size:12px;margin-left:5px;">${E(row.requestor_name)}</span></td>
<td style="width:33%;border-right:1px solid #111;padding:8px;"><span class="label">Purpose of Request:</span><span style="font-size:12px;margin-left:5px;">${E(row.purpose)}</span></td>
<td style="width:33%;padding:8px;"><span class="label">Category:</span><span style="font-size:12px;margin-left:5px;">${row.request_category ? E(row.request_category.toUpperCase()) : '—'}</span></td>
</tr></table>
<table style="border-collapse:collapse;width:100%;border-bottom:1px solid #111;"><thead><tr>
<th style="width:5%;border:1px solid #ea6c00;">No.</th><th style="border:1px solid #ea6c00;text-align:left;">Items / Description</th>
<th style="border:1px solid #ea6c00;text-align:left;">Purpose</th><th style="width:8%;border:1px solid #ea6c00;">Qty</th>
<th style="width:13%;border:1px solid #ea6c00;">Unit Price</th><th style="width:13%;border:1px solid #ea6c00;">Amount</th>
<th style="border:1px solid #ea6c00;text-align:left;">Notes</th></tr></thead>
<tbody>${itemRows}</tbody>
<tfoot><tr><td colspan="5" style="text-align:right;font-weight:bold;padding:6px;border:1px solid #ccc;font-size:10px;text-transform:uppercase;">Total:</td>
<td style="text-align:right;font-weight:bold;font-family:monospace;border:1px solid #ccc;padding:6px;">₱ ${fmtMoney(total)}</td>
<td style="border:1px solid #ccc;"></td></tr></tfoot></table>
<table style="width:100%;"><tr>
<td style="width:50%;border-right:1px solid #111;padding:15px 20px;vertical-align:bottom;"><span class="label">Approved By:</span>
<div style="margin-top:20px;text-align:center;position:relative;">${approverSigPath ? `<img src="${approverSigPath}" style="position:absolute;bottom:0;left:50%;transform:translateX(-50%);height:60px;max-width:180px;object-fit:contain;">` : ''}<strong style="font-size:12px;">${approverName}</strong><br><span style="font-size:9px;color:#888;">${approvedAt}</span></div>
<div class="sig-line"></div><div style="text-align:center;font-size:9px;text-transform:uppercase;color:#888;margin-top:3px;">Head</div></td>
<td style="width:50%;padding:15px 20px;vertical-align:bottom;"><span class="label">Received By:</span>
<div style="margin-top:20px;text-align:center;position:relative;">${receiverSigPath ? `<img src="${receiverSigPath}" style="position:absolute;bottom:0;left:50%;transform:translateX(-50%);height:60px;max-width:180px;object-fit:contain;">` : ''}<strong style="font-size:12px;">${receiverName}</strong><br><span style="font-size:9px;color:#888;">${receivedAt}</span></div>
<div class="sig-line"></div><div style="text-align:center;font-size:9px;color:#888;margin-top:3px;">&nbsp;</div></td></tr></table>
</div><script>window.onload=function(){window.print();window.onafterprint=function(){window.close();};};<\/script></body></html>`;

            const w = window.open('', '_blank');
            if (!w) { showToast('Allow pop-ups to print.', 'error'); return; }
            w.document.write(html);
            w.document.close();
        }

        // ─── Lightbox ────────────────────────────────────────
        function openLightbox(src, e) {
            if (e) e.stopPropagation();
            document.getElementById('lightbox-img').src = src;
            document.getElementById('lightbox').classList.remove('hidden');
        }
        function closeLightbox() {
            document.getElementById('lightbox').classList.add('hidden');
            document.getElementById('lightbox-img').src = '';
        }
        document.addEventListener('keydown', e => {
            if (e.key !== 'Escape') return;
            if (!document.getElementById('lightbox').classList.contains('hidden')) closeLightbox();
            else closeViewModal();
        });

        // ─── Toast ───────────────────────────────────────────
        function showToast(message, type = 'received') {
            const existing = document.getElementById('toast-notif');
            if (existing) existing.remove();
            const color = type === 'error' ? 'bg-red-500' : type === 'received' ? 'bg-blue-600' : 'bg-green-600';
            const icon = type === 'error' ? 'fa-circle-exclamation' : 'fa-circle-check';
            const toast = document.createElement('div');
            toast.id = 'toast-notif';
            toast.className = `fixed bottom-6 right-4 left-4 sm:left-auto sm:right-6 z-[999] flex items-center gap-3 ${color} text-white text-sm font-medium px-5 py-3 rounded-xl shadow-lg transition-all duration-300 opacity-0`;
            toast.innerHTML = `<i class="fa-solid ${icon}"></i> ${escHtml(message)}`;
            document.body.appendChild(toast);
            setTimeout(() => toast.classList.replace('opacity-0', 'opacity-100'), 10);
            setTimeout(() => {
                toast.classList.replace('opacity-100', 'opacity-0');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // ─── Dates Dropdown ──────────────────────────────────
        let allDatesCache = [];

        function buildDatesDropdown() {
            const byDate = {};
            allRequests.forEach(r => {
                const d = r.date_requested ? r.date_requested.substring(0, 10) : null;
                if (!d) return;
                if (!byDate[d]) byDate[d] = { forReceive: 0, received: 0, total: 0 };
                isReceived(r) ? byDate[d].received++ : byDate[d].forReceive++;
                byDate[d].total++;
            });
            allDatesCache = Object.entries(byDate)
                .sort((a, b) => b[0].localeCompare(a[0]))
                .map(([date, counts]) => ({ date, ...counts }));
            document.getElementById('dates-dropdown-count').textContent =
                allDatesCache.length + ' date' + (allDatesCache.length !== 1 ? 's' : '');
            renderDatesList(allDatesCache);
        }

        function renderDatesList(dates) {
            const ul = document.getElementById('dates-list');
            if (!dates.length) {
                ul.innerHTML = '<li class="px-4 py-4 text-xs text-gray-400 text-center">No dates found.</li>';
                return;
            }
            ul.innerHTML = dates.map(({ date, forReceive, received, total }) => {
                const d = new Date(date + 'T00:00:00');
                const label = d.toLocaleDateString('en-PH', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
                return `
                <li class="flex items-center justify-between gap-3 px-4 py-2.5 hover:bg-amber-50 cursor-pointer transition group"
                    data-date="${escHtml(date)}" onclick="jumpToDate(this.dataset.date)">
                    <div class="flex items-center gap-2 min-w-0">
                        <i class="fa-regular fa-calendar text-gray-300 group-hover:text-amber-600 transition text-xs"></i>
                        <span class="text-xs font-medium text-gray-700 truncate">${escHtml(label)}</span>
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        ${forReceive ? `<span class="text-[10px] font-semibold text-blue-700 bg-blue-50 rounded-full px-1.5 py-0.5" title="for receive">${forReceive} to receive</span>` : ''}
                        ${!forReceive && received ? `<span class="w-1.5 h-1.5 rounded-full bg-green-500" title="all received"></span>` : ''}
                        <span class="text-[10px] font-bold text-amber-700 bg-amber-50 group-hover:bg-white border border-amber-200 rounded-full px-1.5 py-0.5">${Number(total)}</span>
                    </div>
                </li>`;
            }).join('');
        }

        function filterDatesDropdown() {
            const q = document.getElementById('dates-search').value.toLowerCase();
            if (!q) { renderDatesList(allDatesCache); return; }
            renderDatesList(allDatesCache.filter(({ date }) => {
                const d = new Date(date + 'T00:00:00');
                return d.toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })
                    .toLowerCase().includes(q) || date.includes(q);
            }));
        }

        function toggleDatesDropdown(e) {
            e.stopPropagation();
            document.getElementById('dates-dropdown').classList.toggle('hidden');
            document.getElementById('dates-search').value = '';
            renderDatesList(allDatesCache);
        }

        function jumpToDate(dateStr) {
            document.getElementById('dates-dropdown').classList.add('hidden');
            const d = new Date(dateStr + 'T00:00:00');
            calYear = d.getFullYear(); calMonth = d.getMonth();
            dayFilter = 'all';
            openDayPanel(dateStr, allRequests.filter(r => r.date_requested?.startsWith(dateStr)));
            document.getElementById('calendar-grid').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        // Isara lang ang dropdown kapag nag-click sa LABAS nito
        document.addEventListener('click', e => {
            if (!e.target.closest('#dates-dropdown-wrap')) document.getElementById('dates-dropdown').classList.add('hidden');
        });

        // ─── Highlight (galing sa ping/redirect) ─────────────
        function checkHighlight() {
            const params = new URLSearchParams(window.location.search);
            const highlightId = params.get('highlight');
            const jumpDate = params.get('date'); // YYYY-MM-DD
            if (!highlightId) return;

            if (jumpDate) {
                const d = new Date(jumpDate + 'T00:00:00');
                if (!isNaN(d)) {
                    calYear = d.getFullYear(); calMonth = d.getMonth();
                    dayFilter = 'all';
                    openDayPanel(jumpDate, allRequests.filter(r => r.date_requested?.startsWith(jumpDate)));
                }
            }
            let tries = 0;
            const interval = setInterval(() => {
                tries++;
                const row = document.querySelector(`#day-requests-cards .day-row[data-id="${CSS.escape(highlightId)}"]`);
                if (row) {
                    clearInterval(interval);
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    row.classList.add('day-row-flash');
                    setTimeout(() => row.classList.remove('day-row-flash'), 3200);
                }
                if (tries >= 25) clearInterval(interval);
            }, 200);
        }

        // ─── Init ────────────────────────────────────────────
        renderCalendar();
        fetchApproved();

        // ─── Realtime (Supabase) ─────────────────────────────
        // "Doorbell" lang: kapag may na-approve na request o may nag-Mark Received, magpapadala ang PHP
        // ng signal sa channel ng Accounting Staff → kukunin ulit ang listahan sa /fetchapproved.
        const STAFF_RT = <?= json_encode($staffRealtime, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
        let staffLive = false;
        function staffSetLive(on) {
            staffLive = on;
            const dot = document.getElementById('last-updated')?.previousElementSibling;
            if (dot) dot.className = 'w-2 h-2 rounded-full animate-pulse ' + (on ? 'bg-green-500' : 'bg-amber-500');
            if (dot) dot.title = on ? 'Live' : 'Auto-refresh';
        }
        (function staffStartRealtime() {
            // May iisang koneksyon na sa sidebar (window.NH_RT) → gamitin iyon
            if (window.NH_RT?.enabled && NH_RT.topics.includes(STAFF_RT.topic)) {
                let t = null;
                NH_RT.on(STAFF_RT.topic, 'changed', () => {
                    clearTimeout(t);
                    t = setTimeout(() => fetchApproved(true), 300);
                });
                NH_RT.onLive(staffSetLive);
                return;
            }
            if (!STAFF_RT.enabled || !window.supabase?.createClient) { staffSetLive(false); return; }
            try {
                const sb = window.supabase.createClient(STAFF_RT.url, STAFF_RT.key, { auth: { persistSession: false } });
                let t = null;
                sb.channel(STAFF_RT.topic)
                    .on('broadcast', { event: 'changed' }, () => {
                        clearTimeout(t);
                        t = setTimeout(() => fetchApproved(true), 300);   // pagsamahin ang sunod-sunod na signal
                    })
                    .subscribe(status => staffSetLive(status === 'SUBSCRIBED'));
            } catch (e) {
                console.error('Realtime:', e);
                staffSetLive(false);
            }
        })();

        // Polling bilang reserba: kada 30s (habang wala pang signal ang approve ng Head, ito ang sasalo)
        let staffLastPoll = Date.now();
        setInterval(() => {
            if (document.hidden) return;
            if (Date.now() - staffLastPoll < 30000) return;
            staffLastPoll = Date.now();
            fetchApproved();
        }, 5000);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') fetchApproved();
        });

        // Ayusin ang calendar kapag lumipat mula desktop papuntang phone na lapad
        let _wasMobile = window.innerWidth < 768;
        window.addEventListener('resize', () => {
            const m = window.innerWidth < 768;
            if (m !== _wasMobile) { _wasMobile = m; renderCalendar(); }
        });
    
        // ─── Highlight ng huling pinindot na row ─────────────
        // Kapag pinindot ang kahit anong bahagi ng row (View, Approve, atbp.),
        // mananatili itong naka-highlight hanggang may ibang pindutin.
        (function () {
            const KEY = 'stf-picked';
            const SEL = '#day-requests-cards .day-row';
            let picked = null;
            try { picked = sessionStorage.getItem(KEY); } catch (e) { }

            if (!document.getElementById('nh-picked-style')) {
                const st = document.createElement('style');
                st.id = 'nh-picked-style';
                st.textContent = `
                    [data-picked="1"] { position: relative; background: #fffbeb !important;
                        box-shadow: inset 3px 0 0 #d97706, inset 0 0 0 1px #fcd34d; }
                    [data-picked="1"]:hover { background: #fef3c7 !important; }
                    [data-picked="1"]::after { content: 'Last opened'; position: absolute; top: 3px; right: 10px;
                        font-size: 9px; font-weight: 700; letter-spacing: .02em; color: #92400e;
                        background: #fde68a; border-radius: 9999px; padding: 1px 7px; pointer-events: none; }`;
                document.head.appendChild(st);
            }

            function apply() {
                document.querySelectorAll(SEL).forEach(el => {
                    const on = picked !== null && String(el.dataset.id) === picked;
                    const v = on ? '1' : '0';
                    if (el.dataset.picked !== v) el.dataset.picked = v;
                });
            }

            // capture: mauuna ito bago buksan ang modal
            document.addEventListener('click', e => {
                const row = e.target.closest(SEL);
                if (!row || row.dataset.id === undefined) return;
                picked = String(row.dataset.id);
                try { sessionStorage.setItem(KEY, picked); } catch (err) { }
                apply();
            }, true);

            // I-apply ulit tuwing magre-render ang listahan (auto-refresh, filter, ibang buwan)
            new MutationObserver(apply).observe(document.body, { childList: true, subtree: true });
            apply();
        })();
    </script>
</body>

</html>