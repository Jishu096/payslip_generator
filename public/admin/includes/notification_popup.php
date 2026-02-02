<!-- Admin Notification System -->
<script>
    const baseURL = '<?php echo $baseURL ?? "/payslip_generator/public/"; ?>';
    let notificationCheckInterval;
    
    function checkForNewNotifications(showPopup = false) {
        fetch(baseURL + 'api/get_notifications.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.notifications.length > 0) {
                    let unreadCount = 0;
                    updateNotificationDropdown(data.notifications);
                    
                    data.notifications.forEach(notif => {
                        if (!notif.is_read) {
                            unreadCount++;
                        }
                    });
                    
                    const notificationCount = document.getElementById('notificationCount');
                    if (notificationCount && unreadCount > 0) {
                        notificationCount.textContent = unreadCount;
                        notificationCount.style.display = 'block';
                    } else if (notificationCount) {
                        notificationCount.style.display = 'none';
                    }
                } else {
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
            
            // Determine icon and color based on notification type
            let iconClass = 'info';
            let icon = 'fa-bell';
            
            if (notif.type === 'finalization_window') {
                iconClass = 'info';
                icon = 'fa-calendar-check';
            } else if (notif.type && notif.type.startsWith('holiday_reminder_')) {
                // Holiday notification - check if closed or restricted from title
                if (notif.title && notif.title.includes('🔵')) {
                    iconClass = 'holiday-closed';
                    icon = 'fa-building-lock';
                } else {
                    iconClass = 'holiday-restricted';
                    icon = 'fa-calendar-day';
                }
            }
            
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
    
    function markNotificationRead(notificationId) {
        fetch(baseURL + 'api/mark_notification_read.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ notification_id: notificationId })
        })
        .then(() => {
            checkForNewNotifications(false);
        });
    }
    
    function markAllAsRead() {
        fetch(baseURL + 'api/mark_notifications_read.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ type: 'all' })
        })
        .then(() => {
            checkForNewNotifications(false);
        });
    }
    
    window.addEventListener('load', function() {
        checkForNewNotifications(false);
        notificationCheckInterval = setInterval(() => {
            checkForNewNotifications(false);
        }, 30000);
    });
    
    window.addEventListener('beforeunload', function() {
        if (notificationCheckInterval) {
            clearInterval(notificationCheckInterval);
        }
    });
</script>
