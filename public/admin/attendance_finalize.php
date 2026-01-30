<?php
session_start();

// Check if user has administrator role
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role']];
$hasAdminRole = in_array('administrator', $userRoles);

if (!isset($_SESSION['user_id']) || (!$hasAdminRole && $_SESSION['role'] !== 'administrator')) {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Administrator';
$userId = $_SESSION['user_id'];

require_once __DIR__ . '/../../app/Config/database.php';
$db = getDBConnection();

// Get available months for finalization with meaningful statistics
$query = "SELECT 
    DATE_FORMAT(a.date, '%M') as month,
    YEAR(a.date) as year,
    COUNT(*) as total_records,
    COUNT(DISTINCT a.date) as days_with_data,
    COUNT(DISTINCT a.employee_id) as unique_employees,
    MIN(a.date) as first_date,
    MAX(a.date) as last_date,
    SUM(CASE WHEN a.workflow_status = 'hr_verified' THEN 1 ELSE 0 END) as verified_records,
    SUM(CASE WHEN a.workflow_status = 'admin_finalized' THEN 1 ELSE 0 END) as finalized_records,
    aml.is_locked
FROM attendance a
LEFT JOIN attendance_month_lock aml ON DATE_FORMAT(a.date, '%M') = aml.month AND YEAR(a.date) = aml.year
GROUP BY DATE_FORMAT(a.date, '%Y-%m'), DATE_FORMAT(a.date, '%M'), YEAR(a.date), aml.is_locked
ORDER BY YEAR(a.date) DESC, MONTH(a.date) DESC";

$stmt = $db->query($query);
$monthsData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current day of month for finalization check
$currentDay = (int)date('d');
$canFinalizeCurrentMonth = $currentDay >= 25;

// Get finalization history
$historyQuery = "SELECT 
    afl.*,
    u.username as finalized_by_user
FROM attendance_finalization_log afl
JOIN users u ON afl.finalized_by = u.user_id
ORDER BY afl.finalized_at DESC
LIMIT 20";

$historyStmt = $db->query($historyQuery);
$finalizationHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

$baseURL = "/payslip_generator/public/";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Finalization - Admin Portal</title>
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        .months-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .month-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid var(--accent);
        }

        .month-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .month-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
        }

        .lock-status {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .lock-status.locked {
            background: #d1fae5;
            color: #065f46;
        }

        .lock-status.unlocked {
            background: #fee2e2;
            color: #991b1b;
        }

        .month-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
            margin-bottom: 15px;
        }

        .stat-item {
            text-align: center;
            padding: 10px;
            background: #f7fafc;
            border-radius: 8px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--accent);
        }

        .stat-label {
            font-size: 11px;
            color: var(--muted);
            text-transform: uppercase;
        }

        .month-actions {
            display: flex;
            gap: 10px;
            position: relative;
            z-index: 10;
        }

        .btn-finalize {
            flex: 1;
            padding: 10px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-finalize:disabled {
            background: #e2e8f0;
            color: #94a3b8;
            cursor: not-allowed;
        }

        .btn-unlock {
            padding: 10px 15px;
            background: #ef4444;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
        }

        .history-table {
            background: white;
            padding: 20px;
            border-radius: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        thead {
            background: #f7fafc;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-left: 4px solid #f59e0b;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e3a8a;
            border-left: 4px solid #3b82f6;
        }

        .btn-view-details {
            padding: 8px 15px;
            background: #6366f1;
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 13px;
            white-space: nowrap;
        }

        .btn-view-details:hover {
            background: #4f46e5;
        }

        .details-table {
            margin-top: 15px;
            display: none;
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }

        .details-table.show {
            display: block;
        }

        .details-table table {
            font-size: 13px;
        }

        .details-table th {
            position: sticky;
            top: 0;
            background: #f7fafc;
            z-index: 1;
        }

        .status-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-present { background: #d1fae5; color: #065f46; }
        .status-absent { background: #fee2e2; color: #991b1b; }
        .status-leave { background: #dbeafe; color: #1e3a8a; }
        .status-holiday { background: #fef3c7; color: #92400e; }

        .workflow-badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
        }

        .workflow-draft { background: #f3f4f6; color: #4b5563; }
        .workflow-verified { background: #dbeafe; color: #1e3a8a; }
        .workflow-finalized { background: #d1fae5; color: #065f46; }

        /* Modal Styles */
        .attendance-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            z-index: 9999;
            overflow: auto;
            padding: 20px;
        }

        .attendance-modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .modal-content {
            background: white;
            border-radius: 16px;
            width: 95%;
            max-width: 1400px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }

        .modal-header {
            padding: 25px 30px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border-radius: 16px 16px 0 0;
        }

        .modal-header h2 {
            margin: 0;
            font-size: 24px;
        }

        .modal-header-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .btn-download {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
        }

        .btn-download:hover {
            background: rgba(255,255,255,0.3);
            transform: translateY(-2px);
        }

        .modal-close {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .modal-close:hover {
            background: rgba(255,255,255,0.3);
            transform: rotate(90deg);
        }

        .modal-body {
            padding: 30px;
            overflow-y: auto;
            flex: 1;
        }

        .filter-controls {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
            padding: 20px;
            background: #f7fafc;
            border-radius: 8px;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-group label {
            font-size: 12px;
            font-weight: 600;
            color: #64748b;
            text-transform: uppercase;
        }

        .filter-group input,
        .filter-group select {
            padding: 8px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 14px;
        }

        .pagination-controls {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
            padding: 15px 0;
            border-top: 2px solid #e2e8f0;
        }

        .pagination-info {
            color: #64748b;
            font-size: 14px;
        }

        .pagination-buttons {
            display: flex;
            gap: 10px;
        }

        .pagination-buttons button {
            padding: 8px 16px;
            border: 1px solid #e2e8f0;
            background: white;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
        }

        .pagination-buttons button:hover:not(:disabled) {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .pagination-buttons button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .pagination-buttons button.active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }

        .modal-table-container {
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }

        .modal-table-container table {
            width: 100%;
            font-size: 14px;
        }

        .modal-table-container thead {
            position: sticky;
            top: 0;
            background: #f7fafc;
            z-index: 10;
        }

        .modal-table-container th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            color: #64748b;
        }

        .modal-table-container .date-separator {
            border-top: 3px solid #667eea;
        }

        .modal-table-container tbody tr:hover {
            background: #f8fafc;
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_navbar.php'; ?>

    <div class="main-content" id="mainContent">
        <div class="page-header">
            <h1><i class="fas fa-lock"></i> Attendance Finalization</h1>
            <p>Lock and finalize HR-verified attendance data</p>
        </div>

        <div class="alert alert-info">
            <strong><i class="fas fa-info-circle"></i> Workflow:</strong>
            HR Officers verify attendance → Admin finalizes and locks the month → Accountant imports finalized data for salary calculation
        </div>

        <div class="alert alert-warning">
            <strong><i class="fas fa-exclamation-triangle"></i> Important:</strong>
            Once a month is finalized and locked, no further attendance changes can be made for that period. Ensure all HR verifications are complete.
        </div>

        <h2 style="margin-top: 30px; margin-bottom: 20px;">Available Months for Finalization</h2>
        <div class="months-grid">
            <?php foreach($monthsData as $monthData): ?>
                <div class="month-card">
                    <div class="month-header">
                        <div class="month-title"><?php echo $monthData['month'] . ' ' . $monthData['year']; ?></div>
                        <span class="lock-status <?php echo $monthData['is_locked'] ? 'locked' : 'unlocked'; ?>">
                            <?php echo $monthData['is_locked'] ? '🔒 Locked' : '🔓 Unlocked'; ?>
                        </span>
                    </div>

                    <div class="month-stats">
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $monthData['days_with_data']; ?></div>
                            <div class="stat-label">Days Tracked</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $monthData['unique_employees']; ?></div>
                            <div class="stat-label">Employees</div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-value"><?php echo $monthData['verified_records']; ?></div>
                            <div class="stat-label">HR Verified</div>
                        </div>
                    </div>
                    
                    <div style="font-size: 12px; color: #64748b; margin-bottom: 15px; text-align: center;">
                        <?php echo date('M d', strtotime($monthData['first_date'])); ?> - 
                        <?php echo date('M d, Y', strtotime($monthData['last_date'])); ?> 
                        (<?php echo $monthData['total_records']; ?> records)
                    </div>

                    <div class="month-actions">
                        <button class="btn-view-details" onclick="showAttendanceModal('<?php echo $monthData['month']; ?>', '<?php echo $monthData['year']; ?>')">
                            <i class="fas fa-table"></i> View Details
                        </button>
                        <?php 
                        // Check if this is current month
                        $isCurrentMonth = ($monthData['month'] == date('F') && $monthData['year'] == date('Y'));
                        $canFinalize = !$isCurrentMonth || ($isCurrentMonth && $canFinalizeCurrentMonth);
                        ?>
                        
                        <?php if (!$monthData['is_locked']): ?>
                            <?php if ($canFinalize): ?>
                                <button class="btn-finalize" 
                                        onclick="finalizeMonth('<?php echo $monthData['month']; ?>', <?php echo $monthData['year']; ?>)"
                                        <?php echo $monthData['verified_records'] == 0 ? 'disabled' : ''; ?>>
                                    <i class="fas fa-check"></i> Finalize & Lock
                                </button>
                            <?php else: ?>
                                <button class="btn-finalize" disabled title="Finalization opens on the 25th of the month">
                                    <i class="fas fa-calendar-times"></i> Available from 25th
                                </button>
                            <?php endif; ?>
                        <?php else: ?>
                            <button class="btn-unlock" 
                                    onclick="unlockMonth('<?php echo $monthData['month']; ?>', <?php echo $monthData['year']; ?>)">
                                <i class="fas fa-unlock"></i> Unlock
                            </button>
                        <?php endif; ?>
                    </div>

                    <!-- Hidden details data -->
                    <div style="display: none;" id="details-data-<?php echo $monthData['month'] . '-' . $monthData['year']; ?>">
                        <?php
                        // Fetch detailed records for this month
                        $detailQuery = "SELECT 
                            a.attendance_id,
                            a.date,
                            e.full_name as employee_name,
                            e.employee_code as emp_id,
                            d.department_name,
                            a.status,
                            a.workflow_status,
                            a.verification_status,
                            a.time_in,
                            a.time_out
                        FROM attendance a
                        JOIN employees e ON a.employee_id = e.employee_id
                        LEFT JOIN departments d ON e.department_id = d.department_id
                        WHERE DATE_FORMAT(a.date, '%M') = ? AND YEAR(a.date) = ?
                        ORDER BY a.date DESC, e.full_name ASC";
                        
                        try {
                            $detailStmt = $db->prepare($detailQuery);
                            
                            // Debug: show what we're searching for
                            echo "<!-- Searching for month='" . $monthData['month'] . "' year='" . $monthData['year'] . "' -->";
                            
                            $detailStmt->execute([$monthData['month'], $monthData['year']]);
                            $detailRecords = $detailStmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            // Debug: output record count
                            echo "<!-- Debug: Found " . count($detailRecords) . " records for " . $monthData['month'] . " " . $monthData['year'] . " -->";
                            
                            // Test query without parameters
                            $testStmt = $db->query("SELECT COUNT(*) as cnt FROM attendance WHERE DATE_FORMAT(date, '%M') = 'January' AND YEAR(date) = 2026");
                            $testResult = $testStmt->fetch(PDO::FETCH_ASSOC);
                            echo "<!-- Test: Direct query found " . $testResult['cnt'] . " records -->";
                        } catch (Exception $e) {
                            echo "<!-- Error: " . $e->getMessage() . " -->";
                            $detailRecords = [];
                        }
                        ?>
                        
                        <?php if (count($detailRecords) > 0): ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Employee</th>
                                    <th>Department</th>
                                    <th>Status</th>
                                    <th>Workflow</th>
                                    <th>Check In</th>
                                    <th>Check Out</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $previousDate = null;
                                foreach($detailRecords as $record): 
                                    $currentDate = $record['date'];
                                    $isNewDate = ($previousDate !== null && $currentDate !== $previousDate);
                                    $previousDate = $currentDate;
                                ?>
                                    <tr <?php echo $isNewDate ? 'class="date-separator"' : ''; ?>>
                                        <td><?php echo date('d M Y', strtotime($record['date'])); ?></td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($record['employee_name']); ?></strong><br>
                                            <small style="color: #64748b;"><?php echo $record['emp_id']; ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($record['department_name'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="status-badge status-<?php echo strtolower($record['status']); ?>">
                                                <?php echo ucfirst($record['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="workflow-badge workflow-<?php 
                                                echo $record['workflow_status'] == 'hr_verified' ? 'verified' : 
                                                     ($record['workflow_status'] == 'admin_finalized' ? 'finalized' : 'draft'); 
                                            ?>">
                                                <?php 
                                                    if ($record['workflow_status'] == 'hr_verified') echo 'HR Verified';
                                                    elseif ($record['workflow_status'] == 'admin_finalized') echo 'Finalized';
                                                    else echo 'Draft';
                                                ?>
                                            </span>
                                        </td>
                                        <td><?php echo $record['time_in'] ?? '--:--'; ?></td>
                                        <td><?php echo $record['time_out'] ?? '--:--'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <p style="padding: 20px; text-align: center; color: #64748b;">No attendance records found for this month.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <h2 style="margin-top: 40px; margin-bottom: 20px;">Finalization History</h2>
        <div class="history-table">
            <table>
                <thead>
                    <tr>
                        <th>Month/Year</th>
                        <th>Finalized By</th>
                        <th>Finalized At</th>
                        <th>Records</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($finalizationHistory as $history): ?>
                        <tr>
                            <td><strong><?php echo $history['month'] . ' ' . $history['year']; ?></strong></td>
                            <td><?php echo htmlspecialchars($history['finalized_by_user']); ?></td>
                            <td><?php echo date('d M Y, h:i A', strtotime($history['finalized_at'])); ?></td>
                            <td><?php echo $history['record_count']; ?></td>
                            <td><?php echo htmlspecialchars($history['notes'] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Attendance Details Modal -->
    <div class="attendance-modal" id="attendanceModal">
        <div class="modal-content">
            <div class="modal-header">
                <h2><i class="fas fa-table"></i> <span id="modalTitle">Attendance Details</span></h2>
                <div class="modal-header-actions">
                    <button class="btn-download" onclick="downloadAttendanceCSV()">
                        <i class="fas fa-download"></i> Download CSV
                    </button>
                    <button class="modal-close" onclick="closeAttendanceModal()">×</button>
                </div>
            </div>
            <div class="modal-body">
                <div class="filter-controls">
                    <div class="filter-group">
                        <label>Search Employee</label>
                        <input type="text" id="filterEmployee" placeholder="Employee name or code..." onkeyup="applyFilters()">
                    </div>
                    <div class="filter-group">
                        <label>Filter by Date</label>
                        <input type="date" id="filterDate" onchange="applyFilters()">
                    </div>
                    <div class="filter-group">
                        <label>Filter by Status</label>
                        <select id="filterStatus" onchange="applyFilters()">
                            <option value="">All Status</option>
                            <option value="present">Present</option>
                            <option value="absent">Absent</option>
                            <option value="leave">Leave</option>
                            <option value="holiday">Holiday</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Filter by Workflow</label>
                        <select id="filterWorkflow" onchange="applyFilters()">
                            <option value="">All Workflow</option>
                            <option value="draft">Draft</option>
                            <option value="hr_verified">HR Verified</option>
                            <option value="admin_finalized">Finalized</option>
                        </select>
                    </div>
                </div>
                <div class="modal-table-container" id="modalTableContainer">
                    <p style="text-align: center; padding: 40px; color: #94a3b8;">
                        <i class="fas fa-spinner fa-spin" style="font-size: 32px;"></i><br><br>
                        Loading attendance data...
                    </p>
                </div>
                <div class="pagination-controls" id="paginationControls" style="display: none;">
                    <div class="pagination-info" id="paginationInfo"></div>
                    <div class="pagination-buttons" id="paginationButtons"></div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/admin_scripts.php'; ?>
    <script type="text/javascript">
        console.log('Admin finalize scripts loaded');
        
        function finalizeMonth(month, year) {
            if (!confirm(`Are you sure you want to finalize and lock ${month} ${year}?\n\nThis action will:\n- Mark all HR-verified attendance as finalized\n- Lock the month to prevent further changes\n- Make data available for Accountant to import\n\nThis cannot be easily undone.`)) {
                return;
            }

            const notes = prompt('Add notes (optional):');

            fetch('api/finalize_attendance.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ month, year, notes })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Month finalized successfully!');
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            });
        }

        function unlockMonth(month, year) {
            if (!confirm(`Are you sure you want to unlock ${month} ${year}?\n\nThis will allow HR to make attendance changes again.`)) {
                return;
            }

            fetch('api/unlock_attendance.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ month, year })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Month unlocked successfully!');
                    location.reload();
                } else {
                    alert('❌ Error: ' + data.message);
                }
            });
        }

        let allRows = [];
        let filteredRows = [];
        let currentPage = 1;
        const rowsPerPage = 50;

        function showAttendanceModal(month, year) {
            const modal = document.getElementById('attendanceModal');
            const modalTitle = document.getElementById('modalTitle');
            const container = document.getElementById('modalTableContainer');
            
            modalTitle.textContent = `${month} ${year} - Attendance Details`;
            modal.classList.add('show');
            
            // Get the hidden data
            const dataDiv = document.getElementById('details-data-' + month + '-' + year);
            if (dataDiv) {
                const tempDiv = document.createElement('div');
                tempDiv.innerHTML = dataDiv.innerHTML;
                const table = tempDiv.querySelector('table');
                
                if (table) {
                    // Extract all rows
                    allRows = Array.from(table.querySelectorAll('tbody tr'));
                    filteredRows = [...allRows];
                    
                    // Reset filters
                    document.getElementById('filterEmployee').value = '';
                    document.getElementById('filterDate').value = '';
                    document.getElementById('filterStatus').value = '';
                    document.getElementById('filterWorkflow').value = '';
                    
                    currentPage = 1;
                    renderTable();
                } else {
                    container.innerHTML = '<p style="text-align: center; padding: 40px; color: #ef4444;">No data available</p>';
                }
            } else {
                container.innerHTML = '<p style="text-align: center; padding: 40px; color: #ef4444;">Error loading data</p>';
            }
            
            // Prevent body scroll
            document.body.style.overflow = 'hidden';
        }

        function applyFilters() {
            const searchText = document.getElementById('filterEmployee').value.toLowerCase();
            const filterDate = document.getElementById('filterDate').value;
            const filterStatus = document.getElementById('filterStatus').value.toLowerCase();
            const filterWorkflow = document.getElementById('filterWorkflow').value.toLowerCase();
            
            filteredRows = allRows.filter(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length < 5) return false;
                
                const dateText = cells[0].textContent.trim();
                const employeeText = cells[1].textContent.toLowerCase();
                const statusText = cells[3].textContent.toLowerCase();
                const workflowText = cells[4].textContent.toLowerCase();
                
                // Filter by search
                if (searchText && !employeeText.includes(searchText)) return false;
                
                // Filter by date
                if (filterDate) {
                    const filterDateObj = new Date(filterDate);
                    const rowDateStr = dateText.split(' ');
                    const rowDate = new Date(rowDateStr[2] + '-' + rowDateStr[1] + '-' + rowDateStr[0]);
                    if (rowDate.toDateString() !== filterDateObj.toDateString()) return false;
                }
                
                // Filter by status
                if (filterStatus && !statusText.includes(filterStatus)) return false;
                
                // Filter by workflow
                if (filterWorkflow && !workflowText.includes(filterWorkflow)) return false;
                
                return true;
            });
            
            currentPage = 1;
            renderTable();
        }

        function renderTable() {
            const container = document.getElementById('modalTableContainer');
            const paginationControls = document.getElementById('paginationControls');
            const paginationInfo = document.getElementById('paginationInfo');
            const paginationButtons = document.getElementById('paginationButtons');
            
            const totalRows = filteredRows.length;
            const totalPages = Math.ceil(totalRows / rowsPerPage);
            const startIdx = (currentPage - 1) * rowsPerPage;
            const endIdx = Math.min(startIdx + rowsPerPage, totalRows);
            const currentRows = filteredRows.slice(startIdx, endIdx);
            
            // Build table
            let tableHTML = `
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Employee</th>
                            <th>Department</th>
                            <th>Status</th>
                            <th>Workflow</th>
                            <th>Check In</th>
                            <th>Check Out</th>
                        </tr>
                    </thead>
                    <tbody>`;
            
            if (currentRows.length > 0) {
                let previousDate = null;
                currentRows.forEach(row => {
                    const currentDate = row.querySelector('td')?.textContent.trim();
                    const isNewDate = (previousDate !== null && currentDate !== previousDate);
                    previousDate = currentDate;
                    
                    const rowClone = row.cloneNode(true);
                    if (isNewDate) {
                        rowClone.classList.add('date-separator');
                    }
                    tableHTML += rowClone.outerHTML;
                });
            } else {
                tableHTML += '<tr><td colspan="7" style="text-align: center; padding: 40px; color: #94a3b8;">No records found</td></tr>';
            }
            
            tableHTML += '</tbody></table>';
            container.innerHTML = tableHTML;
            
            // Update pagination
            if (totalRows > 0) {
                paginationControls.style.display = 'flex';
                paginationInfo.textContent = `Showing ${startIdx + 1}-${endIdx} of ${totalRows} records`;
                
                // Build page buttons
                let buttonsHTML = '';
                buttonsHTML += `<button onclick="changePage(${currentPage - 1})" ${currentPage === 1 ? 'disabled' : ''}>← Previous</button>`;
                
                // Show page numbers (max 5)
                let startPage = Math.max(1, currentPage - 2);
                let endPage = Math.min(totalPages, startPage + 4);
                startPage = Math.max(1, endPage - 4);
                
                for (let i = startPage; i <= endPage; i++) {
                    buttonsHTML += `<button onclick="changePage(${i})" class="${i === currentPage ? 'active' : ''}">${i}</button>`;
                }
                
                buttonsHTML += `<button onclick="changePage(${currentPage + 1})" ${currentPage === totalPages ? 'disabled' : ''}>Next →</button>`;
                paginationButtons.innerHTML = buttonsHTML;
            } else {
                paginationControls.style.display = 'none';
            }
        }

        function changePage(page) {
            const totalPages = Math.ceil(filteredRows.length / rowsPerPage);
            if (page < 1 || page > totalPages) return;
            currentPage = page;
            renderTable();
        }

        function closeAttendanceModal() {
            const modal = document.getElementById('attendanceModal');
            modal.classList.remove('show');
            document.body.style.overflow = 'auto';
        }

        function downloadAttendanceCSV() {
            // Use filtered rows for download
            const modalTitle = document.getElementById('modalTitle').textContent;
            
            if (filteredRows.length === 0) {
                alert('No data to download');
                return;
            }
            
            // Build CSV from filtered rows
            let csv = [];
            // Add header
            csv.push('Date,Employee Name,Employee Code,Department,Status,Workflow Status,Check In,Check Out');
            
            // Add data rows
            filteredRows.forEach(row => {
                const cells = row.querySelectorAll('td');
                if (cells.length < 7) return;
                
                let rowData = [];
                
                // Date
                rowData.push(cells[0].textContent.trim().replace(/\s+/g, ' '));
                
                // Employee (split name and code)
                const employeeCell = cells[1];
                const employeeName = employeeCell.querySelector('strong')?.textContent.trim() || '';
                const employeeCode = employeeCell.querySelector('small')?.textContent.trim() || '';
                rowData.push(employeeName);
                rowData.push(employeeCode);
                
                // Department
                rowData.push(cells[2].textContent.trim().replace(/\s+/g, ' '));
                
                // Status
                rowData.push(cells[3].textContent.trim().replace(/\s+/g, ' '));
                
                // Workflow Status
                rowData.push(cells[4].textContent.trim().replace(/\s+/g, ' '));
                
                // Check In
                rowData.push(cells[5].textContent.trim());
                
                // Check Out
                rowData.push(cells[6].textContent.trim());
                
                // Escape commas and quotes
                rowData = rowData.map(text => {
                    if (text.includes(',') || text.includes('"')) {
                        return '"' + text.replace(/"/g, '""') + '"';
                    }
                    return text;
                });
                
                csv.push(rowData.join(','));
            });
            
            // Create and download
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            const filename = modalTitle.replace(/[^a-z0-9]/gi, '_').toLowerCase() + '.csv';
            
            link.setAttribute('href', url);
            link.setAttribute('download', filename);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Close modal on outside click
        document.getElementById('attendanceModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeAttendanceModal();
            }
        });

        // Close modal on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAttendanceModal();
            }
        });
    </script>
</body>
</html>
