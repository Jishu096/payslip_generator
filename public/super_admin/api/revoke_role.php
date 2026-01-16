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

if (!$perm->hasPermission('role.revoke')) {
    echo json_encode(['success' => false, 'message' => 'Permission denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$userRoleId = intval($_POST['user_role_id'] ?? 0);

if ($userRoleId === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user role ID']);
    exit;
}

try {
    // Get details before deletion for audit log
    $detailsStmt = $conn->prepare("
        SELECT u.username, r.role_name, urn.user_id
        FROM user_roles_new urn
        JOIN users u ON urn.user_id = u.user_id
        JOIN roles r ON urn.role_id = r.role_id
        WHERE urn.user_role_id = ?
    ");
    $detailsStmt->execute([$userRoleId]);
    $details = $detailsStmt->fetch(PDO::FETCH_ASSOC);

    if (!$details) {
        echo json_encode(['success' => false, 'message' => 'Role assignment not found']);
        exit;
    }

    // Prevent revoking own super_admin role
    if ($details['user_id'] == $_SESSION['user_id'] && $details['role_name'] == 'super_admin') {
        echo json_encode(['success' => false, 'message' => 'You cannot revoke your own Super Admin role']);
        exit;
    }

    // Revoke role
    $stmt = $conn->prepare("DELETE FROM user_roles_new WHERE user_role_id = ?");
    $stmt->execute([$userRoleId]);

    // Log audit
    $audit = new AuditLogger($conn);
    $audit->log($_SESSION['user_id'], 'role.revoke', "Revoked role '{$details['role_name']}' from user '{$details['username']}'");

    echo json_encode(['success' => true, 'message' => 'Role revoked successfully']);
} catch (Exception $e) {
    error_log("Revoke role error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
