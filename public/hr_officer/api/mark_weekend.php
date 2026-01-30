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
    
    // Check if it's actually a weekend (Saturday or Sunday)
    $dayOfWeek = date('w', strtotime($date)); // 0 = Sunday, 6 = Saturday
    if ($dayOfWeek != 0 && $dayOfWeek != 6) {
        $conn->rollBack();
        echo json_encode(['success' => false, 'message' => 'Selected date is not a weekend']);
        exit;
    }
    
    $weekendDay = $dayOfWeek == 0 ? 'Sunday' : 'Saturday';
    
    // Get all active employees
    $stmt = $conn->query("SELECT employee_id FROM employees WHERE status = 'active'");
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $affected = 0;
    $remarks = "Weekend Off (" . $weekendDay . ")";
    
    foreach ($employees as $emp) {
        // Check if attendance record exists
        $stmt = $conn->prepare("SELECT attendance_id FROM attendance WHERE employee_id = ? AND date = ?");
        $stmt->execute([$emp['employee_id'], $date]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing) {
            // Update existing record
            $stmt = $conn->prepare("
                UPDATE attendance 
                SET status = 'leave',
                    leave_type = 'Weekend',
                    remarks = ?
                WHERE attendance_id = ?
            ");
            $stmt->execute([$remarks, $existing['attendance_id']]);
        } else {
            // Insert new record
            $stmt = $conn->prepare("
                INSERT INTO attendance (employee_id, date, status, leave_type, remarks, verification_status, workflow_status)
                VALUES (?, ?, 'leave', 'Weekend', ?, 'Pending', 'draft')
            ");
            $stmt->execute([$emp['employee_id'], $date, $remarks]);
        }
        $affected++;
    }
    
    $conn->commit();
    echo json_encode([
        'success' => true, 
        'message' => 'All employees marked as weekend off',
        'affected_rows' => $affected
    ]);
    
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
