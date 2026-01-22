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
    <?php include 'includes/employee_styles.php'; ?>
    <style>
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
    </style>
</head>
<body>
    <?php include 'includes/employee_navbar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Page Header -->
        <div class="page-header">
            <div class="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <span>My Payslips</span>
            </div>
            <h1><i class="fas fa-file-invoice-dollar"></i> My Payslips</h1>
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
                <div class="table-wrapper">
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
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'includes/employee_scripts.php'; ?>
</body>
</html>
