<?php
// systemupdates.php
// All roles: view the latest system updates / announcements.
// IT: can post, edit, pin and delete.

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = array_values(array_filter(array_map(
    fn($c) => defined($c) ? constant($c) : null,
    ['ROLE_IT', 'ROLE_DESIGNER', 'ROLE_ACCOUNTING', 'ROLE_CUTTING', 'ROLE_OPERATIONS', 'ROLE_SALES',
     'ROLE_GRAPHIC', 'ROLE_HR', 'ROLE_SUPERADMIN', 'ROLE_PO', 'ROLE_PC']
)));
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$uid   = intval($_SESSION['account_id'] ?? 0);
$uname = (string) ($_SESSION['username'] ?? '');
$isIT  = ($_SESSION['role'] ?? '') === ROLE_IT;

$CATS = [
    'new'         => ['label' => 'New Feature', 'icon' => 'fa-wand-magic-sparkles', 'cls' => 'bg-emerald-50 text-emerald-700 border-emerald-200', 'dot' => 'bg-emerald-500'],
    'improvement' => ['label' => 'Improvement', 'icon' => 'fa-arrow-trend-up',      'cls' => 'bg-blue-50 text-blue-700 border-blue-200',          'dot' => 'bg-blue-500'],
    'fix'         => ['label' => 'Bug Fix',     'icon' => 'fa-screwdriver-wrench',  'cls' => 'bg-amber-50 text-amber-800 border-amber-200',       'dot' => 'bg-amber-500'],
    'maintenance' => ['label' => 'Maintenance', 'icon' => 'fa-server',              'cls' => 'bg-purple-50 text-purple-700 border-purple-200',    'dot' => 'bg-purple-500'],
    'notice'      => ['label' => 'Notice',      'icon' => 'fa-circle-info',         'cls' => 'bg-gray-100 text-gray-700 border-gray-200',         'dot' => 'bg-gray-500'],
];

if (empty($_SESSION['nh_csrf'])) {
    $_SESSION['nh_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['nh_csrf'];

function suFlash(string $type, string $msg): void
{
    $_SESSION['su_flash'] = ['type' => $type, 'msg' => $msg];
}
function suBack(string $to = ''): void
{
    header('Location: ' . ($to !== '' ? $to : strtok($_SERVER['REQUEST_URI'], '?')));
    exit;
}

// ═══════════════════════════════════════════════════════════════
//  POST ACTIONS (IT only)
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$isIT || !hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        suFlash('error', 'Request not allowed. Please refresh the page.');
        suBack();
    }
    $action = $_POST['action'] ?? '';
    $id     = intval($_POST['id'] ?? 0);

    if ($action === 'save') {
        $title   = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 150);
        $body    = trim((string) ($_POST['body'] ?? ''));
        $cat     = array_key_exists($_POST['category'] ?? '', $CATS) ? $_POST['category'] : 'new';
        $version = mb_substr(trim((string) ($_POST['version'] ?? '')), 0, 30);
        $pinned  = !empty($_POST['is_pinned']) ? 1 : 0;

        if ($title === '' || $body === '') {
            suFlash('error', 'Title and Details are required.');
            suBack($id ? '?edit=' . $id : '');
        }

        if ($id > 0) {
            $st = $conn->prepare("UPDATE noble_system_updates SET title=?, body=?, category=?, version=?, is_pinned=? WHERE id=?");
            $st->bind_param('ssssii', $title, $body, $cat, $version, $pinned, $id);
            $msg = 'Announcement updated.';
        } else {
            $st = $conn->prepare("INSERT INTO noble_system_updates (title, body, category, version, is_pinned, posted_by, posted_name) VALUES (?,?,?,?,?,?,?)");
            $st->bind_param('ssssiis', $title, $body, $cat, $version, $pinned, $uid, $uname);
            $msg = 'Update posted. Everyone can now see it.';
        }
        $st->execute();
        $st->close();
        suFlash('success', $msg);
        suBack();
    }

    if ($action === 'pin' && $id > 0) {
        $st = $conn->prepare("UPDATE noble_system_updates SET is_pinned = 1 - is_pinned WHERE id = ?");
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();
        suBack();
    }

    if ($action === 'delete' && $id > 0) {
        $st = $conn->prepare("DELETE FROM noble_system_updates WHERE id = ?");
        $st->bind_param('i', $id);
        $st->execute();
        $st->close();
        suFlash('success', 'Announcement deleted.');
        suBack();
    }

    suBack();
}

// ═══════════════════════════════════════════════════════════════
//  FETCH
// ═══════════════════════════════════════════════════════════════
$flash = $_SESSION['su_flash'] ?? null;
unset($_SESSION['su_flash']);

$fCat = array_key_exists($_GET['cat'] ?? '', $CATS) ? $_GET['cat'] : '';

// Being edited (IT)
$edit = null;
if ($isIT && !empty($_GET['edit'])) {
    $eid = intval($_GET['edit']);
    $st = $conn->prepare("SELECT * FROM noble_system_updates WHERE id = ?");
    $st->bind_param('i', $eid);
    $st->execute();
    $edit = $st->get_result()->fetch_assoc() ?: null;
    $st->close();
}

if ($fCat !== '') {
    $st = $conn->prepare("SELECT * FROM noble_system_updates WHERE category = ? ORDER BY is_pinned DESC, created_at DESC LIMIT 200");
    $st->bind_param('s', $fCat);
} else {
    $st = $conn->prepare("SELECT * FROM noble_system_updates ORDER BY is_pinned DESC, created_at DESC LIMIT 200");
}
$st->execute();
$updates = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$isNew = fn($d) => (time() - strtotime($d)) < 7 * 86400;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Updates</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
</head>

<body class="bg-slate-100">

    <main class="ml-56 min-h-screen p-8">

        <!-- Header -->
        <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-amber-700">What's New</p>
                <h1 class="text-2xl font-extrabold tracking-tight text-gray-900">System Updates</h1>
                <p class="text-sm text-gray-500 mt-1">New features, fixes, and notices about the NobleHome system.</p>
            </div>
            <a href="<?= BASE_URL ?>/reportbugandimprovement"
                class="inline-flex items-center gap-2 h-9 px-3.5 rounded-lg bg-white border border-gray-200 text-xs font-semibold text-gray-700 hover:border-red-200 hover:text-red-600">
                <i class="fa-solid fa-bug text-red-500"></i> Found a bug? Report it
            </a>
        </div>

        <?php if ($flash): ?>
            <div class="mb-5 flex items-center gap-2 rounded-xl border px-4 py-3 text-sm font-medium
                <?= $flash['type'] === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-700' ?>">
                <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
                <?= $h($flash['msg']) ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 <?= $isIT ? 'xl:grid-cols-[380px_1fr]' : '' ?> gap-6 items-start">

            <?php if ($isIT): ?>
                <!-- ═══ POST FORM (IT) ═══ -->
                <form method="post" class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden xl:sticky xl:top-8">
                    <div class="h-1 bg-[repeating-linear-gradient(-45deg,#f59e0b_0_8px,#1f2937_8px_16px)]"></div>
                    <div class="p-5 space-y-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <h2 class="text-sm font-bold text-gray-900"><?= $edit ? 'Edit Update #' . (int) $edit['id'] : 'Post an Update' ?></h2>
                                <p class="text-[11px] text-gray-500">This will be visible to all departments.</p>
                            </div>
                            <?php if ($edit): ?>
                                <a href="?" class="text-xs font-semibold text-gray-500 hover:text-gray-800">Cancel</a>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="id" value="<?= (int) ($edit['id'] ?? 0) ?>">

                        <div>
                            <label class="block text-[11px] font-bold text-gray-600 mb-1">Title <span class="text-red-500">*</span></label>
                            <input type="text" name="title" maxlength="150" required value="<?= $h($edit['title'] ?? '') ?>"
                                placeholder="e.g. New Sales PO approval flow"
                                class="w-full h-10 rounded-lg border border-gray-200 px-3 text-sm focus:outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100">
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[11px] font-bold text-gray-600 mb-1">Category</label>
                                <select name="category" class="w-full h-10 rounded-lg border border-gray-200 px-2 text-sm bg-white focus:outline-none focus:border-amber-400">
                                    <?php foreach ($CATS as $k => $c): ?>
                                        <option value="<?= $k ?>" <?= ($edit['category'] ?? 'new') === $k ? 'selected' : '' ?>><?= $c['label'] ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[11px] font-bold text-gray-600 mb-1">Version (optional)</label>
                                <input type="text" name="version" maxlength="30" value="<?= $h($edit['version'] ?? '') ?>" placeholder="v2.4"
                                    class="w-full h-10 rounded-lg border border-gray-200 px-3 text-sm focus:outline-none focus:border-amber-400">
                            </div>
                        </div>

                        <div>
                            <label class="block text-[11px] font-bold text-gray-600 mb-1">Details <span class="text-red-500">*</span></label>
                            <textarea name="body" rows="8" required
                                placeholder="- What&#39;s new&#10;- Who is affected&#10;- How to use it"
                                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100"><?= $h($edit['body'] ?? '') ?></textarea>
                            <p class="text-[10px] text-gray-400 mt-1">Tip: start a line with "- " to make it a bullet.</p>
                        </div>

                        <label class="flex items-center gap-2 text-xs font-semibold text-gray-700 cursor-pointer">
                            <input type="checkbox" name="is_pinned" value="1" class="rounded accent-amber-600" <?= !empty($edit['is_pinned']) ? 'checked' : '' ?>>
                            <i class="fa-solid fa-thumbtack text-amber-600"></i> Pin to top
                        </label>

                        <button type="submit"
                            class="w-full inline-flex items-center justify-center gap-2 h-10 rounded-lg bg-gray-900 text-white text-sm font-semibold hover:bg-gray-800">
                            <i class="fa-solid <?= $edit ? 'fa-floppy-disk' : 'fa-bullhorn' ?> text-xs"></i>
                            <?= $edit ? 'Save Changes' : 'Post' ?>
                        </button>
                    </div>
                </form>
            <?php endif; ?>

            <!-- ═══ TIMELINE ═══ -->
            <section class="<?= $isIT ? '' : 'max-w-3xl' ?>">
                <!-- Filter chips -->
                <div class="flex flex-wrap gap-2 mb-4">
                    <a href="?" class="px-3 h-8 inline-flex items-center rounded-full border text-xs font-semibold
                        <?= $fCat === '' ? 'bg-gray-900 border-gray-900 text-white' : 'bg-white border-gray-200 text-gray-600 hover:border-gray-300' ?>">All</a>
                    <?php foreach ($CATS as $k => $c): ?>
                        <a href="?cat=<?= $k ?>" class="px-3 h-8 inline-flex items-center gap-1.5 rounded-full border text-xs font-semibold
                            <?= $fCat === $k ? 'bg-gray-900 border-gray-900 text-white' : 'bg-white border-gray-200 text-gray-600 hover:border-gray-300' ?>">
                            <i class="fa-solid <?= $c['icon'] ?> text-[10px]"></i> <?= $c['label'] ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <?php if (!$updates): ?>
                    <div class="bg-white rounded-2xl border border-gray-200 px-6 py-16 text-center">
                        <div class="w-14 h-14 mx-auto rounded-full bg-gray-100 text-gray-400 flex items-center justify-center mb-3">
                            <i class="fa-solid fa-bullhorn text-xl"></i>
                        </div>
                        <p class="text-sm font-semibold text-gray-600">No updates yet</p>
                        <p class="text-[11px] text-gray-400 mt-1">New announcements from IT will appear here.</p>
                    </div>
                <?php else: ?>
                    <ol class="relative border-l-2 border-gray-200 ml-3 space-y-5">
                        <?php foreach ($updates as $u):
                            $c = $CATS[$u['category']] ?? $CATS['notice']; ?>
                            <li class="relative pl-6">
                                <span class="absolute -left-[9px] top-5 w-4 h-4 rounded-full ring-4 ring-slate-100 <?= $c['dot'] ?>"></span>
                                <article class="bg-white rounded-2xl border <?= $u['is_pinned'] ? 'border-amber-300 shadow-[0_0_0_3px_#fef3c7]' : 'border-gray-200' ?> shadow-sm p-5">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2 mb-1">
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full border text-[10px] font-bold <?= $c['cls'] ?>">
                                                    <i class="fa-solid <?= $c['icon'] ?> text-[9px]"></i> <?= $c['label'] ?>
                                                </span>
                                                <?php if ($u['version'] !== ''): ?>
                                                    <span class="px-2 py-0.5 rounded-full bg-gray-900 text-white text-[10px] font-mono font-bold"><?= $h($u['version']) ?></span>
                                                <?php endif; ?>
                                                <?php if ($u['is_pinned']): ?>
                                                    <span class="text-[10px] font-bold text-amber-700"><i class="fa-solid fa-thumbtack"></i> Pinned</span>
                                                <?php endif; ?>
                                                <?php if ($isNew($u['created_at'])): ?>
                                                    <span class="px-1.5 py-0.5 rounded bg-red-500 text-white text-[9px] font-bold uppercase">New</span>
                                                <?php endif; ?>
                                            </div>
                                            <h3 class="text-base font-bold text-gray-900"><?= $h($u['title']) ?></h3>
                                            <p class="text-[11px] text-gray-500 mt-0.5">
                                                <?= date('F j, Y · g:i A', strtotime($u['created_at'])) ?>
                                                <?php if ($u['posted_name'] !== ''): ?> · by <?= $h($u['posted_name']) ?><?php endif; ?>
                                            </p>
                                        </div>

                                        <?php if ($isIT): ?>
                                            <div class="flex items-center gap-1 shrink-0">
                                                <form method="post">
                                                    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                                                    <input type="hidden" name="action" value="pin">
                                                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                                    <button type="submit" title="<?= $u['is_pinned'] ? 'Unpin' : 'Pin' ?>"
                                                        class="w-8 h-8 rounded-lg hover:bg-amber-50 <?= $u['is_pinned'] ? 'text-amber-600' : 'text-gray-300 hover:text-amber-600' ?>">
                                                        <i class="fa-solid fa-thumbtack text-xs"></i>
                                                    </button>
                                                </form>
                                                <a href="?edit=<?= (int) $u['id'] ?>" title="Edit"
                                                    class="w-8 h-8 inline-flex items-center justify-center rounded-lg text-gray-300 hover:text-blue-600 hover:bg-blue-50">
                                                    <i class="fa-solid fa-pen text-xs"></i>
                                                </a>
                                                <form method="post" onsubmit="return confirm('Delete this update?')">
                                                    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                                    <button type="submit" title="Delete" class="w-8 h-8 rounded-lg text-gray-300 hover:text-red-600 hover:bg-red-50">
                                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="mt-3 text-[13px] leading-relaxed text-gray-700 space-y-1 break-words">
                                        <?php foreach (preg_split('/\R/', $u['body']) as $line):
                                            $t = trim($line);
                                            if ($t === '') { echo '<div class="h-2"></div>'; continue; }
                                            if (preg_match('/^[-*•]\s+(.*)$/u', $t, $m)): ?>
                                                <p class="flex gap-2"><span class="text-amber-600 mt-0.5">•</span><span><?= $h($m[1]) ?></span></p>
                                            <?php else: ?>
                                                <p><?= $h($t) ?></p>
                                            <?php endif;
                                        endforeach; ?>
                                    </div>
                                </article>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </section>
        </div>

    </main>

</body>

</html>
