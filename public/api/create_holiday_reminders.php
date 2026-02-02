<?php
/**
 * Holiday Reminder - Automated Notification System
 * 
 * Purpose: Creates notifications for administrators when a holiday
 *          (Closed or Restricted) is occurring tomorrow.
 * 
 * This script should be called on admin dashboard load or via cron job.
 */

require_once __DIR__ . '/../../app/Config/database.php';

// Holiday data for 2026
$closedHolidays = [
    ['name' => 'Republic Day', 'date' => '2026-01-26', 'type' => 'Closed'],
    ['name' => 'Holi', 'date' => '2026-03-04', 'type' => 'Closed'],
    ['name' => 'Ram Navami', 'date' => '2026-03-27', 'type' => 'Closed'],
    ['name' => 'Mahavir Jayanti', 'date' => '2026-03-31', 'type' => 'Closed'],
    ['name' => 'Good Friday', 'date' => '2026-04-03', 'type' => 'Closed'],
    ['name' => 'Mahabishuba Sankranti / Dr. B.R. Ambedkar Jayanti', 'date' => '2026-04-14', 'type' => 'Closed'],
    ['name' => 'Budha Purnima', 'date' => '2026-05-01', 'type' => 'Closed'],
    ['name' => 'Id-ul-Zuha (Bakrid)', 'date' => '2026-05-27', 'type' => 'Closed'],
    ['name' => 'Muharram', 'date' => '2026-06-26', 'type' => 'Closed'],
    ['name' => 'Rath Yatra', 'date' => '2026-07-16', 'type' => 'Closed'],
    ['name' => 'Milad-un-Nabi / Id-e-Milad', 'date' => '2026-08-26', 'type' => 'Closed'],
    ['name' => 'Janmashtami (Vaishnava)', 'date' => '2026-09-04', 'type' => 'Closed'],
    ['name' => "Mahatma Gandhi's Birthday", 'date' => '2026-10-02', 'type' => 'Closed'],
    ['name' => 'Dussehra (Mahanavami)', 'date' => '2026-10-19', 'type' => 'Closed'],
    ['name' => 'Dussehra', 'date' => '2026-10-20', 'type' => 'Closed'],
    ['name' => "Guru Nanak's Birthday", 'date' => '2026-11-24', 'type' => 'Closed'],
    ['name' => 'Christmas Day', 'date' => '2026-12-25', 'type' => 'Closed'],
];

$restrictedHolidays = [
    ['name' => "New Year's Day", 'date' => '2026-01-01', 'type' => 'Restricted'],
    ['name' => 'Makar Sankranti / Magha Bihu / Pongal', 'date' => '2026-01-14', 'type' => 'Restricted'],
    ['name' => 'Basanta Panchami / Sri Panchami', 'date' => '2026-01-23', 'type' => 'Restricted'],
    ['name' => 'Birthday of Swami Dayananda Saraswati', 'date' => '2026-02-12', 'type' => 'Restricted'],
    ['name' => 'Shivaji Jayanti', 'date' => '2026-02-19', 'type' => 'Restricted'],
    ['name' => 'Holika Dahan / Dol Yatra', 'date' => '2026-03-03', 'type' => 'Restricted'],
    ['name' => 'Chaitra Sukladi / Gudi Padava / Ugadi / Cheti Chand', 'date' => '2026-03-19', 'type' => 'Restricted'],
    ['name' => 'Jamat-Ul-Vida', 'date' => '2026-03-20', 'type' => 'Restricted'],
    ['name' => 'Vaisakhadi (Bengal) / Bahag Bihu (Assam)', 'date' => '2026-04-15', 'type' => 'Restricted'],
    ['name' => 'Raksha Bandhan', 'date' => '2026-08-28', 'type' => 'Restricted'],
    ['name' => 'Ganesh Chaturthi / Vinayaka Chaturthi', 'date' => '2026-09-14', 'type' => 'Restricted'],
    ['name' => "Maharishi Valmiki's Birthday", 'date' => '2026-10-26', 'type' => 'Restricted'],
    ['name' => 'Karaka Chaturthi (Karwa Chouth)', 'date' => '2026-10-29', 'type' => 'Restricted'],
    ['name' => 'Govardhan Puja', 'date' => '2026-11-09', 'type' => 'Restricted'],
    ['name' => 'Bhai Duj', 'date' => '2026-11-11', 'type' => 'Restricted'],
    ['name' => "Guru Teg Bahadur's Martyrdom Day", 'date' => '2026-11-24', 'type' => 'Restricted'],
    ['name' => "Hazarat Ali's Birthday", 'date' => '2026-12-23', 'type' => 'Restricted'],
    ['name' => 'Christmas Eve', 'date' => '2026-12-24', 'type' => 'Restricted'],
];

// Combine all holidays
$allHolidays = array_merge($closedHolidays, $restrictedHolidays);

try {
    $db = getDBConnection();
    
    // Get tomorrow's date
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $tomorrowFormatted = date('l, d F Y', strtotime('+1 day'));
    
    // Check if tomorrow is a holiday
    $tomorrowHolidays = [];
    foreach ($allHolidays as $holiday) {
        if ($holiday['date'] === $tomorrow) {
            $tomorrowHolidays[] = $holiday;
        }
    }
    
    if (empty($tomorrowHolidays)) {
        // No holiday tomorrow
        if (php_sapi_name() === 'cli') {
            echo "No holidays tomorrow.\n";
        }
        return;
    }
    
    // Check if notification already created for this holiday
    foreach ($tomorrowHolidays as $holiday) {
        $notificationKey = 'holiday_reminder_' . $holiday['date'];
        
        $checkQuery = "SELECT COUNT(*) as count FROM notifications 
                       WHERE type = ? 
                       AND DATE(created_at) = CURDATE()";
        
        $checkStmt = $db->prepare($checkQuery);
        $checkStmt->execute([$notificationKey]);
        $exists = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        if ($exists > 0) {
            // Notification already created today for this holiday
            continue;
        }
        
        // Get all administrators
        $adminQuery = "SELECT u.user_id FROM users u 
                       JOIN user_roles ur ON u.user_id = ur.user_id 
                       JOIN roles r ON ur.role_id = r.role_id 
                       WHERE r.role_name = 'administrator'";
        
        $adminStmt = $db->query($adminQuery);
        $admins = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($admins)) {
            continue;
        }
        
        // Determine icon and color based on holiday type
        $holidayType = $holiday['type'];
        $icon = $holidayType === 'Closed' ? '🔵' : '🟡';
        
        // Create notification for each administrator
        $notifQuery = "INSERT INTO notifications 
                       (user_id, type, title, message, link, created_at, is_read) 
                       VALUES (?, ?, ?, ?, ?, NOW(), 0)";
        
        $notifStmt = $db->prepare($notifQuery);
        
        $title = "$icon Holiday Tomorrow: " . $holiday['name'];
        $message = "Reminder: Tomorrow ({$tomorrowFormatted}) is a {$holidayType} Holiday - {$holiday['name']}. " . 
                   ($holidayType === 'Closed' ? "The office will remain closed." : "Employees may avail this as a restricted holiday.");
        $link = "/payslip_generator/public/admin/holidays_nielit.php";
        
        $count = 0;
        foreach ($admins as $adminId) {
            $notifStmt->execute([$adminId, $notificationKey, $title, $message, $link]);
            $count++;
        }
        
        // Log success
        error_log("Created $count holiday reminder notifications for {$holiday['name']} on {$holiday['date']}");
        
        if (php_sapi_name() === 'cli') {
            echo "Created $count notifications for holiday: {$holiday['name']}\n";
        }
    }
    
} catch (Exception $e) {
    error_log("Error creating holiday reminders: " . $e->getMessage());
    if (php_sapi_name() === 'cli') {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
