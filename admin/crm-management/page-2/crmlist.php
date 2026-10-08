<?php
//crmlist.php
$crmListAjaxUrl = BASE_URL . '/crmlistajax';
$crm2dQuotationUrl = BASE_URL . '/crm2dquotation';
?>

<div class="max-w-6xl mx-auto">

    <!-- Header -->
    <div class="mb-4">
        <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase mb-0.5">CRM Management</p>
        <h1 class="text-gray-900 text-xl font-semibold">Sales &amp; Market List</h1>
    </div>

    <!-- Search + Filters (same IDs as before, so your JS still works) -->
    <div class="mb-4 bg-white border border-gray-200 rounded-xl shadow-sm">

        <!-- Row 1: search + clear -->
        <div class="flex flex-col sm:flex-row sm:items-center gap-2 px-4 py-3">
            <div class="relative flex-1 sm:max-w-md">
                <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                    fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z" />
                </svg>
                <input id="crmListSearch" type="text" placeholder="Search control no., client or contact"
                    class="w-full h-9 pl-9 pr-8 text-sm text-gray-800 placeholder-gray-400 bg-gray-50 border border-gray-200 rounded-lg
                       focus:outline-none focus:bg-white focus:ring-2 focus:ring-amber-100 focus:border-amber-600 transition-colors">
                <button type="button" id="crmListSearchClear" aria-label="Clear search" class="hidden absolute right-2 top-1/2 -translate-y-1/2 w-5 h-5 flex items-center justify-center rounded-full
                       text-gray-400 hover:text-gray-600 hover:bg-gray-200 text-base leading-none">&times;</button>
            </div>

            <button type="button" id="crmFilterClear" class="hidden sm:ml-auto inline-flex items-center gap-1.5 h-9 px-3 text-xs font-medium text-amber-700 bg-amber-50
                   border border-amber-200 rounded-lg hover:bg-amber-100 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
                Clear filters
            </button>
        </div>

        <!-- Row 2: filters -->
        <div
            class="grid grid-cols-2 md:grid-cols-4 xl:grid-cols-[repeat(4,minmax(0,1fr))_minmax(0,1.6fr)] gap-3 px-4 py-3 border-t border-gray-100 bg-gray-50/60 rounded-b-xl">

            <label class="block">
                <span class="block mb-1 text-[11px] font-medium text-gray-500">Status</span>
                <div class="relative">
                    <select id="crmFilterStatus"
                        class="crm-filter w-full h-9 pl-3 pr-8 text-sm text-gray-700 bg-white border border-gray-200 rounded-lg appearance-none cursor-pointer
                           hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-amber-100 focus:border-amber-600 transition-colors">
                        <option value="">All status</option>
                    </select>
                    <svg class="absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </label>

            <label class="block">
                <span class="block mb-1 text-[11px] font-medium text-gray-500">Designer</span>
                <div class="relative">
                    <select id="crmFilterDesigner"
                        class="crm-filter w-full h-9 pl-3 pr-8 text-sm text-gray-700 bg-white border border-gray-200 rounded-lg appearance-none cursor-pointer
                           hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-amber-100 focus:border-amber-600 transition-colors">
                        <option value="">All designers</option>
                    </select>
                    <svg class="absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </label>

            <label class="block">
                <span class="block mb-1 text-[11px] font-medium text-gray-500">Project</span>
                <div class="relative">
                    <select id="crmFilterProject"
                        class="crm-filter w-full h-9 pl-3 pr-8 text-sm text-gray-700 bg-white border border-gray-200 rounded-lg appearance-none cursor-pointer
                           hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-amber-100 focus:border-amber-600 transition-colors">
                        <option value="">All projects</option>
                    </select>
                    <svg class="absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </label>

            <label class="block">
                <span class="block mb-1 text-[11px] font-medium text-gray-500">Contract amount</span>
                <div class="relative">
                    <select id="crmFilterAmount"
                        class="crm-filter w-full h-9 pl-3 pr-8 text-sm text-gray-700 bg-white border border-gray-200 rounded-lg appearance-none cursor-pointer
                           hover:border-gray-300 focus:outline-none focus:ring-2 focus:ring-amber-100 focus:border-amber-600 transition-colors">
                        <option value="">Any</option>
                        <option value="with">With amount</option>
                        <option value="without">No amount yet</option>
                    </select>
                    <svg class="absolute right-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none"
                        fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </label>

            <!-- Date range: one joined control instead of two separate boxes -->
            <div class="col-span-2 md:col-span-4 xl:col-span-1">
                <span class="block mb-1 text-[11px] font-medium text-gray-500">Date filed</span>
                <div
                    class="flex items-center h-9 bg-white border border-gray-200 rounded-lg hover:border-gray-300
                        focus-within:ring-2 focus-within:ring-amber-100 focus-within:border-amber-600 transition-colors">
                    <input id="crmFilterFrom" type="date" aria-label="Filed from"
                        class="crm-filter flex-1 min-w-0 h-full px-2.5 text-sm text-gray-700 bg-transparent border-0 rounded-l-lg focus:outline-none">
                    <span class="text-gray-300 text-sm select-none">–</span>
                    <input id="crmFilterTo" type="date" aria-label="Filed to"
                        class="crm-filter flex-1 min-w-0 h-full px-2.5 text-sm text-gray-700 bg-transparent border-0 rounded-r-lg focus:outline-none">
                </div>
            </div>
        </div>
    </div>

    <!-- Table Card (desktop / tablet) -->
    <div class="hidden md:block bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full text-xs">
                <thead>
                    <tr
                        class="bg-gray-50 border-b border-gray-200 text-left text-[10px] uppercase tracking-wide text-gray-500">
                        <th class="px-4 py-2.5 font-semibold select-none whitespace-nowrap">
                            <button type="button" class="crm-sort-th flex items-center gap-1 hover:text-gray-700"
                                data-key="control_no">
                                Control No. <span class="crm-sort-arrow text-gray-300">↕</span>
                            </button>
                        </th>
                        <th class="px-4 py-2.5 font-semibold select-none whitespace-nowrap">
                            <button type="button" class="crm-sort-th flex items-center gap-1 hover:text-gray-700"
                                data-key="client_name">
                                Client <span class="crm-sort-arrow text-gray-300">↕</span>
                            </button>
                        </th>
                        <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Contact No.</th>
                        <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Project</th>
                        <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Designer</th>
                        <th class="px-4 py-2.5 font-semibold text-right select-none whitespace-nowrap">
                            <button type="button"
                                class="crm-sort-th flex items-center gap-1 hover:text-gray-700 ml-auto"
                                data-key="contract_amount">
                                Contract Amount <span class="crm-sort-arrow text-gray-300">↕</span>
                            </button>
                        </th>
                        <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Status</th>
                        <th class="px-4 py-2.5 font-semibold select-none whitespace-nowrap">
                            <button type="button" class="crm-sort-th flex items-center gap-1 hover:text-gray-700"
                                data-key="created_at">
                                Date Filed <span class="crm-sort-arrow text-gray-300">↕</span>
                            </button>
                        </th>
                        <th class="px-4 py-2.5 font-semibold text-right whitespace-nowrap">Action</th>
                    </tr>
                </thead>
                <tbody id="crmListTbody" class="divide-y divide-gray-100">
                    <!-- Populated via JS -->
                </tbody>
            </table>
        </div>
    </div>

    <!-- Card list (mobile) -->
    <div id="crmListCards" class="md:hidden space-y-2.5"></div>

    <p id="crmListCount" class="text-[11px] text-gray-400 mt-2.5"></p>
</div>

<!-- Detail Modal -->
<div id="crmDetailModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-50 px-4">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-lg overflow-hidden max-h-[85vh] flex flex-col">
        <div class="px-5 py-3.5 border-b border-gray-100 flex items-start justify-between">
            <div>
                <p class="text-[10px] text-amber-700 font-semibold tracking-[0.15em] uppercase mb-0.5">Inquiry Detail
                </p>
                <h3 id="crmDetailControlNo" class="text-gray-900 font-mono font-semibold text-sm">—</h3>
            </div>
            <button type="button" onclick="crmCloseDetailModal()"
                class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <div id="crmDetailBody" class="px-5 py-3.5 overflow-y-auto space-y-0.5">
            <!-- Populated via JS -->
        </div>
        <div class="px-5 py-2.5 bg-gray-50 border-t border-gray-100 flex items-center justify-between gap-3">
            <span id="crmDetailStatusBadge"></span>
            <div class="flex items-center gap-2">
                <button type="button" id="crmDetailAssignBtn" onclick="crmOpenAssignModal(crmDetailCurrentId)"
                    class="hidden px-3.5 py-1.5 text-xs font-medium text-white bg-amber-700 rounded-lg hover:bg-amber-600 transition-colors">
                    Schedule &amp; Assign
                </button>
                <button type="button" id="crmDetail2dBtn" onclick="crm2dQuotationFromModal()" disabled
                    class="hidden px-3.5 py-1.5 text-xs font-medium text-white bg-blue-700 rounded-lg hover:bg-blue-800 disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed transition-colors">
                    2D &amp; Quotation
                </button>
                <button type="button" onclick="crmCloseDetailModal()"
                    class="px-3.5 py-1.5 text-xs font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Schedule & Assign Modal -->
<div id="crmAssignModal" class="fixed inset-0 bg-black/40 hidden items-center justify-center z-50 px-4">
    <div class="bg-white rounded-xl shadow-lg w-full max-w-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-800">Schedule &amp; Assign Designer</h3>
            <button type="button" onclick="crmCloseAssignModal()"
                class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
        </div>
        <div class="p-5 space-y-4">
            <p class="text-xs text-gray-400">
                Contact the client first to confirm their available date, then set the schedule and assign a
                designer.
            </p>

            <div>
                <label class="block text-xs font-semibold tracking-wide uppercase text-gray-500 mb-1.5">
                    Client Status <span class="text-gray-400 normal-case font-normal">(Required)</span>
                </label>
                <div id="crmAssignClientStatusGroup" class="flex gap-2">
                    <button type="button"
                        class="crm-client-status-btn flex-1 px-2.5 py-2 text-xs font-medium rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50 transition-colors"
                        data-value="confirmed">
                        Confirmed
                    </button>
                    <button type="button"
                        class="crm-client-status-btn flex-1 px-2.5 py-2 text-xs font-medium rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50 transition-colors"
                        data-value="tentative">
                        Tentative
                    </button>
                    <button type="button"
                        class="crm-client-status-btn flex-1 px-2.5 py-2 text-xs font-medium rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50 transition-colors"
                        data-value="no">
                        Not Proceeding
                    </button>
                </div>
                <p id="crmAssignPauseNote" class="hidden text-[11px] text-amber-600 mt-1.5"></p>
            </div>

            <div id="crmAssignScheduleFields" class="space-y-4">
                <div>
                    <label class="block text-xs font-semibold tracking-wide uppercase text-gray-500 mb-1.5">
                        Measurement Date &amp; Time <span class="text-red-500">*</span>
                    </label>
                    <input type="datetime-local" id="crmAssignDatetime"
                        class="w-full px-3 py-2.5 text-sm text-gray-800 bg-white border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-amber-600 focus:border-amber-600 transition">
                </div>

                <div>
                    <label class="block text-xs font-semibold tracking-wide uppercase text-gray-500 mb-1.5">
                        Designer Assign <span class="text-red-500">*</span>
                    </label>
                    <select id="crmAssignDesigner"
                        class="w-full px-3 py-2.5 text-sm text-gray-800 bg-white border border-gray-300 rounded-md focus:outline-none focus:ring-1 focus:ring-amber-600 focus:border-amber-600 transition">
                        <option value="">Loading designers…</option>
                    </select>
                </div>
            </div>
        </div>
        <div class="px-5 py-3.5 bg-gray-50 border-t border-gray-100 flex items-center justify-end gap-2">
            <button type="button" onclick="crmCloseAssignModal()"
                class="px-3.5 py-1.5 text-xs font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
                Cancel
            </button>
            <button type="button" id="crmAssignSaveBtn" onclick="crmSubmitAssign()"
                class="px-3.5 py-1.5 text-xs font-medium text-white bg-amber-700 rounded-lg hover:bg-amber-600 disabled:opacity-50">
                Submit site visit request
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
    // FILTERS (client-side)
    // ═══════════════════════════════════════════════════════════
    const crmFilters = { status: '', designer: '', project: '', amount: '', from: '', to: '' };

    function crmFiltersActive() {
        return Object.values(crmFilters).some(v => v !== '');
    }

    function crmFilterSig() {
        return JSON.stringify(crmFilters);
    }

    function crmFilteredRows(rows) {
        return rows.filter(r => {
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

        // Kung nawala ang napiling value sa dropdown, i-sync ang state
        crmFilters.status = document.getElementById('crmFilterStatus').value;
        crmFilters.project = document.getElementById('crmFilterProject').value;
        crmFilters.designer = document.getElementById('crmFilterDesigner').value;
    }

    function crmApplyFilters() {
        crmFilters.status = document.getElementById('crmFilterStatus').value;
        crmFilters.designer = document.getElementById('crmFilterDesigner').value;
        crmFilters.project = document.getElementById('crmFilterProject').value;
        crmFilters.amount = document.getElementById('crmFilterAmount').value;
        crmFilters.from = document.getElementById('crmFilterFrom').value;
        crmFilters.to = document.getElementById('crmFilterTo').value;

        document.getElementById('crmFilterClear').classList.toggle('hidden', !crmFiltersActive());
        crmListLastSignature = '';
        crmRenderRows(crmListRawRows);
    }

    document.querySelectorAll('.crm-filter').forEach(el => {
        el.addEventListener('change', crmApplyFilters);
    });

    document.getElementById('crmFilterClear').addEventListener('click', () => {
        document.querySelectorAll('.crm-filter').forEach(el => { el.value = ''; });
        crmApplyFilters();
    });

    function crmUpdateCount(shown, total) {
        const label = total === 1 ? 'inquiry' : 'inquiries';
        document.getElementById('crmListCount').textContent =
            crmFiltersActive() ? `${shown} of ${total} ${label} shown` : `${total} ${label} found`;
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
        const dt = new Date(value.replace(' ', 'T'));
        if (isNaN(dt.getTime())) return value;
        return dt.toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
    }

    function crmFormatDateTimeLong(value) {
        if (!value) return '—';
        const dt = new Date(value.replace(' ', 'T'));
        if (isNaN(dt.getTime())) return value;
        return dt.toLocaleString('en-PH', {
            year: 'numeric', month: 'long', day: 'numeric',
            hour: 'numeric', minute: '2-digit', hour12: true
        });
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
        return `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold border whitespace-nowrap ${s.cls}">
                <span class="w-1.5 h-1.5 rounded-full shrink-0 ${s.dot}"></span>${s.label}
            </span>`;
    }

    function crmStatusBadge(status) {
        const isDone = status === 'In Progress';
        const cls = isDone
            ? 'bg-blue-50 text-blue-700 border-blue-200'
            : 'bg-amber-50 text-amber-700 border-amber-200';
        const dot = isDone ? 'bg-blue-500' : 'bg-amber-500';
        return `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold border whitespace-nowrap ${cls}">
                    <span class="w-1.5 h-1.5 rounded-full shrink-0 ${dot}"></span>${crmEscapeHtml(status)}
                </span>`;
    }

    // Kung wala pang designer, "Schedule & Assign" muna ang action — di pa
    // pwede diretso sa 2D & Quotation hangga't hindi pa naka-set ang schedule
    // at designer. (Tentative/Not Proceeding client_status keeps designer_id
    // null, so this button naturally stays "Schedule & Assign" for them.)
    function crmActionButton(row, size = 'normal') {
        const sizeCls = size === 'small' ? 'px-3 py-1.5 text-xs w-full' : 'px-3 py-1.5 text-xs whitespace-nowrap';

        if (!row.designer_id) {
            return `<button type="button" onclick="crmOpenAssignModal(${row.id})"
                        class="${sizeCls} font-medium text-white bg-amber-700 rounded-lg hover:bg-amber-600 transition-colors">
                        Schedule &amp; Assign
                   </button>`;
        }

        const isDone = row.status === 'In Progress';
        return isDone
            ? `<button type="button" onclick="crm2dQuotation(${row.id})"
                    class="${sizeCls} font-medium text-white bg-blue-700 rounded-lg hover:bg-blue-800 transition-colors">
                    2D &amp; Quotation
               </button>`
            : `<button type="button" disabled title="Complete the site visit first"
                    class="${sizeCls} font-medium text-gray-400 bg-gray-100 border border-gray-200 rounded-lg cursor-not-allowed">
                    2D &amp; Quotation
               </button>`;
    }

    function crmSkeletonRows(count = 5) {
        const tbody = document.getElementById('crmListTbody');
        tbody.innerHTML = Array.from({ length: count }).map(() => `
            <tr>
                ${Array.from({ length: 9 }).map(() => `
                    <td class="px-4 py-3"><div class="h-3 rounded bg-gray-100 animate-pulse"></div></td>
                `).join('')}
            </tr>
        `).join('');

        document.getElementById('crmListCards').innerHTML = Array.from({ length: 3 }).map(() => `
            <div class="bg-white border border-gray-200 rounded-xl p-3.5 space-y-2">
                <div class="h-3 w-1/3 rounded bg-gray-100 animate-pulse"></div>
                <div class="h-4 w-2/3 rounded bg-gray-100 animate-pulse"></div>
                <div class="h-3 w-1/2 rounded bg-gray-100 animate-pulse"></div>
            </div>
        `).join('');
    }

    function crmEmptyState() {
        let message = 'No inquiries yet.';
        if (crmListSearchTerm && crmFiltersActive()) message = `No inquiries match "${crmEscapeHtml(crmListSearchTerm)}" with the selected filters.`;
        else if (crmListSearchTerm) message = `No inquiries match "${crmEscapeHtml(crmListSearchTerm)}".`;
        else if (crmFiltersActive()) message = 'No inquiries match the selected filters.';
        return `
            <div class="flex flex-col items-center justify-center gap-2 py-10 text-center">
                <svg class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 17.25v-6.75A2.25 2.25 0 0111.25 8.25h1.5A2.25 2.25 0 0115 10.5v6.75m-9 0h12a1.5 1.5 0 001.5-1.5V7.5a1.5 1.5 0 00-.44-1.06l-3-3A1.5 1.5 0 0015 3H9a1.5 1.5 0 00-1.06.44l-3 3A1.5 1.5 0 004.5 7.5v8.25a1.5 1.5 0 001.5 1.5z" />
                </svg>
                <p class="text-gray-400 text-xs">${message}</p>
            </div>
        `;
    }

    function crmSortedRows(rows) {
        const sorted = [...rows].sort((a, b) => {
            let av = a[crmSortKey], bv = b[crmSortKey];
            if (crmSortKey === 'contract_amount') { av = Number(av) || 0; bv = Number(bv) || 0; }
            else if (crmSortKey === 'created_at') { av = new Date((av || '').replace(' ', 'T')).getTime() || 0; bv = new Date((bv || '').replace(' ', 'T')).getTime() || 0; }
            else { av = (av || '').toString().toLowerCase(); bv = (bv || '').toString().toLowerCase(); }
            if (av < bv) return crmSortDir === 'asc' ? -1 : 1;
            if (av > bv) return crmSortDir === 'asc' ? 1 : -1;
            return 0;
        });
        return sorted;
    }

    function crmUpdateSortArrows() {
        document.querySelectorAll('.crm-sort-th').forEach(btn => {
            const arrow = btn.querySelector('.crm-sort-arrow');
            if (btn.dataset.key === crmSortKey) {
                arrow.textContent = crmSortDir === 'asc' ? '↑' : '↓';
                arrow.classList.remove('text-gray-300');
                arrow.classList.add('text-amber-600');
            } else {
                arrow.textContent = '↕';
                arrow.classList.add('text-gray-300');
                arrow.classList.remove('text-amber-600');
            }
        });
    }

    // ═══════════════════════════════════════════════════════════
    // RENDER + FETCH
    // ═══════════════════════════════════════════════════════════
    function crmRenderRows(allRows) {
        const tbody = document.getElementById('crmListTbody');
        const cardsWrap = document.getElementById('crmListCards');

        const rows = crmFilteredRows(allRows);
        crmUpdateCount(rows.length, allRows.length);

        if (rows.length === 0) {
            tbody.innerHTML = `<tr><td colspan="9" class="p-0">${crmEmptyState()}</td></tr>`;
            cardsWrap.innerHTML = crmEmptyState();
            return;
        }

        const sorted = crmSortedRows(rows);

        tbody.innerHTML = sorted.map(row => `
            <tr class="hover:bg-amber-50/40 transition-colors cursor-pointer" onclick="crmOpenDetailModal(${row.id})">
                <td class="px-4 py-2.5">
                    <span class="font-mono text-[11px] font-semibold text-amber-700 hover:underline underline-offset-2">
                        ${crmEscapeHtml(row.control_no)}
                    </span>
                </td>
                <td class="px-4 py-2.5">
                    <div class="flex items-center gap-2">
                        <span class="text-gray-800">${crmEscapeHtml(row.client_name)}</span>
                    </div>
                </td>
                <td class="px-4 py-2.5 text-gray-600 whitespace-nowrap">${crmEscapeHtml(row.contact_number)}</td>
                <td class="px-4 py-2.5 text-gray-600">${crmEscapeHtml(row.project_type) || '—'}</td>
                <td class="px-4 py-2.5 text-gray-600">${crmEscapeHtml(row.designer_name)}</td>
                <td class="px-4 py-2.5 text-gray-800 text-right tabular-nums whitespace-nowrap">${crmFormatCurrency(row.contract_amount)}</td>
                <td class="px-4 py-2.5">${crmStatusBadge(row.status)}</td>
                <td class="px-4 py-2.5 text-gray-500 whitespace-nowrap">${crmFormatDate(row.created_at)}</td>
                <td class="px-4 py-2.5 text-right" onclick="event.stopPropagation()">${crmActionButton(row)}</td>
            </tr>
        `).join('');

        cardsWrap.innerHTML = sorted.map(row => `
            <div class="bg-white border border-gray-200 rounded-xl p-3.5 active:bg-amber-50/40 transition-colors" onclick="crmOpenDetailModal(${row.id})">
                <div class="flex items-start justify-between gap-3 mb-2.5">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <span class="shrink-0 inline-flex items-center justify-center w-9 h-9 rounded-full text-xs font-semibold ${crmAvatarColor(row.client_name)}">${crmEscapeHtml(crmInitials(row.client_name))}</span>
                        <div class="min-w-0">
                            <p class="text-gray-900 font-medium truncate">${crmEscapeHtml(row.client_name)}</p>
                            <p class="font-mono text-[11px] text-amber-700">${crmEscapeHtml(row.control_no)}</p>
                        </div>
                    </div>
                    ${crmStatusBadge(row.status)}
                </div>
                <div class="grid grid-cols-2 gap-y-1.5 text-xs text-gray-500 mb-2.5">
                    <span>${crmEscapeHtml(row.contact_number)}</span>
                    <span class="text-right">${crmEscapeHtml(row.project_type) || '—'}</span>
                    <span>${crmEscapeHtml(row.designer_name)}</span>
                    <span class="text-right font-medium text-gray-700 tabular-nums">${crmFormatCurrency(row.contract_amount)}</span>
                </div>
                <div class="flex items-center justify-between gap-3 pt-2 border-t border-gray-100">
                    <span class="text-[11px] text-gray-400">${crmFormatDate(row.created_at)}</span>
                    <div onclick="event.stopPropagation()">${crmActionButton(row, 'small')}</div>
                </div>
            </div>
        `).join('');
    }

    async function crmFetchList({ silent = false } = {}) {
        if (!silent && crmListRawRows.length === 0) crmSkeletonRows();
        try {
            const url = `${CRM_LIST_AJAX_URL}?action=list&q=${encodeURIComponent(crmListSearchTerm)}`;
            const res = await fetch(url);
            const data = await res.json();

            if (!data.success) {
                if (!silent) crmShowToast('Failed to load inquiries.', 'error');
                return;
            }

            crmListRawRows = data.rows;
            crmPopulateFilterOptions(data.rows);

            const signature = JSON.stringify(data.rows.map(r => r.id + ':' + r.status + ':' + r.designer_id))
                + crmSortKey + crmSortDir + crmFilterSig();
            if (signature !== crmListLastSignature) {
                crmRenderRows(data.rows);
                crmListLastSignature = signature;
            }

        } catch (e) {
            console.error('crmFetchList:', e);
            if (!silent) crmShowToast('Connection error while fetching inquiries.', 'error');
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

    document.querySelectorAll('.crm-sort-th').forEach(btn => {
        btn.addEventListener('click', () => {
            const key = btn.dataset.key;
            if (crmSortKey === key) {
                crmSortDir = crmSortDir === 'asc' ? 'desc' : 'asc';
            } else {
                crmSortKey = key;
                crmSortDir = 'asc';
            }
            crmUpdateSortArrows();
            crmListLastSignature = '';
            crmRenderRows(crmListRawRows);
        });
    });

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
    // DETAIL MODAL
    // ═══════════════════════════════════════════════════════════
    function crmDetailRow(label, value) {
        return `
            <div class="flex justify-between gap-3 py-2 border-b border-gray-100 text-[13px] last:border-b-0">
                <span class="text-gray-400 whitespace-nowrap">${label}</span>
                <span class="text-gray-800 font-medium text-right">${value}</span>
            </div>
        `;
    }

    async function crmOpenDetailModal(id) {
        const modal = document.getElementById('crmDetailModal');
        const body = document.getElementById('crmDetailBody');
        const quotationBtn = document.getElementById('crmDetail2dBtn');
        const assignBtn = document.getElementById('crmDetailAssignBtn');
        crmDetailCurrentId = id;

        document.getElementById('crmDetailControlNo').textContent = 'Loading…';
        document.getElementById('crmDetailStatusBadge').innerHTML = '';
        quotationBtn.classList.add('hidden');
        assignBtn.classList.add('hidden');
        quotationBtn.disabled = true;
        body.innerHTML = `
            <div class="space-y-2 py-1">
                ${Array.from({ length: 6 }).map(() => `<div class="h-4 rounded bg-gray-100 animate-pulse"></div>`).join('')}
            </div>
        `;
        modal.classList.remove('hidden');
        modal.classList.add('flex');

        try {
            const res = await fetch(`${CRM_LIST_AJAX_URL}?action=detail&id=${id}`);
            const data = await res.json();

            if (!data.success) {
                body.innerHTML = `<p class="text-sm text-red-500 py-6 text-center">${crmEscapeHtml(data.message || 'Record not found.')}</p>`;
                return;
            }

            const r = data.record;
            const isDone = r.status === 'In Progress';

            document.getElementById('crmDetailControlNo').textContent = r.control_no;
            document.getElementById('crmDetailStatusBadge').innerHTML = crmStatusBadge(r.status);

            if (!r.designer_id) {
                assignBtn.classList.remove('hidden');
                quotationBtn.classList.add('hidden');
            } else {
                assignBtn.classList.add('hidden');
                quotationBtn.classList.remove('hidden');
                quotationBtn.disabled = !isDone;
            }

            body.innerHTML = [
                crmDetailRow('Client Name', crmEscapeHtml(r.client_name)),
                crmDetailRow('Address', crmEscapeHtml(r.address) || '—'),
                crmDetailRow('Contact Number', crmEscapeHtml(r.contact_number)),
                crmDetailRow('Type of Project', crmEscapeHtml(r.project_type) || '—'),
                crmDetailRow('Scope of Project', crmEscapeHtml(r.project_scope) || '—'),
                crmDetailRow('Measuring Space', crmEscapeHtml(r.measuring_space) || '—'),
                crmDetailRow('Client Status', crmClientStatusBadge(r.client_status)),
                crmDetailRow('Measurement Date &amp; Time', crmFormatDateTimeLong(r.measurement_datetime)),
                crmDetailRow('Designer Assign', crmEscapeHtml(r.designer_name)),
                crmDetailRow('Contract Amount', crmFormatCurrency(r.contract_amount)),
                crmDetailRow('Branch', crmEscapeHtml(r.branch) || '—'),
                crmDetailRow('Filed By', crmEscapeHtml(r.sales_name)),
                crmDetailRow('Date Filed', crmFormatDateTimeLong(r.created_at)),
            ].join('');

        } catch (e) {
            console.error('crmOpenDetailModal:', e);
            body.innerHTML = `<p class="text-sm text-red-500 py-6 text-center">Connection error. Please try again.</p>`;
        }
    }

    function crmCloseDetailModal() {
        const modal = document.getElementById('crmDetailModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        crmDetailCurrentId = null;
    }

    document.getElementById('crmDetailModal').addEventListener('click', function (e) {
        if (e.target === this) crmCloseDetailModal();
    });

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            crmCloseDetailModal();
            crmCloseAssignModal();
        }
    });

    // ═══════════════════════════════════════════════════════════
    // SCHEDULE & ASSIGN MODAL
    // ═══════════════════════════════════════════════════════════
    let crmAssignCurrentId = null;
    let crmAssignDesignersLoaded = false;
    let crmAssignClientStatus = null; // 'confirmed' | 'tentative' | 'no' | null

    const crmClientStatusStyles = {
        confirmed: ['bg-emerald-50', 'border-emerald-500', 'text-emerald-700'],
        tentative: ['bg-amber-50', 'border-amber-500', 'text-amber-700'],
        no: ['bg-red-50', 'border-red-500', 'text-red-700'],
    };

    const crmClientStatusPauseNotes = {
        tentative: 'Client hasn\u2019t decided yet — schedule and designer will be set once they confirm.',
        no: 'Client is not proceeding — no schedule or designer needed for now.',
    };

    function crmUpdateClientStatusButtons() {
        document.querySelectorAll('.crm-client-status-btn').forEach(btn => {
            const value = btn.dataset.value;
            const isSelected = value === crmAssignClientStatus;

            btn.classList.remove('bg-emerald-50', 'border-emerald-500', 'text-emerald-700',
                'bg-amber-50', 'border-amber-500', 'text-amber-700',
                'bg-red-50', 'border-red-500', 'text-red-700');
            btn.classList.add('border-gray-300', 'text-gray-600');

            if (isSelected) {
                btn.classList.remove('border-gray-300', 'text-gray-600');
                btn.classList.add(...crmClientStatusStyles[value]);
            }
        });

        // Lalabas lang yung schedule fields kapag "Confirmed" ang napili —
        // wala pa (default) o Tentative/Not Proceeding = nakatago pa rin.
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
    }

    document.querySelectorAll('.crm-client-status-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const value = btn.dataset.value;
            // click again on the same one to deselect — optional field kasi
            crmAssignClientStatus = (crmAssignClientStatus === value) ? null : value;
            crmUpdateClientStatusButtons();
        });
    });

    async function crmOpenAssignModal(id) {
        if (!id) return;
        crmAssignCurrentId = id;
        document.getElementById('crmAssignDatetime').value = '';
        crmAssignClientStatus = null;
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
            const data = await res.json();
            if (data.success) {
                crmShowToast(isPaused ? 'Client status saved.' : 'Schedule saved and designer assigned.', 'success');
                const wasDetailOpen = crmDetailCurrentId === crmAssignCurrentId;
                crmCloseAssignModal();
                crmListLastSignature = '';
                crmFetchList();
                if (wasDetailOpen) crmOpenDetailModal(crmDetailCurrentId);
            } else {
                crmShowToast(data.message || 'Failed to save.', 'error');
            }
        } catch (e) {
            console.error('crmSubmitAssign:', e);
            crmShowToast('Connection error.', 'error');
        } finally {
            btn.disabled = false;
        }
    }

    document.getElementById('crmAssignModal').addEventListener('click', function (e) {
        if (e.target === this) crmCloseAssignModal();
    });
</script>