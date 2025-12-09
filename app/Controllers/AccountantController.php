<?php
session_start();
echo "AUTH CONTROLLER LOADED!";
exit;
require_once __DIR__ . "/../models/User.php";
require_once __DIR__ . "/../models/Employee.php";

class AuthController {

    public function login() {

        $username = $_POST['username'];
        $password = $_POST['password'];

        $userModel = new User();
        $user = $userModel->verifyUser($username, $password);

        // User not found
        if (!$user) {
            echo "<script>alert('Invalid username!'); window.history.back();</script>";
            return;
        }

        // Incorrect password
        if (!password_verify($password, $user['password_hash'])) {
            echo "<script>alert('Incorrect password!'); window.history.back();</script>";
            return;
        }

        // Inactive user
        if ($user['is_active'] != 1) {
            echo "<script>alert('Your account is deactivated!'); window.history.back();</script>";
            return;
        }

        // Save common session data
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];

        // SPECIAL HANDLING FOR EMPLOYEE LOGIN
        if ($user['role'] === 'employee') {

            // employee_id from users table
            $_SESSION['employee_id'] = $user['employee_id'];

            // Fetch employee details
            $empModel = new Employee();
            $emp = $empModel->getEmployeeById($user['employee_id']);

            // Store employee name
            $_SESSION['employee_name'] = $emp['full_name'];
        }

        // Redirect based on role
        switch ($user['role']) {

            case "administrator":
                header("Location: http://localhost/payslip_generator/frontend/views/admin_dashboard.php");
                exit;

            case "employee":
                header("Location: http://localhost/payslip_generator/frontend/views/employee_dashboard.php");
                exit;

            case "accountant":
                header("Location: http://localhost/payslip_generator/frontend/views/accounts_dashboard.php");
                exit;

            case "director":
                header("Location: http://localhost/payslip_generator/frontend/views/director_dashboard.php");
                exit;

            default:
                echo "<script>alert('Unknown user role!');</script>";
        }
    }
}
