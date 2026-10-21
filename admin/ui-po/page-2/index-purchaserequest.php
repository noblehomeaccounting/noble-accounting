<?php
// index-purchaserequest.php  —  Purchase Request Form (hiwalay sa BOM)

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PO];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$prAjaxUrl = BASE_URL . '/purchaserequestajax';

// "Prepared By" = naka-login na Purchasing (pangalan + pirma)
$me = ['name' => $_SESSION['username'] ?? '', 'title' => 'Purchasing', 'sig' => null];
$uid = intval($_SESSION['account_id'] ?? 0);
if ($uid > 0) {
    $st = $conn->prepare("SELECT name, active_signature_id FROM noblerole WHERE id = ? LIMIT 1");
    $st->bind_param('i', $uid);
    $st->execute();
    $u = $st->get_result()->fetch_assoc();
    $st->close();
    if ($u) {
        $me['name'] = $u['name'];
        $sq = !empty($u['active_signature_id'])
            ? $conn->prepare("SELECT path FROM noblesignature WHERE id = ? AND user_id = ? LIMIT 1")
            : $conn->prepare("SELECT path FROM noblesignature WHERE user_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1");
        if (!empty($u['active_signature_id'])) $sq->bind_param('ii', $u['active_signature_id'], $uid);
        else $sq->bind_param('i', $uid);
        $sq->execute();
        $r = $sq->get_result()->fetch_assoc();
        $sq->close();
        if ($r) $me['sig'] = $r['path'];
    }
}

// "Requested by" = ang gumawa ng BOM (galing sa "Next: Purchase Request" ?bom=ID)
$requester = null;
$bomParam = intval($_GET['bom'] ?? 0);
if ($bomParam > 0) {
    $bq = $conn->prepare("SELECT id, bom_number, client_name, requested_by, requested_by_title, requested_by_signature_path
                          FROM noblecrm_bom WHERE id = ? AND received_at IS NOT NULL LIMIT 1");
    $bq->bind_param('i', $bomParam);
    $bq->execute();
    $bRow = $bq->get_result()->fetch_assoc();
    $bq->close();
    if ($bRow) {
        $requester = [
            'bom_id'     => (int) $bRow['id'],
            'bom_number' => $bRow['bom_number'],
            'client'     => $bRow['client_name'],   // client ng BOM = client ng Purchase Request (hindi na tina-type)
            'name'       => $bRow['requested_by'],
            'sig'        => $bRow['requested_by_signature_path'],
            'paid_pr'    => null,
        ];
        // Kapag PAID na ang Purchase Request ng BOM na ito, bawal nang gumawa ulit
        $pq = $conn->prepare("SELECT id, req_no FROM noblepurchaserequest WHERE bom_id = ? AND paid_at IS NOT NULL ORDER BY id DESC LIMIT 1");
        $pq->bind_param('i', $bomParam);
        $pq->execute();
        $paid = $pq->get_result()->fetch_assoc();
        $pq->close();
        if ($paid) $requester['paid_pr'] = ['id' => (int) $paid['id'], 'req_no' => $paid['req_no']];
    }
}

// Mga nakapirming pumipirma sa form (palitan dito kung magbago)
$signatories = [
    'noted'    => ['name' => '',                                    'title' => 'SITE OPERATIONS MANAGER'],
    'approved' => ['name' => 'KEN YANG',                            'title' => 'CEO'],
    'reviewed' => ['name' => 'MARY GRACE RIVERA',                   'title' => 'ACCOUNTING HEAD'],
    'released' => ['name' => 'JOLINA JEAN EPA/JAYA JEAN LIBRANDO',  'title' => 'ACCOUNTING STAFF'],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Request Form</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>

    <style>
        /* ── Papel na form ── */
        .pr-sheet { background: #fff; color: #111; font-family: Arial, Helvetica, sans-serif; font-size: 11px; }
        .pr-sheet table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .pr-sheet td, .pr-sheet th { border: 1px solid #333; padding: 4px 6px; vertical-align: middle; }
        .pr-sheet table.nb, .pr-sheet table.nb > tbody > tr > td, .pr-sheet table.nb > tr > td { border: none; }
        .pr-sheet td { overflow-wrap: anywhere; }
        .pr-mono { font-family: 'Courier New', Courier, monospace; }
        .pr-bar { background: #111; color: #fff; text-align: center; font-weight: 700; letter-spacing: .03em; }
        .pr-red { color: #d00; }
        .pr-hint { font-family: 'Courier New', Courier, monospace; font-style: italic; color: #d00; font-size: 9px; text-align: center; }
        .pr-line { border-bottom: 1px solid #111; }
        .pr-sheet input, .pr-sheet textarea {
            width: 100%; background: #fff8eb; border: 1px dashed #f0b45a; border-radius: 3px;
            padding: 3px 5px; font: inherit; outline: none;
        }
        .pr-sheet input:focus, .pr-sheet textarea:focus { border-style: solid; border-color: #b45309; background: #fff; }
        /* galing sa BOM — hindi na mababago */
        .pr-sheet input[readonly] { background: #f3f4f6; border: 1px solid #e5e7eb; color: #111; font-weight: 700; cursor: not-allowed; }
        .pr-sig { position: relative; height: 54px; }
        .pr-sig img { position: absolute; left: 50%; bottom: 6px; transform: translateX(-50%); height: 52px; width: auto; max-width: 90%; object-fit: contain; z-index: 2; pointer-events: none; }
        .pr-sig .nm { position: absolute; left: 0; right: 0; bottom: 0; text-align: center; font-weight: 700; z-index: 1; }

        @media print {
            @page { size: A4 portrait; margin: 10mm; }
            body { background: #fff !important; }
            aside, header, nav, #sidebar, #mobile-drawer, #mobile-overlay, #notif-panel, #notif-overlay, .no-print { display: none !important; }
            #main-content { margin: 0 !important; padding: 0 !important; }
            .pr-print-area { box-shadow: none !important; border: none !important; padding: 0 !important; overflow: visible !important; }
            .pr-sheet input, .pr-sheet textarea { border: none; background: transparent; }
        }
    </style>
</head>

<body class="bg-slate-100">
    <main id="main-content" class="md:ml-56 pt-20 md:pt-5 min-h-screen p-4 md:p-8 transition-all duration-300">
        <div class="max-w-5xl mx-auto">

            <!-- ===================== HEADER ===================== -->
            <div class="no-print flex flex-wrap items-end justify-between gap-3 mb-4">
                <div>
                    <a href="<?= BASE_URL ?>/purchaseorder" onclick="if (history.length > 1) { history.back(); return false; }" class="inline-flex items-center gap-1 text-[11px] font-semibold text-gray-400 hover:text-amber-700 mb-1">
                        <i class="fa-solid fa-chevron-left text-[9px]"></i> Purchase Orders
                    </a>
                    <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase">Purchasing</p>
                    <h1 class="text-gray-900 text-xl font-semibold">Purchase Request Form</h1>
                </div>
                <div class="inline-flex items-center gap-1 bg-white border border-gray-200 rounded-lg p-1">
                    <button type="button" data-tab="new" class="pr-tab inline-flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-semibold text-gray-500">
                        <i class="fa-solid fa-plus text-[10px]"></i> New Request
                    </button>
                    <button type="button" data-tab="list" class="pr-tab inline-flex items-center gap-2 px-3 py-1.5 rounded-md text-xs font-semibold text-gray-500">
                        <i class="fa-solid fa-list text-[10px]"></i> Submitted
                        <span id="prCount" class="min-w-[20px] px-1.5 rounded-full bg-gray-200 text-gray-600 text-[10px] font-bold text-center">—</span>
                    </button>
                </div>
            </div>

            <!-- ===================== NEW / VIEW (papel) ===================== -->
            <section id="prPanelSheet">
                <div class="no-print flex flex-wrap items-center justify-between gap-2 mb-3">
                    <p id="prSheetHint" class="text-xs text-gray-500"></p>
                    <div class="flex items-center gap-2">
                        <button type="button" id="prClearDraft" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-gray-500 bg-white border border-gray-200 rounded-lg hover:bg-gray-50" title="Erase everything typed in this form">
                            <i class="fa-solid fa-eraser"></i> Clear
                        </button>
                        <button type="button" id="prAddRow" class="inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-amber-700 bg-white border border-amber-200 rounded-lg hover:bg-amber-50">
                            <i class="fa-solid fa-plus"></i> Add Item
                        </button>
                        <button type="button" id="prPrint" class="hidden inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">
                            <i class="fa-solid fa-print"></i> Print / Save PDF
                        </button>
                        <button type="button" id="prNewBtn" class="hidden inline-flex items-center gap-1.5 px-3 py-2 text-xs font-semibold text-gray-700 bg-white border border-gray-200 rounded-lg hover:bg-gray-50">
                            <i class="fa-solid fa-plus"></i> New Request
                        </button>
                        <button type="button" id="prSubmit" class="inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 disabled:opacity-60 shadow-sm">
                            <i class="fa-solid fa-paper-plane"></i> Submit Request
                        </button>
                    </div>
                </div>

                <!-- Walang BOM: hindi puwedeng gumawa ng bagong request dito -->
                <div id="prNoBom" class="hidden no-print bg-white border border-gray-200 rounded-xl px-6 py-14 text-center">
                    <span class="mx-auto w-12 h-12 rounded-full bg-amber-50 text-amber-700 flex items-center justify-center mb-3"><i class="fa-solid fa-lock"></i></span>
                    <p class="text-sm font-semibold text-gray-800">A purchase request can only be made from a received BOM</p>
                    <p class="text-xs text-gray-500 mt-1">Go to Purchase Orders and click <b>Next: Purchase Request</b> on a received BOM that isn't paid yet.</p>
                    <a href="<?= BASE_URL ?>/crmpurchaseorder" class="mt-4 inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold text-white bg-amber-700 rounded-lg hover:bg-amber-800 shadow-sm">
                        <i class="fa-solid fa-arrow-left text-[10px]"></i> Go to Purchase Orders
                    </a>
                </div>

                <div id="prPaper" class="pr-print-area bg-white border border-gray-200 shadow-sm rounded-lg p-4 md:p-6 overflow-x-auto">
                    <div id="prSheet" class="pr-sheet min-w-[760px]"></div>
                </div>
            </section>

            <!-- ===================== LIST ===================== -->
            <section id="prPanelList" class="hidden no-print">
                <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
                    <div class="flex items-center gap-2 px-4 py-3 border-b border-gray-100">
                        <div class="relative w-full sm:w-72">
                            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-300 text-[11px]"></i>
                            <input type="text" id="prSearch" placeholder="Search req no. or client..."
                                class="w-full border border-gray-200 rounded-lg pl-8 pr-3 py-2 text-xs outline-none focus:border-amber-600">
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm min-w-[760px]">
                            <thead class="bg-gray-50 text-[10.5px] font-semibold uppercase tracking-wider text-gray-500">
                                <tr class="border-b border-gray-200">
                                    <th class="px-5 py-3 text-left">Req No.</th>
                                    <th class="px-5 py-3 text-left">Client</th>
                                    <th class="px-5 py-3 text-left">Request Date</th>
                                    <th class="px-5 py-3 text-center">Items</th>
                                    <th class="px-5 py-3 text-right">Total</th>
                                    <th class="px-5 py-3 text-left">Status</th>
                                    <th class="px-5 py-3"></th>
                                </tr>
                            </thead>
                            <tbody id="prList" class="divide-y divide-gray-100"></tbody>
                        </table>
                    </div>
                </div>
            </section>
        </div>

        <div id="crmToastContainer" class="no-print fixed bottom-4 right-4 z-[9999] flex flex-col gap-2.5 pointer-events-none w-[360px] max-w-[calc(100vw-2rem)]"></div>
    </main>

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
        (function () {
            const AJAX = <?= json_encode($prAjaxUrl) ?>;
            const BASE = <?= json_encode(BASE_URL) ?>;
            const LOGO = BASE + '/icon/logo.webp';
            const ME   = <?= json_encode($me) ?>;
            const SIGN = <?= json_encode($signatories) ?>;
            const REQUESTER = <?= json_encode($requester) ?>;   // null kapag hindi galing sa BOM

            let mode = 'edit';      // 'edit' = bagong form | 'view' = naka-save na
            let rowSeq = 0;
            let listRows = [];

            const esc = s => { const d = document.createElement('div'); d.textContent = s ?? ''; return d.innerHTML; };
            const stageBadge = r => {
                const b = (cls, dot, label) => `<span class="inline-flex items-center gap-1.5 text-xs font-semibold ${cls}"><span class="w-1.5 h-1.5 rounded-full ${dot}"></span>${label}</span>`;
                if (r.status === 'Rejected') return b('text-red-600', 'bg-red-500', 'Returned');
                if (r.status !== 'Approved') return b('text-amber-700', 'bg-amber-500', 'For approval');
                if (r.paid_at) return b('text-green-700', 'bg-green-500', 'Paid');
                if (r.released_at) return b('text-sky-700', 'bg-sky-500', 'Waiting to be Paid');
                if (r.staff_received_at) return b('text-indigo-700', 'bg-indigo-500', 'For release');
                if (r.reviewed_at) return b('text-indigo-700', 'bg-indigo-500', 'Reviewed');
                return b('text-green-700', 'bg-green-500', 'Approved');
            };
            const money = n => (Number(n) || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const qtyFmt = q => { const n = parseFloat(q); return isNaN(n) ? '' : n.toFixed(2).replace(/\.?0+$/, ''); };
            const todayISO = () => { const d = new Date(); return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`; };
            // Preview ng Req No habang nagta-type: <taon><initials>-xxxxx (ang numero ay ibibigay pag-submit)
            const clientInitials = c => (String(c).normalize('NFD').replace(/[\u0300-\u036f]/g, '').match(/[A-Za-z0-9]+/g) || [])
                .map(w => w[0].toUpperCase()).join('').slice(0, 10) || 'PR';
            function updateReqPreview() {
                const el = document.getElementById('prReqPreview');
                if (!el || mode !== 'edit') return;
                const client = (sheet.querySelector('[name="client"]')?.value || '').trim();
                const year = (sheet.querySelector('[name="request_date"]')?.value || todayISO()).slice(0, 4);
                el.textContent = client ? `${year}${clientInitials(client)}-xxxxx` : 'Auto-generated on submit';
            }
            const shortDate = iso => {
                if (!iso) return '';
                const d = new Date(iso + 'T00:00:00');
                return isNaN(d) ? iso : `${String(d.getDate()).padStart(2, '0')}-${d.toLocaleString('en-US', { month: 'short' })}-${String(d.getFullYear()).slice(2)}`;
            };

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

            // ═════════════ PAPEL NA FORM ═════════════
            // d = data ng request; edit = true kung pinupunan pa lang
            function renderSheet(d, edit) {
                const f = (name, val, type = 'text', extra = '') => edit
                    ? `<input type="${type}" name="${name}" value="${esc(val ?? '')}" ${extra}>`
                    : esc(val ?? '');
                const prepSig = edit ? ME.sig : d.prepared_by_signature_path;
                const prepName = edit ? ME.name : d.prepared_by;
                const prepTitle = edit ? ME.title : (d.prepared_by_title || 'Purchasing');
                const sigImg = p => p ? `<img src="${BASE}/${esc(p)}" alt="">` : '';

                document.getElementById('prSheet').innerHTML = `
                <!-- Letterhead -->
                <table class="nb"><tr>
                    <td style="width:16%;text-align:left"><img src="${LOGO}" alt="NobleHome" style="height:58px"></td>
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
                            <div>Req No: <b class="pr-red" id="prReqPreview">${edit ? 'Auto-generated on submit' : esc(d.req_no)}</b></div>
                            <div style="font-size:7.5px;margin-top:3px">"Strictly for Internal Use Only"</div>
                        </div>
                    </td>
                </tr></table>

                <table class="pr-mono" style="margin-top:-1px">
                    <tr>
                        <td style="width:18%;text-align:center;background:#eee">CLIENT</td>
                        <td style="width:47%">${f('client', edit && REQUESTER ? REQUESTER.client : d.client, 'text', 'readonly title="From the BOM"')}</td>
                        <td style="width:16%;background:#eee">REQUEST DATE:</td>
                        <td style="width:19%;text-align:right">${edit ? f('request_date', d.request_date || todayISO(), 'date') : esc(shortDate(d.request_date))}</td>
                    </tr>
                    <tr>
                        <td style="text-align:center;background:#eee">PROJECT SCOPE</td>
                        <td>${f('project_scope', d.project_scope ?? 'GENERAL SCOPE')}</td>
                        <td style="background:#eee">DATE NEEDED:</td>
                        <td>${f('date_needed', d.date_needed ?? 'ASAP')}</td>
                    </tr>
                </table>

                <table class="pr-mono" style="margin-top:-1px">
                    <colgroup>
                        <col style="width:5%"><col style="width:22%"><col style="width:13%"><col style="width:13%">
                        <col style="width:12%"><col style="width:13%"><col>${edit ? '<col style="width:4%" class="no-print">' : ''}
                    </colgroup>
                    <tr><td class="pr-bar" colspan="${edit ? 8 : 7}" style="font-weight:400">The information below must be filled out completely, correctly, and legibly .</td></tr>
                    <tr style="text-align:center">
                        <td>NO.</td><td>ITEM NAME</td><td>ITEM PICTURE</td><td>QUANTITY/UNIT</td>
                        <td>PRICE</td><td>AMOUNT</td><td>REMARKS</td>${edit ? '<td class="no-print"></td>' : ''}
                    </tr>
                    <tbody id="prItems"></tbody>
                    <tr>
                        <td colspan="3" style="text-align:center;font-weight:700">***TOTAL***</td>
                        <td></td><td></td>
                        <td style="text-align:right;font-weight:700" id="prTotal">0.00</td>
                        <td${edit ? ' colspan="2"' : ''}></td>
                    </tr>
                </table>

                <!-- Signatures -->
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
                            <div class="pr-sig">${sigImg(prepSig)}<span class="nm" style="font-style:normal">${esc(prepName)}</span></div>
                            <div class="pr-line"></div>
                            <div style="text-align:center">${esc(prepTitle)}</div>
                        </td>
                    </tr>
                    <tr>
                        <td style="vertical-align:top;padding:14px 26px 0">
                            <div style="font-weight:700;font-style:normal">Approved By:</div>
                            <div class="pr-sig"><span class="nm" style="font-style:normal">${esc(SIGN.approved.name)}</span></div>
                            <div class="pr-line"></div>
                            <div style="text-align:center">${esc(SIGN.approved.title)}</div>
                        </td>
                        <td style="vertical-align:top;padding:14px 26px 0">
                            <div class="pr-sig" style="margin-top:14px">${sigImg(edit ? (REQUESTER && REQUESTER.sig) : d.requested_by_signature_path)}<span class="nm" style="font-style:normal">${edit
                                    ? (REQUESTER ? esc(REQUESTER.name) : '<span style="color:#d00;font-weight:400;font-size:10px">Open this form from a received BOM</span>')
                                    : esc(d.requested_by)}</span></div>
                            <div class="pr-line"></div>
                            <div style="text-align:center;font-size:9px">Requested by:</div>
                        </td>
                    </tr>
                </table>

                <p class="pr-hint" style="margin-top:22px">Processing Policies:&nbsp; Request for Payment Form must be approved by the authorized person responsible for the budget item.</p>
                <p class="pr-hint" style="margin-top:8px">Please attach supplier quotation for each budget item</p>

                <!-- Accounting -->
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
                            <div style="text-align:center;font-weight:700;margin-top:12px">${esc(SIGN.reviewed.name)}</div>
                            <div class="pr-line"></div>
                            <div style="text-align:center;font-style:italic">${esc(SIGN.reviewed.title)}</div>
                            <div style="font-weight:700;font-style:italic;margin-top:14px">Released by:</div>
                            <div style="text-align:center;font-weight:700;margin-top:12px">${esc(SIGN.released.name)}</div>
                            <div class="pr-line"></div>
                            <div style="text-align:center;font-style:italic">${esc(SIGN.released.title)}</div>
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

                const body = document.getElementById('prItems');
                if (edit) {
                    rowSeq = 0;
                    for (let i = 0; i < 3; i++) addRow();
                } else {
                    const items = d.items || [];
                    body.innerHTML = items.map((it, i) => `
                        <tr>
                            <td style="text-align:center">${i + 1}</td>
                            <td style="font-family:Arial;font-weight:700;font-size:10px">${esc(it.item_name)}</td>
                            <td style="text-align:center;height:56px">${it.picture_path ? `<img src="${BASE}/${esc(it.picture_path)}" style="max-height:52px;max-width:100%;margin:auto;object-fit:contain">` : ''}</td>
                            <td>${qtyFmt(it.quantity)} ${esc(it.unit)}</td>
                            <td style="text-align:right">${money(it.price)}</td>
                            <td style="text-align:right">${money(it.amount)}</td>
                            <td>${esc(it.remarks)}</td>
                        </tr>`).join('') + (items.length < 3 ? '<tr><td style="height:24px"></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>'.repeat(3 - items.length) : '');
                    document.getElementById('prTotal').textContent = money(d.total_amount);
                }
            }

            // ── item rows (edit mode) ──
            function addRow() {
                const body = document.getElementById('prItems');
                const idx = rowSeq++;
                const tr = document.createElement('tr');
                tr.dataset.idx = idx;
                tr.innerHTML = `
                    <td style="text-align:center" class="pr-no"></td>
                    <td><input name="item_name[]" placeholder="Item name"><input type="hidden" name="row_index[]" value="${idx}"></td>
                    <td style="text-align:center">
                        <label style="cursor:pointer;display:block">
                            <img class="pr-thumb" style="display:none;max-height:44px;margin:auto">
                            <span class="pr-pick no-print" style="font-family:Arial;font-size:10px;color:#b45309"><i class="fa-solid fa-image"></i> Add</span>
                            <input type="file" accept="image/*" data-pic="${idx}" style="display:none">
                        </label>
                    </td>
                    <td><div style="display:flex;gap:3px"><input name="quantity[]" type="number" min="0" step="0.01" placeholder="Qty" style="width:48%"><input name="unit[]" placeholder="Unit" style="width:52%"></div></td>
                    <td><input name="price[]" type="number" min="0" step="0.01" placeholder="0.00" style="text-align:right"></td>
                    <td style="text-align:right" class="pr-amt">0.00</td>
                    <td><input name="remarks[]" placeholder="Remarks"></td>
                    <td class="no-print" style="text-align:center"><button type="button" class="pr-del" title="Remove" style="color:#9ca3af"><i class="fa-solid fa-xmark"></i></button></td>`;
                body.appendChild(tr);
                renumber();
            }

            function renumber() {
                document.querySelectorAll('#prItems tr').forEach((tr, i) => { const c = tr.querySelector('.pr-no'); if (c) c.textContent = i + 1; });
                recalc();
            }

            function recalc() {
                let total = 0;
                document.querySelectorAll('#prItems tr').forEach(tr => {
                    const q = parseFloat(tr.querySelector('[name="quantity[]"]')?.value) || 0;
                    const p = parseFloat(tr.querySelector('[name="price[]"]')?.value) || 0;
                    const a = Math.round(q * p * 100) / 100;
                    total += a;
                    const cell = tr.querySelector('.pr-amt');
                    if (cell) cell.textContent = money(a);
                });
                document.getElementById('prTotal').textContent = money(total);
            }

            const sheet = document.getElementById('prSheet');
            sheet.addEventListener('input', e => { if (e.target.matches('[name="quantity[]"], [name="price[]"]')) recalc(); });
            sheet.addEventListener('click', e => {
                const del = e.target.closest('.pr-del');
                if (!del) return;
                if (document.querySelectorAll('#prItems tr').length <= 1) { toast('At least one item row is needed.', 'error'); return; }
                del.closest('tr').remove();
                renumber();
            });
            sheet.addEventListener('change', e => {
                const inp = e.target.closest('[data-pic]');
                if (!inp || !inp.files[0]) return;
                const td = inp.closest('td');
                const img = td.querySelector('.pr-thumb');
                img.src = URL.createObjectURL(inp.files[0]);
                img.style.display = 'block';
                td.querySelector('.pr-pick').innerHTML = '<i class="fa-solid fa-rotate"></i> Change';
            });

            // ── mode switching ──
            function setMode(m) {
                mode = m;
                document.getElementById('prNoBom').classList.toggle('hidden', m !== 'none');
                document.getElementById('prPaper').classList.toggle('hidden', m === 'none');
                document.getElementById('prAddRow').classList.toggle('hidden', m !== 'edit');
                document.getElementById('prClearDraft').classList.toggle('hidden', m !== 'edit');
                document.getElementById('prSubmit').classList.toggle('hidden', m !== 'edit');
                document.getElementById('prPrint').classList.toggle('hidden', m !== 'view');
                document.getElementById('prNewBtn').classList.toggle('hidden', m !== 'view');
                document.getElementById('prSheetHint').innerHTML = m === 'edit'
                    ? (REQUESTER
                        ? `<i class="fa-solid fa-pen text-amber-700 mr-1"></i>Client <b>${esc(REQUESTER.client)}</b> and requester <b>${esc(REQUESTER.name)}</b> come from ${esc(REQUESTER.bom_number)}. Fill in the highlighted fields. <span id="prDraftTag" class="ml-1 text-gray-400"></span>`
                        : '<i class="fa-solid fa-triangle-exclamation text-red-600 mr-1"></i>Open this form from a received BOM so the requester is filled in automatically.')
                    : (m === 'none' ? '' : '<i class="fa-solid fa-circle-check text-green-600 mr-1"></i>Submitted purchase request. Print or save it as PDF.');
            }

            // ═════════════ DRAFT (hindi nare-reset ang ininput) ═════════════
            // Naka-save sa browser kada BOM; nawawala lang pagka-submit o pag pinindot ang Clear.
            const DRAFT_KEY = 'nh-pr-draft-' + (REQUESTER ? REQUESTER.bom_id : 'none');
            const HEAD_FIELDS = ['project_scope', 'request_date', 'date_needed'];   // ang client ay laging galing sa BOM
            let draftTimer = null;

            function readDraft() {
                try { return JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null'); } catch (e) { return null; }
            }
            function clearDraft() {
                try { localStorage.removeItem(DRAFT_KEY); } catch (e) { }
            }
            function saveDraft() {
                if (mode !== 'edit') return;
                const head = {};
                HEAD_FIELDS.forEach(n => head[n] = sheet.querySelector(`[name="${n}"]`)?.value ?? '');
                const items = [...document.querySelectorAll('#prItems tr')].map(tr => ({
                    item_name: tr.querySelector('[name="item_name[]"]')?.value ?? '',
                    quantity:  tr.querySelector('[name="quantity[]"]')?.value ?? '',
                    unit:      tr.querySelector('[name="unit[]"]')?.value ?? '',
                    price:     tr.querySelector('[name="price[]"]')?.value ?? '',
                    remarks:   tr.querySelector('[name="remarks[]"]')?.value ?? '',
                }));
                try {
                    localStorage.setItem(DRAFT_KEY, JSON.stringify({ head, items, savedAt: Date.now() }));
                    const tag = document.getElementById('prDraftTag');
                    if (tag) tag.textContent = 'Draft saved ' + new Date().toLocaleTimeString('en-PH', { hour: 'numeric', minute: '2-digit' });
                } catch (e) { /* puno o naka-block ang storage — tuloy lang */ }
            }
            function queueSave() { clearTimeout(draftTimer); draftTimer = setTimeout(saveDraft, 300); }

            function applyDraft(d) {
                HEAD_FIELDS.forEach(n => {
                    const el = sheet.querySelector(`[name="${n}"]`);
                    if (el && d.head && d.head[n] !== undefined) el.value = d.head[n];
                });
                const items = (d.items || []).length ? d.items : [];
                if (!items.length) return;
                document.getElementById('prItems').innerHTML = '';
                rowSeq = 0;
                items.forEach(it => {
                    addRow();
                    const tr = document.querySelector('#prItems tr:last-child');
                    tr.querySelector('[name="item_name[]"]').value = it.item_name || '';
                    tr.querySelector('[name="quantity[]"]').value  = it.quantity || '';
                    tr.querySelector('[name="unit[]"]').value      = it.unit || '';
                    tr.querySelector('[name="price[]"]').value     = it.price || '';
                    tr.querySelector('[name="remarks[]"]').value   = it.remarks || '';
                });
                renumber();
            }

            // auto-save kapag may tinype, binago, nagdagdag o nag-alis ng row
            sheet.addEventListener('input', queueSave);
            sheet.addEventListener('input', e => { if (e.target.matches('[name="client"], [name="request_date"]')) updateReqPreview(); });
            sheet.addEventListener('change', e => { if (e.target.matches('[name="request_date"]')) updateReqPreview(); });
            sheet.addEventListener('change', queueSave);
            sheet.addEventListener('click', e => { if (e.target.closest('.pr-del')) queueSave(); });

            // Paid na ang PR ng BOM → ipakita na lang ang PR, walang bagong form
            const LOCKED = REQUESTER && REQUESTER.paid_pr ? REQUESTER.paid_pr : null;
            function showLocked() {
                openPr(LOCKED.id, `<i class="fa-solid fa-lock text-red-600 mr-1"></i><b class="text-red-600">${esc(LOCKED.req_no)} is already paid.</b> A new purchase request can no longer be made for ${esc(REQUESTER.bom_number)}.`);
                document.getElementById('prNewBtn').classList.add('hidden');
            }

            function newForm() {
                if (LOCKED) return showLocked();
                if (!REQUESTER) { setMode('none'); setTab('sheet'); return; }   // walang BOM → walang form
                renderSheet({}, true);
                const draft = readDraft();
                if (draft) {
                    applyDraft(draft);
                    toast('Your unsaved inputs were restored.');
                }
                setMode('edit');
                updateReqPreview();
                setTab('sheet');
            }

            async function openPr(id, note = '') {
                try {
                    const res = await fetch(`${AJAX}?action=view&id=${id}`, { cache: 'no-store' });
                    const data = await res.json();
                    if (!data.success) { toast(data.message || 'Could not open request.', 'error'); return; }
                    PRSheet.render(document.getElementById('prSheet'), data.pr, { base: BASE });
                    setMode('view');
                    const pr = data.pr, hint = document.getElementById('prSheetHint');
                    if (pr.status === 'Approved') {
                        hint.innerHTML = `<i class="fa-solid fa-circle-check text-green-600 mr-1"></i>Approved by <b>${esc(pr.approved_by)}</b>. Print or save it as PDF.`;
                    } else if (pr.status === 'Rejected') {
                        hint.innerHTML = `<i class="fa-solid fa-rotate-left text-red-600 mr-1"></i><b class="text-red-600">Returned:</b> ${esc(pr.rejection_remarks || '')}`;
                    } else {
                        hint.innerHTML = '<i class="fa-solid fa-hourglass-half text-amber-600 mr-1"></i>Waiting for CEO approval.';
                    }
                    if (note) hint.innerHTML = note;
                    if (LOCKED) document.getElementById('prNewBtn').classList.add('hidden');
                    setTab('sheet');
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                } catch (e) { toast('Connection error.', 'error'); }
            }

            document.getElementById('prAddRow').addEventListener('click', () => { addRow(); queueSave(); });
            document.getElementById('prClearDraft').addEventListener('click', () => {
                if (!confirm('Clear everything you typed in this form?')) return;
                clearDraft();
                renderSheet({}, true);
                setMode('edit');
            });
            document.getElementById('prPrint').addEventListener('click', () => window.print());
            document.getElementById('prNewBtn').addEventListener('click', newForm);

            // ── submit ──
            document.getElementById('prSubmit').addEventListener('click', async function () {
                const fd = new FormData();
                const val = n => (sheet.querySelector(`[name="${n}"]`)?.value || '').trim();
                if (!val('client')) { toast('This BOM has no client name. Please check the BOM first.', 'error'); return; }

                if (!REQUESTER) { toast('Open this form from a received BOM (Next: Purchase Request) so the requester is filled in.', 'error', 6000); return; }
                ['project_scope', 'request_date', 'date_needed'].forEach(n => fd.append(n, val(n)));   // client: kukunin ng server sa BOM
                fd.append('bom_id', REQUESTER.bom_id);

                let count = 0;
                document.querySelectorAll('#prItems tr').forEach(tr => {
                    const name = tr.querySelector('[name="item_name[]"]').value.trim();
                    if (!name) return;
                    count++;
                    const idx = tr.dataset.idx;
                    fd.append('item_name[]', name);
                    fd.append('row_index[]', idx);
                    fd.append('quantity[]', tr.querySelector('[name="quantity[]"]').value || '0');
                    fd.append('unit[]', tr.querySelector('[name="unit[]"]').value);
                    fd.append('price[]', tr.querySelector('[name="price[]"]').value || '0');
                    fd.append('remarks[]', tr.querySelector('[name="remarks[]"]').value);
                    const file = tr.querySelector('[data-pic]').files[0];
                    if (file) fd.append('item_picture_' + idx, file);
                });
                if (!count) { toast('Add at least one item.', 'error'); return; }

                const btn = this;
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting…';
                try {
                    const res = await fetch(`${AJAX}?action=create`, { method: 'POST', body: fd });
                    const data = await res.json();
                    if (!data.success) { toast(data.message || 'Could not submit.', 'error'); return; }
                    clearTimeout(draftTimer);
                    clearDraft();
                    toast(`Purchase request ${data.req_no} submitted.`);
                    loadList();
                    openPr(data.id);
                } catch (e) {
                    toast('Connection error while submitting.', 'error');
                } finally {
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Submit Request';
                }
            });

            // ═════════════ LIST ═════════════
            async function loadList() {
                try {
                    const res = await fetch(`${AJAX}?action=list`, { cache: 'no-store' });
                    const data = await res.json();
                    if (!data.success) return;
                    listRows = data.rows;
                    document.getElementById('prCount').textContent = listRows.length;
                    renderList();
                } catch (e) { console.error(e); }
            }

            function renderList() {
                const q = document.getElementById('prSearch').value.trim().toLowerCase();
                const rows = listRows.filter(r => !q || `${r.req_no} ${r.client}`.toLowerCase().includes(q));
                document.getElementById('prList').innerHTML = rows.length ? rows.map(r => `
                    <tr class="hover:bg-amber-50/30 cursor-pointer" data-open="${r.id}">
                        <td class="px-5 py-3 font-mono text-[12px] font-semibold text-red-600">${esc(r.req_no)}</td>
                        <td class="px-5 py-3"><p class="font-semibold text-gray-800 truncate max-w-[240px]">${esc(r.client)}</p><p class="text-[11px] text-gray-400">${esc(r.project_scope || '')}</p></td>
                        <td class="px-5 py-3 text-gray-700">${esc(shortDate(r.request_date))}</td>
                        <td class="px-5 py-3 text-center tabular-nums">${r.item_count}</td>
                        <td class="px-5 py-3 text-right tabular-nums font-semibold text-gray-900">₱ ${money(r.total_amount)}</td>
                        <td class="px-5 py-3">${stageBadge(r)}</td>
                        <td class="px-5 py-3 text-right"><span class="text-[11px] font-semibold text-amber-700">View ›</span></td>
                    </tr>`).join('')
                    : `<tr><td colspan="7" class="px-5 py-14 text-center text-xs text-gray-400"><i class="fa-regular fa-folder-open text-2xl block mb-2 text-gray-300"></i>${listRows.length ? 'No requests match your search.' : 'No purchase requests yet.'}</td></tr>`;
            }
            document.getElementById('prSearch').addEventListener('input', renderList);
            document.getElementById('prList').addEventListener('click', e => {
                const tr = e.target.closest('[data-open]');
                if (tr) openPr(+tr.dataset.open);
            });

            // ═════════════ TABS ═════════════
            function setTab(t) {
                const isList = t === 'list';
                document.getElementById('prPanelSheet').classList.toggle('hidden', isList);
                document.getElementById('prPanelList').classList.toggle('hidden', !isList);
                document.querySelectorAll('.pr-tab').forEach(b => {
                    const on = isList ? b.dataset.tab === 'list' : (b.dataset.tab === 'new' && mode === 'edit');
                    b.classList.toggle('bg-amber-50', on);
                    b.classList.toggle('text-amber-700', on);
                    b.classList.toggle('text-gray-500', !on);
                });
            }
            document.querySelectorAll('.pr-tab').forEach(b => b.addEventListener('click', () => {
                if (b.dataset.tab === 'list') setTab('list');
                else newForm();
            }));

            // ?view=ID → buksan agad ang naka-submit na Purchase Request (galing sa Purchase Orders)
            const viewId = +(new URLSearchParams(location.search).get('view') || 0);
            if (viewId) openPr(viewId);
            else if (REQUESTER) newForm();
            else { setMode('none'); setTab('list'); }
            loadList();
        })();
    </script>
</body>

</html>