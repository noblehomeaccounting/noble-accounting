<?php
//crmlist.php
$crmListAjaxUrl = BASE_URL . '/crmlistajax';
$crm2dQuotationUrl = BASE_URL . '/crm2dquotation';
?>

<div class="max-w-6xl mx-auto">

    <!-- ═══ HEADER ═══ -->
    <section class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden mb-4">
        <div class="h-1.5 bg-[repeating-linear-gradient(-45deg,#f59e0b_0_10px,#1f2937_10px_20px)]"></div>
        <div class="p-5 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <span class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-address-book text-lg"></i></span>
                <div class="min-w-0">
                    <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase">CRM Management · Sales</p>
                    <h1 class="text-gray-900 text-lg font-semibold leading-tight">Sales &amp; Market List</h1>
                    <p class="text-gray-400 text-[11px] mt-0.5">Schedule the site visit and assign a designer, then follow the inquiry to 2D &amp; Quotation.</p>
                </div>
            </div>
        </div>

        <!-- Stage counters (= quick filter) -->
        <div id="crmStageTabs" class="px-5 pb-4 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2">
            <?php foreach ([
                ['', 'All', 'fa-layer-group', 'text-gray-900'],
                ['schedule', 'To schedule', 'fa-calendar-plus', 'text-amber-700'],
                ['sitevisit', 'Site visit', 'fa-ruler-combined', 'text-orange-700'],
                ['quotation', '2D & Quotation', 'fa-file-pen', 'text-blue-700'],
                ['stopped', 'Not proceeding', 'fa-ban', 'text-red-600'],
            ] as [$val, $label, $icon, $cls]): ?>
                <button type="button" data-stage="<?= $val ?>"
                    class="crm-stage-tab text-left rounded-xl border px-3.5 py-2 transition-colors">
                    <span class="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wide text-gray-500"><i class="fa-solid <?= $icon ?> text-[9px]"></i><?= htmlspecialchars($label) ?></span>
                    <span data-stage-count="<?= $val ?>" class="block text-xl font-bold leading-tight tabular-nums <?= $cls ?>">0</span>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Search + filters (same IDs as before) -->
        <div class="px-5 py-3 border-t border-gray-100 bg-gray-50/60">
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative flex-1 min-w-[200px] max-w-md">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[11px] pointer-events-none"></i>
                    <input id="crmListSearch" type="text" placeholder="Search control no., client or contact…"
                        class="w-full h-9 bg-white border border-gray-200 rounded-lg pl-8 pr-8 text-xs outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-100">
                    <button type="button" id="crmListSearchClear" aria-label="Clear search"
                        class="hidden absolute right-1.5 top-1/2 -translate-y-1/2 inline-flex items-center justify-center w-6 h-6 rounded-md text-gray-400 hover:text-gray-700 hover:bg-gray-100"><i class="fa-solid fa-xmark text-xs leading-none"></i></button>
                </div>
                <button type="button" id="crmFilterToggle" onclick="crmToggleFilters()"
                    class="inline-flex items-center gap-1.5 h-9 px-3 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">
                    <i class="fa-solid fa-sliders"></i> Filters <span id="crmFilterCountBadge" class="hidden min-w-[18px] h-[18px] px-1 rounded-full bg-amber-700 text-white text-[10px] font-bold items-center justify-center">0</span>
                </button>
                <select id="crmSortSelect" aria-label="Sort"
                    class="h-9 pl-2.5 pr-7 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg outline-none focus:border-amber-600">
                    <option value="created_at:desc">Newest first</option>
                    <option value="created_at:asc">Oldest first</option>
                    <option value="client_name:asc">Client A–Z</option>
                    <option value="control_no:asc">Control no.</option>
                    <option value="contract_amount:desc">Highest amount</option>
                    <option value="measurement_datetime:asc">Site visit date</option>
                </select>
                <button type="button" id="crmFilterClear"
                    class="hidden inline-flex items-center gap-1.5 h-9 px-3 text-xs font-semibold text-amber-700 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100">
                    <i class="fa-solid fa-xmark"></i> Clear filters
                </button>
                <span id="crmListCount" class="ml-auto text-[11px] text-gray-400"></span>
            </div>

            <div id="crmFilterPanel" class="hidden mt-3 grid grid-cols-2 md:grid-cols-4 xl:grid-cols-[repeat(4,minmax(0,1fr))_minmax(0,1.6fr)] gap-3">
                <label class="block">
                    <span class="block mb-1 text-[11px] font-semibold text-gray-500">Status</span>
                    <select id="crmFilterStatus" class="crm-filter w-full h-9 px-2.5 text-xs text-gray-700 bg-white border border-gray-200 rounded-lg outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-100">
                        <option value="">All status</option>
                    </select>
                </label>
                <label class="block">
                    <span class="block mb-1 text-[11px] font-semibold text-gray-500">Designer</span>
                    <select id="crmFilterDesigner" class="crm-filter w-full h-9 px-2.5 text-xs text-gray-700 bg-white border border-gray-200 rounded-lg outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-100">
                        <option value="">All designers</option>
                    </select>
                </label>
                <label class="block">
                    <span class="block mb-1 text-[11px] font-semibold text-gray-500">Project</span>
                    <select id="crmFilterProject" class="crm-filter w-full h-9 px-2.5 text-xs text-gray-700 bg-white border border-gray-200 rounded-lg outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-100">
                        <option value="">All projects</option>
                    </select>
                </label>
                <label class="block">
                    <span class="block mb-1 text-[11px] font-semibold text-gray-500">Contract amount</span>
                    <select id="crmFilterAmount" class="crm-filter w-full h-9 px-2.5 text-xs text-gray-700 bg-white border border-gray-200 rounded-lg outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-100">
                        <option value="">Any</option>
                        <option value="with">With amount</option>
                        <option value="without">No amount yet</option>
                    </select>
                </label>
                <div class="col-span-2 md:col-span-4 xl:col-span-1">
                    <span class="block mb-1 text-[11px] font-semibold text-gray-500">Date filed</span>
                    <div class="flex items-center h-9 bg-white border border-gray-200 rounded-lg focus-within:ring-2 focus-within:ring-amber-100 focus-within:border-amber-600">
                        <input id="crmFilterFrom" type="date" aria-label="Filed from" class="crm-filter flex-1 min-w-0 h-full px-2.5 text-xs text-gray-700 bg-transparent border-0 rounded-l-lg outline-none">
                        <span class="text-gray-300 text-sm select-none">–</span>
                        <input id="crmFilterTo" type="date" aria-label="Filed to" class="crm-filter flex-1 min-w-0 h-full px-2.5 text-xs text-gray-700 bg-transparent border-0 rounded-r-lg outline-none">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ═══ LIST (may sariling scroll) ═══ -->
    <div id="crmListCards" class="space-y-2.5 max-h-[calc(100vh-300px)] min-h-[260px] overflow-y-auto p-[3px] pr-2 -m-[3px] [scrollbar-width:thin]"></div>
    <!-- (compat: dating table body — hindi na ginagamit) -->
    <div id="crmListTbody" class="hidden"></div>
</div>

<!-- ═══ DETAIL PANEL (galing sa kanan) ═══ -->
<div id="crmDetailModal" class="fixed inset-0 z-50 hidden">
    <div class="absolute inset-0 bg-black/30" onclick="crmCloseDetailModal()"></div>
    <div id="crmDetailSheet" class="absolute top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl flex flex-col translate-x-full transition-transform duration-300 ease-out">
        <div class="h-1.5 shrink-0 bg-[repeating-linear-gradient(-45deg,#f59e0b_0_10px,#1f2937_10px_20px)]"></div>
        <div class="px-5 py-4 border-b border-gray-100 flex items-start justify-between gap-3 shrink-0">
            <div class="min-w-0">
                <p id="crmDetailControlNo" class="font-mono text-[11px] font-semibold text-amber-700">—</p>
                <h3 id="crmDetailClient" class="text-lg font-semibold text-gray-900 truncate capitalize"></h3>
                <div id="crmDetailStatusBadge" class="mt-1.5 flex flex-wrap items-center gap-1.5"></div>
            </div>
            <button type="button" onclick="crmCloseDetailModal()" aria-label="Close"
                class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 shrink-0"><i class="fa-solid fa-xmark text-base leading-none"></i></button>
        </div>
        <div id="crmDetailBody" class="px-5 py-4 overflow-y-auto flex-1 bg-slate-50/60 [scrollbar-width:thin]"></div>
        <div class="px-5 py-4 bg-white border-t border-gray-100 shrink-0 grid grid-cols-2 gap-2">
            <button type="button" onclick="crmCloseDetailModal()"
                class="px-3.5 py-2.5 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">Close</button>
            <button type="button" id="crmDetailAssignBtn" onclick="crmOpenAssignModal(crmDetailCurrentId)"
                class="hidden inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 shadow-sm">
                <i class="fa-solid fa-calendar-plus"></i> Schedule &amp; Assign
            </button>
            <button type="button" id="crmDetail2dBtn" onclick="crm2dQuotationFromModal()" disabled
                class="hidden inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 text-xs font-semibold text-white bg-blue-700 rounded-lg hover:bg-blue-800 disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed">
                <i class="fa-solid fa-file-pen"></i> 2D &amp; Quotation
            </button>
        </div>
    </div>
</div>

<!-- ═══ SCHEDULE & ASSIGN MODAL ═══ -->
<div id="crmAssignModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-[60] px-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden max-h-[92vh] flex flex-col">
        <div class="h-1.5 shrink-0 bg-[repeating-linear-gradient(-45deg,#f59e0b_0_10px,#1f2937_10px_20px)]"></div>
        <div class="px-5 py-4 border-b border-gray-100 flex items-start justify-between gap-3 shrink-0">
            <div class="flex items-center gap-2.5 min-w-0">
                <span class="w-9 h-9 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-calendar-plus"></i></span>
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold text-gray-900">Schedule &amp; Assign Designer</h3>
                    <p id="crmAssignSubtitle" class="text-[11px] text-gray-400 truncate">Call the client first to confirm their available date.</p>
                </div>
            </div>
            <button type="button" onclick="crmCloseAssignModal()" aria-label="Close"
                class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 shrink-0"><i class="fa-solid fa-xmark text-base leading-none"></i></button>
        </div>
        <div class="p-5 space-y-4 overflow-y-auto">
            <div>
                <p class="text-[11.5px] font-semibold text-gray-700 mb-1.5">1. Client status <span class="text-red-500">*</span></p>
                <div id="crmAssignClientStatusGroup" class="grid grid-cols-3 gap-2">
                    <?php foreach ([
                        ['confirmed', 'Confirmed', 'fa-circle-check', 'Book the visit'],
                        ['tentative', 'Tentative', 'fa-hourglass-half', 'Not decided'],
                        ['no', 'Not Proceeding', 'fa-ban', 'Client declined'],
                    ] as [$v, $l, $ic, $sub]): ?>
                        <button type="button" data-value="<?= $v ?>"
                            class="crm-client-status-btn flex flex-col items-center gap-1 px-2 py-2.5 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors">
                            <i class="fa-solid <?= $ic ?>"></i>
                            <span class="text-[11.5px] font-semibold leading-tight text-center"><?= $l ?></span>
                            <span class="text-[10px] opacity-70 leading-tight"><?= $sub ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
                <p id="crmAssignPauseNote" class="hidden mt-2 text-[11.5px] text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2"></p>
            </div>

            <div id="crmAssignScheduleFields" class="space-y-4">
                <div>
                    <label for="crmAssignDatetime" class="block text-[11.5px] font-semibold text-gray-700 mb-1.5">2. Measurement date &amp; time <span class="text-red-500">*</span></label>
                    <input type="datetime-local" id="crmAssignDatetime"
                        class="w-full h-10 px-3 text-sm text-gray-800 bg-white border border-gray-300 rounded-lg outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-100">
                    <p id="crmAssignDateHint" class="text-[11px] text-gray-400 mt-1"></p>
                </div>
                <div>
                    <label for="crmAssignDesigner" class="block text-[11.5px] font-semibold text-gray-700 mb-1.5">3. Assign designer <span class="text-red-500">*</span></label>
                    <select id="crmAssignDesigner"
                        class="w-full h-10 px-3 text-sm text-gray-800 bg-white border border-gray-300 rounded-lg outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-100">
                        <option value="">Loading designers…</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="px-5 py-4 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-2 shrink-0">
            <button type="button" onclick="crmCloseAssignModal()"
                class="px-3.5 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
            <button type="button" id="crmAssignSaveBtn" onclick="crmSubmitAssign()"
                class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 shadow-sm disabled:opacity-50">
                <i class="fa-solid fa-paper-plane"></i> <span>Submit site visit request</span>
            </button>
        </div>
    </div>
</div>

<!-- Toast container -->
<div id="crmToastContainer"
    class="fixed bottom-6 right-6 z-[9999] flex flex-col gap-2.5 pointer-events-none w-full max-w-sm px-4 sm:px-0">
</div>

<script>
    function crmShowToast(message, type = 'success', duration = 4000) {
        const container = document.getElementById('crmToastContainer');
        const palette = type === 'success'
            ? { icon: 'bg-emerald-500', symbol: '✓' }
            : { icon: 'bg-red-600', symbol: '!' };

        const toast = document.createElement('div');
        toast.className = `pointer-events-auto flex items-start gap-2.5 bg-white border border-gray-200 rounded-xl shadow-lg px-4 py-3 text-sm text-gray-800
            translate-x-6 opacity-0 scale-95 transition-all duration-300 ease-out`;
        toast.innerHTML = `
            <span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold text-white ${palette.icon}">${palette.symbol}</span>
            <span class="flex-1 leading-relaxed">${message}</span>
            <button type="button" class="shrink-0 inline-flex items-center justify-center w-5 h-5 text-gray-400 hover:text-gray-600" aria-label="Close"><i class="fa-solid fa-xmark text-xs"></i></button>
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

    const CRM_LIST_AJAX_URL = <?= json_encode($crmListAjaxUrl) ?>;
    const CRM_2D_QUOTATION_URL = <?= json_encode($crm2dQuotationUrl) ?>;
    const CRM_POLL_INTERVAL_MS = 8000;

    let crmListSearchTerm = '';
    let crmListLastSignature = '';
    let crmListPollTimer = null;
    let crmListSearchDebounce = null;
    let crmDetailCurrentId = null;
    let crmListRawRows = [];
    let crmSortKey = 'created_at';
    let crmSortDir = 'desc'; // 'asc' | 'desc'

    // ═══════════════════════════════════════════════════════════
    // STAGE (kung nasaan na ang inquiry)
    // ═══════════════════════════════════════════════════════════
    function crmStageOf(r) {
        if (r.client_status === 'no') return 'stopped';
        if (!r.designer_id) return 'schedule';
        if (r.status === 'In Progress') return 'quotation';
        return 'sitevisit';
    }
    const CRM_STAGE_META = {
        schedule: { label: 'To schedule', bar: 'bg-amber-400', chip: 'bg-amber-50 text-amber-700 border-amber-200', icon: 'fa-calendar-plus' },
        sitevisit: { label: 'For site visit', bar: 'bg-orange-500', chip: 'bg-orange-50 text-orange-700 border-orange-200', icon: 'fa-ruler-combined' },
        quotation: { label: '2D & Quotation', bar: 'bg-blue-500', chip: 'bg-blue-50 text-blue-700 border-blue-200', icon: 'fa-file-pen' },
        stopped: { label: 'Not proceeding', bar: 'bg-red-400', chip: 'bg-red-50 text-red-700 border-red-200', icon: 'fa-ban' },
    };

    // ═══════════════════════════════════════════════════════════
    // FILTERS (client-side)
    // ═══════════════════════════════════════════════════════════
    const crmFilters = { status: '', designer: '', project: '', amount: '', from: '', to: '' };
    let crmStageFilter = '';

    function crmFiltersActive() {
        return Object.values(crmFilters).some(v => v !== '');
    }

    function crmFilterSig() {
        return JSON.stringify(crmFilters) + crmStageFilter;
    }

    function crmFilteredRows(rows, ignoreStage = false) {
        return rows.filter(r => {
            if (!ignoreStage && crmStageFilter && crmStageOf(r) !== crmStageFilter) return false;
            if (crmFilters.status && r.status !== crmFilters.status) return false;

            if (crmFilters.designer === '__none__') {
                if (r.designer_id) return false;
            } else if (crmFilters.designer && String(r.designer_id) !== crmFilters.designer) {
                return false;
            }

            if (crmFilters.project && (r.project_type || '') !== crmFilters.project) return false;

            const hasAmount = Number(r.contract_amount) > 0;
            if (crmFilters.amount === 'with' && !hasAmount) return false;
            if (crmFilters.amount === 'without' && hasAmount) return false;

            const day = (r.created_at || '').slice(0, 10); // YYYY-MM-DD
            if (crmFilters.from && (!day || day < crmFilters.from)) return false;
            if (crmFilters.to && (!day || day > crmFilters.to)) return false;

            return true;
        });
    }

    // Buuin ang options mula sa data. Hindi ire-rebuild kung walang nagbago,
    // para hindi magsara ang dropdown habang nagpo-polling.
    function crmFillSelect(id, firstLabel, items, extra = []) {
        const sel = document.getElementById(id);
        const sig = JSON.stringify([extra, items]);
        if (sel.dataset.sig === sig) return;
        sel.dataset.sig = sig;

        const current = sel.value;
        sel.innerHTML = '';
        sel.add(new Option(firstLabel, ''));
        extra.forEach(([v, t]) => sel.add(new Option(t, v)));
        items.forEach(([v, t]) => sel.add(new Option(t, v)));

        const stillExists = [...sel.options].some(o => o.value === current);
        sel.value = stillExists ? current : '';
    }

    function crmPopulateFilterOptions(rows) {
        const uniq = arr => [...new Set(arr.filter(Boolean))].sort((a, b) => a.localeCompare(b));

        crmFillSelect('crmFilterStatus', 'All status',
            uniq(rows.map(r => r.status)).map(s => [s, s]));

        crmFillSelect('crmFilterProject', 'All projects',
            uniq(rows.map(r => r.project_type)).map(p => [p, p]));

        const designers = new Map();
        rows.forEach(r => {
            if (r.designer_id) designers.set(String(r.designer_id), r.designer_name || ('#' + r.designer_id));
        });
        crmFillSelect('crmFilterDesigner', 'All designers',
            [...designers.entries()].sort((a, b) => a[1].localeCompare(b[1])),
            [['__none__', 'Unassigned']]);

        crmFilters.status = document.getElementById('crmFilterStatus').value;
        crmFilters.project = document.getElementById('crmFilterProject').value;
        crmFilters.designer = document.getElementById('crmFilterDesigner').value;
    }

    function crmUpdateFilterBadge() {
        const n = Object.values(crmFilters).filter(v => v !== '').length;
        const badge = document.getElementById('crmFilterCountBadge');
        badge.textContent = n;
        badge.classList.toggle('hidden', !n);
        badge.classList.toggle('inline-flex', !!n);
        document.getElementById('crmFilterClear').classList.toggle('hidden', !n);
    }

    function crmApplyFilters() {
        crmFilters.status = document.getElementById('crmFilterStatus').value;
        crmFilters.designer = document.getElementById('crmFilterDesigner').value;
        crmFilters.project = document.getElementById('crmFilterProject').value;
        crmFilters.amount = document.getElementById('crmFilterAmount').value;
        crmFilters.from = document.getElementById('crmFilterFrom').value;
        crmFilters.to = document.getElementById('crmFilterTo').value;

        crmUpdateFilterBadge();
        crmListLastSignature = '';
        crmRenderRows(crmListRawRows);
    }

    function crmToggleFilters() {
        const p = document.getElementById('crmFilterPanel');
        p.classList.toggle('hidden');
        try { sessionStorage.setItem('crmListFiltersOpen', p.classList.contains('hidden') ? '0' : '1'); } catch (e) { }
    }
    try { if (sessionStorage.getItem('crmListFiltersOpen') === '1') document.getElementById('crmFilterPanel').classList.remove('hidden'); } catch (e) { }

    document.querySelectorAll('.crm-filter').forEach(el => {
        el.addEventListener('change', crmApplyFilters);
    });

    document.getElementById('crmFilterClear').addEventListener('click', () => {
        document.querySelectorAll('.crm-filter').forEach(el => { el.value = ''; });
        crmApplyFilters();
    });

    // Stage tabs
    function crmRenderStageTabs() {
        document.querySelectorAll('.crm-stage-tab').forEach(b => {
            const on = b.dataset.stage === crmStageFilter;
            b.classList.toggle('border-amber-600', on);
            b.classList.toggle('bg-amber-50', on);
            b.classList.toggle('ring-1', on);
            b.classList.toggle('ring-amber-600', on);
            b.classList.toggle('border-gray-200', !on);
            b.classList.toggle('bg-white', !on);
            b.classList.toggle('hover:bg-gray-50', !on);
        });
    }
    document.querySelectorAll('.crm-stage-tab').forEach(b => b.addEventListener('click', () => {
        crmStageFilter = b.dataset.stage;
        try { sessionStorage.setItem('crmListStage', crmStageFilter); } catch (e) { }
        crmRenderStageTabs();
        crmListLastSignature = '';
        crmRenderRows(crmListRawRows);
    }));
    try { crmStageFilter = sessionStorage.getItem('crmListStage') || ''; } catch (e) { }
    crmRenderStageTabs();

    function crmUpdateCount(shown, total) {
        const label = total === 1 ? 'inquiry' : 'inquiries';
        document.getElementById('crmListCount').textContent =
            (crmFiltersActive() || crmStageFilter) ? `Showing ${shown} of ${total} ${label}` : `${total} ${label}`;
    }

    // ═══════════════════════════════════════════════════════════
    // HELPERS
    // ═══════════════════════════════════════════════════════════
    function crmEscapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }

    function crmFormatCurrency(value, compact = false) {
        const num = Number(value);
        if (!value || isNaN(num)) return '—';
        if (compact) {
            return '₱' + num.toLocaleString('en-PH', { notation: 'compact', maximumFractionDigits: 1 });
        }
        return '₱' + num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function crmFormatDate(value) {
        if (!value) return '—';
        const dt = new Date(String(value).replace(' ', 'T'));
        if (isNaN(dt.getTime())) return value;
        return dt.toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function crmFormatDateTimeLong(value) {
        if (!value) return '—';
        const dt = new Date(String(value).replace(' ', 'T'));
        if (isNaN(dt.getTime())) return value;
        return dt.toLocaleString('en-PH', {
            year: 'numeric', month: 'long', day: 'numeric',
            hour: 'numeric', minute: '2-digit', hour12: true
        });
    }

    function crmFormatDateTimeShort(value) {
        if (!value) return '—';
        const dt = new Date(String(value).replace(' ', 'T'));
        if (isNaN(dt.getTime())) return value;
        return dt.toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true });
    }

    // "Today", "Tomorrow", "in 3 days", "2 days ago"
    function crmRelDay(value) {
        if (!value) return null;
        const dt = new Date(String(value).replace(' ', 'T'));
        if (isNaN(dt.getTime())) return null;
        const a = new Date(); a.setHours(0, 0, 0, 0);
        const b = new Date(dt); b.setHours(0, 0, 0, 0);
        const d = Math.round((b - a) / 864e5);
        if (d === 0) return { text: 'Today', cls: 'text-amber-700' };
        if (d === 1) return { text: 'Tomorrow', cls: 'text-amber-700' };
        if (d > 1) return { text: `in ${d} days`, cls: 'text-gray-500' };
        if (d === -1) return { text: 'Yesterday', cls: 'text-gray-500' };
        return { text: `${-d} days ago`, cls: 'text-gray-500' };
    }

    function crmInitials(name) {
        if (!name) return '?';
        const parts = name.trim().split(/\s+/).filter(Boolean);
        const initials = (parts[0]?.[0] || '') + (parts.length > 1 ? parts[parts.length - 1][0] : '');
        return initials.toUpperCase() || '?';
    }

    function crmAvatarColor(name) {
        const palette = ['bg-amber-100 text-amber-700', 'bg-blue-100 text-blue-700', 'bg-emerald-100 text-emerald-700',
            'bg-rose-100 text-rose-700', 'bg-violet-100 text-violet-700', 'bg-cyan-100 text-cyan-700'];
        let hash = 0;
        for (const ch of (name || '')) hash = (hash * 31 + ch.charCodeAt(0)) % palette.length;
        return palette[Math.abs(hash) % palette.length];
    }

    function crmClientStatusBadge(status) {
        const map = {
            confirmed: { label: 'Confirmed', cls: 'bg-emerald-50 text-emerald-700 border-emerald-200', dot: 'bg-emerald-500' },
            tentative: { label: 'Tentative', cls: 'bg-amber-50 text-amber-700 border-amber-200', dot: 'bg-amber-500' },
            no: { label: 'Not Proceeding', cls: 'bg-red-50 text-red-700 border-red-200', dot: 'bg-red-500' },
        };
        const s = map[status];
        if (!s) return '—';
        return `<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10.5px] font-semibold border whitespace-nowrap ${s.cls}">
                <span class="w-1.5 h-1.5 rounded-full shrink-0 ${s.dot}"></span>${s.label}
            </span>`;
    }

    function crmStatusBadge(status) {
        const isDone = status === 'In Progress';
        const cls = isDone ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-amber-50 text-amber-700 border-amber-200';
        const dot = isDone ? 'bg-blue-500' : 'bg-amber-500';
        return `<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10.5px] font-semibold border whitespace-nowrap ${cls}">
                    <span class="w-1.5 h-1.5 rounded-full shrink-0 ${dot}"></span>${crmEscapeHtml(status)}
                </span>`;
    }

    function crmStageChip(r) {
        const m = CRM_STAGE_META[crmStageOf(r)];
        return `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-semibold border whitespace-nowrap ${m.chip}"><i class="fa-solid ${m.icon} text-[9px]"></i>${m.label}</span>`;
    }

    // Kung wala pang designer, "Schedule & Assign" muna ang action.
    function crmActionButton(row, size = 'normal') {
        const sizeCls = size === 'small' ? 'px-3 py-2 text-xs w-full justify-center' : 'px-3.5 py-2 text-xs whitespace-nowrap';

        if (!row.designer_id) {
            return `<button type="button" onclick="crmOpenAssignModal(${row.id})"
                        class="${sizeCls} inline-flex items-center gap-1.5 font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 shadow-sm">
                        <i class="fa-solid fa-calendar-plus"></i> ${row.client_status === 'tentative' || row.client_status === 'no' ? 'Update status' : 'Schedule &amp; Assign'}
                   </button>`;
        }

        const isDone = row.status === 'In Progress';
        return isDone
            ? `<button type="button" onclick="crm2dQuotation(${row.id})"
                    class="${sizeCls} inline-flex items-center gap-1.5 font-semibold text-white bg-blue-700 rounded-lg hover:bg-blue-800 shadow-sm">
                    <i class="fa-solid fa-file-pen"></i> 2D &amp; Quotation
               </button>`
            : `<button type="button" disabled title="Waiting for the designer's site visit"
                    class="${sizeCls} inline-flex items-center gap-1.5 font-semibold text-gray-400 bg-gray-100 border border-gray-200 rounded-lg cursor-not-allowed">
                    <i class="fa-solid fa-hourglass-half"></i> Waiting site visit
               </button>`;
    }

    function crmSkeletonRows(count = 5) {
        document.getElementById('crmListCards').innerHTML = Array.from({ length: count }).map(() =>
            `<div class="h-[88px] rounded-xl bg-white border border-gray-200 animate-pulse"></div>`).join('');
    }

    function crmEmptyState() {
        let message = 'No inquiries yet.';
        if (crmListSearchTerm && (crmFiltersActive() || crmStageFilter)) message = `No inquiries match "${crmEscapeHtml(crmListSearchTerm)}" with the selected filters.`;
        else if (crmListSearchTerm) message = `No inquiries match "${crmEscapeHtml(crmListSearchTerm)}".`;
        else if (crmFiltersActive() || crmStageFilter) message = 'No inquiries match the selected filters.';
        return `<div class="bg-white border-2 border-dashed border-gray-200 rounded-xl px-6 py-12 text-center">
            <div class="w-12 h-12 mx-auto rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center mb-2"><i class="fa-regular fa-folder-open"></i></div>
            <p class="text-sm font-semibold text-gray-700">${message}</p></div>`;
    }

    function crmSortedRows(rows) {
        return [...rows].sort((a, b) => {
            let av = a[crmSortKey], bv = b[crmSortKey];
            if (crmSortKey === 'contract_amount') { av = Number(av) || 0; bv = Number(bv) || 0; }
            else if (crmSortKey === 'created_at' || crmSortKey === 'measurement_datetime') {
                // walang petsa → laging nasa dulo
                const big = crmSortDir === 'asc' ? Infinity : -Infinity;
                av = new Date(String(av || '').replace(' ', 'T')).getTime() || big;
                bv = new Date(String(bv || '').replace(' ', 'T')).getTime() || big;
            }
            else { av = (av || '').toString().toLowerCase(); bv = (bv || '').toString().toLowerCase(); }
            if (av < bv) return crmSortDir === 'asc' ? -1 : 1;
            if (av > bv) return crmSortDir === 'asc' ? 1 : -1;
            return 0;
        });
    }

    // (compat — wala nang sort headers; dropdown na ang gamit)
    function crmUpdateSortArrows() {
        const sel = document.getElementById('crmSortSelect');
        if (sel) sel.value = `${crmSortKey}:${crmSortDir}`;
    }

    // ═══════════════════════════════════════════════════════════
    // RENDER + FETCH
    // ═══════════════════════════════════════════════════════════
    function crmRenderStageCounts(allRows) {
        const base = crmFilteredRows(allRows, true);
        const counts = { '': base.length, schedule: 0, sitevisit: 0, quotation: 0, stopped: 0 };
        base.forEach(r => counts[crmStageOf(r)]++);
        document.querySelectorAll('[data-stage-count]').forEach(el => el.textContent = counts[el.dataset.stageCount] ?? 0);
    }

    function crmRowCard(row) {
        const stage = crmStageOf(row);
        const m = CRM_STAGE_META[stage];
        const isActive = crmDetailCurrentId === row.id;

        let schedule;
        if (row.measurement_datetime) {
            const rel = stage === 'sitevisit' ? crmRelDay(row.measurement_datetime) : null;
            schedule = `<dd class="text-gray-800 font-medium whitespace-nowrap">${crmEscapeHtml(crmFormatDateTimeShort(row.measurement_datetime))}</dd>
                        ${rel ? `<dd class="text-[10.5px] font-semibold ${rel.cls}">${rel.text}</dd>` : ''}`;
        } else {
            schedule = `<dd class="text-gray-400 whitespace-nowrap">${row.client_status === 'tentative' ? 'Tentative' : 'Not set'}</dd>`;
        }

        return `
        <article class="relative bg-white border rounded-xl overflow-hidden cursor-pointer transition ${isActive ? 'border-amber-500 ring-2 ring-amber-100' : 'border-gray-200 hover:border-gray-300 hover:shadow-sm'}"
            onclick="crmOpenDetailModal(${row.id})">
            <span class="absolute left-0 top-0 bottom-0 w-1 ${m.bar}"></span>
            <div class="flex flex-wrap items-center gap-x-5 gap-y-3 pl-5 pr-4 py-3.5">
                <div class="flex items-center gap-3 min-w-0 flex-1 basis-[250px]">
                    <span class="shrink-0 inline-flex items-center justify-center w-10 h-10 rounded-full text-xs font-bold ${crmAvatarColor(row.client_name)}">${crmEscapeHtml(crmInitials(row.client_name))}</span>
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="font-mono text-[11px] font-semibold text-amber-700">${crmEscapeHtml(row.control_no)}</span>
                            ${crmStageChip(row)}
                        </div>
                        <p class="text-sm font-semibold text-gray-900 truncate capitalize mt-0.5">${crmEscapeHtml(row.client_name)}</p>
                        <p class="text-[11px] text-gray-500 truncate">
                            <i class="fa-solid fa-phone text-gray-300 mr-1"></i>${crmEscapeHtml(row.contact_number) || '—'}
                            <span class="text-gray-300 mx-1.5">·</span><span class="capitalize">${crmEscapeHtml(row.project_type) || '—'}</span>
                        </p>
                    </div>
                </div>
                <dl class="grid grid-cols-3 gap-x-5 text-[11px] basis-[360px] grow sm:grow-0">
                    <div class="min-w-0">
                        <dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Designer</dt>
                        <dd class="font-medium truncate ${row.designer_id ? 'text-gray-800' : 'text-gray-400'}">${row.designer_id ? crmEscapeHtml(row.designer_name) : 'Unassigned'}</dd>
                    </div>
                    <div class="min-w-0">
                        <dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Site visit</dt>
                        ${schedule}
                    </div>
                    <div class="min-w-0">
                        <dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Contract</dt>
                        <dd class="font-semibold tabular-nums whitespace-nowrap ${Number(row.contract_amount) > 0 ? 'text-gray-900' : 'text-gray-400'}">${crmFormatCurrency(row.contract_amount)}</dd>
                        <dd class="text-[10.5px] text-gray-400 whitespace-nowrap">Filed ${crmFormatDate(row.created_at)}</dd>
                    </div>
                </dl>
                <div class="ml-auto shrink-0" onclick="event.stopPropagation()">${crmActionButton(row)}</div>
            </div>
        </article>`;
    }

    function crmRenderRows(allRows) {
        const cardsWrap = document.getElementById('crmListCards');

        crmRenderStageCounts(allRows);
        const rows = crmFilteredRows(allRows);
        crmUpdateCount(rows.length, allRows.length);

        if (rows.length === 0) {
            cardsWrap.innerHTML = crmEmptyState();
            return;
        }

        cardsWrap.innerHTML = crmSortedRows(rows).map(crmRowCard).join('');
    }

    async function crmFetchList({ silent = false } = {}) {
        if (!silent && crmListRawRows.length === 0) crmSkeletonRows();
        try {
            const url = `${CRM_LIST_AJAX_URL}?action=list&q=${encodeURIComponent(crmListSearchTerm)}`;
            const res = await fetch(url, { cache: 'no-store' });
            const data = await res.json();

            if (!data.success) {
                if (!silent) crmShowToast('Failed to load inquiries.', 'error');
                return;
            }

            crmListRawRows = data.rows;
            crmPopulateFilterOptions(data.rows);

            const signature = JSON.stringify(data.rows.map(r => r.id + ':' + r.status + ':' + r.designer_id + ':' + r.client_status + ':' + r.measurement_datetime + ':' + r.contract_amount))
                + crmSortKey + crmSortDir + crmFilterSig();
            if (signature !== crmListLastSignature) {
                crmRenderRows(data.rows);
                crmListLastSignature = signature;
            }

        } catch (e) {
            console.error('crmFetchList:', e);
            if (!silent) {
                document.getElementById('crmListCards').innerHTML = `<div class="bg-white border border-red-200 rounded-xl px-6 py-10 text-center text-xs text-red-600">Could not load inquiries. <button type="button" onclick="crmFetchList()" class="font-semibold underline">Retry</button></div>`;
            }
        }
    }

    function crmStartPolling() {
        if (crmListPollTimer) clearInterval(crmListPollTimer);
        crmListPollTimer = setInterval(() => crmFetchList({ silent: true }), CRM_POLL_INTERVAL_MS);
    }

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            if (crmListPollTimer) clearInterval(crmListPollTimer);
        } else {
            crmFetchList({ silent: true });
            crmStartPolling();
        }
    });

    // ═══════════════════════════════════════════════════════════
    // SEARCH + SORT
    // ═══════════════════════════════════════════════════════════
    const crmSearchInput = document.getElementById('crmListSearch');
    const crmSearchClear = document.getElementById('crmListSearchClear');

    crmSearchInput.addEventListener('input', function () {
        crmSearchClear.classList.toggle('hidden', this.value.length === 0);
        clearTimeout(crmListSearchDebounce);
        const value = this.value;
        crmListSearchDebounce = setTimeout(() => {
            crmListSearchTerm = value.trim();
            crmListLastSignature = '';
            crmFetchList();
        }, 350);
    });

    crmSearchClear.addEventListener('click', () => {
        crmSearchInput.value = '';
        crmSearchClear.classList.add('hidden');
        crmListSearchTerm = '';
        crmListLastSignature = '';
        crmFetchList();
        crmSearchInput.focus();
    });

    document.getElementById('crmSortSelect').addEventListener('change', function () {
        [crmSortKey, crmSortDir] = this.value.split(':');
        try { sessionStorage.setItem('crmListSort', this.value); } catch (e) { }
        crmListLastSignature = '';
        crmRenderRows(crmListRawRows);
    });
    try {
        const s = sessionStorage.getItem('crmListSort');
        if (s && s.includes(':')) [crmSortKey, crmSortDir] = s.split(':');
    } catch (e) { }

    crmUpdateSortArrows();
    crmFetchList().then(crmStartPolling);

    // ═══════════════════════════════════════════════════════════
    // 2D & QUOTATION ACTION
    // ═══════════════════════════════════════════════════════════
    function crm2dQuotation(id) {
        window.location.href = `${CRM_2D_QUOTATION_URL}?id=${id}`;
    }

    function crm2dQuotationFromModal() {
        if (crmDetailCurrentId) crm2dQuotation(crmDetailCurrentId);
    }

    // ═══════════════════════════════════════════════════════════
    // DETAIL PANEL
    // ═══════════════════════════════════════════════════════════
    function crmDetailRow(label, value) {
        return `
            <div class="flex justify-between gap-3 py-2 border-b border-gray-100 text-[12.5px] last:border-b-0">
                <span class="text-gray-400 whitespace-nowrap">${label}</span>
                <span class="text-gray-800 font-medium text-right min-w-0 break-words">${value}</span>
            </div>
        `;
    }
    function crmDetailGroup(title, icon, rowsHtml) {
        return `<section class="bg-white border border-gray-200 rounded-xl overflow-hidden">
            <p class="px-4 py-2.5 border-b border-gray-100 text-[10.5px] font-bold uppercase tracking-wider text-gray-500"><i class="fa-solid ${icon} mr-1 text-amber-700"></i>${title}</p>
            <div class="px-4 py-1">${rowsHtml}</div></section>`;
    }

    async function crmOpenDetailModal(id) {
        const modal = document.getElementById('crmDetailModal');
        const sheet = document.getElementById('crmDetailSheet');
        const body = document.getElementById('crmDetailBody');
        const quotationBtn = document.getElementById('crmDetail2dBtn');
        const assignBtn = document.getElementById('crmDetailAssignBtn');
        crmDetailCurrentId = id;
        crmRenderRows(crmListRawRows);

        document.getElementById('crmDetailControlNo').textContent = 'Loading…';
        document.getElementById('crmDetailClient').textContent = '';
        document.getElementById('crmDetailStatusBadge').innerHTML = '';
        quotationBtn.classList.add('hidden');
        assignBtn.classList.add('hidden');
        quotationBtn.disabled = true;
        body.innerHTML = `<div class="space-y-3">${Array.from({ length: 4 }).map(() => `<div class="h-16 rounded-xl bg-gray-100 animate-pulse"></div>`).join('')}</div>`;
        modal.classList.remove('hidden');
        requestAnimationFrame(() => sheet.classList.remove('translate-x-full'));

        try {
            const res = await fetch(`${CRM_LIST_AJAX_URL}?action=detail&id=${id}`, { cache: 'no-store' });
            const data = await res.json();
            if (crmDetailCurrentId !== id) return;

            if (!data.success) {
                body.innerHTML = `<p class="text-sm text-red-500 py-6 text-center">${crmEscapeHtml(data.message || 'Record not found.')}</p>`;
                return;
            }

            const r = data.record;
            const isDone = r.status === 'In Progress';
            const stage = crmStageOf(r);

            document.getElementById('crmDetailControlNo').textContent = r.control_no;
            document.getElementById('crmDetailClient').textContent = r.client_name || '';
            document.getElementById('crmDetailStatusBadge').innerHTML = crmStageChip(r) + crmStatusBadge(r.status) + (r.client_status ? crmClientStatusBadge(r.client_status) : '');

            if (!r.designer_id) {
                assignBtn.classList.remove('hidden');
                assignBtn.classList.add('inline-flex');
                assignBtn.innerHTML = `<i class="fa-solid fa-calendar-plus"></i> ${r.client_status === 'tentative' || r.client_status === 'no' ? 'Update status' : 'Schedule &amp; Assign'}`;
                quotationBtn.classList.add('hidden');
            } else {
                assignBtn.classList.add('hidden');
                quotationBtn.classList.remove('hidden');
                quotationBtn.classList.add('inline-flex');
                quotationBtn.disabled = !isDone;
            }

            const next = {
                schedule: ['bg-amber-50 border-amber-200 text-amber-900', 'fa-calendar-plus', 'Next: Schedule the site visit', 'Call the client, then set the measurement date and assign a designer.'],
                sitevisit: ['bg-orange-50 border-orange-200 text-orange-900', 'fa-ruler-combined', 'Waiting for the site visit', `${crmEscapeHtml(r.designer_name)} will visit on ${crmEscapeHtml(crmFormatDateTimeLong(r.measurement_datetime))}.`],
                quotation: ['bg-blue-50 border-blue-200 text-blue-900', 'fa-file-pen', '2D & Quotation in progress', 'Open 2D & Quotation to follow the designer\'s files and set the contract.'],
                stopped: ['bg-red-50 border-red-200 text-red-900', 'fa-ban', 'Client is not proceeding', 'You can update the client status if they change their mind.'],
            }[stage];

            body.innerHTML = `<div class="space-y-3">
                <div class="rounded-xl border px-4 py-3 flex items-start gap-3 ${next[0]}">
                    <span class="w-9 h-9 rounded-lg bg-white/70 flex items-center justify-center shrink-0"><i class="fa-solid ${next[1]}"></i></span>
                    <div class="min-w-0"><p class="text-sm font-semibold">${next[2]}</p><p class="text-xs opacity-80 mt-0.5">${next[3]}</p></div>
                </div>
                ${crmDetailGroup('Client', 'fa-user', [
                    crmDetailRow('Name', `<span class="capitalize">${crmEscapeHtml(r.client_name)}</span>`),
                    crmDetailRow('Contact', r.contact_number ? `<a href="tel:${crmEscapeHtml(String(r.contact_number).replace(/[^\d+]/g, ''))}" class="text-amber-700 hover:underline">${crmEscapeHtml(r.contact_number)}</a>` : '—'),
                    crmDetailRow('Address', r.address ? `${crmEscapeHtml(r.address)}<a href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(r.address)}" target="_blank" rel="noopener" class="block text-[11px] text-amber-700 hover:underline mt-0.5"><i class="fa-solid fa-map-location-dot mr-1"></i>Open in Maps</a>` : '—'),
                    crmDetailRow('Client status', crmClientStatusBadge(r.client_status)),
                ].join(''))}
                ${crmDetailGroup('Project', 'fa-helmet-safety', [
                    crmDetailRow('Type', `<span class="capitalize">${crmEscapeHtml(r.project_type) || '—'}</span>`),
                    crmDetailRow('Scope', crmEscapeHtml(r.project_scope) || '—'),
                    crmDetailRow('Measuring space', crmEscapeHtml(r.measuring_space) || '—'),
                    crmDetailRow('Contract amount', crmFormatCurrency(r.contract_amount)),
                ].join(''))}
                ${crmDetailGroup('Site visit & assignment', 'fa-calendar-check', [
                    crmDetailRow('Measurement', crmFormatDateTimeLong(r.measurement_datetime)),
                    crmDetailRow('Designer', r.designer_id ? crmEscapeHtml(r.designer_name) : '<span class="text-gray-400">Unassigned</span>'),
                    crmDetailRow('Branch', `<span class="capitalize">${crmEscapeHtml(r.branch) || '—'}</span>`),
                    crmDetailRow('Filed by', crmEscapeHtml(r.sales_name)),
                    crmDetailRow('Date filed', crmFormatDateTimeLong(r.created_at)),
                ].join(''))}
            </div>`;

        } catch (e) {
            console.error('crmOpenDetailModal:', e);
            body.innerHTML = `<p class="text-sm text-red-500 py-6 text-center">Connection error. Please try again.</p>`;
        }
    }

    function crmCloseDetailModal() {
        const modal = document.getElementById('crmDetailModal');
        document.getElementById('crmDetailSheet').classList.add('translate-x-full');
        setTimeout(() => modal.classList.add('hidden'), 300);
        crmDetailCurrentId = null;
        crmRenderRows(crmListRawRows);
    }

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (!document.getElementById('crmAssignModal').classList.contains('hidden')) crmCloseAssignModal();
            else if (crmDetailCurrentId) crmCloseDetailModal();
        }
    });

    // ═══════════════════════════════════════════════════════════
    // SCHEDULE & ASSIGN MODAL
    // ═══════════════════════════════════════════════════════════
    let crmAssignCurrentId = null;
    let crmAssignDesignersLoaded = false;
    let crmAssignClientStatus = null; // 'confirmed' | 'tentative' | 'no' | null

    const crmClientStatusStyles = {
        confirmed: ['bg-emerald-50', 'border-emerald-500', 'text-emerald-700', 'ring-1', 'ring-emerald-500'],
        tentative: ['bg-amber-50', 'border-amber-500', 'text-amber-700', 'ring-1', 'ring-amber-500'],
        no: ['bg-red-50', 'border-red-500', 'text-red-700', 'ring-1', 'ring-red-500'],
    };

    const crmClientStatusPauseNotes = {
        tentative: 'Client hasn’t decided yet — schedule and designer will be set once they confirm.',
        no: 'Client is not proceeding — no schedule or designer needed for now.',
    };

    function crmUpdateClientStatusButtons() {
        const all = Object.values(crmClientStatusStyles).flat();
        document.querySelectorAll('.crm-client-status-btn').forEach(btn => {
            const value = btn.dataset.value;
            btn.classList.remove(...all);
            btn.classList.add('border-gray-200', 'text-gray-600');
            if (value === crmAssignClientStatus) {
                btn.classList.remove('border-gray-200', 'text-gray-600');
                btn.classList.add(...crmClientStatusStyles[value]);
            }
        });

        // Lalabas lang ang schedule fields kapag "Confirmed"
        const showScheduleFields = crmAssignClientStatus === 'confirmed';
        document.getElementById('crmAssignScheduleFields').classList.toggle('hidden', !showScheduleFields);

        const note = document.getElementById('crmAssignPauseNote');
        const isPaused = crmAssignClientStatus === 'tentative' || crmAssignClientStatus === 'no';
        if (isPaused) {
            note.textContent = crmClientStatusPauseNotes[crmAssignClientStatus];
            note.classList.remove('hidden');
        } else {
            note.classList.add('hidden');
        }

        const label = document.querySelector('#crmAssignSaveBtn span');
        if (label) label.textContent = isPaused ? 'Save client status' : 'Submit site visit request';
    }

    document.querySelectorAll('.crm-client-status-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const value = btn.dataset.value;
            crmAssignClientStatus = (crmAssignClientStatus === value) ? null : value;
            crmUpdateClientStatusButtons();
        });
    });

    // Hint sa petsa: "Tomorrow", "in 3 days", o babala kapag lumipas na
    document.getElementById('crmAssignDatetime').addEventListener('input', function () {
        const hint = document.getElementById('crmAssignDateHint');
        if (!this.value) { hint.textContent = ''; return; }
        const dt = new Date(this.value);
        if (dt.getTime() < Date.now()) {
            hint.textContent = 'This date and time has already passed.';
            hint.className = 'text-[11px] text-red-600 font-semibold mt-1';
            return;
        }
        const rel = crmRelDay(this.value.replace('T', ' '));
        hint.textContent = rel ? rel.text : '';
        hint.className = 'text-[11px] text-gray-500 mt-1';
    });

    async function crmOpenAssignModal(id) {
        if (!id) return;
        crmAssignCurrentId = id;
        document.getElementById('crmAssignDatetime').value = '';
        document.getElementById('crmAssignDateHint').textContent = '';
        const row = crmListRawRows.find(r => r.id === id);
        document.getElementById('crmAssignSubtitle').textContent = row
            ? `${row.control_no} · ${row.client_name} · ${row.contact_number || ''}`
            : 'Call the client first to confirm their available date.';
        crmAssignClientStatus = (row && (row.client_status === 'tentative' || row.client_status === 'no')) ? row.client_status : null;
        crmUpdateClientStatusButtons();
        const modal = document.getElementById('crmAssignModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');

        if (!crmAssignDesignersLoaded) {
            const sel = document.getElementById('crmAssignDesigner');
            sel.innerHTML = '<option value="">Loading designers…</option>';
            try {
                const res = await fetch(`${CRM_LIST_AJAX_URL}?action=designers`);
                const data = await res.json();
                sel.innerHTML = '<option value="">Select designer</option>';
                if (data.success) {
                    data.designers.forEach(d => {
                        const opt = document.createElement('option');
                        opt.value = d.id;
                        opt.textContent = d.name;
                        sel.appendChild(opt);
                    });
                    crmAssignDesignersLoaded = true;
                }
            } catch (e) {
                console.error('crmOpenAssignModal designers:', e);
                sel.innerHTML = '<option value="">Failed to load designers</option>';
            }
        }
    }

    function crmCloseAssignModal() {
        document.getElementById('crmAssignModal').classList.add('hidden');
        document.getElementById('crmAssignModal').classList.remove('flex');
        crmAssignCurrentId = null;
    }

    async function crmSubmitAssign() {
        if (!crmAssignClientStatus) {
            crmShowToast('Please select a client status.', 'error');
            return;
        }

        const isPaused = crmAssignClientStatus === 'tentative' || crmAssignClientStatus === 'no';
        const datetime = document.getElementById('crmAssignDatetime').value;
        const designerId = document.getElementById('crmAssignDesigner').value;

        if (!isPaused && (!datetime || !designerId)) {
            crmShowToast('Please set the measurement date/time and select a designer.', 'error');
            return;
        }

        const btn = document.getElementById('crmAssignSaveBtn');
        btn.disabled = true;

        const formData = new FormData();
        formData.append('action', 'assign');
        formData.append('id', crmAssignCurrentId);
        formData.append('client_status', crmAssignClientStatus || '');
        if (!isPaused) {
            formData.append('designer_id', designerId);
            formData.append('measurement_datetime', datetime);
        }

        try {
            const res = await fetch(CRM_LIST_AJAX_URL, { method: 'POST', body: formData });
            const text = await res.text();
            let data;
            try { data = JSON.parse(text.replace(/^﻿/, '').trim()); }
            catch (er) {
                // Hindi JSON ang sagot (PHP error / expired session) → ipakita ang totoong dahilan
                console.error('crmSubmitAssign response:', res.status, text);
                const plain = text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                crmShowToast(crmEscapeHtml(/<!doctype html|<html[\s>]/i.test(text)
                    ? 'The server returned a page instead of data. Reload the page (your session may have expired).'
                    : `Server error (HTTP ${res.status}): ${plain.slice(0, 200) || 'empty response'}`), 'error', 9000);
                return;
            }
            if (data.success) {
                crmShowToast(isPaused ? 'Client status saved.' : 'Schedule saved and designer assigned.', 'success');
                if (data.warning) crmShowToast(crmEscapeHtml(data.warning), 'error', 8000);
                const wasDetailOpen = crmDetailCurrentId === crmAssignCurrentId;
                crmCloseAssignModal();
                crmListLastSignature = '';
                crmFetchList();
                if (wasDetailOpen) crmOpenDetailModal(crmDetailCurrentId);
            } else {
                crmShowToast(crmEscapeHtml(data.message || 'Failed to save.'), 'error', 8000);
            }
        } catch (e) {
            console.error('crmSubmitAssign:', e);
            crmShowToast('Could not reach the server. Check your connection and try again.', 'error');
        } finally {
            btn.disabled = false;
        }
    }

    document.getElementById('crmAssignModal').addEventListener('click', function (e) {
        if (e.target === this) crmCloseAssignModal();
    });
</script>