<?php
// listaccount.php  —  HR: monitoring ng accounts (view only)

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_HR];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

if (!function_exists('e')) {
    function e($v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}

// "5m ago", "3h ago", "2d ago", o petsa kapag matagal na
function timeAgo(?string $dt): string
{
    if (!$dt) return '';
    $t = strtotime($dt);
    if (!$t) return '';
    $d = time() - $t;
    if ($d < 60) return 'just now';
    if ($d < 3600) return floor($d / 60) . 'm ago';
    if ($d < 86400) return floor($d / 3600) . 'h ago';
    if ($d < 86400 * 7) return floor($d / 86400) . 'd ago';
    return date('M j, Y', $t);
}

function idleAgo(?int $sec): string
{
    if ($sec === null) return '';
    if ($sec < 60) return 'just now';
    if ($sec < 3600) return floor($sec / 60) . 'm ago';
    if ($sec < 86400) return floor($sec / 3600) . 'h ago';
    return floor($sec / 86400) . 'd ago';
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name));
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) $out .= mb_substr($p, 0, 1);
    return strtoupper($out ?: '?');
}

$ONLINE_SECONDS = 60;     // pareho sa sidebar: online kapag may heartbeat sa loob ng 60 segundo

// ── USER ACCOUNTS (nobleaccount) ─────────────────────────────
// Walang token o password na kinukuha
$users = [];
try {
    $sql = "SELECT a.id, a.name, a.email, a.role, a.verified, a.token_expires, a.created_at,
                   n.employeeid, n.position AS emp_position, n.is_official
            FROM nobleaccount a
            LEFT JOIN nobleid n ON n.account_id = a.id
            ORDER BY a.created_at DESC";
    $users = $conn->query($sql)->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $ex) {
    // kapag wala pang account_id / is_official sa nobleid, accounts lang
    $users = $conn->query("SELECT id, name, email, role, verified, token_expires, created_at
                           FROM nobleaccount ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
}

$uStats = ['total' => count($users), 'verified' => 0, 'unverified' => 0, 'linked' => 0, 'new' => 0];
foreach ($users as &$u) {
    $u['is_verified'] = (int) $u['verified'] === 1;
    $u['is_linked']   = !empty($u['employeeid']);
    $u['is_new']      = !empty($u['created_at']) && strtotime($u['created_at']) >= strtotime('-7 days');
    // naka-expire na ang verification link at hindi pa verified
    $u['link_expired'] = !$u['is_verified'] && !empty($u['token_expires']) && strtotime($u['token_expires']) < time();

    $u['is_verified'] ? $uStats['verified']++ : $uStats['unverified']++;
    if ($u['is_linked']) $uStats['linked']++;
    if ($u['is_new']) $uStats['new']++;
}
unset($u);

// ── ADMIN ACCOUNTS (noblerole) ───────────────────────────────
$admins = $conn->query("SELECT id, name, email, role, position, branch, last_active, created_at,
                               TIMESTAMPDIFF(SECOND, last_active, NOW())        AS idle_sec,
                               (DATE(last_active) = CURDATE())                  AS is_today,
                               DATE_FORMAT(last_active, '%b %e, %Y · %l:%i %p') AS last_label
                        FROM noblerole ORDER BY (last_active IS NULL), last_active DESC")->fetch_all(MYSQLI_ASSOC);

$aStats = ['total' => count($admins), 'online' => 0, 'today' => 0, 'inactive' => 0];
$roleList = [];
foreach ($admins as &$a) {
    // Oras galing sa MySQL (naka +08:00 sa connect.php), hindi sa PHP
    $idle = $a['idle_sec'] === null ? null : max(0, (int) $a['idle_sec']);
    $a['idle_sec']    = $idle;
    $a['is_online']   = $idle !== null && $idle <= $ONLINE_SECONDS;
    $a['is_today']    = (int) $a['is_today'] === 1;
    $a['is_inactive'] = $idle === null || $idle > 30 * 86400;

    if ($a['is_online']) $aStats['online']++;
    if ($a['is_today']) $aStats['today']++;
    if ($a['is_inactive']) $aStats['inactive']++;
    if (!empty($a['role'])) $roleList[$a['role']] = true;
}
unset($a);
ksort($roleList);

$prettyRole = fn($r) => strlen((string) $r) <= 3 ? strtoupper((string) $r) : ucwords(str_replace(['_', '-'], ' ', (string) $r));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Monitoring</title>
    <style>
        .la-hazard { background: repeating-linear-gradient(-45deg, #f59e0b 0 10px, #1f2937 10px 20px); }
        .la-tab[data-on="1"] { background: #111827; color: #fbbf24; box-shadow: 0 1px 2px rgba(0,0,0,.08); }
        .la-tab[data-on="1"] .la-badge { background: rgba(255,255,255,.15); color: #fff; }
        .la-chip[data-on="1"] { background: #b45309; border-color: #b45309; color: #fff; }
        .la-scroll { scrollbar-width: thin; scrollbar-color: #d1d5db transparent; }
    </style>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
</head>

<body class="bg-slate-100">
    <main id="main-content" class="md:ml-56 min-h-screen pt-20 md:pt-8 p-4 md:p-8">
      <div class="max-w-7xl mx-auto">

        <!-- ═════ HEADER + TABS ═════ -->
        <section class="bg-white border border-gray-200 rounded-xl mb-4 overflow-hidden">
            <div class="la-hazard h-1.5"></div>
            <div class="p-5 flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <span class="w-11 h-11 rounded-xl bg-amber-50 text-amber-700 flex items-center justify-center shrink-0"><i class="fa-solid fa-users-viewfinder text-lg"></i></span>
                    <div>
                        <p class="text-amber-700 text-[10px] font-semibold tracking-[0.15em] uppercase">HR · View only</p>
                        <h1 class="text-gray-900 text-lg font-semibold leading-tight">Account Monitoring</h1>
                        <p class="text-gray-400 text-[11px] mt-0.5">Every user and admin account in the system.</p>
                    </div>
                </div>
                <div class="inline-flex items-center gap-2 rounded-lg border border-gray-200 bg-white h-9 px-3 text-[11px] text-gray-500">
                    <i class="fa-regular fa-clock"></i> As of <?= date('M j, Y · g:i A') ?>
                    <button type="button" onclick="location.reload()" class="ml-1 w-6 h-6 inline-flex items-center justify-center rounded-md text-gray-400 hover:text-amber-700 hover:bg-amber-50" title="Refresh"><i class="fa-solid fa-rotate-right text-[11px]"></i></button>
                </div>
            </div>
            <div class="px-5 pb-4">
                <div class="inline-flex items-center gap-1 p-1 rounded-xl bg-gray-50 border border-gray-200">
                    <button type="button" data-tab="users" data-on="1"
                        class="la-tab inline-flex items-center gap-2 h-9 px-4 rounded-lg text-xs font-bold uppercase tracking-wider text-gray-500 hover:text-gray-800 transition">
                        <i class="fa-solid fa-users"></i> User Accounts
                        <span class="la-badge px-1.5 rounded-full bg-gray-200 text-gray-600 text-[10px]"><?= $uStats['total'] ?></span>
                    </button>
                    <button type="button" data-tab="admins" data-on="0"
                        class="la-tab inline-flex items-center gap-2 h-9 px-4 rounded-lg text-xs font-bold uppercase tracking-wider text-gray-500 hover:text-gray-800 transition">
                        <i class="fa-solid fa-user-shield"></i> Admin Accounts
                        <span class="la-badge px-1.5 rounded-full bg-gray-200 text-gray-600 text-[10px]"><?= $aStats['total'] ?></span>
                    </button>
                </div>
            </div>
        </section>

        <?php
        // maliit na helper para sa stat cards
        function statCard($icon, $iconCls, $label, $value, $hint = '', $id = '')
        { ?>
            <div class="rounded-xl bg-white border border-gray-200 px-4 py-3.5 flex items-center gap-3.5">
                <span class="w-10 h-10 rounded-xl flex items-center justify-center text-sm shrink-0 <?= $iconCls ?>"><i class="<?= $icon ?>"></i></span>
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-gray-500"><?= $label ?></p>
                    <p class="text-xl font-bold text-gray-900 leading-tight tabular-nums"<?= $id ? ' id="' . $id . '"' : '' ?>><?= $value ?></p>
                    <?php if ($hint): ?><p class="text-[10.5px] text-gray-400 truncate"><?= $hint ?></p><?php endif; ?>
                </div>
            </div>
        <?php } ?>

        <!-- ═════════════ USER ACCOUNTS ═════════════ -->
        <section id="panel-users" class="la-panel">
            <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 mb-4">
                <?php
                statCard('fa-solid fa-users', 'bg-slate-100 text-slate-700', 'Total Users', $uStats['total'], $uStats['new'] . ' new this week');
                statCard('fa-solid fa-circle-check', 'bg-green-50 text-green-600', 'Verified', $uStats['verified'],
                    $uStats['total'] ? round($uStats['verified'] / $uStats['total'] * 100) . '% of users' : '');
                statCard('fa-solid fa-envelope-circle-check', 'bg-amber-50 text-amber-600', 'Not Verified', $uStats['unverified'], 'Email not yet confirmed');
                statCard('fa-solid fa-id-card', 'bg-amber-50 text-amber-700', 'Linked to ID', $uStats['linked'],
                    ($uStats['total'] - $uStats['linked']) . ' without employee ID');
                ?>
            </div>

            <div class="rounded-xl bg-white border border-gray-200 overflow-hidden">
                <!-- toolbar -->
                <div class="flex flex-wrap items-center gap-2.5 px-5 py-3 border-b border-gray-100 bg-gray-50/60">
                    <div class="relative flex-1 min-w-[220px] max-w-sm">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" data-q="users" placeholder="Search name, email or employee ID…"
                            class="w-full h-9 rounded-lg border border-gray-200 bg-white pl-8 pr-3 text-xs outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-100">
                    </div>
                    <div class="flex flex-wrap gap-1.5" data-chips="users">
                        <?php foreach (['all' => 'All', 'verified' => 'Verified', 'unverified' => 'Not Verified', 'linked' => 'Linked ID', 'nolink' => 'No ID', 'new' => 'New (7 days)'] as $k => $lbl): ?>
                            <button type="button" data-filter="<?= $k ?>"
                                data-on="<?= $k === 'all' ? 1 : 0 ?>" class="la-chip rounded-full border border-gray-200 bg-white px-3 py-1 text-[11px] font-semibold text-gray-600 hover:border-amber-300 transition"><?= $lbl ?></button>
                        <?php endforeach; ?>
                    </div>
                    <span class="ml-auto text-xs text-slate-400" data-count="users"></span>
                </div>

                <div class="overflow-auto max-h-[62vh] la-scroll">
                    <table class="w-full text-sm min-w-[880px]">
                        <thead class="sticky top-0 z-10 bg-gray-50 text-[10px] font-semibold uppercase tracking-wider text-gray-500">
                            <tr class="shadow-[inset_0_-1px_0_#f3f4f6]">
                                <th class="px-5 py-3 text-left">Account</th>
                                <th class="px-5 py-3 text-left">Role</th>
                                <th class="px-5 py-3 text-left">Email Status</th>
                                <th class="px-5 py-3 text-left">Employee ID</th>
                                <th class="px-5 py-3 text-left">Registered</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100" data-list="users">
                            <?php foreach ($users as $u):
                                $tags = ['all', $u['is_verified'] ? 'verified' : 'unverified', $u['is_linked'] ? 'linked' : 'nolink'];
                                if ($u['is_new']) $tags[] = 'new';
                                $search = strtolower($u['name'] . ' ' . $u['email'] . ' ' . ($u['employeeid'] ?? ''));
                            ?>
                                <tr class="hover:bg-amber-50/40 transition" data-tags="<?= implode(' ', $tags) ?>" data-search="<?= e($search) ?>">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="w-9 h-9 shrink-0 rounded-lg bg-amber-50 text-amber-700 text-xs font-bold flex items-center justify-center"><?= e(initials($u['name'])) ?></span>
                                            <div class="min-w-0">
                                                <p class="font-semibold text-slate-900 truncate flex items-center gap-1.5">
                                                    <?= e($u['name']) ?>
                                                    <?php if (!empty($u['is_official'])): ?><i class="fa-solid fa-circle-check text-sky-500 text-xs" title="Official Employee"></i><?php endif; ?>
                                                    <?php if ($u['is_new']): ?><span class="rounded bg-amber-100 text-amber-800 px-1.5 text-[10px] font-bold">NEW</span><?php endif; ?>
                                                </p>
                                                <p class="text-xs text-slate-500 truncate"><?= e($u['email']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3 text-slate-600"><?= e($prettyRole($u['role'] ?: 'user')) ?></td>
                                    <td class="px-5 py-3">
                                        <?php if ($u['is_verified']): ?>
                                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-green-700"><span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>Verified</span>
                                        <?php elseif ($u['link_expired']): ?>
                                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-red-600"><span class="w-1.5 h-1.5 rounded-full bg-red-500"></span>Link expired</span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-amber-600"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>Pending</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-3">
                                        <?php if ($u['is_linked']): ?>
                                            <p class="font-mono text-xs font-semibold text-slate-800"><?= e($u['employeeid']) ?></p>
                                            <?php if (!empty($u['emp_position'])): ?><p class="text-[11px] text-slate-400"><?= e($u['emp_position']) ?></p><?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400">Not linked</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-5 py-3 whitespace-nowrap">
                                        <p class="text-slate-700"><?= $u['created_at'] ? date('M j, Y', strtotime($u['created_at'])) : '—' ?></p>
                                        <p class="text-[11px] text-slate-400"><?= e(timeAgo($u['created_at'])) ?></p>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="hidden py-14 text-center text-sm text-slate-400" data-empty="users">
                        <i class="fa-solid fa-user-slash text-2xl text-slate-300 block mb-2"></i>No accounts match your filters.
                    </div>
                </div>
            </div>
        </section>

        <!-- ═════════════ ADMIN ACCOUNTS ═════════════ -->
        <section id="panel-admins" class="la-panel hidden">
            <div class="grid grid-cols-2 xl:grid-cols-4 gap-3 mb-4">
                <?php
                statCard('fa-solid fa-user-shield', 'bg-slate-100 text-slate-700', 'Total Admins', $aStats['total'], count($roleList) . ' roles');
                statCard('fa-solid fa-signal', 'bg-green-50 text-green-600', 'Online Now', $aStats['online'], '<span class="inline-flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>Live · updates every 15s</span>', 'st-online');
                statCard('fa-regular fa-calendar-check', 'bg-sky-50 text-sky-600', 'Active Today', $aStats['today'], date('l'), 'st-today');
                statCard('fa-solid fa-moon', 'bg-slate-100 text-slate-500', 'Inactive 30+ Days', $aStats['inactive'], 'Or never logged in', 'st-inactive');
                ?>
            </div>

            <div class="rounded-xl bg-white border border-gray-200 overflow-hidden">
                <div class="flex flex-wrap items-center gap-2.5 px-5 py-3 border-b border-gray-100 bg-gray-50/60">
                    <div class="relative flex-1 min-w-[220px] max-w-sm">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" data-q="admins" placeholder="Search name, email or branch…"
                            class="w-full h-9 rounded-lg border border-gray-200 bg-white pl-8 pr-3 text-xs outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-100">
                    </div>
                    <select data-rolefilter="admins" class="h-9 rounded-lg border border-gray-200 bg-white px-3 text-xs text-gray-700 outline-none focus:border-amber-600 focus:ring-2 focus:ring-amber-100">
                        <option value="">All roles</option>
                        <?php foreach (array_keys($roleList) as $r): ?>
                            <option value="<?= e(strtolower($r)) ?>"><?= e($prettyRole($r)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="flex flex-wrap gap-1.5" data-chips="admins">
                        <?php foreach (['all' => 'All', 'online' => 'Online', 'today' => 'Active Today', 'inactive' => 'Inactive'] as $k => $lbl): ?>
                            <button type="button" data-filter="<?= $k ?>"
                                data-on="<?= $k === 'all' ? 1 : 0 ?>" class="la-chip rounded-full border border-gray-200 bg-white px-3 py-1 text-[11px] font-semibold text-gray-600 hover:border-amber-300 transition"><?= $lbl ?></button>
                        <?php endforeach; ?>
                    </div>
                    <span class="ml-auto text-xs text-slate-400" data-count="admins"></span>
                </div>

                <div class="overflow-auto max-h-[62vh] la-scroll">
                    <table class="w-full text-sm min-w-[900px]">
                        <thead class="sticky top-0 z-10 bg-gray-50 text-[10px] font-semibold uppercase tracking-wider text-gray-500">
                            <tr class="shadow-[inset_0_-1px_0_#f3f4f6]">
                                <th class="px-5 py-3 text-left">Admin</th>
                                <th class="px-5 py-3 text-left">Role</th>
                                <th class="px-5 py-3 text-left">Position</th>
                                <th class="px-5 py-3 text-left">Branch</th>
                                <th class="px-5 py-3 text-left">Status</th>
                                <th class="px-5 py-3 text-left">Last Active</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100" data-list="admins">
                            <?php foreach ($admins as $a):
                                $tags = ['all'];
                                if ($a['is_online']) $tags[] = 'online';
                                if ($a['is_today']) $tags[] = 'today';
                                if ($a['is_inactive']) $tags[] = 'inactive';
                                $search = strtolower($a['name'] . ' ' . $a['email'] . ' ' . ($a['branch'] ?? '') . ' ' . ($a['position'] ?? ''));
                            ?>
                                <tr class="hover:bg-amber-50/40 transition" data-id="<?= (int) $a['id'] ?>" data-tags="<?= implode(' ', $tags) ?>" data-search="<?= e($search) ?>" data-role="<?= e(strtolower($a['role'] ?? '')) ?>">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="relative w-9 h-9 shrink-0 rounded-lg bg-gray-900 text-amber-400 text-xs font-bold flex items-center justify-center">
                                                <?= e(initials($a['name'])) ?>
                                                <span data-live="dot" class="absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full ring-2 ring-white <?= $a['is_online'] ? 'bg-green-500' : 'bg-slate-300' ?>"></span>
                                            </span>
                                            <div class="min-w-0">
                                                <p class="font-semibold text-slate-900 truncate"><?= e($a['name']) ?></p>
                                                <p class="text-xs text-slate-500 truncate"><?= e($a['email']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-5 py-3">
                                        <span class="inline-block rounded-md bg-amber-50 border border-amber-200 px-2 py-0.5 text-[10.5px] font-semibold text-amber-800 uppercase tracking-wide"><?= e($prettyRole($a['role'])) ?></span>
                                    </td>
                                    <td class="px-5 py-3 text-slate-600"><?= e($prettyRole($a['position'])) ?: '—' ?></td>
                                    <td class="px-5 py-3"><?php if (!empty($a['branch'])): ?><span class="inline-flex items-center gap-1 text-[11px] font-semibold text-sky-700"><i class="fa-solid fa-location-dot text-[10px]"></i><?= e($a['branch']) ?></span><?php else: ?><span class="text-xs text-red-500">No branch</span><?php endif; ?></td>
                                    <td class="px-5 py-3" data-live="status"></td>
                                    <td class="px-5 py-3 whitespace-nowrap" data-live="last"></td>
                                    <script>window.__la = window.__la || []; window.__la.push(<?= json_encode(['id' => (int) $a['id'], 'idle_sec' => $a['idle_sec'], 'is_today' => $a['is_today'], 'last_label' => $a['last_label']]) ?>);</script>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <div class="hidden py-14 text-center text-sm text-slate-400" data-empty="admins">
                        <i class="fa-solid fa-user-slash text-2xl text-slate-300 block mb-2"></i>No admins match your filters.
                    </div>
                </div>
            </div>
        </section>
      </div>
    </main>

    <script>
        (function () {
            // ── Tabs ──
            const tabs = document.querySelectorAll('.la-tab');
            tabs.forEach(t => t.addEventListener('click', () => {
                tabs.forEach(x => { x.dataset.on = x === t ? '1' : '0'; });
                document.querySelectorAll('.la-panel').forEach(p => p.classList.toggle('hidden', p.id !== 'panel-' + t.dataset.tab));
                try { localStorage.setItem('la-tab', t.dataset.tab); } catch (e) { }
            }));
            try {
                const saved = localStorage.getItem('la-tab');
                if (saved) document.querySelector(`.la-tab[data-tab="${saved}"]`)?.click();
            } catch (e) { }

            const ONLINE_SECONDS = <?= $ONLINE_SECONDS ?>;
            const filters = {};

            // ── Filters (search + chips + role) per table ──
            ['users', 'admins'].forEach(key => {
                const state = { q: '', f: 'all', role: '' };
                const rows = [...document.querySelectorAll(`[data-list="${key}"] tr`)];
                const count = document.querySelector(`[data-count="${key}"]`);
                const empty = document.querySelector(`[data-empty="${key}"]`);

                filters[key] = () => apply();
                function apply() {
                    let shown = 0;
                    rows.forEach(r => {
                        const ok = r.dataset.tags.split(' ').includes(state.f)
                            && (!state.q || r.dataset.search.includes(state.q))
                            && (!state.role || r.dataset.role === state.role);
                        r.classList.toggle('hidden', !ok);
                        if (ok) shown++;
                    });
                    count.textContent = `Showing ${shown} of ${rows.length}`;
                    empty.classList.toggle('hidden', shown > 0);
                }

                document.querySelector(`[data-q="${key}"]`).addEventListener('input', e => {
                    state.q = e.target.value.trim().toLowerCase(); apply();
                });
                document.querySelector(`[data-rolefilter="${key}"]`)?.addEventListener('change', e => {
                    state.role = e.target.value; apply();
                });
                const chips = document.querySelectorAll(`[data-chips="${key}"] .la-chip`);
                chips.forEach(c => c.addEventListener('click', () => {
                    state.f = c.dataset.filter;
                    chips.forEach(x => { x.dataset.on = x === c ? '1' : '0'; });
                    apply();
                }));
                apply();
            });

            // ═════ LIVE STATUS NG ADMINS ═════
            const ago = sec => sec < 60 ? 'just now' : sec < 3600 ? Math.floor(sec / 60) + 'm ago'
                : sec < 86400 ? Math.floor(sec / 3600) + 'h ago' : Math.floor(sec / 86400) + 'd ago';

            function paint(a) {
                const tr = document.querySelector(`[data-list="admins"] tr[data-id="${a.id}"]`);
                if (!tr) return null;
                const never = a.idle_sec === null;
                const online = !never && a.idle_sec <= ONLINE_SECONDS;
                const inactive = never || a.idle_sec > 30 * 86400;

                tr.querySelector('[data-live="dot"]').className =
                    'absolute -bottom-0.5 -right-0.5 w-3 h-3 rounded-full ring-2 ring-white ' + (online ? 'bg-green-500' : 'bg-slate-300');

                tr.querySelector('[data-live="status"]').innerHTML = online
                    ? '<span class="inline-flex items-center gap-1.5 rounded-full bg-green-50 px-2.5 py-0.5 text-xs font-semibold text-green-700"><span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>Online</span>'
                    : inactive
                        ? `<span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-semibold text-slate-500"><span class="w-1.5 h-1.5 rounded-full bg-slate-400"></span>${never ? 'Never' : 'Inactive'}</span>`
                        : '<span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-semibold text-amber-700"><span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>Away</span>';

                tr.querySelector('[data-live="last"]').innerHTML = never
                    ? '<span class="text-xs text-slate-400">No activity yet</span>'
                    : `<p class="text-slate-700">${online ? 'Active now' : ago(a.idle_sec)}</p><p class="text-[11px] text-slate-400">${a.last_label ?? ''}</p>`;

                const tags = ['all'];
                if (online) tags.push('online');
                if (a.is_today) tags.push('today');
                if (inactive) tags.push('inactive');
                tr.dataset.tags = tags.join(' ');
                return { online, today: !!a.is_today, inactive };
            }

            function paintAll(list) {
                let online = 0, today = 0, inactive = 0;
                list.forEach(a => {
                    const r = paint(a);
                    if (!r) return;
                    online += r.online; today += r.today; inactive += r.inactive;
                });
                const set = (id, v) => { const el = document.getElementById(id); if (el) el.textContent = v; };
                set('st-online', online); set('st-today', today); set('st-inactive', inactive);
                filters.admins && filters.admins();
            }

            paintAll(window.__la || []);

            async function poll() {
                if (document.hidden) return;
                try {
                    const res = await fetch('<?= BASE_URL ?>/listaccountajax', { cache: 'no-store' });
                    const data = await res.json();
                    if (data.ok) paintAll(data.admins);
                } catch (e) { /* subukan ulit sa susunod */ }
            }
            setInterval(poll, 15000);
            document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(); });
        })();
    </script>
</body>

</html>