<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar" id="sidebar">
    <ul class="sidebar-menu">
        <li><a href="accountant_dashboard.php" class="<?= $current_page == 'accountant_dashboard.php' ? 'active' : '' ?>"><i class="fas fa-home"></i> Dashboard</a></li>
        <li><a href="generate_payslip.php" class="<?= $current_page == 'generate_payslip.php' ? 'active' : '' ?>"><i class="fas fa-file-invoice-dollar"></i> Generate Payslip</a></li>
        <li><a href="payroll_management.php" class="<?= $current_page == 'payroll_management.php' ? 'active' : '' ?>"><i class="fas fa-money-bill-wave"></i> Manage Payroll</a></li>
        <li><a href="financial_reports.php" class="<?= $current_page == 'financial_reports.php' ? 'active' : '' ?>"><i class="fas fa-chart-pie"></i> Reports</a></li>
        <li><a href="../admin/employees.php"><i class="fas fa-users"></i> Employee Directory</a></li>
        <!-- Divider if needed -->
    </ul>
</aside>
