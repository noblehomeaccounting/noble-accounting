<?php
// listaccountajax.php  —  live online status ng mga admin (para sa listaccount.php)

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_HR];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

// Sa MySQL kinukwenta (naka +08:00 sa connect.php) para hindi maapektuhan
// ng timezone ng PHP
$rows = [];
$res = $conn->query("SELECT id,
                            TIMESTAMPDIFF(SECOND, last_active, NOW())        AS idle_sec,
                            (DATE(last_active) = CURDATE())                  AS is_today,
                            DATE_FORMAT(last_active, '%b %e, %Y · %l:%i %p') AS last_label
                     FROM noblerole");
while ($r = $res->fetch_assoc()) {
    $rows[] = [
        'id'         => (int) $r['id'],
        'idle_sec'   => $r['idle_sec'] === null ? null : max(0, (int) $r['idle_sec']),
        'is_today'   => (int) $r['is_today'] === 1,
        'last_label' => $r['last_label'],
    ];
}

echo json_encode(['ok' => true, 'admins' => $rows]);