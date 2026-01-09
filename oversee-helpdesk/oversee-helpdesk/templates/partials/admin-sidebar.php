<?php
/**
 * Admin Sidebar Partial - With Branding
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
$agent = Oversee_Auth::get_current_agent();
$is_admin = current_user_can('oversee_manage_settings');
$is_online = $agent && $agent->is_online;

// Get branding
$branding = Oversee_Branding::get_for_context('admin');
$company_name = $branding['company_name'] ?: 'Support';
$logo_url = $branding['logo_url'];
$logo_width = intval($branding['logo_width'] ?: 120);

// Get initials
$name = $current_user->display_name ?: $current_user->user_email;
$initials = '';
$parts = explode(' ', $name);
foreach ($parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);

// Determine current page
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
$current_page = '';
if (strpos($request_uri, '/admin/tickets') !== false) {
    $current_page = 'tickets';
} elseif (strpos($request_uri, '/admin/articles') !== false || strpos($request_uri, '/admin/article') !== false) {
    $current_page = 'articles';
} elseif (strpos($request_uri, '/admin/settings') !== false) {
    $current_page = 'settings';
} elseif (strpos($request_uri, '/admin/profile') !== false) {
    $current_page = 'profile';
} elseif (strpos($request_uri, '/admin') !== false && strpos($request_uri, '/admin/login') === false) {
    $current_page = 'dashboard';
}

// Get open ticket count
global $wpdb;
$ticket_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}oversee_tickets WHERE status IN ('new', 'open')");
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-header">
        <?php if ($logo_url): ?>
            <a href="<?php echo esc_url(oversee_admin_url()); ?>" class="sidebar-logo-link">
                <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($company_name); ?>" class="sidebar-logo" style="max-width:<?php echo $logo_width; ?>px;max-height:40px;">
            </a>
        <?php else: ?>
            <h1><i class="fa-solid fa-headset"></i> <?php echo esc_html($company_name); ?></h1>
        <?php endif; ?>
    </div>
    
    <nav class="sidebar-nav">
        <div class="nav-section">
            <div class="nav-section-title">Main</div>
            <a href="<?php echo esc_url(oversee_admin_url()); ?>" class="nav-item <?php echo $current_page === 'dashboard' ? 'active' : ''; ?>">
                <i class="fa-solid fa-chart-line"></i> Dashboard
            </a>
            <a href="<?php echo esc_url(oversee_admin_url('tickets')); ?>" class="nav-item <?php echo $current_page === 'tickets' ? 'active' : ''; ?>">
                <i class="fa-solid fa-ticket"></i> Tickets
                <span class="badge<?php echo $ticket_count > 0 ? '' : ' hidden'; ?>" id="ticketBadge"><?php echo intval($ticket_count); ?></span>
            </a>
        </div>
        
        <?php if ($is_admin): ?>
        <div class="nav-section">
            <div class="nav-section-title">Manage</div>
            <a href="<?php echo esc_url(oversee_admin_url('articles')); ?>" class="nav-item <?php echo $current_page === 'articles' ? 'active' : ''; ?>">
                <i class="fa-solid fa-book"></i> KB Articles
            </a>
            <a href="<?php echo esc_url(oversee_admin_url('settings')); ?>" class="nav-item <?php echo $current_page === 'settings' ? 'active' : ''; ?>">
                <i class="fa-solid fa-gear"></i> Settings
            </a>
        </div>
        <?php endif; ?>
        
        <div class="nav-section">
            <div class="nav-section-title">Quick Links</div>
            <a href="<?php echo esc_url(oversee_kb_url()); ?>" class="nav-item" target="_blank">
                <i class="fa-solid fa-globe"></i> Knowledge Base
                <i class="fa-solid fa-arrow-up-right-from-square external-icon"></i>
            </a>
        </div>
    </nav>
    
    <div class="sidebar-user">
        <div class="status-toggle-bar">
            <span class="status-label"><?php echo $is_online ? 'Online' : 'Offline'; ?></span>
            <label class="quick-toggle">
                <input type="checkbox" id="quickStatusToggle" <?php echo $is_online ? 'checked' : ''; ?> onchange="toggleOnlineStatus(this)">
                <span class="quick-toggle-slider"></span>
            </label>
        </div>
        <a href="<?php echo esc_url(oversee_admin_url('profile')); ?>" class="user-info <?php echo $current_page === 'profile' ? 'active' : ''; ?>">
            <div class="user-avatar">
                <?php echo esc_html($initials); ?>
                <span class="status-dot <?php echo $is_online ? '' : 'offline'; ?>" id="userStatusDot"></span>
            </div>
            <div class="user-details">
                <div class="name"><?php echo esc_html($name); ?></div>
                <div class="role"><?php echo $is_admin ? 'Administrator' : 'Agent'; ?></div>
            </div>
            <i class="fa-solid fa-chevron-right user-menu-btn"></i>
        </a>
    </div>
</aside>

<div class="sidebar-backdrop" id="sidebarBackdrop" onclick="toggleMobileSidebar()"></div>

<script>
function toggleMobileSidebar() {
    document.getElementById('sidebar').classList.toggle('mobile-open');
    document.getElementById('sidebarBackdrop').classList.toggle('active');
}

async function toggleOnlineStatus(checkbox) {
    const isOnline = checkbox.checked;
    const statusLabel = document.querySelector('.status-label');
    const statusDot = document.getElementById('userStatusDot');
    
    try {
        const response = await fetch('<?php echo esc_url(rest_url('oversee/v1/agent/status')); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>'
            },
            body: JSON.stringify({ is_online: isOnline })
        });
        
        if (response.ok) {
            statusLabel.textContent = isOnline ? 'Online' : 'Offline';
            statusDot.classList.toggle('offline', !isOnline);
            
            // Sync with profile page toggle if exists
            const profileToggle = document.getElementById('statusBtn');
            if (profileToggle) {
                profileToggle.className = 'status-toggle-btn ' + (isOnline ? 'online' : 'offline');
                const statusText = document.getElementById('statusText');
                if (statusText) statusText.textContent = isOnline ? 'Online' : 'Offline';
            }
        }
    } catch (e) {
        checkbox.checked = !isOnline; // Revert on error
    }
}
</script>
