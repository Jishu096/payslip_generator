<?php
/**
 * Login Attempt Helper
 * 
 * Enhanced with rate limiting, IP-based blocking, progressive delays,
 * and email notifications for suspicious activity.
 * 
 * @package App\Helpers
 * @version 2.0
 * @updated January 10, 2026
 */

require_once __DIR__ . '/../Config/database.php';

// EmailHelper is loaded only when needed to avoid dependency issues
// require_once __DIR__ . '/EmailHelper.php';

class LoginAttemptHelper {
    private $conn;
    
    // Configuration
    private $maxAttempts = 5;              // Max failed attempts before lockout
    private $lockoutMinutes = 15;          // Account lockout duration (increased from 1 to 15)
    private $ipMaxAttempts = 10;           // Max attempts from single IP
    private $ipLockoutMinutes = 30;        // IP lockout duration
    private $progressiveDelayEnabled = true; // Enable progressive delays
    
    // Progressive delay configuration (in seconds)
    private $delays = [
        1 => 0,    // 1st attempt: no delay
        2 => 2,    // 2nd attempt: 2 seconds
        3 => 5,    // 3rd attempt: 5 seconds
        4 => 10,   // 4th attempt: 10 seconds
        5 => 30    // 5th+ attempt: 30 seconds
    ];

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

    /**
     * Ensure login_attempts table exists with enhanced schema
     */
    private function ensureTable() {
        $this->conn->exec("CREATE TABLE IF NOT EXISTS login_attempts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            username VARCHAR(255) NOT NULL,
            ip_address VARCHAR(45) NOT NULL,
            user_agent TEXT,
            attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            success TINYINT(1) DEFAULT 0,
            failure_reason VARCHAR(100),
            INDEX idx_username (username),
            INDEX idx_ip_address (ip_address),
            INDEX idx_attempted_at (attempted_at),
            INDEX idx_success (success)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    }

    /**
     * Check if account is locked due to failed attempts
     * 
     * @param string $username Username to check
     * @return bool True if locked, false otherwise
     */
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

    /**
     * Check if IP address is blocked due to excessive attempts
     * 
     * @param string|null $ip IP address to check (uses current IP if null)
     * @return bool True if blocked, false otherwise
     */
    public function isIPBlocked(?string $ip = null): bool {
        $ip = $ip ?? $this->getClientIP();
        $lockoutTime = date('Y-m-d H:i:s', strtotime("-{$this->ipLockoutMinutes} minutes"));
        
        $sql = "SELECT COUNT(*) as fail_count FROM login_attempts 
                WHERE ip_address = :ip 
                AND attempted_at > :lockout_time 
                AND success = 0";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':ip' => $ip,
            ':lockout_time' => $lockoutTime
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['fail_count'] >= $this->ipMaxAttempts;
    }

    /**
     * Apply progressive delay based on failed attempt count
     * 
     * @param string $username Username to check
     * @return void
     */
    public function applyProgressiveDelay(string $username): void {
        if (!$this->progressiveDelayEnabled) {
            return;
        }
        
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
        
        // Get delay for this attempt count
        $delay = $this->delays[$failCount] ?? $this->delays[5];
        
        if ($delay > 0) {
            sleep($delay);
        }
    }

    /**
     * Record failed login attempt
     * 
     * @param string $username Username
     * @param string $reason Failure reason
     * @return bool Success status
     */
    public function recordFailedAttempt($username, $reason = 'Invalid credentials') {
        $ip = $this->getClientIP();
        
        $sql = "INSERT INTO login_attempts (username, ip_address, success, failure_reason) 
                VALUES (:username, :ip_address, 0, :reason)";
        
        $stmt = $this->conn->prepare($sql);
        $result = $stmt->execute([
            ':username' => $username,
            ':ip_address' => $ip,
            ':reason' => $reason
        ]);
        
        // Check if we should send notification
        $this->checkSuspiciousActivity($username, $ip);
        
        return $result;
    }

    /**
     * Record successful login attempt
     * 
     * @param string $username Username
     * @return bool Success status
     */
    public function recordSuccessfulAttempt($username) {
        $ip = $this->getClientIP();
        
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

    /**
     * Get remaining login attempts before lockout
     * 
     * @param string $username Username to check
     * @return int Remaining attempts
     */
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

    /**
     * Get lockout expiry time for account
     * 
     * @param string $username Username to check
     * @return string Expiry time
     */
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

    /**
     * Get client IP address
     * 
     * @return string Client IP
     */
    private function getClientIP(): string {
        $ipKeys = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ipKeys as $key) {
            if (isset($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                
                // Handle multiple IPs (take first one)
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }

    /**
     * Check for suspicious activity and send notifications
     * 
     * @param string $username Username
     * @param string $ip IP address
     * @return void
     */
    private function checkSuspiciousActivity(string $username, string $ip): void {
        $remaining = $this->getRemainingAttempts($username);
        
        // Send notification when account is about to be locked (1 attempt remaining)
        if ($remaining === 1) {
            $this->sendSecurityNotification($username, $ip, 'warning');
        }
        
        // Send notification when account is locked
        if ($remaining === 0) {
            $this->sendSecurityNotification($username, $ip, 'locked');
        }
    }

    /**
     * Send security notification email
     * 
     * @param string $username Username
     * @param string $ip IP address
     * @param string $type Notification type (warning|locked)
     * @return void
     */
    private function sendSecurityNotification(string $username, string $ip, string $type): void {
        try {
            // Get user email from database
            $sql = "SELECT email FROM users WHERE username = :username LIMIT 1";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || empty($user['email'])) {
                return; // No email found
            }
            
            // Load EmailHelper only when needed
            if (!class_exists('EmailHelper')) {
                require_once __DIR__ . '/EmailHelper.php';
            }
            
            $emailHelper = new EmailHelper();
            $timestamp = date('Y-m-d H:i:s');
            
            if ($type === 'warning') {
                $subject = 'Security Alert: Multiple Failed Login Attempts';
                $message = "
                    <h2>Security Alert</h2>
                    <p>We detected multiple failed login attempts on your account.</p>
                    <p><strong>Details:</strong></p>
                    <ul>
                        <li>Username: {$username}</li>
                        <li>IP Address: {$ip}</li>
                        <li>Time: {$timestamp}</li>
                        <li>Remaining Attempts: 1</li>
                    </ul>
                    <p><strong>Action Required:</strong></p>
                    <p>If this was you, please ensure you're using the correct password. If this wasn't you, your account may be under attack. Please change your password immediately.</p>
                ";
            } else {
                $subject = 'Security Alert: Account Locked';
                $message = "
                    <h2>Account Locked</h2>
                    <p>Your account has been temporarily locked due to multiple failed login attempts.</p>
                    <p><strong>Details:</strong></p>
                    <ul>
                        <li>Username: {$username}</li>
                        <li>IP Address: {$ip}</li>
                        <li>Time: {$timestamp}</li>
                        <li>Lockout Duration: {$this->lockoutMinutes} minutes</li>
                    </ul>
                    <p><strong>What to do:</strong></p>
                    <p>Your account will be automatically unlocked in {$this->lockoutMinutes} minutes. If you didn't attempt to log in, please contact support immediately.</p>
                ";
            }
            
            $emailHelper->sendEmail($user['email'], $subject, $message);
        } catch (Exception $e) {
            // Log error but don't fail the login process
            error_log("Failed to send security notification: " . $e->getMessage());
        }
    }

    /**
     * Get failed login attempts for monitoring (admin dashboard)
     * 
     * @param int $limit Number of records to return
     * @param int $hours Time window in hours
     * @return array Failed attempts
     */
    public function getRecentFailedAttempts(int $limit = 50, int $hours = 24): array {
        $timeWindow = date('Y-m-d H:i:s', strtotime("-{$hours} hours"));
        
        $sql = "SELECT username, ip_address, user_agent, attempted_at, failure_reason
                FROM login_attempts 
                WHERE success = 0 
                AND attempted_at > :time_window
                ORDER BY attempted_at DESC 
                LIMIT :limit";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bindValue(':time_window', $timeWindow, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get statistics for admin dashboard
     * 
     * @return array Statistics
     */
    public function getStatistics(): array {
        $stats = [];
        
        // Failed attempts in last 24 hours
        $sql = "SELECT COUNT(*) as count FROM login_attempts 
                WHERE success = 0 
                AND attempted_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $stmt = $this->conn->query($sql);
        $stats['failed_24h'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Successful logins in last 24 hours
        $sql = "SELECT COUNT(*) as count FROM login_attempts 
                WHERE success = 1 
                AND attempted_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $stmt = $this->conn->query($sql);
        $stats['successful_24h'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Currently locked accounts
        $lockoutTime = date('Y-m-d H:i:s', strtotime("-{$this->lockoutMinutes} minutes"));
        $sql = "SELECT COUNT(DISTINCT username) as count FROM login_attempts 
                WHERE success = 0 
                AND attempted_at > :lockout_time
                GROUP BY username
                HAVING COUNT(*) >= :max_attempts";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':lockout_time' => $lockoutTime,
            ':max_attempts' => $this->maxAttempts
        ]);
        $stats['locked_accounts'] = $stmt->rowCount();
        
        // Blocked IPs
        $ipLockoutTime = date('Y-m-d H:i:s', strtotime("-{$this->ipLockoutMinutes} minutes"));
        $sql = "SELECT COUNT(DISTINCT ip_address) as count FROM login_attempts 
                WHERE success = 0 
                AND attempted_at > :lockout_time
                GROUP BY ip_address
                HAVING COUNT(*) >= :max_attempts";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':lockout_time' => $ipLockoutTime,
            ':max_attempts' => $this->ipMaxAttempts
        ]);
        $stats['blocked_ips'] = $stmt->rowCount();
        
        return $stats;
    }

    /**
     * Clean up old login attempts (for maintenance)
     * 
     * @param int $days Delete records older than this many days
     * @return int Number of deleted records
     */
    public function cleanupOldAttempts(int $days = 30): int {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        $sql = "DELETE FROM login_attempts WHERE attempted_at < :cutoff_date";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':cutoff_date' => $cutoffDate]);
        
        return $stmt->rowCount();
    }
}
?>
