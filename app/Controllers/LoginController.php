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

            // Load all roles for multi-role support
            require_once __DIR__ . '/../Config/database.php';
            require_once __DIR__ . '/../Helpers/RBACHelper.php';
            $conn = getDBConnection();
            
            $stmt = $conn->prepare("
                SELECT r.role_name 
                FROM user_roles_new urn
                JOIN roles r ON urn.role_id = r.role_id
                WHERE urn.user_id = ? AND r.is_active = 1
            ");
            $stmt->execute([$user['user_id']]);
            $allRoles = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            $_SESSION['all_roles'] = !empty($allRoles) ? $allRoles : [$user['role']];
            $_SESSION['has_multiple_roles'] = count($allRoles) > 1;

            // ⭐⭐⭐ ADD EMPLOYEE DETAILS ⭐⭐⭐
            if ($user['role'] === 'employee' || in_array('employee', $allRoles)) {

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

            // Determine primary role based on priority hierarchy
            $rolePriority = ['super_admin', 'administrator', 'hr_officer', 'director', 'accountant', 'auditor', 'employee'];
            $primaryRole = $user['role']; // default
            
            foreach ($rolePriority as $role) {
                if (in_array($role, $allRoles)) {
                    $primaryRole = $role;
                    break;
                }
            }
            
            // Store primary role in session
            $_SESSION['primary_role'] = $primaryRole;
            
            // Get redirect URL for primary role
            $redirectUrl = RBACHelper::getRoleRedirectURL($primaryRole);
            
            // REDIRECT with fallback
            if ($redirectUrl) {
                header("Location: {$redirectUrl}");
            } else {
                // Fallback to old logic
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
            }
            exit;

        } else {
            echo "❌ Invalid username or password.";
        }
    }
}
