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
if (!$perm->hasPermission('user.create') && !$perm->hasPermission('user.view')) {
    header("Location: ../auth/login.php?error=unauthorized");
    exit;
}

$username = $_SESSION['username'] ?? 'Super Admin';

// Fetch all users with their roles
$stmt = $conn->query("
    SELECT 
        u.user_id,
        u.username,
        u.email,
        u.role as primary_role,
        u.is_active,
        u.created_at,
        GROUP_CONCAT(r.display_name ORDER BY r.display_name SEPARATOR ', ') as all_roles
    FROM users u
    LEFT JOIN user_roles_new urn ON u.user_id = urn.user_id
    LEFT JOIN roles r ON urn.role_id = r.role_id
    GROUP BY u.user_id
    ORDER BY u.user_id DESC
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all available roles
$rolesStmt = $conn->query("SELECT role_id, role_name, display_name FROM roles WHERE is_active = 1 ORDER BY display_name");
$availableRoles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

// Statistics
$totalUsers = count($users);
$activeUsers = count(array_filter($users, fn($u) => $u['is_active']));
$inactiveUsers = $totalUsers - $activeUsers;

// Count users by primary role
$roleStats = [];
foreach ($users as $user) {
    $role = $user['primary_role'] ?? 'unknown';
    $roleStats[$role] = ($roleStats[$role] ?? 0) + 1;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Super Admin</title>
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

        .stat-icon.blue {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(37, 99, 235, 0.15));
            color: #3b82f6;
        }

        .stat-icon.green {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.15));
            color: #10b981;
        }

        .stat-icon.red {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(220, 38, 38, 0.15));
            color: #ef4444;
        }

        .stat-icon.purple {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(124, 58, 237, 0.15));
            color: #8b5cf6;
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

        /* Toolbar */
        .toolbar {
            background: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .search-box {
            position: relative;
            flex: 1;
            max-width: 400px;
        }

        .search-box input {
            width: 100%;
            padding: 12px 15px 12px 45px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-box i {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
        }

        .filter-group {
            display: flex;
            gap: 10px;
        }

        .filter-btn {
            padding: 10px 18px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            background: white;
            color: #64748b;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-btn:hover, .filter-btn.active {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }

        /* Users Table */
        .users-table-card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .table-header {
            padding: 20px 25px;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 18px;
            font-weight: 700;
            color: #1e293b;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
        }

        .users-table th {
            background: #f8fafc;
            padding: 16px 20px;
            text-align: left;
            font-size: 12px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }

        .users-table td {
            padding: 18px 20px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            color: #334155;
        }

        .users-table tr:hover {
            background: #fafbfc;
        }

        .users-table tr:last-child td {
            border-bottom: none;
        }

        /* User Cell */
        .user-cell {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .user-avatar {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 16px;
            font-weight: 600;
        }

        .user-info h4 {
            font-size: 15px;
            font-weight: 600;
            color: #1e293b;
            margin: 0 0 4px 0;
        }

        .user-info p {
            font-size: 13px;
            color: #94a3b8;
            margin: 0;
        }

        /* Role Badges */
        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .role-badge.super_admin {
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(217, 119, 6, 0.15));
            color: #f59e0b;
        }

        .role-badge.administrator {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(220, 38, 38, 0.15));
            color: #ef4444;
        }

        .role-badge.accountant {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.15));
            color: #10b981;
        }

        .role-badge.director {
            background: linear-gradient(135deg, rgba(139, 92, 246, 0.15), rgba(124, 58, 237, 0.15));
            color: #8b5cf6;
        }

        .role-badge.employee {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(37, 99, 235, 0.15));
            color: #3b82f6;
        }

        .role-badge.hr_officer {
            background: linear-gradient(135deg, rgba(236, 72, 153, 0.15), rgba(219, 39, 119, 0.15));
            color: #ec4899;
        }

        .role-badge.auditor {
            background: linear-gradient(135deg, rgba(20, 184, 166, 0.15), rgba(13, 148, 136, 0.15));
            color: #14b8a6;
        }

        /* Additional Roles */
        .additional-roles {
            margin-top: 6px;
            font-size: 12px;
            color: #64748b;
        }

        /* Status Badge */
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-badge.active {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .status-badge.inactive {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .status-badge i {
            font-size: 6px;
        }

        /* Action Buttons */
        .action-btns {
            display: flex;
            gap: 8px;
        }

        .action-btn {
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

        .action-btn.edit {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .action-btn.edit:hover {
            background: #3b82f6;
            color: white;
        }

        .action-btn.roles {
            background: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
        }

        .action-btn.roles:hover {
            background: #8b5cf6;
            color: white;
        }

        .action-btn.toggle {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        .action-btn.toggle:hover {
            background: #f59e0b;
            color: white;
        }

        .action-btn.delete {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .action-btn.delete:hover {
            background: #ef4444;
            color: white;
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
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.3);
            animation: modalSlide 0.3s ease-out;
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
        .form-group select {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .form-hint {
            font-size: 12px;
            color: #94a3b8;
            margin-top: 8px;
        }

        .modal-footer {
            padding: 20px 30px;
            border-top: 1px solid #f1f5f9;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
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

        /* Roles Modal */
        .roles-list {
            display: grid;
            gap: 12px;
        }

        .role-checkbox {
            display: flex;
            align-items: center;
            gap: 14px;
            padding: 16px;
            background: #f8fafc;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .role-checkbox:hover {
            background: #f1f5f9;
        }

        .role-checkbox.selected {
            background: rgba(102, 126, 234, 0.1);
            border-color: #667eea;
        }

        .role-checkbox input {
            width: 20px;
            height: 20px;
            accent-color: #667eea;
        }

        .role-checkbox-info h5 {
            font-size: 14px;
            font-weight: 600;
            color: #1e293b;
            margin: 0 0 4px 0;
        }

        .role-checkbox-info p {
            font-size: 12px;
            color: #64748b;
            margin: 0;
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

        /* Date formatting */
        .date-cell {
            font-size: 13px;
            color: #64748b;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #94a3b8;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        .empty-state h4 {
            font-size: 18px;
            color: #64748b;
            margin-bottom: 8px;
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
            .toolbar {
                flex-direction: column;
            }
            .search-box {
                max-width: 100%;
            }
            .users-table {
                display: block;
                overflow-x: auto;
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
                <h1><i class="fas fa-users-cog"></i> User Management</h1>
                <p>Create, manage, and monitor all user accounts in the system</p>
            </div>
            <div class="header-actions">
                <button class="btn btn-white" onclick="openCreateModal()">
                    <i class="fas fa-plus"></i> Create User
                </button>
            </div>
        </div>

        <!-- Alert Box -->
        <div id="alertBox" class="alert" style="display: none;"></div>

        <!-- Stats Row -->
        <div class="stats-row">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $totalUsers; ?></h3>
                    <p>Total Users</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $activeUsers; ?></h3>
                    <p>Active Users</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon red">
                    <i class="fas fa-user-times"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo $inactiveUsers; ?></h3>
                    <p>Inactive Users</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-user-shield"></i>
                </div>
                <div class="stat-info">
                    <h3><?php echo count($availableRoles); ?></h3>
                    <p>Available Roles</p>
                </div>
            </div>
        </div>

        <!-- Toolbar -->
        <div class="toolbar">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search users by name, email, or role..." onkeyup="filterUsers()">
            </div>
            <div class="filter-group">
                <button class="filter-btn active" data-filter="all" onclick="setFilter('all', this)">All</button>
                <button class="filter-btn" data-filter="active" onclick="setFilter('active', this)">Active</button>
                <button class="filter-btn" data-filter="inactive" onclick="setFilter('inactive', this)">Inactive</button>
            </div>
        </div>

        <!-- Users Table -->
        <div class="users-table-card">
            <div class="table-header">
                <div class="table-title"><?php echo $totalUsers; ?> Users</div>
            </div>
            <table class="users-table" id="usersTable">
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Primary Role</th>
                        <th>Additional Roles</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">
                                <i class="fas fa-users"></i>
                                <h4>No Users Found</h4>
                                <p>Create your first user to get started</p>
                            </div>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($users as $user): ?>
                    <tr class="user-row" data-status="<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                        <td>
                            <div class="user-cell">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                </div>
                                <div class="user-info">
                                    <h4><?php echo htmlspecialchars($user['username']); ?></h4>
                                    <p><?php echo htmlspecialchars($user['email'] ?? 'No email'); ?></p>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="role-badge <?php echo htmlspecialchars($user['primary_role'] ?? ''); ?>">
                                <i class="fas fa-user-tag"></i>
                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $user['primary_role'] ?? 'No Role'))); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($user['all_roles']): ?>
                                <div class="additional-roles">
                                    <?php echo htmlspecialchars($user['all_roles']); ?>
                                </div>
                            <?php else: ?>
                                <span style="color: #94a3b8; font-size: 13px;">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="status-badge <?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                <i class="fas fa-circle"></i>
                                <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </td>
                        <td class="date-cell">
                            <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                        </td>
                        <td>
                            <div class="action-btns">
                                <button class="action-btn edit" onclick="editUser(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo htmlspecialchars($user['email'] ?? ''); ?>')" title="Edit User">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="action-btn roles" onclick="manageRoles(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" title="Manage Roles">
                                    <i class="fas fa-user-tag"></i>
                                </button>
                                <button class="action-btn toggle" onclick="toggleStatus(<?php echo $user['user_id']; ?>)" title="Toggle Status">
                                    <i class="fas fa-power-off"></i>
                                </button>
                                <button class="action-btn delete" onclick="deleteUser(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" title="Delete User">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Create User Modal -->
    <div class="modal-overlay" id="createModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Create New User</h3>
                <button class="modal-close" onclick="closeModal('createModal')">&times;</button>
            </div>
            <form id="createUserForm" onsubmit="submitCreateUser(event)">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" required placeholder="Enter username">
                        <div class="form-hint">Username must be unique and contain only letters, numbers, and underscores</div>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" required placeholder="Enter email address">
                    </div>
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required minlength="6" placeholder="Enter password">
                        <div class="form-hint">Minimum 6 characters</div>
                    </div>
                    <div class="form-group">
                        <label>Primary Role</label>
                        <select name="role" required>
                            <option value="">Select a role</option>
                            <?php foreach ($availableRoles as $role): ?>
                            <option value="<?php echo htmlspecialchars($role['role_name']); ?>">
                                <?php echo htmlspecialchars($role['display_name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closeModal('createModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div class="modal-overlay" id="editModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-user-edit"></i> Edit User</h3>
                <button class="modal-close" onclick="closeModal('editModal')">&times;</button>
            </div>
            <form id="editUserForm" onsubmit="submitEditUser(event)">
                <input type="hidden" name="user_id" id="editUserId">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Username</label>
                        <input type="text" name="username" id="editUsername" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" id="editEmail" required>
                    </div>
                    <div class="form-group">
                        <label>New Password (leave blank to keep current)</label>
                        <input type="password" name="password" placeholder="Enter new password">
                        <div class="form-hint">Leave empty if you don't want to change the password</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closeModal('editModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Manage Roles Modal -->
    <div class="modal-overlay" id="rolesModal">
        <div class="modal">
            <div class="modal-header">
                <h3><i class="fas fa-user-tag"></i> Manage Roles</h3>
                <button class="modal-close" onclick="closeModal('rolesModal')">&times;</button>
            </div>
            <form id="rolesForm" onsubmit="submitRoles(event)">
                <input type="hidden" name="user_id" id="rolesUserId">
                <div class="modal-body">
                    <p style="margin-bottom: 20px; color: #64748b;">
                        Assign roles to <strong id="rolesUsername" style="color: #1e293b;"></strong>
                    </p>
                    <div class="roles-list">
                        <?php foreach ($availableRoles as $role): ?>
                        <label class="role-checkbox">
                            <input type="checkbox" name="roles[]" value="<?php echo $role['role_id']; ?>">
                            <div class="role-checkbox-info">
                                <h5><?php echo htmlspecialchars($role['display_name']); ?></h5>
                                <p><?php echo htmlspecialchars($role['role_name']); ?></p>
                            </div>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closeModal('rolesModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Roles</button>
                </div>
            </form>
        </div>
    </div>

    <?php include 'includes/superadmin_scripts.php'; ?>
    
    <script>
        let currentFilter = 'all';

        // Search functionality
        function filterUsers() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const rows = document.querySelectorAll('.user-row');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const status = row.dataset.status;
                const matchesSearch = text.includes(searchTerm);
                const matchesFilter = currentFilter === 'all' || status === currentFilter;
                
                row.style.display = matchesSearch && matchesFilter ? '' : 'none';
            });
        }

        // Filter functionality
        function setFilter(filter, btn) {
            currentFilter = filter;
            document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
            btn.classList.add('active');
            filterUsers();
        }

        // Modal functions
        function openCreateModal() {
            document.getElementById('createModal').classList.add('show');
        }

        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        function editUser(userId, username, email) {
            document.getElementById('editUserId').value = userId;
            document.getElementById('editUsername').value = username;
            document.getElementById('editEmail').value = email;
            document.getElementById('editModal').classList.add('show');
        }

        function manageRoles(userId, username) {
            document.getElementById('rolesUserId').value = userId;
            document.getElementById('rolesUsername').textContent = username;
            
            // Fetch current roles and check the appropriate checkboxes
            fetch(`api/get_user_roles.php?user_id=${userId}`)
                .then(r => r.json())
                .then(data => {
                    // Uncheck all first
                    document.querySelectorAll('#rolesForm input[type="checkbox"]').forEach(cb => {
                        cb.checked = false;
                        cb.closest('.role-checkbox').classList.remove('selected');
                    });
                    
                    // Check the user's roles
                    if (data.roles) {
                        data.roles.forEach(roleId => {
                            const cb = document.querySelector(`#rolesForm input[value="${roleId}"]`);
                            if (cb) {
                                cb.checked = true;
                                cb.closest('.role-checkbox').classList.add('selected');
                            }
                        });
                    }
                })
                .catch(err => console.error('Error fetching roles:', err));
            
            document.getElementById('rolesModal').classList.add('show');
        }

        // Role checkbox selection visual
        document.querySelectorAll('.role-checkbox input').forEach(cb => {
            cb.addEventListener('change', function() {
                if (this.checked) {
                    this.closest('.role-checkbox').classList.add('selected');
                } else {
                    this.closest('.role-checkbox').classList.remove('selected');
                }
            });
        });

        // Form submissions
        function submitCreateUser(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            
            fetch('api/create_user.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showAlert('User created successfully!', 'success');
                    closeModal('createModal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(data.error || 'Failed to create user', 'error');
                }
            })
            .catch(err => showAlert('An error occurred', 'error'));
        }

        function submitEditUser(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            
            fetch('api/update_user.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showAlert('User updated successfully!', 'success');
                    closeModal('editModal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(data.error || 'Failed to update user', 'error');
                }
            })
            .catch(err => showAlert('An error occurred', 'error'));
        }

        function submitRoles(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            
            fetch('api/update_user_roles.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showAlert('Roles updated successfully!', 'success');
                    closeModal('rolesModal');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(data.error || 'Failed to update roles', 'error');
                }
            })
            .catch(err => showAlert('An error occurred', 'error'));
        }

        function toggleStatus(userId) {
            if (!confirm('Are you sure you want to toggle this user\'s status?')) return;
            
            fetch('api/toggle_user_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: userId })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showAlert('Status updated successfully!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(data.error || 'Failed to update status', 'error');
                }
            })
            .catch(err => showAlert('An error occurred', 'error'));
        }

        function deleteUser(userId, username) {
            if (!confirm(`Are you sure you want to delete user "${username}"? This action cannot be undone.`)) return;
            
            fetch('api/delete_user.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ user_id: userId })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    showAlert('User deleted successfully!', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(data.error || 'Failed to delete user', 'error');
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
