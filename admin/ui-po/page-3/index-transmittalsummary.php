<?php
// index-transmittalsummary.php  —  Summary of Transmittal (Purchasing)
// Pumapasok dito ang bawat na-save na transmittal (For delivery).
// Nagiging "Delivered" lang kapag kinumpirma gamit ang Mark as Delivered.

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PO];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$tsAjaxUrl = BASE_URL . '/transmittalsummaryajax';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Summary of Transmittal</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
</head>

<body class="bg-slate-100">
    <main id="main-content" class="md:ml-56 pt-20 md:pt-5 min-h-screen p-4 md:p-8 transition-all duration-300">

        <!-- ===================== HEADER ===================== -->
        <div class="flex flex-wrap items-end justify-between gap-3 mb-4">
            <div>
                <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase mb-0.5">Purchasing</p>
                <h1 class="text-gray-900 text-xl font-semibold">Summary of Transmittal</h1>
                <p class="text-gray-400 text-xs mt-0.5">Every saved transmittal lands here as <b class="text-orange-600">For delivery</b>. Mark it delivered once the client has it.</p>
            </div>
            <div class="flex items-center gap-2">
                <a href="<?= BASE_URL ?>/transmittal" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">
                    <i class="fa-solid fa-file-export"></i> Transmittals
                </a>
                <button type="button" id="tsExport" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 shadow-sm">
                    <i class="fa-solid fa-file-csv"></i> Export
                </button>
            </div>
        </div>

        <!-- ===================== STATS ===================== -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-4">
            <div class="bg-white border border-gray-200 rounded-xl px-5 py-4 flex items-center gap-4">
                <span class="w-11 h-11 rounded-xl bg-orange-50 text-orange-600 flex items-center justify-center"><i class="fa-solid fa-truck-ramp-box"></i></span>
                <div><p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">For Delivery</p><p id="tsStatPending" class="text-2xl font-bold text-gray-900 tabular-nums leading-tight">—</p></div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl px-5 py-4 flex items-center gap-4">
                <span class="w-11 h-11 rounded-xl bg-green-50 text-green-600 flex items-center justify-center"><i class="fa-solid fa-truck"></i></span>
                <div><p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Delivered this month</p><p id="tsStatMonth" class="text-2xl font-bold text-gray-900 tabular-nums leading-tight">—</p></div>
            </div>
            <div class="bg-white border border-gray-200 rounded-xl px-5 py-4 flex items-center gap-4">
                <span class="w-11 h-11 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center"><i class="fa-solid fa-file-lines"></i></span>
                <div><p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Total Transmittals</p><p id="tsStatTotal" class="text-2xl font-bold text-gray-900 tabular-nums leading-tight">—</p></div>
            </div>
        </div>

        <!-- ===================== TABLE CARD ===================== -->
        <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <div class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-gray-100">
                <div id="tsTabs" class="inline-flex items-center gap-1 bg-gray-50 border border-gray-200 rounded-lg p-1"></div>
                <div class="flex flex-wrap items-center gap-2 ml-auto">
                    <div class="relative">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[11px]"></i>
                        <input type="text" id="tsSearch" placeholder="Series no., particulars, mode…"
                            class="w-56 border border-gray-200 rounded-lg pl-8 pr-3 py-2 text-xs outline-none focus:border-amber-600">
                    </div>
                    <select id="tsClient" class="border border-gray-200 rounded-lg px-3 py-2 text-xs outline-none focus:border-amber-600 bg-white max-w-[180px]">
                        <option value="">All clients</option>
                    </select>
                    <div class="inline-flex items-center gap-1 border border-gray-200 rounded-lg px-2 py-1 bg-white">
                        <span class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Date</span>
                        <input type="date" id="tsFrom" class="text-xs outline-none bg-transparent px-1 py-1" title="From">
                        <span class="text-gray-300">–</span>
                        <input type="date" id="tsTo" class="text-xs outline-none bg-transparent px-1 py-1" title="To">
                    </div>
                    <button type="button" id="tsReset" class="px-2 py-2 text-[11px] text-gray-400 hover:text-amber-700" title="Clear filters"><i class="fa-solid fa-filter-circle-xmark"></i></button>
                </div>
            </div>

            <div class="overflow-y-auto overflow-x-hidden max-h-[calc(100vh-380px)] min-h-[260px]">
                <table class="w-full table-fixed text-xs border-separate border-spacing-0">
                    <colgroup>
                        <col style="width:10%"><col style="width:11%"><col style="width:15%"><col style="width:17%">
                        <col style="width:11%"><col style="width:14%"><col><col style="width:140px">
                    </colgroup>
                    <thead class="sticky top-0 z-10">
                        <tr><th colspan="8" class="bg-amber-400 text-gray-900 text-sm font-bold py-2.5 text-center tracking-wide">Summary of Transmittal</th></tr>
                        <tr class="bg-green-600 text-white text-[11px] font-semibold">
                            <th class="px-3 py-2.5 text-left">Date</th>
                            <th class="px-3 py-2.5 text-left">Series Number</th>
                            <th class="px-3 py-2.5 text-left">Company / Client</th>
                            <th class="px-3 py-2.5 text-left">Particulars</th>
                            <th class="px-3 py-2.5 text-left">Date Delivered</th>
                            <th class="px-3 py-2.5 text-left">Mode of Delivery</th>
                            <th class="px-3 py-2.5 text-left">Remarks</th>
                            <th class="px-3 py-2.5 text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody id="tsBody">
                        <tr><td colspan="8" class="px-5 py-12 text-center text-gray-400"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Loading…</td></tr>
                    </tbody>
                </table>
            </div>
            <p id="tsFooter" class="px-4 py-2.5 border-t border-gray-100 bg-gray-50/60 text-[11px] text-gray-400"></p>
        </div>

        <datalist id="tsModes"><option value="Pick up by Client"><option value="Lalamove"><option value="Deliver"></datalist>
        <div id="crmToastContainer" class="fixed bottom-4 right-4 z-[10000] flex flex-col gap-2.5 pointer-events-none w-[340px] max-w-[calc(100vw-2rem)]"></div>
    </main>

    <!-- ===================== MARK AS DELIVERED MODAL ===================== -->
    <div id="tsDeliverModal" class="hidden fixed inset-0 z-[9999] items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
            <div class="p-5">
                <div class="flex items-center gap-2.5 mb-3">
                    <span class="w-9 h-9 rounded-full bg-green-50 text-green-600 flex items-center justify-center"><i class="fa-solid fa-truck text-sm"></i></span>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">Mark as delivered?</p>
                        <p id="tsDlvRef" class="font-mono text-[11px] font-semibold text-gray-700"></p>
                    </div>
                </div>
                <div class="rounded-lg bg-gray-50 border border-gray-200 px-3 py-2.5 text-xs text-gray-600 space-y-1 mb-4">
                    <p><span class="text-gray-400">Client:</span> <span id="tsDlvClient" class="font-semibold text-gray-800"></span></p>
                    <p><span class="text-gray-400">Particulars:</span> <span id="tsDlvPart" class="font-semibold text-gray-800"></span></p>
                </div>
                <div class="grid grid-cols-2 gap-3">
                    <label class="block">
                        <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Date Delivered <span class="text-red-500">*</span></span>
                        <input type="date" id="tsDlvDate" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs outline-none focus:border-green-600">
                    </label>
                    <label class="block">
                        <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Mode of Delivery <span class="text-red-500">*</span></span>
                        <input type="text" id="tsDlvMode" list="tsModes" maxlength="255" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs outline-none focus:border-green-600">
                    </label>
                    <label class="block col-span-2">
                        <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Remarks</span>
                        <input type="text" id="tsDlvRemarks" maxlength="1000" placeholder="Optional" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs outline-none focus:border-green-600">
                    </label>
                </div>
                <label class="mt-4 flex items-start gap-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2.5 cursor-pointer">
                    <input type="checkbox" id="tsDlvConfirm" class="mt-0.5 w-4 h-4 accent-green-600">
                    <span class="text-[11px] text-amber-900 leading-relaxed">I confirm the items under this transmittal were <b>delivered to / picked up by the client</b>. This can't be undone.</span>
                </label>
                <p id="tsDlvError" class="hidden mt-2 text-[11px] text-red-600"></p>
            </div>
            <div class="flex justify-end gap-2 px-5 py-3 bg-gray-50 border-t border-gray-100">
                <button type="button" data-close="tsDeliverModal" class="px-3.5 py-2 text-[11px] font-semibold text-gray-500 bg-white border border-gray-200 rounded-lg hover:bg-gray-100">Cancel</button>
                <button type="button" id="tsDlvOk" disabled class="px-3.5 py-2 text-[11px] font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed">
                    <i class="fa-solid fa-check mr-1"></i> Yes, delivered
                </button>
            </div>
        </div>
    </div>

    <!-- ===================== EDIT MODAL (habang For delivery) ===================== -->
    <div id="tsEditModal" class="hidden fixed inset-0 z-[9999] items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
            <div class="p-5">
                <p class="text-sm font-semibold text-gray-800">Edit summary details</p>
                <p id="tsEditRef" class="font-mono text-[11px] font-semibold text-gray-500 mb-4"></p>
                <div class="space-y-3">
                    <label class="block">
                        <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Particulars <span class="text-red-500">*</span></span>
                        <input type="text" id="tsEditPart" maxlength="255" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs outline-none focus:border-amber-600">
                    </label>
                    <label class="block">
                        <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Mode of Delivery</span>
                        <input type="text" id="tsEditMode" list="tsModes" maxlength="255" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs outline-none focus:border-amber-600">
                    </label>
                    <label class="block">
                        <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Remarks</span>
                        <input type="text" id="tsEditRemarks" maxlength="1000" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs outline-none focus:border-amber-600">
                    </label>
                </div>
            </div>
            <div class="flex justify-end gap-2 px-5 py-3 bg-gray-50 border-t border-gray-100">
                <button type="button" data-close="tsEditModal" class="px-3.5 py-2 text-[11px] font-semibold text-gray-500 bg-white border border-gray-200 rounded-lg hover:bg-gray-100">Cancel</button>
                <button type="button" id="tsEditOk" class="px-3.5 py-2 text-[11px] font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:opacity-60">
                    <i class="fa-solid fa-check mr-1"></i> Save
                </button>
            </div>
        </div>
    </div>

    <script>
        (function () {
            const AJAX = <?= json_encode($tsAjaxUrl) ?>;
            const TR_URL = <?= json_encode(BASE_URL . '/transmittal') ?>;

            let rows = [];
            let tab = 'pending';
            let target = null;

            const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
            const ymd = v => v ? String(v).slice(0, 10) : '';
            const mdy = v => { const s = ymd(v); if (!s) return ''; const [y, m, d] = s.split('-'); return `${m}/${d}/${y}`; };
            const todayISO = () => { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; };
            const company = r => r.client_company || r.client_name || '';

            function toast(message, type = 'success', duration = 3500) {
                const box = document.getElementById('crmToastContainer');
                const p = type === 'success'
                    ? { wrap: 'bg-green-50 border-green-200 text-green-700', icon: 'bg-green-200 text-green-700', sym: '✓' }
                    : { wrap: 'bg-red-50 border-red-200 text-red-700', icon: 'bg-red-200 text-red-700', sym: '!' };
                const t = document.createElement('div');
                t.className = `pointer-events-auto flex items-start gap-2.5 border rounded-lg shadow-lg px-4 py-3 text-sm ${p.wrap} transition-all duration-300`;
                t.innerHTML = `<span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold ${p.icon}">${p.sym}</span><span class="flex-1 leading-relaxed"></span>`;
                t.querySelector('.flex-1').textContent = message;
                box.appendChild(t);
                setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, duration);
            }
            const openModal = id => { const m = document.getElementById(id); m.classList.remove('hidden'); m.classList.add('flex'); };
            const closeModal = id => { const m = document.getElementById(id); m.classList.add('hidden'); m.classList.remove('flex'); };
            document.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => closeModal(b.dataset.close)));
            ['tsDeliverModal', 'tsEditModal'].forEach(id => document.getElementById(id).addEventListener('click', e => { if (e.target.id === id) closeModal(id); }));
            document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeModal('tsDeliverModal'); closeModal('tsEditModal'); } });

            const memo = {
                get: k => { try { return localStorage.getItem('nh-ts-' + k); } catch (e) { return null; } },
                set: (k, v) => { try { localStorage.setItem('nh-ts-' + k, v); } catch (e) { } },
            };

            // ── tabs + filters ──
            const TABS = [
                { key: 'pending', label: 'For Delivery', dot: 'bg-orange-500', test: r => !r.delivered_at },
                { key: 'done',    label: 'Delivered',    dot: 'bg-green-500',  test: r => !!r.delivered_at },
                { key: 'all',     label: 'All',          dot: 'bg-gray-400',   test: () => true },
            ];
            tab = memo.get('tab') || 'pending';
            ['tsSearch', 'tsFrom', 'tsTo'].forEach(id => {
                const el = document.getElementById(id);
                el.value = memo.get(id) || '';
                el.addEventListener(id === 'tsSearch' ? 'input' : 'change', () => { memo.set(id, el.value); render(); });
            });
            document.getElementById('tsClient').addEventListener('change', e => { memo.set('tsClient', e.target.value); render(); });
            document.getElementById('tsReset').addEventListener('click', () => {
                ['tsSearch', 'tsFrom', 'tsTo', 'tsClient'].forEach(id => { document.getElementById(id).value = ''; memo.set(id, ''); });
                render();
            });

            function baseFiltered() {
                const q = document.getElementById('tsSearch').value.trim().toLowerCase();
                const cl = document.getElementById('tsClient').value;
                const from = document.getElementById('tsFrom').value, to = document.getElementById('tsTo').value;
                return rows.filter(r => {
                    if (q && !`${r.ref_no} ${r.req_no ?? ''} ${company(r)} ${r.particulars ?? ''} ${r.delivery_mode ?? ''} ${r.summary_remarks ?? ''}`.toLowerCase().includes(q)) return false;
                    if (cl && company(r) !== cl) return false;
                    const d = ymd(r.transmittal_date);
                    if (from && d < from) return false;
                    if (to && d > to) return false;
                    return true;
                });
            }
            const filtered = () => baseFiltered().filter((TABS.find(t => t.key === tab) || TABS[0]).test);

            function renderTabs() {
                const base = baseFiltered();
                document.getElementById('tsTabs').innerHTML = TABS.map(t => {
                    const on = t.key === tab;
                    return `<button type="button" data-tab="${t.key}" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-semibold transition-all ${on ? 'bg-white shadow-sm text-gray-900' : 'text-gray-500 hover:text-gray-700'}">
                        <span class="w-1.5 h-1.5 rounded-full ${t.dot}"></span>${t.label}
                        <span class="min-w-[20px] px-1.5 py-px rounded-full bg-gray-200 text-gray-600 text-[10px] font-bold text-center">${base.filter(t.test).length}</span></button>`;
                }).join('');
            }
            document.getElementById('tsTabs').addEventListener('click', e => {
                const b = e.target.closest('[data-tab]');
                if (!b) return;
                tab = b.dataset.tab; memo.set('tab', tab); render();
            });

            // ── table ──
            function render() {
                renderTabs();
                const month = todayISO().slice(0, 7);
                document.getElementById('tsStatPending').textContent = rows.filter(r => !r.delivered_at).length;
                document.getElementById('tsStatMonth').textContent = rows.filter(r => ymd(r.delivered_at).startsWith(month)).length;
                document.getElementById('tsStatTotal').textContent = rows.length;

                const list = filtered();
                const body = document.getElementById('tsBody');
                document.getElementById('tsFooter').textContent = `Showing ${list.length} of ${rows.length} transmittal${rows.length === 1 ? '' : 's'}`;
                if (!list.length) {
                    body.innerHTML = `<tr><td colspan="8" class="px-5 py-14 text-center text-gray-400"><i class="fa-regular fa-folder-open text-gray-300 text-2xl block mb-2"></i>${rows.length ? 'Nothing here for these filters.' : 'No transmittals yet. They appear here once saved.'}</td></tr>`;
                    return;
                }
                const td = 'px-3 py-2.5 border-b border-gray-100 align-middle';
                body.innerHTML = list.map(r => {
                    const done = !!r.delivered_at;
                    const action = done
                        ? `<span class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-green-50 text-green-700 text-[10.5px] font-bold"><i class="fa-solid fa-circle-check"></i> DELIVERED</span>`
                        : `<div class="inline-flex items-center gap-1">
                               <button type="button" data-edit="${r.id}" title="Edit details" class="w-7 h-7 inline-flex items-center justify-center text-gray-500 bg-white border border-gray-200 rounded-md hover:bg-gray-50"><i class="fa-solid fa-pen text-[10px]"></i></button>
                               <button type="button" data-deliver="${r.id}" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[11px] font-semibold text-white bg-green-600 rounded-md hover:bg-green-700 shadow-sm"><i class="fa-solid fa-truck text-[10px]"></i> Delivered</button>
                           </div>`;
                    return `
                    <tr class="${done ? 'bg-white' : 'bg-orange-50/30'} hover:bg-amber-50/40">
                        <td class="${td} text-gray-700 whitespace-nowrap">${mdy(r.transmittal_date)}</td>
                        <td class="${td}"><a href="${TR_URL}?view=${r.id}" class="font-mono font-semibold text-gray-900 hover:text-amber-700 hover:underline">${esc(r.ref_no)}</a>
                            ${r.req_no ? `<p class="font-mono text-[10px] text-red-500 truncate">${esc(r.req_no)}</p>` : ''}</td>
                        <td class="${td}"><p class="font-semibold text-gray-800 truncate" title="${esc(company(r))}">${esc(company(r))}</p></td>
                        <td class="${td} text-gray-700"><p class="line-clamp-2">${esc(r.particulars || '—')}</p></td>
                        <td class="${td} whitespace-nowrap">${done ? `<span class="font-semibold text-green-700">${mdy(r.delivered_at)}</span>` : '<span class="inline-flex items-center gap-1 text-[11px] font-semibold text-orange-600"><span class="w-1.5 h-1.5 rounded-full bg-orange-500"></span>For delivery</span>'}</td>
                        <td class="${td} text-gray-700"><p class="truncate" title="${esc(r.delivery_mode || '')}">${esc(r.delivery_mode || '—')}</p></td>
                        <td class="${td} text-gray-500"><p class="line-clamp-2" title="${esc(r.summary_remarks || '')}">${esc(r.summary_remarks || '')}</p></td>
                        <td class="${td} text-right whitespace-nowrap">${action}</td>
                    </tr>`;
                }).join('');
            }

            function fillClients(modes) {
                const sel = document.getElementById('tsClient');
                const keep = sel.value || memo.get('tsClient') || '';
                const names = [...new Set(rows.map(company).filter(Boolean))].sort((a, b) => a.localeCompare(b));
                sel.innerHTML = '<option value="">All clients</option>' + names.map(n => `<option value="${esc(n)}">${esc(n)}</option>`).join('');
                sel.value = names.includes(keep) ? keep : '';
                const dl = document.getElementById('tsModes');
                modes.forEach(m => { if (![...dl.options].some(o => o.value.toLowerCase() === m.toLowerCase())) dl.insertAdjacentHTML('beforeend', `<option value="${esc(m)}">`); });
            }

            async function load() {
                try {
                    const res = await fetch(`${AJAX}?action=list`, { cache: 'no-store' });
                    const d = await res.json();
                    if (!d.success) { toast(d.message || 'Failed to load.', 'error'); return; }
                    rows = d.rows;
                    fillClients(d.modes || []);
                    render();
                } catch (e) { toast('Connection error while loading.', 'error'); }
            }

            document.getElementById('tsBody').addEventListener('click', e => {
                const dv = e.target.closest('[data-deliver]');
                const ed = e.target.closest('[data-edit]');
                if (dv) openDeliver(+dv.dataset.deliver);
                if (ed) openEdit(+ed.dataset.edit);
            });

            // ── Mark as Delivered (may validation) ──
            const okBtn = document.getElementById('tsDlvOk');
            const chk = document.getElementById('tsDlvConfirm');
            const dlvErr = msg => { const p = document.getElementById('tsDlvError'); p.textContent = msg || ''; p.classList.toggle('hidden', !msg); };
            function openDeliver(id) {
                target = rows.find(r => r.id === id);
                if (!target) return;
                document.getElementById('tsDlvRef').textContent = target.ref_no;
                document.getElementById('tsDlvClient').textContent = company(target);
                document.getElementById('tsDlvPart').textContent = target.particulars || '—';
                const date = document.getElementById('tsDlvDate');
                date.min = ymd(target.transmittal_date);
                date.max = todayISO();
                date.value = todayISO();
                document.getElementById('tsDlvMode').value = target.delivery_mode || '';
                document.getElementById('tsDlvRemarks').value = target.summary_remarks || '';
                chk.checked = false;
                okBtn.disabled = true;
                dlvErr('');
                openModal('tsDeliverModal');
            }
            chk.addEventListener('change', () => { okBtn.disabled = !chk.checked; });

            okBtn.addEventListener('click', async function () {
                if (!target || !chk.checked) return;
                const date = document.getElementById('tsDlvDate').value;
                const mode = document.getElementById('tsDlvMode').value.trim();
                if (!date) return dlvErr('Enter the date it was delivered.');
                if (date < ymd(target.transmittal_date)) return dlvErr('Date delivered cannot be earlier than the transmittal date.');
                if (date > todayISO()) return dlvErr('Date delivered cannot be in the future.');
                if (!mode) return dlvErr('Enter the mode of delivery.');
                dlvErr('');
                this.disabled = true;
                this.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Saving…';
                try {
                    const res = await fetch(`${AJAX}?action=deliver`, {
                        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ id: target.id, delivered_at: date, delivery_mode: mode, summary_remarks: document.getElementById('tsDlvRemarks').value.trim(), confirm: '1' })
                    });
                    const d = await res.json();
                    if (!d.success) { dlvErr(d.message || 'Could not save.'); return; }
                    closeModal('tsDeliverModal');
                    toast(`${target.ref_no} marked as delivered.`);
                    load();
                } catch (e) { dlvErr('Connection error. Not saved.'); }
                finally { this.disabled = !chk.checked; this.innerHTML = '<i class="fa-solid fa-check mr-1"></i> Yes, delivered'; }
            });

            // ── Edit details (habang For delivery) ──
            function openEdit(id) {
                target = rows.find(r => r.id === id);
                if (!target) return;
                document.getElementById('tsEditRef').textContent = target.ref_no;
                document.getElementById('tsEditPart').value = target.particulars || '';
                document.getElementById('tsEditMode').value = target.delivery_mode || '';
                document.getElementById('tsEditRemarks').value = target.summary_remarks || '';
                openModal('tsEditModal');
            }
            document.getElementById('tsEditOk').addEventListener('click', async function () {
                if (!target) return;
                const part = document.getElementById('tsEditPart').value.trim();
                if (!part) { toast('Particulars is required.', 'error'); return; }
                this.disabled = true;
                try {
                    const res = await fetch(`${AJAX}?action=update`, {
                        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ id: target.id, particulars: part, delivery_mode: document.getElementById('tsEditMode').value.trim(), summary_remarks: document.getElementById('tsEditRemarks').value.trim() })
                    });
                    const d = await res.json();
                    if (!d.success) { toast(d.message || 'Could not save.', 'error'); return; }
                    closeModal('tsEditModal');
                    toast('Details updated.');
                    load();
                } catch (e) { toast('Connection error.', 'error'); }
                finally { this.disabled = false; }
            });

            // ── export ──
            document.getElementById('tsExport').addEventListener('click', () => {
                const q = v => `"${String(v ?? '').replace(/"/g, '""')}"`;
                const head = ['Date', 'Series Number', 'Company/ Client', 'Particulars', 'Date Delivered', 'Mode of Delivery', 'Remarks'];
                const lines = filtered().map(r => [mdy(r.transmittal_date), r.ref_no, company(r), r.particulars || '', r.delivered_at ? mdy(r.delivered_at) : 'For delivery', r.delivery_mode || '', r.summary_remarks || ''].map(q).join(','));
                const a = document.createElement('a');
                a.href = URL.createObjectURL(new Blob(['﻿' + [head.map(q).join(','), ...lines].join('\r\n')], { type: 'text/csv;charset=utf-8' }));
                a.download = `summary-of-transmittal-${todayISO().replace(/-/g, '')}.csv`;
                document.body.appendChild(a); a.click(); a.remove();
                setTimeout(() => URL.revokeObjectURL(a.href), 1000);
            });

            load();
        })();
    </script>
</body>

</html>