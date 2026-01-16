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

if (!isset($input['attendance_id']) || !isset($input['field']) || !isset($input['value'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$attendanceId = $input['attendance_id'];
$field = $input['field'];
$value = $input['value'];

// Whitelist allowed fields
$allowedFields = ['status', 'time_in', 'time_out', 'leave_type', 'remarks'];
if (!in_array($field, $allowedFields)) {
    echo json_encode(['success' => false, 'message' => 'Invalid field']);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE attendance SET {$field} = ? WHERE attendance_id = ?");
    $stmt->execute([$value, $attendanceId]);
    
    // Log the update
    $logStmt = $conn->prepare("
        INSERT INTO audit_log (user_id, action, log_time) 
        VALUES (?, ?, NOW())
    ");
    $logStmt->execute([
        $_SESSION['user_id'] ?? 0,
        "Updated attendance #{$attendanceId} - {$field}"
    ]);
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
