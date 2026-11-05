<?php
// PurchaseOrder.php  —  Purchasing (PO) Head: approval ng Purchase Requests · Superadmin = tingin lang

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SUPERADMIN, ROLE_PO];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

// Approval: Purchasing Head na · Superadmin = review / monitor lang (walang Approve / Return)
include_once ROOT_PATH . '/network/head-approval.php';
$nhApprover = nhApproverViewer($conn, ROLE_PO);
nhApproverPageGuard($nhApprover, 'Purchasing Head');
$nhCanApprove = $nhApprover === 'head';

$praAjaxUrl = BASE_URL . '/purchaseorderapprovalajax';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <!-- maximum-scale=1: hindi na mag-a-auto-zoom ang iPhone kapag nag-tap sa field (pwede pa ring mag-pinch zoom) -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1">
    <title>Purchase Order</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <style>
        /* Print: kinokopya ang form sa #nhPrintArea (direct child ng body) para hindi maputol ng modal */
        #nhPrintArea { display: none; }
        @media print {
            @page { size: A4 portrait; margin: 8mm; }
            html, body { background: #fff !important; height: auto !important; overflow: visible !important; }
            body > *:not(#nhPrintArea) { display: none !important; }
            #nhPrintArea { display: block !important; width: 100%; zoom: .86; }
            #nhPrintArea > * { min-width: 0 !important; width: 100% !important; }
            #nhPrintArea * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            #nhPrintArea table, #nhPrintArea tr, #nhPrintArea img { page-break-inside: avoid; break-inside: avoid; }
        }
    </style>
</head>

<body class="bg-slate-100">
    <main id="main-content"
        class="md:ml-56 min-h-screen px-3 pt-20 pb-6 sm:px-4 md:p-8 overflow-x-hidden transition-all duration-300">

        <div class="max-w-6xl mx-auto">

            <!-- ===================== HEADER ===================== -->
            <div class="flex flex-wrap items-end justify-between gap-2 sm:gap-3 mb-4 sm:mb-5">
                <div class="min-w-0">
                    <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase mb-0.5">Purchasing</p>
                    <h1 class="text-gray-900 text-lg sm:text-xl font-semibold">Purchase Request Approval</h1>
                    <p class="text-gray-400 text-xs mt-0.5"><?= $nhCanApprove ? 'Review purchase requests from Purchasing. Approving signs the form as Purchasing Head.' : 'View only — the Purchasing Head approves these requests.' ?></p>
                </div>
                <span class="inline-flex items-center gap-1.5 text-[11px] text-gray-500 bg-white border border-gray-200 rounded-full px-3 py-1.5">
                    <span id="praLiveDot" class="w-2 h-2 rounded-full bg-amber-400"></span>
                    <span id="praLiveText">Auto-refresh every 30s</span>
                </span>
            </div>

            <!-- ===================== STATS ===================== -->
            <div class="grid grid-cols-3 gap-1.5 sm:gap-3 mb-4 sm:mb-5">
                <div class="min-w-0 bg-white border border-gray-200 rounded-xl px-2.5 py-2 sm:px-5 sm:py-4 flex items-center gap-4">
                    <span class="hidden sm:flex shrink-0 w-11 h-11 rounded-xl bg-amber-50 text-amber-700 items-center justify-center"><i class="fa-solid fa-hourglass-half"></i></span>
                    <div class="min-w-0">
                        <p class="truncate text-[9.5px] sm:text-[10px] font-semibold uppercase sm:tracking-wider text-gray-400"><span class="sm:hidden">Pending</span><span class="hidden sm:inline">For Approval</span></p>
                        <p id="praStatPending" class="truncate text-lg sm:text-2xl font-bold text-gray-900 leading-tight tabular-nums">—</p>
                    </div>
                </div>
                <div class="min-w-0 bg-white border border-gray-200 rounded-xl px-2.5 py-2 sm:px-5 sm:py-4 flex items-center gap-4">
                    <span class="hidden sm:flex shrink-0 w-11 h-11 rounded-xl bg-green-50 text-green-600 items-center justify-center"><i class="fa-solid fa-file-signature"></i></span>
                    <div class="min-w-0">
                        <p class="truncate text-[9.5px] sm:text-[10px] font-semibold uppercase sm:tracking-wider text-gray-400"><span class="sm:hidden">Today</span><span class="hidden sm:inline">Approved Today</span></p>
                        <p id="praStatToday" class="truncate text-lg sm:text-2xl font-bold text-gray-900 leading-tight tabular-nums">—</p>
                    </div>
                </div>
                <div class="min-w-0 bg-white border border-gray-200 rounded-xl px-2.5 py-2 sm:px-5 sm:py-4 flex items-center gap-4">
                    <span class="hidden sm:flex shrink-0 w-11 h-11 rounded-xl bg-slate-100 text-slate-600 items-center justify-center"><i class="fa-solid fa-peso-sign"></i></span>
                    <div class="min-w-0">
                        <p class="truncate text-[9.5px] sm:text-[10px] font-semibold uppercase sm:tracking-wider text-gray-400"><span class="sm:hidden">Amount</span><span class="hidden sm:inline">Pending Amount</span></p>
                        <p id="praStatAmount" class="truncate text-lg sm:text-2xl font-bold text-gray-900 leading-tight tabular-nums">—</p>
                    </div>
                </div>
            </div>

            <!-- ===================== LIST ===================== -->
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <div class="flex flex-wrap items-center gap-2 px-3 sm:px-4 py-3 border-b border-gray-100">
                    <div class="grid grid-cols-3 sm:inline-flex w-full sm:w-auto min-w-0 items-center gap-0.5 sm:gap-1 bg-gray-50 border border-gray-200 rounded-lg p-1">
                        <?php foreach (['Pending' => ['For Approval', 'bg-amber-500'], 'Approved' => ['Approved', 'bg-green-500'], 'Rejected' => ['Returned', 'bg-red-500']] as $k => [$lbl, $dot]): ?>
                            <button type="button" data-tab="<?= $k ?>"
                                class="pra-tab min-w-0 justify-center inline-flex items-center gap-1 sm:gap-2 px-1 sm:px-3 py-2 sm:py-1.5 rounded-md text-[11px] sm:text-xs font-semibold text-gray-500 whitespace-nowrap transition-all">
                                <span class="shrink-0 w-1.5 h-1.5 rounded-full <?= $dot ?>"></span>
                                <span class="sm:hidden truncate"><?= $k === 'Pending' ? 'Pending' : $lbl ?></span><span class="hidden sm:inline"><?= $lbl ?></span>
                                <span data-count="<?= $k ?>" class="shrink-0 min-w-[18px] sm:min-w-[20px] px-1 sm:px-1.5 py-px rounded-full bg-gray-200 text-gray-600 text-[10px] font-bold text-center">—</span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="relative flex-1 min-w-0 sm:flex-none sm:ml-auto sm:w-80">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[11px]"></i>
                        <input type="text" id="praSearch" placeholder="Search req no., control no., BOM no., client..."
                            class="w-full h-[38px] sm:h-auto bg-white border border-gray-200 rounded-lg pl-8 pr-3 py-2 text-xs outline-none focus:border-amber-600 transition-colors">
                    </div>
                    <button type="button" data-fd-open="praFd" class="sm:hidden shrink-0 inline-flex items-center gap-1.5 h-[38px] px-3 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:border-amber-500 "><i class="fa-solid fa-sliders"></i> Filters <span data-fd-badge class="hidden min-w-[18px] h-[18px] px-1 rounded-full bg-amber-700 text-white text-[10px] font-bold inline-flex items-center justify-center">0</span></button>
                </div>

                <!-- Filters: petsa + sort (sa phone: drawer sa gilid, kagaya ng CRM Monitoring) -->
                <div class="sm:flex sm:flex-wrap items-end gap-2 sm:px-4 sm:py-2.5 sm:border-b border-gray-100 bg-gray-50/50">
                <div id="praFdBackdrop" class="nh-fd-backdrop"></div>
<div id="praFd" class="nh-fd" role="dialog" aria-label="Filters">
    <div class="nh-fd-only flex items-center justify-between px-4 py-3 border-b border-gray-100">
        <div class="flex items-center gap-2"><i class="fa-solid fa-sliders text-amber-600"></i><h2 class="text-gray-900 text-sm font-semibold">Filters</h2></div>
        <button type="button" data-fd-close aria-label="Close" class="w-10 h-10 flex items-center justify-center text-gray-400 hover:text-gray-700 hover:bg-gray-100 rounded-lg text-2xl leading-none">&times;</button>
    </div>
    <div class="nh-fd-body">
<p class="nh-fd-only nh-fd-label">Date</p>
                <div class="grid grid-cols-2 gap-2 sm:contents">
                    <div class="col-span-2 grid grid-cols-3 sm:flex items-center gap-1">
                        <button type="button" data-pra-qdate="today" class="pra-qdate px-2 sm:px-2.5 py-2 sm:py-1.5 text-[11px] font-medium bg-white border border-gray-200 rounded-lg text-gray-600 hover:border-amber-400 hover:text-amber-700 transition-colors">Today</button>
                        <button type="button" data-pra-qdate="week" class="pra-qdate px-2 sm:px-2.5 py-2 sm:py-1.5 text-[11px] font-medium bg-white border border-gray-200 rounded-lg text-gray-600 hover:border-amber-400 hover:text-amber-700 transition-colors">This week</button>
                        <button type="button" data-pra-qdate="month" class="pra-qdate px-2 sm:px-2.5 py-2 sm:py-1.5 text-[11px] font-medium bg-white border border-gray-200 rounded-lg text-gray-600 hover:border-amber-400 hover:text-amber-700 transition-colors">This month</button>
                    </div>
                    <label class="block">
                        <span class="block text-[9.5px] font-semibold uppercase tracking-wider text-gray-400 mb-0.5">Month</span>
                        <input id="praMonth" type="month" class="w-full sm:w-auto h-9 sm:h-auto px-2.5 py-1 text-xs bg-white border border-gray-200 rounded-lg focus:outline-none focus:border-amber-600">
                    </label>
                    <label class="block">
                        <span class="block text-[9.5px] font-semibold uppercase tracking-wider text-gray-400 mb-0.5">Day</span>
                        <input id="praDay" type="date" class="w-full sm:w-auto h-9 sm:h-auto px-2.5 py-1 text-xs bg-white border border-gray-200 rounded-lg focus:outline-none focus:border-amber-600">
                    </label>
                    <label class="block">
                        <span class="block text-[9.5px] font-semibold uppercase tracking-wider text-gray-400 mb-0.5">From</span>
                        <input id="praFrom" type="date" class="w-full sm:w-auto h-9 sm:h-auto px-2.5 py-1 text-xs bg-white border border-gray-200 rounded-lg focus:outline-none focus:border-amber-600">
                    </label>
                    <label class="block">
                        <span class="block text-[9.5px] font-semibold uppercase tracking-wider text-gray-400 mb-0.5">To</span>
                        <input id="praTo" type="date" class="w-full sm:w-auto h-9 sm:h-auto px-2.5 py-1 text-xs bg-white border border-gray-200 rounded-lg focus:outline-none focus:border-amber-600">
                    </label>
                    <button type="button" id="praDateClear" class="hidden col-span-2 px-2 py-1.5 text-[11px] font-medium text-gray-500 hover:text-amber-700">Clear date</button>
                    <p id="praDateHint" class="col-span-2 text-[10.5px] text-gray-400 sm:pb-1.5 empty:hidden"></p>
                    <label class="block col-span-2 sm:ml-auto">
                        <span class="block text-[9.5px] font-semibold uppercase tracking-wider text-gray-400 mb-0.5">Sort by</span>
                        <select id="praSort" data-fd-ignore class="w-full sm:w-auto h-9 sm:h-auto px-2.5 py-1.5 text-xs bg-white border border-gray-200 rounded-lg focus:outline-none focus:border-amber-600">
                            <option value="newest">Newest first</option>
                            <option value="oldest">Oldest first</option>
                            <option value="amount_desc">Amount: high → low</option>
                            <option value="amount_asc">Amount: low → high</option>
                            <option value="client">Client (A–Z)</option>
                        </select>
                    </label>
                </div>

    </div>
    <div class="nh-fd-only px-4 py-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] border-t border-gray-100 bg-gray-50/60">
        <button type="button" data-fd-close class="w-full text-xs font-semibold bg-amber-600 hover:bg-amber-700 text-white px-4 py-2.5 rounded-lg transition-colors">Show results</button>
    </div>
</div>
                </div>

                <!-- Phone: cards (ang table ay para sa tablet/desktop) -->
                <div id="praCards" class="md:hidden divide-y divide-gray-100"></div>

                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-sm min-w-[1020px]">
                        <thead class="bg-gray-50 text-[10.5px] font-semibold uppercase tracking-wider text-gray-500">
                            <tr class="border-b border-gray-200">
                                <th class="px-5 py-3 text-left">Req No.</th>
                                <th class="px-5 py-3 text-left">Project ID</th>
                                <th class="px-5 py-3 text-left">Client</th>
                                <th class="px-5 py-3 text-left">Requested / Prepared</th>
                                <th class="px-5 py-3 text-center">Items</th>
                                <th class="px-5 py-3 text-right">Total</th>
                                <th class="px-5 py-3 text-left" id="praColDate">Submitted</th>
                                <th class="px-5 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody id="praList" class="divide-y divide-gray-100">
                            <tr><td colspan="8" class="px-5 py-12 text-center text-gray-400 text-xs"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
                <p id="praFooter" class="px-3 sm:px-5 py-2.5 border-t border-gray-100 bg-gray-50/60 text-[11px] text-gray-400"></p>
            </div>
        </div>

        <div id="crmToastContainer" class="fixed left-4 right-4 bottom-[calc(1rem+env(safe-area-inset-bottom))] sm:left-auto sm:bottom-4 z-[10000] flex flex-col gap-2.5 pointer-events-none sm:w-[360px]"></div>
    </main>

    <!-- ===================== REVIEW MODAL (form + actions) ===================== -->
    <div id="praModal" class="hidden fixed inset-0 z-[9998] items-start justify-center bg-black/50 p-0 sm:p-4 overflow-y-auto">
        <div class="bg-white sm:rounded-xl shadow-xl w-full max-w-6xl min-h-full sm:min-h-0 sm:my-6 overflow-hidden">
            <div class="sticky top-0 z-10 bg-white px-4 py-3 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                <div class="min-w-0 flex-1">
                    <p id="praModalNo" class="font-mono text-xs font-semibold text-red-600"></p>
                    <p id="praModalSub" class="text-[11px] text-gray-400 truncate"></p>
                </div>
                <div class="flex items-center gap-2 w-full sm:w-auto shrink-0 order-last sm:order-none">
                    <button type="button" id="praPrint" class="inline-flex justify-center items-center gap-1.5 px-3 py-1.5 text-[11px] font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">
                        <i class="fa-solid fa-print"></i><span class="hidden sm:inline"> Print</span>
                    </button>
                    <button type="button" id="praRejectBtn" class="flex-1 sm:flex-none justify-center inline-flex items-center gap-1.5 px-3 py-2.5 sm:py-1.5 text-[11px] font-semibold text-red-600 bg-white border border-red-200 rounded-lg hover:bg-red-50">
                        <i class="fa-solid fa-rotate-left"></i> Return
                    </button>
                    <button type="button" id="praApproveBtn" class="flex-1 sm:flex-none justify-center inline-flex items-center gap-1.5 px-3.5 py-2.5 sm:py-1.5 text-[11px] font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700 shadow-sm disabled:opacity-60">
                        <i class="fa-solid fa-signature"></i> Approve &amp; Sign
                    </button>
                    <button type="button" id="praClose" aria-label="Close" class="hidden sm:flex ml-1 -mr-1 w-8 h-8 items-center justify-center rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg></button>
                </div>
                <!-- Phone: Close katabi ng Req No. -->
                <button type="button" onclick="document.getElementById('praClose').click()" aria-label="Close" class="sm:hidden -mr-2 w-10 h-10 flex items-center justify-center rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100"><svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.25" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 6l12 12M18 6L6 18"/></svg></button>
            </div>
            <div id="praNotice" class="hidden mx-4 mt-3 rounded-lg px-3 py-2 text-xs"></div>
            <div class="p-3 sm:p-4 md:p-6 flex flex-col xl:flex-row gap-5 pb-[calc(1rem+env(safe-area-inset-bottom))]">
                <p class="md:hidden -mb-3 text-[11px] text-gray-400"><i class="fa-solid fa-left-right mr-1"></i>Swipe sideways to see the whole form.</p>
                <div class="flex-1 min-w-0 overflow-x-auto"><div id="praSheet" class="min-w-[760px]"></div></div>
                <aside id="praHistory" class="xl:w-72 shrink-0"></aside>
            </div>
        </div>
    </div>

    <!-- ===================== APPROVE CONFIRM MODAL ===================== -->
    <div id="praApproveModal" class="hidden fixed inset-0 z-[9999] items-end sm:items-center justify-center bg-black/50 sm:p-4">
        <div class="bg-white rounded-t-2xl sm:rounded-xl shadow-xl w-full sm:max-w-sm overflow-hidden pb-[env(safe-area-inset-bottom)]">
            <div class="p-5">
                <div class="flex items-center gap-2.5 mb-3">
                    <span class="w-9 h-9 rounded-full bg-green-50 text-green-600 flex items-center justify-center"><i class="fa-solid fa-file-signature text-sm"></i></span>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">Approve this request?</p>
                        <p id="praApproveNo" class="font-mono text-[11px] font-semibold text-red-600"></p>
                    </div>
                </div>
                <div class="rounded-lg bg-gray-50 border border-gray-200 px-3 py-2.5 text-xs text-gray-600 space-y-1">
                    <p><span class="text-gray-400">Client:</span> <span id="praApproveClient" class="font-semibold text-gray-800"></span></p>
                    <p><span class="text-gray-400">Control No.:</span> <span id="praApproveControl" class="font-mono font-semibold text-amber-700"></span></p>
                    <p><span class="text-gray-400">BOM No.:</span> <span id="praApproveBom" class="font-mono text-gray-700"></span></p>
                    <p><span class="text-gray-400">Total:</span> <span id="praApproveTotal" class="font-semibold text-gray-800"></span></p>
                </div>
                <p class="text-[11px] text-gray-500 mt-3 leading-relaxed">
                    <i class="fa-solid fa-signature text-green-600 mr-1"></i>
                    Your name and saved signature will be placed in <b>Approved By</b> as Purchasing Head. This cannot be undone.
                </p>
            </div>
            <div class="flex justify-end gap-2 px-5 py-3 bg-gray-50 border-t border-gray-100">
                <button type="button" id="praApproveCancel" class="flex-1 sm:flex-none px-3.5 py-2.5 sm:py-2 text-[11px] font-semibold text-gray-500 bg-white border border-gray-200 rounded-lg hover:bg-gray-100">Cancel</button>
                <button type="button" id="praApproveConfirm" class="flex-1 sm:flex-none px-3.5 py-2.5 sm:py-2 text-[11px] font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-60">
                    <i class="fa-solid fa-check mr-1"></i> Approve &amp; Sign
                </button>
            </div>
        </div>
    </div>

    <!-- ===================== RETURN REASON MODAL ===================== -->
    <div id="praRejectModal" class="hidden fixed inset-0 z-[9999] items-end sm:items-center justify-center bg-black/50 sm:p-4">
        <div class="bg-white rounded-t-2xl sm:rounded-xl shadow-xl w-full sm:max-w-sm p-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))] sm:pb-5">
            <div class="flex items-center gap-2.5 mb-1">
                <span class="w-8 h-8 rounded-full bg-red-50 text-red-500 flex items-center justify-center"><i class="fa-solid fa-rotate-left text-xs"></i></span>
                <p class="text-sm font-semibold text-gray-800">Return to Purchasing</p>
            </div>
            <p class="text-xs text-gray-400 mb-3">Tell Purchasing what to fix.</p>
            <textarea id="praRejectRemarks" rows="3" placeholder="e.g. Please attach supplier quotation"
                class="w-full text-[13px] sm:text-xs border border-gray-200 rounded-lg p-2.5 focus:outline-none focus:ring-1 focus:ring-amber-400 resize-none"></textarea>
            <p id="praRejectError" class="hidden text-[11px] text-red-500 mt-1">A reason is required.</p>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" id="praRejectCancel" class="flex-1 sm:flex-none px-3.5 py-2.5 sm:py-2 text-[11px] font-semibold text-gray-500 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100">Cancel</button>
                <button type="button" id="praRejectConfirm" class="flex-1 sm:flex-none px-3.5 py-2.5 sm:py-2 text-[11px] font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-60">Confirm Return</button>
            </div>
        </div>
    </div>

    <script>
        const NH_CAN_APPROVE = <?= json_encode($nhCanApprove) ?>; // Purchasing Head lang; Superadmin = tingin lang
// PRSheet — iisang itsura ng naka-submit na Purchase Request Form
// Ginagamit ng index-purchaserequest.php (PO) at PurchaseOrder.php (Superadmin).
// PRSheet.render(element, data, { base: BASE_URL })
(function () {
    const SIGN = {
        noted:    { name: '',                                   title: 'SITE OPERATIONS MANAGER' },
        approved: { name: '',                                   title: 'Purchasing Head' },
        reviewed: { name: 'MARY GRACE RIVERA',                  title: 'ACCOUNTING HEAD' },
        released: { name: 'JOLINA JEAN EPA/JAYA JEAN LIBRANDO', title: 'ACCOUNTING STAFF' },
    };

    const CSS = `
        .pr-sheet { background:#fff; color:#111; font-family: Arial, Helvetica, sans-serif; font-size:11px; }
        .pr-sheet table { width:100%; border-collapse:collapse; table-layout:fixed; }
        .pr-sheet td, .pr-sheet th { border:1px solid #333; padding:4px 6px; vertical-align:middle; overflow-wrap:anywhere; }
        .pr-sheet table.nb, .pr-sheet table.nb > tbody > tr > td, .pr-sheet table.nb > tr > td { border:none; }
        .pr-sheet .pr-mono { font-family:'Courier New', Courier, monospace; }
        .pr-sheet .pr-bar { background:#111; color:#fff; text-align:center; font-weight:700; letter-spacing:.03em; }
        .pr-sheet .pr-red { color:#d00; }
        .pr-sheet .pr-hint { font-family:'Courier New', Courier, monospace; font-style:italic; color:#d00; font-size:9px; text-align:center; }
        .pr-sheet .pr-line { border-bottom:1px solid #111; }
        .pr-sheet .pr-sig { position:relative; height:54px; }
        .pr-sheet .pr-sig img { position:absolute; left:50%; bottom:6px; transform:translateX(-50%); height:52px; width:auto; max-width:90%; object-fit:contain; z-index:2; pointer-events:none; }
        .pr-sheet .pr-sig .nm { position:absolute; left:0; right:0; bottom:0; text-align:center; font-weight:700; z-index:1; }
    `;
    if (!document.getElementById('pr-sheet-css')) {
        const st = document.createElement('style');
        st.id = 'pr-sheet-css';
        st.textContent = CSS;
        document.head.appendChild(st);
    }

    const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
    const money = n => (Number(n) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const qtyFmt = q => { const n = parseFloat(q); return isNaN(n) ? '' : n.toFixed(2).replace(/\.?0+$/, ''); };
    const shortDate = iso => {
        if (!iso) return '';
        const d = new Date(String(iso).slice(0, 10) + 'T00:00:00');
        return isNaN(d) ? iso : `${String(d.getDate()).padStart(2, '0')}-${d.toLocaleString('en-US', { month: 'short' })}-${String(d.getFullYear()).slice(2)}`;
    };

    function render(el, d, opts = {}) {
        el.classList.add('pr-sheet');
        const BASE = opts.base ?? '';
        const sigImg = p => p ? `<img src="${BASE}/${esc(p)}" alt="">` : '';
        const status = d.status || 'Pending';


        const items = d.items || [];
        const itemRows = items.map((it, i) => `
            <tr>
                <td style="text-align:center">${i + 1}</td>
                <td style="font-family:Arial;font-weight:700;font-size:10px">${esc(it.item_name)}</td>
                <td style="text-align:center;height:56px">${it.picture_path ? `<img src="${BASE}/${esc(it.picture_path)}" style="max-height:52px;max-width:100%;margin:auto;object-fit:contain">` : ''}</td>
                <td>${qtyFmt(it.quantity)} ${esc(it.unit)}</td>
                <td style="text-align:right">${money(it.price)}</td>
                <td style="text-align:right">${money(it.amount)}</td>
                <td>${esc(it.remarks)}</td>
            </tr>`).join('') + '<tr><td style="height:24px"></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>'.repeat(Math.max(0, 3 - items.length));

        const approvedName  = status === 'Approved' ? (d.approved_by || SIGN.approved.name) : SIGN.approved.name;
        const approvedTitle = status === 'Approved' ? (d.approved_by_title || SIGN.approved.title) : SIGN.approved.title;
        const approvedSig   = status === 'Approved' ? d.approved_by_signature_path : null;

        // Accounting Head — napupunan kapag na-review na (reviewed_at)
        const isReviewed    = !!d.reviewed_at;
        const reviewedName  = isReviewed ? (d.reviewed_by || SIGN.reviewed.name) : SIGN.reviewed.name;
        const reviewedTitle = isReviewed ? (d.reviewed_by_title || SIGN.reviewed.title) : SIGN.reviewed.title;
        const reviewedSig   = isReviewed ? d.reviewed_by_signature_path : null;

        // Custodian / Custodian Assistant — napupunan kapag na-release na (released_at)
        const isReleased    = !!d.released_at;
        const releasedName  = isReleased ? (d.released_by || SIGN.released.name) : SIGN.released.name;
        const releasedTitle = isReleased ? (d.released_by_title || SIGN.released.title) : SIGN.released.title;
        const releasedSig   = isReleased ? d.released_by_signature_path : null;

        el.innerHTML = `
        <table class="nb"><tr>
            <td style="width:16%;text-align:left"><img src="${BASE}/icon/logo.webp" alt="NobleHome" style="height:58px"></td>
            <td style="text-align:center">
                <div style="font-family:Georgia,'Times New Roman',serif;font-weight:700;font-size:19px;letter-spacing:.02em">NOBLEHOME CONSTRUCTION CORPORATION</div>
                <div style="font-family:Georgia,serif;font-size:9px">1181MC Premiere Bldg, EDSA Balintawak Quezon City</div>
                <div style="font-family:Georgia,serif;font-style:italic;font-size:8.5px">noblehomeconst.ph@gmail.com/ Tel. No. 02-88221295/Cell. No. 0968-591-6544</div>
            </td>
            <td style="width:16%"></td>
        </tr></table>

        <table style="margin-top:10px"><tr><td class="pr-bar" style="padding:5px">PURCHASE REQUEST FORM</td></tr></table>

        <table style="margin-top:-1px"><tr>
            <td style="height:58px;text-align:right;vertical-align:top;padding:8px">
                <div style="display:inline-block;background:#e5e5e5;border-radius:4px;padding:6px 10px;text-align:left;min-width:200px">
                    <div>Req No: <b class="pr-red">${esc(d.req_no)}</b></div>
                    <div style="font-size:7.5px;margin-top:3px">"Strictly for Internal Use Only"</div>
                </div>
            </td>
        </tr></table>

        <table class="pr-mono" style="margin-top:-1px">
            <tr>
                <td style="width:18%;text-align:center;background:#eee">CLIENT</td>
                <td style="width:47%">${esc(d.client)}</td>
                <td style="width:16%;background:#eee">REQUEST DATE:</td>
                <td style="width:19%;text-align:right">${esc(shortDate(d.request_date))}</td>
            </tr>
            <tr>
                <td style="text-align:center;background:#eee">PROJECT SCOPE</td>
                <td>${esc(d.project_scope)}</td>
                <td style="background:#eee">DATE NEEDED:</td>
                <td>${esc(d.date_needed)}</td>
            </tr>
        </table>

        <table class="pr-mono" style="margin-top:-1px">
            <colgroup>
                <col style="width:5%"><col style="width:22%"><col style="width:13%"><col style="width:13%">
                <col style="width:12%"><col style="width:13%"><col>
            </colgroup>
            <tr><td class="pr-bar" colspan="7" style="font-weight:400">The information below must be filled out completely, correctly, and legibly .</td></tr>
            <tr style="text-align:center">
                <td>NO.</td><td>ITEM NAME</td><td>ITEM PICTURE</td><td>QUANTITY/UNIT</td><td>PRICE</td><td>AMOUNT</td><td>REMARKS</td>
            </tr>
            ${itemRows}
            <tr>
                <td colspan="3" style="text-align:center;font-weight:700">***TOTAL***</td>
                <td></td><td></td>
                <td style="text-align:right;font-weight:700">${money(d.total_amount)}</td>
                <td></td>
            </tr>
        </table>

        <table class="nb" style="margin-top:22px;font-style:italic">
            <tr>
                <td style="width:50%;vertical-align:top;padding:0 26px">
                    <div style="font-weight:700;font-style:normal">Noted By:</div>
                    <div class="pr-sig"><span class="nm">${esc(SIGN.noted.name)}</span></div>
                    <div class="pr-line"></div>
                    <div style="text-align:center">${esc(SIGN.noted.title)}</div>
                </td>
                <td style="width:50%;vertical-align:top;padding:16px 26px 0">
                    <div style="font-weight:700;font-style:normal">Prepared By:</div>
                    <div class="pr-sig">${sigImg(d.prepared_by_signature_path)}<span class="nm" style="font-style:normal">${esc(d.prepared_by)}</span></div>
                    <div class="pr-line"></div>
                    <div style="text-align:center">${esc(d.prepared_by_title || 'Purchasing')}</div>
                </td>
            </tr>
            <tr>
                <td style="vertical-align:top;padding:14px 26px 0">
                    <div style="font-weight:700;font-style:normal">Approved By:</div>
                    <div class="pr-sig">${sigImg(approvedSig)}<span class="nm" style="font-style:normal">${esc(approvedName)}</span></div>
                    <div class="pr-line"></div>
                    <div style="text-align:center">${esc(approvedTitle)}</div>
                </td>
                <td style="vertical-align:top;padding:14px 26px 0">
                    <div class="pr-sig" style="margin-top:14px">${sigImg(d.requested_by_signature_path)}<span class="nm" style="font-style:normal">${esc(d.requested_by)}</span></div>
                    <div class="pr-line"></div>
                    <div style="text-align:center;font-size:9px">Requested by:</div>
                </td>
            </tr>
        </table>

        <p class="pr-hint" style="margin-top:22px">Processing Policies:&nbsp; Request for Payment Form must be approved by the authorized person responsible for the budget item.</p>
        <p class="pr-hint" style="margin-top:8px">Please attach supplier quotation for each budget item</p>

        <table style="margin-top:14px">
            <tr><td style="border-left:none;border-right:none;padding:0">
                <div style="text-align:center;font-weight:700;font-style:italic;font-size:9.5px;padding-top:4px">For Accounting Use Only (if budget is to be released from main office)</div>
                <div style="text-align:center;font-weight:700;font-size:9.5px;margin-top:4px">PAYMENT INFORMATION</div>
                <table class="nb" style="margin-top:6px;font-size:9.5px">
                    ${['Account Name:', 'Account No.:', 'Check Amount:', 'Check Date:'].map(l => `
                    <tr><td style="width:22%;padding:1px 4px">${l}</td><td style="padding:1px 0"><div class="pr-line" style="width:72%;height:11px"></div></td></tr>`).join('')}
                </table>
                <div class="pr-hint" style="text-align:right;padding:4px">***to be filled out by the custodian</div>
            </td></tr>
        </table>

        <table class="nb" style="margin-top:10px">
            <tr>
                <td style="width:55%;vertical-align:top;padding:0 26px">
                    <div style="font-weight:700;font-style:italic">Reviewed By:</div>
                    <div class="pr-sig" style="height:46px">${sigImg(reviewedSig)}<span class="nm">${esc(reviewedName)}</span></div>
                    <div class="pr-line"></div>
                    <div style="text-align:center;font-style:italic">${esc(reviewedTitle)}</div>
                    <div style="font-weight:700;font-style:italic;margin-top:14px">Released by:</div>
                    <div class="pr-sig" style="height:46px">${sigImg(releasedSig)}<span class="nm">${esc(releasedName)}</span></div>
                    <div class="pr-line"></div>
                    <div style="text-align:center;font-style:italic">${esc(releasedTitle)}</div>
                </td>
                <td style="width:45%;vertical-align:top">
                    <table><tr><td style="height:96px"></td></tr>
                        <tr><td style="text-align:center;font-weight:700;font-size:10px">Paid Stamp by the Custodian/Doc Controller</td></tr></table>
                </td>
            </tr>
        </table>

        <p class="pr-hint" style="margin-top:18px;border-top:1px solid #333;padding-top:6px">Please ensure that the corresponding sales invoice is attached to every payment submission in compliance with our recording and documentation policies. This requirement is necessary to validate, properly record, and legitimize each transaction.</p>
        <p class="pr-hint" style="margin-top:8px">Failure to provide the required invoice within three (3) working days shall subject the payment to appropriate action in accordance with company policy.</p>
        `;
    }


    // ── Status History: sino-sino na ang pumirma / tumanggap (hindi kasama sa print) ──
    function history(el, d, opts = {}) {
        if (!el) return;
        const BASE = opts.base ?? '';
        const when = v => {
            if (!v) return '';
            const t = new Date(String(v).replace(' ', 'T'));
            return isNaN(t) ? '' : t.toLocaleString('en-PH', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit' });
        };
        const rejected = d.status === 'Rejected';
        const steps = [
            { label: 'Submitted', done: true, at: d.created_at, who: d.prepared_by, role: d.prepared_by_title || 'Purchasing', sig: d.prepared_by_signature_path, desc: 'Prepared by', wait: '' },
            { label: 'Requested', done: !!d.requested_by, at: null, who: d.requested_by, role: 'From BOM', sig: d.requested_by_signature_path, desc: 'Requested by', wait: 'Waiting for requester' },
            rejected
                ? { label: 'Returned', done: true, bad: true, at: d.rejected_at, who: '', role: 'Purchasing Head', desc: 'Returned by the Purchasing Head', note: d.rejection_remarks, wait: '' }
                : { label: 'Approved', done: d.status === 'Approved', at: d.approved_at, who: d.approved_by, role: d.approved_by_title || 'Purchasing Head', sig: d.approved_by_signature_path, desc: 'Approved by', wait: 'Waiting for Purchasing Head approval' },
        ];
        if (!rejected) steps.push(
            { label: 'Reviewed', done: !!d.reviewed_at, at: d.reviewed_at, who: d.reviewed_by, role: d.reviewed_by_title || 'Accounting Head', sig: d.reviewed_by_signature_path, desc: 'Reviewed by', wait: 'Waiting for Accounting Head review' },
            { label: 'Received', done: !!d.staff_received_at, at: d.staff_received_at, who: d.staff_received_by, role: 'Accounting Staff', desc: 'Received by', wait: 'Waiting for Accounting Staff', nosig: true },
            { label: 'Released', done: !!d.released_at, at: d.released_at, who: d.released_by, role: d.released_by_title || 'Custodian', sig: d.released_by_signature_path, desc: 'Released by', wait: 'Waiting for Custodian release' },
            { label: 'Paid', done: !!d.paid_at, at: d.paid_at, who: '', role: '', desc: 'Paid stamp applied', wait: 'Waiting to be paid' },
        );

        let lastDone = -1;
        steps.forEach((s, i) => { if (s.done) lastDone = i; });

        const items = steps.map((s, i) => {
            const isCurrent = i === lastDone;
            const isNext = !s.done && i === lastDone + 1;
            const tone = s.bad ? { dot: 'bg-red-500', pill: 'bg-red-50 text-red-600 border-red-200' }
                : s.done ? { dot: 'bg-emerald-500', pill: 'bg-emerald-50 text-emerald-700 border-emerald-200' }
                : isNext ? { dot: 'bg-amber-400', pill: 'bg-amber-50 text-amber-700 border-amber-200' }
                : { dot: 'bg-gray-300', pill: 'bg-gray-50 text-gray-400 border-gray-200' };
            const label = s.done ? s.label : (isNext ? s.wait : s.label);
            const body = s.done
                ? `${s.at ? `<p class="text-[11px] text-gray-400 mt-1">${esc(when(s.at))}</p>` : ''}
                   <p class="text-xs text-gray-600 mt-0.5">${esc(s.desc)}${s.who ? ` <b class="text-gray-800">${esc(s.who)}</b>` : ''}</p>
                   ${s.role && s.who ? `<p class="text-[10px] uppercase tracking-wide text-gray-400">${esc(s.role)}</p>` : ''}
                   ${s.note ? `<p class="text-[11px] text-red-600 mt-1 italic">“${esc(s.note)}”</p>` : ''}
                   ${s.sig ? `<img src="${BASE}/${esc(s.sig)}" alt="Signature" class="h-9 w-auto max-w-[160px] object-contain mt-1">`
                           : (s.who && !s.nosig ? `<p class="text-[10px] text-gray-400 italic mt-0.5">No saved signature</p>` : '')}`
                : `<p class="text-[11px] text-gray-400 mt-1">${isNext ? 'Not yet signed' : 'Pending'}</p>`;
            return `
                <li class="relative pl-6 pb-5 last:pb-0">
                    ${i < steps.length - 1 ? '<span class="absolute left-[5px] top-3 bottom-0 w-px bg-gray-300"></span>' : ''}
                    <span class="absolute left-0 top-1.5 w-[11px] h-[11px] rounded-full ring-4 ring-white ${tone.dot}"></span>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="inline-flex px-2.5 py-0.5 rounded-full border text-[10.5px] font-bold uppercase tracking-wide ${tone.pill}">${esc(label)}</span>
                        ${isCurrent ? '<span class="text-[10px] font-bold uppercase tracking-wider text-amber-700">Current</span>' : ''}
                    </div>
                    ${body}
                </li>`;
        }).join('');

        el.innerHTML = `
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between">
                    <p class="text-sm font-semibold text-gray-800">Status History</p>
                    <span class="text-[10px] font-semibold text-gray-400">${steps.filter(s => s.done).length}/${steps.length}</span>
                </div>
                <ol class="px-4 py-4">${items}</ol>
            </div>`;
    }

    window.PRSheet = { render, history, SIGN };
})();
    </script>
    <script>

        // I-print lang ang form: kopya sa labas ng modal para buo ang lalabas
        function nhPrintSheet(id) {
            const src = document.getElementById(id);
            if (!src) return;
            let area = document.getElementById('nhPrintArea');
            if (!area) { area = document.createElement('div'); area.id = 'nhPrintArea'; document.body.appendChild(area); }
            area.innerHTML = '';
            const copy = src.cloneNode(true);
            copy.removeAttribute('id');
            area.appendChild(copy);
            const imgs = [...area.querySelectorAll('img')].filter(i => !i.complete);
            Promise.all(imgs.map(i => new Promise(r => { i.onload = i.onerror = r; }))).then(() => {
                window.print();
                setTimeout(() => { area.innerHTML = ''; }, 500);
            });
        }
        (function () {
            const AJAX = <?= json_encode($praAjaxUrl) ?>;
            const BASE = <?= json_encode(BASE_URL) ?>;

            let rows = [];
            let tab = 'Pending';
            let current = null;   // bukas na request sa modal
            let dateF = { day: '', month: '', week: '', from: '', to: '' };   // isa lang ang aktibo
            let sortBy = 'newest';
            const WAIT_WARN_DAYS = 3;   // ilang araw na For Approval bago maging pula

            const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
            const money = n => (Number(n) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const toDate = v => v ? new Date(String(v).replace(' ', 'T')) : null;
            const fmtDate = v => { const d = toDate(v); return d && !isNaN(d) ? d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—'; };
            const fmtTime = v => { const d = toDate(v); return d && !isNaN(d) ? d.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' }) : ''; };

            // Gaano na katagal: "Just now", "20m ago", "2h ago", "Yesterday", "4 days ago"
            const ageSec = v => { const d = toDate(v); return d && !isNaN(d) ? Math.max(0, Math.floor((Date.now() - d.getTime()) / 1000)) : null; };
            function ago(v) {
                const s = ageSec(v);
                if (s === null) return '';
                if (s < 60) return 'Just now';
                if (s < 3600) return `${Math.floor(s / 60)}m ago`;
                if (s < 86400) return `${Math.floor(s / 3600)}h ago`;
                const d = Math.floor(s / 86400);
                if (d === 1) return 'Yesterday';
                if (d < 30) return `${d} days ago`;
                const m = Math.floor(d / 30);
                return m < 12 ? `${m} month${m === 1 ? '' : 's'} ago` : `${Math.floor(d / 365)}y ago`;
            }

            // Petsang ipinapakita sa bawat tab (Submitted / Approved / Returned)
            const whenOf = r => r.status === 'Pending' ? r.created_at : r.status === 'Approved' ? r.approved_at : r.rejected_at;

            const pad = n => String(n).padStart(2, '0');
            const ymd = dt => `${dt.getFullYear()}-${pad(dt.getMonth() + 1)}-${pad(dt.getDate())}`;
            const dateActive = () => !!(dateF.day || dateF.month || dateF.week || dateF.from || dateF.to);
            function dateOk(r) {
                if (!dateActive()) return true;
                const d = String(whenOf(r) || '').slice(0, 10);
                if (!d) return false;
                if (dateF.from || dateF.to) return (!dateF.from || d >= dateF.from) && (!dateF.to || d <= dateF.to);
                if (dateF.day) return d === dateF.day;
                if (dateF.month) return d.slice(0, 7) === dateF.month;
                const end = new Date(dateF.week + 'T00:00:00'); end.setDate(end.getDate() + 6);
                return d >= dateF.week && d <= ymd(end);
            }
            function dateLabel() {
                if (dateF.from || dateF.to) {
                    const f = v => new Date(v + 'T00:00:00').toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' });
                    return dateF.from && dateF.to ? `${f(dateF.from)} – ${f(dateF.to)}` : dateF.from ? `${f(dateF.from)} onwards` : `up to ${f(dateF.to)}`;
                }
                if (dateF.day) return fmtDate(dateF.day + ' 00:00:00');
                if (dateF.month) { const d = toDate(dateF.month + '-01 00:00:00'); return d ? d.toLocaleDateString('en-PH', { month: 'long', year: 'numeric' }) : dateF.month; }
                return dateF.week ? 'this week' : '';
            }
            const datePhrase = () => dateF.week ? 'this week' : (dateF.from || dateF.to) ? 'from ' + dateLabel() : 'on ' + dateLabel();
            function syncDateUI() {
                document.getElementById('praMonth').value = dateF.month;
                document.getElementById('praDay').value = dateF.day;
                document.getElementById('praFrom').value = dateF.from;
                document.getElementById('praTo').value = dateF.to;
                document.getElementById('praFrom').max = dateF.to || '';
                document.getElementById('praTo').min = dateF.from || '';
                document.getElementById('praDateClear').classList.toggle('hidden', !dateActive());
                const t = ymd(new Date());
                document.querySelectorAll('.pra-qdate').forEach(b => {
                    const q = b.dataset.praQdate;
                    const on = (q === 'today' && dateF.day === t) || (q === 'month' && dateF.month === t.slice(0, 7)) || (q === 'week' && !!dateF.week);
                    ['bg-amber-700', 'text-white', 'border-amber-700'].forEach(c => b.classList.toggle(c, on));
                    ['bg-white', 'text-gray-600', 'border-gray-200'].forEach(c => b.classList.toggle(c, !on));
                });
                const basis = tab === 'Pending' ? 'submitted' : tab === 'Approved' ? 'approved' : 'returned';
                document.getElementById('praDateHint').textContent = dateActive() ? `Showing requests ${basis} ${datePhrase()}` : '';
            }
            function setDate(next) {
                dateF = Object.assign({ day: '', month: '', week: '', from: '', to: '' }, next);
                syncDateUI();
                render();
            }

            // Control No. at BOM No. — para makilala ang project kahit pareho ang pangalan ng client
            function idCell(r) {
                const ctrl = r.control_no
                    ? String(r.control_no).split(', ').map(c => `<span class="block font-mono text-[11.5px] font-semibold text-amber-700 whitespace-nowrap">${esc(c)}</span>`).join('')
                    : '<span class="block text-[11px] text-gray-300">No control no.</span>';
                const bom = r.bom_numbers
                    ? String(r.bom_numbers).split(', ').map(b => `<span class="block font-mono text-[10px] text-gray-400 whitespace-nowrap">${esc(b)}</span>`).join('')
                    : '';
                return ctrl + bom;
            }

            function toast(message, type = 'success', duration = 4000) {
                const box = document.getElementById('crmToastContainer');
                const p = type === 'success'
                    ? { wrap: 'bg-green-50 border-green-200 text-green-700', icon: 'bg-green-200 text-green-700', sym: '✓' }
                    : { wrap: 'bg-red-50 border-red-200 text-red-700', icon: 'bg-red-200 text-red-700', sym: '!' };
                const t = document.createElement('div');
                t.className = `pointer-events-auto flex items-start gap-2.5 border rounded-lg shadow-lg px-4 py-3 text-sm ${p.wrap} transition-all duration-300`;
                t.innerHTML = `<span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold ${p.icon}">${p.sym}</span><span class="flex-1 leading-relaxed"></span>`;
                t.querySelector('.flex-1').textContent = message;
                box.appendChild(t);
                setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, duration);
            }

            const openModal = id => { const m = document.getElementById(id); m.classList.remove('hidden'); m.classList.add('flex'); };
            const closeModal = id => { const m = document.getElementById(id); m.classList.add('hidden'); m.classList.remove('flex'); };
            const anyOpen = () => ['praModal', 'praRejectModal', 'praApproveModal'].some(id => !document.getElementById(id).classList.contains('hidden'));

            // ── tabs ──
            const tabColor = { Pending: 'text-amber-700', Approved: 'text-green-700', Rejected: 'text-red-600' };
            function setTab(t) {
                tab = t;
                document.querySelectorAll('.pra-tab').forEach(b => {
                    const on = b.dataset.tab === t;
                    ['bg-white', 'shadow-sm', 'text-amber-700', 'text-green-700', 'text-red-600'].forEach(c => b.classList.remove(c));
                    if (on) b.classList.add('bg-white', 'shadow-sm', tabColor[b.dataset.tab]);
                    b.classList.toggle('text-gray-500', !on);
                });
                document.getElementById('praColDate').textContent = t === 'Pending' ? 'Submitted' : t === 'Approved' ? 'Approved' : 'Returned';
                syncDateUI();
                render();
            }
            document.querySelectorAll('.pra-tab').forEach(b => b.addEventListener('click', () => setTab(b.dataset.tab)));
            document.getElementById('praSearch').addEventListener('input', render);
            document.getElementById('praSort').addEventListener('change', e => { sortBy = e.target.value; render(); });
            document.querySelectorAll('.pra-qdate').forEach(b => b.addEventListener('click', () => {
                const now = new Date();
                const q = b.dataset.praQdate;
                if (q === 'today') { const t = ymd(now); setDate(dateF.day === t ? {} : { day: t }); }
                else if (q === 'month') { const m = ymd(now).slice(0, 7); setDate(dateF.month === m ? {} : { month: m }); }
                else { const mon = new Date(now); mon.setDate(now.getDate() - ((now.getDay() + 6) % 7)); setDate(dateF.week ? {} : { week: ymd(mon) }); }
            }));
            document.getElementById('praMonth').addEventListener('change', e => setDate(e.target.value ? { month: e.target.value } : {}));
            document.getElementById('praDay').addEventListener('change', e => setDate(e.target.value ? { day: e.target.value } : {}));
            document.getElementById('praDateClear').addEventListener('click', () => setDate({}));
            document.getElementById('praFrom').addEventListener('change', e => setDate({ from: e.target.value, to: dateF.to }));
            document.getElementById('praTo').addEventListener('change', e => setDate({ from: dateF.from, to: e.target.value }));
            // para gumalaw ang "x minutes ago" kahit walang bagong data
            setInterval(() => { if (!document.hidden && !anyOpen()) render(); }, 60000);

            // ── list ──
            function render() {
                const by = s => rows.filter(r => r.status === s);
                // bilang sa tabs: sumusunod sa date filter
                ['Pending', 'Approved', 'Rejected'].forEach(s => document.querySelector(`[data-count="${s}"]`).textContent = by(s).filter(dateOk).length);
                document.getElementById('praStatPending').textContent = by('Pending').length;
                document.getElementById('praStatToday').textContent = rows.filter(r => r.approved_today).length;
                document.getElementById('praStatAmount').textContent = '₱ ' + money(by('Pending').reduce((a, r) => a + Number(r.total_amount || 0), 0));

                const q = document.getElementById('praSearch').value.trim().toLowerCase();
                const source = by(tab).filter(dateOk);
                const list = source.filter(r => !q || `${r.req_no} ${r.control_no ?? ''} ${r.bom_numbers ?? ''} ${r.client} ${r.project_scope ?? ''} ${r.requested_by ?? ''} ${r.prepared_by ?? ''}`.toLowerCase().includes(q));
                const ts = r => { const d = toDate(whenOf(r)); return d && !isNaN(d) ? d.getTime() : 0; };
                const sorters = {
                    newest: (a, b) => ts(b) - ts(a) || b.id - a.id,
                    oldest: (a, b) => ts(a) - ts(b) || a.id - b.id,
                    amount_desc: (a, b) => Number(b.total_amount || 0) - Number(a.total_amount || 0),
                    amount_asc: (a, b) => Number(a.total_amount || 0) - Number(b.total_amount || 0),
                    client: (a, b) => String(a.client || '').localeCompare(String(b.client || '')),
                };
                list.sort(sorters[sortBy] || sorters.newest);

                const body = document.getElementById('praList');
                if (!list.length) {
                    const msg = source.length ? 'No requests match your search.'
                        : dateActive() ? `No requests ${tab === 'Pending' ? 'submitted' : tab === 'Approved' ? 'approved' : 'returned'} ${datePhrase()}.`
                        : tab === 'Pending' ? 'Nothing waiting for approval.' : tab === 'Approved' ? 'No approved requests yet.' : 'No returned requests.';
                    body.innerHTML = `<tr><td colspan="8" class="px-5 py-14 text-center text-xs text-gray-400"><i class="fa-regular ${tab === 'Pending' && !source.length ? 'fa-circle-check text-green-400' : 'fa-folder-open text-gray-300'} text-2xl block mb-2"></i>${msg}</td></tr>`;
                    document.getElementById('praCards').innerHTML = `<div class="px-4 py-12 text-center text-xs text-gray-400"><i class="fa-regular ${tab === 'Pending' && !source.length ? 'fa-circle-check text-green-400' : 'fa-folder-open text-gray-300'} text-2xl block mb-2"></i>${msg}</div>`;
                } else {
                    // Phone: card bawat request
                    document.getElementById('praCards').innerHTML = list.map(r => {
                        const when = whenOf(r);
                        const days = Math.floor((ageSec(when) ?? 0) / 86400);
                        const fresh = (ageSec(when) ?? 1e9) < 3600;
                        const late = r.status === 'Pending' && when && days >= WAIT_WARN_DAYS;
                        const agoTxt = !when ? '' : late
                            ? `<span class="font-semibold text-red-600"><i class="fa-solid fa-triangle-exclamation mr-0.5"></i>${esc(ago(when))} · still waiting</span>`
                            : `<span class="font-semibold ${fresh ? 'text-green-600' : 'text-gray-500'}">${esc(ago(when))}</span>`;
                        const btn = r.status === 'Pending'
                            ? `<button type="button" data-open="${r.id}" class="w-full inline-flex justify-center items-center gap-1.5 px-3.5 py-2.5 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 shadow-sm"><i class="fa-solid fa-magnifying-glass"></i> Review</button>`
                            : `<button type="button" data-open="${r.id}" class="w-full inline-flex justify-center items-center gap-1.5 px-3 py-2.5 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50"><i class="fa-solid fa-eye"></i> View</button>`;
                        return `
                        <div class="px-3 py-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="font-mono text-[12px] font-semibold text-red-600">${esc(r.req_no)}</p>
                                    <p class="font-semibold text-sm text-gray-900 truncate">${esc(r.client)}</p>
                                    <p class="text-[11px] text-gray-400 truncate">${esc(r.project_scope || '')}</p>
                                </div>
                                <p class="shrink-0 text-right tabular-nums font-bold text-gray-900 text-sm">₱ ${money(r.total_amount)}<span class="block text-[10.5px] font-normal text-gray-400">${r.item_count} item${r.item_count == 1 ? '' : 's'}</span></p>
                            </div>
                            <div class="mt-2 text-[11px]">${idCell(r)}</div>
                            <dl class="mt-2 grid grid-cols-2 gap-2 rounded-lg bg-gray-50 border border-gray-100 px-3 py-2 text-[11px]">
                                <div class="min-w-0"><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Requested by</dt><dd class="text-gray-700 truncate">${esc(r.requested_by || '—')}</dd></div>
                                <div class="min-w-0"><dt class="text-[9.5px] font-semibold uppercase tracking-wider text-gray-400">Needed</dt><dd class="text-gray-700 truncate">${esc(r.date_needed || '—')}</dd></div>
                            </dl>
                            <p class="mt-1.5 text-[11px] text-gray-500">${fmtDate(when)} ${fmtTime(when)}${agoTxt ? ' · ' + agoTxt : ''} · by ${esc(r.prepared_by || '—')}</p>
                            ${r.status === 'Rejected' && r.rejection_remarks ? `<p class="mt-1 text-[11px] text-red-700 bg-red-50 border border-red-100 rounded-md px-2 py-1 line-clamp-2">${esc(r.rejection_remarks)}</p>` : ''}
                            <div class="mt-2.5">${btn}</div>
                        </div>`;
                    }).join('');

                    body.innerHTML = list.map(r => {
                        const when = whenOf(r);
                        const days = Math.floor((ageSec(when) ?? 0) / 86400);
                        const fresh = (ageSec(when) ?? 1e9) < 3600;
                        const late = r.status === 'Pending' && when && days >= WAIT_WARN_DAYS;
                        const agoHtml = !when ? '' : late
                            ? `<p class="text-[11px] font-semibold text-red-600"><i class="fa-solid fa-triangle-exclamation mr-0.5"></i>${esc(ago(when))} · still waiting</p>`
                            : `<p class="text-[11px] font-semibold ${fresh ? 'text-green-600' : 'text-gray-500'}">${esc(ago(when))}</p>`;
                        const action = r.status === 'Pending'
                            ? `<button type="button" data-open="${r.id}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-[11px] font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 shadow-sm"><i class="fa-solid fa-magnifying-glass"></i> Review</button>`
                            : `<button type="button" data-open="${r.id}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50"><i class="fa-solid fa-eye"></i> View</button>`;
                        return `
                        <tr class="hover:bg-amber-50/30 transition-colors">
                            <td class="px-5 py-3.5"><p class="font-mono text-[12px] font-semibold text-red-600 whitespace-nowrap">${esc(r.req_no)}</p><p class="text-[11px] text-gray-400">Needed: ${esc(r.date_needed || '—')}</p></td>
                            <td class="px-5 py-3.5">${idCell(r)}</td>
                            <td class="px-5 py-3.5 max-w-[220px]"><p class="font-semibold text-gray-800 truncate">${esc(r.client)}</p><p class="text-[11px] text-gray-400 truncate">${esc(r.project_scope || '')}</p></td>
                            <td class="px-5 py-3.5"><p class="text-gray-700">${esc(r.requested_by || '—')}</p><p class="text-[11px] text-gray-400">by ${esc(r.prepared_by || '—')}</p></td>
                            <td class="px-5 py-3.5 text-center tabular-nums text-gray-700">${r.item_count}</td>
                            <td class="px-5 py-3.5 text-right tabular-nums font-semibold text-gray-900">₱ ${money(r.total_amount)}</td>
                            <td class="px-5 py-3.5 whitespace-nowrap" title="${esc(fmtDate(when) + ' ' + fmtTime(when))}">
                                <p class="text-gray-700">${fmtDate(when)} <span class="text-[11px] text-gray-400">${fmtTime(when)}</span></p>
                                ${agoHtml}
                                ${r.status === 'Rejected' && r.rejection_remarks ? `<p class="text-[11px] text-gray-400 truncate max-w-[180px]" title="${esc(r.rejection_remarks)}">${esc(r.rejection_remarks)}</p>` : ''}
                            </td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap">${action}</td>
                        </tr>`;
                    }).join('');
                }
                document.getElementById('praFooter').textContent = `Showing ${list.length} of ${source.length} request${source.length === 1 ? '' : 's'}` + (dateActive() ? ` · ${dateLabel()}` : '');
            }

            let loading = false, loadAgain = false;
            async function load() {
                if (loading) { loadAgain = true; return; }
                loading = true;
                try {
                    const res = await fetch(`${AJAX}?action=list`, { cache: 'no-store' });
                    const data = await res.json();
                    if (!data.success) { toast(data.message || 'Failed to load requests.', 'error'); return; }
                    rows = data.rows;
                    render();
                } catch (e) { console.error(e); toast('Connection error while loading.', 'error'); }
                finally {
                    loading = false;
                    if (loadAgain) { loadAgain = false; load(); }
                }
            }

            document.getElementById('praList').addEventListener('click', e => {
                const b = e.target.closest('[data-open]');
                if (b) openReview(+b.dataset.open);
            });
            document.getElementById('praCards').addEventListener('click', e => {
                const b = e.target.closest('[data-open]');
                if (b) openReview(+b.dataset.open);
            });

            // ── review modal ──
            async function openReview(id, quiet = false) {
                const sheet = document.getElementById('praSheet');
                if (!quiet) {
                    sheet.innerHTML = `<p class="text-xs text-gray-400 text-center py-10"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Loading…</p>`;
                    document.getElementById('praNotice').classList.add('hidden');
                    openModal('praModal');
                }
                try {
                    const res = await fetch(`${AJAX}?action=view&id=${id}`, { cache: 'no-store' });
                    const data = await res.json();
                    // realtime refresh: huwag galawin kapag nagsara o lumipat na ng request
                    if (quiet && (document.getElementById('praModal').classList.contains('hidden') || !current || current.id !== id)) return;
                    if (!data.success) { sheet.innerHTML = `<p class="text-xs text-red-500 text-center py-10">${esc(data.message)}</p>`; return; }
                    current = data.pr;
                    PRSheet.render(sheet, current, { base: BASE });
                    PRSheet.history(document.getElementById('praHistory'), current, { base: BASE });

                    const pending = current.status === 'Pending' && NH_CAN_APPROVE;
                    document.getElementById('praApproveBtn').classList.toggle('hidden', !pending);
                    document.getElementById('praRejectBtn').classList.toggle('hidden', !pending);
                    document.getElementById('praModalNo').textContent = current.req_no;
                    document.getElementById('praModalSub').textContent = [current.control_no ? `Control ${current.control_no}` : '', current.bom_numbers ? `BOM ${current.bom_numbers}` : '', current.client, `₱ ${money(current.total_amount)}`].filter(Boolean).join(' · ');

                    const notice = document.getElementById('praNotice');
                    notice.className = 'mx-4 mt-3 rounded-lg px-3 py-2 text-xs';
                    if (current.status === 'Approved') {
                        notice.classList.add('bg-green-50', 'text-green-700', 'border', 'border-green-200');
                        notice.innerHTML = `<i class="fa-solid fa-circle-check mr-1"></i>Approved by <b>${esc(current.approved_by)}</b> on ${fmtDate(current.approved_at)} ${fmtTime(current.approved_at)}`;
                    } else if (current.status === 'Rejected') {
                        notice.classList.add('bg-red-50', 'text-red-700', 'border', 'border-red-200');
                        notice.innerHTML = `<i class="fa-solid fa-rotate-left mr-1"></i>Returned on ${fmtDate(current.rejected_at)}: ${esc(current.rejection_remarks)}`;
                    } else {
                        notice.classList.add('hidden');
                    }
                } catch (e) { console.error(e); sheet.innerHTML = `<p class="text-xs text-red-500 text-center py-10">Connection error.</p>`; }
            }

            document.getElementById('praClose').addEventListener('click', () => closeModal('praModal'));
            document.getElementById('praPrint').addEventListener('click', () => nhPrintSheet('praSheet'));

            // ── approve ──
            document.getElementById('praApproveBtn').addEventListener('click', () => {
                if (!current) return;
                document.getElementById('praApproveNo').textContent = current.req_no;
                document.getElementById('praApproveClient').textContent = current.client;
                document.getElementById('praApproveControl').textContent = current.control_no || '—';
                document.getElementById('praApproveBom').textContent = current.bom_numbers || '—';
                document.getElementById('praApproveTotal').textContent = '₱ ' + money(current.total_amount);
                openModal('praApproveModal');
            });
            document.getElementById('praApproveCancel').addEventListener('click', () => closeModal('praApproveModal'));

            document.getElementById('praApproveConfirm').addEventListener('click', async function () {
                if (!current) return;
                const btn = this;
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Approving…';
                try {
                    const res = await fetch(`${AJAX}?action=approve`, {
                        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ id: current.id })
                    });
                    const data = await res.json();
                    closeModal('praApproveModal');
                    if (!data.success) { toast(data.message || 'Approval failed.', 'error'); load(); return; }
                    toast(data.has_signature ? 'Approved and signed.' : 'Approved. No saved signature was found, so only your name was placed.', data.has_signature ? 'success' : 'error', 6000);
                    await load();
                    openReview(current.id);   // ipakita ang napirmahang form
                } catch (e) { toast('Connection error while approving.', 'error'); }
                finally { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-check mr-1"></i> Approve &amp; Sign'; }
            });

            // ── return ──
            document.getElementById('praRejectBtn').addEventListener('click', () => {
                document.getElementById('praRejectRemarks').value = '';
                document.getElementById('praRejectError').classList.add('hidden');
                openModal('praRejectModal');
                setTimeout(() => document.getElementById('praRejectRemarks').focus(), 50);
            });
            document.getElementById('praRejectCancel').addEventListener('click', () => closeModal('praRejectModal'));
            document.getElementById('praRejectConfirm').addEventListener('click', async function () {
                const remarks = document.getElementById('praRejectRemarks').value.trim();
                if (!remarks) { document.getElementById('praRejectError').classList.remove('hidden'); return; }
                const btn = this;
                btn.disabled = true;
                try {
                    const res = await fetch(`${AJAX}?action=reject`, {
                        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ id: current.id, remarks })
                    });
                    const data = await res.json();
                    if (!data.success) { toast(data.message || 'Could not return.', 'error'); load(); return; }
                    toast('Returned to Purchasing.');
                    closeModal('praRejectModal');
                    closeModal('praModal');
                    load();
                } catch (e) { toast('Connection error.', 'error'); }
                finally { btn.disabled = false; }
            });

            ['praModal', 'praRejectModal', 'praApproveModal'].forEach(id => document.getElementById(id).addEventListener('click', e => { if (e.target.id === id) closeModal(id); }));
            document.addEventListener('keydown', e => {
                if (e.key !== 'Escape') return;
                if (!document.getElementById('praApproveModal').classList.contains('hidden')) closeModal('praApproveModal');
                else if (!document.getElementById('praRejectModal').classList.contains('hidden')) closeModal('praRejectModal');
                else closeModal('praModal');
            });

            setTab('Pending');
            load();

            // ─── Realtime (koneksyon ng sidebar: window.NH_RT) ───
            // Bagong PR ng Purchasing, approve/return ng ibang Superadmin, review/release/paid ng Accounting → kunin agad.
            // Ang listahan ay laging nire-refresh; ang bukas na form ay nire-refresh lang kapag walang bukas na Approve/Return.
            const rtLive = () => !!(window.NH_RT && window.NH_RT.enabled && window.NH_RT.isLive());
            const confirmOpen = () => ['praRejectModal', 'praApproveModal'].some(id => !document.getElementById(id).classList.contains('hidden'));
            let missed = false, pollTimer = null;

            function sync() {
                if (document.hidden) { missed = true; return; }
                load();
                const reviewOpen = !document.getElementById('praModal').classList.contains('hidden');
                if (reviewOpen && current) {
                    if (confirmOpen()) { missed = true; return; }
                    openReview(current.id, true);
                }
                missed = false;
            }
            function startPolling() {
                if (pollTimer) clearInterval(pollTimer);
                // Live → 60s fallback lang; hindi Live → 30s (dating bilis)
                pollTimer = setInterval(() => { if (!document.hidden && !anyOpen()) load(); }, rtLive() ? 60000 : 30000);
            }
            (function hookRealtime() {
                const hook = () => {
                    if (!window.NH_RT || !window.NH_RT.enabled || hookRealtime.done) return;
                    hookRealtime.done = true;
                    let t = null;
                    window.NH_RT.on('*', 'changed', payload => {
                        const type = String(payload?.type || '');
                        if (type && !/^(pr|po_receive|transmittal|crm_po)/i.test(type)) return;   // ibang CRM/budget signal — hindi para dito
                        clearTimeout(t);
                        t = setTimeout(sync, 400);
                    });
                    window.NH_RT.onLive(live => {
                        document.getElementById('praLiveDot').className = 'w-2 h-2 rounded-full ' + (live ? 'bg-green-500 animate-pulse' : 'bg-amber-400');
                        document.getElementById('praLiveText').textContent = live ? 'Live' : 'Auto-refresh every 30s';
                        if (!document.hidden) startPolling();
                        if (live && rows.length) sync();
                    });
                };
                hook();
                if (!hookRealtime.done) document.addEventListener('DOMContentLoaded', hook);
            })();
            setInterval(() => { if (missed && !confirmOpen()) sync(); }, 5000);
            document.addEventListener('visibilitychange', () => {
                if (document.hidden) { if (pollTimer) clearInterval(pollTimer); }
                else { sync(); startPolling(); }
            });
            startPolling();
        })();
    </script>

<style>
    /* ═══ Filter drawer sa phone (kagaya ng CRM Monitoring) · sa sm+ walang epekto (display: contents) ═══ */
    .nh-fd-backdrop { display: none; position: fixed; inset: 0; background: rgba(17, 24, 39, .4); z-index: 9998; }
    @media (max-width: 639px) {
        .nh-fd { position: fixed; top: 0; bottom: 0; left: 0; width: 300px; max-width: 88vw; z-index: 9999; background: #fff;
            display: flex; flex-direction: column; transform: translateX(-105%); transition: transform .22s ease;
            box-shadow: 0 10px 30px rgba(0, 0, 0, .18); }
        .nh-fd.open { transform: none; }
        .nh-fd-backdrop.open { display: block; }
        .nh-fd-body { flex: 1; overflow-y: auto; padding: 14px 16px 18px; display: flex; flex-direction: column; gap: 14px; }
        .nh-fd-body > * { width: 100%; }
        .nh-fd-label { font-size: 11px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #374151; margin-bottom: -8px; }
    }
    @media (min-width: 640px) {
        .nh-fd, .nh-fd-body { display: contents; }
        .nh-fd-only { display: none !important; }
    }

</style>

<script>
    // Filter drawer sa phone — bubuksan ng [data-fd-open="id"]; badge = ilang filter ang naka-on
    (function () {
        const isPhone = () => !window.matchMedia('(min-width: 640px)').matches;
        function setup(d) {
            const bd = document.getElementById(d.id + 'Backdrop');
            const open = () => { d.classList.add('open'); bd && bd.classList.add('open'); document.body.style.overflow = 'hidden'; };
            const close = () => { if (!d.classList.contains('open')) return; d.classList.remove('open'); bd && bd.classList.remove('open'); document.body.style.overflow = ''; };
            d._fdOpen = open;
            bd && bd.addEventListener('click', close);
            d.querySelectorAll('[data-fd-close]').forEach(b => b.addEventListener('click', close));
            document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
            window.addEventListener('resize', () => { if (!isPhone()) close(); });
            const badges = document.querySelectorAll(`[data-fd-open="${d.id}"] [data-fd-badge]`);
            const count = () => {
                let n = 0;
                d.querySelectorAll('input, select').forEach(el => {
                    if (el.type === 'hidden' || el.hasAttribute('data-fd-ignore')) return;
                    if (el.tagName === 'SELECT' ? el.selectedIndex > 0 : (el.type === 'checkbox' ? el.checked : !!el.value)) n++;
                });
                badges.forEach(b => { b.textContent = n; b.classList.toggle('hidden', !n); });
            };
            ['change', 'input'].forEach(ev => d.addEventListener(ev, count));
            d.addEventListener('click', () => setTimeout(count, 0));
            setTimeout(count, 0);
        }
        document.querySelectorAll('.nh-fd').forEach(setup);
        document.querySelectorAll('[data-fd-open]').forEach(btn => btn.addEventListener('click', () => {
            const d = document.getElementById(btn.dataset.fdOpen);
            if (d && d._fdOpen) d._fdOpen();
        }));
        window.nhFdOpen = id => { const d = document.getElementById(id); if (d && d._fdOpen) d._fdOpen(); };
    })();
</script>
</body>

</html>