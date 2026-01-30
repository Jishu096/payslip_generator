<?php
/**
 * Attendance Finalization Reminder - Automated Notification System
 * 
 * Purpose: Creates notifications for administrators on the 25th of each month
 *          to remind them the finalization window is open.
 * 
 * Setup: Add to crontab to run daily at 00:01:
 *        1 0 * * * /Applications/XAMPP/xamppfiles/bin/php /path/to/create_finalization_reminders.php
 * 
 * OR: Call this script at the end of any admin page load on the 25th
 */

require_once __DIR__ . '/../app/Config/database.php';

try {
    $db = getDBConnection();
    
    // Check if today is the 25th
    $currentDay = (int)date('d');
    $currentMonth = date('F');
    $currentYear = date('Y');
    
    if ($currentDay !== 25) {
        // Not the 25th - exit silently
        exit;
    }
    
    // Check if notification already created for this month
    $checkQuery = "SELECT COUNT(*) as count FROM notifications 
                   WHERE type = 'finalization_window' 
                   AND DATE_FORMAT(created_at, '%Y-%m') = ?";
    
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->execute([date('Y-m')]);
    $exists = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($exists > 0) {
        // Notification already created for this month
        exit;
    }
    
    // Get all administrators
    $adminQuery = "SELECT u.user_id FROM users u 
                   JOIN user_roles ur ON u.user_id = ur.user_id 
                   JOIN roles r ON ur.role_id = r.role_id 
                   WHERE r.role_name = 'administrator'";
    
    $adminStmt = $db->query($adminQuery);
    $admins = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($admins)) {
        exit;
    }
    
    // Create notification for each administrator
    $notifQuery = "INSERT INTO notifications 
                   (user_id, type, title, message, link, created_at, is_read) 
                   VALUES (?, 'finalization_window', ?, ?, ?, NOW(), 0)";
    
    $notifStmt = $db->prepare($notifQuery);
    
    $title = "Finalization Window Open: $currentMonth $currentYear";
    $message = "The attendance finalization window is now open (from 25th onwards). You can finalize and lock $currentMonth $currentYear attendance records for payroll processing.";
    $link = "/payslip_generator/public/admin/attendance_finalize.php";
    
    $count = 0;
    foreach ($admins as $adminId) {
        $notifStmt->execute([$adminId, $title, $message, $link]);
        $count++;
    }
    
    // Log success (optional - can be removed in production)
    error_log("Created $count finalization window notifications for $currentMonth $currentYear");
    
} catch (Exception $e) {
    error_log("Error creating finalization reminders: " . $e->getMessage());
}
