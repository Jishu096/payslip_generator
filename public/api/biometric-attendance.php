<?php
/**
 * Biometric Attendance API Endpoint
 * 
 * This endpoint receives attendance data from biometric devices
 * Supports: ZKTeco, eSSL, Suprema, and other standard devices
 * 
 * Expected JSON format:
 * {
 *   "device_id": "BIO-001",
 *   "employee_id": 123,
 *   "timestamp": "2026-01-07 09:15:30",
 *   "status": "present",
 *   "type": "check_in",
 *   "api_key": "your_secure_api_key"
 * }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST.']);
    exit;
}

require_once "../../app/Config/database.php";
require_once "../../app/Models/Attendance.php";

// API Key validation (configure this in your environment)
define('BIOMETRIC_API_KEY', 'CHANGE_THIS_TO_SECURE_KEY_12345'); // TODO: Move to config file

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Log all requests for debugging
$logFile = __DIR__ . '/../../storage/logs/biometric_requests.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . json_encode($input) . PHP_EOL, FILE_APPEND);

// Validate API key
$apiKey = $input['api_key'] ?? '';
if ($apiKey !== BIOMETRIC_API_KEY) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Invalid API key']);
    exit;
}

// Validate required fields
$requiredFields = ['employee_id', 'timestamp'];
foreach ($requiredFields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => "Missing required field: $field"]);
        exit;
    }
}

try {
    $db = getDBConnection();
    $attendanceModel = new Attendance();
    
    $employeeId = (int)$input['employee_id'];
    $timestamp = $input['timestamp'];
    $status = $input['status'] ?? 'present'; // Default to present
    $deviceId = $input['device_id'] ?? null;
    $type = $input['type'] ?? 'attendance'; // check_in, check_out, or attendance
    
    // Validate employee exists
    $stmt = $db->prepare("SELECT employee_id FROM employees WHERE employee_id = :id LIMIT 1");
    $stmt->execute([':id' => $employeeId]);
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Employee not found']);
        exit;
    }
    
    // Extract date from timestamp
    $date = date('Y-m-d', strtotime($timestamp));
    
    // Check if attendance already exists for this date
    $checkStmt = $db->prepare("SELECT attendance_id FROM attendance WHERE employee_id = :emp_id AND date = :date LIMIT 1");
    $checkStmt->execute([':emp_id' => $employeeId, ':date' => $date]);
    $existing = $checkStmt->fetch();
    
    if ($existing) {
        // Update existing record
        $updateSql = "UPDATE attendance SET 
                        status = :status,
                        updated_at = NOW()";
        
        // If biometric fields exist in your table, update them
        if ($deviceId) {
            $updateSql .= ", device_id = :device_id";
        }
        
        $updateSql .= " WHERE attendance_id = :id";
        
        $updateStmt = $db->prepare($updateSql);
        $updateParams = [
            ':status' => $status,
            ':id' => $existing['attendance_id']
        ];
        
        if ($deviceId) {
            $updateParams[':device_id'] = $deviceId;
        }
        
        $updateStmt->execute($updateParams);
        
        echo json_encode([
            'success' => true,
            'message' => 'Attendance updated successfully',
            'attendance_id' => $existing['attendance_id'],
            'action' => 'updated'
        ]);
        
    } else {
        // Insert new attendance record
        $insertSql = "INSERT INTO attendance (employee_id, date, status, created_at";
        
        if ($deviceId) {
            $insertSql .= ", device_id";
        }
        
        $insertSql .= ") VALUES (:employee_id, :date, :status, NOW()";
        
        if ($deviceId) {
            $insertSql .= ", :device_id";
        }
        
        $insertSql .= ")";
        
        $insertStmt = $db->prepare($insertSql);
        $insertParams = [
            ':employee_id' => $employeeId,
            ':date' => $date,
            ':status' => $status
        ];
        
        if ($deviceId) {
            $insertParams[':device_id'] = $deviceId;
        }
        
        $insertStmt->execute($insertParams);
        
        echo json_encode([
            'success' => true,
            'message' => 'Attendance recorded successfully',
            'attendance_id' => $db->lastInsertId(),
            'action' => 'created'
        ]);
    }
    
    http_response_code(200);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
    
    // Log error
    $errorLog = __DIR__ . '/../../storage/logs/biometric_errors.log';
    file_put_contents($errorLog, date('Y-m-d H:i:s') . ' - ' . $e->getMessage() . PHP_EOL, FILE_APPEND);
}
