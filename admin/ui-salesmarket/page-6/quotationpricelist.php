<?php
// admin/ui-salesmarket/page-5/quotationpricelist.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SALES];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation Price List</title>
    <style>
        /* Matrix hover highlight */
        #matrix th,
        #matrix td {
            transition: background-color .12s, color .12s;
        }

        #matrix .hl-line {
            background-color: #eff6ff;
        }

        /* buong row + column (mahina) */
        #matrix .hl-head {
            background-color: #dbeafe;
            color: #1d4ed8;
            font-weight: 700;
        }

        /* row name + carcass header */
        #matrix .hl-cell {
            background-color: #2563eb;
            color: #fff;
            font-weight: 700;
        }

        /* mismong presyo */
        #matrix td.price-cell {
            cursor: default;
        }
    </style>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
</head>

<body class="bg-slate-100">
    <main class="ml-56 min-h-screen p-8">

        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
            <div>
                <h1 id="pageTitle" class="text-2xl font-bold text-slate-800">Price List</h1>
                <p class="text-sm text-slate-500">I-click ang Edit para palitan ang mga presyo ng isang row.</p>
            </div>
            <button id="btnAdd" class="px-4 py-2 bg-black text-white rounded-full hover:bg-slate-900 text-sm">Add Row</button>
        </div>

        <!-- Category tabs -->
        <div id="tabs" class="flex gap-2 mb-5"></div>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-3 mb-5">
            <div class="relative flex-1 min-w-[220px] max-w-sm">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm"></i>
                <input id="fSearch" type="search" placeholder="Search row, group or sink…" autocomplete="off"
                    class="w-full border border-slate-300 rounded-lg pl-9 pr-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
            </div>
            <select id="fGroup"
                class="border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"></select>
            <select id="fStatus"
                class="border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="">Status: All</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
            <button id="fClear" type="button" class="hidden text-sm text-blue-600 hover:underline">Clear filters</button>
            <span id="fCount" class="ml-auto text-sm text-slate-500"></span>
        </div>

        <!-- Matrix -->
        <div id="matrix"></div>

        <!-- Drawer prices -->
        <div id="drawerBox"></div>

        <!-- Kitchen sink costing -->
        <div id="sinkBox"></div>

        <!-- Modal -->
        <div id="modal" class="hidden fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl flex flex-col max-h-[90vh]">

                <!-- Header -->
                <div class="flex items-start justify-between px-6 pt-5 pb-4 border-b border-slate-200">
                    <div>
                        <h2 id="modalTitle" class="text-lg font-semibold text-slate-800">Add Row</h2>
                        <p id="modalSub" class="text-sm text-slate-500"></p>
                    </div>
                    <button type="button" id="btnX"
                        class="text-slate-400 hover:text-slate-600 text-2xl leading-none">&times;</button>
                </div>

                <!-- Body -->
                <div class="px-6 py-5 overflow-y-auto space-y-6">
                    <input type="hidden" id="fId" value="0">

                    <!-- Details -->
                    <section class="space-y-4">
                        <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Details</h3>

                        <div>
                            <label id="lblGroup" class="block text-sm font-medium text-slate-700 mb-1">Group</label>
                            <select id="mGroupSel"
                                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"></select>
                            <input id="mGroupNew" maxlength="100" placeholder="Type the new name"
                                class="hidden mt-2 w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div>
                            <label id="lblRow" class="block text-sm font-medium text-slate-700 mb-1">Row</label>
                            <input id="mRow" maxlength="100"
                                class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        <div id="sizeBox" class="hidden">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Max Depth</label>
                                    <div class="relative">
                                        <span
                                            class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">≤</span>
                                        <input type="number" min="0" id="mDepth" placeholder="none"
                                            class="w-full border border-slate-300 rounded-lg pl-8 pr-10 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <span
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">mm</span>
                                    </div>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-slate-700 mb-1">Max Height</label>
                                    <div class="relative">
                                        <span
                                            class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">≤</span>
                                        <input type="number" min="0" id="mHeight" placeholder="none"
                                            class="w-full border border-slate-300 rounded-lg pl-8 pr-10 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <span
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">mm</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Prices -->
                    <section>
                        <div class="flex items-baseline justify-between mb-3">
                            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-400">Prices</h3>
                            <span class="text-xs text-slate-400">Blank = walang presyo</span>
                        </div>
                        <div id="priceInputs" class="grid grid-cols-2 sm:grid-cols-3 gap-4"></div>
                    </section>

                    <!-- Active toggle -->
                    <label class="flex items-center gap-3 cursor-pointer select-none">
                        <input type="checkbox" id="mActive" class="sr-only peer" checked>
                        <span
                            class="relative w-10 h-6 rounded-full bg-slate-300 transition peer-checked:bg-blue-600
                                     after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-5 after:h-5
                                     after:bg-white after:rounded-full after:transition peer-checked:after:translate-x-4"></span>
                        <span class="text-sm text-slate-700">Active</span>
                    </label>

                    <div id="formError"
                        class="hidden text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2"></div>
                </div>

                <!-- Footer -->
                <div class="flex justify-end gap-2 px-6 py-4 border-t border-slate-200 bg-slate-50 rounded-b-2xl">
                    <button type="button" id="btnCancel"
                        class="px-4 py-2 text-sm border border-slate-300 rounded-lg bg-white hover:bg-slate-100">Cancel</button>
                    <button type="button" id="btnSave"
                        class="px-5 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">Save</button>
                </div>
            </div>
        </div>

        <!-- Drawer size modal -->
        <div id="sizeModal" class="hidden fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
                <div class="flex items-start justify-between px-6 pt-5 pb-4 border-b border-slate-200">
                    <div>
                        <h2 id="sizeTitle" class="text-lg font-semibold text-slate-800">Add Drawer Size</h2>
                        <p class="text-sm text-slate-500">Applies to both Kitchen and Wardrobe.</p>
                    </div>
                    <button type="button" id="sizeX"
                        class="text-slate-400 hover:text-slate-600 text-2xl leading-none">&times;</button>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <input type="hidden" id="sId" value="0">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Min Width</label>
                            <div class="relative">
                                <input type="number" min="1" id="sMin"
                                    class="w-full border border-slate-300 rounded-lg pl-3 pr-10 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">mm</span>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Max Width</label>
                            <div class="relative">
                                <input type="number" min="1" id="sMax" placeholder="and up"
                                    class="w-full border border-slate-300 rounded-lg pl-3 pr-10 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs">mm</span>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-slate-400">Iwanang blank ang Max Width para sa pinakamalaking size (e.g. 801mm+).</p>
                    <div id="sizeError"
                        class="hidden text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2"></div>
                </div>
                <div class="flex justify-end gap-2 px-6 py-4 border-t border-slate-200 bg-slate-50 rounded-b-2xl">
                    <button type="button" id="sizeCancel"
                        class="px-4 py-2 text-sm border border-slate-300 rounded-lg bg-white hover:bg-slate-100">Cancel</button>
                    <button type="button" id="sizeSave"
                        class="px-5 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">Save</button>
                </div>
            </div>
        </div>

        <!-- Sink modal -->
        <div id="sinkModal" class="hidden fixed inset-0 bg-slate-900/50 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm">
                <div class="flex items-start justify-between px-6 pt-5 pb-4 border-b border-slate-200">
                    <h2 id="snTitle" class="text-lg font-semibold text-slate-800">Add Sink</h2>
                    <button type="button" id="snX"
                        class="text-slate-400 hover:text-slate-600 text-2xl leading-none">&times;</button>
                </div>
                <div class="px-6 py-5 space-y-4">
                    <input type="hidden" id="snId" value="0">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Tub Type</label>
                        <select id="snType"
                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500"></select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Item Size</label>
                        <input id="snSize" maxlength="50" placeholder="e.g. 400mm x 400mm"
                            class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Price</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">₱</span>
                            <input type="number" step="0.01" min="0.01" id="snPrice"
                                class="w-full border border-slate-300 rounded-lg pl-7 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>
                    <div id="snError"
                        class="hidden text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2"></div>
                </div>
                <div class="flex justify-end gap-2 px-6 py-4 border-t border-slate-200 bg-slate-50 rounded-b-2xl">
                    <button type="button" id="snCancel"
                        class="px-4 py-2 text-sm border border-slate-300 rounded-lg bg-white hover:bg-slate-100">Cancel</button>
                    <button type="button" id="snSave"
                        class="px-5 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">Save</button>
                </div>
            </div>
        </div>

    </main>

    <script>
        const API = '<?= BASE_URL ?>/quotationpricelistajax';
        const $ = id => document.getElementById(id);
        const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        const peso = n => Number(n).toLocaleString('en-PH', { minimumFractionDigits: 0 });

        const NEW_GROUP = '__new__';
        const PRICE_INPUT_CLS = 'w-full border border-slate-300 rounded-lg pl-7 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500';

        let CATS = {}, SINK_TYPES = {}, SIZES = [], SINKS = [], current = '', DATA = [], DRAWER = {};

        /* ───────────── Init / Tabs ───────────── */
        async function init() {
            const r = await (await fetch(`${API}?action=meta`)).json();
            CATS = r.categories;
            SINK_TYPES = r.sink_types;
            current = Object.keys(CATS)[0];
            renderTabs();
            loadList();
        }

        function renderTabs() {
            $('tabs').innerHTML = Object.entries(CATS).map(([k, c]) => `
        <button onclick="switchTab('${k}')"
            class="px-4 py-2 rounded-lg font-medium ${k === current ? 'bg-slate-800 text-white' : 'bg-white text-slate-600 hover:bg-slate-50 shadow'}">
            ${esc(c.label)}
        </button>`).join('');
            $('pageTitle').textContent = CATS[current].label + ' Price List';
        }

        function switchTab(k) { current = k; resetFilters(); renderTabs(); loadList(); }

        /* ───────────── Filters ───────────── */
        function isFiltering() {
            return !!($('fSearch').value.trim() || $('fGroup').value || $('fStatus').value);
        }

        function getFiltered() {
            const q = $('fSearch').value.trim().toLowerCase();
            const g = $('fGroup').value;
            const st = $('fStatus').value;
            return DATA.filter(r =>
                (!g || r.group_name === g) &&
                (!st || (st === 'active') === (r.is_active == 1)) &&
                (!q || r.row_name.toLowerCase().includes(q) || r.group_name.toLowerCase().includes(q))
            );
        }

        // Search lang ang applicable sa sinks (walang group/status ang sink)
        function getFilteredSinks() {
            const q = $('fSearch').value.trim().toLowerCase();
            return SINKS.filter(s => !q
                || s.item_size.toLowerCase().includes(q)
                || (SINK_TYPES[s.tub_type] || '').toLowerCase().includes(q));
        }

        function applyFilters() { render(); renderSinks(); }

        function populateGroupFilter() {
            const prev = $('fGroup').value;
            const groups = [...new Set(DATA.map(r => r.group_name))];
            $('fGroup').innerHTML =
                `<option value="">${esc(CATS[current].group_label)}: All</option>` +
                groups.map(g => `<option value="${esc(g)}">${esc(g)}</option>`).join('');
            $('fGroup').value = groups.includes(prev) ? prev : '';
        }

        function resetFilters() {
            $('fSearch').value = '';
            $('fGroup').value = '';
            $('fStatus').value = '';
        }

        function clearFilters() { resetFilters(); applyFilters(); $('fSearch').focus(); }

        /* ───────────── List / Matrix ───────────── */
        async function loadList() {
            const sinkReq = CATS[current].has_sink
                ? fetch(`${API}?action=sink_list`).then(r => r.json())
                : Promise.resolve({ success: true, data: [] });
            const [l, d, sk] = await Promise.all([
                fetch(`${API}?action=list&category=${encodeURIComponent(current)}`).then(r => r.json()),
                fetch(`${API}?action=drawer_list&category=${encodeURIComponent(current)}`).then(r => r.json()),
                sinkReq
            ]);
            SINKS = sk.success ? sk.data : [];
            DATA = l.success ? l.data : [];
            SIZES = d.success ? d.sizes : [];
            DRAWER = d.success ? d.data : {};
            populateGroupFilter();
            render();
            renderDrawer();
            renderSinks();
        }

        function render() {
            const cfg = CATS[current];
            const codes = Object.keys(cfg.carcass);

            const list = getFiltered();
            const filtering = isFiltering();
            const sinkPart = cfg.has_sink
                ? (filtering ? ` · ${getFilteredSinks().length} of ${SINKS.length} sinks` : ` · ${SINKS.length} sinks`)
                : '';
            $('fCount').textContent = (filtering ? `Showing ${list.length} of ${DATA.length} rows` : `${DATA.length} rows`) + sinkPart;
            $('fClear').classList.toggle('hidden', !filtering);

            const groups = new Map();
            list.forEach(r => {
                if (!groups.has(r.group_name)) groups.set(r.group_name, []);
                groups.get(r.group_name).push(r);
            });

            if (!groups.size) {
                $('matrix').innerHTML = DATA.length
                    ? '<div class="bg-white rounded-xl shadow p-8 text-center text-slate-400 mb-5">Walang tumugma sa filter. <button class="text-blue-600 hover:underline" onclick="clearFilters()">I-clear ang filters</button></div>'
                    : '<div class="bg-white rounded-xl shadow p-8 text-center text-slate-400 mb-5">Wala pang laman. I-click ang "+ Add Row".</div>';
                return;
            }

            $('matrix').innerHTML = [...groups].map(([g, rows]) => `
        <div class="bg-white rounded-xl shadow mb-5 overflow-x-auto">
            <div class="px-4 py-3 bg-slate-800 text-white font-semibold rounded-t-xl">${esc(g)}</div>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="px-4 py-2 text-left">${esc(cfg.row_label)}</th>
                        ${cfg.has_size ? '<th class="px-4 py-2 text-left">Depth</th><th class="px-4 py-2 text-left">Height</th>' : ''}
                        ${codes.map(c => `<th data-col="${esc(c)}" class="px-4 py-2 text-right">${esc(cfg.carcass[c])}</th>`).join('')}
                        <th class="px-4 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${rows.map(r => `
                    <tr class="border-t ${r.is_active == 1 ? '' : 'opacity-50'}">
                        <td class="row-head px-4 py-2 font-medium">
                            ${esc(r.row_name)}
                            ${r.is_active == 1 ? '' : '<span class="ml-2 px-2 py-0.5 rounded-full text-xs bg-slate-200 text-slate-600">Inactive</span>'}
                        </td>
                        ${cfg.has_size ? `
                            <td class="px-4 py-2 text-slate-600">${r.max_depth ? '≤ ' + r.max_depth : '—'}</td>
                            <td class="px-4 py-2 text-slate-600">${r.max_height ? '≤ ' + r.max_height : '—'}</td>` : ''}
                        ${codes.map(c => `
                            <td data-col="${esc(c)}" class="price-cell px-4 py-2 text-right">
                                ${r.prices[c] != null ? peso(r.prices[c]) : '<span class="text-slate-300">—</span>'}
                            </td>`).join('')}
                        <td class="px-4 py-2 text-right whitespace-nowrap">
                            <button class="text-blue-600 hover:underline mr-3" onclick="editRow(${r.id})">Edit</button>
                            <button class="text-red-600 hover:underline" onclick="removeRow(${r.id})">Delete</button>
                        </td>
                    </tr>`).join('')}
                </tbody>
            </table>
        </div>`).join('');
        }

        /* ───────────── Drawer ───────────── */
        const sizeLabel = s => s.max_width == null ? `${s.min_width}mm+` : `${s.min_width}mm – ${s.max_width}mm`;

        function renderDrawer() {
            const title = `
            <div class="px-4 py-3 bg-slate-800 text-white font-semibold rounded-t-xl flex items-center justify-between">
                <span>Drawer (with soft close drawer guide)</span>
                <button onclick="openSize()" class="px-3 py-1 text-xs bg-white/15 hover:bg-white/25 rounded-lg">+ Add Size</button>
            </div>`;

            if (!SIZES.length) {
                $('drawerBox').innerHTML = `<div class="bg-white rounded-xl shadow mb-5">${title}
                    <div class="p-6 text-center text-slate-400 text-sm">Wala pang size. I-click ang "+ Add Size".</div></div>`;
                return;
            }

            $('drawerBox').innerHTML = `
        <div class="bg-white rounded-xl shadow mb-5 overflow-x-auto">
            ${title}
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        ${SIZES.map(s => `
                        <th class="px-4 py-2 text-right">
                            ${esc(sizeLabel(s))}
                            <div class="mt-0.5 text-xs font-normal">
                                <button class="text-blue-600 hover:underline mr-2" onclick="editSize(${s.id})">Edit</button>
                                <button class="text-red-600 hover:underline" onclick="removeSize(${s.id})">Delete</button>
                            </div>
                        </th>`).join('')}
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-t">
                        ${SIZES.map(s => `
                        <td class="px-4 py-3">
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">₱</span>
                                <input type="number" step="0.01" min="0.01" data-drawer="${s.id}"
                                       value="${DRAWER[s.id] != null ? DRAWER[s.id] : ''}" placeholder="—"
                                       class="${PRICE_INPUT_CLS} text-right">
                            </div>
                        </td>`).join('')}
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <button id="btnDrawerSave" onclick="saveDrawer()"
                                class="px-4 py-2 text-sm bg-blue-600 text-white rounded-lg hover:bg-blue-700">Save</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>`;
        }

        async function saveDrawer() {
            const fd = new FormData();
            fd.append('action', 'drawer_save');
            fd.append('category', current);
            document.querySelectorAll('#drawerBox input[data-drawer]')
                .forEach(i => fd.append(`prices[${i.dataset.drawer}]`, i.value));

            const btn = $('btnDrawerSave');
            btn.disabled = true;
            try {
                const r = await (await fetch(API, { method: 'POST', body: fd })).json();
                if (!r.success) return alert(r.message);
                loadList();
            } catch (e) {
                alert('Network error. Try again.');
            } finally {
                btn.disabled = false;
            }
        }

        /* ── Drawer size modal ── */
        function openSize(size = null) {
            $('sizeError').classList.add('hidden');
            $('sizeTitle').textContent = size ? 'Edit Drawer Size' : 'Add Drawer Size';
            $('sId').value = size ? size.id : 0;
            $('sMin').value = size?.min_width ?? '';
            $('sMax').value = size?.max_width ?? '';
            $('sizeModal').classList.remove('hidden');
            setTimeout(() => $('sMin').focus(), 50);
        }
        const closeSize = () => $('sizeModal').classList.add('hidden');
        function editSize(id) { openSize(SIZES.find(s => s.id == id)); }

        async function saveSize() {
            const showErr = msg => {
                $('sizeError').textContent = msg;
                $('sizeError').classList.remove('hidden');
            };
            if (!$('sMin').value) return showErr('Min width is required.');

            const fd = new FormData();
            fd.append('action', 'drawer_size_save');
            fd.append('id', $('sId').value);
            fd.append('min_width', $('sMin').value);
            fd.append('max_width', $('sMax').value);

            $('sizeSave').disabled = true;
            try {
                const r = await (await fetch(API, { method: 'POST', body: fd })).json();
                if (!r.success) return showErr(r.message);
                closeSize();
                loadList();
            } catch (e) {
                showErr('Network error. Try again.');
            } finally {
                $('sizeSave').disabled = false;
            }
        }

        async function removeSize(id) {
            if (!confirm('Delete this drawer size? Mawawala ito sa Kitchen at Wardrobe. Hindi maaapektuhan ang mga lumang quotation.')) return;
            const fd = new FormData();
            fd.append('action', 'drawer_size_delete');
            fd.append('id', id);
            const r = await (await fetch(API, { method: 'POST', body: fd })).json();
            if (!r.success) return alert(r.message);
            loadList();
        }

        /* ───────────── Kitchen Sink Costing ───────────── */
        function renderSinks() {
            if (!CATS[current].has_sink) { $('sinkBox').innerHTML = ''; return; }
            const visible = getFilteredSinks();

            const col = (type, label) => {
                const rows = visible.filter(s => s.tub_type === type);
                return `
                <div class="min-w-0">
                    <div class="px-4 py-2 bg-slate-50 text-slate-600 text-sm font-semibold border-b">${esc(label)}</div>
                    ${rows.length ? `
                    <table class="w-full text-sm">
                        <thead class="text-slate-500 text-xs uppercase">
                            <tr><th class="px-4 py-2 text-left font-medium">Item Size</th>
                                <th class="px-4 py-2 text-right font-medium">Price</th>
                                <th class="px-4 py-2"></th></tr>
                        </thead>
                        <tbody>
                            ${rows.map(s => `
                            <tr class="border-t">
                                <td class="px-4 py-2 font-medium">${esc(s.item_size)}</td>
                                <td class="px-4 py-2 text-right">${peso(s.price)}</td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">
                                    <button class="text-blue-600 hover:underline mr-3" onclick="editSink(${s.id})">Edit</button>
                                    <button class="text-red-600 hover:underline" onclick="removeSink(${s.id})">Delete</button>
                                </td>
                            </tr>`).join('')}
                        </tbody>
                    </table>` : `<div class="p-6 text-center text-slate-400 text-sm">${SINKS.some(s => s.tub_type === type) ? 'Walang tumugma sa search.' : 'Wala pang laman.'}</div>`}
                </div>`;
            };

            $('sinkBox').innerHTML = `
            <div class="bg-white rounded-xl shadow mb-5 overflow-hidden">
                <div class="px-4 py-3 bg-slate-800 text-white font-semibold flex items-center justify-between">
                    <span>Kitchen Sink Costing <span class="font-normal text-slate-300 text-sm">(Olidan supplier · undermount &amp; topmount)</span></span>
                    <button onclick="openSink()" class="px-3 py-1 text-xs bg-white/15 hover:bg-white/25 rounded-lg">+ Add Sink</button>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-2 lg:divide-x">
                    ${Object.entries(SINK_TYPES).map(([t, l]) => col(t, l)).join('')}
                </div>
            </div>`;
        }

        function openSink(sink = null) {
            $('snError').classList.add('hidden');
            $('snTitle').textContent = sink ? 'Edit Sink' : 'Add Sink';
            $('snId').value = sink ? sink.id : 0;
            $('snType').innerHTML = Object.entries(SINK_TYPES)
                .map(([k, l]) => `<option value="${esc(k)}">${esc(l)}</option>`).join('');
            $('snType').value = sink ? sink.tub_type : Object.keys(SINK_TYPES)[0];
            $('snSize').value = sink ? sink.item_size : '';
            $('snPrice').value = sink ? sink.price : '';
            $('sinkModal').classList.remove('hidden');
            setTimeout(() => $('snSize').focus(), 50);
        }
        const closeSink = () => $('sinkModal').classList.add('hidden');
        function editSink(id) { openSink(SINKS.find(s => s.id == id)); }

        async function saveSink() {
            const showErr = msg => {
                $('snError').textContent = msg;
                $('snError').classList.remove('hidden');
            };
            if (!$('snSize').value.trim()) return showErr('Item size is required.');
            if (!$('snPrice').value) return showErr('Price is required.');

            const fd = new FormData();
            fd.append('action', 'sink_save');
            fd.append('id', $('snId').value);
            fd.append('tub_type', $('snType').value);
            fd.append('item_size', $('snSize').value);
            fd.append('price', $('snPrice').value);

            $('snSave').disabled = true;
            try {
                const r = await (await fetch(API, { method: 'POST', body: fd })).json();
                if (!r.success) return showErr(r.message);
                closeSink();
                loadList();
            } catch (e) {
                showErr('Network error. Try again.');
            } finally {
                $('snSave').disabled = false;
            }
        }

        async function removeSink(id) {
            if (!confirm('Delete this sink? Hindi maaapektuhan ang mga lumang quotation.')) return;
            const fd = new FormData();
            fd.append('action', 'sink_delete');
            fd.append('id', id);
            const r = await (await fetch(API, { method: 'POST', body: fd })).json();
            if (!r.success) return alert(r.message);
            loadList();
        }

        /* ───────────── Matrix hover highlight ───────────── */
        const matrixEl = $('matrix');

        function clearHighlight() {
            matrixEl.querySelectorAll('.hl-line, .hl-head, .hl-cell')
                .forEach(el => el.classList.remove('hl-line', 'hl-head', 'hl-cell'));
        }

        matrixEl.addEventListener('mouseover', e => {
            const td = e.target.closest('td.price-cell');
            clearHighlight();
            if (!td) return;

            const tr = td.parentElement;
            const table = td.closest('table');
            const col = td.dataset.col;

            // mahinang tint: buong row at buong column
            tr.querySelectorAll('td:not(:last-child)').forEach(el => el.classList.add('hl-line'));
            table.querySelectorAll(`[data-col="${CSS.escape(col)}"]`).forEach(el => el.classList.add('hl-line'));

            // malakas: pangalan ng row + header ng column
            tr.querySelector('.row-head').classList.add('hl-head');
            table.querySelector(`th[data-col="${CSS.escape(col)}"]`).classList.add('hl-head');

            // pinakamalakas: ang presyong naka-hover
            td.classList.remove('hl-line');
            td.classList.add('hl-cell');
        });

        matrixEl.addEventListener('mouseleave', clearHighlight);

        /* ───────────── Modal ───────────── */
        function editRow(id) { openModal(DATA.find(r => r.id == id)); }

        function getGroup() {
            return ($('mGroupSel').value === NEW_GROUP ? $('mGroupNew').value : $('mGroupSel').value).trim();
        }

        function toggleNewGroup() {
            const isNew = $('mGroupSel').value === NEW_GROUP;
            $('mGroupNew').classList.toggle('hidden', !isNew);
            if (isNew) $('mGroupNew').focus();
        }

        function openModal(row = null) {
            const cfg = CATS[current];
            $('formError').classList.add('hidden');
            $('modalTitle').textContent = (row ? 'Edit ' : 'Add ') + cfg.label + ' Row';
            $('modalSub').textContent = row ? `${row.group_name} › ${row.row_name}` : 'Fill in the details and prices below.';
            $('lblGroup').textContent = cfg.group_label;
            $('lblRow').textContent = cfg.row_label;
            $('fId').value = row ? row.id : 0;
            $('mRow').value = row ? row.row_name : '';
            $('mDepth').value = row?.max_depth ?? '';
            $('mHeight').value = row?.max_height ?? '';
            $('sizeBox').classList.toggle('hidden', !cfg.has_size);
            $('mActive').checked = row ? row.is_active == 1 : true;

            // Group select: existing groups + "New group"
            const groups = [...new Set(DATA.map(r => r.group_name))];
            $('mGroupSel').innerHTML =
                groups.map(g => `<option value="${esc(g)}">${esc(g)}</option>`).join('') +
                `<option value="${NEW_GROUP}">＋ New ${esc(cfg.group_label.toLowerCase())}…</option>`;

            $('mGroupNew').value = '';
            if (row) {
                $('mGroupSel').value = row.group_name;
            } else if (!groups.length) {
                $('mGroupSel').value = NEW_GROUP;   // walang group pa, diretso new
            }
            toggleNewGroup();
            if ($('mGroupSel').value !== NEW_GROUP) $('mGroupNew').classList.add('hidden');

            // Price inputs bawat carcass, may ₱ prefix
            $('priceInputs').innerHTML = Object.entries(cfg.carcass).map(([code, label]) => `
        <div>
            <label class="block text-xs font-medium text-slate-600 mb-1">${esc(label)}</label>
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">₱</span>
                <input type="number" step="0.01" min="0.01" data-code="${esc(code)}" placeholder="—"
                       value="${row && row.prices[code] != null ? row.prices[code] : ''}"
                       class="${PRICE_INPUT_CLS}">
            </div>
        </div>`).join('');

            $('modal').classList.remove('hidden');
            setTimeout(() => $('mRow').focus(), 50);
        }

        const closeModal = () => $('modal').classList.add('hidden');

        /* ───────────── Save / Delete ───────────── */
        async function save() {
            const group = getGroup();
            const showErr = msg => {
                $('formError').textContent = msg;
                $('formError').classList.remove('hidden');
            };

            if (!group) return showErr(CATS[current].group_label + ' is required.');
            if (!$('mRow').value.trim()) return showErr(CATS[current].row_label + ' is required.');

            const fd = new FormData();
            fd.append('action', 'save');
            fd.append('id', $('fId').value);
            fd.append('category', current);
            fd.append('group_name', group);
            fd.append('row_name', $('mRow').value);
            fd.append('max_depth', $('mDepth').value);
            fd.append('max_height', $('mHeight').value);
            if ($('mActive').checked) fd.append('is_active', '1');
            document.querySelectorAll('#priceInputs input').forEach(i => fd.append(`prices[${i.dataset.code}]`, i.value));

            $('btnSave').disabled = true;
            try {
                const r = await (await fetch(API, { method: 'POST', body: fd })).json();
                if (!r.success) return showErr(r.message);
                closeModal();
                loadList();
            } catch (e) {
                showErr('Network error. Try again.');
            } finally {
                $('btnSave').disabled = false;
            }
        }

        async function removeRow(id) {
            if (!confirm('Delete this row? Hindi maaapektuhan ang mga lumang quotation.')) return;
            const fd = new FormData();
            fd.append('action', 'delete');
            fd.append('id', id);
            const r = await (await fetch(API, { method: 'POST', body: fd })).json();
            if (!r.success) return alert(r.message);
            loadList();
        }

        /* ───────────── Events ───────────── */
        $('btnAdd').onclick = () => openModal();
        $('btnCancel').onclick = closeModal;
        $('btnX').onclick = closeModal;
        $('btnSave').onclick = save;
        $('mGroupSel').addEventListener('change', toggleNewGroup);
        $('fSearch').addEventListener('input', applyFilters);
        $('fGroup').addEventListener('change', applyFilters);
        $('fStatus').addEventListener('change', applyFilters);
        $('fClear').onclick = clearFilters;
        $('snX').onclick = closeSink;
        $('snCancel').onclick = closeSink;
        $('snSave').onclick = saveSink;
        $('sinkModal').addEventListener('mousedown', e => { if (e.target === $('sinkModal')) closeSink(); });
        $('sizeX').onclick = closeSize;
        $('sizeCancel').onclick = closeSize;
        $('sizeSave').onclick = saveSize;
        $('sizeModal').addEventListener('mousedown', e => { if (e.target === $('sizeModal')) closeSize(); });
        $('modal').addEventListener('mousedown', e => { if (e.target === $('modal')) closeModal(); });
        document.addEventListener('keydown', e => {
            if (e.key !== 'Escape') return;
            if (!$('modal').classList.contains('hidden')) closeModal();
            if (!$('sizeModal').classList.contains('hidden')) closeSize();
            if (!$('sinkModal').classList.contains('hidden')) closeSink();
        });

        init();
    </script>
</body>

</html>