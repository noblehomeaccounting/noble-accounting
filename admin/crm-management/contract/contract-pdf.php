<?php
// contract-pdf.php — PDF ng contract (dompdf). Route: crmcontractpdf?inquiry_id=...
// Makikita ng Accounting Head, Superadmin, at ng naka-assign na Sales/Designer (hindi Draft).

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_ACCOUNTING, ROLE_SALES, ROLE_DESIGNER, ROLE_SUPERADMIN];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';
include_once ROOT_PATH . '/admin/crm-management/contract/contract-template.php';

$me = intval($_SESSION['account_id'] ?? 0);
$role = $_SESSION['role'] ?? '';
$inquiryId = intval($_GET['inquiry_id'] ?? 0);

$c = nhcLoad($conn, 'c.inquiry_id = ?', 'i', [$inquiryId]);

$allowed = false;
if ($c) {
    if ($role === ROLE_SUPERADMIN) {
        $allowed = true;
    } elseif ($role === ROLE_ACCOUNTING) {
        $st = $conn->prepare("SELECT id FROM noblerole WHERE id = ? AND role = ? AND position = ? LIMIT 1");
        $r = ROLE_ACCOUNTING;
        $p = POSITION_HEAD;
        $st->bind_param("iss", $me, $r, $p);
        $st->execute();
        $allowed = (bool) $st->get_result()->fetch_assoc();
        $st->close();
    } elseif ($c['status'] !== 'Draft') {
        $allowed = ($role === ROLE_SALES && (int) $c['sales_user_id'] === $me)
            || ($role === ROLE_DESIGNER && (int) $c['designer_user_id'] === $me);
    }
}

if (!$allowed) {
    http_response_code(404);
    exit('Contract not found.');
}

$img = nhcImages($c);
$fileName = 'Contract_' . preg_replace('/[^A-Za-z0-9_-]/', '', (string) $c['control_no']) . ($c['status'] === 'Completed' ? '' : '_' . str_replace(' ', '', $c['status'])) . '.pdf';

if (class_exists('\Dompdf\Dompdf')) {
    $opt = new \Dompdf\Options();
    $opt->set('isRemoteEnabled', false);
    $opt->set('isHtml5ParserEnabled', true);
    $opt->set('defaultFont', 'DejaVu Serif');
    $pdf = new \Dompdf\Dompdf($opt);
    $pdf->loadHtml(nhcPdfHtml($c, $img), 'UTF-8');
    $pdf->setPaper('A4', 'portrait');
    $pdf->render();
    $pdf->stream($fileName, ['Attachment' => isset($_GET['download'])]);
    exit;
}

// Walang dompdf → printable na page (Ctrl+P → Save as PDF)
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= nhcEsc($fileName) ?></title>
    <style>
        body { margin: 0; background: #e5e7eb; }
        .sheet { max-width: 800px; margin: 16px auto; background: #fff; padding: 40px 48px; box-shadow: 0 1px 4px rgba(0,0,0,.15); }
        @media print { body { background: #fff; } .sheet { margin: 0; box-shadow: none; padding: 0; } .noprint { display: none; } }
        @media (max-width: 640px) { .sheet { padding: 20px 16px; margin: 0; } }
        <?= nhcCss() ?>
    </style>
</head>
<body>
    <p class="noprint" style="text-align:center;font-family:Arial,sans-serif;font-size:13px">
        <button type="button" onclick="window.print()" style="padding:8px 16px;background:#b45309;color:#fff;border:0;border-radius:8px;font-weight:600;cursor:pointer">Print / Save as PDF</button>
    </p>
    <div class="sheet"><?= nhcRenderContract($c, $img) ?></div>
</body>
</html>