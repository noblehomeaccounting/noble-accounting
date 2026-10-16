<?php
// profileqrregen.php — ONE-TIME: i-regenerate ang lahat ng QR para maging link
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_HR];

include ROOT_PATH . '/admin/authentication/index-roleguard.php';

require_once ROOT_PATH . '/vendor/autoload.php';

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function makeQr(string $text): ?string
{
    $dir = ROOT_PATH . '/uploads/nobleid/';
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return null;
    }
    $usePng = extension_loaded('gd');
    try {
        $options = new QROptions([
            'outputType'   => $usePng ? QRCode::OUTPUT_IMAGE_PNG : QRCode::OUTPUT_MARKUP_SVG,
            'outputBase64' => false,
            'eccLevel'     => QRCode::ECC_M,
            'scale'        => 10,
        ]);
        $out = (new QRCode($options))->render($text);
    } catch (Throwable $e) {
        error_log('QR regen failed: ' . $e->getMessage());
        return null;
    }
    $name = 'qr_' . bin2hex(random_bytes(8)) . ($usePng ? '.png' : '.svg');
    return file_put_contents($dir . $name, $out) === false ? null : 'uploads/nobleid/' . $name;
}

$done = $failed = 0;
$ran  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && !empty($_SESSION['csrf'])
    && hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) {

    $ran = true;
    $rows = $conn->query("SELECT employeeid, qr FROM nobleid")->fetch_all(MYSQLI_ASSOC);

    $upd = $conn->prepare("UPDATE nobleid SET qr = ? WHERE employeeid = ?");
    foreach ($rows as $r) {
        $new = makeQr(BASE_URL . '/verifyid?id=' . rawurlencode($r['employeeid']));
        if (!$new) { $failed++; continue; }

        $upd->bind_param("ss", $new, $r['employeeid']);
        if ($upd->execute()) {
            $done++;
            $old = $r['qr'] ?? '';
            if ($old && strpos($old, 'uploads/nobleid/') === 0 && is_file(ROOT_PATH . '/' . $old)) {
                unlink(ROOT_PATH . '/' . $old);
            }
        } else {
            $failed++;
            if (is_file(ROOT_PATH . '/' . $new)) { unlink(ROOT_PATH . '/' . $new); }
        }
    }
    $upd->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Regenerate QR</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
</head>
<body class="bg-slate-100 p-8">
    <div class="max-w-md mx-auto bg-white rounded-2xl shadow p-6 text-center">
        <h1 class="text-base font-bold text-gray-800 mb-2">Regenerate all QR codes</h1>
        <?php if ($ran): ?>
            <p class="text-sm text-green-700">Done: <?= $done ?> updated<?= $failed ? ", <span class='text-red-600'>$failed failed</span>" : '' ?>.</p>
        <?php else: ?>
            <p class="text-xs text-gray-500 mb-4">Papalitan nito ang QR ng lahat ng employee para link na ang laman.</p>
            <form method="post">
                <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf']) ?>">
                <button class="px-5 py-2 rounded-lg text-xs font-semibold text-white bg-orange-500 hover:bg-orange-600">Run</button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>