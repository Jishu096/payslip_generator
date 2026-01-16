<?php
session_start();

// Get user roles
$userRoles = $_SESSION['all_roles'] ?? [$_SESSION['role'] ?? null];

// Check if user has accountant role
$hasAccountantRole = in_array('accountant', $userRoles);

if (!$hasAccountantRole) {
    header("Location: ../auth/login.php");
    exit;
}

$username = $_SESSION['username'] ?? 'User';
$baseURL = "/payslip_generator/public/";

// Database connection
require_once __DIR__ . '/../../app/Config/database.php';
$db = new Database();
$conn = $db->connect();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_officials'])) {
    try {
        $conn->beginTransaction();
        
        foreach ($_POST['officials'] as $id => $data) {
            $stmt = $conn->prepare("
                UPDATE attendance_officials 
                SET official_name = ?, updated_by = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['name'],
                $_SESSION['user_id'],
                $id
            ]);
        }
        
        $conn->commit();
        $success_message = "Official names updated successfully!";
    } catch (Exception $e) {
        $conn->rollBack();
        $error_message = "Error updating officials: " . $e->getMessage();
    }
}

// Fetch current officials
$stmt = $conn->query("
    SELECT id, position_key, position_title, official_name, display_order
    FROM attendance_officials
    WHERE is_active = 1
    ORDER BY display_order
");
$officials = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Statement Officials - Accountant Portal</title>
    <?php include 'includes/accountant_styles.php'; ?>
    <style>
        .officials-container {
            max-width: 900px;
            margin: 2rem auto;
            padding: 2rem;
        }
        
        .officials-card {
            background: var(--card);
            border-radius: 15px;
            padding: 2rem;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .card-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--accent);
        }
        
        .card-header i {
            font-size: 2rem;
            color: var(--accent);
        }
        
        .card-header h2 {
            font-size: 1.5rem;
            color: var(--text);
            margin: 0;
        }
        
        .info-box {
            background: linear-gradient(135deg, #667eea20, #764ba220);
            border-left: 4px solid var(--accent);
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 2rem;
        }
        
        .info-box p {
            margin: 0;
            color: var(--text);
            line-height: 1.6;
        }
        
        .official-item {
            background: #f8fafc;
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            border: 2px solid var(--border);
            transition: all 0.3s ease;
        }
        
        .official-item:hover {
            border-color: var(--accent);
            box-shadow: 0 3px 10px rgba(102, 126, 234, 0.1);
        }
        
        .official-row {
            display: grid;
            grid-template-columns: 50px 250px 1fr;
            gap: 1.5rem;
            align-items: center;
        }
        
        .position-number {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .position-title {
            font-weight: 600;
            color: var(--text);
            font-size: 1rem;
        }
        
        .name-input-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .name-input {
            flex: 1;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .name-input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .input-icon {
            color: var(--muted);
        }
        
        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn {
            padding: 0.875rem 2rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: white;
            flex: 1;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: var(--border);
            color: var(--text);
        }
        
        .btn-secondary:hover {
            background: var(--muted);
            color: white;
        }
        
        .alert {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background: #10b98120;
            border-left: 4px solid #10b981;
            color: #065f46;
        }
        
        .alert-error {
            background: #ef444420;
            border-left: 4px solid #ef4444;
            color: #991b1b;
        }
        
        .preview-section {
            margin-top: 2rem;
            padding: 1.5rem;
            background: #f8fafc;
            border-radius: 10px;
            border: 2px dashed var(--accent);
        }
        
        .preview-title {
            font-weight: 600;
            color: var(--text);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .preview-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1rem;
            text-align: center;
        }
        
        .preview-item {
            padding: 1rem;
            background: white;
            border-radius: 8px;
            border: 1px solid var(--border);
        }
        
        .preview-name {
            font-weight: 600;
            color: var(--text);
            margin-bottom: 0.25rem;
        }
        
        .preview-title-text {
            font-size: 0.875rem;
            color: var(--muted);
        }
    </style>
</head>
<body>
    <?php include 'includes/accountant_navbar.php'; ?>

    <div class="main-content">
        <div class="officials-container">
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?= $success_message ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= $error_message ?>
                </div>
            <?php endif; ?>

            <div class="officials-card">
                <div class="card-header">
                    <i class="fas fa-user-tie"></i>
                    <h2>Manage Statement Officials</h2>
                </div>

                <div class="info-box">
                    <p><i class="fas fa-info-circle"></i> These names will appear at the bottom of all generated Attendance Statement Excel files. Update them when official positions change.</p>
                </div>

                <form method="POST" action="">
                    <?php foreach ($officials as $official): ?>
                        <div class="official-item">
                            <div class="official-row">
                                <div class="position-number">
                                    <?= $official['display_order'] ?>
                                </div>
                                <div class="position-title">
                                    <?= htmlspecialchars($official['position_title']) ?>
                                </div>
                                <div class="name-input-group">
                                    <i class="fas fa-user input-icon"></i>
                                    <input 
                                        type="text" 
                                        name="officials[<?= $official['id'] ?>][name]" 
                                        value="<?= htmlspecialchars($official['official_name']) ?>"
                                        class="name-input"
                                        placeholder="Enter official name"
                                        required
                                    >
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <div class="preview-section">
                        <div class="preview-title">
                            <i class="fas fa-eye"></i>
                            Preview: How it appears in Excel
                        </div>
                        <div class="preview-grid">
                            <?php foreach ($officials as $official): ?>
                                <div class="preview-item">
                                    <div class="preview-name"><?= htmlspecialchars($official['official_name']) ?></div>
                                    <div class="preview-title-text"><?= htmlspecialchars($official['position_title']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="button-group">
                        <button type="submit" name="update_officials" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Save Changes
                        </button>
                        <a href="accountant_dashboard.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            Back to Dashboard
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include 'includes/accountant_scripts.php'; ?>
</body>
</html>
