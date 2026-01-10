<?php
/**
 * Debug Login API
 * 
 * This script helps identify what's causing the login error
 */

// Enable error display
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Login API Debug</h1>";
echo "<pre>";

// Test 1: Check if files exist
echo "=== File Existence Check ===\n";
$files = [
    'SessionManager' => __DIR__ . '/../../app/Helpers/SessionManager.php',
    'InputValidator' => __DIR__ . '/../../app/Helpers/InputValidator.php',
    'Config' => __DIR__ . '/../../app/Helpers/Config.php',
    'database' => __DIR__ . '/../../app/Config/database.php',
    'User Model' => __DIR__ . '/../../app/Models/User.php',
    'Employee Model' => __DIR__ . '/../../app/Models/Employee.php',
    'LoginAttemptHelper' => __DIR__ . '/../../app/Helpers/LoginAttemptHelper.php',
];

foreach ($files as $name => $path) {
    $exists = file_exists($path);
    echo "$name: " . ($exists ? "✅ EXISTS" : "❌ NOT FOUND") . " ($path)\n";
}

echo "\n=== Loading Files ===\n";

// Test 2: Try loading each file
try {
    echo "Loading SessionManager... ";
    require_once __DIR__ . '/../../app/Helpers/SessionManager.php';
    echo "✅\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

try {
    echo "Loading InputValidator... ";
    require_once __DIR__ . '/../../app/Helpers/InputValidator.php';
    echo "✅\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

try {
    echo "Loading Config... ";
    require_once __DIR__ . '/../../app/Helpers/Config.php';
    echo "✅\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

try {
    echo "Loading database.php... ";
    require_once __DIR__ . '/../../app/Config/database.php';
    echo "✅\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

try {
    echo "Loading User Model... ";
    require_once __DIR__ . '/../../app/Models/User.php';
    echo "✅\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

try {
    echo "Loading Employee Model... ";
    require_once __DIR__ . '/../../app/Models/Employee.php';
    echo "✅\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

try {
    echo "Loading LoginAttemptHelper... ";
    require_once __DIR__ . '/../../app/Helpers/LoginAttemptHelper.php';
    echo "✅\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n=== Testing Database Connection ===\n";
try {
    $conn = getDBConnection();
    echo "✅ Database connection successful!\n";
    echo "MySQL Version: " . $conn->query('SELECT VERSION()')->fetchColumn() . "\n";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "\n";
}

echo "\n=== Testing SessionManager ===\n";
try {
    SessionManager::start();
    echo "✅ SessionManager started successfully\n";
} catch (Exception $e) {
    echo "❌ SessionManager error: " . $e->getMessage() . "\n";
}

echo "\n=== All Tests Complete ===\n";
echo "If all tests passed, the login should work.\n";
echo "If any test failed, that's the issue to fix.\n";

echo "</pre>";
?>
