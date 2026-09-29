<?php
// api/noblerole.php
require_once __DIR__ . '/config.php';

$method = $_SERVER['REQUEST_METHOD'];
$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$input  = json_decode(file_get_contents('php://input'), true) ?? [];

// Walang password sa ibinabalik
$cols = 'id, name, email, role, position, branch, last_active, signature, active_signature_id, created_at';
$validPositions = ['head', 'staff', 'custodian', 'custoassistant']; // dagdagan kung may iba pa sa enum mo

switch ($method) {

    case 'GET':
        if ($id) {
            $stmt = $conn->prepare("SELECT $cols FROM noblerole WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $row ? respond($row) : respond(['error' => 'Not found'], 404);
        }
        $result = $conn->query("SELECT $cols FROM noblerole ORDER BY id DESC");
        respond($result->fetch_all(MYSQLI_ASSOC));
        break;

    case 'POST':
        foreach (['name', 'email', 'role', 'password'] as $f) {
            if (empty($input[$f])) respond(['error' => "$f is required"], 422);
        }
        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            respond(['error' => 'Invalid email'], 422);
        }
        $position = $input['position'] ?? 'staff';
        if (!in_array($position, $validPositions, true)) {
            respond(['error' => 'Invalid position'], 422);
        }
        $hash   = password_hash($input['password'], PASSWORD_DEFAULT);
        $branch = $input['branch'] ?? null;

        $stmt = $conn->prepare(
            "INSERT INTO noblerole (name, email, role, password, position, branch)
             VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->bind_param('ssssss', $input['name'], $input['email'], $input['role'], $hash, $position, $branch);

        if (!$stmt->execute()) {
            error_log($stmt->error);
            respond(['error' => 'Insert failed'], 500);
        }
        respond(['id' => $conn->insert_id], 201);
        break;

    case 'PUT':
        if (!$id) respond(['error' => 'id is required'], 400);

        $sets = [];
        $vals = [];
        foreach (['name', 'email', 'role', 'position', 'branch'] as $f) {
            if (array_key_exists($f, $input)) {
                if ($f === 'position' && !in_array($input[$f], $validPositions, true)) {
                    respond(['error' => 'Invalid position'], 422);
                }
                $sets[] = "$f = ?";
                $vals[] = $input[$f];
            }
        }
        if (!empty($input['password'])) {
            $sets[] = 'password = ?';
            $vals[] = password_hash($input['password'], PASSWORD_DEFAULT);
        }
        if (!$sets) respond(['error' => 'Nothing to update'], 400);

        $vals[] = $id;
        $types  = str_repeat('s', count($vals) - 1) . 'i';

        $stmt = $conn->prepare('UPDATE noblerole SET ' . implode(', ', $sets) . ' WHERE id = ?');
        $stmt->bind_param($types, ...$vals);

        if (!$stmt->execute()) {
            error_log($stmt->error);
            respond(['error' => 'Update failed'], 500);
        }
        respond(['updated' => true]);
        break;

    case 'DELETE':
        if (!$id) respond(['error' => 'id is required'], 400);
        $stmt = $conn->prepare('DELETE FROM noblerole WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        respond(['deleted' => $stmt->affected_rows > 0]);
        break;

    default:
        respond(['error' => 'Method not allowed'], 405);
}