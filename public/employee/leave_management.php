<?php
session_start();

// Support both single-role and multi-role scenarios
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? null];
$hasEmployeeRole = in_array('employee', $userRoles);

if (!isset($_SESSION['role']) || (!$hasEmployeeRole && $_SESSION['role'] !== 'employee')) {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'Employee';
$employeeId = $_SESSION['employee_id'];

require_once "../../app/Models/Employee.php";
require_once "../../app/Models/LeaveRequest.php";

$employeeModel = new Employee();
$leaveModel = new LeaveRequest();

// Get employee details
$employeeData = $employeeModel->getEmployeeById($employeeId);
$employeeName = $employeeData['first_name'] . ' ' . $employeeData['last_name'];

// Handle form submission
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_leave'])) {
    $leaveType = $_POST['leave_type'] ?? '';
    $startDate = $_POST['start_date'] ?? '';
    $endDate = $_POST['end_date'] ?? '';
    $reason = $_POST['reason'] ?? '';
    
    if (empty($leaveType) || empty($startDate) || empty($endDate) || empty($reason)) {
        $error = "All fields are required.";
    } elseif (strtotime($endDate) < strtotime($startDate)) {
        $error = "End date must be after start date.";
    } else {
        if ($leaveModel->submitLeaveRequest($employeeId, $employeeName, $leaveType, $startDate, $endDate, $reason)) {
            $success = true;
        } else {
            $error = "Failed to submit leave request. Please try again.";
        }
    }
}

// Get leave requests history
$leaveRequests = $leaveModel->getLeaveRequestsByEmployee($employeeId);

// Get leave balance
$leaveBalance = $leaveModel->getLeaveBalance($employeeId);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Management - Employee Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: #f7fafc;
            color: #2d3748;
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 30px;
        }

        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
        }

        .breadcrumb {
            font-size: 14px;
            margin-bottom: 10px;
            opacity: 0.9;
        }

        .breadcrumb a {
            color: white;
            text-decoration: none;
            transition: opacity 0.3s;
        }

        .breadcrumb a:hover {
            opacity: 0.8;
        }

        .breadcrumb i {
            margin: 0 8px;
            font-size: 10px;
        }

        .header h1 {
            font-size: 32px;
            font-weight: 700;
            margin: 0;
        }

        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 30px;
        }

        @media (max-width: 968px) {
            .grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .card-header {
            padding: 20px 25px;
            background: #f7fafc;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-header h2 {
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
            margin: 0;
        }

        .card-header i {
            color: #667eea;
            font-size: 20px;
        }

        .card-body {
            padding: 25px;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
        }

        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }

        .alert-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            font-size: 13px;
            color: #4a5568;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #cbd5e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: 'Roboto', sans-serif;
            transition: all 0.3s;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            width: 100%;
            justify-content: center;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .leave-balance {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }

        .balance-card {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
        }

        .balance-number {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .balance-label {
            font-size: 12px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th {
            background: #f7fafc;
            padding: 15px 20px;
            text-align: left;
            font-weight: 600;
            font-size: 13px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 18px 20px;
            border-bottom: 1px solid #e2e8f0;
        }

        tbody tr:hover {
            background: #f7fafc;
        }

        .status-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: inline-block;
        }

        .status-badge.pending {
            background: #feebc8;
            color: #7c2d12;
        }

        .status-badge.approved {
            background: #c6f6d5;
            color: #22543d;
        }

        .status-badge.rejected {
            background: #fed7d7;
            color: #742a2a;
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #a0aec0;
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.5;
        }

        .back-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            font-size: 24px;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }

        .back-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="breadcrumb">
                <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
                <i class="fas fa-chevron-right"></i>
                <span>Leave Management</span>
            </div>
            <h1><i class="fas fa-calendar-alt"></i> Leave Management</h1>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <span>Leave request submitted successfully! Awaiting approval.</span>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <span><?= htmlspecialchars($error) ?></span>
            </div>
        <?php endif; ?>

        <div class="grid">
            <!-- Leave Application Form -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-plus-circle"></i>
                    <h2>Apply for Leave</h2>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="form-group">
                            <label for="leave_type">
                                <i class="fas fa-tag"></i> Leave Type
                            </label>
                            <select id="leave_type" name="leave_type" required>
                                <option value="">Select Leave Type</option>
                                <option value="casual">Casual Leave</option>
                                <option value="sick">Sick Leave</option>
                                <option value="paid">Paid Leave</option>
                                <option value="unpaid">Unpaid Leave</option>
                                <option value="maternity">Maternity Leave</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="start_date">
                                <i class="far fa-calendar"></i> Start Date
                            </label>
                            <input type="date" 
                                   id="start_date" 
                                   name="start_date" 
                                   min="<?= date('Y-m-d') ?>"
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="end_date">
                                <i class="far fa-calendar-check"></i> End Date
                            </label>
                            <input type="date" 
                                   id="end_date" 
                                   name="end_date" 
                                   min="<?= date('Y-m-d') ?>"
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="reason">
                                <i class="fas fa-comment"></i> Reason
                            </label>
                            <textarea id="reason" 
                                      name="reason" 
                                      placeholder="Please provide a reason for your leave request..."
                                      required></textarea>
                        </div>

                        <button type="submit" name="submit_leave" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> Submit Request
                        </button>
                    </form>
                </div>
            </div>

            <!-- Leave Balance -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-chart-pie"></i>
                    <h2>Leave Balance (<?= date('Y') ?>)</h2>
                </div>
                <div class="card-body">
                    <?php if (empty($leaveBalance)): ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No leave taken yet this year</p>
                        </div>
                    <?php else: ?>
                        <div class="leave-balance">
                            <?php foreach ($leaveBalance as $balance): ?>
                                <div class="balance-card">
                                    <div class="balance-number"><?= $balance['days_taken'] ?></div>
                                    <div class="balance-label"><?= ucfirst($balance['leave_type']) ?> Days</div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Leave History -->
        <div class="card">
            <div class="card-header">
                <i class="fas fa-history"></i>
                <h2>Leave History</h2>
            </div>
            <div class="card-body">
                <?php if (empty($leaveRequests)): ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <p>No leave requests found</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Start Date</th>
                                <th>End Date</th>
                                <th>Days</th>
                                <th>Status</th>
                                <th>Requested On</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($leaveRequests as $request): 
                                $days = (strtotime($request['end_date']) - strtotime($request['start_date'])) / 86400 + 1;
                            ?>
                            <tr>
                                <td><?= ucfirst($request['leave_type']) ?></td>
                                <td><?= date('M d, Y', strtotime($request['start_date'])) ?></td>
                                <td><?= date('M d, Y', strtotime($request['end_date'])) ?></td>
                                <td><?= $days ?> days</td>
                                <td>
                                    <span class="status-badge <?= strtolower($request['status']) ?>">
                                        <?= ucfirst($request['status']) ?>
                                    </span>
                                </td>
                                <td><?= isset($request['request_date']) ? date('M d, Y', strtotime($request['request_date'])) : 'N/A' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Back Button -->
    <a href="dashboard.php" class="back-btn" title="Back to Dashboard">
        <i class="fas fa-arrow-left"></i>
    </a>

    <script>
        // Auto-update end date minimum when start date changes
        document.getElementById('start_date').addEventListener('change', function() {
            document.getElementById('end_date').min = this.value;
        });
    </script>
</body>
</html>
