<?php
/**
 * Public Footer Partial
 * Shared across all public-facing pages
 */

if (!defined('ABSPATH')) {
    exit;
}
?>
<footer class="site-footer">
    <div class="footer-inner">
        <p>&copy; <?php echo date('Y'); ?> <?php echo esc_html(oversee_get_portal_title()); ?>. Powered by <a href="https://overseecrm.com" target="_blank" rel="noopener">OverseeCRM</a></p>
    </div>
</footer>
