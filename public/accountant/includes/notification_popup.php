<!-- Notification Popup System -->
<style>
    .notification-badge {
        background: #ef4444;
        color: white;
        font-size: 9px;
        padding: 2px 6px;
        border-radius: 10px;
        margin-left: 8px;
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    .notification-popup {
        position: fixed;
        top: 20px;
        right: 20px;
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        padding: 20px;
        max-width: 400px;
        z-index: 10000;
        transform: translateX(500px);
        transition: transform 0.5s ease;
    }

    .notification-popup.show {
        transform: translateX(0);
    }

    .notification-popup.success {
        border-left: 4px solid #10b981;
    }

    .notification-popup.info {
        border-left: 4px solid #3b82f6;
    }

    .notification-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 10px;
    }

    .notification-title {
        font-weight: 700;
        font-size: 16px;
        color: #1e293b;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .notification-close {
        background: none;
        border: none;
        color: #64748b;
        cursor: pointer;
        font-size: 20px;
        padding: 0;
        width: 24px;
        height: 24px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .notification-close:hover {
        background: #f1f5f9;
        color: #1e293b;
    }

    .notification-message {
        color: #64748b;
        font-size: 14px;
        line-height: 1.5;
        margin-bottom: 15px;
    }

    .notification-actions {
        display: flex;
        gap: 10px;
    }

    .notification-btn {
        padding: 8px 16px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        font-size: 13px;
        transition: all 0.3s;
    }

    .notification-btn-primary {
        background: linear-gradient(135deg, #667eea, #764ba2);
        color: white;
    }

    .notification-btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }

    .notification-btn-secondary {
        background: #f1f5f9;
        color: #64748b;
    }

    .notification-btn-secondary:hover {
        background: #e2e8f0;
    }
</style>

<div id="notificationContainer"></div>

<script>
    let notificationCheckInterval;
    
    function showNotification(title, message, type = 'info', actions = []) {
        const container = document.getElementById('notificationContainer');
        const id = 'notif-' + Date.now();
        
        let actionsHTML = '';
        if (actions.length > 0) {
            actionsHTML = '<div class="notification-actions">';
            actions.forEach(action => {
                actionsHTML += `<button class="notification-btn notification-btn-${action.style}" onclick="${action.onclick}">${action.label}</button>`;
            });
            actionsHTML += '</div>';
        }
        
        const notifHTML = `
            <div id="${id}" class="notification-popup ${type}">
                <div class="notification-header">
                    <div class="notification-title">
                        <i class="fas fa-${type === 'success' ? 'check-circle' : 'bell'}"></i>
                        ${title}
                    </div>
                    <button class="notification-close" onclick="closeNotification('${id}')">×</button>
                </div>
                <div class="notification-message">${message}</div>
                ${actionsHTML}
            </div>
        `;
        
        container.insertAdjacentHTML('beforeend', notifHTML);
        
        setTimeout(() => {
            document.getElementById(id).classList.add('show');
        }, 100);
        
        // Auto close after 10 seconds if no actions
        if (actions.length === 0) {
            setTimeout(() => {
                closeNotification(id);
            }, 10000);
        }
        
        return id;
    }
    
    function closeNotification(id) {
        const notif = document.getElementById(id);
        if (notif) {
            notif.classList.remove('show');
            setTimeout(() => {
                notif.remove();
            }, 500);
        }
    }
    
    function checkForNewNotifications(showPopup = false) {
        fetch('<?php echo $baseURL ?? "/payslip_generator/public/"; ?>api/get_notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.notifications.length > 0) {
                    let hasAttendanceNotifications = false;
                    let unreadCount = 0;
                    
                    // Update notification dropdown list
                    updateNotificationDropdown(data.notifications);
                    
                    data.notifications.forEach(notif => {
                        if (!notif.is_read) {
                            unreadCount++;
                            
                            if (notif.type === 'attendance_finalized') {
                                hasAttendanceNotifications = true;
                                
                                // Only show popup if explicitly requested (not on initial page load)
                                if (showPopup) {
                                    showNotification(
                                        notif.title,
                                        notif.message,
                                        'success',
                                        [
                                            {
                                                label: 'View Now',
                                                style: 'primary',
                                                onclick: `window.location.href='${notif.link}'; closeNotification(this.closest('.notification-popup').id);`
                                            },
                                            {
                                                label: 'Dismiss',
                                                style: 'secondary',
                                                onclick: `markNotificationRead(${notif.notification_id}); closeNotification(this.closest('.notification-popup').id);`
                                            }
                                        ]
                                    );
                                }
                            }
                        }
                    });
                    
                    // Always show badge if there are unread attendance notifications
                    const badge = document.getElementById('newAttendanceBadge');
                    if (badge && hasAttendanceNotifications) {
                        badge.style.display = 'inline-block';
                    } else if (badge && !hasAttendanceNotifications) {
                        badge.style.display = 'none';
                    }
                    
                    // Update notification count in top bell icon
                    const notificationCount = document.getElementById('notificationCount');
                    if (notificationCount && unreadCount > 0) {
                        notificationCount.textContent = unreadCount;
                        notificationCount.style.display = 'block';
                    } else if (notificationCount) {
                        notificationCount.style.display = 'none';
                    }
                } else {
                    // No notifications - hide badge and show empty state
                    const badge = document.getElementById('newAttendanceBadge');
                    if (badge) {
                        badge.style.display = 'none';
                    }
                    const notificationCount = document.getElementById('notificationCount');
                    if (notificationCount) {
                        notificationCount.style.display = 'none';
                    }
                    updateNotificationDropdown([]);
                }
            })
            .catch(err => console.error('Error checking notifications:', err));
    }
    
    function updateNotificationDropdown(notifications) {
        const notificationList = document.getElementById('notificationList');
        if (!notificationList) return;
        
        if (notifications.length === 0) {
            notificationList.innerHTML = `
                <div class="notification-empty">
                    <i class="fas fa-bell-slash"></i>
                    <p>No new notifications</p>
                </div>
            `;
            return;
        }
        
        let html = '';
        notifications.forEach(notif => {
            const timeAgo = getTimeAgo(notif.created_at);
            const iconClass = notif.type === 'attendance_finalized' ? 'success' : 'info';
            const icon = notif.type === 'attendance_finalized' ? 'fa-calendar-check' : 'fa-bell';
            const unreadClass = !notif.is_read ? 'unread' : '';
            
            html += `
                <div class="notification-item ${unreadClass}" onclick="handleNotificationClick(${notif.notification_id}, '${notif.link}')">
                    <div class="notification-item-header">
                        <div class="notification-icon ${iconClass}">
                            <i class="fas ${icon}"></i>
                        </div>
                        <div class="notification-item-content">
                            <div class="notification-item-title">${notif.title}</div>
                            <div class="notification-item-message">${notif.message}</div>
                            <div class="notification-item-time">
                                <i class="fas fa-clock"></i>
                                ${timeAgo}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        notificationList.innerHTML = html;
    }
    
    function getTimeAgo(timestamp) {
        const now = new Date();
        const notifTime = new Date(timestamp);
        const diffMs = now - notifTime;
        const diffMins = Math.floor(diffMs / 60000);
        
        if (diffMins < 1) return 'Just now';
        if (diffMins < 60) return `${diffMins} minute${diffMins > 1 ? 's' : ''} ago`;
        
        const diffHours = Math.floor(diffMins / 60);
        if (diffHours < 24) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
        
        const diffDays = Math.floor(diffHours / 24);
        if (diffDays < 7) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
        
        return notifTime.toLocaleDateString();
    }
    
    function toggleNotificationDropdown() {
        const dropdown = document.getElementById('notificationDropdown');
        if (dropdown) {
            if (dropdown.style.display === 'none') {
                dropdown.style.display = 'block';
                // Close dropdown when clicking outside
                setTimeout(() => {
                    document.addEventListener('click', closeDropdownOnOutsideClick);
                }, 0);
            } else {
                dropdown.style.display = 'none';
                document.removeEventListener('click', closeDropdownOnOutsideClick);
            }
        }
    }
    
    function closeDropdownOnOutsideClick(event) {
        const dropdown = document.getElementById('notificationDropdown');
        const bellContainer = document.querySelector('.notification-bell-container');
        
        if (dropdown && bellContainer && !bellContainer.contains(event.target)) {
            dropdown.style.display = 'none';
            document.removeEventListener('click', closeDropdownOnOutsideClick);
        }
    }
    
    function handleNotificationClick(notificationId, link) {
        markNotificationRead(notificationId);
        setTimeout(() => {
            window.location.href = link;
        }, 200);
    }
    
    function markAllAsRead() {
        fetch('<?php echo $baseURL ?? "/payslip_generator/public/"; ?>api/mark_notifications_read.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ type: 'all' })
        })
        .then(() => {
            checkForNewNotifications(false);
        });
    }
    
    function markNotificationRead(notificationId) {
        fetch('<?php echo $baseURL ?? "/payslip_generator/public/"; ?>api/mark_notification_read.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ notification_id: notificationId })
        })
        .then(() => {
            // Recheck notifications to update badge
            checkForNewNotifications(false);
        });
    }
    
    // Check for notifications on page load (don't show popup, just badge)
    window.addEventListener('load', function() {
        checkForNewNotifications(false);
        
        // Check every 30 seconds and show popup for new notifications
        notificationCheckInterval = setInterval(() => {
            checkForNewNotifications(true);
        }, 30000);
    });
    
    // Clean up interval on page unload
    window.addEventListener('beforeunload', function() {
        if (notificationCheckInterval) {
            clearInterval(notificationCheckInterval);
        }
    });
</script>
