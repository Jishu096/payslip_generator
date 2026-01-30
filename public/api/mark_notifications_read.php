<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../app/Config/database.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['type'])) {
    echo json_encode(['success' => false, 'message' => 'Notification type required']);
    exit;
}

try {
    $db = getDBConnection();
    $userId = $_SESSION['user_id'];
    $notificationType = $input['type'];
    
    // Mark all notifications of this type as read for this user
    if ($notificationType === 'all') {
        // Mark all unread notifications
        $query = "UPDATE notifications 
                  SET is_read = 1, read_at = NOW() 
                  WHERE user_id = ? AND is_read = 0";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$userId]);
    } else {
        // Mark specific type only
        $query = "UPDATE notifications 
                  SET is_read = 1, read_at = NOW() 
                  WHERE user_id = ? AND type = ? AND is_read = 0";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$userId, $notificationType]);
    }
    
    $rowsUpdated = $stmt->rowCount();
    
    echo json_encode([
        'success' => true,
        'message' => "Marked {$rowsUpdated} notifications as read",
        'count' => $rowsUpdated
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error updating notifications: ' . $e->getMessage()
    ]);
}
