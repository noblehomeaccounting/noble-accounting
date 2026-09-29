<?php
//crmsitevisitajax.php

include ROOT_PATH . '/network/connect.php';
include ROOT_PATH . '/admin/authentication/index-roles.php';

// Adjust to whichever roles are allowed to manage these option lists
// (sales staff creating inquiries, designers logging site visits, etc.)
$allowedRoles = [ROLE_SALES, ROLE_DESIGNER];

include ROOT_PATH . '/admin/authentication/index-authguard.php';
include ROOT_PATH . '/admin/authentication/index-roleguard.php';

header('Content-Type: application/json');

// Map the public "type" param to its actual table. Whitelisted on purpose —
// never build the table name directly from user input.
$allowedTypes = [
    'measuring_space' => 'noblecrm_measuring_space',
    'project_scope'   => 'noblecrm_project_scope',
    'room_type'       => 'noblecrm_room_type',
];

$type = $_GET['type'] ?? $_POST['type'] ?? '';
$action = $_GET['action'] ?? $_POST['action'] ?? '';

if (!isset($allowedTypes[$type])) {
    echo json_encode(['success' => false, 'message' => 'Invalid type.']);
    exit;
}

$table = $allowedTypes[$type];

switch ($action) {

    case 'list':
        $result = $conn->query("SELECT id, label FROM {$table} ORDER BY label ASC");
        $items = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];
        echo json_encode(['success' => true, 'items' => $items]);
        break;

    case 'add':
        $label = trim($_POST['label'] ?? '');
        if ($label === '') {
            echo json_encode(['success' => false, 'message' => 'Label is required.']);
            break;
        }
        $stmt = $conn->prepare("INSERT INTO {$table} (label) VALUES (?)");
        $stmt->bind_param("s", $label);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'id' => $stmt->insert_id]);
        } else {
            $msg = ($conn->errno === 1062) ? 'That option already exists.' : 'Failed to add option.';
            echo json_encode(['success' => false, 'message' => $msg]);
        }
        $stmt->close();
        break;

    case 'edit':
        $id = intval($_POST['id'] ?? 0);
        $label = trim($_POST['label'] ?? '');
        if ($id <= 0 || $label === '') {
            echo json_encode(['success' => false, 'message' => 'Invalid input.']);
            break;
        }
        $stmt = $conn->prepare("UPDATE {$table} SET label = ? WHERE id = ?");
        $stmt->bind_param("si", $label, $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            $msg = ($conn->errno === 1062) ? 'That option already exists.' : 'Failed to update option.';
            echo json_encode(['success' => false, 'message' => $msg]);
        }
        $stmt->close();
        break;

    case 'delete':
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid input.']);
            break;
        }
        $stmt = $conn->prepare("DELETE FROM {$table} WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete option.']);
        }
        $stmt->close();
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        break;
}