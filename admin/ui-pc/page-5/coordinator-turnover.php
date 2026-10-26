<?php
// coordinator-turnover.php  —  Project Coordinator: Step 7 Punch List + Step 8 Turnover & Closeout
// Turnover letter: PC → Superadmin (pirma ng GM) → print → pirma ng client → Complete Turnover (Closed)

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PC];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$toAjaxUrl = BASE_URL . '/coordinatorturnoverajax';
$bpPageUrl = BASE_URL . '/coordinatorbilling';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Punch List & Turnover</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/ui-pc/css/pc/coordinator-turnover.css?v=<?= @filemtime(ROOT_PATH . '/admin/ui-pc/css/pc/coordinator-turnover.css') ?: 1 ?>">
</head>

<body class="bg-gray-50">
    <main id="main-content" class="md:ml-56 pt-20 md:pt-5 min-h-screen p-4 md:p-8 transition-all duration-300">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-key text-lg"></i></div>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-amber-700">Operations · Step 7 &amp; 8</p>
                    <h1 class="text-xl font-bold text-gray-900 leading-tight">Punch List &amp; Turnover</h1>
                    <p class="text-xs text-gray-500">Inspect the work, fix and verify every item, then prepare the turnover letter. The project closes once the client signs.</p>
                </div>
            </div>
            <a href="<?= htmlspecialchars($bpPageUrl) ?>" class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg px-3 py-2 hover:bg-gray-50"><i class="fa-solid fa-file-invoice-dollar"></i> Progress Billing</a>
        </div>

        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 mb-4">
            <?php foreach ([
                ['punch', 'Punch list open', 'fa-list-check', 'bg-orange-50 text-orange-600'],
                ['ready', 'Ready for turnover', 'fa-circle-check', 'bg-amber-50 text-amber-700'],
                ['pending_sa', 'For Superadmin', 'fa-signature', 'bg-sky-50 text-sky-600'],
                ['approved', 'Get client signature', 'fa-user-pen', 'bg-violet-50 text-violet-600'],
                ['closed', 'Closed', 'fa-flag-checkered', 'bg-emerald-50 text-emerald-600'],
                ['all', 'All projects', 'fa-layer-group', 'bg-gray-100 text-gray-600'],
            ] as [$k, $label, $icon, $cls]): ?>
                <button type="button" data-tab="<?= $k ?>"
                    class="to-tab text-left bg-white border border-gray-200 rounded-xl p-3.5 flex items-center gap-3 shadow-sm min-w-0 transition hover:border-amber-400 data-[active=true]:border-amber-600 data-[active=true]:ring-2 data-[active=true]:ring-amber-100 data-[active=true]:bg-amber-50/40">
                    <span class="hidden sm:flex w-9 h-9 rounded-lg items-center justify-center shrink-0 <?= $cls ?>"><i class="fa-solid <?= $icon ?>"></i></span>
                    <span class="min-w-0"><span class="block text-[10.5px] font-semibold text-gray-500 uppercase tracking-wide truncate"><?= $label ?></span>
                        <span class="block text-2xl font-bold text-gray-900 leading-tight tabular-nums" data-count="<?= $k ?>">—</span></span>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100"><div class="relative"><i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                <input type="text" id="toSearch" placeholder="Search project, owner, DR no.…" class="w-full h-9 bg-white border border-gray-200 rounded-lg pl-9 pr-3 text-xs outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100"></div></div>
            <div id="toInfo" class="px-4 py-2 text-[11px] text-gray-500 bg-gray-50 border-b border-gray-100">&nbsp;</div>
            <div id="toList" class="divide-y divide-gray-100"><div class="px-6 py-14 text-center text-xs text-gray-400"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Loading…</div></div>
        </div>
        <div id="bpToast" class="fixed bottom-4 right-4 z-[10002] flex flex-col gap-2.5 pointer-events-none w-[340px] max-w-[calc(100vw-2rem)]"></div>
    </main>

    <div id="toModal" class="hidden fixed inset-0 z-[9999] items-start justify-center bg-black/50 p-2 md:p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-7xl my-auto overflow-hidden">
            <div class="dr-hazard h-1.5"></div>
            <div class="flex items-start justify-between gap-3 px-5 py-4 border-b border-gray-100">
                <div class="min-w-0"><p class="text-[10px] font-bold uppercase tracking-[0.15em] text-amber-700">Punch List &amp; Turnover</p>
                    <p id="tmTitle" class="text-base font-semibold text-gray-900 truncate"></p><p id="tmSub" class="text-[11px] text-gray-400 truncate"></p></div>
                <button type="button" id="tmClose" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 shrink-0" aria-label="Close"><i class="fa-solid fa-xmark text-base leading-none"></i></button>
            </div>
            <!-- readiness -->
            <div id="tmReady" class="grid md:grid-cols-3 gap-2 px-5 pt-4"></div>
            <!-- tabs -->
            <div class="flex items-center gap-1 px-5 pt-4 border-b border-gray-100">
                <button type="button" data-mtab="punch" class="tm-tab px-4 py-2 text-xs font-semibold border-b-2 -mb-px">Step 7 · Punch list <span id="tmPunchCount" class="ml-1 text-gray-400"></span></button>
                <button type="button" data-mtab="turnover" class="tm-tab px-4 py-2 text-xs font-semibold border-b-2 -mb-px">Step 8 · Turnover <span id="tmToChip" class="ml-1"></span></button>
            </div>
            <div id="tmPunch" class="p-5"></div>
            <div id="tmTurnover" class="hidden p-5"></div>
        </div>
    </div>

    <script>
        (function () {
            'use strict';
            const AJAX = <?= json_encode($toAjaxUrl) ?>, AJAX_NAME = 'coordinatorturnoverajax', MEMO = 'nh-to-';
            const LOGO = <?= json_encode(BASE_URL . '/icon/logo.webp') ?>;
            const F = id => document.getElementById(id);
            let rows = [], tab = 'punch', today = '', lastSig = '';
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

            async function apiForm(action, fd) {
                const ctrl = new AbortController(), timer = setTimeout(() => ctrl.abort(), 60000);
                try {
                    const res = await fetch(`${AJAX}?action=${action}`, { method: 'POST', body: fd, signal: ctrl.signal, cache: 'no-store' });
                    const text = await res.text(); let d;
                    try { d = JSON.parse(text); } catch (e) { throw new Error(res.status === 413 ? 'The file is too large for the server.' : 'The server sent an unexpected response.'); }
                    if (!d.success) { const er = new Error(d.message || 'Something went wrong.'); er.field = d.field; throw er; }
                    return d;
                } catch (e) { if (e.name === 'AbortError') throw new Error('The upload took too long.'); throw e; } finally { clearTimeout(timer); }
            }

            // ═════════════ LIST ═════════════
            const stageOf = r => r.to_status === 'closed' ? 'closed' : r.to_status === 'approved' ? 'approved' : r.to_status === 'pending_sa' ? 'pending_sa'
                : r.to_status === 'returned' ? 'returned' : r.ready ? 'ready' : 'punch';
            function render() {
                const inTab = (r, k) => k === 'all' ? true : k === 'punch' ? ['punch', 'returned'].includes(stageOf(r)) : stageOf(r) === k;
                document.querySelectorAll('.to-tab').forEach(b => b.dataset.active = String(b.dataset.tab === tab));
                document.querySelectorAll('[data-count]').forEach(el => el.textContent = rows.filter(r => inTab(r, el.dataset.count)).length);
                const q = F('toSearch').value.trim().toLowerCase();
                const list = rows.filter(r => inTab(r, tab) && (!q || `${r.project_name ?? ''} ${r.owner ?? ''} ${r.dr_no} ${r.location ?? ''} ${r.subcontractor ?? ''}`.toLowerCase().includes(q)));
                F('toInfo').innerHTML = `<b class="text-gray-700">${list.length}</b> project${list.length === 1 ? '' : 's'}${q ? ' (filtered)' : ''}`;
                if (!list.length) {
                    F('toList').innerHTML = `<div class="px-6 py-14 text-center"><div class="w-12 h-12 mx-auto rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center mb-3"><i class="fa-solid fa-key"></i></div><p class="text-sm font-semibold text-gray-700">${q ? 'No match.' : 'Nothing here.'}</p></div>`;
                    return;
                }
                const BAR = { punch: 'bg-orange-400', ready: 'bg-amber-500', pending_sa: 'bg-sky-500', approved: 'bg-violet-500', returned: 'bg-red-500', closed: 'bg-emerald-600' };
                F('toList').innerHTML = list.map(r => {
                    const st = stageOf(r);
                    const chip = r.to_status ? toChip(r.to_status) : st === 'ready' ? '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold ring-1 bg-amber-50 text-amber-700 ring-amber-600/20"><i class="fa-solid fa-circle-check text-[9px]"></i>Ready for turnover</span>'
                        : '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold ring-1 bg-orange-50 text-orange-700 ring-orange-600/20"><i class="fa-solid fa-list-check text-[9px]"></i>Not ready</span>';
                    const btn = { punch: ['Open punch list', 'fa-list-check'], ready: ['Prepare turnover', 'fa-file-pen'], returned: ['Fix letter', 'fa-pen'], pending_sa: ['View', 'fa-eye'], approved: ['Complete turnover', 'fa-user-pen'], closed: ['View', 'fa-eye'] }[st];
                    return `<div data-row="${r.dr_id}" class="to-row relative flex flex-wrap items-center gap-x-5 gap-y-2 pl-5 pr-4 py-3.5">
                        <span class="absolute left-0 top-0 bottom-0 w-1 ${BAR[st]}"></span>
                        <div class="min-w-0 flex-1 basis-[260px]">
                            <div class="flex flex-wrap items-center gap-2"><span class="text-sm font-bold text-gray-900 truncate">${esc(r.project_name || r.owner)}</span>${chip}</div>
                            <p class="text-[11px] text-gray-400 truncate mt-0.5"><span class="font-mono">DR# ${esc(r.dr_no)}</span> · ${esc(r.subcontractor || '')}${r.location ? ' · ' + esc(r.location) : ''}</p>
                            ${r.to_status === 'returned' && r.to_remarks ? `<p class="mt-1.5 text-[11px] text-red-700 bg-red-50 border border-red-100 rounded-lg px-2.5 py-1.5"><i class="fa-solid fa-comment-dots mr-1"></i>${esc(r.to_remarks)}</p>` : ''}
                        </div>
                        <dl class="grid grid-cols-3 gap-x-5 text-[11px] basis-[300px] grow sm:grow-0">
                            <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Punch list</dt><dd class="${r.punch_total && r.punch_verified < r.punch_total ? 'text-orange-700 font-semibold' : 'text-gray-700'}">${r.punch_total ? `${r.punch_verified}/${r.punch_total} verified` : 'No items'}</dd></div>
                            <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Overdue</dt><dd class="${r.punch_overdue ? 'text-red-600 font-bold' : 'text-gray-700'}">${r.punch_overdue}</dd></div>
                            <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Billing</dt><dd class="${r.billing_ok ? 'text-emerald-700' : 'text-gray-500'}">${r.billing_ok ? 'Complete' : 'Not complete'}</dd></div>
                        </dl>
                        <div class="ml-auto shrink-0"><button type="button" data-open="${r.dr_id}" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg ${['ready', 'returned', 'approved'].includes(st) ? 'text-white bg-amber-700 hover:bg-amber-800 shadow-sm' : 'text-gray-700 bg-white border border-gray-200 hover:bg-gray-50'}"><i class="fa-solid ${btn[1]}"></i> ${btn[0]}</button></div>
                    </div>`;
                }).join('');
                let id = null; try { id = sessionStorage.getItem(MEMO + 'picked'); } catch (e) { }
                document.querySelectorAll('.to-row').forEach(el => el.setAttribute('data-picked', el.dataset.row === id ? '1' : '0'));
            }
            document.querySelectorAll('.to-tab').forEach(b => b.addEventListener('click', () => { tab = b.dataset.tab; memo.set('tab', tab); render(); }));
            F('toSearch').addEventListener('input', render);
            F('toList').addEventListener('click', e => { const b = e.target.closest('[data-open]'); if (!b) return; try { sessionStorage.setItem(MEMO + 'picked', b.dataset.open); } catch (er) { } render(); openProject(+b.dataset.open); });
            async function load(silent) {
                try {
                    const d = await api('list'); today = d.today;
                    const sig = JSON.stringify(d.rows.map(r => [r.dr_id, r.to_status, r.punch_total, r.punch_verified, r.ready]));
                    if (silent && sig === lastSig) return;
                    if (silent && lastSig) {
                        const prev = new Map(rows.map(r => [r.dr_id, r.to_status]));
                        d.rows.forEach(r => {
                            if (prev.get(r.dr_id) === 'pending_sa' && r.to_status === 'approved') toast(`The turnover letter of ${r.project_name || r.owner} was signed. Print it for the client.`);
                            if (prev.get(r.dr_id) === 'pending_sa' && r.to_status === 'returned') toast(`The turnover letter of ${r.project_name || r.owner} was returned.`, 'error', 6000);
                        });
                    }
                    lastSig = sig; rows = d.rows; render();
                } catch (e) {
                    if (silent) return;
                    F('toList').innerHTML = `<div class="px-6 py-12 text-center text-xs text-red-600">${esc(e.message)}<br><button type="button" id="toRetry" class="mt-2 font-semibold text-amber-700 hover:underline">Retry</button></div>`;
                    F('toRetry').addEventListener('click', () => load());
                }
            }

            // ═════════════ PROJECT MODAL ═════════════
            let pj = null;   // { project, punch, turnover, readiness, suggest, me, mtab, editPunch, editLetter }
            const openM = () => { F('toModal').classList.remove('hidden'); F('toModal').classList.add('flex'); };
            const closeM = () => { F('toModal').classList.add('hidden'); F('toModal').classList.remove('flex'); pj = null; load(true); };
            F('tmClose').addEventListener('click', closeM);
            F('toModal').addEventListener('click', e => { if (e.target === F('toModal')) closeM(); });
            document.addEventListener('keydown', e => { if (e.key === 'Escape' && pj) closeM(); });
            document.querySelectorAll('.tm-tab').forEach(b => b.addEventListener('click', () => { if (pj) { pj.mtab = b.dataset.mtab; memo.set('mtab', pj.mtab); renderModal(); } }));

            async function openProject(drId, keepTab) {
                let d; try { d = await api('get', { dr_id: drId }); } catch (e) { return toast(e.message, 'error'); }
                today = d.today || today;
                const t = d.turnover;
                const defTab = t || d.readiness.filter(c => c.required).every(c => c.ok) ? 'turnover' : 'punch';
                pj = { project: d.project, punch: d.punch, turnover: t, readiness: d.readiness, suggest: d.suggest_scope, me: d.me,
                    mtab: keepTab || defTab, editPunch: null, editLetter: !t || t.status === 'returned' };
                F('tmTitle').textContent = d.project.project_name || d.project.owner;
                F('tmSub').textContent = `DR# ${d.project.dr_no} · ${d.project.quote_no} · ${d.project.subcontractor || ''}${d.project.location ? ' · ' + d.project.location : ''}`;
                renderModal();
                openM();
            }
            const locked = () => pj.turnover && ['approved', 'closed'].includes(pj.turnover.status);
            const readyOk = () => pj.readiness.filter(c => c.required).every(c => c.ok);
            function renderModal() {
                F('tmReady').innerHTML = pj.readiness.map(c => `<div class="flex items-start gap-2.5 rounded-xl border px-3 py-2.5 ${c.ok ? 'border-emerald-200 bg-emerald-50/60' : c.required ? 'border-orange-200 bg-orange-50/60' : 'border-gray-200 bg-gray-50'}">
                    <span class="w-6 h-6 rounded-full flex items-center justify-center shrink-0 text-[11px] ${c.ok ? 'bg-emerald-600 text-white' : c.required ? 'bg-orange-500 text-white' : 'bg-gray-300 text-white'}"><i class="fa-solid ${c.ok ? 'fa-check' : c.required ? 'fa-xmark' : 'fa-minus'}"></i></span>
                    <span class="min-w-0"><span class="block text-xs font-semibold text-gray-900">${esc(c.label)}${c.required ? '' : ' <span class="font-normal text-gray-400">(optional)</span>'}</span><span class="block text-[10.5px] text-gray-500">${esc(c.note)}</span></span></div>`).join('');
                document.querySelectorAll('.tm-tab').forEach(b => { const on = b.dataset.mtab === pj.mtab; b.classList.toggle('border-amber-600', on); b.classList.toggle('text-amber-800', on); b.classList.toggle('border-transparent', !on); b.classList.toggle('text-gray-500', !on); });
                const open = pj.punch.filter(x => x.status !== 'verified').length;
                F('tmPunchCount').textContent = pj.punch.length ? `${pj.punch.length - open}/${pj.punch.length}` : '';
                F('tmToChip').innerHTML = pj.turnover ? toChip(pj.turnover.status) : '';
                F('tmPunch').classList.toggle('hidden', pj.mtab !== 'punch');
                F('tmTurnover').classList.toggle('hidden', pj.mtab !== 'turnover');
                if (pj.mtab === 'punch') renderPunch(); else renderTurnover();
            }
            async function refresh(tabName) { const id = pj.project.dr_id; await openProject(id, tabName || pj.mtab); load(true); }

            // ── STEP 7: PUNCH LIST ──
            const inp = 'w-full border border-gray-200 rounded-lg px-2.5 py-2 text-xs outline-none focus:border-amber-600 bg-white';
            const lbl = (t, req) => `<span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">${t}${req ? ' <span class="text-red-500">*</span>' : ''}</span>`;
            const P_ST = { open: ['Open', 'bg-orange-50 text-orange-700 ring-orange-600/20', 'fa-circle-exclamation'], fixed: ['Fixed · for re-inspection', 'bg-sky-50 text-sky-700 ring-sky-600/20', 'fa-screwdriver-wrench'], verified: ['Verified', 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', 'fa-circle-check'] };
            let pFilter = 'all';
            function renderPunch() {
                const items = pj.punch, lk = locked();
                const cnt = k => items.filter(x => k === 'all' ? true : k === 'overdue' ? x.status !== 'verified' && x.target_date && x.target_date < today : x.status === k).length;
                const shown = items.filter(x => pFilter === 'all' ? true : pFilter === 'overdue' ? x.status !== 'verified' && x.target_date && x.target_date < today : x.status === pFilter);
                const v = pj.editPunch || null;
                F('tmPunch').innerHTML = `
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        ${[['all', 'All'], ['open', 'Open'], ['fixed', 'Fixed'], ['verified', 'Verified'], ['overdue', 'Overdue']].map(([k, t]) => `<button type="button" data-pf="${k}" class="px-3 py-1.5 rounded-lg text-[11px] font-semibold border ${pFilter === k ? 'bg-gray-900 text-white border-gray-900' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'} ${k === 'overdue' && cnt('overdue') ? '!text-red-600' : ''}">${t} <span class="opacity-60">${cnt(k)}</span></button>`).join('')}
                        ${lk ? '<span class="ml-auto text-[11px] text-gray-400"><i class="fa-solid fa-lock mr-1"></i>Locked (turnover letter signed)</span>' : `<button type="button" id="pAdd" class="ml-auto inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800"><i class="fa-solid fa-plus"></i> Add item</button>`}
                    </div>
                    ${v ? `<form id="pForm" class="mb-4 rounded-xl border-2 border-amber-200 bg-amber-50/30 p-4" novalidate>
                        <p class="text-xs font-bold text-gray-900 mb-3"><i class="fa-solid ${v.id ? 'fa-pen' : 'fa-plus'} mr-1 text-amber-700"></i>${v.id ? 'Edit item' : 'New punch item'}</p>
                        <div class="grid grid-cols-2 md:grid-cols-6 gap-3">
                            <label class="col-span-2">${lbl('Area / Location', 1)}<input name="area" value="${esc(v.area || '')}" maxlength="150" list="pAreas" placeholder="e.g. Bedroom" class="${inp}"><span data-err="area" class="hidden block text-[11px] text-red-600 mt-1"></span></label>
                            <label class="col-span-2">${lbl('Responsible')}<input name="responsible" value="${esc(v.responsible || '')}" maxlength="150" placeholder="e.g. ${esc(pj.project.subcontractor || 'Subcon')}" class="${inp}"></label>
                            <label class="col-span-2">${lbl('Target correction date')}<input name="target_date" type="date" value="${esc(v.target_date || '')}" class="${inp}"></label>
                            <label class="col-span-2 md:col-span-4">${lbl('Deficiency', 1)}<textarea name="deficiency" rows="2" maxlength="1000" placeholder="What is wrong or missing? e.g. Wardrobe door is misaligned." class="${inp} resize-y">${esc(v.deficiency || '')}</textarea><span data-err="deficiency" class="hidden block text-[11px] text-red-600 mt-1"></span></label>
                            <label class="col-span-2">${lbl('Remarks')}<textarea name="remarks" rows="2" maxlength="1000" class="${inp} resize-y">${esc(v.remarks || '')}</textarea></label>
                        </div>
                        <datalist id="pAreas">${[...new Set(items.map(x => x.area))].map(a => `<option>${esc(a)}`).join('')}<option>Bedroom<option>Kitchen<option>Living room<option>Toilet</datalist>
                        <div class="flex items-center justify-end gap-2 mt-3"><button type="button" id="pCancel" class="px-3.5 py-2 text-xs font-semibold text-gray-500 bg-white border border-gray-200 rounded-lg hover:bg-gray-100">Cancel</button>
                            <button type="submit" id="pSave" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:opacity-60"><i class="fa-solid fa-floppy-disk"></i> Save item</button></div>
                    </form>` : ''}
                    <div class="space-y-2">${shown.length ? shown.map(x => {
                        const s = P_ST[x.status], over = x.status !== 'verified' && x.target_date && x.target_date < today;
                        return `<div data-pid="${x.id}" class="relative rounded-xl border ${over ? 'border-red-200 bg-red-50/40' : 'border-gray-200 bg-white'} pl-4 pr-3 py-3 flex flex-wrap items-start gap-x-4 gap-y-2">
                            <span class="absolute left-0 top-0 bottom-0 w-1 rounded-l-xl ${x.status === 'verified' ? 'bg-emerald-500' : x.status === 'fixed' ? 'bg-sky-500' : over ? 'bg-red-500' : 'bg-orange-400'}"></span>
                            <div class="min-w-0 flex-1 basis-[280px]">
                                <div class="flex flex-wrap items-center gap-2"><span class="text-[10px] font-bold uppercase tracking-wider text-gray-500">${esc(x.area)}</span>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold ring-1 ${s[1]}"><i class="fa-solid ${s[2]} text-[9px]"></i>${s[0]}</span>
                                    ${over ? '<span class="text-[10px] font-bold text-red-600"><i class="fa-solid fa-clock mr-0.5"></i>Overdue</span>' : ''}</div>
                                <p class="text-sm text-gray-900 mt-0.5 ${x.status === 'verified' ? 'line-through decoration-gray-300 text-gray-500' : ''}">${esc(x.deficiency)}</p>
                                <p class="text-[10.5px] text-gray-400 mt-0.5">${x.responsible ? `<i class="fa-solid fa-user mr-0.5"></i>${esc(x.responsible)} · ` : ''}${x.target_date ? `Target ${fmtY(x.target_date)}` : 'No target date'}${x.fixed_at ? ` · Fixed ${fmtY(x.fixed_at)}` : ''}${x.verified_at ? ` · Verified ${fmtY(x.verified_at)} by ${esc(x.verified_by || '')}` : ''}${x.remarks ? ` · ${esc(x.remarks)}` : ''}</p>
                            </div>
                            ${lk ? '' : `<div class="flex items-center gap-1.5 shrink-0 p-act">
                                ${x.status === 'open' ? `<button type="button" data-st="fixed" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[11px] font-semibold text-white bg-sky-600 rounded-lg hover:bg-sky-700"><i class="fa-solid fa-screwdriver-wrench"></i> Mark fixed</button>` : ''}
                                ${x.status === 'fixed' ? `<button type="button" data-st="verified" class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[11px] font-semibold text-white bg-emerald-600 rounded-lg hover:bg-emerald-700"><i class="fa-solid fa-check-double"></i> Verify</button>` : ''}
                                ${x.status !== 'open' ? `<button type="button" data-st="open" class="px-2.5 py-1.5 text-[11px] font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50" title="Not OK yet — open again"><i class="fa-solid fa-rotate-left"></i> Reopen</button>` : ''}
                                <button type="button" data-pedit="1" class="w-7 h-7 rounded-md text-gray-400 hover:text-gray-700 hover:bg-gray-100" title="Edit"><i class="fa-solid fa-pen text-[11px]"></i></button>
                                <button type="button" data-pdel="1" class="w-7 h-7 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50" title="Remove"><i class="fa-solid fa-trash-can text-[11px]"></i></button></div>`}
                        </div>`;
                    }).join('') : `<div class="rounded-xl border-2 border-dashed border-gray-200 px-6 py-10 text-center"><i class="fa-solid fa-clipboard-check text-2xl text-gray-300 mb-2"></i><p class="text-sm font-semibold text-gray-700">${items.length ? 'No item in this filter.' : 'No punch item yet.'}</p><p class="text-xs text-gray-400 mt-1">${items.length ? '' : 'Inspect the work against the approved drawings, then add any defect. If nothing is wrong, you can go to the turnover.'}</p></div>`}</div>`;
                if (v) F('pForm').querySelector('[name=area]').focus();
            }
            F('tmPunch').addEventListener('click', async e => {
                if (!pj) return;
                const pf = e.target.closest('[data-pf]'); if (pf) { pFilter = pf.dataset.pf; return renderPunch(); }
                if (e.target.closest('#pAdd')) { pj.editPunch = {}; return renderPunch(); }
                if (e.target.closest('#pCancel')) { pj.editPunch = null; return renderPunch(); }
                const card = e.target.closest('[data-pid]'); if (!card) return;
                const id = +card.dataset.pid, it = pj.punch.find(x => x.id === id);
                if (e.target.closest('[data-pedit]')) { pj.editPunch = { ...it }; renderPunch(); F('tmPunch').scrollIntoView({ block: 'nearest' }); return; }
                const del = e.target.closest('[data-pdel]');
                if (del) {
                    card.querySelector('.p-act').innerHTML = `<span class="text-[11px] text-red-700 font-semibold">Remove this item?</span><button type="button" data-pdelyes="1" class="px-2.5 py-1.5 text-[11px] font-semibold text-white bg-red-600 rounded-lg">Remove</button><button type="button" data-pkeep="1" class="px-2.5 py-1.5 text-[11px] font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg">Keep</button>`;
                    return;
                }
                if (e.target.closest('[data-pkeep]')) return renderPunch();
                if (e.target.closest('[data-pdelyes]')) { try { await api('punch_delete', { id }, true); toast('Item removed.'); refresh('punch'); } catch (er) { toast(er.message, 'error'); } return; }
                const st = e.target.closest('[data-st]');
                if (st) { st.disabled = true; try { await api('punch_status', { id, status: st.dataset.st }, true); toast(st.dataset.st === 'verified' ? 'Item verified.' : st.dataset.st === 'fixed' ? 'Marked as fixed. Verify it after re-inspection.' : 'Item opened again.'); refresh('punch'); } catch (er) { toast(er.message, 'error'); st.disabled = false; } }
            });
            F('tmPunch').addEventListener('input', e => { const el = F('pForm')?.querySelector(`[data-err="${e.target.name}"]`); if (el) { el.classList.add('hidden'); e.target.classList.remove('border-red-400'); } });
            F('tmPunch').addEventListener('submit', async e => {
                e.preventDefault();
                const f = F('pForm'), v = Object.fromEntries(['area', 'responsible', 'target_date', 'deficiency', 'remarks'].map(n => [n, f.elements[n].value.trim()]));
                const ferr = (n, m) => { const el = f.querySelector(`[data-err="${n}"]`); el.textContent = m; el.classList.remove('hidden'); f.elements[n].classList.add('border-red-400'); f.elements[n].focus(); };
                if (!v.area) return ferr('area', 'Enter the area or location.');
                if (!v.deficiency) return ferr('deficiency', 'Describe the deficiency.');
                F('pSave').disabled = true;
                try { await api('punch_save', { dr_id: pj.project.dr_id, id: pj.editPunch.id || '', ...v }, true); toast(pj.editPunch.id ? 'Item updated.' : 'Item added.'); refresh('punch'); }
                catch (er) { if (er.field) ferr(er.field, er.message); else toast(er.message, 'error'); F('pSave').disabled = false; }
            });

            // ── STEP 8: TURNOVER ──
            const letterKey = () => 'letter-' + pj.project.dr_id;
            function letterDefaults() {
                const t = pj.turnover, p = pj.project;
                if (t) return { letter_date: t.letter_date, project: t.project, location: t.location || '', contractor: t.contractor || '', client_name: t.client_name, client_title: t.client_title || 'Owner',
                    signed_by: t.signed_by || 'Mr. Ken Yang', signed_title: t.signed_title || 'General Manager', documents: t.documents || '', scope: t.scope.map(s => ({ scope: s.scope, area: s.area || '', status: s.status || 'Accomplished' })) };
                return { letter_date: today, project: '', location: p.location || p.address || '', contractor: 'Noblehome Construction Company', client_name: p.project_name || p.owner || '', client_title: 'Owner',
                    signed_by: 'Mr. Ken Yang', signed_title: 'General Manager', documents: '', scope: (pj.suggest.length ? pj.suggest : ['']).map(s => ({ scope: s, area: '', status: 'Accomplished' })) };
            }
            function readLetter() {
                const f = F('lForm');
                if (!f) return null;
                return { ...Object.fromEntries(['letter_date', 'project', 'location', 'contractor', 'client_name', 'client_title', 'signed_by', 'signed_title', 'documents'].map(n => [n, f.elements[n].value.trim()])),
                    scope: [...f.querySelectorAll('.l-row')].map(r => ({ scope: r.querySelector('[name=scope]').value.trim(), area: r.querySelector('[name=area]').value.trim(), status: r.querySelector('[name=sstatus]').value.trim() || 'Accomplished' })) };
            }
            function renderTurnover() {
                const t = pj.turnover, box = F('tmTurnover');
                if (!pj.editLetter && t) return renderLetterView();
                if (!readyOk()) {
                    box.innerHTML = `<div class="rounded-xl border-2 border-dashed border-orange-200 bg-orange-50/40 px-6 py-10 text-center"><i class="fa-solid fa-lock text-2xl text-orange-300 mb-2"></i><p class="text-sm font-semibold text-gray-800">Not ready for turnover yet</p>
                        <p class="text-xs text-gray-500 mt-1">Finish the items in the checklist above first.</p>
                        <ul class="inline-block text-left text-xs text-gray-600 mt-3 space-y-1">${pj.readiness.filter(c => c.required && !c.ok).map(c => `<li><i class="fa-solid fa-xmark text-orange-500 mr-1"></i>${esc(c.label)} — ${esc(c.note)}</li>`).join('')}</ul></div>`;
                    return;
                }
                let v = null; try { v = JSON.parse(memo.get(letterKey()) || 'null'); } catch (e) { }
                const restored = !!v; v = v || letterDefaults();
                const ret = t && t.status === 'returned';
                const scopeRow = (s = {}) => `<tr class="l-row border-t border-gray-100"><td class="p-1.5"><input name="scope" value="${esc(s.scope || '')}" maxlength="500" placeholder="e.g. CLOSET (WARDROBE, INNER CABINET)" class="${inp}"></td>
                    <td class="p-1.5"><input name="area" value="${esc(s.area || '')}" maxlength="150" placeholder="e.g. BEDROOM" list="lAreas" class="${inp}"></td>
                    <td class="p-1.5"><input name="sstatus" value="${esc(s.status || 'Accomplished')}" maxlength="50" class="${inp}"></td>
                    <td class="p-1.5 text-center"><button type="button" class="l-del w-7 h-7 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50" title="Remove"><i class="fa-solid fa-xmark"></i></button></td></tr>`;
                box.innerHTML = `<div class="grid xl:grid-cols-[minmax(0,1fr)_minmax(0,760px)] gap-5">
                    <form id="lForm" class="space-y-3 min-w-0" novalidate>
                        ${ret ? `<div class="rounded-xl border border-red-200 bg-red-50 px-3.5 py-2.5 text-xs text-red-800"><p class="font-bold"><i class="fa-solid fa-rotate-left mr-1"></i>Returned by ${esc(t.returned_by || 'Superadmin')}</p><p class="mt-0.5">${esc(t.return_remarks || '')}</p></div>` : ''}
                        <div class="grid grid-cols-2 gap-3">
                            <label>${lbl('Letter date', 1)}<input name="letter_date" type="date" value="${esc(v.letter_date)}" class="${inp}"></label>
                            <label>${lbl('Project', 1)}<input name="project" value="${esc(v.project)}" maxlength="255" placeholder="e.g. CABINETS" class="${inp}"><span data-err="project" class="hidden block text-[11px] text-red-600 mt-1"></span></label>
                            <label>${lbl('Project location')}<input name="location" value="${esc(v.location)}" maxlength="255" class="${inp}"></label>
                            <label>${lbl('Contractor')}<input name="contractor" value="${esc(v.contractor)}" maxlength="255" class="${inp}"></label>
                            <label>${lbl('Conforme (client)', 1)}<input name="client_name" value="${esc(v.client_name)}" maxlength="255" placeholder="e.g. Ms. Sunny Ong" class="${inp}"><span data-err="client_name" class="hidden block text-[11px] text-red-600 mt-1"></span></label>
                            <label>${lbl('Client title')}<input name="client_title" value="${esc(v.client_title)}" maxlength="100" list="lTitles" class="${inp}"></label>
                            <label>${lbl('Signed by')}<input name="signed_by" value="${esc(v.signed_by)}" maxlength="255" class="${inp}"></label>
                            <label>${lbl('Title')}<input name="signed_title" value="${esc(v.signed_title)}" maxlength="100" class="${inp}"></label>
                        </div>
                        <div class="rounded-xl border border-gray-200 overflow-hidden">
                            <div class="flex items-center justify-between px-3 py-2 bg-gray-50 border-b border-gray-100"><p class="text-[10px] font-bold uppercase tracking-wider text-gray-600">Completed works scope <span class="text-red-500">*</span></p>
                                ${pj.suggest.length ? '<button type="button" id="lSuggest" class="text-[10.5px] font-semibold text-amber-700 hover:underline">Fill from labor quotation</button>' : ''}</div>
                            <div class="overflow-x-auto"><table class="w-full min-w-[460px] table-fixed text-xs"><colgroup><col style="width:52%"><col style="width:22%"><col style="width:19%"><col style="width:7%"></colgroup>
                                <thead class="text-[10px] uppercase tracking-wider text-gray-500"><tr><th class="px-2 py-1.5 text-left font-semibold">Scope</th><th class="px-2 py-1.5 text-left font-semibold">Area</th><th class="px-2 py-1.5 text-left font-semibold">Status</th><th></th></tr></thead>
                                <tbody id="lScope">${v.scope.map(scopeRow).join('')}</tbody></table></div>
                            <div class="px-3 py-2 border-t border-gray-100"><button type="button" id="lAdd" class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-700 hover:underline"><i class="fa-solid fa-plus text-[10px]"></i> Add work</button></div>
                        </div>
                        <label class="block">${lbl('Turnover documents (one per line)')}<textarea name="documents" rows="3" maxlength="2000" placeholder="e.g. Warranty certificate&#10;Care and maintenance manual&#10;As-built drawings" class="${inp} resize-y">${esc(v.documents)}</textarea></label>
                        <datalist id="lAreas"><option>BEDROOM<option>STUDY<option>TOILET<option>KITCHEN<option>LIVING ROOM</datalist><datalist id="lTitles"><option>Owner<option>Building Admin<option>Representative</datalist>
                        <p id="lErr" class="hidden rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-[11px] text-red-700"></p>
                        <div class="flex items-center justify-between gap-2">
                            <p id="lNote" class="text-[10.5px] text-gray-400">${restored ? 'Restored your unsaved draft.' : 'Goes to the Superadmin for signing.'}</p>
                            <div class="flex items-center gap-2">${t && !ret ? '<button type="button" id="lCancel" class="px-3.5 py-2 text-xs font-semibold text-gray-500 bg-white border border-gray-200 rounded-lg hover:bg-gray-100">Cancel</button>' : ''}
                                <button type="submit" id="lSave" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:opacity-60"><i class="fa-solid fa-paper-plane"></i> <span>${t ? 'Save & resubmit' : 'Submit for signing'}</span></button></div>
                        </div>
                    </form>
                    <div class="min-w-0"><p class="text-[11px] font-bold uppercase tracking-wider text-gray-500 mb-2"><i class="fa-solid fa-eye mr-1 text-amber-700"></i>Preview</p>
                        <div class="rounded-xl border border-gray-200 bg-slate-50 p-3"><div id="lPrev" class="bg-white rounded-lg border border-gray-200 p-4 overflow-hidden tdwm"></div></div></div>
                </div>`;
                previewLetter();
            }
            function previewLetter() {
                const v = readLetter(); if (!v) return;
                F('lPrev').innerHTML = toLetter({ ...v, status: 'draft', scope: v.scope.filter(s => s.scope) }, { signHere: true });
                fitDoc(F('lPrev'));
            }
            function fitDoc(box) { const d = box.querySelector('.todoc'); if (!d) return; const w = box.clientWidth - 34; if (w <= 0) return requestAnimationFrame(() => fitDoc(box)); d.style.zoom = String(Math.max(.35, Math.min(1, w / 740))); }
            window.addEventListener('resize', () => { const b = F('lPrev') || F('lView'); if (b) fitDoc(b); });
            let lT = null;
            F('tmTurnover').addEventListener('input', e => {
                if (!F('lForm') || !F('lForm').contains(e.target)) return;
                const el = F('lForm').querySelector(`[data-err="${e.target.name}"]`); if (el) { el.classList.add('hidden'); e.target.classList.remove('border-red-400'); }
                F('lErr').classList.add('hidden');
                previewLetter();
                clearTimeout(lT); lT = setTimeout(() => { if (pj && F('lForm')) { memo.set(letterKey(), JSON.stringify(readLetter())); F('lNote').textContent = 'Draft saved on this device.'; } }, 400);
            });
            F('tmTurnover').addEventListener('click', async e => {
                if (!pj) return;
                const tb = F('lScope');
                if (e.target.closest('#lAdd')) { tb.insertAdjacentHTML('beforeend', `<tr class="l-row border-t border-gray-100"><td class="p-1.5"><input name="scope" maxlength="500" class="${inp}"></td><td class="p-1.5"><input name="area" maxlength="150" list="lAreas" class="${inp}"></td><td class="p-1.5"><input name="sstatus" value="Accomplished" maxlength="50" class="${inp}"></td><td class="p-1.5 text-center"><button type="button" class="l-del w-7 h-7 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50"><i class="fa-solid fa-xmark"></i></button></td></tr>`); tb.lastElementChild.querySelector('input').focus(); previewLetter(); }
                const d = e.target.closest('.l-del'); if (d) { if (tb.rows.length > 1) d.closest('tr').remove(); else d.closest('tr').querySelectorAll('input').forEach(i => i.value = ''); previewLetter(); }
                if (e.target.closest('#lSuggest')) {
                    const have = [...tb.querySelectorAll('[name=scope]')].map(i => i.value.trim()).filter(Boolean);
                    pj.suggest.filter(s => !have.includes(s)).forEach(s => tb.insertAdjacentHTML('beforeend', `<tr class="l-row border-t border-gray-100"><td class="p-1.5"><input name="scope" value="${esc(s)}" maxlength="500" class="${inp}"></td><td class="p-1.5"><input name="area" maxlength="150" list="lAreas" class="${inp}"></td><td class="p-1.5"><input name="sstatus" value="Accomplished" maxlength="50" class="${inp}"></td><td class="p-1.5 text-center"><button type="button" class="l-del w-7 h-7 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50"><i class="fa-solid fa-xmark"></i></button></td></tr>`));
                    [...tb.querySelectorAll('.l-row')].forEach(r => { if (!r.querySelector('[name=scope]').value.trim() && tb.rows.length > 1) r.remove(); });
                    previewLetter();
                }
                if (e.target.closest('#lCancel')) { memo.del(letterKey()); pj.editLetter = false; renderTurnover(); }
                if (e.target.closest('#lvEdit')) { pj.editLetter = true; renderTurnover(); }
                if (e.target.closest('#lvPrint')) { if (pj.turnover && ['approved', 'closed'].includes(pj.turnover.status)) toPrint(toLetter(pj.turnover)); }
            });
            F('tmTurnover').addEventListener('submit', async e => {
                e.preventDefault();
                if (e.target.id === 'cForm') return completeTurnover(e.target);
                const v = readLetter(), f = F('lForm');
                const ferr = (n, m) => { const el = f.querySelector(`[data-err="${n}"]`); if (el) { el.textContent = m; el.classList.remove('hidden'); } f.elements[n].classList.add('border-red-400'); f.elements[n].focus(); };
                if (!v.project) return ferr('project', 'Enter the project (e.g. CABINETS).');
                if (!v.client_name) return ferr('client_name', 'Enter the client who will sign.');
                const scope = v.scope.filter(s => s.scope);
                if (!scope.length) { F('lErr').textContent = 'Add at least one completed work.'; F('lErr').classList.remove('hidden'); return; }
                const qs = new URLSearchParams(); qs.append('dr_id', pj.project.dr_id);
                ['letter_date', 'project', 'location', 'contractor', 'client_name', 'client_title', 'signed_by', 'signed_title', 'documents'].forEach(n => qs.append(n, v[n]));
                scope.forEach(s => { qs.append('scope[]', s.scope); qs.append('area[]', s.area); qs.append('sstatus[]', s.status); });
                const btn = F('lSave'); btn.disabled = true;
                try { clearTimeout(lT); await apiForm('turnover_save', qs); memo.del(letterKey()); toast('Turnover letter sent to the Superadmin for signing.'); refresh('turnover'); }
                catch (er) { if (er.field) ferr(er.field, er.message); else { F('lErr').textContent = er.message; F('lErr').classList.remove('hidden'); } btn.disabled = false; }
            });

            function renderLetterView() {
                const t = pj.turnover, box = F('tmTurnover');
                const banner = {
                    pending_sa: ['border-sky-200 bg-sky-50 text-sky-800', 'fa-hourglass-half', 'Waiting for the Superadmin to sign the letter. Printing unlocks once it is signed. You can still edit it while waiting.'],
                    approved: ['border-violet-200 bg-violet-50 text-violet-800', 'fa-user-pen', `Signed by ${esc(t.sa_by || '')} on ${fmtY(t.sa_at)}. Print it, have the client sign it, then complete the turnover below.`],
                    closed: ['border-emerald-200 bg-emerald-50 text-emerald-800', 'fa-flag-checkered', `Project closed. Accepted by the client on ${fmtY(t.accepted_date)} · closed by ${esc(t.closed_by || '')}.`],
                }[t.status];
                box.innerHTML = `<div class="grid xl:grid-cols-[minmax(0,1fr)_minmax(0,760px)] gap-5">
                    <div class="space-y-3 min-w-0">
                        <div class="rounded-xl border px-4 py-3 text-xs ${banner[0]}"><i class="fa-solid ${banner[1]} mr-1"></i>${banner[2]}</div>
                        <div class="flex flex-wrap gap-2">
                            ${t.status === 'pending_sa' ? '<button type="button" id="lvEdit" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-amber-800 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100"><i class="fa-solid fa-pen"></i> Edit letter</button>' : ''}
                            <button type="button" id="lvPrint" ${['approved', 'closed'].includes(t.status) ? '' : 'disabled'} class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-gray-900 rounded-lg hover:bg-gray-800 disabled:bg-gray-200 disabled:text-gray-400 disabled:cursor-not-allowed"><i class="fa-solid fa-print"></i> Print / Save PDF</button>
                            ${t.signed_copy_url ? `<a href="${esc(t.signed_copy_url)}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50"><i class="fa-solid fa-file-signature"></i> Signed copy</a>` : ''}
                        </div>
                        ${t.status === 'approved' ? `<form id="cForm" class="rounded-xl border-2 border-violet-200 bg-violet-50/30 p-4 space-y-3" novalidate>
                            <p class="text-xs font-bold text-gray-900"><i class="fa-solid fa-flag-checkered mr-1 text-violet-700"></i>Complete turnover</p>
                            <label class="block">${lbl('Date accepted by client', 1)}<input name="accepted_date" type="date" value="${esc(today)}" max="${esc(today)}" class="${inp}"><span data-err="accepted_date" class="hidden block text-[11px] text-red-600 mt-1"></span></label>
                            <label class="flex items-start gap-2 text-xs text-gray-800 cursor-pointer"><input name="client_signed" type="checkbox" value="1" class="mt-0.5 w-4 h-4 accent-violet-600"><span><b>The client signed the turnover letter</b> (Conforme / Accepted by). <span class="text-red-500">*</span></span></label>
                            <span data-err="client_signed" class="hidden block text-[11px] text-red-600 -mt-2"></span>
                            <label class="block">${lbl('Signed copy (optional) · JPG, PNG, PDF · max 10 MB')}<input name="signed_copy" type="file" accept="image/jpeg,image/png,image/webp,application/pdf" class="block w-full text-xs text-gray-600 file:mr-2 file:px-3 file:py-1.5 file:rounded-lg file:border-0 file:bg-gray-900 file:text-white file:text-xs file:font-semibold"><span data-err="signed_copy" class="hidden block text-[11px] text-red-600 mt-1"></span></label>
                            <div id="cConfirm"><button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-violet-600 rounded-xl hover:bg-violet-700"><i class="fa-solid fa-flag-checkered"></i> Complete turnover &amp; close project</button></div>
                        </form>` : ''}
                        ${t.status === 'closed' ? `<dl class="grid grid-cols-2 gap-3 text-[11px] rounded-xl border border-gray-200 p-3">
                            <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Accepted</dt><dd class="text-gray-800">${fmtY(t.accepted_date)}</dd></div>
                            <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Closed</dt><dd class="text-gray-800">${fmtY(t.closed_at)}</dd></div>
                            <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Client</dt><dd class="text-gray-800">${esc(t.client_name)}</dd></div>
                            <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Signed by</dt><dd class="text-gray-800">${esc(t.sa_by || '')}</dd></div></dl>` : ''}
                    </div>
                    <div class="min-w-0"><div class="rounded-xl border border-gray-200 bg-slate-50 p-3"><div id="lView" class="bg-white rounded-lg border border-gray-200 p-4 overflow-hidden ${t.status === 'pending_sa' ? 'tdwm' : ''}">${toLetter(t)}</div></div></div>
                </div>`;
                fitDoc(F('lView'));
            }
            async function completeTurnover(f) {
                const ferr = (n, m) => { const el = f.querySelector(`[data-err="${n}"]`); if (el) { el.textContent = m; el.classList.remove('hidden'); } };
                f.querySelectorAll('[data-err]').forEach(el => el.classList.add('hidden'));
                const acc = f.elements.accepted_date.value;
                if (!okDate(acc)) return ferr('accepted_date', 'Enter the date the client accepted.');
                if (acc < pj.turnover.letter_date) return ferr('accepted_date', 'Cannot be before the letter date.');
                if (!f.elements.client_signed.checked) return ferr('client_signed', 'Tick this once the client has signed.');
                const file = f.elements.signed_copy.files[0];
                if (file && file.size > 10 * 1024 * 1024) return ferr('signed_copy', 'The file is too large (max 10 MB).');
                const box = F('cConfirm');
                if (!box.dataset.sure) {   // inline confirm
                    box.dataset.sure = '1';
                    box.innerHTML = `<div class="rounded-xl border border-violet-200 bg-white p-3"><p class="text-xs text-gray-800 mb-2">Close <b>${esc(pj.project.project_name || pj.project.owner)}</b>? The project will be marked <b>Closed</b> and can no longer be changed.</p>
                        <div class="flex gap-2"><button type="submit" class="flex-1 px-3 py-2 text-xs font-semibold text-white bg-violet-600 rounded-lg hover:bg-violet-700"><i class="fa-solid fa-check"></i> Yes, close the project</button><button type="button" id="cNo" class="px-3 py-2 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg">Cancel</button></div></div>`;
                    F('cNo').addEventListener('click', () => renderLetterView());
                    return;
                }
                const fd = new FormData(f); fd.append('dr_id', pj.project.dr_id); fd.set('client_signed', '1');
                if (!file) fd.delete('signed_copy');
                box.querySelectorAll('button').forEach(b => b.disabled = true);
                try { await apiForm('complete', fd); toast('Turnover complete. The project is now closed.'); refresh('turnover'); }
                catch (er) { delete box.dataset.sure; renderLetterView(); const f2 = F('cForm'); if (f2 && er.field) { const el = f2.querySelector(`[data-err="${er.field}"]`); if (el) { el.textContent = er.message; el.classList.remove('hidden'); } } else toast(er.message, 'error'); }
            }

            // ═════════════ INIT ═════════════
            tab = memo.get('tab') || 'punch';
            if (!['punch', 'ready', 'pending_sa', 'approved', 'closed', 'all'].includes(tab)) tab = 'punch';
            load().then(() => {
                const want = +new URLSearchParams(location.search).get('dr');
                if (want && rows.some(r => r.dr_id === want)) openProject(want);
                if (want) { try { const u = new URL(location.href); u.searchParams.delete('dr'); history.replaceState(null, '', u); } catch (e) { } }
            });
            setInterval(() => { if (!document.hidden && !pj) load(true); }, 30000);
        })();
    </script>
</body>

</html>