<?php
/**
 * Public Header Partial
 * Shared across all public-facing pages
 */

if (!defined('ABSPATH')) {
    exit;
}

$portal_title = oversee_get_portal_title();
$current_path = $_SERVER['REQUEST_URI'] ?? '';

// Determine active nav item
$is_kb = strpos($current_path, '/support/kb') !== false || $current_path === '/support/' || $current_path === '/support';
$is_tickets = strpos($current_path, '/support/tickets') !== false || strpos($current_path, '/support/ticket/') !== false;
$is_zoom = strpos($current_path, '/support/zoom') !== false;
$is_submit = strpos($current_path, '/support/submit') !== false;
?>
<header class="site-header">
    <div class="header-inner">
        <a href="<?php echo esc_url(oversee_support_url()); ?>" class="header-logo">
            <i class="fa-solid fa-headset"></i>
            <span><?php echo esc_html($portal_title); ?></span>
        </a>
        
        <nav class="header-nav">
            <a href="<?php echo esc_url(oversee_kb_url()); ?>" class="<?php echo $is_kb ? 'active' : ''; ?>">
                <i class="fa-solid fa-book"></i>
                <span>Knowledge Base</span>
            </a>
            <a href="<?php echo esc_url(oversee_support_url('tickets')); ?>" class="<?php echo $is_tickets ? 'active' : ''; ?>">
                <i class="fa-solid fa-ticket"></i>
                <span>My Tickets</span>
            </a>
            <a href="<?php echo esc_url(oversee_support_url('zoom')); ?>" class="<?php echo $is_zoom ? 'active' : ''; ?>">
                <i class="fa-solid fa-video"></i>
                <span>Live Support</span>
            </a>
        </nav>
        
        <div class="header-actions">
            <a href="<?php echo esc_url(oversee_support_url('submit')); ?>" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-plus"></i>
                <span>Submit Ticket</span>
            </a>
        </div>
        
        <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
            <i class="fa-solid fa-bars"></i>
        </button>
    </div>
    
    <!-- Mobile Menu -->
    <div class="mobile-menu" id="mobileMenu">
        <a href="<?php echo esc_url(oversee_kb_url()); ?>" class="<?php echo $is_kb ? 'active' : ''; ?>">
            <i class="fa-solid fa-book"></i> Knowledge Base
        </a>
        <a href="<?php echo esc_url(oversee_support_url('tickets')); ?>" class="<?php echo $is_tickets ? 'active' : ''; ?>">
            <i class="fa-solid fa-ticket"></i> My Tickets
        </a>
        <a href="<?php echo esc_url(oversee_support_url('zoom')); ?>" class="<?php echo $is_zoom ? 'active' : ''; ?>">
            <i class="fa-solid fa-video"></i> Live Support
        </a>
        <a href="<?php echo esc_url(oversee_support_url('submit')); ?>" class="highlight">
            <i class="fa-solid fa-plus"></i> Submit Ticket
        </a>
    </div>
</header>

<script>
function toggleMobileMenu() {
    document.getElementById('mobileMenu').classList.toggle('active');
}
</script>
