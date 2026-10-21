<?php
// purchaserequestajax.php  —  Purchase Request Form: create, list, view

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PO];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$currentUserId = intval($_SESSION['account_id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

const PR_IMG_MIME = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
const PR_IMG_MAX  = 5 * 1024 * 1024; // 5MB bawat larawan
const PR_APPROVAL_LINK = '/purchaseorderapproval'; // route ng PurchaseOrder.php (Superadmin)

function prOut(array $d): void
{
    echo json_encode($d);
    exit;
}

// Pangalan, titulo at aktibong pirma ng naka-login (para sa "Prepared By")
function prSignerInfo(mysqli $conn, int $userId): array
{
    $out = ['name' => '', 'title' => '', 'signature_path' => null];
    if ($userId <= 0) return $out;

    $st = $conn->prepare("SELECT name, position, active_signature_id FROM noblerole WHERE id = ? LIMIT 1");
    $st->bind_param('i', $userId);
    $st->execute();
    $u = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$u) return $out;

    $out['name']  = $u['name'] ?? '';
    $out['title'] = 'Purchasing';

    $path = null;
    if (!empty($u['active_signature_id'])) {
        $s = $conn->prepare("SELECT path FROM noblesignature WHERE id = ? AND user_id = ? LIMIT 1");
        $s->bind_param('ii', $u['active_signature_id'], $userId);
        $s->execute();
        $r = $s->get_result()->fetch_assoc();
        $s->close();
        if ($r) $path = $r['path'];
    }
    if ($path === null) {
        $s = $conn->prepare("SELECT path FROM noblesignature WHERE user_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1");
        $s->bind_param('i', $userId);
        $s->execute();
        $r = $s->get_result()->fetch_assoc();
        $s->close();
        if ($r) $path = $r['path'];
    }
    $out['signature_path'] = $path;
    return $out;
}

function prSaveImage(array $file, int $prId): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Image upload failed.');
    if ($file['size'] > PR_IMG_MAX) throw new RuntimeException('Each item picture must be 5MB or less.');

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(PR_IMG_MIME[$mime])) throw new RuntimeException('Item picture must be JPG, PNG, WEBP or GIF.');

    $dir = ROOT_PATH . '/uploads/purchase_request/' . $prId;
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $name = uniqid('item_', true) . '.' . PR_IMG_MIME[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $name)) {
        throw new RuntimeException('Could not save an item picture.');
    }
    return 'uploads/purchase_request/' . $prId . '/' . $name;
}

// Unang letra ng bawat salita ng client name, hal. "Noblehome Construction Corporation" → "NCC"
function prClientInitials(string $client): string
{
    $words = preg_split('/[^\p{L}\p{N}]+/u', $client, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $out = '';
    foreach ($words as $w) $out .= mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8');
    $out = preg_replace('/[^A-Z0-9]/', '', strtr($out, ['Ñ' => 'N']));
    return substr($out, 0, 10) ?: 'PR';
}

// ================= CREATE =================
if ($action === 'create') {

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') prOut(['success' => false, 'message' => 'Invalid request.']);

    $scope        = trim($_POST['project_scope'] ?? '');
    $requestDate  = trim($_POST['request_date'] ?? '');
    $dateNeeded   = trim($_POST['date_needed'] ?? '') ?: 'ASAP';
    $bomId        = intval($_POST['bom_id'] ?? 0);

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $requestDate)) prOut(['success' => false, 'message' => 'Request date is invalid.']);

    // Requested by = ang gumawa ng BOM (na-receive na)
    $rq = $conn->prepare("SELECT client_name, requested_by, requested_by_signature_path FROM noblecrm_bom
                          WHERE id = ? AND received_at IS NOT NULL LIMIT 1");
    $rq->bind_param('i', $bomId);
    $rq->execute();
    $rqRow = $rq->get_result()->fetch_assoc();
    $rq->close();
    if (!$rqRow) prOut(['success' => false, 'message' => 'Open this form from a received BOM so the requester can be filled in.']);
    // Client = client ng BOM (hindi na tina-type, para pare-pareho sa BOM, PR at Transmittal)
    $client = trim((string) ($rqRow['client_name'] ?? ''));
    if ($client === '') prOut(['success' => false, 'message' => 'This BOM has no client name. Please check the BOM first.']);
    // Bawal nang gumawa ng bagong Purchase Request kapag PAID na ang PR ng BOM na ito
    $pd = $conn->prepare("SELECT req_no FROM noblepurchaserequest WHERE bom_id = ? AND paid_at IS NOT NULL LIMIT 1");
    $pd->bind_param('i', $bomId);
    $pd->execute();
    $paidRow = $pd->get_result()->fetch_assoc();
    $pd->close();
    if ($paidRow) prOut(['success' => false, 'message' => "Purchase request {$paidRow['req_no']} for this BOM is already paid. A new one can no longer be made."]);

    $requestedBy    = $rqRow['requested_by'] ?? '';
    $requestedBySig = $rqRow['requested_by_signature_path'] ?? null;

    // Items — naka-index para tumugma ang item_picture_{i}
    $names   = $_POST['item_name'] ?? [];
    $qtys    = $_POST['quantity'] ?? [];
    $units   = $_POST['unit'] ?? [];
    $prices  = $_POST['price'] ?? [];
    $remarks = $_POST['remarks'] ?? [];
    $rowIdx  = $_POST['row_index'] ?? [];

    $items = [];
    foreach ($names as $i => $n) {
        $n = trim($n);
        if ($n === '') continue;
        $q = max(0, (float) ($qtys[$i] ?? 0));
        $p = max(0, (float) ($prices[$i] ?? 0));
        $items[] = [
            'name'    => $n,
            'qty'     => $q,
            'unit'    => trim($units[$i] ?? ''),
            'price'   => $p,
            'amount'  => round($q * $p, 2),
            'remarks' => trim($remarks[$i] ?? ''),
            'file'    => $_FILES['item_picture_' . intval($rowIdx[$i] ?? -1)] ?? null,
        ];
    }
    if (!$items) prOut(['success' => false, 'message' => 'Add at least one item.']);

    $total  = round(array_sum(array_column($items, 'amount')), 2);
    $signer = prSignerInfo($conn, $currentUserId);

    $conn->begin_transaction();
    $savedFiles = [];
    try {
        // Req No: <taon><initials ng client>-<5 digit na sunod-sunod kada prefix>
        // hal. "Noblehome Construction Corporation" → 2026NCC-00001
        $prefix = date('Y', strtotime($requestDate)) . prClientInitials($client) . '-';
        $sq = $conn->prepare("
            SELECT COALESCE(MAX(CAST(SUBSTRING(req_no, ?) AS UNSIGNED)), 0) AS last_no
            FROM noblepurchaserequest
            WHERE req_no LIKE CONCAT(?, '%')
            FOR UPDATE
        ");
        $startPos = strlen($prefix) + 1;
        $sq->bind_param('is', $startPos, $prefix);
        $sq->execute();
        $lastNo = (int) ($sq->get_result()->fetch_assoc()['last_no'] ?? 0);
        $sq->close();
        $reqNo = $prefix . str_pad((string) ($lastNo + 1), 5, '0', STR_PAD_LEFT);

        $st = $conn->prepare("
            INSERT INTO noblepurchaserequest
                (req_no, bom_id, client, project_scope, request_date, date_needed, requested_by, requested_by_signature_path,
                 prepared_by, prepared_by_title, prepared_by_signature_path, total_amount, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $st->bind_param('sisssssssssdi', $reqNo, $bomId, $client, $scope, $requestDate, $dateNeeded, $requestedBy, $requestedBySig,
            $signer['name'], $signer['title'], $signer['signature_path'], $total, $currentUserId);
        $st->execute();
        $prId = $st->insert_id;
        $st->close();

        $it = $conn->prepare("
            INSERT INTO noblepurchaserequest_items
                (pr_id, item_no, item_name, picture_path, quantity, unit, price, amount, remarks)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        foreach ($items as $k => $row) {
            $pic = $row['file'] ? prSaveImage($row['file'], $prId) : null;
            if ($pic) $savedFiles[] = ROOT_PATH . '/' . $pic;
            $no = $k + 1;
            $it->bind_param('iissdsdds', $prId, $no, $row['name'], $pic, $row['qty'], $row['unit'], $row['price'], $row['amount'], $row['remarks']);
            $it->execute();
        }
        $it->close();

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        foreach ($savedFiles as $f) @unlink($f);
        $msg = $e instanceof RuntimeException ? $e->getMessage() : 'Could not save the purchase request.';
        prOut(['success' => false, 'message' => $msg]);
    }

    // Abisuhan ang Superadmin (CEO) na may bagong request na aaprubahan
    try {
        $roleSa = ROLE_SUPERADMIN;
        $sa = $conn->prepare("SELECT id FROM noblerole WHERE role = ?");
        $sa->bind_param('s', $roleSa);
        $sa->execute();
        $saIds = array_column($sa->get_result()->fetch_all(MYSQLI_ASSOC), 'id');
        $sa->close();

        $msg  = "New purchase request {$reqNo} — {$client} is waiting for your approval.";
        $link = PR_APPROVAL_LINK;
        $n = $conn->prepare("
            INSERT INTO noblenotification (user_id, request_id, control_no, type, message, is_read, created_at, sender_id, link)
            VALUES (?, ?, ?, 'crm', ?, 0, NOW(), ?, ?)
        ");
        foreach ($saIds as $saId) {
            $saId = (int) $saId;
            $n->bind_param('iissis', $saId, $prId, $reqNo, $msg, $currentUserId, $link);
            $n->execute();
        }
        $n->close();
    } catch (Throwable $e) { /* hindi dapat pigilan ng notification ang pag-save */ }

    prOut(['success' => true, 'id' => $prId, 'req_no' => $reqNo]);
}

// ================= LIST =================
if ($action === 'list') {
    $rows = $conn->query("
        SELECT p.id, p.req_no, p.client, p.project_scope, p.request_date, p.date_needed,
               p.total_amount, p.prepared_by, p.created_at, p.status, p.approved_at, p.reviewed_at, p.staff_received_at, p.released_at, p.paid_at,
               (SELECT COUNT(*) FROM noblepurchaserequest_items i WHERE i.pr_id = p.id) AS item_count
        FROM noblepurchaserequest p
        ORDER BY p.id DESC
    ")->fetch_all(MYSQLI_ASSOC);
    prOut(['success' => true, 'rows' => $rows]);
}

// ================= VIEW =================
if ($action === 'view') {
    $id = intval($_GET['id'] ?? 0);
    $st = $conn->prepare("SELECT * FROM noblepurchaserequest WHERE id = ? LIMIT 1");
    $st->bind_param('i', $id);
    $st->execute();
    $pr = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$pr) prOut(['success' => false, 'message' => 'Purchase request not found.']);

    $it = $conn->prepare("SELECT * FROM noblepurchaserequest_items WHERE pr_id = ? ORDER BY item_no ASC");
    $it->bind_param('i', $id);
    $it->execute();
    $pr['items'] = $it->get_result()->fetch_all(MYSQLI_ASSOC);
    $it->close();

    prOut(['success' => true, 'pr' => $pr]);
}

prOut(['success' => false, 'message' => 'Unknown action.']);