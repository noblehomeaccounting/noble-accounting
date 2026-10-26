<?php
// coordinator-timeline.php  —  Project Coordinator: Timeline (Gantt) ng proyekto
// Magagawa lang kapag approved na ng Superadmin ang Labor Quotation.

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PC];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$tlAjaxUrl = BASE_URL . '/coordinatortimelineajax';
$laborPageUrl = BASE_URL . '/coordinatorlabor';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Timeline</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/ui-pc/css/pc/coordinator-timeline.css?v=<?= @filemtime(ROOT_PATH . '/admin/ui-pc/css/pc/coordinator-timeline.css') ?: 1 ?>">
</head>

<body class="bg-gray-50">
    <main id="main-content" class="md:ml-56 pt-20 md:pt-5 min-h-screen p-4 md:p-8 transition-all duration-300">

        <!-- ═══ HEADER ═══ -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-5">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-chart-gantt text-lg"></i></div>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-amber-700">Operations · Timeline
                    </p>
                    <h1 class="text-xl font-bold text-gray-900 leading-tight">Project Timeline</h1>
                    <p class="text-xs text-gray-500">Plan the work day by day once the labor quotation is approved.
                        Print it for the client and the site.</p>
                </div>
            </div>
            <a href="<?= htmlspecialchars($laborPageUrl) ?>"
                class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg px-3 py-2 hover:bg-gray-50"><i
                    class="fa-solid fa-helmet-safety"></i> Labor Quotations</a>
        </div>

        <!-- ═══ STAT TABS ═══ -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
            <?php foreach ([
                ['todo', 'No timeline yet', 'fa-pen-ruler', 'bg-amber-50 text-amber-700'],
                ['upcoming', 'Not started', 'fa-hourglass-start', 'bg-gray-100 text-gray-600'],
                ['ongoing', 'On-going', 'fa-person-digging', 'bg-sky-50 text-sky-600'],
                ['done', 'Finished', 'fa-flag-checkered', 'bg-emerald-50 text-emerald-600'],
                ['all', 'All', 'fa-layer-group', 'bg-gray-100 text-gray-600'],
            ] as [$k, $label, $icon, $cls]): ?>
                <button type="button" data-tab="<?= $k ?>"
                    class="tp-tab text-left bg-white border border-gray-200 rounded-xl p-3.5 flex items-center gap-3 shadow-sm min-w-0 transition hover:border-amber-400 data-[active=true]:border-amber-600 data-[active=true]:ring-2 data-[active=true]:ring-amber-100 data-[active=true]:bg-amber-50/40">
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

        <!-- ═══ LIST ═══ -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-gray-100">
                <div class="relative flex-1 min-w-[200px]">
                    <i
                        class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-xs"></i>
                    <input type="text" id="tpSearch" placeholder="Search client, DR no., quotation no., foreman…"
                        class="w-full h-9 bg-white border border-gray-200 rounded-lg pl-9 pr-3 text-xs outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100">
                </div>
            </div>
            <div id="tpInfo" class="px-4 py-2 text-[11px] text-gray-500 bg-gray-50 border-b border-gray-100">&nbsp;
            </div>
            <div id="tpList" class="divide-y divide-gray-100">
                <div class="px-6 py-14 text-center text-xs text-gray-400"><i
                        class="fa-solid fa-spinner fa-spin mr-1"></i> Loading…</div>
            </div>
        </div>

        <div id="tpToast"
            class="fixed bottom-4 right-4 z-[10000] flex flex-col gap-2.5 pointer-events-none w-[340px] max-w-[calc(100vw-2rem)]">
        </div>
    </main>

    <!-- ═══ EDITOR MODAL ═══ -->
    <div id="tlModal"
        class="hidden fixed inset-0 z-[9999] items-start justify-center bg-black/50 p-2 md:p-4 overflow-y-auto">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-7xl my-auto overflow-hidden">
            <div class="dr-hazard h-1.5"></div>
            <div class="flex items-start justify-between gap-3 px-5 py-4 border-b border-gray-100">
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-amber-700">Timeline</p>
                    <p id="tlTitle" class="text-base font-semibold text-gray-900 truncate"></p>
                    <p id="tlSub" class="text-[11px] text-gray-400 truncate"></p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" id="tlWholeBtn" title="See the whole timeline on one screen"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-amber-800 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100"><i
                            class="fa-solid fa-expand"></i> <span class="hidden sm:inline">Whole view</span></button>
                    <button type="button" id="tlPrint"
                        class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-gray-900 rounded-lg hover:bg-gray-800 disabled:bg-gray-200 disabled:text-gray-400 disabled:cursor-not-allowed"><i
                            class="fa-solid fa-print"></i> <span class="hidden sm:inline">Print / Save
                            PDF</span></button>
                    <button type="button" id="tlClose"
                        class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 shrink-0" aria-label="Close"><i class="fa-solid fa-xmark text-base leading-none"></i></button>
                </div>
            </div>
            <form id="tlForm" novalidate>
                <div class="p-4 md:p-5 space-y-4">
                    <!-- 1) Details -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <label class="col-span-2 md:col-span-1"><span
                                class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Client
                                name <span class="text-red-500">*</span></span><input name="client_name" maxlength="255"
                                class="w-full border border-gray-200 rounded-lg px-2.5 py-2 text-xs outline-none focus:border-amber-600"><span
                                data-err="client_name"
                                class="hidden block text-[11px] text-red-600 mt-1"></span></label>
                        <label><span
                                class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Site
                                location</span><input name="site_location" maxlength="255"
                                class="w-full border border-gray-200 rounded-lg px-2.5 py-2 text-xs outline-none focus:border-amber-600"></label>
                        <label><span
                                class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Project
                                scope</span><input name="project_scope" maxlength="255"
                                placeholder="e.g. RENOVATION OFFICE"
                                class="w-full border border-gray-200 rounded-lg px-2.5 py-2 text-xs outline-none focus:border-amber-600"></label>
                        <label class="col-span-2 md:col-span-1"><span
                                class="block text-[10px] font-semibold uppercase tracking-wider text-amber-700 mb-1"><i
                                    class="fa-solid fa-flag mr-0.5"></i> Project start <span
                                    class="text-red-500">*</span></span><input name="project_start" type="date"
                                class="w-full border-2 border-amber-300 bg-amber-50/40 rounded-lg px-2.5 py-1.5 text-xs font-semibold outline-none focus:border-amber-600"><span
                                data-err="project_start"
                                class="hidden block text-[11px] text-red-600 mt-1"></span></label>
                    </div>

                    <!-- buod -->
                    <div id="tlSummary" class="flex flex-wrap items-center gap-2 text-[11px]"></div>

                    <!-- 2) Tasks + Gantt (parang Excel) -->
                    <div class="rounded-xl border border-gray-200 overflow-hidden">
                        <div class="flex flex-wrap items-center gap-2 px-3 py-2 bg-gray-50 border-b border-gray-200">
                            <button type="button" id="tlAddPhase"
                                class="inline-flex items-center gap-1.5 px-2.5 py-1.5 text-[11px] font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800"><i
                                    class="fa-solid fa-plus"></i> Phase</button>
                            <div id="tlQuick" class="flex flex-wrap gap-1.5"></div>
                            <p class="ml-auto text-[10.5px] text-gray-500 flex items-center gap-3">
                                <span><span class="inline-block w-3 h-3 align-middle rounded-sm bg-gray-400 mr-1"
                                        style="outline:2px dashed #f59e0b;outline-offset:1px"></span>Own start</span>
                                <span><span
                                        class="inline-block w-3 h-3 align-middle rounded-sm bg-gray-400 opacity-50 mr-1"></span>✓
                                    Done</span>
                                <span><span
                                        class="inline-block w-0.5 h-3 align-middle bg-red-600 mr-1.5"></span>Today</span>
                            </p>
                        </div>
                        <div class="flex flex-col lg:flex-row">
                            <div id="tlLeftWrap"
                                class="tl-pane lg:w-[660px] shrink-0 lg:border-r border-gray-200 tl-scroll">
                                <table class="w-full min-w-[620px] table-fixed text-xs" id="tlLeft"></table>
                            </div>
                            <div id="tlRight"
                                class="tl-pane flex-1 min-w-0 tl-scroll border-t lg:border-t-0 border-gray-200"></div>
                        </div>
                    </div>
                    <p class="text-[10.5px] text-gray-500"><i
                            class="fa-solid fa-circle-info mr-1 text-amber-700"></i>Type the task and how many
                        <b>days</b>. The start date follows the task above it. Change the start only if it overlaps
                        another task. Press <b>↺</b> to make it follow again.</p>
                    <p id="tlErr"
                        class="hidden rounded-lg bg-red-50 border border-red-200 px-3 py-2 text-[11px] text-red-700">
                    </p>
                </div>
                <div
                    class="flex flex-wrap items-center justify-between gap-2 px-5 py-3 bg-gray-50 border-t border-gray-100">
                    <p id="tlNote" class="text-[10.5px] text-gray-400"></p>
                    <div class="flex items-center gap-2 ml-auto">
                        <button type="button" id="tlCancel"
                            class="px-3.5 py-2 text-xs font-semibold text-gray-500 bg-white border border-gray-200 rounded-lg hover:bg-gray-100">Close</button>
                        <button type="submit" id="tlSave"
                            class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:opacity-60"><i
                                class="fa-solid fa-floppy-disk"></i> <span>Save timeline</span></button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- ═══ WHOLE VIEW (buong timeline sa isang screen) ═══ -->
    <div id="tlWhole" class="hidden fixed inset-0 z-[10001] bg-white flex-col">
        <div class="dr-hazard h-1.5 shrink-0"></div>
        <div class="flex flex-wrap items-center gap-3 px-4 md:px-6 py-3 border-b border-gray-200 shrink-0">
            <div class="min-w-0 flex-1">
                <p class="text-[10px] font-bold uppercase tracking-[0.15em] text-amber-700">Whole timeline</p>
                <p id="twTitle" class="text-base font-bold text-gray-900 truncate"></p>
                <p id="twSub" class="text-[11px] text-gray-500 truncate"></p>
            </div>
            <div id="twStats" class="flex flex-wrap items-center gap-2 text-[11px]"></div>
            <div class="flex items-center gap-2 shrink-0">
                <button type="button" id="twPrint" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-gray-900 rounded-lg hover:bg-gray-800 disabled:bg-gray-200 disabled:text-gray-400 disabled:cursor-not-allowed"><i class="fa-solid fa-print"></i> <span class="hidden sm:inline">Print</span></button>
                <button type="button" id="twClose" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50"><i class="fa-solid fa-compress"></i> Close</button>
            </div>
        </div>
        <div id="twBody" class="flex-1 min-h-0 overflow-y-auto overflow-x-hidden px-4 md:px-6 py-4"></div>
        <div id="twLegend" class="shrink-0 flex flex-wrap items-center gap-x-4 gap-y-1 px-4 md:px-6 py-2.5 border-t border-gray-200 bg-gray-50 text-[10.5px] text-gray-600"></div>
    </div>

    <script>
        (function () {
            'use strict';
            const AJAX = <?= json_encode($tlAjaxUrl) ?>;
            const LOGO = <?= json_encode(BASE_URL . '/icon/logo.webp') ?>;
            const F = id => document.getElementById(id);
            let rows = [], tab = 'todo', today = '', lastSig = '', ed = null;   // ed = { info, timeline, dirty }

            // ── helpers ──
            const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
            const toD = s => { const [y, m, d] = String(s).slice(0, 10).split('-').map(Number); return new Date(y, m - 1, d); };
            const ymd = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
            const addDays = (s, n) => { const d = toD(s); d.setDate(d.getDate() + n); return ymd(d); };
            const diffDays = (a, b) => Math.round((toD(b) - toD(a)) / 864e5);
            const okDate = s => /^\d{4}-\d{2}-\d{2}$/.test(s || '') && !isNaN(toD(s));
            const fmtY = s => s ? toD(s).toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';
            const fmtMDY = s => { const d = toD(s); return `${d.getMonth() + 1}/${d.getDate()}/${d.getFullYear()}`; };
            const fmtFinish = s => { const d = toD(s); return `${d.getDate()}-${d.toLocaleDateString('en-US', { month: 'short' })}-${String(d.getFullYear()).slice(2)}`; };
            const memo = {
                get: k => { try { return localStorage.getItem('nh-tl-' + k); } catch (e) { return null; } },
                set: (k, v) => { try { localStorage.setItem('nh-tl-' + k, v); } catch (e) { } },
                del: k => { try { localStorage.removeItem('nh-tl-' + k); } catch (e) { } },
            };
            const inp = 'w-full border border-gray-200 rounded-lg px-2.5 py-1.5 text-xs outline-none focus:border-amber-600';
            const lbl = (t, req) => `<span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">${t}${req ? ' <span class="text-red-500">*</span>' : ''}</span>`;

            function toast(message, type = 'success', duration = 3500) {
                const p = type === 'success'
                    ? { wrap: 'bg-green-50 border-green-200 text-green-700', icon: 'bg-green-200 text-green-700', sym: '✓' }
                    : { wrap: 'bg-red-50 border-red-200 text-red-700', icon: 'bg-red-200 text-red-700', sym: '!' };
                const t = document.createElement('div');
                t.className = `pointer-events-auto flex items-start gap-2.5 border rounded-lg shadow-lg px-4 py-3 text-sm ${p.wrap} transition-all duration-300`;
                t.innerHTML = `<span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold ${p.icon}">${p.sym}</span><span class="flex-1 leading-relaxed"></span>`;
                t.querySelector('.flex-1').textContent = message;
                F('tpToast').appendChild(t);
                setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, duration);
            }

            async function api(action, params = {}, post = false) {
                const ctrl = new AbortController();
                const timer = setTimeout(() => ctrl.abort(), 20000);
                try {
                    let body = null;
                    if (params instanceof URLSearchParams) body = params;
                    else { body = new URLSearchParams(); Object.entries(params).forEach(([k, v]) => body.append(k, v ?? '')); }
                    const url = post ? `${AJAX}?action=${action}` : `${AJAX}?action=${action}&${body}`;
                    const res = await fetch(url, post ? { method: 'POST', body, signal: ctrl.signal, cache: 'no-store' } : { signal: ctrl.signal, cache: 'no-store' });
                    const text = await res.text();
                    let d;
                    try { d = JSON.parse(text); }
                    catch (e) { throw new Error(/<html|<!doctype/i.test(text) ? 'The server returned a page instead of data. Check that "coordinatortimelineajax" is registered in the router, or reload if your session expired.' : 'The server sent an unexpected response.'); }
                    if (!d.success) { const er = new Error(d.message || 'Something went wrong.'); er.field = d.field; throw er; }
                    return d;
                } catch (e) {
                    if (e.name === 'AbortError') throw new Error('The server took too long to respond.');
                    throw e;
                } finally { clearTimeout(timer); }
            }

            // ── estado ng timeline batay sa petsa ngayon ──
            function stateOf(r) {
                if (!r.timeline_id) return 'todo';
                if (today < r.date_started) return 'upcoming';
                if (today > r.date_finish) return 'done';
                return 'ongoing';
            }
            const progress = r => {
                if (!r.timeline_id) return 0;
                const total = diffDays(r.date_started, r.date_finish) + 1;
                const passed = Math.min(total, Math.max(0, diffDays(r.date_started, today) + 1));
                return Math.round(passed / total * 100);
            };

            // ═════════════ GANTT (kapareho ng Excel) ═════════════
            // ═════════════ GANTT (shared: editor at print) ═════════════
            const PHASE_COLORS = ['#3b82f6', '#f59e0b', '#10b981', '#8b5cf6', '#ef4444', '#0ea5e9', '#ec4899', '#84cc16', '#f97316', '#14b8a6'];
            const WD = 'SMTWTFS';
            const MONTHS = ['JANUARY', 'FEBRUARY', 'MARCH', 'APRIL', 'MAY', 'JUNE', 'JULY', 'AUGUST', 'SEPTEMBER', 'OCTOBER', 'NOVEMBER', 'DECEMBER'];
            // items: [{type:'phase', name} | {type:'task', name, s, e, fixed}] → idinadagdag ang kulay at span ng phase
            function prepItems(items) {
                let pi = -1, cur = null;
                const out = items.map(x => ({ ...x }));
                out.forEach(x => {
                    if (x.type === 'phase') { pi++; x.color = PHASE_COLORS[pi % PHASE_COLORS.length]; cur = x; x.s = x.e = ''; return; }
                    x.color = cur ? cur.color : PHASE_COLORS[0];
                    if (cur && okDate(x.s) && okDate(x.e)) { if (!cur.s || x.s < cur.s) cur.s = x.s; if (!cur.e || x.e > cur.e) cur.e = x.e; }
                });
                return out;
            }
            function dateRange(items, minDays, fallback) {
                const v = items.filter(x => x.type === 'task' && okDate(x.s) && okDate(x.e));
                let s = v.length ? v.map(x => x.s).sort()[0] : fallback;
                let f = v.length ? v.map(x => x.e).sort().pop() : addDays(s, minDays - 1);
                if (diffDays(s, f) + 1 < minDays) f = addDays(s, minDays - 1);
                return Array.from({ length: Math.min(diffDays(s, f) + 1, 730) }, (_, i) => addDays(s, i));
            }
            // opts: { dayW, rowH, today:bool, status:bool, left:bool (DESCRIPTION/DURATION/START/END columns) }
            function ganttTable(items, dates, opts) {
                const W = opts.dayW, LW = opts.leftW || [170, 52, 66, 66], small = W < 18;
                const dcls = d => { const w = toD(d).getDay(); return (w === 0 ? 'sun ' : '') + (w === 1 ? 'mon ' : '') + (opts.today && d === today ? 'today ' : ''); };
                const months = [];
                dates.forEach(d => { const k = d.slice(0, 7); const m = months[months.length - 1]; if (m && m.k === k) m.n++; else months.push({ k, n: 1 }); });
                const leftHead = opts.left ? `<th class="lh" rowspan="2">DESCRIPTION</th><th class="lh" rowspan="2">DURATION</th><th class="lh" rowspan="2">START DATE</th><th class="lh" rowspan="2">END DATE</th>` : '';
                const head = `<tr>${leftHead}${months.map(m => `<th class="mo" colspan="${m.n}">${m.n * W >= 110 ? MONTHS[+m.k.slice(5) - 1] + ' ' + m.k.slice(0, 4) : m.n * W >= 34 ? MONTHS[+m.k.slice(5) - 1].slice(0, 3) : ''}</th>`).join('')}</tr>
                    <tr>${(() => { let lastShown = -9; return dates.map((d, k) => { const dd = toD(d); let show = !small || d === today || ((dd.getDay() === 1 || dd.getDate() === 1) && k - lastShown >= 3 && !(dates.slice(k + 1, k + 3).includes(today))); if (show) lastShown = k; return `<th class="dy ${dcls(d)}" title="${fmtMDY(d)}">${show ? `<b>${dd.getDate()}</b>` : ''}<i>${WD[dd.getDay()]}</i></th>`; }).join(''); })()}</tr>`;
                // isang row: walang laman bago/pagkatapos, isang td na colspan para sa bar
                const spanRow = (s, e, inner) => {
                    if (!okDate(s) || !okDate(e) || e < dates[0] || s > dates[dates.length - 1]) return dates.map(d => `<td class="${dcls(d)}"></td>`).join('');
                    const a = Math.max(0, diffDays(dates[0], s)), b = Math.min(dates.length - 1, diffDays(dates[0], e));
                    return dates.slice(0, a).map(d => `<td class="${dcls(d)}"></td>`).join('')
                        + `<td colspan="${b - a + 1}" class="${dcls(dates[a]).replace('today ', '')}">${inner(b - a + 1, a)}</td>`
                        + dates.slice(b + 1).map(d => `<td class="${dcls(d)}"></td>`).join('');
                };
                const body = items.map(x => {
                    const dur = okDate(x.s) && okDate(x.e) ? diffDays(x.s, x.e) + 1 : '';
                    const left = opts.left ? `<td class="lc nm" style="${x.type === 'phase' ? `box-shadow: inset 4px 0 0 ${x.color}` : ''}" title="${esc(x.name)}">${esc(x.name)}</td><td class="lc c">${dur}</td><td class="lc c">${dur ? fmtMDY(x.s) : ''}</td><td class="lc c">${dur ? fmtMDY(x.e) : ''}</td>` : '';
                    if (x.type === 'phase') {
                        return `<tr class="ph" style="height:${opts.rowH}px">${left}${spanRow(x.s, x.e, () => `<div class="phbar" style="background:${x.color}33;border:1.5px solid ${x.color}"></div>`)}</tr>`;
                    }
                    return `<tr class="tk" style="height:${opts.rowH}px">${left}${spanRow(x.s, x.e, (n, a) => {
                        const done = opts.status && x.e < today, going = opts.status && x.s <= today && today <= x.e;
                        const pct = going ? Math.round((diffDays(x.s, today) + 0.5) / dur * 100) : 0;
                        const tm = opts.today && x.s <= today && today <= x.e ? `<span class="tm" style="left:${(diffDays(x.s, today) + 0.5) * W - 2}px"></span>` : '';
                        const label = n * W >= 46 ? `${done ? '✓ ' : ''}${dur} day${dur === 1 ? '' : 's'}` : (done ? '✓' : String(dur));
                        return `<div class="bar${x.fixed && opts.status ? ' fixed' : ''}${done ? ' done' : ''}" style="background:${x.color}" title="${esc(x.name)} · ${fmtMDY(x.s)} – ${fmtMDY(x.e)} (${dur} day${dur === 1 ? '' : 's'})">${pct ? `<span class="prog" style="width:${pct}%"></span>` : ''}${tm}<span class="lb">${label}</span></div>`;
                    })}</tr>`;
                }).join('');
                const width = (opts.left ? LW.reduce((a, b) => a + b, 0) : 0) + dates.length * W;
                const cols = (opts.left ? LW.map(w => `<col style="width:${w}px">`).join('') : '') + dates.map(() => `<col style="width:${W}px">`).join('');
                return { html: `<table class="gt${small ? ' sm' : ''}" style=""width:${width}px"><colgroup>${cols}</colgroup><thead>${head}</thead><tbody>${body}</tbody></table>`, width };
            }

            // Buong dokumento (para sa print): kapareho ng Excel na TIME LINE pero mas malinaw
            function ganttHtml(t) {
                const raw = [];
                let last = null;
                (t.tasks || []).forEach(x => {
                    if (x.phase_name !== last) { raw.push({ type: 'phase', name: x.phase_name }); last = x.phase_name; }
                    raw.push({ type: 'task', name: x.task, s: String(x.start_date).slice(0, 10), e: String(x.end_date).slice(0, 10) });
                });
                const items = prepItems(raw);
                const tasks = items.filter(x => x.type === 'task' && okDate(x.s));
                const start = tasks.length ? tasks.map(x => x.s).sort()[0] : null, finish = tasks.length ? tasks.map(x => x.e).sort().pop() : null;
                const g = tasks.length ? ganttTable(items, dateRange(items, 1, start), { dayW: 22, rowH: 22, today: false, status: false, left: true }) : null;
                const w = Math.max(g ? g.width : 0, 900);
                const phases = items.filter(x => x.type === 'phase');
                return `<div class="tldoc" style="width:${w}px">
                    <table class="top" style="width:100%"><tr>
                        <td style="width:170px;height:56px;text-align:center;border-right:1px solid #d1d5db"><img src="${LOGO}" alt="" style="height:44px"></td>
                        <td class="ttl">TIME LINE</td></tr></table>
                    <div style="display:flex;justify-content:space-between;gap:20px;margin:10px 0 14px">
                        <table class="meta" style="width:46%"><colgroup><col style="width:28%"><col></colgroup>
                            <tr><td class="k">CLIENT NAME</td><td>${esc(t.client_name || '')}</td></tr>
                            <tr><td class="k">SITE LOCATION</td><td>${esc(t.site_location || '')}</td></tr>
                            <tr><td class="k">PROJECT SCOPE</td><td>${esc(t.project_scope || '')}</td></tr></table>
                        <table class="meta" style="width:34%"><colgroup><col style="width:45%"><col></colgroup>
                            <tr><td class="k">DATE STARTED</td><td>${start ? fmtMDY(start) : ''}</td></tr>
                            <tr><td class="k">DATE FINISH</td><td>${finish ? fmtFinish(finish) : ''}</td></tr>
                            <tr><td class="k">TOTAL DURATION</td><td>${start ? (diffDays(start, finish) + 1) + ' calendar days' : ''}</td></tr></table>
                    </div>
                    ${g ? g.html + `<div class="legend">${phases.map(p => `<span><i style="background:${p.color}"></i>${esc(p.name)}</span>`).join('')}<span style="color:#dc2626">Red dates = Sunday</span></div>`
                        : '<p style="padding:24px;text-align:center;color:#9ca3af;font-size:12px">No tasks yet.</p>'}
                </div>`;
            }

            // ═════════════ LIST ═════════════
            const ST = {
                todo: { bar: 'bg-amber-500', pill: 'bg-amber-50 text-amber-700 ring-amber-600/20', icon: 'fa-pen-ruler', text: 'No timeline yet' },
                upcoming: { bar: 'bg-gray-400', pill: 'bg-gray-100 text-gray-600 ring-gray-500/20', icon: 'fa-hourglass-start', text: 'Not started' },
                ongoing: { bar: 'bg-sky-500', pill: 'bg-sky-50 text-sky-700 ring-sky-600/20', icon: 'fa-person-digging', text: 'On-going' },
                done: { bar: 'bg-emerald-600', pill: 'bg-emerald-50 text-emerald-700 ring-emerald-600/20', icon: 'fa-flag-checkered', text: 'Finished' },
            };
            function render() {
                const inTab = (r, k) => k === 'all' || stateOf(r) === k;
                document.querySelectorAll('.tp-tab').forEach(b => b.dataset.active = String(b.dataset.tab === tab));
                document.querySelectorAll('[data-count]').forEach(el => el.textContent = rows.filter(r => inTab(r, el.dataset.count)).length);
                const q = F('tpSearch').value.trim().toLowerCase();
                const list = rows.filter(r => inTab(r, tab) && (!q || `${r.client_name} ${r.dr_no} ${r.ref_no} ${r.project_name ?? ''} ${r.quote_no ?? ''} ${r.foreman_name ?? ''}`.toLowerCase().includes(q)));
                F('tpInfo').innerHTML = `<b class="text-gray-700">${list.length}</b> project${list.length === 1 ? '' : 's'}${q ? ' (filtered)' : ''} · only DRs with an approved labor quotation show here`;
                if (!list.length) {
                    const msg = q ? 'No match.' : { todo: 'Every approved project already has a timeline.', upcoming: 'No upcoming project.', ongoing: 'No on-going project today.', done: 'No finished project yet.', all: 'No project yet. A project shows here once its labor quotation is approved.' }[tab];
                    F('tpList').innerHTML = `<div class="px-6 py-14 text-center"><div class="w-12 h-12 mx-auto rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center mb-3"><i class="fa-solid fa-chart-gantt"></i></div><p class="text-sm font-semibold text-gray-700">${msg}</p></div>`;
                    return;
                }
                F('tpList').innerHTML = list.map(r => {
                    const st = stateOf(r), s = ST[st], pct = progress(r);
                    const tlDays = r.timeline_id ? diffDays(r.date_started, r.date_finish) + 1 : 0;
                    const over = r.timeline_id && r.quote_days && tlDays > +r.quote_days;
                    return `<div data-row="${r.dr_id}" class="tp-row relative flex flex-wrap items-center gap-x-5 gap-y-2 pl-5 pr-4 py-3.5">
                        <span class="absolute left-0 top-0 bottom-0 w-1 ${s.bar}"></span>
                        <div class="min-w-0 flex-1 basis-[260px]">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-bold text-gray-900 truncate">${esc(r.client_name)}</span>
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold ring-1 ${s.pill}"><i class="fa-solid ${s.icon} text-[9px]"></i>${s.text}</span>
                            </div>
                            <p class="text-[11px] text-gray-400 truncate mt-0.5"><span class="font-mono">DR# ${esc(r.dr_no)}</span> · <span class="font-mono">${esc(r.quote_no)}</span>${r.foreman_name ? ' · Foreman: ' + esc(r.foreman_name) : ''}</p>
                            ${r.timeline_id ? `<div class="mt-2 flex items-center gap-2 max-w-md"><div class="flex-1 h-1.5 rounded-full bg-gray-100 overflow-hidden"><div class="h-full ${st === 'done' ? 'bg-emerald-500' : 'bg-sky-500'}" style="width:${pct}%"></div></div><span class="text-[10.5px] text-gray-500 tabular-nums">${st === 'upcoming' ? 'starts in ' + diffDays(today, r.date_started) + ' day(s)' : st === 'done' ? 'done' : `day ${diffDays(r.date_started, today) + 1} of ${tlDays}`}</span></div>` : ''}
                            ${over ? `<p class="mt-1 text-[11px] text-orange-700"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Timeline is ${tlDays} days but the labor quotation says ${r.quote_days} days.</p>` : ''}
                        </div>
                        <dl class="grid grid-cols-3 gap-x-5 text-[11px] basis-[300px] grow sm:grow-0">
                            <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Start</dt><dd class="text-gray-700">${r.timeline_id ? fmtY(r.date_started) : '—'}</dd></div>
                            <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Finish</dt><dd class="text-gray-700">${r.timeline_id ? fmtY(r.date_finish) : '—'}</dd></div>
                            <div><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">${r.timeline_id ? 'Tasks' : 'Quote days'}</dt><dd class="text-gray-700">${r.timeline_id ? r.task_count : (r.quote_days || '—')}</dd></div>
                        </dl>
                        <div class="ml-auto shrink-0 flex items-center gap-1.5">${r.timeline_id ? `<button type="button" data-whole="${r.dr_id}" title="See the whole timeline" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-amber-800 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100"><i class="fa-solid fa-expand"></i> Whole view</button>` : ''}<button type="button" data-open="${r.dr_id}" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold rounded-lg ${r.timeline_id ? 'text-gray-700 bg-white border border-gray-200 hover:bg-gray-50' : 'text-white bg-amber-700 hover:bg-amber-800 shadow-sm'}"><i class="fa-solid ${r.timeline_id ? 'fa-pen' : 'fa-plus'}"></i> ${r.timeline_id ? 'Open timeline' : 'Make timeline'}</button></div>
                    </div>`;
                }).join('');
                markPicked();
            }
            function markPicked() {
                let id = null; try { id = sessionStorage.getItem('nh-tl-picked'); } catch (e) { }
                document.querySelectorAll('.tp-row').forEach(el => el.setAttribute('data-picked', el.dataset.row === id ? '1' : '0'));
            }
            document.querySelectorAll('.tp-tab').forEach(b => b.addEventListener('click', () => { tab = b.dataset.tab; memo.set('tab', tab); render(); }));
            F('tpSearch').addEventListener('input', render);
            F('tpList').addEventListener('click', async e => {
                const w = e.target.closest('[data-whole]');
                if (w) {
                    try { sessionStorage.setItem('nh-tl-picked', w.dataset.whole); } catch (er) { }
                    markPicked();
                    let d; try { d = await api('get', { dr_id: +w.dataset.whole }); } catch (er) { return toast(er.message, 'error'); }
                    if (!d.timeline) return toast('No timeline yet.', 'error');
                    today = d.today || today;
                    return openWhole(itemsFromTasks(d.timeline.tasks), d.timeline, d.info, d.timeline);
                }
                const b = e.target.closest('[data-open]'); if (!b) return;
                try { sessionStorage.setItem('nh-tl-picked', b.dataset.open); } catch (er) { }
                markPicked(); openEditor(+b.dataset.open);
            });

            async function load(silent) {
                try {
                    const d = await api('list');
                    today = d.today;
                    const sig = JSON.stringify(d.rows.map(r => [r.dr_id, r.timeline_id, r.date_started, r.date_finish, r.task_count]));
                    if (silent && sig === lastSig) return;
                    lastSig = sig; rows = d.rows; render();
                } catch (e) {
                    if (silent) return;
                    F('tpList').innerHTML = `<div class="px-6 py-12 text-center text-xs text-red-600">${esc(e.message)}<br><button type="button" id="tpRetry" class="mt-2 font-semibold text-amber-700 hover:underline">Retry</button></div>`;
                    F('tpRetry').addEventListener('click', () => load());
                }
            }

            // ═════════════ EDITOR (isang table na parang Excel: kaliwa = tasks, kanan = Gantt) ═════════════
            // rows: [{ type:'phase', name } | { type:'task', name, days, start, mode:'auto'|'fixed' }]
            // auto  = sumusunod sa task sa itaas (end + 1 araw); fixed = sariling start date (para sa sabay/overlap)
            const QUICK = ['GENERAL DISMANTLING & GARBAGE DISPOSAL', 'DELIVERY OF MATERIALS', 'INSTALLATION', 'WALL INSTALLATION', 'PAINTWORKS', 'PUNCH LIST', 'TURNOVER'];
            const TEMPLATE = () => [
                { type: 'phase', name: 'PLAN' },
                { type: 'task', name: 'SAMPLE APPROVED', days: 2, mode: 'auto' },
                { type: 'task', name: 'DRAWING APPROVED', days: 1, mode: 'auto' },
                { type: 'task', name: 'CUTTING LIST', days: 8, mode: 'auto' },
            ];
            const ROW_H = 34, HEAD_H = 64, GDAY = 22;
            const openM = () => { F('tlModal').classList.remove('hidden'); F('tlModal').classList.add('flex'); };
            function closeM() {
                if (ed && ed.dirty && !F('tlClose').dataset.sure) {
                    F('tlNote').innerHTML = '<span class="text-red-600 font-semibold">You have unsaved changes (kept as a draft on this device).</span> <button type="button" id="tlCloseAnyway" class="ml-1 font-semibold text-gray-700 underline">Close anyway</button>';
                    F('tlCloseAnyway').addEventListener('click', () => { F('tlClose').dataset.sure = '1'; closeM(); });
                    return;
                }
                delete F('tlClose').dataset.sure;
                F('tlModal').classList.add('hidden'); F('tlModal').classList.remove('flex'); ed = null; load(true);
            }
            F('tlClose').addEventListener('click', closeM);
            F('tlCancel').addEventListener('click', closeM);
            F('tlModal').addEventListener('click', e => { if (e.target === F('tlModal')) closeM(); });
            document.addEventListener('keydown', e => { if (e.key === 'Escape' && F('tlModal').classList.contains('flex')) closeM(); });

            const form = F('tlForm');
            // sabay na vertical scroll ng kaliwa at kanan
            (() => {
                const L = F('tlLeftWrap'), R = F('tlRight');
                L.addEventListener('scroll', () => { if (R.scrollTop !== L.scrollTop) R.scrollTop = L.scrollTop; }, { passive: true });
                R.addEventListener('scroll', () => { if (L.scrollTop !== R.scrollTop) L.scrollTop = R.scrollTop; }, { passive: true });
            })();

            async function openEditor(drId) {
                let d;
                try { d = await api('get', { dr_id: drId }); } catch (e) { return toast(e.message, 'error'); }
                today = d.today || today;
                const i = d.info, t = d.timeline;
                ed = { info: i, timeline: t, dirty: false, key: 'draft2-' + drId, rows: [] };
                F('tlTitle').textContent = t ? t.client_name : (i.quote_project || i.client_name);
                F('tlSub').textContent = `DR# ${i.dr_no} · ${i.quote_no} (approved${i.duration_days ? ', ' + i.duration_days + ' days' : ''}) · ${i.subcontractor || ''}`;

                let draft = null; try { draft = JSON.parse(memo.get(ed.key) || 'null'); } catch (e) { }
                let meta;
                if (draft) { meta = draft.meta; ed.rows = draft.rows; }
                else if (t) {
                    meta = { client_name: t.client_name, site_location: t.site_location || '', project_scope: t.project_scope || '', project_start: String(t.date_started).slice(0, 10) };
                    ed.rows = fromSaved(t.tasks, meta.project_start);
                } else {
                    meta = { client_name: (i.quote_project || i.client_name || '').toUpperCase(), site_location: (i.quote_location || i.address || '').toUpperCase(), project_scope: '', project_start: today };
                    ed.rows = TEMPLATE();
                }
                ['client_name', 'site_location', 'project_scope', 'project_start'].forEach(n => { form.elements[n].value = meta[n] || ''; form.elements[n].classList.remove('border-red-400'); });
                form.querySelectorAll('[data-err]').forEach(el => el.classList.add('hidden'));
                F('tlQuick').innerHTML = QUICK.map(q => `<button type="button" data-quick="${esc(q)}" class="px-2 py-1 rounded-full text-[10.5px] font-semibold bg-white border border-gray-200 text-gray-600 hover:border-amber-500 hover:text-amber-700">+ ${esc(q)}</button>`).join('');
                F('tlNote').textContent = draft ? 'Restored your unsaved draft.' : (t ? `Last saved ${fmtY(t.updated_at || t.created_at)}${t.updated_by ? ' by ' + t.updated_by : ''}.` : 'Started with the PLAN template. Change the days, then add your phases.');
                F('tlSave').querySelector('span').textContent = t ? 'Save changes' : 'Save timeline';
                F('tlErr').classList.add('hidden');
                ed.dirty = !!draft;
                renderLeft();
                update(false);
                openM();
            }

            // naka-save na tasks → rows (alamin kung auto o fixed ang start)
            function fromSaved(tasks, projectStart) {
                const out = []; let lastPhase = null, prevEnd = null;
                tasks.forEach(x => {
                    if (x.phase_name !== lastPhase) { out.push({ type: 'phase', name: x.phase_name }); lastPhase = x.phase_name; }
                    const expected = prevEnd ? addDays(prevEnd, 1) : projectStart;
                    const s = String(x.start_date).slice(0, 10), e = String(x.end_date).slice(0, 10);
                    out.push({ type: 'task', name: x.task, days: diffDays(s, e) + 1, start: s, mode: s === expected ? 'auto' : 'fixed' });
                    prevEnd = e;
                });
                return out;
            }

            // kalkulahin ang petsa ng bawat task
            function compute() {
                const ps = form.elements.project_start.value;
                let prevEnd = null;
                ed.rows.forEach(r => {
                    if (r.type !== 'task') return;
                    const days = Math.floor(+r.days);
                    const s = r.mode === 'fixed' && okDate(r.start) ? r.start : (prevEnd ? addDays(prevEnd, 1) : (okDate(ps) ? ps : ''));
                    r.cStart = s || '';
                    r.cEnd = s && days >= 1 ? addDays(s, Math.min(days, 730) - 1) : '';
                    if (r.cEnd) prevEnd = r.cEnd;
                });
            }

            const cellIn = 'w-full h-7 border border-transparent hover:border-gray-200 focus:border-amber-600 rounded-md px-2 text-xs outline-none bg-transparent focus:bg-white';
            const iconBtn = (act, icon, title, extra = '') => `<button type="button" data-act="${act}" title="${title}" class="w-6 h-6 rounded-md text-gray-400 hover:text-gray-700 hover:bg-gray-100 ${extra}"><i class="fa-solid ${icon} text-[10px]"></i></button>`;
            function renderLeft() {
                const head = `<colgroup><col><col style="width:58px"><col style="width:156px"><col style="width:80px"><col style="width:78px"></colgroup>
                    <thead><tr style="height:${HEAD_H}px" class="bg-[#e2efda] text-[10px] uppercase tracking-wider text-gray-700">
                        <th class="px-3 text-left font-bold border-b border-gray-200">Description</th><th class="px-1 text-center font-bold border-b border-gray-200">Days</th>
                        <th class="px-1 text-left font-bold border-b border-gray-200">Start</th><th class="px-1 text-left font-bold border-b border-gray-200">End</th><th class="border-b border-gray-200"></th></tr></thead>`;
                const body = ed.rows.map((r, i) => r.type === 'phase'
                    ? `<tr data-i="${i}" style="height:${ROW_H}px" class="bg-gray-100 border-b border-gray-200">
                        <td colspan="4" class="px-1.5"><input data-f="name" value="${esc(r.name)}" maxlength="150" placeholder="PHASE NAME, e.g. DELIVERY OF MATERIALS" class="${cellIn} font-bold uppercase tracking-wide"></td>
                        <td class="pr-1.5 text-right whitespace-nowrap tl-act">${iconBtn('addtask', 'fa-plus', 'Add task to this phase', 'text-amber-700')}${iconBtn('delphase', 'fa-trash-can', 'Remove phase')}</td></tr>`
                    : `<tr data-i="${i}" style="height:${ROW_H}px" class="border-b border-gray-100 hover:bg-amber-50/30">
                        <td class="pl-5 pr-1"><input data-f="name" value="${esc(r.name)}" maxlength="255" placeholder="Task, e.g. DISMANTLE" class="${cellIn} uppercase"></td>
                        <td class="px-1"><input data-f="days" type="number" min="1" max="730" step="1" value="${esc(r.days ?? '')}" placeholder="0" class="${cellIn} text-right font-semibold"></td>
                        <td class="px-1"><div class="flex items-center gap-0.5"><input data-f="start" type="date" class="${cellIn} px-1 min-w-0">${iconBtn('auto', 'fa-rotate-left', 'Follow the task above again', 'tl-auto shrink-0')}</div></td>
                        <td class="px-1 text-[11px] text-gray-700 tabular-nums" data-out="end"></td>
                        <td class="pr-1.5 text-right whitespace-nowrap">${iconBtn('up', 'fa-arrow-up', 'Move up')}${iconBtn('down', 'fa-arrow-down', 'Move down')}${iconBtn('deltask', 'fa-xmark', 'Remove task', 'hover:!text-red-600')}</td></tr>`).join('');
                F('tlLeft').innerHTML = head + `<tbody>${body}</tbody>`;
            }

            // i-update ang mga kusang petsa (hindi nire-render ang inputs para hindi mawala ang cursor)
            function update(markDirty = true) {
                compute();
                F('tlLeft').querySelectorAll('tbody tr').forEach(tr => {
                    const r = ed.rows[+tr.dataset.i];
                    if (!r || r.type !== 'task') return;
                    const st = tr.querySelector('[data-f=start]');
                    if (document.activeElement !== st) st.value = r.cStart;
                    st.classList.toggle('text-gray-400', r.mode !== 'fixed');
                    st.classList.toggle('font-semibold', r.mode === 'fixed');
                    st.classList.toggle('text-amber-800', r.mode === 'fixed');
                    st.title = r.mode === 'fixed' ? 'Own start date (overlaps)' : 'Follows the task above';
                    tr.querySelector('.tl-auto').classList.toggle('invisible', r.mode !== 'fixed');
                    tr.querySelector('[data-out=end]').textContent = r.cEnd ? fmtMDY(r.cEnd) : '—';
                });
                renderRight();
                summary();
                F('tlPrint').disabled = !(ed.timeline && !ed.dirty && !markDirty);
                F('tlPrint').title = F('tlPrint').disabled ? 'Save the timeline first, then print.' : '';
                if (markDirty) {
                    ed.dirty = true;
                    clearTimeout(draftT);
                    draftT = setTimeout(() => { if (ed) { memo.set(ed.key, JSON.stringify({ meta: readMeta(), rows: ed.rows.map(({ cStart, cEnd, ...r }) => r) })); F('tlNote').textContent = 'Draft saved on this device. Not saved to the system yet.'; } }, 400);
                }
            }
            let draftT = null;
            const readMeta = () => Object.fromEntries(['client_name', 'site_location', 'project_scope', 'project_start'].map(n => [n, form.elements[n].value.trim()]));

            // kanang bahagi: Gantt na naka-align sa bawat row ng editor
            function renderRight() {
                const raw = ed.rows.map(r => r.type === 'phase' ? { type: 'phase', name: r.name } : { type: 'task', name: r.name, s: r.cStart, e: r.cEnd, fixed: r.mode === 'fixed' });
                const items = prepItems(raw);
                const ps = form.elements.project_start.value;
                const dates = dateRange(items, 14, okDate(ps) ? ps : today);
                const keepX = F('tlRight').scrollLeft;
                F('tlRight').innerHTML = ganttTable(items, dates, { dayW: GDAY, rowH: ROW_H, today: true, status: true, left: false }).html;
                F('tlRight').scrollLeft = keepX;
                F('tlRight').scrollTop = F('tlLeftWrap').scrollTop;
                // kapantay ang dulo ng dalawang panig (may horizontal scrollbar ang Gantt)
                const sb = F('tlRight').offsetHeight - F('tlRight').clientHeight;
                F('tlLeftWrap').style.paddingBottom = window.innerWidth >= 1024 && sb > 0 ? sb + 'px' : '';
                // kulay ng phase sa kaliwa
                items.forEach((x, i) => { if (x.type === 'phase') { const td = F('tlLeft').querySelector(`tr[data-i="${i}"] td`); if (td) td.style.boxShadow = `inset 4px 0 0 ${x.color}`; } });
                if (!F('tlRight').dataset.scrolled) {
                    F('tlRight').dataset.scrolled = '1';
                    const k = dates.indexOf(today);
                    F('tlRight').scrollLeft = k > 3 ? (k - 3) * GDAY : 0;
                }
            }

            function summary() {
                const tasks = ed.rows.filter(r => r.type === 'task' && r.cStart && r.cEnd);
                if (!tasks.length) { F('tlSummary').innerHTML = '<span class="text-gray-400">Set the project start and the days of each task.</span>'; return; }
                const s = tasks.map(r => r.cStart).sort()[0], f = tasks.map(r => r.cEnd).sort().pop();
                const total = diffDays(s, f) + 1, qd = +ed.info.duration_days || 0;
                F('tlSummary').innerHTML = `
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-gray-100 text-gray-700"><i class="fa-regular fa-calendar"></i> <b>${fmtY(s)}</b> → <b>${fmtY(f)}</b></span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg ${qd && total > qd ? 'bg-orange-50 text-orange-700' : 'bg-emerald-50 text-emerald-700'}"><i class="fa-solid fa-ruler-horizontal"></i> ${total} calendar days${qd ? ` · quotation: ${qd} days` : ''}${qd && total > qd ? ' (over)' : ''}</span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-gray-100 text-gray-700"><i class="fa-solid fa-list-check"></i> ${tasks.length} task${tasks.length === 1 ? '' : 's'}</span>`;
            }

            // ── input: i-save sa rows, saka i-update ──
            F('tlLeft').addEventListener('input', e => {
                const tr = e.target.closest('tr[data-i]'); if (!tr) return;
                const r = ed.rows[+tr.dataset.i], f = e.target.dataset.f;
                if (f === 'name') r.name = e.target.value;
                if (f === 'days') r.days = e.target.value;
                if (f === 'start') { if (okDate(e.target.value)) { r.start = e.target.value; r.mode = 'fixed'; } }
                F('tlErr').classList.add('hidden');
                update();
            });
            ['client_name', 'site_location', 'project_scope', 'project_start'].forEach(n => form.elements[n].addEventListener('input', () => {
                form.elements[n].classList.remove('border-red-400');
                const el = form.querySelector(`[data-err="${n}"]`); if (el) el.classList.add('hidden');
                if (n === 'project_start') F('tlRight').dataset.scrolled = '';
                update();
            }));
            // Enter sa task → bagong task sa ilalim
            F('tlLeft').addEventListener('keydown', e => {
                if (e.key !== 'Enter' || !e.target.dataset.f) return;
                e.preventDefault();
                const i = +e.target.closest('tr').dataset.i;
                insertTask(ed.rows[i].type === 'phase' ? i : i);
            });

            function insertTask(afterIndex) {
                ed.rows.splice(afterIndex + 1, 0, { type: 'task', name: '', days: 1, mode: 'auto' });
                renderLeft(); update();
                F('tlLeft').querySelector(`tr[data-i="${afterIndex + 1}"] [data-f=name]`)?.focus();
            }
            // dulo ng phase (huling task bago ang susunod na phase)
            const phaseEnd = i => { let j = i; while (j + 1 < ed.rows.length && ed.rows[j + 1].type === 'task') j++; return j; };

            F('tlLeft').addEventListener('click', e => {
                const b = e.target.closest('[data-act]'); if (!b) return;
                const i = +b.closest('tr').dataset.i, r = ed.rows[i], act = b.dataset.act;
                if (act === 'addtask') return insertTask(phaseEnd(i));
                if (act === 'auto') { r.mode = 'auto'; delete r.start; update(); return; }
                if (act === 'deltask') {
                    ed.rows.splice(i, 1);
                    renderLeft(); update(); return;
                }
                if (act === 'up' || act === 'down') {
                    const j = act === 'up' ? i - 1 : i + 1;
                    if (j < 1 || j >= ed.rows.length) return;   // hindi puwedeng mauna sa unang phase
                    [ed.rows[i], ed.rows[j]] = [ed.rows[j], ed.rows[i]];
                    renderLeft(); update();
                    F('tlLeft').querySelector(`tr[data-i="${j}"] [data-act="${act}"]`)?.focus();
                    return;
                }
                if (act === 'delphase') {
                    const end = phaseEnd(i), count = end - i;
                    if (ed.rows.filter(x => x.type === 'phase').length === 1) { toast('A timeline needs at least one phase.', 'error'); return; }
                    if (count && !b.dataset.sure) {   // inline confirm
                        // inline confirm: buong row ang ginagamit para kasya ang text at buttons
                        b.closest('tr').innerHTML = `<td colspan="5" class="px-2 bg-red-50"><div class="flex items-center justify-between gap-2 text-[11px]">
                            <span class="text-red-700 font-semibold truncate min-w-0"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Remove “${esc(r.name || 'this phase')}” and its ${count} task${count === 1 ? '' : 's'}?</span>
                            <span class="shrink-0 flex items-center gap-1.5"><button type="button" data-act="delphase" data-sure="1" class="px-2.5 py-1 text-[10.5px] font-semibold text-white bg-red-600 rounded-md hover:bg-red-700">Remove</button><button type="button" data-act="keep" class="px-2.5 py-1 text-[10.5px] font-semibold text-gray-600 bg-white border border-gray-200 rounded-md hover:bg-gray-50">Keep</button></span></div></td>`;
                        return;
                    }
                    ed.rows.splice(i, count + 1);
                    if (ed.rows[0] && ed.rows[0].type !== 'phase') ed.rows.unshift({ type: 'phase', name: '' });
                    renderLeft(); update(); return;
                }
                if (act === 'keep') { renderLeft(); update(false); }
            });

            function addPhase(name) {
                ed.rows.push({ type: 'phase', name: name || '' }, { type: 'task', name: '', days: 1, mode: 'auto' });
                renderLeft(); update();
                const n = ed.rows.length;
                F('tlLeft').querySelector(`tr[data-i="${name ? n - 1 : n - 2}"] [data-f=name]`)?.focus();
            }
            F('tlAddPhase').addEventListener('click', () => addPhase(''));
            F('tlQuick').addEventListener('click', e => { const b = e.target.closest('[data-quick]'); if (b) addPhase(b.dataset.quick); });

            // ── SAVE ──
            form.addEventListener('submit', async e => {
                e.preventDefault();
                const m = readMeta();
                const err = (msg, i) => {
                    F('tlErr').textContent = msg; F('tlErr').classList.remove('hidden');
                    const tr = i !== undefined ? F('tlLeft').querySelector(`tr[data-i="${i}"]`) : null;
                    if (tr) { tr.classList.add('!bg-red-50'); setTimeout(() => tr.classList.remove('!bg-red-50'), 2500); tr.querySelector('input')?.focus(); }
                    else F('tlErr').scrollIntoView({ behavior: 'smooth', block: 'center' });
                };
                const fieldErr = (n, msg) => { const el = form.querySelector(`[data-err="${n}"]`); el.textContent = msg; el.classList.remove('hidden'); form.elements[n].classList.add('border-red-400'); form.elements[n].focus(); };
                F('tlErr').classList.add('hidden');
                if (!m.client_name) return fieldErr('client_name', 'Client name is required.');
                if (!okDate(m.project_start)) return fieldErr('project_start', 'Set the project start date.');
                compute();
                const qs = new URLSearchParams();
                qs.append('dr_id', ed.info.dr_id);
                ['client_name', 'site_location', 'project_scope'].forEach(n => qs.append(n, m[n]));
                let phase = '', n = 0;
                for (let i = 0; i < ed.rows.length; i++) {
                    const r = ed.rows[i];
                    if (r.type === 'phase') { phase = r.name.trim().toUpperCase(); continue; }
                    const name = (r.name || '').trim();
                    if (!name) { if (+r.days >= 1 && r.mode === 'fixed') return err('Type the task name, or remove the row.', i); continue; }
                    if (!phase) { const p = ed.rows.slice(0, i).reverse().findIndex(x => x.type === 'phase'); return err(`Give a phase name for "${name.toUpperCase()}".`, p >= 0 ? i - 1 - p : i); }
                    if (!(Math.floor(+r.days) >= 1)) return err(`Enter how many days "${name.toUpperCase()}" takes.`, i);
                    if (!r.cStart || !r.cEnd) return err(`Could not get the dates of "${name.toUpperCase()}". Check the project start.`, i);
                    qs.append('phase[]', phase); qs.append('task[]', name.toUpperCase()); qs.append('start[]', r.cStart); qs.append('end[]', r.cEnd); n++;
                }
                if (!n) return err('Add at least one task with days.');
                const btn = F('tlSave'), txt = btn.querySelector('span').textContent;
                btn.disabled = true; btn.querySelector('span').textContent = 'Saving…';
                try {
                    clearTimeout(draftT);
                    await api('save', qs, true);
                    clearTimeout(draftT);
                    memo.del(ed.key);
                    toast('Timeline saved.');
                    const d = await api('get', { dr_id: ed.info.dr_id });
                    ed.timeline = d.timeline; ed.dirty = false;
                    F('tlNote').textContent = `Saved ${fmtY(d.timeline.updated_at)}. You can print it now.`;
                    btn.querySelector('span').textContent = 'Save changes';
                    update(false);
                    load(true);
                } catch (er) {
                    if (er.field === 'client_name') fieldErr('client_name', er.message);
                    else err(er.message);
                    btn.querySelector('span').textContent = txt;
                } finally { btn.disabled = false; }
            });

            // ── Print: naka-save na bersyon lang ──
            F('tlPrint').addEventListener('click', () => {
                if (!ed || !ed.timeline || ed.dirty) return toast('Save the timeline first, then print.', 'error');
                printTimeline(ed.timeline);
            });
            function printTimeline(tl) {
                const html = ganttHtml(tl);
                let area = F('nhPrintArea');
                if (!area) { area = document.createElement('div'); area.id = 'nhPrintArea'; document.body.appendChild(area); }
                area.innerHTML = html;
                // i-fit sa A3 landscape (~1480px na lapad)
                const w = area.firstElementChild.scrollWidth || area.firstElementChild.offsetWidth || 1480;
                area.style.zoom = String(Math.min(1, 1480 / Math.max(w, 1)));
                const imgs = [...area.querySelectorAll('img')].filter(i => !i.complete);
                Promise.all(imgs.map(i => new Promise(r => { i.onload = i.onerror = r; }))).then(() => { window.print(); setTimeout(() => { area.innerHTML = ''; area.style.zoom = ''; }, 500); });
            }

            // ═════════════ WHOLE VIEW: buong timeline, kasya sa lapad ng screen ═════════════
            function itemsFromTasks(tasks) {
                const raw = []; let last = null;
                (tasks || []).forEach(x => {
                    if (x.phase_name !== last) { raw.push({ type: 'phase', name: x.phase_name }); last = x.phase_name; }
                    raw.push({ type: 'task', name: x.task, s: String(x.start_date).slice(0, 10), e: String(x.end_date).slice(0, 10) });
                });
                return raw;
            }
            let whole = null;   // { raw, meta, info, printable }
            function drawWhole() {
                if (!whole) return;
                const items = prepItems(whole.raw);
                const tasks = items.filter(x => x.type === 'task' && okDate(x.s) && okDate(x.e));
                if (!tasks.length) { F('twBody').innerHTML = '<p class="py-16 text-center text-sm text-gray-400">No tasks with dates yet.</p>'; F('twStats').innerHTML = ''; F('twLegend').innerHTML = ''; return; }
                const dates = dateRange(items, 1, tasks[0].s);
                // lapad: kasya lahat ng araw sa screen (walang horizontal scroll)
                const cs = getComputedStyle(F('twBody'));
                const avail = F('twBody').clientWidth - parseFloat(cs.paddingLeft) - parseFloat(cs.paddingRight) - 2;
                const narrow = avail < 700;
                const LW = narrow ? [130, 0, 0, 0] : [Math.min(240, Math.max(150, Math.round(avail * 0.16))), 44, 62, 62];
                const leftW = LW.reduce((a, b) => a + b, 0);
                const dayW = Math.max(3, Math.min(28, (avail - leftW) / dates.length));
                const g = ganttTable(items, dates, { dayW, rowH: dayW < 10 ? 22 : 26, today: true, status: true, left: true, leftW: LW });
                F('twBody').innerHTML = g.html;
                if (narrow) F('twBody').querySelectorAll('.gt tr').forEach(tr => { [...tr.children].slice(1, 4).forEach(c => { if (c.classList.contains('lc') || c.classList.contains('lh')) c.style.display = 'none'; }); });
                const s = tasks.map(x => x.s).sort()[0], f = tasks.map(x => x.e).sort().pop(), total = diffDays(s, f) + 1;
                const passed = Math.min(total, Math.max(0, diffDays(s, today) + 1));
                const doneN = tasks.filter(x => x.e < today).length, goingN = tasks.filter(x => x.s <= today && today <= x.e).length;
                F('twStats').innerHTML = `
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-gray-100 text-gray-700"><i class="fa-regular fa-calendar"></i> <b>${fmtY(s)}</b> → <b>${fmtY(f)}</b> · ${total} days</span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-sky-50 text-sky-700"><i class="fa-solid fa-person-digging"></i> ${today < s ? 'Starts in ' + diffDays(today, s) + ' day(s)' : today > f ? 'Finished' : `Day ${passed} of ${total}`}</span>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-emerald-50 text-emerald-700"><i class="fa-solid fa-check"></i> ${doneN}/${tasks.length} done${goingN ? ` · ${goingN} on-going` : ''}</span>`;
                F('twLegend').innerHTML = items.filter(x => x.type === 'phase').map(p => `<span class="inline-flex items-center gap-1.5"><i class="inline-block w-3 h-2 rounded-sm" style="background:${p.color}"></i>${esc(p.name || '—')}</span>`).join('')
                    + '<span class="inline-flex items-center gap-1.5 text-red-600"><i class="inline-block w-0.5 h-3 bg-red-600"></i>Today</span><span class="text-gray-400">Hover a bar to see its dates</span>';
            }
            function openWhole(raw, meta, info, printable) {
                whole = { raw, meta, info, printable };
                F('twTitle').textContent = (meta.client_name || '') + (meta.project_scope ? ' · ' + meta.project_scope : '');
                F('twSub').textContent = [meta.site_location, info ? `DR# ${info.dr_no}` : '', info ? info.quote_no : ''].filter(Boolean).join(' · ');
                F('twPrint').disabled = !printable;
                F('twPrint').title = printable ? '' : 'Save the timeline first, then print.';
                F('tlWhole').classList.remove('hidden'); F('tlWhole').classList.add('flex');
                document.body.style.overflow = 'hidden';
                drawWhole();
            }
            function closeWhole() {
                F('tlWhole').classList.add('hidden'); F('tlWhole').classList.remove('flex');
                document.body.style.overflow = ''; whole = null;
            }
            F('twClose').addEventListener('click', closeWhole);
            F('twPrint').addEventListener('click', () => { if (whole && whole.printable) printTimeline(whole.printable); });
            window.addEventListener('resize', () => { if (whole) drawWhole(); });
            // Esc: isara muna ang Whole view bago ang editor
            document.addEventListener('keydown', e => { if (e.key === 'Escape' && whole) { e.stopImmediatePropagation(); closeWhole(); } }, true);
            // mula sa editor: kasama ang hindi pa naka-save na pagbabago
            F('tlWholeBtn').addEventListener('click', () => {
                if (!ed) return;
                compute();
                const raw = ed.rows.map(r => r.type === 'phase' ? { type: 'phase', name: r.name.toUpperCase() } : { type: 'task', name: (r.name || '').toUpperCase(), s: r.cStart, e: r.cEnd, fixed: r.mode === 'fixed' });
                openWhole(raw.filter(x => x.type === 'phase' || x.name), readMeta(), ed.info, ed.timeline && !ed.dirty ? ed.timeline : null);
            });

            // ═════════════ INIT ═════════════
            tab = memo.get('tab') || 'todo';
            if (!['todo', 'upcoming', 'ongoing', 'done', 'all'].includes(tab)) tab = 'todo';
            load().then(() => {
                const want = +new URLSearchParams(location.search).get('dr');
                if (want && rows.some(r => r.dr_id === want)) openEditor(want);
                if (want) { try { const u = new URL(location.href); u.searchParams.delete('dr'); history.replaceState(null, '', u); } catch (e) { } }
            });
            setInterval(() => { if (!document.hidden && !F('tlModal').classList.contains('flex')) load(true); }, 60000);
        })();
    </script>
</body>

</html>