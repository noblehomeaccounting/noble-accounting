<?php
// fetchannouncements.php

include ROOT_PATH . '/network/connect.php';
header('Content-Type: application/json');

// Official employee ba ang naka-login? (same logic as user navigation top.php)
$isOfficial = false;
$accountId  = intval($_SESSION['account_id'] ?? 0);

if ($accountId) {
    try {
        if ($stmt = $conn->prepare("SELECT is_official FROM nobleid WHERE account_id = ? LIMIT 1")) {
            $stmt->bind_param("i", $accountId);
            $stmt->execute();
            $stmt->bind_result($offVal);
            if ($stmt->fetch()) {
                $isOfficial = ((int) $offVal === 1);
            }
            $stmt->close();
        }
    } catch (Throwable $e) {
        // kung may error, treat as non-official (safe default)
        $isOfficial = false;
    }
}

// Template 4 = Payroll Announcement -> official employees lang
$payrollFilter = $isOfficial ? '' : 'AND a.template <> 4';

$result = $conn->query("
    SELECT a.*, n.name AS posted_by_name
    FROM nobleannouncement a
    LEFT JOIN noblerole n ON n.id = a.posted_by
    WHERE a.is_active = 1
    $payrollFilter
    ORDER BY a.created_at DESC
    LIMIT 10
");

$rows = $result->fetch_all(MYSQLI_ASSOC);
echo json_encode($rows);