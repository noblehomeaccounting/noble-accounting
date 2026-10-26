<?php
// coordinator-labor.php  —  Project Coordinator: Step 3 · Subcontractor / Labor
// Para sa DR na approved na: Foreman ID muna → Labor Quotation (labor only) → Superadmin (Conforme) → saka lang ma-print.

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PC];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$lbAjaxUrl = BASE_URL . '/coordinatorlaborajax';
$drPageUrl = BASE_URL . '/coordinator';   // Delivery Receipts page
$tlPageUrl = BASE_URL . '/coordinatortimeline';   // Timeline page (hiwalay na page)
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Labor Quotations</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/ui-pc/css/pc/coordinator-labor.css?v=<?= @filemtime(ROOT_PATH . '/admin/ui-pc/css/pc/coordinator-labor.css') ?: 1 ?>">
</head>

<body class="bg-gray-50">
    <main id="main-content" class="md:ml-56 pt-20 md:pt-5 min-h-screen p-4 md:p-8 transition-all duration-300">

        <!-- ═══ HEADER ═══ -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-helmet-safety text-lg"></i></div>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-amber-700">Operations · Step 3</p>
                    <h1 class="text-xl font-bold text-gray-900 leading-tight">Subcontractor / Labor Quotations</h1>
                    <p class="text-xs text-gray-500">For DRs signed by the Superadmin. Add the foreman's ID first, then
                        make the labor quotation. You can print it once the Superadmin approves it.</p>
                </div>
            </div>
            <a href="<?= htmlspecialchars($drPageUrl) ?>"
                class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg px-3 py-2 hover:bg-gray-50"><i
                    class="fa-solid fa-truck-ramp-box"></i> Delivery Receipts</a>
        </div>

        <!-- ═══ STAT TABS ═══ -->
        <div id="lpTabs" class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 mb-4">
            <?php foreach ([
                ['todo', 'To do', 'fa-list-check', 'bg-amber-50 text-amber-700'],
                ['foreman', 'Needs foreman', 'fa-id-badge', 'bg-orange-50 text-orange-600'],
                ['pending', 'For approval', 'fa-signature', 'bg-sky-50 text-sky-600'],
                ['returned', 'Returned', 'fa-rotate-left', 'bg-red-50 text-red-600'],
                ['approved', 'Approved', 'fa-circle-check', 'bg-emerald-50 text-emerald-600'],
                ['all', 'All', 'fa-layer-group', 'bg-gray-100 text-gray-600'],
            ] as [$k, $label, $icon, $cls]): ?>
                <button type="button" data-tab="<?= $k ?>"
                    class="lp-tab text-left bg-white border border-gray-200 rounded-xl p-3.5 flex items-center gap-3 shadow-sm min-w-0 transition hover:border-amber-400 data-[active=true]:border-amber-600 data-[active=true]:ring-2 data-[active=true]:ring-amber-100 data-[active=true]:bg-amber-50/40">
                    <span class="hidden sm:flex w-9 h-9 rounded-lg items-center justify-center shrink-0 <?= $cls ?>"><i
                            class="fa-solid <?= $icon ?>"></i></span>
                    <span class="min-w-0">
                        <span
                            class="block text-[10.5px] font-semibold text-gray-500 uppercase tracking-wide truncate"><?= $label ?></span>
                        <span class="block text-2xl font-bold text-gray-900 leading-tight tabular-nums"
                            data-count="<?= $k ?>">—</span>
                    </span>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- ═══ LIST ═══ -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-gray-100">
                <div class="relative flex-1 min-w-[200px]">
                    <i
                        class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                    <input type="text" id="lpSearch" placeholder="Search DR no., client, quotation no., foreman…"
                        class="w-full h-9 bg-white border border-gray-200 rounded-lg pl-9 pr-3 text-xs outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100">
                </div>
            </div>
            <div id="lpInfo" class="px-4 py-2 text-[11px] text-gray-500 bg-gray-50 border-b border-gray-100">&nbsp;
            </div>
            <div id="lpList" class="divide-y divide-gray-100">
                <div class="px-6 py-14 text-center text-xs text-gray-400"><i
                        class="fa-solid fa-spinner fa-spin mr-1"></i> Loading…</div>
            </div>
        </div>

        <div id="crmToastContainer"
            class="fixed bottom-4 right-4 z-[10000] flex flex-col gap-2.5 pointer-events-none w-[340px] max-w-[calc(100vw-2rem)]">
        </div>
    </main>

    <!-- ===================== STEP 3 · LABOR MODAL (Foreman → Labor Quotation) ===================== -->
    <div id="lbModal"
        class="hidden fixed inset-0 z-[9999] items-start justify-center bg-black/50 p-3 md:p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-5xl my-auto overflow-hidden">
            <div class="dr-hazard h-1.5"></div>
            <div class="flex items-start justify-between gap-3 px-5 py-4 border-b border-gray-100">
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-amber-700">Step 3 · Subcontractor /
                        Labor</p>
                    <p id="lbTitle" class="text-base font-semibold text-gray-900 truncate"></p>
                    <p id="lbSub" class="text-[11px] text-gray-400 truncate"></p>
                </div>
                <button type="button" data-close="lbModal"
                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 shrink-0" aria-label="Close"><i class="fa-solid fa-xmark text-base leading-none"></i></button>
            </div>
            <!-- progress -->
            <div id="lbSteps"
                class="flex items-center gap-2 px-5 py-3 bg-gray-50 border-b border-gray-100 text-[11px] font-semibold overflow-x-auto">
            </div>
            <div class="p-5 space-y-5">
                <section id="lbForeman"></section>
                <section id="lbQuote"></section>
            </div>
        </div>
    </div>
    <script>
        (function () {
            'use strict';
            const AJAX = <?= json_encode($lbAjaxUrl) ?>;
            const TIMELINE_URL = <?= json_encode($tlPageUrl) ?>;
            const F = id => document.getElementById(id);
            let rows = [], tab = 'todo', lastSig = '';

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
                get: k => { try { return localStorage.getItem('nh-lb-' + k); } catch (e) { return null; } },
                set: (k, v) => { try { localStorage.setItem('nh-lb-' + k, v); } catch (e) { } },
                del: k => { try { localStorage.removeItem('nh-lb-' + k); } catch (e) { } },
            };
            const inp = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-xs outline-none focus:border-amber-600';
            const lbl = (t, req) => `<span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">${t}${req ? ' <span class="text-red-500">*</span>' : ''}</span>`;

            function toast(message, type = 'success', duration = 3500) {
                const p = type === 'success'
                    ? { wrap: 'bg-green-50 border-green-200 text-green-700', icon: 'bg-green-200 text-green-700', sym: '✓' }
                    : { wrap: 'bg-red-50 border-red-200 text-red-700', icon: 'bg-red-200 text-red-700', sym: '!' };
                const t = document.createElement('div');
                t.className = `pointer-events-auto flex items-start gap-2.5 border rounded-lg shadow-lg px-4 py-3 text-sm ${p.wrap} transition-all duration-300`;
                t.innerHTML = `<span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold ${p.icon}">${p.sym}</span><span class="flex-1 leading-relaxed"></span>`;
                t.querySelector('.flex-1').textContent = message;
                F('crmToastContainer').appendChild(t);
                setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, duration);
            }

            async function api(action, params = {}, post = false) {
                const ctrl = new AbortController();
                const timer = setTimeout(() => ctrl.abort(), 15000);
                try {
                    const qs = new URLSearchParams();
                    Object.entries(params).forEach(([k, v]) => qs.append(k, v ?? ''));
                    const url = post ? `${AJAX}?action=${action}` : `${AJAX}?action=${action}&${qs}`;
                    const res = await fetch(url, post ? { method: 'POST', body: qs, signal: ctrl.signal, cache: 'no-store' } : { signal: ctrl.signal, cache: 'no-store' });
                    const text = await res.text();
                    let d;
                    try { d = JSON.parse(text); }
                    catch (e) { throw new Error(/<html|<!doctype/i.test(text) ? 'The server returned a page instead of data. Check that "coordinatorlaborajax" is registered in the router, or reload if your session expired.' : 'The server sent an unexpected response.'); }
                    if (!d.success) throw new Error(d.message || 'Something went wrong.');
                    return d;
                } catch (e) {
                    throw new Error(e.name === 'AbortError' ? 'The server took too long to respond.' : e.message);
                } finally { clearTimeout(timer); }
            }

            const openM = id => { F(id).classList.remove('hidden'); F(id).classList.add('flex'); };
            const closeM = id => { F(id).classList.add('hidden'); F(id).classList.remove('flex'); };
            document.querySelectorAll('[data-close]').forEach(b => b.addEventListener('click', () => { closeM(b.dataset.close); load(true); }));
            F('lbModal').addEventListener('click', e => { if (e.target === F('lbModal')) { closeM('lbModal'); load(true); } });
            document.addEventListener('keydown', e => { if (e.key === 'Escape' && F('lbModal').classList.contains('flex')) { closeM('lbModal'); load(true); } });

            // ── Labor quotation document (kapareho ng papel na Quotation for Works – Labor Only) ──
            const LQ_LETTER = n => { let s = ''; n = +n; while (n > 0) { const m = (n - 1) % 26; s = String.fromCharCode(65 + m) + s; n = Math.floor((n - 1) / 26); } return s; };
            function lqTotal(items) { return items.reduce((a, i) => a + Math.round(num(i.qty) * num(i.unit_cost) * 100) / 100, 0); }
            function lqDoc(q, opts = {}) {
                const fmtLong = s => s ? toD(s).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : '';
                const secs = [];
                q.items.forEach(i => {
                    let s = secs.find(x => x.no === +i.section_no);
                    if (!s) { s = { no: +i.section_no, name: i.section_name, items: [] }; secs.push(s); }
                    s.items.push(i);
                });
                secs.sort((a, b) => a.no - b.no);
                const body = secs.map(s => `<tr class="sec"><td class="c b">${LQ_LETTER(s.no)}</td><td class="b" colspan="4">${esc(s.name)}</td><td class="r">-</td></tr>` +
                    s.items.map(i => `<tr><td class="c">${LQ_LETTER(s.no)}.${i.item_no}</td><td>${esc(i.item)}</td><td class="r">${qtyFmt(i.qty)}</td><td>${esc(i.unit || '')}</td>
                        <td class="r">${money(i.unit_cost)}</td><td class="r">${money(Math.round(num(i.qty) * num(i.unit_cost) * 100) / 100)}</td></tr>`).join('')).join('');
                const dp = q.downpayment_pct === null || q.downpayment_pct === '' || q.downpayment_pct === undefined ? null : +num(q.downpayment_pct).toFixed(2);
                const approved = q.status === 'approved';
                const sig = approved && q.signature_url ? `<img src="${esc(q.signature_url)}" alt="Signature">` : (opts.preview ? `<img src="${esc(opts.preview)}" alt="" style="opacity:.55">` : (opts.signHere ? '<span class="here">Sign here</span>' : ''));
                return `<div class="lqdoc">
                    <table class="meta"><colgroup><col style="width:17%"><col style="width:3%"><col></colgroup>
                        <tr><td>Project Name</td><td>:</td><td class="b">${esc(q.project_name || '')}</td></tr>
                        <tr><td>Location</td><td>:</td><td class="b">${esc(q.location || '')}</td></tr>
                        <tr><td>Subject</td><td>:</td><td><b>${esc(q.subject || '')}</b></td></tr>
                        <tr><td>Date</td><td>:</td><td class="b">${esc(fmtLong(q.quote_date))}</td></tr>
                    </table>
                    ${q.quote_no ? `<p class="qno">${esc(q.quote_no)}</p>` : ''}
                    <div class="ttl">QUOTATION for RENOVATION WORKS (LABOR ONLY)</div>
                    <table class="tbl"><colgroup><col style="width:8%"><col style="width:40%"><col style="width:10%"><col style="width:8%"><col style="width:16%"><col style="width:18%"></colgroup>
                        <tr class="th"><td rowspan="2">Item<br>No.</td><td rowspan="2">Items</td><td rowspan="2">Qty</td><td rowspan="2">Unit</td><td colspan="2">Labor Cost</td></tr>
                        <tr class="th"><td>Unit Cost</td><td>Amount</td></tr>
                        ${body}
                        <tr class="tot"><td colspan="5" class="c b">TOTAL</td><td class="r b">${money(lqTotal(q.items))}</td></tr>
                    </table>
                    <div class="terms"><p class="b">Terms and Conditions:</p>
                        <p>1. Client shall provide necessary permit for workers passage to the project site.</p>
                        ${dp !== null ? `<p>2. Terms of Payment : The Client shall pay the CONTRACTOR ${dp}% Down Payment, and ${+(100 - dp).toFixed(2)}% Progress Billing</p>` : ''}
                        ${q.duration_days ? `<p>${dp !== null ? 3 : 2}. Duration of Work: ${esc(q.duration_days)} calendar days</p>` : ''}
                    </div>
                    <table class="sign"><tr>
                        <td><p class="lbl">Submitted by:</p><div class="sig"></div><div class="ln">${esc(q.subcontractor || '')}</div><div class="role">Contractor</div></td>
                        <td><p class="lbl">Conforme:</p><div class="sig" data-conf-sig>${sig}</div><div class="ln" data-conf-name>${approved ? esc(q.approved_by || '') : (opts.previewName ? esc(opts.previewName) : '')}</div>
                            ${approved && q.approved_at ? `<div class="role">${esc(fmtSlash(q.approved_at))}</div>` : ''}</td>
                    </tr></table>
                </div>`;
            }

            // ═════════════ STEP 3 · LABOR (Foreman ID → Labor Quotation → Superadmin) ═════════════
            let lb = null;   // { dr, foreman, quote, me, editingForeman, editingQuote }
            const LB_STATE = {
                foreman: { cls: 'bg-amber-50 text-amber-700 ring-amber-600/20', icon: 'fa-id-badge', text: 'Add foreman ID' },
                quote: { cls: 'bg-amber-50 text-amber-700 ring-amber-600/20', icon: 'fa-file-pen', text: 'Make labor quote' },
                pending: { cls: 'bg-sky-50 text-sky-700 ring-sky-600/20', icon: 'fa-signature', text: 'Labor quote for approval' },
                returned: { cls: 'bg-red-50 text-red-700 ring-red-600/20', icon: 'fa-rotate-left', text: 'Labor quote returned' },
                approved: { cls: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', icon: 'fa-circle-check', text: 'Labor quote approved' },
            };
            function laborChip(st) {
                const s = LB_STATE[st];
                return s ? `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold ring-1 ${s.cls}"><i class="fa-solid ${s.icon} text-[9px]"></i>${s.text}</span>` : '';
            }

            async function apiForm(action, fd) {
                const ctrl = new AbortController();
                const timer = setTimeout(() => ctrl.abort(), 60000);
                try {
                    const res = await fetch(`${AJAX}?action=${action}`, { method: 'POST', body: fd, signal: ctrl.signal, cache: 'no-store' });
                    const text = await res.text();
                    let d;
                    try { d = JSON.parse(text); }
                    catch (e) { throw new Error(res.status === 413 ? 'The file is too large for the server.' : /login|<form/i.test(text) ? 'Your session may have expired. Please reload the page.' : 'The server sent an unexpected response.'); }
                    if (!d.success) { const er = new Error(d.message || 'Something went wrong.'); er.field = d.field; throw er; }
                    return d;
                } catch (e) {
                    if (e.name === 'AbortError') throw new Error('The upload took too long. Please try again.');
                    throw e;
                } finally { clearTimeout(timer); }
            }

            async function openLabor(drId) {
                let d;
                try { d = await api('labor_get', { dr_id: drId }); } catch (e) { return toast(e.message, 'error'); }
                lb = { dr: d.dr, foreman: d.foreman, quote: d.quote, me: d.me, preview: d.quote_preview, editingForeman: !d.foreman, editingQuote: false };
                F('lbTitle').textContent = `DR# ${d.dr.dr_no} · ${d.dr.client_name}`;
                F('lbSub').textContent = `Transmittal ${d.dr.ref_no}${d.dr.project_name ? ' · ' + d.dr.project_name : ''}${d.dr.address ? ' · ' + d.dr.address : ''}`;
                renderLabor();
                openM('lbModal');
            }

            function renderLabor() {
                const q = lb.quote, st = !lb.foreman ? 'foreman' : !q ? 'quote' : q.status;
                const steps = [
                    ['Foreman ID', !!lb.foreman],
                    ['Labor quotation', !!q],
                    ['Superadmin approval', q && q.status === 'approved'],
                ];
                F('lbSteps').innerHTML = steps.map(([t, done], i) => `${i ? '<i class="fa-solid fa-chevron-right text-[9px] text-gray-300"></i>' : ''}
                    <span class="inline-flex items-center gap-1.5 whitespace-nowrap ${done ? 'text-emerald-700' : 'text-gray-500'}">
                        <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] ${done ? 'bg-emerald-600 text-white' : 'bg-white border border-gray-300'}">${done ? '<i class="fa-solid fa-check text-[9px]"></i>' : i + 1}</span>${t}</span>`).join('')
                    + `<span class="ml-auto pl-3">${laborChip(st)}</span>`;
                renderForeman();
                renderQuote();
            }

            // ── 1) Foreman ──
            const fTitle = (n, t, sub) => `<div class="flex items-center gap-2.5 mb-3"><span class="w-7 h-7 rounded-lg bg-gray-900 text-white text-xs font-bold flex items-center justify-center shrink-0">${n}</span>
                <div class="min-w-0"><p class="text-sm font-bold text-gray-900">${t}</p>${sub ? `<p class="text-[11px] text-gray-400">${sub}</p>` : ''}</div></div>`;
            function renderForeman() {
                const box = F('lbForeman'), f = lb.foreman, locked = lb.quote && lb.quote.status === 'approved';
                if (f && !lb.editingForeman) {
                    box.innerHTML = fTitle(1, 'Foreman', 'Needed before the labor quotation.') + `
                        <div class="flex flex-wrap items-center gap-4 rounded-xl border border-gray-200 p-3.5">
                            <a href="${esc(f.id_photo_url)}" target="_blank" rel="noopener" class="block w-32 h-20 rounded-lg overflow-hidden border border-gray-200 bg-gray-50 shrink-0" title="Open ID photo">
                                <img src="${esc(f.id_photo_url)}" alt="Foreman ID" class="w-full h-full object-cover"></a>
                            <dl class="grid grid-cols-2 sm:grid-cols-4 gap-x-5 gap-y-1.5 text-[11px] flex-1 min-w-[220px]">
                                <div class="col-span-2 sm:col-span-1"><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Name</dt><dd class="text-sm font-semibold text-gray-900">${esc(f.name)}</dd></div>
                                <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Contact</dt><dd class="text-gray-700">${esc(f.contact_no || '—')}</dd></div>
                                <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">ID type</dt><dd class="text-gray-700">${esc(f.id_type || '—')}</dd></div>
                                <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">ID no.</dt><dd class="font-mono text-gray-700">${esc(f.id_number || '—')}</dd></div>
                            </dl>
                            ${locked ? '<span class="text-[11px] text-gray-400"><i class="fa-solid fa-lock mr-1"></i>Locked</span>' : '<button type="button" id="fmEdit" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50"><i class="fa-solid fa-pen"></i> Edit</button>'}
                        </div>`;
                    if (!locked) F('fmEdit').addEventListener('click', () => { lb.editingForeman = true; renderForeman(); });
                    return;
                }
                const key = 'fm-' + lb.dr.id;
                let dft = null; try { dft = JSON.parse(memo.get(key) || 'null'); } catch (e) { }
                const v = dft || { name: f?.name || '', contact_no: f?.contact_no || '', id_type: f?.id_type || '', id_number: f?.id_number || '' };
                box.innerHTML = fTitle(1, 'Foreman', "Add the foreman's name and ID first. You can make the labor quotation after this.") + `
                    <form id="fmForm" class="rounded-xl border-2 border-amber-200 bg-amber-50/30 p-4" novalidate>
                        <div class="grid md:grid-cols-[1fr_220px] gap-4">
                            <div class="grid grid-cols-2 gap-3 content-start">
                                <label class="col-span-2">${lbl('Foreman full name', 1)}<input name="name" value="${esc(v.name)}" maxlength="255" placeholder="e.g. Dennis Relos" class="${inp}"><span data-err="name" class="hidden block text-[11px] text-red-600 mt-1"></span></label>
                                <label>${lbl('Contact no.')}<input name="contact_no" value="${esc(v.contact_no)}" maxlength="50" inputmode="tel" placeholder="09xx xxx xxxx" class="${inp}"><span data-err="contact_no" class="hidden block text-[11px] text-red-600 mt-1"></span></label>
                                <label>${lbl('ID type')}<input name="id_type" value="${esc(v.id_type)}" maxlength="100" list="fmIdTypes" placeholder="Company ID" class="${inp}"></label>
                                <label class="col-span-2">${lbl('ID number')}<input name="id_number" value="${esc(v.id_number)}" maxlength="100" class="${inp}"></label>
                            </div>
                            <div>
                                ${lbl('Photo of ID', !f)}
                                <label for="fmPhoto" class="flex flex-col items-center justify-center h-36 rounded-xl border-2 border-dashed border-gray-300 bg-white hover:border-amber-500 cursor-pointer overflow-hidden transition">
                                    <img id="fmPrev" src="${esc(f?.id_photo_url || '')}" alt="" class="${f ? '' : 'hidden'} max-h-full max-w-full object-contain">
                                    <span id="fmPh" class="${f ? 'hidden' : ''} text-center text-gray-400 px-3"><i class="fa-solid fa-id-card text-2xl mb-1"></i><span class="block text-[11px]">Click to upload<br>JPG, PNG, WEBP · max 5 MB</span></span>
                                </label>
                                <input type="file" id="fmPhoto" name="id_photo" accept="image/jpeg,image/png,image/webp" class="hidden">
                                <span data-err="id_photo" class="hidden block text-[11px] text-red-600 mt-1"></span>
                                ${f ? '<p class="text-[10.5px] text-gray-400 mt-1">Leave as is to keep the current photo.</p>' : ''}
                            </div>
                        </div>
                        <datalist id="fmIdTypes"><option>Company ID<option>PhilSys National ID<option>Driver's License<option>UMID<option>Voter's ID<option>Passport</datalist>
                        <div class="flex items-center justify-end gap-2 mt-4">
                            ${f ? '<button type="button" id="fmCancel" class="px-3.5 py-2 text-xs font-semibold text-gray-500 bg-white border border-gray-200 rounded-lg hover:bg-gray-100">Cancel</button>' : ''}
                            <button type="submit" id="fmSave" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:opacity-60"><i class="fa-solid fa-floppy-disk"></i> <span>Save foreman</span></button>
                        </div>
                    </form>`;
                const form = F('fmForm');
                const setErr = (field, msg) => {
                    const el = form.querySelector(`[data-err="${field}"]`), inpEl = form.querySelector(`[name="${field}"]`);
                    if (el) { el.textContent = msg || ''; el.classList.toggle('hidden', !msg); }
                    if (inpEl) msg ? inpEl.setAttribute('aria-invalid', 'true') : inpEl.removeAttribute('aria-invalid');
                    if (inpEl && inpEl.type !== 'file') inpEl.classList.toggle('border-red-400', !!msg);
                };
                form.addEventListener('input', e => {
                    if (e.target.name) setErr(e.target.name, '');
                    if (e.target.type !== 'file') memo.set(key, JSON.stringify(Object.fromEntries(['name', 'contact_no', 'id_type', 'id_number'].map(n => [n, form.elements[n].value]))));
                });
                F('fmPhoto').addEventListener('change', function () {
                    setErr('id_photo', '');
                    const file = this.files[0];
                    if (!file) return;
                    if (!/^image\/(jpeg|png|webp)$/.test(file.type)) { this.value = ''; return setErr('id_photo', 'Use a JPG, PNG or WEBP image.'); }
                    if (file.size > 5 * 1024 * 1024) { this.value = ''; return setErr('id_photo', 'The photo is too large (max 5 MB).'); }
                    F('fmPrev').src = URL.createObjectURL(file); F('fmPrev').classList.remove('hidden'); F('fmPh').classList.add('hidden');
                });
                if (F('fmCancel')) F('fmCancel').addEventListener('click', () => { lb.editingForeman = false; memo.del(key); renderForeman(); });
                form.addEventListener('submit', async e => {
                    e.preventDefault();
                    const name = form.elements.name.value.trim(), contact = form.elements.contact_no.value.trim();
                    let bad = false;
                    if (name.length < 2) { setErr('name', "Enter the foreman's full name."); bad = true; }
                    if (contact && !/^[0-9+()\-\s]{7,20}$/.test(contact)) { setErr('contact_no', 'Enter a valid contact number.'); bad = true; }
                    if (!f && !F('fmPhoto').files.length) { setErr('id_photo', "Upload a photo of the foreman's ID."); bad = true; }
                    if (bad) return;
                    const btn = F('fmSave'); btn.disabled = true; btn.querySelector('span').textContent = 'Saving…';
                    const fd = new FormData(form); fd.append('dr_id', lb.dr.id);
                    if (!F('fmPhoto').files.length) fd.delete('id_photo');
                    try {
                        const d = await apiForm('save_foreman', fd);
                        memo.del(key);
                        lb.foreman = d.foreman; lb.editingForeman = false;
                        toast('Foreman saved. You can now make the labor quotation.');
                        renderLabor(); load(true);
                    } catch (er) {
                        if (er.field) setErr(er.field, er.message); else toast(er.message, 'error');
                        btn.disabled = false; btn.querySelector('span').textContent = 'Save foreman';
                    }
                });
            }

            // ── 2) Labor quotation ──
            function renderQuote() {
                const box = F('lbQuote'), q = lb.quote;
                const head = fTitle(2, 'Labor quotation (labor only)', 'Goes to the Superadmin for signing (Conforme). You can print it once approved.');
                if (!lb.foreman) {
                    box.innerHTML = head + `<div class="rounded-xl border-2 border-dashed border-gray-200 px-6 py-10 text-center">
                        <i class="fa-solid fa-lock text-2xl text-gray-300 mb-2"></i><p class="text-sm font-semibold text-gray-700">Add the foreman's ID first</p>
                        <p class="text-xs text-gray-400 mt-1">The labor quotation unlocks once the foreman is saved.</p></div>`;
                    return;
                }
                if (q && !lb.editingQuote && q.status !== 'returned') {
                    const banner = q.status === 'approved'
                        ? `<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-800 flex flex-wrap items-center gap-3"><span class="flex-1 min-w-[200px]"><i class="fa-solid fa-circle-check mr-1"></i><b>Approved</b> and signed by ${esc(q.approved_by || 'Superadmin')} on ${fmtY(q.approved_at)}.</span>
                            <button type="button" id="lqPrint" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-gray-900 rounded-lg hover:bg-gray-800"><i class="fa-solid fa-print"></i> Print / Save PDF</button></div>`
                        : `<div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-xs text-sky-800 flex flex-wrap items-center gap-3"><span class="flex-1 min-w-[200px]"><i class="fa-solid fa-hourglass-half mr-1"></i><b>${esc(q.quote_no)}</b> is waiting for the Superadmin's signature. Printing unlocks once it is approved.</span>
                            <button type="button" id="lqEdit" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-amber-800 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100"><i class="fa-solid fa-pen"></i> Edit</button>
                            <button type="button" disabled title="You can print once the Superadmin approves it." class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-gray-400 bg-gray-200 rounded-lg cursor-not-allowed"><i class="fa-solid fa-lock"></i> Print</button></div>`;
                    box.innerHTML = head + banner + `<div class="mt-3 p-4 bg-slate-50 rounded-xl"><div class="overflow-x-auto"><div class="min-w-[680px] bg-white border border-gray-200 rounded-lg p-6 ${q.status === 'approved' ? '' : 'dr-wm'}">${lqDoc(q)}</div></div></div>`;
                    if (F('lqEdit')) F('lqEdit').addEventListener('click', () => { lb.editingQuote = true; renderQuote(); });
                    if (F('lqPrint')) F('lqPrint').addEventListener('click', () => printHtml(lqDoc(q)));
                    return;
                }
                buildQuoteForm(box, head);
            }

            function buildQuoteForm(box, head) {
                const q = lb.quote, key = 'lq-' + lb.dr.id;
                let dft = null; try { dft = JSON.parse(memo.get(key) || 'null'); } catch (e) { }
                // ibalik sa sections ang items ng quote
                const fromQuote = () => {
                    const secs = [];
                    q.items.forEach(i => { let s = secs.find(x => x.no === +i.section_no); if (!s) { s = { no: +i.section_no, name: i.section_name, items: [] }; secs.push(s); } s.items.push({ item: i.item, qty: qtyFmt(i.qty), unit: i.unit, unit_cost: String(+num(i.unit_cost).toFixed(2)) }); });
                    return secs.sort((a, b) => a.no - b.no).map(s => ({ name: s.name, items: s.items }));
                };
                const v = dft || (q ? {
                    subcontractor: q.subcontractor, subject: q.subject, quote_date: String(q.quote_date).slice(0, 10), duration_days: q.duration_days ?? '',
                    downpayment_pct: q.downpayment_pct === null ? '' : String(+num(q.downpayment_pct).toFixed(2)), project_name: q.project_name || '', location: q.location || '', sections: fromQuote(),
                } : {
                    subcontractor: lb.foreman.name ? 'Mr. ' + lb.foreman.name : '', subject: 'Quotation for Works (Labor Only)', quote_date: todayISO(), duration_days: '', downpayment_pct: '30',
                    project_name: lb.dr.client_name || '', location: lb.dr.address || '', sections: [{ name: '', items: [{}] }],
                });
                if (!v.sections || !v.sections.length) v.sections = [{ name: '', items: [{}] }];

                const returned = q && q.status === 'returned';
                box.innerHTML = head + `
                    ${returned ? `<div class="mb-3 rounded-xl border border-red-200 bg-red-50 px-3.5 py-2.5 text-xs text-red-800"><p class="text-[10px] font-bold uppercase tracking-wider text-red-600 mb-0.5"><i class="fa-solid fa-rotate-left mr-1"></i>Returned by ${esc(q.returned_by || 'Superadmin')}${q.returned_at ? ' · ' + fmtY(q.returned_at) : ''}</p>${esc(q.return_remarks || '')}</div>` : ''}
                    <form id="lqForm" class="rounded-xl border border-gray-200 p-4" novalidate>
                        <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-4">
                            <label class="col-span-2 md:col-span-3">${lbl('Project name')}<input name="project_name" value="${esc(v.project_name)}" maxlength="255" class="${inp}"></label>
                            <label class="col-span-2 md:col-span-3">${lbl('Location')}<input name="location" value="${esc(v.location)}" maxlength="255" class="${inp}"></label>
                            <label class="col-span-2 md:col-span-3">${lbl('Subcontractor (submitted by)', 1)}<input name="subcontractor" value="${esc(v.subcontractor)}" maxlength="255" placeholder="e.g. Mr. Dennis Relos" class="${inp}"><span data-err="subcontractor" class="hidden block text-[11px] text-red-600 mt-1"></span></label>
                            <label class="col-span-2 md:col-span-3">${lbl('Subject')}<input name="subject" value="${esc(v.subject)}" maxlength="255" class="${inp}"></label>
                            <label class="md:col-span-2">${lbl('Date', 1)}<input type="date" name="quote_date" value="${esc(v.quote_date)}" class="${inp}"></label>
                            <label class="md:col-span-2">${lbl('Duration (days)')}<input type="number" name="duration_days" min="1" max="3650" step="1" value="${esc(v.duration_days)}" placeholder="60" class="${inp}"><span data-err="duration_days" class="hidden block text-[11px] text-red-600 mt-1"></span></label>
                            <label class="col-span-2 md:col-span-2">${lbl('Down payment %')}<input type="number" name="downpayment_pct" min="0" max="100" step="any" value="${esc(v.downpayment_pct)}" placeholder="30" class="${inp}"><span data-err="downpayment_pct" class="hidden block text-[11px] text-red-600 mt-1"></span></label>
                        </div>
                        <div id="lqSecs" class="space-y-3"></div>
                        <div class="flex flex-wrap items-center justify-between gap-2 mt-3">
                            <button type="button" id="lqAddSec" class="inline-flex items-center gap-1.5 px-3 py-2 text-[11px] font-semibold text-amber-800 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100"><i class="fa-solid fa-layer-group"></i> Add section</button>
                            <p class="text-xs text-gray-500">Total: <b id="lqTotal" class="font-mono text-base text-gray-900">₱ 0.00</b></p>
                        </div>
                        <p id="lqErr" class="hidden mt-3 rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-[11px] text-red-700"></p>
                        <div class="flex flex-wrap items-center justify-between gap-2 mt-4 pt-3 border-t border-gray-100">
                            <p id="lqNote" class="text-[10.5px] text-gray-400">${dft ? 'Restored your unsaved draft.' : (q ? '' : 'Quotation No. will be ' + esc(lb.preview))}</p>
                            <div class="flex items-center gap-2 ml-auto">
                                ${q && !returned ? '<button type="button" id="lqCancel" class="px-3.5 py-2 text-xs font-semibold text-gray-500 bg-white border border-gray-200 rounded-lg hover:bg-gray-100">Cancel</button>' : ''}
                                <button type="submit" id="lqSave" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:opacity-60"><i class="fa-solid fa-paper-plane"></i> <span>${q ? 'Save & resubmit' : 'Submit for approval'}</span></button>
                            </div>
                        </div>
                        <datalist id="lqUnits"><option>SQM<option>LM<option>PCS<option>SET<option>LOT<option>UNIT</datalist>
                    </form>`;

                const secs = F('lqSecs');
                const rowHtml = (it = {}) => `<tr class="border-t border-gray-100">
                    <td class="p-1.5"><input name="item" value="${esc(it.item ?? '')}" placeholder="e.g. Wardrobe" class="${inp}"></td>
                    <td class="p-1.5"><input name="qty" type="number" min="0" step="any" value="${esc(it.qty ?? '')}" class="${inp} text-right"></td>
                    <td class="p-1.5"><input name="unit" value="${esc(it.unit ?? '')}" list="lqUnits" placeholder="SQM" class="${inp}"></td>
                    <td class="p-1.5"><input name="unit_cost" type="number" min="0" step="any" value="${esc(it.unit_cost ?? '')}" class="${inp} text-right"></td>
                    <td class="p-1.5 text-right font-mono text-[11px] text-gray-700 lq-amt">0.00</td>
                    <td class="p-1.5 text-center"><button type="button" class="lq-del-row w-7 h-7 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50" title="Remove item"><i class="fa-solid fa-xmark"></i></button></td></tr>`;
                const secHtml = (s = {}) => `<div class="lq-sec rounded-xl border border-gray-200 overflow-hidden">
                    <div class="flex items-center gap-2 px-3 py-2 bg-gray-50 border-b border-gray-100">
                        <span class="lq-letter w-7 h-7 rounded-md bg-white border border-gray-200 text-xs font-bold text-gray-700 flex items-center justify-center shrink-0">A</span>
                        <input name="section" value="${esc(s.name ?? '')}" maxlength="150" placeholder="Section name, e.g. CLOSET" class="flex-1 min-w-0 bg-transparent text-xs font-bold uppercase tracking-wide outline-none border-b border-transparent focus:border-amber-600 py-1">
                        <span class="lq-sub font-mono text-[11px] text-gray-500 shrink-0"></span>
                        <button type="button" class="lq-del-sec w-7 h-7 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50 shrink-0" title="Remove section"><i class="fa-solid fa-trash-can text-[11px]"></i></button>
                    </div>
                    <div class="overflow-x-auto"><table class="w-full min-w-[560px] table-fixed text-xs">
                        <colgroup><col style="width:40%"><col style="width:13%"><col style="width:12%"><col style="width:15%"><col style="width:14%"><col style="width:6%"></colgroup>
                        <thead class="text-[10px] uppercase tracking-wider text-gray-500"><tr><th class="px-2 py-1.5 text-left font-semibold">Item <span class="text-red-500">*</span></th><th class="px-2 py-1.5 text-right font-semibold">Qty <span class="text-red-500">*</span></th><th class="px-2 py-1.5 text-left font-semibold">Unit</th><th class="px-2 py-1.5 text-right font-semibold">Unit cost</th><th class="px-2 py-1.5 text-right font-semibold">Amount</th><th></th></tr></thead>
                        <tbody>${(s.items && s.items.length ? s.items : [{}]).map(rowHtml).join('')}</tbody>
                    </table></div>
                    <div class="px-3 py-2 border-t border-gray-100"><button type="button" class="lq-add-row inline-flex items-center gap-1 text-[11px] font-semibold text-amber-700 hover:underline"><i class="fa-solid fa-plus text-[10px]"></i> Add item</button></div>
                </div>`;
                secs.innerHTML = v.sections.map(secHtml).join('');

                const form = F('lqForm');
                const readQ = () => ({
                    ...Object.fromEntries(['project_name', 'location', 'subcontractor', 'subject', 'quote_date', 'duration_days', 'downpayment_pct'].map(n => [n, form.elements[n].value.trim()])),
                    sections: [...secs.querySelectorAll('.lq-sec')].map(s => ({
                        name: s.querySelector('[name=section]').value.trim(),
                        items: [...s.querySelectorAll('tbody tr')].map(tr => Object.fromEntries(['item', 'qty', 'unit', 'unit_cost'].map(n => [n, tr.querySelector(`[name=${n}]`).value.trim()]))),
                    })),
                });
                function calc() {
                    let total = 0;
                    secs.querySelectorAll('.lq-sec').forEach((s, i) => {
                        s.querySelector('.lq-letter').textContent = LQ_LETTER(i + 1);
                        let sub = 0;
                        s.querySelectorAll('tbody tr').forEach(tr => {
                            const a = Math.round(num(tr.querySelector('[name=qty]').value) * num(tr.querySelector('[name=unit_cost]').value) * 100) / 100;
                            tr.querySelector('.lq-amt').textContent = money(a); sub += a;
                        });
                        s.querySelector('.lq-sub').textContent = '₱ ' + money(sub);
                        total += sub;
                    });
                    F('lqTotal').textContent = '₱ ' + money(total);
                }
                let t = null;
                const changed = () => { calc(); clearTimeout(t); t = setTimeout(() => { if (!document.body.contains(form)) return; memo.set(key, JSON.stringify(readQ())); if (F('lqNote')) F('lqNote').textContent = 'Draft saved on this device.'; }, 400); };
                const lqErr = m => { F('lqErr').textContent = m || ''; F('lqErr').classList.toggle('hidden', !m); };
                const setErr = (field, msg) => {
                    const el = form.querySelector(`[data-err="${field}"]`), i = form.elements[field];
                    if (!el) return;   // item/qty rows: walang sariling error label
                    el.textContent = msg || ''; el.classList.toggle('hidden', !msg);
                    if (i instanceof Element) { msg ? i.setAttribute('aria-invalid', 'true') : i.removeAttribute('aria-invalid'); i.classList.toggle('border-red-400', !!msg); }
                };
                form.addEventListener('input', e => { if (e.target.name) setErr(e.target.name, ''); lqErr(''); changed(); });
                form.addEventListener('click', e => {
                    const add = e.target.closest('.lq-add-row'), delR = e.target.closest('.lq-del-row'), delS = e.target.closest('.lq-del-sec');
                    if (add) { add.closest('.lq-sec').querySelector('tbody').insertAdjacentHTML('beforeend', rowHtml()); add.closest('.lq-sec').querySelector('tbody tr:last-child [name=item]').focus(); changed(); }
                    if (delR) { const tb = delR.closest('tbody'); if (tb.rows.length > 1) delR.closest('tr').remove(); else delR.closest('tr').querySelectorAll('input').forEach(i => i.value = ''); changed(); }
                    if (delS) {
                        const sec = delS.closest('.lq-sec');
                        if (secs.querySelectorAll('.lq-sec').length === 1) { sec.querySelectorAll('input').forEach(i => i.value = ''); changed(); return; }
                        const hasData = [...sec.querySelectorAll('input')].some(i => i.value.trim());
                        if (hasData && !sec.dataset.confirm) {   // inline confirm
                            sec.dataset.confirm = '1';
                            delS.outerHTML = `<span class="lq-confirm flex items-center gap-1 shrink-0"><span class="text-[10.5px] text-red-700">Remove section?</span><button type="button" class="lq-del-sec px-2 py-1 text-[10.5px] font-semibold text-white bg-red-600 rounded-md">Remove</button><button type="button" class="lq-keep px-2 py-1 text-[10.5px] font-semibold text-gray-600 bg-white border border-gray-200 rounded-md">Keep</button></span>`;
                            return;
                        }
                        sec.remove(); changed();
                    }
                    const keep = e.target.closest('.lq-keep');
                    if (keep) { const sec = keep.closest('.lq-sec'); delete sec.dataset.confirm; keep.closest('.lq-confirm').outerHTML = '<button type="button" class="lq-del-sec w-7 h-7 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50 shrink-0" title="Remove section"><i class="fa-solid fa-trash-can text-[11px]"></i></button>'; }
                });
                F('lqAddSec').addEventListener('click', () => { secs.insertAdjacentHTML('beforeend', secHtml()); secs.querySelector('.lq-sec:last-child [name=section]').focus(); changed(); });
                if (F('lqCancel')) F('lqCancel').addEventListener('click', () => { lb.editingQuote = false; memo.del(key); renderQuote(); });
                calc();

                form.addEventListener('submit', async e => {
                    e.preventDefault();
                    lqErr('');
                    const f = readQ();
                    let bad = false;
                    if (!f.subcontractor) { setErr('subcontractor', 'Subcontractor is required.'); bad = true; }
                    if (f.duration_days && (+f.duration_days < 1 || +f.duration_days > 3650)) { setErr('duration_days', '1 to 3650 days.'); bad = true; }
                    if (f.downpayment_pct && (+f.downpayment_pct < 0 || +f.downpayment_pct > 100)) { setErr('downpayment_pct', '0 to 100%.'); bad = true; }
                    if (bad) return lqErr('Please check the highlighted fields.');
                    const flat = [];
                    for (const s of f.sections) {
                        const items = s.items.filter(i => i.item);
                        if (!items.length) continue;
                        if (!s.name) return lqErr(`Give a section name for "${items[0].item}" (e.g. CLOSET).`);
                        for (const i of items) {
                            if (num(i.qty) <= 0) return lqErr(`Enter the quantity for "${i.item}".`);
                            flat.push({ section: s.name, ...i });
                        }
                    }
                    if (!flat.length) return lqErr('Add at least one item.');
                    const btn = F('lqSave'), txt = btn.querySelector('span').textContent;
                    btn.disabled = true; btn.querySelector('span').textContent = 'Submitting…';
                    const qs = new URLSearchParams();
                    qs.append('dr_id', lb.dr.id);
                    ['project_name', 'location', 'subcontractor', 'subject', 'quote_date', 'duration_days', 'downpayment_pct'].forEach(n => qs.append(n, f[n]));
                    flat.forEach(i => { qs.append('section[]', i.section); qs.append('item[]', i.item); qs.append('qty[]', i.qty); qs.append('unit[]', i.unit); qs.append('unit_cost[]', i.unit_cost); });
                    try {
                        const d = await apiForm('save_quote', qs);
                        memo.del(key);
                        toast(q ? `${d.quote_no} resubmitted to the Superadmin.` : `${d.quote_no} sent to the Superadmin for signing.`);
                        const r = await api('labor_get', { dr_id: lb.dr.id });
                        lb.quote = r.quote; lb.foreman = r.foreman; lb.editingQuote = false;
                        renderLabor(); load(true);
                    } catch (er) {
                        if (er.field) setErr(er.field, er.message);
                        lqErr(er.message);
                        btn.disabled = false; btn.querySelector('span').textContent = txt;
                    }
                });
            }

            function printHtml(html) {
                let area = F('nhPrintArea');
                if (!area) { area = document.createElement('div'); area.id = 'nhPrintArea'; document.body.appendChild(area); }
                area.innerHTML = html;
                const imgs = [...area.querySelectorAll('img')].filter(i => !i.complete);
                Promise.all(imgs.map(i => new Promise(r => { i.onload = i.onerror = r; }))).then(() => { window.print(); setTimeout(() => { area.innerHTML = ''; }, 500); });
            }


            // ═════════════ LIST ═════════════
            const BAR = { foreman: 'bg-orange-400', quote: 'bg-amber-500', pending: 'bg-sky-500', returned: 'bg-red-500', approved: 'bg-emerald-600' };
            const inTab = (r, k) => k === 'all' ? true : k === 'todo' ? ['foreman', 'quote', 'returned'].includes(r.labor_state) : r.labor_state === k;

            function render() {
                document.querySelectorAll('.lp-tab').forEach(b => b.dataset.active = String(b.dataset.tab === tab));
                document.querySelectorAll('[data-count]').forEach(el => el.textContent = rows.filter(r => inTab(r, el.dataset.count)).length);
                const q = F('lpSearch').value.trim().toLowerCase();
                const list = rows.filter(r => inTab(r, tab) && (!q || `${r.dr_no} ${r.client_name} ${r.ref_no} ${r.project_name ?? ''} ${r.quote_no ?? ''} ${r.foreman_name ?? ''}`.toLowerCase().includes(q)));
                F('lpInfo').innerHTML = `<b class="text-gray-700">${list.length}</b> approved DR${list.length === 1 ? '' : 's'}${q ? ' (filtered)' : ''}`;
                if (!list.length) {
                    const msg = q ? 'No match.' : { todo: 'Nothing to do right now. 🎉', foreman: 'Every DR already has a foreman.', pending: 'No labor quotation is waiting for approval.', returned: 'No returned quotation.', approved: 'No approved quotation yet.', all: 'No approved DR yet. A DR shows here once the Superadmin signs it.' }[tab];
                    F('lpList').innerHTML = `<div class="px-6 py-14 text-center"><div class="w-12 h-12 mx-auto rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center mb-3"><i class="fa-solid fa-helmet-safety"></i></div><p class="text-sm font-semibold text-gray-700">${msg}</p></div>`;
                    return;
                }
                F('lpList').innerHTML = list.map(r => {
                    const btn = { foreman: ['Add foreman', 'fa-id-badge', true], quote: ['Make labor quote', 'fa-file-pen', true], returned: ['Fix & resubmit', 'fa-pen', true], pending: ['View', 'fa-eye', false], approved: ['View / Print', 'fa-print', false] }[r.labor_state];
                    return `<div data-row="${r.dr_id}" class="lp-row relative flex flex-wrap items-center gap-x-5 gap-y-2 pl-5 pr-4 py-3.5">
                        <span class="absolute left-0 top-0 bottom-0 w-1 ${BAR[r.labor_state] || 'bg-gray-300'}"></span>
                        <div class="min-w-0 flex-1 basis-[260px]">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-sm font-bold text-gray-900">DR# ${esc(r.dr_no)}</span>
                                ${laborChip(r.labor_state)}
                                ${r.quote_no ? `<span class="font-mono text-[11px] text-gray-400">${esc(r.quote_no)}</span>` : ''}
                            </div>
                            <p class="text-sm font-semibold text-gray-800 truncate mt-0.5">${esc(r.client_name)}${r.project_name ? ` <span class="font-normal text-gray-400">· ${esc(r.project_name)}</span>` : ''}</p>
                            ${r.labor_state === 'returned' && r.quote_remarks ? `<p class="mt-1.5 text-[11px] text-red-700 bg-red-50 border border-red-100 rounded-lg px-2.5 py-1.5"><i class="fa-solid fa-comment-dots mr-1"></i><b>Superadmin:</b> ${esc(r.quote_remarks)}</p>` : ''}
                            ${r.labor_state === 'approved' && r.quote_approved_by ? `<p class="mt-1 text-[11px] text-emerald-700"><i class="fa-solid fa-signature mr-1"></i>Signed by ${esc(r.quote_approved_by)} · ${fmtY(r.quote_approved_at)}</p>` : ''}
                        </div>
                        <dl class="grid grid-cols-3 gap-x-5 text-[11px] basis-[300px] grow sm:grow-0">
                            <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Transmittal</dt><dd class="font-mono text-gray-700">${esc(r.ref_no)}</dd></div>
                            <div class="min-w-0"><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Foreman</dt><dd class="truncate ${r.foreman_name ? 'text-gray-700' : 'text-orange-600'}">${esc(r.foreman_name || 'Not yet')}</dd></div>
                            <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Labor total</dt><dd class="font-mono font-semibold text-gray-900">${r.quote_total !== null ? '₱ ' + money(r.quote_total) : '—'}</dd></div>
                        </dl>
                        <div class="ml-auto shrink-0 text-right"><button type="button" data-labor="${r.dr_id}" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg ${btn[2] ? 'text-white bg-amber-700 hover:bg-amber-800 shadow-sm' : 'text-gray-600 bg-white border border-gray-200 hover:bg-gray-50'}"><i class="fa-solid ${btn[1]}"></i> ${btn[0]}</button>
                            ${r.labor_state === 'approved' ? `<a href="${TIMELINE_URL}?dr=${r.dr_id}" class="block mt-1 text-[10.5px] font-semibold text-amber-700 hover:underline"><i class="fa-solid fa-chart-gantt mr-0.5"></i> Timeline →</a>` : ''}</div>
                    </div>`;
                }).join('');
                markPicked();
            }
            function markPicked() {
                let id = null; try { id = sessionStorage.getItem('nh-lb-picked'); } catch (e) { }
                document.querySelectorAll('.lp-row').forEach(el => el.setAttribute('data-picked', el.dataset.row === id ? '1' : '0'));
            }
            document.querySelectorAll('.lp-tab').forEach(b => b.addEventListener('click', () => { tab = b.dataset.tab; memo.set('tab', tab); render(); }));
            F('lpSearch').addEventListener('input', render);
            F('lpList').addEventListener('click', e => {
                const b = e.target.closest('[data-labor]'); if (!b) return;
                try { sessionStorage.setItem('nh-lb-picked', b.dataset.labor); } catch (err) { }
                markPicked(); openLabor(+b.dataset.labor);
            });

            async function load(silent) {
                try {
                    const d = await api('list');
                    const sig = JSON.stringify(d.rows.map(r => [r.dr_id, r.labor_state, r.quote_total, r.foreman_name]));
                    if (silent && sig === lastSig) return;
                    if (silent && lastSig) {
                        const prev = new Map(rows.map(r => [r.dr_id, r.labor_state]));
                        d.rows.forEach(r => {
                            if (prev.get(r.dr_id) === 'pending' && r.labor_state === 'approved') toast(`${r.quote_no} was approved. You can print it now.`);
                            if (prev.get(r.dr_id) === 'pending' && r.labor_state === 'returned') toast(`${r.quote_no} was returned. Please check the remarks.`, 'error', 6000);
                        });
                    }
                    lastSig = sig; rows = d.rows; render();
                } catch (e) {
                    if (silent) return;
                    F('lpList').innerHTML = `<div class="px-6 py-12 text-center text-xs text-red-600">${esc(e.message)}<br><button type="button" id="lpRetry" class="mt-2 font-semibold text-amber-700 hover:underline">Retry</button></div>`;
                    F('lpRetry').addEventListener('click', () => load());
                }
            }

            // ═════════════ INIT ═════════════
            tab = memo.get('tab') || 'todo';
            if (!['todo', 'foreman', 'pending', 'returned', 'approved', 'all'].includes(tab)) tab = 'todo';
            load().then(() => {
                // ?dr=ID → buksan agad (galing sa Delivery Receipts page)
                const want = +new URLSearchParams(location.search).get('dr');
                if (want && rows.some(r => r.dr_id === want)) openLabor(want);
                if (want) { try { const u = new URL(location.href); u.searchParams.delete('dr'); history.replaceState(null, '', u); } catch (e) { } }
            });
            setInterval(() => { if (!document.hidden && !F('lbModal').classList.contains('flex')) load(true); }, 30000);
        })();
    </script>
</body>

</html>