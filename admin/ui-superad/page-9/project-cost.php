<?php
// project-cost.php  —  Project Cost: contract ng client laban sa lahat ng gastos (materials + labor), may graph at lahat ng konektadong dokumento
// Basa lang — walang binabago. Superadmin at Accounting Head.

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SUPERADMIN, ROLE_ACCOUNTING];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';
if (($_SESSION['role'] ?? '') === ROLE_ACCOUNTING && ($_SESSION['position'] ?? '') !== POSITION_HEAD) {
    header('Location: ' . BASE_URL . '/unauthorized');
    exit;
}

$pcoAjaxUrl = BASE_URL . '/projectcostajax';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Cost</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <style>
        .pco-hazard { background: repeating-linear-gradient(-45deg, #f59e0b 0 12px, #1f2937 12px 24px); }
        .pco-stripe { background: repeating-linear-gradient(-45deg, #fbbf24 0 6px, #fde68a 6px 12px); }
        .pco-over { background: repeating-linear-gradient(-45deg, #dc2626 0 6px, #f87171 6px 12px); }
        .pco-tip { pointer-events: none; }
        #pcoPrint { display: none; }
        @media print {
            @page { size: A4 portrait; margin: 12mm; }
            body > *:not(#pcoPrint) { display: none !important; }
            #pcoPrint { display: block !important; }
            #pcoPrint, #pcoPrint * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>

<body class="bg-gray-50">
    <main id="main-content" class="md:ml-56 pt-20 md:pt-5 min-h-screen p-4 md:p-8 transition-all duration-300">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-scale-balanced text-lg"></i></div>
                <div class="min-w-0"><p class="text-[10px] font-bold uppercase tracking-[0.15em] text-amber-700">Cost control</p>
                    <h1 class="text-xl font-bold text-gray-900 leading-tight">Project Cost</h1>
                    <p class="text-xs text-gray-500">Contract vs. all project costs — materials (Purchase Request) and labor. See if a project went over.</p></div>
            </div>
            <span class="inline-flex items-center gap-1.5 text-[11px] text-gray-500 bg-white border border-gray-200 rounded-full px-3 py-1.5"><span id="pcoLiveDot" class="w-2 h-2 rounded-full bg-amber-400"></span><span id="pcoLiveText">Auto-refresh</span><span class="text-gray-300">·</span><span id="pcoUpdated">Loading…</span></span>
        </div>

        <!-- Summary -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-4">
            <?php foreach ([
                ['contract', 'Total contract', 'fa-file-signature', 'bg-amber-50 text-amber-700'],
                ['cost', 'Total cost', 'fa-coins', 'bg-slate-100 text-slate-700'],
                ['spent', 'Spent so far', 'fa-money-bill-wave', 'bg-emerald-50 text-emerald-600'],
                ['margin', 'Remaining', 'fa-piggy-bank', 'bg-sky-50 text-sky-600'],
            ] as [$k, $label, $icon, $cls]): ?>
                <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3 shadow-sm min-w-0">
                    <span class="hidden sm:flex w-10 h-10 rounded-lg items-center justify-center shrink-0 <?= $cls ?>"><i class="fa-solid <?= $icon ?>"></i></span>
                    <span class="min-w-0"><span class="block text-[11px] font-semibold text-gray-500 uppercase tracking-wide truncate"><?= $label ?></span>
                        <span class="block text-lg md:text-xl font-bold text-gray-900 leading-tight tabular-nums truncate" data-sum="<?= $k ?>">—</span></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="grid lg:grid-cols-[minmax(0,1fr)_340px] gap-4">
            <!-- Listahan -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden min-w-0">
                <div class="h-1.5 pco-hazard"></div>
                <div class="px-4 pt-3 flex flex-wrap gap-1.5" id="pcoTabs">
                    <?php foreach ([['all', 'All'], ['over', 'Over budget'], ['near', 'Near limit'], ['within', 'Within budget'], ['no_contract', 'No contract']] as [$k, $label]): ?>
                        <button type="button" data-tab="<?= $k ?>" class="pco-tab px-3 py-1.5 rounded-full text-[11px] font-semibold border border-gray-200 text-gray-600 hover:border-amber-400 data-[active=true]:bg-amber-700 data-[active=true]:border-amber-700 data-[active=true]:text-white"><?= $label ?> <span class="tabular-nums opacity-80" data-count="<?= $k ?>"></span></button>
                    <?php endforeach; ?>
                </div>
                <div class="px-4 py-3 border-b border-gray-100 flex flex-col sm:flex-row gap-2">
                    <div class="relative flex-1"><i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                        <input type="text" id="pcoSearch" placeholder="Search client or control no.…" class="w-full h-9 bg-white border border-gray-200 rounded-lg pl-9 pr-3 text-xs outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100"></div>
                    <select id="pcoSort" class="h-9 bg-white border border-gray-200 rounded-lg px-2 text-xs outline-none focus:border-amber-500">
                        <option value="recent">Latest activity</option><option value="used">Most budget used</option><option value="cost">Highest cost</option><option value="contract">Biggest contract</option>
                    </select>
                </div>
                <div id="pcoList" class="divide-y divide-gray-100"><div class="px-6 py-14 text-center text-xs text-gray-400"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Loading…</div></div>
            </div>

            <!-- Graph: lahat ng project -->
            <div class="space-y-4 min-w-0">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-amber-700">Budget health</p>
                    <p class="text-sm font-semibold text-gray-900 mb-3">All projects</p>
                    <div id="pcoHealth"></div>
                </div>
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
                    <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-amber-700">Top budget used</p>
                    <p class="text-sm font-semibold text-gray-900 mb-3">Contract vs cost</p>
                    <div id="pcoTop" class="space-y-3"></div>
                </div>
            </div>
        </div>
    </main>

    <!-- Detalye ng project -->
    <div id="pcoModal" class="hidden fixed inset-0 z-[9999] items-start justify-center bg-black/50 p-2 md:p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-5xl my-auto overflow-hidden">
            <div class="h-1.5 pco-hazard"></div>
            <div class="flex items-start justify-between gap-3 px-5 py-4 border-b border-gray-100">
                <div class="min-w-0"><p class="text-[10px] font-bold uppercase tracking-[0.15em] text-amber-700">Project cost</p><p id="pmTitle" class="text-base font-semibold text-gray-900 truncate"></p><p id="pmSub" class="text-[11px] text-gray-400 truncate"></p></div>
                <button type="button" id="pmClose" aria-label="Close" class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 shrink-0"><i class="fa-solid fa-xmark text-base leading-none"></i></button>
            </div>
            <div id="pmBody" class="p-4 md:p-5 space-y-4"></div>
        </div>
    </div>

    <!-- Tingnan ang dokumento -->
    <div id="pcoDoc" class="hidden fixed inset-0 z-[10000] items-start justify-center bg-black/60 p-2 md:p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-3xl my-auto overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-5 py-3 border-b border-gray-100">
                <p id="pdKind" class="text-[10px] font-bold uppercase tracking-[0.15em] text-amber-700 truncate">Document</p>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" id="pdPrint" class="hidden inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-gray-900 rounded-lg hover:bg-gray-800"><i class="fa-solid fa-print"></i> Print</button>
                    <button type="button" id="pdClose" aria-label="Close" class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 shrink-0"><i class="fa-solid fa-xmark text-base leading-none"></i></button>
                </div>
            </div>
            <div class="p-3 md:p-5 bg-slate-50"><div id="pdPaper" class="bg-white border border-gray-200 rounded-lg p-4 md:p-6 min-w-0"></div></div>
        </div>
    </div>
    <div id="pcoPrint"></div>

    <script>
        (function () {
            'use strict';
            const AJAX = <?= json_encode($pcoAjaxUrl) ?>, MEMO = 'nh-pco-', LOGO = <?= json_encode(BASE_URL . '/icon/logo.webp') ?>;
            const F = id => document.getElementById(id);
            let rows = [], tab = 'all', cur = null, docTab = 'pr', lastSig = '';
            const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
            const money = n => '₱' + (Number(n) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const short = n => { n = Number(n) || 0; const a = Math.abs(n); return (n < 0 ? '-' : '') + '₱' + (a >= 1e6 ? (a / 1e6).toFixed(a >= 1e7 ? 1 : 2).replace(/\.0+$/, '') + 'M' : a >= 1e3 ? (a / 1e3).toFixed(a >= 1e5 ? 0 : 1).replace(/\.0$/, '') + 'K' : a.toFixed(0)); };
            const fmtD = s => s ? new Date(s + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
            const memo = {
                get: k => { try { return localStorage.getItem(MEMO + k); } catch (e) { return null; } },
                set: (k, v) => { try { localStorage.setItem(MEMO + k, v); } catch (e) { } },
            };
            const C = { mat: '#b45309', labor: '#334155', pending: '#fbbf24', over: '#dc2626', ok: '#059669', near: '#d97706', grid: '#e5e7eb' };
            const ST = {
                over: ['Over budget', 'bg-red-50 text-red-700 border-red-200', 'fa-triangle-exclamation'],
                near: ['Near limit', 'bg-amber-50 text-amber-700 border-amber-200', 'fa-gauge-high'],
                within: ['Within budget', 'bg-emerald-50 text-emerald-700 border-emerald-200', 'fa-circle-check'],
                no_contract: ['No contract', 'bg-gray-100 text-gray-500 border-gray-200', 'fa-circle-question'],
            };
            const badge = s => { const [l, c, i] = ST[s] || ST.no_contract; return `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-[10px] font-semibold whitespace-nowrap ${c}"><i class="fa-solid ${i}"></i>${l}</span>`; };

            async function api(action, params = {}) {
                const r = await fetch(AJAX + '?' + new URLSearchParams({ action, ...params }), { credentials: 'same-origin', cache: 'no-store' });
                let j; try { j = await r.json(); } catch (e) { throw new Error('Server error. Please reload.'); }
                if (!j.success) throw new Error(j.message || 'Something went wrong.');
                return j;
            }

            // ── Budget bar (materials | labor | pending labor | natitira), may 100% na guhit ──
            function meter(p, h = 'h-3') {
                const scale = Math.max(p.contract, p.projected, 1);
                const w = v => (Math.max(0, v) / scale * 100).toFixed(2) + '%';
                const mark = p.contract > 0 ? `<span class="absolute top-[-3px] bottom-[-3px] w-0.5 bg-gray-900" style="left:calc(${w(p.contract)} - 1px)" title="Contract"></span>` : '';
                const overW = p.contract > 0 && p.cost > p.contract ? `<span class="absolute inset-y-0 pco-over opacity-90" style="left:${w(p.contract)};width:${w(p.cost - p.contract)}" title="Over the contract"></span>` : '';
                return `<div class="relative ${h} rounded-full bg-gray-100">
                    <div class="absolute inset-0 flex rounded-full overflow-hidden">
                        <span style="width:${w(p.mat)};background:${C.mat}"></span>
                        <span style="width:${w(p.labor)};background:${C.labor}"></span>
                        <span class="pco-stripe" style="width:${w(p.labor_pending)}"></span>
                    </div><div class="absolute inset-0 rounded-full overflow-hidden">${overW}</div>${mark}</div>`;
            }

            // ── Donut (SVG) ──
            function donut(parts, center, sub, stacked) {
                parts = parts.filter(x => x.v > 0 || x.keep);
                const total = parts.reduce((s, x) => s + Math.max(0, x.v), 0);
                const R = 54, L = 2 * Math.PI * R;
                let off = 0;
                const arcs = total > 0 ? parts.filter(x => x.v > 0).map(x => {
                    const len = x.v / total * L;
                    const a = `<circle cx="70" cy="70" r="${R}" fill="none" stroke="${x.c}" stroke-width="18" stroke-dasharray="${len} ${L - len}" stroke-dashoffset="${-off}" transform="rotate(-90 70 70)"><title>${esc(x.l)}: ${money(x.v)}</title></circle>`;
                    off += len; return a;
                }).join('') : '';
                return `<div class="flex ${stacked ? 'flex-col' : ''} items-center gap-4">
                    <svg viewBox="0 0 140 140" class="w-32 h-32 shrink-0"><circle cx="70" cy="70" r="${R}" fill="none" stroke="${C.grid}" stroke-width="18"/>${arcs}
                        <text x="70" y="68" text-anchor="middle" font-size="15" font-weight="700" fill="#111827">${esc(center)}</text>
                        <text x="70" y="86" text-anchor="middle" font-size="9" fill="#6b7280">${esc(sub)}</text></svg>
                    <div class="space-y-1.5 text-[11px] min-w-0 flex-1 ${stacked ? 'w-full' : ''}">${parts.map(x => `<div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-sm shrink-0 ${x.cls || ''}" style="${x.cls ? '' : 'background:' + x.c}"></span><span class="text-gray-600 truncate">${esc(x.l)}</span><span class="ml-auto font-semibold text-gray-900 tabular-nums">${money(x.v)}</span></div>`).join('')}</div></div>`;
            }

            // ── Linya: tuloy-tuloy na gastos sa paglipas ng panahon vs contract ──
            function lineChart(p) {
                const ev = (p.events || []).slice();
                if (!ev.length) return `<p class="text-xs text-gray-400 py-10 text-center">No dated costs yet.</p>`;
                const pts = []; let run = 0, mat = 0;
                ev.forEach(([d, v, k]) => { run += v; if (k === 'mat') mat += v; const last = pts[pts.length - 1]; if (last && last.d === d) { last.v = run; } else pts.push({ d, v: run }); });
                const W = 560, H = 200, PL = 52, PR = 12, PT = 14, PB = 28;
                const t0 = new Date(pts[0].d + 'T00:00:00').getTime();
                let t1 = new Date(pts[pts.length - 1].d + 'T00:00:00').getTime(); if (t1 === t0) t1 = t0 + 864e5;
                const maxV = Math.max(p.contract, run, 1) * 1.08;
                const X = t => PL + (t - t0) / (t1 - t0) * (W - PL - PR);
                const Y = v => PT + (1 - v / maxV) * (H - PT - PB);
                let d = `M${X(t0)},${Y(0)}`;
                pts.forEach(q => { const x = X(new Date(q.d + 'T00:00:00').getTime()); d += ` L${x},${Y(q.v)}`; });
                const lastX = X(t1);
                const area = d + ` L${lastX},${Y(0)} Z`;
                const ticks = [0, .25, .5, .75, 1].map(f => { const v = maxV * f; return `<line x1="${PL}" x2="${W - PR}" y1="${Y(v)}" y2="${Y(v)}" stroke="${C.grid}" stroke-dasharray="${f ? '3 3' : ''}"/><text x="${PL - 6}" y="${Y(v) + 3}" text-anchor="end" font-size="9" fill="#9ca3af">${short(v)}</text>`; }).join('');
                const over = p.contract > 0 && run > p.contract;
                const cl = p.contract > 0 ? `<line x1="${PL}" x2="${W - PR}" y1="${Y(p.contract)}" y2="${Y(p.contract)}" stroke="${C.over}" stroke-width="1.5" stroke-dasharray="6 4"/><text x="${PL + 6}" y="${Y(p.contract) - 5}" text-anchor="start" font-size="10" font-weight="700" fill="${C.over}">Contract ${short(p.contract)}</text>` : '';
                const dots = pts.map(q => `<circle cx="${X(new Date(q.d + 'T00:00:00').getTime())}" cy="${Y(q.v)}" r="3" fill="#fff" stroke="${over ? C.over : C.mat}" stroke-width="2"><title>${fmtD(q.d)} — ${money(q.v)}</title></circle>`).join('');
                const lab = (t, a, s) => `<text x="${X(t)}" y="${H - 8}" text-anchor="${a}" font-size="9" fill="#9ca3af">${fmtD(s)}</text>`;
                return `<svg viewBox="0 0 ${W} ${H}" class="w-full h-auto">${ticks}
                    <path d="${area}" fill="${over ? C.over : C.mat}" fill-opacity=".1"/><path d="${d}" fill="none" stroke="${over ? C.over : C.mat}" stroke-width="2.5" stroke-linejoin="round"/>${cl}${dots}${lab(t0, 'start', pts[0].d)}${pts.length > 1 ? lab(t1, 'end', pts[pts.length - 1].d) : ''}</svg>
                    <p class="text-[10px] text-gray-400">Running total of purchase requests (by request date) and approved labor billings (by billing date).</p>`;
            }

            // ── Summary + listahan ──
            function render() {
                const q = (F('pcoSearch').value || '').trim().toLowerCase();
                const counts = { all: rows.length, over: 0, near: 0, within: 0, no_contract: 0 };
                rows.forEach(r => counts[r.status]++);
                document.querySelectorAll('[data-count]').forEach(el => el.textContent = counts[el.dataset.count] ?? 0);
                document.querySelectorAll('.pco-tab').forEach(b => b.dataset.active = String(b.dataset.tab === tab));
                const sort = F('pcoSort').value;
                let list = rows.filter(r => (tab === 'all' || r.status === tab) && (!q || `${r.client} ${r.control_no}`.toLowerCase().includes(q)));
                const by = { used: r => r.used_pct ?? -1, cost: r => r.cost, contract: r => r.contract };
                if (by[sort]) list.sort((a, b) => by[sort](b) - by[sort](a));
                if (!list.length) { F('pcoList').innerHTML = `<div class="px-6 py-14 text-center text-xs text-gray-400"><i class="fa-solid fa-folder-open text-2xl text-gray-200 block mb-2"></i>${rows.length ? 'No project matches.' : 'No project yet. A project shows here once its BOM is approved.'}</div>`; return; }
                F('pcoList').innerHTML = list.map(r => {
                    const rem = r.contract - r.cost;
                    return `<button type="button" data-open="${r.id}" class="w-full text-left px-4 py-3.5 hover:bg-amber-50/40 transition block">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0"><p class="text-sm font-semibold text-gray-900 truncate">${esc(r.client || '—')}</p>
                                <p class="text-[11px] text-gray-400 truncate">${esc(r.control_no)} · ${r.bom_n} BOM · ${r.pr_n} PR · ${r.lq_n} labor</p></div>
                            <div class="text-right shrink-0">${badge(r.status)}<p class="text-[11px] font-semibold tabular-nums mt-1 ${r.status === 'over' ? 'text-red-600' : 'text-gray-500'}">${r.used_pct === null ? '—' : r.used_pct + '%'} used</p></div>
                        </div>
                        <div class="mt-2.5">${meter(r, 'h-2.5')}</div>
                        <div class="mt-1.5 flex flex-wrap justify-between gap-x-3 text-[11px] tabular-nums"><span class="text-gray-500">Cost <b class="text-gray-900">${money(r.cost)}</b> of ${money(r.contract)}</span>
                            ${r.contract <= 0 ? '<span class="text-gray-400">No contract amount yet</span>' : `<span class="${rem < 0 ? 'text-red-600 font-semibold' : 'text-emerald-600'}">${rem < 0 ? 'Over by ' + money(-rem) : money(rem) + ' left'}</span>`}</div>
                        ${r.projected_over && r.status !== 'over' ? `<p class="mt-1 text-[10px] text-amber-700"><i class="fa-solid fa-circle-exclamation"></i> Will go over once pending labor is approved.</p>` : ''}
                    </button>`;
                }).join('');
            }
            function renderSide(sum) {
                const margin = sum.contract - sum.cost;
                document.querySelector('[data-sum="contract"]').textContent = money(sum.contract);
                document.querySelector('[data-sum="cost"]').textContent = money(sum.cost);
                document.querySelector('[data-sum="spent"]').textContent = money(sum.spent);
                const m = document.querySelector('[data-sum="margin"]'); m.textContent = (margin < 0 ? '-' : '') + money(Math.abs(margin)); m.classList.toggle('text-red-600', margin < 0);
                F('pcoHealth').innerHTML = donut([
                    { l: 'Over budget', v: sum.over, c: C.over, keep: 1 }, { l: 'Near limit (90%+)', v: sum.near, c: C.near, keep: 1 },
                    { l: 'Within budget', v: sum.within, c: C.ok, keep: 1 }, { l: 'No contract', v: sum.no_contract, c: '#d1d5db', keep: 1 },
                ], String(sum.projects), sum.projects === 1 ? 'project' : 'projects').replace(/₱([\d,]+)\.00/g, '$1');
                const top = rows.filter(r => r.contract > 0).sort((a, b) => b.used_pct - a.used_pct).slice(0, 6);
                F('pcoTop').innerHTML = top.length ? top.map(r => `<button type="button" data-open="${r.id}" class="block w-full text-left group">
                    <div class="flex justify-between gap-2 text-[11px] mb-1"><span class="font-semibold text-gray-700 truncate group-hover:text-amber-700">${esc(r.client)}</span><span class="tabular-nums font-semibold ${r.status === 'over' ? 'text-red-600' : 'text-gray-500'}">${r.used_pct}%</span></div>
                    ${meter(r, 'h-2')}</button>`).join('') + `<div class="flex flex-wrap gap-3 pt-1 text-[10px] text-gray-500"><span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm" style="background:${C.mat}"></span>Materials</span><span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm" style="background:${C.labor}"></span>Labor</span><span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm pco-stripe"></span>Pending labor</span><span class="flex items-center gap-1"><span class="w-0.5 h-3 bg-gray-900"></span>Contract</span></div>`
                    : `<p class="text-xs text-gray-400">No project with a contract yet.</p>`;
            }

            async function load(quiet) {
                try {
                    const j = await api('list');
                    const sig = JSON.stringify(j.rows);
                    F('pcoUpdated').textContent = 'Updated ' + new Date().toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' });
                    if (quiet && sig === lastSig) return;
                    lastSig = sig; rows = j.rows; render(); renderSide(j.summary);
                } catch (e) { if (!quiet) F('pcoList').innerHTML = `<div class="px-6 py-14 text-center text-xs text-red-600">${esc(e.message)}</div>`; }
            }

            // ── Detalye ──
            const DOCS = [
                ['pr', 'Purchase Request', 'fa-cart-shopping'], ['labor', 'Labor quotation', 'fa-helmet-safety'], ['billing', 'Labor billing', 'fa-file-invoice-dollar'],
                ['bom', 'BOM', 'fa-list-check'], ['transmittal', 'Transmittal', 'fa-truck-ramp-box'], ['dr', 'Delivery receipt', 'fa-truck'], ['payment', 'Client payments', 'fa-hand-holding-dollar'],
            ];
            const stCls = s => /paid|approved|delivered|received|closed/i.test(s) ? 'bg-emerald-50 text-emerald-700' : /return|reject/i.test(s) ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-700';
            function docList(p) {
                const list = p.docs[docTab] || [];
                if (!list.length) return `<p class="text-xs text-gray-400 py-8 text-center">None yet.</p>`;
                return `<div class="divide-y divide-gray-100">${list.map((d, i) => `<div class="py-2.5">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0"><p class="text-xs font-semibold text-gray-900 truncate">${esc(d.no || '—')}${d.sub ? ` <span class="font-normal text-gray-400">· ${esc(d.sub)}</span>` : ''}</p>
                            <p class="text-[11px] text-gray-400 truncate">${d.date ? fmtD(d.date) : ''}${d.scope ? ' · ' + esc(d.scope) : ''}${d.items && !Array.isArray(d.items) ? ' · ' + d.items + ' items' : ''}${d.change_orders ? ' · change orders ' + money(d.change_orders) : ''}</p></div>
                        <div class="text-right shrink-0">${d.amount !== undefined ? `<p class="text-xs font-bold text-gray-900 tabular-nums">${money(d.amount)}</p>` : ''}<span class="inline-block mt-0.5 px-2 py-0.5 rounded-full text-[10px] font-semibold ${stCls(d.status)}">${esc(d.status)}</span>
                            ${d.id ? `<button type="button" data-viewdoc="${esc(docTab)}:${d.id}" class="ml-1 inline-flex items-center gap-1 mt-0.5 px-2 py-0.5 rounded-full text-[10px] font-semibold border border-amber-300 text-amber-700 hover:bg-amber-50"><i class="fa-solid fa-eye"></i> View</button>` : ''}</div>
                    </div>
                    ${Array.isArray(d.items) && d.items.length ? `<details class="mt-1.5"><summary class="text-[11px] text-amber-700 font-semibold cursor-pointer select-none">${d.items.length} item${d.items.length === 1 ? '' : 's'}</summary>
                        <div class="mt-1.5 rounded-lg border border-gray-100 divide-y divide-gray-100">${d.items.map(it => `<div class="flex items-start justify-between gap-3 px-3 py-1.5 text-[11px]"><span class="min-w-0 text-gray-700 break-words">${esc(it.name)} <span class="text-gray-400">· ${it.qty} ${esc(it.unit || '')} × ${money(it.price)}</span></span><span class="tabular-nums font-semibold text-gray-900 shrink-0">${money(it.amount)}</span></div>`).join('')}</div></details>` : ''}
                </div>`).join('')}</div>`;
            }
            function renderView(p) {
                const rem = p.contract - p.cost, st = ST[p.status] || ST.no_contract;
                F('pmTitle').textContent = p.client || '—';
                F('pmSub').textContent = `${p.control_no} · ${p.bom_n} BOM · ${p.pr_n} PR · ${p.tr_n} transmittal · ${p.dr_n} DR`;
                const tile = (l, v, cls = 'text-gray-900', sub = '') => `<div class="bg-white border border-gray-200 rounded-xl p-3 min-w-0"><p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 truncate">${l}</p><p class="text-base md:text-lg font-bold tabular-nums truncate ${cls}">${v}</p>${sub ? `<p class="text-[10px] text-gray-400 truncate">${sub}</p>` : ''}</div>`;
                const banner = p.status === 'over' ? `Over budget by <b>${money(-rem)}</b> (${p.used_pct}% of contract).`
                    : p.status === 'near' ? `${p.used_pct}% of the contract is already used — only <b>${money(rem)}</b> left.`
                    : p.status === 'within' ? `${p.used_pct}% of the contract used — <b>${money(rem)}</b> left.` : 'No contract amount set for this client yet.';
                const keepScroll = F('pcoModal').scrollTop;
                F('pmBody').innerHTML = `
                    <div class="flex items-start gap-3 rounded-xl border px-4 py-3 ${st[1]}"><i class="fa-solid ${st[2]} mt-0.5"></i><div class="text-xs leading-relaxed"><b>${st[0]}.</b> ${banner}
                        ${p.projected_over && p.status !== 'over' ? `<br>Pending labor quotations (${money(p.labor_pending)}) will push it over the contract.` : ''}</div></div>
                    <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                        ${tile('Contract (budget)', money(p.contract))}
                        ${tile('Total cost', money(p.cost), p.status === 'over' ? 'text-red-600' : 'text-gray-900', 'Materials + labor')}
                        ${tile('Spent so far', money(p.spent), 'text-gray-900', 'Paid PR + approved labor billing')}
                        ${p.contract <= 0 ? tile('Remaining', '—', 'text-gray-400', 'No contract yet') : tile(rem < 0 ? 'Over by' : 'Remaining', money(Math.abs(rem)), rem < 0 ? 'text-red-600' : 'text-emerald-600')}
                        ${tile('Client paid', money(p.client_paid), 'text-gray-900', p.contract > 0 ? Math.min(100, Math.round(p.client_paid / p.contract * 100)) + '% of contract' : '')}
                    </div>
                    <div class="bg-white border border-gray-200 rounded-xl p-4">
                        <div class="flex flex-wrap justify-between gap-2 mb-2"><p class="text-xs font-semibold text-gray-900">Budget used</p><p class="text-[11px] tabular-nums text-gray-500">${money(p.cost)} of ${money(p.contract)}</p></div>
                        ${meter(p, 'h-4')}
                        <div class="flex flex-wrap gap-3 pt-2 text-[10px] text-gray-500"><span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm" style="background:${C.mat}"></span>Materials ${money(p.mat)}</span><span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm" style="background:${C.labor}"></span>Labor ${money(p.labor)}</span>${p.labor_pending ? `<span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm pco-stripe"></span>Pending labor ${money(p.labor_pending)}</span>` : ''}<span class="flex items-center gap-1"><span class="w-0.5 h-3 bg-gray-900"></span>Contract</span>${p.contract > 0 && p.cost > p.contract ? '<span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm pco-over"></span>Over the contract</span>' : ''}</div>
                    </div>
                    <div class="grid lg:grid-cols-[280px_minmax(0,1fr)] gap-4">
                        <div class="bg-white border border-gray-200 rounded-xl p-4 min-w-0"><p class="text-xs font-semibold text-gray-900 mb-3">Where the money goes</p>
                            ${donut([{ l: 'Materials (PR)', v: p.mat, c: C.mat }, { l: 'Labor', v: p.labor, c: C.labor }, { l: 'Pending labor', v: p.labor_pending, c: C.pending, cls: 'pco-stripe' }], short(p.projected), 'total cost', true)}
                            <div class="mt-3 pt-3 border-t border-gray-100 space-y-1 text-[11px]">
                                <div class="flex justify-between"><span class="text-gray-500">Materials paid</span><span class="tabular-nums font-semibold">${money(p.mat_paid)}</span></div>
                                <div class="flex justify-between"><span class="text-gray-500">Materials waiting approval</span><span class="tabular-nums font-semibold">${money(p.mat_pending)}</span></div>
                                <div class="flex justify-between"><span class="text-gray-500">Labor billed (approved)</span><span class="tabular-nums font-semibold">${money(p.labor_paid)}</span></div>
                            </div></div>
                        <div class="bg-white border border-gray-200 rounded-xl p-4 min-w-0"><p class="text-xs font-semibold text-gray-900 mb-2">Spending over time</p>${lineChart(p)}</div>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                        <div class="px-4 pt-3 flex flex-wrap gap-1.5 border-b border-gray-100 pb-3">${DOCS.map(([k, l, i]) => `<button type="button" data-doc="${k}" class="px-2.5 py-1 rounded-full text-[11px] font-semibold border ${docTab === k ? 'bg-amber-700 border-amber-700 text-white' : 'border-gray-200 text-gray-600 hover:border-amber-400'}"><i class="fa-solid ${i} mr-1"></i>${l} <span class="opacity-80">${(p.docs[k] || []).length}</span></button>`).join('')}</div>
                        <div class="px-4">${docList(p)}</div>
                    </div>`;
                F('pcoModal').scrollTop = keepScroll;
            }
            // ── Tingnan ang dokumento ──
            const isDT = v => /^\d{4}-\d{2}-\d{2}( \d{2}:\d{2}(:\d{2})?)?$/.test(String(v));
            const fmtV = v => {
                if (v && typeof v === 'object' && 'money' in v) return money(v.money);
                if (isDT(v)) { const s = String(v); const d = fmtD(s.slice(0, 10)); return s.length > 10 ? d + ' ' + new Date(s.replace(' ', 'T')).toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' }) : d; }
                return esc(String(v));
            };
            const num = v => { const n = Number(v); return isNaN(n) ? esc(String(v ?? '')) : (Number.isInteger(n) ? n : n.toLocaleString('en-PH', { maximumFractionDigits: 2 })); };
            let curDoc = null;
            function paperHtml(d) {
                const fields = d.fields.filter(f => f.key !== 'status');
                const head = `<div class="flex items-center gap-3 border-b-[3px] border-amber-600 pb-3 mb-4">
                    <img src="${esc(LOGO)}" alt="" class="h-11 w-auto" onerror="this.remove()">
                    <div class="min-w-0 flex-1"><p class="text-[10px] font-bold uppercase tracking-[0.15em] text-gray-500">NobleHome Construction Corporation</p>
                        <p class="text-base font-bold text-gray-900 leading-tight">${esc(d.title)}</p><p class="text-xs text-gray-500 truncate">${esc(d.no || '')}</p></div>
                    ${d.status ? `<span class="shrink-0 px-2.5 py-1 rounded-full text-[10px] font-semibold ${stCls(d.status)}">${esc(d.status.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()))}</span>` : ''}</div>`;
                const grid = fields.length ? `<div class="grid sm:grid-cols-2 gap-x-6 gap-y-2 mb-4">${fields.map(f => `<div class="min-w-0 border-b border-gray-100 pb-1.5"><p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">${esc(f.label)}</p><p class="text-xs text-gray-900 break-words">${fmtV(f.value)}</p></div>`).join('')}</div>` : '';
                const it = d.items;
                const items = it.rows.length ? `<p class="text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-1.5">Items</p>
                    <div class="overflow-x-auto mb-4 border border-gray-200 rounded-lg"><table class="w-full text-[11px]"><thead class="bg-gray-50 text-gray-500"><tr>${it.cols.map(c => `<th class="px-2.5 py-2 font-semibold whitespace-nowrap ${c.money ? 'text-right' : 'text-left'}">${esc(c.label)}</th>`).join('')}</tr></thead>
                    <tbody class="divide-y divide-gray-100">${it.rows.map(r => `<tr>${it.cols.map(c => {
                        const v = r[c.key];
                        if (c.file) return `<td class="px-2.5 py-1.5">${v ? `<a href="${esc(v)}" target="_blank" rel="noopener"><img src="${esc(v)}" alt="" class="h-10 w-10 object-cover rounded border border-gray-200" onerror="this.replaceWith(Object.assign(document.createElement('span'),{textContent:'File',className:'text-amber-700 underline'}))"></a>` : ''}</td>`;
                        return `<td class="px-2.5 py-1.5 align-top ${c.money ? 'text-right tabular-nums whitespace-nowrap' : 'text-gray-700'}">${c.money ? money(v) : /^(qty|quantity)$/.test(c.key) ? num(v) : esc(String(v ?? ''))}</td>`;
                    }).join('')}</tr>`).join('')}</tbody></table></div>` : '';
                const sigs = d.signatures.length ? `<p class="text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-2">Signatures</p><div class="grid grid-cols-2 sm:grid-cols-3 gap-4 mb-4">${d.signatures.map(g => `<div class="min-w-0"><div class="h-14 flex items-end border-b border-gray-800"><img src="${esc(g.url)}" alt="" class="max-h-14 max-w-full object-contain" onerror="this.remove()"></div>
                    <p class="text-xs font-semibold text-gray-900 truncate mt-1">${esc(g.name || '—')}</p><p class="text-[10px] text-gray-500 truncate">${esc(g.label)}${g.title ? ' · ' + esc(g.title) : ''}</p></div>`).join('')}</div>` : '';
                const files = d.files.length ? `<p class="text-[11px] font-bold uppercase tracking-wide text-gray-500 mb-2">Attachments</p><div class="flex flex-wrap gap-3">${d.files.map(f => `<a href="${esc(f.url)}" target="_blank" rel="noopener" class="block w-40 max-w-full border border-gray-200 rounded-lg overflow-hidden hover:border-amber-400">
                    ${f.image ? `<img src="${esc(f.url)}" alt="" class="w-full h-28 object-cover bg-gray-50" onerror="this.remove()">` : `<div class="h-16 flex items-center justify-center bg-gray-50 text-amber-700"><i class="fa-solid fa-file-lines text-2xl"></i></div>`}
                    <p class="px-2 py-1.5 text-[11px] font-semibold text-gray-700 truncate"><i class="fa-solid fa-up-right-from-square mr-1 text-gray-400"></i>${esc(f.label)}</p></a>`).join('')}</div>` : '';
                return head + grid + items + sigs + files + (!grid && !items && !sigs && !files ? '<p class="text-xs text-gray-400 py-6 text-center">No details recorded.</p>' : '');
            }
            async function openDoc(type, id) {
                F('pdKind').textContent = 'Loading…'; F('pdPrint').classList.add('hidden'); curDoc = null;
                F('pdPaper').innerHTML = `<div class="py-16 text-center text-xs text-gray-400"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Loading…</div>`;
                F('pcoDoc').classList.remove('hidden'); F('pcoDoc').classList.add('flex');
                try {
                    const j = await api('doc', { type, id });
                    curDoc = j.doc; F('pdKind').textContent = curDoc.title + (curDoc.no ? ' · ' + curDoc.no : '');
                    F('pdPaper').innerHTML = paperHtml(curDoc); F('pdPrint').classList.remove('hidden');
                } catch (e) { F('pdKind').textContent = 'Document'; F('pdPaper').innerHTML = `<div class="py-16 text-center text-xs text-red-600">${esc(e.message)}</div>`; }
            }
            function closeDoc() { F('pcoDoc').classList.add('hidden'); F('pcoDoc').classList.remove('flex'); curDoc = null; }
            F('pdClose').addEventListener('click', closeDoc);
            F('pcoDoc').addEventListener('click', e => { if (e.target === F('pcoDoc')) closeDoc(); });
            F('pdPrint').addEventListener('click', () => { if (!curDoc) return; F('pcoPrint').innerHTML = `<div style="max-width:760px;margin:0 auto">${paperHtml(curDoc)}</div>`; window.print(); });

            async function openProject(id, quiet) {
                if (!quiet) { F('pmTitle').textContent = 'Loading…'; F('pmSub').textContent = ''; F('pmBody').innerHTML = `<div class="py-16 text-center text-xs text-gray-400"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Loading…</div>`; F('pcoModal').classList.remove('hidden'); F('pcoModal').classList.add('flex'); }
                try {
                    const j = await api('view', { id });
                    if (quiet && (F('pcoModal').classList.contains('hidden') || !cur || cur.id !== id)) return;   // isinara na
                    cur = j.project; renderView(cur);
                } catch (e) { if (!quiet) F('pmBody').innerHTML = `<div class="py-16 text-center text-xs text-red-600">${esc(e.message)}</div>`; }
            }
            function closeM() { F('pcoModal').classList.add('hidden'); F('pcoModal').classList.remove('flex'); cur = null; }

            document.addEventListener('click', e => {
                const v = e.target.closest('[data-viewdoc]'); if (v) { const [t, i] = v.dataset.viewdoc.split(':'); openDoc(t, +i); return; }
                const o = e.target.closest('[data-open]'); if (o) { cur = { id: +o.dataset.open }; openProject(+o.dataset.open); return; }
                const t = e.target.closest('.pco-tab'); if (t) { tab = t.dataset.tab; memo.set('tab', tab); render(); return; }
                const d = e.target.closest('[data-doc]'); if (d && cur && cur.docs) { docTab = d.dataset.doc; memo.set('doc', docTab); renderView(cur); }
            });
            F('pmClose').addEventListener('click', closeM);
            F('pcoModal').addEventListener('click', e => { if (e.target === F('pcoModal')) closeM(); });
            document.addEventListener('keydown', e => { if (e.key !== 'Escape') return; if (!F('pcoDoc').classList.contains('hidden')) closeDoc(); else if (!F('pcoModal').classList.contains('hidden')) closeM(); });
            F('pcoSearch').addEventListener('input', render);
            F('pcoSort').addEventListener('change', () => { memo.set('sort', F('pcoSort').value); render(); });
            tab = memo.get('tab') || 'all'; if (!ST[tab] && tab !== 'all') tab = 'all';
            docTab = memo.get('doc') || 'pr'; if (!DOCS.some(x => x[0] === docTab)) docTab = 'pr';
            const ss = memo.get('sort'); if (ss && [...F('pcoSort').options].some(o => o.value === ss)) F('pcoSort').value = ss;
            load();

            // ═════════════ REALTIME (koneksyon ng sidebar: window.NH_RT) ═════════════
            // May bagong PR, bayad, labor o billing kahit saan → i-refresh ang listahan at ang bukas na project.
            const RT_TYPES = /^(crm_payment|crm_project|pr_|po_|transmittal|pc_|dr_|labor_|billing_)/i;
            const rtLive = () => !!(window.NH_RT && window.NH_RT.enabled && window.NH_RT.isLive());
            let rtMissed = false, rtPoll = null, rtLoading = false, rtAgain = false;
            async function rtLoad() {
                if (rtLoading) { rtAgain = true; return; }
                rtLoading = true;
                try { await load(true); if (cur && cur.docs && !F('pcoModal').classList.contains('hidden')) await openProject(cur.id, true); }
                catch (e) { } finally { rtLoading = false; if (rtAgain) { rtAgain = false; rtLoad(); } }
            }
            function rtSync() { if (document.hidden) { rtMissed = true; return; } rtMissed = false; rtLoad(); }
            function rtStartPolling() {
                if (rtPoll) clearInterval(rtPoll);
                rtPoll = setInterval(() => { if (!document.hidden) rtLoad(); }, rtLive() ? 60000 : 30000);
            }
            (function hookRealtime() {
                const hook = () => {
                    if (!window.NH_RT || !window.NH_RT.enabled || hookRealtime.done) return;
                    hookRealtime.done = true;
                    let t = null;
                    window.NH_RT.on('*', 'changed', payload => {
                        const type = String(payload?.type || '');
                        if (type && !RT_TYPES.test(type)) return;
                        clearTimeout(t); t = setTimeout(rtSync, 400);
                    });
                    window.NH_RT.onLive(live => {
                        F('pcoLiveDot').className = 'w-2 h-2 rounded-full ' + (live ? 'bg-green-500 animate-pulse' : 'bg-amber-400');
                        F('pcoLiveText').textContent = live ? 'Live' : 'Auto-refresh';
                        if (!document.hidden) rtStartPolling();
                        if (live && lastSig) rtSync();
                    });
                };
                hook();
                if (!hookRealtime.done) document.addEventListener('DOMContentLoaded', hook);
            })();
            setInterval(() => { if (rtMissed && !document.hidden) rtSync(); }, 5000);
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) { if (rtPoll) clearInterval(rtPoll); }
                else { rtSync(); rtStartPolling(); }
            });
            rtStartPolling();
        })();
    </script>
</body>

</html>