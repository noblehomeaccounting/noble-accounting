<?php
// verify.php — public na page pagkatapos i-scan ang QR ng employee ID
include ROOT_PATH . '/network/connect.php';

function ve($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

$id  = trim($_GET['id'] ?? '');
$emp = null;

if ($id !== '' && strlen($id) <= 50) {
    $stmt = $conn->prepare("SELECT employeeid, fullname, position, photo FROM nobleid WHERE employeeid = ? LIMIT 1");
    $stmt->bind_param("s", $id);
    $stmt->execute();
    $emp = $stmt->get_result()->fetch_assoc() ?: null;
    $stmt->close();
}

$photo = '';
if ($emp && !empty($emp['photo']) && is_file(ROOT_PATH . '/' . ltrim($emp['photo'], '/'))) {
    $photo = BASE_URL . '/' . ltrim($emp['photo'], '/');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Employee Verification — NobleHome</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
</head>

<body class="min-h-screen bg-slate-100 flex items-center justify-center p-4">
    <div class="w-full max-w-sm bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">

        <?php if ($emp): ?>
            <div class="bg-gradient-to-br from-orange-400 to-orange-600 px-6 pt-8 pb-14 text-center text-white">
                <p class="text-[11px] tracking-widest font-semibold opacity-90">NOBLEHOME CONSTRUCTION CORPORATION</p>
            </div>

            <div class="-mt-10 px-6 pb-6 text-center">
                <?php if ($photo): ?>
                    <img src="<?= ve($photo) ?>" alt="" class="w-24 h-24 rounded-full object-cover border-4 border-white shadow mx-auto bg-gray-100">
                <?php else: ?>
                    <div class="w-24 h-24 rounded-full border-4 border-white shadow mx-auto bg-gray-100 text-gray-400 flex items-center justify-center">
                        <i class="fa-solid fa-user text-3xl"></i>
                    </div>
                <?php endif; ?>

                <h1 class="mt-3 text-lg font-bold text-gray-800 uppercase"><?= ve($emp['fullname']) ?></h1>
                <p class="text-sm text-gray-500"><?= ve($emp['position']) ?></p>
                <p class="text-xs text-gray-400 mt-1"><?= ve($emp['employeeid']) ?></p>

                <div class="mt-5 inline-flex items-center gap-2 rounded-full bg-green-50 border border-green-200 px-4 py-1.5 text-xs font-semibold text-green-700">
                    <i class="fa-solid fa-circle-check"></i> Verified employee
                </div>
            </div>
        <?php else: ?>
            <div class="p-8 text-center">
                <i class="fa-solid fa-circle-xmark text-4xl text-red-500 mb-3"></i>
                <h1 class="text-base font-bold text-gray-800">ID not found</h1>
                <p class="text-xs text-gray-500 mt-1">This ID is invalid or no longer active.</p>
            </div>
        <?php endif; ?>

    </div>
</body>

</html>