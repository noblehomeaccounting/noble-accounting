<?php
// reportbugandimprovement.php
// All roles: report a bug or suggest an improvement, and track the status of their own reports.
// IT: sees ALL reports, can change status and reply.

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

// All roles (only defined constants, to avoid errors)
$allowedRoles = array_values(array_filter(array_map(
    fn($c) => defined($c) ? constant($c) : null,
    ['ROLE_IT', 'ROLE_DESIGNER', 'ROLE_ACCOUNTING', 'ROLE_CUTTING', 'ROLE_OPERATIONS', 'ROLE_SALES',
     'ROLE_GRAPHIC', 'ROLE_HR', 'ROLE_SUPERADMIN', 'ROLE_PO', 'ROLE_PC']
)));
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$uid      = intval($_SESSION['account_id'] ?? 0);
$uname    = (string) ($_SESSION['username'] ?? '');
$myRole   = (string) ($_SESSION['role'] ?? '');
$myBranch = (string) ($_SESSION['branch'] ?? '');
$isIT     = $myRole === ROLE_IT;

$TYPES      = ['bug' => 'Bug', 'improvement' => 'Improvement / Suggestion'];
$PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];
$STATUSES   = ['open' => 'Open', 'in_progress' => 'In Progress', 'resolved' => 'Resolved', 'rejected' => 'Rejected'];

if (empty($_SESSION['nh_csrf'])) {
    $_SESSION['nh_csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['nh_csrf'];

function brFlash(string $type, string $msg): void
{
    $_SESSION['br_flash'] = ['type' => $type, 'msg' => $msg];
}
function brBack(): void
{
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}

// ═══════════════════════════════════════════════════════════════
//  POST ACTIONS
// ═══════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        brFlash('error', 'Your session expired. Please refresh the page and try again.');
        brBack();
    }
    $action = $_POST['action'] ?? '';

    // ── New report (all roles) ──
    if ($action === 'submit') {
        $type     = array_key_exists($_POST['type'] ?? '', $TYPES) ? $_POST['type'] : 'bug';
        $priority = array_key_exists($_POST['priority'] ?? '', $PRIORITIES) ? $_POST['priority'] : 'normal';
        $title    = trim((string) ($_POST['title'] ?? ''));
        $desc     = trim((string) ($_POST['description'] ?? ''));
        $pageUrl  = mb_substr(trim((string) ($_POST['page_url'] ?? '')), 0, 255);

        if ($title === '' || $desc === '') {
            brFlash('error', 'Title and Description are required.');
            brBack();
        }
        $title = mb_substr($title, 0, 150);

        // Screenshot (optional) — images only, max 5MB
        $shot = '';
        if (!empty($_FILES['screenshot']) && $_FILES['screenshot']['error'] !== UPLOAD_ERR_NO_FILE) {
            $f = $_FILES['screenshot'];
            $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
            $mime = $f['error'] === UPLOAD_ERR_OK ? (new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']) : '';
            if ($f['error'] !== UPLOAD_ERR_OK || !isset($allowed[$mime]) || $f['size'] > 5 * 1024 * 1024) {
                brFlash('error', 'Invalid screenshot. Only JPG/PNG/WEBP/GIF up to 5MB are allowed.');
                brBack();
            }
            $dir = ROOT_PATH . '/uploads/bugreports';
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $name = 'bug_' . $uid . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
            if (!move_uploaded_file($f['tmp_name'], $dir . '/' . $name)) {
                brFlash('error', 'Screenshot upload failed. Please try again.');
                brBack();
            }
            $shot = '/uploads/bugreports/' . $name;
        }

        $st = $conn->prepare("INSERT INTO noble_bug_reports
            (account_id, username, role, branch, type, priority, title, page_url, description, screenshot)
            VALUES (?,?,?,?,?,?,?,?,?,?)");
        $st->bind_param('isssssssss', $uid, $uname, $myRole, $myBranch, $type, $priority, $title, $pageUrl, $desc, $shot);
        $st->execute();
        $st->close();

        brFlash('success', 'Thank you! Your report has been sent to IT.');
        brBack();
    }

    // ── Update status / reply (IT only) ──
    if ($action === 'update' && $isIT) {
        $id     = intval($_POST['id'] ?? 0);
        $status = array_key_exists($_POST['status'] ?? '', $STATUSES) ? $_POST['status'] : 'open';
        $reply  = trim((string) ($_POST['it_reply'] ?? ''));

        $st = $conn->prepare("UPDATE noble_bug_reports SET status = ?, it_reply = ?, handled_by = ? WHERE id = ?");
        $st->bind_param('sssi', $status, $reply, $uname, $id);
        $st->execute();
        $st->close();

        brFlash('success', 'Report #' . $id . ' updated.');
        brBack();
    }

    // ── Delete: IT can delete any; reporters only their own while still Open ──
    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $st = $conn->prepare("SELECT account_id, status, screenshot FROM noble_bug_reports WHERE id = ?");
        $st->bind_param('i', $id);
        $st->execute();
        $row = $st->get_result()->fetch_assoc();
        $st->close();

        if ($row && ($isIT || ((int) $row['account_id'] === $uid && $row['status'] === 'open'))) {
            $st = $conn->prepare("DELETE FROM noble_bug_reports WHERE id = ?");
            $st->bind_param('i', $id);
            $st->execute();
            $st->close();
            if ($row['screenshot'] !== '' && is_file(ROOT_PATH . $row['screenshot'])) {
                @unlink(ROOT_PATH . $row['screenshot']);
            }
            brFlash('success', 'Report #' . $id . ' deleted.');
        } else {
            brFlash('error', 'You cannot delete this report.');
        }
        brBack();
    }

    brBack();
}

// ═══════════════════════════════════════════════════════════════
//  FETCH
// ═══════════════════════════════════════════════════════════════
$flash = $_SESSION['br_flash'] ?? null;
unset($_SESSION['br_flash']);

$fStatus = array_key_exists($_GET['status'] ?? '', $STATUSES) ? $_GET['status'] : '';
$fType   = array_key_exists($_GET['type'] ?? '', $TYPES) ? $_GET['type'] : '';
$fQ      = trim((string) ($_GET['q'] ?? ''));

$where = [];
$types = '';
$params = [];
if (!$isIT) {
    $where[] = 'account_id = ?';
    $types .= 'i';
    $params[] = $uid;
}
if ($fStatus !== '') {
    $where[] = 'status = ?';
    $types .= 's';
    $params[] = $fStatus;
}
if ($fType !== '') {
    $where[] = 'type = ?';
    $types .= 's';
    $params[] = $fType;
}
if ($fQ !== '') {
    $where[] = '(title LIKE ? OR description LIKE ? OR username LIKE ?)';
    $like = '%' . $fQ . '%';
    $types .= 'sss';
    array_push($params, $like, $like, $like);
}
$sql = "SELECT * FROM noble_bug_reports"
    . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
    . " ORDER BY FIELD(status,'open','in_progress','resolved','rejected'),
               FIELD(priority,'urgent','high','normal','low'), created_at DESC
        LIMIT 300";
$st = $conn->prepare($sql);
if ($params) {
    $st->bind_param($types, ...$params);
}
$st->execute();
$reports = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

// Count per status (IT: all; others: own only)
$stats = array_fill_keys(array_keys($STATUSES), 0);
$cq = $isIT
    ? $conn->query("SELECT status, COUNT(*) c FROM noble_bug_reports GROUP BY status")
    : $conn->query("SELECT status, COUNT(*) c FROM noble_bug_reports WHERE account_id = $uid GROUP BY status");
while ($cq && $r = $cq->fetch_assoc()) {
    $stats[$r['status']] = (int) $r['c'];
}

$statusCls = [
    'open'        => 'bg-amber-50 text-amber-800 border-amber-200',
    'in_progress' => 'bg-blue-50 text-blue-700 border-blue-200',
    'resolved'    => 'bg-green-50 text-green-700 border-green-200',
    'rejected'    => 'bg-gray-100 text-gray-600 border-gray-200',
];
$prioCls = [
    'low'    => 'text-gray-500',
    'normal' => 'text-slate-600',
    'high'   => 'text-orange-600',
    'urgent' => 'text-red-600',
];
$h = fn($s) => htmlspecialchars((string) $s, ENT_QUOTES);
$qs = fn(array $over) => '?' . http_build_query(array_filter(array_merge(['status' => $fStatus, 'type' => $fType, 'q' => $fQ], $over), fn($v) => $v !== ''));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Bug & Improvement</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
</head>

<body class="bg-slate-100">

    <main class="ml-56 min-h-screen p-8">

        <!-- Header -->
        <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-amber-700">Support</p>
                <h1 class="text-2xl font-extrabold tracking-tight text-gray-900">
                    <?= $isIT ? 'Bug Reports & Improvements' : 'Report a Problem' ?>
                </h1>
                <p class="text-sm text-gray-500 mt-1">
                    <?= $isIT
                        ? 'All reports from every department. Update the status and reply so they can see it.'
                        : 'Found something broken or have an idea to improve the system? Let IT know here.' ?>
                </p>
            </div>
            <a href="<?= BASE_URL ?>/systemupdates"
                class="inline-flex items-center gap-2 h-9 px-3.5 rounded-lg bg-white border border-gray-200 text-xs font-semibold text-gray-700 hover:border-amber-300 hover:text-amber-800">
                <i class="fa-solid fa-bullhorn text-amber-600"></i> View System Updates
            </a>
        </div>

        <?php if ($flash): ?>
            <div class="mb-5 flex items-center gap-2 rounded-xl border px-4 py-3 text-sm font-medium
                <?= $flash['type'] === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-700' ?>">
                <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : 'fa-triangle-exclamation' ?>"></i>
                <?= $h($flash['msg']) ?>
            </div>
        <?php endif; ?>

        <!-- Stats -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
            <?php foreach ($STATUSES as $k => $label): ?>
                <a href="<?= $h($qs(['status' => $fStatus === $k ? '' : $k])) ?>"
                    class="rounded-xl border bg-white px-4 py-3 transition hover:shadow-sm <?= $fStatus === $k ? 'border-amber-400 ring-2 ring-amber-100' : 'border-gray-200' ?>">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-gray-400"><?= $label ?></p>
                    <p class="text-2xl font-extrabold text-gray-900"><?= $stats[$k] ?></p>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-cols-1 xl:grid-cols-[380px_1fr] gap-6 items-start">

            <!-- ═══ FORM ═══ -->
            <form method="post" enctype="multipart/form-data"
                class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden xl:sticky xl:top-8">
                <div class="h-1 bg-[repeating-linear-gradient(-45deg,#f59e0b_0_8px,#1f2937_8px_16px)]"></div>
                <div class="p-5 space-y-4">
                    <div>
                        <h2 class="text-sm font-bold text-gray-900">New Report</h2>
                        <p class="text-[11px] text-gray-500">The more details, the faster we can fix it.</p>
                    </div>
                    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                    <input type="hidden" name="action" value="submit">

                    <div class="grid grid-cols-2 gap-2">
                        <?php foreach ($TYPES as $k => $label): ?>
                            <label class="cursor-pointer">
                                <input type="radio" name="type" value="<?= $k ?>" class="peer sr-only" <?= $k === 'bug' ? 'checked' : '' ?>>
                                <span class="flex items-center justify-center gap-2 h-10 rounded-lg border border-gray-200 text-xs font-semibold text-gray-600
                                    peer-checked:border-amber-400 peer-checked:bg-amber-50 peer-checked:text-amber-900">
                                    <i class="fa-solid <?= $k === 'bug' ? 'fa-bug' : 'fa-lightbulb' ?>"></i>
                                    <?= $k === 'bug' ? 'Bug' : 'Suggestion' ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 mb-1">Title <span class="text-red-500">*</span></label>
                        <input type="text" name="title" maxlength="150" required
                            placeholder="e.g. Cannot save quotation"
                            class="w-full h-10 rounded-lg border border-gray-200 px-3 text-sm focus:outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100">
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-gray-600 mb-1">Priority</label>
                            <select name="priority"
                                class="w-full h-10 rounded-lg border border-gray-200 px-2 text-sm bg-white focus:outline-none focus:border-amber-400">
                                <?php foreach ($PRIORITIES as $k => $label): ?>
                                    <option value="<?= $k ?>" <?= $k === 'normal' ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-gray-600 mb-1">Page</label>
                            <?php
                            // Page names from the sidebar menu ($sbMenu etc. come from sidebar.php)
                            $pageNames = [];
                            $refPage = '';
                            $refPath = rtrim((string) parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH), '/');
                            $menuItems = array_merge(array_merge([], ...array_values($sbMenu ?? [])), $sbFollowups ?? [], $sbSettings ?? []);
                            foreach ($menuItems as $mi) {
                                if (($mi['href'] ?? '#') === '#' || str_contains($mi['href'], 'reportbugandimprovement')) continue;
                                $pageNames[$mi['label']] = true;
                                if ($refPage === '' && $refPath !== '' && str_ends_with($refPath, rtrim($mi['href'], '/'))) {
                                    $refPage = $mi['label'];
                                }
                            }
                            ?>
                            <input type="text" name="page_url" maxlength="255" value="<?= $h($refPage) ?>" list="br-pages"
                                placeholder="e.g. CRM List" autocomplete="off"
                                class="w-full h-10 rounded-lg border border-gray-200 px-3 text-sm focus:outline-none focus:border-amber-400">
                            <datalist id="br-pages">
                                <?php foreach (array_keys($pageNames) as $pn): ?>
                                    <option value="<?= $h($pn) ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                        </div>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 mb-1">Description <span class="text-red-500">*</span></label>
                        <textarea name="description" rows="5" required
                            placeholder="What did you do, what happened, and what did you expect to happen?"
                            class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:border-amber-400 focus:ring-2 focus:ring-amber-100"></textarea>
                    </div>

                    <div>
                        <label class="block text-[11px] font-bold text-gray-600 mb-1">Screenshot (optional)</label>
                        <input type="file" name="screenshot" accept="image/jpeg,image/png,image/webp,image/gif"
                            class="block w-full text-xs text-gray-500 file:mr-3 file:h-9 file:px-3 file:rounded-lg file:border-0 file:bg-gray-100 file:text-xs file:font-semibold file:text-gray-700 hover:file:bg-gray-200">
                        <p class="text-[10px] text-gray-400 mt-1">JPG / PNG / WEBP / GIF, up to 5MB</p>
                    </div>

                    <button type="submit"
                        class="w-full inline-flex items-center justify-center gap-2 h-10 rounded-lg bg-gray-900 text-white text-sm font-semibold hover:bg-gray-800">
                        <i class="fa-solid fa-paper-plane text-xs"></i> Send to IT
                    </button>
                </div>
            </form>

            <!-- ═══ LIST ═══ -->
            <section class="bg-white rounded-2xl border border-gray-200 shadow-sm">
                <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 border-b border-gray-100">
                    <h2 class="text-sm font-bold text-gray-900">
                        <?= $isIT ? 'All Reports' : 'My Reports' ?>
                        <span class="ml-1 text-xs font-semibold text-gray-400">(<?= count($reports) ?>)</span>
                    </h2>
                    <form method="get" class="flex flex-wrap items-center gap-2">
                        <?php if ($fStatus !== ''): ?><input type="hidden" name="status" value="<?= $h($fStatus) ?>"><?php endif; ?>
                        <select name="type" onchange="this.form.submit()"
                            class="h-9 rounded-lg border border-gray-200 px-2 text-xs bg-white">
                            <option value="">All types</option>
                            <?php foreach ($TYPES as $k => $label): ?>
                                <option value="<?= $k ?>" <?= $fType === $k ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                        <input type="search" name="q" value="<?= $h($fQ) ?>" placeholder="Search..."
                            class="h-9 w-44 rounded-lg border border-gray-200 px-3 text-xs focus:outline-none focus:border-amber-400">
                        <?php if ($fStatus || $fType || $fQ): ?>
                            <a href="?" class="text-xs font-semibold text-gray-500 hover:text-gray-800">Clear</a>
                        <?php endif; ?>
                    </form>
                </div>

                <?php if (!$reports): ?>
                    <div class="px-6 py-16 text-center">
                        <div class="w-14 h-14 mx-auto rounded-full bg-gray-100 text-gray-400 flex items-center justify-center mb-3">
                            <i class="fa-regular fa-face-smile text-xl"></i>
                        </div>
                        <p class="text-sm font-semibold text-gray-600">No reports yet</p>
                        <p class="text-[11px] text-gray-400 mt-1">If you find something broken, just report it using the form.</p>
                    </div>
                <?php else: ?>
                    <ul class="divide-y divide-gray-100">
                        <?php foreach ($reports as $r): ?>
                            <li class="px-5 py-4">
                                <div class="flex items-start gap-3">
                                    <span class="mt-0.5 w-8 h-8 shrink-0 rounded-lg flex items-center justify-center
                                        <?= $r['type'] === 'bug' ? 'bg-red-50 text-red-600' : 'bg-amber-50 text-amber-700' ?>">
                                        <i class="fa-solid <?= $r['type'] === 'bug' ? 'fa-bug' : 'fa-lightbulb' ?> text-xs"></i>
                                    </span>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="text-[11px] font-mono text-gray-400">#<?= (int) $r['id'] ?></span>
                                            <p class="text-sm font-bold text-gray-900"><?= $h($r['title']) ?></p>
                                            <span class="px-2 py-0.5 rounded-full border text-[10px] font-bold <?= $statusCls[$r['status']] ?>">
                                                <?= $STATUSES[$r['status']] ?>
                                            </span>
                                            <span class="text-[10px] font-bold uppercase <?= $prioCls[$r['priority']] ?>">
                                                <i class="fa-solid fa-flag text-[9px]"></i> <?= $PRIORITIES[$r['priority']] ?>
                                            </span>
                                        </div>
                                        <p class="text-[11px] text-gray-500 mt-0.5">
                                            <?php if ($isIT): ?>
                                                <span class="font-semibold text-gray-700"><?= $h($r['username']) ?></span>
                                                · <?= $h($r['role']) ?><?= $r['branch'] !== '' ? ' · ' . $h($r['branch']) : '' ?> ·
                                            <?php endif; ?>
                                            <?= date('M j, Y g:i A', strtotime($r['created_at'])) ?>
                                            <?php if ($r['page_url'] !== ''): ?>
                                                · <i class="fa-regular fa-window-maximize text-[9px]"></i> <span class="font-semibold text-gray-600"><?= $h($r['page_url']) ?></span>
                                            <?php endif; ?>
                                        </p>
                                        <p class="text-[13px] text-gray-700 mt-2 whitespace-pre-line break-words"><?= $h($r['description']) ?></p>

                                        <?php if ($r['screenshot'] !== ''): ?>
                                            <a href="<?= BASE_URL . $h($r['screenshot']) ?>" target="_blank" rel="noopener" class="inline-block mt-2">
                                                <img src="<?= BASE_URL . $h($r['screenshot']) ?>" alt="Screenshot"
                                                    class="h-24 rounded-lg border border-gray-200 object-cover hover:opacity-90">
                                            </a>
                                        <?php endif; ?>

                                        <?php if (!empty($r['it_reply'])): ?>
                                            <div class="mt-3 rounded-lg bg-slate-50 border border-slate-200 px-3 py-2">
                                                <p class="text-[10px] font-bold uppercase tracking-wider text-slate-500">
                                                    <i class="fa-solid fa-headset"></i> IT Reply<?= $r['handled_by'] !== '' ? ' · ' . $h($r['handled_by']) : '' ?>
                                                </p>
                                                <p class="text-[13px] text-slate-700 mt-0.5 whitespace-pre-line"><?= $h($r['it_reply']) ?></p>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($isIT): ?>
                                            <details class="mt-3 group/d">
                                                <summary class="cursor-pointer list-none inline-flex items-center gap-1.5 text-[11px] font-semibold text-amber-700 hover:text-amber-800">
                                                    <i class="fa-solid fa-pen-to-square"></i> Update / Reply
                                                </summary>
                                                <form method="post" class="mt-2 grid gap-2 sm:grid-cols-[160px_1fr_auto] items-start">
                                                    <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                                                    <input type="hidden" name="action" value="update">
                                                    <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                                    <select name="status" class="h-9 rounded-lg border border-gray-200 px-2 text-xs bg-white">
                                                        <?php foreach ($STATUSES as $k => $label): ?>
                                                            <option value="<?= $k ?>" <?= $r['status'] === $k ? 'selected' : '' ?>><?= $label ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <textarea name="it_reply" rows="2" placeholder="Reply to the reporter (they will see this)"
                                                        class="rounded-lg border border-gray-200 px-3 py-2 text-xs focus:outline-none focus:border-amber-400"><?= $h($r['it_reply'] ?? '') ?></textarea>
                                                    <button type="submit" class="h-9 px-3 rounded-lg bg-gray-900 text-white text-xs font-semibold hover:bg-gray-800">Save</button>
                                                </form>
                                            </details>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($isIT || ((int) $r['account_id'] === $uid && $r['status'] === 'open')): ?>
                                        <form method="post" onsubmit="return confirm('Delete report #<?= (int) $r['id'] ?>?')">
                                            <input type="hidden" name="csrf" value="<?= $h($csrf) ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                                            <button type="submit" title="Delete"
                                                class="w-8 h-8 rounded-lg text-gray-300 hover:text-red-600 hover:bg-red-50">
                                                <i class="fa-solid fa-trash-can text-xs"></i>
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        </div>

    </main>

</body>

</html>
