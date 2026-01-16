<?php
/**
 * CSRFHelper - Cross-Site Request Forgery Protection
 * Government eHRMS - NIELIT Bhubaneswar
 * 
 * Features:
 * - Token generation and validation
 * - Database-backed tokens
 * - Token expiration
 * - One-time use tokens
 */

class CSRFHelper {
    private $conn;
    private $userId;
    private $tokenLifetime = 3600; // 1 hour
    
    public function __construct($connection = null) {
        if ($connection) {
            $this->conn = $connection;
        } else {
            require_once __DIR__ . '/../Config/database.php';
            $this->conn = getDBConnection();
        }
        
        $this->userId = $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Generate a new CSRF token
     * 
     * @param string $action Action identifier (e.g., 'edit_employee', 'generate_payslip')
     * @return string Token
     */
    public function generateToken($action = 'default') {
        if (!$this->userId) {
            throw new Exception('User must be logged in to generate CSRF token');
        }
        
        // Clean up expired tokens first
        $this->cleanupExpiredTokens();
        
        // Generate random token
        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + $this->tokenLifetime);
        
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO csrf_tokens (user_id, token, action, expires_at)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$this->userId, $token, $action, $expiresAt]);
            
            return $token;
        } catch (PDOException $e) {
            error_log("Failed to generate CSRF token: " . $e->getMessage());
            throw new Exception('Failed to generate security token');
        }
    }
    
    /**
     * Validate a CSRF token
     * 
     * @param string $token Token to validate
     * @param string $action Action identifier
     * @param bool $oneTimeUse Mark token as used after validation
     * @return bool
     */
    public function validateToken($token, $action = 'default', $oneTimeUse = true) {
        if (!$this->userId || !$token) {
            return false;
        }
        
        try {
            $stmt = $this->conn->prepare("
                SELECT token_id, expires_at, used_at
                FROM csrf_tokens
                WHERE user_id = ?
                AND token = ?
                AND action = ?
                AND expires_at > NOW()
                LIMIT 1
            ");
            $stmt->execute([$this->userId, $token, $action]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$result) {
                return false;
            }
            
            // Check if token was already used
            if ($result['used_at'] !== null) {
                return false;
            }
            
            // Mark token as used if one-time use
            if ($oneTimeUse) {
                $updateStmt = $this->conn->prepare("
                    UPDATE csrf_tokens
                    SET used_at = NOW()
                    WHERE token_id = ?
                ");
                $updateStmt->execute([$result['token_id']]);
            }
            
            return true;
        } catch (PDOException $e) {
            error_log("Failed to validate CSRF token: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Require valid CSRF token or die
     * 
     * @param string $action Action identifier
     * @param string $redirectUrl Redirect URL on failure
     */
    public function requireToken($action = 'default', $redirectUrl = null) {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? null;
        
        if (!$this->validateToken($token, $action)) {
            if ($redirectUrl) {
                header("Location: $redirectUrl?error=invalid_token");
                exit;
            }
            
            http_response_code(403);
            $this->showCSRFError();
            exit;
        }
    }
    
    /**
     * Generate hidden input field with CSRF token
     * 
     * @param string $action Action identifier
     * @return string HTML input field
     */
    public function getTokenField($action = 'default') {
        $token = $this->generateToken($action);
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }
    
    /**
     * Get token as string (for AJAX requests)
     * 
     * @param string $action Action identifier
     * @return string Token
     */
    public function getToken($action = 'default') {
        return $this->generateToken($action);
    }
    
    /**
     * Clean up expired and used tokens
     */
    private function cleanupExpiredTokens() {
        try {
            // Delete tokens older than 24 hours or already used
            $stmt = $this->conn->prepare("
                DELETE FROM csrf_tokens
                WHERE expires_at < NOW()
                OR (used_at IS NOT NULL AND used_at < DATE_SUB(NOW(), INTERVAL 1 HOUR))
            ");
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("Failed to cleanup CSRF tokens: " . $e->getMessage());
        }
    }
    
    /**
     * Clean up all tokens for a user
     * 
     * @param int $userId User ID
     */
    public function cleanupUserTokens($userId = null) {
        $userId = $userId ?? $this->userId;
        
        if (!$userId) {
            return;
        }
        
        try {
            $stmt = $this->conn->prepare("
                DELETE FROM csrf_tokens
                WHERE user_id = ?
            ");
            $stmt->execute([$userId]);
        } catch (PDOException $e) {
            error_log("Failed to cleanup user CSRF tokens: " . $e->getMessage());
        }
    }
    
    /**
     * Show CSRF error page
     */
    private function showCSRFError() {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Security Error - eHRMS</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                body {
                    font-family: 'Roboto', sans-serif;
                    background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    height: 100vh;
                    margin: 0;
                }
                .error-container {
                    background: white;
                    padding: 50px;
                    border-radius: 20px;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
                    text-align: center;
                    max-width: 500px;
                }
                .error-icon {
                    font-size: 80px;
                    color: #e74c3c;
                    margin-bottom: 20px;
                }
                h1 {
                    color: #2c3e50;
                    margin-bottom: 15px;
                }
                p {
                    color: #7f8c8d;
                    margin-bottom: 30px;
                    line-height: 1.6;
                }
                .btn {
                    display: inline-block;
                    padding: 12px 30px;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    text-decoration: none;
                    border-radius: 25px;
                    font-weight: 600;
                    transition: transform 0.3s ease;
                }
                .btn:hover {
                    transform: translateY(-3px);
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h1>Security Token Invalid</h1>
                <p>Your security token has expired or is invalid. This can happen if you submitted a form after a long period of inactivity, or if you submitted the same form twice.</p>
                <p>Please go back and try again.</p>
                <a href="javascript:history.back()" class="btn">
                    <i class="fas fa-arrow-left"></i> Go Back
                </a>
            </div>
        </body>
        </html>
        <?php
    }
}
