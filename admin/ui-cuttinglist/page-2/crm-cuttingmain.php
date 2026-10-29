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
    <main id="main-content" class="md:ml-56 min-h-screen pt-20 md:pt-8 p-4 md:p-8 overflow-x-hidden transition-all duration-300">

        <div class="max-w-6xl mx-auto">

            <!-- ═══ HEADER ═══ -->
            <section class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden mb-4">
                <div class="h-1.5 bg-[repeating-linear-gradient(-45deg,#f59e0b_0_10px,#1f2937_10px_20px)]"></div>
                <div class="p-5 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-pen-ruler text-lg"></i></span>
                        <div class="min-w-0">
                            <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase">Cutting Department</p>
                            <h1 class="text-gray-900 text-lg font-semibold leading-tight">Cutting List</h1>
                            <p class="text-gray-400 text-[11px] mt-0.5">Approved 2D &amp; Quotation. Files open after Accounting gives the Notice to Proceed — then verify the 2D.</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1.5 text-[11px] text-gray-500 bg-gray-50 border border-gray-200 rounded-full px-3 py-1.5">
                        <span id="cutListLiveDot" class="w-2 h-2 rounded-full bg-amber-400"></span>
                        <span id="cutListUpdated">Loading…</span>
                    </span>
                </div>
            </section>

            <!-- ═══ STATUS CARDS (pindutin para i-filter) ═══ -->
            <div id="cutListTabs" class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-4">
                <?php
                $cards = [
                    ['todo',     'To verify',     'fa-file-circle-exclamation', 'bg-red-50 text-red-600'],
                    ['verified', 'Verified',      'fa-file-circle-check',       'bg-green-50 text-green-600'],
                    ['hold',     'On hold',       'fa-hourglass-half',          'bg-amber-50 text-amber-700'],
                    ['new',      'New',           'fa-bell',                    'bg-sky-50 text-sky-600'],
                    ['all',      'All',           'fa-layer-group',             'bg-slate-100 text-slate-600'],
                ];
                foreach ($cards as $i => [$key, $label, $icon, $tone]): ?>
                    <button type="button" data-filter="<?= $key ?>"
                        class="cutList-tab bg-white border border-gray-200 rounded-xl px-4 py-3 flex items-center gap-3 text-left hover:border-gray-300 transition-colors min-w-0 <?= $i === 4 ? 'col-span-2 sm:col-span-1' : '' ?>">
                        <span class="w-10 h-10 rounded-xl <?= $tone ?> flex items-center justify-center shrink-0"><i class="fa-solid <?= $icon ?>"></i></span>
                        <span class="min-w-0">
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-400 truncate"><?= $label ?></span>
                            <span data-count="<?= $key ?>" class="block text-xl font-bold text-gray-900 leading-tight tabular-nums">0</span>
                        </span>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- ═══ SEARCH ═══ -->
            <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
                <p id="cutListTabLabel" class="text-xs font-semibold text-gray-700"></p>
                <div class="relative w-full sm:w-72">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[11px] pointer-events-none"></i>
                    <input id="cutListSearch" type="text" placeholder="Search control no., client, branch, contact"
                        class="w-full h-9 pl-8 pr-8 text-xs bg-white border border-gray-200 rounded-lg focus:outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-100 transition-colors">
                    <button type="button" id="cutListSearchClear" aria-label="Clear search"
                        class="hidden absolute right-1.5 top-1/2 -translate-y-1/2 inline-flex items-center justify-center w-6 h-6 rounded-md text-gray-400 hover:text-gray-700 hover:bg-gray-100"><i class="fa-solid fa-xmark text-xs leading-none"></i></button>
                </div>
            </div>

            <!-- ═══ LIST ═══ -->
            <div id="cutListTbody" class="space-y-2"></div>
            <p id="cutListCount" class="text-[11px] text-gray-400 mt-2.5"></p>
        </div>

        <!-- Toast container -->
        <div id="crmToastContainer"
            class="fixed bottom-6 right-6 z-[9999] flex flex-col gap-2.5 pointer-events-none w-full max-w-sm px-4 sm:px-0">
        </div>

    </main>

    <style>
        .cutList-tab[data-active="1"] { border-color: #b45309; background: #fffbeb; box-shadow: 0 0 0 1px #b45309; }
    </style>

    <script>
        function crmShowToast(message, type = 'success', duration = 4000) {
            const container = document.getElementById('crmToastContainer');
            const palette = type === 'success'
                ? { wrap: 'bg-green-50 border-green-200 text-green-700', icon: 'bg-green-200 text-green-700', symbol: '✓' }
                : { wrap: 'bg-red-50 border-red-200 text-red-700', icon: 'bg-red-200 text-red-700', symbol: '!' };
            const toast = document.createElement('div');
            toast.className = `pointer-events-auto flex items-start gap-2.5 border rounded-lg shadow-lg px-4 py-3 text-sm ${palette.wrap} translate-x-6 opacity-0 scale-95 transition-all duration-300 ease-out`;
            toast.innerHTML = `
                <span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold ${palette.icon}">${palette.symbol}</span>
                <span class="flex-1 leading-relaxed"></span>
                <button type="button" class="shrink-0 text-current opacity-50 hover:opacity-100 text-base leading-none" aria-label="Close">&times;</button>`;
            toast.querySelector('.flex-1').textContent = message;
            container.appendChild(toast);
            requestAnimationFrame(() => toast.classList.remove('translate-x-6', 'opacity-0', 'scale-95'));
            const remove = () => { toast.classList.add('translate-x-6', 'opacity-0', 'scale-95'); setTimeout(() => toast.remove(), 300); };
            toast.querySelector('button').addEventListener('click', remove);
            if (duration > 0) setTimeout(remove, duration);
        }

        const CUT_LIST_AJAX_URL = <?= json_encode($cutListAjaxUrl) ?>;
        const CUT_DETAIL_URL = <?= json_encode($cutDetailUrl) ?>;
        const CUT_LIST_POLL_INTERVAL_MS = 8000;

        let cutListSearchTerm = '';
        let cutListFilter = 'todo';          // todo | verified | hold | new | all
        let cutListRows = [];
        let cutListLastSignature = '';
        let cutListPollTimer = null;
        let cutListSearchDebounce = null;
        let cutListFirstLoad = true;

        const cutMemo = {
            get: k => { try { return localStorage.getItem('nh-cutlist-' + k); } catch (e) { return null; } },
            set: (k, v) => { try { localStorage.setItem('nh-cutlist-' + k, v); } catch (e) { } },
        };

        function cutListEscapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }
        function cutListFormatDate(value) {
            if (!value) return '—';
            const dt = new Date(String(value).replace(' ', 'T'));
            if (isNaN(dt.getTime())) return value;
            return dt.toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
        }
        function cutListTimeAgo(value) {
            if (!value) return '—';
            const dt = new Date(String(value).replace(' ', 'T'));
            if (isNaN(dt.getTime())) return value;
            const s = Math.floor((Date.now() - dt.getTime()) / 1000);
            if (s < 0) return cutListFormatDate(value);
            if (s < 60) return 'Just now';
            const m = Math.floor(s / 60); if (m < 60) return `${m}m ago`;
            const h = Math.floor(m / 60); if (h < 24) return `${h}h ago`;
            const d = Math.floor(h / 24); if (d < 7) return `${d}d ago`;
            return cutListFormatDate(value);
        }

        const cutIsNtp = r => r.deposit_status === 'Notice to Proceed';
        const cutStage = r => !cutIsNtp(r) ? 'hold' : (r.design_2d_verified ? 'verified' : 'todo');

        const CUT_FILTERS = {
            todo:     { label: 'Notice to Proceed · 2D not verified yet', test: r => cutStage(r) === 'todo' },
            verified: { label: '2D verified · ready for E-Woodfile',      test: r => cutStage(r) === 'verified' },
            hold:     { label: 'On hold · waiting for Accounting\'s Notice to Proceed', test: r => cutStage(r) === 'hold' },
            new:      { label: 'New (not yet opened)',                    test: r => !r.is_read },
            all:      { label: 'All approved submissions',                test: () => true },
        };

        const CUT_STAGE_META = {
            todo:     { bar: 'bg-red-500',   chip: 'bg-red-50 text-red-700 border-red-200',       dot: 'bg-red-500',   icon: 'fa-file-circle-exclamation', label: 'Verify 2D' },
            verified: { bar: 'bg-green-500', chip: 'bg-green-50 text-green-700 border-green-200', dot: 'bg-green-500', icon: 'fa-file-circle-check',       label: '2D verified' },
            hold:     { bar: 'bg-amber-400', chip: 'bg-amber-50 text-amber-700 border-amber-200', dot: 'bg-amber-500', icon: 'fa-hourglass-half',         label: 'On hold' },
        };

        function cutListGoToDetail(id) {
            window.location.href = `${CUT_DETAIL_URL}?id=${id}`;
        }

        // ── status cards ──
        function cutListRenderTabs() {
            document.querySelectorAll('.cutList-tab').forEach(btn => {
                btn.dataset.active = btn.dataset.filter === cutListFilter ? '1' : '0';
            });
            document.getElementById('cutListTabLabel').textContent = CUT_FILTERS[cutListFilter].label;
        }
        function cutListInitTabs() {
            document.querySelectorAll('.cutList-tab').forEach(btn => {
                btn.addEventListener('click', () => {
                    cutListFilter = btn.dataset.filter;
                    cutMemo.set('filter', cutListFilter);
                    cutListRender();
                });
            });
        }
        function cutListCounts() {
            const cap = v => v > 99 ? '99+' : v;
            document.querySelectorAll('#cutListTabs [data-count]').forEach(el => {
                el.textContent = cap(cutListRows.filter(CUT_FILTERS[el.dataset.count].test).length);
            });
        }

        // ── list ──
        function cutListSkeletonRows(count = 4) {
            document.getElementById('cutListTbody').innerHTML = Array.from({ length: count }).map(() => `
                <div class="bg-white border border-gray-200 rounded-xl px-5 py-4 flex items-center gap-4">
                    <div class="flex-1 space-y-2"><div class="h-3 w-32 rounded bg-gray-100 animate-pulse"></div><div class="h-3 w-56 rounded bg-gray-100 animate-pulse"></div></div>
                    <div class="h-8 w-24 rounded-lg bg-gray-100 animate-pulse"></div>
                </div>`).join('');
        }
        function cutListEmptyState() {
            const message = cutListSearchTerm
                ? `No submissions match "${cutListEscapeHtml(cutListSearchTerm)}".`
                : ({ todo: 'Nothing to verify. You\'re all caught up.', verified: 'No verified 2D files yet.', hold: 'Nothing is on hold.', new: 'No new submissions.', all: 'No approved submissions found.' })[cutListFilter];
            return `<div class="bg-white border-2 border-dashed border-gray-200 rounded-xl flex flex-col items-center justify-center gap-2 py-14 text-center">
                    <i class="fa-regular fa-folder-open text-3xl text-gray-300"></i>
                    <p class="text-gray-500 text-xs font-medium">${message}</p>
                </div>`;
        }

        function cutListRowHtml(row) {
            const stage = cutStage(row);
            const m = CUT_STAGE_META[stage];
            const newTag = row.is_read ? '' : `<span class="inline-flex items-center px-1.5 py-px rounded text-[9.5px] font-bold uppercase tracking-wide bg-amber-500 text-white">New</span>`;
            const meta = [
                row.branch ? `<i class="fa-solid fa-building text-[9px] mr-1"></i>${cutListEscapeHtml(row.branch)}` : '',
                row.project_type ? `<span class="capitalize">${cutListEscapeHtml(row.project_type)}</span>` : '',
            ].filter(Boolean).join('<span class="mx-1.5 text-gray-300">·</span>');

            const ntpLine = cutIsNtp(row)
                ? `<p class="text-[10.5px] text-gray-400 mt-1">NTP ${cutListEscapeHtml(cutListTimeAgo(row.deposit_uploaded_at))}</p>`
                : `<p class="text-[10.5px] text-gray-400 mt-1">Waiting for Accounting</p>`;

            const action = stage === 'todo'
                ? `<button type="button" onclick="cutListGoToDetail(${row.id})" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 shadow-sm whitespace-nowrap"><i class="fa-solid fa-file-circle-check"></i> Check &amp; verify</button>`
                : `<button type="button" onclick="cutListGoToDetail(${row.id})" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 whitespace-nowrap">View <i class="fa-solid fa-chevron-right text-[9px]"></i></button>`;

            return `
            <article class="relative bg-white border ${row.is_read ? 'border-gray-200 hover:border-gray-300' : 'border-amber-200 hover:border-amber-300'} rounded-xl overflow-hidden cursor-pointer transition-all hover:shadow-sm" onclick="cutListGoToDetail(${row.id})">
                <span class="absolute left-0 top-0 bottom-0 w-1 ${m.bar}"></span>
                <div class="flex flex-wrap items-center gap-x-6 gap-y-3 pl-5 pr-4 py-4">
                    <div class="min-w-0 flex-1 basis-[220px]">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="notranslate font-mono text-[11.5px] font-bold text-amber-700">${cutListEscapeHtml(row.control_no)}</span>${newTag}
                        </div>
                        <p class="text-gray-900 text-sm font-semibold mt-0.5 truncate capitalize">${cutListEscapeHtml(row.client_name)}</p>
                        <p class="text-gray-400 text-[11px] mt-0.5 truncate">${meta || '—'}</p>
                    </div>
                    <dl class="grid grid-cols-2 gap-x-5 text-[11px] basis-[230px] grow sm:grow-0">
                        <div class="min-w-0">
                            <dt class="text-[9.5px] font-bold uppercase tracking-wider text-gray-400">Designer</dt>
                            <dd class="text-gray-700 font-medium truncate">${cutListEscapeHtml(row.designer_name || '—')}</dd>
                        </div>
                        <div class="min-w-0">
                            <dt class="text-[9.5px] font-bold uppercase tracking-wider text-gray-400">Sales</dt>
                            <dd class="text-gray-700 font-medium truncate">${cutListEscapeHtml(row.sales_staff_name || '—')}</dd>
                        </div>
                    </dl>
                    <div class="basis-[150px]">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10.5px] font-semibold border whitespace-nowrap ${m.chip}">
                            <i class="fa-solid ${m.icon} text-[9px]"></i>${m.label}</span>
                        ${ntpLine}
                    </div>
                    <div class="ml-auto shrink-0" onclick="event.stopPropagation()">${action}</div>
                </div>
            </article>`;
        }

        function cutListRender() {
            cutListRenderTabs();
            cutListCounts();
            const box = document.getElementById('cutListTbody');
            const list = cutListRows.filter(CUT_FILTERS[cutListFilter].test);
            document.getElementById('cutListCount').textContent = `${list.length} of ${cutListRows.length} submission${cutListRows.length === 1 ? '' : 's'}`;
            box.innerHTML = list.length ? list.map(cutListRowHtml).join('') : cutListEmptyState();
        }

        async function cutListFetchList({ silent = false } = {}) {
            if (!silent && !cutListRows.length) cutListSkeletonRows();
            try {
                // laging "all" ang kinukuha; dito na sa page ang pag-filter at bilang
                const url = `${CUT_LIST_AJAX_URL}?action=list&q=${encodeURIComponent(cutListSearchTerm)}&filter=all`;
                const res = await fetch(url, { cache: 'no-store' });
                const data = await res.json();
                if (!data.success) {
                    if (!silent) crmShowToast('Failed to load submissions.', 'error');
                    return;
                }
                document.getElementById('cutListUpdated').textContent = 'Updated ' + new Date().toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' });

                const signature = JSON.stringify(data.rows.map(r => `${r.id}:${r.is_read}:${r.deposit_status}:${r.design_2d_verified ? 1 : 0}`)) + cutListSearchTerm;
                if (signature === cutListLastSignature && silent) return;
                cutListLastSignature = signature;
                cutListRows = data.rows;

                // Unang bukas: kapag walang "To verify", ipakita na lang ang lahat (maliban kung may naka-save na filter)
                if (cutListFirstLoad) {
                    cutListFirstLoad = false;
                    if (!cutMemo.get('filter') && !cutListRows.some(CUT_FILTERS.todo.test)) cutListFilter = 'all';
                }
                cutListRender();
            } catch (e) {
                console.error('cutListFetchList:', e);
                if (!silent) crmShowToast('Connection error while fetching submissions.', 'error');
            }
        }

        const cutListRtLive = () => !!(window.NH_RT && window.NH_RT.enabled && window.NH_RT.isLive());

        function cutListStartPolling() {
            if (cutListPollTimer) clearInterval(cutListPollTimer);
            // Live → 60s fallback lang; hindi Live → dating bilis
            cutListPollTimer = setInterval(() => cutListFetchList({ silent: true }), cutListRtLive() ? 60000 : CUT_LIST_POLL_INTERVAL_MS);
        }

        // ─── Realtime (koneksyon ng sidebar: window.NH_RT) ───
        // Bagong approved, NTP ng Accounting, verify / feedback ng ibang Cutting → kunin agad.
        (function cutListHookRealtime() {
            const hook = () => {
                if (!window.NH_RT || !window.NH_RT.enabled || cutListHookRealtime.done) return;
                cutListHookRealtime.done = true;
                let t = null;
                window.NH_RT.on('*', 'changed', (payload) => {
                    const type = String(payload?.type || '');
                    if (type && !type.startsWith('crm')) return; // budget/voucher — hindi para dito
                    clearTimeout(t);
                    t = setTimeout(() => { if (!document.hidden) cutListFetchList({ silent: true }); }, 400);
                });
                window.NH_RT.onLive(live => {
                    const dot = document.getElementById('cutListLiveDot');
                    if (dot) dot.className = 'w-2 h-2 rounded-full ' + (live ? 'bg-green-500 animate-pulse' : 'bg-amber-400');
                    if (!document.hidden && cutListPollTimer) cutListStartPolling();
                    if (live && cutListLastSignature) cutListFetchList({ silent: true });
                });
            };
            hook();
            if (!cutListHookRealtime.done) document.addEventListener('DOMContentLoaded', hook);
        })();

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

        const cutSavedFilter = cutMemo.get('filter');
        if (cutSavedFilter && CUT_FILTERS[cutSavedFilter]) cutListFilter = cutSavedFilter;
        cutListInitTabs();
        cutListRenderTabs();
        cutListFetchList().then(cutListStartPolling);
    </script>
</body>

</html>