-- ============================================
-- 7th CPC Pay Level Schema for Permanent Employees
-- Created: February 2026
-- ============================================

-- 1. Create Pay Levels Master Table (7th CPC)
CREATE TABLE IF NOT EXISTS `pay_levels` (
    `level_id` int(11) NOT NULL AUTO_INCREMENT,
    `level_name` varchar(50) NOT NULL COMMENT 'e.g., Level 1, Level 2, etc.',
    `level_number` int(11) NOT NULL COMMENT '1-18 as per 7th CPC',
    `min_basic` decimal(12,2) NOT NULL COMMENT 'Minimum basic pay for this level',
    `max_basic` decimal(12,2) NOT NULL COMMENT 'Maximum basic pay for this level',
    `transport_allowance` decimal(10,2) DEFAULT 0.00 COMMENT 'Fixed TA for this level',
    `description` varchar(255) DEFAULT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`level_id`),
    UNIQUE KEY `unique_level_number` (`level_number`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Insert 7th CPC Pay Levels (as of 2026 with DA adjustments)
INSERT INTO `pay_levels` (`level_name`, `level_number`, `min_basic`, `max_basic`, `transport_allowance`, `description`) VALUES
('Level 1', 1, 18000, 56900, 1350, 'Group C - Multi-Tasking Staff'),
('Level 2', 2, 19900, 63200, 1350, 'Group C - Semi-Skilled'),
('Level 3', 3, 21700, 69100, 1350, 'Group C - Skilled'),
('Level 4', 4, 25500, 81100, 1350, 'Group C - Highly Skilled'),
('Level 5', 5, 29200, 92300, 1350, 'Group C - Clerical'),
('Level 6', 6, 35400, 112400, 3600, 'Group B - Junior Scale'),
('Level 7', 7, 44900, 142400, 3600, 'Group B - Senior Scale'),
('Level 8', 8, 47600, 151100, 3600, 'Group B - Selection Grade'),
('Level 9', 9, 53100, 167800, 3600, 'Group B - Junior Administrative'),
('Level 10', 10, 56100, 177500, 7200, 'Group A - Junior Scale'),
('Level 11', 11, 67700, 208700, 7200, 'Group A - Senior Scale'),
('Level 12', 12, 78800, 209200, 7200, 'Group A - Junior Administrative Grade'),
('Level 13', 13, 123100, 215900, 7200, 'Group A - Selection Grade'),
('Level 13A', 14, 131100, 216600, 7200, 'Group A - Senior Administrative Grade'),
('Level 14', 15, 144200, 218200, 7200, 'Group A - Higher Administrative Grade'),
('Level 15', 16, 182200, 224100, 7200, 'Group A - Senior Higher Administrative Grade'),
('Level 16', 17, 205400, 224400, 7200, 'Apex Scale'),
('Level 17', 18, 225000, 225000, 7200, 'Cabinet Secretary Grade');

-- 3. Add pay_level_id to employees table
ALTER TABLE `employees` 
ADD COLUMN `pay_level_id` int(11) DEFAULT NULL AFTER `basic_salary`,
ADD COLUMN `hra_type` enum('city_a', 'city_b', 'city_c') DEFAULT 'city_b' COMMENT 'HRA: A=24%, B=16%, C=8%' AFTER `pay_level_id`,
ADD CONSTRAINT `fk_employee_pay_level` FOREIGN KEY (`pay_level_id`) REFERENCES `pay_levels`(`level_id`) ON DELETE SET NULL;

-- 4. Add new salary component columns to payroll table
ALTER TABLE `payroll`
ADD COLUMN `canteen_subsidy` decimal(10,2) DEFAULT 0.00 AFTER `bonus`,
ADD COLUMN `cpf_deduction` decimal(10,2) DEFAULT 0.00 AFTER `nps_deduction`,
ADD COLUMN `sudexo_deduction` decimal(10,2) DEFAULT 0.00 AFTER `cpf_deduction`,
ADD COLUMN `income_tax` decimal(10,2) DEFAULT 0.00 AFTER `sudexo_deduction`,
ADD COLUMN `pay_level` varchar(20) DEFAULT NULL AFTER `income_tax`;

-- 5. Create Salary Components Configuration Table
CREATE TABLE IF NOT EXISTS `salary_components` (
    `component_id` int(11) NOT NULL AUTO_INCREMENT,
    `component_code` varchar(20) NOT NULL,
    `component_name` varchar(100) NOT NULL,
    `component_type` enum('earning', 'deduction') NOT NULL,
    `calculation_type` enum('percentage', 'fixed', 'manual') NOT NULL,
    `base_field` varchar(50) DEFAULT 'basic' COMMENT 'Field to calculate percentage on',
    `default_rate` decimal(10,4) DEFAULT 0.00 COMMENT 'Percentage or fixed amount',
    `applies_to` enum('permanent', 'contractual', 'intern', 'all') DEFAULT 'permanent',
    `is_mandatory` tinyint(1) DEFAULT 1,
    `display_order` int(11) DEFAULT 0,
    `is_active` tinyint(1) DEFAULT 1,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`component_id`),
    UNIQUE KEY `unique_component_code` (`component_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Insert Default Salary Components for Permanent Employees
INSERT INTO `salary_components` (`component_code`, `component_name`, `component_type`, `calculation_type`, `base_field`, `default_rate`, `applies_to`, `is_mandatory`, `display_order`) VALUES
-- Earnings
('BASIC', 'Basic Pay', 'earning', 'fixed', NULL, 0, 'permanent', 1, 1),
('DA', 'Dearness Allowance', 'earning', 'percentage', 'basic', 58.00, 'permanent', 1, 2),
('HRA', 'House Rent Allowance', 'earning', 'percentage', 'basic', 20.00, 'permanent', 1, 3),
('TA', 'Transport Allowance', 'earning', 'fixed', NULL, 0, 'permanent', 1, 4),
('DA_ON_TA', 'DA on Transport Allowance', 'earning', 'percentage', 'ta', 58.00, 'permanent', 1, 5),
('CANTEEN', 'Canteen Subsidy', 'earning', 'fixed', NULL, 0, 'permanent', 0, 6),
-- Deductions
('EPF', 'Employee Provident Fund', 'deduction', 'percentage', 'basic', 12.00, 'permanent', 1, 7),
('NPS', 'New Pension Scheme', 'deduction', 'percentage', 'basic', 10.00, 'permanent', 1, 8),
('CPF', 'Central Provident Fund', 'deduction', 'percentage', 'basic', 0, 'permanent', 0, 9),
('PTAX', 'Professional Tax', 'deduction', 'fixed', NULL, 200.00, 'permanent', 1, 10),
('SUDEXO', 'Sudexo Meal Card', 'deduction', 'fixed', NULL, 0, 'permanent', 0, 11),
('ITAX', 'Income Tax (TDS)', 'deduction', 'manual', NULL, 0, 'permanent', 0, 12);

-- 7. Create HRA City Classification Table
CREATE TABLE IF NOT EXISTS `hra_cities` (
    `city_id` int(11) NOT NULL AUTO_INCREMENT,
    `city_name` varchar(100) NOT NULL,
    `state` varchar(100) NOT NULL,
    `hra_category` enum('A', 'B', 'C') NOT NULL COMMENT 'A=24%, B=16%, C=8%',
    `hra_percentage` decimal(5,2) NOT NULL,
    `is_active` tinyint(1) DEFAULT 1,
    PRIMARY KEY (`city_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Insert some common cities for HRA
INSERT INTO `hra_cities` (`city_name`, `state`, `hra_category`, `hra_percentage`) VALUES
('Delhi', 'Delhi', 'A', 24.00),
('Mumbai', 'Maharashtra', 'A', 24.00),
('Kolkata', 'West Bengal', 'A', 24.00),
('Chennai', 'Tamil Nadu', 'A', 24.00),
('Bangalore', 'Karnataka', 'A', 24.00),
('Hyderabad', 'Telangana', 'A', 24.00),
('Bhubaneswar', 'Odisha', 'B', 16.00),
('Pune', 'Maharashtra', 'B', 16.00),
('Ahmedabad', 'Gujarat', 'B', 16.00),
('Lucknow', 'Uttar Pradesh', 'B', 16.00),
('Jaipur', 'Rajasthan', 'B', 16.00),
('Chandigarh', 'Chandigarh', 'B', 16.00),
('Cuttack', 'Odisha', 'C', 8.00),
('Rourkela', 'Odisha', 'C', 8.00),
('Sambalpur', 'Odisha', 'C', 8.00);

-- Index for performance
CREATE INDEX idx_employees_pay_level ON employees(pay_level_id);
CREATE INDEX idx_employees_employment_type ON employees(employment_type);
