<?php
session_start();

// Role check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'auditor') {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Auditor';
$baseURL = "/payslip_generator/public/";

// Database connection
require_once __DIR__ . '/../../app/Config/database.php';
$db = new Database();
$conn = $db->connect();

// Get filter parameters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$departmentFilter = $_GET['department'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$verificationFilter = $_GET['verification'] ?? '';

// Build query
$sql = "SELECT 
            a.attendance_id,
            a.employee_id,
            e.full_name,
            d.department_name,
            a.date,
            a.status,
            a.time_in,
            a.time_out,
            a.leave_type,
            a.verification_status,
            a.remarks
        FROM attendance a
        JOIN employees e ON a.employee_id = e.employee_id
        LEFT JOIN departments d ON e.department_id = d.department_id
        WHERE a.date BETWEEN ? AND ?";

$params = [$startDate, $endDate];

if ($departmentFilter) {
    $sql .= " AND e.department_id = ?";
    $params[] = $departmentFilter;
}

if ($statusFilter) {
    $sql .= " AND a.status = ?";
    $params[] = $statusFilter;
}

if ($verificationFilter) {
    $sql .= " AND a.verification_status = ?";
    $params[] = $verificationFilter;
}

$sql .= " ORDER BY a.date DESC, e.full_name ASC";

try {
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $records = [];
    $error = "Error fetching attendance: " . $e->getMessage();
}

// Get departments
try {
    $deptStmt = $conn->query("SELECT department_id, department_name FROM departments ORDER BY department_name ASC");
    $departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $departments = [];
}

// Get stats
$stats = [
    'total' => count($records),
    'present' => count(array_filter($records, fn($r) => strcasecmp($r['status'], 'present') === 0)),
    'absent' => count(array_filter($records, fn($r) => strcasecmp($r['status'], 'absent') === 0)),
    'leave' => count(array_filter($records, fn($r) => strcasecmp($r['status'], 'leave') === 0))
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports - Auditor Portal</title>
    <?php include 'includes/auditor_styles.php'; ?>
    <style>
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 25px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }

        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid var(--accent);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }

        .stat-card.present { border-left-color: #10b981; }
        .stat-card.absent { border-left-color: #ef4444; }
        .stat-card.leave { border-left-color: #f59e0b; }

        .stat-label {
            font-size: 12px;
            color: var(--muted);
            font-weight: 600;
            text-transform: uppercase;
            margin-bottom: 10px;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-card.present .stat-value {
            background: linear-gradient(135deg, #10b981, #059669);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-card.absent .stat-value {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-card.leave .stat-value {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .filters-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            align-items: end;
        }

        .filter-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: var(--muted);
            font-size: 13px;
        }

        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 9px 12px;
            border: 1px solid var(--border);
            border-radius: 6px;
            font-size: 14px;
        }

        .filter-group input:focus,
        .filter-group select:focus {
            outline: none;
            border-color: var(--accent);
        }

        .filter-btn, .export-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .filter-btn {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
        }

        .export-btn {
            background: #10b981;
            color: white;
        }

        .filter-btn:hover, .export-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .table-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .table-header {
            padding: 25px;
            border-bottom: 2px solid #f1f5f9;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.05), rgba(118, 75, 162, 0.05));
        }
        
        .table-header h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
        }
            display: flex;
            justify-content: space-between;
            align-items: center;
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
            font-weight: 600;
            color: var(--muted);
            font-size: 12px;
            text-transform: uppercase;
        }

        .data-table td {
            padding: 12px 15px;
            border-top: 1px solid #f1f5f9;
            font-size: 14px;
        }

        .data-table tbody tr:hover {
            background: #f8fafc;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-badge.present {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge.absent {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-badge.leave {
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.verified {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }
    </style>
</head>
<body>
    <?php include 'includes/auditor_navbar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <div>
                <h1><i class="fas fa-user-check"></i> Attendance Reports</h1>
                <p>Comprehensive attendance tracking and analysis</p>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 13px; color: var(--muted); margin-bottom: 5px;">Period</div>
                <div style="font-size: 16px; font-weight: 700; color: var(--accent);">
                    <?php echo date('M d', strtotime($startDate)); ?> - <?php echo date('M d, Y', strtotime($endDate)); ?>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Records</div>
                <div class="stat-value"><?php echo number_format($stats['total']); ?></div>
            </div>
            <div class="stat-card present">
                <div class="stat-label">Present</div>
                <div class="stat-value"><?php echo number_format($stats['present']); ?></div>
            </div>
            <div class="stat-card absent">
                <div class="stat-label">Absent</div>
                <div class="stat-value"><?php echo number_format($stats['absent']); ?></div>
            </div>
            <div class="stat-card leave">
                <div class="stat-label">Leave</div>
                <div class="stat-value"><?php echo number_format($stats['leave']); ?></div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filters-card">
            <form method="GET" class="filters-grid">
                <div class="filter-group">
                    <label>Start Date</label>
                    <input type="date" name="start_date" value="<?php echo $startDate; ?>">
                </div>
                <div class="filter-group">
                    <label>End Date</label>
                    <input type="date" name="end_date" value="<?php echo $endDate; ?>">
                </div>
                <div class="filter-group">
                    <label>Department</label>
                    <select name="department">
                        <option value="">All Departments</option>
                        <?php foreach ($departments as $dept): ?>
                            <option value="<?php echo $dept['department_id']; ?>" <?php echo $departmentFilter == $dept['department_id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($dept['department_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="">All Status</option>
                        <option value="present" <?php echo $statusFilter === 'present' ? 'selected' : ''; ?>>Present</option>
                        <option value="absent" <?php echo $statusFilter === 'absent' ? 'selected' : ''; ?>>Absent</option>
                        <option value="leave" <?php echo $statusFilter === 'leave' ? 'selected' : ''; ?>>Leave</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>Verification</label>
                    <select name="verification">
                        <option value="">All</option>
                        <option value="Verified" <?php echo $verificationFilter === 'Verified' ? 'selected' : ''; ?>>Verified</option>
                        <option value="Pending" <?php echo $verificationFilter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button type="submit" class="filter-btn">
                        <i class="fas fa-filter"></i> Apply
                    </button>
                </div>
            </form>
        </div>

        <!-- Table -->
        <div class="table-card">
            <div class="table-header">
                <h3><?php echo count($records); ?> Records Found</h3>
                <button class="export-btn" onclick="exportToCSV()">
                    <i class="fas fa-download"></i> Export CSV
                </button>
            </div>

            <?php if (empty($records)): ?>
                <div style="text-align: center; padding: 60px; color: var(--muted);">
                    <i class="fas fa-inbox" style="font-size: 48px; display: block; margin-bottom: 15px; opacity: 0.5;"></i>
                    <h3>No Records Found</h3>
                    <p>Try adjusting your filters</p>
                </div>
            <?php else: ?>
                <table class="data-table" id="attendanceTable">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Time In</th>
                            <th>Time Out</th>
                            <th>Leave Type</th>
                            <th>Verification</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record): ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($record['date'])); ?></td>
                                <td><strong><?php echo htmlspecialchars($record['full_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($record['department_name'] ?? 'N/A'); ?></td>
                                <td><span class="status-badge <?php echo strtolower($record['status']); ?>"><?php echo $record['status']; ?></span></td>
                                <td><?php echo $record['time_in'] ?? '-'; ?></td>
                                <td><?php echo $record['time_out'] ?? '-'; ?></td>
                                <td><?php echo $record['leave_type'] ?? '-'; ?></td>
                                <td><span class="status-badge <?php echo strtolower($record['verification_status'] ?? 'pending'); ?>"><?php echo $record['verification_status'] ?? 'Pending'; ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'includes/auditor_scripts.php'; ?>
    <script>
        const recordsData = <?php echo json_encode($records); ?>;

        function exportToCSV() {
            if (recordsData.length === 0) {
                alert('No data to export');
                return;
            }

            const headers = ['Date', 'Employee', 'Department', 'Status', 'Time In', 'Time Out', 'Leave Type', 'Verification', 'Remarks'];
            const rows = recordsData.map(r => [
                r.date,
                r.full_name,
                r.department_name || '',
                r.status,
                r.time_in || '',
                r.time_out || '',
                r.leave_type || '',
                r.verification_status || 'Pending',
                r.remarks || ''
            ]);

            let csv = headers.join(',') + '\n';
            rows.forEach(row => {
                csv += row.map(cell => `"${cell}"`).join(',') + '\n';
            });

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `attendance_report_${new Date().toISOString().split('T')[0]}.csv`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
