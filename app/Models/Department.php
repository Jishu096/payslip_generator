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
                WHERE d.department_id = :id
                GROUP BY d.department_id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createDepartment($data)
    {
        // Check for duplicate department name
        $check_sql = "SELECT department_id FROM {$this->table} 
                      WHERE LOWER(department_name) = LOWER(:name)";
        $check_stmt = $this->conn->prepare($check_sql);
        $check_stmt->execute([':name' => trim($data['department_name'])]);
        
        if ($check_stmt->rowCount() > 0) {
            return ['success' => false, 'error' => 'name_exists'];
        }

        $sql = "INSERT INTO {$this->table} (department_name, description, created_at) 
                VALUES (:name, :description, NOW())";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':name' => trim($data['department_name']),
            ':description' => trim($data['description'] ?? '')
        ]);

        if ($stmt->rowCount() > 0) {
            return ['success' => true, 'id' => $this->conn->lastInsertId()];
        }

        return ['success' => false, 'error' => 'insert_failed'];
    }

    public function updateDepartment($id, $data)
    {
        // Check for duplicate department name (excluding current record)
        $check_sql = "SELECT department_id FROM {$this->table} 
                      WHERE LOWER(department_name) = LOWER(:name) 
                      AND department_id != :id";
        $check_stmt = $this->conn->prepare($check_sql);
        $check_stmt->execute([
            ':name' => trim($data['department_name']),
            ':id' => $id
        ]);
        
        if ($check_stmt->rowCount() > 0) {
            return ['success' => false, 'error' => 'name_exists'];
        }

        $sql = "UPDATE {$this->table} 
                SET department_name = :name, 
                    description = :description, 
                    updated_at = NOW() 
                WHERE department_id = :id";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([
            ':id' => $id,
            ':name' => trim($data['department_name']),
            ':description' => trim($data['description'] ?? '')
        ]);

        return ['success' => $stmt->rowCount() > 0];
    }

    public function deleteDepartment($id)
    {
        // Check if department has employees
        $check_sql = "SELECT COUNT(*) as count FROM employees WHERE department_id = :id";
        $check_stmt = $this->conn->prepare($check_sql);
        $check_stmt->execute([':id' => $id]);
        $result = $check_stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['count'] > 0) {
            return ['success' => false, 'error' => 'has_employees'];
        }

        $sql = "DELETE FROM {$this->table} WHERE department_id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $id]);

        return ['success' => $stmt->rowCount() > 0];
    }

    public function getEmployeeCountByDepartment($dept_id)
    {
        $sql = "SELECT COUNT(*) as count FROM employees WHERE department_id = :id";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute([':id' => $dept_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] ?? 0;
    }
}
