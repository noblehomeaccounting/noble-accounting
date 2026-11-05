<?php
// push-include.php — ilalagay ang push script sa page
//   Admin: sa dulo ng admin/navigation/sidebar.php   →  $pushType = 'admin'; include ROOT_PATH . '/network/push/push-include.php';
//   User:  sa dulo ng user/navigation/top.php         →  $pushType = 'user';  include ROOT_PATH . '/network/push/push-include.php';
include_once ROOT_PATH . '/network/push/push-config.php';

// Kapag naka-off, may HTML comment na nagsasabi kung bakit (View Page Source → hanapin "NH push")
$nhPushOff = str_contains(NH_PUSH_PUBLIC_KEY, 'PASTE-') ? 'VAPID keys not set in network/push/push-config.php'
    : (!is_file(ROOT_PATH . '/vendor/autoload.php') ? 'vendor/autoload.php not found (run composer install / upload vendor)'
    : (!is_dir(ROOT_PATH . '/vendor/minishlink/web-push') ? 'vendor/minishlink/web-push not found (composer require minishlink/web-push)'
    : ((int) ($_SESSION['account_id'] ?? 0) <= 0 ? 'not logged in (no account_id in session)' : '')));
if ($nhPushOff !== '') {
    echo "\n<!-- NH push off: {$nhPushOff} -->\n";
    // para may masabi pa rin ang "Phone notifications" button sa sidebar / dropdown
    echo '<script>window.NHPush = window.NHPush || { panel: function () { var m = ' . json_encode('Notifications are not set up on the server yet: ' . $nhPushOff) . ';'
       . ' var b = document.createElement("div"); b.textContent = m; b.style.cssText = "position:fixed;left:16px;bottom:16px;z-index:10051;max-width:360px;padding:12px 14px;border-radius:10px;font-size:13px;background:#fef2f2;color:#b91c1c;border:1px solid #fecaca;box-shadow:0 8px 24px rgba(0,0,0,.15)";'
       . ' document.body.appendChild(b); setTimeout(function () { b.remove(); }, 7000); } };</script>' . "\n";
    return;
}
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
<?php $nhPushVer = @filemtime(ROOT_PATH . '/js/push-client.js') ?: time();   // nagbabago kapag pinalitan ang file → walang lumang cache ?>
<script src="<?= BASE_URL ?>/js/push-client.js?v=<?= $nhPushVer ?>" defer></script>