<?php
session_start();

// Support both single-role and multi-role scenarios
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? null];
$hasAdminRole = in_array('administrator', $userRoles);

// Admin-only access
if (!isset($_SESSION['role']) || (!$hasAdminRole && $_SESSION['role'] !== 'administrator')) {
    header("Location: ../auth/login.php");
    exit;
}

require_once "../../app/Config/database.php";
require_once "../../app/Models/Employee.php";
require_once "../../app/Helpers/AttendanceStatementHelper.php";

$db = getDBConnection();
$employeeModel = new Employee();
$attendanceHelper = new AttendanceStatementHelper($db);

$message = '';
$messageType = '';

// Check if editing
$editMode = isset($_GET['id']) && !empty($_GET['id']);
$recordId = $_GET['id'] ?? null;
$existingRecord = null;

if ($editMode) {
    // Fetch existing record
    $stmt = $db->prepare("SELECT * FROM attendance_leave_details WHERE detail_id = :id");
    $stmt->execute([':id' => $recordId]);
    $existingRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existingRecord) {
        $message = "Record not found!";
        $messageType = "error";
        $editMode = false;
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $employeeId = $_POST['employee_id'];
        $leaveType = $_POST['leave_type'];
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'];
        $natureOfLeave = $_POST['nature_of_leave'] ?? null;
        $remarks = $_POST['remarks'] ?? null;
        $isHalfDay = isset($_POST['is_half_day']) ? 1 : 0;
        $halfDayType = $_POST['half_day_type'] ?? null;
        
        // Calculate total days
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $interval = $start->diff($end);
        $totalDays = $interval->days + 1;
        
        if ($isHalfDay) {
            $totalDays = 0.5;
        }
        
        if (isset($_POST['record_id']) && !empty($_POST['record_id'])) {
            // UPDATE existing record
            $stmt = $db->prepare("
                UPDATE attendance_leave_details 
                SET employee_id = :employee_id,
                    leave_type = :leave_type,
                    start_date = :start_date,
                    end_date = :end_date,
                    total_days = :total_days,
                    is_half_day = :is_half_day,
                    half_day_type = :half_day_type,
                    nature_of_leave = :nature_of_leave,
                    remarks = :remarks
                WHERE detail_id = :id
            ");
            
            $stmt->execute([
                ':id' => $_POST['record_id'],
                ':employee_id' => $employeeId,
                ':leave_type' => $leaveType,
                ':start_date' => $startDate,
                ':end_date' => $endDate,
                ':total_days' => $totalDays,
                ':is_half_day' => $isHalfDay,
                ':half_day_type' => $halfDayType,
                ':nature_of_leave' => $natureOfLeave,
                ':remarks' => $remarks
            ]);
            
            $message = "Record updated successfully!";
        } else {
            // INSERT new record
            $stmt = $db->prepare("
                INSERT INTO attendance_leave_details 
                (employee_id, leave_type, start_date, end_date, total_days, is_half_day, 
                 half_day_type, nature_of_leave, remarks, status, approved_by)
                VALUES 
                (:employee_id, :leave_type, :start_date, :end_date, :total_days, :is_half_day,
                 :half_day_type, :nature_of_leave, :remarks, 'approved', :approved_by)
            ");
            
            $stmt->execute([
                ':employee_id' => $employeeId,
                ':leave_type' => $leaveType,
                ':start_date' => $startDate,
                ':end_date' => $endDate,
                ':total_days' => $totalDays,
                ':is_half_day' => $isHalfDay,
                ':half_day_type' => $halfDayType,
                ':nature_of_leave' => $natureOfLeave,
                ':remarks' => $remarks,
                ':approved_by' => $_SESSION['user_id'] ?? null
            ]);
            
            $message = "Record added successfully!";
        }
        
        // Recalculate monthly summary
        $month = date('n', strtotime($startDate));
        $year = date('Y', strtotime($startDate));
        $summary = $attendanceHelper->calculateMonthlySummary($employeeId, $month, $year);
        $attendanceHelper->saveMonthlySummary($summary);
        
        $messageType = "success";
        
        // Clear edit mode after successful update
        if (isset($_POST['record_id'])) {
            $editMode = false;
            $existingRecord = null;
        }
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = "error";
    }
}

// Get all employees
$employees = $employeeModel->getAllEmployees();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Attendance Record - Admin Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        .page-container {
            max-width: 800px; /* Narrower for form focus */
            margin: 0 auto;
        }
        
        .form-section {
            padding: 30px;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #4a5568;
            font-size: 14px;
        }

        .form-group label span.required {
            color: #e53e3e;
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
            background: #f8fafc;
        }

        .form-control:focus {
            background: white;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            outline: none;
        }

        .form-row-2 {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .help-text {
            font-size: 12px;
            color: #718096;
            margin-top: 6px;
        }

        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f7fafc;
            padding: 15px;
            border-radius: 10px;
            border: 1px solid #edf2f7;
        }

        .checkbox-wrapper input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #667eea;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 12px 25px;
            border-radius: 10px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: transform 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_navbar.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

    <main class="main-content">
        <div class="page-container">
            <div class="page-header">
                <a href="attendance_statement.php" class="back-link" style="display: inline-block; margin-bottom: 10px; color: #718096; text-decoration: none; font-size: 14px;">
                    <i class="fas fa-arrow-left"></i> Back to Statement
                </a>
                <h1><?= $editMode ? 'Edit' : 'Add' ?> Attendance Record</h1>
                <p><?= $editMode ? 'Update existing leave or attendance record.' : 'Manually add past leave or attendance records.' ?></p>
            </div>

            <?php if ($message): ?>
                <div class="alert alert-<?= $messageType ?>" style="margin-bottom: 25px; padding: 15px; border-radius: 10px; display: flex; align-items: center; gap: 10px; 
                    background: <?= $messageType === 'success' ? '#def7ec' : '#fde8e8' ?>; 
                    color: <?= $messageType === 'success' ? '#03543f' : '#9b1c1c' ?>;">
                    <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <div class="glass-card form-section">
                <form method="POST" action="" id="leaveForm">
                    <?php if ($editMode && $existingRecord): ?>
                        <input type="hidden" name="record_id" value="<?= $existingRecord['detail_id'] ?>">
                    <?php endif; ?>
                    <div class="form-grid">
                        <!-- Employee Selection -->
                        <div class="form-group">
                            <label>Select Employee <span class="required">*</span></label>
                            <select name="employee_id" class="form-control" required>
                                <option value="">-- Choose Employee --</option>
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?= $emp['employee_id'] ?>" <?= ($editMode && $existingRecord['employee_id'] == $emp['employee_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($emp['full_name']) ?> (<?= htmlspecialchars($emp['designation']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Leave Type -->
                        <div class="form-group">
                            <label>Record Type <span class="required">*</span></label>
                            <select name="leave_type" id="leaveType" class="form-control" required>
                                <option value="">-- Choose Type --</option>
                                <optgroup label="Regular Employee Leaves">
                                    <option value="OD">OD (Official Duty)</option>
                                    <option value="Tour">Tour</option>
                                    <option value="EL">EL (Earned Leave)</option>
                                    <option value="CCL">CCL (Commuted Leave)</option>
                                    <option value="PL">PL (Privilege Leave)</option>
                                    <option value="CL">CL (Casual Leave)</option>
                                    <option value="RH">RH (Restricted Holiday)</option>
                                </optgroup>
                                <optgroup label="Contract Employee">
                                    <option value="Absent">Absent (Unpaid)</option>
                                </optgroup>
                                <option value="Half_Day">Half Day Leave</option>
                            </select>
                            <div class="help-text">Select the type of leave or duty to record.</div>
                        </div>

                        <!-- Date Range -->
                        <div class="form-row-2">
                            <div class="form-group">
                                <label>Start Date <span class="required">*</span></label>
                                <input type="date" name="start_date" id="startDate" class="form-control" value="<?= $editMode ? $existingRecord['start_date'] : '' ?>" required>
                            </div>
                            <div class="form-group">
                                <label>End Date <span class="required">*</span></label>
                                <input type="date" name="end_date" id="endDate" class="form-control" value="<?= $editMode ? $existingRecord['end_date'] : '' ?>" required>
                            </div>
                        </div>

                        <!-- Half Day Logic -->
                        <div class="form-group" id="halfDaySection" style="display: none;">
                            <label>Half Day Details</label>
                            <div class="checkbox-wrapper">
                                <input type="checkbox" name="is_half_day" id="isHalfDay" <?= ($editMode && $existingRecord['is_half_day']) ? 'checked' : '' ?>>
                                <label for="isHalfDay" style="margin:0; font-weight:normal;">Mark as Half Day</label>
                            </div>
                            <div style="margin-top: 10px;">
                                <select name="half_day_type" id="halfDayType" class="form-control" style="display: none;">
                                    <option value="">-- Select Shift --</option>
                                    <option value="FN">Forenoon (FN)</option>
                                    <option value="AN">Afternoon (AN)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Nature of Leave -->
                        <div class="form-group">
                            <label>Nature of Leave</label>
                            <input type="text" name="nature_of_leave" class="form-control" value="<?= $editMode ? htmlspecialchars($existingRecord['nature_of_leave'] ?? '') : '' ?>" placeholder="E.g., Medical emergency, Official meeting, Personal work...">
                            <div class="help-text">Brief description of the leave type or reason</div>
                        </div>

                        <!-- Remarks -->
                        <div class="form-group">
                            <label>Remarks (Optional)</label>
                            <textarea name="remarks" rows="3" class="form-control" placeholder="Additional notes or comments..."><?= $editMode ? htmlspecialchars($existingRecord['remarks'] ?? '') : '' ?></textarea>
                            <div class="help-text">Any additional information or administrative notes</div>
                        </div>

                        <!-- Submit -->
                        <div style="margin-top: 10px;">
                            <button type="submit" class="btn-submit">
                                <i class="fas fa-save"></i> <?= $editMode ? 'Update' : 'Save' ?> Record
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script>
        // Auto-set end date same as start date
        document.getElementById('startDate').addEventListener('change', function() {
            const endDate = document.getElementById('endDate');
            if (!endDate.value) {
                endDate.value = this.value;
            }
        });

        // Show/hide half day options
        document.getElementById('leaveType').addEventListener('change', function() {
            const halfDaySection = document.getElementById('halfDaySection');
            if (this.value === 'Half_Day') {
                halfDaySection.style.display = 'block';
            } else {
                halfDaySection.style.display = 'none';
                document.getElementById('isHalfDay').checked = false;
                document.getElementById('halfDayType').style.display = 'none';
            }
        });

        document.getElementById('isHalfDay').addEventListener('change', function() {
            const halfDayType = document.getElementById('halfDayType');
            if (this.checked) {
                halfDayType.style.display = 'block';
                halfDayType.required = true;
            } else {
                halfDayType.style.display = 'none';
                halfDayType.required = false;
            }
        });

        // Form validation
        document.getElementById('leaveForm').addEventListener('submit', function(e) {
            const startDate = new Date(document.getElementById('startDate').value);
            const endDate = new Date(document.getElementById('endDate').value);
            
            if (endDate < startDate) {
                e.preventDefault();
                alert('End date cannot be before start date!');
                return false;
            }
        });
    </script>
</body>
</html>
