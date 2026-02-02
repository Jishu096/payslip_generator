-- Insert new employees based on 7th CPC data
-- Run this after clearing existing employees

INSERT INTO employees (employee_code, full_name, designation, department_id, email, phone, employment_type, status, basic_salary, pay_level_id, hra_type, join_date, location) VALUES
('EMP001', 'Sh. Anil Kumar Shaw', 'Director-In-Charge', 1, 'anil.shaw@nielit.gov.in', '9876543210', 'permanent', 'active', 96900.00, (SELECT level_id FROM pay_levels WHERE level_number = 12), 'city_b', '2020-01-15', 'NIELIT Bhubaneswar');

INSERT INTO employees (employee_code, full_name, designation, department_id, email, phone, employment_type, status, basic_salary, pay_level_id, hra_type, join_date, location) VALUES
('EMP002', 'Sh Rabin Karmakar', 'Scientist D', 1, 'rabin.karmakar@nielit.gov.in', '9876543211', 'permanent', 'active', 105900.00, (SELECT level_id FROM pay_levels WHERE level_number = 12), 'city_b', '2019-06-01', 'NIELIT Bhubaneswar');

INSERT INTO employees (employee_code, full_name, designation, department_id, email, phone, employment_type, status, basic_salary, pay_level_id, hra_type, join_date, location) VALUES
('EMP003', 'Shri Harihar Dash', 'Scientist C', 1, 'harihar.dash@nielit.gov.in', '9876543212', 'permanent', 'active', 69700.00, (SELECT level_id FROM pay_levels WHERE level_number = 11), 'city_b', '2018-03-10', 'NIELIT Bhubaneswar');

INSERT INTO employees (employee_code, full_name, designation, department_id, email, phone, employment_type, status, basic_salary, pay_level_id, hra_type, join_date, location) VALUES
('EMP004', 'Sh. Satikanta Dash', 'Assistant Director (Admin)', 2, 'satikanta.dash@nielit.gov.in', '9876543213', 'permanent', 'active', 65000.00, (SELECT level_id FROM pay_levels WHERE level_number = 10), 'city_b', '2017-08-20', 'NIELIT Bhubaneswar');

INSERT INTO employees (employee_code, full_name, designation, department_id, email, phone, employment_type, status, basic_salary, pay_level_id, hra_type, join_date, location) VALUES
('EMP005', 'Sh Khaimar Amol Anil', 'Scientist B', 1, 'khaimar.amol@nielit.gov.in', '9876543214', 'permanent', 'active', 57800.00, (SELECT level_id FROM pay_levels WHERE level_number = 10), 'city_b', '2021-02-01', 'NIELIT Bhubaneswar');

INSERT INTO employees (employee_code, full_name, designation, department_id, email, phone, employment_type, status, basic_salary, pay_level_id, hra_type, join_date, location) VALUES
('EMP006', 'Ms. Priyanka Patel', 'Senior Technical Assistant', 1, 'priyanka.patel@nielit.gov.in', '9876543215', 'permanent', 'active', 35400.00, (SELECT level_id FROM pay_levels WHERE level_number = 6), 'city_b', '2019-11-15', 'NIELIT Bhubaneswar');

INSERT INTO employees (employee_code, full_name, designation, department_id, email, phone, employment_type, status, basic_salary, pay_level_id, hra_type, join_date, location) VALUES
('EMP007', 'Smt. Palli Sukanya', 'Assistant Accounts', 3, 'palli.sukanya@nielit.gov.in', '9876543216', 'permanent', 'active', 30500.00, (SELECT level_id FROM pay_levels WHERE level_number = 4), 'city_b', '2020-07-01', 'NIELIT Bhubaneswar');

INSERT INTO employees (employee_code, full_name, designation, department_id, email, phone, employment_type, status, basic_salary, pay_level_id, hra_type, join_date, location) VALUES
('EMP008', 'Sh. Ujjwaldeep', 'Junior Assistant', 2, 'ujjwaldeep@nielit.gov.in', '9876543217', 'permanent', 'active', 20500.00, (SELECT level_id FROM pay_levels WHERE level_number = 2), 'city_b', '2022-01-10', 'NIELIT Bhubaneswar');
