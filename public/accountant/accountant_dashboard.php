<?php
session_start();

// Check if user has accountant role (supports multi-role)
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? ''];
$hasAccountantRole = in_array('accountant', $userRoles);

if (!isset($_SESSION['role']) || (!$hasAccountantRole && $_SESSION['role'] !== 'accountant')) {
    header("Location: ../auth/login.php");
    exit;
}

require_once __DIR__ . "/../../app/Config/database.php";

$db = getDBConnection();
$username = $_SESSION['username'] ?? 'Accountant';
$userId = $_SESSION['user_id'] ?? 0;

// Get previous month for payroll processing
$prevMonth = date('F', strtotime('first day of last month'));
$prevYear = date('Y', strtotime('first day of last month'));
$currentMonthName = date('F');
$currentYear = date('Y');

// ============================================
// WORKFLOW STATUS DETECTION
// ============================================

// Stage 1: Check if attendance is finalized for previous month
$stage1Complete = false;
$stage1Data = null;
$newAttendanceReady = false;
try {
    $stmt = $db->prepare("
        SELECT afl.*, u.username as finalized_by_name,
               (SELECT COUNT(*) FROM attendance WHERE workflow_status = 'admin_finalized' 
                AND DATE_FORMAT(date, '%M') = ? AND YEAR(date) = ?) as record_count
        FROM attendance_finalization_log afl
        LEFT JOIN users u ON afl.finalized_by = u.user_id
        WHERE afl.month = ? AND afl.year = ?
        LIMIT 1
    ");
    $stmt->execute([$prevMonth, $prevYear, $prevMonth, $prevYear]);
    $stage1Data = $stmt->fetch(PDO::FETCH_ASSOC);
    $stage1Complete = !empty($stage1Data);
    
    // Check for unread notification about finalized attendance
    $notifStmt = $db->prepare("
        SELECT COUNT(*) FROM notifications 
        WHERE user_id = ? AND type = 'attendance_finalized' AND is_read = 0
    ");
    $notifStmt->execute([$userId]);
    $newAttendanceReady = $notifStmt->fetchColumn() > 0;
} catch (Exception $e) {
    // Tables might not exist
}

// Stage 2: Check if salary structures are verified (simple check - has salary config)
$stage2Complete = false;
try {
    $stmt = $db->query("SELECT COUNT(*) FROM salary_config WHERE is_active = 1");
    $stage2Complete = $stmt->fetchColumn() > 0;
} catch (Exception $e) {
    // Table might not exist, consider complete if we can proceed
    $stage2Complete = $stage1Complete;
}

// Stage 3: Check payroll processing status for previous month
$stage3Complete = false;
$stage3InProgress = false;
$payrollStats = ['total' => 0, 'processed' => 0];
try {
    $stmt = $db->prepare("
        SELECT 
            (SELECT COUNT(*) FROM employees WHERE status = 'active') as total_employees,
            COUNT(*) as processed_count
        FROM payroll 
        WHERE month = ? AND year = ?
    ");
    $stmt->execute([$prevMonth, $prevYear]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $payrollStats['total'] = $result['total_employees'] ?? 0;
    $payrollStats['processed'] = $result['processed_count'] ?? 0;
    
    $stage3InProgress = $payrollStats['processed'] > 0 && $payrollStats['processed'] < $payrollStats['total'];
    $stage3Complete = $payrollStats['processed'] > 0 && $payrollStats['processed'] >= $payrollStats['total'];
} catch (Exception $e) {}

// Stage 4: Check director approval status
$stage4Complete = false;
$stage4InProgress = false;
$approvalStats = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
try {
    $stmt = $db->prepare("
        SELECT 
            SUM(CASE WHEN approval_status = 'pending' OR approval_status IS NULL THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN approval_status = 'approved' THEN 1 ELSE 0 END) as approved,
            SUM(CASE WHEN approval_status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM payroll 
        WHERE month = ? AND year = ?
    ");
    $stmt->execute([$prevMonth, $prevYear]);
    $approvalStats = $stmt->fetch(PDO::FETCH_ASSOC) ?: $approvalStats;
    
    $stage4InProgress = $approvalStats['pending'] > 0;
    $stage4Complete = $approvalStats['approved'] > 0 && $approvalStats['pending'] == 0 && $approvalStats['rejected'] == 0;
} catch (Exception $e) {}

// Stage 5: Check disbursement status
$stage5Complete = false;
try {
    $stmt = $db->prepare("
        SELECT COUNT(*) FROM payroll 
        WHERE month = ? AND year = ? AND disbursement_status = 'disbursed'
    ");
    $stmt->execute([$prevMonth, $prevYear]);
    $stage5Complete = $stmt->fetchColumn() > 0;
} catch (Exception $e) {}

// Stage 6: Check if reports generated (simplified - check if month is complete)
$stage6Complete = $stage5Complete; // If disbursed, reports can be generated

// Determine current stage
$currentStage = 1;
if ($stage1Complete) $currentStage = 2;
if ($stage1Complete && $stage2Complete) $currentStage = 3;
if ($stage3Complete) $currentStage = 4;
if ($stage4Complete) $currentStage = 5;
if ($stage5Complete) $currentStage = 6;
if ($stage6Complete) $currentStage = 7; // All complete

// Calculate progress percentage
$completedStages = ($stage1Complete ? 1 : 0) + ($stage2Complete ? 1 : 0) + ($stage3Complete ? 1 : 0) 
                 + ($stage4Complete ? 1 : 0) + ($stage5Complete ? 1 : 0) + ($stage6Complete ? 1 : 0);
$progressPercent = ($completedStages / 6) * 100;

// ============================================
// DASHBOARD STATS
// ============================================

// Get total employees
try {
    $stmt = $db->query("SELECT COUNT(*) FROM employees WHERE status = 'active'");
    $totalEmployees = $stmt->fetchColumn() ?? 0;
} catch (Exception $e) {
    $totalEmployees = 0;
}

// Get total payslips generated this month
try {
    $stmt = $db->query("
        SELECT COUNT(*) FROM payroll 
        WHERE month = MONTHNAME(CURDATE()) AND year = YEAR(CURDATE())
    ");
    $payslipsThisMonth = $stmt->fetchColumn() ?? 0;
} catch (Exception $e) {
    $payslipsThisMonth = 0;
}

// Get pending approvals
try {
    $stmt = $db->query("
        SELECT COUNT(*) FROM payroll 
        WHERE approval_status = 'pending' OR approval_status IS NULL
    ");
    $pendingApprovals = $stmt->fetchColumn() ?? 0;
} catch (Exception $e) {
    $pendingApprovals = 0;
}

// Get total disbursed this month
try {
    $stmt = $db->query("
        SELECT COALESCE(SUM(net_salary), 0) FROM payroll 
        WHERE approval_status = 'approved' 
        AND month = MONTHNAME(CURDATE()) AND year = YEAR(CURDATE())
    ");
    $totalDisbursed = $stmt->fetchColumn() ?? 0;
} catch (Exception $e) {
    $totalDisbursed = 0;
}

// Get recent payroll activities
try {
    $stmt = $db->query("
        SELECT 
            p.payroll_id,
            e.full_name,
            p.month,
            p.year,
            p.net_salary,
            p.approval_status,
            p.created_at
        FROM payroll p
        JOIN employees e ON p.employee_id = e.employee_id
        ORDER BY p.created_at DESC
        LIMIT 10
    ");
    $recentPayrolls = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recentPayrolls = [];
}

$currentMonth = date('F Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accountant Dashboard - e-HRMS</title>
    <?php include 'includes/accountant_styles.php'; ?>
    <style>
        :root {
            --bg: #f0f4f8;
            --card: #ffffff;
            --accent: #667eea;
            --accent-2: #764ba2;
            --text: #1e293b;
            --muted: #64748b;
            --border: #e2e8f0;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }

        .main-content {
            padding: 30px;
            background: var(--bg);
            min-height: calc(100vh - 70px);
        }

        /* New Attendance Alert Banner */
        .alert-banner {
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 16px;
            padding: 20px 25px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
            animation: slideDown 0.5s ease, pulse-glow 2s infinite;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3); }
            50% { box-shadow: 0 8px 35px rgba(16, 185, 129, 0.5); }
        }

        .alert-banner-content {
            display: flex;
            align-items: center;
            gap: 18px;
        }

        .alert-banner-icon {
            width: 50px;
            height: 50px;
            background: rgba(255,255,255,0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .alert-banner-text h3 {
            font-size: 18px;
            font-weight: 700;
            color: white;
            margin-bottom: 4px;
        }

        .alert-banner-text p {
            font-size: 14px;
            color: rgba(255,255,255,0.9);
        }

        .alert-banner-actions {
            display: flex;
            gap: 12px;
        }

        .alert-banner-btn {
            padding: 12px 24px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .alert-banner-btn.primary {
            background: white;
            color: #059669;
        }

        .alert-banner-btn.primary:hover {
            background: #f0fdf4;
            transform: translateY(-2px);
        }

        .alert-banner-btn.secondary {
            background: rgba(255,255,255,0.2);
            color: white;
            border: none;
            cursor: pointer;
        }

        .alert-banner-btn.secondary:hover {
            background: rgba(255,255,255,0.3);
        }

        /* Welcome Banner */
        .welcome-banner {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            border-radius: 20px;
            padding: 35px 40px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
            position: relative;
            overflow: hidden;
        }

        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
        }

        .welcome-banner::after {
            content: '';
            position: absolute;
            bottom: -60%;
            right: 20%;
            width: 300px;
            height: 300px;
            background: rgba(255,255,255,0.05);
            border-radius: 50%;
        }

        .welcome-content {
            position: relative;
            z-index: 1;
        }

        .welcome-content h1 {
            font-size: 28px;
            font-weight: 700;
            color: white;
            margin-bottom: 8px;
        }

        .welcome-content p {
            color: rgba(255,255,255,0.85);
            font-size: 16px;
        }

        .welcome-actions {
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
            z-index: 1;
        }

        .period-display {
            text-align: right;
            padding: 15px 25px;
            background: rgba(255,255,255,0.15);
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }

        .period-display .label {
            font-size: 12px;
            color: rgba(255,255,255,0.8);
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 4px;
        }

        .period-display .value {
            font-size: 20px;
            font-weight: 700;
            color: white;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--card);
            border-radius: 16px;
            padding: 25px;
            display: flex;
            align-items: flex-start;
            gap: 18px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            border: 1px solid var(--border);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(0,0,0,0.1);
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            flex-shrink: 0;
        }

        .stat-icon.purple { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .stat-icon.green { background: linear-gradient(135deg, #10b981, #059669); color: white; }
        .stat-icon.orange { background: linear-gradient(135deg, #f59e0b, #d97706); color: white; }
        .stat-icon.blue { background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; }

        .stat-details {
            flex: 1;
        }

        .stat-label {
            font-size: 13px;
            color: var(--muted);
            font-weight: 500;
            margin-bottom: 6px;
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--text);
            line-height: 1;
            margin-bottom: 4px;
        }

        .stat-sublabel {
            font-size: 12px;
            color: var(--muted);
        }

        /* Quick Actions */
        .section-title {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .section-title i {
            color: var(--accent);
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }

        .action-card {
            background: var(--card);
            border-radius: 16px;
            padding: 25px;
            text-decoration: none;
            color: var(--text);
            border: 2px solid var(--border);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 15px;
        }

        .action-card:hover {
            border-color: var(--accent);
            transform: translateY(-5px);
            box-shadow: 0 12px 30px rgba(102, 126, 234, 0.15);
        }

        .action-card.primary {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            border: none;
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
        }

        .action-card.primary:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
        }

        .action-card .action-icon {
            width: 60px;
            height: 60px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 26px;
            background: rgba(102, 126, 234, 0.1);
            color: var(--accent);
        }

        .action-card.primary .action-icon {
            background: rgba(255,255,255,0.2);
            color: white;
        }

        .action-card .action-title {
            font-size: 15px;
            font-weight: 600;
        }

        /* Recent Activity */
        .activity-card {
            background: var(--card);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid var(--border);
        }

        .activity-header {
            padding: 20px 25px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .activity-header h2 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .activity-header h2 i {
            color: var(--accent);
        }

        .view-all-btn {
            font-size: 14px;
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.3s;
        }

        .view-all-btn:hover {
            gap: 10px;
        }

        .activity-table {
            width: 100%;
            border-collapse: collapse;
        }

        .activity-table th {
            text-align: left;
            padding: 14px 25px;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--muted);
            font-weight: 600;
            background: #f8fafc;
            border-bottom: 1px solid var(--border);
        }

        .activity-table td {
            padding: 18px 25px;
            border-bottom: 1px solid var(--border);
            font-size: 14px;
        }

        .activity-table tr:last-child td {
            border-bottom: none;
        }

        .activity-table tr:hover {
            background: #f8fafc;
        }

        .employee-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .employee-avatar {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }

        .employee-name {
            font-weight: 600;
            color: var(--text);
        }

        .period-badge {
            display: inline-block;
            padding: 6px 12px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
        }

        .amount {
            font-weight: 700;
            color: var(--text);
            font-family: 'Roboto Mono', monospace;
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-badge.approved {
            background: #d1fae5;
            color: #065f46;
        }

        .status-badge.pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-badge.rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        .status-badge i {
            font-size: 10px;
        }

        .empty-state {
            text-align: center;
            padding: 60px 40px;
        }

        .empty-state i {
            font-size: 64px;
            color: var(--border);
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 18px;
            color: var(--text);
            margin-bottom: 8px;
        }

        .empty-state p {
            color: var(--muted);
            font-size: 14px;
        }

        /* Notification Bell */
        .notification-bell-container {
            position: relative;
        }

        .notification-bell {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .notification-bell:hover {
            background: rgba(255,255,255,0.3);
        }

        .notification-count {
            position: absolute;
            top: -5px;
            right: -5px;
            width: 22px;
            height: 22px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            font-size: 11px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
        }

        .notification-dropdown {
            position: absolute;
            top: 60px;
            right: 0;
            width: 360px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.2);
            z-index: 1000;
            overflow: hidden;
        }

        .notification-dropdown-header {
            padding: 18px 20px;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .notification-dropdown-header h3 {
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
        }

        .mark-all-read {
            background: none;
            border: none;
            color: var(--accent);
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
        }

        .notification-list {
            max-height: 350px;
            overflow-y: auto;
        }

        .notification-empty {
            padding: 40px;
            text-align: center;
            color: var(--muted);
        }

        .notification-empty i {
            font-size: 40px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        @media (max-width: 1400px) {
            .stats-grid, .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .welcome-banner {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            .stats-grid, .quick-actions {
                grid-template-columns: 1fr;
            }
        }

        /* Payroll Workflow Section */
        .workflow-section {
            background: var(--card);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            border: 1px solid var(--border);
        }

        .workflow-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .workflow-header h2 {
            font-size: 20px;
            font-weight: 700;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .workflow-header h2 i {
            color: var(--accent);
        }

        .workflow-month-selector {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .workflow-month-selector select {
            padding: 10px 16px;
            border: 2px solid var(--border);
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            color: var(--text);
            background: white;
            cursor: pointer;
            transition: all 0.3s;
        }

        .workflow-month-selector select:focus {
            border-color: var(--accent);
            outline: none;
        }

        /* Timeline Container */
        .workflow-timeline {
            display: flex;
            justify-content: space-between;
            position: relative;
            padding: 0 20px;
            margin-bottom: 30px;
        }

        .workflow-timeline::before {
            content: '';
            position: absolute;
            top: 30px;
            left: 60px;
            right: 60px;
            height: 4px;
            background: var(--border);
            border-radius: 2px;
            z-index: 0;
        }

        .workflow-timeline::after {
            content: '';
            position: absolute;
            top: 30px;
            left: 60px;
            width: var(--progress, 0%);
            height: 4px;
            background: linear-gradient(90deg, var(--accent), var(--accent-2));
            border-radius: 2px;
            z-index: 1;
            transition: width 0.5s ease;
        }

        .timeline-stage {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
            cursor: pointer;
            transition: all 0.3s;
        }

        .timeline-stage:hover {
            transform: translateY(-3px);
        }

        .stage-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: white;
            border: 4px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: var(--muted);
            transition: all 0.3s;
            margin-bottom: 12px;
        }

        .timeline-stage.completed .stage-circle {
            background: linear-gradient(135deg, var(--success), #059669);
            border-color: var(--success);
            color: white;
        }

        .timeline-stage.current .stage-circle {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            border-color: var(--accent);
            color: white;
            box-shadow: 0 0 0 8px rgba(102, 126, 234, 0.2);
            animation: pulse-ring 2s infinite;
        }

        .timeline-stage.pending .stage-circle {
            background: white;
            border-color: var(--border);
            color: var(--muted);
        }

        @keyframes pulse-ring {
            0% { box-shadow: 0 0 0 0 rgba(102, 126, 234, 0.4); }
            70% { box-shadow: 0 0 0 12px rgba(102, 126, 234, 0); }
            100% { box-shadow: 0 0 0 0 rgba(102, 126, 234, 0); }
        }

        .stage-label {
            font-size: 13px;
            font-weight: 700;
            color: var(--text);
            text-align: center;
            margin-bottom: 4px;
        }

        .stage-date {
            font-size: 11px;
            color: var(--muted);
            text-align: center;
        }

        /* Expanded Stage Details */
        .stage-details-container {
            background: #f8fafc;
            border-radius: 16px;
            padding: 25px;
            margin-top: 10px;
            border: 2px solid var(--border);
            display: none;
        }

        .stage-details-container.active {
            display: block;
            animation: slideDown 0.3s ease;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .stage-details-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--border);
        }

        .stage-details-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .stage-details-title h3 {
            font-size: 18px;
            font-weight: 700;
            color: var(--text);
        }

        .stage-status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stage-status-badge.completed {
            background: #d1fae5;
            color: #065f46;
        }

        .stage-status-badge.in-progress {
            background: #e0e7ff;
            color: #4338ca;
        }

        .stage-status-badge.pending {
            background: #f1f5f9;
            color: #64748b;
        }

        .stage-tasks {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 12px;
        }

        .stage-task {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 16px;
            background: white;
            border-radius: 12px;
            border: 1px solid var(--border);
            transition: all 0.3s;
        }

        .stage-task:hover {
            border-color: var(--accent);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.1);
        }

        .task-checkbox {
            width: 24px;
            height: 24px;
            border-radius: 6px;
            border: 2px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.3s;
        }

        .stage-task.done .task-checkbox {
            background: var(--success);
            border-color: var(--success);
            color: white;
        }

        .task-checkbox i {
            font-size: 12px;
            opacity: 0;
        }

        .stage-task.done .task-checkbox i {
            opacity: 1;
        }

        .task-label {
            font-size: 14px;
            color: var(--text);
            font-weight: 500;
        }

        .stage-task.done .task-label {
            color: var(--muted);
            text-decoration: line-through;
        }

        .stage-action-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            margin-top: 20px;
        }

        .stage-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }

        @media (max-width: 1200px) {
            .workflow-timeline {
                flex-wrap: wrap;
                gap: 20px;
                justify-content: center;
            }
            .workflow-timeline::before,
            .workflow-timeline::after {
                display: none;
            }
            .stage-tasks {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/accountant_navbar.php'; ?>

    <div class="main-content">
        <?php if ($newAttendanceReady || ($stage1Complete && !$stage3InProgress && !$stage3Complete)): ?>
        <!-- New Attendance Ready Alert -->
        <div class="alert-banner" id="attendanceAlert">
            <div class="alert-banner-content">
                <div class="alert-banner-icon">
                    <i class="fas fa-calendar-check"></i>
                </div>
                <div class="alert-banner-text">
                    <h3>🎉 New Attendance Ready!</h3>
                    <p>Admin has finalized attendance for <strong><?php echo $prevMonth . ' ' . $prevYear; ?></strong>. 
                    <?php if ($stage1Data): ?>
                        <?php echo $stage1Data['record_count'] ?? 0; ?> records are ready for payroll processing.
                    <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="alert-banner-actions">
                <a href="finalized_attendance.php" class="alert-banner-btn primary">
                    <i class="fas fa-eye"></i> View Attendance
                </a>
                <button onclick="dismissAlert()" class="alert-banner-btn secondary">
                    <i class="fas fa-times"></i> Dismiss
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="welcome-content">
                <h1>Welcome back, <?php echo htmlspecialchars($username); ?>! 👋</h1>
                <p>Manage payroll processing and salary disbursement from your dashboard</p>
            </div>
            <div class="welcome-actions">
                <div class="notification-bell-container">
                    <button class="notification-bell" onclick="toggleNotificationDropdown()" type="button">
                        <i class="fas fa-bell"></i>
                        <span id="notificationCount" class="notification-count" style="display: none;">0</span>
                    </button>
                    <div id="notificationDropdown" class="notification-dropdown" style="display: none;">
                        <div class="notification-dropdown-header">
                            <h3>Notifications</h3>
                            <button onclick="markAllAsRead()" class="mark-all-read">Mark all as read</button>
                        </div>
                        <div id="notificationList" class="notification-list">
                            <div class="notification-empty">
                                <i class="fas fa-bell-slash"></i>
                                <p>No new notifications</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="period-display">
                    <div class="label">Current Period</div>
                    <div class="value"><?php echo $currentMonth; ?></div>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon purple">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-label">Total Employees</div>
                    <div class="stat-value"><?php echo $totalEmployees; ?></div>
                    <div class="stat-sublabel">Active employees</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-label">Payslips Generated</div>
                    <div class="stat-value"><?php echo $payslipsThisMonth; ?></div>
                    <div class="stat-sublabel">This month</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-label">Pending Approval</div>
                    <div class="stat-value"><?php echo $pendingApprovals; ?></div>
                    <div class="stat-sublabel">Awaiting director</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon blue">
                    <i class="fas fa-indian-rupee-sign"></i>
                </div>
                <div class="stat-details">
                    <div class="stat-label">Total Disbursed</div>
                    <div class="stat-value">₹<?php echo number_format($totalDisbursed / 100000, 1); ?>L</div>
                    <div class="stat-sublabel">This month</div>
                </div>
            </div>
        </div>

        <!-- Monthly Payroll Workflow -->
        <div class="workflow-section">
            <div class="workflow-header">
                <h2><i class="fas fa-route"></i> Monthly Payroll Cycle</h2>
                <div class="workflow-month-selector">
                    <select id="workflowMonth">
                        <?php 
                        $months = ['January', 'February', 'March', 'April', 'May', 'June', 
                                   'July', 'August', 'September', 'October', 'November', 'December'];
                        $currentMonthNum = date('n') - 1;
                        foreach ($months as $index => $month): 
                        ?>
                            <option value="<?php echo $index; ?>" <?php echo $index === $currentMonthNum ? 'selected' : ''; ?>>
                                <?php echo $month . ' ' . date('Y'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Timeline - Dynamic based on actual workflow status -->
            <?php
            // Helper function to get stage class
            function getStageClass($stageNum, $currentStage, $isComplete) {
                if ($isComplete) return 'completed';
                if ($stageNum == $currentStage) return 'current';
                return 'pending';
            }
            ?>
            <div class="workflow-timeline" style="--progress: <?php echo $progressPercent; ?>%;">
                <div class="timeline-stage <?php echo getStageClass(1, $currentStage, $stage1Complete); ?>" data-stage="1" onclick="showStageDetails(1)">
                    <div class="stage-circle">
                        <i class="fas fa-calendar-check"></i>
                    </div>
                    <div class="stage-label">Receive</div>
                    <div class="stage-date">1st - 5th</div>
                </div>

                <div class="timeline-stage <?php echo getStageClass(2, $currentStage, $stage2Complete); ?>" data-stage="2" onclick="showStageDetails(2)">
                    <div class="stage-circle">
                        <i class="fas fa-search-dollar"></i>
                    </div>
                    <div class="stage-label">Verify</div>
                    <div class="stage-date">5th - 7th</div>
                </div>

                <div class="timeline-stage <?php echo getStageClass(3, $currentStage, $stage3Complete); ?>" data-stage="3" onclick="showStageDetails(3)">
                    <div class="stage-circle">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <div class="stage-label">Process</div>
                    <div class="stage-date">7th - 10th</div>
                </div>

                <div class="timeline-stage <?php echo getStageClass(4, $currentStage, $stage4Complete); ?>" data-stage="4" onclick="showStageDetails(4)">
                    <div class="stage-circle">
                        <i class="fas fa-stamp"></i>
                    </div>
                    <div class="stage-label">Approve</div>
                    <div class="stage-date">10th - 12th</div>
                </div>

                <div class="timeline-stage <?php echo getStageClass(5, $currentStage, $stage5Complete); ?>" data-stage="5" onclick="showStageDetails(5)">
                    <div class="stage-circle">
                        <i class="fas fa-university"></i>
                    </div>
                    <div class="stage-label">Disburse</div>
                    <div class="stage-date">15th - 20th</div>
                </div>

                <div class="timeline-stage <?php echo getStageClass(6, $currentStage, $stage6Complete); ?>" data-stage="6" onclick="showStageDetails(6)">
                    <div class="stage-circle">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stage-label">Reports</div>
                    <div class="stage-date">End of Month</div>
                </div>
            </div>

            <!-- Stage 1: Receive Attendance -->
            <div class="stage-details-container <?php echo $currentStage == 1 ? 'active' : ''; ?>" id="stage-1">
                <div class="stage-details-header">
                    <div class="stage-details-title">
                        <i class="fas fa-calendar-check" style="font-size: 24px; color: <?php echo $stage1Complete ? 'var(--success)' : 'var(--accent)'; ?>;"></i>
                        <h3>Stage 1: Receive Attendance</h3>
                    </div>
                    <span class="stage-status-badge <?php echo $stage1Complete ? 'completed' : ($currentStage == 1 ? 'in-progress' : 'pending'); ?>">
                        <?php echo $stage1Complete ? 'Completed' : ($currentStage == 1 ? 'In Progress' : 'Pending'); ?>
                    </span>
                </div>
                <div class="stage-tasks">
                    <div class="stage-task <?php echo $stage1Complete ? 'done' : ''; ?>">
                        <div class="task-checkbox"><i class="fas fa-check"></i></div>
                        <span class="task-label">Admin finalizes previous month's attendance</span>
                    </div>
                    <div class="stage-task <?php echo $stage1Complete ? 'done' : ''; ?>">
                        <div class="task-checkbox"><i class="fas fa-check"></i></div>
                        <span class="task-label">Receive notification of finalized attendance</span>
                    </div>
                    <div class="stage-task <?php echo $stage1Complete ? 'done' : ''; ?>">
                        <div class="task-checkbox"><i class="fas fa-check"></i></div>
                        <span class="task-label">Review attendance summary (Present/Absent/Leave)</span>
                    </div>
                </div>
                <?php if ($stage1Data): ?>
                <div style="margin-top: 15px; padding: 12px 16px; background: #f0fdf4; border-radius: 10px; border: 1px solid #bbf7d0;">
                    <small style="color: #166534;">
                        <i class="fas fa-info-circle"></i> 
                        Finalized by <strong><?php echo htmlspecialchars($stage1Data['finalized_by_name'] ?? 'Admin'); ?></strong> 
                        on <?php echo date('d M Y', strtotime($stage1Data['finalized_at'])); ?>
                        • <?php echo $stage1Data['record_count'] ?? 0; ?> records
                    </small>
                </div>
                <?php endif; ?>
                <a href="finalized_attendance.php" class="stage-action-btn">
                    <i class="fas fa-eye"></i> View Finalized Attendance
                </a>
            </div>

            <!-- Stage 2: Verify Data -->
            <div class="stage-details-container <?php echo $currentStage == 2 ? 'active' : ''; ?>" id="stage-2">
                <div class="stage-details-header">
                    <div class="stage-details-title">
                        <i class="fas fa-search-dollar" style="font-size: 24px; color: <?php echo $stage2Complete ? 'var(--success)' : 'var(--accent)'; ?>;"></i>
                        <h3>Stage 2: Verify Data</h3>
                    </div>
                    <span class="stage-status-badge <?php echo $stage2Complete ? 'completed' : ($currentStage == 2 ? 'in-progress' : 'pending'); ?>">
                        <?php echo $stage2Complete ? 'Completed' : ($currentStage == 2 ? 'In Progress' : 'Pending'); ?>
                    </span>
                </div>
                <div class="stage-tasks">
                    <div class="stage-task <?php echo $stage2Complete ? 'done' : ''; ?>">
                        <div class="task-checkbox"><i class="fas fa-check"></i></div>
                        <span class="task-label">Check employee salary structure is updated</span>
                    </div>
                    <div class="stage-task <?php echo $stage2Complete ? 'done' : ''; ?>">
                        <div class="task-checkbox"><i class="fas fa-check"></i></div>
                        <span class="task-label">Verify any salary revisions/arrears</span>
                    </div>
                    <div class="stage-task <?php echo $stage2Complete ? 'done' : ''; ?>">
                        <div class="task-checkbox"><i class="fas fa-check"></i></div>
                        <span class="task-label">Check for new joiners/relieved employees</span>
                    </div>
                    <div class="stage-task <?php echo $stage2Complete ? 'done' : ''; ?>">
                        <div class="task-checkbox"><i class="fas fa-check"></i></div>
                        <span class="task-label">Review Leave Without Pay (LWP) deductions</span>
                    </div>
                </div>
                <a href="salary_structure.php" class="stage-action-btn">
                    <i class="fas fa-wallet"></i> View Salary Structure
                </a>
            </div>

            <!-- Stage 3: Process Payroll -->
            <div class="stage-details-container <?php echo $currentStage == 3 ? 'active' : ''; ?>" id="stage-3">
                <div class="stage-details-header">
                    <div class="stage-details-title">
                        <i class="fas fa-calculator" style="font-size: 24px; color: <?php echo $stage3Complete ? 'var(--success)' : 'var(--accent)'; ?>;"></i>
                        <h3>Stage 3: Process Payroll</h3>
                    </div>
                    <span class="stage-status-badge <?php echo $stage3Complete ? 'completed' : ($stage3InProgress || $currentStage == 3 ? 'in-progress' : 'pending'); ?>">
                        <?php 
                        if ($stage3Complete) echo 'Completed';
                        elseif ($stage3InProgress) echo $payrollStats['processed'] . '/' . $payrollStats['total'] . ' Processed';
                        elseif ($currentStage == 3) echo 'In Progress';
                        else echo 'Pending';
                        ?>
                    </span>
                </div>
                <div class="stage-tasks">
                    <div class="stage-task <?php echo $payrollStats['processed'] > 0 ? 'done' : ''; ?>">
                        <div class="task-checkbox"><i class="fas fa-check"></i></div>
                        <span class="task-label">Run salary calculation (bulk/individual)</span>
                    </div>
                    <div class="stage-task <?php echo $stage3Complete ? 'done' : ''; ?>">
                        <div class="task-checkbox"><i class="fas fa-check"></i></div>
                        <span class="task-label">Generate payslips for all employees</span>
                    </div>
                    <div class="stage-task <?php echo $stage3Complete ? 'done' : ''; ?>">
                        <div class="task-checkbox"><i class="fas fa-check"></i></div>
                        <span class="task-label">Review salary statement</span>
                    </div>
                    <div class="stage-task <?php echo $stage3Complete ? 'done' : ''; ?>">
                        <div class="task-checkbox"><i class="fas fa-check"></i></div>
                        <span class="task-label">Handle exceptions/corrections</span>
                    </div>
                </div>
                <?php if ($stage3InProgress): ?>
                <div style="margin-top: 15px; padding: 12px 16px; background: #fef3c7; border-radius: 10px; border: 1px solid #fcd34d;">
                    <small style="color: #92400e;">
                        <i class="fas fa-spinner fa-spin"></i> 
                        Processing: <strong><?php echo $payrollStats['processed']; ?></strong> of <strong><?php echo $payrollStats['total']; ?></strong> employees completed
                    </small>
                </div>
                <?php endif; ?>
                <a href="generate_payslip.php" class="stage-action-btn">
                    <i class="fas fa-play"></i> <?php echo $stage3InProgress ? 'Continue Processing' : 'Start Processing'; ?>
                </a>
            </div>

            <!-- Stage 4: Approval -->
            <div class="stage-details-container <?php echo $currentStage == 4 ? 'active' : ''; ?>" id="stage-4">
                <div class="stage-details-header">
                    <div class="stage-details-title">
                        <i class="fas fa-stamp" style="font-size: 24px; color: <?php echo $stage4Complete ? 'var(--success)' : ($stage4InProgress ? 'var(--warning)' : 'var(--muted)'); ?>;"></i>
                        <h3>Stage 4: Director Approval</h3>
                    </div>
                    <span class="stage-status-badge <?php echo $stage4Complete ? 'completed' : ($stage4InProgress ? 'in-progress' : 'pending'); ?>">
                        <?php 
                        if ($stage4Complete) echo 'Approved';
                        elseif ($stage4InProgress) echo $approvalStats['pending'] . ' Pending';
                        else echo 'Pending';
                        ?>
                    </span>
                </div>
                <div class="stage-tasks">
                    <div class="stage-task <?php echo $stage3Complete ? 'done' : ''; ?>">
                        <div class="task-checkbox"><i class="fas fa-check"></i></div>
                        <span class="task-label">Submit payroll to Director for approval</span>
                    </div>
                    <div class="stage-task <?php echo $stage4InProgress || $stage4Complete ? 'done' : ''; ?>">
                        <div class="task-checkbox"><i class="fas fa-check"></i></div>
                        <span class="task-label">Director reviews salary details</span>
                    </div>
                    <div class="stage-task <?php echo $stage4Complete ? 'done' : ''; ?>">
                        <div class="task-checkbox"><i class="fas fa-check"></i></div>
                        <span class="task-label">Handle rejections (make corrections)</span>
                    </div>
                </div>
                <?php if ($approvalStats['rejected'] > 0): ?>
                <div style="margin-top: 15px; padding: 12px 16px; background: #fee2e2; border-radius: 10px; border: 1px solid #fecaca;">
                    <small style="color: #991b1b;">
                        <i class="fas fa-exclamation-triangle"></i> 
                        <strong><?php echo $approvalStats['rejected']; ?></strong> payroll(s) rejected - corrections needed
                    </small>
                </div>
                <?php endif; ?>
                <?php if ($stage4Complete || $stage4InProgress): ?>
                <a href="payslips.php" class="stage-action-btn">
                    <i class="fas fa-eye"></i> View Approval Status
                </a>
                <?php else: ?>
                <a href="payslips.php" class="stage-action-btn" style="background: linear-gradient(135deg, var(--muted), #475569);">
                    <i class="fas fa-clock"></i> Awaiting Previous Stage
                </a>
                <?php endif; ?>
            </div>

            <!-- Stage 5: Disburse -->
            <div class="stage-details-container <?php echo $currentStage == 5 ? 'active' : ''; ?>" id="stage-5">
                <div class="stage-details-header">
                    <div class="stage-details-title">
                        <i class="fas fa-university" style="font-size: 24px; color: <?php echo $stage5Complete ? 'var(--success)' : 'var(--muted)'; ?>;"></i>
                        <h3>Stage 5: Salary Disbursement</h3>
                    </div>
                    <span class="stage-status-badge <?php echo $stage5Complete ? 'completed' : ($currentStage == 5 ? 'in-progress' : 'pending'); ?>">
                        <?php echo $stage5Complete ? 'Disbursed' : ($currentStage == 5 ? 'In Progress' : 'Pending'); ?>
                    </span>
                </div>
                <div class="stage-tasks">
                    <div class="stage-task <?php echo $stage5Complete ? 'done' : ''; ?>">
                        <div class="task-checkbox"><i class="fas fa-check"></i></div>
                        <span class="task-label">Generate bank file (NEFT/RTGS format)</span>
                    </div>
                    <div class="stage-task <?php echo $stage5Complete ? 'done' : ''; ?>">
                        <div class="task-checkbox"><i class="fas fa-check"></i></div>
                        <span class="task-label">Upload to bank portal</span>
                    </div>
                    <div class="stage-task <?php echo $stage5Complete ? 'done' : ''; ?>">
                        <div class="task-checkbox"><i class="fas fa-check"></i></div>
                        <span class="task-label">Mark salaries as disbursed</span>
                    </div>
                    <div class="stage-task <?php echo $stage5Complete ? 'done' : ''; ?>">
                        <div class="task-checkbox"><i class="fas fa-check"></i></div>
                        <span class="task-label">Send payslips to employees (email/portal)</span>
                    </div>
                </div>
                <?php if ($stage4Complete): ?>
                <a href="bank_file.php" class="stage-action-btn">
                    <i class="fas fa-university"></i> Generate Bank File
                </a>
                <?php else: ?>
                <a href="bank_file.php" class="stage-action-btn" style="background: linear-gradient(135deg, var(--muted), #475569);">
                    <i class="fas fa-clock"></i> Awaiting Director Approval
                </a>
                <?php endif; ?>
            </div>

            <!-- Stage 6: Reports & Compliance -->
            <div class="stage-details-container <?php echo $currentStage == 6 ? 'active' : ''; ?>" id="stage-6">
                <div class="stage-details-header">
                    <div class="stage-details-title">
                        <i class="fas fa-file-alt" style="font-size: 24px; color: <?php echo $stage6Complete ? 'var(--success)' : 'var(--muted)'; ?>;"></i>
                        <h3>Stage 6: Reports & Compliance</h3>
                    </div>
                    <span class="stage-status-badge <?php echo $stage6Complete ? 'completed' : ($currentStage == 6 ? 'in-progress' : 'pending'); ?>">
                        <?php echo $stage6Complete ? 'Complete' : ($currentStage == 6 ? 'In Progress' : 'Pending'); ?>
                    </span>
                </div>
                <div class="stage-tasks">
                    <div class="stage-task <?php echo $stage6Complete ? 'done' : ''; ?>">
                        <div class="task-checkbox"><i class="fas fa-check"></i></div>
                        <span class="task-label">Generate monthly salary reports</span>
                    </div>
                    <div class="stage-task <?php echo $stage6Complete ? 'done' : ''; ?>">
                        <div class="task-checkbox"><i class="fas fa-check"></i></div>
                        <span class="task-label">Prepare PF/NPS remittance reports</span>
                    </div>
                    <div class="stage-task <?php echo $stage6Complete ? 'done' : ''; ?>">
                        <div class="task-checkbox"><i class="fas fa-check"></i></div>
                        <span class="task-label">Generate TDS reports</span>
                    </div>
                    <div class="stage-task <?php echo $stage6Complete ? 'done' : ''; ?>">
                        <div class="task-checkbox"><i class="fas fa-check"></i></div>
                        <span class="task-label">Archive records for audit</span>
                    </div>
                </div>
                <?php if ($stage5Complete): ?>
                <a href="financial_reports.php" class="stage-action-btn">
                    <i class="fas fa-file-alt"></i> Generate Reports
                </a>
                <?php else: ?>
                <a href="financial_reports.php" class="stage-action-btn" style="background: linear-gradient(135deg, var(--muted), #475569);">
                    <i class="fas fa-clock"></i> Awaiting Disbursement
                </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Quick Actions -->
        <h2 class="section-title"><i class="fas fa-bolt"></i> Quick Actions</h2>
        <div class="quick-actions">
            <a href="payroll.php" class="action-card primary">
                <div class="action-icon">
                    <i class="fas fa-calculator"></i>
                </div>
                <span class="action-title">Process Payroll</span>
            </a>
            <a href="generate_attendance_statement.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-file-excel"></i>
                </div>
                <span class="action-title">Attendance Statement</span>
            </a>
            <a href="generate_payslip.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-file-pdf"></i>
                </div>
                <span class="action-title">Generate Payslip</span>
            </a>
            <a href="payslips.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-folder-open"></i>
                </div>
                <span class="action-title">View Payslips</span>
            </a>
            <a href="salary_structure.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <span class="action-title">Salary Structure</span>
            </a>
            <a href="bank_file.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-university"></i>
                </div>
                <span class="action-title">Generate Bank File</span>
            </a>
            <a href="manage_salary_config.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-cog"></i>
                </div>
                <span class="action-title">Salary Config</span>
            </a>
            <a href="manage_statement_officials.php" class="action-card">
                <div class="action-icon">
                    <i class="fas fa-user-tie"></i>
                </div>
                <span class="action-title">Statement Officials</span>
            </a>
        </div>

        <!-- Recent Payroll Activity -->
        <div class="activity-card">
            <div class="activity-header">
                <h2><i class="fas fa-history"></i> Recent Payroll Activity</h2>
                <a href="payslips.php" class="view-all-btn">View All <i class="fas fa-arrow-right"></i></a>
            </div>
            <?php if (empty($recentPayrolls)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <h3>No Payroll Records Yet</h3>
                    <p>Start by processing payroll for the current month</p>
                </div>
            <?php else: ?>
                <table class="activity-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Period</th>
                            <th>Net Salary</th>
                            <th>Status</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentPayrolls as $payroll): 
                            $initials = '';
                            $nameParts = explode(' ', $payroll['full_name']);
                            foreach ($nameParts as $part) {
                                $initials .= strtoupper(substr($part, 0, 1));
                            }
                            $initials = substr($initials, 0, 2);
                            
                            $status = $payroll['approval_status'] ?? 'pending';
                            $statusIcon = $status === 'approved' ? 'check-circle' : ($status === 'rejected' ? 'times-circle' : 'clock');
                        ?>
                            <tr>
                                <td>
                                    <div class="employee-info">
                                        <div class="employee-avatar"><?php echo $initials; ?></div>
                                        <span class="employee-name"><?php echo htmlspecialchars($payroll['full_name']); ?></span>
                                    </div>
                                </td>
                                <td><span class="period-badge"><?php echo $payroll['month'] . ' ' . $payroll['year']; ?></span></td>
                                <td class="amount">₹<?php echo number_format($payroll['net_salary'], 2); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $status; ?>">
                                        <i class="fas fa-<?php echo $statusIcon; ?>"></i>
                                        <?php echo ucfirst($status); ?>
                                    </span>
                                </td>
                                <td><?php echo date('d M Y', strtotime($payroll['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <?php include 'includes/accountant_scripts.php'; ?>
    <?php include 'includes/notification_popup.php'; ?>
    
    <script>
        // Dismiss alert banner
        function dismissAlert() {
            const alert = document.getElementById('attendanceAlert');
            if (alert) {
                alert.style.animation = 'slideUp 0.3s ease forwards';
                setTimeout(() => {
                    alert.style.display = 'none';
                }, 300);
                
                // Mark notifications as read via AJAX
                fetch('../api/mark_notifications_read.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ type: 'attendance_finalized' })
                }).catch(() => {});
            }
        }

        // Show stage details when clicked
        function showStageDetails(stageNum) {
            // Hide all stage details
            document.querySelectorAll('.stage-details-container').forEach(container => {
                container.classList.remove('active');
            });
            
            // Show selected stage
            const selectedStage = document.getElementById('stage-' + stageNum);
            if (selectedStage) {
                selectedStage.classList.add('active');
            }
            
            // Update timeline visual feedback
            document.querySelectorAll('.timeline-stage').forEach(stage => {
                stage.style.opacity = '0.6';
            });
            document.querySelector('.timeline-stage[data-stage="' + stageNum + '"]').style.opacity = '1';
            
            // Scroll to details
            selectedStage.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Reset opacity on hover out
            document.querySelectorAll('.timeline-stage').forEach(stage => {
                stage.addEventListener('mouseleave', function() {
                    document.querySelectorAll('.timeline-stage').forEach(s => {
                        s.style.opacity = '1';
                    });
                });
            });

            // Auto-show current stage details (already handled by PHP)
            const activeStage = document.querySelector('.stage-details-container.active');
            if (!activeStage) {
                const currentStage = document.querySelector('.timeline-stage.current');
                if (currentStage) {
                    showStageDetails(parseInt(currentStage.dataset.stage));
                } else {
                    // Default to stage 1 if none is current
                    showStageDetails(1);
                }
            }
        });

        // Month selector change handler
        document.getElementById('workflowMonth')?.addEventListener('change', function() {
            // Reload with selected month (would require backend support)
            console.log('Selected month:', this.value);
        });
    </script>
    
    <style>
        @keyframes slideUp {
            from { opacity: 1; transform: translateY(0); }
            to { opacity: 0; transform: translateY(-20px); }
        }
    </style>
</body>
</html>
