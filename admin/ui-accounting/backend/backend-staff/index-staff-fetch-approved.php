<?php
// index-staff-fetch-approved.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/network/cache-helper.php';

session_write_close(); // bitawan ang session lock

// gzip (JSON lumiliit nang 80-90%)
if (!ob_start('ob_gzhandler')) {
    ob_start();
}

header('Content-Type: application/json');

// Ipinapadala ang JSON, o 304 (walang laman) kapag walang nagbago mula sa huling kuha ng browser.
// May function_exists guard para hindi mag-conflict kung na-define na sa ibang file.
if (!function_exists('sendJson')) {
    function sendJson(string $json): void
    {
        $etag = '"' . md5($json) . '"';
        header('ETag: ' . $etag);
        header('Cache-Control: private, no-cache'); // laging magtanong sa server, pero pwedeng 304
        if (str_contains($_SERVER['HTTP_IF_NONE_MATCH'] ?? '', $etag)) {
            http_response_code(304);
            return;
        }
        echo $json;
    }
}

// ?_= (force) -> lampasan ang server cache (gamitin pagkatapos ng receive/submit)
$force = isset($_GET['_']);

$cached = $force ? false : getCache('staff_approved_requests', 60);
if ($cached !== false) {
    sendJson($cached);
    exit;
}

// Approved requests
// TIP: kung "approved pero hindi pa nareceive" lang ang kailangan ng table, idagdag:
//      AND b.received_by IS NULL
//      (ihiwalay ang history/received sa ibang endpoint na may pagination)
$result = $conn->query("SELECT b.*, 
    n.name as sender_name, 
    n.email as sender_email,
    r.name as approver_name,
    COALESCE(
        b.approver_signature_path,
        CASE WHEN b.status = 'approved' 
             THEN (SELECT path FROM noblesignature WHERE user_id = b.approved_by AND is_active = 1 LIMIT 1)
             ELSE NULL 
        END
    ) as approver_signature,
    b.receiver_signature_path as receiver_signature,
    recv.name as receiver_name
    FROM noblebudgetrequest b
    LEFT JOIN nobleaccount n ON b.user_id = n.id
    LEFT JOIN noblerole r ON b.approved_by = r.id
    LEFT JOIN noblerole recv ON b.received_by = recv.id
    WHERE b.status = 'approved' 
    ORDER BY b.approved_at DESC");

$data = [];
while ($row = $result->fetch_assoc()) {
    $row['items'] = json_decode($row['items'], true);
    $row['attachments'] = json_decode($row['attachments'] ?? '[]', true);
    $data[] = $row;
}

$json = json_encode($data);


setCache('staff_approved_requests', $json);
sendJson($json);