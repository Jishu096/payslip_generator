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

if (!$perm->hasPermission('user.edit')) {
    echo json_encode(['success' => false, 'error' => 'Permission denied']);
    exit;
}

$userId = intval($_POST['user_id'] ?? 0);
$username = trim($_POST['username'] ?? '');
$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($userId <= 0 || empty($username) || empty($email)) {
    echo json_encode(['success' => false, 'error' => 'User ID, username, and email are required']);
    exit;
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Invalid email address']);
    exit;
}

try {
    // Check if username is taken by another user
    $checkStmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
    $checkStmt->execute([$username, $userId]);
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Username is already taken']);
        exit;
    }

    // Check if email is taken by another user
    $checkStmt = $conn->prepare("SELECT user_id FROM users WHERE email = ? AND user_id != ?");
    $checkStmt->execute([$email, $userId]);
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'error' => 'Email is already in use']);
        exit;
    }

    // Update user
    if (!empty($password)) {
        // Update with new password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, password = ? WHERE user_id = ?");
        $stmt->execute([$username, $email, $hashedPassword, $userId]);
    } else {
        // Update without changing password
        $stmt = $conn->prepare("UPDATE users SET username = ?, email = ? WHERE user_id = ?");
        $stmt->execute([$username, $email, $userId]);
    }

    // Log the action
    $logStmt = $conn->prepare("INSERT INTO audit_log (user_id, action, log_time) VALUES (?, ?, NOW())");
    $logStmt->execute([$_SESSION['user_id'], "user.update: $username"]);

    echo json_encode(['success' => true, 'message' => 'User updated successfully']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
