<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$baseURL = "/payslip_generator/public/hr_officer/";
?>
<aside style="position: fixed; top: 70px; left: 0; width: 280px; height: calc(100vh - 70px); background: white; box-shadow: 2px 0 15px rgba(0,0,0,0.08); overflow-y: auto; z-index: 50;">
    <nav style="padding: 25px 0;">
        <?php
        $menuItems = [
            ['icon' => 'fa-home', 'label' => 'Dashboard', 'page' => 'dashboard.php'],
            ['icon' => 'fa-check-circle', 'label' => 'Verify Attendance', 'page' => 'verify_attendance.php'],
            ['icon' => 'fa-calendar-alt', 'label' => 'Leave Management', 'page' => 'leave_management.php'],
            ['icon' => 'fa-edit', 'label' => 'Manual Entry', 'page' => 'manual_entry.php'],
            ['icon' => 'fa-users', 'label' => 'Employee Records', 'page' => 'employee_records.php']
        ];

        foreach ($menuItems as $item):
            $isActive = $currentPage === $item['page'];
            $activeStyle = $isActive 
                ? 'background: linear-gradient(135deg, #667eea, #764ba2); color: white;' 
                : 'color: #64748b;';
            $iconColor = $isActive ? 'white' : '#667eea';
        ?>
            <a href="<?php echo $baseURL . $item['page']; ?>" style="display: flex; align-items: center; gap: 15px; padding: 15px 25px; text-decoration: none; transition: all 0.3s; <?php echo $activeStyle; ?> margin: 5px 15px; border-radius: 12px;">
                <i class="fas <?php echo $item['icon']; ?>" style="font-size: 18px; color: <?php echo $iconColor; ?>; width: 24px;"></i>
                <span style="font-size: 15px; font-weight: 500;"><?php echo $item['label']; ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
</aside>
