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
$userId = $_SESSION['user_id'];

try {
    $db = getDBConnection();
    $db->beginTransaction();
    
    // 1. Revert finalized records back to hr_verified
    $updateQuery = "UPDATE attendance 
                    SET workflow_status = 'hr_verified',
                        finalized_by = NULL,
                        finalized_at = NULL
                    WHERE DATE_FORMAT(date, '%M') = ? 
                    AND YEAR(date) = ?
                    AND workflow_status = 'admin_finalized'";
    
    $updateStmt = $db->prepare($updateQuery);
    $updateStmt->execute([$month, $year]);
    $recordsUnlocked = $updateStmt->rowCount();
    
    // 2. Unlock the month (keep locked_by for audit purposes)
    $unlockQuery = "UPDATE attendance_month_lock 
                    SET is_locked = 0
                    WHERE month = ? AND year = ?";
    
    $unlockStmt = $db->prepare($unlockQuery);
    $unlockStmt->execute([$month, $year]);
    
    // 3. Update finalization log
    $logQuery = "UPDATE attendance_finalization_log 
                 SET is_locked = 0
                 WHERE month = ? AND year = ?
                 ORDER BY finalized_at DESC LIMIT 1";
    
    $logStmt = $db->prepare($logQuery);
    $logStmt->execute([$month, $year]);
    
    // 4. Insert workflow audit entry
    $auditQuery = "INSERT INTO workflow_audit 
                   (entity_type, entity_id, action, performed_by, details, performed_at) 
                   VALUES ('attendance', ?, 'unlocked', ?, ?, NOW())";
    
    $auditDetails = json_encode([
        'month' => $month,
        'year' => $year,
        'records_unlocked' => $recordsUnlocked
    ]);
    
    $auditStmt = $db->prepare($auditQuery);
    $auditStmt->execute([0, $userId, $auditDetails]);
    
    $db->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => "Successfully unlocked $month $year. HR can now make changes to attendance records.",
        'records_unlocked' => $recordsUnlocked
    ]);
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    error_log("Unlock attendance error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false, 
        'message' => 'Error unlocking attendance: ' . $e->getMessage()
    ]);
}
