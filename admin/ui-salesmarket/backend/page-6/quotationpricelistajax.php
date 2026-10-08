<?php
// admin/ui-salesmarket/backend/page-5/quotationpricelistajax.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SALES];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ─── Config bawat product category ───────────────────────────────────────────
$CATEGORIES = [
    'WARDROBE' => [
        'label' => 'Wardrobe',
        'group_label' => 'Door Type',
        'row_label' => 'Door Material',
        'has_size' => false,
        'has_sink' => false,
        'carcass' => [
            'PARTICLE_BOARD' => 'Particle Board',
            'MARINE_BOARD' => 'Marine Board',
            'PET' => 'PET (High Gloss/Matte)',
            'DUCO' => 'Duco',
            'ALUMINUM' => 'Aluminum',
        ],
    ],
    'KITCHEN' => [
        'label' => 'Kitchen',
        'group_label' => 'Door Panel Finish',
        'row_label' => 'Cabinet',
        'has_size' => true,
        'has_sink' => true,   // Kitchen Sink Costing
        'carcass' => [
            'PARTICLE_BOARD' => 'Particle Board',
            'MARINE_BOARD' => 'Marine Board',
            'ALUMINUM' => 'Aluminum',
        ],
    ],
];

// ─── Sink tub types (Kitchen Sink Costing) ───────────────────────────────────
$SINK_TYPES = [
    'SINGLE' => 'Single Tub Type (SUS 304)',
    'DOUBLE' => 'Double Tub Type (SUS 304)',
];

// ─── Drawer sizes ay nasa database (noblecrm_quotationpricelist_drawer_sizes) ──
function sizeLabel(int $min, ?int $max): string
{
    return $max === null ? "{$min}mm+" : "{$min}mm – {$max}mm";
}

function out(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

function intOrNull($v): ?int
{
    $v = trim((string) $v);
    return ($v !== '' && ctype_digit($v)) ? (int) $v : null;
}

$action = $_REQUEST['action'] ?? '';

try {
    switch ($action) {

        // ── META ────────────────────────────────────────────────────────────
        case 'meta':
            out(['success' => true, 'categories' => $CATEGORIES, 'sink_types' => $SINK_TYPES]);

        // ── LIST (rows + prices ng isang category) ──────────────────────────
        case 'list':
            $cat = $_GET['category'] ?? '';
            if (!isset($CATEGORIES[$cat])) {
                out(['success' => false, 'message' => 'Invalid category.'], 422);
            }

            $stmt = $conn->prepare(
                "SELECT id, group_name, row_name, max_depth, max_height, is_active
                 FROM noblecrm_quotationpricelist_rows
                 WHERE product_category = ? AND deleted_at IS NULL
                 ORDER BY sort_order, id"
            );
            $stmt->bind_param('s', $cat);
            $stmt->execute();
            $rows = [];
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
                $r['prices'] = [];
                $rows[(int) $r['id']] = $r;
            }

            $stmt = $conn->prepare(
                "SELECT p.row_id, p.carcass_code, p.price
                 FROM noblecrm_quotationpricelist p
                 JOIN noblecrm_quotationpricelist_rows r ON r.id = p.row_id
                 WHERE r.product_category = ? AND r.deleted_at IS NULL"
            );
            $stmt->bind_param('s', $cat);
            $stmt->execute();
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $p) {
                $rows[(int) $p['row_id']]['prices'][$p['carcass_code']] = $p['price'];
            }

            out(['success' => true, 'data' => array_values($rows)]);

        // ── SAVE ROW (create + update, kasama ang prices) ───────────────────
        case 'save':
            $id = (int) ($_POST['id'] ?? 0);
            $cat = $_POST['category'] ?? '';
            if (!isset($CATEGORIES[$cat])) {
                out(['success' => false, 'message' => 'Invalid category.'], 422);
            }
            $cfg = $CATEGORIES[$cat];
            $group = trim($_POST['group_name'] ?? '');
            $name = trim($_POST['row_name'] ?? '');
            $depth = $cfg['has_size'] ? intOrNull($_POST['max_depth'] ?? '') : null;
            $height = $cfg['has_size'] ? intOrNull($_POST['max_height'] ?? '') : null;
            $isActive = !empty($_POST['is_active']) ? 1 : 0;
            $posted = is_array($_POST['prices'] ?? null) ? $_POST['prices'] : [];

            if ($group === '' || $name === '' || mb_strlen($group) > 100 || mb_strlen($name) > 100) {
                out(['success' => false, 'message' => $cfg['group_label'] . ' and ' . $cfg['row_label'] . ' are required.'], 422);
            }

            // Validate prices (blank = walang presyo sa carcass na iyon)
            $prices = [];
            foreach ($cfg['carcass'] as $code => $label) {
                $v = trim((string) ($posted[$code] ?? ''));
                if ($v === '')
                    continue;
                if (!is_numeric($v) || (float) $v <= 0) {
                    out(['success' => false, 'message' => "Invalid price for $label."], 422);
                }
                $prices[$code] = round((float) $v, 2);
            }
            if (!$prices) {
                out(['success' => false, 'message' => 'Enter at least one price.'], 422);
            }

            // Duplicate check (same group + row name)
            $dup = $conn->prepare(
                "SELECT id FROM noblecrm_quotationpricelist_rows
                 WHERE product_category = ? AND group_name = ? AND row_name = ?
                   AND deleted_at IS NULL AND id <> ? LIMIT 1"
            );
            $dup->bind_param('sssi', $cat, $group, $name, $id);
            $dup->execute();
            if ($dup->get_result()->fetch_assoc()) {
                out(['success' => false, 'message' => 'This row already exists in that group.'], 409);
            }

            if ($id > 0) {
                $chk = $conn->prepare(
                    "SELECT id FROM noblecrm_quotationpricelist_rows
                     WHERE id = ? AND product_category = ? AND deleted_at IS NULL"
                );
                $chk->bind_param('is', $id, $cat);
                $chk->execute();
                if (!$chk->get_result()->fetch_assoc()) {
                    out(['success' => false, 'message' => 'Row not found.'], 404);
                }
            }

            $userId = $_SESSION['user_id'] ?? null;  // TODO: palitan ng actual session key mo

            $conn->begin_transaction();
            try {
                if ($id > 0) {
                    $stmt = $conn->prepare(
                        "UPDATE noblecrm_quotationpricelist_rows
                         SET group_name = ?, row_name = ?, max_depth = ?, max_height = ?, is_active = ?
                         WHERE id = ?"
                    );
                    $stmt->bind_param('ssiiii', $group, $name, $depth, $height, $isActive, $id);
                    $stmt->execute();
                    $rowId = $id;
                } else {
                    // Ilagay sa dulo ng parehong group; kung bagong group, sa dulo ng category
                    $s = $conn->prepare(
                        "SELECT MAX(sort_order) FROM noblecrm_quotationpricelist_rows
                         WHERE product_category = ? AND group_name = ? AND deleted_at IS NULL"
                    );
                    $s->bind_param('ss', $cat, $group);
                    $s->execute();
                    $max = $s->get_result()->fetch_row()[0];

                    if ($max !== null) {
                        $sort = (int) $max + 1;
                    } else {
                        $s = $conn->prepare(
                            "SELECT COALESCE(MAX(sort_order), 0) FROM noblecrm_quotationpricelist_rows
                             WHERE product_category = ?"
                        );
                        $s->bind_param('s', $cat);
                        $s->execute();
                        $sort = (int) $s->get_result()->fetch_row()[0] + 10;
                    }

                    $stmt = $conn->prepare(
                        "INSERT INTO noblecrm_quotationpricelist_rows
                         (product_category, group_name, row_name, max_depth, max_height, sort_order, is_active, created_by)
                         VALUES (?,?,?,?,?,?,?,?)"
                    );
                    $stmt->bind_param('sssiiiii', $cat, $group, $name, $depth, $height, $sort, $isActive, $userId);
                    $stmt->execute();
                    $rowId = $conn->insert_id;
                }

                $up = $conn->prepare(
                    "INSERT INTO noblecrm_quotationpricelist (row_id, carcass_code, price)
                     VALUES (?,?,?) ON DUPLICATE KEY UPDATE price = VALUES(price)"
                );
                $del = $conn->prepare(
                    "DELETE FROM noblecrm_quotationpricelist WHERE row_id = ? AND carcass_code = ?"
                );
                foreach ($cfg['carcass'] as $code => $label) {
                    if (isset($prices[$code])) {
                        $p = $prices[$code];
                        $up->bind_param('isd', $rowId, $code, $p);
                        $up->execute();
                    } else {
                        $del->bind_param('is', $rowId, $code);
                        $del->execute();
                    }
                }
                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollback();
                throw $e;
            }

            out(['success' => true, 'message' => $id > 0 ? 'Row updated.' : 'Row added.']);

        // ── DELETE (soft) ───────────────────────────────────────────────────
        case 'delete':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                out(['success' => false, 'message' => 'Invalid ID.'], 422);
            }
            $stmt = $conn->prepare(
                "UPDATE noblecrm_quotationpricelist_rows
                 SET deleted_at = NOW(), is_active = 0
                 WHERE id = ? AND deleted_at IS NULL"
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();
            out(['success' => true, 'message' => 'Row deleted.']);

        // ── DRAWER LIST (sizes + prices + labor ng category) ────────────────
        case 'drawer_list':
            $cat = $_GET['category'] ?? '';
            if (!isset($CATEGORIES[$cat])) {
                out(['success' => false, 'message' => 'Invalid category.'], 422);
            }

            $sizes = $conn->query(
                "SELECT id, min_width, max_width FROM noblecrm_quotationpricelist_drawer_sizes
                 WHERE deleted_at IS NULL ORDER BY min_width"
            )->fetch_all(MYSQLI_ASSOC);

            $stmt = $conn->prepare(
                "SELECT d.size_id, d.price, d.labor
                 FROM noblecrm_quotationpricelist_drawer d
                 JOIN noblecrm_quotationpricelist_drawer_sizes s ON s.id = d.size_id
                 WHERE d.product_category = ? AND s.deleted_at IS NULL"
            );
            $stmt->bind_param('s', $cat);
            $stmt->execute();
            $prices = [];
            $labors = [];
            foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $p) {
                $prices[$p['size_id']] = $p['price'];
                $labors[$p['size_id']] = $p['labor'];
            }
            out(['success' => true, 'sizes' => $sizes, 'data' => $prices, 'labors' => $labors]);

        // ── DRAWER SAVE (presyo ng lahat ng size para sa isang category) ────
        // Optional: labors[size_id]. Kapag hindi pinadala, hindi gagalawin ang existing labor
        // (bagong row = 0).
        case 'drawer_save':
            $cat = $_POST['category'] ?? '';
            if (!isset($CATEGORIES[$cat])) {
                out(['success' => false, 'message' => 'Invalid category.'], 422);
            }
            $sizes = $conn->query(
                "SELECT id, min_width, max_width FROM noblecrm_quotationpricelist_drawer_sizes
                 WHERE deleted_at IS NULL"
            )->fetch_all(MYSQLI_ASSOC);
            if (!$sizes) {
                out(['success' => false, 'message' => 'Add a size first.'], 422);
            }

            $posted = is_array($_POST['prices'] ?? null) ? $_POST['prices'] : [];
            $postedLabor = is_array($_POST['labors'] ?? null) ? $_POST['labors'] : [];
            $prices = [];
            $labors = [];   // null = huwag galawin ang existing labor
            foreach ($sizes as $sz) {
                $v = trim((string) ($posted[$sz['id']] ?? ''));
                if ($v === '')
                    continue;
                $lbl = sizeLabel((int) $sz['min_width'], $sz['max_width'] === null ? null : (int) $sz['max_width']);
                if (!is_numeric($v) || (float) $v <= 0) {
                    out(['success' => false, 'message' => "Invalid price for $lbl."], 422);
                }
                $prices[(int) $sz['id']] = round((float) $v, 2);

                if (array_key_exists($sz['id'], $postedLabor)) {
                    $lv = trim((string) $postedLabor[$sz['id']]);
                    if ($lv === '') {
                        $labors[(int) $sz['id']] = 0.0;
                    } elseif (!is_numeric($lv) || (float) $lv < 0) {
                        out(['success' => false, 'message' => "Invalid labor for $lbl."], 422);
                    } else {
                        $labors[(int) $sz['id']] = round((float) $lv, 2);
                    }
                }
            }
            if (!$prices) {
                out(['success' => false, 'message' => 'Enter at least one price.'], 422);
            }

            $userId = $_SESSION['user_id'] ?? null;  // TODO: palitan ng actual session key mo

            $conn->begin_transaction();
            try {
                $up = $conn->prepare(
                    "INSERT INTO noblecrm_quotationpricelist_drawer (product_category, size_id, price, labor, updated_by)
                     VALUES (?,?,?,?,?)
                     ON DUPLICATE KEY UPDATE price = VALUES(price), labor = COALESCE(?, labor), updated_by = VALUES(updated_by)"
                );
                $del = $conn->prepare(
                    "DELETE FROM noblecrm_quotationpricelist_drawer WHERE product_category = ? AND size_id = ?"
                );
                foreach ($sizes as $sz) {
                    $sid = (int) $sz['id'];
                    if (isset($prices[$sid])) {
                        $p = $prices[$sid];
                        $labUpd = $labors[$sid] ?? null;          // para sa existing row
                        $labIns = $labUpd ?? 0.0;                 // para sa bagong row
                        $up->bind_param('siddid', $cat, $sid, $p, $labIns, $userId, $labUpd);
                        $up->execute();
                    } else {
                        $del->bind_param('si', $cat, $sid);
                        $del->execute();
                    }
                }
                $conn->commit();
            } catch (Throwable $e) {
                $conn->rollback();
                throw $e;
            }
            out(['success' => true, 'message' => 'Drawer prices saved.']);

        // ── DRAWER SIZE SAVE (add + edit) ───────────────────────────────────
        case 'drawer_size_save':
            $id = (int) ($_POST['id'] ?? 0);
            $min = intOrNull($_POST['min_width'] ?? '');
            $max = intOrNull($_POST['max_width'] ?? '');   // blank = "and up"

            if ($min === null || $min <= 0) {
                out(['success' => false, 'message' => 'Min width is required.'], 422);
            }
            if ($max !== null && $max < $min) {
                out(['success' => false, 'message' => 'Max width must be greater than or equal to min width.'], 422);
            }

            // Bawal mag-overlap sa ibang size
            $hi = $max ?? 999999;
            $ov = $conn->prepare(
                "SELECT min_width, max_width FROM noblecrm_quotationpricelist_drawer_sizes
                 WHERE deleted_at IS NULL AND id <> ?
                   AND min_width <= ? AND COALESCE(max_width, 999999) >= ?
                 LIMIT 1"
            );
            $ov->bind_param('iii', $id, $hi, $min);
            $ov->execute();
            if ($o = $ov->get_result()->fetch_assoc()) {
                $lbl = sizeLabel((int) $o['min_width'], $o['max_width'] === null ? null : (int) $o['max_width']);
                out(['success' => false, 'message' => "Overlaps with existing size ($lbl)."], 409);
            }

            if ($id > 0) {
                $stmt = $conn->prepare(
                    "UPDATE noblecrm_quotationpricelist_drawer_sizes
                     SET min_width = ?, max_width = ? WHERE id = ? AND deleted_at IS NULL"
                );
                $stmt->bind_param('iii', $min, $max, $id);
            } else {
                $stmt = $conn->prepare(
                    "INSERT INTO noblecrm_quotationpricelist_drawer_sizes (min_width, max_width) VALUES (?,?)"
                );
                $stmt->bind_param('ii', $min, $max);
            }
            $stmt->execute();
            out(['success' => true, 'message' => $id > 0 ? 'Size updated.' : 'Size added.']);

        // ── DRAWER SIZE DELETE (soft) ───────────────────────────────────────
        case 'drawer_size_delete':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                out(['success' => false, 'message' => 'Invalid ID.'], 422);
            }
            $stmt = $conn->prepare(
                "UPDATE noblecrm_quotationpricelist_drawer_sizes
                 SET deleted_at = NOW() WHERE id = ? AND deleted_at IS NULL"
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();
            out(['success' => true, 'message' => 'Size deleted.']);

        // ── SINK LIST ───────────────────────────────────────────────────────
        case 'sink_list':
            $rows = $conn->query(
                "SELECT id, tub_type, item_size, price
                 FROM noblecrm_quotationpricelist_sinks
                 WHERE deleted_at IS NULL
                 ORDER BY tub_type, sort_order, id"
            )->fetch_all(MYSQLI_ASSOC);
            out(['success' => true, 'data' => $rows]);

        // ── SINK SAVE (add + edit) ──────────────────────────────────────────
        case 'sink_save':
            $id = (int) ($_POST['id'] ?? 0);
            $type = $_POST['tub_type'] ?? '';
            $size = trim($_POST['item_size'] ?? '');
            $v = trim((string) ($_POST['price'] ?? ''));

            if (!isset($SINK_TYPES[$type])) {
                out(['success' => false, 'message' => 'Invalid tub type.'], 422);
            }
            if ($size === '' || mb_strlen($size) > 50) {
                out(['success' => false, 'message' => 'Item size is required.'], 422);
            }
            if (!is_numeric($v) || (float) $v <= 0) {
                out(['success' => false, 'message' => 'Enter a valid price.'], 422);
            }
            $price = round((float) $v, 2);

            $dup = $conn->prepare(
                "SELECT id FROM noblecrm_quotationpricelist_sinks
                 WHERE tub_type = ? AND item_size = ? AND deleted_at IS NULL AND id <> ? LIMIT 1"
            );
            $dup->bind_param('ssi', $type, $size, $id);
            $dup->execute();
            if ($dup->get_result()->fetch_assoc()) {
                out(['success' => false, 'message' => 'This size already exists for that tub type.'], 409);
            }

            $userId = $_SESSION['user_id'] ?? null;  // TODO: palitan ng actual session key mo

            if ($id > 0) {
                $stmt = $conn->prepare(
                    "UPDATE noblecrm_quotationpricelist_sinks
                     SET tub_type = ?, item_size = ?, price = ? WHERE id = ? AND deleted_at IS NULL"
                );
                $stmt->bind_param('ssdi', $type, $size, $price, $id);
            } else {
                $s = $conn->prepare(
                    "SELECT COALESCE(MAX(sort_order), 0) FROM noblecrm_quotationpricelist_sinks
                     WHERE tub_type = ? AND deleted_at IS NULL"
                );
                $s->bind_param('s', $type);
                $s->execute();
                $sort = (int) $s->get_result()->fetch_row()[0] + 10;

                $stmt = $conn->prepare(
                    "INSERT INTO noblecrm_quotationpricelist_sinks (tub_type, item_size, price, sort_order, created_by)
                     VALUES (?,?,?,?,?)"
                );
                $stmt->bind_param('ssdii', $type, $size, $price, $sort, $userId);
            }
            $stmt->execute();
            out(['success' => true, 'message' => $id > 0 ? 'Sink updated.' : 'Sink added.']);

        // ── SINK DELETE (soft) ──────────────────────────────────────────────
        case 'sink_delete':
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                out(['success' => false, 'message' => 'Invalid ID.'], 422);
            }
            $stmt = $conn->prepare(
                "UPDATE noblecrm_quotationpricelist_sinks SET deleted_at = NOW()
                 WHERE id = ? AND deleted_at IS NULL"
            );
            $stmt->bind_param('i', $id);
            $stmt->execute();
            out(['success' => true, 'message' => 'Sink deleted.']);

        default:
            out(['success' => false, 'message' => 'Unknown action.'], 400);
    }
} catch (Throwable $e) {
    error_log('quotationpricelistajax: ' . $e->getMessage());
    out(['success' => false, 'message' => 'Server error.'], 500);
}