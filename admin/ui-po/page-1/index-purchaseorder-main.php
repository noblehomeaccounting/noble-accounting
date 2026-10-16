<?php
// index-purchaseorder-main.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PO];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$poAjaxUrl = BASE_URL . '/purchaseorderajax';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order Dashboard</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
</head>

<body class="bg-slate-100">
    <main id="main-content" class="md:ml-56 pt-20 md:pt-5 min-h-screen p-4 md:p-8 transition-all duration-300">

        <div class="max-w-6xl mx-auto">

            <!-- ===================== HEADER ===================== -->
            <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
                <div>
                    <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase mb-0.5">Purchasing</p>
                    <h1 class="text-gray-900 text-xl font-semibold">Purchase Orders</h1>
                    <p class="text-gray-400 text-xs mt-0.5">Bills of Materials approved by the Superadmin. Receive a BOM to sign it and unlock the full document.</p>
                </div>
                <span class="inline-flex items-center gap-1.5 text-[11px] text-gray-400">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span> Auto-refresh every 30s
                </span>
            </div>

            <!-- ===================== STATS ===================== -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
                <div class="bg-white border border-gray-200 rounded-xl px-5 py-4 flex items-center gap-4">
                    <span class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center"><i class="fa-solid fa-inbox"></i></span>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">To Receive</p>
                        <p id="poStatPending" class="text-2xl font-bold text-gray-900 leading-tight tabular-nums">—</p>
                    </div>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl px-5 py-4 flex items-center gap-4">
                    <span class="w-11 h-11 rounded-xl bg-green-50 text-green-600 flex items-center justify-center"><i class="fa-solid fa-calendar-check"></i></span>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Received Today</p>
                        <p id="poStatToday" class="text-2xl font-bold text-gray-900 leading-tight tabular-nums">—</p>
                    </div>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl px-5 py-4 flex items-center gap-4">
                    <span class="w-11 h-11 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center"><i class="fa-solid fa-file-circle-check"></i></span>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Total Received</p>
                        <p id="poStatReceived" class="text-2xl font-bold text-gray-900 leading-tight tabular-nums">—</p>
                    </div>
                </div>
            </div>

            <!-- ===================== LIST ===================== -->
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">

                <!-- tabs + search -->
                <div class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-gray-100">
                    <div class="inline-flex items-center gap-1 bg-gray-50 border border-gray-200 rounded-lg p-1">
                        <button type="button" data-tab="pending"
                            class="po-tab inline-flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-semibold text-gray-500 transition-all">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> To Receive
                            <span id="poCountPending" class="min-w-[20px] px-1.5 py-px rounded-full bg-gray-200 text-gray-600 text-[10px] font-bold text-center">—</span>
                        </button>
                        <button type="button" data-tab="received"
                            class="po-tab inline-flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-semibold text-gray-500 transition-all">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Received
                            <span id="poCountReceived" class="min-w-[20px] px-1.5 py-px rounded-full bg-gray-200 text-gray-600 text-[10px] font-bold text-center">—</span>
                        </button>
                    </div>

                    <div class="relative ml-auto w-full sm:w-72">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[11px]"></i>
                        <input type="text" id="poSearch" placeholder="Search BOM no., control no. or client..."
                            class="w-full bg-white border border-gray-200 rounded-lg pl-8 pr-3 py-2 text-xs outline-none focus:border-amber-600 transition-colors">
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[860px]">
                        <thead class="bg-gray-50 text-[10.5px] font-semibold uppercase tracking-wider text-gray-500">
                            <tr class="border-b border-gray-200">
                                <th class="px-5 py-3 text-left">BOM No.</th>
                                <th class="px-5 py-3 text-left">Client</th>
                                <th class="px-5 py-3 text-center">Items</th>
                                <th class="px-5 py-3 text-left">Date Needed</th>
                                <th class="px-5 py-3 text-left">Approved</th>
                                <th class="px-5 py-3 text-left" id="poColStatus">Status</th>
                                <th class="px-5 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody id="poList" class="divide-y divide-gray-100">
                            <tr><td colspan="7" class="px-5 py-12 text-center text-gray-400 text-xs"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Loading…</td></tr>
                        </tbody>
                    </table>
                </div>

                <p id="poFooter" class="px-5 py-2.5 border-t border-gray-100 bg-gray-50/60 text-[11px] text-gray-400"></p>
            </div>
        </div>

        <div id="crmToastContainer" class="fixed bottom-4 right-4 z-[9999] flex flex-col gap-2.5 pointer-events-none w-[360px] max-w-[calc(100vw-2rem)]"></div>
    </main>

    <!-- ===================== RECEIVE CONFIRM MODAL ===================== -->
    <div id="poReceiveModal" class="hidden fixed inset-0 z-[9999] items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm overflow-hidden">
            <div class="p-5">
                <div class="flex items-center gap-2.5 mb-3">
                    <span class="w-9 h-9 rounded-full bg-amber-50 text-amber-700 flex items-center justify-center">
                        <i class="fa-solid fa-file-signature text-sm"></i>
                    </span>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">Receive this BOM?</p>
                        <p id="poReceiveNo" class="font-mono text-[11px] font-semibold text-amber-700"></p>
                    </div>
                </div>
                <div class="rounded-lg bg-gray-50 border border-gray-200 px-3 py-2.5 text-xs text-gray-600 space-y-1">
                    <p><span class="text-gray-400">Client:</span> <span id="poReceiveClient" class="font-semibold text-gray-800"></span></p>
                    <p><span class="text-gray-400">Items:</span> <span id="poReceiveItems" class="font-semibold text-gray-800"></span></p>
                </div>
                <p class="text-[11px] text-gray-500 mt-3 leading-relaxed">
                    <i class="fa-solid fa-signature text-amber-700 mr-1"></i>
                    Your name, title and saved signature will be placed in <b>Received By</b>. This cannot be undone.
                </p>
            </div>
            <div class="flex justify-end gap-2 px-5 py-3 bg-gray-50 border-t border-gray-100">
                <button type="button" id="poReceiveCancel"
                    class="px-3.5 py-2 text-[11px] font-semibold text-gray-500 bg-white border border-gray-200 rounded-lg hover:bg-gray-100 transition-colors">Cancel</button>
                <button type="button" id="poReceiveConfirm"
                    class="px-3.5 py-2 text-[11px] font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:opacity-60 transition-colors">
                    <i class="fa-solid fa-check mr-1"></i> Receive &amp; Sign
                </button>
            </div>
        </div>
    </div>

    <!-- ===================== BOM VIEW MODAL ===================== -->
    <div id="poBomModal" class="hidden fixed inset-0 z-[9998] items-center justify-center bg-black/50 p-4 overflow-y-auto">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl my-auto overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between gap-2">
                <p id="poBomModalTitle" class="text-xs font-semibold text-gray-700 font-mono truncate">BOM</p>
                <div class="flex items-center gap-3 shrink-0">
                    <a id="poBomDownload" href="#" target="_blank"
                        class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-700 hover:text-amber-800">
                        <i class="fa-solid fa-file-pdf"></i> Download PDF
                    </a>
                    <button type="button" id="poBomClose" class="text-gray-400 hover:text-gray-600 text-lg leading-none">&times;</button>
                </div>
            </div>
            <div id="poBomBody" class="p-5 overflow-x-auto"></div>
        </div>
    </div>

    <!-- ===================== ROW ACTIONS MENU ===================== -->
    <div id="poRowMenu" class="hidden fixed z-[9990] w-52 p-1 bg-white border border-gray-200 rounded-lg shadow-lg"></div>

    <!-- ===================== STATUS HISTORY DRAWER ===================== -->
    <div id="poHistOverlay" class="hidden fixed inset-0 z-[9997] bg-black/20"></div>
    <aside id="poHistDrawer" class="fixed top-0 right-0 z-[9998] h-full w-full max-w-[400px] bg-slate-50 shadow-2xl flex flex-col translate-x-full transition-transform duration-300">
        <div class="bg-white px-5 py-4 border-b border-gray-200 flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase">Status History</p>
                <p id="poHistTitle" class="font-mono text-[12px] font-semibold text-gray-800 truncate mt-0.5">—</p>
                <p id="poHistSub" class="text-[11px] text-gray-400 truncate"></p>
            </div>
            <button type="button" id="poHistClose" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <div id="poHistBody" class="flex-1 overflow-y-auto p-4"></div>
    </aside>

    <script>
        (function () {
            const AJAX = <?= json_encode($poAjaxUrl) ?>;
            const BASE = <?= json_encode(BASE_URL) ?>;
            const PR_URL = <?= json_encode(BASE_URL . '/purchaserequest') ?>;
            const TR_URL = <?= json_encode(BASE_URL . '/transmittal') ?>;

            let rows = [];
            let tab = 'pending';
            let receiveTarget = null;

            // ── helpers ──
            const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
            const toDate = v => v ? new Date(String(v).replace(' ', 'T')) : null;
            const fmtDate = v => {
                const d = toDate(v);
                return d && !isNaN(d) ? d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
            };
            const fmtTime = v => {
                const d = toDate(v);
                return d && !isNaN(d) ? d.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' }) : '';
            };

            function toast(message, type = 'success', duration = 4000) {
                const box = document.getElementById('crmToastContainer');
                const p = type === 'success'
                    ? { wrap: 'bg-green-50 border-green-200 text-green-700', icon: 'bg-green-200 text-green-700', sym: '✓' }
                    : { wrap: 'bg-red-50 border-red-200 text-red-700', icon: 'bg-red-200 text-red-700', sym: '!' };
                const t = document.createElement('div');
                t.className = `pointer-events-auto flex items-start gap-2.5 border rounded-lg shadow-lg px-4 py-3 text-sm ${p.wrap} translate-x-6 opacity-0 scale-95 transition-all duration-300 ease-out`;
                t.innerHTML = `<span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold ${p.icon}">${p.sym}</span>
                    <span class="flex-1 leading-relaxed"></span>
                    <button type="button" class="shrink-0 opacity-50 hover:opacity-100 text-base leading-none">&times;</button>`;
                t.querySelector('span.flex-1').textContent = message;
                box.appendChild(t);
                requestAnimationFrame(() => t.classList.remove('translate-x-6', 'opacity-0', 'scale-95'));
                const remove = () => { t.classList.add('translate-x-6', 'opacity-0', 'scale-95'); setTimeout(() => t.remove(), 300); };
                t.querySelector('button').addEventListener('click', remove);
                if (duration > 0) setTimeout(remove, duration);
            }

            function openModal(id) { const m = document.getElementById(id); m.classList.remove('hidden'); m.classList.add('flex'); }
            function closeModal(id) { const m = document.getElementById(id); m.classList.add('hidden'); m.classList.remove('flex'); }
            const anyModalOpen = () => ['poReceiveModal', 'poBomModal'].some(id => !document.getElementById(id).classList.contains('hidden'));

            // ── tabs ──
            const memo = {
                get: k => { try { return localStorage.getItem('nh-po-' + k); } catch (e) { return null; } },
                set: (k, v) => { try { localStorage.setItem('nh-po-' + k, v); } catch (e) { } },
            };

            function setTab(t) {
                tab = t;
                memo.set('tab', t);
                document.querySelectorAll('.po-tab').forEach(btn => {
                    const on = btn.dataset.tab === t;
                    const onCls = t === 'pending' ? ['bg-white', 'text-amber-700', 'shadow-sm'] : ['bg-white', 'text-green-700', 'shadow-sm'];
                    ['bg-white', 'text-amber-700', 'text-green-700', 'shadow-sm'].forEach(c => btn.classList.remove(c));
                    if (on) onCls.forEach(c => btn.classList.add(c));
                    btn.classList.toggle('text-gray-500', !on);
                });
                document.getElementById('poColStatus').textContent = t === 'pending' ? 'Status' : 'Received';
                render();
            }
            document.querySelectorAll('.po-tab').forEach(b => b.addEventListener('click', () => setTab(b.dataset.tab)));
            const searchBox = document.getElementById('poSearch');
            searchBox.value = memo.get('search') || '';
            searchBox.addEventListener('input', () => { memo.set('search', searchBox.value); render(); });

            // ═════════════ STATUS HISTORY ═════════════
            // Yugto ng pinakahuling Purchase Request ng BOM (para sa maliit na label sa listahan)
            function prStage(p) {
                if (!p) return null;
                if (p.status === 'Rejected') return { label: 'Returned', cls: 'text-red-600', dot: 'bg-red-500' };
                if (p.status !== 'Approved') return { label: 'For CEO approval', cls: 'text-amber-700', dot: 'bg-amber-500' };
                if (p.paid_at) return { label: 'Paid', cls: 'text-green-700', dot: 'bg-green-500' };
                if (p.released_at) return { label: 'Waiting to be Paid', cls: 'text-sky-700', dot: 'bg-sky-500' };
                if (p.staff_received_at) return { label: 'For release', cls: 'text-indigo-700', dot: 'bg-indigo-500' };
                if (p.reviewed_at) return { label: 'Reviewed', cls: 'text-indigo-700', dot: 'bg-indigo-500' };
                return { label: 'Approved by CEO', cls: 'text-green-700', dot: 'bg-green-500' };
            }
            function prChip(r) {
                if (!r.pr_id) return `<p class="text-[11px] text-gray-400 mt-0.5"><i class="fa-regular fa-clock mr-0.5"></i>No purchase request yet</p>`;
                const st = prStage({ status: r.pr_status, reviewed_at: r.pr_reviewed_at, staff_received_at: r.pr_staff_received_at, released_at: r.pr_released_at, paid_at: r.pr_paid_at });
                return `<p class="inline-flex items-center gap-1.5 text-[11px] font-semibold mt-0.5 ${st.cls}"><span class="w-1.5 h-1.5 rounded-full ${st.dot}"></span>PR · ${st.label}</p>`;
            }

            const fmtFull = v => {
                if (!v) return '';
                const t = new Date(String(v).replace(' ', 'T'));
                return isNaN(t) ? '' : t.toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
            };

            // isang hakbang sa timeline
            function stepHtml(s, state, isLast) {
                const tone = {
                    done:    { dot: 'bg-emerald-500', pill: 'bg-emerald-50 text-emerald-700 border-emerald-200' },
                    bad:     { dot: 'bg-red-500',     pill: 'bg-red-50 text-red-600 border-red-200' },
                    next:    { dot: 'bg-amber-400',   pill: 'bg-amber-50 text-amber-700 border-amber-200' },
                    pending: { dot: 'bg-gray-300',    pill: 'bg-white text-gray-400 border-gray-200' },
                }[state];
                const label = state === 'next' ? s.wait : s.label;
                const done = state === 'done' || state === 'bad';
                const body = done
                    ? `${s.at ? `<p class="text-[11px] text-gray-400 mt-1">${esc(fmtFull(s.at))}</p>` : ''}
                       <p class="text-xs text-gray-600 mt-0.5">${esc(s.desc)}${s.who ? ` <b class="text-gray-800">${esc(s.who)}</b>` : ''}</p>
                       ${s.role && s.who ? `<p class="text-[10px] uppercase tracking-wide text-gray-400">${esc(s.role)}</p>` : ''}
                       ${s.note ? `<p class="text-[11px] text-red-600 mt-1 italic">“${esc(s.note)}”</p>` : ''}
                       ${s.sig ? `<img src="${BASE}/${esc(s.sig)}" alt="Signature" class="h-9 w-auto max-w-[170px] object-contain mt-1">`
                               : (s.who && !s.nosig ? '<p class="text-[10px] text-gray-400 italic mt-0.5">No saved signature</p>' : '')}`
                    : `<p class="text-[11px] text-gray-400 mt-1">${state === 'next' ? 'Not yet signed' : 'Pending'}</p>`;
                return `
                    <li class="relative pl-6 ${isLast ? '' : 'pb-5'}">
                        ${isLast ? '' : '<span class="absolute left-[5px] top-3 bottom-0 w-px bg-gray-300"></span>'}
                        <span class="absolute left-0 top-1.5 w-[11px] h-[11px] rounded-full ring-4 ring-white ${tone.dot}"></span>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex px-2.5 py-0.5 rounded-full border text-[10.5px] font-bold uppercase tracking-wide ${tone.pill}">${esc(label)}</span>
                            ${s.current ? '<span class="text-[10px] font-bold uppercase tracking-wider text-amber-700">Current</span>' : ''}
                        </div>
                        ${body}
                    </li>`;
            }

            function timeline(steps, markCurrent = true) {
                let last = -1;
                steps.forEach((s, i) => { if (s.done) last = i; });
                if (last >= 0 && markCurrent) steps[last].current = true;
                return `<ol>${steps.map((s, i) => stepHtml(s, s.bad ? 'bad' : s.done ? 'done' : (i === last + 1 ? 'next' : 'pending'), i === steps.length - 1)).join('')}</ol>`;
            }

            function card(title, right, inner) {
                return `
                    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden mb-3">
                        <div class="px-4 py-2.5 border-b border-gray-100 flex items-center justify-between gap-2">
                            <p class="text-xs font-semibold text-gray-800">${title}</p>${right}
                        </div>
                        <div class="px-4 py-4">${inner}</div>
                    </div>`;
            }

            function renderHistory(bom, prs) {
                const bomSteps = [
                    { label: 'BOM Submitted', done: true, at: bom.created_at || bom.date_submitted, who: bom.requested_by, role: bom.requested_by_title, sig: bom.requested_by_signature_path, desc: 'Requested by' },
                    { label: 'BOM Approved', done: !!bom.approved_at, at: bom.approved_at, who: bom.approved_by, role: bom.approved_by_title || 'Superadmin', sig: bom.approved_by_signature_path, desc: 'Approved by', wait: 'Waiting for approval' },
                    { label: 'BOM Received', done: !!bom.received_at, at: bom.received_at, who: bom.received_by, role: bom.received_by_title || 'Purchasing', sig: bom.received_by_signature_path, desc: 'Received by', wait: 'Waiting for Purchasing' },
                ];
                let html = card('<i class="fa-solid fa-layer-group text-amber-700 mr-1"></i> Bill of Materials', `<span class="font-mono text-[10.5px] text-gray-400">${esc(bom.bom_number)}</span>`, timeline(bomSteps, !prs.length));

                if (!prs.length) {
                    html += card('<i class="fa-solid fa-file-invoice text-amber-700 mr-1"></i> Purchase Request', '',
                        timeline([{ label: 'Purchase Request', done: false, wait: 'Waiting for Purchase Request' }]) +
                        `<a href="${PR_URL}?bom=${bom.id}" class="mt-3 inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800">Create Purchase Request <i class="fa-solid fa-arrow-right text-[10px]"></i></a>`);
                }

                prs.forEach((p, idx) => {
                    const rejected = p.status === 'Rejected';
                    const steps = [
                        { label: 'PR Submitted', done: true, at: p.created_at, who: p.prepared_by, role: p.prepared_by_title || 'Purchasing', sig: p.prepared_by_signature_path, desc: 'Prepared by' },
                        rejected
                            ? { label: 'Returned', done: true, bad: true, at: p.rejected_at, desc: 'Returned by the CEO', note: p.rejection_remarks }
                            : { label: 'Approved', done: p.status === 'Approved', at: p.approved_at, who: p.approved_by, role: p.approved_by_title || 'CEO', sig: p.approved_by_signature_path, desc: 'Approved by', wait: 'Waiting for CEO approval' },
                    ];
                    if (!rejected) steps.push(
                        { label: 'Reviewed', done: !!p.reviewed_at, at: p.reviewed_at, who: p.reviewed_by, role: p.reviewed_by_title || 'Accounting Head', sig: p.reviewed_by_signature_path, desc: 'Reviewed by', wait: 'Waiting for Accounting Head' },
                        { label: 'Received', done: !!p.staff_received_at, at: p.staff_received_at, who: p.staff_received_by, role: 'Accounting Staff', desc: 'Received by', wait: 'Waiting for Accounting Staff', nosig: true },
                        { label: 'Released', done: !!p.released_at, at: p.released_at, who: p.released_by, role: p.released_by_title || 'Custodian', sig: p.released_by_signature_path, desc: 'Released by', wait: 'Waiting for Custodian' },
                        { label: 'Paid', done: !!p.paid_at, at: p.paid_at, desc: 'Paid stamp applied', wait: 'Waiting to be paid' },
                        { label: 'Transmitted', done: !!p.tr_ref_no, at: p.tr_created_at, who: p.tr_issued_by, role: p.tr_ref_no ? 'Transmittal ' + p.tr_ref_no : '', sig: p.tr_issued_sig, desc: 'Issued by', wait: 'Waiting for transmittal' },
                    );
                    const st = prStage(p);
                    html += card(`<i class="fa-solid fa-file-invoice text-amber-700 mr-1"></i> <span class="font-mono text-red-600">${esc(p.req_no)}</span>`,
                        `<span class="inline-flex items-center gap-1.5 text-[10.5px] font-semibold ${st.cls}"><span class="w-1.5 h-1.5 rounded-full ${st.dot}"></span>${st.label}</span>`,
                        timeline(steps, idx === prs.length - 1));
                });
                document.getElementById('poHistBody').innerHTML = html;
            }

            function setDrawer(open) {
                document.getElementById('poHistDrawer').classList.toggle('translate-x-full', !open);
                document.getElementById('poHistOverlay').classList.toggle('hidden', !open);
                if (!open && activeHistId !== null) { activeHistId = null; render(); }
            }
            async function openHistory(id) {
                activeHistId = id;
                render();
                const r = rows.find(x => x.id === id) || {};
                document.getElementById('poHistTitle').textContent = r.bom_number || 'BOM';
                document.getElementById('poHistSub').textContent = r.client_name || '';
                document.getElementById('poHistBody').innerHTML = '<p class="text-xs text-gray-400 text-center py-10"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Loading…</p>';
                setDrawer(true);
                try {
                    const res = await fetch(`${AJAX}?action=history&bom_id=${id}`, { cache: 'no-store' });
                    const data = await res.json();
                    if (!data.success) { document.getElementById('poHistBody').innerHTML = `<p class="text-xs text-red-500 text-center py-10">${esc(data.message)}</p>`; return; }
                    renderHistory(data.bom, data.prs);
                } catch (e) {
                    document.getElementById('poHistBody').innerHTML = '<p class="text-xs text-red-500 text-center py-10">Connection error.</p>';
                }
            }
            document.getElementById('poHistClose').addEventListener('click', () => setDrawer(false));
            document.getElementById('poHistOverlay').addEventListener('click', () => setDrawer(false));
            document.addEventListener('keydown', e => { if (e.key === 'Escape') setDrawer(false); });

            // Naka-highlight ang row na binuksan ang Status History
            let activeHistId = null;
            const rowTone = r => r.id === activeHistId
                ? 'bg-amber-50 shadow-[inset_4px_0_0_#b45309]'
                : 'hover:bg-amber-50/30';
            const histTone = r => r.id === activeHistId
                ? 'text-white bg-amber-700 border-amber-700 hover:bg-amber-800'
                : 'text-gray-600 bg-white border-gray-200 hover:bg-gray-50';

            // Susunod na hakbang: Purchase Request → (kapag Paid) Transmittal
            function nextBtn(r) {
                const primary = 'inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-semibold text-white rounded-lg shadow-sm';
                if (r.tr_id) return `<a href="${TR_URL}?view=${r.tr_id}" class="${primary} bg-green-600 hover:bg-green-700"><i class="fa-solid fa-file-circle-check"></i> View Transmittal</a>`;
                if (r.pr_id && r.pr_paid_at && r.pr_status === 'Approved') return `<a href="${TR_URL}?pr=${r.pr_id}" class="${primary} bg-amber-700 hover:bg-amber-800">Next: Transmittal <i class="fa-solid fa-arrow-right text-[10px]"></i></a>`;
                if (r.pr_id && r.pr_status !== 'Rejected') return `<span title="A transmittal can be made once the purchase request is paid" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-semibold text-gray-400 bg-gray-100 border border-gray-200 rounded-lg cursor-not-allowed"><i class="fa-solid fa-lock text-[10px]"></i> Transmittal</span>`;
                return `<a href="${PR_URL}?bom=${r.id}" class="${primary} bg-amber-700 hover:bg-amber-800">Next: Purchase Request <i class="fa-solid fa-arrow-right text-[10px]"></i></a>`;
            }

            // ── render list ──
            function render() {
                const pending = rows.filter(r => !r.is_received);
                const received = rows.filter(r => r.is_received);

                document.getElementById('poCountPending').textContent = pending.length;
                document.getElementById('poCountReceived').textContent = received.length;
                document.getElementById('poStatPending').textContent = pending.length;
                document.getElementById('poStatReceived').textContent = received.length;
                document.getElementById('poStatToday').textContent = received.filter(r => r.received_today).length;

                const q = document.getElementById('poSearch').value.trim().toLowerCase();
                const source = tab === 'pending' ? pending : received;
                const list = source.filter(r => !q || `${r.bom_number} ${r.control_no ?? ''} ${r.client_name}`.toLowerCase().includes(q));

                const body = document.getElementById('poList');
                if (!list.length) {
                    const msg = source.length
                        ? 'No BOMs match your search.'
                        : (tab === 'pending' ? 'Nothing to receive. New approved BOMs will appear here.' : 'No received BOMs yet.');
                    const icon = tab === 'pending' && !source.length ? 'fa-regular fa-circle-check text-green-400' : 'fa-regular fa-folder-open text-gray-300';
                    body.innerHTML = `<tr><td colspan="7" class="px-5 py-14 text-center text-xs text-gray-400"><i class="${icon} text-2xl block mb-2"></i>${msg}</td></tr>`;
                } else {
                    body.innerHTML = list.map(r => {
                        const status = r.is_received
                            ? `<p class="text-gray-700">${fmtDate(r.received_at)}</p><p class="text-[11px] text-gray-400">${fmtTime(r.received_at)} · ${esc(r.received_by)}</p>${prChip(r)}`
                            : `<span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-[10.5px] font-semibold border bg-amber-50 text-amber-700 border-amber-200">
                                   <i class="fa-solid fa-lock text-[9px]"></i> Awaiting receipt
                               </span>`;
                        const action = r.is_received
                            ? `<div class="inline-flex items-center gap-1.5">
                                   ${nextBtn(r)}
                                   <button type="button" data-menu="${r.id}" title="More actions"
                                       class="w-8 h-8 inline-flex items-center justify-center rounded-lg border ${histTone(r)}">
                                       <i class="fa-solid fa-ellipsis-vertical text-xs"></i>
                                   </button>
                               </div>`
                            : `<button type="button" data-receive="${r.id}"
                                   class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-[11px] font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 shadow-sm">
                                   <i class="fa-solid fa-hand-holding"></i> Receive
                               </button>`;
                        return `
                        <tr class="${rowTone(r)} transition-colors">
                            <td class="px-5 py-3.5">
                                <p class="font-mono text-[12px] font-semibold text-amber-700">${esc(r.bom_number)}</p>
                                <p class="text-[11px] text-gray-400">Submitted ${fmtDate(r.date_submitted)}</p>
                            </td>
                            <td class="px-5 py-3.5 max-w-[220px]">
                                <p class="font-semibold text-gray-800 truncate">${esc(r.client_name)}</p>
                                <p class="font-mono text-[11px] text-gray-400 truncate">${esc(r.control_no ?? '—')}</p>
                            </td>
                            <td class="px-5 py-3.5 text-center tabular-nums text-gray-700">${r.item_count}</td>
                            <td class="px-5 py-3.5 text-gray-700">${esc(r.date_needed || '—')}</td>
                            <td class="px-5 py-3.5">
                                <p class="text-gray-700">${fmtDate(r.approved_at)}</p>
                                <p class="text-[11px] text-gray-400 truncate">by ${esc(r.approved_by || '—')}</p>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap">${status}</td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap">${action}</td>
                        </tr>`;
                    }).join('');
                }

                document.getElementById('poFooter').textContent = `Showing ${list.length} of ${source.length} BOM${source.length === 1 ? '' : 's'}`;
            }

            // ── load ──
            async function load() {
                try {
                    const res = await fetch(`${AJAX}?action=list`, { cache: 'no-store' });
                    const data = await res.json();
                    if (!data.success) { toast(data.message || 'Failed to load purchase orders.', 'error'); return; }
                    rows = data.rows;
                    render();
                } catch (e) {
                    console.error(e);
                    toast('Connection error while loading purchase orders.', 'error');
                }
            }

            // ── row buttons ──
            document.getElementById('poList').addEventListener('click', e => {
                const rec = e.target.closest('[data-receive]');
                const view = e.target.closest('[data-view]');
                if (rec) askReceive(+rec.dataset.receive);
                if (view) openBom(+view.dataset.view);
                const menuBtn = e.target.closest('[data-menu]');
                if (menuBtn) toggleRowMenu(menuBtn);
            });

            // ── row "more actions" menu (nakalutang sa body para hindi maputol ng table) ──
            const rowMenu = document.getElementById('poRowMenu');
            let menuFor = null;
            function closeRowMenu() { rowMenu.classList.add('hidden'); menuFor = null; }
            function toggleRowMenu(btn) {
                const id = +btn.dataset.menu;
                if (menuFor === id) return closeRowMenu();
                menuFor = id;
                const row = rows.find(x => x.id === id) || {};
                const item = 'w-full flex items-center gap-2.5 px-3 py-2 text-xs text-gray-700 hover:bg-amber-50 rounded-md';
                const extra = (row.pr_id ? `
                    <a href="${PR_URL}?view=${row.pr_id}" class="${item}">
                        <i class="fa-solid fa-file-invoice w-4 text-center text-amber-700"></i> View Purchase Request
                    </a>` : '') + (row.tr_id ? `
                    <a href="${TR_URL}?view=${row.tr_id}" class="${item}">
                        <i class="fa-solid fa-file-export w-4 text-center text-green-600"></i> View Transmittal
                    </a>` : '');
                rowMenu.innerHTML = extra + (extra ? '<div class="my-1 border-t border-gray-100"></div>' : '') + `
                    <button type="button" data-mact="history" class="w-full flex items-center gap-2.5 px-3 py-2 text-xs text-gray-700 hover:bg-amber-50 rounded-md text-left">
                        <i class="fa-solid fa-clock-rotate-left w-4 text-center text-amber-700"></i> Status History
                    </button>
                    <button type="button" data-mact="view" class="w-full flex items-center gap-2.5 px-3 py-2 text-xs text-gray-700 hover:bg-amber-50 rounded-md text-left">
                        <i class="fa-solid fa-eye w-4 text-center text-gray-500"></i> View BOM
                    </button>
                    <a href="${AJAX}?action=pdf&bom_id=${id}" target="_blank" data-mact="pdf" class="w-full flex items-center gap-2.5 px-3 py-2 text-xs text-gray-700 hover:bg-amber-50 rounded-md">
                        <i class="fa-solid fa-file-pdf w-4 text-center text-red-500"></i> Download PDF
                    </a>`;
                rowMenu.classList.remove('hidden');
                const r = btn.getBoundingClientRect(), mw = rowMenu.offsetWidth, mh = rowMenu.offsetHeight;
                const top = (r.bottom + 6 + mh > window.innerHeight) ? r.top - mh - 6 : r.bottom + 6;
                rowMenu.style.top = Math.max(8, top) + 'px';
                rowMenu.style.left = Math.max(8, r.right - mw) + 'px';
            }
            rowMenu.addEventListener('click', e => {
                const it = e.target.closest('[data-mact]');
                if (!it || menuFor === null) return;
                const id = menuFor, act = it.dataset.mact;
                closeRowMenu();
                if (act === 'history') openHistory(id);
                if (act === 'view') openBom(id);
            });
            document.addEventListener('click', e => { if (menuFor !== null && !e.target.closest('#poRowMenu, [data-menu]')) closeRowMenu(); });
            window.addEventListener('scroll', closeRowMenu, true);
            window.addEventListener('resize', closeRowMenu);
            document.addEventListener('keydown', e => { if (e.key === 'Escape') closeRowMenu(); });

            // ── receive ──
            function askReceive(id) {
                const r = rows.find(x => x.id === id);
                if (!r) return;
                receiveTarget = r;
                document.getElementById('poReceiveNo').textContent = r.bom_number;
                document.getElementById('poReceiveClient').textContent = r.client_name;
                document.getElementById('poReceiveItems').textContent = `${r.item_count} item${r.item_count === 1 ? '' : 's'}`;
                openModal('poReceiveModal');
            }

            document.getElementById('poReceiveCancel').addEventListener('click', () => closeModal('poReceiveModal'));

            document.getElementById('poReceiveConfirm').addEventListener('click', async function () {
                if (!receiveTarget) return;
                const btn = this;
                const id = receiveTarget.id;
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Receiving…';
                try {
                    const res = await fetch(`${AJAX}?action=receive`, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ bom_id: id })
                    });
                    const data = await res.json();
                    closeModal('poReceiveModal');
                    if (!data.success) {
                        toast(data.message || 'Could not receive this BOM.', 'error');
                        load();
                        return;
                    }
                    toast(data.has_signature
                        ? 'BOM received and signed.'
                        : 'BOM received. No saved signature was found, so only your name was placed.',
                        data.has_signature ? 'success' : 'error', 6000);
                    await load();
                    openBom(id);   // ipakita agad ang na-unlock na BOM
                } catch (e) {
                    console.error(e);
                    toast('Connection error while receiving.', 'error');
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-check mr-1"></i> Receive &amp; Sign';
                    receiveTarget = null;
                }
            });

            // ── BOM view (same printed layout as the cutting / superadmin pages) ──
            async function openBom(id) {
                const body = document.getElementById('poBomBody');
                body.innerHTML = `<p class="text-xs text-gray-400 text-center py-10"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Loading…</p>`;
                document.getElementById('poBomDownload').href = `${AJAX}?action=pdf&bom_id=${id}`;
                openModal('poBomModal');
                try {
                    const res = await fetch(`${AJAX}?action=view&bom_id=${id}`, { cache: 'no-store' });
                    const data = await res.json();
                    if (!data.success) {
                        body.innerHTML = `<p class="text-xs text-red-500 text-center py-10">${esc(data.message || 'Failed to load BOM.')}</p>`;
                        return;
                    }
                    renderBom(data.bom);
                } catch (e) {
                    console.error(e);
                    body.innerHTML = `<p class="text-xs text-red-500 text-center py-10">Connection error while loading.</p>`;
                }
            }

            const bomDate = v => {
                const d = toDate(v);
                if (!d || isNaN(d)) return '—';
                return `${String(d.getDate()).padStart(2, '0')}/${String(d.getMonth() + 1).padStart(2, '0')}/${d.getFullYear()}`;
            };
            const bomQty = q => { const n = parseFloat(q); return isNaN(n) ? '' : n.toFixed(2).replace(/\.?0+$/, ''); };
            const sigUrl = p => p ? `${BASE}/${esc(p)}` : null;

            function renderBom(bom) {
                document.getElementById('poBomModalTitle').textContent = bom.bom_number || 'BOM';
                const items = bom.items || [];
                const cell = 'border border-gray-800 px-2 py-1.5 text-xs';

                let rowsHtml = items.map((it, i) => `
                    <tr>
                        <td class="${cell} text-center">${i + 1}</td>
                        <td class="${cell}">${esc(it.item_code)}</td>
                        <td class="${cell}">${esc(it.item_description)}</td>
                        <td class="${cell} text-center">${bomQty(it.quantity)}</td>
                        <td class="${cell} text-center">${esc(it.unit)}</td>
                        <td class="${cell}">${esc(it.supplier)}</td>
                    </tr>`).join('');
                for (let i = items.length; i < 6; i++) {
                    rowsHtml += `<tr>${(`<td class="${cell}">&nbsp;</td>`).repeat(6)}</tr>`;
                }

                const sigCell = (name, title, sig) => `
                    <td class="relative border border-gray-800 text-center align-bottom px-2 pb-1.5 w-1/4 h-24">
                        ${sig ? `<img src="${sig}" alt="" class="pointer-events-none select-none absolute z-20 left-1/2 -translate-x-1/2 bottom-1 h-20 w-auto max-w-[95%] object-contain">` : ''}
                        <p class="text-xs font-bold">${name ? esc(name) : '&nbsp;'}</p>
                        <p class="text-[9px] italic text-gray-600">${title ? esc(title) : '&nbsp;'}</p>
                    </td>`;

                document.getElementById('poBomBody').innerHTML = `
                    <table class="w-full border-collapse border-l-2 border-r-2 border-t-2 border-gray-800">
                        <tr>
                            <td class="w-[8%]"></td>
                            <td class="text-center w-[62%]" style="font-family:'Arial Narrow',Arial,sans-serif;font-weight:900;font-stretch:condensed;letter-spacing:-2px;font-size:34px;line-height:1.1;">BILL OF MATERIALS</td>
                            <td class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1 w-[8%]">Page</td>
                            <td class="border border-gray-800 bg-gray-200 text-center text-[9px] font-bold uppercase px-2 py-1 w-[22%]">Date Submitted</td>
                        </tr>
                        <tr>
                            <td></td><td></td>
                            <td class="border border-gray-800 text-center text-xs px-2 py-1">${bom.page ?? 1}</td>
                            <td class="border border-gray-800 text-center text-xs px-2 py-1">${bomDate(bom.date_submitted)}</td>
                        </tr>
                    </table>
                    <table class="w-full border-collapse -mt-px border-l-2 border-r-2 border-gray-800">
                        <tr>
                            <td class="border border-gray-800 bg-gray-200 text-[9px] font-bold uppercase px-2 py-1 w-[20%]">J.O Number:</td>
                            <td class="border border-gray-800 bg-gray-200 text-center text-[9px] font-bold uppercase px-2 py-1 w-[58%]" colspan="2">Client</td>
                            <td class="border border-gray-800 bg-gray-200 text-center text-[9px] font-bold uppercase px-2 py-1 w-[22%]">Date Needed</td>
                        </tr>
                        <tr>
                            <td class="border border-gray-800 text-xs px-2 py-1.5">${esc(bom.bom_number)}</td>
                            <td class="border border-gray-800 text-center text-sm font-bold px-2 py-1.5" colspan="2">${esc(bom.client_name)}</td>
                            <td class="border border-gray-800 text-center text-xs px-2 py-1.5">${esc(bom.date_needed)}</td>
                        </tr>
                    </table>
                    <table class="w-full border-collapse -mt-px border-l-2 border-r-2 border-gray-800">
                        <tr>
                            <td class="border border-gray-800 bg-gray-200 text-[9px] font-bold uppercase px-2 py-1 w-[10%]">Scope:</td>
                            <td class="border border-gray-800 bg-gray-200 text-[9px] font-bold uppercase px-2 py-1">${esc(bom.general_scope)}</td>
                        </tr>
                    </table>
                    <table class="w-full border-collapse -mt-px border-l-2 border-r-2 border-gray-800">
                        <thead>
                            <tr class="bg-gray-200">
                                <th class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1.5 w-[5%]">No.</th>
                                <th class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1.5 w-[15%]">Item Code</th>
                                <th class="border border-gray-800 text-left text-[9px] font-bold uppercase px-2 py-1.5 w-[35%]">Item Description</th>
                                <th class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1.5 w-[10%]">Quantity</th>
                                <th class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1.5 w-[10%]">Unit</th>
                                <th class="border border-gray-800 text-left text-[9px] font-bold uppercase px-2 py-1.5 w-[25%]">Supplier</th>
                            </tr>
                        </thead>
                        <tbody>${rowsHtml}</tbody>
                        <tr><td colspan="6" class="border border-gray-800 text-center text-red-600 font-bold italic text-xs py-2">** NOTHING FOLLOWS **</td></tr>
                    </table>
                    <table class="w-full border-collapse -mt-px border-l-2 border-r-2 border-b-2 border-gray-800">
                        <tr class="bg-gray-200">
                            <td class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1 w-1/4">Requested By:</td>
                            <td class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1 w-1/4">Noted By:</td>
                            <td class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1 w-1/4">Received By:</td>
                            <td class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1 w-1/4">Approved By:</td>
                        </tr>
                        <tr>
                            ${sigCell(bom.requested_by, bom.requested_by_title, sigUrl(bom.requested_by_signature_path))}
                            ${sigCell(bom.noted_by, bom.noted_by_title, null)}
                            ${sigCell(bom.received_by, bom.received_by_title, sigUrl(bom.received_by_signature_path))}
                            ${sigCell(bom.approved_by, bom.approved_by_title, sigUrl(bom.approved_by_signature_path))}
                        </tr>
                        <tr><td colspan="4" class="text-center text-[8px] text-gray-500 py-1.5">PROPERTY OF NOBLEHOME CONSTRUCTION</td></tr>
                    </table>`;
            }

            // ── modal close ──
            document.getElementById('poBomClose').addEventListener('click', () => closeModal('poBomModal'));
            ['poBomModal', 'poReceiveModal'].forEach(id => {
                document.getElementById(id).addEventListener('click', e => { if (e.target.id === id) closeModal(id); });
            });
            document.addEventListener('keydown', e => {
                if (e.key !== 'Escape') return;
                closeModal('poBomModal');
                closeModal('poReceiveModal');
            });

            // ── init + auto refresh ──
            setTab(memo.get('tab') === 'received' ? 'received' : 'pending');
            load();
            setInterval(() => { if (!document.hidden && !anyModalOpen()) load(); }, 30000);
        })();
    </script>
</body>

</html>