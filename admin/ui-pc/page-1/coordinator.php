<?php
// coordenator.php  —  Project Coordinator (Operations) workspace
// Pumapasok ang proyekto dito pagkatapos ng transmittal. Bawat tab = isang bahagi ng Operations flow.
// Ngayon: Delivery Receipt — makakagawa lang ng DR kapag OK na (Delivered) ang transmittal.

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PC];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$pcAjaxUrl = BASE_URL . '/coordinatorajax';
$pcSummaryUrl = BASE_URL . '/transmittalsummary';   // Transmittal Summary page (dito mina-mark ang Delivered)
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PC Dashboard</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <style>
        .dr-hazard { background: repeating-linear-gradient(-45deg, #f59e0b 0 12px, #1f2937 12px 24px); }
        .dr-board { background-color: #1f2937; background-image: linear-gradient(rgba(255,255,255,.04) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.04) 1px, transparent 1px); background-size: 22px 22px; }

        /* ── DR print (asul) ── */
        .drdoc { font-family: Arial, Helvetica, sans-serif; color: #111; font-size: 11px; line-height: 1.35; }
        .drdoc table { border-collapse: collapse; width: 100%; table-layout: fixed; }
        .drdoc td { vertical-align: top; padding: 3px 5px; word-wrap: break-word; }
        .drdoc .tbl td { border: 1px solid #333; }
        .drdoc .c { text-align: center; } .drdoc .r { text-align: right; } .drdoc .b { font-weight: 700; } .drdoc .i { font-style: italic; }
        .drdoc p { margin: 0 0 5px; }
        .drdoc .head { border: 2px solid #1d4ed8; margin-bottom: 8px; }
        .drdoc .title { background: #1d4ed8; color: #fff; font-weight: 700; text-align: center; font-size: 15px; letter-spacing: .08em; padding: 5px; }
        .drdoc .co { font-size: 10px; color: #333; }
        .drdoc .meta td { padding: 2px 4px; font-size: 10.5px; }
        .drdoc .tbl .th td { background: #1d4ed8; color: #fff; font-weight: 700; text-align: center; }
        .drdoc .blue { color: #1d4ed8; font-weight: 700; }
        .drdoc .terms { margin-top: 12px; font-size: 10px; }
        .drdoc .sign { margin-top: 36px; } .drdoc .sign td { text-align: center; padding: 0 14px; }
        .drdoc .sign .nm { min-height: 15px; font-weight: 700; } .drdoc .sign .ln { border-top: 1px solid #111; padding-top: 2px; font-size: 10px; }
        #nhPrintArea { display: none; }
        @media print {
            @page { size: A4; margin: 12mm; }
            body > *:not(#nhPrintArea) { display: none !important; }
            #nhPrintArea { display: block !important; }
            #nhPrintArea, #nhPrintArea * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>

<body class="bg-slate-100">
    <main id="main-content" class="md:ml-56 pt-20 md:pt-5 min-h-screen p-4 md:p-8 transition-all duration-300">

        <!-- ===================== SITE BOARD (header) ===================== -->
        <section class="rounded-2xl overflow-hidden shadow-sm mb-5">
            <div class="dr-hazard h-2"></div>
            <div class="dr-board px-5 py-5 md:px-7 md:py-6 text-white">
                <div class="flex flex-wrap items-end justify-between gap-5">
                    <div class="min-w-0">
                        <p class="text-amber-400 text-[10px] font-bold tracking-[0.2em] uppercase">Operations · Project Coordinator</p>
                        <h1 class="text-2xl font-bold mt-1">Delivery Receipts</h1>
                        <p class="text-slate-400 text-xs mt-1 max-w-xl">You can create a DR once the transmittal is OK, meaning it is marked <b class="text-slate-200">Delivered</b> in the Transmittal Summary.</p>
                    </div>
                    <div class="grid grid-cols-3 gap-2 w-full sm:w-auto">
                        <button type="button" data-filter="ready" class="text-left rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 px-4 py-2.5 sm:min-w-[118px]">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-green-400">Ready for DR</p>
                            <p id="drStatReady" class="text-2xl font-bold tabular-nums">—</p>
                        </button>
                        <button type="button" data-filter="waiting" class="text-left rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 px-4 py-2.5 sm:min-w-[118px]">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-amber-400">Waiting</p>
                            <p id="drStatWaiting" class="text-2xl font-bold tabular-nums">—</p>
                        </button>
                        <button type="button" data-filter="done" class="text-left rounded-xl bg-white/5 hover:bg-white/10 border border-white/10 px-4 py-2.5 sm:min-w-[118px]">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-sky-300">DR Created</p>
                            <p id="drStatDone" class="text-2xl font-bold tabular-nums">—</p>
                        </button>
                    </div>
                </div>
            </div>
        </section>

        <!-- ===================== TOOLBAR ===================== -->
        <div class="flex flex-wrap items-center gap-2 mb-3">
            <div id="drTabs" class="flex flex-wrap gap-1 bg-white border border-gray-200 rounded-xl p-1"></div>
            <div class="relative flex-1 min-w-[200px]">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                <input type="text" id="drSearch" placeholder="Search transmittal no., client, project, PR…"
                    class="w-full bg-white border border-gray-200 rounded-xl pl-9 pr-3 py-2.5 text-xs outline-none focus:border-amber-600">
            </div>
        </div>

        <!-- ===================== LIST ===================== -->
        <div id="drList" class="space-y-2">
            <div class="bg-white border border-gray-200 rounded-xl px-6 py-14 text-center text-xs text-gray-400"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Loading…</div>
        </div>

        <div id="crmToastContainer" class="fixed bottom-4 right-4 z-[10000] flex flex-col gap-2.5 pointer-events-none w-[340px] max-w-[calc(100vw-2rem)]"></div>
    </main>

    <!-- ===================== CREATE DR MODAL ===================== -->
    <div id="drModal" class="hidden fixed inset-0 z-[9999] items-start justify-center bg-black/50 p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-4xl my-auto overflow-hidden">
            <div class="dr-hazard h-1.5"></div>
            <div class="flex items-start justify-between gap-3 px-5 py-4 border-b border-gray-100">
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-amber-700">New Delivery Receipt</p>
                    <p id="drModalTitle" class="text-base font-semibold text-gray-900 truncate"></p>
                    <p id="drModalSub" class="text-[11px] text-gray-400"></p>
                </div>
                <button type="button" data-close="drModal" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
            </div>
            <div id="drModalBody" class="p-5"></div>
            <div class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 bg-gray-50 border-t border-gray-100">
                <p id="drDraftNote" class="text-[10.5px] text-gray-400"></p>
                <div class="flex items-center gap-2 ml-auto">
                    <button type="button" data-close="drModal" class="px-3.5 py-2 text-xs font-semibold text-gray-500 bg-white border border-gray-200 rounded-lg hover:bg-gray-100">Cancel</button>
                    <button type="button" id="drSave" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:opacity-60"><i class="fa-solid fa-file-circle-check"></i> Create DR</button>
                </div>
            </div>
        </div>
    </div>

    <!-- ===================== VIEW DR MODAL ===================== -->
    <div id="drViewModal" class="hidden fixed inset-0 z-[9999] items-start justify-center bg-black/50 p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-4xl my-auto overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-5 py-3.5 border-b border-gray-100">
                <p id="drViewTitle" class="text-sm font-semibold text-gray-800"></p>
                <div class="flex items-center gap-2">
                    <button type="button" id="drPrint" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-gray-900 rounded-lg hover:bg-gray-800"><i class="fa-solid fa-print"></i> Print / Save PDF</button>
                    <button type="button" data-close="drViewModal" class="text-gray-400 hover:text-gray-600 text-2xl leading-none">&times;</button>
                </div>
            </div>
            <div class="p-5 bg-slate-50"><div class="overflow-x-auto"><div id="drViewBody" class="min-w-[720px] bg-white border border-gray-200 rounded-lg p-6"></div></div></div>
        </div>
    </div>

    <script>
        (function () {
            'use strict';
            const AJAX = <?= json_encode($pcAjaxUrl) ?>;
            const SUMMARY_URL = <?= json_encode($pcSummaryUrl) ?>;
            const LOGO = <?= json_encode(BASE_URL . '/icon/logo.webp') ?>;

            let rows = [], filter = 'ready', openTr = null;
            const F = id => document.getElementById(id);

            // ── helpers ──
            const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
            const toD = s => { const [y, m, d] = String(s).slice(0, 10).split('-').map(Number); return new Date(y, m - 1, d); };
            const ymd = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
            const todayISO = () => ymd(new Date());
            const fmtY = s => s ? toD(s).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
            const fmtSlash = s => { if (!s) return ''; const [y, m, d] = String(s).slice(0, 10).split('-'); return `${m}/${d}/${y}`; };
            const money = n => (Number(n) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const num = v => Number(String(v ?? '').replace(/,/g, '')) || 0;
            const qtyFmt = q => { const n = parseFloat(q); return isNaN(n) ? '' : String(+n.toFixed(2)); };
            const memo = {
                get: k => { try { return localStorage.getItem('nh-dr-' + k); } catch (e) { return null; } },
                set: (k, v) => { try { localStorage.setItem('nh-dr-' + k, v); } catch (e) { } },
                del: k => { try { localStorage.removeItem('nh-dr-' + k); } catch (e) { } },
            };

            function toast(message, type = 'success', duration = 3500) {
                const box = F('crmToastContainer');
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

            async function api(action, params = {}, post = false) {
                const ctrl = new AbortController();
                const timer = setTimeout(() => ctrl.abort(), 15000);
                try {
                    const qs = new URLSearchParams();
                    Object.entries(params).forEach(([k, v]) => Array.isArray(v) ? v.forEach(x => qs.append(k + '[]', x ?? '')) : qs.append(k, v ?? ''));
                    const url = post ? `${AJAX}?action=${action}` : `${AJAX}?action=${action}&${qs}`;
                    const res = await fetch(url, post ? { method: 'POST', body: qs, signal: ctrl.signal, cache: 'no-store' } : { signal: ctrl.signal, cache: 'no-store' });
                    const text = await res.text();
                    let d;
                    try { d = JSON.parse(text); }
                    catch (e) { throw new Error(/login|<form/i.test(text) ? 'Your session may have expired. Please reload the page.' : 'The server sent an unexpected response.'); }
                    if (!d.success) throw new Error(d.message || 'Something went wrong.');
                    return d;
                } catch (e) {
                    throw new Error(e.name === 'AbortError' ? 'The server took too long to respond.' : e.message);
                } finally { clearTimeout(timer); }
            }

            const openM = id => { F(id).classList.remove('hidden'); F(id).classList.add('flex'); };
            const closeM = id => { F(id).classList.add('hidden'); F(id).classList.remove('flex'); };
            document.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => closeM(b.dataset.close)));
            ['drModal', 'drViewModal'].forEach(id => F(id).addEventListener('click', e => { if (e.target === F(id)) closeM(id); }));
            document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeM('drModal'); closeM('drViewModal'); } });

            // ═════════════ LIST ═════════════
            const TABS = [
                { key: 'ready', label: 'Ready for DR' },
                { key: 'waiting', label: 'Waiting for transmittal' },
                { key: 'done', label: 'DR created' },
                { key: 'all', label: 'All' },
            ];
            const STATE = {
                ready:   { bar: 'bg-green-500', pill: 'bg-green-50 text-green-700 ring-green-600/20', icon: 'fa-circle-check', text: 'Transmittal OK' },
                waiting: { bar: 'bg-amber-400', pill: 'bg-amber-50 text-amber-700 ring-amber-600/20', icon: 'fa-hourglass-half', text: 'Not yet delivered' },
                done:    { bar: 'bg-sky-500', pill: 'bg-sky-50 text-sky-700 ring-sky-600/20', icon: 'fa-file-lines', text: 'DR created' },
            };

            function renderTabs() {
                const n = k => k === 'all' ? rows.length : rows.filter(r => r.state === k).length;
                F('drTabs').innerHTML = TABS.map(t => `<button type="button" data-tab="${t.key}" class="px-3 py-1.5 rounded-lg text-xs font-semibold whitespace-nowrap ${filter === t.key ? 'bg-gray-900 text-white' : 'text-gray-500 hover:bg-gray-100'}">${t.label} <span class="${filter === t.key ? 'text-gray-300' : 'text-gray-400'}">${n(t.key)}</span></button>`).join('');
                F('drStatReady').textContent = n('ready');
                F('drStatWaiting').textContent = n('waiting');
                F('drStatDone').textContent = n('done');
            }
            F('drTabs').addEventListener('click', e => { const b = e.target.closest('[data-tab]'); if (b) setFilter(b.dataset.tab); });
            document.querySelectorAll('[data-filter]').forEach(b => b.addEventListener('click', () => setFilter(b.dataset.filter)));
            function setFilter(k) { filter = k; memo.set('filter', k); render(); }
            F('drSearch').addEventListener('input', render);

            function render() {
                renderTabs();
                const q = F('drSearch').value.trim().toLowerCase();
                const list = rows.filter(r => (filter === 'all' || r.state === filter)
                    && (!q || `${r.ref_no} ${r.client_name} ${r.client_company ?? ''} ${r.project_name ?? ''} ${r.req_no ?? ''} ${r.dr_no ?? ''}`.toLowerCase().includes(q)));
                if (!list.length) {
                    const msg = { ready: 'No transmittal is ready for a DR right now.', waiting: 'No transmittals are waiting.', done: 'No DR created yet.', all: 'No transmittals yet.' }[filter];
                    F('drList').innerHTML = `<div class="bg-white border-2 border-dashed border-gray-200 rounded-xl px-6 py-14 text-center"><i class="fa-solid fa-truck-ramp-box text-3xl text-gray-300 mb-3"></i><p class="text-sm font-semibold text-gray-700">${q ? 'No match' : msg}</p>${filter === 'ready' && !q ? '<p class="text-xs text-gray-400 mt-1">A transmittal shows here once it is marked Delivered in the Transmittal Summary.</p>' : ''}</div>`;
                    return;
                }
                F('drList').innerHTML = list.map(r => {
                    const s = STATE[r.state];
                    let action;
                    if (r.state === 'done') action = `<button type="button" data-view="${r.dr_id}" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-sky-700 bg-sky-50 border border-sky-200 rounded-lg hover:bg-sky-100"><i class="fa-solid fa-eye"></i> View DR</button>`;
                    else if (r.state === 'ready') action = `<button type="button" data-create="${r.id}" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 shadow-sm"><i class="fa-solid fa-plus"></i> Create DR</button>`;
                    else action = `<div class="text-right"><button type="button" disabled class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-gray-400 bg-gray-100 border border-gray-200 rounded-lg cursor-not-allowed"><i class="fa-solid fa-lock"></i> Create DR</button>
                        <a href="${SUMMARY_URL}" class="block mt-1 text-[10.5px] text-amber-700 hover:underline">Mark delivered in Transmittal Summary →</a></div>`;
                    return `<article class="relative bg-white border border-gray-200 rounded-xl overflow-hidden hover:border-gray-300">
                        <span class="absolute left-0 top-0 bottom-0 w-1 ${s.bar}"></span>
                        <div class="flex flex-wrap items-center gap-x-5 gap-y-3 pl-5 pr-4 py-3.5">
                            <div class="min-w-0 flex-1 basis-[260px]">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-mono text-sm font-bold text-gray-900">${esc(r.ref_no)}</span>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold ring-1 ${s.pill}"><i class="fa-solid ${s.icon} text-[9px]"></i>${s.text}</span>
                                </div>
                                <p class="text-sm font-semibold text-gray-800 truncate mt-0.5">${esc(r.client_name)}${r.client_company ? ` <span class="font-normal text-gray-400">· ${esc(r.client_company)}</span>` : ''}</p>
                                <p class="text-[11px] text-gray-400 truncate">${esc(r.project_name || r.particulars || '—')}</p>
                            </div>
                            <dl class="grid grid-cols-3 gap-x-5 gap-y-1 text-[11px] basis-[300px] grow sm:grow-0">
                                <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Transmittal</dt><dd class="text-gray-700">${fmtY(r.transmittal_date)}</dd></div>
                                <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Delivered</dt><dd class="${r.delivered_at ? 'text-green-700 font-semibold' : 'text-amber-600'}">${r.delivered_at ? fmtY(r.delivered_at) : 'Pending'}</dd></div>
                                <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">${r.dr_no ? 'DR No.' : 'Items'}</dt><dd class="${r.dr_no ? 'font-mono font-semibold text-sky-700' : 'text-gray-700'}">${r.dr_no ? esc(r.dr_no) : r.item_count}</dd></div>
                            </dl>
                            <div class="ml-auto shrink-0">${action}</div>
                        </div>
                    </article>`;
                }).join('');
            }
            F('drList').addEventListener('click', e => {
                const c = e.target.closest('[data-create]'), v = e.target.closest('[data-view]');
                if (c) openCreate(+c.dataset.create);
                if (v) openView(+v.dataset.view);
            });

            async function load() {
                try {
                    const d = await api('list');
                    rows = d.rows;
                    render();
                } catch (e) {
                    F('drList').innerHTML = `<div class="bg-white border border-gray-200 rounded-xl px-6 py-12 text-center text-xs text-red-600">${esc(e.message)}<br><button type="button" id="drRetry" class="mt-2 font-semibold text-amber-700 hover:underline">Retry</button></div>`;
                    F('drRetry').addEventListener('click', load);
                }
            }

            // ═════════════ CREATE DR ═════════════
            const inp = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-xs outline-none focus:border-amber-600';
            const lbl = (t, req) => `<span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">${t}${req ? ' <span class="text-red-500">*</span>' : ''}</span>`;
            const itemRow = (it = {}) => `<tr class="border-t border-gray-100">
                <td class="p-1.5"><input name="particular" value="${esc(it.particular ?? '')}" placeholder="e.g. Wardrobe" class="${inp}"></td>
                <td class="p-1.5"><input name="size" value="${esc(it.size ?? '')}" placeholder="2400*600*2700" class="${inp}"></td>
                <td class="p-1.5"><input name="qty" type="number" min="0" step="any" value="${esc(it.qty ?? '')}" class="${inp} text-right"></td>
                <td class="p-1.5"><input name="unit" value="${esc(it.unit ?? '')}" list="drUnits" placeholder="SET" class="${inp}"></td>
                <td class="p-1.5"><input name="amount" type="number" min="0" step="any" value="${esc(it.amount ?? '')}" class="${inp} text-right"></td>
                <td class="p-1.5 text-center"><button type="button" class="dr-del w-7 h-7 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50" title="Remove"><i class="fa-solid fa-xmark"></i></button></td>
            </tr>`;

            async function openCreate(trId) {
                let d;
                try { d = await api('transmittal', { id: trId }); } catch (e) { return toast(e.message, 'error'); }
                if (d.dr_id) { toast('A DR already exists for this transmittal.', 'error'); return load(); }
                const tr = d.tr;
                if (!tr.delivered_at) { toast('This transmittal is not yet delivered.', 'error'); return load(); }
                openTr = tr;

                // draft (hindi mawawala kapag nag-close o nag-reload)
                let draft = null;
                try { draft = JSON.parse(memo.get('draft-' + tr.id) || 'null'); } catch (e) { draft = null; }
                const v = draft || {
                    dr_date: todayISO(), scheduled_date: '', client_name: tr.client_name || '', address: tr.client_address || '',
                    vehicle: tr.delivery_mode || '', notes: '',
                    items: tr.items.map(i => ({ particular: [i.description, i.serial_no].filter(Boolean).join(' — '), size: '', qty: qtyFmt(String(i.quantity).replace(/[^\d.]/g, '')), unit: String(i.quantity).replace(/[\d.,\s]/g, '').toUpperCase(), amount: '' })),
                };
                if (!v.items.length) v.items = [{}];

                F('drModalTitle').textContent = `${tr.ref_no} · ${tr.client_name}`;
                F('drModalSub').innerHTML = `Transmittal ${fmtY(tr.transmittal_date)} · delivered <b class="text-green-700">${fmtY(tr.delivered_at)}</b>${tr.req_no ? ' · ' + esc(tr.req_no) : ''} · DR No. will be <span class="font-mono">${esc(d.dr_preview)}</span>`;
                F('drDraftNote').textContent = draft ? 'Restored your unsaved draft.' : 'Items are copied from the transmittal. Edit as needed.';
                F('drModalBody').innerHTML = `
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-4">
                        <label>${lbl('DR Date', 1)}<input type="date" name="dr_date" value="${esc(v.dr_date)}" class="${inp}"></label>
                        <label>${lbl('Delivery Schedule')}<input type="date" name="scheduled_date" value="${esc(v.scheduled_date)}" class="${inp}"></label>
                        <label class="col-span-2">${lbl('Client Name', 1)}<input name="client_name" value="${esc(v.client_name)}" maxlength="255" class="${inp}"></label>
                        <label class="col-span-2">${lbl('Address / Destination')}<input name="address" value="${esc(v.address)}" maxlength="255" class="${inp}"></label>
                        <label class="col-span-2">${lbl('Transport / Vehicle')}<input name="vehicle" value="${esc(v.vehicle)}" maxlength="150" list="drVehicles" placeholder="e.g. Company truck" class="${inp}"></label>
                        <label class="col-span-2 md:col-span-4">${lbl('Notes (site access, contact on site)')}<input name="notes" value="${esc(v.notes)}" maxlength="1000" class="${inp}"></label>
                    </div>
                    <div class="rounded-xl border border-gray-200 overflow-hidden">
                        <table class="w-full table-fixed text-xs">
                            <colgroup><col style="width:36%"><col style="width:20%"><col style="width:12%"><col style="width:12%"><col style="width:14%"><col style="width:6%"></colgroup>
                            <thead class="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500"><tr>
                                <th class="px-2 py-2 text-left font-semibold">Particular <span class="text-red-500">*</span></th><th class="px-2 py-2 text-left font-semibold">Size</th>
                                <th class="px-2 py-2 text-right font-semibold">Qty <span class="text-red-500">*</span></th><th class="px-2 py-2 text-left font-semibold">Unit</th>
                                <th class="px-2 py-2 text-right font-semibold">Amount</th><th></th></tr></thead>
                            <tbody id="drItems">${v.items.map(itemRow).join('')}</tbody>
                        </table>
                    </div>
                    <div class="flex items-center justify-between mt-2">
                        <button type="button" id="drAddRow" class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-700 hover:underline"><i class="fa-solid fa-plus text-[10px]"></i> Add item</button>
                        <p class="text-[11px] text-gray-500">Total: <b id="drTotal" class="text-gray-900">₱ 0.00</b></p>
                    </div>
                    <p id="drError" class="hidden mt-3 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-[11px] text-red-700"></p>
                    <datalist id="drUnits"><option>SET<option>PCS<option>PC<option>LOT<option>SQM<option>LM<option>PAIRS<option>BOX</datalist>
                    <datalist id="drVehicles"><option>Company truck<option>Lalamove<option>Pick up by client</datalist>`;

                const tb = F('drItems');
                F('drAddRow').addEventListener('click', () => { tb.insertAdjacentHTML('beforeend', itemRow()); changed(); });
                tb.addEventListener('click', e => {
                    const b = e.target.closest('.dr-del'); if (!b) return;
                    if (tb.rows.length > 1) b.closest('tr').remove(); else b.closest('tr').querySelectorAll('input').forEach(i => i.value = '');
                    changed();
                });
                F('drModalBody').addEventListener('input', changed);
                calcTotal();
                openM('drModal');
                setTimeout(() => F('drModalBody').querySelector('[name=scheduled_date]').focus(), 50);
            }

            const val = n => F('drModalBody').querySelector(`[name="${n}"]`).value.trim();
            const col = n => [...F('drItems').querySelectorAll(`[name="${n}"]`)].map(i => i.value.trim());
            function readForm() {
                const p = col('particular'), s = col('size'), q = col('qty'), u = col('unit'), a = col('amount');
                return {
                    dr_date: val('dr_date'), scheduled_date: val('scheduled_date'), client_name: val('client_name'), address: val('address'),
                    vehicle: val('vehicle'), notes: val('notes'),
                    items: p.map((x, i) => ({ particular: x, size: s[i], qty: q[i], unit: u[i], amount: a[i] })),
                };
            }
            function calcTotal() {
                const t = readForm().items.reduce((sum, i) => sum + num(i.amount) * (num(i.qty) || 1), 0);
                F('drTotal').textContent = '₱ ' + money(t);
            }
            let draftTimer = null;
            function changed() {
                calcTotal();
                clearTimeout(draftTimer);
                draftTimer = setTimeout(() => { memo.set('draft-' + openTr.id, JSON.stringify(readForm())); F('drDraftNote').textContent = 'Draft saved on this device.'; }, 400);
            }
            const drErr = m => { F('drError').textContent = m || ''; F('drError').classList.toggle('hidden', !m); };

            F('drSave').addEventListener('click', async function () {
                const f = readForm();
                const items = f.items.filter(i => i.particular);
                drErr('');
                if (!f.client_name) return drErr('Client name is required.');
                if (!items.length) return drErr('Add at least one item.');
                const noQty = items.find(i => num(i.qty) <= 0);
                if (noQty) return drErr(`Enter the quantity for "${noQty.particular}".`);
                if (f.scheduled_date && f.scheduled_date < f.dr_date) return drErr('Delivery schedule cannot be earlier than the DR date.');
                this.disabled = true;
                try {
                    const d = await api('save_dr', {
                        transmittal_id: openTr.id, dr_date: f.dr_date, scheduled_date: f.scheduled_date, client_name: f.client_name,
                        address: f.address, vehicle: f.vehicle, notes: f.notes,
                        particular: items.map(i => i.particular), size: items.map(i => i.size), qty: items.map(i => i.qty),
                        unit: items.map(i => i.unit), amount: items.map(i => i.amount),
                    }, true);
                    memo.del('draft-' + openTr.id);
                    closeM('drModal');
                    toast(`DR# ${d.dr_no} created.`);
                    await load();
                    openView(d.id);
                } catch (e) { drErr(e.message); }
                finally { this.disabled = false; }
            });

            // ═════════════ VIEW / PRINT DR ═════════════
            let viewHtml = '';
            async function openView(id) {
                let d;
                try { d = await api('dr', { id }); } catch (e) { return toast(e.message, 'error'); }
                viewHtml = drDoc(d.dr);
                F('drViewTitle').textContent = `DR# ${d.dr.dr_no} · Transmittal ${d.dr.ref_no}`;
                F('drViewBody').innerHTML = viewHtml;
                openM('drViewModal');
            }
            F('drPrint').addEventListener('click', () => {
                let area = F('nhPrintArea');
                if (!area) { area = document.createElement('div'); area.id = 'nhPrintArea'; document.body.appendChild(area); }
                area.innerHTML = viewHtml;
                const imgs = [...area.querySelectorAll('img')].filter(i => !i.complete);
                Promise.all(imgs.map(i => new Promise(r => { i.onload = i.onerror = r; }))).then(() => { window.print(); setTimeout(() => { area.innerHTML = ''; }, 500); });
            });

            function drDoc(d) {
                const total = d.items.reduce((a, i) => a + num(i.amount) * (num(i.qty) || 1), 0);
                const rowsHtml = d.items.map((i, k) => `<tr><td class="c">${k + 1}</td><td class="b">${esc(i.particular)}</td><td class="c">${esc(i.size)}</td><td class="c">${qtyFmt(i.qty)}</td><td>${esc(i.unit)}</td>
                    <td class="r">${num(i.amount) ? money(i.amount) : ''}</td><td class="r">${num(i.amount) ? money(num(i.amount) * (num(i.qty) || 1)) : '-'}</td></tr>`).join('');
                return `<div class="drdoc">
                    <div class="head"><div class="title">DELIVERY RECEIPT</div>
                        <table><tr>
                            <td style="width:22%"><img src="${LOGO}" alt="" style="width:92px"></td>
                            <td style="width:40%" class="co"><b>Noblehome Construction Corporation</b><br>MC Premier 2F, Quezon City, Metro Manila, Philippines<br>(02) 8822-1295 / (+63)993-548-7799</td>
                            <td><table class="meta">
                                <tr><td>Client Name:</td><td><b>${esc(d.client_name)}</b></td></tr>
                                <tr><td>Address:</td><td><b>${esc(d.address || '')}</b></td></tr>
                                <tr><td>Date:</td><td><b>${esc(fmtSlash(d.dr_date))}</b></td></tr>
                                <tr><td>DR #:</td><td><b>${esc(d.dr_no)}</b></td></tr></table></td>
                        </tr></table></div>
                    <table class="tbl"><colgroup><col style="width:4%"><col style="width:34%"><col style="width:18%"><col style="width:10%"><col style="width:8%"><col style="width:12%"><col style="width:14%"></colgroup>
                        <tr class="th"><td></td><td>Particular</td><td>Size</td><td>Quantity</td><td>Unit</td><td>Amount</td><td>Total</td></tr>
                        ${rowsHtml}
                        <tr><td colspan="7" class="c i">-------------------NOTHING FOLLOWS-------------------</td></tr>
                        <tr><td colspan="6" class="r b">TOTAL:</td><td class="r b">${money(total)}</td></tr>
                    </table>
                    ${d.notes ? `<p style="margin-top:6px"><b>Notes:</b> ${esc(d.notes)}</p>` : ''}
                    <table class="terms"><tr><td style="width:55%">
                        <p class="blue">TERMS AND CONDITIONS</p>
                        <p>1. Payment Terms — 50% Downpayment, 40% upon Delivery, 10% after completion</p>
                        <p>2. Inspection and Acceptance — All materials delivered and installed must be inspected the day after the completion.</p>
                        <p>3. Validity — This Delivery Receipt is not an Invoice. This serves as proof of delivery only.</p>
                    </td><td><p>Account Name: <b>Noblehome Construction Corp.</b></p><p>Account Number: <b>013-238-001-657</b></p><p>Bank: <b>BDO UNIBANK</b></p></td></tr></table>
                    <table class="sign"><tr>
                        <td><div class="nm">${esc(d.prepared_by || '')}</div><div class="ln">Prepared By:</div></td>
                        <td><div class="nm">Mr. Ken Yang</div><div class="ln">Noted By:</div></td>
                        <td><div class="nm"></div><div class="ln">Received By:</div></td>
                    </tr></table>
                </div>`;
            }

            // ═════════════ INIT ═════════════
            filter = memo.get('filter') || 'ready';
            load();
        })();
    </script>
</body>

</html>