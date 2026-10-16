<?php
// index-my-vouchers.php
include ROOT_PATH . '/network/connect.php';
if (empty($_SESSION['logged_in'])) {
    header('Location: ' . BASE_URL . '/');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Vouchers — NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/user/navigation/top.php'; ?>
    <style>
        /* ── Thin scrollbar ── */
        .thin-scroll {
            scrollbar-width: thin;
            /* Firefox */
            scrollbar-color: #fdba74 transparent;
        }

        .thin-scroll::-webkit-scrollbar {
            width: 4px;
            /* vertical */
            height: 4px;
            /* horizontal */
        }

        .thin-scroll::-webkit-scrollbar-track {
            background: transparent;
        }

        .thin-scroll::-webkit-scrollbar-thumb {
            background: #fdba74;
            border-radius: 999px;
        }

        .thin-scroll::-webkit-scrollbar-thumb:hover {
            background: #f97316;
        }

        /* ── Status tabs (underline) ── */
        .mv-tab { position: relative; color: #6b7280; }
        .mv-tab:hover, .mv-tab.is-active { color: #111827; }
        .mv-tab.is-active::after {
            content: ''; position: absolute; left: 0; right: 0; bottom: -1px;
            height: 2px; background: #f7941d;
        }
        .mv-tab .mv-count { background: #f3f4f6; color: #6b7280; }
        .mv-tab.is-active .mv-count { background: #111827; color: #fff; }

        /* sticky na table header */
        .mv-table thead th { position: sticky; top: 0; z-index: 1; background: #fafafa; }
    </style>
</head>

<body class="min-h-screen relative"
    style="background-image: url('<?= BASE_URL ?>/icon/building2.png'); background-size: cover; background-position: center; background-attachment: fixed;">
    <div class="fixed inset-0 bg-black/60 z-0"></div>

    <main class="relative z-10 max-w-6xl mx-auto px-3 md:px-6 pt-24 md:pt-32 pb-8">

        <div class="bg-white rounded-xl shadow-2xl overflow-hidden">

            <!-- Header -->
            <div class="flex flex-wrap items-start justify-between gap-4 px-6 pt-6 pb-5">
                <div>
                    <h1 class="text-lg font-semibold text-gray-900 tracking-tight">My Vouchers</h1>
                    <p class="text-sm text-gray-500 mt-0.5">
                        Released cash vouchers for your acceptance · <span id="range-label" class="text-gray-900 font-medium"></span>
                    </p>
                </div>

                <!-- Summary -->
                <div class="flex items-stretch divide-x divide-gray-200 rounded-lg border border-gray-200">
                    <div class="px-4 py-2">
                        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Vouchers</p>
                        <p class="text-sm font-semibold text-gray-900 tabular-nums" id="st-total">0</p>
                    </div>
                    <div class="px-4 py-2">
                        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">To Accept</p>
                        <p class="text-sm font-semibold text-gray-900 tabular-nums" id="st-pending">0</p>
                    </div>
                    <div class="px-4 py-2">
                        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Total Amount</p>
                        <p class="text-sm font-semibold text-gray-900 tabular-nums" id="st-amount">₱ 0.00</p>
                    </div>
                </div>
            </div>

            <!-- Toolbar: tabs + filters -->
            <div class="flex flex-wrap items-end justify-between gap-3 px-6 border-b border-gray-200">
                <nav class="flex items-center gap-6 -mb-px" id="status-tabs">
                    <button data-status="all" class="mv-tab is-active inline-flex items-center gap-2 py-3 text-sm font-medium">
                        All <span class="mv-count text-[10px] font-semibold rounded-full px-1.5 min-w-[18px] text-center" data-count="all">0</span>
                    </button>
                    <button data-status="pending" class="mv-tab inline-flex items-center gap-2 py-3 text-sm font-medium">
                        Pending Acceptance <span class="mv-count text-[10px] font-semibold rounded-full px-1.5 min-w-[18px] text-center" data-count="pending">0</span>
                    </button>
                    <button data-status="received" class="mv-tab inline-flex items-center gap-2 py-3 text-sm font-medium">
                        Received <span class="mv-count text-[10px] font-semibold rounded-full px-1.5 min-w-[18px] text-center" data-count="received">0</span>
                    </button>
                </nav>

                <div class="flex flex-wrap items-center gap-2 pb-2.5">
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-[11px] pointer-events-none"></i>
                        <input type="text" id="search-input" placeholder="Search"
                            class="pl-8 pr-3 h-9 w-52 text-sm border border-gray-200 rounded-md outline-none focus:border-gray-400 transition-colors">
                    </div>
                    <input type="month" id="month-filter"
                        class="px-3 h-9 text-sm border border-gray-200 rounded-md outline-none focus:border-gray-400 transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
                    <button id="toggle-all-btn" onclick="toggleAll()"
                        class="h-9 inline-flex items-center gap-1.5 px-3 text-sm font-medium text-gray-700 border border-gray-200 rounded-md hover:bg-gray-50 transition-colors">
                        <span id="toggle-all-label">View All</span>
                    </button>
                    <span class="hidden sm:inline-flex items-center gap-1.5 pl-2 text-[11px] text-gray-400" title="Auto-refresh every 5s">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500" id="live-dot"></span>
                        <span id="live-text">Live</span>
                    </span>
                </div>
            </div>

            <!-- Table -->
            <div class="thin-scroll overflow-auto max-h-[60vh]">
                <table class="mv-table w-full text-sm min-w-[760px]">
                    <thead>
                        <tr class="text-[11px] font-medium text-gray-500 uppercase tracking-wider border-b border-gray-200">
                            <th class="px-6 py-3 text-left">Voucher No.</th>
                            <th class="px-6 py-3 text-left">Purpose</th>
                            <th class="px-6 py-3 text-right">Amount</th>
                            <th class="px-6 py-3 text-left">Released</th>
                            <th class="px-6 py-3 text-left">Status</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody id="voucher-tbody" class="divide-y divide-gray-100">
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-gray-400">
                                <i class="fa-solid fa-spinner fa-spin mr-2"></i> Loading...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer -->
            <div class="flex items-center justify-between px-6 py-3 border-t border-gray-200 bg-gray-50/60 text-xs text-gray-500">
                <span id="result-count">—</span>
                <span class="hidden sm:inline">Click a row to view and accept the voucher</span>
            </div>
        </div>
    </main>

    <!-- View Modal -->
    <div id="view-modal"
        class="thin-scroll hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4 py-8 overflow-y-auto">
        <div class="bg-white w-full max-w-4xl rounded-sm shadow-xl border border-gray-300 my-auto">
            <div class="grid grid-cols-[auto_1fr_auto] border-b-2 border-orange-400 items-center px-4 py-3 gap-4">
                <div class="flex items-center gap-3">
                    <img src="<?= BASE_URL ?>/icon/logo.png" alt="Logo" class="w-12 h-12 object-contain">
                    <div>
                        <p class="font-bold text-sm uppercase">Noblehome Construction Corporation</p>
                        <p class="text-[10px] text-gray-500">1181 MC Premiere Bldg., EDSA Bldg., EDSA Balintawak Quezon
                            City</p>
                        <p class="text-[10px] text-gray-500">noblehomeconsl.ph@gmail.com | Tel. No. 02-88221295 | Cell.
                            No. 0968-591-6544</p>
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

            <div class="px-6 py-3 border-b border-gray-300 space-y-2">
                <div class="grid grid-cols-2 gap-4">
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-gray-600 w-28">Payee</span>
                        <span class="text-gray-400 mr-2">:</span>
                        <span id="v-payee" class="text-sm text-gray-800"></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            class="text-[10px] font-bold uppercase tracking-widest text-gray-600 whitespace-nowrap">Payment
                            For</span>
                        <span class="text-gray-400">:</span>
                        <span id="v-purpose" class="text-sm text-gray-800 ml-2"></span>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="flex items-center gap-2">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-gray-600 w-28">Address</span>
                        <span class="text-gray-400 mr-2">:</span>
                        <span id="v-address" class="text-sm text-gray-800"></span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            class="text-[10px] font-bold uppercase tracking-widest text-gray-600 whitespace-nowrap">Amount
                            in Words</span>
                        <span class="text-gray-400">:</span>
                        <span id="v-amount-words" class="text-sm italic text-gray-700 ml-2"></span>
                    </div>
                </div>
            </div>

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
                                            Method No.:</span>
                                        <span id="v-second-no" class="font-mono text-xs ml-2 text-gray-700"></span>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <span class="font-bold text-xs uppercase tracking-widest text-gray-700">Total
                                            Amount:</span>
                                        <span id="v-total" class="font-bold font-mono text-sm"></span>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>

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
                <div class="bg-orange-500 text-white text-[10px] font-bold uppercase tracking-wider text-center py-1.5">
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

            <div class="flex items-center justify-between px-6 py-3">
                <p class="text-[10px] italic text-gray-500">Received the above stated amount in full settlement.</p>
                <div class="flex gap-2" id="v-footer-btns"></div>
            </div>
        </div>
    </div>

    <script>
        let allData = [];
        let currentRow = null;
        let viewMode = 'month'; // 'month' = piniling buwan lang | 'all' = lahat
        let statusFilter = 'all';

        const monthInput = document.getElementById('month-filter');
        const searchInput = document.getElementById('search-input');

        const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
        const peso = n => '₱ ' + (n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const rowTotal = row => (row.items ?? []).reduce((s, i) => s + (parseFloat(i.amount) || 0), 0);
        const isReceived = row => !!row.receiver_name || !!row.manual_receiver_name;

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
            let intPart = Math.floor(amount), decPart = Math.round((amount - intPart) * 100), result = '';
            if (intPart >= 1000000) { result += convertHundreds(Math.floor(intPart / 1000000)) + 'Million '; intPart %= 1000000; }
            if (intPart >= 1000) { result += convertHundreds(Math.floor(intPart / 1000)) + 'Thousand '; intPart %= 1000; }
            if (intPart > 0) { result += convertHundreds(intPart); }
            result += 'Pesos';
            if (decPart > 0) result += ' and ' + decPart + '/100';
            return result.trim() + ' Only';
        }

        // ── Month / View-all controls ────────────────────────
        function nowMonth() {
            const d = new Date();
            return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
        }

        function currentParam() {
            return viewMode === 'all' ? 'all' : (monthInput.value || nowMonth());
        }

        function updateRangeLabel() {
            const label = document.getElementById('range-label');
            if (viewMode === 'all') { label.textContent = 'All time'; return; }
            const [y, m] = currentParam().split('-').map(Number);
            label.textContent = new Date(y, m - 1, 1).toLocaleDateString('en-PH', { month: 'long', year: 'numeric' });
        }

        function toggleAll() {
            viewMode = viewMode === 'all' ? 'month' : 'all';
            const isAll = viewMode === 'all';

            monthInput.disabled = isAll;
            document.getElementById('toggle-all-label').textContent = isAll ? 'This Month' : 'View All';
            document.getElementById('live-dot').classList.toggle('bg-green-500', !isAll);
            document.getElementById('live-dot').classList.toggle('bg-gray-300', isAll);
            document.getElementById('live-text').textContent = isAll ? 'Paused' : 'Live';

            if (!isAll) monthInput.value = nowMonth();
            fetchVouchers(true);
        }

        monthInput.value = nowMonth();
        monthInput.max = nowMonth(); // walang future months
        monthInput.addEventListener('change', function () {
            if (!this.value) this.value = nowMonth();
            fetchVouchers(true);
        });

        // ── Status tabs ──────────────────────────────────────
        document.getElementById('status-tabs').addEventListener('click', e => {
            const b = e.target.closest('[data-status]');
            if (!b) return;
            statusFilter = b.dataset.status;
            document.querySelectorAll('.mv-tab').forEach(t => t.classList.toggle('is-active', t === b));
            applyFilters();
        });

        // ── Fetch ────────────────────────────────────────────
        function fetchVouchers(showLoading = false) {
            const param = currentParam();
            updateRangeLabel();

            if (showLoading) {
                document.getElementById('voucher-tbody').innerHTML = `
                    <tr><td colspan="6" class="px-6 py-10 text-center text-gray-400">
                        <i class="fa-solid fa-spinner fa-spin mr-2"></i> Loading...
                    </td></tr>`;
            }

            fetch('<?= BASE_URL ?>/fetchmyvouchers?month=' + encodeURIComponent(param))
                .then(res => res.json())
                .then(data => {
                    if (param !== currentParam()) return; // lumang response
                    allData = Array.isArray(data) ? data : [];
                    updateStats();
                    applyFilters();
                })
                .catch(() => { });
        }

        function updateStats() {
            const received = allData.filter(isReceived).length;
            const pending = allData.length - received;
            document.getElementById('st-total').textContent = allData.length;
            document.getElementById('st-pending').textContent = pending;
            document.getElementById('st-amount').textContent = peso(allData.reduce((s, r) => s + rowTotal(r), 0));
            document.querySelector('[data-count="all"]').textContent = allData.length;
            document.querySelector('[data-count="pending"]').textContent = pending;
            document.querySelector('[data-count="received"]').textContent = received;
        }

        // Filter: status tab + search (control no., purpose, payee, status)
        function applyFilters() {
            const q = searchInput.value.trim().toLowerCase();
            renderTable(allData.filter(row => {
                const rec = isReceived(row);
                if (statusFilter === 'pending' && rec) return false;
                if (statusFilter === 'received' && !rec) return false;
                if (!q) return true;
                const status = rec ? 'received' : 'pending acceptance';
                return row.control_no?.toLowerCase().includes(q) ||
                    row.purpose?.toLowerCase().includes(q) ||
                    row.payee?.toLowerCase().includes(q) ||
                    status.includes(q);
            }));
        }

        function renderTable(data) {
            const tbody = document.getElementById('voucher-tbody');
            document.getElementById('result-count').textContent =
                'Showing ' + data.length + ' of ' + allData.length + ' voucher' + (allData.length !== 1 ? 's' : '');

            if (!data.length) {
                const msg = allData.length
                    ? 'No vouchers match your filters.'
                    : (viewMode === 'all'
                        ? 'No released vouchers yet.'
                        : 'No vouchers for this month. Try <b class="font-medium text-gray-700">View All</b> or pick another month.');
                tbody.innerHTML = `<tr><td colspan="6" class="px-6 py-14 text-center text-sm text-gray-500">${msg}</td></tr>`;
                return;
            }

            tbody.innerHTML = data.map(row => {
                const idx = allData.indexOf(row);
                const rec = isReceived(row);
                const released = row.released_at
                    ? new Date(row.released_at.replace(' ', 'T')).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
                    : '—';
                const status = rec
                    ? `<span class="inline-flex items-center gap-2 text-sm text-gray-700"><span class="w-1.5 h-1.5 rounded-full bg-green-600"></span>Received</span>`
                    : `<span class="inline-flex items-center gap-2 text-sm text-gray-700"><span class="w-1.5 h-1.5 rounded-full bg-[#f7941d]"></span>Pending Acceptance</span>`;
                const action = rec
                    ? `<span class="inline-flex items-center gap-1 text-[13px] font-medium text-gray-400 group-hover:text-gray-700 transition-colors">View <i class="fa-solid fa-chevron-right text-[10px]"></i></span>`
                    : `<span class="inline-flex items-center gap-1.5 h-8 px-3 rounded-md bg-gray-900 group-hover:bg-[#e07b06] text-white text-[12px] font-medium transition-colors">Review &amp; Accept</span>`;
                return `
                <tr class="group hover:bg-gray-50 transition-colors cursor-pointer" data-idx="${idx}">
                    <td class="px-6 py-3.5 font-mono text-[13px] font-medium text-gray-900 whitespace-nowrap">${esc(row.control_no)}</td>
                    <td class="px-6 py-3.5 max-w-[280px]">
                        <p class="text-gray-900 truncate" title="${esc(row.purpose)}">${esc(row.purpose)}</p>
                        ${row.payee ? `<p class="text-xs text-gray-400 truncate">Payee: ${esc(row.payee)}</p>` : ''}
                    </td>
                    <td class="px-6 py-3.5 text-right tabular-nums font-medium text-gray-900 whitespace-nowrap">${peso(rowTotal(row))}</td>
                    <td class="px-6 py-3.5 text-gray-500 whitespace-nowrap">${released}</td>
                    <td class="px-6 py-3.5 whitespace-nowrap">${status}</td>
                    <td class="px-6 py-3.5 text-right whitespace-nowrap">${action}</td>
                </tr>`;
            }).join('');
        }

        // Click sa row = buksan ang (original) modal
        document.getElementById('voucher-tbody').addEventListener('click', e => {
            const tr = e.target.closest('[data-idx]');
            if (!tr) return;
            const row = allData[+tr.dataset.idx];
            if (row) viewVoucher(row);
        });

        function viewVoucher(row) {
            currentRow = row;
            const items = row.items ?? [];
            const total = items.reduce((sum, i) => sum + (parseFloat(i.amount) || 0), 0);

            document.getElementById('v-control-no').textContent = row.control_no;
            document.getElementById('v-date').textContent = row.date_requested;
            document.getElementById('v-title').textContent = row.title ?? '';
            document.getElementById('v-second-no').textContent = row.second_no ?? '—';
            document.getElementById('v-payee').textContent = row.payee ?? '';
            document.getElementById('v-address').textContent = row.address ?? '';
            document.getElementById('v-purpose').textContent = row.purpose ?? '';
            document.getElementById('v-amount-words').textContent = numberToWords(total);
            document.getElementById('v-total').textContent = 'PhP ' + total.toLocaleString('en-PH', { minimumFractionDigits: 2 });
            document.getElementById('v-prepared-at').textContent = row.prepared_at
                ? new Date(row.prepared_at.replace(' ', 'T')).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '';
            document.getElementById('v-certified-at').textContent = row.certified_at
                ? new Date(row.certified_at.replace(' ', 'T')).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '';
            document.getElementById('v-approved-at').textContent = row.approved_at
                ? new Date(row.approved_at.replace(' ', 'T')).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '';
            document.getElementById('v-received-at').textContent = row.received_at
                ? new Date(row.received_at.replace(' ', 'T')).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '';
            // Prepared
            const prepSig = row.prepared_signature ?? '';
            document.getElementById('v-prepared').innerHTML = prepSig
                ? `<span class="relative inline-block"><img src="${prepSig}" style="position:absolute;bottom:-50px;left:80px;transform:translateX(-50%);height:90px;max-width:160px;object-fit:contain;z-index:10;pointer-events:none;">${row.prepared_name ?? ''}</span>`
                : (row.prepared_name ?? '');

            // Certified
            const certSig = row.certified_signature ?? '';
            document.getElementById('v-certified').innerHTML = certSig
                ? `<span class="relative inline-block"><img src="${certSig}" style="position:absolute;bottom:-50px;left:80px;transform:translateX(-50%);height:90px;max-width:160px;object-fit:contain;z-index:10;pointer-events:none;">${row.certified_name ?? ''}</span>`
                : (row.certified_name ?? '');

            // Approver
            const apprSig = row.approver_signature ?? '';
            document.getElementById('v-approver').innerHTML = apprSig
                ? `<span class="relative inline-block"><img src="${apprSig}" style="position:absolute;bottom:-50px;left:80px;transform:translateX(-50%);height:90px;max-width:160px;object-fit:contain;z-index:10;pointer-events:none;">${row.approver_name ?? ''}</span>`
                : (row.approver_name ?? '');

            // Receiver
            const recvSig = row.receiver_signature ?? '';
            document.getElementById('v-receiver').innerHTML = recvSig
                ? `<span class="relative inline-block"><img src="${recvSig}" style="position:absolute;bottom:-50px;left:80px;transform:translateX(-50%);height:90px;max-width:160px;object-fit:contain;z-index:10;pointer-events:none;">${row.receiver_name ?? ''}</span>`
                : (row.receiver_name ?? '');

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

            const footerBtns = document.getElementById('v-footer-btns');
            const closeBtn = `<button onclick="closeViewModal()" class="text-sm text-gray-500 hover:text-gray-700 font-medium px-4 py-2 rounded transition-all border border-gray-200">Close</button>`;

            if (row.manual_receiver_name) {
                footerBtns.innerHTML = closeBtn + `
        <span class="flex items-center gap-2 text-xs text-green-600 font-semibold px-4 py-2">
            <i class="fa-solid fa-circle-check"></i> Received by ${row.manual_receiver_name}
        </span>`;
            } else if (!row.receiver_name) {
                footerBtns.innerHTML = closeBtn + `
        <button onclick="acceptVoucher(${row.id})"
            class="flex items-center gap-2 bg-orange-500 hover:bg-orange-600 text-white text-xs font-semibold px-4 py-2 rounded-lg transition-all">
            <i class="fa-solid fa-check mr-1"></i>Accept & Claim Voucher
        </button>`;
            } else {
                footerBtns.innerHTML = closeBtn + `
        <span class="flex items-center gap-2 text-xs text-green-600 font-semibold px-4 py-2">
            <i class="fa-solid fa-circle-check"></i> Already Received
        </span>`;
            }

            document.getElementById('view-modal').classList.remove('hidden');
        }

        function closeViewModal() {
            document.getElementById('view-modal').classList.add('hidden');
        }

        function acceptVoucher(voucherId) {
            fetch('<?= BASE_URL ?>/acceptvoucher', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ voucher_id: voucherId })
            })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        closeViewModal();
                        showToast('Voucher accepted successfully!');
                        fetchVouchers();
                    } else {
                        showToast('Failed to accept voucher.', 'error');
                    }
                });
        }

        function showToast(message, type = 'success') {
            const colors = { success: 'bg-green-500', error: 'bg-red-500' };
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

        searchInput.addEventListener('input', applyFilters);

        fetchVouchers();

        // Auto-refresh: buwan lang, hindi "View All", at hindi kapag nakatago ang tab o bukas ang modal
        setInterval(function () {
            const modalOpen = !document.getElementById('view-modal').classList.contains('hidden');
            if (viewMode === 'month' && !document.hidden && !modalOpen) fetchVouchers();
        }, 5000);
    </script>
</body>

</html>