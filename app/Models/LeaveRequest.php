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
            // Start transaction
            $this->conn->beginTransaction();
            
            // Get leave request details
            $leaveStmt = $this->conn->prepare("SELECT * FROM leave_requests WHERE leave_id = ?");
            $leaveStmt->execute([$requestId]);
            $leave = $leaveStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$leave) {
                $this->conn->rollBack();
                error_log("Leave request not found: $requestId");
                return false;
            }
            
            // Update leave request status
            $sql = "UPDATE leave_requests 
                    SET status = 'approved', 
                        reviewed_by = ?, 
                        reviewed_by_name = ?, 
                        review_date = NOW(),
                        review_comments = ?
                    WHERE leave_id = ?";
            
            $stmt = $this->conn->prepare($sql);
            $result = $stmt->execute([$reviewerId, $reviewerName, $comments, $requestId]);
            
            if (!$result) {
                $this->conn->rollBack();
                error_log("Leave approval failed for request_id: $requestId - " . print_r($stmt->errorInfo(), true));
                return false;
            }
            
            // Automatically create attendance entries for all leave dates
            $startDate = new DateTime($leave['start_date']);
            $endDate = new DateTime($leave['end_date']);
            $endDate->modify('+1 day'); // Include end date
            
            $interval = new DateInterval('P1D');
            $dateRange = new DatePeriod($startDate, $interval, $endDate);
            
            $attendanceInsert = $this->conn->prepare("
                INSERT INTO attendance (employee_id, date, status, leave_type, remarks, verification_status, workflow_status)
                VALUES (?, ?, 'leave', ?, ?, 'Verified', 'draft')
                ON DUPLICATE KEY UPDATE 
                    status = 'leave',
                    leave_type = VALUES(leave_type),
                    remarks = VALUES(remarks),
                    verification_status = 'Verified'
            ");
            
            $remarks = "Leave approved by " . $reviewerName . " - " . $leave['reason'];
            
            foreach ($dateRange as $date) {
                $dateStr = $date->format('Y-m-d');
                $attendanceInsert->execute([
                    $leave['employee_id'],
                    $dateStr,
                    $leave['leave_type'],
                    $remarks
                ]);
            }
            
            // Commit transaction
            $this->conn->commit();
            
            // Log success
            $daysCount = iterator_count($dateRange);
            error_log("Leave approved and $daysCount attendance entries created for employee #{$leave['employee_id']}");
            
            return true;
        } catch (PDOException $e) {
            $this->conn->rollBack();
            error_log("Leave approval exception: " . $e->getMessage());
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
                    WHERE leave_id = ?";
            
            $stmt = $this->conn->prepare($sql);
            $result = $stmt->execute([$reviewerId, $reviewerName, $comments, $requestId]);
            
            // Log error if failed
            if (!$result) {
                error_log("Leave rejection failed for request_id: $requestId - " . print_r($stmt->errorInfo(), true));
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("Leave rejection exception: " . $e->getMessage());
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
