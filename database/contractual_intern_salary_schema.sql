-- =====================================================
-- CONTRACTUAL & INTERN SALARY CONFIGURATION SCHEMA
-- For NIELIT eHRMS System
-- =====================================================

-- 1. Add employee_type and daily_rate to employees table
ALTER TABLE employees 
ADD COLUMN IF NOT EXISTS employee_type ENUM('permanent', 'contractual', 'intern') DEFAULT 'permanent' AFTER designation,
ADD COLUMN IF NOT EXISTS daily_rate DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Daily rate for contractual employees' AFTER basic_salary,
ADD COLUMN IF NOT EXISTS stipend DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Fixed stipend for interns' AFTER daily_rate;

-- Add indexes for performance
ALTER TABLE employees 
ADD INDEX idx_employee_type (employee_type);

-- 2. Create salary_config table for DA rates and monthly settings
CREATE TABLE IF NOT EXISTS salary_config (
    config_id INT PRIMARY KEY AUTO_INCREMENT,
    
    -- DA Rates
    da_rate_contractual DECIMAL(10,2) DEFAULT 0.00 COMMENT 'DA rate per day for contractual employees',
    da_rate_intern DECIMAL(10,2) DEFAULT 0.00 COMMENT 'DA rate per day for interns',
    tour_da_rate_contractual DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Tour DA rate for contractual (if different)',
    tour_da_rate_intern DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Tour DA rate for interns (if different)',
    office_da_rate_contractual DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Office OD DA rate for contractual',
    office_da_rate_intern DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Office OD DA rate for interns',
    
    -- Monthly Control Flags
    month INT NOT NULL COMMENT '1-12',
    year INT NOT NULL COMMENT 'YYYY',
    da_enabled TINYINT(1) DEFAULT 1 COMMENT 'Enable/disable DA for this month',
    
    -- Metadata
    updated_by INT NOT NULL COMMENT 'User ID who updated',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    notes TEXT COMMENT 'Optional notes for this configuration',
    
    -- Ensure one config per month-year
    UNIQUE KEY unique_month_year (month, year),
    INDEX idx_month_year (month, year),
    FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Salary configuration for contractual and intern employees';

-- 3. Create default salary configuration for current year
INSERT INTO salary_config 
(da_rate_contractual, da_rate_intern, tour_da_rate_contractual, tour_da_rate_intern, 
 office_da_rate_contractual, office_da_rate_intern, month, year, da_enabled, updated_by, notes)
VALUES 
-- Default DA rates (₹ per day) - Accountant can update these
(300.00, 200.00, 500.00, 300.00, 300.00, 200.00, 1, 2026, 1, 1, 'Default DA rates for January 2026'),
(300.00, 200.00, 500.00, 300.00, 300.00, 200.00, 2, 2026, 1, 1, 'Default DA rates for February 2026'),
(300.00, 200.00, 500.00, 300.00, 300.00, 200.00, 3, 2026, 1, 1, 'Default DA rates for March 2026'),
(300.00, 200.00, 500.00, 300.00, 300.00, 200.00, 4, 2026, 1, 1, 'Default DA rates for April 2026'),
(300.00, 200.00, 500.00, 300.00, 300.00, 200.00, 5, 2026, 1, 1, 'Default DA rates for May 2026'),
(300.00, 200.00, 500.00, 300.00, 300.00, 200.00, 6, 2026, 1, 1, 'Default DA rates for June 2026'),
(300.00, 200.00, 500.00, 300.00, 300.00, 200.00, 7, 2026, 1, 1, 'Default DA rates for July 2026'),
(300.00, 200.00, 500.00, 300.00, 300.00, 200.00, 8, 2026, 1, 1, 'Default DA rates for August 2026'),
(300.00, 200.00, 500.00, 300.00, 300.00, 200.00, 9, 2026, 1, 1, 'Default DA rates for September 2026'),
(300.00, 200.00, 500.00, 300.00, 300.00, 200.00, 10, 2026, 1, 1, 'Default DA rates for October 2026'),
(300.00, 200.00, 500.00, 300.00, 300.00, 200.00, 11, 2026, 1, 1, 'Default DA rates for November 2026'),
(300.00, 200.00, 500.00, 300.00, 300.00, 200.00, 12, 2026, 1, 1, 'Default DA rates for December 2026')
ON DUPLICATE KEY UPDATE 
    da_rate_contractual = VALUES(da_rate_contractual),
    da_rate_intern = VALUES(da_rate_intern);

-- 4. Add employee_type field to payroll table for audit trail
ALTER TABLE payroll 
ADD COLUMN IF NOT EXISTS employee_type ENUM('permanent', 'contractual', 'intern') DEFAULT 'permanent' AFTER employee_id,
ADD COLUMN IF NOT EXISTS daily_rate DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Daily rate used (for contractual)' AFTER employee_type,
ADD COLUMN IF NOT EXISTS stipend DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Stipend used (for intern)' AFTER daily_rate,
ADD COLUMN IF NOT EXISTS working_days INT DEFAULT 0 COMMENT 'Net working days for calculation' AFTER stipend,
ADD COLUMN IF NOT EXISTS od_tour_days INT DEFAULT 0 COMMENT 'OD/Tour days for DA calculation' AFTER working_days,
ADD COLUMN IF NOT EXISTS da_amount DECIMAL(10,2) DEFAULT 0.00 COMMENT 'DA amount calculated' AFTER od_tour_days;

-- Add index for employee type filtering
ALTER TABLE payroll 
ADD INDEX idx_employee_type (employee_type);

-- 5. Sample contractual and intern employees for testing
-- Note: Adjust these IDs based on your existing employee records

-- Example: Convert or add contractual employees
-- UPDATE employees SET employee_type = 'contractual', daily_rate = 800.00 WHERE employee_id = 100;
-- UPDATE employees SET employee_type = 'intern', stipend = 10000.00 WHERE employee_id = 101;

-- Create sample contractual employee
INSERT INTO employees 
(employee_id, full_name, email, phone, date_of_birth, gender, address, department_id, 
 designation, employee_type, date_of_joining, daily_rate, basic_salary, account_number, bank_name, ifsc_code)
VALUES 
('NIELIT/CTR/2026/001', 'Rahul Kumar', 'rahul.contractual@nielit.gov.in', '9876543210', 
 '1995-05-15', 'Male', 'Bhubaneswar, Odisha', 1, 'Contractual Consultant', 'contractual', 
 '2026-01-01', 800.00, 0.00, '1234567890', 'State Bank of India', 'SBIN0001234')
ON DUPLICATE KEY UPDATE employee_type = 'contractual', daily_rate = 800.00;

-- Create sample intern
INSERT INTO employees 
(employee_id, full_name, email, phone, date_of_birth, gender, address, department_id, 
 designation, employee_type, date_of_joining, stipend, basic_salary, account_number, bank_name, ifsc_code)
VALUES 
('NIELIT/INT/2026/001', 'Priya Sharma', 'priya.intern@nielit.gov.in', '9876543211', 
 '2000-08-20', 'Female', 'Bhubaneswar, Odisha', 1, 'Research Intern', 'intern', 
 '2026-01-01', 10000.00, 0.00, '1234567891', 'State Bank of India', 'SBIN0001234')
ON DUPLICATE KEY UPDATE employee_type = 'intern', stipend = 10000.00;

-- =====================================================
-- VERIFICATION QUERIES
-- =====================================================

-- Check employee types
SELECT employee_type, COUNT(*) as count 
FROM employees 
GROUP BY employee_type;

-- View salary configurations
SELECT month, year, da_rate_contractual, da_rate_intern, 
       tour_da_rate_contractual, tour_da_rate_intern, da_enabled 
FROM salary_config 
ORDER BY year, month;

-- Sample contractual/intern employees
SELECT employee_id, full_name, designation, employee_type, 
       daily_rate, stipend, date_of_joining 
FROM employees 
WHERE employee_type IN ('contractual', 'intern');

-- =====================================================
-- NOTES FOR ACCOUNTANT
-- =====================================================
-- 
-- DA RATE CONFIGURATION:
-- - da_rate_contractual: Daily DA for contractual employees (₹300 default)
-- - da_rate_intern: Daily DA for interns (₹200 default)
-- - tour_da_rate_*: Higher DA rate for outstation tours (₹500/₹300 default)
-- - office_da_rate_*: DA rate for local OD (same as regular DA by default)
--
-- PAYROLL CALCULATION:
-- Contractual: (daily_rate × working_days) + (da_rate × od_tour_days)
-- Intern: stipend + (da_rate × od_tour_days)
--
-- MONTHLY CONTROL:
-- - Set da_enabled = 0 to disable DA for specific month
-- - Update rates before generating payslips for the month
-- 
-- =====================================================
