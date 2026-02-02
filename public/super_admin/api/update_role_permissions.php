<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../../app/Config/database.php';
require_once __DIR__ . '/../../../app/Helpers/PermissionHelper.php';

$conn = getDBConnection();
$perm = new PermissionHelper($conn, $_SESSION['user_id']);

if (!$perm->hasPermission('role.assign')) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

$roleId = intval($_POST['role_id'] ?? 0);
$permissions = $_POST['permissions'] ?? [];

if ($roleId <= 0) {
    echo json_encode(['success' => false, 'error' => 'Role ID is required']);
    exit;
}

// Ensure permissions is an array
if (!is_array($permissions)) {
    $permissions = [];
}

try {
    $conn->beginTransaction();

    // Get role name for logging
    $roleStmt = $conn->prepare("SELECT role_name FROM roles WHERE role_id = ?");
    $roleStmt->execute([$roleId]);
    $role = $roleStmt->fetch();
    
    if (!$role) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'error' => 'Role not found']);
        exit;
    }

    // Delete existing permissions for this role
    $deleteStmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
    $deleteStmt->execute([$roleId]);

    // Insert new permissions
    if (!empty($permissions)) {
        $insertStmt = $conn->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
        foreach ($permissions as $permId) {
            $insertStmt->execute([$roleId, intval($permId)]);
        }
    }

    // Log the action
    $permCount = count($permissions);
    $logStmt = $conn->prepare("INSERT INTO audit_log (user_id, action, log_time) VALUES (?, ?, NOW())");
    $logStmt->execute([$_SESSION['user_id'], "role.permissions.update: {$role['role_name']} ($permCount permissions)"]);

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Permissions updated successfully', 'count' => $permCount]);
} catch (PDOException $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
