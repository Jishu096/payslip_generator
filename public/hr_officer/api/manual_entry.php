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

$employeeId = $_POST['employee_id'] ?? null;
$status = $_POST['status'] ?? null;
$timeIn = !empty($_POST['time_in']) ? $_POST['time_in'] : null;
$timeOut = !empty($_POST['time_out']) ? $_POST['time_out'] : null;
$leaveType = !empty($_POST['leave_type']) ? $_POST['leave_type'] : null;
$remarks = $_POST['remarks'] ?? null;

// Validate required fields
if (!$employeeId || !$status) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Determine entry type (single or range)
$isSingleEntry = !empty($_POST['date']);
$userId = $_SESSION['user_id'];

try {
    $conn->beginTransaction();
    
    if ($isSingleEntry) {
        // Single date entry
        $date = $_POST['date'];
        
        // Check if record already exists
        $checkStmt = $conn->prepare("SELECT attendance_id FROM attendance WHERE employee_id = ? AND date = ?");
        $checkStmt->execute([$employeeId, $date]);
        
        if ($checkStmt->fetch()) {
            throw new Exception('Attendance record already exists for this date');
        }
        
        // Insert single record
        $stmt = $conn->prepare("
            INSERT INTO attendance (employee_id, date, status, time_in, time_out, leave_type, remarks, verification_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Verified')
        ");
        
        $stmt->execute([$employeeId, $date, $status, $timeIn, $timeOut, $leaveType, $remarks]);
        $count = 1;
        
    } else {
        // Date range entry
        $startDate = $_POST['start_date'] ?? null;
        $endDate = $_POST['end_date'] ?? null;
        
        if (!$startDate || !$endDate) {
            throw new Exception('Start date and end date are required for range entry');
        }
        
        if (strtotime($startDate) > strtotime($endDate)) {
            throw new Exception('Start date must be before or equal to end date');
        }
        
        // Get existing dates for this employee
        $existingStmt = $conn->prepare("
            SELECT date FROM attendance 
            WHERE employee_id = ? AND date BETWEEN ? AND ?
        ");
        $existingStmt->execute([$employeeId, $startDate, $endDate]);
        $existingDates = $existingStmt->fetchAll(PDO::FETCH_COLUMN);
        
        // Prepare insert statement
        $stmt = $conn->prepare("
            INSERT INTO attendance (employee_id, date, status, time_in, time_out, leave_type, remarks, verification_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'Verified')
        ");
        
        // Insert for each date in range
        $count = 0;
        $currentDate = $startDate;
        
        while (strtotime($currentDate) <= strtotime($endDate)) {
            // Skip if record already exists
            if (!in_array($currentDate, $existingDates)) {
                $stmt->execute([$employeeId, $currentDate, $status, $timeIn, $timeOut, $leaveType, $remarks]);
                $count++;
            }
            
            // Move to next date
            $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
        }
        
        if ($count === 0) {
            throw new Exception('All dates in range already have attendance records');
        }
    }
    
    // Log the action
    $logStmt = $conn->prepare("
        INSERT INTO audit_log (user_id, action, log_time) 
        VALUES (?, ?, NOW())
    ");
    $logStmt->execute([
        $userId, 
        "Manual attendance entry: {$count} record(s) added for employee #{$employeeId}"
    ]);
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $count > 1 ? 'Attendance records added successfully' : 'Attendance record added successfully',
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
