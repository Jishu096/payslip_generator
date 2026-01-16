-- ============================================
-- eHRMS RBAC + Audit + Month-Lock Schema
-- Production-Grade Government HRMS
-- ============================================

-- 1. ROLES TABLE
CREATE TABLE IF NOT EXISTS roles (
    role_id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_role_name (role_name),
    INDEX idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default roles
INSERT INTO roles (role_name, display_name, description) VALUES
('super_admin', 'Super Admin', 'System owner with full control over user management and system configuration'),
('administrator', 'Administrator', 'System custodian - manages users, departments, attendance uploads'),
('hr_officer', 'HR Officer (Assistant Director)', 'Verifies attendance, approves leave, handles employee onboarding'),
('accountant', 'Accountant', 'Manages salary structure, generates payslips, handles payroll'),
('director', 'Director', 'Final approval authority for payroll and attendance'),
('auditor', 'Auditor', 'Read-only compliance role for government audit'),
('employee', 'Employee', 'Self-service portal for viewing payslips and attendance')
ON DUPLICATE KEY UPDATE display_name=VALUES(display_name);

-- 2. PERMISSIONS TABLE
CREATE TABLE IF NOT EXISTS permissions (
    permission_id INT PRIMARY KEY AUTO_INCREMENT,
    permission_name VARCHAR(100) UNIQUE NOT NULL,
    display_name VARCHAR(150) NOT NULL,
    category VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_permission_name (permission_name),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert permissions
INSERT INTO permissions (permission_name, display_name, category, description) VALUES
-- User Management
('user.create', 'Create Users', 'user_management', 'Create new user accounts'),
('user.edit', 'Edit Users', 'user_management', 'Edit existing user details'),
('user.delete', 'Delete Users', 'user_management', 'Soft-delete users'),
('user.activate', 'Activate/Deactivate Users', 'user_management', 'Enable or disable user accounts'),
('user.assign_roles', 'Assign Roles', 'user_management', 'Assign roles to users'),

-- Employee Management
('employee.create', 'Create Employees', 'employee_management', 'Add new employees'),
('employee.edit', 'Edit Employees', 'employee_management', 'Edit employee details'),
('employee.delete', 'Delete Employees', 'employee_management', 'Soft-delete employees'),
('employee.view', 'View Employees', 'employee_management', 'View employee list'),
('employee.onboard', 'Onboard Employees', 'employee_management', 'Handle employee onboarding'),

-- Attendance Management
('attendance.view', 'View Attendance', 'attendance', 'View attendance records'),
('attendance.upload', 'Upload Attendance', 'attendance', 'Upload absentee statements'),
('attendance.verify', 'Verify Attendance', 'attendance', 'Verify and approve attendance'),
('attendance.edit', 'Edit Attendance', 'attendance', 'Manually edit attendance records'),
('attendance.approve', 'Approve Attendance', 'attendance', 'Final approval of attendance'),
('attendance.lock', 'Lock Attendance', 'attendance', 'Lock attendance month'),

-- Leave Management
('leave.view', 'View Leave', 'leave', 'View leave records'),
('leave.edit', 'Edit Leave', 'leave', 'Edit leave type (CL, OD, EL)'),
('leave.approve', 'Approve Leave', 'leave', 'Approve or reject leave requests'),

-- Salary Management
('salary.view', 'View Salary', 'salary', 'View salary information'),
('salary.edit', 'Edit Salary', 'salary', 'Change employee salary'),
('salary.approve', 'Approve Salary', 'salary', 'Approve salary change requests'),

-- Payroll Management
('payroll.view', 'View Payroll', 'payroll', 'View payroll records'),
('payroll.create', 'Create Payroll', 'payroll', 'Generate payroll'),
('payroll.approve', 'Approve Payroll', 'payroll', 'Final approval of payroll'),
('payroll.lock', 'Lock Payroll', 'payroll', 'Lock payroll month'),

-- Payslip Management
('payslip.generate', 'Generate Payslips', 'payslip', 'Generate employee payslips'),
('payslip.view_own', 'View Own Payslip', 'payslip', 'View own payslip'),
('payslip.view_all', 'View All Payslips', 'payslip', 'View all employee payslips'),

-- Reports
('reports.view', 'View Reports', 'reports', 'View system reports'),
('reports.export', 'Export Reports', 'reports', 'Export reports to PDF/Excel'),

-- System Management
('system.backup', 'System Backup', 'system', 'Take full system backup'),
('system.restore', 'System Restore', 'system', 'Restore system from backup'),
('system.settings', 'System Settings', 'system', 'Configure system settings'),
('system.security', 'Security Settings', 'system', 'Configure security settings'),

-- Audit
('audit.view', 'View Audit Logs', 'audit', 'View audit trail'),
('audit.export', 'Export Audit Logs', 'audit', 'Export audit logs')
ON DUPLICATE KEY UPDATE display_name=VALUES(display_name);

-- 3. ROLE_PERMISSIONS TABLE
CREATE TABLE IF NOT EXISTS role_permissions (
    role_permission_id INT PRIMARY KEY AUTO_INCREMENT,
    role_id INT NOT NULL,
    permission_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_role_permission (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(permission_id) ON DELETE CASCADE,
    INDEX idx_role_id (role_id),
    INDEX idx_permission_id (permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Assign permissions to Super Admin (ALL permissions)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r, permissions p
WHERE r.role_name = 'super_admin'
ON DUPLICATE KEY UPDATE role_permission_id=role_permission_id;

-- Assign permissions to Administrator
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r, permissions p
WHERE r.role_name = 'administrator'
AND p.permission_name IN (
    'user.create', 'user.edit', 'user.delete',
    'employee.create', 'employee.edit', 'employee.delete', 'employee.view',
    'attendance.view', 'attendance.upload',
    'leave.view',
    'reports.view'
)
ON DUPLICATE KEY UPDATE role_permission_id=role_permission_id;

-- Assign permissions to HR Officer
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r, permissions p
WHERE r.role_name = 'hr_officer'
AND p.permission_name IN (
    'employee.create', 'employee.edit', 'employee.view', 'employee.onboard',
    'attendance.view', 'attendance.verify', 'attendance.edit',
    'leave.view', 'leave.edit', 'leave.approve',
    'reports.view'
)
ON DUPLICATE KEY UPDATE role_permission_id=role_permission_id;

-- Assign permissions to Accountant
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r, permissions p
WHERE r.role_name = 'accountant'
AND p.permission_name IN (
    'employee.view',
    'attendance.view',
    'salary.view',
    'payroll.view', 'payroll.create',
    'payslip.generate', 'payslip.view_all',
    'reports.view', 'reports.export'
)
ON DUPLICATE KEY UPDATE role_permission_id=role_permission_id;

-- Assign permissions to Director
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r, permissions p
WHERE r.role_name = 'director'
AND p.permission_name IN (
    'employee.view',
    'attendance.view', 'attendance.approve', 'attendance.lock',
    'leave.view', 'leave.approve',
    'salary.view', 'salary.approve',
    'payroll.view', 'payroll.approve', 'payroll.lock',
    'payslip.view_all',
    'reports.view', 'reports.export'
)
ON DUPLICATE KEY UPDATE role_permission_id=role_permission_id;

-- Assign permissions to Auditor (READ-ONLY)
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r, permissions p
WHERE r.role_name = 'auditor'
AND p.permission_name IN (
    'employee.view',
    'attendance.view',
    'leave.view',
    'salary.view',
    'payroll.view',
    'payslip.view_all',
    'reports.view', 'reports.export',
    'audit.view', 'audit.export'
)
ON DUPLICATE KEY UPDATE role_permission_id=role_permission_id;

-- Assign permissions to Employee
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id
FROM roles r, permissions p
WHERE r.role_name = 'employee'
AND p.permission_name IN (
    'attendance.view',
    'leave.view',
    'payslip.view_own'
)
ON DUPLICATE KEY UPDATE role_permission_id=role_permission_id;

-- 4. USER_ROLES TABLE (Multi-role support)
CREATE TABLE IF NOT EXISTS user_roles_new (
    user_role_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    assigned_by INT,
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_primary TINYINT(1) DEFAULT 0,
    UNIQUE KEY unique_user_role (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(role_id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_role_id (role_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. AUDIT_LOG TABLE
CREATE TABLE IF NOT EXISTS audit_log (
    log_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT,
    old_values TEXT,
    new_values TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    session_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. PAYROLL_LOCK TABLE (Month-lock mechanism)
CREATE TABLE IF NOT EXISTS payroll_lock (
    lock_id INT PRIMARY KEY AUTO_INCREMENT,
    month INT NOT NULL,
    year INT NOT NULL,
    locked_by INT NOT NULL,
    locked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    lock_type ENUM('attendance', 'payroll', 'full') NOT NULL,
    reason TEXT,
    can_unlock TINYINT(1) DEFAULT 0,
    unlocked_by INT,
    unlocked_at TIMESTAMP NULL,
    UNIQUE KEY unique_month_year_type (month, year, lock_type),
    FOREIGN KEY (locked_by) REFERENCES users(user_id),
    FOREIGN KEY (unlocked_by) REFERENCES users(user_id),
    INDEX idx_month_year (month, year),
    INDEX idx_lock_type (lock_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. CSRF_TOKENS TABLE
CREATE TABLE IF NOT EXISTS csrf_tokens (
    token_id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(64) UNIQUE NOT NULL,
    action VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    used_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Add soft-delete columns to existing tables
ALTER TABLE users ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS deleted_by INT NULL;
ALTER TABLE users ADD INDEX IF NOT EXISTS idx_deleted_at (deleted_at);

ALTER TABLE employees ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL;
ALTER TABLE employees ADD COLUMN IF NOT EXISTS deleted_by INT NULL;
ALTER TABLE employees ADD INDEX IF NOT EXISTS idx_deleted_at (deleted_at);

ALTER TABLE departments ADD COLUMN IF NOT EXISTS deleted_at TIMESTAMP NULL;
ALTER TABLE departments ADD COLUMN IF NOT EXISTS deleted_by INT NULL;
ALTER TABLE departments ADD INDEX IF NOT EXISTS idx_deleted_at (deleted_at);

-- 9. Add 2FA columns to users table
ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_secret VARCHAR(32) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS totp_enabled TINYINT(1) DEFAULT 0;
ALTER TABLE users ADD COLUMN IF NOT EXISTS ip_whitelist TEXT NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login_ip VARCHAR(45) NULL;
ALTER TABLE users ADD COLUMN IF NOT EXISTS last_login_at TIMESTAMP NULL;

-- 10. ARREARS TABLE (Post-approval adjustments)
CREATE TABLE IF NOT EXISTS arrears (
    arrear_id INT PRIMARY KEY AUTO_INCREMENT,
    employee_id INT NOT NULL,
    month INT NOT NULL,
    year INT NOT NULL,
    arrear_type ENUM('salary', 'allowance', 'deduction', 'other') NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    reason TEXT NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_by INT,
    approved_at TIMESTAMP NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    applied_in_month INT,
    applied_in_year INT,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(user_id),
    FOREIGN KEY (approved_by) REFERENCES users(user_id),
    INDEX idx_employee (employee_id),
    INDEX idx_month_year (month, year),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================
-- CLEANUP: Remove old user_roles if exists
-- ============================================
-- DROP TABLE IF EXISTS user_roles;
-- RENAME TABLE user_roles_new TO user_roles;

-- ============================================
-- Migration: Populate user_roles from existing users.role
-- ============================================
INSERT INTO user_roles_new (user_id, role_id, is_primary)
SELECT u.user_id, r.role_id, 1
FROM users u
JOIN roles r ON u.role = r.role_name
WHERE NOT EXISTS (
    SELECT 1 FROM user_roles_new ur
    WHERE ur.user_id = u.user_id AND ur.role_id = r.role_id
);

-- ============================================
-- SUCCESS MESSAGE
-- ============================================
SELECT 'RBAC + Audit + Month-Lock Schema Created Successfully!' AS status;
