<?php
// index-my-request-history.php

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
    <title>My Requests — NobleHome</title>
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
        .mr-tab {
            position: relative;
            color: #6b7280;
        }

        .mr-tab:hover {
            color: #111827;
        }

        .mr-tab.is-active {
            color: #111827;
        }

        .mr-tab.is-active::after {
            content: '';
            position: absolute;
            left: 0;
            right: 0;
            bottom: -1px;
            height: 2px;
            background: #f7941d;
        }

        .mr-tab .mr-count {
            background: #f3f4f6;
            color: #6b7280;
        }

        .mr-tab.is-active .mr-count {
            background: #111827;
            color: #fff;
        }

        /* sticky na table header */
        .mr-table thead th {
            position: sticky;
            top: 0;
            z-index: 1;
            background: #fafafa;
        }
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
                    <h1 class="text-lg font-semibold text-gray-900 tracking-tight">My Requests</h1>
                    <p class="text-sm text-gray-500 mt-0.5">
                        Budget requests you have submitted · <span id="range-label" class="text-gray-900 font-medium"></span>
                    </p>
                </div>

                <!-- Summary -->
                <div class="flex items-stretch divide-x divide-gray-200 rounded-lg border border-gray-200">
                    <div class="px-4 py-2">
                        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Requests</p>
                        <p class="text-sm font-semibold text-gray-900 tabular-nums" id="st-total">0</p>
                    </div>
                    <div class="px-4 py-2">
                        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Pending</p>
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
                    <button data-status="all" class="mr-tab is-active inline-flex items-center gap-2 py-3 text-sm font-medium">
                        All <span class="mr-count text-[10px] font-semibold rounded-full px-1.5 min-w-[18px] text-center" data-count="all">0</span>
                    </button>
                    <button data-status="pending" class="mr-tab inline-flex items-center gap-2 py-3 text-sm font-medium">
                        Pending <span class="mr-count text-[10px] font-semibold rounded-full px-1.5 min-w-[18px] text-center" data-count="pending">0</span>
                    </button>
                    <button data-status="approved" class="mr-tab inline-flex items-center gap-2 py-3 text-sm font-medium">
                        Approved <span class="mr-count text-[10px] font-semibold rounded-full px-1.5 min-w-[18px] text-center" data-count="approved">0</span>
                    </button>
                    <button data-status="rejected" class="mr-tab inline-flex items-center gap-2 py-3 text-sm font-medium">
                        Rejected <span class="mr-count text-[10px] font-semibold rounded-full px-1.5 min-w-[18px] text-center" data-count="rejected">0</span>
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
                    <span class="hidden sm:inline-flex items-center gap-1.5 pl-2 text-[11px] text-gray-400" title="Auto-refresh every 10s">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500" id="live-dot"></span>
                        <span id="live-text">Live</span>
                    </span>
                </div>
            </div>

            <!-- Table -->
            <div class="thin-scroll overflow-auto max-h-[60vh]">
                <table class="mr-table w-full text-sm min-w-[760px]">
                    <thead>
                        <tr class="text-[11px] font-medium text-gray-500 uppercase tracking-wider border-b border-gray-200">
                            <th class="px-6 py-3 text-left">Control No.</th>
                            <th class="px-6 py-3 text-left">Purpose</th>
                            <th class="px-6 py-3 text-right">Amount</th>
                            <th class="px-6 py-3 text-left">Date Submitted</th>
                            <th class="px-6 py-3 text-left">Status</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody id="history-tbody" class="divide-y divide-gray-100">
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
                <span class="hidden sm:inline">Click a row to view the full request</span>
            </div>
        </div>
    </main>

    <!-- View Modal -->
    <div id="view-modal"
        class="thin-scroll hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50 px-4 py-8 overflow-y-auto">
        <div class="bg-white w-full max-w-4xl rounded-sm shadow-xl border border-gray-300 my-auto">

            <div class="grid grid-cols-[1fr_auto] border-b-2 border-gray-800">
                <div class="flex items-center gap-4 px-3 py-3 border-r-2 border-gray-800">
                    <img src="<?= BASE_URL ?>/icon/logo.webp" alt="Logo" class="w-12 h-12 object-contain">
                    <div class="w-px h-12 bg-gray-400"></div>
                    <div>
                        <p class="font-bold text-sm uppercase tracking-wide">Noblehome Construction Corporation</p>
                        <p class="text-[10px] text-gray-500 mt-1 leading-relaxed">
                            1181 MC Premiere Bldg., EDSA Balintawak Quezon City<br>
                            noblehomeconsl.ph@gmail.com | Tel. No. 02-88221295 | Cell. No. 0968-591-6544
                        </p>
                    </div>
                </div>
                <div class="flex flex-col">
                    <div class="flex items-center justify-between px-4 py-2 border-b-2 border-gray-800 gap-4">
                        <h2 class="font-bold text-sm uppercase tracking-widest whitespace-nowrap">Budget Request Form
                        </h2>
                        <button onclick="closeViewModal()"
                            class="text-gray-400 hover:text-red-500 transition-colors p-1">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <div class="flex flex-row flex-1 text-[10px]">
                        <div class="flex flex-col border-r-2 border-gray-800 flex-1">
                            <span
                                class="bg-orange-500 text-white font-bold px-4 py-1 uppercase tracking-wider text-center border-b-2 border-gray-800">Control
                                No.</span>
                            <p id="v-control-no"
                                class="flex-1 px-4 py-1 font-mono text-xs text-center bg-gray-50 min-w-[180px]"></p>
                        </div>
                        <div class="flex flex-col flex-1">
                            <span
                                class="bg-orange-500 text-white font-bold px-4 py-1 uppercase tracking-wider text-center border-b-2 border-gray-800">Date:</span>
                            <p id="v-date"
                                class="flex-1 px-4 py-1 font-mono text-xs text-center bg-gray-50 min-w-[150px]"></p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-2 border-b-2 border-gray-800">
                <div class="flex items-center gap-2 px-6 py-3 border-r-2 border-gray-800">
                    <span
                        class="text-[10px] font-bold uppercase tracking-widest text-gray-600 whitespace-nowrap">Requestor
                        Name:</span>
                    <p id="v-requestor" class="text-sm text-gray-800"></p>
                </div>
                <div class="flex items-center gap-2 px-6 py-3">
                    <span
                        class="text-[10px] font-bold uppercase tracking-widest text-gray-600 whitespace-nowrap">Purpose
                        of Request:</span>
                    <p id="v-purpose" class="text-sm text-gray-800"></p>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-sm border-collapse">
                    <thead>
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
                    <tbody id="v-items-tbody"></tbody>
                    <tfoot>
                        <tr class="border-t-2 border-gray-800 bg-gray-50">
                            <td colspan="5"
                                class="px-4 py-2 font-bold text-xs uppercase tracking-widest text-right border-r border-gray-300">
                                Total:</td>
                            <td id="v-total" class="px-4 py-2 font-bold font-mono text-right"></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
                <div class="px-6 py-3 border-t border-gray-100">
                    <div id="v-reject-comment"></div>
                </div>
            </div>

            <div class="grid grid-cols-2 border-t-2 border-gray-800">
                <div class="px-5 py-4 border-r-2 border-gray-800">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-gray-600">Approved By:</span>
                        <span id="v-status-badge"></span>
                    </div>
                    <div id="v-approved-by" class="text-center"></div>
                    <div class="border-b-2 border-gray-400 mb-1 mt-6"></div>
                    <p class="text-[10px] text-center text-gray-500 font-medium uppercase tracking-wider">Head</p>
                </div>
                <div class="px-5 py-4">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="text-[10px] font-bold uppercase tracking-widest text-gray-600">Received By:</span>
                    </div>
                    <div id="v-received-by" class="text-center"></div>
                    <div class="border-b-2 border-gray-400 mb-1 mt-6"></div>
                    <p class="text-[10px] text-center text-gray-500 font-medium uppercase tracking-wider">&nbsp;</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        let allData = [];
        let viewMode = 'month'; // 'month' = piniling buwan lang | 'all' = lahat
        let statusFilter = 'all';

        const monthInput = document.getElementById('month-filter');
        const searchInput = document.getElementById('search-input');

        const statusMap = {
            pending: { label: 'Pending', cls: 'bg-yellow-100 text-yellow-700' },
            approved: { label: 'Approved', cls: 'bg-green-100 text-green-700' },
            rejected: { label: 'Rejected', cls: 'bg-red-100 text-red-700' },
        };

        // ginagamit ng modal (original)
        function statusBadge(status) {
            const s = statusMap[status] ?? { label: status, cls: 'bg-gray-100 text-gray-500' };
            return `<span class="${s.cls} text-[10px] font-semibold px-2 py-1 rounded-full uppercase tracking-wide">${s.label}</span>`;
        }

        // badge sa table (mas tahimik / professional)
        const tableStatus = {
            pending: { label: 'Pending', dot: 'bg-amber-500' },
            approved: { label: 'Approved', dot: 'bg-green-600' },
            rejected: { label: 'Rejected', dot: 'bg-red-500' },
        };
        function tableBadge(status) {
            const s = tableStatus[status] ?? { label: status || '—', dot: 'bg-gray-400' };
            return `<span class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <span class="w-1.5 h-1.5 rounded-full ${s.dot}"></span>${esc(s.label)}
                    </span>`;
        }

        const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
        const peso = n => '₱ ' + (n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const rowTotal = row => (row.items ?? []).reduce((s, i) => s + (parseFloat(i.amount) || 0), 0);

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
            fetchHistory(true);
        }

        monthInput.value = nowMonth();
        monthInput.max = nowMonth(); // walang future months
        monthInput.addEventListener('change', function () {
            if (!this.value) this.value = nowMonth();
            fetchHistory(true);
        });

        // ── Status tabs ──────────────────────────────────────
        document.getElementById('status-tabs').addEventListener('click', e => {
            const b = e.target.closest('[data-status]');
            if (!b) return;
            statusFilter = b.dataset.status;
            document.querySelectorAll('.mr-tab').forEach(t => t.classList.toggle('is-active', t === b));
            applyFilters();
        });

        // ── Fetch ────────────────────────────────────────────
        function fetchHistory(showLoading = false) {
            const param = currentParam();
            updateRangeLabel();

            if (showLoading) {
                document.getElementById('history-tbody').innerHTML = `
                    <tr><td colspan="6" class="px-6 py-10 text-center text-gray-400">
                        <i class="fa-solid fa-spinner fa-spin mr-2"></i> Loading...
                    </td></tr>`;
            }

            fetch('<?= BASE_URL ?>/fetchmyrequests?month=' + encodeURIComponent(param))
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
            const count = st => allData.filter(r => r.status === st).length;
            document.getElementById('st-total').textContent = allData.length;
            document.getElementById('st-pending').textContent = count('pending');
            document.getElementById('st-amount').textContent = peso(allData.reduce((s, r) => s + rowTotal(r), 0));
            document.querySelector('[data-count="all"]').textContent = allData.length;
            ['pending', 'approved', 'rejected'].forEach(st =>
                document.querySelector(`[data-count="${st}"]`).textContent = count(st));
        }

        function applyFilters() {
            const q = searchInput.value.trim().toLowerCase();
            renderTable(allData.filter(row =>
                (statusFilter === 'all' || row.status === statusFilter) &&
                (!q ||
                    row.control_no?.toLowerCase().includes(q) ||
                    row.purpose?.toLowerCase().includes(q) ||
                    row.status?.toLowerCase().includes(q))));
        }

        function renderTable(data) {
            const tbody = document.getElementById('history-tbody');
            document.getElementById('result-count').textContent =
                'Showing ' + data.length + ' of ' + allData.length + ' request' + (allData.length !== 1 ? 's' : '');

            if (!data.length) {
                const msg = allData.length
                    ? 'No requests match your filters.'
                    : (viewMode === 'all'
                        ? 'You have not submitted any requests yet.'
                        : 'No requests for this month. Try <b class="font-medium text-gray-700">View All</b> or pick another month.');
                tbody.innerHTML = `<tr><td colspan="6" class="px-6 py-14 text-center text-sm text-gray-500">${msg}</td></tr>`;
                return;
            }

            tbody.innerHTML = data.map(row => {
                const idx = allData.indexOf(row);
                const items = row.items ?? [];
                const date = row.date_requested
                    ? new Date(row.date_requested).toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' })
                    : '—';
                return `
                <tr class="group hover:bg-gray-50 transition-colors cursor-pointer" data-idx="${idx}">
                    <td class="px-6 py-3.5 font-mono text-[13px] font-medium text-gray-900 whitespace-nowrap">${esc(row.control_no)}</td>
                    <td class="px-6 py-3.5 max-w-[280px]">
                        <p class="text-gray-900 truncate" title="${esc(row.purpose)}">${esc(row.purpose)}</p>
                        <p class="text-xs text-gray-400">${items.length} item${items.length !== 1 ? 's' : ''}</p>
                    </td>
                    <td class="px-6 py-3.5 text-right tabular-nums font-medium text-gray-900 whitespace-nowrap">${peso(rowTotal(row))}</td>
                    <td class="px-6 py-3.5 text-gray-500 whitespace-nowrap">${date}</td>
                    <td class="px-6 py-3.5 whitespace-nowrap">${tableBadge(row.status)}</td>
                    <td class="px-6 py-3.5 text-right">
                        <span class="inline-flex items-center gap-1 text-[13px] font-medium text-gray-400 group-hover:text-[#e07b06] transition-colors">
                            View <i class="fa-solid fa-chevron-right text-[10px]"></i>
                        </span>
                    </td>
                </tr>`;
            }).join('');
        }

        // Click sa row = buksan ang (original) modal
        document.getElementById('history-tbody').addEventListener('click', e => {
            const tr = e.target.closest('[data-idx]');
            if (!tr) return;
            const row = allData[+tr.dataset.idx];
            if (row) viewRequest(row);
        });

        function viewRequest(row) {
            const items = row.items ?? [];
            let total = 0;
            document.getElementById('v-control-no').textContent = row.control_no;
            document.getElementById('v-date').textContent = row.date_requested;
            document.getElementById('v-requestor').textContent = row.requestor_name;
            document.getElementById('v-purpose').textContent = row.purpose;
            document.getElementById('v-status-badge').innerHTML = statusBadge(row.status);

            document.getElementById('v-items-tbody').innerHTML = items.map((item, i) => {
                const amount = parseFloat(item.amount) || 0;
                total += amount;
                return `<tr class="border-t border-gray-200">
            <td class="px-3 py-2 text-center text-xs text-gray-400 font-mono border-r border-gray-200">${i + 1}</td>
            <td class="px-4 py-2 border-r border-gray-200">${item.description || ''}</td>
            <td class="px-4 py-2 border-r border-gray-200">${item.purpose || ''}</td>
            <td class="px-4 py-2 border-r border-gray-200 text-center">${item.quantity || 0}</td>
            <td class="px-4 py-2 border-r border-gray-200 text-right font-mono">${parseFloat(item.unit_price || 0).toLocaleString('en-PH', { minimumFractionDigits: 2 })}</td>
            <td class="px-4 py-2 border-r border-gray-200 text-right font-mono">₱ ${amount.toLocaleString('en-PH', { minimumFractionDigits: 2 })}</td>
            <td class="px-4 py-2">${item.notes || ''}</td>
        </tr>`;
            }).join('');

            document.getElementById('v-total').textContent = '₱ ' + total.toLocaleString('en-PH', { minimumFractionDigits: 2 });

            const fmtDate = str => str
                ? new Date(str.replace(' ', 'T')).toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' })
                + ' ' + new Date(str.replace(' ', 'T')).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', hour12: true })
                : '';

            // ── Approver with signature ──
            const approverSig = (row.status === 'approved' && row.approver_signature)
                ? `<?= BASE_URL ?>/${row.approver_signature}` : '';

            document.getElementById('v-approved-by').innerHTML = row.approver_name
                ? `<div class="relative inline-block">
               ${approverSig
                    ? `<img src="${approverSig}" alt="Signature"
                          style="position:absolute; bottom:0; left:50%; transform:translateX(-50%);
                                 height:80px; max-width:220px; z-index:10; pointer-events:none;">`
                    : ''}
               <p class="text-sm font-semibold text-gray-800">${row.approver_name}</p>
               <p class="text-[10px] text-gray-400">${fmtDate(row.approved_at)}</p>
           </div>`
                : '<p class="text-xs text-gray-300 italic">Not yet approved</p>';

            // ── Receiver with signature ──
            const receiverSig = row.receiver_signature_path
                ? `<?= BASE_URL ?>/${row.receiver_signature_path}` : '';

            document.getElementById('v-received-by').innerHTML = row.receiver_name
                ? `<div class="relative inline-block">
               ${receiverSig
                    ? `<img src="${receiverSig}" alt="Signature"
                          style="position:absolute; bottom:0; left:50%; transform:translateX(-50%);
                                 height:80px; max-width:220px; z-index:10; pointer-events:none;">`
                    : ''}
               <p class="text-sm font-semibold text-gray-800">${row.receiver_name}</p>
               <p class="text-[10px] text-gray-400">${fmtDate(row.received_at)}</p>
           </div>`
                : '<p class="text-xs text-gray-300 italic">Not yet received</p>';

            // ── Reject reason (kung rejected) ──
            const rejectDiv = document.getElementById('v-reject-comment');
            if (rejectDiv) {
                rejectDiv.innerHTML = (row.reject_comment && row.status === 'rejected')
                    ? `<div class="mt-2 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                   <p class="text-[10px] font-bold uppercase tracking-widest text-red-500 mb-1">Reason for Rejection:</p>
                   <p class="text-sm text-red-700">${row.reject_comment}</p>
               </div>` : '';
            }

            document.getElementById('view-modal').classList.remove('hidden');
        }

        function closeViewModal() {
            document.getElementById('view-modal').classList.add('hidden');
        }

        searchInput.addEventListener('input', applyFilters);

        fetchHistory();

        // Auto-refresh: buwan lang, hindi "View All", at hindi kapag nakatago ang tab o bukas ang modal
        setInterval(function () {
            const modalOpen = !document.getElementById('view-modal').classList.contains('hidden');
            if (viewMode === 'month' && !document.hidden && !modalOpen) fetchHistory();
        }, 10000);
    </script>
</body>

</html>