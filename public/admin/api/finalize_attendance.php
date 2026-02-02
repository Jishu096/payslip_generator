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
    $accountantQuery = "SELECT u.user_id, u.email, u.username FROM users u 
                        JOIN user_roles ur ON u.user_id = ur.user_id 
                        JOIN roles r ON ur.role_id = r.role_id 
                        WHERE r.role_name = 'accountant'";
    $accountantStmt = $db->query($accountantQuery);
    $accountants = $accountantStmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($accountants)) {
        $notifQuery = "INSERT INTO notifications 
                       (user_id, type, title, message, link, created_at, is_read) 
                       VALUES (?, 'attendance_finalized', ?, ?, ?, NOW(), 0)";
        
        $notifStmt = $db->prepare($notifQuery);
        
        foreach ($accountants as $accountant) {
            $notifTitle = "Attendance Finalized: $month $year";
            $notifMessage = "Admin has finalized $recordsFinalized attendance records for $month $year. You can now import and process for salary calculation.";
            $notifLink = "/payslip_generator/public/accountant/finalized_attendance.php";
            
            $notifStmt->execute([$accountant['user_id'], $notifTitle, $notifMessage, $notifLink]);
        }
        
        // 6. Send email notification to accountants
        try {
            require_once __DIR__ . '/../../../app/Helpers/EmailHelper.php';
            $emailHelper = new EmailHelper($db);
            
            foreach ($accountants as $accountant) {
                if (!empty($accountant['email'])) {
                    $emailSubject = "🔔 Attendance Finalized: $month $year - Action Required";
                    $emailBody = "
                    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                        <div style='background: linear-gradient(135deg, #667eea, #764ba2); padding: 30px; border-radius: 12px 12px 0 0;'>
                            <h1 style='color: white; margin: 0; font-size: 24px;'>📋 Attendance Finalized</h1>
                        </div>
                        <div style='background: #f8fafc; padding: 30px; border: 1px solid #e2e8f0; border-top: none; border-radius: 0 0 12px 12px;'>
                            <p style='color: #334155; font-size: 16px; margin-bottom: 20px;'>
                                Hello <strong>" . htmlspecialchars($accountant['username']) . "</strong>,
                            </p>
                            <p style='color: #334155; font-size: 16px; margin-bottom: 20px;'>
                                The Administrator has finalized attendance for <strong>$month $year</strong>.
                            </p>
                            <div style='background: white; border-left: 4px solid #10b981; padding: 15px 20px; margin: 20px 0; border-radius: 4px;'>
                                <p style='margin: 0; color: #334155;'>
                                    <strong>📊 Records Finalized:</strong> $recordsFinalized<br>
                                    <strong>📅 Period:</strong> $month $year<br>
                                    <strong>✅ Status:</strong> Ready for Payroll Processing
                                </p>
                            </div>
                            <p style='color: #334155; font-size: 16px; margin-bottom: 25px;'>
                                You can now proceed with salary calculation and payslip generation for this period.
                            </p>
                            <a href='http://localhost/payslip_generator/public/accountant/finalized_attendance.php' 
                               style='display: inline-block; background: linear-gradient(135deg, #667eea, #764ba2); 
                                      color: white; padding: 14px 28px; text-decoration: none; border-radius: 8px; 
                                      font-weight: 600;'>
                                View Finalized Attendance →
                            </a>
                            <hr style='border: none; border-top: 1px solid #e2e8f0; margin: 30px 0;'>
                            <p style='color: #64748b; font-size: 12px; margin: 0;'>
                                This is an automated notification from NIELIT e-HRMS.<br>
                                Please do not reply to this email.
                            </p>
                        </div>
                    </div>";
                    
                    $emailHelper->sendEmail($accountant['email'], $emailSubject, $emailBody);
                }
            }
        } catch (Exception $emailError) {
            // Log email error but don't fail the main operation
            error_log("Email notification error: " . $emailError->getMessage());
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
