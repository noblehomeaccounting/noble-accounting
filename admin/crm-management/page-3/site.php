<?php
// site.php  —  Designer: Assigned Inquiries
// Listahan ng inquiries na naka-assign sa designer → Site Visit → 2D & Quotation

// Change this if your system uses a different route/path.
$crmDesignerAjaxUrl = BASE_URL . '/crmdesignerajax';

// URL to the Site Visit form — redirected here when "Proceed" is clicked
$crmSiteVisitUrl = BASE_URL . '/crmsitevisit';

// URL to the 2D & Quotation page — unlocked once the site visit is done
$crm2dQuotationUrl = BASE_URL . '/crm2dquotation';

?>
<div class="max-w-6xl mx-auto">

    <!-- ═══ HEADER ═══ -->
    <section class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden mb-4">
        <div class="bg-[repeating-linear-gradient(-45deg,#f59e0b_0_10px,#1f2937_10px_20px)] h-1.5"></div>
        <div class="p-5 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3 min-w-0">
                <span class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-compass-drafting text-lg"></i></span>
                <div class="min-w-0">
                    <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase">CRM Management · Designer</p>
                    <h1 class="text-gray-900 text-lg font-semibold leading-tight">Assigned Inquiries</h1>
                    <p class="text-gray-400 text-[11px] mt-0.5">Do the site visit first, then the 2D &amp; Quotation unlocks.</p>
                </div>
            </div>
            <!-- Counters = filter -->
            <div id="crmCounters" class="grid grid-cols-2 sm:grid-cols-4 gap-2 w-full lg:w-auto">
                <?php foreach ([
                    ['', 'All', 'text-gray-900'],
                    ['new', 'New', 'text-amber-700'],
                    ['Pending', 'Site visit', 'text-amber-800'],
                    ['In Progress', '2D & Quotation', 'text-blue-700'],
                ] as [$val, $label, $cls]): ?>
                    <button type="button" data-filter="<?= htmlspecialchars($val) ?>"
                        class="crm-counter text-left rounded-xl border px-3.5 py-2 transition-colors lg:min-w-[112px]">
                        <span class="block text-[10px] font-semibold uppercase tracking-wide opacity-80"><?= htmlspecialchars($label) ?></span>
                        <span data-count="<?= htmlspecialchars($val === '' ? 'all' : $val) ?>" class="block text-xl font-bold leading-tight tabular-nums <?= $cls ?>">—</span>
                    </button>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="px-5 py-3 border-t border-gray-100 bg-gray-50/60 flex flex-wrap items-center gap-2">
            <div class="relative flex-1 min-w-[200px] max-w-md">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[11px]"></i>
                <input id="crmSearch" type="text" placeholder="Search control no., client or contact…"
                    class="w-full h-9 bg-white border border-gray-200 rounded-lg pl-8 pr-3 text-xs outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-100">
            </div>
            <select id="crmTypeFilter"
                class="h-9 pl-2.5 pr-7 text-xs border border-gray-200 rounded-lg bg-white outline-none focus:border-amber-600 text-gray-600">
                <option value="">All project types</option>
            </select>
            <span id="crmAllCount" class="ml-auto text-[11px] text-gray-400"></span>
        </div>
    </section>

    <!-- ═══ LIST ═══ -->
    <div id="crmDesignerList" class="space-y-2.5 max-h-[calc(100vh-300px)] min-h-[320px] overflow-y-auto overscroll-contain [scrollbar-width:thin] [scrollbar-color:#d1d5db_transparent] p-1 pr-2 -m-1">
        <div class="bg-white border border-gray-200 rounded-xl px-6 py-12 text-center text-xs text-gray-400"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Loading assigned inquiries…</div>
    </div>

    <p id="crmDesignerCount" class="text-[11px] text-gray-400 mt-3"></p>
</div>

<!-- ═══ DETAIL PANEL (galing sa kanan) ═══ -->
<div id="crmDetailOverlay" class="fixed inset-0 bg-black/30 hidden z-40" onclick="crmCloseDetailPanel()"></div>

<div id="crmDetailPanel" class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 flex flex-col
           translate-x-full transition-transform duration-300 ease-out">
    <div class="bg-[repeating-linear-gradient(-45deg,#f59e0b_0_10px,#1f2937_10px_20px)] h-1.5 shrink-0"></div>
    <div class="px-5 py-4 border-b border-gray-100 flex items-start justify-between gap-3 shrink-0">
        <div class="min-w-0">
            <p id="crmDetailControlNo" class="font-mono text-[11px] font-semibold text-amber-700">—</p>
            <h3 id="crmDetailClient" class="text-lg font-semibold text-gray-900 truncate capitalize">—</h3>
            <div id="crmDetailStatusBadge" class="mt-1.5 flex flex-wrap items-center gap-1.5"></div>
        </div>
        <button type="button" onclick="crmCloseDetailPanel()" aria-label="Close"
            class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 shrink-0"><i class="fa-solid fa-xmark text-base leading-none"></i></button>
    </div>

    <div id="crmDetailBody" class="[scrollbar-width:thin] [scrollbar-color:#d1d5db_transparent] overscroll-contain px-5 py-4 overflow-y-auto flex-1 bg-slate-50/60">
        <!-- Populated via JS -->
    </div>

    <div class="px-5 py-4 bg-white border-t border-gray-100 shrink-0">
        <div class="grid grid-cols-2 gap-2">
            <button type="button" id="crmDetailSiteVisitBtn" onclick="crmProceedFromPanel()"
                class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 shadow-sm">
                <i class="fa-solid fa-ruler-combined"></i> <span>Start site visit</span>
            </button>
            <button type="button" id="crmDetail2dBtn" onclick="crm2dQuotationFromPanel()" disabled
                class="inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 text-xs font-semibold text-white bg-blue-700 rounded-lg hover:bg-blue-800 disabled:bg-gray-100 disabled:text-gray-400 disabled:cursor-not-allowed">
                <i class="fa-solid fa-file-pen"></i> 2D &amp; Quotation
            </button>
        </div>
    </div>
</div>

<!-- Toast container -->
<div id="crmToastContainer"
    class="fixed bottom-6 right-6 z-[9999] flex flex-col gap-2.5 pointer-events-none w-full max-w-sm px-4 sm:px-0">
</div>

<script>
    // ═══════════════════════════════════════════════════════════
    // TOAST NOTIFICATIONS
    // ═══════════════════════════════════════════════════════════
    function crmShowToast(message, type = 'success', duration = 4000) {
        const container = document.getElementById('crmToastContainer');
        const palette = type === 'success'
            ? { wrap: 'bg-green-50 border-green-200 text-green-700', icon: 'bg-green-200 text-green-700', symbol: '✓' }
            : { wrap: 'bg-red-50 border-red-200 text-red-700', icon: 'bg-red-200 text-red-700', symbol: '!' };
        const toast = document.createElement('div');
        toast.className = `pointer-events-auto flex items-start gap-2.5 border rounded-lg shadow-lg px-4 py-3 text-sm ${palette.wrap} translate-x-6 opacity-0 scale-95 transition-all duration-300 ease-out`;
        toast.innerHTML = `
            <span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold ${palette.icon}">${palette.symbol}</span>
            <span class="flex-1 leading-relaxed"></span>
            <button type="button" class="shrink-0 inline-flex items-center justify-center w-5 h-5 text-current opacity-50 hover:opacity-100" aria-label="Close"><i class="fa-solid fa-xmark text-xs"></i></button>`;
        toast.querySelector('.flex-1').textContent = message;
        container.appendChild(toast);
        requestAnimationFrame(() => toast.classList.remove('translate-x-6', 'opacity-0', 'scale-95'));
        const remove = () => { toast.classList.add('translate-x-6', 'opacity-0', 'scale-95'); setTimeout(() => toast.remove(), 300); };
        toast.querySelector('button').addEventListener('click', remove);
        if (duration > 0) setTimeout(remove, duration);
    }

    const CRM_DESIGNER_AJAX_URL = <?= json_encode($crmDesignerAjaxUrl) ?>;
    const CRM_SITEVISIT_URL = <?= json_encode($crmSiteVisitUrl) ?>;
    const CRM_2D_QUOTATION_URL = <?= json_encode($crm2dQuotationUrl) ?>;
    const CRM_POLL_INTERVAL_MS = 8000;

    let crmAllRows = [];
    let crmDesignerLastSignature = '';
    let crmDesignerPollTimer = null;
    let crmDetailCurrentId = null;

    let crmSearchTerm = '';
    let crmStatusFilterValue = '';     // '' | 'new' | 'Pending' | 'In Progress'
    let crmTypeFilterValue = '';
    let crmSearchDebounce = null;

    // ── "Viewed" (para sa New badge) — naka-save lang sa device na ito ──
    const CRM_VIEWED_KEY = 'crmDesignerViewedIds';
    function crmGetViewedIds() {
        try {
            const raw = localStorage.getItem(CRM_VIEWED_KEY);
            return raw ? new Set(JSON.parse(raw)) : new Set();
        } catch (e) { return new Set(); }
    }
    function crmMarkViewed(id) {
        const viewed = crmGetViewedIds();
        if (viewed.has(id)) return;
        viewed.add(id);
        try { localStorage.setItem(CRM_VIEWED_KEY, JSON.stringify([...viewed])); } catch (e) { }
    }

    // ── Helpers ──
    function crmEscapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str ?? '';
        return div.innerHTML;
    }
    function crmFormatCurrency(value) {
        const num = Number(value);
        if (!value || isNaN(num)) return '—';
        return '₱' + num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    const crmToDate = v => { if (!v) return null; const d = new Date(String(v).replace(' ', 'T')); return isNaN(d.getTime()) ? null : d; };
    function crmFormatDate(value) {
        const dt = crmToDate(value);
        return dt ? dt.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' }) : '—';
    }
    function crmFormatDateTimeLong(value) {
        const dt = crmToDate(value);
        return dt ? dt.toLocaleString('en-PH', { year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true }) : '—';
    }
    function crmFormatDateTimeShort(value) {
        const dt = crmToDate(value);
        return dt ? dt.toLocaleString('en-PH', { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true }) : '—';
    }
    // "Today", "Tomorrow", "in 3 days", "2 days ago"
    function crmRelDay(value) {
        const dt = crmToDate(value); if (!dt) return null;
        const a = new Date(); a.setHours(0, 0, 0, 0);
        const b = new Date(dt); b.setHours(0, 0, 0, 0);
        const days = Math.round((b - a) / 864e5);
        if (days === 0) return { text: 'Today', tone: 'amber' };
        if (days === 1) return { text: 'Tomorrow', tone: 'amber' };
        if (days > 1) return { text: `in ${days} days`, tone: 'gray' };
        if (days === -1) return { text: 'Yesterday', tone: 'red' };
        return { text: `${-days} days ago`, tone: 'red' };
    }
    const crmIsReady = r => r.mode === 'ready_for_quotation';

    function crmStatusBadge(status, mode) {
        if (status === 'In Progress') {
            return `<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10.5px] font-semibold border whitespace-nowrap bg-blue-50 text-blue-700 border-blue-200"><span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>${mode === 'ready_for_quotation' ? '2D &amp; Quotation' : 'Site visit done'}</span>`;
        }
        return `<span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10.5px] font-semibold border whitespace-nowrap bg-amber-50 text-amber-700 border-amber-200"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>For site visit</span>`;
    }
    function crmModeChip(mode) {
        return mode === 'ready_for_quotation'
            ? '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-semibold border border-violet-200 bg-violet-50 text-violet-700"><i class="fa-solid fa-bolt text-[9px]"></i>No site visit</span>'
            : '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-semibold border border-gray-200 bg-gray-50 text-gray-600"><i class="fa-solid fa-ruler-combined text-[9px]"></i>Site visit</span>';
    }
    const CRM_TONE = { amber: 'text-amber-700', red: 'text-red-600', gray: 'text-gray-500' };

    // ═══════════════════════════════════════════════════════════
    // LIST
    // ═══════════════════════════════════════════════════════════
    function crmRowHtml(row, viewedIds) {
        const isNew = !viewedIds.has(row.id);
        const isActive = crmDetailCurrentId === row.id;
        const done = row.status === 'In Progress';
        const rel = !done && row.measurement_datetime ? crmRelDay(row.measurement_datetime) : null;
        const bar = done ? 'bg-blue-500' : 'bg-amber-400';

        const action = done
            ? `<button type="button" data-act="2d" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-white bg-blue-700 rounded-lg hover:bg-blue-800 shadow-sm"><i class="fa-solid fa-file-pen"></i> 2D &amp; Quotation</button>`
            : `<button type="button" data-act="visit" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 shadow-sm"><i class="fa-solid fa-ruler-combined"></i> Start site visit</button>`;

        return `
            <article data-row-id="${row.id}"
                class="crm-row relative bg-white border rounded-xl overflow-hidden cursor-pointer transition ${isActive ? 'border-amber-500 ring-2 ring-amber-100' : 'border-gray-200 hover:border-gray-300 hover:shadow-sm'}">
                <span class="absolute left-0 top-0 bottom-0 w-1 ${bar}"></span>
                <div class="flex flex-wrap items-center gap-x-5 gap-y-3 pl-5 pr-4 py-3.5">
                    <div class="min-w-0 flex-1 basis-[240px]">
                        <div class="flex flex-wrap items-center gap-2">
                            ${isNew ? '<span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[9.5px] font-bold uppercase tracking-wide bg-amber-500 text-white">New</span>' : ''}
                            <span class="notranslate font-mono text-[11px] font-semibold text-amber-700">${crmEscapeHtml(row.control_no)}</span>
                            ${crmStatusBadge(row.status, row.mode)}
                        </div>
                        <p class="mt-1 text-sm font-semibold text-gray-900 truncate capitalize">${crmEscapeHtml(row.client_name)}</p>
                        <p class="text-[11px] text-gray-500 truncate">
                            <i class="fa-solid fa-phone text-gray-300 mr-1"></i>${crmEscapeHtml(row.contact_number) || '—'}
                            <span class="text-gray-300 mx-1.5">·</span><span class="capitalize">${crmEscapeHtml(row.project_type) || '—'}</span>
                            <span class="text-gray-300 mx-1.5">·</span>by ${crmEscapeHtml(row.sales_name) || '—'}
                        </p>
                    </div>
                    <dl class="grid grid-cols-2 gap-x-5 text-[11px] basis-[260px] grow sm:grow-0">
                        <div>
                            <dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">${crmIsReady(row) ? 'Mode' : 'Site visit'}</dt>
                            <dd class="text-gray-800 font-medium whitespace-nowrap">${crmIsReady(row) ? 'No site visit' : (row.measurement_datetime ? crmEscapeHtml(crmFormatDateTimeShort(row.measurement_datetime)) : '—')}</dd>
                            ${rel ? `<dd class="text-[10.5px] font-semibold ${CRM_TONE[rel.tone]}">${rel.text}</dd>` : ''}
                        </div>
                        <div>
                            <dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Assigned</dt>
                            <dd class="text-gray-800 font-medium whitespace-nowrap">${crmEscapeHtml(crmFormatDate(row.created_at))}</dd>
                        </div>
                    </dl>
                    <div class="ml-auto shrink-0">${action}</div>
                </div>
            </article>`;
    }

    function crmFilterRows(rows) {
        const viewed = crmGetViewedIds();
        let out = rows;
        if (crmStatusFilterValue === 'new') out = out.filter(r => !viewed.has(r.id));
        else if (crmStatusFilterValue) out = out.filter(r => r.status === crmStatusFilterValue);
        if (crmTypeFilterValue) out = out.filter(r => (r.project_type || '') === crmTypeFilterValue);
        if (crmSearchTerm) {
            const t = crmSearchTerm.toLowerCase();
            out = out.filter(r => `${r.control_no || ''} ${r.client_name || ''} ${r.contact_number || ''}`.toLowerCase().includes(t));
        }
        // Pending muna (pinakamalapit na site visit), saka In Progress
        return [...out].sort((a, b) => {
            if (a.status !== b.status) return a.status === 'Pending' ? -1 : 1;
            const ad = crmToDate(a.measurement_datetime)?.getTime() ?? crmToDate(a.created_at)?.getTime() ?? 0;
            const bd = crmToDate(b.measurement_datetime)?.getTime() ?? crmToDate(b.created_at)?.getTime() ?? 0;
            return a.status === 'Pending' ? ad - bd : bd - ad;
        });
    }

    function crmPopulateProjectFilter(rows) {
        const sel = document.getElementById('crmTypeFilter');
        const current = sel.value;
        const types = [...new Set(rows.map(r => r.project_type).filter(Boolean))].sort();
        const sig = types.join('|');
        if (sel.dataset.sig === sig) return;
        sel.dataset.sig = sig;
        sel.innerHTML = '<option value="">All project types</option>' + types.map(t => `<option value="${crmEscapeHtml(t)}">${crmEscapeHtml(t)}</option>`).join('');
        if (types.includes(current)) sel.value = current;
    }

    function crmRenderRows() {
        crmPopulateProjectFilter(crmAllRows);
        const viewed = crmGetViewedIds();

        // counters
        const counts = {
            all: crmAllRows.length,
            new: crmAllRows.filter(r => !viewed.has(r.id)).length,
            'Pending': crmAllRows.filter(r => r.status === 'Pending').length,
            'In Progress': crmAllRows.filter(r => r.status === 'In Progress').length,
        };
        document.querySelectorAll('#crmCounters [data-count]').forEach(el => el.textContent = counts[el.dataset.count] ?? 0);
        document.querySelectorAll('#crmCounters .crm-counter').forEach(b => {
            const on = b.dataset.filter === crmStatusFilterValue;
            b.classList.toggle('border-amber-600', on);
            b.classList.toggle('bg-amber-50', on);
            b.classList.toggle('ring-1', on);
            b.classList.toggle('ring-amber-600', on);
            b.classList.toggle('border-gray-200', !on);
            b.classList.toggle('bg-white', !on);
        });

        const rows = crmFilterRows(crmAllRows);
        const list = document.getElementById('crmDesignerList');
        if (!rows.length) {
            const msg = !crmAllRows.length ? 'No inquiries assigned to you yet.'
                : crmStatusFilterValue === 'new' ? 'You have opened all your inquiries.' : 'No inquiries match your search or filter.';
            list.innerHTML = `<div class="bg-white border-2 border-dashed border-gray-200 rounded-xl px-6 py-12 text-center">
                <div class="w-12 h-12 mx-auto rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center mb-2"><i class="fa-regular fa-folder-open"></i></div>
                <p class="text-sm font-semibold text-gray-700">${msg}</p></div>`;
        } else {
            list.innerHTML = rows.map(r => crmRowHtml(r, viewed)).join('');
        }
        document.getElementById('crmAllCount').textContent = `Showing ${rows.length} of ${crmAllRows.length}`;
        document.getElementById('crmDesignerCount').textContent = `${crmAllRows.length} inquir${crmAllRows.length === 1 ? 'y' : 'ies'} assigned to you`;
    }

    // Isang listener para sa lahat ng row (buong card = buksan ang detail; buttons = diretso)
    document.getElementById('crmDesignerList').addEventListener('click', e => {
        const card = e.target.closest('[data-row-id]'); if (!card) return;
        const id = Number(card.dataset.rowId);
        const btn = e.target.closest('[data-act]');
        if (btn) {
            crmMarkViewed(id);
            return btn.dataset.act === '2d' ? crm2dQuotation(id) : crmProceed(id);
        }
        crmOpenDetailPanel(id);
    });

    async function crmFetchList({ silent = false } = {}) {
        try {
            const res = await fetch(`${CRM_DESIGNER_AJAX_URL}?action=list`, { cache: 'no-store' });
            const data = await res.json();
            if (!data.success) {
                if (!silent) crmShowToast(data.message || 'Failed to load assigned inquiries.', 'error');
                return;
            }
            const signature = JSON.stringify(data.rows.map(r => r.id + ':' + r.status + ':' + r.mode + ':' + (r.measurement_datetime || '')));
            const prevIds = new Set(crmAllRows.map(r => r.id));
            const fresh = silent && crmAllRows.length ? data.rows.filter(r => !prevIds.has(r.id)).length : 0;
            crmAllRows = data.rows;
            if (signature !== crmDesignerLastSignature) {
                crmRenderRows();
                crmDesignerLastSignature = signature;
            }
            if (fresh) crmShowToast(`${fresh} new inquir${fresh === 1 ? 'y was' : 'ies were'} assigned to you.`);
        } catch (e) {
            console.error('crmFetchList:', e);
            if (!silent) {
                document.getElementById('crmDesignerList').innerHTML = `<div class="bg-white border border-red-200 rounded-xl px-6 py-10 text-center text-xs text-red-600">Could not load your inquiries. <button type="button" onclick="crmFetchList()" class="font-semibold underline">Retry</button></div>`;
            }
        }
    }

    function crmStartPolling() {
        if (crmDesignerPollTimer) clearInterval(crmDesignerPollTimer);
        crmDesignerPollTimer = setInterval(() => crmFetchList({ silent: true }), CRM_POLL_INTERVAL_MS);
    }
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) { if (crmDesignerPollTimer) clearInterval(crmDesignerPollTimer); }
        else { crmFetchList({ silent: true }); crmStartPolling(); }
    });

    document.getElementById('crmSearch').addEventListener('input', function () {
        clearTimeout(crmSearchDebounce);
        const value = this.value;
        crmSearchDebounce = setTimeout(() => { crmSearchTerm = value.trim(); crmRenderRows(); }, 250);
    });
    document.getElementById('crmTypeFilter').addEventListener('change', function () {
        crmTypeFilterValue = this.value;
        crmRenderRows();
    });
    document.querySelectorAll('#crmCounters .crm-counter').forEach(b => b.addEventListener('click', () => {
        crmStatusFilterValue = b.dataset.filter;
        try { sessionStorage.setItem('crmDesignerFilter', crmStatusFilterValue); } catch (e) { }
        crmRenderRows();
    }));
    try { crmStatusFilterValue = sessionStorage.getItem('crmDesignerFilter') || ''; } catch (e) { }

    crmFetchList().then(crmStartPolling);

    // ═══════════════════════════════════════════════════════════
    // ACTIONS
    // ═══════════════════════════════════════════════════════════
    function crmProceed(id) { window.location.href = `${CRM_SITEVISIT_URL}?id=${id}`; }
    function crmProceedFromPanel() { if (crmDetailCurrentId) crmProceed(crmDetailCurrentId); }
    function crm2dQuotation(id) { window.location.href = `${CRM_2D_QUOTATION_URL}?id=${id}`; }
    function crm2dQuotationFromPanel() { if (crmDetailCurrentId) crm2dQuotation(crmDetailCurrentId); }

    // ═══════════════════════════════════════════════════════════
    // DETAIL PANEL
    // ═══════════════════════════════════════════════════════════
    function crmDetailRow(label, value) {
        return `<div class="flex justify-between gap-3 py-2 border-b border-gray-100 text-[12.5px] last:border-b-0">
                <span class="text-gray-400 whitespace-nowrap">${label}</span>
                <span class="text-gray-800 font-medium text-right min-w-0 break-words">${value}</span></div>`;
    }
    function crmDetailGroup(title, icon, rowsHtml) {
        return `<section class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <p class="px-4 py-2.5 border-b border-gray-100 text-[10.5px] font-bold uppercase tracking-wider text-gray-500"><i class="fa-solid ${icon} mr-1 text-amber-700"></i>${title}</p>
                <div class="px-4 py-1">${rowsHtml}</div></section>`;
    }

    async function crmOpenDetailPanel(id) {
        const overlay = document.getElementById('crmDetailOverlay');
        const panel = document.getElementById('crmDetailPanel');
        const body = document.getElementById('crmDetailBody');
        const siteVisitBtn = document.getElementById('crmDetailSiteVisitBtn');
        const quotationBtn = document.getElementById('crmDetail2dBtn');
        crmDetailCurrentId = id;

        crmMarkViewed(id);
        crmRenderRows();

        document.getElementById('crmDetailControlNo').textContent = 'Loading…';
        document.getElementById('crmDetailClient').textContent = '';
        document.getElementById('crmDetailStatusBadge').innerHTML = '';
        siteVisitBtn.style.display = '';
        siteVisitBtn.querySelector('span').textContent = 'Start site visit';
        quotationBtn.disabled = true;
        body.innerHTML = `<div class="space-y-3">${Array.from({ length: 4 }).map(() => '<div class="h-16 rounded-xl bg-gray-100 animate-pulse"></div>').join('')}</div>`;

        overlay.classList.remove('hidden');
        requestAnimationFrame(() => panel.classList.remove('translate-x-full'));

        try {
            const res = await fetch(`${CRM_DESIGNER_AJAX_URL}?action=detail&id=${id}`, { cache: 'no-store' });
            const data = await res.json();
            if (crmDetailCurrentId !== id) return;
            if (!data.success) {
                body.innerHTML = `<p class="text-sm text-red-500 py-6 text-center">${crmEscapeHtml(data.message || 'Record not found.')}</p>`;
                return;
            }

            const r = data.record;
            const isDone = r.status === 'In Progress';
            const isReady = r.mode === 'ready_for_quotation';

            document.getElementById('crmDetailControlNo').textContent = r.control_no;
            document.getElementById('crmDetailClient').textContent = r.client_name || '';
            document.getElementById('crmDetailStatusBadge').innerHTML = crmStatusBadge(r.status, r.mode) + crmModeChip(r.mode);

            // buttons
            if (isReady) {
                siteVisitBtn.style.display = 'none';
            } else {
                siteVisitBtn.style.display = '';
                siteVisitBtn.querySelector('span').textContent = isDone ? 'View site visit' : 'Start site visit';
                siteVisitBtn.className = isDone
                    ? 'inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50'
                    : 'inline-flex items-center justify-center gap-1.5 px-3.5 py-2.5 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 shadow-sm';
            }
            quotationBtn.disabled = !isDone;
            quotationBtn.parentElement.className = isReady ? 'grid grid-cols-1 gap-2' : 'grid grid-cols-2 gap-2';

            // next step
            const rel = crmRelDay(r.measurement_datetime);
            let tone, icon, title, text;
            if (isDone) { tone = 'bg-blue-50 border-blue-200 text-blue-900'; icon = 'fa-file-pen'; title = 'Next: 2D & Quotation'; text = isReady ? 'This inquiry does not need a site visit. Prepare the 2D and quotation.' : 'The site visit is done. Prepare the 2D and quotation.'; }
            else { tone = 'bg-amber-50 border-amber-200 text-amber-900'; icon = 'fa-ruler-combined'; title = 'Next: Site visit'; text = r.measurement_datetime ? `Scheduled ${crmFormatDateTimeLong(r.measurement_datetime)}${rel ? ' · ' + rel.text : ''}. Record the measurements after the visit.` : 'No schedule set yet. Ask the sales staff for the visit date.'; }

            // target completion
            const tgt = crmRelDay(r.target_completion_date);

            body.innerHTML = `<div class="space-y-3">
                <div class="rounded-xl border px-4 py-3 flex items-start gap-3 ${tone}">
                    <span class="w-9 h-9 rounded-lg bg-white/70 flex items-center justify-center shrink-0"><i class="fa-solid ${icon}"></i></span>
                    <div class="min-w-0"><p class="text-sm font-semibold">${title}</p><p class="text-xs opacity-80 mt-0.5">${crmEscapeHtml(text)}</p></div>
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <div class="bg-white border border-gray-200 rounded-xl px-3.5 py-2.5">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400"><i class="fa-regular fa-calendar mr-1"></i>Site visit</p>
                        <p class="text-[13px] font-semibold text-gray-900 mt-0.5">${isReady ? 'Not needed' : crmEscapeHtml(crmFormatDateTimeShort(r.measurement_datetime))}</p>
                        ${!isReady && !isDone && rel ? `<p class="text-[10.5px] font-semibold ${CRM_TONE[rel.tone]}">${rel.text}</p>` : ''}
                    </div>
                    <div class="bg-white border border-gray-200 rounded-xl px-3.5 py-2.5">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400"><i class="fa-regular fa-flag mr-1"></i>Target completion</p>
                        <p class="text-[13px] font-semibold text-gray-900 mt-0.5">${crmEscapeHtml(crmFormatDate(r.target_completion_date))}</p>
                        ${tgt ? `<p class="text-[10.5px] font-semibold ${CRM_TONE[tgt.tone]}">${tgt.text}</p>` : ''}
                    </div>
                </div>

                ${crmDetailGroup('Client', 'fa-user', [
                    crmDetailRow('Name', `<span class="capitalize">${crmEscapeHtml(r.client_name)}</span>`),
                    crmDetailRow('Contact', r.contact_number ? `<a href="tel:${crmEscapeHtml(String(r.contact_number).replace(/[^\d+]/g, ''))}" class="text-amber-700 hover:underline">${crmEscapeHtml(r.contact_number)}</a>` : '—'),
                    crmDetailRow('Address', r.address ? `${crmEscapeHtml(r.address)}<a href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(r.address)}" target="_blank" rel="noopener" class="block text-[11px] text-amber-700 hover:underline mt-0.5"><i class="fa-solid fa-map-location-dot mr-1"></i>Open in Maps</a>` : '—'),
                ].join(''))}

                ${crmDetailGroup('Project', 'fa-helmet-safety', [
                    crmDetailRow('Type', `<span class="capitalize">${crmEscapeHtml(r.project_type) || '—'}</span>`),
                    crmDetailRow('Scope', crmEscapeHtml(r.project_scope) || '—'),
                    crmDetailRow('Measuring space', crmEscapeHtml(r.measuring_space) || '—'),
                    crmDetailRow('Contract amount', crmFormatCurrency(r.contract_amount)),
                ].join(''))}

                ${crmDetailGroup('Assignment', 'fa-clipboard-user', [
                    crmDetailRow('Filed by', crmEscapeHtml(r.sales_name) || '—'),
                    crmDetailRow('Branch', `<span class="capitalize">${crmEscapeHtml(r.branch) || '—'}</span>`),
                    crmDetailRow('Date assigned', crmEscapeHtml(crmFormatDateTimeLong(r.created_at))),
                ].join(''))}
            </div>`;
        } catch (e) {
            console.error('crmOpenDetailPanel:', e);
            body.innerHTML = `<p class="text-sm text-red-500 py-6 text-center">Connection error. Please try again.</p>`;
        }
    }

    function crmCloseDetailPanel() {
        const overlay = document.getElementById('crmDetailOverlay');
        const panel = document.getElementById('crmDetailPanel');
        panel.classList.add('translate-x-full');
        setTimeout(() => overlay.classList.add('hidden'), 300);
        crmDetailCurrentId = null;
        crmRenderRows();
    }
    document.addEventListener('keydown', e => { if (e.key === 'Escape' && crmDetailCurrentId) crmCloseDetailPanel(); });
</script>