<?php
require_once __DIR__ . "/../Config/database.php";

class User {
    private $conn;

    public function __construct() {
        $this->conn = getDBConnection();
    }

    public function verifyUser($username, $password) {
        $sql = "SELECT * FROM users WHERE username = :username AND is_active = 1 LIMIT 1";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(":username", $username);
        $stmt->execute();

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // Verify hashed password
            if (password_verify($password, $user['password_hash'])) {
                return $user;
            }
        }
        return false;
    }

    public function insertUser($username, $password, $role) {

        // Hash password (secure)
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users (username, password_hash, role, is_active)
                VALUES (:username, :password_hash, :role, 1)";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            ':username' => $username,
            ':password_hash' => $hashed,
            ':role' => $role
        ]);
    }

    public function getAllUsers() {
        $sql = "SELECT * FROM users ORDER BY user_id DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function toggleUserStatus($id) {

        // First check current status
        $query = "SELECT is_active FROM users WHERE user_id = :id LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return false; // user not found
        }

        // Flip the status
        $newStatus = ($row['is_active'] == 1) ? 0 : 1;

        // Update
        $update = "UPDATE users SET is_active = :newStatus WHERE user_id = :id";
        $stmt2 = $this->conn->prepare($update);
        $stmt2->bindParam(":newStatus", $newStatus);
        $stmt2->bindParam(":id", $id);

        return $stmt2->execute();
    }

    public function updatePassword($id, $newHash) {
        $sql = "UPDATE users SET password_hash = :password_hash WHERE user_id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':id' => $id,
            ':password_hash' => $newHash
        ]);
    }

    public function createUserForEmployee($username, $password, $role, $employee_id) {

        $hashed = password_hash($password, PASSWORD_DEFAULT);

        $sql = "INSERT INTO users 
                (username, password_hash, role, employee_id, is_active)
                VALUES (:username, :password_hash, :role, :employee_id, 1)";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            ':username' => $username,
            ':password_hash' => $hashed,
            ':role' => $role,
            ':employee_id' => $employee_id
        ]);
    }
    public function createUserManually($username, $password, $role, $employee_id) {

        // 1. OPTIONAL — Check if username already exists
        $check = $this->conn->prepare("SELECT user_id FROM users WHERE username = :u LIMIT 1");
        $check->execute([":u" => $username]);
        if ($check->fetch()) {
            return false;  // username already taken
        }

        // 2. Hash the password
        $hashed = password_hash($password, PASSWORD_DEFAULT);

        // 3. Insert query
        $sql = "INSERT INTO users (username, password_hash, role, employee_id, is_active)
                VALUES (:username, :password_hash, :role, :employee_id, 1)";

        $stmt = $this->conn->prepare($sql);

        return $stmt->execute([
            ":username" => $username,
            ":password_hash" => $hashed,
            ":role" => $role,
            ":employee_id" => $employee_id
        ]);
    }

    public function deleteUserById($id) {
        $sql = "DELETE FROM users WHERE user_id = :id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([':id' => $id]);
    }

    /**
     * Get all roles for a user from the new RBAC system
     */
    public function getUserRoles($userId) {
        $sql = "SELECT r.role_id, r.role_name, r.description 
                FROM user_roles ur
                JOIN roles r ON ur.role_id = r.role_id
                WHERE ur.user_id = :user_id
                ORDER BY r.role_name";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Assign a role to a user
     */
    public function assignRoleToUser($userId, $roleName) {
        // Get role_id from role_name
        $roleQuery = "SELECT role_id FROM roles WHERE role_name = :role_name LIMIT 1";
        $roleStmt = $this->conn->prepare($roleQuery);
        $roleStmt->bindParam(':role_name', $roleName);
        $roleStmt->execute();
        $role = $roleStmt->fetch(PDO::FETCH_ASSOC);

        if (!$role) {
            return false;
        }

        $sql = "INSERT INTO user_roles (user_id, role_id) 
                VALUES (:user_id, :role_id)
                ON DUPLICATE KEY UPDATE assigned_at = NOW()";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':user_id' => $userId,
            ':role_id' => $role['role_id']
        ]);
    }

    /**
     * Remove a role from a user
     */
    public function removeRoleFromUser($userId, $roleName) {
        $roleQuery = "SELECT role_id FROM roles WHERE role_name = :role_name LIMIT 1";
        $roleStmt = $this->conn->prepare($roleQuery);
        $roleStmt->bindParam(':role_name', $roleName);
        $roleStmt->execute();
        $role = $roleStmt->fetch(PDO::FETCH_ASSOC);

        if (!$role) {
            return false;
        }

        $sql = "DELETE FROM user_roles WHERE user_id = :user_id AND role_id = :role_id";
        $stmt = $this->conn->prepare($sql);
        return $stmt->execute([
            ':user_id' => $userId,
            ':role_id' => $role['role_id']
        ]);
    }

    /**
     * Check if user has a specific role
     */
    public function hasRole($userId, $roleName) {
        $sql = "SELECT COUNT(*) as count FROM user_roles ur
                JOIN roles r ON ur.role_id = r.role_id
                WHERE ur.user_id = :user_id AND r.role_name = :role_name";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':role_name' => $roleName
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }

    /**
     * Check if user has a specific permission
     */
    public function hasPermission($userId, $permissionName) {
        $sql = "SELECT COUNT(*) as count FROM user_roles ur
                JOIN role_permissions rp ON ur.role_id = rp.role_id
                JOIN permissions p ON rp.permission_id = p.permission_id
                WHERE ur.user_id = :user_id AND p.permission_name = :permission_name";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':permission_name' => $permissionName
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }

    /**
     * Get all permissions for a user
     */
    public function getUserPermissions($userId) {
        $sql = "SELECT DISTINCT p.permission_id, p.permission_name, p.description, p.resource, p.action
                FROM user_roles ur
                JOIN role_permissions rp ON ur.role_id = rp.role_id
                JOIN permissions p ON rp.permission_id = p.permission_id
                WHERE ur.user_id = :user_id
                ORDER BY p.permission_name";
        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

}
