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
</head>

<body class="bg-gray-100 font-['Barlow_Condensed']">
    <main class="ml-56 min-h-screen p-4">

        <div class="max-w-7xl mx-auto">

            <div class="bg-white border border-gray-300 shadow-sm rounded-lg">

                <div class="px-6 pt-6 pb-5 flex items-start justify-between border-b border-gray-300">
                    <div>
                        <p class="text-[10px] tracking-[0.25em] uppercase text-gray-500 mb-1 ">Client Relationship
                            Management</p>
                        <h1 class="text-xl font-bold text-[#0B2540] tracking-wide">
                            2D and Quotation
                            <span id="q2dStageBadge"
                                class="hidden align-middle ml-2 text-[10px] font-bold uppercase tracking-widest px-2 py-0.1 rounded-lg bg-amber-100 text-amber-800 border border-amber-600"></span>
                        </h1>
                        <?php if (empty($qError) && !empty($inquiry['deadline'])): ?>
                            <p
                                class="text-xs font-medium mt-1.5 <?= $qDeadlineIsOverdue ? 'text-red-700' : 'text-gray-500' ?>">
                                Deadline: <?= htmlspecialchars(date('F d, Y', strtotime($inquiry['deadline']))) ?>
                                <?= $qDeadlineIsOverdue ? ' — overdue' : '' ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" id="q2dFeedbackBtn" onclick="q2dToggleFeedbackSidebar()"
                            class="relative text-xs font-medium text-gray-600 border border-gray-300 px-3 py-1.5 hover:bg-gray-50 transition-colors rounded-full">
                            <i class="fa-solid fa-comment-dots"></i> Feedback
                            <span id="q2dFeedbackBadge"
                                class="hidden absolute -top-1.5 -right-1.5 min-w-[16px] h-4 px-1 rounded-full bg-red-600 text-white text-[9px] font-bold items-center justify-center leading-none">0</span>
                        </button>
                        <a href="<?= htmlspecialchars($crmBackUrl) ?>"
                            class="text-xs font-medium text-gray-600 border border-gray-300 px-3 py-1.5 hover:bg-gray-50 transition-colors rounded-full">
                            <i class="fa-solid fa-circle-arrow-left"></i> Back to List
                        </a>
                    </div>
                </div>

                <div class="h-[3px] bg-gray-200"></div>

                <div class="px-6 py-6 text-sm">

                    <?php if (!empty($qError)): ?>
                        <div class="border border-gray-400 bg-gray-50 text-gray-800 text-sm px-4 py-2.5">
                            <strong class="uppercase text-[11px] tracking-wide">Notice:</strong>
                            <?= htmlspecialchars($qError) ?>
                        </div>
                    <?php else: ?>

                        <!-- Everything below is rendered/refreshed by JS from crm2dquotationajax.php?action=state -->
                        <div id="q2dRoot">
                            <div class="space-y-3 py-4">
                                <div class="h-6 bg-gray-100 animate-pulse w-1/3"></div>
                                <div class="h-24 bg-gray-100 animate-pulse"></div>
                                <div class="h-24 bg-gray-100 animate-pulse"></div>
                            </div>
                        </div>

                    <?php endif; ?>

                </div>

                <div class="border-t border-gray-300 px-6 py-3 text-[11px] text-gray-400 flex justify-between">
                    <span>Generated on <?= date('F d, Y g:i A') ?></span>
                    <span id="q2dUpdatedAt">2D and Quotation</span>
                </div>

            </div>

        </div>

        <!-- Feedback sidebar -->
        <div id="q2dFeedbackOverlay" onclick="q2dCloseFeedbackSidebar()"
            class="fixed inset-0 bg-black/30 z-[9998] opacity-0 pointer-events-none transition-opacity duration-300">
        </div>

        <aside id="q2dFeedbackSidebar"
            class="fixed top-0 right-0 h-full w-full max-w-sm bg-white shadow-2xl z-[9999] translate-x-full transition-transform duration-300 ease-out flex flex-col">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-300">
                <h2 class="text-sm font-bold text-[#0B2540] uppercase tracking-wide">Feedback from Cutting</h2>
                <button type="button" onclick="q2dCloseFeedbackSidebar()"
                    class="text-gray-400 hover:text-gray-700 text-xl leading-none">&times;</button>
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
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-300">
                <h2 id="q2dFilesSidebarTitle" class="text-sm font-bold text-[#0B2540] uppercase tracking-wide">Files</h2>
                <button type="button" onclick="q2dCloseFilesSidebar()"
                    class="text-gray-400 hover:text-gray-700 text-xl leading-none">&times;</button>
            </div>
            <div id="q2dFilesSidebarBody" class="flex-1 overflow-y-auto px-5 py-4">
                <p class="text-sm text-gray-400 italic">No files.</p>
            </div>
        </aside>

        <div id="crmToastContainer"
            class="fixed bottom-6 right-6 z-[9999] flex flex-col gap-2 pointer-events-none w-30 max-w-sm px-4 sm:px-0">
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
                        ? { wrap: 'bg-white border-green-900 text-gray-800 rounded-2xl', icon: 'bg-green-600 text-white rounded-lg', symbol: '✓' }
                        : { wrap: 'bg-white border-red-700 text-gray-800 rounded-2xl', icon: 'bg-red-700 text-white rounded-lg', symbol: '!' };

                    const toast = document.createElement('div');
                    toast.className = `pointer-events-auto flex items-start gap-2.5 border shadow-lg px-4 py-3 text-sm
            ${palette.wrap}
            translate-x-6 opacity-0 scale-95 transition-all duration-300 ease-out`;
                    toast.innerHTML = `
            <span class="shrink-0 inline-flex items-center justify-center w-5 h-5 text-xs font-bold ${palette.icon}">${palette.symbol}</span>
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

                function q2dEscapeHtml(str) {
                    const div = document.createElement('div');
                    div.textContent = str ?? '';
                    return div.innerHTML;
                }

                // Detect kung ang status/label ay talagang "Approved" para malagyan ng
                // checkmark icon — hindi lang basta text badge.
                function q2dIsApproved(label) {
                    return typeof label === 'string' && label.trim().toLowerCase() === 'approved';
                }
                function q2dApprovedIcon() {
                    return '<i class="fa-solid fa-circle-check text-green-600 mr-1"></i>';
                }

                function q2dLateBadge() {
                    return `<span class="inline-block text-[10px] font-semibold uppercase tracking-wide px-2 py-0.5 border border-red-700 text-red-700 ml-1">Late Submission</span>`;
                }

                const Q2D_UPLOAD_SVG = `<svg class="w-5 h-5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
            </svg>`;


                function q2dInputId(slot) {
                    if (slot === '2d') return 'design_2d_pdf';
                    if (slot === 'quotation') return 'quotation_pdf';
                    return 'design_3d_file';
                }

                function q2dSectionLabel(text) {
                    return `<h2 class="text-[11px] uppercase tracking-[0.2em] text-gray-500 font-semibold border-b border-gray-300 pb-2 mb-4">${text}</h2>`;
                }

                // ── Files sidebar: bawat slot (2D / Quotation / 3D) ay button na lang na
                // nagpapakita ng bilang ng files. Pag-click, doon lumalabas ang buong listahan
                // sa slide-out sidebar — hindi na crowded ang table.
                const Q2D_SLOT_LABELS = { '2d': '2D', quotation: 'Quotation', '3d': '3D' };
                let q2dFilesRegistry = {}; // { slot: fileData } — pinopopulate tuwing nag-re-render

                function q2dFilesSidebarListHtml(fileData) {
                    const files = q2dGetFilesArray(fileData);
                    if (!files.length) return '<p class="text-sm text-gray-400 italic">No files.</p>';

                    const list = files.map(f => `
                        <a href="${q2dEscapeHtml(f.url)}" target="_blank" title="${q2dEscapeHtml(f.name)}"
                            class="flex items-center gap-2 text-sm border border-gray-200 bg-gray-50 hover:bg-gray-100 px-3 py-2 mb-2 transition-colors">
                            <i class="fa-solid fa-file-lines text-gray-400 shrink-0"></i>
                            <span class="flex-1 truncate text-[#0B2540] hover:text-[#A9822C] underline underline-offset-2">${q2dEscapeHtml(f.name)}</span>
                        </a>
                    `).join('');

                    const uploadedLine = (fileData && fileData.uploaded_by_name)
                        ? `<p class="text-[11px] text-gray-500 mt-3 pt-3 border-t border-gray-200">Uploaded by: ${q2dEscapeHtml(fileData.uploaded_by_name)} (${q2dEscapeHtml(fileData.uploaded_role_label)})</p>`
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

                // Button na nagbubukas ng files sidebar para sa isang slot — ipinapalit sa
                // dating direktang listahan ng "View File 1, View File 2, ..." sa loob ng table.
                function q2dFilesButton(slot, fileData) {
                    q2dFilesRegistry[slot] = fileData;
                    const label = Q2D_SLOT_LABELS[slot] || 'Files';
                    const count = q2dGetFilesArray(fileData).length;
                    return `
                        <button type="button" onclick="q2dOpenFilesSidebar('${slot}')"
                            class="inline-flex items-center gap-2 text-sm font-semibold text-[#0B2540] border border-gray-300 rounded-full px-3 py-1.5 hover:bg-gray-50 hover:border-[#0B2540] transition-colors">
                            <i class="fa-solid fa-folder-open"></i> View ${label} File${count === 1 ? '' : 's'} (${count})
                        </button>
                    `;
                }

                function q2dSlotDoneView(fileData, showEdit, slot) {
                    return `
                    <div class="text-sm">
                        ${q2dFilesButton(slot, fileData)}
                        <p class="text-[11px] text-gray-500 mt-2">
                            Uploaded by: ${q2dEscapeHtml(fileData.uploaded_by_name)}
                            (${q2dEscapeHtml(fileData.uploaded_role_label)})
                        </p>
                        ${showEdit ? `
                            <button type="button" onclick="q2dUnlock('${slot}')"
                                class="mt-2.5 text-xs font-medium text-gray-600 border border-gray-400 px-3 py-1.5 hover:bg-gray-100">
                                Edit
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
                        ? `PDF or image (JPG/PNG/WEBP), max 15MB each · up to ${Q2D_MAX_FILES} files`
                        : `PDF only, max 15MB each · up to ${Q2D_MAX_FILES} files`;
                    const idleLabel = savedFiles.length ? 'Click to add more files' : (is3d ? 'Click to upload 3D file(s)' : (slot === '2d' ? 'Click to upload 2D PDF(s)' : 'Click to upload Quotation PDF(s)'));

                    const savedList = savedFiles.length ? `
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 mb-1">Saved files (${savedFiles.length})</p>
                        <ul class="mb-3 space-y-1">
                            ${savedFiles.map(f => `
                                <li class="flex items-center gap-2 text-xs border border-gray-200 bg-gray-50 px-2 py-1">
                                    <a href="${q2dEscapeHtml(f.url)}" target="_blank" class="flex-1 truncate text-[#0B2540] hover:text-[#A9822C] underline underline-offset-2" title="${q2dEscapeHtml(f.name)}">${q2dEscapeHtml(f.name)}</a>
                                    <button type="button" onclick="q2dDeleteFile('${slot}', ${Number(f.id)})"
                                        class="shrink-0 text-gray-400 hover:text-red-700 text-base leading-none" title="Remove file" aria-label="Remove file">&times;</button>
                                </li>`).join('')}
                        </ul>
                    ` : '';

                    return `
                    ${savedList}
                    <label for="${inputId}"
                        class="flex flex-col items-center justify-center gap-1.5 border border-dashed border-gray-400 py-5 px-3 cursor-pointer hover:border-[#0B2540] hover:bg-gray-50 transition-colors">
                        ${Q2D_UPLOAD_SVG}
                        <span id="${inputId}_label" data-idle="${q2dEscapeHtml(idleLabel)}" class="text-sm text-gray-600 text-center w-full truncate px-1">${q2dEscapeHtml(idleLabel)}</span>
                        <span class="text-[11px] text-gray-400 text-center">${hint}</span>
                    </label>
                    <input id="${inputId}" type="file" multiple accept="${accept}" data-saved-count="${savedFiles.length}" class="hidden">
                    <ul id="${inputId}_list" class="mt-2 space-y-1"></ul>
                    <button type="button" id="${inputId}_done_btn" onclick="q2dSaveSlot('${slot}')" ${savedFiles.length ? '' : 'disabled'}
                        class="mt-3 w-full px-4 py-2 text-sm font-semibold uppercase tracking-wide text-white bg-[#0B2540] hover:bg-[#123564] disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors">
                        Mark as Done
                    </button>
                `;
                }

                // fromCustomer = true → ang remarks ay galing sa customer, hindi sa Designer Head.
                function q2dRevisionRemarksBox(remarks, fromCustomer) {
                    if (!remarks) return '';
                    return `
                    <div class="border-l-4 border-red-700 bg-red-50 px-3 py-2 mb-3">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-red-700 mb-0.5">${fromCustomer ? 'Customer Revision Remarks' : 'Revision Remarks'}</p>
                        <p class="text-xs text-red-800">${q2dEscapeHtml(remarks).replace(/\n/g, '<br>')}</p>
                    </div>
                `;
                }


                function q2dApprovedNoReuploadView(fileData, slot) {
                    return `
                    <div class="text-sm">
                        <span class="inline-flex items-center text-[10px] font-semibold uppercase tracking-wide px-2 py-0.5 border border-green-700 text-green-800 mb-2">${q2dApprovedIcon()}Approved</span>
                        <div class="mt-1">${q2dFilesButton(slot, fileData)}</div>
                        <p class="text-[11px] text-gray-500 mt-2">No re-upload needed.</p>
                    </div>
                `;
                }

                function q2dRenderToggle(design3d) {
                    if (!design3d || !design3d.toggle_editable) return '';
                    const checked = design3d.include_3d ? 'checked' : '';
                    return `
                    <label class="flex items-center gap-2.5 mb-5 text-sm text-gray-700 cursor-pointer select-none">
                        <input type="checkbox" id="q2dInclude3dToggle" ${checked}
                            onchange="q2dToggleInclude3d(this.checked)"
                            class="w-4 h-4 accent-green-600">
                        Submit 3D together with 2D &amp; Quotation
                        <span class="text-[11px] text-gray-400 font-normal">
                            (off = 3D unlocks only after 2D &amp; Quotation are approved)
                        </span>
                    </label>
                `;
                }

                function q2dFileTable(columns) {
                    // columns: [{ label, contentHtml }, ...] — rendered as a single formal table row.
                    const widthClass = columns.length === 3 ? 'w-1/3' : 'w-1/2';
                    const heads = columns.map((c, i) =>
                        `<th class="${widthClass} text-left font-semibold text-[11px] uppercase tracking-wide text-gray-600 px-4 py-2 border-r border-b border-gray-300 last:border-r-0">${q2dEscapeHtml(c.label)}</th>`
                    ).join('');
                    const cells = columns.map((c, i) =>
                        `<td class="align-top px-4 py-4 border-r border-gray-300 last:border-r-0" data-slot-container="${c.slot}">${c.contentHtml}</td>`
                    ).join('');
                    return `
        <table class="w-full table-fixed border border-gray-300 mb-6">
            <thead><tr class="bg-gray-50">${heads}</tr></thead>
            <tbody><tr>${cells}</tr></tbody>
        </table>
    `;
                }

                function q2dRenderCompletedView(completedEntry, design3d, state) {
                    const reviewedLine = completedEntry.reviewed_at
                        ? `<p class="text-xs text-gray-400">Reviewed ${q2dEscapeHtml(completedEntry.reviewed_at)}</p>`
                        : '';

                    const twoD = completedEntry.design_2d;
                    const twoDInner = twoD.done
                        ? q2dSlotDoneView(twoD, completedEntry.design_2d_revisable, '2d')
                        : q2dSlotUploadView('2d', twoD.files);

                    const columns = [
                        { label: '2D File', slot: '2d', contentHtml: twoDInner },
                        { label: 'Quotation File', slot: 'quotation', contentHtml: q2dSlotDoneView(completedEntry.quotation, false, 'quotation') },
                    ];

                    // 3D na kasama sa Initial submission
                    if (design3d && design3d.include_3d && design3d.files && design3d.files.length) {
                        columns.push({ label: '3D File', slot: '3d', contentHtml: q2dSlotDoneView(design3d, false, '3d') });
                    }

                    let note;
                    if (!twoD.done) {
                        note = 'Cutting flagged an issue with the 2D file — update the files below.';
                    } else if (state && state.customer_approved) {
                        note = 'All files have been approved by the customer. No further action is needed here.';
                    } else if (state && state.ready_for_customer) {
                        note = 'All files are approved by the Designer Head. Waiting for the customer\'s decision.';
                    } else {
                        note = 'Both files have been approved. No further action is needed.';
                    }

                    return `
        <div class="flex items-center justify-between border-l-4 border-[#0B2540] bg-gray-50 px-4 py-2.5 mb-6">
            <div>
                <p class="text-sm text-gray-800">
                    <strong class="uppercase tracking-wide text-[11px]">Status:</strong>
                    <span class="inline-flex items-center text-[11px] font-semibold uppercase tracking-wide px-2 py-0.5 border border-green-700 ml-1 text-green-800">${q2dApprovedIcon()}Approved</span>
                </p>
                ${reviewedLine}
            </div>
        </div>
        <p class="text-sm text-gray-500 italic mb-6">${note}</p>
        ${q2dFileTable(columns)}
    `;
                }


                function q2dRenderStatusBanner(activeDraft) {
                    return `
                    <div class="flex items-center justify-between border-l-4 border-[#0B2540] bg-gray-50 px-4 py-2.5 mb-6">
                        <p class="text-sm text-gray-800">
                            <strong class="uppercase tracking-wide text-[11px]">Status:</strong>
                            <span class="inline-flex items-center text-[11px] font-semibold uppercase tracking-wide px-2 py-0.5 border ml-1 ${activeDraft.status_class}">${q2dIsApproved(activeDraft.status_label) ? q2dApprovedIcon() : ''}${q2dEscapeHtml(activeDraft.status_label)}</span>
                            ${activeDraft.is_late ? q2dLateBadge() : ''}
                        </p>
                    </div>
                    ${activeDraft.is_locked ? `
                        <p class="text-sm text-gray-500 italic mb-6">
                            Both files have been submitted and are waiting for approval. This can no longer be edited unless sent back for revision.
                        </p>
                    ` : ''}
                `;
                }


                function q2dRenderActiveDraftSlots(activeDraft, design3d) {
                    const twoD = activeDraft.design_2d;
                    const quot = activeDraft.quotation;
                    const locked = activeDraft.is_locked;
                    const include3d = !!(design3d && design3d.include_3d);

                    const twoDInner = twoD.done
                        ? q2dSlotDoneView(twoD, !locked, '2d')
                        : q2dSlotUploadView('2d', twoD.files);

                    const quotInner = quot.done
                        ? q2dSlotDoneView(quot, !locked, 'quotation')
                        : q2dSlotUploadView('quotation', quot.files);

                    const columns = [
                        { label: '2D File', slot: '2d', contentHtml: twoDInner },
                        { label: 'Quotation File', slot: 'quotation', contentHtml: quotInner },
                    ];

                    if (include3d) {
                        const threeDInner = design3d.done
                            ? q2dSlotDoneView(design3d, !locked, '3d')
                            : q2dSlotUploadView('3d', design3d.files);
                        columns.push({ label: '3D File', slot: '3d', contentHtml: threeDInner });
                    }

                    return q2dFileTable(columns);
                }

                // Files na lang ang kailangan — ang contract ay nasa Final na.
                function q2dRenderSubmitBar(activeDraft, design3d) {
                    if (activeDraft.is_locked) return '';
                    const include3d = !!(design3d && design3d.include_3d);
                    const allDone = activeDraft.both_done && (!include3d || design3d.done);
                    const blockerMsg = allDone ? '' : `Complete ${include3d ? 'all three slots' : 'both slots'} first.`;

                    return `
    <div class="pt-3 border-t border-gray-300 flex items-center justify-end gap-3">
        ${activeDraft.is_late ? `<p class="text-xs text-red-700 font-semibold">This will be recorded as a Late Submission.</p>` : ''}
        ${blockerMsg ? `<p class="text-xs text-gray-400">${blockerMsg}</p>` : ''}
        <button type="button" id="q2dSubmitBtn" onclick="q2dSubmitFinal()" ${allDone ? '' : 'disabled'}
            class="px-5 py-2 text-sm font-semibold uppercase tracking-wide text-white bg-[#0B2540] hover:bg-[#123564] disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors">
            Submit for Approval
        </button>
    </div>
`;
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

                        headerBlock = `
                    ${q2dSectionLabel('Re-upload Files')}
                    <p class="text-sm text-gray-500 italic mb-6">
                        ${fromCustomer ? 'The customer requested changes. ' : ''}Needs revision: <strong>${needs.join(', ') || '—'}</strong>.
                        Attach the corrected file(s) below. Files that were already approved do not need to be re-uploaded.
                    </p>
                `;
                    } else {
                        headerBlock = `
                    ${q2dSectionLabel('No Active Submission')}
                    <p class="text-sm text-gray-500 italic mb-6">
                        No 2D and Quotation files have been submitted yet for this inquiry. Attach one or more PDFs below to start.
                    </p>
                `;
                    }

                    const twoDInner = (revisionEntry && !revisionEntry.design_2d_needs_revision)
                        ? q2dApprovedNoReuploadView(revisionEntry.design_2d, '2d')
                        : `${revisionEntry ? q2dRevisionRemarksBox(revisionEntry.design_2d.remarks, fromCustomer) : ''}${q2dSlotUploadView('2d', [])}`;

                    const quotInner = (revisionEntry && !revisionEntry.quotation_needs_revision)
                        ? q2dApprovedNoReuploadView(revisionEntry.quotation, 'quotation')
                        : `${revisionEntry ? q2dRevisionRemarksBox(revisionEntry.quotation.remarks, fromCustomer) : ''}${q2dSlotUploadView('quotation', [])}`;

                    const columns = [
                        { label: '2D File', slot: '2d', contentHtml: twoDInner },
                        { label: 'Quotation File', slot: 'quotation', contentHtml: quotInner },
                    ];

                    // 3D na kasama sa main submission — makikita rin dito kung kailangan ng revision.
                    if (include3d) {
                        const threeDInner = revisionEntry.design_3d_needs_revision
                            ? `${q2dRevisionRemarksBox(revisionEntry.design_3d.remarks, fromCustomer)}${q2dSlotUploadView('3d', [])}`
                            : q2dApprovedNoReuploadView(revisionEntry.design_3d, '3d');
                        columns.push({ label: '3D File', slot: '3d', contentHtml: threeDInner });
                    }

                    return `
        ${headerBlock}
        ${q2dFileTable(columns)}
    `;
                }

                // Kunin ang listahan ng files ng isang slot sa magkaparehong shape, kahit
                // "files" array (multi) o "url" na lang (single/legacy) ang laman ng fileData.
                function q2dGetFilesArray(fileData) {
                    if (!fileData) return [];
                    if (fileData.files && fileData.files.length) return fileData.files;
                    if (fileData.url) return [{ url: fileData.url, name: fileData.name || 'File' }];
                    return [];
                }

                // Ginagamit pa rin ng ibang parte ng page (hindi Prior Submissions) — hindi na
                // ito tinutukoy ng bagong table-style na history sa ibaba.
                function q2dFileCell(fileData) {
                    const files = (fileData && fileData.files) || [];
                    if (!files.length && !(fileData && fileData.url)) return '—';

                    const links = files.length
                        ? `<div class="space-y-1">${files.map((f, i) => `
                            <div class="flex items-center gap-1.5 min-w-0">
                                <a href="${q2dEscapeHtml(f.url)}" target="_blank"
                                    class="shrink-0 text-[#0B2540] hover:text-[#A9822C] underline underline-offset-2 whitespace-nowrap text-xs">
                                    ${files.length > 1 ? `File ${i + 1}` : 'View File'}
                                </a>
                                ${files.length > 1 ? `
                                    <span class="text-gray-400 truncate min-w-0 text-xs" title="${q2dEscapeHtml(f.name)}">${q2dEscapeHtml(f.name)}</span>
                                ` : ''}
                            </div>`).join('')}</div>`
                        : `<a href="${q2dEscapeHtml(fileData.url)}" target="_blank" class="block text-[#0B2540] hover:text-[#A9822C] underline underline-offset-2 text-xs">View File</a>`;

                    const reviewLine = fileData.review_status
                        ? `<span class="block text-[10px] font-semibold uppercase tracking-wide mt-0.5 ${fileData.review_class}">${q2dEscapeHtml(fileData.review_status)}</span>`
                        : '';
                    return `${links}${reviewLine}`;
                }

                // Isang file lang ang nilalaman ng cell na ito (row-per-file na ngayon ang
                // Prior Submissions table). Ang reviewStatus ay status ng buong slot/grupo
                // (2D, Quotation, o 3D) ng submission entry na ito — ipinapakita na ngayon sa
                // BAWAT file row sa loob ng column na iyon, hindi lang sa huling row, para
                // malinaw na "Approved" (o kung ano man ang status) ang bawat individual file.
                function q2dPastFileCell(files, idx, reviewStatus, reviewClass) {
                    const f = files[idx];
                    if (!f) return idx === 0 ? '<span class="text-gray-300">—</span>' : '';
                    const reviewHtml = reviewStatus
                        ? `<span class="inline-flex items-center text-[10px] font-semibold uppercase tracking-wide mt-0.5 ${reviewClass || ''}">${q2dIsApproved(reviewStatus) ? q2dApprovedIcon() : ''}${q2dEscapeHtml(reviewStatus)}</span>`
                        : '';
                    return `
                        <a href="${q2dEscapeHtml(f.url)}" target="_blank" title="${q2dEscapeHtml(f.name)}"
                            class="text-[#0B2540] hover:text-[#A9822C] underline underline-offset-2 truncate block">
                            ${q2dEscapeHtml(f.name)}
                        </a>
                        ${reviewHtml}
                    `;
                }

                // Prior Submissions bilang tunay na table: bawat file ay sariling row na
                // ("spreadsheet style"), imbes na nakatambak lahat sa iisang cell. Ang
                // Submitted / Result / Remarks ay iisa lang bawat submission entry, kaya
                // naka-rowspan ito sa unang row ng grupong iyon.
                function q2dRenderPastEntries(pastEntries) {
                    if (!pastEntries || pastEntries.length === 0) return '';

                    const anyBundled3d = pastEntries.some(e => e.design_3d && e.design_3d.included);

                    const colWidths = anyBundled3d
                        ? ['w-[16%]', 'w-[16%]', 'w-[13%]', 'w-[13%]', 'w-[11%]', 'w-[auto]']
                        : ['w-[20%]', 'w-[20%]', 'w-[14%]', 'w-[12%]', 'w-[auto]'];

                    let rowsHtml = '';

                    pastEntries.forEach((entry, entryIdx) => {
                        const d2Files = q2dGetFilesArray(entry.design_2d);
                        const qFiles = q2dGetFilesArray(entry.quotation);
                        const d3Files = anyBundled3d
                            ? q2dGetFilesArray(entry.design_3d && entry.design_3d.included ? entry.design_3d : null)
                            : [];
                        const rowCount = Math.max(d2Files.length, qFiles.length, d3Files.length, 1);

                        const hasPerFileRemarks = !!entry.design_2d_remarks || !!entry.quotation_remarks;
                        const remarksCell = hasPerFileRemarks
                            ? `${entry.design_2d_remarks ? `<p class="mb-1"><span class="font-semibold text-gray-700">2D:</span> ${q2dEscapeHtml(entry.design_2d_remarks).replace(/\n/g, '<br>')}</p>` : ''}${entry.quotation_remarks ? `<p><span class="font-semibold text-gray-700">Quotation:</span> ${q2dEscapeHtml(entry.quotation_remarks).replace(/\n/g, '<br>')}</p>` : ''}`
                            : (entry.remarks ? q2dEscapeHtml(entry.remarks).replace(/\n/g, '<br>') : '—');

                        // Kung customer ang nag-reject ng submission na ito, ipakita sa Result cell.
                        const customerTag = (entry.status === 'For Revision' && entry.revision_source === 'Customer')
                            ? `<span class="block text-[10px] font-semibold uppercase text-red-700 mt-0.5">By Customer</span>`
                            : '';

                        for (let i = 0; i < rowCount; i++) {
                            const isFirst = i === 0;
                            // Makapal na border sa taas ng bawat bagong submission entry (maliban sa una)
                            // para malinaw kung saan nagsisimula/natatapos ang bawat grupo.
                            const groupTop = (entryIdx > 0 && isFirst) ? 'border-t-2 border-t-gray-300' : '';

                            const threeDCell = anyBundled3d
                                ? `<td class="align-top px-4 py-2 border-r border-gray-100 text-xs ${groupTop}">${q2dPastFileCell(d3Files, i, entry.design_3d && entry.design_3d.review_status, entry.design_3d && entry.design_3d.review_class)}</td>`
                                : '';

                            // "Submitted" — ipinapakita na ngayon sa bawat file row (hindi na naka-rowspan),
                            // gamit ang timestamp ng buong submission entry (iisa lang ang batayan nito
                            // dahil sabay isinusumite ang lahat ng files sa isang entry).
                            const submittedCell = `
                                <td class="align-top px-4 py-2 border-r border-l border-gray-200 text-gray-600 text-xs ${groupTop}">${entry.submitted_at ? q2dEscapeHtml(entry.submitted_at) : '—'}</td>
                            `;

                            const sharedCells = isFirst ? `
                                <td rowspan="${rowCount}" class="align-top px-4 py-2 border-r border-gray-200 ${groupTop}">
                                    <span class="inline-flex items-center text-[11px] font-semibold uppercase tracking-wide ${entry.status_class}">
                                        ${q2dIsApproved(entry.status_label) ? q2dApprovedIcon() : ''}${q2dEscapeHtml(entry.status_label)}
                                    </span>
                                    ${customerTag}
                                    ${entry.is_late ? `<span class="block text-[10px] font-semibold uppercase text-red-700 mt-0.5">Late Submission</span>` : ''}
                                </td>
                                <td rowspan="${rowCount}" class="align-top px-4 py-2 text-gray-600 text-xs ${groupTop}">${remarksCell}</td>
                            ` : '';

                            rowsHtml += `
                                <tr class="border-b border-gray-100 last:border-b-0 hover:bg-gray-50/60">
                                    <td class="align-top px-4 py-2 border-r border-gray-100 text-xs ${groupTop}">${q2dPastFileCell(d2Files, i, entry.design_2d.review_status, entry.design_2d.review_class)}</td>
                                    <td class="align-top px-4 py-2 border-r border-gray-100 text-xs ${groupTop}">${q2dPastFileCell(qFiles, i, entry.quotation.review_status, entry.quotation.review_class)}</td>
                                    ${threeDCell}
                                    ${submittedCell}
                                    ${sharedCells}
                                </tr>
                            `;
                        }
                    });

                    return `
                    <h2 class="text-[11px] uppercase tracking-[0.2em] text-gray-500 font-semibold border-b border-gray-300 pb-2 mb-4 mt-8">
                        Prior Submissions
                    </h2>
                    <div class="overflow-x-auto border border-gray-300 mb-4">
                        <table class="w-full table-fixed text-sm">
                            <thead>
                                <tr class="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
                                    <th class="${colWidths[0]} text-left font-medium px-4 py-2 border-r border-b border-gray-200">2D File</th>
                                    <th class="${colWidths[1]} text-left font-medium px-4 py-2 border-r border-b border-gray-200">Quotation File</th>
                                    ${anyBundled3d ? `<th class="${colWidths[2]} text-left font-medium px-4 py-2 border-r border-b border-gray-200">3D File</th>` : ''}
                                    <th class="${colWidths[anyBundled3d ? 3 : 2]} text-left font-medium px-4 py-2 border-r border-l border-b border-gray-200">Submitted</th>
                                    <th class="${colWidths[anyBundled3d ? 4 : 3]} text-left font-medium px-4 py-2 border-r border-b border-gray-200">Result</th>
                                    <th class="${colWidths[anyBundled3d ? 5 : 4]} text-left font-medium px-4 py-2 border-b border-gray-200">Remarks</th>
                                </tr>
                            </thead>
                            <tbody>${rowsHtml}</tbody>
                        </table>
                    </div>
                `;
                }

                function q2dRender3dStandaloneSection(design3d) {
                    if (!design3d || design3d.include_3d || design3d.stage === 'Locked') return '';

                    const stage = design3d.stage;
                    let inner;

                    if (stage === 'Approved') {
                        inner = q2dFileTable([{ label: '3D File', slot: '3d', contentHtml: q2dSlotDoneView(design3d, false, '3d') }]);
                    } else if (stage === 'Waiting for Approval') {
                        inner = `
        <p class="text-sm text-gray-500 italic mb-3">3D file submitted, waiting for approval.</p>
        ${q2dFileTable([{ label: '3D File', slot: '3d', contentHtml: q2dSlotDoneView(design3d, false, '3d') }])}
    `;
                    } else {
                        // Stage 'For Revision' ay galing sa Designer Head O sa Customer — iisa ang remarks column.
                        const remarksBox = (stage === 'For Revision' && design3d.remarks)
                            ? q2dRevisionRemarksBox(design3d.remarks, false) : '';
                        const uploadInner = design3d.done
                            ? q2dSlotDoneView(design3d, true, '3d')
                            : q2dSlotUploadView('3d', design3d.files);
                        inner = `
        ${q2dFileTable([{ label: '3D File', slot: '3d', contentHtml: remarksBox + uploadInner }])}
        <div class="pt-3 -mt-3 border-t border-gray-300 flex items-center justify-end gap-3">
            ${!design3d.done ? `<p class="text-xs text-gray-400">Complete the 3D slot first.</p>` : ''}
            <button type="button" onclick="q2dSubmit3d()" ${design3d.done ? '' : 'disabled'}
                class="px-5 py-2 text-sm font-semibold uppercase tracking-wide text-white bg-[#0B2540] hover:bg-[#123564] disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors">
                Submit 3D for Approval
            </button>
        </div>
    `;
                    }

                    return `
    <h2 class="text-[11px] uppercase tracking-[0.2em] text-gray-500 font-semibold border-b border-gray-300 pb-2 mb-4 mt-8">
        3D File
    </h2>
    ${inner}
`;
                }

                function q2dInquirySummary(inquiry) {
                    const deadlineRow = inquiry.deadline ? `
                        <tr>
                            <td class="w-32 bg-gray-50 font-semibold text-[10px] uppercase tracking-wider text-gray-500 px-4 py-2.5 border-r border-t border-gray-200">
                                Deadline
                            </td>
                            <td class="px-4 py-2.5 border-t border-gray-200 ${inquiry.deadline_overdue ? 'text-red-700 font-semibold' : 'text-gray-900'}" colspan="3">
                                ${q2dEscapeHtml(inquiry.deadline)}${inquiry.deadline_overdue ? ' — overdue' : ''}
                            </td>
                        </tr>
                    ` : '';

                    return `
                    <table class="w-full border border-gray-300 text-sm mb-6">
                        <tbody>
                            <tr>
                                <td class="w-32 bg-amber-600 font-semibold text-[10px] uppercase tracking-wider text-white px-4 py-2.5 border-r border-gray-200">
                                    Control No  :
                                </td>
                                <td class="px-4 py-2.5 font-semibold text-gray-900">
                                    ${q2dEscapeHtml(inquiry.control_no)}
                                </td>
                                <td class="w-28 bg-amber-600 font-semibold text-[10px] uppercase tracking-wider text-white px-4 py-2.5 border-r border-l border-gray-200">
                                    Client Name :
                                </td>
                                <td class="px-4 py-2.5 text-gray-900">
                                    ${q2dEscapeHtml(inquiry.client_name)}
                                </td>
                            </tr>
                            ${deadlineRow}
                        </tbody>
                    </table>
                `;
                }

                // 2D DESIGN PROGRESS — tracker lang (0 / 50 / 100%). HINDI na ito gate:
                // pwedeng mag-upload at mag-submit ng 2D & Quotation kahit anong progress.
                // Ang customer check ay nasa Customer Review step, pagkatapos lang ma-approve ng Designer Head.
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
                        ? 'The 2D & Quotation has been submitted, so the progress is now locked.'
                        : readOnly
                        ? 'Current progress of the 2D design. Only the assigned designer can update this.'
                        : 'Update how far along the 2D design is. This is only a tracker — you can upload and submit the files for approval any time.';

                    const bar = `
            <div class="mb-1 flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Progress</span>
                <span class="text-lg font-bold text-[#0B2540]">${pct}%</span>
            </div>
            <div class="relative h-3 bg-gray-100 rounded-full overflow-hidden border border-gray-200">
                <div class="h-full bg-gradient-to-r from-[#0B2540] to-[#1d4d85] rounded-full transition-all duration-500"
                    style="width: ${pct}%;"></div>
            </div>`;

                    const controls = readOnly ? `
            <div class="flex justify-between mt-2 px-0.5">
                <span class="text-[11px] font-medium text-[#0B2540]">0%</span>
                <span class="text-[11px] font-medium ${pct >= 50 ? 'text-[#0B2540]' : 'text-gray-400'}">50%</span>
                <span class="text-[11px] font-medium ${pct >= 100 ? 'text-[#0B2540]' : 'text-gray-400'}">100%</span>
            </div>` : `
            <div class="flex gap-2 mt-3">
                ${steps.map(s => `
                    <button type="button" onclick="q2dSaveProgress('${s.value}')"
                        class="flex-1 px-4 py-2 text-sm font-semibold uppercase tracking-wide border transition-colors
                        ${step1.progress === s.value ? 'bg-[#0B2540] text-white border-[#0B2540]' : 'bg-white text-gray-600 border-gray-400 hover:bg-gray-50'}">
                        ${s.label}
                    </button>`).join('')}
            </div>`;

                    return `
        ${q2dSectionLabel('2D Design Progress')}
        <p class="text-sm text-gray-500 italic mb-4">${hint}</p>
        ${bar}
        ${controls}
        <div class="mb-8"></div>
    `;
                }

                // Pagkatapos ma-approve ng Designer Head ang lahat:
                //   1) Waiting for Customer → button papunta sa Customer Review page
                //   2) Customer Approved    → Proceed to Final / View Final
                function q2dRenderProceedToFinal(state) {
                    if (!state.ready_for_customer) return '';

                    if (!state.customer_approved) {
                        return `
    <div class="pt-4 mt-2 border-t border-gray-300 flex items-center justify-end gap-3">
        <span class="inline-block text-[11px] font-semibold uppercase tracking-wide px-2 py-0.5 border border-amber-700 text-amber-700">Waiting for Customer</span>
        <a href="${Q2D_CUSTOMER_URL}"
            class="px-5 py-2 text-sm font-semibold uppercase tracking-wide text-white bg-amber-600 hover:bg-amber-700 transition-colors">
            Customer Review
        </a>
    </div>
`;
                    }

                    const hasFinal = !!state.has_final;

                    return `
    <div class="pt-4 mt-2 border-t border-gray-300 flex items-center justify-end gap-3">
        <span class="inline-flex items-center text-[11px] font-semibold uppercase tracking-wide px-2 py-0.5 border border-green-700 text-green-800">${q2dApprovedIcon()}Customer Approved</span>
        <p class="text-xs text-gray-500">${hasFinal ? 'Final submission already created.' : 'All Initial files are approved by the customer.'}</p>
        <a href="${Q2D_FINAL_URL}"
            class="px-5 py-2 text-sm font-semibold uppercase tracking-wide transition-colors
            ${hasFinal
                            ? 'text-green-900 bg-white border border-green-900 hover:bg-green-50'
                            : 'text-white bg-green-900 hover:bg-green-800'}">
            ${hasFinal ? 'View Final' : 'Proceed to Final Submission'}
        </a>
    </div>
`;
                }

                function q2dRenderRoot(state) {
                    const root = document.getElementById('q2dRoot');

                    const design3d = state.design_3d;

                    let body = q2dInquirySummary(state.inquiry);
                    const progressLocked = !!((state.active_draft && state.active_draft.is_locked) || state.completed_entry);
                    body += q2dRenderProgress(state.step1, state.is_sales, progressLocked);
                    body += q2dRenderCuttingFeedback(state.cutting_feedback);
                    body += q2dRenderToggle(design3d);

                    if (state.active_draft) {
                        body += q2dRenderStatusBanner(state.active_draft);
                        body += q2dRenderActiveDraftSlots(state.active_draft, design3d);
                        body += q2dRenderSubmitBar(state.active_draft, design3d);
                    } else if (state.completed_entry) {
                        body += q2dRenderCompletedView(state.completed_entry, design3d, state);
                        body += q2dRender3dStandaloneSection(design3d);
                    } else {
                        body += q2dRenderRevisionOrFreshSlots(state.revision_entry);
                        body += q2dRender3dStandaloneSection(design3d);
                    }

                    body += q2dRenderPastEntries(state.past_entries);
                    body += q2dRenderProceedToFinal(state);

                    root.innerHTML = body;

                    // Hindi na nire-reset ang q2dSelected dito — para hindi mawala ang mga napili
                    // (hal. kapag nag-delete ka ng naka-save na file sa kabilang slot).
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
                            <li class="flex items-center gap-2 text-xs border border-green-200 bg-green-50 px-2 py-1">
                                <a href="${url}" target="_blank" rel="noopener" class="flex-1 truncate text-[#0B2540] hover:text-[#A9822C] underline underline-offset-2" title="${q2dEscapeHtml(f.name)}">${q2dEscapeHtml(f.name)}</a>
                                <span class="shrink-0 text-[10px] text-green-700 uppercase font-semibold">New</span>
                                <button type="button" onclick="q2dRemoveSelected('${slot}', ${i})"
                                    class="shrink-0 text-gray-400 hover:text-red-700 text-base leading-none" title="Remove" aria-label="Remove">&times;</button>
                            </li>`;
                    }).join('');

                    if (label) {
                        label.textContent = selected.length
                            ? `${selected.length} new file${selected.length > 1 ? 's' : ''} selected — click to add more`
                            : (label.dataset.idle || label.textContent);
                    }
                    if (doneBtn) doneBtn.disabled = (savedCount + selected.length) === 0;
                }

                function q2dRemoveSelected(slot, index) {
                    q2dSelected[slot].splice(index, 1);
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
                        const savedCount = Number(input.dataset.savedCount || 0);
                        const picked = Array.from(input.files);
                        input.value = ''; // para pwedeng pumili ulit ng parehong file

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
                    });

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
                                <div class="border border-gray-400 bg-gray-50 text-gray-800 text-sm px-4 py-2.5">
                                    <strong class="uppercase text-[11px] tracking-wide">Notice:</strong>
                                    ${q2dEscapeHtml(data.message || 'Unable to load this submission.')}
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
                            updatedEl.textContent = `Updated ${now.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', second: '2-digit' })}`;
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
                    if (doneBtn) doneBtn.disabled = true;

                    try {
                        const res = await fetch(Q2D_AJAX_URL, { method: 'POST', body: formData });
                        const data = await res.json();

                        if (!data.success) {
                            crmShowToast(q2dEscapeHtml(data.message || 'Something went wrong.'), 'error');
                            q2dRefreshSlotUi(slot); // ibalik ang tamang state ng button
                            return;
                        }

                        q2dSelected[slot] = [];
                        q2dLastSignature = ''; // force re-render even if signature looks unchanged
                        crmShowToast(data.message || 'Saved.');
                        await q2dFetchState();
                    } catch (e) {
                        console.error('q2dSaveSlot:', e);
                        crmShowToast('Connection error. Please try again.', 'error');
                        q2dRefreshSlotUi(slot);
                    }
                }

                // MULTI-FILE: alisin ang isang naka-save na file (habang naka-Edit / hindi pa done).
                async function q2dDeleteFile(slot, fileId) {
                    if (!confirm('Remove this file?')) return;

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
                            return;
                        }

                        q2dLastSignature = '';
                        crmShowToast(data.message || 'File removed.');
                        await q2dFetchState();
                    } catch (e) {
                        console.error('q2dDeleteFile:', e);
                        crmShowToast('Connection error. Please try again.', 'error');
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