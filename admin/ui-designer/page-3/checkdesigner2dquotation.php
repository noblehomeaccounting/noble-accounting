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
</head>

<body class="bg-slate-100">
    <main class="ml-56 min-h-screen p-8 overflow-x-hidden">

        <div class="max-w-6xl mx-auto">

            <!-- Header -->
            <div class="mb-4">
                <div class="mb-3">
                    <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase mb-0.5">Design
                        Management</p>
                    <h1 class="text-gray-900 text-xl font-semibold">
                        2D &amp; Quotation Approval
                        <span
                            class="align-middle ml-2 inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wide border bg-amber-50 text-amber-700 border-amber-200">Initial</span>
                    </h1>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="relative w-full sm:w-64 min-w-0">
                        <input id="dchkSearch" type="text" placeholder="Search control no. / client / contact"
                            class="w-full pl-8 pr-5 py-1.5 text-xs border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-amber-100 focus:border-amber-600 bg-white transition-colors">
                        <i
                            class="fa-brands fa-sistrix absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                        <button type="button" id="dchkSearchClear"
                            class="hidden absolute right-2 top-1/2 -translate-y-1/2 text-gray-300 hover:text-gray-500 text-base leading-none w-4 h-4">&times;</button>
                    </div>

                    <div id="dchkTabs" class="flex items-center gap-1.5">
                        <?php
                        $tabs = [['', 'All'], ['Waiting for Approval', 'Queuing'], ['Approved', 'Approved'], ['For Revision', 'For Revision']];
                        foreach ($tabs as [$val, $label]): ?>
                            <button type="button" data-status="<?= htmlspecialchars($val) ?>"
                                class="dchk-tab px-3 py-1.5 text-xs font-semibold rounded-lg border transition-colors flex items-center gap-1.5">
                                <?= $label ?>
                                <span
                                    class="dchk-tab-count inline-flex items-center justify-center min-w-[1.15rem] h-[1.15rem] px-1 rounded-full text-[10px] font-bold bg-black/10">0</span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead>
                            <tr
                                class="bg-gray-50 border-b border-gray-200 text-left text-[10px] uppercase tracking-wide text-gray-500">
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Control No.</th>
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Client</th>
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">2D File</th>
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Quotation File</th>
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">3D File</th>
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Contract Amount</th>
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Submitted</th>
                                <th class="px-4 py-2.5 font-semibold whitespace-nowrap">Status</th>
                                <th class="px-4 py-2.5 font-semibold text-right whitespace-nowrap">Action</th>
                            </tr>
                        </thead>
                        <tbody id="dchkTbody" class="divide-y divide-gray-100"></tbody>
                    </table>
                </div>
            </div>

            <p id="dchkCount" class="text-[11px] text-gray-400 mt-2.5"></p>
        </div>

        <!-- Right-side review panel -->
        <div id="dchkOverlay" class="fixed inset-0 bg-black/30 hidden z-40" onclick="dchkClosePanel()"></div>

        <div id="dchkPanel" class="fixed top-0 right-0 h-full w-full max-w-xl bg-white shadow-2xl z-50 flex flex-col
                   translate-x-full transition-transform duration-300 ease-out">
            <div class="px-5 py-4 border-b border-gray-100 flex items-start justify-between shrink-0">
                <div>
                    <p class="text-[10px] text-amber-700 font-semibold tracking-[0.15em] uppercase mb-0.5">Initial
                        Submission Review</p>
                    <h3 id="dchkModalControlNo" class="text-gray-900 font-mono font-semibold text-sm">—</h3>
                </div>
                <button type="button" onclick="dchkClosePanel()"
                    class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
            </div>
            <div id="dchkModalBody" class="px-5 py-4 overflow-y-auto space-y-0.5 flex-1"></div>
            <!-- Walang scroll dito; ang listahan ng files ang may sariling scroll para laging kita ang Submit button -->
            <div id="dchkModalFooter" class="px-5 py-4 bg-gray-50 border-t border-gray-100 shrink-0"></div>
        </div>

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
            toast.className = `pointer-events-auto flex items-start gap-2.5 border rounded-lg shadow-lg px-4 py-3 text-sm ${palette.wrap}
                translate-x-6 opacity-0 scale-95 transition-all duration-300 ease-out`;
            toast.innerHTML = `
                <span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold ${palette.icon}">${palette.symbol}</span>
                <span class="flex-1 leading-relaxed">${message}</span>
                <button type="button" class="shrink-0 text-current opacity-50 hover:opacity-100 text-base leading-none" aria-label="Close">&times;</button>`;
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

        function dchkFormatDate(value) {
            if (!value) return '—';
            const dt = new Date(value.replace(' ', 'T'));
            if (isNaN(dt.getTime())) return value;
            return dt.toLocaleString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
        }

        function dchkFormatDateTimeLong(value) {
            if (!value) return '—';
            const dt = new Date(value.replace(' ', 'T'));
            if (isNaN(dt.getTime())) return value;
            return dt.toLocaleString('en-PH', { year: 'numeric', month: 'long', day: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true });
        }

        function dchkFormatCurrency(value) {
            const num = Number(value);
            if (!value || isNaN(num)) return '<span class="text-gray-300">—</span>';
            return '₱' + num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function dchkStatusBadge(status, compact = false) {
            const map = {
                'Approved': 'bg-green-50 text-green-700 border-green-200',
                'For Revision': 'bg-red-50 text-red-700 border-red-200',
                'Waiting for Approval': 'bg-amber-50 text-amber-700 border-amber-200',
            };
            const dotMap = { 'Approved': 'bg-green-500', 'For Revision': 'bg-red-500', 'Waiting for Approval': 'bg-amber-500' };
            const cls = map[status] || 'bg-gray-50 text-gray-600 border-gray-200';
            const dot = dotMap[status] || 'bg-gray-400';
            const size = compact ? 'px-2 py-0.5 text-[10px]' : 'px-2.5 py-1 text-[11px]';
            return `<span class="inline-flex items-center gap-1.5 ${size} rounded-full font-semibold border whitespace-nowrap ${cls}">
                <span class="w-1.5 h-1.5 rounded-full shrink-0 ${dot}"></span>${dchkEscapeHtml(status)}</span>`;
        }

        function dchkStatusBadgeWithRow(row) {
            let out = dchkStatusBadge(row.status);
            if (row.status === 'Approved' && row.design_3d_stage === 'Waiting for Approval') {
                out += ` <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border border-amber-200 bg-amber-50 text-amber-700 whitespace-nowrap ml-1">3D Waiting</span>`;
            }
            if (row.status === 'Approved' && row.design_3d_stage === 'For Revision') {
                out += ` <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border border-red-200 bg-red-50 text-red-700 whitespace-nowrap ml-1">3D Revision</span>`;
            }
            if (row.is_late) {
                out += ` <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border border-red-200 bg-white text-red-700 whitespace-nowrap ml-1">Late</span>`;
            }
            return out;
        }

        function dchkReviewBadge(status) {
            const map = {
                'Approved': 'bg-green-50 text-green-700 border-green-200',
                'For Revision': 'bg-red-50 text-red-700 border-red-200',
                'Pending': 'bg-gray-50 text-gray-500 border-gray-200',
            };
            const cls = map[status] || map['Pending'];
            return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border whitespace-nowrap ${cls}">${dchkEscapeHtml(status || 'Pending')}</span>`;
        }

        // ── MULTI-FILE: listahan ng links (ginagamit ng detail panel — inline, walang dropdown) ──
        function dchkFileLinksHtml(files, linkClass = 'block px-3 py-1.5 text-xs text-amber-700 hover:bg-amber-50 whitespace-nowrap', wrapperClass = '') {
            if (!files || !files.length) return '';
            const links = files.map((f, i) => `
                <a href="${dchkEscapeHtml(f.url)}" target="_blank" rel="noopener" title="${dchkEscapeHtml(f.name)}"
                    class="${linkClass}">${files.length > 1 ? `View File ${i + 1}` : 'View File'}</a>`).join('');
            return wrapperClass ? `<div class="${wrapperClass}">${links}</div>` : links;
        }

        // Registry ng files na naka-attach sa bawat "N Files" button (para di na kailangang i-encode sa DOM)
        let dchkFileRegistry = {};

        // Para sa table cell: 1 file = plain link; marami = "N Files ▾" na compact button,
        // ang listahan ay lumulutang (fixed position, naka-attach sa <body>) para hindi na-cut ng overflow-x-auto ng table.
        function dchkFileLinks(files, uploaderName, uploaderRole) {
            if (!files || !files.length) return `<span class="text-gray-300">—</span>`;

            const uploaderLine = `<p class="text-[10px] text-gray-400 whitespace-nowrap">${dchkEscapeHtml(uploaderName)} (${dchkEscapeHtml(uploaderRole)})</p>`;

            if (files.length === 1) {
                return `<a href="${dchkEscapeHtml(files[0].url)}" target="_blank" rel="noopener"
                    class="text-amber-700 hover:underline font-medium text-xs whitespace-nowrap">View File</a>${uploaderLine}`;
            }

            const key = `k${Math.random().toString(36).slice(2, 9)}`;
            dchkFileRegistry[key] = files;

            return `
                <button type="button" data-file-toggle-key="${key}" onclick="dchkToggleFileDropdown(event, '${key}')"
                    class="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium text-amber-700 border border-amber-200 bg-amber-50 rounded-md hover:bg-amber-100 whitespace-nowrap">
                    ${files.length} Files
                    <i class="fa-solid fa-chevron-down text-[9px]"></i>
                </button>
                ${uploaderLine}`;
        }

        // ── Floating dropdown: hiwalay sa table, naka-attach sa <body>, positioned via getBoundingClientRect ──
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
            dropdown.className = 'fixed z-[9998] min-w-[130px] bg-white border border-gray-200 rounded-lg shadow-lg py-1';
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

            // wag lalampas sa kanang gilid ng viewport
            if (left + dropdownWidth > window.innerWidth - 8) {
                left = window.innerWidth - dropdownWidth - 8;
            }
            if (left < 8) left = 8;
            if (top < 8) top = 8;

            dropdown.style.top = `${top}px`;
            dropdown.style.left = `${left}px`;
        }

        document.addEventListener('click', dchkCloseFileDropdown);
        window.addEventListener('scroll', dchkCloseFileDropdown, true);
        window.addEventListener('resize', dchkCloseFileDropdown);

        // ── tabs ──
        function dchkInitTabs() {
            document.querySelectorAll('.dchk-tab').forEach(btn => {
                btn.addEventListener('click', () => {
                    dchkStatusFilter = btn.dataset.status;
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
                btn.classList.toggle('bg-amber-700', active);
                btn.classList.toggle('text-white', active);
                btn.classList.toggle('border-amber-700', active);
                btn.classList.toggle('bg-white', !active);
                btn.classList.toggle('text-gray-600', !active);
                btn.classList.toggle('border-gray-300', !active);
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

        // ── table ──
        function dchkSkeletonRows(count = 5) {
            document.getElementById('dchkTbody').innerHTML = Array.from({ length: count }).map(() => `
                <tr>${Array.from({ length: 9 }).map(() => `<td class="px-4 py-3"><div class="h-3 rounded bg-gray-100 animate-pulse"></div></td>`).join('')}</tr>`).join('');
        }

        function dchkEmptyState() {
            const message = dchkSearchTerm ? `No submissions match "${dchkEscapeHtml(dchkSearchTerm)}".` : 'No submissions found.';
            return `<tr><td colspan="9" class="p-0">
                <div class="flex flex-col items-center justify-center gap-2 py-10 text-center">
                    <p class="text-gray-400 text-xs">${message}</p>
                </div></td></tr>`;
        }

        function dchkActionButton(row) {
            if (row.review_target && row.review_target !== 'none') {
                return `<button type="button" onclick="dchkOpenPanel(${row.id})"
                    class="px-3 py-1.5 text-xs font-medium text-white bg-amber-700 rounded-lg hover:bg-amber-800 transition-colors whitespace-nowrap">Review</button>`;
            }
            return `<button type="button" onclick="dchkOpenPanel(${row.id})"
                class="px-3 py-1.5 text-xs font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors whitespace-nowrap">View</button>`;
        }

        function dchkRenderRows(rows) {
            const tbody = document.getElementById('dchkTbody');
            dchkLastRows = rows;
            dchkFileRegistry = {};
            dchkCloseFileDropdown();

            if (rows.length === 0) { tbody.innerHTML = dchkEmptyState(); return; }

            const viewed = dchkGetViewedIds();

            tbody.innerHTML = rows.map(row => {
                const isViewed = viewed.has(row.id);
                const isActive = dchkCurrentId === row.id;

                let rowBg = '';
                if (isActive) rowBg = 'bg-amber-100';
                else if (!isViewed) rowBg = 'bg-amber-50/30';

                const accent = isActive ? 'border-l-4 border-l-amber-600 pl-3' : 'border-l-4 border-l-transparent pl-3';
                const dot = isViewed ? '' : `<span class="inline-block w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0" title="Not yet viewed"></span>`;
                const viewedLabel = isViewed ? `<span class="text-[10px] text-gray-400 whitespace-nowrap">Viewed</span>` : '';

                return `
                <tr class="hover:bg-amber-50/40 transition-colors cursor-pointer ${rowBg}" onclick="dchkOpenPanel(${row.id})">
                    <td class="pr-4 py-2.5 ${accent}">
                        <div class="flex items-center gap-1.5">
                            ${dot}
                            <span class="font-mono text-[11px] font-semibold text-amber-700 whitespace-nowrap">${dchkEscapeHtml(row.control_no)}</span>
                        </div>
                        ${viewedLabel ? `<div class="mt-0.5">${viewedLabel}</div>` : ''}
                    </td>
                    <td class="px-4 py-2.5 text-gray-800">${dchkEscapeHtml(row.client_name)}</td>
                    <td class="px-4 py-2.5 whitespace-nowrap" onclick="event.stopPropagation()">${dchkFileLinks(row.design_2d_files, row.design_2d_uploader_name, row.design_2d_uploaded_role)}</td>
                    <td class="px-4 py-2.5 whitespace-nowrap" onclick="event.stopPropagation()">${dchkFileLinks(row.quotation_files, row.quotation_uploader_name, row.quotation_uploaded_role)}</td>
                    <td class="px-4 py-2.5 whitespace-nowrap" onclick="event.stopPropagation()">${(row.include_3d || row.design_3d_stage !== 'Locked')
                        ? dchkFileLinks(row.design_3d_files, row.design_3d_uploader_name, row.design_3d_uploaded_role)
                        : '<span class="text-gray-300 text-xs">Not yet</span>'}</td>
                    <td class="px-4 py-2.5 text-gray-800 font-medium whitespace-nowrap">${dchkFormatCurrency(row.contract_amount)}</td>
                    <td class="px-4 py-2.5 text-gray-500 whitespace-nowrap">${dchkFormatDate(row.submitted_at)}</td>
                    <td class="px-4 py-2.5">${dchkStatusBadgeWithRow(row)}</td>
                    <td class="px-4 py-2.5 text-right" onclick="event.stopPropagation()">${dchkActionButton(row)}</td>
                </tr>`;
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
                if (!silent) crmShowToast('Connection error while fetching submissions.', 'error');
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
            return `<div class="flex justify-between gap-3 py-2 border-b border-gray-100 text-[13px] last:border-b-0">
                <span class="text-gray-400 whitespace-nowrap">${label}</span>
                <span class="text-gray-800 font-medium text-right">${value}</span></div>`;
        }

        function dchkDetailRowHighlight(label, value) {
            return `<div class="flex justify-between items-center gap-3 py-2.5 px-3 my-1 rounded-lg bg-slate-50 border border-slate-300 text-[13px]">
                <span class="text-slate-600 font-medium">${label}</span>
                <span class="text-slate-900 font-semibold text-right">${value}</span></div>`;
        }

        // Slot-level summary. Ang remarks ay galing sa per-file remarks ("File 2: ...").
        function dchkFileReviewSummary(label, reviewStatus, remarks) {
            const remarksHtml = remarks
                ? `<p class="text-xs text-red-700 mt-1 max-w-[260px] ml-auto text-right whitespace-pre-line">${dchkEscapeHtml(remarks)}</p>` : '';
            return `<div class="flex items-start justify-between gap-3 py-2 border-b border-gray-100 text-[13px] last:border-b-0">
                <span class="text-gray-400 whitespace-nowrap pt-0.5">${dchkEscapeHtml(label)}</span>
                <div class="text-right">${dchkReviewBadge(reviewStatus)}${remarksHtml}</div></div>`;
        }

        // Detail panel: lahat ng files + uploader (naka-stack pa rin dito, ok lang kasi may sarili itong scroll)
        function dchkFileDetail(files, name, role) {
            if (!files || !files.length) return '—';
            const links = dchkFileLinksHtml(files, 'text-amber-700 hover:underline', 'flex flex-col items-end');
            return `${links}<span class="text-gray-400 font-normal text-xs">(${dchkEscapeHtml(name)}, ${dchkEscapeHtml(role)})</span>`;
        }

        // ═══════════════════════════════════════════════════
        // PER-FILE DECISIONS
        // ═══════════════════════════════════════════════════
        const DCHK_BTN_BASE = 'px-2.5 py-1 text-[11px] font-medium rounded-md border transition-colors whitespace-nowrap';

        function dchkFileDecisionRow(f, index, total) {
            const linkText = total > 1 ? `View File ${index + 1}` : 'View File';
            const link = `<a href="${dchkEscapeHtml(f.url)}" target="_blank" rel="noopener" title="${dchkEscapeHtml(f.name)}"
                class="text-amber-700 hover:underline text-xs font-medium whitespace-nowrap">${linkText}</a>`;

            // Naka-lock na ang file na approved na dati
            if (f.id > 0 && f.review_status === 'Approved') {
                return `<div class="flex items-center justify-between gap-2 py-1.5 px-2 rounded-md bg-green-50/60">
                    ${link}${dchkReviewBadge('Approved')}</div>`;
            }

            // Lumang data na walang row sa files table (id = 0) — hindi pwedeng i-review per file
            if (!f.id) {
                return `<div class="flex items-center justify-between gap-2 py-1.5 px-2 rounded-md bg-red-50/60">
                    ${link}<span class="text-[10px] text-red-600">Legacy file – run backfill SQL</span></div>`;
            }

            return `<div class="rounded-md border border-gray-200 bg-white p-2" data-file-row="${f.id}">
                <div class="flex items-center justify-between gap-2">
                    ${link}
                    <div class="flex items-center gap-1.5">
                        <button type="button" data-fbtn="approve" onclick="dchkSetFileDecision(${f.id}, 'Approved')"
                            class="${DCHK_BTN_BASE} border-gray-300 text-gray-600 hover:bg-gray-50">Approve</button>
                        <button type="button" data-fbtn="revise" onclick="dchkSetFileDecision(${f.id}, 'For Revision')"
                            class="${DCHK_BTN_BASE} border-gray-300 text-gray-600 hover:bg-gray-50">Revision</button>
                    </div>
                </div>
                <textarea data-fremarks placeholder="What needs to be revised?" rows="2"
                    oninput="dchkSetFileRemarks(${f.id}, this.value)"
                    class="hidden mt-2 w-full text-xs border border-gray-300 rounded-lg px-2.5 py-1.5 focus:outline-none focus:ring-2 focus:ring-red-100 focus:border-red-500"></textarea>
            </div>`;
        }

        function dchkSlotDecisionCard(slot, label, files) {
            files = files || [];
            const total = files.length;
            const pending = files.filter(f => f.id > 0 && f.review_status !== 'Approved').length;
            const countLabel = total > 1 ? ` <span class="text-[10px] text-gray-400 font-normal">(${total} files)</span>` : '';
            const approveAll = pending > 1
                ? `<button type="button" onclick="dchkApproveAll('${slot}')"
                    class="text-[11px] font-medium text-green-700 hover:underline whitespace-nowrap">Approve all</button>` : '';
            const body = total
                ? files.map((f, i) => dchkFileDecisionRow(f, i, total)).join('')
                : `<span class="text-gray-300 text-xs">No file</span>`;

            return `<div class="border border-gray-200 rounded-lg p-2.5" data-decision-slot="${slot}">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <p class="text-xs font-semibold text-gray-600">${label}${countLabel}</p>${approveAll}
                </div>
                <div class="space-y-1.5">${body}</div>
            </div>`;
        }

        function dchkSetFileDecision(fileId, decision) {
            const d = dchkDecisions[fileId];
            if (!d) return;
            d.decision = decision;
            if (decision === 'Approved') d.remarks = '';

            const row = document.querySelector(`[data-file-row="${fileId}"]`);
            if (!row) return;
            const approveBtn = row.querySelector('[data-fbtn="approve"]');
            const reviseBtn = row.querySelector('[data-fbtn="revise"]');
            const textarea = row.querySelector('[data-fremarks]');

            const approved = decision === 'Approved';
            ['bg-green-700', 'text-white', 'border-green-700', 'hover:bg-green-800'].forEach(k => approveBtn.classList.toggle(k, approved));
            ['text-gray-600', 'border-gray-300', 'hover:bg-gray-50'].forEach(k => approveBtn.classList.toggle(k, !approved));

            const revise = decision === 'For Revision';
            ['bg-red-700', 'text-white', 'border-red-700', 'hover:bg-red-800'].forEach(k => reviseBtn.classList.toggle(k, revise));
            ['text-gray-600', 'border-gray-300', 'hover:bg-gray-50'].forEach(k => reviseBtn.classList.toggle(k, !revise));

            textarea.classList.toggle('hidden', !revise);
            if (!revise) textarea.value = '';

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
            btn.disabled = !Object.values(dchkDecisions).every(d => {
                if (!d.decision) return false;
                if (d.decision === 'For Revision' && d.remarks.trim() === '') return false;
                return true;
            });
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

        function dchkRenderFooter(record) {
            const footer = document.getElementById('dchkModalFooter');
            const listClass = 'space-y-2.5 mb-2.5 max-h-[45vh] overflow-y-auto pr-1';
            const submitBtn = (fn) => `
                <div class="flex items-center justify-end gap-2">
                    <button type="button" id="dchkSubmitReviewBtn" onclick="${fn}()" disabled
                        class="px-3.5 py-1.5 text-xs font-medium text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors">Submit Review</button>
                </div>`;

            if (record.review_target === '3d_only') {
                dchkBuildDecisions([['design_3d', record.design_3d_files]]);
                footer.innerHTML = `
                    <div class="${listClass}">${dchkSlotDecisionCard('design_3d', '3D File', record.design_3d_files)}</div>
                    ${submitBtn('dchkSubmitReview3d')}
                    <p class="text-[11px] text-gray-400 mt-2">Sending any 3D file back for revision will also reopen the 2D file for rework, since 3D is derived from it.</p>`;
                dchkUpdateSubmitState();
                return;
            }

            if (record.review_target === 'none') {
                dchkDecisions = {};
                const reviewedLine = record.reviewed_at
                    ? `<p class="text-[11px] text-gray-400">Reviewed ${dchkFormatDateTimeLong(record.reviewed_at)}</p>` : '';
                footer.innerHTML = `
                    <div class="flex items-center justify-between gap-3">
                        <div>${dchkStatusBadgeWithRow(record)}${reviewedLine}</div>
                        <button type="button" onclick="dchkClosePanel()"
                            class="px-3.5 py-1.5 text-xs font-medium text-gray-600 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">Close</button>
                    </div>`;
                return;
            }

            const slots = [['design_2d', record.design_2d_files], ['quotation', record.quotation_files]];
            if (record.include_3d) slots.push(['design_3d', record.design_3d_files]);
            dchkBuildDecisions(slots);

            footer.innerHTML = `
                <div class="${listClass}">
                    ${dchkSlotDecisionCard('design_2d', '2D File', record.design_2d_files)}
                    ${dchkSlotDecisionCard('quotation', 'Quotation File', record.quotation_files)}
                    ${record.include_3d ? dchkSlotDecisionCard('design_3d', '3D File', record.design_3d_files) : ''}
                </div>
                ${submitBtn('dchkSubmitReview')}
                ${record.include_3d ? `<p class="text-[11px] text-gray-400 mt-2">Sending any 3D file back for revision will also send the 2D files back, since 3D is derived from them.</p>` : ''}`;

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
            body.innerHTML = `<div class="space-y-2 py-1">${Array.from({ length: 6 }).map(() => `<div class="h-4 rounded bg-gray-100 animate-pulse"></div>`).join('')}</div>`;
            footer.innerHTML = '';

            overlay.classList.remove('hidden');
            requestAnimationFrame(() => panel.classList.remove('translate-x-full'));

            try {
                const res = await fetch(`${DCHK_AJAX_URL}?action=detail&id=${id}`);
                const data = await res.json();

                if (!data.success) {
                    body.innerHTML = `<p class="text-sm text-red-500 py-6 text-center">${dchkEscapeHtml(data.message || 'Record not found.')}</p>`;
                    return;
                }

                const r = data.record;
                document.getElementById('dchkModalControlNo').textContent = r.control_no;
                const showsThreeD = r.include_3d || r.design_3d_stage !== 'Locked';

                body.innerHTML = [
                    dchkDetailRow('Client', dchkEscapeHtml(r.client_name)),
                    dchkDetailRow('Contact Number', dchkEscapeHtml(r.contact_number)),
                    dchkDetailRow('Project Type', dchkEscapeHtml(r.project_type) || '—'),
                    dchkDetailRow('Contract Amount', dchkFormatCurrency(r.contract_amount)),
                    dchkDetailRowHighlight('Target Completion Date', dchkFormatDate(r.target_completion_date)),
                    dchkDetailRow('2D File', dchkFileDetail(r.design_2d_files, r.design_2d_uploader_name, r.design_2d_uploaded_role)),
                    dchkDetailRow('Quotation File', dchkFileDetail(r.quotation_files, r.quotation_uploader_name, r.quotation_uploaded_role)),
                    showsThreeD ? dchkDetailRow('3D File', dchkFileDetail(r.design_3d_files, r.design_3d_uploader_name, r.design_3d_uploaded_role)) : '',
                    dchkDetailRow('Submitted', dchkFormatDateTimeLong(r.submitted_at)),
                    dchkFileReviewSummary('2D Review', r.design_2d_review_status, r.design_2d_remarks),
                    dchkFileReviewSummary('Quotation Review', r.quotation_review_status, r.quotation_remarks),
                    showsThreeD ? dchkFileReviewSummary('3D Review', r.design_3d_review_status, r.design_3d_remarks) : '',
                ].join('');

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