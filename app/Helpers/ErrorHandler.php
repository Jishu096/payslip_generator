<?php
/**
 * Error Handler
 * 
 * Global error and exception handler with logging integration
 * and user-friendly error pages.
 * 
 * @package App\Helpers
 * @version 1.0
 * @created January 10, 2026
 */

require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/Config.php';

class ErrorHandler {
    
    /**
     * Whether handler is registered
     */
    private static $registered = false;
    
    /**
     * Error page templates
     */
    private static $errorPages = [
        403 => 'errors/403.php',
        404 => 'errors/404.php',
        500 => 'errors/500.php',
        503 => 'errors/503.php',
    ];
    
    /**
     * Register error and exception handlers
     * 
     * @return void
     */
    public static function register(): void {
        if (self::$registered) {
            return;
        }
        
        // Set error handler
        set_error_handler([self::class, 'handleError']);
        
        // Set exception handler
        set_exception_handler([self::class, 'handleException']);
        
        // Set shutdown handler for fatal errors
        register_shutdown_function([self::class, 'handleShutdown']);
        
        self::$registered = true;
    }
    
    /**
     * Handle PHP errors
     * 
     * @param int $errno Error number
     * @param string $errstr Error message
     * @param string $errfile Error file
     * @param int $errline Error line
     * @return bool True to prevent default error handler
     */
    public static function handleError(int $errno, string $errstr, string $errfile, int $errline): bool {
        // Don't handle errors suppressed with @
        if (!(error_reporting() & $errno)) {
            return false;
        }
        
        // Map error types to log levels
        $level = self::getLogLevelForError($errno);
        
        // Log the error
        Logger::log($level, "PHP Error: $errstr", [
            'errno' => $errno,
            'file' => $errfile,
            'line' => $errline,
            'type' => self::getErrorTypeName($errno),
        ]);
        
        // In production, show generic error page
        if (Config::isProduction() && in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            self::showErrorPage(500);
            exit;
        }
        
        // Let PHP handle the error display based on display_errors setting
        return false;
    }
    
    /**
     * Handle uncaught exceptions
     * 
     * @param Throwable $exception Exception to handle
     * @return void
     */
    public static function handleException(Throwable $exception): void {
        // Log the exception
        Logger::exception($exception, Logger::CRITICAL);
        
        // Determine HTTP status code
        $statusCode = 500;
        if (method_exists($exception, 'getStatusCode')) {
            $statusCode = $exception->getStatusCode();
        }
        
        // Set HTTP response code
        http_response_code($statusCode);
        
        // Show error page
        if (Config::isProduction()) {
            self::showErrorPage($statusCode);
        } else {
            // In development, show detailed error
            self::showDetailedError($exception);
        }
        
        exit;
    }
    
    /**
     * Handle fatal errors on shutdown
     * 
     * @return void
     */
    public static function handleShutdown(): void {
        $error = error_get_last();
        
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            // Log fatal error
            Logger::critical("Fatal Error: {$error['message']}", [
                'file' => $error['file'],
                'line' => $error['line'],
                'type' => self::getErrorTypeName($error['type']),
            ]);
            
            // Show error page in production
            if (Config::isProduction()) {
                self::showErrorPage(500);
            }
        }
    }
    
    /**
     * Show error page
     * 
     * @param int $statusCode HTTP status code
     * @return void
     */
    private static function showErrorPage(int $statusCode): void {
        // Check if custom error page exists
        if (isset(self::$errorPages[$statusCode])) {
            $errorPage = __DIR__ . '/../../public/' . self::$errorPages[$statusCode];
            
            if (file_exists($errorPage)) {
                require $errorPage;
                return;
            }
        }
        
        // Fallback to generic error page
        self::showGenericErrorPage($statusCode);
    }
    
    /**
     * Show generic error page
     * 
     * @param int $statusCode HTTP status code
     * @return void
     */
    private static function showGenericErrorPage(int $statusCode): void {
        $messages = [
            403 => 'Access Forbidden',
            404 => 'Page Not Found',
            500 => 'Internal Server Error',
            503 => 'Service Unavailable',
        ];
        
        $title = $messages[$statusCode] ?? 'Error';
        $message = self::getErrorMessage($statusCode);
        
        echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>$statusCode - $title</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #333;
        }
        .error-container {
            background: white;
            padding: 60px 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            text-align: center;
            max-width: 600px;
        }
        .error-code {
            font-size: 120px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 20px;
        }
        h1 {
            font-size: 32px;
            margin-bottom: 20px;
            color: #1a1f36;
        }
        p {
            font-size: 16px;
            color: #555;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .btn {
            display: inline-block;
            padding: 14px 30px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
        }
    </style>
</head>
<body>
    <div class='error-container'>
        <div class='error-code'>$statusCode</div>
        <h1>$title</h1>
        <p>$message</p>
        <a href='/payslip_generator/public/auth/login.php' class='btn'>Go to Login</a>
    </div>
</body>
</html>";
    }
    
    /**
     * Show detailed error (development only)
     * 
     * @param Throwable $exception Exception to display
     * @return void
     */
    private static function showDetailedError(Throwable $exception): void {
        $class = get_class($exception);
        $message = htmlspecialchars($exception->getMessage());
        $file = htmlspecialchars($exception->getFile());
        $line = $exception->getLine();
        $trace = htmlspecialchars($exception->getTraceAsString());
        
        echo "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Exception: $class</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Consolas', 'Monaco', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
        }
        .exception-container {
            background: #252526;
            border-left: 4px solid #ef4444;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        h1 {
            color: #ef4444;
            font-size: 24px;
            margin-bottom: 10px;
        }
        .message {
            font-size: 16px;
            margin-bottom: 20px;
            color: #fff;
        }
        .location {
            color: #9cdcfe;
            margin-bottom: 20px;
        }
        .trace {
            background: #1e1e1e;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            white-space: pre-wrap;
            font-size: 13px;
            line-height: 1.6;
        }
        .label {
            color: #4ec9b0;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class='exception-container'>
        <h1>$class</h1>
        <div class='message'>$message</div>
        <div class='location'>
            <span class='label'>File:</span> $file<br>
            <span class='label'>Line:</span> $line
        </div>
        <div class='label'>Stack Trace:</div>
        <div class='trace'>$trace</div>
    </div>
</body>
</html>";
    }
    
    /**
     * Get error message for status code
     * 
     * @param int $statusCode HTTP status code
     * @return string Error message
     */
    private static function getErrorMessage(int $statusCode): string {
        $messages = [
            403 => 'You don\'t have permission to access this resource.',
            404 => 'The page you are looking for could not be found.',
            500 => 'Something went wrong on our end. Please try again later.',
            503 => 'The service is temporarily unavailable. Please try again later.',
        ];
        
        return $messages[$statusCode] ?? 'An error occurred while processing your request.';
    }
    
    /**
     * Get log level for error type
     * 
     * @param int $errno Error number
     * @return string Log level
     */
    private static function getLogLevelForError(int $errno): string {
        switch ($errno) {
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_PARSE:
                return Logger::CRITICAL;
                
            case E_WARNING:
            case E_CORE_WARNING:
            case E_COMPILE_WARNING:
            case E_USER_WARNING:
                return Logger::WARNING;
                
            case E_NOTICE:
            case E_USER_NOTICE:
            case E_DEPRECATED:
            case E_USER_DEPRECATED:
                return Logger::INFO;
                
            default:
                return Logger::ERROR;
        }
    }
    
    /**
     * Get error type name
     * 
     * @param int $errno Error number
     * @return string Error type name
     */
    private static function getErrorTypeName(int $errno): string {
        $types = [
            E_ERROR => 'E_ERROR',
            E_WARNING => 'E_WARNING',
            E_PARSE => 'E_PARSE',
            E_NOTICE => 'E_NOTICE',
            E_CORE_ERROR => 'E_CORE_ERROR',
            E_CORE_WARNING => 'E_CORE_WARNING',
            E_COMPILE_ERROR => 'E_COMPILE_ERROR',
            E_COMPILE_WARNING => 'E_COMPILE_WARNING',
            E_USER_ERROR => 'E_USER_ERROR',
            E_USER_WARNING => 'E_USER_WARNING',
            E_USER_NOTICE => 'E_USER_NOTICE',
            E_STRICT => 'E_STRICT',
            E_RECOVERABLE_ERROR => 'E_RECOVERABLE_ERROR',
            E_DEPRECATED => 'E_DEPRECATED',
            E_USER_DEPRECATED => 'E_USER_DEPRECATED',
        ];
        
        return $types[$errno] ?? 'UNKNOWN';
    }
    
    /**
     * Trigger custom error page
     * 
     * @param int $statusCode HTTP status code
     * @param string|null $message Custom message (optional)
     * @return void
     */
    public static function abort(int $statusCode, ?string $message = null): void {
        http_response_code($statusCode);
        
        if ($message) {
            Logger::warning("HTTP $statusCode: $message");
        }
        
        self::showErrorPage($statusCode);
        exit;
    }
}
