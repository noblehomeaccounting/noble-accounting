<?php
// index-cashvoucherdashboard.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_ACCOUNTING];
$allowedPositions = [POSITION_CUSTODIAN, POSITION_HEAD, POSITION_STAFF];

include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$position = $_SESSION['position'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Voucher Dashboard</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
</head>

<body class="bg-gray-100">
    <main id="main-content" class="md:ml-56 pt-20 md:pt-5 min-h-screen p-4 md:p-8 transition-all duration-300">

        <!-- ═══ HEADER ═══ -->
        <div class="mb-5 flex items-start sm:items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-11 h-11 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-file-invoice-dollar text-lg"></i>
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl font-bold text-gray-800 leading-tight">Cash Voucher Dashboard</h1>
                    <p class="text-sm text-gray-500">Review and process cash vouchers</p>
                </div>
            </div>
            <div class="flex items-center gap-2 text-[11px] text-gray-500 bg-white border border-gray-200 rounded-full px-3 py-1.5 shadow-sm">
                <span id="cvd-live-dot" class="w-2 h-2 rounded-full bg-amber-400" title="Green = Live"></span>
                <span id="last-updated">Loading…</span>
            </div>
        </div>

        <!-- ═══ STATUS TABS ═══ -->
        <?php
        $hasActionTab = in_array($position, [POSITION_STAFF, POSITION_HEAD], true);
        $actionLabel = $position === POSITION_HEAD ? 'To approve' : 'To prepare';
        $cvdTabs = [];
        if ($hasActionTab) $cvdTabs[] = ['action', $actionLabel, 'fa-bell', 'bg-red-50 text-red-500'];
        $cvdTabs[] = ['all',              'All this month',     'fa-layer-group',     'bg-amber-50 text-amber-700'];
        $cvdTabs[] = ['preparing',        'Preparing',        'fa-pen-to-square',   'bg-orange-50 text-orange-600'];
        $cvdTabs[] = ['voucher_approval', 'For approval',     'fa-hourglass-half',  'bg-yellow-50 text-yellow-600'];
        $cvdTabs[] = ['ready_to_release', 'Ready to release', 'fa-box-open',        'bg-blue-50 text-blue-600'];
        $cvdTabs[] = ['released',         'Released',         'fa-circle-check',    'bg-green-50 text-green-600'];
        $defaultTab = $hasActionTab ? 'action' : 'all';
        ?>
        <div class="grid grid-cols-2 sm:grid-cols-3 <?= $hasActionTab ? 'xl:grid-cols-6' : 'xl:grid-cols-5' ?> gap-3 mb-5" id="cvd-tabs">
            <?php foreach ($cvdTabs as $i => [$key, $label, $icon, $tone]): ?>
                <button type="button" data-tab="<?= $key ?>" data-active="<?= $key === $defaultTab ? 1 : 0 ?>" onclick="setTab('<?= $key ?>')"
                    class="cvd-tab border rounded-xl p-3.5 flex items-center gap-3 text-left shadow-sm transition min-w-0 <?= $key === 'action' ? 'cvd-tab-action' : '' ?>">
                    <span class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 <?= $tone ?>"><i class="fa-solid <?= $icon ?>"></i></span>
                    <span class="min-w-0">
                        <span class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide leading-tight"><?= $label ?></span>
                        <span class="block text-2xl font-bold text-gray-800 leading-none mt-1" data-count="<?= $key ?>">0</span>
                    </span>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- ═══ LIST CARD ═══ -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-2.5 px-4 py-3 border-b border-gray-100">
                <!-- Month navigation -->
                <div class="flex items-center gap-2 flex-wrap" id="cvd-month-nav">
                    <button type="button" onclick="changeMonth(-1)" title="Previous month"
                        class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600 transition">
                        <i class="fa-solid fa-chevron-left text-xs"></i>
                    </button>
                    <div class="relative">
                        <select id="cvd-month-select" onchange="pickMonth(this.value)" title="Choose month"
                            class="appearance-none h-9 min-w-[150px] text-center text-sm font-bold text-gray-800 bg-white border border-gray-200 hover:bg-gray-50 rounded-lg pl-3 pr-8 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition cursor-pointer"></select>
                        <i class="fa-solid fa-chevron-down absolute right-3 top-1/2 -translate-y-1/2 text-[9px] text-gray-400 pointer-events-none"></i>
                    </div>
                    <button type="button" onclick="changeMonth(1)" title="Next month"
                        class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 hover:bg-gray-50 text-gray-600 transition">
                        <i class="fa-solid fa-chevron-right text-xs"></i>
                    </button>
                    <button type="button" onclick="goThisMonth()"
                        class="h-9 text-xs font-semibold text-amber-700 border border-amber-200 bg-amber-50 hover:bg-amber-100 px-3 rounded-lg transition">
                        This month
                    </button>
                    <span class="text-xs text-gray-500 ml-1">Total: <b id="cvd-month-total" class="font-mono text-gray-800">₱ 0.00</b></span>
                </div>
                <div class="relative w-full lg:w-72">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[11px]"></i>
                    <input type="text" id="search-input" placeholder="Search voucher no., title, payee, purpose..."
                        class="w-full h-9 pl-8 pr-3 text-xs border border-gray-200 rounded-lg outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition">
                </div>
            </div>
            <div id="cvd-info" class="px-4 py-2 text-[11px] text-gray-500 bg-gray-50 border-b border-gray-100">&nbsp;</div>
            <div id="voucher-cards" class="max-h-[640px] overflow-y-auto scrollbar-thin divide-y divide-gray-100">
                <div class="px-4 py-10 text-center text-gray-400 text-sm">
                    <i class="fa-solid fa-spinner fa-spin mr-2"></i> Loading...
                </div>
            </div>
        </div>


        <!-- ─── VOUCHER MODAL ────────────────────── -->
        <div id="voucher-modal"
            class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-2 py-4 md:px-4 md:py-8 overflow-y-auto">

            <!-- ─── DESKTOP layout (md+) ─── -->
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
                            <span id="v-payee" class="flex-1 text-sm text-gray-800"></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="text-[10px] font-bold uppercase tracking-widest text-gray-600 whitespace-nowrap">Payment
                                For</span>
                            <span class="text-gray-400">:</span>
                            <span id="v-purpose" class="flex-1 text-sm text-gray-800 ml-2"></span>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-bold uppercase tracking-widest text-gray-600 w-28">Payment
                                Method</span>
                            <span class="text-gray-400 mr-2">:</span>
                            <span id="v-payment-method" class="flex-1 text-sm text-gray-800"></span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="text-[10px] font-bold uppercase tracking-widest text-gray-600 whitespace-nowrap">Amount
                                in Words</span>
                            <span class="text-gray-400">:</span>
                            <span id="v-amount-words" class="flex-1 text-sm italic text-gray-700 ml-2"></span>
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

                <!-- Signatures: Prepared → Approved → Released → Received -->
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

                <!-- Payee / Payment For / Payment Method / Amount Words -->
                <div class="px-4 py-3 space-y-2.5 border-b border-gray-100">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400 mb-0.5">Payee</p>
                            <p id="v-payee-m" class="text-sm font-semibold text-gray-800"></p>
                        </div>
                        <div>
                            <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400 mb-0.5">Payment For
                            </p>
                            <p id="v-purpose-m" class="text-sm text-gray-700"></p>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400 mb-0.5">Payment
                                Method</p>
                            <p id="v-payment-method-m" class="text-sm text-gray-700"></p>
                        </div>
                        <div>
                            <p class="text-[9px] font-bold uppercase tracking-widest text-gray-400 mb-0.5">Amount in
                                Words</p>
                            <p id="v-amount-words-m" class="text-xs italic text-gray-600"></p>
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

                <!-- Mobile Footer -->
                <div class="px-4 py-3 bg-gray-50">
                    <p class="text-[9px] italic text-gray-400 mb-2">Received the above stated amount in full settlement.
                    </p>
                    <div class="flex flex-col gap-2" id="v-footer-btns-m"></div>
                </div>
            </div>

        </div><!-- end voucher-modal -->

    </main>


    <style>
        .scrollbar-thin::-webkit-scrollbar { width: 4px; }
        .scrollbar-thin::-webkit-scrollbar-track { background: transparent; }
        .scrollbar-thin::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 999px; }
        .scrollbar-thin::-webkit-scrollbar-thumb:hover { background: #9ca3af; }

        .cvd-tab { background: #fff; border-color: #e5e7eb; }
        .cvd-tab:hover { border-color: #f59e0b; }
        .cvd-tab[data-active="1"] { border-color: #b45309; box-shadow: 0 0 0 1px #b45309 inset; background: #fffbeb; }
        .cvd-tab-action[data-hot="1"] [data-count] { color: #dc2626; }

        @keyframes cvdRowFlash { 0%,100% { background: transparent; } 50% { background: #fef3c7; } }
        .cvd-row-flash { animation: cvdRowFlash .6s ease-in-out 5; }

        /* Row na huling pinindot / binuksan */
        .cvd-row[data-selected="1"] { background: #fffbeb; box-shadow: inset 3px 0 0 #d97706, inset 0 0 0 1px #fcd34d; }
        .cvd-row[data-selected="1"]:hover { background: #fef3c7; }
    </style>



    <script>
        const POSITION = '<?= htmlspecialchars($position) ?>';
        const API = '<?= BASE_URL ?>';
        // Tandaan: huwag mag-declare ng `esc` o `BASE_URL`; ginagamit na iyon ng sidebar script.

        let allData = [];
        let currentRow = null;
        let lastText = '';

        // ─── Helpers ─────────────────────────────────────────
        const escHtml = s => String(s ?? '').replace(/[&<>"']/g,
            c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

        const fmtMoney = n => (parseFloat(n) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });

        function fmtDate(str, month = 'short') {
            if (!str) return '';
            const d = new Date(String(str).replace(' ', 'T'));
            if (isNaN(d)) return '';
            return d.toLocaleDateString('en-PH', { year: 'numeric', month, day: 'numeric' });
        }

        function sumItems(items) {
            return (items ?? []).reduce((sum, i) => sum + (parseFloat(i.amount) || 0), 0);
        }

        function numberToWords(amount) {
            const ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine',
                'Ten', 'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen',
                'Seventeen', 'Eighteen', 'Nineteen'];
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

        // "Preparing" = naka-submit na ang voucher pero wala pang nag-prepare
        function voucherStatus(row) {
            if (!row.voucher_status) return null;
            if (row.voucher_status === 'voucher_approval' && !row.prepared_by) return 'preparing';
            return row.voucher_status;
        }

        function statusBadge(status) {
            const map = {
                'preparing': 'bg-orange-100 text-orange-700',
                'voucher_approval': 'bg-yellow-100 text-yellow-700',
                'ready_to_release': 'bg-blue-100 text-blue-700',
                'released': 'bg-green-100 text-green-700',
            };
            const label = {
                'preparing': 'Preparing',
                'voucher_approval': 'For Approval',
                'ready_to_release': 'Ready to Release',
                'released': 'Released',
            };
            return `<span class="${map[status] ?? 'bg-gray-100 text-gray-500'} text-[10px] font-semibold px-2 py-1 rounded-full uppercase tracking-wide whitespace-nowrap">${escHtml(label[status] ?? status)}</span>`;
        }

        // Naka-escape na ang text bago i-highlight
        function highlight(text, q) {
            if (!text) return '';
            const safe = escHtml(text);
            if (!q) return safe;
            const escapedQ = escHtml(q).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            return safe.replace(new RegExp(`(${escapedQ})`, 'gi'),
                '<mark class="bg-yellow-200 text-yellow-900 rounded px-0.5">$1</mark>');
        }

        function matchesQuery(row, q) {
            return row.control_no?.toLowerCase().includes(q) ||
                row.voucher_control_no?.toLowerCase().includes(q) ||
                row.voucher_title?.toLowerCase().includes(q) ||
                row.voucher_payee?.toLowerCase().includes(q) ||
                row.purpose?.toLowerCase().includes(q);
        }

        function sigImg(src) {
            return src
                ? `<img src="${escHtml(src)}" style="position:absolute;bottom:-50px;left:80px;transform:translateX(-50%);height:90px;max-width:160px;object-fit:contain;z-index:10;pointer-events:none;">`
                : '';
        }


        // ─── Tabs / filter ───────────────────────────────────
        const IS_STAFF = POSITION === '<?= POSITION_STAFF ?>';
        const IS_HEAD = POSITION === '<?= POSITION_HEAD ?>';
        let currentTab = '<?= $defaultTab ?>';
        const MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
        let viewYear = new Date().getFullYear();
        let viewMonth = new Date().getMonth(); // 0-11
        let loaded = false;
        let actionTabTouched = false;
        const savingVids = new Set();

        // Huling pinindot na voucher (naka-highlight hanggang may ibang pindutin)
        let selectedId = null;
        try { selectedId = sessionStorage.getItem('cvd-selected'); } catch (e) { }
        function markSelected(id) {
            selectedId = String(id);
            try { sessionStorage.setItem('cvd-selected', selectedId); } catch (e) { }
            document.querySelectorAll('#voucher-cards .cvd-row').forEach(el => {
                const on = el.dataset.id === selectedId;
                el.dataset.selected = on ? '1' : '0';
                const tag = el.querySelector('.cvd-opened-tag');
                if (tag) tag.classList.toggle('hidden', !on);
            });
        }

        // Kailangan ng aksyon MO (depende sa posisyon)
        function needsMyAction(row) {
            if (!row.voucher_id) return false;
            if (IS_STAFF) return !row.prepared_by && row.voucher_status !== 'released';
            if (IS_HEAD) return row.voucher_status === 'voucher_approval' && !row.approved_by && !!row.prepared_by;
            return false;
        }

        // ─── Per month ───────────────────────────────────────
        const monthKey = (y = viewYear, m = viewMonth) => y + '-' + String(m + 1).padStart(2, '0');
        const monthLabel = (y = viewYear, m = viewMonth) => MONTH_NAMES[m] + ' ' + y;
        const rowMonth = row => String(row.date_requested ?? '').substring(0, 7);
        const inMonth = row => rowMonth(row) === monthKey();

        function setMonth(y, m) {
            viewYear = y; viewMonth = m;
            try { sessionStorage.setItem('cvd-month', monthKey()); } catch (e) { }
            buildMonthSelect();
            rerender();
        }
        function changeMonth(step) {
            let m = viewMonth + step, y = viewYear;
            if (m < 0) { m = 11; y--; } else if (m > 11) { m = 0; y++; }
            setMonth(y, m);
        }
        function goThisMonth() { const n = new Date(); setMonth(n.getFullYear(), n.getMonth()); }
        function pickMonth(val) {
            const [y, m] = String(val).split('-').map(Number);
            if (y && m) setMonth(y, m - 1);
        }

        // Dropdown: mga buwan na may voucher (+ buwan ngayon at ang napili), may bilang
        function buildMonthSelect() {
            const sel = document.getElementById('cvd-month-select');
            const counts = {};
            allData.forEach(r => { const k = rowMonth(r); if (/^\d{4}-\d{2}$/.test(k)) counts[k] = (counts[k] || 0) + 1; });
            const keys = new Set(Object.keys(counts));
            keys.add(monthKey()); keys.add(monthKey(new Date().getFullYear(), new Date().getMonth()));
            sel.innerHTML = [...keys].sort().reverse().map(k => {
                const [y, m] = k.split('-').map(Number);
                return `<option value="${k}" ${k === monthKey() ? 'selected' : ''}>${monthLabel(y, m - 1)}${counts[k] ? ' (' + counts[k] + ')' : ''}</option>`;
            }).join('');
        }

        // Ang "To prepare / To approve" ay mula sa LAHAT ng buwan para walang makaligtaan;
        // ang ibang tab ay para sa buwan na napili lang.
        function tabMatch(row, tab) {
            if (tab === 'action') return needsMyAction(row);
            if (!inMonth(row)) return false;
            if (tab === 'all') return true;
            return voucherStatus(row) === tab;
        }

        function setTab(key) {
            currentTab = key;
            if (key === 'action') actionTabTouched = true;
            document.querySelectorAll('#cvd-tabs .cvd-tab').forEach(b => b.dataset.active = b.dataset.tab === key ? '1' : '0');
            rerender();
        }

        function updateCounts() {
            document.querySelectorAll('#cvd-tabs [data-count]').forEach(el => {
                el.textContent = allData.filter(r => tabMatch(r, el.dataset.count)).length;
            });
            const act = document.querySelector('#cvd-tabs .cvd-tab-action');
            if (act) act.dataset.hot = allData.some(needsMyAction) ? '1' : '0';
            const monthSum = allData.filter(inMonth).reduce((s, r) => s + sumItems(r.items), 0);
            document.getElementById('cvd-month-total').textContent = '₱ ' + fmtMoney(monthSum);
            document.getElementById('cvd-month-nav').classList.toggle('opacity-50', currentTab === 'action');
        }

        const STATUS_BAR = {
            preparing: 'bg-orange-400', voucher_approval: 'bg-yellow-400',
            ready_to_release: 'bg-blue-500', released: 'bg-green-500',
        };

        function emptyHtml(title, sub = '') {
            return `<div class="px-4 py-12 text-center">
                <div class="w-12 h-12 mx-auto rounded-full bg-gray-100 text-gray-400 flex items-center justify-center mb-2"><i class="fa-solid fa-inbox"></i></div>
                <p class="text-sm font-semibold text-gray-600">${escHtml(title)}</p>
                ${sub ? `<p class="text-xs text-gray-400 mt-1">${escHtml(sub)}</p>` : ''}
            </div>`;
        }

        // Maikling progress: Prepared → Approved → Released
        function stepsHtml(row) {
            const step = (done, label) => `<span class="inline-flex items-center gap-1 ${done ? 'text-green-700' : 'text-gray-400'}">
                <i class="fa-solid ${done ? 'fa-circle-check' : 'fa-circle'} text-[9px]"></i>${label}</span>`;
            return `<span class="inline-flex items-center gap-2 flex-wrap">
                ${step(!!row.prepared_by || !!row.prepared_name, 'Prepared')}
                <span class="text-gray-300">›</span>${step(!!row.approved_by || !!row.approver_name, 'Approved')}
                <span class="text-gray-300">›</span>${step(row.voucher_status === 'released', 'Released')}</span>`;
        }

        // ─── Render list ─────────────────────────────────────
        function renderTable(data, q = '') {
            const cards = document.getElementById('voucher-cards');
            const info = document.getElementById('cvd-info');
            if (!loaded) return;
            const monthCount = allData.filter(inMonth).length;
            info.innerHTML = currentTab === 'action'
                ? `Showing <b class="text-gray-700">${data.length}</b> waiting for you · <span class="text-amber-700 font-semibold">all months</span>`
                : `Showing <b class="text-gray-700">${data.length}</b> of ${monthCount} voucher${monthCount !== 1 ? 's' : ''} · <b class="text-gray-700">${escHtml(monthLabel())}</b>`;

            if (!data.length) {
                cards.innerHTML = !allData.length ? emptyHtml('No vouchers yet.')
                    : currentTab === 'action' && !q ? emptyHtml('All caught up!', 'Nothing needs your action right now.')
                    : !monthCount && !q ? emptyHtml(`No vouchers in ${monthLabel()}.`, 'Use the arrows or the month list to check another month.')
                    : emptyHtml('No matching vouchers.', 'Try another search or tab.');
                return;
            }

            cards.innerHTML = data.map(row => {
                const id = Number(row.id);
                const total = sumItems(row.items);
                const st = voucherStatus(row);
                const mine = needsMyAction(row);
                return `
                <div data-id="${id}" data-selected="${String(id) === String(selectedId) ? 1 : 0}" class="cvd-row flex items-stretch gap-3 px-4 sm:px-5 py-3.5 hover:bg-gray-50 transition-colors cursor-pointer"
                    onclick="viewVoucherById(${id})">
                    <div class="w-1 rounded-full shrink-0 ${STATUS_BAR[st] ?? 'bg-gray-300'}"></div>
                    <div class="flex-1 min-w-0 flex flex-col lg:flex-row lg:items-center gap-2.5 lg:gap-5">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="notranslate font-mono text-[11px] font-bold text-amber-800 bg-amber-50 border border-amber-200 rounded px-1.5 py-0.5">${highlight(row.voucher_control_no ?? row.control_no, q)}</span>
                                ${st ? statusBadge(st) : '<span class="text-[10px] font-semibold text-gray-500 bg-gray-100 rounded-full px-2 py-0.5">Not submitted</span>'}
                                ${mine ? `<span class="inline-flex items-center gap-1 text-[10px] font-bold text-red-600 bg-red-50 border border-red-200 rounded-full px-2 py-0.5">
                                    <i class="fa-solid fa-bell text-[9px]"></i> ${IS_HEAD ? 'Needs your approval' : 'Needs preparing'}</span>` : ''}
                                <span class="cvd-opened-tag ${String(id) === String(selectedId) ? '' : 'hidden'} inline-flex items-center gap-1 text-[10px] font-bold text-amber-800 bg-amber-100 rounded-full px-2 py-0.5">
                                    <i class="fa-solid fa-eye text-[9px]"></i> Last opened</span>
                            </div>
                            <p class="mt-1.5 text-sm font-semibold text-gray-800 truncate">${highlight(row.voucher_title || row.voucher_payee || '—', q)}</p>
                            <p class="text-[13px] text-gray-600 break-words line-clamp-2">
                                ${row.voucher_title && row.voucher_payee ? `<span class="text-gray-500">${highlight(row.voucher_payee, q)} · </span>` : ''}${highlight(row.purpose ?? '—', q)}
                            </p>
                            <div class="flex items-center gap-x-3 gap-y-1 mt-1.5 flex-wrap text-[11px] text-gray-500">
                                <span class="whitespace-nowrap"><i class="fa-regular fa-calendar text-gray-400 mr-1"></i>${escHtml(fmtDate(row.date_requested) || row.date_requested || '—')}</span>
                                ${stepsHtml(row)}
                            </div>
                        </div>
                        <div class="flex items-center justify-between lg:justify-end gap-3 shrink-0">
                            <div class="lg:text-right">
                                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">Total</p>
                                <p class="font-mono text-sm font-bold ${st === 'released' ? 'text-green-700' : 'text-gray-800'}">₱ ${fmtMoney(total)}</p>
                            </div>
                            <button type="button" onclick="event.stopPropagation(); viewVoucherById(${id})"
                                class="inline-flex items-center gap-1.5 ${mine ? 'bg-amber-600 hover:bg-amber-700 text-white' : 'bg-white border border-gray-200 hover:bg-gray-50 text-gray-700'} text-xs font-semibold px-3.5 py-1.5 rounded-lg transition">
                                <i class="fa-solid ${mine ? 'fa-pen-to-square' : 'fa-receipt'}"></i> ${mine ? (IS_HEAD ? 'Review' : 'Prepare') : 'View'}
                            </button>
                        </div>
                    </div>
                </div>`;
            }).join('');
        }



        // ─── View modal ──────────────────────────────────────
        function viewVoucherById(id) {
            const row = allData.find(r => r.id == id);
            if (!row) return;
            markSelected(id);
            viewVoucher(row);
        }

        function viewVoucher(row) {
            currentRow = row;
            const items = row.items ?? [];
            const total = sumItems(items);

            // ── Desktop fields ──
            document.getElementById('v-control-no').textContent = row.voucher_control_no ?? row.control_no;
            document.getElementById('v-date').textContent = row.date_requested;
            document.getElementById('v-title').textContent = row.voucher_title ?? '';
            document.getElementById('v-second-no').textContent = row.voucher_payment_method ?? '—';
            document.getElementById('v-payee').textContent = row.voucher_payee ?? '';
            document.getElementById('v-payment-method').textContent = row.voucher_payment_method ?? '';
            document.getElementById('v-purpose').textContent = row.voucher_purpose ?? '';
            document.getElementById('v-amount-words').textContent = numberToWords(total);
            document.getElementById('v-total').textContent = 'PhP ' + fmtMoney(total);

            // Prepared
            document.getElementById('v-prepared').innerHTML = row.prepared_signature
                ? `<span class="relative inline-block">${sigImg(row.prepared_signature)}${escHtml(row.prepared_name)}</span>`
                : escHtml(row.prepared_name);
            const preparedAtStr = fmtDate(row.prepared_at);
            document.getElementById('v-prepared-at').textContent = preparedAtStr;

            // Approver
            document.getElementById('v-approver').innerHTML = row.approver_signature
                ? `<span class="relative inline-block">${sigImg(row.approver_signature)}${escHtml(row.approver_name)}</span>`
                : escHtml(row.approver_name);
            const approvedAtStr = row.approver_name && row.approved_at ? fmtDate(row.approved_at) : '';
            document.getElementById('v-approved-at').textContent = approvedAtStr;

            // Released
            document.getElementById('v-released').innerHTML = row.released_signature
                ? `<span class="relative inline-block">${sigImg(row.released_signature)}${escHtml(row.released_name)}</span>`
                : escHtml(row.released_name);
            const releasedAtStr = row.released_name && row.released_at ? fmtDate(row.released_at) : '';
            document.getElementById('v-released-at').textContent = releasedAtStr;

            // Received
            const receiverName = row.manual_receiver_name || row.receiver_name || '';
            const receivedAt = row.manual_receiver_date ? fmtDate(row.manual_receiver_date) : fmtDate(row.received_at);
            document.getElementById('v-receiver').textContent = receiverName;
            document.getElementById('v-received-at').textContent = receivedAt;

            // Desktop items
            let rows = '', filled = 0;
            items.forEach(item => {
                if (!item.description) return;
                filled++;
                rows += `<tr class="border-t border-gray-200">
                <td class="px-3 py-2 text-center text-xs text-gray-400 border-r border-gray-200">${filled}</td>
                <td class="px-4 py-2 border-r border-gray-200 text-sm">${escHtml(item.description)}${item.purpose ? ' — ' + escHtml(item.purpose) : ''}</td>
                <td class="px-4 py-2 text-right font-mono text-sm">${fmtMoney(item.amount)}</td>
            </tr>`;
            });
            for (let e = filled; e < 5; e++) {
                rows += `<tr class="border-t border-gray-200">
                <td class="px-3 py-2 text-center text-xs text-gray-300 border-r border-gray-200">${e + 1}</td>
                <td class="px-4 py-2 border-r border-gray-200" style="height:28px;"></td>
                <td class="px-4 py-2"></td>
            </tr>`;
            }
            document.getElementById('v-items-tbody').innerHTML = rows;

            // Desktop footer buttons
            const closeBtn = `<button onclick="closeVoucherModal()" class="text-sm text-gray-500 hover:text-gray-700 font-medium px-4 py-2 rounded transition-all border border-gray-200">Close</button>`;
            document.getElementById('v-footer-btns').innerHTML = closeBtn + buildActionBtn(row);

            // ── Mobile fields ──
            document.getElementById('v-title-m').textContent = row.voucher_title ?? '';
            document.getElementById('v-control-no-m').textContent = row.voucher_control_no ?? row.control_no;
            document.getElementById('v-date-m').textContent = row.date_requested;
            document.getElementById('v-second-no-m').textContent = row.voucher_payment_method ?? '—';
            document.getElementById('v-payee-m').textContent = row.voucher_payee ?? '';
            document.getElementById('v-payment-method-m').textContent = row.voucher_payment_method ?? '';
            document.getElementById('v-purpose-m').textContent = row.voucher_purpose ?? '';
            document.getElementById('v-amount-words-m').textContent = numberToWords(total);
            document.getElementById('v-total-m').textContent = 'PhP ' + fmtMoney(total);
            document.getElementById('v-prepared-m').textContent = row.prepared_name ?? '';
            document.getElementById('v-prepared-at-m').textContent = preparedAtStr;
            document.getElementById('v-approver-m').textContent = row.approver_name ?? '';
            document.getElementById('v-approved-at-m').textContent = approvedAtStr;
            document.getElementById('v-released-m').textContent = row.released_name ?? '';
            document.getElementById('v-released-at-m').textContent = releasedAtStr;
            document.getElementById('v-receiver-m').textContent = receiverName;
            document.getElementById('v-received-at-m').textContent = receivedAt;

            // Mobile items
            let mobileRows = '', mFilled = 0;
            items.forEach(item => {
                if (!item.description) return;
                mFilled++;
                mobileRows += `
            <div class="grid grid-cols-[32px_1fr_80px] px-3 py-2 items-start">
                <span class="text-[10px] text-gray-400 text-center">${mFilled}</span>
                <span class="text-xs text-gray-700">${escHtml(item.description)}${item.purpose ? ' — ' + escHtml(item.purpose) : ''}</span>
                <span class="text-xs font-mono text-right text-gray-800">${fmtMoney(item.amount)}</span>
            </div>`;
            });
            if (!mFilled) mobileRows = `<div class="px-3 py-3 text-xs text-gray-400 text-center">No items.</div>`;
            document.getElementById('v-items-mobile').innerHTML = mobileRows;

            // Mobile footer buttons
            const mCloseBtn = `<button onclick="closeVoucherModal()" class="w-full text-sm text-gray-500 font-medium py-2.5 rounded-xl border border-gray-200 bg-white">Close</button>`;
            document.getElementById('v-footer-btns-m').innerHTML = buildActionBtnMobile(row) + mCloseBtn;

            document.getElementById('voucher-modal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function buildActionBtn(row) {
            const vid = Number(row.voucher_id);
            if (POSITION === '<?= POSITION_STAFF ?>') {
                if (!row.prepared_by) {
                    return `<button type="button" data-action="1" onclick="markPrepared(${vid})"
                    class="flex items-center gap-2 bg-amber-600 hover:bg-amber-700 text-white text-xs font-semibold px-4 py-2 rounded-lg transition-all">
                    <i class="fa-solid fa-pen-to-square mr-1"></i>Mark as Prepared
                </button>`;
                }
            } else if (POSITION === '<?= POSITION_HEAD ?>') {
                if (row.voucher_status === 'voucher_approval' && !row.approved_by) {
                    if (!row.prepared_by) {
                        return `<span class="flex items-center gap-2 text-xs text-red-500 font-semibold px-4 py-2 bg-red-50 rounded-lg border border-red-200">
                        <i class="fa-solid fa-lock"></i>Waiting to be prepared
                    </span>`;
                    }
                    return `<button type="button" data-action="1" onclick="markApproved(${vid})"
                    class="flex items-center gap-2 bg-green-500 hover:bg-green-600 text-white text-xs font-semibold px-4 py-2 rounded-lg transition-all">
                    <i class="fa-solid fa-check mr-1"></i>Approve Voucher
                </button>`;
                }
            }

            if (row.voucher_status === 'released') {
                return `<button onclick="printVoucherById(${Number(row.id)})"
                class="flex items-center gap-2 bg-gray-800 hover:bg-gray-900 text-white text-xs font-semibold px-4 py-2 rounded-lg transition-all">
                <i class="fa-solid fa-print mr-1"></i>Print Voucher
            </button>`;
            } else if (row.voucher_status === 'ready_to_release') {
                return `<span class="text-xs text-blue-500 px-4 py-2 font-medium flex items-center gap-2 bg-blue-50 rounded-lg border border-blue-200">
                <i class="fa-solid fa-box"></i>Ready to Release
            </span>`;
            } else if (voucherStatus(row) === 'preparing') {
                return `<span class="text-xs text-orange-600 px-4 py-2 font-medium flex items-center gap-2 bg-orange-50 rounded-lg border border-orange-200">
                <i class="fa-solid fa-pen-to-square"></i>Waiting to be prepared
            </span>`;
            } else if (row.voucher_status === 'voucher_approval') {
                return `<span class="text-xs text-yellow-600 px-4 py-2 font-medium flex items-center gap-2 bg-yellow-50 rounded-lg border border-yellow-200">
                <i class="fa-solid fa-clock"></i>Waiting for approval
            </span>`;
            }
            return '';
        }

        function buildActionBtnMobile(row) {
            const vid = Number(row.voucher_id);
            if (POSITION === '<?= POSITION_STAFF ?>') {
                if (!row.prepared_by) {
                    return `<button type="button" data-action="1" onclick="markPrepared(${vid})"
                    class="w-full flex items-center justify-center gap-2 bg-amber-600 text-white text-sm font-semibold py-2.5 rounded-xl transition-all">
                    <i class="fa-solid fa-pen-to-square"></i>Mark as Prepared
                </button>`;
                }
            } else if (POSITION === '<?= POSITION_HEAD ?>') {
                if (row.voucher_status === 'voucher_approval' && !row.approved_by) {
                    if (!row.prepared_by) {
                        return `<span class="flex items-center justify-center gap-2 text-xs text-red-500 font-semibold py-2.5 bg-red-50 rounded-xl border border-red-200">
                        <i class="fa-solid fa-lock"></i>Waiting to be prepared
                    </span>`;
                    }
                    return `<button type="button" data-action="1" onclick="markApproved(${vid})"
                    class="w-full flex items-center justify-center gap-2 bg-green-500 text-white text-sm font-semibold py-2.5 rounded-xl transition-all">
                    <i class="fa-solid fa-check"></i>Approve Voucher
                </button>`;
                }
            }

            if (row.voucher_status === 'released') {
                return `<button onclick="printVoucherById(${Number(row.id)})"
                class="w-full flex items-center justify-center gap-2 bg-gray-800 text-white text-sm font-semibold py-2.5 rounded-xl transition-all">
                <i class="fa-solid fa-print"></i>Print Voucher
            </button>`;
            } else if (row.voucher_status === 'ready_to_release') {
                return `<span class="flex items-center justify-center gap-2 text-xs text-blue-600 py-2.5 font-medium bg-blue-50 rounded-xl border border-blue-200">
                <i class="fa-solid fa-box"></i>Ready to Release
            </span>`;
            } else if (voucherStatus(row) === 'preparing') {
                return `<span class="flex items-center justify-center gap-2 text-xs text-orange-600 py-2.5 font-medium bg-orange-50 rounded-xl border border-orange-200">
                <i class="fa-solid fa-pen-to-square"></i>Waiting to be prepared
            </span>`;
            } else if (row.voucher_status === 'voucher_approval') {
                return `<span class="flex items-center justify-center gap-2 text-xs text-yellow-600 py-2.5 font-medium bg-yellow-50 rounded-xl border border-yellow-200">
                <i class="fa-solid fa-clock"></i>Waiting for approval
            </span>`;
            }
            return '';
        }

        function closeVoucherModal() {
            document.getElementById('voucher-modal').classList.add('hidden');
            document.body.style.overflow = '';
            currentRow = null;
        }
        // Isara kapag pinindot ang madilim na background
        document.getElementById('voucher-modal').addEventListener('click', function (e) {
            if (e.target === this) closeVoucherModal();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !document.getElementById('voucher-modal').classList.contains('hidden')) {
                closeVoucherModal();
            }
        });

        // ─── Actions ─────────────────────────────────────────
        // Habang nagse-save: i-disable ang lahat ng action button sa modal
        function setModalBusy(busy, label) {
            document.querySelectorAll('#v-footer-btns button, #v-footer-btns-m button').forEach(b => {
                if (!b.dataset.action) return;
                b.disabled = busy;
                b.classList.toggle('opacity-60', busy);
                b.classList.toggle('cursor-wait', busy);
                if (busy) { b.dataset.html = b.innerHTML; b.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${label}`; }
                else if (b.dataset.html) b.innerHTML = b.dataset.html;
            });
        }

        function markPrepared(voucherId) {
            if (savingVids.has(voucherId)) return;
            savingVids.add(voucherId); setModalBusy(true, 'Saving…');
            fetch(API + '/cashvoucherprepared', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ voucher_id: voucherId })
            }).then(res => res.json()).then(data => {
                if (data.success) { closeVoucherModal(); showToast('Voucher marked as prepared!'); fetchVouchers(true); }
                else showToast(data.error ?? data.message ?? 'Failed to mark as prepared.', 'error');
            }).catch(() => showToast('Network error. Please try again.', 'error'))
              .finally(() => { savingVids.delete(voucherId); setModalBusy(false); });
        }

        function markApproved(voucherId) {
            if (savingVids.has(voucherId)) return;
            savingVids.add(voucherId); setModalBusy(true, 'Approving…');
            fetch(API + '/cashvoucherapproved', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ voucher_id: voucherId })
            }).then(res => res.json()).then(data => {
                if (data.success) { closeVoucherModal(); showToast('Voucher approved successfully!'); fetchVouchers(true); }
                else showToast(data.error ?? data.message ?? 'Failed.', 'error');
            }).catch(() => showToast('Network error. Please try again.', 'error'))
              .finally(() => { savingVids.delete(voucherId); setModalBusy(false); });
        }

        function showToast(message, type = 'success') {
            const colors = { success: 'bg-green-500', error: 'bg-red-500', info: 'bg-blue-500' };
            const toast = document.createElement('div');
            toast.className = `fixed bottom-6 left-4 right-4 sm:left-auto sm:right-6 z-[999] flex items-center gap-3 ${colors[type] ?? colors.success} text-white text-sm font-semibold px-5 py-3 rounded-xl shadow-lg transition-all duration-300 opacity-0 translate-y-2`;
            toast.innerHTML = `<i class="fa-solid ${type === 'success' ? 'fa-circle-check' : 'fa-circle-xmark'}"></i> ${escHtml(message)}`;
            document.body.appendChild(toast);
            requestAnimationFrame(() => toast.classList.remove('opacity-0', 'translate-y-2'));
            setTimeout(() => { toast.classList.add('opacity-0', 'translate-y-2'); setTimeout(() => toast.remove(), 300); }, 3000);
        }

        // ─── Print ───────────────────────────────────────────
        function printVoucherById(id) {
            const row = allData.find(r => r.id == id);
            if (row) printVoucher(row);
        }

        function printVoucher(row) {
            const items = row.items ?? [];
            const td = 'border:1px solid #ccc;padding:5px;font-size:11px;';
            let total = 0, itemRows = '', filled = 0;
            items.forEach(item => {
                if (!item.description) return;
                filled++;
                const amt = parseFloat(item.amount || 0);
                total += amt;
                itemRows += `<tr>
                <td style="text-align:center;${td}">${filled}</td>
                <td style="${td}">${escHtml(item.description)}${item.purpose ? ' — ' + escHtml(item.purpose) : ''}</td>
                <td style="text-align:right;${td}font-family:monospace;">${fmtMoney(amt)}</td>
            </tr>`;
            });
            for (let e = filled; e < 5; e++) {
                itemRows += `<tr>
                <td style="text-align:center;${td}color:#ccc;">${e + 1}</td>
                <td style="${td}height:24px;"></td>
                <td style="${td}"></td>
            </tr>`;
            }

            const preparedAt = fmtDate(row.prepared_at, 'long');
            const approvedAt = fmtDate(row.approved_at, 'long');
            const releasedAt = fmtDate(row.released_at, 'long');
            const receiverName = row.manual_receiver_name || row.receiver_name || '';
            const receivedAt = row.manual_receiver_date ? fmtDate(row.manual_receiver_date) : fmtDate(row.received_at);
            const voucherNo = row.voucher_control_no ?? row.control_no;

            const sigCell = (sig, name, when, last = false) => `
            <div style="padding:10px 8px;${last ? '' : 'border-right:1px solid #e5e7eb;'}display:flex;flex-direction:column;">
                <div style="height:65px;display:flex;align-items:flex-end;justify-content:center;margin-bottom:4px;">
                    ${sig ? `<img src="${escHtml(sig)}" style="max-height:65px;max-width:120px;object-fit:contain;">` : ''}
                </div>
                <div style="border-top:1px solid #999;padding-top:4px;text-align:center;">
                    <div style="font-size:10px;font-weight:600;">${escHtml(name)}</div>
                    <div style="font-size:9px;color:#888;">${escHtml(when)}</div>
                </div>
            </div>`;

            const html = `<!DOCTYPE html><html><head><meta charset="UTF-8">
        <title>Cash Voucher - ${escHtml(voucherNo)}</title>
        <style>
            *{box-sizing:border-box;}
            body{font-family:Arial,sans-serif;font-size:11px;margin:0;padding:15px;}
            table{width:100%;border-collapse:collapse;}
            .orange-bg{background:#f97316;color:white;-webkit-print-color-adjust:exact;print-color-adjust:exact;}
            @page{size:A4 portrait;margin:1cm;}
        </style></head><body>
        <div style="border:2px solid #f97316;border-radius:2px;">
            <div style="display:grid;grid-template-columns:1fr auto;border-bottom:2px solid #f97316;">
                <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;">
                    <img src="${API}/icon/logo.webp" style="width:48px;height:48px;object-fit:contain;">
                    <div>
                        <div style="font-weight:bold;font-size:12px;text-transform:uppercase;">Noblehome Construction Corporation</div>
                        <div style="font-size:9px;color:#666;margin-top:2px;">1181 MC Premiere Bldg., EDSA Balintawak Quezon City</div>
                        <div style="font-size:9px;color:#666;">noblehomeconsl.ph@gmail.com | Tel. No. 02-88221295 | Cell. No. 0968-591-6544</div>
                    </div>
                </div>
                <div style="text-align:right;padding:10px 14px;border-left:1px solid #f97316;">
                    <div style="font-weight:bold;font-size:16px;text-transform:uppercase;letter-spacing:2px;">Cash Voucher</div>
                    <div style="font-size:9px;color:#888;text-transform:uppercase;margin-bottom:6px;">${escHtml(row.voucher_title)}</div>
                    <table style="width:auto;margin-left:auto;">
                        <tr>
                            <td class="orange-bg" style="font-size:9px;font-weight:bold;padding:3px 10px;text-align:center;border-right:1px solid #ea6c00;">Voucher No.</td>
                            <td class="orange-bg" style="font-size:9px;font-weight:bold;padding:3px 10px;text-align:center;">Date:</td>
                        </tr>
                        <tr>
                            <td style="border:1px solid #d1d5db;font-family:monospace;font-size:10px;padding:4px 10px;text-align:center;background:#f9fafb;border-right:0;">${escHtml(voucherNo)}</td>
                            <td style="border:1px solid #d1d5db;font-family:monospace;font-size:10px;padding:4px 10px;text-align:center;background:#f9fafb;">${escHtml(row.date_requested)}</td>
                        </tr>
                    </table>
                </div>
            </div>
            <div style="padding:10px 14px;border-bottom:1px solid #e5e7eb;">
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:6px;">
                    <div style="display:flex;gap:6px;align-items:center;">
                        <span style="font-size:9px;font-weight:bold;text-transform:uppercase;color:#555;width:90px;">Payee</span>
                        <span style="color:#aaa;margin-right:4px;">:</span>
                        <span style="border-bottom:1px solid #aaa;flex:1;font-size:11px;">${escHtml(row.voucher_payee)}</span>
                    </div>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <span style="font-size:9px;font-weight:bold;text-transform:uppercase;color:#555;white-space:nowrap;">Payment For</span>
                        <span style="color:#aaa;margin:0 4px;">:</span>
                        <span style="border-bottom:1px solid #aaa;flex:1;font-size:11px;">${escHtml(row.voucher_purpose)}</span>
                    </div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                    <div style="display:flex;gap:6px;align-items:center;">
                        <span style="font-size:9px;font-weight:bold;text-transform:uppercase;color:#555;width:90px;">Payment Method</span>
                        <span style="color:#aaa;margin-right:4px;">:</span>
                        <span style="border-bottom:1px solid #aaa;flex:1;font-size:11px;">${escHtml(row.voucher_payment_method)}</span>
                    </div>
                    <div style="display:flex;gap:6px;align-items:center;">
                        <span style="font-size:9px;font-weight:bold;text-transform:uppercase;color:#555;white-space:nowrap;">Amount in Words</span>
                        <span style="color:#aaa;margin:0 4px;">:</span>
                        <span style="border-bottom:1px solid #aaa;flex:1;font-size:11px;font-style:italic;">${escHtml(numberToWords(total))}</span>
                    </div>
                </div>
            </div>
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
                                    Payment Method No.: <span style="font-family:monospace;font-size:10px;color:#374151;">${escHtml(row.voucher_payment_method ?? '—')}</span>
                                </div>
                                <div style="font-weight:bold;font-size:11px;">Total Amount: <span style="font-family:monospace;">${fmtMoney(total)}</span></div>
                            </div>
                        </td>
                    </tr>
                </tfoot>
            </table>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;border-top:2px solid #f97316;">
                <div class="orange-bg" style="font-size:9px;font-weight:bold;text-align:center;padding:5px;border-right:1px solid #ea6c00;">Prepared By</div>
                <div class="orange-bg" style="font-size:9px;font-weight:bold;text-align:center;padding:5px;border-right:1px solid #ea6c00;">Approved By</div>
                <div class="orange-bg" style="font-size:9px;font-weight:bold;text-align:center;padding:5px;border-right:1px solid #ea6c00;">Released By</div>
                <div class="orange-bg" style="font-size:9px;font-weight:bold;text-align:center;padding:5px;">Received By</div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;border-bottom:1px solid #e5e7eb;">
                ${sigCell(row.prepared_signature, row.prepared_name ?? '', preparedAt)}
                ${sigCell(row.approver_signature, row.approver_name ?? '', approvedAt)}
                ${sigCell(row.released_signature, row.released_name ?? '', releasedAt)}
                ${sigCell('', receiverName, receivedAt, true)}
            </div>
            <div style="padding:8px 14px;">
                <span style="font-size:9px;font-style:italic;color:#888;">Received the above stated amount in full settlement.</span>
            </div>
        </div>
        <script>window.onload=function(){window.print();window.onafterprint=function(){window.close();};};<\/script>
        </body></html>`;

            const w = window.open('', '_blank');
            if (!w) { showToast('Allow pop-ups to print.', 'error'); return; }
            w.document.write(html);
            w.document.close();
        }


        function fetchVouchers(force = false) {
            return fetch(API + '/cashvoucherfetchall', force ? { cache: 'no-store' } : {})
                .then(res => {
                    document.getElementById('last-updated').textContent = 'Updated ' + new Date().toLocaleTimeString('en-PH');
                    if (res.status === 304) return null; // walang nagbago
                    return res.text();
                })
                .then(text => {
                    if (text === null) { if (force) rerender(); return; }
                    if (!force && text === lastText) return;
                    let data;
                    try { data = JSON.parse(text); } catch (e) { return; }
                    if (!Array.isArray(data)) return;
                    lastText = text;
                    allData = data;
                    const first = !loaded;
                    loaded = true;
                    buildMonthSelect();
                    // Kung walang kailangang aksyon sa unang load, ipakita na lang ang lahat
                    if (first && currentTab === 'action' && !actionTabTouched && !allData.some(needsMyAction)
                        && !new URLSearchParams(location.search).get('highlight')) {
                        setTab('all'); actionTabTouched = false;
                    } else {
                        rerender();
                    }
                    if (first) checkHighlight();
                })
                .catch(err => {
                    console.error('Fetch error:', err);
                    if (!loaded) {
                        document.getElementById('voucher-cards').innerHTML =
                            emptyHtml('Could not load vouchers.', 'Check your connection, then refresh the page.');
                    }
                });
        }

        function rerender() {
            updateCounts();
            const q = document.getElementById('search-input').value.trim().toLowerCase();
            renderTable(allData.filter(row => tabMatch(row, currentTab) && (!q || matchesQuery(row, q))), q);
        }

        document.getElementById('search-input').addEventListener('input', rerender);

        // ─── Highlight (galing sa notification link) ─────────
        function checkHighlight() {
            const highlightId = new URLSearchParams(window.location.search).get('highlight');
            if (!highlightId) return;
            // siguraduhing makikita ang row kahit nasa ibang tab o ibang buwan
            if (!document.querySelector(`#voucher-cards .cvd-row[data-id="${CSS.escape(highlightId)}"]`)) {
                const target = allData.find(r => String(r.id) === String(highlightId));
                const k = target ? rowMonth(target) : '';
                if (/^\d{4}-\d{2}$/.test(k)) { viewYear = +k.slice(0, 4); viewMonth = +k.slice(5, 7) - 1; buildMonthSelect(); }
                setTab('all');
            }
            let tries = 0;
            const interval = setInterval(() => {
                tries++;
                const row = document.querySelector(`#voucher-cards .cvd-row[data-id="${CSS.escape(highlightId)}"]`);
                if (row) {
                    clearInterval(interval);
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    row.classList.add('cvd-row-flash');
                    setTimeout(() => row.classList.remove('cvd-row-flash'), 3200);
                }
                if (tries >= 25) clearInterval(interval);
            }, 200);
        }

        // ─── Init ────────────────────────────────────────────
        // Tandaan ang huling buwan na tiningnan (sa tab na ito lang)
        try {
            const saved = sessionStorage.getItem('cvd-month');
            if (saved && /^\d{4}-\d{2}$/.test(saved)) { viewYear = +saved.slice(0, 4); viewMonth = +saved.slice(5, 7) - 1; }
        } catch (e) { }
        buildMonthSelect();
        fetchVouchers(true);

        // ─── Realtime (koneksyon ng sidebar: window.NH_RT) ───
        // Signal ng Accounting (submit / prepare / approve / release) → kunin agad.
        // Polling = fallback lang: 15s kapag hindi Live, 90s kapag Live.
        const CVD_POLL_MS = 15000, CVD_POLL_LIVE_MS = 90000;
        const cvdLive = () => !!(window.NH_RT && window.NH_RT.enabled && window.NH_RT.isLive());
        let cvdLastPoll = Date.now(), cvdMissed = false, cvdRtTimer = null;

        function cvdRefresh(force) {
            cvdLastPoll = Date.now();
            cvdMissed = false;
            return fetchVouchers(force);
        }

        if (window.NH_RT && window.NH_RT.enabled) {
            window.NH_RT.on('*', 'changed', () => {
                clearTimeout(cvdRtTimer);
                cvdRtTimer = setTimeout(() => {
                    if (document.hidden) { cvdMissed = true; return; } // kukunin pagbalik sa tab
                    cvdRefresh(true);
                }, 500);
            });
            window.NH_RT.onLive(live => {
                const el = document.getElementById('cvd-live-dot');
                if (el) el.className = 'w-2 h-2 rounded-full ' + (live ? 'bg-green-500 animate-pulse' : 'bg-amber-400');
                if (live && loaded) cvdRefresh(true); // habol sa na-miss habang offline
            });
        }

        setInterval(() => {
            if (document.visibilityState !== 'visible') return;
            if (Date.now() - cvdLastPoll < (cvdLive() ? CVD_POLL_LIVE_MS : CVD_POLL_MS) - 1000) return;
            cvdRefresh(false);
        }, 5000);

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') cvdRefresh(cvdMissed);
        });
    </script>

</body>

</html>