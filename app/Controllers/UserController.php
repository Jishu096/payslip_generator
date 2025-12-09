<?php
require_once __DIR__ . "/../Models/User.php";

    class UserController {

        public function createUser() {

            $userModel = new User();

            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            $role     = $_POST['role'] ?? '';
            $employee_id = $_POST['employee_id'] ?? '';
            $email = $_POST['email'] ?? '';

            // Validation
            if (empty($username) || empty($password) || empty($role)) {
                header("Location: /payslip_generator/public/admin/create_user.php?error=missing_fields");
                exit;
            }

            if ($password !== $confirm_password) {
                header("Location: /payslip_generator/public/admin/create_user.php?error=password_mismatch");
                exit;
            }

            if (strlen($username) < 3) {
                header("Location: /payslip_generator/public/admin/create_user.php?error=username_too_short");
                exit;
            }

            if (strlen($password) < 8) {
                header("Location: /payslip_generator/public/admin/create_user.php?error=password_too_short");
                exit;
            }

            // Check password strength
            $strength = $this->checkPasswordStrength($password);
            if ($strength < 40) {
                header("Location: /payslip_generator/public/admin/create_user.php?error=password_weak");
                exit;
            }

            $created = $userModel->createUserManually($username, $password, $role, $employee_id ?: null);

            if ($created) {
                header("Location: /payslip_generator/public/admin/manage_users.php?created=1");
                exit;
            } else {
                header("Location: /payslip_generator/public/admin/create_user.php?error=username_exists");
                exit;
            }
        }

        private function checkPasswordStrength($password) {
            $strength = 0;
            
            if (strlen($password) >= 8) $strength += 20;
            if (preg_match('/[A-Z]/', $password)) $strength += 20;
            if (preg_match('/[a-z]/', $password)) $strength += 20;
            if (preg_match('/\d/', $password)) $strength += 20;
            if (preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) $strength += 20;
            
            return $strength;
        }

        public function toggleUser() {
            $userModel = new User();
            $user_id = $_GET['id'];

            if ($userModel->toggleUserStatus($user_id)) {
                echo "User status updated!";
            } else {
                echo "Error updating user status.";
            }
        }

        public function resetPassword() {
            // Security check
            session_start();
            if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrator') {
                header("Location: /payslip_generator/public/auth/login.php");
                exit;
            }

            $userModel = new User();
            $user_id = $_GET['id'] ?? null;

            if (!$user_id) {
                header("Location: /payslip_generator/public/admin/manage_users.php?error=missing_id");
                exit;
            }

            $newPassword = "password123"; // default reset password
            $hashed = password_hash($newPassword, PASSWORD_DEFAULT);

            if ($userModel->updatePassword($user_id, $hashed)) {
                header("Location: /payslip_generator/public/admin/manage_users.php?success=1");
                exit;
            } else {
                header("Location: /payslip_generator/public/admin/manage_users.php?error=reset_failed");
                exit;
            }
        }

        public function deleteUser() {
            // Security check
            session_start();
            if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrator') {
                header("Location: /payslip_generator/public/auth/login.php");
                exit;
            }

            $userModel = new User();
            $user_id = $_GET['id'] ?? null;

            if (!$user_id) {
                header("Location: /payslip_generator/public/admin/manage_users.php?error=missing_id");
                exit;
            }

            if ($userModel->deleteUserById($user_id)) {
                header("Location: /payslip_generator/public/admin/manage_users.php?deleted=1");
                exit;
            } else {
                header("Location: /payslip_generator/public/admin/manage_users.php?error=delete_failed");
                exit;
            }
        }
}
