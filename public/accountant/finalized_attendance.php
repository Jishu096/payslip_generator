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

// Count total stats
$totalEmployees = 0;
$totalRecords = 0;
$totalMonths = count($finalizedMonths);
foreach ($finalizedMonths as $m) {
    $totalEmployees = max($totalEmployees, $m['unique_employees']);
    $totalRecords += $m['finalized_records'];
}

$baseURL = "/payslip_generator/public/";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>Finalized Attendance - Accountant Portal</title>
    <?php include 'includes/accountant_styles.php'; ?>
    <style>
        /* Page Specific Styles */
        .page-wrapper {
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
        }
        
        .main-content {
            margin-left: 260px;
            padding: 0;
        }
        
        /* Hero Section */
        .page-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 40px 100px;
            position: relative;
            overflow: hidden;
        }
        
        .page-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            pointer-events: none;
        }
        
        .page-hero::after {
            content: '';
            position: absolute;
            bottom: -50%;
            left: -10%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(255,255,255,0.08) 0%, transparent 70%);
            pointer-events: none;
        }
        
        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .hero-content h1 {
            font-size: 32px;
            font-weight: 700;
            color: white;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .hero-content h1 i {
            font-size: 28px;
            opacity: 0.9;
        }
        
        .hero-content p {
            color: rgba(255,255,255,0.85);
            font-size: 15px;
        }
        
        /* Stats Cards in Hero */
        .hero-stats {
            display: flex;
            gap: 20px;
            margin-top: 25px;
        }
        
        .hero-stat {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 16px;
            padding: 20px 28px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .hero-stat-icon {
            width: 50px;
            height: 50px;
            background: rgba(255,255,255,0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
        }
        
        .hero-stat-text h3 {
            font-size: 26px;
            font-weight: 700;
            color: white;
        }
        
        .hero-stat-text p {
            font-size: 12px;
            color: rgba(255,255,255,0.75);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Content Area */
        .content-area {
            max-width: 1400px;
            margin: -60px auto 40px;
            padding: 0 40px;
            position: relative;
            z-index: 2;
        }
        
        /* Filter Bar */
        .filter-bar {
            background: white;
            border-radius: 16px;
            padding: 20px 25px;
            margin-bottom: 25px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
        }
        
        .filter-bar-left {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .filter-bar-left i {
            font-size: 20px;
            color: var(--accent);
        }
        
        .filter-bar-left span {
            font-weight: 600;
            color: var(--text);
        }
        
        .filter-bar-right {
            display: flex;
            gap: 12px;
        }
        
        .filter-select {
            padding: 10px 16px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
            font-family: inherit;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .filter-select:focus {
            outline: none;
            border-color: var(--accent);
        }
        
        /* Months Grid */
        .months-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .month-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        
        .month-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.18);
        }
        
        .month-card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 16px 18px;
            position: relative;
            overflow: hidden;
        }
        
        .month-card-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -30%;
            width: 150px;
            height: 150px;
            background: radial-gradient(circle, rgba(255,255,255,0.15) 0%, transparent 70%);
        }
        
        .month-year {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            z-index: 1;
        }
        
        .month-name {
            font-size: 20px;
            font-weight: 700;
            color: white;
            line-height: 1.2;
        }
        
        .month-name span {
            display: block;
            font-size: 12px;
            font-weight: 500;
            opacity: 0.85;
            margin-top: 2px;
        }
        
        .finalized-badge {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            color: white;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .finalized-badge i {
            color: #4ade80;
            font-size: 10px;
        }
        
        .month-card-body {
            padding: 16px 18px;
        }
        
        .month-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 12px;
        }
        
        .stat-item {
            text-align: center;
            padding: 12px 8px;
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            transition: all 0.3s;
        }
        
        .stat-item:hover {
            border-color: var(--accent);
            background: linear-gradient(135deg, #eff6ff 0%, #e0e7ff 100%);
        }
        
        .stat-value {
            font-size: 20px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-label {
            font-size: 10px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 2px;
            font-weight: 600;
        }
        
        .finalized-info {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border: 1px solid #bbf7d0;
            border-radius: 10px;
            padding: 10px 12px;
            margin-bottom: 12px;
        }
        
        .finalized-info-row {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 11px;
            color: #166534;
        }
        
        .finalized-info-row:first-child {
            margin-bottom: 5px;
        }
        
        .finalized-info-row i {
            width: 14px;
            text-align: center;
            color: #22c55e;
            font-size: 11px;
        }
        
        .finalized-info-row strong {
            color: #14532d;
        }
        
        .month-actions {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
        }
        
        .btn {
            padding: 10px 14px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 12px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            text-align: center;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }
        
        .btn i {
            font-size: 11px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: white;
            color: var(--text);
            border: 2px solid var(--border);
        }
        
        .btn-secondary:hover {
            border-color: var(--accent);
            color: var(--accent);
            background: #f5f3ff;
        }
        
        /* Empty State */
        .empty-state {
            background: white;
            border-radius: 20px;
            padding: 80px 40px;
            text-align: center;
            box-shadow: 0 4px 25px rgba(0,0,0,0.08);
        }
        
        .empty-state-icon {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #f1f5f9 0%, #e2e8f0 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
        }
        
        .empty-state-icon i {
            font-size: 50px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .empty-state h2 {
            font-size: 24px;
            color: var(--text);
            margin-bottom: 10px;
        }
        
        .empty-state p {
            color: var(--muted);
            font-size: 15px;
            max-width: 400px;
            margin: 0 auto;
        }
        
        /* Modal Styles */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(4px);
            z-index: 9999;
            padding: 30px;
            overflow: auto;
        }
        
        .modal-content {
            background: white;
            max-width: 1200px;
            margin: 30px auto;
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 25px 60px rgba(0,0,0,0.3);
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 25px 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h2 {
            color: white;
            font-size: 22px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .modal-close {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.3s;
        }
        
        .modal-close:hover {
            background: rgba(255,255,255,0.3);
            transform: rotate(90deg);
        }
        
        .modal-body {
            padding: 30px;
            max-height: 60vh;
            overflow-y: auto;
        }
        
        /* Table Styles */
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table thead {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            position: sticky;
            top: 0;
        }
        
        .data-table th {
            padding: 16px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--muted);
            border-bottom: 2px solid var(--border);
        }
        
        .data-table td {
            padding: 14px 15px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }
        
        .data-table tbody tr {
            transition: all 0.2s;
        }
        
        .data-table tbody tr:hover {
            background: #f8fafc;
        }
        
        .employee-cell {
            display: flex;
            flex-direction: column;
        }
        
        .employee-cell strong {
            color: var(--text);
        }
        
        .employee-cell small {
            color: var(--muted);
            font-size: 12px;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-present {
            background: #dcfce7;
            color: #166534;
        }
        
        .status-absent {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .status-leave {
            background: #fef3c7;
            color: #92400e;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .page-hero {
                padding: 30px 20px 80px;
            }
            
            .hero-content h1 {
                font-size: 24px;
            }
            
            .hero-stats {
                flex-direction: column;
            }
            
            .content-area {
                padding: 0 20px;
                margin-top: -50px;
            }
            
            .filter-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .months-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                margin: 15px;
                border-radius: 16px;
            }
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <?php include 'includes/accountant_navbar.php'; ?>
        
        <div class="main-content">
            <!-- Hero Section -->
            <div class="page-hero">
                <div class="hero-content">
                    <h1><i class="fas fa-calendar-check"></i> Finalized Attendance</h1>
                    <p>Review and download attendance records finalized by Admin for payroll processing</p>
                    
                    <div class="hero-stats">
                        <div class="hero-stat">
                            <div class="hero-stat-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="hero-stat-text">
                                <h3><?php echo $totalMonths; ?></h3>
                                <p>Finalized Months</p>
                            </div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="hero-stat-text">
                                <h3><?php echo $totalEmployees; ?></h3>
                                <p>Employees Covered</p>
                            </div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-icon">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <div class="hero-stat-text">
                                <h3><?php echo number_format($totalRecords); ?></h3>
                                <p>Total Records</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Content Area -->
            <div class="content-area">
                <?php if (!empty($finalizedMonths)): ?>
                <!-- Filter Bar -->
                <div class="filter-bar">
                    <div class="filter-bar-left">
                        <i class="fas fa-filter"></i>
                        <span><?php echo count($finalizedMonths); ?> month(s) available</span>
                    </div>
                    <div class="filter-bar-right">
                        <select class="filter-select" id="yearFilter" onchange="filterMonths()">
                            <option value="">All Years</option>
                            <?php
                            $years = array_unique(array_column($finalizedMonths, 'year'));
                            foreach ($years as $year):
                            ?>
                            <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (empty($finalizedMonths)): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-inbox"></i>
                        </div>
                        <h2>No Finalized Attendance Yet</h2>
                        <p>Admin has not finalized any attendance records. Once attendance is finalized, it will appear here for your review.</p>
                    </div>
                <?php else: ?>
                    <div class="months-grid" id="monthsGrid">
                        <?php foreach($finalizedMonths as $month): ?>
                            <div class="month-card" data-year="<?php echo $month['year']; ?>">
                                <div class="month-card-header">
                                    <div class="month-year">
                                        <div class="month-name">
                                            <?php echo $month['month']; ?>
                                            <span><?php echo $month['year']; ?></span>
                                        </div>
                                        <span class="finalized-badge">
                                            <i class="fas fa-check-circle"></i> Finalized
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="month-card-body">
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
                                        <div class="finalized-info-row">
                                            <i class="fas fa-user-shield"></i>
                                            <span>Finalized by: <strong><?php echo htmlspecialchars($month['finalized_by_user'] ?? 'Admin'); ?></strong></span>
                                        </div>
                                        <div class="finalized-info-row">
                                            <i class="fas fa-clock"></i>
                                            <span><?php echo $month['finalized_at'] ? date('d M Y, h:i A', strtotime($month['finalized_at'])) : 'Date not recorded'; ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="month-actions">
                                        <button class="btn btn-primary" onclick="viewDetails('<?php echo $month['month']; ?>', '<?php echo $month['year']; ?>')">
                                            <i class="fas fa-eye"></i> View Details
                                        </button>
                                        <button class="btn btn-secondary" onclick="downloadCSV('<?php echo $month['month']; ?>', '<?php echo $month['year']; ?>')">
                                            <i class="fas fa-download"></i> Download
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Hidden data for modal -->
                                <div style="display: none;" id="data-<?php echo $month['month'] . '-' . $month['year']; ?>">
                                    <?php
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
        </div>
    </div>
    
    <!-- Details Modal -->
    <div class="modal-overlay" id="detailsModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-table"></i> <span id="modalTitle">Attendance Details</span></h2>
                <button class="modal-close" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div id="modalContent"></div>
            </div>
        </div>
    </div>
    
    <script>
        function viewDetails(month, year) {
            const dataDiv = document.getElementById('data-' + month + '-' + year);
            const records = JSON.parse(dataDiv.textContent);
            
            document.getElementById('modalTitle').textContent = month + ' ' + year + ' - Attendance Details';
            
            let html = `<table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Department</th>
                        <th>Status</th>
                        <th>Time In</th>
                        <th>Time Out</th>
                    </tr>
                </thead>
                <tbody>`;
            
            records.forEach(record => {
                const statusClass = record.status.toLowerCase() === 'present' ? 'status-present' : 
                                   record.status.toLowerCase() === 'absent' ? 'status-absent' : 'status-leave';
                
                html += `<tr>
                    <td>${new Date(record.date).toLocaleDateString('en-GB', {day: '2-digit', month: 'short', year: 'numeric'})}</td>
                    <td class="employee-cell">
                        <strong>${record.employee_name}</strong>
                        <small>${record.employee_code}</small>
                    </td>
                    <td>${record.department_name || 'N/A'}</td>
                    <td><span class="status-badge ${statusClass}">${record.status}</span></td>
                    <td>${record.time_in || '--:--'}</td>
                    <td>${record.time_out || '--:--'}</td>
                </tr>`;
            });
            
            html += '</tbody></table>';
            document.getElementById('modalContent').innerHTML = html;
            document.getElementById('detailsModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
        }
        
        function closeModal() {
            document.getElementById('detailsModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Close modal on overlay click
        document.getElementById('detailsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
        
        function downloadCSV(month, year) {
            const dataDiv = document.getElementById('data-' + month + '-' + year);
            const records = JSON.parse(dataDiv.textContent);
            
            let csv = 'Date,Employee Name,Employee Code,Department,Status,Time In,Time Out\n';
            
            records.forEach(record => {
                csv += `${record.date},"${record.employee_name}",${record.employee_code},"${record.department_name || 'N/A'}",${record.status},${record.time_in || '--:--'},${record.time_out || '--:--'}\n`;
            });
            
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `${month}_${year}_finalized_attendance.csv`;
            link.click();
            URL.revokeObjectURL(url);
        }
        
        function filterMonths() {
            const yearFilter = document.getElementById('yearFilter').value;
            const cards = document.querySelectorAll('.month-card');
            
            cards.forEach(card => {
                const cardYear = card.getAttribute('data-year');
                if (!yearFilter || cardYear === yearFilter) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }
        
        // Mark attendance notifications as read on page load
        window.addEventListener('load', function() {
            fetch('<?php echo $baseURL; ?>api/mark_notifications_read.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ type: 'attendance_finalized' })
            }).catch(e => console.log('Notification marking skipped'));
        });
    </script>
</body>
</html>
</body>
</html>
