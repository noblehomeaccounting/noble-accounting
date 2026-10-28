<?php
// sitevisitform.php
// Designer: Site Visit Record. Ang mga bahagi ay nasa sitevisit/ folder:
//   sv-helpers.php, sv-save.php, sv-header.php, sv-done.php, sv-form.php,
//   step1-visit.php … step5-review.php, sv-whole-view.php, sv-script.php, sv-script-form.php (+ js/)

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_DESIGNER];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';


$svError = '';
$svFormError = '';          // error sa form mismo (ipinapakita sa taas ng form, hindi tinatago ang page)
$svSuccess = false;
$svOld = [];                // para hindi mawala ang tinype kapag may error
$currentDesignerId = intval($_SESSION['account_id'] ?? 0);
// NOTE: adjust this to whatever session key actually holds the designer's display name.
$currentDesignerName = $_SESSION['account_name'] ?? $_SESSION['fullname'] ?? ('Designer #' . $currentDesignerId);

$inquiryId = intval($_GET['id'] ?? $_POST['inquiry_id'] ?? 0);
$inquiry = null;

if ($inquiryId <= 0) {
    $svError = 'Missing or invalid inquiry reference.';
} else {
    // Fetch the inquiry, making sure it belongs to the logged-in designer
    // ("mode" is included so we can skip this page entirely when the client
    // already provided a 2D — see redirect guard below).
    $stmt = $conn->prepare("
        SELECT id, control_no, client_name, address, contact_number, status, deadline, mode, measuring_space
        FROM noblecrminquiry
        WHERE id = ? AND designer_id = ?
        LIMIT 1
    ");
    $stmt->bind_param("ii", $inquiryId, $currentDesignerId);
    $stmt->execute();
    $inquiry = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$inquiry) {
        $svError = 'Inquiry not found, or not assigned to you.';
    }
}

if (empty($svError) && $inquiry && ($inquiry['mode'] ?? 'site_visit') === 'ready_for_quotation') {
    header('Location: ' . BASE_URL . '/crm2dquotation?id=' . $inquiryId);
    exit;
}

// When this form is opened, mark the related notification as read
if (empty($svError) && $inquiryId > 0) {
    $notifStmt = $conn->prepare("
        UPDATE noblenotification SET is_read = 1
        WHERE request_id = ? AND user_id = ?
    ");
    $notifStmt->bind_param("ii", $inquiryId, $currentDesignerId);
    $notifStmt->execute();
    $notifStmt->close();
}

require_once __DIR__ . '/sitevisit/sv-helpers.php';

// Pag-save ng bagong record (validation, upload, insert)
require __DIR__ . '/sitevisit/sv-save.php';

if (!empty($_SESSION['sv_flash_success'])) {
    $svSuccess = $_SESSION['sv_flash_success'];
    unset($_SESSION['sv_flash_success']);
}

$svHistory = [];
if (empty($svError) && $inquiryId > 0) {
    $histStmt = $conn->prepare("
        SELECT id, address, visit_datetime, visited, photos, measurement_files,
               measurements, site_conditions, client_requirements, existing_structure,
               created_at
        FROM noblecrm_sitevisit
        WHERE inquiry_id = ? AND designer_id = ?
        ORDER BY created_at DESC
    ");
    $histStmt->bind_param("ii", $inquiryId, $currentDesignerId);
    $histStmt->execute();
    $svHistory = $histStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $histStmt->close();
}

// Spaces ng pinakabagong record (wala ito sa mga lumang record → lumang "Measurements" view)
$svSpaces = [];
if (!empty($svHistory)) {
    $spStmt = $conn->prepare("
        SELECT id, space_name, requirements, notes, measurement_files, photos
        FROM noblecrm_sitevisit_space
        WHERE sitevisit_id = ?
        ORDER BY sort_order ASC, id ASC
    ");
    if ($spStmt) { // hindi pa na-run ang SQL → tahimik na lumang view
        $latestId = (int) $svHistory[0]['id'];
        $spStmt->bind_param("i", $latestId);
        $spStmt->execute();
        $svSpaces = $spStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $spStmt->close();
    }
}

// Scope Area na pinili ni Sales sa inquiry → mabilisang "add space"
$svInquirySpaces = array_values(array_unique(array_filter(array_map(
    fn($x) => trim(preg_replace('/\s+/u', ' ', $x)),
    preg_split('/[,;\n]+/', (string) ($inquiry['measuring_space'] ?? ''))
))));
$svSpaceSuggest = ['Kitchen', 'Living Area', 'Dining Area', "Master's Bedroom", 'Bedroom', 'Kids Bedroom', 'Toilet & Bath', 'Walk-in Closet', 'Laundry Area', 'Study / Office', 'Entertainment Area', 'Hallway', 'Stairs', 'Balcony', 'Garage', 'Exterior / Facade'];
$svCsv = fn(?string $csv) => array_values(array_filter(array_map('trim', explode(',', (string) $csv))));

$crmDesignerListUrl = BASE_URL . '/crmdesigner';
$crm2dQuotationUrl = BASE_URL . '/crm2dquotation?id=' . $inquiryId;

$svAlreadyDone = !empty($svHistory);
$svOldVal = fn($k) => htmlspecialchars($svOld[$k] ?? '');

$svToneText = ['amber' => 'text-amber-700', 'red' => 'text-red-600', 'gray' => 'text-gray-500'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Visit Record</title>
    <?php include ROOT_PATH . '/link/top.php'; ?>
    <?php include ROOT_PATH . '/admin/navigation/sidebar.php'; ?>
    <link rel="stylesheet" href="<?= BASE_URL ?>/admin/crm-management/css/crm/crmsitevisit.css?v=<?= @filemtime(ROOT_PATH . '/admin/crm-management/css/crm/crmsitevisit.css') ?: 1 ?>">
</head>

<body class="bg-gray-50 font-['Barlow_Condensed']">
    <main class="md:ml-56 min-h-screen px-4 pb-6 pt-20 md:p-7 sv-scope bg-[#F5F6FA] overflow-x-hidden">

        <div class="max-w-6xl mx-auto">

            <?php if (!empty($svError)): ?>

                <div class="sv-card overflow-hidden">
                    <div class="sv-hazard h-1.5"></div>
                    <div class="px-6 py-10 text-center">
                        <span class="w-12 h-12 mx-auto rounded-xl bg-red-50 text-red-500 flex items-center justify-center mb-3"><i class="fa-solid fa-triangle-exclamation text-lg"></i></span>
                        <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($svError) ?></p>
                        <a href="<?= htmlspecialchars($crmDesignerListUrl) ?>"
                            class="mt-4 inline-flex items-center gap-2 text-xs font-semibold text-white bg-amber-700 hover:bg-amber-800 rounded-lg px-4 py-2">
                            <i class="fa-solid fa-arrow-left"></i> Back to assigned list
                        </a>
                    </div>
                </div>

            <?php elseif ($inquiry): ?>

                <?php
                $dl = svDaysLabel($inquiry['deadline'] ?? null);
                $latestVisit = $svAlreadyDone ? $svHistory[0] : null;
                $latestVisited = $latestVisit && $latestVisit['visited'] === 'yes';
                $stepState = $svAlreadyDone ? ($latestVisited ? 2 : 1) : 1; // 0 assigned, 1 site visit, 2 2D & quotation
                ?>

                <?php include __DIR__ . '/sitevisit/sv-header.php'; ?>

                <?php if ($svAlreadyDone): ?>
                    <?php include __DIR__ . '/sitevisit/sv-done.php'; ?>
                <?php else: ?>

                    <?php include __DIR__ . '/sitevisit/sv-form.php'; ?>

                <?php endif; ?>

            <?php endif; ?>

        </div>

        <?php if (!$svAlreadyDone && !empty($inquiry) && empty($svError)) include __DIR__ . '/sitevisit/sv-whole-view.php'; ?>

        <!-- Toast container: bottom-right -->
        <div id="crmToastContainer"
            class="fixed bottom-6 right-6 z-[9999] flex flex-col gap-2.5 pointer-events-none w-full max-w-sm px-4 sm:px-0">
        </div>

        <?php include __DIR__ . '/sitevisit/sv-script.php'; ?>
        <?php if (!$svAlreadyDone && !empty($inquiry) && empty($svError)) include __DIR__ . '/sitevisit/sv-script-form.php'; ?>

    </main>
</body>

</html>