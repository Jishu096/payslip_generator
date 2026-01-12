<?php
/**
 * Database Configuration
 * 
 * Provides database connection using Config helper
 * 
 * @package App\Config
 * @version 2.0
 * @updated January 10, 2026
 */

require_once __DIR__ . '/../Helpers/Config.php';
require_once __DIR__ . '/../Helpers/Logger.php';

/**
 * Get database connection
 * 
 * @return PDO Database connection
 * @throws PDOException If connection fails
 */
function getDBConnection(): PDO {
    static $pdo = null;
    
    // Return existing connection if available
    if ($pdo !== null) {
        return $pdo;
    }
    
    // Load configuration
    Config::load();
    $dbConfig = Config::database();
    
    try {
        // Build DSN
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
            $dbConfig['host'],
            $dbConfig['port'],
            $dbConfig['database']
        );
        
        // PDO options
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
        ];
        
        // Create connection
        $pdo = new PDO(
            $dsn,
            $dbConfig['username'],
            $dbConfig['password'],
            $options
        );
        
        // Log successful connection (debug only)
        if (Config::isDevelopment()) {
            Logger::debug('Database connection established');
        }
        
        return $pdo;
        
    } catch (PDOException $e) {
        // Log error
        Logger::critical('Database connection failed', [
            'error' => $e->getMessage(),
            'host' => $dbConfig['host'],
            'database' => $dbConfig['database']
        ]);
        
        // In production, show generic error
        if (Config::isProduction()) {
            throw new PDOException('Database connection failed. Please try again later.');
        }
        
        // In development, show detailed error
        throw $e;
    }
}

/**
 * Close database connection
 * 
 * @return void
 */
function closeDBConnection(): void {
    $pdo = null;
}