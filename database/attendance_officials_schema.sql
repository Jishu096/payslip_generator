-- Table to store official positions for attendance statements
CREATE TABLE IF NOT EXISTS attendance_officials (
    id INT PRIMARY KEY AUTO_INCREMENT,
    position_key VARCHAR(100) NOT NULL UNIQUE,
    position_title VARCHAR(150) NOT NULL,
    official_name VARCHAR(150) NOT NULL,
    display_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    updated_by INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (updated_by) REFERENCES users(user_id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert default officials
INSERT INTO attendance_officials (position_key, position_title, official_name, display_order) VALUES
('assistant_accounts_1', 'Assistant Accounts', 'Sh Suvranshu Mahapatra', 1),
('assistant_accounts_2', 'Assistant Accounts', 'Smt Sukanya Palli', 2),
('assistant_director_admin', 'Assistant Director (Admin)', 'Sh Satikanta Dash', 3),
('director_in_charge', 'Director-In-Charge', 'Sh Anil Kumar Shaw', 4);
