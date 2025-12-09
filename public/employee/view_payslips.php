<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employee') {
    header("Location: login.php");
    exit;
}

require_once "../../backend/models/Payslip.php";

$payslipModel = new Payslip();
$payslips = $payslipModel->getPayslipsByEmployee($_SESSION['employee_id']);
?>
<!DOCTYPE html>
<html>
<head>
    <title>My Payslips</title>
    <style>
        body { font-family: Arial; background: #f5f5f5; margin:0; }
        .header {
            background: #055483; color:white; padding:15px;
            display:flex; justify-content:space-between; align-items:center;
        }
        .container {
            width:70%; margin:40px auto; background:white; padding:20px;
            border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.1);
        }
        table {
            width:100%; border-collapse:collapse; margin-top:20px;
        }
        table, th, td {
            border:1px solid #ddd;
        }
        th, td {
            padding:12px; text-align:left;
        }
        th {
            background:#055483; color:white;
        }
        a.btn {
            background:#055483; color:white; padding:8px 12px;
            border-radius:5px; text-decoration:none;
        }
    </style>
</head>
<body>

<div class="header">
    <h2>My Payslips</h2>
    <a href="employee_dashboard.php" style="color:white;">⬅ Back</a>
</div>

<div class="container">
    <h3>Payslip History</h3>

    <?php if (empty($payslips)): ?>
        <p>No payslips available yet.</p>
    <?php else: ?>

        <table>
            <tr>
                <th>Month</th>
                <th>Year</th>
                <th>Generated On</th>
                <th>Download</th>
            </tr>

            <?php foreach ($payslips as $row): ?>
            <tr>
                <td><?= $row['month'] ?></td>
                <td><?= $row['year'] ?></td>
                <td><?= $row['generated_at'] ?></td>
                <td>
                    <a class="btn" target="_blank"
                       href="../../storage/payslips/<?= $row['file_path'] ?>">
                       Download PDF
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>

        </table>

    <?php endif; ?>
</div>

</body>
</html>
