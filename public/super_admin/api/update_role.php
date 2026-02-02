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

if (!$perm->hasPermission('role.edit')) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

$roleId = intval($_POST['role_id'] ?? 0);
$displayName = trim($_POST['display_name'] ?? '');
$description = trim($_POST['description'] ?? '');
$isActive = intval($_POST['is_active'] ?? 1);

if ($roleId <= 0 || empty($displayName)) {
    echo json_encode(['success' => false, 'error' => 'Role ID and display name are required']);
    exit;
}

try {
    // Check if role exists
    $checkStmt = $conn->prepare("SELECT role_name FROM roles WHERE role_id = ?");
    $checkStmt->execute([$roleId]);
    $role = $checkStmt->fetch();
    
    if (!$role) {
        echo json_encode(['success' => false, 'error' => 'Role not found']);
        exit;
    }

    // Update role
    $stmt = $conn->prepare("UPDATE roles SET display_name = ?, description = ?, is_active = ?, updated_at = NOW() WHERE role_id = ?");
    $stmt->execute([$displayName, $description, $isActive, $roleId]);

    // Log the action
    $logStmt = $conn->prepare("INSERT INTO audit_log (user_id, action, log_time) VALUES (?, ?, NOW())");
    $logStmt->execute([$_SESSION['user_id'], "role.update: {$role['role_name']}"]);

    echo json_encode(['success' => true, 'message' => 'Role updated successfully']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
