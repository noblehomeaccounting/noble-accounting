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

<body class="bg-slate-100">
    <main id="main-content" class="md:ml-56 pt-20 md:pt-5 min-h-screen p-4 md:p-8 transition-all duration-300">

        <!-- Header -->
        <div class="mb-4 flex items-start justify-between flex-wrap gap-2">
            <div>
                <h1 class="text-base font-bold text-gray-800">Cash Vouchers</h1>
                <p class="text-[11px] text-gray-400 mt-0.5">Completed and received budget requests</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">

                <!-- Calendar nav -->
                <div id="cal-controls" class="hidden items-center gap-1.5 flex-wrap justify-between w-full sm:w-auto">
                    <div class="relative" id="dates-dropdown-wrap">
                        <button onclick="toggleDatesDropdown(event)"
                            class="flex items-center gap-1.5 text-[10px] font-semibold text-gray-600 bg-white border border-gray-200 hover:bg-gray-50 px-2 py-1 rounded-lg transition shadow-sm">
                            <i class="fa-solid fa-calendar-days text-orange-400 text-[10px]"></i>
                            <span class="hidden sm:inline">Dates with Vouchers</span>
                            <span class="sm:hidden">Dates</span>
                            <svg class="w-2.5 h-2.5 text-gray-400" fill="none" stroke="currentColor" stroke-width="2.5"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                        <div id="dates-dropdown"
                            class="hidden absolute left-0 mt-2 w-72 bg-white border border-gray-200 rounded-xl shadow-xl z-50 overflow-hidden">
                            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                                <p class="text-xs font-bold text-gray-500 uppercase tracking-widest">Dates with Vouchers
                                </p>
                                <span id="dates-dropdown-count" class="text-[10px] text-gray-400"></span>
                            </div>
                            <div class="px-3 py-2 border-b border-gray-100">
                                <input type="text" id="dates-search" placeholder="Search date..."
                                    oninput="filterDatesDropdown()"
                                    class="w-full text-xs border border-gray-200 rounded-lg px-3 py-1.5 outline-none focus:border-orange-400 transition">
                            </div>
                            <ul id="dates-list" class="max-h-64 overflow-y-auto py-1"></ul>
                        </div>
                    </div>
                    <button onclick="prevMonth()"
                        class="w-7 h-7 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-100 transition text-gray-500">
                        <i class="fa-solid fa-chevron-left text-[10px]"></i>
                    </button>
                    <span id="cal-label" class="text-xs font-semibold text-gray-700 min-w-[100px] text-center"></span>
                    <button onclick="nextMonth()"
                        class="w-7 h-7 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-100 transition text-gray-500">
                        <i class="fa-solid fa-chevron-right text-[10px]"></i>
                    </button>
                    <button onclick="goToday()"
                        class="text-[10px] font-semibold text-orange-500 border border-orange-200 bg-orange-50 hover:bg-orange-100 px-2 py-1 rounded-lg transition">
                        Today
                    </button>
                </div>

                <!-- List controls -->
                <div id="list-controls" class="flex items-center gap-2">
                    <select id="category-filter"
                        class="text-[10px] border border-gray-200 rounded-full px-2 py-1 outline-none focus:border-amber-400 transition-all text-gray-600 bg-white">
                        <option value="">All Categories</option>
                        <option value="project">Project</option>
                        <option value="client">Client</option>
                        <option value="nhcc">NHCC</option>
                    </select>
                    <div class="relative">
                        <i
                            class="fa-solid fa-magnifying-glass absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-[10px]"></i>
                        <input type="text" id="search-input" placeholder="Search..."
                            class="pl-7 pr-3 py-1 text-[10px] border border-gray-200 rounded-full outline-none focus:border-amber-400 transition-all w-32 sm:w-44">
                    </div>
                </div>

                <div class="flex items-center gap-1.5">
                    <span id="last-updated" class="text-[10px] text-gray-400 hidden sm:inline"></span>
                    <div class="w-2 h-2 rounded-full bg-green-400 animate-pulse"></div>
                </div>

                <!-- View Toggle -->
                <div class="flex items-center bg-white border border-gray-200 rounded-lg overflow-hidden">
                    <button id="btn-list" onclick="setView('list')"
                        class="flex items-center gap-1 px-2.5 py-1 text-[10px] font-semibold transition-all bg-orange-500 text-white">
                        <i class="fa-solid fa-list text-[9px]"></i>
                        <span class="hidden sm:inline">List</span>
                    </button>
                    <button id="btn-calendar" onclick="setView('calendar')"
                        class="flex items-center gap-1 px-2.5 py-1 text-[10px] font-semibold transition-all text-gray-500 hover:bg-gray-50">
                        <i class="fa-solid fa-calendar-days text-[9px]"></i>
                        <span class="hidden sm:inline">Calendar</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- ─── LIST VIEW ─────────────────────────────────── -->
        <div id="view-list">
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                    <span class="text-sm font-semibold text-gray-700">Voucher Records</span>
                </div>

                <!-- Desktop Table (md and up) -->
                <div class="hidden md:block overflow-x-auto">
                    <div class="max-h-[500px] overflow-y-auto scrollbar-thin">
                        <table class="table-fixed w-full text-sm">
    <colgroup>
        <col style="width:13%"> <!-- Voucher No. -->
        <col style="width:13%"> <!-- Budget Request No. -->
        <col style="width:8%">  <!-- Payee -->
        <col style="width:9%">  <!-- Payment For -->
        <col style="width:9%">  <!-- Category -->
        <col style="width:8%">  <!-- Date -->
        <col style="width:9%">  <!-- Total Amount -->
        <col style="width:7%">  <!-- Approved By -->
        <col style="width:7%">  <!-- Received By -->
        <col style="width:10%"> <!-- Status -->
        <col style="width:7%">  <!-- Action -->
    </colgroup>
    <thead class="sticky top-0 z-10">
        <tr class="bg-gray-50 text-[10px] font-semibold text-black uppercase tracking-wide">
            <th class="px-2 py-3 text-center align-middle">Voucher No.</th>
            <th class="px-2 py-3 text-center align-middle">Budget Request No.</th>
            <th class="px-2 py-3 text-center align-middle">Payee</th>
            <th class="px-2 py-3 text-center align-middle">Payment For</th>
            <th class="px-2 py-3 text-center align-middle">Category</th>
            <th class="px-2 py-3 text-center align-middle">Date</th>
            <th class="px-2 py-3 text-center align-middle">Total Amount</th>
            <th class="px-2 py-3 text-center align-middle">Approved By</th>
            <th class="px-2 py-3 text-center align-middle">Received By</th>
            <th class="px-2 py-3 text-center align-middle">Status</th>
            <th class="px-2 py-3 text-center align-middle">Action</th>
        </tr>
    </thead>
    <tbody id="voucher-tbody">
        <tr>
            <td colspan="11" class="px-5 py-8 text-center text-gray-400 text-sm">
                <i class="fa-solid fa-spinner fa-spin mr-2"></i> Loading...
            </td>
        </tr>
    </tbody>
</table>
                    </div>
                </div>

                <!-- Mobile List (below md) -->
                <div class="md:hidden max-h-[500px] overflow-y-auto scrollbar-thin" id="voucher-cards">
                    <div class="px-4 py-8 text-center text-gray-400 text-sm">
                        <i class="fa-solid fa-spinner fa-spin mr-2"></i> Loading...
                    </div>
                </div>
            </div>
        </div>


        <!-- ─── CALENDAR VIEW ─────────────────────────────── -->
        <div id="view-calendar" class="hidden">

            <!-- Calendar Card -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-3">

                <!-- Day headers -->
                <div class="grid grid-cols-7 border-b border-gray-100">
                    <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $d): ?>
                        <div class="py-2 text-center text-[10px] font-bold uppercase tracking-widest
                    <?= $d === 'Sun' ? 'text-red-400' : ($d === 'Sat' ? 'text-blue-400' : 'text-gray-400') ?>">
                            <?= $d ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Calendar Grid -->
                <div id="calendar-grid" class="grid grid-cols-7"></div>
            </div>

            <!-- Legend -->
            <div class="flex items-center gap-3 mb-3 px-1 flex-wrap">
                <div class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-yellow-400"></span><span
                        class="text-[10px] text-gray-500">For Approval</span></div>
                <div class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-400"></span><span
                        class="text-[10px] text-gray-500">Ready to Release</span></div>
                <div class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-400"></span><span
                        class="text-[10px] text-gray-500">Released</span></div>
                <div class="flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-gray-300"></span><span
                        class="text-[10px] text-gray-500">Not Submitted</span></div>
            </div>

            <!-- Day Detail Panel -->
            <div id="day-panel" class="hidden bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                <div class="flex items-center justify-between px-4 py-3 border-b border-gray-100">
                    <span id="day-panel-title" class="text-sm font-semibold text-gray-700"></span>
                    <button onclick="closeDayPanel()" class="text-gray-300 hover:text-gray-500 transition">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>

                <!-- Desktop table (md+) -->
                <div class="hidden md:block overflow-x-auto max-h-[400px] overflow-y-auto scrollbar-thin">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 z-10">
                            <tr class="bg-gray-50 text-[11px] font-semibold text-gray-400 uppercase tracking-widest">
                                <th class="px-5 py-3 text-left">Voucher No.</th>
                                <th class="px-5 py-3 text-left">Payee</th>
                                <th class="px-5 py-3 text-left">Payment For</th>
                                <th class="px-5 py-3 text-left">Category</th>
                                <th class="px-5 py-3 text-left">Total Amount</th>
                                <th class="px-5 py-3 text-left">Status</th>
                                <th class="px-5 py-3 text-left">Action</th>
                            </tr>
                        </thead>
                        <tbody id="day-vouchers-tbody"></tbody>
                    </table>
                </div>

                <!-- Mobile list (below md) -->
                <div class="md:hidden max-h-[400px] overflow-y-auto scrollbar-thin" id="day-vouchers-cards"></div>
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
                        <img src="<?= BASE_URL ?>/icon/logo.png" alt="Logo" class="w-12 h-12 object-contain">
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
                            <input id="v-payee"
                                class="flex-1 border-b border-gray-400 text-sm pb-0.5 outline-none bg-transparent" />
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
                        <img src="<?= BASE_URL ?>/icon/logo.png" alt="Logo"
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
                            <input id="v-payee-m"
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
                            <label class="text-[10px] font-bold uppercase tracking-widest text-gray-500">
                                Link to Project <span class="text-gray-300">(optional)</span>
                            </label>
                            <select id="conf-project-select"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm outline-none focus:border-orange-400 transition-all">
                                <option value="">— Select Project —</option>
                            </select>
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

        @keyframes badgePulse {
            0% {
                transform: scale(1);
                opacity: 1;
            }

            50% {
                transform: scale(1.4);
                opacity: 0.5;
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .highlight-badge {
            display: inline-block;
            width: 10px;
            height: 10px;
            background-color: #ef4444;
            border-radius: 50%;
            animation: badgePulse 0.8s ease-in-out 6;
            margin-right: 6px;
            vertical-align: middle;
            flex-shrink: 0;
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
        const POSITION = '<?= htmlspecialchars($position) ?>';
        const isCustoAssistant = POSITION === '<?= POSITION_CUSTOASSISTANT ?>';
        const MY_ID = '<?= intval($_SESSION['account_id'] ?? 0) ?>';

        const MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

        function setView(view) {
            currentView = view;

            const listEl = document.getElementById('view-list');
            const calEl = document.getElementById('view-calendar');
            const listCtrl = document.getElementById('list-controls');
            const calCtrl = document.getElementById('cal-controls');
            const btnList = document.getElementById('btn-list');
            const btnCalendar = document.getElementById('btn-calendar');

            if (view === 'list') {
                listEl.classList.remove('hidden');
                calEl.classList.add('hidden');
                listCtrl.style.display = 'flex';
                calCtrl.style.display = 'none';
                btnList.className = 'flex items-center gap-1 px-2.5 py-1 text-[10px] font-semibold transition-all bg-orange-500 text-white';
                btnCalendar.className = 'flex items-center gap-1 px-2.5 py-1 text-[10px] font-semibold transition-all text-gray-500 hover:bg-gray-50';
                applyFilters();
            } else {
                listEl.classList.add('hidden');
                calEl.classList.remove('hidden');
                listCtrl.style.display = 'none';
                calCtrl.style.display = 'flex';
                calCtrl.style.flexWrap = 'wrap';
                calCtrl.style.alignItems = 'center';
                calCtrl.style.gap = '6px';
                btnCalendar.className = 'flex items-center gap-1 px-2.5 py-1 text-[10px] font-semibold transition-all bg-orange-500 text-white';
                btnList.className = 'flex items-center gap-1 px-2.5 py-1 text-[10px] font-semibold transition-all text-gray-500 hover:bg-gray-50';
                renderCalendar();
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
            const map = {
                'voucher_approval': 'bg-yellow-100 text-yellow-700',
                'ready_to_release': 'bg-blue-100 text-blue-700',
                'released': 'bg-green-100 text-green-700',
            };
            const label = {
                'voucher_approval': 'For Approval',
                'ready_to_release': 'Ready to Release',
                'released': 'Released',
            };
            const cls = map[status] ?? 'bg-gray-100 text-gray-500';
            const lbl = label[status] ?? status;
            return `<span class="${cls} text-[10px] font-semibold px-2 py-1 rounded-full uppercase tracking-wide">${lbl}</span>`;
        }

        function categoryBadge(category, reference) {
            if (!category) return '<span class="text-gray-300 text-xs">—</span>';
            const map = {
                project: { label: 'Project', icon: 'fa-helmet-safety', color: 'bg-blue-100 text-blue-700 border-blue-200' },
                client: { label: 'Client', icon: 'fa-user-tie', color: 'bg-purple-100 text-purple-700 border-purple-200' },
                nhcc: { label: 'NHCC', icon: 'fa-building', color: 'bg-orange-100 text-orange-700 border-orange-200' },
            };
            const cfg = map[category] ?? { label: category, icon: 'fa-tag', color: 'bg-gray-100 text-gray-600 border-gray-200' };
            if (!reference) {
                return `<span class="inline-flex items-center gap-1 border rounded-full px-2 py-0.5 text-[10px] font-semibold ${cfg.color}">
                    <i class="fa-solid ${cfg.icon} text-[9px]"></i> ${cfg.label}
                </span>`;
            }
            const safeRef = reference.replace(/'/g, "\\'").replace(/`/g, '\\`');
            return `<div class="relative inline-block"
                onmouseenter="showCatTooltip(event,'${cfg.label}','${safeRef}')"
                onmouseleave="hideCatTooltip()">
                <span class="inline-flex items-center gap-1 border rounded-full px-2 py-0.5 text-[10px] font-semibold cursor-default ${cfg.color}">
                    <i class="fa-solid ${cfg.icon} text-[9px]"></i> ${cfg.label}
                    <i class="fa-solid fa-circle-info text-[8px] opacity-50"></i>
                </span>
            </div>`;
        }

        function highlight(text, q) {
            if (!text) return '';
            if (!q) return text;
            const escaped = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            return String(text).replace(new RegExp(`(${escaped})`, 'gi'),
                '<mark class="bg-yellow-200 text-yellow-900 rounded px-0.5">$1</mark>');
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

        function renderTable(data, q = '') {
            const tbody = document.getElementById('voucher-tbody');
            const cards = document.getElementById('voucher-cards');

            if (!data.length) {
                const empty = `<div class="px-5 py-8 text-center text-gray-400 text-sm">No completed vouchers yet.</div>`;
                tbody.innerHTML = `<tr><td colspan="11" class="px-5 py-8 text-center text-gray-400">No completed vouchers yet.</td></tr>`;
                cards.innerHTML = empty;
                return;
            }

            // ── Desktop rows (unchanged) ──
            tbody.innerHTML = data.map(row => {
                const items = row.items ?? [];
                const total = items.reduce((sum, i) => sum + (parseFloat(i.amount) || 0), 0);
                const isComplete = row.approver_name && (row.receiver_name || row.manual_receiver_name) && row.voucher_status === 'released';
                const isPending = !row.approver_name || (!row.receiver_name && !row.manual_receiver_name);
                const rowClass = isComplete ? 'bg-green-50 hover:bg-green-100' : isPending ? 'bg-red-100 hover:bg-red-200' : 'hover:bg-gray-50';

          return `
<tr data-id="${row.id}" class="border-t border-gray-100 transition-colors ${rowClass}">
    <td class="px-4 py-3 align-middle whitespace-nowrap font-mono text-xs text-blue-900 cursor-pointer "
        onclick="viewVoucher(${JSON.stringify(row).replace(/"/g, '&quot;')})">
        ${highlight(row.voucher_control_no ?? '—', q)}
    </td>
    <td class="px-4 py-3 align-middle whitespace-nowrap font-mono text-xs text-gray-500">${highlight(row.budget_control_no, q)}</td>
    <td class="px-4 py-3 align-middle text-gray-800 text-xs min-w-[110px]">${highlight(row.voucher_payee ?? '—', q)}</td>
    <td class="px-4 py-3 align-middle text-gray-600 text-xs min-w-[140px] max-w-[200px]">${highlight(row.purpose, q)}</td>
    <td class="px-4 py-3 align-middle whitespace-nowrap text-xs">${categoryBadge(row.request_category, row.request_reference)}</td>
    <td class="px-4 py-3 align-middle whitespace-nowrap text-xs text-gray-400 font-mono">${row.date_requested}</td>
    <td class="px-4 py-3 align-middle whitespace-nowrap font-mono text-xs font-semibold ${isComplete ? 'text-green-600' : 'text-gray-700'}">
        PHP ${total.toLocaleString('en-PH', { minimumFractionDigits: 2 })}
    </td>
    <td class="px-4 py-3 align-middle whitespace-nowrap text-xs text-gray-700">${highlight(row.approver_name ?? '—', q)}</td>
    <td class="px-4 py-3 align-middle whitespace-nowrap text-xs text-gray-700">${highlight(row.manual_receiver_name || row.receiver_name || '—', q)}</td>
    <td class="px-4 py-3 align-middle whitespace-nowrap">${row.voucher_status ? statusBadge(row.voucher_status) : '<span class="text-[10px] text-gray-400">Not submitted</span>'}</td>
    <td class="px-4 py-3 align-middle whitespace-nowrap text-center">
        <button onclick="viewVoucher(${JSON.stringify(row).replace(/"/g, '&quot;')})"
            class="inline-flex items-center gap-1.5 whitespace-nowrap bg-orange-500 hover:bg-orange-600 text-white text-xs font-semibold px-3 py-1.5 rounded-full transition-all">
           View
        </button>
    </td>
</tr>`;
            }).join('');

            // ── Mobile cards ──
            cards.innerHTML = data.map(row => {
                const items = row.items ?? [];
                const total = items.reduce((sum, i) => sum + (parseFloat(i.amount) || 0), 0);
                const isComplete = row.approver_name && (row.receiver_name || row.manual_receiver_name) && row.voucher_status === 'released';
                const isPending = !row.approver_name || (!row.receiver_name && !row.manual_receiver_name);
                const cardBg = isComplete ? 'bg-green-50' : isPending ? 'bg-red-50' : 'bg-white';

                return `
<div data-id="${row.id}" 
    class="flex items-center gap-3 px-4 py-3 border-b border-gray-100 transition-colors ${isComplete ? 'bg-green-50' : isPending ? 'bg-red-50' : 'bg-white'} active:bg-gray-50">
    
    <!-- Left: Status color bar -->
    <div class="w-1 self-stretch rounded-full flex-shrink-0 ${isComplete ? 'bg-green-400' : isPending ? 'bg-red-300' : row.voucher_status === 'voucher_approval' ? 'bg-yellow-400' : row.voucher_status === 'ready_to_release' ? 'bg-blue-400' : 'bg-gray-200'}"></div>

    <!-- Middle: Main info -->
    <div class="flex-1 min-w-0">
        <div class="flex items-center justify-between gap-2 mb-0.5">
            <span class="font-mono text-[10px] font-bold text-blue-500 truncate">${highlight(row.voucher_control_no ?? '—', q)}</span>
            ${row.voucher_status ? statusBadge(row.voucher_status) : '<span class="text-[9px] text-gray-400 border border-gray-200 rounded-full px-1.5 py-0.5 flex-shrink-0">Not submitted</span>'}
        </div>
        <div class="text-sm font-semibold text-gray-800 truncate leading-tight">${highlight(row.voucher_payee ?? '—', q)}</div>
        <div class="text-[11px] text-gray-400 truncate">${highlight(row.purpose ?? '—', q)}</div>
        <div class="flex items-center gap-2 mt-1.5">
            ${categoryBadge(row.request_category, row.request_reference)}
            <span class="text-[10px] text-gray-400 font-mono">${row.date_requested}</span>
        </div>
    </div>

    <!-- Right: Amount + action -->
    <div class="flex flex-col items-end gap-2 flex-shrink-0">
        <div class="font-mono text-xs font-bold ${isComplete ? 'text-green-600' : 'text-gray-700'} text-right">
            PhP ${total.toLocaleString('en-PH', { minimumFractionDigits: 2 })}
        </div>
        <button onclick="viewVoucher(${JSON.stringify(row).replace(/"/g, '&quot;')})"
            class="bg-orange-500 hover:bg-orange-600 active:bg-orange-700 text-white text-[10px] font-semibold px-3 py-1.5 rounded-full transition-all whitespace-nowrap">
            <i class="fa-solid fa-receipt mr-1"></i>View
        </button>
    </div>
</div>`;
            }).join('');
        }

        function applyFilters() {
            const q = document.getElementById('search-input').value.toLowerCase();
            const cat = document.getElementById('category-filter').value;
            const filtered = allData.filter(row => {
                const matchSearch =
                    row.voucher_control_no?.toLowerCase().includes(q) ||
                    row.budget_control_no?.toLowerCase().includes(q) ||
                    row.voucher_payee?.toLowerCase().includes(q) ||
                    row.requestor_name?.toLowerCase().includes(q) ||
                    row.purpose?.toLowerCase().includes(q) ||
                    row.approver_name?.toLowerCase().includes(q) ||
                    row.receiver_name?.toLowerCase().includes(q) ||
                    row.request_reference?.toLowerCase().includes(q);
                const matchCategory = !cat || row.request_category === cat;
                return matchSearch && matchCategory;
            });
            renderTable(filtered, q);
        }

        document.getElementById('search-input').addEventListener('input', applyFilters);
        document.getElementById('category-filter').addEventListener('change', applyFilters);

        // ─── CALENDAR VIEW ───────────────────────────────────
        function statusDotColor(status) {
            const map = {
                'voucher_approval': 'bg-yellow-400',
                'ready_to_release': 'bg-blue-400',
                'released': 'bg-green-400',
            };
            return map[status] ?? 'bg-gray-300';
        }

        function renderCalendar() {
            document.getElementById('cal-label').textContent = MONTH_NAMES[calMonth] + ' ' + calYear;
            const grid = document.getElementById('calendar-grid');
            grid.innerHTML = '';
            const firstDay = new Date(calYear, calMonth, 1).getDay();
            const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();
            const today = new Date();

            const byDate = {};
            allData.forEach(r => {
                const d = r.date_requested ? r.date_requested.substring(0, 10) : null;
                if (!d) return;
                if (!byDate[d]) byDate[d] = [];
                byDate[d].push(r);
            });

            for (let i = 0; i < firstDay; i++) {
                const blank = document.createElement('div');
                blank.className = 'border-b border-r border-gray-100 bg-gray-50/30';
                blank.style.minHeight = window.innerWidth < 768 ? '44px' : '90px';
                grid.appendChild(blank);
            }

            for (let day = 1; day <= daysInMonth; day++) {
                const mm = String(calMonth + 1).padStart(2, '0');
                const dd = String(day).padStart(2, '0');
                const dateStr = `${calYear}-${mm}-${dd}`;
                const rows = byDate[dateStr] ?? [];
                const isToday = (today.getFullYear() === calYear && today.getMonth() === calMonth && today.getDate() === day);
                const isSelected = selectedDate === dateStr;
                const dayOfWeek = (firstDay + day - 1) % 7;
                const isMobile = window.innerWidth < 768;

                const cell = document.createElement('div');
                cell.style.minHeight = isMobile ? '44px' : '90px';

                const hasVouchers = rows.length > 0;

                cell.className = [
                    'border-b border-r border-gray-100 cursor-pointer transition-all duration-150 relative',
                    isMobile ? 'flex flex-col items-center justify-start pt-1.5 pb-1' : 'p-2',
                    hasVouchers ? 'hover:bg-orange-50' : 'hover:bg-gray-50',
                    isSelected ? 'bg-orange-50 ring-2 ring-inset ring-orange-400' : '',
                ].join(' ');

                const seenStatuses = [...new Set(rows.map(r => r.voucher_status ?? 'none'))];
                const dotColors = {
                    'voucher_approval': 'bg-yellow-400',
                    'ready_to_release': 'bg-blue-400',
                    'released': 'bg-green-400',
                    'none': 'bg-gray-300',
                };

                if (isMobile) {
                    // ── Mobile: compact phone-calendar style ──
                    const dayNumClass = isToday
                        ? 'w-6 h-6 bg-orange-500 text-white rounded-full flex items-center justify-center text-xs font-bold'
                        : dayOfWeek === 0 ? 'text-red-400 text-xs font-semibold'
                            : dayOfWeek === 6 ? 'text-blue-400 text-xs font-semibold'
                                : 'text-gray-600 text-xs font-semibold';

                    const dots = seenStatuses.map(s =>
                        `<span class="inline-block w-1.5 h-1.5 rounded-full ${dotColors[s] ?? 'bg-gray-300'}"></span>`
                    ).join('');

                    cell.innerHTML = `
                <span class="${dayNumClass}">${day}</span>
                ${hasVouchers ? `
                <div class="flex items-center justify-center gap-0.5 mt-0.5 flex-wrap">${dots}</div>
                <span class="text-[8px] font-bold text-orange-500 leading-tight">${rows.length}</span>
                ` : ''}`;
                } else {
                    // ── Desktop: original detailed view ──
                    const countBadge = hasVouchers
                        ? `<span class="text-[10px] font-bold text-orange-500">${rows.length} voucher${rows.length > 1 ? 's' : ''}</span>` : '';

                    const dots = seenStatuses.map(s =>
                        `<span class="inline-block w-2 h-2 rounded-full ${dotColors[s] ?? 'bg-gray-300'}"></span>`
                    ).join('');

                    const dayNumClass = isToday
                        ? 'w-6 h-6 bg-orange-500 text-white rounded-full flex items-center justify-center'
                        : dayOfWeek === 0 ? 'text-red-400' : dayOfWeek === 6 ? 'text-blue-400' : 'text-gray-500';

                    cell.innerHTML = `
                <div class="flex items-start justify-between mb-1">
                    <span class="text-xs font-semibold ${dayNumClass}">${day}</span>
                    ${countBadge}
                </div>
                ${hasVouchers ? `
                <div class="flex flex-wrap gap-1 mt-1">${dots}</div>
                <div class="mt-1.5 space-y-0.5">
                    ${rows.slice(0, 2).map(r => `
                        <div class="text-[9px] truncate px-1.5 py-0.5 rounded font-medium
                            ${r.voucher_status === 'released' ? 'bg-green-100 text-green-700'
                            : r.voucher_status === 'ready_to_release' ? 'bg-blue-100 text-blue-700'
                                : r.voucher_status === 'voucher_approval' ? 'bg-yellow-100 text-yellow-700'
                                    : 'bg-gray-100 text-gray-500'}">
                            ${r.voucher_payee || r.purpose || '—'}
                        </div>`).join('')}
                    ${rows.length > 2 ? `<div class="text-[9px] text-gray-400 px-1">+${rows.length - 2} more</div>` : ''}
                </div>` : ''}`;
                }

                cell.onclick = () => openDayPanel(dateStr, rows);
                grid.appendChild(cell);
            }

            const total = firstDay + daysInMonth;
            const trailing = total % 7 === 0 ? 0 : 7 - (total % 7);
            for (let i = 0; i < trailing; i++) {
                const blank = document.createElement('div');
                blank.className = 'border-b border-r border-gray-100 bg-gray-50/30';
                blank.style.minHeight = window.innerWidth < 768 ? '44px' : '90px';
                grid.appendChild(blank);
            }
        }

        function prevMonth() {
            calMonth--;
            if (calMonth < 0) { calMonth = 11; calYear--; }
            selectedDate = null;
            closeDayPanel();
            renderCalendar();
        }

        function nextMonth() {
            calMonth++;
            if (calMonth > 11) { calMonth = 0; calYear++; }
            selectedDate = null;
            closeDayPanel();
            renderCalendar();
        }

        function goToday() {
            const now = new Date();
            calYear = now.getFullYear();
            calMonth = now.getMonth();
            selectedDate = null;
            closeDayPanel();
            renderCalendar();
        }


        function openDayPanel(dateStr, rows) {
            selectedDate = dateStr;
            renderCalendar();

            const panel = document.getElementById('day-panel');
            const title = document.getElementById('day-panel-title');
            const tbody = document.getElementById('day-vouchers-tbody');
            const mobileCards = document.getElementById('day-vouchers-cards');

            const d = new Date(dateStr + 'T00:00:00');
            title.textContent = d.toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });

            if (!rows.length) {
                tbody.innerHTML = `<tr><td colspan="7" class="px-5 py-8 text-center text-gray-400 text-sm">No vouchers on this day.</td></tr>`;
                mobileCards.innerHTML = `<div class="px-4 py-8 text-center text-gray-400 text-sm">No vouchers on this day.</div>`;
            } else {
                // Desktop table rows
                tbody.innerHTML = rows.map(row => {
                    const items = row.items ?? [];
                    const total = items.reduce((sum, i) => sum + (parseFloat(i.amount) || 0), 0);
                    const isComplete = row.voucher_status === 'released';
                    return `
<tr data-id="${row.id}" class="border-t border-gray-100 transition-colors ${isComplete ? 'bg-green-50 hover:bg-green-100' : 'hover:bg-gray-50'}">
    <td class="px-5 py-3 font-mono text-xs text-blue-900 cursor-pointer"
        onclick="viewVoucher(${JSON.stringify(row).replace(/"/g, '&quot;')})">${row.voucher_control_no ?? '—'}</td>
    <td class="px-5 py-3 text-gray-800 text-sm">${row.voucher_payee ?? '—'}</td>
    <td class="px-5 py-3 text-gray-600 text-sm">${row.purpose ?? '—'}</td>
    <td class="px-5 py-3">${categoryBadge(row.request_category, row.request_reference)}</td>
    <td class="px-5 py-3 font-mono text-xs font-semibold ${isComplete ? 'text-green-600' : 'text-gray-700'}">
        PhP ${total.toLocaleString('en-PH', { minimumFractionDigits: 2 })}
    </td>
    <td class="px-5 py-3">${row.voucher_status ? statusBadge(row.voucher_status) : '<span class="text-[10px] text-gray-400">Not submitted</span>'}</td>
    <td class="px-5 py-3">
        <button onclick="viewVoucher(${JSON.stringify(row).replace(/"/g, '&quot;')})"
            class="bg-orange-500 hover:bg-orange-600 text-white text-[10px] font-semibold px-3 py-1.5 rounded-full transition-all">
            <i class="fa-solid fa-receipt mr-1"></i>View
        </button>
    </td>
</tr>`;
                }).join('');

                // Mobile list rows
                mobileCards.innerHTML = rows.map(row => {
                    const items = row.items ?? [];
                    const total = items.reduce((sum, i) => sum + (parseFloat(i.amount) || 0), 0);
                    const isComplete = row.voucher_status === 'released';
                    const isPending = !row.approver_name || (!row.receiver_name && !row.manual_receiver_name);
                    const barColor = isComplete ? 'bg-green-400' : isPending ? 'bg-red-300'
                        : row.voucher_status === 'voucher_approval' ? 'bg-yellow-400'
                            : row.voucher_status === 'ready_to_release' ? 'bg-blue-400' : 'bg-gray-200';
                    const rowBg = isComplete ? 'bg-green-50' : isPending ? 'bg-red-50' : 'bg-white';

                    return `
<div data-id="${row.id}" class="flex items-center gap-3 px-4 py-3 border-b border-gray-100 ${rowBg}">
    <div class="w-1 self-stretch rounded-full flex-shrink-0 ${barColor}"></div>
    <div class="flex-1 min-w-0">
        <div class="flex items-center justify-between gap-2 mb-0.5">
            <span class="font-mono text-[10px] font-bold text-blue-500 truncate">${row.voucher_control_no ?? '—'}</span>
            ${row.voucher_status ? statusBadge(row.voucher_status) : '<span class="text-[9px] text-gray-400 border border-gray-200 rounded-full px-1.5 py-0.5">Not submitted</span>'}
        </div>
        <div class="text-sm font-semibold text-gray-800 truncate">${row.voucher_payee ?? '—'}</div>
        <div class="text-[11px] text-gray-400 truncate">${row.purpose ?? '—'}</div>
        <div class="flex items-center gap-2 mt-1">
            ${categoryBadge(row.request_category, row.request_reference)}
        </div>
    </div>
    <div class="flex flex-col items-end gap-2 flex-shrink-0">
        <div class="font-mono text-xs font-bold ${isComplete ? 'text-green-600' : 'text-gray-700'}">
            PhP ${total.toLocaleString('en-PH', { minimumFractionDigits: 2 })}
        </div>
        <button onclick="viewVoucher(${JSON.stringify(row).replace(/"/g, '&quot;')})"
            class="bg-orange-500 text-white text-xs font-semibold px-1 py-1.5 rounded-full whitespace-nowrap">
            <i class="fa-solid fa-receipt mr-1"></i>View
        </button>
    </div>
</div>`;
                }).join('');
            }

            panel.classList.remove('hidden');
            setTimeout(() => panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' }), 50);
        }

        function closeDayPanel() {
            selectedDate = null;
            document.getElementById('day-panel').classList.add('hidden');
            renderCalendar();
        }

        // ─── Dates Dropdown (calendar) ───────────────────────
        function buildDatesDropdown() {
            const byDate = {};
            allData.forEach(r => {
                const d = r.date_requested ? r.date_requested.substring(0, 10) : null;
                if (!d) return;
                if (!byDate[d]) byDate[d] = { voucher_approval: 0, ready_to_release: 0, released: 0, none: 0, total: 0 };
                const s = r.voucher_status ?? 'none';
                byDate[d][s] = (byDate[d][s] || 0) + 1;
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
            ul.innerHTML = dates.map(({ date, voucher_approval, ready_to_release, released, none, total }) => {
                const d = new Date(date + 'T00:00:00');
                const label = d.toLocaleDateString('en-PH', { weekday: 'short', year: 'numeric', month: 'short', day: 'numeric' });
                const dots = [
                    voucher_approval ? `<span class="inline-block w-1.5 h-1.5 rounded-full bg-yellow-400"></span>` : '',
                    ready_to_release ? `<span class="inline-block w-1.5 h-1.5 rounded-full bg-blue-400"></span>` : '',
                    released ? `<span class="inline-block w-1.5 h-1.5 rounded-full bg-green-400"></span>` : '',
                    none ? `<span class="inline-block w-1.5 h-1.5 rounded-full bg-gray-300"></span>` : '',
                ].join('');
                return `
<li class="flex items-center justify-between gap-3 px-4 py-2.5 hover:bg-orange-50 cursor-pointer transition group"
    onclick="jumpToDate('${date}')">
    <div class="flex items-center gap-2">
        <i class="fa-regular fa-calendar text-gray-300 group-hover:text-orange-400 transition text-xs"></i>
        <span class="text-xs font-medium text-gray-700">${label}</span>
    </div>
    <div class="flex items-center gap-1.5">
        ${dots}
        <span class="text-[10px] font-bold text-orange-500 bg-orange-50 group-hover:bg-white border border-orange-200 rounded-full px-1.5 py-0.5 ml-1">${total}</span>
    </div>
</li>`;
            }).join('');
        }

        function filterDatesDropdown() {
            const q = document.getElementById('dates-search').value.toLowerCase();
            if (!q) { renderDatesList(allDatesCache); return; }
            const filtered = allDatesCache.filter(({ date }) => {
                const d = new Date(date + 'T00:00:00');
                return d.toLocaleDateString('en-PH', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })
                    .toLowerCase().includes(q) || date.includes(q);
            });
            renderDatesList(filtered);
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
            renderCalendar();
            const rows = allData.filter(r => r.date_requested?.startsWith(dateStr));
            openDayPanel(dateStr, rows);
            document.getElementById('calendar-grid').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        document.addEventListener('click', () => {
            document.getElementById('dates-dropdown')?.classList.add('hidden');
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

            document.getElementById('v-control-no').textContent = row.voucher_control_no ?? row.control_no;
            document.getElementById('v-date').textContent = row.date_requested;
            document.getElementById('v-second-no').textContent = row.voucher_payment_method ?? '—';
            document.getElementById('v-title').textContent = row.voucher_title ?? '';
            document.getElementById('v-payee').value = row.voucher_payee ?? '';
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
            document.getElementById('v-payee-m').value = row.voucher_payee ?? '';
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
                fetchVouchers();

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
                        fetchVouchers();
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
                        fetchVouchers();
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
                <img src="<?= BASE_URL ?>/icon/logo.png" style="width:48px;height:48px;object-fit:contain;">
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

        // ─── Fetch ───────────────────────────────────────────
        let _highlightDone = false;

        function fetchVouchers() {
            fetch('<?= BASE_URL ?>/fetchreceived')
                .then(res => res.json())
                .then(data => {
                    // Custodian assistant lang: ready_to_release lang ang makikita
                    allData = isCustoAssistant
                        ? data.filter(r =>
                            r.voucher_status === 'ready_to_release' ||
                            (r.voucher_status === 'released' && String(r.released_by) === MY_ID))
                        : data;

                    if (currentView === 'list') {
                        applyFilters();
                    } else {
                        renderCalendar();
                        buildDatesDropdown();
                        if (selectedDate) {
                            const rows = allData.filter(r => r.date_requested?.startsWith(selectedDate));
                            openDayPanel(selectedDate, rows);
                        }
                    }
                    document.getElementById('last-updated').textContent =
                        'Updated ' + new Date().toLocaleTimeString('en-PH');

                    if (!_highlightDone) {
                        _highlightDone = true;
                        checkHighlight();
                    }
                });
        }
        // ─── Toast ───────────────────────────────────────────
        function showToast(message, type = 'success') {
            const colors = { success: 'bg-green-500', error: 'bg-red-500', info: 'bg-blue-500' };
            const toast = document.createElement('div');
            toast.className = `fixed bottom-6 right-6 z-[999] flex items-center gap-3 ${colors[type]} text-white text-sm font-semibold px-5 py-3 rounded-xl shadow-lg transition-all duration-300 opacity-0 translate-y-2`;
            toast.innerHTML = `<i class="fa-solid ${type === 'success' ? 'fa-circle-check' : 'fa-circle-xmark'}"></i> ${message}`;
            document.body.appendChild(toast);
            requestAnimationFrame(() => toast.classList.remove('opacity-0', 'translate-y-2'));
            setTimeout(() => {
                toast.classList.add('opacity-0', 'translate-y-2');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        function checkHighlight() {
            const params = new URLSearchParams(window.location.search);
            const highlightId = params.get('highlight');
            if (!highlightId) return;

            let tries = 0;
            const interval = setInterval(() => {
                tries++;

                const isMobile = window.innerWidth < 768;

                // Piliin ang tamang element depende sa viewport
                const row = isMobile
                    ? document.querySelector(`#voucher-cards div[data-id="${highlightId}"]`)
                    : document.querySelector(`#voucher-tbody tr[data-id="${highlightId}"]`);

                if (row) {
                    clearInterval(interval);

                    // Scroll ang container
                    const container = row.closest('.overflow-y-auto');
                    if (container) {
                        const rowTop = row.offsetTop - container.offsetTop;
                        container.scrollTop = rowTop - 50;
                    } else {
                        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }

                    if (!isMobile) {
                        // Desktop — red dot
                        const firstTd = row.querySelector('td:first-child');
                        const badge = document.createElement('span');
                        badge.className = 'highlight-badge';
                        if (firstTd) firstTd.prepend(badge);
                        setTimeout(() => badge.remove(), 5000);
                    } else {
                        // Mobile — flash
                        const originalBg = row.style.backgroundColor;
                        let on = true;
                        const flashInterval = setInterval(() => {
                            row.style.backgroundColor = on ? '#fecaca' : '#fee2e2';
                            on = !on;
                        }, 300);
                        setTimeout(() => {
                            clearInterval(flashInterval);
                            row.style.backgroundColor = originalBg;
                        }, 5000);
                    }
                }

                if (tries >= 25) clearInterval(interval);
            }, 200);
        }

        // ─── ESC to close modals ───────────────────────────────
        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape') return;

            const confirmModal = document.getElementById('voucher-confirm-modal');
            const voucherModal = document.getElementById('voucher-modal');

            // Priority: close confirm modal muna kung bukas siya (nasa taas siya sa stack)
            if (!confirmModal.classList.contains('hidden')) {
                closeConfirmModal();
                return;
            }

            if (!voucherModal.classList.contains('hidden')) {
                closeVoucherModal();
                return;
            }
        });

        // ─── Init ────────────────────────────────────────────
        fetchVouchers();
        setInterval(fetchVouchers, 5000);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') fetchVouchers();
        });
    </script>
</body>

</html>