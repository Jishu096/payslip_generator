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

// Get finalized months available for export
$query = "SELECT 
    DATE_FORMAT(a.date, '%M') as month,
    YEAR(a.date) as year,
    COUNT(*) as total_records,
    aml.is_locked,
    aml.locked_at,
    MAX(ael.exported_at) as last_exported
FROM attendance a
JOIN attendance_month_lock aml ON DATE_FORMAT(a.date, '%M') = aml.month AND YEAR(a.date) = aml.year
LEFT JOIN attendance_export_log ael ON DATE_FORMAT(a.date, '%M') = ael.month AND YEAR(a.date) = ael.year
WHERE aml.is_locked = 1 AND a.workflow_status = 'admin_finalized'
GROUP BY DATE_FORMAT(a.date, '%Y-%m'), DATE_FORMAT(a.date, '%M'), YEAR(a.date), aml.is_locked, aml.locked_at
ORDER BY YEAR(a.date) DESC, MONTH(a.date) DESC";

$stmt = $db->query($query);
$availableMonths = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get export history
$historyQuery = "SELECT 
    ael.*,
    u.username as exported_by_user
FROM attendance_export_log ael
JOIN users u ON ael.exported_by = u.user_id
ORDER BY ael.exported_at DESC
LIMIT 50";

$historyStmt = $db->query($historyQuery);
$exportHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

$baseURL = "/payslip_generator/public/";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Export - Admin Portal</title>
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        .page-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            padding: 40px;
            border-radius: 20px;
            margin-bottom: 30px;
            color: white;
            box-shadow: 0 10px 40px rgba(16, 185, 129, 0.3);
        }

        .page-header h1 {
            color: white;
            margin: 0 0 10px 0;
            font-size: 28px;
            font-weight: 700;
        }

        .page-header p {
            color: rgba(255, 255, 255, 0.9);
            margin: 0;
            font-size: 16px;
        }

        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-box {
            background: white;
            border-radius: 16px;
            padding: 25px;
            text-align: center;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }

        .stat-box:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(0,0,0,0.12);
        }

        .stat-box i {
            font-size: 32px;
            margin-bottom: 12px;
        }

        .stat-box.green i { color: #10b981; }
        .stat-box.blue i { color: #3b82f6; }
        .stat-box.purple i { color: #8b5cf6; }
        .stat-box.orange i { color: #f59e0b; }

        .stat-box .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 5px;
        }

        .stat-box .stat-label {
            font-size: 13px;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }

        .section-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
            margin: 30px 0 20px 0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: #10b981;
        }

        .export-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .export-card {
            background: white;
            border-radius: 16px;
            padding: 28px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .export-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #10b981, #059669);
        }

        .export-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 40px rgba(16, 185, 129, 0.2);
        }

        .month-title {
            font-size: 22px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .month-title i {
            color: #10b981;
            font-size: 20px;
        }

        .export-info {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 25px;
            padding: 15px;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 12px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
        }

        .info-label {
            color: var(--muted);
            font-weight: 500;
        }

        .info-value {
            font-weight: 600;
            color: var(--text);
        }

        .export-actions {
            display: flex;
            gap: 12px;
        }

        .btn-export {
            flex: 1;
            padding: 14px 20px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.3s ease;
        }

        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
        }

        .btn-csv {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }

        .btn-csv:hover {
            box-shadow: 0 8px 25px rgba(59, 130, 246, 0.4);
        }

        .history-section {
            background: white;
            padding: 30px;
            border-radius: 16px;
            margin-top: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .history-section h2 {
            margin: 0 0 25px 0;
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .history-section h2 i {
            color: #10b981;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #f1f5f9;
        }

        th {
            font-weight: 600;
            text-transform: uppercase;
            font-size: 11px;
            letter-spacing: 0.5px;
            color: #64748b;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
        }

        tbody tr {
            transition: background 0.2s ease;
        }

        tbody tr:hover {
            background: #f8fafc;
        }

        .download-link {
            color: #10b981;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            background: rgba(16, 185, 129, 0.1);
            border-radius: 8px;
            transition: all 0.3s ease;
        }

        .download-link:hover {
            background: #10b981;
            color: white;
        }

        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-excel {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .badge-csv {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .empty-state i {
            font-size: 80px;
            color: #cbd5e1;
            margin-bottom: 25px;
        }

        .empty-state h3 {
            font-size: 22px;
            color: var(--text);
            margin-bottom: 10px;
        }

        .empty-state p {
            color: var(--muted);
            margin-bottom: 25px;
        }

        .empty-state .btn-export {
            width: 220px;
            margin: 0 auto;
        }

        .quick-tips {
            background: linear-gradient(135deg, #f0fdf4, #dcfce7);
            border-radius: 16px;
            padding: 25px;
            margin-bottom: 30px;
        }

        .quick-tips h3 {
            font-size: 16px;
            font-weight: 700;
            color: #166534;
            margin: 0 0 15px 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .quick-tips ul {
            margin: 0;
            padding-left: 20px;
            color: #15803d;
        }

        .quick-tips li {
            margin-bottom: 8px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_navbar.php'; ?>

    <div class="main-content" id="mainContent">
        <div class="page-header">
            <h1><i class="fas fa-file-export"></i> Attendance Export</h1>
            <p>Export finalized attendance data to Excel or CSV for payroll processing</p>
        </div>

        <!-- Stats Overview -->
        <div class="stats-overview">
            <div class="stat-box green">
                <i class="fas fa-calendar-check"></i>
                <div class="stat-value"><?php echo count($availableMonths); ?></div>
                <div class="stat-label">Ready to Export</div>
            </div>
            <div class="stat-box blue">
                <i class="fas fa-download"></i>
                <div class="stat-value"><?php echo count($exportHistory); ?></div>
                <div class="stat-label">Total Exports</div>
            </div>
            <div class="stat-box purple">
                <i class="fas fa-file-excel"></i>
                <div class="stat-value"><?php echo count(array_filter($exportHistory, fn($e) => $e['export_format'] === 'excel')); ?></div>
                <div class="stat-label">Excel Files</div>
            </div>
            <div class="stat-box orange">
                <i class="fas fa-file-csv"></i>
                <div class="stat-value"><?php echo count(array_filter($exportHistory, fn($e) => $e['export_format'] === 'csv')); ?></div>
                <div class="stat-label">CSV Files</div>
            </div>
        </div>

        <!-- Quick Tips -->
        <div class="quick-tips">
            <h3><i class="fas fa-lightbulb"></i> Quick Tips</h3>
            <ul>
                <li><strong>Excel format</strong> is recommended for Accountants - includes formatting and formulas</li>
                <li><strong>CSV format</strong> is best for importing into other systems</li>
                <li>Exported files are stored in the system and can be re-downloaded from history</li>
            </ul>
        </div>

        <?php if (empty($availableMonths)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>No Finalized Months Available</h3>
                <p>Please finalize attendance months first before exporting.</p>
                <a href="attendance_finalize.php" class="btn-export">
                    <i class="fas fa-lock"></i> Go to Finalization
                </a>
            </div>
        <?php else: ?>
            <h2 class="section-title"><i class="fas fa-download"></i> Available for Export</h2>
            <div class="export-grid">
                <?php foreach($availableMonths as $month): ?>
                    <div class="export-card">
                        <div class="month-title">
                            <i class="fas fa-calendar-check"></i>
                            <?php echo $month['month'] . ' ' . $month['year']; ?>
                        </div>

                        <div class="export-info">
                            <div class="info-row">
                                <span class="info-label"><i class="fas fa-database"></i> Total Records:</span>
                                <span class="info-value"><?php echo number_format($month['total_records']); ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label"><i class="fas fa-lock"></i> Finalized:</span>
                                <span class="info-value"><?php echo date('d M Y', strtotime($month['locked_at'])); ?></span>
                            </div>
                            <?php if ($month['last_exported']): ?>
                                <div class="info-row">
                                    <span class="info-label"><i class="fas fa-history"></i> Last Exported:</span>
                                    <span class="info-value"><?php echo date('d M Y', strtotime($month['last_exported'])); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="export-actions">
                            <button class="btn-export" 
                                    onclick="exportAttendance('<?php echo $month['month']; ?>', <?php echo $month['year']; ?>, 'excel')">
                                <i class="fas fa-file-excel"></i> Excel
                            </button>
                            <button class="btn-export btn-csv" 
                                    onclick="exportAttendance('<?php echo $month['month']; ?>', <?php echo $month['year']; ?>, 'csv')">
                                <i class="fas fa-file-csv"></i> CSV
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Export History -->
        <div class="history-section">
            <h2><i class="fas fa-history"></i> Export History</h2>
            <?php if (empty($exportHistory)): ?>
                <p style="text-align: center; color: var(--muted); padding: 40px;">No exports yet. Export your first month above!</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Month/Year</th>
                            <th>Format</th>
                            <th>Records</th>
                            <th>Exported By</th>
                            <th>Exported At</th>
                            <th>Download</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($exportHistory as $export): ?>
                            <tr>
                                <td><strong><?php echo $export['month'] . ' ' . $export['year']; ?></strong></td>
                                <td>
                                    <span class="badge badge-<?php echo $export['export_format']; ?>">
                                        <?php echo strtoupper($export['export_format']); ?>
                                    </span>
                                </td>
                                <td><?php echo $export['record_count']; ?></td>
                                <td><?php echo htmlspecialchars($export['exported_by_user']); ?></td>
                                <td><?php echo date('d M Y, h:i A', strtotime($export['exported_at'])); ?></td>
                                <td>
                                    <?php if ($export['file_path'] && file_exists(__DIR__ . '/../../storage/' . $export['file_path'])): ?>
                                        <a href="<?php echo $baseURL; ?>api/download_export.php?id=<?php echo $export['export_id']; ?>" 
                                           class="download-link">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                    <?php else: ?>
                                        <span style="color: var(--muted);">File not available</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'includes/admin_scripts.php'; ?>
    <script>
        function exportAttendance(month, year, format) {
            if (!confirm(`Export ${month} ${year} as ${format.toUpperCase()}?\n\nThis will create a new export file for the Accountant.`)) {
                return;
            }

            // Show loading
            const btn = event.target.closest('button');
            const originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Exporting...';
            btn.disabled = true;

            fetch('api/export_attendance.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ month, year, format })
            })
            .then(response => {
                // Check if response is ok
                if (!response.ok) {
                    return response.text().then(text => {
                        throw new Error(`HTTP ${response.status}: ${text.substring(0, 200)}`);
                    });
                }
                return response.text().then(text => {
                    try {
                        return JSON.parse(text);
                    } catch (e) {
                        console.error('Response text:', text);
                        throw new Error('Invalid JSON response: ' + text.substring(0, 200));
                    }
                });
            })
            .then(data => {
                if (data.success) {
                    alert('✅ Export successful!\n\nFile: ' + data.filename);
                    // Download the file
                    window.location.href = data.download_url;
                    setTimeout(() => location.reload(), 2000);
                } else {
                    alert('❌ Export failed: ' + data.message);
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                }
            })
            .catch(error => {
                alert('❌ Error: ' + error.message);
                btn.innerHTML = originalHtml;
                btn.disabled = false;
            });
        }
    </script>
</body>
</html>
