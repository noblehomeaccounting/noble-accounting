<?php
// index-cuttinglist-detail.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_CUTTING];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$cutListAjaxUrl = BASE_URL . '/cuttinglistajax';
$cutListUrl = BASE_URL . '/crmcuttinglist';

$recordId = intval($_GET['id'] ?? 0);
if ($recordId <= 0) {
    header('Location: ' . $cutListUrl);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cutting List — Submission Details</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
</head>

<body class="bg-slate-100">
    <main class="ml-56 min-h-screen p-8 overflow-x-hidden">

        <div class="max-w-4xl mx-auto">

            <!-- Back link -->
            <a href="<?= htmlspecialchars($cutListUrl) ?>"
                class="inline-flex items-center gap-1.5 text-[12px] font-semibold text-gray-500 hover:text-amber-700 mb-4 transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                </svg>
                Back to list
            </a>

            <!-- Header card (now includes Hold / NTP badge next to name) -->
            <div id="cutHeaderCard" class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 mb-5">
                <div class="h-4 w-40 rounded bg-gray-100 animate-pulse mb-2"></div>
                <div class="h-6 w-64 rounded bg-gray-100 animate-pulse"></div>
            </div>

            <!-- Files -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 mb-5">
                <div class="flex items-center justify-between mb-3">
                    <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase">Files</p>
                    <button type="button" id="cutFeedbackBtn" onclick="cutToggleFeedbackSidebar()"
                        class="relative inline-flex items-center gap-1.5 text-[11px] font-semibold text-gray-600 border border-gray-300 rounded-full px-3 py-1.5 hover:bg-gray-50 transition-colors">
                        <i class="fa-solid fa-comment-dots"></i> Feedback on 2D
                        <span id="cutFeedbackBadge"
                            class="hidden absolute -top-1.5 -right-1.5 min-w-[16px] h-4 px-1 rounded-full bg-amber-600 text-white text-[9px] font-bold items-center justify-center leading-none">0</span>
                    </button>
                </div>
                <div id="cutFileRows" class="space-y-2.5">
                    <div class="h-10 rounded bg-gray-100 animate-pulse"></div>
                </div>
            </div>

            <!-- Site Notes -->
            <div class="mb-3">
                <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase">Site Notes</p>
            </div>
            <div id="cutSiteVisits" class="space-y-4 mb-6">
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                    <div class="h-3 w-full rounded bg-gray-100 animate-pulse mb-2"></div>
                    <div class="h-3 w-3/4 rounded bg-gray-100 animate-pulse"></div>
                </div>
            </div>

        </div>

        <!-- Toast container -->
        <div id="crmToastContainer"
            class="fixed bottom-6 right-6 z-[9999] flex flex-col gap-2.5 pointer-events-none w-full max-w-sm px-4 sm:px-0">
        </div>

        <!-- Feedback sidebar -->
        <div id="cutFeedbackOverlay" onclick="cutCloseFeedbackSidebar()"
            class="fixed inset-0 bg-black/30 z-[9998] opacity-0 pointer-events-none transition-opacity duration-300"></div>

        <aside id="cutFeedbackSidebar"
            class="fixed top-0 right-0 h-full w-full max-w-sm bg-white shadow-2xl z-[9999] translate-x-full transition-transform duration-300 ease-out flex flex-col">
            <div class="flex items-center justify-between px-5 py-4 border-b border-gray-200">
                <h2 class="text-sm font-bold text-gray-800 uppercase tracking-wide">Feedback on 2D File</h2>
                <button type="button" onclick="cutCloseFeedbackSidebar()"
                    class="text-gray-400 hover:text-gray-700 text-xl leading-none">&times;</button>
            </div>
            <div id="cutFeedbackSidebarBody" class="flex-1 overflow-y-auto px-5 py-4">
                <p class="text-sm text-gray-400 italic">Loading…</p>
            </div>
        </aside>

    </main>

    <script>
        function crmShowToast(message, type = 'success', duration = 4000) {
            const container = document.getElementById('crmToastContainer');
            const palette = type === 'success'
                ? { wrap: 'bg-green-50 border-green-200 text-green-700', icon: 'bg-green-200 text-green-700', symbol: '✓' }
                : { wrap: 'bg-red-50 border-red-200 text-red-700', icon: 'bg-red-200 text-red-700', symbol: '!' };
            const toast = document.createElement('div');
            toast.className = `pointer-events-auto flex items-start gap-2.5 border rounded-lg shadow-lg px-4 py-3 text-sm
                ${palette.wrap}
                translate-x-6 opacity-0 scale-95 transition-all duration-300 ease-out`;
            toast.innerHTML = `
                <span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold ${palette.icon}">${palette.symbol}</span>
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

        const CUT_LIST_AJAX_URL = <?= json_encode($cutListAjaxUrl) ?>;
        const CUT_RECORD_ID = <?= json_encode($recordId) ?>;

        let cutCurrentRecord = null;

        function cutEscapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }

        function cutFormatDateTimeLong(value) {
            if (!value) return '—';
            const dt = new Date(String(value).replace(' ', 'T'));
            if (isNaN(dt.getTime())) return value;
            return dt.toLocaleString('en-PH', {
                year: 'numeric', month: 'long', day: 'numeric',
                hour: 'numeric', minute: '2-digit', hour12: true
            });
        }

        function cutCurrency(value) {
            if (value === null || value === undefined || value === '') return '—';
            const num = Number(String(value).replace(/,/g, ''));
            if (isNaN(num)) return cutEscapeHtml(value);
            return '₱' + num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function cutDetailRow(label, value) {
            return `
                <div class="flex justify-between gap-3 py-2 border-b border-gray-100 text-[13px] last:border-b-0">
                    <span class="text-gray-400 whitespace-nowrap">${cutEscapeHtml(label)}</span>
                    <span class="text-gray-800 font-medium text-right">${value || '—'}</span>
                </div>
            `;
        }

        function cutNtpBadge(r) {
            const isNtp = r.deposit_status === 'Notice to Proceed';

            if (isNtp) {
                return `
                    <span class="inline-flex items-center gap-1 h-5 px-2 rounded-full text-[10px] font-bold leading-none bg-green-100 text-green-700 border border-green-200"
                          title="Downpayment received ${cutEscapeHtml(cutFormatDateTimeLong(r.deposit_uploaded_at))}">
                        <i class="fa-solid fa-circle-check text-[9px] leading-none"></i>
                        <span class="leading-none">NTP</span>
                    </span>
                `;
            }

            return `
                <span class="inline-flex items-center gap-1 h-5 px-2 rounded-full text-[10px] font-bold leading-none bg-amber-100 text-amber-700 border border-amber-200"
                      title="Waiting for Accounting to log the deposit">
                    <i class="fa-solid fa-clock text-[9px] leading-none"></i>
                    <span class="leading-none">Hold</span>
                </span>
            `;
        }

        function cutHoldNotice(r) {
            if (r.deposit_status === 'Notice to Proceed') return '';
            return `
                <div class="mt-3 flex items-center gap-2 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                    <i class="fa-solid fa-circle-info text-amber-600 text-xs"></i>
                    <p class="text-[12px] text-amber-800">Waiting for Accounting to mark this as Notice to Proceed before production starts.</p>
                </div>
            `;
        }

        function cutLockedNotice(message) {
            return `
                <div class="flex flex-col items-center justify-center text-center py-6 gap-2">
                    <i class="fa-solid fa-lock text-amber-400 text-lg"></i>
                    <p class="text-[13px] text-gray-500">${cutEscapeHtml(message)}</p>
                </div>
            `;
        }

        function cutRenderHeader(r) {
            document.getElementById('cutHeaderCard').innerHTML = `
                <div class="mb-4">
                    <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase mb-0.5">Control No. ${cutEscapeHtml(r.control_no)}</p>
                    <h1 class="text-gray-900 text-xl font-semibold flex items-center gap-2 flex-wrap">
                        <span>${cutEscapeHtml(r.client_name)}</span>
                        ${cutNtpBadge(r)}
                    </h1>
                    <p class="text-xs text-gray-400 mt-1">${r.branch ? cutEscapeHtml(r.branch) + ' Branch · ' : ''}Sales: ${cutEscapeHtml(r.sales_staff_name)} · Designer: ${cutEscapeHtml(r.designer_name)}</p>
                    ${cutHoldNotice(r)}
                </div>
                <div class="pt-1 border-t border-gray-100">
                    ${cutDetailRow('Address', cutEscapeHtml(r.address))}
                    ${cutDetailRow('Contact Number', cutEscapeHtml(r.contact_number))}
                    ${cutDetailRow('Type of Project', cutEscapeHtml(r.project_type))}
                    ${cutDetailRow('Scope of Project', cutEscapeHtml(r.project_scope))}
                    ${cutDetailRow('Measuring Space', cutEscapeHtml(r.measuring_space))}
                    ${cutDetailRow('Measurement Date and Time', cutFormatDateTimeLong(r.measurement_datetime))}
                    ${cutDetailRow('Contract Amount', cutCurrency(r.contract_amount))}
                </div>
            `;
        }

        function cutRevisedBadge(uploadedAt) {
            return `
                <span class="inline-flex items-center gap-1 h-5 px-2 rounded-full text-[10px] font-bold leading-none bg-blue-100 text-blue-700 border border-blue-200"
                      title="Re-uploaded ${cutEscapeHtml(cutFormatDateTimeLong(uploadedAt))} in response to feedback">
                    <i class="fa-solid fa-rotate text-[9px] leading-none"></i>
                    <span class="leading-none">Revised</span>
                </span>
            `;
        }

        // Shown once cutting has confirmed the 2D file has no more errors.
        function cutVerifiedBadge(verifierName, verifiedAt) {
            return `
                <span class="inline-flex items-center gap-1 h-5 px-2 rounded-full text-[10px] font-bold leading-none bg-green-100 text-green-700 border border-green-200"
                      title="Verified by ${cutEscapeHtml(verifierName || '—')} on ${cutEscapeHtml(cutFormatDateTimeLong(verifiedAt))}">
                    <i class="fa-solid fa-file-circle-check text-[9px] leading-none"></i>
                    <span class="leading-none">Verified</span>
                </span>
            `;
        }

        function cutFileCard(label, path, uploaderName, uploaderRole, uploadedAt, revised) {
            const link = path
                ? `<a href="${cutEscapeHtml(path)}" target="_blank" rel="noopener"
                        class="inline-flex items-center gap-1 text-amber-700 hover:text-amber-900 hover:underline font-semibold text-[13px]">
                        View PDF
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                    </a>`
                : `<span class="text-gray-400 text-[13px]">No file uploaded</span>`;

            const metaLine = path
                ? `<p class="text-[11.5px] text-gray-500 mt-0.5">${cutEscapeHtml(uploaderName || '—')} · ${cutEscapeHtml(uploaderRole || '—')}${uploadedAt ? ' · ' + cutEscapeHtml(cutFormatDateTimeLong(uploadedAt)) : ''}</p>`
                : '';

            return `
                <div class="flex items-center justify-between gap-3 border border-gray-200 rounded-xl px-4 py-3 bg-white">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="shrink-0 w-9 h-9 rounded-lg flex items-center justify-center ${path ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-300'}">
                            <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[13px] font-bold text-gray-800 flex items-center gap-2 flex-wrap">
                                <span>${cutEscapeHtml(label)}</span>
                                ${revised ? cutRevisedBadge(uploadedAt) : ''}
                            </p>
                            ${link}
                            ${metaLine}
                        </div>
                    </div>
                </div>
            `;
        }

        // 2D card gets its own renderer since it carries the Verify button /
        // Verified badge that the other file types don't have.
        function cutRender2dFileCard(r) {
            const path = r.design_2d_path;
            const feedback = r.cutting_feedback || [];
            const revised = feedback.some(f => f.is_resolved);

            const link = path
                ? `<a href="${cutEscapeHtml(path)}" target="_blank" rel="noopener"
                        class="inline-flex items-center gap-1 text-amber-700 hover:text-amber-900 hover:underline font-semibold text-[13px]">
                        View PDF
                        <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
                    </a>`
                : `<span class="text-gray-400 text-[13px]">No file uploaded</span>`;

            const metaLine = path
                ? `<p class="text-[11.5px] text-gray-500 mt-0.5">${cutEscapeHtml(r.design_2d_uploader_name || '—')} · ${cutEscapeHtml(r.design_2d_uploaded_role || '—')}${r.design_2d_uploaded_at ? ' · ' + cutEscapeHtml(cutFormatDateTimeLong(r.design_2d_uploaded_at)) : ''}</p>`
                : '';

            const actionSlot = !path
                ? ''
                : r.design_2d_verified
                    ? cutVerifiedBadge(r.design_2d_verified_by_name, r.design_2d_verified_at)
                    : `<button type="button" onclick="cutVerify2d(${r.id})"
                            class="shrink-0 px-2.5 py-1.5 text-[11px] font-semibold text-white bg-green-700 hover:bg-green-800 rounded-lg transition-colors">
                            Verify 2D
                        </button>`;

            return `
                <div class="flex items-center justify-between gap-3 border border-gray-200 rounded-xl px-4 py-3 bg-white">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="shrink-0 w-9 h-9 rounded-lg flex items-center justify-center ${path ? 'bg-amber-100 text-amber-700' : 'bg-gray-100 text-gray-300'}">
                            <svg class="w-4.5 h-4.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[13px] font-bold text-gray-800 flex items-center gap-2 flex-wrap">
                                <span>2D File</span>
                                ${revised ? cutRevisedBadge(r.design_2d_uploaded_at) : ''}
                            </p>
                            ${link}
                            ${metaLine}
                        </div>
                    </div>
                    ${actionSlot}
                </div>
            `;
        }

        function cutRenderFeedbackSidebar(r) {
            const badge = document.getElementById('cutFeedbackBadge');
            const body = document.getElementById('cutFeedbackSidebarBody');
            if (!badge || !body) return;

            const feedback = r.cutting_feedback || [];
            const unresolvedCount = feedback.filter(f => !f.is_resolved).length;

            if (unresolvedCount > 0) {
                badge.textContent = unresolvedCount;
                badge.classList.remove('hidden');
                badge.classList.add('flex');
            } else {
                badge.classList.add('hidden');
                badge.classList.remove('flex');
            }

            const historyHtml = feedback.length
                ? `<div class="space-y-2 mb-4">
                    ${feedback.map(f => `
                        <div class="border-l-2 ${f.is_resolved ? 'border-gray-300 bg-gray-50' : 'border-amber-400 bg-amber-50'} rounded px-3 py-2">
                            <p class="text-[12.5px] text-gray-800 whitespace-pre-line">${cutEscapeHtml(f.message)}</p>
                            <p class="text-[10.5px] text-gray-400 mt-1">
                                ${cutEscapeHtml(f.created_by_name)} · ${cutFormatDateTimeLong(f.created_at)}
                                ${f.is_resolved ? '<span class="text-green-700 font-semibold ml-1">Resolved</span>' : '<span class="text-amber-700 font-semibold ml-1">Waiting for designer</span>'}
                            </p>
                        </div>
                    `).join('')}
                   </div>`
                : `<p class="text-sm text-gray-400 italic mb-4">No feedback sent yet.</p>`;

            body.innerHTML = `
                ${historyHtml}
                <button type="button" onclick="cutToggleFeedbackForm(${r.id})"
                    class="text-[12px] font-semibold text-amber-700 hover:text-amber-900">
                    + Send feedback on 2D
                </button>
                <div id="cutFeedbackForm_${r.id}" class="hidden mt-2">
                    <textarea id="cutFeedbackText_${r.id}" rows="3" placeholder="Ano ang kailangan i-revise sa 2D..."
                        class="w-full text-[13px] border border-gray-200 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-amber-200"></textarea>
                    <div class="flex justify-end gap-2 mt-2">
                        <button type="button" onclick="cutToggleFeedbackForm(${r.id})"
                            class="text-[12px] text-gray-500 px-3 py-1.5">Cancel</button>
                        <button type="button" onclick="cutSendFeedback(${r.id})"
                            class="text-[12px] font-semibold text-white bg-amber-700 hover:bg-amber-800 px-3 py-1.5 rounded-lg">Send</button>
                    </div>
                </div>
            `;
        }

        function cutToggleFeedbackForm(id) {
            const form = document.getElementById(`cutFeedbackForm_${id}`);
            if (form) form.classList.toggle('hidden');
        }

        async function cutSendFeedback(quotationId) {
            const textarea = document.getElementById(`cutFeedbackText_${quotationId}`);
            const message = textarea ? textarea.value.trim() : '';
            if (!message) {
                crmShowToast('Please write your feedback first.', 'error');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'send_feedback');
            formData.append('quotation_id', quotationId);
            formData.append('message', message);

            try {
                const res = await fetch(CUT_LIST_AJAX_URL, { method: 'POST', body: formData });
                const data = await res.json();

                if (!data.success) {
                    crmShowToast(data.message || 'Something went wrong.', 'error');
                    return;
                }

                crmShowToast(data.message || 'Feedback sent.');
                cutLoadDetail(); // reload so the new feedback shows in the sidebar
            } catch (e) {
                console.error('cutSendFeedback:', e);
                crmShowToast('Connection error. Please try again.', 'error');
            }
        }

        // Marks the 2D file as verified (walang mali) by the current cutting user.
        async function cutVerify2d(quotationId) {
            const formData = new FormData();
            formData.append('action', 'verify_2d');
            formData.append('quotation_id', quotationId);

            try {
                const res = await fetch(CUT_LIST_AJAX_URL, { method: 'POST', body: formData });
                const data = await res.json();

                if (!data.success) {
                    crmShowToast(data.message || 'Something went wrong.', 'error');
                    return;
                }

                crmShowToast(data.message || '2D file verified.');
                cutLoadDetail(); // reload so the badge shows immediately
            } catch (e) {
                console.error('cutVerify2d:', e);
                crmShowToast('Connection error. Please try again.', 'error');
            }
        }

        function cutOpenFeedbackSidebar() {
            document.getElementById('cutFeedbackOverlay').classList.remove('opacity-0', 'pointer-events-none');
            document.getElementById('cutFeedbackSidebar').classList.remove('translate-x-full');
        }

        function cutCloseFeedbackSidebar() {
            document.getElementById('cutFeedbackOverlay').classList.add('opacity-0', 'pointer-events-none');
            document.getElementById('cutFeedbackSidebar').classList.add('translate-x-full');
        }

        function cutToggleFeedbackSidebar() {
            const sidebar = document.getElementById('cutFeedbackSidebar');
            if (sidebar.classList.contains('translate-x-full')) {
                cutOpenFeedbackSidebar();
            } else {
                cutCloseFeedbackSidebar();
            }
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') cutCloseFeedbackSidebar();
        });

        function cutVisitedBadge(visited) {
            return visited
                ? `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold border whitespace-nowrap bg-green-50 text-green-700 border-green-200"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>Visited</span>`
                : `<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-semibold border whitespace-nowrap bg-gray-50 text-gray-500 border-gray-200"><span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span>Not Visited</span>`;
        }

        function cutNoteBlock(label, value) {
            return `
                <div>
                    <p class="text-[11px] font-semibold text-gray-500 mb-1">${cutEscapeHtml(label)}</p>
                    <p class="text-[13px] text-gray-800 whitespace-pre-line">${value ? cutEscapeHtml(value) : '<span class="text-gray-400 italic">None provided</span>'}</p>
                </div>
            `;
        }

        function cutRenderSiteVisitCard(visit, label) {
            const photosHtml = visit.photos.length
                ? `<div class="grid grid-cols-6 sm:grid-cols-8 gap-2 mt-3">
                    ${visit.photos.map(p => `
                        <a href="${cutEscapeHtml(p)}" target="_blank" rel="noopener" class="block aspect-square border border-gray-200 rounded overflow-hidden hover:border-amber-600 transition-colors">
                            <img src="${cutEscapeHtml(p)}" class="w-full h-full object-cover">
                        </a>
                    `).join('')}
                   </div>`
                : `<p class="text-xs text-gray-400 mt-2">No photographs attached.</p>`;

            return `
                <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
                    <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between gap-3 flex-wrap">
                        <div>
                            <p class="text-[10px] text-gray-400 font-semibold tracking-[0.1em] uppercase">${label}</p>
                            <p class="text-xs text-gray-400 mt-0.5">Logged ${cutFormatDateTimeLong(visit.created_at)} · ${cutEscapeHtml(visit.designer_name)}</p>
                        </div>
                        ${cutVisitedBadge(visit.visited)}
                    </div>
                    <div class="px-5 py-3">
                        <p class="text-[13px] text-gray-800">${cutEscapeHtml(visit.address)}</p>
                        <p class="text-xs text-gray-400 mt-1 mb-3">Visit date: ${cutFormatDateTimeLong(visit.visit_datetime)}</p>
                        <div class="grid sm:grid-cols-2 gap-4">
                            ${cutNoteBlock('Measurements', visit.measurements)}
                            ${cutNoteBlock('Site conditions', visit.site_conditions)}
                            ${cutNoteBlock('Client requirements', visit.client_requirements)}
                            ${cutNoteBlock('Existing structure', visit.existing_structure)}
                        </div>
                        ${photosHtml}
                    </div>
                </div>
            `;
        }

        function cutRenderFiles(r) {
            if (r.deposit_status !== 'Notice to Proceed') {
                document.getElementById('cutFileRows').innerHTML =
                    cutLockedNotice('Files are hidden until this is marked Notice to Proceed.');
                return;
            }

            const cards = [
                cutRender2dFileCard(r),
                cutFileCard('Quotation File', r.quotation_path, r.quotation_uploader_name, r.quotation_uploaded_role, r.quotation_uploaded_at, false),
                r.show_3d
                    ? cutFileCard('3D File', r.design_3d_path, r.design_3d_uploader_name, r.design_3d_uploaded_role, r.design_3d_uploaded_at, false)
                    : '',
            ].join('');
            document.getElementById('cutFileRows').innerHTML = cards;
        }

        function cutRenderSiteVisits(siteVisits, r) {
            const container = document.getElementById('cutSiteVisits');

            if (r.deposit_status !== 'Notice to Proceed') {
                container.innerHTML = `
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
                        ${cutLockedNotice('Site notes are hidden until this is marked Notice to Proceed.')}
                    </div>
                `;
                return;
            }

            if (!siteVisits || siteVisits.length === 0) {
                container.innerHTML = `
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 text-center">
                        <p class="text-sm text-gray-400">No site visit was recorded for this inquiry.</p>
                    </div>
                `;
                return;
            }
            const ordered = [...siteVisits].reverse();
            const total = siteVisits.length;
            container.innerHTML = ordered
                .map((visit, i) => cutRenderSiteVisitCard(visit, total > 1 ? `Visit ${total - i}` : 'Site Visit'))
                .join('');
        }

        async function cutLoadDetail() {
            if (!CUT_RECORD_ID || CUT_RECORD_ID <= 0) {
                document.getElementById('cutHeaderCard').innerHTML = `<p class="text-sm text-red-500">Invalid record.</p>`;
                return;
            }
            try {
                const res = await fetch(`${CUT_LIST_AJAX_URL}?action=detail&id=${CUT_RECORD_ID}`);
                const data = await res.json();

                if (!data.success) {
                    document.getElementById('cutHeaderCard').innerHTML = `<p class="text-sm text-red-500">${cutEscapeHtml(data.message || 'Record not found.')}</p>`;
                    document.getElementById('cutFileRows').innerHTML = '';
                    document.getElementById('cutSiteVisits').innerHTML = '';
                    return;
                }

                const r = data.record;
                cutCurrentRecord = r;
                cutRenderHeader(r);
                cutRenderFiles(r);
                cutRenderSiteVisits(r.site_visits, r);
                cutRenderFeedbackSidebar(r);

            } catch (e) {
                console.error('cutLoadDetail:', e);
                crmShowToast('Connection error while loading the record.', 'error');
            }
        }

        cutLoadDetail();
    </script>
</body>

</html>