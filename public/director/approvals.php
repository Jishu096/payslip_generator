<?php
session_start();

// Support both single-role and multi-role scenarios
if (!isset($_SESSION['role'])) {
    header("Location: ../auth/login.php");
    exit;
}

// Check if user has director role (either primary or in all_roles)
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role']];
$hasDirectorRole = in_array('director', $userRoles);

if (!$hasDirectorRole && $_SESSION['role'] !== 'director') {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . "/../../app/Config/database.php";
$db = getDBConnection();

$username = $_SESSION['username'] ?? 'Director';

// Fetch all pending approvals (salary + role changes)
$stmt = $db->prepare("
    SELECT 
        'salary' as type,
        scr.request_id as id,
        scr.employee_id,
        scr.status,
        scr.request_date,
        e.full_name,
        e.email,
        d.department_name,
        scr.new_salary,
        scr.old_salary,
        NULL as requested_role,
        NULL as current_role
    FROM salary_change_requests scr
    JOIN employees e ON scr.employee_id = e.employee_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    
    UNION ALL
    
    SELECT 
        'role' as type,
        rcr.role_change_request_id as id,
        rcr.employee_id,
        rcr.status,
        rcr.request_date,
        e.full_name,
        e.email,
        d.department_name,
        NULL as new_salary,
        NULL as old_salary,
        rcr.requested_role,
        rcr.current_role
    FROM role_change_requests rcr
    JOIN employees e ON rcr.employee_id = e.employee_id
    LEFT JOIN departments d ON e.department_id = d.department_id
    
    ORDER BY status ASC, request_date DESC
");
$stmt->execute();
$allApprovals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count by status
$pendingCount = 0;
$approvedCount = 0;
$rejectedCount = 0;

foreach ($allApprovals as $approval) {
    if ($approval['status'] === 'pending') $pendingCount++;
    elseif ($approval['status'] === 'approved') $approvedCount++;
    elseif ($approval['status'] === 'rejected') $rejectedCount++;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Approvals - Director</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root[data-theme="light"] {
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --text-primary: #1a1f36;
            --text-secondary: #555;
            --text-tertiary: #7f8c8d;
            --border-color: #e0e0e0;
            --card-shadow: 0 2px 10px rgba(0,0,0,0.08);
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        :root[data-theme="dark"] {
            --bg-primary: #1a1f36;
            --bg-secondary: #232946;
            --text-primary: #fffffe;
            --text-secondary: #b8c1ec;
            --text-tertiary: #a0a8d4;
            --border-color: #3d4263;
            --card-shadow: 0 4px 20px rgba(0,0,0,0.4);
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .header {
            margin-bottom: 30px;
        }

        .header h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 32px;
            margin-bottom: 8px;
        }

        .header p {
            color: var(--text-tertiary);
            font-size: 16px;
        }

        .breadcrumb {
            display: flex;
            gap: 8px;
            align-items: center;
            font-size: 14px;
            color: var(--text-tertiary);
            margin-bottom: 20px;
        }

        .breadcrumb a {
            color: #667eea;
            text-decoration: none;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--bg-primary);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--card-shadow);
            border-left: 4px solid #667eea;
            text-align: center;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 14px;
            color: var(--text-tertiary);
        }

        .card {
            background: var(--bg-primary);
            border-radius: 12px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
            margin-bottom: 24px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border-color);
        }

        .card-header h2 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
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
        }

        td {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
        }

        tr:hover {
            background: var(--bg-secondary);
        }

        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-pending {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
        }

        .badge-approved {
            background: rgba(16, 185, 129, 0.1);
            color: #10b981;
        }

        .badge-rejected {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }

        .badge-salary {
            background: rgba(59, 130, 246, 0.1);
            color: #3b82f6;
        }

        .badge-role {
            background: rgba(168, 85, 247, 0.1);
            color: #a855f7;
        }

        .type-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
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

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            transform: translateX(-3px);
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 24px;
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }

            table {
                font-size: 13px;
            }

            th, td {
                padding: 12px;
            }
        }
    </style>
</head>
<body>

    <!-- Theme Toggle -->
    <button class="theme-toggle" id="themeToggle">
        <i class="fas fa-moon" id="themeIcon"></i>
    </button>

    <div class="container">
        <a href="director_dashboard.php" class="back-btn">
            <i class="fas fa-arrow-left"></i> Back to Dashboard
        </a>

        <div class="header">
            <h1><i class="fas fa-check-square"></i> All Approvals</h1>
            <p>View all salary and role change requests</p>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value" style="color: #f59e0b;"><?php echo $pendingCount; ?></div>
                <div class="stat-label"><i class="fas fa-clock"></i> Pending</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #10b981;"><?php echo $approvedCount; ?></div>
                <div class="stat-label"><i class="fas fa-check"></i> Approved</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" style="color: #ef4444;"><?php echo $rejectedCount; ?></div>
                <div class="stat-label"><i class="fas fa-times"></i> Rejected</div>
            </div>
        </div>

        <!-- Approvals Table -->
        <div class="card">
            <div class="card-header">
                <h2><i class="fas fa-list"></i> Request History</h2>
            </div>

            <?php if (count($allApprovals) > 0): ?>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Details</th>
                                <th>Status</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allApprovals as $approval): ?>
                                <tr>
                                    <td>
                                        <span class="type-badge <?php echo $approval['type']; ?>-type">
                                            <i class="fas fa-<?php echo $approval['type'] === 'salary' ? 'money-bill' : 'user'; ?>"></i>
                                            <?php echo ucfirst($approval['type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($approval['full_name']); ?></strong><br>
                                        <small style="color: var(--text-tertiary);"><?php echo htmlspecialchars($approval['email']); ?></small>
                                    </td>
                                    <td><?php echo htmlspecialchars($approval['department_name'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if ($approval['type'] === 'salary'): ?>
                                            <small>
                                                Old: <strong><?php echo number_format($approval['old_salary'], 2); ?></strong><br>
                                                New: <strong><?php echo number_format($approval['new_salary'], 2); ?></strong>
                                            </small>
                                        <?php else: ?>
                                            <small>
                                                Current: <strong><?php echo htmlspecialchars($approval['current_role']); ?></strong><br>
                                                Requested: <strong><?php echo htmlspecialchars($approval['requested_role']); ?></strong>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $approval['status']; ?>">
                                            <?php echo ucfirst($approval['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small><?php echo date('M d, Y', strtotime($approval['request_date'])); ?></small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <i class="fas fa-inbox"></i>
                    <p>No approvals found</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const html = document.documentElement;

        const savedTheme = localStorage.getItem('theme') || 'light';
        html.setAttribute('data-theme', savedTheme);
        themeIcon.className = savedTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';

        themeToggle.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            themeIcon.className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
        });
    </script>

</body>
</html>
