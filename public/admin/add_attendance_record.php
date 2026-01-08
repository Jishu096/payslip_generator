<?php
session_start();

// Admin-only access
if (!isset($_SESSION['role'])) {
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $employeeId = $_POST['employee_id'];
        $leaveType = $_POST['leave_type'];
        $startDate = $_POST['start_date'];
        $endDate = $_POST['end_date'];
        $natureOfLeave = $_POST['nature_of_leave'] ?? null;
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
        
        // Insert leave record
        $stmt = $db->prepare("
            INSERT INTO attendance_leave_details 
            (employee_id, leave_type, start_date, end_date, total_days, is_half_day, 
             half_day_type, nature_of_leave, status, approved_by)
            VALUES 
            (:employee_id, :leave_type, :start_date, :end_date, :total_days, :is_half_day,
             :half_day_type, :nature_of_leave, 'approved', :approved_by)
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
            ':approved_by' => $_SESSION['user_id'] ?? null
        ]);
        
        // Recalculate monthly summary
        $month = date('n', strtotime($startDate));
        $year = date('Y', strtotime($startDate));
        $summary = $attendanceHelper->calculateMonthlySummary($employeeId, $month, $year);
        $attendanceHelper->saveMonthlySummary($summary);
        
        $message = "Leave/Absence record added successfully and monthly summary updated!";
        $messageType = "success";
        
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
    <title>Add Leave/Absence Record</title>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 30px 20px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 20px;
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }

        .card-header h2 {
            color: #2d3748;
            font-size: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #2d3748;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #2d3748;
        }

        .form-group label .required {
            color: #e53e3e;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-top: 10px;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
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
            background: #c6f6d5;
            border-left: 4px solid #48bb78;
            color: #22543d;
        }

        .alert-error {
            background: #fed7d7;
            border-left: 4px solid #e53e3e;
            color: #742a2a;
        }

        .help-text {
            font-size: 12px;
            color: #718096;
            margin-top: 5px;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h2>
                    <i class="fas fa-calendar-plus"></i>
                    Add Leave/Absence Record
                </h2>
                <a href="attendance_statement.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> Back to Statement
                </a>
            </div>

            <form method="POST" action="" id="leaveForm">
                <div class="form-group">
                    <label>
                        Employee <span class="required">*</span>
                    </label>
                    <select name="employee_id" required>
                        <option value="">-- Select Employee --</option>
                        <?php foreach ($employees as $emp): ?>
                            <option value="<?= $emp['employee_id'] ?>">
                                <?= htmlspecialchars($emp['full_name']) ?> - <?= htmlspecialchars($emp['designation']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>
                        Leave/Absence Type <span class="required">*</span>
                    </label>
                    <select name="leave_type" id="leaveType" required>
                        <option value="">-- Select Type --</option>
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
                    <div class="help-text">
                        OD/Tour are payable days. EL, CL, etc. are leave days. Absent reduces salary for contract staff.
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>
                            Start Date <span class="required">*</span>
                        </label>
                        <input type="date" name="start_date" id="startDate" required>
                    </div>

                    <div class="form-group">
                        <label>
                            End Date <span class="required">*</span>
                        </label>
                        <input type="date" name="end_date" id="endDate" required>
                    </div>
                </div>

                <div class="form-group" id="halfDaySection" style="display: none;">
                    <label>Half Day Options</label>
                    <div class="checkbox-group">
                        <input type="checkbox" name="is_half_day" id="isHalfDay">
                        <label for="isHalfDay" style="margin: 0;">This is a half day</label>
                    </div>
                    <select name="half_day_type" id="halfDayType" style="margin-top: 10px; display: none;">
                        <option value="">-- Select Half --</option>
                        <option value="FN">FN (Forenoon)</option>
                        <option value="AN">AN (Afternoon)</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>
                        Nature of Leave/Reason
                    </label>
                    <textarea name="nature_of_leave" rows="3" 
                              placeholder="E.g., Official meeting at NIELIT Delhi, Medical emergency, Personal work..."></textarea>
                    <div class="help-text">
                        Provide brief description of leave reason (optional but recommended for OD/Tour)
                    </div>
                </div>

                <div style="display: flex; gap: 10px; margin-top: 25px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Record & Update Summary
                    </button>
                    <button type="reset" class="btn btn-secondary">
                        <i class="fas fa-undo"></i> Reset Form
                    </button>
                </div>
            </form>
        </div>
    </div>

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
