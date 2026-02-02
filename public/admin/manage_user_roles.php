<?php
session_start();
require_once '../../app/Models/User.php';
require_once '../../app/Helpers/RBACHelper.php';

// Only Super Admin can access role management
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? ''];
$hasSuperAdminRole = in_array('super_admin', $userRoles);

if (!$hasSuperAdminRole) {
    // Redirect admins to admin dashboard, others to login
    $hasAdminRole = in_array('administrator', $userRoles);
    if ($hasAdminRole) {
        header("Location: admin_dashboard.php?error=unauthorized");
    } else {
        header("Location: /payslip_generator/public/auth/login.php");
    }
    exit;
}

$db = new PDO('mysql:host=localhost;dbname=payslip_generator', 'root', '');
$userModel = new User($db);

// Get all users
$stmt = $db->prepare("SELECT user_id, username, email, role FROM users ORDER BY username");
$stmt->execute();
$allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all available roles
$stmt = $db->prepare("SELECT role_id, role_name, description FROM roles ORDER BY role_name");
$stmt->execute();
$allRoles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle role assignment/removal
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = intval($_POST['user_id'] ?? 0);
    $roleId = intval($_POST['role_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    if ($userId > 0 && $roleId > 0) {
        try {
            if ($action === 'assign') {
                // Check if user already has this role
                $stmt = $db->prepare("SELECT COUNT(*) FROM user_roles WHERE user_id = ? AND role_id = ?");
                $stmt->execute([$userId, $roleId]);
                $exists = $stmt->fetchColumn();

                if (!$exists) {
                    $stmt = $db->prepare("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)");
                    $stmt->execute([$userId, $roleId]);
                    $message = "Role assigned successfully!";
                    $messageType = "success";
                } else {
                    $message = "User already has this role.";
                    $messageType = "warning";
                }
            } elseif ($action === 'remove') {
                $stmt = $db->prepare("DELETE FROM user_roles WHERE user_id = ? AND role_id = ?");
                $stmt->execute([$userId, $roleId]);
                $message = "Role removed successfully!";
                $messageType = "success";
            }
        } catch (Exception $e) {
            $message = "Error: " . $e->getMessage();
            $messageType = "error";
        }

        // Refresh users list
        $stmt = $db->prepare("SELECT user_id, username, email, role FROM users ORDER BY username");
        $stmt->execute();
        $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Get user roles for each user
$userRolesMap = [];
foreach ($allUsers as $user) {
    $stmt = $db->prepare("
        SELECT r.role_id, r.role_name 
        FROM user_roles ur 
        JOIN roles r ON ur.role_id = r.role_id 
        WHERE ur.user_id = ?
    ");
    $stmt->execute([$user['user_id']]);
    $userRolesMap[$user['user_id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage User Roles - Admin</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Roboto", sans-serif;
            background: #ffffff;
            color: #2d3748;
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
        }

        .breadcrumb {
            font-size: 14px;
            margin-bottom: 10px;
            opacity: 0.9;
        }

        .breadcrumb a {
            color: white;
            text-decoration: none;
            transition: opacity 0.3s;
        }

        .breadcrumb a:hover {
            opacity: 0.8;
        }

        .breadcrumb i {
            margin: 0 8px;
            font-size: 10px;
        }

        .header h1 {
            font-size: 32px;
            font-weight: 700;
            margin: 0;
        }

        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-10px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .message.success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }

        .message.warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fcd34d;
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f7fafc;
            padding: 18px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        tr:hover {
            background: #f7fafc;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 18px;
        }

        .user-details h4 {
            font-size: 15px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 4px;
        }

        .user-details p {
            font-size: 13px;
            color: #718096;
        }

        .roles-container {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            border: 2px solid;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .role-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .role-badge.employee {
            background: #ede9fe;
            color: #7c3aed;
            border-color: #7c3aed;
        }

        .role-badge.accountant {
            background: #fef3c7;
            color: #d97706;
            border-color: #d97706;
        }

        .role-badge.director {
            background: #ddd6fe;
            color: #6d28d9;
            border-color: #6d28d9;
        }

        .role-badge.administrator {
            background: #fee2e2;
            color: #dc2626;
            border-color: #dc2626;
        }

        .role-badge .remove-btn {
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            padding: 0;
            font-size: 14px;
            transition: transform 0.2s ease;
        }

        .role-badge .remove-btn:hover {
            transform: scale(1.2);
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f7fafc;
            color: #2d3748;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #edf2f7;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            padding: 35px;
            border-radius: 15px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                transform: translateY(20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .modal-header h2 {
            font-size: 24px;
            font-weight: 700;
            color: #2d3748;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: #a0aec0;
            transition: color 0.3s ease;
        }

        .close-btn:hover {
            color: #2d3748;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
            color: #2d3748;
        }

        .form-group select {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            background: white;
            color: #2d3748;
            font-family: "Roboto", sans-serif;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .form-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .modal-footer {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 30px;
        }

        .no-data {
            text-align: center;
            padding: 60px 20px;
            color: #a0aec0;
        }

        .no-data i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .no-data p {
            font-size: 16px;
            font-weight: 500;
        }

        .back-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .back-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .header h1 {
                font-size: 24px;
            }

            table {
                font-size: 13px;
            }

            th, td {
                padding: 12px;
            }

            .user-info {
                flex-direction: column;
                align-items: flex-start;
            }

            .user-avatar {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }

            .roles-container {
                flex-direction: column;
                gap: 6px;
            }

            .role-badge {
                width: 100%;
                justify-content: space-between;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .back-btn {
                width: 50px;
                height: 50px;
                bottom: 15px;
                right: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="breadcrumb">
                <a href="/payslip_generator/public/admin/admin_dashboard.php"><i class="fas fa-home"></i> Admin Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <span>Manage User Roles</span>
            </div>
            <h1><i class="fas fa-user-shield"></i> Manage User Roles</h1>
        </div>

        <!-- Message -->
        <?php if ($message): ?>
            <div class="message <?= $messageType ?>">
                <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : ($messageType === 'warning' ? 'exclamation-circle' : 'times-circle') ?>"></i>
                <span><?= htmlspecialchars($message) ?></span>
            </div>
        <?php endif; ?>

        <!-- Users Table Card -->
        <div class="card">
            <?php if (count($allUsers) > 0): ?>
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Assigned Roles</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($allUsers as $user): ?>
                            <tr>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?= strtoupper(substr($user['username'], 0, 1)) ?>
                                        </div>
                                        <div class="user-details">
                                            <h4><?= htmlspecialchars($user['username']) ?></h4>
                                            <p><?= htmlspecialchars($user['email']) ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="roles-container">
                                        <?php
                                        $userRoles = $userRolesMap[$user['user_id']] ?? [];
                                        if (count($userRoles) > 0):
                                            foreach ($userRoles as $role):
                                                ?>
                                                <form method="POST" style="display: inline; margin: 0;" onsubmit="return confirm('Remove <?= htmlspecialchars($role['role_name']) ?> role from <?= htmlspecialchars($user['username']) ?>?');">
                                                    <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                                    <input type="hidden" name="role_id" value="<?= $role['role_id'] ?>">
                                                    <input type="hidden" name="action" value="remove">
                                                    <button type="submit" class="role-badge <?= htmlspecialchars($role['role_name']) ?>">
                                                        <i class="fas fa-user-tag"></i>
                                                        <?= htmlspecialchars(ucfirst($role['role_name'])) ?>
                                                        <span class="remove-btn" title="Remove role">
                                                            <i class="fas fa-times"></i>
                                                        </span>
                                                    </button>
                                                </form>
                                                <?php
                                            endforeach;
                                        else:
                                            ?>
                                            <span style="color: #a0aec0; font-style: italic;">No roles assigned</span>
                                            <?php
                                        endif;
                                        ?>
                                    </div>
                                </td>
                                <td>
                                    <button class="btn btn-primary" onclick="openAssignModal(<?= $user['user_id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                                        <i class="fas fa-plus-circle"></i> Assign Role
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-users-slash"></i>
                    <p>No users found in the system</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Assign Role Modal -->
    <div id="assignModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-user-plus"></i> Assign Role</h2>
                <button class="close-btn" onclick="closeAssignModal()" type="button">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="user_id" id="modalUserId">
                <input type="hidden" name="action" value="assign">

                <div class="form-group">
                    <label><i class="fas fa-user"></i> User:</label>
                    <div style="padding: 12px; background: #f7fafc; border-radius: 8px; font-weight: 600; color: #667eea;">
                        <span id="modalUserName"></span>
                    </div>
                </div>

                <div class="form-group">
                    <label for="modalRoleId"><i class="fas fa-user-shield"></i> Select Role:</label>
                    <select id="modalRoleId" name="role_id" required>
                        <option value="">-- Choose a role to assign --</option>
                        <?php foreach ($allRoles as $role): ?>
                            <option value="<?= $role['role_id'] ?>">
                                <?= htmlspecialchars(ucfirst($role['role_name'])) ?> - <?= htmlspecialchars($role['description']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAssignModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-check"></i> Assign Role
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Back Button -->
    <a href="/payslip_generator/public/admin/admin_dashboard.php" class="back-btn" title="Back to Dashboard">
        <i class="fas fa-arrow-left"></i>
    </a>

    <script>
        function openAssignModal(userId, userName) {
            document.getElementById('modalUserId').value = userId;
            document.getElementById('modalUserName').textContent = userName;
            document.getElementById('assignModal').classList.add('active');
        }

        function closeAssignModal() {
            document.getElementById('assignModal').classList.remove('active');
            document.getElementById('modalRoleId').value = '';
        }

        // Close modal when clicking outside
        document.getElementById('assignModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeAssignModal();
            }
        });
    </script>
</body>
</html>
