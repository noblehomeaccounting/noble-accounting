<?php
// ewoodapprovalajax.php


include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SUPERADMIN];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

// dompdf — needed for the bom_pdf action below.
require_once ROOT_PATH . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

header('Content-Type: application/json');

$currentUserId = intval($_SESSION['account_id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Notifies the cutting staff who uploaded this submission that the
// Superadmin has approved or rejected it.
function ewoodNotifyUploader(mysqli $conn, int $progressId, int $superadminId, string $decision, string $reason = ''): void
{
    $stmt = $conn->prepare("
        SELECT p.uploaded_by, i.control_no, i.client_name
        FROM noblecrm_cuttinglistprogression p
        JOIN noblecrm_2dquotation q ON q.id = p.quotation_id
        JOIN noblecrminquiry i ON i.id = q.inquiry_id
        WHERE p.id = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $progressId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row || empty($row['uploaded_by'])) {
        return; // nothing to notify, or uploader unknown
    }

    $uploaderId = (int) $row['uploaded_by'];
    $controlNo = $row['control_no'] ?? null;
    $label = $controlNo
        ? ($row['client_name'] ? "{$controlNo} — {$row['client_name']}" : $controlNo)
        : 'your submission';

    $message = $decision === 'approved'
        ? "Your cutting progress upload for {$label} has been approved."
        : "Your cutting progress upload for {$label} was rejected" . ($reason !== '' ? ": {$reason}" : '.');

    $link = '/crmewood';

    $notifStmt = $conn->prepare("
        INSERT INTO noblenotification
            (user_id, request_id, control_no, type, message, is_read, created_at, sender_id, link)
        VALUES (?, ?, ?, 'crm', ?, 0, NOW(), ?, ?)
    ");
    $notifStmt->bind_param(
        'iissis',
        $uploaderId,
        $progressId,
        $controlNo,
        $message,
        $superadminId,
        $link
    );
    $notifStmt->execute();
    $notifStmt->close();
}

// -----------------------------------------------------------------------
// BOM read-only helpers (Superadmin can view/download, never create/edit —
// creation stays exclusive to ROLE_CUTTING in bomajax.php).
// -----------------------------------------------------------------------

function ewoodBomFetchList(mysqli $conn, int $quotationId): array
{
    $stmt = $conn->prepare("
        SELECT id, bom_number, client_name, date_submitted, date_needed, created_at
        FROM noblecrm_bom
        WHERE quotation_id = ?
        ORDER BY id DESC
    ");
    $stmt->bind_param('i', $quotationId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function ewoodBomFetchOne(mysqli $conn, int $bomId): ?array
{
    $stmt = $conn->prepare("SELECT * FROM noblecrm_bom WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $bomId);
    $stmt->execute();
    $bom = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$bom) {
        return null;
    }

    $itemsStmt = $conn->prepare("SELECT * FROM noblecrm_bom_items WHERE bom_id = ? ORDER BY item_no ASC");
    $itemsStmt->bind_param('i', $bomId);
    $itemsStmt->execute();
    $bom['items'] = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $itemsStmt->close();

    return $bom;
}

function ewoodBomEsc(?string $v): string
{
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

function ewoodBomSignatureDataUri(?string $relPath): ?string
{
    if (!$relPath) {
        return null;
    }
    $abs = ROOT_PATH . '/' . ltrim($relPath, '/');
    if (!is_file($abs)) {
        return null;
    }
    $mime = @mime_content_type($abs) ?: 'image/png';
    $data = base64_encode(file_get_contents($abs));
    return "data:{$mime};base64,{$data}";
}

// Pulls the approver's (Superadmin's) name, title, and active signature
// path — same lookup pattern as bomFetchRequesterInfo() in bomajax.php.
function ewoodBomApproverInfo(mysqli $conn, int $userId): array
{
    $result = ['name' => '', 'title' => '', 'signature_path' => null];
    if ($userId <= 0) {
        return $result;
    }

    $stmt = $conn->prepare("SELECT name, position, active_signature_id FROM noblerole WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$user) {
        return $result;
    }

    $result['name'] = $user['name'] ?? '';
    $result['title'] = ucwords(str_replace('_', ' ', $user['position'] ?? ''));

    $signaturePath = null;

    // 1) Prefer the row pointed to by active_signature_id.
    if (!empty($user['active_signature_id'])) {
        $sigStmt = $conn->prepare("SELECT path FROM noblesignature WHERE id = ? AND user_id = ? LIMIT 1");
        $sigStmt->bind_param('ii', $user['active_signature_id'], $userId);
        $sigStmt->execute();
        $sigRow = $sigStmt->get_result()->fetch_assoc();
        $sigStmt->close();
        if ($sigRow) {
            $signaturePath = $sigRow['path'];
        }
    }

    // 2) Fallback: most recent is_active=1 row for this user.
    if ($signaturePath === null) {
        $sigStmt = $conn->prepare("SELECT path FROM noblesignature WHERE user_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1");
        $sigStmt->bind_param('i', $userId);
        $sigStmt->execute();
        $sigRow = $sigStmt->get_result()->fetch_assoc();
        $sigStmt->close();
        if ($sigRow) {
            $signaturePath = $sigRow['path'];
        }
    }

    $result['signature_path'] = $signaturePath;
    return $result;
}

// Stamps the Superadmin's name/title/signature directly onto the BOM that
// was submitted together with this cutting-progress upload.
//
// Primary link is progression_id. Older BOMs (and any submit where the
// progression_id didn't make it through) have that column NULL, so we fall
// back to the newest not-yet-approved BOM of the same quotation and repair
// the link while we're at it.
//
// Returns how many BOM rows were actually stamped — 0 means nothing was
// linked to this upload, which the caller surfaces to the front-end instead
// of silently reporting success.
function ewoodBomMarkApproved(mysqli $conn, int $progressId, int $superadminId): int
{
    $approver = ewoodBomApproverInfo($conn, $superadminId);

    // 1) Direct link via progression_id.
    $stmt = $conn->prepare("
        UPDATE noblecrm_bom
        SET approved_by = ?, approved_by_title = ?, approved_by_signature_path = ?, approved_at = NOW()
        WHERE progression_id = ?
    ");
    $stmt->bind_param('sssi', $approver['name'], $approver['title'], $approver['signature_path'], $progressId);
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();

    if ($affected > 0) {
        return $affected;
    }

    // 2) Fallback: newest unapproved BOM belonging to the same quotation.
    $q = $conn->prepare("SELECT quotation_id FROM noblecrm_cuttinglistprogression WHERE id = ? LIMIT 1");
    $q->bind_param('i', $progressId);
    $q->execute();
    $row = $q->get_result()->fetch_assoc();
    $q->close();

    if (!$row) {
        return 0;
    }
    $quotationId = (int) $row['quotation_id'];

    $fb = $conn->prepare("
        UPDATE noblecrm_bom
        SET approved_by = ?, approved_by_title = ?, approved_by_signature_path = ?,
            approved_at = NOW(), progression_id = ?
        WHERE quotation_id = ? AND approved_at IS NULL
        ORDER BY id DESC
        LIMIT 1
    ");
    $fb->bind_param(
        'sssii',
        $approver['name'],
        $approver['title'],
        $approver['signature_path'],
        $progressId,
        $quotationId
    );
    $fb->execute();
    $affected = $fb->affected_rows;
    $fb->close();

    return $affected;
}

// Same printed layout as bomajax.php's bomRenderHtml() — kept as a separate
// copy here since this file is scoped to ROLE_SUPERADMIN and bomajax.php's
// functions aren't shared across role-guarded endpoints.
//
// NOTE: requested/approved signature cells use the same absolute-overlay
// markup as bomajax.php (name in a relative div, signature image absolutely
// positioned on top of it) instead of a plain <br> + image stack, because
// that's the version that renders correctly through dompdf.
function ewoodBomRenderHtml(array $bom): string
{
    $items = $bom['items'];
    $rows = '';
    foreach ($items as $i => $it) {
        $rows .= '<tr>'
            . '<td class="c">' . ($i + 1) . '</td>'
            . '<td>' . ewoodBomEsc($it['item_code']) . '</td>'
            . '<td>' . ewoodBomEsc($it['item_description']) . '</td>'
            . '<td class="c">' . rtrim(rtrim(number_format((float) $it['quantity'], 2, '.', ''), '0'), '.') . '</td>'
            . '<td class="c">' . ewoodBomEsc($it['unit']) . '</td>'
            . '<td>' . ewoodBomEsc($it['supplier']) . '</td>'
            . '</tr>';
    }
    $minRows = 6;
    for ($i = count($items); $i < $minRows; $i++) {
        $rows .= '<tr><td class="c">&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
    }

    $dateSubmitted = date('d/m/Y', strtotime($bom['date_submitted']));

    $requestedSigUri = ewoodBomSignatureDataUri($bom['requested_by_signature_path'] ?? null);
    $requestedCell = '<div style="position:relative;height:46px;">'
        . '<div style="position:relative;z-index:1;padding-top:26px;">' . ewoodBomEsc($bom['requested_by']) . '</div>'
        . ($requestedSigUri
            ? '<div style="position:absolute;top:-6px;left:0;right:0;text-align:center;z-index:2;">'
            . '<img src="' . $requestedSigUri . '" style="max-height:50px;max-width:160px;">'
            . '</div>'
            : '')
        . '</div>';

    // Approver (Superadmin) signature — filled in by ewoodBomMarkApproved()
    // once this BOM's linked cutting-progress upload gets QR-approved.
    $approvedSigUri = ewoodBomSignatureDataUri($bom['approved_by_signature_path'] ?? null);
    $approvedCell = '<div style="position:relative;height:46px;">'
        . '<div style="position:relative;z-index:1;padding-top:26px;">' . ewoodBomEsc($bom['approved_by']) . '</div>'
        . ($approvedSigUri
            ? '<div style="position:absolute;top:-6px;left:0;right:0;text-align:center;z-index:2;">'
            . '<img src="' . $approvedSigUri . '" style="max-height:50px;max-width:160px;">'
            . '</div>'
            : '')
        . '</div>';

    return '
    <html><head><meta charset="UTF-8"><style>
        @page { margin: 18px; }
body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color:#111; }
table { width: 100%; border-collapse: collapse; table-layout: fixed; }
td, th { border: 1px solid #000; padding: 7px 8px; vertical-align: middle; word-wrap: break-word; }
.noborder { border: none; }
.c { text-align: center; }
.r { text-align: right; }
.label { font-size: 8px; font-weight: bold; text-transform: uppercase; color:#333; background-color:#d9d9d9; }
.title { font-size: 20px; font-weight: 900; font-family: Helvetica, Arial, sans-serif; text-align: center; letter-spacing: 0.5px; white-space: nowrap; }
.client { font-size: 15px; font-weight: bold; text-align: center; }
.nothing-follows { text-align:center; color:#b00; font-weight:bold; font-style:italic; padding: 8px; }
.sig-name { font-weight: bold; text-align:center; }
.sig-title { font-style: italic; text-align:center; font-size: 8px; }
.footer { text-align:center; font-size: 7px; padding-top: 4px; }
    </style></head><body>

    <table>
        <tr>
            <td style="width:8%">&nbsp;</td>
            <td class="title" style="width:62%" colspan="1">BILL OF MATERIALS</td>
            <td class="label c" style="width:8%">PAGE</td>
            <td class="label c" style="width:22%">DATE SUBMITTED</td>
        </tr>
        <tr>
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td class="c">' . (int) $bom['page'] . '</td>
            <td class="c">' . ewoodBomEsc($dateSubmitted) . '</td>
        </tr>
    </table>

    <table style="margin-top:-1px;">
        <tr>
            <td class="label" style="width:20%">J.O NUMBER:</td>
            <td class="label c" colspan="2" style="width:58%">CLIENT</td>
            <td class="label c" style="width:22%">DATE NEEDED</td>
        </tr>
        <tr>
            <td>' . ewoodBomEsc($bom['bom_number']) . '</td>
            <td class="client" colspan="2">' . ewoodBomEsc($bom['client_name']) . '</td>
            <td class="c">' . ewoodBomEsc($bom['date_needed']) . '</td>
        </tr>
    </table>

    <table style="margin-top:-1px;">
        <tr>
            <td class="label" style="width:10%">SCOPE:</td>
            <td class="label">' . ewoodBomEsc($bom['general_scope']) . '</td>
        </tr>
    </table>

    <table style="margin-top:-1px;">
        <tr class="label">
            <td class="c" style="width:5%">NO.</td>
            <td class="c" style="width:15%">ITEM CODE</td>
            <td style="width:35%">ITEM DESCRIPTION</td>
            <td class="c" style="width:10%">QUANTITY</td>
            <td class="c" style="width:10%">UNIT</td>
            <td style="width:25%">SUPPLIER</td>
        </tr>
        ' . $rows . '
        <tr><td colspan="6" class="nothing-follows">** NOTHING FOLLOWS **</td></tr>
    </table>

    <table style="margin-top:-1px;">
        <tr class="label c">
            <td style="width:25%">REQUESTED BY:</td>
            <td style="width:25%">NOTED BY:</td>
            <td style="width:25%">RECEIVED BY:</td>
            <td style="width:25%">APPROVED BY:</td>
        </tr>
        <tr>
            <td class="sig-name">' . $requestedCell . '</td>
            <td class="sig-name">' . ewoodBomEsc($bom['noted_by']) . '</td>
            <td class="sig-name">' . ewoodBomEsc($bom['received_by']) . '</td>
            <td class="sig-name">' . $approvedCell . '</td>
        </tr>
        <tr>
            <td class="sig-title">' . ewoodBomEsc($bom['requested_by_title']) . '</td>
            <td class="sig-title">' . ewoodBomEsc($bom['noted_by_title']) . '</td>
            <td class="sig-title">' . ewoodBomEsc($bom['received_by_title']) . '</td>
            <td class="sig-title">' . ewoodBomEsc($bom['approved_by_title']) . '</td>
        </tr>
        <tr><td colspan="4" class="footer">PROPERTY OF NOBLEHOME CONSTRUCTION</td></tr>
    </table>

    </body></html>';
}

// ================= PENDING APPROVAL =================
// Lists cutting-progress rows that have at least one uploaded photo
// (expected to contain a QR code) and have not yet been QR-approved.
if ($action === 'pending_approval') {

    $stmt = $conn->prepare("
        SELECT p.id, p.quotation_id, p.photos, p.status, i.control_no, i.client_name
        FROM noblecrm_cuttinglistprogression p
        JOIN noblecrm_2dquotation q ON q.id = p.quotation_id
        JOIN noblecrminquiry i ON i.id = q.inquiry_id
        WHERE p.qr_approved = 0
          AND p.qr_rejected = 0
          AND p.photos IS NOT NULL AND p.photos != ''
        ORDER BY p.id DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    $entries = [];
    while ($row = $result->fetch_assoc()) {
        $photos = array_values(array_filter(explode(',', $row['photos'] ?? '')));
        if (empty($photos))
            continue;
        $entries[] = [
            'id' => (int) $row['id'],
            'quotation_id' => (int) $row['quotation_id'], // needed by the front-end to load this job's BOM(s)
            'control_no' => $row['control_no'],
            'client_name' => $row['client_name'],
            'status' => $row['status'],
            'photos' => array_map(fn($p) => BASE_URL . '/' . $p, $photos),
        ];
    }
    $stmt->close();

    echo json_encode(['success' => true, 'entries' => $entries]);
    exit;
}

// ================= APPROVED LIST =================
// History of everything that has already been QR-approved, shown in the
// second "Approved" container on ewood.php. Read-only — no actions here.
if ($action === 'approved_list') {

    $stmt = $conn->prepare("
        SELECT p.id, p.quotation_id, p.photos, p.status, p.approved_at,
               i.control_no, i.client_name, r.name AS approved_by_name
        FROM noblecrm_cuttinglistprogression p
        JOIN noblecrm_2dquotation q ON q.id = p.quotation_id
        JOIN noblecrminquiry i ON i.id = q.inquiry_id
        LEFT JOIN noblerole r ON r.id = p.approved_by
        WHERE p.qr_approved = 1
        ORDER BY p.approved_at DESC, p.id DESC
    ");
    $stmt->execute();
    $result = $stmt->get_result();

    $entries = [];
    while ($row = $result->fetch_assoc()) {
        $photos = array_values(array_filter(explode(',', $row['photos'] ?? '')));
        $entries[] = [
            'id' => (int) $row['id'],
            'quotation_id' => (int) $row['quotation_id'],
            'control_no' => $row['control_no'],
            'client_name' => $row['client_name'],
            'status' => $row['status'],
            'approved_at' => $row['approved_at'],
            'approved_by' => $row['approved_by_name'],
            'photos' => array_map(fn($p) => BASE_URL . '/' . $p, $photos),
        ];
    }
    $stmt->close();

    echo json_encode(['success' => true, 'entries' => $entries]);
    exit;
}

// ================= APPROVE =================
// Superadmin has already manually verified the QR via the WeChat app
// (the code can't be decoded in-browser — see note at top of file).
// This just records the approval.
if ($action === 'approve') {

    $progressId = intval($_POST['progress_id'] ?? 0);

    if ($progressId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Missing data.']);
        exit;
    }

    $stmt = $conn->prepare("
    UPDATE noblecrm_cuttinglistprogression
    SET qr_approved = 1, approved_by = ?, approved_at = NOW(), status = 'Completed'
    WHERE id = ?
");
    $stmt->bind_param('ii', $currentUserId, $progressId);
    $stmt->execute();
    $stmt->close();
    $bomUpdated = ewoodBomMarkApproved($conn, $progressId, $currentUserId);

    // Let the cutting staff who uploaded this know it's been approved.
    ewoodNotifyUploader($conn, $progressId, $currentUserId, 'approved');

    echo json_encode([
        'success' => true,
        'message' => 'Approved.',
        'bom_updated' => $bomUpdated,
    ]);
    exit;
}

// ================= REJECT =================
// Superadmin declines this upload (e.g. wrong QR, unreadable, invalid).
// A short reason is required so cutting knows what to fix.
if ($action === 'reject') {

    $progressId = intval($_POST['progress_id'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');

    if ($progressId <= 0 || $remarks === '') {
        echo json_encode(['success' => false, 'message' => 'A rejection reason is required.']);
        exit;
    }

    // Fetch the uploader BEFORE we wipe/clear the row's data, so the
    // notification still has what it needs.
    ewoodNotifyUploader($conn, $progressId, $currentUserId, 'rejected', $remarks);

    $stmt = $conn->prepare("
        UPDATE noblecrm_cuttinglistprogression
        SET qr_rejected = 1, rejection_remarks = ?, rejected_by = ?, rejected_at = NOW(),
            archive_path = NULL, archive_original_name = NULL, photos = '', status = 'Pending'
        WHERE id = ?
    ");
    $stmt->bind_param('sii', $remarks, $currentUserId, $progressId);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['success' => true, 'message' => 'Rejected.']);
    exit;
}

// ================= BOM: LIST =================
// Lets the Superadmin see which BOM(s) exist for this quotation while
// reviewing a pending QR approval. Read-only — creation stays with cutting.
if ($action === 'bom_list') {

    $quotationId = intval($_GET['quotation_id'] ?? 0);
    if ($quotationId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid quotation.']);
        exit;
    }

    echo json_encode(['success' => true, 'entries' => ewoodBomFetchList($conn, $quotationId)]);
    exit;
}

// ================= BOM: VIEW =================
// One BOM's full data (header + items), for the in-page preview modal.
if ($action === 'bom_view') {

    $bomId = intval($_GET['bom_id'] ?? 0);
    $bom = $bomId > 0 ? ewoodBomFetchOne($conn, $bomId) : null;

    if (!$bom) {
        echo json_encode(['success' => false, 'message' => 'BOM not found.']);
        exit;
    }

    echo json_encode(['success' => true, 'bom' => $bom]);
    exit;
}

// ================= BOM: PDF =================
if ($action === 'bom_pdf') {

    $bomId = intval($_GET['bom_id'] ?? 0);
    $bom = $bomId > 0 ? ewoodBomFetchOne($conn, $bomId) : null;

    if (!$bom) {
        http_response_code(404);
        echo 'Not found.';
        exit;
    }

    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $options->set('defaultPaperSize', 'legal');
    $options->set('defaultFont', 'Helvetica');

    $dompdf = new Dompdf($options);
    $dompdf->setPaper('legal', 'portrait');
    $dompdf->loadHtml(ewoodBomRenderHtml($bom));
    $dompdf->render();

    $filename = $bom['bom_number'] . '.pdf';
    $dompdf->stream($filename, ['Attachment' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);