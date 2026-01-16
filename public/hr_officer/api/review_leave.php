<?php
session_start();
header('Content-Type: application/json');

// Role check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'hr_officer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Database connection
require_once __DIR__ . '/../../../app/Config/database.php';
$db = new Database();
$conn = $db->connect();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['leave_id']) || !isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$leaveId = $input['leave_id'];
$action = $input['action']; // 'approve' or 'reject'
$comments = $input['comments'] ?? '';
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Validate action
if (!in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

$status = ($action === 'approve') ? 'approved' : 'rejected';

try {
    $conn->beginTransaction();
    
    // Update leave request
    $stmt = $conn->prepare("
        UPDATE leave_requests 
        SET status = ?, 
            reviewed_by = ?, 
            reviewed_by_name = ?, 
            review_date = NOW(),
            review_comments = ?
        WHERE leave_id = ? AND status = 'pending'
    ");
    
    $stmt->execute([$status, $userId, $username, $comments, $leaveId]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception('Leave request not found or already reviewed');
    }
    
    // Log the action
    $logStmt = $conn->prepare("
        INSERT INTO audit_log (user_id, action, log_time) 
        VALUES (?, ?, NOW())
    ");
    $logStmt->execute([$userId, "Leave request #{$leaveId} {$status} by HR"]);
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "Leave request {$status} successfully"
    ]);
    
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
