<?php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';

$project_id = intval($_GET['id'] ?? 0);
if (!$project_id) die('Invalid project');

$stmt = $conn->prepare("SELECT * FROM nobleprojectmonitor WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $project_id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();
if (!$project) die('Project not found');

$stmt2 = $conn->prepare("SELECT * FROM nobleprojectbilling WHERE project_id = ? ORDER BY id ASC");
$stmt2->bind_param("i", $project_id);
$stmt2->execute();
$billings = $stmt2->get_result()->fetch_all(MYSQLI_ASSOC);

$stmt3 = $conn->prepare("SELECT * FROM nobleprojectexpense WHERE project_id = ? ORDER BY id ASC");
$stmt3->bind_param("i", $project_id);
$stmt3->execute();
$expenses = $stmt3->get_result()->fetch_all(MYSQLI_ASSOC);

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

$ss = new Spreadsheet();
$ws = $ss->getActiveSheet();
$ws->setTitle('Project Monitoring');
$ws->setShowGridlines(false);

// ── DEFAULT FONT (applies to entire sheet) ────────────────────────────────────
$ss->getDefaultStyle()->applyFromArray([
    'font' => [
        'name' => 'Segoe UI',
        'size' => 9,
        'bold' => true,
    ],
]);

// ── COLORS ───────────────────────────────────────────────────────────────────
$C_DARK   = '1E293B';
$C_ORA    = 'D97706';
$C_ORA2   = 'F97316';
$C_THEAD  = '374151';
$C_YELLOW = 'FEF3C7';
$C_WHITE  = 'FFFFFF';
$C_BORDER = 'E5E7EB';

// ── HELPERS ──────────────────────────────────────────────────────────────────
function xFill($c) {
    return ['fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => $c]]];
}
function xFont($c, $bold = false, $sz = 9, $name = 'Arial') {
    return ['font' => ['color' => ['rgb' => $c], 'bold' => $bold, 'size' => $sz, 'name' => $name]];
}
function xAlign($h = 'left', $v = 'center', $wrap = false) {
    return ['alignment' => ['horizontal' => $h, 'vertical' => $v, 'wrapText' => $wrap]];
}
function xBorder($c = 'E5E7EB', $st = Border::BORDER_THIN) {
    return ['borders' => ['allBorders' => ['borderStyle' => $st, 'color' => ['rgb' => $c]]]];
}
function xApply($ws, $range, array $styles) {
    $merged = array_merge_recursive(...$styles);
    $ws->getStyle($range)->applyFromArray($merged);
}
function xMergeSet($ws, $from, $to, $val, array $styles) {
    if ($from !== $to) $ws->mergeCells("{$from}:{$to}");
    $ws->setCellValue($from, $val);
    xApply($ws, "{$from}:{$to}", $styles);
}

// ── COLUMN WIDTHS ─────────────────────────────────────────────────────────────
// Layout (15 columns A-O):
// A=margin | B=logo | C=NO. | D-G=PARTICULARS | H-I=AMOUNT |
// J-K=BANK/MODE | L=PAY DATE | M=REFERENCE | N=REMARKS | O=margin
$colW = [
    'A' => 0,  // left margin
    'B' => 8,    // logo (reserved for drawing)
    'C' => 5,    // NO.
    'D' => 14,   // PARTICULARS (4 cols total = wide)
    'E' => 14,
    'F' => 12,
    'G' => 8,
    'H' => 10,   // AMOUNT
    'I' => 10,
    'J' => 11,   // BANK/CHECK or MODE OF PAYMENT
    'K' => 11,
    'L' => 12,   // PAYMENT DATE
    'M' => 12,   // REFERENCE
    'N' => 12,   // REMARKS
    'O' => 0.5,  // right margin
];
foreach ($colW as $col => $w) $ws->getColumnDimension($col)->setWidth($w);

// ── HEADER ROW HEIGHTS ────────────────────────────────────────────────────────
$ws->getRowDimension(1)->setRowHeight(2);
$ws->getRowDimension(2)->setRowHeight(26);  // main title row
$ws->getRowDimension(3)->setRowHeight(14);  // subtitle row
$ws->getRowDimension(4)->setRowHeight(13);  // address / date row
$ws->getRowDimension(5)->setRowHeight(2);

// ── HEADER: fill entire rows 1-5 ─────────────────────────────────────────────
// Left side orange (A-G), right side dark (H-O)
foreach (range(1, 5) as $r) {
    xApply($ws, "A{$r}:G{$r}", [xFill($C_ORA)]);
    xApply($ws, "H{$r}:O{$r}", [xFill($C_ORA)]);
}

// ── LOGO ─────────────────────────────────────────────────────────────────────


$logoPath = ROOT_PATH . '/icon/logowhi.png';
if (file_exists($logoPath)) {
    $d = new Drawing();
    $d->setPath($logoPath);
    $d->setCoordinates('B2');
    $d->setOffsetX(6);
    $d->setOffsetY(10);
    $d->setWidth(48);
    $d->setHeight(48);
    $d->setWorksheet($ws);
}

// ── COMPANY NAME (col C onward — col B reserved for logo image) ───────────────
xMergeSet($ws, 'C2', 'G2', 'NOBLEHOME',
    [xFill($C_ORA), xFont($C_WHITE, true, 15), xAlign('left', 'bottom')]);
xMergeSet($ws, 'C3', 'G3', 'CONSTRUCTION CORPORATION',
    [xFill($C_ORA), xFont($C_WHITE, true, 8), xAlign('left', 'center')]);
xMergeSet($ws, 'C4', 'G4', '1181 MC Premier Bldg., EDSA Balintawak Quezon City',
    [xFill($C_ORA), xFont($C_WHITE, false, 7), xAlign('left', 'center')]);

// ── PROJECT MONITORING (right dark side) ──────────────────────────────────────
xMergeSet($ws, 'H2', 'O2', 'PROJECT MONITORING',
    [xFill($C_ORA), xFont($C_WHITE, true, 16), xAlign('right', 'bottom')]);
xMergeSet($ws, 'H3', 'O3', 'ACCOUNTING REPORT',
    [xFill($C_ORA), xFont($C_WHITE, true, 9), xAlign('right', 'center')]);
xMergeSet($ws, 'H4', 'O4', 'DATE ISSUE:   ' . date('Y-m-d'),
    [xFill($C_ORA), xFont($C_WHITE, false, 8), xAlign('right', 'center')]);

// ── GAP ───────────────────────────────────────────────────────────────────────
$ws->getRowDimension(6)->setRowHeight(10);
xApply($ws, 'A6:O6', [xFill($C_WHITE)]);

// ── BASIC INFORMATION ─────────────────────────────────────────────────────────
$ws->getRowDimension(7)->setRowHeight(18);
xApply($ws, 'A7:O7', [xFill($C_WHITE)]);
// Orange pill
$ws->mergeCells('B7:E7');
$ws->setCellValue('B7', '  BASIC INFORMATION');
xApply($ws, 'B7:E7', [xFill($C_ORA2), xFont($C_WHITE, true, 8), xAlign('left', 'center')]);
xApply($ws, 'F7:O7', [xFill($C_WHITE)]);
// Bottom border on label row
xApply($ws, 'A7:O7', [['borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $C_BORDER]]]]]);

$infoRows = [
    ['PROJECT NAME :',    $project['project_name']    ?? '', 'JOB ORDER :',           $project['job_order']         ?? ''],
    ['PROJECT SCOPE :',   $project['project_scope']   ?? '', 'PURCHASE ORDER :',      $project['purchase_order']    ?? ''],
    ['CLIENT NAME :',     $project['client_name']     ?? '', 'NOTICE TO PROCEED :',   $project['notice_to_proceed'] ?? ''],
    ['CONTRACT AMOUNT :', $project['contract_amount'] ? '₱ ' . number_format($project['contract_amount'], 2) : '',
                                                             '(1) BILLING ORDER # :', $project['billing_order_1']   ?? ''],
    ['SALES PERSON :',    $project['sales_person']    ?? '', '(2) BILLING ORDER # :', $project['billing_order_2']   ?? ''],
    ['ADDRESS :',         $project['address']         ?? '', 'STATUS :',              $project['status']            ?? ''],
];

$row = 8;
$infoStartRow = $row;
foreach ($infoRows as $f) {
    $ws->getRowDimension($row)->setRowHeight(16);
    xApply($ws, "A{$row}:O{$row}", [xFill($C_WHITE)]);

    // Left label B-C
    $ws->mergeCells("B{$row}:C{$row}");
    $ws->setCellValue("B{$row}", $f[0]);
    xApply($ws, "B{$row}:C{$row}", [xFill($C_WHITE), xFont($C_THEAD, true, 8), xAlign('left', 'center')]);

    // Left value D-G with underline
    $ws->mergeCells("D{$row}:G{$row}");
    $ws->setCellValue("D{$row}", $f[1]);
    xApply($ws, "D{$row}:G{$row}", [xFill($C_WHITE), xFont('111827', false, 9), xAlign('left', 'center')]);
    $ws->getStyle("D{$row}:G{$row}")->getBorders()->getBottom()
        ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('374151');

    // Middle gap H (empty)
    xApply($ws, "H{$row}", [xFill($C_WHITE)]);

    // Right label I-J
    $ws->mergeCells("I{$row}:J{$row}");
    $ws->setCellValue("I{$row}", $f[2]);
    xApply($ws, "I{$row}:J{$row}", [xFill($C_WHITE), xFont($C_THEAD, true, 8), xAlign('left', 'center')]);

    // Right value K-N with underline
    $ws->mergeCells("K{$row}:N{$row}");
    $ws->setCellValue("K{$row}", $f[3]);
    xApply($ws, "K{$row}:N{$row}", [xFill($C_WHITE), xFont('111827', false, 9), xAlign('left', 'center')]);
    $ws->getStyle("K{$row}:N{$row}")->getBorders()->getBottom()
        ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('374151');

    $row++;
}
// Outer border around info block
$ws->getStyle("A{$infoStartRow}:O" . ($row - 1))->applyFromArray([
    'borders' => ['outline' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $C_BORDER]]]
]);

// Gap before sections
$ws->getRowDimension($row)->setRowHeight(12);
xApply($ws, "A{$row}:O{$row}", [xFill($C_WHITE)]);
$row++;

// ── RENDER TABLE SECTION ──────────────────────────────────────────────────────
function renderTableSection(
    $ws, &$row, $sectionTitle, $tableHeaders, $records, $footerLabel, $footerAmtRange,
    $hasBalance, $balanceAmt, $incomeLoss = null 
){
    global $C_ORA2, $C_THEAD, $C_WHITE, $C_YELLOW, $C_BORDER;

    // Section title bar
    $ws->getRowDimension($row)->setRowHeight(18);
    xApply($ws, "A{$row}:O{$row}", [xFill('F3F4F6')]);
    $ws->mergeCells("B{$row}:F{$row}");
    $ws->setCellValue("B{$row}", '  ' . $sectionTitle);
    xApply($ws, "B{$row}:F{$row}", [xFill($C_ORA2), xFont('FFFFFF', true, 8), xAlign('left', 'center')]);
    $row++;

    // Table header
    $ws->getRowDimension($row)->setRowHeight(22);
    xApply($ws, "A{$row}:O{$row}", [xFill($C_THEAD)]);
    foreach ($tableHeaders as $h) {
        [$cs, $ce, $label] = $h;
        if ($cs !== $ce) $ws->mergeCells("{$cs}{$row}:{$ce}{$row}");
        $ws->setCellValue("{$cs}{$row}", $label);
        xApply($ws, "{$cs}{$row}:{$ce}{$row}", [
            xFill($C_THEAD),
            xFont('FFFFFF', true, 8),
            xAlign('center', 'center', true),
            xBorder('4B5563'),
        ]);
    }
    $row++;

    // Data rows (min 10)
    $padded = array_pad($records, 10, null);
    $total  = 0;
    foreach ($padded as $i => $rec) {
        $ws->getRowDimension($row)->setRowHeight(16);
        $bg = ($i % 2 === 0) ? $C_WHITE : 'F9FAFB';
        xApply($ws, "A{$row}:O{$row}", [xFill($bg)]);

        // NO. column — merge B:C
        $ws->mergeCells("B{$row}:C{$row}");
        $ws->setCellValue("B{$row}", $i + 1);
        xApply($ws, "B{$row}:C{$row}", [xFill($bg), xFont('9CA3AF', false, 9), xAlign('center', 'center'), xBorder($C_BORDER)]);

        // Margin cols
        xApply($ws, "A{$row}", [xFill($bg), xBorder($bg)]);
        xApply($ws, "O{$row}", [xFill($bg), xBorder($bg)]);

        // Data columns
        $dataCols = $tableHeaders;
        array_shift($dataCols); // skip NO. header
        foreach ($dataCols as $h) {
            [$cs, $ce, ] = $h;
            $fieldKey = $h[3] ?? null;
            $val = $rec && $fieldKey ? ($rec[$fieldKey] ?? '') : '';
            if ($fieldKey === 'amount' && $rec) {
                $val = '₱ ' . number_format($rec['amount'] ?? 0, 2);
                $total += floatval($rec['amount'] ?? 0);
            }
            if ($cs !== $ce) $ws->mergeCells("{$cs}{$row}:{$ce}{$row}");
            $ws->setCellValue("{$cs}{$row}", $val);
            $alignH = $fieldKey === 'amount' ? 'right' : 'left';
            if ($cs === 'C') $alignH = 'center';
            xApply($ws, "{$cs}{$row}:{$ce}{$row}", [xFill($bg), xFont('374151', false, 9), xAlign($alignH, 'center'), xBorder($C_BORDER)]);
        }
        $row++;
    }

    // Footer row
    $ws->getRowDimension($row)->setRowHeight(18);
    xApply($ws, "A{$row}:O{$row}", [xFill($C_YELLOW)]);

    $ws->mergeCells("B{$row}:D{$row}");
    $ws->setCellValue("B{$row}", '  ' . $footerLabel);
    xApply($ws, "B{$row}:D{$row}", [xFill($C_YELLOW), xFont($C_THEAD, true, 8), xAlign('left', 'center')]);

    [$fa, $fb] = $footerAmtRange;
    $ws->mergeCells("{$fa}{$row}:{$fb}{$row}");
    $ws->setCellValue("{$fa}{$row}", '₱ ' . number_format($total, 2));
    xApply($ws, "{$fa}{$row}:{$fb}{$row}", [xFill($C_YELLOW), xFont('000000', true, 10), xAlign('right', 'center')]);

    if ($hasBalance) {
        $ws->mergeCells("J{$row}:L{$row}");
        $ws->setCellValue("J{$row}", 'TOTAL BALANCE :');
        xApply($ws, "J{$row}:L{$row}", [xFill($C_YELLOW), xFont($C_THEAD, true, 8), xAlign('right', 'center')]);
        $ws->mergeCells("M{$row}:N{$row}");
        $ws->setCellValue("M{$row}", '₱ ' . number_format($balanceAmt, 2));
        xApply($ws, "M{$row}:N{$row}", [xFill($C_YELLOW), xFont('000000', true, 10), xAlign('right', 'center')]);
    }
    xApply($ws, "O{$row}", [xFill($C_YELLOW)]);

    $row++;

// Income/Loss row — ipapakita lang kung may value
if ($incomeLoss !== null) {
    $ws->getRowDimension($row)->setRowHeight(18);
    
    $isLoss = $incomeLoss < 0;
    $rowBg  = $isLoss ? 'FEF2F2' : 'F0FDF4'; // pula bg kung loss, berde kung income
    $txtClr = $isLoss ? 'DC2626' : '16A34A';  // pula text kung loss, berde kung income
    
    xApply($ws, "A{$row}:O{$row}", [xFill($rowBg)]);
    
    $ws->mergeCells("B{$row}:D{$row}");
    $ws->setCellValue("B{$row}", '  POSSIBLE INCOME / LOSS :');
    xApply($ws, "B{$row}:D{$row}", [xFill($rowBg), xFont($C_THEAD, true, 8), xAlign('left', 'center')]);
    
    $ws->mergeCells("E{$row}:N{$row}");
    $ws->setCellValue("E{$row}", '₱ ' . number_format(abs($incomeLoss), 2) . ($isLoss ? '  (LOSS)' : '  (INCOME)'));
    xApply($ws, "E{$row}:N{$row}", [xFill($rowBg), xFont($txtClr, true, 10), xAlign('right', 'center')]);
    
    xApply($ws, "O{$row}", [xFill($rowBg)]);
    $row++;
    
    // Gap
    $ws->getRowDimension($row)->setRowHeight(10);
    xApply($ws, "A{$row}:O{$row}", [xFill($C_WHITE)]);
    $row++;
}
}

// ── BILLING HEADERS ───────────────────────────────────────────────────────────
$billingHeaders = [
    ['B', 'C', 'NO.'],
    ['D', 'G', 'PARTICULARS',    'particulars'],
    ['H', 'I', 'AMOUNT',         'amount'],
    ['J', 'K', 'BANK / CHECK',   'bank_check'],
    ['L', 'L', 'PAYMENT DATE',   'payment_date'],
    ['M', 'M', 'REFERENCE',      'reference'],
    ['N', 'N', 'REMARKS',        'remarks'],
];

$contractAmt  = floatval($project['contract_amount'] ?? 0);
$billingTotal = array_sum(array_column($billings, 'amount'));
$balance      = $contractAmt - $billingTotal;

renderTableSection($ws, $row,
    '1. BILLED AND PAID BY CLIENT / OWNER',
    $billingHeaders,
    $billings,
    'TOTAL AMOUNT CREDITED :',
    ['E', 'I'],
    true,
    $balance
);

// ── EXPENSE HEADERS ───────────────────────────────────────────────────────────
$expenseHeaders = [
    ['B', 'C', 'NO.'],
    ['D', 'F', 'ACCOUNT TITLE',            'title'],
    ['G', 'G', 'PARTICULARS',      'particulars'],
    ['H', 'I', 'AMOUNT',           'amount'],
    ['J', 'K', 'MODE OF PAYMENT',  'mode_of_payment'],
    ['L', 'L', 'PAYMENT DATE',     'payment_date'],
    ['M', 'M', 'REFERENCE',        'reference'],
    ['N', 'N', 'REMARKS',          'remarks'],
];

$expenseTotal = array_sum(array_column($expenses, 'amount'));
$incomeLoss   = $contractAmt - $expenseTotal;

renderTableSection($ws, $row,
    '2. COSTS / EXPENSES',
    $expenseHeaders,
    $expenses,
    'TOTAL AMOUNT PAID :',
    ['E', 'N'],
    false,
    0,
    $incomeLoss
);


// ── FOOTER LINE ───────────────────────────────────────────────────────────────
$ws->getRowDimension($row)->setRowHeight(14);
$ws->mergeCells("A{$row}:O{$row}");
$ws->setCellValue("A{$row}", 'Generated: ' . date('F d, Y') . ' | ' . ($project['reference_no'] ?? ''));
xApply($ws, "A{$row}:O{$row}", [
    xFill($C_WHITE), xFont('9CA3AF', false, 8), xAlign('right', 'center'),
    ['borders' => ['top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $C_BORDER]]]]
]);

// ── PAGE SETUP ────────────────────────────────────────────────────────────────
$ws->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE);
$ws->getPageSetup()->setPaperSize(PageSetup::PAPERSIZE_A4);
$ws->getPageSetup()->setFitToPage(true);
$ws->getPageSetup()->setFitToWidth(1);
$ws->getPageSetup()->setFitToHeight(0);
$ws->getPageMargins()->setTop(0.3);
$ws->getPageMargins()->setBottom(0.3);
$ws->getPageMargins()->setLeft(0.3);
$ws->getPageMargins()->setRight(0.3);

// ── OUTPUT ────────────────────────────────────────────────────────────────────
// ?view=1  → ipakita sa browser (HTML preview ng parehong sheet)
// default  → i-download bilang .xlsx
$filename = 'project-monitoring-' . preg_replace('/[^A-Za-z0-9._-]+/', '-', (string) ($project['reference_no'] ?? $project_id)) . '.xlsx';

if (($_GET['view'] ?? '') === '1') {
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Html($ss);
    if (method_exists($writer, 'setEmbedImages')) $writer->setEmbedImages(true);   // kasama ang logo sa preview
    if (method_exists($writer, 'setEditHtmlCallback')) {
        $title = htmlspecialchars(($project['project_name'] ?? 'Project') . ' — Excel preview', ENT_QUOTES, 'UTF-8');
        $writer->setEditHtmlCallback(function (string $html) use ($title): string {
            $css = '<title>' . $title . '</title><style>
                html, body { margin: 0; background: #e5e7eb; }
                body { padding: 16px; }
                body > table, body > div > table { background: #fff; margin: 0 auto; box-shadow: 0 1px 3px rgba(0,0,0,.12), 0 8px 24px rgba(0,0,0,.08); }
                @media print { html, body { background: #fff; padding: 0; } body > table, body > div > table { box-shadow: none; } }
            </style>';
            return preg_replace('#</head>#i', $css . '</head>', $html, 1);
        });
    }
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: no-store');
    header('X-Frame-Options: SAMEORIGIN');
    $writer->save('php://output');
    exit;
}

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ss);
$writer->save('php://output');
exit;