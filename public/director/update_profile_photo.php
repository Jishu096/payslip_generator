<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'director') {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . '/../../app/Config/database.php';

$db = getDBConnection();
$userId = $_SESSION['user_id'] ?? null;
$message = '';
$error = '';

// Check for success message
if (isset($_GET['success']) && $_GET['success'] === '1') {
    $message = 'Profile photo updated successfully!';
}

// Get current employee info
$stmt = $db->prepare("SELECT e.employee_id, e.full_name, e.profile_photo FROM users u JOIN employees e ON u.employee_id = e.employee_id WHERE u.user_id = ?");
$stmt->execute([$userId]);
$employee = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle photo upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_photo'])) {
    $file = $_FILES['profile_photo'];
    
    // Validate file
    if ($file['error'] === UPLOAD_ERR_OK) {
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $maxSize = 2 * 1024 * 1024; // 2MB (matching PHP upload_max_filesize)
        
        if (!in_array($file['type'], $allowedTypes)) {
            $error = 'Only JPG, PNG, and GIF images are allowed.';
        } elseif ($file['size'] > $maxSize) {
            $error = 'File size must be less than 2MB.';
        } else {
            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'profile_' . $employee['employee_id'] . '_' . time() . '.' . $extension;
            $uploadPath = __DIR__ . '/../assets/uploads/profile_photos/' . $filename;
            
            // Ensure directory exists with proper permissions
            $uploadDir = __DIR__ . '/../assets/uploads/profile_photos/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            // Make sure directory is writable
            if (!is_writable($uploadDir)) {
                chmod($uploadDir, 0777);
            }
            
            // Delete old photo if exists
            if ($employee['profile_photo'] && file_exists(__DIR__ . '/../assets/uploads/profile_photos/' . $employee['profile_photo'])) {
                unlink(__DIR__ . '/../assets/uploads/profile_photos/' . $employee['profile_photo']);
            }
            
            // Move uploaded file
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                // Update database
                $stmt = $db->prepare("UPDATE employees SET profile_photo = ? WHERE employee_id = ?");
                if ($stmt->execute([$filename, $employee['employee_id']])) {
                    $message = 'Profile photo updated successfully!';
                    $employee['profile_photo'] = $filename;
                    // Redirect to avoid form resubmission
                    header("Location: update_profile_photo.php?success=1");
                    exit;
                } else {
                    $error = 'Failed to update database.';
                }
            } else {
                $error = 'Failed to upload file. Please check directory permissions.';
            }
        }
    } else {
        $error = 'Upload error: ' . $file['error'];
    }
}

// Handle photo removal
if (isset($_GET['remove']) && $_GET['remove'] === '1') {
    if ($employee['profile_photo']) {
        $photoPath = __DIR__ . '/../assets/uploads/profile_photos/' . $employee['profile_photo'];
        if (file_exists($photoPath)) {
            unlink($photoPath);
        }
        
        $stmt = $db->prepare("UPDATE employees SET profile_photo = NULL WHERE employee_id = ?");
        if ($stmt->execute([$employee['employee_id']])) {
            $message = 'Profile photo removed successfully!';
            $employee['profile_photo'] = null;
        }
    }
}

$baseURL = "/payslip_generator/public/";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Profile Photo - Director Portal</title>
    <?php include 'includes/director_styles.php'; ?>
    <style>
        .upload-container {
            max-width: 600px;
            margin: 0 auto;
        }

        .current-photo {
            text-align: center;
            margin-bottom: 30px;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .photo-preview {
            width: 200px;
            height: 200px;
            margin: 0 auto 20px;
            border-radius: 50%;
            overflow: hidden;
            border: 4px solid var(--accent);
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .photo-preview img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .photo-preview i {
            font-size: 80px;
            color: var(--muted);
        }

        .upload-form {
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--text);
        }

        .file-input-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }

        .file-input-wrapper input[type="file"] {
            position: absolute;
            left: -9999px;
        }

        .file-input-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 15px 25px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
        }

        .file-input-label:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .file-name {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
            font-size: 14px;
            color: var(--muted);
            text-align: center;
        }

        .btn-group {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }

        .btn {
            flex: 1;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            text-align: center;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-danger {
            background: #dc3545;
            color: white;
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .upload-instructions {
            background: #e7f3ff;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid var(--accent);
            margin-bottom: 20px;
        }

        .upload-instructions h4 {
            margin-bottom: 10px;
            color: var(--accent);
        }

        .upload-instructions ul {
            margin-left: 20px;
            color: #555;
        }

        .upload-instructions li {
            margin-bottom: 5px;
        }
    </style>
</head>
<body>
    <?php include 'includes/director_sidebar.php'; ?>

    <div class="main-content">
        <div class="content-header">
            <div>
                <h1><i class="fas fa-camera"></i> Update Profile Photo</h1>
                <p>Upload or update your profile picture</p>
            </div>
        </div>

        <div class="upload-container">
            <?php if ($message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Current Photo -->
            <div class="current-photo">
                <h3 style="margin-bottom: 20px;">Current Photo</h3>
                <div class="photo-preview">
                    <?php if ($employee['profile_photo']): ?>
                        <img src="<?php echo $baseURL; ?>assets/uploads/profile_photos/<?php echo htmlspecialchars($employee['profile_photo']); ?>" alt="Profile Photo">
                    <?php else: ?>
                        <i class="fas fa-user-tie"></i>
                    <?php endif; ?>
                </div>
                <p style="color: var(--muted); margin-bottom: 15px;">
                    <?php echo htmlspecialchars($employee['full_name']); ?>
                </p>
                <?php if ($employee['profile_photo']): ?>
                    <a href="?remove=1" class="btn btn-danger" onclick="return confirm('Are you sure you want to remove your profile photo?');">
                        <i class="fas fa-trash"></i> Remove Photo
                    </a>
                <?php endif; ?>
            </div>

            <!-- Upload Instructions -->
            <div class="upload-instructions">
                <h4><i class="fas fa-info-circle"></i> Upload Guidelines</h4>
                <ul>
                    <li>Allowed formats: JPG, PNG, GIF</li>
                    <li>Maximum file size: 2MB</li>
                    <li>Recommended: Square image (1:1 ratio) for best results</li>
                    <li>Minimum dimensions: 200x200 pixels</li>
                </ul>
            </div>

            <!-- Upload Form -->
            <div class="upload-form">
                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                    <div class="form-group">
                        <label for="profile_photo">
                            <i class="fas fa-upload"></i> Choose Photo
                        </label>
                        <div class="file-input-wrapper">
                            <input type="file" name="profile_photo" id="profile_photo" accept="image/*" required onchange="displayFileName(this)">
                            <label for="profile_photo" class="file-input-label">
                                <i class="fas fa-cloud-upload-alt"></i>
                                Click to Browse Files
                            </label>
                        </div>
                        <div class="file-name" id="fileName">No file chosen</div>
                    </div>

                    <div class="btn-group">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-check"></i> Upload Photo
                        </button>
                        <a href="director_dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function displayFileName(input) {
            const fileName = document.getElementById('fileName');
            if (input.files && input.files[0]) {
                fileName.textContent = input.files[0].name;
                fileName.style.color = 'var(--text)';
            } else {
                fileName.textContent = 'No file chosen';
                fileName.style.color = 'var(--muted)';
            }
        }
    </script>

    <?php include 'includes/director_scripts.php'; ?>
</body>
</html>
