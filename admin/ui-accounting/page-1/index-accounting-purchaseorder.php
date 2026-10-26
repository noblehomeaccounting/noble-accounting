<?php
// index-accounting-purchaseorder.php  —  Accounting Head: review & sign CEO-approved Purchase Requests

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_ACCOUNTING];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

// Accounting Head lang ang puwede rito
if (($_SESSION['position'] ?? '') !== POSITION_HEAD) {
    http_response_code(403);
    echo 'Only the Accounting Head can access this page.';
    exit;
}

$apoAjaxUrl = BASE_URL . '/accountingpurchaseorderajax';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Order Approval</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/ui-accounting/css/accounting/index-accounting-purchaseorder.css?v=<?= @filemtime(ROOT_PATH . '/admin/ui-accounting/css/accounting/index-accounting-purchaseorder.css') ?: 1 ?>">
</head>

<body class="bg-slate-100">
    <main id="main-content" class="md:ml-56 pt-20 md:pt-5 min-h-screen p-4 md:p-8 transition-all duration-300">

        <div class="max-w-6xl mx-auto">

            <!-- ===================== HEADER ===================== -->
            <div class="flex flex-wrap items-end justify-between gap-3 mb-5">
                <div>
                    <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase mb-0.5">Accounting</p>
                    <h1 class="text-gray-900 text-xl font-semibold">Purchase Request Review</h1>
                    <p class="text-gray-400 text-xs mt-0.5">Purchase requests approved by the CEO. Reviewing signs the form as Accounting Head.</p>
                </div>
                <span class="inline-flex items-center gap-1.5 text-[11px] text-gray-400">
                    <span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span> Auto-refresh every 30s
                </span>
            </div>

            <!-- ===================== STATS ===================== -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
                <div class="bg-white border border-gray-200 rounded-xl px-5 py-4 flex items-center gap-4">
                    <span class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center"><i class="fa-solid fa-inbox"></i></span>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">To Review</p>
                        <p id="apoStatPending" class="text-2xl font-bold text-gray-900 leading-tight tabular-nums">—</p>
                    </div>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl px-5 py-4 flex items-center gap-4">
                    <span class="w-11 h-11 rounded-xl bg-green-50 text-green-600 flex items-center justify-center"><i class="fa-solid fa-file-signature"></i></span>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Reviewed Today</p>
                        <p id="apoStatToday" class="text-2xl font-bold text-gray-900 leading-tight tabular-nums">—</p>
                    </div>
                </div>
                <div class="bg-white border border-gray-200 rounded-xl px-5 py-4 flex items-center gap-4">
                    <span class="w-11 h-11 rounded-xl bg-slate-100 text-slate-600 flex items-center justify-center"><i class="fa-solid fa-peso-sign"></i></span>
                    <div>
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">Amount To Review</p>
                        <p id="apoStatAmount" class="text-2xl font-bold text-gray-900 leading-tight tabular-nums">—</p>
                    </div>
                </div>
            </div>

            <!-- ===================== LIST ===================== -->
            <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                <div class="flex flex-wrap items-center gap-2 px-4 py-3 border-b border-gray-100">
                    <div class="inline-flex items-center gap-1 bg-gray-50 border border-gray-200 rounded-lg p-1">
                        <button type="button" data-tab="pending" class="apo-tab inline-flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-semibold text-gray-500 transition-all">
                            <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span> To Review
                            <span data-count="pending" class="min-w-[20px] px-1.5 py-px rounded-full bg-gray-200 text-gray-600 text-[10px] font-bold text-center">—</span>
                        </button>
                        <button type="button" data-tab="reviewed" class="apo-tab inline-flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-semibold text-gray-500 transition-all">
                            <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Reviewed
                            <span data-count="reviewed" class="min-w-[20px] px-1.5 py-px rounded-full bg-gray-200 text-gray-600 text-[10px] font-bold text-center">—</span>
                        </button>
                    </div>
                    <div class="relative ml-auto w-full sm:w-72">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[11px]"></i>
                        <input type="text" id="apoSearch" placeholder="Search req no., client or requester..."
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
                                <th class="px-5 py-3 text-left" id="apoColDate">CEO Approved</th>
                                <th class="px-5 py-3 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody id="apoList" class="divide-y divide-gray-100">
                            <tr><td colspan="7" class="px-5 py-12 text-center text-gray-400 text-xs"><i class="fa-solid fa-spinner fa-spin mr-1"></i> Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
                <p id="apoFooter" class="px-5 py-2.5 border-t border-gray-100 bg-gray-50/60 text-[11px] text-gray-400"></p>
            </div>
        </div>

        <div id="crmToastContainer" class="fixed bottom-4 right-4 z-[10000] flex flex-col gap-2.5 pointer-events-none w-[360px] max-w-[calc(100vw-2rem)]"></div>
    </main>

    <!-- ===================== REVIEW MODAL ===================== -->
    <div id="apoModal" class="hidden fixed inset-0 z-[9998] items-start justify-center bg-black/50 p-4 overflow-y-auto">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-4xl my-6 overflow-hidden">
            <div class="sticky top-0 z-10 bg-white px-4 py-3 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
                <div class="min-w-0">
                    <p id="apoModalNo" class="font-mono text-xs font-semibold text-red-600"></p>
                    <p id="apoModalSub" class="text-[11px] text-gray-400 truncate"></p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" id="apoPrint" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-semibold text-gray-600 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">
                        <i class="fa-solid fa-print"></i> Print
                    </button>
                    <button type="button" id="apoReviewBtn" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-[11px] font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700 shadow-sm">
                        <i class="fa-solid fa-signature"></i> Review &amp; Sign
                    </button>
                    <button type="button" id="apoClose" class="inline-flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-700 hover:bg-gray-100 shrink-0" aria-label="Close"><i class="fa-solid fa-xmark text-base leading-none"></i></button>
                </div>
            </div>
            <div id="apoNotice" class="hidden mx-4 mt-3 rounded-lg px-3 py-2 text-xs"></div>
            <div class="p-4 md:p-6 overflow-x-auto">
                <div id="apoSheet" class="min-w-[760px]"></div>
            </div>
        </div>
    </div>

    <!-- ===================== CONFIRM MODAL ===================== -->
    <div id="apoConfirmModal" class="hidden fixed inset-0 z-[9999] items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-sm overflow-hidden">
            <div class="p-5">
                <div class="flex items-center gap-2.5 mb-3">
                    <span class="w-9 h-9 rounded-full bg-green-50 text-green-600 flex items-center justify-center"><i class="fa-solid fa-file-signature text-sm"></i></span>
                    <div>
                        <p class="text-sm font-semibold text-gray-800">Review this request?</p>
                        <p id="apoConfirmNo" class="font-mono text-[11px] font-semibold text-red-600"></p>
                    </div>
                </div>
                <div class="rounded-lg bg-gray-50 border border-gray-200 px-3 py-2.5 text-xs text-gray-600 space-y-1">
                    <p><span class="text-gray-400">Client:</span> <span id="apoConfirmClient" class="font-semibold text-gray-800"></span></p>
                    <p><span class="text-gray-400">Total:</span> <span id="apoConfirmTotal" class="font-semibold text-gray-800"></span></p>
                </div>
                <p class="text-[11px] text-gray-500 mt-3 leading-relaxed">
                    <i class="fa-solid fa-signature text-green-600 mr-1"></i>
                    Your name and saved signature will be placed in <b>Reviewed By</b> as Accounting Head. This cannot be undone.
                </p>
            </div>
            <div class="flex justify-end gap-2 px-5 py-3 bg-gray-50 border-t border-gray-100">
                <button type="button" id="apoConfirmCancel" class="px-3.5 py-2 text-[11px] font-semibold text-gray-500 bg-white border border-gray-200 rounded-lg hover:bg-gray-100">Cancel</button>
                <button type="button" id="apoConfirmOk" class="px-3.5 py-2 text-[11px] font-semibold text-white bg-green-600 rounded-lg hover:bg-green-700 disabled:opacity-60">
                    <i class="fa-solid fa-check mr-1"></i> Review &amp; Sign
                </button>
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

    window.PRSheet = { render, SIGN };
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
            const AJAX = <?= json_encode($apoAjaxUrl) ?>;
            const BASE = <?= json_encode(BASE_URL) ?>;

            let rows = [];
            let tab = 'pending';
            let current = null;

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
            const MODALS = ['apoModal', 'apoConfirmModal'];
            const anyOpen = () => MODALS.some(id => !document.getElementById(id).classList.contains('hidden'));

            // ── tabs ──
            function setTab(t) {
                tab = t;
                document.querySelectorAll('.apo-tab').forEach(b => {
                    const on = b.dataset.tab === t;
                    ['bg-white', 'shadow-sm', 'text-amber-700', 'text-green-700'].forEach(c => b.classList.remove(c));
                    if (on) b.classList.add('bg-white', 'shadow-sm', t === 'pending' ? 'text-amber-700' : 'text-green-700');
                    b.classList.toggle('text-gray-500', !on);
                });
                document.getElementById('apoColDate').textContent = t === 'pending' ? 'CEO Approved' : 'Reviewed';
                render();
            }
            document.querySelectorAll('.apo-tab').forEach(b => b.addEventListener('click', () => setTab(b.dataset.tab)));
            document.getElementById('apoSearch').addEventListener('input', render);

            // ── list ──
            function render() {
                const pending = rows.filter(r => !r.is_reviewed);
                const reviewed = rows.filter(r => r.is_reviewed);
                document.querySelector('[data-count="pending"]').textContent = pending.length;
                document.querySelector('[data-count="reviewed"]').textContent = reviewed.length;
                document.getElementById('apoStatPending').textContent = pending.length;
                document.getElementById('apoStatToday').textContent = rows.filter(r => r.reviewed_today).length;
                document.getElementById('apoStatAmount').textContent = '₱ ' + money(pending.reduce((a, r) => a + Number(r.total_amount || 0), 0));

                const q = document.getElementById('apoSearch').value.trim().toLowerCase();
                const source = tab === 'pending' ? pending : reviewed;
                const list = source.filter(r => !q || `${r.req_no} ${r.client} ${r.requested_by ?? ''} ${r.prepared_by ?? ''}`.toLowerCase().includes(q));

                const body = document.getElementById('apoList');
                if (!list.length) {
                    const msg = source.length ? 'No requests match your search.' : tab === 'pending' ? 'Nothing to review. CEO-approved requests will appear here.' : 'No reviewed requests yet.';
                    body.innerHTML = `<tr><td colspan="7" class="px-5 py-14 text-center text-xs text-gray-400"><i class="fa-regular ${tab === 'pending' && !source.length ? 'fa-circle-check text-green-400' : 'fa-folder-open text-gray-300'} text-2xl block mb-2"></i>${msg}</td></tr>`;
                } else {
                    body.innerHTML = list.map(r => {
                        const when = r.is_reviewed ? r.reviewed_at : r.approved_at;
                        const who  = r.is_reviewed ? r.reviewed_by : r.approved_by;
                        const action = r.is_reviewed
                            ? `<button type="button" data-open="${r.id}" class="inline-flex items-center gap-1.5 px-3 py-1.5 text-[11px] font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50"><i class="fa-solid fa-eye"></i> View</button>`
                            : `<button type="button" data-open="${r.id}" class="inline-flex items-center gap-1.5 px-3.5 py-1.5 text-[11px] font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 shadow-sm"><i class="fa-solid fa-magnifying-glass"></i> Review</button>`;
                        return `
                        <tr class="hover:bg-amber-50/30 transition-colors">
                            <td class="px-5 py-3.5"><p class="font-mono text-[12px] font-semibold text-red-600">${esc(r.req_no)}</p><p class="text-[11px] text-gray-400">Needed: ${esc(r.date_needed || '—')}</p></td>
                            <td class="px-5 py-3.5 max-w-[220px]"><p class="font-semibold text-gray-800 truncate">${esc(r.client)}</p><p class="text-[11px] text-gray-400 truncate">${esc(r.project_scope || '')}</p></td>
                            <td class="px-5 py-3.5"><p class="text-gray-700">${esc(r.requested_by || '—')}</p><p class="text-[11px] text-gray-400">by ${esc(r.prepared_by || '—')}</p></td>
                            <td class="px-5 py-3.5 text-center tabular-nums text-gray-700">${r.item_count}</td>
                            <td class="px-5 py-3.5 text-right tabular-nums font-semibold text-gray-900">₱ ${money(r.total_amount)}</td>
                            <td class="px-5 py-3.5 whitespace-nowrap"><p class="text-gray-700">${fmtDate(when)}</p><p class="text-[11px] text-gray-400">${fmtTime(when)} · ${esc(who || '—')}</p></td>
                            <td class="px-5 py-3.5 text-right whitespace-nowrap">${action}</td>
                        </tr>`;
                    }).join('');
                }
                document.getElementById('apoFooter').textContent = `Showing ${list.length} of ${source.length} request${source.length === 1 ? '' : 's'}`;
            }

            async function load() {
                try {
                    const res = await fetch(`${AJAX}?action=list`, { cache: 'no-store' });
                    const data = await res.json();
                    if (!data.success) { toast(data.message || 'Failed to load requests.', 'error'); return; }
                    rows = data.rows;
                    render();
                } catch (e) { console.error(e); toast('Connection error while loading.', 'error'); }
            }

            document.getElementById('apoList').addEventListener('click', e => {
                const b = e.target.closest('[data-open]');
                if (b) openReview(+b.dataset.open);
            });

            // ── review modal ──
            async function openReview(id) {
                const sheet = document.getElementById('apoSheet');
                sheet.innerHTML = `<p class="text-xs text-gray-400 text-center py-10"><i class="fa-solid fa-spinner fa-spin mr-1"></i>Loading…</p>`;
                document.getElementById('apoNotice').classList.add('hidden');
                openModal('apoModal');
                try {
                    const res = await fetch(`${AJAX}?action=view&id=${id}`, { cache: 'no-store' });
                    const data = await res.json();
                    if (!data.success) { sheet.innerHTML = `<p class="text-xs text-red-500 text-center py-10">${esc(data.message)}</p>`; return; }
                    current = data.pr;
                    PRSheet.render(sheet, current, { base: BASE });

                    const reviewed = !!current.reviewed_at;
                    document.getElementById('apoReviewBtn').classList.toggle('hidden', reviewed);
                    document.getElementById('apoModalNo').textContent = current.req_no;
                    document.getElementById('apoModalSub').textContent = `${current.client} · ₱ ${money(current.total_amount)}`;

                    const notice = document.getElementById('apoNotice');
                    notice.className = 'mx-4 mt-3 rounded-lg px-3 py-2 text-xs border';
                    if (reviewed) {
                        notice.classList.add('bg-green-50', 'text-green-700', 'border-green-200');
                        notice.innerHTML = `<i class="fa-solid fa-circle-check mr-1"></i>Reviewed by <b>${esc(current.reviewed_by)}</b> on ${fmtDate(current.reviewed_at)} ${fmtTime(current.reviewed_at)}`;
                    } else {
                        notice.classList.add('bg-amber-50', 'text-amber-800', 'border-amber-200');
                        notice.innerHTML = `<i class="fa-solid fa-circle-info mr-1"></i>Approved by <b>${esc(current.approved_by)}</b> (CEO) on ${fmtDate(current.approved_at)}. Check the details, then Review &amp; Sign.`;
                    }
                } catch (e) { console.error(e); sheet.innerHTML = `<p class="text-xs text-red-500 text-center py-10">Connection error.</p>`; }
            }

            document.getElementById('apoClose').addEventListener('click', () => closeModal('apoModal'));
            document.getElementById('apoPrint').addEventListener('click', () => nhPrintSheet('apoSheet'));

            // ── review & sign ──
            document.getElementById('apoReviewBtn').addEventListener('click', () => {
                if (!current) return;
                document.getElementById('apoConfirmNo').textContent = current.req_no;
                document.getElementById('apoConfirmClient').textContent = current.client;
                document.getElementById('apoConfirmTotal').textContent = '₱ ' + money(current.total_amount);
                openModal('apoConfirmModal');
            });
            document.getElementById('apoConfirmCancel').addEventListener('click', () => closeModal('apoConfirmModal'));
            document.getElementById('apoConfirmOk').addEventListener('click', async function () {
                if (!current) return;
                const btn = this;
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-1"></i> Signing…';
                try {
                    const res = await fetch(`${AJAX}?action=review`, {
                        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: new URLSearchParams({ id: current.id })
                    });
                    const data = await res.json();
                    closeModal('apoConfirmModal');
                    if (!data.success) { toast(data.message || 'Could not review.', 'error'); load(); return; }
                    toast(data.has_signature ? 'Reviewed and signed.' : 'Reviewed. No saved signature was found, so only your name was placed.', data.has_signature ? 'success' : 'error', 6000);
                    await load();
                    openReview(current.id);
                } catch (e) { toast('Connection error while signing.', 'error'); }
                finally { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-check mr-1"></i> Review &amp; Sign'; }
            });

            MODALS.forEach(id => document.getElementById(id).addEventListener('click', e => { if (e.target.id === id) closeModal(id); }));
            document.addEventListener('keydown', e => {
                if (e.key !== 'Escape') return;
                if (!document.getElementById('apoConfirmModal').classList.contains('hidden')) closeModal('apoConfirmModal');
                else closeModal('apoModal');
            });

            setTab('pending');
            load();
            setInterval(() => { if (!document.hidden && !anyOpen()) load(); }, 30000);
        })();
    </script>
</body>

</html>