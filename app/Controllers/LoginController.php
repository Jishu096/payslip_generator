<?php
require_once __DIR__ . "/../Models/User.php";
require_once __DIR__ . "/../Models/Employee.php";

class LoginController {

    public function login() {
        echo "Login page should be opened from frontend/auth/login.php";
    }

    public function checkLogin() {
        if (empty($_POST['username']) || empty($_POST['password'])) {
            die("Username and password are required.");
        }
        $username = trim($_POST['username']);
        $password = trim($_POST['password']);

        $userModel = new User();
        $user = $userModel->verifyUser($username, $password);

        if ($user) {

            // BASIC SESSION
            $_SESSION['user_id']  = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role']     = $user['role'];

            // ⭐⭐⭐ ADD EMPLOYEE DETAILS ⭐⭐⭐
            if ($user['role'] === 'employee') {

                // Store employee_id directly from users table
                $_SESSION['employee_id'] = $user['employee_id'];

                // Fetch employee full profile
                $empModel = new Employee();
                $emp = $empModel->getEmployeeById($user['employee_id']);

                // Store full name in session
                $_SESSION['employee_name'] = $emp['full_name'];
            }
            
            // Base URL that works on localhost AND your IP
                $baseURL = "/payslip_generator/public/";

            // REDIRECT
            switch ($user['role']) {
                case 'employee':
                    header("Location: {$baseURL}employee/employee_dashboard.php");
                    break;

                case 'accountant':
                    header("Location: {$baseURL}accountant/accountant_dashboard.php");
                    break;

                case 'director':
                    header("Location: {$baseURL}director/director_dashboard.php");
                    break;

                case 'administrator':
                    header("Location: {$baseURL}admin/admin_dashboard.php");
                    break;
            }
            exit;

        } else {
            echo "❌ Invalid username or password.";
        }
    }
}
