<?php
session_start();
header('Content-Type: application/json');

// Role check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'hr_officer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Database connection
require_once __DIR__ . '/../../../app/Config/database.php';
$db = new Database();
$conn = $db->connect();

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['date'])) {
    echo json_encode(['success' => false, 'message' => 'Date required']);
    exit;
}

$date = $input['date'];

try {
    // Begin transaction
    $conn->beginTransaction();
    
    // Update all attendance records for this date to Verified
    $stmt = $conn->prepare("
        UPDATE attendance 
        SET verification_status = 'Verified' 
        WHERE date = ?
    ");
    $stmt->execute([$date]);
    
    $recordsUpdated = $stmt->rowCount();
    
    // Log the verification
    $logStmt = $conn->prepare("
        INSERT INTO audit_log (user_id, action, log_time) 
        VALUES (?, ?, NOW())
    ");
    $logStmt->execute([
        $_SESSION['user_id'] ?? 0,
        "Verified attendance sheet for {$date} ({$recordsUpdated} records)"
    ]);
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => "Verified {$recordsUpdated} attendance records",
        'records_updated' => $recordsUpdated
    ]);
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
