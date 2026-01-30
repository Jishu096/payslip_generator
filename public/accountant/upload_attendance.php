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
$userId = $_SESSION['user_id'];

require_once __DIR__ . '/../../app/Config/database.php';
$db = getDBConnection();

// Get available exports from Admin
$query = "SELECT 
    ael.*,
    u.username as exported_by_user,
    COUNT(p.payroll_id) as salary_calculated
FROM attendance_export_log ael
JOIN users u ON ael.exported_by = u.user_id
LEFT JOIN payroll p ON ael.month = p.month AND ael.year = p.year
GROUP BY ael.export_id, ael.month, ael.year, ael.exported_by, ael.exported_at, ael.file_path, ael.record_count, ael.export_format, u.username
ORDER BY ael.exported_at DESC
LIMIT 20";

$stmt = $db->query($query);
$availableExports = $stmt->fetchAll(PDO::FETCH_ASSOC);

$baseURL = "/payslip_generator/public/";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Attendance - Accountant Portal</title>
    <?php include 'includes/accountant_styles.php'; ?>
    <style>
        .upload-section {
            background: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .upload-zone {
            border: 3px dashed #cbd5e1;
            border-radius: 12px;
            padding: 60px 20px;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .upload-zone:hover {
            border-color: var(--accent);
            background: #f7f5ff;
        }

        .upload-zone.dragover {
            border-color: var(--accent);
            background: #eef2ff;
        }

        .upload-icon {
            font-size: 64px;
            color: var(--accent);
            margin-bottom: 20px;
        }

        .exports-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .export-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            border-left: 4px solid var(--accent);
        }

        .card-header {
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

        .status-badge {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
        }

        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-imported {
            background: #d1fae5;
            color: #065f46;
        }

        .card-info {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
        }

        .btn-import {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-import:disabled {
            background: #e2e8f0;
            color: #94a3b8;
            cursor: not-allowed;
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e3a8a;
            border-left: 4px solid #3b82f6;
        }

        #fileInput {
            display: none;
        }
    </style>
</head>
<body>
    <?php include 'includes/accountant_navbar.php'; ?>
    <?php include 'includes/accountant_sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-file-import"></i> Import Attendance Data</h1>
            <p>Import finalized attendance from Admin for salary calculation</p>
        </div>

        <div class="alert alert-info">
            <strong><i class="fas fa-info-circle"></i> Workflow:</strong>
            Admin exports finalized attendance → Import here → Use imported data for salary calculation
        </div>

        <!-- Manual Upload Section -->
        <div class="upload-section">
            <h2 style="margin-bottom: 20px;"><i class="fas fa-upload"></i> Manual Upload (Optional)</h2>
            <div class="upload-zone" id="uploadZone" onclick="document.getElementById('fileInput').click()">
                <div class="upload-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                <h3>Drag and drop Excel file here</h3>
                <p style="color: var(--muted);">or click to browse</p>
                <p style="font-size: 12px; color: var(--muted); margin-top: 10px;">Supported formats: .xlsx, .xls</p>
            </div>
            <input type="file" id="fileInput" accept=".xlsx,.xls" onchange="handleFileUpload(event)">
        </div>

        <!-- Available Exports from Admin -->
        <h2 style="margin-top: 40px; margin-bottom: 20px;">Available Exports from Admin</h2>
        <?php if (empty($availableExports)): ?>
            <div class="upload-section" style="text-align: center; padding: 60px;">
                <i class="fas fa-inbox" style="font-size: 64px; color: var(--muted); margin-bottom: 20px;"></i>
                <h3>No Exports Available</h3>
                <p style="color: var(--muted);">Waiting for Admin to export finalized attendance data.</p>
            </div>
        <?php else: ?>
            <div class="exports-grid">
                <?php foreach($availableExports as $export): ?>
                    <div class="export-card">
                        <div class="card-header">
                            <div class="month-title"><?php echo $export['month'] . ' ' . $export['year']; ?></div>
                            <span class="status-badge <?php echo $export['salary_calculated'] > 0 ? 'status-imported' : 'status-pending'; ?>">
                                <?php echo $export['salary_calculated'] > 0 ? '✓ Imported' : 'Pending'; ?>
                            </span>
                        </div>

                        <div class="card-info">
                            <div class="info-row">
                                <span style="color: var(--muted);">Records:</span>
                                <strong><?php echo $export['record_count']; ?></strong>
                            </div>
                            <div class="info-row">
                                <span style="color: var(--muted);">Format:</span>
                                <strong><?php echo strtoupper($export['export_format']); ?></strong>
                            </div>
                            <div class="info-row">
                                <span style="color: var(--muted);">Exported By:</span>
                                <strong><?php echo htmlspecialchars($export['exported_by_user']); ?></strong>
                            </div>
                            <div class="info-row">
                                <span style="color: var(--muted);">Export Date:</span>
                                <strong><?php echo date('d M Y', strtotime($export['exported_at'])); ?></strong>
                            </div>
                            <?php if ($export['salary_calculated'] > 0): ?>
                                <div class="info-row">
                                    <span style="color: var(--muted);">Salaries Calculated:</span>
                                    <strong style="color: #10b981;"><?php echo $export['salary_calculated']; ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>

                        <button class="btn-import" 
                                onclick="importFromExport(<?php echo $export['export_id']; ?>, '<?php echo $export['month']; ?>', <?php echo $export['year']; ?>)"
                                <?php echo $export['salary_calculated'] > 0 ? 'disabled' : ''; ?>>
                            <?php if ($export['salary_calculated'] > 0): ?>
                                <i class="fas fa-check"></i> Already Imported
                            <?php else: ?>
                                <i class="fas fa-download"></i> Import & Calculate
                            <?php endif; ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/accountant_scripts.php'; ?>
    <script>
        // Drag and drop functionality
        const uploadZone = document.getElementById('uploadZone');

        uploadZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });

        uploadZone.addEventListener('dragleave', () => {
            uploadZone.classList.remove('dragover');
        });

        uploadZone.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleFile(files[0]);
            }
        });

        function handleFileUpload(event) {
            const file = event.target.files[0];
            if (file) {
                handleFile(file);
            }
        }

        function handleFile(file) {
            if (!file.name.match(/\.(xlsx|xls)$/)) {
                alert('Please upload an Excel file (.xlsx or .xls)');
                return;
            }

            const formData = new FormData();
            formData.append('file', file);

            // Show loading
            uploadZone.innerHTML = '<div class="upload-icon"><i class="fas fa-spinner fa-spin"></i></div><h3>Uploading...</h3>';

            fetch('api/upload_attendance_file.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ File uploaded successfully!\nRecords imported: ' + data.imported_count);
                    location.reload();
                } else {
                    alert('❌ Upload failed: ' + data.message);
                    location.reload();
                }
            })
            .catch(error => {
                alert('❌ Error: ' + error.message);
                location.reload();
            });
        }

        function importFromExport(exportId, month, year) {
            if (!confirm(`Import attendance data for ${month} ${year}?\n\nThis will make the data available for salary calculation.`)) {
                return;
            }

            fetch('api/import_attendance_from_export.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ export_id: exportId, month, year })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ Import successful!\nRecords loaded: ' + data.record_count + '\n\nYou can now proceed to salary calculation.');
                    location.href = 'calculate_salary.php?month=' + month + '&year=' + year;
                } else {
                    alert('❌ Import failed: ' + data.message);
                }
            });
        }
    </script>
</body>
</html>
