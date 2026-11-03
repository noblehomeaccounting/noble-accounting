<?php
// contractsajax.php — backend ng contracts.php (Contract Agreement)
//
// Daloy:
//   1. Accounting Head — gagawa ng contract (pagkatapos ma-Approved ang Final), pipirma → "For Staff Signing"
//   2. Sales + Designer (naka-assign sa inquiry) — pipirma → "For Client Signing" (bubukas ang client link)
//   3. Client — sa link (contract-sign.php): e-sign + picture ng ID → "For GM Signing"
//   4. Superadmin (General Manager) — huling pirma → "Completed"
// Kapag may mali: ibabalik sa Accounting Head ("Draft") na may remarks, at uulitin ang pirmahan.

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_ACCOUNTING, ROLE_SALES, ROLE_DESIGNER, ROLE_SUPERADMIN];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';
include_once ROOT_PATH . '/admin/crm-management/contract/contract-template.php';

$currentUserId = intval($_SESSION['account_id'] ?? 0);
$currentRole = $_SESSION['role'] ?? '';
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

// ── Sino ang naka-login ──
$viewer = '';
if ($currentRole === ROLE_SUPERADMIN) {
    $viewer = 'superadmin';
} elseif ($currentRole === ROLE_SALES) {
    $viewer = 'sales';
} elseif ($currentRole === ROLE_DESIGNER) {
    $viewer = 'designer';
} elseif ($currentRole === ROLE_ACCOUNTING) {
    $st = $conn->prepare("SELECT id FROM noblerole WHERE id = ? AND role = ? AND position = ? LIMIT 1");
    $r = ROLE_ACCOUNTING;
    $p = POSITION_HEAD;
    $st->bind_param("iss", $currentUserId, $r, $p);
    $st->execute();
    if ($st->get_result()->fetch_assoc()) {
        $viewer = 'acct_head';
    }
    $st->close();
}

if ($action !== 'file') {
    header('Content-Type: application/json');
}

function nhcRespond(bool $ok, string $message = '', array $extra = []): void
{
    if (!headers_sent()) header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $ok, 'message' => $message], $extra));
    exit;
}

if ($viewer === '') {
    nhcRespond(false, 'Contracts are for the Accounting Head, the assigned Sales and Designer, and the Superadmin only.');
}

// ═══════════════════════════════════════════════════════════
// HELPERS
// ═══════════════════════════════════════════════════════════

/**
 * Naka-set na e-signature ng user — kapareho ng Budget Request:
 * una ang noblerole.active_signature_id, kung wala ay ang pinakabagong is_active = 1 sa noblesignature.
 */
function nhcActiveSignature(mysqli $conn, int $userId): ?string
{
    $path = null;
    $st = $conn->prepare("SELECT active_signature_id FROM noblerole WHERE id = ? LIMIT 1");
    $st->bind_param("i", $userId);
    $st->execute();
    $u = $st->get_result()->fetch_assoc();
    $st->close();

    if ($u && !empty($u['active_signature_id'])) {
        $sid = (int) $u['active_signature_id'];
        $st = $conn->prepare("SELECT path FROM noblesignature WHERE id = ? AND user_id = ? LIMIT 1");
        $st->bind_param("ii", $sid, $userId);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $st->close();
        if ($r && !empty($r['path'])) $path = $r['path'];
    }
    if ($path === null) {
        $st = $conn->prepare("SELECT path FROM noblesignature WHERE user_id = ? AND is_active = 1 ORDER BY id DESC LIMIT 1");
        $st->bind_param("i", $userId);
        $st->execute();
        $r = $st->get_result()->fetch_assoc();
        $st->close();
        if ($r && !empty($r['path'])) $path = $r['path'];
    }
    return $path;
}

function nhcSigUrl(?string $path): ?string
{
    return $path ? (defined('BASE_URL') ? BASE_URL : '') . '/' . ltrim($path, '/') : null;
}

function nhcRoleIds(mysqli $conn, string $role, ?string $position = null): array
{
    if ($position === null) {
        $st = $conn->prepare("SELECT id FROM noblerole WHERE role = ?");
        $st->bind_param("s", $role);
    } else {
        $st = $conn->prepare("SELECT id FROM noblerole WHERE role = ? AND position = ?");
        $st->bind_param("ss", $role, $position);
    }
    $st->execute();
    $ids = array_map(fn($r) => (int) $r['id'], $st->get_result()->fetch_all(MYSQLI_ASSOC));
    $st->close();
    return $ids;
}

function nhcNotify(mysqli $conn, array $userIds, int $inquiryId, string $controlNo, string $message, int $senderId): void
{
    try {
        $userIds = array_values(array_unique(array_filter(array_map('intval', $userIds), fn($u) => $u > 0 && $u !== $senderId)));
        if (!$userIds) return;
        $link = "/crmcontracts?inquiry={$inquiryId}";
        $st = $conn->prepare("
            INSERT INTO noblenotification (user_id, request_id, control_no, type, message, is_read, created_at, sender_id, link)
            VALUES (?, ?, ?, 'crm_contract', ?, 0, NOW(), ?, ?)
        ");
        $uid = 0;
        $st->bind_param("iissis", $uid, $inquiryId, $controlNo, $message, $senderId, $link);
        foreach ($userIds as $uid) {
            $st->execute();
        }
        $st->close();
    } catch (Throwable $e) {
        error_log('contractsajax notify: ' . $e->getMessage());
    }
}

function nhcRealtime(mysqli $conn, int $inquiryId): void
{
    try {
        include_once ROOT_PATH . '/network/realtime-helper.php';
        nhRtInquiry($conn, $inquiryId, [[ROLE_ACCOUNTING, POSITION_HEAD], [ROLE_SUPERADMIN, null]], 'crm_contract');
    } catch (Throwable $e) {
        error_log('contractsajax realtime: ' . $e->getMessage());
    }
}

/** Pinakabagong Final na Approved ng inquiry (id) o 0. */
function nhcApprovedFinalId(mysqli $conn, int $inquiryId): int
{
    $st = $conn->prepare("SELECT id, status FROM noblecrm_2dquotation_final WHERE inquiry_id = ? ORDER BY id DESC LIMIT 1");
    $st->bind_param("i", $inquiryId);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return ($row && $row['status'] === 'Approved') ? (int) $row['id'] : 0;
}

function nhcClientLink(string $token): string
{
    $base = defined('BASE_URL') ? (string) BASE_URL : '';
    if (!preg_match('#^https?://#i', $base)) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host = preg_replace('/[^A-Za-z0-9.:\-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $base = ($https ? 'https' : 'http') . '://' . $host . $base;
    }
    return rtrim($base, '/') . '/contractsign?t=' . rawurlencode($token);
}

/** Ano ang puwedeng gawin ng naka-login sa contract na ito. */
function nhcMyAction(?array $c, string $viewer, int $me): string
{
    if (!$c) {
        return $viewer === 'acct_head' ? 'create' : 'none';
    }
    switch ($c['status']) {
        case 'Draft':
            return $viewer === 'acct_head' ? 'edit' : 'none';
        case 'For Staff Signing':
            if ($viewer === 'sales' && (int) $c['sales_user_id'] === $me && empty($c['sales_signed_at'])) return 'sign';
            if ($viewer === 'designer' && (int) $c['designer_user_id'] === $me && empty($c['designer_signed_at'])) return 'sign';
            return 'none';
        case 'For Client Signing':
            return ($viewer === 'sales' && (int) $c['sales_user_id'] === $me) ? 'send_link' : 'none';
        case 'For GM Signing':
            return $viewer === 'superadmin' ? 'gm_sign' : 'none';
    }
    return 'none';
}

/** Puwede bang makita ng naka-login ang contract na ito? */
function nhcCanSee(array $c, string $viewer, int $me): bool
{
    if ($viewer === 'acct_head' || $viewer === 'superadmin') return true;
    if ($c['status'] === 'Draft') return false;
    if ($viewer === 'sales') return (int) $c['sales_user_id'] === $me;
    if ($viewer === 'designer') return (int) $c['designer_user_id'] === $me;
    return false;
}

function nhcSteps(array $c): array
{
    $hasDesigner = (int) ($c['designer_user_id'] ?? 0) > 0 || $c['status'] === 'Draft';
    $steps = [
        ['key' => 'acct', 'label' => 'Accounting Head', 'name' => $c['acct_name'] ?? null, 'at' => $c['acct_signed_at'] ?? null],
        ['key' => 'sales', 'label' => 'Sales', 'name' => $c['sales_name'] ?? null, 'at' => $c['sales_signed_at'] ?? null],
    ];
    if ($hasDesigner) {
        $steps[] = ['key' => 'designer', 'label' => 'Designer', 'name' => $c['designer_name'] ?? null, 'at' => $c['designer_signed_at'] ?? null];
    }
    $steps[] = ['key' => 'client', 'label' => 'Client', 'name' => $c['client_signed_name'] ?? null, 'at' => $c['client_signed_at'] ?? null];
    $steps[] = ['key' => 'gm', 'label' => 'General Manager', 'name' => $c['gm_name'] ?? null, 'at' => $c['gm_signed_at'] ?? null];
    return $steps;
}

function nhcLoadById(mysqli $conn, int $id): ?array
{
    return nhcLoad($conn, 'c.id = ?', 'i', [$id]);
}

function nhcRequire(?array $c, string $viewer, int $me): array
{
    if (!$c || !nhcCanSee($c, $viewer, $me)) {
        nhcRespond(false, 'Contract not found.');
    }
    return $c;
}

/** Ibalik sa Draft (para itama ng Accounting Head) — burado ang lahat ng pirma at ang client link. */
function nhcResetToDraft(mysqli $conn, int $id, ?string $remarks, int $by, bool $clearClient): bool
{
    $clientSql = $clearClient
        ? ", client_signed_name = NULL, client_signature_path = NULL, client_id_path = NULL, client_signed_at = NULL, client_ip = NULL"
        : '';
    $st = $conn->prepare("
        UPDATE noblecrm_contract
        SET status = 'Draft', remarks = ?, returned_by = ?, returned_at = NOW(), updated_at = NOW(),
            acct_signed_by = NULL, acct_signed_at = NULL, acct_signature_path = NULL,
            sales_signed_at = NULL, sales_signature_path = NULL,
            designer_signed_at = NULL, designer_signature_path = NULL,
            gm_signed_by = NULL, gm_signed_at = NULL, gm_signature_path = NULL,
            client_token = NULL {$clientSql}
        WHERE id = ?
    ");
    $st->bind_param("sii", $remarks, $by, $id);
    $ok = $st->execute();
    $st->close();
    return $ok;
}

function nhcRowJson(array $r, string $viewer, int $me): array
{
    $c = !empty($r['cid']) ? $r : null;
    return [
        'inquiry_id'   => (int) $r['inquiry_id'],
        'contract_id'  => $c ? (int) $r['cid'] : null,
        'control_no'   => $r['control_no'],
        'client_name'  => $r['client_name'],
        'project_type' => $r['project_type'] ?? null,
        'status'       => $c ? $r['status'] : 'Not created',
        'amount'       => $c ? (float) $r['contract_amount'] : null,
        'signed'       => $c ? [
            'acct'     => !empty($r['acct_signed_at']),
            'sales'    => !empty($r['sales_signed_at']),
            'designer' => !empty($r['designer_signed_at']) || ((int) $r['designer_user_id'] === 0 && $r['status'] !== 'Draft'),
            'client'   => !empty($r['client_signed_at']),
            'gm'       => !empty($r['gm_signed_at']),
        ] : null,
        'returned'     => $c && $r['status'] === 'Draft' && !empty($r['remarks']),
        'my_action'    => nhcMyAction($c ? [
            'status' => $r['status'], 'sales_user_id' => $r['sales_user_id'], 'designer_user_id' => $r['designer_user_id'],
            'sales_signed_at' => $r['sales_signed_at'], 'designer_signed_at' => $r['designer_signed_at'],
        ] : null, $viewer, $me),
        'updated_at'   => $c ? ($r['updated_at'] ?? $r['created_at']) : null,
    ];
}

// ═══════════════════════════════════════════════════════════
// LIST
// ═══════════════════════════════════════════════════════════

if ($action === 'list') {
    $search = trim((string) ($_GET['q'] ?? ''));

    $sql = "
        SELECT i.id AS inquiry_id, i.control_no, i.client_name, i.project_type,
               c.id AS cid, c.status, c.contract_amount, c.remarks, c.created_at, c.updated_at,
               c.sales_user_id, c.designer_user_id,
               c.acct_signed_at, c.sales_signed_at, c.designer_signed_at, c.client_signed_at, c.gm_signed_at
        FROM noblecrminquiry i
        LEFT JOIN (
            SELECT f.inquiry_id FROM noblecrm_2dquotation_final f
            JOIN (SELECT inquiry_id, MAX(id) AS mx FROM noblecrm_2dquotation_final GROUP BY inquiry_id) l ON l.mx = f.id
            WHERE f.status = 'Approved'
        ) fa ON fa.inquiry_id = i.id
        LEFT JOIN noblecrm_contract c ON c.inquiry_id = i.id
        WHERE (fa.inquiry_id IS NOT NULL OR c.id IS NOT NULL)
    ";
    $types = '';
    $params = [];

    if ($viewer === 'sales') {
        $sql .= " AND c.id IS NOT NULL AND c.status <> 'Draft' AND c.sales_user_id = ? ";
        $types .= 'i';
        $params[] = $currentUserId;
    } elseif ($viewer === 'designer') {
        $sql .= " AND c.id IS NOT NULL AND c.status <> 'Draft' AND c.designer_user_id = ? ";
        $types .= 'i';
        $params[] = $currentUserId;
    }

    if ($search !== '') {
        $sql .= " AND (i.control_no LIKE ? OR i.client_name LIKE ?) ";
        $like = '%' . $search . '%';
        $types .= 'ss';
        $params[] = $like;
        $params[] = $like;
    }
    $sql .= " ORDER BY COALESCE(c.updated_at, c.created_at, '2000-01-01') DESC, i.id DESC LIMIT 300";

    $st = $conn->prepare($sql);
    if ($types !== '') $st->bind_param($types, ...$params);
    $st->execute();
    $rows = array_map(fn($r) => nhcRowJson($r, $viewer, $currentUserId), $st->get_result()->fetch_all(MYSQLI_ASSOC));
    $st->close();

    nhcRespond(true, '', ['rows' => $rows, 'viewer' => $viewer]);
}

// ═══════════════════════════════════════════════════════════
// DETAIL (o default values para sa bagong contract)
// ═══════════════════════════════════════════════════════════

if ($action === 'detail') {
    $inquiryId = intval($_GET['inquiry_id'] ?? 0);
    $st = $conn->prepare("SELECT id FROM noblecrm_contract WHERE inquiry_id = ? LIMIT 1");
    $st->bind_param("i", $inquiryId);
    $st->execute();
    $existing = $st->get_result()->fetch_assoc();
    $st->close();

    if (!$existing) {
        if ($viewer !== 'acct_head' && $viewer !== 'superadmin') {
            nhcRespond(false, 'Contract not found.');
        }
        // Bagong contract — mga default galing sa inquiry
        $st = $conn->prepare("SELECT id, control_no, client_name, address, contact_number, project_type, contract_amount FROM noblecrminquiry WHERE id = ? LIMIT 1");
        $st->bind_param("i", $inquiryId);
        $st->execute();
        $inq = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$inq) nhcRespond(false, 'Inquiry not found.');
        if (!nhcApprovedFinalId($conn, $inquiryId)) nhcRespond(false, 'The Final 2D & Quotation is not approved yet.');

        nhcRespond(true, '', ['contract' => null, 'defaults' => [
            'inquiry_id'        => (int) $inq['id'],
            'control_no'        => $inq['control_no'],
            'client_name'       => $inq['client_name'],
            'owner_name'        => $inq['client_name'],
            'project_name'      => $inq['project_type'] ?? '',
            'address'           => $inq['address'] ?? '',
            'contact_number'    => $inq['contact_number'] ?? '',
            'contract_amount'   => (float) ($inq['contract_amount'] ?? 0) ?: '',
            'start_date'        => '',
            'completion_date'   => '',
            'dp_percent'        => 50,
            'delivery_percent'  => 20,
            'install_percent'   => 20,
            'retention_percent' => 10,
            'dp_paid'           => 0,
            'validity_days'     => 30,
            'body_html'         => nhcDefaultBody(),
        ], 'default_body' => nhcDefaultBody(), 'placeholders' => nhcPlaceholderList(),
           'my_action' => nhcMyAction(null, $viewer, $currentUserId),
           'has_signature' => $viewer === 'acct_head' ? (bool) nhcActiveSignature($conn, $currentUserId) : null,
           'my_signature_url' => $viewer === 'acct_head' ? nhcSigUrl(nhcActiveSignature($conn, $currentUserId)) : null]);
    }

    $c = nhcRequire(nhcLoadById($conn, (int) $existing['id']), $viewer, $currentUserId);
    $myAction = nhcMyAction($c, $viewer, $currentUserId);
    $showLink = $c['status'] === 'For Client Signing' && !empty($c['client_token'])
        && in_array($viewer, ['sales', 'designer', 'acct_head', 'superadmin'], true);

    nhcRespond(true, '', [
        'contract' => [
            'id'                => (int) $c['id'],
            'inquiry_id'        => (int) $c['inquiry_id'],
            'control_no'        => $c['control_no'],
            'client_name'       => $c['client_name'],
            'status'            => $c['status'],
            'owner_name'        => $c['owner_name'],
            'project_name'      => $c['project_name'],
            'address'           => $c['address'],
            'contact_number'    => $c['contact_number'],
            'start_date'        => $c['start_date'],
            'completion_date'   => $c['completion_date'],
            'contract_amount'   => (float) $c['contract_amount'],
            'dp_percent'        => (int) $c['dp_percent'],
            'delivery_percent'  => (int) $c['delivery_percent'],
            'install_percent'   => (int) $c['install_percent'],
            'retention_percent' => (int) $c['retention_percent'],
            'dp_paid'           => (int) $c['dp_paid'],
            'validity_days'     => (int) $c['validity_days'],
            'body_html'         => $myAction === 'edit' ? nhcSanitizeBody((string) ($c['body_html'] ?: nhcDefaultBody())) : null,
            'dp_amount'         => nhcDpAmount($c),
            'balance'           => nhcBalance($c),
            'remarks'           => $c['remarks'],
            'steps'             => nhcSteps($c),
            'has_client_id'     => !empty($c['client_id_path']),
            'client_link'       => $showLink ? nhcClientLink($c['client_token']) : null,
        ],
        'my_action'   => $myAction,
        'default_body' => $myAction === 'edit' ? nhcDefaultBody() : null,
        'placeholders' => $myAction === 'edit' ? nhcPlaceholderList() : null,
        'has_signature' => in_array($myAction, ['edit', 'sign', 'gm_sign'], true) ? (bool) nhcActiveSignature($conn, $currentUserId) : null,
        'my_signature_url' => in_array($myAction, ['edit', 'sign', 'gm_sign'], true) ? nhcSigUrl(nhcActiveSignature($conn, $currentUserId)) : null,
    ]);
}

// ═══════════════════════════════════════════════════════════
// FILE — client signature / ID (private folder)
// ═══════════════════════════════════════════════════════════

if ($action === 'file') {
    $c = nhcLoadById($conn, intval($_GET['id'] ?? 0));
    if (!$c || !nhcCanSee($c, $viewer, $currentUserId)) {
        http_response_code(404);
        exit;
    }
    $kind = $_GET['kind'] ?? '';
    $rel = $kind === 'client_id' ? $c['client_id_path'] : ($kind === 'client_sig' ? $c['client_signature_path'] : null);
    $abs = $rel ? ROOT_PATH . '/' . ltrim($rel, '/') : '';
    $info = ($abs && is_file($abs)) ? @getimagesize($abs) : false;
    if (!$info) {
        http_response_code(404);
        exit;
    }
    header('Content-Type: ' . $info['mime']);
    header('Content-Length: ' . filesize($abs));
    header('Cache-Control: private, no-store');
    header('X-Content-Type-Options: nosniff');
    readfile($abs);
    exit;
}

// ═══════════════════════════════════════════════════════════
// SAVE (Accounting Head) — gawa / edit habang Draft
// ═══════════════════════════════════════════════════════════

if ($action === 'save' || $action === 'issue') {
    if ($viewer !== 'acct_head') {
        nhcRespond(false, 'Only the Accounting Head can prepare the contract.');
    }

    $inquiryId = intval($_POST['inquiry_id'] ?? 0);
    $finalId = nhcApprovedFinalId($conn, $inquiryId);
    if (!$finalId) {
        nhcRespond(false, 'The Final 2D & Quotation must be approved by the Sales Head and Designer Head first.');
    }

    $st = $conn->prepare("SELECT c.id, c.status, i.control_no, i.sales_staff_id, i.designer_id
        FROM noblecrminquiry i LEFT JOIN noblecrm_contract c ON c.inquiry_id = i.id WHERE i.id = ? LIMIT 1");
    $st->bind_param("i", $inquiryId);
    $st->execute();
    $cur = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$cur) nhcRespond(false, 'Inquiry not found.');
    if ($cur['id'] && $cur['status'] !== 'Draft') {
        nhcRespond(false, 'This contract is already out for signing. Recall it first to make changes.');
    }

    // ── validation ──
    $str = fn($k, $max) => mb_substr(trim((string) ($_POST[$k] ?? '')), 0, $max);
    $owner = $str('owner_name', 150);
    $project = $str('project_name', 150);
    $address = $str('address', 255);
    $contact = $str('contact_number', 50);
    $start = trim((string) ($_POST['start_date'] ?? ''));
    $completion = trim((string) ($_POST['completion_date'] ?? ''));
    $amountRaw = preg_replace('/[^0-9.]/', '', (string) ($_POST['contract_amount'] ?? ''));
    $pct = [];
    foreach (['dp_percent', 'delivery_percent', 'install_percent', 'retention_percent'] as $k) {
        $pct[$k] = (int) ($_POST[$k] ?? -1);
    }
    $dpPaid = !empty($_POST['dp_paid']) ? 1 : 0;
    $validity = (int) ($_POST['validity_days'] ?? 30);

    $isDate = function ($d) {
        $t = DateTime::createFromFormat('Y-m-d', $d);
        return $t && $t->format('Y-m-d') === $d;
    };

    if (mb_strlen($owner) < 2) nhcRespond(false, 'Please enter the Owner (client or company name).');
    if (mb_strlen($project) < 2) nhcRespond(false, 'Please enter the project name.');
    if ($address === '') nhcRespond(false, 'Please enter the address.');
    if ($contact === '') nhcRespond(false, 'Please enter the contact number.');
    if (!$isDate($start)) nhcRespond(false, 'Please enter a valid start date.');
    if (!$isDate($completion)) nhcRespond(false, 'Please enter a valid completion date.');
    if ($completion < $start) nhcRespond(false, 'The completion date cannot be before the start date.');
    if ($amountRaw === '' || !is_numeric($amountRaw) || (float) $amountRaw <= 0 || (float) $amountRaw > 9999999999) {
        nhcRespond(false, 'Please enter a valid contract amount.');
    }
    foreach ($pct as $k => $v) {
        if ($v < 0 || $v > 100) nhcRespond(false, 'Payment percentages must be between 0 and 100.');
    }
    if (array_sum($pct) !== 100) nhcRespond(false, 'The payment percentages (Down payment + Delivery + Installation + Retention) must add up to 100%.');
    if ($validity < 1 || $validity > 365) nhcRespond(false, 'Price validity must be between 1 and 365 days.');
    $amount = round((float) $amountRaw, 2);

    // Laman ng contract (rich text) — iba-iba kada project
    $bodyRaw = (string) ($_POST['body_html'] ?? '');
    if (strlen($bodyRaw) > 500000) nhcRespond(false, 'The contract content is too long.');
    $body = nhcSanitizeBody($bodyRaw);
    if ($body === '') nhcRespond(false, 'The contract content is empty.');
    if (preg_match_all('/\[\[([a-z_]+)\]\]/', $body, $pm)) {
        $unknown = array_diff(array_unique($pm[1]), array_keys(nhcPlaceholderList()));
        if ($unknown) nhcRespond(false, 'Unknown field in the contract: [[' . implode(']], [[', $unknown) . ']]. Remove it or use "Insert field".');
    }

    $mySig = null;
    if ($action === 'issue') {
        $mySig = nhcActiveSignature($conn, $currentUserId);
        if (!$mySig) nhcRespond(false, 'You have no e-signature set yet. Set your signature first (same as in Budget Request), then sign again.');
    }

    $conn->begin_transaction();
    try {
        if ($cur['id']) {
            $id = (int) $cur['id'];
            $st = $conn->prepare("
                UPDATE noblecrm_contract
                SET final_id = ?, owner_name = ?, project_name = ?, address = ?, contact_number = ?, start_date = ?, completion_date = ?,
                    contract_amount = ?, dp_percent = ?, delivery_percent = ?, install_percent = ?, retention_percent = ?,
                    dp_paid = ?, validity_days = ?, body_html = ?, updated_at = NOW()
                WHERE id = ? AND status = 'Draft'
            ");
            $st->bind_param("issssssdiiiiiisi", $finalId, $owner, $project, $address, $contact, $start, $completion,
                $amount, $pct['dp_percent'], $pct['delivery_percent'], $pct['install_percent'], $pct['retention_percent'],
                $dpPaid, $validity, $body, $id);
            $st->execute();
            $st->close();
        } else {
            $st = $conn->prepare("
                INSERT INTO noblecrm_contract
                    (inquiry_id, final_id, status, owner_name, project_name, address, contact_number, start_date, completion_date,
                     contract_amount, dp_percent, delivery_percent, install_percent, retention_percent, dp_paid, validity_days,
                     body_html, created_by, created_at, updated_at)
                VALUES (?, ?, 'Draft', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $st->bind_param("iissssssdiiiiiisi", $inquiryId, $finalId, $owner, $project, $address, $contact, $start, $completion,
                $amount, $pct['dp_percent'], $pct['delivery_percent'], $pct['install_percent'], $pct['retention_percent'],
                $dpPaid, $validity, $body, $currentUserId);
            $st->execute();
            $id = (int) $st->insert_id;
            $st->close();
        }

        // Para gumana pa rin ang ibang pages (Accounting CRM List, Project Cost) na gumagamit ng contract_amount
        $st = $conn->prepare("UPDATE noblecrminquiry SET contract_amount = ? WHERE id = ?");
        $st->bind_param("di", $amount, $inquiryId);
        $st->execute();
        $st->close();

        if ($action === 'issue') {
            // Pirma ng Accounting Head + snapshot ng Sales/Designer na pipirma
            $salesId = (int) $cur['sales_staff_id'];
            $designerId = (int) $cur['designer_id'];
            if ($salesId <= 0) {
                throw new RuntimeException('NO_SALES');
            }
            $st = $conn->prepare("
                UPDATE noblecrm_contract
                SET status = 'For Staff Signing', issued_at = NOW(), remarks = NULL,
                    acct_signed_by = ?, acct_signed_at = NOW(), acct_signature_path = ?,
                    sales_user_id = ?, designer_user_id = ?,
                    sales_signed_at = NULL, sales_signature_path = NULL,
                    designer_signed_at = NULL, designer_signature_path = NULL,
                    client_token = NULL
                WHERE id = ? AND status = 'Draft'
            ");
            $st->bind_param("isiii", $currentUserId, $mySig, $salesId, $designerId, $id);
            $st->execute();
            $st->close();
        }
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        if ($e->getMessage() === 'NO_SALES') {
            nhcRespond(false, 'This inquiry has no assigned Sales yet, so nobody can sign the Sales line.');
        }
        error_log('contractsajax save: ' . $e->getMessage());
        nhcRespond(false, 'Failed to save the contract.');
    }

    if ($action === 'issue') {
        nhcNotify($conn, [(int) $cur['sales_staff_id'], (int) $cur['designer_id']], $inquiryId, (string) $cur['control_no'],
            "Contract for {$cur['control_no']} is ready for your signature.", $currentUserId);
    }
    nhcRealtime($conn, $inquiryId);
    nhcRespond(true, $action === 'issue' ? 'Contract signed and sent to Sales and Designer for signing.' : 'Draft saved.', ['id' => $id]);
}

// ═══════════════════════════════════════════════════════════
// RECALL (Accounting Head) — bawiin habang hindi pa pumipirma ang client
// ═══════════════════════════════════════════════════════════

if ($action === 'recall') {
    if ($viewer !== 'acct_head') nhcRespond(false, 'Only the Accounting Head can recall the contract.');
    $c = nhcRequire(nhcLoadById($conn, intval($_POST['id'] ?? 0)), $viewer, $currentUserId);
    if (!in_array($c['status'], ['For Staff Signing', 'For Client Signing'], true)) {
        nhcRespond(false, 'Only contracts that the client has not signed yet can be recalled.');
    }
    if (!nhcResetToDraft($conn, (int) $c['id'], null, $currentUserId, true)) nhcRespond(false, 'Failed to recall the contract.');
    nhcNotify($conn, [(int) $c['sales_user_id'], (int) $c['designer_user_id']], (int) $c['inquiry_id'], (string) $c['control_no'],
        "Contract for {$c['control_no']} was recalled by the Accounting Head for changes.", $currentUserId);
    nhcRealtime($conn, (int) $c['inquiry_id']);
    nhcRespond(true, 'Contract recalled. You can edit it again.');
}

// ═══════════════════════════════════════════════════════════
// SIGN (Sales / Designer)
// ═══════════════════════════════════════════════════════════

if ($action === 'sign') {
    $c = nhcRequire(nhcLoadById($conn, intval($_POST['id'] ?? 0)), $viewer, $currentUserId);
    if (nhcMyAction($c, $viewer, $currentUserId) !== 'sign') {
        nhcRespond(false, 'This contract is not waiting for your signature.');
    }
    $sig = nhcActiveSignature($conn, $currentUserId);
    if (!$sig) nhcRespond(false, 'You have no e-signature set yet. Set your signature first (same as in Budget Request), then sign again.');

    $col = $viewer === 'sales' ? 'sales' : 'designer';
    $conn->begin_transaction();
    try {
        $st = $conn->prepare("SELECT * FROM noblecrm_contract WHERE id = ? FOR UPDATE");
        $id = (int) $c['id'];
        $st->bind_param("i", $id);
        $st->execute();
        $fresh = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$fresh || $fresh['status'] !== 'For Staff Signing' || !empty($fresh["{$col}_signed_at"])) {
            throw new RuntimeException('STALE');
        }

        $st = $conn->prepare("UPDATE noblecrm_contract SET {$col}_signed_at = NOW(), {$col}_signature_path = ?, updated_at = NOW() WHERE id = ?");
        $st->bind_param("si", $sig, $id);
        $st->execute();
        $st->close();

        $salesDone = $col === 'sales' || !empty($fresh['sales_signed_at']);
        $designerDone = $col === 'designer' || !empty($fresh['designer_signed_at']) || (int) $fresh['designer_user_id'] <= 0;
        $bothDone = $salesDone && $designerDone;

        if ($bothDone) {
            $token = bin2hex(random_bytes(24));
            $st = $conn->prepare("UPDATE noblecrm_contract SET status = 'For Client Signing', client_token = ? WHERE id = ?");
            $st->bind_param("si", $token, $id);
            $st->execute();
            $st->close();
        }
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        if ($e->getMessage() === 'STALE') nhcRespond(false, 'This contract has changed. Please refresh.');
        error_log('contractsajax sign: ' . $e->getMessage());
        nhcRespond(false, 'Failed to sign the contract.');
    }

    if ($bothDone) {
        nhcNotify($conn, [(int) $c['sales_user_id']], (int) $c['inquiry_id'], (string) $c['control_no'],
            "Contract for {$c['control_no']} is ready for the client. Send the signing link to the client.", $currentUserId);
    }
    nhcRealtime($conn, (int) $c['inquiry_id']);
    nhcRespond(true, $bothDone ? 'Signed. The client signing link is now ready.' : 'Signed. Waiting for the other signature.');
}

// ═══════════════════════════════════════════════════════════
// GM SIGN (Superadmin) — huling pirma
// ═══════════════════════════════════════════════════════════

if ($action === 'gm_sign') {
    if ($viewer !== 'superadmin') nhcRespond(false, 'Only the General Manager can give the final signature.');
    $c = nhcRequire(nhcLoadById($conn, intval($_POST['id'] ?? 0)), $viewer, $currentUserId);
    if ($c['status'] !== 'For GM Signing') nhcRespond(false, 'This contract is not waiting for the final signature.');
    $sig = nhcActiveSignature($conn, $currentUserId);
    if (!$sig) nhcRespond(false, 'You have no e-signature set yet. Set your signature first (same as in Budget Request), then sign again.');

    $id = (int) $c['id'];
    $st = $conn->prepare("
        UPDATE noblecrm_contract
        SET status = 'Completed', gm_signed_by = ?, gm_signed_at = NOW(), gm_signature_path = ?, client_token = NULL, updated_at = NOW()
        WHERE id = ? AND status = 'For GM Signing'
    ");
    $st->bind_param("isi", $currentUserId, $sig, $id);
    $st->execute();
    $ok = $st->affected_rows === 1;
    $st->close();
    if (!$ok) nhcRespond(false, 'This contract has changed. Please refresh.');

    nhcNotify($conn, array_merge(nhcRoleIds($conn, ROLE_ACCOUNTING, POSITION_HEAD), [(int) $c['sales_user_id'], (int) $c['designer_user_id']]),
        (int) $c['inquiry_id'], (string) $c['control_no'], "Contract for {$c['control_no']} is fully signed.", $currentUserId);
    nhcRealtime($conn, (int) $c['inquiry_id']);
    nhcRespond(true, 'Contract completed.');
}

// ═══════════════════════════════════════════════════════════
// RETURN — ibalik sa Accounting Head para itama (Sales/Designer o Superadmin)
// ═══════════════════════════════════════════════════════════

if ($action === 'return') {
    $c = nhcRequire(nhcLoadById($conn, intval($_POST['id'] ?? 0)), $viewer, $currentUserId);
    $remarks = mb_substr(trim((string) ($_POST['remarks'] ?? '')), 0, 1000);
    if ($remarks === '') nhcRespond(false, 'Please write what needs to be corrected.');

    $allowed = ($viewer === 'superadmin' && $c['status'] === 'For GM Signing')
        || (in_array($viewer, ['sales', 'designer'], true) && in_array($c['status'], ['For Staff Signing', 'For Client Signing'], true));
    if (!$allowed) nhcRespond(false, 'You cannot return this contract right now.');

    $who = ['superadmin' => 'General Manager', 'sales' => 'Sales', 'designer' => 'Designer'][$viewer];
    $note = "{$who}: {$remarks}";
    // Kapag GM ang nagbalik, pipirma ulit ang client sa itatamang contract.
    if (!nhcResetToDraft($conn, (int) $c['id'], $note, $currentUserId, true)) nhcRespond(false, 'Failed to return the contract.');

    nhcNotify($conn, array_merge(nhcRoleIds($conn, ROLE_ACCOUNTING, POSITION_HEAD), [(int) $c['sales_user_id'], (int) $c['designer_user_id']]),
        (int) $c['inquiry_id'], (string) $c['control_no'], "Contract for {$c['control_no']} was returned for correction — {$note}", $currentUserId);
    nhcRealtime($conn, (int) $c['inquiry_id']);
    nhcRespond(true, 'Returned to the Accounting Head for correction.');
}

nhcRespond(false, 'Unknown action.');