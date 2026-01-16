<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../../app/Config/database.php';
require_once __DIR__ . '/../../../app/Helpers/PermissionHelper.php';
require_once __DIR__ . '/../../../app/Helpers/AuditLogger.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$conn = getDBConnection();
$perm = new PermissionHelper($conn, $_SESSION['user_id']);

if (!$perm->hasPermission('role.assign')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$userId = intval($_POST['user_id'] ?? 0);
$roleId = intval($_POST['role_id'] ?? 0);

if ($userId === 0 || $roleId === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user or role']);
    exit;
}

try {
    // Check if already assigned
    $checkStmt = $conn->prepare("SELECT user_role_id FROM user_roles_new WHERE user_id = ? AND role_id = ?");
    $checkStmt->execute([$userId, $roleId]);
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Role already assigned to this user']);
        exit;
    }

    // Assign role
    $stmt = $conn->prepare("INSERT INTO user_roles_new (user_id, role_id, assigned_at) VALUES (?, ?, NOW())");
    $stmt->execute([$userId, $roleId]);

    // Get details for audit log
    $userStmt = $conn->prepare("SELECT username FROM users WHERE user_id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    $roleStmt = $conn->prepare("SELECT role_name FROM roles WHERE role_id = ?");
    $roleStmt->execute([$roleId]);
    $role = $roleStmt->fetch(PDO::FETCH_ASSOC);

    // Log audit
    $audit = new AuditLogger($conn);
    $audit->log($_SESSION['user_id'], 'role.assign', "Assigned role '{$role['role_name']}' to user '{$user['username']}'");

    echo json_encode(['success' => true, 'message' => 'Role assigned successfully']);
} catch (Exception $e) {
    error_log("Assign role error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
