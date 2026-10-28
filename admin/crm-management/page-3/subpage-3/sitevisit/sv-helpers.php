<?php
// sitevisit/sv-helpers.php — mga function: WEBP convert, file upload, view helpers
// Kasama ng sitevisitform.php (include). Huwag buksan nang direkta.

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

const SV_MAX_SPACES = 30;
const SV_MAX_PDF_BYTES = 15 * 1024 * 1024; // 15MB bawat PDF

// Listahan ng files ng isang input. $key = para sa naka-group (hal. space_pdfs[3][])
function svFileList(string $field, ?string $key = null): array
{
    if (empty($_FILES[$field]['name'])) return [];
    $f = $_FILES[$field];
    $pick = fn($part) => $key === null ? ($f[$part] ?? []) : ($f[$part][$key] ?? []);
    $names = $pick('name');
    if ($names === [] || $names === null) return [];
    if (!is_array($names)) {
        return [['name' => $names, 'tmp' => $pick('tmp_name'), 'error' => $pick('error'), 'size' => $pick('size')]];
    }
    $tmp = $pick('tmp_name'); $err = $pick('error'); $size = $pick('size');
    $out = [];
    foreach ($names as $i => $n) {
        if (is_array($n)) continue;
        $out[] = ['name' => (string) $n, 'tmp' => $tmp[$i] ?? '', 'error' => $err[$i] ?? UPLOAD_ERR_NO_FILE, 'size' => (int) ($size[$i] ?? 0)];
    }
    return $out;
}

// I-save ang mga larawan bilang WEBP. Ibinabalik: [paths, ilang na-skip]
function svSavePhotos(array $files, int $inquiryId, string $uploadDir): array
{
    $paths = []; $skipped = 0;
    foreach ($files as $i => $file) {
        if ($file['error'] === UPLOAD_ERR_NO_FILE || $file['name'] === '') continue;
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file['error'] !== UPLOAD_ERR_OK || !in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) { $skipped++; continue; }
        $safeName = 'sv_' . $inquiryId . '_' . time() . '_' . $i . '_' . bin2hex(random_bytes(4)) . '.webp';
        $webpTmp = svConvertToWebp($file['tmp'], $ext);
        if ($webpTmp && rename($webpTmp, $uploadDir . $safeName)) {
            $paths[] = 'uploads/crm-sitevisit/' . $safeName;
        } else {
            if ($webpTmp) @unlink($webpTmp);
            $skipped++;
        }
    }
    return [$paths, $skipped];
}

// I-save ang measurement files: PDF, o photo (JPG/PNG/WEBP → WEBP). Ibinabalik: [paths, ilang na-skip]
function svSavePdfs(array $files, int $inquiryId, string $uploadDir): array
{
    $paths = []; $skipped = 0;
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    foreach ($files as $i => $file) {
        if ($file['error'] === UPLOAD_ERR_NO_FILE || $file['name'] === '') continue;
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        // Photo ng sukat (hal. kuha ng sketch o tape measure)
        if ($file['error'] === UPLOAD_ERR_OK && in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            $safeName = 'svmi_' . $inquiryId . '_' . time() . '_' . $i . '_' . bin2hex(random_bytes(4)) . '.webp';
            $webpTmp = svConvertToWebp($file['tmp'], $ext);
            if ($webpTmp && rename($webpTmp, $uploadDir . $safeName)) {
                $paths[] = 'uploads/crm-sitevisit/' . $safeName;
            } else {
                if ($webpTmp) @unlink($webpTmp);
                $skipped++;
            }
            continue;
        }

        if (
            $file['error'] !== UPLOAD_ERR_OK || $ext !== 'pdf'
            || finfo_file($finfo, $file['tmp']) !== 'application/pdf'
            || $file['size'] > SV_MAX_PDF_BYTES
        ) { $skipped++; continue; }
        $safeName = 'svm_' . $inquiryId . '_' . time() . '_' . $i . '_' . bin2hex(random_bytes(4)) . '.pdf';
        if (move_uploaded_file($file['tmp'], $uploadDir . $safeName)) {
            $paths[] = 'uploads/crm-sitevisit/' . $safeName;
        } else {
            $skipped++;
        }
    }
    finfo_close($finfo);
    return [$paths, $skipped];
}


// Mga helper para sa view
function svDaysLabel(?string $date): ?array
{
    if (empty($date)) return null;
    $ts = strtotime($date);
    if (!$ts) return null;
    $days = (int) round((strtotime(date('Y-m-d', $ts)) - strtotime('today')) / 86400);
    if ($days === 0) return ['Due today', 'amber'];
    if ($days === 1) return ['Due tomorrow', 'amber'];
    if ($days > 1) return [$days . ' days left', $days <= 3 ? 'amber' : 'gray'];
    return [abs($days) . ' day' . (abs($days) === 1 ? '' : 's') . ' overdue', 'red'];
}
function svNotes(?string $txt): string
{
    return !empty($txt)
        ? '<p class="text-[13px] text-gray-800 leading-relaxed">' . nl2br(htmlspecialchars($txt)) . '</p>'
        : '<p class="text-[13px] text-gray-400 italic">None provided</p>';
}
