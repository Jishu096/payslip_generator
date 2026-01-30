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
        .export-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .export-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid #10b981;
        }

        .month-title {
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 15px;
        }

        .export-info {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 20px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            font-size: 14px;
        }

        .info-label {
            color: var(--muted);
        }

        .info-value {
            font-weight: 600;
            color: var(--text);
        }

        .export-actions {
            display: flex;
            gap: 10px;
        }

        .btn-export {
            flex: 1;
            padding: 12px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
        }

        .btn-csv {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }

        .history-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-top: 30px;
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

        .download-link {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }

        .download-link:hover {
            text-decoration: underline;
        }

        .badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-excel {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-csv {
            background: #dbeafe;
            color: #1e3a8a;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e3a8a;
            border-color: #3b82f6;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 12px;
        }

        .empty-state i {
            font-size: 64px;
            color: var(--muted);
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_navbar.php'; ?>

    <div class="main-content" id="mainContent">
        <div class="page-header">
            <h1><i class="fas fa-file-export"></i> Attendance Export</h1>
            <p>Export finalized attendance data to Excel for Accountant</p>
        </div>

        <div class="alert alert-info">
            <strong><i class="fas fa-info-circle"></i> Workflow:</strong>
            Export finalized attendance as Excel file → Send to Accountant → Accountant imports for salary calculation
        </div>

        <?php if (empty($availableMonths)): ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h3>No Finalized Months Available</h3>
                <p>Please finalize attendance months first before exporting.</p>
                <a href="attendance_finalize.php" class="btn-export" style="width: 200px; margin: 20px auto;">
                    Go to Finalization
                </a>
            </div>
        <?php else: ?>
            <h2 style="margin-top: 30px; margin-bottom: 20px;">Available for Export</h2>
            <div class="export-grid">
                <?php foreach($availableMonths as $month): ?>
                    <div class="export-card">
                        <div class="month-title">
                            <i class="fas fa-calendar-check"></i>
                            <?php echo $month['month'] . ' ' . $month['year']; ?>
                        </div>

                        <div class="export-info">
                            <div class="info-row">
                                <span class="info-label">Total Records:</span>
                                <span class="info-value"><?php echo $month['total_records']; ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Finalized:</span>
                                <span class="info-value"><?php echo date('d M Y', strtotime($month['locked_at'])); ?></span>
                            </div>
                            <?php if ($month['last_exported']): ?>
                                <div class="info-row">
                                    <span class="info-label">Last Exported:</span>
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

        <h2 style="margin-top: 40px; margin-bottom: 20px;">Export History</h2>
        <div class="history-section">
            <?php if (empty($exportHistory)): ?>
                <p style="text-align: center; color: var(--muted);">No exports yet</p>
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
