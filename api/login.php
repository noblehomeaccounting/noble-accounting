<?php
// api/login.php

require_once ROOT_PATH . '/network/connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}
header('Content-Type: application/json; charset=utf-8');

function respond($data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(['error' => 'Method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$email = trim($input['email'] ?? '');
$password = $input['password'] ?? '';

if ($email === '' || $password === '') {
    respond(['error' => 'Please enter your email and password.'], 422);
}
if (!str_ends_with(strtolower($email), '@noble.com')) {
    respond(['error' => 'Only @noble.com email addresses are allowed.'], 422);
}

$stmt = $conn->prepare("SELECT id, name, email, role, position, branch, password FROM noblerole WHERE email = ? LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$account = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$account || !password_verify($password, $account['password'])) {
    sleep(1); // pabagalin ang brute force
    respond(['error' => 'Invalid email or password.'], 401);
}

// Gumawa ng token (ang hash lang ang ise-save)
$token = bin2hex(random_bytes(32));
$hash = hash('sha256', $token);
$expires = date('Y-m-d H:i:s', strtotime('+7 days'));

$stmt = $conn->prepare("INSERT INTO api_tokens (account_id, token_hash, expires_at) VALUES (?, ?, ?)");
$stmt->bind_param('iss', $account['id'], $hash, $expires);
$stmt->execute();
$stmt->close();

respond([
    'token' => $token,
    'expires_at' => $expires,
    'user' => [
        'id' => (int) $account['id'],
        'name' => $account['name'],
        'email' => $account['email'],
        'role' => $account['role'],
        'position' => $account['position'],
        'branch' => $account['branch'],
    ],
]);