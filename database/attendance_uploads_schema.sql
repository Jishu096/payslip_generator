-- Attendance Uploads Table
-- Tracks PDF attendance statements uploaded by admin

CREATE TABLE IF NOT EXISTS attendance_uploads (
    upload_id INT PRIMARY KEY AUTO_INCREMENT,
    month VARCHAR(20) NOT NULL,
    year INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    uploaded_by INT NOT NULL,
    status ENUM('UPLOADED', 'VERIFIED', 'REJECTED') DEFAULT 'UPLOADED',
    uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    verified_at TIMESTAMP NULL,
    verified_by INT NULL,
    total_records INT DEFAULT 0,
    remarks TEXT NULL,
    FOREIGN KEY (uploaded_by) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(user_id) ON DELETE SET NULL,
    INDEX idx_month_year (month, year),
    INDEX idx_status (status),
    INDEX idx_uploaded_at (uploaded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
