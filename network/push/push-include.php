<?php
// push-include.php — ilalagay ang push script sa page
//   Admin: sa dulo ng admin/navigation/sidebar.php   →  $pushType = 'admin'; include ROOT_PATH . '/network/push/push-include.php';
//   User:  sa dulo ng user/navigation/top.php         →  $pushType = 'user';  include ROOT_PATH . '/network/push/push-include.php';
include_once ROOT_PATH . '/network/push/push-config.php';
if (!nhPushEnabled() || (int) ($_SESSION['account_id'] ?? 0) <= 0) return;
$nhPushCfg = [
    'base'   => BASE_URL,
    'sw'     => BASE_URL . '/sw.js',
    'save'   => BASE_URL . ($pushType === 'user' ? '/pushsubscribeuser' : '/pushsubscribeadmin'),
    'key'    => NH_PUSH_PUBLIC_KEY,
    'type'   => $pushType,
    'uid'    => (int) $_SESSION['account_id'],
];
?>
<script>window.NH_PUSH_CFG = <?= json_encode($nhPushCfg, JSON_HEX_TAG | JSON_UNESCAPED_SLASHES) ?>;</script>
<script src="<?= BASE_URL ?>/js/push-client.js?v=1" defer></script>