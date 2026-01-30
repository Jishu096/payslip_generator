<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../app/Config/database.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['notification_id'])) {
    echo json_encode(['success' => false, 'message' => 'Notification ID required']);
    exit;
}

try {
    $db = getDBConnection();
    $userId = $_SESSION['user_id'];
    $notificationId = $input['notification_id'];
    
    // Mark notification as read (verify it belongs to this user)
    $query = "UPDATE notifications 
              SET is_read = 1, read_at = NOW() 
              WHERE notification_id = ? AND user_id = ?";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$notificationId, $userId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Notification marked as read'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Notification not found or already read'
        ]);
    }
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error updating notification: ' . $e->getMessage()
    ]);
}
