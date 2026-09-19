<?php
// bomajax.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_CUTTING];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

// dompdf — adjust the path below if your vendor folder lives elsewhere.
require_once ROOT_PATH . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$currentUserId = intval($_SESSION['account_id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// -----------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------

function bomFetchQuotation(mysqli $conn, int $quotationId): ?array
{
    // control_no / client_name live on noblecrminquiry, not on
    // noblecrm_2dquotation itself — join through inquiry_id.
    $stmt = $conn->prepare("
        SELECT q.id, i.control_no, i.client_name
        FROM noblecrm_2dquotation q
        INNER JOIN noblecrminquiry i ON i.id = q.inquiry_id
        WHERE q.id = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $quotationId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

// Builds the BOMNHCC-YYYYMMDD-00001 style number from the row's own
// auto-increment id, so it is a single global counter that never resets.
function bomGenerateNumber(int $bomId): string
{
    return 'BOMNHCC-' . date('Ymd') . '-' . str_pad((string) $bomId, 5, '0', STR_PAD_LEFT);
}

function bomFetchRequesterInfo(mysqli $conn, int $userId): array
{
    $result = ['name' => '', 'title' => '', 'signature_path' => null];
    if ($userId <= 0)
        return $result;

    $stmt = $conn->prepare("SELECT name, position, active_signature_id FROM noblerole WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$user)
        return $result;

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
        if ($sigRow)
            $signaturePath = $sigRow['path'];
    }

    // 2) Fallback: most recent is_active=1 row for this user.
    if ($signaturePath === null) {
        $sigStmt = $conn->prepare("SELECT path FROM noblesignature WHERE user_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1");
        $sigStmt->bind_param('i', $userId);
        $sigStmt->execute();
        $sigRow = $sigStmt->get_result()->fetch_assoc();
        $sigStmt->close();
        if ($sigRow)
            $signaturePath = $sigRow['path'];
    }

    $result['signature_path'] = $signaturePath;
    return $result;
}


function bomSignatureDataUri(?string $relPath): ?string
{
    if (!$relPath)
        return null;
    $abs = ROOT_PATH . '/' . ltrim($relPath, '/');
    if (!is_file($abs))
        return null;
    $mime = @mime_content_type($abs) ?: 'image/png';
    $data = base64_encode(file_get_contents($abs));
    return "data:{$mime};base64,{$data}";
}

function bomFetchOne(mysqli $conn, int $bomId): ?array
{
    $stmt = $conn->prepare("SELECT * FROM noblecrm_bom WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $bomId);
    $stmt->execute();
    $bom = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$bom)
        return null;

    $itemsStmt = $conn->prepare("SELECT * FROM noblecrm_bom_items WHERE bom_id = ? ORDER BY item_no ASC");
    $itemsStmt->bind_param('i', $bomId);
    $itemsStmt->execute();
    $bom['items'] = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $itemsStmt->close();

    return $bom;
}

function bomEsc(?string $v): string
{
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}


function bomRenderHtml(array $bom): string
{
    $items = $bom['items'];
    $rows = '';
    foreach ($items as $i => $it) {
        $rows .= '<tr>'
            . '<td class="c">' . ($i + 1) . '</td>'
            . '<td>' . bomEsc($it['item_code']) . '</td>'
            . '<td>' . bomEsc($it['item_description']) . '</td>'
            . '<td class="c">' . rtrim(rtrim(number_format((float) $it['quantity'], 2, '.', ''), '0'), '.') . '</td>'
            . '<td class="c">' . bomEsc($it['unit']) . '</td>'
            . '<td>' . bomEsc($it['supplier']) . '</td>'
            . '</tr>';
    }
    // Pad so the table doesn't look empty on small BOMs.
    $minRows = 6;
    for ($i = count($items); $i < $minRows; $i++) {
        $rows .= '<tr><td class="c">&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
    }

    $dateSubmitted = date('d/m/Y', strtotime($bom['date_submitted']));


    $requestedSigUri = bomSignatureDataUri($bom['requested_by_signature_path'] ?? null);
    $requestedCell = '<div style="position:relative;height:46px;">'
        . '<div style="position:relative;z-index:1;padding-top:26px;">' . bomEsc($bom['requested_by']) . '</div>'
        . ($requestedSigUri
            ? '<div style="position:absolute;top:-6px;left:0;right:0;text-align:center;z-index:2;">'
                . '<img src="' . $requestedSigUri . '" style="max-height:50px;max-width:160px;">'
              . '</div>'
            : '')
        . '</div>';

    // Approver (Superadmin) signature — filled in once the linked
    // cutting-progress upload gets QR-approved (see ewoodapprovalajax.php).
    $approvedSigUri = bomSignatureDataUri($bom['approved_by_signature_path'] ?? null);
    $approvedCell = '<div style="position:relative;height:46px;">'
        . '<div style="position:relative;z-index:1;padding-top:26px;">' . bomEsc($bom['approved_by']) . '</div>'
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
            <td class="c">' . bomEsc($dateSubmitted) . '</td>
        </tr>
    </table>

    <table style="margin-top:-1px;">
        <tr>
            <td class="label" style="width:20%">J.O NUMBER:</td>
            <td class="label c" colspan="2" style="width:58%">CLIENT</td>
            <td class="label c" style="width:22%">DATE NEEDED</td>
        </tr>
        <tr>
            <td>' . bomEsc($bom['bom_number']) . '</td>
            <td class="client" colspan="2">' . bomEsc($bom['client_name']) . '</td>
            <td class="c">' . bomEsc($bom['date_needed']) . '</td>
        </tr>
    </table>

    <table style="margin-top:-1px;">
        <tr>
            <td class="label" style="width:10%">SCOPE:</td>
            <td class="label">' . bomEsc($bom['general_scope']) . '</td>
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
            <td class="sig-name">' . bomEsc($bom['noted_by']) . '</td>
            <td class="sig-name">' . bomEsc($bom['received_by']) . '</td>
            <td class="sig-name">' . $approvedCell . '</td>
        </tr>
        <tr>
            <td class="sig-title">' . bomEsc($bom['requested_by_title']) . '</td>
            <td class="sig-title">' . bomEsc($bom['noted_by_title']) . '</td>
            <td class="sig-title">' . bomEsc($bom['received_by_title']) . '</td>
            <td class="sig-title">' . bomEsc($bom['approved_by_title']) . '</td>
        </tr>
        <tr><td colspan="4" class="footer">PROPERTY OF NOBLEHOME CONSTRUCTION</td></tr>
    </table>

    </body></html>';
}

// -----------------------------------------------------------------------
// Actions
// -----------------------------------------------------------------------

if ($action === 'list') {

    $quotationId = intval($_GET['quotation_id'] ?? 0);
    if ($quotationId <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid quotation.']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT id, bom_number, client_name, date_submitted, date_needed, created_at, progression_id, approved_at
        FROM noblecrm_bom
        WHERE quotation_id = ?
        ORDER BY id DESC
    ");
    $stmt->bind_param('i', $quotationId);
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'entries' => $rows]);
    exit;
}
// Returns one BOM (header fields + items) as JSON, for the in-page
// preview modal — as opposed to action=pdf, which streams a PDF download.
if ($action === 'view') {

    $bomId = intval($_GET['bom_id'] ?? 0);
    $bom = $bomId > 0 ? bomFetchOne($conn, $bomId) : null;

    if (!$bom) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'BOM not found.']);
        exit;
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'bom' => $bom]);
    exit;
}

if ($action === 'relink') {

    $quotationId = intval($_POST['quotation_id'] ?? 0);
    $progressionId = intval($_POST['progression_id'] ?? 0);

    if ($quotationId <= 0 || $progressionId <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid data.']);
        exit;
    }

    // Confirm the progression row actually belongs to this quotation and to
    // the currently logged-in cutting user, so one user can't relink a BOM
    // using a progression_id that isn't theirs / isn't for this job.
    $check = $conn->prepare("
        SELECT id FROM noblecrm_cuttinglistprogression
        WHERE id = ? AND quotation_id = ? AND uploaded_by = ?
        LIMIT 1
    ");
    $check->bind_param('iii', $progressionId, $quotationId, $currentUserId);
    $check->execute();
    $progRow = $check->get_result()->fetch_assoc();
    $check->close();

    if (!$progRow) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Progression not found for this job.']);
        exit;
    }

    // Find the newest not-yet-approved BOM for this quotation.
    $find = $conn->prepare("
        SELECT id FROM noblecrm_bom
        WHERE quotation_id = ? AND approved_at IS NULL
        ORDER BY id DESC
        LIMIT 1
    ");
    $find->bind_param('i', $quotationId);
    $find->execute();
    $bomRow = $find->get_result()->fetch_assoc();
    $find->close();

    if (!$bomRow) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No unapproved BOM to relink.']);
        exit;
    }

    $bomId = (int) $bomRow['id'];
    $upd = $conn->prepare("UPDATE noblecrm_bom SET progression_id = ? WHERE id = ?");
    $upd->bind_param('ii', $progressionId, $bomId);
    $upd->execute();
    $upd->close();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'bom_id' => $bomId]);
    exit;
}

if ($action === 'create') {

    $quotationId = intval($_POST['quotation_id'] ?? 0);
    $quotation = $quotationId > 0 ? bomFetchQuotation($conn, $quotationId) : null;

    if (!$quotation) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid quotation.']);
        exit;
    }

    $dateNeeded = trim($_POST['date_needed'] ?? 'ASAP') ?: 'ASAP';
    $generalScope = trim($_POST['general_scope'] ?? 'GENERAL SCOPE') ?: 'GENERAL SCOPE';


    $requester = bomFetchRequesterInfo($conn, $currentUserId);
    $requestedBy = $requester['name'];
    $requestedByTitle = $requester['title'];
    $requestedBySignaturePath = $requester['signature_path'];

    $notedBy = null;
    $notedByTitle = null;
    $receivedBy = null;
    $receivedByTitle = null;
    $approvedBy = null;
    $approvedByTitle = null;

    $itemCodes = $_POST['item_code'] ?? [];
    $itemDescs = $_POST['item_description'] ?? [];
    $itemQtys = $_POST['quantity'] ?? [];
    $itemUnits = $_POST['unit'] ?? [];
    $itemSups = $_POST['supplier'] ?? [];

    $items = [];
    $count = count($itemDescs);
    for ($i = 0; $i < $count; $i++) {
        $desc = trim($itemDescs[$i] ?? '');
        if ($desc === '')
            continue; // skip blank rows
        $items[] = [
            'item_code' => trim($itemCodes[$i] ?? ''),
            'description' => $desc,
            'quantity' => (float) ($itemQtys[$i] ?? 0),
            'unit' => trim($itemUnits[$i] ?? ''),
            'supplier' => trim($itemSups[$i] ?? ''),
        ];
    }

    if (empty($items)) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Add at least one item with a description.']);
        exit;
    }

    $today = date('Y-m-d');
    $placeholderNumber = 'TEMP-' . uniqid();


    $progressionId = intval($_POST['progression_id'] ?? 0) ?: null;

    $stmt = $conn->prepare("
        INSERT INTO noblecrm_bom
            (quotation_id, progression_id, bom_number, page, date_submitted, date_needed, client_name, general_scope,
             requested_by, requested_by_title, requested_by_signature_path, noted_by, noted_by_title,
             received_by, received_by_title, approved_by, approved_by_title, created_by, created_at)
        VALUES (?, ?, ?, 1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->bind_param(
        'iissssssssssssssi',
        $quotationId,
        $progressionId,
        $placeholderNumber,
        $today,
        $dateNeeded,
        $quotation['client_name'],
        $generalScope,
        $requestedBy,
        $requestedByTitle,
        $requestedBySignaturePath,
        $notedBy,
        $notedByTitle,
        $receivedBy,
        $receivedByTitle,
        $approvedBy,
        $approvedByTitle,
        $currentUserId
    );
    $stmt->execute();
    $bomId = $stmt->insert_id;
    $stmt->close();

    // Now that we have the row's own id, turn it into the real reference
    // number: BOMNHCC-YYYYMMDD-00001 (global counter, never resets).
    $bomNumber = bomGenerateNumber($bomId);
    $upd = $conn->prepare("UPDATE noblecrm_bom SET bom_number = ? WHERE id = ?");
    $upd->bind_param('si', $bomNumber, $bomId);
    $upd->execute();
    $upd->close();

    $itemStmt = $conn->prepare("
        INSERT INTO noblecrm_bom_items (bom_id, item_no, item_code, item_description, quantity, unit, supplier)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    foreach ($items as $idx => $it) {
        $itemNo = $idx + 1;
        $itemStmt->bind_param(
            'iissdss',
            $bomId,
            $itemNo,
            $it['item_code'],
            $it['description'],
            $it['quantity'],
            $it['unit'],
            $it['supplier']
        );
        $itemStmt->execute();
    }
    $itemStmt->close();

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Bill of Materials saved.',
        'id' => $bomId,
        'bom_number' => $bomNumber,
    ]);
    exit;
}

if ($action === 'pdf') {

    $bomId = intval($_GET['bom_id'] ?? 0);
    $bom = $bomId > 0 ? bomFetchOne($conn, $bomId) : null;

    if (!$bom) {
        http_response_code(404);
        echo 'Not found.';
        exit;
    }

    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $options->set('defaultPaperSize', 'legal');
    $options->set('defaultFont', 'Helvetica'); // dompdf falls back to serif otherwise, even with sans-serif in the CSS

    $dompdf = new Dompdf($options);
    $dompdf->setPaper('legal', 'portrait');
    $dompdf->loadHtml(bomRenderHtml($bom));
    $dompdf->render();

    $filename = $bom['bom_number'] . '.pdf';
    $dompdf->stream($filename, ['Attachment' => true]);
    exit;
}

header('Content-Type: application/json');
echo json_encode(['success' => false, 'message' => 'Unknown action.']);