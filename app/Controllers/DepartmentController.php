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
            header("Location: admin/departments.php");
            exit;
        }

        $department_name = $_POST['department_name'] ?? '';

        if (empty($department_name)) {
            header("Location: admin/add_department.php?error=name_required");
            exit;
        }

        $result = $this->department->createDepartment([
            'department_name' => $department_name
        ]);

        if ($result['success']) {
            header("Location: admin/departments.php?created=1");
        } else {
            header("Location: admin/add_department.php?error=" . urlencode($result['error']));
        }
        exit;
    }

    public function updateDepartment()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header("Location: admin/departments.php");
            exit;
        }

        $id = $_GET['id'] ?? null;
        $department_name = $_POST['department_name'] ?? '';

        if (!$id || empty($department_name)) {
            header("Location: admin/departments.php?error=missing_data");
            exit;
        }

        $result = $this->department->updateDepartment($id, [
            'department_name' => $department_name
        ]);

        if ($result['success']) {
            header("Location: admin/departments.php?updated=1");
        } else {
            header("Location: admin/departments.php?error=" . urlencode($result['error']));
        }
        exit;
    }

    public function deleteDepartment()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? null];
        $hasDirectorRole = in_array('director', $userRoles);
        $redirectPage = $hasDirectorRole ? 'director/departments.php' : 'admin/departments.php';
        
        $id = $_GET['id'] ?? null;

        if (!$id) {
            header("Location: {$redirectPage}?error=missing_id");
            exit;
        }

        // Track who deleted it
        $userId = $_SESSION['user_id'] ?? null;
        $result = $this->department->deleteDepartment($id, $userId);

        if ($result['success']) {
            header("Location: {$redirectPage}?deleted=1");
        } else {
            header("Location: {$redirectPage}?error=" . urlencode($result['error']));
        }
        exit;
    }

    public function restoreDepartment()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? null];
        $hasDirectorRole = in_array('director', $userRoles);
        $hasSuperAdminRole = in_array('super_admin', $userRoles);
        $hasAdminRole = in_array('administrator', $userRoles);
        
        // Authorization: Only Director, Administrator, or Super Admin can restore
        if (!$hasDirectorRole && !$hasSuperAdminRole && !$hasAdminRole) {
            $redirectPage = 'admin/departments.php';
            header("Location: {$redirectPage}?error=unauthorized");
            exit;
        }

        $id = $_GET['id'] ?? null;

        if (!$id) {
            // Determine redirect based on role
            $redirectPage = $hasDirectorRole ? 'director/departments.php' : 'admin/departments.php';
            header("Location: {$redirectPage}?error=missing_id");
            exit;
        }

        $result = $this->department->restoreDepartment($id);

        // Determine redirect based on role
        $redirectPage = $hasDirectorRole ? 'director/departments.php' : 'admin/departments.php';
        
        if ($result['success']) {
            header("Location: {$redirectPage}?restored=1");
        } else {
            header("Location: {$redirectPage}?error=" . urlencode($result['error']));
        }
        exit;
    }

    public function permanentlyDeleteDepartment()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? null];
        $hasSuperAdminRole = in_array('super_admin', $userRoles);
        $hasDirectorRole = in_array('director', $userRoles);
        $redirectPage = $hasDirectorRole ? 'director/departments.php' : 'admin/departments.php';
        
        // Authorization: Only Super Admin can permanently delete
        if (!$hasSuperAdminRole) {
            header("Location: {$redirectPage}?error=unauthorized_permanent");
            exit;
        }

        $id = $_GET['id'] ?? null;

        if (!$id) {
            header("Location: {$redirectPage}?error=missing_id");
            exit;
        }

        $result = $this->department->permanentlyDeleteDepartment($id);

        if ($result['success']) {
            header("Location: {$redirectPage}?show_deleted=1&permanently_deleted=1");
        } else {
            header("Location: {$redirectPage}?show_deleted=1&error=" . urlencode($result['error']));
        }
        exit;
    }
}
