<?php
// memo-page.php — iisang page ng Memo (kasama ng memo.php at memoapproval.php)
//   $memoMode = 'hr'       → HR Assistant (Prepared by) / HR Head (Verified by)
//   $memoMode = 'approver' → Superadmin (Approved by) — kapag pinili ng HR na kailangan
if (!isset($memoMode)) $memoMode = 'hr';
$memoAjax  = BASE_URL . '/memoajax';
$memoTitle = $memoMode === 'hr' ? 'Memorandum' : 'Memo Approvals';
$memoWho   = $memoMode === 'approver' ? 'Superadmin · Approved by'
    : (($_SESSION['position'] ?? '') === POSITION_HEAD ? 'HR Head · Verified by' : 'HR Assistant · Prepared by');
$memoIntro = $memoMode === 'hr'
    ? 'Write a Notice to Explain, Notice of Decision or Certificate of Employment and send it to all or selected employees.'
    : 'Memos that HR sent for your approval. Sign to send them to the employees, or return them to HR.';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($memoTitle) ?></title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <?php include ROOT_PATH . '/admin/ui-humanresource/page-6/memo-doc.php'; ?>
    <style>
        .mm-hazard { background: repeating-linear-gradient(-45deg, #f59e0b 0 10px, #1f2937 10px 20px); }
        .mm-tab[data-active="1"] { border-color: #b45309; background: #fffbeb; box-shadow: 0 0 0 1px #b45309; }
        .mm-sel, .mm-inp { height: 36px; font-size: 12px; background: #fff; border: 1px solid #e5e7eb; border-radius: 8px; outline: none; }
        .mm-sel { padding: 0 30px 0 12px; font-weight: 500; color: #374151; }
        .mm-inp { width: 100%; padding: 0 10px; }
        textarea.mm-inp { height: auto; padding: 8px 10px; line-height: 1.5; resize: vertical; }
        .mm-sel:focus, .mm-inp:focus { border-color: #b45309; box-shadow: 0 0 0 3px #fef3c7; }
        .mm-lbl { display: block; font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #6b7280; margin-bottom: 4px; }
        .mm-type[data-on="1"], .mm-aud[data-on="1"] { border-color: #b45309; background: #fffbeb; box-shadow: 0 0 0 1px #b45309; }
        .mm-scroll { scrollbar-width: thin; scrollbar-color: #d1d5db transparent; }
    </style>
</head>

<body class="bg-slate-100">
    <main id="main-content" class="md:ml-56 min-h-screen pt-20 md:pt-8 p-4 md:p-8 overflow-x-hidden">
        <div class="max-w-6xl mx-auto">

            <!-- ═══ HEADER + TABS ═══ -->
            <section class="relative z-20 bg-white border border-gray-200 rounded-xl mb-4">
                <div class="mm-hazard h-1.5 rounded-t-xl"></div>
                <div class="p-5 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-envelope-open-text text-lg"></i></span>
                        <div class="min-w-0">
                            <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase"><?= htmlspecialchars($memoWho) ?></p>
                            <h1 class="text-gray-900 text-lg font-semibold leading-tight"><?= htmlspecialchars($memoTitle) ?></h1>
                            <p class="text-gray-400 text-[11px] mt-0.5 max-w-xl"><?= htmlspecialchars($memoIntro) ?></p>
                        </div>
                    </div>
                    <?php if ($memoMode === 'hr'): ?>
                        <button type="button" id="mmNew" class="inline-flex items-center gap-2 px-4 py-2.5 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 shadow-sm"><i class="fa-solid fa-plus"></i> New Memo</button>
                    <?php endif; ?>
                </div>
                <div class="px-5 pb-4 grid grid-cols-2 sm:grid-cols-4 gap-2">
                    <?php foreach ([['todo', $memoMode === 'hr' ? 'To do' : 'For your signature', 'text-amber-700'], ['progress', 'In progress', 'text-blue-700'], ['sent', 'Sent', 'text-emerald-700'], ['', 'All', 'text-gray-900']] as [$v, $l, $cls]): ?>
                        <button type="button" data-tab="<?= $v ?>" class="mm-tab text-left rounded-xl border border-gray-200 bg-white px-3.5 py-2 transition-colors">
                            <span class="block text-[10px] font-semibold uppercase tracking-wide text-gray-500"><?= $l ?></span>
                            <span class="mm-tab-count block text-xl font-bold leading-tight tabular-nums <?= $cls ?>">0</span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="px-5 py-3 border-t border-gray-100 bg-gray-50/60 rounded-b-xl flex flex-wrap items-center gap-2">
                    <div class="relative flex-1 min-w-[200px] max-w-sm">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[11px]"></i>
                        <input id="mmSearch" type="text" placeholder="Search memo no., subject…" class="mm-inp" style="padding-left:2rem">
                    </div>
                    <select id="mmType" class="mm-sel">
                        <option value="">All types</option>
                        <option value="nte">Notice to Explain</option>
                        <option value="nod">Notice of Decision</option>
                        <option value="coe">Certificate of Employment</option>
                    </select>
                    <input id="mmMonth" type="month" class="mm-sel" title="Month">
                    <span id="mmCount" class="ml-auto text-[11px] text-gray-400"></span>
                </div>
            </section>

            <div id="mmList" class="space-y-2.5"></div>
        </div>

        <!-- ═══ MODAL ═══ -->
        <div id="mmModal" class="hidden fixed inset-0 z-[9998] items-start justify-center bg-black/50 p-2 md:p-4 overflow-y-auto">
            <div class="bg-white rounded-2xl shadow-xl w-full max-w-7xl my-auto overflow-hidden lg:h-[calc(100vh-2rem)] lg:flex lg:flex-col">
                <div class="shrink-0 flex items-start justify-between gap-3 px-5 py-3.5 border-b border-gray-100">
                    <div class="min-w-0">
                        <p id="mmKicker" class="text-[10px] font-bold uppercase tracking-[0.15em] text-amber-700">Memo</p>
                        <p id="mmMTitle" class="text-base font-semibold text-gray-900 truncate"></p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <button type="button" id="mmPrint" class="hidden inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-gray-900 rounded-lg hover:bg-gray-800"><i class="fa-solid fa-print"></i> Print</button>
                        <button type="button" id="mmClose" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100" aria-label="Close"><i class="fa-solid fa-xmark text-base leading-none"></i></button>
                    </div>
                </div>
                <div class="grid lg:grid-cols-[minmax(0,1fr)_380px] lg:flex-1 lg:min-h-0">
                    <div class="p-3 md:p-5 bg-slate-50 min-w-0 lg:border-r border-gray-100 lg:overflow-y-auto mm-scroll">
                        <div class="overflow-x-auto mm-scroll">
                            <div id="mmDoc" class="min-w-[680px] max-w-[820px] mx-auto bg-white border border-gray-200 rounded-xl p-6 md:p-9 shadow-sm"></div>
                        </div>
                    </div>
                    <div id="mmSide" class="p-5 space-y-4 lg:overflow-y-auto mm-scroll"></div>
                </div>
            </div>
        </div>

        <div id="mmToast" class="fixed bottom-4 right-4 z-[10000] flex flex-col gap-2.5 pointer-events-none w-[340px] max-w-[calc(100vw-2rem)]"></div>
    </main>

    <script>
        (function () {
            'use strict';
            const MODE = <?= json_encode($memoMode) ?>;
            const AJAX = <?= json_encode($memoAjax) ?>;
            const SIG_PAGE = <?= json_encode(BASE_URL . '/accountingsignatured') ?>;
            const F = id => document.getElementById(id);
            const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
            const fmtDate = v => { if (!v) return ''; const d = new Date(String(v).slice(0, 10) + 'T00:00:00'); return isNaN(d) ? v : d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }); };
            const fmtDT = v => { if (!v) return ''; const d = new Date(String(v).replace(' ', 'T')); return isNaN(d) ? v : d.toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }); };
            const T = MemoDoc.TYPES, S = MemoDoc.STATUS;

            const TEMPLATES = {
                nte: { subject: 'Notice to Explain', body: 'Dear {name},\n\nIt has come to the attention of management that on [date], you [describe the incident]. This is a violation of the Company Code of Conduct, specifically [section / rule].\n\nIn view of this, you are hereby directed to submit a written explanation within five (5) days from receipt of this memo why no disciplinary action should be taken against you.\n\nPlease be guided accordingly.' },
                nod: { subject: 'Notice of Decision', body: 'Dear {name},\n\nThis refers to the Notice to Explain dated [date] regarding [incident]. After careful evaluation of your written explanation and the facts of the case, management has decided to [decision, e.g. impose a three (3) day suspension without pay effective (date)].\n\nPlease be guided accordingly.' },
                coe: { subject: 'Certificate of Employment', body: 'TO WHOM IT MAY CONCERN:\n\nThis is to certify that {name} has been employed with NobleHome Construction Corporation as [Position] from [start date] up to the present.\n\nThis certification is being issued upon the request of {name} for [purpose] purposes.\n\nIssued this [date] at Quezon City, Philippines.' },
            };

            let rows = [], tab = 'todo', ctx = {}, cur = null, emps = null, ntes = [];

            function toast(message, type = 'success') {
                const t = document.createElement('div');
                t.className = `pointer-events-auto border rounded-lg shadow-lg px-4 py-3 text-sm transition-all duration-300 ${type === 'success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700'}`;
                t.textContent = message;
                F('mmToast').appendChild(t);
                setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, 3800);
            }
            async function api(action, params = {}, post = false) {
                const body = new URLSearchParams();
                Object.entries(params).forEach(([k, v]) => body.append(k, v ?? ''));
                const url = post ? `${AJAX}?action=${action}` : `${AJAX}?action=${action}&${body}`;
                const res = await fetch(url, post ? { method: 'POST', body, cache: 'no-store' } : { cache: 'no-store' });
                const text = await res.text();
                let d;
                try { d = JSON.parse(text); }
                catch (e) { throw new Error(/<html|<!doctype/i.test(text) ? 'The server returned a page instead of data. Check that "memoajax" is in the router, or reload if your session expired.' : 'The server sent an unexpected response.'); }
                if (!d.success) throw new Error(d.message || 'Something went wrong.');
                return d;
            }
            const typePill = t => { const x = T[t] || T.nte; return `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-semibold border" style="color:${x.color};background:${x.bg};border-color:${x.color}33"><i class="fa-solid ${x.icon} text-[9px]"></i>${x.label}</span>`; };
            const statPill = s => { const x = S[s] || S.draft; return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10.5px] font-semibold border" style="color:${x[1]};background:${x[2]};border-color:${x[1]}33">${x[0]}</span>`; };
            const WAIT = { draft: 'HR (draft)', returned: 'HR (fix & resubmit)', pending_verify: 'HR Head', pending_approval: 'Superadmin' };

            // ═══ LIST ═══
            const inTab = (r, k) => k === 'todo' ? r.my_turn : k === 'sent' ? r.status === 'sent' : k === 'progress' ? (!r.my_turn && r.status !== 'sent') : true;
            function render() {
                const q = F('mmSearch').value.trim().toLowerCase(), ty = F('mmType').value, mo = F('mmMonth').value;
                const base = rows.filter(r => (!ty || r.type === ty) && (!mo || String(r.memo_date || r.created_at).slice(0, 7) === mo)
                    && (!q || `${r.memo_no || ''} ${r.subject || ''} ${r.prepared_name || ''}`.toLowerCase().includes(q)));
                document.querySelectorAll('.mm-tab').forEach(b => {
                    b.dataset.active = b.dataset.tab === tab ? '1' : '0';
                    b.querySelector('.mm-tab-count').textContent = base.filter(r => inTab(r, b.dataset.tab)).length;
                });
                const list = base.filter(r => inTab(r, tab));
                F('mmCount').textContent = `${list.length} memo${list.length === 1 ? '' : 's'}`;
                if (!list.length) {
                    F('mmList').innerHTML = `<div class="bg-white border-2 border-dashed border-gray-200 rounded-xl px-6 py-12 text-center"><div class="w-12 h-12 mx-auto rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center mb-2"><i class="fa-solid fa-envelope-open"></i></div><p class="text-sm font-semibold text-gray-700">${tab === 'todo' ? (MODE === 'hr' ? 'Nothing to do. Create a new memo to start.' : 'All caught up. Nothing waiting for your signature.') : 'No memos here.'}</p></div>`;
                    return;
                }
                F('mmList').innerHTML = list.map(r => {
                    const to = r.audience === 'all' ? 'All employees' : `${r.selected_count} employee${r.selected_count == 1 ? '' : 's'}`;
                    const info = r.status === 'sent'
                        ? `<span class="text-emerald-700"><i class="fa-solid fa-paper-plane mr-1"></i>Sent ${fmtDate(r.sent_at)} · ${r.seen}/${r.rcpt} read${r.type === 'nte' ? ` · ${r.answered} answered` : ''}</span>`
                        : r.status === 'returned' ? `<span class="text-red-700"><i class="fa-solid fa-rotate-left mr-1"></i>Returned · ${esc(r.return_remarks || '')}</span>`
                            : `<span class="text-gray-500"><i class="fa-regular fa-clock mr-1"></i>Waiting for ${WAIT[r.status] || ''}</span>`;
                    return `<button type="button" data-id="${r.id}" class="mm-row relative w-full text-left bg-white border border-gray-200 rounded-xl overflow-hidden hover:border-gray-300 hover:shadow-sm transition">
                        <span class="absolute left-0 top-0 bottom-0 w-1" style="background:${(T[r.type] || T.nte).color}"></span>
                        <span class="flex flex-wrap items-center gap-x-5 gap-y-2 pl-5 pr-4 py-3.5">
                            <span class="min-w-0 flex-1 basis-[280px]">
                                <span class="flex flex-wrap items-center gap-2">
                                    ${r.my_turn ? '<span class="px-1.5 py-0.5 rounded text-[9.5px] font-bold uppercase tracking-wide bg-amber-700 text-white">Your turn</span>' : ''}
                                    <span class="font-mono text-[12px] font-bold text-gray-900">${esc(r.memo_no || 'DRAFT')}</span>${typePill(r.type)}${statPill(r.status)}
                                    ${r.need_superadmin == 1 ? '<span class="text-[10px] font-semibold text-blue-700"><i class="fa-solid fa-user-tie mr-0.5"></i>needs Superadmin</span>' : ''}
                                </span>
                                <span class="block text-sm font-semibold text-gray-800 truncate mt-0.5">${esc(r.subject || '(no subject)')}</span>
                                <span class="block text-[11px] truncate mt-0.5">${info}</span>
                            </span>
                            <span class="grid grid-cols-3 gap-x-5 text-[11px] basis-[300px] grow sm:grow-0">
                                <span><span class="block text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Date</span><span class="text-gray-700">${fmtDate(r.memo_date || r.created_at)}</span></span>
                                <span class="min-w-0"><span class="block text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">To</span><span class="text-gray-700 truncate block">${to}</span></span>
                                <span class="min-w-0"><span class="block text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Prepared by</span><span class="text-gray-700 truncate block">${esc(r.prepared_name || '—')}</span></span>
                            </span>
                            <span class="ml-auto shrink-0">${r.my_turn
                                ? `<span class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg shadow-sm"><i class="fa-solid ${MODE === 'hr' && ['draft', 'returned'].includes(r.status) ? 'fa-pen' : 'fa-pen-nib'}"></i> ${['draft', 'returned'].includes(r.status) ? 'Continue' : 'Review & sign'}</span>`
                                : '<span class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg"><i class="fa-regular fa-eye"></i> View</span>'}</span>
                        </span></button>`;
                }).join('');
            }
            async function load() {
                try { const d = await api('list'); rows = d.rows; ctx = d; render(); }
                catch (e) { F('mmList').innerHTML = `<div class="bg-white border border-red-200 rounded-xl px-6 py-10 text-center text-xs text-red-600">${esc(e.message)}</div>`; }
            }

            // ═══ MODAL ═══
            const openM = () => { F('mmModal').classList.remove('hidden'); F('mmModal').classList.add('flex'); document.body.style.overflow = 'hidden'; };
            const closeM = () => { F('mmModal').classList.add('hidden'); F('mmModal').classList.remove('flex'); document.body.style.overflow = ''; cur = null; };
            F('mmClose').addEventListener('click', closeM);
            F('mmModal').addEventListener('click', e => { if (e.target === F('mmModal')) closeM(); });
            document.addEventListener('keydown', e => { if (e.key === 'Escape' && cur) closeM(); });
            F('mmPrint').addEventListener('click', () => cur && MemoDoc.print(cur.memo));
            F('mmList').addEventListener('click', e => { const b = e.target.closest('.mm-row'); if (b) openMemo(+b.dataset.id); });
            if (F('mmNew')) F('mmNew').addEventListener('click', () => openMemo(0));

            async function openMemo(id) {
                let d;
                try { d = await api('get', { id }); } catch (e) { return toast(e.message, 'error'); }
                cur = d;
                if (d.can_edit && !emps) { try { const e = await api('employees'); emps = e.employees; ntes = e.ntes; } catch (e) { emps = []; } }
                const m = d.memo;
                if (d.can_edit && !m.id) { m.subject = TEMPLATES.nte.subject; m.body = TEMPLATES.nte.body; }
                cur.pick = new Set((m.recipient_ids || []).map(Number));
                F('mmKicker').textContent = d.can_edit ? (m.id ? 'Edit memo' : 'New memo') : d.sign_as ? 'Your signature' : (T[m.type] || T.nte).label;
                F('mmMTitle').textContent = m.id ? `${m.memo_no || 'Draft'} · ${m.subject || ''}` : 'New memo';
                F('mmPrint').classList.toggle('hidden', !m.id || d.can_edit);
                if (d.can_edit) renderEdit(); else { drawDoc(); renderSide(); }
                openM();
            }

            function drawDoc() {
                const d = cur, m = d.memo;
                const opts = d.sign_as ? (d.my_signature ? { preview: { col: d.sign_as, url: d.my_signature, name: d.me } } : { here: d.sign_as })
                    : (d.can_edit && d.my_signature ? { preview: { col: 'prepared', url: d.my_signature, name: d.me } } : {});
                MemoDoc.render(F('mmDoc'), m, opts);
            }

            function sigBox() {
                return cur.my_signature
                    ? `<div class="rounded-xl border border-gray-200 bg-white p-3 flex items-center justify-center h-20"><img src="${esc(cur.my_signature)}" alt="Your signature" class="max-h-full max-w-full object-contain"></div><p class="text-[11px] text-gray-500 mt-1.5">Signing as <b class="text-gray-800">${esc(cur.me)}</b>.</p>`
                    : `<div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900"><p class="font-bold"><i class="fa-solid fa-triangle-exclamation mr-1"></i>No e-signature set yet</p><p class="mt-0.5">Your name will show without a signature image.</p><a href="${SIG_PAGE}" class="inline-flex items-center gap-1 mt-1.5 font-semibold text-amber-800 hover:underline">Set my signature <i class="fa-solid fa-arrow-right text-[10px]"></i></a></div>`;
            }

            function timeline(m) {
                const steps = [['Prepared by', m.prepared_name, 'HR', m.prepared_at], ['Verified by', m.verified_name, 'HR Head', m.verified_at]];
                if (m.need_superadmin == 1) steps.push(['Approved by', m.approved_name, 'Superadmin', m.approved_at]);
                steps.push(['Sent to employees', '', '', m.sent_at]);
                const next = steps.findIndex(s => !s[3]);
                return `<section class="rounded-xl border border-gray-200 overflow-hidden">
                    <p class="px-4 py-2.5 border-b border-gray-100 text-[10.5px] font-bold uppercase tracking-wider text-gray-500"><i class="fa-solid fa-signature mr-1 text-amber-700"></i>Progress</p>
                    <ol class="px-4 py-3 space-y-3">${steps.map(([l, n, role, at], i) => {
                        const done = !!at, now = !done && i === next && !['draft', 'returned'].includes(m.status);
                        return `<li class="flex items-start gap-3"><span class="w-7 h-7 rounded-full shrink-0 flex items-center justify-center text-[11px] ${done ? 'bg-emerald-600 text-white' : now ? 'bg-amber-500 text-white ring-4 ring-amber-100' : 'bg-gray-100 text-gray-400'}"><i class="fa-solid ${done ? 'fa-check' : now ? 'fa-hourglass-half' : 'fa-pen-nib'}"></i></span>
                            <span class="min-w-0"><span class="block text-[12px] font-semibold text-gray-800">${l}${role ? ` <span class="font-normal text-gray-400">· ${role}</span>` : ''}</span>
                            <span class="block text-[11px] ${done ? 'text-gray-500' : now ? 'text-amber-700 font-semibold' : 'text-gray-400'}">${done ? `${esc(n || '')} ${n ? '·' : ''} ${fmtDT(at)}` : now ? 'Waiting now' : 'Not yet'}</span></span></li>`;
                    }).join('')}</ol></section>`;
            }

            // ═══ EDIT (HR) — form sa kanan, live preview sa kaliwa ═══
            // bagong #mmSide para hindi dumoble ang mga event listener
            function freshSide() { const o = F('mmSide'), n = o.cloneNode(false); o.replaceWith(n); return n; }

            function renderEdit() {
                const m = cur.memo, side = freshSide();
                const ret = m.status === 'returned' && m.return_remarks ? `<div class="rounded-xl border border-red-200 bg-red-50 px-3.5 py-2.5 text-[12px] text-red-800"><p class="font-bold"><i class="fa-solid fa-rotate-left mr-1"></i>Returned by ${esc(m.returned_name || '')}</p><p class="mt-0.5 whitespace-pre-line">${esc(m.return_remarks)}</p></div>` : '';
                side.innerHTML = `${ret}
                    <div><span class="mm-lbl">1 · Type of memo</span>
                        <div class="grid grid-cols-3 gap-1.5">${Object.entries(T).map(([k, t]) => `<button type="button" data-type="${k}" data-on="${m.type === k ? 1 : 0}" class="mm-type rounded-lg border border-gray-200 bg-white px-2 py-2 text-center hover:border-amber-300">
                            <i class="fa-solid ${t.icon} text-sm" style="color:${t.color}"></i><span class="block text-[10.5px] font-semibold text-gray-800 leading-tight mt-1">${t.label}</span></button>`).join('')}</div></div>

                    <div><span class="mm-lbl">2 · Send to</span>
                        <div class="grid grid-cols-2 gap-1.5">
                            <button type="button" data-aud="all" data-on="${m.audience === 'all' ? 1 : 0}" class="mm-aud rounded-lg border border-gray-200 bg-white px-3 py-2 text-left hover:border-amber-300"><span class="block text-[12px] font-semibold text-gray-800"><i class="fa-solid fa-users mr-1 text-amber-700"></i>All employees</span><span class="block text-[10.5px] text-gray-400">${emps ? emps.length : 0} people</span></button>
                            <button type="button" data-aud="selected" data-on="${m.audience !== 'all' ? 1 : 0}" class="mm-aud rounded-lg border border-gray-200 bg-white px-3 py-2 text-left hover:border-amber-300"><span class="block text-[12px] font-semibold text-gray-800"><i class="fa-solid fa-user-check mr-1 text-amber-700"></i>Selected</span><span id="mmPickCount" class="block text-[10.5px] text-gray-400"></span></button>
                        </div>
                        <div id="mmPicker" class="${m.audience === 'all' ? 'hidden' : ''} mt-2 rounded-xl border border-gray-200 overflow-hidden">
                            <div class="p-2 border-b border-gray-100 flex gap-2"><input id="mmEmpQ" class="mm-inp !h-8" placeholder="Search employee…"><button type="button" id="mmEmpClear" class="text-[10.5px] font-semibold text-gray-500 hover:text-amber-700 whitespace-nowrap px-1">Clear</button></div>
                            <div id="mmEmpList" class="max-h-48 overflow-y-auto mm-scroll divide-y divide-gray-50"></div>
                        </div></div>

                    <div class="grid grid-cols-2 gap-2">
                        <label class="col-span-2"><span class="mm-lbl">3 · Subject</span><input data-k="subject" class="mm-inp" value="${esc(m.subject || '')}"></label>
                        <label><span class="mm-lbl">Memo date</span><input data-k="memo_date" type="date" class="mm-inp" value="${esc(m.memo_date || '')}"></label>
                        <label id="mmDueWrap" class="${m.type === 'nte' ? '' : 'hidden'}"><span class="mm-lbl">Response due</span><input data-k="response_due" type="date" class="mm-inp" value="${esc(m.response_due || '')}"></label>
                        <label id="mmRefWrap" class="${m.type === 'nod' ? '' : 'hidden'}"><span class="mm-lbl">Related NTE</span><select data-k="ref_memo_id" class="mm-sel w-full"><option value="">— none —</option>${ntes.map(n => `<option value="${n.id}" ${+m.ref_memo_id === +n.id ? 'selected' : ''}>${esc(n.memo_no)} · ${esc(n.subject)}</option>`).join('')}</select></label>
                    </div>

                    <label class="block"><span class="flex items-center justify-between"><span class="mm-lbl">4 · Message</span><button type="button" id="mmTpl" class="text-[10.5px] font-semibold text-amber-700 hover:underline mb-1"><i class="fa-solid fa-wand-magic-sparkles mr-0.5"></i>Use template</button></span>
                        <textarea data-k="body" rows="10" class="mm-inp">${esc(m.body || '')}</textarea>
                        <span class="block text-[10.5px] text-gray-400 mt-1">Type <code class="bg-gray-100 px-1 rounded">{name}</code> and each employee will see their own name.</span></label>

                    <label class="flex items-start gap-3 rounded-xl border border-gray-200 p-3 cursor-pointer hover:border-amber-300">
                        <input data-k="need_superadmin" type="checkbox" class="mt-0.5 w-4 h-4 accent-amber-700" ${m.need_superadmin == 1 ? 'checked' : ''}>
                        <span><span class="block text-[12px] font-semibold text-gray-800">Needs Superadmin approval</span><span class="block text-[10.5px] text-gray-500">Adds "Approved By: CEO/General Manager". Unchecked = sent right after the HR Head verifies.</span></span></label>

                    <section><p class="mm-lbl"><i class="fa-solid fa-signature mr-1 text-amber-700"></i>Your signature (Prepared by)</p>${sigBox()}</section>
                    <p id="mmErr" class="hidden rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-[11px] text-red-700"></p>
                    <div id="mmActs" class="space-y-2">
                        <button type="button" id="mmSubmit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-amber-700 rounded-xl hover:bg-amber-800 shadow-sm"><i class="fa-solid fa-paper-plane"></i> ${ctx.is_hr_head ? 'Sign & send' : 'Submit to HR Head'}</button>
                        <button type="button" id="mmSave" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50"><i class="fa-regular fa-floppy-disk"></i> Save draft</button>
                        ${m.id && m.status === 'draft' ? '<button type="button" id="mmDel" class="w-full text-[11px] font-semibold text-red-600 hover:underline py-1">Delete this draft</button>' : ''}
                    </div>`;

                const read = () => {
                    side.querySelectorAll('[data-k]').forEach(i => { m[i.dataset.k] = i.type === 'checkbox' ? (i.checked ? 1 : 0) : i.value; });
                    m.recipient_ids = [...cur.pick];
                    const names = (emps || []).filter(e => cur.pick.has(+e.id));
                    m.to_label = m.audience === 'all' ? 'All Employees' : names.length === 1 ? names[0].name : names.length ? `${names.length} employees` : '';
                    m.ref_no = (ntes.find(n => +n.id === +m.ref_memo_id) || {}).memo_no || '';
                    F('mmPickCount').textContent = `${cur.pick.size} picked`;
                    drawDoc();
                };
                const drawEmps = () => {
                    const q = (F('mmEmpQ').value || '').toLowerCase();
                    const list = (emps || []).filter(e => !q || `${e.name} ${e.email}`.toLowerCase().includes(q));
                    F('mmEmpList').innerHTML = list.length ? list.map(e => `<label class="flex items-center gap-2.5 px-3 py-2 hover:bg-amber-50/50 cursor-pointer">
                        <input type="checkbox" data-emp="${e.id}" class="w-4 h-4 accent-amber-700" ${cur.pick.has(+e.id) ? 'checked' : ''}>
                        <span class="min-w-0"><span class="block text-[12px] font-medium text-gray-800 truncate">${esc(e.name)}</span><span class="block text-[10px] text-gray-400 truncate">${esc(e.email || '')}</span></span></label>`).join('')
                        : '<p class="px-3 py-6 text-center text-[11px] text-gray-400">No employee found.</p>';
                };
                drawEmps(); read();

                side.addEventListener('input', e => { if (e.target.id === 'mmEmpQ') return drawEmps(); read(); });
                side.addEventListener('change', e => {
                    const c = e.target.closest('[data-emp]');
                    if (c) { c.checked ? cur.pick.add(+c.dataset.emp) : cur.pick.delete(+c.dataset.emp); }
                    read();
                });
                side.addEventListener('click', e => {
                    const t = e.target.closest('[data-type]');
                    if (t) {
                        const wasTpl = Object.values(TEMPLATES).some(x => x.body === m.body) || !m.body.trim();
                        m.type = t.dataset.type;
                        side.querySelectorAll('[data-type]').forEach(b => b.dataset.on = b === t ? '1' : '0');
                        F('mmDueWrap').classList.toggle('hidden', m.type !== 'nte');
                        F('mmRefWrap').classList.toggle('hidden', m.type !== 'nod');
                        if (wasTpl) { side.querySelector('[data-k="body"]').value = TEMPLATES[m.type].body; side.querySelector('[data-k="subject"]').value = TEMPLATES[m.type].subject; }
                        return read();
                    }
                    const a = e.target.closest('[data-aud]');
                    if (a) { m.audience = a.dataset.aud; side.querySelectorAll('[data-aud]').forEach(b => b.dataset.on = b === a ? '1' : '0'); F('mmPicker').classList.toggle('hidden', m.audience === 'all'); return read(); }
                    if (e.target.closest('#mmEmpClear')) { cur.pick.clear(); drawEmps(); return read(); }
                    if (e.target.closest('#mmTpl')) { side.querySelector('[data-k="body"]').value = TEMPLATES[m.type].body; return read(); }
                });

                const err = msg => { F('mmErr').textContent = msg || ''; F('mmErr').classList.toggle('hidden', !msg); };
                const payload = () => ({ id: m.id || 0, type: m.type, audience: m.audience, recipient_ids: JSON.stringify([...cur.pick]), subject: m.subject, body: m.body,
                    memo_date: m.memo_date, response_due: m.response_due || '', ref_memo_id: m.ref_memo_id || '', need_superadmin: m.need_superadmin ? 1 : 0 });
                F('mmSave').addEventListener('click', async function () {
                    err(''); this.disabled = true;
                    try { const r = await api('save', payload(), true); toast(r.message); await load(); openMemo(r.id); } catch (e) { err(e.message); } finally { this.disabled = false; }
                });
                F('mmSubmit').addEventListener('click', () => {
                    err('');
                    const who = m.audience === 'all' ? `all ${emps ? emps.length : ''} employees` : `${cur.pick.size} employee${cur.pick.size === 1 ? '' : 's'}`;
                    const next = ctx.is_hr_head ? (m.need_superadmin ? 'It goes to the Superadmin for approval.' : `It will be sent to ${who} right away.`) : 'It goes to the HR Head to verify.';
                    F('mmActs').innerHTML = `<div class="rounded-xl border border-amber-200 bg-amber-50 p-3"><p class="text-xs text-amber-900">Submit this <b>${(T[m.type] || T.nte).label}</b> for <b>${who}</b>? ${next}</p>
                        <div class="flex gap-2 mt-2"><button type="button" id="mmYes" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800"><i class="fa-solid fa-check"></i> Yes, submit</button>
                        <button type="button" id="mmNo" class="px-3 py-2 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg">Cancel</button></div></div>`;
                    F('mmNo').addEventListener('click', renderEdit);
                    F('mmYes').addEventListener('click', async function () {
                        this.disabled = true; this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting…';
                        try { const r = await api('submit', payload(), true); toast(r.message); await load(); openMemo(r.id); }
                        catch (e) { renderEdit(); F('mmErr').textContent = e.message; F('mmErr').classList.remove('hidden'); }
                    });
                });
                if (F('mmDel')) F('mmDel').addEventListener('click', async () => {
                    try { const r = await api('delete', { id: m.id }, true); toast(r.message); closeM(); load(); } catch (e) { err(e.message); }
                });
            }

            // ═══ SIGN / VIEW ═══
            function renderSide() {
                const d = cur, m = d.memo, side = freshSide();
                if (d.sign_as) {
                    const label = d.sign_as === 'verified' ? 'Verified by (HR Head)' : 'Approved by (Superadmin)';
                    const after = d.sign_as === 'verified' && m.need_superadmin == 1 ? 'It then goes to the Superadmin.' : `It will be sent to ${esc(m.to_label || 'the employees')}.`;
                    side.innerHTML = `
                        <div class="rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-3 text-[12px]"><p class="font-semibold text-gray-800">${typePill(m.type)} <span class="ml-1">${esc(m.to_label || '')}</span></p><p class="text-gray-500 mt-1">Prepared by ${esc(m.prepared_name || '')} · ${fmtDT(m.prepared_at)}</p></div>
                        <section><p class="mm-lbl"><i class="fa-solid fa-signature mr-1 text-amber-700"></i>Your signature (${label})</p>${sigBox()}</section>
                        <p id="mmErr" class="hidden rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-[11px] text-red-700"></p>
                        <div id="mmSignArea"><button type="button" id="mmSign" class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-amber-700 rounded-xl hover:bg-amber-800 shadow-sm"><i class="fa-solid fa-file-signature"></i> Sign</button><p class="text-[10.5px] text-gray-500 mt-1.5 text-center">${after}</p></div>
                        <details class="group rounded-xl border border-gray-200">
                            <summary class="list-none cursor-pointer flex items-center justify-between px-3.5 py-2.5 text-xs font-semibold text-red-700"><span><i class="fa-solid fa-rotate-left mr-1"></i>Return to HR</span><i class="fa-solid fa-chevron-down text-[10px] text-gray-400 group-open:rotate-180 transition"></i></summary>
                            <div class="px-3.5 pb-3.5"><textarea id="mmRem" rows="3" maxlength="1000" placeholder="What should HR fix?" class="mm-inp"></textarea>
                                <button type="button" id="mmRet" class="mt-2 w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100"><i class="fa-solid fa-paper-plane"></i> Send back to HR</button></div>
                        </details>
                        ${timeline(m)}`;
                    const err = msg => { F('mmErr').textContent = msg || ''; F('mmErr').classList.toggle('hidden', !msg); };
                    F('mmSign').addEventListener('click', async function () {
                        this.disabled = true; this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Signing…';
                        try { const r = await api(d.sign_as === 'verified' ? 'verify' : 'approve', { id: m.id }, true); toast(r.message); await load(); openMemo(m.id); }
                        catch (e) { renderSide(); err(e.message); }
                    });
                    F('mmRet').addEventListener('click', async function () {
                        const rem = F('mmRem').value.trim();
                        if (rem.length < 3) return err('Tell HR what to fix.');
                        this.disabled = true;
                        try { const r = await api('return', { id: m.id, remarks: rem }, true); toast(r.message); closeM(); load(); }
                        catch (e) { err(e.message); this.disabled = false; }
                    });
                    return;
                }

                // tingin lang — kasama ang mga nakatanggap at sagot
                const rc = d.recipients || [];
                const seen = rc.filter(r => r.read_at).length, ans = rc.filter(r => r.response_at).length;
                side.innerHTML = `
                    <div class="rounded-xl border p-3.5 text-xs" style="border-color:${(S[m.status] || S.draft)[1]}33;background:${(S[m.status] || S.draft)[2]};color:${(S[m.status] || S.draft)[1]}">
                        <p class="font-bold text-sm">${(S[m.status] || S.draft)[0]}</p>
                        <p class="mt-0.5">${m.status === 'sent' ? `Sent ${fmtDT(m.sent_at)} to ${rc.length} employee${rc.length === 1 ? '' : 's'}.` : WAIT[m.status] ? `Waiting for ${WAIT[m.status]}.` : ''}</p></div>
                    ${m.status === 'sent' ? `<section class="rounded-xl border border-gray-200 overflow-hidden">
                        <div class="px-4 py-2.5 border-b border-gray-100 flex items-center justify-between"><p class="text-[10.5px] font-bold uppercase tracking-wider text-gray-500"><i class="fa-solid fa-users mr-1 text-amber-700"></i>Recipients</p>
                            <p class="text-[10.5px] text-gray-500">${seen}/${rc.length} read${m.type === 'nte' ? ` · ${ans} answered` : ''}</p></div>
                        <div class="max-h-[340px] overflow-y-auto mm-scroll divide-y divide-gray-100">${rc.map(r => `<div class="px-4 py-2.5">
                            <div class="flex items-center justify-between gap-2"><span class="text-[12px] font-semibold text-gray-800 truncate">${esc(r.name || 'Account #' + r.account_id)}</span>
                                ${r.response_at ? '<span class="text-[10px] font-bold text-emerald-700"><i class="fa-solid fa-reply mr-0.5"></i>Answered</span>' : r.read_at ? '<span class="text-[10px] font-semibold text-blue-700"><i class="fa-solid fa-eye mr-0.5"></i>Read</span>' : '<span class="text-[10px] text-gray-400">Not opened</span>'}</div>
                            ${r.response ? `<details class="mt-1"><summary class="text-[11px] font-semibold text-amber-700 cursor-pointer">View response · ${fmtDT(r.response_at)}</summary><p class="mt-1 text-[11.5px] text-gray-700 whitespace-pre-line bg-gray-50 border border-gray-100 rounded-lg p-2">${esc(r.response)}</p></details>` : ''}
                        </div>`).join('') || '<p class="px-4 py-6 text-center text-[11px] text-gray-400">No recipients.</p>'}</div></section>` : ''}
                    ${timeline(m)}`;
            }

            // ═══ events ═══
            document.querySelectorAll('.mm-tab').forEach(b => b.addEventListener('click', () => { tab = b.dataset.tab; render(); }));
            ['mmSearch', 'mmType', 'mmMonth'].forEach(id => F(id).addEventListener('input', render));
            setInterval(() => { if (!document.hidden && !cur) load(); }, 30000);
            load().then(() => { const o = new URLSearchParams(location.search).get('memo'); if (o && /^\d+$/.test(o)) openMemo(+o); });
        })();
    </script>
</body>

</html>