<?php
require_once __DIR__ . "/../Config/database.php";

class User {
    private $conn;

    public function __construct() {
        $db = new Database();
        $this->conn = $db->connect();
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

}
