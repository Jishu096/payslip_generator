<style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        background: #f5f7fa;
        overflow-x: hidden;
    }

    /* Top Navbar */
    .navbar {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 0 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        height: 70px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        position: fixed;
        width: 100%;
        top: 0;
        z-index: 1000;
    }

    .navbar-brand {
        display: flex;
        align-items: center;
        gap: 15px;
        font-size: 20px;
        font-weight: 600;
    }

    .navbar-brand i {
        font-size: 28px;
    }

    .navbar-toggle {
        font-size: 24px;
        cursor: pointer;
        display: none;
    }

    .navbar-right {
        display: flex;
        align-items: center;
        gap: 25px;
    }

    .user-info {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .user-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(255,255,255,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
    }

    .logout-btn {
        background: rgba(255,255,255,0.2);
        padding: 8px 20px;
        border-radius: 20px;
        color: white;
        text-decoration: none;
        transition: all 0.3s ease;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .logout-btn:hover {
        background: rgba(255,255,255,0.3);
    }

    /* Sidebar */
    .sidebar {
        width: 260px;
        background: #2c3e50;
        position: fixed;
        left: 0;
        top: 70px;
        height: calc(100vh - 70px);
        overflow-y: auto;
        transition: all 0.3s ease;
        box-shadow: 2px 0 10px rgba(0,0,0,0.1);
    }

    .sidebar.collapsed {
        left: -260px;
    }

    .sidebar-menu {
        list-style: none;
        padding: 20px 0;
    }

    .sidebar-menu li {
        margin-bottom: 5px;
    }

    .sidebar-menu a {
        display: flex;
        align-items: center;
        gap: 15px;
        padding: 15px 25px;
        color: #ecf0f1;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .sidebar-menu a:hover,
    .sidebar-menu a.active {
        background: rgba(52, 152, 219, 0.2);
        border-left: 4px solid #3498db;
        padding-left: 21px;
    }

    .sidebar-menu i {
        width: 20px;
        font-size: 18px;
    }

    /* Main Content */
    .main-content {
        margin-left: 260px;
        margin-top: 70px;
        padding: 30px;
        transition: all 0.3s ease;
        min-height: calc(100vh - 70px);
    }

    .main-content.expanded {
        margin-left: 0;
    }

    .page-header {
        margin-bottom: 30px;
    }

    .page-header h1 {
        font-size: 28px;
        color: #2c3e50;
        margin-bottom: 5px;
    }

    .page-header p {
        color: #7f8c8d;
        font-size: 14px;
    }

    .btn {
        display: inline-block;
        padding: 12px 24px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: all 0.3s ease;
    }

    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
    }

    /* Responsive */
    @media (max-width: 768px) {
        .navbar-toggle {
            display: block;
        }

        .sidebar {
            left: -260px;
        }

        .sidebar.active {
            left: 0;
        }

        .main-content {
            margin-left: 0;
        }

        .navbar-brand span {
            display: none;
        }

        .user-info span {
            display: none;
        }
    }
</style>
