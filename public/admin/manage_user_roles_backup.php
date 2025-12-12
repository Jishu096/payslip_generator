<?php
session_start();
require_once '../../app/Models/User.php';
require_once '../../app/Helpers/RBACHelper.php';

// Check if user is logged in and is administrator
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? ''];
if (!in_array('administrator', $userRoles) && $_SESSION['role'] !== 'administrator') {
    header("Location: /payslip_generator/public/auth/login.php");
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

        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --text-primary: #1a1f36;
            --text-secondary: #4a5568;
            --text-tertiary: #8b92a7;
            --border-color: #e2e8f0;
            --accent: #667eea;
            --accent-dark: #764ba2;
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
            --shadow: rgba(0, 0, 0, 0.1);
        }

        :root

        body {
            font-family: "Roboto", sans-serif;
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 40px;
            flex-wrap: wrap;
            gap: 20px;
        }

        .header h1 {
            font-family: "Roboto", sans-serif;
            font-size: 32px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .breadcrumb {
            display: flex;
            gap: 8px;
            align-items: center;
            font-size: 14px;
            color: var(--text-tertiary);
        }

        .breadcrumb a {
            color: var(--accent);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .breadcrumb a:hover {
            color: var(--accent-dark);
        }

        .message {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }

        .message.success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .message.warning {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning);
            border-left: 4px solid var(--warning);
        }

        .message.error {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--error);
            border-left: 4px solid var(--error);
        }

        .card {
            background: var(--bg-primary);
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 10px var(--shadow);
            margin-bottom: 24px;
        }

        .table-responsive {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: var(--bg-secondary);
            padding: 16px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            color: var(--text-secondary);
            border-bottom: 2px solid var(--border-color);
            font-family: "Roboto", sans-serif;
        }

        td {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
        }

        tr:hover {
            background-color: var(--bg-secondary);
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 16px;
        }

        .user-details h4 {
            margin-bottom: 4px;
            font-size: 14px;
        }

        .user-details p {
            font-size: 12px;
            color: var(--text-tertiary);
        }

        .roles-container {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .role-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            border: 1px solid var(--border-color);
        }

        .role-badge.employee {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }

        .role-badge.accountant {
            border-color: #f59e0b;
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        .role-badge.director {
            border-color: #8b5cf6;
            background: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
        }

        .role-badge.administrator {
            border-color: #ef4444;
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .role-badge .remove-btn {
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            padding: 0;
            margin-left: 4px;
            font-size: 14px;
            transition: transform 0.2s ease;
        }

        .role-badge .remove-btn:hover {
            transform: scale(1.2);
        }

        .actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent-dark));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--border-color);
        }

        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
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
            background: var(--bg-primary);
            padding: 30px;
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
            margin-bottom: 20px;
        }

        .modal-header h2 {
            font-family: "Roboto", sans-serif;
            font-size: 24px;
            font-weight: 700;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 28px;
            cursor: pointer;
            color: var(--text-tertiary);
            transition: color 0.3s ease;
        }

        .close-btn:hover {
            color: var(--text-primary);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
        }

        .form-group select {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-family: "Roboto", sans-serif;
            font-size: 14px;
            transition: border-color 0.3s ease;
        }

        .form-group select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .modal-footer {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: var(--text-tertiary);
        }

        .no-data i {
            font-size: 48px;
            margin-bottom: 16px;
            opacity: 0.5;
        }

        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                align-items: flex-start;
            }

            .header h1 {
                font-size: 24px;
            }

            .card {
                padding: 20px;
            }

            table {
                font-size: 13px;
            }

            th, td {
                padding: 12px;
            }

            .user-info {
                flex-direction: column;
                gap: 8px;
            }

            .user-avatar {
                width: 35px;
                height: 35px;
                font-size: 14px;
            }

            .actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

    <!-- Theme Toggle -->

    <div class="container">
        <!-- Header -->
        <div class="header">
            <div>
                <div class="breadcrumb">
                    <a href="/payslip_generator/public/admin/admin_dashboard.php">Admin</a>
                    <i class="fas fa-chevron-right"></i>
                    <span>Manage User Roles</span>
                </div>
                <h1>Manage User Roles</h1>
            </div>
        </div>

        <!-- Message -->
        <?php if ($message): ?>
            <div class="message <?= $messageType ?>">
                <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : ($messageType === 'warning' ? 'exclamation-circle' : 'times-circle') ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <!-- Users Table Card -->
        <div class="card">
            <div class="table-responsive">
                <?php if (count($allUsers) > 0): ?>
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Current Roles</th>
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
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Remove <?= htmlspecialchars($role['role_name']) ?> role?');">
                                                        <input type="hidden" name="user_id" value="<?= $user['user_id'] ?>">
                                                        <input type="hidden" name="role_id" value="<?= $role['role_id'] ?>">
                                                        <input type="hidden" name="action" value="remove">
                                                        <button type="submit" class="role-badge <?= htmlspecialchars($role['role_name']) ?>">
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
                                                <span class="role-badge">No roles assigned</span>
                                                <?php
                                            endif;
                                            ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="actions">
                                            <button class="btn btn-primary btn-small" onclick="openAssignModal(<?= $user['user_id'] ?>, '<?= htmlspecialchars($user['username']) ?>')">
                                                <i class="fas fa-plus"></i> Assign Role
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <div class="no-data">
                        <i class="fas fa-users"></i>
                        <p>No users found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Assign Role Modal -->
    <div id="assignModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Assign Role</h2>
                <button class="close-btn" onclick="closeAssignModal()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="user_id" id="modalUserId">
                <input type="hidden" name="action" value="assign">

                <div class="form-group">
                    <label>User: <strong id="modalUserName"></strong></label>
                </div>

                <div class="form-group">
                    <label for="modalRoleId">Select Role:</label>
                    <select id="modalRoleId" name="role_id" required>
                        <option value="">-- Choose a role --</option>
                        <?php foreach ($allRoles as $role): ?>
                            <option value="<?= $role['role_id'] ?>">
                                <?= htmlspecialchars(ucfirst($role['role_name'])) ?> - <?= htmlspecialchars($role['description']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeAssignModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Role</button>
                </div>
            </form>
        </div>
    </div>

</body>
</html>
