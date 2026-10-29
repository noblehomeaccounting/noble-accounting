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
        #chk2dTableWrap,
        #chk2dModalBody {
            scrollbar-width: thin;
            scrollbar-color: #d1d5db transparent;
        }
        /* napiling status card */
        .chk2d-tab[data-active="1"] { border-color: #b45309; background: #fffbeb; box-shadow: 0 0 0 1px #b45309; }
    </style>
</head>

<body class="bg-slate-100">
    <main id="main-content"
        class="md:ml-56 min-h-screen pt-20 md:pt-8 p-4 md:p-8 overflow-x-hidden transition-all duration-300">

        <div class="max-w-6xl mx-auto">

            <!-- ===================== HEADER ===================== -->
            <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
                <div>
                    <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase mb-0.5">CRM Management</p>
                    <h1 class="text-gray-900 text-xl font-semibold flex items-center gap-2 flex-wrap">
                        2D &amp; Quotation Approval
                        <span class="inline-flex items-center gap-1 px-2 py-[3px] rounded-full text-[10px] leading-none font-semibold uppercase tracking-wider border bg-green-50 text-green-700 border-green-200">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Final
                        </span>
                    </h1>
                    <p class="text-gray-400 text-xs mt-0.5">Check the final 2D, quotation and 3D files, then approve them or send them back for revision.</p>
                </div>
                <span class="inline-flex items-center gap-1.5 text-[11px] text-gray-400">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span> Updates automatically
                </span>
            </div>

            <!-- ===================== STATUS CARDS (pindutin para i-filter) ===================== -->
            <div id="chk2dTabs" class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
                <?php
                $tabs = [
                    ['Waiting for Approval', 'Queuing',      'fa-hourglass-half', 'bg-amber-50 text-amber-700'],
                    ['Approved',             'Approved',     'fa-circle-check',   'bg-green-50 text-green-600'],
                    ['For Revision',         'For Revision', 'fa-rotate-left',    'bg-red-50 text-red-600'],
                    ['',                     'All',          'fa-layer-group',    'bg-slate-100 text-slate-600'],
                ];
                foreach ($tabs as [$val, $label, $icon, $tone]): ?>
                    <button type="button" data-status="<?= htmlspecialchars($val) ?>"
                        class="chk2d-tab bg-white border border-gray-200 rounded-xl px-4 py-3.5 flex items-center gap-3 text-left hover:border-gray-300 transition-colors">
                        <span class="w-10 h-10 rounded-xl <?= $tone ?> flex items-center justify-center shrink-0"><i class="fa-solid <?= $icon ?>"></i></span>
                        <span class="min-w-0">
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-400"><?= $label ?></span>
                            <span class="chk2d-tab-count block text-xl font-bold text-gray-900 leading-tight tabular-nums">0</span>
                        </span>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- ===================== SEARCH ===================== -->
            <div class="flex items-center justify-between gap-2 mb-3">
                <p id="chk2dTabLabel" class="text-xs font-semibold text-gray-700"></p>
                <div class="relative w-full sm:w-72">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[11px]"></i>
                    <input id="chk2dSearch" type="text" placeholder="Search control no., client, contact"
                        class="w-full pl-8 pr-7 py-2 text-xs bg-white border border-gray-200 rounded-lg focus:outline-none focus:border-amber-600 transition-colors">
                    <button type="button" id="chk2dSearchClear"
                        class="hidden absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-500 text-base leading-none w-4 h-4">&times;</button>
                </div>
            </div>

            <!-- ===================== LIST ===================== -->
            <div id="chk2dTableWrap">
                <div id="chk2dTbody" class="space-y-2"></div>
            </div>

            <p id="chk2dCount" class="text-[11px] text-gray-400 mt-2"></p>
        </div>

        <!-- Right-side review panel -->
        <div id="chk2dOverlay" class="fixed inset-0 bg-black/30 hidden z-40" onclick="chk2dClosePanel()"></div>

        <div id="chk2dPanel" class="fixed top-0 right-0 h-full w-full max-w-lg bg-white shadow-2xl z-50 flex flex-col
                   translate-x-full transition-transform duration-300 ease-out">
            <div class="px-4 py-3 border-b border-gray-100 flex items-start justify-between shrink-0">
                <div class="min-w-0">
                    <p class="text-[10px] text-amber-700 font-semibold tracking-[0.15em] uppercase mb-0.5">Final
                        Submission Review</p>
                    <h3 id="chk2dModalControlNo" class="text-gray-900 font-mono font-semibold text-sm">—</h3>
                    <div id="chk2dModalStatus" class="mt-1.5 flex flex-wrap items-center gap-1"></div>
                </div>
                <button type="button" onclick="chk2dClosePanel()"
                    class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
            </div>
            <div id="chk2dModalBody" class="px-4 py-3 overflow-y-auto flex-1 bg-slate-50/60"></div>
            <div id="chk2dModalFooter" class="px-4 py-3 bg-white border-t border-gray-200 shrink-0"></div>
        </div>

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
            toast.className = `pointer-events-auto flex items-start gap-2.5 border rounded-lg shadow-lg px-4 py-3 text-sm ${palette.wrap}
                translate-x-6 opacity-0 scale-95 transition-all duration-300 ease-out`;
            toast.innerHTML = `
                <span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold ${palette.icon}">${palette.symbol}</span>
                <span class="flex-1 leading-relaxed">${message}</span>
                <button type="button" class="shrink-0 text-current opacity-50 hover:opacity-100 text-base leading-none" aria-label="Close">&times;</button>`;
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
        const CHK2D_VIEWED_KEY = 'chk2dViewedIds';

        let chk2dSearchTerm = '';
        let chk2dStatusFilter = '';
        let chk2dLastSignature = '';
        let chk2dPollTimer = null;
        let chk2dSearchDebounce = null;
        let chk2dCurrentId = null;
        let chk2dLastRows = [];
        let chk2dDecisions = {};

        // ── helpers ──
        function chk2dEscapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }

        function chk2dGetViewedIds() {
            try {
                const raw = localStorage.getItem(CHK2D_VIEWED_KEY);
                return raw ? new Set(JSON.parse(raw)) : new Set();
            } catch (e) { return new Set(); }
        }

        function chk2dMarkViewed(id) {
            const viewed = chk2dGetViewedIds();
            if (viewed.has(id)) return;
            viewed.add(id);
            try { localStorage.setItem(CHK2D_VIEWED_KEY, JSON.stringify([...viewed])); } catch (e) { }
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
            return dt.toLocaleString('en-PH', { year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true });
        }

        function chk2dFormatCurrency(value) {
            const num = Number(value);
            if (!value || isNaN(num)) return '<span class="text-gray-300">—</span>';
            return '₱' + num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function chk2dStatusBadge(status, compact = false) {
            const map = {
                'Approved': 'bg-green-50 text-green-700 border-green-200',
                'For Revision': 'bg-red-50 text-red-700 border-red-200',
                'Waiting for Approval': 'bg-amber-50 text-amber-700 border-amber-200',
            };
            const dotMap = { 'Approved': 'bg-green-500', 'For Revision': 'bg-red-500', 'Waiting for Approval': 'bg-amber-500' };
            const cls = map[status] || 'bg-gray-50 text-gray-600 border-gray-200';
            const dot = dotMap[status] || 'bg-gray-400';
            const size = compact ? 'px-2 py-0.5 text-[10px]' : 'px-2.5 py-1 text-[11px]';
            return `<span class="inline-flex items-center gap-1.5 ${size} rounded-full font-semibold border whitespace-nowrap ${cls}">
                <span class="w-1.5 h-1.5 rounded-full shrink-0 ${dot}"></span>${chk2dEscapeHtml(status)}</span>`;
        }

        // Main status badge plus the small "3D Waiting / 3D Revision / Late" flags.
        function chk2dStatusBadgeWithRow(row) {
            let out = chk2dStatusBadge(row.status, true);
            if (row.status === 'Approved' && row.design_3d_stage === 'Waiting for Approval') {
                out += `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border border-amber-200 bg-amber-50 text-amber-700 whitespace-nowrap">3D Waiting</span>`;
            }
            if (row.status === 'Approved' && row.design_3d_stage === 'For Revision') {
                out += `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border border-red-200 bg-red-50 text-red-700 whitespace-nowrap">3D Revision</span>`;
            }
            if (row.is_late) {
                out += `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border border-red-200 bg-white text-red-700 whitespace-nowrap">Late</span>`;
            }
            return out;
        }

        function chk2dReviewBadge(status) {
            const map = {
                'Approved': 'bg-green-50 text-green-700 border-green-200',
                'For Revision': 'bg-red-50 text-red-700 border-red-200',
                'Pending': 'bg-gray-50 text-gray-500 border-gray-200',
            };
            const cls = map[status] || map['Pending'];
            return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border whitespace-nowrap ${cls}">${chk2dEscapeHtml(status || 'Pending')}</span>`;
        }

        // One compact chip per file (replaces the three wide file columns).
        const CHK2D_FILE_ICON = { '2D': 'fa-pen-ruler', 'Quote': 'fa-file-invoice-dollar', '3D': 'fa-cube' };
        function chk2dFileChip(label, path, uploaderName, uploaderRole, emptyTitle = 'No file') {
            const icon = CHK2D_FILE_ICON[label] || 'fa-file-lines';
            if (!path) {
                return `<span title="${chk2dEscapeHtml(emptyTitle)}" class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-[11px] font-semibold border border-dashed border-gray-200 text-gray-300">
                    <i class="fa-solid ${icon} text-[10px]"></i>${label}</span>`;
            }
            const who = uploaderName ? `Uploaded by ${uploaderName}${uploaderRole ? ' (' + uploaderRole + ')' : ''}` : 'View file';
            return `<a href="${chk2dEscapeHtml(path)}" target="_blank" rel="noopener" title="${chk2dEscapeHtml(who)}"
                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg text-[11px] font-semibold border border-gray-200 bg-white text-gray-700 hover:border-amber-400 hover:text-amber-800 hover:bg-amber-50 transition-colors">
                <i class="fa-solid ${icon} text-[10px] text-amber-600"></i>${label}</a>`;
        }

        function chk2dFilesCell(row) {
            const show3d = row.include_3d || row.design_3d_stage !== 'Locked';
            return `<div class="flex flex-wrap items-center gap-1.5">
                ${chk2dFileChip('2D', row.design_2d_path, row.design_2d_uploader_name, row.design_2d_uploaded_role)}
                ${chk2dFileChip('Quote', row.quotation_path, row.quotation_uploader_name, row.quotation_uploaded_role)}
                ${chk2dFileChip('3D', show3d ? row.design_3d_path : null, row.design_3d_uploader_name, row.design_3d_uploaded_role, show3d ? 'No file' : 'Not yet')}
            </div>`;
        }

        // ── tabs ──
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
                btn.dataset.active = btn.dataset.status === chk2dStatusFilter ? '1' : '0';
            });
            const names = { '': 'All submissions', 'Waiting for Approval': 'Queuing', 'Approved': 'Approved', 'For Revision': 'For revision' };
            const el = document.getElementById('chk2dTabLabel');
            if (el) el.textContent = names[chk2dStatusFilter] ?? '';
        }

        async function chk2dFetchCounts() {
            try {
                const res = await fetch(`${CHK2D_AJAX_URL}?action=list&q=&status=`);
                const data = await res.json();
                if (!data.success) return;

                const counts = { '': data.rows.length, 'Waiting for Approval': 0, 'Approved': 0, 'For Revision': 0 };
                data.rows.forEach(row => { if (counts[row.status] !== undefined) counts[row.status]++; });

                document.querySelectorAll('.chk2d-tab').forEach(btn => {
                    const el = btn.querySelector('.chk2d-tab-count');
                    if (el) el.textContent = counts[btn.dataset.status] ?? 0;
                });
            } catch (e) { console.error('chk2dFetchCounts:', e); }
        }

        // ── table ──
        function chk2dSkeletonRows(count = 4) {
            document.getElementById('chk2dTbody').innerHTML = Array.from({ length: count }).map(() => `
                <div class="bg-white border border-gray-200 rounded-xl px-5 py-4 flex items-center gap-4">
                    <div class="flex-1 space-y-2"><div class="h-3 w-32 rounded bg-gray-100 animate-pulse"></div><div class="h-3 w-48 rounded bg-gray-100 animate-pulse"></div></div>
                    <div class="h-8 w-24 rounded-lg bg-gray-100 animate-pulse"></div>
                </div>`).join('');
        }

        function chk2dEmptyState() {
            const message = chk2dSearchTerm ? `No submissions match "${chk2dEscapeHtml(chk2dSearchTerm)}".`
                : (chk2dStatusFilter === 'Waiting for Approval' ? 'Nothing in the queue. New final submissions will show up here.' : 'No submissions here.');
            return `<div class="bg-white border-2 border-dashed border-gray-200 rounded-xl flex flex-col items-center justify-center gap-2 py-14 text-center">
                    <i class="fa-regular fa-folder-open text-3xl text-gray-300"></i>
                    <p class="text-gray-500 text-xs font-medium">${message}</p>
                </div>`;
        }

        function chk2dActionButton(row) {
            if (row.review_target && row.review_target !== 'none') {
                return `<button type="button" onclick="chk2dOpenPanel(${row.id})"
                    class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 shadow-sm transition-colors whitespace-nowrap">
                    <i class="fa-solid fa-clipboard-check"></i> Review</button>`;
            }
            return `<button type="button" onclick="chk2dOpenPanel(${row.id})"
                class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:border-gray-300 hover:bg-gray-50 transition-colors whitespace-nowrap">
                View <i class="fa-solid fa-chevron-right text-[9px]"></i></button>`;
        }

        // kulay ng gilid ng card ayon sa status
        const CHK2D_BAR = { 'Approved': 'bg-green-500', 'For Revision': 'bg-red-500', 'Waiting for Approval': 'bg-amber-400' };

        function chk2dRenderRows(rows) {
            const tbody = document.getElementById('chk2dTbody');
            chk2dLastRows = rows;

            if (rows.length === 0) { tbody.innerHTML = chk2dEmptyState(); return; }

            const viewed = chk2dGetViewedIds();

            tbody.innerHTML = rows.map(row => {
                const isViewed = viewed.has(row.id);
                const isActive = chk2dCurrentId === row.id;
                const bar = CHK2D_BAR[row.status] || 'bg-gray-300';
                const ring = isActive ? 'border-amber-500 ring-2 ring-amber-200' : (isViewed ? 'border-gray-200 hover:border-gray-300' : 'border-amber-200 hover:border-amber-300');
                const newTag = isViewed ? '' : `<span class="inline-flex items-center gap-1 px-1.5 py-px rounded text-[9.5px] font-bold uppercase tracking-wide bg-amber-500 text-white">New</span>`;

                return `
                <article class="relative bg-white border ${ring} rounded-xl overflow-hidden cursor-pointer transition-all hover:shadow-sm" onclick="chk2dOpenPanel(${row.id})">
                    <span class="absolute left-0 top-0 bottom-0 w-1 ${bar}"></span>
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-3 pl-5 pr-4 py-4">
                        <div class="min-w-0 flex-1 basis-[200px]">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-mono text-[11.5px] font-bold text-amber-700 whitespace-nowrap">${chk2dEscapeHtml(row.control_no)}</span>${newTag}
                            </div>
                            <p class="text-gray-900 text-sm font-semibold mt-0.5 truncate">${chk2dEscapeHtml(row.client_name)}</p>
                            <p class="text-gray-400 text-[11px] mt-0.5"><i class="fa-regular fa-calendar mr-1"></i>Submitted ${chk2dFormatDate(row.submitted_at)}</p>
                        </div>
                        <div class="basis-[210px]" onclick="event.stopPropagation()">
                            <p class="text-[9.5px] font-bold uppercase tracking-wider text-gray-400 mb-1">Files</p>
                            ${chk2dFilesCell(row)}
                        </div>
                        <div class="basis-[150px]">
                            <p class="text-[9.5px] font-bold uppercase tracking-wider text-gray-400">Contract</p>
                            <p class="text-gray-900 text-sm font-bold tabular-nums mt-0.5">${chk2dFormatCurrency(row.contract_amount)}</p>
                        </div>
                        <div class="basis-[120px]">
                            <p class="text-[9.5px] font-bold uppercase tracking-wider text-gray-400 mb-1">Status</p>
                            <div class="flex flex-wrap items-center gap-1">${chk2dStatusBadgeWithRow(row)}</div>
                        </div>
                        <div class="ml-auto shrink-0" onclick="event.stopPropagation()">${chk2dActionButton(row)}</div>
                    </div>
                </article>`;
            }).join('');
        }

        async function chk2dFetchList({ silent = false } = {}) {
            if (!silent) chk2dSkeletonRows();
            try {
                const url = `${CHK2D_AJAX_URL}?action=list&q=${encodeURIComponent(chk2dSearchTerm)}&status=${encodeURIComponent(chk2dStatusFilter)}`;
                const res = await fetch(url);
                const data = await res.json();

                if (!data.success) {
                    if (!silent) crmShowToast(data.message || 'Failed to load submissions.', 'error');
                    return;
                }

                const signature = JSON.stringify(data.rows.map(r => `${r.id}:${r.status}:${r.design_3d_stage}`)) + chk2dStatusFilter;
                if (signature !== chk2dLastSignature) {
                    chk2dRenderRows(data.rows);
                    chk2dLastSignature = signature;
                }

                document.getElementById('chk2dCount').textContent = `${data.count} submission${data.count === 1 ? '' : 's'} found`;
                chk2dFetchCounts();
            } catch (e) {
                console.error('chk2dFetchList:', e);
                if (!silent) crmShowToast('Connection error while fetching submissions.', 'error');
            }
        }

        const chk2dRtLive = () => !!(window.NH_RT && window.NH_RT.enabled && window.NH_RT.isLive());

        function chk2dStartPolling() {
            if (chk2dPollTimer) clearInterval(chk2dPollTimer);
            // Live → 60s fallback lang; hindi Live → dating bilis
            chk2dPollTimer = setInterval(() => chk2dFetchList({ silent: true }), chk2dRtLive() ? 60000 : CHK2D_POLL_INTERVAL_MS);
        }

        // ─── Realtime (koneksyon ng sidebar: window.NH_RT) ───
        // Bagong Final submission, review ng ibang Superadmin, reset ng Sales → kunin agad ang listahan.
        // Hindi ginagalaw ang bukas na review panel (listahan lang ang nire-refresh).
        (function chk2dHookRealtime() {
            const hook = () => {
                if (!window.NH_RT || !window.NH_RT.enabled || chk2dHookRealtime.done) return;
                chk2dHookRealtime.done = true;
                let t = null;
                window.NH_RT.on('*', 'changed', (payload) => {
                    const type = String(payload?.type || '');
                    if (type && !type.startsWith('crm')) return; // budget/voucher — hindi para dito
                    clearTimeout(t);
                    t = setTimeout(() => { if (!document.hidden) chk2dFetchList({ silent: true }); }, 400);
                });
                window.NH_RT.onLive(live => {
                    if (!document.hidden && chk2dPollTimer) chk2dStartPolling();
                    if (live && chk2dLastRows.length) chk2dFetchList({ silent: true });
                });
            };
            hook();
            if (!chk2dHookRealtime.done) document.addEventListener('DOMContentLoaded', hook);
        })();

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

        // ── review panel: body (sections) ──
        function chk2dSection(title, rowsHtml) {
            return `<div class="bg-white border border-gray-200 rounded-lg overflow-hidden mb-3">
                <p class="px-3 py-1.5 bg-gray-50 border-b border-gray-200 text-[10px] font-bold uppercase tracking-wide text-gray-500">${title}</p>
                <div class="px-3 divide-y divide-gray-100">${rowsHtml}</div>
            </div>`;
        }

        function chk2dDetailRow(label, value) {
            return `<div class="flex justify-between gap-3 py-1.5 text-xs">
                <span class="text-gray-400 whitespace-nowrap">${label}</span>
                <span class="text-gray-800 font-medium text-right">${value}</span></div>`;
        }

        function chk2dDetailRowHighlight(label, value) {
            return `<div class="flex justify-between items-center gap-3 py-1.5 -mx-3 px-3 bg-amber-50/70 text-xs">
                <span class="text-amber-800 font-medium">${label}</span>
                <span class="text-amber-900 font-semibold text-right">${value}</span></div>`;
        }

        // File link + its review status together, so each file reads as one row.
        function chk2dFileRow(label, path, name, role, reviewStatus, remarks) {
            const file = path
                ? `<a href="${chk2dEscapeHtml(path)}" target="_blank" rel="noopener" class="text-amber-700 hover:underline font-medium">View File</a> <span class="text-gray-400">${chk2dEscapeHtml(name)}${role ? ', ' + chk2dEscapeHtml(role) : ''}</span>`
                : '<span class="text-gray-300">—</span>';
            const remarksHtml = remarks
                ? `<p class="mt-1.5 text-[11px] text-red-700 bg-red-50 border border-red-100 rounded px-2 py-1">${chk2dEscapeHtml(remarks)}</p>` : '';
            return `<div class="py-2 text-xs">
                <div class="flex items-center justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-gray-500 font-medium">${label}</p>
                        <p class="mt-0.5 truncate">${file}</p>
                    </div>
                    ${chk2dReviewBadge(reviewStatus)}
                </div>
                ${remarksHtml}
            </div>`;
        }

        // ── review panel: footer (decisions) ──
        function chk2dDecisionRow(slot, label, path, alreadyApproved) {
            const pdfLink = path
                ? `<a href="${chk2dEscapeHtml(path)}" target="_blank" rel="noopener" class="text-amber-700 hover:underline text-[11px] font-medium whitespace-nowrap">View</a>`
                : `<span class="text-gray-300 text-[11px]">No file</span>`;

            if (alreadyApproved) {
                return `<div class="flex items-center justify-between gap-2 px-3 py-2 border border-green-200 bg-green-50/50 rounded-lg" data-decision-slot="${slot}">
                    <div class="flex items-center gap-2">
                        <p class="text-xs font-semibold text-gray-700">${label}</p>${pdfLink}
                    </div>
                    ${chk2dReviewBadge('Approved')}
                </div>`;
            }

            return `<div class="px-3 py-2 border border-gray-200 rounded-lg bg-white" data-decision-slot="${slot}">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2 min-w-0">
                        <p class="text-xs font-semibold text-gray-700">${label}</p>${pdfLink}
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <button type="button" data-decision-btn="approve" onclick="chk2dSetDecision('${slot}', 'Approved')"
                            class="px-2.5 py-1 text-[11px] font-medium rounded-md border border-gray-300 text-gray-600 hover:bg-gray-50 transition-colors whitespace-nowrap">Approve</button>
                        <button type="button" data-decision-btn="revise" onclick="chk2dSetDecision('${slot}', 'For Revision')" title="Send for Revision"
                            class="px-2.5 py-1 text-[11px] font-medium rounded-md border border-gray-300 text-gray-600 hover:bg-gray-50 transition-colors whitespace-nowrap">Revision</button>
                    </div>
                </div>
                <textarea data-decision-remarks placeholder="What needs to be revised?" rows="2"
                    oninput="chk2dSetRemarks('${slot}', this.value)"
                    class="hidden mt-2 w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-500"></textarea>
            </div>`;
        }

        function chk2dSetDecision(slot, decision) {
            chk2dDecisions[slot].decision = decision;
            if (decision === 'Approved') chk2dDecisions[slot].remarks = '';

            const c = document.querySelector(`[data-decision-slot="${slot}"]`);
            const approveBtn = c.querySelector('[data-decision-btn="approve"]');
            const reviseBtn = c.querySelector('[data-decision-btn="revise"]');
            const textarea = c.querySelector('[data-decision-remarks]');

            const approved = decision === 'Approved';
            ['bg-green-700', 'text-white', 'border-green-700', 'hover:bg-green-800'].forEach(k => approveBtn.classList.toggle(k, approved));
            ['text-gray-600', 'border-gray-300', 'hover:bg-gray-50'].forEach(k => approveBtn.classList.toggle(k, !approved));

            const revise = decision === 'For Revision';
            ['bg-red-700', 'text-white', 'border-red-700', 'hover:bg-red-800'].forEach(k => reviseBtn.classList.toggle(k, revise));
            ['text-gray-600', 'border-gray-300', 'hover:bg-gray-50'].forEach(k => reviseBtn.classList.toggle(k, !revise));

            textarea.classList.toggle('hidden', !revise);
            if (!revise) textarea.value = '';

            chk2dUpdateSubmitState();
        }

        function chk2dSetRemarks(slot, value) {
            chk2dDecisions[slot].remarks = value;
            chk2dUpdateSubmitState();
        }

        function chk2dUpdateSubmitState() {
            const btn = document.getElementById('chk2dSubmitReviewBtn');
            if (!btn) return;
            btn.disabled = !Object.values(chk2dDecisions).every(d => {
                if (!d.decision) return false;
                if (d.decision === 'For Revision' && d.remarks.trim() === '') return false;
                return true;
            });
        }

        const CHK2D_DECISION_LABEL = `<p class="text-[10px] font-bold uppercase tracking-wide text-gray-400 mb-2">Your decision</p>`;

        function chk2dRenderFooter(record) {
            const footer = document.getElementById('chk2dModalFooter');

            if (record.review_target === '3d_only') {
                chk2dDecisions = { design_3d: { decision: null, remarks: '' } };
                footer.innerHTML = `
                    ${CHK2D_DECISION_LABEL}
                    <div class="space-y-2 mb-3">${chk2dDecisionRow('design_3d', '3D File', record.design_3d_path, false)}</div>
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-[11px] text-gray-400 leading-snug">Sending the 3D file back for revision will also reopen the 2D file for rework, since 3D is derived from it.</p>
                        <button type="button" id="chk2dSubmitReviewBtn" onclick="chk2dSubmitReview3d()" disabled
                            class="shrink-0 px-3.5 py-1.5 text-xs font-medium text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors">Submit Review</button>
                    </div>`;
                chk2dUpdateSubmitState();
                return;
            }

            if (record.review_target === 'none') {
                const reviewedLine = record.reviewed_at
                    ? `<p class="text-[11px] text-gray-400 mt-1">Reviewed ${chk2dFormatDateTimeLong(record.reviewed_at)}</p>` : '';
                footer.innerHTML = `
                    <div class="flex items-center justify-between gap-3">
                        <div><div class="flex flex-wrap items-center gap-1">${chk2dStatusBadgeWithRow(record)}</div>${reviewedLine}</div>
                        <button type="button" onclick="chk2dClosePanel()"
                            class="px-3.5 py-1.5 text-xs font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Close</button>
                    </div>`;
                return;
            }

            const design2dLocked = record.design_2d_review_status === 'Approved';
            const quotationLocked = record.quotation_review_status === 'Approved';

            chk2dDecisions = {
                design_2d: design2dLocked ? { decision: 'Approved', remarks: '' } : { decision: null, remarks: '' },
                quotation: quotationLocked ? { decision: 'Approved', remarks: '' } : { decision: null, remarks: '' },
            };
            if (record.include_3d) chk2dDecisions.design_3d = { decision: null, remarks: '' };

            footer.innerHTML = `
                ${CHK2D_DECISION_LABEL}
                <div class="space-y-2 mb-3">
                    ${chk2dDecisionRow('design_2d', '2D File', record.design_2d_path, design2dLocked)}
                    ${chk2dDecisionRow('quotation', 'Quotation File', record.quotation_path, quotationLocked)}
                    ${record.include_3d ? chk2dDecisionRow('design_3d', '3D File', record.design_3d_path, false) : ''}
                </div>
                <div class="flex items-center justify-between gap-3">
                    <p class="text-[11px] text-gray-400 leading-snug">${record.include_3d ? 'Sending the 3D file back for revision will also send the 2D file back, since 3D is derived from it.' : ''}</p>
                    <button type="button" id="chk2dSubmitReviewBtn" onclick="chk2dSubmitReview()" disabled
                        class="shrink-0 px-3.5 py-1.5 text-xs font-medium text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors">Submit Review</button>
                </div>`;

            chk2dUpdateSubmitState();
        }

        async function chk2dOpenPanel(id) {
            const overlay = document.getElementById('chk2dOverlay');
            const panel = document.getElementById('chk2dPanel');
            const body = document.getElementById('chk2dModalBody');
            const footer = document.getElementById('chk2dModalFooter');
            chk2dCurrentId = id;

            chk2dMarkViewed(id);
            if (chk2dLastRows.length) chk2dRenderRows(chk2dLastRows);

            document.getElementById('chk2dModalControlNo').textContent = 'Loading…';
            document.getElementById('chk2dModalStatus').innerHTML = '';
            body.innerHTML = `<div class="space-y-2 py-1">${Array.from({ length: 6 }).map(() => `<div class="h-4 rounded bg-gray-100 animate-pulse"></div>`).join('')}</div>`;
            footer.innerHTML = '';

            overlay.classList.remove('hidden');
            requestAnimationFrame(() => panel.classList.remove('translate-x-full'));

            try {
                const res = await fetch(`${CHK2D_AJAX_URL}?action=detail&id=${id}`);
                const data = await res.json();

                if (!data.success) {
                    body.innerHTML = `<p class="text-sm text-red-500 py-6 text-center">${chk2dEscapeHtml(data.message || 'Record not found.')}</p>`;
                    return;
                }

                const r = data.record;
                document.getElementById('chk2dModalControlNo').textContent = r.control_no;
                document.getElementById('chk2dModalStatus').innerHTML = chk2dStatusBadgeWithRow(r);
                const showsThreeD = r.include_3d || r.design_3d_stage !== 'Locked';

                body.innerHTML = [
                    chk2dSection('Client', [
                        chk2dDetailRow('Name', chk2dEscapeHtml(r.client_name)),
                        chk2dDetailRow('Contact Number', chk2dEscapeHtml(r.contact_number)),
                        chk2dDetailRow('Project Type', chk2dEscapeHtml(r.project_type) || '—'),
                    ].join('')),

                    chk2dSection('Project', [
                        chk2dDetailRow('Contract Amount', chk2dFormatCurrency(r.contract_amount)),
                        chk2dDetailRowHighlight('Target Completion Date', chk2dFormatDate(r.target_completion_date)),
                        chk2dDetailRow('Submitted', chk2dFormatDateTimeLong(r.submitted_at)),
                    ].join('')),

                    chk2dSection('Files &amp; Review', [
                        chk2dFileRow('2D File', r.design_2d_path, r.design_2d_uploader_name, r.design_2d_uploaded_role, r.design_2d_review_status, r.design_2d_remarks),
                        chk2dFileRow('Quotation File', r.quotation_path, r.quotation_uploader_name, r.quotation_uploaded_role, r.quotation_review_status, r.quotation_remarks),
                        showsThreeD ? chk2dFileRow('3D File', r.design_3d_path, r.design_3d_uploader_name, r.design_3d_uploaded_role, r.design_3d_review_status, r.design_3d_remarks) : '',
                    ].join('')),
                ].join('');

                chk2dRenderFooter(r);
            } catch (e) {
                console.error('chk2dOpenPanel:', e);
                body.innerHTML = `<p class="text-sm text-red-500 py-6 text-center">Connection error. Please try again.</p>`;
            }
        }

        function chk2dClosePanel() {
            const overlay = document.getElementById('chk2dOverlay');
            document.getElementById('chk2dPanel').classList.add('translate-x-full');
            setTimeout(() => overlay.classList.add('hidden'), 300);
            chk2dCurrentId = null;
            if (chk2dLastRows.length) chk2dRenderRows(chk2dLastRows);
        }

        document.addEventListener('keydown', e => { if (e.key === 'Escape') chk2dClosePanel(); });

        async function chk2dPost(formData, logName) {
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
                console.error(logName, e);
                crmShowToast('Connection error. Please try again.', 'error');
            }
        }

        async function chk2dSubmitReview() {
            if (!chk2dCurrentId) return;
            const fd = new FormData();
            fd.append('action', 'review');
            fd.append('id', chk2dCurrentId);
            fd.append('design_2d_decision', chk2dDecisions.design_2d.decision);
            fd.append('design_2d_remarks', chk2dDecisions.design_2d.remarks.trim());
            fd.append('quotation_decision', chk2dDecisions.quotation.decision);
            fd.append('quotation_remarks', chk2dDecisions.quotation.remarks.trim());
            if (chk2dDecisions.design_3d) {
                fd.append('design_3d_decision', chk2dDecisions.design_3d.decision);
                fd.append('design_3d_remarks', chk2dDecisions.design_3d.remarks.trim());
            }
            await chk2dPost(fd, 'chk2dSubmitReview:');
        }

        async function chk2dSubmitReview3d() {
            if (!chk2dCurrentId) return;
            const fd = new FormData();
            fd.append('action', 'review_3d');
            fd.append('id', chk2dCurrentId);
            fd.append('design_3d_decision', chk2dDecisions.design_3d.decision);
            fd.append('design_3d_remarks', chk2dDecisions.design_3d.remarks.trim());
            await chk2dPost(fd, 'chk2dSubmitReview3d:');
        }

        chk2dInitTabs();
        chk2dFetchList().then(chk2dStartPolling);
    </script>
</body>

</html>