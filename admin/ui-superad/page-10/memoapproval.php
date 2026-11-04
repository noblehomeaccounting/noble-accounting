<?php
// memoapproval.php (route: memoapproval) — Superadmin: Approved by
//   Lalabas lang dito ang mga memo na pinili ng HR na "Needs Superadmin approval"

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SUPERADMIN];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$memoMode = 'approver';
include ROOT_PATH . '/admin/ui-humanresource/page-6/memo-page.php';