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
        // Get employee details - Update to fetch joining/resignation dates
        // Note: Using 'join_date' as seen in Employee model. Assuming 'resignation_date' or 'contract_end_date' might exist.
        // If resignation_date logic is needed, ensure the column exists. For now using contract_end_date for non-regular if needed, 
        // or assuming join_date is the primary one for pro-rata.
        $empStmt = $this->db->prepare("SELECT employee_type, status, join_date, contract_end_date FROM employees WHERE employee_id = ?");
        $empStmt->execute([$employeeId]);
        $employee = $empStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employee) {
            throw new Exception("Employee not found");
        }
        
        // Determine active period in the month
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $monthStartDate = "$year-$month-01";
        $monthEndDate = "$year-$month-$daysInMonth";
        
        // Start Date = Max(MonthStart, JoinDate)
        $calcStartDate = $monthStartDate;
        if (!empty($employee['join_date']) && $employee['join_date'] > $monthStartDate) {
            $calcStartDate = $employee['join_date'];
        }
        
        // End Date = Min(MonthEnd, Resignation/ContractEnd)
        // If employee is inactive, we might check a resignation date if it exists, or rely on status.
        // For now, checking contract_end_date for all types if present and earlier than month end.
        $calcEndDate = $monthEndDate;
        if (!empty($employee['contract_end_date']) && $employee['contract_end_date'] < $monthEndDate) {
            $calcEndDate = $employee['contract_end_date'];
        }
        
        // Ensure start <= end (case where joined after month end or left before month start)
        if ($calcStartDate > $calcEndDate) {
             // Employee not active in this month
             $daysInMonth = 0; // effectively 0 working days
             $weekendHolidays = ['saturdays' => 0, 'sundays' => 0, 'govt_holidays' => 0];
        } else {
             // Calculate working days & holidays specific to this active period
             $weekendHolidays = $this->calculateWeekendAndHolidaysForPeriod($calcStartDate, $calcEndDate);
             
             // The 'daysInMonth' for calculation purposes is now the number of days in the active period
             // converting to DateTime to calculate difference
             $start = new DateTime($calcStartDate);
             $end = new DateTime($calcEndDate);
             $daysInMonth = $end->diff($start)->days + 1;
        }

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
        
        // Working days = Total days - Weekends - Holidays (Standard working days in month)
        $standardWorkingDays = $daysInMonth - ($weekendHolidays['saturdays'] + $weekendHolidays['sundays'] + $weekendHolidays['govt_holidays']);
        $workingDays = $standardWorkingDays;
        
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
        $startDate = "$year-$month-01";
        $endDate = "$year-$month-$daysInMonth";
        return $this->calculateWeekendAndHolidaysForPeriod($startDate, $endDate);
    }

    /**
     * Calculate Saturdays, Sundays, and Government Holidays in a date range
     */
    private function calculateWeekendAndHolidaysForPeriod($startDate, $endDate) {
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        
        $saturdays = 0;
        $sundays = 0;
        
        // Loop through days to count weekends
        $period = new DatePeriod($start, new DateInterval('P1D'), $end->modify('+1 day'));
        
        foreach ($period as $dt) {
            $dayOfWeek = $dt->format('w'); // 0 (Sun) - 6 (Sat)
            if ($dayOfWeek == 0) $sundays++;
            if ($dayOfWeek == 6) $saturdays++;
        }
        
        // Count government holidays from holidays table within range
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as holiday_count
            FROM holidays
            WHERE holiday_date BETWEEN :start_date AND :end_date
            AND is_active = 1
            AND holiday_type = 'national'
        ");
        $stmt->execute([':start_date' => $startDate, ':end_date' => $endDate]);
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
