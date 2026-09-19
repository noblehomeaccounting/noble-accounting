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

<body class="bg-slate-100">
    <main id="main-content" class="md:ml-56 pt-20 md:pt-5 min-h-screen p-3 md:p-8 transition-all duration-300">

        <div class="mb-4 md:mb-6">
            <h1 class="text-lg md:text-xl font-bold text-gray-800">Cash Voucher Dashboard</h1>
            <p class="text-xs md:text-sm text-gray-400 mt-1">Review and process cash vouchers</p>
        </div>

        <div class="bg-white rounded-lg shadow-sm border border-gray-100 overflow-hidden">

            <!-- Table Header / Search -->
            <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3 border-b border-gray-100">
                <span class="text-sm font-semibold text-gray-700">Voucher Records</span>
                <div class="flex items-center gap-2">
                    <div class="relative">
                        <i
                            class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                        <input type="text" id="search-input" placeholder="Search..."
                            class="pl-8 pr-3 py-1.5 text-xs border border-gray-200 rounded-full outline-none focus:border-amber-400 transition-all w-36 md:w-48">
                    </div>
                    <span id="last-updated" class="text-[10px] text-gray-400 hidden sm:inline"></span>
                    <div class="w-2 h-2 rounded-full bg-green-400 animate-pulse flex-shrink-0"></div>
                </div>
            </div>

            <!-- Desktop Table -->
            <div class="hidden md:block overflow-x-auto">
                <div class="max-h-[500px] overflow-y-auto scrollbar-thin">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 z-10">
                            <tr class="bg-gray-50 text-[11px] font-semibold text-gray-400 uppercase tracking-widest">
                                <th class="px-5 py-3 text-left">Voucher No.</th>
                                <th class="px-5 py-3 text-left">Title</th>
                                <th class="px-5 py-3 text-left">Payee</th>
                                <th class="px-5 py-3 text-left">Payment For</th>
                                <th class="px-5 py-3 text-left">Date</th>
                                <th class="px-5 py-3 text-left">Total Amount</th>
                                <th class="px-5 py-3 text-left">Status</th>
                                <th class="px-5 py-3 text-left">Action</th>
                            </tr>
                        </thead>
                        <tbody id="voucher-tbody">
                            <tr>
                                <td colspan="8" class="px-5 py-8 text-center text-gray-400 text-sm">
                                    <i class="fa-solid fa-spinner fa-spin mr-2"></i> Loading...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Mobile Cards -->
            <div class="md:hidden" id="voucher-cards">
                <div class="px-4 py-8 text-center text-gray-400 text-sm">
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

                <!-- Signatures -->
                <div class="grid grid-cols-4 border-t-2 border-orange-400">
                    <div
                        class="bg-orange-500 text-white text-[10px] font-bold uppercase tracking-wider text-center py-1.5 border-r border-orange-400">
                        Prepared By</div>
                    <div
                        class="bg-orange-500 text-white text-[10px] font-bold uppercase tracking-wider text-center py-1.5 border-r border-orange-400">
                        Certified Thru Correct</div>
                    <div
                        class="bg-orange-500 text-white text-[10px] font-bold uppercase tracking-wider text-center py-1.5 border-r border-orange-400">
                        Approved By</div>
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
                        <p class="text-[10px] text-gray-500">Name: <span id="v-certified"
                                class="text-gray-800 font-semibold text-xs"></span></p>
                        <p class="text-[10px] text-gray-500 mt-3">Date: <span id="v-certified-at"
                                class="text-gray-600 text-xs"></span></p>
                    </div>
                    <div class="px-4 py-4 border-r border-gray-200">
                        <p class="text-[10px] text-gray-500">Name: <span id="v-approver"
                                class="text-gray-800 font-semibold text-xs"></span></p>
                        <p class="text-[10px] text-gray-500 mt-3">Date: <span id="v-approved-at"
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
                        <span class="text-[9px] font-bold text-white uppercase tracking-wider">Certified Thru
                            Correct</span>
                    </div>
                    <div class="px-3 py-3 border-r border-b border-gray-100 min-h-[70px] flex flex-col justify-end">
                        <p id="v-prepared-m" class="text-xs font-semibold text-gray-800"></p>
                        <p id="v-prepared-at-m" class="text-[9px] text-gray-400 mt-0.5"></p>
                    </div>
                    <div class="px-3 py-3 border-b border-gray-100 min-h-[70px] flex flex-col justify-end">
                        <p id="v-certified-m" class="text-xs font-semibold text-gray-800"></p>
                        <p id="v-certified-at-m" class="text-[9px] text-gray-400 mt-0.5"></p>
                    </div>
                    <div class="border-r border-gray-100 bg-orange-500 py-1 text-center">
                        <span class="text-[9px] font-bold text-white uppercase tracking-wider">Approved By</span>
                    </div>
                    <div class="bg-orange-500 py-1 text-center">
                        <span class="text-[9px] font-bold text-white uppercase tracking-wider">Received By</span>
                    </div>
                    <div class="px-3 py-3 border-r border-gray-100 min-h-[70px] flex flex-col justify-end">
                        <p id="v-approver-m" class="text-xs font-semibold text-gray-800"></p>
                        <p id="v-approved-at-m" class="text-[9px] text-gray-400 mt-0.5"></p>
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
            background: #ef4444;
            border-radius: 50%;
            animation: badgePulse 0.8s ease-in-out 6;
            margin-right: 6px;
            vertical-align: middle;
            flex-shrink: 0;
        }
    </style>

    <script>
        const POSITION = '<?= htmlspecialchars($position) ?>';
        let allData = [];
        let currentRow = null;

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
            return `<span class="${map[status] ?? 'bg-gray-100 text-gray-500'} text-[10px] font-semibold px-2 py-1 rounded-full uppercase tracking-wide">${label[status] ?? status}</span>`;
        }

        function highlight(text, q) {
            if (!text) return '';
            if (!q) return text;
            const escaped = q.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
            return String(text).replace(new RegExp(`(${escaped})`, 'gi'),
                '<mark class="bg-yellow-200 text-yellow-900 rounded px-0.5">$1</mark>');
        }

        function renderTable(data, q = '') {
            const tbody = document.getElementById('voucher-tbody');
            const cards = document.getElementById('voucher-cards');

            if (!data.length) {
                tbody.innerHTML = `<tr><td colspan="8" class="px-5 py-8 text-center text-gray-400">No vouchers yet.</td></tr>`;
                cards.innerHTML = `<div class="px-4 py-8 text-center text-gray-400 text-sm">No vouchers yet.</div>`;
                return;
            }

            tbody.innerHTML = data.map(row => {
                const items = row.items ?? [];
                const total = items.reduce((sum, i) => sum + (parseFloat(i.amount) || 0), 0);
                return `
                <tr data-id="${row.id}" class="border-t border-gray-100 hover:bg-gray-50 transition-colors">
                    <td class="px-5 py-3 font-mono text-xs text-blue-500 cursor-pointer underline"
                        onclick="viewVoucher(${JSON.stringify(row).replace(/"/g, '&quot;')})">
                        ${highlight(row.control_no, q)}
                    </td>
                    <td class="px-5 py-3 text-xs text-gray-700">${highlight(row.voucher_title ?? '—', q)}</td>
                    <td class="px-5 py-3 text-gray-800">${highlight(row.voucher_payee ?? '—', q)}</td>
                    <td class="px-5 py-3 text-gray-600">${highlight(row.purpose, q)}</td>
                    <td class="px-5 py-3 text-xs text-gray-400 font-mono">${row.date_requested}</td>
                    <td class="px-5 py-3 font-mono text-xs font-semibold text-gray-700">
                        PHP ${total.toLocaleString('en-PH', { minimumFractionDigits: 2 })}
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

            cards.innerHTML = data.map(row => {
                const items = row.items ?? [];
                const total = items.reduce((sum, i) => sum + (parseFloat(i.amount) || 0), 0);
                const isComplete = row.voucher_status === 'released';
                const isPending = !row.voucher_status;
                const barColor = isComplete ? 'bg-green-400'
                    : row.voucher_status === 'voucher_approval' ? 'bg-yellow-400'
                        : row.voucher_status === 'ready_to_release' ? 'bg-blue-400'
                            : 'bg-gray-200';
                const rowBg = isComplete ? 'bg-green-50' : 'bg-white';

                return `
                <div data-id="${row.id}"
                    class="flex items-center gap-3 px-4 py-3 border-b border-gray-100 transition-colors ${rowBg} active:bg-gray-50">
                    <div class="w-1 self-stretch rounded-full flex-shrink-0 ${barColor}"></div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center justify-between gap-2 mb-0.5">
                            <span class="font-mono text-[10px] font-bold text-blue-500 truncate">${highlight(row.control_no, q)}</span>
                            ${row.voucher_status ? statusBadge(row.voucher_status) : '<span class="text-[9px] text-gray-400 border border-gray-200 rounded-full px-1.5 py-0.5 flex-shrink-0">Not submitted</span>'}
                        </div>
                        <div class="text-sm font-semibold text-gray-800 truncate leading-tight">${highlight(row.voucher_title ?? '—', q)}</div>
                        <div class="text-[11px] text-gray-500 truncate">${highlight(row.voucher_payee ?? '—', q)}</div>
                        <div class="text-[11px] text-gray-400 truncate">${highlight(row.purpose ?? '—', q)}</div>
                        <div class="mt-1 text-[10px] text-gray-400 font-mono">${row.date_requested}</div>
                    </div>
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

        function viewVoucher(row) {
            currentRow = row;
            const items = row.items ?? [];
            const total = items.reduce((sum, i) => sum + (parseFloat(i.amount) || 0), 0);

            // ── Desktop fields ──
            document.getElementById('v-control-no').textContent = row.voucher_control_no ?? row.control_no;
            document.getElementById('v-date').textContent = row.date_requested;
            document.getElementById('v-title').textContent = row.voucher_title ?? '';
            document.getElementById('v-second-no').textContent = row.voucher_payment_method ?? '—';
            document.getElementById('v-payee').textContent = row.voucher_payee ?? '';
            document.getElementById('v-payment-method').textContent = row.voucher_payment_method ?? '';
            document.getElementById('v-purpose').textContent = row.voucher_purpose ?? '';
            document.getElementById('v-amount-words').textContent = numberToWords(total);
            document.getElementById('v-total').textContent = 'PhP ' + total.toLocaleString('en-PH', { minimumFractionDigits: 2 });

            // Prepared
            const prepSig = row.prepared_signature ?? '';
            document.getElementById('v-prepared').innerHTML = prepSig
                ? `<span class="relative inline-block"><img src="${prepSig}" style="position:absolute;bottom:-50px;left:80px;transform:translateX(-50%);height:90px;max-width:160px;object-fit:contain;z-index:10;pointer-events:none;">${row.prepared_name ?? ''}</span>`
                : (row.prepared_name ?? '');
            const preparedAtStr = row.prepared_at
                ? new Date(row.prepared_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '';
            document.getElementById('v-prepared-at').textContent = preparedAtStr;

            // Certified
            const certSig = row.certified_signature ?? '';
            document.getElementById('v-certified').innerHTML = certSig
                ? `<span class="relative inline-block"><img src="${certSig}" style="position:absolute;bottom:-50px;left:80px;transform:translateX(-50%);height:90px;max-width:160px;object-fit:contain;z-index:10;pointer-events:none;">${row.certified_name ?? ''}</span>`
                : (row.certified_name ?? '');
            const certifiedAtStr = row.certified_name && row.certified_at
                ? new Date(row.certified_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '';
            document.getElementById('v-certified-at').textContent = certifiedAtStr;

            // Approver
            const apprSig = row.approver_signature ?? '';
            document.getElementById('v-approver').innerHTML = apprSig
                ? `<span class="relative inline-block"><img src="${apprSig}" style="position:absolute;bottom:-50px;left:80px;transform:translateX(-50%);height:90px;max-width:160px;object-fit:contain;z-index:10;pointer-events:none;">${row.approver_name ?? ''}</span>`
                : (row.approver_name ?? '');
            const approvedAtStr = row.approver_name && row.approved_at
                ? new Date(row.approved_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '';
            document.getElementById('v-approved-at').textContent = approvedAtStr;

            const receiverName = row.manual_receiver_name || row.receiver_name || '';
            const receivedAt = row.manual_receiver_date
                ? new Date(row.manual_receiver_date).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
                : row.received_at
                    ? new Date(row.received_at.replace(' ', 'T')).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
                    : '';
            document.getElementById('v-receiver').textContent = receiverName;
            document.getElementById('v-received-at').textContent = receivedAt;

            // Desktop items
            let rows = '', filled = 0;
            items.forEach(item => {
                if (!item.description) return;
                filled++;
                const amt = parseFloat(item.amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 });
                rows += `<tr class="border-t border-gray-200">
                    <td class="px-3 py-2 text-center text-xs text-gray-400 border-r border-gray-200">${filled}</td>
                    <td class="px-4 py-2 border-r border-gray-200 text-sm">${item.description}${item.purpose ? ' — ' + item.purpose : ''}</td>
                    <td class="px-4 py-2 text-right font-mono text-sm">${amt}</td>
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

            // ── Desktop footer buttons ──
            const footerBtns = document.getElementById('v-footer-btns');
            const closeBtn = `<button onclick="closeVoucherModal()" class="text-sm text-gray-500 hover:text-gray-700 font-medium px-4 py-2 rounded transition-all border border-gray-200">Close</button>`;
            footerBtns.innerHTML = closeBtn + buildActionBtn(row);

            // ── Mobile fields ──
            document.getElementById('v-title-m').textContent = row.voucher_title ?? '';
            document.getElementById('v-control-no-m').textContent = row.voucher_control_no ?? row.control_no;
            document.getElementById('v-date-m').textContent = row.date_requested;
            document.getElementById('v-second-no-m').textContent = row.voucher_payment_method ?? '—';
            document.getElementById('v-payee-m').textContent = row.voucher_payee ?? '';
            document.getElementById('v-payment-method-m').textContent = row.voucher_payment_method ?? '';
            document.getElementById('v-purpose-m').textContent = row.voucher_purpose ?? '';
            document.getElementById('v-amount-words-m').textContent = numberToWords(total);
            document.getElementById('v-total-m').textContent = 'PhP ' + total.toLocaleString('en-PH', { minimumFractionDigits: 2 });
            document.getElementById('v-prepared-m').textContent = row.prepared_name ?? '';
            document.getElementById('v-prepared-at-m').textContent = preparedAtStr;
            document.getElementById('v-certified-m').textContent = row.certified_name ?? '';
            document.getElementById('v-certified-at-m').textContent = certifiedAtStr;
            document.getElementById('v-approver-m').textContent = row.approver_name ?? '';
            document.getElementById('v-approved-at-m').textContent = approvedAtStr;
            document.getElementById('v-receiver-m').textContent = receiverName;
            document.getElementById('v-received-at-m').textContent = receivedAt;

            // Mobile items
            const mobileItems = document.getElementById('v-items-mobile');
            let mobileRows = '';
            let mFilled = 0;
            items.forEach(item => {
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

            // ── Mobile footer buttons ──
            const mFooter = document.getElementById('v-footer-btns-m');
            const mCloseBtn = `<button onclick="closeVoucherModal()" class="w-full text-sm text-gray-500 font-medium py-2.5 rounded-xl border border-gray-200 bg-white">Close</button>`;
            mFooter.innerHTML = buildActionBtnMobile(row) + mCloseBtn;

            document.getElementById('voucher-modal').classList.remove('hidden');
        }

        function buildActionBtn(row) {
            if (POSITION === '<?= POSITION_STAFF ?>') {
                if (!row.prepared_by) {
                    return `<button onclick="markPrepared(${row.voucher_id})"
                        class="flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-white text-xs font-semibold px-4 py-2 rounded-lg transition-all">
                        <i class="fa-solid fa-pen-to-square mr-1"></i>Mark as Prepared
                    </button>`;
                }
            } else if (POSITION === '<?= POSITION_HEAD ?>') {
                if (row.voucher_status === 'voucher_approval' && !row.approved_by) {
                    if (!row.prepared_by) {
                        return `<span class="flex items-center gap-2 text-xs text-red-500 font-semibold px-4 py-2 bg-red-50 rounded-lg border border-red-200">
                            <i class="fa-solid fa-lock"></i>Waiting to be prepared
                        </span>`;
                    } else {
                        return `<button onclick="markApproved(${row.voucher_id})"
                            class="flex items-center gap-2 bg-green-500 hover:bg-green-600 text-white text-xs font-semibold px-4 py-2 rounded-lg transition-all">
                            <i class="fa-solid fa-check mr-1"></i>Approve Voucher
                        </button>`;
                    }
                }
            }
            if (row.voucher_status === 'released') {
                return `<button onclick="printVoucher(${JSON.stringify(row).replace(/"/g, '&quot;')})"
                    class="flex items-center gap-2 bg-gray-800 hover:bg-gray-900 text-white text-xs font-semibold px-4 py-2 rounded-lg transition-all">
                    <i class="fa-solid fa-print mr-1"></i>Print Voucher
                </button>`;
            } else if (row.voucher_status === 'ready_to_release') {
                return `<span class="text-xs text-blue-500 px-4 py-2 font-medium flex items-center gap-2 bg-blue-50 rounded-lg border border-blue-200">
                    <i class="fa-solid fa-box"></i>Ready to Release
                </span>`;
            } else if (row.voucher_status === 'voucher_approval') {
                return `<span class="text-xs text-yellow-600 px-4 py-2 font-medium flex items-center gap-2 bg-yellow-50 rounded-lg border border-yellow-200">
                    <i class="fa-solid fa-clock"></i>Waiting for approval
                </span>`;
            }
            return '';
        }

        function buildActionBtnMobile(row) {
            if (POSITION === '<?= POSITION_STAFF ?>') {
                if (!row.prepared_by) {
                    return `<button onclick="markPrepared(${row.voucher_id})"
                        class="w-full flex items-center justify-center gap-2 bg-orange-500 text-white text-sm font-semibold py-2.5 rounded-xl transition-all">
                        <i class="fa-solid fa-pen-to-square"></i>Mark as Prepared
                    </button>`;
                }
            } else if (POSITION === '<?= POSITION_HEAD ?>') {
                if (row.voucher_status === 'voucher_approval' && !row.approved_by) {
                    if (!row.prepared_by) {
                        return `<span class="flex items-center justify-center gap-2 text-xs text-red-500 font-semibold py-2.5 bg-red-50 rounded-xl border border-red-200">
                            <i class="fa-solid fa-lock"></i>Waiting to be prepared
                        </span>`;
                    } else {
                        return `<button onclick="markApproved(${row.voucher_id})"
                            class="w-full flex items-center justify-center gap-2 bg-green-500 text-white text-sm font-semibold py-2.5 rounded-xl transition-all">
                            <i class="fa-solid fa-check"></i>Approve Voucher
                        </button>`;
                    }
                }
            }
            if (row.voucher_status === 'released') {
                return `<button onclick="printVoucher(${JSON.stringify(row).replace(/"/g, '&quot;')})"
                    class="w-full flex items-center justify-center gap-2 bg-gray-800 text-white text-sm font-semibold py-2.5 rounded-xl transition-all">
                    <i class="fa-solid fa-print"></i>Print Voucher
                </button>`;
            } else if (row.voucher_status === 'ready_to_release') {
                return `<span class="flex items-center justify-center gap-2 text-xs text-blue-600 py-2.5 font-medium bg-blue-50 rounded-xl border border-blue-200">
                    <i class="fa-solid fa-box"></i>Ready to Release
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
            currentRow = null;
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !document.getElementById('voucher-modal').classList.contains('hidden')) {
                closeVoucherModal();
            }
        });

        function markPrepared(voucherId) {
            fetch('<?= BASE_URL ?>/cashvoucherprepared', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ voucher_id: voucherId })
            }).then(res => res.json()).then(data => {
                if (data.success) { closeVoucherModal(); showToast('Voucher marked as prepared!'); previousCount = 0; fetchVouchers(); }
                else showToast(data.error ?? 'Failed to mark as prepared.', 'error');
            });
        }

        function markApproved(voucherId) {
            fetch('<?= BASE_URL ?>/cashvoucherapproved', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ voucher_id: voucherId })
            }).then(res => res.json()).then(data => {
                if (data.success) { closeVoucherModal(); showToast('Voucher approved successfully!'); previousCount = 0; fetchVouchers(); }
                else showToast(data.error ?? 'Failed.', 'error');
            });
        }

        function showToast(message, type = 'success') {
            const colors = { success: 'bg-green-500', error: 'bg-red-500', info: 'bg-blue-500' };
            const toast = document.createElement('div');
            toast.className = `fixed bottom-6 right-6 z-[999] flex items-center gap-3 ${colors[type]} text-white text-sm font-semibold px-5 py-3 rounded-xl shadow-lg transition-all duration-300 opacity-0 translate-y-2`;
            toast.innerHTML = `<i class="fa-solid ${type === 'success' ? 'fa-circle-check' : 'fa-circle-xmark'}"></i> ${message}`;
            document.body.appendChild(toast);
            requestAnimationFrame(() => toast.classList.remove('opacity-0', 'translate-y-2'));
            setTimeout(() => { toast.classList.add('opacity-0', 'translate-y-2'); setTimeout(() => toast.remove(), 300); }, 3000);
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
            const certifiedAt = row.certified_at ? new Date(row.certified_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) : '';
            const approvedAt = row.approved_at ? new Date(row.approved_at).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }) : '';
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
                <div style="display:grid;grid-template-columns:1fr auto;border-bottom:2px solid #f97316;">
                    <div style="display:flex;align-items:center;gap:12px;padding:10px 14px;">
                        <img src="<?= BASE_URL ?>/icon/logo.png" style="width:48px;height:48px;object-fit:contain;">
                        <div>
                            <div style="font-weight:bold;font-size:12px;text-transform:uppercase;">Noblehome Construction Corporation</div>
                            <div style="font-size:9px;color:#666;margin-top:2px;">1181 MC Premiere Bldg., EDSA Balintawak Quezon City</div>
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
                <div style="padding:10px 14px;border-bottom:1px solid #e5e7eb;">
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:6px;">
                        <div style="display:flex;gap:6px;align-items:center;">
                            <span style="font-size:9px;font-weight:bold;text-transform:uppercase;color:#555;width:90px;">Payee</span>
                            <span style="color:#aaa;margin-right:4px;">:</span>
                            <span style="border-bottom:1px solid #aaa;flex:1;font-size:11px;">${row.voucher_payee ?? ''}</span>
                        </div>
                        <div style="display:flex;gap:6px;align-items:center;">
                            <span style="font-size:9px;font-weight:bold;text-transform:uppercase;color:#555;white-space:nowrap;">Payment For</span>
                            <span style="color:#aaa;margin:0 4px;">:</span>
                            <span style="border-bottom:1px solid #aaa;flex:1;font-size:11px;">${row.voucher_purpose ?? ''}</span>
                        </div>
                    </div>
                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                        <div style="display:flex;gap:6px;align-items:center;">
                            <span style="font-size:9px;font-weight:bold;text-transform:uppercase;color:#555;width:90px;">Payment Method</span>
                            <span style="color:#aaa;margin-right:4px;">:</span>
                            <span style="border-bottom:1px solid #aaa;flex:1;font-size:11px;">${row.voucher_payment_method ?? ''}</span>
                        </div>
                        <div style="display:flex;gap:6px;align-items:center;">
                            <span style="font-size:9px;font-weight:bold;text-transform:uppercase;color:#555;white-space:nowrap;">Amount in Words</span>
                            <span style="color:#aaa;margin:0 4px;">:</span>
                            <span style="border-bottom:1px solid #aaa;flex:1;font-size:11px;font-style:italic;">${numberToWords(total)}</span>
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
                                        Payment Method No.: <span style="font-family:monospace;font-size:10px;color:#374151;">${row.voucher_payment_method ?? '—'}</span>
                                    </div>
                                    <div style="font-weight:bold;font-size:11px;">Total Amount: <span style="font-family:monospace;">${total.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</span></div>
                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;border-top:2px solid #f97316;">
                    <div class="orange-bg" style="font-size:9px;font-weight:bold;text-align:center;padding:5px;border-right:1px solid #ea6c00;">Prepared By</div>
                    <div class="orange-bg" style="font-size:9px;font-weight:bold;text-align:center;padding:5px;border-right:1px solid #ea6c00;">Certified Thru Correct</div>
                    <div class="orange-bg" style="font-size:9px;font-weight:bold;text-align:center;padding:5px;border-right:1px solid #ea6c00;">Approved By</div>
                    <div class="orange-bg" style="font-size:9px;font-weight:bold;text-align:center;padding:5px;">Received By</div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr 1fr;border-bottom:1px solid #e5e7eb;">
                    <div style="padding:10px 8px;border-right:1px solid #e5e7eb;display:flex;flex-direction:column;">
                        <div style="height:65px;display:flex;align-items:flex-end;justify-content:center;margin-bottom:4px;">
                            ${row.prepared_signature ? `<img src="${row.prepared_signature}" style="max-height:65px;max-width:120px;object-fit:contain;">` : ''}
                        </div>
                        <div style="border-top:1px solid #999;padding-top:4px;text-align:center;">
                            <div style="font-size:10px;font-weight:600;">${row.prepared_name ?? ''}</div>
                            <div style="font-size:9px;color:#888;">${preparedAt}</div>
                        </div>
                    </div>
                    <div style="padding:10px 8px;border-right:1px solid #e5e7eb;display:flex;flex-direction:column;">
                        <div style="height:65px;display:flex;align-items:flex-end;justify-content:center;margin-bottom:4px;">
                            ${row.certified_signature ? `<img src="${row.certified_signature}" style="max-height:65px;max-width:120px;object-fit:contain;">` : ''}
                        </div>
                        <div style="border-top:1px solid #999;padding-top:4px;text-align:center;">
                            <div style="font-size:10px;font-weight:600;">${row.certified_name ?? ''}</div>
                            <div style="font-size:9px;color:#888;">${certifiedAt}</div>
                        </div>
                    </div>
                    <div style="padding:10px 8px;border-right:1px solid #e5e7eb;display:flex;flex-direction:column;">
                        <div style="height:65px;display:flex;align-items:flex-end;justify-content:center;margin-bottom:4px;">
                            ${row.approver_signature ? `<img src="${row.approver_signature}" style="max-height:65px;max-width:120px;object-fit:contain;">` : ''}
                        </div>
                        <div style="border-top:1px solid #999;padding-top:4px;text-align:center;">
                            <div style="font-size:10px;font-weight:600;">${row.approver_name ?? ''}</div>
                            <div style="font-size:9px;color:#888;">${approvedAt}</div>
                        </div>
                    </div>
                    <div style="padding:10px 8px;display:flex;flex-direction:column;">
                        <div style="height:65px;"></div>
                        <div style="border-top:1px solid #999;padding-top:4px;text-align:center;">
                            <div style="font-size:10px;font-weight:600;">${receiverName}</div>
                            <div style="font-size:9px;color:#888;">${receivedAt}</div>
                        </div>
                    </div>
                </div>
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

        let previousCount = 0;

        function fetchVouchers() {
            fetch('<?= BASE_URL ?>/cashvoucherfetchall')
                .then(res => res.json())
                .then(data => {
                    allData = data;  // tanggalin ang previousCount check
                    const q = document.getElementById('search-input').value.toLowerCase();
                    const filtered = q ? allData.filter(row =>
                        row.control_no?.toLowerCase().includes(q) ||
                        row.voucher_title?.toLowerCase().includes(q) ||
                        row.voucher_payee?.toLowerCase().includes(q) ||
                        row.purpose?.toLowerCase().includes(q)
                    ) : allData;
                    renderTable(filtered, q);
                    document.getElementById('last-updated').textContent = 'Updated ' + new Date().toLocaleTimeString('en-PH');
                })
                .catch(err => console.error('Fetch error:', err));
        }

        document.getElementById('search-input').addEventListener('input', function () {
            const q = this.value.toLowerCase();
            const filtered = allData.filter(row =>
                row.control_no?.toLowerCase().includes(q) ||
                row.voucher_title?.toLowerCase().includes(q) ||
                row.voucher_payee?.toLowerCase().includes(q) ||
                row.purpose?.toLowerCase().includes(q)
            );
            renderTable(filtered, q);
        });

        function checkHighlight() {
            const params = new URLSearchParams(window.location.search);
            const highlightId = params.get('highlight');
            if (!highlightId) return;
            const interval = setInterval(() => {
                const isMobile = window.innerWidth < 768;
                const row = isMobile
                    ? document.querySelector(`#voucher-cards div[data-id="${highlightId}"]`)
                    : document.querySelector(`#voucher-tbody tr[data-id="${highlightId}"]`);
                if (row) {
                    clearInterval(interval);
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    if (!isMobile) {
                        const firstCell = row.querySelector('td:first-child');
                        if (firstCell) {
                            const badge = document.createElement('span');
                            badge.className = 'highlight-badge';
                            firstCell.prepend(badge);
                            setTimeout(() => badge.remove(), 5000);
                        }
                    } else {
                        let on = true;
                        const flash = setInterval(() => { row.style.backgroundColor = on ? '#fecaca' : '#fee2e2'; on = !on; }, 300);
                        setTimeout(() => { clearInterval(flash); row.style.backgroundColor = ''; }, 5000);
                    }
                }
            }, 200);
            setTimeout(() => clearInterval(interval), 5000);
        }



        fetchVouchers();
        setTimeout(checkHighlight, 500);
        setInterval(fetchVouchers, 5000);
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') { previousCount = 0; fetchVouchers(); }
        });
    </script>

</body>

</html>