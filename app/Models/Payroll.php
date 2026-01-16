<?php
/**
 * Payroll Model
 * Handles salary calculations for all employee types:
 * - Permanent: Basic + HRA + DA - PF - Tax
 * - Contractual: Daily Rate × Net Days + DA (OD/Tour days)
 * - Intern: Stipend + DA (OD/Tour days)
 */

class Payroll {
    private $db;
    
    public function __construct() {
        require_once __DIR__ . '/../Config/database.php';
        $dbObj = new Database();
        $this->db = $dbObj->connect();
    }
    
    /**
     * Create payroll record with automatic employee type detection
     * @param array $data Payroll data
     * @return int|false Payroll ID or false on failure
     */
    public function createPayroll($data) {
        try {
            // Get employee details including type
            $employee = $this->getEmployeeDetails($data['employee_id']);
            if (!$employee) {
                throw new Exception("Employee not found");
            }
            
            // Apply employee-type-specific calculation
            $calculatedData = $this->applyEmployeeTypeRules($employee, $data);
            
            $stmt = $this->db->prepare("
                INSERT INTO payroll (
                    employee_id, employee_type, month, year,
                    basic_salary, daily_rate, stipend, working_days, od_tour_days,
                    hra, da, ta, da_on_ta, bonus,
                    da_amount, epf, nps, tax, professional_tax, other_deductions,
                    gross_salary, total_deductions, net_salary, remarks
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ");
            
            $success = $stmt->execute([
                $calculatedData['employee_id'],
                $calculatedData['employee_type'],
                $calculatedData['month'],
                $calculatedData['year'],
                $calculatedData['basic_salary'],
                $calculatedData['daily_rate'],
                $calculatedData['stipend'],
                $calculatedData['working_days'],
                $calculatedData['od_tour_days'],
                $calculatedData['hra'],
                $calculatedData['da'],
                $calculatedData['ta'],
                $calculatedData['da_on_ta'],
                $calculatedData['bonus'],
                $calculatedData['da_amount'],
                $calculatedData['epf'],
                $calculatedData['nps'],
                $calculatedData['tax'],
                $calculatedData['professional_tax'],
                $calculatedData['other_deductions'],
                $calculatedData['gross_salary'],
                $calculatedData['total_deductions'],
                $calculatedData['net_salary'],
                $calculatedData['remarks'] ?? ''
            ]);
            
            return $success ? $this->db->lastInsertId() : false;
        } catch (Exception $e) {
            error_log("Payroll::createPayroll - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Apply salary calculation rules based on employee type
     * @param array $employee Employee details
     * @param array $data Input payroll data
     * @return array Calculated payroll data
     */
    public function applyEmployeeTypeRules($employee, $data) {
        $employeeType = $employee['employee_type'] ?? 'permanent';
        
        switch ($employeeType) {
            case 'contractual':
                return $this->calculateContractualSalary($employee, $data);
            case 'intern':
                return $this->calculateInternSalary($employee, $data);
            case 'permanent':
            default:
                return $this->calculatePermanentSalary($employee, $data);
        }
    }
    
    /**
     * Calculate salary for Permanent employees
     * Formula: Basic + HRA + DA - PF - Tax - Deductions
     * @param array $employee Employee details
     * @param array $data Input data
     * @return array Calculated data
     */
    private function calculatePermanentSalary($employee, $data) {
        // Basic salary components
        $basic = (float)($data['basic_salary'] ?? $employee['basic_salary']);
        $hra = (float)($data['hra'] ?? 0);
        $da = (float)($data['da'] ?? 0);
        $ta = (float)($data['ta'] ?? 0);
        $daOnTa = (float)($data['da_on_ta'] ?? 0);
        $bonus = (float)($data['bonus'] ?? 0);
        
        // Deductions
        $epf = (float)($data['epf'] ?? 0);
        $nps = (float)($data['nps'] ?? 0);
        $tax = (float)($data['tax'] ?? 0);
        $profTax = (float)($data['professional_tax'] ?? 0);
        $otherDeductions = (float)($data['other_deductions'] ?? 0);
        
        // Calculate gross and net
        $gross = $basic + $hra + $da + $ta + $daOnTa + $bonus;
        $totalDeductions = $epf + $nps + $tax + $profTax + $otherDeductions;
        $net = $gross - $totalDeductions;
        
        return [
            'employee_id' => $employee['employee_id'],
            'employee_type' => 'permanent',
            'month' => $data['month'],
            'year' => $data['year'],
            'basic_salary' => $basic,
            'daily_rate' => 0,
            'stipend' => 0,
            'working_days' => 0,
            'od_tour_days' => 0,
            'hra' => $hra,
            'da' => $da,
            'ta' => $ta,
            'da_on_ta' => $daOnTa,
            'bonus' => $bonus,
            'da_amount' => 0,
            'epf' => $epf,
            'nps' => $nps,
            'tax' => $tax,
            'professional_tax' => $profTax,
            'other_deductions' => $otherDeductions,
            'gross_salary' => $gross,
            'total_deductions' => $totalDeductions,
            'net_salary' => $net,
            'remarks' => $data['remarks'] ?? ''
        ];
    }
    
    /**
     * Calculate salary for Contractual employees
     * Formula: (Daily Rate × Working Days) + (DA Rate × OD/Tour Days)
     * @param array $employee Employee details
     * @param array $data Input data
     * @return array Calculated data
     */
    private function calculateContractualSalary($employee, $data) {
        // Get attendance data
        $attendanceData = $this->getAttendanceData(
            $employee['employee_id'],
            $data['month'],
            $data['year']
        );
        
        $workingDays = $attendanceData['net_working_days'];
        $odTourDays = $attendanceData['od_tour_days'];
        
        // Get daily rate
        $dailyRate = (float)($data['daily_rate'] ?? $employee['daily_rate']);
        
        // Get DA configuration for this month
        require_once __DIR__ . '/SalaryConfig.php';
        $salaryConfig = new SalaryConfig();
        
        // Determine DA rate based on OD/Tour type
        $daRate = $salaryConfig->getDARate('contractual', $data['month'], $data['year'], 'regular');
        $tourDaRate = $salaryConfig->getDARate('contractual', $data['month'], $data['year'], 'tour');
        
        // Calculate base pay and DA
        $basePay = $dailyRate * $workingDays;
        $daAmount = $odTourDays * $daRate; // Simplified: use regular DA rate
        
        // Contractual employees: No HRA, PF, Tax
        $gross = $basePay + $daAmount;
        $net = $gross; // No deductions
        
        return [
            'employee_id' => $employee['employee_id'],
            'employee_type' => 'contractual',
            'month' => $data['month'],
            'year' => $data['year'],
            'basic_salary' => 0,
            'daily_rate' => $dailyRate,
            'stipend' => 0,
            'working_days' => $workingDays,
            'od_tour_days' => $odTourDays,
            'hra' => 0,
            'da' => 0,
            'ta' => 0,
            'da_on_ta' => 0,
            'bonus' => 0,
            'da_amount' => $daAmount,
            'epf' => 0,
            'nps' => 0,
            'tax' => 0,
            'professional_tax' => 0,
            'other_deductions' => 0,
            'gross_salary' => $gross,
            'total_deductions' => 0,
            'net_salary' => $net,
            'remarks' => "Daily Rate: ₹{$dailyRate} × {$workingDays} days + DA: ₹{$daRate} × {$odTourDays} OD/Tour days"
        ];
    }
    
    /**
     * Calculate salary for Interns
     * Formula: Stipend + (DA Rate × OD/Tour Days)
     * @param array $employee Employee details
     * @param array $data Input data
     * @return array Calculated data
     */
    private function calculateInternSalary($employee, $data) {
        // Get attendance data for OD/Tour days
        $attendanceData = $this->getAttendanceData(
            $employee['employee_id'],
            $data['month'],
            $data['year']
        );
        
        $odTourDays = $attendanceData['od_tour_days'];
        
        // Get stipend
        $stipend = (float)($data['stipend'] ?? $employee['stipend']);
        
        // Get DA configuration
        require_once __DIR__ . '/SalaryConfig.php';
        $salaryConfig = new SalaryConfig();
        $daRate = $salaryConfig->getDARate('intern', $data['month'], $data['year'], 'regular');
        
        // Calculate DA
        $daAmount = $odTourDays * $daRate;
        
        // Interns: No HRA, PF, Tax
        $gross = $stipend + $daAmount;
        $net = $gross; // No deductions
        
        return [
            'employee_id' => $employee['employee_id'],
            'employee_type' => 'intern',
            'month' => $data['month'],
            'year' => $data['year'],
            'basic_salary' => 0,
            'daily_rate' => 0,
            'stipend' => $stipend,
            'working_days' => 0,
            'od_tour_days' => $odTourDays,
            'hra' => 0,
            'da' => 0,
            'ta' => 0,
            'da_on_ta' => 0,
            'bonus' => 0,
            'da_amount' => $daAmount,
            'epf' => 0,
            'nps' => 0,
            'tax' => 0,
            'professional_tax' => 0,
            'other_deductions' => 0,
            'gross_salary' => $gross,
            'total_deductions' => 0,
            'net_salary' => $net,
            'remarks' => "Stipend: ₹{$stipend} + DA: ₹{$daRate} × {$odTourDays} OD/Tour days"
        ];
    }
    
    /**
     * Get attendance data for salary calculation
     * @param string $employeeId
     * @param int $month
     * @param int $year
     * @return array Attendance summary
     */
    public function getAttendanceData($employeeId, $month, $year) {
        try {
            require_once __DIR__ . '/Attendance.php';
            $attendanceModel = new Attendance();
            
            // Get all attendance records for the month
            $records = $attendanceModel->getAttendanceByEmployee($employeeId, $month, $year);
            
            // Count different types of days
            $presentDays = 0;
            $odTourDays = 0;
            $leaveDays = 0;
            
            foreach ($records as $record) {
                $status = strtolower($record['status'] ?? '');
                $leaveType = strtolower($record['leave_type'] ?? '');
                
                if ($status === 'present') {
                    $presentDays++;
                } elseif (in_array($leaveType, ['od', 'tour', 'od/tour'])) {
                    $odTourDays++;
                } elseif ($status === 'leave' || in_array($leaveType, ['el', 'cl', 'hpl', 'ccl', 'pl', 'rh'])) {
                    $leaveDays++;
                }
            }
            
            // Calculate working days (exclude Saturdays, Sundays, holidays)
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
            $weekends = $this->countWeekends($month, $year);
            $holidays = $this->countHolidays($month, $year);
            
            $totalWorkingDays = $daysInMonth - $weekends - $holidays;
            $netWorkingDays = $presentDays + $odTourDays; // Actual days worked
            
            return [
                'total_days' => $daysInMonth,
                'total_working_days' => $totalWorkingDays,
                'net_working_days' => $netWorkingDays,
                'present_days' => $presentDays,
                'od_tour_days' => $odTourDays,
                'leave_days' => $leaveDays,
                'weekends' => $weekends,
                'holidays' => $holidays
            ];
        } catch (Exception $e) {
            error_log("Payroll::getAttendanceData - " . $e->getMessage());
            return [
                'total_days' => 0,
                'total_working_days' => 0,
                'net_working_days' => 0,
                'present_days' => 0,
                'od_tour_days' => 0,
                'leave_days' => 0,
                'weekends' => 0,
                'holidays' => 0
            ];
        }
    }
    
    /**
     * Count weekends (Saturdays and Sundays) in a month
     * @param int $month
     * @param int $year
     * @return int Number of weekend days
     */
    private function countWeekends($month, $year) {
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $weekends = 0;
        
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $dayOfWeek = date('N', strtotime("$year-$month-$day"));
            if ($dayOfWeek == 6 || $dayOfWeek == 7) { // Saturday or Sunday
                $weekends++;
            }
        }
        
        return $weekends;
    }
    
    /**
     * Count holidays in a month
     * @param int $month
     * @param int $year
     * @return int Number of holidays
     */
    private function countHolidays($month, $year) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM holidays 
                WHERE MONTH(holiday_date) = ? AND YEAR(holiday_date) = ?
            ");
            $stmt->execute([$month, $year]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int)($result['count'] ?? 0);
        } catch (PDOException $e) {
            error_log("Payroll::countHolidays - " . $e->getMessage());
            return 0;
        }
    }
    
    /**
     * Get employee details including type
     * @param string $employeeId
     * @return array|false Employee data or false
     */
    private function getEmployeeDetails($employeeId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM employees WHERE employee_id = ?
            ");
            $stmt->execute([$employeeId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Payroll::getEmployeeDetails - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get payroll by ID
     * @param int $payrollId
     * @return array|false Payroll data or false
     */
    public function getPayrollById($payrollId) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM payroll WHERE payroll_id = ?");
            $stmt->execute([$payrollId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Payroll::getPayrollById - " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all payroll records for an employee
     * @param string $employeeId
     * @return array Payroll records
     */
    public function getPayrollByEmployee($employeeId) {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM payroll 
                WHERE employee_id = ? 
                ORDER BY year DESC, month DESC
            ");
            $stmt->execute([$employeeId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Payroll::getPayrollByEmployee - " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Check if payroll exists for employee in a month
     * @param string $employeeId
     * @param int $month
     * @param int $year
     * @return bool True if exists
     */
    public function payrollExists($employeeId, $month, $year) {
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count 
                FROM payroll 
                WHERE employee_id = ? AND month = ? AND year = ?
            ");
            $stmt->execute([$employeeId, $month, $year]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['count'] > 0;
        } catch (PDOException $e) {
            error_log("Payroll::payrollExists - " . $e->getMessage());
            return false;
        }
    }
}
