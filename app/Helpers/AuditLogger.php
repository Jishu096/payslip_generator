<?php
/**
 * AuditLogger - Comprehensive Audit Trail System
 * Government eHRMS - NIELIT Bhubaneswar
 * 
 * Features:
 * - Log all critical actions
 * - Capture IP address, user agent, session ID
 * - Store old/new values for changes
 * - Query audit logs with filters
 */

class AuditLogger {
    private $conn;
    private $userId;
    private $ipAddress;
    private $userAgent;
    private $sessionId;
    
    public function __construct($connection = null) {
        if ($connection) {
            $this->conn = $connection;
        } else {
            require_once __DIR__ . '/../Config/database.php';
            $this->conn = getDBConnection();
        }
        
        $this->userId = $_SESSION['user_id'] ?? null;
        $this->ipAddress = $this->getClientIP();
        $this->userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $this->sessionId = session_id();
    }
    
    /**
     * Log an action to audit trail
     * 
     * @param string $action Action name (e.g., 'salary_changed', 'attendance_edited')
     * @param string $entityType Entity type (e.g., 'employee', 'payroll', 'attendance')
     * @param int|null $entityId Entity ID
     * @param mixed $oldValues Old values (will be JSON encoded)
     * @param mixed $newValues New values (will be JSON encoded)
     * @return bool
     */
    public function log($action, $entityType, $entityId = null, $oldValues = null, $newValues = null) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO audit_log 
                (user_id, action, entity_type, entity_id, old_values, new_values, 
                 ip_address, user_agent, session_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $oldJson = $oldValues ? json_encode($oldValues, JSON_UNESCAPED_UNICODE) : null;
            $newJson = $newValues ? json_encode($newValues, JSON_UNESCAPED_UNICODE) : null;
            
            $stmt->execute([
                $this->userId,
                $action,
                $entityType,
                $entityId,
                $oldJson,
                $newJson,
                $this->ipAddress,
                $this->userAgent,
                $this->sessionId
            ]);
            
            return true;
        } catch (PDOException $e) {
            error_log("Failed to log audit: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log salary change
     */
    public function logSalaryChange($employeeId, $oldSalary, $newSalary, $reason = '') {
        return $this->log(
            'salary_changed',
            'employee',
            $employeeId,
            ['old_salary' => $oldSalary, 'reason' => $reason],
            ['new_salary' => $newSalary]
        );
    }
    
    /**
     * Log attendance edit
     */
    public function logAttendanceEdit($attendanceId, $employeeId, $oldStatus, $newStatus, $date) {
        return $this->log(
            'attendance_edited',
            'attendance',
            $attendanceId,
            ['status' => $oldStatus, 'date' => $date, 'employee_id' => $employeeId],
            ['status' => $newStatus]
        );
    }
    
    /**
     * Log payroll generation
     */
    public function logPayrollGeneration($payrollId, $employeeId, $month, $year, $netSalary) {
        return $this->log(
            'payroll_generated',
            'payroll',
            $payrollId,
            null,
            [
                'employee_id' => $employeeId,
                'month' => $month,
                'year' => $year,
                'net_salary' => $netSalary
            ]
        );
    }
    
    /**
     * Log payroll approval
     */
    public function logPayrollApproval($month, $year, $status = 'approved') {
        return $this->log(
            'payroll_approved',
            'payroll',
            null,
            ['month' => $month, 'year' => $year],
            ['status' => $status]
        );
    }
    
    /**
     * Log leave approval/rejection
     */
    public function logLeaveAction($leaveId, $employeeId, $action, $reason = '') {
        return $this->log(
            "leave_$action",
            'leave_request',
            $leaveId,
            ['employee_id' => $employeeId],
            ['action' => $action, 'reason' => $reason]
        );
    }
    
    /**
     * Log user login
     */
    public function logLogin($username, $success = true) {
        return $this->log(
            $success ? 'login_success' : 'login_failed',
            'user',
            $this->userId,
            null,
            ['username' => $username, 'success' => $success]
        );
    }
    
    /**
     * Log user logout
     */
    public function logLogout($username) {
        return $this->log(
            'logout',
            'user',
            $this->userId,
            null,
            ['username' => $username]
        );
    }
    
    /**
     * Log user creation
     */
    public function logUserCreation($userId, $username, $role) {
        return $this->log(
            'user_created',
            'user',
            $userId,
            null,
            ['username' => $username, 'role' => $role]
        );
    }
    
    /**
     * Log user deletion (soft delete)
     */
    public function logUserDeletion($userId, $username) {
        return $this->log(
            'user_deleted',
            'user',
            $userId,
            ['username' => $username],
            ['deleted' => true]
        );
    }
    
    /**
     * Log role assignment
     */
    public function logRoleAssignment($userId, $username, $oldRoles, $newRoles) {
        return $this->log(
            'roles_assigned',
            'user',
            $userId,
            ['username' => $username, 'roles' => $oldRoles],
            ['roles' => $newRoles]
        );
    }
    
    /**
     * Get audit logs with filters
     * 
     * @param array $filters Filters (user_id, action, entity_type, start_date, end_date)
     * @param int $limit Number of records to fetch
     * @param int $offset Offset for pagination
     * @return array
     */
    public function getLogs($filters = [], $limit = 100, $offset = 0) {
        $sql = "
            SELECT 
                al.*,
                u.username,
                u.email
            FROM audit_log al
            LEFT JOIN users u ON al.user_id = u.user_id
            WHERE 1=1
        ";
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $sql .= " AND al.user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['action'])) {
            $sql .= " AND al.action = ?";
            $params[] = $filters['action'];
        }
        
        if (!empty($filters['entity_type'])) {
            $sql .= " AND al.entity_type = ?";
            $params[] = $filters['entity_type'];
        }
        
        if (!empty($filters['entity_id'])) {
            $sql .= " AND al.entity_id = ?";
            $params[] = $filters['entity_id'];
        }
        
        if (!empty($filters['start_date'])) {
            $sql .= " AND al.created_at >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $sql .= " AND al.created_at <= ?";
            $params[] = $filters['end_date'];
        }
        
        $sql .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Failed to fetch audit logs: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get total count of audit logs with filters
     * 
     * @param array $filters Filters
     * @return int
     */
    public function getLogsCount($filters = []) {
        $sql = "SELECT COUNT(*) as total FROM audit_log WHERE 1=1";
        $params = [];
        
        if (!empty($filters['user_id'])) {
            $sql .= " AND user_id = ?";
            $params[] = $filters['user_id'];
        }
        
        if (!empty($filters['action'])) {
            $sql .= " AND action = ?";
            $params[] = $filters['action'];
        }
        
        if (!empty($filters['entity_type'])) {
            $sql .= " AND entity_type = ?";
            $params[] = $filters['entity_type'];
        }
        
        if (!empty($filters['start_date'])) {
            $sql .= " AND created_at >= ?";
            $params[] = $filters['start_date'];
        }
        
        if (!empty($filters['end_date'])) {
            $sql .= " AND created_at <= ?";
            $params[] = $filters['end_date'];
        }
        
        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total'] ?? 0;
        } catch (PDOException $e) {
            error_log("Failed to count audit logs: " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get recent activity for dashboard
     * 
     * @param int $limit Number of records
     * @return array
     */
    public function getRecentActivity($limit = 10) {
        return $this->getLogs([], $limit, 0);
    }
    
    /**
     * Get activity for a specific entity
     * 
     * @param string $entityType Entity type
     * @param int $entityId Entity ID
     * @return array
     */
    public function getEntityActivity($entityType, $entityId) {
        return $this->getLogs([
            'entity_type' => $entityType,
            'entity_id' => $entityId
        ], 50, 0);
    }
    
    /**
     * Get client IP address
     * 
     * @return string
     */
    private function getClientIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        }
    }
    
    /**
     * Export audit logs to CSV
     * 
     * @param array $filters Filters
     * @param string $filename Output filename
     */
    public function exportToCSV($filters = [], $filename = 'audit_log.csv') {
        $logs = $this->getLogs($filters, 10000, 0);
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // Header
        fputcsv($output, [
            'Log ID',
            'Date/Time',
            'Username',
            'Action',
            'Entity Type',
            'Entity ID',
            'Old Values',
            'New Values',
            'IP Address'
        ]);
        
        // Data rows
        foreach ($logs as $log) {
            fputcsv($output, [
                $log['log_id'],
                $log['created_at'],
                $log['username'] ?? 'System',
                $log['action'],
                $log['entity_type'],
                $log['entity_id'],
                $log['old_values'],
                $log['new_values'],
                $log['ip_address']
            ]);
        }
        
        fclose($output);
        exit;
    }
}
