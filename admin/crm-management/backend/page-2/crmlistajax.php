<?php
// crmlistajax.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

$allowedRoles = [ROLE_SALES];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

$currentSalesId = intval($_SESSION['account_id'] ?? 0);
$action = $_GET['action'] ?? ($_POST['action'] ?? 'list');

function crmListBaseQuery()
{
    return "
        SELECT
            i.id, i.control_no, i.client_name, i.address, i.project_type,
            i.project_scope, i.measuring_space, i.measurement_datetime,
            i.target_completion_date, i.statusdesignersitevisit, i.client_status,
            i.contact_number, i.contract_amount, i.branch, i.created_at,
            i.status, i.designer_id,
            d.name AS designer_name,
            s.name AS sales_name
        FROM noblecrminquiry i
        LEFT JOIN noblerole d ON d.id = i.designer_id
        LEFT JOIN noblerole s ON s.id = i.sales_staff_id
    ";
}

function crmListFormatRow($row)
{
    return [
        'id'                       => (int) $row['id'],
        'control_no'               => $row['control_no'],
        'client_name'              => $row['client_name'],
        'address'                  => $row['address'],
        'project_type'             => $row['project_type'],
        'project_scope'            => $row['project_scope'],
        'measuring_space'          => $row['measuring_space'],
        'measurement_datetime'     => $row['measurement_datetime'],
        'target_completion_date'   => $row['target_completion_date'],
        'statusdesignersitevisit'  => $row['statusdesignersitevisit'],
        'client_status'            => $row['client_status'],
        'contact_number'           => $row['contact_number'],
        'contract_amount'          => $row['contract_amount'],
        'branch'                   => $row['branch'],
        'status'                   => $row['status'] ?: 'Pending',
        'designer_id'              => (int) ($row['designer_id'] ?? 0),
        'designer_name'            => $row['designer_name'] ?? '—',
        'sales_name'               => $row['sales_name'] ?? '—',
        'created_at'               => $row['created_at'],
    ];
}

if ($action === 'list') {

    $search = trim($_GET['q'] ?? '');

    $sql = crmListBaseQuery() . " WHERE i.sales_staff_id = ? ";
    $types = "i";
    $params = [$currentSalesId];

    if ($search !== '') {
        $sql .= " AND (i.control_no LIKE ? OR i.client_name LIKE ? OR i.contact_number LIKE ?) ";
        $like = '%' . $search . '%';
        $types .= "sss";
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $sql .= " ORDER BY i.created_at DESC LIMIT 200 ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = crmListFormatRow($row);
    }
    $stmt->close();

    echo json_encode([
        'success' => true,
        'rows'    => $rows,
        'count'   => count($rows),
        'server_time' => date('c'),
    ]);
    exit;
}

if ($action === 'detail') {

    $id = intval($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid record.']);
        exit;
    }

    $sql = crmListBaseQuery() . " WHERE i.id = ? AND i.sales_staff_id = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $id, $currentSalesId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    if (!$row) {
        echo json_encode(['success' => false, 'message' => 'Record not found.']);
        exit;
    }

    echo json_encode([
        'success' => true,
        'record'  => crmListFormatRow($row),
    ]);
    exit;
}

// --- List of designers, para sa Schedule & Assign modal ---
if ($action === 'designers') {

    $designers = [];
    $result = $conn->query("
        SELECT id, name FROM noblerole
        WHERE role IN ('DESIGN DEPARTMENT')
        ORDER BY name ASC
    ");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $designers[] = $row;
        }
    }

    echo json_encode([
        'success'   => true,
        'designers' => $designers,
    ]);
    exit;
}

if ($action === 'assign') {

    $id = intval($_POST['id'] ?? 0);

    $clientStatus = trim($_POST['client_status'] ?? '');
    $allowedClientStatus = ['confirmed', 'tentative', 'no'];

    if ($id <= 0 || !in_array($clientStatus, $allowedClientStatus, true)) {
        echo json_encode(['success' => false, 'message' => 'Client status is required.']);
        exit;
    }

    $isPaused = in_array($clientStatus, ['tentative', 'no'], true);

    // Kumpirmahin na pag-aari ng kasalukuyang sales staff ang record na ito
    $checkStmt = $conn->prepare("
        SELECT client_name, control_no
        FROM noblecrminquiry
        WHERE id = ? AND sales_staff_id = ?
        LIMIT 1
    ");
    $checkStmt->bind_param("ii", $id, $currentSalesId);
    $checkStmt->execute();
    $inquiry = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if (!$inquiry) {
        echo json_encode(['success' => false, 'message' => 'Record not found.']);
        exit;
    }

    // --- Tentative / Not Proceeding: client_status lang ang i-uupdate,
    //     walang designer, walang schedule, walang notification. ---
    if ($isPaused) {
        $stmt = $conn->prepare("
            UPDATE noblecrminquiry
            SET client_status = ?
            WHERE id = ? AND sales_staff_id = ?
        ");
        $stmt->bind_param("sii", $clientStatus, $id, $currentSalesId);

        if ($stmt->execute()) {
            $stmt->close();
            echo json_encode(['success' => true]);
        } else {
            $stmt->close();
            echo json_encode(['success' => false, 'message' => 'Failed to save.']);
        }
        exit;
    }

    // --- Normal flow: Confirmed — schedule + designer required ---
    $designerId = intval($_POST['designer_id'] ?? 0);
    $measurementDatetime = trim($_POST['measurement_datetime'] ?? '');

    if ($designerId <= 0 || $measurementDatetime === '') {
        echo json_encode(['success' => false, 'message' => 'Designer and measurement date/time are required.']);
        exit;
    }

    $stmt = $conn->prepare("
        UPDATE noblecrminquiry
        SET designer_id = ?, measurement_datetime = ?, statusdesignersitevisit = 'waiting for measurement', client_status = ?
        WHERE id = ? AND sales_staff_id = ?
    ");
    $stmt->bind_param("issii", $designerId, $measurementDatetime, $clientStatus, $id, $currentSalesId);

    if ($stmt->execute()) {
        $stmt->close();

        $notifMessage = "New inquiry from {$inquiry['client_name']} (Control No. {$inquiry['control_no']}) has been assigned to you.";
        $notifLink = '/crmdesigner?id=' . $id;

        $notifStmt = $conn->prepare("
            INSERT INTO noblenotification
                (user_id, request_id, control_no, type, message, is_read, created_at, sender_id, link)
            VALUES (?, ?, ?, 'crm', ?, 0, NOW(), ?, ?)
        ");
        $notifStmt->bind_param(
            "iissis",
            $designerId,
            $id,
            $inquiry['control_no'],
            $notifMessage,
            $currentSalesId,
            $notifLink
        );
        $notifStmt->execute();
        $notifStmt->close();

        echo json_encode(['success' => true]);
    } else {
        $stmt->close();
        echo json_encode(['success' => false, 'message' => 'Failed to save assignment.']);
    }
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action.']);