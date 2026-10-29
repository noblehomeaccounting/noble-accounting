<?php
// ewoodapprovalajax.php  (route: crmwoodapprovalajax)


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

// Kapag may PHP/MySQL error, JSON pa rin ang ibabalik (dati HTML fatal error kaya "Error while rejecting" lang ang lumalabas)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
set_exception_handler(function (Throwable $e) {
    error_log('ewoodapprovalajax: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: application/json');
    }
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
});

$currentUserId = intval($_SESSION['account_id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Notifies the cutting staff who uploaded this submission that the
// Superadmin has approved or rejected it.
function ewoodNotifyUploader(mysqli $conn, int $progressId, int $superadminId, string $decision, string $reason = ''): void
{
    // Notification lang ito — kapag pumalya (hal. iba ang columns ng noblenotification),
    // hindi dapat nito pigilan ang approve/revision.
    try {
        ewoodNotifyUploaderRun($conn, $progressId, $superadminId, $decision, $reason);
    } catch (Throwable $e) {
        error_log('ewoodNotifyUploader: ' . $e->getMessage());
    }
}

function ewoodNotifyUploaderRun(mysqli $conn, int $progressId, int $superadminId, string $decision, string $reason = ''): void
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

    if ($decision === 'partial') {
        // may BOM na naaprubahan at may naibalik
        $message = "Your cutting progress upload for {$label} was approved, but some BOMs were sent back: {$reason}";
    } else {
        $message = $decision === 'approved'
            ? "Your cutting progress upload for {$label} has been approved."
            : "Your cutting progress upload for {$label} was rejected" . ($reason !== '' ? ": {$reason}" : '.');
    }

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

// Realtime: Cutting (E-Woodfile / Cutting List), ibang Superadmin (E-Wood Management),
// at PO kapag approved (lalabas ang BOM sa Purchase Order) + Sales/Designer ng inquiry.
// Signal lang ito — kapag pumalya, hindi nito pinipigilan ang approve/revision.
function ewoodRealtime(mysqli $conn, int $progressId, string $type, bool $approved): void
{
    try {
        include_once ROOT_PATH . '/network/realtime-helper.php';
        $st = $conn->prepare("SELECT quotation_id FROM noblecrm_cuttinglistprogression WHERE id = ? LIMIT 1");
        $st->bind_param('i', $progressId);
        $st->execute();
        $quotationId = (int) ($st->get_result()->fetch_assoc()['quotation_id'] ?? 0);
        $st->close();

        $groups = [[ROLE_CUTTING, null], [ROLE_SUPERADMIN, null]];
        if ($approved) {
            $groups[] = [ROLE_PO, null];
        }

        if ($quotationId > 0) {
            nhRtQuotation($conn, $quotationId, $groups, $type);
        } else {
            foreach ($groups as $g) {
                nhRealtimeNotifyGroup((string) $g[0], null, 'changed', ['type' => $type]);
            }
        }
    } catch (Throwable $e) {
        error_log('ewoodRealtime: ' . $e->getMessage());
    }
}

// May returned_at / returned_by / return_remarks na ba ang noblecrm_bom? (noblecrm_bom_return.sql)
function ewoodBomHasReturnCols(mysqli $conn): bool
{
    static $has = null;
    if ($has === null) {
        try {
            $r = $conn->query("SHOW COLUMNS FROM noblecrm_bom LIKE 'return_remarks'");
            $has = $r && $r->num_rows > 0;
        } catch (Throwable $e) {
            $has = false;
        }
    }
    return $has;
}

// -----------------------------------------------------------------------
// BOM read-only helpers (Superadmin can view/download, never create/edit —
// creation stays exclusive to ROLE_CUTTING in bomajax.php).
// -----------------------------------------------------------------------

function ewoodBomFetchList(mysqli $conn, int $quotationId, int $progressId = 0): array
{
    // Hanapin sa quotation_id, at pati sa progression_id ng upload na ito
    // (para lumabas pa rin kahit hindi tugma ang quotation_id ng BOM)
    $stmt = $conn->prepare("
        SELECT id, bom_number, client_name, date_submitted, date_needed, created_at, approved_at
        FROM noblecrm_bom
        WHERE (quotation_id = ? OR (? > 0 AND progression_id = ?))
          AND bom_number NOT LIKE 'TEMP-%'
        ORDER BY id DESC
    ");
    $stmt->bind_param('iii', $quotationId, $progressId, $progressId);
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

    // Purchase Order (PO) signature — filled in once PO receives the BOM
    // (see purchaseorderajax.php).
    $receivedSigUri = ewoodBomSignatureDataUri($bom['received_by_signature_path'] ?? null);
    $receivedCell = '<div style="position:relative;height:46px;">'
        . '<div style="position:relative;z-index:1;padding-top:26px;">' . ewoodBomEsc($bom['received_by']) . '</div>'
        . ($receivedSigUri
            ? '<div style="position:absolute;top:-6px;left:0;right:0;text-align:center;z-index:2;">'
            . '<img src="' . $receivedSigUri . '" style="max-height:50px;max-width:160px;">'
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
            <td class="sig-name">' . $receivedCell . '</td>
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


// ── Para sa review page: BOM (kasama ang items) na naka-link sa bawat upload ──
// Una: BOM na may progression_id ng upload. Kapag wala (lumang data), ang
// pinakabagong hindi pa approved na BOM ng parehong quotation.
function ewoodBomsForUploads(mysqli $conn, array $rows, bool $fallback): array
{
    $out = [];
    if (!$rows) {
        return $out;
    }
    $ids = implode(',', array_map(fn($r) => (int) $r['id'], $rows));
    $boms = $conn->query("
        SELECT id, progression_id, quotation_id, bom_number, date_needed, general_scope, created_at, approved_at, requested_by
        FROM noblecrm_bom
        WHERE progression_id IN ($ids) AND bom_number NOT LIKE 'TEMP-%'
        ORDER BY id DESC
    ")->fetch_all(MYSQLI_ASSOC);
    foreach ($boms as $b) {
        $out[(int) $b['progression_id']][] = $b;
    }
    if ($fallback) {
        foreach ($rows as $r) {
            $pid = (int) $r['id'];
            if (!empty($out[$pid])) {
                continue;
            }
            $st = $conn->prepare("
                SELECT id, progression_id, quotation_id, bom_number, date_needed, general_scope, created_at, approved_at, requested_by
                FROM noblecrm_bom
                WHERE quotation_id = ? AND approved_at IS NULL AND bom_number NOT LIKE 'TEMP-%'
                  AND (progression_id IS NULL OR progression_id = 0)
                ORDER BY id DESC LIMIT 1
            ");
            $qid = (int) $r['quotation_id'];
            $st->bind_param('i', $qid);
            $st->execute();
            $b = $st->get_result()->fetch_assoc();
            $st->close();
            if ($b) {
                $b['linked_by_job'] = 1;   // hindi direktang naka-link; nahanap sa job
                $out[$pid][] = $b;
            }
        }
    }
    $bomIds = [];
    foreach ($out as $list) {
        foreach ($list as $b) {
            $bomIds[] = (int) $b['id'];
        }
    }
    $items = [];
    if ($bomIds) {
        $res = $conn->query("SELECT bom_id, item_no, item_code, item_description, quantity, unit, supplier
                             FROM noblecrm_bom_items WHERE bom_id IN (" . implode(',', array_unique($bomIds)) . ") ORDER BY bom_id, item_no");
        foreach ($res->fetch_all(MYSQLI_ASSOC) as $it) {
            $items[(int) $it['bom_id']][] = $it;
        }
    }
    foreach ($out as &$list) {
        foreach ($list as &$b) {
            $b['id'] = (int) $b['id'];
            $b['items'] = $items[$b['id']] ?? [];
        }
    }
    return $out;
}

function ewoodUploadRow(array $row, array $bomsByUpload): array
{
    $photos = array_values(array_filter(array_map('trim', explode(',', $row['photos'] ?? ''))));
    return [
        'id' => (int) $row['id'],
        'quotation_id' => (int) $row['quotation_id'],
        'control_no' => $row['control_no'] ?? ('Upload #' . (int) $row['id']),
        'client_name' => $row['client_name'] ?? '(job not found)',
        'status' => $row['status'],
        'remarks' => $row['remarks'] ?? null,
        'created_at' => $row['created_at'] ?? null,
        'uploaded_by' => $row['uploaded_by_name'] ?? null,
        'archive_url' => !empty($row['archive_path']) ? BASE_URL . '/' . ltrim($row['archive_path'], '/') : null,
        'archive_name' => $row['archive_original_name'] ?? null,
        'approved_at' => $row['approved_at'] ?? null,
        'approved_by' => $row['approved_by_name'] ?? null,
        'photos' => array_map(fn($p) => BASE_URL . '/' . ltrim($p, '/'), $photos),
        'boms' => $bomsByUpload[(int) $row['id']] ?? [],
    ];
}

// ================= PENDING APPROVAL =================
// Lists cutting-progress rows that have at least one uploaded photo
// (expected to contain a QR code) and have not yet been QR-approved.
if ($action === 'pending_approval') {

    $stmt = $conn->prepare("
        SELECT p.*, i.control_no, i.client_name, u.name AS uploaded_by_name
        FROM noblecrm_cuttinglistprogression p
        LEFT JOIN noblecrm_2dquotation q ON q.id = p.quotation_id
        LEFT JOIN noblecrminquiry i ON i.id = q.inquiry_id
        LEFT JOIN noblerole u ON u.id = p.uploaded_by
        WHERE COALESCE(p.qr_approved, 0) = 0
          AND COALESCE(p.qr_rejected, 0) = 0
          AND p.photos IS NOT NULL AND p.photos != ''
        ORDER BY p.id ASC
    ");
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $boms = ewoodBomsForUploads($conn, $rows, true);
    $entries = [];
    foreach ($rows as $row) {
        $e = ewoodUploadRow($row, $boms);
        if ($e['photos']) {
            $entries[] = $e;
        }
    }

    echo json_encode(['success' => true, 'entries' => $entries]);
    exit;
}

// ================= APPROVED LIST =================
// History of everything that has already been QR-approved, shown in the
// second "Approved" container on ewood.php. Read-only — no actions here.
if ($action === 'approved_list') {

    $stmt = $conn->prepare("
        SELECT p.*, i.control_no, i.client_name, r.name AS approved_by_name, u.name AS uploaded_by_name
        FROM noblecrm_cuttinglistprogression p
        LEFT JOIN noblecrm_2dquotation q ON q.id = p.quotation_id
        LEFT JOIN noblecrminquiry i ON i.id = q.inquiry_id
        LEFT JOIN noblerole r ON r.id = p.approved_by
        LEFT JOIN noblerole u ON u.id = p.uploaded_by
        WHERE p.qr_approved = 1
        ORDER BY p.approved_at DESC, p.id DESC
        LIMIT 300
    ");
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $boms = ewoodBomsForUploads($conn, $rows, false);
    $entries = array_map(fn($row) => ewoodUploadRow($row, $boms), $rows);

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

    // Pinili ng Superadmin kung aling BOM ang iaapprove (kapag higit sa isa ang BOM ng upload).
    // Ang hindi napili ay ibinabalik sa Cutting para ayusin (kasama ang dahilan).
    // Walang bom_ids = lumang paraan: lahat ng BOM ng upload ay aaprubahan.
    $approveIds = null;
    $returned = [];       // id => bom_number
    $approvedNos = [];
    $remarks = trim((string) ($_POST['remarks'] ?? ''));
    if (isset($_POST['bom_ids'])) {
        $approveIds = array_values(array_unique(array_filter(array_map('intval', explode(',', (string) $_POST['bom_ids'])))));
        if (!$approveIds) {
            echo json_encode(['success' => false, 'message' => 'Select at least one BOM to approve, or use Send back.']);
            exit;
        }
        $st = $conn->prepare("
            SELECT id, bom_number FROM noblecrm_bom
            WHERE progression_id = ? AND approved_at IS NULL AND bom_number NOT LIKE 'TEMP-%'
        ");
        $st->bind_param('i', $progressId);
        $st->execute();
        $linked = [];
        foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
            $linked[(int) $r['id']] = (string) $r['bom_number'];
        }
        $st->close();

        foreach ($approveIds as $bid) {
            if (!isset($linked[$bid])) {
                echo json_encode(['success' => false, 'message' => 'The BOMs of this upload changed. Refresh the page and try again.']);
                exit;
            }
            $approvedNos[] = $linked[$bid];
        }
        $returned = array_diff_key($linked, array_flip($approveIds));
        if ($returned && mb_strlen($remarks) < 3) {
            echo json_encode(['success' => false, 'message' => 'Tell cutting what to fix in the BOM(s) you did not approve.']);
            exit;
        }
        if (mb_strlen($remarks) > 1000) {
            echo json_encode(['success' => false, 'message' => 'The reason is too long (max 1000 characters).']);
            exit;
        }
    }

    $conn->begin_transaction();
    try {
        // May naibalik na BOM → hindi pa tapos ang job (In Progress), para makapag-upload ulit ang Cutting
        $newStatus = $returned ? 'In Progress' : 'Completed';
        $stmt = $conn->prepare("
            UPDATE noblecrm_cuttinglistprogression
            SET qr_approved = 1, approved_by = ?, approved_at = NOW(), status = ?
            WHERE id = ? AND COALESCE(qr_approved, 0) = 0 AND COALESCE(qr_rejected, 0) = 0
        ");
        $stmt->bind_param('isi', $currentUserId, $newStatus, $progressId);
        $stmt->execute();
        $changed = $stmt->affected_rows;
        $stmt->close();

        if ($changed !== 1) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'This submission was already approved or sent back for revision. Refresh the page.']);
            exit;
        }

        if ($approveIds === null) {
            $bomUpdated = ewoodBomMarkApproved($conn, $progressId, $currentUserId);
        } else {
            // Pirma sa mga napiling BOM lang
            $approver = ewoodBomApproverInfo($conn, $currentUserId);
            $sign = $conn->prepare("
                UPDATE noblecrm_bom
                SET approved_by = ?, approved_by_title = ?, approved_by_signature_path = ?, approved_at = NOW()
                WHERE id = ? AND progression_id = ? AND approved_at IS NULL
            ");
            $bomUpdated = 0;
            foreach ($approveIds as $bid) {
                $sign->bind_param('sssii', $approver['name'], $approver['title'], $approver['signature_path'], $bid, $progressId);
                $sign->execute();
                $bomUpdated += $sign->affected_rows;
            }
            $sign->close();

            // Ang hindi napili: tanggalin sa upload na ito → lalabas ulit sa Cutting para ayusin (parehong BOM number)
            if ($returned) {
                if (ewoodBomHasReturnCols($conn)) {
                    $ret = $conn->prepare("
                        UPDATE noblecrm_bom
                        SET progression_id = NULL, returned_at = NOW(), returned_by = ?, return_remarks = ?
                        WHERE id = ? AND approved_at IS NULL
                    ");
                    foreach (array_keys($returned) as $bid) {
                        $ret->bind_param('isi', $currentUserId, $remarks, $bid);
                        $ret->execute();
                    }
                } else {
                    $ret = $conn->prepare("UPDATE noblecrm_bom SET progression_id = NULL WHERE id = ? AND approved_at IS NULL");
                    foreach (array_keys($returned) as $bid) {
                        $ret->bind_param('i', $bid);
                        $ret->execute();
                    }
                }
                $ret->close();
            }
        }
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }

    // Abiso sa Cutting na nag-upload
    if ($returned) {
        ewoodNotifyUploader($conn, $progressId, $currentUserId, 'partial', implode(', ', $returned) . ' — ' . $remarks);
    } else {
        ewoodNotifyUploader($conn, $progressId, $currentUserId, 'approved');
    }
    ewoodRealtime($conn, $progressId, 'crm_ewood_approved', true);

    echo json_encode([
        'success' => true,
        'message' => $returned ? 'Approved. Some BOMs were sent back.' : 'Approved.',
        'bom_updated' => $bomUpdated,
        'approved_boms' => $approvedNos,
        'returned_boms' => array_values($returned),
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

    if (mb_strlen($remarks) > 1000) {
        echo json_encode(['success' => false, 'message' => 'The reason is too long (max 1000 characters).']);
        exit;
    }

    // I-save muna ang revision; saka mag-notify (hindi na kailangan ng archive/photos ng notification)
    $stmt = $conn->prepare("
        UPDATE noblecrm_cuttinglistprogression
        SET qr_rejected = 1, rejection_remarks = ?, rejected_by = ?, rejected_at = NOW(),
            archive_path = NULL, archive_original_name = NULL, photos = '', status = 'Pending'
        WHERE id = ? AND COALESCE(qr_approved, 0) = 0 AND COALESCE(qr_rejected, 0) = 0
    ");
    $stmt->bind_param('sii', $remarks, $currentUserId, $progressId);
    $stmt->execute();
    $changed = $stmt->affected_rows;
    $stmt->close();

    if ($changed !== 1) {
        echo json_encode(['success' => false, 'message' => 'This submission was already approved or sent back for revision. Refresh the page.']);
        exit;
    }

    ewoodNotifyUploader($conn, $progressId, $currentUserId, 'rejected', $remarks);
    ewoodRealtime($conn, $progressId, 'crm_ewood_rejected', false);

    echo json_encode(['success' => true, 'message' => 'Rejected.']);
    exit;
}

// ================= BOM: LIST =================
// Lets the Superadmin see which BOM(s) exist for this quotation while
// reviewing a pending QR approval. Read-only — creation stays with cutting.
if ($action === 'bom_list') {

    $quotationId = intval($_GET['quotation_id'] ?? 0);
    $progressId = intval($_GET['progress_id'] ?? 0);
    if ($quotationId <= 0 && $progressId <= 0) {
        echo json_encode(['success' => false, 'message' => 'This submission has no quotation linked, so its BOM cannot be found.']);
        exit;
    }

    echo json_encode(['success' => true, 'entries' => ewoodBomFetchList($conn, $quotationId, $progressId)]);
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