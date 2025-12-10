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

require_once "../../app/Models/Payslip.php";

$payslipModel = new Payslip();
$payslips = $payslipModel->getPayslipsByEmployee($_SESSION['employee_id']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Payslips - Payroll System</title>
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
        }

        [data-theme="dark"] {
            --bg-primary: #1a1f36;
            --bg-secondary: #252d3d;
            --bg-tertiary: #2a3350;
            --text-primary: #fffffe;
            --text-secondary: #ccc;
            --text-tertiary: #999;
            --border-color: #3d4263;
            --card-shadow: 0 4px 20px rgba(0,0,0,0.4);
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

        .payslips-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .payslips-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .payslips-header h1 {
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

        .theme-toggle {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: var(--bg-tertiary);
            border: none;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            transition: all 0.3s ease;
        }

        .theme-toggle:hover {
            transform: scale(1.1);
        }

        .payslips-card {
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

        .payslips-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .payslips-table thead {
            background-color: var(--bg-secondary);
            border-bottom: 2px solid var(--border-color);
        }

        .payslips-table th {
            padding: 16px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
            color: var(--text-primary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .payslips-table td {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
            font-size: 14px;
        }

        .payslips-table tbody tr:hover {
            background-color: var(--bg-secondary);
        }

        .month-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            background: var(--gradient-primary);
            color: white;
        }

        .btn-download {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: var(--gradient-primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .date-text {
            color: var(--text-secondary);
            font-size: 13px;
        }

        @media (max-width: 768px) {
            .payslips-container {
                padding: 20px 15px;
            }

            .payslips-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .payslips-header h1 {
                font-size: 24px;
            }

            .payslips-card {
                padding: 20px;
            }

            .payslips-table {
                font-size: 12px;
            }

            .payslips-table th,
            .payslips-table td {
                padding: 10px 8px;
            }
        }
    </style>
</head>
<body>
    <div class="payslips-container">
        <!-- Header -->
        <div class="payslips-header">
            <div>
                <h1><i class="fas fa-file-invoice"></i> My Payslips</h1>
            </div>
            <div class="header-controls">
                <a href="dashboard.php" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
                <button class="theme-toggle" id="themeToggle" title="Toggle Theme">
                    <i class="fas fa-moon"></i>
                </button>
            </div>
        </div>

        <!-- Payslips Card -->
        <div class="payslips-card">
            <div class="card-title">
                <i class="fas fa-history"></i> Payslip History
            </div>

            <?php if (empty($payslips)): ?>
                <div class="empty-state">
                    <i class="fas fa-file-invoice"></i>
                    <h3>No Payslips Available</h3>
                    <p>Your payslips will appear here once they are generated.</p>
                </div>
            <?php else: ?>
                <table class="payslips-table">
                    <thead>
                        <tr>
                            <th>Period</th>
                            <th>Generated On</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $monthNames = [
                            1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                            5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                            9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                        ];
                        
                        foreach ($payslips as $row): 
                            $monthName = $monthNames[(int)$row['month']] ?? $row['month'];
                        ?>
                        <tr>
                            <td>
                                <span class="month-badge"><?= $monthName ?> <?= $row['year'] ?></span>
                            </td>
                            <td>
                                <span class="date-text">
                                    <i class="far fa-calendar-alt"></i> 
                                    <?= date('M d, Y', strtotime($row['generated_at'])) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($row['file_path'])): ?>
                                    <a class="btn-download" href="../../storage/payslips/<?= htmlspecialchars($row['file_path']) ?>" target="_blank">
                                        <i class="fas fa-download"></i> Download PDF
                                    </a>
                                <?php else: ?>
                                    <span style="color: var(--text-tertiary);">Not available</span>
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
        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        const html = document.documentElement;

        // Load saved theme
        const savedTheme = localStorage.getItem('theme') || 'light';
        html.setAttribute('data-theme', savedTheme);
        updateThemeIcon(savedTheme);

        themeToggle.addEventListener('click', function() {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('theme', newTheme);
            updateThemeIcon(newTheme);
        });

        function updateThemeIcon(theme) {
            const icon = themeToggle.querySelector('i');
            if (theme === 'dark') {
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
            } else {
                icon.classList.remove('fa-sun');
                icon.classList.add('fa-moon');
            }
        }
    </script>
</body>
</html>
