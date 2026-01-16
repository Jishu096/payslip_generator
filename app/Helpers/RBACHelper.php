<?php
/**
 * RBAC Helper - Role-Based Access Control utilities
 * Provides functions for checking roles, permissions, and managing multi-role access
 */

class RBACHelper {
    private $conn;

    public function __construct($connection = null) {
        if ($connection) {
            $this->conn = $connection;
        } else {
            require_once __DIR__ . "/../Config/database.php";
            $db = new Database();
            $this->conn = $db->connect();
        }
    }

    /**
     * Check if current user has a specific role
     */
    public static function userHasRole($sessionRole, $requiredRole) {
        if (is_array($sessionRole)) {
            return in_array($requiredRole, $sessionRole);
        }
        return $sessionRole === $requiredRole;
    }

    /**
     * Check if user has any of the specified roles
     */
    public static function userHasAnyRole($sessionRole, $requiredRoles) {
        $roles = is_array($sessionRole) ? $sessionRole : [$sessionRole];
        $requiredRoles = is_array($requiredRoles) ? $requiredRoles : [$requiredRoles];
        
        foreach ($requiredRoles as $role) {
            if (in_array($role, $roles)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if user has all specified roles
     */
    public static function userHasAllRoles($sessionRole, $requiredRoles) {
        $roles = is_array($sessionRole) ? $sessionRole : [$sessionRole];
        $requiredRoles = is_array($requiredRoles) ? $requiredRoles : [$requiredRoles];
        
        foreach ($requiredRoles as $role) {
            if (!in_array($role, $roles)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Get navigation items based on user roles
     */
    public static function getNavigationByRoles($allRoles) {
        $navigation = [];

        // Convert single role to array
        $roles = is_array($allRoles) ? $allRoles : [$allRoles];

        // Employee navigation
        if (in_array('employee', $roles)) {
            $navigation['employee'] = [
                ['label' => 'Dashboard', 'icon' => 'fas fa-home', 'link' => 'dashboard.php'],
                ['label' => 'My Profile', 'icon' => 'fas fa-user', 'link' => 'employee_profile.php'],
                ['label' => 'Payslips', 'icon' => 'fas fa-file-invoice', 'link' => 'view_payslips.php'],
                ['label' => 'Attendance', 'icon' => 'fas fa-calendar-check', 'link' => 'attendance.php'],
                ['label' => 'Edit Profile', 'icon' => 'fas fa-edit', 'link' => 'edit_profile.php'],
            ];
        }

        // Accountant navigation
        if (in_array('accountant', $roles)) {
            $navigation['accountant'] = [
                ['label' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'link' => 'accountant_dashboard.php'],
                ['label' => 'Payroll Management', 'icon' => 'fas fa-wallet', 'link' => 'payroll_management.php'],
                ['label' => 'Generate Payslip', 'icon' => 'fas fa-file-pdf', 'link' => 'generate_payslip.php'],
                ['label' => 'Financial Reports', 'icon' => 'fas fa-chart-bar', 'link' => 'financial_reports.php'],
            ];
        }

        // Director navigation
        if (in_array('director', $roles)) {
            $navigation['director'] = [
                ['label' => 'Dashboard', 'icon' => 'fas fa-chart-line', 'link' => 'director_dashboard.php'],
                ['label' => 'Salary Approvals', 'icon' => 'fas fa-check-circle', 'link' => 'salary_approvals.php'],
                ['label' => 'Role Approvals', 'icon' => 'fas fa-user-check', 'link' => 'role_approvals.php'],
            ];
        }

        // Administrator navigation
        if (in_array('administrator', $roles)) {
            $navigation['admin'] = [
                ['label' => 'Dashboard', 'icon' => 'fas fa-cogs', 'link' => 'admin_dashboard.php'],
                ['label' => 'Employees', 'icon' => 'fas fa-users', 'link' => 'employees.php'],
                ['label' => 'Departments', 'icon' => 'fas fa-building', 'link' => 'departments.php'],
                ['label' => 'Users', 'icon' => 'fas fa-user-shield', 'link' => 'manage_users.php'],
                ['label' => 'Reports', 'icon' => 'fas fa-file-alt', 'link' => 'reports.php'],
                ['label' => 'Settings', 'icon' => 'fas fa-sliders-h', 'link' => 'settings.php'],
            ];
        }

        return $navigation;
    }

    /**
     * Get primary navigation path for current session
     * Returns the main navigation array for the user's primary role
     */
    public static function getPrimaryNavigation($allRoles, $primaryRole = null) {
        $navigation = self::getNavigationByRoles($allRoles);
        
        // Determine which navigation to return first
        if (!$primaryRole) {
            // Default priority: admin > accountant > director > employee
            $priority = ['admin', 'accountant', 'director', 'employee'];
            foreach ($priority as $key) {
                if (isset($navigation[$key])) {
                    return $navigation[$key];
                }
            }
        } else {
            // Get navigation for primary role
            $roleMap = [
                'administrator' => 'admin',
                'employee' => 'employee',
                'accountant' => 'accountant',
                'director' => 'director'
            ];
            
            $navKey = $roleMap[$primaryRole] ?? null;
            if ($navKey && isset($navigation[$navKey])) {
                return $navigation[$navKey];
            }
        }

        return [];
    }

    /**
     * Get role switcher dropdown for multi-role users
     */
    public static function getRoleSwitcherHTML($allRoles) {
        if (count($allRoles) <= 1) {
            return ''; // No need for switcher
        }

        $html = '<div class="role-switcher"><select id="roleSwitch" onchange="switchRole(this.value)">';
        $html .= '<option value="">Switch Role...</option>';
        
        foreach ($allRoles as $role) {
            $displayRole = match($role) {
                'administrator' => 'Administrator',
                'employee' => 'Employee',
                'accountant' => 'Accountant',
                'director' => 'Director',
                default => ucfirst($role)
            };
            $html .= '<option value="' . $role . '">' . $displayRole . '</option>';
        }
        
        $html .= '</select></div>';
        return $html;
    }

    /**
     * Verify that user has access to a specific dashboard
     */
    public static function verifyDashboardAccess($userRoles, $requiredRole) {
        $roles = is_array($userRoles) ? $userRoles : [$userRoles];
        return in_array($requiredRole, $roles);
    }

    /**
     * Get role description
     */
    public static function getRoleDescription($role) {
        return match($role) {
            'super_admin' => 'Super Admin - System owner with full control',
            'administrator' => 'Administrator - System custodian',
            'hr_officer' => 'HR Officer - Attendance and leave management',
            'accountant' => 'Accountant - Payroll and salary management',
            'director' => 'Director - Final approval authority',
            'auditor' => 'Auditor - Read-only compliance access',
            'employee' => 'Employee - Self-service portal',
            default => ucfirst($role)
        };
    }
    
    /**
     * Get role redirect URL after login
     * 
     * @param string $role Role name
     * @return string Redirect URL
     */
    public static function getRoleRedirectURL($role) {
        $baseURL = '/payslip_generator/public/';
        
        return match($role) {
            'super_admin' => $baseURL . 'super_admin/dashboard.php',
            'administrator' => $baseURL . 'admin/admin_dashboard.php',
            'hr_officer' => $baseURL . 'hr_officer/dashboard.php',
            'accountant' => $baseURL . 'accountant/accountant_dashboard.php',
            'director' => $baseURL . 'director/director_dashboard.php',
            'auditor' => $baseURL . 'auditor/dashboard.php',
            'employee' => $baseURL . 'employee/dashboard.php',
            default => $baseURL . 'auth/login.php'
        };
    }
    
    /**
     * Get permission helper instance
     * 
     * @param PDO $conn Database connection
     * @return PermissionHelper
     */
    public static function getPermissionHelper($conn = null) {
        require_once __DIR__ . '/PermissionHelper.php';
        return new PermissionHelper($conn);
    }
}
