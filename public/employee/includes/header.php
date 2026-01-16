<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = basename($_SERVER['PHP_SELF']);
$employeeName = $_SESSION['employee_name'] ?? 'Employee';
$avatarLetter = strtoupper(substr($employeeName, 0, 1));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Employee Portal' ?> - e-HRMS</title>
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets/css/employee_theme.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <i class="fas fa-cube"></i> e-HRMS Portal
            </div>
            
            <ul class="nav-links">
                <li>
                    <a href="dashboard.php" class="nav-link <?= $current_page == 'dashboard.php' ? 'active' : '' ?>">
                        <i class="fas fa-home"></i> Dashboard
                    </a>
                </li>
                <li>
                    <a href="attendance_calendar.php" class="nav-link <?= $current_page == 'attendance_calendar.php' ? 'active' : '' ?>">
                        <i class="fas fa-calendar-check"></i> Attendance
                    </a>
                </li>
                <li>
                    <a href="leave_management.php" class="nav-link <?= $current_page == 'leave_management.php' ? 'active' : '' ?>">
                        <i class="fas fa-umbrella-beach"></i> Leaves
                    </a>
                </li>
                <li>
                    <a href="view_payslips.php" class="nav-link <?= $current_page == 'view_payslips.php' ? 'active' : '' ?>">
                        <i class="fas fa-file-invoice-dollar"></i> Payslips
                    </a>
                </li>
                <li>
                    <a href="employee_profile.php" class="nav-link <?= $current_page == 'employee_profile.php' ? 'active' : '' ?>">
                        <i class="fas fa-user-circle"></i> My Profile
                    </a>
                </li>
            </ul>

            <div class="user-profile">
                <div class="avatar"><?= $avatarLetter ?></div>
                <div class="user-info">
                    <strong><?= htmlspecialchars($employeeName) ?></strong>
                    <small>Employee Account</small>
                </div>
                <a href="../auth/logout.php" style="margin-left:auto; color: #ef4444;" title="Logout">
                    <i class="fas fa-sign-out-alt"></i>
                </a>
            </div>
        </aside>

        <!-- Main Content Wrapper -->
        <main class="main-content">
            <!-- Top Header Mobile Toggle -->
            <div class="top-header">
                <div>
                    <h1 class="page-title"><?= $pageTitle ?? 'Dashboard' ?></h1>
                    <p style="color:var(--text-secondary);"><?= date('l, F j, Y') ?></p>
                </div>
                
                <!-- Helper Actions -->
                <div style="display:flex; gap:10px;">
                     <!-- Notifications or other actionable items could go here -->
                </div>
            </div>
