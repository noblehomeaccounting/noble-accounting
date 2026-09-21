<?php
// check2dquotation.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SUPERADMIN];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$chk2dAjaxUrl = BASE_URL . '/check2dquotationajax';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2D Quotation Approval</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <style>
        /* Initial ↔ Final overview: both halves of a pair light up together on hover */
        .chk2d-pair-cell {
            transition: background-color .15s ease, border-color .15s ease, box-shadow .15s ease;
        }

        .chk2d-pair-cell.chk2d-pair-active {
            background-color: #fffbeb !important;
            border-color: #d97706 !important;
            box-shadow: 0 0 0 3px rgba(217, 119, 6, .18);
        }
    </style>
</head>

<body class="bg-slate-100">
    <main class="ml-56 min-h-screen p-8 overflow-x-hidden">

        <div class="max-w-6xl mx-auto">

            <!-- Header -->
            <div class="mb-4">
                <div class="mb-3">
                    <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase mb-0.5">CRM
                        Management</p>
                    <h1 class="text-gray-900 text-xl font-semibold">2D &amp; Quotation Approval</h1>
                </div>

                <!-- Search + Tabs, pantay sa iisang row -->
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="relative w-full sm:w-64 min-w-0">
                        <input id="chk2dSearch" type="text" placeholder="Search control no. / client / contact"
                            class="w-full pl-8 pr-5 py-1.5 text-xs border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-100 focus:border-amber-600 bg-white transition-colors">
                        <i
                            class="fa-brands fa-sistrix absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                        <button type="button" id="chk2dSearchClear"
                            class="hidden absolute right-2 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-500 text-base leading-none w-4 h-4">&times;</button>
                    </div>

                    <!-- Status filter tabs -->
                    <div id="chk2dTabs" class="flex items-center gap-1.5">
                        <button type="button" data-status=""
                            class="chk2d-tab px-3 py-1.5 text-xs font-semibold rounded-lg border transition-colors flex items-center gap-1.5">
                            All
                            <span
                                class="chk2d-tab-count inline-flex items-center justify-center min-w-[1.15rem] h-[1.15rem] px-1 rounded-full text-[10px] font-bold bg-black/10">0</span>
                        </button>
                        <button type="button" data-status="Waiting for Approval"
                            class="chk2d-tab px-3 py-1.5 text-xs font-semibold rounded-lg border transition-colors flex items-center gap-1.5">
                            Queuing
                            <span
                                class="chk2d-tab-count inline-flex items-center justify-center min-w-[1.15rem] h-[1.15rem] px-1 rounded-full text-[10px] font-bold bg-black/10">0</span>
                        </button>
                        <button type="button" data-status="Approved"
                            class="chk2d-tab px-3 py-1.5 text-xs font-semibold rounded-lg border transition-colors flex items-center gap-1.5">
                            Approved
                            <span
                                class="chk2d-tab-count inline-flex items-center justify-center min-w-[1.15rem] h-[1.15rem] px-1 rounded-full text-[10px] font-bold bg-black/10">0</span>
                        </button>
                        <button type="button" data-status="For Revision"
                            class="chk2d-tab px-3 py-1.5 text-xs font-semibold rounded-lg border transition-colors flex items-center gap-1.5">
                            For Revision
                            <span
                                class="chk2d-tab-count inline-flex items-center justify-center min-w-[1.15rem] h-[1.15rem] px-1 rounded-full text-[10px] font-bold bg-black/10">0</span>
                        </button>
                    </div>
                </div>
            </div>



            <!-- Table Card -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead>
                            <tr
                                class="bg-gray-50 border-b border-gray-200 text-left text-[10px] uppercase tracking-wide text-gray-500">
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Control No.</th>
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Client</th>
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">2D File</th>
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Quotation File</th>
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">3D File</th>
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Contract Amount</th>
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Submitted</th>
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Status</th>
                                <th class="px-4 py-2.5 font-semibold text-right whitespace-nowrap">Action</th>
                            </tr>
                        </thead>
                        <tbody id="chk2dTbody" class="divide-y divide-gray-100"></tbody>
                    </table>
                </div>
            </div>

            <p id="chk2dCount" class="text-[11px] text-gray-400 mt-2.5"></p>

            <!-- ═══════════════════════════════════════════════════════════
                 INITIAL ↔ FINAL OVERVIEW
                 One aligned row per inquiry. Hover either side and its pair
                 on the other side is highlighted too.
            ═══════════════════════════════════════════════════════════ -->
            <div class="mt-8">
                <div class="mb-3">
                    <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase mb-0.5">Overview</p>
                    <h2 class="text-gray-900 text-base font-semibold">Initial &amp; Final</h2>
                    <p class="text-[11px] text-gray-400 mt-0.5">Hover a submission to highlight its Initial ↔ Final
                        pair. Click one to open it.</p>
                </div>

                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="grid grid-cols-2 gap-x-4 px-4 pt-4 pb-2 border-b border-gray-100">
                        <div class="flex items-center gap-2">
                            <span
                                class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wide border bg-amber-50 text-amber-700 border-amber-200">Initial</span>
                            <span class="text-[11px] text-gray-400">2D &amp; Quotation</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span
                                class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wide border bg-green-50 text-green-700 border-green-200">Final</span>
                            <span class="text-[11px] text-gray-400">2D &amp; Quotation</span>
                        </div>
                    </div>

                    <div id="chk2dPairBody"
                        class="grid grid-cols-2 gap-x-3 gap-y-1.5 p-3 max-h-[34rem] overflow-y-auto">
                        <div class="col-span-2 py-8 text-center text-xs text-gray-400">Loading…</div>
                    </div>
                </div>

                <p id="chk2dPairCount" class="text-[11px] text-gray-400 mt-2.5"></p>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════
             RIGHT-SIDE REVIEW PANEL (slides in from the right)
        ═══════════════════════════════════════════════════════════ -->
        <div id="chk2dOverlay" class="fixed inset-0 bg-black/30 hidden z-40" onclick="chk2dClosePanel()"></div>

        <div id="chk2dPanel" class="fixed top-0 right-0 h-full w-full max-w-xl bg-white shadow-2xl z-50 flex flex-col
                   translate-x-full transition-transform duration-300 ease-out">

            <div class="px-5 py-4 border-b border-gray-100 flex items-start justify-between shrink-0">
                <div>
                    <p id="chk2dModalHeading"
                        class="text-[10px] text-amber-700 font-semibold tracking-[0.15em] uppercase mb-0.5">
                        Submission Review</p>
                    <h3 id="chk2dModalControlNo" class="text-gray-900 font-mono font-semibold text-sm">—</h3>
                </div>
                <button type="button" onclick="chk2dClosePanel()"
                    class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
            </div>

            <div id="chk2dModalBody" class="px-5 py-4 overflow-y-auto space-y-0.5 flex-1">
                <!-- Populated via JS -->
            </div>

            <div id="chk2dModalFooter" class="px-5 py-4 bg-gray-50 border-t border-gray-100 shrink-0">
                <!-- Populated via JS: per-file decisions + Submit Review, or read-only status -->
            </div>
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

        const CHK2D_AJAX_URL = <?= json_encode($chk2dAjaxUrl) ?>;
        const CHK2D_POLL_INTERVAL_MS = 8000;

        let chk2dSearchTerm = '';
        let chk2dStatusFilter = ''; // default tab: "All"
        let chk2dLastSignature = '';
        let chk2dPollTimer = null;
        let chk2dSearchDebounce = null;
        let chk2dCurrentId = null;
        let chk2dCurrentStage = null; // 'Initial' | 'Final' — ids repeat across the two tables
        let chk2dLastRows = []; // most recently rendered row set

        const CHK2D_VIEWED_KEY = 'chk2dViewedIds';

        function chk2dRowKey(stage, id) {
            return `${stage}:${id}`;
        }

        function chk2dGetViewedIds() {
            try {
                const raw = localStorage.getItem(CHK2D_VIEWED_KEY);
                return raw ? new Set(JSON.parse(raw)) : new Set();
            } catch (e) {
                return new Set();
            }
        }

        // Old entries were plain numeric ids (Initial only) — still honored.
        function chk2dIsViewed(viewedSet, stage, id) {
            return viewedSet.has(chk2dRowKey(stage, id)) || (stage === 'Initial' && viewedSet.has(id));
        }

        function chk2dMarkViewed(stage, id) {
            const viewed = chk2dGetViewedIds();
            if (chk2dIsViewed(viewed, stage, id)) return;
            viewed.add(chk2dRowKey(stage, id));
            try {
                localStorage.setItem(CHK2D_VIEWED_KEY, JSON.stringify([...viewed]));
            } catch (e) {
                console.error('chk2dMarkViewed:', e);
            }
        }

        let chk2dDecisions = {
            design_2d: { decision: null, remarks: '' },
            quotation: { decision: null, remarks: '' },
        };

        function chk2dEscapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }

        function chk2dFormatDate(value) {
            if (!value) return '—';
            const dt = new Date(value.replace(' ', 'T'));
            if (isNaN(dt.getTime())) return value;
            return dt.toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
        }

        function chk2dFormatDateTimeLong(value) {
            if (!value) return '—';
            const dt = new Date(value.replace(' ', 'T'));
            if (isNaN(dt.getTime())) return value;
            return dt.toLocaleString('en-PH', {
                year: 'numeric', month: 'long', day: 'numeric',
                hour: 'numeric', minute: '2-digit', hour12: true
            });
        }

        function chk2dStatusBadge(status, compact = false) {


            const map = {
                'Approved': 'bg-green-50 text-green-700 border-green-200',
                'For Revision': 'bg-red-50 text-red-700 border-red-200',
                'Waiting for Approval': 'bg-amber-50 text-amber-700 border-amber-200',
            };
            const dotMap = {
                'Approved': 'bg-green-500',
                'For Revision': 'bg-red-500',
                'Waiting for Approval': 'bg-amber-500',
            };
            const cls = map[status] || 'bg-gray-50 text-gray-600 border-gray-200';
            const dot = dotMap[status] || 'bg-gray-400';
            const size = compact ? 'px-2 py-0.5 text-[10px]' : 'px-2.5 py-1 text-[11px]';
            return `<span class="inline-flex items-center gap-1.5 ${size} rounded-full font-semibold border whitespace-nowrap ${cls}">
                <span class="w-1.5 h-1.5 rounded-full shrink-0 ${dot}"></span>${chk2dEscapeHtml(status)}
            </span>`;
        }

        // Initial / Final tag — same colors as the Initial and Final pages.
        function chk2dStageBadge(stage) {

            const isFinal = stage === 'Final';
            const cls = isFinal
                ? 'bg-green-50 text-green-700 border-green-200'
                : 'bg-amber-50 text-amber-700 border-amber-200';
            return `<span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wide border whitespace-nowrap ${cls}">${isFinal ? 'Final' : 'Initial'}</span>`;
        }

        // When a row is overall 'Approved' but its 3D file is still sitting in
        // the sequential queue, the plain "Approved" badge alone would be
        // misleading — show a small suffix pill.
        function chk2dStatusBadgeWithRow(row, compact = false) {
            const base = chk2dStatusBadge(row.status, compact);
            if (row.status === 'Approved' && row.design_3d_stage === 'Waiting for Approval') {
                return base + ` <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border border-amber-200 bg-amber-50 text-amber-700 whitespace-nowrap ml-1">3D Waiting</span>`;
            }
            if (row.status === 'Approved' && row.design_3d_stage === 'For Revision') {
                return base + ` <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border border-red-200 bg-red-50 text-red-700 whitespace-nowrap ml-1">3D Revision</span>`;
            }
            return base;
        }

        // Smaller badge used for the per-file review status (Pending / Approved / For Revision).
        function chk2dReviewBadge(status) {
            const map = {
                'Approved': 'bg-green-50 text-green-700 border-green-200',
                'For Revision': 'bg-red-50 text-red-700 border-red-200',
                'Pending': 'bg-gray-50 text-gray-500 border-gray-200',
            };
            const cls = map[status] || map['Pending'];
            return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border whitespace-nowrap ${cls}">${chk2dEscapeHtml(status || 'Pending')}</span>`;
        }

        function chk2dFileLink(path, uploaderName, uploaderRole) {
            if (!path) return `<span class="text-gray-300">—</span>`;
            return `
                <a href="${chk2dEscapeHtml(path)}" target="_blank" rel="noopener"
                    class="text-amber-700 hover:underline font-medium text-xs whitespace-nowrap">View File</a>
                <p class="text-[10px] text-gray-400 whitespace-nowrap">${chk2dEscapeHtml(uploaderName)} (${chk2dEscapeHtml(uploaderRole)})</p>
            `;
        }

        // ── Tabs ──
        function chk2dInitTabs() {
            document.querySelectorAll('.chk2d-tab').forEach(btn => {
                btn.addEventListener('click', () => {
                    chk2dStatusFilter = btn.dataset.status;
                    chk2dLastSignature = '';
                    chk2dRenderTabs();
                    chk2dFetchList();
                });
            });
            chk2dRenderTabs();
        }

        function chk2dRenderTabs() {
            document.querySelectorAll('.chk2d-tab').forEach(btn => {
                const active = btn.dataset.status === chk2dStatusFilter;
                btn.classList.toggle('bg-amber-700', active);
                btn.classList.toggle('text-white', active);
                btn.classList.toggle('border-amber-700', active);
                btn.classList.toggle('bg-white', !active);
                btn.classList.toggle('text-gray-600', !active);
                btn.classList.toggle('border-gray-300', !active);
                btn.classList.toggle('hover:bg-gray-50', !active);
            });
        }

        async function chk2dFetchCounts() {
            try {
                const url = `${CHK2D_AJAX_URL}?action=list&q=&status=`;
                const res = await fetch(url);
                const data = await res.json();
                if (!data.success) return;

                const counts = {
                    '': data.rows.length,
                    'Waiting for Approval': 0,
                    'Approved': 0,
                    'For Revision': 0,
                };
                data.rows.forEach(row => {
                    if (counts[row.status] !== undefined) counts[row.status]++;
                });

                document.querySelectorAll('.chk2d-tab').forEach(btn => {
                    const countEl = btn.querySelector('.chk2d-tab-count');
                    if (countEl) countEl.textContent = counts[btn.dataset.status] ?? 0;
                });

                // Same full (unfiltered-by-status) row set also feeds the Initial ↔ Final overview.
                chk2dAllRows = data.rows;
                chk2dRenderPairs();
            } catch (e) {
                console.error('chk2dFetchCounts:', e);
            }
        }

        // ── Skeleton / empty states ──
        function chk2dSkeletonRows(count = 5) {
            const tbody = document.getElementById('chk2dTbody');
            tbody.innerHTML = Array.from({ length: count }).map(() => `
                <tr>
                    ${Array.from({ length: 9 }).map(() => `
                        <td class="px-4 py-3"><div class="h-3 rounded bg-gray-100 animate-pulse"></div></td>
                    `).join('')}
                </tr>
            `).join('');
        }

        function chk2dEmptyState() {
            const message = chk2dSearchTerm
                ? `No submissions match "${chk2dEscapeHtml(chk2dSearchTerm)}".`
                : 'No submissions found.';
            return `
                <tr>
                    <td colspan="9" class="p-0">
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

        // Review whenever review_target says there's something to decide on
        // (main / main_with_3d / 3d_only); View otherwise.
        function chk2dActionButton(row) {
            if (row.review_target && row.review_target !== 'none') {
                return `<button type="button" onclick="chk2dOpenPanel('${row.stage}', ${row.id})"
                            class="px-3 py-1.5 text-xs font-medium text-white bg-amber-700 rounded-lg hover:bg-amber-800 transition-colors whitespace-nowrap">
                            Review
                        </button>`;
            }
            return `<button type="button" onclick="chk2dOpenPanel('${row.stage}', ${row.id})"
                        class="px-3 py-1.5 text-xs font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors whitespace-nowrap">
                        View
                    </button>`;
        }

        function chk2dFormatCurrency(value) {
            const num = Number(value);
            if (!value || isNaN(num)) return '<span class="text-gray-300">—</span>';
            return '₱' + num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function chk2dRenderRows(rows) {
            const tbody = document.getElementById('chk2dTbody');
            chk2dLastRows = rows;

            if (rows.length === 0) {
                tbody.innerHTML = chk2dEmptyState();
                return;
            }

            const viewedIds = chk2dGetViewedIds();

            tbody.innerHTML = rows.map(row => {
                const isViewed = chk2dIsViewed(viewedIds, row.stage, row.id);
                const isActive = chk2dCurrentId === row.id && chk2dCurrentStage === row.stage;

                let rowBgCls = '';
                if (isActive) rowBgCls = 'bg-amber-100';
                else if (!isViewed) rowBgCls = 'bg-amber-50/30';

                const firstCellAccent = isActive
                    ? 'border-l-4 border-l-amber-600 pl-3'
                    : 'border-l-4 border-l-transparent pl-3';
                const unreadDot = isViewed
                    ? ''
                    : `<span class="inline-block w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0" title="Not yet viewed"></span>`;
                const viewedLabel = isViewed
                    ? `<span class="text-[10px] text-gray-400 whitespace-nowrap">Viewed</span>`
                    : '';
                return `
                <tr class="hover:bg-amber-50/40 transition-colors cursor-pointer ${rowBgCls}" data-row-key="${chk2dRowKey(row.stage, row.id)}" onclick="chk2dOpenPanel('${row.stage}', ${row.id})">
                    <td class="pr-4 py-2.5 ${firstCellAccent}">
                        <div class="flex items-center gap-1.5">
                            ${unreadDot}
                            <span class="font-mono text-[11px] font-semibold text-amber-700 whitespace-nowrap">${chk2dEscapeHtml(row.control_no)}</span>
                        </div>
                        <div class="flex items-center gap-1.5 mt-0.5">
                            ${chk2dStageBadge(row.stage)}
                            ${viewedLabel}
                        </div>
                    </td>
                    <td class="px-4 py-2.5 text-gray-800">${chk2dEscapeHtml(row.client_name)}</td>
                    <td class="px-4 py-2.5 whitespace-nowrap" onclick="event.stopPropagation()">${chk2dFileLink(row.design_2d_path, row.design_2d_uploader_name, row.design_2d_uploaded_role)}</td>
                    <td class="px-4 py-2.5 whitespace-nowrap" onclick="event.stopPropagation()">${chk2dFileLink(row.quotation_path, row.quotation_uploader_name, row.quotation_uploaded_role)}</td>
                    <td class="px-4 py-2.5 whitespace-nowrap" onclick="event.stopPropagation()">${(row.include_3d || row.design_3d_stage !== 'Locked')
                        ? chk2dFileLink(row.design_3d_path, row.design_3d_uploader_name, row.design_3d_uploaded_role)
                        : '<span class="text-gray-300 text-xs">Not yet</span>'
                    }</td>
                    <td class="px-4 py-2.5 text-gray-800 font-medium whitespace-nowrap">${chk2dFormatCurrency(row.contract_amount)}</td>
                    <td class="px-4 py-2.5 text-gray-500 whitespace-nowrap">${chk2dFormatDate(row.submitted_at)}</td>
                    <td class="px-4 py-2.5">${chk2dStatusBadgeWithRow(row)}</td>
                    <td class="px-4 py-2.5 text-right" onclick="event.stopPropagation()">${chk2dActionButton(row)}</td>
                </tr>
            `;
            }).join('');
        }

        async function chk2dFetchList({ silent = false } = {}) {
            if (!silent) chk2dSkeletonRows();
            try {
                const url = `${CHK2D_AJAX_URL}?action=list&q=${encodeURIComponent(chk2dSearchTerm)}&status=${encodeURIComponent(chk2dStatusFilter)}`;
                const res = await fetch(url);
                const data = await res.json();

                if (!data.success) {
                    if (!silent) crmShowToast('Failed to load submissions.', 'error');
                    return;
                }

                const signature = JSON.stringify(data.rows.map(r => `${r.stage}:${r.id}:${r.status}:${r.design_3d_stage}`)) + chk2dStatusFilter;
                if (signature !== chk2dLastSignature) {
                    chk2dRenderRows(data.rows);
                    chk2dLastSignature = signature;
                }

                document.getElementById('chk2dCount').textContent =
                    `${data.count} submission${data.count === 1 ? '' : 's'} found`;

                chk2dFetchCounts();

            } catch (e) {
                console.error('chk2dFetchList:', e);
                if (!silent) crmShowToast('Connection error while fetching submissions.', 'error');
            }
        }

        function chk2dStartPolling() {
            if (chk2dPollTimer) clearInterval(chk2dPollTimer);
            chk2dPollTimer = setInterval(() => chk2dFetchList({ silent: true }), CHK2D_POLL_INTERVAL_MS);
        }

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                if (chk2dPollTimer) clearInterval(chk2dPollTimer);
            } else {
                chk2dFetchList({ silent: true });
                chk2dStartPolling();
            }
        });

        const chk2dSearchInput = document.getElementById('chk2dSearch');
        const chk2dSearchClear = document.getElementById('chk2dSearchClear');

        chk2dSearchInput.addEventListener('input', function () {
            chk2dSearchClear.classList.toggle('hidden', this.value.length === 0);
            clearTimeout(chk2dSearchDebounce);
            const value = this.value;
            chk2dSearchDebounce = setTimeout(() => {
                chk2dSearchTerm = value.trim();
                chk2dLastSignature = '';
                chk2dFetchList();
            }, 350);
        });

        chk2dSearchClear.addEventListener('click', () => {
            chk2dSearchInput.value = '';
            chk2dSearchClear.classList.add('hidden');
            chk2dSearchTerm = '';
            chk2dLastSignature = '';
            chk2dFetchList();
            chk2dSearchInput.focus();
        });

        // ═══════════════════════════════════════════════════════════
        // INITIAL ↔ FINAL OVERVIEW
        // Two columns, one aligned row per inquiry. Hovering either side
        // highlights its pair on the other side (matched by inquiry_id).
        // ═══════════════════════════════════════════════════════════
        let chk2dAllRows = [];
        let chk2dPairSignature = '';
        let chk2dPairActiveId = null;

        function chk2dPairCell(row, stage, group) {
            const pairId = group.inquiry_id;

            if (!row) {
                let msg = 'No Initial submitted';
                if (stage === 'Final') {
                    msg = (group.initial && group.initial.status !== 'Approved')
                        ? 'Locked — Initial not approved yet'
                        : 'No Final submitted yet';
                }
                return `
            <div class="chk2d-pair-cell border border-dashed border-gray-300 rounded-md bg-gray-50/60 px-2.5 py-1.5 flex items-center"
                 data-pair="${pairId}">
                <p class="text-[10px] text-gray-400 italic">${msg}</p>
            </div>
        `;
            }

            return `
        <div class="chk2d-pair-cell border border-gray-200 rounded-md bg-white px-2.5 py-1.5 cursor-pointer"
             data-pair="${pairId}" onclick="chk2dOpenPanel('${row.stage}', ${row.id})">
            <div class="flex items-center justify-between gap-2">
                <span class="font-mono text-[10px] font-semibold text-amber-700 whitespace-nowrap">${chk2dEscapeHtml(row.control_no)}</span>
                ${chk2dStatusBadgeWithRow(row, true)}
            </div>
            <p class="text-[11px] text-gray-800 truncate leading-tight mt-0.5">
                ${chk2dEscapeHtml(row.client_name)}
                <span class="text-gray-400">· ${chk2dFormatDate(row.submitted_at)}</span>
            </p>
        </div>
    `;
        }

        function chk2dRenderPairs() {
            const body = document.getElementById('chk2dPairBody');
            const countEl = document.getElementById('chk2dPairCount');
            if (!body) return;

            // Same search box as the table above — filtered client-side.
            const term = chk2dSearchTerm.toLowerCase();
            const rows = term
                ? chk2dAllRows.filter(r => [r.control_no, r.client_name, r.contact_number]
                    .some(v => String(v ?? '').toLowerCase().includes(term)))
                : chk2dAllRows;

            // Only re-render when something actually changed, so the hover
            // highlight doesn't flicker on every poll.
            const signature = term + '|' + rows.map(r => `${r.stage}:${r.id}:${r.status}:${r.design_3d_stage}`).join(',');
            if (signature === chk2dPairSignature) return;
            chk2dPairSignature = signature;
            chk2dPairActiveId = null;

            const groups = new Map(); // inquiry_id → { initial, final } (rows arrive newest-first)
            rows.forEach(r => {
                if (!groups.has(r.inquiry_id)) {
                    groups.set(r.inquiry_id, { inquiry_id: r.inquiry_id, initial: null, final: null });
                }
                const g = groups.get(r.inquiry_id);
                if (r.stage === 'Final') g.final = r; else g.initial = r;
            });

            if (groups.size === 0) {
                body.innerHTML = `<div class="col-span-2 py-8 text-center text-xs text-gray-400">${term ? 'No matches.' : 'No submissions to show.'}</div>`;
                if (countEl) countEl.textContent = '';
                return;
            }

            body.innerHTML = [...groups.values()]
                .map(g => chk2dPairCell(g.initial, 'Initial', g) + chk2dPairCell(g.final, 'Final', g))
                .join('');

            if (countEl) countEl.textContent = `${groups.size} inquir${groups.size === 1 ? 'y' : 'ies'}`;
        }

        // Linked hover: whichever side you point at, both cells of that pair light up.
        (function chk2dBindPairHover() {
            const body = document.getElementById('chk2dPairBody');
            if (!body) return;

            function setActive(id) {
                if (id === chk2dPairActiveId) return;
                body.querySelectorAll('.chk2d-pair-active').forEach(el => el.classList.remove('chk2d-pair-active'));
                chk2dPairActiveId = id;
                if (id !== null) {
                    body.querySelectorAll(`[data-pair="${id}"]`).forEach(el => el.classList.add('chk2d-pair-active'));
                }
            }

            body.addEventListener('mouseover', e => {
                const cell = e.target.closest('[data-pair]');
                setActive(cell ? cell.dataset.pair : null);
            });
            body.addEventListener('mouseleave', () => setActive(null));
        })();

        chk2dInitTabs();
        chk2dFetchList().then(chk2dStartPolling);

        // ═══════════════════════════════════════════════════════════
        // REVIEW PANEL (right-side slide-in)
        // ═══════════════════════════════════════════════════════════
        function chk2dDetailRow(label, value) {
            return `
                <div class="flex justify-between gap-3 py-2 border-b border-gray-100 text-[13px] last:border-b-0">
                    <span class="text-gray-400 whitespace-nowrap">${label}</span>
                    <span class="text-gray-800 font-medium text-right">${value}</span>
                </div>
            `;
        }

        function chk2dDetailRowHighlight(label, value) {
            return `
        <div class="flex justify-between items-center gap-3 py-2.5 px-3 my-1 rounded-lg
                    bg-slate-50 border border-slate-300 text-[13px]">
            <span class="text-slate-600 font-medium flex items-center gap-1.5">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                ${label}
            </span>
            <span class="text-slate-900 font-semibold text-right">${value}</span>
        </div>
    `;
        }

        // Shows the current review status (+ remarks, if any) for one file.
        function chk2dFileReviewSummary(label, reviewStatus, remarks) {
            const remarksHtml = remarks
                ? `<p class="text-xs text-red-700 mt-1 max-w-[220px] ml-auto text-right">${chk2dEscapeHtml(remarks)}</p>`
                : '';
            return `
                <div class="flex items-start justify-between gap-3 py-2 border-b border-gray-100 text-[13px] last:border-b-0">
                    <span class="text-gray-400 whitespace-nowrap pt-0.5">${chk2dEscapeHtml(label)}</span>
                    <div class="text-right">
                        ${chk2dReviewBadge(reviewStatus)}
                        ${remarksHtml}
                    </div>
                </div>
            `;
        }

        // One decision block inside the footer: a "View File" link, Approve /
        // Send for Revision buttons, and a remarks box that only shows up once
        // "Send for Revision" is picked. If `alreadyApproved`, the block renders
        // as a locked read-only summary.
        function chk2dDecisionRow(slot, label, path, alreadyApproved) {
            const pdfLink = path
                ? `<a href="${chk2dEscapeHtml(path)}" target="_blank" rel="noopener"
                       class="text-amber-700 hover:underline text-xs font-medium whitespace-nowrap">View File</a>`
                : `<span class="text-gray-300 text-xs">No file</span>`;

            if (alreadyApproved) {
                return `
                    <div class="border border-gray-200 rounded-lg p-2.5 bg-green-50/40" data-decision-slot="${slot}">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-xs font-semibold text-gray-600">${label}</p>
                            <div class="flex items-center gap-2">
                                ${pdfLink}
                                ${chk2dReviewBadge('Approved')}
                            </div>
                        </div>
                    </div>
                `;
            }

            return `
                <div class="border border-gray-200 rounded-lg p-2.5" data-decision-slot="${slot}">
                    <div class="flex items-center justify-between mb-2 gap-2">
                        <p class="text-xs font-semibold text-gray-600">${label}</p>
                        ${pdfLink}
                    </div>
                    <div class="flex items-center gap-2 mb-2">
                        <button type="button" data-decision-btn="approve" onclick="chk2dSetDecision('${slot}', 'Approved')"
                            class="chk2d-decision-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50 transition-colors whitespace-nowrap">
                            Approve
                        </button>
                        <button type="button" data-decision-btn="revise" onclick="chk2dSetDecision('${slot}', 'For Revision')"
                            class="chk2d-decision-btn px-3 py-1.5 text-xs font-medium rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50 transition-colors whitespace-nowrap">
                            Send for Revision
                        </button>
                    </div>
                    <textarea data-decision-remarks placeholder="What needs to be revised?" rows="2"
                        oninput="chk2dSetRemarks('${slot}', this.value)"
                        class="hidden w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-500"></textarea>
                </div>
            `;
        }

        function chk2dSetDecision(slot, decision) {
            chk2dDecisions[slot].decision = decision;
            if (decision === 'Approved') chk2dDecisions[slot].remarks = '';

            const container = document.querySelector(`[data-decision-slot="${slot}"]`);
            const approveBtn = container.querySelector('[data-decision-btn="approve"]');
            const reviseBtn = container.querySelector('[data-decision-btn="revise"]');
            const textarea = container.querySelector('[data-decision-remarks]');

            const approved = decision === 'Approved';
            approveBtn.classList.toggle('bg-green-700', approved);
            approveBtn.classList.toggle('text-white', approved);
            approveBtn.classList.toggle('border-green-700', approved);
            approveBtn.classList.toggle('hover:bg-green-800', approved);
            approveBtn.classList.toggle('text-gray-600', !approved);
            approveBtn.classList.toggle('border-gray-300', !approved);
            approveBtn.classList.toggle('hover:bg-gray-50', !approved);

            const revise = decision === 'For Revision';
            reviseBtn.classList.toggle('bg-red-700', revise);
            reviseBtn.classList.toggle('text-white', revise);
            reviseBtn.classList.toggle('border-red-700', revise);
            reviseBtn.classList.toggle('hover:bg-red-800', revise);
            reviseBtn.classList.toggle('text-gray-600', !revise);
            reviseBtn.classList.toggle('border-gray-300', !revise);
            reviseBtn.classList.toggle('hover:bg-gray-50', !revise);

            textarea.classList.toggle('hidden', !revise);
            if (!revise) textarea.value = '';

            chk2dUpdateSubmitState();
        }

        function chk2dSetRemarks(slot, value) {
            chk2dDecisions[slot].remarks = value;
            chk2dUpdateSubmitState();
        }

        // Iterates over whatever slots exist in chk2dDecisions right now
        // (2, or 3 when 3D is part of this review).
        function chk2dUpdateSubmitState() {
            const btn = document.getElementById('chk2dSubmitReviewBtn');
            if (!btn) return;
            const slots = Object.keys(chk2dDecisions);
            const ready = slots.every(slot => {
                const d = chk2dDecisions[slot];
                if (!d.decision) return false;
                if (d.decision === 'For Revision' && d.remarks.trim() === '') return false;
                return true;
            });
            btn.disabled = !ready;
        }

        function chk2dRenderFooter(record) {
            const footer = document.getElementById('chk2dModalFooter');

            // Sequential 3D-only review — 2D & Quotation are already Approved
            // and locked; only the 3D file needs a decision here.
            if (record.review_target === '3d_only') {
                chk2dDecisions = {
                    design_3d: { decision: null, remarks: '' },
                };
                footer.innerHTML = `
                    <div class="space-y-2.5 mb-2.5">
                        ${chk2dDecisionRow('design_3d', '3D File', record.design_3d_path, false)}
                    </div>
                    <div class="flex items-center justify-end gap-2">
                        <button type="button" id="chk2dSubmitReviewBtn" onclick="chk2dSubmitReview3d()" disabled
                            class="px-3.5 py-1.5 text-xs font-medium text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors">
                            Submit Review
                        </button>
                    </div>
                    <p class="text-[11px] text-gray-400 mt-2">Sending the 3D file back for revision will also reopen the 2D file for rework, since 3D is derived from it.</p>
                `;
                chk2dUpdateSubmitState();
                return;
            }

            if (record.review_target === 'none') {
                const reviewedLine = record.reviewed_at
                    ? `<p class="text-[11px] text-gray-400">Reviewed ${chk2dFormatDateTimeLong(record.reviewed_at)}</p>`
                    : '';
                footer.innerHTML = `
                    <div class="flex items-center justify-between gap-3">
                        <div>${chk2dStatusBadgeWithRow(record)}${reviewedLine}</div>
                        <button type="button" onclick="chk2dClosePanel()"
                            class="px-3.5 py-1.5 text-xs font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                            Close
                        </button>
                    </div>
                `;
                return;
            }

            // 'main' or 'main_with_3d' — standard 2D + Quotation review,
            // optionally with a bundled 3D decision too.

            // Files already Approved in a prior partial-review pass are
            // pre-filled and locked.
            const design2dLocked = record.design_2d_review_status === 'Approved';
            const quotationLocked = record.quotation_review_status === 'Approved';

            chk2dDecisions = {
                design_2d: design2dLocked
                    ? { decision: 'Approved', remarks: '' }
                    : { decision: null, remarks: '' },
                quotation: quotationLocked
                    ? { decision: 'Approved', remarks: '' }
                    : { decision: null, remarks: '' },
            };
            if (record.include_3d) {
                chk2dDecisions.design_3d = { decision: null, remarks: '' };
            }

            footer.innerHTML = `
                <div class="space-y-2.5 mb-2.5">
                    ${chk2dDecisionRow('design_2d', '2D File', record.design_2d_path, design2dLocked)}
                    ${chk2dDecisionRow('quotation', 'Quotation File', record.quotation_path, quotationLocked)}
                    ${record.include_3d ? chk2dDecisionRow('design_3d', '3D File', record.design_3d_path, false) : ''}
                </div>
                <div class="flex items-center justify-end gap-2">
                    <button type="button" id="chk2dSubmitReviewBtn" onclick="chk2dSubmitReview()" disabled
                        class="px-3.5 py-1.5 text-xs font-medium text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors">
                        Submit Review
                    </button>
                </div>
                ${record.include_3d ? `<p class="text-[11px] text-gray-400 mt-2">Sending the 3D file back for revision will also send the 2D file back, since 3D is derived from it.</p>` : ''}
            `;

            chk2dUpdateSubmitState();
        }

        async function chk2dOpenPanel(stage, id) {
            const overlay = document.getElementById('chk2dOverlay');
            const panel = document.getElementById('chk2dPanel');
            const body = document.getElementById('chk2dModalBody');
            const footer = document.getElementById('chk2dModalFooter');
            chk2dCurrentId = id;
            chk2dCurrentStage = stage;

            // Mark viewed immediately and refresh the table so the unread dot
            // disappears the moment the record is opened.
            chk2dMarkViewed(stage, id);
            if (chk2dLastRows.length) chk2dRenderRows(chk2dLastRows);

            document.getElementById('chk2dModalHeading').textContent = `${stage} Submission Review`;
            document.getElementById('chk2dModalControlNo').textContent = 'Loading…';
            body.innerHTML = `
                <div class="space-y-2 py-1">
                    ${Array.from({ length: 6 }).map(() => `<div class="h-4 rounded bg-gray-100 animate-pulse"></div>`).join('')}
                </div>
            `;
            footer.innerHTML = '';

            overlay.classList.remove('hidden');
            requestAnimationFrame(() => panel.classList.remove('translate-x-full'));

            try {
                const res = await fetch(`${CHK2D_AJAX_URL}?action=detail&id=${id}&stage=${encodeURIComponent(stage)}`);
                const data = await res.json();

                if (!data.success) {
                    body.innerHTML = `<p class="text-sm text-red-500 py-6 text-center">${chk2dEscapeHtml(data.message || 'Record not found.')}</p>`;
                    return;
                }

                const r = data.record;
                document.getElementById('chk2dModalControlNo').textContent = r.control_no;

                const showsThreeD = r.include_3d || r.design_3d_stage !== 'Locked';

                body.innerHTML = [
                    chk2dDetailRow('Client', chk2dEscapeHtml(r.client_name)),
                    chk2dDetailRow('Contact Number', chk2dEscapeHtml(r.contact_number)),
                    chk2dDetailRow('Project Type', chk2dEscapeHtml(r.project_type) || '—'),
                    chk2dDetailRow('Contract Amount', chk2dFormatCurrency(r.contract_amount)),
                    chk2dDetailRowHighlight('Target Completion Date', chk2dFormatDate(r.target_completion_date)),
                    chk2dDetailRow('2D File', r.design_2d_path
                        ? `<a href="${chk2dEscapeHtml(r.design_2d_path)}" target="_blank" rel="noopener" class="text-amber-700 hover:underline">View File</a> <span class="text-gray-400 font-normal">(${chk2dEscapeHtml(r.design_2d_uploader_name)}, ${chk2dEscapeHtml(r.design_2d_uploaded_role)})</span>`
                        : '—'),
                    chk2dDetailRow('Quotation File', r.quotation_path
                        ? `<a href="${chk2dEscapeHtml(r.quotation_path)}" target="_blank" rel="noopener" class="text-amber-700 hover:underline">View File</a> <span class="text-gray-400 font-normal">(${chk2dEscapeHtml(r.quotation_uploader_name)}, ${chk2dEscapeHtml(r.quotation_uploaded_role)})</span>`
                        : '—'),
                    showsThreeD ? chk2dDetailRow('3D File', r.design_3d_path
                        ? `<a href="${chk2dEscapeHtml(r.design_3d_path)}" target="_blank" rel="noopener" class="text-amber-700 hover:underline">View File</a> <span class="text-gray-400 font-normal">(${chk2dEscapeHtml(r.design_3d_uploader_name)}, ${chk2dEscapeHtml(r.design_3d_uploaded_role)})</span>`
                        : '—') : '',
                    chk2dDetailRow('Submitted', chk2dFormatDateTimeLong(r.submitted_at)),
                    chk2dFileReviewSummary('2D Review', r.design_2d_review_status, r.design_2d_remarks),
                    chk2dFileReviewSummary('Quotation Review', r.quotation_review_status, r.quotation_remarks),
                    showsThreeD ? chk2dFileReviewSummary('3D Review', r.design_3d_review_status, r.design_3d_remarks) : '',
                ].join('');

                chk2dRenderFooter(r);

            } catch (e) {
                console.error('chk2dOpenPanel:', e);
                body.innerHTML = `<p class="text-sm text-red-500 py-6 text-center">Connection error. Please try again.</p>`;
            }
        }

        function chk2dClosePanel() {
            const overlay = document.getElementById('chk2dOverlay');
            const panel = document.getElementById('chk2dPanel');
            panel.classList.add('translate-x-full');
            setTimeout(() => overlay.classList.add('hidden'), 300);
            chk2dCurrentId = null;
            chk2dCurrentStage = null;
            if (chk2dLastRows.length) chk2dRenderRows(chk2dLastRows);
        }

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') chk2dClosePanel();
        });

        // Sends both/all file decisions together — the single "submit" action
        // for the main review (2D + Quotation, optionally + bundled 3D).
        async function chk2dSubmitReview() {
            if (!chk2dCurrentId || !chk2dCurrentStage) return;

            const formData = new FormData();
            formData.append('action', 'review');
            formData.append('id', chk2dCurrentId);
            formData.append('stage', chk2dCurrentStage);
            formData.append('design_2d_decision', chk2dDecisions.design_2d.decision);
            formData.append('design_2d_remarks', chk2dDecisions.design_2d.remarks.trim());
            formData.append('quotation_decision', chk2dDecisions.quotation.decision);
            formData.append('quotation_remarks', chk2dDecisions.quotation.remarks.trim());
            if (chk2dDecisions.design_3d) {
                formData.append('design_3d_decision', chk2dDecisions.design_3d.decision);
                formData.append('design_3d_remarks', chk2dDecisions.design_3d.remarks.trim());
            }

            try {
                const res = await fetch(CHK2D_AJAX_URL, { method: 'POST', body: formData });
                const data = await res.json();

                if (!data.success) {
                    crmShowToast(data.message || 'Something went wrong.', 'error');
                    return;
                }

                crmShowToast(data.message || 'Review saved.');
                chk2dClosePanel();
                chk2dLastSignature = '';
                chk2dFetchList();
            } catch (e) {
                console.error('chk2dSubmitReview:', e);
                crmShowToast('Connection error. Please try again.', 'error');
            }
        }

        // Standalone submit for the sequential "3D only" review.
        async function chk2dSubmitReview3d() {
            if (!chk2dCurrentId || !chk2dCurrentStage) return;

            const formData = new FormData();
            formData.append('action', 'review_3d');
            formData.append('id', chk2dCurrentId);
            formData.append('stage', chk2dCurrentStage);
            formData.append('design_3d_decision', chk2dDecisions.design_3d.decision);
            formData.append('design_3d_remarks', chk2dDecisions.design_3d.remarks.trim());

            try {
                const res = await fetch(CHK2D_AJAX_URL, { method: 'POST', body: formData });
                const data = await res.json();

                if (!data.success) {
                    crmShowToast(data.message || 'Something went wrong.', 'error');
                    return;
                }

                crmShowToast(data.message || 'Review saved.');
                chk2dClosePanel();
                chk2dLastSignature = '';
                chk2dFetchList();
            } catch (e) {
                console.error('chk2dSubmitReview3d:', e);
                crmShowToast('Connection error. Please try again.', 'error');
            }
        }
    </script>
</body>

</html>