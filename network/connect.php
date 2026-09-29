<?php
// connect.php

require_once ROOT_PATH . '/vendor/autoload.php';
Dotenv\Dotenv::createImmutable(ROOT_PATH)->safeLoad();

$isLocal = in_array($_SERVER['HTTP_HOST'] ?? '', ['localhost', 'localhost:8000', '127.0.0.1', '127.0.0.1:8000']);
$suffix  = $isLocal ? 'LOCAL' : 'PROD';

$host     = $_ENV["DB_HOST_$suffix"]     ?? '';
$username = $_ENV["DB_USER_$suffix"]     ?? '';
$password = $_ENV["DB_PASSWORD_$suffix"] ?? '';
$database = $_ENV["DB_NAME_$suffix"]     ?? '';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    error_log("DB connection failed: " . $conn->connect_error);
    http_response_code(500);
    die("Database connection failed");
}

$conn->set_charset("utf8mb4");
$conn->query("SET time_zone = '+08:00'");