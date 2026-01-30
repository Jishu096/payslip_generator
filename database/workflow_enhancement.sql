-- Workflow Enhancement Schema for Enterprise HRMS
-- Implements attendance finalization workflow: HR → Admin → Accountant → Director

-- Note: attendance table already has workflow_status, verified_by, verified_at, finalized_by, finalized_at columns

-- Create attendance finalization log table
CREATE TABLE IF NOT EXISTS attendance_finalization_log (
    log_id INT(11) NOT NULL AUTO_INCREMENT,
    month VARCHAR(20) NOT NULL,
    year INT(11) NOT NULL,
    finalized_by INT(11) NOT NULL,
    finalized_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_locked TINYINT(1) DEFAULT 1 COMMENT 'Whether month is locked',
    record_count INT(11) DEFAULT 0,
    notes TEXT,
    PRIMARY KEY (log_id),
    KEY idx_month_year (month, year),
    KEY idx_finalized_by (finalized_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Tracks monthly attendance finalization by Admin';

-- Create attendance export log table
CREATE TABLE IF NOT EXISTS attendance_export_log (
    export_id INT(11) NOT NULL AUTO_INCREMENT,
    month VARCHAR(20) NOT NULL,
    year INT(11) NOT NULL,
    exported_by INT(11) NOT NULL,
    exported_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    file_path VARCHAR(255),
    record_count INT(11) DEFAULT 0,
    export_format ENUM('excel', 'csv', 'pdf') DEFAULT 'excel',
    PRIMARY KEY (export_id),
    KEY idx_month_year (month, year),
    KEY idx_exported_by (exported_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Tracks attendance Excel exports by Admin';

-- Create payroll approval workflow table
CREATE TABLE IF NOT EXISTS payroll_approval (
    approval_id INT(11) NOT NULL AUTO_INCREMENT,
    payroll_id INT(11) NOT NULL,
    month VARCHAR(20) NOT NULL,
    year INT(11) NOT NULL,
    submitted_by INT(11) NOT NULL COMMENT 'Accountant who submitted',
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_by INT(11) DEFAULT NULL COMMENT 'Director who approved',
    approved_at TIMESTAMP NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    rejection_reason TEXT,
    PRIMARY KEY (approval_id),
    KEY idx_payroll_id (payroll_id),
    KEY idx_status (status),
    KEY idx_submitted_by (submitted_by),
    KEY idx_approved_by (approved_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Director approval workflow for payroll';

-- Note: payroll table already has approval_status, submitted_at, approved_at columns

-- Create salary rules configuration table
CREATE TABLE IF NOT EXISTS salary_rules (
    rule_id INT(11) NOT NULL AUTO_INCREMENT,
    employment_type ENUM('permanent', 'contract', 'intern') NOT NULL,
    component_name VARCHAR(100) NOT NULL COMMENT 'HRA, DA, TA, etc.',
    calculation_type ENUM('percentage', 'fixed', 'formula') NOT NULL,
    calculation_value DECIMAL(10,2) NOT NULL COMMENT 'Percentage or fixed amount',
    is_active TINYINT(1) DEFAULT 1,
    created_by INT(11),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (rule_id),
    UNIQUE KEY unique_rule (employment_type, component_name),
    KEY idx_employment_type (employment_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Salary calculation rules by employment type';

-- Insert default salary rules
INSERT INTO salary_rules (employment_type, component_name, calculation_type, calculation_value, created_by) VALUES
('permanent', 'HRA', 'percentage', 20.00, 1),
('permanent', 'DA', 'percentage', 58.00, 1),
('permanent', 'TA', 'percentage', 10.00, 1),
('permanent', 'DA_ON_TA', 'percentage', 58.00, 1),
('permanent', 'EPF', 'percentage', 12.00, 1),
('permanent', 'NPS', 'percentage', 10.00, 1),
('permanent', 'PROFESSIONAL_TAX', 'fixed', 200.00, 1),
('contract', 'HRA', 'percentage', 15.00, 1),
('contract', 'DA', 'percentage', 50.00, 1),
('contract', 'EPF', 'percentage', 10.00, 1),
('intern', 'STIPEND', 'fixed', 5000.00, 1)
ON DUPLICATE KEY UPDATE calculation_value=VALUES(calculation_value);

-- Create attendance month lock table
CREATE TABLE IF NOT EXISTS attendance_month_lock (
    lock_id INT(11) NOT NULL AUTO_INCREMENT,
    month VARCHAR(20) NOT NULL,
    year INT(11) NOT NULL,
    locked_by INT(11) NOT NULL,
    locked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_locked TINYINT(1) DEFAULT 1,
    notes TEXT,
    PRIMARY KEY (lock_id),
    UNIQUE KEY unique_month_year (month, year),
    KEY idx_locked_by (locked_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Admin locks attendance months after verification';

-- Create system workflow audit table
CREATE TABLE IF NOT EXISTS workflow_audit (
    audit_id INT(11) NOT NULL AUTO_INCREMENT,
    entity_type ENUM('attendance', 'payroll', 'leave', 'user') NOT NULL,
    entity_id INT(11) NOT NULL,
    action VARCHAR(100) NOT NULL,
    previous_status VARCHAR(50),
    new_status VARCHAR(50),
    performed_by INT(11) NOT NULL,
    performed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45),
    user_agent TEXT,
    details JSON,
    PRIMARY KEY (audit_id),
    KEY idx_entity (entity_type, entity_id),
    KEY idx_performed_by (performed_by),
    KEY idx_performed_at (performed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Complete workflow audit trail for Auditor role';

-- Add indices for performance (attendance uses date column, not month/year)
CREATE INDEX IF NOT EXISTS idx_attendance_date ON attendance(date);
CREATE INDEX IF NOT EXISTS idx_attendance_workflow_status ON attendance(workflow_status);
CREATE INDEX IF NOT EXISTS idx_payroll_month_year ON payroll(month, year);

-- Note: Foreign key constraints skipped to avoid duplicate constraint errors
-- Tables are created without foreign keys for flexibility

-- Create views for reporting
CREATE OR REPLACE VIEW v_attendance_workflow_status AS
SELECT 
    DATE_FORMAT(a.date, '%Y-%m') as month_year,
    a.workflow_status,
    COUNT(*) as record_count,
    COUNT(DISTINCT a.employee_id) as employee_count
FROM attendance a
GROUP BY DATE_FORMAT(a.date, '%Y-%m'), a.workflow_status;

-- View for pending payroll approvals (simplified - no created_by in payroll table)
CREATE OR REPLACE VIEW v_payroll_pending_approvals AS
SELECT 
    p.payroll_id,
    p.employee_id,
    e.full_name,
    p.month,
    p.year,
    p.net_salary,
    p.approval_status,
    p.submitted_at
FROM payroll p
JOIN employees e ON p.employee_id = e.employee_id
WHERE p.approval_status IN ('submitted', 'pending')
