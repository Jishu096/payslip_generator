<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/../../app/Config/database.php';

try {
    $db = getDBConnection();
    $userId = $_SESSION['user_id'];
    
    // Get unread notifications for this user
    $query = "SELECT * FROM notifications 
              WHERE user_id = ? AND is_read = 0 
              ORDER BY created_at DESC 
              LIMIT 10";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$userId]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'count' => count($notifications)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching notifications: ' . $e->getMessage()
    ]);
}
