<?php
require_once __DIR__ . "/../Models/Employee.php";
require_once __DIR__ . "/../Models/User.php";
require_once __DIR__ . "/../Helpers/NotificationHelper.php";
require_once __DIR__ . "/../Config/database.php";

class EmployeeController {

    private $notificationHelper;

    public function __construct() {
        $db = new Database();
        $conn = $db->connect();
        $this->notificationHelper = new NotificationHelper($conn);
    }

    public function addEmployee() {
    $employeeModel = new Employee();
    $userModel = new User();

    // 🔍 1) Insert employee (with validation inside model)
        $employee_id = $employeeModel->insertEmployee($_POST);

    // 🔍 Check for missing fields
        if (strpos($employee_id, "MISSING_FIELD_") === 0) {
            $field = str_replace("MISSING_FIELD_", "", $employee_id);
            echo "<script>alert('Missing required field: {$field}'); window.history.back();</script>";
            return;
        }

    // 🔍 2) Duplicate email
        if ($employee_id === "EMAIL_EXISTS") {
            echo "<script>alert('Employee with same EMAIL already exists!'); window.history.back();</script>";
            return;
        }

    // 🔍 2) Duplicate phone
        if ($employee_id === "PHONE_EXISTS") {
            echo "<script>alert('Employee with same PHONE already exists!'); window.history.back();</script>";
            return;
        }

    // 🔍 3) Duplicate name (optional)
        if ($employee_id === "NAME_EXISTS") {
            echo "<script>alert('Employee with same NAME already exists!'); window.history.back();</script>";
            return;
        }

    // 🔍 4) Any other failure
        if (!$employee_id) {
            echo "<script>alert('Error adding employee!'); window.history.back();</script>";
            return;
        }

    // ✔ 5) Auto-create user account for the employee
            $username = $_POST['email'];
            $password = "password123";   // default password
            $role = "employee";

            $userCreated = $userModel->createUserForEmployee($username, $password, $role, $employee_id);

            if ($userCreated) {
                // Send notification if enabled
                if ($this->notificationHelper->isNotificationEnabled('employee_update')) {
                    $employeeName = $_POST['full_name'] ?? 'New Employee';
                    $adminEmail = $_SESSION['email'] ?? 'admin@company.com'; // You can fetch from settings or session
                    $this->notificationHelper->notifyEmployeeUpdate($adminEmail, $employeeName, 'New Employee Added');
                }
                
                header("Location: /payslip_generator/public/admin/employees.php?success=1");
                exit;
            } else {
                header("Location: /payslip_generator/public/admin/employees.php?error=user_create_failed");
                exit;
            }
        }


    public function deleteEmployee() {
        $model = new Employee();
        $id = $_GET['id'];

        if ($model->deleteEmployeeById($id)) {
            header("Location: /payslip_generator/public/admin/employees.php?deleted=1");
            exit;
        }

        header("Location: /payslip_generator/public/admin/employees.php?error=delete_failed");
        exit;
    }

    public function updateEmployee() {
        $model = new Employee();
        $id = $_POST['employee_id'];
        $db = new Database();
        $conn = $db->connect();

        // Check if salary is being changed
        $currentEmployee = $model->getEmployeeById($id);
        $currentSalary = $currentEmployee['basic_salary'];
        $newSalary = $_POST['basic_salary'];

        // Check if user role is being changed
        $userStmt = $conn->prepare("SELECT role FROM users WHERE employee_id = ? LIMIT 1");
        $userStmt->execute([$id]);
        $userRecord = $userStmt->fetch(PDO::FETCH_ASSOC);
        $currentRole = $userRecord['role'] ?? 'employee';
        $newRole = $_POST['user_role'] ?? $currentRole;
        $roleChanged = ($currentRole !== $newRole);

        // If role changed, create approval request
        if ($roleChanged) {
            $stmt = $conn->prepare("INSERT INTO role_change_requests 
                (employee_id, employee_name, old_role, new_role, change_reason, requested_by, requested_by_name) 
                VALUES (:emp_id, :emp_name, :old_role, :new_role, :reason, :req_by, :req_name)");
            
            $stmt->execute([
                ':emp_id' => $id,
                ':emp_name' => $_POST['full_name'],
                ':old_role' => $currentRole,
                ':new_role' => $newRole,
                ':reason' => $_POST['role_change_reason'] ?? 'Position change',
                ':req_by' => $_SESSION['user_id'],
                ':req_name' => $_SESSION['username']
            ]);

            // Don't update role yet, keep old role until approval
            $_POST['user_role'] = $currentRole;
            $roleChangePending = true;
        } else {
            $roleChangePending = false;
        }

        // If salary changed, create approval request instead of updating directly
        if ($currentSalary != $newSalary) {
            $stmt = $conn->prepare("INSERT INTO salary_change_requests 
                (employee_id, employee_name, current_salary, new_salary, change_type, change_reason, requested_by, requested_by_name) 
                VALUES (:emp_id, :emp_name, :current, :new, :type, :reason, :req_by, :req_name)");
            
            $stmt->execute([
                ':emp_id' => $id,
                ':emp_name' => $_POST['full_name'],
                ':current' => $currentSalary,
                ':new' => $newSalary,
                ':type' => $_POST['change_type'] ?? 'Salary Adjustment',
                ':reason' => $_POST['change_reason'] ?? 'Salary update',
                ':req_by' => $_SESSION['user_id'],
                ':req_name' => $_SESSION['username']
            ]);

            // Update employee without salary change
            $postDataWithoutSalary = $_POST;
            $postDataWithoutSalary['basic_salary'] = $currentSalary; // Keep old salary
            $salaryChangePending = true;
        } else {
            $postDataWithoutSalary = $_POST;
            $salaryChangePending = false;
        }

        if ($model->updateEmployee($id, $postDataWithoutSalary)) {
            // Send notification if enabled
            if ($this->notificationHelper->isNotificationEnabled('employee_update')) {
                $employeeName = $_POST['full_name'] ?? 'Employee';
                $adminEmail = $_SESSION['email'] ?? 'admin@company.com';
                $this->notificationHelper->notifyEmployeeUpdate($adminEmail, $employeeName, 'Employee Profile Updated');
            }
            
            // Redirect with appropriate status
            $redirectParams = '?updated=1';
            if ($salaryChangePending) $redirectParams .= '&salary_pending=1';
            if ($roleChangePending) $redirectParams .= '&role_pending=1';
            
            header("Location: /payslip_generator/public/admin/employees.php" . $redirectParams);
            exit;
        }

        header("Location: /payslip_generator/public/admin/employees.php?error=update_failed");
        exit;
    }

}
