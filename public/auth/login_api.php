<?php
header('Content-Type: application/json');

// Load security helpers
require_once __DIR__ . '/../../app/Helpers/Config.php';
require_once __DIR__ . '/../../app/Helpers/SessionManager.php';
require_once __DIR__ . '/../../app/Helpers/InputValidator.php';
require_once __DIR__ . '/../../app/Config/database.php';
require_once __DIR__ . '/../../app/Models/User.php';
require_once __DIR__ . '/../../app/Models/Employee.php';
require_once __DIR__ . '/../../app/Helpers/LoginAttemptHelper.php';

// Start secure session
SessionManager::start();

// Set timezone to Asia/Kolkata
date_default_timezone_set('Asia/Kolkata');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Sanitize and validate input
$username = InputValidator::sanitizeString($_POST['username'] ?? '');
$password = $_POST['password'] ?? ''; // Don't sanitize passwords

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Username and password are required']);
    exit;
}

try {
    $conn = getDBConnection();
    $attemptHelper = new LoginAttemptHelper($conn);

    // Check if account is locked
    if ($attemptHelper->isAccountLocked($username)) {
        $expiryTime = $attemptHelper->getLockoutExpiryTime($username);
        echo json_encode([
            'success' => false,
            'locked' => true,
            'message' => "Account locked due to multiple failed login attempts. Try again after {$expiryTime}.",
            'forgotLink' => true
        ]);
        exit;
    }

    // Attempt login
    $userModel = new User();
    $user = $userModel->verifyUser($username, $password);

    if ($user) {
        // Regenerate session ID to prevent session fixation
        SessionManager::regenerate();
        
        // Successful login
        SessionManager::set('user_id', $user['user_id']);
        SessionManager::set('username', $user['username']);
        SessionManager::set('role', $user['role']); // Keep primary role for backward compatibility

        // Get all roles from RBAC system
        $userRoles = $userModel->getUserRoles($user['user_id']);
        SessionManager::set('all_roles', array_column($userRoles, 'role_name')); // Array of all role names
        SessionManager::set('has_multiple_roles', count(SessionManager::get('all_roles')) > 1);

        if ($user['role'] === 'employee' || in_array('employee', SessionManager::get('all_roles'))) {
            SessionManager::set('employee_id', $user['employee_id']);
            $empModel = new Employee();
            $emp = $empModel->getEmployeeById($user['employee_id']);
            SessionManager::set('employee_name', $emp['full_name']);
        }

        $attemptHelper->recordSuccessfulAttempt($username);

        // Determine primary redirect based on primary role or first role
        $baseURL = "/payslip_generator/public/";
        $primaryRole = $user['role'];
        
        // If has multiple roles, show role selector page
        if (SessionManager::get('has_multiple_roles')) {
            $redirect = $baseURL . 'auth/role_selector.php';
        } else {
            $redirect = match($primaryRole) {
                'employee' => $baseURL . 'employee/dashboard.php',
                'accountant' => $baseURL . 'accountant/accountant_dashboard.php',
                'director' => $baseURL . 'director/director_dashboard.php',
                'administrator' => $baseURL . 'admin/admin_dashboard.php',
                default => $baseURL . 'admin/admin_dashboard.php'
            };
        }

        echo json_encode(['success' => true, 'redirect' => $redirect]);
    } else {
        // Failed login - record attempt
        $attemptHelper->recordFailedAttempt($username);
        $remaining = $attemptHelper->getRemainingAttempts($username);

        if ($remaining > 0) {
            echo json_encode([
                'success' => false,
                'message' => "Invalid credentials. {$remaining} attempt(s) remaining."
            ]);
        } else {
            $expiryTime = $attemptHelper->getLockoutExpiryTime($username);
            echo json_encode([
                'success' => false,
                'locked' => true,
                'message' => "Account locked due to multiple failed attempts. Try again after {$expiryTime}.",
                'forgotLink' => true
            ]);
        }
    }
} catch (Exception $e) {
    // Log error
    error_log("Login Error: " . $e->getMessage());
    
    // In development, show detailed error
    if (Config::isDevelopment()) {
        echo json_encode([
            'success' => false, 
            'message' => 'Server error: ' . $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
    }
}
// No closing PHP tag to prevent accidental output
