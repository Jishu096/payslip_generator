<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrator') {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . '/../../app/Models/User.php';
$userModel = new User();
$users = $userModel->getAllUsers();

$success = isset($_GET['success']);
$created = isset($_GET['created']);
$updated = isset($_GET['updated']);
$deleted = isset($_GET['deleted']);
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Enterprise Payroll Solutions</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        .users-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            overflow: hidden;
        }

        .table-header {
            padding: 20px 25px;
            border-bottom: 1px solid #e0e0e0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .table-header h2 {
            font-size: 20px;
            color: #2c3e50;
            flex: 1;
            min-width: 200px;
        }

        .search-box {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
        }

        .search-box input {
            padding: 10px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            min-width: 200px;
            flex: 1;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
        }

        .table-wrapper {
            overflow-x: auto;
            max-width: 100%;
            -webkit-overflow-scrolling: touch;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        thead {
            background: #f8f9fa;
            position: sticky;
            top: 0;
        }

        th {
            padding: 15px 12px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            font-size: 13px;
            white-space: nowrap;
        }

        td {
            padding: 15px 12px;
            border-top: 1px solid #f0f0f0;
            color: #555;
            font-size: 14px;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
        }

        .badge-active { background: #d4edda; color: #155724; }
        .badge-inactive { background: #f8d7da; color: #721c24; }

        .role-badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
        }

        .role-employee { background: #e3f2fd; color: #1565c0; }
        .role-accountant { background: #f3e5f5; color: #6a1b9a; }
        .role-director { background: #fff3e0; color: #e65100; }
        .role-administrator { background: #fce4ec; color: #c2185b; }

        .action-btns {
            display: flex;
            gap: 8px;
            white-space: nowrap;
        }

        .btn-sm {
            padding: 6px 10px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 36px;
            height: 36px;
        }

        .btn-reset {
            background: #f39c12;
            color: white;
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
        }

        .btn-sm:hover {
            transform: translateY(-2px);
            opacity: 0.9;
        }

        .btn-sm i {
            pointer-events: none;
        }

        .add-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .add-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        @media (max-width: 1200px) {
            th, td {
                padding: 12px 8px;
                font-size: 13px;
            }
            .btn-sm {
                padding: 5px 8px;
                min-width: 32px;
                height: 32px;
            }
        }

        @media (max-width: 768px) {
            .table-header {
                flex-direction: column;
                align-items: stretch;
            }
            .table-header h2 {
                width: 100%;
                margin: 0;
            }
            .search-box {
                width: 100%;
                flex-direction: column;
            }
            .search-box input {
                width: 100%;
                min-width: unset;
            }
            .add-btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>

    <?php include 'includes/admin_navbar.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="page-header">
            <h1><i class="fas fa-users-cog"></i> User Management</h1>
            <p>Manage system users and their access levels</p>
        </div>

        <?php if ($created): ?>
            <div style="background:#e8f7ee;border:1px solid #b6e0c5;color:#1b6b3d;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                <i class="fas fa-check-circle"></i> User created successfully.
            </div>
        <?php elseif ($deleted): ?>
            <div style="background:#e8f7ee;border:1px solid #b6e0c5;color:#1b6b3d;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                <i class="fas fa-check-circle"></i> User deleted successfully.
            </div>
        <?php elseif ($success): ?>
            <div style="background:#e8f7ee;border:1px solid #b6e0c5;color:#1b6b3d;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                <i class="fas fa-check-circle"></i> Password reset to default (password123).
            </div>
        <?php elseif ($error === 'username_exists'): ?>
            <div style="background:#fff4e5;border:1px solid #ffd8a8;color:#b35c00;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                <i class="fas fa-exclamation-triangle"></i> Username already exists. Please choose a different one.
            </div>
        <?php elseif ($error === 'create_failed'): ?>
            <div style="background:#fff4e5;border:1px solid #ffd8a8;color:#b35c00;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                <i class="fas fa-exclamation-triangle"></i> Failed to create user. Please try again.
            </div>
        <?php elseif ($error === 'delete_failed'): ?>
            <div style="background:#fff4e5;border:1px solid #ffd8a8;color:#b35c00;padding:12px 16px;border-radius:8px;margin-bottom:20px;">
                <i class="fas fa-exclamation-triangle"></i> Failed to delete user. Please try again.
            </div>
        <?php endif; ?>

        <div class="users-table">
            <div class="table-header">
                <h2>All Users</h2>
                <div class="search-box">
                    <input type="text" placeholder="Search users..." id="searchInput">
                    <a href="create_user.php" class="add-btn">
                        <i class="fas fa-plus"></i> Add User
                    </a>
                </div>
            </div>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Employee ID</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($users)): ?>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($user['user_id']); ?></td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email'] ?? '—'); ?></td>
                                    <td>
                                        <span class="badge role-badge role-<?php echo htmlspecialchars($user['role']); ?>">
                                            <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['employee_id'] ?? '—'); ?></td>
                                    <td>
                                        <span class="badge <?php echo $user['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <div class="action-btns">
                                            <a class="btn-sm btn-reset" href="/payslip_generator/public/index.php?page=reset-password&id=<?php echo urlencode($user['user_id']); ?>" title="Reset to default password">
                                                <i class="fas fa-key"></i>
                                            </a>
                                            <a class="btn-sm btn-delete confirm-delete" href="/payslip_generator/public/index.php?page=delete-user&id=<?php echo urlencode($user['user_id']); ?>" data-name="<?php echo htmlspecialchars($user['username']); ?>">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align:center; padding:20px; color:#7f8c8d;">No users found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <?php include 'includes/admin_scripts.php'; ?>

    <script>
        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const table = document.querySelector('table tbody');
        const rows = table.querySelectorAll('tr');

        searchInput.addEventListener('keyup', function() {
            const query = this.value.toLowerCase();

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Delete confirmation
        document.querySelectorAll('.confirm-delete').forEach(function(link) {
            link.addEventListener('click', function(e) {
                var name = this.getAttribute('data-name') || 'this user';
                if (!confirm('Are you sure you want to delete user ' + name + '?')) {
                    e.preventDefault();
                }
            });
        });
    </script>

</body>
</html>
