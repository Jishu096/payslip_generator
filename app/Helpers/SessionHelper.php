<?php
/**
 * Session Management Helper
 * 
 * Handles session timeout and security features
 */

class SessionHelper
{
    private static $conn = null;
    private static $settings = null;
    
    // Default timeout: 30 minutes (in seconds)
    const DEFAULT_TIMEOUT = 1800;
    
    /**
     * Initialize session with security settings
     */
    public static function init()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Load settings if not already loaded
        if (self::$settings === null) {
            self::loadSettings();
        }
        
        // Check session timeout if enabled
        if (self::isTimeoutEnabled()) {
            self::checkTimeout();
        }
        
        // Update last activity time
        $_SESSION['LAST_ACTIVITY'] = time();
    }
    
    /**
     * Load settings from database
     */
    private static function loadSettings()
    {
        self::$settings = [];
        
        try {
            require_once __DIR__ . '/../Config/database.php';
            $db = new Database();
            self::$conn = $db->connect();
            
            $stmt = self::$conn->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('session_timeout', 'session_timeout_minutes')");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                self::$settings[$row['setting_key']] = $row['setting_value'];
            }
        } catch (Exception $e) {
            // Use defaults if database unavailable
            self::$settings['session_timeout'] = '1';
            self::$settings['session_timeout_minutes'] = '30';
        }
    }
    
    /**
     * Check if session timeout is enabled
     */
    public static function isTimeoutEnabled()
    {
        return (self::$settings['session_timeout'] ?? '0') === '1';
    }
    
    /**
     * Get timeout duration in seconds
     */
    public static function getTimeoutSeconds()
    {
        $minutes = (int)(self::$settings['session_timeout_minutes'] ?? 30);
        return $minutes * 60;
    }
    
    /**
     * Check if session has timed out
     */
    private static function checkTimeout()
    {
        if (isset($_SESSION['LAST_ACTIVITY'])) {
            $inactive = time() - $_SESSION['LAST_ACTIVITY'];
            $timeout = self::getTimeoutSeconds();
            
            if ($inactive > $timeout) {
                self::logout('timeout');
            }
        }
    }
    
    /**
     * Get remaining session time in seconds
     */
    public static function getRemainingTime()
    {
        if (!isset($_SESSION['LAST_ACTIVITY']) || !self::isTimeoutEnabled()) {
            return null;
        }
        
        $inactive = time() - $_SESSION['LAST_ACTIVITY'];
        $timeout = self::getTimeoutSeconds();
        $remaining = $timeout - $inactive;
        
        return max(0, $remaining);
    }
    
    /**
     * Logout user and redirect
     */
    public static function logout($reason = 'manual')
    {
        // Store logout reason before destroying session
        $logoutReason = $reason;
        
        // Clear all session data
        $_SESSION = array();
        
        // Destroy session cookie
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        // Destroy session
        session_destroy();
        
        // Redirect with reason
        $redirectUrl = '/payslip_generator/public/auth/login.php';
        if ($logoutReason === 'timeout') {
            $redirectUrl .= '?timeout=1';
        }
        
        header("Location: " . $redirectUrl);
        exit;
    }
    
    /**
     * Check if user is logged in
     */
    public static function isLoggedIn()
    {
        return isset($_SESSION['user_id']) && isset($_SESSION['role']);
    }
    
    /**
     * Require authentication - redirect to login if not logged in
     */
    public static function requireAuth($allowedRoles = null)
    {
        self::init();
        
        if (!self::isLoggedIn()) {
            header("Location: /payslip_generator/public/auth/login.php");
            exit;
        }
        
        // Check role if specified
        if ($allowedRoles !== null) {
            $userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role']];
            $hasAccess = false;
            
            foreach ((array)$allowedRoles as $role) {
                if (in_array($role, $userRoles)) {
                    $hasAccess = true;
                    break;
                }
            }
            
            if (!$hasAccess) {
                header("Location: /payslip_generator/public/auth/login.php?unauthorized=1");
                exit;
            }
        }
    }
    
    /**
     * Get JavaScript for session timeout warning
     */
    public static function getTimeoutWarningScript()
    {
        if (!self::isTimeoutEnabled() || !self::isLoggedIn()) {
            return '';
        }
        
        $timeout = self::getTimeoutSeconds();
        $warningTime = $timeout - 120; // Warn 2 minutes before timeout
        
        return <<<SCRIPT
<script>
(function() {
    const sessionTimeout = {$timeout} * 1000;
    const warningTime = {$warningTime} * 1000;
    let lastActivity = Date.now();
    let warningShown = false;
    
    // Update activity on user interaction
    ['click', 'keypress', 'scroll', 'mousemove'].forEach(event => {
        document.addEventListener(event, () => {
            lastActivity = Date.now();
            warningShown = false;
        }, { passive: true });
    });
    
    // Check timeout periodically
    setInterval(() => {
        const inactive = Date.now() - lastActivity;
        
        // Show warning 2 minutes before timeout
        if (inactive >= warningTime && !warningShown) {
            warningShown = true;
            if (confirm('Your session will expire in 2 minutes due to inactivity. Click OK to stay logged in.')) {
                // Refresh activity by making a request
                fetch(window.location.href, { method: 'HEAD' });
                lastActivity = Date.now();
            }
        }
        
        // Force logout if timeout exceeded
        if (inactive >= sessionTimeout) {
            window.location.href = '/payslip_generator/public/auth/login.php?timeout=1';
        }
    }, 30000); // Check every 30 seconds
})();
</script>
SCRIPT;
    }
}
