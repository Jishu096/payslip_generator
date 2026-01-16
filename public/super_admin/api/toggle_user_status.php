<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../../app/Config/database.php';
require_once __DIR__ . '/../../../app/Helpers/PermissionHelper.php';
require_once __DIR__ . '/../../../app/Helpers/AuditLogger.php';

// Verify super admin permission
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$conn = getDBConnection();
$perm = new PermissionHelper($conn, $_SESSION['user_id']);

if (!$perm->hasPermission('user.update')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$userId = intval($_POST['user_id'] ?? 0);
$isActive = intval($_POST['is_active'] ?? 0);

if ($userId === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

// Prevent deactivating self
if ($userId === $_SESSION['user_id'] && $isActive === 0) {
    echo json_encode(['success' => false, 'message' => 'You cannot deactivate your own account']);
    exit;
}

try {
    // Update user status
    $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE user_id = ?");
    $stmt->execute([$isActive, $userId]);

    // Get username for audit log
    $userStmt = $conn->prepare("SELECT username FROM users WHERE user_id = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    // Log audit
    $audit = new AuditLogger($conn);
    $action = $isActive ? 'activated' : 'deactivated';
    $audit->log($_SESSION['user_id'], 'user.update', "User {$action}: {$user['username']} (ID: $userId)");

    echo json_encode(['success' => true, 'message' => 'User status updated successfully']);
} catch (Exception $e) {
    error_log("Toggle user status error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
