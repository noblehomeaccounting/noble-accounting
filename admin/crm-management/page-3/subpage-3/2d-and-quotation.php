<?php
// 2d-and-quotation.php  — INITIAL step (walang contract dito; nasa Final na)
// MULTI-ATTACHMENT VERSION + CUSTOMER REVIEW step

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SALES, ROLE_DESIGNER];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$qError = '';
$currentUserId = intval($_SESSION['account_id'] ?? 0);

// ⚠️ Adjust this if your session stores the role under a different key
$currentUserRole = $_SESSION['role'] ?? '';
$isSales = ($currentUserRole === ROLE_SALES);
$ownerColumn = $isSales ? 'sales_staff_id' : 'designer_id';

$inquiryId = intval($_GET['id'] ?? 0);
$inquiry = null;

if ($inquiryId <= 0) {
    $qError = 'Missing or invalid inquiry reference.';
} else {
    $stmt = $conn->prepare("
        SELECT id, control_no, client_name, address, contact_number, status, deadline
        FROM noblecrminquiry
        WHERE id = ? AND {$ownerColumn} = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $inquiryId, $currentUserId);
    $stmt->execute();
    $inquiry = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$inquiry) {
        $qError = 'Inquiry not found, or not assigned to you.';
    } elseif (!in_array($inquiry['status'], ['In Progress', 'Approved', 'For Revision'], true)) {
        $qError = 'The site visit must be completed before 2D and Quotation can be submitted.';
    }
}

$crmBackUrl = $isSales ? (BASE_URL . '/crmsaleslist') : (BASE_URL . '/crmdesigner');

$qDeadlineIsOverdue = false;
if (empty($qError) && !empty($inquiry['deadline'])) {
    $qLatestStmt = $conn->prepare("
        SELECT status, is_late
        FROM noblecrm_2dquotation
        WHERE inquiry_id = ? AND stage = 'Initial'
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ");
    $qLatestStmt->bind_param("i", $inquiryId);
    $qLatestStmt->execute();
    $qLatestEntry = $qLatestStmt->get_result()->fetch_assoc();
    $qLatestStmt->close();

    if ($qLatestEntry && in_array($qLatestEntry['status'], ['Approved', 'Waiting for Approval'], true)) {
        // Already submitted — trust the flag saved at submit time.
        $qDeadlineIsOverdue = (bool) ($qLatestEntry['is_late'] ?? 0);
    } else {
        // Nothing submitted yet — overdue only if today is already past the deadline.
        $qDeadlineTs = strtotime($inquiry['deadline']);
        $qDeadlineIsOverdue = $qDeadlineTs < strtotime('today');
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2D and Quotation</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/crm-management/css/crm/crm2dquotation.css?v=<?= @filemtime(ROOT_PATH . '/admin/crm-management/css/crm/crm2dquotation.css') ?: 1 ?>">
</head>

<body class="bg-gray-100 font-['Barlow_Condensed']">
    <main class="ml-56 min-h-screen p-5 lg:p-7 bg-[#F5F6FA] overflow-x-hidden">

        <div class="max-w-6xl mx-auto">

            <!-- ═══ HEADER ═══ -->
            <section class="q2d-card overflow-hidden mb-4">
                <div class="q2d-hazard h-1.5"></div>
                <div class="p-5 flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-compass-drafting text-lg"></i></span>
                        <div class="min-w-0">
                            <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase">CRM · 2D &amp; Quotation</p>
                            <h1 class="text-gray-900 text-lg font-semibold leading-tight flex flex-wrap items-center gap-2">
                                Initial 2D &amp; Quotation
                                <span id="q2dStageBadge" class="hidden px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-amber-100 text-amber-800 border border-amber-300"></span>
                            </h1>
                            <?php if (empty($qError) && $inquiry): ?>
                                <p class="mt-0.5 text-[12px] text-gray-500 flex flex-wrap items-center gap-x-2">
                                    <span class="font-mono font-semibold text-amber-700"><?= htmlspecialchars($inquiry['control_no']) ?></span>
                                    <span class="text-gray-300">·</span>
                                    <span class="capitalize font-medium text-gray-700"><?= htmlspecialchars($inquiry['client_name']) ?></span>
                                    <?php if (!empty($inquiry['deadline']) && $qDeadlineIsOverdue): ?>
                                        <span class="text-gray-300">·</span>
                                        <span class="text-red-600 font-semibold"><i class="fa-solid fa-triangle-exclamation mr-0.5"></i>Overdue</span>
                                    <?php endif; ?>
                                </p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" id="q2dFeedbackBtn" onclick="q2dToggleFeedbackSidebar()"
                            class="relative inline-flex items-center gap-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg px-3.5 py-2 hover:bg-gray-50">
                            <i class="fa-solid fa-comment-dots text-amber-700"></i> Feedback
                            <span id="q2dFeedbackBadge"
                                class="hidden absolute -top-1.5 -right-1.5 min-w-[18px] h-[18px] px-1 rounded-full bg-red-600 text-white text-[9px] font-bold items-center justify-center leading-none">0</span>
                        </button>
                        <a href="<?= htmlspecialchars($crmBackUrl) ?>"
                            class="inline-flex items-center gap-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg px-3.5 py-2 hover:bg-gray-50">
                            <i class="fa-solid fa-arrow-left"></i> Back to list
                        </a>
                    </div>
                </div>
                <?php if (empty($qError)): ?>
                    <div id="q2dStepper" class="px-5 pb-4"></div>
                <?php endif; ?>
            </section>

            <?php if (!empty($qError)): ?>
                <div class="q2d-card px-6 py-10 text-center">
                    <span class="w-12 h-12 mx-auto rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center mb-3"><i class="fa-solid fa-triangle-exclamation text-lg"></i></span>
                    <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($qError) ?></p>
                    <a href="<?= htmlspecialchars($crmBackUrl) ?>"
                        class="mt-4 inline-flex items-center gap-2 text-xs font-semibold text-white bg-amber-700 hover:bg-amber-800 rounded-lg px-4 py-2">
                        <i class="fa-solid fa-arrow-left"></i> Back to list
                    </a>
                </div>
            <?php else: ?>

                <!-- Everything below is rendered/refreshed by JS from crm2dquotationajax.php?action=state -->
                <div id="q2dRoot">
                    <div class="grid lg:grid-cols-3 gap-4">
                        <div class="lg:col-span-2 space-y-3">
                            <div class="h-16 rounded-xl bg-white border border-gray-200 animate-pulse"></div>
                            <div class="grid sm:grid-cols-2 gap-3">
                                <div class="h-56 rounded-xl bg-white border border-gray-200 animate-pulse"></div>
                                <div class="h-56 rounded-xl bg-white border border-gray-200 animate-pulse"></div>
                            </div>
                        </div>
                        <div class="h-48 rounded-xl bg-white border border-gray-200 animate-pulse"></div>
                    </div>
                </div>

            <?php endif; ?>

            <div class="mt-5 text-[11px] text-gray-400 flex flex-wrap justify-between gap-2">
                <span>Generated on <?= date('F d, Y g:i A') ?></span>
                <span id="q2dUpdatedAt"><i class="fa-solid fa-rotate mr-1"></i>Live · refreshes every few seconds</span>
            </div>

        </div>

        <!-- Feedback sidebar -->
        <div id="q2dFeedbackOverlay" onclick="q2dCloseFeedbackSidebar()"
            class="fixed inset-0 bg-black/30 z-[9998] opacity-0 pointer-events-none transition-opacity duration-300">
        </div>

        <aside id="q2dFeedbackSidebar"
            class="fixed top-0 right-0 h-full w-full max-w-sm bg-white shadow-2xl z-[9999] translate-x-full transition-transform duration-300 ease-out flex flex-col">
            <div class="q2d-hazard h-1.5 shrink-0"></div>
            <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-gray-100">
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.15em] text-amber-700">From Cutting</p>
                    <h2 class="text-sm font-semibold text-gray-900">Feedback</h2>
                </div>
                <button type="button" onclick="q2dCloseFeedbackSidebar()"
                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 shrink-0" aria-label="Close"><i class="fa-solid fa-xmark text-base leading-none"></i></button>
            </div>
            <div id="q2dFeedbackSidebarBody" class="flex-1 overflow-y-auto px-5 py-4">
                <p class="text-sm text-gray-400 italic">No feedback yet.</p>
            </div>
        </aside>

        <!-- Files sidebar — dito lumalabas ang lahat ng files ng isang slot (2D / Quotation / 3D) -->
        <div id="q2dFilesOverlay" onclick="q2dCloseFilesSidebar()"
            class="fixed inset-0 bg-black/30 z-[9998] opacity-0 pointer-events-none transition-opacity duration-300">
        </div>

        <aside id="q2dFilesSidebar"
            class="fixed top-0 right-0 h-full w-full max-w-sm bg-white shadow-2xl z-[9999] translate-x-full transition-transform duration-300 ease-out flex flex-col">
            <div class="q2d-hazard h-1.5 shrink-0"></div>
            <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-gray-100">
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-[0.15em] text-amber-700">Attachments</p>
                    <h2 id="q2dFilesSidebarTitle" class="text-sm font-semibold text-gray-900">Files</h2>
                </div>
                <button type="button" onclick="q2dCloseFilesSidebar()"
                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 shrink-0" aria-label="Close"><i class="fa-solid fa-xmark text-base leading-none"></i></button>
            </div>
            <div id="q2dFilesSidebarBody" class="flex-1 overflow-y-auto px-5 py-4">
                <p class="text-sm text-gray-400 italic">No files.</p>
            </div>
        </aside>

        <div id="crmToastContainer"
            class="fixed bottom-6 right-6 z-[9999] flex flex-col gap-2 pointer-events-none w-full max-w-sm px-4 sm:px-0">
        </div>

        <?php if (empty($qError)): ?>

            <?php include ROOT_PATH . '/admin/crm-management/page-3/subpage-3/feedback2d.php'; ?>

            <script>
                const Q2D_AJAX_URL = <?= json_encode(BASE_URL . '/crm2dquotationajax') ?>;
                const Q2D_FINAL_URL = <?= json_encode(BASE_URL . '/crm2dquotationfinal?id=' . $inquiryId) ?>;
                // ⚠️ Palitan kung iba ang route ng customer review page mo
                const Q2D_CUSTOMER_URL = <?= json_encode(BASE_URL . '/crm2dcustomerreview?id=' . $inquiryId) ?>;
                const Q2D_INQUIRY_ID = <?= (int) $inquiryId ?>;
                const Q2D_IS_SALES = <?= json_encode($isSales) ?>;

                const Q2D_POLL_INTERVAL_MS = 8000;
                const Q2D_MAX_FILES = 10;                  // dapat kapareho ng Q2D_MAX_FILES_PER_SLOT sa PHP
                const Q2D_MAX_BYTES = 15 * 1024 * 1024;    // 15MB bawat file

                let q2dPollTimer = null;
                let q2dLastSignature = '';

                // MULTI-FILE: mga bagong napiling file (hindi pa na-upload) bawat slot.
                let q2dSelected = { '2d': [], quotation: [], '3d': [] };
                let q2dObjectUrls = { '2d': [], quotation: [], '3d': [] };

                function q2dHasPending() {
                    return Object.values(q2dSelected).some(arr => arr.length > 0);
                }

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

                function q2dEscapeHtml(str) {
                    const div = document.createElement('div');
                    div.textContent = str ?? '';
                    return div.innerHTML;
                }

                // ═══════════════════ UI HELPERS ═══════════════════
                const Q2D_TONES = {
                    green: 'bg-emerald-50 text-emerald-700 border-emerald-200',
                    amber: 'bg-amber-50 text-amber-700 border-amber-200',
                    red: 'bg-red-50 text-red-700 border-red-200',
                    blue: 'bg-blue-50 text-blue-700 border-blue-200',
                    gray: 'bg-gray-100 text-gray-600 border-gray-200',
                };
                function q2dChip(text, tone = 'gray', icon = '') {
                    return `<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-semibold border whitespace-nowrap ${Q2D_TONES[tone] || Q2D_TONES.gray}">${icon ? `<i class="fa-solid ${icon} text-[9px]"></i>` : ''}${q2dEscapeHtml(text)}</span>`;
                }
                // Kulay batay sa status label galing sa server
                function q2dStatusTone(label) {
                    const l = String(label || '').toLowerCase();
                    if (l.includes('approved')) return 'green';
                    if (l.includes('revision') || l.includes('reject')) return 'red';
                    if (l.includes('waiting') || l.includes('pending') || l.includes('submitted')) return 'blue';
                    if (l.includes('draft')) return 'gray';
                    return 'amber';
                }
                const Q2D_TONE_ICON = { green: 'fa-circle-check', red: 'fa-rotate-left', blue: 'fa-hourglass-half', gray: 'fa-pen', amber: 'fa-circle-info' };
                const Q2D_CALLOUT = {
                    green: 'bg-emerald-50 border-emerald-200 text-emerald-900',
                    red: 'bg-red-50 border-red-200 text-red-900',
                    blue: 'bg-blue-50 border-blue-200 text-blue-900',
                    amber: 'bg-amber-50 border-amber-200 text-amber-900',
                    gray: 'bg-white border-gray-200 text-gray-800',
                };
                function q2dCallout(tone, icon, title, text, extra = '') {
                    return `
                    <div class="rounded-xl border px-4 py-3.5 mb-4 flex flex-wrap items-center gap-3 ${Q2D_CALLOUT[tone] || Q2D_CALLOUT.gray}">
                        <span class="w-9 h-9 rounded-lg bg-white/70 flex items-center justify-center shrink-0"><i class="fa-solid ${icon}"></i></span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold">${title}</p>
                            ${text ? `<p class="text-xs opacity-80 mt-0.5">${text}</p>` : ''}
                        </div>
                        ${extra}
                    </div>`;
                }

                // Detect kung ang status/label ay talagang "Approved" para malagyan ng
                // checkmark icon — hindi lang basta text badge.
                function q2dIsApproved(label) {
                    return typeof label === 'string' && label.trim().toLowerCase() === 'approved';
                }
                function q2dApprovedIcon() {
                    return '<i class="fa-solid fa-circle-check text-emerald-600 mr-1"></i>';
                }

                function q2dLateBadge() {
                    return q2dChip('Late submission', 'red', 'fa-clock');
                }

                function q2dInputId(slot) {
                    if (slot === '2d') return 'design_2d_pdf';
                    if (slot === 'quotation') return 'quotation_pdf';
                    return 'design_3d_file';
                }

                function q2dSectionLabel(text) {
                    return `<h2 class="q2d-eyebrow mb-3 flex items-center gap-2"><span class="w-1 h-3.5 rounded bg-amber-600"></span>${text}</h2>`;
                }

                // ── Files sidebar: bawat slot (2D / Quotation / 3D) ay button na lang na
                // nagpapakita ng bilang ng files. Pag-click, doon lumalabas ang buong listahan
                // sa slide-out sidebar.
                const Q2D_SLOT_LABELS = { '2d': '2D', quotation: 'Quotation', '3d': '3D' };
                const Q2D_SLOT_META = {
                    '2d': { icon: 'fa-compass-drafting', box: 'bg-blue-50 text-blue-600' },
                    quotation: { icon: 'fa-file-invoice-dollar', box: 'bg-emerald-50 text-emerald-600' },
                    '3d': { icon: 'fa-cube', box: 'bg-violet-50 text-violet-600' },
                };
                let q2dFilesRegistry = {}; // { slot: fileData } — pinopopulate tuwing nag-re-render

                function q2dFileIcon(name) {
                    return /\.(jpe?g|png|webp)$/i.test(name || '') ? 'fa-file-image text-violet-500' : 'fa-file-pdf text-red-500';
                }

                function q2dFilesSidebarListHtml(fileData) {
                    const files = q2dGetFilesArray(fileData);
                    if (!files.length) return '<p class="text-sm text-gray-400 italic">No files.</p>';

                    const list = files.map((f, i) => `
                        <a href="${q2dEscapeHtml(f.url)}" target="_blank" rel="noopener" title="${q2dEscapeHtml(f.name)}"
                            class="flex items-center gap-3 rounded-xl border border-gray-200 px-3 py-2.5 mb-2 hover:border-amber-600 hover:bg-amber-50/40 transition-colors min-w-0">
                            <span class="w-8 h-8 rounded-lg bg-gray-50 flex items-center justify-center shrink-0"><i class="fa-solid ${q2dFileIcon(f.name)}"></i></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[13px] font-medium text-gray-900 truncate">${q2dEscapeHtml(f.name)}</span>
                                <span class="block text-[10.5px] text-gray-400">File ${i + 1} of ${files.length}</span>
                            </span>
                            <i class="fa-solid fa-arrow-up-right-from-square text-[11px] text-gray-400"></i>
                        </a>
                    `).join('');

                    const uploadedLine = (fileData && fileData.uploaded_by_name)
                        ? `<p class="text-[11px] text-gray-500 mt-3 pt-3 border-t border-gray-100"><i class="fa-solid fa-user-pen mr-1 text-gray-400"></i>Uploaded by ${q2dEscapeHtml(fileData.uploaded_by_name)} (${q2dEscapeHtml(fileData.uploaded_role_label)})</p>`
                        : '';

                    return `<div>${list}</div>${uploadedLine}`;
                }

                function q2dOpenFilesSidebar(slot) {
                    const fileData = q2dFilesRegistry[slot];
                    const label = Q2D_SLOT_LABELS[slot] || 'Files';
                    document.getElementById('q2dFilesSidebarTitle').textContent = `${label} Files`;
                    document.getElementById('q2dFilesSidebarBody').innerHTML = q2dFilesSidebarListHtml(fileData);

                    const overlay = document.getElementById('q2dFilesOverlay');
                    const sidebar = document.getElementById('q2dFilesSidebar');
                    overlay.classList.remove('opacity-0', 'pointer-events-none');
                    sidebar.classList.remove('translate-x-full');
                }

                function q2dCloseFilesSidebar() {
                    const overlay = document.getElementById('q2dFilesOverlay');
                    const sidebar = document.getElementById('q2dFilesSidebar');
                    overlay.classList.add('opacity-0', 'pointer-events-none');
                    sidebar.classList.add('translate-x-full');
                }
                document.addEventListener('keydown', e => {
                    if (e.key === 'Escape' && !document.getElementById('q2dFilesSidebar').classList.contains('translate-x-full')) q2dCloseFilesSidebar();
                });

                // Button na nagbubukas ng files sidebar para sa isang slot
                function q2dFilesButton(slot, fileData) {
                    q2dFilesRegistry[slot] = fileData;
                    const label = Q2D_SLOT_LABELS[slot] || 'Files';
                    const count = q2dGetFilesArray(fileData).length;
                    return `
                        <button type="button" onclick="q2dOpenFilesSidebar('${slot}')"
                            class="w-full flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-left hover:border-amber-600 hover:bg-amber-50/40 transition-colors">
                            <span class="w-8 h-8 rounded-lg bg-white border border-gray-200 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-folder-open text-sm"></i></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[13px] font-semibold text-gray-900">View ${label} file${count === 1 ? '' : 's'}</span>
                                <span class="block text-[10.5px] text-gray-400">${count} attachment${count === 1 ? '' : 's'}</span>
                            </span>
                            <i class="fa-solid fa-chevron-right text-[11px] text-gray-400"></i>
                        </button>
                    `;
                }

                function q2dSlotDoneView(fileData, showEdit, slot) {
                    return `
                    <div class="text-sm">
                        ${q2dFilesButton(slot, fileData)}
                        <p class="text-[11px] text-gray-500 mt-2.5 flex items-center gap-1.5">
                            <i class="fa-solid fa-user-pen text-gray-400"></i>
                            <span class="truncate">${q2dEscapeHtml(fileData.uploaded_by_name)} (${q2dEscapeHtml(fileData.uploaded_role_label)})</span>
                        </p>
                        ${showEdit ? `
                            <button type="button" onclick="q2dUnlock('${slot}')"
                                class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg px-3 py-1.5 hover:bg-gray-50">
                                <i class="fa-solid fa-pen text-[10px]"></i> Edit files
                            </button>
                        ` : ''}
                    </div>
                `;
                }

                // Upload view para sa lahat ng slot — multi-file. `savedFiles` = mga naka-save na sa server.
                function q2dSlotUploadView(slot, savedFiles) {
                    savedFiles = savedFiles || [];
                    const inputId = q2dInputId(slot);
                    const is3d = slot === '3d';
                    const accept = is3d ? 'application/pdf,image/jpeg,image/png,image/webp' : 'application/pdf';
                    const hint = is3d
                        ? `PDF or JPG/PNG/WEBP · max 15MB each · up to ${Q2D_MAX_FILES}`
                        : `PDF only · max 15MB each · up to ${Q2D_MAX_FILES}`;
                    const idleLabel = savedFiles.length ? 'Add more files' : (is3d ? 'Upload 3D file(s)' : (slot === '2d' ? 'Upload 2D PDF(s)' : 'Upload Quotation PDF(s)'));

                    const savedList = savedFiles.length ? `
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 mb-1.5">Saved files (${savedFiles.length})</p>
                        <ul class="q2d-scroll q2d-file-list mb-3 space-y-1.5 pr-1">
                            ${savedFiles.map(f => `
                                <li class="q2d-saved rounded-lg border border-gray-200 bg-white px-2.5 py-1.5 text-xs">
                                    <div class="q2d-saved-row flex items-center gap-2 min-w-0">
                                        <i class="fa-solid ${q2dFileIcon(f.name)} shrink-0"></i>
                                        <a href="${q2dEscapeHtml(f.url)}" target="_blank" rel="noopener" class="flex-1 truncate text-gray-800 hover:text-amber-700 hover:underline" title="${q2dEscapeHtml(f.name)}">${q2dEscapeHtml(f.name)}</a>
                                        <button type="button" onclick="q2dAskDelete(this, true)"
                                            class="inline-flex items-center justify-center w-6 h-6 rounded-md text-gray-400 hover:text-red-600 hover:bg-red-50 shrink-0" title="Remove file" aria-label="Remove file"><i class="fa-solid fa-xmark text-xs leading-none"></i></button>
                                    </div>
                                    <div class="q2d-saved-confirm hidden flex items-center gap-2">
                                        <span class="flex-1 text-red-700 font-semibold">Remove this file?</span>
                                        <button type="button" onclick="q2dAskDelete(this, false)" class="px-2 py-1 rounded-md text-gray-600 bg-white border border-gray-200 hover:bg-gray-50 font-semibold">Keep</button>
                                        <button type="button" onclick="q2dDeleteFile('${slot}', ${Number(f.id)}, this)" class="px-2 py-1 rounded-md text-white bg-red-600 hover:bg-red-700 font-semibold">Remove</button>
                                    </div>
                                </li>`).join('')}
                        </ul>
                    ` : '';

                    return `
                    ${savedList}
                    <label for="${inputId}" data-drop-for="${slot}" class="q2d-drop">
                        <span class="w-9 h-9 rounded-lg bg-white border border-gray-200 text-amber-700 flex items-center justify-center"><i class="fa-solid fa-cloud-arrow-up"></i></span>
                        <span id="${inputId}_label" data-idle="${q2dEscapeHtml(idleLabel)}" class="text-[12.5px] font-semibold text-gray-700 w-full truncate px-1">${q2dEscapeHtml(idleLabel)}</span>
                        <span class="text-[10.5px] text-gray-400">${hint} · or drag here</span>
                    </label>
                    <input id="${inputId}" type="file" multiple accept="${accept}" data-saved-count="${savedFiles.length}" class="hidden">
                    <ul id="${inputId}_list" class="q2d-scroll q2d-file-list mt-2 space-y-1.5 pr-1"></ul>
                    <button type="button" id="${inputId}_done_btn" onclick="q2dSaveSlot('${slot}')" ${savedFiles.length ? '' : 'disabled'}
                        class="mt-3 w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-semibold text-white bg-amber-700 hover:bg-amber-800 rounded-lg shadow-sm disabled:bg-gray-100 disabled:text-gray-400 disabled:shadow-none disabled:cursor-not-allowed">
                        <i class="fa-solid fa-check"></i> Mark as done
                    </button>
                `;
                }

                // Inline confirm para sa pag-alis ng naka-save na file (walang confirm() popup)
                function q2dAskDelete(btn, show) {
                    const li = btn.closest('.q2d-saved');
                    li.querySelector('.q2d-saved-row').classList.toggle('hidden', show);
                    li.querySelector('.q2d-saved-confirm').classList.toggle('hidden', !show);
                    li.classList.toggle('border-red-200', show);
                    li.classList.toggle('bg-red-50', show);
                }

                // fromCustomer = true → ang remarks ay galing sa customer, hindi sa Designer Head.
                function q2dRevisionRemarksBox(remarks, fromCustomer) {
                    if (!remarks) return '';
                    return `
                    <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 mb-3">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-red-700 mb-0.5"><i class="fa-solid ${fromCustomer ? 'fa-user' : 'fa-user-tie'} mr-1"></i>${fromCustomer ? 'Customer revision remarks' : 'Revision remarks'}</p>
                        <p class="text-xs text-red-800 leading-relaxed">${q2dEscapeHtml(remarks).replace(/\n/g, '<br>')}</p>
                    </div>
                `;
                }

                function q2dApprovedNoReuploadView(fileData, slot) {
                    return `
                    <div class="text-sm">
                        ${q2dFilesButton(slot, fileData)}
                        <p class="text-[11px] text-emerald-700 mt-2.5"><i class="fa-solid fa-circle-check mr-1"></i>Already approved — no re-upload needed.</p>
                    </div>
                `;
                }

                function q2dRenderToggle(design3d) {
                    if (!design3d || !design3d.toggle_editable) return '';
                    const checked = design3d.include_3d ? 'checked' : '';
                    return `
                    <div class="q2d-card px-4 py-3 mb-4 flex items-center gap-3">
                        <span class="w-9 h-9 rounded-lg bg-violet-50 text-violet-600 flex items-center justify-center shrink-0"><i class="fa-solid fa-cube"></i></span>
                        <label for="q2dInclude3dToggle" class="min-w-0 flex-1 cursor-pointer select-none">
                            <span class="block text-[13px] font-semibold text-gray-900">Submit 3D together with 2D &amp; Quotation</span>
                            <span class="block text-[11px] text-gray-500">Off = 3D unlocks only after 2D &amp; Quotation are approved.</span>
                        </label>
                        <span class="q2d-switch">
                            <input type="checkbox" id="q2dInclude3dToggle" ${checked} onchange="q2dToggleInclude3d(this.checked)" aria-label="Submit 3D together">
                            <span></span>
                        </span>
                    </div>
                `;
                }

                // columns: [{ label, slot, contentHtml, chip? }] → mga card na magkakatabi
                function q2dFileTable(columns) {
                    const cols = columns.length >= 3 ? 'md:grid-cols-3' : (columns.length === 2 ? 'sm:grid-cols-2' : '');
                    const cards = columns.map(c => {
                        const meta = Q2D_SLOT_META[c.slot] || Q2D_SLOT_META['2d'];
                        return `
                        <div class="q2d-card overflow-hidden flex flex-col min-w-0">
                            <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-2.5">
                                <span class="w-8 h-8 rounded-lg ${meta.box} flex items-center justify-center shrink-0"><i class="fa-solid ${meta.icon} text-sm"></i></span>
                                <p class="text-[13px] font-semibold text-gray-900 truncate flex-1">${q2dEscapeHtml(c.label)}</p>
                                ${c.chip || ''}
                            </div>
                            <div class="p-4 flex-1 min-w-0" data-slot-container="${c.slot}">${c.contentHtml}</div>
                        </div>`;
                    }).join('');
                    return `<div class="grid ${cols} gap-3 mb-4">${cards}</div>`;
                }
                const Q2D_CHIP_DONE = () => q2dChip('Done', 'green', 'fa-check');
                const Q2D_CHIP_TODO = () => q2dChip('To upload', 'amber', 'fa-arrow-up');
                const Q2D_CHIP_APPROVED = () => q2dChip('Approved', 'green', 'fa-circle-check');
                const Q2D_CHIP_REVISE = () => q2dChip('Needs revision', 'red', 'fa-rotate-left');
                const Q2D_CHIP_SUBMITTED = () => q2dChip('Submitted', 'blue', 'fa-paper-plane');

                function q2dRenderCompletedView(completedEntry, design3d, state) {
                    const reviewedLine = completedEntry.reviewed_at ? `Reviewed ${q2dEscapeHtml(completedEntry.reviewed_at)}.` : '';

                    // Approved na 2D: hindi na pinapalitan — ang revision (Rev 1, Rev 2…) ay nasa Feedback panel.
                    const twoD = completedEntry.design_2d;
                    const openFb = ((state && state.cutting_feedback) || []).filter(f => !f.is_resolved).length;
                    const twoDInner = `${q2dSlotDoneView(twoD, false, '2d')}
                        <button type="button" onclick="q2dOpenFeedbackSidebar()"
                            class="mt-3 w-full inline-flex items-center justify-center gap-2 px-3 py-2 text-xs font-semibold rounded-lg
                            ${openFb ? 'text-white bg-amber-700 hover:bg-amber-800 shadow-sm' : 'text-gray-700 bg-white border border-gray-200 hover:bg-gray-50'}">
                            <i class="fa-solid ${openFb ? 'fa-cloud-arrow-up' : 'fa-clock-rotate-left'}"></i>
                            ${openFb ? 'Upload a revised 2D' : 'Revision history'}
                        </button>`;

                    const columns = [
                        { label: '2D File', slot: '2d', contentHtml: twoDInner, chip: openFb ? q2dChip('Feedback', 'red', 'fa-scissors') : Q2D_CHIP_APPROVED() },
                        { label: 'Quotation File', slot: 'quotation', contentHtml: q2dSlotDoneView(completedEntry.quotation, false, 'quotation'), chip: Q2D_CHIP_APPROVED() },
                    ];

                    // 3D na kasama sa Initial submission
                    if (design3d && design3d.include_3d && design3d.files && design3d.files.length) {
                        columns.push({ label: '3D File', slot: '3d', contentHtml: q2dSlotDoneView(design3d, false, '3d'), chip: Q2D_CHIP_APPROVED() });
                    }

                    let callout;
                    if (openFb) {
                        callout = q2dCallout('red', 'fa-scissors', `Cutting sent ${openFb} feedback on the 2D file`,
                            'Upload a revised 2D from the Feedback panel. The previous file stays in the revision history.',
                            `<button type="button" onclick="q2dOpenFeedbackSidebar()" class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold text-white bg-red-700 hover:bg-red-800 rounded-lg shadow-sm shrink-0">Open feedback <i class="fa-solid fa-arrow-right"></i></button>`);
                    } else if (state && state.ready_for_customer) {
                        // Ang "Customer review" / "Customer approved" na callout sa itaas na ang nagsasabi ng status
                        callout = reviewedLine ? `<p class="text-[11px] text-gray-400 -mt-2 mb-3"><i class="fa-regular fa-clock mr-1"></i>${reviewedLine}</p>` : '';
                    } else {
                        callout = q2dCallout('green', 'fa-circle-check', 'Approved', `Both files have been approved. No further action is needed. ${reviewedLine}`);
                    }

                    return `${callout}${q2dFileTable(columns)}`;
                }

                function q2dRenderStatusBanner(activeDraft) {
                    const tone = q2dStatusTone(activeDraft.status_label);
                    const text = activeDraft.is_locked
                        ? 'Files are submitted and waiting for approval. They can no longer be edited unless sent back for revision.'
                        : 'Upload the files for each slot, mark them as done, then submit for approval.';
                    return q2dCallout(tone, Q2D_TONE_ICON[tone], `Status: ${q2dEscapeHtml(activeDraft.status_label)}`, text, activeDraft.is_late ? q2dLateBadge() : '');
                }

                function q2dRenderActiveDraftSlots(activeDraft, design3d) {
                    const twoD = activeDraft.design_2d;
                    const quot = activeDraft.quotation;
                    const locked = activeDraft.is_locked;
                    const include3d = !!(design3d && design3d.include_3d);
                    const chipFor = done => locked ? Q2D_CHIP_SUBMITTED() : (done ? Q2D_CHIP_DONE() : Q2D_CHIP_TODO());

                    const twoDInner = twoD.done
                        ? q2dSlotDoneView(twoD, !locked, '2d')
                        : q2dSlotUploadView('2d', twoD.files);

                    const quotInner = quot.done
                        ? q2dSlotDoneView(quot, !locked, 'quotation')
                        : q2dSlotUploadView('quotation', quot.files);

                    const columns = [
                        { label: '2D File', slot: '2d', contentHtml: twoDInner, chip: chipFor(twoD.done) },
                        { label: 'Quotation File', slot: 'quotation', contentHtml: quotInner, chip: chipFor(quot.done) },
                    ];

                    if (include3d) {
                        const threeDInner = design3d.done
                            ? q2dSlotDoneView(design3d, !locked, '3d')
                            : q2dSlotUploadView('3d', design3d.files);
                        columns.push({ label: '3D File', slot: '3d', contentHtml: threeDInner, chip: chipFor(design3d.done) });
                    }

                    return q2dFileTable(columns);
                }

                // Submit bar na may inline confirm (walang popup)
                function q2dSubmitBlock({ id, ready, blocker, label, onConfirm, warn = '', confirmText }) {
                    return `
                    <div class="q2d-card px-4 py-3.5 mb-4" id="${id}">
                        <div data-submit-idle class="flex flex-wrap items-center justify-end gap-3">
                            ${warn ? `<p class="text-xs text-red-600 font-semibold mr-auto"><i class="fa-solid fa-clock mr-1"></i>${warn}</p>` : ''}
                            ${blocker ? `<p class="text-xs text-gray-400 ${warn ? '' : 'mr-auto'}"><i class="fa-solid fa-circle-info mr-1"></i>${blocker}</p>` : (warn ? '' : `<p class="text-xs text-emerald-700 mr-auto"><i class="fa-solid fa-circle-check mr-1"></i>All files are ready.</p>`)}
                            <button type="button" onclick="q2dAskSubmit('${id}', true)" ${ready ? '' : 'disabled'}
                                class="inline-flex items-center gap-2 px-5 py-2.5 text-xs font-semibold text-white bg-amber-700 hover:bg-amber-800 rounded-lg shadow-sm disabled:bg-gray-100 disabled:text-gray-400 disabled:shadow-none disabled:cursor-not-allowed">
                                <i class="fa-solid fa-paper-plane"></i> ${label}
                            </button>
                        </div>
                        <div data-submit-confirm class="hidden flex flex-wrap items-center gap-3">
                            <span class="w-9 h-9 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-circle-question"></i></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[13px] font-semibold text-gray-900">Submit for approval?</p>
                                <p class="text-[11.5px] text-gray-500">${confirmText}</p>
                            </div>
                            <button type="button" onclick="q2dAskSubmit('${id}', false)" class="px-3.5 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                            <button type="button" data-submit-yes onclick="q2dConfirmSubmit(this, ${onConfirm})" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-white bg-amber-700 hover:bg-amber-800 rounded-lg">
                                <i class="fa-solid fa-check"></i> Yes, submit
                            </button>
                        </div>
                    </div>`;
                }
                function q2dAskSubmit(id, show) {
                    const box = document.getElementById(id);
                    if (!box) return;
                    box.querySelector('[data-submit-idle]').classList.toggle('hidden', show);
                    box.querySelector('[data-submit-confirm]').classList.toggle('hidden', !show);
                    box.classList.toggle('border-amber-300', show);
                }
                async function q2dConfirmSubmit(btn, fn) {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting…';
                    await fn();
                    if (document.body.contains(btn)) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-check"></i> Yes, submit'; }
                }

                // Files na lang ang kailangan — ang contract ay nasa Final na.
                function q2dRenderSubmitBar(activeDraft, design3d) {
                    if (activeDraft.is_locked) return '';
                    const include3d = !!(design3d && design3d.include_3d);
                    const allDone = activeDraft.both_done && (!include3d || design3d.done);
                    return q2dSubmitBlock({
                        id: 'q2dSubmitMain',
                        ready: allDone,
                        blocker: allDone ? '' : `Mark ${include3d ? 'all three slots' : 'both slots'} as done first.`,
                        warn: activeDraft.is_late ? 'This will be recorded as a Late Submission.' : '',
                        label: 'Submit for approval',
                        onConfirm: 'q2dSubmitFinal',
                        confirmText: `The ${include3d ? '2D, Quotation and 3D' : '2D and Quotation'} files will go to the Designer Head. You can't edit them unless sent back for revision.`,
                    });
                }

                function q2dRenderRevisionOrFreshSlots(revisionEntry) {
                    const fromCustomer = !!(revisionEntry && revisionEntry.source === 'Customer');
                    const include3d = !!(revisionEntry && revisionEntry.include_3d);

                    let headerBlock;
                    if (revisionEntry) {
                        const needs = [];
                        if (revisionEntry.design_2d_needs_revision) needs.push('2D');
                        if (revisionEntry.quotation_needs_revision) needs.push('Quotation');
                        if (include3d && revisionEntry.design_3d_needs_revision) needs.push('3D');

                        headerBlock = q2dCallout('red', 'fa-rotate-left',
                            `${fromCustomer ? 'The customer requested changes' : 'Sent back for revision'} — ${q2dEscapeHtml(needs.join(', ') || '—')}`,
                            'Attach the corrected file(s) below. Files that were already approved do not need to be re-uploaded.');
                    } else {
                        headerBlock = q2dCallout('amber', 'fa-cloud-arrow-up', 'No submission yet',
                            'Attach one or more PDFs for the 2D and the Quotation to start.');
                    }

                    const twoDNeeds = !(revisionEntry && !revisionEntry.design_2d_needs_revision);
                    const quotNeeds = !(revisionEntry && !revisionEntry.quotation_needs_revision);

                    const twoDInner = !twoDNeeds
                        ? q2dApprovedNoReuploadView(revisionEntry.design_2d, '2d')
                        : `${revisionEntry ? q2dRevisionRemarksBox(revisionEntry.design_2d.remarks, fromCustomer) : ''}${q2dSlotUploadView('2d', [])}`;

                    const quotInner = !quotNeeds
                        ? q2dApprovedNoReuploadView(revisionEntry.quotation, 'quotation')
                        : `${revisionEntry ? q2dRevisionRemarksBox(revisionEntry.quotation.remarks, fromCustomer) : ''}${q2dSlotUploadView('quotation', [])}`;

                    const chip = needs => !needs ? Q2D_CHIP_APPROVED() : (revisionEntry ? Q2D_CHIP_REVISE() : Q2D_CHIP_TODO());
                    const columns = [
                        { label: '2D File', slot: '2d', contentHtml: twoDInner, chip: chip(twoDNeeds) },
                        { label: 'Quotation File', slot: 'quotation', contentHtml: quotInner, chip: chip(quotNeeds) },
                    ];

                    // 3D na kasama sa main submission — makikita rin dito kung kailangan ng revision.
                    if (include3d) {
                        const threeDInner = revisionEntry.design_3d_needs_revision
                            ? `${q2dRevisionRemarksBox(revisionEntry.design_3d.remarks, fromCustomer)}${q2dSlotUploadView('3d', [])}`
                            : q2dApprovedNoReuploadView(revisionEntry.design_3d, '3d');
                        columns.push({ label: '3D File', slot: '3d', contentHtml: threeDInner, chip: chip(!!revisionEntry.design_3d_needs_revision) });
                    }

                    return `${headerBlock}${q2dFileTable(columns)}`;
                }

                // Kunin ang listahan ng files ng isang slot sa magkaparehong shape, kahit
                // "files" array (multi) o "url" na lang (single/legacy) ang laman ng fileData.
                function q2dGetFilesArray(fileData) {
                    if (!fileData) return [];
                    if (fileData.files && fileData.files.length) return fileData.files;
                    if (fileData.url) return [{ url: fileData.url, name: fileData.name || 'File' }];
                    return [];
                }

                // Ginagamit pa rin ng ibang parte ng page (hal. feedback2d.php)
                function q2dFileCell(fileData) {
                    const files = (fileData && fileData.files) || [];
                    if (!files.length && !(fileData && fileData.url)) return '—';

                    const links = files.length
                        ? `<div class="space-y-1">${files.map((f, i) => `
                            <div class="flex items-center gap-1.5 min-w-0">
                                <a href="${q2dEscapeHtml(f.url)}" target="_blank"
                                    class="shrink-0 text-amber-700 hover:underline whitespace-nowrap text-xs font-semibold">
                                    ${files.length > 1 ? `File ${i + 1}` : 'View File'}
                                </a>
                                ${files.length > 1 ? `
                                    <span class="text-gray-400 truncate min-w-0 text-xs" title="${q2dEscapeHtml(f.name)}">${q2dEscapeHtml(f.name)}</span>
                                ` : ''}
                            </div>`).join('')}</div>`
                        : `<a href="${q2dEscapeHtml(fileData.url)}" target="_blank" class="block text-amber-700 hover:underline text-xs font-semibold">View File</a>`;

                    const reviewLine = fileData.review_status
                        ? `<span class="block text-[10px] font-semibold uppercase tracking-wide mt-0.5 ${fileData.review_class}">${q2dEscapeHtml(fileData.review_status)}</span>`
                        : '';
                    return `${links}${reviewLine}`;
                }

                // Isang column ng files para sa isang slot sa loob ng history card
                function q2dPastSlotBlock(label, slot, files, reviewStatus) {
                    const meta = Q2D_SLOT_META[slot];
                    const tone = reviewStatus ? q2dStatusTone(reviewStatus) : 'gray';
                    return `
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5 mb-1.5">
                            <i class="fa-solid ${meta.icon} text-[11px] text-gray-400"></i>
                            <span class="text-[10.5px] font-semibold uppercase tracking-wide text-gray-500">${label}</span>
                            ${reviewStatus ? `<span class="ml-auto">${q2dChip(reviewStatus, tone)}</span>` : ''}
                        </div>
                        ${files.length ? `<ul class="space-y-1">${files.map(f => `
                            <li class="min-w-0"><a href="${q2dEscapeHtml(f.url)}" target="_blank" rel="noopener" title="${q2dEscapeHtml(f.name)}"
                                class="flex items-center gap-1.5 text-[12px] text-gray-700 hover:text-amber-700 min-w-0">
                                <i class="fa-solid ${q2dFileIcon(f.name)} text-[11px] shrink-0"></i><span class="truncate hover:underline">${q2dEscapeHtml(f.name)}</span>
                            </a></li>`).join('')}</ul>` : '<p class="text-[12px] text-gray-300">—</p>'}
                    </div>`;
                }

                // Prior Submissions — isang card bawat submission (unang bukas, iba naka-collapse)
                function q2dRenderPastEntries(pastEntries) {
                    if (!pastEntries || pastEntries.length === 0) return '';

                    const anyBundled3d = pastEntries.some(e => e.design_3d && e.design_3d.included);

                    const cards = pastEntries.map((entry, idx) => {
                        const d2Files = q2dGetFilesArray(entry.design_2d);
                        const qFiles = q2dGetFilesArray(entry.quotation);
                        const with3d = anyBundled3d && entry.design_3d && entry.design_3d.included;
                        const d3Files = with3d ? q2dGetFilesArray(entry.design_3d) : [];
                        const tone = q2dStatusTone(entry.status_label);

                        const hasPerFileRemarks = !!entry.design_2d_remarks || !!entry.quotation_remarks;
                        const remarksHtml = hasPerFileRemarks
                            ? `${entry.design_2d_remarks ? `<p class="mb-1"><span class="font-semibold text-gray-700">2D:</span> ${q2dEscapeHtml(entry.design_2d_remarks).replace(/\n/g, '<br>')}</p>` : ''}${entry.quotation_remarks ? `<p><span class="font-semibold text-gray-700">Quotation:</span> ${q2dEscapeHtml(entry.quotation_remarks).replace(/\n/g, '<br>')}</p>` : ''}`
                            : (entry.remarks ? q2dEscapeHtml(entry.remarks).replace(/\n/g, '<br>') : '');

                        const byCustomer = entry.status === 'For Revision' && entry.revision_source === 'Customer';

                        return `
                        <details class="q2d-history q2d-card overflow-hidden" ${idx === 0 ? 'open' : ''}>
                            <summary class="px-4 py-3 flex flex-wrap items-center gap-2 hover:bg-gray-50">
                                <span class="w-7 h-7 rounded-lg bg-gray-100 text-gray-500 flex items-center justify-center text-[11px] font-bold shrink-0">#${pastEntries.length - idx}</span>
                                ${q2dChip(entry.status_label, tone, Q2D_TONE_ICON[tone])}
                                ${byCustomer ? q2dChip('By customer', 'red', 'fa-user') : ''}
                                ${entry.is_late ? q2dLateBadge() : ''}
                                <span class="ml-auto text-[11.5px] text-gray-500 whitespace-nowrap"><i class="fa-regular fa-calendar mr-1"></i>${entry.submitted_at ? q2dEscapeHtml(entry.submitted_at) : '—'}</span>
                                <i class="fa-solid fa-chevron-down text-[10px] text-gray-400 q2d-chev"></i>
                            </summary>
                            <div class="px-4 pb-4 pt-1 border-t border-gray-100">
                                <div class="grid ${with3d ? 'sm:grid-cols-3' : 'sm:grid-cols-2'} gap-4 pt-3">
                                    ${q2dPastSlotBlock('2D', '2d', d2Files, entry.design_2d && entry.design_2d.review_status)}
                                    ${q2dPastSlotBlock('Quotation', 'quotation', qFiles, entry.quotation && entry.quotation.review_status)}
                                    ${with3d ? q2dPastSlotBlock('3D', '3d', d3Files, entry.design_3d.review_status) : ''}
                                </div>
                                ${remarksHtml ? `
                                    <div class="mt-3 rounded-lg bg-gray-50 border border-gray-100 px-3 py-2.5 text-[12px] text-gray-600 leading-relaxed">
                                        <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400 mb-1"><i class="fa-solid fa-comment mr-1"></i>Remarks</p>
                                        ${remarksHtml}
                                    </div>` : ''}
                            </div>
                        </details>`;
                    }).join('');

                    return `
                    <div class="mt-6">
                        ${q2dSectionLabel(`Prior submissions (${pastEntries.length})`)}
                        <div class="q2d-scroll q2d-history-list space-y-2 pr-1">${cards}</div>
                    </div>`;
                }

                function q2dRender3dStandaloneSection(design3d) {
                    if (!design3d || design3d.include_3d || design3d.stage === 'Locked') return '';

                    const stage = design3d.stage;
                    let inner;

                    if (stage === 'Approved') {
                        inner = q2dFileTable([{ label: '3D File', slot: '3d', contentHtml: q2dSlotDoneView(design3d, false, '3d'), chip: Q2D_CHIP_APPROVED() }]);
                    } else if (stage === 'Waiting for Approval') {
                        inner = `
                            ${q2dCallout('blue', 'fa-hourglass-half', '3D submitted', 'Waiting for approval.')}
                            ${q2dFileTable([{ label: '3D File', slot: '3d', contentHtml: q2dSlotDoneView(design3d, false, '3d'), chip: Q2D_CHIP_SUBMITTED() }])}
                        `;
                    } else {
                        // Stage 'For Revision' ay galing sa Designer Head O sa Customer — iisa ang remarks column.
                        const remarksBox = (stage === 'For Revision' && design3d.remarks)
                            ? q2dRevisionRemarksBox(design3d.remarks, false) : '';
                        const uploadInner = design3d.done
                            ? q2dSlotDoneView(design3d, true, '3d')
                            : q2dSlotUploadView('3d', design3d.files);
                        const chip = stage === 'For Revision' ? Q2D_CHIP_REVISE() : (design3d.done ? Q2D_CHIP_DONE() : Q2D_CHIP_TODO());
                        inner = `
                            ${q2dFileTable([{ label: '3D File', slot: '3d', contentHtml: remarksBox + uploadInner, chip }])}
                            ${q2dSubmitBlock({
                                id: 'q2dSubmit3dBox',
                                ready: !!design3d.done,
                                blocker: design3d.done ? '' : 'Mark the 3D slot as done first.',
                                label: 'Submit 3D for approval',
                                onConfirm: 'q2dSubmit3d',
                                confirmText: 'The 3D file(s) will be sent for approval.',
                            })}
                        `;
                    }

                    return `<div class="mt-6">${q2dSectionLabel('3D file')}${inner}</div>`;
                }

                // Sidebar card: deadline + client (galing sa state.inquiry)
                function q2dInquirySummary(inquiry) {
                    if (!inquiry.deadline) return '';
                    return `
                    <div class="q2d-card px-4 py-3.5 ${inquiry.deadline_overdue ? 'border-red-200 bg-red-50' : ''}">
                        <p class="q2d-eyebrow"><i class="fa-regular fa-flag mr-1"></i>Deadline</p>
                        <p class="text-[15px] font-semibold mt-0.5 ${inquiry.deadline_overdue ? 'text-red-700' : 'text-gray-900'}">${q2dEscapeHtml(inquiry.deadline)}</p>
                        ${inquiry.deadline_overdue ? '<p class="text-[11px] font-semibold text-red-600"><i class="fa-solid fa-triangle-exclamation mr-1"></i>Overdue</p>' : ''}
                    </div>`;
                }

                // 2D DESIGN PROGRESS — tracker lang (0 / 50 / 100%). HINDI na ito gate.
                function q2dRenderProgress(step1, isSales, submitted) {
                    const steps = [
                        { value: '0', label: '0%' },
                        { value: '50', label: '50%' },
                        { value: '100', label: '100%' },
                    ];
                    const pct = Number(step1.progress) || 0;
                    // read-only: Sales, ready_for_quotation, o kapag naisumite na ang 2D & Quotation (Waiting / Approved)
                    const readOnly = isSales || step1.auto_confirmed || !!submitted;

                    const hint = submitted
                        ? 'Locked — the 2D & Quotation has been submitted.'
                        : readOnly
                            ? 'Only the assigned designer can update this.'
                            : 'Tracker only — you can submit files any time.';

                    const controls = readOnly ? '' : `
                        <div class="grid grid-cols-3 gap-1 mt-3 p-1 rounded-lg bg-gray-100">
                            ${steps.map(s => `
                                <button type="button" onclick="q2dSaveProgress('${s.value}')"
                                    class="px-2 py-1.5 text-xs font-semibold rounded-md transition-colors
                                    ${String(step1.progress) === s.value ? 'bg-white text-amber-800 shadow-sm' : 'text-gray-500 hover:text-gray-800'}">
                                    ${s.label}
                                </button>`).join('')}
                        </div>`;

                    return `
                    <div class="q2d-card px-4 py-3.5">
                        <div class="flex items-center justify-between">
                            <p class="q2d-eyebrow"><i class="fa-solid fa-pen-ruler mr-1"></i>2D design progress</p>
                            ${readOnly ? '<i class="fa-solid fa-lock text-[11px] text-gray-300"></i>' : ''}
                        </div>
                        <div class="flex items-end justify-between mt-1">
                            <span class="text-2xl font-bold text-gray-900 tabular-nums">${pct}%</span>
                            <span class="text-[11px] text-gray-400 mb-1">${pct >= 100 ? 'Complete' : pct >= 50 ? 'Halfway' : 'Not started'}</span>
                        </div>
                        <div class="h-2.5 bg-gray-100 rounded-full overflow-hidden mt-1.5">
                            <div class="h-full bg-gradient-to-r from-amber-500 to-amber-700 rounded-full transition-all duration-500" style="width:${pct}%"></div>
                        </div>
                        ${controls}
                        <p class="text-[11px] text-gray-400 mt-2">${hint}</p>
                    </div>`;
                }

                // Pagkatapos ma-approve ng Designer Head ang lahat:
                //   1) Waiting for Customer → button papunta sa Customer Review page
                //   2) Customer Approved    → Proceed to Final / View Final
                function q2dRenderProceedToFinal(state) {
                    if (!state.ready_for_customer) return '';

                    if (!state.customer_approved) {
                        return q2dCallout('amber', 'fa-user-clock', 'Next: Customer review',
                            'All files are approved by the Designer Head. Record the customer\'s decision.',
                            `<a href="${Q2D_CUSTOMER_URL}" class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold text-white bg-amber-700 hover:bg-amber-800 rounded-lg shadow-sm shrink-0">
                                Customer review <i class="fa-solid fa-arrow-right"></i></a>`);
                    }

                    const hasFinal = !!state.has_final;
                    return q2dCallout('green', 'fa-circle-check', 'Customer approved',
                        hasFinal ? 'The Final submission has already been created.' : 'All Initial files are approved by the customer. You can now prepare the Final.',
                        `<a href="${Q2D_FINAL_URL}" class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold rounded-lg shrink-0
                            ${hasFinal ? 'text-emerald-800 bg-white border border-emerald-300 hover:bg-emerald-50' : 'text-white bg-emerald-700 hover:bg-emerald-800 shadow-sm'}">
                            ${hasFinal ? 'View Final' : 'Proceed to Final'} <i class="fa-solid fa-arrow-right"></i></a>`);
                }

                // Stepper sa header
                function q2dRenderStepper(state) {
                    const el = document.getElementById('q2dStepper');
                    if (!el) return;
                    const ad = state.active_draft;
                    const submitted = !!((ad && ad.is_locked) || state.completed_entry);
                    const headOk = !!state.completed_entry || !!state.ready_for_customer;
                    const custOk = !!state.customer_approved;
                    const steps = [
                        ['fa-ruler-combined', 'Site visit', true],
                        ['fa-cloud-arrow-up', 'Upload files', submitted],
                        ['fa-user-tie', 'Head approval', headOk],
                        ['fa-user-check', 'Customer review', custOk],
                        ['fa-flag-checkered', 'Final', !!state.has_final],
                    ];
                    const cur = steps.findIndex(s => !s[2]);
                    const revising = !!state.revision_entry && !ad && !state.completed_entry;
                    el.innerHTML = `<div class="flex items-center gap-2 text-[11px] font-semibold">${steps.map(([ic, lbl, done], i) => {
                        const isCur = i === cur;
                        const red = isCur && revising && i === 1;
                        const dot = done ? 'bg-amber-700 text-white'
                            : isCur ? (red ? 'bg-red-50 text-red-600 ring-2 ring-red-500' : 'bg-amber-50 text-amber-700 ring-2 ring-amber-600')
                                : 'bg-gray-100 text-gray-400';
                        const txt = done ? 'text-amber-800' : isCur ? (red ? 'text-red-700' : 'text-gray-900') : 'text-gray-400';
                        return `${i ? `<span class="q2d-step-line ${done || isCur ? 'q2d-done' : ''}"></span>` : ''}
                            <span class="inline-flex items-center gap-1.5 whitespace-nowrap ${txt}" title="${lbl}">
                                <span class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] ${dot}"><i class="fa-solid ${done ? 'fa-check' : (red ? 'fa-rotate-left' : ic)}"></i></span>
                                <span class="hidden md:inline">${red ? 'Revision' : lbl}</span>
                            </span>`;
                    }).join('')}</div>`;
                }

                function q2dRenderRoot(state) {
                    const root = document.getElementById('q2dRoot');
                    const design3d = state.design_3d;

                    let main = q2dRenderProceedToFinal(state);
                    main += q2dRenderCuttingFeedback(state.cutting_feedback);
                    main += q2dRenderToggle(design3d);

                    if (state.active_draft) {
                        main += q2dRenderStatusBanner(state.active_draft);
                        main += q2dRenderActiveDraftSlots(state.active_draft, design3d);
                        main += q2dRenderSubmitBar(state.active_draft, design3d);
                    } else if (state.completed_entry) {
                        main += q2dRenderCompletedView(state.completed_entry, design3d, state);
                        main += q2dRender3dStandaloneSection(design3d);
                    } else {
                        main += q2dRenderRevisionOrFreshSlots(state.revision_entry);
                        main += q2dRender3dStandaloneSection(design3d);
                    }

                    main += q2dRenderPastEntries(state.past_entries);

                    const progressLocked = !!((state.active_draft && state.active_draft.is_locked) || state.completed_entry);
                    const side = q2dInquirySummary(state.inquiry) + q2dRenderProgress(state.step1, state.is_sales, progressLocked);

                    root.innerHTML = `
                    <div class="grid lg:grid-cols-[minmax(0,1fr)_280px] gap-4 items-start">
                        <div class="min-w-0">${main}</div>
                        <aside class="space-y-3 lg:sticky lg:top-4 min-w-0 order-first lg:order-none">${side}</aside>
                    </div>`;

                    q2dRenderStepper(state);

                    // Hindi na nire-reset ang q2dSelected dito — para hindi mawala ang mga napili
                    q2dBindSlot('2d');
                    q2dBindSlot('quotation');
                    q2dBindSlot('3d');
                }

                // ── MULTI-FILE: pagpili, listahan ng napili, remove, at "Mark as Done" enable/disable ──
                function q2dIsAllowedFile(slot, file) {
                    const name = (file.name || '').toLowerCase();
                    if (slot === '3d') {
                        return ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'].includes(file.type)
                            || /\.(pdf|jpe?g|png|webp)$/.test(name);
                    }
                    return file.type === 'application/pdf' || name.endsWith('.pdf');
                }

                function q2dFmtSize(b) {
                    return b >= 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB';
                }

                function q2dRefreshSlotUi(slot) {
                    const inputId = q2dInputId(slot);
                    const input = document.getElementById(inputId);
                    const label = document.getElementById(`${inputId}_label`);
                    const list = document.getElementById(`${inputId}_list`);
                    const doneBtn = document.getElementById(`${inputId}_done_btn`);
                    if (!input || !list) return;

                    // linisin ang lumang object URLs
                    q2dObjectUrls[slot].forEach(u => URL.revokeObjectURL(u));
                    q2dObjectUrls[slot] = [];

                    const selected = q2dSelected[slot];
                    const savedCount = Number(input.dataset.savedCount || 0);

                    list.innerHTML = selected.map((f, i) => {
                        const url = URL.createObjectURL(f);
                        q2dObjectUrls[slot].push(url);
                        return `
                            <li class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-2.5 py-1.5 text-xs min-w-0">
                                <i class="fa-solid ${q2dFileIcon(f.name)} shrink-0"></i>
                                <a href="${url}" target="_blank" rel="noopener" class="flex-1 min-w-0 truncate text-gray-800 hover:underline" title="${q2dEscapeHtml(f.name)}">${q2dEscapeHtml(f.name)}</a>
                                <span class="shrink-0 text-[10px] text-gray-400">${q2dFmtSize(f.size)}</span>
                                <span class="shrink-0 text-[9.5px] text-emerald-700 uppercase font-bold">New</span>
                                <button type="button" onclick="q2dRemoveSelected('${slot}', ${i})"
                                    class="inline-flex items-center justify-center w-6 h-6 rounded-md text-gray-400 hover:text-red-600 hover:bg-white shrink-0" title="Remove" aria-label="Remove"><i class="fa-solid fa-xmark text-xs leading-none"></i></button>
                            </li>`;
                    }).join('');

                    if (label) {
                        label.textContent = selected.length
                            ? `${selected.length} new file${selected.length > 1 ? 's' : ''} selected — add more`
                            : (label.dataset.idle || label.textContent);
                    }
                    if (doneBtn) doneBtn.disabled = (savedCount + selected.length) === 0;
                }

                function q2dRemoveSelected(slot, index) {
                    q2dSelected[slot].splice(index, 1);
                    q2dRefreshSlotUi(slot);
                }

                function q2dAddFiles(slot, picked) {
                    const input = document.getElementById(q2dInputId(slot));
                    if (!input) return;
                    const savedCount = Number(input.dataset.savedCount || 0);

                    let rejected = null;
                    for (const f of picked) {
                        if (!q2dIsAllowedFile(slot, f)) {
                            rejected = `"${f.name}" is not an allowed file type.`;
                            continue;
                        }
                        if (f.size > Q2D_MAX_BYTES) {
                            rejected = `"${f.name}" exceeds the 15MB limit.`;
                            continue;
                        }
                        if (q2dSelected[slot].some(x => x.name === f.name && x.size === f.size && x.lastModified === f.lastModified)) {
                            continue; // duplicate
                        }
                        if (savedCount + q2dSelected[slot].length >= Q2D_MAX_FILES) {
                            rejected = `Maximum of ${Q2D_MAX_FILES} files per slot.`;
                            break;
                        }
                        q2dSelected[slot].push(f);
                    }
                    if (rejected) crmShowToast(q2dEscapeHtml(rejected), 'error');

                    q2dRefreshSlotUi(slot);
                }

                function q2dBindSlot(slot) {
                    const input = document.getElementById(q2dInputId(slot));
                    if (!input) {
                        // walang upload view ngayon para sa slot na ito → walang dapat i-hold na napili
                        q2dSelected[slot] = [];
                        q2dObjectUrls[slot].forEach(u => URL.revokeObjectURL(u));
                        q2dObjectUrls[slot] = [];
                        return;
                    }

                    input.addEventListener('change', function () {
                        const picked = Array.from(input.files);
                        input.value = ''; // para pwedeng pumili ulit ng parehong file
                        q2dAddFiles(slot, picked);
                    });

                    // Drag & drop sa upload box
                    const drop = document.querySelector(`[data-drop-for="${slot}"]`);
                    if (drop) {
                        ['dragenter', 'dragover'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('q2d-drag'); }));
                        ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('q2d-drag'); }));
                        drop.addEventListener('drop', e => q2dAddFiles(slot, Array.from(e.dataTransfer.files || [])));
                    }

                    q2dRefreshSlotUi(slot); // ibalik ang listahan ng napili kung na-re-render lang ang page
                }

                function q2dRenderStageBadge(stage) {
                    const el = document.getElementById('q2dStageBadge');
                    if (!el || !stage) return;
                    el.textContent = stage;
                    el.classList.remove('hidden');
                    el.classList.add('inline-block');
                }

                async function q2dFetchState({ silent = false } = {}) {
                    try {
                        const res = await fetch(`${Q2D_AJAX_URL}?action=state&inquiry_id=${Q2D_INQUIRY_ID}`);
                        const data = await res.json();

                        if (!data.success) {
                            if (!silent) {
                                document.getElementById('q2dRoot').innerHTML = `
                                <div class="q2d-card px-6 py-10 text-center">
                                    <span class="w-12 h-12 mx-auto rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center mb-3"><i class="fa-solid fa-triangle-exclamation text-lg"></i></span>
                                    <p class="text-sm font-semibold text-gray-900">${q2dEscapeHtml(data.message || 'Unable to load this submission.')}</p>
                                </div>
                            `;
                            }
                            return;
                        }

                        q2dRenderStageBadge(data.stage);

                        // Huwag i-refresh sa background poll habang may napiling file na hindi pa naa-upload.
                        if (silent && q2dHasPending()) {
                            return;
                        }

                        const signature = JSON.stringify(data.step1) + JSON.stringify(data.active_draft) + JSON.stringify(data.completed_entry)
                            + JSON.stringify(data.revision_entry) + JSON.stringify(data.past_entries)
                            + JSON.stringify(data.design_3d) + JSON.stringify(data.cutting_feedback)
                            + JSON.stringify(data.has_final)
                            + JSON.stringify(data.ready_for_customer) + JSON.stringify(data.customer_approved);
                        if (signature !== q2dLastSignature) {
                            q2dRenderRoot(data);
                            q2dLastSignature = signature;
                        }

                        const now = new Date();
                        const updatedEl = document.getElementById('q2dUpdatedAt');
                        if (updatedEl) {
                            updatedEl.innerHTML = `<i class="fa-solid fa-rotate mr-1"></i>Updated ${now.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', second: '2-digit' })}`;
                        }

                    } catch (e) {
                        console.error('q2dFetchState:', e);
                        if (!silent) crmShowToast('Connection error while loading this submission.', 'error');
                    }
                }

                function q2dStartPolling() {
                    if (q2dPollTimer) clearInterval(q2dPollTimer);
                    q2dPollTimer = setInterval(() => q2dFetchState({ silent: true }), Q2D_POLL_INTERVAL_MS);
                }

                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) {
                        if (q2dPollTimer) clearInterval(q2dPollTimer);
                    } else {
                        q2dFetchState({ silent: true });
                        q2dStartPolling();
                    }
                });

                q2dFetchState().then(q2dStartPolling);


                // toggle "submit 3D together" on/off.
                async function q2dToggleInclude3d(checked) {
                    const formData = new FormData();
                    formData.append('action', 'save_toggle');
                    formData.append('inquiry_id', Q2D_INQUIRY_ID);
                    formData.append('include_3d', checked ? '1' : '0');

                    try {
                        const res = await fetch(Q2D_AJAX_URL, { method: 'POST', body: formData });
                        const data = await res.json();

                        if (!data.success) {
                            crmShowToast(data.message || 'Something went wrong.', 'error');
                            q2dLastSignature = '';
                            await q2dFetchState();
                            return;
                        }

                        q2dLastSignature = '';
                        await q2dFetchState();
                    } catch (e) {
                        console.error('q2dToggleInclude3d:', e);
                        crmShowToast('Connection error. Please try again.', 'error');
                    }
                }

                // save the 0/50/100 progress value.
                async function q2dSaveProgress(value) {
                    const formData = new FormData();
                    formData.append('action', 'save_progress');
                    formData.append('inquiry_id', Q2D_INQUIRY_ID);
                    formData.append('progress', value);

                    try {
                        const res = await fetch(Q2D_AJAX_URL, { method: 'POST', body: formData });
                        const data = await res.json();

                        if (!data.success) {
                            crmShowToast(data.message || 'Something went wrong.', 'error');
                            return;
                        }

                        q2dLastSignature = '';
                        await q2dFetchState();
                    } catch (e) {
                        console.error('q2dSaveProgress:', e);
                        crmShowToast('Connection error. Please try again.', 'error');
                    }
                }

                // MULTI-FILE: i-upload lahat ng napiling file (files[]) at i-mark done ang slot.
                async function q2dSaveSlot(slot) {
                    const formData = new FormData();
                    formData.append('action', 'save_slot');
                    formData.append('inquiry_id', Q2D_INQUIRY_ID);
                    formData.append('slot', slot);
                    q2dSelected[slot].forEach(f => formData.append('files[]', f));

                    const doneBtn = document.getElementById(`${q2dInputId(slot)}_done_btn`);
                    if (doneBtn) {
                        doneBtn.disabled = true;
                        doneBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Uploading…';
                    }
                    const resetBtn = () => { if (doneBtn) doneBtn.innerHTML = '<i class="fa-solid fa-check"></i> Mark as done'; q2dRefreshSlotUi(slot); };

                    try {
                        const res = await fetch(Q2D_AJAX_URL, { method: 'POST', body: formData });
                        const data = await res.json();

                        if (!data.success) {
                            crmShowToast(q2dEscapeHtml(data.message || 'Something went wrong.'), 'error');
                            resetBtn(); // ibalik ang tamang state ng button
                            return;
                        }

                        q2dSelected[slot] = [];
                        q2dLastSignature = ''; // force re-render even if signature looks unchanged
                        crmShowToast(data.message || 'Saved.');
                        await q2dFetchState();
                    } catch (e) {
                        console.error('q2dSaveSlot:', e);
                        crmShowToast('Connection error. Please try again.', 'error');
                        resetBtn();
                    }
                }

                // MULTI-FILE: alisin ang isang naka-save na file (inline confirm na ang tanong, walang popup).
                async function q2dDeleteFile(slot, fileId, btn) {
                    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>'; }

                    const formData = new FormData();
                    formData.append('action', 'delete_file');
                    formData.append('inquiry_id', Q2D_INQUIRY_ID);
                    formData.append('slot', slot);
                    formData.append('file_id', fileId);

                    try {
                        const res = await fetch(Q2D_AJAX_URL, { method: 'POST', body: formData });
                        const data = await res.json();

                        if (!data.success) {
                            crmShowToast(q2dEscapeHtml(data.message || 'Something went wrong.'), 'error');
                            if (btn) { btn.disabled = false; btn.textContent = 'Remove'; }
                            return;
                        }

                        q2dLastSignature = '';
                        crmShowToast(data.message || 'File removed.');
                        await q2dFetchState();
                    } catch (e) {
                        console.error('q2dDeleteFile:', e);
                        crmShowToast('Connection error. Please try again.', 'error');
                        if (btn) { btn.disabled = false; btn.textContent = 'Remove'; }
                    }
                }

                async function q2dUnlock(slot) {
                    const formData = new FormData();
                    formData.append('action', 'unlock_slot');
                    formData.append('inquiry_id', Q2D_INQUIRY_ID);
                    formData.append('slot', slot);

                    try {
                        const res = await fetch(Q2D_AJAX_URL, { method: 'POST', body: formData });
                        const data = await res.json();

                        if (!data.success) {
                            crmShowToast(data.message || 'Something went wrong.', 'error');
                            return;
                        }

                        q2dLastSignature = '';
                        await q2dFetchState();
                    } catch (e) {
                        console.error('q2dUnlock:', e);
                        crmShowToast('Connection error. Please try again.', 'error');
                    }
                }

                async function q2dSubmitFinal() {
                    const formData = new FormData();
                    formData.append('action', 'submit_final');
                    formData.append('inquiry_id', Q2D_INQUIRY_ID);

                    try {
                        const res = await fetch(Q2D_AJAX_URL, { method: 'POST', body: formData });
                        const data = await res.json();

                        if (!data.success) {
                            crmShowToast(data.message || 'Something went wrong.', 'error');
                            return;
                        }

                        q2dLastSignature = '';
                        crmShowToast(data.message || 'Submitted for approval.');
                        await q2dFetchState();
                    } catch (e) {
                        console.error('q2dSubmitFinal:', e);
                        crmShowToast('Connection error. Please try again.', 'error');
                    }
                }

                async function q2dSubmit3d() {
                    const formData = new FormData();
                    formData.append('action', 'submit_3d');
                    formData.append('inquiry_id', Q2D_INQUIRY_ID);

                    try {
                        const res = await fetch(Q2D_AJAX_URL, { method: 'POST', body: formData });
                        const data = await res.json();

                        if (!data.success) {
                            crmShowToast(data.message || 'Something went wrong.', 'error');
                            return;
                        }

                        q2dLastSignature = '';
                        crmShowToast(data.message || 'Submitted for approval.');
                        await q2dFetchState();
                    } catch (e) {
                        console.error('q2dSubmit3d:', e);
                        crmShowToast('Connection error. Please try again.', 'error');
                    }
                }
            </script>
        <?php endif; ?>

    </main>
</body>

</html>