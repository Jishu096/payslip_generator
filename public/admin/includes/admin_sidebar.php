<?php
$current_page = basename($_SERVER['PHP_SELF']);
$attendance_pages = ['manage_attendance.php', 'add_attendance_record.php', 'manage_records.php', 'attendance_statement.php', 'attendance_reports.php', 'attendance_calendar.php', 'leave_approvals.php'];
$is_attendance_active = in_array($current_page, $attendance_pages);
?>
<aside class="sidebar" id="sidebar">
    <ul class="sidebar-menu">
        <li><a href="admin_dashboard.php" class="<?php echo $current_page == 'admin_dashboard.php' ? 'active' : ''; ?>"><i class="fas fa-home"></i> Dashboard</a></li>
        <li><a href="employees.php" class="<?php echo $current_page == 'employees.php' ? 'active' : ''; ?>"><i class="fas fa-users"></i> Employees</a></li>
        <li><a href="create_user.php" class="<?php echo $current_page == 'create_user.php' ? 'active' : ''; ?>"><i class="fas fa-user-plus"></i> Create User</a></li>
        <li><a href="manage_users.php" class="<?php echo $current_page == 'manage_users.php' ? 'active' : ''; ?>"><i class="fas fa-users-cog"></i> Manage Users</a></li>
        <li><a href="manage_user_roles.php" class="<?php echo $current_page == 'manage_user_roles.php' ? 'active' : ''; ?>"><i class="fas fa-user-shield"></i> User Roles</a></li>
        <li><a href="departments.php" class="<?php echo $current_page == 'departments.php' ? 'active' : ''; ?>"><i class="fas fa-building"></i> Departments</a></li>
        
        <!-- Attendance Submenu -->
        <li class="has-submenu <?php echo $is_attendance_active ? 'open' : ''; ?>">
            <a href="#" onclick="toggleSubmenu(event, this)" class="<?php echo $is_attendance_active ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i> Attendance
                <i class="fas fa-chevron-down submenu-icon"></i>
            </a>
            <ul class="submenu">
                <li><a href="manage_attendance.php" class="<?php echo $current_page == 'manage_attendance.php' ? 'active' : ''; ?>">Mark Attendance</a></li>
                <li><a href="add_attendance_record.php" class="<?php echo $current_page == 'add_attendance_record.php' ? 'active' : ''; ?>">Add Record</a></li>
                <li><a href="manage_records.php" class="<?php echo $current_page == 'manage_records.php' ? 'active' : ''; ?>">Manage Records</a></li>
                <li><a href="attendance_statement.php" class="<?php echo $current_page == 'attendance_statement.php' ? 'active' : ''; ?>">Attendance Statement</a></li>
                <li><a href="attendance_reports.php" class="<?php echo $current_page == 'attendance_reports.php' ? 'active' : ''; ?>">Reports</a></li>
                <li><a href="attendance_calendar.php" class="<?php echo $current_page == 'attendance_calendar.php' ? 'active' : ''; ?>">Calendar</a></li>
                <li><a href="leave_approvals.php" class="<?php echo $current_page == 'leave_approvals.php' ? 'active' : ''; ?>">Leave Approvals</a></li>
            </ul>
        </li>
        
        <li><a href="reports.php" class="<?php echo $current_page == 'reports.php' ? 'active' : ''; ?>"><i class="fas fa-chart-bar"></i> Reports</a></li>
        <li><a href="settings.php" class="<?php echo $current_page == 'settings.php' ? 'active' : ''; ?>"><i class="fas fa-cog"></i> Settings</a></li>
    </ul>
</aside>

<script>
function toggleSubmenu(event, element) {
    event.preventDefault();
    const parentLi = element.parentElement;
    parentLi.classList.toggle('open');
}
</script>
