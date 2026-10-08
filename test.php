<?php
// seed_pricelist.php  (run once, then delete)
define('ROOT_PATH', __DIR__);
$_SERVER['HTTP_HOST'] = 'localhost';   // palitan kung production ang seseedan
require ROOT_PATH . '/network/connect.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if ($conn->query("SELECT COUNT(*) c FROM noblecrm_quotationpricelist_rows")->fetch_assoc()['c'] > 0) {
    die("Already seeded.\n");
}

$W = ['PARTICLE_BOARD', 'MARINE_BOARD', 'PET', 'DUCO', 'ALUMINUM'];
$K = ['PARTICLE_BOARD', 'MARINE_BOARD', 'ALUMINUM'];

$seed = [
    'WARDROBE' => [$W, [
        ['Swing Door & Awning', [
            ['Melamine Particle Finish',    null, null, [11880, 13365, 14850, 16335, 18392]],
            ['Melamine Marine Finish',      null, null, [13365, 14850, 16335, 17820, 20064]],
            ['PET Finish',                  null, null, [14850, 16335, 17820, 19305, 21736]],
            ['Duco Finish',                 null, null, [16335, 17820, 19305, 20048, 22572]],
            ['Glass in Wood Frame Profile', null, null, [17820, 19305, 20048, 21533, 24244]],
            ['Glass in Alum. Frame Profile',null, null, [19305, 20048, 21533, 23018, 25916]],
        ]],
        ['Sliding Door', [
            ['Melamine Particle Finish',    null, null, [13365, 14850, 16335, 17820, 20064]],
            ['Melamine Marine Finish',      null, null, [14850, 16335, 17820, 19305, 21736]],
            ['PET Finish',                  null, null, [16335, 17820, 19305, 20048, 22572]],
            ['Duco Finish',                 null, null, [17820, 19305, 20048, 21533, 24244]],
            ['Glass in Wood Frame Profile', null, null, [19305, 20048, 21533, 23018, 25916]],
            ['Glass in Alum. Frame Profile',null, null, [20048, 21533, 23018, 24503, 27588]],
        ]],
    ]],
    'KITCHEN' => [$K, [
        ['Particle Melamine Finish', [
            ['Open Cabinet',       null, null, [8800, 9350, 12540]],
            ['Wall Cabinet',       350,  800,  [9900, 10450, 13376]],
            ['Curve Wall Cabinet', 350,  800,  [12870, 13585, 14212]],
            ['Base Cabinet',       570,  700,  [10450, 11000, 15048]],
            ['Curve Base Cabinet', 570,  700,  [13585, 14300, 16720]],
            ['Tall Cabinet',       600,  2100, [13750, 14300, 18392]],
        ]],
        ['Marine Melamine Finish', [
            ['Open Cabinet',       null, null, [9350, 9900, 14212]],
            ['Wall Cabinet',       350,  800,  [10450, 11000, 15048]],
            ['Curve Wall Cabinet', 350,  800,  [13585, 14300, 15884]],
            ['Base Cabinet',       570,  700,  [11550, 12100, 16335]],
            ['Curve Base Cabinet', 570,  700,  [15015, 15730, 17243]],
            ['Tall Cabinet',       600,  2100, [14300, 14850, 19965]],
        ]],
        ['PET High Gloss Finish', [
            ['Open Cabinet', null, null, [10450, 11000, 15048]],
            ['Wall Cabinet', 350,  800,  [11550, 12100, 18392]],
            ['Base Cabinet', 570,  700,  [12705, 13310, 19965]],
            ['Tall Cabinet', 600,  2100, [15593, 16335, 21780]],
        ]],
        ['Duco Finish', [
            ['Open Cabinet', null, null, [11550, 12100, 15884]],
            ['Wall Cabinet', 350,  800,  [12650, 13200, 19228]],
            ['Base Cabinet', 570,  700,  [13915, 14520, 20873]],
            ['Tall Cabinet', 600,  2100, [17078, 17820, 22688]],
        ]],
        ['Clear Glass Doors with Frame (Silver/Black/Gold)', [
            ['Wall Cabinet', 350, 800,  [13200, 14300, 21736]],
            ['Base Cabinet', 570, 700,  [14520, 15730, 23595]],
            ['Tall Cabinet', 600, 2100, [17820, 19305, 25410]],
        ]],
        ['Tinted Glass Doors with Frame (Silver/Black/Gold)', [
            ['Wall Cabinet', 350, 800,  [13750, 14850, 22572]],
            ['Base Cabinet', 570, 700,  [15125, 16335, 24503]],
            ['Tall Cabinet', 600, 2100, [18563, 20048, 27225]],
        ]],
        ['Aluminum Melamine Finish', [
            ['Wall Cabinet', 350, 800,  [13200, 15048, 16720]],
            ['Base Cabinet', 570, 700,  [14520, 16335, 18150]],
            ['Tall Cabinet', 600, 2100, [17820, 18150, 21780]],
        ]],
    ]],
];

$insRow = $conn->prepare("INSERT INTO noblecrm_quotationpricelist_rows
    (product_category, group_name, row_name, max_depth, max_height, sort_order) VALUES (?,?,?,?,?,?)");
$insPrice = $conn->prepare("INSERT INTO noblecrm_quotationpricelist (row_id, carcass_code, price) VALUES (?,?,?)");

$sort = 0; $nRows = 0; $nPrices = 0;
foreach ($seed as $cat => [$codes, $groups]) {
    foreach ($groups as [$group, $rows]) {
        foreach ($rows as [$name, $d, $h, $prices]) {
            $sort += 10;
            $insRow->bind_param('sssiii', $cat, $group, $name, $d, $h, $sort);
            $insRow->execute();
            $rid = $conn->insert_id; $nRows++;
            foreach ($codes as $i => $code) {
                $p = $prices[$i];
                $insPrice->bind_param('isd', $rid, $code, $p);
                $insPrice->execute(); $nPrices++;
            }
        }
    }
}
echo "Done: $nRows rows, $nPrices prices.\n";   // dapat 41 rows, 147 prices