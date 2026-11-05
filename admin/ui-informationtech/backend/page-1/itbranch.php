<?php
// itbranch.php (route: updateitbranch) — palitan ang branch ng admin account (noblerole)
//   POST JSON { id, branch }   ·   branch = '' para tanggalin

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

header('Content-Type: application/json; charset=utf-8');

function brOut(array $d, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($d);
    exit;
}

// IT lang ang puwedeng magpalit ng branch (kapareho ng createaccount-main.php)
if (($_SESSION['role'] ?? '') !== ROLE_IT) {
    brOut(['success' => false, 'message' => 'Access denied.'], 403);
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    brOut(['success' => false, 'message' => 'Invalid request.'], 405);
}

$body   = json_decode(file_get_contents('php://input'), true) ?: [];
$id     = intval($body['id'] ?? 0);
$branch = trim((string) ($body['branch'] ?? ''));

if ($id <= 0) {
    brOut(['success' => false, 'message' => 'Invalid account id.'], 422);
}

try {
    // May ganitong account ba?
    $st = $conn->prepare("SELECT name, branch FROM noblerole WHERE id = ? LIMIT 1");
    $st->bind_param('i', $id);
    $st->execute();
    $acc = $st->get_result()->fetch_assoc();
    $st->close();
    if (!$acc) {
        brOut(['success' => false, 'message' => 'Account not found.'], 404);
    }

    // Dapat nasa listahan ng branch (noblebranch) — o blangko para walang branch
    if ($branch !== '') {
        $st = $conn->prepare("SELECT name FROM noblebranch WHERE name = ? LIMIT 1");
        $st->bind_param('s', $branch);
        $st->execute();
        $found = $st->get_result()->fetch_assoc();
        $st->close();
        if (!$found) {
            brOut(['success' => false, 'message' => 'That branch does not exist. Reload the page.'], 422);
        }
        $branch = $found['name'];   // eksaktong spelling galing sa noblebranch
    }

    // Walang pagbabago — hindi na kailangang mag-update
    if ((string) $acc['branch'] === $branch) {
        brOut(['success' => true, 'id' => $id, 'branch' => $branch, 'changed' => false]);
    }

    $st = $conn->prepare("UPDATE noblerole SET branch = ? WHERE id = ?");
    $st->bind_param('si', $branch, $id);
    $st->execute();
    $st->close();

    brOut(['success' => true, 'id' => $id, 'branch' => $branch, 'changed' => true]);
} catch (Throwable $e) {
    error_log('itbranch: ' . $e->getMessage());
    brOut(['success' => false, 'message' => 'Could not update the branch. Try again.'], 500);
}