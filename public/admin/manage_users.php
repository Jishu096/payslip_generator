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
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Payroll System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --bg-tertiary: #f1f3f5;
            --text-primary: #1a1f36;
            --text-secondary: #555;
            --text-tertiary: #7f8c8d;
            --border-color: #e0e0e0;
            --card-shadow: 0 2px 10px rgba(0,0,0,0.08);
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        [data-theme="dark"] {
            --bg-primary: #1a1f36;
            --bg-secondary: #232946;
            --bg-tertiary: #2d3250;
            --text-primary: #fffffe;
            --text-secondary: #b8c1ec;
            --text-tertiary: #a0a8d4;
            --border-color: #3d4263;
            --card-shadow: 0 4px 20px rgba(0,0,0,0.4);
        }

        body {
            font-family: 'Manrope', sans-serif;
            background: var(--bg-secondary);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
        }

        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            background: var(--bg-primary);
            border: 2px solid var(--border-color);
            border-radius: 50px;
            padding: 10px 15px;
            cursor: pointer;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
        }

        .theme-toggle:hover {
            transform: translateY(-2px);
        }

        .page-header h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 32px;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .page-header p {
            color: var(--text-tertiary);
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            animation: slideDown 0.3s ease;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-warning {
            background: #fff4e5;
            border: 1px solid #ffd8a8;
            color: #b35c00;
        }

        [data-theme="dark"] .alert-success {
            background: rgba(39, 174, 96, 0.15);
            border-color: rgba(39, 174, 96, 0.3);
            color: #6ee7b7;
        }

        [data-theme="dark"] .alert-warning {
            background: rgba(255, 152, 0, 0.15);
            border-color: rgba(255, 152, 0, 0.3);
            color: #ffb74d;
        }

        .users-card {
            background: var(--bg-primary);
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .card-header {
            padding: 25px 30px;
            border-bottom: 2px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }

        .card-header h2 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 22px;
            color: var(--text-primary);
        }

        .header-actions {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            padding: 12px 40px 12px 16px;
            border: 2px solid var(--border-color);
            border-radius: 10px;
            font-size: 14px;
            min-width: 280px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        .search-box input:focus {
            outline: none;
            border-color: #667eea;
        }

        .search-box i {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-tertiary);
        }

        .btn-add {
            background: var(--gradient-primary);
            color: white;
            padding: 12px 24px;
            border-radius: 10px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: var(--bg-secondary);
        }

        th {
            padding: 16px 20px;
            text-align: left;
            font-weight: 600;
            color: var(--text-primary);
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 18px 20px;
            border-top: 1px solid var(--border-color);
            color: var(--text-secondary);
            font-size: 14px;
        }

        tbody tr {
            transition: all 0.2s ease;
        }

        tbody tr:hover {
            background: var(--bg-secondary);
        }

        .badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-active { background: #d4edda; color: #155724; }
        .badge-inactive { background: #f8d7da; color: #721c24; }

        [data-theme="dark"] .badge-active { background: rgba(39, 174, 96, 0.2); color: #6ee7b7; }
        [data-theme="dark"] .badge-inactive { background: rgba(231, 76, 60, 0.2); color: #ff6b6b; }

        .role-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .role-employee { background: #e3f2fd; color: #1565c0; }
        .role-accountant { background: #f3e5f5; color: #6a1b9a; }
        .role-director { background: #fff3e0; color: #e65100; }
        .role-administrator { background: #fce4ec; color: #c2185b; }

        [data-theme="dark"] .role-employee { background: rgba(33, 150, 243, 0.2); color: #64b5f6; }
        [data-theme="dark"] .role-accountant { background: rgba(156, 39, 176, 0.2); color: #ce93d8; }
        [data-theme="dark"] .role-director { background: rgba(255, 152, 0, 0.2); color: #ffb74d; }
        [data-theme="dark"] .role-administrator { background: rgba(233, 30, 99, 0.2); color: #f48fb1; }

        .action-btns {
            display: flex;
            gap: 8px;
        }

        .btn-action {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-reset {
            background: #f39c12;
            color: white;
        }

        .btn-delete {
            background: #e74c3c;
            color: white;
        }

        .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        @media (max-width: 768px) {
            .card-header {
                flex-direction: column;
                align-items: stretch;
            }

            .header-actions {
                flex-direction: column;
                width: 100%;
            }

            .search-box input {
                width: 100%;
                min-width: unset;
            }

            .btn-add {
                width: 100%;
                justify-content: center;
            }

            table {
                font-size: 13px;
            }

            th, td {
                padding: 12px 10px;
            }
        }
    </style>
</head>
<body>

    <button class="theme-toggle" id="themeToggle">
        <i class="fas fa-moon" id="themeIcon"></i>
    </button>

    <?php include 'includes/admin_navbar.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="page-header">
            <h1><i class="fas fa-users-cog"></i> User Management</h1>
            <p>Manage system users and their access levels</p>
        </div>

        <?php if ($created): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> User created successfully.
            </div>
        <?php elseif ($deleted): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> User deleted successfully.
            </div>
        <?php elseif ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> Password reset to default (password123).
            </div>
        <?php elseif ($error): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="users-card">
            <div class="card-header">
                <h2>All Users (<?php echo count($users); ?>)</h2>
                <div class="header-actions">
                    <div class="search-box">
                        <input type="text" placeholder="Search by username, email, role..." id="searchInput">
                        <i class="fas fa-search"></i>
                    </div>
                    <a href="create_user.php" class="btn-add">
                        <i class="fas fa-plus"></i> Add User
                    </a>
                </div>
            </div>

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
                <tbody id="userTable">
                    <?php if (!empty($users)): ?>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><strong>#<?php echo htmlspecialchars($user['user_id']); ?></strong></td>
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
                                        <a class="btn-action btn-reset" href="/payslip_generator/public/index.php?page=reset-password&id=<?php echo urlencode($user['user_id']); ?>" title="Reset Password">
                                            <i class="fas fa-key"></i>
                                        </a>
                                        <a class="btn-action btn-delete confirm-delete" href="/payslip_generator/public/index.php?page=delete-user&id=<?php echo urlencode($user['user_id']); ?>" data-name="<?php echo htmlspecialchars($user['username']); ?>" title="Delete User">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" style="text-align:center; padding:40px; color:var(--text-tertiary);">
                                <i class="fas fa-users" style="font-size:48px;opacity:0.3;margin-bottom:15px;"></i><br>
                                No users found.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

    <?php include 'includes/admin_scripts.php'; ?>

    <script>
        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const html = document.documentElement;

        const savedTheme = localStorage.getItem('adminTheme') || 'light';
        html.setAttribute('data-theme', savedTheme);
        themeIcon.className = savedTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';

        themeToggle.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('adminTheme', newTheme);
            themeIcon.className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        });

        // Search functionality
        const searchInput = document.getElementById('searchInput');
        const table = document.getElementById('userTable');
        const rows = table.querySelectorAll('tr');

        searchInput.addEventListener('keyup', function() {
            const query = this.value.toLowerCase();
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(query) ? '' : 'none';
            });
        });

        // Delete confirmation
        document.querySelectorAll('.confirm-delete').forEach(link => {
            link.addEventListener('click', function(e) {
                const name = this.getAttribute('data-name') || 'this user';
                if (!confirm(`Are you sure you want to delete user "${name}"? This action cannot be undone.`)) {
                    e.preventDefault();
                }
            });
        });
    </script>

</body>
</html>
