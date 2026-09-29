<?php
ob_start();

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) { http_response_code(400); exit; }

$result = $conn->query("SELECT b.*, 
    n.name as sender_name,
    n.email as sender_email,
    r.name as approver_name,
    recv.name as receiver_name
    FROM noblebudgetrequest b
    LEFT JOIN nobleaccount n ON b.user_id = n.id
    LEFT JOIN noblerole r ON b.approved_by = r.id
    LEFT JOIN noblerole recv ON b.received_by = recv.id
    WHERE b.id = $id 
    AND b.status = 'approved' 
    AND b.received_by IS NOT NULL
    LIMIT 1");

$row = $result->fetch_assoc();
if (!$row) { 
    ob_end_clean();
    http_response_code(403); 
    exit('Not ready for download.'); 
}

$items    = json_decode($row['items'], true) ?? [];
$total    = array_sum(array_column($items, 'amount'));
$approved = $row['approved_at'] ? date('F d, Y g:i A', strtotime($row['approved_at'])) : '';
$received = $row['received_at'] ? date('F d, Y g:i A', strtotime($row['received_at'])) : '';

$garbage = ob_get_clean();
if (!empty(trim($garbage))) {
    header('Content-Type: text/plain');
    echo "OUTPUT BEFORE PDF:\n" . $garbage;
    exit;
}

require_once ROOT_PATH . '/vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);

$logoPath  = ROOT_PATH . '/icon/logo.png';
$stampPath = ROOT_PATH . '/icon/stamp.png';

$logoBase64  = base64_encode(file_get_contents($logoPath));
$stampBase64 = base64_encode(file_get_contents($stampPath));

// Build item rows — palaging 5 rows
$itemRows = '';
$filledCount = 0;
foreach ($items as $i => $item) {
    if (empty($item['description'])) continue;
    $filledCount++;
    $amount    = number_format(floatval($item['amount'] ?? 0), 2);
    $unitPrice = number_format(floatval($item['unit_price'] ?? 0), 2);
    $itemRows .= "
    <tr>
        <td style='text-align:center;border:1px solid #ccc;padding:5px;height:22px;'>{$filledCount}</td>
        <td style='border:1px solid #ccc;padding:5px;'>" . htmlspecialchars($item['description'] ?? '') . "</td>
        <td style='border:1px solid #ccc;padding:5px;'>" . htmlspecialchars($item['purpose'] ?? '') . "</td>
        <td style='text-align:center;border:1px solid #ccc;padding:5px;'>" . ($item['quantity'] ?? 0) . "</td>
        <td style='text-align:right;border:1px solid #ccc;padding:5px;font-family:monospace;'>" . number_format(floatval($item['unit_price'] ?? 0), 2) . "</td>
        <td style='text-align:right;border:1px solid #ccc;padding:5px;font-family:monospace;'>PhP {$amount}</td>
        <td style='border:1px solid #ccc;padding:5px;'>" . htmlspecialchars($item['notes'] ?? '') . "</td>
    </tr>";
}

// Dagdag ng empty rows hanggang 5
for ($e = $filledCount; $e < 5; $e++) {
    $rowNum = $e + 1;
    $itemRows .= "
    <tr>
        <td style='text-align:center;border:1px solid #ccc;padding:5px;height:22px;color:#ccc;'>{$rowNum}</td>
        <td style='border:1px solid #ccc;padding:5px;height:22px;'></td>
        <td style='border:1px solid #ccc;padding:5px;'></td>
        <td style='text-align:center;border:1px solid #ccc;padding:5px;'>0</td>
        <td style='text-align:right;border:1px solid #ccc;padding:5px;font-family:monospace;'>0.00</td>
        <td style='text-align:right;border:1px solid #ccc;padding:5px;font-family:monospace;'>PhP 0.00</td>
        <td style='border:1px solid #ccc;padding:5px;'></td>
    </tr>";
}

$html = "
<!DOCTYPE html>
<html>
<head>
<meta charset='UTF-8'>
<style>
    * { box-sizing: border-box; }
    body { font-family: Arial, sans-serif; font-size: 11px; margin: 0; padding: 15px; }
    table { width: 100%; border-collapse: collapse; }
    th { background: #f97316; color: white; padding: 6px 8px; font-size: 10px; text-transform: uppercase; }
    .label { font-size: 9px; font-weight: bold; text-transform: uppercase; letter-spacing: 1px; color: #555; }
    .control-box { background: #f97316; color: white; font-weight: bold; text-align: center; padding: 4px 8px; font-size: 9px; text-transform: uppercase; }
    .sig-line { border-top: 1px solid #999; margin-top: 30px; }
</style>
</head>
<body>

<div style='border: 1px solid #111;'>

    <!-- Header -->
    <table style='border-bottom: 1px solid #111;'>
        <tr>
            <td style='width:60%; border-right: 1px solid #111; padding: 10px;'>
                <table>
                    <tr>
                        <td style='width:60px;'>
                            <img src='data:image/png;base64,{$logoBase64}' style='width:50px;height:50px;object-fit:contain;'>
                        </td>
                        <td style='border-left:1px solid #aaa; padding-left:10px;'>
                            <strong style='font-size:11px;text-transform:uppercase;'>Noblehome Construction Corporation</strong><br>
                            <span style='font-size:9px;color:#666;'>
                                1181 MC Premiere Bldg., EDSA Balintawak Quezon City<br>
                                noblehomeconsl.ph@gmail.com | Tel. No. 02-88221295
                            </span>
                        </td>
                    </tr>
                </table>
            </td>
            <td style='width:40%; padding:0; vertical-align:top;'>
                <div style='font-weight:bold;font-size:12px;text-transform:uppercase;letter-spacing:1px;padding:8px;border-bottom:1px solid #111;'>
                    Budget Request Form
                </div>
                <table style='width:100%;'>
                    <tr>
                        <td style='width:50%; border-right:1px solid #111; padding:0; vertical-align:top;'>
                            <div class='control-box'>Control No.</div>
                            <div style='text-align:center;font-family:monospace;font-size:10px;padding:6px;background:#f9fafb;'>
                                {$row['control_no']}
                            </div>
                        </td>
                        <td style='width:50%; padding:0; vertical-align:top;'>
                            <div class='control-box'>Date</div>
                            <div style='text-align:center;font-family:monospace;font-size:10px;padding:6px;background:#f9fafb;'>
                                {$row['date_requested']}
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <!-- Requestor + Purpose -->
    <table style='border-bottom:1px solid #111;'>
        <tr>
            <td style='width:50%;border-right:1px solid #111;padding:8px;'>
                <span class='label'>Requestor Name:</span>
                <span style='font-size:12px;margin-left:5px;'>{$row['requestor_name']}</span>
            </td>
            <td style='width:50%;padding:8px;'>
                <span class='label'>Purpose of Request:</span>
                <span style='font-size:12px;margin-left:5px;'>{$row['purpose']}</span>
            </td>
        </tr>
    </table>

    <!-- Items + Stamp wrapper -->
    <div style='position:relative;'>

        <table style='border-collapse:collapse;width:100%;border-bottom:1px solid #111;'>
            <thead>
                <tr>
                    <th style='width:5%;border:1px solid #ea6c00;'>No.</th>
                    <th style='border:1px solid #ea6c00;text-align:left;'>Items / Description</th>
                    <th style='border:1px solid #ea6c00;text-align:left;'>Purpose</th>
                    <th style='width:8%;border:1px solid #ea6c00;'>Qty</th>
                    <th style='width:13%;border:1px solid #ea6c00;'>Unit Price</th>
                    <th style='width:13%;border:1px solid #ea6c00;'>Amount</th>
                    <th style='border:1px solid #ea6c00;text-align:left;'>Notes</th>
                </tr>
            </thead>
            <tbody>
                {$itemRows}
            </tbody>
            <tfoot>
                <tr>
                    <td colspan='5' style='text-align:right;font-weight:bold;padding:6px;border:1px solid #ccc;font-size:10px;text-transform:uppercase;'>Total:</td>
                    <td style='text-align:right;font-weight:bold;font-family:monospace;border:1px solid #ccc;padding:6px;'>PhP " . number_format($total, 2) . "</td>
                    <td style='border:1px solid #ccc;'></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <!-- Signatures -->
    <table style='width:100%;'>
        <tr>
            <td style='width:50%;border-right:1px solid #111;padding:15px 20px;vertical-align:bottom;'>
                <span class='label'>Approved By:</span>
                <div style='margin-top:20px;text-align:center;'>
                    <strong style='font-size:12px;'>{$row['approver_name']}</strong><br>
                    <span style='font-size:9px;color:#888;'>{$approved}</span>
                </div>
                <div class='sig-line'></div>
                <div style='text-align:center;font-size:9px;text-transform:uppercase;color:#888;margin-top:3px;'>Head</div>
            </td>
            <td style='width:50%;padding:15px 20px;vertical-align:bottom;'>
                <span class='label'>Received By:</span>
                <div style='margin-top:20px;text-align:center;'>
                    <strong style='font-size:12px;'>{$row['receiver_name']}</strong><br>
                    <span style='font-size:9px;color:#888;'>{$received}</span>
                </div>
                <div class='sig-line'></div>
                <div style='text-align:center;font-size:9px;color:#888;margin-top:3px;'>&nbsp;</div>
            </td>
        </tr>
    </table>

</div>

</body>
</html>";

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream('BudgetRequest-' . $row['control_no'] . '.pdf', ['Attachment' => true]);