<?php
// itpushajax.php (route: itpushajax) — IT: on/off, listahan ng device, test, alisin
//   POST JSON { action: 'list' }
//             { action: 'toggle', type: 'user'|'admin', on: true|false }
//             { action: 'test',   type: 'user'|'admin', account_id }
//             { action: 'remove', id }

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';
include_once ROOT_PATH . '/network/push/push-helper.php';

header('Content-Type: application/json; charset=utf-8');

function ipOut(array $d, int $code = 200): void { http_response_code($code); echo json_encode($d); exit; }

if (($_SESSION['role'] ?? '') !== ROLE_IT) ipOut(['success' => false, 'message' => 'Access denied.'], 403);
if ($_SERVER['REQUEST_METHOD'] !== 'POST') ipOut(['success' => false, 'message' => 'Invalid request.'], 405);

$in     = json_decode(file_get_contents('php://input'), true) ?: [];
$action = (string) ($in['action'] ?? 'list');
$me     = (int) ($_SESSION['account_id'] ?? 0);

// Pangalan ng account (hindi alam ang eksaktong column ng nobleaccount, kaya hinahanap ang mayroon)
function ipNames(mysqli $conn, string $table, array $ids): array
{
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) return [];
    $out = [];
    $r = $conn->query("SELECT * FROM `$table` WHERE id IN (" . implode(',', $ids) . ")");
    if (!$r) return [];
    foreach ($r->fetch_all(MYSQLI_ASSOC) as $row) {
        $name = '';
        foreach (['name', 'fullname', 'full_name', 'username', 'email'] as $k) {
            if (!empty($row[$k])) { $name = (string) $row[$k]; break; }
        }
        $out[(int) $row['id']] = [
            'name' => $name !== '' ? $name : ('#' . $row['id']),
            'role' => (string) ($row['role'] ?? ''),
            'position' => (string) ($row['position'] ?? ''),
        ];
    }
    return $out;
}

// "Chrome · Android" mula sa user agent
function ipDevice(string $ua): array
{
    $os = preg_match('/iPhone|iPad|iPod/i', $ua) ? 'iPhone' : (preg_match('/Android/i', $ua) ? 'Android'
        : (preg_match('/Windows/i', $ua) ? 'Windows' : (preg_match('/Mac OS X|Macintosh/i', $ua) ? 'Mac' : (preg_match('/Linux/i', $ua) ? 'Linux' : 'Other'))));
    $br = preg_match('/Edg\//', $ua) ? 'Edge' : (preg_match('/OPR\/|Opera/', $ua) ? 'Opera'
        : (preg_match('/SamsungBrowser/', $ua) ? 'Samsung Internet' : (preg_match('/Firefox\//', $ua) ? 'Firefox'
        : (preg_match('/Chrome\//', $ua) ? 'Chrome' : (preg_match('/Safari\//', $ua) ? 'Safari' : 'Browser')))));
    return ['os' => $os, 'browser' => $br, 'mobile' => in_array($os, ['iPhone', 'Android'], true)];
}

function ipSettings(mysqli $conn): array
{
    $s = ['enabled_user' => '1', 'enabled_admin' => '1', 'ready' => true];
    try {
        $r = $conn->query("SELECT name, value FROM noblepush_setting");
        foreach ($r->fetch_all(MYSQLI_ASSOC) as $row) $s[$row['name']] = $row['value'];
    } catch (Throwable $e) { $s['ready'] = false; }   // wala pang table → patakbuhin ang push-setting.sql
    return ['user' => $s['enabled_user'] === '1', 'admin' => $s['enabled_admin'] === '1', 'ready' => $s['ready']];
}

try {
    if ($action === 'list') {
        $rows = $conn->query("SELECT id, account_type, account_id, user_agent, created_at, last_seen_at, last_push_at
                              FROM noblepush_subscription ORDER BY COALESCE(last_seen_at, created_at) DESC")->fetch_all(MYSQLI_ASSOC);
        $adminIds = []; $userIds = [];
        foreach ($rows as $r) { if ($r['account_type'] === 'admin') $adminIds[] = $r['account_id']; else $userIds[] = $r['account_id']; }
        $names = ['admin' => ipNames($conn, 'noblerole', $adminIds), 'user' => ipNames($conn, 'nobleaccount', $userIds)];

        $devices = [];
        foreach ($rows as $r) {
            $n = $names[$r['account_type']][(int) $r['account_id']] ?? ['name' => '#' . $r['account_id'] . ' (deleted)', 'role' => '', 'position' => ''];
            $devices[] = [
                'id' => (int) $r['id'], 'type' => $r['account_type'], 'account_id' => (int) $r['account_id'],
                'name' => $n['name'], 'role' => $n['role'], 'position' => $n['position'],
                'created_at' => $r['created_at'], 'last_seen_at' => $r['last_seen_at'], 'last_push_at' => $r['last_push_at'],
            ] + ipDevice((string) $r['user_agent']);
        }
        ipOut([
            'success'  => true,
            'settings' => ipSettings($conn),
            'server'   => nhPushEnabled(),   // may keys + vendor
            'devices'  => $devices,
        ]);
    }

    if ($action === 'toggle') {
        $type = (string) ($in['type'] ?? '');
        if (!in_array($type, ['user', 'admin'], true)) ipOut(['success' => false, 'message' => 'Invalid type.'], 422);
        $on   = !empty($in['on']) ? '1' : '0';
        $name = 'enabled_' . $type;
        $st = $conn->prepare("INSERT INTO noblepush_setting (name, value, updated_by) VALUES (?, ?, ?)
                              ON DUPLICATE KEY UPDATE value = VALUES(value), updated_by = VALUES(updated_by)");
        $st->bind_param('ssi', $name, $on, $me);
        $st->execute();
        $st->close();
        ipOut(['success' => true, 'message' => ($type === 'user' ? 'Request Portal' : 'Department') . ' notifications turned ' . ($on === '1' ? 'on.' : 'off.')]);
    }

    if ($action === 'test') {
        $type = (string) ($in['type'] ?? '');
        $aid  = (int) ($in['account_id'] ?? 0);
        if (!in_array($type, ['user', 'admin'], true) || $aid <= 0) ipOut(['success' => false, 'message' => 'Invalid account.'], 422);
        if (!nhPushEnabled()) ipOut(['success' => false, 'message' => 'Push is not set up on the server (keys or vendor missing).']);
        $GLOBALS['nhPushForce'] = true;   // gumagana ang test ng IT kahit naka-off
        $sent = nhPush($conn, $type, $aid, 'NobleHome test from IT', 'This is a test notification. You can ignore it.', '/', 'nh-it-test');
        ipOut($sent > 0
            ? ['success' => true, 'message' => "Test sent to $sent device(s)."]
            : ['success' => false, 'message' => 'Could not send. The device may be offline or the subscription expired (check the PHP error log for nhPush).']);
    }

    if ($action === 'remove') {
        $id = (int) ($in['id'] ?? 0);
        $st = $conn->prepare("DELETE FROM noblepush_subscription WHERE id = ?");
        $st->bind_param('i', $id);
        $st->execute();
        $ok = $st->affected_rows > 0;
        $st->close();
        ipOut(['success' => $ok, 'message' => $ok ? 'Device removed. It will stop getting notifications.' : 'Device not found.']);
    }

    ipOut(['success' => false, 'message' => 'Unknown action.'], 400);
} catch (Throwable $e) {
    error_log('itpushajax: ' . $e->getMessage());
    $m = $e->getMessage();
    ipOut(['success' => false, 'message' => stripos($m, 'noblepush_setting') !== false
        ? 'Run push-setting.sql first (table noblepush_setting is missing).'
        : 'Server error. Check the PHP error log (itpushajax).'], 500);
}