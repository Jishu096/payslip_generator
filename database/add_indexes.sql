-- Performance Optimization: Add Database Indexes
-- Created: 2026-01-06
-- Purpose: Speed up common queries by 2-5x with zero code changes

-- ===========================================
-- PAYSLIP & PAYROLL INDEXES
-- ===========================================

-- Speed up employee payslip lookups (view_payslips.php)
CREATE INDEX IF NOT EXISTS idx_payslips_employee_id 
ON payslips(employee_id);

-- Speed up payslip-payroll joins
CREATE INDEX IF NOT EXISTS idx_payslips_payroll_id 
ON payslips(payroll_id);

-- Speed up monthly payroll queries
CREATE INDEX IF NOT EXISTS idx_payroll_month_year 
ON payroll(month, year);

-- Speed up payroll employee lookups
CREATE INDEX IF NOT EXISTS idx_payroll_employee_id 
ON payroll(employee_id);

-- ===========================================
-- USER & AUTHENTICATION INDEXES
-- ===========================================

-- Speed up login queries (critical for authentication)
CREATE INDEX IF NOT EXISTS idx_users_username 
ON users(username);

-- Speed up email lookups (forgot password, notifications)
CREATE INDEX IF NOT EXISTS idx_users_email 
ON users(email);

-- Speed up login attempt tracking
CREATE INDEX IF NOT EXISTS idx_login_attempts_username 
ON login_attempts(username);

CREATE INDEX IF NOT EXISTS idx_login_attempts_timestamp 
ON login_attempts(attempted_at);

-- ===========================================
-- EMPLOYEE INDEXES
-- ===========================================

-- Speed up employee email lookups
CREATE INDEX IF NOT EXISTS idx_employees_email 
ON employees(email);

-- Speed up department-based queries
CREATE INDEX IF NOT EXISTS idx_employees_department_id 
ON employees(department_id);

-- Speed up employment type filtering (permanent/contract/intern)
CREATE INDEX IF NOT EXISTS idx_employees_employment_type 
ON employees(employment_type);

-- ===========================================
-- RBAC (ROLE-BASED ACCESS CONTROL) INDEXES
-- ===========================================

-- Speed up user role lookups (every page with RBAC)
CREATE INDEX IF NOT EXISTS idx_user_roles_user_id 
ON user_roles(user_id);

-- Speed up role-based queries
CREATE INDEX IF NOT EXISTS idx_user_roles_role_id 
ON user_roles(role_id);

-- Composite index for user-role combination checks
CREATE INDEX IF NOT EXISTS idx_user_roles_user_role 
ON user_roles(user_id, role_id);

-- ===========================================
-- APPROVAL WORKFLOW INDEXES
-- ===========================================

-- Speed up salary approval filtering (director dashboard)
CREATE INDEX IF NOT EXISTS idx_salary_requests_status 
ON salary_change_requests(status);

CREATE INDEX IF NOT EXISTS idx_salary_requests_employee_id 
ON salary_change_requests(employee_id);

-- Speed up role approval filtering
CREATE INDEX IF NOT EXISTS idx_role_requests_status 
ON role_change_requests(status);

CREATE INDEX IF NOT EXISTS idx_role_requests_employee_id 
ON role_change_requests(employee_id);

-- ===========================================
-- DEPARTMENT INDEXES
-- ===========================================

-- Speed up department name lookups
CREATE INDEX IF NOT EXISTS idx_departments_name 
ON departments(department_name);

-- ===========================================
-- ATTENDANCE INDEXES
-- ===========================================

-- Speed up attendance queries by employee
CREATE INDEX IF NOT EXISTS idx_attendance_employee_id 
ON attendance(employee_id);

-- Speed up attendance date range queries
CREATE INDEX IF NOT EXISTS idx_attendance_date 
ON attendance(date);

-- ===========================================
-- VERIFICATION
-- ===========================================

-- Show all indexes created
SHOW INDEX FROM payslips;
SHOW INDEX FROM payroll;
SHOW INDEX FROM users;
SHOW INDEX FROM employees;
SHOW INDEX FROM user_roles;
SHOW INDEX FROM salary_change_requests;
SHOW INDEX FROM role_change_requests;
SHOW INDEX FROM departments;
SHOW INDEX FROM attendance;
SHOW INDEX FROM login_attempts;
