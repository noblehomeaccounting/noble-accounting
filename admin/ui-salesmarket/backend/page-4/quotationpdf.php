<?php
// quotationpdf.php  -  Export a quotation as a PDF (Itemized Cost Breakdown)
// Route: quotationpdf?id=QUOTATION_ID

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SALES];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

// dompdf - adjust the path below if your vendor folder lives elsewhere.
require_once ROOT_PATH . '/vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Company logo shown at the top of the PDF (PNG or JPG). Change the path if your logo is elsewhere.
const QP_LOGO_PATH = '/assets/img/noblehome-logo.png';   // under ROOT_PATH

// -----------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------

function qpEsc($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// Peso sign on the left, amount on the right (like the Excel accounting format).
function qpMoney($n): string
{
    return '<div class="m"><span class="p">&#8369;</span>' . number_format((float)$n, 2) . '</div>';
}

// The sheet shows measurements with 2 decimals; the full value is only used in the math.
function qpMeas($n): string
{
    return ($n === null || $n === '') ? '' : number_format((float)$n, 2);
}

function qpQty($n): string
{
    return rtrim(rtrim(number_format((float)$n, 2, '.', ''), '0'), '.');
}

// Company logo as a data URI (dompdf runs with remote files disabled). Null if the file is missing.
function qpLogoDataUri(): ?string
{
    $path = ROOT_PATH . QP_LOGO_PATH;
    if (!is_file($path)) return null;
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'image/png';
    return 'data:' . $mime . ';base64,' . base64_encode(file_get_contents($path));
}

// Section photo as a data URI (dompdf runs with remote files disabled).
// Downscaled and flattened to JPEG so the PDF stays small.
function qpImageDataUri(?string $name): ?string
{
    if (!$name) return null;
    $path = ROOT_PATH . '/uploads/quotations/' . basename($name);
    if (!is_file($path)) return null;

    $raw = file_get_contents($path);

    if (function_exists('imagecreatefromstring')) {
        $img = @imagecreatefromstring($raw);
        if ($img) {
            $w = imagesx($img);
            $h = imagesy($img);
            $scale = min(1, 700 / max($w, $h));
            $nw = max(1, (int)round($w * $scale));
            $nh = max(1, (int)round($h * $scale));

            $canvas = imagecreatetruecolor($nw, $nh);
            imagefilledrectangle($canvas, 0, 0, $nw, $nh, imagecolorallocate($canvas, 255, 255, 255));
            imagecopyresampled($canvas, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
            imagedestroy($img);

            ob_start();
            imagejpeg($canvas, null, 85);
            $jpg = ob_get_clean();
            imagedestroy($canvas);
            return 'data:image/jpeg;base64,' . base64_encode($jpg);
        }
    }

    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($raw) ?: 'image/png';
    return 'data:' . $mime . ';base64,' . base64_encode($raw);
}

function qpFetch(mysqli $conn, int $qid): ?array
{
    $stmt = $conn->prepare('SELECT * FROM noblecrm_quotations WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $qid);
    $stmt->execute();
    $quote = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$quote) return null;

    $stmt = $conn->prepare('SELECT * FROM noblecrm_quotation_sections WHERE quotation_id = ? ORDER BY sort_order, id');
    $stmt->bind_param('i', $qid);
    $stmt->execute();
    $quote['sections'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stmt = $conn->prepare('SELECT i.* FROM noblecrm_quotation_items i
                              JOIN noblecrm_quotation_sections s ON s.id = i.section_id
                             WHERE s.quotation_id = ? ORDER BY i.sort_order, i.id');
    $stmt->bind_param('i', $qid);
    $stmt->execute();
    $quote['items'] = [];
    foreach ($stmt->get_result()->fetch_all(MYSQLI_ASSOC) as $it) {
        $quote['items'][$it['section_id']][] = $it;
    }
    $stmt->close();

    return $quote;
}

// Top of the sheet: logo, company name, office, then Project / Client / Location / Date / Scope.
// Column widths match the item table below (17% | 52% | 10% | 21%).
function qpHeaderHtml(array $quote): string
{
    $logo     = qpLogoDataUri();
    $logoHtml = $logo ? '<img src="' . $logo . '" width="80">' : '&nbsp;';
    $date     = !empty($quote['quote_date']) ? date('j-M-y', strtotime($quote['quote_date'])) : '';

    $project  = qpEsc(mb_strtoupper((string)($quote['project_name'] ?? '')));
    $client   = qpEsc((string)($quote['client_name'] ?? ''));
    $location = qpEsc(mb_strtoupper((string)($quote['project_location'] ?? '')));
    $scope    = qpEsc(mb_strtoupper((string)($quote['project_scope'] ?? '')));

    return '
    <table class="top">
        <colgroup><col width="17%"><col width="66%"><col width="17%"></colgroup>
        <tr>
            <td rowspan="3" class="logo" style="width:17%">' . $logoHtml . '</td>
            <td class="t1" style="width:66%">QUOTATION</td>
            <td rowspan="3" style="width:17%">&nbsp;</td>
        </tr>
        <tr><td class="t2">NOBLEHOME CONSTRUCTION CORP.</td></tr>
        <tr><td class="t3">Office: 2nd Floor, MC Premier , Quezon City, Metro Manila</td></tr>
    </table>

    <table class="hdr">
        <colgroup><col width="17%"><col width="52%"><col width="10%"><col width="21%"></colgroup>
        <tr><td colspan="4" class="c b" style="font-size:8px;padding:4px;">QUOTATION</td></tr>
        <tr>
            <td class="lab">PROJECT :</td><td class="val">' . $project . '</td>
            <td class="lab">CLIENT :</td><td class="val">' . $client . '</td>
        </tr>
        <tr>
            <td class="lab">LOCATION :</td><td class="val">' . $location . '</td>
            <td class="lab">DATE :</td><td class="val">' . qpEsc($date) . '</td>
        </tr>
        <tr>
            <td class="lab">SCOPE :</td><td class="val" colspan="3">' . $scope . '</td>
        </tr>
    </table>';
}

// Footer below the GRAND TOTAL: notes, bank accounts, thank-you line and signature block.
// Kept together on one page (page-break-inside: avoid).
function qpFooterHtml(): string
{
    return '
    <div class="foot-wrap">
    <table class="foot" style="margin-top:-1px;">
        <colgroup><col width="5%"><col width="9%"><col width="56%"><col width="30%"></colgroup>
        <tr><td class="c">Note:</td><td colspan="3"></td></tr>
        <tr><td class="c">1</td><td colspan="3">Payment Terms, 50% down payment, 40% before Installation and 10% after Installation done.</td></tr>
        <tr><td class="c">2</td><td colspan="3">Payable to <b>NOBLEHOME CONSTRUCTION CORP.</b></td></tr>
        <tr><td></td><td colspan="2" class="b">Bank:</td><td class="r b">Account No.:</td></tr>
        <tr><td></td><td class="b">BDO</td><td class="c b i">NOBLEHOME CONSTRUCTION CORP.</td><td class="r b i red">013238001657</td></tr>
        <tr><td></td><td class="b">AUB</td><td class="c b i">NOBLEHOME CONSTRUCTION CORP.</td><td class="r b i red">538010001790</td></tr>
        <tr><td class="c">3</td><td colspan="3">Quote according to the drawing, any changes made by customers will be charged accordingly.</td></tr>
        <tr><td class="c">4</td><td colspan="3">VAT Inclusive.</td></tr>
        <tr><td class="c">5</td><td colspan="3">Free delivery for NCR Area only, beyond NCR have additional fee.</td></tr>
        <tr><td class="c">6</td><td colspan="3">This is only for quotation, for more details refer to the contract.</td></tr>
        <tr><td class="c">7</td><td colspan="3">For work permit, we provide the service for getting permit from building admin, the related costing will customer shoulder.</td></tr>
        <tr><td colspan="4" class="c i" style="padding-top:5px;">Thank you for giving us a chance to be at service for you. Keep safe.</td></tr>
    </table>

    <table class="sign" style="margin-top:14px;">
        <colgroup><col width="22%"><col width="22%"><col width="24%"><col width="32%"></colgroup>
        <tr>
            <td class="c">Prepared by:</td>
            <td class="c">Approved by:</td>
            <td class="c">Approved by:</td>
            <td class="c">Conforme:</td>
        </tr>
        <tr class="nm">
            <td>&nbsp;</td>
            <td>&nbsp;</td>
            <td class="c b u">Ken Yang</td>
            <td><div class="line">&nbsp;</div></td>
        </tr>
        <tr>
            <td class="c i">Designer / Sales Associate</td>
            <td class="c i">Project Operation Manager</td>
            <td class="c i">President</td>
            <td>&nbsp;</td>
        </tr>
    </table>
    </div>';
}

// One block of rows under the main items (DRAWER or ACCESSORIES).
// Keeps each labor group together so its labor cell can be merged.
function qpBlockHtml(string $num, string $label, array $list): string
{
    $ordered = [];
    $seen    = [];
    foreach ($list as $it) {
        $g = $it['labor_type'] === 'group' ? (string)$it['group_name'] : '';
        if ($g === '') { $ordered[] = $it; continue; }
        if (isset($seen[$g])) continue;
        $seen[$g] = true;
        foreach ($list as $m) {
            if ($m['labor_type'] === 'group' && (string)$m['group_name'] === $g) $ordered[] = $m;
        }
    }

    $groups = [];
    foreach ($ordered as $it) {
        if ($it['labor_type'] !== 'group') continue;
        $g = (string)$it['group_name'];
        $groups[$g]['size']  = ($groups[$g]['size'] ?? 0) + 1;
        $groups[$g]['mat']   = ($groups[$g]['mat'] ?? 0) + (float)$it['material_total'];
        $groups[$g]['labor'] = ($groups[$g]['labor'] ?? 0) + (float)$it['labor_total'];
    }

    $out = '<tr><td class="c b">' . $num . '</td><td colspan="9" class="b">' . qpEsc($label) . '</td></tr>';

    $k = 0;
    $printed = [];
    foreach ($ordered as $it) {
        $k++;
        $g       = $it['labor_type'] === 'group' ? (string)$it['group_name'] : '';
        $isGroup = $g !== '';
        $first   = $isGroup && !isset($printed[$g]);
        $printed[$g] = true;

        $out .= '<tr>'
              . '<td class="c">' . $num . '.' . $k . '</td>'
              . '<td colspan="2" class="c">' . qpEsc($it['description']) . '</td>'
              . '<td class="c">' . ($it['uses_measurement'] ? qpMeas($it['measurement']) : '') . '</td>'
              . '<td class="c">' . qpEsc($it['unit']) . '</td>'
              . '<td class="c">' . qpQty($it['qty']) . '</td>'
              . '<td>' . qpMoney($it['material_total']) . '</td>';

        if (!$isGroup) {
            $lab   = (float)$it['labor_total'];
            $total = (float)$it['material_total'] + $lab;
            $out .= '<td>' . ($lab > 0 ? qpMoney($lab) : '&nbsp;') . '</td>'
                  . '<td>' . qpMoney($total) . '</td>'
                  . '<td>' . qpMoney($total) . '</td>';
        } elseif ($first) {
            $rs    = (int)$groups[$g]['size'];
            $total = $groups[$g]['mat'] + $groups[$g]['labor'];
            $out .= '<td rowspan="' . $rs . '">' . qpMoney($groups[$g]['labor']) . '</td>'
                  . '<td rowspan="' . $rs . '">' . qpMoney($total) . '</td>'
                  . '<td rowspan="' . $rs . '">' . qpMoney($total) . '</td>';
        }
        $out .= '</tr>';
    }
    return $out;
}

function qpRenderHtml(array $quote): string
{
    $body  = '';
    $grand = 0.0;

    foreach ($quote['sections'] as $si => $sec) {
        $n     = $si + 1;
        $its   = $quote['items'][$sec['id']] ?? [];
        $mains = array_values(array_filter($its, fn($i) => $i['item_type'] === 'main'));
        $accs  = array_values(array_filter($its, fn($i) => $i['item_type'] === 'accessory'));

        // Flat per-piece main items (drawer, sink: unit 'pc.') get their own DRAWER block below the
        // main items, same layout as accessory rows. Ang lm./sqm. main items ang naiiwan sa taas.
        $pcs   = array_values(array_filter($mains, fn($i) => $i['unit'] === 'pc.'));
        $mains = array_values(array_filter($mains, fn($i) => $i['unit'] !== 'pc.'));

        foreach ($its as $it) {
            $grand += (float)$it['material_total'] + (float)$it['labor_total'];
        }

        // Section title bar
        $body .= '<tr class="sec"><td colspan="10">' . qpEsc(mb_strtoupper($sec['title'])) . '</td></tr>';

        // Main items: item number and photo are merged down the main rows
        $img      = qpImageDataUri($sec['image_path']);
        $photo    = $img ? '<img src="' . $img . '" width="68">' : '&nbsp;';
        $mainRows = $mains ?: [null];
        $span     = count($mainRows);

        foreach ($mainRows as $mi => $it) {
            $body .= '<tr>';
            if ($mi === 0) {
                $body .= '<td rowspan="' . $span . '" class="c b">' . $n . '.0</td>'
                       . '<td rowspan="' . $span . '" class="c">' . $photo . '</td>';
            }
            if ($it === null) {
                $body .= '<td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>';
            } else {
                $total = (float)$it['material_total'] + (float)$it['labor_total'];
                $body .= '<td class="c">' . qpEsc($it['description']) . '</td>'
                       . '<td class="c">' . qpMeas($it['measurement']) . '</td>'
                       . '<td class="c">' . qpEsc($it['unit']) . '</td>'
                       . '<td class="c">' . qpQty($it['qty']) . '</td>'
                       . '<td>' . qpMoney($it['material_total']) . '</td>'
                       . '<td>' . qpMoney($it['labor_total']) . '</td>'
                       . '<td>' . qpMoney($total) . '</td>'
                       . '<td>' . qpMoney($total) . '</td>';
            }
            $body .= '</tr>';
        }

        // Blocks under the main items: DRAWER (pc. items) first, then ACCESSORIES.
        // n.1 = first block, n.2 = second block (if both exist).
        $blocks = [];
        if ($pcs) {
            $allDrawer = true;
            foreach ($pcs as $p) {
                if (stripos(ltrim((string)$p['description']), 'drawer') !== 0) { $allDrawer = false; break; }
            }
            $blocks[] = [$allDrawer ? 'DRAWER' : 'DRAWER & OTHERS', $pcs];
        }
        if ($accs) $blocks[] = ['ACCESSORIES', $accs];

        foreach ($blocks as $bi => [$label, $list]) {
            $body .= qpBlockHtml($n . '.' . ($bi + 1), $label, $list);
        }
    }

    if (!$quote['sections']) {
        $body .= '<tr><td colspan="10" class="c" style="padding:14px;">No items.</td></tr>';
    }

    $body .= '<tr class="grand"><td colspan="9" class="r b">GRAND TOTAL</td><td>' . qpMoney($grand) . '</td></tr>';

    return '<html><head><meta charset="UTF-8"><style>
        @page { margin: 22px 18px; }
        body { font-family: "DejaVu Sans", sans-serif; font-size: 7px; color: #111; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        td, th { border: 1px solid #000; padding: 3px 4px; vertical-align: middle; word-wrap: break-word; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .c { text-align: center; }
        .r { text-align: right; }
        .b { font-weight: bold; }
        .sp td { border: none; padding: 0; height: 0; line-height: 0; font-size: 0; }

        /* header block */
        .top { border-top: 1px solid #000; border-left: 1px solid #000; border-right: 1px solid #000; }
        .top td { border: none; text-align: center; padding: 1px 4px; }
        .top .logo { text-align: left; padding: 6px 0 4px 10px; }
        .top .t1 { font-size: 20px; font-weight: bold; padding-top: 6px; }
        .top .t2 { font-size: 12px; font-weight: bold; padding-top: 3px; white-space: nowrap; }
        .top .t3 { font-size: 7px; padding-bottom: 6px; }
        .hdr td { padding: 5px 6px; font-size: 8px; }
        .hdr .lab { text-align: right; font-weight: bold; }
        .hdr .val { font-weight: bold; }

        .title { font-size: 9px; font-weight: bold; text-align: center; padding: 5px; }
        .head th { background: #d3d9e8; font-weight: bold; text-align: center; }
        .sec td { background: #d9d9d9; font-weight: bold; font-size: 8px; padding: 5px 6px; }
        .grand td { background: #eeeeee; font-size: 8px; padding: 5px 4px; }
        .m { text-align: right; }
        .m .p { float: left; }

        /* footer: notes + signatures (no borders) */
        .foot-wrap { page-break-inside: avoid; }
        .foot td, .sign td { border: 1px solid #cfcfcf; padding: 3px 4px; font-size: 7px; }
        .foot, .sign { border: 1px solid #cfcfcf; }
        .foot .i, .sign .i { font-style: italic; }
        .foot .red { color: #e00000; }
        .sign .nm td { height: 30px; vertical-align: bottom; }
        .sign .u { text-decoration: underline; }
        .sign .line { border-bottom: 1px solid #000; width: 75%; margin: 0 auto; height: 14px; }
    </style></head><body>

    ' . qpHeaderHtml($quote) . '

    <table style="margin-top:-1px;">
        <colgroup>
            <col width="5%"><col width="12%"><col width="20%"><col width="10%"><col width="6%">
            <col width="5%"><col width="11%"><col width="10%"><col width="11%"><col width="10%">
        </colgroup>
        <thead>
            <tr class="sp"><td style="width:5%"></td><td style="width:12%"></td><td style="width:20%"></td><td style="width:10%"></td><td style="width:6%"></td><td style="width:5%"></td><td style="width:11%"></td><td style="width:10%"></td><td style="width:11%"></td><td style="width:10%"></td></tr>
            <tr><th colspan="10" class="title">ITEMIZED COST BREAKDOWN</th></tr>
            <tr class="head">
                <th rowspan="2">Item No.</th>
                <th rowspan="2">Item Photo</th>
                <th rowspan="2">Description</th>
                <th rowspan="2">Measurement</th>
                <th rowspan="2">Units (Sqm./Lm./ Pcs.)</th>
                <th rowspan="2">Qty.</th>
                <th colspan="3">Unit Cost</th>
                <th rowspan="2">Total Amount</th>
            </tr>
            <tr class="head"><th>Materials</th><th>Labor</th><th>Total</th></tr>
            <tr><td colspan="10" class="c b" style="font-size:8px;padding:5px;">MODULAR FURNITURE</td></tr>
        </thead>
        <tbody>' . $body . '</tbody>
    </table>

    ' . qpFooterHtml() . '

    </body></html>';
}

// -----------------------------------------------------------------------
// Action
// -----------------------------------------------------------------------

$qid   = (int)($_GET['id'] ?? 0);
$quote = $qid > 0 ? qpFetch($conn, $qid) : null;

if (!$quote) {
    http_response_code(404);
    echo 'Quotation not found.';
    exit;
}

$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultPaperSize', 'legal');
$options->set('defaultFont', 'DejaVu Sans'); // DejaVu has the peso sign; the core Helvetica font does not

$dompdf = new Dompdf($options);
$dompdf->setPaper('legal', 'portrait');
$dompdf->loadHtml(qpRenderHtml($quote));
$dompdf->render();

$safe     = trim(preg_replace('/[^A-Za-z0-9_-]+/', '-', $quote['client_name']), '-') ?: 'Client';
$filename = 'Quotation-' . $safe . '-' . date('Ymd', strtotime($quote['quote_date'] ?: 'now')) . '.pdf';
$dompdf->stream($filename, ['Attachment' => true]);
exit;