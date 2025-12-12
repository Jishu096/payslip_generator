<?php
session_start();

// Support both single-role and multi-role scenarios
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? null];
$hasEmployeeRole = in_array('employee', $userRoles);

if (!isset($_SESSION['role']) || (!$hasEmployeeRole && $_SESSION['role'] !== 'employee')) {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Employee';

require_once "../../app/Models/Attendance.php";
$attendance = new Attendance();
$rows = $attendance->getAttendanceByEmployee($_SESSION['employee_id']);

// Calculate summary
$totalDays = count($rows);
$presentDays = 0;
$absentDays = 0;
$leaveDays = 0;

foreach ($rows as $r) {
    $status = strtolower($r['status'] ?? '');
    if ($status === 'present') $presentDays++;
    elseif ($status === 'absent') $absentDays++;
    elseif ($status === 'leave') $leaveDays++;
}

$attendancePercentage = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 1) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance - Payroll System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            --color-present: #10b981;
            --color-absent: #ef4444;
            --color-leave: #f59e0b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Manrope', sans-serif;
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            transition: all 0.3s ease;
        }

        .attendance-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .attendance-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .attendance-header h1 {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 32px;
            font-weight: 700;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .header-controls {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .back-btn {
            padding: 10px 18px;
            background-color: var(--bg-tertiary);
            color: var(--text-primary);
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .back-btn:hover {
            transform: translateX(-2px);
            box-shadow: var(--card-shadow);
        }

        /* Summary Cards */
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background-color: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 24px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
        }

        .summary-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }

        .summary-card.gradient {
            background: var(--gradient-primary);
            color: white;
            border: none;
        }

        .summary-label {
            font-size: 14px;
            color: var(--text-tertiary);
            font-weight: 500;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .summary-card.gradient .summary-label {
            color: rgba(255,255,255,0.8);
        }

        .summary-value {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .summary-subtext {
            font-size: 13px;
            opacity: 0.7;
        }

        /* Attendance Table */
        .attendance-card {
            background-color: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 30px;
            box-shadow: var(--card-shadow);
        }

        .card-title {
            font-family: 'Space Grotesk', sans-serif;
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--text-primary);
        }

        .card-title i {
            font-size: 24px;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-tertiary);
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 20px;
            margin-bottom: 10px;
            color: var(--text-secondary);
        }

        .attendance-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .attendance-table thead {
            background-color: var(--bg-secondary);
            border-bottom: 2px solid var(--border-color);
        }

        .attendance-table th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            color: var(--text-primary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .attendance-table td {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }

        .attendance-table tbody tr:hover {
            background-color: var(--bg-secondary);
        }

        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-present {
            background-color: #dcfce7;
            color: #166534;
        }


        .status-absent {
            background-color: #fee2e2;
            color: #991b1b;
        }


        .status-leave {
            background-color: #fef3c7;
            color: #92400e;
        }


        .date-text {
            color: var(--text-secondary);
            font-size: 13px;
        }

        @media (max-width: 768px) {
            .attendance-container {
                padding: 20px 15px;
            }

            .attendance-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .attendance-header h1 {
                font-size: 24px;
            }

            .summary-grid {
                grid-template-columns: 1fr;
            }

            .attendance-card {
                padding: 20px;
            }

            .attendance-table {
                font-size: 12px;
            }

            .attendance-table th,
            .attendance-table td {
                padding: 10px 8px;
            }
        }
    </style>
</head>
<body>
    <div class="attendance-container">
        <!-- Header -->
        <div class="attendance-header">
            <div>
                <h1><i class="fas fa-calendar-check"></i> My Attendance</h1>
            </div>
            <div class="header-controls">
                <a href="dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="summary-grid">
            <div class="summary-card gradient">
                <div class="summary-label">Attendance Rate</div>
                <div class="summary-value"><?= $attendancePercentage ?>%</div>
                <div class="summary-subtext">Overall attendance</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Present Days</div>
                <div class="summary-value" style="color: var(--color-present);"><?= $presentDays ?></div>
                <div class="summary-subtext">Total present</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Absent Days</div>
                <div class="summary-value" style="color: var(--color-absent);"><?= $absentDays ?></div>
                <div class="summary-subtext">Total absent</div>
            </div>
            <div class="summary-card">
                <div class="summary-label">Leave Days</div>
                <div class="summary-value" style="color: var(--color-leave);"><?= $leaveDays ?></div>
                <div class="summary-subtext">Total leaves</div>
            </div>
        </div>

        <!-- Attendance Records -->
        <div class="attendance-card">
            <div class="card-title">
                <i class="fas fa-list"></i> Attendance Records
            </div>

            <?php if (empty($rows)): ?>
                <div class="empty-state">
                    <i class="fas fa-calendar-times"></i>
                    <h3>No Attendance Records</h3>
                    <p>Your attendance records will appear here once they are marked.</p>
                </div>
            <?php else: ?>
                <table class="attendance-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Day</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rows as $r): 
                            $date = $r['date'] ?? '';
                            $dayName = date('l', strtotime($date));
                            $formattedDate = date('M d, Y', strtotime($date));
                            $status = strtolower($r['status'] ?? 'unknown');
                        ?>
                        <tr>
                            <td>
                                <span class="date-text">
                                    <i class="far fa-calendar"></i> <?= $formattedDate ?>
                                </span>
                            </td>
                            <td><?= $dayName ?></td>
                            <td>
                                <?php if ($status === 'present'): ?>
                                    <span class="status-badge status-present">
                                        <i class="fas fa-check"></i> Present
                                    </span>
                                <?php elseif ($status === 'absent'): ?>
                                    <span class="status-badge status-absent">
                                        <i class="fas fa-times"></i> Absent
                                    </span>
                                <?php elseif ($status === 'leave'): ?>
                                    <span class="status-badge status-leave">
                                        <i class="fas fa-umbrella-beach"></i> Leave
                                    </span>
                                <?php else: ?>
                                    <span class="status-badge" style="background: var(--bg-tertiary); color: var(--text-secondary);">
                                        <?= htmlspecialchars(ucfirst($r['status'] ?? 'Unknown')) ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <script>
    </script>
</body>
</html>