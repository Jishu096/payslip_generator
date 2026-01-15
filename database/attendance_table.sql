-- ========================================
-- ATTENDANCE TABLE (Daily Attendance)
-- Simple daily attendance tracking table
-- ========================================

CREATE TABLE IF NOT EXISTS `attendance` (
    `attendance_id` INT PRIMARY KEY AUTO_INCREMENT,
    `employee_id` INT NOT NULL,
    `date` DATE NOT NULL,
    `status` ENUM('present', 'absent', 'leave', 'holiday') DEFAULT 'present',
    `check_in_time` TIME NULL,
    `check_out_time` TIME NULL,
    `remarks` TEXT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    UNIQUE KEY unique_employee_date (employee_id, date),
    INDEX idx_date (date),
    INDEX idx_employee (employee_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Daily attendance records for employees';
