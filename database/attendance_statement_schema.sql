-- ========================================
-- ATTENDANCE & ABSENTEE STATEMENT MODULE
-- Database Schema for NIELIT Payroll System
-- ========================================

-- Add employee_type to existing employees table if not exists
ALTER TABLE employees 
ADD COLUMN IF NOT EXISTS employee_type ENUM('regular', 'contract', 'project', 'daily_wage') DEFAULT 'regular' AFTER status,
ADD COLUMN IF NOT EXISTS contract_end_date DATE NULL AFTER employee_type,
ADD COLUMN IF NOT EXISTS employee_group VARCHAR(100) NULL COMMENT 'Project Staff, Daily Wage, etc.' AFTER contract_end_date,
ADD COLUMN IF NOT EXISTS location VARCHAR(100) DEFAULT 'NIELIT Bhubaneswar' AFTER employee_group;

-- Monthly Attendance Summary Table
CREATE TABLE IF NOT EXISTS monthly_attendance_summary (
    summary_id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    month INT NOT NULL COMMENT '1-12',
    year INT NOT NULL COMMENT 'YYYY',
    
    -- Regular Employee Fields
    od_days DECIMAL(4,1) DEFAULT 0 COMMENT 'Official Duty days',
    tour_days DECIMAL(4,1) DEFAULT 0 COMMENT 'Tour days',
    el_days DECIMAL(4,1) DEFAULT 0 COMMENT 'Earned Leave',
    ccl_days DECIMAL(4,1) DEFAULT 0 COMMENT 'Commuted Leave',
    pl_days DECIMAL(4,1) DEFAULT 0 COMMENT 'Privilege Leave',
    cl_days DECIMAL(4,1) DEFAULT 0 COMMENT 'Casual Leave',
    rh_days DECIMAL(4,1) DEFAULT 0 COMMENT 'Restricted Holiday',
    sat_days DECIMAL(4,1) DEFAULT 0 COMMENT 'Saturdays',
    sun_days DECIMAL(4,1) DEFAULT 0 COMMENT 'Sundays',
    gh_days DECIMAL(4,1) DEFAULT 0 COMMENT 'Government Holidays',
    working_days DECIMAL(4,1) DEFAULT 0 COMMENT 'Total working days in month',
    net_working_days DECIMAL(4,1) DEFAULT 0 COMMENT 'Net days for salary',
    
    -- Contract Employee Fields
    absent_days DECIMAL(4,1) DEFAULT 0 COMMENT 'Unpaid absent days',
    total_days DECIMAL(4,1) DEFAULT 0 COMMENT 'Total days in month',
    payable_days DECIMAL(4,1) DEFAULT 0 COMMENT 'Days for salary payment',
    
    remarks TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    UNIQUE KEY unique_employee_month (employee_id, month, year),
    INDEX idx_month_year (month, year),
    INDEX idx_employee (employee_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Monthly attendance summary for payroll processing';

-- Leave/Absence Detail Records
CREATE TABLE IF NOT EXISTS attendance_leave_details (
    detail_id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    leave_type ENUM('OD', 'Tour', 'EL', 'CCL', 'PL', 'CL', 'RH', 'Absent', 'Half_Day') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_days DECIMAL(4,1) NOT NULL COMMENT 'Including half-days as 0.5',
    is_half_day BOOLEAN DEFAULT FALSE,
    half_day_type ENUM('FN', 'AN') NULL COMMENT 'Forenoon or Afternoon',
    nature_of_leave VARCHAR(255) NULL COMMENT 'Detailed reason',
    remarks TEXT NULL,
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'approved',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    INDEX idx_employee_dates (employee_id, start_date, end_date),
    INDEX idx_leave_type (leave_type),
    INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Detailed leave and absence records';

-- Payroll Monthly Snapshot
CREATE TABLE IF NOT EXISTS payroll_monthly_snapshot (
    snapshot_id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    month INT NOT NULL,
    year INT NOT NULL,
    basic_salary DECIMAL(10,2) NOT NULL,
    per_day_salary DECIMAL(10,2) NOT NULL,
    payable_days DECIMAL(4,1) NOT NULL,
    gross_salary DECIMAL(10,2) NOT NULL,
    deductions DECIMAL(10,2) DEFAULT 0,
    net_salary DECIMAL(10,2) NOT NULL,
    attendance_summary_id INT NULL,
    payment_status ENUM('pending', 'processed', 'paid') DEFAULT 'pending',
    processed_at TIMESTAMP NULL,
    processed_by INT NULL,
    remarks TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (attendance_summary_id) REFERENCES monthly_attendance_summary(summary_id) ON DELETE SET NULL,
    UNIQUE KEY unique_payroll_month (employee_id, month, year),
    INDEX idx_month_year (month, year),
    INDEX idx_payment_status (payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Monthly payroll snapshot linked to attendance';

-- Insert sample data for testing
-- Update existing employees with employee_type
UPDATE employees SET employee_type = 'regular', location = 'NIELIT Bhubaneswar' WHERE employee_id IN (18, 19, 20);
UPDATE employees SET employee_type = 'contract', employee_group = 'Project Staff', location = 'NIELIT Bhubaneswar' WHERE employee_id IN (21);
UPDATE employees SET employee_type = 'daily_wage', employee_group = 'Daily Wage Workers', location = 'NIELIT Balasore' WHERE employee_id IN (22);

-- Sample attendance summary for January 2026
INSERT INTO monthly_attendance_summary 
(employee_id, month, year, od_days, tour_days, el_days, cl_days, sat_days, sun_days, gh_days, working_days, net_working_days, remarks) 
VALUES
(18, 1, 2026, 0, 0, 0, 0, 4, 5, 1, 31, 21, 'Full attendance'),
(19, 1, 2026, 2, 0, 1, 0, 4, 5, 1, 31, 20, 'OD on 10th & 11th, CL on 15th'),
(20, 1, 2026, 0, 1, 0, 1, 4, 5, 1, 31, 20, 'Tour on 20th, CL on 25th')
ON DUPLICATE KEY UPDATE 
od_days = VALUES(od_days),
tour_days = VALUES(tour_days),
el_days = VALUES(el_days),
cl_days = VALUES(cl_days),
working_days = VALUES(working_days),
net_working_days = VALUES(net_working_days),
remarks = VALUES(remarks);

-- Sample for contract employees (absent days)
INSERT INTO monthly_attendance_summary 
(employee_id, month, year, total_days, absent_days, payable_days, remarks) 
VALUES
(21, 1, 2026, 31, 2, 29, 'Absent on 8th & 22nd'),
(22, 1, 2026, 31, 0, 31, 'Full attendance')
ON DUPLICATE KEY UPDATE 
total_days = VALUES(total_days),
absent_days = VALUES(absent_days),
payable_days = VALUES(payable_days),
remarks = VALUES(remarks);

-- Sample leave detail records
INSERT INTO attendance_leave_details 
(employee_id, leave_type, start_date, end_date, total_days, nature_of_leave, status) 
VALUES
(19, 'OD', '2026-01-10', '2026-01-11', 2, 'Official meeting at NIELIT HQ Delhi', 'approved'),
(19, 'CL', '2026-01-15', '2026-01-15', 1, 'Personal work', 'approved'),
(20, 'Tour', '2026-01-20', '2026-01-20', 1, 'Training program at Balasore center', 'approved'),
(20, 'CL', '2026-01-25', '2026-01-25', 1, 'Medical', 'approved'),
(21, 'Absent', '2026-01-08', '2026-01-08', 1, NULL, 'approved'),
(21, 'Absent', '2026-01-22', '2026-01-22', 1, NULL, 'approved')
ON DUPLICATE KEY UPDATE total_days = VALUES(total_days);

-- Create view for easy reporting
CREATE OR REPLACE VIEW vw_attendance_statement AS
SELECT 
    e.employee_id,
    e.full_name,
    e.designation,
    e.employee_type,
    e.employee_group,
    e.location,
    e.status,
    e.contract_end_date,
    mas.month,
    mas.year,
    mas.od_days,
    mas.tour_days,
    mas.el_days,
    mas.ccl_days,
    mas.pl_days,
    mas.cl_days,
    mas.rh_days,
    mas.sat_days,
    mas.sun_days,
    mas.gh_days,
    mas.working_days,
    mas.net_working_days,
    mas.absent_days,
    mas.total_days,
    mas.payable_days,
    mas.remarks
FROM employees e
LEFT JOIN monthly_attendance_summary mas ON e.employee_id = mas.employee_id
WHERE e.status = 'active'
ORDER BY e.employee_type, e.employee_group, e.full_name;
