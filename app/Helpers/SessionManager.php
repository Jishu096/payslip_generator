<?php
/**
 * Session Manager Helper
 * 
 * Provides secure session management with timeout, regeneration,
 * and validation features.
 * 
 * @package App\Helpers
 * @version 1.0
 * @created January 10, 2026
 */

class SessionManager {
    
    /**
     * Session timeout in seconds (default: 30 minutes)
     */
    private const SESSION_TIMEOUT = 1800;
    
    /**
     * Session regeneration interval in seconds (default: 5 minutes)
     */
    private const REGENERATION_INTERVAL = 300;
    
    /**
     * Initialize secure session
     * 
     * @param array $options Additional session options
     * @return void
     */
    public static function start(array $options = []): void {
        // Check if session is already started
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        
        // Set secure session configuration
        $defaultOptions = [
            'cookie_lifetime' => 0, // Session cookie (expires on browser close)
            'cookie_httponly' => true, // Prevent JavaScript access
            'cookie_secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on', // HTTPS only
            'cookie_samesite' => 'Strict', // CSRF protection
            'use_strict_mode' => true, // Reject uninitialized session IDs
            'use_only_cookies' => true, // Don't accept session IDs from URL
            'sid_length' => 48, // Longer session ID
            'sid_bits_per_character' => 6 // More entropy
        ];
        
        // Merge with custom options
        $sessionOptions = array_merge($defaultOptions, $options);
        
        // Start session with secure options
        session_start($sessionOptions);
        
        // Initialize session security
        self::initializeSecurity();
    }
    
    /**
     * Initialize session security measures
     * 
     * @return void
     */
    private static function initializeSecurity(): void {
        // Set initial timestamp if not exists
        if (!isset($_SESSION['_created'])) {
            $_SESSION['_created'] = time();
        }
        
        // Set last activity timestamp
        if (!isset($_SESSION['_last_activity'])) {
            $_SESSION['_last_activity'] = time();
        }
        
        // Set last regeneration timestamp
        if (!isset($_SESSION['_last_regeneration'])) {
            $_SESSION['_last_regeneration'] = time();
        }
        
        // Store user agent and IP for validation
        if (!isset($_SESSION['_user_agent'])) {
            $_SESSION['_user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
        }
        
        if (!isset($_SESSION['_ip_address'])) {
            $_SESSION['_ip_address'] = self::getClientIP();
        }
        
        // Check session validity
        self::validateSession();
        
        // Check if session needs regeneration
        self::checkRegeneration();
    }
    
    /**
     * Validate current session
     * 
     * @return bool True if valid, false otherwise
     */
    public static function validateSession(): bool {
        // Check session timeout
        if (self::isExpired()) {
            self::destroy();
            return false;
        }
        
        // Validate user agent
        if (!self::validateUserAgent()) {
            self::destroy();
            return false;
        }
        
        // Validate IP address (optional - can be disabled for dynamic IPs)
        // if (!self::validateIPAddress()) {
        //     self::destroy();
        //     return false;
        // }
        
        // Update last activity
        $_SESSION['_last_activity'] = time();
        
        return true;
    }
    
    /**
     * Check if session is expired
     * 
     * @return bool True if expired, false otherwise
     */
    public static function isExpired(): bool {
        if (!isset($_SESSION['_last_activity'])) {
            return true;
        }
        
        $inactiveTime = time() - $_SESSION['_last_activity'];
        return $inactiveTime > self::SESSION_TIMEOUT;
    }
    
    /**
     * Validate user agent
     * 
     * @return bool True if valid, false otherwise
     */
    private static function validateUserAgent(): bool {
        $currentUserAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $sessionUserAgent = $_SESSION['_user_agent'] ?? '';
        
        return $currentUserAgent === $sessionUserAgent;
    }
    
    /**
     * Validate IP address
     * 
     * @return bool True if valid, false otherwise
     */
    private static function validateIPAddress(): bool {
        $currentIP = self::getClientIP();
        $sessionIP = $_SESSION['_ip_address'] ?? '';
        
        return $currentIP === $sessionIP;
    }
    
    /**
     * Get client IP address
     * 
     * @return string Client IP address
     */
    private static function getClientIP(): string {
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
     * Check if session needs regeneration
     * 
     * @return void
     */
    private static function checkRegeneration(): void {
        $timeSinceRegeneration = time() - ($_SESSION['_last_regeneration'] ?? 0);
        
        if ($timeSinceRegeneration > self::REGENERATION_INTERVAL) {
            self::regenerate();
        }
    }
    
    /**
     * Regenerate session ID
     * 
     * @param bool $deleteOldSession Whether to delete old session
     * @return void
     */
    public static function regenerate(bool $deleteOldSession = true): void {
        // Only regenerate if session is active
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id($deleteOldSession);
            $_SESSION['_last_regeneration'] = time();
        }
    }
    
    /**
     * Destroy session
     * 
     * @return void
     */
    public static function destroy(): void {
        // Unset all session variables
        $_SESSION = [];
        
        // Delete session cookie
        if (isset($_COOKIE[session_name()])) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }
        
        // Destroy session
        session_destroy();
    }
    
    /**
     * Set session value
     * 
     * @param string $key Session key
     * @param mixed $value Session value
     * @return void
     */
    public static function set(string $key, $value): void {
        $_SESSION[$key] = $value;
    }
    
    /**
     * Get session value
     * 
     * @param string $key Session key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed Session value or default
     */
    public static function get(string $key, $default = null) {
        return $_SESSION[$key] ?? $default;
    }
    
    /**
     * Check if session key exists
     * 
     * @param string $key Session key
     * @return bool True if exists, false otherwise
     */
    public static function has(string $key): bool {
        return isset($_SESSION[$key]);
    }
    
    /**
     * Remove session value
     * 
     * @param string $key Session key
     * @return void
     */
    public static function remove(string $key): void {
        unset($_SESSION[$key]);
    }
    
    /**
     * Flash a message (available for one request)
     * 
     * @param string $key Flash key
     * @param mixed $value Flash value
     * @return void
     */
    public static function flash(string $key, $value): void {
        $_SESSION['_flash'][$key] = $value;
    }
    
    /**
     * Get flash message and remove it
     * 
     * @param string $key Flash key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed Flash value or default
     */
    public static function getFlash(string $key, $default = null) {
        $value = $_SESSION['_flash'][$key] ?? $default;
        unset($_SESSION['_flash'][$key]);
        return $value;
    }
    
    /**
     * Check if flash message exists
     * 
     * @param string $key Flash key
     * @return bool True if exists, false otherwise
     */
    public static function hasFlash(string $key): bool {
        return isset($_SESSION['_flash'][$key]);
    }
    
    /**
     * Get session timeout in seconds
     * 
     * @return int Timeout in seconds
     */
    public static function getTimeout(): int {
        return self::SESSION_TIMEOUT;
    }
    
    /**
     * Get remaining session time in seconds
     * 
     * @return int Remaining time in seconds
     */
    public static function getRemainingTime(): int {
        if (!isset($_SESSION['_last_activity'])) {
            return 0;
        }
        
        $elapsed = time() - $_SESSION['_last_activity'];
        $remaining = self::SESSION_TIMEOUT - $elapsed;
        
        return max(0, $remaining);
    }
    
    /**
     * Check if user is logged in
     * 
     * @return bool True if logged in, false otherwise
     */
    public static function isLoggedIn(): bool {
        return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
    }
    
    /**
     * Require authentication (redirect if not logged in)
     * 
     * @param string $redirectUrl URL to redirect to if not logged in
     * @return void
     */
    public static function requireAuth(string $redirectUrl = '/payslip_generator/public/auth/login.php'): void {
        if (!self::isLoggedIn()) {
            header("Location: $redirectUrl");
            exit;
        }
    }
}
