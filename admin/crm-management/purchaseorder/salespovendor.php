<?php
// salespovendor.php (route: salespovendor) — Purchase Order kada supplier / company
//   Pumili ng supplier (hal. Shera) → lahat ng PO nila, naka-filter kada taon at buwan
//   Data: salespoajax?action=list (walang bagong table) · View ng PO: salespoajax?action=get

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';

$allowedRoles = [ROLE_SALES, ROLE_ACCOUNTING, ROLE_SUPERADMIN];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

// Accounting: Head, Staff, Custodian at Custodian Assistant lang (kapareho ng salespoapproval)
$svPos = $_SESSION['position'] ?? '';
if (($_SESSION['role'] ?? '') === ROLE_ACCOUNTING
    && !in_array($svPos, [POSITION_HEAD, POSITION_STAFF, POSITION_CUSTODIAN, POSITION_CUSTOASSISTANT], true)) {
    http_response_code(403);
    exit('Access denied.');
}

$svAjax = BASE_URL . '/salespoajax';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PO by Supplier</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <?php include ROOT_PATH . '/admin/crm-management/purchaseorder/salespo-doc.php'; ?>
    <style>
        .sv-hazard { background: repeating-linear-gradient(-45deg, #f59e0b 0 10px, #1f2937 10px 20px); }
        .sv-sel, .sv-inp { height: 36px; font-size: 12px; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; outline: none; }
        .sv-sel { padding: 0 30px 0 12px; font-weight: 500; color: #374151; }
        .sv-inp { width: 100%; padding: 0 10px 0 2rem; }
        .sv-sel:focus, .sv-inp:focus { border-color: #b45309; box-shadow: 0 0 0 3px #fef3c7; }
        .sv-vendor[data-on="1"] { background: #fffbeb; border-color: #b45309; box-shadow: inset 3px 0 0 #b45309; }
        .sv-month[data-on="1"] { background: #b45309; border-color: #b45309; color: #fff; }
        .sv-month[data-on="1"] .sv-msub { color: #fde68a; }
        .sv-month:disabled { opacity: .45; cursor: default; }
        .sv-bar[data-on="1"] .sv-fill { background: #b45309; }
        .sv-scroll { scrollbar-width: thin; scrollbar-color: #d1d5db transparent; }
    </style>
</head>

<body class="bg-slate-100">
    <main id="main-content" class="md:ml-56 min-h-screen pt-20 md:pt-8 p-4 md:p-8 overflow-x-hidden">
        <div class="max-w-7xl mx-auto">

            <!-- ═══ HEADER ═══ -->
            <section class="bg-white border border-gray-200 rounded-xl mb-4">
                <div class="sv-hazard h-1.5 rounded-t-xl"></div>
                <div class="p-5 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-truck-ramp-box text-lg"></i></span>
                        <div class="min-w-0">
                            <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase">Purchase Order · Report</p>
                            <h1 class="text-gray-900 text-lg font-semibold leading-tight">PO by Supplier</h1>
                            <p class="text-gray-400 text-[11px] mt-0.5">Pick a supplier to see all of its Purchase Orders by year and month.</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <select id="svStatus" class="sv-sel" title="Status">
                            <option value="all">All submitted (no drafts)</option>
                            <option value="released">Released only</option>
                            <option value="ongoing">Still in signing</option>
                        </select>
                    </div>
                </div>
            </section>

            <div class="grid lg:grid-cols-[300px_minmax(0,1fr)] gap-4 items-start">

                <!-- ═══ LEFT: SUPPLIERS ═══ -->
                <aside class="bg-white border border-gray-200 rounded-xl overflow-hidden lg:sticky lg:top-6">
                    <div class="p-3 border-b border-gray-100">
                        <div class="relative">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[11px]"></i>
                            <input id="svVendorSearch" type="text" placeholder="Search supplier…" class="sv-inp">
                        </div>
                        <div class="flex items-center justify-between mt-2 text-[10.5px] text-gray-400">
                            <span id="svVendorCount"></span>
                            <select id="svVendorSort" class="text-[10.5px] font-semibold text-gray-500 bg-transparent outline-none cursor-pointer">
                                <option value="amount">Highest amount</option>
                                <option value="count">Most POs</option>
                                <option value="name">Name A–Z</option>
                                <option value="recent">Latest PO</option>
                            </select>
                        </div>
                    </div>
                    <div id="svVendors" class="max-h-[70vh] overflow-y-auto sv-scroll p-2 space-y-1.5"></div>
                </aside>

                <!-- ═══ RIGHT: DETAIL ═══ -->
                <section id="svDetail" class="min-w-0"></section>
            </div>
        </div>

        <!-- ═══ PO MODAL (view only) ═══ -->
        <div id="svModal" class="hidden fixed inset-0 z-[9998] items-start justify-center bg-black/50 p-2 md:p-4 overflow-y-auto">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-5xl my-auto overflow-hidden">
                <div class="flex items-center justify-between gap-3 px-5 py-3.5 border-b border-gray-100">
                    <div class="min-w-0">
                        <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-amber-700">Purchase Order</p>
                        <p id="svMTitle" class="text-base font-semibold text-gray-900 truncate"></p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button type="button" id="svPrint" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-gray-900 rounded-lg hover:bg-gray-800"><i class="fa-solid fa-print"></i> Print</button>
                        <button type="button" id="svClose" class="w-8 h-8 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 text-xl leading-none" aria-label="Close">&times;</button>
                    </div>
                </div>
                <div class="p-3 md:p-5 bg-slate-50">
                    <div class="overflow-x-auto sv-scroll">
                        <div id="svDoc" class="min-w-[760px] bg-white border border-gray-200 rounded-xl p-5 md:p-7 shadow-sm"></div>
                    </div>
                </div>
            </div>
        </div>

        <div id="svToast" class="fixed bottom-4 right-4 z-[10000] flex flex-col gap-2.5 pointer-events-none w-[340px] max-w-[calc(100vw-2rem)]"></div>
    </main>

    <script>
        (function () {
            'use strict';
            const AJAX = <?= json_encode($svAjax) ?>;
            const F = id => document.getElementById(id);
            const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
            const money = n => (Number(n) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const short = n => { n = Number(n) || 0; return n >= 1e6 ? (n / 1e6).toFixed(n >= 1e7 ? 1 : 2).replace(/\.?0+$/, '') + 'M' : n >= 1e3 ? (n / 1e3).toFixed(n >= 1e4 ? 0 : 1).replace(/\.0$/, '') + 'K' : money(n); };
            const fmtDate = v => { if (!v) return ''; const d = new Date(String(v).slice(0, 10) + 'T00:00:00'); return isNaN(d) ? v : d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }); };
            const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
            const MONTHS_FULL = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

            let all = [];                       // lahat ng PO galing salespoajax (walang draft)
            let sel = { vendor: '', year: '', month: '' };   // month: '' = buong taon, '01'..'12'
            let statusF = 'all', vSort = 'amount';

            function toast(message, type = 'success') {
                const t = document.createElement('div');
                t.className = `pointer-events-auto border rounded-lg shadow-lg px-4 py-3 text-sm transition-all duration-300 ${type === 'success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700'}`;
                t.textContent = message;
                F('svToast').appendChild(t);
                setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, 3800);
            }
            async function api(action, params = {}) {
                const res = await fetch(`${AJAX}?action=${action}&${new URLSearchParams(params)}`, { cache: 'no-store' });
                const text = await res.text();
                let d;
                try { d = JSON.parse(text); }
                catch (e) { throw new Error(/<html|<!doctype/i.test(text) ? 'The server returned a page instead of data. Reload if your session expired.' : 'The server sent an unexpected response.'); }
                if (!d.success) throw new Error(d.message || 'Something went wrong.');
                return d;
            }

            // ═══ helpers ═══
            const vName = r => String(r.vendor || r.vendor_name || '').trim();
            const vKey = name => name.toLowerCase().replace(/\s+/g, ' ').trim() || '__none__';
            const yearOf = r => String(r.po_date || '').slice(0, 4);
            const monOf = r => String(r.po_date || '').slice(5, 7);
            const statusOk = r => statusF === 'released' ? r.status === 'released'
                : statusF === 'ongoing' ? (r.status !== 'released') : true;
            const pool = () => all.filter(statusOk);
            const sum = list => list.reduce((a, r) => a + (Number(r.final_amount) || 0), 0);
            const pill = s => { const t = SalesPO.STATUS_TONE[s] || SalesPO.STATUS_TONE.draft; return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-semibold border whitespace-nowrap" style="color:${t[1]};background:${t[2]};border-color:${t[1]}33">${t[0]}</span>`; };

            function vendors() {
                const map = new Map();
                pool().forEach(r => {
                    const name = vName(r), k = vKey(name);
                    if (!map.has(k)) map.set(k, { key: k, name: name || '— No supplier —', rows: [] });
                    map.get(k).rows.push(r);
                });
                return [...map.values()].map(v => ({ ...v, count: v.rows.length, total: sum(v.rows), last: v.rows.reduce((m, r) => (r.po_date || '') > m ? r.po_date : m, '') }));
            }

            // ═══ URL (para ma-share ang link: ?vendor=shera&year=2026&month=11) ═══
            function pushUrl() {
                const p = new URLSearchParams();
                if (sel.vendor) p.set('vendor', sel.vendor);
                if (sel.year) p.set('year', sel.year);
                if (sel.month) p.set('month', sel.month);
                history.replaceState(null, '', location.pathname + (p.toString() ? '?' + p : ''));
            }

            // ═══ LEFT: listahan ng supplier ═══
            function renderVendors() {
                const q = F('svVendorSearch').value.trim().toLowerCase();
                let list = vendors().filter(v => !q || v.name.toLowerCase().includes(q));
                const by = { amount: (a, b) => b.total - a.total, count: (a, b) => b.count - a.count, name: (a, b) => a.name.localeCompare(b.name), recent: (a, b) => String(b.last).localeCompare(String(a.last)) };
                list.sort(by[vSort]);
                F('svVendorCount').textContent = `${list.length} supplier${list.length === 1 ? '' : 's'}`;
                if (!list.length) { F('svVendors').innerHTML = '<p class="px-3 py-10 text-center text-xs text-gray-400">No supplier found.</p>'; return; }
                const max = Math.max(...list.map(v => v.total), 1);
                F('svVendors').innerHTML = list.map(v => `
                    <button type="button" data-v="${esc(v.key)}" data-on="${v.key === sel.vendor ? 1 : 0}"
                        class="sv-vendor w-full text-left rounded-lg border border-gray-200 bg-white px-3 py-2.5 hover:border-amber-300 transition">
                        <span class="flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-lg bg-gray-900 text-amber-400 text-[11px] font-black flex items-center justify-center shrink-0">${esc(v.name.replace(/[^A-Za-z0-9]/g, '').slice(0, 2).toUpperCase() || '?')}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[12.5px] font-semibold text-gray-900 truncate">${esc(v.name)}</span>
                                <span class="block text-[10.5px] text-gray-400">${v.count} PO${v.count === 1 ? '' : 's'} · last ${fmtDate(v.last) || '—'}</span>
                            </span>
                        </span>
                        <span class="flex items-center gap-2 mt-2">
                            <span class="flex-1 h-1 rounded-full bg-gray-100 overflow-hidden"><span class="block h-full bg-amber-500 rounded-full" style="width:${Math.max(3, v.total / max * 100)}%"></span></span>
                            <span class="font-mono text-[11px] font-bold text-gray-700">₱${short(v.total)}</span>
                        </span>
                    </button>`).join('');
            }

            // ═══ RIGHT: detalye ng isang supplier ═══
            function renderDetail() {
                const box = F('svDetail');
                const v = vendors().find(x => x.key === sel.vendor);
                if (!v) {
                    box.innerHTML = `<div class="bg-white border-2 border-dashed border-gray-200 rounded-xl px-6 py-20 text-center">
                        <div class="w-14 h-14 mx-auto rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center mb-3"><i class="fa-solid fa-hand-pointer text-xl"></i></div>
                        <p class="text-sm font-semibold text-gray-700">Pick a supplier on the left</p>
                        <p class="text-[11px] text-gray-400 mt-1">You'll see all its Purchase Orders, per year and per month.</p></div>`;
                    return;
                }

                // mga taon na may PO
                const years = [...new Set(v.rows.map(yearOf).filter(Boolean))].sort().reverse();
                if (sel.year && sel.year !== 'all' && !years.includes(sel.year)) sel.year = '';
                if (!sel.year) sel.year = years[0] || 'all';
                const inYear = sel.year === 'all' ? v.rows : v.rows.filter(r => yearOf(r) === sel.year);
                if (sel.year === 'all') sel.month = '';

                const perMonth = MONTHS.map((_, i) => { const m = String(i + 1).padStart(2, '0'); const l = inYear.filter(r => monOf(r) === m); return { m, count: l.length, total: sum(l) }; });
                const shown = (sel.month ? inYear.filter(r => monOf(r) === sel.month) : inYear).slice().sort((a, b) => String(b.po_date).localeCompare(String(a.po_date)) || b.id - a.id);
                const released = shown.filter(r => r.status === 'released');
                const maxM = Math.max(...perMonth.map(p => p.total), 1);
                const periodLabel = sel.year === 'all' ? 'All years' : sel.month ? `${MONTHS_FULL[+sel.month - 1]} ${sel.year}` : `Year ${sel.year}`;

                box.innerHTML = `
                <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                    <!-- supplier header -->
                    <div class="relative bg-gray-900 text-white px-5 py-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="w-11 h-11 rounded-xl bg-amber-500 text-gray-900 font-black flex items-center justify-center shrink-0">${esc(v.name.replace(/[^A-Za-z0-9]/g, '').slice(0, 2).toUpperCase() || '?')}</span>
                                <div class="min-w-0">
                                    <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-amber-400">NobleHome → Supplier</p>
                                    <h2 class="text-lg font-semibold truncate">${esc(v.name)}</h2>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <select id="svYear" class="sv-sel !bg-gray-800 !text-white !border-gray-700">
                                    ${years.map(y => `<option value="${y}" ${y === sel.year ? 'selected' : ''}>${y}</option>`).join('')}
                                    <option value="all" ${sel.year === 'all' ? 'selected' : ''}>All years</option>
                                </select>
                                <button type="button" id="svCsv" class="h-9 inline-flex items-center gap-1.5 px-3 text-xs font-semibold text-gray-900 bg-amber-400 rounded-lg hover:bg-amber-300"><i class="fa-solid fa-file-csv"></i> Export</button>
                                <button type="button" id="svPrintList" class="h-9 inline-flex items-center gap-1.5 px-3 text-xs font-semibold text-white bg-white/10 rounded-lg hover:bg-white/20"><i class="fa-solid fa-print"></i></button>
                            </div>
                        </div>
                    </div>
                    <div class="sv-hazard h-1"></div>

                    <!-- stat cards -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-px bg-gray-100">
                        ${[
                            ['Period', periodLabel, 'text-gray-900', ''],
                            ['Purchase Orders', shown.length, 'text-gray-900', `${released.length} released`],
                            ['Total amount', '₱' + money(sum(shown)), 'text-amber-700', 'final amount, VAT incl.'],
                            ['Released amount', '₱' + money(sum(released)), 'text-emerald-700', shown.length ? Math.round(released.length / shown.length * 100) + '% of POs' : ''],
                        ].map(([k, val, cls, sub]) => `<div class="bg-white px-4 py-3">
                            <p class="text-[9.5px] font-bold uppercase tracking-wider text-gray-400">${k}</p>
                            <p class="text-base font-bold font-mono ${cls} truncate">${esc(String(val))}</p>
                            <p class="text-[10.5px] text-gray-400">${sub}</p></div>`).join('')}
                    </div>

                    ${sel.year === 'all' ? '' : `
                    <!-- months -->
                    <div class="px-5 pt-4">
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-[10.5px] font-bold uppercase tracking-wider text-gray-500"><i class="fa-regular fa-calendar mr-1 text-amber-700"></i>Per month · ${sel.year}</p>
                            ${sel.month ? '<button type="button" data-month="" class="text-[11px] font-semibold text-amber-700 hover:underline">Show whole year</button>' : ''}
                        </div>
                        <!-- bars -->
                        <div class="flex items-end gap-1.5 h-24 px-1">
                            ${perMonth.map(p => `<button type="button" data-month="${p.count ? p.m : ''}" ${p.count ? '' : 'disabled'} title="${MONTHS_FULL[+p.m - 1]}: ${p.count} PO · ₱${money(p.total)}"
                                class="sv-bar flex-1 h-full flex flex-col justify-end items-center group" data-on="${p.m === sel.month ? 1 : 0}">
                                <span class="text-[9px] font-mono text-gray-400 mb-0.5 ${p.count ? '' : 'invisible'}">${short(p.total)}</span>
                                <span class="sv-fill w-full rounded-t-md ${p.count ? 'bg-amber-200 group-hover:bg-amber-400' : 'bg-gray-100'} transition-colors" style="height:${p.count ? Math.max(6, p.total / maxM * 100) : 3}%"></span>
                            </button>`).join('')}
                        </div>
                        <div class="grid grid-cols-6 sm:grid-cols-12 gap-1.5 mt-1.5">
                            ${perMonth.map(p => `<button type="button" data-month="${p.m}" data-on="${p.m === sel.month ? 1 : 0}" ${p.count ? '' : 'disabled'}
                                class="sv-month rounded-lg border border-gray-200 bg-white py-1.5 text-center transition hover:border-amber-300">
                                <span class="block text-[11px] font-bold">${MONTHS[+p.m - 1]}</span>
                                <span class="sv-msub block text-[9.5px] text-gray-400">${p.count || '–'}</span>
                            </button>`).join('')}
                        </div>
                    </div>`}

                    <!-- list -->
                    <div class="p-5">
                        <p class="text-[10.5px] font-bold uppercase tracking-wider text-gray-500 mb-2"><i class="fa-solid fa-list mr-1 text-amber-700"></i>Purchase Orders · ${esc(periodLabel)}</p>
                        ${shown.length ? `<div class="border border-gray-200 rounded-xl overflow-hidden overflow-x-auto sv-scroll">
                            <table class="w-full text-[12px] min-w-[640px]">
                                <thead><tr class="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
                                    <th class="text-left font-semibold px-4 py-2.5">P.O. no.</th>
                                    <th class="text-left font-semibold px-3 py-2.5">P.O. date</th>
                                    <th class="text-left font-semibold px-3 py-2.5">Prepared by</th>
                                    <th class="text-center font-semibold px-3 py-2.5">Items</th>
                                    <th class="text-left font-semibold px-3 py-2.5">Status</th>
                                    <th class="text-right font-semibold px-4 py-2.5">Final amount</th>
                                </tr></thead>
                                <tbody class="divide-y divide-gray-100">
                                    ${shown.map(r => `<tr data-po="${r.id}" class="hover:bg-amber-50/50 cursor-pointer">
                                        <td class="px-4 py-2.5 font-mono font-bold text-gray-900 whitespace-nowrap">${esc(r.po_no)}</td>
                                        <td class="px-3 py-2.5 text-gray-600 whitespace-nowrap">${fmtDate(r.po_date)}</td>
                                        <td class="px-3 py-2.5 text-gray-600 truncate max-w-[180px]">${esc(r.prepared_by || '—')}</td>
                                        <td class="px-3 py-2.5 text-center text-gray-600">${r.item_count ?? ''}</td>
                                        <td class="px-3 py-2.5">${pill(r.status)}</td>
                                        <td class="px-4 py-2.5 text-right font-mono font-bold text-gray-900 whitespace-nowrap">₱${money(r.final_amount)}</td>
                                    </tr>`).join('')}
                                </tbody>
                                <tfoot><tr class="bg-gray-900 text-white">
                                    <td colspan="5" class="px-4 py-2.5 text-[10.5px] font-bold uppercase tracking-wider">Total · ${shown.length} PO${shown.length === 1 ? '' : 's'}</td>
                                    <td class="px-4 py-2.5 text-right font-mono font-bold text-amber-300 whitespace-nowrap">₱${money(sum(shown))}</td>
                                </tr></tfoot>
                            </table></div>`
                        : '<div class="border-2 border-dashed border-gray-200 rounded-xl px-6 py-10 text-center text-xs text-gray-400">No Purchase Orders in this period.</div>'}
                    </div>
                </div>`;

                F('svYear').addEventListener('change', e => { sel.year = e.target.value; sel.month = ''; pushUrl(); renderDetail(); });
                F('svCsv').addEventListener('click', () => exportCsv(v.name, periodLabel, shown));
                F('svPrintList').addEventListener('click', () => printList(v.name, periodLabel, shown));
            }

            function renderAll() { renderVendors(); renderDetail(); }

            // ═══ export / print ng listahan ═══
            function exportCsv(vendor, period, list) {
                const cell = s => `"${String(s ?? '').replace(/"/g, '""')}"`;
                const lines = [['P.O. No.', 'P.O. Date', 'Supplier', 'Prepared by', 'Items', 'Status', 'Final Amount']]
                    .concat(list.map(r => [r.po_no, r.po_date, vName(r), r.prepared_by, r.item_count, (SalesPO.STATUS_TONE[r.status] || [r.status])[0], Number(r.final_amount || 0).toFixed(2)]))
                    .concat([['', '', '', '', '', 'TOTAL', sum(list).toFixed(2)]]);
                const blob = new Blob(['﻿' + lines.map(l => l.map(cell).join(',')).join('\r\n')], { type: 'text/csv;charset=utf-8' });
                const a = Object.assign(document.createElement('a'), { href: URL.createObjectURL(blob), download: `PO_${vendor}_${period}.csv`.replace(/[^\w.-]+/g, '_') });
                document.body.appendChild(a); a.click(); a.remove(); URL.revokeObjectURL(a.href);
            }

            function printList(vendor, period, list) {
                let area = document.getElementById('spoPrintArea');
                if (!area) { area = document.createElement('div'); area.id = 'spoPrintArea'; document.body.appendChild(area); }
                area.innerHTML = `<div class="spo">
                    <div class="hz"></div>
                    <div class="top"><div class="co"><img src="<?= BASE_URL ?>/icon/logo.webp" alt=""><div><h1>NOBLEHOME CONSTRUCTION CORPORATION</h1><p>Purchase Orders by Supplier</p></div></div>
                        <div class="ttl"><h2 style="font-size:16px">${esc(vendor)}</h2><div style="font-size:11px;color:#6b7280">${esc(period)}</div></div></div>
                    <table class="items"><thead><tr><th class="n">No.</th><th>P.O. no.</th><th>P.O. date</th><th>Prepared by</th><th class="c">Items</th><th>Status</th><th class="r">Final amount</th></tr></thead>
                    <tbody>${list.map((r, i) => `<tr><td class="n">${i + 1}</td><td><b>${esc(r.po_no)}</b></td><td>${fmtDate(r.po_date)}</td><td>${esc(r.prepared_by || '')}</td><td class="c">${r.item_count ?? ''}</td><td>${esc((SalesPO.STATUS_TONE[r.status] || [r.status])[0])}</td><td class="r">₱${money(r.final_amount)}</td></tr>`).join('')}</tbody></table>
                    <div class="bottom"><div></div><div class="sum"><div><span>Purchase Orders</span><span class="v">${list.length}</span></div><div class="final"><span>Total amount</span><span class="v">₱${money(sum(list))}</span></div></div></div>
                    <p class="foot">Printed ${new Date().toLocaleString('en-PH')}</p></div>`;
                const img = area.querySelector('img');
                const go = () => { window.print(); setTimeout(() => { area.innerHTML = ''; }, 500); };
                img && !img.complete ? (img.onload = img.onerror = go) : go();
            }

            // ═══ PO modal ═══
            let curPo = null;
            const openM = () => { F('svModal').classList.remove('hidden'); F('svModal').classList.add('flex'); document.body.style.overflow = 'hidden'; };
            const closeM = () => { F('svModal').classList.add('hidden'); F('svModal').classList.remove('flex'); document.body.style.overflow = ''; curPo = null; };
            async function openPO(id) {
                try {
                    const d = await api('get', { id });
                    curPo = d.po;
                    F('svMTitle').textContent = `${d.po.po_no} · ${d.po.vendor_company || d.po.vendor_name || ''}`;
                    SalesPO.render(F('svDoc'), d.po, {});
                    openM();
                } catch (e) { toast(e.message, 'error'); }
            }

            // ═══ events ═══
            F('svVendors').addEventListener('click', e => {
                const b = e.target.closest('[data-v]'); if (!b) return;
                sel = { vendor: b.dataset.v, year: '', month: '' };
                pushUrl(); renderAll();
                if (window.innerWidth < 1024) F('svDetail').scrollIntoView({ behavior: 'smooth', block: 'start' });
            });
            F('svDetail').addEventListener('click', e => {
                const m = e.target.closest('[data-month]');
                if (m && !m.disabled) { sel.month = sel.month === m.dataset.month ? '' : m.dataset.month; pushUrl(); renderDetail(); return; }
                const r = e.target.closest('[data-po]'); if (r) openPO(+r.dataset.po);
            });
            F('svVendorSearch').addEventListener('input', renderVendors);
            F('svVendorSort').addEventListener('change', e => { vSort = e.target.value; renderVendors(); });
            F('svStatus').addEventListener('change', e => { statusF = e.target.value; renderAll(); });
            F('svClose').addEventListener('click', closeM);
            F('svModal').addEventListener('click', e => { if (e.target === F('svModal')) closeM(); });
            F('svPrint').addEventListener('click', () => { if (curPo) SalesPO.print(curPo); });
            document.addEventListener('keydown', e => { if (e.key === 'Escape' && curPo) closeM(); });

            // ═══ load ═══
            (async function () {
                const p = new URLSearchParams(location.search);
                sel = { vendor: (p.get('vendor') || '').toLowerCase(), year: p.get('year') || '', month: p.get('month') || '' };
                F('svVendors').innerHTML = '<p class="px-3 py-10 text-center text-xs text-gray-400"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Loading…</p>';
                try {
                    const d = await api('list');
                    all = (d.rows || []).filter(r => r.status !== 'draft');
                    // kung pangalan lang ang nasa ?vendor= (hal. "shera"), hanapin ang supplier na may ganoong salita
                    if (sel.vendor && !vendors().some(v => v.key === sel.vendor)) {
                        const hit = vendors().find(v => v.key.includes(sel.vendor));
                        sel.vendor = hit ? hit.key : '';
                    }
                    renderAll();
                } catch (e) {
                    F('svVendors').innerHTML = `<p class="px-3 py-10 text-center text-xs text-red-600">${esc(e.message)}</p>`;
                    renderDetail();
                }
            })();
        })();
    </script>
</body>

</html>