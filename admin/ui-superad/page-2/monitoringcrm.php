<?php
// monitoringcrm.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SUPERADMIN];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$monAjaxUrl = BASE_URL . '/monitoringcrmajax';
$monViewUrl = BASE_URL . '/monitoringcrmview';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Monitoring</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
</head>

<body class="bg-slate-100">
    <main class="ml-56 min-h-screen p-8 overflow-x-hidden">

        <div class="max-w-8xl mx-auto">

            <!-- Header -->
            <div class="mb-4">
                <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                    <div class="min-w-0">
                        <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase mb-0.5">CRM Management</p>
                        <h1 class="text-gray-900 text-xl font-semibold">Monitoring</h1>
                    </div>
                </div>

                <!-- Search + Filters -->
                <div class="flex flex-wrap items-center gap-2">
                    <div class="relative w-full sm:w-64 min-w-0">
                        <input id="monSearch" type="text" placeholder="Search control no. / client / contact"
                            class="w-full pl-8 pr-7 py-1.5 text-xs border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-100 focus:border-amber-600 bg-white transition-colors">
                        <svg class="absolute left-2 top-1.5 w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                        </svg>
                        <button type="button" id="monSearchClear"
                            class="hidden absolute right-2 top-1.5 text-gray-300 hover:text-gray-500 text-base leading-none w-4 h-4">&times;</button>
                    </div>

                    <select id="monFilterMode"
                        class="mon-filter py-1.5 pl-2 pr-6 text-xs border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-amber-100 focus:border-amber-600">
                        <option value="">All Modes</option>
                        <option value="site_visit">Site Visit</option>
                        <option value="ready_for_quotation">Ready for Quotation</option>
                    </select>

                    <select id="monFilterClient"
                        class="mon-filter py-1.5 pl-2 pr-6 text-xs border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-amber-100 focus:border-amber-600">
                        <option value="">All Client Status</option>
                    </select>

                    <select id="monFilterVisit"
                        class="mon-filter py-1.5 pl-2 pr-6 text-xs border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-amber-100 focus:border-amber-600">
                        <option value="">All Site Visit Status</option>
                    </select>

                    <button type="button" id="monFilterReset"
                        class="hidden text-xs text-amber-700 hover:underline px-1">Clear filters</button>
                </div>
            </div>

            <!-- Stage filter tabs -->
            <div id="monTabs" class="flex items-center gap-1.5 mb-4 flex-wrap">
                <button type="button" data-stage=""
                    class="mon-tab px-3 py-1.5 text-xs font-semibold rounded-lg border transition-colors flex items-center gap-1.5">
                    All
                    <span class="mon-tab-count inline-flex items-center justify-center min-w-[1.15rem] h-[1.15rem] px-1 rounded-full text-[10px] font-bold bg-black/10">0</span>
                </button>
                <button type="button" data-stage="in_progress"
                    class="mon-tab px-3 py-1.5 text-xs font-semibold rounded-lg border transition-colors flex items-center gap-1.5">
                    In Progress
                    <span class="mon-tab-count inline-flex items-center justify-center min-w-[1.15rem] h-[1.15rem] px-1 rounded-full text-[10px] font-bold bg-black/10">0</span>
                </button>
                <button type="button" data-stage="for_revision"
                    class="mon-tab px-3 py-1.5 text-xs font-semibold rounded-lg border transition-colors flex items-center gap-1.5">
                    For Revision
                    <span class="mon-tab-count inline-flex items-center justify-center min-w-[1.15rem] h-[1.15rem] px-1 rounded-full text-[10px] font-bold bg-black/10">0</span>
                </button>
                <button type="button" data-stage="completed"
                    class="mon-tab px-3 py-1.5 text-xs font-semibold rounded-lg border transition-colors flex items-center gap-1.5">
                    Fully Approved
                    <span class="mon-tab-count inline-flex items-center justify-center min-w-[1.15rem] h-[1.15rem] px-1 rounded-full text-[10px] font-bold bg-black/10">0</span>
                </button>
            </div>

            <!-- Table Card -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead>
                            <tr class="bg-gray-50 border-b border-gray-200 text-left text-[10px] uppercase tracking-wide text-gray-500">
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Control No.</th>
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Client</th>
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Contact</th>
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Mode</th>
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Client Status</th>
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Site Visit Status</th>
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Current Stage</th>
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Last Updated</th>
                                <th class="px-4 py-2.5 font-semibold text-right whitespace-nowrap">Action</th>
                            </tr>
                        </thead>
                        <tbody id="monTbody" class="divide-y divide-gray-100"></tbody>
                    </table>
                </div>
            </div>

            <p id="monCount" class="text-[11px] text-gray-400 mt-2.5"></p>
        </div>

        <!-- Toast container -->
        <div id="crmToastContainer"
            class="fixed bottom-6 right-6 z-[9999] flex flex-col gap-2.5 pointer-events-none w-full max-w-sm px-4 sm:px-0">
        </div>

    </main>

    <script>
        function crmShowToast(message, type = 'success', duration = 4000) {
            const container = document.getElementById('crmToastContainer');
            const palette = type === 'success'
                ? { wrap: 'bg-green-50 border-green-200 text-green-700', icon: 'bg-green-200 text-green-700', symbol: '✓' }
                : { wrap: 'bg-red-50 border-red-200 text-red-700', icon: 'bg-red-200 text-red-700', symbol: '!' };

            const toast = document.createElement('div');
            toast.className = `pointer-events-auto flex items-start gap-2.5 border rounded-lg shadow-lg px-4 py-3 text-sm
                ${palette.wrap}
                translate-x-6 opacity-0 scale-95 transition-all duration-300 ease-out`;

            toast.innerHTML = `
                <span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold ${palette.icon}">${palette.symbol}</span>
                <span class="flex-1 leading-relaxed">${message}</span>
                <button type="button" class="shrink-0 text-current opacity-50 hover:opacity-100 text-base leading-none" aria-label="Close">&times;</button>
            `;
            container.appendChild(toast);
            requestAnimationFrame(() => toast.classList.remove('translate-x-6', 'opacity-0', 'scale-95'));
            const remove = () => {
                toast.classList.add('translate-x-6', 'opacity-0', 'scale-95');
                setTimeout(() => toast.remove(), 300);
            };
            toast.querySelector('button').addEventListener('click', remove);
            if (duration > 0) setTimeout(remove, duration);
        }

        const MON_AJAX_URL = <?= json_encode($monAjaxUrl) ?>;
        const MON_VIEW_URL = <?= json_encode($monViewUrl) ?>;
        const MON_POLL_INTERVAL_MS = 8000;

        let monSearchTerm = '';
        let monStageFilter = ''; // default tab: "All"
        let monModeFilter = '';
        let monClientFilter = '';
        let monVisitFilter = '';
        let monLastSignature = '';
        let monPollTimer = null;
        let monSearchDebounce = null;

        function monEscapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }

        function monFormatDate(value) {
            if (!value) return '—';
            const dt = new Date(value.replace(' ', 'T'));
            if (isNaN(dt.getTime())) return value;
            return dt.toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
        }

        // Builds the list URL with the current search + filters.
        function monBuildUrl(stage) {
            const p = new URLSearchParams({
                action: 'list',
                q: monSearchTerm,
                stage: stage,
                mode: monModeFilter,
                clientstatus: monClientFilter,
                visitstatus: monVisitFilter,
            });
            return `${MON_AJAX_URL}?${p.toString()}`;
        }

        function monHasActiveFilters() {
            return !!(monModeFilter || monClientFilter || monVisitFilter);
        }

        function monTextOrDash(value) {
            return value ? monEscapeHtml(value) : '<span class="text-gray-300">—</span>';
        }

        function monModeBadge(mode) {
            const isReady = mode === 'ready_for_quotation';
            const cls = isReady
                ? 'bg-blue-50 text-blue-700 border-blue-200'
                : 'bg-gray-50 text-gray-600 border-gray-200';
            const label = isReady ? 'Ready for Quotation' : 'Site Visit';
            return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium border whitespace-nowrap ${cls}">${label}</span>`;
        }

        // Maps the row's stage_group (computed server-side) to a badge style.
        function monStageBadge(row) {
            const map = {
                'in_progress': 'bg-amber-50 text-amber-700 border-amber-200',
                'for_revision': 'bg-red-50 text-red-700 border-red-200',
                'completed': 'bg-green-50 text-green-700 border-green-200',
                'draft': 'bg-gray-50 text-gray-500 border-gray-200',
            };
            const dotMap = {
                'in_progress': 'bg-amber-500',
                'for_revision': 'bg-red-500',
                'completed': 'bg-green-500',
                'draft': 'bg-gray-400',
            };
            const cls = map[row.stage_group] || map['draft'];
            const dot = dotMap[row.stage_group] || dotMap['draft'];
            return `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold border whitespace-nowrap ${cls}">
                        <span class="w-1.5 h-1.5 rounded-full shrink-0 ${dot}"></span>${monEscapeHtml(row.stage_label)}
                    </span>`;
        }

        // ── Tabs ──
        function monInitTabs() {
            document.querySelectorAll('.mon-tab').forEach(btn => {
                btn.addEventListener('click', () => {
                    monStageFilter = btn.dataset.stage;
                    monLastSignature = '';
                    monRenderTabs();
                    monFetchList();
                });
            });
            monRenderTabs();
        }

        function monRenderTabs() {
            document.querySelectorAll('.mon-tab').forEach(btn => {
                const active = btn.dataset.stage === monStageFilter;
                btn.classList.toggle('bg-amber-700', active);
                btn.classList.toggle('text-white', active);
                btn.classList.toggle('border-amber-700', active);
                btn.classList.toggle('bg-white', !active);
                btn.classList.toggle('text-gray-600', !active);
                btn.classList.toggle('border-gray-300', !active);
                btn.classList.toggle('hover:bg-gray-50', !active);
            });
        }

        // ── Filters (Mode / Client Status / Site Visit Status) ──
        function monFillSelect(id, values) {
            const sel = document.getElementById(id);
            const keep = sel.value;
            sel.insertAdjacentHTML('beforeend',
                `<option value="__none__">(None)</option>` +
                values.map(v => `<option value="${monEscapeHtml(v)}">${monEscapeHtml(v)}</option>`).join('')
            );
            sel.value = keep;
        }

        async function monLoadFilterOptions() {
            try {
                const res = await fetch(`${MON_AJAX_URL}?action=filter_options`);
                const data = await res.json();
                if (!data.success) return;
                monFillSelect('monFilterClient', data.client_statuses);
                monFillSelect('monFilterVisit', data.visit_statuses);
            } catch (e) {
                console.error('monLoadFilterOptions:', e);
            }
        }

        function monInitFilters() {
            const bind = (id, setter) => {
                document.getElementById(id).addEventListener('change', function () {
                    setter(this.value);
                    document.getElementById('monFilterReset').classList.toggle('hidden', !monHasActiveFilters());
                    monLastSignature = '';
                    monFetchList();
                });
            };
            bind('monFilterMode', v => monModeFilter = v);
            bind('monFilterClient', v => monClientFilter = v);
            bind('monFilterVisit', v => monVisitFilter = v);

            document.getElementById('monFilterReset').addEventListener('click', function () {
                monModeFilter = monClientFilter = monVisitFilter = '';
                ['monFilterMode', 'monFilterClient', 'monFilterVisit'].forEach(id => {
                    document.getElementById(id).value = '';
                });
                this.classList.add('hidden');
                monLastSignature = '';
                monFetchList();
            });

            monLoadFilterOptions();
        }

        async function monFetchCounts() {
            try {
                const res = await fetch(monBuildUrl(''));
                const data = await res.json();
                if (!data.success) return;

                const counts = {
                    '': data.rows.length,
                    'in_progress': 0,
                    'for_revision': 0,
                    'completed': 0,
                };
                data.rows.forEach(row => {
                    if (counts[row.stage_group] !== undefined) counts[row.stage_group]++;
                });

                document.querySelectorAll('.mon-tab').forEach(btn => {
                    const countEl = btn.querySelector('.mon-tab-count');
                    if (countEl) countEl.textContent = counts[btn.dataset.stage] ?? 0;
                });
            } catch (e) {
                console.error('monFetchCounts:', e);
            }
        }

        // ── Skeleton / empty states ──
        function monSkeletonRows(count = 5) {
            const tbody = document.getElementById('monTbody');
            tbody.innerHTML = Array.from({ length: count }).map(() => `
                <tr>
                    ${Array.from({ length: 9 }).map(() => `
                        <td class="px-4 py-3"><div class="h-3 rounded bg-gray-100 animate-pulse"></div></td>
                    `).join('')}
                </tr>
            `).join('');
        }

        function monEmptyState() {
            const message = (monSearchTerm || monHasActiveFilters())
                ? 'No records match your search or filters.'
                : 'No records found.';
            return `
                <tr>
                    <td colspan="9" class="p-0">
                        <div class="flex flex-col items-center justify-center gap-2 py-10 text-center">
                            <svg class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <p class="text-gray-400 text-xs">${message}</p>
                        </div>
                    </td>
                </tr>
            `;
        }

        function monRenderRows(rows) {
            const tbody = document.getElementById('monTbody');

            if (rows.length === 0) {
                tbody.innerHTML = monEmptyState();
                return;
            }

            tbody.innerHTML = rows.map(row => `
                <tr class="hover:bg-amber-50/40 transition-colors cursor-pointer" onclick="monGoToView(${row.inquiry_id})">
                    <td class="px-4 py-2.5">
                        <span class="font-mono text-[11px] font-semibold text-amber-700 whitespace-nowrap">${monEscapeHtml(row.control_no)}</span>
                    </td>
                    <td class="px-4 py-2.5 text-gray-800 whitespace-nowrap">${monEscapeHtml(row.client_name)}</td>
                    <td class="px-4 py-2.5 text-gray-500 whitespace-nowrap">${monEscapeHtml(row.contact_number)}</td>
                    <td class="px-4 py-2.5">${monModeBadge(row.mode)}</td>
                    <td class="px-4 py-2.5 text-gray-600 whitespace-nowrap">${monTextOrDash(row.clientstatus)}</td>
                    <td class="px-4 py-2.5 text-gray-600 whitespace-nowrap">${monTextOrDash(row.visitstatus)}</td>
                    <td class="px-4 py-2.5">${monStageBadge(row)}</td>
                    <td class="px-4 py-2.5 text-gray-500 whitespace-nowrap">${monFormatDate(row.last_updated)}</td>
                    <td class="px-4 py-2.5 text-right" onclick="event.stopPropagation()">
                        <button type="button" onclick="monGoToView(${row.inquiry_id})"
                            class="px-3 py-1.5 text-xs font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors whitespace-nowrap">
                            Track
                        </button>
                    </td>
                </tr>
            `).join('');
        }

        function monGoToView(inquiryId) {
            window.location.href = `${MON_VIEW_URL}?id=${inquiryId}`;
        }

        async function monFetchList({ silent = false } = {}) {
            if (!silent) monSkeletonRows();
            try {
                const res = await fetch(monBuildUrl(monStageFilter));
                const data = await res.json();

                if (!data.success) {
                    if (!silent) crmShowToast('Failed to load records.', 'error');
                    return;
                }

                const signature = JSON.stringify(data.rows.map(r => r.inquiry_id + ':' + r.stage_group + ':' + r.last_updated))
                    + monStageFilter + monModeFilter + monClientFilter + monVisitFilter;
                if (signature !== monLastSignature) {
                    monRenderRows(data.rows);
                    monLastSignature = signature;
                }

                document.getElementById('monCount').textContent =
                    `${data.count} record${data.count === 1 ? '' : 's'} found`;

                monFetchCounts();

            } catch (e) {
                console.error('monFetchList:', e);
                if (!silent) crmShowToast('Connection error while fetching records.', 'error');
            }
        }

        function monStartPolling() {
            if (monPollTimer) clearInterval(monPollTimer);
            monPollTimer = setInterval(() => monFetchList({ silent: true }), MON_POLL_INTERVAL_MS);
        }

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                if (monPollTimer) clearInterval(monPollTimer);
            } else {
                monFetchList({ silent: true });
                monStartPolling();
            }
        });

        const monSearchInput = document.getElementById('monSearch');
        const monSearchClear = document.getElementById('monSearchClear');

        monSearchInput.addEventListener('input', function () {
            monSearchClear.classList.toggle('hidden', this.value.length === 0);
            clearTimeout(monSearchDebounce);
            const value = this.value;
            monSearchDebounce = setTimeout(() => {
                monSearchTerm = value.trim();
                monLastSignature = '';
                monFetchList();
            }, 350);
        });

        monSearchClear.addEventListener('click', () => {
            monSearchInput.value = '';
            monSearchClear.classList.add('hidden');
            monSearchTerm = '';
            monLastSignature = '';
            monFetchList();
            monSearchInput.focus();
        });

        monInitFilters();
        monInitTabs();
        monFetchList().then(monStartPolling);
    </script>
</body>

</html>