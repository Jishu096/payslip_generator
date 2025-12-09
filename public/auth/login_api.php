<?php
header('Content-Type: application/json');
session_start();

// Set timezone to Asia/Kolkata
date_default_timezone_set('Asia/Kolkata');

require_once __DIR__ . '/../../app/Config/database.php';
require_once __DIR__ . '/../../app/Models/User.php';
require_once __DIR__ . '/../../app/Helpers/LoginAttemptHelper.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

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
        // Successful login
        $_SESSION['user_id']  = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role']     = $user['role'];

        if ($user['role'] === 'employee') {
            $_SESSION['employee_id'] = $user['employee_id'];
            $empModel = new Employee();
            $emp = $empModel->getEmployeeById($user['employee_id']);
            $_SESSION['employee_name'] = $emp['full_name'];
        }

        $attemptHelper->recordSuccessfulAttempt($username);

        // Determine redirect based on role
        $baseURL = "/payslip_generator/public/";
        $redirect = match($user['role']) {
            'employee' => $baseURL . 'employee/employee_dashboard.php',
            'accountant' => $baseURL . 'accountant/accountant_dashboard.php',
            'director' => $baseURL . 'director/director_dashboard.php',
            'administrator' => $baseURL . 'admin/admin_dashboard.php',
            default => $baseURL . 'admin/admin_dashboard.php'
        };

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
    error_log("Login Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
}
?>
