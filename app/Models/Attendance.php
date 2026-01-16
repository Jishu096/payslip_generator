<?php

class Attendance {
    private $conn;
    private $table = 'attendance';

    public function __construct() {
        require_once __DIR__ . '/../Config/database.php';
        $this->conn = getDBConnection();
    }

    /**
     * Get attendance records for a specific employee
     * @param int $employeeId
     * @return array
     */
    public function getAttendanceByEmployee($employeeId) {
        try {
            $query = "SELECT * FROM " . $this->table . " 
                      WHERE employee_id = :employee_id 
                      ORDER BY date DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':employee_id', $employeeId, PDO::PARAM_INT);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            // Return empty array if table doesn't exist or query fails
            return [];
        }
    }

    /**
     * Get attendance records for a specific employee within a date range
     * @param int $employeeId
     * @param string $startDate
     * @param string $endDate
     * @return array
     */
    public function getAttendanceByDateRange($employeeId, $startDate, $endDate) {
        try {
            $query = "SELECT * FROM " . $this->table . " 
                      WHERE employee_id = :employee_id 
                      AND date BETWEEN :start_date AND :end_date
                      ORDER BY date DESC";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':employee_id', $employeeId, PDO::PARAM_INT);
            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * Mark attendance for an employee
     * @param int $employeeId
     * @param string $date
     * @param string $status (Present, Absent, Leave, etc.)
     * @return bool
     */
    public function markAttendance($employeeId, $date, $status = 'present') {
        try {
            // Normalize status to lowercase to match ENUM values
            $status = strtolower($status);
            
            $query = "INSERT INTO " . $this->table . " 
                      (employee_id, date, status) 
                      VALUES (:employee_id, :date, :status)
                      ON DUPLICATE KEY UPDATE status = VALUES(status)";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':employee_id', $employeeId, PDO::PARAM_INT);
            $stmt->bindParam(':date', $date);
            $stmt->bindParam(':status', $status);
            
            return $stmt->execute();
        } catch (PDOException $e) {
            // Log error for debugging
            error_log("Attendance Error: " . $e->getMessage());
            error_log("Employee ID: $employeeId, Date: $date, Status: $status");
            return false;
        }
    }

    /**
     * Get attendance summary for an employee
     * @param int $employeeId
     * @param string $month (format: YYYY-MM)
     * @return array
     */
    public function getAttendanceSummary($employeeId, $month) {
        try {
            $query = "SELECT 
                        COUNT(*) as total_days,
                        SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present_days,
                        SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent_days,
                        SUM(CASE WHEN status = 'Leave' THEN 1 ELSE 0 END) as leave_days
                      FROM " . $this->table . " 
                      WHERE employee_id = :employee_id 
                      AND DATE_FORMAT(date, '%Y-%m') = :month";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':employee_id', $employeeId, PDO::PARAM_INT);
            $stmt->bindParam(':month', $month);
            $stmt->execute();
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [
                'total_days' => 0,
                'present_days' => 0,
                'absent_days' => 0,
                'leave_days' => 0
            ];
        }
    }
    /**
     * Get daily attendance statistics
     * @param string $date (format: YYYY-MM-DD)
     * @return array
     */
    public function getDailyStats($date) {
        try {
            $query = "SELECT 
                        status, COUNT(*) as count
                      FROM " . $this->table . " 
                      WHERE date = :date 
                      GROUP BY status";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':date', $date);
            $stmt->execute();
            
            $stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Ensure all keys exist
            return [
                'present' => $stats['present'] ?? 0,
                'absent' => $stats['absent'] ?? 0,
                'leave' => $stats['leave'] ?? 0
            ];
        } catch (PDOException $e) {
            return ['present' => 0, 'absent' => 0, 'leave' => 0];
        }
    }
}
