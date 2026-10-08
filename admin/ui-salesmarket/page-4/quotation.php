<?php
// quotation.php  -  Quotation list (create / open / delete). The builder is in quotationbuilder.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SALES];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

include ROOT_PATH . '/admin/ui-salesmarket/page-4/quotation-helpers.php';

$selfUrl = strtok($_SERVER['REQUEST_URI'], '?');

/* ------------------------------------------------------------------
   HANDLE POST
------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrf, $_POST['csrf'] ?? '')) {
        http_response_code(403);
        exit('Invalid request. Refresh the page and try again.');
    }

    $action = $_POST['action'] ?? '';
    $pq = (int) ($_POST['quotation_id'] ?? 0);
    $redirect = $selfUrl;
    $msg = 'Saved.';
    $afterCommit = [];   // image files to delete once the transaction succeeds

    try {
        $conn->begin_transaction();

        $quoteRow = $pq ? qOne($conn, 'SELECT * FROM noblecrm_quotations WHERE id = ?', 'i', [$pq]) : null;
        if ($action !== 'create_quotation' && !$quoteRow) {
            throw new QuoteError('Quotation not found.');
        }

        switch ($action) {

            case 'create_quotation': {
                $date = $_POST['quote_date'] ?? '';
                if (!DateTime::createFromFormat('Y-m-d', $date))
                    $date = date('Y-m-d');

                // Client and project come from the CRM list (noblecrminquiry), not typed in.
                $inqId = (int) ($_POST['inquiry_id'] ?? 0);
                $inq = qOne(
                    $conn,
                    'SELECT * FROM noblecrminquiry WHERE id = ? AND sales_staff_id = ?',
                    'ii',
                    [$inqId, (int) ($_SESSION['account_id'] ?? 0)]
                );
                if (!$inq)
                    throw new QuoteError('Pick a client from the CRM list.');
                if (!in_array($inq['status'], ['In Progress', 'Approved', 'For Revision'], true)) {
                    throw new QuoteError('The site visit must be completed before making a quotation.');
                }
                if (qOne($conn, 'SELECT id FROM noblecrm_quotations WHERE inquiry_id = ?', 'i', [$inqId])) {
                    throw new QuoteError('This client already has a quotation.');
                }
                $client = (string) $inq['client_name'];
                $project = (string) ($inq['project_type'] ?? '');
                $loc = (string) ($inq['address'] ?? '');         // shown as LOCATION on the PDF
                $scope = (string) ($inq['project_scope'] ?? '');   // shown as SCOPE on the PDF

                // Default rates from settings: labor_rate_lm / labor_rate_sqm (falls back to the old labor_rate, then 1500)
                $set = array_column(
                    qRows($conn, "SELECT setting_key, setting_value FROM noblecrm_quotation_settings
                                                    WHERE setting_key IN ('labor_rate','labor_rate_lm','labor_rate_sqm')"),
                    'setting_value',
                    'setting_key'
                );
                $base = isset($set['labor_rate']) ? (float) $set['labor_rate'] : 1500.0;
                $rLm = isset($set['labor_rate_lm']) ? (float) $set['labor_rate_lm'] : $base;
                $rSqm = isset($set['labor_rate_sqm']) ? (float) $set['labor_rate_sqm'] : $base;
                qRun(
                    $conn,
                    'INSERT INTO noblecrm_quotations (inquiry_id, client_name, project_name, project_location, project_scope, quote_date, labor_rate, labor_rate_lm, labor_rate_sqm) VALUES (?,?,?,?,?,?,?,?,?)',
                    'isssssddd',
                    [$inqId, $client, $project, $loc, $scope, $date, $rLm, $rLm, $rSqm]
                );
                $redirect = $quoteBuilderUrl . '?id=' . $conn->insert_id;   // go straight to the builder
                $msg = 'Quotation created.';
                break;
            }

            case 'delete_quotation': {
                foreach (qRows($conn, 'SELECT image_path FROM noblecrm_quotation_sections WHERE quotation_id = ?', 'i', [$pq]) as $s) {
                    $afterCommit[] = $s['image_path'];
                }
                qRun($conn, 'DELETE FROM noblecrm_quotations WHERE id = ?', 'i', [$pq]);
                $redirect = $selfUrl;
                $msg = 'Quotation deleted.';
                break;
            }

            default:
                throw new QuoteError('Unknown action.');
        }

        $conn->commit();
        foreach ($afterCommit as $f)
            qDeleteImage($f);
        qFlash('ok', $msg);
        qGo($redirect);
    } catch (QuoteError $ex) {
        $conn->rollback();
        qFlash('err', $ex->getMessage());
        qGo($redirect);
    } catch (mysqli_sql_exception $ex) {
        $conn->rollback();
        error_log('quotation: ' . $ex->getMessage());
        qFlash('err', 'Database error. Please try again.');
        qGo($redirect);
    }
}

/* ------------------------------------------------------------------
   LOAD DATA
------------------------------------------------------------------- */
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// CRM inquiries of this sales user that have no quotation yet.
// Ones whose site visit is not done are listed too, but disabled in the dropdown.
$inquiryOptions = qRows($conn, 'SELECT i.id, i.control_no, i.client_name, i.project_type, i.status,
                                       (i.status IN (' . QUOTE_ALLOWED_STATUSES . ')) AS can_quote
                                  FROM noblecrminquiry i
                                  LEFT JOIN noblecrm_quotations q ON q.inquiry_id = i.id
                                 WHERE i.sales_staff_id = ? AND q.id IS NULL
                                 ORDER BY can_quote DESC, i.created_at DESC', 'i', [(int) ($_SESSION['account_id'] ?? 0)]);

// Existing quotations
$quotes = qRows($conn, 'SELECT q.*, COALESCE(SUM(i.material_total + i.labor_total), 0) AS total,
                                COUNT(DISTINCT s.id) AS section_count
                           FROM noblecrm_quotations q
                           LEFT JOIN noblecrm_quotation_sections s ON s.quotation_id = q.id
                           LEFT JOIN noblecrm_quotation_items i ON i.section_id = s.id
                          GROUP BY q.id ORDER BY q.id DESC');

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quotation</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
</head>

<body class="bg-slate-100">
    <main class="ml-56 min-h-screen p-8">

        <?php if ($flash): ?>
            <div role="status" class="mb-6 rounded-md border px-4 py-3 text-sm <?= $flash[0] === 'ok'
                ? 'border-emerald-300 bg-emerald-50 text-emerald-800'
                : 'border-red-300 bg-red-50 text-red-800' ?>">
                <?= e($flash[1]) ?>
            </div>
        <?php endif; ?>



        <div class="mb-6">
            <h1 class="text-2xl font-semibold text-slate-900">Quotations</h1>
            <p class="mt-1 text-sm text-slate-600">Create a quotation, then add sections, main items, and accessories.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-8 xl:grid-cols-[22rem_1fr]">
            <section class="self-start rounded-lg bg-white p-6 shadow-sm ring-1 ring-slate-200">
                <h2 class="mb-4 text-lg font-semibold text-slate-900">New quotation</h2>
                <form method="post" action="<?= e($selfUrl) ?>" class="space-y-4">
                    <?= qHidden('csrf', $csrf) ?><?= qHidden('action', 'create_quotation') ?>
                    <div>
                        <label for="inquiry_id" class="mb-1 block text-sm font-medium text-slate-700">Client (from CRM
                            list)</label>
                        <select id="inquiry_id" name="inquiry_id" required class="<?= $inputCls ?>">
                            <option value="">Select a client</option>
                            <?php foreach ($inquiryOptions as $o): ?>
                                <option value="<?= (int) $o['id'] ?>" <?= $o['can_quote'] ? '' : 'disabled' ?>>
                                    <?= e($o['control_no'] . ' - ' . $o['client_name'] . ($o['project_type'] ? ' (' . $o['project_type'] . ')' : '')
                                        . ($o['can_quote'] ? '' : ' - site visit not done yet')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!$inquiryOptions): ?>
                            <p class="mt-1 text-xs text-amber-800">No clients without a quotation yet.</p>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label for="quote_date" class="mb-1 block text-sm font-medium text-slate-700">Date</label>
                        <input id="quote_date" name="quote_date" type="date" value="<?= e(date('Y-m-d')) ?>"
                            class="<?= $inputCls ?>">
                    </div>
                    <button class="<?= $btnCls ?>">Create Quotation</button>
                </form>

                <div class="mt-6 flex flex-col gap-3 border-t border-slate-200 pt-4">
                    <a href="<?= e(rtrim(BASE_URL, '/')) ?>/quotationcrud"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300">
                        Manage Accessories
                    </a>

                    <a href="<?= e(rtrim(BASE_URL, '/')) ?>/quotationpricelist"
                        class="inline-flex w-full items-center justify-center gap-2 rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-800 hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-slate-300">
                        Price List Kitchen & Wardrobe
                    </a>
                </div>
            </section>

            <section class="min-w-0 rounded-lg bg-white shadow-sm ring-1 ring-slate-200">
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 text-slate-600">
                            <tr>
                                <th class="px-4 py-3 font-medium">Client</th>
                                <th class="px-4 py-3 font-medium">Project</th>
                                <th class="px-4 py-3 font-medium">Date</th>
                                <th class="px-4 py-3 text-right font-medium">Total</th>
                                <th class="px-4 py-3 text-right font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if (!$quotes): ?>
                                <tr>
                                    <td colspan="5" class="px-4 py-10 text-center text-slate-500">No quotations yet. Create
                                        one using the form on the left.</td>
                                </tr>
                            <?php endif; ?>
                            <?php foreach ($quotes as $q): ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3 text-slate-900"><?= e($q['client_name']) ?></td>
                                    <td class="px-4 py-3 text-slate-700"><?= e($q['project_name']) ?></td>
                                    <td class="px-4 py-3 text-slate-700"><?= e($q['quote_date']) ?></td>
                                    <td class="px-4 py-3 text-right tabular-nums text-slate-900"><?= peso($q['total']) ?>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-right">
                                        <a href="<?= e($quoteBuilderUrl) ?>?id=<?= (int) $q['id'] ?>"
                                            class="text-slate-700 underline hover:text-slate-900">Open</a>
                                        <span class="ml-3"><?php qPostBtn(
                                            'delete_quotation',
                                            ['quotation_id' => $q['id']],
                                            'Delete',
                                            'Delete this quotation and all its items? This cannot be undone.'
                                        ); ?></span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

    </main>
</body>

</html>