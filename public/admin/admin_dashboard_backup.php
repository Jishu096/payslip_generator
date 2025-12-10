<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrator') {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Admin';

// Database connection
require_once __DIR__ . '/../../app/Config/database.php';
$db = new Database();
$conn = $db->connect();

// Fetch real statistics
// Total Employees
$stmt = $conn->query("SELECT COUNT(*) as count FROM employees");
$totalEmployees = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total Departments
$stmt = $conn->query("SELECT COUNT(*) as count FROM departments");
$totalDepartments = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Active Users
$stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
$activeUsers = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Total Users
$stmt = $conn->query("SELECT COUNT(*) as count FROM users");
$totalUsers = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Recent Employees (last 5)
$stmt = $conn->query("SELECT e.*, d.department_name FROM employees e 
                      LEFT JOIN departments d ON e.department_id = d.department_id 
                      ORDER BY e.created_at DESC LIMIT 5");
$recentEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Department wise employee count
$stmt = $conn->query("SELECT d.department_name, COUNT(e.employee_id) as count 
                      FROM departments d 
                      LEFT JOIN employees e ON d.department_id = e.department_id 
                      GROUP BY d.department_id 
                      ORDER BY count DESC LIMIT 5");
$departmentStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php 
        $stmtTitle = $conn->query("SELECT setting_value FROM settings WHERE setting_key = 'company_name'");
        $companyTitle = $stmtTitle->fetch(PDO::FETCH_ASSOC);
        echo htmlspecialchars($companyTitle['setting_value'] ?? 'Enterprise Payroll Solutions');
    ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f7fa;
            overflow-x: hidden;
        }

        /* Top Navbar */
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 70px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 20px;
            font-weight: 600;
        }

        .navbar-brand i {
            font-size: 28px;
        }

        .navbar-toggle {
            font-size: 24px;
            cursor: pointer;
            display: none;
        }

        .navbar-right {
            display: flex;
            align-items: center;
            gap: 25px;
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
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .logout-btn {
            background: rgba(255,255,255,0.2);
            padding: 8px 20px;
            border-radius: 20px;
            color: white;
            text-decoration: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .logout-btn:hover {
            background: rgba(255,255,255,0.3);
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: #2c3e50;
            position: fixed;
            left: 0;
            top: 70px;
            height: calc(100vh - 70px);
            overflow-y: auto;
            transition: all 0.3s ease;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
        }

        .sidebar.collapsed {
            left: -260px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 20px 0;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px 25px;
            color: #ecf0f1;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(52, 152, 219, 0.2);
            border-left: 4px solid #3498db;
            padding-left: 21px;
        }

        .sidebar-menu i {
            width: 20px;
            font-size: 18px;
        }

        /* Main Content */
        .main-content {
            margin-left: 260px;
            margin-top: 70px;
            padding: 30px;
            transition: all 0.3s ease;
            min-height: calc(100vh - 70px);
        }

        .main-content.expanded {
            margin-left: 0;
        }

        .page-header {
            margin-bottom: 30px;
        }

        .page-header h1 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .page-header p {
            color: #7f8c8d;
            font-size: 14px;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            gap: 20px;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            color: white;
        }

        .stat-icon.blue { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stat-icon.green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .stat-icon.orange { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stat-icon.purple { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }

        .stat-details h3 {
            font-size: 32px;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .stat-details p {
            color: #7f8c8d;
            font-size: 14px;
        }

        /* Action Cards */
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
        }

        .action-card {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }

        .action-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .action-card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }

        .action-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .action-card h3 {
            font-size: 18px;
            color: #2c3e50;
        }

        .action-card p {
            color: #7f8c8d;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .navbar-toggle {
                display: block;
            }

            .sidebar {
                left: -260px;
            }

            .sidebar.active {
                left: 0;
            }

            .main-content {
                margin-left: 0;
            }

            .navbar-brand span {
                display: none;
            }

            .user-info span {
                display: none;
            }

            div[style*="grid-template-columns: 1fr 1fr"] {
                grid-template-columns: 1fr !important;
            }

            div[style*="grid-template-columns: repeat(auto-fit"] {
                grid-template-columns: 1fr !important;
            }
        }
    </style>
</head>
<body>

    <!-- Top Navbar -->
    <nav class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-bars navbar-toggle" id="sidebarToggle"></i>
            <i class="fas fa-building"></i>
            <span>Enterprise Payroll Solutions</span>
        </div>
        <div class="navbar-right">
            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <span><?php echo htmlspecialchars($username); ?></span>
            </div>
            <a href="../index.php?page=logout" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </nav>

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">
        <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="employees.php"><i class="fas fa-users"></i> Employees</a></li>
            <li><a href="create_user.php"><i class="fas fa-user-plus"></i> Create User</a></li>
            <li><a href="manage_users.php"><i class="fas fa-users-cog"></i> Manage Users</a></li>
            <li><a href="departments.php"><i class="fas fa-building"></i> Departments</a></li>
            <li><a href="reports.php"><i class="fas fa-chart-bar"></i> Reports</a></li>
            <li><a href="settings.php"><i class="fas fa-cog"></i> Settings</a></li>
        </ul>
    </aside>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <div class="page-header">
            <h1><i class="fas fa-tachometer-alt"></i> Administrator Dashboard</h1>
            <p>Welcome back, <?php echo htmlspecialchars($username); ?>! Manage your organization efficiently.</p>
        </div>

        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo $totalEmployees; ?></h3>
                    <p>Total Employees</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo $activeUsers; ?></h3>
                    <p>Active Users</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-users-cog"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo $totalUsers; ?></h3>
                    <p>Total Users</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-building"></i>
                </div>
                <div class="stat-details">
                    <h3><?php echo $totalDepartments; ?></h3>
                    <p>Departments</p>
                </div>
            </div>
        </div>

        <!-- Action Cards -->
        <div class="actions-grid">
            <div class="action-card">
                <div class="action-card-header">
                    <div class="action-icon">
                        <i class="fas fa-user-plus"></i>
                    </div>
                    <h3>Add New Employee</h3>
                </div>
                <p>Create a new employee record with complete details and automatically generate login credentials.</p>
                <a href="add_employee.php" class="btn">
                    <i class="fas fa-plus"></i> Add Employee
                </a>
            </div>

            <div class="action-card">
                <div class="action-card-header">
                    <div class="action-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3>Manage Employees</h3>
                </div>
                <p>View, edit, update, or remove employee details. Access complete employee information in one place.</p>
                <a href="employees.php" class="btn">
                    <i class="fas fa-cog"></i> Manage Employees
                </a>
            </div>

            <div class="action-card">
                <div class="action-card-header">
                    <div class="action-icon">
                        <i class="fas fa-key"></i>
                    </div>
                    <h3>Create User Accounts</h3>
                </div>
                <p>Create login credentials for employees, accountants, directors, and administrators with role-based access.</p>
                <a href="create_user.php" class="btn">
                    <i class="fas fa-user-plus"></i> Create User
                </a>
            </div>

            <div class="action-card">
                <div class="action-card-header">
                    <div class="action-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <h3>Manage User Access</h3>
                </div>
                <p>Enable or disable user login access, reset passwords, and manage user permissions across the system.</p>
                <a href="manage_users.php" class="btn">
                    <i class="fas fa-users-cog"></i> Manage Users
                </a>
            </div>
        </div>

        <!-- Recent Activity Section -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-top: 30px;">
            <!-- Recent Employees -->
            <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="color: #2c3e50; font-size: 18px;"><i class="fas fa-clock"></i> Recent Employees</h3>
                    <a href="employees.php" style="color: #667eea; text-decoration: none; font-size: 14px;">View All →</a>
                </div>
                <?php if (!empty($recentEmployees)): ?>
                    <div style="display: flex; flex-direction: column; gap: 12px;">
                        <?php foreach ($recentEmployees as $emp): ?>
                            <div style="padding: 12px; background: #f8f9fa; border-radius: 8px; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div style="font-weight: 600; color: #2c3e50; margin-bottom: 4px;">
                                        <?php echo htmlspecialchars($emp['full_name']); ?>
                                    </div>
                                    <div style="font-size: 13px; color: #7f8c8d;">
                                        <?php echo htmlspecialchars($emp['designation']); ?> • 
                                        <?php echo htmlspecialchars($emp['department_name'] ?? 'N/A'); ?>
                                    </div>
                                </div>
                                <div style="font-size: 12px; color: #95a5a6;">
                                    <?php echo date('M d, Y', strtotime($emp['created_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: #7f8c8d; padding: 20px;">No employees yet</p>
                <?php endif; ?>
            </div>

            <!-- Department Statistics -->
            <div style="background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.08);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3 style="color: #2c3e50; font-size: 18px;"><i class="fas fa-chart-pie"></i> Department Overview</h3>
                    <a href="departments.php" style="color: #667eea; text-decoration: none; font-size: 14px;">View All →</a>
                </div>
                <?php if (!empty($departmentStats)): ?>
                    <div style="display: flex; flex-direction: column; gap: 15px;">
                        <?php foreach ($departmentStats as $dept): ?>
                            <div>
                                <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                                    <span style="color: #2c3e50; font-weight: 500;">
                                        <?php echo htmlspecialchars($dept['department_name']); ?>
                                    </span>
                                    <span style="color: #667eea; font-weight: 600;">
                                        <?php echo $dept['count']; ?> employees
                                    </span>
                                </div>
                                <div style="height: 8px; background: #e0e0e0; border-radius: 4px; overflow: hidden;">
                                    <div style="height: 100%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); width: <?php echo $totalEmployees > 0 ? ($dept['count'] / $totalEmployees * 100) : 0; ?>%; border-radius: 4px;"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="text-align: center; color: #7f8c8d; padding: 20px;">No departments yet</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Stats Summary -->
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; border-radius: 12px; margin-top: 30px; color: white;">
            <h3 style="margin-bottom: 20px; font-size: 20px;"><i class="fas fa-info-circle"></i> Quick Summary</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
                <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px; backdrop-filter: blur(10px);">
                    <div style="font-size: 13px; opacity: 0.9; margin-bottom: 5px;">Employee to Department Ratio</div>
                    <div style="font-size: 24px; font-weight: 600;">
                        <?php echo $totalDepartments > 0 ? number_format($totalEmployees / $totalDepartments, 1) : 0; ?>:1
                    </div>
                </div>
                <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px; backdrop-filter: blur(10px);">
                    <div style="font-size: 13px; opacity: 0.9; margin-bottom: 5px;">User Account Coverage</div>
                    <div style="font-size: 24px; font-weight: 600;">
                        <?php echo $totalEmployees > 0 ? round(($totalUsers / $totalEmployees) * 100) : 0; ?>%
                    </div>
                </div>
                <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px; backdrop-filter: blur(10px);">
                    <div style="font-size: 13px; opacity: 0.9; margin-bottom: 5px;">Active User Rate</div>
                    <div style="font-size: 24px; font-weight: 600;">
                        <?php echo $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100) : 0; ?>%
                    </div>
                </div>
                <div style="background: rgba(255,255,255,0.1); padding: 15px; border-radius: 8px; backdrop-filter: blur(10px);">
                    <div style="font-size: 13px; opacity: 0.9; margin-bottom: 5px;">System Status</div>
                    <div style="font-size: 24px; font-weight: 600;">
                        <i class="fas fa-check-circle"></i> Operational
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Sidebar Toggle
        const sidebar = document.getElementById('sidebar');
        const mainContent = document.getElementById('mainContent');
        const sidebarToggle = document.getElementById('sidebarToggle');

        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        });

        // Close sidebar on mobile when clicking outside
        document.addEventListener('click', function(event) {
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !sidebarToggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });
    </script>

</body>
</html>
