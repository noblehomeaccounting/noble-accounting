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
        $spaces[] = ['key' => (string) $key, 'name' => $name, 'requirements' => $requirements, 'notes' => $notes];

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
        'spaces' => array_map(fn($x) => ['name' => mb_substr($x['name'], 0, 100), 'requirements' => $x['requirements'], 'notes' => $x['notes']], array_slice($spaces, 0, SV_MAX_SPACES)),
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
                foreach ($spaces as $order => $sp) {
                    $pdfCsv = implode(',', $sp['pdfs']);
                    $phCsv = implode(',', $sp['photos']);
                    $sortOrder = $order + 1;
                    $spStmt->bind_param("iiisssss", $sitevisitId, $inquiryId, $sortOrder, $sp['name'], $sp['requirements'], $sp['notes'], $pdfCsv, $phCsv);
                    if (!$spStmt->execute()) throw new RuntimeException($spStmt->error);
                }
                $spStmt->close();
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