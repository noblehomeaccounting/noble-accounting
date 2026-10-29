<?php
// superadmin-billing.php  —  Superadmin: Progress Billing approvals (Application for Payment)
// Daloy: PC → Accounting Head (Noted by) → Superadmin (Approved by)

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SUPERADMIN];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$apAjaxUrl = BASE_URL . '/superadminbillingajax';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing Approvals</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <style>
        .dr-hazard { background: repeating-linear-gradient(-45deg, #f59e0b 0 12px, #1f2937 12px 24px); }
        [data-picked="1"] { box-shadow: 0 0 0 2px #b45309; }
        /* ── Progress Billing paper (kapareho ng Excel) ── */
        .bpdoc { font-family: Calibri, Arial, Helvetica, sans-serif; color: #111; font-size: 10.5px; line-height: 1.3; width: 980px; }
        .bpdoc table { border-collapse: collapse; width: 100%; table-layout: fixed; }
        .bpdoc td { padding: 2px 6px; vertical-align: middle; word-wrap: break-word; }
        .bpdoc .r { text-align: right; } .bpdoc .c { text-align: center; } .bpdoc .b { font-weight: 700; }
        .bpdoc .hdr { border: 2px solid #111; }
        .bpdoc .hdr .co { font-size: 20px; font-weight: 800; color: #e8650a; letter-spacing: .02em; }
        .bpdoc .hdr .ad { font-size: 9px; color: #333; }
        .bpdoc .band { background: #1f2937; color: #fff; font-weight: 800; font-size: 13px; letter-spacing: .04em; padding: 5px 8px; }
        .bpdoc .sec { background: #111; color: #fff; font-weight: 800; font-size: 10px; letter-spacing: .06em; padding: 3px 6px; margin-top: 8px; }
        .bpdoc .sec.red { background: #b91c1c; }
        .bpdoc .info td { border: 1px solid #9ca3af; height: 18px; }
        .bpdoc .info .k { font-weight: 700; background: #f3f4f6; width: 15%; }
        .bpdoc .sum td { border-bottom: 1px solid #e5e7eb; height: 17px; }
        .bpdoc .sum .v { border-left: 1px solid #9ca3af; font-family: Consolas, monospace; text-align: right; width: 22%; }
        .bpdoc .sum .due td { background: #e8650a; color: #fff; font-weight: 800; }
        .bpdoc .sum .due .v { background: #b45309; }
        .bpdoc .sov td { border: 1px solid #9ca3af; height: 24px; font-size: 10px; }
        .bpdoc .sov .th td { background: #b91c1c; color: #fff; font-weight: 700; text-align: center; font-size: 9px; line-height: 1.15; height: 34px; }
        .bpdoc .sov .m { font-family: Consolas, monospace; text-align: right; }
        .bpdoc .sov .cur td { background: #fde047; font-weight: 700; }
        .bpdoc .sov .tot td { background: #f3f4f6; font-weight: 800; }
        .bpdoc .legend { font-size: 8.5px; color: #555; margin-top: 3px; }
        .bpdoc .cert { font-size: 9.5px; margin-top: 4px; }
        .bpdoc .sign { margin-top: 18px; }
        .bpdoc .sign td { text-align: center; padding: 0 18px; vertical-align: bottom; }
        .bpdoc .sign .sig { height: 52px; display: flex; align-items: flex-end; justify-content: center; }
        .bpdoc .sign .sig img { max-height: 52px; max-width: 190px; object-fit: contain; }
        .bpdoc .sign .nm { border-top: 1.5px solid #111; padding-top: 2px; font-weight: 800; text-transform: uppercase; min-height: 18px; }
        .bpdoc .sign .rl { font-size: 9px; color: #444; }
        .bpdoc .sign .wait { font-size: 9px; color: #b45309; font-weight: 700; }
        #nhPrintArea { display: none; }
        @media print {
            @page { size: A4 landscape; margin: 8mm; }
            body > *:not(#nhPrintArea) { display: none !important; }
            #nhPrintArea { display: block !important; }
            #nhPrintArea .bpdoc { zoom: 1.08; }
            #nhPrintArea, #nhPrintArea * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>

<body class="bg-gray-50">
    <main id="main-content" class="md:ml-56 pt-20 md:pt-5 min-h-screen p-4 md:p-8 transition-all duration-300">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-file-signature text-lg"></i></div>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-amber-700">Superadmin · Approved by</p>
                    <h1 class="text-xl font-bold text-gray-900 leading-tight">Progress Billing Approvals</h1>
                    <p class="text-xs text-gray-500">Approve and sign the progress billings already noted by the Accounting Head. The PC can print and bill again after you approve.</p>
                </div>
            </div>
            <span class="inline-flex items-center gap-1.5 text-[11px] text-gray-500 bg-white border border-gray-200 rounded-full px-3 py-1.5"><span id="apLiveDot" class="w-2 h-2 rounded-full bg-amber-400"></span><span id="apLiveText">Auto-refresh</span><span class="text-gray-300">·</span><span id="apUpdated">Loading…</span></span>
        </div>
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <?php foreach ([
                ['mine', 'For your signature', 'fa-signature', 'bg-amber-50 text-amber-700'],
                ['returned', 'Returned to PC', 'fa-rotate-left', 'bg-red-50 text-red-600'],
                ['approved', 'Approved', 'fa-circle-check', 'bg-emerald-50 text-emerald-600'],
                ['all', 'All billings', 'fa-layer-group', 'bg-gray-100 text-gray-600'],
            ] as [$k, $label, $icon, $cls]): ?>
                <button type="button" data-tab="<?= $k ?>"
                    class="ap-tab text-left bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3 shadow-sm min-w-0 transition hover:border-amber-400 data-[active=true]:border-amber-600 data-[active=true]:ring-2 data-[active=true]:ring-amber-100 data-[active=true]:bg-amber-50/40">
                    <span class="hidden sm:flex w-10 h-10 rounded-lg items-center justify-center shrink-0 <?= $cls ?>"><i class="fa-solid <?= $icon ?>"></i></span>
                    <span class="min-w-0"><span class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide truncate"><?= $label ?></span>
                        <span class="block text-2xl font-bold text-gray-900 leading-tight tabular-nums" data-count="<?= $k ?>">—</span>
                        <span class="block text-[11px] text-gray-400 truncate font-mono" data-sum="<?= $k ?>">&nbsp;</span></span>
                </button>
            <?php endforeach; ?>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100"><div class="relative"><i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                <input type="text" id="apSearch" placeholder="Search application no., project, subcontractor…" class="w-full h-9 bg-white border border-gray-200 rounded-lg pl-9 pr-3 text-xs outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100"></div></div>
            <div id="apInfo" class="px-4 py-2 text-[11px] text-gray-500 bg-gray-50 border-b border-gray-100">&nbsp;</div>
            <div id="apList" class="divide-y divide-gray-100"><div class="px-6 py-14 text-center text-xs text-gray-400"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Loading…</div></div>
        </div>
        <div id="bpToast" class="fixed bottom-4 right-4 z-[10002] flex flex-col gap-2.5 pointer-events-none w-[340px] max-w-[calc(100vw-2rem)]"></div>
    </main>

    <div id="apModal" class="hidden fixed inset-0 z-[9999] items-start justify-center bg-black/50 p-2 md:p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-7xl my-auto overflow-hidden">
            <div class="flex items-start justify-between gap-3 px-5 py-4 border-b border-gray-100">
                <div class="min-w-0"><p class="text-[10px] font-bold uppercase tracking-[0.15em] text-amber-700">Application for Payment</p>
                    <p id="amTitle" class="text-base font-semibold text-gray-900 truncate"></p><p id="amSub" class="text-[11px] text-gray-400 truncate"></p></div>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" id="amPrint" class="hidden inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-gray-900 rounded-lg hover:bg-gray-800"><i class="fa-solid fa-print"></i> Print</button>
                    <button type="button" id="amClose" class="w-8 h-8 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 text-xl leading-none">&times;</button>
                </div>
            </div>
            <div class="grid lg:grid-cols-[minmax(0,1fr)_330px]">
                <div class="p-4 bg-slate-50 min-w-0 lg:border-r border-gray-100"><div id="amDoc" class="bg-white border border-gray-200 rounded-lg p-4 overflow-hidden"></div></div>
                <div id="amSide" class="p-5 space-y-4"></div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            'use strict';
            const AJAX = <?= json_encode($apAjaxUrl) ?>, AJAX_NAME = 'superadminbillingajax', MEMO = 'nh-apsa-';
            const STAGE = 'sa', MY_STATUS = STAGE === 'head' ? 'pending_head' : 'pending_sa';
            const LOGO = <?= json_encode(BASE_URL . '/icon/logo.webp') ?>;
            const F = id => document.getElementById(id);
            let rows = [], tab = 'mine', lastSig = '', me = '', mySig = null;
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

            // ═════════════ PROGRESS BILLING: compute + papel (shared) ═════════════
            const bpLabel = a => a.billing_type === 'dp' ? 'DOWN PAYMENT' : `APPLICATION FOR PAYMENT ${a.app_no}`;
            const r2 = n => Math.round((+n || 0) * 100) / 100;
            // compute ng lahat ng row hanggang sa papel na k
            function bpCompute(apps, k) {
                const rows = []; let prev = 0;
                for (let i = 0; i <= k && i < apps.length; i++) {
                    const a = apps[i], A = r2(+a.contract_amount + +a.change_orders), C = r2(a.this_period), B = r2(prev), D = r2(B + C);
                    rows.push({ a, A, B, C, D, pct: A ? D / A * 100 : 0, bal: r2(A - D), ret: r2(D * (+a.retainage_rate || 0) / 100) });
                    prev = D;
                }
                const cur = rows[rows.length - 1], a = cur.a, rate = +a.retainage_rate || 0;
                const L1 = r2(a.contract_amount), L2 = r2(a.change_orders), L3 = r2(L1 + L2), L4 = cur.D, L5b = r2(L4 * rate / 100), L6 = r2(L4 - L5b);
                const L7 = r2(cur.B - r2(cur.B * rate / 100)), L8 = r2(L6 - L7), L9 = r2(L3 - L6);
                return { rows, cur, sum: { L1, L2, L3, L4, rate, L5b, L6, L7, L8, L9 } };
            }
            // papel ng isang billing (k = index sa apps)
            function bpPaper(p, apps, k, opts = {}) {
                const { rows, cur, sum } = bpCompute(apps, k), a = cur.a;
                const m = v => money(v);
                const per = a.period_from || a.period_to ? `${a.period_from ? fmtY(a.period_from) : ''} – ${a.period_to ? fmtY(a.period_to) : ''}` : '';
                const signed = { head: !!a.head_at, sa: a.status === 'approved' };
                const sig = (url, name, role, ok, waitTxt) => `<td><div class="sig">${ok && url ? `<img src="${esc(url)}" alt="">` : ''}</div><div class="nm">${esc(ok ? (name || '') : (name || ''))}&nbsp;</div><div class="rl">${role}</div>${!ok && opts.showWait ? `<div class="wait">${waitTxt}</div>` : ''}</td>`;
                return `<div class="bpdoc">
                    <table class="hdr"><tr>
                        <td style="width:110px;text-align:center;border-right:2px solid #111"><img src="${LOGO}" alt="" style="height:64px"></td>
                        <td><div class="co">NOBLEHOME CONSTRUCTION CORPORATION</div><div class="ad">1181 MC Premier, Brgy. Balingasa, EDSA Balintawak, Quezon City · Tel: (02) 8822-1295 · (+63) 993-548-7799</div></td>
                    </tr></table>
                    <div class="band">PROGRESS BILLING / APPLICATION FOR PAYMENT</div>

                    <div class="sec">PROJECT INFORMATION</div>
                    <table class="info"><tr><td class="k">Project Name:</td><td>${esc(p.project_name || p.tr_project || '')}</td><td class="k">Application No.:</td><td class="b">${esc(a.ref_no || '(new)')}</td></tr>
                        <tr><td class="k">Project Location:</td><td>${esc(p.location || '')}</td><td class="k">Billing Period:</td><td>${esc(per)}</td></tr>
                        <tr><td class="k">Owner/Client:</td><td>${esc(p.owner || '')}</td><td class="k">Contract Date:</td><td>${esc(p.contract_date ? fmtY(p.contract_date) : '')}</td></tr>
                        <tr><td class="k">Subcontractor:</td><td>${esc(p.subcontractor || '')}</td><td class="k">Billing Date:</td><td>${esc(a.billing_date ? fmtY(a.billing_date) : '')}</td></tr></table>

                    <div class="sec">CONTRACT SUMMARY</div>
                    <table class="sum">
                        <tr><td>1. Original Contract Amount</td><td class="v">${m(sum.L1)}</td></tr>
                        <tr><td>2. Net Change by Approved Change Orders</td><td class="v">${m(sum.L2)}</td></tr>
                        <tr><td class="b">3. Contract Sum to Date (Line 1 + Line 2)</td><td class="v b">${m(sum.L3)}</td></tr>
                        <tr><td>4. Total Completed &amp; Stored to Date (from Schedule of Values, Total column)</td><td class="v">${m(sum.L4)}</td></tr>
                        <tr><td>5a. Retainage Rate (%)</td><td class="v">${(+sum.rate).toFixed(2)}%</td></tr>
                        <tr><td>5b. Retainage Amount (Line 4 × Line 5a)</td><td class="v">${m(sum.L5b)}</td></tr>
                        <tr><td>6. Total Earned Less Retainage (Line 4 − Line 5b)</td><td class="v">${m(sum.L6)}</td></tr>
                        <tr><td>7. Less Previous Certificates for Payment (from Schedule of Values, Previous Billed column)</td><td class="v">${m(sum.L7)}</td></tr>
                        <tr class="due"><td>8. CURRENT PAYMENT DUE (Line 6 − Line 7)</td><td class="v">${m(sum.L8)}</td></tr>
                        <tr><td>9. Balance to Finish, Including Retainage (Line 3 − Line 6)</td><td class="v">${m(sum.L9)}</td></tr>
                    </table>

                    <div class="sec red">SCHEDULE OF VALUES — PREVIOUS BILLING vs. CURRENT BILLING</div>
                    <table class="sov"><colgroup><col style="width:5%"><col style="width:17%"><col style="width:11%"><col style="width:10%"><col style="width:10%"><col style="width:9%"><col style="width:11%"><col style="width:7%"><col style="width:10%"><col style="width:10%"></colgroup>
                        <tr class="th"><td>Item No.</td><td>Description of Work</td><td>Scheduled Value (A)</td><td>Previous Billed (Work Completed to Date, B)</td><td>This Period (Current Billing Today, C)</td><td>Date</td><td>Total Completed &amp; Stored to Date (D=B+C)</td><td>% Complete (D/A)</td><td>Balance to Finish (A−D)</td><td>Retainage (D × Rate)</td></tr>
                        ${rows.map((r, i) => `<tr class="${i === rows.length - 1 ? 'cur' : ''}"><td class="c">${i + 1}</td><td>${esc(bpLabel(r.a))}</td><td class="m">${m(r.A)}</td><td class="m">${m(r.B)}</td><td class="m">${m(r.C)}</td><td class="c">${esc(fmtMDY(r.a.billing_date))}</td><td class="m b">${m(r.D)}</td><td class="c">${r.pct.toFixed(1)}%</td><td class="m">${m(r.bal)}</td><td class="m">${m(r.ret)}</td></tr>`).join('')}
                    </table>
                    <div class="legend">Legend: highlighted row = this billing. Scheduled value = contract sum. Previous billed = total of past billings. Retainage = total completed × rate.</div>

                    <div class="sec">CONTRACTOR'S CERTIFICATION</div>
                    <div class="cert">The undersigned Contractor certifies that, to the best of its knowledge, the work covered by this Application for Payment has been completed in accordance with the Contract Documents, that all amounts have been paid for work for which previous Certificates for Payment were issued, and that the current payment shown herein is now due.</div>
                    <table class="sign"><tr>
                        ${sig(a.prepared_signature_url, a.prepared_by, 'Prepared by · Project Coordinator', true, '')}
                        ${sig(a.head_signature_url, a.head_by, 'Noted by · Accounting Head', signed.head, 'Waiting for signature')}
                        ${sig(a.sa_signature_url, a.sa_by || 'Mr. Ken Yang', 'Approved by', signed.sa, 'Waiting for signature')}
                    </tr></table>
                </div>`;
            }
            const BP_ST = {
                pending_head: { cls: 'bg-amber-50 text-amber-700 ring-amber-600/20', icon: 'fa-signature', text: 'For Accounting Head' },
                pending_sa: { cls: 'bg-sky-50 text-sky-700 ring-sky-600/20', icon: 'fa-signature', text: 'For Superadmin' },
                approved: { cls: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', icon: 'fa-circle-check', text: 'Approved' },
                returned: { cls: 'bg-red-50 text-red-700 ring-red-600/20', icon: 'fa-rotate-left', text: 'Returned' },
            };
            const bpChip = st => { const s = BP_ST[st]; return s ? `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold ring-1 ${s.cls}"><i class="fa-solid ${s.icon} text-[9px]"></i>${s.text}</span>` : ''; };
            function bpPrint(html) {
                let area = F('nhPrintArea');
                if (!area) { area = document.createElement('div'); area.id = 'nhPrintArea'; document.body.appendChild(area); }
                area.innerHTML = html;
                const imgs = [...area.querySelectorAll('img')].filter(i => !i.complete);
                Promise.all(imgs.map(i => new Promise(r => { i.onload = i.onerror = r; }))).then(() => { window.print(); setTimeout(() => { area.innerHTML = ''; }, 500); });
            }

            // ═════════════ LIST ═════════════
            const inTab = (r, k) => k === 'all' ? true : k === 'mine' ? r.status === MY_STATUS : k === 'approved' ? r.status === 'approved' : k === 'returned' ? r.status === 'returned' : false;
            function render() {
                document.querySelectorAll('.ap-tab').forEach(b => b.dataset.active = String(b.dataset.tab === tab));
                document.querySelectorAll('[data-count]').forEach(el => el.textContent = rows.filter(r => inTab(r, el.dataset.count)).length);
                document.querySelectorAll('[data-sum]').forEach(el => { const l = rows.filter(r => inTab(r, el.dataset.sum)); el.textContent = l.length ? '₱ ' + money(l.reduce((s, r) => s + r.this_period, 0)) : ' '; });
                const q = F('apSearch').value.trim().toLowerCase();
                const list = rows.filter(r => inTab(r, tab) && (!q || `${r.ref_no} ${r.project_name ?? ''} ${r.owner ?? ''} ${r.subcontractor ?? ''} ${r.dr_no}`.toLowerCase().includes(q)));
                F('apInfo').innerHTML = `<b class="text-gray-700">${list.length}</b> billing${list.length === 1 ? '' : 's'}${q ? ' (filtered)' : ''}`;
                if (!list.length) {
                    F('apList').innerHTML = `<div class="px-6 py-14 text-center"><div class="w-12 h-12 mx-auto rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center mb-3"><i class="fa-solid ${tab === 'mine' && !q ? 'fa-mug-hot' : 'fa-file-circle-question'}"></i></div><p class="text-sm font-semibold text-gray-700">${q ? 'No match.' : tab === 'mine' ? 'All caught up! Nothing is waiting for your signature.' : 'Nothing here.'}</p></div>`;
                    return;
                }
                const BAR = { pending_head: 'bg-amber-500', pending_sa: 'bg-sky-500', approved: 'bg-emerald-600', returned: 'bg-red-500' };
                F('apList').innerHTML = list.map(r => {
                    const mine = r.status === MY_STATUS, sum = r.contract_amount + r.change_orders, pct = sum ? r.to_date / sum * 100 : 0;
                    const sub = r.status === 'returned' ? `<span class="text-red-700"><i class="fa-solid fa-comment-dots mr-1"></i>${esc(r.return_remarks || '')}</span>`
                        : r.status === 'approved' ? `<span class="text-emerald-700"><i class="fa-solid fa-signature mr-1"></i>Approved ${fmtY(r.sa_at)} by ${esc(r.sa_by || '')}</span>`
                        : r.status === 'pending_sa' ? `<span class="text-sky-700"><i class="fa-solid fa-signature mr-1"></i>Noted by ${esc(r.head_by || '')} · ${fmtY(r.head_at)}</span>`
                        : `<span class="text-amber-700"><i class="fa-regular fa-clock mr-1"></i>Submitted ${ago(r.submitted_at)} by ${esc(r.prepared_by || '')}</span>`;
                    return `<button type="button" data-id="${r.id}" class="ap-row relative w-full text-left flex flex-wrap items-center gap-x-5 gap-y-2 pl-5 pr-4 py-3.5 hover:bg-amber-50/40 transition">
                        <span class="absolute left-0 top-0 bottom-0 w-1 ${BAR[r.status] || 'bg-gray-300'}"></span>
                        <span class="min-w-0 flex-1 basis-[260px]">
                            <span class="flex flex-wrap items-center gap-2"><span class="font-mono text-sm font-bold text-gray-900">${esc(r.ref_no)}</span>${bpChip(r.status)}</span>
                            <span class="block text-sm font-semibold text-gray-800 truncate mt-0.5">${esc(r.project_name || r.owner)} <span class="font-normal text-gray-400">· ${esc(bpLabel(r))}</span></span>
                            <span class="block text-[11px] truncate mt-0.5">${sub}</span>
                        </span>
                        <span class="grid grid-cols-3 gap-x-5 text-[11px] basis-[300px] grow sm:grow-0">
                            <span class="min-w-0"><span class="block text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Subcontractor</span><span class="text-gray-700 truncate block">${esc(r.subcontractor || '—')}</span></span>
                            <span><span class="block text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Billing date</span><span class="text-gray-700">${fmtY(r.billing_date)}</span></span>
                            <span><span class="block text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Billed to date</span><span class="text-gray-700">${pct.toFixed(1)}%</span></span>
                        </span>
                        <span class="ml-auto shrink-0 flex items-center gap-3">
                            <span class="text-right"><span class="block text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">This billing</span><span class="font-mono text-sm font-bold text-gray-900">₱ ${money(r.this_period)}</span></span>
                            <span class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg ${mine ? 'text-white bg-amber-700 shadow-sm' : 'text-gray-600 bg-white border border-gray-200'}"><i class="fa-solid ${mine ? 'fa-pen-nib' : 'fa-eye'}"></i> ${mine ? 'Review & sign' : 'View'}</span>
                        </span></button>`;
                }).join('');
                let id = null; try { id = sessionStorage.getItem(MEMO + 'picked'); } catch (e) { }
                document.querySelectorAll('.ap-row').forEach(el => el.setAttribute('data-picked', el.dataset.id === id ? '1' : '0'));
            }
            document.querySelectorAll('.ap-tab').forEach(b => b.addEventListener('click', () => { tab = b.dataset.tab; memo.set('tab', tab); render(); }));
            F('apSearch').addEventListener('input', render);
            F('apList').addEventListener('click', e => { const b = e.target.closest('.ap-row'); if (!b) return; try { sessionStorage.setItem(MEMO + 'picked', b.dataset.id); } catch (er) { } render(); openReview(+b.dataset.id); });
            async function load(silent) {
                try {
                    const d = await api('list');
                    me = d.me || ''; mySig = d.my_signature || null;
                    F('apUpdated').textContent = 'Updated ' + new Date().toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' });
                    const sig = JSON.stringify(d.rows.map(r => [r.id, r.status]));
                    if (silent && sig === lastSig) return;
                    if (silent && lastSig) {
                        const known = new Set(rows.filter(r => r.status === MY_STATUS).map(r => r.id));
                        const fresh = d.rows.filter(r => r.status === MY_STATUS && !known.has(r.id)).length;
                        if (fresh) toast(`${fresh} new billing${fresh === 1 ? '' : 's'} waiting for your signature.`);
                    }
                    lastSig = sig; rows = d.rows; render();
                } catch (e) {
                    if (silent) return;
                    F('apList').innerHTML = `<div class="px-6 py-12 text-center text-xs text-red-600">${esc(e.message)}<br><button type="button" id="apRetry" class="mt-2 font-semibold text-amber-700 hover:underline">Retry</button></div>`;
                    F('apRetry').addEventListener('click', () => load());
                }
            }

            // ═════════════ REVIEW ═════════════
            let cur = null;   // { project, apps, a }
            const openM = () => { F('apModal').classList.remove('hidden'); F('apModal').classList.add('flex'); };
            const closeM = () => { F('apModal').classList.add('hidden'); F('apModal').classList.remove('flex'); cur = null; };
            F('amClose').addEventListener('click', closeM);
            F('apModal').addEventListener('click', e => { if (e.target === F('apModal')) closeM(); });
            document.addEventListener('keydown', e => { if (e.key === 'Escape' && cur) closeM(); });
            // quiet = realtime refresh: walang error toast, at hindi na bubuksan kapag naisara / may ginagawa habang naglo-load
            async function openReview(id, quiet) {
                let d; try { d = await api('get', { id }); } catch (e) { return quiet ? null : toast(e.message, 'error'); }
                if (quiet && (!cur || cur.a.id !== id || F('apModal').classList.contains('hidden') || rtBusy())) return;
                const k = d.apps.length - 1, a = d.apps[k];
                cur = { project: d.project, apps: d.apps, a };
                F('amTitle').textContent = `${a.ref_no} · ${bpLabel(a)}`;
                F('amSub').textContent = `${d.project.project_name || d.project.owner} · DR# ${d.project.dr_no} · ${d.project.subcontractor || ''}`;
                F('amPrint').classList.toggle('hidden', a.status !== 'approved');
                drawDoc();
                renderSide();
                openM();
            }
            function drawDoc() {
                const apps = cur.apps.slice(), k = apps.length - 1, a = { ...apps[k] };
                // preview ng pirma mo sa tamang puwesto
                if (a.status === MY_STATUS && mySig) {
                    if (STAGE === 'head') { a.head_at = 'preview'; a.head_by = me; a.head_signature_url = mySig; }
                    else { a.status = 'approved'; a.sa_by = me; a.sa_signature_url = mySig; }
                }
                apps[k] = a;
                F('amDoc').innerHTML = bpPaper(cur.project, apps, k, { showWait: true });
                const doc = F('amDoc').querySelector('.bpdoc');
                if (a !== cur.a && cur.a.status === MY_STATUS) doc.querySelectorAll('.sign td')[STAGE === 'head' ? 1 : 2].querySelector('.sig img')?.style.setProperty('opacity', '.5');
                const w = F('amDoc').clientWidth - 34;
                if (w <= 0) { requestAnimationFrame(drawDoc); return; }
                doc.style.zoom = String(Math.max(0.3, Math.min(1, w / 980)));
            }
            window.addEventListener('resize', () => { if (cur) drawDoc(); });
            function renderSide() {
                const a = cur.a, { cur: row, sum } = bpCompute(cur.apps, cur.apps.length - 1);
                const facts = `<dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-[11px] bg-gray-50 border border-gray-100 rounded-xl p-3">
                    <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">This billing</dt><dd class="font-mono font-bold text-gray-900">₱ ${money(row.C)}</dd></div>
                    <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Payment due</dt><dd class="font-mono font-bold text-amber-800">₱ ${money(sum.L8)}</dd></div>
                    <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Total to date</dt><dd class="font-mono text-gray-800">₱ ${money(row.D)} <span class="text-gray-400">(${row.pct.toFixed(1)}%)</span></dd></div>
                    <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Balance</dt><dd class="font-mono text-gray-800">₱ ${money(row.bal)}</dd></div>
                    <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Retainage</dt><dd class="text-gray-800">${(+a.retainage_rate).toFixed(2)}%</dd></div>
                    <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Prepared by</dt><dd class="text-gray-800 truncate">${esc(a.prepared_by || '')}</dd></div>
                    ${a.remarks ? `<div class="col-span-2"><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Remarks</dt><dd class="text-gray-800">${esc(a.remarks)}</dd></div>` : ''}</dl>`;
                const side = F('amSide');
                if (a.status !== MY_STATUS) {
                    const s = { approved: ['emerald', 'fa-circle-check', `Approved by ${esc(a.sa_by || '')} on ${fmtY(a.sa_at)}.`], returned: ['red', 'fa-rotate-left', `Returned by ${esc(a.returned_by || '')}: ${esc(a.return_remarks || '')}`],
                        pending_head: ['amber', 'fa-hourglass-half', 'Waiting for the Accounting Head.'], pending_sa: ['sky', 'fa-hourglass-half', `Noted by ${esc(a.head_by || '')}. Waiting for the Superadmin.`] }[a.status];
                    const C = { emerald: 'border-emerald-200 bg-emerald-50 text-emerald-800', red: 'border-red-200 bg-red-50 text-red-800', amber: 'border-amber-200 bg-amber-50 text-amber-800', sky: 'border-sky-200 bg-sky-50 text-sky-800' };
                    side.innerHTML = `<div class="rounded-xl border p-4 text-xs ${C[s[0]]}"><i class="fa-solid ${s[1]} mr-1"></i>${s[2]}</div>${facts}`;
                    return;
                }
                side.innerHTML = `${a.return_remarks ? `<div class="rounded-xl border border-purple-200 bg-purple-50 px-3.5 py-2.5 text-[11px] text-purple-800"><p class="font-bold"><i class="fa-solid fa-rotate mr-1"></i>Resubmitted after remarks:</p><p class="mt-0.5">${esc(a.return_remarks)}</p></div>` : ''}
                    ${facts}
                    <section><p class="text-[11px] font-bold uppercase tracking-wider text-gray-500 mb-2"><i class="fa-solid fa-signature mr-1 text-amber-700"></i>Your signature (${STAGE === 'head' ? 'Noted by' : 'Approved by'})</p>
                        ${mySig ? `<div class="rounded-xl border border-gray-200 bg-white p-3 flex items-center justify-center h-24"><img src="${esc(mySig)}" alt="Your signature" class="max-h-full max-w-full object-contain"></div><p class="text-[11px] text-gray-500 mt-2">Signing as <b class="text-gray-800">${esc(me)}</b>.</p>`
                            : `<div class="rounded-xl border border-amber-200 bg-amber-50 p-3.5 text-xs text-amber-900"><p class="font-bold mb-0.5"><i class="fa-solid fa-triangle-exclamation mr-1"></i>No active signature yet</p><p>Add your signature first, then come back.</p><a href="<?= BASE_URL ?>/signaturedinsert" class="inline-flex items-center gap-1 mt-2 font-semibold text-amber-800 hover:underline">Go to Insert Signature <i class="fa-solid fa-arrow-right text-[10px]"></i></a></div>`}</section>
                    <p id="amErr" class="hidden rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-[11px] text-red-700"></p>
                    <div id="amApprove"></div>
                    <details id="amReturnBox" class="group rounded-xl border border-gray-200">
                        <summary class="list-none cursor-pointer flex items-center justify-between px-3.5 py-2.5 text-xs font-semibold text-red-700"><span><i class="fa-solid fa-rotate-left mr-1"></i>Return to PC for changes</span><i class="fa-solid fa-chevron-down text-[10px] text-gray-400 group-open:rotate-180 transition"></i></summary>
                        <div class="px-3.5 pb-3.5"><textarea id="amRemarks" rows="3" maxlength="1000" placeholder="What should the PC fix? e.g. The amount for this period should be 18,000." class="w-full border border-gray-200 rounded-lg px-3 py-2 text-xs outline-none focus:border-red-400 focus:ring-2 focus:ring-red-100 resize-y"></textarea>
                            <p id="amRemErr" class="hidden text-[11px] text-red-600 mt-1"></p>
                            <button type="button" id="amReturn" class="mt-2 w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold text-red-700 bg-red-50 border border-red-200 rounded-lg hover:bg-red-100 disabled:opacity-60"><i class="fa-solid fa-paper-plane"></i> Send back to PC</button></div>
                    </details>`;
                const err = m => { F('amErr').textContent = m || ''; F('amErr').classList.toggle('hidden', !m); };
                const btn = () => { F('amApprove').innerHTML = `<button type="button" id="amSign" ${mySig ? '' : 'disabled'} class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-sm font-semibold text-white bg-amber-700 rounded-xl hover:bg-amber-800 shadow-sm disabled:bg-gray-200 disabled:text-gray-400 disabled:shadow-none disabled:cursor-not-allowed"><i class="fa-solid fa-file-signature"></i> ${STAGE === 'head' ? 'Sign (Noted by)' : 'Approve &amp; sign'}</button>`; F('amSign').addEventListener('click', ask); };
                function ask() {
                    err('');
                    F('amApprove').innerHTML = `<div class="rounded-xl border border-amber-200 bg-amber-50 p-3"><p class="text-xs text-amber-900 mb-2">${STAGE === 'head' ? 'Sign' : 'Approve and sign'} <b>${esc(a.ref_no)}</b> (₱ ${money(a.this_period)})?${STAGE === 'head' ? ' It will go to the Superadmin next.' : ' The PC will be able to print it and make the next billing.'}</p>
                        <div class="flex gap-2"><button type="button" id="amYes" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:opacity-60"><i class="fa-solid fa-check"></i> Yes, sign</button>
                        <button type="button" id="amNo" class="px-3 py-2 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button></div></div>`;
                    F('amNo').addEventListener('click', btn);
                    F('amYes').addEventListener('click', async function () {
                        this.disabled = true; this.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Signing…';
                        try { const d = await api('approve', { id: a.id }, true); toast(`${d.ref_no} signed.`); await load(); openReview(a.id); }
                        catch (e) { btn(); err(e.message); }
                    });
                }
                btn();
                const rk = 'rem-' + a.id;
                F('amRemarks').value = memo.get(rk) || ''; if (F('amRemarks').value) F('amReturnBox').open = true;
                F('amRemarks').addEventListener('input', () => { memo.set(rk, F('amRemarks').value); F('amRemErr').classList.add('hidden'); F('amRemarks').removeAttribute('aria-invalid'); });
                F('amReturn').addEventListener('click', async function () {
                    const rem = F('amRemarks').value.trim();
                    if (rem.length < 3) { F('amRemErr').textContent = 'Tell the PC what to fix.'; F('amRemErr').classList.remove('hidden'); F('amRemarks').setAttribute('aria-invalid', 'true'); F('amRemarks').focus(); return; }
                    this.disabled = true;
                    try { await api('return', { id: a.id, remarks: rem }, true); memo.del(rk); toast(`${a.ref_no} returned to the PC.`); closeM(); load(); }
                    catch (e) { F('amRemErr').textContent = e.message; F('amRemErr').classList.remove('hidden'); }
                    finally { this.disabled = false; }
                });
            }
            F('amPrint').addEventListener('click', () => { if (cur && cur.a.status === 'approved') bpPrint(bpPaper(cur.project, cur.apps, cur.apps.length - 1)); });

            // ═════════════ INIT ═════════════
            tab = memo.get('tab') || 'mine';
            if (!['mine', 'returned', 'approved', 'all'].includes(tab)) tab = 'mine';
            load();

            // ═════════════ REALTIME (koneksyon ng sidebar: window.NH_RT) ═════════════
            // Bagong ipinasa / inayos ng PC (o pinirmahan sa ibang tab) → kunin agad ang listahan.
            // Ang bukas na papel ay nire-refresh din, pero HINDI habang may kumpirmasyon o sinusulat na remarks — hahabol pagkatapos.
            const RT_TYPES = /^(pc_billing|billing_)/i;
            const rtLive = () => !!(window.NH_RT && window.NH_RT.enabled && window.NH_RT.isLive());
            const rtBusy = () => !!cur && !!(F('amYes') || F('amReturnBox')?.open || document.activeElement === F('amRemarks'));
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
                if (!cur || F('apModal').classList.contains('hidden')) return;
                const keep = F('apModal').scrollTop;
                openReview(cur.a.id, true).then(() => { F('apModal').scrollTop = keep; });
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
                        F('apLiveDot').className = 'w-2 h-2 rounded-full ' + (live ? 'bg-green-500 animate-pulse' : 'bg-amber-400');
                        F('apLiveText').textContent = live ? 'Live' : 'Auto-refresh';
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