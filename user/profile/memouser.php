<?php
// memouser.php (route: memouser) — user side: "My Memos"
//   Lahat ng memo na natanggap ng employee · Sagot sa Notice to Explain

include ROOT_PATH . '/network/connect.php';
// ⚠️ isama dito ang auth guard ng user side ninyo (kapareho ng ibang user page)

$muId = (int) ($_SESSION['account_id'] ?? 0);   // nobleaccount.id (kapareho ng top.php)
if ($muId <= 0) {
    header('Location: ' . BASE_URL . '/home1');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Memos</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/ui-humanresource/page-6/memo-doc.php'; ?>
    <style>
        .mu-hazard {
            background: repeating-linear-gradient(-45deg, #f59e0b 0 10px, #1f2937 10px 20px);
        }

        .mu-item[data-on="1"] {
            border-color: #b45309;
            background: #fffbeb;
            box-shadow: inset 3px 0 0 #b45309;
        }

        .mu-scroll {
            scrollbar-width: thin;
            scrollbar-color: #d1d5db transparent;
        }
    </style>
</head>

<body class="min-h-screen relative"
    style="background-image: url('<?= BASE_URL ?>/icon/building2.png'); background-size: cover; background-position: center; background-repeat: no-repeat; background-attachment: fixed;">
    <?php include ROOT_PATH . '/user/navigation/top.php'; ?>
    <div class="fixed inset-0 bg-black/55 z-0"></div>


    <main class="relative z-10 max-w-6xl mx-auto px-4 pt-36 md:pt-40 pb-10">
        <section class="bg-white/95 backdrop-blur border border-white/20 rounded-xl mb-4 shadow-xl">
            <div class="mu-hazard h-1.5 rounded-t-xl"></div>
            <div class="p-5 flex items-center gap-3">
                <span
                    class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i
                        class="fa-solid fa-envelope-open-text text-lg"></i></span>
                <div>
                    <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase">From HR</p>
                    <h1 class="text-gray-900 text-lg font-semibold leading-tight">My Memos</h1>
                    <p class="text-gray-400 text-[11px] mt-0.5">Memos and certificates sent to you. Answer a Notice to
                        Explain before its deadline.</p>
                </div>
                <span id="muUnread"
                    class="hidden ml-auto px-2.5 py-1 rounded-full bg-red-500 text-white text-[11px] font-bold"></span>
            </div>
        </section>

        <div class="grid lg:grid-cols-[320px_minmax(0,1fr)] gap-4 items-start">
            <aside class="bg-white/95 backdrop-blur border border-white/20 shadow-xl rounded-xl overflow-hidden lg:sticky lg:top-36">
                <div id="muList" class="max-h-[72vh] overflow-y-auto mu-scroll p-2 space-y-1.5"></div>
            </aside>
            <section id="muView" class="min-w-0"></section>
        </div>
    </main>

    <div id="muToast"
        class="fixed bottom-4 right-4 z-[10000] flex flex-col gap-2.5 pointer-events-none w-[340px] max-w-[calc(100vw-2rem)]">
    </div>

    <script>
        (function () {
            'use strict';
            const AJAX = <?= json_encode(BASE_URL . '/memouserajax') ?>;
            const F = id => document.getElementById(id);
            const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
            const fmtDate = v => { if (!v) return ''; const d = new Date(String(v).slice(0, 10) + 'T00:00:00'); return isNaN(d) ? v : d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }); };
            const fmtDT = v => { if (!v) return ''; const d = new Date(String(v).replace(' ', 'T')); return isNaN(d) ? v : d.toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' }); };
            const T = MemoDoc.TYPES;
            const today = () => { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; };

            let rows = [], curId = 0, cur = null;

            function toast(message, type = 'success') {
                const t = document.createElement('div');
                t.className = `pointer-events-auto border rounded-lg shadow-lg px-4 py-3 text-sm ${type === 'success' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700'}`;
                t.textContent = message; F('muToast').appendChild(t);
                setTimeout(() => t.remove(), 3800);
            }
            async function api(action, params = {}, post = false) {
                const body = new URLSearchParams(params);
                const res = await fetch(post ? `${AJAX}?action=${action}` : `${AJAX}?action=${action}&${body}`, post ? { method: 'POST', body, cache: 'no-store' } : { cache: 'no-store' });
                const text = await res.text();
                let d; try { d = JSON.parse(text); } catch (e) { throw new Error('The server sent an unexpected response. Reload the page.'); }
                if (!d.success) throw new Error(d.message || 'Something went wrong.');
                return d;
            }

            const needsReply = r => r.type === 'nte' && !r.response_at;
            function renderList() {
                const unread = rows.filter(r => !r.read_at).length;
                if (window.setMemoBadge) window.setMemoBadge(unread);   // badge sa top.php
                F('muUnread').textContent = `${unread} new`; F('muUnread').classList.toggle('hidden', !unread);
                if (!rows.length) { F('muList').innerHTML = '<p class="px-3 py-12 text-center text-xs text-gray-400"><i class="fa-regular fa-envelope text-2xl block mb-2"></i>No memos yet.</p>'; return; }
                F('muList').innerHTML = rows.map(r => {
                    const t = T[r.type] || T.nte;
                    const late = needsReply(r) && r.response_due && r.response_due < today();
                    return `<button type="button" data-id="${r.id}" data-on="${r.id === curId ? 1 : 0}" class="mu-item w-full text-left rounded-lg border border-gray-200 bg-white px-3 py-2.5 hover:border-amber-300 transition">
                        <span class="flex items-start gap-2.5">
                            <span class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0" style="background:${t.bg};color:${t.color}"><i class="fa-solid ${t.icon} text-xs"></i></span>
                            <span class="min-w-0 flex-1">
                                <span class="flex items-center gap-1.5"><span class="text-[10px] font-bold uppercase tracking-wide" style="color:${t.color}">${t.label}</span>${!r.read_at ? '<span class="w-2 h-2 rounded-full bg-red-500"></span>' : ''}</span>
                                <span class="block text-[12.5px] ${r.read_at ? 'font-medium text-gray-700' : 'font-bold text-gray-900'} truncate">${esc(r.subject)}</span>
                                <span class="block text-[10.5px] text-gray-400">${fmtDate(r.sent_at)}${needsReply(r) ? ` · <span class="${late ? 'text-red-600' : 'text-amber-700'} font-semibold">${late ? 'Overdue' : 'Reply by ' + fmtDate(r.response_due)}</span>` : r.type === 'nte' ? ' · <span class="text-emerald-700 font-semibold">Answered</span>' : ''}</span>
                            </span>
                        </span></button>`;
                }).join('');
            }

            function renderEmpty() {
                F('muView').innerHTML = `<div class="bg-white/95 backdrop-blur border-2 border-dashed border-gray-300 rounded-xl px-6 py-20 text-center shadow-xl">
                    <div class="w-14 h-14 mx-auto rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center mb-3"><i class="fa-regular fa-envelope-open text-xl"></i></div>
                    <p class="text-sm font-semibold text-gray-700">Pick a memo to read it</p></div>`;
            }

            async function openMemo(id) {
                curId = id; renderList();
                let d; try { d = await api('get', { id }); } catch (e) { return toast(e.message, 'error'); }
                cur = d.memo;
                const r = rows.find(x => x.id == id); if (r && !r.read_at) { r.read_at = d.memo.read_at; renderList(); }
                const m = cur, nte = m.type === 'nte';
                F('muView').innerHTML = `
                    <div class="bg-white border border-white/20 rounded-xl overflow-hidden shadow-xl">
                        <div class="flex items-center justify-between gap-2 px-4 py-2.5 border-b border-gray-100">
                            <span class="font-mono text-[12px] font-bold text-gray-700">${esc(m.memo_no || '')}</span>
                            <button type="button" id="muPrint" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-gray-900 rounded-lg hover:bg-gray-800"><i class="fa-solid fa-print"></i> Print / Save PDF</button>
                        </div>
                        <div class="p-3 md:p-5 bg-slate-50 overflow-x-auto mu-scroll">
                            <div id="muDoc" class="min-w-[640px] max-w-[820px] mx-auto bg-white border border-gray-200 rounded-xl p-6 md:p-9 shadow-sm"></div>
                        </div>
                        ${nte ? `<div id="muReply" class="border-t border-gray-100 p-5"></div>` : ''}
                    </div>`;
                MemoDoc.render(F('muDoc'), m, { toName: m.my_name });
                F('muPrint').addEventListener('click', () => MemoDoc.print(m, { toName: m.my_name }));
                if (nte) renderReply();
            }

            function renderReply() {
                const m = cur, box = F('muReply');
                if (m.response_at) {
                    box.innerHTML = `<p class="text-[10.5px] font-bold uppercase tracking-wider text-emerald-700 mb-1.5"><i class="fa-solid fa-reply mr-1"></i>Your response · sent ${fmtDT(m.response_at)}</p>
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-3.5 text-[13px] text-gray-800 whitespace-pre-line">${esc(m.response)}</div>`;
                    return;
                }
                const late = m.response_due && m.response_due < today();
                const key = 'nh-memo-reply-' + m.id;
                box.innerHTML = `<div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                        <p class="text-[10.5px] font-bold uppercase tracking-wider text-gray-600"><i class="fa-solid fa-pen mr-1 text-amber-700"></i>Response to Notice to Explain</p>
                        ${m.response_due ? `<span class="text-[11px] font-semibold ${late ? 'text-red-600' : 'text-amber-700'}"><i class="fa-regular fa-clock mr-1"></i>${late ? 'Overdue since' : 'Due'} ${fmtDate(m.response_due)}</span>` : ''}</div>
                    <textarea id="muText" rows="7" maxlength="5000" placeholder="Write your explanation here. Be clear and honest about what happened." class="w-full border border-gray-200 rounded-xl px-3.5 py-3 text-[13px] outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-100 resize-y"></textarea>
                    <div class="flex items-center justify-between gap-3 mt-2">
                        <span id="muLen" class="text-[10.5px] text-gray-400">0 / 5000 · your draft is kept on this device</span>
                        <button type="button" id="muSend" class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-amber-700 rounded-xl hover:bg-amber-800 shadow-sm"><i class="fa-solid fa-paper-plane"></i> Send to HR</button>
                    </div>
                    <div id="muConfirm"></div>`;
                const ta = F('muText');
                try { ta.value = localStorage.getItem(key) || ''; } catch (e) { }
                const len = () => F('muLen').textContent = `${ta.value.length} / 5000 · your draft is kept on this device`;
                len();
                ta.addEventListener('input', () => { len(); try { localStorage.setItem(key, ta.value); } catch (e) { } });
                F('muSend').addEventListener('click', () => {
                    if (ta.value.trim().length < 10) return toast('Please write your explanation first.', 'error');
                    F('muConfirm').innerHTML = `<div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-3 text-xs text-amber-900">Send this response to HR? You can't change it after sending.
                        <div class="flex gap-2 mt-2"><button type="button" id="muYes" class="px-3 py-2 font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800"><i class="fa-solid fa-check mr-1"></i>Yes, send</button>
                        <button type="button" id="muNo" class="px-3 py-2 font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg">Cancel</button></div></div>`;
                    F('muNo').addEventListener('click', () => F('muConfirm').innerHTML = '');
                    F('muYes').addEventListener('click', async function () {
                        this.disabled = true;
                        try {
                            const r = await api('respond', { id: m.id, response: ta.value.trim() }, true);
                            try { localStorage.removeItem(key); } catch (e) { }
                            toast(r.message);
                            const row = rows.find(x => x.id == m.id); if (row) row.response_at = new Date().toISOString();
                            openMemo(m.id);
                        } catch (e) { toast(e.message, 'error'); this.disabled = false; }
                    });
                });
            }

            async function load() {
                try {
                    const d = await api('list'); rows = d.rows; renderList();
                    const q = new URLSearchParams(location.search).get('memo');
                    if (q && rows.some(r => r.id == q)) openMemo(+q);
                    else if (!curId) renderEmpty();
                } catch (e) { F('muList').innerHTML = `<p class="px-3 py-10 text-center text-xs text-red-600">${esc(e.message)}</p>`; renderEmpty(); }
            }
            F('muList').addEventListener('click', e => {
                const b = e.target.closest('[data-id]'); if (!b) return;
                openMemo(+b.dataset.id);
                if (window.innerWidth < 1024) F('muView').scrollIntoView({ behavior: 'smooth' });
            });
            setInterval(async () => { if (document.hidden) return; try { const d = await api('list'); if (JSON.stringify(d.rows.map(r => r.id)) !== JSON.stringify(rows.map(r => r.id))) { rows = d.rows; renderList(); } } catch (e) { } }, 30000);
            load();
        })();
    </script>
</body>

</html>