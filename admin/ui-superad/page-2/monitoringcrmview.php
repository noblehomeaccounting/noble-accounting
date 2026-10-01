<?php
// monitoringcrmview.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SUPERADMIN];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$monAjaxUrl = BASE_URL . '/monitoringcrmajax';
$monListUrl = BASE_URL . '/monitoring';
$inquiryId = intval($_GET['id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Monitoring — Tracking</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
</head>

<body class="bg-slate-100">
    <main class="ml-56 min-h-screen p-8 overflow-x-hidden">
        <!-- #region -->
        <div class="max-w-4xl mx-auto">

            <!-- Back link -->
            <a href="<?= htmlspecialchars($monListUrl) ?>"
                class="inline-flex items-center gap-1.5 text-xs font-medium text-gray-500 hover:text-amber-700 transition-colors mb-4">
                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                </svg>
                Back to Monitoring
            </a>

            <!-- Header card -->
            <div id="monHeaderCard" class="bg-white rounded-lg shadow-sm border border-gray-200 p-5 mb-5">
                <div class="h-4 w-40 rounded bg-gray-100 animate-pulse mb-2"></div>
                <div class="h-6 w-64 rounded bg-gray-100 animate-pulse"></div>
            </div>

            <?php include ROOT_PATH . '/admin/ui-superad/page-2/set-2sitevisit.php'; ?>
            <?php include ROOT_PATH . '/admin/ui-superad/page-2/set-3clientreviewapproval.php'; ?>
            <?php include ROOT_PATH . '/admin/ui-superad/page-2/set-4quotationhistory.php'; ?>
Arzhel
        </div>
Huibiuhbuibi
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

        const MON_AJAX_URL = <?= json_encode($monAjaxUrl) ?>;
        const MON_INQUIRY_ID = <?= json_encode($inquiryId) ?>;

        function monEscapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str ?? '';
            return div.innerHTML;
        }

        function monFormatDateTimeLong(value) {
            if (!value) return '—';
            const dt = new Date(value.replace(' ', 'T'));
            if (isNaN(dt.getTime())) return value;
            return dt.toLocaleString('en-PH', {
                year: 'numeric', month: 'long', day: 'numeric',
                hour: 'numeric', minute: '2-digit', hour12: true
            });
        }

        function monFormatMoney(value) {
            if (value === null || value === undefined || value === '') return '—';
            const num = Number(value);
            if (isNaN(num)) return '—';
            return '₱' + num.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        }

        function monDetailRow(label, value) {
            return `
                <div class="flex justify-between gap-3 py-2 border-b border-gray-100 text-[13px] last:border-b-0">
                    <span class="text-gray-400 whitespace-nowrap">${monEscapeHtml(label)}</span>
                    <span class="text-gray-800 font-medium text-right">${value || '—'}</span>
                </div>
            `;
        }

        function monRenderHeader(inquiry) {
            document.getElementById('monHeaderCard').innerHTML = `
                <div class="mb-4">
                    <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase mb-0.5">Control No. ${monEscapeHtml(inquiry.control_no)}</p>
                    <h1 class="text-gray-900 text-xl font-semibold">${monEscapeHtml(inquiry.client_name)}</h1>
                    <p class="text-xs text-gray-400 mt-1">Filed ${monFormatDateTimeLong(inquiry.created_at)} ${inquiry.branch ? '· ' + monEscapeHtml(inquiry.branch) + ' Branch' : ''}</p>
                </div>
                <div class="pt-1 border-t border-gray-100">
                    ${monDetailRow('Address', monEscapeHtml(inquiry.address))}
                    ${monDetailRow('Contact Number', monEscapeHtml(inquiry.contact_number))}
                    ${monDetailRow('Type of Project', monEscapeHtml(inquiry.project_type))}
                    ${monDetailRow('Scope of Project', monEscapeHtml(inquiry.project_scope))}
                    ${monDetailRow('Measuring Space', monEscapeHtml(inquiry.measuring_space))}
                    ${monDetailRow('Measurement Date and Time', monFormatDateTimeLong(inquiry.measurement_datetime))}
                    ${monDetailRow('Sales Staff', monEscapeHtml(inquiry.sales_staff_name))}
                    ${monDetailRow('Designer Assign', monEscapeHtml(inquiry.designer_name))}
                    ${monDetailRow('Contract Amount', monFormatMoney(inquiry.contract_amount))}
                </div>
            `;
        }

        async function monLoadTimeline() {
            if (!MON_INQUIRY_ID || MON_INQUIRY_ID <= 0) {
                document.getElementById('monHeaderCard').innerHTML = `<p class="text-sm text-red-500">Invalid record.</p>`;
                document.getElementById('monSiteVisits').innerHTML = '';
                document.getElementById('monDesignProgress').innerHTML = '';
                document.getElementById('monTimeline').innerHTML = '';
                return;
            }

            try {
                const res = await fetch(`${MON_AJAX_URL}?action=timeline&id=${MON_INQUIRY_ID}`);
                const data = await res.json();

                if (!data.success) {
                    document.getElementById('monHeaderCard').innerHTML = `<p class="text-sm text-red-500">${monEscapeHtml(data.message || 'Record not found.')}</p>`;
                    document.getElementById('monSiteVisits').innerHTML = '';
                    document.getElementById('monDesignProgress').innerHTML = '';
                    document.getElementById('monTimeline').innerHTML = '';
                    return;
                }

                monRenderHeader(data.inquiry);
                monRenderSiteVisits(data.site_visits);
                monRenderDesignProgress(data.design_progress);
                monRenderTimeline(data.cycles);

            } catch (e) {
                console.error('monLoadTimeline:', e);
                crmShowToast('Connection error while loading the record.', 'error');
            }
        }

        monLoadTimeline();
    </script>
</body>

</html>