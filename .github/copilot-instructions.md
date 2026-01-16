# e-HRMS Copilot Instructions

## Project Overview
PHP-based payroll management system with **multi-role RBAC**, automated payslip generation, and attendance tracking. Uses MVC-like architecture with MySQL backend, running on XAMPP (macOS).

## Architecture Patterns

### Directory Structure
```
app/
  Controllers/    # Business logic (9 controllers)
  Models/         # Data access (6 models, instantiate via __construct())
  Helpers/        # Utilities (RBACHelper, EmailHelper, NotificationHelper, AttendanceStatementHelper)
  Config/         # Database connection (unix socket path for XAMPP macOS)
public/
  {role}/         # Role-specific views (admin/, accountant/, director/, employee/, auth/)
  assets/         # Frontend resources
database/         # SQL schemas and sample data
```

### Routing
- **Entry point**: `public/index.php` → `app/Routes/web.php`
- **URL pattern**: `index.php?page=check-login` (query-based routing)
- **Controllers**: Instantiate and call methods directly (e.g., `new LoginController()->checkLogin()`)
- **Views**: Direct PHP files in `public/{role}/` directories (no templating engine)

### Database Connection
```php
require_once __DIR__ . '/../../app/Config/database.php';
$db = new Database();
$conn = $db->connect();  // Returns PDO instance
// OR: $conn = getDBConnection();  // Helper function
```
- **Connection**: Unix socket (`/Applications/XAMPP/xamppfiles/var/mysql/mysql.sock`)
- **Database name**: `payslip_generator`
- **Credentials**: root with no password (XAMPP default)

## RBAC System

### Multi-Role Support
Users can have multiple roles simultaneously (e.g., employee + accountant).

**Session variables**:
```php
$_SESSION['user_id']           // User ID
$_SESSION['username']          // Username
$_SESSION['role']              // Primary role (backward compatible)
$_SESSION['all_roles']         // Array of all roles ['employee', 'accountant']
$_SESSION['has_multiple_roles'] // Boolean flag
$_SESSION['employee_id']       // Employee ID (if role=employee)
$_SESSION['employee_name']     // Full name (if role=employee)
```

**Role checking** (use `RBACHelper`):
```php
require_once __DIR__ . '/../../app/Helpers/RBACHelper.php';

// Check single role
if (RBACHelper::userHasRole($_SESSION['role'], 'accountant')) { ... }

// Check any of multiple roles
if (RBACHelper::userHasAnyRole($_SESSION['all_roles'], ['accountant', 'director'])) { ... }

// Get role-specific navigation
$navigation = RBACHelper::getNavigationByRoles($_SESSION['all_roles']);
```

**Roles hierarchy**:
1. **administrator**: Full system access (employees, departments, users, settings)
2. **accountant**: Payroll, payslip generation, financial reports
3. **director**: Salary/role change approvals
4. **employee**: View payslips, attendance, profile management

### Page Authentication
Every protected page starts with:
```php
<?php
session_start();

// Role check (adapt to specific role)
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'accountant') {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'User';
```

For multi-role pages:
```php
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role']];
$hasAccountantRole = in_array('accountant', $userRoles);
if (!$hasAccountantRole) { /* redirect */ }
```

## View Components

### Shared Includes Pattern
Each role has a `includes/` directory with reusable components:
```php
<?php include 'includes/admin_styles.php'; ?>     // <head> styles/links
<?php include 'includes/admin_navbar.php'; ?>     // Top navigation bar
<?php include 'includes/admin_sidebar.php'; ?>    // Sidebar menu
<?php include 'includes/admin_scripts.php'; ?>    // Footer scripts
```

**Important**: Adjust paths based on file depth (e.g., `../includes/`, `./includes/`)

### URL Base Path
Always use absolute paths with base:
```php
$baseURL = "/payslip_generator/public/";
// Links: {$baseURL}admin/dashboard.php
// Forms: action="{$baseURL}index.php?page=create-user"
```

## Models & Data Access

### Model Instantiation
Models instantiate their own database connection:
```php
require_once __DIR__ . '/../../app/Models/Employee.php';
$empModel = new Employee();  // Connection created in __construct()
$employees = $empModel->getAllEmployees();
```

### Key Models
- **User**: Authentication, role management (`getUserRoles()`, `assignRoleToUser()`, `hasPermission()`)
- **Employee**: Employee data (`getAllEmployees()`, `getEmployeeById()`, `insertEmployee()`)
- **Payroll**: Salary records (`createPayroll()`, calculation fields)
- **Attendance**: Time tracking (`getAttendanceByEmployee()`, status: Present/Absent/Leave)
- **Department**: Organizational units
- **Payslip**: Generated payslip metadata

## Design System

### CSS Framework
**100% custom CSS** (no Bootstrap/Tailwind in most pages). Use CSS variables:
```css
--bg: #f8fafc;
--card: #ffffff;
--accent: #667eea;        /* Primary purple */
--accent-2: #764ba2;      /* Darker purple */
--text: #1e293b;
--muted: #64748b;
--border: #e2e8f0;
--success: #10b981;
```

### Component Patterns
- **Gradients**: `linear-gradient(135deg, #667eea, #764ba2)` for buttons/headers
- **Cards**: White background, 20px border-radius, `box-shadow: 0 10px 40px rgba(0,0,0,0.15)`
- **Glassmorphism**: `backdrop-filter: blur(15px)` on overlays
- **Buttons**: Gradient background, hover `translateY(-3px)`, 12px border-radius
- **Inputs**: 13px padding, purple focus border with glow

### Animations
```css
@keyframes slideUp { from { opacity: 0; transform: translateY(30px); } }
@keyframes fadeIn { from { opacity: 0; } }
```

### External Libraries
- **Icons**: Font Awesome 6.4
- **Fonts**: Roboto (Google Fonts)
- **Particles**: tsParticles (login page background)

## Key Business Logic

### Payslip Generation
1. Accountant selects employee, month, year
2. Form pre-fills `basic_salary` from employee record
3. Enter allowances (HRA, DA, TA, DA on TA, Bonus)
4. Enter deductions (Tax, PF, NPS, Professional Tax, Other)
5. Real-time calculation: `gross = basic + allowances`, `net = gross - deductions`
6. Submit creates records in `payroll` + `payslips` tables (transaction-safe)

**Standard rates** (edit in `generate_payslip.php`):
```php
$standardRates = [
    'hra_percent' => 20,      // HRA = 20% of Basic
    'da_percent' => 58,       // DA = 58% of Basic
    'epf_percent' => 12,      // EPF = 12% of Basic
    'nps_percent' => 10,      // NPS = 10% of Basic
    'professional_tax' => 200 // Flat amount
];
```

### Approval Workflows
- **Salary changes**: Director approves via `SalaryApprovalController`
- **Role changes**: Director approves via `RoleChangeApprovalController`
- Controllers handle both approve/reject actions based on POST data

### Attendance System
- **Models**: `Attendance`, `AttendanceStatementHelper`
- **Status values**: 'Present', 'Absent', 'Leave'
- **Integration**: Biometric sync support (see `docs/BIOMETRIC_INTEGRATION.md`)

## Development Workflow

### Local Environment
- **Server**: XAMPP on macOS
- **URL**: `http://localhost/payslip_generator/public/`
- **Database**: Access via phpMyAdmin or MySQL CLI

### Testing
- **Sample data**: `database/sample_data.sql`
- **Test script**: `test-rbac.sh` (RBAC functionality)
- **Test employee SQL**: `create_test_employee.sql`

### Adding New Features
1. **Model**: Create in `app/Models/` with database connection in `__construct()`
2. **Controller**: Create in `app/Controllers/`, add route case in `app/Routes/web.php`
3. **View**: Create in `public/{role}/`, include shared components from `includes/`
4. **RBAC**: Use `RBACHelper` for role checks, update navigation in helper if needed

## Common Pitfalls

1. **Path issues**: Always use `__DIR__` for requires, check depth for includes
2. **Session**: Call `session_start()` at top of every page (before any output)
3. **RBAC**: Don't check `$_SESSION['role']` only—check `$_SESSION['all_roles']` for multi-role
4. **Database**: Use prepared statements (`$stmt->prepare()`) to prevent SQL injection
5. **Transactions**: Wrap multi-table inserts in `BEGIN/COMMIT/ROLLBACK` for data integrity
6. **Redirects**: Always `exit` after `header("Location: ...")` to prevent code execution
7. **Base URL**: Use `$baseURL` variable for consistent paths across environments

## Documentation
- **Comprehensive guides**: `docs/guides/` (RBAC, payslip generation, accountant features)
- **Implementation details**: `docs/implementation/`
- **Quick reference**: `docs/quick-reference/`
- **Project report**: `PROJECT_REPORT.md` (803 lines, complete system overview)

## Key Files to Reference
- [app/Helpers/RBACHelper.php](app/Helpers/RBACHelper.php): Role checking utilities
- [app/Routes/web.php](app/Routes/web.php): Routing logic
- [app/Controllers/LoginController.php](app/Controllers/LoginController.php): Auth flow
- [public/accountant/generate_payslip.php](public/accountant/generate_payslip.php): Payslip generation example
- [docs/guides/MULTI_ROLE_RBAC_GUIDE.md](docs/guides/MULTI_ROLE_RBAC_GUIDE.md): Complete RBAC reference
