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
        height: 100vh;
        padding: 0;
        position: fixed;
        left: 0;
        top: 0;
        z-index: 1000;
        box-shadow: 4px 0 15px rgba(0,0,0,0.1);
        overflow-y: auto;
        overflow-x: hidden;
    }

    .sidebar::-webkit-scrollbar {
        width: 6px;
    }

    .sidebar::-webkit-scrollbar-track {
        background: rgba(255, 255, 255, 0.1);
    }

    .sidebar::-webkit-scrollbar-thumb {
        background: rgba(255, 255, 255, 0.3);
        border-radius: 10px;
    }

    .sidebar::-webkit-scrollbar-thumb:hover {
        background: rgba(255, 255, 255, 0.5);
    }

    .sidebar-header {
        padding: 25px 20px;
        border-bottom: 1px solid rgba(255,255,255,0.1);
        text-align: center;
    }

    .sidebar-logo {
        width: 80px;
        height: 80px;
        border-radius: 12px;
        object-fit: contain;
        margin-bottom: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.2);
        background: white;
        padding: 8px;
    }

    .sidebar-icon {
        font-size: 40px;
        margin-bottom: 12px;
        opacity: 0.9;
    }

    .sidebar-header h3 {
        font-size: 18px;
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
        padding: 20px 15px;
        border-top: 1px solid rgba(255,255,255,0.1);
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 10px 15px;
        background: rgba(255,255,255,0.1);
        border-radius: 10px;
        margin-bottom: 10px;
        font-size: 14px;
    }

    .user-info i {
        font-size: 20px;
    }

    .logout-btn {
        display: flex;
        align-items: center;
        gap: 12px;
        color: white;
        text-decoration: none;
        padding: 12px 15px;
        border-radius: 10px;
        transition: all 0.3s ease;
        font-size: 14px;
        font-weight: 500;
        background: rgba(239, 68, 68, 0.3);
    }

    .logout-btn i {
        width: 20px;
        text-align: center;
        font-size: 16px;
    }

    .logout-btn:hover {
        background: rgba(239, 68, 68, 0.5);
        transform: translateX(5px);
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

    /* Notification Bell */
    .notification-bell-container {
        position: relative;
    }

    .notification-bell {
        position: relative;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 45px;
        height: 45px;
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
        border-radius: 50%;
        color: white;
        border: none;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .notification-bell:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    }

    .notification-bell i {
        font-size: 20px;
    }

    .notification-count {
        position: absolute;
        top: -5px;
        right: -5px;
        background: #ef4444;
        color: white;
        font-size: 11px;
        font-weight: 700;
        padding: 3px 7px;
        border-radius: 10px;
        min-width: 20px;
        text-align: center;
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
        animation: pulse-notification 2s infinite;
    }

    @keyframes pulse-notification {
        0%, 100% { 
            transform: scale(1);
            opacity: 1;
        }
        50% { 
            transform: scale(1.1);
            opacity: 0.8;
        }
    }

    /* Notification Dropdown */
    .notification-dropdown {
        position: absolute;
        top: 60px;
        right: 0;
        width: 400px;
        max-height: 500px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        z-index: 1000;
        overflow: hidden;
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

    .notification-dropdown-header {
        padding: 15px 20px;
        border-bottom: 2px solid var(--border);
        display: flex;
        justify-content: space-between;
        align-items: center;
        background: linear-gradient(135deg, #f8fafc, #f1f5f9);
    }

    .notification-dropdown-header h3 {
        font-size: 16px;
        font-weight: 700;
        color: var(--text);
        margin: 0;
    }

    .mark-all-read {
        background: none;
        border: none;
        color: var(--accent);
        font-size: 12px;
        font-weight: 600;
        cursor: pointer;
        padding: 5px 10px;
        border-radius: 5px;
        transition: all 0.3s;
    }

    .mark-all-read:hover {
        background: rgba(102, 126, 234, 0.1);
    }

    .notification-list {
        max-height: 450px;
        overflow-y: auto;
    }

    .notification-list::-webkit-scrollbar {
        width: 6px;
    }

    .notification-list::-webkit-scrollbar-track {
        background: #f1f5f9;
    }

    .notification-list::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }

    .notification-item {
        padding: 15px 20px;
        border-bottom: 1px solid var(--border);
        cursor: pointer;
        transition: all 0.3s;
        background: white;
    }

    .notification-item:hover {
        background: #f8fafc;
    }

    .notification-item.unread {
        background: #eff6ff;
        border-left: 4px solid var(--accent);
    }

    .notification-item-header {
        display: flex;
        align-items: start;
        gap: 12px;
        margin-bottom: 8px;
    }

    .notification-icon {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        flex-shrink: 0;
    }

    .notification-icon.success {
        background: #d1fae5;
        color: #059669;
    }

    .notification-icon.info {
        background: #dbeafe;
        color: #2563eb;
    }

    .notification-item-content {
        flex: 1;
    }

    .notification-item-title {
        font-size: 14px;
        font-weight: 600;
        color: var(--text);
        margin-bottom: 4px;
    }

    .notification-item-message {
        font-size: 13px;
        color: var(--muted);
        line-height: 1.5;
    }

    .notification-item-time {
        font-size: 11px;
        color: var(--muted);
        margin-top: 6px;
        display: flex;
        align-items: center;
        gap: 5px;
    }

    .notification-empty {
        padding: 60px 20px;
        text-align: center;
        color: var(--muted);
    }

    .notification-empty i {
        font-size: 48px;
        margin-bottom: 15px;
        opacity: 0.3;
    }

    .notification-empty p {
        font-size: 14px;
        margin: 0;
    }

    /* Mobile Menu Toggle */
    .mobile-menu-toggle {
        display: none;
        position: fixed;
        top: 20px;
        left: 20px;
        z-index: 1100;
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
        color: white;
        border: none;
        width: 45px;
        height: 45px;
        border-radius: 12px;
        cursor: pointer;
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transition: all 0.3s ease;
    }

    .mobile-menu-toggle:hover {
        transform: scale(1.05);
        box-shadow: 0 6px 16px rgba(0,0,0,0.2);
    }

    .mobile-menu-toggle i {
        font-size: 20px;
    }

    .sidebar-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.5);
        z-index: 999;
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .sidebar-overlay.active {
        opacity: 1;
    }

    /* Tablet Responsive */
    @media (max-width: 1199px) {
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    /* Mobile & Tablet - Sidebar Toggle */
    @media (max-width: 768px) {
        .mobile-menu-toggle {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .sidebar-overlay {
            display: block;
        }

        .sidebar {
            position: fixed;
            left: -260px;
            transition: left 0.3s ease;
            z-index: 1001;
        }

        .sidebar.active {
            left: 0;
        }

        .main-content {
            margin-left: 0;
            padding: 80px 20px 20px 20px;
        }

        .stats-grid {
            grid-template-columns: 1fr;
        }

        .content-header {
            flex-direction: column;
            gap: 15px;
        }

        .content-header h1 {
            font-size: 22px;
        }

        table {
            font-size: 13px;
        }

        .stat-card {
            padding: 20px;
        }
    }

    /* Small Mobile */
    @media (max-width: 480px) {
        .main-content {
            padding: 70px 15px 15px 15px;
        }

        .content-header h1 {
            font-size: 20px;
        }

        table {
            font-size: 12px;
        }
    }
</style>
