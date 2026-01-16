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

if (!isset($input['leave_ids']) || !isset($input['action']) || !is_array($input['leave_ids'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$leaveIds = $input['leave_ids'];
$action = $input['action']; // 'approve' or 'reject'
$comments = $input['comments'] ?? '';
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Validate action
if (!in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

if (empty($leaveIds)) {
    echo json_encode(['success' => false, 'message' => 'No leave requests selected']);
    exit;
}

$status = ($action === 'approve') ? 'approved' : 'rejected';

try {
    $conn->beginTransaction();
    
    // Build placeholders for IN clause
    $placeholders = str_repeat('?,', count($leaveIds) - 1) . '?';
    
    // Update leave requests
    $sql = "
        UPDATE leave_requests 
        SET status = ?, 
            reviewed_by = ?, 
            reviewed_by_name = ?, 
            review_date = NOW(),
            review_comments = ?
        WHERE leave_id IN ($placeholders) AND status = 'pending'
    ";
    
    $stmt = $conn->prepare($sql);
    $params = array_merge([$status, $userId, $username, $comments], $leaveIds);
    $stmt->execute($params);
    
    $count = $stmt->rowCount();
    
    if ($count === 0) {
        throw new Exception('No pending leave requests found');
    }
    
    // Log the action
    $logStmt = $conn->prepare("
        INSERT INTO audit_log (user_id, action, log_time) 
        VALUES (?, ?, NOW())
    ");
    $logStmt->execute([$userId, "Bulk {$status} {$count} leave requests by HR"]);
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => "{$count} leave request(s) {$status} successfully",
        'count' => $count
    ]);
    
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
