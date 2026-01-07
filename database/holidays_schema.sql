-- Holidays table for central government holidays
CREATE TABLE IF NOT EXISTS holidays (
    holiday_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    holiday_name VARCHAR(100) NOT NULL,
    holiday_date DATE NOT NULL,
    holiday_type ENUM('national', 'optional', 'restricted') DEFAULT 'national',
    description TEXT,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_holiday_date (holiday_date),
    INDEX idx_holiday_date (holiday_date),
    INDEX idx_holiday_type (holiday_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Central Government Holidays';

-- Insert India Central Government Holidays for 2026
INSERT INTO holidays (holiday_name, holiday_date, holiday_type, description) VALUES
('Republic Day', '2026-01-26', 'national', 'National Holiday'),
('Maha Shivaratri', '2026-03-03', 'national', 'Hindu Festival'),
('Holi', '2026-03-25', 'national', 'Festival of Colors'),
('Good Friday', '2026-04-03', 'national', 'Christian Festival'),
('Dr. Ambedkar Jayanti', '2026-04-14', 'national', 'National Holiday'),
('Ram Navami', '2026-04-21', 'optional', 'Hindu Festival'),
('Mahavir Jayanti', '2026-04-21', 'optional', 'Jain Festival'),
('Id-ul-Fitr (Eid)', '2026-05-04', 'national', 'Islamic Festival'),
('Buddha Purnima', '2026-05-12', 'optional', 'Buddhist Festival'),
('Id-ul-Zuha (Bakrid)', '2026-07-10', 'national', 'Islamic Festival'),
('Muharram', '2026-07-30', 'optional', 'Islamic Festival'),
('Independence Day', '2026-08-15', 'national', 'National Holiday'),
('Janmashtami', '2026-08-25', 'optional', 'Hindu Festival'),
('Milad-un-Nabi', '2026-09-29', 'optional', 'Islamic Festival'),
('Mahatma Gandhi Jayanti', '2026-10-02', 'national', 'National Holiday'),
('Dussehra', '2026-10-13', 'national', 'Hindu Festival'),
('Diwali', '2026-11-01', 'national', 'Festival of Lights'),
('Guru Nanak Jayanti', '2026-11-23', 'optional', 'Sikh Festival'),
('Christmas', '2026-12-25', 'national', 'Christian Festival')
ON DUPLICATE KEY UPDATE holiday_name = VALUES(holiday_name);

-- Add leave balance tracking table
CREATE TABLE IF NOT EXISTS employee_leave_balance (
    balance_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    employee_id INT(11) NOT NULL,
    year INT(4) NOT NULL,
    casual_leave_total INT(3) DEFAULT 8,
    casual_leave_used INT(3) DEFAULT 0,
    sick_leave_total INT(3) DEFAULT 7,
    sick_leave_used INT(3) DEFAULT 0,
    paid_leave_total INT(3) DEFAULT 12,
    paid_leave_used INT(3) DEFAULT 0,
    unpaid_leave_used INT(3) DEFAULT 0,
    maternity_leave_total INT(3) DEFAULT 180,
    maternity_leave_used INT(3) DEFAULT 0,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    UNIQUE KEY unique_employee_year (employee_id, year),
    INDEX idx_employee_year (employee_id, year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Employee Leave Balance Tracking';
