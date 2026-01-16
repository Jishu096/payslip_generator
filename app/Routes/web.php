<?php

$request = $_GET['page'] ?? 'home';

switch ($request) {

    case 'check-login':
        require_once __DIR__ . "/../Controllers/LoginController.php";
        $controller = new LoginController();
        $controller->checkLogin();
        break;

    case 'login':
        echo "Login route working!";
        break;

    case 'add-employee':
        require_once __DIR__ . "/../Controllers/EmployeeController.php";
        $controller = new EmployeeController();
        $controller->addEmployee();
        break;

    case 'create-user':
        require_once __DIR__ . "/../Controllers/UserController.php";
        $controller = new UserController();
        $controller->createUser();
        break;

    case 'delete-employee':
        require_once __DIR__ . "/../Controllers/EmployeeController.php";
        $controller = new EmployeeController();
        $controller->deleteEmployee();
        break;

    case 'update-employee':
        require_once __DIR__ . "/../Controllers/EmployeeController.php";
        $controller = new EmployeeController();
        $controller->updateEmployee();
        break;
    case 'toggle-user':
        require_once __DIR__ . "/../Controllers/UserController.php";
        $controller = new UserController();
        $controller->toggleUser();
        break;

    case 'reset-password':
        require_once __DIR__ . "/../Controllers/UserController.php";
        $controller = new UserController();
        $controller->resetPassword();
        break;

    case 'delete-user':
        require_once __DIR__ . "/../Controllers/UserController.php";
        $controller = new UserController();
        $controller->deleteUser();
        break;

    case 'request-profile-update':
        require_once __DIR__ . "/../Controllers/ProfileController.php";
        $controller = new ProfileController();
        $controller->requestUpdate();
        break;

    case 'create-department':
        require_once __DIR__ . "/../Controllers/DepartmentController.php";
        $controller = new DepartmentController();
        $controller->createDepartment();
        break;

    case 'update-department':
        require_once __DIR__ . "/../Controllers/DepartmentController.php";
        $controller = new DepartmentController();
        $controller->updateDepartment();
        break;

    case 'delete-department':
        require_once __DIR__ . "/../Controllers/DepartmentController.php";
        $controller = new DepartmentController();
        $controller->deleteDepartment();
        break;

    case 'approve-salary-change':
    case 'reject-salary-change':
        require_once __DIR__ . "/../Controllers/SalaryApprovalController.php";
        // Controller handles approval/rejection based on POST action
        break;

    case 'approve-role-change':
    case 'reject-role-change':
        require_once __DIR__ . "/../Controllers/RoleChangeApprovalController.php";
        // Controller handles approval/rejection based on POST action
        break;

    case 'generate-attendance-statement':
        require_once __DIR__ . "/../Controllers/AttendanceStatementController.php";
        $controller = new AttendanceStatementController();
        $controller->generateStatement();
        break;

    case 'delete-audit-log':
        require_once __DIR__ . "/../Controllers/AuditLogController.php";
        $controller = new AuditLogController();
        $controller->deleteLog();
        break;

    case 'clear-audit-logs':
        require_once __DIR__ . "/../Controllers/AuditLogController.php";
        $controller = new AuditLogController();
        $controller->clearLogs();
        break;

    case 'logout':
        session_start();
        session_destroy();
        header("Location: /payslip_generator/public/auth/login.php");
        exit;
        break;

    default:
        echo "Backend Router Working Successfully!";
        break;
}
