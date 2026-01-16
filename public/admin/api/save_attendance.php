<?php
session_start();
header('Content-Type: application/json');

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['role'])) {
    echo json_encode(['success' => false, 'error' => 'Not logged in', 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../../app/Models/Attendance.php';
require_once __DIR__ . '/../../../app/Config/database.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$employeeId = $input['employee_id'] ?? null;
$date = $input['date'] ?? null;
$status = $input['status'] ?? null;

// Log the attempt
error_log("AJAX Save Attempt - Employee: $employeeId, Date: $date, Status: $status");

if (!$employeeId || !$date || !$status) {
    echo json_encode(['success' => false, 'error' => 'Missing fields', 'message' => 'Missing required fields']);
    exit;
}

try {
    $attendanceModel = new Attendance();
    $result = $attendanceModel->markAttendance($employeeId, $date, $status);
    
    if ($result) {
        echo json_encode(['success' => true, 'message' => 'Attendance saved']);
    } else {
        // Get the last error
        $db = getDBConnection();
        $errorInfo = $db->errorInfo();
        error_log("Database error: " . print_r($errorInfo, true));
        echo json_encode(['success' => false, 'error' => 'Database returned false', 'message' => 'Database error', 'dbError' => $errorInfo]);
    }
} catch (Exception $e) {
    error_log("Exception in save_attendance: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'message' => 'Exception occurred']);
}
?>
