-- Payroll and payslip schema (aligned with auto-calculation fields)

CREATE TABLE IF NOT EXISTS `payroll` (
	`payroll_id` int(11) NOT NULL AUTO_INCREMENT,
	`employee_id` int(11) NOT NULL,
	`month` varchar(20) DEFAULT NULL,
	`year` int(11) NOT NULL,
	`basic` decimal(10,2) DEFAULT NULL,
	`da_amount` decimal(10,2) DEFAULT NULL,
	`hra_amount` decimal(10,2) DEFAULT NULL,
	`ta_amount` decimal(10,2) DEFAULT 0.00,
	`da_on_ta` decimal(10,2) DEFAULT 0.00,
	`bonus` decimal(10,2) DEFAULT 0.00,
	`gross_salary` decimal(10,2) DEFAULT NULL,
	`tax_deduction` decimal(10,2) DEFAULT 0.00,
	`pf_deduction` decimal(10,2) DEFAULT 0.00,
	`nps_deduction` decimal(10,2) DEFAULT 0.00,
	`professional_tax` decimal(10,2) DEFAULT 0.00,
	`other_deductions` decimal(10,2) DEFAULT 0.00,
	`total_deductions` decimal(10,2) DEFAULT NULL,
	`net_salary` decimal(10,2) DEFAULT NULL,
	`created_at` timestamp NOT NULL DEFAULT current_timestamp(),
	PRIMARY KEY (`payroll_id`),
	KEY `idx_payroll_employee` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `payslips` (
	`payslip_id` int(11) NOT NULL AUTO_INCREMENT,
	`payroll_id` int(11) NOT NULL,
	`employee_id` int(11) NOT NULL,
	`file_path` varchar(255) DEFAULT NULL,
	`generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
	PRIMARY KEY (`payslip_id`),
	KEY `idx_payslip_payroll` (`payroll_id`),
	KEY `idx_payslip_employee` (`employee_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Note: Existing databases already altered to include new payroll component columns.
