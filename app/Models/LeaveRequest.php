<?php

class LeaveRequest {
    private $conn;

    public function __construct() {
        require_once __DIR__ . '/../Config/database.php';
        $database = new Database();
        $this->conn = $database->connect();
    }

    /**
     * Submit a leave request
     */
    public function submitLeaveRequest($employeeId, $employeeName, $leaveType, $startDate, $endDate, $reason) {
        try {
            $sql = "INSERT INTO leave_requests 
                    (employee_id, employee_name, leave_type, start_date, end_date, reason, status, request_date) 
                    VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())";
            
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([$employeeId, $employeeName, $leaveType, $startDate, $endDate, $reason]);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Get leave requests by employee
     */
    public function getLeaveRequestsByEmployee($employeeId) {
        try {
            $sql = "SELECT * FROM leave_requests 
                    WHERE employee_id = ? 
                    ORDER BY request_date DESC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$employeeId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get all leave requests with filters
     */
    public function getAllLeaveRequests($status = null, $employeeId = null) {
        try {
            $sql = "SELECT lr.*, 
                           e.designation, 
                           d.department_name 
                    FROM leave_requests lr
                    JOIN employees e ON lr.employee_id = e.employee_id
                    LEFT JOIN departments d ON e.department_id = d.department_id
                    WHERE 1=1";
            
            $params = [];
            
            if ($status) {
                $sql .= " AND lr.status = ?";
                $params[] = $status;
            }
            
            if ($employeeId) {
                $sql .= " AND lr.employee_id = ?";
                $params[] = $employeeId;
            }
            
            $sql .= " ORDER BY lr.request_date DESC";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Approve leave request
     */
    public function approveLeaveRequest($requestId, $reviewerId, $reviewerName, $comments = null) {
        try {
            $sql = "UPDATE leave_requests 
                    SET status = 'approved', 
                        reviewed_by = ?, 
                        reviewed_by_name = ?, 
                        review_date = NOW(),
                        review_comments = ?
                    WHERE request_id = ?";
            
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([$reviewerId, $reviewerName, $comments, $requestId]);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Reject leave request
     */
    public function rejectLeaveRequest($requestId, $reviewerId, $reviewerName, $comments) {
        try {
            $sql = "UPDATE leave_requests 
                    SET status = 'rejected', 
                        reviewed_by = ?, 
                        reviewed_by_name = ?, 
                        review_date = NOW(),
                        review_comments = ?
                    WHERE request_id = ?";
            
            $stmt = $this->conn->prepare($sql);
            return $stmt->execute([$reviewerId, $reviewerName, $comments, $requestId]);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * Get leave balance summary
     */
    public function getLeaveBalance($employeeId, $year = null) {
        if (!$year) {
            $year = date('Y');
        }
        
        try {
            $sql = "SELECT 
                        leave_type,
                        COUNT(*) as total_days,
                        SUM(DATEDIFF(end_date, start_date) + 1) as days_taken
                    FROM leave_requests 
                    WHERE employee_id = ? 
                    AND status = 'approved'
                    AND YEAR(start_date) = ?
                    GROUP BY leave_type";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([$employeeId, $year]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Get pending leave requests count
     */
    public function getPendingCount() {
        try {
            $sql = "SELECT COUNT(*) as count FROM leave_requests WHERE status = 'pending'";
            $stmt = $this->conn->query($sql);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] ?? 0;
        } catch (PDOException $e) {
            return 0;
        }
    }
}
