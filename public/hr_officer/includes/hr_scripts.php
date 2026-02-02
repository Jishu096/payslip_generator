<script>
    // Utility functions for HR Officer portal
    document.addEventListener('DOMContentLoaded', function() {
        console.log('HR Officer Portal loaded');
    });
</script>

<?php
// Session timeout warning script
require_once __DIR__ . '/../../../app/Helpers/SessionHelper.php';
echo SessionHelper::getTimeoutWarningScript();
?>
