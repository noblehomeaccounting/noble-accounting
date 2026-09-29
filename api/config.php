<?php
// api/config.php
require_once ROOT_PATH . '/network/connect.php';

header('Content-Type: application/json; charset=utf-8');

function respond($data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data);
    exit;
}

$key = $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($key === '' && function_exists('getallheaders')) {
    foreach (getallheaders() as $name => $value) {
        if (strtolower($name) === 'x-api-key') {
            $key = $value;
        }
    }
}

if (empty($_ENV['API_KEY']) || !hash_equals($_ENV['API_KEY'], $key)) {
    respond(['error' => 'Unauthorized'], 401);
}