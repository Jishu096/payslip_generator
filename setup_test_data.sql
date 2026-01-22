-- ============================================
-- e-HRMS Complete Test Data Setup
-- Creates test users for all roles with sample data
-- ============================================

USE payslip_generator;

-- ============================================
-- 1. CREATE DEPARTMENTS
-- ============================================
INSERT INTO departments (department_name) VALUES
('Information Technology'),
('Human Resources'),
('Finance'),
('Operations')
ON DUPLICATE KEY UPDATE department_name=VALUES(department_name);

-- ============================================
-- 2. CREATE ROLES (if using rbac_audit_schema)
-- ============================================
INSERT INTO roles (role_name, display_name, description, is_active) VALUES
('super_admin', 'Super Admin', 'System owner with full control', 1),
('administrator', 'Administrator', 'System custodian', 1),
('hr_officer', 'HR Officer', 'Assistant Director', 1),
('accountant', 'Accountant', 'Payroll Manager', 1),
('director', 'Director', 'Final Authority', 1),
('auditor', 'Auditor', 'Read-only compliance', 1),
('employee', 'Employee', 'Self-service portal', 1)
ON DUPLICATE KEY UPDATE display_name=VALUES(display_name);

-- ============================================
-- 3. CREATE TEST EMPLOYEES
-- ============================================

-- Employee 1: Regular Employee
INSERT INTO employees (full_name, email, phone, designation, department_id, join_date, basic_salary, employment_type, status)
VALUES ('Rajesh Kumar', 'rajesh.kumar@company.com', '9876543210', 'Senior Developer', 1, '2023-01-15', 75000.00, 'permanent', 'active')
ON DUPLICATE KEY UPDATE email=VALUES(email);
SET @emp1_id = LAST_INSERT_ID();

-- Employee 2: Junior Employee
INSERT INTO employees (full_name, email, phone, designation, department_id, join_date, basic_salary, employment_type, status)
VALUES ('Priya Sharma', 'priya.sharma@company.com', '9876543211', 'Junior Developer', 1, '2024-06-01', 45000.00, 'permanent', 'active')
ON DUPLICATE KEY UPDATE email=VALUES(email);
SET @emp2_id = LAST_INSERT_ID();

-- Employee 3: HR Staff
INSERT INTO employees (full_name, email, phone, designation, department_id, join_date, basic_salary, employment_type, status)
VALUES ('Amit Verma', 'amit.verma@company.com', '9876543212', 'HR Manager', 2, '2022-03-10', 65000.00, 'permanent', 'active')
ON DUPLICATE KEY UPDATE email=VALUES(email);
SET @emp3_id = LAST_INSERT_ID();

-- Employee 4: Finance Staff
INSERT INTO employees (full_name, email, phone, designation, department_id, join_date, basic_salary, employment_type, status)
VALUES ('Sneha Patel', 'sneha.patel@company.com', '9876543213', 'Senior Accountant', 3, '2021-08-20', 70000.00, 'permanent', 'active')
ON DUPLICATE KEY UPDATE email=VALUES(email);
SET @emp4_id = LAST_INSERT_ID();

-- Employee 5: Director
INSERT INTO employees (full_name, email, phone, designation, department_id, join_date, basic_salary, employment_type, status)
VALUES ('Dr. Arun Mehta', 'arun.mehta@company.com', '9876543214', 'Director', 4, '2020-01-01', 150000.00, 'permanent', 'active')
ON DUPLICATE KEY UPDATE email=VALUES(email);
SET @emp5_id = LAST_INSERT_ID();

-- Employee 6: Contract Employee
INSERT INTO employees (full_name, email, phone, designation, department_id, join_date, basic_salary, employment_type, status)
VALUES ('Rahul Singh', 'rahul.singh@company.com', '9876543215', 'Consultant', 1, '2025-01-01', 55000.00, 'contract', 'active')
ON DUPLICATE KEY UPDATE email=VALUES(email);
SET @emp6_id = LAST_INSERT_ID();

-- ============================================
-- 4. CREATE TEST USERS WITH ROLES
-- Password for all: test123
-- Hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
-- ============================================

-- Super Admin
INSERT INTO users (username, password_hash, email, role, employee_id, is_active, created_at)
VALUES ('superadmin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin@company.com', 'super_admin', NULL, 1, NOW())
ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash);

-- Administrator
INSERT INTO users (username, password_hash, email, role, employee_id, is_active, created_at)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@company.com', 'administrator', NULL, 1, NOW())
ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash);

-- HR Officer (linked to Employee 3)
INSERT INTO users (username, password_hash, email, role, employee_id, is_active, created_at)
VALUES ('hrofficer', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'amit.verma@company.com', 'hr_officer', @emp3_id, 1, NOW())
ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash);

-- Accountant (linked to Employee 4)
INSERT INTO users (username, password_hash, email, role, employee_id, is_active, created_at)
VALUES ('accountant', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'sneha.patel@company.com', 'accountant', @emp4_id, 1, NOW())
ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash);

-- Director (linked to Employee 5)
INSERT INTO users (username, password_hash, email, role, employee_id, is_active, created_at)
VALUES ('director', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'arun.mehta@company.com', 'director', @emp5_id, 1, NOW())
ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash);

-- Auditor
INSERT INTO users (username, password_hash, email, role, employee_id, is_active, created_at)
VALUES ('auditor', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'auditor@company.com', 'auditor', NULL, 1, NOW())
ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash);

-- Regular Employee 1 (linked to Employee 1)
INSERT INTO users (username, password_hash, email, role, employee_id, is_active, created_at)
VALUES ('rajesh.kumar', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rajesh.kumar@company.com', 'employee', @emp1_id, 1, NOW())
ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash);

-- Regular Employee 2 (linked to Employee 2)
INSERT INTO users (username, password_hash, email, role, employee_id, is_active, created_at)
VALUES ('priya.sharma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'priya.sharma@company.com', 'employee', @emp2_id, 1, NOW())
ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash);

-- Contract Employee (linked to Employee 6)
INSERT INTO users (username, password_hash, email, role, employee_id, is_active, created_at)
VALUES ('rahul.singh', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'rahul.singh@company.com', 'employee', @emp6_id, 1, NOW())
ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash);

-- Multi-Role User: Employee + Accountant (linked to Employee 4)
INSERT INTO users (username, password_hash, email, role, employee_id, is_active, created_at)
VALUES ('multirole', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'multirole@company.com', 'employee', @emp4_id, 1, NOW())
ON DUPLICATE KEY UPDATE password_hash=VALUES(password_hash);

-- ============================================
-- 5. ASSIGN MULTI-ROLES (if using user_roles_new table)
-- ============================================

-- Get user IDs
SET @multirole_user_id = (SELECT user_id FROM users WHERE username = 'multirole');
SET @employee_role_id = (SELECT role_id FROM roles WHERE role_name = 'employee');
SET @accountant_role_id = (SELECT role_id FROM roles WHERE role_name = 'accountant');

-- Assign both roles to multirole user
INSERT INTO user_roles_new (user_id, role_id, is_primary)
VALUES 
(@multirole_user_id, @employee_role_id, 0),
(@multirole_user_id, @accountant_role_id, 1)
ON DUPLICATE KEY UPDATE is_primary=VALUES(is_primary);

-- ============================================
-- 6. CREATE SAMPLE ATTENDANCE RECORDS
-- (Last 30 days for Employee 1)
-- ============================================

INSERT INTO attendance (employee_id, date, status, created_at)
SELECT 
    @emp1_id,
    DATE_SUB(CURDATE(), INTERVAL seq DAY),
    CASE 
        WHEN DAYOFWEEK(DATE_SUB(CURDATE(), INTERVAL seq DAY)) IN (1, 7) THEN 'Leave'
        WHEN RAND() < 0.95 THEN 'Present'
        ELSE 'Absent'
    END,
    NOW()
FROM (
    SELECT 0 seq UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 
    UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9
    UNION SELECT 10 UNION SELECT 11 UNION SELECT 12 UNION SELECT 13 UNION SELECT 14
    UNION SELECT 15 UNION SELECT 16 UNION SELECT 17 UNION SELECT 18 UNION SELECT 19
    UNION SELECT 20 UNION SELECT 21 UNION SELECT 22 UNION SELECT 23 UNION SELECT 24
    UNION SELECT 25 UNION SELECT 26 UNION SELECT 27 UNION SELECT 28 UNION SELECT 29
) AS seq
ON DUPLICATE KEY UPDATE status=VALUES(status);

-- ============================================
-- 7. CREATE SAMPLE PAYROLL RECORDS
-- (Last 3 months for Employee 1)
-- ============================================

INSERT INTO payroll (employee_id, month, year, basic_salary, hra, da, ta, other_allowances, pf, tax, other_deductions, gross_salary, net_salary, created_at)
VALUES
(@emp1_id, 12, 2025, 75000.00, 15000.00, 43500.00, 5000.00, 2000.00, 9000.00, 5000.00, 1000.00, 140500.00, 125500.00, NOW()),
(@emp1_id, 11, 2025, 75000.00, 15000.00, 43500.00, 5000.00, 2000.00, 9000.00, 5000.00, 1000.00, 140500.00, 125500.00, NOW()),
(@emp1_id, 10, 2025, 75000.00, 15000.00, 43500.00, 5000.00, 2000.00, 9000.00, 5000.00, 1000.00, 140500.00, 125500.00, NOW())
ON DUPLICATE KEY UPDATE basic_salary=VALUES(basic_salary);

-- ============================================
-- 8. CREATE SAMPLE PAYSLIPS
-- ============================================

INSERT INTO payslips (employee_id, payroll_id, month, year, generated_at)
SELECT 
    employee_id,
    payroll_id,
    month,
    year,
    NOW()
FROM payroll 
WHERE employee_id = @emp1_id
ON DUPLICATE KEY UPDATE generated_at=VALUES(generated_at);

-- ============================================
-- 9. CREATE SAMPLE LEAVE REQUESTS
-- ============================================

INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, reason, status, created_at)
VALUES
(@emp1_id, 'casual', '2026-02-10', '2026-02-12', 'Personal work', 'pending', NOW()),
(@emp1_id, 'sick', '2025-12-15', '2025-12-17', 'Medical checkup', 'approved', NOW()),
(@emp2_id, 'casual', '2026-02-05', '2026-02-07', 'Family function', 'pending', NOW())
ON DUPLICATE KEY UPDATE status=VALUES(status);

-- ============================================
-- 10. CREATE HOLIDAYS (2026)
-- ============================================

INSERT INTO holidays (holiday_date, holiday_name, holiday_type, is_active, created_at)
VALUES
('2026-01-26', 'Republic Day', 'national', 1, NOW()),
('2026-03-14', 'Holi', 'national', 1, NOW()),
('2026-04-14', 'Ambedkar Jayanti', 'national', 1, NOW()),
('2026-08-15', 'Independence Day', 'national', 1, NOW()),
('2026-10-02', 'Gandhi Jayanti', 'national', 1, NOW()),
('2026-12-25', 'Christmas', 'national', 1, NOW())
ON DUPLICATE KEY UPDATE holiday_name=VALUES(holiday_name);

-- ============================================
-- VERIFICATION QUERIES
-- ============================================

SELECT '✅ TEST DATA SETUP COMPLETE!' AS status;
SELECT '' AS '';

SELECT '📊 SUMMARY' AS info;
SELECT COUNT(*) AS total_departments FROM departments;
SELECT COUNT(*) AS total_employees FROM employees WHERE is_active = 1;
SELECT COUNT(*) AS total_users FROM users WHERE is_active = 1;
SELECT COUNT(*) AS total_attendance_records FROM attendance;
SELECT COUNT(*) AS total_payroll_records FROM payroll;
SELECT COUNT(*) AS total_holidays FROM holidays WHERE is_active = 1;

SELECT '' AS '';
SELECT '🔐 TEST ACCOUNTS' AS info;
SELECT username, role, email FROM users WHERE is_active = 1 ORDER BY 
    FIELD(role, 'super_admin', 'administrator', 'hr_officer', 'director', 'accountant', 'auditor', 'employee');

SELECT '' AS '';
SELECT '🔑 ALL PASSWORDS: test123' AS credentials;
SELECT '🌐 URL: http://localhost/payslip_generator/public/auth/login.php' AS login_url;
