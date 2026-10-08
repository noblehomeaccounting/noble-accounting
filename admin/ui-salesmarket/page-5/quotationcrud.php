<?php
// quotationcrud.php  -  Accessories catalog for quotations

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SALES];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

/* ------------------------------------------------------------------
   SETUP
   Uses the mysqli $conn from network/connect.php.
------------------------------------------------------------------- */
$db = $conn;
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT); // throw an exception when a query fails

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['csrf_qcrud'])) {
    $_SESSION['csrf_qcrud'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_qcrud'];

$units      = ['pc.', 'pcs.', 'lm.', 'sqm.', 'set'];
$laborTypes = [
    'none'  => 'No labor',
    'fixed' => 'Fixed (own labor per item)',
    'group' => 'Group (one labor for the whole group)',
];

if (!function_exists('e')) {
    function e($v): string
    {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('peso')) {
    function peso($n): string
    {
        return '₱' . number_format((float)$n, 2);
    }
}

$selfUrl = strtok($_SERVER['REQUEST_URI'], '?');

/* ------------------------------------------------------------------
   HANDLE POST (add / update / delete)
------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Invalid request. Refresh the page and try again.');
    }

    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'delete') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $db->prepare('DELETE FROM noblecrm_accessories WHERE id = ?');
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['flash'] = ['ok', 'Accessory deleted.'];
            header('Location: ' . $selfUrl);
            exit;
        }

        if ($action === 'save') {
            $id         = (int)($_POST['id'] ?? 0);
            $name       = trim($_POST['name'] ?? '');
            $unit       = $_POST['unit'] ?? 'pc.';
            $price      = (float)str_replace(',', '', $_POST['unit_price'] ?? '0');
            $usesMeas   = isset($_POST['uses_measurement']) ? 1 : 0;
            $laborType  = $_POST['labor_type'] ?? 'none';
            $laborAmt   = (float)str_replace(',', '', $_POST['labor_amount'] ?? '0');
            $groupName  = trim($_POST['group_name'] ?? '');

            $errors = [];
            if ($name === '')                         $errors[] = 'Accessory name is required.';
            if (!in_array($unit, $units, true))       $errors[] = 'Invalid unit.';
            if ($price < 0)                           $errors[] = 'Price cannot be negative.';
            if (!isset($laborTypes[$laborType]))      $errors[] = 'Invalid labor type.';
            if ($laborType === 'fixed' && $laborAmt < 0) $errors[] = 'Labor cannot be negative.';
            if ($laborType === 'group' && $groupName === '') $errors[] = 'Enter a group name (e.g. Lighting).';

            // Clear fields that don't apply to the chosen labor type
            if ($laborType !== 'fixed') $laborAmt  = 0;
            if ($laborType !== 'group') $groupName = '';

            if ($errors) {
                $_SESSION['flash'] = ['err', implode(' ', $errors)];
                header('Location: ' . $selfUrl . ($id ? '?edit=' . $id : ''));
                exit;
            }

            if ($id > 0) {
                $stmt = $db->prepare(
                    'UPDATE noblecrm_accessories
                        SET name=?, unit=?, unit_price=?, uses_measurement=?,
                            labor_type=?, labor_amount=?, group_name=?
                      WHERE id=?'
                );
                $gn = $groupName !== '' ? $groupName : null;
                $stmt->bind_param('ssdisdsi', $name, $unit, $price, $usesMeas, $laborType, $laborAmt, $gn, $id);
                $stmt->execute();
                $stmt->close();
                $_SESSION['flash'] = ['ok', 'Accessory updated.'];
            } else {
                $stmt = $db->prepare(
                    'INSERT INTO noblecrm_accessories
                        (name, unit, unit_price, uses_measurement, labor_type, labor_amount, group_name)
                     VALUES (?,?,?,?,?,?,?)'
                );
                $gn = $groupName !== '' ? $groupName : null;
                $stmt->bind_param('ssdisds', $name, $unit, $price, $usesMeas, $laborType, $laborAmt, $gn);
                $stmt->execute();
                $stmt->close();
                $_SESSION['flash'] = ['ok', 'Accessory added.'];
            }
            header('Location: ' . $selfUrl);
            exit;
        }
    } catch (mysqli_sql_exception $ex) {
        error_log('quotationcrud: ' . $ex->getMessage());
        $_SESSION['flash'] = ['err', 'Database error. Please try again.'];
        header('Location: ' . $selfUrl);
        exit;
    }
}

/* ------------------------------------------------------------------
   LOAD DATA
------------------------------------------------------------------- */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$search = trim($_GET['q'] ?? '');
if ($search !== '') {
    $stmt = $db->prepare(
        'SELECT * FROM noblecrm_accessories
          WHERE name LIKE ? OR group_name LIKE ?
          ORDER BY group_name IS NULL, group_name, name'
    );
    $like = "%$search%";
    $stmt->bind_param('ss', $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $db->query('SELECT * FROM noblecrm_accessories ORDER BY group_name IS NULL, group_name, name');
}
$rows = $result->fetch_all(MYSQLI_ASSOC);

// For edit mode
$edit = null;
if (isset($_GET['edit'])) {
    $s = $db->prepare('SELECT * FROM noblecrm_accessories WHERE id = ?');
    $editId = (int)$_GET['edit'];
    $s->bind_param('i', $editId);
    $s->execute();
    $edit = $s->get_result()->fetch_assoc() ?: null;
    $s->close();
}

$f = $edit ?: [
    'id' => 0, 'name' => '', 'unit' => 'pc.', 'unit_price' => '',
    'uses_measurement' => 0, 'labor_type' => 'none', 'labor_amount' => '', 'group_name' => '',
];

// Existing groups (for suggestions)
$groups = array_column(
    $db->query("SELECT DISTINCT group_name FROM noblecrm_accessories WHERE group_name IS NOT NULL ORDER BY group_name")
       ->fetch_all(MYSQLI_ASSOC),
    'group_name'
);

$inputCls = 'w-full rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 '
          . 'focus:border-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-300';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation Accessories</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
</head>

<body class="bg-slate-100">
    <main class="ml-56 min-h-screen p-8">

        <div class="mb-6">
            <h1 class="text-2xl font-semibold text-slate-900">Accessories</h1>
            <p class="mt-1 text-sm text-slate-600">
                These are the items you can pick in a quotation. Price and labor fill in automatically when selected.
            </p>
        </div>

        <?php if ($flash): ?>
            <div role="status"
                 class="mb-6 rounded-md border px-4 py-3 text-sm
                        <?= $flash[0] === 'ok'
                            ? 'border-emerald-300 bg-emerald-50 text-emerald-800'
                            : 'border-red-300 bg-red-50 text-red-800' ?>">
                <?= e($flash[1]) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 gap-8 xl:grid-cols-[22rem_1fr]">

            <!-- FORM -->
            <section class="self-start rounded-lg bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h2 class="mb-4 text-lg font-semibold text-slate-900">
                    <?= $edit ? 'Edit accessory' : 'Add accessory' ?>
                </h2>

                <form method="post" action="<?= e($selfUrl) ?>" class="space-y-4" id="accForm">
                    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">

                    <div>
                        <label for="name" class="mb-1 block text-sm font-medium text-slate-700">Name</label>
                        <input id="name" name="name" type="text" required maxlength="150"
                               value="<?= e($f['name']) ?>" class="<?= $inputCls ?>"
                               placeholder="Faucet (Stainless 304)">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="unit" class="mb-1 block text-sm font-medium text-slate-700">Unit</label>
                            <select id="unit" name="unit" class="<?= $inputCls ?>">
                                <?php foreach ($units as $u): ?>
                                    <option value="<?= e($u) ?>" <?= $f['unit'] === $u ? 'selected' : '' ?>><?= e($u) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label for="unit_price" class="mb-1 block text-sm font-medium text-slate-700">Unit price</label>
                            <input id="unit_price" name="unit_price" type="number" step="0.01" min="0" required
                                   value="<?= e($f['unit_price']) ?>" class="<?= $inputCls ?>" placeholder="0.00">
                        </div>
                    </div>

                    <label class="flex items-start gap-2 text-sm text-slate-700">
                        <input type="checkbox" name="uses_measurement" value="1"
                               class="mt-0.5 h-4 w-4 rounded border-slate-300"
                               <?= $f['uses_measurement'] ? 'checked' : '' ?>>
                        <span>
                            Has measurement
                            <span class="block text-xs text-slate-500">
                                Check this if a length or area is entered in the quotation (e.g. strip light, 3.849 lm.).
                            </span>
                        </span>
                    </label>

                    <div>
                        <label for="labor_type" class="mb-1 block text-sm font-medium text-slate-700">Labor</label>
                        <select id="labor_type" name="labor_type" class="<?= $inputCls ?>">
                            <?php foreach ($laborTypes as $key => $label): ?>
                                <option value="<?= e($key) ?>" <?= $f['labor_type'] === $key ? 'selected' : '' ?>><?= e($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div id="fixedWrap" class="hidden">
                        <label for="labor_amount" class="mb-1 block text-sm font-medium text-slate-700">Labor amount</label>
                        <input id="labor_amount" name="labor_amount" type="number" step="0.01" min="0"
                               value="<?= e($f['labor_amount']) ?>" class="<?= $inputCls ?>" placeholder="500.00">
                    </div>

                    <div id="groupWrap" class="hidden">
                        <label for="group_name" class="mb-1 block text-sm font-medium text-slate-700">Group name</label>
                        <input id="group_name" name="group_name" type="text" maxlength="80" list="groupList"
                               value="<?= e($f['group_name']) ?>" class="<?= $inputCls ?>" placeholder="Lighting">
                        <datalist id="groupList">
                            <?php foreach ($groups as $g): ?>
                                <option value="<?= e($g) ?>"></option>
                            <?php endforeach; ?>
                        </datalist>
                        <p class="mt-1 text-xs text-slate-500">
                            Same name = one shared labor charge. Labor is computed from the measurement of the item marked "Has measurement" in the group.
                        </p>
                    </div>

                    <div class="flex items-center gap-3 pt-2">
                        <button type="submit"
                                class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2">
                            <?= $edit ? 'Save changes' : 'Add' ?>
                        </button>
                        <?php if ($edit): ?>
                            <a href="<?= e($selfUrl) ?>" class="text-sm text-slate-600 underline hover:text-slate-900">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>

                <div class="mt-6 border-t border-slate-200 pt-4">
                    <a href="<?= e(rtrim(BASE_URL, '/')) ?>/quotation"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300">
                        Manage Quotations
                    </a>
                </div>
            </section>

            <!-- LISTAHAN -->
            <section class="min-w-0 rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 p-4">
                    <h2 class="text-lg font-semibold text-slate-900">
                        List <span class="text-sm font-normal text-slate-500">(<?= count($rows) ?>)</span>
                    </h2>
                    <form method="get" action="<?= e($selfUrl) ?>" class="flex gap-2">
                        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search name or group"
                               class="w-64 rounded-md border border-slate-300 px-3 py-1.5 text-sm focus:border-slate-700 focus:outline-none focus:ring-2 focus:ring-slate-300">
                        <button class="rounded-md border border-slate-300 px-3 py-1.5 text-sm text-slate-700 hover:bg-slate-50">Search</button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-4 py-3 font-medium">Name</th>
                                <th class="px-4 py-3 font-medium">Unit</th>
                                <th class="px-4 py-3 text-right font-medium">Price</th>
                                <th class="px-4 py-3 font-medium">Labor</th>
                                <th class="px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (!$rows): ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-slate-500">
                                        <?= $search !== ''
                                            ? 'No results for "' . e($search) . '".'
                                            : 'No accessories yet. Add one using the form on the left.' ?>
                                    </td>
                                </tr>
                            <?php endif; ?>

                            <?php foreach ($rows as $r): ?>
                                <tr class="<?= $edit && (int)$edit['id'] === (int)$r['id'] ? 'bg-amber-50' : 'hover:bg-slate-50' ?>">
                                    <td class="px-4 py-3 text-slate-900">
                                        <?= e($r['name']) ?>
                                        <?php if ($r['uses_measurement']): ?>
                                            <span class="ml-1 rounded bg-sky-100 px-1.5 py-0.5 text-xs text-sky-800">has measurement</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-3 text-slate-700"><?= e($r['unit']) ?></td>
                                    <td class="px-4 py-3 text-right tabular-nums text-slate-900"><?= peso($r['unit_price']) ?></td>
                                    <td class="px-4 py-3 text-slate-700">
                                        <?php if ($r['labor_type'] === 'fixed'): ?>
                                            Fixed, <span class="tabular-nums"><?= peso($r['labor_amount']) ?></span>
                                        <?php elseif ($r['labor_type'] === 'group'): ?>
                                            Group: <span class="font-medium"><?= e($r['group_name']) ?></span>
                                        <?php else: ?>
                                            <span class="text-slate-400">None</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">
                                        <a href="<?= e($selfUrl) ?>?edit=<?= (int)$r['id'] ?>"
                                           class="text-slate-700 underline hover:text-slate-900">Edit</a>
                                        <form method="post" action="<?= e($selfUrl) ?>" class="ml-3 inline"
                                              onsubmit="return confirm('Delete &quot;<?= e(addslashes($r['name'])) ?>&quot;? This cannot be undone.');">
                                            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                            <button type="submit" class="text-red-700 underline hover:text-red-900">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>

    <script>
        // Show only the fields that apply to the chosen labor type
        (function () {
            const type = document.getElementById('labor_type');
            const fixedWrap = document.getElementById('fixedWrap');
            const groupWrap = document.getElementById('groupWrap');

            function sync() {
                fixedWrap.classList.toggle('hidden', type.value !== 'fixed');
                groupWrap.classList.toggle('hidden', type.value !== 'group');
            }
            type.addEventListener('change', sync);
            sync();
        })();
    </script>
</body>

</html>