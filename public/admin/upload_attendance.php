<?php
session_start();

$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? ''];
$hasAdminRole = in_array('administrator', $userRoles);

if (!isset($_SESSION['role']) || (!$hasAdminRole && $_SESSION['role'] !== 'administrator')) {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . '/../../app/Config/database.php';
$db = getDBConnection();
$username = $_SESSION['username'] ?? 'Admin';

// Handle PDF upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['attendance_pdf'])) {
    $month = $_POST['month'] ?? '';
    $year = $_POST['year'] ?? '';
    
    if ($month && $year && $_FILES['attendance_pdf']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../../storage/attendance/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = 'attendance_' . $month . '_' . $year . '_' . time() . '.pdf';
        $filePath = $uploadDir . $fileName;
        
        if (move_uploaded_file($_FILES['attendance_pdf']['tmp_name'], $filePath)) {
            try {
                // Insert upload record
                $stmt = $db->prepare("
                    INSERT INTO attendance_uploads 
                    (month, year, file_path, uploaded_by, status, uploaded_at) 
                    VALUES (?, ?, ?, ?, 'UPLOADED', NOW())
                ");
                $stmt->execute([$month, $year, $fileName, $_SESSION['user_id']]);
                
                $_SESSION['success_message'] = "Attendance statement uploaded successfully! HR Officer will be notified.";
            } catch (Exception $e) {
                $_SESSION['error_message'] = 'Error saving upload record: ' . $e->getMessage();
            }
        } else {
            $_SESSION['error_message'] = 'Error uploading file.';
        }
    } else {
        $_SESSION['error_message'] = 'Please fill all fields and select a valid PDF file.';
    }
    
    header("Location: upload_attendance.php");
    exit();
}

// Get recent uploads
try {
    $stmt = $db->query("
        SELECT 
            au.*,
            u.username as uploaded_by_name
        FROM attendance_uploads au
        LEFT JOIN users u ON au.uploaded_by = u.user_id
        ORDER BY au.uploaded_at DESC
        LIMIT 10
    ");
    $recentUploads = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recentUploads = [];
}

$months = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
$years = range(date('Y'), date('Y') - 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Attendance - Admin</title>
    <?php include 'includes/admin_styles.php'; ?>
</head>
<body>
    <?php include 'includes/admin_navbar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <div>
                <h1><i class="fas fa-upload"></i> Upload Absentee Statement</h1>
                <p>Upload PDF attendance statements for HR verification</p>
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

        <!-- Upload Form -->
        <div class="upload-card">
            <div class="card-header">
                <h2><i class="fas fa-file-upload"></i> Upload New Statement</h2>
            </div>
            <form method="POST" enctype="multipart/form-data" class="upload-form">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="month">Month <span class="required">*</span></label>
                        <select name="month" id="month" required>
                            <option value="">Select Month</option>
                            <?php foreach ($months as $m): ?>
                                <option value="<?php echo $m; ?>"><?php echo $m; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="year">Year <span class="required">*</span></label>
                        <select name="year" id="year" required>
                            <option value="">Select Year</option>
                            <?php foreach ($years as $y): ?>
                                <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="attendance_pdf">Absentee PDF Statement <span class="required">*</span></label>
                    <div class="file-upload">
                        <input type="file" name="attendance_pdf" id="attendance_pdf" accept=".pdf" required>
                        <div class="file-upload-label">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <span>Click to upload or drag and drop</span>
                            <small>PDF files only (Max 10MB)</small>
                        </div>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-upload"></i> Upload Statement
                </button>
            </form>
        </div>

        <!-- Recent Uploads -->
        <div class="table-card">
            <div class="card-header">
                <h2><i class="fas fa-history"></i> Recent Uploads</h2>
            </div>
            <div class="table-container">
                <?php if (empty($recentUploads)): ?>
                    <div style="text-align: center; padding: 60px; color: var(--muted);">
                        <i class="fas fa-inbox" style="font-size: 56px; opacity: 0.5; display: block; margin-bottom: 20px;"></i>
                        <h3 style="font-size: 20px; margin-bottom: 10px;">No Uploads Yet</h3>
                        <p>Upload your first attendance statement above</p>
                    </div>
                <?php else: ?>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Period</th>
                                <th>File Name</th>
                                <th>Uploaded By</th>
                                <th>Upload Date</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentUploads as $upload): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($upload['month'] . ' ' . $upload['year']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($upload['file_path']); ?></td>
                                    <td><?php echo htmlspecialchars($upload['uploaded_by_name'] ?? 'Unknown'); ?></td>
                                    <td><?php echo date('d M Y, H:i', strtotime($upload['uploaded_at'])); ?></td>
                                    <td>
                                        <?php
                                        $statusColors = [
                                            'UPLOADED' => 'info',
                                            'VERIFIED' => 'success',
                                            'REJECTED' => 'danger'
                                        ];
                                        $badgeClass = $statusColors[$upload['status']] ?? 'info';
                                        ?>
                                        <span class="badge badge-<?php echo $badgeClass; ?>">
                                            <?php echo htmlspecialchars($upload['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="../../storage/attendance/<?php echo htmlspecialchars($upload['file_path']); ?>" 
                                           class="btn-small btn-primary" target="_blank">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Info Box -->
        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <div>
                <h3>Upload Instructions</h3>
                <p><strong>Step 1:</strong> Select the month and year for the attendance statement</p>
                <p><strong>Step 2:</strong> Upload the PDF absentee statement from the attendance system</p>
                <p><strong>Step 3:</strong> System will notify HR Officer for verification</p>
                <p><strong>Step 4:</strong> HR Officer will convert and verify the data</p>
            </div>
        </div>
    </div>

    <?php include 'includes/admin_scripts.php'; ?>
    <style>
        .upload-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }

        .upload-form {
            max-width: 800px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text);
            font-size: 14px;
        }

        .required {
            color: var(--danger);
        }

        .form-group select,
        .form-group input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
        }

        .file-upload {
            position: relative;
        }

        .file-upload input[type="file"] {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }

        .file-upload-label {
            border: 2px dashed var(--border);
            border-radius: 12px;
            padding: 40px;
            text-align: center;
            background: #f8fafc;
            transition: all 0.3s;
        }

        .file-upload:hover .file-upload-label {
            border-color: var(--accent);
            background: #f0f4ff;
        }

        .file-upload-label i {
            font-size: 48px;
            color: var(--accent);
            display: block;
            margin-bottom: 15px;
        }

        .file-upload-label span {
            display: block;
            font-size: 16px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 8px;
        }

        .file-upload-label small {
            color: var(--muted);
            font-size: 13px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
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

        .info-box {
            background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
            border-radius: 12px;
            padding: 25px;
            display: flex;
            gap: 20px;
            align-items: start;
        }

        .info-box i {
            font-size: 32px;
            color: var(--accent);
        }

        .info-box h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 10px;
        }

        .info-box p {
            margin: 8px 0;
            color: var(--text);
            font-size: 14px;
        }

        .btn-small {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
        }

        .btn-small.btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
        }

        .btn-small.btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }

        .badge {
            padding: 6px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .badge-info {
            background: #dbeafe;
            color: #1e40af;
        }

        .badge-success {
            background: #d1fae5;
            color: #065f46;
        }

        .badge-danger {
            background: #fee2e2;
            color: #991b1b;
        }
    </style>
</body>
</html>
