<script>
    // Sidebar Toggle
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const sidebarToggle = document.getElementById('sidebarToggle');

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        });
    }

    // Close sidebar on mobile when clicking outside
    document.addEventListener('click', function(event) {
        if (window.innerWidth <= 768) {
            if (sidebar && !sidebar.contains(event.target) && sidebarToggle && !sidebarToggle.contains(event.target)) {
                sidebar.classList.remove('active');
            }
        }
    });
</script>

<?php
// Session timeout warning script
require_once __DIR__ . '/../../../app/Helpers/SessionHelper.php';
echo SessionHelper::getTimeoutWarningScript();
?>
