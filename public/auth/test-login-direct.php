<?php
// Direct test of login_api.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Direct Login API Test</h1>";
echo "<pre>";

// Simulate POST request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['username'] = 'test';
$_POST['password'] = 'test';

echo "Simulating POST request...\n";
echo "Username: test\n";
echo "Password: test\n\n";

echo "Loading login_api.php...\n\n";

// Capture output
ob_start();
try {
    include __DIR__ . '/login_api.php';
    $output = ob_get_clean();
    echo "✅ Login API loaded successfully!\n\n";
    echo "Output:\n";
    echo $output;
} catch (Throwable $e) {
    ob_end_clean();
    echo "❌ ERROR:\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack Trace:\n" . $e->getTraceAsString() . "\n";
}

echo "</pre>";
?>
