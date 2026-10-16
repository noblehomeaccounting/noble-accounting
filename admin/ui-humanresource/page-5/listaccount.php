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
    <title>Human Resource List of Accounts</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
</head>

<body class="bg-slate-100">
    <main class="ml-56 min-h-screen p-8">

        <!-- ═════ HEADER ═════ -->
        <div class="flex flex-wrap items-end justify-between gap-4 mb-6">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.18em] text-[#F7941D]">Human Resource</p>
                <h1 class="text-2xl font-bold text-slate-900 mt-0.5">Account Monitoring</h1>
                <p class="text-sm text-slate-500 mt-0.5">View-only list of every user and admin account in the system.</p>
            </div>
            <div class="inline-flex items-center gap-2 rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs text-slate-500">
                <i class="fa-regular fa-clock"></i> As of <?= date('M j, Y · g:i A') ?>
                <button type="button" onclick="location.reload()" class="ml-1 text-slate-400 hover:text-[#F7941D]" title="Refresh"><i class="fa-solid fa-rotate-right"></i></button>
            </div>
        </div>

        <!-- ═════ TABS ═════ -->
        <div class="flex gap-1 border-b border-slate-200 mb-6">
            <button type="button" data-tab="users"
                class="la-tab relative inline-flex items-center gap-2 px-4 py-3 text-sm font-semibold text-slate-900">
                <i class="fa-solid fa-users"></i> User Accounts
                <span class="rounded-full bg-slate-900 text-white px-2 text-[11px] font-bold"><?= $uStats['total'] ?></span>
                <span class="la-bar absolute inset-x-3 -bottom-px h-[3px] rounded-t bg-[#F7941D]"></span>
            </button>
            <button type="button" data-tab="admins"
                class="la-tab relative inline-flex items-center gap-2 px-4 py-3 text-sm font-semibold text-slate-500 hover:text-slate-900">
                <i class="fa-solid fa-user-shield"></i> Admin Accounts
                <span class="rounded-full bg-slate-200 text-slate-600 px-2 text-[11px] font-bold"><?= $aStats['total'] ?></span>
                <span class="la-bar hidden absolute inset-x-3 -bottom-px h-[3px] rounded-t bg-[#F7941D]"></span>
            </button>
        </div>

        <?php
        // maliit na helper para sa stat cards
        function statCard($icon, $iconCls, $label, $value, $hint = '', $id = '')
        { ?>
            <div class="rounded-xl bg-white border border-slate-200 px-5 py-4 flex items-center gap-4">
                <span class="w-11 h-11 rounded-xl flex items-center justify-center text-base <?= $iconCls ?>"><i class="<?= $icon ?>"></i></span>
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400"><?= $label ?></p>
                    <p class="text-2xl font-bold text-slate-900 leading-tight tabular-nums"<?= $id ? ' id="' . $id . '"' : '' ?>><?= $value ?></p>
                    <?php if ($hint): ?><p class="text-[11px] text-slate-400"><?= $hint ?></p><?php endif; ?>
                </div>
            </div>
        <?php } ?>

        <!-- ═════════════ USER ACCOUNTS ═════════════ -->
        <section id="panel-users" class="la-panel">
            <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-5">
                <?php
                statCard('fa-solid fa-users', 'bg-slate-100 text-slate-700', 'Total Users', $uStats['total'], $uStats['new'] . ' new this week');
                statCard('fa-solid fa-circle-check', 'bg-green-50 text-green-600', 'Verified', $uStats['verified'],
                    $uStats['total'] ? round($uStats['verified'] / $uStats['total'] * 100) . '% of users' : '');
                statCard('fa-solid fa-envelope-circle-check', 'bg-amber-50 text-amber-600', 'Not Verified', $uStats['unverified'], 'Email not yet confirmed');
                statCard('fa-solid fa-id-card', 'bg-orange-50 text-[#F7941D]', 'Linked to ID', $uStats['linked'],
                    ($uStats['total'] - $uStats['linked']) . ' without employee ID');
                ?>
            </div>

            <div class="rounded-xl bg-white border border-slate-200 overflow-hidden">
                <!-- toolbar -->
                <div class="flex flex-wrap items-center gap-3 px-5 py-3.5 border-b border-slate-200">
                    <div class="relative flex-1 min-w-[220px] max-w-sm">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" data-q="users" placeholder="Search name, email or employee ID…"
                            class="w-full rounded-lg border border-slate-200 bg-slate-50 pl-8 pr-3 py-2 text-sm outline-none focus:border-[#F7941D] focus:bg-white focus:ring-2 focus:ring-orange-100">
                    </div>
                    <div class="flex flex-wrap gap-1.5" data-chips="users">
                        <?php foreach (['all' => 'All', 'verified' => 'Verified', 'unverified' => 'Not Verified', 'linked' => 'Linked ID', 'nolink' => 'No ID', 'new' => 'New (7 days)'] as $k => $lbl): ?>
                            <button type="button" data-filter="<?= $k ?>"
                                class="la-chip rounded-full border px-3 py-1 text-xs font-semibold transition <?= $k === 'all' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300' ?>"><?= $lbl ?></button>
                        <?php endforeach; ?>
                    </div>
                    <span class="ml-auto text-xs text-slate-400" data-count="users"></span>
                </div>

                <div class="overflow-auto max-h-[62vh]">
                    <table class="w-full text-sm min-w-[880px]">
                        <thead class="sticky top-0 z-10 bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                            <tr class="border-b border-slate-200">
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
                                <tr class="hover:bg-orange-50/40 transition" data-tags="<?= implode(' ', $tags) ?>" data-search="<?= e($search) ?>">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="w-9 h-9 shrink-0 rounded-full bg-slate-800 text-white text-xs font-bold flex items-center justify-center"><?= e(initials($u['name'])) ?></span>
                                            <div class="min-w-0">
                                                <p class="font-semibold text-slate-900 truncate flex items-center gap-1.5">
                                                    <?= e($u['name']) ?>
                                                    <?php if (!empty($u['is_official'])): ?><i class="fa-solid fa-circle-check text-sky-500 text-xs" title="Official Employee"></i><?php endif; ?>
                                                    <?php if ($u['is_new']): ?><span class="rounded bg-orange-100 text-orange-700 px-1.5 text-[10px] font-bold">NEW</span><?php endif; ?>
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
            <div class="grid grid-cols-2 xl:grid-cols-4 gap-4 mb-5">
                <?php
                statCard('fa-solid fa-user-shield', 'bg-slate-100 text-slate-700', 'Total Admins', $aStats['total'], count($roleList) . ' roles');
                statCard('fa-solid fa-signal', 'bg-green-50 text-green-600', 'Online Now', $aStats['online'], '<span class="inline-flex items-center gap-1"><span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse"></span>Live · updates every 15s</span>', 'st-online');
                statCard('fa-regular fa-calendar-check', 'bg-sky-50 text-sky-600', 'Active Today', $aStats['today'], date('l'), 'st-today');
                statCard('fa-solid fa-moon', 'bg-slate-100 text-slate-500', 'Inactive 30+ Days', $aStats['inactive'], 'Or never logged in', 'st-inactive');
                ?>
            </div>

            <div class="rounded-xl bg-white border border-slate-200 overflow-hidden">
                <div class="flex flex-wrap items-center gap-3 px-5 py-3.5 border-b border-slate-200">
                    <div class="relative flex-1 min-w-[220px] max-w-sm">
                        <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-xs"></i>
                        <input type="text" data-q="admins" placeholder="Search name, email or branch…"
                            class="w-full rounded-lg border border-slate-200 bg-slate-50 pl-8 pr-3 py-2 text-sm outline-none focus:border-[#F7941D] focus:bg-white focus:ring-2 focus:ring-orange-100">
                    </div>
                    <select data-rolefilter="admins" class="rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 outline-none focus:border-[#F7941D]">
                        <option value="">All roles</option>
                        <?php foreach (array_keys($roleList) as $r): ?>
                            <option value="<?= e(strtolower($r)) ?>"><?= e($prettyRole($r)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="flex flex-wrap gap-1.5" data-chips="admins">
                        <?php foreach (['all' => 'All', 'online' => 'Online', 'today' => 'Active Today', 'inactive' => 'Inactive'] as $k => $lbl): ?>
                            <button type="button" data-filter="<?= $k ?>"
                                class="la-chip rounded-full border px-3 py-1 text-xs font-semibold transition <?= $k === 'all' ? 'border-slate-900 bg-slate-900 text-white' : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300' ?>"><?= $lbl ?></button>
                        <?php endforeach; ?>
                    </div>
                    <span class="ml-auto text-xs text-slate-400" data-count="admins"></span>
                </div>

                <div class="overflow-auto max-h-[62vh]">
                    <table class="w-full text-sm min-w-[900px]">
                        <thead class="sticky top-0 z-10 bg-slate-50 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
                            <tr class="border-b border-slate-200">
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
                                <tr class="hover:bg-orange-50/40 transition" data-id="<?= (int) $a['id'] ?>" data-tags="<?= implode(' ', $tags) ?>" data-search="<?= e($search) ?>" data-role="<?= e(strtolower($a['role'] ?? '')) ?>">
                                    <td class="px-5 py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="relative w-9 h-9 shrink-0 rounded-full bg-[#F7941D] text-white text-xs font-bold flex items-center justify-center">
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
                                        <span class="rounded-md bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-700"><?= e($prettyRole($a['role'])) ?></span>
                                    </td>
                                    <td class="px-5 py-3 text-slate-600"><?= e($prettyRole($a['position'])) ?: '—' ?></td>
                                    <td class="px-5 py-3 text-slate-600"><?= e($a['branch'] ?: '—') ?></td>
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
    </main>

    <script>
        (function () {
            // ── Tabs ──
            const tabs = document.querySelectorAll('.la-tab');
            tabs.forEach(t => t.addEventListener('click', () => {
                tabs.forEach(x => {
                    const on = x === t;
                    x.classList.toggle('text-slate-900', on);
                    x.classList.toggle('text-slate-500', !on);
                    x.querySelector('.la-bar').classList.toggle('hidden', !on);
                    const badge = x.querySelector('.rounded-full');
                    badge.className = 'rounded-full px-2 text-[11px] font-bold ' + (on ? 'bg-slate-900 text-white' : 'bg-slate-200 text-slate-600');
                });
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
                    chips.forEach(x => {
                        const on = x === c;
                        x.classList.toggle('border-slate-900', on); x.classList.toggle('bg-slate-900', on); x.classList.toggle('text-white', on);
                        x.classList.toggle('border-slate-200', !on); x.classList.toggle('bg-white', !on); x.classList.toggle('text-slate-600', !on);
                    });
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