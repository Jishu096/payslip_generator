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
        scr.employee_name as full_name,
        NULL as email,
        NULL as department_name,
        scr.current_salary as old_salary,
        scr.new_salary,
        NULL as old_role,
        NULL as new_role
    FROM salary_change_requests scr
    
    UNION ALL
    
    SELECT 
        'role' as type,
        rcr.request_id as id,
        rcr.employee_id,
        rcr.status,
        rcr.request_date,
        rcr.employee_name as full_name,
        NULL as email,
        NULL as department_name,
        NULL as old_salary,
        NULL as new_salary,
        rcr.old_role,
        rcr.new_role
    FROM role_change_requests rcr
    
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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Approvals - Director</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --text-primary: #1a1f36;
            --text-secondary: #555;
            --text-tertiary: #7f8c8d;
            --border-color: #e0e0e0;
            --card-shadow: 0 2px 10px rgba(0,0,0,0.08);
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: "Roboto", sans-serif;
            background: var(--bg-secondary);
            color: var(--text-primary);
            transition: background 0.3s ease, color 0.3s ease;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header {
            background: var(--gradient-primary);
            color: white;
            padding: 30px;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            margin-bottom: 30px;
        }

        .header h1 {
            font-size: 28px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .header p {
            opacity: 0.9;
            font-size: 15px;
        }

        .breadcrumb {
            display: flex;
            gap: 8px;
            align-items: center;
            font-size: 14px;
            margin-top: 10px;
            opacity: 0.9;
        }

        .breadcrumb a {
            color: white;
            text-decoration: none;
            opacity: 0.8;
            transition: opacity 0.3s;
        }

        .breadcrumb a:hover {
            opacity: 1;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--bg-primary);
            border-radius: 16px;
            padding: 25px;
            box-shadow: var(--card-shadow);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }

        .stat-card .icon {
            font-size: 36px;
            width: 70px;
            height: 70px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
        }

        .stat-card.pending .icon {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }

        .stat-card.approved .icon {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .stat-card.rejected .icon {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
            color: var(--text-primary);
        }

        .stat-label {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .card {
            background: var(--bg-primary);
            border-radius: 16px;
            padding: 30px;
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
            font-family: "Roboto", sans-serif;
            font-size: 22px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
            color: var(--text-primary);
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
            padding: 18px;
            text-align: left;
            font-weight: 700;
            font-size: 13px;
            color: var(--text-secondary);
            border-bottom: 2px solid var(--border-color);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        td {
            padding: 18px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }

        tr:hover {
            background: var(--bg-secondary);
            transition: background 0.2s ease;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-pending {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }

        .badge-approved {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .badge-rejected {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }

        .badge-salary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }

        .badge-role {
            background: linear-gradient(135deg, #a855f7 0%, #9333ea 100%);
            color: white;
        }

        .type-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
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
            gap: 10px;
            color: #667eea;
            text-decoration: none;
            font-weight: 700;
            font-size: 14px;
            margin-bottom: 24px;
            padding: 10px 20px;
            border-radius: 10px;
            background: rgba(102, 126, 234, 0.05);
            transition: all 0.3s ease;
        }

        .back-btn:hover {
            transform: translateX(-4px);
            background: rgba(102, 126, 234, 0.1);
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
                                        <small style="color: var(--text-tertiary);">Employee ID: <?php echo $approval['employee_id']; ?></small>
                                    </td>
                                    <td>—</td>
                                    <td>
                                        <?php if ($approval['type'] === 'salary'): ?>
                                            <small>
                                                <?php if ($approval['old_salary']): ?>
                                                    Old: <strong><?php echo number_format($approval['old_salary'], 2); ?></strong><br>
                                                <?php endif; ?>
                                                New: <strong><?php echo number_format($approval['new_salary'], 2); ?></strong>
                                            </small>
                                        <?php else: ?>
                                            <small>
                                                <?php if ($approval['old_role']): ?>
                                                    Current: <strong><?php echo htmlspecialchars($approval['old_role']); ?></strong><br>
                                                <?php endif; ?>
                                                Requested: <strong><?php echo htmlspecialchars($approval['new_role']); ?></strong>
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

</body>
</html>
