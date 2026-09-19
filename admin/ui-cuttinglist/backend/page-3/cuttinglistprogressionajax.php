<?php
// cuttinglistprogressionajax.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_CUTTING];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

$currentUserId = intval($_SESSION['account_id'] ?? 0);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

const CUTPROG_ALLOWED_ARCHIVE_EXT = ['zip', 'rar'];
const CUTPROG_ALLOWED_IMAGE_MIME  = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
const CUTPROG_MAX_ARCHIVE_BYTES   = 100 * 1024 * 1024; // 100MB
const CUTPROG_MAX_IMAGE_BYTES     = 15 * 1024 * 1024;  // 15MB per image

function cutProgUploadDir(int $quotationId): string
{
    $dir = ROOT_PATH . '/uploads/cutting_progression/' . $quotationId;
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir;
}

function cutProgRelPath(int $quotationId, string $filename): string
{
    return 'uploads/cutting_progression/' . $quotationId . '/' . $filename;
}

function cutProgSafeExt(string $filename): string
{
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

// Validates + moves the archive upload. Returns [relPath, originalName] or null.
function cutProgHandleArchive(array $file, int $quotationId): ?array
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Archive upload failed (error code ' . $file['error'] . ').');
    }
    if ($file['size'] > CUTPROG_MAX_ARCHIVE_BYTES) {
        throw new RuntimeException('Archive exceeds the 100MB limit.');
    }

    $ext = cutProgSafeExt($file['name']);
    if (!in_array($ext, CUTPROG_ALLOWED_ARCHIVE_EXT, true)) {
        throw new RuntimeException('Only .zip or .rar archives are allowed.');
    }

    $dir = cutProgUploadDir($quotationId);
    $filename = uniqid('cut_', true) . '.' . $ext;
    $destAbs = $dir . '/' . $filename;

    if (!move_uploaded_file($file['tmp_name'], $destAbs)) {
        throw new RuntimeException('Could not save the archive file.');
    }

    return [cutProgRelPath($quotationId, $filename), $file['name']];
}

// Converts one uploaded image to .webp. Returns relative path or null on skip.
function cutProgHandleImageToWebp(array $file, int $quotationId): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Image upload failed (error code ' . $file['error'] . ').');
    }
    if ($file['size'] > CUTPROG_MAX_IMAGE_BYTES) {
        throw new RuntimeException('One of the images exceeds the 15MB limit.');
    }
    if (!function_exists('imagewebp')) {
        throw new RuntimeException('Server is missing GD webp support.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!in_array($mime, CUTPROG_ALLOWED_IMAGE_MIME, true)) {
        throw new RuntimeException('Unsupported image type: ' . $mime);
    }

    $image = match ($mime) {
        'image/jpeg' => imagecreatefromjpeg($file['tmp_name']),
        'image/png'  => imagecreatefrompng($file['tmp_name']),
        'image/gif'  => imagecreatefromgif($file['tmp_name']),
        'image/webp' => imagecreatefromwebp($file['tmp_name']),
    };

    if (!$image) {
        throw new RuntimeException('Could not read one of the uploaded images.');
    }

    // Preserve transparency for png/gif sources.
    imagepalettetotruecolor($image);
    imagealphablending($image, true);
    imagesavealpha($image, true);

    $dir = cutProgUploadDir($quotationId);
    $filename = uniqid('img_', true) . '.webp';
    $destAbs = $dir . '/' . $filename;

    $ok = imagewebp($image, $destAbs, 82);
    imagedestroy($image);

    if (!$ok) {
        throw new RuntimeException('Could not convert one of the images to webp.');
    }

    return cutProgRelPath($quotationId, $filename);
}

function cutProgCheckQuotationUploadable(mysqli $conn, int $quotationId): array
{
    $stmt = $conn->prepare("
        SELECT status, deposit_status, design_2d_verified_by_cutting
        FROM noblecrm_2dquotation
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->bind_param('i', $quotationId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return [false, 'Record not found.'];
    }
    if ($row['status'] !== 'Approved') {
        return [false, 'Not yet fully approved.'];
    }
    if ($row['deposit_status'] !== 'Notice to Proceed') {
        return [false, 'Still on hold pending Notice to Proceed from Accounting.'];
    }
    if (empty($row['design_2d_verified_by_cutting'])) {
        return [false, 'The 2D file must be verified first before uploading cutting progress.'];
    }

    return [true, ''];
}

// A submission is still "pending" for this quotation if it hasn't been
// QR-approved nor QR-rejected yet by the Superadmin (see ewood.php).
function cutProgHasPendingApproval(mysqli $conn, int $quotationId): bool
{
    $stmt = $conn->prepare("
        SELECT id FROM noblecrm_cuttinglistprogression
        WHERE quotation_id = ? AND qr_approved = 0 AND qr_rejected = 0
        LIMIT 1
    ");
    $stmt->bind_param('i', $quotationId);
    $stmt->execute();
    $exists = $stmt->get_result()->fetch_assoc() !== null;
    $stmt->close();
    return $exists;
}

// Notifies every Superadmin that a new submission needs QR approval.
// Wrapped defensively — if control_no/client_name aren't on this table,
// we just fall back to a generic label instead of breaking the upload.
function cutProgNotifySuperadmins(mysqli $conn, int $quotationId, int $progressionId, int $uploaderId): void
{
    $controlNo = null;
    $clientName = null;

    try {
        $infoStmt = $conn->prepare("SELECT control_no, client_name FROM noblecrm_2dquotation WHERE id = ? LIMIT 1");
        if ($infoStmt) {
            $infoStmt->bind_param('i', $quotationId);
            $infoStmt->execute();
            $infoRow = $infoStmt->get_result()->fetch_assoc();
            $infoStmt->close();
            if ($infoRow) {
                $controlNo = $infoRow['control_no'] ?? null;
                $clientName = $infoRow['client_name'] ?? null;
            }
        }
    } catch (\Throwable $e) {
        // Columns not present on this table — ignore, use fallback label below.
    }

    $label = $controlNo
        ? ($clientName ? "{$controlNo} — {$clientName}" : $controlNo)
        : "Quotation #{$quotationId}";

    $roleStmt = $conn->prepare("SELECT id FROM noblerole WHERE role = 'SUPER ADMIN'");
    $roleStmt->execute();
    $roleResult = $roleStmt->get_result();
    $superadminIds = [];
    while ($row = $roleResult->fetch_assoc()) {
        $superadminIds[] = (int) $row['id'];
    }
    $roleStmt->close();

    if (empty($superadminIds)) {
        return;
    }

    $message = "New cutting progress upload for {$label} is pending QR approval.";
    $link = '/crmewoodapproval';

    $notifStmt = $conn->prepare("
        INSERT INTO noblenotification
            (user_id, request_id, control_no, type, message, is_read, created_at, sender_id, link)
        VALUES (?, ?, ?, 'crm', ?, 0, NOW(), ?, ?)
    ");
    foreach ($superadminIds as $superadminId) {
        $notifStmt->bind_param(
            'iissis',
            $superadminId,
            $progressionId,
            $controlNo,
            $message,
            $uploaderId,
            $link
        );
        $notifStmt->execute();
    }
    $notifStmt->close();
}

if ($action === 'upload') {

    $quotationId = intval($_POST['quotation_id'] ?? 0);
    $remarks = trim($_POST['remarks'] ?? '');
    $status = $_POST['status'] ?? 'Pending';

    if (!in_array($status, ['Pending', 'In Progress', 'Completed'], true)) {
        $status = 'Pending';
    }

    if ($quotationId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid quotation.']);
        exit;
    }

    [$uploadable, $reason] = cutProgCheckQuotationUploadable($conn, $quotationId);
    if (!$uploadable) {
        echo json_encode(['success' => false, 'message' => $reason]);
        exit;
    }

    // Block re-submission while a previous upload is still awaiting
    // Superadmin QR approval/rejection.
    if (cutProgHasPendingApproval($conn, $quotationId)) {
        echo json_encode([
            'success' => false,
            'message' => 'You still have a submission awaiting approval. Please wait for it to be approved or rejected before submitting again.'
        ]);
        exit;
    }

    $hasArchive = isset($_FILES['archive']) && $_FILES['archive']['error'] !== UPLOAD_ERR_NO_FILE;
    $imageFiles = $_FILES['images'] ?? null;
    $hasImages = $imageFiles && is_array($imageFiles['name'] ?? null) && count(array_filter($imageFiles['name'])) > 0;

    if (!$hasArchive && !$hasImages && $remarks === '') {
        echo json_encode(['success' => false, 'message' => 'Attach a file or add a remark before submitting.']);
        exit;
    }

    try {
        $archivePath = null;
        $archiveOriginalName = null;
        if ($hasArchive) {
            [$archivePath, $archiveOriginalName] = cutProgHandleArchive($_FILES['archive'], $quotationId);
        }

        $photoPaths = [];
        if ($hasImages) {
            $count = count($imageFiles['name']);
            for ($i = 0; $i < $count; $i++) {
                if ($imageFiles['error'][$i] === UPLOAD_ERR_NO_FILE) continue;
                $single = [
                    'name'     => $imageFiles['name'][$i],
                    'type'     => $imageFiles['type'][$i],
                    'tmp_name' => $imageFiles['tmp_name'][$i],
                    'error'    => $imageFiles['error'][$i],
                    'size'     => $imageFiles['size'][$i],
                ];
                $rel = cutProgHandleImageToWebp($single, $quotationId);
                if ($rel !== null) $photoPaths[] = $rel;
            }
        }
    } catch (RuntimeException $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        exit;
    }

    $photosCsv = implode(',', $photoPaths);

    $stmt = $conn->prepare("
        INSERT INTO noblecrm_cuttinglistprogression
            (quotation_id, archive_path, archive_original_name, photos, remarks, status, uploaded_by, uploaded_role, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'cutting', NOW())
    ");
    $stmt->bind_param(
        'isssssi',
        $quotationId,
        $archivePath,
        $archiveOriginalName,
        $photosCsv,
        $remarks,
        $status,
        $currentUserId
    );
    $stmt->execute();
    $newId = $stmt->insert_id;
    $stmt->close();

    // Let the Superadmin(s) know there's a new QR approval waiting for them.
    cutProgNotifySuperadmins($conn, $quotationId, $newId, $currentUserId);

    echo json_encode([
        'success' => true,
        'message' => 'Progress update saved. It is now pending QR approval from the Superadmin.',
        'id'      => $newId,
    ]);
    exit;
}

if ($action === 'history') {

    $quotationId = intval($_GET['quotation_id'] ?? 0);
    if ($quotationId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid quotation.']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT p.id, p.archive_path, p.archive_original_name, p.photos, p.remarks,
               p.status, p.qr_approved, p.qr_rejected, p.rejection_remarks,
               p.created_at, r.name AS uploaded_by_name
        FROM noblecrm_cuttinglistprogression p
        LEFT JOIN noblerole r ON r.id = p.uploaded_by
        WHERE p.quotation_id = ?
        ORDER BY p.id DESC
    ");
    $stmt->bind_param('i', $quotationId);
    $stmt->execute();
    $result = $stmt->get_result();

    $entries = [];
    while ($row = $result->fetch_assoc()) {
        $photos = array_values(array_filter(explode(',', $row['photos'] ?? '')));

        $approvalStatus = 'Pending';
        if (!empty($row['qr_rejected'])) {
            $approvalStatus = 'Rejected';
        } elseif (!empty($row['qr_approved'])) {
            $approvalStatus = 'Approved';
        }

        $entries[] = [
            'id'                    => (int) $row['id'],
            'archive_url'           => $row['archive_path'] ? BASE_URL . '/' . $row['archive_path'] : null,
            'archive_original_name' => $row['archive_original_name'],
            'photos'                => array_map(fn($p) => BASE_URL . '/' . $p, $photos),
            'remarks'               => $row['remarks'],
            'status'                => $row['status'],
            'approval_status'       => $approvalStatus,
            'rejection_remarks'     => $row['rejection_remarks'],
            'uploaded_by_name'      => $row['uploaded_by_name'] ?? '—',
            'created_at'            => $row['created_at'],
        ];
    }
    $stmt->close();

    echo json_encode(['success' => true, 'entries' => $entries]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);