<?php
// sitevisit/sv-save.php — POST handler: validation, upload ng files, insert ng record + spaces
// Kasama ng sitevisitform.php (include). Huwag buksan nang direkta.

// Kapag lumampas sa post_max_size, binubura ng PHP ang buong $_POST at $_FILES.
if (
    empty($svError) && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST'
    && empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0
) {
    $svFormError = 'The files are too large for the server to accept. Try fewer or smaller files.';
}

// ═══ Ayusin ang nawawalang file ng naka-save na record (Re-upload / Remove) — JSON ═══
// Galing sa sv-script.php kapag hindi ma-load ang isang photo o PDF (hal. nabura ng deploy).
if (empty($svError) && ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && in_array($_POST['sv_fix'] ?? '', ['replace', 'remove'], true)) {
    header('Content-Type: application/json');
    $svFixOut = function (bool $ok, string $msg, array $extra = []) {
        echo json_encode(['success' => $ok, 'message' => $msg] + $extra);
        exit;
    };

    // Path lang sa loob ng uploads/crm-sitevisit (galing sa src/href ng larawan o link)
    if (!preg_match('#(uploads/crm-sitevisit/[A-Za-z0-9_\-]+\.(webp|jpe?g|png|pdf))(?:[?\#].*)?$#i', (string) ($_POST['path'] ?? ''), $m)) {
        $svFixOut(false, 'Invalid file reference.');
    }
    $rel = $m[1];
    $isPdf = strtolower($m[2]) === 'pdf';

    // Siguraduhing kasama ang file sa record ng inquiry na ito (at sa designer na ito)
    $svFixRows = [];   // [table, id, column, csv]
    $collect = function (string $sql, string $table, array $params, string $types) use ($conn, &$svFixRows) {
        try {
            $st = $conn->prepare($sql);
            if (!$st) return;
            $st->bind_param($types, ...$params);
            $st->execute();
            foreach ($st->get_result()->fetch_all(MYSQLI_ASSOC) as $r) {
                foreach (['photos', 'measurement_files'] as $col) {
                    if (array_key_exists($col, $r)) $svFixRows[] = [$table, (int) $r['id'], $col, (string) $r[$col]];
                }
            }
            $st->close();
        } catch (Throwable $e) {
            // walang table/column → laktawan
        }
    };
    $collect("SELECT id, photos, measurement_files FROM noblecrm_sitevisit WHERE inquiry_id = ? AND designer_id = ?", 'noblecrm_sitevisit', [$inquiryId, $currentDesignerId], 'ii');
    $collect("SELECT id, photos, measurement_files FROM noblecrm_sitevisit_space WHERE inquiry_id = ?", 'noblecrm_sitevisit_space', [$inquiryId], 'i');
    $collect("SELECT id, photos, measurement_files FROM noblecrm_sitevisit_space_item WHERE inquiry_id = ?", 'noblecrm_sitevisit_space_item', [$inquiryId], 'i');

    $inRecord = false;
    foreach ($svFixRows as [, , , $csv]) {
        if (in_array($rel, array_map('trim', explode(',', $csv)), true)) { $inRecord = true; break; }
    }
    if (!$inRecord) $svFixOut(false, 'That file is not part of this site visit.');

    $abs = ROOT_PATH . '/' . $rel;

    if ($_POST['sv_fix'] === 'replace') {
        $f = svFileList('file')[0] ?? null;
        if (!$f || $f['error'] !== UPLOAD_ERR_OK) $svFixOut(false, 'Upload failed. Please try again.');
        if (!is_dir(dirname($abs))) mkdir(dirname($abs), 0755, true);

        if ($isPdf) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($fi, $f['tmp']);
            finfo_close($fi);
            if ($mime !== 'application/pdf') $svFixOut(false, 'Please choose a PDF file.');
            if ($f['size'] > SV_MAX_PDF_BYTES) $svFixOut(false, 'The PDF is too large.');
            if (!move_uploaded_file($f['tmp'], $abs)) $svFixOut(false, 'Could not save the file.');
        } else {
            $conv = svConvertImage($f['tmp']);   // WEBP (o JPG kapag sira ang WEBP) — parehong pangalan pa rin
            if (!$conv) $svFixOut(false, 'Could not read that photo. Use JPG, PNG or WEBP.');
            if (!svMoveConverted($conv[0], $abs)) { @unlink($conv[0]); $svFixOut(false, 'Could not save the photo.'); }
        }
        include_once ROOT_PATH . '/network/realtime-helper.php';
        if (function_exists('nhRtInquiry')) nhRtInquiry($conn, (int) $inquiryId, [], 'crm_sitevisit');
        $svFixOut(true, 'File restored.', ['path' => $rel]);
    }

    // remove: tanggalin sa lahat ng record (at ang file kung meron pa)
    $conn->begin_transaction();
    try {
        foreach ($svFixRows as [$table, $id, $col, $csv]) {
            $list = array_values(array_filter(array_map('trim', explode(',', $csv)), fn($x) => $x !== '' && $x !== $rel));
            $new = implode(',', $list);
            if ($new === $csv) continue;
            $st = $conn->prepare("UPDATE `$table` SET `$col` = ? WHERE id = ?");
            $st->bind_param('si', $new, $id);
            $st->execute();
            $st->close();
        }
        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        error_log('sitevisit remove file: ' . $e->getMessage());
        $svFixOut(false, 'Could not remove the file. Please try again.');
    }
    if (is_file($abs)) @unlink($abs);
    include_once ROOT_PATH . '/network/realtime-helper.php';
    if (function_exists('nhRtInquiry')) nhRtInquiry($conn, (int) $inquiryId, [], 'crm_sitevisit');
    $svFixOut(true, 'File removed from the record.');
}

// --- Handle submit ---
if (empty($svError) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_sitevisit'])) {

    $visitAddress = trim($inquiry['address'] ?? '');
    $visitDatetimeRaw = trim($_POST['visit_datetime'] ?? '');
    $visited = trim($_POST['visited'] ?? '');
    $measurements = ''; // legacy text column — hindi na ginagamit ng form (PDF upload na)
    $siteConditions = trim($_POST['site_conditions'] ?? '');
    $clientRequirements = trim($_POST['client_requirements'] ?? '');
    $existingStructure = trim($_POST['existing_structure'] ?? '');

    // ── Spaces (hal. Kitchen, Master's Bedroom) — bawat isa may sariling PDF, photos at notes ──
    $rawSpaces = is_array($_POST['spaces'] ?? null) ? $_POST['spaces'] : [];
    $spaces = [];
    $spaceErr = '';
    $seenSpace = [];
    foreach ($rawSpaces as $key => $sp) {
        if (!is_array($sp) || !preg_match('/^\d{1,6}$/', (string) $key)) continue;
        $name = trim(preg_replace('/\s+/u', ' ', (string) ($sp['name'] ?? '')));
        $requirements = trim((string) ($sp['requirements'] ?? ''));
        $notes = trim((string) ($sp['notes'] ?? ''));   // = Remark

        // Mga item sa loob ng space (hal. Upper cabinet 240 × 70 × 35 cm) — optional
        $items = [];
        $itemErr = '';
        foreach ((is_array($sp['items'] ?? null) ? $sp['items'] : []) as $ik => $it) {
            if (!is_array($it) || !preg_match('/^\d{1,6}$/', (string) $ik)) continue;
            $iName = trim(preg_replace('/\s+/u', ' ', (string) ($it['name'] ?? '')));
            $iMat = trim((string) ($it['material'] ?? ''));
            $iRem = trim((string) ($it['remark'] ?? ''));
            $dims = [];
            foreach (['w', 'h', 'd'] as $dk) {
                $raw = str_replace(',', '', trim((string) ($it[$dk] ?? '')));
                if ($raw === '') { $dims[$dk] = null; continue; }
                if (!is_numeric($raw) || (float) $raw <= 0 || (float) $raw > 100000) { $itemErr = $itemErr ?: 'Check the size of "' . ($iName ?: 'an item') . '" in "' . $name . '" (numbers in cm only).'; $dims[$dk] = null; continue; }
                $dims[$dk] = round((float) $raw, 2);
            }
            $qtyRaw = trim((string) ($it['qty'] ?? ''));
            $qty = $qtyRaw === '' ? 1 : (int) $qtyRaw;
            $fkey = $key . '_' . $ik;   // susi ng files ng item: item_photos[fkey][], item_files[fkey][]
            $iHasFiles = (bool) array_filter(array_merge(svFileList('item_photos', $fkey), svFileList('item_files', $fkey)), fn($f) => $f['error'] !== UPLOAD_ERR_NO_FILE && $f['name'] !== '');
            if ($iName === '' && $iMat === '' && $iRem === '' && !array_filter($dims) && !$iHasFiles) continue;   // blankong hilera
            if ($iName === '') $itemErr = $itemErr ?: 'Every item in "' . $name . '" needs a name.';
            elseif (mb_strlen($iName) > 100) $itemErr = $itemErr ?: 'An item name in "' . $name . '" is too long (max 100 characters).';
            elseif (mb_strlen($iMat) > 150 || mb_strlen($iRem) > 500) $itemErr = $itemErr ?: 'The material or remark of "' . $iName . '" is too long.';
            elseif ($qty < 1 || $qty > 999 || !preg_match('/^\d*$/', $qtyRaw)) $itemErr = $itemErr ?: 'The quantity of "' . $iName . '" must be 1 to 999.';
            $items[] = ['name' => $iName, 'w' => $dims['w'], 'h' => $dims['h'], 'd' => $dims['d'], 'qty' => max(1, min(999, $qty)), 'material' => $iMat, 'remark' => $iRem, 'fkey' => $fkey, 'has_files' => $iHasFiles];
        }
        if (count($items) > SV_MAX_ITEMS) $itemErr = $itemErr ?: 'You can add up to ' . SV_MAX_ITEMS . ' items per space.';

        $spaces[] = ['key' => (string) $key, 'name' => $name, 'requirements' => $requirements, 'notes' => $notes, 'items' => $items];

        if ($spaceErr !== '') continue;
        if ($name === '') {
            $spaceErr = 'Every space needs a name.';
        } elseif (mb_strlen($name) > 100) {
            $spaceErr = 'A space name is too long (max 100 characters).';
        } elseif (mb_strlen($requirements) > 2000) {
            $spaceErr = 'The requirements for "' . $name . '" are too long (max 2000 characters).';
        } elseif (mb_strlen($notes) > 2000) {
            $spaceErr = 'The remark for "' . $name . '" is too long (max 2000 characters).';
        } elseif (isset($seenSpace[mb_strtolower($name)])) {
            $spaceErr = '"' . $name . '" was added more than once.';
        } elseif ($itemErr !== '') {
            $spaceErr = $itemErr;
        }
        $seenSpace[mb_strtolower($name)] = true;
    }
    if ($spaceErr === '' && count($spaces) > SV_MAX_SPACES) {
        $spaceErr = 'You can add up to ' . SV_MAX_SPACES . ' spaces only.';
    }

    $svOld = [
        'visit_datetime' => $visitDatetimeRaw,
        'visited' => $visited,
        'deadline' => trim($_POST['deadline'] ?? ''),
        'site_conditions' => $siteConditions,
        'client_requirements' => $clientRequirements,
        'existing_structure' => $existingStructure,
        'spaces' => array_map(fn($x) => ['name' => mb_substr($x['name'], 0, 100), 'requirements' => $x['requirements'], 'notes' => $x['notes'], 'items' => array_map(fn($i) => array_diff_key($i, ['fkey' => 1, 'has_files' => 1]), array_slice($x['items'], 0, SV_MAX_ITEMS))], array_slice($spaces, 0, SV_MAX_SPACES)),
    ];

    // datetime-local (Y-m-d\TH:i) → Y-m-d H:i:s para sa MySQL strict mode
    $visitDatetime = '';
    foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s', 'Y-m-d H:i', 'Y-m-d H:i:s'] as $fmt) {
        $dt = DateTime::createFromFormat($fmt, $visitDatetimeRaw);
        if ($dt && $dt->format($fmt) === $visitDatetimeRaw) {
            $visitDatetime = $dt->format('Y-m-d H:i:s');
            break;
        }
    }

    $deadlineRaw = $svOld['deadline'];
    $deadline = null;
    if ($deadlineRaw !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $deadlineRaw);
        if ($d && $d->format('Y-m-d') === $deadlineRaw) {
            $deadline = $deadlineRaw;
        }
    }

    if (empty($visitAddress)) {
        $svFormError = 'This inquiry has no registered address. Ask the sales staff to update it first.';
    } elseif ($visitDatetimeRaw === '' || !in_array($visited, ['yes', 'no'], true)) {
        $svFormError = 'Please fill out all required fields.';
    } elseif ($visitDatetime === '') {
        $svFormError = 'The date & time of visit is not valid.';
    } elseif ($deadlineRaw !== '' && $deadline === null) {
        $svFormError = 'The deadline date is not valid.';
    } elseif ($spaceErr !== '') {
        $svFormError = $spaceErr;
    } elseif ($visited === 'yes' && !$spaces) {
        $svFormError = 'Add at least one space (e.g. Kitchen) with its measurements before saving.';
    } else {

        $uploadDir = ROOT_PATH . '/uploads/crm-sitevisit/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $skippedFiles = 0;

        // Files bawat space
        foreach ($spaces as &$sp) {
            [$sp['pdfs'], $sk1] = svSavePdfs(svFileList('space_pdfs', $sp['key']), $inquiryId, $uploadDir);
            [$sp['photos'], $sk2] = svSavePhotos(svFileList('space_photos', $sp['key']), $inquiryId, $uploadDir);
            $skippedFiles += $sk1 + $sk2;
            // files ng bawat item (photo + PDF/photo ng sukat)
            foreach ($sp['items'] as &$it) {
                [$it['files'], $sk4] = svSavePdfs(svFileList('item_files', $it['fkey']), $inquiryId, $uploadDir);
                [$it['photos'], $sk5] = svSavePhotos(svFileList('item_photos', $it['fkey']), $inquiryId, $uploadDir);
                $skippedFiles += $sk4 + $sk5;
            }
            unset($it);
        }
        unset($sp);

        // Ibang site photos (hindi nakatali sa isang space)
        [$generalPhotos, $sk3] = svSavePhotos(svFileList('photos'), $inquiryId, $uploadDir);
        $skippedFiles += $sk3;

        // Sa lumang columns, lahat ng files (para gumana pa rin ang ibang page na bumabasa nito)
        $allPhotos = array_merge(...array_merge([$generalPhotos], array_column($spaces, 'photos')));
        $allPdfs = $spaces ? array_merge(...array_column($spaces, 'pdfs')) : [];
        $photosCsv = implode(',', $allPhotos);
        $measurementFilesCsv = implode(',', $allPdfs);

        $saved = false;
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("
                INSERT INTO noblecrm_sitevisit
                    (inquiry_id, designer_id, address, visit_datetime, visited, photos, measurement_files,
                     measurements, site_conditions, client_requirements, existing_structure,
                     created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            if (!$stmt) throw new RuntimeException($conn->error);
            $stmt->bind_param(
                "iisssssssss",
                $inquiryId,
                $currentDesignerId,
                $visitAddress,
                $visitDatetime,
                $visited,
                $photosCsv,
                $measurementFilesCsv,
                $measurements,
                $siteConditions,
                $clientRequirements,
                $existingStructure
            );
            if (!$stmt->execute()) throw new RuntimeException($stmt->error);
            $sitevisitId = (int) $conn->insert_id;
            $stmt->close();

            if ($spaces) {
                $spStmt = $conn->prepare("
                    INSERT INTO noblecrm_sitevisit_space
                        (sitevisit_id, inquiry_id, sort_order, space_name, requirements, notes, measurement_files, photos, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                if (!$spStmt) throw new RuntimeException('The spaces table is missing or outdated. Run noblecrm_sitevisit_space.sql (or the add_requirements SQL) first. (' . $conn->error . ')');
                // mga item (kapag may laman lang — kailangan ang noblecrm_sitevisit_space_item.sql)
                $itStmt = null;
                $itFileCols = false;
                if (array_filter(array_column($spaces, 'items'))) {
                    // may photos / measurement_files na column na ba? (noblecrm_sitevisit_space_item_files.sql)
                    try { $c = $conn->query("SHOW COLUMNS FROM noblecrm_sitevisit_space_item LIKE 'measurement_files'"); $itFileCols = $c && $c->num_rows > 0; } catch (Throwable $e) { $itFileCols = false; }
                    $anyItemFiles = false;
                    foreach ($spaces as $s0) foreach ($s0['items'] as $i0) if ($i0['files'] || $i0['photos']) $anyItemFiles = true;
                    if ($anyItemFiles && !$itFileCols) throw new RuntimeException('The spaces table is missing the item photo columns. Run noblecrm_sitevisit_space_item_files.sql first.');
                    $itStmt = $conn->prepare($itFileCols ? "
                        INSERT INTO noblecrm_sitevisit_space_item
                            (space_id, sitevisit_id, inquiry_id, sort_order, item_name, width_cm, height_cm, depth_cm, qty, material, remark, photos, measurement_files, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    " : "
                        INSERT INTO noblecrm_sitevisit_space_item
                            (space_id, sitevisit_id, inquiry_id, sort_order, item_name, width_cm, height_cm, depth_cm, qty, material, remark, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    if (!$itStmt) throw new RuntimeException('The spaces table is missing the items table. Run noblecrm_sitevisit_space_item.sql first. (' . $conn->error . ')');
                }
                foreach ($spaces as $order => $sp) {
                    $pdfCsv = implode(',', $sp['pdfs']);
                    $phCsv = implode(',', $sp['photos']);
                    $sortOrder = $order + 1;
                    $spStmt->bind_param("iiisssss", $sitevisitId, $inquiryId, $sortOrder, $sp['name'], $sp['requirements'], $sp['notes'], $pdfCsv, $phCsv);
                    if (!$spStmt->execute()) throw new RuntimeException($spStmt->error);
                    $spaceId = (int) $conn->insert_id;
                    foreach ($sp['items'] as $io => $it) {
                        $iSort = $io + 1;
                        $iMat = $it['material'] !== '' ? $it['material'] : null;
                        $iRem = $it['remark'] !== '' ? $it['remark'] : null;
                        if ($itFileCols) {
                            $iPh = implode(',', $it['photos']);
                            $iFl = implode(',', $it['files']);
                            $itStmt->bind_param("iiiisdddissss", $spaceId, $sitevisitId, $inquiryId, $iSort, $it['name'], $it['w'], $it['h'], $it['d'], $it['qty'], $iMat, $iRem, $iPh, $iFl);
                        } else {
                            $itStmt->bind_param("iiiisdddiss", $spaceId, $sitevisitId, $inquiryId, $iSort, $it['name'], $it['w'], $it['h'], $it['d'], $it['qty'], $iMat, $iRem);
                        }
                        if (!$itStmt->execute()) throw new RuntimeException($itStmt->error);
                    }
                }
                $spStmt->close();
                if ($itStmt) $itStmt->close();
            }

            // Mark the original inquiry as In Progress now that it has proceeded,
            // and save the deadline for 2D & Quotation on the inquiry itself.
            $updateStmt = $conn->prepare("
                UPDATE noblecrminquiry SET status = 'In Progress', deadline = ?
                WHERE id = ? AND designer_id = ?
            ");
            $updateStmt->bind_param("sii", $deadline, $inquiryId, $currentDesignerId);
            $updateStmt->execute();
            $updateStmt->close();

            $conn->commit();
            $saved = true;
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('crmsitevisit save: ' . $e->getMessage());
            $svFormError = str_contains($e->getMessage(), 'spaces table')
                ? $e->getMessage()
                : 'Something went wrong while saving. Please try again.';
        }

        if ($saved) {
            // Realtime: CRM List ng Sales (umusad sa "Site visit" / "Revisit needed") + ibang tab ng Designer
            include_once ROOT_PATH . '/network/realtime-helper.php';
            nhRtInquiry($conn, (int) $inquiryId, [], 'crm_sitevisit');

            $_SESSION['sv_flash_success'] = 'Site visit details saved.'
                . ($skippedFiles ? ' ' . $skippedFiles . ' file(s) were skipped (wrong type or too large).' : '');
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        }
    }
}