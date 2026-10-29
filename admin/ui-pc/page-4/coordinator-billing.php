<?php
// coordinator-billing.php  —  Project Coordinator: Progress Billing / Application for Payment
// Bubukas kapag approved na ang Labor Quotation. Bawat billing (papel): PC → Accounting Head → Superadmin.

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PC];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$bpAjaxUrl = BASE_URL . '/coordinatorbillingajax';
$tlPageUrl = BASE_URL . '/coordinatortimeline';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Progress Billing</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <link rel="stylesheet"
        href="<?= BASE_URL ?>/admin/ui-pc/css/pc/coordinator-billing.css?v=<?= @filemtime(ROOT_PATH . '/admin/ui-pc/css/pc/coordinator-billing.css') ?: 1 ?>">
</head>

<body class="bg-gray-50">
    <main id="main-content" class="md:ml-56 pt-20 md:pt-5 min-h-screen p-4 md:p-8 transition-all duration-300">

        <!-- ═══ HEADER ═══ -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-file-invoice-dollar text-lg"></i></div>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-amber-700">Operations · Progress
                        Billing</p>
                    <h1 class="text-xl font-bold text-gray-900 leading-tight">Progress Billing / Application for Payment
                    </h1>
                    <p class="text-xs text-gray-500">One paper per billing. Each one is signed by the Accounting Head,
                        then the Superadmin, before you can make the next.</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1.5 text-[11px] text-gray-500 bg-white border border-gray-200 rounded-full px-3 py-1.5" title="Updates automatically when someone else makes a change"><span id="bpLiveDot" class="w-2 h-2 rounded-full bg-amber-400"></span><span id="bpLiveText">Auto-refresh</span></span>
            <a href="<?= htmlspecialchars($tlPageUrl) ?>"
                class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg px-3 py-2 hover:bg-gray-50"><i
                    class="fa-solid fa-chart-gantt"></i> Timeline</a>
            </div>
        </div>

        <!-- ═══ STAT TABS ═══ -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
            <?php foreach ([
                ['todo', 'Can bill', 'fa-file-circle-plus', 'bg-amber-50 text-amber-700'],
                ['signing', 'Being signed', 'fa-signature', 'bg-sky-50 text-sky-600'],
                ['returned', 'Returned', 'fa-rotate-left', 'bg-red-50 text-red-600'],
                ['full', 'Fully billed', 'fa-flag-checkered', 'bg-emerald-50 text-emerald-600'],
                ['all', 'All projects', 'fa-layer-group', 'bg-gray-100 text-gray-600'],
            ] as [$k, $label, $icon, $cls]): ?>
                <button type="button" data-tab="<?= $k ?>"
                    class="bp-tab text-left bg-white border border-gray-200 rounded-xl p-3.5 flex items-center gap-3 shadow-sm min-w-0 transition hover:border-amber-400 data-[active=true]:border-amber-600 data-[active=true]:ring-2 data-[active=true]:ring-amber-100 data-[active=true]:bg-amber-50/40">
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

        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100">
                <div class="relative">
                    <i
                        class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                    <input type="text" id="bpSearch" placeholder="Search project, owner, subcontractor, DR no.…"
                        class="w-full h-9 bg-white border border-gray-200 rounded-lg pl-9 pr-3 text-xs outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100">
                </div>
            </div>
            <div id="bpInfo" class="px-4 py-2 text-[11px] text-gray-500 bg-gray-50 border-b border-gray-100">&nbsp;
            </div>
            <div id="bpList" class="divide-y divide-gray-100">
                <div class="px-6 py-14 text-center text-xs text-gray-400"><i
                        class="fa-solid fa-spinner fa-spin mr-1"></i> Loading…</div>
            </div>
        </div>
        <div id="bpToast"
            class="fixed bottom-4 right-4 z-[10002] flex flex-col gap-2.5 pointer-events-none w-[340px] max-w-[calc(100vw-2rem)]">
        </div>
    </main>

    <!-- ═══ PROJECT MODAL ═══ -->
    <div id="bpModal"
        class="hidden fixed inset-0 z-[9999] items-start justify-center bg-black/50 p-2 md:p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-7xl my-auto overflow-hidden">
            <div class="dr-hazard h-1.5"></div>
            <div class="flex items-start justify-between gap-3 px-5 py-4 border-b border-gray-100">
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-amber-700">Progress Billing</p>
                    <p id="pmTitle" class="text-base font-semibold text-gray-900 truncate"></p>
                    <p id="pmSub" class="text-[11px] text-gray-400 truncate"></p>
                </div>
                <button type="button" id="pmClose"
                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 shrink-0"
                    aria-label="Close"><i class="fa-solid fa-xmark text-base leading-none"></i></button>
            </div>
            <div id="pmKpis" class="grid grid-cols-2 lg:grid-cols-4 gap-3 px-5 pt-4"></div>
            <div class="grid xl:grid-cols-[400px_minmax(0,1fr)] gap-5 p-5">
                <!-- kaliwa: listahan ng billing + form -->
                <div class="space-y-4 min-w-0">
                    <div>
                        <div class="flex items-center justify-between mb-2">
                            <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500">Billings</p>
                            <button type="button" id="pmNew"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:bg-gray-200 disabled:text-gray-400 disabled:cursor-not-allowed"><i
                                    class="fa-solid fa-plus"></i> New billing</button>
                        </div>
                        <p id="pmNewHint" class="hidden mb-2 text-[11px] text-gray-500"></p>
                        <div id="pmApps"
                            class="rounded-xl border border-gray-200 divide-y divide-gray-100 overflow-hidden"></div>
                    </div>
                    <form id="pmForm" class="hidden rounded-xl border-2 border-amber-200 bg-amber-50/30 p-4 space-y-3"
                        novalidate></form>
                </div>
                <!-- kanan: papel -->
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-2">
                        <p id="pvLabel" class="text-[11px] font-bold uppercase tracking-wider text-gray-500"><i
                                class="fa-solid fa-file-lines mr-1 text-amber-700"></i>Paper</p>
                        <div class="flex items-center gap-2">
                            <span id="pvStatus"></span>
                            <button type="button" id="pvPrint"
                                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-semibold text-white bg-gray-900 rounded-lg hover:bg-gray-800 disabled:bg-gray-200 disabled:text-gray-400 disabled:cursor-not-allowed"><i
                                    class="fa-solid fa-print"></i> Print / Save PDF</button>
                        </div>
                    </div>
                    <div id="pvBanner"></div>
                    <div class="rounded-xl border border-gray-200 bg-slate-50 p-3">
                        <div id="pvBox" class="bg-white rounded-lg border border-gray-200 p-4 overflow-hidden"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (function () {
            'use strict';
            const AJAX = <?= json_encode($bpAjaxUrl) ?>, AJAX_NAME = 'coordinatorbillingajax', MEMO = 'nh-bp-';
            const LOGO = <?= json_encode(BASE_URL . '/icon/logo.webp') ?>;
            const F = id => document.getElementById(id);
            let rows = [], tab = 'todo', today = '', lastSig = '';
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
            const stateOf = r => {
                const sum = r.contract_amount + (r.change_orders || 0);
                if (r.last_status === 'returned') return 'returned';
                if (r.last_status === 'pending_head' || r.last_status === 'pending_sa') return 'signing';
                if (r.app_count && r.approved_total >= sum - 0.004) return 'full';
                return 'todo';
            };
            function render() {
                const inTab = (r, k) => k === 'all' || stateOf(r) === k;
                document.querySelectorAll('.bp-tab').forEach(b => b.dataset.active = String(b.dataset.tab === tab));
                document.querySelectorAll('[data-count]').forEach(el => el.textContent = rows.filter(r => inTab(r, el.dataset.count)).length);
                const q = F('bpSearch').value.trim().toLowerCase();
                const list = rows.filter(r => inTab(r, tab) && (!q || `${r.project_name ?? ''} ${r.owner ?? ''} ${r.subcontractor ?? ''} ${r.dr_no} ${r.quote_no ?? ''} ${r.location ?? ''}`.toLowerCase().includes(q)));
                F('bpInfo').innerHTML = `<b class="text-gray-700">${list.length}</b> project${list.length === 1 ? '' : 's'} · only projects with an approved labor quotation show here`;
                if (!list.length) {
                    const msg = q ? 'No match.' : { todo: 'No project is waiting for a new billing.', signing: 'No billing is being signed.', returned: 'No returned billing.', full: 'No fully billed project yet.', all: 'No project yet.' }[tab];
                    F('bpList').innerHTML = `<div class="px-6 py-14 text-center"><div class="w-12 h-12 mx-auto rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center mb-3"><i class="fa-solid fa-file-invoice-dollar"></i></div><p class="text-sm font-semibold text-gray-700">${msg}</p></div>`;
                    return;
                }
                const BAR = { todo: 'bg-amber-500', signing: 'bg-sky-500', returned: 'bg-red-500', full: 'bg-emerald-600' };
                F('bpList').innerHTML = list.map(r => {
                    const st = stateOf(r), sum = r.contract_amount + (r.change_orders || 0), pct = sum ? Math.min(100, r.approved_total / sum * 100) : 0;
                    return `<div data-row="${r.dr_id}" class="bp-row relative flex flex-wrap items-center gap-x-5 gap-y-2 pl-5 pr-4 py-3.5">
                        <span class="absolute left-0 top-0 bottom-0 w-1 ${BAR[st]}"></span>
                        <div class="min-w-0 flex-1 basis-[260px]">
                            <div class="flex flex-wrap items-center gap-2"><span class="text-sm font-bold text-gray-900 truncate">${esc(r.project_name || r.owner)}</span>${r.last_status ? bpChip(r.last_status) : '<span class="text-[10.5px] text-gray-400">No billing yet</span>'}</div>
                            <p class="text-[11px] text-gray-400 truncate mt-0.5"><span class="font-mono">DR# ${esc(r.dr_no)}</span> · ${esc(r.subcontractor || '')}${r.location ? ' · ' + esc(r.location) : ''}</p>
                            <div class="mt-2 flex items-center gap-2 max-w-md"><div class="flex-1 h-1.5 rounded-full bg-gray-100 overflow-hidden"><div class="h-full ${st === 'full' ? 'bg-emerald-500' : 'bg-amber-500'}" style="width:${pct}%"></div></div><span class="text-[10.5px] text-gray-500 tabular-nums">${pct.toFixed(1)}% billed</span></div>
                            ${st === 'returned' && r.last_remarks ? `<p class="mt-1.5 text-[11px] text-red-700 bg-red-50 border border-red-100 rounded-lg px-2.5 py-1.5"><i class="fa-solid fa-comment-dots mr-1"></i>${esc(r.last_remarks)}</p>` : ''}
                        </div>
                        <dl class="grid grid-cols-3 gap-x-5 text-[11px] basis-[320px] grow sm:grow-0">
                            <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Contract</dt><dd class="font-mono text-gray-800">₱ ${money(sum)}</dd></div>
                            <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Approved billed</dt><dd class="font-mono text-gray-800">₱ ${money(r.approved_total)}</dd></div>
                            <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Balance</dt><dd class="font-mono font-semibold text-gray-900">₱ ${money(sum - r.approved_total)}</dd></div>
                        </dl>
                        <div class="ml-auto shrink-0"><button type="button" data-open="${r.dr_id}" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg ${st === 'todo' || st === 'returned' ? 'text-white bg-amber-700 hover:bg-amber-800 shadow-sm' : 'text-gray-700 bg-white border border-gray-200 hover:bg-gray-50'}"><i class="fa-solid ${st === 'returned' ? 'fa-pen' : st === 'todo' ? 'fa-plus' : 'fa-eye'}"></i> ${st === 'returned' ? 'Fix billing' : st === 'todo' ? (r.app_count ? 'New billing' : 'Start billing') : 'Open'}</button></div>
                    </div>`;
                }).join('');
                let id = null; try { id = sessionStorage.getItem('nh-bp-picked'); } catch (e) { }
                document.querySelectorAll('.bp-row').forEach(el => el.setAttribute('data-picked', el.dataset.row === id ? '1' : '0'));
            }
            document.querySelectorAll('.bp-tab').forEach(b => b.addEventListener('click', () => { tab = b.dataset.tab; memo.set('tab', tab); render(); }));
            F('bpSearch').addEventListener('input', render);
            F('bpList').addEventListener('click', e => {
                const b = e.target.closest('[data-open]'); if (!b) return;
                try { sessionStorage.setItem('nh-bp-picked', b.dataset.open); } catch (er) { }
                openProject(+b.dataset.open);
            });
            async function load(silent) {
                try {
                    const d = await api('list');
                    today = d.today;
                    const sig = JSON.stringify(d.rows.map(r => [r.dr_id, r.app_count, r.last_status, r.approved_total]));
                    if (silent && sig === lastSig) return;
                    if (silent && lastSig) {
                        const prev = new Map(rows.map(r => [r.dr_id, r.last_status]));
                        d.rows.forEach(r => {
                            if (prev.get(r.dr_id) && prev.get(r.dr_id) !== r.last_status) {
                                if (r.last_status === 'approved') toast(`${r.last_ref} was approved. You can print it now.`);
                                if (r.last_status === 'returned') toast(`${r.last_ref} was returned. Please check the remarks.`, 'error', 6000);
                                if (r.last_status === 'pending_sa') toast(`${r.last_ref} was signed by the Accounting Head.`);
                            }
                        });
                    }
                    lastSig = sig; rows = d.rows; render();
                } catch (e) {
                    if (silent) return;
                    F('bpList').innerHTML = `<div class="px-6 py-12 text-center text-xs text-red-600">${esc(e.message)}<br><button type="button" id="bpRetry" class="mt-2 font-semibold text-amber-700 hover:underline">Retry</button></div>`;
                    F('bpRetry').addEventListener('click', () => load());
                }
            }

            // ═════════════ PROJECT MODAL ═════════════
            let pj = null;   // { project, apps, sel, mode: null|'new'|'edit', me, mySig }
            const openM = () => { F('bpModal').classList.remove('hidden'); F('bpModal').classList.add('flex'); };
            const closeM = () => { F('bpModal').classList.add('hidden'); F('bpModal').classList.remove('flex'); pj = null; load(true); };
            F('pmClose').addEventListener('click', closeM);
            F('bpModal').addEventListener('click', e => { if (e.target === F('bpModal')) closeM(); });
            document.addEventListener('keydown', e => { if (e.key === 'Escape' && pj) closeM(); });

            // quiet = realtime refresh: walang error toast, at hindi na bubuksan ulit kapag naisara / may sinimulang billing habang naglo-load
            async function openProject(drId, keepSel, quiet) {
                let d;
                try { d = await api('get', { dr_id: drId }); } catch (e) { return quiet ? null : toast(e.message, 'error'); }
                if (quiet && (!pj || pj.project.dr_id !== drId || pj.mode)) return;
                today = d.today || today;
                const p = d.project;
                pj = { project: p, apps: d.apps, sel: d.apps.length - 1, mode: null, me: d.me, mySig: d.my_signature };
                if (keepSel !== undefined) pj.sel = Math.min(keepSel, d.apps.length - 1);
                F('pmTitle').textContent = p.project_name || p.owner;
                F('pmSub').textContent = `DR# ${p.dr_no} · ${p.quote_no} · ${p.subcontractor || ''}${p.location ? ' · ' + p.location : ''}`;
                const last = d.apps[d.apps.length - 1];
                if (last && last.status === 'returned') { pj.mode = 'edit'; pj.sel = d.apps.length - 1; }
                else if (!d.apps.length) pj.mode = 'new';
                renderProject();
                openM();
                requestAnimationFrame(fitPaper);
            }

            const contractSum = () => { const l = pj.apps[pj.apps.length - 1]; return r2(+pj.project.contract_amount + (l ? +l.change_orders : 0)); };
            const approvedTotal = () => r2(pj.apps.filter(a => a.status === 'approved').reduce((s, a) => s + +a.this_period, 0));
            function renderProject() {
                const sum = contractSum(), apr = approvedTotal(), billed = r2(pj.apps.reduce((s, a) => s + +a.this_period, 0));
                const kpi = (label, val, cls = 'text-gray-900', sub = '') => `<div class="rounded-xl border border-gray-200 px-4 py-3"><p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500">${label}</p><p class="font-mono text-lg font-bold ${cls} leading-tight mt-0.5">${val}</p>${sub ? `<p class="text-[10.5px] text-gray-400">${sub}</p>` : ''}</div>`;
                F('pmKpis').innerHTML = kpi('Contract sum', '₱ ' + money(sum), 'text-gray-900', `Labor quotation ${esc(pj.project.quote_no)}`)
                    + kpi('Approved & billed', '₱ ' + money(apr), 'text-emerald-700', `${sum ? (apr / sum * 100).toFixed(1) : 0}% of contract`)
                    + kpi('Being signed', '₱ ' + money(billed - apr), 'text-sky-700', pj.apps.filter(a => a.status !== 'approved').length ? 'waiting for signatures' : 'none')
                    + kpi('Balance to bill', '₱ ' + money(sum - billed), sum - billed <= 0 ? 'text-emerald-700' : 'text-amber-800', sum - billed <= 0 ? 'fully billed' : '');

                // listahan ng billing
                F('pmApps').innerHTML = pj.apps.length ? pj.apps.map((a, i) => `<button type="button" data-sel="${i}" class="w-full text-left flex items-center gap-3 px-3 py-2.5 hover:bg-amber-50/40 ${i === pj.sel && pj.mode !== 'new' ? 'bg-amber-50' : ''}">
                        <span class="w-7 h-7 rounded-lg ${a.status === 'approved' ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-600'} text-[11px] font-bold flex items-center justify-center shrink-0">${i + 1}</span>
                        <span class="min-w-0 flex-1"><span class="block text-xs font-semibold text-gray-900 truncate">${esc(bpLabel(a))}</span><span class="block text-[10.5px] text-gray-400 truncate">${esc(a.ref_no)} · ${fmtY(a.billing_date)}</span></span>
                        <span class="text-right shrink-0"><span class="block font-mono text-xs font-bold text-gray-900">₱ ${money(a.this_period)}</span>${bpChip(a.status)}</span></button>`).join('')
                    : '<p class="px-4 py-6 text-center text-[11px] text-gray-400">No billing yet. Start with the down payment.</p>';

                // New billing button
                const last = pj.apps[pj.apps.length - 1], left = r2(sum - pj.apps.reduce((s, a) => s + +a.this_period, 0));
                let why = '';
                if (last && last.status !== 'approved') why = last.status === 'returned' ? 'Fix and resubmit the returned billing first.' : `Wait for ${last.status === 'pending_head' ? 'the Accounting Head' : 'the Superadmin'} to sign ${last.ref_no}.`;
                else if (left <= 0) why = 'The contract is fully billed.';
                F('pmNew').disabled = !!why || pj.mode === 'new';
                F('pmNewHint').textContent = why; F('pmNewHint').classList.toggle('hidden', !why);
                renderForm();
                renderPaper();
            }
            F('pmApps').addEventListener('click', e => {
                const b = e.target.closest('[data-sel]'); if (!b || !pj) return;
                pj.sel = +b.dataset.sel;
                const last = pj.apps.length - 1;
                if (pj.mode === 'new') pj.mode = null;
                if (pj.mode === 'edit' && pj.sel !== last) pj.mode = null;
                renderProject();
            });
            F('pmNew').addEventListener('click', () => { if (!pj) return; pj.mode = 'new'; renderProject(); setTimeout(() => F('pmForm').querySelector('[name=this_period]')?.focus(), 50); });

            // ── FORM ──
            const inp = 'w-full border border-gray-200 rounded-lg px-2.5 py-2 text-xs outline-none focus:border-amber-600 bg-white';
            const lbl = (t, req) => `<span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">${t}${req ? ' <span class="text-red-500">*</span>' : ''}</span>`;
            const draftKey = () => `draft-${pj.project.dr_id}-${pj.mode}`;
            function formDefaults() {
                const editing = pj.mode === 'edit', prevApps = editing ? pj.apps.slice(0, -1) : pj.apps, base = editing ? pj.apps[pj.apps.length - 1] : null;
                const lastPrev = prevApps[prevApps.length - 1];
                const dpSuggest = r2(+pj.project.contract_amount * (+pj.project.downpayment_pct || 0) / 100);
                if (base) return { billing_type: base.billing_type, this_period: String(base.this_period), billing_date: base.billing_date, period_from: base.period_from || '', period_to: base.period_to || '', retainage_rate: String(+base.retainage_rate), change_orders: String(+base.change_orders), remarks: base.remarks || '' };
                return {
                    billing_type: prevApps.length ? 'progress' : 'dp', this_period: prevApps.length ? '' : (dpSuggest ? String(dpSuggest) : ''), billing_date: today, period_from: lastPrev ? addDays(lastPrev.billing_date, 1) : '', period_to: today,
                    retainage_rate: lastPrev ? String(+lastPrev.retainage_rate) : '0', change_orders: lastPrev ? String(+lastPrev.change_orders) : '0', remarks: ''
                };
            }
            function renderForm() {
                const f = F('pmForm');
                if (!pj.mode) { f.classList.add('hidden'); f.innerHTML = ''; return; }
                let v = null; try { v = JSON.parse(memo.get(draftKey()) || 'null'); } catch (e) { }
                const restored = !!v; v = v || formDefaults();
                const editing = pj.mode === 'edit', base = editing ? pj.apps[pj.apps.length - 1] : null;
                const prevCount = editing ? pj.apps.length - 1 : pj.apps.length;
                const ret = base && base.status === 'returned';
                f.classList.remove('hidden');
                f.innerHTML = `
                    <div class="flex items-center justify-between gap-2"><p class="text-xs font-bold text-gray-900"><i class="fa-solid ${editing ? 'fa-pen' : 'fa-file-circle-plus'} mr-1 text-amber-700"></i>${editing ? 'Fix ' + esc(base.ref_no) : 'New billing #' + (prevCount + 1)}</p>
                        ${editing && !ret ? '' : ''}<button type="button" id="pfCancel" class="text-[11px] font-semibold text-gray-500 hover:text-gray-800">${ret ? '' : 'Cancel'}</button></div>
                    ${ret ? `<div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-[11px] text-red-800"><p class="font-bold"><i class="fa-solid fa-rotate-left mr-1"></i>Returned by ${esc(base.returned_by || '')} (${base.returned_stage === 'sa' ? 'Superadmin' : 'Accounting Head'})</p><p class="mt-0.5">${esc(base.return_remarks || '')}</p></div>` : ''}
                    ${prevCount === 0 ? `<div>${lbl('Type', 1)}<div class="grid grid-cols-2 gap-2">
                        ${[['dp', 'Down payment', 'fa-hand-holding-dollar'], ['progress', 'Application for payment', 'fa-list-check']].map(([k, t, ic]) => `<label class="pf-type flex items-center gap-2 rounded-lg border-2 px-3 py-2 cursor-pointer text-xs font-semibold ${v.billing_type === k ? 'border-amber-600 bg-white text-amber-800' : 'border-gray-200 bg-white text-gray-600'}"><input type="radio" name="billing_type" value="${k}" class="sr-only" ${v.billing_type === k ? 'checked' : ''}><i class="fa-solid ${ic}"></i>${t}</label>`).join('')}</div></div>`
                        : `<input type="hidden" name="billing_type" value="progress"><p class="text-[11px] text-gray-500">Type: <b class="text-gray-800">Application for Payment ${pj.apps.slice(0, prevCount).filter(a => a.billing_type === 'progress').length + 1}</b></p>`}
                    <div>
                        ${lbl('Amount this billing (this period)', 1)}
                        <div class="flex gap-2">
                            <div class="relative flex-1"><span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400">₱</span><input name="this_period" type="number" min="0" step="0.01" value="${esc(v.this_period)}" class="${inp} pl-6 font-mono font-semibold text-right"></div>
                            <div class="relative w-24"><input name="pct" type="number" min="0" max="100" step="0.01" placeholder="%" class="${inp} pr-6 text-right"><span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400">%</span></div>
                        </div>
                        <div class="flex items-center justify-between mt-1"><span id="pfLeft" class="text-[10.5px] text-gray-500"></span><button type="button" id="pfRest" class="text-[10.5px] font-semibold text-amber-700 hover:underline">Bill the remaining balance</button></div>
                        <span data-err="this_period" class="hidden block text-[11px] text-red-600 mt-1"></span>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <label>${lbl('Billing date', 1)}<input name="billing_date" type="date" value="${esc(v.billing_date)}" class="${inp} px-1.5"></label>
                        <label>${lbl('Period from')}<input name="period_from" type="date" value="${esc(v.period_from)}" class="${inp} px-1.5"></label>
                        <label>${lbl('Period to')}<input name="period_to" type="date" value="${esc(v.period_to)}" class="${inp} px-1.5"><span data-err="period_to" class="hidden block text-[11px] text-red-600 mt-1"></span></label>
                    </div>
                    <div class="grid grid-cols-2 gap-2">
                        <label>${lbl('Retainage %')}<div class="relative"><input name="retainage_rate" type="number" min="0" max="100" step="0.01" value="${esc(v.retainage_rate)}" class="${inp} pr-6 text-right"><span class="absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400">%</span></div><span data-err="retainage_rate" class="hidden block text-[11px] text-red-600 mt-1"></span></label>
                        <label>${lbl('Change orders (net)')}<div class="relative"><span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-gray-400">₱</span><input name="change_orders" type="number" step="0.01" value="${esc(v.change_orders)}" class="${inp} pl-6 text-right"></div><span data-err="change_orders" class="hidden block text-[11px] text-red-600 mt-1"></span></label>
                    </div>
                    <label class="block">${lbl('Remarks')}<textarea name="remarks" rows="2" maxlength="1000" class="${inp} resize-y">${esc(v.remarks)}</textarea></label>
                    <p id="pfErr" class="hidden rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-[11px] text-red-700"></p>
                    <div class="flex items-center justify-between gap-2 pt-1">
                        <p id="pfNote" class="text-[10.5px] text-gray-400">${restored ? 'Restored your unsaved draft.' : 'Goes to the Accounting Head, then the Superadmin.'}</p>
                        <button type="submit" id="pfSave" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:opacity-60"><i class="fa-solid fa-paper-plane"></i> <span>${editing ? 'Resubmit' : 'Submit for signing'}</span></button>
                    </div>`;
                syncPct(); updateLeft();
            }
            const fv = n => { const f = F('pmForm'), el = f.elements[n]; if (!el) return ''; if (el instanceof RadioNodeList || el.type === 'radio') return f.querySelector(`[name="${n}"]:checked`)?.value || ''; return el.value; };
            const readF = () => Object.fromEntries(['billing_type', 'this_period', 'billing_date', 'period_from', 'period_to', 'retainage_rate', 'change_orders', 'remarks'].map(n => [n, fv(n)]));
            const prevBilled = () => r2((pj.mode === 'edit' ? pj.apps.slice(0, -1) : pj.apps).reduce((s, a) => s + +a.this_period, 0));
            const formSum = () => r2(+pj.project.contract_amount + (+fv('change_orders') || 0));
            function syncPct() { const s = formSum(), a = +fv('this_period') || 0; F('pmForm').elements.pct.value = s && a ? (a / s * 100).toFixed(2) : ''; }
            function updateLeft() {
                const s = formSum(), left = r2(s - prevBilled() - (+fv('this_period') || 0));
                F('pfLeft').innerHTML = left < 0 ? `<span class="text-red-600 font-semibold">Over the contract by ₱ ${money(-left)}</span>` : `Balance after this: <b class="font-mono">₱ ${money(left)}</b>`;
            }
            function fieldErr(n, msg) { const el = F('pmForm').querySelector(`[data-err="${n}"]`); if (el) { el.textContent = msg || ''; el.classList.toggle('hidden', !msg); } const i = F('pmForm').elements[n]; if (i && i.classList) i.classList.toggle('border-red-400', !!msg); }
            let dT = null;
            F('pmForm').addEventListener('input', e => {
                const n = e.target.name;
                if (n === 'pct') { const s = formSum(), p = +e.target.value; if (s && p >= 0) F('pmForm').elements.this_period.value = r2(s * p / 100).toFixed(2); }
                else if (n === 'this_period' || n === 'change_orders') syncPct();
                if (n === 'billing_type') F('pmForm').querySelectorAll('.pf-type').forEach(l => { const on = l.querySelector('input').checked; l.classList.toggle('border-amber-600', on); l.classList.toggle('text-amber-800', on); l.classList.toggle('border-gray-200', !on); l.classList.toggle('text-gray-600', !on); });
                ['this_period', 'period_to', 'retainage_rate', 'change_orders'].forEach(k => fieldErr(k, ''));
                F('pfErr').classList.add('hidden');
                updateLeft(); renderPaper();
                clearTimeout(dT); dT = setTimeout(() => { if (pj && pj.mode) { memo.set(draftKey(), JSON.stringify(readF())); F('pfNote').textContent = 'Draft saved on this device.'; } }, 400);
            });
            F('pmForm').addEventListener('click', e => {
                if (e.target.closest('#pfCancel')) { memo.del(draftKey()); pj.mode = null; pj.sel = pj.apps.length - 1; renderProject(); }
                if (e.target.closest('#pfRest')) { const left = r2(formSum() - prevBilled()); F('pmForm').elements.this_period.value = Math.max(0, left).toFixed(2); F('pmForm').elements.this_period.dispatchEvent(new Event('input', { bubbles: true })); }
            });
            F('pmForm').addEventListener('submit', async e => {
                e.preventDefault();
                const v = readF(), amt = +v.this_period || 0, s = formSum();
                const err = m => { F('pfErr').textContent = m; F('pfErr').classList.remove('hidden'); };
                if (amt <= 0) { fieldErr('this_period', 'Enter the amount for this billing.'); return F('pmForm').elements.this_period.focus(); }
                if (r2(prevBilled() + amt) > s + 0.004) { fieldErr('this_period', `Over the contract. Remaining balance is ₱ ${money(s - prevBilled())}.`); return; }
                if (+v.retainage_rate < 0 || +v.retainage_rate > 100) return fieldErr('retainage_rate', '0 to 100%.');
                if (v.period_from && v.period_to && v.period_to < v.period_from) return fieldErr('period_to', 'Cannot be before the start.');
                if (!okDate(v.billing_date)) return err('Set the billing date.');
                const btn = F('pfSave'), txt = btn.querySelector('span').textContent;
                btn.disabled = true; btn.querySelector('span').textContent = 'Submitting…';
                const params = { dr_id: pj.project.dr_id, ...v };
                if (pj.mode === 'edit') params.id = pj.apps[pj.apps.length - 1].id;
                try {
                    clearTimeout(dT);
                    const d = await api('save', params, true);
                    memo.del(draftKey());
                    toast(`${d.ref_no} sent to the Accounting Head for signing.`);
                    await openProject(pj.project.dr_id);
                    load(true);
                } catch (er) {
                    if (er.field) fieldErr(er.field, er.message);
                    err(er.message);
                    btn.disabled = false; btn.querySelector('span').textContent = txt;
                }
            });

            // ── PAPER (kanan) ──
            function renderPaper() {
                let apps = pj.apps, k = pj.sel, draft = false;
                if (pj.mode) {
                    const v = readF(), base = pj.mode === 'edit' ? pj.apps[pj.apps.length - 1] : null;
                    const prev = pj.mode === 'edit' ? pj.apps.slice(0, -1) : pj.apps;
                    const prevProg = prev.filter(a => a.billing_type === 'progress').length;
                    const d = {
                        ...(base || {}), ref_no: base ? base.ref_no : '(new)', billing_type: v.billing_type || 'progress', app_no: v.billing_type === 'dp' ? 0 : prevProg + 1, contract_amount: +pj.project.contract_amount, change_orders: +v.change_orders || 0,
                        retainage_rate: +v.retainage_rate || 0, this_period: +v.this_period || 0, billing_date: v.billing_date || today, period_from: v.period_from, period_to: v.period_to,
                        prepared_by: pj.me, prepared_signature_url: pj.mySig, status: 'draft', head_at: null, head_by: null, sa_by: null
                    };
                    apps = [...prev, d]; k = apps.length - 1; draft = true;
                }
                if (!apps.length || k < 0) { F('pvBox').innerHTML = '<p class="py-16 text-center text-xs text-gray-400">The billing paper will show here.</p>'; F('pvPrint').disabled = true; F('pvStatus').innerHTML = ''; F('pvBanner').innerHTML = ''; return; }
                const a = apps[k];
                F('pvBox').innerHTML = bpPaper(pj.project, apps, k, { showWait: true });
                F('pvLabel').innerHTML = `<i class="fa-solid fa-file-lines mr-1 text-amber-700"></i>${draft ? 'Preview — ' : ''}${esc(bpLabel(a))}`;
                F('pvStatus').innerHTML = draft ? '<span class="text-[10.5px] font-semibold text-amber-700">Not submitted yet</span>' : bpChip(a.status);
                F('pvPrint').disabled = draft || a.status !== 'approved';
                F('pvPrint').title = F('pvPrint').disabled ? 'You can print once the Superadmin approves it.' : '';
                const ban = draft ? '' : a.status === 'pending_head' ? ['sky', 'fa-hourglass-half', 'Waiting for the Accounting Head to sign (Noted by).']
                    : a.status === 'pending_sa' ? ['sky', 'fa-hourglass-half', `Signed by ${esc(a.head_by || 'Accounting Head')}. Waiting for the Superadmin (Approved by).`]
                        : a.status === 'returned' ? ['red', 'fa-rotate-left', `Returned: ${esc(a.return_remarks || '')}`] : ['emerald', 'fa-circle-check', `Approved by ${esc(a.sa_by || '')} on ${fmtY(a.sa_at)}. You can print it.`];
                const BAN = { sky: 'bg-sky-50 border-sky-200 text-sky-800', red: 'bg-red-50 border-red-200 text-red-800', emerald: 'bg-emerald-50 border-emerald-200 text-emerald-800' };
                F('pvBanner').innerHTML = ban ? `<div class="mb-2 rounded-lg border px-3 py-2 text-[11px] ${BAN[ban[0]]}"><i class="fa-solid ${ban[1]} mr-1"></i>${ban[2]}</div>` : '';
                fitPaper();
            }
            function fitPaper() {
                const doc = F('pvBox').querySelector('.bpdoc'); if (!doc) return;
                const w = F('pvBox').clientWidth - 34;
                if (w <= 0) return requestAnimationFrame(fitPaper);   // hindi pa nakabukas ang modal
                doc.style.zoom = String(Math.max(0.3, Math.min(1, w / 980)));
            }
            window.addEventListener('resize', () => { if (pj) fitPaper(); });
            F('pvPrint').addEventListener('click', () => {
                if (!pj || pj.mode) return;
                const a = pj.apps[pj.sel];
                if (!a || a.status !== 'approved') return toast('You can print once the Superadmin approves it.', 'error');
                bpPrint(bpPaper(pj.project, pj.apps, pj.sel));
            });

            // ═════════════ INIT ═════════════
            tab = memo.get('tab') || 'todo';
            if (!['todo', 'signing', 'returned', 'full', 'all'].includes(tab)) tab = 'todo';
            load().then(() => {
                const want = +new URLSearchParams(location.search).get('dr');
                if (want && rows.some(r => r.dr_id === want)) openProject(want);
                if (want) { try { const u = new URL(location.href); u.searchParams.delete('dr'); history.replaceState(null, '', u); } catch (e) { } }
            });

            // ═════════════ REALTIME (koneksyon ng sidebar: window.NH_RT) ═════════════
            // May signal mula sa ibang PC, Superadmin o Accounting Head → kunin agad ang listahan.
            // Ang bukas na detalye ay nire-refresh din, pero HINDI habang may form na sinusulatan — hahabol pagkasara.
            const RT_TYPES = /^(pc_|dr|labor|foreman|timeline|billing|punch|turnover)/i;
            const rtLive = () => !!(window.NH_RT && window.NH_RT.enabled && window.NH_RT.isLive());
            const rtBusy = () => !!pj && !!pj.mode;
            let rtMissed = false, rtPoll = null, rtLoading = false, rtAgain = false;
            async function rtLoad() {
                if (rtLoading) { rtAgain = true; return; }
                rtLoading = true;
                try { await load(true); } catch (e) { } finally { rtLoading = false; if (rtAgain) { rtAgain = false; rtLoad(); } }
            }
            function rtRefreshOpen() {
                // bukas ang project at walang sinusulatang billing → kunin ulit (hal. pinirmahan ng Accounting Head / Superadmin)
                if (!pj) return;
                const keep = F('bpModal').scrollTop;
                openProject(pj.project.dr_id, pj.sel, true).then(() => { F('bpModal').scrollTop = keep; });
            }
            function rtSync() {
                if (document.hidden) { rtMissed = true; return; }
                rtLoad();
                if (rtBusy()) { rtMissed = true; return; }
                rtRefreshOpen();
                rtMissed = false;
            }
            function rtStartPolling() {
                if (rtPoll) clearInterval(rtPoll);
                // Live → 60s fallback lang; hindi Live → 30s (dating bilis)
                rtPoll = setInterval(() => { if (!document.hidden && !!!pj) rtLoad(); }, rtLive() ? 60000 : 30000);
            }
            (function hookRealtime() {
                const hook = () => {
                    if (!window.NH_RT || !window.NH_RT.enabled || hookRealtime.done) return;
                    hookRealtime.done = true;
                    let t = null;
                    window.NH_RT.on('*', 'changed', payload => {
                        const type = String(payload?.type || '');
                        if (type && !RT_TYPES.test(type)) return;   // ibang signal (CRM/budget/PR) — hindi para dito
                        clearTimeout(t);
                        t = setTimeout(rtSync, 400);
                    });
                    window.NH_RT.onLive(live => {
                        F('bpLiveDot').className = 'w-2 h-2 rounded-full ' + (live ? 'bg-green-500 animate-pulse' : 'bg-amber-400');
                        F('bpLiveText').textContent = live ? 'Live' : 'Auto-refresh';
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