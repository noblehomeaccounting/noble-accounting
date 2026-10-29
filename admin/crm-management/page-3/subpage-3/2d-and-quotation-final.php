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
$inquiry = null;

if ($inquiryId <= 0) {
    $qError = 'Missing or invalid inquiry reference.';
} else {
    $stmt = $conn->prepare("
        SELECT id, control_no, client_name, address, contact_number, status
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

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>2D and Quotation (Final)</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/crm-management/css/crm/crm2dquotationfinal.css?v=<?= @filemtime(ROOT_PATH . '/admin/crm-management/css/crm/crm2dquotationfinal.css') ?: 1 ?>">
</head>

<body class="bg-gray-100 font-['Barlow_Condensed']">
    <main class="ml-56 min-h-screen p-5 lg:p-7 bg-[#F5F6FA] overflow-x-hidden">

        <div class="max-w-6xl mx-auto">

            <!-- ═══ HEADER ═══ -->
            <section class="q2d-card overflow-hidden mb-4">
                <div class="q2d-hazard h-1.5"></div>
                <div class="p-5 flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="w-11 h-11 rounded-xl bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-flag-checkered text-lg"></i></span>
                        <div class="min-w-0">
                            <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase">CRM · 2D &amp; Quotation</p>
                            <h1 class="text-gray-900 text-lg font-semibold leading-tight flex flex-wrap items-center gap-2">
                                Final 2D &amp; Quotation
                                <span id="q2dStageBadge" class="hidden px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-300"></span>
                            </h1>
                            <?php if (empty($qError) && $inquiry): ?>
                                <p class="mt-0.5 text-[12px] text-gray-500 flex flex-wrap items-center gap-x-2">
                                    <span class="font-mono font-semibold text-amber-700"><?= htmlspecialchars($inquiry['control_no']) ?></span>
                                    <span class="text-gray-300">·</span>
                                    <span class="capitalize font-medium text-gray-700"><?= htmlspecialchars($inquiry['client_name']) ?></span>
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
                        <?php if (empty($qError)): ?>
                            <a href="<?= htmlspecialchars($initialPageUrl) ?>"
                                class="inline-flex items-center gap-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg px-3.5 py-2 hover:bg-gray-50">
                                <i class="fa-solid fa-file-lines"></i> View Initial
                            </a>
                        <?php endif; ?>
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
                    <span class="w-12 h-12 mx-auto rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center mb-3"><i class="fa-solid fa-lock text-lg"></i></span>
                    <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($qError) ?></p>
                    <div class="mt-4 flex flex-wrap justify-center gap-2">
                        <?php if ($inquiryId > 0 && $inquiry): ?>
                            <a href="<?= htmlspecialchars($initialPageUrl) ?>"
                                class="inline-flex items-center gap-2 text-xs font-semibold text-white bg-amber-700 hover:bg-amber-800 rounded-lg px-4 py-2">
                                <i class="fa-solid fa-file-lines"></i> Go to Initial
                            </a>
                        <?php endif; ?>
                        <a href="<?= htmlspecialchars($crmBackUrl) ?>"
                            class="inline-flex items-center gap-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 hover:bg-gray-50 rounded-lg px-4 py-2">
                            <i class="fa-solid fa-arrow-left"></i> Back to list
                        </a>
                    </div>
                </div>
            <?php else: ?>

                <!-- Rendered/refreshed by JS from crm2dquotationajaxfinal.php?action=state&stage=Final -->
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

        <div id="crmToastContainer"
            class="fixed bottom-6 right-6 z-[9999] flex flex-col gap-2 pointer-events-none w-full max-w-sm px-4 sm:px-0">
        </div>

        <?php if (empty($qError)): ?>

            <?php include ROOT_PATH . '/admin/crm-management/page-3/subpage-3/feedback2d.php'; ?>

            <script>
                const Q2D_AJAX_URL = <?= json_encode(BASE_URL . '/crm2dquotationajaxfinal') ?>;
                const Q2D_INQUIRY_ID = <?= (int) $inquiryId ?>;
                const Q2D_IS_SALES = <?= json_encode($isSales) ?>;
                const Q2D_STAGE = 'Final';

                const Q2D_POLL_INTERVAL_MS = 8000;
                const Q2D_MAX_BYTES = 15 * 1024 * 1024;

                let q2dPollTimer = null;
                let q2dLastSignature = '';
                let q2dPendingSelection = { '2d': false, quotation: false, '3d': false };

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

                function q2dInputId(slot) {
                    if (slot === '2d') return 'design_2d_pdf';
                    if (slot === 'quotation') return 'quotation_pdf';
                    return 'design_3d_file';
                }

                function q2dSectionLabel(text) {
                    return `<h2 class="q2d-eyebrow mb-3 flex items-center gap-2"><span class="w-1 h-3.5 rounded bg-amber-600"></span>${text}</h2>`;
                }

                const Q2D_SLOT_META = {
                    '2d': { icon: 'fa-compass-drafting', box: 'bg-blue-50 text-blue-600' },
                    quotation: { icon: 'fa-file-invoice-dollar', box: 'bg-emerald-50 text-emerald-600' },
                    '3d': { icon: 'fa-cube', box: 'bg-violet-50 text-violet-600' },
                };
                function q2dFileIcon(name) {
                    return /\.(jpe?g|png|webp)$/i.test(name || '') ? 'fa-file-image text-violet-500' : 'fa-file-pdf text-red-500';
                }

                function q2dSlotDoneView(fileData, showEdit, slot) {
                    const name = fileData.filename || (fileData.url ? String(fileData.url).split('/').pop() : 'File');
                    return `
                    <div class="text-sm">
                        <a href="${q2dEscapeHtml(fileData.url)}" target="_blank" rel="noopener"
                            class="flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 hover:border-amber-600 hover:bg-amber-50/40 transition-colors min-w-0">
                            <span class="w-8 h-8 rounded-lg bg-white border border-gray-200 flex items-center justify-center shrink-0"><i class="fa-solid ${q2dFileIcon(name)} text-sm"></i></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[13px] font-semibold text-gray-900">View file</span>
                                <span class="block text-[10.5px] text-gray-400 truncate">${q2dEscapeHtml(name)}</span>
                            </span>
                            <i class="fa-solid fa-arrow-up-right-from-square text-[11px] text-gray-400"></i>
                        </a>
                        <p class="text-[11px] text-gray-500 mt-2.5 flex items-center gap-1.5">
                            <i class="fa-solid fa-user-pen text-gray-400"></i>
                            <span class="truncate">${q2dEscapeHtml(fileData.uploaded_by_name)} (${q2dEscapeHtml(fileData.uploaded_role_label)})</span>
                        </p>
                        ${showEdit ? `
                            <button type="button" onclick="q2dUnlock('${slot}')"
                                class="mt-3 inline-flex items-center gap-1.5 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg px-3 py-1.5 hover:bg-gray-50">
                                <i class="fa-solid fa-pen text-[10px]"></i> Replace file
                            </button>
                        ` : ''}
                    </div>
                `;
                }

                // Single-file upload box. 3D accepts PDF or image (JPG/PNG/WEBP).
                function q2dUploadBox(slot, currentLabel, is3d) {
                    const inputId = q2dInputId(slot);
                    const hasCurrent = currentLabel.startsWith('Current: ');
                    const accept = is3d ? 'application/pdf,image/jpeg,image/png,image/webp' : 'application/pdf';
                    const hint = is3d ? 'PDF or JPG/PNG/WEBP · max 15MB' : 'PDF only · max 15MB';
                    return `
                    <label for="${inputId}" data-drop-for="${slot}" class="q2d-drop">
                        <span class="w-9 h-9 rounded-lg bg-white border border-gray-200 text-amber-700 flex items-center justify-center"><i class="fa-solid fa-cloud-arrow-up"></i></span>
                        <span id="${inputId}_label" class="text-[12.5px] font-semibold text-gray-700 w-full truncate px-1">${q2dEscapeHtml(currentLabel)}</span>
                        <span class="text-[10.5px] text-gray-400">${hint} · or drag here</span>
                    </label>
                    <input id="${inputId}" type="file" accept="${accept}" class="hidden">
                    <a id="${inputId}_preview" href="#" target="_blank" rel="noopener"
                        class="hidden mt-2 items-center gap-1.5 text-xs font-semibold text-amber-700 hover:underline">
                        <i class="fa-solid fa-eye"></i> View selected file
                    </a>
                    <button type="button" id="${inputId}_done_btn" onclick="q2dSaveSlot('${slot}')" ${hasCurrent ? '' : 'disabled'}
                        class="mt-3 w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-semibold text-white bg-amber-700 hover:bg-amber-800 rounded-lg shadow-sm disabled:bg-gray-100 disabled:text-gray-400 disabled:shadow-none disabled:cursor-not-allowed">
                        <i class="fa-solid fa-check"></i> Mark as done
                    </button>
                `;
                }
                function q2dSlotUploadView(slot, currentLabel) { return q2dUploadBox(slot, currentLabel, false); }
                function q2dSlotUpload3dView(currentLabel) { return q2dUploadBox('3d', currentLabel, true); }

                function q2dRevisionRemarksBox(remarks) {
                    if (!remarks) return '';
                    return `
                    <div class="rounded-lg border border-red-200 bg-red-50 px-3 py-2.5 mb-3">
                        <p class="text-[10px] font-bold uppercase tracking-wide text-red-700 mb-0.5"><i class="fa-solid fa-user-tie mr-1"></i>Revision remarks</p>
                        <p class="text-xs text-red-800 leading-relaxed">${q2dEscapeHtml(remarks).replace(/\n/g, '<br>')}</p>
                    </div>
                `;
                }

                function q2dApprovedNoReuploadView(fileData) {
                    return `
                    <div class="text-sm">
                        ${fileData.url ? `<a href="${q2dEscapeHtml(fileData.url)}" target="_blank" rel="noopener"
                            class="flex items-center gap-3 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 hover:border-amber-600 transition-colors">
                            <span class="w-8 h-8 rounded-lg bg-white border border-gray-200 flex items-center justify-center shrink-0"><i class="fa-solid fa-file-pdf text-red-500 text-sm"></i></span>
                            <span class="text-[13px] font-semibold text-gray-900 flex-1">View file</span>
                            <i class="fa-solid fa-arrow-up-right-from-square text-[11px] text-gray-400"></i></a>` : ''}
                        <p class="text-[11px] text-emerald-700 mt-2.5"><i class="fa-solid fa-circle-check mr-1"></i>Already approved — no re-upload needed.</p>
                    </div>
                `;
                }

                // "Submit 3D together" toggle — designer only, and only while it's still editable.
                function q2dRenderToggle(design3d) {
                    if (!design3d || !design3d.toggle_editable) return '';
                    const checked = design3d.include_3d ? 'checked' : '';
                    return `
                    <div class="q2d-card px-4 py-3 mb-4 flex items-center gap-3">
                        <span class="w-9 h-9 rounded-lg bg-violet-50 text-violet-600 flex items-center justify-center shrink-0"><i class="fa-solid fa-cube"></i></span>
                        <label for="q2dInclude3dToggle" class="min-w-0 flex-1 cursor-pointer select-none">
                            <span class="block text-[13px] font-semibold text-gray-900">Submit 3D together with Final 2D &amp; Quotation</span>
                            <span class="block text-[11px] text-gray-500">Off = 3D unlocks only after Final 2D &amp; Quotation are approved.</span>
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

                function q2dRenderCompletedView(completedEntry, design3d) {
                    const reviewedLine = completedEntry.reviewed_at ? `Reviewed ${q2dEscapeHtml(completedEntry.reviewed_at)}.` : '';

                    // 2D slot can re-open even when Approved if Cutting flagged an unresolved issue.
                    const twoD = completedEntry.design_2d;
                    const twoDInner = twoD.done
                        ? q2dSlotDoneView(twoD, completedEntry.design_2d_revisable, '2d')
                        : q2dSlotUploadView('2d', twoD.path ? `Current: ${twoD.filename}` : 'Upload revised 2D PDF');

                    const columns = [
                        { label: 'Final 2D File', slot: '2d', contentHtml: twoDInner, chip: twoD.done ? Q2D_CHIP_APPROVED() : Q2D_CHIP_REVISE() },
                        { label: 'Final Quotation File', slot: 'quotation', contentHtml: q2dSlotDoneView(completedEntry.quotation, false, 'quotation'), chip: Q2D_CHIP_APPROVED() },
                    ];

                    // 3D that was bundled with this Final submission
                    if (design3d && design3d.include_3d && design3d.url) {
                        columns.push({ label: 'Final 3D File', slot: '3d', contentHtml: q2dSlotDoneView(design3d, false, '3d'), chip: Q2D_CHIP_APPROVED() });
                    }

                    const callout = twoD.done
                        ? q2dCallout('green', 'fa-circle-check', 'Final approved', `Both Final files have been approved. No further action is needed. ${reviewedLine}`)
                        : q2dCallout('red', 'fa-scissors', 'Cutting flagged an issue with the 2D file', `Upload a corrected copy below. ${reviewedLine}`);

                    return `${callout}${q2dFileTable(columns)}`;
                }

                function q2dRenderStatusBanner(activeDraft) {
                    const tone = q2dStatusTone(activeDraft.status_label);
                    const text = activeDraft.is_locked
                        ? 'Final files are submitted and waiting for approval. They can no longer be edited unless sent back for revision.'
                        : 'Upload the Final files, mark them as done, then submit for approval.';
                    return q2dCallout(tone, Q2D_TONE_ICON[tone], `Final status: ${q2dEscapeHtml(activeDraft.status_label)}`, text);
                }

                function q2dRenderActiveDraftSlots(activeDraft, design3d) {
                    const twoD = activeDraft.design_2d;
                    const quot = activeDraft.quotation;
                    const locked = activeDraft.is_locked;
                    const include3d = !!(design3d && design3d.include_3d);
                    const chipFor = done => locked ? Q2D_CHIP_SUBMITTED() : (done ? Q2D_CHIP_DONE() : Q2D_CHIP_TODO());

                    const twoDInner = twoD.done
                        ? q2dSlotDoneView(twoD, !locked, '2d')
                        : q2dSlotUploadView('2d', twoD.path ? `Current: ${twoD.filename}` : 'Upload Final 2D PDF');

                    const quotInner = quot.done
                        ? q2dSlotDoneView(quot, !locked, 'quotation')
                        : q2dSlotUploadView('quotation', quot.path ? `Current: ${quot.filename}` : 'Upload Final Quotation PDF');

                    const columns = [
                        { label: 'Final 2D File', slot: '2d', contentHtml: twoDInner, chip: chipFor(twoD.done) },
                        { label: 'Final Quotation File', slot: 'quotation', contentHtml: quotInner, chip: chipFor(quot.done) },
                    ];

                    if (include3d) {
                        const threeDInner = design3d.done
                            ? q2dSlotDoneView(design3d, !locked, '3d')
                            : q2dSlotUpload3dView(design3d.path ? `Current: ${design3d.filename}` : 'Upload 3D file');
                        columns.push({ label: 'Final 3D File', slot: '3d', contentHtml: threeDInner, chip: chipFor(design3d.done) });
                    }

                    return q2dFileTable(columns);
                }

                // Submit bar na may inline confirm (walang popup)
                function q2dSubmitBlock({ id, ready, blocker, label, onConfirm, confirmText }) {
                    return `
                    <div class="q2d-card px-4 py-3.5 mb-4" id="${id}">
                        <div data-submit-idle class="flex flex-wrap items-center justify-end gap-3">
                            ${blocker ? `<p class="text-xs text-gray-400 mr-auto"><i class="fa-solid fa-circle-info mr-1"></i>${blocker}</p>` : `<p class="text-xs text-emerald-700 mr-auto"><i class="fa-solid fa-circle-check mr-1"></i>All files are ready.</p>`}
                            <button type="button" onclick="q2dAskSubmit('${id}', true)" ${ready ? '' : 'disabled'}
                                class="inline-flex items-center gap-2 px-5 py-2.5 text-xs font-semibold text-white bg-emerald-700 hover:bg-emerald-800 rounded-lg shadow-sm disabled:bg-gray-100 disabled:text-gray-400 disabled:shadow-none disabled:cursor-not-allowed">
                                <i class="fa-solid fa-paper-plane"></i> ${label}
                            </button>
                        </div>
                        <div data-submit-confirm class="hidden flex flex-wrap items-center gap-3">
                            <span class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-circle-question"></i></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[13px] font-semibold text-gray-900">Submit for approval?</p>
                                <p class="text-[11.5px] text-gray-500">${confirmText}</p>
                            </div>
                            <button type="button" onclick="q2dAskSubmit('${id}', false)" class="px-3.5 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                            <button type="button" onclick="q2dConfirmSubmit(this, ${onConfirm})" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-white bg-emerald-700 hover:bg-emerald-800 rounded-lg">
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
                    box.classList.toggle('border-emerald-300', show);
                }
                async function q2dConfirmSubmit(btn, fn) {
                    btn.disabled = true;
                    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting…';
                    await fn();
                    if (document.body.contains(btn)) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-check"></i> Yes, submit'; }
                }

                function q2dRenderSubmitBar(activeDraft, design3d) {
                    if (activeDraft.is_locked) return '';
                    const include3d = !!(design3d && design3d.include_3d);
                    const allDone = activeDraft.both_done && (!include3d || design3d.done);
                    return q2dSubmitBlock({
                        id: 'q2dSubmitMain',
                        ready: allDone,
                        blocker: allDone ? '' : `Mark ${include3d ? 'all three files' : 'both files'} as done first.`,
                        label: 'Submit Final for approval',
                        onConfirm: 'q2dSubmitFinal',
                        confirmText: `The Final ${include3d ? '2D, Quotation and 3D' : '2D and Quotation'} will be sent for approval. The contract will also be locked.`,
                    });
                }

                function q2dRenderRevisionOrFreshSlots(revisionEntry) {
                    let headerBlock;
                    if (revisionEntry) {
                        const both = revisionEntry.design_2d_needs_revision && revisionEntry.quotation_needs_revision;
                        const what = both ? 'Final 2D and Quotation' : (revisionEntry.design_2d_needs_revision ? 'Final 2D only' : 'Final Quotation only');
                        headerBlock = q2dCallout('red', 'fa-rotate-left', `Sent back for revision — ${what}`,
                            both ? 'Attach the corrected PDFs below.' : 'The other file was already approved and does not need to be re-uploaded.');
                    } else {
                        headerBlock = q2dCallout('amber', 'fa-cloud-arrow-up', 'No Final submission yet',
                            'The Initial files were approved. Attach the Final 2D and Quotation PDFs below to start.');
                    }

                    const twoDNeeds = !(revisionEntry && !revisionEntry.design_2d_needs_revision);
                    const quotNeeds = !(revisionEntry && !revisionEntry.quotation_needs_revision);

                    const twoDInner = !twoDNeeds
                        ? q2dApprovedNoReuploadView(revisionEntry.design_2d)
                        : `${revisionEntry ? q2dRevisionRemarksBox(revisionEntry.design_2d.remarks) : ''}${q2dSlotUploadView('2d', 'Upload Final 2D PDF')}`;

                    const quotInner = !quotNeeds
                        ? q2dApprovedNoReuploadView(revisionEntry.quotation)
                        : `${revisionEntry ? q2dRevisionRemarksBox(revisionEntry.quotation.remarks) : ''}${q2dSlotUploadView('quotation', 'Upload Final Quotation PDF')}`;

                    const chip = needs => !needs ? Q2D_CHIP_APPROVED() : (revisionEntry ? Q2D_CHIP_REVISE() : Q2D_CHIP_TODO());

                    return `
                    ${headerBlock}
                    ${q2dFileTable([
                        { label: 'Final 2D File', slot: '2d', contentHtml: twoDInner, chip: chip(twoDNeeds) },
                        { label: 'Final Quotation File', slot: 'quotation', contentHtml: quotInner, chip: chip(quotNeeds) },
                    ])}
                `;
                }

                // 3D uploaded on its own (toggle OFF) — unlocks after the Final 2D & Quotation are Approved.
                function q2dRender3dStandaloneSection(design3d) {
                    if (!design3d || design3d.include_3d || design3d.stage === 'Locked') return '';

                    const stage = design3d.stage;
                    let inner;

                    if (stage === 'Approved') {
                        inner = q2dFileTable([{ label: 'Final 3D File', slot: '3d', contentHtml: q2dSlotDoneView(design3d, false, '3d'), chip: Q2D_CHIP_APPROVED() }]);
                    } else if (stage === 'Waiting for Approval') {
                        inner = `
                            ${q2dCallout('blue', 'fa-hourglass-half', '3D submitted', 'Waiting for approval.')}
                            ${q2dFileTable([{ label: 'Final 3D File', slot: '3d', contentHtml: q2dSlotDoneView(design3d, false, '3d'), chip: Q2D_CHIP_SUBMITTED() }])}
                        `;
                    } else {
                        const remarksBox = (stage === 'For Revision' && design3d.remarks)
                            ? q2dRevisionRemarksBox(design3d.remarks) : '';
                        const uploadInner = design3d.done
                            ? q2dSlotDoneView(design3d, true, '3d')
                            : q2dSlotUpload3dView(design3d.path ? `Current: ${design3d.filename}` : 'Upload 3D file');
                        const chip = stage === 'For Revision' ? Q2D_CHIP_REVISE() : (design3d.done ? Q2D_CHIP_DONE() : Q2D_CHIP_TODO());
                        inner = `
                            ${q2dFileTable([{ label: 'Final 3D File', slot: '3d', contentHtml: remarksBox + uploadInner, chip }])}
                            ${q2dSubmitBlock({
                                id: 'q2dSubmit3dBox',
                                ready: !!design3d.done,
                                blocker: design3d.done ? '' : 'Mark the 3D file as done first.',
                                label: 'Submit 3D for approval',
                                onConfirm: 'q2dSubmit3d',
                                confirmText: 'The Final 3D file will be sent for approval.',
                            })}
                        `;
                    }

                    return `<div class="mt-6">${q2dSectionLabel('Final 3D file')}${inner}</div>`;
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

                    const head = (sub) => `
                        <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-file-signature text-sm"></i></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[13px] font-semibold text-gray-900">Contract</p>
                                <p class="text-[10.5px] text-gray-400">${sub}</p>
                            </div>
                            ${locked ? '<i class="fa-solid fa-lock text-[11px] text-gray-300" title="Locked"></i>' : ''}
                        </div>`;

                    const readOnly = `
                    <div class="q2d-card overflow-hidden">
                        ${head(locked ? 'Locked — Final has been submitted' : 'Set by Sales')}
                        <div class="p-4 space-y-3">
                            <div>
                                <p class="q2d-eyebrow">Amount</p>
                                <p class="text-xl font-bold mt-0.5 ${hasAmount ? 'text-gray-900' : 'text-gray-300'} tabular-nums">${hasAmount ? q2dFormatCurrency(inquiry.contract_amount) : 'Not yet set'}</p>
                            </div>
                            <div>
                                <p class="q2d-eyebrow mb-1">File</p>
                                ${hasFile ? `<a href="${q2dEscapeHtml(inquiry.contract_file_url)}" target="_blank" rel="noopener"
                                    class="flex items-center gap-2.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 hover:border-amber-600 min-w-0">
                                    <i class="fa-solid ${q2dFileIcon(fileLabel)} shrink-0"></i>
                                    <span class="text-[12.5px] font-medium text-gray-800 truncate flex-1">${q2dEscapeHtml(fileLabel)}</span>
                                    <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-gray-400"></i></a>`
                            : '<p class="text-[12.5px] text-gray-400 italic">Not yet uploaded.</p>'}
                            </div>
                        </div>
                    </div>`;

                    if (!Q2D_IS_SALES || locked) return readOnly;

                    return `
                    <div class="q2d-card overflow-hidden">
                        ${head('Required before the Final can be approved')}
                        <div class="p-4">
                            <label for="contract_amount_input" class="block text-[11.5px] font-semibold text-gray-700 mb-1">Contract amount <span class="text-red-500">*</span></label>
                            <div class="relative mb-3">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400">₱</span>
                                <input id="contract_amount_input" type="text" inputmode="decimal"
                                    value="${hasAmount ? inquiry.contract_amount : ''}" placeholder="0.00"
                                    class="q2d-field h-10 font-semibold tabular-nums" style="padding-left:1.75rem">
                            </div>

                            <p class="block text-[11.5px] font-semibold text-gray-700 mb-1">Contract file</p>
                            <label for="contract_file_input" data-drop-for="contract" class="q2d-drop">
                                <span class="w-9 h-9 rounded-lg bg-white border border-gray-200 text-amber-700 flex items-center justify-center"><i class="fa-solid fa-cloud-arrow-up"></i></span>
                                <span id="contract_file_input_label" class="text-[12.5px] font-semibold text-gray-700 w-full truncate px-1">${hasFile ? 'Current: ' + q2dEscapeHtml(fileLabel) : 'Upload contract'}</span>
                                <span class="text-[10.5px] text-gray-400">PDF or JPG/PNG/WEBP · max 15MB</span>
                            </label>
                            <input id="contract_file_input" type="file" accept="application/pdf,image/jpeg,image/png,image/webp" class="hidden">
                            <a id="contract_file_input_preview" href="#" target="_blank" rel="noopener"
                                class="hidden mt-2 items-center gap-1.5 text-xs font-semibold text-amber-700 hover:underline">
                                <i class="fa-solid fa-eye"></i> View selected file
                            </a>
                            ${hasFile ? `<a href="${q2dEscapeHtml(inquiry.contract_file_url)}" target="_blank" rel="noopener" class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-gray-600 hover:text-amber-700"><i class="fa-solid fa-file"></i> View current file</a>` : ''}

                            <button type="button" id="q2dSaveContractBtn" onclick="q2dSaveContract()"
                                class="mt-4 w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 text-xs font-semibold text-white bg-amber-700 hover:bg-amber-800 rounded-lg shadow-sm">
                                <i class="fa-solid fa-floppy-disk"></i> Save contract
                            </button>
                        </div>
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

                // Drag & drop papunta sa isang single-file input
                function q2dWireDrop(key, input) {
                    const drop = document.querySelector(`[data-drop-for="${key}"]`);
                    if (!drop || !input) return;
                    ['dragenter', 'dragover'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.add('q2d-drag'); }));
                    ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, e => { e.preventDefault(); drop.classList.remove('q2d-drag'); }));
                    drop.addEventListener('drop', e => {
                        const f = (e.dataTransfer.files || [])[0];
                        if (!f) return;
                        const dt = new DataTransfer();
                        dt.items.add(f);
                        input.files = dt.files;
                        input.dispatchEvent(new Event('change'));
                    });
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
                            if (file.size > Q2D_MAX_BYTES) {
                                crmShowToast(`"${q2dEscapeHtml(file.name)}" exceeds the 15MB limit.`, 'error');
                                this.value = '';
                                this.dispatchEvent(new Event('change'));
                                return;
                            }
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
                    q2dWireDrop('contract', input);
                }

                async function q2dSaveContract() {
                    const amountInput = document.getElementById('contract_amount_input');
                    const fileInput = document.getElementById('contract_file_input');
                    const amount = amountInput ? amountInput.value.trim().replace(/,/g, '') : '';

                    if (!amount || isNaN(amount) || Number(amount) <= 0) {
                        crmShowToast('Please enter a valid contract amount.', 'error');
                        if (amountInput) amountInput.focus();
                        return;
                    }

                    const extra = { contract_amount: amount };
                    if (fileInput && fileInput.files.length) {
                        extra.contract_file = fileInput.files[0];
                    }

                    const btn = document.getElementById('q2dSaveContractBtn');
                    const reset = () => { if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save contract'; } };
                    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…'; }

                    try {
                        const data = await q2dPost('save_contract_amount', extra);
                        if (!data.success) {
                            crmShowToast(data.message || 'Something went wrong.', 'error');
                            reset();
                            return;
                        }
                        q2dLastSignature = '';
                        crmShowToast(data.message || 'Contract saved.');
                        await q2dFetchState();
                    } catch (e) {
                        console.error('q2dSaveContract:', e);
                        crmShowToast('Connection error. Please try again.', 'error');
                        reset();
                    }
                }

                function q2dFileCell(fileData) {
                    if (!fileData.url) return '—';
                    const reviewLine = fileData.review_status
                        ? `<span class="block text-[10px] font-semibold uppercase tracking-wide mt-0.5 ${fileData.review_class}">${q2dEscapeHtml(fileData.review_status)}</span>`
                        : '';
                    return `<a href="${q2dEscapeHtml(fileData.url)}" target="_blank" class="text-amber-700 hover:underline font-semibold">View File</a>${reviewLine}`;
                }

                function q2dPastSlotBlock(label, slot, fileData) {
                    const meta = Q2D_SLOT_META[slot];
                    const rs = fileData && fileData.review_status;
                    const url = fileData && fileData.url;
                    const name = url ? String(url).split('/').pop() : '';
                    return `
                    <div class="min-w-0">
                        <div class="flex items-center gap-1.5 mb-1.5">
                            <i class="fa-solid ${meta.icon} text-[11px] text-gray-400"></i>
                            <span class="text-[10.5px] font-semibold uppercase tracking-wide text-gray-500">${label}</span>
                            ${rs ? `<span class="ml-auto">${q2dChip(rs, q2dStatusTone(rs))}</span>` : ''}
                        </div>
                        ${url ? `<a href="${q2dEscapeHtml(url)}" target="_blank" rel="noopener" title="${q2dEscapeHtml(name)}"
                            class="flex items-center gap-1.5 text-[12px] text-gray-700 hover:text-amber-700 min-w-0">
                            <i class="fa-solid ${q2dFileIcon(name)} text-[11px] shrink-0"></i><span class="truncate hover:underline">${q2dEscapeHtml(name || 'View file')}</span></a>`
                        : '<p class="text-[12px] text-gray-300">—</p>'}
                    </div>`;
                }

                function q2dRenderPastEntries(pastEntries) {
                    if (!pastEntries || pastEntries.length === 0) return '';

                    const anyBundled3d = pastEntries.some(e => e.design_3d && e.design_3d.included);

                    const cards = pastEntries.map((entry, idx) => {
                        const tone = q2dStatusTone(entry.status_label);
                        const with3d = anyBundled3d && entry.design_3d && entry.design_3d.included;
                        const hasPerFileRemarks = !!entry.design_2d_remarks || !!entry.quotation_remarks;
                        const remarksHtml = hasPerFileRemarks
                            ? `${entry.design_2d_remarks ? `<p class="mb-1"><span class="font-semibold text-gray-700">2D:</span> ${q2dEscapeHtml(entry.design_2d_remarks).replace(/\n/g, '<br>')}</p>` : ''}${entry.quotation_remarks ? `<p><span class="font-semibold text-gray-700">Quotation:</span> ${q2dEscapeHtml(entry.quotation_remarks).replace(/\n/g, '<br>')}</p>` : ''}`
                            : (entry.remarks ? q2dEscapeHtml(entry.remarks).replace(/\n/g, '<br>') : '');

                        return `
                        <details class="q2d-history q2d-card overflow-hidden" ${idx === 0 ? 'open' : ''}>
                            <summary class="px-4 py-3 flex flex-wrap items-center gap-2 hover:bg-gray-50">
                                <span class="w-7 h-7 rounded-lg bg-gray-100 text-gray-500 flex items-center justify-center text-[11px] font-bold shrink-0">#${pastEntries.length - idx}</span>
                                ${q2dChip(entry.status_label, tone, Q2D_TONE_ICON[tone])}
                                <span class="ml-auto text-[11.5px] text-gray-500 whitespace-nowrap"><i class="fa-regular fa-calendar mr-1"></i>${entry.submitted_at ? q2dEscapeHtml(entry.submitted_at) : '—'}</span>
                                <i class="fa-solid fa-chevron-down text-[10px] text-gray-400 q2d-chev"></i>
                            </summary>
                            <div class="px-4 pb-4 pt-1 border-t border-gray-100">
                                <div class="grid ${with3d ? 'sm:grid-cols-3' : 'sm:grid-cols-2'} gap-4 pt-3">
                                    ${q2dPastSlotBlock('2D', '2d', entry.design_2d)}
                                    ${q2dPastSlotBlock('Quotation', 'quotation', entry.quotation)}
                                    ${with3d ? q2dPastSlotBlock('3D', '3d', entry.design_3d) : ''}
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
                        ${q2dSectionLabel(`Prior Final submissions (${pastEntries.length})`)}
                        <div class="q2d-scroll q2d-history-list space-y-2 pr-1">${cards}</div>
                    </div>`;
                }

                // (Pinalitan na ng Contract card sa kanan — iniwan para sa compatibility)
                function q2dInquirySummary(inquiry) { return ''; }

                function q2dRenderStepper(state) {
                    const el = document.getElementById('q2dStepper');
                    if (!el) return;
                    const ad = state.active_draft;
                    const d3 = state.design_3d;
                    const submitted = !!((ad && ad.is_locked) || state.completed_entry);
                    const approved = !!state.completed_entry;
                    const steps = [
                        ['fa-file-lines', 'Initial approved', true],
                        ['fa-cloud-arrow-up', 'Final files', submitted],
                        ['fa-user-tie', 'Final approval', approved],
                    ];
                    if (d3 && !d3.include_3d && d3.stage) steps.push(['fa-cube', '3D', d3.stage === 'Approved']);
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

                    let main = q2dRenderCuttingFeedback(state.cutting_feedback);
                    main += q2dRenderToggle(design3d);

                    if (state.active_draft) {
                        main += q2dRenderStatusBanner(state.active_draft);
                        main += q2dRenderActiveDraftSlots(state.active_draft, design3d);
                        main += q2dRenderSubmitBar(state.active_draft, design3d);
                    } else if (state.completed_entry) {
                        main += q2dRenderCompletedView(state.completed_entry, design3d);
                        main += q2dRender3dStandaloneSection(design3d);
                    } else {
                        main += q2dRenderRevisionOrFreshSlots(state.revision_entry);
                        main += q2dRender3dStandaloneSection(design3d);
                    }

                    main += q2dRenderPastEntries(state.past_entries);

                    root.innerHTML = `
                    <div class="grid lg:grid-cols-[minmax(0,1fr)_300px] gap-4 items-start">
                        <div class="min-w-0">${main}</div>
                        <aside class="space-y-3 lg:sticky lg:top-4 min-w-0 order-first lg:order-none">${q2dRenderContractSection(state)}</aside>
                    </div>`;

                    q2dRenderStepper(state);

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

                        if (this.files.length && this.files[0].size > Q2D_MAX_BYTES) {
                            crmShowToast(`"${q2dEscapeHtml(this.files[0].name)}" exceeds the 15MB limit.`, 'error');
                            this.value = '';
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
                    q2dWireDrop(slot, input);
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
                                <div class="q2d-card px-6 py-10 text-center">
                                    <span class="w-12 h-12 mx-auto rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center mb-3"><i class="fa-solid fa-triangle-exclamation text-lg"></i></span>
                                    <p class="text-sm font-semibold text-gray-900">${q2dEscapeHtml(data.message || 'Unable to load this submission.')}</p>
                                </div>
                            `;
                            }
                            return;
                        }

                        q2dRenderStageBadge(data.stage);

                        // Huwag i-refresh habang may napiling file, o may tinatype sa contract.
                        const hasPendingSelection = q2dPendingSelection['2d'] || q2dPendingSelection.quotation || q2dPendingSelection['3d'];
                        const editingContract = document.activeElement && document.activeElement.id === 'contract_amount_input';
                        const contractFile = document.getElementById('contract_file_input');
                        if (silent && (hasPendingSelection || editingContract || (contractFile && contractFile.files.length))) {
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
                            updatedEl.innerHTML = `<i class="fa-solid fa-rotate mr-1"></i>Updated ${now.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', second: '2-digit' })}`;
                        }

                    } catch (e) {
                        console.error('q2dFetchState:', e);
                        if (!silent) crmShowToast('Connection error while loading this submission.', 'error');
                    }
                }

                const q2dRtLive = () => !!(window.NH_RT && window.NH_RT.enabled && window.NH_RT.isLive());
                function q2dStartPolling() {
                    if (q2dPollTimer) clearInterval(q2dPollTimer);
                    // Live → 60s fallback lang; hindi Live → dating bilis
                    q2dPollTimer = setInterval(() => q2dFetchState({ silent: true }), q2dRtLive() ? 60000 : Q2D_POLL_INTERVAL_MS);
                }

                // ─── Realtime (koneksyon ng sidebar: window.NH_RT) ───
                // Signal para sa inquiry na ito lang (approve, revise, upload, customer review, contract…) → kunin agad.
                (function q2dHookRealtime() {
                    const hook = () => {
                        if (!window.NH_RT || !window.NH_RT.enabled || q2dHookRealtime.done) return;
                        q2dHookRealtime.done = true;
                        let t = null;
                        window.NH_RT.on('*', 'changed', (payload) => {
                            if (Number(payload?.inquiry_id || 0) !== Number(Q2D_INQUIRY_ID)) return;
                            clearTimeout(t);
                            t = setTimeout(() => { if (!document.hidden) q2dFetchState({ silent: true }); }, 400);
                        });
                        window.NH_RT.onLive(live => {
                            if (!document.hidden && q2dPollTimer) q2dStartPolling(); // palitan ang bilis ng fallback polling
                            if (live) q2dFetchState({ silent: true });      // habol sa na-miss
                        });
                    };
                    hook();
                    if (!q2dHookRealtime.done) document.addEventListener('DOMContentLoaded', hook);
                })();

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

                    const doneBtn = document.getElementById(`${q2dInputId(slot)}_done_btn`);
                    const reset = () => { if (doneBtn) { doneBtn.disabled = false; doneBtn.innerHTML = '<i class="fa-solid fa-check"></i> Mark as done'; } };
                    if (doneBtn) { doneBtn.disabled = true; doneBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Uploading…'; }

                    try {
                        const data = await q2dPost('save_slot', extra);
                        if (!data.success) {
                            crmShowToast(data.message || 'Something went wrong.', 'error');
                            reset();
                            return;
                        }
                        q2dPendingSelection[slot] = false;
                        q2dLastSignature = '';
                        crmShowToast(data.message || 'Saved.');
                        await q2dFetchState();
                    } catch (e) {
                        console.error('q2dSaveSlot:', e);
                        crmShowToast('Connection error. Please try again.', 'error');
                        reset();
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