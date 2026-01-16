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

try {
    // Get today's manual entries (verification_status = 'Verified' indicates manual entry)
    $stmt = $conn->prepare("
        SELECT 
            a.attendance_id,
            a.employee_id,
            e.full_name,
            a.status,
            a.time_in,
            a.time_out,
            a.date
        FROM attendance a
        JOIN employees e ON a.employee_id = e.employee_id
        WHERE a.date = CURDATE() 
        AND a.verification_status = 'Verified'
        ORDER BY a.attendance_id DESC
        LIMIT 10
    ");
    
    $stmt->execute();
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $entries
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'data' => []
    ]);
}
?>
