<?php
// checkdesigner2dquotation.php
// Initial 2D & Quotation approval — DESIGNER HEAD only.
// MULTI-ATTACHMENT + PER-FILE REVIEW VERSION:
// lahat ng file ng bawat slot ay makikita at mabubuksan, at may sariling Approve / Revision ang bawat file.

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_DESIGNER];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

// Head lang — ang ibang designer staff ay hindi pwede rito.
$dchkAccountId = intval($_SESSION['account_id'] ?? 0);
$dchkStmt = $conn->prepare("SELECT id FROM noblerole WHERE id = ? AND role = ? AND position = ? LIMIT 1");
$dchkRole = ROLE_DESIGNER;
$dchkPos = POSITION_HEAD;
$dchkStmt->bind_param("iss", $dchkAccountId, $dchkRole, $dchkPos);
$dchkStmt->execute();
$dchkIsHead = (bool) $dchkStmt->get_result()->fetch_assoc();
$dchkStmt->close();

if (!$dchkIsHead) {
    http_response_code(403);
    exit('Access denied. This page is for the Designer Head only.');
}

$dchkAjaxUrl = BASE_URL . '/checkdesigner2dquotationajax';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Initial 2D Quotation Approval</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/ui-designer/css/crm/checkdesigner2dquotation.css?v=<?= @filemtime(ROOT_PATH . '/admin/ui-designer/css/crm/checkdesigner2dquotation.css') ?: 1 ?>">
</head>

<body class="bg-slate-100">
    <main class="ml-56 min-h-screen p-5 lg:p-7 bg-[#F5F6FA] overflow-x-hidden">

        <div class="max-w-6xl mx-auto">

            <!-- ═══ HEADER ═══ -->
            <section class="dchk-card overflow-hidden mb-4">
                <div class="dchk-hazard h-1.5"></div>
                <div class="p-5 flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-clipboard-check text-lg"></i></span>
                        <div class="min-w-0">
                            <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase">Design Management · Designer Head</p>
                            <h1 class="text-gray-900 text-lg font-semibold leading-tight flex flex-wrap items-center gap-2">
                                2D &amp; Quotation Approval
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border bg-amber-50 text-amber-700 border-amber-200">Initial</span>
                            </h1>
                            <p class="text-gray-400 text-[11px] mt-0.5">Open a submission, approve or send back each file, then submit your review.</p>
                        </div>
                    </div>

                    <!-- Counters = tabs -->
                    <div id="dchkTabs" class="grid grid-cols-2 sm:grid-cols-4 gap-2 w-full lg:w-auto">
                        <?php
                        $tabs = [
                            ['', 'All', 'text-gray-900'],
                            ['Waiting for Approval', 'Queuing', 'text-amber-700'],
                            ['For Revision', 'For Revision', 'text-red-600'],
                            ['Approved', 'Approved', 'text-emerald-700'],
                        ];
                        foreach ($tabs as [$val, $label, $cls]): ?>
                            <button type="button" data-status="<?= htmlspecialchars($val) ?>"
                                class="dchk-tab text-left rounded-xl border px-3.5 py-2 transition-colors lg:min-w-[110px]">
                                <span class="block text-[10px] font-semibold uppercase tracking-wide opacity-80"><?= $label ?></span>
                                <span class="dchk-tab-count block text-xl font-bold leading-tight tabular-nums <?= $cls ?>">0</span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="px-5 py-3 border-t border-gray-100 bg-gray-50/60 flex flex-wrap items-center gap-2">
                    <div class="relative flex-1 min-w-[200px] max-w-md">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[11px]"></i>
                        <input id="dchkSearch" type="text" placeholder="Search control no., client or contact…"
                            class="w-full h-9 bg-white border border-gray-200 rounded-lg pl-8 pr-8 text-xs outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-100">
                        <button type="button" id="dchkSearchClear" aria-label="Clear search"
                            class="hidden absolute right-1.5 top-1/2 -translate-y-1/2 inline-flex items-center justify-center w-6 h-6 rounded-md text-gray-400 hover:text-gray-700 hover:bg-gray-100"><i class="fa-solid fa-xmark text-xs leading-none"></i></button>
                    </div>
                    <span id="dchkCount" class="ml-auto text-[11px] text-gray-400"></span>
                </div>
            </section>

            <!-- ═══ LIST ═══ -->
            <div id="dchkTbody" class="dchk-scroll dchk-list space-y-2.5"></div>
        </div>

        <!-- ═══ REVIEW PANEL ═══ -->
        <div id="dchkOverlay" class="fixed inset-0 bg-black/30 hidden z-40" onclick="dchkClosePanel()"></div>

        <div id="dchkPanel" class="fixed top-0 right-0 h-full w-full max-w-xl bg-white shadow-2xl z-50 flex flex-col
                   translate-x-full transition-transform duration-300 ease-out">
            <div class="dchk-hazard h-1.5 shrink-0"></div>
            <div class="px-5 py-4 border-b border-gray-100 flex items-start justify-between gap-3 shrink-0">
                <div class="min-w-0">
                    <p class="text-[10px] text-amber-700 font-semibold tracking-[0.15em] uppercase">Initial submission review</p>
                    <h3 id="dchkModalControlNo" class="text-gray-900 font-mono font-semibold text-sm">—</h3>
                    <p id="dchkModalClient" class="text-[15px] font-semibold text-gray-900 truncate capitalize"></p>
                    <div id="dchkModalBadges" class="mt-1 flex flex-wrap items-center gap-1.5"></div>
                </div>
                <button type="button" onclick="dchkClosePanel()" aria-label="Close"
                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 shrink-0"><i class="fa-solid fa-xmark text-base leading-none"></i></button>
            </div>
            <div id="dchkModalBody" class="dchk-scroll px-5 py-4 overflow-y-auto flex-1 bg-slate-50/60"></div>
            <!-- Ang listahan ng files ang may sariling scroll para laging kita ang Submit button -->
            <div id="dchkModalFooter" class="px-5 py-4 bg-white border-t border-gray-100 shrink-0"></div>
        </div>

        <div id="crmToastContainer"
            class="fixed bottom-6 right-6 z-[9999] flex flex-col gap-2.5 pointer-events-none w-full max-w-sm px-4 sm:px-0">
        </div>

    </main>

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
                <span class="flex-1 leading-relaxed"></span>
                <button type="button" class="shrink-0 inline-flex items-center justify-center w-5 h-5 text-gray-400 hover:text-gray-600" aria-label="Close"><i class="fa-solid fa-xmark text-xs"></i></button>`;
            toast.querySelector('.flex-1').textContent = message;
            container.appendChild(toast);
            requestAnimationFrame(() => toast.classList.remove('translate-x-6', 'opacity-0', 'scale-95'));
            const remove = () => {
                toast.classList.add('translate-x-6', 'opacity-0', 'scale-95');
                setTimeout(() => toast.remove(), 300);
            };
            toast.querySelector('button').addEventListener('click', remove);
            if (duration > 0) setTimeout(remove, duration);
        }

        const DCHK_AJAX_URL = <?= json_encode($dchkAjaxUrl) ?>;
        const DCHK_POLL_INTERVAL_MS = 8000;
        const DCHK_VIEWED_KEY = 'dchkViewedIds';

        let dchkSearchTerm = '';
        let dchkStatusFilter = '';
        let dchkLastSignature = '';
        let dchkPollTimer = null;
        let dchkSearchDebounce = null;
        let dchkCurrentId = null;
        let dchkLastRows = [];
        // PER-FILE: { [fileId]: { slot, decision, remarks } } — mga file lang na hindi pa Approved
        let dchkDecisions = {};

        // ── helpers ──
        function dchkEscapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }

        function dchkGetViewedIds() {
            try {
                const raw = localStorage.getItem(DCHK_VIEWED_KEY);
                return raw ? new Set(JSON.parse(raw)) : new Set();
            } catch (e) { return new Set(); }
        }

        function dchkMarkViewed(id) {
            const viewed = dchkGetViewedIds();
            if (viewed.has(id)) return;
            viewed.add(id);
            try { localStorage.setItem(DCHK_VIEWED_KEY, JSON.stringify([...viewed])); } catch (e) { }
        }

        const dchkToDate = v => { if (!v) return null; const d = new Date(String(v).replace(' ', 'T')); return isNaN(d.getTime()) ? null : d; };

        function dchkFormatDate(value) {
            if (!value) return '—';
            const dt = dchkToDate(value);
            if (!dt) return value;
            return dt.toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
        }

        function dchkFormatDateTimeLong(value) {
            if (!value) return '—';
            const dt = dchkToDate(value);
            if (!dt) return value;
            return dt.toLocaleString('en-PH', { year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true });
        }

        // "3 days ago", "Today"
        function dchkAgo(value) {
            const dt = dchkToDate(value); if (!dt) return '';
            const a = new Date(); a.setHours(0, 0, 0, 0);
            const b = new Date(dt); b.setHours(0, 0, 0, 0);
            const d = Math.round((a - b) / 864e5);
            return d <= 0 ? 'Today' : d === 1 ? 'Yesterday' : `${d} days ago`;
        }
        // "in 12 days", "3 days overdue"
        function dchkDaysLeft(value) {
            const dt = dchkToDate(value); if (!dt) return null;
            const a = new Date(); a.setHours(0, 0, 0, 0);
            const b = new Date(dt); b.setHours(0, 0, 0, 0);
            const d = Math.round((b - a) / 864e5);
            if (d < 0) return { text: `${-d} day${d === -1 ? '' : 's'} overdue`, cls: 'text-red-600' };
            if (d === 0) return { text: 'Due today', cls: 'text-amber-700' };
            return { text: `in ${d} day${d === 1 ? '' : 's'}`, cls: d <= 7 ? 'text-amber-700' : 'text-gray-500' };
        }

        function dchkFormatCurrency(value) {
            const num = Number(value);
            if (!value || isNaN(num)) return '<span class="text-gray-300">—</span>';
            return '₱' + num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function dchkStatusBadge(status, compact = false) {
            const map = {
                'Approved': 'bg-emerald-50 text-emerald-700 border-emerald-200',
                'For Revision': 'bg-red-50 text-red-700 border-red-200',
                'Waiting for Approval': 'bg-amber-50 text-amber-700 border-amber-200',
            };
            const dotMap = { 'Approved': 'bg-emerald-500', 'For Revision': 'bg-red-500', 'Waiting for Approval': 'bg-amber-500' };
            const cls = map[status] || 'bg-gray-50 text-gray-600 border-gray-200';
            const dot = dotMap[status] || 'bg-gray-400';
            const size = compact ? 'px-2 py-0.5 text-[10px]' : 'px-2.5 py-0.5 text-[10.5px]';
            return `<span class="inline-flex items-center gap-1.5 ${size} rounded-full font-semibold border whitespace-nowrap ${cls}">
                <span class="w-1.5 h-1.5 rounded-full shrink-0 ${dot}"></span>${dchkEscapeHtml(status)}</span>`;
        }

        function dchkMiniChip(text, cls) {
            return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border whitespace-nowrap ${cls}">${text}</span>`;
        }

        function dchkStatusBadgeWithRow(row) {
            let out = dchkStatusBadge(row.status);
            if (row.status === 'Approved' && row.design_3d_stage === 'Waiting for Approval') {
                out += ' ' + dchkMiniChip('<i class="fa-solid fa-cube text-[8px] mr-1"></i>3D waiting', 'border-amber-200 bg-amber-50 text-amber-700');
            }
            if (row.status === 'Approved' && row.design_3d_stage === 'For Revision') {
                out += ' ' + dchkMiniChip('<i class="fa-solid fa-cube text-[8px] mr-1"></i>3D revision', 'border-red-200 bg-red-50 text-red-700');
            }
            if (row.is_late) {
                out += ' ' + dchkMiniChip('<i class="fa-solid fa-clock text-[8px] mr-1"></i>Late', 'border-red-200 bg-white text-red-700');
            }
            return out;
        }

        function dchkReviewBadge(status) {
            const map = {
                'Approved': 'bg-emerald-50 text-emerald-700 border-emerald-200',
                'For Revision': 'bg-red-50 text-red-700 border-red-200',
                'Pending': 'bg-gray-50 text-gray-500 border-gray-200',
            };
            const cls = map[status] || map['Pending'];
            return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border whitespace-nowrap ${cls}">${dchkEscapeHtml(status || 'Pending')}</span>`;
        }

        function dchkFileIcon(name) {
            return /\.(jpe?g|png|webp)$/i.test(name || '') ? 'fa-file-image text-violet-500' : 'fa-file-pdf text-red-500';
        }

        // ── MULTI-FILE: listahan ng links (floating dropdown) ──
        function dchkFileLinksHtml(files, linkClass = 'flex items-center gap-2 px-3 py-1.5 text-xs text-gray-700 hover:bg-amber-50 hover:text-amber-800 whitespace-nowrap', wrapperClass = '') {
            if (!files || !files.length) return '';
            const links = files.map((f, i) => `
                <a href="${dchkEscapeHtml(f.url)}" target="_blank" rel="noopener" title="${dchkEscapeHtml(f.name)}"
                    class="${linkClass}"><i class="fa-solid ${dchkFileIcon(f.name)} text-[11px]"></i><span class="truncate max-w-[220px]">${dchkEscapeHtml(f.name || (files.length > 1 ? `File ${i + 1}` : 'View File'))}</span></a>`).join('');
            return wrapperClass ? `<div class="${wrapperClass}">${links}</div>` : links;
        }

        // Registry ng files na naka-attach sa bawat chip (para di na kailangang i-encode sa DOM)
        let dchkFileRegistry = {};

        // Chip bawat slot: 1 file = direktang link; marami = dropdown na lumulutang (naka-attach sa <body>).
        const DCHK_SLOT_META = {
            '2d': { label: '2D', icon: 'fa-compass-drafting', cls: 'text-blue-700 bg-blue-50 border-blue-200 hover:bg-blue-100' },
            quotation: { label: 'Quotation', icon: 'fa-file-invoice-dollar', cls: 'text-emerald-700 bg-emerald-50 border-emerald-200 hover:bg-emerald-100' },
            '3d': { label: '3D', icon: 'fa-cube', cls: 'text-violet-700 bg-violet-50 border-violet-200 hover:bg-violet-100' },
        };
        function dchkFileLinks(files, uploaderName, uploaderRole, slot = '2d') {
            const m = DCHK_SLOT_META[slot];
            const base = 'inline-flex items-center gap-1.5 px-2.5 py-1 text-[11px] font-semibold border rounded-lg whitespace-nowrap';
            if (!files || !files.length) {
                return `<span class="${base} text-gray-300 bg-white border-dashed border-gray-200"><i class="fa-solid ${m.icon} text-[10px]"></i>${m.label} —</span>`;
            }
            const title = uploaderName ? `title="Uploaded by ${dchkEscapeHtml(uploaderName)} (${dchkEscapeHtml(uploaderRole)})"` : '';

            if (files.length === 1) {
                return `<a href="${dchkEscapeHtml(files[0].url)}" target="_blank" rel="noopener" ${title}
                    class="${base} ${m.cls}"><i class="fa-solid ${m.icon} text-[10px]"></i>${m.label}<i class="fa-solid fa-arrow-up-right-from-square text-[8px] opacity-60"></i></a>`;
            }

            const key = `k${Math.random().toString(36).slice(2, 9)}`;
            dchkFileRegistry[key] = files;

            return `
                <button type="button" data-file-toggle-key="${key}" onclick="dchkToggleFileDropdown(event, '${key}')" ${title}
                    class="${base} ${m.cls}">
                    <i class="fa-solid ${m.icon} text-[10px]"></i>${m.label} · ${files.length}
                    <i class="fa-solid fa-chevron-down text-[8px] opacity-60"></i>
                </button>`;
        }

        // ── Floating dropdown: hiwalay sa list, naka-attach sa <body>, positioned via getBoundingClientRect ──
        function dchkCloseFileDropdown() {
            const existing = document.getElementById('dchkFloatingDropdown');
            if (existing) existing.remove();
        }

        function dchkToggleFileDropdown(evt, key) {
            evt.stopPropagation();
            const btn = evt.currentTarget;
            const existing = document.getElementById('dchkFloatingDropdown');
            const wasOpenForThis = existing && existing.dataset.key === key;
            dchkCloseFileDropdown();
            if (wasOpenForThis) return;

            const files = dchkFileRegistry[key];
            if (!files || !files.length) return;

            const rect = btn.getBoundingClientRect();
            const dropdown = document.createElement('div');
            dropdown.id = 'dchkFloatingDropdown';
            dropdown.dataset.key = key;
            dropdown.className = 'dchk-scroll fixed z-[9998] min-w-[180px] max-h-64 overflow-y-auto bg-white border border-gray-200 rounded-xl shadow-lg py-1.5';
            dropdown.innerHTML = dchkFileLinksHtml(files);
            dropdown.addEventListener('click', e => e.stopPropagation());

            document.body.appendChild(dropdown);

            // i-position pagkatapos ma-render para makuha ang tunay na height (para sa flip-up kung kailangan)
            const dropdownHeight = dropdown.offsetHeight;
            const dropdownWidth = dropdown.offsetWidth;
            const spaceBelow = window.innerHeight - rect.bottom;
            const openUpward = spaceBelow < dropdownHeight + 8 && rect.top > dropdownHeight + 8;

            let top = openUpward ? (rect.top - dropdownHeight - 4) : (rect.bottom + 4);
            let left = rect.left;

            if (left + dropdownWidth > window.innerWidth - 8) left = window.innerWidth - dropdownWidth - 8;
            if (left < 8) left = 8;
            if (top < 8) top = 8;

            dropdown.style.top = `${top}px`;
            dropdown.style.left = `${left}px`;
        }

        document.addEventListener('click', dchkCloseFileDropdown);
        window.addEventListener('scroll', e => {
            const dd = document.getElementById('dchkFloatingDropdown');
            if (dd && dd.contains(e.target)) return;
            dchkCloseFileDropdown();
        }, true);
        window.addEventListener('resize', dchkCloseFileDropdown);

        // ── tabs ──
        function dchkInitTabs() {
            try { dchkStatusFilter = sessionStorage.getItem('dchkStatusFilter') ?? ''; } catch (e) { }
            document.querySelectorAll('.dchk-tab').forEach(btn => {
                btn.addEventListener('click', () => {
                    dchkStatusFilter = btn.dataset.status;
                    try { sessionStorage.setItem('dchkStatusFilter', dchkStatusFilter); } catch (e) { }
                    dchkLastSignature = '';
                    dchkRenderTabs();
                    dchkFetchList();
                });
            });
            dchkRenderTabs();
        }

        function dchkRenderTabs() {
            document.querySelectorAll('.dchk-tab').forEach(btn => {
                const active = btn.dataset.status === dchkStatusFilter;
                btn.classList.toggle('border-amber-600', active);
                btn.classList.toggle('bg-amber-50', active);
                btn.classList.toggle('ring-1', active);
                btn.classList.toggle('ring-amber-600', active);
                btn.classList.toggle('border-gray-200', !active);
                btn.classList.toggle('bg-white', !active);
                btn.classList.toggle('hover:bg-gray-50', !active);
            });
        }

        async function dchkFetchCounts() {
            try {
                const res = await fetch(`${DCHK_AJAX_URL}?action=list&q=&status=`);
                const data = await res.json();
                if (!data.success) return;

                const counts = { '': data.rows.length, 'Waiting for Approval': 0, 'Approved': 0, 'For Revision': 0 };
                data.rows.forEach(row => { if (counts[row.status] !== undefined) counts[row.status]++; });

                document.querySelectorAll('.dchk-tab').forEach(btn => {
                    const el = btn.querySelector('.dchk-tab-count');
                    if (el) el.textContent = counts[btn.dataset.status] ?? 0;
                });
            } catch (e) { console.error('dchkFetchCounts:', e); }
        }

        // ── list ──
        function dchkSkeletonRows(count = 4) {
            document.getElementById('dchkTbody').innerHTML = Array.from({ length: count }).map(() =>
                `<div class="h-[84px] rounded-xl bg-white border border-gray-200 animate-pulse"></div>`).join('');
        }

        function dchkEmptyState() {
            const message = dchkSearchTerm ? `No submissions match "${dchkEscapeHtml(dchkSearchTerm)}".`
                : dchkStatusFilter === 'Waiting for Approval' ? 'Nothing in the queue — all caught up.' : 'No submissions found.';
            return `<div class="bg-white border-2 border-dashed border-gray-200 rounded-xl px-6 py-12 text-center">
                <div class="w-12 h-12 mx-auto rounded-xl bg-gray-100 text-gray-400 flex items-center justify-center mb-2"><i class="fa-regular fa-folder-open"></i></div>
                <p class="text-sm font-semibold text-gray-700">${message}</p></div>`;
        }

        function dchkActionButton(row) {
            if (row.review_target && row.review_target !== 'none') {
                return `<button type="button" onclick="dchkOpenPanel(${row.id})"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 shadow-sm whitespace-nowrap">
                    <i class="fa-solid fa-clipboard-check"></i> ${row.review_target === '3d_only' ? 'Review 3D' : 'Review'}</button>`;
            }
            return `<button type="button" onclick="dchkOpenPanel(${row.id})"
                class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50 whitespace-nowrap">
                <i class="fa-regular fa-eye"></i> View</button>`;
        }

        function dchkRenderRows(rows) {
            const tbody = document.getElementById('dchkTbody');
            dchkLastRows = rows;
            dchkFileRegistry = {};
            dchkCloseFileDropdown();

            if (rows.length === 0) { tbody.innerHTML = dchkEmptyState(); return; }

            const viewed = dchkGetViewedIds();
            const barMap = { 'Approved': 'bg-emerald-500', 'For Revision': 'bg-red-500', 'Waiting for Approval': 'bg-amber-400' };

            tbody.innerHTML = rows.map(row => {
                const isViewed = viewed.has(row.id);
                const isActive = dchkCurrentId === row.id;
                const show3d = row.include_3d || row.design_3d_stage !== 'Locked';

                return `
                <article class="relative bg-white border rounded-xl overflow-hidden cursor-pointer transition ${isActive ? 'border-amber-500 ring-2 ring-amber-100' : 'border-gray-200 hover:border-gray-300 hover:shadow-sm'}"
                    onclick="dchkOpenPanel(${row.id})">
                    <span class="absolute left-0 top-0 bottom-0 w-1 ${barMap[row.status] || 'bg-gray-300'}"></span>
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-3 pl-5 pr-4 py-3.5">
                        <div class="min-w-0 flex-1 basis-[230px]">
                            <div class="flex flex-wrap items-center gap-1.5">
                                ${!isViewed ? '<span class="inline-flex items-center px-1.5 py-0.5 rounded text-[9.5px] font-bold uppercase tracking-wide bg-amber-500 text-white">New</span>' : ''}
                                <span class="font-mono text-[11px] font-semibold text-amber-700 whitespace-nowrap">${dchkEscapeHtml(row.control_no)}</span>
                                ${dchkStatusBadgeWithRow(row)}
                            </div>
                            <p class="mt-1 text-sm font-semibold text-gray-900 truncate capitalize">${dchkEscapeHtml(row.client_name)}</p>
                            <p class="text-[11px] text-gray-500">
                                <i class="fa-regular fa-calendar text-gray-300 mr-1"></i>${dchkFormatDate(row.submitted_at)}
                                ${row.submitted_at ? `<span class="text-gray-300 mx-1">·</span>${dchkAgo(row.submitted_at)}` : ''}
                                <span class="text-gray-300 mx-1">·</span><span class="font-semibold text-gray-700">${dchkFormatCurrency(row.contract_amount)}</span>
                            </p>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5" onclick="event.stopPropagation()">
                            ${dchkFileLinks(row.design_2d_files, row.design_2d_uploader_name, row.design_2d_uploaded_role, '2d')}
                            ${dchkFileLinks(row.quotation_files, row.quotation_uploader_name, row.quotation_uploaded_role, 'quotation')}
                            ${show3d ? dchkFileLinks(row.design_3d_files, row.design_3d_uploader_name, row.design_3d_uploaded_role, '3d') : ''}
                        </div>
                        <div class="ml-auto shrink-0" onclick="event.stopPropagation()">${dchkActionButton(row)}</div>
                    </div>
                </article>`;
            }).join('');
        }

        async function dchkFetchList({ silent = false } = {}) {
            if (!silent) dchkSkeletonRows();
            try {
                const url = `${DCHK_AJAX_URL}?action=list&q=${encodeURIComponent(dchkSearchTerm)}&status=${encodeURIComponent(dchkStatusFilter)}`;
                const res = await fetch(url);
                const data = await res.json();

                if (!data.success) {
                    if (!silent) crmShowToast(data.message || 'Failed to load submissions.', 'error');
                    return;
                }

                // kasama na ang bilang ng files sa signature para mag-refresh kapag nagbago ang attachments
                const signature = JSON.stringify(data.rows.map(r =>
                    `${r.id}:${r.status}:${r.design_3d_stage}:${(r.design_2d_files || []).length}:${(r.quotation_files || []).length}:${(r.design_3d_files || []).length}`
                )) + dchkStatusFilter;
                if (signature !== dchkLastSignature) {
                    dchkRenderRows(data.rows);
                    dchkLastSignature = signature;
                }

                document.getElementById('dchkCount').textContent = `${data.count} submission${data.count === 1 ? '' : 's'} found`;
                dchkFetchCounts();
            } catch (e) {
                console.error('dchkFetchList:', e);
                if (!silent) {
                    document.getElementById('dchkTbody').innerHTML = `<div class="bg-white border border-red-200 rounded-xl px-6 py-10 text-center text-xs text-red-600">Could not load submissions. <button type="button" onclick="dchkFetchList()" class="font-semibold underline">Retry</button></div>`;
                }
            }
        }

        function dchkStartPolling() {
            if (dchkPollTimer) clearInterval(dchkPollTimer);
            dchkPollTimer = setInterval(() => dchkFetchList({ silent: true }), DCHK_POLL_INTERVAL_MS);
        }

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                if (dchkPollTimer) clearInterval(dchkPollTimer);
            } else {
                dchkFetchList({ silent: true });
                dchkStartPolling();
            }
        });

        const dchkSearchInput = document.getElementById('dchkSearch');
        const dchkSearchClear = document.getElementById('dchkSearchClear');

        dchkSearchInput.addEventListener('input', function () {
            dchkSearchClear.classList.toggle('hidden', this.value.length === 0);
            clearTimeout(dchkSearchDebounce);
            const value = this.value;
            dchkSearchDebounce = setTimeout(() => {
                dchkSearchTerm = value.trim();
                dchkLastSignature = '';
                dchkFetchList();
            }, 350);
        });

        dchkSearchClear.addEventListener('click', () => {
            dchkSearchInput.value = '';
            dchkSearchClear.classList.add('hidden');
            dchkSearchTerm = '';
            dchkLastSignature = '';
            dchkFetchList();
            dchkSearchInput.focus();
        });

        // ── review panel ──
        function dchkDetailRow(label, value) {
            return `<div class="flex justify-between gap-3 py-2 border-b border-gray-100 text-[12.5px] last:border-b-0">
                <span class="text-gray-400 whitespace-nowrap">${label}</span>
                <span class="text-gray-800 font-medium text-right min-w-0 break-words">${value}</span></div>`;
        }

        function dchkTile(label, icon, value, sub = '') {
            return `<div class="bg-white border border-gray-200 rounded-xl px-3.5 py-2.5 min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-400"><i class="fa-solid ${icon} mr-1"></i>${label}</p>
                <p class="text-[13px] font-semibold text-gray-900 mt-0.5 truncate">${value}</p>
                ${sub ? `<p class="text-[10.5px] font-semibold">${sub}</p>` : ''}
            </div>`;
        }

        // Kasalukuyang review status ng isang slot (galing sa server)
        function dchkSlotSummary(label, slot, reviewStatus, remarks, files, name, role) {
            const m = DCHK_SLOT_META[slot];
            const count = (files || []).length;
            return `<div class="bg-white border border-gray-200 rounded-xl p-3 min-w-0">
                <div class="flex items-center gap-2">
                    <i class="fa-solid ${m.icon} text-xs text-gray-400"></i>
                    <span class="text-[12px] font-semibold text-gray-800">${label}</span>
                    <span class="text-[10.5px] text-gray-400">${count} file${count === 1 ? '' : 's'}</span>
                    <span class="ml-auto">${dchkReviewBadge(reviewStatus)}</span>
                </div>
                ${name ? `<p class="text-[10.5px] text-gray-400 mt-1"><i class="fa-solid fa-user-pen mr-1"></i>${dchkEscapeHtml(name)} (${dchkEscapeHtml(role)})</p>` : ''}
                ${remarks ? `<p class="text-[11.5px] text-red-700 mt-1.5 bg-red-50 border border-red-100 rounded-lg px-2.5 py-1.5 whitespace-pre-line">${dchkEscapeHtml(remarks)}</p>` : ''}
            </div>`;
        }

        // (Lumang helpers — iniwan para sa compatibility)
        function dchkDetailRowHighlight(label, value) { return dchkDetailRow(label, value); }
        function dchkFileReviewSummary(label, reviewStatus, remarks) { return dchkDetailRow(dchkEscapeHtml(label), dchkReviewBadge(reviewStatus) + (remarks ? `<span class="block text-xs text-red-700 mt-1 whitespace-pre-line">${dchkEscapeHtml(remarks)}</span>` : '')); }
        function dchkFileDetail(files, name, role) {
            if (!files || !files.length) return '—';
            return dchkFileLinksHtml(files, 'text-amber-700 hover:underline', 'flex flex-col items-end') + `<span class="text-gray-400 font-normal text-xs">(${dchkEscapeHtml(name)}, ${dchkEscapeHtml(role)})</span>`;
        }

        // ═══════════════════════════════════════════════════
        // PER-FILE DECISIONS
        // ═══════════════════════════════════════════════════
        function dchkFileDecisionRow(f, index, total) {
            const name = f.name || (total > 1 ? `File ${index + 1}` : 'View File');
            const link = `<a href="${dchkEscapeHtml(f.url)}" target="_blank" rel="noopener" title="${dchkEscapeHtml(f.name)}"
                class="flex items-center gap-2 min-w-0 flex-1 text-[12px] font-medium text-gray-800 hover:text-amber-700">
                <i class="fa-solid ${dchkFileIcon(f.name)} text-[12px] shrink-0"></i>
                <span class="truncate hover:underline">${dchkEscapeHtml(name)}</span>
                <i class="fa-solid fa-arrow-up-right-from-square text-[9px] text-gray-400 shrink-0"></i></a>`;

            // Naka-lock na ang file na approved na dati
            if (f.id > 0 && f.review_status === 'Approved') {
                return `<div class="dchk-file flex items-center gap-2" data-decision="approved">
                    ${link}<span class="text-[10.5px] font-semibold text-emerald-700 whitespace-nowrap"><i class="fa-solid fa-lock text-[9px] mr-1"></i>Approved before</span></div>`;
            }

            // Lumang data na walang row sa files table (id = 0) — hindi pwedeng i-review per file
            if (!f.id) {
                return `<div class="dchk-file flex items-center gap-2 bg-red-50/60">
                    ${link}<span class="text-[10px] text-red-600 whitespace-nowrap">Legacy file – run backfill SQL</span></div>`;
            }

            return `<div class="dchk-file" data-file-row="${f.id}" data-decision="">
                <div class="flex items-center gap-2">
                    ${link}
                    <div class="dchk-seg">
                        <button type="button" data-fbtn="approve" onclick="dchkSetFileDecision(${f.id}, 'Approved')"><i class="fa-solid fa-check text-[10px]"></i>Approve</button>
                        <button type="button" data-fbtn="revise" onclick="dchkSetFileDecision(${f.id}, 'For Revision')"><i class="fa-solid fa-rotate-left text-[10px]"></i>Revision</button>
                    </div>
                </div>
                <textarea data-fremarks placeholder="What needs to be revised? (required)" rows="2"
                    oninput="dchkSetFileRemarks(${f.id}, this.value)"
                    class="dchk-remarks hidden mt-2"></textarea>
            </div>`;
        }

        function dchkSlotDecisionCard(slot, label, files) {
            files = files || [];
            const total = files.length;
            const pending = files.filter(f => f.id > 0 && f.review_status !== 'Approved').length;
            const metaKey = slot === 'design_2d' ? '2d' : slot === 'design_3d' ? '3d' : 'quotation';
            const m = DCHK_SLOT_META[metaKey];
            const approveAll = pending > 1
                ? `<button type="button" onclick="dchkApproveAll('${slot}')"
                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-emerald-700 hover:text-emerald-800 hover:underline whitespace-nowrap"><i class="fa-solid fa-check-double text-[10px]"></i>Approve all</button>` : '';
            const body = total
                ? files.map((f, i) => dchkFileDecisionRow(f, i, total)).join('')
                : `<p class="text-gray-300 text-xs">No file</p>`;

            return `<div class="border border-gray-200 rounded-xl p-3 bg-gray-50/60" data-decision-slot="${slot}">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <p class="text-[12px] font-semibold text-gray-700 flex items-center gap-1.5"><i class="fa-solid ${m.icon} text-gray-400 text-[11px]"></i>${label}
                        ${total > 1 ? `<span class="text-[10.5px] text-gray-400 font-normal">${total} files</span>` : ''}</p>${approveAll}
                </div>
                <div class="dchk-scroll dchk-file-list space-y-1.5 pr-1">${body}</div>
            </div>`;
        }

        function dchkSetFileDecision(fileId, decision) {
            const d = dchkDecisions[fileId];
            if (!d) return;
            d.decision = decision;
            if (decision === 'Approved') d.remarks = '';

            const row = document.querySelector(`[data-file-row="${fileId}"]`);
            if (!row) return;
            const textarea = row.querySelector('[data-fremarks]');
            const revise = decision === 'For Revision';

            row.dataset.decision = revise ? 'revise' : 'approved';
            textarea.classList.toggle('hidden', !revise);
            if (!revise) textarea.value = '';
            else setTimeout(() => textarea.focus(), 0);

            dchkUpdateSubmitState();
        }

        function dchkSetFileRemarks(fileId, value) {
            if (!dchkDecisions[fileId]) return;
            dchkDecisions[fileId].remarks = value;
            dchkUpdateSubmitState();
        }

        function dchkApproveAll(slot) {
            Object.entries(dchkDecisions).forEach(([fileId, d]) => {
                if (d.slot === slot) dchkSetFileDecision(fileId, 'Approved');
            });
        }

        function dchkUpdateSubmitState() {
            const btn = document.getElementById('dchkSubmitReviewBtn');
            if (!btn) return;
            const all = Object.values(dchkDecisions);
            const ok = all.every(d => {
                if (!d.decision) return false;
                if (d.decision === 'For Revision' && d.remarks.trim() === '') return false;
                return true;
            });
            btn.disabled = !ok;

            // progress line
            const decided = all.filter(d => d.decision).length;
            const missingRemarks = all.filter(d => d.decision === 'For Revision' && d.remarks.trim() === '').length;
            const prog = document.getElementById('dchkDecisionProgress');
            if (prog) {
                prog.innerHTML = ok
                    ? `<span class="text-emerald-700"><i class="fa-solid fa-circle-check mr-1"></i>All ${all.length} file${all.length === 1 ? '' : 's'} reviewed.</span>`
                    : missingRemarks && decided === all.length
                        ? `<span class="text-red-600"><i class="fa-solid fa-pen mr-1"></i>Add remarks to ${missingRemarks} file${missingRemarks === 1 ? '' : 's'} for revision.</span>`
                        : `<span class="text-gray-500">${decided} of ${all.length} file${all.length === 1 ? '' : 's'} reviewed</span>`;
            }
            const bar = document.getElementById('dchkDecisionBar');
            if (bar) bar.style.width = all.length ? `${Math.round(decided / all.length * 100)}%` : '0%';

            // isara ang confirm kapag nagbago ang desisyon
            dchkAskConfirm(false);
        }

        function dchkBuildDecisions(slots) {
            dchkDecisions = {};
            slots.forEach(([slot, files]) => (files || []).forEach(f => {
                if (f.id > 0 && f.review_status !== 'Approved') {
                    dchkDecisions[f.id] = { slot, decision: null, remarks: '' };
                }
            }));
        }

        function dchkCollectDecisions() {
            return Object.entries(dchkDecisions).map(([id, d]) => ({
                id: Number(id), decision: d.decision, remarks: (d.remarks || '').trim()
            }));
        }

        // Inline confirm bago i-submit ang review (walang popup)
        let dchkPendingSubmitFn = null;
        function dchkAskConfirm(show) {
            const idle = document.getElementById('dchkSubmitIdle');
            const conf = document.getElementById('dchkSubmitConfirm');
            if (!idle || !conf) return;
            if (show) {
                const all = Object.values(dchkDecisions);
                const nA = all.filter(d => d.decision === 'Approved').length;
                const nR = all.filter(d => d.decision === 'For Revision').length;
                document.getElementById('dchkConfirmText').innerHTML = nR
                    ? `<b class="text-emerald-700">${nA} approved</b> · <b class="text-red-700">${nR} for revision</b> — the submission will go back to the designer.`
                    : `<b class="text-emerald-700">All ${nA} file${nA === 1 ? '' : 's'} approved.</b>`;
            }
            idle.classList.toggle('hidden', !!show);
            conf.classList.toggle('hidden', !show);
        }
        async function dchkConfirmYes(btn) {
            if (!dchkPendingSubmitFn) return;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting…';
            await window[dchkPendingSubmitFn]();
            if (document.body.contains(btn)) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-check"></i> Yes, submit'; }
        }

        function dchkRenderFooter(record) {
            const footer = document.getElementById('dchkModalFooter');
            // Ang mga desisyon ay nasa scrollable body; sa footer ang progress + Submit para laging kita
            const area = document.getElementById('dchkDecisionArea') || footer;
            const listClass = 'space-y-2.5 mb-3';
            const submitBtn = (fn) => {
                dchkPendingSubmitFn = fn;
                return `
                <div class="mb-2.5">
                    <div class="flex items-center justify-between text-[11px] font-semibold mb-1"><span id="dchkDecisionProgress"></span></div>
                    <div class="h-1.5 rounded-full bg-gray-100 overflow-hidden"><div id="dchkDecisionBar" class="h-full bg-amber-600 rounded-full transition-all duration-300" style="width:0%"></div></div>
                </div>
                <div id="dchkSubmitIdle" class="flex items-center justify-end gap-2">
                    <button type="button" onclick="dchkClosePanel()" class="px-3.5 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                    <button type="button" id="dchkSubmitReviewBtn" onclick="dchkAskConfirm(true)" disabled
                        class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 shadow-sm disabled:bg-gray-100 disabled:text-gray-400 disabled:shadow-none disabled:cursor-not-allowed">
                        <i class="fa-solid fa-paper-plane"></i> Submit review</button>
                </div>
                <div id="dchkSubmitConfirm" class="hidden rounded-xl border border-amber-200 bg-amber-50 p-3">
                    <p class="text-[12.5px] font-semibold text-amber-900"><i class="fa-solid fa-circle-question mr-1"></i>Submit this review?</p>
                    <p id="dchkConfirmText" class="text-[11.5px] text-gray-700 mt-1"></p>
                    <div class="flex justify-end gap-2 mt-2.5">
                        <button type="button" onclick="dchkAskConfirm(false)" class="px-3 py-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">Go back</button>
                        <button type="button" onclick="dchkConfirmYes(this)" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold text-white bg-amber-700 hover:bg-amber-800 rounded-lg"><i class="fa-solid fa-check"></i> Yes, submit</button>
                    </div>
                </div>`;
            };

            if (record.review_target === '3d_only') {
                dchkBuildDecisions([['design_3d', record.design_3d_files]]);
                area.innerHTML = `
                    <p class="text-[10.5px] font-bold uppercase tracking-wider text-gray-500 mb-2"><i class="fa-solid fa-gavel mr-1 text-amber-700"></i>Your decision · 3D only</p>
                    <div class="${listClass}">${dchkSlotDecisionCard('design_3d', '3D File', record.design_3d_files)}</div>
                    <p class="text-[11px] text-gray-500 rounded-lg bg-violet-50 border border-violet-100 px-2.5 py-1.5"><i class="fa-solid fa-circle-info mr-1 text-violet-500"></i>Sending any 3D file back for revision will also reopen the 2D file for rework, since 3D is derived from it.</p>`;
                footer.innerHTML = submitBtn('dchkSubmitReview3d');
                dchkUpdateSubmitState();
                return;
            }

            if (record.review_target === 'none') {
                dchkDecisions = {};
                dchkPendingSubmitFn = null;
                const reviewedLine = record.reviewed_at
                    ? `<p class="text-[11px] text-gray-400 mt-1"><i class="fa-regular fa-clock mr-1"></i>Reviewed ${dchkFormatDateTimeLong(record.reviewed_at)}</p>` : '';
                footer.innerHTML = `
                    <div class="flex items-center justify-between gap-3">
                        <div class="min-w-0"><div class="flex flex-wrap gap-1">${dchkStatusBadgeWithRow(record)}</div>${reviewedLine}</div>
                        <button type="button" onclick="dchkClosePanel()"
                            class="px-3.5 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">Close</button>
                    </div>`;
                return;
            }

            const slots = [['design_2d', record.design_2d_files], ['quotation', record.quotation_files]];
            if (record.include_3d) slots.push(['design_3d', record.design_3d_files]);
            dchkBuildDecisions(slots);

            area.innerHTML = `
                <p class="text-[10.5px] font-bold uppercase tracking-wider text-gray-500 mb-2"><i class="fa-solid fa-gavel mr-1 text-amber-700"></i>Your decision</p>
                <div class="${listClass}">
                    ${dchkSlotDecisionCard('design_2d', '2D File', record.design_2d_files)}
                    ${dchkSlotDecisionCard('quotation', 'Quotation File', record.quotation_files)}
                    ${record.include_3d ? dchkSlotDecisionCard('design_3d', '3D File', record.design_3d_files) : ''}
                </div>
                ${record.include_3d ? `<p class="text-[11px] text-gray-500 rounded-lg bg-violet-50 border border-violet-100 px-2.5 py-1.5"><i class="fa-solid fa-circle-info mr-1 text-violet-500"></i>Sending any 3D file back for revision will also send the 2D files back, since 3D is derived from them.</p>` : ''}`;
            footer.innerHTML = submitBtn('dchkSubmitReview');

            dchkUpdateSubmitState();
        }

        async function dchkOpenPanel(id) {
            const overlay = document.getElementById('dchkOverlay');
            const panel = document.getElementById('dchkPanel');
            const body = document.getElementById('dchkModalBody');
            const footer = document.getElementById('dchkModalFooter');
            dchkCurrentId = id;
            dchkCloseFileDropdown();

            dchkMarkViewed(id);
            if (dchkLastRows.length) dchkRenderRows(dchkLastRows);

            document.getElementById('dchkModalControlNo').textContent = 'Loading…';
            document.getElementById('dchkModalClient').textContent = '';
            document.getElementById('dchkModalBadges').innerHTML = '';
            body.innerHTML = `<div class="space-y-3">${Array.from({ length: 4 }).map(() => `<div class="h-14 rounded-xl bg-gray-100 animate-pulse"></div>`).join('')}</div>`;
            footer.innerHTML = '';

            overlay.classList.remove('hidden');
            requestAnimationFrame(() => panel.classList.remove('translate-x-full'));

            try {
                const res = await fetch(`${DCHK_AJAX_URL}?action=detail&id=${id}`);
                const data = await res.json();
                if (dchkCurrentId !== id) return;

                if (!data.success) {
                    body.innerHTML = `<p class="text-sm text-red-500 py-6 text-center">${dchkEscapeHtml(data.message || 'Record not found.')}</p>`;
                    return;
                }

                const r = data.record;
                document.getElementById('dchkModalControlNo').textContent = r.control_no;
                document.getElementById('dchkModalClient').textContent = r.client_name || '';
                document.getElementById('dchkModalBadges').innerHTML = dchkStatusBadgeWithRow(r);
                const showsThreeD = r.include_3d || r.design_3d_stage !== 'Locked';
                const due = dchkDaysLeft(r.target_completion_date);

                body.innerHTML = `
                    <div class="space-y-3">
                        <div class="grid grid-cols-2 gap-2">
                            ${dchkTile('Contract amount', 'fa-peso-sign', dchkFormatCurrency(r.contract_amount))}
                            ${dchkTile('Target completion', 'fa-flag', dchkFormatDate(r.target_completion_date), due ? `<span class="${due.cls}">${due.text}</span>` : '')}
                        </div>

                        <section class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                            <p class="px-4 py-2.5 border-b border-gray-100 text-[10.5px] font-bold uppercase tracking-wider text-gray-500"><i class="fa-solid fa-user mr-1 text-amber-700"></i>Client &amp; project</p>
                            <div class="px-4 py-1">
                                ${dchkDetailRow('Client', `<span class="capitalize">${dchkEscapeHtml(r.client_name)}</span>`)}
                                ${dchkDetailRow('Contact number', r.contact_number ? `<a href="tel:${dchkEscapeHtml(String(r.contact_number).replace(/[^\d+]/g, ''))}" class="text-amber-700 hover:underline">${dchkEscapeHtml(r.contact_number)}</a>` : '—')}
                                ${dchkDetailRow('Project type', `<span class="capitalize">${dchkEscapeHtml(r.project_type) || '—'}</span>`)}
                                ${dchkDetailRow('Submitted', dchkFormatDateTimeLong(r.submitted_at))}
                            </div>
                        </section>

                        <section>
                            <p class="text-[10.5px] font-bold uppercase tracking-wider text-gray-500 mb-2"><i class="fa-solid fa-clipboard-list mr-1 text-amber-700"></i>Current review status</p>
                            <div class="space-y-2">
                                ${dchkSlotSummary('2D', '2d', r.design_2d_review_status, r.design_2d_remarks, r.design_2d_files, r.design_2d_uploader_name, r.design_2d_uploaded_role)}
                                ${dchkSlotSummary('Quotation', 'quotation', r.quotation_review_status, r.quotation_remarks, r.quotation_files, r.quotation_uploader_name, r.quotation_uploaded_role)}
                                ${showsThreeD ? dchkSlotSummary('3D', '3d', r.design_3d_review_status, r.design_3d_remarks, r.design_3d_files, r.design_3d_uploader_name, r.design_3d_uploaded_role) : ''}
                            </div>
                        </section>

                        <section id="dchkDecisionArea" class="pt-1"></section>
                    </div>`;

                dchkRenderFooter(r);
            } catch (e) {
                console.error('dchkOpenPanel:', e);
                body.innerHTML = `<p class="text-sm text-red-500 py-6 text-center">Connection error. Please try again.</p>`;
            }
        }

        function dchkClosePanel() {
            const overlay = document.getElementById('dchkOverlay');
            document.getElementById('dchkPanel').classList.add('translate-x-full');
            setTimeout(() => overlay.classList.add('hidden'), 300);
            dchkCurrentId = null;
            dchkCloseFileDropdown();
            if (dchkLastRows.length) dchkRenderRows(dchkLastRows);
        }

        document.addEventListener('keydown', e => { if (e.key === 'Escape') { dchkCloseFileDropdown(); dchkClosePanel(); } });

        async function dchkPost(formData, logName) {
            try {
                const res = await fetch(DCHK_AJAX_URL, { method: 'POST', body: formData });
                const data = await res.json();

                if (!data.success) {
                    crmShowToast(data.message || 'Something went wrong.', 'error');
                    return;
                }

                crmShowToast(data.message || 'Review saved.');
                dchkClosePanel();
                dchkLastSignature = '';
                dchkFetchList();
            } catch (e) {
                console.error(logName, e);
                crmShowToast('Connection error. Please try again.', 'error');
            }
        }

        async function dchkSubmitReview() {
            if (!dchkCurrentId) return;
            const fd = new FormData();
            fd.append('action', 'review');
            fd.append('id', dchkCurrentId);
            fd.append('file_decisions', JSON.stringify(dchkCollectDecisions()));
            await dchkPost(fd, 'dchkSubmitReview:');
        }

        async function dchkSubmitReview3d() {
            if (!dchkCurrentId) return;
            const fd = new FormData();
            fd.append('action', 'review_3d');
            fd.append('id', dchkCurrentId);
            fd.append('file_decisions', JSON.stringify(dchkCollectDecisions()));
            await dchkPost(fd, 'dchkSubmitReview3d:');
        }

        dchkInitTabs();
        dchkFetchList().then(dchkStartPolling);
    </script>
</body>

</html>