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
$stmt = $conn->query("SELECT COUNT(*) as count FROM employees");
$totalEmployees = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->query("SELECT COUNT(*) as count FROM employees WHERE status = 'active'");
$activeEmployees = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->query("SELECT COUNT(*) as count FROM departments");
$totalDepartments = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
$activeUsers = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

// Recent Employees (last 5)
$stmt = $conn->query("SELECT e.*, d.department_name FROM employees e 
                      LEFT JOIN departments d ON e.department_id = d.department_id 
                      ORDER BY e.created_at DESC LIMIT 5");
$recentEmployees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Department wise employee count
$stmt = $conn->query("SELECT d.department_name, COUNT(e.employee_id) as count 
                      FROM departments d 
                      LEFT JOIN employees e ON d.department_id = e.department_id 
                      GROUP BY d.department_id, d.department_name 
                      ORDER BY count DESC LIMIT 5");
$departmentStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get max count for progress bar calculation
$maxCount = !empty($departmentStats) ? max(array_column($departmentStats, 'count')) : 1;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Payroll System</title>
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
            --gradient-blue: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            --gradient-green: linear-gradient(135deg, #10b981 0%, #059669 100%);
            --gradient-orange: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
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
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .theme-toggle:hover {
            transform: translateY(-2px);
        }

        .theme-toggle i {
            font-size: 18px;
            color: var(--text-primary);
        }

        .dashboard-header {
            margin-bottom: 30px;
        }

        .dashboard-header h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 32px;
            margin-bottom: 8px;
            color: var(--text-primary);
        }

        .dashboard-header p {
            color: var(--text-tertiary);
            font-size: 16px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--bg-primary);
            border-radius: 16px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
        }

        .stat-card.purple::before { background: var(--gradient-primary); }
        .stat-card.blue::before { background: var(--gradient-blue); }
        .stat-card.green::before { background: var(--gradient-green); }
        .stat-card.orange::before { background: var(--gradient-orange); }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .stat-card.purple .stat-icon { background: var(--gradient-primary); }
        .stat-card.blue .stat-icon { background: var(--gradient-blue); }
        .stat-card.green .stat-icon { background: var(--gradient-green); }
        .stat-card.orange .stat-icon { background: var(--gradient-orange); }

        .stat-value {
            font-size: 36px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: var(--text-tertiary);
            font-weight: 500;
        }

        .content-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
            gap: 24px;
        }

        .card {
            background: var(--bg-primary);
            border-radius: 16px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border-color);
        }

        .card-header h3 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 20px;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-link {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .card-link:hover {
            color: #764ba2;
        }

        .employee-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }

        .employee-item:hover {
            background: var(--bg-secondary);
        }

        .employee-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .employee-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--gradient-primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
        }

        .employee-details {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .employee-name {
            font-weight: 600;
            color: var(--text-primary);
        }

        .employee-dept {
            font-size: 13px;
            color: var(--text-tertiary);
        }

        .dept-bar {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
        }

        .dept-bar:last-child {
            border-bottom: none;
        }

        .dept-name {
            flex: 1;
            font-weight: 500;
            color: var(--text-primary);
        }

        .dept-progress {
            flex: 2;
            height: 8px;
            background: var(--bg-secondary);
            border-radius: 10px;
            overflow: hidden;
        }

        .dept-progress-fill {
            height: 100%;
            background: var(--gradient-primary);
            border-radius: 10px;
            transition: width 0.5s ease;
        }

        .dept-count {
            font-weight: 600;
            color: var(--text-primary);
            min-width: 40px;
            text-align: right;
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .theme-toggle {
                top: 10px;
                right: 10px;
            }
        }
    </style>
</head>
<body>

    <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
        <i class="fas fa-moon" id="themeIcon"></i>
    </button>

    <?php include 'includes/admin_navbar.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="dashboard-header">
            <h1><i class="fas fa-chart-line"></i> Dashboard</h1>
            <p>Welcome back, <?php echo htmlspecialchars($username); ?>! Here's what's happening today.</p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card purple">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $totalEmployees; ?></div>
                        <div class="stat-label">Total Employees</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card blue">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $activeEmployees; ?></div>
                        <div class="stat-label">Active Employees</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card green">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $totalDepartments; ?></div>
                        <div class="stat-label">Departments</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-building"></i>
                    </div>
                </div>
            </div>

            <div class="stat-card orange">
                <div class="stat-header">
                    <div>
                        <div class="stat-value"><?php echo $activeUsers; ?></div>
                        <div class="stat-label">Active Users</div>
                    </div>
                    <div class="stat-icon">
                        <i class="fas fa-user-shield"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Recent Employees -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-user-plus"></i> Recent Employees</h3>
                    <a href="employees.php" class="card-link">View All <i class="fas fa-arrow-right"></i></a>
                </div>
                <div class="employee-list">
                    <?php if (!empty($recentEmployees)): ?>
                        <?php foreach ($recentEmployees as $emp): ?>
                            <div class="employee-item">
                                <div class="employee-info">
                                    <div class="employee-avatar">
                                        <?php echo strtoupper(substr($emp['full_name'], 0, 2)); ?>
                                    </div>
                                    <div class="employee-details">
                                        <div class="employee-name"><?php echo htmlspecialchars($emp['full_name']); ?></div>
                                        <div class="employee-dept"><?php echo htmlspecialchars($emp['department_name'] ?? 'N/A'); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: var(--text-tertiary); padding: 20px;">No recent employees found.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Department Statistics -->
            <div class="card">
                <div class="card-header">
                    <h3><i class="fas fa-chart-bar"></i> Department Overview</h3>
                    <a href="departments.php" class="card-link">Manage <i class="fas fa-cog"></i></a>
                </div>
                <div class="dept-stats">
                    <?php if (!empty($departmentStats)): ?>
                        <?php foreach ($departmentStats as $dept): ?>
                            <div class="dept-bar">
                                <div class="dept-name"><?php echo htmlspecialchars($dept['department_name']); ?></div>
                                <div class="dept-progress">
                                    <div class="dept-progress-fill" style="width: <?php echo $maxCount > 0 ? ($dept['count'] / $maxCount * 100) : 0; ?>%"></div>
                                </div>
                                <div class="dept-count"><?php echo $dept['count']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p style="text-align: center; color: var(--text-tertiary); padding: 20px;">No department data available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Theme Toggle Functionality
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const html = document.documentElement;

        const savedTheme = localStorage.getItem('adminTheme') || 'light';
        html.setAttribute('data-theme', savedTheme);
        updateThemeIcon(savedTheme);

        themeToggle.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('adminTheme', newTheme);
            updateThemeIcon(newTheme);
        });

        function updateThemeIcon(theme) {
            if (theme === 'dark') {
                themeIcon.classList.remove('fa-moon');
                themeIcon.classList.add('fa-sun');
            } else {
                themeIcon.classList.remove('fa-sun');
                themeIcon.classList.add('fa-moon');
            }
        }
    </script>

</body>
</html>
