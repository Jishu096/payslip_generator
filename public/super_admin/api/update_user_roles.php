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

$userId = intval($_POST['user_id'] ?? 0);
$roles = $_POST['roles'] ?? [];

if ($userId <= 0) {
    echo json_encode(['success' => false, 'error' => 'User ID is required']);
    exit;
}

// Ensure roles is an array
if (!is_array($roles)) {
    $roles = [];
}

try {
    $conn->beginTransaction();

    // Get username for logging
    $userStmt = $conn->prepare("SELECT username FROM users WHERE user_id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();
    
    if (!$user) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }

    // Delete existing roles for this user
    $deleteStmt = $conn->prepare("DELETE FROM user_roles_new WHERE user_id = ?");
    $deleteStmt->execute([$userId]);

    // Insert new roles
    if (!empty($roles)) {
        $insertStmt = $conn->prepare("INSERT INTO user_roles_new (user_id, role_id, assigned_at) VALUES (?, ?, NOW())");
        foreach ($roles as $roleId) {
            $insertStmt->execute([$userId, intval($roleId)]);
        }

        // Update primary role in users table (first role selected)
        $primaryRoleStmt = $conn->prepare("SELECT role_name FROM roles WHERE role_id = ?");
        $primaryRoleStmt->execute([intval($roles[0])]);
        $primaryRole = $primaryRoleStmt->fetchColumn();
        
        if ($primaryRole) {
            $updateStmt = $conn->prepare("UPDATE users SET role = ? WHERE user_id = ?");
            $updateStmt->execute([$primaryRole, $userId]);
        }
    }

    // Log the action
    $roleCount = count($roles);
    $logStmt = $conn->prepare("INSERT INTO audit_log (user_id, action, log_time) VALUES (?, ?, NOW())");
    $logStmt->execute([$_SESSION['user_id'], "user.roles.update: {$user['username']} ($roleCount roles)"]);

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'Roles updated successfully', 'count' => $roleCount]);
} catch (PDOException $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
