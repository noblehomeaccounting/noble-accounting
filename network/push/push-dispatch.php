<?php
// push-dispatch.php — Cron (opsyonal, pang-salo): ipinapadala ang notification na walang Supabase doorbell
//   Hostinger hPanel → Advanced → Cron Jobs → PHP, every 5 minutes:
//   /usr/bin/php /home/<user>/domains/financenoblehome.com/public_html/network/push/push-dispatch.php
//   Test sa XAMPP:  php network/push/push-dispatch.php

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Run this from the command line / cron.'); }

// (CLI ito, walang index.php, kaya dito itinatakda ang ROOT_PATH at BASE_URL)
if (!defined('ROOT_PATH')) define('ROOT_PATH', dirname(__DIR__, 2));
if (!defined('BASE_URL'))  define('BASE_URL', 'https://financenoblehome.com');
$_SERVER['HTTP_HOST']   = $_SERVER['HTTP_HOST']   ?? parse_url(BASE_URL, PHP_URL_HOST);
$_SERVER['SERVER_NAME'] = $_SERVER['SERVER_NAME'] ?? parse_url(BASE_URL, PHP_URL_HOST);

include ROOT_PATH . '/network/connect.php';
include_once ROOT_PATH . '/network/push/push-dispatch-lib.php';

echo date('Y-m-d H:i:s') . ' sent ' . nhPushDispatch($conn, 100) . " push(es)\n";