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

if (!isset($input['employee_id']) || !isset($input['date']) || !isset($input['status'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$employeeId = $input['employee_id'];
$date = $input['date'];
$status = $input['status'];
$timeIn = $input['time_in'] ?? null;
$timeOut = $input['time_out'] ?? null;
$leaveType = $input['leave_type'] ?? null;
$remarks = $input['remarks'] ?? null;

try {
    // Check if entry already exists
    $checkStmt = $conn->prepare("
        SELECT attendance_id FROM attendance 
        WHERE employee_id = ? AND date = ?
    ");
    $checkStmt->execute([$employeeId, $date]);
    
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Attendance entry already exists for this employee and date']);
        exit;
    }
    
    // Insert new attendance record
    $stmt = $conn->prepare("
        INSERT INTO attendance (employee_id, date, status, time_in, time_out, leave_type, remarks, verification_status)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')
    ");
    $stmt->execute([$employeeId, $date, $status, $timeIn, $timeOut, $leaveType, $remarks]);
    
    $attendanceId = $conn->lastInsertId();
    
    // Log the action
    $logStmt = $conn->prepare("
        INSERT INTO audit_log (user_id, action, log_time) 
        VALUES (?, ?, NOW())
    ");
    $logStmt->execute([
        $_SESSION['user_id'] ?? 0,
        "Added attendance entry #{$attendanceId} for employee #{$employeeId} on {$date}"
    ]);
    
    echo json_encode([
        'success' => true,
        'attendance_id' => $attendanceId
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
