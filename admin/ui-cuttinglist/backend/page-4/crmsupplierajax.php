<?php
// crmsupplierajax.php  —  backend ng crmsupplier.php (Supplier & Products)
// Ginagamit din ng ewoodfile.php (BOM) ang action=catalog para sa pagpili ng supplier at produkto.

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_CUTTING, ROLE_SUPERADMIN];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
set_exception_handler(function (Throwable $e) {
    error_log('crmsupplierajax: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: application/json');
    }
    $msg = $e->getMessage();
    if (stripos($msg, "noblecrm_supplier") !== false && stripos($msg, "doesn't exist") !== false) {
        $msg = 'The supplier tables are not set up yet. Please run noblecrm_supplier.sql first.';
    }
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $msg]);
    exit;
});

$currentUserId = intval($_SESSION['account_id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

function supRespond(bool $ok, string $message = '', array $extra = []): void
{
    echo json_encode(array_merge(['success' => $ok, 'message' => $message], $extra));
    exit;
}

function supRows(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $stmt = $conn->prepare($sql);
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

// Tanggalin ang sobrang espasyo; null kapag walang laman
function supText($v, int $max): ?string
{
    $v = trim(preg_replace('/\s+/u', ' ', (string) ($v ?? '')));
    if ($v === '') {
        return null;
    }
    return mb_substr($v, 0, $max);
}

function supRequirePost(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        supRespond(false, 'Invalid request.');
    }
}

// ═════════════ LIST: lahat ng supplier ═════════════
if ($action === 'list') {
    $rows = supRows($conn, "
        SELECT s.id, s.name, s.contact_person, s.contact_no, s.address, s.is_active, s.created_at, s.updated_at,
               COUNT(i.id) AS item_count, COALESCE(SUM(i.is_active = 1), 0) AS active_count
        FROM noblecrm_supplier s
        LEFT JOIN noblecrm_supplier_item i ON i.supplier_id = s.id
        GROUP BY s.id
        ORDER BY s.is_active DESC, s.name ASC
    ");
    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
        $r['is_active'] = (int) $r['is_active'];
        $r['item_count'] = (int) $r['item_count'];
        $r['active_count'] = (int) $r['active_count'];
    }
    supRespond(true, '', ['rows' => $rows]);
}

// ═════════════ ITEMS: produkto ng isang supplier ═════════════
if ($action === 'items') {
    $sid = intval($_GET['supplier_id'] ?? 0);
    if ($sid <= 0) {
        supRespond(false, 'Choose a supplier.');
    }
    $rows = supRows($conn, "
        SELECT id, item_code, description, unit, is_active, created_at, updated_at
        FROM noblecrm_supplier_item WHERE supplier_id = ?
        ORDER BY is_active DESC, item_code ASC
    ", 'i', [$sid]);
    foreach ($rows as &$r) {
        $r['id'] = (int) $r['id'];
        $r['is_active'] = (int) $r['is_active'];
    }
    supRespond(true, '', ['rows' => $rows]);
}

// ═════════════ CATALOG: para sa BOM (active lang) ═════════════
if ($action === 'catalog') {
    $sup = supRows($conn, "SELECT id, name FROM noblecrm_supplier WHERE is_active = 1 ORDER BY name ASC");
    $items = supRows($conn, "
        SELECT i.id, i.supplier_id, i.item_code, i.description, i.unit
        FROM noblecrm_supplier_item i
        JOIN noblecrm_supplier s ON s.id = i.supplier_id AND s.is_active = 1
        WHERE i.is_active = 1
        ORDER BY i.item_code ASC
    ");
    $map = [];
    foreach ($sup as $s) {
        $map[(int) $s['id']] = ['id' => (int) $s['id'], 'name' => $s['name'], 'items' => []];
    }
    foreach ($items as $it) {
        $sid = (int) $it['supplier_id'];
        if (isset($map[$sid])) {
            $map[$sid]['items'][] = ['id' => (int) $it['id'], 'item_code' => $it['item_code'], 'description' => $it['description'], 'unit' => $it['unit'] ?? ''];
        }
    }
    supRespond(true, '', ['suppliers' => array_values($map)]);
}

// ═════════════ SAVE SUPPLIER (bago o edit) ═════════════
if ($action === 'save_supplier') {
    supRequirePost();
    $id = intval($_POST['id'] ?? 0);
    $name = supText($_POST['name'] ?? '', 150);
    $person = supText($_POST['contact_person'] ?? '', 150);
    $phone = supText($_POST['contact_no'] ?? '', 50);
    $addr = supText($_POST['address'] ?? '', 255);

    if ($name === null) {
        supRespond(false, 'Enter the supplier name.', ['field' => 'name']);
    }
    if (strcasecmp($name, 'OUTSOURCE') === 0) {
        supRespond(false, '"Outsource" is a built-in choice in the BOM. Use another name.', ['field' => 'name']);
    }
    if ($phone !== null && !preg_match('/^[0-9+()\-\s\/]{5,50}$/', $phone)) {
        supRespond(false, 'Enter a valid contact number.', ['field' => 'contact_no']);
    }
    $dup = supRows($conn, "SELECT id FROM noblecrm_supplier WHERE LOWER(name) = LOWER(?) AND id <> ? LIMIT 1", 'si', [$name, $id]);
    if ($dup) {
        supRespond(false, 'A supplier with this name already exists.', ['field' => 'name']);
    }

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE noblecrm_supplier SET name = ?, contact_person = ?, contact_no = ?, address = ?, updated_at = NOW() WHERE id = ?");
        $stmt->bind_param('ssssi', $name, $person, $phone, $addr, $id);
        $stmt->execute();
        $found = supRows($conn, "SELECT id FROM noblecrm_supplier WHERE id = ?", 'i', [$id]);
        $stmt->close();
        if (!$found) {
            supRespond(false, 'This supplier no longer exists. Reload the page.');
        }
        supRespond(true, 'Supplier updated.', ['id' => $id]);
    }

    $stmt = $conn->prepare("INSERT INTO noblecrm_supplier (name, contact_person, contact_no, address, is_active, created_by, created_at) VALUES (?, ?, ?, ?, 1, ?, NOW())");
    $stmt->bind_param('ssssi', $name, $person, $phone, $addr, $currentUserId);
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();
    supRespond(true, 'Supplier added.', ['id' => $newId]);
}

// ═════════════ SUPPLIER: active / inactive ═════════════
if ($action === 'set_supplier_active') {
    supRequirePost();
    $id = intval($_POST['id'] ?? 0);
    $on = intval($_POST['active'] ?? 0) === 1 ? 1 : 0;
    $stmt = $conn->prepare("UPDATE noblecrm_supplier SET is_active = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('ii', $on, $id);
    $stmt->execute();
    $stmt->close();
    supRespond(true, $on ? 'Supplier is active again.' : 'Supplier hidden from the BOM.');
}

// ═════════════ DELETE SUPPLIER (kasama ang mga produkto nito) ═════════════
// Ligtas: ang BOM ay nagse-save ng text (pangalan, code), hindi ng id.
if ($action === 'delete_supplier') {
    supRequirePost();
    $id = intval($_POST['id'] ?? 0);
    if ($id <= 0) {
        supRespond(false, 'Invalid supplier.');
    }
    $conn->begin_transaction();
    try {
        $a = $conn->prepare("DELETE FROM noblecrm_supplier_item WHERE supplier_id = ?");
        $a->bind_param('i', $id);
        $a->execute();
        $a->close();
        $b = $conn->prepare("DELETE FROM noblecrm_supplier WHERE id = ?");
        $b->bind_param('i', $id);
        $b->execute();
        $gone = $b->affected_rows;
        $b->close();
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
    supRespond($gone === 1, $gone === 1 ? 'Supplier deleted.' : 'This supplier was already deleted.');
}

// ═════════════ SAVE ITEM (bago o edit) ═════════════
if ($action === 'save_item') {
    supRequirePost();
    $id = intval($_POST['id'] ?? 0);
    $sid = intval($_POST['supplier_id'] ?? 0);
    $code = supText($_POST['item_code'] ?? '', 50);
    $desc = supText($_POST['description'] ?? '', 255);
    $unit = supText($_POST['unit'] ?? '', 30);

    if ($sid <= 0 || !supRows($conn, "SELECT id FROM noblecrm_supplier WHERE id = ?", 'i', [$sid])) {
        supRespond(false, 'This supplier no longer exists. Reload the page.');
    }
    if ($code === null) {
        supRespond(false, 'Enter the item code.', ['field' => 'item_code']);
    }
    if ($desc === null) {
        supRespond(false, 'Enter the description.', ['field' => 'description']);
    }
    $code = mb_strtoupper($code);
    $dup = supRows($conn, "SELECT id FROM noblecrm_supplier_item WHERE supplier_id = ? AND item_code = ? AND id <> ? LIMIT 1", 'isi', [$sid, $code, $id]);
    if ($dup) {
        supRespond(false, "This supplier already has item code {$code}.", ['field' => 'item_code']);
    }

    if ($id > 0) {
        $stmt = $conn->prepare("UPDATE noblecrm_supplier_item SET item_code = ?, description = ?, unit = ?, updated_at = NOW() WHERE id = ? AND supplier_id = ?");
        $stmt->bind_param('sssii', $code, $desc, $unit, $id, $sid);
        $stmt->execute();
        $stmt->close();
        if (!supRows($conn, "SELECT id FROM noblecrm_supplier_item WHERE id = ? AND supplier_id = ?", 'ii', [$id, $sid])) {
            supRespond(false, 'This item no longer exists. Reload the list.');
        }
        supRespond(true, 'Item updated.', ['id' => $id]);
    }

    $stmt = $conn->prepare("INSERT INTO noblecrm_supplier_item (supplier_id, item_code, description, unit, is_active, created_by, created_at) VALUES (?, ?, ?, ?, 1, ?, NOW())");
    $stmt->bind_param('isssi', $sid, $code, $desc, $unit, $currentUserId);
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();
    supRespond(true, 'Item added.', ['id' => $newId]);
}

// ═════════════ ITEM: active / inactive ═════════════
if ($action === 'set_item_active') {
    supRequirePost();
    $id = intval($_POST['id'] ?? 0);
    $on = intval($_POST['active'] ?? 0) === 1 ? 1 : 0;
    $stmt = $conn->prepare("UPDATE noblecrm_supplier_item SET is_active = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param('ii', $on, $id);
    $stmt->execute();
    $stmt->close();
    supRespond(true, $on ? 'Item is active again.' : 'Item hidden from the BOM.');
}

// ═════════════ DELETE ITEM ═════════════
if ($action === 'delete_item') {
    supRequirePost();
    $id = intval($_POST['id'] ?? 0);
    $stmt = $conn->prepare("DELETE FROM noblecrm_supplier_item WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $gone = $stmt->affected_rows;
    $stmt->close();
    supRespond($gone === 1, $gone === 1 ? 'Item deleted.' : 'This item was already deleted.');
}

supRespond(false, 'Unknown action.');