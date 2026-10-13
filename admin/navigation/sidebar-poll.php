<?php
// sidebar-poll.php → i-register sa router bilang /sidebarpoll
// Pinagsama: heartbeat + badge counts + notification signature

require_once ROOT_PATH . '/admin/authentication/index-roles.php';
include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

// Huwag hayaang lumabas ang PHP warnings/HTML sa JSON response
ini_set('display_errors', '0');

try {
    $user_id  = intval($_SESSION['account_id'] ?? 0);
    $role     = $_SESSION['role'] ?? '';
    $position = $_SESSION['position'] ?? POSITION_STAFF;

    // Bitawan ang session lock bago ang mga query
    session_write_close();

    if (!$user_id) {
        http_response_code(401);
        echo json_encode(['error' => 'auth']);
        exit;
    }

    require_once ROOT_PATH . '/admin/navigation/sidebar-counts.php';

    // 1. Heartbeat (online presence)
    $conn->query("UPDATE noblerole SET last_active = NOW() WHERE id = $user_id");

    // 2. Badge counts (cleanup ng announcements: hawak na ng cron)
    $counts = getSidebarCounts($conn, $role, $position, $user_id);

    // 3. Notifications: bilang at pinakabagong id lang
    $r = $conn->query("
        SELECT COUNT(*) AS unread, COALESCE(MAX(id), 0) AS latest_id
        FROM noblenotification
        WHERE user_id = $user_id AND is_read = 0
    ");
    $n = $r->fetch_assoc();

    echo json_encode([
        'counts' => $counts,
        'notif'  => [
            'unread'    => (int)$n['unread'],
            'latest_id' => (int)$n['latest_id'],
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
exit;