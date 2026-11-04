<?php
// salespoapproval.php (route: salespoapproval) — pirmahan ng Purchase Order galing Sales
//   Accounting Head → Acknowledged by · Superadmin → Approved by
//   Accounting Staff → Received by · Custodian / Custodian Assistant → Released by

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';

$allowedRoles = [ROLE_ACCOUNTING, ROLE_SUPERADMIN];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

// Accounting: Head, Staff, Custodian at Custodian Assistant lang
$spoPosCheck = $_SESSION['position'] ?? '';
if (($_SESSION['role'] ?? '') === ROLE_ACCOUNTING
    && !in_array($spoPosCheck, [POSITION_HEAD, POSITION_STAFF, POSITION_CUSTODIAN, POSITION_CUSTOASSISTANT], true)) {
    http_response_code(403);
    exit('Access denied.');
}

$spoMode = 'approver';

include ROOT_PATH . '/admin/crm-management/purchaseorder/salespo-page.php';