<?php

class Department
{
    private $conn;
    private $table = 'departments';

    public function __construct($db)
    {
        $this->conn = $db;
    }

    public function getAllDepartments()
    {
        $sql = "SELECT d.*, COUNT(e.employee_id) as employee_count 
                FROM {$this->table} d 
                LEFT JOIN employees e ON d.department_id = e.department_id 
                WHERE d.deleted_at IS NULL
                GROUP BY d.department_id 
                ORDER BY d.department_name ASC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDepartmentById($id)
    {
        $sql = "SELECT d.*, COUNT(e.employee_id) as employee_count 
                FROM {$this->table} d 
                LEFT JOIN employees e ON d.department_id = e.department_id 
                WHERE d.department_id = :id AND d.deleted_at IS NULL
                GROUP BY d.department_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createDepartment($data)
    {
        try {
            // Check for duplicate department name
            $check_sql = "SELECT department_id FROM {$this->table} 
                          WHERE LOWER(department_name) = LOWER(:name) AND deleted_at IS NULL";
            $check_stmt = $this->conn->prepare($check_sql);
            $check_stmt->execute([':name' => trim($data['department_name'])]);
            
            if ($check_stmt->rowCount() > 0) {
                return ['success' => false, 'error' => 'name_exists'];
            }

            $sql = "INSERT INTO {$this->table} (department_name) 
                    VALUES (:name)";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':name' => trim($data['department_name'])
            ]);

            if ($stmt->rowCount() > 0) {
                return ['success' => true, 'id' => $this->conn->lastInsertId()];
            }

            return ['success' => false, 'error' => 'insert_failed'];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function updateDepartment($id, $data)
    {
        try {
            // Check for duplicate department name (excluding current record)
            $check_sql = "SELECT department_id FROM {$this->table} 
                          WHERE LOWER(department_name) = LOWER(:name) 
                          AND department_id != :id
                          AND deleted_at IS NULL";
            $check_stmt = $this->conn->prepare($check_sql);
            $check_stmt->execute([
                ':name' => trim($data['department_name']),
                ':id' => $id
            ]);
            
            if ($check_stmt->rowCount() > 0) {
                return ['success' => false, 'error' => 'name_exists'];
            }

            $sql = "UPDATE {$this->table} 
                    SET department_name = :name 
                    WHERE department_id = :id AND deleted_at IS NULL";
            
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':id' => $id,
                ':name' => trim($data['department_name'])
            ]);

            return ['success' => $stmt->rowCount() > 0];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function deleteDepartment($id, $userId = null)
    {
        try {
            // Check if department has employees
            $check_sql = "SELECT COUNT(*) as count FROM employees WHERE department_id = :id";
            $check_stmt = $this->conn->prepare($check_sql);
            $check_stmt->execute([':id' => $id]);
            $result = $check_stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['count'] > 0) {
                return ['success' => false, 'error' => 'has_employees'];
            }

            // Soft delete: set deleted_at timestamp and deleted_by user
            $sql = "UPDATE {$this->table} 
                    SET deleted_at = NOW(), deleted_by = :user_id 
                    WHERE department_id = :id AND deleted_at IS NULL";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([
                ':id' => $id,
                ':user_id' => $userId
            ]);

            return ['success' => $stmt->rowCount() > 0];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function getEmployeeCountByDepartment($dept_id)
    {
        $sql = "SELECT COUNT(*) as count FROM employees WHERE department_id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $dept_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }

    public function getDeletedDepartments()
    {
        $sql = "SELECT d.*, 
                COUNT(e.employee_id) as employee_count,
                u.username as deleted_by_username
                FROM {$this->table} d 
                LEFT JOIN employees e ON d.department_id = e.department_id 
                LEFT JOIN users u ON d.deleted_by = u.user_id
                WHERE d.deleted_at IS NOT NULL
                GROUP BY d.department_id 
                ORDER BY d.deleted_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function restoreDepartment($id)
    {
        try {
            $sql = "UPDATE {$this->table} 
                    SET deleted_at = NULL, deleted_by = NULL 
                    WHERE department_id = :id AND deleted_at IS NOT NULL";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':id' => $id]);

            return ['success' => $stmt->rowCount() > 0];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function permanentlyDeleteDepartment($id)
    {
        try {
            // Check if department has employees
            $check_sql = "SELECT COUNT(*) as count FROM employees WHERE department_id = :id";
            $check_stmt = $this->conn->prepare($check_sql);
            $check_stmt->execute([':id' => $id]);
            $result = $check_stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['count'] > 0) {
                return ['success' => false, 'error' => 'has_employees'];
            }

            // Permanent delete: completely remove from database
            $sql = "DELETE FROM {$this->table} WHERE department_id = :id";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute([':id' => $id]);

            return ['success' => $stmt->rowCount() > 0];
        } catch (PDOException $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
