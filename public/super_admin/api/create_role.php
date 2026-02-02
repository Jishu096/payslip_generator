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

if (!$perm->hasPermission('role.create')) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

$roleName = trim($_POST['role_name'] ?? '');
$displayName = trim($_POST['display_name'] ?? '');
$description = trim($_POST['description'] ?? '');

if (empty($roleName) || empty($displayName)) {
    echo json_encode(['success' => false, 'error' => 'Role name and display name are required']);
    exit;
}

// Validate role_name format (lowercase letters and underscores only)
if (!preg_match('/^[a-z_]+$/', $roleName)) {
    echo json_encode(['success' => false, 'error' => 'Role name must contain only lowercase letters and underscores']);
    exit;
}

try {
    // Check if role already exists
    $checkStmt = $conn->prepare("SELECT role_id FROM roles WHERE role_name = ?");
    $checkStmt->execute([$roleName]);
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'A role with this name already exists']);
        exit;
    }

    // Insert new role
    $stmt = $conn->prepare("INSERT INTO roles (role_name, display_name, description, is_active, created_at) VALUES (?, ?, ?, 1, NOW())");
    $stmt->execute([$roleName, $displayName, $description]);
    
    $roleId = $conn->lastInsertId();

    // Log the action
    $logStmt = $conn->prepare("INSERT INTO audit_log (user_id, action, log_time) VALUES (?, ?, NOW())");
    $logStmt->execute([$_SESSION['user_id'], "role.create: $roleName"]);

    echo json_encode(['success' => true, 'role_id' => $roleId, 'message' => 'Role created successfully']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
