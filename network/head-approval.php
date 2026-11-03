<?php
// network/head-approval.php — iisang tuntunin ng approval: ang HEAD ng department ang nag-a-approve,
// at ang Superadmin ay review / monitor na lang (nakikita lahat, walang Approve / Return).
//
//   $nhApprover = nhApproverViewer($conn, ROLE_PO);   // 'head' | 'monitor' | ''
//   nhApproverGuard($nhApprover, $action, ['approve', 'reject'], 'Purchasing Head');  // sa ajax

if (!function_exists('nhApproverViewer')) {

    /** 'head' = Head ng department (puwedeng mag-approve) · 'monitor' = Superadmin (tingin lang) · '' = walang access */
    function nhApproverViewer(mysqli $conn, string $deptRole): string
    {
        $role = (string) ($_SESSION['role'] ?? '');
        $uid = (int) ($_SESSION['account_id'] ?? 0);
        if ($role === ROLE_SUPERADMIN) {
            return 'monitor';
        }
        if ($role !== $deptRole || $uid <= 0) {
            return '';
        }
        $st = $conn->prepare("SELECT id FROM noblerole WHERE id = ? AND role = ? AND position = ? LIMIT 1");
        $pos = POSITION_HEAD;
        $st->bind_param('iss', $uid, $deptRole, $pos);
        $st->execute();
        $isHead = (bool) $st->get_result()->fetch_assoc();
        $st->close();
        return $isHead ? 'head' : '';
    }

    /** Para sa page: hindi Head / hindi Superadmin → 403 */
    function nhApproverPageGuard(string $viewer, string $headLabel): void
    {
        if ($viewer === '') {
            http_response_code(403);
            exit('Access denied. This page is for the ' . $headLabel . ' (approval) and the Superadmin (view only).');
        }
    }

    /** Para sa ajax: walang access → error; Superadmin na susubok mag-approve → error */
    function nhApproverGuard(string $viewer, string $action, array $writeActions, string $headLabel): void
    {
        if ($viewer === '') {
            if (!headers_sent()) header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Only the ' . $headLabel . ' can open this.']);
            exit;
        }
        if ($viewer !== 'head' && in_array($action, $writeActions, true)) {
            if (!headers_sent()) header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'This is now approved by the ' . $headLabel . '. Superadmin can only review it here.']);
            exit;
        }
    }

    /** Lahat ng Head ng isang department (para sa notification) */
    function nhHeadIds(mysqli $conn, string $deptRole): array
    {
        $st = $conn->prepare("SELECT id FROM noblerole WHERE role = ? AND position = ?");
        $pos = POSITION_HEAD;
        $st->bind_param('ss', $deptRole, $pos);
        $st->execute();
        $ids = array_map(fn($r) => (int) $r['id'], $st->get_result()->fetch_all(MYSQLI_ASSOC));
        $st->close();
        return $ids;
    }
}