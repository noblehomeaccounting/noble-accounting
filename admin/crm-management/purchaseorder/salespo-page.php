<?php
// salespo-page.php — iisang page ng Purchase Order (kasama ng purchaseordermain.php at salespoapproval.php)
//   $spoMode = 'sales'    → gumagawa / nagsa-submit (Prepared by)
//   $spoMode = 'approver' → Accounting Head · Superadmin · Accounting Staff · Custodian / Custodian Assistant
if (!isset($spoMode))
    $spoMode = 'sales';
$spoAjax = BASE_URL . '/salespoajax';
$spoTitle = $spoMode === 'sales' ? 'Purchase Orders' : 'Purchase Order Approvals';
$spoRoleNow = $_SESSION['role'] ?? '';
$spoPosNow = $_SESSION['position'] ?? '';
$spoWho = $spoMode === 'sales' ? 'Sales'
    : ($spoRoleNow === ROLE_SUPERADMIN ? 'Superadmin · Approved by'
        : ($spoPosNow === POSITION_HEAD ? 'Accounting Head · Acknowledged by'
            : ($spoPosNow === POSITION_STAFF ? 'Accounting Staff · Received by'
                : ($spoPosNow === POSITION_CUSTOASSISTANT ? 'Custodian Assistant · Released by' : 'Custodian · Released by'))));
$spoIntro = $spoMode === 'sales'
    ? 'Prepare a Purchase Order and submit it. It goes to the Accounting Head, then the Superadmin, Accounting Staff and Custodian.'
    : 'Purchase Orders from Sales. Sign the ones waiting for you, or return them to Sales with a note.';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <!-- maximum-scale=1: hindi na mag-a-auto-zoom ang iPhone kapag nag-tap sa field (pwede pa ring mag-pinch zoom) -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1">
    <title><?= htmlspecialchars($spoTitle) ?></title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <?php include ROOT_PATH . '/admin/crm-management/purchaseorder/salespo-doc.php'; ?>
    <style>
        .spo-hazard {
            background: repeating-linear-gradient(-45deg, #f59e0b 0 10px, #1f2937 10px 20px);
        }

        .spo-tab[data-active="1"] {
            border-color: #b45309;
            background: #fffbeb;
            box-shadow: 0 0 0 1px #b45309;
        }

        .spo-sel {
            height: 36px;
            padding: 0 30px 0 12px;
            font-size: 12px;
            font-weight: 500;
            color: #374151;
            background-color: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            outline: none;
        }

        .spo-sel:focus,
        .spo-inp:focus {
            border-color: #b45309;
            box-shadow: 0 0 0 3px #fef3c7;
        }

        .spo-inp {
            width: 100%;
            height: 34px;
            padding: 0 10px;
            font-size: 12px;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            outline: none;
        }

        .spo-q {
            height: 30px;
            font-size: 11px;
            font-weight: 600;
            color: #4b5563;
            background: #fff;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
        }

        .spo-q:hover {
            border-color: #fbbf24;
            color: #b45309;
        }

        .spo-q[data-on="1"] {
            background: #b45309;
            color: #fff;
            border-color: #b45309;
        }

        .spo-search { height: 36px; }

        /* Mobile: bahagyang mas mataas na tap target */
        @media (max-width: 639px) {
            .spo-inp, .spo-sel, .spo-search { height: 38px; }
            .spo-q { height: 34px; }
        }

        .spo-scroll {
            scrollbar-width: thin;
            scrollbar-color: #d1d5db transparent;
        }
    </style>
</head>

<body class="bg-slate-100">
    <main id="main-content" class="md:ml-56 min-h-screen px-3 pt-20 pb-6 sm:px-4 md:p-8 overflow-x-hidden">
        <div class="max-w-6xl mx-auto">

            <!-- ═══ HEADER + TABS ═══ -->
            <section class="relative z-20 bg-white border border-gray-200 rounded-xl mb-4">
                <div class="spo-hazard h-1.5 rounded-t-xl"></div>
                <div class="p-4 sm:p-5 flex flex-wrap items-center justify-between gap-3 sm:gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <span
                            class="w-10 h-10 sm:w-11 sm:h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i
                                class="fa-solid fa-file-invoice text-lg"></i></span>
                        <div class="min-w-0">
                            <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase">
                                <?= htmlspecialchars($spoWho) ?></p>
                            <h1 class="text-gray-900 text-lg font-semibold leading-tight">
                                <?= htmlspecialchars($spoTitle) ?></h1>
                            <p class="text-gray-400 text-[11px] mt-0.5 max-w-xl"><?= htmlspecialchars($spoIntro) ?></p>
                        </div>
                    </div>
                    <?php if ($spoMode === 'sales'): ?>
                        <button type="button" id="spoNew"
                            class="w-full sm:w-auto justify-center inline-flex items-center gap-2 px-4 py-2.5 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 shadow-sm"><i
                                class="fa-solid fa-plus"></i> New Purchase Order</button>
                    <?php endif; ?>
                </div>
                <div id="spoTabs" class="px-4 sm:px-5 pb-4 grid grid-cols-4 gap-1.5 sm:gap-2">
                    <?php foreach ([['todo', $spoMode === 'sales' ? 'To do' : 'For your signature', 'text-amber-700', $spoMode === 'sales' ? 'To do' : 'To sign'], ['progress', 'In progress', 'text-blue-700', 'Ongoing'], ['released', 'Released', 'text-emerald-700', 'Released'], ['', 'All', 'text-gray-900', 'All']] as [$v, $l, $cls, $short]): ?>
                        <button type="button" data-tab="<?= $v ?>"
                            class="spo-tab min-w-0 text-left rounded-xl border border-gray-200 bg-white px-2 py-1.5 sm:px-3.5 sm:py-2 transition-colors">
                            <span
                                class="block truncate text-[9.5px] sm:text-[10px] font-semibold uppercase sm:tracking-wide text-gray-500"><span class="sm:hidden"><?= $short ?></span><span class="hidden sm:inline"><?= $l ?></span></span>
                            <span class="flex items-baseline justify-between gap-2"><span
                                    class="spo-tab-count text-lg sm:text-xl font-bold leading-tight tabular-nums <?= $cls ?>">0</span><span
                                    class="spo-tab-sum hidden sm:inline text-[10.5px] text-gray-400 font-mono truncate"></span></span>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Search · Date · Status · Sort -->
                <div
                    class="px-4 sm:px-5 py-3 border-t border-gray-100 bg-gray-50/60 rounded-b-xl grid grid-cols-[minmax(0,1fr)_auto] sm:flex sm:flex-wrap items-center gap-2">
                    <div class="relative sm:flex-1 sm:min-w-[200px] sm:max-w-sm">
                        <i
                            class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[11px]"></i>
                        <input id="spoSearch" type="text" placeholder="Search P.O. no., vendor, customer…"
                            class="spo-inp spo-search" style="padding-left:2rem">
                    </div>
                    <button type="button" data-fd-open="spoFd" class="sm:hidden shrink-0 inline-flex items-center gap-1.5 h-[38px] px-3 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:border-amber-500 "><i class="fa-solid fa-sliders"></i> Filters <span data-fd-badge class="hidden min-w-[18px] h-[18px] px-1 rounded-full bg-amber-700 text-white text-[10px] font-bold inline-flex items-center justify-center">0</span></button>
                    <div id="spoFdBackdrop" class="nh-fd-backdrop"></div>
<div id="spoFd" class="nh-fd" role="dialog" aria-label="Filters">
    <div class="nh-fd-only flex items-center justify-between px-4 py-3 border-b border-gray-100">
        <div class="flex items-center gap-2"><i class="fa-solid fa-sliders text-amber-600"></i><h2 class="text-gray-900 text-sm font-semibold">Filters</h2></div>
        <button type="button" data-fd-close aria-label="Close" class="-mr-2 w-10 h-10 flex items-center justify-center text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-lg"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg></button>
    </div>
    <div class="nh-fd-body">
                    <div class="relative min-w-0" id="spoDateWrap">
                        <button type="button" id="spoDateBtn"
                            class="w-full sm:w-auto h-[38px] sm:h-9 inline-flex items-center gap-2 px-3 text-xs font-medium text-gray-700 bg-white border border-gray-200 rounded-lg hover:border-gray-300">
                            <i class="fa-regular fa-calendar text-gray-400"></i><span id="spoDateText" class="flex-1 sm:flex-none min-w-0 truncate text-left">Any date</span>
                            <span id="spoDateX"
                                class="hidden w-4 h-4 -mr-1 rounded-full bg-amber-700 text-white text-[9px] leading-4 text-center">&times;</span>
                            <i class="fa-solid fa-chevron-down text-[9px] text-gray-400"></i>
                        </button>
                        <div id="spoDatePop"
                            class="hidden absolute left-0 top-full mt-1.5 z-30 w-[320px] max-w-[calc(100vw-3.5rem)] bg-white border border-gray-200 rounded-xl shadow-lg p-3.5">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400 mb-1.5">P.O. date</p>
                            <div class="grid grid-cols-3 gap-1.5 mb-3">
                                <button type="button" data-q="today" class="spo-q">Today</button>
                                <button type="button" data-q="week" class="spo-q">This week</button>
                                <button type="button" data-q="month" class="spo-q">This month</button>
                            </div>
                            <div class="grid grid-cols-2 gap-2 mb-3">
                                <label><span
                                        class="block text-[10px] font-semibold text-gray-500 mb-0.5">Month</span><input
                                        id="spoMonth" type="month" class="spo-inp"></label>
                                <label><span
                                        class="block text-[10px] font-semibold text-gray-500 mb-0.5">Day</span><input
                                        id="spoDay" type="date" class="spo-inp"></label>
                                <label><span
                                        class="block text-[10px] font-semibold text-gray-500 mb-0.5">From</span><input
                                        id="spoFrom" type="date" class="spo-inp"></label>
                                <label><span
                                        class="block text-[10px] font-semibold text-gray-500 mb-0.5">To</span><input
                                        id="spoTo" type="date" class="spo-inp"></label>
                            </div>
                            <div class="flex items-center justify-between pt-2.5 border-t border-gray-100">
                                <button type="button" id="spoDateClear"
                                    class="text-[11px] font-semibold text-gray-500 hover:text-amber-700">Clear
                                    date</button>
                                <button type="button" id="spoDateDone"
                                    class="px-3 py-1.5 text-[11px] font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800">Done</button>
                            </div>
                        </div>
                    </div>
<p class="nh-fd-only nh-fd-label">Status</p>
                    <select id="spoStatus" class="spo-sel w-full sm:w-auto min-w-0" title="Status">
                        <option value="">All statuses</option>
                        <?php if ($spoMode === 'sales'): ?>
                            <option value="draft">Draft</option><?php endif; ?>
                        <option value="pending_ack">For Accounting Head</option>
                        <option value="pending_approval">For Superadmin</option>
                        <option value="pending_receive">For Accounting Staff</option>
                        <option value="pending_release">For release</option>
                        <option value="released">Released</option>
                        <option value="returned">Returned to Sales</option>
                    </select>
<p class="nh-fd-only nh-fd-label">Sort by</p>
                    <select id="spoSort" data-fd-ignore class="spo-sel w-full sm:w-auto min-w-0" title="Sort by">
                        <option value="newest">Sort: Newest</option>
                        <option value="oldest">Sort: Oldest</option>
                        <option value="waiting">Sort: Longest waiting</option>
                        <option value="amount_desc">Sort: Amount high → low</option>
                        <option value="amount_asc">Sort: Amount low → high</option>
                        <option value="vendor">Sort: Vendor A–Z</option>
                    </select>

    </div>
    <div class="nh-fd-only px-4 py-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] border-t border-gray-100 bg-gray-50/60">
        <button type="button" data-fd-close class="w-full text-xs font-semibold bg-amber-600 hover:bg-amber-700 text-white px-4 py-2.5 rounded-lg transition-colors">Show results</button>
    </div>
</div>
                    <span id="spoCount" class="col-span-2 sm:ml-auto text-[11px] text-gray-400 truncate"></span>
                </div>
            </section>

            <div id="spoList" class="space-y-2.5"></div>
        </div>

        <!-- ═══ PO MODAL ═══ -->
        <div id="spoModal"
            class="hidden fixed inset-0 z-[9998] items-start justify-center bg-black/50 p-0 md:p-4 overflow-y-auto">
            <div class="bg-white md:rounded-2xl shadow-xl w-full max-w-7xl min-h-full md:min-h-0 md:my-auto overflow-hidden">
                <div class="sticky top-0 z-10 bg-white flex items-start justify-between gap-3 px-4 md:px-5 py-3 md:py-3.5 border-b border-gray-100">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-amber-700" id="spoMKicker">
                            Purchase Order</p>
                        <p id="spoMTitle" class="text-base font-semibold text-gray-900 truncate"></p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button type="button" id="spoPrint"
                            class="hidden inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-gray-900 rounded-lg hover:bg-gray-800"><i
                                class="fa-solid fa-print"></i><span class="hidden sm:inline"> Print</span></button>
                        <button type="button" id="spoClose"
                            class="w-10 h-10 md:w-8 md:h-8 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 text-2xl md:text-xl leading-none"
                            aria-label="Close">&times;</button>
                    </div>
                </div>
                <div class="grid lg:grid-cols-[minmax(0,1fr)_320px]">
                    <div class="p-3 md:p-5 bg-slate-50 min-w-0 lg:border-r border-gray-100">
                        <p class="md:hidden mb-2 text-[11px] text-gray-400"><i class="fa-solid fa-left-right mr-1"></i>Swipe sideways to see the whole P.O.</p>
                        <div class="overflow-x-auto spo-scroll -mx-3 px-3 md:mx-0 md:px-0">
                            <div id="spoDoc"
                                class="min-w-[760px] bg-white border border-gray-200 rounded-xl p-5 md:p-7 shadow-sm">
                            </div>
                        </div>
                    </div>
                    <div id="spoSide" class="p-4 md:p-5 pb-[calc(1rem+env(safe-area-inset-bottom))] md:pb-5 space-y-4"></div>
                </div>
            </div>
        </div>

        <div id="spoToast"
            class="fixed left-4 right-4 bottom-[calc(1rem+env(safe-area-inset-bottom))] sm:left-auto sm:bottom-4 z-[10000] flex flex-col gap-2.5 pointer-events-none sm:w-[340px]">
        </div>
    </main>

    <script>
        (function () {
            'use strict';
            const MODE = <?= json_encode($spoMode) ?>;
            const AJAX = <?= json_encode($spoAjax) ?>;
            const SIG_PAGE = <?= json_encode(BASE_URL . '/signaturedinsert') ?>;
            const F = id => document.getElementById(id);
            const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
            const money = n => (Number(n) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const fmtDate = v => { if (!v) return ''; const d = new Date(String(v).slice(0, 10) + 'T00:00:00'); return isNaN(d) ? v : d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }); };
            const fmtDT = v => { if (!v) return ''; const d = new Date(String(v).replace(' ', 'T')); return isNaN(d) ? v : d.toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }); };
            const WARN_DAYS = 3;

            let rows = [], tab = 'todo', me = '', mySig = null, cur = null, sigHash = '';
            let dateF = { day: '', month: '', week: '', from: '', to: '' }, statusF = '', sortBy = 'newest';

            function toast(message, type = 'success', duration = 3800) {
                const t = document.createElement('div');
                t.className = `pointer-events-auto flex items-start gap-2.5 border rounded-lg shadow-lg px-4 py-3 text-sm transition-all duration-300 ${type === 'success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700'}`;
                t.innerHTML = `<span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold ${type === 'success' ? 'bg-green-200' : 'bg-red-200'}">${type === 'success' ? '✓' : '!'}</span><span class="flex-1 leading-relaxed"></span>`;
                t.querySelector('.flex-1').textContent = message;
                F('spoToast').appendChild(t);
                setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, duration);
            }
            async function api(action, params = {}, post = false) {
                const body = new URLSearchParams();
                Object.entries(params).forEach(([k, v]) => body.append(k, v ?? ''));
                const url = post ? `${AJAX}?action=${action}` : `${AJAX}?action=${action}&${body}`;
                const res = await fetch(url, post ? { method: 'POST', body, cache: 'no-store' } : { cache: 'no-store' });
                const text = await res.text();
                let d;
                try { d = JSON.parse(text); }
                catch (e) { throw new Error(/<html|<!doctype/i.test(text) ? 'The server returned a page instead of data. Check that "salespoajax" is in the router, or reload if your session expired.' : 'The server sent an unexpected response.'); }
                if (!d.success) throw new Error(d.message || 'Something went wrong.');
                return d;
            }

            // ═══ gaano na katagal ═══
            const ageSec = v => { if (!v) return null; const d = new Date(String(v).replace(' ', 'T')); return isNaN(d) ? null : Math.max(0, Math.floor((Date.now() - d) / 1000)); };
            function ago(v) {
                const s = ageSec(v); if (s === null) return '';
                if (s < 60) return 'just now';
                if (s < 3600) return `${Math.floor(s / 60)}m ago`;
                if (s < 86400) return `${Math.floor(s / 3600)}h ago`;
                const d = Math.floor(s / 86400);
                return d === 1 ? 'yesterday' : d < 30 ? `${d} days ago` : fmtDate(v);
            }
            const WAIT_ON = { draft: 'Sales (draft)', returned: 'Sales (fix & resubmit)', pending_ack: 'Accounting Head', pending_approval: 'Superadmin', pending_receive: 'Accounting Staff', pending_release: 'Custodian / Custodian Asst.' };

            // ═══ filters ═══
            const pad = n => String(n).padStart(2, '0');
            const ymd = d => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
            const dateOn = () => !!(dateF.day || dateF.month || dateF.week || dateF.from || dateF.to);
            function dateOk(r) {
                if (!dateOn()) return true;
                const d = String(r.po_date || '').slice(0, 10);
                if (!d) return false;
                if (dateF.from || dateF.to) return (!dateF.from || d >= dateF.from) && (!dateF.to || d <= dateF.to);
                if (dateF.day) return d === dateF.day;
                if (dateF.month) return d.slice(0, 7) === dateF.month;
                const end = new Date(dateF.week + 'T00:00:00'); end.setDate(end.getDate() + 6);
                return d >= dateF.week && d <= ymd(end);
            }
            const q = () => F('spoSearch').value.trim().toLowerCase();
            const searchOk = r => !q() || `${r.po_no} ${r.vendor ?? ''} ${r.vendor_name ?? ''} ${r.customer ?? ''} ${r.prepared_by ?? ''}`.toLowerCase().includes(q());
            const inTab = (r, k) => k === 'todo' ? r.my_turn : k === 'released' ? r.status === 'released' : k === 'progress' ? (!r.my_turn && r.status !== 'released') : true;
            function sortList(list) {
                const t = v => { const d = v ? new Date(String(v).replace(' ', 'T')) : null; return d && !isNaN(d) ? d.getTime() : 0; };
                const by = {
                    newest: (a, b) => b.id - a.id,
                    oldest: (a, b) => a.id - b.id,
                    waiting: (a, b) => (t(a.waiting_since) || Infinity) - (t(b.waiting_since) || Infinity),
                    amount_desc: (a, b) => b.final_amount - a.final_amount,
                    amount_asc: (a, b) => a.final_amount - b.final_amount,
                    vendor: (a, b) => String(a.vendor || '').localeCompare(String(b.vendor || '')),
                };
                return list.sort(by[sortBy] || by.newest);
            }
            function syncDate() {
                F('spoMonth').value = dateF.month; F('spoDay').value = dateF.day;
                F('spoFrom').value = dateF.from; F('spoTo').value = dateF.to;
                F('spoFrom').max = dateF.to || ''; F('spoTo').min = dateF.from || '';
                const t = ymd(new Date()), sh = v => new Date(v + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric' });
                document.querySelectorAll('.spo-q').forEach(b => {
                    const k = b.dataset.q;
                    b.dataset.on = ((k === 'today' && dateF.day === t) || (k === 'month' && dateF.month === t.slice(0, 7)) || (k === 'week' && !!dateF.week)) ? '1' : '0';
                });
                F('spoDateText').textContent = !dateOn() ? 'Any date' : dateF.day ? (dateF.day === t ? 'Today' : sh(dateF.day)) : dateF.week ? 'This week'
                    : dateF.month ? new Date(dateF.month + '-01T00:00:00').toLocaleDateString('en-PH', { month: 'short', year: 'numeric' })
                        : dateF.from && dateF.to ? `${sh(dateF.from)} – ${sh(dateF.to)}` : dateF.from ? `From ${sh(dateF.from)}` : `Until ${sh(dateF.to)}`;
                F('spoDateX').classList.toggle('hidden', !dateOn());
                F('spoDateBtn').classList.toggle('border-amber-600', dateOn());
                F('spoDateBtn').classList.toggle('bg-amber-50', dateOn());
            }
            function setDate(next) { dateF = Object.assign({ day: '', month: '', week: '', from: '', to: '' }, next); syncDate(); render(); }

            // ═══ list ═══
            const STATUS_PILL = s => { const t = SalesPO.STATUS_TONE[s] || SalesPO.STATUS_TONE.draft; return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-semibold border" style="color:${t[1]};background:${t[2]};border-color:${t[1]}33">${t[0]}</span>`; };
            function render() {
                const base = rows.filter(r => dateOk(r) && (!statusF || r.status === statusF) && searchOk(r));
                document.querySelectorAll('.spo-tab').forEach(b => {
                    const l = base.filter(r => inTab(r, b.dataset.tab));
                    b.dataset.active = b.dataset.tab === tab ? '1' : '0';
                    b.querySelector('.spo-tab-count').textContent = l.length;
                    b.querySelector('.spo-tab-sum').textContent = l.length ? '₱' + money(l.reduce((a, r) => a + r.final_amount, 0)) : '';
                });
                const list = sortList(base.filter(r => inTab(r, tab)));
                F('spoCount').textContent = `${list.length} purchase order${list.length === 1 ? '' : 's'}`;
                if (!list.length) {
                    const msg = (dateOn() || statusF || q()) ? 'No purchase orders match the filters.'
                        : tab === 'todo' ? (MODE === 'sales' ? 'Nothing to do. Create a new Purchase Order to start.' : 'All caught up. Nothing is waiting for your signature.') : 'No purchase orders here.';
                    F('spoList').innerHTML = `<div class="bg-white border-2 border-dashed border-gray-200 rounded-xl px-6 py-12 text-center"><div class="w-12 h-12 mx-auto rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center mb-2"><i class="fa-solid ${tab === 'todo' && !dateOn() ? 'fa-mug-hot' : 'fa-file-circle-question'}"></i></div><p class="text-sm font-semibold text-gray-700">${msg}</p></div>`;
                    return;
                }
                F('spoList').innerHTML = list.map(r => {
                    const bar = (SalesPO.STATUS_TONE[r.status] || SalesPO.STATUS_TONE.draft)[1];
                    const since = r.status === 'released' ? r.released_at : r.status === 'returned' ? r.returned_at : (r.waiting_since || r.updated_at);
                    const s = ageSec(since);
                    const late = r.my_turn && r.status !== 'draft' && s !== null && s >= WARN_DAYS * 86400;
                    const when = r.status === 'released' ? `<span class="text-emerald-700"><i class="fa-solid fa-circle-check mr-1"></i>Released ${ago(since)}</span>`
                        : r.status === 'returned' ? `<span class="text-red-700"><i class="fa-solid fa-rotate-left mr-1"></i>Returned ${ago(since)} · ${esc(r.return_remarks || '')}</span>`
                            : r.status === 'draft' ? `<span class="text-gray-500"><i class="fa-regular fa-pen-to-square mr-1"></i>Draft · edited ${ago(r.updated_at)}</span>`
                                : late ? `<span class="text-red-600 font-semibold"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Waiting for ${WAIT_ON[r.status]} · ${ago(since)}</span>`
                                    : `<span class="${s !== null && s < 3600 ? 'text-green-600 font-semibold' : 'text-gray-500'}"><i class="fa-regular fa-clock mr-1"></i>Waiting for ${WAIT_ON[r.status]} · ${ago(since)}</span>`;
                    const btn = r.my_turn
                        ? `<span class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg shadow-sm"><i class="fa-solid ${MODE === 'sales' ? 'fa-pen' : 'fa-pen-nib'}"></i> ${MODE === 'sales' ? (r.status === 'returned' ? 'Fix & resubmit' : 'Continue') : 'Review & sign'}</span>`
                        : '<span class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg"><i class="fa-regular fa-eye"></i> View</span>';
                    return `<button type="button" data-id="${r.id}" class="spo-row relative w-full text-left bg-white border border-gray-200 rounded-xl overflow-hidden hover:border-gray-300 hover:shadow-sm transition">
                        <span class="absolute left-0 top-0 bottom-0 w-1" style="background:${bar}"></span>
                        <span class="flex flex-wrap items-center gap-x-5 gap-y-2.5 pl-4 sm:pl-5 pr-3 sm:pr-4 py-3 sm:py-3.5">
                            <span class="min-w-0 flex-1 basis-full sm:basis-[260px]">
                                <span class="flex flex-wrap items-center gap-2">
                                    ${r.my_turn ? '<span class="px-1.5 py-0.5 rounded text-[9.5px] font-bold uppercase tracking-wide bg-amber-700 text-white">Your turn</span>' : ''}
                                    <span class="font-mono text-[12px] font-bold text-gray-900">${esc(r.po_no)}</span>${STATUS_PILL(r.status)}
                                </span>
                                <span class="block text-sm font-semibold text-gray-800 truncate mt-0.5">${esc(r.vendor || '— no vendor yet —')}</span>
                                <span class="block text-[11px] line-clamp-2 sm:line-clamp-none sm:truncate mt-0.5" title="${esc(fmtDT(since))}">${when}</span>
                            </span>
                            <span class="grid grid-cols-3 gap-x-3 sm:gap-x-5 text-[11px] basis-full sm:basis-[300px] grow sm:grow-0 rounded-lg bg-gray-50 sm:bg-transparent border border-gray-100 sm:border-0 px-3 py-2 sm:p-0">
                                <span><span class="block text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">P.O. date</span><span class="text-gray-700">${fmtDate(r.po_date)}</span></span>
                                <span class="min-w-0"><span class="block text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Prepared by</span><span class="text-gray-700 truncate block">${esc(r.prepared_by || '—')}</span></span>
                                <span><span class="block text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Items</span><span class="text-gray-700">${r.item_count}</span></span>
                            </span>
                            <span class="w-full sm:w-auto sm:ml-auto shrink-0 flex items-center justify-between sm:justify-start gap-3">
                                <span class="text-left sm:text-right"><span class="block text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Final amount</span><span class="font-mono text-sm font-bold text-gray-900">₱${money(r.final_amount)}</span></span>
                                ${btn}
                            </span>
                        </span>
                    </button>`;
                }).join('');
            }

            async function load(silent) {
                try {
                    const d = await api('list');
                    me = d.me || ''; mySig = d.my_signature || null;
                    const h = JSON.stringify(d.rows.map(r => [r.id, r.status, r.updated_at]));
                    if (silent && h === sigHash) return;
                    if (silent && sigHash && MODE === 'approver') {
                        const known = new Set(rows.filter(r => r.my_turn).map(r => r.id));
                        const fresh = d.rows.filter(r => r.my_turn && !known.has(r.id)).length;
                        if (fresh) toast(`${fresh} new Purchase Order${fresh === 1 ? '' : 's'} waiting for your signature.`);
                    }
                    sigHash = h; rows = d.rows; render();
                } catch (e) {
                    if (silent) return;
                    F('spoList').innerHTML = `<div class="bg-white border border-red-200 rounded-xl px-6 py-10 text-center text-xs text-red-600">${esc(e.message)} <button type="button" id="spoRetry" class="font-semibold underline ml-1">Retry</button></div>`;
                    F('spoRetry').addEventListener('click', () => load());
                }
            }

            // ═══ modal ═══
            const openM = () => { F('spoModal').classList.remove('hidden'); F('spoModal').classList.add('flex'); document.body.style.overflow = 'hidden'; };
            const closeM = () => { F('spoModal').classList.add('hidden'); F('spoModal').classList.remove('flex'); document.body.style.overflow = ''; cur = null; };
            F('spoClose').addEventListener('click', closeM);
            F('spoModal').addEventListener('click', e => { if (e.target === F('spoModal')) closeM(); });
            document.addEventListener('keydown', e => { if (e.key === 'Escape' && cur) closeM(); });
            F('spoPrint').addEventListener('click', () => { if (cur) SalesPO.print(cur.po); });
            F('spoList').addEventListener('click', e => { const b = e.target.closest('.spo-row'); if (b) openPO(+b.dataset.id); });
            if (F('spoNew')) F('spoNew').addEventListener('click', () => openPO(0));

            async function openPO(id, quiet) {
                let d;
                try { d = await api('get', { id }); } catch (e) { return quiet ? null : toast(e.message, 'error'); }
                if (quiet && (!cur || cur.po.id !== id || busy())) return;
                cur = d;
                const po = d.po;
                F('spoMKicker').textContent = d.can_edit ? (po.id ? 'Edit Purchase Order' : 'New Purchase Order') : d.can_sign ? `${d.sign_label} · your signature` : 'Purchase Order';
                F('spoMTitle').textContent = po.id ? `${po.po_no} · ${po.vendor_company || po.vendor_name || 'No vendor yet'}` : 'New Purchase Order';
                F('spoPrint').classList.toggle('hidden', !po.id || d.can_edit);
                const col = { pending_ack: 'ack', pending_approval: 'approved', pending_receive: 'received', pending_release: 'released' }[po.status];
                SalesPO.render(F('spoDoc'), po, d.can_edit ? { edit: true, mySig: d.my_signature }
                    : d.can_sign ? (d.my_signature ? { preview: { col, url: d.my_signature, name: d.me } } : { here: col }) : {});
                renderSide();
                openM();
            }

            function timeline(po) {
                const steps = [
                    ['Prepared by', po.prepared_by, 'Sales', po.submitted_at],
                    ['Acknowledged by', po.ack_by, 'Accounting Head', po.ack_at],
                    ['Approved by', po.approved_by, 'Superadmin', po.approved_at],
                    ['Received by', po.received_by, 'Accounting Staff', po.received_at],
                    ['Released by', po.released_by, po.released_title || 'Custodian / Custodian Asst.', po.released_at],
                ];
                const nextIdx = steps.findIndex(s => !s[3]);
                return `<section class="rounded-xl border border-gray-200 overflow-hidden">
                    <p class="px-4 py-2.5 border-b border-gray-100 text-[10.5px] font-bold uppercase tracking-wider text-gray-500"><i class="fa-solid fa-signature mr-1 text-amber-700"></i>Signatures</p>
                    <ol class="px-4 py-3 space-y-3">${steps.map(([l, n, role, at], i) => {
                    const done = !!at, next = !done && i === nextIdx && po.status !== 'draft' && po.status !== 'returned';
                    return `<li class="flex items-start gap-3">
                            <span class="w-7 h-7 rounded-full shrink-0 flex items-center justify-center text-[11px] ${done ? 'bg-emerald-600 text-white' : next ? 'bg-amber-500 text-white ring-4 ring-amber-100' : 'bg-gray-100 text-gray-400'}"><i class="fa-solid ${done ? 'fa-check' : next ? 'fa-hourglass-half' : 'fa-pen-nib'}"></i></span>
                            <span class="min-w-0"><span class="block text-[12px] font-semibold text-gray-800">${l} <span class="font-normal text-gray-400">· ${esc(role)}</span></span>
                            <span class="block text-[11px] ${done ? 'text-gray-500' : next ? 'text-amber-700 font-semibold' : 'text-gray-400'}">${done ? `${esc(n || '')} · ${fmtDT(at)} <span class="text-gray-400">(${ago(at)})</span>` : next ? 'Waiting now' : 'Not yet'}</span></span></li>`;
                }).join('')}</ol></section>`;
            }

            function sigBox() {
                return mySig
                    ? `<div class="rounded-xl border border-gray-200 bg-white p-3 flex items-center justify-center h-20"><img src="${esc(mySig)}" alt="Your signature" class="max-h-full max-w-full object-contain"></div><p class="text-[11px] text-gray-500 mt-1.5">Signing as <b class="text-gray-800">${esc(me)}</b>.</p>`
                    : `<div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900"><p class="font-bold"><i class="fa-solid fa-triangle-exclamation mr-1"></i>No e-signature set yet</p><p class="mt-0.5">Set the same signature you use in Budget Requests, then come back.</p><a href="${SIG_PAGE}" class="inline-flex items-center gap-1 mt-1.5 font-semibold text-amber-800 hover:underline">Go to Insert Signature <i class="fa-solid fa-arrow-right text-[10px]"></i></a></div>`;
            }

            const busy = () => !!(F('spoYes') || F('spoRetBox')?.open || F('spoSubmitYes') || (cur && cur.can_edit));

            function renderSide() {
                const d = cur, po = d.po, side = F('spoSide');
                const ret = po.status === 'returned' && po.return_remarks
                    ? `<div class="rounded-xl border border-red-200 bg-red-50 px-3.5 py-2.5 text-[12px] text-red-800"><p class="font-bold"><i class="fa-solid fa-rotate-left mr-1"></i>Returned by ${esc(po.returned_by || '')}</p><p class="mt-0.5 whitespace-pre-line">${esc(po.return_remarks)}</p><p class="text-[10.5px] text-red-600 mt-1">${fmtDT(po.returned_at)}</p></div>` : '';

                // ── Sales: gumagawa / nag-e-edit ──
                if (d.can_edit) {
                    side.innerHTML = `${ret}
                        <div class="rounded-xl border border-amber-200 bg-amber-50/60 px-3.5 py-3 text-[12px] text-amber-900"><p class="font-bold"><i class="fa-solid fa-circle-info mr-1"></i>How it works</p>
                            <ol class="mt-1 list-decimal ml-4 space-y-0.5 text-[11.5px]"><li>Fill in the vendor and the items on the form.</li><li>Save as draft anytime.</li><li>Submit — it goes to the Accounting Head, then Superadmin, Accounting Staff and Custodian.</li></ol></div>
                        <section><p class="text-[11px] font-bold uppercase tracking-wider text-gray-500 mb-2"><i class="fa-solid fa-signature mr-1 text-amber-700"></i>Your signature (Prepared by)</p>${sigBox()}</section>
                        <p id="spoErr" class="hidden rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-[11px] text-red-700"></p>
                        <div id="spoActs" class="space-y-2">
                            <button type="button" id="spoSubmit" ${mySig ? '' : 'disabled'} class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-amber-700 rounded-xl hover:bg-amber-800 shadow-sm disabled:bg-gray-200 disabled:text-gray-400 disabled:shadow-none disabled:cursor-not-allowed"><i class="fa-solid fa-paper-plane"></i> ${po.status === 'returned' ? 'Resubmit' : 'Submit'}</button>
                            <button type="button" id="spoSave" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50"><i class="fa-regular fa-floppy-disk"></i> Save draft</button>
                            ${po.id && po.status === 'draft' ? '<button type="button" id="spoDel" class="w-full text-[11px] font-semibold text-red-600 hover:underline py-1">Delete this draft</button>' : ''}
                        </div>
                        ${po.id ? timeline(po) : ''}`;
                    const err = m => { F('spoErr').textContent = m || ''; F('spoErr').classList.toggle('hidden', !m); };
                    const payload = () => { const f = SalesPO.collect(F('spoDoc')); return { ...f, items: JSON.stringify(f.items), id: po.id || 0 }; };
                    F('spoSave').addEventListener('click', async function () {
                        err(''); this.disabled = true;
                        try { const r = await api('save', payload(), true); toast(r.message); await load(); openPO(r.id); }
                        catch (e) { err(e.message); } finally { this.disabled = false; }
                    });
                    F('spoSubmit').addEventListener('click', () => {
                        err('');
                        const f = SalesPO.collect(F('spoDoc'));
                        if (!f.items.length) return err('Add at least one item first.');
                        const c = SalesPO.calc(f.items, f.discount_pct, f.addl_discount, f.down_payment);
                        F('spoActs').innerHTML = `<div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
                            <p class="text-xs text-amber-900">Submit this Purchase Order (<b>₱${money(c.final)}</b>, ${f.items.length} item${f.items.length === 1 ? '' : 's'})? Your signature goes on <b>Prepared by</b> and it goes to the Accounting Head. You can't edit it unless it is returned.</p>
                            <div class="flex gap-2 mt-2"><button type="button" id="spoSubmitYes" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800"><i class="fa-solid fa-check"></i> Yes, submit</button>
                            <button type="button" id="spoSubmitNo" class="px-3 py-2 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg">Cancel</button></div></div>`;
                        F('spoSubmitNo').addEventListener('click', renderSide);
                        F('spoSubmitYes').addEventListener('click', async function () {
                            this.disabled = true; this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting…';
                            try { const r = await api('submit', payload(), true); toast(r.message); await load(); openPO(r.id); }
                            catch (e) { renderSide(); F('spoErr').textContent = e.message; F('spoErr').classList.remove('hidden'); }
                        });
                    });
                    if (F('spoDel')) F('spoDel').addEventListener('click', function () {
                        this.outerHTML = `<div id="spoDelBox" class="rounded-lg border border-red-200 bg-red-50 p-2.5 text-[11.5px] text-red-800">Delete this draft for good?
                            <div class="flex gap-2 mt-1.5"><button type="button" id="spoDelYes" class="px-3 py-1.5 font-semibold text-white bg-red-600 rounded-lg">Delete</button><button type="button" id="spoDelNo" class="px-3 py-1.5 font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg">Keep</button></div></div>`;
                        F('spoDelNo').addEventListener('click', renderSide);
                        F('spoDelYes').addEventListener('click', async () => {
                            try { const r = await api('delete', { id: po.id }, true); toast(r.message); closeM(); load(); } catch (e) { toast(e.message, 'error'); }
                        });
                    });
                    return;
                }

                // ── Pipirma (Accounting Head / Superadmin / Staff / Custodian) ──
                if (d.can_sign) {
                    side.innerHTML = `${po.returned_at && po.return_remarks ? `<div class="rounded-xl border border-purple-200 bg-purple-50 px-3.5 py-2.5 text-[11px] text-purple-800"><p class="font-bold"><i class="fa-solid fa-rotate mr-1"></i>Resubmitted after it was returned:</p><p class="mt-0.5">${esc(po.return_remarks)}</p></div>` : ''}
                        <section><p class="text-[11px] font-bold uppercase tracking-wider text-gray-500 mb-2"><i class="fa-solid fa-signature mr-1 text-amber-700"></i>Your signature (${esc(d.sign_label)})</p>${sigBox()}</section>
                        <p id="spoErr" class="hidden rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-[11px] text-red-700"></p>
                        <div id="spoSignArea"></div>
                        <details id="spoRetBox" class="group rounded-xl border border-gray-200">
                            <summary class="list-none cursor-pointer flex items-center justify-between px-3.5 py-2.5 text-xs font-semibold text-red-700"><span><i class="fa-solid fa-rotate-left mr-1"></i>Return to Sales</span><i class="fa-solid fa-chevron-down text-[10px] text-gray-400 group-open:rotate-180 transition"></i></summary>
                            <div class="px-3.5 pb-3.5"><textarea id="spoRem" rows="3" maxlength="1000" placeholder="What should Sales fix? e.g. Wrong unit price for item 2." class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs outline-none focus:border-red-400 focus:ring-2 focus:ring-red-100 resize-y"></textarea>
                                <p id="spoRemErr" class="hidden text-[11px] text-red-600 mt-1"></p>
                                <button type="button" id="spoRet" class="mt-2 w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 disabled:opacity-60"><i class="fa-solid fa-paper-plane"></i> Send back to Sales</button></div>
                        </details>
                        ${timeline(po)}`;
                    const err = m => { F('spoErr').textContent = m || ''; F('spoErr').classList.toggle('hidden', !m); };
                    const signBtn = () => {
                        F('spoSignArea').innerHTML = `<button type="button" id="spoSign" ${mySig ? '' : 'disabled'} class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-amber-700 rounded-xl hover:bg-amber-800 shadow-sm disabled:bg-gray-200 disabled:text-gray-400 disabled:shadow-none disabled:cursor-not-allowed"><i class="fa-solid fa-file-signature"></i> Sign (${esc(d.sign_label)})</button>`;
                        F('spoSign').addEventListener('click', ask);
                    };
                    function ask() {
                        err('');
                        F('spoSignArea').innerHTML = `<div class="rounded-xl border border-amber-200 bg-amber-50 p-3"><p class="text-xs text-amber-900">Sign <b>${esc(po.po_no)}</b> (₱${money(po.final_amount)}) as <b>${esc(d.sign_label)}</b>?</p>
                            <div class="flex gap-2 mt-2"><button type="button" id="spoYes" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800"><i class="fa-solid fa-check"></i> Yes, sign</button>
                            <button type="button" id="spoNo" class="px-3 py-2 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg">Cancel</button></div></div>`;
                        F('spoNo').addEventListener('click', signBtn);
                        F('spoYes').addEventListener('click', async function () {
                            this.disabled = true; this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Signing…';
                            try { const r = await api('sign', { id: po.id }, true); toast(r.message); await load(); openPO(po.id); }
                            catch (e) { signBtn(); err(e.message); load(true); }
                        });
                    }
                    signBtn();
                    const rk = 'nh-spo-rem-' + po.id;
                    try { F('spoRem').value = localStorage.getItem(rk) || ''; } catch (e) { }
                    if (F('spoRem').value) F('spoRetBox').open = true;
                    F('spoRem').addEventListener('input', () => { try { localStorage.setItem(rk, F('spoRem').value); } catch (e) { } F('spoRemErr').classList.add('hidden'); });
                    F('spoRet').addEventListener('click', async function () {
                        const rem = F('spoRem').value.trim();
                        if (rem.length < 3) { F('spoRemErr').textContent = 'Tell Sales what to fix.'; F('spoRemErr').classList.remove('hidden'); F('spoRem').focus(); return; }
                        this.disabled = true;
                        try { const r = await api('return', { id: po.id, remarks: rem }, true); try { localStorage.removeItem(rk); } catch (e) { } toast(r.message); closeM(); load(); }
                        catch (e) { F('spoRemErr').textContent = e.message; F('spoRemErr').classList.remove('hidden'); }
                        finally { this.disabled = false; }
                    });
                    return;
                }

                // ── Tingin lang ──
                const st = SalesPO.STATUS_TONE[po.status] || SalesPO.STATUS_TONE.draft;
                const wait = WAIT_ON[po.status];
                side.innerHTML = `${ret}
                    <div class="rounded-xl border p-3.5 text-xs" style="border-color:${st[1]}33;background:${st[2]};color:${st[1]}">
                        <p class="font-bold text-sm">${st[0]}</p>
                        <p class="mt-0.5">${po.status === 'released' ? `Released by ${esc(po.released_by || '')} on ${fmtDT(po.released_at)}.` : wait ? `Waiting for ${esc(wait)}.` : ''}</p></div>
                    <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-[11px] bg-gray-50 border border-gray-100 rounded-xl p-3">
                        <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Final amount</dt><dd class="font-mono font-bold text-gray-900">₱${money(po.final_amount)}</dd></div>
                        <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Balance</dt><dd class="font-mono text-gray-800">₱${money(po.balance)}</dd></div>
                        <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Items</dt><dd class="text-gray-800">${(po.items || []).length}</dd></div>
                        <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Prepared by</dt><dd class="text-gray-800 truncate">${esc(po.prepared_by || '')}</dd></div>
                    </dl>
                    ${timeline(po)}`;
            }

            // ═══ events ═══
            document.querySelectorAll('.spo-tab').forEach(b => b.addEventListener('click', () => { tab = b.dataset.tab; try { sessionStorage.setItem('nh-spo-tab-' + MODE, tab); } catch (e) { } render(); }));
            try { const t = sessionStorage.getItem('nh-spo-tab-' + MODE); if (t !== null) tab = t; } catch (e) { }
            F('spoSearch').addEventListener('input', render);
            F('spoStatus').addEventListener('change', e => { statusF = e.target.value; render(); });
            F('spoSort').addEventListener('change', e => { sortBy = e.target.value; render(); });
            document.querySelectorAll('.spo-q').forEach(b => b.addEventListener('click', () => {
                const now = new Date(), k = b.dataset.q;
                if (k === 'today') { const t = ymd(now); setDate(dateF.day === t ? {} : { day: t }); }
                else if (k === 'month') { const m = ymd(now).slice(0, 7); setDate(dateF.month === m ? {} : { month: m }); }
                else { const mon = new Date(now); mon.setDate(now.getDate() - ((now.getDay() + 6) % 7)); setDate(dateF.week ? {} : { week: ymd(mon) }); }
            }));
            F('spoMonth').addEventListener('change', e => setDate(e.target.value ? { month: e.target.value } : {}));
            F('spoDay').addEventListener('change', e => setDate(e.target.value ? { day: e.target.value } : {}));
            F('spoFrom').addEventListener('change', e => setDate({ from: e.target.value, to: dateF.to }));
            F('spoTo').addEventListener('change', e => setDate({ from: dateF.from, to: e.target.value }));
            F('spoDateClear').addEventListener('click', () => setDate({}));
            (function () {
                const btn = F('spoDateBtn'), pop = F('spoDatePop');
                const show = on => pop.classList.toggle('hidden', !on);
                btn.addEventListener('click', e => { if (e.target.closest('#spoDateX')) { setDate({}); show(false); return; } show(pop.classList.contains('hidden')); });
                F('spoDateDone').addEventListener('click', () => show(false));
                document.addEventListener('click', e => { if (!e.target.closest('#spoDateWrap')) show(false); });
            })();
            setInterval(() => { if (!document.hidden && !cur) render(); }, 60000);

            // ═══ realtime + polling ═══
            let poll = null;
            const live = () => !!(window.NH_RT && window.NH_RT.enabled && window.NH_RT.isLive());
            function startPoll() { clearInterval(poll); poll = setInterval(() => { if (!document.hidden) load(true); }, live() ? 60000 : 20000); }
            function sync() {
                if (document.hidden) return;
                load(true);
                if (cur && cur.po.id && !busy()) openPO(cur.po.id, true);
            }
            (function hook() {
                const h = () => {
                    if (!window.NH_RT || !window.NH_RT.enabled || hook.done) return;
                    hook.done = true;
                    let t = null;
                    window.NH_RT.on('*', 'changed', p => { const ty = String(p?.type || ''); if (ty && !ty.startsWith('spo_')) return; clearTimeout(t); t = setTimeout(sync, 400); });
                    window.NH_RT.onLive(() => startPoll());
                };
                h();
                if (!hook.done) document.addEventListener('DOMContentLoaded', h);
            })();
            document.addEventListener('visibilitychange', () => { if (!document.hidden) sync(); });

            syncDate();
            load().then(() => {
                startPoll();
                const open = new URLSearchParams(location.search).get('po');
                if (open && /^\d+$/.test(open)) openPO(+open);
                else if (MODE === 'sales' && new URLSearchParams(location.search).get('new') === '1') openPO(0);
            });
        })();
    </script>

<style>
    /* ═══ Filter drawer sa phone (kagaya ng CRM Monitoring) · sa sm+ walang epekto (display: contents) ═══ */
    .nh-fd-backdrop { display: none; position: fixed; inset: 0; background: rgba(17, 24, 39, .4); z-index: 9998; }
    @media (max-width: 639px) {
        .nh-fd { position: fixed; top: 0; bottom: 0; left: 0; width: 300px; max-width: 88vw; z-index: 9999; background: #fff;
            display: flex; flex-direction: column; transform: translateX(-105%); transition: transform .22s ease;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .18); }
        .nh-fd.open { transform: none; }
        .nh-fd-backdrop.open { display: block; }
        .nh-fd-body { flex: 1; overflow-y: auto; padding: 14px 16px 18px; display: flex; flex-direction: column; gap: 14px; }
        .nh-fd-body > * { width: 100%; }
        .nh-fd-label { font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #374151; margin-bottom: -8px; }
    }
    @media (min-width: 640px) {
        .nh-fd, .nh-fd-body { display: contents; }
        .nh-fd-only { display: none !important; }
    }
    @media (max-width: 639px) {
        #spoFd #spoDateBtn, #spoFd #spoDateDone { display: none !important; }
        #spoFd #spoDatePop { display: block !important; position: static; width: auto; max-width: none; margin: 0; padding: 0; border: 0; box-shadow: none; }
    }
</style>

<script>
    // Filter drawer sa phone — bubuksan ng [data-fd-open="id"]; badge = ilang filter ang naka-on
    (function () {
        const isPhone = () => !window.matchMedia('(min-width: 640px)').matches;
        function setup(d) {
            const bd = document.getElementById(d.id + 'Backdrop');
            const open = () => { d.classList.add('open'); bd && bd.classList.add('open'); document.body.style.overflow = 'hidden'; };
            const close = () => { if (!d.classList.contains('open')) return; d.classList.remove('open'); bd && bd.classList.remove('open'); document.body.style.overflow = ''; };
            d._fdOpen = open;
            bd && bd.addEventListener('click', close);
            d.querySelectorAll('[data-fd-close]').forEach(b => b.addEventListener('click', close));
            document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
            window.addEventListener('resize', () => { if (!isPhone()) close(); });
            const badges = document.querySelectorAll(`[data-fd-open="${d.id}"] [data-fd-badge]`);
            const count = () => {
                let n = 0;
                d.querySelectorAll('input, select').forEach(el => {
                    if (el.type === 'hidden' || el.hasAttribute('data-fd-ignore')) return;
                    if (el.tagName === 'SELECT' ? el.selectedIndex > 0 : (el.type === 'checkbox' ? el.checked : !!el.value)) n++;
                });
                badges.forEach(b => { b.textContent = n; b.classList.toggle('hidden', !n); });
            };
            ['change', 'input'].forEach(ev => d.addEventListener(ev, count));
            d.addEventListener('click', () => setTimeout(count, 0));
            setTimeout(count, 0);
        }
        document.querySelectorAll('.nh-fd').forEach(setup);
        document.querySelectorAll('[data-fd-open]').forEach(btn => btn.addEventListener('click', () => {
            const d = document.getElementById(btn.dataset.fdOpen);
            if (d && d._fdOpen) d._fdOpen();
        }));
        window.nhFdOpen = id => { const d = document.getElementById(id); if (d && d._fdOpen) d._fdOpen(); };
    })();
</script>
</body>

</html>