<?php
// superadmin-labor.php  —  Superadmin: Labor Quotation approvals (Step 3)
// Pinipirmahan dito (Conforme) ang labor-only quotation ng PC. Mapi-print lang ng PC kapag approved na.

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SUPERADMIN];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$slAjaxUrl = BASE_URL . '/superadminlaborajax';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Labor Quotation Approvals</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <style>
        /* ── Labor quotation document ── */
        .lqdoc { font-family: Calibri, Arial, Helvetica, sans-serif; color: #111; font-size: 11.5px; line-height: 1.35; }
        .lqdoc table { border-collapse: collapse; width: 100%; table-layout: fixed; }
        .lqdoc td { padding: 2px 5px; vertical-align: top; word-wrap: break-word; }
        .lqdoc .c { text-align: center; } .lqdoc .r { text-align: right; } .lqdoc .b { font-weight: 700; }
        .lqdoc p { margin: 0 0 3px; }
        .lqdoc .meta td { padding: 1px 4px; font-size: 12px; }
        .lqdoc .qno { text-align: right; font-size: 10px; color: #555; margin-top: -14px; }
        .lqdoc .ttl { text-align: center; font-weight: 700; font-size: 15px; padding: 6px 0 4px; border-bottom: 2px solid #111; margin-top: 6px; }
        .lqdoc .tbl td { border: 1px solid #555; }
        .lqdoc .tbl .th td { background: #2563eb; color: #fff; font-weight: 700; text-align: center; vertical-align: middle; }
        .lqdoc .tbl .sec td { background: #e5e7eb; }
        .lqdoc .tbl .tot td { background: #7dd3fc; }
        .lqdoc .terms { margin-top: 10px; font-size: 11px; }
        .lqdoc .sign { margin-top: 24px; }
        .lqdoc .sign td { width: 50%; padding: 0 24px 0 0; }
        .lqdoc .sign .lbl { margin-bottom: 0; }
        .lqdoc .sign .sig { height: 50px; display: flex; align-items: flex-end; justify-content: center; }
        .lqdoc .sign .sig img { max-height: 50px; max-width: 180px; object-fit: contain; }
        .lqdoc .sign .ln { border-top: 1px solid #111; margin: 0 20px; padding-top: 2px; text-align: center; font-weight: 700; min-height: 18px; }
        .lqdoc .sign .role { text-align: center; font-size: 10.5px; }
        .lqdoc .sign .here { border: 2px dashed #f59e0b; border-radius: 6px; background: #fffbeb; color: #b45309; font-size: 10px; font-weight: 700; padding: 4px 8px; }
        .sa-scroll::-webkit-scrollbar { width: 6px; height: 6px; } .sa-scroll::-webkit-scrollbar-thumb { background: #d1d5db; border-radius: 9px; }
        [data-picked="1"] { box-shadow: inset 3px 0 0 #b45309, 0 0 0 2px #b45309; }
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
                <div class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-helmet-safety text-lg"></i></div>
                <div class="min-w-0">
                    <h1 class="text-xl font-bold text-gray-900 leading-tight">Labor Quotation Approvals</h1>
                    <p class="text-xs text-gray-500">Sign (Conforme) the labor-only quotations from the Project Coordinator. They can print once you approve.</p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1.5 text-[11px] text-gray-500 bg-white border border-gray-200 rounded-full px-3 py-1.5">
                <span id="slLiveDot" class="w-2 h-2 rounded-full bg-amber-400"></span><span id="slLiveText">Auto-refresh</span><span class="text-gray-300">·</span><span id="slUpdated">Loading…</span>
            </span>
        </div>

        <!-- ═══ STAT TABS ═══ -->
        <div id="slTabs" class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <?php foreach ([
                ['pending', 'For signing', 'fa-signature', 'bg-amber-50 text-amber-700'],
                ['returned', 'Returned to PC', 'fa-rotate-left', 'bg-red-50 text-red-600'],
                ['approved', 'Approved', 'fa-circle-check', 'bg-emerald-50 text-emerald-600'],
                ['all', 'All quotations', 'fa-layer-group', 'bg-gray-100 text-gray-600'],
            ] as [$k, $label, $icon, $cls]): ?>
                <button type="button" data-tab="<?= $k ?>"
                    class="sl-tab text-left bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3 shadow-sm min-w-0 transition hover:border-amber-400 data-[active=true]:border-amber-600 data-[active=true]:ring-2 data-[active=true]:ring-amber-100 data-[active=true]:bg-amber-50/40">
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
                    <input type="text" id="slSearch" placeholder="Search quotation no., project, subcontractor, foreman, DR…"
                        class="w-full h-9 bg-white border border-gray-200 rounded-lg pl-9 pr-3 text-xs outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100">
                </div>
            </div>
            <div id="slInfo" class="px-4 py-2 text-[11px] text-gray-500 bg-gray-50 border-b border-gray-100">&nbsp;</div>
            <div id="slList" class="divide-y divide-gray-100">
                <div class="px-6 py-14 text-center text-xs text-gray-400"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Loading…</div>
            </div>
        </div>

        <div id="slToast" class="fixed bottom-4 right-4 z-[10000] flex flex-col gap-2.5 pointer-events-none w-[340px] max-w-[calc(100vw-2rem)]"></div>
    </main>

    <!-- ═══ REVIEW MODAL ═══ -->
    <div id="slModal" class="hidden fixed inset-0 z-[9999] items-start justify-center bg-black/50 p-3 md:p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-6xl my-auto overflow-hidden">
            <div class="flex items-start justify-between gap-3 px-5 py-4 border-b border-gray-100">
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-amber-700">Labor quotation (labor only)</p>
                    <p id="slMTitle" class="text-base font-semibold text-gray-900 truncate"></p>
                    <p id="slMSub" class="text-[11px] text-gray-400 truncate"></p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" id="slPrint" class="hidden inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-gray-900 rounded-lg hover:bg-gray-800"><i class="fa-solid fa-print"></i> Print</button>
                    <button type="button" data-close class="w-8 h-8 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 text-xl leading-none">&times;</button>
                </div>
            </div>
            <div class="grid lg:grid-cols-[minmax(0,1fr)_340px]">
                <div class="p-4 md:p-5 bg-slate-50 min-w-0 lg:border-r border-gray-100">
                    <div class="overflow-x-auto sa-scroll"><div id="slDoc" class="min-w-[680px] bg-white border border-gray-200 rounded-lg p-6"></div></div>
                </div>
                <div id="slSide" class="p-5 space-y-4"></div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            'use strict';
            const AJAX = <?= json_encode($slAjaxUrl) ?>;
            const F = id => document.getElementById(id);
            let rows = [], tab = 'pending', me = '', mySignature = null, cur = null, curForeman = null, lastSig = '';

            const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
            const toD = s => { const [y, m, d] = String(s).slice(0, 10).split('-').map(Number); return new Date(y, m - 1, d); };
            const fmtY = s => s ? toD(s).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
            const fmtSlash = s => { if (!s) return ''; const [y, m, d] = String(s).slice(0, 10).split('-'); return `${m}/${d}/${y}`; };
            const money = n => (Number(n) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const num = v => Number(String(v ?? '').replace(/,/g, '')) || 0;
            const qtyFmt = q => { const n = parseFloat(q); return isNaN(n) ? '' : String(+n.toFixed(2)); };
            const ago = s => { if (!s) return ''; const days = Math.floor((Date.now() - new Date(String(s).replace(' ', 'T'))) / 864e5); return days <= 0 ? 'today' : days === 1 ? '1 day ago' : `${days} days ago`; };
            const store = {
                get: k => { try { return localStorage.getItem('nh-salq-' + k); } catch (e) { return null; } },
                set: (k, v) => { try { localStorage.setItem('nh-salq-' + k, v); } catch (e) { } },
            };

            function toast(message, type = 'success', duration = 3500) {
                const p = type === 'success'
                    ? { wrap: 'bg-green-50 border-green-200 text-green-700', icon: 'bg-green-200 text-green-700', sym: '✓' }
                    : { wrap: 'bg-red-50 border-red-200 text-red-700', icon: 'bg-red-200 text-red-700', sym: '!' };
                const t = document.createElement('div');
                t.className = `pointer-events-auto flex items-start gap-2.5 border rounded-lg shadow-lg px-4 py-3 text-sm ${p.wrap} transition-all duration-300`;
                t.innerHTML = `<span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold ${p.icon}">${p.sym}</span><span class="flex-1 leading-relaxed"></span>`;
                t.querySelector('.flex-1').textContent = message;
                F('slToast').appendChild(t);
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
                            ? 'The server returned a page instead of data. Check that "superadminlaborajax" is registered in the router, or reload if your session expired.'
                            : 'The server sent an unexpected response.');
                    }
                    if (!d.success) throw new Error(d.message || 'Something went wrong.');
                    return d;
                } catch (e) {
                    throw new Error(e.name === 'AbortError' ? 'The server took too long to respond.' : e.message);
                } finally { clearTimeout(timer); }
            }

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

            // ═════════════ LIST ═════════════
            const ST = {
                pending:  { bar: 'bg-amber-500', pill: 'bg-amber-50 text-amber-700 ring-amber-600/20', icon: 'fa-signature', text: 'For signing' },
                returned: { bar: 'bg-red-500', pill: 'bg-red-50 text-red-700 ring-red-600/20', icon: 'fa-rotate-left', text: 'Returned to PC' },
                approved: { bar: 'bg-emerald-500', pill: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', icon: 'fa-circle-check', text: 'Approved' },
            };
            function renderTabs() {
                const list = k => k === 'all' ? rows : rows.filter(r => r.status === k);
                document.querySelectorAll('.sl-tab').forEach(b => b.dataset.active = String(b.dataset.tab === tab));
                document.querySelectorAll('[data-count]').forEach(el => el.textContent = list(el.dataset.count).length);
                document.querySelectorAll('[data-sum]').forEach(el => { const l = list(el.dataset.sum); el.textContent = l.length ? '₱ ' + money(l.reduce((s, r) => s + num(r.total), 0)) : ' '; });
            }
            function render() {
                renderTabs();
                const q = F('slSearch').value.trim().toLowerCase();
                const list = rows.filter(r => (tab === 'all' || r.status === tab)
                    && (!q || `${r.quote_no} ${r.project_name ?? ''} ${r.location ?? ''} ${r.subcontractor} ${r.foreman_name ?? ''} ${r.dr_no} ${r.ref_no}`.toLowerCase().includes(q)));
                const label = { pending: 'waiting for your signature', returned: 'returned to the PC', approved: 'approved', all: 'in total' }[tab];
                F('slInfo').innerHTML = `<b class="text-gray-700">${list.length}</b> quotation${list.length === 1 ? '' : 's'} ${label}${q ? ' (filtered)' : ''}`;
                if (!list.length) {
                    const msg = q ? 'No match.' : { pending: 'All caught up! No labor quotation is waiting for your signature.', returned: 'No quotation is waiting for the PC to fix.', approved: 'No approved quotation yet.', all: 'No labor quotation yet.' }[tab];
                    F('slList').innerHTML = `<div class="px-6 py-14 text-center"><div class="w-12 h-12 mx-auto rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center mb-3"><i class="fa-solid ${tab === 'pending' && !q ? 'fa-mug-hot' : 'fa-file-circle-question'}"></i></div><p class="text-sm font-semibold text-gray-700">${msg}</p></div>`;
                    return;
                }
                F('slList').innerHTML = list.map(r => {
                    const s = ST[r.status] || ST.pending;
                    const sub = r.status === 'approved' ? `<span class="text-emerald-700"><i class="fa-solid fa-signature mr-1"></i>Signed ${fmtY(r.approved_at)}${r.approved_by ? ' by ' + esc(r.approved_by) : ''}</span>`
                        : r.status === 'returned' ? `<span class="text-red-700"><i class="fa-solid fa-comment-dots mr-1"></i>${esc(r.return_remarks || '')}</span>`
                        : `<span class="text-amber-700"><i class="fa-regular fa-clock mr-1"></i>Submitted ${ago(r.submitted_at)}</span>`;
                    return `<button type="button" data-id="${r.id}" class="sl-row relative w-full text-left flex flex-wrap items-center gap-x-5 gap-y-2 pl-5 pr-4 py-3.5 hover:bg-amber-50/40 transition">
                        <span class="absolute left-0 top-0 bottom-0 w-1 ${s.bar}"></span>
                        <span class="min-w-0 flex-1 basis-[260px]">
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="font-mono text-sm font-bold text-gray-900">${esc(r.quote_no)}</span>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold ring-1 ${s.pill}"><i class="fa-solid ${s.icon} text-[9px]"></i>${s.text}</span>
                                ${r.resubmitted ? '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-purple-50 text-purple-700 ring-1 ring-purple-600/20"><i class="fa-solid fa-rotate text-[9px]"></i>Resubmitted</span>' : ''}
                            </span>
                            <span class="block text-sm font-semibold text-gray-800 truncate mt-0.5">${esc(r.project_name || '—')}${r.location ? ` <span class="font-normal text-gray-400">· ${esc(r.location)}</span>` : ''}</span>
                            <span class="block text-[11px] truncate mt-0.5">${sub}</span>
                        </span>
                        <span class="grid grid-cols-3 gap-x-5 text-[11px] basis-[300px] grow sm:grow-0">
                            <span class="min-w-0"><span class="block text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Subcontractor</span><span class="text-gray-700 truncate block">${esc(r.subcontractor)}</span></span>
                            <span class="min-w-0"><span class="block text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Foreman</span><span class="text-gray-700 truncate block">${esc(r.foreman_name || '—')}</span></span>
                            <span><span class="block text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">DR</span><span class="font-mono text-gray-700">${esc(r.dr_no)}</span></span>
                        </span>
                        <span class="ml-auto shrink-0 flex items-center gap-3">
                            <span class="text-right"><span class="block text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">${r.duration_days ? r.duration_days + ' days' : r.item_count + ' item' + (r.item_count === 1 ? '' : 's')}</span><span class="font-mono text-sm font-bold text-gray-900">₱ ${money(r.total)}</span></span>
                            <span class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg ${r.status === 'pending' ? 'text-white bg-amber-700 shadow-sm' : 'text-gray-600 bg-white border border-gray-200'}">
                                <i class="fa-solid ${r.status === 'pending' ? 'fa-pen-nib' : 'fa-eye'}"></i> ${r.status === 'pending' ? 'Review & sign' : 'View'}</span>
                        </span>
                    </button>`;
                }).join('');
                markPicked();
            }
            function markPicked() {
                let id = null; try { id = sessionStorage.getItem('nh-salq-picked'); } catch (e) { }
                document.querySelectorAll('.sl-row').forEach(el => el.setAttribute('data-picked', el.dataset.id === id ? '1' : '0'));
            }
            document.querySelectorAll('.sl-tab').forEach(b => b.addEventListener('click', () => { tab = b.dataset.tab; store.set('tab', tab); render(); }));
            F('slSearch').addEventListener('input', render);
            F('slList').addEventListener('click', e => {
                const b = e.target.closest('.sl-row'); if (!b) return;
                try { sessionStorage.setItem('nh-salq-picked', b.dataset.id); } catch (err) { }
                markPicked(); openReview(+b.dataset.id);
            });

            async function load(silent) {
                try {
                    const d = await api('list');
                    me = d.me || ''; mySignature = d.my_signature || null;
                    F('slUpdated').textContent = 'Updated ' + new Date().toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' });
                    const sig = JSON.stringify(d.rows.map(r => [r.id, r.status, r.submitted_at]));
                    if (silent && sig === lastSig) return;
                    if (silent && lastSig) {
                        const known = new Set(rows.filter(r => r.status === 'pending').map(r => r.id));
                        const fresh = d.rows.filter(r => r.status === 'pending' && !known.has(r.id)).length;
                        if (fresh) toast(`${fresh} new labor quotation${fresh === 1 ? '' : 's'} waiting for your signature.`);
                    }
                    lastSig = sig; rows = d.rows; render();
                } catch (e) {
                    if (silent) return;
                    F('slList').innerHTML = `<div class="px-6 py-12 text-center text-xs text-red-600">${esc(e.message)}<br><button type="button" id="slRetry" class="mt-2 font-semibold text-amber-700 hover:underline">Retry</button></div>`;
                    F('slRetry').addEventListener('click', () => load());
                }
            }

            // ═════════════ REVIEW MODAL ═════════════
            const openM = () => { F('slModal').classList.remove('hidden'); F('slModal').classList.add('flex'); };
            const closeM = () => { F('slModal').classList.add('hidden'); F('slModal').classList.remove('flex'); cur = null; };
            F('slModal').querySelector('[data-close]').addEventListener('click', closeM);
            F('slModal').addEventListener('click', e => { if (e.target === F('slModal')) closeM(); });
            document.addEventListener('keydown', e => { if (e.key === 'Escape' && !F('slModal').classList.contains('hidden')) closeM(); });

            // quiet = realtime refresh: walang error toast, at hindi na bubuksan kapag naisara / may ginagawa habang naglo-load
            async function openReview(id, quiet) {
                let d;
                try { d = await api('quote', { id }); } catch (e) { return quiet ? null : toast(e.message, 'error'); }
                if (quiet && (!cur || cur.id !== id || F('slModal').classList.contains('hidden') || rtBusy())) return;
                cur = d.quote; curForeman = d.foreman;
                F('slMTitle').textContent = `${cur.quote_no} · ${cur.project_name || ''}`;
                F('slMSub').textContent = `DR# ${cur.dr_no} · Transmittal ${cur.ref_no} · prepared by ${cur.prepared_by || '—'}`;
                F('slPrint').classList.toggle('hidden', cur.status !== 'approved');
                const pending = cur.status === 'pending';
                F('slDoc').innerHTML = lqDoc(cur, pending ? (mySignature ? { preview: mySignature, previewName: me } : { signHere: true }) : {});
                renderSide();
                openM();
            }

            function foremanCard() {
                const f = curForeman;
                if (!f) return '<div class="rounded-xl border border-gray-200 p-3 text-[11px] text-gray-400">No foreman on file.</div>';
                return `<div class="rounded-xl border border-gray-200 p-3">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-2"><i class="fa-solid fa-id-badge mr-1 text-amber-700"></i>Foreman</p>
                    <div class="flex gap-3">
                        <a href="${esc(f.id_photo_url)}" target="_blank" rel="noopener" class="block w-24 h-16 rounded-lg overflow-hidden border border-gray-200 bg-gray-50 shrink-0" title="Open ID photo"><img src="${esc(f.id_photo_url)}" alt="Foreman ID" class="w-full h-full object-cover"></a>
                        <div class="min-w-0 text-[11px]">
                            <p class="text-sm font-semibold text-gray-900 truncate">${esc(f.name)}</p>
                            <p class="text-gray-500">${esc(f.contact_no || '—')}</p>
                            <p class="text-gray-500 truncate">${esc([f.id_type, f.id_number].filter(Boolean).join(' · ') || 'ID details not given')}</p>
                        </div>
                    </div>
                    <a href="${esc(f.id_photo_url)}" target="_blank" rel="noopener" class="inline-block mt-2 text-[11px] font-semibold text-amber-700 hover:underline">Open ID photo <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i></a>
                </div>`;
            }

            function renderSide() {
                const side = F('slSide');
                const facts = `<dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-[11px] bg-gray-50 border border-gray-100 rounded-xl p-3">
                    <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Date</dt><dd class="text-gray-800">${fmtY(cur.quote_date)}</dd></div>
                    <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Duration</dt><dd class="text-gray-800">${cur.duration_days ? cur.duration_days + ' calendar days' : '—'}</dd></div>
                    <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Down payment</dt><dd class="text-gray-800">${cur.downpayment_pct !== null ? +num(cur.downpayment_pct).toFixed(2) + '%' : '—'}</dd></div>
                    <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Total</dt><dd class="font-mono font-bold text-gray-900">₱ ${money(lqTotal(cur.items))}</dd></div>
                    <div class="col-span-2"><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Subcontractor</dt><dd class="text-gray-800">${esc(cur.subcontractor)}</dd></div>
                </dl>`;
                if (cur.status === 'approved') {
                    side.innerHTML = `<div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-xs text-emerald-800"><p class="font-bold text-sm mb-1"><i class="fa-solid fa-circle-check mr-1"></i>Approved</p>
                        <p>Signed by <b>${esc(cur.approved_by || '')}</b> on ${fmtY(cur.approved_at)}. The PC can now print this quotation.</p></div>${facts}${foremanCard()}`;
                    return;
                }
                if (cur.status === 'returned') {
                    side.innerHTML = `<div class="rounded-xl border border-red-200 bg-red-50 p-4 text-xs text-red-800"><p class="font-bold text-sm mb-1"><i class="fa-solid fa-rotate-left mr-1"></i>Returned to the PC</p>
                        <p class="mb-1">${esc(cur.return_remarks || '')}</p><p class="text-red-600">${esc(cur.returned_by || '')}${cur.returned_at ? ' · ' + fmtY(cur.returned_at) : ''}</p>
                        <p class="mt-2 text-red-700">It will come back here once the PC fixes and resubmits it.</p></div>${facts}${foremanCard()}`;
                    return;
                }
                side.innerHTML = `
                    ${cur.return_remarks ? `<div class="rounded-xl border border-purple-200 bg-purple-50 px-3.5 py-2.5 text-[11px] text-purple-800"><p class="font-bold"><i class="fa-solid fa-rotate mr-1"></i>Resubmitted after your remarks:</p><p class="mt-0.5">${esc(cur.return_remarks)}</p></div>` : ''}
                    ${facts}${foremanCard()}
                    <section>
                        <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500 mb-2"><i class="fa-solid fa-signature mr-1 text-amber-700"></i>Your signature (Conforme)</p>
                        ${mySignature
                            ? `<div class="rounded-xl border border-gray-200 bg-white p-3 flex items-center justify-center h-24"><img src="${esc(mySignature)}" alt="Your signature" class="max-h-full max-w-full object-contain"></div>
                               <p class="text-[11px] text-gray-500 mt-2">Signing as <b class="text-gray-800">${esc(me || 'Superadmin')}</b>.</p>`
                            : `<div class="rounded-xl border border-amber-200 bg-amber-50 p-3.5 text-xs text-amber-900"><p class="font-bold mb-0.5"><i class="fa-solid fa-triangle-exclamation mr-1"></i>No active signature yet</p>
                                <p>Add your signature first, then come back to approve.</p>
                                <a href="<?= BASE_URL ?>/signaturedinsert" class="inline-flex items-center gap-1 mt-2 font-semibold text-amber-800 hover:underline">Go to Insert Signature <i class="fa-solid fa-arrow-right text-[10px]"></i></a></div>`}
                    </section>
                    <p id="slErr" class="hidden rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-[11px] text-red-700"></p>
                    <div id="slApproveArea"></div>
                    <details id="slReturnBox" class="group rounded-xl border border-gray-200">
                        <summary class="list-none cursor-pointer flex items-center justify-between px-3.5 py-2.5 text-xs font-semibold text-red-700"><span><i class="fa-solid fa-rotate-left mr-1"></i>Return to PC for changes</span><i class="fa-solid fa-chevron-down text-[10px] text-gray-400 group-open:rotate-180 transition"></i></summary>
                        <div class="px-3.5 pb-3.5">
                            <textarea id="slRemarks" rows="3" maxlength="1000" placeholder="What should the PC fix? e.g. Unit cost of Drawer should be 450."
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs outline-none focus:border-red-400 focus:ring-2 focus:ring-red-100 resize-y"></textarea>
                            <p id="slRemErr" class="hidden text-[11px] text-red-600 mt-1"></p>
                            <button type="button" id="slReturn" class="mt-2 w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 disabled:opacity-60"><i class="fa-solid fa-paper-plane"></i> Send back to PC</button>
                        </div>
                    </details>`;

                const err = m => { F('slErr').textContent = m || ''; F('slErr').classList.toggle('hidden', !m); };
                function approveBtn() {
                    F('slApproveArea').innerHTML = `<button type="button" id="slApprove" ${mySignature ? '' : 'disabled'} class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-amber-700 rounded-xl hover:bg-amber-800 shadow-sm disabled:bg-gray-200 disabled:text-gray-400 disabled:shadow-none disabled:cursor-not-allowed"><i class="fa-solid fa-file-signature"></i> Approve &amp; sign</button>`;
                    F('slApprove').addEventListener('click', askApprove);
                }
                function askApprove() {
                    err('');
                    if (!mySignature) return err('Add your signature in Insert Signature first.');
                    F('slApproveArea').innerHTML = `<div class="rounded-xl border border-amber-200 bg-amber-50 p-3">
                        <p class="text-xs text-amber-900 mb-2">Approve and sign <b>${esc(cur.quote_no)}</b> (₱ ${money(lqTotal(cur.items))})? The PC will be able to print it, and it can no longer be edited.</p>
                        <div class="flex gap-2"><button type="button" id="slYes" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:opacity-60"><i class="fa-solid fa-check"></i> Yes, approve</button>
                        <button type="button" id="slNo" class="px-3 py-2 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button></div></div>`;
                    F('slNo').addEventListener('click', approveBtn);
                    F('slYes').addEventListener('click', async function () {
                        this.disabled = true; this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Signing…';
                        try {
                            const d = await api('approve', { id: cur.id }, true);
                            toast(`${d.quote_no} approved and signed.`);
                            const id = cur.id; await load(); openReview(id);
                        } catch (e) { approveBtn(); err(e.message); }
                    });
                }
                approveBtn();

                const remKey = 'rem-' + cur.id;
                F('slRemarks').value = store.get(remKey) || '';
                if (F('slRemarks').value) F('slReturnBox').open = true;
                F('slRemarks').addEventListener('input', () => { store.set(remKey, F('slRemarks').value); F('slRemErr').classList.add('hidden'); F('slRemarks').removeAttribute('aria-invalid'); });
                F('slReturn').addEventListener('click', async function () {
                    const rem = F('slRemarks').value.trim();
                    if (rem.length < 3) { F('slRemErr').textContent = 'Tell the PC what to fix.'; F('slRemErr').classList.remove('hidden'); F('slRemarks').setAttribute('aria-invalid', 'true'); F('slRemarks').focus(); return; }
                    this.disabled = true;
                    try {
                        await api('return', { id: cur.id, remarks: rem }, true);
                        store.set(remKey, '');
                        toast(`${cur.quote_no} returned to the PC.`);
                        closeM(); load();
                    } catch (e) { F('slRemErr').textContent = e.message; F('slRemErr').classList.remove('hidden'); }
                    finally { this.disabled = false; }
                });
            }

            F('slPrint').addEventListener('click', () => {
                if (!cur || cur.status !== 'approved') return;
                let area = F('nhPrintArea');
                if (!area) { area = document.createElement('div'); area.id = 'nhPrintArea'; document.body.appendChild(area); }
                area.innerHTML = lqDoc(cur);
                const imgs = [...area.querySelectorAll('img')].filter(i => !i.complete);
                Promise.all(imgs.map(i => new Promise(r => { i.onload = i.onerror = r; }))).then(() => { window.print(); setTimeout(() => { area.innerHTML = ''; }, 500); });
            });

            // ═════════════ INIT ═════════════
            tab = store.get('tab') || 'pending';
            if (!['pending', 'returned', 'approved', 'all'].includes(tab)) tab = 'pending';
            load();

            // ═════════════ REALTIME (koneksyon ng sidebar: window.NH_RT) ═════════════
            // Bagong ipinasa / inayos ng PC (o pinirmahan sa ibang tab) → kunin agad ang listahan.
            // Ang bukas na papel ay nire-refresh din, pero HINDI habang may kumpirmasyon o sinusulat na remarks — hahabol pagkatapos.
            const RT_TYPES = /^(pc_labor|pc_foreman|labor_)/i;
            const rtLive = () => !!(window.NH_RT && window.NH_RT.enabled && window.NH_RT.isLive());
            const rtBusy = () => !!cur && !!(F('slYes') || F('slReturnBox')?.open || document.activeElement === F('slRemarks'));
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
                if (!cur || F('slModal').classList.contains('hidden')) return;
                const keep = F('slModal').scrollTop;
                openReview(cur.id, true).then(() => { F('slModal').scrollTop = keep; });
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
                        F('slLiveDot').className = 'w-2 h-2 rounded-full ' + (live ? 'bg-green-500 animate-pulse' : 'bg-amber-400');
                        F('slLiveText').textContent = live ? 'Live' : 'Auto-refresh';
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