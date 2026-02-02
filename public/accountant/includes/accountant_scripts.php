<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function() {
        // Add smooth scrolling
        $('a[href^="#"]').on('click', function(e) {
            e.preventDefault();
            const target = $(this.hash);
            if (target.length) {
                $('html, body').animate({
                    scrollTop: target.offset().top - 20
                }, 800);
            }
        });

        // Add fade-in animation to cards
        $('.stat-card, .data-card').each(function(index) {
            $(this).css({
                'opacity': '0',
                'transform': 'translateY(20px)'
            });
            $(this).delay(index * 100).animate({
                'opacity': '1'
            }, 600, function() {
                $(this).css('transform', 'translateY(0)');
            });
        });
    });
</script>

<?php
// Session timeout warning script
require_once __DIR__ . '/../../../app/Helpers/SessionHelper.php';
echo SessionHelper::getTimeoutWarningScript();
?>
