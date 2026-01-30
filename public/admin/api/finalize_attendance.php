<?php
session_start();

// Check if user has administrator role
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role']];
$hasAdminRole = in_array('administrator', $userRoles);

if (!isset($_SESSION['user_id']) || (!$hasAdminRole && $_SESSION['role'] !== 'administrator')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

require_once __DIR__ . '/../../../app/Config/database.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['month']) || !isset($input['year'])) {
    echo json_encode(['success' => false, 'message' => 'Month and year are required']);
    exit;
}

$month = $input['month'];
$year = $input['year'];
$notes = $input['notes'] ?? '';
$userId = $_SESSION['user_id'];

try {
    $db = getDBConnection();
    $db->beginTransaction();
    
    // 1. Update all HR verified records to admin_finalized for this month
    $updateQuery = "UPDATE attendance 
                    SET workflow_status = 'admin_finalized',
                        finalized_by = ?,
                        finalized_at = NOW()
                    WHERE DATE_FORMAT(date, '%M') = ? 
                    AND YEAR(date) = ?
                    AND workflow_status = 'hr_verified'";
    
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->execute([$userId, $month, $year]);
    $recordsFinalized = $updateStmt->rowCount();
    
    if ($recordsFinalized === 0) {
        $db->rollBack();
        echo json_encode([
            'success' => false, 
            'message' => 'No HR verified records found for this month. Please ensure HR has verified attendance before finalizing.'
        ]);
        exit;
    }
    
    // 2. Insert into attendance_finalization_log
    $logQuery = "INSERT INTO attendance_finalization_log 
                 (month, year, finalized_by, finalized_at, record_count, notes, is_locked) 
                 VALUES (?, ?, ?, NOW(), ?, ?, 1)";
    
    $logStmt = $db->prepare($logQuery);
    $logStmt->execute([$month, $year, $userId, $recordsFinalized, $notes]);
    
    // 3. Lock the month in attendance_month_lock table
    $lockQuery = "INSERT INTO attendance_month_lock (month, year, locked_by, locked_at, is_locked) 
                  VALUES (?, ?, ?, NOW(), 1)
                  ON DUPLICATE KEY UPDATE 
                  locked_by = VALUES(locked_by),
                  locked_at = NOW(),
                  is_locked = 1";
    
    $lockStmt = $db->prepare($lockQuery);
    $lockStmt->execute([$month, $year, $userId]);
    
    // 4. Insert workflow audit entry
    $auditQuery = "INSERT INTO workflow_audit 
                   (entity_type, entity_id, action, performed_by, details, performed_at) 
                   VALUES ('attendance', ?, 'finalized', ?, ?, NOW())";
    
    $auditDetails = json_encode([
        'month' => $month,
        'year' => $year,
        'records_finalized' => $recordsFinalized,
        'notes' => $notes
    ]);
    
    $auditStmt = $db->prepare($auditQuery);
    $auditStmt->execute([0, $userId, $auditDetails]);
    
    // 5. Create notification for all accountants
    $accountantQuery = "SELECT u.user_id FROM users u 
                        JOIN user_roles ur ON u.user_id = ur.user_id 
                        JOIN roles r ON ur.role_id = r.role_id 
                        WHERE r.role_name = 'accountant'";
    $accountantStmt = $db->query($accountantQuery);
    $accountants = $accountantStmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($accountants)) {
        $notifQuery = "INSERT INTO notifications 
                       (user_id, type, title, message, link, created_at, is_read) 
                       VALUES (?, 'attendance_finalized', ?, ?, ?, NOW(), 0)";
        
        $notifStmt = $db->prepare($notifQuery);
        
        foreach ($accountants as $accountantId) {
            $notifTitle = "Attendance Finalized: $month $year";
            $notifMessage = "Admin has finalized $recordsFinalized attendance records for $month $year. You can now import and process for salary calculation.";
            $notifLink = "/payslip_generator/public/accountant/finalized_attendance.php";
            
            $notifStmt->execute([$accountantId, $notifTitle, $notifMessage, $notifLink]);
        }
    }
    
    $db->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => "Successfully finalized $recordsFinalized records for $month $year. Month is now locked and ready for export. Accountants have been notified.",
        'records_finalized' => $recordsFinalized
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log("Finalize attendance error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false, 
        'message' => 'Error finalizing attendance: ' . $e->getMessage()
    ]);
}
