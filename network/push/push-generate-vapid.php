<?php
// push-generate-vapid.php — patakbuhin ISANG BESES sa terminal (hindi sa browser):
//   cd C:\xampp\htdocs\nobleaccounting
//   php network/push/push-generate-vapid.php
// Kopyahin ang lalabas sa network/push/push-config.php

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Run this from the command line.'); }
// (CLI lang ito, kaya walang ROOT_PATH galing sa index.php)
define('ROOT_PATH', dirname(__DIR__, 2));
require ROOT_PATH . '/vendor/autoload.php';

$keys = Minishlink\WebPush\VAPID::createVapidKeys();
echo "NH_PUSH_PUBLIC_KEY  = {$keys['publicKey']}\n";
echo "NH_PUSH_PRIVATE_KEY = {$keys['privateKey']}\n";