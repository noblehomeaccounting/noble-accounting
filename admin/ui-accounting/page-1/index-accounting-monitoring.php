<?php
// index-accounting-monitoring.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_ACCOUNTING];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Project Monitoring — Accounting</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/ui-accounting/css/accounting/index-accounting-monitoring.css?v=<?= @filemtime(ROOT_PATH . '/admin/ui-accounting/page-1/css/accounting/index-accounting-monitoring.css') ?: 1 ?>">
</head>

<body class="bg-gray-100">

    <main id="main-content" class="md:ml-56 pt-20 md:pt-5 min-h-screen p-4 md:p-8 transition-all duration-300">

        <!-- ═══ HEADER ═══ -->
        <div class="mb-5 flex items-start sm:items-center justify-between flex-wrap gap-3">
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-11 h-11 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                    <i class="fa-solid fa-helmet-safety text-lg"></i>
                </div>
                <div class="min-w-0">
                    <h1 class="text-xl font-bold text-gray-800 leading-tight">Project Monitoring</h1>
                    <p class="text-sm text-gray-500">Accounting overview — all projects</p>
                </div>
            </div>
            <div class="flex items-center gap-2 text-[11px] text-gray-500 bg-white border border-gray-200 rounded-full px-3 py-1.5 shadow-sm">
                <span id="pm-load-dot" class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                <span id="pm-load-text">Loading…</span>
            </div>
        </div>

        <!-- ═══ METRICS (sumusunod sa filter) ═══ -->
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-5">
            <?php
            $cards = [
                ['m-total',    'Projects',              'fa-helmet-safety',   'bg-amber-50 text-amber-700', ''],
                ['m-contract', 'Contract value',        'fa-file-signature',  'bg-blue-50 text-blue-600',   ''],
                ['m-credited', 'Collected',             'fa-hand-holding-dollar', 'bg-green-50 text-green-600', 'm-credited-sub'],
                ['m-expense',  'Expenses',              'fa-arrow-up',        'bg-red-50 text-red-500',     ''],
                ['m-income',   'Possible income / loss','fa-scale-balanced',  'bg-purple-50 text-purple-600','m-income-sub'],
            ];
            foreach ($cards as $i => [$id, $label, $icon, $tone, $sub]): ?>
                <div class="bg-white border border-gray-200 rounded-xl p-4 flex items-center gap-3 shadow-sm min-w-0 <?= $i === 4 ? 'col-span-2 lg:col-span-1' : '' ?>">
                    <div class="hidden 2xl:flex w-10 h-10 rounded-lg <?= $tone ?> items-center justify-center shrink-0"><i class="fa-solid <?= $icon ?>"></i></div>
                    <div class="min-w-0">
                        <p class="text-[11px] font-semibold text-gray-500 uppercase tracking-wide"><?= $label ?></p>
                        <p id="<?= $id ?>" class="font-bold text-gray-800 leading-none mt-1 whitespace-nowrap <?= $i ? 'font-mono text-[15px] xl:text-base' : 'text-2xl' ?>">—</p>
                        <?php if ($sub): ?><p id="<?= $sub ?>" class="text-[11px] text-gray-400 mt-1 truncate"></p><?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- ═══ LIST CARD ═══ -->
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
            <div class="flex flex-col xl:flex-row xl:items-center justify-between gap-2.5 px-4 py-3 border-b border-gray-100">
                <div id="pm-tabs" class="flex items-center gap-1.5 flex-wrap">
                    <?php foreach (['all' => 'All', 'Ongoing' => 'Ongoing', 'Completed' => 'Completed', 'Pending' => 'Pending', 'Cancelled' => 'Cancelled'] as $val => $label): ?>
                        <button type="button" data-filter="<?= $val ?>" data-active="<?= $val === 'all' ? 1 : 0 ?>" onclick="setFilter('<?= $val ?>')"
                            class="pm-tab inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full border transition">
                            <?= $label ?> <span class="pm-tab-count text-[10px] font-bold bg-gray-100 text-gray-500 rounded-full px-1.5" data-count="<?= $val ?>">0</span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="flex items-center gap-2 flex-wrap xl:flex-nowrap">
                    <div class="relative flex-1 min-w-0 xl:w-64">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[11px]"></i>
                        <input id="search" type="text" placeholder="Search project, client, reference…" oninput="applyFilters()"
                            class="w-full h-9 pl-8 pr-3 text-xs border border-gray-200 rounded-lg outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition" />
                    </div>
                    <select id="pm-sort" onchange="applyFilters()" title="Sort"
                        class="h-9 text-xs border border-gray-200 rounded-lg px-2.5 outline-none focus:border-amber-500 focus:ring-2 focus:ring-amber-100 transition text-gray-700 bg-white">
                        <option value="">Sort: default</option>
                        <option value="name-asc">Project name A–Z</option>
                        <option value="contract-desc">Contract: highest</option>
                        <option value="balance-desc">Balance: highest</option>
                        <option value="income-asc">Income / loss: lowest</option>
                        <option value="collected-asc">% collected: lowest</option>
                    </select>
                </div>
            </div>
            <div id="pm-info" class="px-4 py-2 text-[11px] text-gray-500 bg-gray-50 border-b border-gray-100">&nbsp;</div>

            <div id="proj-tbody" class="pm-scroll max-h-[calc(100vh-24rem)] min-h-[200px] overflow-y-auto divide-y divide-gray-100">
                <div class="px-4 py-12 text-center text-gray-400 text-sm"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Loading projects…</div>
            </div>
        </div>

    </main>

    <!-- ═══ PROJECT DETAIL MODAL (view only) ═══ -->
    <div id="project-modal" class="hidden fixed inset-0 z-50 flex items-start justify-center bg-black/50 backdrop-blur-sm px-3 sm:px-4 py-4 sm:py-6 overflow-y-auto">
        <div class="bg-white w-full max-w-6xl rounded-2xl shadow-2xl flex flex-col my-auto">
            <!-- Top bar -->
            <div class="flex items-center justify-between gap-3 px-4 sm:px-6 py-4 border-b border-gray-100 sticky top-0 bg-white z-10 rounded-t-2xl">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-700 flex items-center justify-center shrink-0">
                        <i class="fa-solid fa-helmet-safety"></i>
                    </div>
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h2 class="text-base font-bold text-gray-800 truncate" id="modal-project-name">—</h2>
                            <span id="modal-status" class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full"></span>
                        </div>
                        <p class="notranslate text-[11px] text-amber-700 font-mono" id="modal-reference-no"></p>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" onclick="printProject()" title="Print"
                        class="inline-flex items-center gap-2 h-9 px-3 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 text-gray-700 text-xs font-semibold transition">
                        <i class="fa-solid fa-print"></i><span class="hidden sm:inline">Print</span>
                    </button>
                    <button type="button" onclick="openExcelPreview()" title="View Excel"
                        class="inline-flex items-center gap-2 h-9 px-3 rounded-lg border border-green-200 bg-green-50 hover:bg-green-100 text-green-800 text-xs font-semibold transition">
                        <i class="fa-solid fa-eye"></i><span class="hidden sm:inline">View Excel</span>
                    </button>
                    <a id="modal-export-btn" href="#" title="Export Excel"
                        class="inline-flex items-center gap-2 h-9 px-3 rounded-lg bg-green-600 hover:bg-green-700 text-white text-xs font-semibold transition">
                        <i class="fa-solid fa-file-excel"></i><span class="hidden sm:inline">Export Excel</span>
                    </a>
                    <button type="button" onclick="closeProjectModal()" title="Close"
                        class="w-9 h-9 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>

            <div id="modal-body" class="p-4 sm:p-6">
                <div class="flex items-center justify-center py-20 text-gray-400 text-sm"><i class="fa-solid fa-spinner fa-spin mr-2"></i> Loading…</div>
            </div>
        </div>
    </div>

    <!-- ═══ EXCEL PREVIEW ═══ -->
    <div id="excel-preview" class="hidden fixed inset-0 z-[60] flex flex-col bg-black/60 backdrop-blur-sm p-2 sm:p-4">
        <div class="bg-white w-full max-w-7xl mx-auto rounded-2xl shadow-2xl flex flex-col flex-1 min-h-0 overflow-hidden">
            <div class="flex items-center justify-between gap-3 px-4 py-3 border-b border-gray-100 shrink-0">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="w-9 h-9 rounded-lg bg-green-100 text-green-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-file-excel"></i></div>
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-gray-800 truncate" id="xl-title">Excel preview</p>
                        <p class="text-[11px] text-gray-500">Ito ang laman ng Excel file — view only</p>
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <a id="xl-newtab" href="#" target="_blank" rel="noopener" title="Open in new tab"
                        class="hidden sm:inline-flex items-center gap-2 h-9 px-3 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 text-gray-700 text-xs font-semibold transition">
                        <i class="fa-solid fa-up-right-from-square"></i> New tab
                    </a>
                    <a id="xl-download" href="#" title="Download .xlsx"
                        class="inline-flex items-center gap-2 h-9 px-3 rounded-lg bg-green-600 hover:bg-green-700 text-white text-xs font-semibold transition">
                        <i class="fa-solid fa-download"></i><span class="hidden sm:inline">Download .xlsx</span>
                    </a>
                    <button type="button" onclick="closeExcelPreview()" title="Close"
                        class="w-9 h-9 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition">
                        <i class="fa-solid fa-xmark"></i>
                    </button>
                </div>
            </div>
            <div class="relative flex-1 min-h-0 bg-gray-200">
                <div id="xl-loading" class="absolute inset-0 flex items-center justify-center text-sm text-gray-500">
                    <i class="fa-solid fa-spinner fa-spin mr-2"></i> Preparing preview…
                </div>
                <iframe id="xl-frame" title="Excel preview" class="w-full h-full border-0 bg-transparent" src="about:blank"></iframe>
            </div>
        </div>
    </div>

    <div id="nhPrintArea"></div>

    <script>
        // Tandaan: BASE_URL ay galing sa sidebar script
        let allProjects = [];
        let expenseMap = {};          // project_id -> total expenses (undefined = naglo-load pa)
        let activeFilter = 'all';
        let ACTIVE_PROJECT_ID = null;
        let ACTIVE_CONTRACT_AMOUNT = 0;

        // ─── Utils ───────────────────────────────────────────
        const escHtml = s => String(s ?? '').replace(/[&<>"']/g,
            c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const num = v => { const n = parseFloat(v); return Number.isFinite(n) ? n : 0; };
        const peso = v => '₱ ' + Math.abs(num(v)).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const signed = v => (num(v) < 0 ? '−' : '') + peso(v);
        function fmtDay(v) {
            if (!v || String(v).startsWith('0000')) return '—';
            const d = new Date(String(v).substring(0, 10) + 'T00:00:00');
            return isNaN(d) ? String(v) : d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
        }
        function badgeClass(status) {
            return {
                ongoing: 'bg-green-100 text-green-800', completed: 'bg-blue-100 text-blue-800',
                pending: 'bg-amber-100 text-amber-800', cancelled: 'bg-red-100 text-red-700',
            }[(status || '').toLowerCase()] || 'bg-gray-100 text-gray-600';
        }
        const barClass = s => ({ ongoing: 'bg-green-500', completed: 'bg-blue-500', pending: 'bg-amber-400', cancelled: 'bg-red-400' }[(s || '').toLowerCase()] || 'bg-gray-300');

        async function readJson(res) {
            const text = await res.text();
            try { return JSON.parse(text.replace(/^﻿/, '').trim()); }
            catch (e) {
                console.error('Not JSON:', res.status, res.url, text);
                const isPage = /<!doctype html|<html[\s>]/i.test(text);
                throw Object.assign(new Error(isPage
                    ? 'The server returned a page instead of data (route missing from $adminRoutes, or session expired — reload the page).'
                    : `Server error (HTTP ${res.status}).`), { server: true });
            }
        }
        const getJson = url => fetch(url, { cache: 'no-store' }).then(readJson);

        function metrics(p) {
            const contract = num(p.contract_amount);
            const credited = num(p.total_credited);
            const expense = expenseMap[p.id];
            const exp = num(expense);
            return {
                contract, credited, expense, exp,
                income: contract - exp,
                balance: contract - credited,
                pct: contract > 0 ? Math.min(100, credited / contract * 100) : 0,
            };
        }

        // ─── Filter / sort ───────────────────────────────────
        function setFilter(val) {
            activeFilter = val;
            document.querySelectorAll('#pm-tabs .pm-tab').forEach(b => b.dataset.active = b.dataset.filter === val ? '1' : '0');
            applyFilters();
        }

        function applyFilters() {
            const q = document.getElementById('search').value.trim().toLowerCase();
            const sort = document.getElementById('pm-sort').value;
            const matchQ = p => !q || [p.project_name, p.client_name, p.reference_no, p.job_order, p.sales_person]
                .some(v => v && String(v).toLowerCase().includes(q));

            // bilang kada status (sumusunod sa search)
            const counts = { all: 0 };
            allProjects.filter(matchQ).forEach(p => { counts.all++; counts[p.status || ''] = (counts[p.status || ''] || 0) + 1; });
            document.querySelectorAll('#pm-tabs [data-count]').forEach(el => el.textContent = counts[el.dataset.count] || 0);

            let rows = allProjects.filter(p => (activeFilter === 'all' || (p.status || '') === activeFilter) && matchQ(p));
            if (sort) {
                const [key, dir] = sort.split('-');
                const val = p => {
                    const m = metrics(p);
                    return key === 'name' ? String(p.project_name || '').toLowerCase()
                        : key === 'contract' ? m.contract : key === 'balance' ? m.balance
                        : key === 'income' ? m.income : m.pct;
                };
                rows = [...rows].sort((a, b) => {
                    const av = val(a), bv = val(b);
                    const c = typeof av === 'string' ? av.localeCompare(bv) : av - bv;
                    return dir === 'asc' ? c : -c;
                });
            }
            renderTable(rows, !!(q || activeFilter !== 'all'));
            renderMetrics(rows);
        }

        // ─── Render list ─────────────────────────────────────
        function cell(label, value, cls = 'text-gray-800') {
            return `<div class="min-w-0">
                <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide">${label}</p>
                <p class="font-mono text-[13px] font-semibold ${cls} truncate">${value}</p>
            </div>`;
        }

        function renderTable(rows, filtered) {
            const box = document.getElementById('proj-tbody');
            document.getElementById('pm-info').innerHTML =
                `Showing <b class="text-gray-700">${rows.length}</b> of ${allProjects.length} project${allProjects.length === 1 ? '' : 's'}`;
            if (!rows.length) {
                box.innerHTML = `<div class="px-4 py-12 text-center">
                    <div class="w-12 h-12 mx-auto rounded-full bg-gray-100 text-gray-400 flex items-center justify-center mb-2"><i class="fa-solid fa-helmet-safety"></i></div>
                    <p class="text-sm font-semibold text-gray-600">${filtered ? 'No projects match this filter.' : 'No projects yet.'}</p></div>`;
                return;
            }
            const loading = '<i class="fa-solid fa-spinner fa-spin text-gray-300 text-[11px]"></i>';
            box.innerHTML = rows.map((p, i) => {
                const m = metrics(p);
                const id = Number(p.id);
                const incomeCls = m.expense === undefined ? 'text-gray-400' : m.income > 0 ? 'text-green-700' : m.income < 0 ? 'text-red-600' : 'text-gray-500';
                return `
                <div data-id="${id}" class="pm-row flex items-stretch gap-3 px-4 sm:px-5 py-3.5 hover:bg-gray-50 transition-colors cursor-pointer"
                    onclick="openProjectModal(${id})" tabindex="0" onkeydown="if(event.key==='Enter') openProjectModal(${id})">
                    <div class="w-6 shrink-0 pt-0.5 text-right text-[11px] font-mono text-gray-400">${i + 1}</div>
                    <div class="w-1 rounded-full shrink-0 ${barClass(p.status)}"></div>
                    <div class="flex-1 min-w-0 flex flex-col xl:flex-row xl:items-center gap-3 xl:gap-6">
                        <div class="min-w-0 xl:w-[32%] xl:shrink-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="text-sm font-bold text-gray-800 truncate">${escHtml(p.project_name || '—')}</p>
                                <span class="text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full ${badgeClass(p.status)}">${escHtml(p.status || '—')}</span>
                            </div>
                            <p class="text-[12px] text-gray-500 truncate">
                                ${p.reference_no ? `<span class="notranslate font-mono text-amber-700">${escHtml(p.reference_no)}</span> · ` : ''}${escHtml(p.client_name || 'No client')}
                            </p>
                            <div class="mt-1.5 flex items-center gap-2">
                                <div class="flex-1 bg-gray-100 rounded-full h-1.5 overflow-hidden">
                                    <div class="bg-green-500 h-full rounded-full" style="width:${m.pct.toFixed(1)}%"></div>
                                </div>
                                <span class="text-[10px] font-semibold text-gray-500 w-20 text-right">${m.contract ? m.pct.toFixed(0) + '% collected' : 'No contract'}</span>
                            </div>
                        </div>
                        <div class="flex-1 grid grid-cols-2 sm:grid-cols-5 gap-x-4 gap-y-2">
                            ${cell('Contract', m.contract ? peso(m.contract) : '—')}
                            ${cell('Collected', m.credited ? peso(m.credited) : '—', 'text-green-700')}
                            ${cell('Balance', m.contract ? peso(m.balance) : '—', m.balance > 0 ? 'text-amber-800' : 'text-gray-800')}
                            ${cell('Expenses', m.expense === undefined ? loading : (m.exp ? peso(m.exp) : '—'), 'text-red-600')}
                            ${cell('Income / loss', m.expense === undefined ? loading : ((m.contract || m.exp) ? signed(m.income) : '—'), incomeCls)}
                        </div>
                        <i class="hidden xl:block fa-solid fa-chevron-right text-gray-300 text-xs"></i>
                    </div>
                </div>`;
            }).join('');
        }

        function renderMetrics(rows) {
            let contract = 0, credited = 0, expense = 0, pending = 0;
            rows.forEach(p => {
                const m = metrics(p);
                contract += m.contract; credited += m.credited; expense += m.exp;
                if (m.expense === undefined) pending++;
            });
            const income = contract - expense;
            document.getElementById('m-total').textContent = rows.length;
            document.getElementById('m-contract').textContent = peso(contract);
            document.getElementById('m-credited').textContent = peso(credited);
            document.getElementById('m-credited-sub').textContent = contract ? Math.round(credited / contract * 100) + '% of contract value' : '';
            document.getElementById('m-expense').textContent = pending ? '…' : peso(expense);
            const inc = document.getElementById('m-income');
            inc.textContent = pending ? '…' : signed(income);
            inc.classList.toggle('text-green-700', !pending && income > 0);
            inc.classList.toggle('text-red-600', !pending && income < 0);
            document.getElementById('m-income-sub').textContent = pending ? `Loading expenses (${rows.length - pending}/${rows.length})…` : 'Contract − expenses';
        }

        // ─── Loading ─────────────────────────────────────────
        // Kinukuha ang expenses kada project nang hanggang 6 nang sabay (hindi lahat agad)
        async function loadExpenses(ids) {
            let idx = 0, done = 0;
            const setLoad = () => document.getElementById('pm-load-text').textContent =
                done < ids.length ? `Loading expenses ${done}/${ids.length}…` : 'Updated ' + new Date().toLocaleTimeString('en-PH');
            let t = null;
            const refresh = () => { if (!t) t = setTimeout(() => { t = null; applyFilters(); }, 150); };
            async function worker() {
                while (idx < ids.length) {
                    const id = ids[idx++];
                    try {
                        const data = await getJson(`${BASE_URL}/fetchprojectexpense?project_id=${encodeURIComponent(id)}`);
                        expenseMap[id] = (Array.isArray(data) ? data : []).reduce((s, row) => s + num(row.amount), 0);
                    } catch { expenseMap[id] = 0; }
                    done++; setLoad(); refresh();
                }
            }
            setLoad();
            await Promise.all(Array.from({ length: Math.min(6, ids.length) }, worker));
            const dot = document.getElementById('pm-load-dot');
            dot.classList.remove('bg-amber-500', 'animate-pulse'); dot.classList.add('bg-green-500');
            applyFilters();
        }

        async function init() {
            try {
                const data = await getJson(`${BASE_URL}/fetchprojects`);
                allProjects = Array.isArray(data) ? data : [];
                applyFilters();                       // ipakita agad ang listahan
                await loadExpenses(allProjects.map(p => p.id));
            } catch (e) {
                document.getElementById('proj-tbody').innerHTML = `<div class="px-4 py-12 text-center">
                    <div class="w-12 h-12 mx-auto rounded-full bg-red-50 text-red-500 flex items-center justify-center mb-2"><i class="fa-solid fa-circle-exclamation"></i></div>
                    <p class="text-sm font-semibold text-gray-700">Failed to load projects.</p>
                    <p class="text-xs text-gray-400 mt-1">${escHtml(e?.server ? e.message : 'Check your connection, then reload the page.')}</p></div>`;
                document.getElementById('pm-load-text').textContent = 'Load failed';
            }
        }

        // ─── Project modal (view only) ───────────────────────
        function sectionTitle(n, text, icon) {
            return `<div class="flex items-center gap-2 mb-3">
                <span class="w-7 h-7 rounded-lg bg-amber-100 text-amber-700 flex items-center justify-center text-xs font-bold">${n}</span>
                <h3 class="text-sm font-bold text-gray-800"><i class="fa-solid ${icon} text-gray-400 mr-1"></i>${text}</h3>
            </div>`;
        }
        function sumCard(label, id, cls = 'text-gray-800') {
            return `<div class="bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 min-w-0">
                <p class="text-[10px] font-semibold text-gray-500 uppercase tracking-wide">${label}</p>
                <p id="${id}" class="font-mono text-base font-bold ${cls} mt-0.5 break-all">…</p>
            </div>`;
        }

        async function openProjectModal(projectId) {
            const p = allProjects.find(x => x.id == projectId);
            if (!p) return;
            ACTIVE_PROJECT_ID = projectId;
            ACTIVE_CONTRACT_AMOUNT = num(p.contract_amount);

            document.getElementById('modal-project-name').textContent = p.project_name || '—';
            document.getElementById('modal-reference-no').textContent = p.reference_no || '';
            const st = document.getElementById('modal-status');
            st.textContent = p.status || '—';
            st.className = 'text-[10px] font-bold uppercase tracking-wide px-2 py-0.5 rounded-full ' + badgeClass(p.status);
            document.getElementById('modal-export-btn').href = `${BASE_URL}/exportprojectexcel?id=${encodeURIComponent(projectId)}`;
            document.getElementById('project-modal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';

            const fields = [
                ['Project name', p.project_name], ['Client name', p.client_name],
                ['Job order', p.job_order], ['Purchase order', p.purchase_order],
                ['Project scope', p.project_scope], ['Notice to proceed', p.notice_to_proceed],
                ['Contract amount', ACTIVE_CONTRACT_AMOUNT ? peso(ACTIVE_CONTRACT_AMOUNT) : ''], ['Sales person', p.sales_person],
                ['(1) Billing order #', p.billing_order_1], ['(2) Billing order #', p.billing_order_2],
                ['Address', p.address], ['Status', p.status],
            ];

            document.getElementById('modal-body').innerHTML = `
                <!-- Summary -->
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-6">
                    ${sumCard('Contract', 'pm-s-contract')}
                    ${sumCard('Collected', 'pm-s-collected', 'text-green-700')}
                    ${sumCard('Balance', 'pm-s-balance', 'text-amber-800')}
                    ${sumCard('Expenses', 'pm-s-expense', 'text-red-600')}
                    <div class="col-span-2 lg:col-span-1">${sumCard('Income / loss', 'pm-s-income')}</div>
                </div>

                <!-- Basic info -->
                <div class="mb-6">
                    ${sectionTitle('<i class="fa-solid fa-info"></i>', 'Basic information', 'fa-circle-info')}
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-2.5 bg-gray-50 border border-gray-200 rounded-xl p-4">
                        ${fields.map(([label, value]) => `
                            <div class="flex items-baseline gap-3 min-w-0 border-b border-gray-200/70 pb-1.5">
                                <dt class="text-[10px] font-bold uppercase tracking-wide text-gray-500 w-32 shrink-0">${escHtml(label)}</dt>
                                <dd class="text-sm text-gray-800 break-words min-w-0">${escHtml(value || '—')}</dd>
                            </div>`).join('')}
                    </dl>
                </div>

                <!-- 1. Billing -->
                <div class="mb-6">
                    ${sectionTitle(1, 'Billed and paid by client / owner', 'fa-hand-holding-dollar')}
                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <div id="billing-tbody" class="pm-scroll pm-print-scroll max-h-[320px] overflow-y-auto divide-y divide-gray-100">
                            <div class="px-4 py-8 text-center text-gray-400 text-sm"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Loading...</div>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 bg-amber-50 border-t border-amber-100 text-xs">
                            <span class="font-bold uppercase tracking-wide text-amber-900">Total credited: <span id="billing-total" class="font-mono">₱ 0.00</span></span>
                            <span class="font-bold uppercase tracking-wide text-amber-900">Balance: <span id="billing-balance" class="font-mono">₱ 0.00</span></span>
                        </div>
                    </div>
                </div>

                <!-- 2. Expenses -->
                <div>
                    ${sectionTitle(2, 'Costs / expenses', 'fa-receipt')}
                    <div class="border border-gray-200 rounded-xl overflow-hidden">
                        <div id="expense-tbody" class="pm-scroll pm-print-scroll max-h-[320px] overflow-y-auto divide-y divide-gray-100">
                            <div class="px-4 py-8 text-center text-gray-400 text-sm"><i class="fa-solid fa-spinner fa-spin mr-2"></i>Loading...</div>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 bg-amber-50 border-t border-amber-100 text-xs">
                            <span class="font-bold uppercase tracking-wide text-amber-900">Total paid: <span id="expense-total" class="font-mono">₱ 0.00</span></span>
                            <span class="font-bold uppercase tracking-wide text-amber-900">Possible income / loss: <span id="income-loss" class="font-mono">₱ 0.00</span></span>
                        </div>
                    </div>
                </div>

                <p class="text-[10px] text-gray-400 text-right border-t border-gray-100 mt-6 pt-3">
                    Generated ${escHtml(new Date().toLocaleDateString('en-PH', { year: 'numeric', month: 'long', day: 'numeric' }))}${p.reference_no ? ' · ' + escHtml(p.reference_no) : ''}
                </p>`;

            document.getElementById('pm-s-contract').textContent = ACTIVE_CONTRACT_AMOUNT ? peso(ACTIVE_CONTRACT_AMOUNT) : '—';
            fetchBilling(projectId);
            fetchExpenses(projectId);
        }

        function closeProjectModal() {
            document.getElementById('project-modal').classList.add('hidden');
            document.body.style.overflow = '';
            ACTIVE_PROJECT_ID = null;
        }

        function lineHtml(i, main, sub, amount, meta) {
            return `<div class="flex items-start gap-3 px-4 py-2.5">
                <span class="w-6 shrink-0 text-right text-[11px] font-mono text-gray-400 pt-0.5">${i}</span>
                <div class="flex-1 min-w-0">
                    ${sub ? `<p class="text-[10px] font-semibold text-amber-800 uppercase tracking-wide">${sub}</p>` : ''}
                    <p class="text-sm text-gray-800 break-words">${main}</p>
                    ${meta ? `<p class="text-[11px] text-gray-500 mt-0.5 flex flex-wrap gap-x-3">${meta}</p>` : ''}
                </div>
                <span class="font-mono text-sm font-semibold text-gray-800 shrink-0">${amount}</span>
            </div>`;
        }
        const metaItem = (label, v) => v ? `<span><span class="text-gray-400">${label}</span> ${escHtml(v)}</span>` : '';
        const emptyLine = msg => `<div class="px-4 py-8 text-center text-gray-400 text-sm">${msg}</div>`;

        function setIncome(id, value) {
            const el = document.getElementById(id);
            if (!el) return;
            el.textContent = signed(value);
            el.classList.toggle('text-green-700', value >= 0);
            el.classList.toggle('text-red-600', value < 0);
        }

        function fetchBilling(pid) {
            getJson(`${BASE_URL}/fetchprojectbilling?project_id=${encodeURIComponent(pid)}`).then(data => {
                if (pid !== ACTIVE_PROJECT_ID) return;
                const rows = Array.isArray(data) ? data : [];
                const total = rows.reduce((s, r) => s + num(r.amount), 0);
                document.getElementById('billing-tbody').innerHTML = rows.length
                    ? rows.map((r, i) => lineHtml(i + 1, escHtml(r.particulars || '—'), '', peso(r.amount),
                        [metaItem('Paid', fmtDay(r.payment_date)), metaItem('Bank/Check', r.bank_check), metaItem('Ref', r.reference), metaItem('Remarks', r.remarks)].join(''))).join('')
                    : emptyLine('No billing entries yet.');
                const balance = ACTIVE_CONTRACT_AMOUNT - total;
                document.getElementById('billing-total').textContent = peso(total);
                document.getElementById('billing-balance').textContent = signed(balance);
                document.getElementById('pm-s-collected').textContent = peso(total);
                document.getElementById('pm-s-balance').textContent = ACTIVE_CONTRACT_AMOUNT ? signed(balance) : '—';
            }).catch(err => {
                if (pid !== ACTIVE_PROJECT_ID) return;
                document.getElementById('billing-tbody').innerHTML = emptyLine('<span class="text-red-600">Could not load billing.</span> ' + escHtml(err?.server ? err.message : ''));
            });
        }

        function fetchExpenses(pid) {
            getJson(`${BASE_URL}/fetchprojectexpense?project_id=${encodeURIComponent(pid)}`).then(data => {
                if (pid !== ACTIVE_PROJECT_ID) return;
                const rows = Array.isArray(data) ? data : [];
                const total = rows.reduce((s, r) => s + num(r.amount), 0);
                expenseMap[pid] = total; // para tugma ang listahan
                document.getElementById('expense-tbody').innerHTML = rows.length
                    ? rows.map((r, i) => lineHtml(i + 1, escHtml(r.particulars || '—'), escHtml(r.title || ''), peso(r.amount),
                        [metaItem('Paid', fmtDay(r.payment_date)), metaItem('Mode', r.mode_of_payment), metaItem('Ref', r.reference), metaItem('Remarks', r.remarks)].join(''))).join('')
                    : emptyLine('No expense entries yet.');
                document.getElementById('expense-total').textContent = peso(total);
                document.getElementById('pm-s-expense').textContent = peso(total);
                setIncome('income-loss', ACTIVE_CONTRACT_AMOUNT - total);
                setIncome('pm-s-income', ACTIVE_CONTRACT_AMOUNT - total);
            }).catch(err => {
                if (pid !== ACTIVE_PROJECT_ID) return;
                document.getElementById('expense-tbody').innerHTML = emptyLine('<span class="text-red-600">Could not load expenses.</span> ' + escHtml(err?.server ? err.message : ''));
            });
        }

        // ─── Excel preview (parehong sheet, ipinapakita bilang HTML) ───
        function openExcelPreview() {
            if (!ACTIVE_PROJECT_ID) return;
            const p = allProjects.find(x => x.id == ACTIVE_PROJECT_ID) || {};
            const base = `${BASE_URL}/exportprojectexcel?id=${encodeURIComponent(ACTIVE_PROJECT_ID)}`;
            const viewUrl = base + '&view=1&_=' + Date.now();
            document.getElementById('xl-title').textContent = (p.project_name || 'Project') + ' — Excel preview';
            document.getElementById('xl-download').href = base;
            document.getElementById('xl-newtab').href = base + '&view=1';
            const loading = document.getElementById('xl-loading');
            loading.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i> Preparing preview…';
            loading.classList.remove('hidden');
            const frame = document.getElementById('xl-frame');
            frame.onload = () => {
                if (frame.src === 'about:blank') return;
                // Kung hindi ang preview ang bumalik (hal. error o login page), sabihin
                let ok = true;
                try { ok = !!frame.contentDocument?.querySelector('table'); } catch (e) { ok = true; }
                if (ok) loading.classList.add('hidden');
                else loading.innerHTML = '<span class="text-red-600"><i class="fa-solid fa-circle-exclamation mr-1"></i>Could not build the preview. Try "Download .xlsx" instead.</span>';
            };
            frame.src = viewUrl;
            document.getElementById('excel-preview').classList.remove('hidden');
        }
        function closeExcelPreview() {
            document.getElementById('excel-preview').classList.add('hidden');
            document.getElementById('xl-frame').src = 'about:blank';
        }
        document.getElementById('excel-preview').addEventListener('click', function (e) { if (e.target === this) closeExcelPreview(); });

        // Print: kopyahin ang laman ng modal sa #nhPrintArea (dati walang lumalabas sa print)
        function printProject() {
            const area = document.getElementById('nhPrintArea');
            const head = document.querySelector('#project-modal .sticky').cloneNode(true);
            head.querySelectorAll('button, a').forEach(el => el.remove());
            head.classList.remove('sticky');
            area.innerHTML = '';
            area.appendChild(head);
            area.appendChild(document.getElementById('modal-body').cloneNode(true));
            area.querySelectorAll('[id]').forEach(el => el.removeAttribute('id'));
            window.print();
            setTimeout(() => { area.innerHTML = ''; }, 500);
        }

        // Isara: background click o Esc
        document.getElementById('project-modal').addEventListener('click', function (e) {
            if (e.target === this) closeProjectModal();
        });
        document.addEventListener('keydown', e => {
            if (e.key !== 'Escape') return;
            if (!document.getElementById('excel-preview').classList.contains('hidden')) closeExcelPreview();
            else if (!document.getElementById('project-modal').classList.contains('hidden')) closeProjectModal();
        });

        init();
    
        // ─── Highlight ng huling pinindot na row ─────────────
        // Kapag pinindot ang kahit anong bahagi ng row (View, Approve, atbp.),
        // mananatili itong naka-highlight hanggang may ibang pindutin.
        (function () {
            const KEY = 'pm-picked';
            const SEL = '#proj-tbody .pm-row';
            let picked = null;
            try { picked = sessionStorage.getItem(KEY); } catch (e) { }

            if (!document.getElementById('nh-picked-style')) {
                const st = document.createElement('style');
                st.id = 'nh-picked-style';
                st.textContent = `
                    [data-picked="1"] { position: relative; background: #fffbeb !important;
                        box-shadow: inset 3px 0 0 #d97706, inset 0 0 0 1px #fcd34d; }
                    [data-picked="1"]:hover { background: #fef3c7 !important; }
                    [data-picked="1"]::after { content: 'Last opened'; position: absolute; top: 3px; right: 10px;
                        font-size: 9px; font-weight: 700; letter-spacing: .02em; color: #92400e;
                        background: #fde68a; border-radius: 9999px; padding: 1px 7px; pointer-events: none; }`;
                document.head.appendChild(st);
            }

            function apply() {
                document.querySelectorAll(SEL).forEach(el => {
                    const on = picked !== null && String(el.dataset.id) === picked;
                    const v = on ? '1' : '0';
                    if (el.dataset.picked !== v) el.dataset.picked = v;
                });
            }

            // capture: mauuna ito bago buksan ang modal
            document.addEventListener('click', e => {
                const row = e.target.closest(SEL);
                if (!row || row.dataset.id === undefined) return;
                picked = String(row.dataset.id);
                try { sessionStorage.setItem(KEY, picked); } catch (err) { }
                apply();
            }, true);

            // I-apply ulit tuwing magre-render ang listahan (auto-refresh, filter, ibang buwan)
            new MutationObserver(apply).observe(document.body, { childList: true, subtree: true });
            apply();
        })();
    </script>

</body>
</html>