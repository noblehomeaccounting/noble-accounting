<?php
// 2d-and-quotation.php

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
        ORDER BY created_at DESC
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
    <main class="ml-56 min-h-screen p-6">

        <div class="max-w-4xl mx-auto">

            <div class="bg-white border border-gray-300 shadow-sm rounded-lg">

                <div class="px-8 pt-6 pb-5 flex items-start justify-between border-b border-gray-300">
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

                <div class="px-8 py-6 text-sm">

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

                <div class="border-t border-gray-300 px-8 py-3 text-[11px] text-gray-400 flex justify-between">
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

        <div id="crmToastContainer"
            class="fixed bottom-6 right-6 z-[9999] flex flex-col gap-2 pointer-events-none w-30 max-w-sm px-4 sm:px-0">
        </div>

        <?php if (empty($qError)): ?>

            <?php include ROOT_PATH . '/admin/crm-management/page-3/subpage-3/feedback2d.php'; ?>

            <script>
                const Q2D_AJAX_URL = <?= json_encode(BASE_URL . '/crm2dquotationajax') ?>;
                const Q2D_FINAL_URL = <?= json_encode(BASE_URL . '/crm2dquotationfinal?id=' . $inquiryId) ?>;
                const Q2D_INQUIRY_ID = <?= (int) $inquiryId ?>;
                const Q2D_IS_SALES = <?= json_encode($isSales) ?>;

                const Q2D_POLL_INTERVAL_MS = 8000;

                let q2dPollTimer = null;
                let q2dLastSignature = '';
                let q2dPendingSelection = { '2d': false, quotation: false, '3d': false };

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

                // NEW-LATE: small reusable "Late Submission" pill.
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

                function q2dSlotDoneView(fileData, showEdit, slot) {
                    return `
                    <div class="text-sm">
                        <a href="${q2dEscapeHtml(fileData.url)}" target="_blank"
                            class="text-[#0B2540] hover:text-[#A9822C] font-semibold underline underline-offset-2 block mb-1">
                            View File
                        </a>
                        <p class="text-[11px] text-gray-500">
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

                function q2dSlotUploadView(slot, currentLabel) {
                    const inputId = q2dInputId(slot);
                    const hasCurrent = currentLabel.startsWith('Current: ');
                    return `
                    <label for="${inputId}"
                        class="flex flex-col items-center justify-center gap-1.5 border border-dashed border-gray-400 py-5 px-3 cursor-pointer hover:border-[#0B2540] hover:bg-gray-50 transition-colors">
                        ${Q2D_UPLOAD_SVG}
                        <span id="${inputId}_label" class="text-sm text-gray-600 text-center w-full truncate px-1">${q2dEscapeHtml(currentLabel)}</span>
                        <span class="text-[11px] text-gray-400">PDF only, max 15MB</span>
                    </label>
                    <input id="${inputId}" type="file" accept="application/pdf" class="hidden">
                    <a id="${inputId}_preview" href="#" target="_blank" rel="noopener"
                        class="hidden mt-1.5 items-center gap-1 text-xs font-medium text-[#0B2540] hover:text-[#A9822C] hover:underline">
                        View selected PDF &rarr;
                    </a>
                    <button type="button" id="${inputId}_done_btn" onclick="q2dSaveSlot('${slot}')" ${hasCurrent ? '' : 'disabled'}
                        class="mt-3 w-full px-4 py-2 text-sm font-semibold uppercase tracking-wide text-white bg-[#0B2540] hover:bg-[#123564] disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors">
                        Mark as Done
                    </button>
                `;
                }


                function q2dSlotUpload3dView(currentLabel) {
                    const inputId = 'design_3d_file';
                    const hasCurrent = currentLabel.startsWith('Current: ');
                    return `
                    <label for="${inputId}"
                        class="flex flex-col items-center justify-center gap-1.5 border border-dashed border-gray-400 py-5 px-3 cursor-pointer hover:border-[#0B2540] hover:bg-gray-50 transition-colors">
                        ${Q2D_UPLOAD_SVG}
                        <span id="${inputId}_label" class="text-sm text-gray-600 text-center w-full truncate px-1">${q2dEscapeHtml(currentLabel)}</span>
                        <span class="text-[11px] text-gray-400">PDF or image (JPG/PNG/WEBP), max 15MB</span>
                    </label>
                    <input id="${inputId}" type="file" accept="application/pdf,image/jpeg,image/png,image/webp" class="hidden">
                    <a id="${inputId}_preview" href="#" target="_blank" rel="noopener"
                        class="hidden mt-1.5 items-center gap-1 text-xs font-medium text-[#0B2540] hover:text-[#A9822C] hover:underline">
                        View selected file &rarr;
                    </a>
                    <button type="button" id="${inputId}_done_btn" onclick="q2dSaveSlot('3d')" ${hasCurrent ? '' : 'disabled'}
                        class="mt-3 w-full px-4 py-2 text-sm font-semibold uppercase tracking-wide text-white bg-[#0B2540] hover:bg-[#123564] disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors">
                        Mark as Done
                    </button>
                `;
                }

                function q2dRevisionRemarksBox(remarks) {
                    if (!remarks) return '';
                    return `
                    <div class="border-l-4 border-red-700 bg-red-50 px-3 py-2 mb-3">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-red-700 mb-0.5">Revision Remarks</p>
                        <p class="text-xs text-red-800">${q2dEscapeHtml(remarks).replace(/\n/g, '<br>')}</p>
                    </div>
                `;
                }


                function q2dApprovedNoReuploadView(fileData) {
                    return `
                    <div class="text-sm">
                        <span class="inline-block text-[10px] font-semibold uppercase tracking-wide px-2 py-0.5 border border-green-700 text-green-800 mb-2">Approved</span>
                        ${fileData.url ? `<a href="${q2dEscapeHtml(fileData.url)}" target="_blank" class="text-[#0B2540] hover:text-[#A9822C] font-semibold underline underline-offset-2 block mb-1">View File</a>` : ''}
                        <p class="text-[11px] text-gray-500">No re-upload needed.</p>
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

                function q2dRenderCompletedView(completedEntry, design3d) {
                    const reviewedLine = completedEntry.reviewed_at
                        ? `<p class="text-xs text-gray-400">Reviewed ${q2dEscapeHtml(completedEntry.reviewed_at)}</p>`
                        : '';

                    const twoD = completedEntry.design_2d;
                    const twoDInner = twoD.done
                        ? q2dSlotDoneView(twoD, completedEntry.design_2d_revisable, '2d')
                        : q2dSlotUploadView('2d', twoD.path ? `Current: ${twoD.filename}` : 'Click to upload revised 2D PDF');

                    const columns = [
                        { label: '2D File', slot: '2d', contentHtml: twoDInner },
                        { label: 'Quotation File', slot: 'quotation', contentHtml: q2dSlotDoneView(completedEntry.quotation, false, 'quotation') },
                    ];

                    // 3D na kasama sa Initial submission
                    if (design3d && design3d.include_3d && design3d.url) {
                        columns.push({ label: '3D File', slot: '3d', contentHtml: q2dSlotDoneView(design3d, false, '3d') });
                    }

                    return `
        <div class="flex items-center justify-between border-l-4 border-[#0B2540] bg-gray-50 px-4 py-2.5 mb-6">
            <div>
                <p class="text-sm text-gray-800">
                    <strong class="uppercase tracking-wide text-[11px]">Status:</strong>
                    <span class="inline-block text-[11px] font-semibold uppercase tracking-wide px-2 py-0.5 border border-green-700 ml-1 text-green-800">Approved</span>
                </p>
                ${reviewedLine}
            </div>
        </div>
        <p class="text-sm text-gray-500 italic mb-6">
            ${twoD.done ? 'Both files have been approved. No further action is needed.' : 'Cutting flagged an issue with the 2D file — upload a corrected copy below.'}
        </p>
        ${q2dFileTable(columns)}
    `;
                }


                function q2dRenderStatusBanner(activeDraft) {
                    return `
                    <div class="flex items-center justify-between border-l-4 border-[#0B2540] bg-gray-50 px-4 py-2.5 mb-6">
                        <p class="text-sm text-gray-800">
                            <strong class="uppercase tracking-wide text-[11px]">Status:</strong>
                            <span class="inline-block text-[11px] font-semibold uppercase tracking-wide px-2 py-0.5 border ml-1 ${activeDraft.status_class}">${q2dEscapeHtml(activeDraft.status_label)}</span>
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
        : q2dSlotUploadView('2d', twoD.path ? `Current: ${twoD.filename}` : 'Click to upload 2D PDF');

    const quotInner = quot.done
        ? q2dSlotDoneView(quot, !locked, 'quotation')
        : q2dSlotUploadView('quotation', quot.path ? `Current: ${quot.filename}` : 'Click to upload Quotation PDF');

    const columns = [
        { label: '2D File', slot: '2d', contentHtml: twoDInner },
        { label: 'Quotation File', slot: 'quotation', contentHtml: quotInner },
    ];

    if (include3d) {
        const threeDInner = design3d.done
            ? q2dSlotDoneView(design3d, !locked, '3d')
            : q2dSlotUpload3dView(design3d.path ? `Current: ${design3d.filename}` : 'Click to upload 3D file');
        columns.push({ label: '3D File', slot: '3d', contentHtml: threeDInner });
    }

    return q2dFileTable(columns);
}

                function q2dRenderSubmitBar(activeDraft, design3d, contractAmount) {
                    if (activeDraft.is_locked) return '';
                    const include3d = !!(design3d && design3d.include_3d);
                    const filesDone = activeDraft.both_done && (!include3d || design3d.done);
                    const hasContractAmount = contractAmount !== null && contractAmount !== undefined && contractAmount !== '' && Number(contractAmount) > 0;
                    const allDone = filesDone && hasContractAmount;

                    let blockerMsg = '';
                    if (!filesDone) {
                        blockerMsg = `Complete ${include3d ? 'all three files' : 'both files'} first.`;
                    } else if (!hasContractAmount) {
                        blockerMsg = 'Waiting for Sales to set the Contract Amount.';
                    }

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
                    const headerBlock = revisionEntry ? `
                    ${q2dSectionLabel('Re-upload Files')}
                    <p class="text-sm text-gray-500 italic mb-6">
                        ${revisionEntry.design_2d_needs_revision && revisionEntry.quotation_needs_revision
                        ? 'Both the 2D and Quotation files need revision. Attach the corrected PDFs below.'
                        : revisionEntry.design_2d_needs_revision
                            ? 'Only the 2D file needs revision. The Quotation file was already approved and does not need to be re-uploaded.'
                            : 'Only the Quotation file needs revision. The 2D file was already approved and does not need to be re-uploaded.'}
                    </p>
                ` : `
                    ${q2dSectionLabel('No Active Submission')}
                    <p class="text-sm text-gray-500 italic mb-6">
                        No 2D and Quotation files have been submitted yet for this inquiry. Attach a PDF below to start.
                    </p>
                `;

                    const twoDInner = (revisionEntry && !revisionEntry.design_2d_needs_revision)
                        ? q2dApprovedNoReuploadView(revisionEntry.design_2d)
                        : `${revisionEntry ? q2dRevisionRemarksBox(revisionEntry.design_2d.remarks) : ''}${q2dSlotUploadView('2d', 'Click to upload 2D PDF')}`;

                    const quotInner = (revisionEntry && !revisionEntry.quotation_needs_revision)
                        ? q2dApprovedNoReuploadView(revisionEntry.quotation)
                        : `${revisionEntry ? q2dRevisionRemarksBox(revisionEntry.quotation.remarks) : ''}${q2dSlotUploadView('quotation', 'Click to upload Quotation PDF')}`;

                     return `
        ${headerBlock}
        ${q2dFileTable([
            { label: '2D File', slot: '2d', contentHtml: twoDInner },
            { label: 'Quotation File', slot: 'quotation', contentHtml: quotInner },
        ])}
    `;
                }

                function q2dFileCell(fileData) {
                    if (!fileData.url) return '—';
                    const reviewLine = fileData.review_status
                        ? `<span class="block text-[10px] font-semibold uppercase tracking-wide mt-0.5 ${fileData.review_class}">${q2dEscapeHtml(fileData.review_status)}</span>`
                        : '';
                    return `<a href="${q2dEscapeHtml(fileData.url)}" target="_blank" class="text-[#0B2540] hover:text-[#A9822C] underline underline-offset-2">View File</a>${reviewLine}`;
                }


                function q2dRenderPastEntries(pastEntries) {
                    if (!pastEntries || pastEntries.length === 0) return '';

                    const anyBundled3d = pastEntries.some(e => e.design_3d && e.design_3d.included);

                    const rows = pastEntries.map(entry => {
                        const hasPerFileRemarks = !!entry.design_2d_remarks || !!entry.quotation_remarks;
                        const remarksCell = hasPerFileRemarks
                            ? `${entry.design_2d_remarks ? `<p class="mb-1"><span class="font-semibold text-gray-700">2D:</span> ${q2dEscapeHtml(entry.design_2d_remarks).replace(/\n/g, '<br>')}</p>` : ''}${entry.quotation_remarks ? `<p><span class="font-semibold text-gray-700">Quotation:</span> ${q2dEscapeHtml(entry.quotation_remarks).replace(/\n/g, '<br>')}</p>` : ''}`
                            : (entry.remarks ? q2dEscapeHtml(entry.remarks).replace(/\n/g, '<br>') : '—');

                        const threeDCell = anyBundled3d
                            ? `<td class="px-4 py-2 border-r border-gray-200">${entry.design_3d && entry.design_3d.included ? q2dFileCell(entry.design_3d) : '<span class="text-gray-300">—</span>'}</td>`
                            : '';

                        return `
                        <tr class="border-b border-gray-200 last:border-b-0">
                            <td class="px-4 py-2 border-r border-gray-200">${q2dFileCell(entry.design_2d)}</td>
                            <td class="px-4 py-2 border-r border-gray-200">${q2dFileCell(entry.quotation)}</td>
                            ${threeDCell}
                            <td class="px-4 py-2 border-r border-gray-200 text-gray-600 whitespace-nowrap">${entry.submitted_at ? q2dEscapeHtml(entry.submitted_at) : '—'}</td>
                            <td class="px-4 py-2 border-r border-gray-200">
                                <span class="text-[11px] font-semibold uppercase tracking-wide ${entry.status_class}">${q2dEscapeHtml(entry.status_label)}</span>
                                ${entry.is_late ? `<span class="block text-[10px] font-semibold uppercase text-red-700 mt-0.5">Late Submission</span>` : ''}
                            </td>
                            <td class="px-4 py-2 text-gray-600 max-w-[220px]">${remarksCell}</td>
                        </tr>
                    `;
                    }).join('');

                    return `
                    <h2 class="text-[11px] uppercase tracking-[0.2em] text-gray-500 font-semibold border-b border-gray-300 pb-2 mb-4 mt-8">
                        Prior Submissions
                    </h2>
                    <table class="w-full border border-gray-300 text-sm mb-4">
                        <thead>
                            <tr class="bg-gray-50 text-[10px] uppercase tracking-wider text-gray-500">
                                <th class="text-left font-medium px-4 py-2 border-r border-b border-gray-200">2D File</th>
                                <th class="text-left font-medium px-4 py-2 border-r border-b border-gray-200">Quotation File</th>
                                ${anyBundled3d ? `<th class="text-left font-medium px-4 py-2 border-r border-b border-gray-200">3D File</th>` : ''}
                                <th class="text-left font-medium px-4 py-2 border-r border-b border-gray-200">Submitted</th>
                                <th class="text-left font-medium px-4 py-2 border-r border-b border-gray-200">Result</th>
                                <th class="text-left font-medium px-4 py-2 border-b border-gray-200">Remarks</th>
                            </tr>
                        </thead>
                        <tbody>${rows}</tbody>
                    </table>
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
                        const remarksBox = (stage === 'For Revision' && design3d.remarks)
                            ? q2dRevisionRemarksBox(design3d.remarks) : '';
                        const uploadInner = design3d.done
                            ? q2dSlotDoneView(design3d, true, '3d')
                            : q2dSlotUpload3dView(design3d.path ? `Current: ${design3d.filename}` : 'Click to upload 3D file');
                        inner = `
        ${q2dFileTable([{ label: '3D File', slot: '3d', contentHtml: remarksBox + uploadInner }])}
        <div class="pt-3 -mt-3 border-t border-gray-300 flex items-center justify-end gap-3">
            ${!design3d.done ? `<p class="text-xs text-gray-400">Complete the 3D file first.</p>` : ''}
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

                function q2dRenderStep1(step1, isSales) {
                    const steps = [
                        { value: '0', label: '0%' },
                        { value: '50', label: '50%' },
                        { value: '100', label: '100%' },
                    ];

                    // SALES: view-only — progress bar, walang clickable buttons
                    if (isSales) {
                        const pct = Number(step1.progress) || 0;

                        const markers = steps.map(s => {
                            const reached = pct >= Number(s.value);
                            return `
                <div class="flex flex-col items-center" style="width: 1px;">
                    <span class="text-[11px] font-medium mt-2 ${reached ? 'text-[#0B2540]' : 'text-gray-400'}">${s.label}</span>
                </div>
            `;
                        }).join('');

                        return `
            ${q2dSectionLabel('Design Progress')}
            <p class="text-sm text-gray-500 italic mb-5">
                Current design progress. Only the assigned designer can update this and confirm customer approval.
            </p>

            <div class="mb-1 flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wide text-gray-500">Progress</span>
                <span class="text-lg font-bold text-[#0B2540]">${pct}%</span>
            </div>

            <div class="relative h-3 bg-gray-100 rounded-full overflow-hidden border border-gray-200">
                <div class="h-full bg-gradient-to-r from-[#0B2540] to-[#1d4d85] rounded-full transition-all duration-500"
                    style="width: ${pct}%;"></div>
            </div>

            <div class="flex justify-between mt-2 px-0.5">
                <span class="text-[11px] font-medium ${pct >= 0 ? 'text-[#0B2540]' : 'text-gray-400'}">0%</span>
                <span class="text-[11px] font-medium ${pct >= 50 ? 'text-[#0B2540]' : 'text-gray-400'}">50%</span>
                <span class="text-[11px] font-medium ${pct >= 100 ? 'text-[#0B2540]' : 'text-gray-400'}">100%</span>
            </div>
        `;
                    }

                    // DESIGNER: editable (dating behavior)
                    const buttons = steps.map(s => {
                        const active = step1.progress === s.value;
                        return `
            <button type="button" onclick="q2dSaveProgress('${s.value}')"
                class="flex-1 px-4 py-3 text-sm font-semibold uppercase tracking-wide border transition-colors
                ${active ? 'bg-[#0B2540] text-white border-[#0B2540]' : 'bg-white text-gray-600 border-gray-400 hover:bg-gray-50'}">
                ${s.label}
            </button>
        `;
                    }).join('');

                    const canConfirm = step1.progress === '100';

                    return `
        ${q2dSectionLabel('Design Progress')}
        <p class="text-sm text-gray-500 italic mb-4">
            Update the progress below. The 2D &amp; Quotation step unlocks once the design is confirmed by the customer.
        </p>
        <div class="flex gap-2 mb-5">${buttons}</div>
        <div class="pt-3 border-t border-gray-300 flex items-center justify-end gap-3">
            ${!canConfirm ? `<p class="text-xs text-gray-400">Progress must reach 100% first.</p>` : ''}
            <button type="button" onclick="q2dConfirmCustomer()" ${canConfirm ? '' : 'disabled'}
                class="px-5 py-2 text-sm font-semibold uppercase tracking-wide text-white bg-[#0B2540] hover:bg-[#123564] disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors">
                Confirm Customer Approval
            </button>
        </div>
    `;
                }


                // confirmed, so it's always clear when/who confirmed it.
                function q2dRenderStep1ConfirmedBadge(step1) {
                    return `
        <div class="flex items-center justify-between border-l-4 border-[#0B2540] bg-gray-50 px-4 py-2.5 mb-6">
            <p class="text-sm text-gray-800">
                <strong class="uppercase tracking-wide text-[11px]">Design Status:</strong>
                <span class="inline-block text-[9px] font-semibold uppercase tracking-wide px-2 py-0.5 border border-green-700 ml-1 text-white bg-green-600 rounded-2xl">Client Review & Approval</span>
            </p>
            ${step1.confirmed_at ? `<p class="text-xs text-gray-400">${q2dEscapeHtml(step1.confirmed_at)} by ${q2dEscapeHtml(step1.confirmed_by_name)}</p>` : ''}
        </div>
    `;
                }

                function q2dFormatCurrency(value, withSymbol = true) {
                    const num = Number(value);
                    if (value === null || value === undefined || value === '' || isNaN(num)) {
                        return withSymbol ? '—' : '';
                    }
                    const formatted = num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                    return withSymbol ? '₱' + formatted : formatted;
                }

                function q2dFormatNumberInput(rawValue) {
                    let cleaned = rawValue.replace(/[^0-9.]/g, '');
                    const parts = cleaned.split('.');
                    if (parts.length > 2) cleaned = parts[0] + '.' + parts.slice(1).join('');
                    const [intPart, decimalPart] = cleaned.split('.');
                    const formattedInt = intPart ? Number(intPart).toLocaleString('en-US') : '';
                    return decimalPart !== undefined ? formattedInt + '.' + decimalPart.slice(0, 2) : formattedInt;
                }

                function q2dRenderContractAmount(state) {
                    if (!state.quotation_done) return '';

                    const amount = state.inquiry.contract_amount;
                    const hasAmount = amount !== null && amount !== undefined && amount !== '' && Number(amount) > 0;

                    if (!state.is_sales) {
                        return `
        <table class="w-full border border-gray-300 text-md mb-6">
            <tbody><tr>
                <td class="w-40 bg-gray-50 font-semibold text-[10px] uppercase tracking-wider text-black px-4 py-2.5 border-r border-gray-200">Contract Amount :</td>
                <td class="px-4 py-2.5 text-gray-900">${hasAmount ? q2dFormatCurrency(amount) : '<span class="text-gray-400 italic">Not yet set by Sales.</span>'}</td>
            </tr></tbody>
        </table>
    `;
                    }

                    return `
    <div class="rounded-lg border border-gray-200 bg-white p-3 mb-6 shadow-sm">
    <label for="q2dContractAmountInput" class="block text-md font-medium text-black mb-2">
        Contract amount :
    </label>
    <div class="flex items-stretch gap-2">
        <div class="relative flex-1">
            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">₱</span>
            <input type="text" inputmode="decimal" id="q2dContractAmountInput"
                value="${hasAmount ? q2dFormatCurrency(amount, false) : ''}" placeholder="0.00"
                class="w-full pl-7 pr-2 py-2 text-sm rounded-lg border border-gray-300 text-gray-900
                       focus:outline-none focus:ring-2 focus:ring-[#0B2540]/20 focus:border-[#0B2540]
                       transition-colors">
        </div>
        <button type="button" id="q2dContractAmountSaveBtn" onclick="q2dSaveContractAmount()" ${hasAmount ? '' : 'disabled'}
            class="px-4 py-2 text-md font-medium text-white bg-black rounded-lg
                   hover:bg-[#123564] active:bg-[#0a1f36] transition-colors 
                   disabled:bg-gray-300 disabled:cursor-not-allowed disabled:hover:bg-gray-300
                   focus:outline-none focus:ring-2 focus:ring-[#0B2540]/40 focus:ring-offset-1">
            ${hasAmount ? 'Update' : 'Save'}
        </button>
    </div>
</div>
                `;
                }

                function q2dBindContractAmountInput() {
                    const input = document.getElementById('q2dContractAmountInput');
                    const saveBtn = document.getElementById('q2dContractAmountSaveBtn');
                    if (!input) return;

                    input.addEventListener('input', function () {
                        const cursorFromEnd = this.value.length - this.selectionStart;
                        this.value = q2dFormatNumberInput(this.value);
                        const newPos = this.value.length - cursorFromEnd;
                        this.setSelectionRange(newPos, newPos);

                        if (saveBtn) {
                            const rawValue = this.value.replace(/,/g, '').trim();
                            const isValid = rawValue !== '' && !isNaN(Number(rawValue)) && Number(rawValue) > 0;
                            saveBtn.disabled = !isValid;
                        }
                    });
                }

                async function q2dSaveContractAmount() {
                    const input = document.getElementById('q2dContractAmountInput');
                    if (!input) return;
                    const rawValue = input.value.replace(/,/g, '').trim();

                    if (!rawValue || isNaN(Number(rawValue)) || Number(rawValue) <= 0) {
                        crmShowToast('Please enter a valid contract amount.', 'error');
                        return;
                    }

                    const formData = new FormData();
                    formData.append('action', 'save_contract_amount');
                    formData.append('inquiry_id', Q2D_INQUIRY_ID);
                    formData.append('contract_amount', rawValue);

                    try {
                        const res = await fetch(Q2D_AJAX_URL, { method: 'POST', body: formData });
                        const data = await res.json();

                        if (!data.success) {
                            crmShowToast(data.message || 'Something went wrong.', 'error');
                            return;
                        }

                        q2dLastSignature = '';
                        crmShowToast(data.message || 'Saved.');
                        await q2dFetchState();
                    } catch (e) {
                        console.error('q2dSaveContractAmount:', e);
                        crmShowToast('Connection error. Please try again.', 'error');
                    }
                }

                function q2dRenderProceedToFinal(state) {
                    const done = state.completed_entry;
                    if (!done || !done.design_2d.done) return '';

                    const d3 = state.design_3d;
                    if (d3 && !d3.include_3d && d3.stage !== 'Approved') return '';


                    const hasFinal = !!state.has_final;

                    return `
    <div class="pt-4 mt-2 border-t border-gray-300 flex items-center justify-end gap-3">
        <p class="text-xs text-gray-500">${hasFinal ? 'Final submission already created.' : 'All Initial files are approved.'}</p>
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

                    // Preserve any unsaved (not-yet-clicked-Save) contract amount typing
                    // across re-renders triggered by other actions (e.g. toggling 3D).
                    const existingAmountInput = document.getElementById('q2dContractAmountInput');
                    const pendingAmountValue = existingAmountInput ? existingAmountInput.value : null;
                    const pendingAmountWasFocused = existingAmountInput === document.activeElement;
                    const pendingAmountCaret = pendingAmountWasFocused ? existingAmountInput.selectionStart : null;

                    if (!state.step1 || !state.step1.confirmed) {
                        root.innerHTML = q2dInquirySummary(state.inquiry)
                            + q2dRenderCuttingFeedback(state.cutting_feedback)
                            + q2dRenderStep1(state.step1, state.is_sales);
                        return;
                    }

                    const design3d = state.design_3d;

                    let body = q2dInquirySummary(state.inquiry);
                    body += q2dRenderStep1ConfirmedBadge(state.step1);
                    body += q2dRenderCuttingFeedback(state.cutting_feedback);
                    body += q2dRenderContractAmount(state);
                    body += q2dRenderToggle(design3d);

                    if (state.active_draft) {
                        body += q2dRenderStatusBanner(state.active_draft);
                        body += q2dRenderActiveDraftSlots(state.active_draft, design3d);
                        body += q2dRenderSubmitBar(state.active_draft, design3d, state.inquiry.contract_amount);
                    } else if (state.completed_entry) {
                        body += q2dRenderCompletedView(state.completed_entry, design3d);
                        body += q2dRender3dStandaloneSection(design3d);
                    } else {
                        body += q2dRenderRevisionOrFreshSlots(state.revision_entry);
                        body += q2dRender3dStandaloneSection(design3d);
                    }

                    body += q2dRenderPastEntries(state.past_entries);
                    body += q2dRenderProceedToFinal(state);

                    root.innerHTML = body;

                    // Restore whatever the user had typed but not yet saved.
                    if (pendingAmountValue) {
                        const newAmountInput = document.getElementById('q2dContractAmountInput');
                        if (newAmountInput && newAmountInput.value !== pendingAmountValue) {
                            newAmountInput.value = pendingAmountValue;
                            if (pendingAmountWasFocused) {
                                newAmountInput.focus();
                                if (pendingAmountCaret !== null) {
                                    newAmountInput.setSelectionRange(pendingAmountCaret, pendingAmountCaret);
                                }
                            }
                        }
                    }

                    q2dPendingSelection = { '2d': false, quotation: false, '3d': false };
                    q2dBindLabel('2d');
                    q2dBindLabel('quotation');
                    q2dBindLabel('3d');
                    q2dBindContractAmountInput();
                }


                // ── Filename label + preview link + enable "Mark as Done" once may napiling file ──
                function q2dBindLabel(slot) {
                    const inputId = q2dInputId(slot);
                    const input = document.getElementById(inputId);
                    const label = document.getElementById(`${inputId}_label`);
                    const preview = document.getElementById(`${inputId}_preview`);
                    const doneBtn = document.getElementById(`${inputId}_done_btn`);
                    if (!input || !label || !preview) return;

                    let currentObjectUrl = null;
                    const fallback = label.textContent;

                    input.addEventListener('change', function () {
                        if (currentObjectUrl) {
                            URL.revokeObjectURL(currentObjectUrl);
                            currentObjectUrl = null;
                        }

                        if (this.files.length) {
                            const file = this.files[0];
                            label.textContent = file.name;
                            label.title = file.name;

                            currentObjectUrl = URL.createObjectURL(file);
                            preview.href = currentObjectUrl;
                            preview.classList.remove('hidden');
                            preview.classList.add('flex');

                            if (doneBtn) doneBtn.disabled = false;
                            q2dPendingSelection[slot] = true;
                        } else {
                            label.textContent = fallback;
                            label.title = '';
                            preview.classList.add('hidden');
                            preview.classList.remove('flex');
                            preview.href = '#';
                            q2dPendingSelection[slot] = fallback.startsWith('Current: ') ? true : false;
                            if (doneBtn) doneBtn.disabled = !fallback.startsWith('Current: ');
                        }
                    });
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

                        const hasPendingSelection = q2dPendingSelection['2d'] || q2dPendingSelection.quotation || q2dPendingSelection['3d'];
                        if (silent && hasPendingSelection) {
                            return;
                        }

                        const signature = JSON.stringify(data.step1) + JSON.stringify(data.active_draft) + JSON.stringify(data.completed_entry)
                            + JSON.stringify(data.revision_entry) + JSON.stringify(data.past_entries)
                            + JSON.stringify(data.design_3d) + JSON.stringify(data.inquiry.contract_amount) + JSON.stringify(data.quotation_done)
                            + JSON.stringify(data.cutting_feedback);
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


                // NEW-3D: toggle "submit 3D together" on/off.
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

                // NEW-STEP1: save the 0/50/100 progress value.
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

                // NEW-STEP1: staff marks the design as confirmed by the customer.
                // Requires progress to already be at 100% (enforced server-side too).
                async function q2dConfirmCustomer() {
                    const formData = new FormData();
                    formData.append('action', 'confirm_customer');
                    formData.append('inquiry_id', Q2D_INQUIRY_ID);

                    try {
                        const res = await fetch(Q2D_AJAX_URL, { method: 'POST', body: formData });
                        const data = await res.json();

                        if (!data.success) {
                            crmShowToast(data.message || 'Something went wrong.', 'error');
                            return;
                        }

                        q2dLastSignature = '';
                        crmShowToast(data.message || 'Confirmed.');
                        await q2dFetchState();
                    } catch (e) {
                        console.error('q2dConfirmCustomer:', e);
                        crmShowToast('Connection error. Please try again.', 'error');
                    }
                }

                async function q2dSaveSlot(slot) {
                    const inputId = q2dInputId(slot);
                    const fileKey = slot === '2d' ? 'design_2d_pdf' : (slot === 'quotation' ? 'quotation_pdf' : 'design_3d_file');
                    const input = document.getElementById(inputId);

                    const formData = new FormData();
                    formData.append('action', 'save_slot');
                    formData.append('inquiry_id', Q2D_INQUIRY_ID);
                    formData.append('slot', slot);
                    if (input && input.files.length) {
                        formData.append(fileKey, input.files[0]);
                    }

                    try {
                        const res = await fetch(Q2D_AJAX_URL, { method: 'POST', body: formData });
                        const data = await res.json();

                        if (!data.success) {
                            crmShowToast(data.message || 'Something went wrong.', 'error');
                            return;
                        }

                        q2dPendingSelection[slot] = false;
                        q2dLastSignature = ''; // force re-render even if signature looks unchanged
                        crmShowToast(data.message || 'Saved.');
                        await q2dFetchState();
                    } catch (e) {
                        console.error('q2dSaveSlot:', e);
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