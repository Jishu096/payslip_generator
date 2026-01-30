<?php
session_start();

// Check if user has employee role
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'employee') {
    header("Location: ../auth/login.php");
    exit;
}

$employeeId = $_SESSION['employee_id'] ?? null;
$username = $_SESSION['username'] ?? 'Employee';

require_once __DIR__ . '/../../app/Config/database.php';
$db = getDBConnection();

// Fetch employee documents
$query = "SELECT 
    ed.*,
    u.username as uploaded_by_user
FROM employee_documents ed
LEFT JOIN users u ON ed.uploaded_by = u.user_id
WHERE ed.employee_id = :employee_id
ORDER BY ed.uploaded_at DESC";

$stmt = $db->prepare($query);
$stmt->execute([':employee_id' => $employeeId]);
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

$baseURL = "/payslip_generator/public/";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Documents - Employee Portal</title>
    <?php include 'includes/employee_styles.php'; ?>
    <style>
        .documents-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .document-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-left: 4px solid var(--accent);
        }

        .document-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
        }

        .document-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 28px;
            margin-bottom: 15px;
        }

        .document-name {
            font-size: 16px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 8px;
        }

        .document-meta {
            font-size: 13px;
            color: var(--muted);
            margin-bottom: 5px;
        }

        .document-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        .btn-download, .btn-view {
            flex: 1;
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
        }

        .btn-download {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
        }

        .btn-view {
            background: #f7fafc;
            color: var(--text);
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
    <?php include 'includes/employee_navbar.php'; ?>
    <?php include 'includes/employee_sidebar.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h1><i class="fas fa-file-alt"></i> My Documents</h1>
            <p>View and download your employment documents</p>
        </div>

        <?php if (empty($documents)): ?>
            <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <h3>No Documents Available</h3>
                <p>You don't have any documents uploaded yet. Contact HR if you need documents.</p>
            </div>
        <?php else: ?>
            <div class="documents-grid">
                <?php foreach ($documents as $doc): ?>
                    <div class="document-card">
                        <div class="document-icon">
                            <i class="fas fa-file-<?php echo getFileIcon($doc['file_type']); ?>"></i>
                        </div>
                        <div class="document-name"><?php echo htmlspecialchars($doc['document_name']); ?></div>
                        <div class="document-meta">
                            <i class="fas fa-calendar"></i>
                            <?php echo date('d M Y', strtotime($doc['uploaded_at'])); ?>
                        </div>
                        <div class="document-meta">
                            <i class="fas fa-tag"></i>
                            <?php echo htmlspecialchars($doc['document_type']); ?>
                        </div>
                        <?php if ($doc['file_size']): ?>
                            <div class="document-meta">
                                <i class="fas fa-hdd"></i>
                                <?php echo formatFileSize($doc['file_size']); ?>
                            </div>
                        <?php endif; ?>
                        <div class="document-actions">
                            <a href="<?php echo $baseURL; ?>api/download_document.php?id=<?php echo $doc['document_id']; ?>" 
                               class="btn-download" download>
                                <i class="fas fa-download"></i> Download
                            </a>
                            <?php if (in_array($doc['file_type'], ['pdf', 'jpg', 'jpeg', 'png'])): ?>
                                <a href="<?php echo $baseURL; ?>api/view_document.php?id=<?php echo $doc['document_id']; ?>" 
                                   class="btn-view" target="_blank">
                                    <i class="fas fa-eye"></i> View
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/employee_scripts.php'; ?>
</body>
</html>

<?php
function getFileIcon($fileType) {
    $icons = [
        'pdf' => 'pdf',
        'doc' => 'word',
        'docx' => 'word',
        'xls' => 'excel',
        'xlsx' => 'excel',
        'jpg' => 'image',
        'jpeg' => 'image',
        'png' => 'image',
        'zip' => 'archive'
    ];
    return $icons[strtolower($fileType)] ?? 'alt';
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>
