<?php
/**
 * Logger Helper
 * 
 * Centralized logging system with multiple log levels,
 * file rotation, and structured logging support.
 * 
 * @package App\Helpers
 * @version 1.0
 * @created January 10, 2026
 */

require_once __DIR__ . '/Config.php';

class Logger {
    
    /**
     * Log levels
     */
    const DEBUG = 'DEBUG';
    const INFO = 'INFO';
    const WARNING = 'WARNING';
    const ERROR = 'ERROR';
    const CRITICAL = 'CRITICAL';
    
    /**
     * Log level priorities
     */
    private static $levelPriorities = [
        'DEBUG' => 0,
        'INFO' => 1,
        'WARNING' => 2,
        'ERROR' => 3,
        'CRITICAL' => 4,
    ];
    
    /**
     * Log directory path
     */
    private static $logPath = null;
    
    /**
     * Maximum log file size (5MB)
     */
    private static $maxFileSize = 5242880;
    
    /**
     * Maximum number of log files to keep
     */
    private static $maxFiles = 30;
    
    /**
     * Initialize logger
     * 
     * @return void
     */
    private static function init(): void {
        if (self::$logPath !== null) {
            return;
        }
        
        // Get log path from config
        $logPath = Config::get('LOG_PATH', 'storage/logs');
        
        // Convert to absolute path if relative
        if (!preg_match('/^[a-zA-Z]:/', $logPath)) {
            self::$logPath = __DIR__ . '/../../' . $logPath;
        } else {
            self::$logPath = $logPath;
        }
        
        // Create log directory if it doesn't exist
        if (!is_dir(self::$logPath)) {
            mkdir(self::$logPath, 0755, true);
        }
        
        // Get max files from config
        self::$maxFiles = (int) Config::get('LOG_MAX_FILES', 30);
    }
    
    /**
     * Log a message
     * 
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context
     * @return bool Success status
     */
    public static function log(string $level, string $message, array $context = []): bool {
        self::init();
        
        // Check if we should log this level
        if (!self::shouldLog($level)) {
            return true; // Silently ignore
        }
        
        // Format log entry
        $logEntry = self::formatLogEntry($level, $message, $context);
        
        // Get log file path
        $logFile = self::getLogFilePath();
        
        // Rotate log if needed
        self::rotateLogIfNeeded($logFile);
        
        // Write to log file
        return file_put_contents($logFile, $logEntry . PHP_EOL, FILE_APPEND | LOCK_EX) !== false;
    }
    
    /**
     * Check if we should log this level
     * 
     * @param string $level Log level
     * @return bool True if should log, false otherwise
     */
    private static function shouldLog(string $level): bool {
        $configLevel = strtoupper(Config::get('LOG_LEVEL', 'ERROR'));
        
        if (!isset(self::$levelPriorities[$level]) || !isset(self::$levelPriorities[$configLevel])) {
            return true;
        }
        
        return self::$levelPriorities[$level] >= self::$levelPriorities[$configLevel];
    }
    
    /**
     * Format log entry
     * 
     * @param string $level Log level
     * @param string $message Log message
     * @param array $context Additional context
     * @return string Formatted log entry
     */
    private static function formatLogEntry(string $level, string $message, array $context): string {
        $timestamp = date('Y-m-d H:i:s');
        
        // Basic format
        $entry = "[$timestamp] [$level] $message";
        
        // Add context if provided
        if (!empty($context)) {
            $entry .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        
        return $entry;
    }
    
    /**
     * Get log file path
     * 
     * @return string Log file path
     */
    private static function getLogFilePath(): string {
        $date = date('Y-m-d');
        return self::$logPath . "/app-{$date}.log";
    }
    
    /**
     * Rotate log file if needed
     * 
     * @param string $logFile Log file path
     * @return void
     */
    private static function rotateLogIfNeeded(string $logFile): void {
        if (!file_exists($logFile)) {
            return;
        }
        
        // Check file size
        if (filesize($logFile) >= self::$maxFileSize) {
            $timestamp = date('YmdHis');
            $rotatedFile = $logFile . '.' . $timestamp;
            rename($logFile, $rotatedFile);
            
            // Compress rotated file
            if (function_exists('gzopen')) {
                self::compressLogFile($rotatedFile);
            }
        }
        
        // Clean up old log files
        self::cleanupOldLogs();
    }
    
    /**
     * Compress log file
     * 
     * @param string $file File to compress
     * @return void
     */
    private static function compressLogFile(string $file): void {
        $gzFile = $file . '.gz';
        
        $fp = fopen($file, 'rb');
        $gzfp = gzopen($gzFile, 'wb9');
        
        while (!feof($fp)) {
            gzwrite($gzfp, fread($fp, 1024 * 512));
        }
        
        fclose($fp);
        gzclose($gzfp);
        
        // Delete original file
        unlink($file);
    }
    
    /**
     * Clean up old log files
     * 
     * @return void
     */
    private static function cleanupOldLogs(): void {
        $files = glob(self::$logPath . '/app-*.log*');
        
        if (count($files) <= self::$maxFiles) {
            return;
        }
        
        // Sort by modification time
        usort($files, function($a, $b) {
            return filemtime($a) - filemtime($b);
        });
        
        // Delete oldest files
        $filesToDelete = array_slice($files, 0, count($files) - self::$maxFiles);
        foreach ($filesToDelete as $file) {
            unlink($file);
        }
    }
    
    /**
     * Log debug message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     * @return bool Success status
     */
    public static function debug(string $message, array $context = []): bool {
        return self::log(self::DEBUG, $message, $context);
    }
    
    /**
     * Log info message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     * @return bool Success status
     */
    public static function info(string $message, array $context = []): bool {
        return self::log(self::INFO, $message, $context);
    }
    
    /**
     * Log warning message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     * @return bool Success status
     */
    public static function warning(string $message, array $context = []): bool {
        return self::log(self::WARNING, $message, $context);
    }
    
    /**
     * Log error message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     * @return bool Success status
     */
    public static function error(string $message, array $context = []): bool {
        return self::log(self::ERROR, $message, $context);
    }
    
    /**
     * Log critical message
     * 
     * @param string $message Log message
     * @param array $context Additional context
     * @return bool Success status
     */
    public static function critical(string $message, array $context = []): bool {
        return self::log(self::CRITICAL, $message, $context);
    }
    
    /**
     * Log exception
     * 
     * @param Throwable $exception Exception to log
     * @param string $level Log level (default: ERROR)
     * @return bool Success status
     */
    public static function exception(Throwable $exception, string $level = self::ERROR): bool {
        $context = [
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ];
        
        return self::log($level, 'Exception occurred: ' . $exception->getMessage(), $context);
    }
    
    /**
     * Log security event
     * 
     * @param string $event Event description
     * @param array $context Additional context
     * @return bool Success status
     */
    public static function security(string $event, array $context = []): bool {
        $context['ip'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $context['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $context['user_id'] = $_SESSION['user_id'] ?? null;
        
        return self::log(self::WARNING, "SECURITY: $event", $context);
    }
    
    /**
     * Log performance metric
     * 
     * @param string $metric Metric name
     * @param float $value Metric value
     * @param string $unit Unit of measurement
     * @return bool Success status
     */
    public static function performance(string $metric, float $value, string $unit = 'ms'): bool {
        $context = [
            'metric' => $metric,
            'value' => $value,
            'unit' => $unit,
        ];
        
        return self::log(self::INFO, "PERFORMANCE: $metric = $value $unit", $context);
    }
    
    /**
     * Get recent log entries
     * 
     * @param int $lines Number of lines to retrieve
     * @param string|null $level Filter by log level (optional)
     * @return array Log entries
     */
    public static function getRecentLogs(int $lines = 100, ?string $level = null): array {
        self::init();
        
        $logFile = self::getLogFilePath();
        
        if (!file_exists($logFile)) {
            return [];
        }
        
        // Read last N lines
        $file = new SplFileObject($logFile, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();
        $startLine = max(0, $lastLine - $lines);
        
        $logs = [];
        $file->seek($startLine);
        
        while (!$file->eof()) {
            $line = trim($file->current());
            
            if (!empty($line)) {
                // Filter by level if specified
                if ($level === null || strpos($line, "[$level]") !== false) {
                    $logs[] = $line;
                }
            }
            
            $file->next();
        }
        
        return $logs;
    }
    
    /**
     * Clear all logs
     * 
     * @return bool Success status
     */
    public static function clearLogs(): bool {
        self::init();
        
        $files = glob(self::$logPath . '/app-*.log*');
        
        foreach ($files as $file) {
            if (!unlink($file)) {
                return false;
            }
        }
        
        return true;
    }
}
