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

// Fetch all users
$usersStmt = $conn->query("
    SELECT user_id, username, email, role as primary_role, is_active
    FROM users
    WHERE is_active = 1
    ORDER BY username
");
$users = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all roles
$rolesStmt = $conn->query("SELECT role_id, role_name, display_name, description FROM roles WHERE is_active = 1 ORDER BY role_name");
$roles = $rolesStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch user-role assignments
$assignmentsStmt = $conn->query("
    SELECT 
        urn.user_role_id,
        urn.user_id,
        u.username,
        r.role_id,
        r.role_name,
        r.display_name,
        urn.assigned_at
    FROM user_roles_new urn
    JOIN users u ON urn.user_id = u.user_id
    JOIN roles r ON urn.role_id = r.role_id
    ORDER BY u.username, r.role_name
");
$assignments = $assignmentsStmt->fetchAll(PDO::FETCH_ASSOC);

// Group assignments by user
$userRoles = [];
foreach ($assignments as $assignment) {
    $userId = $assignment['user_id'];
    if (!isset($userRoles[$userId])) {
        $userRoles[$userId] = [
            'username' => $assignment['username'],
            'roles' => []
        ];
    }
    $userRoles[$userId]['roles'][] = $assignment;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Role Management - Super Admin</title>
    <?php include 'includes/superadmin_styles.php'; ?>
    <style>
        .roles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .role-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s;
        }
        .role-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.2);
        }
        .role-card h3 {
            color: #1e293b;
            font-size: 18px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .role-card .role-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
        }
        .role-card p {
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 15px;
        }
        .role-card .stats {
            display: flex;
            gap: 20px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
            font-size: 13px;
        }
        .role-card .stats div {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #64748b;
        }
        .role-card .stats strong {
            color: #667eea;
        }
        .assignments-table {
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        .assignments-table table {
            width: 100%;
            border-collapse: collapse;
        }
        .assignments-table th {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }
        .assignments-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e2e8f0;
            font-size: 14px;
        }
        .assignments-table tr:hover {
            background: #f8fafc;
        }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            margin: 2px;
        }
        .badge-role {
            background: #e0e7ff;
            color: #4338ca;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin: 0 3px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-danger {
            background: #ef4444;
            color: white;
        }
        .btn-danger:hover {
            background: #dc2626;
        }
        .btn-secondary {
            background: #6b7280;
            color: white;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }
        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 30px;
            border-radius: 20px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }
        .modal-header h3 {
            color: #1e293b;
            font-size: 22px;
        }
        .close {
            font-size: 28px;
            font-weight: bold;
            color: #64748b;
            cursor: pointer;
        }
        .close:hover {
            color: #ef4444;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #334155;
            font-weight: 600;
            font-size: 14px;
        }
        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
        }
        .alert {
            padding: 12px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 14px;
            display: none;
        }
        .alert-success {
            background: #d1fae5;
            color: #065f46;
        }
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
        }
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 30px 0 20px 0;
        }
        .section-header h2 {
            font-size: 20px;
            color: #1e293b;
        }
    </style>
</head>
<body>
    <?php include 'includes/superadmin_navbar.php'; ?>
    
    <div class="container">
        <?php include 'includes/superadmin_sidebar.php'; ?>
        
        <div class="main-content">
            <div class="page-header">
                <div>
                    <h1><i class="fas fa-user-tag"></i> Role Management</h1>
                    <p>Assign and manage user roles across the system</p>
                </div>
            </div>

            <div id="alertBox" class="alert"></div>

            <!-- Available Roles Section -->
            <div class="section-header">
                <h2>Available Roles</h2>
            </div>

            <div class="roles-grid">
                <?php foreach ($roles as $role): ?>
                    <?php
                    // Count users with this role
                    $countStmt = $conn->prepare("SELECT COUNT(*) as count FROM user_roles_new WHERE role_id = ?");
                    $countStmt->execute([$role['role_id']]);
                    $userCount = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
                    
                    // Role icons
                    $roleIcons = [
                        'super_admin' => 'fa-shield-halved',
                        'administrator' => 'fa-user-shield',
                        'hr_officer' => 'fa-users-gear',
                        'accountant' => 'fa-calculator',
                        'director' => 'fa-crown',
                        'auditor' => 'fa-clipboard-check',
                        'employee' => 'fa-user'
                    ];
                    $icon = $roleIcons[$role['role_name']] ?? 'fa-user-tag';
                    ?>
                    <div class="role-card">
                        <h3>
                            <div class="role-icon">
                                <i class="fas <?php echo $icon; ?>"></i>
                            </div>
                            <?php echo htmlspecialchars($role['display_name']); ?>
                        </h3>
                        <p><?php echo htmlspecialchars($role['description'] ?? 'No description available'); ?></p>
                        <div class="stats">
                            <div>
                                <i class="fas fa-users"></i>
                                <strong><?php echo $userCount; ?></strong> users
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Role Assignments Section -->
            <div class="section-header">
                <h2>Current Role Assignments</h2>
                <button class="btn btn-primary" onclick="openAssignModal()">
                    <i class="fas fa-plus"></i> Assign Role
                </button>
            </div>

            <div class="assignments-table">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Email</th>
                            <th>Assigned Roles</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                            <td><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></td>
                            <td>
                                <?php
                                if (isset($userRoles[$user['user_id']])) {
                                    foreach ($userRoles[$user['user_id']]['roles'] as $roleAssignment) {
                                        echo '<span class="badge badge-role">' . 
                                             htmlspecialchars($roleAssignment['display_name']) . 
                                             '</span> ';
                                    }
                                } else {
                                    echo '<span style="color: #64748b; font-size: 13px;">No roles assigned</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <button class="btn btn-primary" onclick="openManageRolesModal(<?php echo $user['user_id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                    <i class="fas fa-edit"></i> Manage Roles
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Assign Role Modal -->
    <div id="assignRoleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-plus"></i> Assign Role to User</h3>
                <span class="close" onclick="closeAssignModal()">&times;</span>
            </div>
            <form id="assignRoleForm">
                <div class="form-group">
                    <label>Select User *</label>
                    <select name="user_id" required>
                        <option value="">-- Select User --</option>
                        <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['user_id']; ?>">
                            <?php echo htmlspecialchars($user['username']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Select Role *</label>
                    <select name="role_id" required>
                        <option value="">-- Select Role --</option>
                        <?php foreach ($roles as $role): ?>
                        <option value="<?php echo $role['role_id']; ?>">
                            <?php echo htmlspecialchars($role['display_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 25px;">
                    <button type="button" class="btn btn-secondary" onclick="closeAssignModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Assign Role
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Manage User Roles Modal -->
    <div id="manageRolesModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-tasks"></i> Manage Roles: <span id="modalUsername"></span></h3>
                <span class="close" onclick="closeManageRolesModal()">&times;</span>
            </div>
            <div id="currentRolesList" style="margin-bottom: 20px;"></div>
            <form id="addRoleToUserForm" style="margin-top: 20px; padding-top: 20px; border-top: 2px solid #e2e8f0;">
                <input type="hidden" name="user_id" id="manageUserId">
                <div class="form-group">
                    <label>Add Another Role</label>
                    <select name="role_id" required>
                        <option value="">-- Select Role --</option>
                        <?php foreach ($roles as $role): ?>
                        <option value="<?php echo $role['role_id']; ?>">
                            <?php echo htmlspecialchars($role['display_name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeManageRolesModal()">Close</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Role
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php include 'includes/superadmin_scripts.php'; ?>
    <script>
        function openAssignModal() {
            document.getElementById('assignRoleModal').style.display = 'block';
        }

        function closeAssignModal() {
            document.getElementById('assignRoleModal').style.display = 'none';
            document.getElementById('assignRoleForm').reset();
        }

        async function openManageRolesModal(userId, username) {
            document.getElementById('manageUserId').value = userId;
            document.getElementById('modalUsername').textContent = username;
            
            // Fetch current roles
            try {
                const response = await fetch(`api/get_user_roles.php?user_id=${userId}`);
                const result = await response.json();
                
                if (result.success) {
                    const rolesList = document.getElementById('currentRolesList');
                    if (result.roles.length > 0) {
                        rolesList.innerHTML = '<h4 style="margin-bottom: 15px; color: #1e293b;">Current Roles:</h4>';
                        result.roles.forEach(role => {
                            rolesList.innerHTML += `
                                <div style="display: flex; justify-content: space-between; align-items: center; padding: 12px; background: #f8fafc; border-radius: 10px; margin-bottom: 10px;">
                                    <span style="font-weight: 600; color: #334155;">${role.display_name}</span>
                                    <button class="btn btn-danger" onclick="revokeRole(${role.user_role_id}, '${role.display_name}')">
                                        <i class="fas fa-times"></i> Revoke
                                    </button>
                                </div>
                            `;
                        });
                    } else {
                        rolesList.innerHTML = '<p style="color: #64748b; font-style: italic;">No roles assigned yet</p>';
                    }
                }
            } catch (error) {
                console.error('Error fetching roles:', error);
            }
            
            document.getElementById('manageRolesModal').style.display = 'block';
        }

        function closeManageRolesModal() {
            document.getElementById('manageRolesModal').style.display = 'none';
            document.getElementById('addRoleToUserForm').reset();
        }

        // Assign role form submission
        document.getElementById('assignRoleForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            try {
                const response = await fetch('api/assign_role.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    showAlert('Role assigned successfully!', 'success');
                    closeAssignModal();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(result.message || 'Failed to assign role', 'error');
                }
            } catch (error) {
                showAlert('An error occurred', 'error');
            }
        });

        // Add role to user form submission
        document.getElementById('addRoleToUserForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            try {
                const response = await fetch('api/assign_role.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                
                if (result.success) {
                    showAlert('Role added successfully!', 'success');
                    closeManageRolesModal();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(result.message || 'Failed to add role', 'error');
                }
            } catch (error) {
                showAlert('An error occurred', 'error');
            }
        });

        async function revokeRole(userRoleId, roleName) {
            if (!confirm(`Are you sure you want to revoke the "${roleName}" role?`)) {
                return;
            }
            
            try {
                const response = await fetch('api/revoke_role.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ user_role_id: userRoleId })
                });
                const result = await response.json();
                
                if (result.success) {
                    showAlert('Role revoked successfully!', 'success');
                    closeManageRolesModal();
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showAlert(result.message || 'Failed to revoke role', 'error');
                }
            } catch (error) {
                showAlert('An error occurred', 'error');
            }
        }

        function showAlert(message, type) {
            const alertBox = document.getElementById('alertBox');
            alertBox.textContent = message;
            alertBox.className = 'alert alert-' + type;
            alertBox.style.display = 'block';
            setTimeout(() => alertBox.style.display = 'none', 5000);
        }

        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>
