<?php
// PurchaseOrder.php  —  Superadmin (CEO): approval ng Purchase Requests

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SUPERADMIN];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$praAjaxUrl = BASE_URL . '/purchaseorderapprovalajax';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
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
        class="md:ml-56 min-h-screen pt-20 md:pt-8 p-4 md:p-8 overflow-x-hidden transition-all duration-300">

        <div class="max-w-6xl mx-auto">

            <!-- ===================== HEADER ===================== -->
            <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
                <div>
                    <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase mb-0.5">Purchasing</p>
                    <h1 class="text-gray-900 text-xl font-semibold">Purchase Request Approval</h1>
                    <p class="text-gray-400 text-xs mt-0.5">Review purchase requests from Purchasing. Approving signs the form as CEO.</p>
                </div>
                <span class="inline-flex items-center gap-1.5 text-[11px] text-gray-500 bg-white border border-gray-200 rounded-full px-3 py-1.5">
                    <span id="praLiveDot" class="w-2 h-2 rounded-full bg-amber-400"></span>
                    <span id="praLiveText">Auto-refresh every 30s</span>
                </span>
            </div>

            <!-- ===================== STATS ===================== -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
                <div class="bg-white border border-gray-200 rounded-xl px-5 py-4 flex items-center gap-4">
                    <span class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center"><i class="fa-solid fa-hourglass-half"></i></span>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">For Approval</p>
                        <p id="praStatPending" class="text-2xl font-bold text-gray-900 leading-tight tabular-nums">—</p>
                    </div>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl px-5 py-4 flex items-center gap-4">
                    <span class="w-11 h-11 rounded-xl bg-green-50 text-green-600 flex items-center justify-center"><i class="fa-solid fa-file-signature"></i></span>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Approved Today</p>
                        <p id="praStatToday" class="text-2xl font-bold text-gray-900 leading-tight tabular-nums">—</p>
                    </div>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl px-5 py-4 flex items-center gap-4">
                    <span class="w-11 h-11 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center"><i class="fa-solid fa-peso-sign"></i></span>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Pending Amount</p>
                        <p id="praStatAmount" class="text-2xl font-bold text-gray-900 leading-tight tabular-nums">—</p>
                    </div>
                </div>
            </div>

            <!-- ===================== LIST ===================== -->
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <div class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-gray-100">
                    <div class="inline-flex items-center gap-1 bg-gray-50 border border-gray-200 rounded-lg p-1">
                        <?php foreach (['Pending' => ['For Approval', 'bg-amber-500'], 'Approved' => ['Approved', 'bg-green-500'], 'Rejected' => ['Returned', 'bg-red-500']] as $k => [$lbl, $dot]): ?>
                            <button type="button" data-tab="<?= $k ?>"
                                class="pra-tab inline-flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-semibold text-gray-500 transition-all">
                                <span class="w-1.5 h-1.5 rounded-full <?= $dot ?>"></span> <?= $lbl ?>
                                <span data-count="<?= $k ?>" class="min-w-[20px] px-1.5 py-px rounded-full bg-gray-200 text-gray-600 text-[10px] font-bold text-center">—</span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="relative ml-auto w-full sm:w-72">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[11px]"></i>
                        <input type="text" id="praSearch" placeholder="Search req no., client or requester..."
                            class="w-full bg-white border border-gray-200 rounded-lg pl-8 pr-3 py-2 text-xs outline-none focus:border-amber-600 transition-colors">
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm min-w-[880px]">
                        <thead class="bg-gray-50 text-[10.5px] font-semibold uppercase tracking-wider text-gray-500">
                            <tr class="border-b border-gray-200">
                                <th class="px-5 py-3 text-left">Req No.</th>
                                <th class="px-5 py-3 text-left">Client</th>
                                <th class="px-5 py-3 text-left">Requested / Prepared</th>
                                <th class="px-5 py-3 text-center">Items</th>
                                <th class="px-5 py-3 text-right">Total</th>
                                <th class="px-5 py-3 text-left" id="praColDate">Submitted</th>
                                <th class="px-5 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody id="praList" class="divide-y divide-gray-100">
                            <tr><td colspan="7" class="px-5 py-12 text-center text-gray-400 text-xs"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
                <p id="praFooter" class="px-5 py-2.5 border-t border-gray-100 bg-gray-50/60 text-[11px] text-gray-400"></p>
            </div>
        </div>

        <div id="crmToastContainer" class="fixed bottom-4 right-4 z-[10000] flex flex-col gap-2.5 pointer-events-none w-[360px] max-w-[calc(100vw-2rem)]"></div>
    </main>

    <!-- ===================== REVIEW MODAL (form + actions) ===================== -->
    <div id="praModal" class="hidden fixed inset-0 z-[9998] items-start justify-center bg-black/50 p-4 overflow-y-auto">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-6xl my-6 overflow-hidden">
            <div class="sticky top-0 z-10 bg-white px-4 py-3 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                <div class="min-w-0">
                    <p id="praModalNo" class="font-mono text-xs font-semibold text-red-600"></p>
                    <p id="praModalSub" class="text-[11px] text-gray-400 truncate"></p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" id="praPrint" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">
                        <i class="fa-solid fa-print"></i> Print
                    </button>
                    <button type="button" id="praRejectBtn" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-semibold text-red-600 bg-white border border-red-200 rounded-lg hover:bg-red-50">
                        <i class="fa-solid fa-rotate-left"></i> Return
                    </button>
                    <button type="button" id="praApproveBtn" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-[11px] font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700 shadow-sm disabled:opacity-60">
                        <i class="fa-solid fa-signature"></i> Approve &amp; Sign
                    </button>
                    <button type="button" id="praClose" class="ml-1 text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
                </div>
            </div>
            <div id="praNotice" class="hidden mx-4 mt-3 rounded-lg px-3 py-2 text-xs"></div>
            <div class="p-4 md:p-6 flex flex-col xl:flex-row gap-5">
                <div class="flex-1 min-w-0 overflow-x-auto"><div id="praSheet" class="min-w-[760px]"></div></div>
                <aside id="praHistory" class="xl:w-72 shrink-0"></aside>
            </div>
        </div>
    </div>

    <!-- ===================== APPROVE CONFIRM MODAL ===================== -->
    <div id="praApproveModal" class="hidden fixed inset-0 z-[9999] items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm overflow-hidden">
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
                    <p><span class="text-gray-400">Total:</span> <span id="praApproveTotal" class="font-semibold text-gray-800"></span></p>
                </div>
                <p class="text-[11px] text-gray-500 mt-3 leading-relaxed">
                    <i class="fa-solid fa-signature text-green-600 mr-1"></i>
                    Your name and saved signature will be placed in <b>Approved By</b> as CEO. This cannot be undone.
                </p>
            </div>
            <div class="flex justify-end gap-2 px-5 py-3 bg-gray-50 border-t border-gray-100">
                <button type="button" id="praApproveCancel" class="px-3.5 py-2 text-[11px] font-semibold text-gray-500 bg-white border border-gray-200 rounded-lg hover:bg-gray-100">Cancel</button>
                <button type="button" id="praApproveConfirm" class="px-3.5 py-2 text-[11px] font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-60">
                    <i class="fa-solid fa-check mr-1"></i> Approve &amp; Sign
                </button>
            </div>
        </div>
    </div>

    <!-- ===================== RETURN REASON MODAL ===================== -->
    <div id="praRejectModal" class="hidden fixed inset-0 z-[9999] items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm p-5">
            <div class="flex items-center gap-2.5 mb-1">
                <span class="w-8 h-8 rounded-full bg-red-50 text-red-500 flex items-center justify-center"><i class="fa-solid fa-rotate-left text-xs"></i></span>
                <p class="text-sm font-semibold text-gray-800">Return to Purchasing</p>
            </div>
            <p class="text-xs text-gray-400 mb-3">Tell Purchasing what to fix.</p>
            <textarea id="praRejectRemarks" rows="3" placeholder="e.g. Please attach supplier quotation"
                class="w-full text-xs border border-gray-200 rounded-lg p-2.5 focus:outline-none focus:ring-1 focus:ring-amber-400 resize-none"></textarea>
            <p id="praRejectError" class="hidden text-[11px] text-red-500 mt-1">A reason is required.</p>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" id="praRejectCancel" class="px-3.5 py-2 text-[11px] font-semibold text-gray-500 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100">Cancel</button>
                <button type="button" id="praRejectConfirm" class="px-3.5 py-2 text-[11px] font-semibold text-white bg-red-600 rounded-lg hover:bg-red-700 disabled:opacity-60">Confirm Return</button>
            </div>
        </div>
    </div>

    <script>
// PRSheet — iisang itsura ng naka-submit na Purchase Request Form
// Ginagamit ng index-purchaserequest.php (PO) at PurchaseOrder.php (Superadmin).
// PRSheet.render(element, data, { base: BASE_URL })
(function () {
    const SIGN = {
        noted:    { name: '',                                   title: 'SITE OPERATIONS MANAGER' },
        approved: { name: 'KEN YANG',                           title: 'CEO' },
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
                ? { label: 'Returned', done: true, bad: true, at: d.rejected_at, who: '', role: 'CEO', desc: 'Returned by the CEO', note: d.rejection_remarks, wait: '' }
                : { label: 'Approved', done: d.status === 'Approved', at: d.approved_at, who: d.approved_by, role: d.approved_by_title || 'CEO', sig: d.approved_by_signature_path, desc: 'Approved by', wait: 'Waiting for CEO approval' },
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

            const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
            const money = n => (Number(n) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const toDate = v => v ? new Date(String(v).replace(' ', 'T')) : null;
            const fmtDate = v => { const d = toDate(v); return d && !isNaN(d) ? d.toLocaleDateString('en-PH', { month: 'short', day: 'numeric', year: 'numeric' }) : '—'; };
            const fmtTime = v => { const d = toDate(v); return d && !isNaN(d) ? d.toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' }) : ''; };

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
                render();
            }
            document.querySelectorAll('.pra-tab').forEach(b => b.addEventListener('click', () => setTab(b.dataset.tab)));
            document.getElementById('praSearch').addEventListener('input', render);

            // ── list ──
            function render() {
                const by = s => rows.filter(r => r.status === s);
                ['Pending', 'Approved', 'Rejected'].forEach(s => document.querySelector(`[data-count="${s}"]`).textContent = by(s).length);
                document.getElementById('praStatPending').textContent = by('Pending').length;
                document.getElementById('praStatToday').textContent = rows.filter(r => r.approved_today).length;
                document.getElementById('praStatAmount').textContent = '₱ ' + money(by('Pending').reduce((a, r) => a + Number(r.total_amount || 0), 0));

                const q = document.getElementById('praSearch').value.trim().toLowerCase();
                const source = by(tab);
                const list = source.filter(r => !q || `${r.req_no} ${r.client} ${r.requested_by ?? ''} ${r.prepared_by ?? ''}`.toLowerCase().includes(q));

                const body = document.getElementById('praList');
                if (!list.length) {
                    const msg = source.length ? 'No requests match your search.'
                        : tab === 'Pending' ? 'Nothing waiting for approval.' : tab === 'Approved' ? 'No approved requests yet.' : 'No returned requests.';
                    body.innerHTML = `<tr><td colspan="7" class="px-5 py-14 text-center text-xs text-gray-400"><i class="fa-regular ${tab === 'Pending' && !source.length ? 'fa-circle-check text-green-400' : 'fa-folder-open text-gray-300'} text-2xl block mb-2"></i>${msg}</td></tr>`;
                } else {
                    body.innerHTML = list.map(r => {
                        const when = r.status === 'Pending' ? r.created_at : r.status === 'Approved' ? r.approved_at : r.rejected_at;
                        const action = r.status === 'Pending'
                            ? `<button type="button" data-open="${r.id}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-[11px] font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 shadow-sm"><i class="fa-solid fa-magnifying-glass"></i> Review</button>`
                            : `<button type="button" data-open="${r.id}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50"><i class="fa-solid fa-eye"></i> View</button>`;
                        return `
                        <tr class="hover:bg-amber-50/30 transition-colors">
                            <td class="px-5 py-3.5"><p class="font-mono text-[12px] font-semibold text-red-600">${esc(r.req_no)}</p><p class="text-[11px] text-gray-400">Needed: ${esc(r.date_needed || '—')}</p></td>
                            <td class="px-5 py-3.5 max-w-[220px]"><p class="font-semibold text-gray-800 truncate">${esc(r.client)}</p><p class="text-[11px] text-gray-400 truncate">${esc(r.project_scope || '')}</p></td>
                            <td class="px-5 py-3.5"><p class="text-gray-700">${esc(r.requested_by || '—')}</p><p class="text-[11px] text-gray-400">by ${esc(r.prepared_by || '—')}</p></td>
                            <td class="px-5 py-3.5 text-center tabular-nums text-gray-700">${r.item_count}</td>
                            <td class="px-5 py-3.5 text-right tabular-nums font-semibold text-gray-900">₱ ${money(r.total_amount)}</td>
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <p class="text-gray-700">${fmtDate(when)}</p>
                                <p class="text-[11px] text-gray-400 truncate max-w-[180px]" title="${esc(r.rejection_remarks || '')}">${r.status === 'Rejected' ? esc(r.rejection_remarks || '') : fmtTime(when)}</p>
                            </td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap">${action}</td>
                        </tr>`;
                    }).join('');
                }
                document.getElementById('praFooter').textContent = `Showing ${list.length} of ${source.length} request${source.length === 1 ? '' : 's'}`;
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

                    const pending = current.status === 'Pending';
                    document.getElementById('praApproveBtn').classList.toggle('hidden', !pending);
                    document.getElementById('praRejectBtn').classList.toggle('hidden', !pending);
                    document.getElementById('praModalNo').textContent = current.req_no;
                    document.getElementById('praModalSub').textContent = `${current.client} · ₱ ${money(current.total_amount)}`;

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
</body>

</html>