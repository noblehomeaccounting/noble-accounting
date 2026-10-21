<?php
// coordenatorajax.php  —  Project Coordinator: Delivery Receipt (DR)
// Makakagawa lang ng DR kapag OK na ang transmittal (na-mark na Delivered sa Transmittal Summary).

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_PC];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$currentUserId = intval($_SESSION['account_id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

// huwag i-lock ang session habang tumatakbo ang request (iwas "stuck" sa loading)
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

// DR No: <taon>06-<sunod-sunod, 3 digit>, hal. 202606-001 (nagre-reset kada taon)
const DR_NO_MIDDLE = '06';

function pcOut(array $d): void
{
    echo json_encode($d);
    exit;
}

set_exception_handler(function (Throwable $e) {
    error_log('[coordenatorajax] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
});

function pcAll(mysqli $conn, string $sql, string $types = '', array $args = []): array
{
    $st = $conn->prepare($sql);
    if ($types !== '') $st->bind_param($types, ...$args);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $rows;
}
function pcOne(mysqli $conn, string $sql, string $types = '', array $args = []): ?array
{
    $r = pcAll($conn, $sql, $types, $args);
    return $r[0] ?? null;
}
function pcStr(string $key, int $max = 255): string
{
    return mb_substr(trim((string) ($_POST[$key] ?? '')), 0, $max);
}
function pcDate(?string $d): ?string
{
    $d = trim((string) $d);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) ? $d : null;
}
function pcUserName(mysqli $conn, int $uid): string
{
    $r = pcOne($conn, "SELECT name FROM noblerole WHERE id = ? LIMIT 1", 'i', [$uid]);
    return $r['name'] ?? '';
}
function pcTransmittal(mysqli $conn, int $id): ?array
{
    return pcOne($conn, "
        SELECT t.id, t.ref_no, t.transmittal_date, t.project_name, t.client_name, t.client_company, t.client_address,
               t.particulars, t.delivery_mode, t.delivered_at, p.req_no
        FROM nobletransmittal t
        LEFT JOIN noblepurchaserequest p ON p.id = t.pr_id
        WHERE t.id = ? LIMIT 1", 'i', [$id]);
}

// ================= LIST: lahat ng transmittal + kung OK na at kung may DR na =================
if ($action === 'list') {
    $rows = pcAll($conn, "
        SELECT t.id, t.ref_no, t.transmittal_date, t.project_name, t.client_name, t.client_company,
               t.particulars, t.delivery_mode, t.delivered_at, p.req_no,
               (SELECT COUNT(*) FROM nobletransmittal_items i WHERE i.transmittal_id = t.id) AS item_count,
               d.id AS dr_id, d.dr_no, d.dr_date
        FROM nobletransmittal t
        LEFT JOIN noblepurchaserequest p ON p.id = t.pr_id
        LEFT JOIN noblepc_dr d ON d.transmittal_id = t.id
        ORDER BY (d.id IS NOT NULL), (t.delivered_at IS NULL), t.transmittal_date DESC, t.id DESC
    ");
    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
        $r['item_count'] = (int) $r['item_count'];
        $r['dr_id'] = $r['dr_id'] !== null ? (int) $r['dr_id'] : null;
        // ready = OK na ang transmittal at wala pang DR
        $r['state'] = $r['dr_id'] ? 'done' : ($r['delivered_at'] ? 'ready' : 'waiting');
    }
    unset($r);
    pcOut(['success' => true, 'rows' => $rows]);
}

// ================= TRANSMITTAL: detalye para i-prefill ang DR =================
if ($action === 'transmittal') {
    $id = intval($_GET['id'] ?? 0);
    $tr = pcTransmittal($conn, $id);
    if (!$tr) pcOut(['success' => false, 'message' => 'Transmittal not found.']);
    $tr['items'] = pcAll($conn, "SELECT serial_no, description, quantity, remarks FROM nobletransmittal_items WHERE transmittal_id = ? ORDER BY item_no", 'i', [$id]);
    $dr = pcOne($conn, "SELECT id FROM noblepc_dr WHERE transmittal_id = ? LIMIT 1", 'i', [$id]);
    pcOut(['success' => true, 'tr' => $tr, 'dr_id' => $dr ? (int) $dr['id'] : null, 'me' => pcUserName($conn, $currentUserId),
           'dr_preview' => date('Y') . DR_NO_MIDDLE . '-xxx']);
}

// ================= SAVE DR =================
if ($action === 'save_dr') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') pcOut(['success' => false, 'message' => 'Invalid request.']);
    $trId = intval($_POST['transmittal_id'] ?? 0);
    $tr = pcTransmittal($conn, $trId);
    if (!$tr) pcOut(['success' => false, 'message' => 'Transmittal not found.']);
    // ang bantay: OK na dapat ang transmittal
    if (empty($tr['delivered_at'])) pcOut(['success' => false, 'message' => 'This transmittal is not yet marked as delivered in the Transmittal Summary. You can create the DR once it is OK.']);

    $date    = pcDate($_POST['dr_date'] ?? '') ?: date('Y-m-d');
    $sched   = pcDate($_POST['scheduled_date'] ?? '');
    $client  = pcStr('client_name');
    $address = pcStr('address');
    $vehicle = pcStr('vehicle', 150);
    $notes   = pcStr('notes', 1000);
    if ($client === '') pcOut(['success' => false, 'message' => 'Client name is required.']);
    if ($sched && $sched < $date) pcOut(['success' => false, 'message' => 'Delivery schedule cannot be earlier than the DR date.']);

    $items = [];
    foreach ((array) ($_POST['particular'] ?? []) as $i => $part) {
        $part = mb_substr(trim((string) $part), 0, 255);
        if ($part === '') continue;
        $qty = (float) str_replace(',', '', (string) ($_POST['qty'][$i] ?? 0));
        $amt = (float) str_replace(',', '', (string) ($_POST['amount'][$i] ?? 0));
        if ($qty <= 0) pcOut(['success' => false, 'message' => "Enter the quantity for \"$part\"."]);
        $items[] = [$part, mb_substr(trim((string) ($_POST['size'][$i] ?? '')), 0, 100), $qty,
                    mb_substr(trim((string) ($_POST['unit'][$i] ?? '')), 0, 30), round(max(0, $amt), 2)];
    }
    if (!$items) pcOut(['success' => false, 'message' => 'Add at least one item.']);

    $me = pcUserName($conn, $currentUserId);
    $conn->begin_transaction();
    try {
        // isang DR lang bawat transmittal
        $exists = pcOne($conn, "SELECT id FROM noblepc_dr WHERE transmittal_id = ? FOR UPDATE", 'i', [$trId]);
        if ($exists) throw new RuntimeException('A DR was already created for this transmittal.');

        $prefix = date('Y', strtotime($date)) . DR_NO_MIDDLE . '-';
        $last = pcOne($conn, "SELECT COALESCE(MAX(CAST(SUBSTRING(dr_no, ?) AS UNSIGNED)), 0) AS n FROM noblepc_dr WHERE dr_no LIKE CONCAT(?, '%') FOR UPDATE",
            'is', [strlen($prefix) + 1, $prefix]);
        $drNo = $prefix . str_pad((string) (((int) $last['n']) + 1), 3, '0', STR_PAD_LEFT);

        $st = $conn->prepare("INSERT INTO noblepc_dr (transmittal_id, dr_no, dr_date, client_name, address, scheduled_date, vehicle, notes, prepared_by, created_by, created_at)
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $st->bind_param('issssssssi', $trId, $drNo, $date, $client, $address, $sched, $vehicle, $notes, $me, $currentUserId);
        $st->execute();
        $drId = $st->insert_id;
        $st->close();

        $it = $conn->prepare("INSERT INTO noblepc_dr_item (dr_id, item_no, particular, size, qty, unit, amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
        foreach ($items as $k => [$part, $size, $qty, $unit, $amt]) {
            $no = $k + 1;
            $it->bind_param('iissdsd', $drId, $no, $part, $size, $qty, $unit, $amt);
            $it->execute();
        }
        $it->close();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        pcOut(['success' => false, 'message' => $e instanceof RuntimeException ? $e->getMessage() : 'Could not save the DR. Please try again.']);
    }
    pcOut(['success' => true, 'id' => $drId, 'dr_no' => $drNo]);
}

// ================= VIEW DR =================
if ($action === 'dr') {
    $id = intval($_GET['id'] ?? 0);
    $dr = pcOne($conn, "SELECT d.*, t.ref_no FROM noblepc_dr d JOIN nobletransmittal t ON t.id = d.transmittal_id WHERE d.id = ? LIMIT 1", 'i', [$id]);
    if (!$dr) pcOut(['success' => false, 'message' => 'DR not found.']);
    $dr['items'] = pcAll($conn, "SELECT particular, size, qty, unit, amount FROM noblepc_dr_item WHERE dr_id = ? ORDER BY item_no", 'i', [$id]);
    pcOut(['success' => true, 'dr' => $dr]);
}

pcOut(['success' => false, 'message' => 'Unknown action.']);