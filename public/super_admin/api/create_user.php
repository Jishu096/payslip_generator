<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/../../../app/Config/database.php';
require_once __DIR__ . '/../../../app/Helpers/PermissionHelper.php';

// Verify super admin permission
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$conn = getDBConnection();
$perm = new PermissionHelper($conn, $_SESSION['user_id']);

if (!$perm->hasPermission('user.create')) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = trim($_POST['password'] ?? '');
$role = trim($_POST['role'] ?? '');

if (empty($username) || empty($password) || empty($role)) {
    echo json_encode(['success' => false, 'error' => 'Username, password, and role are required']);
    exit;
}

try {
    // Check if username already exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
    $stmt->execute([$username]);
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Username already exists']);
        exit;
    }

    // Hash password
    $passwordHash = password_hash($password, PASSWORD_BCRYPT);

    // Insert user
    $stmt = $conn->prepare("
        INSERT INTO users (username, email, password_hash, role, is_active, created_at)
        VALUES (?, ?, ?, ?, 1, NOW())
    ");
    $stmt->execute([$username, $email, $passwordHash, $role]);
    $newUserId = $conn->lastInsertId();

    // Assign role in user_roles_new table
    $roleStmt = $conn->prepare("SELECT role_id FROM roles WHERE role_name = ?");
    $roleStmt->execute([$role]);
    $roleData = $roleStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($roleData) {
        $stmt = $conn->prepare("INSERT INTO user_roles_new (user_id, role_id, assigned_at) VALUES (?, ?, NOW())");
        $stmt->execute([$newUserId, $roleData['role_id']]);
    }

    // Log audit
    $logStmt = $conn->prepare("INSERT INTO audit_log (user_id, action, log_time) VALUES (?, ?, NOW())");
    $logStmt->execute([$_SESSION['user_id'], "user.create: $username"]);

    echo json_encode(['success' => true, 'message' => 'User created successfully', 'user_id' => $newUserId]);
} catch (Exception $e) {
    error_log("Create user error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>
