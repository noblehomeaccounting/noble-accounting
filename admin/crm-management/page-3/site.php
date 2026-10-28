<?php
// site.php  —  Designer: Assigned Inquiries
// Listahan ng inquiries na naka-assign sa designer → Site Visit → 2D & Quotation

// Change this if your system uses a different route/path.
$crmDesignerAjaxUrl = BASE_URL . '/crmdesignerajax';

// URL to the Site Visit form — redirected here when "Proceed" is clicked
$crmSiteVisitUrl = BASE_URL . '/crmsitevisit';

// URL to the 2D & Quotation page — unlocked once the site visit is done
$crm2dQuotationUrl = BASE_URL . '/crm2dquotation';

// Feedback summary (Cutting / Designer Head / Customer) — hiwalay na endpoint
$crmDesignerFeedbackUrl = BASE_URL . '/crmdesignerfeedbackajax';

// Nasaang bahagi na: Site visit → Initial → Customer review → Final → Cutting — hiwalay na endpoint
$crmDesignerProgressUrl = BASE_URL . '/crmdesignerprogressajax';

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
            <div id="crmCounters" class="grid grid-cols-2 sm:grid-cols-5 gap-2 w-full lg:w-auto">
                <?php foreach ([
                    ['', 'All', 'text-gray-900'],
                    ['new', 'New', 'text-amber-700'],
                    ['Pending', 'Site visit', 'text-amber-800'],
                    ['In Progress', '2D & Quotation', 'text-blue-700'],
                    ['feedback', 'Feedback', 'text-red-600'],
                ] as [$val, $label, $cls]): ?>
                    <button type="button" data-filter="<?= htmlspecialchars($val) ?>"
                        class="crm-counter relative text-left rounded-xl border px-3.5 py-2 transition-colors lg:min-w-[112px]">
                        <?php if ($val === 'feedback'): ?>
                            <span id="crmFeedbackPing" class="hidden absolute top-2 right-2 flex w-2.5 h-2.5">
                                <span class="absolute inline-flex w-full h-full rounded-full bg-red-400 opacity-75 animate-ping"></span>
                                <span class="relative inline-flex w-2.5 h-2.5 rounded-full bg-red-500"></span>
                            </span>
                        <?php endif; ?>
                        <span class="block text-[10px] font-semibold uppercase tracking-wide opacity-80"><?php if ($val === 'feedback'): ?><i class="fa-solid fa-comment-dots mr-1"></i><?php endif; ?><?= htmlspecialchars($label) ?></span>
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

    <!-- ═══ FEEDBACK BANNER (lumalabas lang kapag may feedback) ═══ -->
    <button type="button" id="crmFeedbackBanner" onclick="crmSetFilter('feedback')"
        class="hidden w-full mb-3 text-left flex items-center gap-3 rounded-xl border border-red-200 bg-red-50 px-4 py-3 hover:bg-red-100/70 transition">
        <span class="w-9 h-9 rounded-lg bg-red-600 text-white flex items-center justify-center shrink-0"><i class="fa-solid fa-comment-dots"></i></span>
        <span class="min-w-0 flex-1">
            <span id="crmFeedbackBannerTitle" class="block text-sm font-semibold text-red-800"></span>
            <span id="crmFeedbackBannerText" class="block text-[11px] text-red-700/80 truncate"></span>
        </span>
        <span class="shrink-0 text-[11px] font-semibold text-red-700">Show <i class="fa-solid fa-arrow-right ml-0.5"></i></span>
    </button>

    <!-- ═══ LIST ═══ -->
    <div id="crmDesignerList" class="space-y-2.5 max-h-[calc(100vh-300px)] min-h-[320px] overflow-y-auto overscroll-contain [scrollbar-width:thin] [scrollbar-color:#d1d5db_transparent] py-1 pr-1.5">
        <div class="bg-white border border-gray-200 rounded-xl px-6 py-12 text-center text-xs text-gray-400"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Loading assigned inquiries…</div>
    </div>

    <p id="crmDesignerCount" class="text-[11px] text-gray-400 mt-3"></p>
</div>

<!-- ═══ DETAIL PANEL (galing sa kanan) ═══ -->
<div id="crmDetailOverlay" class="fixed inset-0 bg-black/30 hidden z-40" onclick="crmCloseDetailPanel()"></div>

<!-- wrapper: para hindi magka-horizontal scroll habang nakatago ang panel -->
<div class="fixed inset-y-0 right-0 w-full max-w-md overflow-hidden z-50 pointer-events-none">
<div id="crmDetailPanel" class="pointer-events-auto h-full w-full bg-white shadow-2xl flex flex-col
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
    const CRM_FEEDBACK_URL = <?= json_encode($crmDesignerFeedbackUrl) ?>;
    const CRM_PROGRESS_URL = <?= json_encode($crmDesignerProgressUrl) ?>;

    // inquiry_id → { phase (1-5), phase_label, status, tone, note, next }
    let crmProgress = {};
    const crmPg = id => crmProgress[String(id)] || null;
    const CRM_PHASES = ['Site visit', 'Initial', 'Customer review', 'Final', 'Cutting'];
    const CRM_PG_TONE = {
        amber: { chip: 'bg-amber-50 text-amber-800 border-amber-200', seg: 'bg-amber-500', text: 'text-amber-800', box: 'bg-amber-50 border-amber-200 text-amber-900' },
        blue:  { chip: 'bg-blue-50 text-blue-700 border-blue-200',   seg: 'bg-blue-500',  text: 'text-blue-700',  box: 'bg-blue-50 border-blue-200 text-blue-900' },
        red:   { chip: 'bg-red-50 text-red-700 border-red-200',      seg: 'bg-red-500',   text: 'text-red-700',   box: 'bg-red-50 border-red-200 text-red-900' },
        green: { chip: 'bg-emerald-50 text-emerald-700 border-emerald-200', seg: 'bg-emerald-500', text: 'text-emerald-700', box: 'bg-emerald-50 border-emerald-200 text-emerald-900' },
    };
    const CRM_POLL_INTERVAL_MS = 8000;

    // inquiry_id → { total, entries: [{ kind, label, count, at, messages?, slots? }] }
    let crmFeedback = {};
    let crmFeedbackLoaded = false;
    const crmFb = id => crmFeedback[String(id)] || null;
    const CRM_FB_STYLE = {
        cutting:  { icon: 'fa-scissors', chip: 'bg-red-50 text-red-700 border-red-200', short: 'Cutting' },
        head:     { icon: 'fa-user-tie', chip: 'bg-red-50 text-red-700 border-red-200', short: 'Designer Head' },
        customer: { icon: 'fa-user', chip: 'bg-orange-50 text-orange-700 border-orange-200', short: 'Customer' },
        '3d':     { icon: 'fa-cube', chip: 'bg-orange-50 text-orange-700 border-orange-200', short: '3D' },
    };

    let crmAllRows = [];
    let crmDesignerLastSignature = '';
    let crmDesignerPollTimer = null;
    let crmDetailCurrentId = null;

    let crmSearchTerm = '';
    let crmStatusFilterValue = '';     // '' | 'new' | 'Pending' | 'In Progress' | 'feedback'
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
        const fb = crmFb(row.id);
        const bar = fb ? 'bg-red-500' : done ? 'bg-blue-500' : 'bg-amber-400';

        const action = fb
            ? `<button type="button" data-act="2d" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 shadow-sm"><i class="fa-solid fa-comment-dots"></i> Open feedback</button>`
            : done
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
                            ${fb ? fb.entries.map(crmFbChip).join('') : ''}
                        </div>
                        <p class="mt-1 text-sm font-semibold text-gray-900 truncate capitalize">${crmEscapeHtml(row.client_name)}</p>
                        <p class="text-[11px] text-gray-500 truncate">
                            <i class="fa-solid fa-phone text-gray-300 mr-1"></i>${crmEscapeHtml(row.contact_number) || '—'}
                            <span class="text-gray-300 mx-1.5">·</span><span class="capitalize">${crmEscapeHtml(row.project_type) || '—'}</span>
                            <span class="text-gray-300 mx-1.5">·</span>by ${crmEscapeHtml(row.sales_name) || '—'}
                        </p>
                    </div>
                    ${crmStageBlock(row)}
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
                ${fb ? `<div class="flex items-start gap-2 ml-5 mr-4 mb-3 -mt-1 rounded-lg bg-red-50/70 border border-red-100 px-3 py-2 text-[11.5px] text-red-800">
                    <i class="fa-solid fa-quote-left text-[10px] mt-0.5 text-red-400"></i>
                    <p class="min-w-0 flex-1 truncate">${crmEscapeHtml(crmFbPreview(fb))}</p>
                </div>` : ''}
            </article>`;
    }

    // Stage indicator sa card: "Initial · For approval" + 5 na hakbang
    function crmStageBlock(row) {
        const pg = crmPg(row.id);
        if (!pg) return '';
        const t = CRM_PG_TONE[pg.tone] || CRM_PG_TONE.amber;
        const segs = CRM_PHASES.map((name, i) => {
            const n = i + 1;
            const cls = n < pg.phase || (n === pg.phase && pg.tone === 'green') ? 'bg-amber-600' : n === pg.phase ? t.seg : 'bg-gray-200';
            return `<span title="${n}. ${name}" class="h-1.5 flex-1 rounded-full ${cls}"></span>`;
        }).join('');
        return `<div class="basis-[190px] sm:max-w-[200px] grow sm:grow-0 min-w-0">
                <p class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Stage <span class="normal-case tracking-normal text-gray-300">· ${pg.phase} of 5</span></p>
                <p class="mt-0.5"><span title="${crmEscapeHtml(pg.note)}" class="inline-flex flex-wrap items-center gap-x-1 px-2 py-0.5 rounded-lg border text-[10.5px] font-bold leading-tight max-w-full ${t.chip}">${crmEscapeHtml(pg.phase_label)}<span class="font-semibold opacity-80">· ${crmEscapeHtml(pg.status)}</span></span></p>
                <div class="mt-1.5 flex gap-0.5 max-w-[160px]" aria-label="Stage ${pg.phase} of 5">${segs}</div>
            </div>`;
    }

    // Stage stepper sa detail panel
    function crmStageDetail(id) {
        const pg = crmPg(id);
        if (!pg) return '';
        const t = CRM_PG_TONE[pg.tone] || CRM_PG_TONE.amber;
        const steps = CRM_PHASES.map((name, i) => {
            const n = i + 1;
            const done = n < pg.phase || (n === pg.phase && pg.tone === 'green');
            const cur = n === pg.phase;
            const dot = done ? 'bg-amber-700 text-white' : cur ? `bg-white ring-2 ${t.text} ring-current` : 'bg-gray-100 text-gray-400';
            return `<li class="relative flex-1 flex flex-col items-center text-center min-w-0">
                    ${n > 1 ? `<span class="absolute top-3.5 right-1/2 w-full h-0.5 ${n <= pg.phase ? 'bg-amber-600' : 'bg-gray-200'}"></span>` : ''}
                    <span class="relative z-[1] w-7 h-7 rounded-full flex items-center justify-center text-[10.5px] font-bold ${dot}">${done ? '<i class="fa-solid fa-check text-[10px]"></i>' : n}</span>
                    <span class="mt-1 text-[10px] font-semibold leading-tight ${cur ? 'text-gray-900' : done ? 'text-amber-800' : 'text-gray-400'}">${name}</span>
                </li>`;
        }).join('');
        return `<section class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <p class="px-4 py-2.5 border-b border-gray-100 text-[10.5px] font-bold uppercase tracking-wider text-gray-500"><i class="fa-solid fa-route mr-1 text-amber-700"></i>Stage</p>
                <div class="px-3 pt-3"><ol class="flex items-start">${steps}</ol></div>
                <div class="m-3 mt-3 rounded-lg border px-3 py-2 ${t.box}">
                    <p class="text-[12px] font-semibold">${crmEscapeHtml(pg.phase_label)} · ${crmEscapeHtml(pg.status)}</p>
                    <p class="text-[11.5px] opacity-80">${crmEscapeHtml(pg.note)}</p>
                    <p class="text-[10.5px] font-semibold mt-1 opacity-70">Next: ${crmEscapeHtml(pg.next)}</p>
                </div>
            </section>`;
    }

    function crmFbChip(e) {
        const st = CRM_FB_STYLE[e.kind] || CRM_FB_STYLE.cutting;
        const text = e.kind === 'cutting' ? `${e.count} from Cutting` : `Revision · ${st.short}`;
        return `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-semibold border whitespace-nowrap ${st.chip}"><i class="fa-solid ${st.icon} text-[9px]"></i>${crmEscapeHtml(text)}</span>`;
    }
    // Isang linyang buod ng pinakabagong feedback (para sa card)
    function crmFbPreview(fb) {
        const e = fb.entries[0];
        if (e.kind === 'cutting' && e.messages?.length) return `${e.messages[0].by}: ${e.messages[0].message}`;
        const withRemarks = (e.slots || []).find(s => s.remarks);
        if (withRemarks) return `${withRemarks.label}: ${withRemarks.remarks}`;
        return e.label + ((e.slots || []).length ? ' — ' + e.slots.map(s => s.label).join(', ') : '');
    }

    function crmFilterRows(rows) {
        const viewed = crmGetViewedIds();
        let out = rows;
        if (crmStatusFilterValue === 'new') out = out.filter(r => !viewed.has(r.id));
        else if (crmStatusFilterValue === 'feedback') out = out.filter(r => crmFb(r.id));
        else if (crmStatusFilterValue) out = out.filter(r => r.status === crmStatusFilterValue);
        if (crmTypeFilterValue) out = out.filter(r => (r.project_type || '') === crmTypeFilterValue);
        if (crmSearchTerm) {
            const t = crmSearchTerm.toLowerCase();
            out = out.filter(r => `${r.control_no || ''} ${r.client_name || ''} ${r.contact_number || ''}`.toLowerCase().includes(t));
        }
        // May feedback muna, tapos Pending (pinakamalapit na site visit), saka In Progress
        return [...out].sort((a, b) => {
            const fa = crmFb(a.id) ? 1 : 0, fbb = crmFb(b.id) ? 1 : 0;
            if (fa !== fbb) return fbb - fa;
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
            feedback: crmAllRows.filter(r => crmFb(r.id)).length,
        };
        crmRenderFeedbackBanner(counts.feedback);
        document.querySelectorAll('#crmCounters [data-count]').forEach(el => el.textContent = counts[el.dataset.count] ?? 0);
        document.querySelectorAll('#crmCounters .crm-counter').forEach(b => {
            const on = b.dataset.filter === crmStatusFilterValue;
            if (b.dataset.filter === 'feedback' && counts.feedback && !on) {
                b.classList.add('border-red-300', 'bg-red-50/60');
            } else {
                b.classList.remove('border-red-300', 'bg-red-50/60');
            }
            b.classList.toggle('border-amber-600', on);
            b.classList.toggle('bg-amber-50', on);
            b.classList.toggle('ring-1', on);
            b.classList.toggle('ring-amber-600', on);
            const fbHot = b.dataset.filter === 'feedback' && counts.feedback && !on;
            b.classList.toggle('border-gray-200', !on && !fbHot);
            b.classList.toggle('bg-white', !on && !fbHot);
        });

        const rows = crmFilterRows(crmAllRows);
        const list = document.getElementById('crmDesignerList');
        if (!rows.length) {
            const msg = !crmAllRows.length ? 'No inquiries assigned to you yet.'
                : crmStatusFilterValue === 'new' ? 'You have opened all your inquiries.'
                : crmStatusFilterValue === 'feedback' ? 'No feedback to work on. You\'re all caught up.'
                : 'No inquiries match your search or filter.';
            list.innerHTML = `<div class="bg-white border-2 border-dashed border-gray-200 rounded-xl px-6 py-12 text-center">
                <div class="w-12 h-12 mx-auto rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center mb-2"><i class="fa-regular fa-folder-open"></i></div>
                <p class="text-sm font-semibold text-gray-700">${msg}</p></div>`;
        } else {
            list.innerHTML = rows.map(r => crmRowHtml(r, viewed)).join('');
        }
        document.getElementById('crmAllCount').textContent = `Showing ${rows.length} of ${crmAllRows.length}`;
        document.getElementById('crmDesignerCount').textContent = `${crmAllRows.length} inquir${crmAllRows.length === 1 ? 'y' : 'ies'} assigned to you`;
    }

    function crmRenderFeedbackBanner(n) {
        const banner = document.getElementById('crmFeedbackBanner');
        document.getElementById('crmFeedbackPing').classList.toggle('hidden', !n);
        if (!n || crmStatusFilterValue === 'feedback') { banner.classList.add('hidden'); return; }
        const rows = crmAllRows.filter(r => crmFb(r.id));
        document.getElementById('crmFeedbackBannerTitle').textContent =
            `${n} inquir${n === 1 ? 'y has' : 'ies have'} feedback to work on`;
        document.getElementById('crmFeedbackBannerText').textContent =
            rows.slice(0, 3).map(r => `${r.control_no} · ${r.client_name}`).join('   |   ') + (rows.length > 3 ? `  +${rows.length - 3} more` : '');
        banner.classList.remove('hidden');
    }

    function crmSetFilter(value) {
        crmStatusFilterValue = value;
        try { sessionStorage.setItem('crmDesignerFilter', crmStatusFilterValue); } catch (e) { }
        crmRenderRows();
        document.getElementById('crmDesignerList').scrollTop = 0;
    }

    // Feedback summary — hindi dapat masira ang listahan kapag pumalya ito
    async function crmFetchFeedback() {
        try {
            const res = await fetch(`${CRM_FEEDBACK_URL}?action=summary`, { cache: 'no-store' });
            const data = await res.json();
            if (!data.success) { console.warn('crmFetchFeedback:', data.message); return crmFeedback; }
            return data.items || {};
        } catch (e) {
            console.warn('crmFetchFeedback:', e);
            return crmFeedback;
        }
    }
    // Stage summary — hindi dapat masira ang listahan kapag pumalya ito
    async function crmFetchProgress() {
        try {
            const res = await fetch(`${CRM_PROGRESS_URL}?action=summary`, { cache: 'no-store' });
            const data = await res.json();
            if (!data.success) { console.warn('crmFetchProgress:', data.message); return crmProgress; }
            return data.items || {};
        } catch (e) {
            console.warn('crmFetchProgress:', e);
            return crmProgress;
        }
    }
    const crmPgSignature = items => Object.keys(items).sort().map(k => k + ':' + items[k].phase + items[k].status).join('|');
    const crmFbSignature = items => Object.keys(items).sort().map(k => k + ':' + items[k].total + ':' + items[k].entries.map(e => e.kind + (e.at_raw || '')).join(',')).join('|');

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
            const [res, fbItems, pgItems] = await Promise.all([
                fetch(`${CRM_DESIGNER_AJAX_URL}?action=list`, { cache: 'no-store' }),
                crmFetchFeedback(),
                crmFetchProgress(),
            ]);
            const data = await res.json();
            if (!data.success) {
                if (!silent) crmShowToast(data.message || 'Failed to load assigned inquiries.', 'error');
                return;
            }
            const signature = JSON.stringify(data.rows.map(r => r.id + ':' + r.status + ':' + r.mode + ':' + (r.measurement_datetime || ''))) + '#' + crmFbSignature(fbItems) + '#' + crmPgSignature(pgItems);
            const prevIds = new Set(crmAllRows.map(r => r.id));
            const fresh = silent && crmAllRows.length ? data.rows.filter(r => !prevIds.has(r.id)).length : 0;

            // Bagong feedback mula noong huling check → toast
            const newFb = crmFeedbackLoaded
                ? data.rows.filter(r => {
                    const now = fbItems[String(r.id)], before = crmFeedback[String(r.id)];
                    return now && (!before || now.total > before.total);
                })
                : [];
            crmFeedback = fbItems;
            crmFeedbackLoaded = true;
            crmProgress = pgItems;
            crmAllRows = data.rows;
            if (signature !== crmDesignerLastSignature) {
                crmRenderRows();
                crmDesignerLastSignature = signature;
            }
            if (fresh) crmShowToast(`${fresh} new inquir${fresh === 1 ? 'y was' : 'ies were'} assigned to you.`);
            if (newFb.length) {
                crmShowToast(newFb.length === 1
                    ? `New feedback on ${newFb[0].control_no} (${newFb[0].client_name}).`
                    : `New feedback on ${newFb.length} inquiries.`, 'error', 7000);
            }
            // Kung bukas ang detail panel, i-refresh ang feedback section nito
            if (crmDetailCurrentId) crmRenderDetailFeedback(crmDetailCurrentId);
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
    document.querySelectorAll('#crmCounters .crm-counter').forEach(b => b.addEventListener('click', () => crmSetFilter(b.dataset.filter)));
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

            // Mas eksakto ang susunod na hakbang galing sa stage (Initial / Customer / Final / Cutting)
            const pgNow = crmPg(id);
            if (pgNow && pgNow.phase >= 2) {
                tone = (CRM_PG_TONE[pgNow.tone] || CRM_PG_TONE.amber).box;
                icon = pgNow.phase === 5 ? 'fa-circle-check' : pgNow.tone === 'blue' ? 'fa-hourglass-half' : 'fa-file-pen';
                title = pgNow.phase === 5 ? 'Done: with Cutting' : `Next: ${pgNow.next}`;
                text = pgNow.note;
            }
            if (crmFb(id)) {
                tone = 'bg-red-50 border-red-200 text-red-900'; icon = 'fa-comment-dots'; title = 'Next: Work on the feedback';
                text = 'Open the 2D & Quotation page to revise and re-upload. The details are below.';
            }
            quotationBtn.innerHTML = crmFb(id)
                ? '<i class="fa-solid fa-comment-dots"></i> Open feedback'
                : '<i class="fa-solid fa-file-pen"></i> 2D &amp; Quotation';
            quotationBtn.classList.toggle('bg-red-600', !!crmFb(id));
            quotationBtn.classList.toggle('hover:bg-red-700', !!crmFb(id));
            quotationBtn.classList.toggle('bg-blue-700', !crmFb(id));
            quotationBtn.classList.toggle('hover:bg-blue-800', !crmFb(id));

            body.innerHTML = `<div class="space-y-3">
                <div class="rounded-xl border px-4 py-3 flex items-start gap-3 ${tone}">
                    <span class="w-9 h-9 rounded-lg bg-white/70 flex items-center justify-center shrink-0"><i class="fa-solid ${icon}"></i></span>
                    <div class="min-w-0"><p class="text-sm font-semibold">${title}</p><p class="text-xs opacity-80 mt-0.5">${crmEscapeHtml(text)}</p></div>
                </div>

                <div id="crmDetailFeedback"></div>

                ${crmStageDetail(id)}

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
            crmRenderDetailFeedback(id);
        } catch (e) {
            console.error('crmOpenDetailPanel:', e);
            body.innerHTML = `<p class="text-sm text-red-500 py-6 text-center">Connection error. Please try again.</p>`;
        }
    }

    // Feedback section sa detail panel (Cutting messages / mga ibinalik na slot)
    function crmRenderDetailFeedback(id) {
        const box = document.getElementById('crmDetailFeedback');
        if (!box || crmDetailCurrentId !== id) return;
        const fb = crmFb(id);
        if (!fb) { box.innerHTML = ''; return; }

        box.innerHTML = fb.entries.map(e => {
            const st = CRM_FB_STYLE[e.kind] || CRM_FB_STYLE.cutting;
            let items = '';
            if (e.kind === 'cutting') {
                items = (e.messages || []).map(m => `
                    <li class="px-4 py-2.5">
                        <p class="text-[12.5px] text-gray-800 whitespace-pre-line break-words">${crmEscapeHtml(m.message)}</p>
                        <p class="text-[10.5px] text-gray-400 mt-0.5"><i class="fa-regular fa-user mr-1"></i>${crmEscapeHtml(m.by)} · ${crmEscapeHtml(m.at || '')}</p>
                    </li>`).join('');
                if (e.count > (e.messages || []).length) items += `<li class="px-4 py-2 text-[11px] text-gray-400">+${e.count - e.messages.length} more on the 2D &amp; Quotation page</li>`;
            } else {
                items = (e.slots || []).map(s => `
                    <li class="px-4 py-2.5">
                        <p class="text-[11px] font-semibold text-gray-700"><i class="fa-solid fa-rotate-left text-[9px] mr-1 text-red-500"></i>${crmEscapeHtml(s.label)}</p>
                        <p class="text-[12.5px] text-gray-800 mt-0.5 whitespace-pre-line break-words">${s.remarks ? crmEscapeHtml(s.remarks) : '<span class="text-gray-400">No remarks given.</span>'}</p>
                    </li>`).join('') || '<li class="px-4 py-2.5 text-[12px] text-gray-500">Needs revision.</li>';
            }
            return `<section class="bg-white border border-red-200 rounded-xl overflow-hidden mb-3 last:mb-0">
                <div class="px-4 py-2.5 border-b border-red-100 bg-red-50/60 flex items-center justify-between gap-2">
                    <p class="text-[10.5px] font-bold uppercase tracking-wider text-red-700"><i class="fa-solid ${st.icon} mr-1"></i>${crmEscapeHtml(e.label)}${e.kind === 'cutting' ? ` · ${e.count}` : ''}</p>
                    ${e.at ? `<span class="text-[10.5px] text-red-700/70 whitespace-nowrap">${crmEscapeHtml(e.at)}</span>` : ''}
                </div>
                <ul class="divide-y divide-gray-100 max-h-56 overflow-y-auto overscroll-contain [scrollbar-width:thin]">${items}</ul>
            </section>`;
        }).join('');
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