<?php
/**
 * Attendance Statement Helper
 * Handles calculations and business logic for attendance statements and payroll integration
 */

class AttendanceStatementHelper {
    private $db;
    
    public function __construct($db = null) {
        $this->db = $db ?: getDBConnection();
    }
    
    /**
     * Calculate monthly attendance summary for an employee
     * 
     * @param int $employeeId
     * @param int $month (1-12)
     * @param int $year
     * @return array Summary data
     */
    public function calculateMonthlySummary($employeeId, $month, $year) {
        // Get employee details
        $empStmt = $this->db->prepare("SELECT employee_type, status FROM employees WHERE employee_id = ?");
        $empStmt->execute([$employeeId]);
        $employee = $empStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            throw new Exception("Employee not found");
        }
        
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        
        // Calculate Saturdays, Sundays, and Government Holidays
        $weekendHolidays = $this->calculateWeekendAndHolidays($month, $year);
        
        // Get all leave/absence records for the month
        $leaveData = $this->getMonthlyLeaveData($employeeId, $month, $year);
        
        if ($employee['employee_type'] === 'regular') {
            return $this->calculateRegularEmployeeSummary(
                $employeeId, $month, $year, $daysInMonth, $weekendHolidays, $leaveData
            );
        } else {
            return $this->calculateContractEmployeeSummary(
                $employeeId, $month, $year, $daysInMonth, $weekendHolidays, $leaveData
            );
        }
    }
    
    /**
     * Calculate summary for regular employees
     */
    private function calculateRegularEmployeeSummary($employeeId, $month, $year, $daysInMonth, $weekendHolidays, $leaveData) {
        $odDays = 0;
        $tourDays = 0;
        $elDays = 0;
        $cclDays = 0;
        $plDays = 0;
        $clDays = 0;
        $rhDays = 0;
        
        // Sum up different leave types
        foreach ($leaveData as $leave) {
            switch ($leave['leave_type']) {
                case 'OD':
                    $odDays += $leave['total_days'];
                    break;
                case 'Tour':
                    $tourDays += $leave['total_days'];
                    break;
                case 'EL':
                    $elDays += $leave['total_days'];
                    break;
                case 'CCL':
                    $cclDays += $leave['total_days'];
                    break;
                case 'PL':
                    $plDays += $leave['total_days'];
                    break;
                case 'CL':
                    $clDays += $leave['total_days'];
                    break;
                case 'RH':
                    $rhDays += $leave['total_days'];
                    break;
            }
        }
        
        // Working days = Total days in month
        $workingDays = $daysInMonth;
        
        // Net Working Days = Working Days - (EL + CCL + PL + CL + RH)
        // OD and Tour are payable, so they're included in net working days
        $netWorkingDays = $workingDays - ($elDays + $cclDays + $plDays + $clDays + $rhDays);
        
        return [
            'employee_id' => $employeeId,
            'month' => $month,
            'year' => $year,
            'od_days' => $odDays,
            'tour_days' => $tourDays,
            'el_days' => $elDays,
            'ccl_days' => $cclDays,
            'pl_days' => $plDays,
            'cl_days' => $clDays,
            'rh_days' => $rhDays,
            'sat_days' => $weekendHolidays['saturdays'],
            'sun_days' => $weekendHolidays['sundays'],
            'gh_days' => $weekendHolidays['govt_holidays'],
            'working_days' => $workingDays,
            'net_working_days' => $netWorkingDays
        ];
    }
    
    /**
     * Calculate summary for contract/project/daily wage employees
     */
    private function calculateContractEmployeeSummary($employeeId, $month, $year, $daysInMonth, $weekendHolidays, $leaveData) {
        $absentDays = 0;
        $leaveDays = 0;
        
        // Sum up absent and leave days
        foreach ($leaveData as $leave) {
            if ($leave['leave_type'] === 'Absent') {
                $absentDays += $leave['total_days'];
            } else {
                $leaveDays += $leave['total_days'];
            }
        }
        
        // Total days in month
        $totalDays = $daysInMonth;
        
        // Payable days = Total days - Absent days (weekends/holidays are typically unpaid for contract staff)
        // Adjust based on company policy
        $payableDays = $totalDays - $absentDays - $weekendHolidays['sundays'] - $weekendHolidays['govt_holidays'];
        
        return [
            'employee_id' => $employeeId,
            'month' => $month,
            'year' => $year,
            'total_days' => $totalDays,
            'absent_days' => $absentDays,
            'payable_days' => max(0, $payableDays) // Ensure non-negative
        ];
    }
    
    /**
     * Calculate Saturdays, Sundays, and Government Holidays in a month
     */
    private function calculateWeekendAndHolidays($month, $year) {
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $saturdays = 0;
        $sundays = 0;
        
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dayOfWeek = date('w', mktime(0, 0, 0, $month, $day, $year));
            if ($dayOfWeek == 0) $sundays++;
            if ($dayOfWeek == 6) $saturdays++;
        }
        
        // Count government holidays from holidays table
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as holiday_count
            FROM holidays
            WHERE YEAR(holiday_date) = :year
            AND MONTH(holiday_date) = :month
            AND is_active = 1
            AND holiday_type = 'national'
        ");
        $stmt->execute([':year' => $year, ':month' => $month]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'saturdays' => $saturdays,
            'sundays' => $sundays,
            'govt_holidays' => $result['holiday_count'] ?? 0
        ];
    }
    
    /**
     * Get all leave/absence records for an employee in a month
     */
    private function getMonthlyLeaveData($employeeId, $month, $year) {
        $stmt = $this->db->prepare("
            SELECT leave_type, total_days, nature_of_leave
            FROM attendance_leave_details
            WHERE employee_id = :employee_id
            AND YEAR(start_date) = :year
            AND MONTH(start_date) = :month
            AND status = 'approved'
            ORDER BY start_date
        ");
        $stmt->execute([
            ':employee_id' => $employeeId,
            ':year' => $year,
            ':month' => $month
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Save or update monthly attendance summary
     */
    public function saveMonthlySummary($summaryData) {
        $fields = array_keys($summaryData);
        $placeholders = array_map(function($field) { return ":$field"; }, $fields);
        
        $updateFields = array_map(function($field) {
            return "$field = VALUES($field)";
        }, array_diff($fields, ['employee_id', 'month', 'year']));
        
        $sql = "INSERT INTO monthly_attendance_summary 
                (" . implode(', ', $fields) . ") 
                VALUES (" . implode(', ', $placeholders) . ")
                ON DUPLICATE KEY UPDATE " . implode(', ', $updateFields);
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($summaryData);
    }
    
    /**
     * Calculate payroll for an employee based on attendance
     */
    public function calculatePayroll($employeeId, $month, $year) {
        // Get employee salary details
        $empStmt = $this->db->prepare("
            SELECT e.*, s.basic_salary
            FROM employees e
            LEFT JOIN salary s ON e.employee_id = s.employee_id
            WHERE e.employee_id = :employee_id
        ");
        $empStmt->execute([':employee_id' => $employeeId]);
        $employee = $empStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee || !$employee['basic_salary']) {
            throw new Exception("Employee salary not configured");
        }
        
        // Get attendance summary
        $summaryStmt = $this->db->prepare("
            SELECT * FROM monthly_attendance_summary
            WHERE employee_id = :employee_id
            AND month = :month
            AND year = :year
        ");
        $summaryStmt->execute([
            ':employee_id' => $employeeId,
            ':month' => $month,
            ':year' => $year
        ]);
        $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$summary) {
            throw new Exception("Attendance summary not found. Please generate attendance statement first.");
        }
        
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $basicSalary = $employee['basic_salary'];
        $perDaySalary = $basicSalary / $daysInMonth;
        
        // Calculate payable days based on employee type
        if ($employee['employee_type'] === 'regular') {
            $payableDays = $summary['net_working_days'];
        } else {
            $payableDays = $summary['payable_days'];
        }
        
        $grossSalary = $perDaySalary * $payableDays;
        
        return [
            'employee_id' => $employeeId,
            'month' => $month,
            'year' => $year,
            'basic_salary' => $basicSalary,
            'per_day_salary' => round($perDaySalary, 2),
            'payable_days' => $payableDays,
            'gross_salary' => round($grossSalary, 2),
            'attendance_summary_id' => $summary['summary_id']
        ];
    }
    
    /**
     * Save payroll snapshot
     */
    public function savePayrollSnapshot($payrollData) {
        $sql = "INSERT INTO payroll_monthly_snapshot 
                (employee_id, month, year, basic_salary, per_day_salary, payable_days, 
                 gross_salary, net_salary, attendance_summary_id, processed_by)
                VALUES 
                (:employee_id, :month, :year, :basic_salary, :per_day_salary, :payable_days,
                 :gross_salary, :net_salary, :attendance_summary_id, :processed_by)
                ON DUPLICATE KEY UPDATE
                basic_salary = VALUES(basic_salary),
                per_day_salary = VALUES(per_day_salary),
                payable_days = VALUES(payable_days),
                gross_salary = VALUES(gross_salary),
                net_salary = VALUES(net_salary),
                attendance_summary_id = VALUES(attendance_summary_id),
                processed_by = VALUES(processed_by),
                processed_at = CURRENT_TIMESTAMP";
        
        $payrollData['net_salary'] = $payrollData['gross_salary'] - ($payrollData['deductions'] ?? 0);
        $payrollData['processed_by'] = $_SESSION['user_id'] ?? null;
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($payrollData);
    }
    
    /**
     * Bulk process attendance for all employees in a month
     */
    public function processMonthlyAttendance($month, $year) {
        $stmt = $this->db->prepare("SELECT employee_id FROM employees WHERE status = 'active'");
        $stmt->execute();
        $employees = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $processed = 0;
        $errors = [];
        
        foreach ($employees as $employeeId) {
            try {
                $summary = $this->calculateMonthlySummary($employeeId, $month, $year);
                $this->saveMonthlySummary($summary);
                $processed++;
            } catch (Exception $e) {
                $errors[] = "Employee ID $employeeId: " . $e->getMessage();
            }
        }
        
        return [
            'success' => true,
            'processed' => $processed,
            'errors' => $errors
        ];
    }
}
