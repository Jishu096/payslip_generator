# Multi-Role RBAC Implementation Guide

## Overview
The Payroll System now supports **enterprise-level Role-Based Access Control (RBAC)** allowing users to have multiple roles simultaneously.

## What's New

### Database Structure
- **`roles`** - Table storing role definitions
- **`user_roles`** - Junction table linking users to roles (many-to-many)
- **`permissions`** - Table storing permission definitions
- **`role_permissions`** - Junction table linking roles to permissions

### Key Features
✅ **Multi-Role Support** - Users can have multiple roles (e.g., employee + accountant)
✅ **Fine-Grained Permissions** - Control access at permission level
✅ **Session Management** - All roles loaded in session automatically
✅ **RBAC Helper** - Easy utility functions for role/permission checks
✅ **Backward Compatible** - Existing single-role systems still work

## User Model Methods

### New Methods for Multi-Role Management

```php
// Get all roles for a user
$userModel->getUserRoles($userId);
// Returns: Array of role objects with role_id, role_name, description

// Assign a role to a user
$userModel->assignRoleToUser($userId, 'accountant');
// Returns: true/false

// Remove a role from a user
$userModel->removeRoleFromUser($userId, 'director');
// Returns: true/false

// Check if user has a specific role
$userModel->hasRole($userId, 'employee');
// Returns: true/false

// Check if user has a permission
$userModel->hasPermission($userId, 'manage_payroll');
// Returns: true/false

// Get all permissions for a user
$userModel->getUserPermissions($userId);
// Returns: Array of permission objects
```

## Session Variables (After Login)

```php
// Primary role (for backward compatibility)
$_SESSION['role'];  // 'employee', 'accountant', 'director', or 'administrator'

// All roles assigned to user (NEW)
$_SESSION['all_roles'];  // Array: ['employee', 'accountant']

// Check if user has multiple roles
$_SESSION['has_multiple_roles'];  // true/false

// Employee-specific data
$_SESSION['employee_id'];
$_SESSION['employee_name'];
```

## Using RBAC Helper

### Basic Role Checks
```php
<?php
require_once __DIR__ . '/../../app/Helpers/RBACHelper.php';

// Check if user has a specific role
if (RBACHelper::userHasRole($_SESSION['role'], 'accountant')) {
    // Show accountant features
}

// Check if user has any of specified roles
if (RBACHelper::userHasAnyRole($_SESSION['all_roles'], ['accountant', 'director'])) {
    // Show dashboard
}

// Check if user has all specified roles
if (RBACHelper::userHasAllRoles($_SESSION['all_roles'], ['employee', 'accountant'])) {
    // Show combined dashboard
}
```

### Navigation by Roles
```php
// Get navigation items based on user's roles
$navigation = RBACHelper::getNavigationByRoles($_SESSION['all_roles']);

// Navigation structure:
// [
//     'employee' => [...],
//     'accountant' => [...],
//     'director' => [...],
//     'admin' => [...]
// ]

// Get primary navigation (first appropriate one)
$primaryNav = RBACHelper::getPrimaryNavigation($_SESSION['all_roles']);

// Get role switcher HTML for multi-role users
$switcher = RBACHelper::getRoleSwitcherHTML($_SESSION['all_roles']);
```

### Dashboard Access Control
```php
// Verify user has access to dashboard
if (!RBACHelper::verifyDashboardAccess($_SESSION['all_roles'], 'accountant')) {
    die('Access Denied!');
}
```

## Test User: Saimon Raj Patro

### Credentials
- **Username:** saimon123
- **Email:** saimon123@gmail.com
- **Password:** password123
- **Roles:** employee + accountant
- **Employee ID:** 17
- **Department:** Accounts

### What Saimon Can Do
✅ View own payslips (employee)
✅ View own attendance (employee)
✅ Edit own profile (employee)
✅ Manage payroll (accountant)
✅ Generate payslips (accountant)
✅ View financial reports (accountant)

## How to Assign Multiple Roles

### Using PHP
```php
<?php
require_once __DIR__ . '/app/Config/database.php';
require_once __DIR__ . '/app/Models/User.php';

$userModel = new User();

// Assign employee role
$userModel->assignRoleToUser($userId, 'employee');

// Assign accountant role
$userModel->assignRoleToUser($userId, 'accountant');
?>
```

### Using MySQL Directly
```sql
-- Get user ID
SELECT user_id FROM users WHERE email = 'saimon123@gmail.com';

-- Get role IDs
SELECT role_id FROM roles WHERE role_name IN ('employee', 'accountant');

-- Assign roles
INSERT INTO user_roles (user_id, role_id) VALUES (12, 1);  -- employee
INSERT INTO user_roles (user_id, role_id) VALUES (12, 2);  -- accountant
```

## Creating New Roles

### Add Role
```sql
INSERT INTO roles (role_name, description) 
VALUES ('manager', 'Manager can oversee team and approve requests');
```

### Add Permissions
```sql
INSERT INTO permissions (permission_name, description, resource, action) 
VALUES ('view_team_payslips', 'View team payslips', 'payslips', 'view_team');
```

### Link Role to Permissions
```sql
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.role_id, p.permission_id 
FROM roles r, permissions p
WHERE r.role_name = 'manager' AND p.permission_name = 'view_team_payslips';
```

## Login Flow with Multi-Role

1. User logs in with username/password
2. System authenticates credentials
3. **NEW:** System fetches all roles from `user_roles` table
4. **NEW:** All roles stored in `$_SESSION['all_roles']`
5. Redirect logic:
   - If multiple roles: Go to primary role's dashboard (priority: admin > accountant > director > employee)
   - If single role: Go to that role's dashboard
6. Dashboard shows multi-role indicator if applicable

## Security Considerations

✅ **Role Validation** - Always verify user's roles before showing/doing actions
✅ **Permission Checks** - Use `hasPermission()` for sensitive operations
✅ **Session Security** - Roles stored in secure session, not client-side
✅ **Database Constraints** - Foreign keys enforce referential integrity
✅ **Backward Compatible** - Primary role still maintained for legacy code

## Example: Multi-Role Dashboard Modification

```php
<?php
session_start();

// Require both employee and accountant roles
if (!isset($_SESSION['all_roles']) || 
    !in_array('employee', $_SESSION['all_roles']) || 
    !in_array('accountant', $_SESSION['all_roles'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . '/../../app/Helpers/RBACHelper.php';

// Get navigation for both roles
$employeeNav = RBACHelper::getNavigationByRoles('employee');
$accountantNav = RBACHelper::getNavigationByRoles('accountant');

// Show tabbed interface or combined view
?>
```

## Benefits of This System

1. **Scalability** - Easy to add new roles and permissions
2. **Flexibility** - Users can have any combination of roles
3. **Security** - Fine-grained permission control
4. **Maintainability** - Centralized role/permission management
5. **Enterprise-Ready** - Industry-standard RBAC pattern
6. **Easy Integration** - Simple helper functions for common checks

## Next Steps

1. ✅ Test multi-role login with saimon123
2. ⏳ Update dashboards to show multi-role navigation switcher
3. ⏳ Implement permission-based feature visibility
4. ⏳ Create admin panel for role/permission management
5. ⏳ Add role request workflow for users
