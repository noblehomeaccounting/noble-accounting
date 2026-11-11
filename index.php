<?php
// index.php
define('ROOT_PATH', __DIR__);

require_once ROOT_PATH . '/vendor/autoload.php';

// ─── Load .env ────────────────────────────────────────────────────────────────
$envFile = ROOT_PATH . '/.env';

if (!file_exists($envFile)) {
    die('.env file not found.');
}

foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    $line = trim($line);
    if ($line === '' || str_starts_with($line, '#'))
        continue;  // skip comments/blanks
    if (!str_contains($line, '='))
        continue;                    // skip invalid lines

    [$key, $value] = explode('=', $line, 2);
    $_ENV[trim($key)] = trim($value);
}

// ─── Constants ────────────────────────────────────────────────────────────────
define('GOOGLE_CLIENT_ID', $_ENV['GOOGLE_CLIENT_ID'] ?? '');
define('GOOGLE_CLIENT_SECRET', $_ENV['GOOGLE_CLIENT_SECRET'] ?? '');

// Cloudflare Turnstile (login security check) — keys galing sa .env
define('TURNSTILE_SITE_KEY', $_ENV['TURNSTILE_SITE_KEY'] ?? '');
define('TURNSTILE_SECRET_KEY', $_ENV['TURNSTILE_SECRET_KEY'] ?? '');

// ─── Base URL ─────────────────────────────────────────────────────────────────
$isLocalhost = (
    strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ||
    strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false
);

define(
    'BASE_URL',
    $isLocalhost
    ? 'http://localhost/nobleaccounting'
    : $_ENV['APP_URL']
);


// ─── Routing ──────────────────────────────────────────────────────────────────
$request = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$request = trim($request, '/');
$request = preg_replace('#^nobleaccounting/?#', '', $request);
$request = trim($request, '/');

if ($request === '' || $request === 'home') {
    $request = 'home';
}

// ─── Routes (nakahiwalay sa 2 file) ─────────────────────────────────────────────
//   routes/admin-routes.php → admin pages at ajax   (session: nobleadmin)
//   routes/user-routes.php  → user portal at public (session: nobleuser)
// Bagong route? Idagdag lang sa tamang file. Ang file na kinalalagyan nito ang
// nagsasabi kung aling session ang gagamitin.
$adminRoutes = require ROOT_PATH . '/routes/admin-routes.php';
$userRoutes  = require ROOT_PATH . '/routes/user-routes.php';

$isAdminRoute = isset($adminRoutes[$request]);

session_name($isAdminRoute ? 'nobleadmin' : 'nobleuser');

// ─── Session cookie security ─────────────────────────────────────────────────
// httponly → hindi mababasa ng JavaScript ang session cookie (iwas session theft)
// secure   → sa HTTPS lang ipapadala (live); naka-off sa localhost para gumana sa XAMPP
// samesite → Lax: hindi ipapadala kapag galing sa ibang site ang form/request,
//            pero gumagana pa rin ang Google login at mga link mula sa email/chat
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

session_set_cookie_params([
    'lifetime' => 0,          // mawawala kapag isinara ang browser (gaya ng dati)
    'path'     => '/',
    'secure'   => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
ini_set('session.use_strict_mode', '1');   // tanggihan ang gawa-gawang session ID

session_start();

// ─── Dispatch ─────────────────────────────────────────────────────────────────
$file = $isAdminRoute ? $adminRoutes[$request] : ($userRoutes[$request] ?? null);

if ($file === null) {
    http_response_code(404);
    include ROOT_PATH . '/404.php';
    exit;
}

$filepath = ROOT_PATH . '/' . $file;

if (file_exists($filepath)) {
    include $filepath;
} else {
    http_response_code(404);
    include ROOT_PATH . '/404.php';
    exit;
}