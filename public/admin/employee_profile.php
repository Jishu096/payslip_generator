<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'administrator') {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . '/../../app/Models/Employee.php';
require_once __DIR__ . '/../../app/Config/database.php';

$id = $_GET['id'] ?? null;
if (!$id) {
    header("Location: employees.php?error=missing_id");
    exit;
}

$employeeModel = new Employee();
$emp = $employeeModel->getEmployeeById($id);
if (!$emp) {
    header("Location: employees.php?error=not_found");
    exit;
}

// Get user role
$db = getDBConnection();
$stmt = $db->prepare("SELECT role FROM users WHERE employee_id = ? LIMIT 1");
$stmt->execute([$id]);
$userRecord = $stmt->fetch(PDO::FETCH_ASSOC);
$currentUserRole = $userRecord['role'] ?? 'employee';

$username = $_SESSION['username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Profile - <?php echo htmlspecialchars($emp['full_name']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        :root {
            --bg-primary: #ffffff;
            --bg-secondary: #f8f9fa;
            --bg-tertiary: #f1f3f5;
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
            --bg-tertiary: #2d3250;
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
            transition: background 0.3s ease, color 0.3s ease;
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
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .theme-toggle:hover {
            transform: translateY(-2px);
        }

        .theme-toggle i {
            font-size: 18px;
            color: var(--text-primary);
        }

        .profile-header {
            background: var(--gradient-primary);
            padding: 40px 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: var(--card-shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
        }

        .profile-info {
            display: flex;
            align-items: center;
            gap: 25px;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 42px;
            border: 4px solid rgba(255,255,255,0.3);
        }

        .profile-details h1 {
            margin: 0 0 8px 0;
            font-family: 'Space Grotesk', sans-serif;
            font-size: 32px;
        }

        .profile-meta {
            display: flex;
            gap: 20px;
            font-size: 15px;
            opacity: 0.9;
        }

        .profile-actions {
            display: flex;
            gap: 12px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .btn-primary {
            background: rgba(255,255,255,0.2);
            color: white;
            border: 2px solid rgba(255,255,255,0.3);
        }

        .btn-primary:hover {
            background: rgba(255,255,255,0.3);
        }

        .tabs-container {
            background: var(--bg-primary);
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .tabs {
            display: flex;
            background: var(--bg-secondary);
            border-bottom: 2px solid var(--border-color);
            overflow-x: auto;
        }

        .tab {
            padding: 18px 30px;
            cursor: pointer;
            transition: all 0.3s ease;
            color: var(--text-secondary);
            font-weight: 600;
            border-bottom: 3px solid transparent;
            white-space: nowrap;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .tab:hover {
            background: var(--bg-tertiary);
        }

        .tab.active {
            color: #667eea;
            border-bottom-color: #667eea;
            background: var(--bg-primary);
        }

        .tab-content {
            padding: 35px;
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .info-item label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-tertiary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .info-item .value {
            font-size: 16px;
            color: var(--text-primary);
            font-weight: 500;
        }

        .badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }

        .badge-active {
            background: rgba(52, 211, 153, 0.15);
            color: #34d399;
        }

        .badge-inactive {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
        }

        .badge-on_leave {
            background: rgba(251, 191, 36, 0.15);
            color: #f59e0b;
        }

        .section-divider {
            height: 2px;
            background: var(--border-color);
            margin: 30px 0;
        }

        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }

            .profile-info {
                flex-direction: column;
                text-align: center;
            }

            .profile-actions {
                width: 100%;
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }

            .tabs {
                flex-wrap: nowrap;
            }

            .tab {
                padding: 15px 20px;
            }
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
        <div class="profile-header">
            <div class="profile-info">
                <div class="profile-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="profile-details">
                    <h1><?php echo htmlspecialchars($emp['full_name']); ?></h1>
                    <div class="profile-meta">
                        <span><i class="fas fa-id-badge"></i> ID: <?php echo htmlspecialchars($emp['employee_id']); ?></span>
                        <span><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($emp['designation']); ?></span>
                        <span>
                            <?php 
                            $status = $emp['status'] ?? 'active';
                            $statusClass = 'badge-' . $status;
                            $statusText = ucwords(str_replace('_', ' ', $status));
                            ?>
                            <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                        </span>
                    </div>
                </div>
            </div>
            <div class="profile-actions">
                <a href="edit_employee.php?id=<?php echo $emp['employee_id']; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Edit Profile
                </a>
                <a href="employees.php" class="btn btn-primary">
                    <i class="fas fa-arrow-left"></i> Back
                </a>
            </div>
        </div>

        <div class="tabs-container">
            <div class="tabs">
                <div class="tab active" onclick="switchTab('personal')">
                    <i class="fas fa-user"></i> Personal Info
                </div>
                <div class="tab" onclick="switchTab('employment')">
                    <i class="fas fa-briefcase"></i> Employment
                </div>
                <div class="tab" onclick="switchTab('bank')">
                    <i class="fas fa-university"></i> Bank Details
                </div>
                <div class="tab" onclick="switchTab('documents')">
                    <i class="fas fa-file-alt"></i> Documents
                </div>
            </div>

            <!-- Personal Info Tab -->
            <div id="personal" class="tab-content active">
                <h2 style="margin-bottom: 25px; color: var(--text-primary);">Personal Information</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <label><i class="fas fa-user"></i> Full Name</label>
                        <div class="value"><?php echo htmlspecialchars($emp['full_name']); ?></div>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-envelope"></i> Email</label>
                        <div class="value"><?php echo htmlspecialchars($emp['email']); ?></div>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-phone"></i> Phone</label>
                        <div class="value"><?php echo htmlspecialchars($emp['phone']); ?></div>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-map-marker-alt"></i> Address</label>
                        <div class="value"><?php echo htmlspecialchars($emp['address'] ?? '—'); ?></div>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-city"></i> City</label>
                        <div class="value"><?php echo htmlspecialchars($emp['city'] ?? '—'); ?></div>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-flag"></i> State</label>
                        <div class="value"><?php echo htmlspecialchars($emp['state'] ?? '—'); ?></div>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-mail-bulk"></i> Pincode</label>
                        <div class="value"><?php echo htmlspecialchars($emp['pincode'] ?? '—'); ?></div>
                    </div>
                </div>

                <div class="section-divider"></div>

                <h3 style="margin-bottom: 20px; color: var(--text-primary);">Emergency Contact</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <label><i class="fas fa-user-shield"></i> Contact Name</label>
                        <div class="value"><?php echo htmlspecialchars($emp['emergency_contact_name'] ?? '—'); ?></div>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-phone-alt"></i> Contact Phone</label>
                        <div class="value"><?php echo htmlspecialchars($emp['emergency_contact_phone'] ?? '—'); ?></div>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-handshake"></i> Relation</label>
                        <div class="value"><?php echo htmlspecialchars($emp['emergency_contact_relation'] ?? '—'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Employment Tab -->
            <div id="employment" class="tab-content">
                <h2 style="margin-bottom: 25px; color: var(--text-primary);">Employment Details</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <label><i class="fas fa-id-badge"></i> Employee ID</label>
                        <div class="value"><?php echo htmlspecialchars($emp['employee_id']); ?></div>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-briefcase"></i> Designation</label>
                        <div class="value"><?php echo htmlspecialchars($emp['designation']); ?></div>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-building"></i> Department</label>
                        <div class="value"><?php echo htmlspecialchars($emp['department_name'] ?? '—'); ?></div>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-id-card"></i> Employment Type</label>
                        <div class="value"><?php echo htmlspecialchars(ucfirst($emp['employment_type'])); ?></div>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-toggle-on"></i> Status</label>
                        <div class="value"><span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span></div>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-user-tag"></i> User Role</label>
                        <div class="value"><?php echo htmlspecialchars(ucfirst($currentUserRole)); ?></div>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-calendar-alt"></i> Join Date</label>
                        <div class="value"><?php echo htmlspecialchars($emp['join_date'] ?? '—'); ?></div>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-dollar-sign"></i> Basic Salary</label>
                        <div class="value">₹<?php echo number_format($emp['basic_salary'], 2); ?></div>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-briefcase"></i> Experience</label>
                        <div class="value"><?php echo htmlspecialchars($emp['experience_years'] ?? '0'); ?> years</div>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-calendar-check"></i> Last Appraisal</label>
                        <div class="value"><?php echo htmlspecialchars($emp['last_appraisal_date'] ?? '—'); ?></div>
                    </div>
                </div>

                <?php if (!empty($emp['remarks'])): ?>
                <div class="section-divider"></div>
                <div class="info-item">
                    <label><i class="fas fa-comment-dots"></i> Remarks</label>
                    <div class="value"><?php echo htmlspecialchars($emp['remarks']); ?></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Bank Details Tab -->
            <div id="bank" class="tab-content">
                <h2 style="margin-bottom: 25px; color: var(--text-primary);">Bank & Identity Details</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <label><i class="fas fa-university"></i> Bank Account Number</label>
                        <div class="value"><?php echo htmlspecialchars($emp['bank_account_no'] ?? '—'); ?></div>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-barcode"></i> IFSC Code</label>
                        <div class="value"><?php echo htmlspecialchars($emp['ifsc_code'] ?? '—'); ?></div>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-id-card"></i> Aadhaar Number</label>
                        <div class="value"><?php echo htmlspecialchars($emp['aadhaar_no'] ?? '—'); ?></div>
                    </div>
                    <div class="info-item">
                        <label><i class="fas fa-id-badge"></i> PAN Number</label>
                        <div class="value"><?php echo htmlspecialchars($emp['pan_no'] ?? '—'); ?></div>
                    </div>
                </div>
            </div>

            <!-- Documents Tab -->
            <div id="documents" class="tab-content">
                <h2 style="margin-bottom: 25px; color: var(--text-primary);">Documents</h2>
                <div style="padding: 40px; text-align: center; color: var(--text-tertiary);">
                    <i class="fas fa-folder-open" style="font-size: 64px; margin-bottom: 20px; opacity: 0.3;"></i>
                    <p>Document management feature coming soon...</p>
                </div>
            </div>
        </div>
    </main>

    <?php include 'includes/admin_scripts.php'; ?>

    <script>
        // Theme Toggle Functionality
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

        // Tab Switching
        function switchTab(tabName) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.classList.remove('active');
            });

            // Remove active class from all tabs
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Show selected tab content
            document.getElementById(tabName).classList.add('active');

            // Add active class to clicked tab
            event.target.closest('.tab').classList.add('active');
        }
    </script>

</body>
</html>
