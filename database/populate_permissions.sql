-- Populate all permissions for government HRMS RBAC
-- Safe to run multiple times (INSERT IGNORE)

INSERT IGNORE INTO permissions (permission_name, description, resource) VALUES
-- System Management (Super Admin only)
('system.backup', 'Create and manage system backups', 'system'),
('system.restore', 'Restore system from backup', 'system'),
('system.settings', 'Configure system settings', 'system'),
('system.logs', 'View system logs', 'system'),
('roles.assign', 'Assign roles to users', 'system'),
('roles.manage', 'Create and modify role definitions', 'system'),

-- User Management
('users.create', 'Create new user accounts', 'users'),
('users.view', 'View user details', 'users'),
('users.edit', 'Edit user information', 'users'),
('users.delete', 'Delete user accounts', 'users'),
('users.activate', 'Activate/Deactivate users', 'users'),
('users.2fa', 'Manage 2FA settings', 'users'),

-- Employee Management
('employees.create', 'Add new employees', 'employees'),
('employees.view', 'View employee records', 'employees'),
('employees.edit', 'Edit employee information', 'employees'),
('employees.delete', 'Delete employee records', 'employees'),
('employees.profile_own', 'View and edit own profile', 'employees'),

-- Department Management
('departments.create', 'Create departments', 'departments'),
('departments.view', 'View departments', 'departments'),
('departments.edit', 'Edit departments', 'departments'),
('departments.delete', 'Delete departments', 'departments'),

-- Salary Management (Separation of Duties)
('salary.view', 'View salary information', 'salary'),
('salary.edit', 'Propose salary changes (requires approval)', 'salary'),
('salary.approve', 'Approve salary changes', 'salary'),
('salary.history', 'View salary change history', 'salary'),

-- Attendance Management
('attendance.mark', 'Mark attendance', 'attendance'),
('attendance.view', 'View attendance records', 'attendance'),
('attendance.edit', 'Edit attendance (before verification)', 'attendance'),
('attendance.verify', 'Verify and lock attendance', 'attendance'),
('attendance.reports', 'Generate attendance reports', 'attendance'),
('attendance.statement', 'Generate attendance statements', 'attendance'),

-- Leave Management
('leave.apply', 'Apply for leave', 'leave'),
('leave.approve', 'Approve/Reject leave requests', 'leave'),
('leave.view', 'View leave records', 'leave'),
('leave.cancel', 'Cancel approved leaves', 'leave'),

-- Payroll Management (Maker-Checker)
('payroll.generate', 'Generate payroll (Maker)', 'payroll'),
('payroll.verify', 'Verify payroll (Checker)', 'payroll'),
('payroll.approve', 'Final approve payroll', 'payroll'),
('payroll.view', 'View payroll records', 'payroll'),
('payroll.reports', 'Generate payroll reports', 'payroll'),
('payroll.lock', 'Lock/Unlock payroll months', 'payroll'),
('payroll.arrears', 'Create arrears entries', 'payroll'),

-- Payslip Management
('payslip.generate', 'Generate employee payslips', 'payslips'),
('payslip.view_own', 'View own payslip', 'payslips'),
('payslip.view_all', 'View all payslips', 'payslips'),
('payslip.download', 'Download payslip PDFs', 'payslips'),

-- Audit & Compliance
('audit.view', 'View audit logs', 'audit'),
('audit.export', 'Export audit logs', 'audit'),
('audit.analyze', 'Analyze audit trails', 'audit');

-- Assign permissions to roles
-- Super Admin gets all permissions
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.role_name = 'super_admin';

-- Administrator (no salary/payroll approval)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.role_name = 'administrator'
AND p.permission_name IN (
    'users.create', 'users.view', 'users.edit', 'users.delete', 'users.activate',
    'employees.create', 'employees.view', 'employees.edit', 'employees.delete',
    'departments.create', 'departments.view', 'departments.edit', 'departments.delete',
    'attendance.mark', 'attendance.view', 'attendance.edit',
    'leave.view', 'leave.approve',
    'salary.view'
);

-- HR Officer
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.role_name = 'hr_officer'
AND p.permission_name IN (
    'employees.create', 'employees.view', 'employees.edit',
    'departments.view',
    'attendance.mark', 'attendance.view', 'attendance.verify', 'attendance.reports', 'attendance.statement',
    'leave.view', 'leave.approve', 'leave.cancel',
    'salary.view', 'salary.edit',
    'payroll.view'
);

-- Accountant (Maker role)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.role_name = 'accountant'
AND p.permission_name IN (
    'employees.view',
    'attendance.view', 'attendance.reports',
    'salary.view',
    'payroll.generate', 'payroll.view', 'payroll.reports',
    'payslip.generate', 'payslip.view_all', 'payslip.download'
);

-- Director (Checker/Approver role)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.role_name = 'director'
AND p.permission_name IN (
    'employees.view',
    'departments.view',
    'attendance.view', 'attendance.reports',
    'salary.view', 'salary.approve', 'salary.history',
    'leave.view', 'leave.approve', 'leave.cancel',
    'payroll.verify', 'payroll.approve', 'payroll.view', 'payroll.reports', 'payroll.lock',
    'payslip.view_all', 'payslip.download'
);

-- Auditor (read-only + audit tools)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.role_name = 'auditor'
AND p.permission_name IN (
    'employees.view',
    'departments.view',
    'attendance.view', 'attendance.reports',
    'salary.view', 'salary.history',
    'payroll.view', 'payroll.reports',
    'payslip.view_all',
    'audit.view', 'audit.export', 'audit.analyze'
);

-- Employee (self-service only)
INSERT IGNORE INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.role_name = 'employee'
AND p.permission_name IN (
    'employees.profile_own',
    'attendance.view',
    'leave.apply', 'leave.view',
    'payslip.view_own', 'payslip.download'
);

-- Verification queries
SELECT 'Total permissions:' as status, COUNT(*) as count FROM permissions;
SELECT 'Total role-permission mappings:' as status, COUNT(*) as count FROM role_permissions;
SELECT r.display_name, COUNT(rp.permission_id) as permission_count
FROM roles r
LEFT JOIN role_permissions rp ON r.id = rp.role_id
GROUP BY r.id, r.display_name
ORDER BY permission_count DESC;
