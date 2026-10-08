<?php
// quotation-helpers.php  -  shared by quotation.php (list) and quotationbuilder.php (builder)

/* ------------------------------------------------------------------
   SETUP
   ($conn comes from network/connect.php, session is started in index.php)
------------------------------------------------------------------- */
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (empty($_SESSION['csrf_quote'])) {
    $_SESSION['csrf_quote'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_quote'];

const UPLOAD_SUBDIR = 'uploads/quotations';   // under ROOT_PATH

// Inquiry statuses where the site visit is already done (same rule as crm2dquotationajax.php)
const QUOTE_ALLOWED_STATUSES = "'In Progress','Approved','For Revision'";

class QuoteError extends RuntimeException
{
}

// Public URL of an uploaded image. BASE_URL comes from index.php (localhost vs production).
function qUploadUrl(?string $name): string
{
    return rtrim(BASE_URL, '/') . '/' . UPLOAD_SUBDIR . '/' . rawurlencode(basename((string) $name));
}

if (!function_exists('e')) {
    function e($v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('peso')) {
    function peso($n): string
    {
        return '₱' . number_format((float) $n, 2);
    }
}
function qFmtMeas($n): string
{
    if ($n === null || $n === '')
        return '';
    return rtrim(rtrim(number_format((float) $n, 4, '.', ''), '0'), '.');
}

/* ---- small DB helpers ---- */
function qRun(mysqli $conn, string $sql, string $types = '', array $params = []): mysqli_stmt
{
    $st = $conn->prepare($sql);
    if ($types !== '')
        $st->bind_param($types, ...$params);
    $st->execute();
    return $st;
}
function qRows(mysqli $conn, string $sql, string $types = '', array $params = []): array
{
    $st = qRun($conn, $sql, $types, $params);
    $r = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    $st->close();
    return $r;
}
function qOne(mysqli $conn, string $sql, string $types = '', array $params = []): ?array
{
    return qRows($conn, $sql, $types, $params)[0] ?? null;
}

/* ---- calculations (the PHP result is the final one) ---- */
// Labor rates of a quotation: ['lm.' => rate, 'sqm.' => rate].
// Falls back to the old single labor_rate column for quotations made before the split.
function qRates(array $q): array
{
    $base = (float) ($q['labor_rate'] ?? 0);
    $lm = (float) ($q['labor_rate_lm'] ?? 0);
    $sqm = (float) ($q['labor_rate_sqm'] ?? 0);
    return ['lm.' => $lm > 0 ? $lm : $base, 'sqm.' => $sqm > 0 ? $sqm : $base];
}

function qRecalcSection(mysqli $conn, int $sid, array $rates): void
{
    $items = qRows($conn, 'SELECT * FROM noblecrm_quotation_items WHERE section_id = ? ORDER BY sort_order, id', 'i', [$sid]);

    // One anchor per group: the first item in the group that has a measurement.
    $anchors = [];
    foreach ($items as $it) {
        $g = (string) $it['group_name'];
        if ($it['labor_type'] === 'group' && $g !== '' && $it['uses_measurement'] && !isset($anchors[$g])) {
            $anchors[$g] = (int) $it['id'];
        }
    }

    $upd = $conn->prepare('UPDATE noblecrm_quotation_items SET material_total = ?, labor_total = ? WHERE id = ?');
    foreach ($items as $it) {
        $qty = (float) $it['qty'];
        $meas = (float) $it['measurement'];
        $price = (float) $it['unit_price'];

        if ($it['item_type'] === 'main') {
            // Materials cost is a rate per unit (lm. or sqm.), so it is multiplied by the measurement.
            $mat = $price * $meas * $qty;
            if ($it['unit'] === 'pc.') {
                // flat per piece (drawer): labor = labor_amount x qty
                $lab = $it['labor_type'] === 'fixed' ? (float) $it['labor_amount'] * $qty : 0;
            } else {
                $lab = isset($rates[$it['unit']]) ? $meas * $qty * $rates[$it['unit']] : 0;  // rate follows the item's unit
            }
        } else {
            $base = $it['uses_measurement'] ? $meas : 1;
            $mat = $base * $qty * $price;
            $lab = 0;
            if ($it['labor_type'] === 'fixed') {
                $lab = (float) $it['labor_amount'] * $qty;
            } elseif ($it['labor_type'] === 'group') {
                $g = (string) $it['group_name'];
                if (isset($anchors[$g]) && $anchors[$g] === (int) $it['id']) {
                    $lab = $meas * ($it['unit'] === 'sqm.' ? $rates['sqm.'] : $rates['lm.']);   // one labor charge per group
                }
            }
        }
        $mat = round($mat, 2);
        $lab = round($lab, 2);
        $id = (int) $it['id'];
        $upd->bind_param('ddi', $mat, $lab, $id);
        $upd->execute();
    }
    $upd->close();
}

function qRecalcQuotation(mysqli $conn, int $qid, array $rates): void
{
    foreach (qRows($conn, 'SELECT id FROM noblecrm_quotation_sections WHERE quotation_id = ?', 'i', [$qid]) as $s) {
        qRecalcSection($conn, (int) $s['id'], $rates);
    }
}

function qOwnsSection(mysqli $conn, int $sid, int $qid): bool
{
    return (bool) qOne($conn, 'SELECT id FROM noblecrm_quotation_sections WHERE id = ? AND quotation_id = ?', 'ii', [$sid, $qid]);
}

/* ---- images ---- */
function qSaveImage(array $f): ?string
{
    $err = $f['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_NO_FILE)
        return null;
    if ($err !== UPLOAD_ERR_OK)
        throw new QuoteError('Image upload failed. Try again.');
    if ($f['size'] > 5 * 1024 * 1024)
        throw new QuoteError('Image must be 5 MB or smaller.');

    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'][$mime] ?? null;
    if (!$ext)
        throw new QuoteError('Image must be a JPG, PNG, or WebP file.');

    $dir = ROOT_PATH . '/' . UPLOAD_SUBDIR;
    if (!is_dir($dir) && !mkdir($dir, 0755, true))
        throw new QuoteError('Could not create the upload folder.');

    $name = bin2hex(random_bytes(12)) . '.' . $ext;
    if (!move_uploaded_file($f['tmp_name'], "$dir/$name"))
        throw new QuoteError('Could not save the image.');
    return $name;
}
function qDeleteImage(?string $name): void
{
    if (!$name)
        return;
    $path = ROOT_PATH . '/' . UPLOAD_SUBDIR . '/' . basename($name);
    if (is_file($path))
        @unlink($path);
}

function qFlash(string $type, string $msg): void
{
    $_SESSION['flash'] = [$type, $msg];
}
function qGo(string $url): void
{
    header('Location: ' . $url);
    exit;
}

$quoteListUrl = rtrim(BASE_URL, '/') . '/quotation';
$quoteBuilderUrl = rtrim(BASE_URL, '/') . '/quotationbuilder';

$inputCls = 'w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 '
    . 'focus:border-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-300';
$btnCls = 'rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700 '
    . 'focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2';

function qPostBtn(
    string $action,
    array $fields,
    string $label,
    string $confirm = '',
    string $cls = 'text-red-700 underline hover:text-red-900'
): void {
    global $csrf, $selfUrl;
    echo '<form method="post" action="' . e($selfUrl) . '" class="inline"'
        . ($confirm !== '' ? ' onsubmit="return confirm(\'' . e(addslashes($confirm)) . '\');"' : '') . '>';
    echo '<input type="hidden" name="csrf" value="' . e($csrf) . '">';
    echo '<input type="hidden" name="action" value="' . e($action) . '">';
    foreach ($fields as $k => $v) {
        echo '<input type="hidden" name="' . e($k) . '" value="' . e($v) . '">';
    }
    echo '<button type="submit" class="' . e($cls) . '">' . e($label) . '</button></form>';
}
function qHidden(string $name, $value): string
{
    return '<input type="hidden" name="' . e($name) . '" value="' . e($value) . '">';
}