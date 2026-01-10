<?php
/**
 * Simple Login API Test
 * Shows actual PHP errors
 */

// Enable error display
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing login_api.php loading...\n\n";

try {
    echo "1. Loading SessionManager... ";
    require_once __DIR__ . '/../../app/Helpers/SessionManager.php';
    echo "OK\n";
    
    echo "2. Loading InputValidator... ";
    require_once __DIR__ . '/../../app/Helpers/InputValidator.php';
    echo "OK\n";
    
    echo "3. Loading Config... ";
    require_once __DIR__ . '/../../app/Helpers/Config.php';
    echo "OK\n";
    
    echo "4. Loading database.php... ";
    require_once __DIR__ . '/../../app/Config/database.php';
    echo "OK\n";
    
    echo "5. Loading User Model... ";
    require_once __DIR__ . '/../../app/Models/User.php';
    echo "OK\n";
    
    echo "6. Loading Employee Model... ";
    require_once __DIR__ . '/../../app/Models/Employee.php';
    echo "OK\n";
    
    echo "7. Loading LoginAttemptHelper... ";
    require_once __DIR__ . '/../../app/Helpers/LoginAttemptHelper.php';
    echo "OK\n";
    
    echo "\n8. Starting SessionManager... ";
    SessionManager::start();
    echo "OK\n";
    
    echo "\n9. Testing database connection... ";
    $conn = getDBConnection();
    echo "OK\n";
    
    echo "\n10. Creating LoginAttemptHelper... ";
    $helper = new LoginAttemptHelper($conn);
    echo "OK\n";
    
    echo "\n✅ ALL TESTS PASSED!\n";
    echo "The login_api.php should work now.\n";
    
} catch (Throwable $e) {
    echo "\n\n❌ ERROR FOUND:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack Trace:\n" . $e->getTraceAsString() . "\n";
}
?>
