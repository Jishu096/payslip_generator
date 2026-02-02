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
$baseURL = "/payslip_generator/public/";

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

// Get audit logs count
$totalGenerations = 0;
try {
    $countStmt = $db->query("SELECT COUNT(*) FROM audit_logs WHERE action = 'attendance_statement_generated'");
    $totalGenerations = $countStmt->fetchColumn();
} catch (Exception $e) {}

$currentMonth = date('n');
$currentYear = date('Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generate Attendance Statement - Accountant Portal</title>
    <?php include 'includes/accountant_styles.php'; ?>
    <style>
        /* Page Layout */
        .page-wrapper {
            min-height: 100vh;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8ec 100%);
        }
        
        .main-content {
            margin-left: 260px;
            padding: 0;
        }
        
        /* Hero Section */
        .page-hero {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 40px 100px;
            position: relative;
            overflow: hidden;
        }
        
        .page-hero::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 600px;
            height: 600px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            pointer-events: none;
        }
        
        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 1400px;
            margin: 0 auto;
        }
        
        .hero-content h1 {
            font-size: 32px;
            font-weight: 700;
            color: white;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .hero-content h1 i {
            font-size: 28px;
            opacity: 0.9;
        }
        
        .hero-content p {
            color: rgba(255,255,255,0.85);
            font-size: 15px;
        }
        
        /* Hero Stats */
        .hero-stats {
            display: flex;
            gap: 20px;
            margin-top: 25px;
        }
        
        .hero-stat {
            background: rgba(255,255,255,0.15);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.2);
            border-radius: 16px;
            padding: 18px 24px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .hero-stat-icon {
            width: 48px;
            height: 48px;
            background: rgba(255,255,255,0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            color: white;
        }
        
        .hero-stat-text h3 {
            font-size: 24px;
            font-weight: 700;
            color: white;
        }
        
        .hero-stat-text p {
            font-size: 12px;
            color: rgba(255,255,255,0.75);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        /* Content Area */
        .content-area {
            max-width: 1400px;
            margin: -60px auto 40px;
            padding: 0 40px;
            position: relative;
            z-index: 2;
        }
        
        /* Alerts */
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .alert i {
            font-size: 20px;
            margin-top: 2px;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
            border: 1px solid #6ee7b7;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        
        .alert-warning {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
            border: 1px solid #fcd34d;
        }
        
        .alert-content strong {
            display: block;
            margin-bottom: 4px;
        }
        
        .alert-content p {
            margin: 0;
            font-size: 13px;
            opacity: 0.9;
        }
        
        /* Main Grid Layout */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
        }
        
        /* Cards */
        .card {
            background: white;
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 4px 25px rgba(0,0,0,0.08);
        }
        
        .card-header {
            background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
            padding: 20px 25px;
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .card-header i {
            font-size: 20px;
            color: var(--accent);
        }
        
        .card-header h2 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
            margin: 0;
        }
        
        .card-body {
            padding: 25px;
        }
        
        /* Info Box */
        .info-box {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            border: 1px solid #93c5fd;
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .info-box-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        
        .info-box-header i {
            font-size: 18px;
            color: #2563eb;
        }
        
        .info-box-header h3 {
            font-size: 15px;
            font-weight: 700;
            color: #1e40af;
            margin: 0;
        }
        
        .info-list {
            display: grid;
            gap: 10px;
            margin: 0;
            padding: 0;
            list-style: none;
        }
        
        .info-list li {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            font-size: 13px;
            color: #1e3a8a;
            line-height: 1.5;
        }
        
        .info-list li i {
            color: #3b82f6;
            margin-top: 3px;
            font-size: 12px;
        }
        
        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            display: block;
            font-weight: 600;
            color: var(--text);
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .form-label .required {
            color: #ef4444;
            margin-left: 4px;
        }
        
        .form-select {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 14px;
            font-family: inherit;
            color: var(--text);
            background: white;
            transition: all 0.3s;
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%2364748b'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 20px;
        }
        
        .form-select:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
        }
        
        .form-select:disabled {
            background-color: #f1f5f9;
            cursor: not-allowed;
        }
        
        /* Generate Button */
        .generate-btn {
            width: 100%;
            padding: 16px 24px;
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
        }
        
        .generate-btn:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
        }
        
        .generate-btn:disabled {
            background: linear-gradient(135deg, #9ca3af, #6b7280);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .generate-btn i {
            font-size: 18px;
        }
        
        /* Audit Table */
        .audit-section {
            grid-column: 1 / -1;
        }
        
        .audit-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .clear-btn {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            padding: 10px 18px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
        }
        
        .clear-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3);
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .data-table th {
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: white;
        }
        
        .data-table th:first-child {
            border-radius: 12px 0 0 0;
        }
        
        .data-table th:last-child {
            border-radius: 0 12px 0 0;
        }
        
        .data-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
            color: var(--text);
        }
        
        .data-table tbody tr {
            transition: all 0.2s;
        }
        
        .data-table tbody tr:hover {
            background: #f8fafc;
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-purple {
            background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
            color: #4338ca;
        }
        
        .file-name {
            background: #f1f5f9;
            padding: 6px 10px;
            border-radius: 6px;
            font-family: 'Monaco', 'Consolas', monospace;
            font-size: 12px;
            color: var(--muted);
        }
        
        .delete-btn {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
            padding: 8px 12px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            transition: all 0.2s ease;
        }
        
        .delete-btn:hover {
            background: #dc2626;
            color: white;
            border-color: #dc2626;
        }
        
        .empty-row {
            text-align: center;
            padding: 50px 20px !important;
        }
        
        .empty-row i {
            font-size: 40px;
            color: var(--muted);
            opacity: 0.4;
            display: block;
            margin-bottom: 12px;
        }
        
        .empty-row span {
            color: var(--muted);
            font-size: 14px;
        }
        
        /* Loading Overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.8);
            backdrop-filter: blur(4px);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        
        .loading-content {
            background: white;
            padding: 40px 50px;
            border-radius: 20px;
            text-align: center;
            box-shadow: 0 25px 60px rgba(0,0,0,0.3);
            animation: scaleIn 0.3s ease;
        }
        
        @keyframes scaleIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
        
        .loading-content .spinner {
            width: 60px;
            height: 60px;
            border: 4px solid #e2e8f0;
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .loading-content h3 {
            color: var(--text);
            font-size: 20px;
            margin-bottom: 8px;
        }
        
        .loading-content p {
            color: var(--muted);
            font-size: 14px;
            margin: 0;
        }
        
        /* Responsive */
        @media (max-width: 1024px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
            
            .page-hero {
                padding: 30px 20px 80px;
            }
            
            .hero-content h1 {
                font-size: 24px;
            }
            
            .hero-stats {
                flex-direction: column;
            }
            
            .content-area {
                padding: 0 20px;
                margin-top: -50px;
            }
        }
        
        @keyframes fadeOut {
            from { opacity: 1; transform: translateX(0); }
            to { opacity: 0; transform: translateX(20px); }
        }
    </style>
</head>
<body>
    <div class="page-wrapper">
        <?php include 'includes/accountant_navbar.php'; ?>
        
        <div class="main-content">
            <!-- Hero Section -->
            <div class="page-hero">
                <div class="hero-content">
                    <h1><i class="fas fa-file-excel"></i> Generate Attendance Statement</h1>
                    <p>Create official Government format Excel for verified attendance data</p>
                    
                    <div class="hero-stats">
                        <div class="hero-stat">
                            <div class="hero-stat-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="hero-stat-text">
                                <h3><?php echo count($availableMonths); ?></h3>
                                <p>Verified Months</p>
                            </div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-icon">
                                <i class="fas fa-file-download"></i>
                            </div>
                            <div class="hero-stat-text">
                                <h3><?php echo $totalGenerations; ?></h3>
                                <p>Total Generated</p>
                            </div>
                        </div>
                        <div class="hero-stat">
                            <div class="hero-stat-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="hero-stat-text">
                                <h3>Govt</h3>
                                <p>Format Standard</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Content Area -->
            <div class="content-area">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <div class="alert-content">
                            <?php 
                                echo $_SESSION['success_message']; 
                                unset($_SESSION['success_message']);
                            ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <div class="alert-content">
                            <?php 
                                echo $_SESSION['error_message']; 
                                unset($_SESSION['error_message']);
                            ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (empty($availableMonths)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div class="alert-content">
                            <strong>No Verified Attendance Data Available</strong>
                            <p>Please ensure HR Officer has verified attendance data before generating statements.</p>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="content-grid">
                    <!-- Instructions Card -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-info-circle"></i>
                            <h2>Important Instructions</h2>
                        </div>
                        <div class="card-body">
                            <div class="info-box">
                                <div class="info-box-header">
                                    <i class="fas fa-lightbulb"></i>
                                    <h3>Before You Generate</h3>
                                </div>
                                <ul class="info-list">
                                    <li>
                                        <i class="fas fa-check"></i>
                                        <span><strong>Saturday & Sunday</strong> are WEEKENDS - Not counted in working days</span>
                                    </li>
                                    <li>
                                        <i class="fas fa-check"></i>
                                        <span><strong>Only HR VERIFIED</strong> attendance will be included</span>
                                    </li>
                                    <li>
                                        <i class="fas fa-check"></i>
                                        <span><strong>Director-locked months</strong> cannot be regenerated</span>
                                    </li>
                                    <li>
                                        <i class="fas fa-check"></i>
                                        <span><strong>Excel Format:</strong> Government Accounts standard with 13 columns</span>
                                    </li>
                                    <li>
                                        <i class="fas fa-check"></i>
                                        <span><strong>Separate Excel files</strong> for each employee category</span>
                                    </li>
                                    <li>
                                        <i class="fas fa-check"></i>
                                        <span><strong>Leave Types:</strong> EL, HPL, CCL, PL, CL, RH, OD/TOUR properly categorized</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Generation Form Card -->
                    <div class="card">
                        <div class="card-header">
                            <i class="fas fa-cogs"></i>
                            <h2>Generate Statement</h2>
                        </div>
                        <div class="card-body">
                            <form action="../../app/Controllers/AttendanceStatementController.php" method="POST" id="generateForm">
                                <div class="form-group">
                                    <label class="form-label">
                                        Month & Year <span class="required">*</span>
                                    </label>
                                    <select name="month_year" id="month_year" class="form-select" required <?php echo empty($availableMonths) ? 'disabled' : ''; ?>>
                                        <option value="">Select verified month...</option>
                                        <?php foreach ($availableMonths as $month): ?>
                                            <option value="<?php echo $month['month_num'] . '-' . $month['year']; ?>">
                                                <?php echo $month['month_year']; ?> ✓
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="form-group">
                                    <label class="form-label">
                                        Employee Category <span class="required">*</span>
                                    </label>
                                    <select name="employee_type" id="employee_type" class="form-select" required <?php echo empty($availableMonths) ? 'disabled' : ''; ?>>
                                        <option value="">Select category...</option>
                                        <option value="Permanent">Permanent Employees</option>
                                        <option value="Contractual">Contractual Employees</option>
                                        <option value="Intern">Interns</option>
                                    </select>
                                </div>

                                <button type="submit" class="generate-btn" <?php echo empty($availableMonths) ? 'disabled' : ''; ?>>
                                    <i class="fas fa-file-excel"></i>
                                    <span>Generate Excel Statement</span>
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Audit Table -->
                    <div class="card audit-section">
                        <div class="card-header audit-header">
                            <div style="display: flex; align-items: center; gap: 12px;">
                                <i class="fas fa-history"></i>
                                <h2>Recent Generations</h2>
                            </div>
                            <button onclick="clearAuditLogs()" class="clear-btn">
                                <i class="fas fa-trash-alt"></i>
                                <span>Clear All</span>
                            </button>
                        </div>
                        <div class="card-body" style="padding: 0;">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Date & Time</th>
                                        <th>Month/Year</th>
                                        <th>Category</th>
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
                                            echo '<tr id="emptyRow"><td colspan="6" class="empty-row">
                                                    <i class="fas fa-inbox"></i>
                                                    <span>No statements generated yet</span>
                                                  </td></tr>';
                                        } else {
                                            $monthNames = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                                            foreach ($auditLogs as $log) {
                                                echo '<tr data-log-id="' . $log['log_id'] . '">';
                                                echo '<td>' . date('d M Y, h:i A', strtotime($log['created_at'])) . '</td>';
                                                echo '<td><strong>' . $monthNames[$log['month']] . '</strong> ' . $log['year'] . '</td>';
                                                echo '<td><span class="badge badge-purple">' . htmlspecialchars($log['employee_type']) . '</span></td>';
                                                echo '<td>' . htmlspecialchars($log['username']) . '</td>';
                                                echo '<td><span class="file-name">' . htmlspecialchars($log['file_name']) . '</span></td>';
                                                echo '<td><button onclick="deleteAuditLog(' . $log['log_id'] . ')" class="delete-btn"><i class="fas fa-trash"></i></button></td>';
                                                echo '</tr>';
                                            }
                                        }
                                    } catch (Exception $e) {
                                        echo '<tr><td colspan="6" class="empty-row"><i class="fas fa-exclamation-triangle"></i><span>Error loading audit logs</span></td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-content">
            <div class="spinner"></div>
            <h3>Generating Excel</h3>
            <p>Please wait while we prepare your attendance statement...</p>
        </div>
    </div>
    
    <script>
        document.getElementById('generateForm').addEventListener('submit', function(e) {
            // Show loading overlay
            document.getElementById('loadingOverlay').style.display = 'flex';
            
            // Disable button
            const btn = this.querySelector('.generate-btn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>Generating...</span>';
            
            // Re-enable after file download starts
            setTimeout(function() {
                document.getElementById('loadingOverlay').style.display = 'none';
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-file-excel"></i> <span>Generate Excel Statement</span>';
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
                    row.style.animation = 'fadeOut 0.3s ease forwards';
                    setTimeout(() => {
                        row.remove();
                        const tbody = document.getElementById('auditTableBody');
                        if (tbody.children.length === 0) {
                            tbody.innerHTML = '<tr id="emptyRow"><td colspan="6" class="empty-row"><i class="fas fa-inbox"></i><span>No statements generated yet</span></td></tr>';
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
                    tbody.innerHTML = '<tr id="emptyRow"><td colspan="6" class="empty-row"><i class="fas fa-inbox"></i><span>No statements generated yet</span></td></tr>';
                } else {
                    alert('Error clearing logs: ' + data.message);
                }
            })
            .catch(error => alert('Error: ' + error));
        }
    </script>
</body>
</html>
