<?php
/**
 * Application Entry Point
 * 
 * Enhanced with secure session management, configuration management,
 * error handling, and logging.
 * 
 * @version 3.0
 * @updated January 10, 2026
 */

// Load core helpers
require_once __DIR__ . '/../app/Helpers/Config.php';
require_once __DIR__ . '/../app/Helpers/Logger.php';
require_once __DIR__ . '/../app/Helpers/ErrorHandler.php';
require_once __DIR__ . '/../app/Helpers/SessionManager.php';

// Load configuration
Config::load();

// Register error handler
ErrorHandler::register();

// Environment-based error handling
if (Config::isDevelopment()) {
    // Development: Show all errors
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    // Production: Hide errors, log only
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
}

// Start secure session
SessionManager::start();

// Log application start (debug only)
if (Config::isDevelopment()) {
    Logger::debug('Application started', [
        'uri' => $_SERVER['REQUEST_URI'] ?? '',
        'method' => $_SERVER['REQUEST_METHOD'] ?? ''
    ]);
}

// Call the router
require_once __DIR__ . '/../app/Routes/web.php';
