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
                                <p class="text-sm font-semibold text-gray-900">Payments &amp; Notice to Proceed</p>
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

        function acctDetailDepositBadge(status, r = {}) {
            const isNtp = status === 'Notice to Proceed';
            if (!isNtp && Number(r.payment_count) > 0) {
                return `<span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-[11px] font-semibold border bg-sky-50 text-sky-700 border-sky-200"><span class="w-1.5 h-1.5 rounded-full bg-sky-500"></span>Paid ${acctDetailCurrency(r.paid_total)} · waiting for NTP</span>`;
            }
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
                        ${acctDetailDepositBadge(r.deposit_status, r)}
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


        // ═══════════════════════════════════════════════════════════
        // PAYMENTS (maraming bayad, bawat isa may resibo) → NOTICE TO PROCEED (huling hakbang)
        // ═══════════════════════════════════════════════════════════
        let acctPayments = [];
        let acctPaymentsReady = true;
        let acctRecord = null;
        let acctNtpStep = 0;          // 0 = sarado, 1 = review, 2 = final confirm
        let acctPayFormOpen = false;
        let acctPayDeleteId = 0;
        const ACCT_INP = 'w-full px-3 py-2.5 text-sm border border-gray-200 rounded-lg focus:outline-none focus:border-amber-600 bg-white';
        const acctNum = v => Number(String(v ?? 0).replace(/,/g, '')) || 0;
        const acctToday = () => { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; };
        function acctFmtDay(v) {
            if (!v) return '—';
            const d = new Date(String(v).slice(0, 10) + 'T00:00:00');
            return isNaN(d.getTime()) ? v : d.toLocaleDateString('en-PH', { year: 'numeric', month: 'short', day: 'numeric' });
        }
        // huwag i-render ulit habang may tina-type / kinukumpirma
        const acctDepositBusy = () => acctNtpStep > 0 || acctPayDeleteId > 0 || acctDetailDepositSubmitting
            || (acctPayFormOpen && !!document.querySelector('#acctPayForm [data-touched="1"]'));

        function acctDepositSummary(r) {
            const contract = acctNum(r.contract_amount), paid = acctNum(r.paid_total);
            const pct = contract > 0 ? Math.min(100, Math.round(paid / contract * 100)) : 0;
            const bal = Math.max(0, contract - paid);
            return `<div class="px-5 py-4 border-b border-gray-100">
                <div class="grid grid-cols-3 gap-2 text-center">
                    <div class="rounded-lg bg-gray-50 px-2 py-2"><p class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Contract</p><p class="text-[13px] font-bold text-gray-900 tabular-nums mt-0.5">${acctDetailCurrency(contract)}</p></div>
                    <div class="rounded-lg bg-green-50 px-2 py-2"><p class="text-[9.5px] font-semibold uppercase tracking-wider text-green-700">Paid</p><p class="text-[13px] font-bold text-green-800 tabular-nums mt-0.5">${acctDetailCurrency(paid)}</p></div>
                    <div class="rounded-lg ${bal > 0 ? 'bg-amber-50' : 'bg-gray-50'} px-2 py-2"><p class="text-[9.5px] font-semibold uppercase tracking-wider ${bal > 0 ? 'text-amber-700' : 'text-gray-400'}">Balance</p><p class="text-[13px] font-bold ${bal > 0 ? 'text-amber-800' : 'text-gray-900'} tabular-nums mt-0.5">${acctDetailCurrency(bal)}</p></div>
                </div>
                <div class="mt-3 h-2 rounded-full bg-gray-100 overflow-hidden"><div class="h-full rounded-full ${pct >= 100 ? 'bg-green-600' : 'bg-amber-500'}" style="width:${pct}%"></div></div>
                <p class="mt-1 text-[10.5px] text-gray-400">${pct}% of the contract paid · ${acctPayments.length} payment${acctPayments.length === 1 ? '' : 's'}</p>
            </div>`;
        }

        function acctDepositSteps(r) {
            const ntp = r.deposit_status === 'Notice to Proceed';
            const has = acctPayments.length > 0;
            const step = (n, label, done, cur) => `<li class="flex items-center gap-2 text-[11.5px] ${cur ? 'font-semibold text-gray-900' : done ? 'text-gray-600' : 'text-gray-400'}">
                <span class="w-5 h-5 rounded-full flex items-center justify-center text-[10px] font-bold shrink-0 ${done ? 'bg-green-600 text-white' : cur ? 'bg-amber-700 text-white' : 'bg-gray-100 text-gray-400'}">${done ? '<i class="fa-solid fa-check text-[9px]"></i>' : n}</span>${label}</li>`;
            return `<ol class="px-5 pt-4 flex items-center gap-2">
                ${step(1, 'Record payments', has, !has)}
                <li class="flex-1 h-px ${has ? 'bg-green-300' : 'bg-gray-200'}"></li>
                ${step(2, 'Notice to Proceed', ntp, has && !ntp)}
            </ol>`;
        }

        function acctPaymentRow(p, i, locked) {
            const confirm = acctPayDeleteId === p.id;
            const slip = p.slip_path
                ? `<a href="${acctDetailEscapeHtml(p.slip_path)}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-[11px] font-semibold text-amber-700 hover:underline"><i class="fa-solid fa-receipt text-[10px]"></i>Receipt</a>`
                : '<span class="text-[11px] text-gray-300">No receipt</span>';
            return `<li class="px-5 py-3 ${confirm ? 'bg-red-50' : ''}">
                <div class="flex items-start gap-3">
                    <span class="w-7 h-7 rounded-lg bg-green-50 text-green-700 text-[11px] font-bold flex items-center justify-center shrink-0">${i + 1}</span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-2">
                            <p class="text-sm font-bold text-gray-900 tabular-nums">${acctDetailCurrency(p.amount)}</p>
                            <p class="text-[11px] text-gray-500">${acctFmtDay(p.paid_on || p.created_at)}</p>
                        </div>
                        <p class="text-[11px] text-gray-500 break-words">${acctDetailEscapeHtml(p.method_name || '—')}${p.reference_no ? ` · Ref ${acctDetailEscapeHtml(p.reference_no)}` : ''}</p>
                        ${p.note ? `<p class="text-[11px] text-gray-600 mt-0.5 break-words">${acctDetailEscapeHtml(p.note)}</p>` : ''}
                        <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1">
                            ${slip}
                            <span class="text-[10.5px] text-gray-400">by ${acctDetailEscapeHtml(p.created_by_name || '—')}</span>
                            ${!locked && p.id > 0 && !confirm ? `<button type="button" data-pay-del="${p.id}" class="ml-auto text-[11px] font-semibold text-gray-400 hover:text-red-600"><i class="fa-solid fa-trash-can text-[10px] mr-0.5"></i>Remove</button>` : ''}
                        </div>
                        ${confirm ? `<div class="mt-2 flex flex-wrap items-center gap-2">
                            <p class="text-[11px] text-red-700 flex-1 min-w-[140px]">Remove this payment and its receipt?</p>
                            <button type="button" data-pay-del-no class="px-2.5 py-1 text-[11px] font-semibold text-gray-600 bg-white border border-gray-200 rounded-md">Cancel</button>
                            <button type="button" data-pay-del-yes="${p.id}" class="px-2.5 py-1 text-[11px] font-semibold text-white bg-red-600 rounded-md hover:bg-red-700">Remove</button>
                        </div>` : ''}
                    </div>
                </div>
            </li>`;
        }

        function acctPayFormHtml(methods, ntp) {
            if (!acctPayFormOpen) {
                return `<div class="px-5 py-3 border-t border-gray-100">
                    <button type="button" data-pay-open class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 text-xs font-semibold text-amber-800 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100">
                        <i class="fa-solid fa-plus"></i> ${acctPayments.length ? 'Record another payment' : 'Record a payment'}</button>
                </div>`;
            }
            return `<form id="acctPayForm" class="px-5 py-4 border-t border-gray-100 space-y-3 bg-slate-50/60" novalidate>
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-gray-500">${acctPayments.length ? `Payment #${acctPayments.length + 1}` : 'First payment (downpayment)'}</p>
                    ${acctPayments.length ? `<button type="button" data-pay-close aria-label="Close" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 shrink-0"><i class="fa-solid fa-xmark text-base leading-none"></i></button>` : ''}
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <label class="block col-span-2 sm:col-span-1">
                        <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Amount <span class="text-red-500">*</span></span>
                        <div class="relative"><span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">₱</span>
                        <input type="text" inputmode="decimal" name="amount" placeholder="0.00" class="${ACCT_INP} pl-7 tabular-nums"></div>
                    </label>
                    <label class="block col-span-2 sm:col-span-1">
                        <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Payment date <span class="text-red-500">*</span></span>
                        <input type="date" name="paid_on" value="${acctToday()}" max="${acctToday()}" class="${ACCT_INP}">
                    </label>
                </div>
                <label class="block">
                    <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Payment method <span class="text-red-500">*</span></span>
                    <select name="payment_method_id" class="${ACCT_INP}">
                        <option value="" selected disabled>Select method</option>
                        ${methods.map(m => `<option value="${m.id}">${acctDetailEscapeHtml(m.name)}</option>`).join('')}
                    </select>
                    ${methods.length === 0 ? `<span class="block text-[11px] text-red-500 mt-1">No payment methods yet — <a href="${ACCT_PM_URL}" target="_blank" class="underline">add one</a>.</span>` : ''}
                </label>
                <label class="block">
                    <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Reference / OR no.</span>
                    <input type="text" name="reference_no" maxlength="100" placeholder="e.g. OR 000123 or bank ref" class="${ACCT_INP}">
                </label>
                <div>
                    <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Receipt / deposit slip <span class="text-red-500">*</span></span>
                    <label id="acctSlipDrop" class="flex items-center gap-3 rounded-lg border-2 border-dashed border-gray-200 bg-white px-4 py-3 cursor-pointer hover:border-amber-400 hover:bg-amber-50/40 transition-colors">
                        <span class="w-9 h-9 rounded-lg bg-gray-50 text-gray-400 flex items-center justify-center shrink-0"><i class="fa-solid fa-cloud-arrow-up"></i></span>
                        <span class="min-w-0"><span id="acctSlipName" class="block text-xs font-semibold text-gray-700 truncate">Choose a file</span>
                        <span class="block text-[10.5px] text-gray-400">PDF, JPG or PNG · up to 10MB</span></span>
                        <input type="file" name="slip" accept=".pdf,.jpg,.jpeg,.png" class="hidden">
                    </label>
                </div>
                <label class="block">
                    <span class="block text-[10px] font-semibold uppercase tracking-wider text-gray-500 mb-1">Note</span>
                    <input type="text" name="note" maxlength="500" placeholder="e.g. 2nd payment, partial" class="${ACCT_INP}">
                </label>
                <p id="acctPayErr" class="hidden text-[11px] text-red-600"></p>
                <button type="submit" id="acctPaySaveBtn"
                    class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 text-xs font-semibold text-white bg-gray-900 rounded-lg hover:bg-black disabled:bg-gray-300 disabled:cursor-not-allowed">
                    <i class="fa-solid fa-floppy-disk"></i> Save payment</button>
                <p class="text-[10.5px] text-gray-400 text-center">${ntp ? 'Notice to Proceed was already given — this only adds to the payments.' : 'Saving a payment does not give the Notice to Proceed yet.'}</p>
            </form>`;
        }

        function acctNtpHtml(r) {
            if (r.deposit_status === 'Notice to Proceed' || !acctPayments.length) return '';
            const paid = acctNum(r.paid_total), bal = Math.max(0, acctNum(r.contract_amount) - paid);
            if (acctNtpStep === 0) {
                return `<div class="px-5 py-4 border-t border-gray-100">
                    <p class="text-[11px] text-gray-500 mb-2"><b class="text-gray-700">Last step:</b> once the client's payment is complete enough to start, give the Notice to Proceed.</p>
                    <button type="button" data-ntp="1" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 shadow-sm">
                        <i class="fa-solid fa-flag-checkered"></i> Give Notice to Proceed…</button>
                </div>`;
            }
            if (acctNtpStep === 1) {
                return `<div class="px-5 py-4 border-t border-amber-200 bg-amber-50/60">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-amber-800 mb-2">Step 1 of 2 · Review</p>
                    <div class="rounded-lg bg-white border border-amber-200 px-3 py-1">
                        ${acctDetailRow('Total paid', `<span class="text-green-700">${acctDetailCurrency(paid)}</span>`)}
                        ${acctDetailRow('Receipts', `${acctPayments.length} payment${acctPayments.length === 1 ? '' : 's'}`)}
                        ${acctDetailRow('Balance', bal > 0 ? `<span class="text-amber-700">${acctDetailCurrency(bal)}</span>` : 'Fully paid')}
                    </div>
                    <label class="mt-3 flex items-start gap-2.5 cursor-pointer">
                        <input type="checkbox" id="acctNtpCheck" class="mt-0.5 w-4 h-4 accent-amber-700 shrink-0">
                        <span class="text-[11.5px] text-amber-900 leading-relaxed">I checked every receipt and amount above.</span>
                    </label>
                    <div class="mt-3 flex gap-2">
                        <button type="button" data-ntp="0" class="flex-1 px-3 py-2 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">Cancel</button>
                        <button type="button" data-ntp="2" id="acctNtpNext" disabled class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:bg-gray-300 disabled:cursor-not-allowed">Continue <i class="fa-solid fa-arrow-right text-[10px]"></i></button>
                    </div>
                </div>`;
            }
            return `<div class="px-5 py-4 border-t border-red-200 bg-red-50/60">
                <p class="text-[10px] font-bold uppercase tracking-wider text-red-700 mb-1.5">Step 2 of 2 · Final confirm</p>
                <p class="text-sm font-semibold text-gray-900">Give the Notice to Proceed for ${acctDetailEscapeHtml(r.client_name || '')}?</p>
                <ul class="mt-2 space-y-1 text-[11.5px] text-red-800">
                    <li><i class="fa-solid fa-bell mr-1"></i>Cutting List, Operations and Design will be notified and production can start.</li>
                    <li><i class="fa-solid fa-lock mr-1"></i>This can't be undone. Recorded payments can no longer be removed.</li>
                </ul>
                <div class="mt-3 flex gap-2">
                    <button type="button" data-ntp="1" class="flex-1 px-3 py-2 text-xs font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50"><i class="fa-solid fa-arrow-left text-[10px] mr-1"></i>Back</button>
                    <button type="button" data-ntp-go id="acctNtpGo" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700"><i class="fa-solid fa-flag-checkered"></i> Yes, give NTP</button>
                </div>
            </div>`;
        }

        async function acctDetailRenderDeposit(r) {
            acctRecord = r;
            const section = document.getElementById('acctDetailDepositSection');
            if (r.status !== 'Approved') {
                section.innerHTML = `<div class="px-5 py-8 text-center text-gray-400 text-xs">Payments can be recorded once all files are approved.</div>`;
                return;
            }
            const ntp = r.deposit_status === 'Notice to Proceed';
            if (!acctPayments.length && !ntp) acctPayFormOpen = true;   // unang bayad: bukas agad ang form
            const methods = acctPayFormOpen ? await acctDetailGetPaymentMethods() : [];

            section.innerHTML = `
                ${ntp ? `<div class="px-5 py-3 bg-green-50/70 border-b border-green-100 flex items-center gap-3">
                    <span class="w-8 h-8 rounded-full bg-green-600 text-white flex items-center justify-center shrink-0"><i class="fa-solid fa-check text-xs"></i></span>
                    <div class="min-w-0"><p class="text-[13px] font-semibold text-green-800">Notice to Proceed given</p>
                    <p class="text-[11px] text-green-700">${acctDetailFormatDateTimeLong(r.deposit_uploaded_at)} · ${acctDetailEscapeHtml(r.deposit_uploader_name || '—')}</p></div>
                </div>` : ''}
                ${!acctPaymentsReady ? `<p class="mx-5 mt-4 text-[11px] text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2"><i class="fa-solid fa-database mr-1"></i>Run <b>noblecrm_deposit_payment.sql</b> to enable multiple payments.</p>` : ''}
                ${acctDepositSteps(r)}
                ${acctDepositSummary(r)}
                <div>
                    <p class="px-5 pt-3 text-[10px] font-bold uppercase tracking-wider text-gray-400">Payments</p>
                    ${acctPayments.length
                        ? `<ul class="divide-y divide-gray-100">${acctPayments.map((p, i) => acctPaymentRow(p, i, ntp)).join('')}</ul>`
                        : `<p class="px-5 py-3 text-[12px] text-gray-400">No payment recorded yet.</p>`}
                </div>
                ${acctPaymentsReady ? acctPayFormHtml(methods, ntp) : ''}
                ${acctPaymentsReady ? acctNtpHtml(r) : ''}`;

            const form = document.getElementById('acctPayForm');
            if (form) {
                form.addEventListener('input', e => { e.target.dataset.touched = '1'; document.getElementById('acctPayErr').classList.add('hidden'); });
                form.addEventListener('change', e => { e.target.dataset.touched = '1'; });
                form.amount.addEventListener('input', () => { form.amount.value = form.amount.value.replace(/[^\d.,]/g, ''); });
                form.slip.addEventListener('change', () => {
                    const f = form.slip.files[0];
                    document.getElementById('acctSlipName').textContent = f ? f.name : 'Choose a file';
                    document.getElementById('acctSlipDrop').classList.toggle('border-amber-400', !!f);
                });
                form.addEventListener('submit', acctSavePayment);
            }
            const chk = document.getElementById('acctNtpCheck');
            if (chk) chk.addEventListener('change', () => { document.getElementById('acctNtpNext').disabled = !chk.checked; });
        }

        // isang listener para sa lahat ng button sa deposit section
        document.getElementById('acctDetailDepositSection').addEventListener('click', async e => {
            const t = e.target.closest('button');
            if (!t || !acctRecord) return;
            if (t.matches('[data-pay-open]')) { acctPayFormOpen = true; acctNtpStep = 0; return acctDetailRenderDeposit(acctRecord); }
            if (t.matches('[data-pay-close]')) { acctPayFormOpen = false; return acctDetailRenderDeposit(acctRecord); }
            if (t.matches('[data-pay-del]')) { acctPayDeleteId = +t.dataset.payDel; return acctDetailRenderDeposit(acctRecord); }
            if (t.matches('[data-pay-del-no]')) { acctPayDeleteId = 0; return acctDetailRenderDeposit(acctRecord); }
            if (t.matches('[data-pay-del-yes]')) return acctDeletePayment(+t.dataset.payDelYes, t);
            if (t.matches('[data-ntp]')) {
                const n = +t.dataset.ntp;
                if (n === 2 && !document.getElementById('acctNtpCheck')?.checked) return;
                acctNtpStep = n;
                if (n > 0) acctPayFormOpen = false;
                return acctDetailRenderDeposit(acctRecord);
            }
            if (t.matches('[data-ntp-go]')) return acctGiveNtp(t);
        });

        async function acctPost(fd) {
            const res = await fetch(ACCT_LIST_AJAX_URL, { method: 'POST', body: fd });
            const text = await res.text();
            try { return JSON.parse(text); } catch (x) { throw new Error(`Server error (HTTP ${res.status}).`); }
        }

        async function acctSavePayment(e) {
            e.preventDefault();
            if (acctDetailDepositSubmitting) return;
            const form = e.currentTarget;
            const err = document.getElementById('acctPayErr');
            const fail = (msg, el) => { err.textContent = msg; err.classList.remove('hidden'); el?.focus(); };
            const amount = acctNum(form.amount.value);
            if (!(amount > 0)) return fail('Please enter a valid amount.', form.amount);
            if (!form.paid_on.value) return fail('Please enter the payment date.', form.paid_on);
            if (form.paid_on.value > acctToday()) return fail('The payment date cannot be in the future.', form.paid_on);
            if (!form.payment_method_id.value) return fail('Please select a payment method.', form.payment_method_id);
            const file = form.slip.files[0];
            if (!file) return fail('Please attach the receipt / deposit slip.');
            if (file.size > 10 * 1024 * 1024) return fail('File is too large (max 10MB).');

            const fd = new FormData();
            fd.append('action', 'add_payment');
            fd.append('id', ACCT_RECORD_ID);
            fd.append('amount', String(amount));
            fd.append('paid_on', form.paid_on.value);
            fd.append('payment_method_id', form.payment_method_id.value);
            fd.append('reference_no', form.reference_no.value.trim());
            fd.append('note', form.note.value.trim());
            fd.append('slip', file);

            const btn = document.getElementById('acctPaySaveBtn');
            acctDetailDepositSubmitting = true;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';
            try {
                const data = await acctPost(fd);
                if (!data.success) { fail(data.message || 'Failed to save the payment.'); return; }
                crmShowToast(`Payment of ${acctDetailCurrency(amount).replace(/<[^>]+>/g, '')} recorded.`);
                acctPayFormOpen = false;
                acctDetailLastSignature = '';
                acctDetailDepositSubmitting = false;
                await acctDetailLoad();
            } catch (x) {
                console.error('acctSavePayment:', x);
                fail(x.message || 'Connection error. Please try again.');
            } finally {
                acctDetailDepositSubmitting = false;
                if (document.body.contains(btn)) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-floppy-disk"></i> Save payment'; }
            }
        }

        async function acctDeletePayment(paymentId, btn) {
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
            const fd = new FormData();
            fd.append('action', 'delete_payment');
            fd.append('id', ACCT_RECORD_ID);
            fd.append('payment_id', paymentId);
            try {
                const data = await acctPost(fd);
                if (!data.success) { crmShowToast(data.message || 'Could not remove the payment.', 'error'); }
                else crmShowToast('Payment removed.');
            } catch (x) {
                crmShowToast(x.message || 'Connection error.', 'error');
            }
            acctPayDeleteId = 0;
            acctDetailLastSignature = '';
            await acctDetailLoad();
        }

        async function acctGiveNtp(btn) {
            if (acctDetailDepositSubmitting) return;
            acctDetailDepositSubmitting = true;
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Giving NTP…';
            const fd = new FormData();
            fd.append('action', 'give_ntp');
            fd.append('id', ACCT_RECORD_ID);
            fd.append('confirm', 'NTP');
            try {
                const data = await acctPost(fd);
                if (!data.success) {
                    crmShowToast(data.message || 'Could not give the Notice to Proceed.', 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-flag-checkered"></i> Yes, give NTP';
                    return;
                }
                crmShowToast(data.message || 'Notice to Proceed given.');
                acctNtpStep = 0;
                acctDetailLastSignature = '';
                acctDetailDepositSubmitting = false;
                await acctDetailLoad();
            } catch (x) {
                crmShowToast(x.message || 'Connection error.', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-flag-checkered"></i> Yes, give NTP';
            } finally {
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
                acctPayments = data.payments || [];
                acctPaymentsReady = data.payments_ready !== false;

                // walang binago → huwag i-render ulit (para hindi mabura ang tina-type sa form)
                if (silent && acctDepositBusy()) return;
                const signature = JSON.stringify([
                    acctPayments.map(p => p.id + ':' + p.amount),
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