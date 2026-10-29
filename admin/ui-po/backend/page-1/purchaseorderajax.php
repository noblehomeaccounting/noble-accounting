<?php
// purchaseorderajax.php  —  Purchase Order: approved BOMs, receive, view, pdf

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PO];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

require_once ROOT_PATH . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

include_once ROOT_PATH . '/network/po-bom-group.php';   // pagsasama ng mga BOM na sabay ginawa sa Cutting

$currentUserId = intval($_SESSION['account_id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function poJson(array $data): void
{
    header('Content-Type: application/json');
    header('Cache-Control: no-store');
    echo json_encode($data);
    exit;
}

// Pangalan, titulo at aktibong pirma ng naka-login na PO
// (parehong lookup ng bomajax.php / ewoodapprovalajax.php)
function poSignerInfo(mysqli $conn, int $userId): array
{
    $result = ['name' => '', 'title' => '', 'signature_path' => null];
    if ($userId <= 0) return $result;

    $stmt = $conn->prepare("SELECT name, position, active_signature_id FROM noblerole WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$user) return $result;

    $result['name']  = $user['name'] ?? '';
    $result['title'] = ucwords(str_replace('_', ' ', $user['position'] ?? ''));

    $signaturePath = null;
    if (!empty($user['active_signature_id'])) {
        $s = $conn->prepare("SELECT path FROM noblesignature WHERE id = ? AND user_id = ? LIMIT 1");
        $s->bind_param('ii', $user['active_signature_id'], $userId);
        $s->execute();
        $r = $s->get_result()->fetch_assoc();
        $s->close();
        if ($r) $signaturePath = $r['path'];
    }
    if ($signaturePath === null) {
        $s = $conn->prepare("SELECT path FROM noblesignature WHERE user_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1");
        $s->bind_param('i', $userId);
        $s->execute();
        $r = $s->get_result()->fetch_assoc();
        $s->close();
        if ($r) $signaturePath = $r['path'];
    }

    $result['signature_path'] = $signaturePath;
    return $result;
}

function poFetchBom(mysqli $conn, int $bomId): ?array
{
    $stmt = $conn->prepare("SELECT * FROM noblecrm_bom WHERE id = ? LIMIT 1");
    $stmt->bind_param('i', $bomId);
    $stmt->execute();
    $bom = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$bom) return null;

    $it = $conn->prepare("SELECT * FROM noblecrm_bom_items WHERE bom_id = ? ORDER BY item_no ASC");
    $it->bind_param('i', $bomId);
    $it->execute();
    $bom['items'] = $it->get_result()->fetch_all(MYSQLI_ASSOC);
    $it->close();

    return $bom;
}

function poEsc(?string $v): string
{
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}

function poSigDataUri(?string $relPath): ?string
{
    if (!$relPath) return null;
    $abs = ROOT_PATH . '/' . ltrim($relPath, '/');
    if (!is_file($abs)) return null;
    $mime = @mime_content_type($abs) ?: 'image/png';
    return "data:{$mime};base64," . base64_encode(file_get_contents($abs));
}

// Pangalan na may pirma sa ibabaw (parehong markup na gumagana sa dompdf)
function poSigCell(?string $name, ?string $sigPath): string
{
    $uri = poSigDataUri($sigPath);
    return '<div style="position:relative;height:46px;">'
        . '<div style="position:relative;z-index:1;padding-top:26px;">' . poEsc($name) . '</div>'
        . ($uri
            ? '<div style="position:absolute;top:-6px;left:0;right:0;text-align:center;z-index:2;">'
            . '<img src="' . $uri . '" style="max-height:50px;max-width:160px;"></div>'
            : '')
        . '</div>';
}

function poRenderHtml(array $bom): string
{
    $rows = '';
    foreach ($bom['items'] as $i => $it) {
        $rows .= '<tr>'
            . '<td class="c">' . ($i + 1) . '</td>'
            . '<td>' . poEsc($it['item_code']) . '</td>'
            . '<td>' . poEsc($it['item_description']) . '</td>'
            . '<td class="c">' . rtrim(rtrim(number_format((float) $it['quantity'], 2, '.', ''), '0'), '.') . '</td>'
            . '<td class="c">' . poEsc($it['unit']) . '</td>'
            . '<td>' . poEsc($it['supplier']) . '</td>'
            . '</tr>';
    }
    for ($i = count($bom['items']); $i < 6; $i++) {
        $rows .= '<tr><td class="c">&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td></tr>';
    }

    $dateSubmitted = date('d/m/Y', strtotime($bom['date_submitted']));

    return '
    <html><head><meta charset="UTF-8"><style>
        @page { margin: 18px; }
        body { font-family: Helvetica, Arial, sans-serif; font-size: 10px; color:#111; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td, th { border: 1px solid #000; padding: 7px 8px; vertical-align: middle; word-wrap: break-word; }
        .c { text-align: center; }
        .label { font-size: 8px; font-weight: bold; text-transform: uppercase; color:#333; background-color:#d9d9d9; }
        .title { font-size: 20px; font-weight: 900; text-align: center; letter-spacing: 0.5px; white-space: nowrap; }
        .client { font-size: 15px; font-weight: bold; text-align: center; }
        .nothing-follows { text-align:center; color:#b00; font-weight:bold; font-style:italic; padding: 8px; }
        .sig-name { font-weight: bold; text-align:center; }
        .sig-title { font-style: italic; text-align:center; font-size: 8px; }
        .footer { text-align:center; font-size: 7px; padding-top: 4px; }
    </style></head><body>

    <table>
        <tr>
            <td style="width:8%">&nbsp;</td>
            <td class="title" style="width:62%">BILL OF MATERIALS</td>
            <td class="label c" style="width:8%">PAGE</td>
            <td class="label c" style="width:22%">DATE SUBMITTED</td>
        </tr>
        <tr>
            <td>&nbsp;</td><td>&nbsp;</td>
            <td class="c">' . (int) $bom['page'] . '</td>
            <td class="c">' . poEsc($dateSubmitted) . '</td>
        </tr>
    </table>

    <table style="margin-top:-1px;">
        <tr>
            <td class="label" style="width:20%">J.O NUMBER:</td>
            <td class="label c" colspan="2" style="width:58%">CLIENT</td>
            <td class="label c" style="width:22%">DATE NEEDED</td>
        </tr>
        <tr>
            <td>' . poEsc($bom['bom_number']) . '</td>
            <td class="client" colspan="2">' . poEsc($bom['client_name']) . '</td>
            <td class="c">' . poEsc($bom['date_needed']) . '</td>
        </tr>
    </table>

    <table style="margin-top:-1px;">
        <tr>
            <td class="label" style="width:10%">SCOPE:</td>
            <td class="label">' . poEsc($bom['general_scope']) . '</td>
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
            <td class="sig-name">' . poSigCell($bom['requested_by'], $bom['requested_by_signature_path'] ?? null) . '</td>
            <td class="sig-name">' . poEsc($bom['noted_by']) . '</td>
            <td class="sig-name">' . poSigCell($bom['received_by'], $bom['received_by_signature_path'] ?? null) . '</td>
            <td class="sig-name">' . poSigCell($bom['approved_by'], $bom['approved_by_signature_path'] ?? null) . '</td>
        </tr>
        <tr>
            <td class="sig-title">' . poEsc($bom['requested_by_title']) . '</td>
            <td class="sig-title">' . poEsc($bom['noted_by_title']) . '</td>
            <td class="sig-title">' . poEsc($bom['received_by_title']) . '</td>
            <td class="sig-title">' . poEsc($bom['approved_by_title']) . '</td>
        </tr>
        <tr><td colspan="4" class="footer">PROPERTY OF NOBLEHOME CONSTRUCTION</td></tr>
    </table>

    </body></html>';
}

// ================= LIST =================
// Lahat ng BOM na na-approve na ng Superadmin (approved_at IS NOT NULL).
// Ang mga BOM na sabay ginawa sa Cutting (iisang upload) ay iisang row: isang Receive, isang PR, isang Transmittal.
if ($action === 'list') {

    $prOf = poPrOfBomSql($conn, 'b.id');
    $groupKey = poGroupKeySql('b');
    $sql = "
        SELECT b.id, $groupKey AS group_key, b.bom_number, b.client_name, b.date_submitted, b.date_needed, b.general_scope,
               b.requested_by, b.approved_by, b.approved_at,
               b.received_by, b.received_at,
               i.control_no,
               pr.id AS pr_id, pr.req_no AS pr_req_no, pr.status AS pr_status, pr.reviewed_at AS pr_reviewed_at,
               pr.staff_received_at AS pr_staff_received_at, pr.released_at AS pr_released_at, pr.paid_at AS pr_paid_at,
               (SELECT t.id FROM nobletransmittal t WHERE t.pr_id = pr.id LIMIT 1) AS tr_id,
               (SELECT COUNT(*) FROM noblecrm_bom_items it WHERE it.bom_id = b.id) AS item_count,
               (b.received_at IS NOT NULL AND DATE(b.received_at) = CURDATE())      AS received_today
        FROM noblecrm_bom b
        LEFT JOIN noblecrm_2dquotation q ON q.id = b.quotation_id
        LEFT JOIN noblecrminquiry i      ON i.id = q.inquiry_id
        LEFT JOIN noblepurchaserequest pr ON pr.id = $prOf
        WHERE b.approved_at IS NOT NULL AND b.bom_number NOT LIKE 'TEMP-%'
        ORDER BY b.id ASC
    ";
    $bomRows = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);

    // Pagsamahin ayon sa grupo. Ang "lead" (unang BOM) ang id ng row.
    $groups = [];
    foreach ($bomRows as $b) {
        $k = $b['group_key'];
        $prId = $b['pr_id'] !== null ? (int) $b['pr_id'] : null;
        if (!isset($groups[$k])) {
            $groups[$k] = $b + ['boms' => [], 'pr_ids' => [], 'tr_ids' => [], '_all_received' => true, '_any_today' => false, '_pr_row' => null];
            $groups[$k]['item_count'] = 0;
            $groups[$k]['date_needed_list'] = [];
        }
        $g = &$groups[$k];
        $g['boms'][] = ['id' => (int) $b['id'], 'bom_number' => $b['bom_number'], 'item_count' => (int) $b['item_count'],
                        'date_needed' => $b['date_needed'], 'received_at' => $b['received_at']];
        $g['item_count'] += (int) $b['item_count'];
        if ($b['date_needed'] !== null && $b['date_needed'] !== '') $g['date_needed_list'][] = $b['date_needed'];
        if (empty($b['received_at'])) $g['_all_received'] = false;
        else if ($b['received_at'] > ($g['received_at'] ?? '')) { $g['received_at'] = $b['received_at']; $g['received_by'] = $b['received_by']; }
        if ((int) $b['received_today'] === 1) $g['_any_today'] = true;
        if ($prId !== null) {
            $g['pr_ids'][$prId] = true;
            if ($b['tr_id'] !== null) $g['tr_ids'][(int) $b['tr_id']] = true;
            // pinakabagong PR ng grupo ang susundan
            if ($g['_pr_row'] === null || $prId > (int) $g['_pr_row']['pr_id']) $g['_pr_row'] = $b;
        }
        unset($g);
    }

    $rows = [];
    foreach ($groups as $g) {
        $p = $g['_pr_row'];
        $rows[] = [
            'id'              => (int) $g['boms'][0]['id'],
            'bom_ids'         => array_column($g['boms'], 'id'),
            'boms'            => $g['boms'],
            'bom_number'      => implode(', ', array_column($g['boms'], 'bom_number')),
            'client_name'     => $g['client_name'],
            'control_no'      => $g['control_no'],
            'date_submitted'  => $g['date_submitted'],
            'date_needed'     => implode(' / ', array_unique($g['date_needed_list'])),
            'general_scope'   => $g['general_scope'],
            'requested_by'    => $g['requested_by'],
            'approved_by'     => $g['approved_by'],
            'approved_at'     => $g['approved_at'],
            'received_by'     => $g['_all_received'] ? $g['received_by'] : null,
            'received_at'     => $g['_all_received'] ? $g['received_at'] : null,
            'is_received'     => $g['_all_received'],
            'received_today'  => $g['_all_received'] && $g['_any_today'],
            'item_count'      => $g['item_count'],
            'pr_id'           => $p ? (int) $p['pr_id'] : null,
            'pr_req_no'       => $p['pr_req_no'] ?? null,
            'pr_status'       => $p['pr_status'] ?? null,
            'pr_reviewed_at'  => $p['pr_reviewed_at'] ?? null,
            'pr_staff_received_at' => $p['pr_staff_received_at'] ?? null,
            'pr_released_at'  => $p['pr_released_at'] ?? null,
            'pr_paid_at'      => $p['pr_paid_at'] ?? null,
            'tr_id'           => $p && $p['tr_id'] !== null ? (int) $p['tr_id'] : null,
            'pr_count'        => count($g['pr_ids']),   // >1 = luma: magkahiwalay na PR bago pa ang pagsasama
        ];
    }
    // dating ayos: hindi pa na-receive muna, tapos pinakabagong na-receive / na-approve
    usort($rows, fn($a, $b) => [(int) $a['is_received'], $b['received_at'] ?? '', $b['approved_at'] ?? '', $b['id']]
                            <=> [(int) $b['is_received'], $a['received_at'] ?? '', $a['approved_at'] ?? '', $a['id']]);

    poJson(['success' => true, 'rows' => $rows]);
}

// ================= RECEIVE =================
// Inilalagay ang pangalan/titulo/pirma ng PO sa RECEIVED BY
if ($action === 'receive') {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        poJson(['success' => false, 'message' => 'Invalid request.']);
    }

    $bomId = intval($_POST['bom_id'] ?? 0);
    if ($bomId <= 0) {
        poJson(['success' => false, 'message' => 'Invalid BOM.']);
    }

    $signer = poSignerInfo($conn, $currentUserId);
    if ($signer['name'] === '') {
        poJson(['success' => false, 'message' => 'Your account could not be found. Please log in again.']);
    }

    // Buong grupo (lahat ng BOM na sabay ginawa sa Cutting) ang ire-receive.
    // Approved lang, at hindi pa na-receive (iwas doble kapag sabay na pinindot)
    $groupIds = poBomGroupIds($conn, $bomId) ?: [$bomId];
    $stmt = $conn->prepare("
        UPDATE noblecrm_bom
        SET received_by = ?, received_by_title = ?, received_by_signature_path = ?,
            received_by_id = ?, received_at = NOW()
        WHERE id = ? AND approved_at IS NOT NULL AND received_at IS NULL
    ");
    $affected = 0;
    foreach ($groupIds as $gid) {
        $stmt->bind_param('sssii', $signer['name'], $signer['title'], $signer['signature_path'], $currentUserId, $gid);
        $stmt->execute();
        $affected += max(0, $stmt->affected_rows);
    }
    $stmt->close();

    if ($affected < 1) {
        poJson(['success' => false, 'message' => 'This BOM was already received or is not yet approved. Refreshing the list.']);
    }

    // Realtime: ibang PO (listahan + badge) at Superadmin + Sales/Designer ng inquiry
    try {
        include_once ROOT_PATH . '/network/realtime-helper.php';
        $q = $conn->prepare("SELECT quotation_id FROM noblecrm_bom WHERE id = ? LIMIT 1");
        $q->bind_param('i', $bomId);
        $q->execute();
        $qid = (int) ($q->get_result()->fetch_assoc()['quotation_id'] ?? 0);
        $q->close();
        nhRtQuotation($conn, $qid, [[ROLE_PO, null], [ROLE_SUPERADMIN, null]], 'crm_po_receive');
        if ($qid <= 0) {
            nhRealtimeNotifyGroup(ROLE_PO, null, 'changed', ['type' => 'crm_po_receive']);
        }
    } catch (Throwable $e) {
        error_log('po receive realtime: ' . $e->getMessage());
    }

    poJson([
        'success'       => true,
        'message'       => $affected > 1 ? "$affected BOMs received." : 'BOM received.',
        'count'         => $affected,
        'has_signature' => $signer['signature_path'] !== null,
    ]);
}

// ================= VIEW =================
// Mabubuksan lang kapag na-receive na
if ($action === 'view') {

    $bomId = intval($_GET['bom_id'] ?? 0);
    $bom = $bomId > 0 ? poFetchBom($conn, $bomId) : null;

    if (!$bom || empty($bom['approved_at'])) {
        poJson(['success' => false, 'message' => 'BOM not found.']);
    }
    if (empty($bom['received_at'])) {
        poJson(['success' => false, 'message' => 'Receive this BOM first to view it.']);
    }

    poJson(['success' => true, 'bom' => $bom]);
}

// ================= HISTORY =================
// Status History ng BOM + lahat ng Purchase Request nito (sino na ang pumirma)
if ($action === 'history') {

    $bomId = intval($_GET['bom_id'] ?? 0);
    $st = $conn->prepare("
        SELECT id, bom_number, client_name, date_submitted, created_at,
               requested_by, requested_by_title, requested_by_signature_path,
               approved_by, approved_by_title, approved_by_signature_path, approved_at,
               received_by, received_by_title, received_by_signature_path, received_at
        FROM noblecrm_bom WHERE id = ? AND approved_at IS NOT NULL LIMIT 1
    ");
    $st->bind_param('i', $bomId);
    $st->execute();
    $bom = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$bom) poJson(['success' => false, 'message' => 'BOM not found.']);

    // Lahat ng PR ng grupo (lead man o kasama lang ang BOM)
    $groupIds = poBomGroupIds($conn, $bomId) ?: [$bomId];
    $gIn = implode(',', array_map('intval', $groupIds));
    $prIds = array_column($conn->query("SELECT id FROM noblepurchaserequest WHERE bom_id IN ($gIn)"
        . (poHasPrLink($conn) ? " UNION SELECT pr_id FROM noblepurchaserequest_bom WHERE bom_id IN ($gIn)" : ''))->fetch_all(MYSQLI_ASSOC), 'id');
    $prIn = $prIds ? implode(',', array_map('intval', $prIds)) : '0';
    $bom['group_boms'] = $conn->query("SELECT id, bom_number FROM noblecrm_bom WHERE id IN ($gIn) ORDER BY id")->fetch_all(MYSQLI_ASSOC);

    $pq = $conn->prepare("
        SELECT id, req_no, status, created_at, total_amount,
               prepared_by, prepared_by_title, prepared_by_signature_path,
               approved_by, approved_by_title, approved_by_signature_path, approved_at,
               rejection_remarks, rejected_at,
               reviewed_by, reviewed_by_title, reviewed_by_signature_path, reviewed_at,
               staff_received_by, staff_received_at,
               released_by, released_by_title, released_by_signature_path, released_at,
               paid_at,
               (SELECT t.ref_no FROM nobletransmittal t WHERE t.pr_id = noblepurchaserequest.id LIMIT 1)     AS tr_ref_no,
               (SELECT t.created_at FROM nobletransmittal t WHERE t.pr_id = noblepurchaserequest.id LIMIT 1) AS tr_created_at,
               (SELECT t.issued_by FROM nobletransmittal t WHERE t.pr_id = noblepurchaserequest.id LIMIT 1)  AS tr_issued_by,
               (SELECT t.issued_by_signature_path FROM nobletransmittal t WHERE t.pr_id = noblepurchaserequest.id LIMIT 1) AS tr_issued_sig
        FROM noblepurchaserequest WHERE id IN ($prIn) ORDER BY id ASC
    ");
    $pq->execute();
    $prs = $pq->get_result()->fetch_all(MYSQLI_ASSOC);
    $pq->close();

    poJson(['success' => true, 'bom' => $bom, 'prs' => $prs]);
}

// ================= PDF =================
if ($action === 'pdf') {

    $bomId = intval($_GET['bom_id'] ?? 0);
    $bom = $bomId > 0 ? poFetchBom($conn, $bomId) : null;

    if (!$bom || empty($bom['approved_at']) || empty($bom['received_at'])) {
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
    $dompdf->loadHtml(poRenderHtml($bom));
    $dompdf->render();
    $dompdf->stream($bom['bom_number'] . '.pdf', ['Attachment' => true]);
    exit;
}

poJson(['success' => false, 'message' => 'Unknown action.']);