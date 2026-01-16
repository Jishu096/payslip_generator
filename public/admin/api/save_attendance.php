<?php
session_start();
header('Content-Type: application/json');

// Check authorization
if (!isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// User roles check
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role']];
$hasAdminRole = in_array('administrator', $userRoles);

if (!$hasAdminRole && $_SESSION['role'] !== 'administrator') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit;
}

require_once __DIR__ . '/../../../app/Models/Attendance.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

$employeeId = $input['employee_id'] ?? null;
$date = $input['date'] ?? null;
$status = $input['status'] ?? null;

if (!$employeeId || !$date || !$status) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$attendanceModel = new Attendance();
if ($attendanceModel->markAttendance($employeeId, $date, $status)) {
    echo json_encode(['success' => true, 'message' => 'Attendance saved']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
