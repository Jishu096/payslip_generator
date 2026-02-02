<?php
session_start();

// Check if user is Super Admin
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . '/../../app/Config/database.php';
require_once __DIR__ . '/../../app/Helpers/PermissionHelper.php';

$conn = getDBConnection();
$perm = new PermissionHelper($conn, $_SESSION['user_id']);

// Verify super admin permission
if (!$perm->hasPermission('role.assign') && !$perm->hasPermission('role.view')) {
    header("Location: ../auth/login.php?error=unauthorized");
    exit;
}

$username = $_SESSION['username'] ?? 'Super Admin';

// Fetch all roles with user counts
$rolesStmt = $conn->query("
    SELECT 
        r.role_id,
        r.role_name,
        r.display_name,
        r.description,
        r.is_active,
        COUNT(DISTINCT urn.user_id) as user_count
    FROM roles r
    LEFT JOIN user_roles_new urn ON r.role_id = urn.role_id
    GROUP BY r.role_id
    ORDER BY r.display_name
");
$roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch permissions grouped by resource (category)
$permsStmt = $conn->query("
    SELECT permission_id, permission_name, description, resource, action
    FROM permissions
    ORDER BY resource, permission_name
");
$permissions = $permsStmt->fetchAll(PDO::FETCH_ASSOC);

// Group permissions by resource (as category)
$permissionsByCategory = [];
foreach ($permissions as $perm) {
    $category = ucfirst($perm['resource'] ?? 'General');
    if (!isset($permissionsByCategory[$category])) {
        $permissionsByCategory[$category] = [];
    }
    // Add display_name for template compatibility
    $perm['display_name'] = ucwords(str_replace(['_', '.'], ' ', $perm['permission_name']));
    $perm['category'] = $category;
    $permissionsByCategory[$category][] = $perm;
}

// Fetch role-permission mappings
$rolePerm = $conn->query("SELECT role_id, permission_id FROM role_permissions")->fetchAll(PDO::FETCH_ASSOC);
$rolePermissions = [];
foreach ($rolePerm as $rp) {
    if (!isset($rolePermissions[$rp['role_id']])) {
        $rolePermissions[$rp['role_id']] = [];
    }
    $rolePermissions[$rp['role_id']][] = $rp['permission_id'];
}

// Statistics
$totalRoles = count($roles);
$activeRoles = count(array_filter($roles, fn($r) => $r['is_active']));
$totalPermissions = count($permissions);
$totalAssignments = array_sum(array_column($roles, 'user_count'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Management - Super Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/superadmin_styles.php'; ?>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        /* Page Header */
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 35px 40px;
            border-radius: 24px;
            margin-bottom: 30px;
            color: white;
            box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }

        .page-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin: 0 0 8px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .page-header p {
            opacity: 0.9;
            font-size: 15px;
            margin: 0;
        }

        .header-actions {
            display: flex;
            gap: 12px;
            position: relative;
            z-index: 10;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            border: none;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-white {
            background: white;
            color: #667eea;
        }

        .btn-white:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
        }

        /* Stats Cards */
        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 16px;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }

        .stat-icon.purple {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(124, 58, 237, 0.15));
            color: #8b5cf6;
        }

        .stat-icon.green {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.15));
            color: #10b981;
        }

        .stat-icon.blue {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(37, 99, 235, 0.15));
            color: #3b82f6;
        }

        .stat-icon.orange {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(217, 119, 6, 0.15));
            color: #f59e0b;
        }

        .stat-info h3 {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
            margin: 0 0 4px 0;
        }

        .stat-info p {
            font-size: 14px;
            color: #64748b;
            margin: 0;
        }

        /* Roles Grid */
        .roles-section {
            margin-bottom: 30px;
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #667eea;
        }

        .roles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 24px;
        }

        .role-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }

        .role-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.12);
            border-color: rgba(102, 126, 234, 0.2);
        }

        .role-card-header {
            padding: 24px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .role-icon {
            width: 52px;
            height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-right: 16px;
        }

        .role-icon.super_admin {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
        }

        .role-icon.administrator {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .role-icon.accountant {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .role-icon.director {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
        }

        .role-icon.employee {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
        }

        .role-icon.hr_officer {
            background: linear-gradient(135deg, #ec4899, #db2777);
            color: white;
        }

        .role-icon.auditor {
            background: linear-gradient(135deg, #14b8a6, #0d9488);
            color: white;
        }

        .role-info h3 {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
            margin: 0 0 4px 0;
        }

        .role-info p {
            font-size: 13px;
            color: #64748b;
            margin: 0;
        }

        .role-header-left {
            display: flex;
            align-items: center;
        }

        .role-actions {
            display: flex;
            gap: 8px;
        }

        .role-btn {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
        }

        .role-btn.edit {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .role-btn.edit:hover {
            background: #3b82f6;
            color: white;
        }

        .role-btn.permissions {
            background: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
        }

        .role-btn.permissions:hover {
            background: #8b5cf6;
            color: white;
        }

        .role-card-body {
            padding: 24px;
        }

        .role-stat-row {
            display: flex;
            gap: 20px;
            margin-bottom: 16px;
        }

        .role-stat {
            flex: 1;
            text-align: center;
            padding: 14px;
            background: #f8fafc;
            border-radius: 12px;
        }

        .role-stat h4 {
            font-size: 22px;
            font-weight: 700;
            color: #1e293b;
            margin: 0 0 4px 0;
        }

        .role-stat p {
            font-size: 12px;
            color: #64748b;
            margin: 0;
        }

        .role-description {
            font-size: 14px;
            color: #64748b;
            line-height: 1.6;
        }

        .role-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            margin-top: 12px;
        }

        .role-status.active {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .role-status.inactive {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .role-status i {
            font-size: 6px;
        }

        /* Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .modal-overlay.show {
            display: flex;
        }

        .modal {
            background: white;
            border-radius: 24px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlide 0.3s ease-out;
        }

        .modal.wide {
            max-width: 800px;
        }

        @keyframes modalSlide {
            from {
                opacity: 0;
                transform: translateY(-30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }

        .modal-header {
            padding: 25px 30px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: white;
            z-index: 10;
        }

        .modal-header h3 {
            font-size: 20px;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-header h3 i {
            color: #667eea;
        }

        .modal-close {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: none;
            background: #f1f5f9;
            color: #64748b;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 18px;
        }

        .modal-close:hover {
            background: #fee2e2;
            color: #ef4444;
        }

        .modal-body {
            padding: 30px;
        }

        .form-group {
            margin-bottom: 24px;
        }

        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #334155;
            margin-bottom: 10px;
        }

        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-group input:focus,
        .form-group textarea:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            position: sticky;
            bottom: 0;
            background: white;
        }

        .btn-cancel {
            background: #f1f5f9;
            color: #64748b;
        }

        .btn-cancel:hover {
            background: #e2e8f0;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }

        /* Permissions Grid */
        .permissions-category {
            margin-bottom: 24px;
        }

        .category-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f1f5f9;
        }

        .category-header h4 {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin: 0;
            text-transform: capitalize;
        }

        .category-header .count {
            background: #e2e8f0;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
        }

        .permissions-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .permission-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px;
            background: #f8fafc;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .permission-item:hover {
            background: #f1f5f9;
        }

        .permission-item.selected {
            background: rgba(102, 126, 234, 0.1);
            border-color: #667eea;
        }

        .permission-item input {
            width: 18px;
            height: 18px;
            accent-color: #667eea;
        }

        .permission-info h5 {
            font-size: 13px;
            font-weight: 600;
            color: #1e293b;
            margin: 0 0 2px 0;
        }

        .permission-info p {
            font-size: 11px;
            color: #94a3b8;
            margin: 0;
        }

        /* Select All */
        .select-all-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            background: #f8fafc;
            border-radius: 12px;
            margin-bottom: 24px;
        }

        .select-all-row label {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            font-weight: 600;
            color: #334155;
            cursor: pointer;
        }

        .selected-count {
            font-size: 13px;
            color: #64748b;
        }

        /* Alert */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: alertSlide 0.3s ease-out;
        }

        @keyframes alertSlide {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #059669;
        }

        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #dc2626;
        }

        /* Responsive */
        @media (max-width: 1200px) {
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-row {
                grid-template-columns: 1fr;
            }
            .page-header {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            .roles-grid {
                grid-template-columns: 1fr;
            }
            .permissions-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/superadmin_navbar.php'; ?>
    
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div>
                <h1><i class="fas fa-user-shield"></i> Role Management</h1>
                <p>Configure roles and assign permissions to control system access</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-white" onclick="openCreateRoleModal()">
                    <i class="fas fa-plus"></i> Create Role
                </button>
            </div>
        </div>

        <!-- Alert Box -->
        <div id="alertBox" class="alert" style="display: none;"></div>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-user-tag"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $totalRoles; ?></h3>
                    <p>Total Roles</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $activeRoles; ?></h3>
                    <p>Active Roles</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-key"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $totalPermissions; ?></h3>
                    <p>Permissions</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $totalAssignments; ?></h3>
                    <p>Role Assignments</p>
                </div>
            </div>
        </div>

        <!-- Roles Section -->
        <div class="roles-section">
            <h2 class="section-title"><i class="fas fa-crown"></i> System Roles</h2>
            <div class="roles-grid">
                <?php foreach ($roles as $role): ?>
                <div class="role-card">
                    <div class="role-card-header">
                        <div class="role-header-left">
                            <div class="role-icon <?php echo htmlspecialchars($role['role_name']); ?>">
                                <i class="fas fa-<?php 
                                    $iconMap = [
                                        'super_admin' => 'crown',
                                        'administrator' => 'user-cog',
                                        'accountant' => 'calculator',
                                        'director' => 'briefcase',
                                        'employee' => 'user',
                                        'hr_officer' => 'users',
                                        'auditor' => 'search'
                                    ];
                                    echo $iconMap[$role['role_name']] ?? 'user-tag';
                                ?>"></i>
                            </div>
                            <div class="role-info">
                                <h3><?php echo htmlspecialchars($role['display_name']); ?></h3>
                                <p><?php echo htmlspecialchars($role['role_name']); ?></p>
                            </div>
                        </div>
                        <div class="role-actions">
                            <button class="role-btn edit" onclick="editRole(<?php echo $role['role_id']; ?>)" title="Edit Role">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="role-btn permissions" onclick="managePermissions(<?php echo $role['role_id']; ?>, '<?php echo htmlspecialchars($role['display_name']); ?>')" title="Manage Permissions">
                                <i class="fas fa-key"></i>
                            </button>
                        </div>
                    </div>
                    <div class="role-card-body">
                        <div class="role-stat-row">
                            <div class="role-stat">
                                <h4><?php echo $role['user_count']; ?></h4>
                                <p>Users</p>
                            </div>
                            <div class="role-stat">
                                <h4><?php echo count($rolePermissions[$role['role_id']] ?? []); ?></h4>
                                <p>Permissions</p>
                            </div>
                        </div>
                        <p class="role-description">
                            <?php echo htmlspecialchars($role['description'] ?? 'No description available'); ?>
                        </p>
                        <span class="role-status <?php echo $role['is_active'] ? 'active' : 'inactive'; ?>">
                            <i class="fas fa-circle"></i>
                            <?php echo $role['is_active'] ? 'Active' : 'Inactive'; ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Create Role Modal -->
    <div class="modal-overlay" id="createRoleModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle"></i> Create New Role</h3>
                <button class="modal-close" onclick="closeModal('createRoleModal')">&times;</button>
            </div>
            <form id="createRoleForm" onsubmit="submitCreateRole(event)">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Role Name (System)</label>
                        <input type="text" name="role_name" required placeholder="e.g., manager" pattern="[a-z_]+" title="Lowercase letters and underscores only">
                        <p style="font-size: 12px; color: #94a3b8; margin-top: 8px;">Used internally. Use lowercase letters and underscores only.</p>
                    </div>
                    <div class="form-group">
                        <label>Display Name</label>
                        <input type="text" name="display_name" required placeholder="e.g., Manager">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" placeholder="Describe the role's responsibilities..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closeModal('createRoleModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Role</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Role Modal -->
    <div class="modal-overlay" id="editRoleModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit Role</h3>
                <button class="modal-close" onclick="closeModal('editRoleModal')">&times;</button>
            </div>
            <form id="editRoleForm" onsubmit="submitEditRole(event)">
                <input type="hidden" name="role_id" id="editRoleId">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Role Name (System)</label>
                        <input type="text" name="role_name" id="editRoleName" required readonly style="background: #f1f5f9;">
                        <p style="font-size: 12px; color: #94a3b8; margin-top: 8px;">System name cannot be changed.</p>
                    </div>
                    <div class="form-group">
                        <label>Display Name</label>
                        <input type="text" name="display_name" id="editDisplayName" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" id="editDescription"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Status</label>
                        <select name="is_active" id="editIsActive">
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closeModal('editRoleModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Permissions Modal -->
    <div class="modal-overlay" id="permissionsModal">
        <div class="modal wide">
            <div class="modal-header">
                <h3><i class="fas fa-key"></i> Manage Permissions - <span id="permRoleName"></span></h3>
                <button class="modal-close" onclick="closeModal('permissionsModal')">&times;</button>
            </div>
            <form id="permissionsForm" onsubmit="submitPermissions(event)">
                <input type="hidden" name="role_id" id="permRoleId">
                <div class="modal-body">
                    <div class="select-all-row">
                        <label>
                            <input type="checkbox" id="selectAllPerms" onchange="toggleAllPermissions()">
                            Select All Permissions
                        </label>
                        <span class="selected-count" id="selectedCount">0 selected</span>
                    </div>
                    
                    <?php foreach ($permissionsByCategory as $category => $perms): ?>
                    <div class="permissions-category">
                        <div class="category-header">
                            <h4><?php echo htmlspecialchars($category); ?></h4>
                            <span class="count"><?php echo count($perms); ?></span>
                        </div>
                        <div class="permissions-grid">
                            <?php foreach ($perms as $perm): ?>
                            <label class="permission-item">
                                <input type="checkbox" name="permissions[]" value="<?php echo $perm['permission_id']; ?>" onchange="updateSelectedCount()">
                                <div class="permission-info">
                                    <h5><?php echo htmlspecialchars($perm['display_name']); ?></h5>
                                    <p><?php echo htmlspecialchars($perm['permission_name']); ?></p>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closeModal('permissionsModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Permissions</button>
                </div>
            </form>
        </div>
    </div>

    <?php include 'includes/superadmin_scripts.php'; ?>
    
    <script>
        // Role permissions data from PHP
        const rolePermissions = <?php echo json_encode($rolePermissions); ?>;
        const rolesData = <?php echo json_encode($roles); ?>;

        // Modal functions
        function openCreateRoleModal() {
            document.getElementById('createRoleForm').reset();
            document.getElementById('createRoleModal').classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        function editRole(roleId) {
            const role = rolesData.find(r => r.role_id == roleId);
            if (!role) return;
            
            document.getElementById('editRoleId').value = role.role_id;
            document.getElementById('editRoleName').value = role.role_name;
            document.getElementById('editDisplayName').value = role.display_name;
            document.getElementById('editDescription').value = role.description || '';
            document.getElementById('editIsActive').value = role.is_active;
            
            document.getElementById('editRoleModal').classList.add('show');
        }

        function managePermissions(roleId, roleName) {
            document.getElementById('permRoleId').value = roleId;
            document.getElementById('permRoleName').textContent = roleName;
            
            // Reset all checkboxes
            document.querySelectorAll('#permissionsForm input[type="checkbox"]').forEach(cb => {
                cb.checked = false;
                cb.closest('.permission-item')?.classList.remove('selected');
            });
            
            // Check the role's current permissions
            const perms = rolePermissions[roleId] || [];
            perms.forEach(permId => {
                const cb = document.querySelector(`#permissionsForm input[value="${permId}"]`);
                if (cb) {
                    cb.checked = true;
                    cb.closest('.permission-item')?.classList.add('selected');
                }
            });
            
            updateSelectedCount();
            document.getElementById('permissionsModal').classList.add('show');
        }

        // Permission checkbox visual
        document.querySelectorAll('.permission-item input').forEach(cb => {
            cb.addEventListener('change', function() {
                if (this.checked) {
                    this.closest('.permission-item').classList.add('selected');
                } else {
                    this.closest('.permission-item').classList.remove('selected');
                }
            });
        });

        function toggleAllPermissions() {
            const selectAll = document.getElementById('selectAllPerms').checked;
            document.querySelectorAll('#permissionsForm .permission-item input').forEach(cb => {
                cb.checked = selectAll;
                if (selectAll) {
                    cb.closest('.permission-item').classList.add('selected');
                } else {
                    cb.closest('.permission-item').classList.remove('selected');
                }
            });
            updateSelectedCount();
        }

        function updateSelectedCount() {
            const count = document.querySelectorAll('#permissionsForm .permission-item input:checked').length;
            const total = document.querySelectorAll('#permissionsForm .permission-item input').length;
            document.getElementById('selectedCount').textContent = `${count} of ${total} selected`;
            
            // Update select all checkbox state
            document.getElementById('selectAllPerms').checked = count === total;
            document.getElementById('selectAllPerms').indeterminate = count > 0 && count < total;
        }

        // Form submissions
        function submitCreateRole(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            
            fetch('api/create_role.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showAlert('Role created successfully!', 'success');
                    closeModal('createRoleModal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(data.error || 'Failed to create role', 'error');
                }
            })
            .catch(err => showAlert('An error occurred', 'error'));
        }

        function submitEditRole(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            
            fetch('api/update_role.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showAlert('Role updated successfully!', 'success');
                    closeModal('editRoleModal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(data.error || 'Failed to update role', 'error');
                }
            })
            .catch(err => showAlert('An error occurred', 'error'));
        }

        function submitPermissions(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            
            fetch('api/update_role_permissions.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showAlert('Permissions updated successfully!', 'success');
                    closeModal('permissionsModal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(data.error || 'Failed to update permissions', 'error');
                }
            })
            .catch(err => showAlert('An error occurred', 'error'));
        }

        function showAlert(message, type) {
            const alertBox = document.getElementById('alertBox');
            alertBox.className = `alert alert-${type}`;
            alertBox.innerHTML = `<i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-circle'}"></i> ${message}`;
            alertBox.style.display = 'flex';
            
            setTimeout(() => {
                alertBox.style.display = 'none';
            }, 5000);
        }

        // Close modal on outside click
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('show');
                }
            });
        });
    </script>
</body>
</html>
