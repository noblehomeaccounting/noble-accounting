<?php
// superadmin-dr.php  —  Superadmin: Delivery Receipt approvals
// Dito pinipirmahan ang DR na gawa ng Project Coordinator. Mapi-print lang ng PC kapag approved na.

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SUPERADMIN];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$saAjaxUrl = BASE_URL . '/superadmindrajax';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DR Approvals</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <style>
        /* ── DR document (kapareho ng nasa PC page) ── */
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
        .drdoc .sign td { text-align: center; padding: 0 14px; }
        .drdoc .sign .sig { height: 46px; display: flex; align-items: flex-end; justify-content: center; }
        .drdoc .sign .sig img { max-height: 46px; max-width: 170px; object-fit: contain; }
        .drdoc .sign .nm { min-height: 15px; font-weight: 700; } .drdoc .sign .ln { border-top: 1px solid #111; padding-top: 2px; font-size: 10px; }
        .drdoc .sign .when { font-size: 9px; color: #555; margin-top: 1px; }
        .drdoc .sign .here { border: 2px dashed #f59e0b; border-radius: 6px; background: #fffbeb; color: #b45309; font-size: 10px; font-weight: 700; padding: 4px 8px; }

        .sa-scroll::-webkit-scrollbar { width: 6px; height: 6px; } .sa-scroll::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 9px; }
        [data-picked="1"] { box-shadow: 0 0 0 2px #b45309; }

        #nhPrintArea { display: none; }
        @media print {
            @page { size: A4; margin: 12mm; }
            body > *:not(#nhPrintArea) { display: none !important; }
            #nhPrintArea { display: block !important; }
            #nhPrintArea, #nhPrintArea * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>

<body class="bg-gray-50">
    <main id="main-content" class="md:ml-56 pt-20 md:pt-5 min-h-screen p-4 md:p-8 transition-all duration-300">

        <!-- ═══ HEADER ═══ -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-file-signature text-lg"></i></div>
                <div class="min-w-0">
                    <h1 class="text-xl font-bold text-gray-900 leading-tight">Delivery Receipt Approvals</h1>
                    <p class="text-xs text-gray-500">Sign the DRs made by the Project Coordinator. They can print a DR only after you approve it.</p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1.5 text-[11px] text-gray-500 bg-white border border-gray-200 rounded-full px-3 py-1.5">
                <span class="w-2 h-2 rounded-full bg-green-500"></span><span id="saUpdated">Loading…</span>
            </span>
        </div>

        <!-- ═══ STAT TABS ═══ -->
        <div id="saTabs" class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <?php foreach ([
                ['pending', 'For signing', 'fa-signature', 'bg-amber-50 text-amber-700'],
                ['returned', 'Returned to PC', 'fa-rotate-left', 'bg-red-50 text-red-600'],
                ['approved', 'Approved', 'fa-circle-check', 'bg-emerald-50 text-emerald-600'],
                ['all', 'All DRs', 'fa-layer-group', 'bg-gray-100 text-gray-600'],
            ] as [$k, $label, $icon, $cls]): ?>
                <button type="button" data-tab="<?= $k ?>"
                    class="sa-tab text-left bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3 shadow-sm min-w-0 transition hover:border-amber-400 data-[active=true]:border-amber-600 data-[active=true]:ring-2 data-[active=true]:ring-amber-100 data-[active=true]:bg-amber-50/40">
                    <span class="hidden sm:flex w-10 h-10 rounded-lg items-center justify-center shrink-0 <?= $cls ?>"><i class="fa-solid <?= $icon ?>"></i></span>
                    <span class="min-w-0">
                        <span class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide truncate"><?= $label ?></span>
                        <span class="block text-2xl font-bold text-gray-900 leading-tight tabular-nums" data-count="<?= $k ?>">—</span>
                        <span class="block text-[11px] text-gray-400 truncate font-mono" data-sum="<?= $k ?>">&nbsp;</span>
                    </span>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- ═══ LIST ═══ -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-gray-100">
                <div class="relative flex-1 min-w-[200px]">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                    <input type="text" id="saSearch" placeholder="Search DR no., client, transmittal, PR…"
                        class="w-full h-9 bg-white border border-gray-200 rounded-lg pl-9 pr-3 text-xs outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100">
                </div>
            </div>
            <div id="saInfo" class="px-4 py-2 text-[11px] text-gray-500 bg-gray-50 border-b border-gray-100">&nbsp;</div>
            <div id="saList" class="divide-y divide-gray-100">
                <div class="px-6 py-14 text-center text-xs text-gray-400"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Loading…</div>
            </div>
        </div>

        <div id="saToast" class="fixed bottom-4 right-4 z-[10000] flex flex-col gap-2.5 pointer-events-none w-[340px] max-w-[calc(100vw-2rem)]"></div>
    </main>

    <!-- ═══ REVIEW MODAL ═══ -->
    <div id="saModal" class="hidden fixed inset-0 z-[9999] items-start justify-center bg-black/50 p-3 md:p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-6xl my-auto overflow-hidden">
            <div class="flex items-start justify-between gap-3 px-5 py-4 border-b border-gray-100">
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-amber-700">Delivery Receipt</p>
                    <p id="saMTitle" class="text-base font-semibold text-gray-900 truncate"></p>
                    <p id="saMSub" class="text-[11px] text-gray-400 truncate"></p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" id="saPrint" class="hidden inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-gray-900 rounded-lg hover:bg-gray-800"><i class="fa-solid fa-print"></i> Print</button>
                    <button type="button" data-close class="w-8 h-8 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 text-xl leading-none">&times;</button>
                </div>
            </div>
            <div class="grid lg:grid-cols-[minmax(0,1fr)_340px]">
                <!-- Document -->
                <div class="p-4 md:p-5 bg-slate-50 min-w-0 lg:border-r border-gray-100">
                    <div class="overflow-x-auto sa-scroll"><div id="saDoc" class="min-w-[720px] bg-white border border-gray-200 rounded-lg p-6"></div></div>
                </div>
                <!-- Actions -->
                <div id="saSide" class="p-5 space-y-4"></div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            'use strict';
            const AJAX = <?= json_encode($saAjaxUrl) ?>;
            const LOGO = <?= json_encode(BASE_URL . '/icon/logo.webp') ?>;
            const F = id => document.getElementById(id);

            let rows = [], tab = 'pending', me = '', mySignature = null, cur = null, lastSig = '';

            // ── helpers ──
            const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
            const toD = s => { const [y, m, d] = String(s).slice(0, 10).split('-').map(Number); return new Date(y, m - 1, d); };
            const fmtY = s => s ? toD(s).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
            const fmtSlash = s => { if (!s) return ''; const [y, m, d] = String(s).slice(0, 10).split('-'); return `${m}/${d}/${y}`; };
            const money = n => (Number(n) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const num = v => Number(String(v ?? '').replace(/,/g, '')) || 0;
            const qtyFmt = q => { const n = parseFloat(q); return isNaN(n) ? '' : String(+n.toFixed(2)); };
            const ago = s => {
                if (!s) return '';
                const t = new Date(String(s).replace(' ', 'T')); const days = Math.floor((Date.now() - t) / 864e5);
                return days <= 0 ? 'today' : days === 1 ? '1 day ago' : `${days} days ago`;
            };
            const store = {
                get: k => { try { return localStorage.getItem('nh-sadr-' + k); } catch (e) { return null; } },
                set: (k, v) => { try { localStorage.setItem('nh-sadr-' + k, v); } catch (e) { } },
            };

            function toast(message, type = 'success', duration = 3500) {
                const p = type === 'success'
                    ? { wrap: 'bg-green-50 border-green-200 text-green-700', icon: 'bg-green-200 text-green-700', sym: '✓' }
                    : { wrap: 'bg-red-50 border-red-200 text-red-700', icon: 'bg-red-200 text-red-700', sym: '!' };
                const t = document.createElement('div');
                t.className = `pointer-events-auto flex items-start gap-2.5 border rounded-lg shadow-lg px-4 py-3 text-sm ${p.wrap} transition-all duration-300`;
                t.innerHTML = `<span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold ${p.icon}">${p.sym}</span><span class="flex-1 leading-relaxed"></span>`;
                t.querySelector('.flex-1').textContent = message;
                F('saToast').appendChild(t);
                setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, duration);
            }

            async function api(action, params = {}, post = false) {
                const ctrl = new AbortController();
                const timer = setTimeout(() => ctrl.abort(), 20000);
                try {
                    const qs = new URLSearchParams();
                    Object.entries(params).forEach(([k, v]) => qs.append(k, v ?? ''));
                    const url = post ? `${AJAX}?action=${action}` : `${AJAX}?action=${action}&${qs}`;
                    const res = await fetch(url, post ? { method: 'POST', body: qs, signal: ctrl.signal, cache: 'no-store' } : { signal: ctrl.signal, cache: 'no-store' });
                    const text = await res.text();
                    let d;
                    try { d = JSON.parse(text); }
                    catch (e) {
                        throw new Error(/<html|<!doctype/i.test(text)
                            ? 'The server returned a page instead of data. Check that "superadmindrajax" is registered in the router, or reload if your session expired.'
                            : 'The server sent an unexpected response.');
                    }
                    if (!d.success) throw new Error(d.message || 'Something went wrong.');
                    return d;
                } catch (e) {
                    throw new Error(e.name === 'AbortError' ? 'The server took too long to respond.' : e.message);
                } finally { clearTimeout(timer); }
            }

            // ═════════════ LIST ═════════════
            const ST = {
                pending:  { bar: 'bg-amber-500', pill: 'bg-amber-50 text-amber-700 ring-amber-600/20', icon: 'fa-signature', text: 'For signing' },
                returned: { bar: 'bg-red-500', pill: 'bg-red-50 text-red-700 ring-red-600/20', icon: 'fa-rotate-left', text: 'Returned to PC' },
                approved: { bar: 'bg-emerald-500', pill: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', icon: 'fa-circle-check', text: 'Approved' },
            };

            function permitChip(v) {
                if (v === 1) return '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-orange-50 text-orange-700 ring-1 ring-orange-600/20"><i class="fa-solid fa-id-card-clip text-[9px]"></i>Needs permit</span>';
                if (v === 0) return '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-100 text-gray-600 ring-1 ring-gray-500/20"><i class="fa-solid fa-truck-fast text-[9px]"></i>No permit</span>';
                return '';
            }

            function renderTabs() {
                const list = k => k === 'all' ? rows : rows.filter(r => r.status === k);
                document.querySelectorAll('.sa-tab').forEach(b => b.dataset.active = String(b.dataset.tab === tab));
                document.querySelectorAll('[data-count]').forEach(el => el.textContent = list(el.dataset.count).length);
                document.querySelectorAll('[data-sum]').forEach(el => {
                    const l = list(el.dataset.sum);
                    el.textContent = l.length ? '₱ ' + money(l.reduce((s, r) => s + num(r.total), 0)) : ' ';
                });
            }

            function render() {
                renderTabs();
                const q = F('saSearch').value.trim().toLowerCase();
                const list = rows.filter(r => (tab === 'all' || r.status === tab)
                    && (!q || `${r.dr_no} ${r.client_name} ${r.ref_no} ${r.project_name ?? ''} ${r.req_no ?? ''} ${r.prepared_by ?? ''}`.toLowerCase().includes(q)));
                const label = { pending: 'waiting for your signature', returned: 'returned to the PC', approved: 'approved', all: 'in total' }[tab];
                F('saInfo').innerHTML = `<b class="text-gray-700">${list.length}</b> DR${list.length === 1 ? '' : 's'} ${label}${q ? ' (filtered)' : ''}`;

                if (!list.length) {
                    const msg = q ? 'No match.' : { pending: 'All caught up! No DR is waiting for your signature.', returned: 'No DR is waiting for the PC to fix.', approved: 'No approved DR yet.', all: 'No DR yet.' }[tab];
                    F('saList').innerHTML = `<div class="px-6 py-14 text-center"><div class="w-12 h-12 mx-auto rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center mb-3"><i class="fa-solid ${tab === 'pending' && !q ? 'fa-mug-hot' : 'fa-file-circle-question'}"></i></div><p class="text-sm font-semibold text-gray-700">${msg}</p></div>`;
                    return;
                }
                F('saList').innerHTML = list.map(r => {
                    const s = ST[r.status] || ST.pending;
                    const sub = r.status === 'approved' ? `<span class="text-emerald-700"><i class="fa-solid fa-signature mr-1"></i>Signed ${fmtY(r.approved_at)}${r.approved_by ? ' by ' + esc(r.approved_by) : ''}</span>`
                        : r.status === 'returned' ? `<span class="text-red-700"><i class="fa-solid fa-comment-dots mr-1"></i>${esc(r.return_remarks || '')}</span>`
                        : `<span class="text-amber-700"><i class="fa-regular fa-clock mr-1"></i>Submitted ${ago(r.submitted_at)}</span>`;
                    return `<button type="button" data-id="${r.id}" class="sa-row relative w-full text-left flex flex-wrap items-center gap-x-5 gap-y-2 pl-5 pr-4 py-3.5 hover:bg-amber-50/40 transition">
                        <span class="absolute left-0 top-0 bottom-0 w-1 ${s.bar}"></span>
                        <span class="min-w-0 flex-1 basis-[260px]">
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-sm font-bold text-gray-900">DR# ${esc(r.dr_no)}</span>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold ring-1 ${s.pill}"><i class="fa-solid ${s.icon} text-[9px]"></i>${s.text}</span>
                                ${permitChip(r.permit_required)}
                                ${r.resubmitted ? '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-50 text-purple-700 ring-1 ring-purple-600/20"><i class="fa-solid fa-rotate text-[9px]"></i>Resubmitted</span>' : ''}
                            </span>
                            <span class="block text-sm font-semibold text-gray-800 truncate mt-0.5">${esc(r.client_name)}</span>
                            <span class="block text-[11px] truncate mt-0.5">${sub}</span>
                        </span>
                        <span class="grid grid-cols-3 gap-x-5 text-[11px] basis-[300px] grow sm:grow-0">
                            <span><span class="block text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">DR date</span><span class="text-gray-700">${fmtY(r.dr_date)}</span></span>
                            <span><span class="block text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Transmittal</span><span class="font-mono text-gray-700">${esc(r.ref_no)}</span></span>
                            <span><span class="block text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Prepared by</span><span class="text-gray-700 truncate block">${esc(r.prepared_by || '—')}</span></span>
                        </span>
                        <span class="ml-auto shrink-0 flex items-center gap-3">
                            <span class="text-right"><span class="block text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">${r.item_count} item${r.item_count === 1 ? '' : 's'}</span><span class="font-mono text-sm font-bold text-gray-900">₱ ${money(r.total)}</span></span>
                            <span class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg ${r.status === 'pending' ? 'text-white bg-amber-700 shadow-sm' : 'text-gray-600 bg-white border border-gray-200'}">
                                <i class="fa-solid ${r.status === 'pending' ? 'fa-pen-nib' : 'fa-eye'}"></i> ${r.status === 'pending' ? 'Review & sign' : 'View'}</span>
                        </span>
                    </button>`;
                }).join('');
                markPicked();
            }

            // "Last opened" highlight
            function markPicked() {
                const id = sessionStorage.getItem('nh-sadr-picked');
                document.querySelectorAll('.sa-row').forEach(el => el.setAttribute('data-picked', el.dataset.id === id ? '1' : '0'));
            }

            document.querySelectorAll('.sa-tab').forEach(b => b.addEventListener('click', () => { tab = b.dataset.tab; store.set('tab', tab); render(); }));
            F('saSearch').addEventListener('input', render);
            F('saList').addEventListener('click', e => {
                const b = e.target.closest('.sa-row');
                if (!b) return;
                try { sessionStorage.setItem('nh-sadr-picked', b.dataset.id); } catch (err) { }
                markPicked();
                openReview(+b.dataset.id);
            });

            async function load(silent) {
                try {
                    const d = await api('list');
                    me = d.me || '';
                    mySignature = d.my_signature || null;
                    F('saUpdated').textContent = 'Updated ' + new Date().toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' });
                    const sig = JSON.stringify(d.rows.map(r => [r.id, r.status, r.submitted_at]));
                    if (silent && sig === lastSig) return;
                    if (silent && lastSig) {
                        const known = new Set(rows.filter(r => r.status === 'pending').map(r => r.id));
                        const fresh = d.rows.filter(r => r.status === 'pending' && !known.has(r.id)).length;
                        if (fresh) toast(`${fresh} new DR${fresh === 1 ? '' : 's'} waiting for your signature.`);
                    }
                    lastSig = sig;
                    rows = d.rows;
                    render();
                } catch (e) {
                    if (silent) return;
                    F('saList').innerHTML = `<div class="px-6 py-12 text-center text-xs text-red-600">${esc(e.message)}<br><button type="button" id="saRetry" class="mt-2 font-semibold text-amber-700 hover:underline">Retry</button></div>`;
                    F('saRetry').addEventListener('click', () => load());
                }
            }

            // ═════════════ REVIEW MODAL ═════════════
            const openM = () => { F('saModal').classList.remove('hidden'); F('saModal').classList.add('flex'); };
            const closeM = () => { F('saModal').classList.add('hidden'); F('saModal').classList.remove('flex'); cur = null; };
            F('saModal').querySelector('[data-close]').addEventListener('click', closeM);
            F('saModal').addEventListener('click', e => { if (e.target === F('saModal')) closeM(); });
            document.addEventListener('keydown', e => { if (e.key === 'Escape' && !F('saModal').classList.contains('hidden')) closeM(); });

            async function openReview(id) {
                let d;
                try { d = await api('dr', { id }); } catch (e) { return toast(e.message, 'error'); }
                cur = d.dr;
                F('saMTitle').textContent = `DR# ${cur.dr_no} · ${cur.client_name}`;
                F('saMSub').textContent = `Transmittal ${cur.ref_no}${cur.project_name ? ' · ' + cur.project_name : ''} · prepared by ${cur.prepared_by || '—'}`;
                F('saPrint').classList.toggle('hidden', cur.status !== 'approved');
                F('saDoc').innerHTML = drDoc(cur, cur.status === 'pending');
                renderSide();
                openM();
            }

            function renderSide() {
                const side = F('saSide');
                const total = cur.items.reduce((a, i) => a + num(i.amount) * (num(i.qty) || 1), 0);
                const facts = `<dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-[11px] bg-gray-50 border border-gray-100 rounded-xl p-3">
                    <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">DR date</dt><dd class="text-gray-800">${fmtY(cur.dr_date)}</dd></div>
                    <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Delivery sched.</dt><dd class="text-gray-800">${cur.scheduled_date ? fmtY(cur.scheduled_date) : '—'}</dd></div>
                    <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Items</dt><dd class="text-gray-800">${cur.items.length}</dd></div>
                    <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Total</dt><dd class="font-mono font-bold text-gray-900">₱ ${money(total)}</dd></div>
                    <div class="col-span-2"><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Permit</dt><dd class="text-gray-800">${cur.permit_required === 1 ? '<b class="text-orange-700">Needs permit</b> · tracked in Permit Processing' : cur.permit_required === 0 ? 'No permit needed' : '<span class="text-gray-400">Not set</span>'}</dd></div>
                    ${cur.vehicle ? `<div class="col-span-2"><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Vehicle</dt><dd class="text-gray-800">${esc(cur.vehicle)}</dd></div>` : ''}
                </dl>`;

                if (cur.status === 'approved') {
                    side.innerHTML = `<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-xs text-emerald-800">
                        <p class="font-bold text-sm mb-1"><i class="fa-solid fa-circle-check mr-1"></i>Approved</p>
                        <p>Signed by <b>${esc(cur.approved_by || '')}</b> on ${fmtY(cur.approved_at)}. The PC can now print this DR.</p>
                        ${cur.signature_url ? `<img src="${esc(cur.signature_url)}" alt="Signature" class="mt-3 h-14 bg-white border border-emerald-100 rounded-lg p-1.5">` : ''}</div>${facts}`;
                    return;
                }
                if (cur.status === 'returned') {
                    side.innerHTML = `<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-xs text-red-800">
                        <p class="font-bold text-sm mb-1"><i class="fa-solid fa-rotate-left mr-1"></i>Returned to the PC</p>
                        <p class="mb-1">${esc(cur.return_remarks || '')}</p>
                        <p class="text-red-600">${esc(cur.returned_by || '')}${cur.returned_at ? ' · ' + fmtY(cur.returned_at) : ''}</p>
                        <p class="mt-2 text-red-700">It will come back here once the PC fixes and resubmits it.</p></div>${facts}`;
                    return;
                }

                // ── PENDING: sign or return ──
                // Pirma = active signature mo sa Insert Signature (noblesignature), gaya ng sa budget request
                side.innerHTML = `
                    ${cur.return_remarks ? `<div class="rounded-xl border border-purple-200 bg-purple-50 px-3.5 py-2.5 text-[11px] text-purple-800"><p class="font-bold"><i class="fa-solid fa-rotate mr-1"></i>Resubmitted after your remarks:</p><p class="mt-0.5">${esc(cur.return_remarks)}</p></div>` : ''}
                    ${facts}
                    <section>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500 mb-2"><i class="fa-solid fa-signature mr-1 text-amber-700"></i>Your signature</p>
                        ${mySignature
                            ? `<div class="rounded-xl border border-gray-200 bg-white p-3 flex items-center justify-center h-28"><img src="${esc(mySignature)}" alt="Your signature" class="max-h-full max-w-full object-contain"></div>
                               <p class="text-[11px] text-gray-500 mt-2">Signing as <b class="text-gray-800">${esc(me || 'Superadmin')}</b> · goes on <b>Noted By</b>.</p>`
                            : `<div class="rounded-xl border border-amber-200 bg-amber-50 p-3.5 text-xs text-amber-900">
                                <p class="font-bold mb-0.5"><i class="fa-solid fa-triangle-exclamation mr-1"></i>No active signature yet</p>
                                <p>Add your signature first, then come back to approve.</p>
                                <a href="<?= BASE_URL ?>/signaturedinsert" class="inline-flex items-center gap-1 mt-2 font-semibold text-amber-800 hover:underline">Go to Insert Signature <i class="fa-solid fa-arrow-right text-[10px]"></i></a></div>`}
                    </section>
                    <p id="saErr" class="hidden rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-[11px] text-red-700"></p>
                    <div id="saApproveArea">
                        <button type="button" id="saApprove" ${mySignature ? '' : 'disabled'} class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-amber-700 rounded-xl hover:bg-amber-800 shadow-sm disabled:bg-gray-200 disabled:text-gray-400 disabled:shadow-none disabled:cursor-not-allowed"><i class="fa-solid fa-file-signature"></i> Approve &amp; sign</button>
                    </div>
                    <details id="saReturnBox" class="group rounded-xl border border-gray-200">
                        <summary class="list-none cursor-pointer flex items-center justify-between px-3.5 py-2.5 text-xs font-semibold text-red-700"><span><i class="fa-solid fa-rotate-left mr-1"></i>Return to PC for changes</span><i class="fa-solid fa-chevron-down text-[10px] text-gray-400 group-open:rotate-180 transition"></i></summary>
                        <div class="px-3.5 pb-3.5">
                            <textarea id="saRemarks" rows="3" maxlength="1000" placeholder="What should the PC fix? e.g. Wrong quantity for Wardrobe; add the delivery schedule."
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs outline-none focus:border-red-400 focus:ring-2 focus:ring-red-100 resize-y"></textarea>
                            <p id="saRemErr" class="hidden text-[11px] text-red-600 mt-1"></p>
                            <button type="button" id="saReturn" class="mt-2 w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 disabled:opacity-60"><i class="fa-solid fa-paper-plane"></i> Send back to PC</button>
                        </div>
                    </details>`;

                const err = m => { F('saErr').textContent = m || ''; F('saErr').classList.toggle('hidden', !m); };

                // preview: ganito ang lalabas sa DR pag na-approve
                if (mySignature) {
                    F('saDoc').querySelector('[data-noted-sig]').innerHTML = `<img src="${esc(mySignature)}" alt="" style="opacity:.55">`;
                    F('saDoc').querySelector('[data-noted-name]').textContent = me || '';
                }

                // Approve → inline confirm
                F('saApprove').addEventListener('click', askApprove);
                function askApprove() {
                    err('');
                    if (!mySignature) return err('Add your signature in Insert Signature first.');
                    F('saApproveArea').innerHTML = `<div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
                        <p class="text-xs text-amber-900 mb-2">Approve and sign <b>DR# ${esc(cur.dr_no)}</b>? The PC will be able to print it, and it can no longer be edited.</p>
                        <div class="flex gap-2"><button type="button" id="saYes" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:opacity-60"><i class="fa-solid fa-check"></i> Yes, approve</button>
                        <button type="button" id="saNo" class="px-3 py-2 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button></div></div>`;
                    F('saNo').addEventListener('click', renderApproveBtn);
                    F('saYes').addEventListener('click', async function () {
                        this.disabled = true; this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Signing…';
                        try {
                            const d = await api('approve', { id: cur.id }, true);
                            toast(`DR# ${d.dr_no} approved and signed.`);
                            const id = cur.id;
                            await load();
                            openReview(id);
                        } catch (e) { renderApproveBtn(); err(e.message); }
                    });
                }
                function renderApproveBtn() {
                    F('saApproveArea').innerHTML = `<button type="button" id="saApprove" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-amber-700 rounded-xl hover:bg-amber-800 shadow-sm"><i class="fa-solid fa-file-signature"></i> Approve &amp; sign</button>`;
                    F('saApprove').addEventListener('click', askApprove);
                }

                // Return (draft ng remarks per DR)
                const remKey = 'rem-' + cur.id;
                F('saRemarks').value = store.get(remKey) || '';
                if (F('saRemarks').value) F('saReturnBox').open = true;
                F('saRemarks').addEventListener('input', () => { store.set(remKey, F('saRemarks').value); F('saRemErr').classList.add('hidden'); F('saRemarks').removeAttribute('aria-invalid'); });
                F('saReturn').addEventListener('click', async function () {
                    const rem = F('saRemarks').value.trim();
                    if (rem.length < 3) {
                        F('saRemErr').textContent = 'Tell the PC what to fix.'; F('saRemErr').classList.remove('hidden');
                        F('saRemarks').setAttribute('aria-invalid', 'true'); F('saRemarks').focus(); return;
                    }
                    this.disabled = true;
                    try {
                        await api('return', { id: cur.id, remarks: rem }, true);
                        store.set(remKey, '');
                        toast(`DR# ${cur.dr_no} returned to the PC.`);
                        closeM();
                        load();
                    } catch (e) { F('saRemErr').textContent = e.message; F('saRemErr').classList.remove('hidden'); }
                    finally { this.disabled = false; }
                });
            }

            // ── Print (approved lang) ──
            F('saPrint').addEventListener('click', () => {
                if (!cur || cur.status !== 'approved') return;
                let area = F('nhPrintArea');
                if (!area) { area = document.createElement('div'); area.id = 'nhPrintArea'; document.body.appendChild(area); }
                area.innerHTML = drDoc(cur, false);
                const imgs = [...area.querySelectorAll('img')].filter(i => !i.complete);
                Promise.all(imgs.map(i => new Promise(r => { i.onload = i.onerror = r; }))).then(() => { window.print(); setTimeout(() => { area.innerHTML = ''; }, 500); });
            });

            // ── DR document ──
            function drDoc(d, signing) {
                const total = d.items.reduce((a, i) => a + num(i.amount) * (num(i.qty) || 1), 0);
                const rowsHtml = d.items.map((i, k) => `<tr><td class="c">${k + 1}</td><td class="b">${esc(i.particular)}</td><td class="c">${esc(i.size)}</td><td class="c">${qtyFmt(i.qty)}</td><td>${esc(i.unit)}</td>
                    <td class="r">${num(i.amount) ? money(i.amount) : ''}</td><td class="r">${num(i.amount) ? money(num(i.amount) * (num(i.qty) || 1)) : '-'}</td></tr>`).join('');
                const approved = d.status === 'approved';
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
                    <table class="sign" style="margin-top:22px"><tr>
                        <td><div class="sig"></div><div class="nm">${esc(d.prepared_by || '')}</div><div class="ln">Prepared By:</div></td>
                        <td><div class="sig" data-noted-sig>${approved && d.signature_url ? `<img src="${esc(d.signature_url)}" alt="Signature">` : (signing ? '<span class="here">Sign here</span>' : '')}</div>
                            <div class="nm" data-noted-name>${approved ? esc(d.approved_by || '') : ''}</div><div class="ln">Noted By:</div>
                            ${approved && d.approved_at ? `<div class="when">${esc(fmtSlash(d.approved_at))}</div>` : ''}</td>
                        <td><div class="sig"></div><div class="nm"></div><div class="ln">Received By:</div></td>
                    </tr></table>
                </div>`;
            }

            // ═════════════ INIT ═════════════
            tab = store.get('tab') || 'pending';
            if (!['pending', 'returned', 'approved', 'all'].includes(tab)) tab = 'pending';
            load();
            // bagong DR mula sa PC? tingnan bawat 30s (hindi habang may bukas na DR)
            setInterval(() => { if (!document.hidden && !cur) load(true); }, 30000);
        })();
    </script>
</body>

</html>