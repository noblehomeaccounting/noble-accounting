<?php
// superadmin-turnover.php  —  Superadmin: Turnover Letter approvals (Step 8)

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SUPERADMIN];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$saAjaxUrl = BASE_URL . '/superadminturnoverajax';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Turnover Approvals</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <style>
        .dr-hazard { background: repeating-linear-gradient(-45deg, #f59e0b 0 12px, #1f2937 12px 24px); }
        [data-picked="1"] { box-shadow: 0 0 0 2px #b45309; }
        /* ── Turnover letter (kapareho ng sample) ── */
        .todoc { font-family: Calibri, Arial, Helvetica, sans-serif; color: #111; font-size: 12px; line-height: 1.45; width: 720px; padding: 6px 10px; }
        .todoc .hd { display: flex; align-items: center; gap: 16px; border-bottom: 3px solid #e8650a; padding-bottom: 8px; }
        .todoc .hd img { height: 62px; }
        .todoc .hd .co { flex: 1; text-align: center; color: #6b7280; font-weight: 700; letter-spacing: .03em; }
        .todoc .hd .co b { display: block; font-size: 13px; color: #374151; }
        .todoc .hd .co span { font-size: 10px; font-weight: 600; }
        .todoc h2 { text-align: center; font-size: 13.5px; font-weight: 800; margin: 16px 0 12px; letter-spacing: .02em; }
        .todoc p { margin: 0 0 9px; }
        .todoc .meta p { margin: 0 0 2px; }
        .todoc .subj { font-weight: 700; margin: 12px 0; }
        .todoc table { border-collapse: collapse; width: 100%; margin: 6px 0 12px; }
        .todoc th, .todoc td { border: 1px solid #111; padding: 4px 8px; vertical-align: top; text-align: left; }
        .todoc th { font-weight: 700; font-size: 11px; }
        .todoc td ul { margin: 0; padding-left: 16px; }
        .todoc .docs { margin: -4px 0 10px; font-size: 11.5px; }
        .todoc .docs ul { margin: 2px 0 0; padding-left: 18px; }
        .todoc .sigblk { margin-top: 10px; }
        .todoc .sigimg { height: 46px; display: flex; align-items: flex-end; }
        .todoc .sigimg img { max-height: 46px; max-width: 170px; object-fit: contain; }
        .todoc .nm { font-weight: 700; }
        .todoc .here { border: 2px dashed #f59e0b; border-radius: 6px; background: #fffbeb; color: #b45309; font-size: 10px; font-weight: 700; padding: 3px 8px; }
        .todoc .conf { margin-top: 16px; }
        .todoc .conf .line { width: 230px; border-bottom: 1px solid #111; height: 40px; }
        .tdwm { position: relative; overflow: hidden; }
        .tdwm::after { content: 'FOR APPROVAL — NOT VALID'; position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font: 700 40px/1 Arial, sans-serif; color: rgba(220, 38, 38, .12); transform: rotate(-24deg); pointer-events: none; }
        #nhPrintArea { display: none; }
        @media print {
            @page { size: A4 portrait; margin: 14mm; }
            body > *:not(#nhPrintArea) { display: none !important; }
            #nhPrintArea { display: block !important; }
            #nhPrintArea .todoc { width: auto; max-width: 720px; margin: 0 auto; }
            #nhPrintArea, #nhPrintArea * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>

<body class="bg-gray-50">
    <main id="main-content" class="md:ml-56 pt-20 md:pt-5 min-h-screen p-4 md:p-8 transition-all duration-300">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-key text-lg"></i></div>
                <div class="min-w-0"><p class="text-[10px] font-bold uppercase tracking-[0.15em] text-amber-700">Superadmin · Step 8</p>
                    <h1 class="text-xl font-bold text-gray-900 leading-tight">Turnover Letter Approvals</h1>
                    <p class="text-xs text-gray-500">Sign the turnover letters prepared by the Project Coordinator. They print it for the client after you sign.</p></div>
            </div>
            <span class="inline-flex items-center gap-1.5 text-[11px] text-gray-500 bg-white border border-gray-200 rounded-full px-3 py-1.5"><span id="saLiveDot" class="w-2 h-2 rounded-full bg-amber-400"></span><span id="saLiveText">Auto-refresh</span><span class="text-gray-300">·</span><span id="saUpdated">Loading…</span></span>
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
            <?php foreach ([
                ['pending_sa', 'For signing', 'fa-signature', 'bg-amber-50 text-amber-700'],
                ['returned', 'Returned to PC', 'fa-rotate-left', 'bg-red-50 text-red-600'],
                ['approved', 'Signed · with client', 'fa-user-pen', 'bg-violet-50 text-violet-600'],
                ['closed', 'Closed', 'fa-flag-checkered', 'bg-emerald-50 text-emerald-600'],
                ['all', 'All', 'fa-layer-group', 'bg-gray-100 text-gray-600'],
            ] as [$k, $label, $icon, $cls]): ?>
                <button type="button" data-tab="<?= $k ?>" class="sa-tab text-left bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3 shadow-sm min-w-0 transition hover:border-amber-400 data-[active=true]:border-amber-600 data-[active=true]:ring-2 data-[active=true]:ring-amber-100 data-[active=true]:bg-amber-50/40">
                    <span class="hidden sm:flex w-10 h-10 rounded-lg items-center justify-center shrink-0 <?= $cls ?>"><i class="fa-solid <?= $icon ?>"></i></span>
                    <span class="min-w-0"><span class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide truncate"><?= $label ?></span><span class="block text-2xl font-bold text-gray-900 leading-tight tabular-nums" data-count="<?= $k ?>">—</span></span>
                </button>
            <?php endforeach; ?>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100"><div class="relative"><i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                <input type="text" id="saSearch" placeholder="Search project, client, DR no.…" class="w-full h-9 bg-white border border-gray-200 rounded-lg pl-9 pr-3 text-xs outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100"></div></div>
            <div id="saInfo" class="px-4 py-2 text-[11px] text-gray-500 bg-gray-50 border-b border-gray-100">&nbsp;</div>
            <div id="saList" class="divide-y divide-gray-100"><div class="px-6 py-14 text-center text-xs text-gray-400"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Loading…</div></div>
        </div>
        <div id="bpToast" class="fixed bottom-4 right-4 z-[10002] flex flex-col gap-2.5 pointer-events-none w-[340px] max-w-[calc(100vw-2rem)]"></div>
    </main>
    <div id="saModal" class="hidden fixed inset-0 z-[9999] items-start justify-center bg-black/50 p-2 md:p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-6xl my-auto overflow-hidden">
            <div class="flex items-start justify-between gap-3 px-5 py-4 border-b border-gray-100">
                <div class="min-w-0"><p class="text-[10px] font-bold uppercase tracking-[0.15em] text-amber-700">Turnover letter</p><p id="smTitle" class="text-base font-semibold text-gray-900 truncate"></p><p id="smSub" class="text-[11px] text-gray-400 truncate"></p></div>
                <div class="flex items-center gap-2 shrink-0"><button type="button" id="smPrint" class="hidden inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-gray-900 rounded-lg hover:bg-gray-800"><i class="fa-solid fa-print"></i> Print</button>
                    <button type="button" id="smClose" class="w-8 h-8 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 text-xl leading-none">&times;</button></div>
            </div>
            <div class="grid lg:grid-cols-[minmax(0,1fr)_330px]">
                <div class="p-4 bg-slate-50 min-w-0 lg:border-r border-gray-100"><div id="smDoc" class="bg-white border border-gray-200 rounded-lg p-4 overflow-hidden"></div></div>
                <div id="smSide" class="p-5 space-y-4"></div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            'use strict';
            const AJAX = <?= json_encode($saAjaxUrl) ?>, AJAX_NAME = 'superadminturnoverajax', MEMO = 'nh-sato-';
            const LOGO = <?= json_encode(BASE_URL . '/icon/logo.webp') ?>;
            const F = id => document.getElementById(id);
            let rows = [], tab = 'pending_sa', lastSig = '', me = '', mySig = null;
            // ── helpers ──
            const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
            const toD = s => { const [y, m, d] = String(s).slice(0, 10).split('-').map(Number); return new Date(y, m - 1, d); };
            const ymd = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
            const addDays = (s, n) => { const d = toD(s); d.setDate(d.getDate() + n); return ymd(d); };
            const okDate = s => /^\d{4}-\d{2}-\d{2}$/.test(s || '') && !isNaN(toD(s));
            const fmtY = s => s ? toD(s).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
            const fmtMDY = s => { if (!s) return ''; const d = toD(s); return `${d.getMonth() + 1}/${d.getDate()}/${d.getFullYear()}`; };
            const money = n => (Number(n) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const ago = s => { if (!s) return ''; const days = Math.floor((Date.now() - new Date(String(s).replace(' ', 'T'))) / 864e5); return days <= 0 ? 'today' : days === 1 ? '1 day ago' : `${days} days ago`; };
            const memo = {
                get: k => { try { return localStorage.getItem(MEMO + k); } catch (e) { return null; } },
                set: (k, v) => { try { localStorage.setItem(MEMO + k, v); } catch (e) { } },
                del: k => { try { localStorage.removeItem(MEMO + k); } catch (e) { } },
            };
            function toast(message, type = 'success', duration = 3500) {
                const p = type === 'success'
                    ? { wrap: 'bg-green-50 border-green-200 text-green-700', icon: 'bg-green-200 text-green-700', sym: '✓' }
                    : { wrap: 'bg-red-50 border-red-200 text-red-700', icon: 'bg-red-200 text-red-700', sym: '!' };
                const t = document.createElement('div');
                t.className = `pointer-events-auto flex items-start gap-2.5 border rounded-lg shadow-lg px-4 py-3 text-sm ${p.wrap} transition-all duration-300`;
                t.innerHTML = `<span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold ${p.icon}">${p.sym}</span><span class="flex-1 leading-relaxed"></span>`;
                t.querySelector('.flex-1').textContent = message;
                F('bpToast').appendChild(t);
                setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, duration);
            }
            async function api(action, params = {}, post = false) {
                const ctrl = new AbortController();
                const timer = setTimeout(() => ctrl.abort(), 20000);
                try {
                    const body = new URLSearchParams();
                    Object.entries(params).forEach(([k, v]) => body.append(k, v ?? ''));
                    const url = post ? `${AJAX}?action=${action}` : `${AJAX}?action=${action}&${body}`;
                    const res = await fetch(url, post ? { method: 'POST', body, signal: ctrl.signal, cache: 'no-store' } : { signal: ctrl.signal, cache: 'no-store' });
                    const text = await res.text();
                    let d;
                    try { d = JSON.parse(text); }
                    catch (e) { throw new Error(/<html|<!doctype/i.test(text) ? `The server returned a page instead of data. Check that "${AJAX_NAME}" is registered in the router, or reload if your session expired.` : 'The server sent an unexpected response.'); }
                    if (!d.success) { const er = new Error(d.message || 'Something went wrong.'); er.field = d.field; throw er; }
                    return d;
                } catch (e) {
                    if (e.name === 'AbortError') throw new Error('The server took too long to respond.');
                    throw e;
                } finally { clearTimeout(timer); }
            }

            // ═════════════ TURNOVER LETTER (shared) ═════════════
            const fmtLong = s => s ? toD(s).toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) : '';
            function toLetter(t, opts = {}) {
                const signed = ['approved', 'closed'].includes(t.status);
                const sig = signed && t.sa_signature_url ? `<img src="${esc(t.sa_signature_url)}" alt="">` : opts.preview ? `<img src="${esc(opts.preview)}" alt="" style="opacity:.55">` : opts.signHere ? '<span class="here">Sign here</span>' : '';
                const docs = String(t.documents || '').split(/\r?\n/).map(x => x.trim()).filter(Boolean);
                return `<div class="todoc">
                    <div class="hd"><img src="${LOGO}" alt=""><div class="co"><b>NOBLEHOME CONSTRUCTION COMPANY</b><span>1181 MC PREMIERE BRGY. BALINGASA QUEZON CITY</span></div></div>
                    <h2>CONSTRUCTION PROJECT TURNOVER LETTER</h2>
                    <p>Date : ${esc(fmtLong(t.letter_date))}</p>
                    <div class="meta"><p>Project : ${esc(t.project || '')}</p><p>Project Location: ${esc(t.location || '')}</p><p>Contractor/Subcontractor: ${esc(t.contractor || '')}</p></div>
                    <p class="subj">SUBJECT: TURNOVER OF COMPLETED CONSTRUCTION WORKS</p>
                    <p>Dear Sir/Madam,</p>
                    <p>This letter serves as the formal turnover of the completed construction works.</p>
                    <table><colgroup><col style="width:58%"><col style="width:22%"><col></colgroup>
                        <tr><th>COMPLETED WORKS SCOPE</th><th>AREA</th><th>STATUS</th></tr>
                        ${(t.scope || []).map(s => `<tr><td><ul><li>${esc(s.scope)}</li></ul></td><td>${esc(s.area || '')}</td><td>${esc(s.status || 'Accomplished')}</td></tr>`).join('') || '<tr><td colspan="3" style="color:#9ca3af">Add the completed works.</td></tr>'}
                    </table>
                    ${docs.length ? `<div class="docs"><b>Documents turned over:</b><ul>${docs.map(d => `<li>${esc(d)}</li>`).join('')}</ul></div>` : ''}
                    <p>We hereby confirm that the following works have been completed in accordance with the approved plans, specifications, and the agreed scope of work. The completed areas have been inspected, cleaned, and cleared of construction debris and excess materials.</p>
                    <p>We respectfully request your final inspection, acceptance, and turnover of the completed works. Any remaining punch-list items identified during the inspection will be rectified accordingly.</p>
                    <p>Thank you for your cooperation and support throughout the completion of the project.</p>
                    <div class="sigblk"><p style="margin:0">Respectfully,</p><div class="sigimg">${sig}</div>
                        <div class="nm">${esc(t.signed_by || 'Mr. Ken Yang')}</div><div>${esc(t.signed_title || 'General Manager')}</div><div>Noblehome Construction Corporation</div></div>
                    <div class="conf"><p style="margin:0">CONFORME / ACCEPTED BY:</p><div class="line"></div>
                        <div class="nm">${esc(t.client_name || '')}</div><div>${esc(t.client_title || 'Owner')}</div>
                        ${t.accepted_date ? `<div style="font-size:11px;margin-top:2px">Date accepted: ${esc(fmtLong(t.accepted_date))}</div>` : '<div style="font-size:11px;margin-top:2px">Date: ____________________</div>'}</div>
                </div>`;
            }
            const TO_ST = {
                pending_sa: { cls: 'bg-sky-50 text-sky-700 ring-sky-600/20', icon: 'fa-signature', text: 'For Superadmin' },
                approved: { cls: 'bg-violet-50 text-violet-700 ring-violet-600/20', icon: 'fa-user-pen', text: 'Signed · get client signature' },
                returned: { cls: 'bg-red-50 text-red-700 ring-red-600/20', icon: 'fa-rotate-left', text: 'Returned' },
                closed: { cls: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', icon: 'fa-flag-checkered', text: 'Closed' },
            };
            const toChip = st => { const s = TO_ST[st]; return s ? `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold ring-1 ${s.cls}"><i class="fa-solid ${s.icon} text-[9px]"></i>${s.text}</span>` : ''; };
            function toPrint(html) {
                let area = F('nhPrintArea');
                if (!area) { area = document.createElement('div'); area.id = 'nhPrintArea'; document.body.appendChild(area); }
                area.innerHTML = html;
                const imgs = [...area.querySelectorAll('img')].filter(i => !i.complete);
                Promise.all(imgs.map(i => new Promise(r => { i.onload = i.onerror = r; }))).then(() => { window.print(); setTimeout(() => { area.innerHTML = ''; }, 500); });
            }

            function render() {
                const inTab = (r, k) => k === 'all' || r.status === k;
                document.querySelectorAll('.sa-tab').forEach(b => b.dataset.active = String(b.dataset.tab === tab));
                document.querySelectorAll('[data-count]').forEach(el => el.textContent = rows.filter(r => inTab(r, el.dataset.count)).length);
                const q = F('saSearch').value.trim().toLowerCase();
                const list = rows.filter(r => inTab(r, tab) && (!q || `${r.project} ${r.project_name ?? ''} ${r.client_name} ${r.dr_no} ${r.location ?? ''}`.toLowerCase().includes(q)));
                F('saInfo').innerHTML = `<b class="text-gray-700">${list.length}</b> letter${list.length === 1 ? '' : 's'}${q ? ' (filtered)' : ''}`;
                if (!list.length) { F('saList').innerHTML = `<div class="px-6 py-14 text-center"><div class="w-12 h-12 mx-auto rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center mb-3"><i class="fa-solid ${tab === 'pending_sa' && !q ? 'fa-mug-hot' : 'fa-file-circle-question'}"></i></div><p class="text-sm font-semibold text-gray-700">${q ? 'No match.' : tab === 'pending_sa' ? 'All caught up! No turnover letter is waiting for your signature.' : 'Nothing here.'}</p></div>`; return; }
                const BAR = { pending_sa: 'bg-amber-500', returned: 'bg-red-500', approved: 'bg-violet-500', closed: 'bg-emerald-600' };
                F('saList').innerHTML = list.map(r => {
                    const mine = r.status === 'pending_sa';
                    const sub = r.status === 'returned' ? `<span class="text-red-700"><i class="fa-solid fa-comment-dots mr-1"></i>${esc(r.return_remarks || '')}</span>` : r.status === 'closed' ? `<span class="text-emerald-700"><i class="fa-solid fa-flag-checkered mr-1"></i>Closed ${fmtY(r.closed_at)} · accepted ${fmtY(r.accepted_date)}</span>`
                        : r.status === 'approved' ? `<span class="text-violet-700"><i class="fa-solid fa-signature mr-1"></i>Signed ${fmtY(r.sa_at)} · waiting for the client</span>` : `<span class="text-amber-700"><i class="fa-regular fa-clock mr-1"></i>Submitted ${ago(r.submitted_at)} by ${esc(r.prepared_by || '')}</span>`;
                    return `<button type="button" data-id="${r.id}" class="sa-row relative w-full text-left flex flex-wrap items-center gap-x-5 gap-y-2 pl-5 pr-4 py-3.5 hover:bg-amber-50/40 transition">
                        <span class="absolute left-0 top-0 bottom-0 w-1 ${BAR[r.status]}"></span>
                        <span class="min-w-0 flex-1 basis-[260px]"><span class="flex flex-wrap items-center gap-2"><span class="text-sm font-bold text-gray-900 truncate">${esc(r.project)} · ${esc(r.client_name)}</span>${toChip(r.status)}</span>
                            <span class="block text-[11px] text-gray-400 truncate mt-0.5"><span class="font-mono">DR# ${esc(r.dr_no)}</span>${r.location ? ' · ' + esc(r.location) : ''} · ${esc(r.subcontractor || '')}</span><span class="block text-[11px] truncate mt-0.5">${sub}</span></span>
                        <span class="grid grid-cols-3 gap-x-5 text-[11px] basis-[280px] grow sm:grow-0">
                            <span><span class="block text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Letter date</span><span class="text-gray-700">${fmtY(r.letter_date)}</span></span>
                            <span><span class="block text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Works</span><span class="text-gray-700">${r.scope_count}</span></span>
                            <span><span class="block text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Punch items</span><span class="text-gray-700">${r.punch_total} verified</span></span></span>
                        <span class="ml-auto shrink-0 inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg ${mine ? 'text-white bg-amber-700 shadow-sm' : 'text-gray-600 bg-white border border-gray-200'}"><i class="fa-solid ${mine ? 'fa-pen-nib' : 'fa-eye'}"></i> ${mine ? 'Review & sign' : 'View'}</span></button>`;
                }).join('');
                let id = null; try { id = sessionStorage.getItem(MEMO + 'picked'); } catch (e) { }
                document.querySelectorAll('.sa-row').forEach(el => el.setAttribute('data-picked', el.dataset.id === id ? '1' : '0'));
            }
            document.querySelectorAll('.sa-tab').forEach(b => b.addEventListener('click', () => { tab = b.dataset.tab; memo.set('tab', tab); render(); }));
            F('saSearch').addEventListener('input', render);
            F('saList').addEventListener('click', e => { const b = e.target.closest('.sa-row'); if (!b) return; try { sessionStorage.setItem(MEMO + 'picked', b.dataset.id); } catch (er) { } render(); openReview(+b.dataset.id); });
            async function load(silent) {
                try {
                    const d = await api('list'); me = d.me || ''; mySig = d.my_signature || null;
                    F('saUpdated').textContent = 'Updated ' + new Date().toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' });
                    const sig = JSON.stringify(d.rows.map(r => [r.id, r.status]));
                    if (silent && sig === lastSig) return;
                    if (silent && lastSig) { const known = new Set(rows.filter(r => r.status === 'pending_sa').map(r => r.id)); const n = d.rows.filter(r => r.status === 'pending_sa' && !known.has(r.id)).length; if (n) toast(`${n} new turnover letter${n === 1 ? '' : 's'} to sign.`); }
                    lastSig = sig; rows = d.rows; render();
                } catch (e) { if (silent) return; F('saList').innerHTML = `<div class="px-6 py-12 text-center text-xs text-red-600">${esc(e.message)}</div>`; }
            }
            let cur = null;
            const openM = () => { F('saModal').classList.remove('hidden'); F('saModal').classList.add('flex'); };
            const closeM = () => { F('saModal').classList.add('hidden'); F('saModal').classList.remove('flex'); cur = null; };
            F('smClose').addEventListener('click', closeM);
            F('saModal').addEventListener('click', e => { if (e.target === F('saModal')) closeM(); });
            document.addEventListener('keydown', e => { if (e.key === 'Escape' && cur) closeM(); });
            function fitDoc() { const d = F('smDoc').querySelector('.todoc'); if (!d) return; const w = F('smDoc').clientWidth - 34; if (w <= 0) return requestAnimationFrame(fitDoc); d.style.zoom = String(Math.max(.35, Math.min(1, w / 740))); }
            window.addEventListener('resize', () => { if (cur) fitDoc(); });
            // quiet = realtime refresh: walang error toast, at hindi na bubuksan kapag naisara / may ginagawa habang naglo-load
            async function openReview(id, quiet) {
                let d; try { d = await api('get', { id }); } catch (e) { return quiet ? null : toast(e.message, 'error'); }
                if (quiet && (!cur || cur.turnover.id !== id || F('saModal').classList.contains('hidden') || rtBusy())) return;
                cur = d; const t = d.turnover;
                F('smTitle').textContent = `${t.project} · ${t.client_name}`;
                F('smSub').textContent = `DR# ${t.dr_no} · ${t.quote_no || ''} · prepared by ${t.prepared_by || ''}`;
                F('smPrint').classList.toggle('hidden', !['approved', 'closed'].includes(t.status));
                F('smDoc').innerHTML = toLetter(t, t.status === 'pending_sa' ? (mySig ? { preview: mySig } : { signHere: true }) : {});
                renderSide(); openM(); fitDoc();
            }
            function renderSide() {
                const t = cur.turnover, side = F('smSide');
                const pv = cur.punch.filter(p => p.status === 'verified').length;
                const facts = `<div class="rounded-xl border border-gray-200 p-3 text-[11px] space-y-1.5">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500">Before turnover</p>
                    <p><i class="fa-solid fa-circle-check text-emerald-600 mr-1"></i>Punch list: <b>${pv}/${cur.punch.length}</b> verified${cur.punch.length ? '' : ' (no defects found)'}</p>
                    <p><i class="fa-solid fa-circle-check text-emerald-600 mr-1"></i>Billing: <b>₱ ${money(cur.billing.approved)}</b> approved of ₱ ${money(t.contract_amount)}</p>
                    <p><i class="fa-regular fa-calendar mr-1 text-gray-400"></i>Timeline: ${cur.timeline ? `${fmtY(cur.timeline.date_started)} → ${fmtY(cur.timeline.date_finish)}` : 'none'}</p>
                    ${cur.punch.length ? `<details class="pt-1"><summary class="cursor-pointer font-semibold text-amber-700">See punch items</summary><ul class="mt-1 space-y-1">${cur.punch.map(p => `<li class="text-gray-600"><b>${esc(p.area)}:</b> ${esc(p.deficiency)} <span class="text-emerald-700">✓ ${esc(p.verified_by || '')}</span></li>`).join('')}</ul></details>` : ''}</div>`;
                if (t.status !== 'pending_sa') {
                    const s = { approved: ['border-violet-200 bg-violet-50 text-violet-800', `Signed by ${esc(t.sa_by || '')} on ${fmtY(t.sa_at)}. Waiting for the client.`], returned: ['border-red-200 bg-red-50 text-red-800', `Returned: ${esc(t.return_remarks || '')}`], closed: ['border-emerald-200 bg-emerald-50 text-emerald-800', `Closed ${fmtY(t.closed_at)} · accepted by the client ${fmtY(t.accepted_date)}.${t.signed_copy_url ? ` <a class="underline font-semibold" href="${esc(t.signed_copy_url)}" target="_blank" rel="noopener">Signed copy</a>` : ''}`] }[t.status];
                    side.innerHTML = `<div class="rounded-xl border p-4 text-xs ${s[0]}">${s[1]}</div>${facts}`; return;
                }
                side.innerHTML = `${t.return_remarks ? `<div class="rounded-xl border border-purple-200 bg-purple-50 px-3.5 py-2.5 text-[11px] text-purple-800"><b>Resubmitted after your remarks:</b> ${esc(t.return_remarks)}</div>` : ''}${facts}
                    <section><p class="text-[11px] font-bold uppercase tracking-wider text-gray-500 mb-2"><i class="fa-solid fa-signature mr-1 text-amber-700"></i>Your signature (Respectfully)</p>
                    ${mySig ? `<div class="rounded-xl border border-gray-200 bg-white p-3 flex items-center justify-center h-24"><img src="${esc(mySig)}" alt="" class="max-h-full max-w-full object-contain"></div><p class="text-[11px] text-gray-500 mt-2">Signing as <b class="text-gray-800">${esc(me)}</b>.</p>`
                        : `<div class="rounded-xl border border-amber-200 bg-amber-50 p-3.5 text-xs text-amber-900"><b>No active signature yet.</b> <a href="<?= BASE_URL ?>/signaturedinsert" class="font-semibold underline">Go to Insert Signature</a></div>`}</section>
                    <p id="smErr" class="hidden rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-[11px] text-red-700"></p><div id="smApprove"></div>
                    <details id="smRet" class="group rounded-xl border border-gray-200"><summary class="list-none cursor-pointer flex items-center justify-between px-3.5 py-2.5 text-xs font-semibold text-red-700"><span><i class="fa-solid fa-rotate-left mr-1"></i>Return to PC for changes</span><i class="fa-solid fa-chevron-down text-[10px] text-gray-400 group-open:rotate-180 transition"></i></summary>
                        <div class="px-3.5 pb-3.5"><textarea id="smRemarks" rows="3" maxlength="1000" placeholder="What should the PC fix?" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs outline-none focus:border-red-400 focus:ring-2 focus:ring-red-100 resize-y"></textarea><p id="smRemErr" class="hidden text-[11px] text-red-600 mt-1"></p>
                        <button type="button" id="smReturn" class="mt-2 w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 disabled:opacity-60"><i class="fa-solid fa-paper-plane"></i> Send back to PC</button></div></details>`;
                const err = m => { F('smErr').textContent = m || ''; F('smErr').classList.toggle('hidden', !m); };
                const btn = () => { F('smApprove').innerHTML = `<button type="button" id="smSign" ${mySig ? '' : 'disabled'} class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-amber-700 rounded-xl hover:bg-amber-800 shadow-sm disabled:bg-gray-200 disabled:text-gray-400"><i class="fa-solid fa-file-signature"></i> Approve &amp; sign</button>`; F('smSign').addEventListener('click', ask); };
                function ask() {
                    F('smApprove').innerHTML = `<div class="rounded-xl border border-amber-200 bg-amber-50 p-3"><p class="text-xs text-amber-900 mb-2">Sign the turnover letter of <b>${esc(t.project)}</b>? The PC can then print it for the client.</p><div class="flex gap-2"><button type="button" id="smYes" class="flex-1 px-3 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:opacity-60"><i class="fa-solid fa-check"></i> Yes, sign</button><button type="button" id="smNo" class="px-3 py-2 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg">Cancel</button></div></div>`;
                    F('smNo').addEventListener('click', btn);
                    F('smYes').addEventListener('click', async function () { this.disabled = true; try { await api('approve', { id: t.id }, true); toast('Turnover letter signed.'); await load(); openReview(t.id); } catch (e) { btn(); err(e.message); } });
                }
                btn();
                const rk = 'rem-' + t.id; F('smRemarks').value = memo.get(rk) || ''; if (F('smRemarks').value) F('smRet').open = true;
                F('smRemarks').addEventListener('input', () => { memo.set(rk, F('smRemarks').value); F('smRemErr').classList.add('hidden'); });
                F('smReturn').addEventListener('click', async function () {
                    const rem = F('smRemarks').value.trim();
                    if (rem.length < 3) { F('smRemErr').textContent = 'Tell the PC what to fix.'; F('smRemErr').classList.remove('hidden'); F('smRemarks').focus(); return; }
                    this.disabled = true;
                    try { await api('return', { id: t.id, remarks: rem }, true); memo.del(rk); toast('Turnover letter returned to the PC.'); closeM(); load(); } catch (e) { F('smRemErr').textContent = e.message; F('smRemErr').classList.remove('hidden'); } finally { this.disabled = false; }
                });
            }
            F('smPrint').addEventListener('click', () => { if (cur && ['approved', 'closed'].includes(cur.turnover.status)) toPrint(toLetter(cur.turnover)); });
            tab = memo.get('tab') || 'pending_sa'; if (!['pending_sa', 'returned', 'approved', 'closed', 'all'].includes(tab)) tab = 'pending_sa';
            load();

            // ═════════════ REALTIME (koneksyon ng sidebar: window.NH_RT) ═════════════
            // Bagong ipinasa / inayos ng PC (o pinirmahan sa ibang tab) → kunin agad ang listahan.
            // Ang bukas na papel ay nire-refresh din, pero HINDI habang may kumpirmasyon o sinusulat na remarks — hahabol pagkatapos.
            const RT_TYPES = /^(pc_turnover|pc_punch|turnover_)/i;
            const rtLive = () => !!(window.NH_RT && window.NH_RT.enabled && window.NH_RT.isLive());
            const rtBusy = () => !!cur && !!(F('smYes') || F('smRet')?.open || document.activeElement === F('smRemarks'));
            let rtMissed = false, rtPoll = null, rtLoading = false, rtAgain = false;
            async function rtLoad() {
                if (rtLoading) { rtAgain = true; return; }
                rtLoading = true;
                try { await load(true); } catch (e) { } finally { rtLoading = false; if (rtAgain) { rtAgain = false; rtLoad(); } }
            }
            function rtSync() {
                if (document.hidden) { rtMissed = true; return; }
                rtLoad();
                if (rtBusy()) { rtMissed = true; return; }
                rtMissed = false;
                if (!cur || F('saModal').classList.contains('hidden')) return;
                const keep = F('saModal').scrollTop;
                openReview(cur.turnover.id, true).then(() => { F('saModal').scrollTop = keep; });
            }
            function rtStartPolling() {
                if (rtPoll) clearInterval(rtPoll);
                // Live → 60s fallback lang; hindi Live → 30s (dating bilis)
                rtPoll = setInterval(() => { if (!document.hidden && !cur) rtLoad(); }, rtLive() ? 60000 : 30000);
            }
            (function hookRealtime() {
                const hook = () => {
                    if (!window.NH_RT || !window.NH_RT.enabled || hookRealtime.done) return;
                    hookRealtime.done = true;
                    let t = null;
                    window.NH_RT.on('*', 'changed', payload => {
                        const type = String(payload?.type || '');
                        if (type && !RT_TYPES.test(type)) return;   // ibang signal — hindi para dito
                        clearTimeout(t);
                        t = setTimeout(rtSync, 400);
                    });
                    window.NH_RT.onLive(live => {
                        F('saLiveDot').className = 'w-2 h-2 rounded-full ' + (live ? 'bg-green-500 animate-pulse' : 'bg-amber-400');
                        F('saLiveText').textContent = live ? 'Live' : 'Auto-refresh';
                        if (!document.hidden) rtStartPolling();
                        if (live && lastSig) rtSync();
                    });
                };
                hook();
                if (!hookRealtime.done) document.addEventListener('DOMContentLoaded', hook);
            })();
            setInterval(() => { if (rtMissed && !document.hidden && !rtBusy()) rtSync(); }, 5000);
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) { if (rtPoll) clearInterval(rtPoll); }
                else { rtSync(); rtStartPolling(); }
            });
            rtStartPolling();
        })();
    </script>
</body>

</html>