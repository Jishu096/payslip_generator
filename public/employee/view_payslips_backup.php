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
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Payslips - Payroll System</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
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

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }
        }

        .back-btn:hover {
            transform: translateX(-2px);
            box-shadow: var(--card-shadow);
        }

        .payslips-card {
            background-color: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 30px;
            box-shadow: var(--card-shadow);
        }

        .card-title {
            font-family: "Roboto", sans-serif;
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
    </script>
</body>
</html>
