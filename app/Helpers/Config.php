<?php
/**
 * Configuration Helper
 * 
 * Loads and manages application configuration from .env file
 * Built-in .env parser (no external dependencies required)
 * 
 * @package App\Helpers
 * @version 1.0
 * @created January 10, 2026
 */

class Config {
    
    /**
     * Configuration values
     */
    private static $config = [];
    
    /**
     * Whether configuration has been loaded
     */
    private static $loaded = false;
    
    /**
     * Path to .env file
     */
    private static $envPath = null;
    
    /**
     * Load configuration from .env file
     * 
     * @param string|null $envPath Path to .env file (optional)
     * @return void
     */
    public static function load(?string $envPath = null): void {
        if (self::$loaded) {
            return; // Already loaded
        }
        
        // Determine .env file path
        if ($envPath === null) {
            $envPath = __DIR__ . '/../../.env';
        }
        
        self::$envPath = $envPath;
        
        // Check if .env file exists
        if (!file_exists($envPath)) {
            // Use default values if .env doesn't exist
            self::loadDefaults();
            self::$loaded = true;
            return;
        }
        
        // Parse .env file
        self::parseEnvFile($envPath);
        
        // Load from environment variables
        self::loadFromEnvironment();
        
        self::$loaded = true;
    }
    
    /**
     * Parse .env file
     * 
     * @param string $path Path to .env file
     * @return void
     */
    private static function parseEnvFile(string $path): void {
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            // Skip comments
            if (strpos(trim($line), '#') === 0) {
                continue;
            }
            
            // Parse key=value
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                
                $key = trim($key);
                $value = trim($value);
                
                // Remove quotes
                $value = self::removeQuotes($value);
                
                // Handle variable substitution ${VAR_NAME}
                $value = self::substituteVariables($value);
                
                // Store in config
                self::$config[$key] = $value;
                
                // Also set as environment variable
                putenv("$key=$value");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }
    
    /**
     * Remove quotes from value
     * 
     * @param string $value Value to process
     * @return string Unquoted value
     */
    private static function removeQuotes(string $value): string {
        if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
            (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
            return substr($value, 1, -1);
        }
        return $value;
    }
    
    /**
     * Substitute variables in value
     * 
     * @param string $value Value to process
     * @return string Processed value
     */
    private static function substituteVariables(string $value): string {
        // Replace ${VAR_NAME} with actual value
        return preg_replace_callback('/\$\{([A-Z_]+)\}/', function($matches) {
            $varName = $matches[1];
            // Access config directly to avoid recursion loop in get() -> load()
            if (isset(self::$config[$varName])) {
                return self::$config[$varName];
            }
            // Check environment variables
            $envVal = getenv($varName);
            if ($envVal !== false) {
                return $envVal;
            }
            return $_ENV[$varName] ?? '';
        }, $value);
    }

    
    /**
     * Load configuration from environment variables
     * 
     * @return void
     */
    private static function loadFromEnvironment(): void {
        // Override with actual environment variables
        foreach ($_ENV as $key => $value) {
            if (!isset(self::$config[$key])) {
                self::$config[$key] = $value;
            }
        }
    }
    
    /**
     * Load default configuration values
     * 
     * @return void
     */
    private static function loadDefaults(): void {
        self::$config = [
            'APP_NAME' => 'e-HRMS Payslip Generator',
            'APP_ENV' => 'production',
            'APP_DEBUG' => 'false',
            'APP_URL' => 'http://localhost/payslip_generator',
            'APP_TIMEZONE' => 'Asia/Kolkata',
            
            'DB_HOST' => 'localhost',
            'DB_PORT' => '3306',
            'DB_DATABASE' => 'payslip_generator',
            'DB_USERNAME' => 'root',
            'DB_PASSWORD' => '',
            
            'SESSION_LIFETIME' => '1800',
            'SESSION_SECURE' => 'false',
            'CSRF_TOKEN_EXPIRY' => '3600',
            'LOGIN_MAX_ATTEMPTS' => '5',
            'LOGIN_LOCKOUT_TIME' => '900',
            
            'LOG_LEVEL' => 'error',
            'LOG_PATH' => 'storage/logs',
            'LOG_MAX_FILES' => '30',
            
            'CACHE_ENABLED' => 'true',
            'CACHE_DRIVER' => 'file',
            'CACHE_PATH' => 'storage/cache',
            'CACHE_DEFAULT_TTL' => '3600',
        ];
    }
    
    /**
     * Get configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $default Default value if key doesn't exist
     * @return mixed Configuration value
     */
    public static function get(string $key, $default = null) {
        if (!self::$loaded) {
            self::load();
        }
        
        return self::$config[$key] ?? $default;
    }
    
    /**
     * Set configuration value
     * 
     * @param string $key Configuration key
     * @param mixed $value Configuration value
     * @return void
     */
    public static function set(string $key, $value): void {
        if (!self::$loaded) {
            self::load();
        }
        
        self::$config[$key] = $value;
    }
    
    /**
     * Check if configuration key exists
     * 
     * @param string $key Configuration key
     * @return bool True if exists, false otherwise
     */
    public static function has(string $key): bool {
        if (!self::$loaded) {
            self::load();
        }
        
        return isset(self::$config[$key]);
    }
    
    /**
     * Get all configuration values
     * 
     * @return array All configuration
     */
    public static function all(): array {
        if (!self::$loaded) {
            self::load();
        }
        
        return self::$config;
    }
    
    /**
     * Get application environment
     * 
     * @return string Environment (development, production, etc.)
     */
    public static function env(): string {
        return self::get('APP_ENV', 'production');
    }
    
    /**
     * Check if application is in debug mode
     * 
     * @return bool True if debug mode, false otherwise
     */
    public static function isDebug(): bool {
        $debug = self::get('APP_DEBUG', 'false');
        return in_array(strtolower($debug), ['true', '1', 'yes', 'on']);
    }
    
    /**
     * Check if application is in production
     * 
     * @return bool True if production, false otherwise
     */
    public static function isProduction(): bool {
        return self::env() === 'production';
    }
    
    /**
     * Check if application is in development
     * 
     * @return bool True if development, false otherwise
     */
    public static function isDevelopment(): bool {
        return in_array(self::env(), ['development', 'dev', 'local']);
    }
    
    /**
     * Get database configuration
     * 
     * @return array Database configuration
     */
    public static function database(): array {
        return [
            'host' => self::get('DB_HOST', 'localhost'),
            'port' => self::get('DB_PORT', '3306'),
            'database' => self::get('DB_DATABASE', 'payslip_generator'),
            'username' => self::get('DB_USERNAME', 'root'),
            'password' => self::get('DB_PASSWORD', ''),
        ];
    }
    
    /**
     * Get mail configuration
     * 
     * @return array Mail configuration
     */
    public static function mail(): array {
        return [
            'host' => self::get('MAIL_HOST', 'smtp.gmail.com'),
            'port' => self::get('MAIL_PORT', '587'),
            'username' => self::get('MAIL_USERNAME', ''),
            'password' => self::get('MAIL_PASSWORD', ''),
            'from_address' => self::get('MAIL_FROM_ADDRESS', 'noreply@example.com'),
            'from_name' => self::get('MAIL_FROM_NAME', self::get('APP_NAME', 'e-HRMS')),
            'encryption' => self::get('MAIL_ENCRYPTION', 'tls'),
        ];
    }
    
    /**
     * Get session configuration
     * 
     * @return array Session configuration
     */
    public static function session(): array {
        return [
            'lifetime' => (int) self::get('SESSION_LIFETIME', 1800),
            'secure' => self::get('SESSION_SECURE', 'false') === 'true',
        ];
    }
    
    /**
     * Get security configuration
     * 
     * @return array Security configuration
     */
    public static function security(): array {
        return [
            'csrf_token_expiry' => (int) self::get('CSRF_TOKEN_EXPIRY', 3600),
            'login_max_attempts' => (int) self::get('LOGIN_MAX_ATTEMPTS', 5),
            'login_lockout_time' => (int) self::get('LOGIN_LOCKOUT_TIME', 900),
            'ip_max_attempts' => (int) self::get('IP_MAX_ATTEMPTS', 10),
            'ip_lockout_time' => (int) self::get('IP_LOCKOUT_TIME', 1800),
        ];
    }
    
    /**
     * Reload configuration
     * 
     * @return void
     */
    public static function reload(): void {
        self::$loaded = false;
        self::$config = [];
        self::load(self::$envPath);
    }
}
