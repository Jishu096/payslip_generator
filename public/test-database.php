<?php
/**
 * Database Connection Test
 * 
 * This script tests the database connection and configuration
 * 
 * @version 1.0
 * @created January 10, 2026
 */

// Load required files
require_once __DIR__ . '/../app/Helpers/Config.php';
require_once __DIR__ . '/../app/Config/database.php';

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html>
<html>
<head>
    <title>Database Connection Test</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #28a745;
            margin: 10px 0;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #dc3545;
            margin: 10px 0;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #17a2b8;
            margin: 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #667eea;
            color: white;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #5568d3;
        }
    </style>
</head>
<body>
    <div class='container'>
        <h1>🔍 Database Connection Test</h1>";

// Test 1: Configuration Loading
echo "<h2>1. Configuration Test</h2>";
try {
    Config::load();
    $dbConfig = Config::database();
    
    echo "<div class='success'>✅ Configuration loaded successfully</div>";
    echo "<table>";
    echo "<tr><th>Setting</th><th>Value</th></tr>";
    echo "<tr><td>Host</td><td>{$dbConfig['host']}</td></tr>";
    echo "<tr><td>Port</td><td>{$dbConfig['port']}</td></tr>";
    echo "<tr><td>Database</td><td>{$dbConfig['database']}</td></tr>";
    echo "<tr><td>Username</td><td>{$dbConfig['username']}</td></tr>";
    echo "<tr><td>Password</td><td>" . (empty($dbConfig['password']) ? '(empty)' : '***') . "</td></tr>";
    echo "</table>";
} catch (Exception $e) {
    echo "<div class='error'>❌ Configuration Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// Test 2: Database Connection
echo "<h2>2. Database Connection Test</h2>";
try {
    $pdo = getDBConnection();
    echo "<div class='success'>✅ Database connection established successfully</div>";
    
    // Get MySQL version
    $version = $pdo->query('SELECT VERSION()')->fetchColumn();
    echo "<div class='info'>MySQL Version: $version</div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>❌ Database Connection Error: " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<div class='info'><strong>Common Solutions:</strong><br>";
    echo "1. Make sure MySQL is running in XAMPP<br>";
    echo "2. Check database credentials in .env file<br>";
    echo "3. Verify database name exists<br>";
    echo "4. Check if database user has proper permissions</div>";
}

// Test 3: Query Test
echo "<h2>3. Database Query Test</h2>";
try {
    $pdo = getDBConnection();
    
    // Test query - get list of tables
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<div class='success'>✅ Query executed successfully</div>";
    echo "<div class='info'><strong>Tables found:</strong> " . count($tables) . "</div>";
    
    if (count($tables) > 0) {
        echo "<table>";
        echo "<tr><th>#</th><th>Table Name</th></tr>";
        foreach ($tables as $index => $table) {
            echo "<tr><td>" . ($index + 1) . "</td><td>$table</td></tr>";
        }
        echo "</table>";
    }
    
} catch (PDOException $e) {
    echo "<div class='error'>❌ Query Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

// Test 4: User Table Test
echo "<h2>4. Users Table Test</h2>";
try {
    $pdo = getDBConnection();
    
    // Check if users table exists and get count
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $userCount = $stmt->fetchColumn();
    
    echo "<div class='success'>✅ Users table accessible</div>";
    echo "<div class='info'><strong>Total Users:</strong> $userCount</div>";
    
    // Get sample user data (without passwords)
    $stmt = $pdo->query("SELECT user_id, username, role, is_active FROM users LIMIT 5");
    $users = $stmt->fetchAll();
    
    if (count($users) > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Username</th><th>Role</th><th>Status</th></tr>";
        foreach ($users as $user) {
            $status = $user['is_active'] ? '✅ Active' : '❌ Inactive';
            echo "<tr>";
            echo "<td>{$user['user_id']}</td>";
            echo "<td>{$user['username']}</td>";
            echo "<td>{$user['role']}</td>";
            echo "<td>$status</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (PDOException $e) {
    echo "<div class='error'>❌ Users Table Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<h2>✅ Test Complete</h2>";
echo "<p>If all tests passed, your database configuration is working correctly!</p>";
echo "<a href='/payslip_generator/public/auth/login.php' class='btn'>Go to Login Page</a>";
echo "<a href='/payslip_generator/' class='btn' style='background: #6c757d; margin-left: 10px;'>Back to Home</a>";

echo "</div>
</body>
</html>";
?>
