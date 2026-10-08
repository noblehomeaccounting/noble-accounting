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
    <style>
        .mon-scroll {
            scrollbar-width: thin;
            scrollbar-color: #d1d5db transparent;
        }

        .mon-scroll::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        .mon-scroll::-webkit-scrollbar-track {
            background: transparent;
        }

        .mon-scroll::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 9999px;
        }

        .mon-scroll::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }

        .mon-field {
            width: 100%;
            font-size: 12px;
            color: #374151;
            background: #fff;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 7px 10px;
            outline: none;
            transition: border-color .15s, box-shadow .15s;
        }

        .mon-field:focus {
            border-color: #d97706;
            box-shadow: 0 0 0 3px #fef3c7;
        }

        .mon-label {
            display: block;
            margin-bottom: 4px;
            font-size: 10px;
            font-weight: 600;
            letter-spacing: .05em;
            text-transform: uppercase;
            color: #9ca3af;
        }

        .mon-section-title {
            font-size: 11px;
            font-weight: 600;
            color: #374151;
            margin-bottom: 2px;
        }

        .mon-hint {
            font-size: 11px;
            color: #9ca3af;
        }
    </style>
</head>

<body>

    <main class="md:pl-[240px] px-6 py-6">
        <div class="max-w-6xl mx-auto">

            <!-- Header -->
            <div class="mb-5">
                <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase mb-0.5">CRM Management
                </p>
                <h1 class="text-gray-900 text-xl font-semibold">CRM Monitoring</h1>
            </div>

            <!-- Toolbar -->
            <div class="flex flex-wrap items-center gap-2 mb-3">
                <div class="relative w-full sm:w-80">
                    <input id="monSearch" type="text" placeholder="Search control no., client, contact"
                        class="w-full pl-8 pr-8 py-2 text-xs border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-100 focus:border-amber-600 bg-white transition-colors">
                    <svg class="absolute left-2.5 top-2.5 w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24"
                        stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                    </svg>
                    <button type="button" id="monSearchClear"
                        class="hidden absolute right-2.5 top-2 text-gray-300 hover:text-gray-500 text-lg leading-none w-4 h-4">&times;</button>
                </div>

                <button type="button" id="monFilterBtn"
                    class="inline-flex items-center gap-1.5 text-xs font-medium px-3 py-2 rounded-lg border bg-white text-gray-700 border-gray-300 hover:border-amber-500 transition-colors">
                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 5h18l-7 8v6l-4 2v-8L3 5z" />
                    </svg>
                    Filters
                    <span id="monFilterBadge"
                        class="hidden bg-amber-600 text-white rounded-full text-[10px] leading-none px-1.5 py-1"></span>
                </button>

                <div class="flex items-center gap-2 ml-auto">
                    <button type="button" id="monClearAll"
                        class="hidden text-xs text-gray-500 hover:text-amber-700 font-medium px-2 py-1.5">Clear
                        all</button>
                    <select id="fSort" class="mon-field" style="width:auto" title="Sort by">
                        <option value="newest">Newest first</option>
                        <option value="oldest">Oldest first</option>
                        <option value="client">Client (A–Z)</option>
                        <option value="control">Control no.</option>
                    </select>
                </div>
            </div>

            <!-- Quick filters -->
            <div class="flex flex-wrap items-center gap-1.5 mb-3">
                <span class="mon-hint mr-1">Quick:</span>
                <div id="monPresets" class="flex flex-wrap gap-1.5"></div>
            </div>

            <!-- Active filter pills -->
            <div id="monPills" class="flex-wrap items-center gap-1.5 mb-3" style="display:none"></div>

            <!-- Table: header 40px + 5 rows x 44px = 260px, the rest scrolls -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="mon-scroll overflow-auto" style="max-height: 260px;">
                    <table class="w-full text-xs">
                        <thead class="sticky top-0 z-10">
                            <tr class="h-10 text-left text-[11px] uppercase tracking-wide text-gray-500">
                                <th class="px-5 font-semibold whitespace-nowrap bg-gray-50 border-b border-gray-200">
                                    Control No.</th>
                                <th class="px-5 font-semibold whitespace-nowrap bg-gray-50 border-b border-gray-200">
                                    Client</th>
                                <th class="px-5 font-semibold whitespace-nowrap bg-gray-50 border-b border-gray-200">
                                    Contact No.</th>
                                <th class="px-5 font-semibold whitespace-nowrap bg-gray-50 border-b border-gray-200">
                                    Project</th>
                                <th class="px-5 font-semibold whitespace-nowrap bg-gray-50 border-b border-gray-200">
                                    Current Status</th>
                                <th class="px-5 font-semibold whitespace-nowrap bg-gray-50 border-b border-gray-200">
                                    Sales</th>
                                <th class="px-5 font-semibold whitespace-nowrap bg-gray-50 border-b border-gray-200">
                                    Designer</th>
                                <th class="px-5 font-semibold whitespace-nowrap bg-gray-50 border-b border-gray-200">
                                    Date Filed</th>
                                <th
                                    class="px-5 font-semibold whitespace-nowrap bg-gray-50 border-b border-gray-200 text-right">
                                    Action</th>
                            </tr>
                        </thead>
                        <tbody id="monTbody" class="divide-y divide-gray-100"></tbody>
                    </table>
                </div>
            </div>

            <p id="monCount" class="text-[11px] text-gray-400 mt-2.5"></p>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-6">
                <?php include ROOT_PATH . '/admin/ui-superad/page-2/monitoringcontain1.php'; ?>
                <?php include ROOT_PATH . '/admin/ui-superad/page-2/monitoringcontain2.php'; ?>
            </div>

        </div>
    </main>

    <!-- Filter modal -->
    <div id="monModal" class="items-center justify-center p-4"
        style="display:none; position:fixed; inset:0; z-index:9999;" role="dialog" aria-modal="true"
        aria-labelledby="monModalTitle">
        <div id="monModalBackdrop" class="absolute inset-0 bg-gray-900/40"></div>

        <div class="relative bg-white rounded-xl shadow-xl w-full max-w-3xl flex flex-col" style="max-height: 90vh;">

            <!-- Header -->
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
                <div>
                    <h2 id="monModalTitle" class="text-gray-900 text-sm font-semibold">Filters</h2>
                    <p class="mon-hint">Narrow down inquiries by status history and details</p>
                </div>
                <button type="button" id="monModalClose"
                    class="w-7 h-7 flex items-center justify-center text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-lg text-xl leading-none"
                    aria-label="Close"><i class="fa-solid fa-circle-xmark" style="color: rgb(0, 0, 0);"></i></button>
            </div>

            <!-- Body -->
            <div class="flex-1 overflow-y-auto mon-scroll">

                <!-- Status history -->
                <div class="p-5">
                    <div class="flex flex-wrap items-start justify-between gap-3 mb-3">
                        <div>
                            <p class="mon-section-title">Status history</p>
                            <p class="mon-hint">Show inquiries that have passed through the selected statuses</p>
                        </div>
                        <div id="monMatch"
                            class="inline-flex rounded-lg border border-gray-300 overflow-hidden text-[11px] font-medium">
                            <button type="button" data-match="any" class="px-3 py-1.5">Any of</button>
                            <button type="button" data-match="all" class="px-3 py-1.5 border-l border-gray-300">All
                                of</button>
                        </div>
                    </div>
                    <div id="monStatusList" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-4">
                    </div>
                </div>

                <!-- Timing -->
                <div class="p-5 border-t border-gray-100">
                    <p class="mon-section-title">Current status &amp; timing</p>
                    <p class="mon-hint mb-3">Status date: kung kailan nangyari ang napiling status. Kung walang napili,
                        kahit anong status change sa range.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div><label class="mon-label" for="fCurrent">Current status</label>
                            <select id="fCurrent" class="mon-field">
                                <option value="">Any</option>
                            </select>
                        </div>
                        <div><label class="mon-label" for="fSFrom">Status date from</label>
                            <input id="fSFrom" type="date" class="mon-field">
                        </div>
                        <div><label class="mon-label" for="fSTo">Status date to</label>
                            <input id="fSTo" type="date" class="mon-field">
                        </div>
                    </div>
                </div>

                <!-- Details -->
                <div class="p-5 border-t border-gray-100">
                    <p class="mon-section-title mb-3">Inquiry details</p>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <div><label class="mon-label" for="fProject">Project</label>
                            <select id="fProject" class="mon-field">
                                <option value="">All projects</option>
                            </select>
                        </div>
                        <div><label class="mon-label" for="fSales">Sales</label>
                            <select id="fSales" class="mon-field">
                                <option value="">All sales</option>
                            </select>
                        </div>
                        <div><label class="mon-label" for="fDesigner">Designer</label>
                            <select id="fDesigner" class="mon-field">
                                <option value="">All designers</option>
                                <option value="none">Unassigned</option>
                            </select>
                        </div>
                        <div><label class="mon-label" for="fClient">Client status</label>
                            <select id="fClient" class="mon-field">
                                <option value="">Any</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="tentative">Tentative</option>
                                <option value="no">Not proceeding</option>
                                <option value="none">Not yet contacted</option>
                            </select>
                        </div>
                        <div><label class="mon-label" for="fMode">Mode</label>
                            <select id="fMode" class="mon-field">
                                <option value="">Any</option>
                                <option value="site_visit">Site visit</option>
                                <option value="ready_for_quotation">Ready for quotation</option>
                            </select>
                        </div>
                        <div><label class="mon-label" for="fFrom">Date filed from</label>
                            <input id="fFrom" type="date" class="mon-field">
                        </div>
                        <div><label class="mon-label" for="fTo">Date filed to</label>
                            <input id="fTo" type="date" class="mon-field">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div
                class="px-5 py-3 border-t border-gray-100 bg-gray-50/60 rounded-b-xl flex items-center justify-between">
                <button type="button" id="monPanelClear"
                    class="text-xs text-gray-500 hover:text-amber-700 font-medium">Reset filters</button>
                <button type="button" id="monModalDone"
                    class="text-xs font-medium bg-amber-600 hover:bg-amber-700 text-white px-4 py-2 rounded-lg transition-colors">Done</button>
            </div>
        </div>
    </div>

    <script>
        const MON_AJAX_URL = <?= json_encode($monAjaxUrl) ?>;
        const MON_VIEW_URL = <?= json_encode($monViewUrl) ?>;
        const MON_VISIBLE_ROWS = 5;

        const MON_BADGE = {
            green: 'bg-green-50 text-green-700 ring-green-200',
            blue: 'bg-blue-50 text-blue-700 ring-blue-200',
            amber: 'bg-amber-50 text-amber-700 ring-amber-200',
            red: 'bg-red-50 text-red-700 ring-red-200'
        };
        const MON_DOT = {
            green: 'bg-green-500',
            blue: 'bg-blue-500',
            amber: 'bg-amber-500',
            red: 'bg-red-500'
        };

        // Grouping ng status sa modal
        const MON_GROUPS = [
            ['Contacting & site visit', ['CONTACTING CLIENT', 'WAITING FOR CLIENT', 'WAITING FOR MEASUREMENT', 'REVISIT NEEDED', 'SITE VISIT DONE']],
            ['2D & quotation', ['2D DESIGN', '2D & QUOTATION']],
            ['Initial review', ['INITIAL FOR APPROVAL', 'INITIAL REVISION', 'INITIAL APPROVED', 'WAITING FOR 3D', '3D FOR APPROVAL', '3D REVISION']],
            ['Customer', ['FOR CLIENT REVIEW', 'CUSTOMER REVISION', 'CUSTOMER APPROVED']],
            ['Final', ['FINAL IN PROGRESS', 'FINAL FOR APPROVAL', 'FINAL REVISION', 'FINAL APPROVED']],
            ['Closed', ['NOT PROCEEDING']]
        ];

        // Quick filters (history-based, "any of")
        const MON_PRESETS = [
            { name: 'Had a revision', statuses: ['INITIAL REVISION', 'CUSTOMER REVISION', '3D REVISION', 'FINAL REVISION'] },
            { name: 'Reached customer review', statuses: ['FOR CLIENT REVIEW', 'CUSTOMER REVISION', 'CUSTOMER APPROVED'] },
            { name: 'Reached final', statuses: ['FINAL IN PROGRESS', 'FINAL FOR APPROVAL', 'FINAL REVISION', 'FINAL APPROVED'] }
        ];

        // Filter state
        const monF = {
            q: '',
            status: new Set(),
            status_match: 'any',
            current: '',
            status_from: '', status_to: '',
            project: '', sales: '', designer: '', client_status: '', mode: '',
            from: '', to: '',
            sort: 'newest'
        };
        // state key -> element id (lahat ng select/date inputs)
        const MON_FIELDS = {
            current: 'fCurrent', status_from: 'fSFrom', status_to: 'fSTo',
            project: 'fProject', sales: 'fSales', designer: 'fDesigner',
            client_status: 'fClient', mode: 'fMode',
            from: 'fFrom', to: 'fTo', sort: 'fSort'
        };
        const MON_DEFAULTS = { sort: 'newest' };

        let monStatuses = {};       // label -> color
        let monLastCounts = null;
        let monSearchDebounce = null;
        let monSelectedId = null;
        let monReq = 0;

        const $ = id => document.getElementById(id);

        function monEsc(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }
        function monVal(str) {
            return str && String(str).trim() !== '' && str !== '—'
                ? monEsc(str)
                : '<span class="text-gray-300">—</span>';
        }
        function monDate(value) {
            if (!value) return '<span class="text-gray-300">—</span>';
            const dt = new Date(value.replace(' ', 'T'));
            if (isNaN(dt.getTime())) return monEsc(value);
            return dt.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
        }
        function monPretty(label) {
            return label.toLowerCase().split(' ')
                .map(w => /^\d/.test(w) ? w.toUpperCase() : w.charAt(0).toUpperCase() + w.slice(1))
                .join(' ');
        }
        function monShortDate(v) {
            if (!v) return '';
            const dt = new Date(v + 'T00:00:00');
            return isNaN(dt.getTime()) ? v : dt.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
        }
        function monRange(a, b) {
            if (a && b) return `${monShortDate(a)} – ${monShortDate(b)}`;
            if (a) return `from ${monShortDate(a)}`;
            return `until ${monShortDate(b)}`;
        }

        // Ilan ang active filters (hindi kasama ang search at sort)
        function monPanelFilterCount() {
            let n = monF.status.size ? 1 : 0;
            ['current', 'status_from', 'status_to', 'project', 'sales', 'designer', 'client_status', 'mode', 'from', 'to']
                .forEach(k => { if (monF[k]) n++; });
            return n;
        }

        // ── Modal ──
        function monOpenModal() {
            $('monModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
            $('monModalClose').focus();
        }
        function monCloseModal() {
            $('monModal').style.display = 'none';
            document.body.style.overflow = '';
            $('monFilterBtn').focus();
        }
        function monModalOpen() {
            return $('monModal').style.display !== 'none';
        }

        // ── Table ──
        function monRender(rows) {
            const tbody = $('monTbody');
            if (!rows.length) {
                const msg = (monPanelFilterCount() || monF.q) ? 'No inquiries match the current filters.' : 'No inquiries yet.';
                tbody.innerHTML = `<tr class="h-11"><td colspan="9" class="px-5 text-center text-gray-400">${msg}</td></tr>`;
                return;
            }
            tbody.innerHTML = rows.map(r => `
            <tr class="h-11 cursor-pointer transition-colors ${r.id === monSelectedId ? 'bg-amber-50' : 'hover:bg-amber-50/40'}"
                data-id="${r.id}" onclick="monRowClick(${r.id})">
                <td class="px-5 font-mono text-xs font-semibold text-amber-700 whitespace-nowrap">${monEsc(r.control_no)}</td>
                <td class="px-5">
                    <div class="max-w-[100px] truncate text-gray-800 font-medium" title="${monEsc(r.client_name)}">${monEsc(r.client_name)}</div>
                </td>
                <td class="px-5 text-gray-600 whitespace-nowrap">${monVal(r.contact_number)}</td>
                <td class="px-5 text-gray-600 whitespace-nowrap capitalize">${monVal(r.project_type)}</td>
                <td class="px-5 whitespace-nowrap">
                    <span class="inline-block text-[10px] font-semibold tracking-wide px-2 py-0.5 rounded-full ring-1 ${MON_BADGE[r.stage_color] || 'bg-gray-50 text-gray-600 ring-gray-200'}">${monEsc(r.stage)}</span>
                </td>
                <td class="px-5 text-gray-600 whitespace-nowrap capitalize">${monVal(r.sales_name)}</td>
                <td class="px-5 text-gray-600 whitespace-nowrap capitalize">${monVal(r.designer_name)}</td>
                <td class="px-5 text-gray-500 whitespace-nowrap">${monDate(r.created_at)}</td>
                <td class="px-5 text-right whitespace-nowrap">
                    <a href="${MON_VIEW_URL}?id=${r.id}" onclick="event.stopPropagation()"
                        class="inline-flex items-center gap-1.5 text-[11px] font-medium px-2.5 py-1 rounded-md border border-amber-200 bg-amber-50 text-amber-700 hover:bg-amber-600 hover:text-white hover:border-amber-600 transition-colors">
                        View
                    </a>
                </td>
            </tr>
        `).join('');
        }

        function monRowClick(id) {
            monSelectedId = id;
            document.querySelectorAll('#monTbody tr[data-id]').forEach(tr => {
                const active = Number(tr.dataset.id) === id;
                tr.classList.toggle('bg-amber-50', active);
                tr.classList.toggle('hover:bg-amber-50/40', !active);
            });
            if (window.monBox1Show) window.monBox1Show(id);
            if (window.monBox2Show) window.monBox2Show(id);
        }

        // ── Status checklist (grouped) ──
        function monBuildStatusList() {
            const used = new Set();
            const groups = MON_GROUPS.map(([title, labels]) => [title, labels.filter(l => l in monStatuses)]);
            groups.forEach(([, ls]) => ls.forEach(l => used.add(l)));
            const rest = Object.keys(monStatuses).filter(l => !used.has(l));
            if (rest.length) groups.push(['Other', rest]);

            $('monStatusList').innerHTML = groups.filter(([, ls]) => ls.length).map(([title, ls]) => `
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400 mb-1">${monEsc(title)}</p>
                    ${ls.map(l => `
                    <label class="flex items-center gap-2 py-1 px-1.5 -mx-1.5 rounded-md hover:bg-gray-50 cursor-pointer text-xs text-gray-700" data-row="${monEsc(l)}">
                        <input type="checkbox" value="${monEsc(l)}" class="accent-amber-600">
                        <span class="w-1.5 h-1.5 rounded-full ${MON_DOT[monStatuses[l]] || 'bg-gray-400'}"></span>
                        <span class="flex-1">${monEsc(monPretty(l))}</span>
                        <span data-count class="text-[11px] text-gray-400"></span>
                    </label>`).join('')}
                </div>
            `).join('');
            monUpdateCounts(monLastCounts);
        }

        function monUpdateCounts(counts) {
            monLastCounts = counts;
            document.querySelectorAll('#monStatusList label[data-row]').forEach(row => {
                const label = row.dataset.row;
                const n = counts && counts[label] != null ? counts[label] : '';
                row.querySelector('[data-count]').textContent = n;
                const cb = row.querySelector('input');
                row.style.opacity = (counts && n === 0 && !cb.checked) ? '0.45' : '';
            });
        }

        // ── Quick filters ──
        function monPresetActive(p) {
            return monF.status_match === 'any'
                && monF.status.size === p.statuses.length
                && p.statuses.every(s => monF.status.has(s));
        }
        function monRenderPresets() {
            $('monPresets').innerHTML = MON_PRESETS.map((p, i) => {
                const on = monPresetActive(p);
                return `<button type="button" data-preset="${i}"
                    class="text-[11px] font-medium px-2.5 py-1 rounded-full border transition-colors
                    ${on ? 'bg-amber-600 border-amber-600 text-white' : 'bg-white border-gray-300 text-gray-600 hover:border-amber-400 hover:text-amber-700'}">
                    ${monEsc(p.name)}</button>`;
            }).join('');
        }

        // ── Active pills ──
        function monRenderPills() {
            const pills = [];
            const selText = id => { const s = $(id); return s.options[s.selectedIndex] ? s.options[s.selectedIndex].text : ''; };

            if (monF.status.size) {
                const lead = monF.status.size > 1 ? `Passed through ${monF.status_match === 'all' ? 'all of' : 'any of'}:` : 'Passed through:';
                pills.push({ lead });
                monF.status.forEach(l => pills.push({ k: 'status', v: l, text: monPretty(l), color: monStatuses[l] }));
            }
            if (monF.current) pills.push({ k: 'current', text: 'Current: ' + monPretty(monF.current) });
            if (monF.status_from || monF.status_to) pills.push({ k: 'status_range', text: 'Status date ' + monRange(monF.status_from, monF.status_to) });
            if (monF.project) pills.push({ k: 'project', text: 'Project: ' + selText('fProject') });
            if (monF.sales) pills.push({ k: 'sales', text: 'Sales: ' + selText('fSales') });
            if (monF.designer) pills.push({ k: 'designer', text: 'Designer: ' + selText('fDesigner') });
            if (monF.client_status) pills.push({ k: 'client_status', text: 'Client: ' + selText('fClient') });
            if (monF.mode) pills.push({ k: 'mode', text: 'Mode: ' + selText('fMode') });
            if (monF.from || monF.to) pills.push({ k: 'filed_range', text: 'Filed ' + monRange(monF.from, monF.to) });

            const box = $('monPills');
            if (!pills.length) {
                box.style.display = 'none';
                box.innerHTML = '';
                return;
            }
            box.style.display = 'flex';
            box.innerHTML = pills.map(p => {
                if (p.lead) return `<span class="mon-hint mr-0.5">${monEsc(p.lead)}</span>`;
                const dot = p.color ? `<span class="w-1.5 h-1.5 rounded-full ${MON_DOT[p.color] || 'bg-gray-400'}"></span>` : '';
                return `<span class="inline-flex items-center gap-1.5 text-[11px] bg-amber-50 text-amber-800 border border-amber-200 rounded-full pl-2.5 pr-1 py-0.5">
                    ${dot}${monEsc(p.text)}
                    <button type="button" data-remove="${p.k}" data-val="${monEsc(p.v ?? '')}"
                        class="w-4 h-4 leading-none text-amber-500 hover:text-amber-800 hover:bg-amber-100 rounded-full" aria-label="Remove">&times;</button>
                </span>`;
            }).join('');
        }

        // ── Sync UI galing sa state ──
        function monSyncDateLimits() {
            $('fTo').min = monF.from || '';
            $('fFrom').max = monF.to || '';
            $('fSTo').min = monF.status_from || '';
            $('fSFrom').max = monF.status_to || '';
        }

        function monSyncUI() {
            Object.entries(MON_FIELDS).forEach(([k, id]) => { $(id).value = monF[k]; });
            document.querySelectorAll('#monStatusList input[type=checkbox]').forEach(cb => {
                cb.checked = monF.status.has(cb.value);
            });
            document.querySelectorAll('#monMatch button').forEach(b => {
                const on = b.dataset.match === monF.status_match;
                b.classList.toggle('bg-amber-600', on);
                b.classList.toggle('text-white', on);
                b.classList.toggle('bg-white', !on);
                b.classList.toggle('text-gray-600', !on);
            });
            monSyncDateLimits();
            monRenderPills();
            monRenderPresets();

            const n = monPanelFilterCount();
            const badge = $('monFilterBadge');
            badge.textContent = n;
            badge.classList.toggle('hidden', n === 0);
            $('monClearAll').classList.toggle('hidden', n === 0 && !monF.q);

            const btn = $('monFilterBtn');
            btn.classList.toggle('border-amber-600', n > 0);
            btn.classList.toggle('bg-amber-50', n > 0);
        }

        function monRefresh() {
            monSyncUI();
            monFetchList();
        }

        // ── Fetch ──
        function monQueryString() {
            const p = new URLSearchParams({ action: 'list', q: monF.q, sort: monF.sort });
            if (monF.status.size) {
                p.set('status', [...monF.status].join(','));
                p.set('status_match', monF.status_match);
            }
            ['current', 'status_from', 'status_to', 'project', 'sales', 'designer', 'client_status', 'mode', 'from', 'to']
                .forEach(k => { if (monF[k]) p.set(k, monF[k]); });
            return p.toString();
        }

        async function monFetchList() {
            const countEl = $('monCount');
            const token = ++monReq;
            try {
                const res = await fetch(`${MON_AJAX_URL}?${monQueryString()}`);
                const data = await res.json();
                if (token !== monReq) return; // may mas bagong request na
                if (!data.success) {
                    countEl.textContent = data.message || 'Failed to load.';
                    return;
                }
                monRender(data.rows);
                monUpdateCounts(data.status_counts);

                const n = data.count;
                let text = `${n} ${n === 1 ? 'inquiry' : 'inquiries'} found`;
                if (n !== data.total) text += ` (of ${data.total} matching the other filters)`;
                if (n > MON_VISIBLE_ROWS) text += ` · Scroll to see more`;
                countEl.textContent = text;

                // Live count sa modal button
                $('monModalDone').textContent = `Show ${n} ${n === 1 ? 'result' : 'results'}`;
            } catch (e) {
                if (token !== monReq) return;
                console.error('monFetchList:', e);
                countEl.textContent = 'Connection error while fetching inquiries.';
            }
        }

        async function monLoadOptions() {
            try {
                const res = await fetch(`${MON_AJAX_URL}?action=filter_options`);
                const data = await res.json();
                if (!data.success) return;

                monStatuses = data.statuses || {};
                Object.keys(monStatuses).forEach(l => $('fCurrent').add(new Option(monPretty(l), l)));
                data.projects.forEach(p => $('fProject').add(new Option(p.charAt(0).toUpperCase() + p.slice(1), p)));
                data.sales.forEach(s => $('fSales').add(new Option(s.name, s.id)));
                data.designers.forEach(d => $('fDesigner').add(new Option(d.name, d.id)));

                monBuildStatusList();
                monSyncUI();
            } catch (e) {
                console.error('monLoadOptions:', e);
            }
        }

        function monResetAll() {
            monF.q = '';
            monF.status.clear();
            monF.status_match = 'any';
            Object.keys(MON_FIELDS).forEach(k => { monF[k] = MON_DEFAULTS[k] ?? ''; });
            $('monSearch').value = '';
            $('monSearchClear').classList.add('hidden');
            monRefresh();
        }

        // ── Events ──
        const monInput = $('monSearch');
        const monClear = $('monSearchClear');

        monInput.addEventListener('input', function () {
            monClear.classList.toggle('hidden', this.value.length === 0);
            clearTimeout(monSearchDebounce);
            const v = this.value;
            monSearchDebounce = setTimeout(() => { monF.q = v.trim(); monRefresh(); }, 350);
        });

        monClear.addEventListener('click', () => {
            monInput.value = '';
            monClear.classList.add('hidden');
            monF.q = '';
            monRefresh();
            monInput.focus();
        });

        // Modal open / close
        $('monFilterBtn').addEventListener('click', monOpenModal);
        $('monModalClose').addEventListener('click', monCloseModal);
        $('monModalDone').addEventListener('click', monCloseModal);
        $('monModalBackdrop').addEventListener('click', monCloseModal);
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape' && monModalOpen()) monCloseModal();
        });

        $('monPanelClear').addEventListener('click', monResetAll);
        $('monClearAll').addEventListener('click', monResetAll);

        // Selects at date inputs
        Object.entries(MON_FIELDS).forEach(([key, id]) => {
            $(id).addEventListener('change', function () {
                monF[key] = this.value;
                monRefresh();
            });
        });

        // Status checkboxes
        $('monStatusList').addEventListener('change', e => {
            const cb = e.target.closest('input[type=checkbox]');
            if (!cb) return;
            cb.checked ? monF.status.add(cb.value) : monF.status.delete(cb.value);
            monRefresh();
        });

        // Any / All
        $('monMatch').addEventListener('click', e => {
            const b = e.target.closest('button[data-match]');
            if (!b) return;
            monF.status_match = b.dataset.match;
            monRefresh();
        });

        // Quick filters
        $('monPresets').addEventListener('click', e => {
            const b = e.target.closest('button[data-preset]');
            if (!b) return;
            const p = MON_PRESETS[Number(b.dataset.preset)];
            if (monPresetActive(p)) {
                monF.status.clear();
            } else {
                monF.status = new Set(p.statuses);
                monF.status_match = 'any';
            }
            monRefresh();
        });

        // Remove pill
        $('monPills').addEventListener('click', e => {
            const b = e.target.closest('button[data-remove]');
            if (!b) return;
            const k = b.dataset.remove;
            if (k === 'status') monF.status.delete(b.dataset.val);
            else if (k === 'status_range') { monF.status_from = ''; monF.status_to = ''; }
            else if (k === 'filed_range') { monF.from = ''; monF.to = ''; }
            else monF[k] = '';
            monRefresh();
        });

        monSyncUI();
        monLoadOptions();
        monFetchList();
    </script>

</body>

</html>