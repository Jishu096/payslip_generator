<?php
/**
 * Audit Log Controller
 * Handles deletion of audit log entries
 */

class AuditLogController {
    private $db;
    
    public function __construct() {
        require_once __DIR__ . '/../Config/database.php';
        $dbObj = new Database();
        $this->db = $dbObj->connect();
    }
    
    /**
     * Delete a single audit log entry
     */
    public function deleteLog() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        header('Content-Type: application/json');
        
        // Check authentication
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        $logId = $data['log_id'] ?? null;
        
        if (!$logId) {
            echo json_encode(['success' => false, 'message' => 'Log ID required']);
            exit;
        }
        
        try {
            $stmt = $this->db->prepare("DELETE FROM audit_logs WHERE log_id = ?");
            $stmt->execute([$logId]);
            
            echo json_encode(['success' => true, 'message' => 'Audit log deleted successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
    
    /**
     * Clear all attendance statement audit logs
     */
    public function clearLogs() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        header('Content-Type: application/json');
        
        // Check authentication and role
        if (!isset($_SESSION['user_id'])) {
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
        
        $userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? null];
        $hasAccountantRole = in_array('accountant', $userRoles);
        
        if (!$hasAccountantRole) {
            echo json_encode(['success' => false, 'message' => 'Only accountants can clear audit logs']);
            exit;
        }
        
        try {
            $stmt = $this->db->prepare("
                DELETE FROM audit_logs 
                WHERE action = 'attendance_statement_generated'
            ");
            $stmt->execute();
            
            echo json_encode(['success' => true, 'message' => 'All audit logs cleared successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}
