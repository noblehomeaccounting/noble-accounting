<?php
// purchaseordermain.php — Sales: gumawa at i-submit ang Purchase Order (Prepared by)
// Daloy: Sales → Accounting Head (Acknowledged by) → Superadmin (Approved by)
//        → Accounting Staff (Received by) → Custodian / Custodian Assistant (Released by)

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';

$allowedRoles = [ROLE_SALES];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$spoMode = 'sales';
include ROOT_PATH . '/admin/crm-management/purchaseorder/salespo-page.php';