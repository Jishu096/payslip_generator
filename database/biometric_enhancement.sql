-- Optional database enhancements for biometric attendance integration
-- Run this SQL when you're ready to integrate biometric devices

-- Add biometric-specific columns to attendance table
ALTER TABLE attendance 
ADD COLUMN device_id VARCHAR(50) NULL COMMENT 'Biometric device identifier',
ADD COLUMN check_in_time TIME NULL COMMENT 'Actual check-in time from device',
ADD COLUMN check_out_time TIME NULL COMMENT 'Actual check-out time from device',
ADD COLUMN biometric_type ENUM('fingerprint', 'face', 'iris', 'card') NULL COMMENT 'Type of biometric verification',
ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP COMMENT 'Last update timestamp',
ADD INDEX idx_attendance_device_id (device_id),
ADD INDEX idx_attendance_updated (updated_at);

-- Create biometric devices table
CREATE TABLE IF NOT EXISTS biometric_devices (
    device_id VARCHAR(50) PRIMARY KEY,
    device_name VARCHAR(100) NOT NULL,
    device_type VARCHAR(50) DEFAULT 'fingerprint',
    location VARCHAR(100) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    last_sync TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Biometric device registry';

-- Create employee biometric enrollment table
CREATE TABLE IF NOT EXISTS employee_biometric (
    enrollment_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    employee_id INT(11) NOT NULL,
    biometric_type ENUM('fingerprint', 'face', 'iris', 'card') NOT NULL,
    template_data LONGTEXT NULL COMMENT 'Encrypted biometric template',
    device_id VARCHAR(50) NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (employee_id) REFERENCES employees(employee_id) ON DELETE CASCADE,
    FOREIGN KEY (device_id) REFERENCES biometric_devices(device_id) ON DELETE SET NULL,
    INDEX idx_employee_bio (employee_id, biometric_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Employee biometric enrollment data';

-- Create attendance sync log (for tracking device sync status)
CREATE TABLE IF NOT EXISTS attendance_sync_log (
    sync_id INT(11) AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(50) NOT NULL,
    sync_start TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sync_end TIMESTAMP NULL,
    records_synced INT(11) DEFAULT 0,
    records_failed INT(11) DEFAULT 0,
    status ENUM('pending', 'success', 'failed', 'partial') DEFAULT 'pending',
    error_message TEXT NULL,
    FOREIGN KEY (device_id) REFERENCES biometric_devices(device_id) ON DELETE CASCADE,
    INDEX idx_sync_device (device_id),
    INDEX idx_sync_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Biometric attendance sync logs';

-- Sample biometric device entry (example)
-- INSERT INTO biometric_devices (device_id, device_name, device_type, location, ip_address) 
-- VALUES ('BIO-001', 'Main Entrance Scanner', 'fingerprint', 'Main Office Entrance', '192.168.1.100');

-- Note: These are optional enhancements. Your current attendance system will work without them.
