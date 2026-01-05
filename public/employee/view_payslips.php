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

        .card-header {
            padding: 20px 25px;
            background: #f7fafc;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header h2 {
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
            margin: 0;
        }

        .card-header i {
            color: #667eea;
            font-size: 20px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f7fafc;
            padding: 18px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        tr:hover {
            background: #f7fafc;
        }

        .month-badge {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            display: inline-block;
        }

        .date-text {
            color: #718096;
            font-size: 14px;
        }

        .btn-download {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
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

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #a0aec0;
        }

        .empty-state i {
            font-size: 64px;
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 22px;
            font-weight: 600;
            color: #718096;
            margin-bottom: 10px;
        }

        .empty-state p {
            font-size: 14px;
        }

        .back-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .back-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .header h1 {
                font-size: 24px;
            }

            table {
                font-size: 13px;
            }

            th, td {
                padding: 12px;
            }

            .back-btn {
                width: 50px;
                height: 50px;
                bottom: 15px;
                right: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <span>My Payslips</span>
            </div>
            <h1><i class="fas fa-file-invoice"></i> My Payslips</h1>
        </div>

        <!-- Payslips Card -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-history"></i>
                <h2>Payslip History</h2>
            </div>

            <?php if (empty($payslips)): ?>
                <div class="empty-state">
                    <i class="fas fa-file-invoice"></i>
                    <h3>No Payslips Available</h3>
                    <p>Your payslips will appear here once they are generated.</p>
                </div>
            <?php else: ?>
                <table>
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
                                    <a class="btn-download" href="../accountant/generate_payslip_pdf.php?payslip_id=<?= $row['payslip_id'] ?>" target="_blank">
                                        <i class="fas fa-file-pdf"></i> View Payslip
                                    </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- Back Button -->
    <a href="dashboard.php" class="back-btn" title="Back to Dashboard">
        <i class="fas fa-arrow-left"></i>
    </a>
</body>
</html>
