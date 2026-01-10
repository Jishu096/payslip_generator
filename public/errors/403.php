<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>403 - Access Forbidden</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 60px 40px;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            text-align: center;
            max-width: 600px;
            animation: slideUp 0.6s ease-out;
        }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .error-icon {
            font-size: 100px;
            color: #ef4444;
            margin-bottom: 20px;
        }
        .error-code {
            font-size: 120px;
            font-weight: 700;
            color: #ef4444;
            margin-bottom: 20px;
        }
        h1 { font-size: 32px; margin-bottom: 15px; color: #1a1f36; }
        p { font-size: 16px; color: #555; margin-bottom: 30px; line-height: 1.6; }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 14px 30px;
            background: #ef4444;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(239, 68, 68, 0.4);
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon"><i class="fas fa-lock"></i></div>
        <div class="error-code">403</div>
        <h1>Access Forbidden</h1>
        <p>You don't have permission to access this resource. Please contact your administrator if you believe this is an error.</p>
        <a href="/payslip_generator/public/auth/login.php" class="btn">
            <i class="fas fa-home"></i> Go to Login
        </a>
    </div>
</body>
</html>
