<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrator') {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Admin';
$uploadSuccess = isset($_GET['success']) && $_GET['success'] === '1';
$uploadError = $_GET['error'] ?? '';
$importedCount = $_GET['imported'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Import Employees - Enterprise Payroll Solutions</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --text-primary: #1a1f36;
            --text-secondary: #555;
            --text-tertiary: #7f8c8d;
            --border-color: #e0e0e0;
            --card-shadow: 0 2px 10px rgba(0,0,0,0.08);
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        [data-theme="dark"] {
            --bg-primary: #1a1f36;
            --bg-secondary: #232946;
            --text-primary: #fffffe;
            --text-secondary: #b8c1ec;
            --text-tertiary: #a0a8d4;
            --border-color: #3d4263;
            --card-shadow: 0 4px 20px rgba(0,0,0,0.4);
        }

        body {
            font-family: 'Manrope', sans-serif;
            background: var(--bg-secondary);
            color: var(--text-primary);
        }

        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            background: var(--bg-primary);
            border: 2px solid var(--border-color);
            border-radius: 50px;
            padding: 10px 15px;
            cursor: pointer;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
        }

        .theme-toggle i {
            font-size: 18px;
            color: var(--text-primary);
        }

        .import-container {
            max-width: 900px;
            margin: 0 auto;
        }

        .import-card {
            background: var(--bg-primary);
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            padding: 35px;
            margin-bottom: 30px;
            border: 1px solid var(--border-color);
        }

        .upload-area {
            border: 3px dashed var(--border-color);
            border-radius: 12px;
            padding: 50px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
            background: var(--bg-secondary);
        }

        .upload-area:hover {
            border-color: #667eea;
            background: var(--bg-primary);
        }

        .upload-area.dragover {
            border-color: #667eea;
            background: rgba(102, 126, 234, 0.05);
        }

        .upload-icon {
            font-size: 64px;
            color: var(--text-tertiary);
            margin-bottom: 20px;
        }

        .upload-area h3 {
            color: var(--text-primary);
            margin-bottom: 10px;
        }

        .upload-area p {
            color: var(--text-secondary);
            margin-bottom: 20px;
        }

        .file-input {
            display: none;
        }

        .btn {
            padding: 12px 28px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-primary {
            background: var(--gradient-primary);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .btn-secondary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            text-decoration: none;
        }

        .file-preview {
            margin-top: 30px;
            padding: 20px;
            background: var(--bg-secondary);
            border-radius: 10px;
            display: none;
        }

        .file-preview.show {
            display: block;
        }

        .file-info {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px;
            background: var(--bg-primary);
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .file-details {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .file-icon {
            font-size: 32px;
            color: #10b981;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 10px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            border: 2px solid;
        }

        .alert-success {
            background: #e8f7ee;
            border-color: #b6e0c5;
            color: #1b6b3d;
        }

        [data-theme="dark"] .alert-success {
            background: rgba(52, 211, 153, 0.15);
            border-color: rgba(52, 211, 153, 0.3);
            color: #34d399;
        }

        .alert-error {
            background: #fff4e5;
            border-color: #ffd8a8;
            color: #b35c00;
        }

        [data-theme="dark"] .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border-color: rgba(239, 68, 68, 0.3);
            color: #ef4444;
        }

        .instructions {
            background: var(--bg-secondary);
            padding: 25px;
            border-radius: 12px;
            margin-top: 30px;
        }

        .instructions h3 {
            color: var(--text-primary);
            margin-bottom: 15px;
        }

        .instructions ol {
            margin-left: 20px;
            color: var(--text-secondary);
            line-height: 1.8;
        }

        .instructions ol li {
            margin-bottom: 10px;
        }

        .sample-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .sample-link:hover {
            color: #764ba2;
        }

        .progress-bar {
            width: 100%;
            height: 8px;
            background: var(--border-color);
            border-radius: 10px;
            overflow: hidden;
            margin-top: 15px;
            display: none;
        }

        .progress-fill {
            height: 100%;
            background: var(--gradient-primary);
            width: 0%;
            transition: width 0.3s ease;
        }
    </style>
</head>
<body>

    <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
        <i class="fas fa-moon" id="themeIcon"></i>
    </button>

    <?php include 'includes/admin_navbar.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

    <main class="main-content" id="mainContent">
        <div class="page-header">
            <h1><i class="fas fa-file-upload"></i> Bulk Import Employees</h1>
            <p>Upload CSV or Excel file to import multiple employees at once</p>
        </div>

        <?php if ($uploadSuccess): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span>Successfully imported <?php echo $importedCount; ?> employee(s)!</span>
            </div>
        <?php elseif ($uploadError): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <span><?php echo htmlspecialchars($uploadError); ?></span>
            </div>
        <?php endif; ?>

        <div class="import-container">
            <div class="import-card">
                <form id="importForm" method="POST" action="process_bulk_import.php" enctype="multipart/form-data">
                    <div class="upload-area" id="uploadArea">
                        <div class="upload-icon">
                            <i class="fas fa-cloud-upload-alt"></i>
                        </div>
                        <h3>Drop your file here or click to browse</h3>
                        <p>Supports CSV and Excel files (.csv, .xls, .xlsx)</p>
                        <input type="file" id="fileInput" name="employee_file" class="file-input" accept=".csv,.xls,.xlsx" required>
                        <button type="button" class="btn btn-primary" onclick="document.getElementById('fileInput').click()">
                            <i class="fas fa-folder-open"></i> Choose File
                        </button>
                    </div>

                    <div class="file-preview" id="filePreview">
                        <div class="file-info" id="fileInfo">
                            <!-- File info will be populated here -->
                        </div>
                        <button type="submit" class="btn btn-primary" style="width: 100%;">
                            <i class="fas fa-upload"></i> Upload and Import
                        </button>
                        <div class="progress-bar" id="progressBar">
                            <div class="progress-fill" id="progressFill"></div>
                        </div>
                    </div>
                </form>

                <div class="instructions">
                    <h3><i class="fas fa-info-circle"></i> Import Instructions</h3>
                    <ol>
                        <li>Download the <a href="download_sample_template.php" class="sample-link"><i class="fas fa-download"></i> Sample CSV Template</a></li>
                        <li>Fill in employee details following the template format</li>
                        <li>Required fields: Full Name, Email, Phone, Designation, Department ID, Employment Type, Basic Salary</li>
                        <li>Optional fields: Address, City, State, Pincode, Emergency Contact details, Bank details, etc.</li>
                        <li>Save the file as CSV or Excel format</li>
                        <li>Upload the file using the form above</li>
                        <li>The system will validate and import all valid records</li>
                    </ol>
                </div>
            </div>

            <div class="import-card">
                <h3 style="margin-bottom: 20px; color: var(--text-primary);"><i class="fas fa-table"></i> CSV Column Format</h3>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: var(--bg-secondary);">
                                <th style="padding: 12px; text-align: left; border: 1px solid var(--border-color);">Column Name</th>
                                <th style="padding: 12px; text-align: left; border: 1px solid var(--border-color);">Required</th>
                                <th style="padding: 12px; text-align: left; border: 1px solid var(--border-color);">Example</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">full_name</td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">✅ Yes</td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">John Doe</td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">email</td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">✅ Yes</td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">john@example.com</td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">phone</td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">✅ Yes</td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">+91 9876543210</td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">designation</td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">✅ Yes</td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">Senior Developer</td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">department_id</td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">✅ Yes</td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">4</td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">employment_type</td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">✅ Yes</td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">permanent</td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">basic_salary</td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">✅ Yes</td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">50000.00</td>
                            </tr>
                            <tr>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">status</td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">Optional</td>
                                <td style="padding: 10px; border: 1px solid var(--border-color);">active</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/admin_scripts.php'; ?>

    <script>
        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        const themeIcon = document.getElementById('themeIcon');
        const html = document.documentElement;

        const savedTheme = localStorage.getItem('adminTheme') || 'light';
        html.setAttribute('data-theme', savedTheme);
        updateThemeIcon(savedTheme);

        themeToggle.addEventListener('click', () => {
            const currentTheme = html.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            
            html.setAttribute('data-theme', newTheme);
            localStorage.setItem('adminTheme', newTheme);
            updateThemeIcon(newTheme);
        });

        function updateThemeIcon(theme) {
            if (theme === 'dark') {
                themeIcon.classList.remove('fa-moon');
                themeIcon.classList.add('fa-sun');
            } else {
                themeIcon.classList.remove('fa-sun');
                themeIcon.classList.add('fa-moon');
            }
        }

        // File Upload Handling
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const filePreview = document.getElementById('filePreview');
        const fileInfo = document.getElementById('fileInfo');
        const importForm = document.getElementById('importForm');
        const progressBar = document.getElementById('progressBar');
        const progressFill = document.getElementById('progressFill');

        // Drag and Drop
        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => {
                uploadArea.classList.add('dragover');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            uploadArea.addEventListener(eventName, () => {
                uploadArea.classList.remove('dragover');
            }, false);
        });

        uploadArea.addEventListener('drop', (e) => {
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect();
            }
        });

        fileInput.addEventListener('change', handleFileSelect);

        function handleFileSelect() {
            const file = fileInput.files[0];
            if (file) {
                const fileSize = (file.size / 1024).toFixed(2);
                const fileExt = file.name.split('.').pop().toLowerCase();
                
                let fileIconClass = 'fa-file';
                if (fileExt === 'csv') fileIconClass = 'fa-file-csv';
                else if (fileExt === 'xlsx' || fileExt === 'xls') fileIconClass = 'fa-file-excel';
                
                fileInfo.innerHTML = `
                    <div class="file-details">
                        <div class="file-icon">
                            <i class="fas ${fileIconClass}"></i>
                        </div>
                        <div>
                            <div style="font-weight: 600; color: var(--text-primary);">${file.name}</div>
                            <div style="font-size: 13px; color: var(--text-tertiary);">${fileSize} KB</div>
                        </div>
                    </div>
                    <button type="button" onclick="clearFile()" style="background: none; border: none; cursor: pointer; color: var(--text-tertiary); font-size: 20px;">
                        <i class="fas fa-times"></i>
                    </button>
                `;
                
                filePreview.classList.add('show');
            }
        }

        function clearFile() {
            fileInput.value = '';
            filePreview.classList.remove('show');
            uploadArea.classList.remove('dragover');
        }

        // Form Submit with Progress
        importForm.addEventListener('submit', (e) => {
            progressBar.style.display = 'block';
            let progress = 0;
            const interval = setInterval(() => {
                progress += 10;
                progressFill.style.width = progress + '%';
                if (progress >= 90) {
                    clearInterval(interval);
                }
            }, 200);
        });
    </script>

</body>
</html>
