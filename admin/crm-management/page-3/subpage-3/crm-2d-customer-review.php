<?php
// crm-2d-customer-review.php — pagitan ng Initial at Final.
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SALES, ROLE_DESIGNER];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$qError = '';
$currentUserId = intval($_SESSION['account_id'] ?? 0);
$currentUserRole = $_SESSION['role'] ?? '';
$isSales = ($currentUserRole === ROLE_SALES);
$ownerColumn = $isSales ? 'sales_staff_id' : 'designer_id';

$inquiryId = intval($_GET['id'] ?? 0);
$inquiry = null;

if ($inquiryId <= 0) {
    $qError = 'Missing or invalid inquiry reference.';
} else {
    $stmt = $conn->prepare("
        SELECT id, control_no, client_name, status, deadline
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
        $qError = 'The site visit must be completed first.';
    }
}

$crmBackUrl = $isSales ? (BASE_URL . '/crmsaleslist') : (BASE_URL . '/crmdesigner');
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Review</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
</head>

<body class="bg-gray-100 font-['Barlow_Condensed']">
    <main class="ml-56 min-h-screen p-5 lg:p-7 bg-[#F5F6FA] overflow-x-hidden">
        <div class="max-w-5xl mx-auto">

            <!-- ═══ HEADER ═══ -->
            <section class="bg-white border border-gray-200 rounded-2xl shadow-sm overflow-hidden mb-4">
                <div class="h-1.5 bg-[repeating-linear-gradient(-45deg,#f59e0b_0_10px,#1f2937_10px_20px)]"></div>
                <div class="p-5 flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-user-check text-lg"></i></span>
                        <div class="min-w-0">
                            <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase">CRM · 2D &amp; Quotation</p>
                            <h1 class="text-gray-900 text-lg font-semibold leading-tight flex flex-wrap items-center gap-2">
                                Customer Review
                                <span id="crStatusBadge" class="hidden"></span>
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
                        <?php if (empty($qError)): ?>
                            <a href="<?= htmlspecialchars(BASE_URL . '/crm2dquotation?id=' . $inquiryId) ?>"
                                class="inline-flex items-center gap-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg px-3.5 py-2 hover:bg-gray-50">
                                <i class="fa-solid fa-folder-open"></i> Initial files
                            </a>
                        <?php endif; ?>
                        <a href="<?= htmlspecialchars($crmBackUrl) ?>"
                            class="inline-flex items-center gap-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg px-3.5 py-2 hover:bg-gray-50">
                            <i class="fa-solid fa-arrow-left"></i> Back to list
                        </a>
                    </div>
                </div>

                <?php if (empty($qError)): ?>
                    <!-- Stepper -->
                    <div class="px-5 pb-4">
                        <div id="crStepper" class="flex items-center gap-2 text-[11px] font-semibold">
                            <?php
                            $crSteps = [['fa-cloud-arrow-up', 'Initial files', 'done'], ['fa-user-tie', 'Head approval', 'done'], ['fa-user-check', 'Customer review', 'current'], ['fa-flag-checkered', 'Final', 'todo']];
                            foreach ($crSteps as $i => [$ic, $lbl, $st]): ?>
                                <?php if ($i > 0): ?><span data-step-line="<?= $i ?>" class="flex-1 min-w-[12px] h-0.5 rounded <?= $st !== 'todo' ? 'bg-amber-700' : 'bg-gray-200' ?>"></span><?php endif; ?>
                                <span data-step="<?= $i ?>" class="inline-flex items-center gap-1.5 whitespace-nowrap <?= $st === 'done' ? 'text-amber-800' : ($st === 'current' ? 'text-gray-900' : 'text-gray-400') ?>">
                                    <span class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] <?= $st === 'done' ? 'bg-amber-700 text-white' : ($st === 'current' ? 'bg-amber-50 text-amber-700 ring-2 ring-amber-600' : 'bg-gray-100 text-gray-400') ?>">
                                        <i class="fa-solid <?= $st === 'done' ? 'fa-check' : $ic ?>"></i>
                                    </span>
                                    <span class="hidden sm:inline"><?= htmlspecialchars($lbl) ?></span>
                                </span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </section>

            <?php if (!empty($qError)): ?>
                <div class="bg-white border border-gray-200 rounded-2xl shadow-sm px-6 py-10 text-center">
                    <span class="w-12 h-12 mx-auto rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center mb-3"><i class="fa-solid fa-triangle-exclamation text-lg"></i></span>
                    <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($qError) ?></p>
                    <a href="<?= htmlspecialchars($crmBackUrl) ?>"
                        class="mt-4 inline-flex items-center gap-2 text-xs font-semibold text-white bg-amber-700 hover:bg-amber-800 rounded-lg px-4 py-2">
                        <i class="fa-solid fa-arrow-left"></i> Back to list
                    </a>
                </div>
            <?php else: ?>
                <div id="crRoot">
                    <div class="space-y-3">
                        <div class="h-16 rounded-xl bg-white border border-gray-200 animate-pulse"></div>
                        <div class="h-40 rounded-xl bg-white border border-gray-200 animate-pulse"></div>
                        <div class="h-40 rounded-xl bg-white border border-gray-200 animate-pulse"></div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="mt-5 text-[11px] text-gray-400 flex flex-wrap justify-between gap-2">
                <span>Generated on <?= date('F d, Y g:i A') ?></span>
                <span id="crUpdatedAt"><i class="fa-solid fa-rotate mr-1"></i>Live · refreshes every few seconds</span>
            </div>
        </div>

        <div id="crmToastContainer"
            class="fixed bottom-6 right-6 z-[9999] flex flex-col gap-2 pointer-events-none w-full max-w-sm px-4 sm:px-0">
        </div>

        <?php if (empty($qError)): ?>
            <script>
                const Q2D_AJAX_URL = <?= json_encode(BASE_URL . '/crm2dquotationajax') ?>;
                const Q2D_FINAL_URL = <?= json_encode(BASE_URL . '/crm2dquotationfinal?id=' . $inquiryId) ?>;
                const Q2D_BACK_TO_2D_URL = <?= json_encode(BASE_URL . '/crm2dquotation?id=' . $inquiryId) ?>;
                const Q2D_INQUIRY_ID = <?= (int) $inquiryId ?>;

                // { '2d': {decision, remarks}, quotation: {...}, '3d': {...} } — mga napili pa lang, hindi pa saved
                let crDecisions = {};
                let crSubmitting = false;
                let crLastSignature = '';
                let crPollTimer = null;
                const CR_POLL_MS = 10000;

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
                        <button type="button" class="shrink-0 inline-flex items-center justify-center w-5 h-5 text-gray-400 hover:text-gray-600" aria-label="Close"><i class="fa-solid fa-xmark text-xs"></i></button>`;
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

                function crHasPending() {
                    return Object.keys(crDecisions).length > 0;
                }

                // Icon at kulay bawat slot
                const CR_SLOT_META = {
                    '2d': { icon: 'fa-compass-drafting', box: 'bg-blue-50 text-blue-600' },
                    quotation: { icon: 'fa-file-invoice-dollar', box: 'bg-emerald-50 text-emerald-600' },
                    '3d': { icon: 'fa-cube', box: 'bg-violet-50 text-violet-600' },
                };
                function crFileIcon(name) {
                    return /\.(jpe?g|png|webp)$/i.test(name || '') ? 'fa-file-image text-violet-500' : 'fa-file-pdf text-red-500';
                }

                // Listahan ng files — may sariling scroll kapag marami
                function crFilesHtml(files) {
                    if (!files || !files.length) return '<p class="text-xs text-gray-300">No files</p>';
                    return `<div class="max-h-48 overflow-y-auto pr-1 space-y-1.5 [scrollbar-width:thin]">${files.map((f, i) => `
                        <a href="${q2dEscapeHtml(f.url)}" target="_blank" rel="noopener" title="${q2dEscapeHtml(f.name)}"
                            class="flex items-center gap-2.5 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 hover:border-amber-600 hover:bg-amber-50/40 transition-colors min-w-0">
                            <i class="fa-solid ${crFileIcon(f.name)} shrink-0"></i>
                            <span class="flex-1 min-w-0 truncate text-[12.5px] font-medium text-gray-800">${q2dEscapeHtml(f.name)}</span>
                            <span class="text-[10px] text-gray-400 shrink-0">${i + 1}/${files.length}</span>
                            <i class="fa-solid fa-arrow-up-right-from-square text-[10px] text-gray-400 shrink-0"></i>
                        </a>`).join('')}</div>`;
                }

                // (Pinalitan ng header — iniwan para sa compatibility)
                function crInquirySummary(inquiry) { return ''; }

                function crDecisionCell(s) {
                    // Okay na dati → locked, walang choices
                    if (s.decision === 'Okay') {
                        return `<div class="rounded-xl border border-emerald-200 bg-emerald-50 px-3.5 py-3 flex items-center gap-2.5">
                                    <i class="fa-solid fa-lock text-emerald-600"></i>
                                    <div><p class="text-[12.5px] font-semibold text-emerald-800">Customer okay</p>
                                    <p class="text-[11px] text-emerald-700/80">No further action needed.</p></div>
                                </div>`;
                    }

                    const cur = crDecisions[s.slot] || {};
                    const isOkay = cur.decision === 'Okay';
                    const isRevise = cur.decision === 'Revise';

                    const pendingNote = (s.decision === 'Revise')
                        ? `<p class="text-[11px] font-semibold text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-2.5 py-1.5 mb-2.5"><i class="fa-solid fa-rotate mr-1"></i>Revised file — waiting for the customer's decision again</p>`
                        : '';

                    return `
                    ${pendingNote}
                    <p class="text-[10.5px] font-semibold uppercase tracking-wide text-gray-500 mb-1.5">Customer decision <span class="text-red-500">*</span></p>
                    <div class="grid grid-cols-2 gap-2">
                        <label class="cursor-pointer select-none">
                            <input type="radio" name="d_${s.slot}" value="Okay" class="peer sr-only" ${isOkay ? 'checked' : ''}
                                onchange="crSetDecision('${s.slot}', 'Okay')">
                            <span class="flex items-center justify-center gap-1.5 h-10 rounded-lg border border-gray-200 bg-white text-xs font-semibold text-gray-600 hover:border-gray-300
                                peer-checked:bg-emerald-700 peer-checked:text-white peer-checked:border-emerald-700 peer-focus-visible:ring-2 peer-focus-visible:ring-emerald-300 transition-colors">
                                <i class="fa-solid fa-check"></i>Okay
                            </span>
                        </label>
                        <label class="cursor-pointer select-none">
                            <input type="radio" name="d_${s.slot}" value="Revise" class="peer sr-only" ${isRevise ? 'checked' : ''}
                                onchange="crSetDecision('${s.slot}', 'Revise')">
                            <span class="flex items-center justify-center gap-1.5 h-10 rounded-lg border border-gray-200 bg-white text-xs font-semibold text-gray-600 hover:border-gray-300
                                peer-checked:bg-red-700 peer-checked:text-white peer-checked:border-red-700 peer-focus-visible:ring-2 peer-focus-visible:ring-red-300 transition-colors">
                                <i class="fa-solid fa-rotate-left"></i>Needs revision
                            </span>
                        </label>
                    </div>
                    <textarea id="rm_${s.slot}" rows="3" oninput="crSetRemarks('${s.slot}', this.value)"
                        placeholder="What does the customer want changed? (required)"
                        class="${isRevise ? '' : 'hidden'} mt-2 w-full rounded-lg border border-red-300 bg-white text-xs px-3 py-2 resize-y outline-none focus:border-red-500 focus:ring-2 focus:ring-red-100">${q2dEscapeHtml(cur.remarks || '')}</textarea>`;
                }

                function crSlotCard(s) {
                    const m = CR_SLOT_META[s.slot] || CR_SLOT_META['2d'];
                    const cur = crDecisions[s.slot] || {};
                    const locked = s.decision === 'Okay';
                    const ring = locked ? 'border-gray-200' : cur.decision === 'Okay' ? 'border-emerald-300' : cur.decision === 'Revise' ? 'border-red-300' : 'border-gray-200';
                    const count = (s.data.files || []).length;
                    const chip = locked
                        ? '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-semibold border bg-emerald-50 text-emerald-700 border-emerald-200"><i class="fa-solid fa-circle-check text-[9px]"></i>Okay</span>'
                        : cur.decision === 'Okay' ? '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-semibold border bg-emerald-50 text-emerald-700 border-emerald-200"><i class="fa-solid fa-check text-[9px]"></i>Okay</span>'
                            : cur.decision === 'Revise' ? '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-semibold border bg-red-50 text-red-700 border-red-200"><i class="fa-solid fa-rotate-left text-[9px]"></i>Revision</span>'
                                : '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-semibold border bg-amber-50 text-amber-700 border-amber-200"><i class="fa-solid fa-hourglass-half text-[9px]"></i>Waiting</span>';

                    return `
                    <article data-slot-card="${s.slot}" class="bg-white border ${ring} rounded-2xl shadow-sm overflow-hidden transition-colors">
                        <div class="px-4 py-3 border-b border-gray-100 flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-lg ${m.box} flex items-center justify-center shrink-0"><i class="fa-solid ${m.icon} text-sm"></i></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-[13px] font-semibold text-gray-900 truncate">${q2dEscapeHtml(s.label)}</p>
                                <p class="text-[10.5px] text-gray-400">${count} file${count === 1 ? '' : 's'}${s.data.uploaded_by_name && s.data.uploaded_by_name !== '—' ? ` · ${q2dEscapeHtml(s.data.uploaded_by_name)} (${q2dEscapeHtml(s.data.uploaded_role_label)})` : ''}</p>
                            </div>
                            <span data-slot-chip>${chip}</span>
                        </div>
                        <div class="p-4 grid md:grid-cols-2 gap-4">
                            <div class="min-w-0">
                                <p class="text-[10.5px] font-semibold uppercase tracking-wide text-gray-500 mb-1.5">Attachments</p>
                                ${crFilesHtml(s.data.files)}
                            </div>
                            <div class="min-w-0">${crDecisionCell(s)}</div>
                        </div>
                    </article>`;
                }

                function crRender(data) {
                    const root = document.getElementById('crRoot');
                    const approved = data.status === 'Customer Approved';

                    const callout = approved
                        ? `<div class="rounded-xl border border-emerald-200 bg-emerald-50 text-emerald-900 px-4 py-3.5 mb-4 flex flex-wrap items-center gap-3">
                                <span class="w-9 h-9 rounded-lg bg-white/70 flex items-center justify-center shrink-0"><i class="fa-solid fa-circle-check"></i></span>
                                <div class="min-w-0 flex-1"><p class="text-sm font-semibold">Customer approved</p>
                                <p class="text-xs opacity-80">The customer has approved all files. You may proceed to the Final submission.</p></div>
                                <a href="${Q2D_FINAL_URL}" class="inline-flex items-center gap-2 px-4 py-2 text-xs font-semibold text-white bg-emerald-700 hover:bg-emerald-800 rounded-lg shadow-sm shrink-0">Proceed to Final <i class="fa-solid fa-arrow-right"></i></a>
                           </div>`
                        : `<div class="rounded-xl border border-amber-200 bg-amber-50 text-amber-900 px-4 py-3.5 mb-4 flex items-center gap-3">
                                <span class="w-9 h-9 rounded-lg bg-white/70 flex items-center justify-center shrink-0"><i class="fa-solid fa-user-clock"></i></span>
                                <div class="min-w-0 flex-1"><p class="text-sm font-semibold">Waiting for the customer's decision</p>
                                <p class="text-xs opacity-80">Choose Okay or Needs revision for each file. Files that need revision go back to the designer; files marked Okay stay locked.</p></div>
                           </div>`;

                    let footer = '';
                    if (!approved) {
                        footer = `
                        <div id="crSubmitBar" class="sticky bottom-4 z-10 mt-4 bg-white border border-gray-200 rounded-2xl shadow-lg px-4 py-3.5">
                            <div data-submit-idle class="flex flex-wrap items-center gap-3">
                                <div class="min-w-0 flex-1">
                                    <p id="crSubmitSummary" class="text-[12px] font-semibold text-gray-700"></p>
                                    <p id="crSubmitHint" class="text-[11px] text-gray-400"></p>
                                </div>
                                <button type="button" id="crSubmitBtn" onclick="crSubmit()"
                                    class="inline-flex items-center gap-2 px-5 py-2.5 text-xs font-semibold text-white bg-amber-700 hover:bg-amber-800 rounded-lg shadow-sm disabled:bg-gray-100 disabled:text-gray-400 disabled:shadow-none disabled:cursor-not-allowed">
                                    <i class="fa-solid fa-paper-plane"></i> Submit customer decision
                                </button>
                            </div>
                            <div data-submit-confirm class="hidden flex flex-wrap items-center gap-3">
                                <span class="w-9 h-9 rounded-lg bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-circle-question"></i></span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-[13px] font-semibold text-gray-900">Submit the customer's decision?</p>
                                    <p id="crConfirmText" class="text-[11.5px] text-gray-600"></p>
                                </div>
                                <button type="button" onclick="crAskConfirm(false)" class="px-3.5 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">Go back</button>
                                <button type="button" id="crConfirmYes" onclick="crDoSubmit()" class="inline-flex items-center gap-1.5 px-3.5 py-2 text-xs font-semibold text-white bg-amber-700 hover:bg-amber-800 rounded-lg">
                                    <i class="fa-solid fa-check"></i> Yes, submit
                                </button>
                            </div>
                        </div>`;
                    }

                    root.innerHTML = `
                        ${crInquirySummary(data.inquiry)}
                        ${callout}
                        <div class="space-y-3">${data.slots.map(crSlotCard).join('')}</div>
                        ${footer}`;

                    // badge sa header
                    const badge = document.getElementById('crStatusBadge');
                    badge.textContent = data.status;
                    badge.className = 'inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider border '
                        + (approved ? 'bg-emerald-50 text-emerald-700 border-emerald-300' : 'bg-amber-50 text-amber-700 border-amber-300');

                    // stepper: kapag approved na, tapos na rin ang "Customer review"
                    if (approved) crMarkStepDone(2, 3);

                    crUpdateSubmitState(data);
                }

                function crMarkStepDone(doneIdx, nextIdx) {
                    const done = document.querySelector(`#crStepper [data-step="${doneIdx}"]`);
                    const next = document.querySelector(`#crStepper [data-step="${nextIdx}"]`);
                    const line = document.querySelector(`#crStepper [data-step-line="${nextIdx}"]`);
                    if (done) {
                        done.className = 'inline-flex items-center gap-1.5 whitespace-nowrap text-amber-800';
                        done.firstElementChild.className = 'w-6 h-6 rounded-full flex items-center justify-center text-[10px] bg-amber-700 text-white';
                        done.firstElementChild.innerHTML = '<i class="fa-solid fa-check"></i>';
                    }
                    if (next) {
                        next.className = 'inline-flex items-center gap-1.5 whitespace-nowrap text-gray-900';
                        next.firstElementChild.className = 'w-6 h-6 rounded-full flex items-center justify-center text-[10px] bg-amber-50 text-amber-700 ring-2 ring-amber-600';
                    }
                    if (line) line.className = 'flex-1 min-w-[12px] h-0.5 rounded bg-amber-700';
                }

                // Na-enable lang ang submit kapag may desisyon na ang lahat ng slot na hindi pa Okay,
                // at may remarks ang bawat Revise.
                function crUpdateSubmitState(data) {
                    const btn = document.getElementById('crSubmitBtn');
                    const hint = document.getElementById('crSubmitHint');
                    const summary = document.getElementById('crSubmitSummary');
                    if (!btn) return;

                    const open = data.slots.filter(s => s.decision !== 'Okay');
                    let msg = '';
                    let ok = true;

                    for (const s of open) {
                        const d = crDecisions[s.slot];
                        if (!d || !d.decision) { ok = false; msg = 'Choose a decision for every file first.'; break; }
                        if (d.decision === 'Revise' && !(d.remarks || '').trim()) { ok = false; msg = 'Remarks are required for files that need revision.'; break; }
                    }

                    const decided = open.filter(s => (crDecisions[s.slot] || {}).decision).length;
                    const nOk = open.filter(s => (crDecisions[s.slot] || {}).decision === 'Okay').length;
                    const nRev = open.filter(s => (crDecisions[s.slot] || {}).decision === 'Revise').length;
                    if (summary) {
                        summary.innerHTML = `${decided} of ${open.length} decided`
                            + (nOk ? ` · <span class="text-emerald-700">${nOk} okay</span>` : '')
                            + (nRev ? ` · <span class="text-red-700">${nRev} for revision</span>` : '');
                    }

                    btn.disabled = !ok || crSubmitting;
                    if (hint) {
                        hint.textContent = ok ? 'Ready to submit.' : msg;
                        hint.className = 'text-[11px] ' + (ok ? 'text-emerald-700' : 'text-gray-400');
                    }

                    // update ang chip at border ng bawat card
                    open.forEach(s => {
                        const card = document.querySelector(`[data-slot-card="${s.slot}"]`);
                        if (!card) return;
                        const d = (crDecisions[s.slot] || {}).decision;
                        card.classList.toggle('border-emerald-300', d === 'Okay');
                        card.classList.toggle('border-red-300', d === 'Revise');
                        card.classList.toggle('border-gray-200', !d);
                        const chip = card.querySelector('[data-slot-chip]');
                        if (chip) chip.innerHTML = d === 'Okay'
                            ? '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-semibold border bg-emerald-50 text-emerald-700 border-emerald-200"><i class="fa-solid fa-check text-[9px]"></i>Okay</span>'
                            : d === 'Revise' ? '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-semibold border bg-red-50 text-red-700 border-red-200"><i class="fa-solid fa-rotate-left text-[9px]"></i>Revision</span>'
                                : '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10.5px] font-semibold border bg-amber-50 text-amber-700 border-amber-200"><i class="fa-solid fa-hourglass-half text-[9px]"></i>Waiting</span>';
                    });

                    // isara ang confirm kapag may binago
                    crAskConfirm(false);
                }

                let crCurrentData = null;

                function crSetDecision(slot, decision) {
                    crDecisions[slot] = { ...(crDecisions[slot] || {}), decision };
                    if (decision === 'Okay') delete crDecisions[slot].remarks;
                    const ta = document.getElementById('rm_' + slot);
                    if (ta) {
                        ta.classList.toggle('hidden', decision !== 'Revise');
                        if (decision === 'Revise') setTimeout(() => ta.focus(), 0);
                    }
                    if (crCurrentData) crUpdateSubmitState(crCurrentData);
                }

                function crSetRemarks(slot, value) {
                    crDecisions[slot] = { ...(crDecisions[slot] || {}), remarks: value };
                    if (crCurrentData) crUpdateSubmitState(crCurrentData);
                }

                async function crFetchState({ silent = false } = {}) {
                    try {
                        const res = await fetch(`${Q2D_AJAX_URL}?action=customer_state&inquiry_id=${Q2D_INQUIRY_ID}`);
                        const data = await res.json();

                        if (!data.success) {
                            if (!silent) {
                                document.getElementById('crRoot').innerHTML = `
                                    <div class="bg-white border border-gray-200 rounded-2xl shadow-sm px-6 py-10 text-center">
                                        <span class="w-12 h-12 mx-auto rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center mb-3"><i class="fa-solid fa-triangle-exclamation text-lg"></i></span>
                                        <p class="text-sm font-semibold text-gray-900">${q2dEscapeHtml(data.message || 'Unable to load customer review.')}</p>
                                        <a href="${Q2D_BACK_TO_2D_URL}" class="mt-4 inline-flex items-center gap-2 text-xs font-semibold text-white bg-amber-700 hover:bg-amber-800 rounded-lg px-4 py-2">
                                            <i class="fa-solid fa-arrow-left"></i> Back to Initial files
                                        </a>
                                    </div>`;
                            }
                            return;
                        }

                        // Huwag guluhin ang form habang may napili/sinusulat
                        if (silent && crHasPending()) return;

                        const signature = JSON.stringify(data.slots) + data.status;
                        if (signature !== crLastSignature) {
                            crCurrentData = data;
                            crRender(data);
                            crLastSignature = signature;
                        }

                        const updatedEl = document.getElementById('crUpdatedAt');
                        if (updatedEl) {
                            updatedEl.innerHTML = `<i class="fa-solid fa-rotate mr-1"></i>Updated ${new Date().toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', second: '2-digit' })}`;
                        }
                    } catch (e) {
                        console.error('crFetchState:', e);
                        if (!silent) crmShowToast('Connection error while loading this page.', 'error');
                    }
                }

                // Inline confirm (walang confirm() popup)
                function crAskConfirm(show) {
                    const bar = document.getElementById('crSubmitBar');
                    if (!bar) return;
                    bar.querySelector('[data-submit-idle]').classList.toggle('hidden', !!show);
                    bar.querySelector('[data-submit-confirm]').classList.toggle('hidden', !show);
                    bar.classList.toggle('border-amber-300', !!show);
                }

                function crSubmit() {
                    if (crSubmitting) return;

                    const open = (crCurrentData?.slots || []).filter(s => s.decision !== 'Okay');
                    const anyRevise = open.some(s => (crDecisions[s.slot] || {}).decision === 'Revise');
                    document.getElementById('crConfirmText').innerHTML = anyRevise
                        ? '<span class="text-red-700 font-semibold">Some files need revision</span> and will be sent back to the designer.'
                        : '<span class="text-emerald-700 font-semibold">The customer approved all files.</span> You will go to the Final submission next.';
                    crAskConfirm(true);
                }

                async function crDoSubmit() {
                    if (crSubmitting) return;
                    crSubmitting = true;
                    const btn = document.getElementById('crSubmitBtn');
                    if (btn) btn.disabled = true;
                    const yes = document.getElementById('crConfirmYes');
                    if (yes) { yes.disabled = true; yes.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting…'; }
                    const resetYes = () => { if (yes) { yes.disabled = false; yes.innerHTML = '<i class="fa-solid fa-check"></i> Yes, submit'; } };

                    const fd = new FormData();
                    fd.append('action', 'submit_customer_review');
                    fd.append('inquiry_id', Q2D_INQUIRY_ID);
                    fd.append('decisions', JSON.stringify(crDecisions));

                    try {
                        const res = await fetch(Q2D_AJAX_URL, { method: 'POST', body: fd });
                        const data = await res.json();

                        if (!data.success) {
                            crmShowToast(q2dEscapeHtml(data.message || 'Something went wrong.'), 'error');
                            crSubmitting = false;
                            resetYes();
                            if (crCurrentData) crUpdateSubmitState(crCurrentData);
                            return;
                        }

                        crmShowToast(data.message || 'Saved.');
                        crDecisions = {};
                        setTimeout(() => {
                            window.location.href = data.next === 'final' ? Q2D_FINAL_URL : Q2D_BACK_TO_2D_URL;
                        }, 700);
                    } catch (e) {
                        console.error('crSubmit:', e);
                        crmShowToast('Connection error. Please try again.', 'error');
                        crSubmitting = false;
                        resetYes();
                        if (crCurrentData) crUpdateSubmitState(crCurrentData);
                    }
                }

                const q2dRtLive = () => !!(window.NH_RT && window.NH_RT.enabled && window.NH_RT.isLive());
                function crStartPolling() {
                    if (crPollTimer) clearInterval(crPollTimer);
                    crPollTimer = setInterval(() => crFetchState({ silent: true }), q2dRtLive() ? 60000 : CR_POLL_MS);
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
                            t = setTimeout(() => { if (!document.hidden) crFetchState({ silent: true }); }, 400);
                        });
                        window.NH_RT.onLive(live => {
                            if (!document.hidden && crPollTimer) crStartPolling(); // palitan ang bilis ng fallback polling
                            if (live) crFetchState({ silent: true });      // habol sa na-miss
                        });
                    };
                    hook();
                    if (!q2dHookRealtime.done) document.addEventListener('DOMContentLoaded', hook);
                })();

                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) {
                        if (crPollTimer) clearInterval(crPollTimer);
                    } else {
                        crFetchState({ silent: true });
                        crStartPolling();
                    }
                });

                crFetchState().then(crStartPolling);
            </script>
        <?php endif; ?>
    </main>
</body>

</html>