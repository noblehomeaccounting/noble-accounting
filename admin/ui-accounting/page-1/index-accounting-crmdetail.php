<?php
// index-accounting-crmdetail.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_ACCOUNTING];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$acctListAjaxUrl = BASE_URL . '/accountingcrmlistajax';
$acctListUrl = BASE_URL . '/crmaccounting';
$acctPaymentMethodsUrl = BASE_URL . '/accountingpaymentmethods';

$recordId = intval($_GET['id'] ?? 0);
if ($recordId <= 0) {
    header('Location: ' . $acctListUrl);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting — Submission Details</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
</head>

<body class="bg-slate-100">
    <main id="main-content" class="md:ml-56 min-h-screen pt-20 md:pt-8 p-4 md:p-8 overflow-x-hidden transition-all duration-300">

        <div class="max-w-6xl mx-auto">

            <!-- Back -->
            <a href="<?= htmlspecialchars($acctListUrl) ?>"
                class="inline-flex items-center gap-1.5 text-xs font-semibold text-gray-500 hover:text-amber-700 mb-3 transition-colors">
                <i class="fa-solid fa-chevron-left text-[10px]"></i> Approved 2D &amp; Quotation
            </a>

            <!-- ===================== HEADER ===================== -->
            <div id="acctDetailHeader" class="bg-white border border-gray-200 rounded-xl px-5 py-5 mb-4">
                <div class="space-y-2">
                    <div class="h-3 w-32 rounded bg-gray-100 animate-pulse"></div>
                    <div class="h-6 w-64 rounded bg-gray-100 animate-pulse"></div>
                    <div class="h-3 w-80 rounded bg-gray-100 animate-pulse"></div>
                </div>
            </div>

            <div id="acctDetailLoading" class="hidden"></div>

            <!-- ===================== BODY: kaliwa = detalye, kanan = deposit ===================== -->
            <div id="acctDetailContent" class="hidden grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_380px] gap-4 items-start">

                <div class="space-y-4 min-w-0">
                    <!-- Client & project -->
                    <section class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                        <div class="px-5 py-3 border-b border-gray-100 flex items-center gap-2">
                            <i class="fa-solid fa-user-tie text-amber-700 text-xs"></i>
                            <p class="text-sm font-semibold text-gray-900">Client &amp; Project</p>
                        </div>
                        <dl id="acctDetailClientRows" class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 px-5 py-4"></dl>
                    </section>

                    <!-- Files -->
                    <section class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-folder-open text-amber-700 text-xs"></i>
                                <p class="text-sm font-semibold text-gray-900">Approved Files</p>
                            </div>
                            <p id="acctDetailApprovedOn" class="text-[11px] text-gray-400"></p>
                        </div>
                        <div id="acctDetailFileRows" class="divide-y divide-gray-100"></div>
                    </section>
                </div>

                <!-- Deposit / Notice to Proceed -->
                <aside class="lg:sticky lg:top-6">
                    <section class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                        <div class="px-5 py-3 border-b border-gray-100 flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <i class="fa-solid fa-money-bill-wave text-amber-700 text-xs"></i>
                                <p class="text-sm font-semibold text-gray-900">Deposit &amp; Notice to Proceed</p>
                            </div>
                            <a href="<?= htmlspecialchars($acctPaymentMethodsUrl) ?>" target="_blank" rel="noopener"
                                class="text-[11px] font-semibold text-gray-400 hover:text-amber-700" title="Manage payment methods">
                                <i class="fa-solid fa-gear"></i>
                            </a>
                        </div>
                        <div id="acctDetailDepositSection"></div>
                    </section>
                </aside>
            </div>
        </div>

        <!-- Toast container -->
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
            toast.className = `pointer-events-auto flex items-start gap-2.5 border rounded-lg shadow-lg px-4 py-3 text-sm ${palette.wrap} translate-x-6 opacity-0 scale-95 transition-all duration-300 ease-out`;
            toast.innerHTML = `
                <span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold ${palette.icon}">${palette.symbol}</span>
                <span class="flex-1 leading-relaxed"></span>
                <button type="button" class="shrink-0 text-current opacity-50 hover:opacity-100 text-base leading-none" aria-label="Close">&times;</button>`;
            toast.querySelector('.flex-1').textContent = message;
            container.appendChild(toast);
            requestAnimationFrame(() => toast.classList.remove('translate-x-6', 'opacity-0', 'scale-95'));
            const remove = () => { toast.classList.add('translate-x-6', 'opacity-0', 'scale-95'); setTimeout(() => toast.remove(), 300); };
            toast.querySelector('button').addEventListener('click', remove);
            if (duration > 0) setTimeout(remove, duration);
        }

        const ACCT_LIST_AJAX_URL = <?= json_encode($acctListAjaxUrl) ?>;
        const ACCT_RECORD_ID = <?= json_encode($recordId) ?>;
        const ACCT_DETAIL_POLL_INTERVAL_MS = 8000;

        let acctDetailPollTimer = null;
        let acctDetailLastSignature = '';
        let acctDetailPaymentMethods = null; // cached after first fetch
        let acctDetailDepositSubmitting = false;

        function acctDetailEscapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }

        // Falls back to '—' for any field the backend hasn't returned yet.
        function acctDetailField(r, key) {
            const val = r?.[key];
            return (val === undefined || val === null || val === '') ? null : val;
        }

        function acctDetailFormatDateTimeLong(value) {
            if (!value) return '—';
            const dt = new Date(String(value).replace(' ', 'T'));
            if (isNaN(dt.getTime())) return value;
            return dt.toLocaleString('en-PH', {
                year: 'numeric', month: 'long', day: 'numeric',
                hour: 'numeric', minute: '2-digit', hour12: true
            });
        }

        function acctDetailTimeAgo(value) {
            if (!value) return '—';
            const dt = new Date(String(value).replace(' ', 'T'));
            if (isNaN(dt.getTime())) return value;

            const diffSec = Math.floor((Date.now() - dt.getTime()) / 1000);
            if (diffSec < 0) return acctDetailFormatDateTimeLong(value);
            if (diffSec < 60) return 'Just now';
            const diffMin = Math.floor(diffSec / 60);
            if (diffMin < 60) return `${diffMin}m ago`;
            const diffHr = Math.floor(diffMin / 60);
            if (diffHr < 24) return `${diffHr}h ago`;
            const diffDay = Math.floor(diffHr / 24);
            if (diffDay < 7) return `${diffDay}d ago`;
            return acctDetailFormatDateTimeLong(value);
        }

        function acctDetailCurrency(value) {
            if (value === null || value === undefined || value === '') return '—';
            const num = Number(String(value).replace(/,/g, ''));
            if (isNaN(num)) return acctDetailEscapeHtml(value);
            return '₱' + num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }


        const ACCT_PM_URL = <?= json_encode($acctPaymentMethodsUrl) ?>;

        // isang detalye (label sa itaas, laman sa ibaba)
        function acctDetailItem(label, value, wide = false) {
            return `<div class="min-w-0 ${wide ? 'sm:col-span-2' : ''}">
                <dt class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">${label}</dt>
                <dd class="text-[13px] text-gray-800 font-medium mt-0.5 break-words">${value ?? '<span class="text-gray-300">—</span>'}</dd>
            </div>`;
        }
        // hilera sa summary ng deposit
        function acctDetailRow(label, value) {
            return `<div class="flex items-start justify-between gap-3 py-2 text-[13px] border-b border-gray-100 last:border-b-0">
                <span class="text-gray-500 whitespace-nowrap">${label}</span>
                <span class="text-gray-900 font-semibold text-right break-words">${value}</span></div>`;
        }

        function acctDetailReviewBadge(status) {
            const map = {
                'Approved': ['bg-green-50 text-green-700 border-green-200', 'fa-circle-check'],
                'For Revision': ['bg-red-50 text-red-700 border-red-200', 'fa-rotate-left'],
                'Pending': ['bg-gray-50 text-gray-500 border-gray-200', 'fa-clock'],
            };
            const [cls, icon] = map[status] || map['Pending'];
            return `<span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10.5px] font-semibold border whitespace-nowrap ${cls}">
                <i class="fa-solid ${icon} text-[9px]"></i>${acctDetailEscapeHtml(status || 'Pending')}</span>`;
        }

        const ACCT_FILE_ICON = { '2D File': 'fa-pen-ruler', 'Quotation File': 'fa-file-invoice-dollar', '3D File': 'fa-cube' };
        function acctDetailFileCard(label, path, uploaderName, uploaderRole, reviewStatus) {
            const icon = ACCT_FILE_ICON[label] || 'fa-file-lines';
            return `<div class="flex items-center gap-3 px-5 py-3.5">
                <span class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0 ${path ? 'bg-amber-50 text-amber-700' : 'bg-gray-50 text-gray-300'}"><i class="fa-solid ${icon}"></i></span>
                <div class="min-w-0 flex-1">
                    <p class="text-[13px] font-semibold text-gray-900">${acctDetailEscapeHtml(label)}</p>
                    <p class="text-[11px] text-gray-400 truncate">${path ? `${acctDetailEscapeHtml(uploaderName || '—')} · ${acctDetailEscapeHtml(uploaderRole || '—')}` : 'No file uploaded'}</p>
                </div>
                ${acctDetailReviewBadge(reviewStatus)}
                ${path ? `<a href="${acctDetailEscapeHtml(path)}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:border-amber-400 hover:text-amber-800 whitespace-nowrap">Open <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i></a>` : ''}
            </div>`;
        }

        function acctDetailDepositBadge(status) {
            const isNtp = status === 'Notice to Proceed';
            return isNtp
                ? `<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[11px] font-semibold border bg-green-50 text-green-700 border-green-200"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>Notice to Proceed</span>`
                : `<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[11px] font-semibold border bg-amber-50 text-amber-700 border-amber-200"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>Hold · waiting for deposit</span>`;
        }

        function acctDetailRenderHeader(r) {
            const chip = (icon, text) => text ? `<span class="inline-flex items-center gap-1.5 text-xs text-gray-500"><i class="fa-solid ${icon} text-[10px] text-gray-400"></i>${text}</span>` : '';
            document.getElementById('acctDetailHeader').innerHTML = `
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="font-mono text-xs font-bold text-amber-700">${acctDetailEscapeHtml(r.control_no ?? '—')}</p>
                        <h1 class="text-gray-900 text-xl font-semibold mt-0.5 truncate">${acctDetailEscapeHtml(r.client_name)}</h1>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 mt-1.5">
                            ${chip('fa-building', acctDetailEscapeHtml(acctDetailField(r, 'branch') ?? ''))}
                            ${chip('fa-user', acctDetailEscapeHtml(acctDetailField(r, 'sales_staff_name') ?? ''))}
                            ${chip('fa-circle-check', 'Approved ' + acctDetailFormatDateTimeLong(r.reviewed_at))}
                        </div>
                    </div>
                    <div class="flex flex-col items-start sm:items-end gap-2">
                        <div class="text-left sm:text-right">
                            <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Contract Amount</p>
                            <p class="text-2xl font-bold text-gray-900 tabular-nums leading-tight">${acctDetailCurrency(acctDetailField(r, 'contract_amount'))}</p>
                        </div>
                        ${acctDetailDepositBadge(r.deposit_status)}
                    </div>
                </div>`;
        }

        async function acctDetailGetPaymentMethods() {
            if (acctDetailPaymentMethods) return acctDetailPaymentMethods;
            try {
                const res = await fetch(`${ACCT_LIST_AJAX_URL}?action=payment_methods`);
                const data = await res.json();
                acctDetailPaymentMethods = data.success ? data.items : [];
            } catch (e) {
                console.error('acctDetailGetPaymentMethods:', e);
                acctDetailPaymentMethods = [];
            }
            return acctDetailPaymentMethods;
        }


        async function acctDetailRenderDeposit(r) {
            const section = document.getElementById('acctDetailDepositSection');

            // ── tapos na: summary ──
            if (r.deposit_status === 'Notice to Proceed') {
                const slipLink = r.deposit_slip_path
                    ? `<a href="${acctDetailEscapeHtml(r.deposit_slip_path)}" target="_blank" rel="noopener" class="text-amber-700 hover:underline">View slip <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i></a>`
                    : '<span class="text-gray-400">No file</span>';
                section.innerHTML = `
                    <div class="px-5 py-4 bg-green-50/60 border-b border-green-100 flex items-center gap-3">
                        <span class="w-9 h-9 rounded-full bg-green-600 text-white flex items-center justify-center shrink-0"><i class="fa-solid fa-check"></i></span>
                        <div><p class="text-sm font-semibold text-green-800">Notice to Proceed given</p><p class="text-[11px] text-green-700">Cutting List, Operations and Design were notified.</p></div>
                    </div>
                    <div class="px-5 py-2">
                        ${acctDetailRow('Amount deposited', `<span class="text-base">${acctDetailCurrency(r.deposit_amount)}</span>`)}
                        ${acctDetailRow('Payment method', acctDetailEscapeHtml(r.deposit_payment_method_name || '—'))}
                        ${acctDetailRow('Deposit slip', slipLink)}
                        ${acctDetailRow('Logged by', `${acctDetailEscapeHtml(r.deposit_uploader_name || '—')} <span class="font-normal text-gray-400">· ${acctDetailEscapeHtml(r.deposit_uploaded_role || '—')}</span>`)}
                        ${acctDetailRow('Logged on', acctDetailFormatDateTimeLong(r.deposit_uploaded_at))}
                    </div>`;
                return;
            }

            if (r.status !== 'Approved') {
                section.innerHTML = `<div class="px-5 py-8 text-center text-gray-400 text-xs">The deposit can be logged once all files are approved.</div>`;
                return;
            }

            // ── Hold: form ──
            const methods = await acctDetailGetPaymentMethods();
            const inp = 'w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:border-amber-600 bg-white';
            section.innerHTML = `
                <form id="acctDepositForm" class="px-5 py-4 space-y-4" novalidate>
                    <p class="text-xs text-gray-500 leading-relaxed">Log the client's deposit to give the <b class="text-gray-700">Notice to Proceed</b>. Cutting List, Operations and Design will be notified right away.</p>

                    <label class="block">
                        <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Amount deposited <span class="text-red-500">*</span></span>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₱</span>
                            <input type="number" step="0.01" min="0.01" id="acctDepositAmount" placeholder="0.00" class="${inp} pl-7 tabular-nums">
                        </div>
                    </label>

                    <label class="block">
                        <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Payment method <span class="text-red-500">*</span></span>
                        <select id="acctDepositMethod" class="${inp}">
                            <option value="" disabled selected>Select method</option>
                            ${methods.map(m => `<option value="${m.id}">${acctDetailEscapeHtml(m.name)}</option>`).join('')}
                        </select>
                        ${methods.length === 0 ? `<span class="block text-[11px] text-red-500 mt-1">No payment methods yet — <a href="${ACCT_PM_URL}" target="_blank" class="underline">add one</a>.</span>` : ''}
                    </label>

                    <div>
                        <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Deposit slip <span class="text-red-500">*</span></span>
                        <label id="acctSlipDrop" class="flex items-center gap-3 rounded-lg border-2 border-dashed border-gray-200 px-4 py-3.5 cursor-pointer hover:border-amber-400 hover:bg-amber-50/40 transition-colors">
                            <span class="w-9 h-9 rounded-lg bg-gray-50 text-gray-400 flex items-center justify-center shrink-0"><i class="fa-solid fa-cloud-arrow-up"></i></span>
                            <span class="min-w-0">
                                <span id="acctSlipName" class="block text-xs font-semibold text-gray-700 truncate">Choose a file</span>
                                <span class="block text-[10.5px] text-gray-400">PDF, JPG or PNG · up to 10MB</span>
                            </span>
                            <input type="file" id="acctDepositSlip" accept=".pdf,.jpg,.jpeg,.png" class="hidden">
                        </label>
                    </div>

                    <label class="flex items-start gap-2.5 rounded-lg bg-amber-50 border border-amber-200 px-3 py-2.5 cursor-pointer">
                        <input type="checkbox" id="acctDepositConfirm" class="mt-0.5 w-4 h-4 accent-amber-700">
                        <span class="text-[11px] text-amber-900 leading-relaxed">I checked the amount and the deposit slip. This gives the Notice to Proceed and can't be undone.</span>
                    </label>

                    <button type="submit" id="acctDepositSubmitBtn" disabled
                        class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:bg-gray-300 disabled:cursor-not-allowed transition-colors">
                        <i class="fa-solid fa-flag-checkered"></i> Mark as Notice to Proceed
                    </button>
                </form>`;

            const slip = document.getElementById('acctDepositSlip');
            slip.addEventListener('change', () => {
                const f = slip.files[0];
                document.getElementById('acctSlipName').textContent = f ? f.name : 'Choose a file';
                document.getElementById('acctSlipDrop').classList.toggle('border-amber-400', !!f);
                document.getElementById('acctSlipDrop').classList.toggle('bg-amber-50/40', !!f);
            });
            document.getElementById('acctDepositConfirm').addEventListener('change', e => {
                document.getElementById('acctDepositSubmitBtn').disabled = !e.target.checked;
            });
            document.getElementById('acctDepositForm').addEventListener('submit', acctDetailSubmitDeposit);
        }

        async function acctDetailSubmitDeposit(e) {
            e.preventDefault();
            if (acctDetailDepositSubmitting) return;

            const amount = document.getElementById('acctDepositAmount').value;
            const methodId = document.getElementById('acctDepositMethod').value;
            const fileInput = document.getElementById('acctDepositSlip');
            const file = fileInput.files[0];

            if (!amount || Number(amount) <= 0) {
                crmShowToast('Please enter a valid deposit amount.', 'error');
                return;
            }
            if (!methodId) {
                crmShowToast('Please select a payment method.', 'error');
                return;
            }
            if (!file) {
                crmShowToast('Please attach the deposit slip.', 'error');
                return;
            }

            const btn = document.getElementById('acctDepositSubmitBtn');
            acctDetailDepositSubmitting = true;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';

            const formData = new FormData();
            formData.append('action', 'upload_deposit');
            formData.append('id', ACCT_RECORD_ID);
            formData.append('deposit_amount', amount);
            formData.append('deposit_payment_method_id', methodId);
            formData.append('deposit_slip', file);

            try {
                const res = await fetch(ACCT_LIST_AJAX_URL, { method: 'POST', body: formData });
                const data = await res.json();

                if (!data.success) {
                    crmShowToast(data.message || 'Failed to save deposit details.', 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-flag-checkered"></i> Mark as Notice to Proceed';
                    acctDetailDepositSubmitting = false;
                    return;
                }

                crmShowToast(data.message || 'Marked as Notice to Proceed.');
                acctDetailLastSignature = ''; // force re-render on next load
                acctDetailDepositSubmitting = false;
                acctDetailLoad();
            } catch (e) {
                console.error('acctDetailSubmitDeposit:', e);
                crmShowToast('Connection error. Please try again.', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-flag-checkered"></i> Mark as Notice to Proceed';
                acctDetailDepositSubmitting = false;
            }
        }


        async function acctDetailLoad({ silent = false } = {}) {
            const content = document.getElementById('acctDetailContent');
            try {
                // ito rin ang nagma-mark na read sa notification
                const res = await fetch(`${ACCT_LIST_AJAX_URL}?action=detail&id=${ACCT_RECORD_ID}`);
                const data = await res.json();
                if (!data.success) {
                    if (!silent) {
                        document.getElementById('acctDetailHeader').innerHTML = `<p class="text-sm text-red-500 py-4 text-center">${acctDetailEscapeHtml(data.message || 'Record not found.')}</p>`;
                        crmShowToast(data.message || 'Record not found.', 'error');
                    }
                    return;
                }
                const r = data.record;

                // walang binago → huwag i-render ulit (para hindi mabura ang tina-type sa form)
                const signature = JSON.stringify([
                    r.design_2d_review_status, r.quotation_review_status, r.design_3d_review_status,
                    r.design_2d_path, r.quotation_path, r.design_3d_path,
                    r.show_3d, r.status, r.reviewed_at, r.deposit_status, r.deposit_slip_path,
                ]);
                if (silent && signature === acctDetailLastSignature) return;
                acctDetailLastSignature = signature;

                acctDetailRenderHeader(r);
                document.getElementById('acctDetailApprovedOn').textContent = r.reviewed_at ? 'Approved ' + acctDetailFormatDateTimeLong(r.reviewed_at) : '';

                const esc = v => v === null ? null : acctDetailEscapeHtml(v);
                document.getElementById('acctDetailClientRows').innerHTML = [
                    acctDetailItem('Client', esc(r.client_name)),
                    acctDetailItem('Contact number', esc(acctDetailField(r, 'contact_number'))),
                    acctDetailItem('Address', esc(acctDetailField(r, 'address')), true),
                    acctDetailItem('Project type', esc(acctDetailField(r, 'project_type'))),
                    acctDetailItem('Scope of project', esc(acctDetailField(r, 'project_scope'))),
                    acctDetailItem('Measuring space', esc(acctDetailField(r, 'measuring_space'))),
                    acctDetailItem('Measurement date &amp; time', acctDetailField(r, 'measurement_datetime') ? acctDetailFormatDateTimeLong(r.measurement_datetime) : null),
                    acctDetailItem('Designer', esc(acctDetailField(r, 'designer_name'))),
                    acctDetailItem('Sales staff', esc(acctDetailField(r, 'sales_staff_name'))),
                ].join('');

                document.getElementById('acctDetailFileRows').innerHTML = [
                    acctDetailFileCard('2D File', r.design_2d_path, r.design_2d_uploader_name, r.design_2d_uploaded_role, r.design_2d_review_status),
                    acctDetailFileCard('Quotation File', r.quotation_path, r.quotation_uploader_name, r.quotation_uploaded_role, r.quotation_review_status),
                    r.show_3d ? acctDetailFileCard('3D File', r.design_3d_path, r.design_3d_uploader_name, r.design_3d_uploaded_role, r.design_3d_review_status) : '',
                ].join('');

                await acctDetailRenderDeposit(r);
                content.classList.remove('hidden');
            } catch (e) {
                console.error('acctDetailLoad:', e);
                if (!silent) crmShowToast('Connection error while loading the record.', 'error');
            }
        }

        function acctDetailStartPolling() {
            if (acctDetailPollTimer) clearInterval(acctDetailPollTimer);
            acctDetailPollTimer = setInterval(() => acctDetailLoad({ silent: true }), ACCT_DETAIL_POLL_INTERVAL_MS);
        }

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                if (acctDetailPollTimer) clearInterval(acctDetailPollTimer);
            } else {
                acctDetailLoad({ silent: true });
                acctDetailStartPolling();
            }
        });

        acctDetailLoad().then(acctDetailStartPolling);
    </script>
</body>

</html>