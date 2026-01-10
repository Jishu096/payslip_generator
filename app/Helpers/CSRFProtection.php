<?php
/**
 * CSRF Protection Helper
 * 
 * Provides CSRF token generation and validation to protect against
 * Cross-Site Request Forgery attacks.
 * 
 * @package App\Helpers
 * @version 1.0
 * @created January 10, 2026
 */

class CSRFProtection {
    
    /**
     * Token name in session
     */
    private const TOKEN_NAME = 'csrf_token';
    
    /**
     * Token expiry time in seconds (default: 1 hour)
     */
    private const TOKEN_EXPIRY = 3600;
    
    /**
     * Generate a new CSRF token
     * 
     * @return string The generated token
     */
    public static function generateToken(): string {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Generate a random token
        $token = bin2hex(random_bytes(32));
        
        // Store token and timestamp in session
        $_SESSION[self::TOKEN_NAME] = [
            'token' => $token,
            'timestamp' => time()
        ];
        
        return $token;
    }
    
    /**
     * Get the current CSRF token (generates new one if not exists)
     * 
     * @return string The current token
     */
    public static function getToken(): string {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if token exists and is valid
        if (!isset($_SESSION[self::TOKEN_NAME]) || self::isTokenExpired()) {
            return self::generateToken();
        }
        
        return $_SESSION[self::TOKEN_NAME]['token'];
    }
    
    /**
     * Validate a CSRF token
     * 
     * @param string|null $token The token to validate
     * @return bool True if valid, false otherwise
     */
    public static function validateToken(?string $token): bool {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Check if token is provided
        if (empty($token)) {
            return false;
        }
        
        // Check if session token exists
        if (!isset($_SESSION[self::TOKEN_NAME])) {
            return false;
        }
        
        // Check if token is expired
        if (self::isTokenExpired()) {
            self::destroyToken();
            return false;
        }
        
        // Compare tokens using timing-safe comparison
        $sessionToken = $_SESSION[self::TOKEN_NAME]['token'];
        $isValid = hash_equals($sessionToken, $token);
        
        // Regenerate token after validation (one-time use)
        if ($isValid) {
            self::generateToken();
        }
        
        return $isValid;
    }
    
    /**
     * Check if the current token is expired
     * 
     * @return bool True if expired, false otherwise
     */
    private static function isTokenExpired(): bool {
        if (!isset($_SESSION[self::TOKEN_NAME]['timestamp'])) {
            return true;
        }
        
        $tokenAge = time() - $_SESSION[self::TOKEN_NAME]['timestamp'];
        return $tokenAge > self::TOKEN_EXPIRY;
    }
    
    /**
     * Destroy the current CSRF token
     * 
     * @return void
     */
    public static function destroyToken(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        unset($_SESSION[self::TOKEN_NAME]);
    }
    
    /**
     * Generate HTML input field with CSRF token
     * 
     * @return string HTML input field
     */
    public static function getTokenField(): string {
        $token = self::getToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
    
    /**
     * Validate CSRF token from POST request
     * Terminates script with error message if invalid
     * 
     * @param string $errorMessage Custom error message (optional)
     * @return void
     */
    public static function validateOrDie(string $errorMessage = 'Invalid CSRF token. Please refresh the page and try again.'): void {
        $token = $_POST['csrf_token'] ?? null;
        
        if (!self::validateToken($token)) {
            http_response_code(403);
            die($errorMessage);
        }
    }
    
    /**
     * Validate CSRF token from request and return boolean
     * 
     * @return bool True if valid, false otherwise
     */
    public static function validateRequest(): bool {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? null;
        return self::validateToken($token);
    }
    
    /**
     * Get token for AJAX requests (as JSON)
     * 
     * @return string JSON string with token
     */
    public static function getTokenJSON(): string {
        return json_encode([
            'csrf_token' => self::getToken()
        ]);
    }
}
