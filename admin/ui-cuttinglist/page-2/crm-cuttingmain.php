<?php
// index-cuttinglist-main.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_CUTTING];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$cutListAjaxUrl = BASE_URL . '/cuttinglistajax';
$cutDetailUrl = BASE_URL . '/crmcuttinglistdetail';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cutting List — Approved Submissions</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
</head>

<body class="bg-slate-100">
    <main class="ml-56 min-h-screen p-8 overflow-x-hidden">

        <div class="max-w-7xl mx-auto">

            <!-- Header -->
            <div class="mb-5">
                <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase mb-0.5">Cutting
                    Department</p>
                <h1 class="text-gray-900 text-xl font-semibold">Approved Submissions</h1>
                <p class="text-gray-400 text-xs mt-0.5">Fully approved 2D &amp; quotation submissions — includes
                    those still on hold pending accounting's deposit confirmation.</p>
            </div>

            <!-- Toolbar: search + tabs -->
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mb-4">
                <div class="relative w-full sm:w-72 min-w-0">
                    <input id="cutListSearch" type="text" placeholder="Search control no. / branch / contact"
                        class="w-full pl-6 pr-7 py-1.5 text-xs border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-100 focus:border-amber-600 bg-white transition-colors">
                    <i
                        class="fa-solid fa-magnifying-glass absolute left-2 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                    <button type="button" id="cutListSearchClear"
                        class="hidden absolute right-2 top-1.5 text-gray-300 hover:text-gray-500 text-base leading-none w-4 h-4">&times;</button>
                </div>
                <div id="cutListTabs" class="flex items-center gap-1.5">
                    <button type="button" data-filter="unread"
                        class="cutList-tab px-3 py-1.5 text-[11px] font-semibold rounded-lg border transition-colors flex items-center gap-1.5">
                        New
                        <span id="cutListNewCount"
                            class="inline-flex items-center justify-center min-w-[16px] h-[16px] px-1 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">0</span>
                    </button>
                    <button type="button" data-filter="all"
                        class="cutList-tab px-3 py-1.5 text-[11px] font-semibold rounded-lg border transition-colors flex items-center gap-1.5">
                        All
                        <span id="cutListAllCount"
                            class="inline-flex items-center justify-center min-w-[16px] h-[16px] px-1 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">0</span>
                    </button>
                </div>
            </div>

            <!-- Table Card -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead>
                            <tr
                                class="bg-gray-50 border-b border-gray-200 text-left text-[10px] uppercase tracking-wide text-gray-500">
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap"></th>
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Control No.</th>
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Client</th>
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Branch</th>
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Sales Staff</th>
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Designer</th>
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Project Type</th>
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">NTP By Accounting</th>
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Status</th>
                                <th class="px-4 py-2.5 font-semibold text-right whitespace-nowrap">Action</th>
                            </tr>
                        </thead>
                        <tbody id="cutListTbody" class="divide-y divide-gray-100"></tbody>
                    </table>
                </div>
            </div>

            <p id="cutListCount" class="text-[11px] text-gray-400 mt-2.5"></p>
        </div>

        <!-- Toast container -->
        <div id="crmToastContainer"
            class="fixed bottom-6 right-6 z-[9999] flex flex-col gap-2.5 pointer-events-none w-full max-w-sm px-4 sm:px-0">
        </div>

    </main>

    <script>
        function crmShowToast(message, type = 'success', duration = 4000) {
            const container = document.getElementById('crmToastContainer');
            const palette = type === 'success'
                ? { wrap: 'bg-green-50 border-green-200 text-green-700', icon: 'bg-green-200 text-green-700', symbol: '✓' }
                : { wrap: 'bg-red-50 border-red-200 text-red-700', icon: 'bg-red-200 text-red-700', symbol: '!' };

            const toast = document.createElement('div');
            toast.className = `pointer-events-auto flex items-start gap-2.5 border rounded-lg shadow-lg px-4 py-3 text-sm
                ${palette.wrap}
                translate-x-6 opacity-0 scale-95 transition-all duration-300 ease-out`;

            toast.innerHTML = `
                <span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold ${palette.icon}">${palette.symbol}</span>
                <span class="flex-1 leading-relaxed">${message}</span>
                <button type="button" class="shrink-0 text-current opacity-50 hover:opacity-100 text-base leading-none" aria-label="Close">&times;</button>
            `;
            container.appendChild(toast);
            requestAnimationFrame(() => toast.classList.remove('translate-x-6', 'opacity-0', 'scale-95'));
            const remove = () => {
                toast.classList.add('translate-x-6', 'opacity-0', 'scale-95');
                setTimeout(() => toast.remove(), 300);
            };
            toast.querySelector('button').addEventListener('click', remove);
            if (duration > 0) setTimeout(remove, duration);
        }

        const CUT_LIST_AJAX_URL = <?= json_encode($cutListAjaxUrl) ?>;
        const CUT_DETAIL_URL = <?= json_encode($cutDetailUrl) ?>;
        const CUT_LIST_POLL_INTERVAL_MS = 8000;

        let cutListSearchTerm = '';
        let cutListFilter = 'all';
        let cutListLastSignature = '';
        let cutListPollTimer = null;
        let cutListSearchDebounce = null;

        function cutListEscapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }

        function cutListFormatDate(value) {
            if (!value) return '—';
            const dt = new Date(value.replace(' ', 'T'));
            if (isNaN(dt.getTime())) return value;
            return dt.toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
        }

        function cutListFormatDateTimeLong(value) {
            if (!value) return '—';
            const dt = new Date(value.replace(' ', 'T'));
            if (isNaN(dt.getTime())) return value;
            return dt.toLocaleString('en-PH', {
                year: 'numeric', month: 'long', day: 'numeric',
                hour: 'numeric', minute: '2-digit', hour12: true
            });
        }

        function cutListTimeAgo(value) {
            if (!value) return '—';
            const dt = new Date(value.replace(' ', 'T'));
            if (isNaN(dt.getTime())) return value;
            const diffSec = Math.floor((Date.now() - dt.getTime()) / 1000);
            if (diffSec < 0) return cutListFormatDate(value);
            if (diffSec < 60) return 'Just now';
            const diffMin = Math.floor(diffSec / 60);
            if (diffMin < 60) return `${diffMin}m ago`;
            const diffHr = Math.floor(diffMin / 60);
            if (diffHr < 24) return `${diffHr}h ago`;
            const diffDay = Math.floor(diffHr / 24);
            if (diffDay < 7) return `${diffDay}d ago`;
            return cutListFormatDate(value);
        }

        function cutListText(value) {
            return (value === null || value === undefined || value === '')
                ? '<span class="text-gray-300">—</span>'
                : cutListEscapeHtml(value);
        }

        function cutListNewDot(isRead) {
            if (isRead) return '';
            return `<span class="relative flex h-2 w-2 shrink-0">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-amber-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2 w-2 bg-amber-500"></span>
                    </span>`;
        }

    
        function cutListNtpBadge(row) {
            const isNtp = row.deposit_status === 'Notice to Proceed';

            if (isNtp) {
                return `
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center w-7 h-7 text-white shrink-0">
                            <i class="fa-solid fa-circle-check text-[15px] text-green-700"></i>
                        </span>
                        <div class="leading-tight">
                            <p class="text-[12px] font-semibold text-green-800">Notice to Proceed</p>
                            <p class="text-[10px] text-gray-400">${cutListFormatDate(row.deposit_uploaded_at)}</p>
                        </div>
                    </div>
                `;
            }

            return `
                <div class="flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-7 h-7 text-white shrink-0">
                        <i class="fa-solid fa-clock text-[15px] text-amber-600"></i>
                    </span>
                    <div class="leading-tight">
                        <p class="text-[12px] font-semibold text-amber-700">Hold</p>
                        <p class="text-[10px] text-gray-400">Waiting on Accounting</p>
                    </div>
                </div>
            `;
        }

        // Status cell: mirrors the NTP badge but as a compact pill, next to
        // the "NTP By Accounting" column so it's scannable at a glance.
        function cutListStatusPill(row) {
            const isNtp = row.deposit_status === 'Notice to Proceed';
            const cls = isNtp
                ? 'bg-green-50 text-green-700 border-green-200'
                : 'bg-amber-50 text-amber-700 border-amber-200';
            return `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-semibold border whitespace-nowrap ${cls}">
                        <span class="w-1.5 h-1.5 rounded-full shrink-0 ${isNtp ? 'bg-green-600' : 'bg-amber-600'}"></span>
                        ${isNtp ? 'NTP' : 'Hold'}
                    </span>`;
        }

        function cutListGoToDetail(id) {
            window.location.href = `${CUT_DETAIL_URL}?id=${id}`;
        }

        // ── Tabs ──
        function cutListInitTabs() {
            document.querySelectorAll('.cutList-tab').forEach(btn => {
                btn.addEventListener('click', () => {
                    cutListFilter = btn.dataset.filter;
                    cutListLastSignature = '';
                    cutListRenderTabs();
                    cutListFetchList();
                });
            });
            cutListRenderTabs();
        }

        function cutListRenderTabs() {
            document.querySelectorAll('.cutList-tab').forEach(btn => {
                const active = btn.dataset.filter === cutListFilter;
                btn.classList.toggle('bg-amber-700', active);
                btn.classList.toggle('text-white', active);
                btn.classList.toggle('border-amber-700', active);
                btn.classList.toggle('bg-white', !active);
                btn.classList.toggle('text-gray-600', !active);
                btn.classList.toggle('border-gray-300', !active);
                btn.classList.toggle('hover:bg-gray-50', !active);
            });
        }

        function cutListSkeletonRows(count = 6) {
            const tbody = document.getElementById('cutListTbody');
            tbody.innerHTML = Array.from({ length: count }).map(() => `
                <tr>
                    ${Array.from({ length: 10 }).map(() => `
                        <td class="px-4 py-3"><div class="h-3 rounded bg-gray-100 animate-pulse"></div></td>
                    `).join('')}
                </tr>
            `).join('');
        }

        function cutListEmptyState() {
            const message = cutListSearchTerm
                ? `No submissions match "${cutListEscapeHtml(cutListSearchTerm)}".`
                : (cutListFilter === 'unread' ? 'No new approved submissions.' : 'No approved submissions found.');
            return `
                <tr>
                    <td colspan="10" class="p-0">
                        <div class="flex flex-col items-center justify-center gap-2 py-10 text-center">
                            <svg class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <p class="text-gray-400 text-xs">${message}</p>
                        </div>
                    </td>
                </tr>
            `;
        }

        function cutListRenderRows(rows) {
            const tbody = document.getElementById('cutListTbody');

            if (rows.length === 0) {
                tbody.innerHTML = cutListEmptyState();
                return;
            }

            tbody.innerHTML = rows.map(row => `
                <tr class="hover:bg-amber-50/40 transition-colors cursor-pointer" onclick="cutListGoToDetail(${row.id})">
                    <td class="px-4 py-2.5 w-5">${cutListNewDot(row.is_read)}</td>
                    <td class="px-4 py-2.5">
                        <span class="font-mono text-[11px] font-semibold text-amber-700">${cutListEscapeHtml(row.control_no)}</span>
                    </td>
                    <td class="px-4 py-2.5 text-gray-800 font-medium">${cutListText(row.client_name)}</td>
                    <td class="px-4 py-2.5 text-gray-600">${cutListText(row.branch)}</td>
                    <td class="px-4 py-2.5 text-gray-600">${cutListText(row.sales_staff_name)}</td>
                    <td class="px-4 py-2.5 text-gray-600">${cutListText(row.designer_name)}</td>
                    <td class="px-4 py-2.5">
                        ${row.project_type
                    ? `<span class="inline-block px-2 py-0.5 rounded-full text-[10.5px] font-semibold bg-amber-50 text-amber-700 border border-amber-200">${cutListEscapeHtml(row.project_type)}</span>`
                    : '<span class="text-gray-300">—</span>'}
                    </td>
                    <td class="px-4 py-2.5">${cutListNtpBadge(row)}</td>
                    <td class="px-4 py-2.5">${cutListStatusPill(row)}</td>
                    <td class="px-4 py-2.5 text-right" onclick="event.stopPropagation()">
                        <button type="button" onclick="cutListGoToDetail(${row.id})"
                            class="px-2.5 py-1.5 text-[11px] font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors">
                            View Details
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        async function cutListFetchList({ silent = false } = {}) {
            if (!silent) cutListSkeletonRows();
            try {
                const url = `${CUT_LIST_AJAX_URL}?action=list&q=${encodeURIComponent(cutListSearchTerm)}&filter=${encodeURIComponent(cutListFilter)}`;
                const res = await fetch(url);
                const data = await res.json();

                if (!data.success) {
                    if (!silent) crmShowToast('Failed to load submissions.', 'error');
                    return;
                }

                const signature = JSON.stringify(data.rows.map(r => r.id + ':' + r.is_read + ':' + r.deposit_status)) + cutListFilter;
                if (signature !== cutListLastSignature) {
                    cutListRenderRows(data.rows);
                    cutListLastSignature = signature;
                }

                document.getElementById('cutListCount').textContent =
                    `${data.count} submission${data.count === 1 ? '' : 's'} found`;

                if (cutListFilter === 'all') {
                    const allCountEl = document.getElementById('cutListAllCount');
                    if (allCountEl) allCountEl.textContent = data.count > 99 ? '99+' : data.count;
                }

                const unreadCount = cutListFilter === 'unread'
                    ? data.count
                    : data.rows.filter(r => !r.is_read).length;
                const newCountEl = document.getElementById('cutListNewCount');
                if (newCountEl) newCountEl.textContent = unreadCount > 99 ? '99+' : unreadCount;

            } catch (e) {
                console.error('cutListFetchList:', e);
                if (!silent) crmShowToast('Connection error while fetching submissions.', 'error');
            }
        }

        function cutListStartPolling() {
            if (cutListPollTimer) clearInterval(cutListPollTimer);
            cutListPollTimer = setInterval(() => cutListFetchList({ silent: true }), CUT_LIST_POLL_INTERVAL_MS);
        }

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                if (cutListPollTimer) clearInterval(cutListPollTimer);
            } else {
                cutListFetchList({ silent: true });
                cutListStartPolling();
            }
        });

        const cutListSearchInput = document.getElementById('cutListSearch');
        const cutListSearchClear = document.getElementById('cutListSearchClear');

        cutListSearchInput.addEventListener('input', function () {
            cutListSearchClear.classList.toggle('hidden', this.value.length === 0);
            clearTimeout(cutListSearchDebounce);
            const value = this.value;
            cutListSearchDebounce = setTimeout(() => {
                cutListSearchTerm = value.trim();
                cutListLastSignature = '';
                cutListFetchList();
            }, 350);
        });

        cutListSearchClear.addEventListener('click', () => {
            cutListSearchInput.value = '';
            cutListSearchClear.classList.add('hidden');
            cutListSearchTerm = '';
            cutListLastSignature = '';
            cutListFetchList();
            cutListSearchInput.focus();
        });

        cutListInitTabs();
        cutListFetchList().then(cutListStartPolling);
    </script>
</body>

</html>