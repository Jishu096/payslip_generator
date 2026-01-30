<?php
session_start();

// Check if user has accountant role
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role']];
$hasAccountantRole = in_array('accountant', $userRoles);

if (!isset($_SESSION['user_id']) || (!$hasAccountantRole && $_SESSION['role'] !== 'accountant')) {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Accountant';

require_once __DIR__ . '/../../app/Config/database.php';
$db = getDBConnection();

// Get finalized months
$query = "SELECT 
    DATE_FORMAT(a.date, '%M') as month,
    YEAR(a.date) as year,
    COUNT(*) as total_records,
    COUNT(DISTINCT a.date) as days_with_data,
    COUNT(DISTINCT a.employee_id) as unique_employees,
    MIN(a.date) as first_date,
    MAX(a.date) as last_date,
    SUM(CASE WHEN a.workflow_status = 'admin_finalized' THEN 1 ELSE 0 END) as finalized_records,
    afl.finalized_at,
    afl.finalized_by,
    u.username as finalized_by_user
FROM attendance a
LEFT JOIN attendance_finalization_log afl ON DATE_FORMAT(a.date, '%M') = afl.month AND YEAR(a.date) = afl.year
LEFT JOIN users u ON afl.finalized_by = u.user_id
WHERE a.workflow_status = 'admin_finalized'
GROUP BY DATE_FORMAT(a.date, '%Y-%m'), DATE_FORMAT(a.date, '%M'), YEAR(a.date), afl.finalized_at, afl.finalized_by, u.username
ORDER BY YEAR(a.date) DESC, MONTH(a.date) DESC";

$stmt = $db->query($query);
$finalizedMonths = $stmt->fetchAll(PDO::FETCH_ASSOC);

$baseURL = "/payslip_generator/public/";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finalized Attendance - Accountant</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --bg: #f8fafc;
            --card: #ffffff;
            --accent: #667eea;
            --accent-2: #764ba2;
            --text: #1e293b;
            --muted: #64748b;
            --border: #e2e8f0;
            --success: #10b981;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: var(--bg);
            color: var(--text);
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .page-header {
            margin-bottom: 40px;
        }

        .page-header h1 {
            font-size: 32px;
            margin-bottom: 10px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .months-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 25px;
        }

        .month-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: all 0.3s;
        }

        .month-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }

        .month-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border);
        }

        .month-title {
            font-size: 24px;
            font-weight: 700;
            color: var(--text);
        }

        .finalized-badge {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .month-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-item {
            text-align: center;
            padding: 15px;
            background: #f8fafc;
            border-radius: 12px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--accent);
        }

        .stat-label {
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
            margin-top: 5px;
        }

        .finalized-info {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 20px;
            padding: 10px;
            background: #f0fdf4;
            border-radius: 8px;
            border-left: 3px solid var(--success);
        }

        .month-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s;
            text-align: center;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: #f8fafc;
            color: var(--text);
            border: 2px solid var(--border);
        }

        .btn-secondary:hover {
            background: white;
            border-color: var(--accent);
            color: var(--accent);
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 64px;
            color: var(--muted);
            margin-bottom: 20px;
        }

        .empty-state h2 {
            font-size: 24px;
            color: var(--text);
            margin-bottom: 10px;
        }

        .empty-state p {
            color: var(--muted);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="page-header">
            <h1><i class="fas fa-calendar-check"></i> Finalized Attendance</h1>
            <p style="color: var(--muted);">View and download attendance records finalized by Admin</p>
        </div>

        <?php if (empty($finalizedMonths)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h2>No Finalized Attendance Yet</h2>
                <p>Admin has not finalized any attendance records. Check back later.</p>
            </div>
        <?php else: ?>
            <div class="months-grid">
                <?php foreach($finalizedMonths as $month): ?>
                    <div class="month-card">
                        <div class="month-header">
                            <div class="month-title"><?php echo $month['month'] . ' ' . $month['year']; ?></div>
                            <span class="finalized-badge">
                                <i class="fas fa-check-circle"></i> Finalized
                            </span>
                        </div>

                        <div class="month-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $month['days_with_data']; ?></div>
                                <div class="stat-label">Days</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $month['unique_employees']; ?></div>
                                <div class="stat-label">Employees</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $month['finalized_records']; ?></div>
                                <div class="stat-label">Records</div>
                            </div>
                        </div>

                        <div class="finalized-info">
                            <i class="fas fa-user-shield"></i> Finalized by: <strong><?php echo htmlspecialchars($month['finalized_by_user'] ?? 'Admin'); ?></strong><br>
                            <i class="fas fa-clock"></i> Date: <?php echo date('d M Y, h:i A', strtotime($month['finalized_at'])); ?>
                        </div>

                        <div class="month-actions">
                            <button class="btn btn-primary" onclick="viewDetails('<?php echo $month['month']; ?>', '<?php echo $month['year']; ?>')">
                                <i class="fas fa-eye"></i> View Details
                            </button>
                            <button class="btn btn-secondary" onclick="downloadCSV('<?php echo $month['month']; ?>', '<?php echo $month['year']; ?>')">
                                <i class="fas fa-download"></i> Download CSV
                            </button>
                        </div>

                        <!-- Hidden data for modal -->
                        <div style="display: none;" id="data-<?php echo $month['month'] . '-' . $month['year']; ?>">
                            <?php
                            // Fetch detailed records
                            $detailQuery = "SELECT 
                                a.date,
                                e.full_name as employee_name,
                                e.employee_code,
                                d.department_name,
                                a.status,
                                a.time_in,
                                a.time_out
                            FROM attendance a
                            JOIN employees e ON a.employee_id = e.employee_id
                            LEFT JOIN departments d ON e.department_id = d.department_id
                            WHERE DATE_FORMAT(a.date, '%M') = ? 
                            AND YEAR(a.date) = ?
                            AND a.workflow_status = 'admin_finalized'
                            ORDER BY a.date DESC, e.full_name ASC";
                            
                            $detailStmt = $db->prepare($detailQuery);
                            $detailStmt->execute([$month['month'], $month['year']]);
                            $records = $detailStmt->fetchAll(PDO::FETCH_ASSOC);
                            echo json_encode($records);
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Modal for viewing details -->
    <div id="detailsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 9999; padding: 20px; overflow: auto;">
        <div style="background: white; max-width: 1200px; margin: 50px auto; border-radius: 16px; padding: 30px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 id="modalTitle" style="margin: 0;"></h2>
                <button onclick="closeModal()" style="background: #ef4444; color: white; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-weight: 600;">
                    <i class="fas fa-times"></i> Close
                </button>
            </div>
            <div id="modalContent" style="overflow-x: auto;"></div>
        </div>
    </div>

    <script>
        function viewDetails(month, year) {
            const dataDiv = document.getElementById('data-' + month + '-' + year);
            const records = JSON.parse(dataDiv.textContent);
            
            document.getElementById('modalTitle').textContent = month + ' ' + year + ' - Attendance Details';
            
            let html = `<table style="width: 100%; border-collapse: collapse;">
                <thead style="background: #f8fafc;">
                    <tr>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Date</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Employee</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Department</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Status</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Time In</th>
                        <th style="padding: 12px; text-align: left; border-bottom: 2px solid #e2e8f0;">Time Out</th>
                    </tr>
                </thead>
                <tbody>`;
            
            records.forEach(record => {
                html += `<tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 10px;">${new Date(record.date).toLocaleDateString('en-GB', {day: '2-digit', month: 'short', year: 'numeric'})}</td>
                    <td style="padding: 10px;"><strong>${record.employee_name}</strong><br><small style="color: #64748b;">${record.employee_code}</small></td>
                    <td style="padding: 10px;">${record.department_name || 'N/A'}</td>
                    <td style="padding: 10px;"><span style="background: #dbeafe; color: #1e3a8a; padding: 4px 8px; border-radius: 6px; font-size: 12px;">${record.status}</span></td>
                    <td style="padding: 10px;">${record.time_in || '--:--'}</td>
                    <td style="padding: 10px;">${record.time_out || '--:--'}</td>
                </tr>`;
            });
            
            html += '</tbody></table>';
            document.getElementById('modalContent').innerHTML = html;
            document.getElementById('detailsModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('detailsModal').style.display = 'none';
        }

        function downloadCSV(month, year) {
            const dataDiv = document.getElementById('data-' + month + '-' + year);
            const records = JSON.parse(dataDiv.textContent);
            
            let csv = 'Date,Employee Name,Employee Code,Department,Status,Time In,Time Out\n';
            
            records.forEach(record => {
                csv += `${record.date},${record.employee_name},${record.employee_code},${record.department_name || 'N/A'},${record.status},${record.time_in || '--:--'},${record.time_out || '--:--'}\n`;
            });
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `${month}_${year}_finalized_attendance.csv`;
            link.click();
        }

        // Check for new notifications on page load
        window.addEventListener('load', function() {
            // Mark attendance notifications as read
            fetch('<?php echo $baseURL; ?>api/mark_notifications_read.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ type: 'attendance_finalized' })
            });
        });
    </script>

    <?php include 'includes/notification_popup.php'; ?>
</body>
</html>
