<script>
    // Auto-hide alerts after 5 seconds
    setTimeout(function() {
        const alerts = document.querySelectorAll('.alert:not(#alertBox)');
        alerts.forEach(function(alert) {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.remove();
            }, 500);
        });
    }, 5000);
</script>

<?php
// Session timeout warning script
require_once __DIR__ . '/../../../app/Helpers/SessionHelper.php';
echo SessionHelper::getTimeoutWarningScript();
?>
