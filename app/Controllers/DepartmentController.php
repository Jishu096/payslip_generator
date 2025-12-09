<?php

require_once __DIR__ . '/../Models/Department.php';
require_once __DIR__ . '/../Config/database.php';

class DepartmentController
{
    private $conn;
    private $department;

    public function __construct()
    {
        $db = new Database();
        $this->conn = $db->connect();
        $this->department = new Department($this->conn);
    }

    public function createDepartment()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: ../admin/departments.php");
            exit;
        }

        $department_name = $_POST['department_name'] ?? '';
        $description = $_POST['description'] ?? '';

        if (empty($department_name)) {
            header("Location: ../admin/departments.php?error=name_required");
            exit;
        }

        $result = $this->department->createDepartment([
            'department_name' => $department_name,
            'description' => $description
        ]);

        if ($result['success']) {
            header("Location: ../admin/departments.php?created=1");
        } else {
            header("Location: ../admin/departments.php?error=" . urlencode($result['error']));
        }
        exit;
    }

    public function updateDepartment()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: ../admin/departments.php");
            exit;
        }

        $id = $_GET['id'] ?? null;
        $department_name = $_POST['department_name'] ?? '';
        $description = $_POST['description'] ?? '';

        if (!$id || empty($department_name)) {
            header("Location: ../admin/departments.php?error=missing_data");
            exit;
        }

        $result = $this->department->updateDepartment($id, [
            'department_name' => $department_name,
            'description' => $description
        ]);

        if ($result['success']) {
            header("Location: ../admin/departments.php?updated=1");
        } else {
            header("Location: ../admin/departments.php?error=" . urlencode($result['error']));
        }
        exit;
    }

    public function deleteDepartment()
    {
        $id = $_GET['id'] ?? null;

        if (!$id) {
            header("Location: ../admin/departments.php?error=missing_id");
            exit;
        }

        $result = $this->department->deleteDepartment($id);

        if ($result['success']) {
            header("Location: ../admin/departments.php?deleted=1");
        } else {
            header("Location: ../admin/departments.php?error=" . urlencode($result['error']));
        }
        exit;
    }
}
