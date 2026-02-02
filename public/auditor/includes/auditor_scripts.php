<!-- Auditor Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    // Common auditor scripts
    console.log('Auditor scripts loaded');
</script>

<?php
// Session timeout warning script
require_once __DIR__ . '/../../../app/Helpers/SessionHelper.php';
echo SessionHelper::getTimeoutWarningScript();
?>
