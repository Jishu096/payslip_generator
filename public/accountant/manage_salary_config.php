<?php
session_start();

// Role check (multi-role support)
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? null];
$hasAccountantRole = in_array('accountant', $userRoles);

if (!$hasAccountantRole) {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'User';
$baseURL = "/payslip_generator/public/";

// Load models
require_once __DIR__ . '/../../app/Models/SalaryConfig.php';
$salaryConfigModel = new SalaryConfig();

// Get current month and year
$currentMonth = (int)date('n');
$currentYear = (int)date('Y');
$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : $currentMonth;
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : $currentYear;

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_config') {
        $configData = [
            'month' => (int)$_POST['month'],
            'year' => (int)$_POST['year'],
            'da_rate_contractual' => (float)$_POST['da_rate_contractual'],
            'da_rate_intern' => (float)$_POST['da_rate_intern'],
            'tour_da_rate_contractual' => (float)$_POST['tour_da_rate_contractual'],
            'tour_da_rate_intern' => (float)$_POST['tour_da_rate_intern'],
            'office_da_rate_contractual' => (float)$_POST['office_da_rate_contractual'],
            'office_da_rate_intern' => (float)$_POST['office_da_rate_intern'],
            'da_enabled' => isset($_POST['da_enabled']) ? 1 : 0,
            'updated_by' => $_SESSION['user_id'],
            'notes' => $_POST['notes'] ?? ''
        ];
        
        // Validate
        $validation = $salaryConfigModel->validateConfig($configData);
        
        if ($validation['success']) {
            if ($salaryConfigModel->upsertConfig($configData)) {
                $message = "Salary configuration updated successfully for " . date('F Y', mktime(0, 0, 0, $configData['month'], 1, $configData['year']));
                $messageType = 'success';
                $selectedMonth = $configData['month'];
                $selectedYear = $configData['year'];
            } else {
                $message = "Failed to update salary configuration";
                $messageType = 'error';
            }
        } else {
            $message = "Validation failed: " . implode(', ', $validation['errors']);
            $messageType = 'error';
        }
    } elseif ($_POST['action'] === 'create_year_defaults') {
        $year = (int)$_POST['create_year'];
        if ($salaryConfigModel->createDefaultConfigsForYear($year, $_SESSION['user_id'])) {
            $message = "Default configurations created for all months of $year";
            $messageType = 'success';
        } else {
            $message = "Failed to create default configurations";
            $messageType = 'error';
        }
    }
}

// Load configuration for selected month
$config = $salaryConfigModel->getConfigByMonth($selectedMonth, $selectedYear);

// If no config exists, set defaults
if (!$config) {
    $config = [
        'da_rate_contractual' => 300.00,
        'da_rate_intern' => 200.00,
        'tour_da_rate_contractual' => 500.00,
        'tour_da_rate_intern' => 300.00,
        'office_da_rate_contractual' => 300.00,
        'office_da_rate_intern' => 200.00,
        'da_enabled' => 1,
        'notes' => ''
    ];
}

// Get all configs for current year for overview
$yearConfigs = $salaryConfigModel->getConfigsByYear($selectedYear);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Configuration - e-HRMS</title>
    <?php include 'includes/accountant_styles.php'; ?>
    <style>
        :root {
            --bg: #f8fafc;
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
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Roboto', sans-serif;
            background: var(--bg);
            color: var(--text);
        }
        
        .config-container {
            width: 100%;
        }
        
        .header-section {
            background: linear-gradient(135deg, #667eea, #764ba2);
            padding: 40px;
            border-radius: 20px;
            color: white;
            margin-bottom: 40px;
            box-shadow: 0 10px 40px rgba(102, 126, 234, 0.3);
        }
        
        .header-section h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .header-section p {
            opacity: 0.95;
            font-size: 16px;
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #6ee7b7;
        }
        
        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        
        .config-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 30px;
        }
        
        @media (max-width: 1024px) {
            .config-grid {
                grid-template-columns: 1fr;
            }
        }
        
        .config-card {
            background: var(--card);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.08);
        }
        
        .config-card h2 {
            font-size: 24px;
            margin-bottom: 20px;
            color: var(--accent);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-group label {
            display: block;
            font-weight: 500;
            margin-bottom: 8px;
            color: var(--text);
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 13px;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s ease;
            font-family: 'Roboto', sans-serif;
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            background: #f1f5f9;
            border-radius: 12px;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            font-weight: 500;
        }
        
        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #f1f5f9;
            color: var(--text);
        }
        
        .btn-secondary:hover {
            background: #e2e8f0;
        }
        
        .rate-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 16px;
        }
        
        .rate-item {
            padding: 16px;
            background: #f8fafc;
            border-radius: 12px;
            border-left: 4px solid var(--accent);
        }
        
        .rate-item h4 {
            font-size: 14px;
            color: var(--muted);
            margin-bottom: 6px;
        }
        
        .rate-item .value {
            font-size: 24px;
            font-weight: 600;
            color: var(--text);
        }
        
        .month-selector {
            display: flex;
            gap: 16px;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .overview-table {
            width: 100%;
            border-collapse: collapse;
            background: white;
            border-radius: 12px;
            overflow: hidden;
        }
        
        .overview-table th,
        .overview-table td {
            padding: 14px;
            text-align: left;
            border-bottom: 1px solid var(--border);
        }
        
        .overview-table th {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            font-weight: 600;
        }
        
        .overview-table tr:hover {
            background: #f8fafc;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 500;
        }
        
        .status-enabled {
            background: #d1fae5;
            color: #065f46;
        }
        
        .status-disabled {
            background: #fee2e2;
            color: #991b1b;
        }
        
        .info-box {
            background: #dbeafe;
            border-left: 4px solid #3b82f6;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        
        .info-box h3 {
            color: #1e40af;
            margin-bottom: 10px;
            font-size: 18px;
        }
        
        .info-box ul {
            margin-left: 20px;
            color: #1e3a8a;
        }
        
        .info-box ul li {
            margin-bottom: 6px;
        }
    </style>
</head>
<body>
    <?php include 'includes/accountant_navbar.php'; ?>
    
    <div class="main-content">
        <div class="config-container">
        <div class="header-section">
            <h1><i class="fas fa-cog"></i> Salary Configuration Management</h1>
            <p>Configure DA rates and salary rules for Contractual and Intern employees</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="info-box">
            <h3><i class="fas fa-info-circle"></i> Salary Calculation Rules</h3>
            <ul>
                <li><strong>Contractual Employees:</strong> Pay = (Daily Rate × Working Days) + (DA Rate × OD/Tour Days)</li>
                <li><strong>Intern Employees:</strong> Pay = Fixed Stipend + (DA Rate × OD/Tour Days)</li>
                <li><strong>DA Types:</strong> Regular DA (local OD), Tour DA (outstation travel)</li>
                <li>DA is only paid for verified OD/TOUR attendance days</li>
                <li>You can disable DA entirely for a month using the toggle below</li>
            </ul>
        </div>
        
        <!-- Month/Year Selector -->
        <form method="GET" class="month-selector">
            <div class="form-group" style="margin: 0; flex: 1;">
                <select name="month" class="form-control">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?php echo $m; ?>" <?php echo $m == $selectedMonth ? 'selected' : ''; ?>>
                            <?php echo date('F', mktime(0, 0, 0, $m, 1)); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <div class="form-group" style="margin: 0; flex: 1;">
                <select name="year" class="form-control">
                    <?php for ($y = 2024; $y <= 2030; $y++): ?>
                        <option value="<?php echo $y; ?>" <?php echo $y == $selectedYear ? 'selected' : ''; ?>>
                            <?php echo $y; ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-secondary">
                <i class="fas fa-sync"></i> Load Month
            </button>
        </form>
        
        <div class="config-grid">
            <!-- Configuration Form -->
            <div class="config-card">
                <h2><i class="fas fa-edit"></i> Edit Configuration for <?php echo date('F Y', mktime(0, 0, 0, $selectedMonth, 1, $selectedYear)); ?></h2>
                
                <form method="POST" action="">
                    <input type="hidden" name="action" value="update_config">
                    <input type="hidden" name="month" value="<?php echo $selectedMonth; ?>">
                    <input type="hidden" name="year" value="<?php echo $selectedYear; ?>">
                    
                    <div class="form-group">
                        <label><i class="fas fa-briefcase"></i> Contractual DA Rate (₹ per day)</label>
                        <input type="number" name="da_rate_contractual" step="0.01" 
                               value="<?php echo $config['da_rate_contractual']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-user-graduate"></i> Intern DA Rate (₹ per day)</label>
                        <input type="number" name="da_rate_intern" step="0.01" 
                               value="<?php echo $config['da_rate_intern']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-plane"></i> Contractual Tour DA Rate (₹ per day)</label>
                        <input type="number" name="tour_da_rate_contractual" step="0.01" 
                               value="<?php echo $config['tour_da_rate_contractual']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-plane"></i> Intern Tour DA Rate (₹ per day)</label>
                        <input type="number" name="tour_da_rate_intern" step="0.01" 
                               value="<?php echo $config['tour_da_rate_intern']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Contractual Office DA Rate (₹ per day)</label>
                        <input type="number" name="office_da_rate_contractual" step="0.01" 
                               value="<?php echo $config['office_da_rate_contractual']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-map-marker-alt"></i> Intern Office DA Rate (₹ per day)</label>
                        <input type="number" name="office_da_rate_intern" step="0.01" 
                               value="<?php echo $config['office_da_rate_intern']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <div class="checkbox-group">
                            <input type="checkbox" name="da_enabled" id="da_enabled" 
                                   <?php echo $config['da_enabled'] ? 'checked' : ''; ?>>
                            <label for="da_enabled">
                                <i class="fas fa-toggle-on"></i> Enable DA for this month
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label><i class="fas fa-sticky-note"></i> Notes (Optional)</label>
                        <textarea name="notes" rows="3" placeholder="Add any notes about this configuration..."><?php echo htmlspecialchars($config['notes']); ?></textarea>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Configuration
                    </button>
                </form>
            </div>
            
            <!-- Current Configuration Preview -->
            <div class="config-card">
                <h2><i class="fas fa-eye"></i> Current Configuration</h2>
                
                <div class="rate-grid">
                    <div class="rate-item">
                        <h4>Contractual DA</h4>
                        <div class="value">₹<?php echo number_format($config['da_rate_contractual'], 2); ?></div>
                    </div>
                    <div class="rate-item">
                        <h4>Intern DA</h4>
                        <div class="value">₹<?php echo number_format($config['da_rate_intern'], 2); ?></div>
                    </div>
                    <div class="rate-item">
                        <h4>Contractual Tour DA</h4>
                        <div class="value">₹<?php echo number_format($config['tour_da_rate_contractual'], 2); ?></div>
                    </div>
                    <div class="rate-item">
                        <h4>Intern Tour DA</h4>
                        <div class="value">₹<?php echo number_format($config['tour_da_rate_intern'], 2); ?></div>
                    </div>
                </div>
                
                <div style="margin-top: 30px; padding: 20px; background: <?php echo $config['da_enabled'] ? '#d1fae5' : '#fee2e2'; ?>; border-radius: 12px;">
                    <h3 style="margin-bottom: 10px; color: <?php echo $config['da_enabled'] ? '#065f46' : '#991b1b'; ?>;">
                        <i class="fas fa-<?php echo $config['da_enabled'] ? 'check-circle' : 'times-circle'; ?>"></i>
                        DA Status: <?php echo $config['da_enabled'] ? 'Enabled' : 'Disabled'; ?>
                    </h3>
                    <p style="color: <?php echo $config['da_enabled'] ? '#047857' : '#7f1d1d'; ?>; margin: 0;">
                        <?php if ($config['da_enabled']): ?>
                            DA will be calculated and added to payslips for this month.
                        <?php else: ?>
                            DA is disabled. No DA will be added to payslips for this month.
                        <?php endif; ?>
                    </p>
                </div>
                
                <div style="margin-top: 30px;">
                    <h3 style="margin-bottom: 15px;"><i class="fas fa-calculator"></i> Sample Calculations</h3>
                    
                    <div style="background: #f8fafc; padding: 15px; border-radius: 10px; margin-bottom: 15px;">
                        <h4 style="color: var(--accent); margin-bottom: 10px;">Contractual Employee</h4>
                        <p style="margin: 0; color: var(--muted);">
                            Daily Rate: ₹800 × 22 working days = ₹17,600<br>
                            DA: ₹<?php echo number_format($config['da_rate_contractual'], 2); ?> × 3 OD days = ₹<?php echo number_format($config['da_rate_contractual'] * 3, 2); ?><br>
                            <strong>Total: ₹<?php echo number_format(17600 + ($config['da_rate_contractual'] * 3), 2); ?></strong>
                        </p>
                    </div>
                    
                    <div style="background: #f8fafc; padding: 15px; border-radius: 10px;">
                        <h4 style="color: var(--accent); margin-bottom: 10px;">Intern</h4>
                        <p style="margin: 0; color: var(--muted);">
                            Stipend: ₹10,000<br>
                            DA: ₹<?php echo number_format($config['da_rate_intern'], 2); ?> × 2 OD days = ₹<?php echo number_format($config['da_rate_intern'] * 2, 2); ?><br>
                            <strong>Total: ₹<?php echo number_format(10000 + ($config['da_rate_intern'] * 2), 2); ?></strong>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Year Overview -->
        <div class="config-card">
            <h2><i class="fas fa-calendar-alt"></i> Year <?php echo $selectedYear; ?> Overview</h2>
            
            <?php if (empty($yearConfigs)): ?>
                <p style="color: var(--muted); padding: 20px; text-align: center;">
                    No configurations found for <?php echo $selectedYear; ?>.
                </p>
                <form method="POST" style="text-align: center;">
                    <input type="hidden" name="action" value="create_year_defaults">
                    <input type="hidden" name="create_year" value="<?php echo $selectedYear; ?>">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create Default Configurations for <?php echo $selectedYear; ?>
                    </button>
                </form>
            <?php else: ?>
                <table class="overview-table">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th>Contractual DA</th>
                            <th>Intern DA</th>
                            <th>Tour DA (Contract)</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($yearConfigs as $cfg): ?>
                            <tr>
                                <td><strong><?php echo date('F', mktime(0, 0, 0, $cfg['month'], 1)); ?></strong></td>
                                <td>₹<?php echo number_format($cfg['da_rate_contractual'], 2); ?></td>
                                <td>₹<?php echo number_format($cfg['da_rate_intern'], 2); ?></td>
                                <td>₹<?php echo number_format($cfg['tour_da_rate_contractual'], 2); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $cfg['da_enabled'] ? 'enabled' : 'disabled'; ?>">
                                        <?php echo $cfg['da_enabled'] ? 'Enabled' : 'Disabled'; ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="?month=<?php echo $cfg['month']; ?>&year=<?php echo $cfg['year']; ?>" 
                                       class="btn btn-secondary" style="padding: 8px 16px; font-size: 14px;">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    </div>
    
    <?php include 'includes/accountant_scripts.php'; ?>
</body>
</html>
