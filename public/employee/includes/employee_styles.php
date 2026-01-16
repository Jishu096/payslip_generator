<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

    .sidebar {
        width: 260px;
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
        color: white;
        min-height: 100vh;
        padding: 0;
        position: fixed;
        left: 0;
        top: 0;
        z-index: 1000;
        box-shadow: 4px 0 15px rgba(0,0,0,0.1);
    }

    .sidebar-header {
        padding: 25px 20px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        text-align: center;
    }

    .sidebar-header h3 {
        font-size: 20px;
        font-weight: 700;
        margin-bottom: 5px;
    }

    .sidebar-header p {
        font-size: 12px;
        opacity: 0.8;
    }

    .sidebar-menu {
        padding: 20px 15px;
    }

    .sidebar-menu a {
        display: flex;
        align-items: center;
        gap: 12px;
        color: white;
        text-decoration: none;
        padding: 12px 15px;
        margin-bottom: 8px;
        border-radius: 10px;
        transition: all 0.3s ease;
        font-size: 14px;
        font-weight: 500;
    }

    .sidebar-menu a i {
        width: 20px;
        text-align: center;
        font-size: 16px;
    }

    .sidebar-menu a:hover {
        background: rgba(255,255,255,0.15);
        transform: translateX(5px);
    }

    .sidebar-menu a.active {
        background: rgba(255,255,255,0.25);
        font-weight: 600;
    }

    .sidebar-footer {
        position: absolute;
        bottom: 0;
        left: 0;
        right: 0;
        padding: 20px 15px;
        border-top: 1px solid rgba(255,255,255,0.1);
    }

    .sidebar-footer .user-info {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 15px;
        background: rgba(255,255,255,0.1);
        border-radius: 8px;
        margin-bottom: 10px;
        font-size: 14px;
    }

    .sidebar-footer .user-info i {
        font-size: 18px;
    }

    .sidebar-footer .logout-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        width: 100%;
        padding: 12px;
        background: rgba(239, 68, 68, 0.9);
        color: white;
        text-decoration: none;
        border-radius: 8px;
        font-weight: 600;
        font-size: 14px;
        transition: all 0.3s;
    }

    .sidebar-footer .logout-btn:hover {
        background: rgba(239, 68, 68, 1);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
    }

    .main-content {
        margin-left: 260px;
        padding: 30px;
        min-height: 100vh;
    }

    .content-header {
        background: white;
        padding: 25px 30px;
        border-radius: 15px;
        margin-bottom: 30px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .content-header h1 {
        font-size: 28px;
        font-weight: 700;
        color: var(--text);
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .content-header h1 i {
        color: var(--accent);
    }

    .content-header p {
        color: var(--muted);
        font-size: 14px;
        margin-top: 5px;
    }

    @media (max-width: 768px) {
        .sidebar {
            width: 100%;
            min-height: auto;
            position: relative;
        }

        .main-content {
            margin-left: 0;
            padding: 20px;
        }
    }
</style>
