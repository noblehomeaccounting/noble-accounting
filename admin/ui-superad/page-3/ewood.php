<?php
// ewood.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SUPERADMIN];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$approvalAjaxUrl = BASE_URL . '/crmwoodapprovalajax';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Monitoring</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
</head>

<body class="bg-slate-100">
    <main class="ml-56 min-h-screen p-8 overflow-x-hidden">

        <div class="max-w-4xl mx-auto space-y-10">

            <!-- ===================== PENDING ===================== -->
            <div>
                <div class="mb-5">
                    <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase mb-0.5">Cutting Department</p>
                    <h1 class="text-gray-900 text-xl font-semibold">Pending QR Approvals</h1>
                    <p class="text-gray-400 text-xs mt-1">Click a photo to enlarge, scan it with the WeChat app (Scan from Album/Photo), then Approve or send for Revision below.</p>
                </div>

                <div id="ewoodApprovalList" class="space-y-3"></div>
                <p id="ewoodApprovalEmpty" class="hidden text-center text-gray-400 text-xs py-10">No approvals pending.</p>
            </div>

            <!-- ===================== APPROVED HISTORY ===================== -->
            <div>
                <div class="mb-5">
                    <p class="text-green-700 text-[10px] font-semibold tracking-[0.15em] uppercase mb-0.5">Cutting Department</p>
                    <h1 class="text-gray-900 text-xl font-semibold">Approved</h1>
                    <p class="text-gray-400 text-xs mt-1">Everything you've already approved. Read-only.</p>
                </div>

                <div id="ewoodApprovedList" class="space-y-3"></div>
                <p id="ewoodApprovedEmpty" class="hidden text-center text-gray-400 text-xs py-10">No approvals yet.</p>
            </div>

        </div>

        <div id="crmToastContainer" class="fixed bottom-4 right-4 z-[9999] flex flex-col gap-2.5 pointer-events-none"></div>
    </main>

    <!-- BOM view modal — renders the BOM like the printed form (read-only for Superadmin) -->
    <div id="ewoodBomViewModal" class="hidden fixed inset-0 z-[9998] items-center justify-center bg-black/50 p-4 overflow-y-auto">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl my-auto overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between gap-2 shrink-0">
                <p id="ewoodBomViewModalTitle" class="text-xs font-semibold text-gray-700 font-mono truncate">BOM</p>
                <div class="flex items-center gap-3 shrink-0">
                    <a id="ewoodBomViewDownload" href="#" target="_blank"
                        class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-700 hover:text-amber-800">
                        <i class="fa-solid fa-file-pdf"></i> Download PDF
                    </a>
                    <button type="button" onclick="ewoodBomCloseViewModal()"
                        class="text-gray-400 hover:text-gray-600 text-lg leading-none">&times;</button>
                </div>
            </div>
            <div id="ewoodBomViewBody" class="p-5 overflow-x-auto"></div>
        </div>
    </div>

    <!-- Revision reason modal (replaces window.prompt()) -->
    <div id="ewoodRejectModal" class="hidden fixed inset-0 z-[9999] items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-sm p-5">
            <p class="text-sm font-semibold text-gray-800 mb-1">Reason for Revision</p>
            <p class="text-xs text-gray-400 mb-3">Let cutting know what to fix before resubmitting.</p>
            <textarea id="ewoodRejectRemarks" rows="3"
                class="w-full text-xs border border-gray-200 rounded-md p-2 focus:outline-none focus:ring-1 focus:ring-amber-400 resize-none"
                placeholder="e.g. QR code unreadable, please reupload"></textarea>
            <p id="ewoodRejectError" class="hidden text-[11px] text-red-500 mt-1">A reason is required.</p>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" onclick="ewoodRejectCloseModal()"
                    id="ewoodRejectCancelBtn"
                    class="px-3 py-1.5 text-[11px] font-semibold text-gray-500 bg-gray-50 border border-gray-200 rounded-2xl hover:bg-gray-100 transition-colors">
                    Cancel
                </button>
                <button type="button" onclick="ewoodRejectSubmit()"
                    id="ewoodRejectSubmitBtn"
                    class="px-3 py-1.5 text-[11px] font-semibold text-white bg-red-600 rounded-2xl hover:bg-red-700 transition-colors">
                    Confirm Revision
                </button>
            </div>
        </div>
    </div>

    <script>
        function crmShowToast(message, type = 'success', duration = 4000) {
            const container = document.getElementById('crmToastContainer');
            const palette = type === 'success'
                ? { wrap: 'bg-green-50 border-green-200 text-green-700', icon: 'bg-green-200 text-green-700', symbol: '✓' }
                : { wrap: 'bg-red-50 border-red-200 text-red-700', icon: 'bg-red-200 text-red-700', symbol: '!' };
            const toast = document.createElement('div');
            toast.className = `pointer-events-auto flex items-start gap-2.5 border rounded-lg shadow-lg px-4 py-3 text-sm ${palette.wrap} translate-x-6 opacity-0 scale-95 transition-all duration-300 ease-out`;
            toast.innerHTML = `<span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold ${palette.icon}">${palette.symbol}</span>
                <span class="flex-1 leading-relaxed">${message}</span>
                <button type="button" class="shrink-0 text-current opacity-50 hover:opacity-100 text-base leading-none">&times;</button>`;
            container.appendChild(toast);
            requestAnimationFrame(() => toast.classList.remove('translate-x-6', 'opacity-0', 'scale-95'));
            const remove = () => { toast.classList.add('translate-x-6', 'opacity-0', 'scale-95'); setTimeout(() => toast.remove(), 300); };
            toast.querySelector('button').addEventListener('click', remove);
            if (duration > 0) setTimeout(remove, duration);
        }

        const CUT_PROG_AJAX_URL = <?= json_encode($approvalAjaxUrl) ?>;

        function ewoodEscapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }

        // Cutting-progress status badge — same three values as the Status
        // dropdown on ewoodfile.php's Upload & BOM form.
        function ewoodStatusBadge(status) {
            const map = {
                'Pending':     'bg-amber-50 text-amber-700 border-amber-200',
                'In Progress': 'bg-blue-50 text-blue-700 border-blue-200',
                'Completed':   'bg-green-50 text-green-700 border-green-200',
            };
            const cls = map[status] || map['Pending'];
            return `<span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold border whitespace-nowrap ${cls}">${ewoodEscapeHtml(status || 'Pending')}</span>`;
        }

        function ewoodFmtDateTime(value) {
            if (!value) return '—';
            const dt = new Date(value.includes('T') ? value : value.replace(' ', 'T'));
            if (isNaN(dt.getTime())) return value;
            const dd = String(dt.getDate()).padStart(2, '0');
            const mm = String(dt.getMonth() + 1).padStart(2, '0');
            const hh = String(dt.getHours()).padStart(2, '0');
            const mi = String(dt.getMinutes()).padStart(2, '0');
            return `${dd}/${mm}/${dt.getFullYear()} ${hh}:${mi}`;
        }

        // ===================== PENDING LIST =====================

        async function ewoodLoadApprovalList() {
            const list = document.getElementById('ewoodApprovalList');
            const empty = document.getElementById('ewoodApprovalEmpty');
            try {
                const res = await fetch(`${CUT_PROG_AJAX_URL}?action=pending_approval`);
                const data = await res.json();
                if (!data.success) { crmShowToast('Failed to load pending approvals.', 'error'); return; }

                if (data.entries.length === 0) {
                    list.innerHTML = '';
                    empty.classList.remove('hidden');
                    return;
                }
                empty.classList.add('hidden');

                list.innerHTML = data.entries.map(entry => `
                    <div id="ewoodApproval_${entry.id}" class="bg-white border border-gray-200 rounded-lg p-4">
                        <div class="flex items-start gap-4">
                            <a href="${entry.photos[0]}" target="_blank" rel="noopener noreferrer" class="shrink-0">
                                <img src="${entry.photos[0]}" class="w-24 h-24 object-cover rounded border border-gray-200 hover:opacity-80 transition-opacity" title="Click to enlarge / download">
                            </a>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="font-mono text-[11px] font-semibold text-amber-700 truncate">${ewoodEscapeHtml(entry.control_no)}</p>
                                    ${ewoodStatusBadge(entry.status)}
                                </div>
                                <p class="text-gray-800 text-xs font-medium truncate">${ewoodEscapeHtml(entry.client_name)}</p>
                                <p class="text-gray-400 text-[10.5px] mt-0.5">Scan with the WeChat app, then Approve or send for Revision.</p>
                                <div class="flex items-center gap-2 mt-2">
                                    <button type="button" onclick="ewoodRejectEntry(${entry.id})"
                                        id="ewoodRejectBtn_${entry.id}"
                                        class="px-3 py-1.5 text-[11px] font-semibold text-red-600 bg-red-50 border border-red-200 rounded-2xl hover:bg-red-100 transition-colors">
                                        Revision
                                    </button>
                                    <button type="button" onclick="ewoodConfirmApprove(${entry.id})"
                                        id="ewoodApproveBtn_${entry.id}"
                                        class="px-3 py-1.5 text-[11px] font-semibold text-white bg-green-600 rounded-2xl hover:bg-green-700 transition-colors">
                                        Approve
                                    </button>
                                </div>

                                <div class="mt-3 pt-3 border-t border-gray-100">
                                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-1.5">Bill of Materials</p>
                                    <div id="ewoodBomList_${entry.id}" class="flex flex-wrap gap-1.5">
                                        <p class="text-gray-400 text-[10.5px]">Loading…</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('');

                // Load each entry's BOM list after the cards are in the DOM.
                data.entries.forEach(entry => ewoodBomLoadList(entry.id, entry.quotation_id, 'ewoodBomList'));
            } catch (e) {
                console.error(e);
                crmShowToast('Connection error while loading approvals.', 'error');
            }
        }

        // Superadmin already verified the QR manually (via WeChat app),
        // this just records the approval.
        async function ewoodConfirmApprove(progressId) {
            const approveBtn = document.getElementById(`ewoodApproveBtn_${progressId}`);
            const rejectBtn = document.getElementById(`ewoodRejectBtn_${progressId}`);
            approveBtn.disabled = true;
            rejectBtn.disabled = true;
            approveBtn.textContent = 'Approving…';

            try {
                const res = await fetch(`${CUT_PROG_AJAX_URL}?action=approve`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ progress_id: progressId })
                });
                const data = await res.json();

                if (data.success) {
                    crmShowToast('Approved!', 'success');
                    const card = document.getElementById(`ewoodApproval_${progressId}`);
                    if (card) card.remove();
                    // Reflect it immediately in the Approved container below.
                    ewoodLoadApprovedList();
                    const list = document.getElementById('ewoodApprovalList');
                    if (list && list.children.length === 0) {
                        document.getElementById('ewoodApprovalEmpty').classList.remove('hidden');
                    }
                } else {
                    crmShowToast(data.message || 'Approval failed.', 'error');
                    approveBtn.disabled = false;
                    rejectBtn.disabled = false;
                    approveBtn.textContent = 'Approve';
                }
            } catch (e) {
                console.error(e);
                crmShowToast('Error while approving.', 'error');
                approveBtn.disabled = false;
                rejectBtn.disabled = false;
                approveBtn.textContent = 'Approve';
            }
        }

        // ---------------------------------------------------------------
        // Revision (reject) modal — replaces window.prompt()/alert().
        // ---------------------------------------------------------------

        let ewoodRejectTargetId = null;

        function ewoodRejectEntry(progressId) {
            ewoodRejectTargetId = progressId;
            const modal = document.getElementById('ewoodRejectModal');
            const textarea = document.getElementById('ewoodRejectRemarks');
            textarea.value = '';
            document.getElementById('ewoodRejectError').classList.add('hidden');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            setTimeout(() => textarea.focus(), 50);
        }

        function ewoodRejectCloseModal() {
            document.getElementById('ewoodRejectModal').classList.add('hidden');
            document.getElementById('ewoodRejectModal').classList.remove('flex');
            ewoodRejectTargetId = null;
        }

        async function ewoodRejectSubmit() {
            const progressId = ewoodRejectTargetId;
            if (!progressId) return;

            const textarea = document.getElementById('ewoodRejectRemarks');
            const errorMsg = document.getElementById('ewoodRejectError');
            const remarks = textarea.value.trim();

            if (remarks === '') {
                errorMsg.classList.remove('hidden');
                textarea.focus();
                return;
            }
            errorMsg.classList.add('hidden');

            const submitBtn = document.getElementById('ewoodRejectSubmitBtn');
            const cancelBtn = document.getElementById('ewoodRejectCancelBtn');
            submitBtn.disabled = true;
            cancelBtn.disabled = true;
            submitBtn.textContent = 'Submitting…';

            const approveBtn = document.getElementById(`ewoodApproveBtn_${progressId}`);
            const rejectBtn = document.getElementById(`ewoodRejectBtn_${progressId}`);
            if (approveBtn) approveBtn.disabled = true;
            if (rejectBtn) { rejectBtn.disabled = true; rejectBtn.textContent = 'Rejecting…'; }

            try {
                const res = await fetch(`${CUT_PROG_AJAX_URL}?action=reject`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ progress_id: progressId, remarks: remarks })
                });
                const data = await res.json();

                if (data.success) {
                    crmShowToast('Sent back for revision.', 'success');
                    const card = document.getElementById(`ewoodApproval_${progressId}`);
                    if (card) card.remove();
                    const list = document.getElementById('ewoodApprovalList');
                    if (list && list.children.length === 0) {
                        document.getElementById('ewoodApprovalEmpty').classList.remove('hidden');
                    }
                    ewoodRejectCloseModal();
                } else {
                    crmShowToast(data.message || 'Reject failed.', 'error');
                    if (approveBtn) approveBtn.disabled = false;
                    if (rejectBtn) { rejectBtn.disabled = false; rejectBtn.textContent = 'Revision'; }
                }
            } catch (e) {
                console.error(e);
                crmShowToast('Error while rejecting.', 'error');
                if (approveBtn) approveBtn.disabled = false;
                if (rejectBtn) { rejectBtn.disabled = false; rejectBtn.textContent = 'Revision'; }
            } finally {
                submitBtn.disabled = false;
                cancelBtn.disabled = false;
                submitBtn.textContent = 'Confirm Revision';
            }
        }

        // ===================== APPROVED LIST (history, read-only) =====================

        async function ewoodLoadApprovedList() {
            const list = document.getElementById('ewoodApprovedList');
            const empty = document.getElementById('ewoodApprovedEmpty');
            try {
                const res = await fetch(`${CUT_PROG_AJAX_URL}?action=approved_list`);
                const data = await res.json();
                if (!data.success) { crmShowToast('Failed to load approved history.', 'error'); return; }

                if (data.entries.length === 0) {
                    list.innerHTML = '';
                    empty.classList.remove('hidden');
                    return;
                }
                empty.classList.add('hidden');

                list.innerHTML = data.entries.map(entry => `
                    <div id="ewoodApproved_${entry.id}" class="bg-white border border-gray-200 rounded-lg p-4">
                        <div class="flex items-start gap-4">
                            <a href="${entry.photos[0] || '#'}" target="_blank" rel="noopener noreferrer" class="shrink-0">
                                <img src="${entry.photos[0] || ''}" class="w-24 h-24 object-cover rounded border border-gray-200 hover:opacity-80 transition-opacity" title="Click to enlarge / download">
                            </a>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="font-mono text-[11px] font-semibold text-amber-700 truncate">${ewoodEscapeHtml(entry.control_no)}</p>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-semibold border whitespace-nowrap bg-green-50 text-green-700 border-green-200">
                                        <i class="fa-solid fa-circle-check"></i> QR Approved
                                    </span>
                                </div>
                                <p class="text-gray-800 text-xs font-medium truncate">${ewoodEscapeHtml(entry.client_name)}</p>
                                <p class="text-gray-400 text-[10.5px] mt-0.5">
                                    Approved ${ewoodFmtDateTime(entry.approved_at)}${entry.approved_by ? ' by ' + ewoodEscapeHtml(entry.approved_by) : ''}
                                </p>
                                <p class="text-gray-400 text-[10.5px] mt-1 flex items-center gap-1">
                                    <span class="font-semibold text-gray-500">Job Status:</span> ${ewoodStatusBadge(entry.status)}
                                </p>

                                <div class="mt-3 pt-3 border-t border-gray-100">
                                    <p class="text-[10px] font-semibold text-gray-400 uppercase tracking-wide mb-1.5">Bill of Materials</p>
                                    <div id="ewoodBomListApproved_${entry.id}" class="flex flex-wrap gap-1.5">
                                        <p class="text-gray-400 text-[10.5px]">Loading…</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `).join('');

                data.entries.forEach(entry => ewoodBomLoadList(entry.id, entry.quotation_id, 'ewoodBomListApproved'));
            } catch (e) {
                console.error(e);
                crmShowToast('Connection error while loading approved history.', 'error');
            }
        }

        // ---------------------------------------------------------------
        // BOM (read-only view for the Superadmin, mirrors ewoodfile.php's
        // BOM view modal — reuses the same crmwoodapprovalajax endpoint's
        // bom_list / bom_view / bom_pdf actions). Shared by both containers.
        // ---------------------------------------------------------------

        function ewoodBomRenderList(prefix, entryId, entries) {
            const box = document.getElementById(`${prefix}_${entryId}`);
            if (!box) return; // card may have been removed already (approved/rejected mid-flight)
            if (!entries.length) {
                box.innerHTML = `<p class="text-gray-400 text-[10.5px]">No BOM submitted yet for this job.</p>`;
                return;
            }
            box.innerHTML = entries.map(e => `
                <button type="button" data-bom-id="${e.id}"
                    class="ewood-bom-pdf-btn inline-flex items-center gap-1 px-2 py-1 text-[10.5px] font-mono font-semibold text-amber-700 bg-amber-50 border border-amber-200 rounded-full hover:bg-amber-100 transition-colors">
                    <i class="fa-solid fa-file-pdf"></i> ${ewoodEscapeHtml(e.bom_number)}
                </button>
            `).join('');
        }

        // Event delegation on both list containers: covers BOM buttons in
        // every card, including ones added after this listener is attached.
        function ewoodBomBindDelegation(containerId) {
            const container = document.getElementById(containerId);
            if (!container) return;
            container.addEventListener('click', function (ev) {
                const btn = ev.target.closest('.ewood-bom-pdf-btn');
                if (!btn) return;
                ewoodBomOpenViewModal(btn.dataset.bomId);
            });
        }
        ewoodBomBindDelegation('ewoodApprovalList');
        ewoodBomBindDelegation('ewoodApprovedList');

        async function ewoodBomLoadList(entryId, quotationId, prefix) {
            try {
                const res = await fetch(`${CUT_PROG_AJAX_URL}?action=bom_list&quotation_id=${quotationId}`);
                const data = await res.json();
                if (!data.success) return;
                ewoodBomRenderList(prefix, entryId, data.entries);
            } catch (e) {
                console.error(e);
            }
        }

        // Fetches one BOM's full data (header + items) and renders it inside
        // the modal, mirroring the printed BOM layout.
        async function ewoodBomOpenViewModal(bomId) {
            const modal = document.getElementById('ewoodBomViewModal');
            const body = document.getElementById('ewoodBomViewBody');
            body.innerHTML = `<p class="text-xs text-gray-400 text-center py-10"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Loading…</p>`;
            document.getElementById('ewoodBomViewDownload').href = `${CUT_PROG_AJAX_URL}?action=bom_pdf&bom_id=${bomId}`;
            modal.classList.remove('hidden');
            modal.classList.add('flex');

            try {
                const res = await fetch(`${CUT_PROG_AJAX_URL}?action=bom_view&bom_id=${bomId}`);
                const data = await res.json();
                if (!data.success) {
                    body.innerHTML = `<p class="text-xs text-red-500 text-center py-10">${ewoodEscapeHtml(data.message || 'Failed to load BOM.')}</p>`;
                    return;
                }
                ewoodBomRenderViewBody(data.bom);
            } catch (e) {
                console.error(e);
                body.innerHTML = `<p class="text-xs text-red-500 text-center py-10">Connection error while loading.</p>`;
            }
        }

        function ewoodBomCloseViewModal() {
            const modal = document.getElementById('ewoodBomViewModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function ewoodBomFmtDate(value) {
            if (!value) return '—';
            const dt = new Date(value.includes('T') ? value : value.replace(' ', 'T'));
            if (isNaN(dt.getTime())) return value;
            // Match the PDF's date('d/m/Y') format exactly (day first).
            const dd = String(dt.getDate()).padStart(2, '0');
            const mm = String(dt.getMonth() + 1).padStart(2, '0');
            return `${dd}/${mm}/${dt.getFullYear()}`;
        }

        // Strips trailing zeros the same way the PDF's number_format/rtrim does.
        function ewoodBomFmtQty(q) {
            const n = parseFloat(q);
            if (isNaN(n)) return '';
            return n.toFixed(2).replace(/\.?0+$/, '');
        }

        // Renders the BOM inside the modal body, matching the printed form.
        function ewoodBomRenderViewBody(bom) {
            document.getElementById('ewoodBomViewModalTitle').textContent = bom.bom_number || 'BOM';

            const items = bom.items || [];
            const minRows = 6;
            const cellCls = 'border border-gray-800 px-2 py-1.5 text-xs';

            let rows = items.map((it, i) => `
                <tr>
                    <td class="${cellCls} text-center">${i + 1}</td>
                    <td class="${cellCls}">${ewoodEscapeHtml(it.item_code)}</td>
                    <td class="${cellCls}">${ewoodEscapeHtml(it.item_description)}</td>
                    <td class="${cellCls} text-center">${ewoodBomFmtQty(it.quantity)}</td>
                    <td class="${cellCls} text-center">${ewoodEscapeHtml(it.unit)}</td>
                    <td class="${cellCls}">${ewoodEscapeHtml(it.supplier)}</td>
                </tr>`).join('');
            for (let i = items.length; i < minRows; i++) {
                rows += `<tr>
                    <td class="${cellCls}">&nbsp;</td><td class="${cellCls}">&nbsp;</td><td class="${cellCls}">&nbsp;</td>
                    <td class="${cellCls}">&nbsp;</td><td class="${cellCls}">&nbsp;</td><td class="${cellCls}">&nbsp;</td>
                </tr>`;
            }

            // sigPath is expected to be a full URL (BASE_URL already applied
            // server-side); no BASE_URL prefix added here.
            const sigCell = (name, title, sigPath) => `
                <td class="border border-gray-800 text-center align-bottom px-2 pt-8 pb-1.5 w-1/4">
                    ${sigPath ? `<img src="${sigPath}" class="mx-auto max-h-9 max-w-[110px] -mb-1">` : ''}
                    <p class="text-xs font-bold">${name ? ewoodEscapeHtml(name) : '&nbsp;'}</p>
                    <p class="text-[9px] italic text-gray-600">${title ? ewoodEscapeHtml(title) : '&nbsp;'}</p>
                </td>`;

            const requestedSigUrl = bom.requested_by_signature_path ? `<?= BASE_URL ?>/${ewoodEscapeHtml(bom.requested_by_signature_path)}` : null;
            const approvedSigUrl  = bom.approved_by_signature_path  ? `<?= BASE_URL ?>/${ewoodEscapeHtml(bom.approved_by_signature_path)}`  : null;

            document.getElementById('ewoodBomViewBody').innerHTML = `
                <table class="w-full border-collapse border-l-2 border-r-2 border-t-2 border-gray-800">
                    <tr>
                        <td class="w-[8%]"></td>
                        <td class="text-center w-[62%]" style="font-family:'Arial Narrow',Arial,sans-serif;font-weight:900;font-stretch:condensed;letter-spacing:-2px;font-size:34px;line-height:1.1;">BILL OF MATERIALS</td>
                        <td class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1 w-[8%] align-middle">Page</td>
                        <td class="border border-gray-800 bg-gray-200 text-center text-[9px] font-bold uppercase px-2 py-1 w-[22%] align-middle">Date Submitted</td>
                    </tr>
                    <tr>
                        <td></td><td></td>
                        <td class="border border-gray-800 text-center text-xs px-2 py-1">${bom.page ?? 1}</td>
                        <td class="border border-gray-800 text-center text-xs px-2 py-1">${ewoodBomFmtDate(bom.date_submitted)}</td>
                    </tr>
                </table>
                <table class="w-full border-collapse -mt-px border-l-2 border-r-2 border-gray-800">
                    <tr>
                        <td class="border border-gray-800 bg-gray-200 text-[9px] font-bold uppercase px-2 py-1 w-[20%] align-middle">J.O Number:</td>
                        <td class="border border-gray-800 bg-gray-200 text-center text-[9px] font-bold uppercase px-2 py-1 w-[58%] align-middle" colspan="2">Client</td>
                        <td class="border border-gray-800 bg-gray-200 text-center text-[9px] font-bold uppercase px-2 py-1 w-[22%] align-middle">Date Needed</td>
                    </tr>
                    <tr>
                        <td class="border border-gray-800 text-xs px-2 py-1.5">${ewoodEscapeHtml(bom.bom_number)}</td>
                        <td class="border border-gray-800 text-center text-sm font-bold px-2 py-1.5" colspan="2">${ewoodEscapeHtml(bom.client_name)}</td>
                        <td class="border border-gray-800 text-center text-xs px-2 py-1.5">${ewoodEscapeHtml(bom.date_needed)}</td>
                    </tr>
                </table>
                <table class="w-full border-collapse -mt-px border-l-2 border-r-2 border-gray-800">
                    <tr>
                        <td class="border border-gray-800 bg-gray-200 text-[9px] font-bold uppercase px-2 py-1 w-[10%] align-middle">Scope:</td>
                        <td class="border border-gray-800 bg-gray-200 text-[9px] font-bold uppercase px-2 py-1">${ewoodEscapeHtml(bom.general_scope)}</td>
                    </tr>
                </table>
                <table class="w-full border-collapse -mt-px border-l-2 border-r-2 border-gray-800">
                    <thead>
                        <tr class="bg-gray-200">
                            <th class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1.5 w-[5%]">No.</th>
                            <th class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1.5 w-[15%]">Item Code</th>
                            <th class="border border-gray-800 text-left text-[9px] font-bold uppercase px-2 py-1.5 w-[35%]">Item Description</th>
                            <th class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1.5 w-[10%]">Quantity</th>
                            <th class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1.5 w-[10%]">Unit</th>
                            <th class="border border-gray-800 text-left text-[9px] font-bold uppercase px-2 py-1.5 w-[25%]">Supplier</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                    <tr><td colspan="6" class="border border-gray-800 text-center text-red-600 font-bold italic text-xs py-2">** NOTHING FOLLOWS **</td></tr>
                </table>
                <table class="w-full border-collapse -mt-px border-l-2 border-r-2 border-b-2 border-gray-800">
                    <tr class="bg-gray-200">
                        <td class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1 w-1/4">Requested By:</td>
                        <td class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1 w-1/4">Noted By:</td>
                        <td class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1 w-1/4">Received By:</td>
                        <td class="border border-gray-800 text-center text-[9px] font-bold uppercase px-2 py-1 w-1/4">Approved By:</td>
                    </tr>
                    <tr>
                        ${sigCell(bom.requested_by, bom.requested_by_title, requestedSigUrl)}
                        ${sigCell(bom.noted_by, bom.noted_by_title, null)}
                        ${sigCell(bom.received_by, bom.received_by_title, null)}
                        ${sigCell(bom.approved_by, bom.approved_by_title, approvedSigUrl)}
                    </tr>
                    <tr><td colspan="4" class="text-center text-[8px] text-gray-500 py-1.5">PROPERTY OF NOBLEHOME CONSTRUCTION</td></tr>
                </table>
            `;
        }

        document.getElementById('ewoodBomViewModal').addEventListener('click', function (ev) {
            if (ev.target === this) ewoodBomCloseViewModal();
        });

        document.getElementById('ewoodRejectModal').addEventListener('click', function (ev) {
            if (ev.target === this) ewoodRejectCloseModal();
        });

        document.addEventListener('keydown', function (ev) {
            if (ev.key !== 'Escape') return;
            ewoodBomCloseViewModal();
            ewoodRejectCloseModal();
        });

        ewoodLoadApprovalList();
        ewoodLoadApprovedList();
    </script>
</body>

</html>