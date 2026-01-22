<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'director') {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . "/../../app/Config/database.php";

$db = getDBConnection();
$username = $_SESSION['username'] ?? 'Director';

// Get filter parameters
$monthFilter = $_GET['month'] ?? date('F');
$yearFilter = $_GET['year'] ?? date('Y');

// Get payroll summary by month/year
try {
    // Total payout (approved)
    $stmt = $db->prepare("
        SELECT SUM(net_salary) as total_payout, COUNT(*) as approved_count
        FROM payroll 
        WHERE month = ? AND year = ? AND approval_status = 'approved'
    ");
    $stmt->execute([$monthFilter, $yearFilter]);
    $payoutData = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalPayout = $payoutData['total_payout'] ?? 0;
    $approvedCount = $payoutData['approved_count'] ?? 0;
    
    // Pending count
    $stmt = $db->prepare("
        SELECT COUNT(*) as pending_count
        FROM payroll 
        WHERE month = ? AND year = ? 
        AND (approval_status = 'pending' OR approval_status IS NULL)
    ");
    $stmt->execute([$monthFilter, $yearFilter]);
    $pendingData = $stmt->fetch(PDO::FETCH_ASSOC);
    $pendingCount = $pendingData['pending_count'] ?? 0;
    
    // Rejected count
    $stmt = $db->prepare("
        SELECT COUNT(*) as rejected_count
        FROM payroll 
        WHERE month = ? AND year = ? AND approval_status = 'rejected'
    ");
    $stmt->execute([$monthFilter, $yearFilter]);
    $rejectedData = $stmt->fetch(PDO::FETCH_ASSOC);
    $rejectedCount = $rejectedData['rejected_count'] ?? 0;
    
    // Department-wise breakdown
    $stmt = $db->prepare("
        SELECT 
            d.department_name,
            COUNT(*) as employee_count,
            SUM(p.net_salary) as department_payout,
            SUM(p.gross_salary) as total_gross,
            SUM(p.total_deductions) as total_deductions
        FROM payroll p
        JOIN employees e ON p.employee_id = e.employee_id
        LEFT JOIN departments d ON e.department_id = d.department_id
        WHERE p.month = ? AND p.year = ? AND p.approval_status = 'approved'
        GROUP BY d.department_id
        ORDER BY department_payout DESC
    ");
    $stmt->execute([$monthFilter, $yearFilter]);
    $departmentBreakdown = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Monthly trend (last 12 months)
    $stmt = $db->query("
        SELECT 
            month,
            year,
            SUM(net_salary) as total_payout,
            COUNT(*) as employee_count
        FROM payroll 
        WHERE approval_status = 'approved'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
        GROUP BY year, month
        ORDER BY year DESC, 
            FIELD(month, 'January', 'February', 'March', 'April', 'May', 'June', 
                        'July', 'August', 'September', 'October', 'November', 'December') DESC
        LIMIT 12
    ");
    $monthlyTrend = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $error = $e->getMessage();
    $totalPayout = 0;
    $approvedCount = 0;
    $pendingCount = 0;
    $rejectedCount = 0;
    $departmentBreakdown = [];
    $monthlyTrend = [];
}

$months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$years = range(date('Y'), date('Y') - 3);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payroll Reports - Director</title>
    <?php include 'includes/director_styles.php'; ?>
    <style>
        .filters-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .filter-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: var(--muted);
            font-size: 13px;
        }

        .filter-group select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
        }

        .filter-btn {
            padding: 10px 20px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            border-left: 4px solid var(--accent);
        }

        .stat-card.payout { border-left-color: #667eea; }
        .stat-card.approved { border-left-color: #10b981; }
        .stat-card.pending { border-left-color: #f59e0b; }
        .stat-card.rejected { border-left-color: #ef4444; }

        .stat-label {
            font-size: 12px;
            color: var(--muted);
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--text);
        }

        .report-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border);
        }

        .section-header h2 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .export-btn {
            padding: 8px 16px;
            background: #e0e7ff;
            color: #667eea;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .export-btn:hover {
            background: #c7d2fe;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
        }

        .data-table thead {
            background: #f8fafc;
        }

        .data-table th {
            padding: 12px 15px;
            text-align: left;
            font-size: 13px;
            font-weight: 600;
            color: var(--muted);
            text-transform: uppercase;
        }

        .data-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }

        .data-table tbody tr:hover {
            background: #f8fafc;
        }

        .trend-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid var(--border);
        }

        .trend-item:last-child {
            border-bottom: none;
        }

        .trend-period {
            font-weight: 600;
            color: var(--text);
        }

        .trend-value {
            font-weight: 700;
            font-size: 16px;
            color: var(--accent);
        }

        .trend-employees {
            font-size: 12px;
            color: var(--muted);
        }

        @media (max-width: 1200px) {
            .stats-row {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/director_sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <div>
                <h1><i class="fas fa-chart-line"></i> Payroll Reports</h1>
                <p>Financial summaries and payroll analytics</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" class="filters-grid">
                <div class="filter-group">
                    <label>Month</label>
                    <select name="month">
                        <?php foreach ($months as $m): ?>
                            <option value="<?php echo $m; ?>" <?php echo $monthFilter === $m ? 'selected' : ''; ?>><?php echo $m; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Year</label>
                    <select name="year">
                        <?php foreach ($years as $y): ?>
                            <option value="<?php echo $y; ?>" <?php echo $yearFilter == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="filter-btn">
                        <i class="fas fa-sync-alt"></i> Update Report
                    </button>
                </div>
            </form>
        </div>

        <!-- Stats -->
        <div class="stats-row">
            <div class="stat-card payout">
                <div class="stat-label">
                    <i class="fas fa-wallet"></i> Total Payout
                </div>
                <div class="stat-value">₹<?php echo number_format($totalPayout, 2); ?></div>
            </div>
            <div class="stat-card approved">
                <div class="stat-label">
                    <i class="fas fa-check-circle"></i> Approved
                </div>
                <div class="stat-value"><?php echo $approvedCount; ?></div>
            </div>
            <div class="stat-card pending">
                <div class="stat-label">
                    <i class="fas fa-clock"></i> Pending
                </div>
                <div class="stat-value"><?php echo $pendingCount; ?></div>
            </div>
            <div class="stat-card rejected">
                <div class="stat-label">
                    <i class="fas fa-times-circle"></i> Rejected
                </div>
                <div class="stat-value"><?php echo $rejectedCount; ?></div>
            </div>
        </div>

        <!-- Department Breakdown -->
        <div class="report-section">
            <div class="section-header">
                <h2><i class="fas fa-building"></i> Department-wise Breakdown</h2>
                <button class="export-btn" onclick="exportTable('dept-table', 'Department_Breakdown_<?php echo $monthFilter . '_' . $yearFilter; ?>')">
                    <i class="fas fa-download"></i> Export CSV
                </button>
            </div>
            
            <?php if (empty($departmentBreakdown)): ?>
                <div style="text-align: center; padding: 40px; color: var(--muted);">
                    <i class="fas fa-inbox" style="font-size: 48px; opacity: 0.5; display: block; margin-bottom: 15px;"></i>
                    <p>No approved payrolls found for this period</p>
                </div>
            <?php else: ?>
                <table class="data-table" id="dept-table">
                    <thead>
                        <tr>
                            <th>Department</th>
                            <th>Employees</th>
                            <th>Gross Salary</th>
                            <th>Deductions</th>
                            <th>Net Payout</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($departmentBreakdown as $dept): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($dept['department_name'] ?? 'Unassigned'); ?></strong></td>
                                <td><?php echo $dept['employee_count']; ?></td>
                                <td>₹<?php echo number_format($dept['total_gross'], 2); ?></td>
                                <td style="color: #ef4444;">₹<?php echo number_format($dept['total_deductions'], 2); ?></td>
                                <td style="color: var(--accent); font-weight: 700;">₹<?php echo number_format($dept['department_payout'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Monthly Trend -->
        <div class="report-section">
            <div class="section-header">
                <h2><i class="fas fa-chart-area"></i> 12-Month Trend</h2>
            </div>
            
            <?php if (empty($monthlyTrend)): ?>
                <div style="text-align: center; padding: 40px; color: var(--muted);">
                    <i class="fas fa-chart-line" style="font-size: 48px; opacity: 0.5; display: block; margin-bottom: 15px;"></i>
                    <p>No historical data available</p>
                </div>
            <?php else: ?>
                <?php foreach ($monthlyTrend as $trend): ?>
                    <div class="trend-item">
                        <div>
                            <div class="trend-period"><?php echo $trend['month'] . ' ' . $trend['year']; ?></div>
                            <div class="trend-employees"><?php echo $trend['employee_count']; ?> employees</div>
                        </div>
                        <div class="trend-value">₹<?php echo number_format($trend['total_payout'], 2); ?></div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'includes/director_scripts.php'; ?>
    <script>
        function exportTable(tableId, filename) {
            const table = document.getElementById(tableId);
            let csv = [];
            
            // Headers
            const headers = [];
            table.querySelectorAll('thead th').forEach(th => {
                headers.push(th.textContent.trim());
            });
            csv.push(headers.join(','));
            
            // Rows
            table.querySelectorAll('tbody tr').forEach(tr => {
                const row = [];
                tr.querySelectorAll('td').forEach(td => {
                    row.push('"' + td.textContent.trim().replace(/"/g, '""') + '"');
                });
                csv.push(row.join(','));
            });
            
            // Download
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename + '.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
