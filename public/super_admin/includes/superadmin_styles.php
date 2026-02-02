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
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: var(--bg);
        color: var(--text);
    }

    /* Top Navigation Bar */
    .top-navbar {
        position: fixed;
        top: 0;
        left: 260px;
        right: 0;
        height: 60px;
        background: white;
        border-bottom: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 25px;
        z-index: 999;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }

    .top-navbar-left {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .top-navbar-left .mobile-menu-toggle {
        display: none;
        background: none;
        border: none;
        font-size: 20px;
        color: var(--text);
        cursor: pointer;
    }

    .page-title {
        font-size: 18px;
        font-weight: 600;
        color: var(--text);
        text-transform: capitalize;
    }

    .top-navbar-right {
        display: flex;
        align-items: center;
        gap: 20px;
    }

    /* Notification Bell */
    .notification-wrapper {
        position: relative;
    }

    .notification-btn {
        background: none;
        border: none;
        font-size: 20px;
        color: var(--muted);
        cursor: pointer;
        padding: 8px;
        border-radius: 10px;
        transition: all 0.3s ease;
        position: relative;
    }

    .notification-btn:hover {
        background: #f1f5f9;
        color: var(--accent);
    }

    .notification-badge {
        position: absolute;
        top: 2px;
        right: 2px;
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
        font-size: 10px;
        font-weight: 700;
        padding: 2px 6px;
        border-radius: 10px;
        min-width: 18px;
        text-align: center;
    }

    .notification-dropdown {
        position: absolute;
        top: 100%;
        right: 0;
        width: 360px;
        background: white;
        border-radius: 16px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.15);
        opacity: 0;
        visibility: hidden;
        transform: translateY(10px);
        transition: all 0.3s ease;
        margin-top: 10px;
        overflow: hidden;
    }

    .notification-dropdown.show {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .notification-header {
        padding: 16px 20px;
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
        color: white;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .notification-header h4 {
        font-size: 14px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .notification-count {
        font-size: 12px;
        opacity: 0.9;
    }

    .notification-list {
        max-height: 320px;
        overflow-y: auto;
    }

    .notification-item {
        display: flex;
        gap: 12px;
        padding: 14px 20px;
        border-bottom: 1px solid var(--border);
        transition: background 0.3s ease;
    }

    .notification-item:hover {
        background: #f8fafc;
    }

    .notification-item:last-child {
        border-bottom: none;
    }

    .notification-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .notification-icon.warning {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.15), rgba(217, 119, 6, 0.15));
        color: #f59e0b;
    }

    .notification-icon.danger {
        background: linear-gradient(135deg, rgba(239, 68, 68, 0.15), rgba(220, 38, 38, 0.15));
        color: #ef4444;
    }

    .notification-icon.success {
        background: linear-gradient(135deg, rgba(16, 185, 129, 0.15), rgba(5, 150, 105, 0.15));
        color: #10b981;
    }

    .notification-icon.info {
        background: linear-gradient(135deg, rgba(59, 130, 246, 0.15), rgba(37, 99, 235, 0.15));
        color: #3b82f6;
    }

    .notification-content {
        flex: 1;
    }

    .notification-content h5 {
        font-size: 13px;
        font-weight: 600;
        color: var(--text);
        margin-bottom: 4px;
    }

    .notification-content p {
        font-size: 12px;
        color: var(--muted);
        margin-bottom: 4px;
        line-height: 1.4;
    }

    .notification-time {
        font-size: 11px;
        color: var(--accent);
        font-weight: 500;
    }

    .notification-footer {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 14px;
        background: #f8fafc;
        color: var(--accent);
        text-decoration: none;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .notification-footer:hover {
        background: #f1f5f9;
        color: var(--accent-2);
    }

    /* User Menu */
    .user-menu {
        display: flex;
        align-items: center;
        gap: 8px;
        padding: 8px 16px;
        background: linear-gradient(135deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
        border-radius: 10px;
    }

    .user-greeting {
        font-size: 12px;
        color: var(--muted);
    }

    .user-name {
        font-size: 14px;
        font-weight: 600;
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

    /* Main Content */
    .main-content {
        margin-left: 260px;
        padding: 30px;
        padding-top: 90px; /* Account for top navbar */
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

    /* Cards */
    .card {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        margin-bottom: 25px;
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #f0f0f0;
    }

    .card-title {
        font-size: 20px;
        color: #2d3748;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    /* Stats Grid */
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 25px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        border-left: 4px solid var(--accent);
        transition: all 0.3s ease;
    }
    
    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0,0,0,0.12);
    }
    
    .stat-card.status { border-left-color: #10b981; }
    .stat-card.users { border-left-color: #3b82f6; }
    .stat-card.alerts { border-left-color: #ef4444; }
    
    .stat-label {
        font-size: 12px;
        color: var(--muted);
        font-weight: 600;
        text-transform: uppercase;
        margin-bottom: 10px;
        letter-spacing: 0.5px;
    }
    
    .stat-value {
        font-size: 32px;
        font-weight: 700;
        background: linear-gradient(135deg, var(--accent), var(--accent-2));
        background-clip: text;
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    
    .stat-card.status .stat-value {
        background: linear-gradient(135deg, #10b981, #059669);
        background-clip: text;
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    
    .stat-card.users .stat-value {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
        background-clip: text;
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    
    .stat-card.alerts .stat-value {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        background-clip: text;
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
    }
    
    .stat-desc {
        font-size: 13px;
        color: var(--muted);
        margin-top: 8px;
    }

    /* Buttons */
    .btn {
        padding: 12px 25px;
        border: none;
        border-radius: 8px;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        text-decoration: none;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102,126,234,0.4);
    }

    .btn-success {
        background: #48bb78;
        color: white;
    }

    .btn-danger {
        background: #f56565;
        color: white;
    }

    .btn-warning {
        background: #ed8936;
        color: white;
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
        .top-navbar {
            left: 0;
            padding: 0 15px;
        }

        .top-navbar-left .mobile-menu-toggle {
            display: flex;
        }

        .page-title {
            font-size: 15px;
        }

        .notification-dropdown {
            width: 300px;
            right: -50px;
        }

        .user-menu {
            display: none;
        }

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

        .sidebar-footer {
            position: relative;
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
