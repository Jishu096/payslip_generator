<?php
require_once __DIR__ . '/../../app/Config/database.php';

$conn = getDBConnection();

function findToken($conn, $token) {
    $hash = hash('sha256', $token);
    $sql = "SELECT pr.id, pr.user_id, pr.expires_at, pr.used, u.username
            FROM password_resets pr
            JOIN users u ON pr.user_id = u.user_id
            WHERE pr.token_hash = :th AND pr.used = 0 AND pr.expires_at > NOW()
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    $stmt->execute([':th' => $hash]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

$token = $_GET['token'] ?? '';
$tokenValid = false;
$tokenRow = null;

if ($token) {
    $tokenRow = findToken($conn, $token);
    $tokenValid = (bool)$tokenRow;
}

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';
    $newPass = trim($_POST['new_password'] ?? '');
    $confirm = trim($_POST['confirm_password'] ?? '');

    if ($newPass === '' || $confirm === '') {
        $error = 'Please fill all fields.';
    } elseif ($newPass !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (strlen($newPass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } else {
        $row = findToken($conn, $token);
        if (!$row) {
            $error = 'Invalid or expired token.';
        } else {
            $hash = password_hash($newPass, PASSWORD_DEFAULT);
            $conn->prepare("UPDATE users SET password_hash = :ph WHERE user_id = :uid")
                 ->execute([':ph' => $hash, ':uid' => $row['user_id']]);
            $conn->prepare("UPDATE password_resets SET used = 1 WHERE id = :id")
                 ->execute([':id' => $row['id']]);
            $message = 'Password updated successfully. You can now log in.';
            $tokenValid = false;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600&family=Manrope:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0b1221;
            --card: #0f172a;
            --accent: #0ea5e9;
            --accent-2: #22d3ee;
            --text: #e5e7eb;
            --muted: #9ca3af;
            --border: #1f2937;
            --success: #34d399;
            --danger: #f87171;
        }
        body {
            font-family: 'Manrope', sans-serif;
            background: radial-gradient(circle at 20% 20%, rgba(34,211,238,0.08), transparent 25%),
                        radial-gradient(circle at 80% 0%, rgba(14,165,233,0.06), transparent 30%),
                        var(--bg);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 28px;
            width: 100%;
            max-width: 440px;
            box-shadow: 0 24px 50px rgba(0,0,0,0.4);
        }
        h1 {
            font-family: 'Space Grotesk', sans-serif;
            color: var(--text);
            margin-bottom: 8px;
        }
        p.sub {
            color: var(--muted);
            margin-bottom: 18px;
        }
        .form-group { margin-bottom: 16px; }
        label { color: var(--text); font-weight: 600; font-size: 13px; }
        input[type=password] {
            width: 100%;
            padding: 12px;
            border-radius: 10px;
            border: 1px solid var(--border);
            background: rgba(255,255,255,0.03);
            color: var(--text);
            font-size: 14px;
        }
        input[type=password]:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(14,165,233,0.15);
        }
        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 10px;
            font-weight: 700;
            cursor: pointer;
            background: linear-gradient(135deg, var(--accent), var(--accent-2));
            color: #0b1221;
            margin-top: 4px;
        }
        .msg { padding: 12px; border-radius: 10px; margin-bottom: 12px; font-size: 14px; }
        .msg.success { background: rgba(52,211,153,0.12); color: var(--success); border: 1px solid rgba(52,211,153,0.4); }
        .msg.error { background: rgba(248,113,113,0.12); color: var(--danger); border: 1px solid rgba(248,113,113,0.4); }
        .disabled { opacity: 0.6; pointer-events: none; }
        a { color: var(--accent); text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Reset Password</h1>
        <p class="sub">Set a new password for your account.</p>

        <?php if ($message): ?>
            <div class="msg success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?></div>
            <p><a href="/payslip_generator/public/auth/login.php">Return to login</a></p>
        <?php elseif ($error): ?>
            <div class="msg error"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($tokenValid): ?>
            <form method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit" class="btn">Update Password</button>
            </form>
        <?php elseif (!$message && !$error): ?>
            <div class="msg error"><i class="fas la-exclamation-circle"></i> Invalid or expired token. The link expires after 5 minutes—please request a new one.</div>
            <p>
                <a href="/payslip_generator/public/auth/login.php">Return to login</a>
                &nbsp;·&nbsp;
                <a href="/payslip_generator/public/auth/login.php?forgot=1">Send a new reset link</a>
            </p>
        <?php endif; ?>
    </div>
</body>
</html>
