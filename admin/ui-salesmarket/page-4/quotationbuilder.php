<?php
// quotationbuilder.php  -  Quotation builder (sections, main items, accessories)

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SALES];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

include ROOT_PATH . '/admin/ui-salesmarket/page-4/quotation-helpers.php';

$selfUrl = strtok($_SERVER['REQUEST_URI'], '?');

/* ------------------------------------------------------------------
   HANDLE POST
------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Invalid request. Refresh the page and try again.');
    }

    $action   = $_POST['action'] ?? '';
    $pq       = (int)($_POST['quotation_id'] ?? 0);
    $redirect = $pq ? "$selfUrl?id=$pq" : $selfUrl;
    $msg      = 'Saved.';
    $afterCommit = [];   // image files to delete once the transaction succeeds

    try {
        $conn->begin_transaction();

        $quoteRow = $pq ? qOne($conn, 'SELECT * FROM noblecrm_quotations WHERE id = ?', 'i', [$pq]) : null;
        if ($action !== 'create_quotation' && !$quoteRow) {
            throw new QuoteError('Quotation not found.');
        }
        $rates = $quoteRow ? qRates($quoteRow) : ['lm.' => 0.0, 'sqm.' => 0.0];

        switch ($action) {

            case 'update_quotation': {
                $date = $_POST['quote_date'] ?? '';
                if (!DateTime::createFromFormat('Y-m-d', $date)) $date = date('Y-m-d');
                $loc   = trim($_POST['project_location'] ?? '');
                $scope = trim($_POST['project_scope'] ?? '');
                $rLm  = (float)($_POST['labor_rate_lm'] ?? 0);
                $rSqm = (float)($_POST['labor_rate_sqm'] ?? 0);
                if ($rLm <= 0 || $rSqm <= 0) throw new QuoteError('Both labor rates must be greater than zero.');
                // labor_rate (old column) is kept in sync with the lm. rate for older code such as the PDF
                qRun($conn, 'UPDATE noblecrm_quotations SET quote_date=?, project_location=?, project_scope=?, labor_rate=?, labor_rate_lm=?, labor_rate_sqm=? WHERE id=?',
                    'sssdddi', [$date, $loc, $scope, $rLm, $rLm, $rSqm, $pq]);
                qRecalcQuotation($conn, $pq, ['lm.' => $rLm, 'sqm.' => $rSqm]);
                $msg = 'Quotation updated and totals recalculated.';
                break;
            }

            case 'save_section': {
                $sid   = (int)($_POST['section_id'] ?? 0);
                $title = trim($_POST['title'] ?? '');
                if ($title === '') throw new QuoteError('Section title is required.');
                $img = qSaveImage($_FILES['image'] ?? []);

                if ($sid > 0) {
                    $old = qOne($conn, 'SELECT image_path FROM noblecrm_quotation_sections WHERE id = ? AND quotation_id = ?', 'ii', [$sid, $pq]);
                    if (!$old) { qDeleteImage($img); throw new QuoteError('Section not found.'); }
                    if ($img) {
                        qRun($conn, 'UPDATE noblecrm_quotation_sections SET title=?, image_path=? WHERE id=?', 'ssi', [$title, $img, $sid]);
                        $afterCommit[] = $old['image_path'];
                    } else {
                        qRun($conn, 'UPDATE noblecrm_quotation_sections SET title=? WHERE id=?', 'si', [$title, $sid]);
                    }
                    $msg = 'Section updated.';
                } else {
                    $next = qOne($conn, 'SELECT COALESCE(MAX(sort_order),0)+1 AS n FROM noblecrm_quotation_sections WHERE quotation_id = ?', 'i', [$pq])['n'];
                    qRun($conn, 'INSERT INTO noblecrm_quotation_sections (quotation_id, sort_order, title, image_path) VALUES (?,?,?,?)',
                        'iiss', [$pq, (int)$next, $title, $img]);
                    $sid = $conn->insert_id;
                    $msg = 'Section added.';
                }
                $redirect .= '#section-' . $sid;
                break;
            }

            case 'delete_section': {
                $sid = (int)($_POST['section_id'] ?? 0);
                $old = qOne($conn, 'SELECT image_path FROM noblecrm_quotation_sections WHERE id = ? AND quotation_id = ?', 'ii', [$sid, $pq]);
                if (!$old) throw new QuoteError('Section not found.');
                $afterCommit[] = $old['image_path'];
                qRun($conn, 'DELETE FROM noblecrm_quotation_sections WHERE id = ?', 'i', [$sid]);
                $msg = 'Section deleted.';
                break;
            }

            case 'save_main': {
                $sid = (int)($_POST['section_id'] ?? 0);
                if (!qOwnsSection($conn, $sid, $pq)) throw new QuoteError('Section not found.');

                $itemId   = (int)($_POST['item_id'] ?? 0);
                $desc     = trim($_POST['description'] ?? '');
                $unit     = $_POST['unit'] ?? '';
                $a        = (float)($_POST['dim_a'] ?? 0);
                $b        = (float)($_POST['dim_b'] ?? 0);
                $ov       = (float)($_POST['measurement'] ?? 0);
                $qty      = (float)($_POST['qty'] ?? 0);
                $price    = (float)str_replace(',', '', $_POST['material_cost'] ?? '0');
                $laborAmt = (float)str_replace(',', '', $_POST['labor_amount'] ?? '0');

                if ($desc === '') throw new QuoteError('Description is required.');
                // 'pc.' = flat price per piece (drawer, sink): price x qty, labor = labor_amount x qty (walang measurement)
                if (!in_array($unit, ['lm.', 'sqm.', 'pc.'], true)) throw new QuoteError('Invalid unit.');
                if ($qty <= 0) throw new QuoteError('Quantity must be greater than zero.');
                if ($price < 0) throw new QuoteError('Materials cost cannot be negative.');
                if ($laborAmt < 0) throw new QuoteError('Labor cannot be negative.');

                if ($unit === 'pc.') {
                    $meas = 1.0;
                    $da   = null;
                    $dbv  = null;
                } else {
                    $meas = $ov > 0 ? $ov : ($unit === 'sqm.' ? $a * $b / 1000000 : $a / 1000);
                    $meas = round($meas, 4);
                    if ($meas <= 0) throw new QuoteError('Enter the dimensions or a measurement.');

                    $da  = $a > 0 ? $a : null;
                    $dbv = $b > 0 ? $b : null;
                }

                // pc. = fixed labor per piece; lm./sqm. = auto (rate x measurement, computed in qRecalcSection)
                $lType = $unit === 'pc.' ? 'fixed' : 'auto';
                $lAmt  = $unit === 'pc.' ? $laborAmt : 0.0;

                if ($itemId > 0) {
                    if (!qOne($conn, "SELECT id FROM noblecrm_quotation_items WHERE id=? AND section_id=? AND item_type='main'", 'ii', [$itemId, $sid])) {
                        throw new QuoteError('Item not found.');
                    }
                    qRun($conn, 'UPDATE noblecrm_quotation_items SET description=?, dim_a_mm=?, dim_b_mm=?, measurement=?, unit=?, qty=?, unit_price=?, labor_type=?, labor_amount=? WHERE id=?',
                        'sdddsddsdi', [$desc, $da, $dbv, $meas, $unit, $qty, $price, $lType, $lAmt, $itemId]);
                    $msg = 'Main item updated.';
                } else {
                    $next = (int)qOne($conn, 'SELECT COALESCE(MAX(sort_order),0)+1 AS n FROM noblecrm_quotation_items WHERE section_id = ?', 'i', [$sid])['n'];
                    qRun($conn, "INSERT INTO noblecrm_quotation_items
                                (section_id,item_type,sort_order,description,dim_a_mm,dim_b_mm,measurement,unit,qty,unit_price,uses_measurement,labor_type,labor_amount)
                              VALUES (?, 'main', ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)",
                        'iisdddsddsd', [$sid, $next, $desc, $da, $dbv, $meas, $unit, $qty, $price, $lType, $lAmt]);
                    $msg = 'Main item added.';
                }
                qRecalcSection($conn, $sid, $rates);
                $redirect .= '#section-' . $sid;
                break;
            }

            case 'save_accessory': {
                $sid = (int)($_POST['section_id'] ?? 0);
                if (!qOwnsSection($conn, $sid, $pq)) throw new QuoteError('Section not found.');

                $itemId = (int)($_POST['item_id'] ?? 0);
                $qty    = (float)($_POST['qty'] ?? 0);
                $meas   = (float)($_POST['measurement'] ?? 0);
                if ($qty <= 0) throw new QuoteError('Quantity must be greater than zero.');

                if ($itemId > 0) {
                    $it = qOne($conn, "SELECT * FROM noblecrm_quotation_items WHERE id=? AND section_id=? AND item_type='accessory'", 'ii', [$itemId, $sid]);
                    if (!$it) throw new QuoteError('Item not found.');
                    if ($it['uses_measurement'] && $meas <= 0) throw new QuoteError('Enter a measurement for this accessory.');
                    $m = $it['uses_measurement'] ? round($meas, 4) : null;
                    qRun($conn, 'UPDATE noblecrm_quotation_items SET qty=?, measurement=? WHERE id=?', 'ddi', [$qty, $m, $itemId]);
                    $msg = 'Accessory updated.';
                } else {
                    $acc = qOne($conn, 'SELECT * FROM noblecrm_accessories WHERE id = ?', 'i', [(int)($_POST['accessory_id'] ?? 0)]);
                    if (!$acc) throw new QuoteError('Pick an accessory from the list.');
                    $uses = (int)$acc['uses_measurement'];
                    if ($uses && $meas <= 0) throw new QuoteError('Enter a measurement for this accessory.');
                    $m    = $uses ? round($meas, 4) : null;
                    $grp  = ($acc['labor_type'] === 'group' && $acc['group_name'] !== '') ? $acc['group_name'] : null;
                    $next = (int)qOne($conn, 'SELECT COALESCE(MAX(sort_order),0)+1 AS n FROM noblecrm_quotation_items WHERE section_id = ?', 'i', [$sid])['n'];

                    qRun($conn, "INSERT INTO noblecrm_quotation_items
                                (section_id,item_type,sort_order,accessory_id,description,measurement,unit,qty,unit_price,uses_measurement,labor_type,labor_amount,group_name)
                              VALUES (?, 'accessory', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                        'iiisdsddisds',
                        [$sid, $next, (int)$acc['id'], $acc['name'], $m, $acc['unit'], $qty, (float)$acc['unit_price'],
                         $uses, $acc['labor_type'], (float)$acc['labor_amount'], $grp]);
                    $msg = 'Accessory added.';
                }
                qRecalcSection($conn, $sid, $rates);
                $redirect .= '#section-' . $sid;
                break;
            }

            case 'delete_item': {
                $it = qOne($conn, 'SELECT i.id, i.section_id FROM noblecrm_quotation_items i
                                JOIN noblecrm_quotation_sections s ON s.id = i.section_id
                                WHERE i.id = ? AND s.quotation_id = ?', 'ii', [(int)($_POST['item_id'] ?? 0), $pq]);
                if (!$it) throw new QuoteError('Item not found.');
                qRun($conn, 'DELETE FROM noblecrm_quotation_items WHERE id = ?', 'i', [(int)$it['id']]);
                qRecalcSection($conn, (int)$it['section_id'], $rates);
                $redirect .= '#section-' . $it['section_id'];
                $msg = 'Item deleted.';
                break;
            }

            default:
                throw new QuoteError('Unknown action.');
        }

        $conn->commit();
        foreach ($afterCommit as $f) qDeleteImage($f);
        qFlash('ok', $msg);
        qGo($redirect);
    } catch (QuoteError $ex) {
        $conn->rollback();
        qFlash('err', $ex->getMessage());
        qGo($redirect);
    } catch (mysqli_sql_exception $ex) {
        $conn->rollback();
        error_log('quotation: ' . $ex->getMessage());
        qFlash('err', 'Database error. Please try again.');
        qGo($redirect);
    }
}

/* ------------------------------------------------------------------
   LOAD DATA
------------------------------------------------------------------- */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$qid   = (int)($_GET['id'] ?? 0);
$quote = $qid ? qOne($conn, 'SELECT * FROM noblecrm_quotations WHERE id = ?', 'i', [$qid]) : null;
if (!$quote) {
    qFlash('err', 'Quotation not found.');
    qGo($quoteListUrl);
}
$rates = qRates($quote);

// Builder data
$sections = $itemsBySection = $catalog = [];
$grandTotal = 0.0;
$editId = (int)($_GET['edit'] ?? 0);
if ($quote) {
    $sections = qRows($conn, 'SELECT * FROM noblecrm_quotation_sections WHERE quotation_id = ? ORDER BY sort_order, id', 'i', [$qid]);
    foreach (qRows($conn, 'SELECT i.* FROM noblecrm_quotation_items i
                          JOIN noblecrm_quotation_sections s ON s.id = i.section_id
                         WHERE s.quotation_id = ? ORDER BY i.sort_order, i.id', 'i', [$qid]) as $it) {
        $itemsBySection[$it['section_id']][] = $it;
        $grandTotal += (float)$it['material_total'] + (float)$it['labor_total'];
    }
    $catalog = qRows($conn, 'SELECT * FROM noblecrm_accessories ORDER BY name');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
</head>

<body class="bg-slate-100">
    <main class="ml-56 min-h-screen p-8">

        <?php if ($flash): ?>
            <div role="status"
                 class="mb-6 rounded-md border px-4 py-3 text-sm <?= $flash[0] === 'ok'
                    ? 'border-emerald-300 bg-emerald-50 text-emerald-800'
                    : 'border-red-300 bg-red-50 text-red-800' ?>">
                <?= e($flash[1]) ?>
            </div>
        <?php endif; ?>



        <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
            <div>
                <a href="<?= e($quoteListUrl) ?>" class="text-lg text-black underline hover:text-slate-900">All Quotations <i class="fa-solid fa-circle-arrow-left" style="color: rgb(0, 0, 0);"></i></a>
                <h1 class="mt-1 text-2xl font-semibold text-slate-900"><?= e($quote['client_name']) ?></h1>
                <p class="text-sm text-slate-600"><?= e($quote['project_name']) ?></p>
            </div>
            <div class="text-right">
                <div class="text-sm text-slate-600">Grand total</div>
                <div class="text-3xl font-semibold tabular-nums text-slate-900"><?= peso($grandTotal) ?></div>
                <a href="<?= e(rtrim(BASE_URL, '/') . '/quotationpdf?id=' . $qid) ?>"
                   class="mt-3 inline-block rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300">
                    Export PDF
                </a>
            </div>
        </div>

        <details class="mb-8 rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
            <summary class="cursor-pointer px-6 py-4 text-sm font-medium text-slate-800">
                Quotation details (labor rate: lm. <?= peso($rates['lm.']) ?> &middot; sqm. <?= peso($rates['sqm.']) ?>)
            </summary>
            <form method="post" action="<?= e($selfUrl) ?>" class="grid grid-cols-1 gap-4 border-t border-slate-200 p-6 md:grid-cols-5">
                <?= qHidden('csrf', $csrf) ?><?= qHidden('action', 'update_quotation') ?><?= qHidden('quotation_id', $qid) ?>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="uq_client">Client</label>
                    <input id="uq_client" readonly value="<?= e($quote['client_name']) ?>" class="<?= $inputCls ?> bg-slate-100">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="uq_project">Project</label>
                    <input id="uq_project" readonly value="<?= e($quote['project_name']) ?>" class="<?= $inputCls ?> bg-slate-100">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="uq_date">Date</label>
                    <input id="uq_date" name="quote_date" type="date" value="<?= e($quote['quote_date']) ?>" class="<?= $inputCls ?>">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="uq_rate_lm">Labor rate per lm.</label>
                    <input id="uq_rate_lm" name="labor_rate_lm" type="number" step="0.01" min="0.01" value="<?= e($rates['lm.']) ?>" class="<?= $inputCls ?>">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="uq_rate_sqm">Labor rate per sqm.</label>
                    <input id="uq_rate_sqm" name="labor_rate_sqm" type="number" step="0.01" min="0.01" value="<?= e($rates['sqm.']) ?>" class="<?= $inputCls ?>">
                </div>
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="uq_loc">Location</label>
                    <input id="uq_loc" name="project_location" maxlength="255" value="<?= e($quote['project_location'] ?? '') ?>" class="<?= $inputCls ?>">
                </div>
                <div class="md:col-span-3">
                    <label class="mb-1 block text-sm font-medium text-slate-700" for="uq_scope">Scope</label>
                    <input id="uq_scope" name="project_scope" maxlength="255" value="<?= e($quote['project_scope'] ?? '') ?>" class="<?= $inputCls ?>"
                           placeholder="Kitchen L-shape and pantry - supply and installation">
                </div>
                <div class="md:col-span-5 flex items-center gap-4">
                    <button class="<?= $btnCls ?>">Save details</button>
                    <span class="text-xs text-slate-500">Changing the labor rate recalculates every section in this quotation.</span>
                </div>
            </form>
        </details>

        <?php if (!$sections): ?>
            <div class="mb-8 rounded-lg border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-600">
                No sections yet. Add the first one below, for example "Kitchen Tall Cabinet".
            </div>
        <?php endif; ?>

        <?php foreach ($sections as $si => $sec):
            $n     = $si + 1;
            $sid   = (int)$sec['id'];
            $its   = $itemsBySection[$sid] ?? [];
            $mains = array_values(array_filter($its, fn($i) => $i['item_type'] === 'main'));
            $accs  = array_values(array_filter($its, fn($i) => $i['item_type'] === 'accessory'));

            // pc. main items (drawer, sink) get their own block under the main items, same as the PDF.
            // Drawer = n.1, Accessories = n.2 (or n.1 kung walang drawer).
            $pcs   = array_values(array_filter($mains, fn($i) => $i['unit'] === 'pc.'));
            $mains = array_values(array_filter($mains, fn($i) => $i['unit'] !== 'pc.'));
            $pcNo  = $n . '.1';
            $accNo = $n . '.' . ($pcs ? 2 : 1);
            $pcLabel = 'Drawer';
            foreach ($pcs as $p) {
                if (stripos(ltrim((string)$p['description']), 'drawer') !== 0) { $pcLabel = 'Drawer & others'; break; }
            }

            // Keep each labor group together so its labor cell can span the rows.
            $ordered = []; $seen = [];
            foreach ($accs as $it) {
                $g = $it['labor_type'] === 'group' ? (string)$it['group_name'] : '';
                if ($g === '') { $ordered[] = $it; continue; }
                if (isset($seen[$g])) continue;
                $seen[$g] = true;
                foreach ($accs as $m) {
                    if ($m['labor_type'] === 'group' && (string)$m['group_name'] === $g) $ordered[] = $m;
                }
            }
            $groupInfo = [];
            foreach ($ordered as $it) {
                if ($it['labor_type'] !== 'group') continue;
                $g = (string)$it['group_name'];
                $groupInfo[$g]['size']  = ($groupInfo[$g]['size'] ?? 0) + 1;
                $groupInfo[$g]['mat']   = ($groupInfo[$g]['mat'] ?? 0) + (float)$it['material_total'];
                $groupInfo[$g]['labor'] = ($groupInfo[$g]['labor'] ?? 0) + (float)$it['labor_total'];
                $groupInfo[$g]['anchor'] = ($groupInfo[$g]['anchor'] ?? false) || $it['uses_measurement'];
            }

            $secTotal = 0.0;
            foreach ($its as $it) $secTotal += (float)$it['material_total'] + (float)$it['labor_total'];

            $editItem = null;
            foreach ($its as $it) if ((int)$it['id'] === $editId) $editItem = $it;
            $editMain = $editItem && $editItem['item_type'] === 'main';
            $editAcc  = $editItem && $editItem['item_type'] === 'accessory';
            $m = $editMain ? $editItem : null;
            $hasDims = $m && ($m['dim_a_mm'] !== null);
            $anchorUrl = e($selfUrl) . '?id=' . $qid;
        ?>
        <section id="section-<?= $sid ?>" class="mb-8 scroll-mt-6 rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
            <header class="flex flex-wrap items-center justify-between gap-4 rounded-t-lg border-b border-slate-200 bg-slate-200/70 px-6 py-3">
                <div class="flex items-center gap-4">
                    <?php if ($sec['image_path']): ?>
                        <img src="<?= e(qUploadUrl($sec['image_path'])) ?>" alt="" class="h-14 w-20 rounded border border-slate-300 bg-white object-contain">
                    <?php endif; ?>
                    <h2 class="text-base font-semibold text-slate-900"><?= $n ?>.0&nbsp; <?= e($sec['title']) ?></h2>
                </div>
                <div class="flex items-center gap-4 text-sm">
                    <span class="tabular-nums font-medium text-slate-900"><?= peso($secTotal) ?></span>
                    <?php qPostBtn('delete_section', ['quotation_id' => $qid, 'section_id' => $sid], 'Delete section',
                        'Delete this section and all its items?'); ?>
                </div>
            </header>

            <!-- Main items -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 text-slate-600">
                        <tr>
                            <th class="px-4 py-2 font-medium">No.</th>
                            <th class="px-4 py-2 font-medium">Description</th>
                            <th class="px-4 py-2 text-right font-medium">Measurement</th>
                            <th class="px-4 py-2 font-medium">Unit</th>
                            <th class="px-4 py-2 text-right font-medium">Qty</th>
                            <th class="px-4 py-2 text-right font-medium">Materials</th>
                            <th class="px-4 py-2 text-right font-medium">Labor</th>
                            <th class="px-4 py-2 text-right font-medium">Total</th>
                            <th class="px-4 py-2 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php if (!$mains): ?>
                            <tr><td colspan="9" class="px-4 py-4 text-slate-500">No main items yet.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($mains as $mi => $it): ?>
                            <tr class="<?= (int)$it['id'] === $editId ? 'bg-amber-50' : '' ?>">
                                <td class="px-4 py-2 text-slate-700"><?= $mi === 0 ? $n . '.0' : '' ?></td>
                                <td class="px-4 py-2 text-slate-900">
                                    <?= e($it['description']) ?>
                                    <?php if ($it['dim_a_mm'] !== null): ?>
                                        <span class="block text-xs text-slate-500">
                                            <?= qFmtMeas($it['dim_a_mm']) ?><?= $it['dim_b_mm'] !== null ? ' × ' . qFmtMeas($it['dim_b_mm']) : '' ?> mm
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-2 text-right tabular-nums"><?= $it['unit'] === 'pc.' ? '' : qFmtMeas($it['measurement']) ?></td>
                                <td class="px-4 py-2"><?= e($it['unit']) ?></td>
                                <td class="px-4 py-2 text-right tabular-nums"><?= qFmtMeas($it['qty']) ?></td>
                                <td class="px-4 py-2 text-right tabular-nums"><?= peso($it['material_total']) ?></td>
                                <td class="px-4 py-2 text-right tabular-nums"><?= peso($it['labor_total']) ?></td>
                                <td class="px-4 py-2 text-right tabular-nums font-medium"><?= peso($it['material_total'] + $it['labor_total']) ?></td>
                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                    <a href="<?= $anchorUrl ?>&edit=<?= (int)$it['id'] ?>#section-<?= $sid ?>" class="text-slate-700 underline hover:text-slate-900">Edit</a>
                                    <span class="ml-3"><?php qPostBtn('delete_item', ['quotation_id' => $qid, 'item_id' => $it['id']], 'Delete', 'Delete this item?'); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <!-- Drawer / pc. items -->
                        <?php if ($pcs): ?>
                            <tr class="bg-slate-50">
                                <td class="px-4 py-2 font-medium text-slate-700"><?= $pcNo ?></td>
                                <td colspan="8" class="px-4 py-2 font-semibold text-slate-900"><?= e($pcLabel) ?></td>
                            </tr>
                            <?php foreach ($pcs as $pi => $it): ?>
                                <tr class="<?= (int)$it['id'] === $editId ? 'bg-amber-50' : '' ?>">
                                    <td class="px-4 py-2 text-slate-700"><?= $pcNo ?>.<?= $pi + 1 ?></td>
                                    <td class="px-4 py-2 text-slate-900"><?= e($it['description']) ?></td>
                                    <td class="px-4 py-2 text-right tabular-nums"></td>
                                    <td class="px-4 py-2"><?= e($it['unit']) ?></td>
                                    <td class="px-4 py-2 text-right tabular-nums"><?= qFmtMeas($it['qty']) ?></td>
                                    <td class="px-4 py-2 text-right tabular-nums"><?= peso($it['material_total']) ?></td>
                                    <td class="px-4 py-2 text-right tabular-nums"><?= $it['labor_total'] > 0 ? peso($it['labor_total']) : '' ?></td>
                                    <td class="px-4 py-2 text-right tabular-nums font-medium"><?= peso($it['material_total'] + $it['labor_total']) ?></td>
                                    <td class="whitespace-nowrap px-4 py-2 text-right">
                                        <a href="<?= $anchorUrl ?>&edit=<?= (int)$it['id'] ?>#section-<?= $sid ?>" class="text-slate-700 underline hover:text-slate-900">Edit</a>
                                        <span class="ml-3"><?php qPostBtn('delete_item', ['quotation_id' => $qid, 'item_id' => $it['id']], 'Delete', 'Delete this item?'); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <!-- Accessories -->
                        <tr class="bg-slate-50">
                            <td class="px-4 py-2 font-medium text-slate-700"><?= $accNo ?></td>
                            <td colspan="8" class="px-4 py-2 font-semibold text-slate-900">Accessories</td>
                        </tr>
                        <?php if (!$ordered): ?>
                            <tr><td colspan="9" class="px-4 py-4 text-slate-500">No accessories yet.</td></tr>
                        <?php endif; ?>
                        <?php
                        $k = 0; $printedGroups = [];
                        foreach ($ordered as $it):
                            $k++;
                            $g = $it['labor_type'] === 'group' ? (string)$it['group_name'] : '';
                            $isGroup = $g !== '';
                            $firstOfGroup = $isGroup && !isset($printedGroups[$g]);
                            $printedGroups[$g] = true;
                        ?>
                            <tr class="<?= (int)$it['id'] === $editId ? 'bg-amber-50' : '' ?>">
                                <td class="px-4 py-2 text-slate-700"><?= $accNo ?>.<?= $k ?></td>
                                <td class="px-4 py-2 text-slate-900"><?= e($it['description']) ?></td>
                                <td class="px-4 py-2 text-right tabular-nums"><?= qFmtMeas($it['measurement']) ?></td>
                                <td class="px-4 py-2"><?= e($it['unit']) ?></td>
                                <td class="px-4 py-2 text-right tabular-nums"><?= qFmtMeas($it['qty']) ?></td>
                                <td class="px-4 py-2 text-right tabular-nums"><?= peso($it['material_total']) ?></td>
                                <?php if (!$isGroup): ?>
                                    <td class="px-4 py-2 text-right tabular-nums"><?= $it['labor_total'] > 0 ? peso($it['labor_total']) : '' ?></td>
                                    <td class="px-4 py-2 text-right tabular-nums font-medium"><?= peso($it['material_total'] + $it['labor_total']) ?></td>
                                <?php elseif ($firstOfGroup): ?>
                                    <td rowspan="<?= (int)$groupInfo[$g]['size'] ?>" class="border-l border-slate-200 px-4 py-2 text-right align-middle tabular-nums">
                                        <?= peso($groupInfo[$g]['labor']) ?>
                                        <span class="block text-xs text-slate-500">shared: <?= e($g) ?></span>
                                    </td>
                                    <td rowspan="<?= (int)$groupInfo[$g]['size'] ?>" class="px-4 py-2 text-right align-middle tabular-nums font-medium">
                                        <?= peso($groupInfo[$g]['mat'] + $groupInfo[$g]['labor']) ?>
                                    </td>
                                <?php endif; ?>
                                <td class="whitespace-nowrap px-4 py-2 text-right">
                                    <a href="<?= $anchorUrl ?>&edit=<?= (int)$it['id'] ?>#section-<?= $sid ?>" class="text-slate-700 underline hover:text-slate-900">Edit</a>
                                    <span class="ml-3"><?php qPostBtn('delete_item', ['quotation_id' => $qid, 'item_id' => $it['id']], 'Delete', 'Delete this item?'); ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php foreach ($groupInfo as $gName => $gi): if (!$gi['anchor']): ?>
                <p class="mx-6 mt-3 rounded border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                    The group "<?= e($gName) ?>" has no item with a measurement, so no labor is charged for it.
                    Add an accessory that has a measurement (for example the strip light).
                </p>
            <?php endif; endforeach; ?>

            <!-- Action buttons (nagbubukas ng modal) -->
            <div class="flex flex-wrap items-center gap-3 border-t border-slate-200 p-4">
                <button type="button" data-open="dlg-main-<?= $sid ?>" class="<?= $btnCls ?>">Add main item</button>
                <button type="button" data-open="dlg-acc-<?= $sid ?>" class="<?= $btnCls ?>">Add accessory</button>
                <button type="button" data-open="dlg-sec-<?= $sid ?>"
                        class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50">
                    Section title and image
                </button>
            </div>

                        <!-- ============ MODAL: Main item (landscape) ============ -->
            <dialog id="dlg-main-<?= $sid ?>"
                    class="m-auto w-full max-w-5xl overflow-y-auto rounded-lg p-0 shadow-xl backdrop:bg-slate-900/50" style="max-height:90vh;"
                    <?= $editMain ? 'data-autoopen data-close-url="' . $anchorUrl . '#section-' . $sid . '"' : '' ?>>
                <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3">
                    <h3 class="text-base font-semibold text-slate-900"><?= $editMain ? 'Edit main item' : 'Add main item' ?></h3>
                    <button type="button" data-close class="text-2xl leading-none text-slate-500 hover:text-slate-900" aria-label="Close">&times;</button>
                </div>
                <form method="post" action="<?= e($selfUrl) ?>" data-kind="main" data-rate-lm="<?= e($rates['lm.']) ?>" data-rate-sqm="<?= e($rates['sqm.']) ?>"
                      class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2">
                    <?= qHidden('csrf', $csrf) ?><?= qHidden('action', 'save_main') ?>
                    <?= qHidden('quotation_id', $qid) ?><?= qHidden('section_id', $sid) ?>
                    <?= qHidden('item_id', $editMain ? $m['id'] : 0) ?>

                    <!-- ───── KALIWA: Price list ───── -->
                    <details data-plbox class="self-start rounded-md border border-slate-200 bg-slate-50">
                        <summary class="cursor-pointer px-3 py-2 text-sm font-medium text-slate-700">Use price list (optional)</summary>
                        <div class="space-y-3 border-t border-slate-200 p-3">
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-600">Product</label>
                                    <select data-pl="cat" class="<?= $inputCls ?>"><option value="">Select product</option></select>
                                </div>
                                <div>
                                    <label data-pl-lbl="group" class="mb-1 block text-xs font-medium text-slate-600">Group</label>
                                    <select data-pl="group" class="<?= $inputCls ?>"><option value="">Select</option></select>
                                </div>
                                <div>
                                    <label data-pl-lbl="row" class="mb-1 block text-xs font-medium text-slate-600">Item</label>
                                    <select data-pl="row" class="<?= $inputCls ?>"><option value="">Select</option></select>
                                </div>
                                <div data-pl-carcass-wrap>
                                    <label class="mb-1 block text-xs font-medium text-slate-600">Carcass</label>
                                    <select data-pl="carcass" class="<?= $inputCls ?>"><option value="">Select</option></select>
                                </div>
                            </div>

                            <div data-pl-size class="hidden">
                                <div class="grid grid-cols-2 gap-3">
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-600">Actual depth (mm)</label>
                                        <input data-pl="depth" type="number" min="0" class="<?= $inputCls ?>">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-xs font-medium text-slate-600">Actual height (mm)</label>
                                        <input data-pl="height" type="number" min="0" class="<?= $inputCls ?>">
                                    </div>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 items-end gap-3">
                                <div>
                                    <label class="mb-1 block text-xs font-medium text-slate-600">Non-standard markup (%)</label>
                                    <input data-pl="markup" type="number" step="0.01" min="0" value="20" class="<?= $inputCls ?>">
                                </div>
                                <div class="pb-2 text-right">
                                    <button type="button" data-pl-clear class="text-xs text-slate-600 underline hover:text-slate-900">Clear price list</button>
                                </div>
                            </div>

                            <p data-pl-result class="rounded bg-white px-3 py-2 text-sm text-slate-700 ring-1 ring-slate-200">
                                Pick the product, group, item and carcass to get the price.
                            </p>
                        </div>
                    </details>

                    <!-- ───── KANAN: Item fields ───── -->
                    <div class="space-y-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Description</label>
                            <input name="description" required maxlength="255" value="<?= e($m['description'] ?? '') ?>" class="<?= $inputCls ?>"
                                   placeholder="Tall cabinet in PET marine finish with swing door">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Unit</label>
                                <select name="unit" class="<?= $inputCls ?>">
                                    <?php foreach (['lm.', 'sqm.', 'pc.'] as $u): ?>
                                        <option value="<?= e($u) ?>" <?= ($m['unit'] ?? 'sqm.') === $u ? 'selected' : '' ?>><?= e($u) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Qty</label>
                                <input name="qty" type="number" step="0.01" min="0.01" value="<?= e($m ? qFmtMeas($m['qty']) : '1') ?>" class="<?= $inputCls ?>">
                            </div>
                        </div>
                        <div data-dims-wrap class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Length (mm)</label>
                                <input name="dim_a" type="number" step="0.01" min="0" value="<?= e($m ? qFmtMeas($m['dim_a_mm']) : '') ?>" class="<?= $inputCls ?>">
                            </div>
                            <div data-b-wrap>
                                <label class="mb-1 block text-sm font-medium text-slate-700">Width (mm)</label>
                                <input name="dim_b" type="number" step="0.01" min="0" value="<?= e($m ? qFmtMeas($m['dim_b_mm']) : '') ?>" class="<?= $inputCls ?>">
                            </div>
                        </div>
                        <div data-ov-wrap>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Measurement override (optional)</label>
                            <input name="measurement" type="number" step="0.0001" min="0"
                                   value="<?= e($m && !$hasDims && $m['unit'] !== 'pc.' ? qFmtMeas($m['measurement']) : '') ?>" class="<?= $inputCls ?>">
                            <p class="mt-1 text-xs text-slate-500">Leave blank to calculate from the dimensions. lm. uses the length; sqm. uses length × width.</p>
                        </div>
                        <div>
                            <label data-mat-lbl class="mb-1 block text-sm font-medium text-slate-700">Materials cost per unit (× measurement × qty)</label>
                            <input name="material_cost" type="number" step="0.01" min="0" required value="<?= e($m['unit_price'] ?? '') ?>" class="<?= $inputCls ?>">
                        </div>
                        <div data-labor-wrap class="hidden">
                            <label class="mb-1 block text-sm font-medium text-slate-700">Labor per piece (× qty)</label>
                            <input name="labor_amount" type="number" step="0.01" min="0"
                                   value="<?= e($m && $m['unit'] === 'pc.' ? $m['labor_amount'] : '') ?>" class="<?= $inputCls ?>">
                        </div>
                    </div>

                    <!-- ───── ILALIM: buong lapad ───── -->
                    <p data-preview class="rounded bg-slate-100 px-3 py-2 text-sm text-slate-700 md:col-span-2"></p>
                    <div class="flex items-center gap-3 md:col-span-2">
                        <button class="<?= $btnCls ?>"><?= $editMain ? 'Save changes' : 'Add main item' ?></button>
                        <button type="button" data-close class="text-sm text-slate-600 underline">Cancel</button>
                    </div>
                </form>
            </dialog>

            <!-- ============ MODAL: Accessory ============ -->
            <dialog id="dlg-acc-<?= $sid ?>"
                    class="m-auto w-full max-w-md overflow-y-auto rounded-lg p-0 shadow-xl backdrop:bg-slate-900/50" style="max-height:90vh;"
                    <?= $editAcc ? 'data-autoopen data-close-url="' . $anchorUrl . '#section-' . $sid . '"' : '' ?>>
                <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3">
                    <h3 class="text-base font-semibold text-slate-900"><?= $editAcc ? 'Edit accessory' : 'Add accessory' ?></h3>
                    <button type="button" data-close class="text-2xl leading-none text-slate-500 hover:text-slate-900" aria-label="Close">&times;</button>
                </div>
                <form method="post" action="<?= e($selfUrl) ?>" data-kind="acc" data-uses="<?= $editAcc ? (int)$editItem['uses_measurement'] : '' ?>"
                      class="space-y-3 p-4">
                    <?= qHidden('csrf', $csrf) ?><?= qHidden('action', 'save_accessory') ?>
                    <?= qHidden('quotation_id', $qid) ?><?= qHidden('section_id', $sid) ?>
                    <?= qHidden('item_id', $editAcc ? $editItem['id'] : 0) ?>

                    <?php if ($editAcc): ?>
                        <p class="text-sm font-medium text-slate-900"><?= e($editItem['description']) ?></p>
                        <p class="text-xs text-slate-500">To use a different accessory, delete this one and add it again.</p>
                    <?php else: ?>
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Accessory</label>
                            <select name="accessory_id" required class="<?= $inputCls ?>">
                                <option value="">Select an accessory</option>
                                <?php foreach ($catalog as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>"
                                            data-unit="<?= e($c['unit']) ?>" data-price="<?= e($c['unit_price']) ?>"
                                            data-uses="<?= (int)$c['uses_measurement'] ?>" data-ltype="<?= e($c['labor_type']) ?>"
                                            data-lamt="<?= e($c['labor_amount']) ?>" data-group="<?= e($c['group_name']) ?>">
                                        <?= e($c['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (!$catalog): ?>
                                <p class="mt-1 text-xs text-amber-800">The catalog is empty. Add accessories in the accessories page first.</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-700">Qty</label>
                            <input name="qty" type="number" step="0.01" min="0.01" value="<?= e($editAcc ? qFmtMeas($editItem['qty']) : '1') ?>" class="<?= $inputCls ?>">
                        </div>
                        <div data-meas-wrap class="hidden">
                            <label class="mb-1 block text-sm font-medium text-slate-700">Measurement</label>
                            <input name="measurement" type="number" step="0.0001" min="0"
                                   value="<?= e($editAcc ? qFmtMeas($editItem['measurement']) : '') ?>" class="<?= $inputCls ?>">
                        </div>
                    </div>
                    <p data-preview class="rounded bg-slate-100 px-3 py-2 text-sm text-slate-700"></p>
                    <div class="flex items-center gap-3">
                        <button class="<?= $btnCls ?>"><?= $editAcc ? 'Save changes' : 'Add accessory' ?></button>
                        <button type="button" data-close class="text-sm text-slate-600 underline">Cancel</button>
                    </div>
                </form>
            </dialog>

            <!-- ============ MODAL: Section title and image ============ -->
            <dialog id="dlg-sec-<?= $sid ?>"
                    class="m-auto w-full max-w-lg overflow-y-auto rounded-lg p-0 shadow-xl backdrop:bg-slate-900/50" style="max-height:90vh;">
                <div class="sticky top-0 z-10 flex items-center justify-between border-b border-slate-200 bg-white px-4 py-3">
                    <h3 class="text-base font-semibold text-slate-900">Section title and image</h3>
                    <button type="button" data-close class="text-2xl leading-none text-slate-500 hover:text-slate-900" aria-label="Close">&times;</button>
                </div>
                <form method="post" action="<?= e($selfUrl) ?>" enctype="multipart/form-data" class="grid grid-cols-1 gap-4 p-4">
                    <?= qHidden('csrf', $csrf) ?><?= qHidden('action', 'save_section') ?>
                    <?= qHidden('quotation_id', $qid) ?><?= qHidden('section_id', $sid) ?>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Title</label>
                        <input name="title" required maxlength="150" value="<?= e($sec['title']) ?>" class="<?= $inputCls ?>">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Replace image (JPG, PNG, WebP, max 5 MB)</label>
                        <input name="image" type="file" accept="image/jpeg,image/png,image/webp" class="<?= $inputCls ?>">
                    </div>
                    <div class="flex items-center gap-3">
                        <button class="<?= $btnCls ?>">Save section</button>
                        <button type="button" data-close class="text-sm text-slate-600 underline">Cancel</button>
                    </div>
                </form>
            </dialog>
        </section>
        <?php endforeach; ?>

        <section class="rounded-lg bg-white p-6 shadow-sm ring-1 ring-slate-200">
            <h2 class="mb-4 text-lg font-semibold text-slate-900">Add section</h2>
            <form method="post" action="<?= e($selfUrl) ?>" enctype="multipart/form-data" class="grid grid-cols-1 gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end">
                <?= qHidden('csrf', $csrf) ?><?= qHidden('action', 'save_section') ?>
                <?= qHidden('quotation_id', $qid) ?><?= qHidden('section_id', 0) ?>
                <div>
                    <label for="new_title" class="mb-1 block text-sm font-medium text-slate-700">Title</label>
                    <input id="new_title" name="title" required maxlength="150" class="<?= $inputCls ?>" placeholder="Kitchen Tall Cabinet">
                </div>
                <div>
                    <label for="new_image" class="mb-1 block text-sm font-medium text-slate-700">Image (optional)</label>
                    <input id="new_image" name="image" type="file" accept="image/jpeg,image/png,image/webp" class="<?= $inputCls ?>">
                </div>
                <button class="<?= $btnCls ?>">Add section</button>
            </form>
        </section>

    </main>

    <script>
        /* ───────────── Modals (native <dialog>) ───────────── */
        (function () {
            document.addEventListener('click', e => {
                // buksan
                const opener = e.target.closest('[data-open]');
                if (opener) {
                    const d = document.getElementById(opener.dataset.open);
                    if (d) d.showModal();
                    return;
                }
                // isara (X / Cancel)
                const closer = e.target.closest('[data-close]');
                if (closer) {
                    const d = closer.closest('dialog');
                    if (d) d.close();
                    return;
                }
                // click sa backdrop
                if (e.target.tagName === 'DIALOG') e.target.close();
            });

            // Kapag edit mode at isinara ang modal, tanggalin ang ?edit= sa URL
            document.addEventListener('close', e => {
                const d = e.target;
                if (d.tagName === 'DIALOG' && d.dataset.closeUrl) window.location.href = d.dataset.closeUrl;
            }, true);

            // Kusang bubukas kapag galing sa "Edit" link
            document.querySelectorAll('dialog[data-autoopen]').forEach(d => d.showModal());
        })();
    </script>

    <script>
        /* ───────────── Live preview ng labor / accessory ───────────── */
        (function () {
            const peso = n => '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const val = (f, name) => parseFloat(f.elements[name] && f.elements[name].value) || 0;

            function updateMain(f) {
                const unit = f.elements.unit.value;
                const rate = parseFloat(unit === 'sqm.' ? f.dataset.rateSqm : f.dataset.rateLm) || 0;
                const a = val(f, 'dim_a'), b = val(f, 'dim_b'), ov = val(f, 'measurement'), qty = val(f, 'qty');

                // pc. = flat price per piece (drawer / sink): walang dimensions, labor = labor per piece × qty
                const isPc = unit === 'pc.';
                f.querySelector('[data-dims-wrap]').classList.toggle('hidden', isPc);
                f.querySelector('[data-ov-wrap]').classList.toggle('hidden', isPc);
                f.querySelector('[data-labor-wrap]').classList.toggle('hidden', !isPc);
                f.querySelector('[data-mat-lbl]').textContent = isPc
                    ? 'Price per piece (× qty)'
                    : 'Materials cost per unit (× measurement × qty)';
                f.querySelector('[data-b-wrap]').classList.toggle('hidden', unit !== 'sqm.');
                if (isPc) {
                    const labPc = qty * val(f, 'labor_amount');
                    f.querySelector('[data-preview]').textContent =
                        'Materials ' + peso(qty * val(f, 'material_cost')) + ' (price × qty), labor ' + peso(labPc) + ' (labor × qty)';
                    return;
                }

                let m = ov > 0 ? ov : (unit === 'sqm.' ? a * b / 1e6 : a / 1000);
                m = Math.round(m * 1e4) / 1e4;
                const labor = m * qty * rate;
                const mat = m * qty * val(f, 'material_cost');
                f.querySelector('[data-preview]').textContent =
                    m > 0 ? 'Measurement ' + m + ' ' + unit + ', materials ' + peso(mat) + ', labor ' + peso(labor)
                          : 'Enter the dimensions or a measurement to see the materials and labor.';
            }

            function updateAcc(f) {
                const sel = f.elements.accessory_id;
                const prev = f.querySelector('[data-preview]');
                let uses;
                if (sel) {
                    const o = sel.selectedOptions[0];
                    if (!o || !o.value) {
                        f.querySelector('[data-meas-wrap]').classList.add('hidden');
                        prev.textContent = 'Pick an accessory to see its price and labor.';
                        return;
                    }
                    uses = o.dataset.uses === '1';
                    const price = parseFloat(o.dataset.price) || 0;
                    const qty = val(f, 'qty'), meas = val(f, 'measurement');
                    const mat = (uses ? meas : 1) * qty * price;
                    let labor = 'no labor';
                    if (o.dataset.ltype === 'fixed') labor = 'labor ' + peso((parseFloat(o.dataset.lamt) || 0) * qty);
                    if (o.dataset.ltype === 'group') labor = 'shared labor in group "' + o.dataset.group + '"';
                    prev.textContent = peso(price) + ' per ' + o.dataset.unit + ', materials ' + peso(mat) + ', ' + labor;
                } else {
                    uses = f.dataset.uses === '1';
                    prev.textContent = '';
                }
                f.querySelector('[data-meas-wrap]').classList.toggle('hidden', !uses);
                if (f.elements.measurement) f.elements.measurement.required = uses;
            }

            function update(f) { f.dataset.kind === 'main' ? updateMain(f) : updateAcc(f); }

            ['input', 'change'].forEach(ev => document.addEventListener(ev, e => {
                const f = e.target.closest && e.target.closest('form[data-kind]');
                if (f) update(f);
            }));
            document.querySelectorAll('form[data-kind]').forEach(update);
        })();

        /* ───────────── Price list dropdowns ───────────── */
        (function () {
            const API = '<?= e(rtrim(BASE_URL, '/')) ?>/quotationpricelistajax';
            const MARKUP_DEFAULT = 20;
            const HINT = 'Pick the product, group, item and carcass to get the price.';
            const HINT_FLAT = 'Pick the width / sink to get the price.';
            const fmt = n => '₱' + n.toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            const q = (box, k) => box.querySelector(`[data-pl="${k}"]`);

            // Drawer at Sink = flat price per piece (price × qty lang); may labor per piece din ang drawer
            const FLAT_GROUPS = ['__drawer__', '__sink__'];
            const sizeLabel = s => s.max_width == null ? `${s.min_width}mm+` : `${s.min_width}mm – ${s.max_width}mm`;

            let META = null, SINK_TYPES = {};
            const LISTS = {}, FLAT = {};

            const tubShort = t => (SINK_TYPES[t] || t).replace(/\s*Type.*$/i, '');
            const isFlat = box => FLAT_GROUPS.includes(q(box, 'group').value);

            async function getMeta() {
                if (!META) {
                    const r = await (await fetch(`${API}?action=meta`)).json();
                    META = r.categories;
                    SINK_TYPES = r.sink_types || {};
                }
                return META;
            }
            async function getList(cat) {
                if (!LISTS[cat]) {
                    const r = await (await fetch(`${API}?action=list&category=${encodeURIComponent(cat)}`)).json();
                    LISTS[cat] = r.success ? r.data.filter(x => String(x.is_active) === '1') : [];
                }
                return LISTS[cat];
            }
            // Drawer sizes + prices + labor ng category, at sinks (Kitchen lang)
            async function getFlat(cat) {
                if (!FLAT[cat]) {
                    const meta = await getMeta();
                    const [d, s] = await Promise.all([
                        fetch(`${API}?action=drawer_list&category=${encodeURIComponent(cat)}`).then(r => r.json()),
                        meta[cat].has_sink
                            ? fetch(`${API}?action=sink_list`).then(r => r.json())
                            : Promise.resolve({ success: true, data: [] })
                    ]);
                    FLAT[cat] = {
                        sizes: d.success ? d.sizes : [],
                        prices: d.success ? d.data : {},
                        labors: d.success ? (d.labors || {}) : {},
                        sinks: s.success ? s.data : []
                    };
                }
                return FLAT[cat];
            }

            function fill(sel, items, placeholder) {
                sel.innerHTML = '';
                sel.add(new Option(placeholder, ''));
                items.forEach(i => sel.add(new Option(i.t, i.v)));
            }

            function currentRow(box) {
                return (box._rows || []).find(r => String(r.id) === q(box, 'row').value);
            }

            // Ang napiling drawer size / sink (kung flat ang napili)
            function flatItem(box) {
                const g = q(box, 'group').value, v = q(box, 'row').value;
                if (!v || !box._flat) return null;
                if (g === '__drawer__') {
                    const s = box._flat.sizes.find(x => String(x.id) === v);
                    const p = box._flat.prices[v];
                    return s && p != null
                        ? { price: parseFloat(p), labor: parseFloat(box._flat.labors[v]) || 0, label: 'Drawer', desc: 'Drawer (soft close) - ' + sizeLabel(s) }
                        : null;
                }
                const s = box._flat.sinks.find(x => String(x.id) === v);
                return s
                    ? { price: parseFloat(s.price), labor: 0, label: 'Sink', desc: `Kitchen sink - ${tubShort(s.tub_type)} - ${s.item_size}` }
                    : null;
            }

            function resetBelow(box, from) {
                const order = ['group', 'row', 'carcass'];
                order.slice(order.indexOf(from)).forEach(k => fill(q(box, k), [], 'Select'));
                box.querySelector('[data-pl-size]').classList.add('hidden');
                box.querySelector('[data-pl-carcass-wrap]').classList.remove('hidden');
            }

            async function onCat(box) {
                const cat = q(box, 'cat').value;
                const meta = await getMeta();
                box._rows = cat ? await getList(cat) : [];
                box._flat = cat ? await getFlat(cat) : null;
                resetBelow(box, 'group');
                if (!cat) return;
                box.querySelector('[data-pl-lbl="group"]').textContent = meta[cat].group_label;
                box.querySelector('[data-pl-lbl="row"]').textContent = meta[cat].row_label;

                const sel = q(box, 'group');
                sel.innerHTML = '';
                sel.add(new Option('Select', ''));

                const groups = [...new Set(box._rows.map(r => r.group_name))];
                if (groups.length) {
                    const og = document.createElement('optgroup');
                    og.label = meta[cat].group_label;
                    groups.forEach(g => og.appendChild(new Option(g, g)));
                    sel.appendChild(og);
                }

                // Mga bagong dinagdag: Drawer (lahat), Sink (Kitchen)
                const extras = [];
                if (box._flat.sizes.some(s => box._flat.prices[s.id] != null)) extras.push(['__drawer__', 'Drawer (with soft close)']);
                if (box._flat.sinks.length) extras.push(['__sink__', 'Kitchen sink']);
                if (extras.length) {
                    const og = document.createElement('optgroup');
                    og.label = 'Drawer & others';
                    extras.forEach(([v, t]) => og.appendChild(new Option(t, v)));
                    sel.appendChild(og);
                }
            }

            function onGroup(box) {
                const g = q(box, 'group').value;
                const cfg = META[q(box, 'cat').value];
                resetBelow(box, 'row');

                const flat = FLAT_GROUPS.includes(g);
                box.querySelector('[data-pl-carcass-wrap]').classList.toggle('hidden', flat);
                box.querySelector('[data-pl-lbl="row"]').textContent =
                    g === '__drawer__' ? 'Width' : g === '__sink__' ? 'Tub & size' : cfg.row_label;
                if (!g) return;

                if (g === '__drawer__') {
                    fill(q(box, 'row'),
                        box._flat.sizes
                            .filter(s => box._flat.prices[s.id] != null)
                            .map(s => ({ v: s.id, t: sizeLabel(s) + ' — ' + fmt(parseFloat(box._flat.prices[s.id])) })),
                        'Select width');
                    return;
                }
                if (g === '__sink__') {
                    fill(q(box, 'row'),
                        box._flat.sinks.map(s => ({ v: s.id, t: `${tubShort(s.tub_type)} · ${s.item_size} — ${fmt(parseFloat(s.price))}` })),
                        'Select sink');
                    return;
                }

                const rows = box._rows.filter(r => r.group_name === g).map(r => {
                    const size = (r.max_depth || r.max_height)
                        ? ` (≤${r.max_depth || '–'} × ≤${r.max_height || '–'})` : '';
                    return { v: r.id, t: r.row_name + size };
                });
                fill(q(box, 'row'), rows, 'Select');
            }

            function onRow(box) {
                if (isFlat(box)) return;
                const row = currentRow(box);
                resetBelow(box, 'carcass');
                if (!row) return;
                const cfg = META[q(box, 'cat').value];
                fill(q(box, 'carcass'),
                    Object.entries(cfg.carcass)
                        .filter(([c]) => row.prices[c] != null)
                        .map(([c, l]) => ({ v: c, t: l + ' — ' + fmt(parseFloat(row.prices[c])) })),
                    'Select carcass');
                const hasLimit = !!(row.max_depth || row.max_height);
                box.querySelector('[data-pl-size]').classList.toggle('hidden', !hasLimit);
                q(box, 'depth').placeholder = row.max_depth ? 'standard ≤ ' + row.max_depth : '';
                q(box, 'height').placeholder = row.max_height ? 'standard ≤ ' + row.max_height : '';
            }

            function apply(box) {
                const f = box.closest('form');
                const res = box.querySelector('[data-pl-result]');
                const bLabel = f.querySelector('[data-b-wrap] label');
                const cat = q(box, 'cat').value;
                const row = currentRow(box);
                const code = q(box, 'carcass').value;

                bLabel.textContent = 'Width (mm)';

                // ── Drawer / Sink: flat price + labor per piece, qty ang magmu-multiply ──
                if (isFlat(box)) {
                    const item = flatItem(box);
                    if (!item) { res.textContent = HINT_FLAT; return; }

                    f.elements.unit.value = 'pc.';
                    f.elements.dim_a.value = '';
                    f.elements.dim_b.value = '';
                    f.elements.measurement.value = '';
                    f.elements.material_cost.value = item.price.toFixed(2);
                    f.elements.labor_amount.value = item.labor.toFixed(2);
                    f.dataset.plFlat = '1';

                    const d = f.elements.description;
                    if (!d.value.trim() || d.value === f.dataset.autoDesc) {
                        d.value = item.desc;
                        f.dataset.autoDesc = item.desc;
                    }
                    res.textContent = `${item.label}: ${fmt(item.price)} + labor ${fmt(item.labor)} per piece. Qty lang ang magmu-multiply.`;
                    f.dispatchEvent(new Event('input', { bubbles: true }));
                    return;
                }
                // Galing sa drawer/sink papunta sa regular na item → ibalik ang unit
                if (f.dataset.plFlat) {
                    delete f.dataset.plFlat;
                    f.elements.labor_amount.value = '';
                    if (f.elements.unit.value === 'pc.') f.elements.unit.value = 'sqm.';
                    f.dispatchEvent(new Event('input', { bubbles: true }));
                }

                if (!row || !code || row.prices[code] == null) {
                    res.textContent = HINT;
                    return;
                }

                const price = parseFloat(row.prices[code]);
                const L = parseFloat(f.elements.dim_a.value) || 0;
                const D = parseFloat(q(box, 'depth').value) || 0;
                const H = parseFloat(q(box, 'height').value) || 0;
                const mkRaw = parseFloat(q(box, 'markup').value);
                const mk = isNaN(mkRaw) ? MARKUP_DEFAULT : mkRaw;

                const overD = !!row.max_depth && D > row.max_depth;
                const overH = !!row.max_height && H > row.max_height;
                const standard = !(overD || overH);

                if (!L) {
                    res.textContent = fmt(price) + ' price list. Enter the Length (mm) to compute.';
                    return;
                }

                // Materials cost field = RATE per unit. Ang server ang magmu-multiply sa measurement × qty.
                let unit, dimB, meas, rate, text;
                if (standard) {
                    // STANDARD: rate = price, per lm. (× Length)
                    unit = 'lm.'; dimB = '';
                    meas = L / 1000;
                    rate = price;
                    text = `Standard size: ${fmt(rate)} per lm × ${meas.toFixed(2)} lm = ${fmt(rate * meas)}`;
                } else {
                    // NON-STANDARD: rate = price + markup%, per sqm. (× Length × Height)
                    if (!H) {
                        res.textContent = 'Non-standard depth. Enter the actual height to compute per sqm.';
                        return;
                    }
                    unit = 'sqm.'; dimB = H;
                    meas = L * H / 1e6;
                    rate = price * (1 + mk / 100);
                    bLabel.textContent = 'Height (mm)';
                    const why = [overD && 'depth', overH && 'height'].filter(Boolean).join(' & ');
                    text = `Non-standard (${why} over the limit): ${fmt(price)} + ${mk}% = ${fmt(rate)} per sqm × ${meas.toFixed(2)} sqm = ${fmt(rate * meas)}`;
                }

                f.elements.unit.value = unit;
                f.elements.dim_b.value = dimB;
                f.elements.material_cost.value = rate.toFixed(2);

                const d = f.elements.description;
                if (!d.value.trim() || d.value === f.dataset.autoDesc) {
                    const t = `${row.row_name} - ${row.group_name} - ${META[cat].carcass[code]}`;
                    d.value = t;
                    f.dataset.autoDesc = t;
                }

                res.textContent = text;
                f.dispatchEvent(new Event('input', { bubbles: true }));   // i-refresh ang labor preview
            }

            // I-load lang ang price list kapag unang beses binuksan ang box
            document.addEventListener('toggle', async e => {
                const box = e.target;
                if (!box.matches || !box.matches('details[data-plbox]') || !box.open || box._ready) return;
                box._ready = true;
                try {
                    const meta = await getMeta();
                    fill(q(box, 'cat'), Object.entries(meta).map(([k, c]) => ({ v: k, t: c.label })), 'Select product');
                } catch (err) {
                    box._ready = false;
                    box.querySelector('[data-pl-result]').textContent = 'Could not load the price list. Try again.';
                }
            }, true);

            document.addEventListener('change', async e => {
                const t = e.target;
                const box = t.closest && t.closest('[data-plbox]');
                if (!box) return;
                const k = t.dataset.pl;
                if (k === 'cat') await onCat(box);
                else if (k === 'group') onGroup(box);
                else if (k === 'row') onRow(box);
                apply(box);
            });

            document.addEventListener('input', e => {
                const t = e.target;
                const box = t.closest && t.closest('[data-plbox]');
                if (box && ['depth', 'height', 'markup'].includes(t.dataset.pl)) return apply(box);
                if (t.name === 'dim_a') {                       // nagbago ang Length → recompute
                    const f = t.closest('form[data-kind="main"]');
                    const b = f && f.querySelector('[data-plbox]');
                    if (b) apply(b);
                }
            });

            document.addEventListener('click', e => {
                const btn = e.target.closest && e.target.closest('[data-pl-clear]');
                if (!btn) return;
                const box = btn.closest('[data-plbox]');
                const f = box.closest('form');
                q(box, 'cat').value = '';
                box._rows = [];
                box._flat = null;
                resetBelow(box, 'group');
                q(box, 'depth').value = '';
                q(box, 'height').value = '';
                q(box, 'markup').value = MARKUP_DEFAULT;
                f.querySelector('[data-b-wrap] label').textContent = 'Width (mm)';
                if (f.dataset.plFlat) {
                    delete f.dataset.plFlat;
                    f.elements.labor_amount.value = '';
                    if (f.elements.unit.value === 'pc.') f.elements.unit.value = 'sqm.';
                    f.dispatchEvent(new Event('input', { bubbles: true }));
                }
                box.querySelector('[data-pl-result]').textContent = HINT;
            });
        })();
    </script>
</body>

</html>