-- Create a test employee account for leave management testing
-- Password: test123

-- First, insert employee record (if not exists)
INSERT INTO employees (first_name, last_name, email, designation, department_id, joining_date, basic_salary, employment_type)
VALUES ('Test', 'Employee', 'test.employee@company.com', 'Software Developer', 1, '2025-01-01', 50000.00, 'permanent')
ON DUPLICATE KEY UPDATE employee_id=LAST_INSERT_ID(employee_id);

-- Get the employee_id
SET @emp_id = LAST_INSERT_ID();

-- Create user account linked to employee
INSERT INTO users (username, password, email, role, employee_id, is_active, created_at)
VALUES ('testemployee', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'test.employee@company.com', 'employee', @emp_id, 1, NOW())
ON DUPLICATE KEY UPDATE username=username;

SELECT 'Test employee account created!' AS message,
       'Username: testemployee' AS username,
       'Password: test123' AS password;
