<?php
// index-custodian-main.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_ACCOUNTING];
$allowedPositions = [POSITION_CUSTODIAN, POSITION_STAFF, POSITION_CUSTOASSISTANT];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Custodian Dashboard</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
</head>

<body class="bg-gray-100">
    <main id="main-content" class="md:ml-56 pt-20 md:pt-5 min-h-screen p-4 md:p-8 transition-all duration-300">

        <!-- ═══ HEADER ═══ -->
        <div class="mb-5 flex items-start sm:items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-11 h-11 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-receipt text-lg"></i>
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl font-bold text-gray-800 leading-tight">Cash Vouchers</h1>
                    <p class="text-sm text-gray-500">Completed and received budget requests</p>
                </div>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <div
                    class="flex items-center gap-2 text-[11px] text-gray-500 bg-white border border-gray-200 rounded-full px-3 py-1.5 shadow-sm">
                    <span id="cv-live-dot" class="w-2 h-2 rounded-full bg-amber-400" title="Green = Live"></span>
                    <span id="last-updated">Loading…</span>
                </div>
                <!-- View Toggle -->
                <div class="flex items-center bg-white border border-gray-200 rounded-lg p-0.5 shadow-sm">
                    <button id="btn-list" type="button" onclick="setView('list')" data-active="1"
                        class="cv-view-btn flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md transition">
                        <i class="fa-solid fa-list text-[11px]"></i> List
                    </button>
                    <button id="btn-calendar" type="button" onclick="setView('calendar')" data-active="0"
                        class="cv-view-btn flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-md transition">
                        <i class="fa-solid fa-calendar-days text-[11px]"></i> Calendar
                    </button>
                </div>
            </div>
        </div>

        <!-- ═══ STATUS TABS (bilang ng buwan; pindutin para i-filter) ═══ -->
        <div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-3 mb-5" id="cv-tabs">
            <?php
            $cvTabs = [
                ['all', 'All this month', 'fa-layer-group', 'bg-amber-50 text-amber-700'],
                ['none', 'Not submitted', 'fa-file-circle-plus', 'bg-gray-100 text-gray-500'],
                ['voucher_approval', 'For approval', 'fa-hourglass-half', 'bg-yellow-50 text-yellow-600'],
                ['ready_to_release', 'Ready to release', 'fa-box-open', 'bg-blue-50 text-blue-600'],
                ['released', 'Released', 'fa-circle-check', 'bg-green-50 text-green-600'],
            ];
            foreach ($cvTabs as $i => [$key, $label, $icon, $tone]): ?>
                <button type="button" data-tab="<?= $key ?>" data-active="<?= $key === 'all' ? 1 : 0 ?>"
                    onclick="setStatusTab('<?= $key ?>')"
                    class="cv-tab border rounded-xl p-3.5 flex items-center gap-3 text-left shadow-sm transition min-w-0 <?= $i === 0 ? 'col-span-2 sm:col-span-1' : '' ?>">
                    <span class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 <?= $tone ?>"><i
                            class="fa-solid <?= $icon ?>"></i></span>
                    <span class="min-w-0">
                        <span
                            class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide leading-tight"><?= $label ?></span>
                        <span class="block text-2xl font-bold text-gray-800 leading-none mt-1"
                            data-count="<?= $key ?>">0</span>
                        <?php if ($key === 'all'): ?>
                            <span class="block text-[11px] text-gray-500 mt-1 truncate font-mono" id="cv-month-total">₱
                                0.00</span>
                        <?php elseif ($key === 'ready_to_release'): ?>
                            <span class="block text-[11px] text-blue-700 mt-1 truncate" id="cv-release-sub">All months</span>
                        <?php endif; ?>
                    </span>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- ═══ MAIN CARD ═══ -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-5">
            <!-- Toolbar -->
            <div class="flex items-center justify-between flex-wrap gap-3 px-4 py-3 border-b border-gray-100">
                <div class="flex items-center gap-2">
                    <button type="button" onclick="prevMonth()" title="Previous month"
                        class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600 transition">
                        <i class="fa-solid fa-chevron-left text-xs"></i>
                    </button>
                    <span id="cal-label" class="text-base font-bold text-gray-800 min-w-[140px] text-center"></span>
                    <button type="button" onclick="nextMonth()" title="Next month"
                        class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600 transition">
                        <i class="fa-solid fa-chevron-right text-xs"></i>
                    </button>
                    <button type="button" onclick="goToday()"
                        class="ml-1 h-9 text-xs font-semibold text-amber-700 border border-amber-200 bg-amber-50 hover:bg-amber-100 px-3 rounded-lg transition">
                        Today
                    </button>
                </div>

                <!-- List controls -->
                <div id="list-controls" class="flex items-center gap-2 flex-wrap w-full sm:w-auto">
                    <div class="relative flex-1 sm:flex-none min-w-0">
                        <i
                            class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[11px]"></i>
                        <input type="text" id="search-input" placeholder="Search voucher, payee, purpose..."
                            class="w-full sm:w-64 h-9 pl-8 pr-3 text-xs border border-gray-200 rounded-lg outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition">
                    </div>
                    <select id="category-filter"
                        class="h-9 text-xs border border-gray-200 rounded-lg px-2.5 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition text-gray-700 bg-white">
                        <option value="">All categories</option>
                        <option value="project">Project</option>
                        <option value="client">Client</option>
                        <option value="nhcc">NHCC</option>
                    </select>
                </div>

                <!-- Calendar controls -->
                <div id="cal-controls" class="hidden items-center gap-2">
                    <div class="relative" id="dates-dropdown-wrap">
                        <button type="button" onclick="toggleDatesDropdown(event)"
                            class="h-9 flex items-center gap-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 px-3 rounded-lg transition">
                            <i class="fa-solid fa-calendar-check text-amber-600"></i>
                            <span class="hidden sm:inline">Jump to date</span>
                            <span class="sm:hidden">Dates</span>
                            <i class="fa-solid fa-chevron-down text-[9px] text-gray-400"></i>
                        </button>
                        <div id="dates-dropdown"
                            class="hidden absolute right-0 mt-2 w-72 max-w-[calc(100vw-2rem)] bg-white border border-gray-200 rounded-xl shadow-xl z-50 overflow-hidden">
                            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                                <p class="text-xs font-bold text-gray-600 uppercase tracking-wide">Dates with vouchers
                                </p>
                                <span id="dates-dropdown-count" class="text-[10px] text-gray-400"></span>
                            </div>
                            <div class="px-3 py-2 border-b border-gray-100">
                                <div class="relative">
                                    <i
                                        class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[10px]"></i>
                                    <input type="text" id="dates-search" placeholder="Search date..."
                                        oninput="filterDatesDropdown()"
                                        class="w-full text-xs border border-gray-200 rounded-lg pl-8 pr-3 py-2 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition">
                                </div>
                            </div>
                            <ul id="dates-list" class="max-h-64 overflow-y-auto py-1 scrollbar-thin"></ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ─── LIST VIEW ─── -->
            <div id="view-list">
                <!-- Paalala: may ready to release mula sa mga naunang buwan -->
                <button type="button" id="cv-release-banner" onclick="setStatusTab('ready_to_release')"
                    class="hidden w-full text-left flex items-center gap-3 px-4 py-2.5 bg-blue-50 border-b border-blue-100 hover:bg-blue-100 transition">
                    <span class="w-7 h-7 rounded-lg bg-blue-600 text-white flex items-center justify-center shrink-0"><i
                            class="fa-solid fa-box-open text-xs"></i></span>
                    <span class="text-xs text-blue-900 flex-1 min-w-0" id="cv-release-banner-text"></span>
                    <span class="text-xs font-semibold text-blue-700 shrink-0">View <i
                            class="fa-solid fa-arrow-right text-[10px]"></i></span>
                </button>
                <div id="cv-list-info" class="px-4 py-2 text-[11px] text-gray-500 bg-gray-50 border-b border-gray-100">
                </div>
                <div id="voucher-cards" class="max-h-[600px] overflow-y-auto scrollbar-thin divide-y divide-gray-100">
                    <div class="px-4 py-10 text-center text-gray-400 text-sm">
                        <i class="fa-solid fa-spinner fa-spin mr-2"></i> Loading...
                    </div>
                </div>
            </div>

            <!-- ─── CALENDAR VIEW ─── -->
            <div id="view-calendar" class="hidden">
                <div class="grid grid-cols-7 bg-gray-50 border-b border-gray-100">
                    <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $d): ?>
                        <div
                            class="py-2 text-center text-[10px] sm:text-[11px] font-bold uppercase tracking-wide <?= $d === 'Sun' ? 'text-red-400' : ($d === 'Sat' ? 'text-blue-400' : 'text-gray-500') ?>">
                            <span class="sm:hidden"><?= substr($d, 0, 1) ?></span><span
                                class="hidden sm:inline"><?= $d ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div id="calendar-grid" class="grid grid-cols-7"></div>
                <div
                    class="flex items-center gap-4 flex-wrap px-4 py-2.5 border-t border-gray-100 text-[11px] text-gray-500">
                    <span class="flex items-center gap-1.5"><span
                            class="w-2.5 h-2.5 rounded-full bg-gray-400"></span>Not submitted</span>
                    <span class="flex items-center gap-1.5"><span
                            class="w-2.5 h-2.5 rounded-full bg-yellow-400"></span>For approval</span>
                    <span class="flex items-center gap-1.5"><span
                            class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>Ready to release</span>
                    <span class="flex items-center gap-1.5"><span
                            class="w-2.5 h-2.5 rounded-full bg-green-500"></span>Released</span>
                </div>
            </div>
        </div>

        <!-- ═══ DAY PANEL (calendar) ═══ -->
        <div id="day-panel" class="hidden bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-4 sm:px-5 py-3.5 border-b border-gray-100">
                <div class="flex items-center gap-3 min-w-0">
                    <div
                        class="w-9 h-9 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center shrink-0">
                        <i class="fa-regular fa-calendar"></i>
                    </div>
                    <div class="min-w-0">
                        <p id="day-panel-title" class="text-sm font-bold text-gray-800 truncate"></p>
                        <p id="day-panel-sub" class="text-[11px] text-gray-500 truncate"></p>
                    </div>
                </div>
                <button type="button" onclick="closeDayPanel()" title="Close"
                    class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition shrink-0">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div id="day-vouchers-cards" class="max-h-[520px] overflow-y-auto scrollbar-thin divide-y divide-gray-100">
            </div>
        </div>


        <!-- ─── VOUCHER MODAL (shared) ────────────────────── -->
        <div id="voucher-modal"
            class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-2 py-4 md:px-4 md:py-8 overflow-y-auto">

            <!-- Desktop layout (md+) -->
            <div class="hidden md:block bg-white w-full max-w-4xl rounded-sm shadow-xl border border-gray-300 my-auto">

                <!-- Header -->
                <div class="grid grid-cols-[auto_1fr_auto] border-b-2 border-orange-400 items-center px-4 py-3 gap-4">
                    <div class="flex items-center gap-3">
                        <img src="<?= BASE_URL ?>/icon/logo.webp" alt="Logo" class="w-12 h-12 object-contain">
                        <div>
                            <p class="font-bold text-sm uppercase">Noblehome Construction Corporation</p>
                            <p class="text-[10px] text-gray-500">1181 MC Premiere Bldg., EDSA Bldg., EDSA Balintawak
                                Quezon City</p>
                            <p class="text-[10px] text-gray-500">noblehomeconsl.ph@gmail.com | Tel. No. 02-88221295 |
                                Cell. No. 0968-591-6544</p>
                        </div>
                    </div>
                    <div></div>
                    <div class="text-right">
                        <p class="font-bold text-lg uppercase tracking-widest text-gray-800">Cash Voucher</p>
                        <p id="v-title" class="text-[10px] text-gray-500 uppercase tracking-wider mb-1"></p>
                        <div class="grid grid-cols-2 mt-1">
                            <div
                                class="bg-orange-500 text-white text-[10px] font-bold px-3 py-1 uppercase tracking-wider text-center border-r border-orange-400">
                                Voucher No.</div>
                            <div
                                class="bg-orange-500 text-white text-[10px] font-bold px-3 py-1 uppercase tracking-wider text-center">
                                Date:</div>
                        </div>
                        <div class="grid grid-cols-2">
                            <div id="v-control-no"
                                class="border border-gray-300 text-xs font-mono px-3 py-1 text-center bg-gray-50 border-r-0">
                            </div>
                            <div id="v-date"
                                class="border border-gray-300 text-xs font-mono px-3 py-1 text-center bg-gray-50"></div>
                        </div>
                    </div>
                </div>

                <!-- Payee / Payment For / Payment Method / Amount in Words -->
                <div class="px-6 py-3 border-b border-gray-300 space-y-2">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex items-center gap-2">
                            <span
                                class="text-[10px] font-bold uppercase tracking-widest text-gray-600 w-28">Payee</span>
                            <span class="text-gray-400 mr-2">:</span>
                            <input id="v-payee" autocomplete="off"
                                class="w-full text-sm font-semibold text-gray-800 border-b border-gray-300 pb-0.5 outline-none bg-transparent" />
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="text-[10px] font-bold uppercase tracking-widest text-gray-600 whitespace-nowrap">Payment
                                For</span>
                            <span class="text-gray-400">:</span>
                            <select id="v-purpose"
                                class="flex-1 border-b border-gray-400 text-sm pb-0.5 outline-none bg-transparent ml-2">
                                <option value="">— Select —</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold uppercase tracking-widest text-gray-600 w-28">Payment
                                Method</span>
                            <span class="text-gray-400 mr-2">:</span>
                            <input id="v-payment-method"
                                class="flex-1 border-b border-gray-400 text-sm pb-0.5 outline-none bg-transparent"
                                placeholder="e.g. Cash, Check No." />
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="text-[10px] font-bold uppercase tracking-widest text-gray-600 whitespace-nowrap">Amount
                                in Words</span>
                            <span class="text-gray-400">:</span>
                            <input id="v-amount-words" readonly
                                class="flex-1 border-b border-gray-400 text-sm pb-0.5 outline-none bg-transparent italic text-gray-700 ml-2" />
                        </div>
                    </div>
                </div>

                <!-- Particulars Table -->
                <div class="relative">
                    <table class="w-full text-sm border-collapse">
                        <thead>
                            <tr class="bg-orange-500 text-white">
                                <th
                                    class="w-12 px-3 py-2 border border-orange-400 text-center text-[11px] uppercase tracking-wider">
                                    No.</th>
                                <th
                                    class="px-4 py-2 border border-orange-400 text-center text-[11px] uppercase tracking-widest">
                                    P A R T I C U L A R S</th>
                                <th
                                    class="w-36 px-4 py-2 border border-orange-400 text-center text-[11px] uppercase tracking-wider">
                                    Amount (P)</th>
                            </tr>
                        </thead>
                        <tbody id="v-items-tbody"></tbody>
                        <tfoot>
                            <tr class="border-t-2 border-orange-400 bg-orange-50">
                                <td colspan="3" class="border border-gray-300 p-0">
                                    <div class="flex items-center justify-between px-4 py-2">
                                        <div>
                                            <span
                                                class="text-[10px] font-bold uppercase tracking-widest text-gray-500">Payment
                                                Method No:</span>
                                            <span id="v-second-no" class="font-mono text-xs ml-2 text-gray-700"></span>
                                        </div>
                                        <div class="flex items-center gap-4">
                                            <span
                                                class="font-bold text-xs uppercase tracking-widest text-gray-700">Total
                                                Amount:</span>
                                            <span id="v-total" class="font-bold font-mono text-sm"></span>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <!-- Signatures -->

                <div class="grid grid-cols-4 border-t-2 border-orange-400">
                    <div
                        class="bg-orange-500 text-white text-[10px] font-bold uppercase tracking-wider text-center py-1.5 border-r border-orange-400">
                        Prepared By</div>
                    <div
                        class="bg-orange-500 text-white text-[10px] font-bold uppercase tracking-wider text-center py-1.5 border-r border-orange-400">
                        Approved By</div>
                    <div
                        class="bg-orange-500 text-white text-[10px] font-bold uppercase tracking-wider text-center py-1.5 border-r border-orange-400">
                        Released By</div>
                    <div
                        class="bg-orange-500 text-white text-[10px] font-bold uppercase tracking-wider text-center py-1.5">
                        Received By</div>
                </div>
                <div class="grid grid-cols-4 border-b border-gray-200">
                    <div class="px-4 py-4 border-r border-gray-200">
                        <p class="text-[10px] text-gray-500">Name: <span id="v-prepared"
                                class="text-gray-800 font-semibold text-xs"></span></p>
                        <p class="text-[10px] text-gray-500 mt-3">Date: <span id="v-prepared-at"
                                class="text-gray-600 text-xs"></span></p>
                    </div>
                    <div class="px-4 py-4 border-r border-gray-200">
                        <p class="text-[10px] text-gray-500">Name: <span id="v-approver"
                                class="text-gray-800 font-semibold text-xs"></span></p>
                        <p class="text-[10px] text-gray-500 mt-3">Date: <span id="v-approved-at"
                                class="text-gray-600 text-xs"></span></p>
                    </div>
                    <div class="px-4 py-4 border-r border-gray-200">
                        <p class="text-[10px] text-gray-500">Name: <span id="v-released"
                                class="text-gray-800 font-semibold text-xs"></span></p>
                        <p class="text-[10px] text-gray-500 mt-3">Date: <span id="v-released-at"
                                class="text-gray-600 text-xs"></span></p>
                    </div>
                    <div class="px-4 py-4">
                        <p class="text-[10px] text-gray-500">Name: <span id="v-receiver"
                                class="text-gray-800 font-semibold text-xs"></span></p>
                        <p class="text-[10px] text-gray-500 mt-3">Date: <span id="v-received-at"
                                class="text-gray-600 text-xs"></span></p>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-between px-6 py-3">
                    <p class="text-[10px] italic text-gray-500">Received the above stated amount in full settlement.</p>
                    <div class="flex gap-2" id="v-footer-btns"></div>
                </div>
            </div>

            <!-- ─── MOBILE layout (below md) ─── -->
            <div class="md:hidden bg-white w-full rounded-2xl shadow-xl my-auto overflow-hidden">

                <!-- Mobile Header -->
                <div class="bg-orange-500 px-4 py-3 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <img src="<?= BASE_URL ?>/icon/logo.webp" alt="Logo"
                            class="w-8 h-8 object-contain bg-white rounded-lg p-0.5">
                        <div>
                            <p class="text-white font-bold text-xs uppercase leading-tight">Cash Voucher</p>
                            <p id="v-title-m" class="text-orange-100 text-[9px] uppercase tracking-wider"></p>
                        </div>
                    </div>
                    <button onclick="closeVoucherModal()" class="text-orange-200 hover:text-white transition">
                        <i class="fa-solid fa-xmark text-lg"></i>
                    </button>
                </div>

                <!-- Voucher No + Date -->
                <div class="grid grid-cols-2 border-b border-gray-100">
                    <div class="px-4 py-2.5 border-r border-gray-100">
                        <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400 mb-0.5">Voucher No.</p>
                        <p id="v-control-no-m" class="font-mono text-xs font-bold text-gray-800 truncate"></p>
                    </div>
                    <div class="px-4 py-2.5">
                        <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400 mb-0.5">Date</p>
                        <p id="v-date-m" class="font-mono text-xs text-gray-700"></p>
                    </div>
                </div>

                <!-- Payee / Address / Purpose / Amount Words -->
                <div class="px-4 py-3 space-y-2.5 border-b border-gray-100">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400 mb-0.5">Payee</p>
                            <input id="v-payee-m" autocomplete="off"
                                class="w-full text-sm font-semibold text-gray-800 border-b border-gray-300 pb-0.5 outline-none bg-transparent" />
                        </div>
                        <div>
                            <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400 mb-0.5">Payment For
                            </p>
                            <select id="v-purpose-m"
                                class="w-full text-sm text-gray-700 border-b border-gray-300 pb-0.5 outline-none bg-transparent">
                                <option value="">— Select —</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400 mb-0.5">Payment
                                Method</p>
                            <input id="v-payment-method-m"
                                class="w-full text-sm text-gray-700 border-b border-gray-300 pb-0.5 outline-none bg-transparent"
                                placeholder="e.g. Cash, Check No." />
                        </div>
                        <div>
                            <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400 mb-0.5">Amount in
                                Words</p>
                            <input id="v-amount-words-m" readonly
                                class="w-full text-xs italic text-gray-600 border-b border-gray-300 pb-0.5 outline-none bg-transparent" />
                        </div>
                    </div>
                </div>

                <!-- Particulars -->
                <div class="border-b border-gray-100">
                    <div class="bg-orange-500 grid grid-cols-[32px_1fr_80px] px-3 py-1.5">
                        <span class="text-[9px] font-bold text-white text-center uppercase">#</span>
                        <span class="text-[9px] font-bold text-white uppercase tracking-widest">Particulars</span>
                        <span class="text-[9px] font-bold text-white uppercase text-right">Amount</span>
                    </div>
                    <div id="v-items-mobile" class="divide-y divide-gray-100"></div>
                    <div class="flex items-center justify-between px-3 py-2 bg-orange-50 border-t-2 border-orange-400">
                        <div>
                            <span class="text-[9px] font-bold uppercase tracking-widest text-gray-500">Payment Method
                                No: </span>
                            <span id="v-second-no-m" class="font-mono text-xs text-gray-700"></span>
                        </div>
                        <div class="text-right">
                            <p class="text-[9px] font-bold uppercase tracking-widest text-gray-500">Total Amount</p>
                            <p id="v-total-m" class="font-bold font-mono text-sm text-orange-600"></p>
                        </div>
                    </div>
                </div>

                <!-- Signatures (2x2 grid on mobile) -->
                <div class="grid grid-cols-2 border-b border-gray-100">
                    <div class="border-b border-r border-gray-100 bg-orange-500 py-1 text-center">
                        <span class="text-[9px] font-bold text-white uppercase tracking-wider">Prepared By</span>
                    </div>
                    <div class="border-b border-gray-100 bg-orange-500 py-1 text-center">
                        <span class="text-[9px] font-bold text-white uppercase tracking-wider">Approved By</span>
                    </div>
                    <div class="px-3 py-3 border-r border-b border-gray-100 min-h-[70px] flex flex-col justify-end">
                        <p id="v-prepared-m" class="text-xs font-semibold text-gray-800"></p>
                        <p id="v-prepared-at-m" class="text-[9px] text-gray-400 mt-0.5"></p>
                    </div>
                    <div class="px-3 py-3 border-b border-gray-100 min-h-[70px] flex flex-col justify-end">
                        <p id="v-approver-m" class="text-xs font-semibold text-gray-800"></p>
                        <p id="v-approved-at-m" class="text-[9px] text-gray-400 mt-0.5"></p>
                    </div>
                    <div class="border-r border-gray-100 bg-orange-500 py-1 text-center">
                        <span class="text-[9px] font-bold text-white uppercase tracking-wider">Released By</span>
                    </div>
                    <div class="bg-orange-500 py-1 text-center">
                        <span class="text-[9px] font-bold text-white uppercase tracking-wider">Received By</span>
                    </div>
                    <div class="px-3 py-3 border-r border-gray-100 min-h-[70px] flex flex-col justify-end">
                        <p id="v-released-m" class="text-xs font-semibold text-gray-800"></p>
                        <p id="v-released-at-m" class="text-[9px] text-gray-400 mt-0.5"></p>
                    </div>
                    <div class="px-3 py-3 min-h-[70px] flex flex-col justify-end">
                        <p id="v-receiver-m" class="text-xs font-semibold text-gray-800"></p>
                        <p id="v-received-at-m" class="text-[9px] text-gray-400 mt-0.5"></p>
                    </div>
                </div>

                <!-- Mobile Footer note + buttons -->
                <div class="px-4 py-3 bg-gray-50">
                    <p class="text-[9px] italic text-gray-400 mb-2">Received the above stated amount in full settlement.
                    </p>
                    <div class="flex flex-col gap-2" id="v-footer-btns-m"></div>
                </div>
            </div>
        </div>

        <!-- ─── CONFIRMATION MODAL ───────────────────────────────── -->
        <div id="voucher-confirm-modal"
            class="hidden fixed inset-0 z-[60] flex items-start justify-center bg-black/50 px-2 py-4 md:px-4 md:py-6 overflow-y-auto">
            <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl my-auto overflow-hidden">

                <!-- Header -->
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                    <div class="flex items-center gap-2">
                        <div class="w-7 h-7 rounded-lg bg-orange-100 flex items-center justify-center">
                            <i class="fa-solid fa-circle-check text-orange-500 text-xs"></i>
                        </div>
                        <div>
                            <h3 class="font-bold text-sm text-gray-800">Confirm Voucher Submission</h3>
                            <p class="text-[10px] text-gray-400">Review before submitting</p>
                        </div>
                    </div>
                    <button onclick="closeConfirmModal()" class="text-gray-400 hover:text-gray-600 transition-colors">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <!-- Body: side by side -->
                <div class="grid grid-cols-1 md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-gray-100">

                    <!-- LEFT: Voucher Summary -->
                    <div class="px-6 py-5 space-y-3">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-orange-500 mb-2">Voucher Details
                        </p>

                        <div class="space-y-2">
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-400 font-medium">Voucher No.</span>
                                <span id="conf-voucher-no" class="font-mono font-bold text-gray-700"></span>
                            </div>
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-400 font-medium">Date</span>
                                <span id="conf-date" class="font-mono text-gray-700"></span>
                            </div>
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-400 font-medium">Payee</span>
                                <span id="conf-payee" class="text-gray-700 text-right max-w-[180px] truncate"></span>
                            </div>
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-400 font-medium">Payment For</span>
                                <span id="conf-purpose" class="text-gray-700 text-right max-w-[180px] truncate"></span>
                            </div>
                            <div class="flex justify-between text-xs">
                                <span class="text-gray-400 font-medium">Total Amount</span>
                                <span id="conf-total" class="font-mono font-bold text-orange-600"></span>
                            </div>
                        </div>

                        <hr class="border-gray-100">

                        <!-- Department dropdown -->
                        <div class="space-y-1.5">
                            <label class="text-[10px] font-bold uppercase tracking-widest text-gray-500">
                                Department <span class="text-gray-300">(optional)</span>
                            </label>
                            <select id="conf-second-no-select"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-orange-400 transition-all">
                                <option value="">— Select Department —</option>
                            </select>
                        </div>
                    </div>

                    <!-- RIGHT: Mapped Expense Preview -->
                    <div class="px-6 py-5 space-y-3">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-700 mb-2">
                            <i class="fa-solid fa-receipt mr-1 text-gray-400"></i>Will also save to Costs / Expenses
                        </p>

                        <!-- Project picker -->
                        <div class="space-y-1.5">
                            <div class="flex items-center justify-between">
                                <label class="text-[10px] font-bold uppercase tracking-widest text-gray-500">
                                    Link to Project <span class="text-gray-300">(optional)</span>
                                </label>
                                <button type="button" onclick="toggleNewProject()"
                                    class="flex items-center gap-1 text-[10px] font-semibold text-orange-500 hover:text-orange-600 transition">
                                    <i class="fa-solid fa-plus text-[9px]"></i> New project
                                </button>
                            </div>

                            <select id="conf-project-select"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-orange-400 transition-all">
                                <option value="">— Select Project —</option>
                            </select>

                            <!-- Inline create -->
                            <div id="new-project-box"
                                class="hidden mt-2 p-3 rounded-lg border border-orange-200 bg-orange-50/60 space-y-2">
                                <label class="text-[10px] font-bold uppercase tracking-widest text-gray-500">New project
                                    name</label>
                                <div class="flex gap-2">
                                    <input id="new-project-name" type="text" placeholder="e.g. NOBLEHOME"
                                        onkeydown="if(event.key==='Enter'){event.preventDefault();createProjectInline();}"
                                        class="flex-1 min-w-0 border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-orange-400 bg-white uppercase">
                                    <button type="button" id="btn-create-project" onclick="createProjectInline()"
                                        class="flex items-center gap-1.5 bg-orange-500 hover:bg-orange-600 disabled:opacity-60 text-white text-xs font-semibold px-3 py-2 rounded-lg transition whitespace-nowrap">
                                        <i class="fa-solid fa-plus text-[10px]"></i> Create
                                    </button>
                                </div>
                                <p class="text-[10px] text-gray-400">
                                    Gagawa ng bagong project (status: New) at awtomatikong pipiliin dito.
                                </p>
                            </div>
                        </div>

                        <hr class="border-gray-100">
                        <p class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mb-1">Expense Entry
                            Preview</p>

                        <div class="bg-gray-50 rounded-lg border border-gray-100 divide-y divide-gray-100 text-xs">
                            <div class="flex justify-between px-3 py-2">
                                <span class="text-gray-400">Account Title</span>
                                <span id="prev-title"
                                    class="text-gray-700 font-medium text-right max-w-[180px] truncate">—</span>
                            </div>
                            <div class="flex justify-between px-3 py-2">
                                <span class="text-gray-400">Particulars</span>
                                <span id="prev-particulars"
                                    class="text-gray-700 font-medium text-right max-w-[180px] truncate">—</span>
                            </div>
                            <div class="flex justify-between px-3 py-2">
                                <span class="text-gray-400">Amount</span>
                                <span id="prev-amount" class="font-mono font-bold text-gray-800">—</span>
                            </div>
                            <div class="flex justify-between px-3 py-2">
                                <span class="text-gray-400">Mode of Payment</span>
                                <span id="prev-mode" class="text-gray-700">—</span>
                            </div>
                            <div class="flex justify-between px-3 py-2">
                                <span class="text-gray-400">Reference</span>
                                <span id="prev-reference" class="font-mono text-gray-700">—</span>
                            </div>
                            <div class="flex justify-between px-3 py-2">
                                <span class="text-gray-400">Payment Date</span>
                                <span id="prev-payment-date" class="font-mono text-gray-700">—</span>
                            </div>
                        </div>

                        <p class="text-[10px] text-gray-400 italic">
                            <i class="fa-solid fa-info-circle mr-1"></i>
                            This expense entry will be saved to the selected project automatically.
                        </p>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100 bg-gray-50">
                    <button onclick="closeConfirmModal()"
                        class="text-sm text-gray-500 hover:text-gray-700 font-medium px-4 py-2 rounded transition-all border border-gray-200 bg-white">
                        Cancel
                    </button>
                    <button onclick="submitWithTitle()"
                        class="flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-white text-sm font-semibold px-6 py-2 rounded-lg transition-all">
                        <i class="fa-solid fa-paper-plane text-xs"></i>Submit & Save Expense
                    </button>
                </div>
            </div>
        </div>

        <!-- Category Tooltip -->
        <div id="cat-tooltip" class="fixed z-[9999] pointer-events-none hidden" style="visibility:hidden">
            <div
                class="bg-gray-800 text-white text-xs font-medium px-3 py-2 rounded-lg shadow-lg max-w-[220px] break-words leading-relaxed">
                <span id="cat-tooltip-label"
                    class="text-gray-400 text-[10px] block mb-0.5 uppercase tracking-wider"></span>
                <span id="cat-tooltip-ref"></span>
            </div>
            <div class="w-2 h-2 bg-gray-800 rotate-45 mx-auto -mt-1"></div>
        </div>

    </main>

    <style>
        .scrollbar-thin::-webkit-scrollbar {
            width: 4px;
        }

        .scrollbar-thin::-webkit-scrollbar-track {
            background: transparent;
        }

        .scrollbar-thin::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 999px;
        }

        .scrollbar-thin::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }

        .cv-view-btn {
            color: #6b7280;
        }

        .cv-view-btn:hover {
            background: #f9fafb;
        }

        .cv-view-btn[data-active="1"] {
            background: #b45309;
            color: #fff;
        }

        .cv-tab {
            background: #fff;
            border-color: #e5e7eb;
        }

        .cv-tab:hover {
            border-color: #f59e0b;
        }

        .cv-tab[data-active="1"] {
            border-color: #b45309;
            box-shadow: 0 0 0 1px #b45309 inset;
            background: #fffbeb;
        }

        @keyframes cvRowFlash {

            0%,
            100% {
                background: transparent;
            }

            50% {
                background: #fef3c7;
            }
        }

        .cv-row-flash {
            animation: cvRowFlash .6s ease-in-out 5;
        }
    </style>


    <script>
        // ─── State ───────────────────────────────────────────
        let allData = [];
        let currentRow = null;
        let currentView = 'list';
        let calYear = new Date().getFullYear();
        let calMonth = new Date().getMonth();
        let selectedDate = null;
        let allDatesCache = [];
        let datesLoaded = false;
        let releaseQueue = [];        // ready_to_release mula sa lahat ng buwan
        let releaseLoaded = false;
        let releaseSeq = 0, releaseLastAt = 0;
        let lastSig = '';
        let statusTab = 'all';
        let isLoadingMonth = true;
        const POSITION = '<?= htmlspecialchars($position) ?>';
        const isCustoAssistant = POSITION === '<?= POSITION_CUSTOASSISTANT ?>';
        const MY_ID = '<?= intval($_SESSION['account_id'] ?? 0) ?>';

        const MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

        const escHtml = s => String(s ?? '').replace(/[&<>"']/g,
            c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const fmtMoney = n => (parseFloat(n) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const rowTotal = r => (r.items ?? []).reduce((s, i) => s + (parseFloat(i.amount) || 0), 0);
        const rowStatus = r => r.voucher_status || 'none';
        function fmtDay(v) {
            if (!v) return '—';
            const d = new Date(String(v).substring(0, 10) + 'T00:00:00');
            return isNaN(d) ? String(v) : d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
        }

        const STATUS = {
            none: { label: 'Not submitted', badge: 'bg-gray-100 text-gray-600', bar: 'bg-gray-400', chip: 'bg-gray-50 text-gray-700 border-gray-400' },
            voucher_approval: { label: 'For approval', badge: 'bg-yellow-100 text-yellow-800', bar: 'bg-yellow-400', chip: 'bg-yellow-50 text-yellow-800 border-yellow-400' },
            ready_to_release: { label: 'Ready to release', badge: 'bg-blue-100 text-blue-800', bar: 'bg-blue-500', chip: 'bg-blue-50 text-blue-800 border-blue-500' },
            released: { label: 'Released', badge: 'bg-green-100 text-green-800', bar: 'bg-green-500', chip: 'bg-green-50 text-green-800 border-green-500' },
        };
        const stCfg = s => STATUS[s] ?? { label: s, badge: 'bg-gray-100 text-gray-600', bar: 'bg-gray-300', chip: 'bg-gray-50 text-gray-700 border-gray-300' };

        // ─── View toggle ─────────────────────────────────────
        function setView(view) {
            currentView = view;
            const isList = view === 'list';
            document.getElementById('view-list').classList.toggle('hidden', !isList);
            document.getElementById('view-calendar').classList.toggle('hidden', isList);
            document.getElementById('list-controls').style.display = isList ? 'flex' : 'none';
            const calCtrl = document.getElementById('cal-controls');
            calCtrl.classList.toggle('hidden', isList);
            calCtrl.classList.toggle('flex', !isList);
            document.getElementById('btn-list').dataset.active = isList ? '1' : '0';
            document.getElementById('btn-calendar').dataset.active = isList ? '0' : '1';
            if (isList) {
                document.getElementById('day-panel').classList.add('hidden');
                selectedDate = null;
                applyFilters();
            } else {
                renderCalendar();
            }
        }

        // Status tabs: sa list nagfi-filter; sa calendar, lilipat sa list
        function setStatusTab(key) {
            statusTab = key;
            document.querySelectorAll('#cv-tabs .cv-tab').forEach(b => b.dataset.active = b.dataset.tab === key ? '1' : '0');
            if (currentView !== 'list') setView('list'); else applyFilters();
        }

        function updateStats() {
            const c = { all: allData.length, none: 0, voucher_approval: 0, ready_to_release: 0, released: 0 };
            let sum = 0;
            allData.forEach(r => { const s = rowStatus(r); if (c[s] !== undefined) c[s]++; sum += rowTotal(r); });
            if (releaseLoaded) c.ready_to_release = releaseQueue.length; // lahat ng buwan
            document.querySelectorAll('#cv-tabs [data-count]').forEach(el => el.textContent = c[el.dataset.count] ?? 0);
            document.getElementById('cv-month-total').textContent = '₱ ' + fmtMoney(sum);
            const older = releaseQueue.filter(r => !String(r.date_requested ?? '').startsWith(monthKey()));
            document.getElementById('cv-release-sub').textContent = !releaseLoaded ? 'Checking all months…'
                : older.length ? `${older.length} from earlier months` : 'All months';
            const banner = document.getElementById('cv-release-banner');
            const showBanner = releaseLoaded && older.length > 0 && statusTab !== 'ready_to_release';
            banner.classList.toggle('hidden', !showBanner);
            if (showBanner) {
                const amt = older.reduce((s, r) => s + rowTotal(r), 0);
                document.getElementById('cv-release-banner-text').innerHTML =
                    `<b>${older.length}</b> voucher${older.length === 1 ? '' : 's'} from earlier months ${older.length === 1 ? 'is' : 'are'} still <b>ready to release</b> (₱ ${fmtMoney(amt)}).`;
            }
        }

        // ─── Helpers ─────────────────────────────────────────
        function numberToWords(amount) {
            const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
            const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
            if (amount === 0) return 'Zero Pesos Only';
            function convertHundreds(n) {
                let r = '';
                if (n >= 100) { r += ones[Math.floor(n / 100)] + ' Hundred '; n %= 100; }
                if (n >= 20) { r += tens[Math.floor(n / 10)] + ' '; n %= 10; }
                if (n > 0) { r += ones[n] + ' '; }
                return r;
            }
            let intPart = Math.floor(amount);
            let decPart = Math.round((amount - intPart) * 100);
            let result = '';
            if (intPart >= 1000000) { result += convertHundreds(Math.floor(intPart / 1000000)) + 'Million '; intPart %= 1000000; }
            if (intPart >= 1000) { result += convertHundreds(Math.floor(intPart / 1000)) + 'Thousand '; intPart %= 1000; }
            if (intPart > 0) { result += convertHundreds(intPart); }
            result += 'Pesos';
            if (decPart > 0) result += ' and ' + decPart + '/100';
            return result.trim() + ' Only';
        }

        function statusBadge(status) {
            const cfg = stCfg(status || 'none');
            return `<span class="${cfg.badge} text-[10px] font-bold px-2 py-0.5 rounded-full uppercase tracking-wide whitespace-nowrap">${escHtml(cfg.label)}</span>`;
        }

        function categoryBadge(category, reference) {
            if (!category) return '<span class="text-gray-300 text-xs">—</span>';
            const map = {
                project: { label: 'Project', icon: 'fa-helmet-safety', color: 'bg-blue-100 text-blue-700 border-blue-200' },
                client: { label: 'Client', icon: 'fa-user-tie', color: 'bg-purple-100 text-purple-700 border-purple-200' },
                nhcc: { label: 'NHCC', icon: 'fa-building', color: 'bg-orange-100 text-orange-700 border-orange-200' },
            };
            const cfg = map[category] ?? { label: category, icon: 'fa-tag', color: 'bg-gray-100 text-gray-600 border-gray-200' };
            const pill = info => `<span class="inline-flex items-center gap-1 border rounded-full px-2 py-0.5 text-[10px] font-semibold ${info ? 'cursor-default' : ''} ${cfg.color}">
                <i class="fa-solid ${cfg.icon} text-[9px]"></i> ${escHtml(cfg.label)}
                ${info ? '<i class="fa-solid fa-circle-info text-[8px] opacity-50"></i>' : ''}</span>`;
            if (!reference) return pill(false);
            return `<span class="relative inline-block" data-label="${escHtml(cfg.label)}" data-ref="${escHtml(reference)}"
                onmouseenter="showCatTooltip(event, this.dataset.label, this.dataset.ref)" onmouseleave="hideCatTooltip()">${pill(true)}</span>`;
        }

        // Ligtas na highlight: i-escape muna, saka markahan
        function highlight(text, q) {
            const safe = escHtml(text ?? '');
            if (!q) return safe;
            const qs = escHtml(q).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            return safe.replace(new RegExp(`(${qs})`, 'gi'), '<mark class="bg-yellow-200 text-yellow-900 rounded px-0.5">$1</mark>');
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

        function viewVoucherById(id) {
            const row = allData.find(r => String(r.id) === String(id))
                || releaseQueue.find(r => String(r.id) === String(id));
            if (row) viewVoucher(row);
        }

        // Isang row (ginagamit sa list at sa day panel)
        function voucherRowHtml(row, q = '') {
            const st = rowStatus(row);
            const cfg = stCfg(st);
            const receiver = row.manual_receiver_name || row.receiver_name;
            const id = escHtml(row.id);
            return `
            <div data-id="${id}" class="cv-row flex items-stretch gap-3 px-4 sm:px-5 py-3.5 hover:bg-gray-50 transition-colors cursor-pointer"
                onclick="viewVoucherById(this.dataset.id)">
                <div class="w-1 rounded-full shrink-0 ${cfg.bar}"></div>
                <div class="flex-1 min-w-0 flex flex-col lg:flex-row lg:items-center gap-2.5 lg:gap-5">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            ${row.voucher_control_no
                    ? `<span class="notranslate font-mono text-[11px] font-bold text-amber-800 bg-amber-50 border border-amber-200 rounded px-1.5 py-0.5">${highlight(row.voucher_control_no, q)}</span>`
                    : `<span class="text-[11px] font-semibold text-gray-500 bg-gray-50 border border-dashed border-gray-300 rounded px-1.5 py-0.5">No voucher yet</span>`}
                            ${statusBadge(st)}
                            <span class="notranslate font-mono text-[10px] text-gray-400">BR ${highlight(row.budget_control_no ?? '—', q)}</span>
                        </div>
                        <p class="mt-1.5 text-sm font-semibold text-gray-800 truncate">${highlight(row.voucher_payee || row.requestor_name || '—', q)}</p>
                        <p class="text-[13px] text-gray-600 break-words line-clamp-2">${highlight(row.purpose ?? '—', q)}</p>
                        <div class="flex items-center gap-x-3 gap-y-1 mt-1.5 flex-wrap text-[11px] text-gray-500">
                            ${categoryBadge(row.request_category, row.request_reference)}
                            <span class="whitespace-nowrap"><i class="fa-regular fa-calendar text-gray-400 mr-1"></i>${escHtml(fmtDay(row.date_requested))}</span>
                            ${row.approver_name ? `<span class="truncate"><i class="fa-solid fa-user-check text-[9px] text-gray-400 mr-1"></i>Approved: ${highlight(row.approver_name, q)}</span>` : ''}
                            ${receiver ? `<span class="truncate"><i class="fa-solid fa-hand-holding text-[9px] text-gray-400 mr-1"></i>Received: ${highlight(receiver, q)}</span>` : ''}
                        </div>
                    </div>
                    <div class="flex items-center justify-between lg:justify-end gap-3 shrink-0">
                        <div class="lg:text-right">
                            <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Total</p>
                            <p class="font-mono text-sm font-bold ${st === 'released' ? 'text-green-700' : 'text-gray-800'}">₱ ${fmtMoney(rowTotal(row))}</p>
                        </div>
                        <button type="button" onclick="event.stopPropagation(); viewVoucherById('${id}')"
                            class="inline-flex items-center gap-1.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold px-3.5 py-1.5 rounded-lg transition">
                            <i class="fa-solid fa-receipt"></i> View
                        </button>
                    </div>
                </div>
            </div>`;
        }

        function emptyHtml(title, sub = '') {
            return `<div class="px-4 py-12 text-center">
                <div class="w-12 h-12 mx-auto rounded-full bg-gray-100 text-gray-400 flex items-center justify-center mb-2">
                    <i class="fa-solid fa-inbox"></i></div>
                <p class="text-sm font-semibold text-gray-600">${escHtml(title)}</p>
                ${sub ? `<p class="text-xs text-gray-400 mt-1">${escHtml(sub)}</p>` : ''}
            </div>`;
        }

        // ─── List view ───────────────────────────────────────
        function renderTable(data, q = '') {
            const cards = document.getElementById('voucher-cards');
            const info = document.getElementById('cv-list-info');
            const monthLabel = MONTH_NAMES[calMonth] + ' ' + calYear;
            if (isLoadingMonth && !allData.length) {
                info.textContent = monthLabel;
                cards.innerHTML = `<div class="px-4 py-10 text-center text-gray-400 text-sm"><i class="fa-solid fa-spinner fa-spin mr-2"></i> Loading...</div>`;
                return;
            }
            if (statusTab === 'ready_to_release') {
                info.innerHTML = `Showing <b class="text-gray-700">${data.length}</b> ready to release · <span class="text-blue-700 font-semibold">all months</span>${releaseLoaded ? '' : ' <i class="fa-solid fa-spinner fa-spin ml-1"></i>'}`;
                cards.innerHTML = data.length ? data.map(r => voucherRowHtml(r, q)).join('')
                    : releaseLoaded ? emptyHtml('Nothing is waiting to be released.', 'All approved vouchers have been released.')
                        : `<div class="px-4 py-10 text-center text-gray-400 text-sm"><i class="fa-solid fa-spinner fa-spin mr-2"></i> Checking all months...</div>`;
                return;
            }
            info.innerHTML = `Showing <b class="text-gray-700">${data.length}</b> of ${allData.length} voucher${allData.length !== 1 ? 's' : ''} · ${escHtml(monthLabel)}`;
            if (!data.length) {
                cards.innerHTML = allData.length
                    ? emptyHtml('No matching vouchers.', 'Try another search, status, or category.')
                    : emptyHtml('No vouchers for this month.', 'Use the arrows above to check another month.');
                return;
            }
            cards.innerHTML = data.map(r => voucherRowHtml(r, q)).join('');
        }

        function applyFilters() {
            updateStats();
            const q = document.getElementById('search-input').value.trim().toLowerCase();
            const cat = document.getElementById('category-filter').value;
            // "Ready to release": mula sa LAHAT ng buwan para walang makaligtaan
            const source = statusTab === 'ready_to_release'
                ? (releaseLoaded ? releaseQueue : allData.filter(r => rowStatus(r) === 'ready_to_release'))
                : allData;
            const filtered = source.filter(row => {
                const matchSearch = !q || [row.voucher_control_no, row.budget_control_no, row.voucher_payee, row.requestor_name,
                row.purpose, row.approver_name, row.receiver_name, row.manual_receiver_name, row.request_reference]
                    .some(v => v && String(v).toLowerCase().includes(q));
                const matchCategory = !cat || row.request_category === cat;
                const matchStatus = statusTab === 'all' || rowStatus(row) === statusTab;
                return matchSearch && matchCategory && matchStatus;
            });
            renderTable(filtered, q);
        }

        document.getElementById('search-input').addEventListener('input', applyFilters);
        document.getElementById('category-filter').addEventListener('change', applyFilters);

        // ─── CALENDAR VIEW ───────────────────────────────────
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
            allData.forEach(r => {
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
                const rows = byDate[dateStr] ?? [];
                const isToday = today.getFullYear() === calYear && today.getMonth() === calMonth && today.getDate() === day;
                const isSelected = selectedDate === dateStr;
                const dow = (firstDay + day - 1) % 7;
                const has = rows.length > 0;
                const open = rows.filter(r => rowStatus(r) !== 'released').length;

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
                    const seen = [...new Set(rows.map(rowStatus))];
                    cell.innerHTML = `
                        <span class="text-xs font-semibold ${numCls}">${day}</span>
                        ${has ? `<div class="flex items-center gap-0.5">${seen.map(s => `<span class="w-1.5 h-1.5 rounded-full ${stCfg(s).bar}"></span>`).join('')}</div>
                        <span class="text-[9px] font-bold text-amber-700 leading-none">${rows.length}</span>` : ''}`;
                } else {
                    cell.innerHTML = `
                        <div class="flex items-center justify-between gap-1">
                            <span class="text-xs font-semibold ${numCls}">${day}</span>
                            ${has ? `<span class="text-[10px] font-bold px-1.5 py-0.5 rounded-md ${open ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-800'}"
                                title="${open} not yet released">${rows.length}</span>` : ''}
                        </div>
                        ${has ? `<div class="flex flex-col gap-0.5 min-w-0">
                            ${rows.slice(0, 2).map(r => `
                                <div class="text-[10px] leading-tight truncate pl-1.5 pr-1 py-0.5 rounded border-l-2 font-medium ${stCfg(rowStatus(r)).chip}">
                                    ${escHtml(r.voucher_payee || r.purpose || '—')}
                                </div>`).join('')}
                            ${rows.length > 2 ? `<div class="text-[10px] text-gray-400 pl-1">+${rows.length - 2} more</div>` : ''}
                        </div>` : ''}`;
                }

                cell.onclick = () => openDayPanel(dateStr, rows);
                grid.appendChild(cell);
            }

            const total = firstDay + daysInMonth;
            const trailing = total % 7 === 0 ? 0 : 7 - (total % 7);
            for (let i = 0; i < trailing; i++) blank();
        }

        // ─── Month loading (per-month fetch) ─────────────────
        function monthKey() {
            return calYear + '-' + String(calMonth + 1).padStart(2, '0');
        }

        function loadMonth() {
            selectedDate = null;
            document.getElementById('day-panel').classList.add('hidden');
            document.getElementById('cal-label').textContent = MONTH_NAMES[calMonth] + ' ' + calYear;
            allData = [];
            lastSig = '';
            isLoadingMonth = true;
            if (currentView === 'list') applyFilters(); else renderCalendar();
            return fetchVouchers(false);
        }

        function prevMonth() {
            calMonth--; if (calMonth < 0) { calMonth = 11; calYear--; }
            loadMonth();
        }
        function nextMonth() {
            calMonth++; if (calMonth > 11) { calMonth = 0; calYear++; }
            loadMonth();
        }
        function goToday() {
            const now = new Date();
            calYear = now.getFullYear(); calMonth = now.getMonth();
            loadMonth();
        }

        function openDayPanel(dateStr, rows, keepScroll = false) {
            selectedDate = dateStr;
            renderCalendar();
            const panel = document.getElementById('day-panel');
            const d = new Date(dateStr + 'T00:00:00');
            document.getElementById('day-panel-title').textContent =
                d.toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            const sum = rows.reduce((s, r) => s + rowTotal(r), 0);
            document.getElementById('day-panel-sub').textContent = rows.length
                ? `${rows.length} voucher${rows.length !== 1 ? 's' : ''} · ₱ ${fmtMoney(sum)}`
                : 'No vouchers';
            document.getElementById('day-vouchers-cards').innerHTML = rows.length
                ? rows.map(r => voucherRowHtml(r)).join('')
                : emptyHtml('No vouchers on this day.');
            const wasHidden = panel.classList.contains('hidden');
            panel.classList.remove('hidden');
            if (wasHidden && !keepScroll) setTimeout(() => panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 50);
        }

        function closeDayPanel() {
            selectedDate = null;
            document.getElementById('day-panel').classList.add('hidden');
            renderCalendar();
        }

        // ─── Dates Dropdown (calendar) ───────────────────────
        function buildDatesDropdown(force = false) {
            const params = new URLSearchParams();
            if (isCustoAssistant) params.set('mine', '1');
            if (force) params.set('_', Date.now());
            document.getElementById('dates-list').innerHTML =
                '<li class="px-4 py-4 text-xs text-gray-400 text-center"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Loading...</li>';
            return fetch('<?= BASE_URL ?>/fetchreceiveddates?' + params.toString())
                .then(r => r.json())
                .then(rows => {
                    allDatesCache = Array.isArray(rows) ? rows : [];
                    datesLoaded = true;
                    document.getElementById('dates-dropdown-count').textContent =
                        allDatesCache.length + ' date' + (allDatesCache.length !== 1 ? 's' : '');
                    renderDatesList(allDatesCache);
                })
                .catch(() => {
                    document.getElementById('dates-list').innerHTML =
                        '<li class="px-4 py-4 text-xs text-red-400 text-center">Failed to load dates.</li>';
                });
        }

        function renderDatesList(dates) {
            const ul = document.getElementById('dates-list');
            if (!dates.length) {
                ul.innerHTML = '<li class="px-4 py-4 text-xs text-gray-400 text-center">No dates found.</li>';
                return;
            }
            ul.innerHTML = dates.map(({ date, voucher_approval, ready_to_release, released, none, total }) => {
                const d = new Date(date + 'T00:00:00');
                const label = d.toLocaleDateString('en-PH', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
                const dots = [
                    none ? '<span class="w-1.5 h-1.5 rounded-full bg-gray-400" title="not submitted"></span>' : '',
                    voucher_approval ? '<span class="w-1.5 h-1.5 rounded-full bg-yellow-400" title="for approval"></span>' : '',
                    ready_to_release ? '<span class="w-1.5 h-1.5 rounded-full bg-blue-500" title="ready to release"></span>' : '',
                    released ? '<span class="w-1.5 h-1.5 rounded-full bg-green-500" title="released"></span>' : '',
                ].join('');
                return `
                <li class="flex items-center justify-between gap-3 px-4 py-2.5 hover:bg-amber-50 cursor-pointer transition group"
                    data-date="${escHtml(date)}" onclick="jumpToDate(this.dataset.date)">
                    <div class="flex items-center gap-2 min-w-0">
                        <i class="fa-regular fa-calendar text-gray-300 group-hover:text-amber-600 transition text-xs"></i>
                        <span class="text-xs font-medium text-gray-700 truncate">${escHtml(label)}</span>
                    </div>
                    <div class="flex items-center gap-1 shrink-0">
                        ${dots}
                        <span class="text-[10px] font-bold text-amber-700 bg-amber-50 group-hover:bg-white border border-amber-200 rounded-full px-1.5 py-0.5 ml-1">${Number(total) || 0}</span>
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
            const dd = document.getElementById('dates-dropdown');
            dd.classList.toggle('hidden');
            document.getElementById('dates-search').value = '';
            if (!dd.classList.contains('hidden')) {
                if (!datesLoaded) buildDatesDropdown();
                else renderDatesList(allDatesCache);
            }
        }

        function jumpToDate(dateStr) {
            document.getElementById('dates-dropdown').classList.add('hidden');
            const d = new Date(dateStr + 'T00:00:00');
            calYear = d.getFullYear(); calMonth = d.getMonth();
            loadMonth().then(() => {
                openDayPanel(dateStr, allData.filter(r => r.date_requested?.startsWith(dateStr)));
                document.getElementById('calendar-grid').scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
        }

        // Isara lang ang dropdown kapag nag-click sa LABAS nito
        document.addEventListener('click', e => {
            if (!e.target.closest('#dates-dropdown-wrap')) document.getElementById('dates-dropdown')?.classList.add('hidden');
        });


        // ─── Voucher Modal ───────────────────────────────────
        function viewVoucher(row) {
            currentRow = row;
            const items = row.items ?? [];
            const total = items.reduce((sum, i) => sum + (parseFloat(i.amount) || 0), 0);

            // ── lock state ──
            const isStaff = POSITION === '<?= POSITION_STAFF ?>';
            const isSubmitted = !!row.voucher_status; // may status na = submitted na, dapat locked
            const canEditFields = isStaff && !isSubmitted;
            const defaultPayee = row.voucher_payee || row.requestor_name || '';

            document.getElementById('v-control-no').textContent = row.voucher_control_no ?? row.control_no;
            document.getElementById('v-date').textContent = row.date_requested;
            document.getElementById('v-second-no').textContent = row.voucher_payment_method ?? '—';
            document.getElementById('v-title').textContent = row.voucher_title ?? '';
            document.getElementById('v-payee').value = defaultPayee;
            document.getElementById('v-amount-words').value = numberToWords(total);
            document.getElementById('v-payment-method').value = row.voucher_payment_method ?? '';
            document.getElementById('v-payment-method-m').value = row.voucher_payment_method ?? '';

            // ── apply disabled state ──
            document.getElementById('v-payee').disabled = !canEditFields;
            document.getElementById('v-payee-m').disabled = !canEditFields;
            document.getElementById('v-payment-method').disabled = !canEditFields;
            document.getElementById('v-payment-method-m').disabled = !canEditFields;
            [document.getElementById('v-payee'), document.getElementById('v-payee-m'),
            document.getElementById('v-payment-method'), document.getElementById('v-payment-method-m')]
                .forEach(el => el.classList.toggle('bg-gray-100', !canEditFields));

            // Populate Payment For dropdown (account titles)
            fetch('<?= BASE_URL ?>/fetchpettycashaccounttitles')
                .then(r => r.json())
                .then(titles => {
                    const sel = document.getElementById('v-purpose');
                    const selM = document.getElementById('v-purpose-m');
                    sel.innerHTML = '<option value="">— Select —</option>';
                    selM.innerHTML = '<option value="">— Select —</option>';
                    titles.forEach(d => {
                        const o = `<option value="${d.title}">${d.title}</option>`;
                        sel.insertAdjacentHTML('beforeend', o);
                        selM.insertAdjacentHTML('beforeend', o);
                    });
                    sel.value = row.voucher_purpose ?? '';
                    selM.value = row.voucher_purpose ?? '';

                    // ── lock the dropdown too ──
                    sel.disabled = !canEditFields;
                    selM.disabled = !canEditFields;
                    sel.classList.toggle('bg-gray-100', !canEditFields);
                    selM.classList.toggle('bg-gray-100', !canEditFields);
                });


            document.getElementById('v-total').textContent = 'PhP ' + total.toLocaleString('en-PH', { minimumFractionDigits: 2 });

            // Prepared — may signature
            const prepSig = row.prepared_signature ?? '';
            document.getElementById('v-prepared').innerHTML = prepSig
                ? `<span class="relative inline-block">
    <img src="${prepSig}" 
         style="position:absolute; bottom:-50px; left:80px; transform:translateX(-50%); 
                height:90px; max-width:160px; object-fit:contain; z-index:10; pointer-events:none;">
    ${row.prepared_name ?? ''}
   </span>`
                : (row.prepared_name ?? '');
            document.getElementById('v-prepared-at').textContent = row.prepared_at
                ? new Date(row.prepared_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '';

            // Approver — may signature (2nd column na ngayon)
            const apprSig = row.approver_signature ?? '';
            document.getElementById('v-approver').innerHTML = apprSig
                ? `<span class="relative inline-block">
    <img src="${apprSig}" 
         style="position:absolute; bottom:-50px; left:80px; transform:translateX(-50%); 
                height:90px; max-width:160px; object-fit:contain; z-index:10; pointer-events:none;">
    ${row.approver_name ?? ''}
   </span>`
                : (row.approver_name ?? '');
            document.getElementById('v-approved-at').textContent = row.approver_name && row.approved_at
                ? new Date(row.approved_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '';

            // Released — custodian assistant (3rd column, pumalit sa Certified)
            const relSig = row.released_signature ?? '';
            document.getElementById('v-released').innerHTML = relSig
                ? `<span class="relative inline-block">
    <img src="${relSig}" 
         style="position:absolute; bottom:-50px; left:80px; transform:translateX(-50%); 
                height:90px; max-width:160px; object-fit:contain; z-index:10; pointer-events:none;">
    ${row.released_name ?? ''}
   </span>`
                : (row.released_name ?? '');
            document.getElementById('v-released-at').textContent = row.released_name && row.released_at
                ? new Date(row.released_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '';

            const receiverName = row.manual_receiver_name || row.receiver_name || '';
            const receivedAt = row.manual_receiver_date
                ? new Date(row.manual_receiver_date).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
                : row.received_at
                    ? new Date(row.received_at.replace(' ', 'T')).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
                    : '';
            document.getElementById('v-receiver').textContent = receiverName;
            document.getElementById('v-received-at').textContent = receivedAt;

            // Items
            let rows = '';
            let filled = 0;
            items.forEach(item => {
                if (!item.description) return;
                filled++;
                const amt = parseFloat(item.amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });
                rows += `
<tr class="border-t border-gray-200">
<td class="px-3 py-2 text-center text-xs text-gray-400 border-r border-gray-200">${filled}</td>
<td class="px-4 py-2 border-r border-gray-200 text-sm">${item.description}${item.purpose ? ' — ' + item.purpose : ''}</td>
<td class="px-4 py-2 text-right font-mono text-sm">${amt}</td>
</tr>`;
            });
            for (let e = filled; e < 5; e++) {
                rows += `
<tr class="border-t border-gray-200">
<td class="px-3 py-2 text-center text-xs text-gray-300 border-r border-gray-200">${e + 1}</td>
<td class="px-4 py-2 border-r border-gray-200" style="height:28px;"></td>
<td class="px-4 py-2"></td>
</tr>`;
            }
            document.getElementById('v-items-tbody').innerHTML = rows;

            const footerBtns = document.getElementById('v-footer-btns');
            const closeBtn = `<button onclick="closeVoucherModal()" class="text-sm text-gray-500 hover:text-gray-700 font-medium px-4 py-2 rounded transition-all border border-gray-200">Close</button>`;

            if (!row.budget_received_by) {
                footerBtns.innerHTML = closeBtn + `
<span class="flex items-center gap-2 text-xs text-gray-400 px-4 py-2 font-medium bg-gray-50 rounded-lg border border-gray-200">
<i class="fa-solid fa-lock text-gray-300"></i>
Waiting for staff to mark as received
</span>`;
            } else if (!row.voucher_status) {
                // Submit Voucher — STAFF lang
                if (isStaff) {
                    footerBtns.innerHTML = closeBtn + `
<button onclick="confirmSubmit()"
class="flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-white text-xs font-semibold px-4 py-2 rounded-lg transition-all">
<i class="fa-solid fa-paper-plane mr-1"></i>Submit Voucher
</button>`;
                } else {
                    footerBtns.innerHTML = closeBtn + `
<span class="flex items-center gap-2 text-xs text-gray-400 px-4 py-2 font-medium bg-gray-50 rounded-lg border border-gray-200">
<i class="fa-solid fa-lock text-gray-300"></i>
Only staff can submit this voucher
</span>`;
                }
            } else if (row.voucher_status === 'ready_to_release') {
                // Release — CUSTODIAN ASSISTANT lang
                if (isCustoAssistant) {
                    footerBtns.innerHTML = closeBtn + `
<div class="flex items-center gap-2">
<input type="text" id="manual-receiver-name" placeholder="Receiver name (optional)"
    class="border border-gray-200 rounded px-3 py-1.5 text-xs outline-none focus:border-orange-400 w-44">
<input type="date" id="manual-receiver-date"
    class="border border-gray-200 rounded px-3 py-1.5 text-xs outline-none focus:border-orange-400 w-36">
<button onclick="releaseVoucher(${row.voucher_id})"
    class="flex items-center gap-2 bg-green-500 hover:bg-green-600 text-white text-xs font-semibold px-4 py-2 rounded-lg transition-all whitespace-nowrap">
    <i class="fa-solid fa-check mr-1"></i>Release Cash Voucher
</button>
</div>`;
                } else {
                    footerBtns.innerHTML = closeBtn + `
<span class="text-xs text-blue-500 px-4 py-2 font-medium flex items-center gap-2 bg-blue-50 rounded-lg border border-blue-200">
<i class="fa-solid fa-box"></i>Ready to Release — waiting for custodian assistant
</span>`;
                }
            } else {
                footerBtns.innerHTML = closeBtn + `
<span class="text-xs text-gray-400 px-4 py-2 font-medium flex items-center gap-2">
${row.voucher_status === 'released'
                        ? `<button onclick="printVoucher(${JSON.stringify(row).replace(/"/g, '&quot;')})"
           class="flex items-center gap-2 bg-gray-800 hover:bg-gray-900 text-white text-xs font-semibold px-4 py-2 rounded-lg transition-all">
           <i class="fa-solid fa-print mr-1"></i>Print Voucher
       </button>`
                        : '<i class="fa-solid fa-clock text-yellow-500 mr-1"></i>Waiting for approval'}
</span>`;
            }

            // ── Sync mobile fields ──
            document.getElementById('v-title-m').textContent = row.voucher_title ?? '';
            document.getElementById('v-control-no-m').textContent = row.voucher_control_no ?? row.control_no;
            document.getElementById('v-date-m').textContent = row.date_requested;
            document.getElementById('v-second-no-m').textContent = row.voucher_payment_method ?? '—';
            document.getElementById('v-payee-m').value = defaultPayee;
            document.getElementById('v-amount-words-m').value = numberToWords(total);
            document.getElementById('v-total-m').textContent = 'PhP ' + total.toLocaleString('en-PH', { minimumFractionDigits: 2 });
            document.getElementById('v-prepared-m').textContent = row.prepared_name ?? '';
            document.getElementById('v-prepared-at-m').textContent = document.getElementById('v-prepared-at').textContent;
            document.getElementById('v-approver-m').textContent = row.approver_name ?? '';
            document.getElementById('v-approved-at-m').textContent = document.getElementById('v-approved-at').textContent;
            document.getElementById('v-released-m').textContent = row.released_name ?? '';
            document.getElementById('v-released-at-m').textContent = document.getElementById('v-released-at').textContent;
            document.getElementById('v-receiver-m').textContent = receiverName;
            document.getElementById('v-received-at-m').textContent = receivedAt;

            // Mobile items
            const mobileItems = document.getElementById('v-items-mobile');
            let mobileRows = '';
            let mFilled = 0;
            (row.items ?? []).forEach(item => {
                if (!item.description) return;
                mFilled++;
                const amt = parseFloat(item.amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });
                mobileRows += `
<div class="grid grid-cols-[32px_1fr_80px] px-3 py-2 items-start">
<span class="text-[10px] text-gray-400 text-center">${mFilled}</span>
<span class="text-xs text-gray-700">${item.description}${item.purpose ? ' — ' + item.purpose : ''}</span>
<span class="text-xs font-mono text-right text-gray-800">${amt}</span>
</div>`;
            });
            if (!mFilled) mobileRows = `<div class="px-3 py-3 text-xs text-gray-400 text-center">No items.</div>`;
            mobileItems.innerHTML = mobileRows;

            const mFooter = document.getElementById('v-footer-btns-m');
            const mCloseBtn = `<button onclick="closeVoucherModal()" class="w-full text-sm text-gray-500 font-medium py-2.5 rounded-xl border border-gray-200 bg-white">Close</button>`;

            if (!row.budget_received_by) {
                mFooter.innerHTML = `
<span class="flex items-center justify-center gap-2 text-xs text-gray-400 py-2 font-medium bg-white rounded-xl border border-gray-200">
<i class="fa-solid fa-lock text-gray-300"></i> Waiting for staff to mark as received
</span>` + mCloseBtn;
            } else if (!row.voucher_status) {
                if (isStaff) {
                    mFooter.innerHTML = `
<button onclick="confirmSubmit()"
class="w-full flex items-center justify-center gap-2 bg-orange-500 text-white text-sm font-semibold py-2.5 rounded-xl transition-all">
<i class="fa-solid fa-paper-plane"></i>Submit Voucher
</button>` + mCloseBtn;
                } else {
                    mFooter.innerHTML = `
<span class="flex items-center justify-center gap-2 text-xs text-gray-400 py-2 font-medium bg-white rounded-xl border border-gray-200">
<i class="fa-solid fa-lock text-gray-300"></i> Only staff can submit this voucher
</span>` + mCloseBtn;
                }
            } else if (row.voucher_status === 'ready_to_release') {
                if (isCustoAssistant) {
                    mFooter.innerHTML = `
<input type="text" id="manual-receiver-name-m" placeholder="Receiver name (optional)"
class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:border-orange-400">
<input type="date" id="manual-receiver-date-m"
class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm outline-none focus:border-orange-400">
<button onclick="releaseVoucherMobile(${row.voucher_id})"
class="w-full flex items-center justify-center gap-2 bg-green-500 text-white text-sm font-semibold py-2.5 rounded-xl transition-all">
<i class="fa-solid fa-check"></i>Release Cash Voucher
</button>` + mCloseBtn;
                } else {
                    mFooter.innerHTML = `
<span class="flex items-center justify-center gap-2 text-xs text-blue-600 py-2.5 font-medium bg-blue-50 rounded-xl border border-blue-200">
<i class="fa-solid fa-box"></i>Ready to Release
</span>` + mCloseBtn;
                }
            } else if (row.voucher_status === 'released') {
                mFooter.innerHTML = `
<button onclick="printVoucher(${JSON.stringify(row).replace(/"/g, '&quot;')})"
class="w-full flex items-center justify-center gap-2 bg-gray-800 text-white text-sm font-semibold py-2.5 rounded-xl transition-all">
<i class="fa-solid fa-print"></i>Print Voucher
</button>` + mCloseBtn;
            } else {
                mFooter.innerHTML = `
<span class="flex items-center justify-center gap-2 text-xs text-yellow-600 py-2 font-medium bg-yellow-50 rounded-xl border border-yellow-200">
<i class="fa-solid fa-clock"></i> Waiting for approval
</span>` + mCloseBtn;
            }

            document.getElementById('voucher-modal').classList.remove('hidden');
        }

        function closeVoucherModal() {
            document.getElementById('voucher-modal').classList.add('hidden');
            currentRow = null;
        }

        function confirmSubmit() {
            const payee = (document.getElementById('v-payee').value.trim()
                || document.getElementById('v-payee-m').value.trim());
            if (!payee) {
                document.getElementById('v-payee').classList.add('border-red-400');
                document.getElementById('v-payee-m').classList.add('border-red-400');
                return;
            }
            document.getElementById('v-payee').classList.remove('border-red-400');
            document.getElementById('v-payee-m').classList.remove('border-red-400');
            openConfirmModal();
        }

        function openConfirmModal() {
            const row = currentRow;
            const items = row.items ?? [];
            const total = items.reduce((sum, i) => sum + (parseFloat(i.amount) || 0), 0);
            const payee = document.getElementById('v-payee').value.trim() || document.getElementById('v-payee-m').value.trim();
            const purpose = document.getElementById('v-purpose').value.trim() || document.getElementById('v-purpose-m').value.trim();

            // Populate voucher summary
            document.getElementById('conf-voucher-no').textContent = row.voucher_control_no ?? row.control_no ?? '—';
            document.getElementById('conf-date').textContent = row.date_requested ?? '—';
            document.getElementById('conf-payee').textContent = payee;
            document.getElementById('conf-purpose').textContent = purpose;
            document.getElementById('conf-total').textContent = 'PhP ' + total.toLocaleString('en-PH', { minimumFractionDigits: 2 });

            // Populate expense preview (static fields from voucher)
            const particularsPreview = (items ?? [])
                .filter(i => i.description)
                .map(i => i.description + (i.purpose ? ' — ' + i.purpose : ''))
                .join(', ');
            document.getElementById('prev-particulars').textContent = particularsPreview || purpose || '—';
            document.getElementById('prev-amount').textContent = 'PhP ' + total.toLocaleString('en-PH', { minimumFractionDigits: 2 });
            const paymentMethod = document.getElementById('v-payment-method').value.trim() || document.getElementById('v-payment-method-m').value.trim();
            document.getElementById('prev-mode').textContent = paymentMethod || '—';
            document.getElementById('prev-reference').textContent = row.voucher_control_no ?? row.control_no ?? '—';
            document.getElementById('prev-payment-date').textContent = row.date_requested ?? '—';
            document.getElementById('prev-title').textContent = '—'; // updates when title is selected

            // Reset dropdowns
            document.getElementById('conf-second-no-select').value = '';
            document.getElementById('conf-project-select').value = '';


            // Fetch departments
            fetch('<?= BASE_URL ?>/fetchpettycashdepartment')
                .then(r => r.json())
                .then(data => {
                    const sel = document.getElementById('conf-second-no-select');
                    sel.innerHTML = '<option value="">— Select Department —</option>';
                    data.forEach(d => {
                        const opt = document.createElement('option');
                        opt.value = d.name;
                        opt.textContent = d.name;
                        sel.appendChild(opt);
                    });
                });

            // Fetch projects
            fetch('<?= BASE_URL ?>/fetchprojects')
                .then(r => r.json())
                .then(data => {
                    window._allProjects = data; // store for validation
                    const sel = document.getElementById('conf-project-select');
                    sel.innerHTML = '<option value="">— Select Project —</option>';
                    data.forEach(p => {
                        const opt = document.createElement('option');
                        opt.value = p.id;
                        opt.textContent = p.project_name + (p.reference_no ? '  [' + p.reference_no + ']' : '');
                        sel.appendChild(opt);
                    });

                    // Auto-select if request_reference matches a project reference_no
                    if (row.request_reference) {
                        const match = data.find(p => p.reference_no === row.request_reference);
                        if (match) sel.value = match.id;
                    }

                    // Show notice if nothing is selected after auto-select attempt
                    updateProjectNotice();
                });

            document.getElementById('voucher-confirm-modal').classList.remove('hidden');
        }

        function toggleNewProject(forceOpen) {
            const box = document.getElementById('new-project-box');
            const open = forceOpen ?? box.classList.contains('hidden');
            box.classList.toggle('hidden', !open);
            if (open) {
                const input = document.getElementById('new-project-name');
                // I-prefill ng napiling department, dahil kailangang magkapareho ang pangalan nila
                if (!input.value) input.value = document.getElementById('conf-second-no-select').value || '';
                input.focus();
            }
        }

        async function createProjectInline() {
            const input = document.getElementById('new-project-name');
            const btn = document.getElementById('btn-create-project');
            const projectSel = document.getElementById('conf-project-select');
            const name = input.value.trim().toUpperCase();

            if (!name) {
                input.classList.add('border-red-400');
                input.focus();
                return;
            }
            input.classList.remove('border-red-400');

            // Kung existing na, piliin na lang
            const existing = (window._allProjects ?? []).find(p => (p.project_name ?? '').toUpperCase() === name);
            if (existing) {
                projectSel.value = existing.id;
                toggleNewProject(false);
                updateProjectNotice();
                showToast('Project already exists — selected it.');
                return;
            }

            const originalHTML = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-[10px]"></i> Creating...';

            try {
                // 1. I-create ang project (parehong payload ng Project Monitor)
                const res = await fetch('<?= BASE_URL ?>/saveproject', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        id: null,
                        project_name: name,
                        job_order: '', project_scope: '', purchase_order: '',
                        client_name: '', notice_to_proceed: '', contract_amount: '',
                        billing_order_1: '', sales_person: '', billing_order_2: '',
                        address: '', status: 'New'
                    })
                });
                const data = await res.json();
                if (!data.success) {
                    showToast(data.error ?? 'Failed to create project.', 'error');
                    return;
                }

                // 2. Idagdag din sa listahan ng Project Names (best-effort, hindi hinihintay)
                fetch('<?= BASE_URL ?>/saveprojectname', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name })
                }).catch(() => { });

                // 3. I-refresh ang dropdown at piliin ang bagong project
                const list = await (await fetch('<?= BASE_URL ?>/fetchprojects')).json();
                window._allProjects = list;
                projectSel.innerHTML = '<option value="">— Select Project —</option>';
                list.forEach(p => {
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = p.project_name + (p.reference_no ? '  [' + p.reference_no + ']' : '');
                    projectSel.appendChild(opt);
                });

                const created = list
                    .filter(p => (p.project_name ?? '').toUpperCase() === name)
                    .sort((a, b) => b.id - a.id)[0];
                if (created) projectSel.value = created.id;

                projectSel.classList.remove('border-red-400');
                input.value = '';
                toggleNewProject(false);
                updateProjectNotice();
                showToast('Project created & selected!');
            } catch (err) {
                showToast('Network error. Please try again.', 'error');
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalHTML;
            }
        }

        function updateProjectNotice() {
            const projectSel = document.getElementById('conf-project-select');
            const deptSel = document.getElementById('conf-second-no-select');
            let notice = document.getElementById('conf-project-notice');
            if (!notice) {
                notice = document.createElement('p');
                notice.id = 'conf-project-notice';
                notice.className = 'text-[10px] text-amber-500 mt-1 flex items-center gap-1';
                notice.innerHTML = '<i class="fa-solid fa-triangle-exclamation text-[9px]"></i> This department has no linked project. Please select one or proceed without linking.';
                projectSel.parentNode.appendChild(notice);
            }
            // Show notice only when a department is selected BUT no project is chosen
            const hasDept = deptSel.value !== '';
            const hasProject = projectSel.value !== '';
            notice.style.display = (hasDept && !hasProject) ? 'flex' : 'none';
        }

        // Live-update the preview title when dropdown changes
        document.addEventListener('change', function (e) {
            if (e.target.id === 'conf-project-select') {
                updateProjectNotice();
                if (e.target.value) e.target.classList.remove('border-red-400');
            }
            if (e.target.id === 'conf-second-no-select') {
                updateProjectNotice();
                e.target.classList.remove('border-red-400');
            }
        });

        function closeConfirmModal() {
            document.getElementById('voucher-confirm-modal').classList.add('hidden');
            // Reset submit button state
            const submitBtn = document.querySelector('#voucher-confirm-modal button[onclick="submitWithTitle()"]');
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane text-xs"></i>Submit & Save Expense';
            }
        }

        async function submitWithTitle() {
            const secondNo = document.getElementById('conf-second-no-select').value.trim();
            const projectId = document.getElementById('conf-project-select').value;
            const deptVal = document.getElementById('conf-second-no-select').value;
            const projectSel = document.getElementById('conf-project-select');

            if (deptVal && !projectId) {
                projectSel.classList.add('border-red-400');
                projectSel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                showToast('Please select a project for the selected department.', 'error');
                return;
            }

            // Check if department matches project_name of selected project
            if (deptVal && projectId) {
                const projects = window._allProjects ?? [];
                const selectedProject = projects.find(p => p.id == projectId);
                if (selectedProject && selectedProject.project_name.toUpperCase() !== deptVal.toUpperCase()) {
                    projectSel.classList.add('border-red-400');
                    document.getElementById('conf-second-no-select').classList.add('border-red-400');
                    showToast(`Department "${deptVal}" does not match project "${selectedProject.project_name}". Please select the correct project.`, 'error');
                    return;
                }
            }

            projectSel.classList.remove('border-red-400');
            document.getElementById('conf-second-no-select').classList.remove('border-red-400');

            const row = currentRow;
            const items = row.items ?? [];
            const total = items.reduce((sum, i) => sum + (parseFloat(i.amount) || 0), 0);
            const payee = document.getElementById('v-payee').value.trim() || document.getElementById('v-payee-m').value.trim();
            const purpose = document.getElementById('v-purpose').value.trim() || document.getElementById('v-purpose-m').value.trim();
            const paymentMethod = document.getElementById('v-payment-method').value.trim() || document.getElementById('v-payment-method-m').value.trim();
            const submitBtn = document.querySelector('#voucher-confirm-modal button[onclick="submitWithTitle()"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin text-xs"></i> Submitting...';

            try {
                // 1. Submit the voucher
                const voucherRes = await fetch('<?= BASE_URL ?>/submitrequestvoucher', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        request_id: row.id,
                        payee,
                        purpose,
                        payment_method: paymentMethod,
                        second_no: secondNo
                    })
                });
                const voucherData = await voucherRes.json();

                if (!voucherData.success) {
                    showToast(voucherData.error ?? 'Failed to submit voucher.', 'error');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane text-xs"></i>Submit & Save Expense';
                    return;
                }

                // 2. Save expense entry only if a project was selected
                closeConfirmModal();
                closeVoucherModal();

                if (projectId) {
                    const voucherNo = row.voucher_control_no ?? row.control_no ?? '';
                    // Build particulars from actual items
                    const particularsText = (row.items ?? [])
                        .filter(i => i.description)
                        .map(i => i.description + (i.purpose ? ' — ' + i.purpose : ''))
                        .join(', ');

                    const expenseRes = await fetch('<?= BASE_URL ?>/saveprojectexpense', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            id: null,
                            project_id: parseInt(projectId),
                            title: purpose,
                            particulars: particularsText || purpose,
                            amount: total,
                            mode_of_payment: paymentMethod,
                            payment_date: row.date_requested ?? '',
                            reference: voucherNo,
                            remarks: ''
                        })
                    });
                    const expenseData = await expenseRes.json();
                    showToast(expenseData.success ? 'Voucher submitted & expense saved!' : 'Voucher submitted, but expense save failed.', expenseData.success ? 'success' : 'error');
                } else {
                    showToast('Voucher submitted successfully!');
                }
                allData = [];
                datesLoaded = false;      // i-refresh ang dates dropdown sa susunod na bukas
                fetchVouchers(true);

            } catch (err) {
                showToast('Network error. Please try again.', 'error');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane text-xs"></i>Submit & Save Expense';
            }
        }

        function releaseVoucher(voucherId) {
            const manualName = document.getElementById('manual-receiver-name')?.value.trim() ?? '';
            const manualDate = document.getElementById('manual-receiver-date')?.value ?? '';
            const btn = event.currentTarget;
            btn.disabled = true;
            btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-1"></i>Releasing...`;

            fetch('<?= BASE_URL ?>/releasevoucher', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ voucher_id: voucherId, manual_name: manualName, manual_date: manualDate })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        closeVoucherModal();
                        showToast('Cash voucher released!');
                        allData = [];
                        datesLoaded = false;
                        fetchVouchers(true);
                    } else {
                        showToast(data.error ?? 'Failed to release.', 'error');
                        btn.disabled = false;
                        btn.innerHTML = `<i class="fa-solid fa-check mr-1"></i>Release Cash Voucher`;
                    }
                })
                .catch(() => {
                    showToast('Network error. Please try again.', 'error');
                    btn.disabled = false;
                    btn.innerHTML = `<i class="fa-solid fa-check mr-1"></i>Release Cash Voucher`;
                });
        }

        function releaseVoucherMobile(voucherId) {
            const manualName = document.getElementById('manual-receiver-name-m')?.value.trim() ?? '';
            const manualDate = document.getElementById('manual-receiver-date-m')?.value ?? '';
            const btn = event.currentTarget;
            btn.disabled = true;
            btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin mr-1"></i>Releasing...`;

            fetch('<?= BASE_URL ?>/releasevoucher', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ voucher_id: voucherId, manual_name: manualName, manual_date: manualDate })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        closeVoucherModal();
                        showToast('Cash voucher released!');
                        allData = [];
                        datesLoaded = false;
                        fetchVouchers(true);
                    } else {
                        showToast(data.error ?? 'Failed to release.', 'error');
                        btn.disabled = false;
                        btn.innerHTML = `<i class="fa-solid fa-check mr-1"></i>Release Cash Voucher`;
                    }
                })
                .catch(() => {
                    showToast('Network error. Please try again.', 'error');
                    btn.disabled = false;
                    btn.innerHTML = `<i class="fa-solid fa-check mr-1"></i>Release Cash Voucher`;
                });
        }

        function printVoucher(row) {
            const items = row.items ?? [];
            let total = 0, itemRows = '', filled = 0;
            items.forEach(item => {
                if (!item.description) return;
                filled++;
                const amt = parseFloat(item.amount || 0);
                total += amt;
                itemRows += `<tr>
        <td style="text-align:center;border:1px solid #ccc;padding:5px;font-size:11px;">${filled}</td>
        <td style="border:1px solid #ccc;padding:5px;font-size:11px;">${item.description}${item.purpose ? ' — ' + item.purpose : ''}</td>
        <td style="text-align:right;border:1px solid #ccc;padding:5px;font-family:monospace;font-size:11px;">${amt.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</td>
    </tr>`;
            });
            for (let e = filled; e < 5; e++) {
                itemRows += `<tr>
        <td style="text-align:center;border:1px solid #ccc;padding:5px;color:#ccc;font-size:11px;">${e + 1}</td>
        <td style="border:1px solid #ccc;padding:5px;height:24px;"></td>
        <td style="border:1px solid #ccc;padding:5px;"></td>
    </tr>`;
            }

            const preparedAt = row.prepared_at ? new Date(row.prepared_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) : '';
            const approvedAt = row.approved_at ? new Date(row.approved_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) : '';
            const releasedAt = row.released_at ? new Date(row.released_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) : '';
            const receiverName = row.manual_receiver_name || row.receiver_name || '';
            const receivedAt = row.manual_receiver_date
                ? new Date(row.manual_receiver_date).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
                : row.received_at
                    ? new Date(row.received_at.replace(' ', 'T')).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
                    : '';

            const html = `<!DOCTYPE html><html><head><meta charset="UTF-8">
<title>Cash Voucher - ${row.voucher_control_no ?? row.control_no}</title>
<style>
    *{box-sizing:border-box;}
    body{font-family:Arial,sans-serif;font-size:11px;margin:0;padding:15px;}
    table{width:100%;border-collapse:collapse;}
    .orange-bg{background:#f97316;color:white;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
    @page{size:A4 portrait;margin:1cm;}
</style></head><body>
<div style="border:2px solid #f97316;border-radius:2px;">

    <!-- Header -->
    <div style="display:grid;grid-template-columns:1fr auto;border-bottom:2px solid #f97316;">
        <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;">
            <img src="<?= BASE_URL ?>/icon/logo.webp" style="width:48px;height:48px;object-fit:contain;">
            <div>
                <div style="font-weight:bold;font-size:12px;text-transform:uppercase;">Noblehome Construction Corporation</div>
                <div style="font-size:9px;color:#666;margin-top:2px;">1181 MC Premiere Bldg., EDSA Bldg., EDSA Balintawak Quezon City</div>
                <div style="font-size:9px;color:#666;">noblehomeconsl.ph@gmail.com | Tel. No. 02-88221295 | Cell. No. 0968-591-6544</div>
            </div>
        </div>
        <div style="text-align:right;padding:10px 14px;border-left:1px solid #f97316;">
            <div style="font-weight:bold;font-size:16px;text-transform:uppercase;letter-spacing:2px;">Cash Voucher</div>
            <div style="font-size:9px;color:#888;text-transform:uppercase;margin-bottom:6px;">${row.voucher_title ?? ''}</div>
            <table style="width:auto;margin-left:auto;">
                <tr>
                    <td class="orange-bg" style="font-size:9px;font-weight:bold;padding:3px 10px;text-align:center;border-right:1px solid #ea6c00;">Voucher No.</td>
                    <td class="orange-bg" style="font-size:9px;font-weight:bold;padding:3px 10px;text-align:center;">Date:</td>
                </tr>
                <tr>
                    <td style="border:1px solid #d1d5db;font-family:monospace;font-size:10px;padding:4px 10px;text-align:center;background:#f9fafb;border-right:0;">${row.voucher_control_no ?? row.control_no}</td>
                    <td style="border:1px solid #d1d5db;font-family:monospace;font-size:10px;padding:4px 10px;text-align:center;background:#f9fafb;">${row.date_requested}</td>
                </tr>
            </table>
        </div>
    </div>

    <!-- Payee / Address -->
    <div style="padding:10px 14px;border-bottom:1px solid #e5e7eb;">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:6px;">
            <div style="display:flex;gap:6px;align-items:center;">
                <span style="font-size:9px;font-weight:bold;text-transform:uppercase;letter-spacing:1px;color:#555;width:90px;">Payee</span>
                <span style="color:#aaa;margin-right:4px;">:</span>
                <span style="border-bottom:1px solid #aaa;flex:1;font-size:11px;">${row.voucher_payee ?? ''}</span>
            </div>
            <div style="display:flex;gap:6px;align-items:center;">
                <span style="font-size:9px;font-weight:bold;text-transform:uppercase;letter-spacing:1px;color:#555;white-space:nowrap;">Payment For</span>
                <span style="color:#aaa;margin:0 4px;">:</span>
                <span style="border-bottom:1px solid #aaa;flex:1;font-size:11px;">${row.voucher_purpose ?? ''}</span>
            </div>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div style="display:flex;gap:6px;align-items:center;">
                <span style="font-size:9px;font-weight:bold;text-transform:uppercase;letter-spacing:1px;color:#555;width:90px;">Address</span>
                <span style="color:#aaa;margin-right:4px;">:</span>
                <span style="border-bottom:1px solid #aaa;flex:1;font-size:11px;">${row.voucher_address ?? ''}</span>
            </div>
            <div style="display:flex;gap:6px;align-items:center;">
                <span style="font-size:9px;font-weight:bold;text-transform:uppercase;letter-spacing:1px;color:#555;white-space:nowrap;">Amount in Words</span>
                <span style="color:#aaa;margin:0 4px;">:</span>
                <span style="border-bottom:1px solid #aaa;flex:1;font-size:11px;font-style:italic;">${(() => {
                    // numberToWords inline
                    const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
                    const tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];
                    let amount = total; if (amount === 0) return 'Zero Pesos Only';
                    function cvt(n) { let r = ''; if (n >= 100) { r += ones[Math.floor(n / 100)] + ' Hundred '; n %= 100; } if (n >= 20) { r += tens[Math.floor(n / 10)] + ' '; n %= 10; } if (n > 0) { r += ones[n] + ' '; } return r; }
                    let i = Math.floor(amount), d = Math.round((amount - i) * 100), res = '';
                    if (i >= 1000000) { res += cvt(Math.floor(i / 1000000)) + 'Million '; i %= 1000000; }
                    if (i >= 1000) { res += cvt(Math.floor(i / 1000)) + 'Thousand '; i %= 1000; }
                    if (i > 0) { res += cvt(i); } res += 'Pesos'; if (d > 0) res += ' and ' + d + '/100';
                    return res.trim() + ' Only';
                })()}</span>
            </div>
        </div>
    </div>

    <!-- Particulars -->
    <table style="border-collapse:collapse;">
        <thead>
            <tr class="orange-bg">
                <th style="width:40px;padding:6px;border:1px solid #ea6c00;text-align:center;font-size:10px;">No.</th>
                <th style="padding:6px;border:1px solid #ea6c00;text-align:center;font-size:10px;letter-spacing:3px;">P A R T I C U L A R S</th>
                <th style="width:130px;padding:6px;border:1px solid #ea6c00;text-align:center;font-size:10px;">Amount (P)</th>
            </tr>
        </thead>
        <tbody>${itemRows}</tbody>
        <tfoot>
            <tr style="background:#fff7ed;border-top:2px solid #f97316;">
                <td colspan="3" style="border:1px solid #e5e7eb;padding:0;">
                    <div style="display:flex;justify-content:space-between;padding:6px 12px;">
                        <div style="font-size:9px;font-weight:bold;text-transform:uppercase;color:#888;">
                            Payment Method No.: <span style="font-family:monospace;font-size:10px;color:#374151;">${row.voucher_second_no ?? '—'}</span>
                        </div>
                        <div style="font-weight:bold;font-size:11px;">
                            Total Amount: <span style="font-family:monospace;">${total.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</span>
                        </div>
                    </div>
                </td>
            </tr>
        </tfoot>
    </table>

    <!-- Signature Headers: Prepared → Approved → Released → Received -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;border-top:2px solid #f97316;">
        <div class="orange-bg" style="font-size:9px;font-weight:bold;text-align:center;padding:5px;border-right:1px solid #ea6c00;">Prepared By</div>
        <div class="orange-bg" style="font-size:9px;font-weight:bold;text-align:center;padding:5px;border-right:1px solid #ea6c00;">Approved By</div>
        <div class="orange-bg" style="font-size:9px;font-weight:bold;text-align:center;padding:5px;border-right:1px solid #ea6c00;">Released By</div>
        <div class="orange-bg" style="font-size:9px;font-weight:bold;text-align:center;padding:5px;">Received By</div>
    </div>

<div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;border-bottom:1px solid #e5e7eb;">
<!-- Prepared -->
<div style="padding:10px 8px;border-right:1px solid #e5e7eb;display:flex;flex-direction:column;">
    <div style="height:65px;display:flex;align-items:flex-end;justify-content:center;margin-bottom:4px;">
        ${row.prepared_signature ? `<img src="${row.prepared_signature}" style="max-height:65px;max-width:120px;object-fit:contain;">` : ''}
    </div>
    <div style="border-top:1px solid #999;padding-top:4px;text-align:center;">
        <div style="font-size:10px;font-weight:600;">${row.prepared_name ?? ''}</div>
        <div style="font-size:9px;color:#888;">${preparedAt}</div>
    </div>
</div>
<!-- Approved -->
<div style="padding:10px 8px;border-right:1px solid #e5e7eb;display:flex;flex-direction:column;">
    <div style="height:65px;display:flex;align-items:flex-end;justify-content:center;margin-bottom:4px;">
        ${row.approver_signature ? `<img src="${row.approver_signature}" style="max-height:65px;max-width:120px;object-fit:contain;">` : ''}
    </div>
    <div style="border-top:1px solid #999;padding-top:4px;text-align:center;">
        <div style="font-size:10px;font-weight:600;">${row.approver_name ?? ''}</div>
        <div style="font-size:9px;color:#888;">${approvedAt}</div>
    </div>
</div>
<!-- Released -->
<div style="padding:10px 8px;border-right:1px solid #e5e7eb;display:flex;flex-direction:column;">
    <div style="height:65px;display:flex;align-items:flex-end;justify-content:center;margin-bottom:4px;">
        ${row.released_signature ? `<img src="${row.released_signature}" style="max-height:65px;max-width:120px;object-fit:contain;">` : ''}
    </div>
    <div style="border-top:1px solid #999;padding-top:4px;text-align:center;">
        <div style="font-size:10px;font-weight:600;">${row.released_name ?? ''}</div>
        <div style="font-size:9px;color:#888;">${releasedAt}</div>
    </div>
</div>
<!-- Received -->
<div style="padding:10px 8px;display:flex;flex-direction:column;">
    <div style="height:65px;display:flex;align-items:flex-end;justify-content:center;margin-bottom:4px;">
        <!-- no signature for receiver -->
    </div>
    <div style="border-top:1px solid #999;padding-top:4px;text-align:center;">
        <div style="font-size:10px;font-weight:600;">${receiverName}</div>
        <div style="font-size:9px;color:#888;">${receivedAt}</div>
    </div>
</div>
</div>

    <!-- Footer note -->
    <div style="padding:8px 14px;">
        <span style="font-size:9px;font-style:italic;color:#888;">Received the above stated amount in full settlement.</span>
    </div>

</div>
<script>window.onload=function(){window.print();window.onafterprint=function(){window.close();};};<\/script>
</body></html>`;

            const w = window.open('', '_blank');
            w.document.write(html);
            w.document.close();
        }

        // ─── Ready to release (lahat ng buwan) ───────────────
        // Gamit ang fetchreceiveddates para malaman kung aling buwan ang may ready_to_release,
        // saka kinukuha ang mga buwan na iyon lang (walang bagong endpoint na kailangan).
        function loadReleaseQueue() {
            const seq = ++releaseSeq;
            releaseLastAt = Date.now();
            const p = new URLSearchParams({ _: Date.now() });
            if (isCustoAssistant) p.set('mine', '1');
            return fetch('<?= BASE_URL ?>/fetchreceiveddates?' + p.toString(), { cache: 'no-store' })
                .then(r => r.json())
                .then(dates => {
                    const months = [...new Set((Array.isArray(dates) ? dates : [])
                        .filter(d => Number(d.ready_to_release) > 0)
                        .map(d => String(d.date).slice(0, 7)))].sort().reverse();
                    return Promise.all(months.map(m => {
                        const q = new URLSearchParams({ month: m, _: Date.now() });
                        if (isCustoAssistant) q.set('mine', '1');
                        return fetch('<?= BASE_URL ?>/fetchreceived?' + q.toString(), { cache: 'no-store' })
                            .then(r => r.json()).catch(() => []);
                    }));
                })
                .then(lists => {
                    if (seq !== releaseSeq) return;
                    releaseQueue = lists.flat().filter(r => r && r.voucher_status === 'ready_to_release')
                        .sort((a, b) => String(a.date_requested ?? '').localeCompare(String(b.date_requested ?? ''))); // pinakaluma muna
                    releaseLoaded = true;
                    if (currentView === 'list') applyFilters(); else updateStats();
                })
                .catch(err => {
                    console.error('Release queue error:', err);
                    if (seq !== releaseSeq) return;
                    releaseLoaded = true; // gamitin ang data ng buwan na lang
                    releaseQueue = allData.filter(r => r.voucher_status === 'ready_to_release');
                    if (currentView === 'list') applyFilters(); else updateStats();
                });
        }

        // ─── Fetch (per month) ───────────────────────────────
        let _highlightDone = false;

        function fetchVouchers(force = false) {
            const mk = monthKey();
            const params = new URLSearchParams({ month: mk });
            if (isCustoAssistant) params.set('mine', '1');   // server na ang nagfi-filter
            if (force) params.set('_', Date.now());

            return fetch('<?= BASE_URL ?>/fetchreceived?' + params.toString(), force ? { cache: 'no-store' } : {})
                .then(res => res.json())
                .then(data => {
                    if (mk !== monthKey()) return; // lumipat na ng buwan habang naglo-load
                    if (!Array.isArray(data)) data = [];
                    const wasLoading = isLoadingMonth;
                    isLoadingMonth = false;

                    const sig = mk + '|' + data.map(r =>
                        r.id + '|' + (r.voucher_id ?? '') + '|' + (r.voucher_status ?? '') + '|' +
                        (r.approved_at ?? '') + '|' + (r.released_at ?? '') + '|' + (r.manual_receiver_name ?? '')
                    ).join(',');
                    document.getElementById('last-updated').textContent = 'Updated ' + new Date().toLocaleTimeString('en-PH');
                    if (sig === lastSig && !force && !wasLoading) return;
                    lastSig = sig;

                    allData = data; // buwan lang ang laman nito
                    // may nagbago (hal. na-release) → i-refresh din ang listahan ng lahat ng buwan
                    if (force || !wasLoading || !releaseLoaded) loadReleaseQueue();

                    if (currentView === 'list') {
                        applyFilters();
                    } else {
                        renderCalendar();
                        if (selectedDate) {
                            openDayPanel(selectedDate, allData.filter(r => r.date_requested?.startsWith(selectedDate)), true);
                        }
                    }

                    if (!_highlightDone) {
                        _highlightDone = true;
                        checkHighlight();
                    }
                })
                .catch(err => {
                    console.error('Fetch error:', err);
                    if (mk === monthKey() && isLoadingMonth) {
                        isLoadingMonth = false;
                        document.getElementById('voucher-cards').innerHTML =
                            emptyHtml('Could not load vouchers.', 'Check your connection, then refresh the page.');
                    }
                });
        }

        // ─── Toast ───────────────────────────────────────────
        function showToast(message, type = 'success') {
            const colors = { success: 'bg-green-600', error: 'bg-red-500', info: 'bg-blue-600' };
            const toast = document.createElement('div');
            toast.className = `fixed bottom-6 left-4 right-4 sm:left-auto sm:right-6 z-[999] flex items-center gap-3 ${colors[type] ?? colors.success} text-white text-sm font-semibold px-5 py-3 rounded-xl shadow-lg transition-all duration-300 opacity-0 translate-y-2`;
            toast.innerHTML = `<i class="fa-solid ${type === 'error' ? 'fa-circle-xmark' : 'fa-circle-check'}"></i> ${escHtml(message)}`;
            document.body.appendChild(toast);
            requestAnimationFrame(() => toast.classList.remove('opacity-0', 'translate-y-2'));
            setTimeout(() => {
                toast.classList.add('opacity-0', 'translate-y-2');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // ─── Highlight (galing sa notification link) ─────────
        function checkHighlight() {
            const highlightId = new URLSearchParams(window.location.search).get('highlight');
            if (!highlightId) return;
            let tries = 0;
            const interval = setInterval(() => {
                tries++;
                const row = document.querySelector(`#voucher-cards .cv-row[data-id="${CSS.escape(highlightId)}"]`);
                if (row) {
                    clearInterval(interval);
                    const container = document.getElementById('voucher-cards');
                    container.scrollTop = row.offsetTop - container.offsetTop - 50;
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    row.classList.add('cv-row-flash');
                    setTimeout(() => row.classList.remove('cv-row-flash'), 3200);
                }
                if (tries >= 25) clearInterval(interval);
            }, 200);
        }

        // ─── ESC to close modals ─────────────────────────────
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;
            const confirmModal = document.getElementById('voucher-confirm-modal');
            const voucherModal = document.getElementById('voucher-modal');
            if (!confirmModal.classList.contains('hidden')) { closeConfirmModal(); return; }
            if (!voucherModal.classList.contains('hidden')) { closeVoucherModal(); return; }
        });

        // Ayusin ang calendar kapag nagbago ang lapad (desktop ↔ phone)
        let _wasMobile = window.innerWidth < 768;
        window.addEventListener('resize', () => {
            const m = window.innerWidth < 768;
            if (m !== _wasMobile) { _wasMobile = m; if (currentView === 'calendar') renderCalendar(); }
        });

        // ─── Init ────────────────────────────────────────────
        document.getElementById('cal-label').textContent = MONTH_NAMES[calMonth] + ' ' + calYear;
        applyFilters();
        fetchVouchers();

        // ─── Realtime (koneksyon ng sidebar: window.NH_RT) ───
        // Anumang signal sa channel ng Accounting / position mo (receive, submit voucher, approve, release)
        // → kunin agad ang bago. Polling = fallback lang (mas mabagal kapag Live).
        const CV_POLL_MS = 30000, CV_POLL_LIVE_MS = 90000;
        const cvLive = () => !!(window.NH_RT && window.NH_RT.enabled && window.NH_RT.isLive());
        let cvLastPoll = Date.now(), cvRtTimer = null; // kakakuha lang sa itaas

        function cvRefresh(force) {
            cvLastPoll = Date.now();
            return fetchVouchers(force);
        }

        if (window.NH_RT && window.NH_RT.enabled) {
            window.NH_RT.on('*', 'changed', () => {
                clearTimeout(cvRtTimer);
                cvRtTimer = setTimeout(() => {
                    if (document.hidden) { cvLastPoll = 0; return; } // kukunin pagbalik sa tab
                    cvRefresh(true); // force → kasama na ang listahan ng ready to release
                }, 500);
            });
            window.NH_RT.onLive(live => {
                const el = document.getElementById('cv-live-dot');
                if (el) el.className = 'w-2 h-2 rounded-full ' + (live ? 'bg-green-500 animate-pulse' : 'bg-amber-400');
                if (live && cvLastPoll) cvRefresh(true); // habol sa na-miss habang offline
            });
        }

        setInterval(() => {
            if (document.hidden) return;
            if (Date.now() - cvLastPoll < (cvLive() ? CV_POLL_LIVE_MS : CV_POLL_MS) - 1000) return;
            cvRefresh(false);
            if (Date.now() - releaseLastAt > 120000) loadReleaseQueue(); // bawat ~2 minuto
        }, 10000);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') cvRefresh(cvLastPoll === 0);
        });

        // ─── Highlight ng huling pinindot na row ─────────────
        // Kapag pinindot ang kahit anong bahagi ng row (View, Approve, atbp.),
        // mananatili itong naka-highlight hanggang may ibang pindutin.
        (function () {
            const KEY = 'cv-picked';
            const SEL = '.cv-row';
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