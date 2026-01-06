<?php
/**
 * Admin My Profile Page
 * Matches reference design with all features
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!is_user_logged_in() || !current_user_can('oversee_view_dashboard')) {
    wp_safe_redirect(oversee_admin_url('login'));
    exit;
}

$current_user = wp_get_current_user();
$agent = Oversee_Auth::get_current_agent();
$is_admin = current_user_can('oversee_manage_settings');
$is_online = $agent && $agent->is_online;

// Get user meta
$calendar_link = get_user_meta($current_user->ID, 'oversee_calendar_link', true);
$zoom_link = get_user_meta($current_user->ID, 'oversee_zoom_link', true);

// Get initials
$name = $current_user->display_name ?: $current_user->user_email;
$initials = '';
$parts = explode(' ', $name);
foreach ($parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo esc_html(oversee_get_portal_title()); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo esc_url(OVERSEE_PLUGIN_URL . 'assets/css/admin.css?v=2.0.0'); ?>">
</head>
<body class="admin-body">
    <?php include OVERSEE_TEMPLATES_PATH . '/partials/admin-sidebar.php'; ?>
    
    <main class="main">
        <div class="topbar">
            <button class="hamburger-btn" onclick="toggleMobileSidebar()"><i class="fa-solid fa-bars"></i></button>
            <h2>My Profile</h2>
            <div class="topbar-right">
                <a href="<?php echo esc_url(oversee_admin_url('logout')); ?>" class="btn btn-secondary">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>
        
        <div class="profile-content">
            <!-- Profile Tabs -->
            <div class="profile-tabs">
                <button class="profile-tab active" onclick="showTab('profile')">Profile</button>
                <button class="profile-tab" onclick="showTab('notifications')">Notifications</button>
            </div>
            
            <!-- Profile Tab -->
            <div id="tab-profile" class="tab-content active">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3>Profile Information</h3>
                            <p>Update your account details and meeting links</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Profile Header -->
                        <div class="profile-header">
                            <div class="profile-avatar">
                                <?php echo esc_html($initials); ?>
                                <span class="status <?php echo $is_online ? 'online' : 'offline'; ?>" id="avatarStatus"></span>
                            </div>
                            <div class="profile-info">
                                <h4><?php echo esc_html($name); ?></h4>
                                <p><?php echo esc_html($current_user->user_email); ?></p>
                            </div>
                            <div class="profile-status">
                                <button class="status-toggle-btn <?php echo $is_online ? 'online' : 'offline'; ?>" id="statusBtn" onclick="toggleStatus()">
                                    <span class="dot"></span>
                                    <span id="statusText"><?php echo $is_online ? 'Online' : 'Offline'; ?></span>
                                </button>
                            </div>
                        </div>
                        
                        <form id="profileForm" onsubmit="saveProfile(event)">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Display Name</label>
                                    <input type="text" id="displayName" value="<?php echo esc_attr($current_user->display_name); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Email Address</label>
                                    <input type="email" id="userEmail" value="<?php echo esc_attr($current_user->user_email); ?>" disabled>
                                    <p class="form-help">Email cannot be changed here</p>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label>Calendar Link (Calendly, Cal.com, etc.)</label>
                                <input type="url" id="calendarLink" value="<?php echo esc_attr($calendar_link); ?>" placeholder="https://calendly.com/your-name">
                                
                                <?php if ($calendar_link): ?>
                                <div class="link-preview calendar">
                                    <i class="fa-solid fa-calendar"></i>
                                    <div class="link-preview-info">
                                        <strong>Calendar Link</strong>
                                        <span id="calendarDisplay"><?php echo esc_html(str_replace('https://', '', $calendar_link)); ?></span>
                                    </div>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="testLink('calendar')">Test</button>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="form-group">
                                <label>Zoom Meeting Link</label>
                                <input type="url" id="zoomLink" value="<?php echo esc_attr($zoom_link); ?>" placeholder="https://zoom.us/j/your-meeting-id">
                                
                                <?php if ($zoom_link): ?>
                                <div class="link-preview zoom">
                                    <i class="fa-solid fa-video"></i>
                                    <div class="link-preview-info">
                                        <strong>Zoom Link</strong>
                                        <span id="zoomDisplay"><?php echo esc_html(str_replace('https://', '', $zoom_link)); ?></span>
                                    </div>
                                    <button type="button" class="btn btn-secondary btn-sm" onclick="testLink('zoom')">Test</button>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="feature-box">
                                <h4><i class="fa-solid fa-lightbulb"></i> Quick Insert Links</h4>
                                <p>Your calendar and Zoom links can be quickly inserted when replying to tickets using the toolbar buttons. Customers will receive a formatted message with your booking link.</p>
                            </div>
                            
                            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Changes</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Notifications Tab -->
            <div id="tab-notifications" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3>Push Notifications</h3>
                            <p>Get notified in your browser</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="push-status granted" id="pushStatus">
                            <i class="fa-solid fa-circle-check"></i>
                            <span><strong>Push notifications enabled</strong> - You'll receive notifications in this browser</span>
                        </div>
                        
                        <div class="notification-item">
                            <div class="notification-info">
                                <h5>New ticket assigned</h5>
                                <p>Notify when a ticket is assigned to you</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" checked id="notifyAssigned">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="notification-item">
                            <div class="notification-info">
                                <h5>Customer reply</h5>
                                <p>Notify when a customer replies to your ticket</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" checked id="notifyReply">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="notification-item">
                            <div class="notification-info">
                                <h5>Mentioned in a note</h5>
                                <p>Notify when someone mentions you</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" id="notifyMention">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="notification-item">
                            <div class="notification-info">
                                <h5>High priority tickets</h5>
                                <p>Notify for any high priority ticket</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" checked id="notifyHighPriority">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <button class="btn btn-secondary" onclick="testPush()"><i class="fa-solid fa-bell"></i> Send Test Notification</button>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3>Email Notifications</h3>
                            <p>Notifications sent to your email</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="notification-item">
                            <div class="notification-info">
                                <h5>Daily summary</h5>
                                <p>Receive a daily email with your ticket stats</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" id="emailDaily">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="notification-item">
                            <div class="notification-info">
                                <h5>Weekly report</h5>
                                <p>Receive a weekly performance report</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" checked id="emailWeekly">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <div class="toast" id="toast"><i class="fa-solid fa-check-circle"></i><span id="toastMessage"></span></div>
    
    <script>
        const WP_NONCE = '<?php echo wp_create_nonce('wp_rest'); ?>';
        const REST_URL = '<?php echo esc_url(rest_url('oversee/v1')); ?>';
        let isOnline = <?php echo $is_online ? 'true' : 'false'; ?>;
        
        function showTab(tab) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.profile-tab').forEach(t => t.classList.remove('active'));
            document.getElementById('tab-' + tab).classList.add('active');
            event.target.classList.add('active');
        }
        
        async function toggleStatus() {
            isOnline = !isOnline;
            const btn = document.getElementById('statusBtn');
            const text = document.getElementById('statusText');
            const avatar = document.getElementById('avatarStatus');
            
            btn.className = isOnline ? 'status-toggle-btn online' : 'status-toggle-btn offline';
            avatar.className = isOnline ? 'status online' : 'status offline';
            text.textContent = isOnline ? 'Online' : 'Offline';
            
            try {
                await fetch(REST_URL + '/agents/status', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': WP_NONCE
                    },
                    body: JSON.stringify({ is_online: isOnline })
                });
                showToast(isOnline ? 'You are now online' : 'You are now offline');
            } catch (e) {
                showToast('Failed to update status', true);
            }
        }
        
        async function saveProfile(event) {
            event.preventDefault();
            
            const data = {
                display_name: document.getElementById('displayName').value,
                calendar_link: document.getElementById('calendarLink').value,
                zoom_link: document.getElementById('zoomLink').value
            };
            
            try {
                await fetch(REST_URL + '/agents/profile', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': WP_NONCE
                    },
                    body: JSON.stringify(data)
                });
                showToast('Profile saved successfully!');
            } catch (e) {
                showToast('Failed to save profile', true);
            }
        }
        
        function testLink(type) {
            const link = type === 'calendar' 
                ? document.getElementById('calendarLink').value 
                : document.getElementById('zoomLink').value;
            if (link) window.open(link, '_blank');
        }
        
        function testPush() {
            if ('Notification' in window) {
                Notification.requestPermission().then(permission => {
                    if (permission === 'granted') {
                        new Notification('Test Notification', { 
                            body: 'Push notifications are working!',
                            icon: '<?php echo esc_url(OVERSEE_PLUGIN_URL . 'assets/images/icon.png'); ?>'
                        });
                        updatePushStatus('granted');
                    } else {
                        updatePushStatus('denied');
                        showToast('Enable notifications in browser settings', true);
                    }
                });
            } else {
                showToast('Notifications not supported', true);
            }
        }
        
        function updatePushStatus(status) {
            const el = document.getElementById('pushStatus');
            if (status === 'granted') {
                el.className = 'push-status granted';
                el.innerHTML = '<i class="fa-solid fa-circle-check"></i><span><strong>Push notifications enabled</strong> - You\'ll receive notifications in this browser</span>';
            } else {
                el.className = 'push-status denied';
                el.innerHTML = '<i class="fa-solid fa-circle-xmark"></i><span><strong>Push notifications disabled</strong> - Enable in browser settings</span>';
            }
        }
        
        function showToast(msg, isError = false) {
            const toast = document.getElementById('toast');
            const icon = toast.querySelector('i');
            document.getElementById('toastMessage').textContent = msg;
            icon.className = isError ? 'fa-solid fa-exclamation-circle' : 'fa-solid fa-check-circle';
            toast.classList.toggle('error', isError);
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 3000);
        }
        
        // Check push status on load
        document.addEventListener('DOMContentLoaded', function() {
            if ('Notification' in window) {
                updatePushStatus(Notification.permission);
            }
        });
    </script>
</body>
</html>
