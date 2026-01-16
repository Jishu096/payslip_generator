<?php
session_start();

// Security: Only Accountant role can access
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? ''];
$hasAccountantRole = in_array('accountant', $userRoles);

if (!isset($_SESSION['role']) || (!$hasAccountantRole && $_SESSION['role'] !== 'accountant')) {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . "/../../app/Config/database.php";
$db = getDBConnection();
$username = $_SESSION['username'] ?? 'Accountant';

// Get available months (only HR verified and not locked by director)
try {
    $stmt = $db->query("
        SELECT DISTINCT 
            MONTH(date) as month_num,
            YEAR(date) as year,
            DATE_FORMAT(date, '%M %Y') as month_year
        FROM attendance
        WHERE verification_status = 'Verified'
        GROUP BY YEAR(date), MONTH(date)
        HAVING COUNT(*) > 0
        ORDER BY YEAR(date) DESC, MONTH(date) DESC
    ");
    $availableMonths = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $availableMonths = [];
}

$currentMonth = date('n');
$currentYear = date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Attendance Statement - Accountant</title>
    <?php include 'includes/accountant_styles.php'; ?>
    <style>
        .form-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            margin-bottom: 25px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .form-group label {
            font-weight: 600;
            color: var(--text);
            font-size: 14px;
        }

        .form-group select {
            padding: 12px 15px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
            color: var(--text);
            background: white;
            transition: all 0.3s;
        }

        .form-group select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .required {
            color: var(--danger);
        }

        .info-box {
            background: linear-gradient(135deg, #e0e7ff, #dbeafe);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            border-left: 4px solid var(--accent);
        }

        .info-box h3 {
            font-size: 16px;
            font-weight: 700;
            color: var(--accent);
            margin-bottom: 10px;
        }

        .info-box ul {
            margin: 0;
            padding-left: 20px;
        }

        .info-box li {
            margin: 5px 0;
            color: var(--text);
            font-size: 14px;
        }

        .generate-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .generate-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
        }

        .generate-btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
            transform: none;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border-left: 4px solid #ef4444;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border-left: 4px solid #f59e0b;
        }

        .audit-table {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .audit-table h2 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .audit-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .audit-table th {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            padding: 12px 15px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }

        .audit-table td {
            padding: 12px 15px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
            color: var(--text);
        }

        .audit-table tr:hover {
            background: var(--bg);
        }
    </style>
</head>
<body>
    <?php include 'includes/accountant_navbar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <div>
                <h1><i class="fas fa-file-excel"></i> Generate Attendance Statement</h1>
                <p>Create official Government format Excel for verified attendance data</p>
            </div>
        </div>

        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php 
                    echo $_SESSION['success_message']; 
                    unset($_SESSION['success_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php 
                    echo $_SESSION['error_message']; 
                    unset($_SESSION['error_message']);
                ?>
            </div>
        <?php endif; ?>

        <?php if (empty($availableMonths)): ?>
            <div class="alert alert-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <div>
                    <strong>No Verified Attendance Data Available</strong>
                    <p style="margin: 5px 0 0 0; font-size: 13px;">Please ensure HR Officer has verified attendance data before generating statements.</p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Information Box -->
        <div class="info-box">
            <h3><i class="fas fa-info-circle"></i> Important Instructions</h3>
            <ul>
                <li><strong>Saturday & Sunday are WEEKENDS</strong> - Not counted in working days</li>
                <li><strong>Only HR VERIFIED attendance</strong> will be included in the statement</li>
                <li><strong>Director-locked months</strong> cannot be regenerated</li>
                <li><strong>Excel Format:</strong> Government Accounts standard with 13 columns</li>
                <li><strong>Separate Excel files</strong> will be generated for each employee category</li>
                <li><strong>Leave Calculation:</strong> EL, HPL, CCL, PL, CL, RH, OD/TOUR properly categorized</li>
            </ul>
        </div>

        <!-- Generation Form -->
        <div class="form-card">
            <h2 style="font-size: 20px; font-weight: 700; margin-bottom: 25px; color: var(--text);">
                <i class="fas fa-cogs"></i> Select Parameters
            </h2>
            
            <form action="../../app/Controllers/AttendanceStatementController.php" method="POST" id="generateForm">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="month_year">Month & Year <span class="required">*</span></label>
                        <select name="month_year" id="month_year" required>
                            <option value="">-- Select Month --</option>
                            <?php foreach ($availableMonths as $month): ?>
                                <option value="<?php echo $month['month_num'] . '-' . $month['year']; ?>">
                                    <?php echo $month['month_year']; ?> (Verified)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="employee_type">Employee Category <span class="required">*</span></label>
                        <select name="employee_type" id="employee_type" required>
                            <option value="">-- Select Category --</option>
                            <option value="Permanent">Permanent Employees</option>
                            <option value="Contractual">Contractual Employees</option>
                            <option value="Intern">Interns</option>
                        </select>
                    </div>
                </div>

                <button type="submit" class="generate-btn" <?php echo empty($availableMonths) ? 'disabled' : ''; ?>>
                    <i class="fas fa-file-excel"></i>
                    <span>Generate Attendance Statement Excel</span>
                </button>
            </form>
        </div>

        <!-- Recent Generations (Audit Log) -->
        <div class="audit-table">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
                <h2><i class="fas fa-history"></i> Recent Generations</h2>
                <button onclick="clearAuditLogs()" class="clear-btn" style="background: #ef4444; color: white; padding: 0.6rem 1.2rem; border: none; border-radius: 8px; cursor: pointer; font-size: 0.875rem; display: flex; align-items: center; gap: 0.5rem; transition: all 0.3s ease;">
                    <i class="fas fa-trash-alt"></i>
                    <span>Clear All</span>
                </button>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Date & Time</th>
                        <th>Month/Year</th>
                        <th>Employee Type</th>
                        <th>Generated By</th>
                        <th>File Name</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody id="auditTableBody">
                    <?php
                    try {
                        $stmt = $db->query("
                            SELECT 
                                al.log_id,
                                al.created_at,
                                al.month,
                                al.year,
                                al.employee_type,
                                al.file_name,
                                u.username
                            FROM audit_logs al
                            JOIN users u ON al.user_id = u.user_id
                            WHERE al.action = 'attendance_statement_generated'
                            ORDER BY al.created_at DESC
                            LIMIT 10
                        ");
                        $auditLogs = $stmt->fetchAll(PDO::FETCH_ASSOC);

                        if (empty($auditLogs)) {
                            echo '<tr id="emptyRow"><td colspan="6" style="text-align: center; padding: 30px; color: var(--muted);">
                                    <i class="fas fa-inbox" style="font-size: 32px; display: block; margin-bottom: 10px; opacity: 0.5;"></i>
                                    No statements generated yet
                                  </td></tr>';
                        } else {
                            foreach ($auditLogs as $log) {
                                $monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                                echo '<tr data-log-id="' . $log['log_id'] . '">';
                                echo '<td>' . date('d M Y, h:i A', strtotime($log['created_at'])) . '</td>';
                                echo '<td>' . $monthNames[$log['month']] . ' ' . $log['year'] . '</td>';
                                echo '<td><span style="background: #e0e7ff; color: #667eea; padding: 0.25rem 0.75rem; border-radius: 6px; font-size: 0.875rem; font-weight: 600;">' . htmlspecialchars($log['employee_type']) . '</span></td>';
                                echo '<td>' . htmlspecialchars($log['username']) . '</td>';
                                echo '<td><code style="background: #f1f5f9; padding: 0.25rem 0.5rem; border-radius: 4px; font-size: 0.813rem;">' . htmlspecialchars($log['file_name']) . '</code></td>';
                                echo '<td><button onclick="deleteAuditLog(' . $log['log_id'] . ')" class="delete-btn" style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; padding: 0.4rem 0.8rem; border-radius: 6px; cursor: pointer; font-size: 0.813rem; transition: all 0.2s ease;"><i class="fas fa-trash"></i></button></td>';
                                echo '</tr>';
                            }
                        }
                    } catch (Exception $e) {
                        echo '<tr><td colspan="6" style="text-align: center; padding: 20px; color: var(--danger);">Error loading audit logs</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php include 'includes/accountant_scripts.php'; ?>
    
    <!-- Loading Overlay -->
    <div id="loadingOverlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.7); z-index: 9999; justify-content: center; align-items: center;">
        <div style="background: white; padding: 2rem 3rem; border-radius: 15px; text-align: center; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
            <i class="fas fa-spinner fa-spin" style="font-size: 3rem; color: #667eea; margin-bottom: 1rem;"></i>
            <h3 style="color: #1e293b; margin: 1rem 0 0.5rem 0; font-size: 1.5rem;">Generating Excel</h3>
            <p style="color: #64748b; margin: 0; font-size: 1rem;">Please wait while we prepare your attendance statement...</p>
        </div>
    </div>
    
    <script>
        document.getElementById('generateForm').addEventListener('submit', function(e) {
            // Show loading overlay
            document.getElementById('loadingOverlay').style.display = 'flex';
            
            // Disable button
            const btn = this.querySelector('.generate-btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Generating Excel... Please wait</span>';
            
            // Re-enable after file download starts (3 seconds timeout)
            setTimeout(function() {
                document.getElementById('loadingOverlay').style.display = 'none';
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-file-excel"></i> <span>Generate Attendance Statement Excel</span>';
            }, 3000);
        });
        
        // Delete single audit log
        function deleteAuditLog(logId) {
            if (!confirm('Are you sure you want to delete this entry?')) return;
            
            fetch('<?php echo $baseURL; ?>index.php?page=delete-audit-log', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ log_id: logId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const row = document.querySelector(`tr[data-log-id="${logId}"]`);
                    row.style.animation = 'fadeOut 0.3s ease';
                    setTimeout(() => {
                        row.remove();
                        const tbody = document.getElementById('auditTableBody');
                        if (tbody.children.length === 0) {
                            tbody.innerHTML = '<tr id="emptyRow"><td colspan="6" style="text-align: center; padding: 30px; color: var(--muted);"><i class="fas fa-inbox" style="font-size: 32px; display: block; margin-bottom: 10px; opacity: 0.5;"></i>No statements generated yet</td></tr>';
                        }
                    }, 300);
                } else {
                    alert('Error deleting entry: ' + data.message);
                }
            })
            .catch(error => alert('Error: ' + error));
        }
        
        // Clear all audit logs
        function clearAuditLogs() {
            if (!confirm('Are you sure you want to clear ALL generation history? This action cannot be undone.')) return;
            
            fetch('<?php echo $baseURL; ?>index.php?page=clear-audit-logs', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const tbody = document.getElementById('auditTableBody');
                    tbody.innerHTML = '<tr id="emptyRow"><td colspan="6" style="text-align: center; padding: 30px; color: var(--muted);"><i class="fas fa-inbox" style="font-size: 32px; display: block; margin-bottom: 10px; opacity: 0.5;"></i>No statements generated yet</td></tr>';
                    alert('All audit logs cleared successfully!');
                } else {
                    alert('Error clearing logs: ' + data.message);
                }
            })
            .catch(error => alert('Error: ' + error));
        }
    </script>
    
    <style>
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        .delete-btn:hover {
            background: #dc2626 !important;
            color: white !important;
            border-color: #dc2626 !important;
        }
        .clear-btn:hover {
            background: #dc2626 !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
        }
    </style>
</body>
</html>
