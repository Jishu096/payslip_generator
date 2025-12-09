<?php
require_once __DIR__ . '/../Config/database.php';

class LoginAttemptHelper {
    private $conn;
    private $maxAttempts = 5;
    private $lockoutMinutes = 1;  // Account locks for 1 minute after 5 failed attempts

    public function __construct($conn = null) {
        // Set timezone to Asia/Kolkata
        date_default_timezone_set('Asia/Kolkata');
        if ($conn === null) {
            $this->conn = getDBConnection();
        } else {
            $this->conn = $conn;
        }
        $this->ensureTable();
    }

    private function ensureTable() {
        $this->conn->exec("CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            success TINYINT(1) DEFAULT 0,
            INDEX (username),
            INDEX (attempted_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }

    public function isAccountLocked($username) {
        $lockoutTime = date('Y-m-d H:i:s', strtotime("-{$this->lockoutMinutes} minutes"));
        
        $sql = "SELECT COUNT(*) as fail_count FROM login_attempts 
                WHERE username = :username 
                AND attempted_at > :lockout_time 
                AND success = 0";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':username' => $username,
            ':lockout_time' => $lockoutTime
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['fail_count'] >= $this->maxAttempts;
    }

    public function recordFailedAttempt($username) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        $sql = "INSERT INTO login_attempts (username, ip_address, success) 
                VALUES (:username, :ip_address, 0)";
        
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':username' => $username,
            ':ip_address' => $ip
        ]);
    }

    public function recordSuccessfulAttempt($username) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        
        $sql = "INSERT INTO login_attempts (username, ip_address, success) 
                VALUES (:username, :ip_address, 1)";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':username' => $username,
            ':ip_address' => $ip
        ]);
        
        // Clear failed attempts for this user
        $clearSql = "DELETE FROM login_attempts 
                     WHERE username = :username AND success = 0";
        $clearStmt = $this->conn->prepare($clearSql);
        return $clearStmt->execute([':username' => $username]);
    }

    public function getRemainingAttempts($username) {
        $lockoutTime = date('Y-m-d H:i:s', strtotime("-{$this->lockoutMinutes} minutes"));
        
        $sql = "SELECT COUNT(*) as fail_count FROM login_attempts 
                WHERE username = :username 
                AND attempted_at > :lockout_time 
                AND success = 0";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':username' => $username,
            ':lockout_time' => $lockoutTime
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $failCount = $result['fail_count'];
        return max(0, $this->maxAttempts - $failCount);
    }

    public function getLockoutExpiryTime($username) {
        $sql = "SELECT MAX(attempted_at) as last_attempt FROM login_attempts 
                WHERE username = :username AND success = 0 
                AND attempted_at > DATE_SUB(NOW(), INTERVAL {$this->lockoutMinutes} MINUTE)";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':username' => $username]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($result['last_attempt']) {
            $expiryTime = date('Y-m-d H:i:s', strtotime("+{$this->lockoutMinutes} minutes", strtotime($result['last_attempt'])));
            return $expiryTime;
        }
        return date('Y-m-d H:i:s', strtotime("+{$this->lockoutMinutes} minutes"));
    }
}
?>
