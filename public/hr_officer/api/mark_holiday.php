<?php
session_start();
header('Content-Type: application/json');

// Check HR Officer role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'hr_officer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../../app/Config/database.php';
$db = new Database();
$conn = $db->connect();

$data = json_decode(file_get_contents('php://input'), true);
$date = $data['date'] ?? null;

if (!$date) {
    echo json_encode(['success' => false, 'message' => 'Date is required']);
    exit;
}

try {
    $conn->beginTransaction();
    
    // Check if it's actually a holiday
    $stmt = $conn->prepare("SELECT holiday_name FROM holidays WHERE holiday_date = ? AND is_active = 1");
    $stmt->execute([$date]);
    $holiday = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$holiday) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Selected date is not a holiday']);
        exit;
    }
    
    // Get all active employees
    $stmt = $conn->query("SELECT employee_id FROM employees WHERE status = 'active'");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $affected = 0;
    $remarks = "Holiday: " . $holiday['holiday_name'];
    
    foreach ($employees as $emp) {
        // Check if attendance record exists
        $stmt = $conn->prepare("SELECT attendance_id FROM attendance WHERE employee_id = ? AND date = ?");
        $stmt->execute([$emp['employee_id'], $date]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Update existing record
            $stmt = $conn->prepare("
                UPDATE attendance 
                SET status = 'holiday',
                    leave_type = 'Holiday',
                    remarks = ?
                WHERE attendance_id = ?
            ");
            $stmt->execute([$remarks, $existing['attendance_id']]);
        } else {
            // Insert new record
            $stmt = $conn->prepare("
                INSERT INTO attendance (employee_id, date, status, leave_type, remarks, verification_status, workflow_status)
                VALUES (?, ?, 'holiday', 'Holiday', ?, 'Pending', 'draft')
            ");
            $stmt->execute([$emp['employee_id'], $date, $remarks]);
        }
        $affected++;
    }
    
    $conn->commit();
    echo json_encode([
        'success' => true, 
        'message' => 'All employees marked as holiday',
        'affected_rows' => $affected
    ]);
    
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
