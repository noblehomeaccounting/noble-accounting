<?php
// crmsitevisit.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_DESIGNER];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';


$svError = '';
$svSuccess = false;
$currentDesignerId = intval($_SESSION['account_id'] ?? 0);
// NOTE: adjust this to whatever session key actually holds the designer's display name.
$currentDesignerName = $_SESSION['account_name'] ?? $_SESSION['fullname'] ?? ('Designer #' . $currentDesignerId);

$inquiryId = intval($_GET['id'] ?? $_POST['inquiry_id'] ?? 0);

if ($inquiryId <= 0) {
    $svError = 'Missing or invalid inquiry reference.';
} else {
    // Fetch the inquiry, making sure it belongs to the logged-in designer
    // ("mode" is included so we can skip this page entirely when the client
    // already provided a 2D — see redirect guard below).
    $stmt = $conn->prepare("
        SELECT id, control_no, client_name, address, contact_number, status, deadline, mode
        FROM noblecrminquiry
        WHERE id = ? AND designer_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $inquiryId, $currentDesignerId);
    $stmt->execute();
    $inquiry = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$inquiry) {
        $svError = 'Inquiry not found, or not assigned to you.';
    }
}

if (empty($svError) && $inquiry && ($inquiry['mode'] ?? 'site_visit') === 'ready_for_quotation') {
    header('Location: ' . BASE_URL . '/crm2dquotation?id=' . $inquiryId);
    exit;
}

// When this form is opened, mark the related notification as read
if (empty($svError) && $inquiryId > 0) {
    $notifStmt = $conn->prepare("
        UPDATE noblenotification SET is_read = 1
        WHERE request_id = ? AND user_id = ?
    ");
    $notifStmt->bind_param("ii", $inquiryId, $currentDesignerId);
    $notifStmt->execute();
    $notifStmt->close();
}

// --- Handle submit ---
if (empty($svError) && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_sitevisit'])) {

    $visitAddress = trim($_POST['visit_address'] ?? '');
    $visitDatetime = trim($_POST['visit_datetime'] ?? '');
    $visited = trim($_POST['visited'] ?? '');
    $measurements = ''; // legacy text column — hindi na ginagamit ng form (PDF upload na)
    $siteConditions = trim($_POST['site_conditions'] ?? '');
    $clientRequirements = trim($_POST['client_requirements'] ?? '');
    $existingStructure = trim($_POST['existing_structure'] ?? '');

    // Deadline for 2D & Quotation, set alongside the site visit record.
    // Stored on the parent inquiry row (not per-visit) since only one
    // deadline should be "active" per inquiry at a time.
    $deadlineRaw = trim($_POST['deadline'] ?? '');
    $deadline = null;
    if ($deadlineRaw !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $deadlineRaw);
        if ($d && $d->format('Y-m-d') === $deadlineRaw) {
            $deadline = $deadlineRaw;
        }
    }

    if (empty($visitAddress) || empty($visitDatetime) || !in_array($visited, ['yes', 'no'], true)) {
        $svError = 'Please fill out all required fields.';
    } else {

        // --- Upload pictures (optional, multiple allowed) ---
        $uploadedPaths = [];
        $uploadDir = ROOT_PATH . '/uploads/crm-sitevisit/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];

        // Convert each uploaded picture to WEBP using GD
        function svConvertToWebp(string $tmpPath, string $ext): ?string
        {
            $ext = strtolower($ext);

            switch ($ext) {
                case 'jpg':
                case 'jpeg':
                    $src = @imagecreatefromjpeg($tmpPath);
                    break;
                case 'png':
                    $src = @imagecreatefrompng($tmpPath);
                    if ($src) {
                        // Preserve transparency for PNG
                        imagepalettetotruecolor($src);
                        imagealphablending($src, true);
                        imagesavealpha($src, true);
                    }
                    break;
                case 'webp':
                    $src = @imagecreatefromwebp($tmpPath);
                    break;
                default:
                    $src = false;
            }

            if (!$src) {
                return null;
            }

            $webpTmp = tempnam(sys_get_temp_dir(), 'svwebp_') . '.webp';
            $ok = imagewebp($src, $webpTmp, 82); // 82 = quality
            imagedestroy($src);

            return $ok ? $webpTmp : null;
        }

        if (!empty($_FILES['photos']) && is_array($_FILES['photos']['name'])) {
            foreach ($_FILES['photos']['name'] as $i => $fileName) {
                if ($_FILES['photos']['error'][$i] !== UPLOAD_ERR_OK || $fileName === '') {
                    continue;
                }
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                if (!in_array($ext, $allowedExt, true)) {
                    continue; // skip non-image files
                }

                $tmpName = $_FILES['photos']['tmp_name'][$i];
                $safeName = 'sv_' . $inquiryId . '_' . time() . '_' . $i . '_' . bin2hex(random_bytes(4)) . '.webp';
                $destPath = $uploadDir . $safeName;

                $webpTmp = svConvertToWebp($tmpName, $ext);

                if ($webpTmp && rename($webpTmp, $destPath)) {
                    $uploadedPaths[] = 'uploads/crm-sitevisit/' . $safeName;
                } elseif ($webpTmp) {
                    @unlink($webpTmp);
                }
                // If conversion fails, just skip that picture
            }
        }

        // --- Upload measurement PDFs (optional, multiple allowed) ---
        $measurementPaths = [];
        $maxPdfBytes = 15 * 1024 * 1024; // 15MB bawat file
        $finfo = finfo_open(FILEINFO_MIME_TYPE);

        if (!empty($_FILES['measurement_pdfs']) && is_array($_FILES['measurement_pdfs']['name'])) {
            foreach ($_FILES['measurement_pdfs']['name'] as $i => $fileName) {
                if ($_FILES['measurement_pdfs']['error'][$i] !== UPLOAD_ERR_OK || $fileName === '') {
                    continue;
                }
                $tmpName = $_FILES['measurement_pdfs']['tmp_name'][$i];
                $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

                if ($ext !== 'pdf' || finfo_file($finfo, $tmpName) !== 'application/pdf') {
                    continue; // skip non-PDF
                }
                if ($_FILES['measurement_pdfs']['size'][$i] > $maxPdfBytes) {
                    continue; // skip lampas sa 15MB
                }

                $safeName = 'svm_' . $inquiryId . '_' . time() . '_' . $i . '_' . bin2hex(random_bytes(4)) . '.pdf';
                if (move_uploaded_file($tmpName, $uploadDir . $safeName)) {
                    $measurementPaths[] = 'uploads/crm-sitevisit/' . $safeName;
                }
            }
        }
        finfo_close($finfo);

        $photosCsv = implode(',', $uploadedPaths);
        $measurementFilesCsv = implode(',', $measurementPaths);

        $stmt = $conn->prepare("
            INSERT INTO noblecrm_sitevisit
                (inquiry_id, designer_id, address, visit_datetime, visited, photos, measurement_files,
                 measurements, site_conditions, client_requirements, existing_structure, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
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

        if ($stmt->execute()) {
            $stmt->close();

            // Mark the original inquiry as In Progress now that it has proceeded,
            // and save the deadline for 2D & Quotation on the inquiry itself.
            $updateStmt = $conn->prepare("
                UPDATE noblecrminquiry SET status = 'In Progress', deadline = ?
                WHERE id = ? AND designer_id = ?
            ");
            $updateStmt->bind_param("sii", $deadline, $inquiryId, $currentDesignerId);
            $updateStmt->execute();
            $updateStmt->close();

            $_SESSION['sv_flash_success'] = 'Site visit details saved.';
            header('Location: ' . $_SERVER['REQUEST_URI']);
            exit;
        } else {
            $svError = 'Something went wrong while saving. Please try again.';
            $stmt->close();
        }
    }
}

if (!empty($_SESSION['sv_flash_success'])) {
    $svSuccess = $_SESSION['sv_flash_success'];
    unset($_SESSION['sv_flash_success']);
}

// Fetch the history of submitted site visits for this inquiry
$svHistory = [];
if (empty($svError) && $inquiryId > 0) {
    $histStmt = $conn->prepare("
        SELECT id, address, visit_datetime, visited, photos, measurement_files,
               measurements, site_conditions, client_requirements, existing_structure, created_at
        FROM noblecrm_sitevisit
        WHERE inquiry_id = ? AND designer_id = ?
        ORDER BY created_at DESC
    ");
    $histStmt->bind_param("ii", $inquiryId, $currentDesignerId);
    $histStmt->execute();
    $svHistory = $histStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $histStmt->close();
}

$crmDesignerListUrl = BASE_URL . '/crmdesigner';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Visit Record</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <style>
        .sv-card { background: #fff; border: 1px solid #E5E7EB; border-radius: 12px; box-shadow: 0 1px 2px rgba(16,24,40,0.04); }
        .sv-field { width: 100%; border: 1px solid #D1D5DB; border-radius: 8px; padding: 0.55rem 0.75rem; font-size: 14px; color: #111827; background: #fff; }
        .sv-field:focus { outline: none; border-color: #2563EB; box-shadow: 0 0 0 3px rgba(37,99,235,0.12); }
        .sv-field::placeholder { color: #9CA3AF; }
        .sv-field-sm { padding: 0.4rem 0.6rem; font-size: 13px; }
    </style>
</head>

<body class="bg-gray-50 font-['Barlow_Condensed']">
    <main class="ml-56 min-h-screen p-7 sv-scope bg-[#F5F6FA]">

        <div class="max-w-6xl mx-auto">

            <!-- Page header -->
            <div class="flex items-start justify-between gap-4 mb-6">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Site Visit Record</h1>
                    <p class="text-sm text-gray-500 mt-1">
                        Track site visit status, notes, and photos for this inquiry.
                        <?php if (!empty($inquiry['control_no'])): ?>
                            &nbsp;·&nbsp;Ref. <?= htmlspecialchars($inquiry['control_no']) ?>
                        <?php endif; ?>
                    </p>
                </div>
                <a href="<?= htmlspecialchars($crmDesignerListUrl) ?>"
                    class="shrink-0 inline-flex items-center gap-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg px-4 py-2 hover:bg-gray-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18" />
                    </svg>
                    Back to assigned list
                </a>
            </div>

            <?php if (!empty($svError)): ?>
                <div class="sv-card px-5 py-4 flex items-start gap-3 border-red-200 bg-red-50">
                    <svg class="w-5 h-5 text-red-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                    <p class="text-sm text-red-800"><?= htmlspecialchars($svError) ?></p>
                </div>
            <?php endif; ?>

            <?php if (empty($svError) && $inquiry): ?>

                <?php $svAlreadyDone = !empty($svHistory); ?>

                <!-- Info cards row -->
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">

                    <div class="sv-card p-4">
                        <div class="w-9 h-9 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center mb-3">
                            <i class="fa-solid fa-user"></i>
                        </div>
                        <p class="text-xs text-gray-500 mb-0.5">Client</p>
                        <p class="text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($inquiry['client_name']) ?></p>
                    </div>

                    <div class="sv-card p-4">
                        <div class="w-9 h-9 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center mb-3">
                           <i class="fa-solid fa-clock"></i>
                        </div>
                        <p class="text-xs text-gray-500 mb-0.5">Status</p>
                        <p class="text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($inquiry['status']) ?></p>
                    </div>

                    <div class="sv-card p-4">
                        <div class="w-9 h-9 rounded-lg bg-emerald-50 text-emerald-600 flex items-center justify-center mb-3">
                            <i class="fa-solid fa-phone"></i>
                        </div>
                        <p class="text-xs text-gray-500 mb-0.5">Contact no.</p>
                        <p class="text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($inquiry['contact_number']) ?></p>
                    </div>

                    <div class="sv-card p-4">
                        <div class="w-9 h-9 rounded-lg bg-purple-50 text-purple-600 flex items-center justify-center mb-3">
                            <i class="fa-solid fa-pen-ruler"></i>
                        </div>
                        <p class="text-xs text-gray-500 mb-0.5">Assigned designer</p>
                        <p class="text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($currentDesignerName) ?></p>
                    </div>

                </div>

                <!-- Registered address strip -->
                <div class="sv-card px-5 py-3.5 mb-6 flex items-center gap-3">
                    <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10.5a3 3 0 11-6 0 3 3 0 016 0z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1115 0z" />
                    </svg>
                    <p class="text-sm text-gray-700"><?= htmlspecialchars($inquiry['address']) ?></p>
                </div>

                <?php if (!empty($inquiry['deadline'])): ?>
                    <?php
                        $deadlineTs = strtotime($inquiry['deadline']);
                        $isOverdue = $deadlineTs < strtotime('today') && $inquiry['status'] !== 'Approved';
                    ?>
                    <div class="sv-card px-5 py-3.5 mb-6 flex items-center gap-3 <?= $isOverdue ? 'bg-red-50 border-red-100' : '' ?>">
                        <svg class="w-4 h-4 shrink-0 <?= $isOverdue ? 'text-red-500' : 'text-gray-400' ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 012.25-2.25h13.5A2.25 2.25 0 0121 7.5v11.25m-18 0A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75m-18 0v-7.5A2.25 2.25 0 015.25 9h13.5A2.25 2.25 0 0121 11.25v7.5" />
                        </svg>
                        <p class="text-sm <?= $isOverdue ? 'text-red-700 font-medium' : 'text-gray-700' ?>">
                            Deadline for 2D &amp; Quotation:
                            <?= htmlspecialchars(date('F d, Y', $deadlineTs)) ?>
                            <?= $isOverdue ? ' — overdue' : '' ?>
                        </p>
                    </div>
                <?php endif; ?>

                <?php if ($svAlreadyDone): ?>

                    <?php $latestVisit = $svHistory[0]; ?>
                    <?php $latestPhotos = array_filter(explode(',', $latestVisit['photos'] ?? '')); ?>
                    <?php $latestMeasFiles = array_values(array_filter(explode(',', $latestVisit['measurement_files'] ?? ''))); ?>
                    <?php $latestVisited = $latestVisit['visited'] === 'yes'; ?>

                    <div class="grid lg:grid-cols-3 gap-6">

                        <!-- Left column -->
                        <div class="lg:col-span-2 space-y-6">

                            <!-- Visit details -->
                            <div class="sv-card p-5">
                                <h2 class="text-sm font-semibold text-gray-900 mb-4">Visit details</h2>
                                <div class="divide-y divide-gray-100">
                                    <div class="grid grid-cols-[160px_1fr] gap-4 py-2.5 text-sm">
                                        <p class="text-gray-500">Address visited</p>
                                        <p class="text-gray-900"><?= htmlspecialchars($latestVisit['address']) ?></p>
                                    </div>
                                    <div class="grid grid-cols-[160px_1fr] gap-4 py-2.5 text-sm">
                                        <p class="text-gray-500">Date &amp; time</p>
                                        <p class="text-gray-900"><?= htmlspecialchars(date('F d, Y — g:i A', strtotime($latestVisit['visit_datetime']))) ?></p>
                                    </div>
                                    <div class="grid grid-cols-[160px_1fr] gap-4 py-2.5 text-sm items-center">
                                        <p class="text-gray-500">Visited</p>
                                        <span class="inline-flex w-fit items-center text-xs font-medium px-2.5 py-1 rounded-full <?= $latestVisited ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' ?>">
                                            <?= $latestVisited ? 'Yes — proceed to 2D & Quotation' : 'No — not visited' ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($inquiry['deadline'])): ?>
                                        <div class="grid grid-cols-[160px_1fr] gap-4 py-2.5 text-sm">
                                            <p class="text-gray-500">Deadline (2D &amp; Quotation)</p>
                                            <p class="text-gray-900"><?= htmlspecialchars(date('F d, Y', strtotime($inquiry['deadline']))) ?></p>
                                        </div>
                                    <?php endif; ?>
                                    <div class="grid grid-cols-[160px_1fr] gap-4 py-2.5 text-sm">
                                        <p class="text-gray-500">Logged on</p>
                                        <p class="text-gray-500"><?= htmlspecialchars(date('F d, Y / g:i A', strtotime($latestVisit['created_at']))) ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Site notes -->
                            <div class="sv-card p-5">
                                <h2 class="text-sm font-semibold text-gray-900 mb-4">Site notes</h2>
                                <div class="grid sm:grid-cols-2 gap-5">
                                    <div class="sm:col-span-2">
                                        <p class="text-xs font-medium text-gray-500 mb-1">Measurements</p>
                                        <?php if (!empty($latestMeasFiles)): ?>
                                            <ul class="space-y-1">
                                                <?php foreach ($latestMeasFiles as $mi => $mPath): ?>
                                                    <li>
                                                        <a href="<?= htmlspecialchars(BASE_URL . '/' . $mPath) ?>" target="_blank" rel="noopener"
                                                            class="inline-flex items-center gap-1.5 text-sm text-blue-600 hover:underline">
                                                            <i class="fa-solid fa-file-pdf"></i> Measurement PDF <?= $mi + 1 ?>
                                                        </a>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php elseif (!empty($latestVisit['measurements'])): ?>
                                            <p class="text-sm text-gray-800"><?= nl2br(htmlspecialchars($latestVisit['measurements'])) ?></p>
                                        <?php else: ?>
                                            <span class="text-sm text-gray-400 italic">None provided</span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <p class="text-xs font-medium text-gray-500 mb-1">Site conditions</p>
                                        <p class="text-sm text-gray-800"><?= !empty($latestVisit['site_conditions']) ? nl2br(htmlspecialchars($latestVisit['site_conditions'])) : '<span class="text-gray-400 italic">None provided</span>' ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-medium text-gray-500 mb-1">Client requirements</p>
                                        <p class="text-sm text-gray-800"><?= !empty($latestVisit['client_requirements']) ? nl2br(htmlspecialchars($latestVisit['client_requirements'])) : '<span class="text-gray-400 italic">None provided</span>' ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs font-medium text-gray-500 mb-1">Existing structure</p>
                                        <p class="text-sm text-gray-800"><?= !empty($latestVisit['existing_structure']) ? nl2br(htmlspecialchars($latestVisit['existing_structure'])) : '<span class="text-gray-400 italic">None provided</span>' ?></p>
                                    </div>
                                </div>
                            </div>

                            <!-- Prior entries -->
                            <?php if (count($svHistory) > 1): ?>
                                <div class="sv-card p-5">
                                    <div class="flex items-center justify-between mb-3">
                                        <h2 class="text-sm font-semibold text-gray-900">Prior entries</h2>
                                    </div>
                                    <table class="w-full text-sm">
                                        <thead>
                                            <tr class="text-left text-xs text-gray-500 border-b border-gray-100">
                                                <th class="font-medium pb-2 pr-4">Address</th>
                                                <th class="font-medium pb-2 pr-4">Date</th>
                                                <th class="font-medium pb-2 pr-4">Photos</th>
                                                <th class="font-medium pb-2">Result</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100">
                                            <?php foreach (array_slice($svHistory, 1) as $entry): ?>
                                                <?php
                                                $entryVisited = $entry['visited'] === 'yes';
                                                $entryPhotos = array_filter(explode(',', $entry['photos'] ?? ''));
                                                ?>
                                                <tr>
                                                    <td class="py-2.5 pr-4 text-gray-800"><?= htmlspecialchars($entry['address']) ?></td>
                                                    <td class="py-2.5 pr-4 text-gray-500 whitespace-nowrap"><?= htmlspecialchars(date('M d, Y g:i A', strtotime($entry['visit_datetime']))) ?></td>
                                                    <td class="py-2.5 pr-4 text-gray-500"><?= count($entryPhotos) ?></td>
                                                    <td class="py-2.5">
                                                        <span class="inline-flex items-center text-xs font-medium px-2.5 py-1 rounded-full <?= $entryVisited ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' ?>">
                                                            <?= $entryVisited ? 'Visited' : 'Not visited' ?>
                                                        </span>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>

                        </div>

                        <!-- Right column -->
                        <div class="space-y-6">

                            <!-- Photos -->
                            <div class="sv-card p-5">
                                <h2 class="text-sm font-semibold text-gray-900 mb-4">
                                    Photographs
                                    <?php if (!empty($latestPhotos)): ?>
                                        <span class="text-gray-400 font-normal">(<?= count($latestPhotos) ?>)</span>
                                    <?php endif; ?>
                                </h2>
                                <?php if (empty($latestPhotos)): ?>
                                    <p class="text-sm text-gray-400 italic">No photographs were submitted for this visit.</p>
                                <?php else: ?>
                                    <div class="grid grid-cols-3 gap-2">
                                        <?php foreach ($latestPhotos as $photoPath): ?>
                                            <a href="<?= htmlspecialchars(BASE_URL . '/' . $photoPath) ?>" target="_blank"
                                                class="block aspect-square rounded-lg overflow-hidden border border-gray-200 hover:opacity-90 transition-opacity">
                                                <img src="<?= htmlspecialchars(BASE_URL . '/' . $photoPath) ?>" class="w-full h-full object-cover">
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <!-- Status alert -->
                            <div class="sv-card p-5 <?= $latestVisited ? 'bg-emerald-50 border-emerald-100' : 'bg-amber-50 border-amber-100' ?>">
                                <div class="flex items-start gap-3">
                                    <svg class="w-5 h-5 shrink-0 mt-0.5 <?= $latestVisited ? 'text-emerald-600' : 'text-amber-600' ?>" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <?php if ($latestVisited): ?>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        <?php else: ?>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" />
                                        <?php endif; ?>
                                    </svg>
                                    <div>
                                        <p class="text-sm font-medium text-gray-900">
                                            <?= $latestVisited ? 'Ready to proceed' : 'Site not yet visited' ?>
                                        </p>
                                        <p class="text-sm text-gray-600 mt-0.5">
                                            <?= $latestVisited
                                                ? 'This inquiry can move forward to 2D layout and quotation.'
                                                : 'Schedule a revisit so this inquiry can proceed.' ?>
                                        </p>
                                    </div>
                                </div>
                            </div>

                        </div>

                    </div>

                <?php else: ?>

                    <div class="sv-card p-5 max-w-2xl">
                        <h2 class="text-sm font-semibold text-gray-900 mb-0.5">New record</h2>
                        <p class="text-xs text-gray-400 italic mb-4">No site visits have been recorded yet for this inquiry.</p>

                        <!-- Site Visit Form -->
                        <form method="POST" action="" enctype="multipart/form-data" class="space-y-3.5">

                            <input type="hidden" name="inquiry_id" value="<?= (int) $inquiry['id'] ?>">

                            <div class="grid sm:grid-cols-2 gap-3.5">
                                <!-- Address -->
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">
                                        Address <span class="text-red-500">*</span>
                                    </label>
                                    <textarea name="visit_address" rows="1" required
                                        class="sv-field sv-field-sm"
                                        placeholder="Site visit address"><?= htmlspecialchars($inquiry['address']) ?></textarea>
                                </div>

                                <!-- Date and Time -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">
                                        Date &amp; time of visit <span class="text-red-500">*</span>
                                    </label>
                                    <input type="datetime-local" name="visit_datetime" required class="sv-field sv-field-sm">
                                </div>

                                <!-- Visit Yes / No -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">
                                        Did the site visit happen? <span class="text-red-500">*</span>
                                    </label>
                                    <div class="inline-flex rounded-lg border border-gray-300 overflow-hidden h-[34px]">
                                        <label class="cursor-pointer">
                                            <input type="radio" name="visited" value="yes" class="peer sr-only" required>
                                            <span class="flex items-center h-[32px] px-4 text-xs text-gray-600 peer-checked:bg-blue-600 peer-checked:text-white transition-colors">Yes</span>
                                        </label>
                                        <label class="cursor-pointer border-l border-gray-300">
                                            <input type="radio" name="visited" value="no" class="peer sr-only" required>
                                            <span class="flex items-center h-[32px] px-4 text-xs text-gray-600 peer-checked:bg-blue-600 peer-checked:text-white transition-colors">No</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Deadline for 2D & Quotation -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">
                                        Deadline for 2D &amp; Quotation
                                    </label>
                                    <input type="date" name="deadline" class="sv-field sv-field-sm"
                                        min="<?= htmlspecialchars(date('Y-m-d')) ?>">
                                </div>

                                <!-- Measurements (PDF) -->
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Measurements (PDF)</label>
                                    <label for="sv_meas_pdfs"
                                        class="flex items-center gap-2 border border-dashed border-gray-300 rounded-lg px-3 py-2 cursor-pointer hover:border-blue-400 hover:bg-blue-50/30 transition-colors w-fit">
                                        <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                                d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                        </svg>
                                        <span class="text-xs text-gray-600">Upload PDF(s), max 15MB each</span>
                                    </label>
                                    <input id="sv_meas_pdfs" type="file" name="measurement_pdfs[]" accept="application/pdf" multiple class="hidden">
                                    <ul id="sv_meas_list" class="mt-2 space-y-1"></ul>
                                </div>

                                <!-- Site Conditions / Notes -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Site conditions / notes</label>
                                    <textarea name="site_conditions" rows="2" class="sv-field sv-field-sm"
                                        placeholder="Terrain, access, utilities, hazards..."></textarea>
                                </div>

                                <!-- Client Requirements -->
                                <div>
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Client requirements</label>
                                    <textarea name="client_requirements" rows="2" class="sv-field sv-field-sm"
                                        placeholder="Preferences, budget notes, must-haves..."></textarea>
                                </div>

                                <!-- Existing Structure Information -->
                                <div class="sm:col-span-2">
                                    <label class="block text-xs font-medium text-gray-700 mb-1">Existing structure information</label>
                                    <textarea name="existing_structure" rows="2" class="sv-field sv-field-sm"
                                        placeholder="Age, condition, materials, any structures already on site..."></textarea>
                                </div>
                            </div>

                            <!-- Upload Picture -->
                            <div>
                                <label class="block text-xs font-medium text-gray-700 mb-1">Attach photograph(s)</label>
                                <label for="sv_photos"
                                    class="flex items-center gap-2 border border-dashed border-gray-300 rounded-lg px-3 py-2 cursor-pointer hover:border-blue-400 hover:bg-blue-50/30 transition-colors w-fit">
                                    <svg class="w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                                    </svg>
                                    <span class="text-xs text-gray-600">Upload photographs (JPG, PNG, WEBP)</span>
                                </label>
                                <input id="sv_photos" type="file" name="photos[]" accept="image/jpeg,image/png,image/webp" multiple class="hidden">
                                <div id="sv_photo_preview" class="mt-2 grid grid-cols-6 sm:grid-cols-8 gap-1.5"></div>
                            </div>

                            <div class="pt-1 flex justify-end">
                                <button type="submit" name="submit_sitevisit"
                                    class="px-4 py-2 text-xs font-semibold text-white bg-blue-600 hover:bg-blue-700 rounded-lg transition-colors">
                                    Save record
                                </button>
                            </div>
                        </form>
                    </div>

                <?php endif; ?>

            <?php endif; ?>

        </div>

        <!-- Toast container: bottom-right -->
        <div id="crmToastContainer"
            class="fixed bottom-6 right-6 z-[9999] flex flex-col gap-2.5 pointer-events-none w-full max-w-sm px-4 sm:px-0">
        </div>

        <script>
            function crmShowToast(message, type = 'success', duration = 4000) {
                const container = document.getElementById('crmToastContainer');
                const palette = type === 'success'
                    ? { icon: 'bg-emerald-500', symbol: '✓' }
                    : { icon: 'bg-amber-500', symbol: '!' };

                const toast = document.createElement('div');
                toast.className = `pointer-events-auto flex items-start gap-2.5 bg-white border border-gray-200 rounded-xl shadow-lg px-4 py-3 text-sm text-gray-800
            translate-x-6 opacity-0 scale-95 transition-all duration-300 ease-out`;
                toast.innerHTML = `
            <span class="shrink-0 inline-flex items-center justify-center w-5 h-5 rounded-full text-xs font-bold text-white ${palette.icon}">${palette.symbol}</span>
            <span class="flex-1 leading-relaxed">${message}</span>
            <button type="button" class="shrink-0 text-gray-400 hover:text-gray-600 text-base leading-none" aria-label="Close">&times;</button>
        `;
                container.appendChild(toast);
                requestAnimationFrame(() => toast.classList.remove('translate-x-6', 'opacity-0', 'scale-95'));
                const remove = () => {
                    toast.classList.add('translate-x-6', 'opacity-0', 'scale-95');
                    setTimeout(() => toast.remove(), 300);
                };
                toast.querySelector('button').addEventListener('click', remove);
                if (duration > 0) setTimeout(remove, duration);
            }

            <?php if ($svSuccess): ?>
                crmShowToast(<?= json_encode($svSuccess) ?>, 'success');
            <?php endif; ?>

            // Photo upload — nag-iipon kada pili (hindi napapalitan), may thumbnail + remove button
            const svPhotosInput = document.getElementById('sv_photos');
            if (svPhotosInput) {
                const svPhotoDt = new DataTransfer();
                const svPhotoPreview = document.getElementById('sv_photo_preview');
                const svPhotoUrls = new Map(); // File -> object URL, para ma-revoke pag tinanggal
                const svPhotoTypes = ['image/jpeg', 'image/png', 'image/webp'];

                function svRenderPhotoPreview() {
                    svPhotoPreview.innerHTML = '';
                    Array.from(svPhotoDt.files).forEach((file, idx) => {
                        if (!svPhotoUrls.has(file)) svPhotoUrls.set(file, URL.createObjectURL(file));

                        const wrap = document.createElement('div');
                        wrap.className = 'relative';

                        const img = document.createElement('img');
                        img.src = svPhotoUrls.get(file);
                        img.title = file.name;
                        img.className = 'w-full h-16 object-cover rounded-md border border-gray-200';
                        wrap.appendChild(img);

                        const rm = document.createElement('button');
                        rm.type = 'button';
                        rm.setAttribute('aria-label', 'Remove photo');
                        rm.className = 'absolute -top-1.5 -right-1.5 w-4 h-4 rounded-full bg-gray-700 text-white text-[11px] leading-none flex items-center justify-center hover:bg-red-600';
                        rm.innerHTML = '&times;';
                        rm.addEventListener('click', () => {
                            const removed = svPhotoDt.files[idx];
                            URL.revokeObjectURL(svPhotoUrls.get(removed));
                            svPhotoUrls.delete(removed);
                            svPhotoDt.items.remove(idx);
                            svPhotosInput.files = svPhotoDt.files;
                            svRenderPhotoPreview();
                        });
                        wrap.appendChild(rm);

                        svPhotoPreview.appendChild(wrap);
                    });
                }

                svPhotosInput.addEventListener('change', function () {
                    Array.from(this.files).forEach(file => {
                        const isDup = Array.from(svPhotoDt.files).some(f =>
                            f.name === file.name && f.size === file.size && f.lastModified === file.lastModified);
                        if (svPhotoTypes.includes(file.type) && !isDup) svPhotoDt.items.add(file);
                    });
                    this.files = svPhotoDt.files;
                    svRenderPhotoPreview();
                });
            }

            // Measurement PDFs — nag-iipon ng files kada pili (hindi napapalitan), may remove button
            const svMeasInput = document.getElementById('sv_meas_pdfs');
            if (svMeasInput) {
                const svMeasDt = new DataTransfer();
                const svMeasList = document.getElementById('sv_meas_list');

                function svRenderMeasList() {
                    svMeasList.innerHTML = '';
                    Array.from(svMeasDt.files).forEach((file, idx) => {
                        const li = document.createElement('li');
                        li.className = 'text-xs text-gray-600 flex items-center gap-1.5';
                        li.innerHTML = '<i class="fa-solid fa-file-pdf text-red-500"></i>';

                        const name = document.createElement('span');
                        name.className = 'truncate max-w-xs';
                        name.textContent = file.name;
                        li.appendChild(name);

                        const rm = document.createElement('button');
                        rm.type = 'button';
                        rm.className = 'text-gray-400 hover:text-red-600 text-base leading-none ml-1';
                        rm.setAttribute('aria-label', 'Remove file');
                        rm.innerHTML = '&times;';
                        rm.addEventListener('click', () => {
                            svMeasDt.items.remove(idx);
                            svMeasInput.files = svMeasDt.files;
                            svRenderMeasList();
                        });
                        li.appendChild(rm);

                        svMeasList.appendChild(li);
                    });
                }

                svMeasInput.addEventListener('change', function () {
                    const picked = Array.from(this.files);
                    picked.forEach(file => {
                        const isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
                        const isDup = Array.from(svMeasDt.files).some(f => f.name === file.name && f.size === file.size);
                        if (isPdf && !isDup) svMeasDt.items.add(file);
                    });
                    this.files = svMeasDt.files;
                    svRenderMeasList();
                });
            }
        </script>

    </main>
</body>

</html>