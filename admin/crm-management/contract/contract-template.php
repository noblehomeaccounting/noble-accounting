<?php
// contract-template.php — Contract Agreement ng NobleHome (template + helpers)
// Ginagamit ng: contracts.php / contractsajax.php (staff), contract-pdf.php (PDF), contract-sign.php (client link)
//
// ⚠️ Ang mga clause ay kinopya mula sa papel na contract ng NobleHome.
//    Kung may babaguhin sa wording, dito lang (nhcRenderContract) — lahat ng page ay susunod.

if (defined('NHC_LOADED')) {
    return;
}
define('NHC_LOADED', true);

const NHC_STATUSES = ['Draft', 'For Staff Signing', 'For Client Signing', 'For GM Signing', 'Completed'];

// Logo sa letterhead (palitan kung iba ang path ng logo ninyo). Kapag wala, text lang ang lalabas.
const NHC_LOGO_CANDIDATES = [
    '/icon/logo.png',
    '/icon/logo.webp',
];

// Folder ng client signature + ID (naka-block sa direct access — sa ajax lang mabubuksan)
const NHC_PRIVATE_DIR = '/uploads/crm-contract-private/';

const NHC_COMPANY = [
    'name'    => 'NOBLEHOME CONSTRUCTION CORPORATION',
    'address' => '1181MC Premiere Bldg., EDSA Balintawak Quezon City',
    'contact' => 'noblehomeconst.ph@gmail.com / Tel. No. 02-88221295 / Cell. No. 0968-591-6544',
];

function nhcEsc($v): string
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function nhcMoney($v): string
{
    return number_format((float) $v, 2);
}

function nhcDateLong(?string $d): string
{
    if (!$d || $d === '0000-00-00') return '____________';
    $t = strtotime($d);
    return $t ? date('F j, Y', $t) : '____________';
}

function nhcDateShort(?string $d): string
{
    if (!$d) return '';
    $t = strtotime($d);
    return $t ? date('m/d/Y', $t) : '';
}

/** 0–100 → "Ten", "Twenty-five", ... (para sa "Ten percent (10%)") */
function nhcPercentWords(int $n): string
{
    $ones = ['zero', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine', 'ten',
        'eleven', 'twelve', 'thirteen', 'fourteen', 'fifteen', 'sixteen', 'seventeen', 'eighteen', 'nineteen'];
    $tens = [2 => 'twenty', 'thirty', 'forty', 'fifty', 'sixty', 'seventy', 'eighty', 'ninety'];
    if ($n >= 100) return 'One hundred';
    if ($n < 20) return ucfirst($ones[max(0, $n)]);
    $w = $tens[intdiv($n, 10)] . ($n % 10 ? '-' . $ones[$n % 10] : '');
    return ucfirst($w);
}

function nhcDpAmount(array $c): float
{
    return round((float) $c['contract_amount'] * (int) $c['dp_percent'] / 100, 2);
}

function nhcBalance(array $c): float
{
    $total = (float) $c['contract_amount'];
    return !empty($c['dp_paid']) ? round($total - nhcDpAmount($c), 2) : $total;
}

/** Buong row ng contract + pangalan ng mga pumirma. */
function nhcLoad(mysqli $conn, string $where, string $types, array $params): ?array
{
    $sql = "
        SELECT c.*, i.control_no, i.client_name, i.sales_staff_id, i.designer_id,
               ar.name AS acct_name, sr.name AS sales_name, dr.name AS designer_name, gr.name AS gm_name
        FROM noblecrm_contract c
        JOIN noblecrminquiry i ON i.id = c.inquiry_id
        LEFT JOIN noblerole ar ON ar.id = c.acct_signed_by
        LEFT JOIN noblerole sr ON sr.id = c.sales_user_id
        LEFT JOIN noblerole dr ON dr.id = c.designer_user_id
        LEFT JOIN noblerole gr ON gr.id = c.gm_signed_by
        WHERE {$where}
        LIMIT 1";
    $st = $conn->prepare($sql);
    $st->bind_param($types, ...$params);
    $st->execute();
    $row = $st->get_result()->fetch_assoc();
    $st->close();
    return $row ?: null;
}

/** Larawan → data URI (para sa PDF at para hindi ma-expose ang private na path). */
function nhcImgData(?string $relPath): string
{
    if (!$relPath) return '';
    $abs = ROOT_PATH . '/' . ltrim($relPath, '/');
    if (!is_file($abs) || filesize($abs) > 8 * 1024 * 1024) return '';
    $info = @getimagesize($abs);
    if (!$info || empty($info['mime'])) return '';
    return 'data:' . $info['mime'] . ';base64,' . base64_encode((string) file_get_contents($abs));
}

function nhcLogoData(): string
{
    foreach (NHC_LOGO_CANDIDATES as $p) {
        $d = nhcImgData($p);
        if ($d !== '') return $d;
    }
    return '';
}

/** Lahat ng larawan ng isang contract bilang data URI. */
function nhcImages(array $c): array
{
    return [
        'logo'     => nhcLogoData(),
        'acct'     => nhcImgData($c['acct_signature_path'] ?? null),
        'sales'    => nhcImgData($c['sales_signature_path'] ?? null),
        'designer' => nhcImgData($c['designer_signature_path'] ?? null),
        'client'   => nhcImgData($c['client_signature_path'] ?? null),
        'gm'       => nhcImgData($c['gm_signature_path'] ?? null),
    ];
}

function nhcCss(): string
{
    return <<<CSS
    .nhc-doc { font-family: "DejaVu Serif", Cambria, Georgia, "Times New Roman", serif; color: #111; font-size: 11.5pt; line-height: 1.45; }
    .nhc-doc p { margin: 0 0 7pt 0; text-align: justify; }
    .nhc-head { text-align: center; border-bottom: 1px solid #b45309; padding-bottom: 6pt; margin-bottom: 10pt; }
    .nhc-head td { vertical-align: middle; }
    .nhc-head .co { font-family: "DejaVu Sans", Arial, sans-serif; font-weight: bold; font-size: 13pt; letter-spacing: .3pt; }
    .nhc-head .sub { font-family: "DejaVu Sans", Arial, sans-serif; font-size: 8pt; color: #444; font-style: italic; }
    .nhc-title { text-align: center; font-weight: bold; font-size: 14pt; margin: 6pt 0 12pt; letter-spacing: .5pt; }
    .nhc-art { font-weight: bold; margin: 12pt 0 5pt; text-transform: uppercase; }
    .nhc-sub { font-weight: bold; margin: 8pt 0 4pt 8pt; }
    .nhc-ind { margin-left: 16pt !important; }
    .nhc-list { margin: 0 0 7pt 0; padding-left: 26pt; }
    .nhc-list li { margin-bottom: 3pt; text-align: justify; }
    .nhc-fill { font-weight: bold; text-decoration: underline; }
    .nhc-page { page-break-before: always; }
    .nhc-sign { width: 100%; border-collapse: collapse; margin-top: 8pt; }
    .nhc-sign td { width: 50%; vertical-align: bottom; padding: 10pt 12pt 6pt 0; }
    .nhc-sigbox { height: 52pt; position: relative; }
    .nhc-sigbox img { max-height: 52pt; max-width: 170pt; }
    .nhc-signame { border-top: 1px solid #111; font-weight: bold; text-transform: uppercase; padding-top: 2pt; font-family: "DejaVu Sans", Arial, sans-serif; font-size: 10pt; }
    .nhc-sigrole { font-style: italic; font-size: 10pt; }
    .nhc-sigdate { font-size: 10pt; }
    .nhc-pending { color: #9ca3af; font-style: italic; font-size: 9pt; }
    .nhc-info td { padding: 1.5pt 0; font-size: 10.5pt; vertical-align: top; }
    .nhc-info td.k { font-weight: bold; white-space: nowrap; padding-right: 6pt; }
    .nhc-body p { text-align: justify; }
    .nhc-body h1, .nhc-body h2, .nhc-body h3 { margin: 6pt 0 10pt; line-height: 1.25; }
    .nhc-body h1 { font-size: 16pt; } .nhc-body h2 { font-size: 14pt; letter-spacing: .5pt; } .nhc-body h3 { font-size: 12pt; }
    .nhc-body ol, .nhc-body ul { margin: 0 0 7pt 0; padding-left: 26pt; }
    .nhc-body li { margin-bottom: 3pt; }
    .nhc-body .ql-align-center { text-align: center; } .nhc-body .ql-align-right { text-align: right; } .nhc-body .ql-align-justify { text-align: justify; }
    .nhc-body .ql-indent-1 { padding-left: 16pt; } .nhc-body .ql-indent-2 { padding-left: 32pt; } .nhc-body .ql-indent-3 { padding-left: 48pt; }
    .nhc-body .ql-indent-4 { padding-left: 64pt; } .nhc-body .ql-indent-5 { padding-left: 80pt; } .nhc-body .ql-indent-6 { padding-left: 96pt; }
    .nhc-body .ql-indent-7 { padding-left: 112pt; } .nhc-body .ql-indent-8 { padding-left: 128pt; }
    CSS;
}

/** Buong HTML document para sa dompdf (letterhead sa bawat page). */
/**
 * Maliit na pirma ng lahat sa ibaba ng BAWAT page (parang initial sa bawat pahina).
 * Kapag wala pang pirma ang isa, blangkong linya lang.
 */
function nhcPageSignatures(array $c, array $img): string
{
    $who = [
        ['sales', 'Sales Coordinator', $c['sales_name'] ?? ''],
        ['designer', 'Designer', $c['designer_name'] ?? ''],
        ['acct', 'Accounting Head', $c['acct_name'] ?? ''],
        ['client', 'Client', $c['client_signed_name'] ?? ''],
        ['gm', 'General Manager', $c['gm_name'] ?? ''],
    ];
    // Walang designer na naka-assign → hindi isasama
    if (empty($c['designer_user_id']) && empty($c['designer_signature_path']) && ($c['status'] ?? '') !== 'Draft') {
        $who = array_values(array_filter($who, fn($w) => $w[0] !== 'designer'));
    }
    $cells = '';
    foreach ($who as [$k, $role, $name]) {
        // Pirma lang (walang pangalan / posisyon) — ang buong pirmahan ay nasa huling page
        $sig = ($img[$k] ?? '') !== '' ? '<img src="' . $img[$k] . '" alt="">' : '';
        $cells .= '<td><div class="nhc-ps-sig">' . $sig . '</div></td>';
    }
    return '<table class="nhc-ps"><tr>' . $cells . '</tr></table>';
}

function nhcPageSignaturesCss(): string
{
    return '.nhc-ps { width: 100%; border-collapse: collapse; table-layout: fixed; font-family: "DejaVu Sans", Arial, sans-serif; }'
        . '.nhc-ps td { text-align: center; vertical-align: bottom; padding: 0 5pt; }'
        . '.nhc-ps-sig { height: 26pt; }'
        . '.nhc-ps-sig img { max-height: 26pt; max-width: 80pt; }';
}

/** Buong HTML document para sa dompdf (letterhead sa itaas + pirma ng lahat sa ibaba ng bawat page). */
function nhcPdfHtml(array $c, array $img): string
{
    return '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'
        . '@page { margin: 118px 56px 92px 56px; }'
        . 'body { margin: 0; }'
        . '#nhc-fixed-head { position: fixed; top: -100px; left: 0; right: 0; }'
        . '#nhc-fixed-foot { position: fixed; bottom: -60px; left: 0; right: 0; }'
        . '#nhc-last-cover { position: absolute; bottom: -70px; left: -20px; right: -20px; height: 70px; background: #fff; }'
        . nhcCss() . nhcPageSignaturesCss()
        . '</style></head><body>'
        . '<div id="nhc-fixed-head">' . nhcHeaderHtml($img) . '</div>'
        . '<div id="nhc-fixed-foot">' . nhcPageSignatures($c, $img) . '</div>'
        . nhcRenderContract($c, $img, true)
        // Huling page (buong pirmahan na) → tinatakpan ang maliit na pirma sa ibaba
        . '<div id="nhc-last-cover"></div>'
        . '</body></html>';
}

function nhcHeaderHtml(array $img): string
{
    $logo = $img['logo'] ?? '';
    $logoCell = $logo !== '' ? '<td style="width:62pt;text-align:right;padding-right:8pt"><img src="' . $logo . '" style="height:46pt"></td>' : '';
    return '<table class="nhc-head" style="width:100%;border-collapse:collapse"><tr>' . $logoCell . '<td>'
        . '<div class="co">' . nhcEsc(NHC_COMPANY['name']) . '</div>'
        . '<div class="sub">' . nhcEsc(NHC_COMPANY['address']) . '</div>'
        . '<div class="sub">' . nhcEsc(NHC_COMPANY['contact']) . '</div>'
        . '</td>' . ($logo !== '' ? '<td style="width:62pt"></td>' : '') . '</tr></table>';
}

/** Isang linya ng pirma (signature image + pangalan + posisyon + petsa). */
function nhcSignCell(string $img, ?string $name, string $role, ?string $signedAt, string $pendingText): string
{
    $sig = $img !== '' ? '<img src="' . $img . '" alt="signature">' : '<span class="nhc-pending">' . nhcEsc($pendingText) . '</span>';
    return '<div class="nhc-sigbox">' . $sig . '</div>'
        . '<div class="nhc-signame">' . nhcEsc($name ?: '______________________') . '</div>'
        . '<div class="nhc-sigrole">' . nhcEsc($role) . '</div>'
        . '<div class="nhc-sigdate">Date: ' . nhcEsc($signedAt ? nhcDateShort($signedAt) : '__________') . '</div>';
}

/**
 * Halimbawang laman ng contract (EXAMPLE lang — hindi ang totoong contract ng NobleHome).
 * Ang Accounting Head ang magsusulat ng buong laman kada contract (iba-iba ang bawat project).
 * Ang [[...]] ay automatic na napapalitan ng value mula sa form (amount, petsa, atbp.).
 */
function nhcDefaultBody(): string
{
    return <<<'NHCBODY'
<h2 class="ql-align-center">CONTRACT AGREEMENT</h2>
<p><strong>THIS AGREEMENT</strong> is made and entered into this month of [[month]] year [[year]] by and between [[owner]] (the "Owner") and <strong>NOBLEHOME CONSTRUCTION CORPORATION</strong> (the "Contractor") for the project known as [[project]].</p>
<p><br></p>
<p><strong>ARTICLE 1 SCOPE OF WORK</strong></p>
<p class="ql-indent-1"><em>(Write the scope of work for this project.)</em></p>
<p><br></p>
<p><strong>ARTICLE 2 SCHEDULE</strong></p>
<p class="ql-indent-1">Start date: [[start_date]]</p>
<p class="ql-indent-1">Completion date: [[completion_date]]</p>
<p class="ql-indent-1"><em>(Add schedule terms, if any.)</em></p>
<p><br></p>
<p><strong>ARTICLE 3 CONTRACT AMOUNT</strong></p>
<p class="ql-indent-1">The Owner shall pay the Contractor the amount of Php [[contract_amount]].</p>
<p><br></p>
<p><strong>ARTICLE 4 PAYMENT TERMS</strong></p>
<p class="ql-indent-1">1. [[dp_percent]]% down payment (Php [[dp_amount]]).[[dp_paid_note]]</p>
<p class="ql-indent-1">2. [[delivery_percent]]% before delivery</p>
<p class="ql-indent-1">3. [[install_percent]]% for installation</p>
<p class="ql-indent-1">4. [[retention_percent]]% retention</p>
<p class="ql-indent-1"><em>(Add or change the payment terms.)</em></p>
<p><br></p>
<p><strong>ARTICLE 5 OTHER TERMS</strong></p>
<p class="ql-indent-1"><em>(Write the other terms for this project, e.g. warranty, delays, changes in the work, termination.)</em></p>
<p><br></p>
<p><strong>ATTACHMENTS</strong></p>
<p class="ql-indent-1">1. Quotation</p>
<p class="ql-indent-1"><em>(Add other attachments.)</em></p>
NHCBODY;
}

/** Mga [[field]] na puwedeng ilagay sa contract — key => label (para sa editor). */
function nhcPlaceholderList(): array
{
    return [
        'owner'                 => 'Owner (client / company)',
        'project'               => 'Project name',
        'month'                 => 'Month (of signing)',
        'year'                  => 'Year (of signing)',
        'start_date'            => 'Start date',
        'completion_date'       => 'Completion date',
        'contract_amount'       => 'Contract amount',
        'dp_percent'            => 'Down payment %',
        'dp_amount'             => 'Down payment amount',
        'dp_paid_note'          => '"The said amount has been paid." (if paid)',
        'delivery_percent'      => 'Before delivery %',
        'install_percent'       => 'Installation %',
        'retention_percent'     => 'Retention %',
        'retention_words'       => 'Retention % in words',
        'retention_words_lower' => 'Retention % in words (lowercase)',
        'balance'               => 'Balance',
        'validity_days'         => 'Price validity (days)',
        'address'               => 'Address',
        'contact_number'        => 'Contact no.',
        'control_no'            => 'Control no.',
    ];
}

/** Mga value ng [[field]] para sa isang contract. [key => [text, underline?]] */
function nhcPlaceholderValues(array $c): array
{
    $issued = ($c['issued_at'] ?? null) ?: (($c['created_at'] ?? null) ?: date('Y-m-d'));
    $ret = (int) ($c['retention_percent'] ?? 0);
    return [
        'owner'                 => [strtoupper((string) ($c['owner_name'] ?? '')), true],
        'project'               => [strtoupper((string) ($c['project_name'] ?? '')), true],
        'month'                 => [strtoupper(date('F', strtotime($issued))), true],
        'year'                  => [date('Y', strtotime($issued)), true],
        'start_date'            => [nhcDateLong($c['start_date'] ?? null), true],
        'completion_date'       => [nhcDateLong($c['completion_date'] ?? null), true],
        'contract_amount'       => [nhcMoney($c['contract_amount'] ?? 0), true],
        'dp_percent'            => [(string) (int) ($c['dp_percent'] ?? 0), false],
        'dp_amount'             => [nhcMoney(nhcDpAmount($c)), false],
        'dp_paid_note'          => [!empty($c['dp_paid']) ? ' The said amount has been paid.' : '', false],
        'delivery_percent'      => [(string) (int) ($c['delivery_percent'] ?? 0), false],
        'install_percent'       => [(string) (int) ($c['install_percent'] ?? 0), false],
        'retention_percent'     => [(string) $ret, false],
        'retention_words'       => [nhcPercentWords($ret), false],
        'retention_words_lower' => [strtolower(nhcPercentWords($ret)), false],
        'balance'               => [nhcMoney(nhcBalance($c)), false],
        'validity_days'         => [(string) (int) ($c['validity_days'] ?? 30), false],
        'address'               => [(string) ($c['address'] ?? ''), false],
        'contact_number'        => [(string) ($c['contact_number'] ?? ''), false],
        'control_no'            => [(string) ($c['control_no'] ?? ''), false],
    ];
}

/**
 * Nililinis ang HTML galing sa editor — formatting tags lang ang pinapayagan
 * (walang script, link, style o larawan). Ibinabalik ang malinis na HTML o '' kung walang laman.
 */
function nhcSanitizeBody(string $html): string
{
    $html = trim($html);
    if ($html === '') return '';
    $allowed = ['p', 'br', 'strong', 'b', 'em', 'i', 'u', 's', 'h1', 'h2', 'h3', 'ol', 'ul', 'li', 'span', 'sub', 'sup', 'blockquote'];
    $drop = ['script', 'style', 'iframe', 'object', 'embed', 'img', 'svg', 'math', 'form', 'input', 'textarea', 'button', 'link', 'meta', 'video', 'audio'];
    $doc = new DOMDocument();
    $prev = libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="UTF-8"><div id="nhc-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);
    $root = null;
    foreach ($doc->getElementsByTagName('div') as $d) {
        if ($d->getAttribute('id') === 'nhc-root') { $root = $d; break; }
    }
    if (!$root) return '';

    $walk = function (DOMNode $node) use (&$walk, $allowed, $drop) {
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $child = $node->childNodes->item($i);
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);
                if (in_array($tag, $drop, true)) {
                    $node->removeChild($child);
                    continue;
                }
                $walk($child);
                if (!in_array($tag, $allowed, true)) {
                    // hindi kilalang tag → iiwan ang laman, aalisin ang tag
                    while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                    $node->removeChild($child);
                    continue;
                }
                // alignment / indent na klase lang ang pinapanatili
                $keep = [];
                foreach (preg_split('/\s+/', (string) $child->getAttribute('class')) as $cls) {
                    if (preg_match('/^ql-(align-(center|right|justify)|indent-[1-8])$/', $cls)) $keep[] = $cls;
                }
                while ($child->attributes->length) $child->removeAttribute($child->attributes->item(0)->nodeName);
                if ($keep) $child->setAttribute('class', implode(' ', $keep));
            } elseif ($child->nodeType === XML_COMMENT_NODE || $child->nodeType === XML_PI_NODE) {
                $node->removeChild($child);
            }
        }
    };
    $walk($root);

    $out = '';
    foreach ($root->childNodes as $n) $out .= $doc->saveHTML($n);
    $plain = trim(html_entity_decode(strip_tags($out), ENT_QUOTES, 'UTF-8'));
    return $plain === '' ? '' : $out;
}

/** Laman ng contract na may value na ang mga [[field]]. */
function nhcFillBody(array $c): string
{
    $body = nhcSanitizeBody((string) (($c['body_html'] ?? '') ?: nhcDefaultBody()));
    $vals = nhcPlaceholderValues($c);
    return preg_replace_callback('/\[\[([a-z_]+)\]\]/', function ($m) use ($vals) {
        if (!isset($vals[$m[1]])) return $m[0];
        [$text, $fill] = $vals[$m[1]];
        $t = nhcEsc($text);
        return ($fill && $text !== '') ? '<span class="nhc-fill">' . $t . '</span>' : $t;
    }, $body);
}

/**
 * Buong contract (HTML): letterhead + laman (ini-edit ng Accounting Head) + pirmahan (automatic).
 * $c ay row ng noblecrm_contract (+ names galing nhcLoad).
 */
function nhcRenderContract(array $c, array $img, bool $repeatHeader = false): string
{
    $e = 'nhcEsc';
    $amount = nhcMoney($c['contract_amount']);
    $dp = nhcMoney(nhcDpAmount($c));
    $paid = !empty($c['dp_paid']);

    $h = '<div class="nhc-doc">';
    $h .= $repeatHeader ? '' : nhcHeaderHtml($img); // PDF: naka-fixed ang header sa bawat page (nhcPdfHtml)
    $h .= '<div class="nhc-body">' . nhcFillBody($c) . '</div>';

    // ── Pirmahan (galing sa system, hindi ini-edit) ──
    $h .= '<div style="page-break-inside: avoid">';
    $h .= '<div class="nhc-art" style="text-align:center;margin-top:14pt">Client Sales Contact</div>';
    $clientSig = ($img['client'] ?? '') !== '' ? '<img src="' . $img['client'] . '" alt="client signature" style="max-height:52pt;max-width:200pt">' : '<span class="nhc-pending">Waiting for client signature</span>';
    $h .= '<table style="width:100%;border-collapse:collapse;margin:6pt 0 8pt"><tr>'
        . '<td style="width:62%;vertical-align:bottom;padding-right:14pt"><div style="font-size:10pt">CLIENT NAME AND SIGNATURE:</div><div class="nhc-sigbox">' . $clientSig . '</div>'
        . '<div class="nhc-signame">' . $e($c['client_signed_name'] ?? '') . '&nbsp;</div></td>'
        . '<td style="vertical-align:bottom"><div style="font-size:10pt">DATE:</div><div class="nhc-sigbox"></div><div class="nhc-signame">' . $e(!empty($c['client_signed_at']) ? nhcDateLong($c['client_signed_at']) : '') . '&nbsp;</div></td>'
        . '</tr></table>';

    $h .= '<table class="nhc-info" style="border-collapse:collapse">'
        . '<tr><td class="k">ADDRESS:</td><td>' . $e(strtoupper((string) ($c['address'] ?? ''))) . '</td></tr>'
        . '<tr><td class="k">CONTACT NO:</td><td>' . $e($c['contact_number'] ?? '') . '</td></tr>'
        . '<tr><td class="k">DOWNPAYMENT:</td><td>PHP ' . $e($dp) . ($paid ? ' <i>(PAID)</i>' : '') . '</td></tr>'
        . '<tr><td class="k">TOTAL CONTRACT AMOUNT:</td><td>PHP ' . $e($amount) . '</td></tr>'
        . '<tr><td class="k">BALANCE:</td><td>PHP ' . $e(nhcMoney(nhcBalance($c))) . '</td></tr>'
        . '</table>';

    $h .= '<div class="nhc-art" style="margin-top:14pt">Signed by:</div>';
    $h .= '<table class="nhc-sign"><tr>'
        . '<td>' . nhcSignCell($img['sales'] ?? '', $c['sales_name'] ?? null, 'Sales Coordinator', $c['sales_signed_at'] ?? null, 'Waiting for Sales') . '</td>'
        . '<td>' . nhcSignCell($img['designer'] ?? '', $c['designer_name'] ?? null, 'Designer', $c['designer_signed_at'] ?? null, 'Waiting for Designer') . '</td>'
        . '</tr><tr>'
        . '<td>' . nhcSignCell($img['acct'] ?? '', $c['acct_name'] ?? null, 'Accounting Head', $c['acct_signed_at'] ?? null, 'Waiting for Accounting Head') . '</td>'
        . '<td>' . nhcSignCell($img['gm'] ?? '', $c['gm_name'] ?? null, 'General Manager', $c['gm_signed_at'] ?? null, 'Waiting for General Manager') . '</td>'
        . '</tr></table>';
    $h .= '</div>';

    $h .= '</div>';
    return $h;
}