<?php
// sitevisit/sv-helpers.php — mga function: WEBP convert, file upload, view helpers
// Kasama ng sitevisitform.php (include). Huwag buksan nang direkta.

const SV_IMG_MAX_SIDE = 2560;   // liliitan ang litrato hanggang 2560px (mahabang gilid) — malinaw pa rin, mas magaan
const SV_IMG_QUALITY = 82;

// Basahin ang litrato batay sa LAMAN (hindi sa pangalan ng file) → GD image o null.
// Kaya: .jpg na HEIC pala, o sirang file → hindi na mase-save nang sira.
function svLoadImage(string $tmpPath)
{
    $info = @getimagesize($tmpPath);
    if ($info && ($info[0] * $info[1]) > 60000000) return null;   // sobrang laki (> ~60MP)
    @ini_set('memory_limit', '512M');

    $raw = @file_get_contents($tmpPath);
    $src = $raw !== false ? @imagecreatefromstring($raw) : false;

    // HEIC/HEIF (iPhone): GD hindi kaya → Imagick kung naka-install
    if (!$src && class_exists('Imagick')) {
        try {
            $im = new Imagick($tmpPath);
            $im->setImageFormat('jpeg');
            $src = @imagecreatefromstring($im->getImageBlob());
            $im->clear();
        } catch (Throwable $e) {
            $src = false;
        }
    }
    if (!$src) return null;

    // Ayusin ang ikot ng litrato galing phone (EXIF)
    if (($info[2] ?? 0) === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $o = (int) ((@exif_read_data($tmpPath)['Orientation']) ?? 1);
        $deg = [3 => 180, 6 => -90, 8 => 90][$o] ?? 0;
        if ($deg) { $r = imagerotate($src, $deg, 0); if ($r) { imagedestroy($src); $src = $r; } }
    }

    if (!imageistruecolor($src)) imagepalettetotruecolor($src);

    // Liitan
    $w = imagesx($src); $h = imagesy($src);
    $scale = min(1, SV_IMG_MAX_SIDE / max($w, $h));
    if ($scale < 1) {
        $nw = max(1, (int) round($w * $scale)); $nh = max(1, (int) round($h * $scale));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($src);
        $src = $dst;
    }
    imagealphablending($src, true);
    imagesavealpha($src, true);
    return $src;
}

// Tama ba ang na-save na larawan? (may laman at nababasa)
function svIsValidImage(string $path): bool
{
    clearstatcache(true, $path);
    return is_file($path) && filesize($path) > 100 && @getimagesize($path) !== false;
}

// I-convert ang litrato → WEBP; kapag sira ang WEBP output, JPG ang gagamitin.
// Ibinabalik: [tmpPath, ext] o null kapag hindi mabasa ang litrato.
function svConvertImage(string $tmpPath): ?array
{
    $src = svLoadImage($tmpPath);
    if (!$src) return null;

    $base = tempnam(sys_get_temp_dir(), 'svimg_');
    @unlink($base);   // tempnam gumagawa ng file — hindi na kailangan

    if (function_exists('imagewebp')) {
        $webp = $base . '.webp';
        if (@imagewebp($src, $webp, SV_IMG_QUALITY) && svIsValidImage($webp)) {
            imagedestroy($src);
            return [$webp, 'webp'];
        }
        @unlink($webp);
        error_log('sitevisit: WEBP output invalid, falling back to JPG');
    }

    // Fallback: JPG (puting background para sa transparent na PNG)
    $w = imagesx($src); $h = imagesy($src);
    $bg = imagecreatetruecolor($w, $h);
    imagefill($bg, 0, 0, imagecolorallocate($bg, 255, 255, 255));
    imagecopy($bg, $src, 0, 0, 0, 0, $w, $h);
    imagedestroy($src);
    $jpg = $base . '.jpg';
    $ok = @imagejpeg($bg, $jpg, 85);
    imagedestroy($bg);
    if ($ok && svIsValidImage($jpg)) return [$jpg, 'jpg'];
    @unlink($jpg);
    return null;
}

// Lumang pangalan — naiwan para sa ibang file na tumatawag pa nito
function svConvertToWebp(string $tmpPath, string $ext): ?string
{
    $r = svConvertImage($tmpPath);
    return $r ? $r[0] : null;
}

// Ilipat ang na-convert na file sa uploads (copy kapag ibang drive ang temp folder)
function svMoveConverted(string $from, string $to): bool
{
    if (@rename($from, $to) || (@copy($from, $to) && @unlink($from) !== null)) {
        return svIsValidImage($to);
    }
    return false;
}

const SV_PHOTO_EXTS = ['jpg', 'jpeg', 'png', 'webp', 'heic', 'heif'];

const SV_MAX_SPACES = 30;
const SV_MAX_ITEMS = 50;   // item bawat space (hal. Upper cabinet, Lower cabinet)
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
        if ($file['error'] !== UPLOAD_ERR_OK || !in_array($ext, SV_PHOTO_EXTS, true)) { $skipped++; continue; }
        $conv = svConvertImage($file['tmp']);
        if (!$conv) { $skipped++; error_log('sitevisit: cannot read photo "' . $file['name'] . '"'); continue; }
        $safeName = 'sv_' . $inquiryId . '_' . time() . '_' . $i . '_' . bin2hex(random_bytes(4)) . '.' . $conv[1];
        if (svMoveConverted($conv[0], $uploadDir . $safeName)) {
            $paths[] = 'uploads/crm-sitevisit/' . $safeName;
        } else {
            @unlink($conv[0]);
            @unlink($uploadDir . $safeName);
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
        if ($file['error'] === UPLOAD_ERR_OK && in_array($ext, SV_PHOTO_EXTS, true)) {
            $conv = svConvertImage($file['tmp']);
            if (!$conv) { $skipped++; continue; }
            $safeName = 'svmi_' . $inquiryId . '_' . time() . '_' . $i . '_' . bin2hex(random_bytes(4)) . '.' . $conv[1];
            if (svMoveConverted($conv[0], $uploadDir . $safeName)) {
                $paths[] = 'uploads/crm-sitevisit/' . $safeName;
            } else {
                @unlink($conv[0]);
                @unlink($uploadDir . $safeName);
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