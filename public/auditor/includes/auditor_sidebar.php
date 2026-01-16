<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<style>
    .sidebar {
        position: fixed;
        left: 0;
        top: 70px;
        width: 260px;
        height: calc(100vh - 70px);
        background: white;
        box-shadow: 2px 0 10px rgba(0,0,0,0.08);
        overflow-y: auto;
        z-index: 998;
    }

    .sidebar-menu {
        padding: 20px 0;
    }

    .menu-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 25px;
        color: #64748b;
        text-decoration: none;
        transition: all 0.3s;
        font-weight: 500;
        border-left: 3px solid transparent;
    }

    .menu-item:hover {
        background: #f8fafc;
        color: #8b5cf6;
        border-left-color: #8b5cf6;
    }

    .menu-item.active {
        background: linear-gradient(90deg, rgba(139, 92, 246, 0.1), transparent);
        color: #8b5cf6;
        border-left-color: #8b5cf6;
    }

    .menu-item i {
        width: 20px;
        text-align: center;
    }

    @media (max-width: 768px) {
        .sidebar {
            transform: translateX(-100%);
            transition: transform 0.3s;
        }
        
        .sidebar.show {
            transform: translateX(0);
        }
    }
</style>

<aside class="sidebar">
    <nav class="sidebar-menu">
        <a href="<?php echo $baseURL; ?>auditor/dashboard.php" class="menu-item <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span>Dashboard</span>
        </a>
        <a href="<?php echo $baseURL; ?>auditor/attendance_reports.php" class="menu-item <?php echo $currentPage === 'attendance_reports.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-check"></i>
            <span>Attendance Reports</span>
        </a>
        <a href="<?php echo $baseURL; ?>auditor/payroll_reports.php" class="menu-item <?php echo $currentPage === 'payroll_reports.php' ? 'active' : ''; ?>">
            <i class="fas fa-file-invoice-dollar"></i>
            <span>Payroll Reports</span>
        </a>
        <a href="<?php echo $baseURL; ?>auditor/approval_history.php" class="menu-item <?php echo $currentPage === 'approval_history.php' ? 'active' : ''; ?>">
            <i class="fas fa-clipboard-check"></i>
            <span>Approval History</span>
        </a>
        <a href="<?php echo $baseURL; ?>auditor/audit_trail.php" class="menu-item <?php echo $currentPage === 'audit_trail.php' ? 'active' : ''; ?>">
            <i class="fas fa-list-alt"></i>
            <span>Audit Trail</span>
        </a>
    </nav>
</aside>
