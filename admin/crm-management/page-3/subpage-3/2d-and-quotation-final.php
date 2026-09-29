<?php
// 2d-and-quotation-final.php  — FINAL step (opens only after the Initial is Approved)

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
    } else {
        // Gate: the Initial must be Approved first.
        $iStmt = $conn->prepare("
            SELECT status FROM noblecrm_2dquotation
            WHERE inquiry_id = ?
            ORDER BY created_at DESC, id DESC
            LIMIT 1
        ");
        $iStmt->bind_param("i", $inquiryId);
        $iStmt->execute();
        $initialRow = $iStmt->get_result()->fetch_assoc();
        $iStmt->close();

        if (!$initialRow || $initialRow['status'] !== 'Approved') {
            $qError = 'The Initial 2D and Quotation must be approved before the Final can be submitted.';
        }
    }
}

$crmBackUrl = $isSales ? (BASE_URL . '/crmsaleslist') : (BASE_URL . '/crmdesigner');
// ⚠️ verify this route matches your Initial page route
$initialPageUrl = BASE_URL . '/crm2dquotation?id=' . $inquiryId;

$qDeadlineIsOverdue = false;
if (empty($qError) && !empty($inquiry['deadline'])) {
    $qLatestStmt = $conn->prepare("
        SELECT status, is_late
        FROM noblecrm_2dquotation_final
        WHERE inquiry_id = ?
        ORDER BY created_at DESC, id DESC
        LIMIT 1
    ");
    $qLatestStmt->bind_param("i", $inquiryId);
    $qLatestStmt->execute();
    $qLatestEntry = $qLatestStmt->get_result()->fetch_assoc();
    $qLatestStmt->close();

    if ($qLatestEntry && in_array($qLatestEntry['status'], ['Approved', 'Waiting for Approval'], true)) {
        $qDeadlineIsOverdue = (bool) ($qLatestEntry['is_late'] ?? 0);
    } else {
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
    <title>2D and Quotation (Final)</title>
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
                                class="hidden align-middle ml-2 text-[10px] font-semibold uppercase tracking-wide rounded-lg px-2 py-0.1 border border-green-600 text-white bg-green-700"></span>
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
                        <?php if (empty($qError)): ?>
                            <a href="<?= htmlspecialchars($initialPageUrl) ?>"
                                class="text-xs font-medium text-gray-600 border border-gray-300 px-3 py-1.5 hover:bg-gray-50 transition-colors rounded-full">
                                <i class="fa-solid fa-file-lines"></i> View Initial
                            </a>
                        <?php endif; ?>
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

                        <!-- Rendered/refreshed by JS from crm2dquotationajaxfinal.php?action=state&stage=Final -->
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
                    <span id="q2dUpdatedAt">2D and Quotation (Final)</span>
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
                const Q2D_AJAX_URL = <?= json_encode(BASE_URL . '/crm2dquotationajaxfinal') ?>;
                const Q2D_INQUIRY_ID = <?= (int) $inquiryId ?>;
                const Q2D_IS_SALES = <?= json_encode($isSales) ?>;
                const Q2D_STAGE = 'Final';

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

                function q2dFormatCurrency(value) {
                    const num = Number(value);
                    if (value === null || value === undefined || value === '' || isNaN(num)) return '—';
                    return '₱' + num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                }

                // Every POST carries inquiry_id + stage automatically.
                async function q2dPost(action, extra = {}) {
                    const fd = new FormData();
                    fd.append('action', action);
                    fd.append('inquiry_id', Q2D_INQUIRY_ID);
                    fd.append('stage', Q2D_STAGE);
                    Object.entries(extra).forEach(([k, v]) => fd.append(k, v));
                    const res = await fetch(Q2D_AJAX_URL, { method: 'POST', body: fd });
                    return res.json();
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

                // 3D accepts PDF or image (JPG/PNG/WEBP).
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

                // "Submit 3D together" toggle — designer only, and only while it's still editable.
                function q2dRenderToggle(design3d) {
                    if (!design3d || !design3d.toggle_editable) return '';
                    const checked = design3d.include_3d ? 'checked' : '';
                    return `
                    <label class="flex items-center gap-2.5 mb-5 text-sm text-gray-700 cursor-pointer select-none">
                        <input type="checkbox" id="q2dInclude3dToggle" ${checked}
                            onchange="q2dToggleInclude3d(this.checked)" class="w-4 h-4 accent-green-600">
                        Submit 3D together with Final 2D &amp; Quotation
                        <span class="text-[11px] text-gray-400 font-normal">
                            (off = 3D unlocks only after Final 2D &amp; Quotation are approved)
                        </span>
                    </label>
                `;
                }

                function q2dFileTable(columns) {
                    const widthClass = columns.length === 3 ? 'w-1/3' : 'w-1/2';
                    const heads = columns.map(c =>
                        `<th class="${widthClass} text-left font-semibold text-[11px] uppercase tracking-wide text-gray-600 px-4 py-2 border-r border-b border-gray-300 last:border-r-0">${q2dEscapeHtml(c.label)}</th>`
                    ).join('');
                    const cells = columns.map(c =>
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

                    // 2D slot can re-open even when Approved if Cutting flagged an unresolved issue.
                    const twoD = completedEntry.design_2d;
                    const twoDInner = twoD.done
                        ? q2dSlotDoneView(twoD, completedEntry.design_2d_revisable, '2d')
                        : q2dSlotUploadView('2d', twoD.path ? `Current: ${twoD.filename}` : 'Click to upload revised 2D PDF');

                    const columns = [
                        { label: 'Final 2D File', slot: '2d', contentHtml: twoDInner },
                        { label: 'Final Quotation File', slot: 'quotation', contentHtml: q2dSlotDoneView(completedEntry.quotation, false, 'quotation') },
                    ];

                    // 3D that was bundled with this Final submission
                    if (design3d && design3d.include_3d && design3d.url) {
                        columns.push({ label: 'Final 3D File', slot: '3d', contentHtml: q2dSlotDoneView(design3d, false, '3d') });
                    }

                    return `
                    <div class="flex items-center justify-between border-l-4 border-[#0B2540] bg-gray-50 px-4 py-2.5 mb-6">
                        <div>
                            <p class="text-sm text-gray-800">
                                <strong class="uppercase tracking-wide text-[11px]">Final Status:</strong>
                                <span class="inline-block text-[11px] font-semibold uppercase tracking-wide px-2 py-0.5 border border-green-700 ml-1 text-green-800">Approved</span>
                            </p>
                            ${reviewedLine}
                        </div>
                    </div>
                    <p class="text-sm text-gray-500 italic mb-6">
                        ${twoD.done ? 'Both Final files have been approved. No further action is needed.' : 'Cutting flagged an issue with the 2D file — upload a corrected copy below.'}
                    </p>
                    ${q2dFileTable(columns)}
                `;
                }

                function q2dRenderStatusBanner(activeDraft) {
                    return `
                    <div class="flex items-center justify-between border-l-4 border-[#0B2540] bg-gray-50 px-4 py-2.5 mb-6">
                        <p class="text-sm text-gray-800">
                            <strong class="uppercase tracking-wide text-[11px]">Final Status:</strong>
                            <span class="inline-block text-[11px] font-semibold uppercase tracking-wide px-2 py-0.5 border ml-1 ${activeDraft.status_class}">${q2dEscapeHtml(activeDraft.status_label)}</span>
                            ${activeDraft.is_late ? q2dLateBadge() : ''}
                        </p>
                    </div>
                    ${activeDraft.is_locked ? `
                        <p class="text-sm text-gray-500 italic mb-6">
                            Final files have been submitted and are waiting for approval. This can no longer be edited unless sent back for revision.
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
                        : q2dSlotUploadView('2d', twoD.path ? `Current: ${twoD.filename}` : 'Click to upload Final 2D PDF');

                    const quotInner = quot.done
                        ? q2dSlotDoneView(quot, !locked, 'quotation')
                        : q2dSlotUploadView('quotation', quot.path ? `Current: ${quot.filename}` : 'Click to upload Final Quotation PDF');

                    const columns = [
                        { label: 'Final 2D File', slot: '2d', contentHtml: twoDInner },
                        { label: 'Final Quotation File', slot: 'quotation', contentHtml: quotInner },
                    ];

                    if (include3d) {
                        const threeDInner = design3d.done
                            ? q2dSlotDoneView(design3d, !locked, '3d')
                            : q2dSlotUpload3dView(design3d.path ? `Current: ${design3d.filename}` : 'Click to upload 3D file');
                        columns.push({ label: 'Final 3D File', slot: '3d', contentHtml: threeDInner });
                    }

                    return q2dFileTable(columns);
                }

                function q2dRenderSubmitBar(activeDraft, design3d) {
                    if (activeDraft.is_locked) return '';
                    const include3d = !!(design3d && design3d.include_3d);
                    const allDone = activeDraft.both_done && (!include3d || design3d.done);
                    const blockerMsg = allDone ? '' : `Complete ${include3d ? 'all three files' : 'both files'} first.`;

                    return `
    <div class="pt-3 border-t border-gray-300 flex items-center justify-end gap-3">
        ${activeDraft.is_late ? `<p class="text-xs text-red-700 font-semibold">This will be recorded as a Late Submission.</p>` : ''}
        ${blockerMsg ? `<p class="text-xs text-gray-400">${blockerMsg}</p>` : ''}
        <button type="button" id="q2dSubmitBtn" onclick="q2dSubmitFinal()" ${allDone ? '' : 'disabled'}
            class="px-5 py-2 text-sm font-semibold uppercase tracking-wide text-white bg-green-600 hover:bg-green-600 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors">
            Submit Final for Approval
        </button>
    </div>
`;
                }

                function q2dRenderRevisionOrFreshSlots(revisionEntry) {
                    const headerBlock = revisionEntry ? `
                    ${q2dSectionLabel('Re-upload Final Files')}
                    <p class="text-sm text-gray-500 italic mb-6">
                        ${revisionEntry.design_2d_needs_revision && revisionEntry.quotation_needs_revision
                        ? 'Both the Final 2D and Quotation files need revision. Attach the corrected PDFs below.'
                        : revisionEntry.design_2d_needs_revision
                            ? 'Only the Final 2D file needs revision. The Quotation file was already approved and does not need to be re-uploaded.'
                            : 'Only the Final Quotation file needs revision. The 2D file was already approved and does not need to be re-uploaded.'}
                    </p>
                ` : `
                    ${q2dSectionLabel('No Active Final Submission')}
                    <p class="text-sm text-gray-500 italic mb-6">
                        The Initial files were approved. No Final 2D and Quotation files have been submitted yet — attach a PDF below to start.
                    </p>
                `;

                    const twoDInner = (revisionEntry && !revisionEntry.design_2d_needs_revision)
                        ? q2dApprovedNoReuploadView(revisionEntry.design_2d)
                        : `${revisionEntry ? q2dRevisionRemarksBox(revisionEntry.design_2d.remarks) : ''}${q2dSlotUploadView('2d', 'Click to upload Final 2D PDF')}`;

                    const quotInner = (revisionEntry && !revisionEntry.quotation_needs_revision)
                        ? q2dApprovedNoReuploadView(revisionEntry.quotation)
                        : `${revisionEntry ? q2dRevisionRemarksBox(revisionEntry.quotation.remarks) : ''}${q2dSlotUploadView('quotation', 'Click to upload Final Quotation PDF')}`;

                    return `
                    ${headerBlock}
                    ${q2dFileTable([
                    { label: 'Final 2D File', slot: '2d', contentHtml: twoDInner },
                    { label: 'Final Quotation File', slot: 'quotation', contentHtml: quotInner },
                ])}
                `;
                }

                // 3D uploaded on its own (toggle OFF) — unlocks after the Final 2D & Quotation are Approved.
                function q2dRender3dStandaloneSection(design3d) {
                    if (!design3d || design3d.include_3d || design3d.stage === 'Locked') return '';

                    const stage = design3d.stage;
                    let inner;

                    if (stage === 'Approved') {
                        inner = q2dFileTable([{ label: 'Final 3D File', slot: '3d', contentHtml: q2dSlotDoneView(design3d, false, '3d') }]);
                    } else if (stage === 'Waiting for Approval') {
                        inner = `
        <p class="text-sm text-gray-500 italic mb-3">3D file submitted, waiting for approval.</p>
        ${q2dFileTable([{ label: 'Final 3D File', slot: '3d', contentHtml: q2dSlotDoneView(design3d, false, '3d') }])}
    `;
                    } else {
                        const remarksBox = (stage === 'For Revision' && design3d.remarks)
                            ? q2dRevisionRemarksBox(design3d.remarks) : '';
                        const uploadInner = design3d.done
                            ? q2dSlotDoneView(design3d, true, '3d')
                            : q2dSlotUpload3dView(design3d.path ? `Current: ${design3d.filename}` : 'Click to upload 3D file');
                        inner = `
        ${q2dFileTable([{ label: 'Final 3D File', slot: '3d', contentHtml: remarksBox + uploadInner }])}
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
        Final 3D File
    </h2>
    ${inner}
`;
                }

                // ═══════════════════════════════════════════════════════════
                // CONTRACT (Final only) — amount + file (PDF or image, converted
                // to webp server-side). Sales-only to edit; locked once the
                // Final submission is Waiting for Approval / Approved.
                // ═══════════════════════════════════════════════════════════
                function q2dRenderContractSection(state) {
                    const inquiry = state.inquiry;
                    const locked = state.contract_locked;
                    const hasAmount = inquiry.contract_amount !== null && inquiry.contract_amount !== undefined
                        && inquiry.contract_amount !== '' && Number(inquiry.contract_amount) > 0;
                    const hasFile = !!inquiry.contract_file_url;
                    const fileLabel = hasFile ? inquiry.contract_file_url.split('/').pop() : '';

                    const readOnlyTable = `
                    <table class="w-full border border-gray-300 text-sm mb-6">
                        <tbody>
                            <tr>
                                <td class="w-32 bg-gray-50 font-semibold text-[10px] uppercase tracking-wider text-gray-500 px-4 py-2.5 border-r border-gray-200">
                                    Amount
                                </td>
                                <td class="px-4 py-2.5 text-gray-900 font-semibold">
                                    ${hasAmount ? q2dFormatCurrency(inquiry.contract_amount) : '<span class="text-gray-400 italic font-normal">Not yet set.</span>'}
                                </td>
                            </tr>
                            <tr>
                                <td class="w-32 bg-gray-50 font-semibold text-[10px] uppercase tracking-wider text-gray-500 px-4 py-2.5 border-r border-t border-gray-200">
                                    File
                                </td>
                                <td class="px-4 py-2.5 border-t border-gray-200">
                                    ${hasFile ? `<a href="${q2dEscapeHtml(inquiry.contract_file_url)}" target="_blank" class="text-[#0B2540] hover:text-[#A9822C] font-semibold underline underline-offset-2">View File</a>` : '<span class="text-gray-400 italic">Not yet uploaded.</span>'}
                                </td>
                            </tr>
                        </tbody>
                    </table>
                `;

                    if (!Q2D_IS_SALES) {
                        if (!hasAmount && !hasFile) return '';
                        return `${q2dSectionLabel('Contract')}${readOnlyTable}`;
                    }

                    if (locked) {
                        return `${q2dSectionLabel('Contract')}${readOnlyTable}`;
                    }

                    return `
                    ${q2dSectionLabel('Contract')}
                    <div class="border border-gray-300 p-4 mb-6">
                        <label class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500 mb-1.5">
                            Contract Amount
                        </label>
                        <input id="contract_amount_input" type="text" inputmode="decimal"
                            value="${hasAmount ? inquiry.contract_amount : ''}" placeholder="0.00"
                            class="w-full border border-gray-400 px-3 py-2 text-sm mb-4 focus:outline-none focus:border-[#0B2540]">

                        <label class="block text-[11px] font-semibold uppercase tracking-wide text-gray-500 mb-1.5">
                            Contract File
                        </label>
                        <label for="contract_file_input"
                            class="flex flex-col items-center justify-center gap-1.5 border border-dashed border-gray-400 py-5 px-3 cursor-pointer hover:border-[#0B2540] hover:bg-gray-50 transition-colors">
                            ${Q2D_UPLOAD_SVG}
                            <span id="contract_file_input_label" class="text-sm text-gray-600 text-center w-full truncate px-1">${hasFile ? 'Current: ' + q2dEscapeHtml(fileLabel) : 'Click to upload contract (PDF or image)'}</span>
                            <span class="text-[11px] text-gray-400">PDF or image (JPG/PNG/WEBP), max 15MB</span>
                        </label>
                        <input id="contract_file_input" type="file" accept="application/pdf,image/jpeg,image/png,image/webp" class="hidden">
                        <a id="contract_file_input_preview" href="#" target="_blank" rel="noopener"
                            class="hidden mt-1.5 items-center gap-1 text-xs font-medium text-[#0B2540] hover:text-[#A9822C] hover:underline">
                            View selected file &rarr;
                        </a>

                        <button type="button" onclick="q2dSaveContract()"
                            class="mt-4 px-5 py-2 text-sm font-semibold uppercase tracking-wide text-white bg-[#0B2540] hover:bg-[#123564] transition-colors">
                            Save Contract
                        </button>
                    </div>
                `;
                }

                // Live thousands-separator formatting while typing, e.g. 1000000000 -> 1,000,000,000
                function q2dBindContractAmountInput() {
                    const input = document.getElementById('contract_amount_input');
                    if (!input) return;

                    function formatWithCommas(raw) {
                        let cleaned = String(raw).replace(/[^\d.]/g, '');
                        const parts = cleaned.split('.');
                        let intPart = (parts[0] || '').replace(/^0+(?=\d)/, '');
                        intPart = intPart.replace(/\B(?=(\d{3})+(?!\d))/g, ',');
                        if (parts.length > 1) {
                            return intPart + '.' + parts.slice(1).join('').slice(0, 2);
                        }
                        return intPart;
                    }

                    input.addEventListener('input', function () {
                        const before = this.value;
                        const caretPos = (typeof this.selectionStart === 'number') ? this.selectionStart : before.length;
                        const caretFromEnd = before.length - caretPos;
                        this.value = formatWithCommas(before);
                        const newPos = Math.max(0, this.value.length - caretFromEnd);
                        try { this.setSelectionRange(newPos, newPos); } catch (err) { /* ignore */ }
                    });

                    // format whatever value was pre-filled (existing contract amount)
                    if (input.value) {
                        input.value = formatWithCommas(input.value);
                    }
                }

                function q2dBindContractFileLabel() {
                    const input = document.getElementById('contract_file_input');
                    const label = document.getElementById('contract_file_input_label');
                    const preview = document.getElementById('contract_file_input_preview');
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
                        } else {
                            label.textContent = fallback;
                            label.title = '';
                            preview.classList.add('hidden');
                            preview.classList.remove('flex');
                            preview.href = '#';
                        }
                    });
                }

                async function q2dSaveContract() {
                    const amountInput = document.getElementById('contract_amount_input');
                    const fileInput = document.getElementById('contract_file_input');
                    const amount = amountInput ? amountInput.value.trim().replace(/,/g, '') : '';

                    if (!amount || isNaN(amount) || Number(amount) <= 0) {
                        crmShowToast('Please enter a valid contract amount.', 'error');
                        return;
                    }

                    const extra = { contract_amount: amount };
                    if (fileInput && fileInput.files.length) {
                        extra.contract_file = fileInput.files[0];
                    }

                    try {
                        const data = await q2dPost('save_contract_amount', extra);
                        if (!data.success) {
                            crmShowToast(data.message || 'Something went wrong.', 'error');
                            return;
                        }
                        q2dLastSignature = '';
                        crmShowToast(data.message || 'Contract saved.');
                        await q2dFetchState();
                    } catch (e) {
                        console.error('q2dSaveContract:', e);
                        crmShowToast('Connection error. Please try again.', 'error');
                    }
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
                        Prior Final Submissions
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

                function q2dInquirySummary(inquiry) {
                    const amount = inquiry.contract_amount;
                    const hasAmount = amount !== null && amount !== undefined && amount !== '' && Number(amount) > 0;
const amountRow = `
    <tr>
        <td class="w-32 whitespace-nowrap bg-gray-50 font-semibold text-[10px] uppercase tracking-wider text-gray-500 px-4 py-2 border-r border-t border-gray-200">
            Contract Amount
        </td>
        <td class="px-4 py-2 border-t border-gray-200 text-gray-900 font-semibold" colspan="3">
            ${hasAmount ? q2dFormatCurrency(amount) : '<span class="text-gray-400 italic font-normal">Not yet set by Sales.</span>'}
        </td>
    </tr>
`;
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
                            ${amountRow}
                            ${deadlineRow}
                        </tbody>
                    </table>
                `;
                }

                function q2dRenderRoot(state) {
                    const root = document.getElementById('q2dRoot');
                    const design3d = state.design_3d;

                    let body = q2dInquirySummary(state.inquiry);
                    body += q2dRenderCuttingFeedback(state.cutting_feedback);
                    body += q2dRenderToggle(design3d);

                    if (state.active_draft) {
                        body += q2dRenderStatusBanner(state.active_draft);
                        body += q2dRenderActiveDraftSlots(state.active_draft, design3d);
                        body += q2dRenderContractSection(state);
                        body += q2dRenderSubmitBar(state.active_draft, design3d);
                    } else if (state.completed_entry) {
                        body += q2dRenderCompletedView(state.completed_entry, design3d);
                        body += q2dRenderContractSection(state);
                        body += q2dRender3dStandaloneSection(design3d);
                    } else {
                        body += q2dRenderRevisionOrFreshSlots(state.revision_entry);
                        body += q2dRenderContractSection(state);
                        body += q2dRender3dStandaloneSection(design3d);
                    }

                    body += q2dRenderPastEntries(state.past_entries);

                    root.innerHTML = body;

                    q2dPendingSelection = { '2d': false, quotation: false, '3d': false };
                    q2dBindLabel('2d');
                    q2dBindLabel('quotation');
                    q2dBindLabel('3d');
                    q2dBindContractFileLabel();
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
                            q2dPendingSelection[slot] = fallback.startsWith('Current: ');
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
                        const res = await fetch(`${Q2D_AJAX_URL}?action=state&inquiry_id=${Q2D_INQUIRY_ID}&stage=${Q2D_STAGE}`);
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

                        const signature = JSON.stringify(data.active_draft) + JSON.stringify(data.completed_entry)
                            + JSON.stringify(data.revision_entry) + JSON.stringify(data.past_entries)
                            + JSON.stringify(data.cutting_feedback) + JSON.stringify(data.design_3d)
                            + JSON.stringify(data.inquiry) + JSON.stringify(data.contract_locked);
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

                // Toggle "Submit 3D together" on/off.
                async function q2dToggleInclude3d(checked) {
                    try {
                        const data = await q2dPost('save_toggle', { include_3d: checked ? '1' : '0' });
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

                async function q2dSaveSlot(slot) {
                    const input = document.getElementById(q2dInputId(slot));
                    const fileKey = slot === '2d' ? 'design_2d_pdf' : (slot === 'quotation' ? 'quotation_pdf' : 'design_3d_file');
                    const extra = { slot };
                    if (input && input.files.length) {
                        extra[fileKey] = input.files[0];
                    }

                    try {
                        const data = await q2dPost('save_slot', extra);
                        if (!data.success) {
                            crmShowToast(data.message || 'Something went wrong.', 'error');
                            return;
                        }
                        q2dPendingSelection[slot] = false;
                        q2dLastSignature = '';
                        crmShowToast(data.message || 'Saved.');
                        await q2dFetchState();
                    } catch (e) {
                        console.error('q2dSaveSlot:', e);
                        crmShowToast('Connection error. Please try again.', 'error');
                    }
                }

                async function q2dUnlock(slot) {
                    try {
                        const data = await q2dPost('unlock_slot', { slot });
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
                    try {
                        const data = await q2dPost('submit_final');
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

                // Standalone 3D submission (toggle OFF, after Final 2D & Quotation are Approved).
                async function q2dSubmit3d() {
                    try {
                        const data = await q2dPost('submit_3d');
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