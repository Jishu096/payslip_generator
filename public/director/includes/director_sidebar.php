<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar" id="sidebar">
    <ul class="sidebar-menu">
        <li><a href="director_dashboard.php" class="<?= $current_page == 'director_dashboard.php' ? 'active' : '' ?>"><i class="fas fa-home"></i> Dashboard</a></li>
        <li><a href="employees.php" class="<?= $current_page == 'employees.php' ? 'active' : '' ?>"><i class="fas fa-users"></i> Employee Directory</a></li>
        <li><a href="salary_approvals.php" class="<?= $current_page == 'salary_approvals.php' ? 'active' : '' ?>"><i class="fas fa-hand-holding-usd"></i> Salary Approvals</a></li>
        <li><a href="role_approvals.php" class="<?= $current_page == 'role_approvals.php' ? 'active' : '' ?>"><i class="fas fa-user-check"></i> Role Approvals</a></li>
        <li><a href="reports.php" class="<?= $current_page == 'reports.php' ? 'active' : '' ?>"><i class="fas fa-chart-line"></i> Reports</a></li>
    </ul>
</aside>
