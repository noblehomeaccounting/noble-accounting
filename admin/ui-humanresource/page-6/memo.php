<?php
// memo.php (route: memo) — HR: gumawa / mag-verify ng Memorandum
//   HR Assistant → Prepared by · HR Head → Verified by

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_HR];
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

$memoMode = 'hr';
include ROOT_PATH . '/admin/ui-humanresource/page-6/memo-page.php';