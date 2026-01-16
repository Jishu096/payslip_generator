<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../../app/Config/database.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = intval($_GET['user_id'] ?? 0);

if ($userId === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
    exit;
}

try {
    $conn = getDBConnection();
    
    $stmt = $conn->prepare("
        SELECT 
            urn.user_role_id,
            r.role_id,
            r.role_name,
            r.display_name
        FROM user_roles_new urn
        JOIN roles r ON urn.role_id = r.role_id
        WHERE urn.user_id = ?
        ORDER BY r.role_name
    ");
    $stmt->execute([$userId]);
    $roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['success' => true, 'roles' => $roles]);
} catch (Exception $e) {
    error_log("Get user roles error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
?>
