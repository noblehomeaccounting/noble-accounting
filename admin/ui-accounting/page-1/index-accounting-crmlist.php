<?php
// index-accounting-crmlist.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_ACCOUNTING];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$acctListAjaxUrl = BASE_URL . '/accountingcrmlistajax';
$acctDetailUrl = BASE_URL . '/accountingcrmdetail';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting — Approved 2D & Quotation</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
</head>

<body class="bg-slate-100">
    <main id="main-content" class="md:ml-56 min-h-screen pt-20 md:pt-8 p-4 md:p-8 overflow-x-hidden transition-all duration-300">

        <div class="max-w-6xl mx-auto">

            <!-- ===================== HEADER ===================== -->
            <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
                <div>
                    <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase mb-0.5">Accounting · CRM</p>
                    <h1 class="text-gray-900 text-xl font-semibold">Approved 2D &amp; Quotation</h1>
                    <p class="text-gray-400 text-xs mt-0.5">Record each client payment with its receipt, then give the <b class="text-gray-600">Notice to Proceed</b> — production starts after that.</p>
                </div>
                <a href="<?= BASE_URL ?>/accountingpaymentmethods"
                    class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 hover:text-amber-700 transition-colors whitespace-nowrap">
                    <i class="fa-solid fa-gear"></i> Payment Methods
                </a>
            </div>

            <!-- ===================== STATUS CARDS (pindutin para i-filter) ===================== -->
            <div id="acctListTabs" class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
                <?php
                $cards = [
                    ['hold',  'Waiting for deposit', 'fa-hourglass-half',    'bg-amber-50 text-amber-700', 'acctStatHold'],
                    ['ntp',   'Notice to Proceed',   'fa-circle-check',      'bg-green-50 text-green-600', 'acctStatNtp'],
                    ['balance', 'With balance',      'fa-scale-unbalanced',  'bg-rose-50 text-rose-600',   'acctStatBalance'],
                    ['new',   'New',                 'fa-bell',              'bg-sky-50 text-sky-600',     'acctListNewCount'],
                    ['all',   'All',                 'fa-layer-group',       'bg-slate-100 text-slate-600','acctListAllCount'],
                ];
                foreach ($cards as [$key, $label, $icon, $tone, $countId]): ?>
                    <button type="button" data-filter="<?= $key ?>"
                        class="acctList-tab bg-white border border-gray-200 rounded-xl px-4 py-3.5 flex items-center gap-3 text-left hover:border-gray-300 transition-colors">
                        <span class="w-10 h-10 rounded-xl <?= $tone ?> flex items-center justify-center shrink-0"><i class="fa-solid <?= $icon ?>"></i></span>
                        <span class="min-w-0">
                            <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-400 truncate"><?= $label ?></span>
                            <span id="<?= $countId ?>" class="block text-xl font-bold text-gray-900 leading-tight tabular-nums">0</span>
                        </span>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- ===================== SEARCH ===================== -->
            <div class="flex items-center justify-between gap-2 mb-3">
                <p id="acctListTabLabel" class="text-xs font-semibold text-gray-700"></p>
                <div class="relative w-full sm:w-72">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[11px]"></i>
                    <input id="acctListSearch" type="text" placeholder="Search control no., client, contact, branch"
                        class="w-full pl-8 pr-7 py-2 text-xs bg-white border border-gray-200 rounded-lg focus:outline-none focus:border-amber-600 transition-colors">
                    <button type="button" id="acctListSearchClear"
                        class="hidden absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-500 text-base leading-none w-4 h-4">&times;</button>
                </div>
            </div>

            <!-- ===================== LIST ===================== -->
            <div id="acctListTbody" class="space-y-2"></div>
            <p id="acctListCount" class="text-[11px] text-gray-400 mt-2.5"></p>
        </div>

        <!-- Toast container -->
        <div id="crmToastContainer"
            class="fixed bottom-6 right-6 z-[9999] flex flex-col gap-2.5 pointer-events-none w-full max-w-sm px-4 sm:px-0">
        </div>

    </main>

    <style>
        .acctList-tab[data-active="1"] { border-color: #b45309; background: #fffbeb; box-shadow: 0 0 0 1px #b45309; }
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

        const ACCT_LIST_AJAX_URL = <?= json_encode($acctListAjaxUrl) ?>;
        const ACCT_DETAIL_URL = <?= json_encode($acctDetailUrl) ?>;
        const ACCT_LIST_POLL_INTERVAL_MS = 8000;

        let acctListSearchTerm = '';
        let acctListFilter = 'hold';     // hold | ntp | new | all
        let acctListRows = [];
        let acctListLastSignature = '';
        let acctListPollTimer = null;
        let acctListSearchDebounce = null;

        const acctMemo = {
            get: k => { try { return localStorage.getItem('nh-acctlist-' + k); } catch (e) { return null; } },
            set: (k, v) => { try { localStorage.setItem('nh-acctlist-' + k, v); } catch (e) { } },
        };

        function acctListEscapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }
        function acctListFormatDate(value) {
            if (!value) return '—';
            const dt = new Date(value.replace(' ', 'T'));
            if (isNaN(dt.getTime())) return value;
            return dt.toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
        }
        function acctListFormatDateTimeLong(value) {
            if (!value) return '—';
            const dt = new Date(value.replace(' ', 'T'));
            if (isNaN(dt.getTime())) return value;
            return dt.toLocaleString('en-PH', { year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true });
        }
        // "5m ago" kapag bago; petsa na kapag luma
        function acctListTimeAgo(value) {
            if (!value) return '—';
            const dt = new Date(value.replace(' ', 'T'));
            if (isNaN(dt.getTime())) return value;
            const diffSec = Math.floor((Date.now() - dt.getTime()) / 1000);
            if (diffSec < 0) return acctListFormatDate(value);
            if (diffSec < 60) return 'Just now';
            const diffMin = Math.floor(diffSec / 60);
            if (diffMin < 60) return `${diffMin}m ago`;
            const diffHr = Math.floor(diffMin / 60);
            if (diffHr < 24) return `${diffHr}h ago`;
            const diffDay = Math.floor(diffHr / 24);
            if (diffDay < 7) return `${diffDay}d ago`;
            return acctListFormatDate(value);
        }
        function acctListCurrency(value) {
            if (value === null || value === undefined || value === '') return '<span class="text-gray-300">—</span>';
            const num = Number(String(value).replace(/,/g, ''));
            if (isNaN(num)) return acctListEscapeHtml(value);
            return '₱' + num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }
        const acctIsNtp = r => r.deposit_status === 'Notice to Proceed';

        function acctListDepositBadge(r) {
            if (!acctIsNtp(r) && Number(r.payment_count) > 0) {
                return `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10.5px] font-semibold border bg-sky-50 text-sky-700 border-sky-200 whitespace-nowrap"><span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>Paid · waiting for NTP</span>`;
            }
            return acctIsNtp(r)
                ? `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10.5px] font-semibold border bg-green-50 text-green-700 border-green-200 whitespace-nowrap"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>Notice to Proceed</span>`
                : `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10.5px] font-semibold border bg-amber-50 text-amber-700 border-amber-200 whitespace-nowrap"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>Hold · waiting for deposit</span>`;
        }

        function acctListGoToDetail(id) {
            window.location.href = `${ACCT_DETAIL_URL}?id=${id}`;
        }

        // ── status cards ──
        const ACCT_FILTERS = {
            hold: { label: 'Waiting for deposit', test: r => !acctIsNtp(r) },
            ntp:  { label: 'Notice to Proceed',   test: r => acctIsNtp(r) },
            // may balanse pa — kailangang bayad nang buo bago ma-turnover ng Project Coordinator
            balance: { label: 'With balance (must be fully paid before turnover)', test: r => acctIsNtp(r) && Number(r.balance) > 0.004 },
            new:  { label: 'New (not yet opened)', test: r => !r.is_read },
            all:  { label: 'All approved submissions', test: () => true },
        };
        function acctListRenderTabs() {
            document.querySelectorAll('.acctList-tab').forEach(btn => {
                btn.dataset.active = btn.dataset.filter === acctListFilter ? '1' : '0';
            });
            document.getElementById('acctListTabLabel').textContent = ACCT_FILTERS[acctListFilter].label;
        }
        function acctListInitTabs() {
            document.querySelectorAll('.acctList-tab').forEach(btn => {
                btn.addEventListener('click', () => {
                    acctListFilter = btn.dataset.filter;
                    acctMemo.set('filter', acctListFilter);
                    acctListRender();
                });
            });
        }
        function acctListCounts() {
            const n = k => acctListRows.filter(ACCT_FILTERS[k].test).length;
            const cap = v => v > 99 ? '99+' : v;
            document.getElementById('acctStatHold').textContent = cap(n('hold'));
            document.getElementById('acctStatNtp').textContent = cap(n('ntp'));
            document.getElementById('acctStatBalance').textContent = cap(n('balance'));
            document.getElementById('acctListNewCount').textContent = cap(n('new'));
            document.getElementById('acctListAllCount').textContent = cap(n('all'));
        }

        // ── list ──
        function acctListSkeletonRows(count = 4) {
            document.getElementById('acctListTbody').innerHTML = Array.from({ length: count }).map(() => `
                <div class="bg-white border border-gray-200 rounded-xl px-5 py-4 flex items-center gap-4">
                    <div class="flex-1 space-y-2"><div class="h-3 w-32 rounded bg-gray-100 animate-pulse"></div><div class="h-3 w-56 rounded bg-gray-100 animate-pulse"></div></div>
                    <div class="h-8 w-20 rounded-lg bg-gray-100 animate-pulse"></div>
                </div>`).join('');
        }
        function acctListEmptyState() {
            const message = acctListSearchTerm
                ? `No submissions match "${acctListEscapeHtml(acctListSearchTerm)}".`
                : ({ hold: 'No submission is waiting for a deposit.', ntp: 'No Notice to Proceed yet.', balance: 'Every client with a Notice to Proceed is fully paid.', new: 'No new approvals.', all: 'No approved submissions found.' })[acctListFilter];
            return `<div class="bg-white border-2 border-dashed border-gray-200 rounded-xl flex flex-col items-center justify-center gap-2 py-14 text-center">
                    <i class="fa-regular fa-folder-open text-3xl text-gray-300"></i>
                    <p class="text-gray-500 text-xs font-medium">${message}</p>
                </div>`;
        }

        function acctListRender() {
            acctListRenderTabs();
            acctListCounts();
            const box = document.getElementById('acctListTbody');
            const list = acctListRows.filter(ACCT_FILTERS[acctListFilter].test);
            document.getElementById('acctListCount').textContent = `${list.length} of ${acctListRows.length} submission${acctListRows.length === 1 ? '' : 's'}`;
            if (!list.length) { box.innerHTML = acctListEmptyState(); return; }

            box.innerHTML = list.map(row => {
                const ntp = acctIsNtp(row);
                const newTag = row.is_read ? '' : `<span class="inline-flex items-center px-1.5 py-px rounded text-[9.5px] font-bold uppercase tracking-wide bg-amber-500 text-white">New</span>`;
                const meta = [row.branch ? `<i class="fa-solid fa-building text-[9px] mr-1"></i>${acctListEscapeHtml(row.branch)}` : '',
                              row.sales_staff_name ? `<i class="fa-solid fa-user text-[9px] mr-1"></i>${acctListEscapeHtml(row.sales_staff_name)}` : '',
                              row.project_type ? acctListEscapeHtml(row.project_type) : ''].filter(Boolean).join('<span class="mx-1.5 text-gray-300">·</span>');
                const action = ntp
                    ? `<button type="button" onclick="acctListGoToDetail(${row.id})" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 whitespace-nowrap">View <i class="fa-solid fa-chevron-right text-[9px]"></i></button>`
                    : `<button type="button" onclick="acctListGoToDetail(${row.id})" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 shadow-sm whitespace-nowrap"><i class="fa-solid ${Number(row.payment_count) > 0 ? 'fa-flag-checkered' : 'fa-money-bill-wave'}"></i> ${Number(row.payment_count) > 0 ? 'Payments / NTP' : 'Record Payment'}</button>`;
                return `
                <article class="relative bg-white border ${row.is_read ? 'border-gray-200 hover:border-gray-300' : 'border-amber-200 hover:border-amber-300'} rounded-xl overflow-hidden cursor-pointer transition-all hover:shadow-sm" onclick="acctListGoToDetail(${row.id})">
                    <span class="absolute left-0 top-0 bottom-0 w-1 ${ntp ? 'bg-green-500' : 'bg-amber-400'}"></span>
                    <div class="flex flex-wrap items-center gap-x-6 gap-y-3 pl-5 pr-4 py-4">
                        <div class="min-w-0 flex-1 basis-[230px]">
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="font-mono text-[11.5px] font-bold text-amber-700">${acctListEscapeHtml(row.control_no)}</span>${newTag}
                            </div>
                            <p class="text-gray-900 text-sm font-semibold mt-0.5 truncate">${acctListEscapeHtml(row.client_name)}</p>
                            <p class="text-gray-400 text-[11px] mt-0.5 truncate">${meta || '—'}</p>
                        </div>
                        <div class="basis-[120px]">
                            <p class="text-[9.5px] font-bold uppercase tracking-wider text-gray-400">Designer</p>
                            <p class="text-gray-700 text-xs mt-0.5 truncate">${acctListEscapeHtml(row.designer_name || '—')}</p>
                        </div>
                        <div class="basis-[150px]">
                            <p class="text-[9.5px] font-bold uppercase tracking-wider text-gray-400">Contract</p>
                            <p class="text-gray-900 text-sm font-bold tabular-nums mt-0.5">${acctListCurrency(row.contract_amount)}</p>
                            ${Number(row.payment_count) > 0 ? `<p class="text-[10.5px] text-gray-500 tabular-nums">Paid <b class="text-green-700">${acctListCurrency(row.paid_total)}</b>${Number(row.balance) > 0 ? ` · Bal <b class="text-amber-700">${acctListCurrency(row.balance)}</b>` : ' · <b class="text-green-700">Fully paid</b>'}</p>` : '<p class="text-[10.5px] text-gray-400">No payment yet</p>'}
                        </div>
                        <div class="basis-[170px]">
                            <p class="text-[9.5px] font-bold uppercase tracking-wider text-gray-400 mb-1">Deposit</p>
                            ${acctListDepositBadge(row)}
                            <p class="text-[10.5px] text-gray-400 mt-1" title="${acctListEscapeHtml(acctListFormatDateTimeLong(row.reviewed_at))}">Approved ${acctListTimeAgo(row.reviewed_at)}</p>
                        </div>
                        <div class="ml-auto shrink-0" onclick="event.stopPropagation()">${action}</div>
                    </div>
                </article>`;
            }).join('');
        }

        async function acctListFetchList({ silent = false } = {}) {
            if (!silent) acctListSkeletonRows();
            try {
                // laging "all" ang kinukuha; dito na sa page ang pag-filter at bilang
                const url = `${ACCT_LIST_AJAX_URL}?action=list&q=${encodeURIComponent(acctListSearchTerm)}&filter=all`;
                const res = await fetch(url);
                const data = await res.json();
                if (!data.success) {
                    if (!silent) crmShowToast('Failed to load submissions.', 'error');
                    return;
                }
                const signature = JSON.stringify(data.rows.map(r => `${r.id}:${r.is_read}:${r.deposit_status}:${r.paid_total}:${r.payment_count}`)) + acctListSearchTerm;
                if (signature !== acctListLastSignature || !silent) {
                    acctListRows = data.rows;
                    acctListLastSignature = signature;
                    acctListRender();
                }
            } catch (e) {
                console.error('acctListFetchList:', e);
                if (!silent) crmShowToast('Connection error while fetching submissions.', 'error');
            }
        }

        const acctListRtLive = () => !!(window.NH_RT && window.NH_RT.enabled && window.NH_RT.isLive());

        function acctListStartPolling() {
            if (acctListPollTimer) clearInterval(acctListPollTimer);
            // Live → 60s fallback lang; hindi Live → dating bilis
            acctListPollTimer = setInterval(() => acctListFetchList({ silent: true }), acctListRtLive() ? 60000 : ACCT_LIST_POLL_INTERVAL_MS);
        }

        // ─── Realtime (koneksyon ng sidebar: window.NH_RT) ───
        // Bagong approved (Designer Head / Superadmin), bayad o NTP ng ibang Accounting, reset → kunin agad.
        (function acctListHookRealtime() {
            const hook = () => {
                if (!window.NH_RT || !window.NH_RT.enabled || acctListHookRealtime.done) return;
                acctListHookRealtime.done = true;
                let t = null;
                window.NH_RT.on('*', 'changed', (payload) => {
                    const type = String(payload?.type || '');
                    if (type && !type.startsWith('crm')) return; // budget/voucher — hindi para dito
                    clearTimeout(t);
                    t = setTimeout(() => { if (!document.hidden) acctListFetchList({ silent: true }); }, 400);
                });
                window.NH_RT.onLive(live => {
                    if (!document.hidden && acctListPollTimer) acctListStartPolling();
                    if (live && acctListRows.length) acctListFetchList({ silent: true });
                });
            };
            hook();
            if (!acctListHookRealtime.done) document.addEventListener('DOMContentLoaded', hook);
        })();
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                if (acctListPollTimer) clearInterval(acctListPollTimer);
            } else {
                acctListFetchList({ silent: true });
                acctListStartPolling();
            }
        });

        const acctListSearchInput = document.getElementById('acctListSearch');
        const acctListSearchClear = document.getElementById('acctListSearchClear');
        acctListSearchInput.addEventListener('input', function () {
            acctListSearchClear.classList.toggle('hidden', this.value.length === 0);
            clearTimeout(acctListSearchDebounce);
            const value = this.value;
            acctListSearchDebounce = setTimeout(() => {
                acctListSearchTerm = value.trim();
                acctListFetchList();
            }, 350);
        });
        acctListSearchClear.addEventListener('click', () => {
            acctListSearchInput.value = '';
            acctListSearchClear.classList.add('hidden');
            acctListSearchTerm = '';
            acctListFetchList();
            acctListSearchInput.focus();
        });

        acctListFilter = ACCT_FILTERS[acctMemo.get('filter')] ? acctMemo.get('filter') : 'hold';
        acctListInitTabs();
        acctListRenderTabs();
        acctListFetchList().then(acctListStartPolling);
    </script>
</body>

</html>