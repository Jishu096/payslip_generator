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

if (!$perm->hasPermission('user.delete')) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$userId = intval($input['user_id'] ?? 0);

if ($userId <= 0) {
    echo json_encode(['success' => false, 'error' => 'User ID is required']);
    exit;
}

// Prevent deleting yourself
if ($userId == $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'error' => 'You cannot delete your own account']);
    exit;
}

try {
    // Get username for logging
    $userStmt = $conn->prepare("SELECT username FROM users WHERE user_id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch();
    
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'User not found']);
        exit;
    }

    $conn->beginTransaction();

    // Delete user roles first
    $deleteRolesStmt = $conn->prepare("DELETE FROM user_roles_new WHERE user_id = ?");
    $deleteRolesStmt->execute([$userId]);

    // Delete user
    $deleteUserStmt = $conn->prepare("DELETE FROM users WHERE user_id = ?");
    $deleteUserStmt->execute([$userId]);

    // Log the action
    $logStmt = $conn->prepare("INSERT INTO audit_log (user_id, action, log_time) VALUES (?, ?, NOW())");
    $logStmt->execute([$_SESSION['user_id'], "user.delete: {$user['username']}"]);

    $conn->commit();
    echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
} catch (PDOException $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
