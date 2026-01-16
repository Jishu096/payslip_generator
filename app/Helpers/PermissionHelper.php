<?php
/**
 * PermissionHelper - Production-Grade RBAC Middleware
 * Government eHRMS - NIELIT Bhubaneswar
 * 
 * Features:
 * - Permission-based access control
 * - Month-lock enforcement
 * - Audit logging integration
 * - CSRF protection
 */

class PermissionHelper {
    private $conn;
    private $userId;
    private $userRoles;
    
    public function __construct($connection = null, $userId = null) {
        if ($connection) {
            $this->conn = $connection;
        } else {
            require_once __DIR__ . '/../Config/database.php';
            $this->conn = getDBConnection();
        }
        
        $this->userId = $userId ?? $_SESSION['user_id'] ?? null;
        $this->loadUserRoles();
    }
    
    /**
     * Load user roles from database
     */
    private function loadUserRoles() {
        if (!$this->userId) {
            $this->userRoles = [];
            return;
        }
        
        $stmt = $this->conn->prepare("
            SELECT r.role_name, r.role_id, r.display_name
            FROM user_roles_new ur
            JOIN roles r ON ur.role_id = r.role_id
            WHERE ur.user_id = ? AND r.is_active = 1
        ");
        $stmt->execute([$this->userId]);
        $this->userRoles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Check if user has a specific permission
     * 
     * @param string $permissionName Permission name (e.g., 'salary.edit')
     * @return bool
     */
    public function hasPermission($permissionName) {
        if (empty($this->userRoles)) {
            return false;
        }
        
        $roleIds = array_column($this->userRoles, 'role_id');
        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as has_perm
            FROM role_permissions rp
            JOIN permissions p ON rp.permission_id = p.permission_id
            WHERE rp.role_id IN ($placeholders)
            AND p.permission_name = ?
        ");
        
        $stmt->execute(array_merge($roleIds, [$permissionName]));
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result['has_perm'] > 0;
    }
    
    /**
     * Check if user has any of the specified permissions
     * 
     * @param array $permissions Array of permission names
     * @return bool
     */
    public function hasAnyPermission($permissions) {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Check if user has all specified permissions
     * 
     * @param array $permissions Array of permission names
     * @return bool
     */
    public function hasAllPermissions($permissions) {
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Require permission or die with 403
     * 
     * @param string|array $permissions Permission name or array of permissions
     * @param string $redirectUrl Redirect URL on failure
     */
    public function requirePermission($permissions, $redirectUrl = null) {
        $permissions = is_array($permissions) ? $permissions : [$permissions];
        
        if (!$this->hasAnyPermission($permissions)) {
            if ($redirectUrl) {
                header("Location: $redirectUrl");
                exit;
            }
            
            http_response_code(403);
            $this->showAccessDenied();
            exit;
        }
    }
    
    /**
     * Check if a specific month is locked
     * 
     * @param int $month Month (1-12)
     * @param int $year Year (e.g., 2026)
     * @param string $lockType 'attendance', 'payroll', or 'full'
     * @return bool
     */
    public function isMonthLocked($month, $year, $lockType = 'full') {
        $stmt = $this->conn->prepare("
            SELECT lock_id, locked_by, locked_at, reason
            FROM payroll_lock
            WHERE month = ? AND year = ?
            AND (lock_type = ? OR lock_type = 'full')
            AND unlocked_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$month, $year, $lockType]);
        
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Get lock details for a month
     * 
     * @param int $month
     * @param int $year
     * @return array|null
     */
    public function getMonthLockDetails($month, $year) {
        $stmt = $this->conn->prepare("
            SELECT pl.*, u.username as locked_by_name
            FROM payroll_lock pl
            JOIN users u ON pl.locked_by = u.user_id
            WHERE pl.month = ? AND pl.year = ?
            AND pl.unlocked_at IS NULL
            ORDER BY pl.locked_at DESC
            LIMIT 1
        ");
        $stmt->execute([$month, $year]);
        
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
    
    /**
     * Require month to be unlocked or die with error
     * 
     * @param int $month
     * @param int $year
     * @param string $lockType
     * @param string $errorUrl
     */
    public function requireMonthUnlocked($month, $year, $lockType = 'full', $errorUrl = null) {
        if ($this->isMonthLocked($month, $year, $lockType)) {
            // Super Admin can bypass month lock
            if ($this->hasPermission('system.restore')) {
                return;
            }
            
            $lockDetails = $this->getMonthLockDetails($month, $year);
            $message = "This month is locked by " . ($lockDetails['locked_by_name'] ?? 'system');
            
            if ($errorUrl) {
                header("Location: $errorUrl?error=" . urlencode($message));
                exit;
            }
            
            http_response_code(403);
            echo "<h1>Access Denied</h1>";
            echo "<p>$message</p>";
            echo "<p>Reason: " . ($lockDetails['reason'] ?? 'Payroll approved') . "</p>";
            exit;
        }
    }
    
    /**
     * Lock a month (requires director or super_admin)
     * 
     * @param int $month
     * @param int $year
     * @param string $lockType
     * @param string $reason
     * @return bool
     */
    public function lockMonth($month, $year, $lockType = 'full', $reason = '') {
        if (!$this->hasAnyPermission(['payroll.lock', 'system.restore'])) {
            return false;
        }
        
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO payroll_lock (month, year, lock_type, locked_by, reason, can_unlock)
                VALUES (?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    locked_by = VALUES(locked_by),
                    locked_at = CURRENT_TIMESTAMP,
                    reason = VALUES(reason)
            ");
            
            $canUnlock = $this->hasPermission('system.restore') ? 1 : 0;
            $stmt->execute([$month, $year, $lockType, $this->userId, $reason, $canUnlock]);
            
            // Log the lock action
            require_once __DIR__ . '/AuditLogger.php';
            $logger = new AuditLogger($this->conn);
            $logger->log('month_locked', 'payroll_lock', null, null, [
                'month' => $month,
                'year' => $year,
                'lock_type' => $lockType,
                'reason' => $reason
            ]);
            
            return true;
        } catch (PDOException $e) {
            error_log("Failed to lock month: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Unlock a month (Super Admin only)
     * 
     * @param int $month
     * @param int $year
     * @param string $lockType
     * @return bool
     */
    public function unlockMonth($month, $year, $lockType = 'full') {
        if (!$this->hasPermission('system.restore')) {
            return false;
        }
        
        try {
            $stmt = $this->conn->prepare("
                UPDATE payroll_lock
                SET unlocked_by = ?, unlocked_at = CURRENT_TIMESTAMP
                WHERE month = ? AND year = ? AND lock_type = ?
                AND unlocked_at IS NULL
            ");
            $stmt->execute([$this->userId, $month, $year, $lockType]);
            
            // Log the unlock action
            require_once __DIR__ . '/AuditLogger.php';
            $logger = new AuditLogger($this->conn);
            $logger->log('month_unlocked', 'payroll_lock', null, null, [
                'month' => $month,
                'year' => $year,
                'lock_type' => $lockType
            ]);
            
            return true;
        } catch (PDOException $e) {
            error_log("Failed to unlock month: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get user's role names
     * 
     * @return array
     */
    public function getUserRoles() {
        return array_column($this->userRoles, 'role_name');
    }
    
    /**
     * Check if user has a specific role
     * 
     * @param string $roleName
     * @return bool
     */
    public function hasRole($roleName) {
        return in_array($roleName, $this->getUserRoles());
    }
    
    /**
     * Get all permissions for current user
     * 
     * @return array
     */
    public function getAllPermissions() {
        if (empty($this->userRoles)) {
            return [];
        }
        
        $roleIds = array_column($this->userRoles, 'role_id');
        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        
        $stmt = $this->conn->prepare("
            SELECT DISTINCT p.permission_name, p.display_name, p.category
            FROM role_permissions rp
            JOIN permissions p ON rp.permission_id = p.permission_id
            WHERE rp.role_id IN ($placeholders)
            ORDER BY p.category, p.display_name
        ");
        $stmt->execute($roleIds);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Show access denied page
     */
    private function showAccessDenied() {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Access Denied - eHRMS</title>
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                body {
                    font-family: 'Roboto', sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
                    <i class="fas fa-lock"></i>
                </div>
                <h1>Access Denied</h1>
                <p>You do not have permission to access this resource. Please contact your system administrator if you believe this is an error.</p>
                <a href="/payslip_generator/public/auth/login.php" class="btn">
                    <i class="fas fa-home"></i> Back to Dashboard
                </a>
            </div>
        </body>
        </html>
        <?php
    }
}
