<?php
/**
 * Admin My Profile Page
 * Production-ready with clean separation:
 * - Calendar Link + Zoom Link = For ticket replies only
 * - Calendar Embed = For Live Support page lightbox
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

// Get agent settings
$calendar_link = $agent->calendar_link ?? '';
$zoom_link = $agent->zoom_link ?? '';
$calendar_embed = $agent->calendar_embed ?? '';
$show_on_live_support = $agent ? ($agent->show_on_live_support ?? 0) : 0;

// Get initials
$name = $current_user->display_name ?: $current_user->user_email;
$initials = '';
$parts = explode(' ', $name);
foreach ($parts as $part) {
    $initials .= strtoupper(substr($part, 0, 1));
}
$initials = substr($initials, 0, 2);
$branding = Oversee_Branding::get_for_context('admin');
$favicon = Oversee_Branding::get('favicon_url');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo esc_html($branding['company_name'] ?: oversee_get_portal_title()); ?></title>
    <?php if ($favicon): ?>
    <link rel="icon" href="<?php echo esc_url($favicon); ?>" type="image/x-icon">
    <?php endif; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="<?php echo esc_url(OVERSEE_PLUGIN_URL . 'assets/css/admin.css?v=' . OVERSEE_VERSION); ?>">
    <?php echo Oversee_Branding::get_css_variables(); ?>
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
                                    <input type="email" value="<?php echo esc_attr($current_user->user_email); ?>" disabled>
                                    <p class="form-help">Email cannot be changed here</p>
                                </div>
                            </div>
                            
                            <!-- Ticket Reply Links Section -->
                            <div class="section-divider">
                                <h4><i class="fa-solid fa-ticket"></i> Ticket Reply Links</h4>
                                <p>These links can be inserted when replying to tickets using the toolbar buttons.</p>
                            </div>
                            
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Calendar Booking Link</label>
                                    <input type="url" id="calendarLink" value="<?php echo esc_attr($calendar_link); ?>" placeholder="https://calendly.com/your-name">
                                    <p class="form-help">Calendly, Cal.com, or any booking page URL</p>
                                </div>
                                <div class="form-group">
                                    <label>Zoom Meeting Link</label>
                                    <input type="url" id="zoomLink" value="<?php echo esc_attr($zoom_link); ?>" placeholder="https://zoom.us/j/your-meeting-id">
                                    <p class="form-help">Your personal Zoom meeting room</p>
                                </div>
                            </div>
                            
                            <!-- Live Support Section -->
                            <div class="section-divider">
                                <h4><i class="fa-solid fa-headset"></i> Live Support Page</h4>
                                <p>Configure your appearance on the public Live Support page.</p>
                            </div>
                            
                            <div class="form-group">
                                <label>Calendar Embed Code</label>
                                <textarea id="calendarEmbed" rows="4" placeholder="Paste your calendar embed code here..."><?php echo esc_textarea($calendar_embed); ?></textarea>
                                <p class="form-help">
                                    <i class="fa-solid fa-info-circle"></i>
                                    Paste iframe embed code from Calendly, Cal.com, HubSpot, Acuity, TidyCal, SavvyCal, or link.overseecrm.com
                                </p>
                            </div>
                            
                            <div class="toggle-card <?php echo $show_on_live_support ? 'active' : ''; ?>">
                                <label class="toggle-switch">
                                    <input type="checkbox" id="showOnLiveSupport" <?php echo $show_on_live_support ? 'checked' : ''; ?>>
                                    <span class="slider"></span>
                                </label>
                                <div class="toggle-content">
                                    <strong>Show on Live Support Page</strong>
                                    <span>When online and enabled, customers can book time with you via the embedded calendar popup</span>
                                </div>
                            </div>
                            
                            <?php if ($calendar_embed): ?>
                            <button type="button" class="btn btn-secondary mt-12" onclick="previewEmbed()">
                                <i class="fa-solid fa-eye"></i> Preview Calendar Popup
                            </button>
                            <?php endif; ?>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-save"></i> Save Changes</button>
                            </div>
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
                await fetch(REST_URL + '/agent/status', {
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
                zoom_link: document.getElementById('zoomLink').value,
                calendar_embed: document.getElementById('calendarEmbed').value,
                show_on_live_support: document.getElementById('showOnLiveSupport').checked ? 1 : 0
            };
            
            try {
                const response = await fetch(REST_URL + '/agent/profile', {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-WP-Nonce': WP_NONCE
                    },
                    body: JSON.stringify(data)
                });
                
                if (response.ok) {
                    showToast('Profile saved successfully!');
                    // Update toggle card visual state
                    const toggleCard = document.querySelector('.toggle-card');
                    if (toggleCard) {
                        toggleCard.classList.toggle('active', data.show_on_live_support);
                    }
                } else {
                    throw new Error('Save failed');
                }
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
        
        function previewEmbed() {
            const embed = document.getElementById('calendarEmbed').value;
            if (!embed) {
                showToast('No embed code to preview', true);
                return;
            }
            
            // Create modal for preview
            const modal = document.createElement('div');
            modal.className = 'embed-modal';
            modal.innerHTML = `
                <div class="embed-modal-content">
                    <div class="embed-modal-header">
                        <h3>Calendar Preview</h3>
                        <button onclick="this.closest('.embed-modal').remove()">&times;</button>
                    </div>
                    <div class="embed-modal-body">${embed}</div>
                </div>
            `;
            modal.onclick = function(e) { if (e.target === modal) modal.remove(); };
            document.body.appendChild(modal);
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
