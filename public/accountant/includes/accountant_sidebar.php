<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: #f5f6fa;
    }

    .sidebar {
        width: 250px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        min-height: 100vh;
        padding: 20px;
        position: fixed;
        left: 0;
        top: 0;
        z-index: 1000;
    }

    .sidebar h3 {
        margin-bottom: 30px;
        font-size: 20px;
        text-align: center;
    }

    .sidebar a {
        display: block;
        color: white;
        text-decoration: none;
        padding: 12px 15px;
        margin-bottom: 10px;
        border-radius: 8px;
        transition: all 0.3s ease;
    }

    .sidebar a:hover,
    .sidebar a.active {
        background: rgba(255,255,255,0.2);
        transform: translateX(5px);
    }

    .sidebar hr {
        margin: 20px 0;
        opacity: 0.3;
        border: none;
        border-top: 1px solid rgba(255,255,255,0.3);
    }

    .logout-btn {
        background: #e74c3c;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        width: 100%;
        justify-content: center;
    }

    .logout-btn:hover {
        background: #c0392b;
    }

    .main-content {
        margin-left: 250px;
        padding: 30px;
    }

    .page-header {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        margin-bottom: 30px;
    }

    .page-header h1 {
        color: #2c3e50;
        font-size: 28px;
        margin-bottom: 5px;
    }

    .page-header p {
        color: #7f8c8d;
        font-size: 14px;
    }
</style>

<div class="sidebar">
    <h3><i class="fas fa-calculator"></i> Accountant</h3>
    <a href="accountant_dashboard.php" class="<?php echo $current_page == 'accountant_dashboard.php' ? 'active' : ''; ?>">
        <i class="fas fa-home"></i> Dashboard
    </a>
    <a href="payroll_management.php" class="<?php echo $current_page == 'payroll_management.php' ? 'active' : ''; ?>">
        <i class="fas fa-money-bill-wave"></i> Payroll Management
    </a>
    <a href="financial_reports.php" class="<?php echo $current_page == 'financial_reports.php' ? 'active' : ''; ?>">
        <i class="fas fa-chart-bar"></i> Financial Reports
    </a>
    <a href="generate_payslip.php" class="<?php echo $current_page == 'generate_payslip.php' ? 'active' : ''; ?>">
        <i class="fas fa-file-invoice-dollar"></i> Generate Payslip
    </a>
    <a href="../admin/employees.php">
        <i class="fas fa-users"></i> Employees
    </a>
    <hr>
    <a href="../auth/login.php?logout=1" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i> Logout
    </a>
</div>
