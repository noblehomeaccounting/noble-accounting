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
    <main class="ml-56 min-h-screen p-4">
        <div class="max-w-7xl mx-auto">
            <div class="bg-white border border-gray-300 shadow-sm rounded-lg">

                <div class="px-6 pt-6 pb-5 flex items-start justify-between border-b border-gray-300">
                    <div>
                        <p class="text-[10px] tracking-[0.25em] uppercase text-gray-500 mb-1">Client Relationship Management</p>
                        <h1 class="text-xl font-bold text-[#0B2540] tracking-wide">
                            Customer Review
                            <span id="crStatusBadge"
                                class="hidden align-middle ml-2 text-[10px] font-bold uppercase tracking-widest px-2 py-0.5 rounded-lg border"></span>
                        </h1>
                    </div>
                    <div class="flex items-center gap-2">
                        <?php if (empty($qError)): ?>
                            <a href="<?= htmlspecialchars(BASE_URL . '/crm2dquotation?id=' . $inquiryId) ?>"
                                class="text-xs font-medium text-gray-600 border border-gray-300 px-3 py-1.5 hover:bg-gray-50 transition-colors rounded-full">
                                <i class="fa-solid fa-folder-open"></i> Initial Files
                            </a>
                        <?php endif; ?>
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
                        <div id="crRoot">
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
                    <span id="crUpdatedAt">Customer Review</span>
                </div>
            </div>
        </div>

        <div id="crmToastContainer"
            class="fixed bottom-6 right-6 z-[9999] flex flex-col gap-2 pointer-events-none w-30 max-w-sm px-4 sm:px-0">
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
                        ? { wrap: 'bg-white border-green-900 text-gray-800 rounded-2xl', icon: 'bg-green-600 text-white rounded-lg', symbol: '✓' }
                        : { wrap: 'bg-white border-red-700 text-gray-800 rounded-2xl', icon: 'bg-red-700 text-white rounded-lg', symbol: '!' };

                    const toast = document.createElement('div');
                    toast.className = `pointer-events-auto flex items-start gap-2.5 border shadow-lg px-4 py-3 text-sm
                        ${palette.wrap} translate-x-6 opacity-0 scale-95 transition-all duration-300 ease-out`;
                    toast.innerHTML = `
                        <span class="shrink-0 inline-flex items-center justify-center w-5 h-5 text-xs font-bold ${palette.icon}">${palette.symbol}</span>
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

                function q2dEscapeHtml(str) {
                    const div = document.createElement('div');
                    div.textContent = str ?? '';
                    return div.innerHTML;
                }

                function crHasPending() {
                    return Object.keys(crDecisions).length > 0;
                }

                function crFilesHtml(files) {
                    if (!files || !files.length) return '<span class="text-gray-300">—</span>';
                    return files.map(f => `
                        <a href="${q2dEscapeHtml(f.url)}" target="_blank" title="${q2dEscapeHtml(f.name)}"
                            class="flex items-center gap-2 text-xs border border-gray-200 bg-gray-50 hover:bg-gray-100 px-2 py-1 mb-1 transition-colors">
                            <i class="fa-solid fa-file-lines text-gray-400 shrink-0"></i>
                            <span class="flex-1 truncate text-[#0B2540] underline underline-offset-2">${q2dEscapeHtml(f.name)}</span>
                        </a>`).join('');
                }

                function crInquirySummary(inquiry) {
                    return `
                    <table class="w-full border border-gray-300 text-sm mb-6">
                        <tbody>
                            <tr>
                                <td class="w-32 bg-amber-600 font-semibold text-[10px] uppercase tracking-wider text-white px-4 py-2.5 border-r border-gray-200">Control No :</td>
                                <td class="px-4 py-2.5 font-semibold text-gray-900">${q2dEscapeHtml(inquiry.control_no)}</td>
                                <td class="w-28 bg-amber-600 font-semibold text-[10px] uppercase tracking-wider text-white px-4 py-2.5 border-r border-l border-gray-200">Client Name :</td>
                                <td class="px-4 py-2.5 text-gray-900">${q2dEscapeHtml(inquiry.client_name)}</td>
                            </tr>
                        </tbody>
                    </table>`;
                }

                function crDecisionCell(s) {
                    // Okay na dati → locked, walang choices
                    if (s.decision === 'Okay') {
                        return `<span class="inline-flex items-center text-[11px] font-semibold uppercase tracking-wide px-2 py-0.5 border border-green-700 text-green-800">
                                    <i class="fa-solid fa-circle-check text-green-600 mr-1"></i>Customer Okay
                                </span>
                                <p class="text-[11px] text-gray-500 mt-2">No further action needed.</p>`;
                    }

                    const cur = crDecisions[s.slot] || {};
                    const isOkay = cur.decision === 'Okay';
                    const isRevise = cur.decision === 'Revise';

                    const pendingNote = (s.decision === 'Revise')
                        ? `<p class="text-[10px] font-semibold uppercase tracking-wide text-amber-700 mb-2">Revised file — waiting for customer decision again</p>`
                        : '';

                    return `
                    ${pendingNote}
                    <div class="flex gap-2">
                        <label class="flex-1 cursor-pointer select-none">
                            <input type="radio" name="d_${s.slot}" value="Okay" class="peer hidden" ${isOkay ? 'checked' : ''}
                                onchange="crSetDecision('${s.slot}', 'Okay')">
                            <span class="block text-center px-3 py-2 text-xs font-semibold uppercase tracking-wide border border-gray-400 text-gray-600 hover:bg-gray-50
                                peer-checked:bg-green-700 peer-checked:text-white peer-checked:border-green-700 transition-colors">
                                <i class="fa-solid fa-check mr-1"></i>Okay
                            </span>
                        </label>
                        <label class="flex-1 cursor-pointer select-none">
                            <input type="radio" name="d_${s.slot}" value="Revise" class="peer hidden" ${isRevise ? 'checked' : ''}
                                onchange="crSetDecision('${s.slot}', 'Revise')">
                            <span class="block text-center px-3 py-2 text-xs font-semibold uppercase tracking-wide border border-gray-400 text-gray-600 hover:bg-gray-50
                                peer-checked:bg-red-700 peer-checked:text-white peer-checked:border-red-700 transition-colors">
                                <i class="fa-solid fa-rotate-left mr-1"></i>Needs Revision
                            </span>
                        </label>
                    </div>
                    <textarea id="rm_${s.slot}" rows="3" oninput="crSetRemarks('${s.slot}', this.value)"
                        placeholder="Customer remarks (required)"
                        class="${isRevise ? '' : 'hidden'} mt-2 w-full border border-gray-300 text-xs p-2 focus:outline-none focus:border-[#0B2540]">${q2dEscapeHtml(cur.remarks || '')}</textarea>`;
                }

                function crRender(data) {
                    const root = document.getElementById('crRoot');
                    const approved = data.status === 'Customer Approved';

                    const rows = data.slots.map(s => `
                        <tr class="border-b border-gray-200 last:border-b-0">
                            <td class="align-top px-4 py-4 font-semibold text-[#0B2540] uppercase tracking-wide text-xs border-r border-gray-200">${q2dEscapeHtml(s.label)}</td>
                            <td class="align-top px-4 py-4 border-r border-gray-200">
                                ${crFilesHtml(s.data.files)}
                                ${s.data.uploaded_by_name && s.data.uploaded_by_name !== '—'
                            ? `<p class="text-[11px] text-gray-500 mt-2">Uploaded by: ${q2dEscapeHtml(s.data.uploaded_by_name)} (${q2dEscapeHtml(s.data.uploaded_role_label)})</p>` : ''}
                            </td>
                            <td class="align-top px-4 py-4">${crDecisionCell(s)}</td>
                        </tr>`).join('');

                    const banner = approved
                        ? `<span class="inline-flex items-center text-[11px] font-semibold uppercase tracking-wide px-2 py-0.5 border border-green-700 ml-1 text-green-800">
                                <i class="fa-solid fa-circle-check text-green-600 mr-1"></i>Customer Approved</span>`
                        : `<span class="inline-flex items-center text-[11px] font-semibold uppercase tracking-wide px-2 py-0.5 border border-amber-700 ml-1 text-amber-700">Waiting for Customer</span>`;

                    const hint = approved
                        ? 'The customer has approved all files. You may proceed to the Final submission.'
                        : 'Select Okay or Needs Revision for each file. Files marked Needs Revision go back to the designer for re-upload; files that are Okay stay locked.';

                    let footer;
                    if (approved) {
                        footer = `
                        <div class="pt-4 mt-2 border-t border-gray-300 flex items-center justify-end gap-3">
                            <a href="${Q2D_FINAL_URL}"
                                class="px-5 py-2 text-sm font-semibold uppercase tracking-wide text-white bg-green-900 hover:bg-green-800 transition-colors">
                                Proceed to Final Submission
                            </a>
                        </div>`;
                    } else {
                        footer = `
                        <div class="pt-4 mt-2 border-t border-gray-300 flex items-center justify-end gap-3">
                            <p id="crSubmitHint" class="text-xs text-gray-400"></p>
                            <button type="button" id="crSubmitBtn" onclick="crSubmit()"
                                class="px-5 py-2 text-sm font-semibold uppercase tracking-wide text-white bg-[#0B2540] hover:bg-[#123564] disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors">
                                Submit Customer Decision
                            </button>
                        </div>`;
                    }

                    root.innerHTML = `
                        ${crInquirySummary(data.inquiry)}
                        <div class="flex items-center justify-between border-l-4 border-[#0B2540] bg-gray-50 px-4 py-2.5 mb-4">
                            <p class="text-sm text-gray-800"><strong class="uppercase tracking-wide text-[11px]">Status:</strong>${banner}</p>
                        </div>
                        <p class="text-sm text-gray-500 italic mb-6">${hint}</p>
                        <div class="overflow-x-auto border border-gray-300 mb-6">
                            <table class="w-full table-fixed text-sm">
                                <thead>
                                    <tr class="bg-gray-50 text-[11px] uppercase tracking-wide text-gray-600">
                                        <th class="w-[12%] text-left font-semibold px-4 py-2 border-r border-b border-gray-300">File</th>
                                        <th class="w-[40%] text-left font-semibold px-4 py-2 border-r border-b border-gray-300">Attachments</th>
                                        <th class="text-left font-semibold px-4 py-2 border-b border-gray-300">Customer Decision</th>
                                    </tr>
                                </thead>
                                <tbody>${rows}</tbody>
                            </table>
                        </div>
                        ${footer}`;

                    // badge sa header
                    const badge = document.getElementById('crStatusBadge');
                    badge.textContent = data.status;
                    badge.className = 'align-middle ml-2 text-[10px] font-bold uppercase tracking-widest px-2 py-0.5 rounded-lg border inline-block '
                        + (approved ? 'bg-green-100 text-green-800 border-green-600' : 'bg-amber-100 text-amber-800 border-amber-600');

                    crUpdateSubmitState(data);
                }

                // Na-enable lang ang submit kapag may desisyon na ang lahat ng slot na hindi pa Okay,
                // at may remarks ang bawat Revise.
                function crUpdateSubmitState(data) {
                    const btn = document.getElementById('crSubmitBtn');
                    const hint = document.getElementById('crSubmitHint');
                    if (!btn) return;

                    const open = data.slots.filter(s => s.decision !== 'Okay');
                    let msg = '';
                    let ok = true;

                    for (const s of open) {
                        const d = crDecisions[s.slot];
                        if (!d || !d.decision) { ok = false; msg = 'Choose a decision for every file first.'; break; }
                        if (d.decision === 'Revise' && !(d.remarks || '').trim()) { ok = false; msg = 'Remarks are required for files that need revision.'; break; }
                    }

                    btn.disabled = !ok || crSubmitting;
                    if (hint) hint.textContent = msg;
                }

                let crCurrentData = null;

                function crSetDecision(slot, decision) {
                    crDecisions[slot] = { ...(crDecisions[slot] || {}), decision };
                    if (decision === 'Okay') delete crDecisions[slot].remarks;
                    const ta = document.getElementById('rm_' + slot);
                    if (ta) ta.classList.toggle('hidden', decision !== 'Revise');
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
                                    <div class="border border-gray-400 bg-gray-50 text-gray-800 text-sm px-4 py-2.5">
                                        <strong class="uppercase text-[11px] tracking-wide">Notice:</strong>
                                        ${q2dEscapeHtml(data.message || 'Unable to load customer review.')}
                                    </div>
                                    <div class="mt-4">
                                        <a href="${Q2D_BACK_TO_2D_URL}" class="text-xs font-medium text-gray-600 border border-gray-300 px-3 py-1.5 hover:bg-gray-50 rounded-full">
                                            <i class="fa-solid fa-circle-arrow-left"></i> Back to Initial Files
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
                            updatedEl.textContent = `Updated ${new Date().toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit', second: '2-digit' })}`;
                        }
                    } catch (e) {
                        console.error('crFetchState:', e);
                        if (!silent) crmShowToast('Connection error while loading this page.', 'error');
                    }
                }

                async function crSubmit() {
                    if (crSubmitting) return;

                    const open = (crCurrentData?.slots || []).filter(s => s.decision !== 'Okay');
                    const anyRevise = open.some(s => (crDecisions[s.slot] || {}).decision === 'Revise');
                    const msg = anyRevise
                        ? 'Some files are marked Needs Revision and will be sent back to the designer. Continue?'
                        : 'Confirm that the customer approved all files?';
                    if (!confirm(msg)) return;

                    crSubmitting = true;
                    const btn = document.getElementById('crSubmitBtn');
                    if (btn) btn.disabled = true;

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
                        if (crCurrentData) crUpdateSubmitState(crCurrentData);
                    }
                }

                function crStartPolling() {
                    if (crPollTimer) clearInterval(crPollTimer);
                    crPollTimer = setInterval(() => crFetchState({ silent: true }), CR_POLL_MS);
                }

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