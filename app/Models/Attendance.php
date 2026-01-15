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
    public function markAttendance($employeeId, $date, $status = 'Present') {
        try {
            $query = "INSERT INTO " . $this->table . " 
                      (employee_id, date, status) 
                      VALUES (:employee_id, :date, :status)
                      ON DUPLICATE KEY UPDATE status = :status";
            
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':employee_id', $employeeId, PDO::PARAM_INT);
            $stmt->bindParam(':date', $date);
            $stmt->bindParam(':status', $status);
            
            return $stmt->execute();
        } catch (PDOException $e) {
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
}
