<?php
/**
 * Admin Settings Page
 * Matches reference design with all features
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!is_user_logged_in() || !current_user_can('oversee_manage_settings')) {
    wp_safe_redirect(oversee_admin_url());
    exit;
}

$current_user = wp_get_current_user();
$settings = get_option('oversee_settings', []);
$branding = Oversee_Branding::get_for_context('admin');
$favicon = Oversee_Branding::get('favicon_url');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo esc_html($branding['company_name'] ?: oversee_get_portal_title()); ?></title>
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
            <h2>Settings</h2>
            <div class="topbar-right">
                <button class="btn btn-primary" onclick="saveSettings()"><i class="fa-solid fa-save"></i> Save Changes</button>
            </div>
        </div>
        
        <div class="settings-content">
            <!-- Settings Tabs -->
            <div class="settings-tabs">
                <button class="settings-tab active" onclick="showTab('general')"><i class="fa-solid fa-gear"></i> General</button>
                <button class="settings-tab" onclick="showTab('agents')"><i class="fa-solid fa-users"></i> Agents</button>
                <button class="settings-tab" onclick="showTab('assignment')"><i class="fa-solid fa-user-plus"></i> Assignment</button>
                <button class="settings-tab" onclick="showTab('notifications')"><i class="fa-solid fa-bell"></i> Notifications</button>
                <button class="settings-tab" onclick="showTab('integrations')"><i class="fa-solid fa-plug"></i> Integrations</button>
                <button class="settings-tab" onclick="showTab('security')"><i class="fa-solid fa-shield"></i> Security</button>
            </div>
            
            <!-- General Tab -->
            <div id="tab-general" class="tab-content active">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3>Portal Settings</h3>
                            <p>Basic configuration for your support portal</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Portal Name</label>
                            <input type="text" id="portalName" value="<?php echo esc_attr($settings['portal_name'] ?? get_bloginfo('name') . ' Support'); ?>">
                            <p class="form-help">Displayed in the header and emails</p>
                        </div>
                        
                        <div class="form-group">
                            <label>Support Email</label>
                            <input type="email" id="supportEmail" value="<?php echo esc_attr($settings['support_email'] ?? get_option('admin_email')); ?>">
                            <p class="form-help">Email address for support notifications</p>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Ticket Number Prefix</label>
                                <input type="text" id="ticketPrefix" value="<?php echo esc_attr($settings['ticket_prefix'] ?? 'TKT'); ?>">
                            </div>
                            <div class="form-group">
                                <label>Default Priority</label>
                                <select id="defaultPriority">
                                    <option value="low" <?php selected($settings['default_priority'] ?? 'normal', 'low'); ?>>Low</option>
                                    <option value="normal" <?php selected($settings['default_priority'] ?? 'normal', 'normal'); ?>>Normal</option>
                                    <option value="high" <?php selected($settings['default_priority'] ?? 'normal', 'high'); ?>>High</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3>Business Hours</h3>
                            <p>Set your support availability</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="option-row">
                            <div class="option-info">
                                <h5>Enable business hours</h5>
                                <p>Show availability status based on schedule</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" id="enableBusinessHours" <?php checked($settings['business_hours_enabled'] ?? false); ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-group">
                                <label>Start Time</label>
                                <input type="time" id="businessStart" value="<?php echo esc_attr($settings['business_start'] ?? '09:00'); ?>">
                            </div>
                            <div class="form-group">
                                <label>End Time</label>
                                <input type="time" id="businessEnd" value="<?php echo esc_attr($settings['business_end'] ?? '17:00'); ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Agents Tab -->
            <div id="tab-agents" class="tab-content">
                <!-- Agent Stats Row -->
                <div class="agents-overview">
                    <div class="overview-stat">
                        <div class="overview-stat-icon icon-blue">
                            <i class="fa-solid fa-users"></i>
                        </div>
                        <div class="overview-stat-content">
                            <span class="overview-stat-value" id="totalAgentsCount">-</span>
                            <span class="overview-stat-label">Total Agents</span>
                        </div>
                    </div>
                    <div class="overview-stat">
                        <div class="overview-stat-icon icon-green">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                        <div class="overview-stat-content">
                            <span class="overview-stat-value" id="onlineAgentsCount">-</span>
                            <span class="overview-stat-label">Online Now</span>
                        </div>
                    </div>
                    <div class="overview-stat">
                        <div class="overview-stat-icon icon-orange">
                            <i class="fa-solid fa-ticket"></i>
                        </div>
                        <div class="overview-stat-content">
                            <span class="overview-stat-value" id="totalOpenTickets">-</span>
                            <span class="overview-stat-label">Open Tickets</span>
                        </div>
                    </div>
                    <div class="overview-stat">
                        <div class="overview-stat-icon icon-purple">
                            <i class="fa-solid fa-clock"></i>
                        </div>
                        <div class="overview-stat-content">
                            <span class="overview-stat-value" id="avgResponseTime">-</span>
                            <span class="overview-stat-label">Avg Response</span>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3><i class="fa-solid fa-users icon-primary"></i>Support Team</h3>
                            <p>Manage who can access the support dashboard and handle tickets</p>
                        </div>
                        <button class="btn btn-primary" onclick="showModal('addAgentModal')">
                            <i class="fa-solid fa-plus"></i> Add Agent
                        </button>
                    </div>
                    <div class="card-body no-padding">
                        <div class="agents-table-wrapper">
                            <table class="data-table agents-table">
                                <thead>
                                    <tr>
                                        <th>Agent</th>
                                        <th>Role</th>
                                        <th>Status</th>
                                        <th>Workload</th>
                                        <th>Performance</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="agentsList">
                                    <tr>
                                        <td colspan="6">
                                            <div class="table-loading">
                                                <i class="fa-solid fa-spinner fa-spin"></i>
                                                <span>Loading agents...</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Assignment Tab -->
            <div id="tab-assignment" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3><i class="fa-solid fa-user-gear icon-primary"></i>Auto-Assignment</h3>
                            <p>Automatically assign new tickets to available agents</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="toggle-list">
                            <label class="toggle-item toggle-item-main">
                                <div class="toggle-switch">
                                    <input type="checkbox" id="autoAssign" <?php checked($settings['auto_assign'] ?? false); ?> onchange="toggleAutoAssign()">
                                    <span class="toggle-slider"></span>
                                </div>
                                <div class="toggle-content">
                                    <span class="toggle-title">Enable auto-assignment</span>
                                    <span class="toggle-desc">Automatically assign incoming tickets to available agents based on your settings</span>
                                </div>
                            </label>
                        </div>
                        
                        <div id="assignmentOptions" class="assignment-options-panel">
                            <div class="form-section">
                                <label class="form-label">Assignment Method</label>
                                <div class="assignment-methods">
                                    <?php $current_method = $settings['assignment_method'] ?? 'round_robin'; ?>
                                    <label class="method-option <?php echo $current_method === 'round_robin' ? 'selected' : ''; ?>">
                                        <input type="radio" name="assignmentMethodRadio" value="round_robin" <?php checked($current_method, 'round_robin'); ?> onchange="updateAssignmentMethod(this)">
                                        <div class="method-card">
                                            <div class="method-icon">
                                                <i class="fa-solid fa-rotate"></i>
                                            </div>
                                            <div class="method-info">
                                                <span class="method-name">Round Robin</span>
                                                <span class="method-desc">Distribute tickets evenly in rotation</span>
                                            </div>
                                        </div>
                                    </label>
                                    <label class="method-option <?php echo $current_method === 'least_busy' ? 'selected' : ''; ?>">
                                        <input type="radio" name="assignmentMethodRadio" value="least_busy" <?php checked($current_method, 'least_busy'); ?> onchange="updateAssignmentMethod(this)">
                                        <div class="method-card">
                                            <div class="method-icon">
                                                <i class="fa-solid fa-scale-balanced"></i>
                                            </div>
                                            <div class="method-info">
                                                <span class="method-name">Least Busy</span>
                                                <span class="method-desc">Assign to agent with fewest tickets</span>
                                            </div>
                                        </div>
                                    </label>
                                    <label class="method-option <?php echo $current_method === 'random' ? 'selected' : ''; ?>">
                                        <input type="radio" name="assignmentMethodRadio" value="random" <?php checked($current_method, 'random'); ?> onchange="updateAssignmentMethod(this)">
                                        <div class="method-card">
                                            <div class="method-icon">
                                                <i class="fa-solid fa-shuffle"></i>
                                            </div>
                                            <div class="method-info">
                                                <span class="method-name">Random</span>
                                                <span class="method-desc">Randomly select available agent</span>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                                <input type="hidden" id="assignmentMethod" value="<?php echo esc_attr($current_method); ?>">
                            </div>
                            
                            <div class="assignment-settings">
                                <div class="toggle-list">
                                    <label class="toggle-item">
                                        <div class="toggle-switch">
                                            <input type="checkbox" id="onlineOnly" <?php checked($settings['assign_online_only'] ?? true); ?>>
                                            <span class="toggle-slider"></span>
                                        </div>
                                        <div class="toggle-content">
                                            <span class="toggle-title">Only assign to online agents</span>
                                            <span class="toggle-desc">Skip agents who are currently marked as offline</span>
                                        </div>
                                    </label>
                                </div>
                                
                                <div class="form-section max-tickets-section">
                                    <label class="form-label">Maximum tickets per agent</label>
                                    <p class="form-hint">Stop assigning when an agent reaches this many open tickets</p>
                                    <div class="max-tickets-input">
                                        <button type="button" class="ticket-adjust-btn" onclick="adjustMaxTickets(-1)">
                                            <i class="fa-solid fa-minus"></i>
                                        </button>
                                        <input type="number" id="maxTickets" value="<?php echo esc_attr($settings['max_tickets_per_agent'] ?? 20); ?>" min="1" max="100" class="max-tickets-field">
                                        <button type="button" class="ticket-adjust-btn" onclick="adjustMaxTickets(1)">
                                            <i class="fa-solid fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Notifications Tab -->
            <div id="tab-notifications" class="tab-content">
                <!-- Push Notification Permission Prompt (shows when permission is 'default') -->
                <div id="pushPermissionPrompt" class="permission-prompt-banner">
                    <div class="permission-prompt-content">
                        <div class="permission-prompt-icon">
                            <i class="fa-solid fa-bell"></i>
                        </div>
                        <div class="permission-prompt-text">
                            <h4>Enable Browser Notifications</h4>
                            <p>Get instant alerts when new tickets arrive or customers reply - even when you're on another tab.</p>
                        </div>
                        <div class="permission-prompt-actions">
                            <button type="button" class="btn btn-primary btn-lg" onclick="triggerPushPermissionPrompt()">
                                <i class="fa-solid fa-bell"></i> Allow Notifications
                            </button>
                            <button type="button" class="btn btn-outline" onclick="dismissPermissionPrompt()">
                                Maybe Later
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Push Notifications Card -->
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3><i class="fa-solid fa-bell icon-warning"></i>Browser Push Notifications</h3>
                            <p>Get instant alerts when new tickets arrive or customers reply</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="pushNotificationCard" class="push-notification-card">
                            <div class="push-card-icon" id="pushCardIcon">
                                <i class="fa-solid fa-bell-slash"></i>
                            </div>
                            <div class="push-card-content">
                                <h4 id="pushCardTitle">Push Notifications Disabled</h4>
                                <p id="pushCardDesc">Enable push notifications to receive instant alerts about new tickets and customer replies.</p>
                            </div>
                            <div class="push-card-actions" id="pushCardActions">
                                <button type="button" class="btn btn-primary" id="pushEnableBtn" onclick="triggerPushPermissionPrompt()">
                                    <i class="fa-solid fa-bell"></i> Enable Notifications
                                </button>
                            </div>
                        </div>
                        
                        <!-- Push Notification Options (shown when enabled) -->
                        <div id="pushNotificationOptions" class="push-options hidden">
                            <div class="form-section">
                                <label class="form-label">Notify me about:</label>
                                <div class="toggle-list">
                                    <label class="toggle-item">
                                        <div class="toggle-switch">
                                            <input type="checkbox" id="pushNewTicket" checked onchange="savePushPreferences()">
                                            <span class="toggle-slider"></span>
                                        </div>
                                        <div class="toggle-content">
                                            <span class="toggle-title">New tickets</span>
                                            <span class="toggle-desc">When a customer submits a new support ticket</span>
                                        </div>
                                    </label>
                                    <label class="toggle-item">
                                        <div class="toggle-switch">
                                            <input type="checkbox" id="pushCustomerReply" checked onchange="savePushPreferences()">
                                            <span class="toggle-slider"></span>
                                        </div>
                                        <div class="toggle-content">
                                            <span class="toggle-title">Customer replies</span>
                                            <span class="toggle-desc">When a customer replies to an existing ticket</span>
                                        </div>
                                    </label>
                                    <label class="toggle-item">
                                        <div class="toggle-switch">
                                            <input type="checkbox" id="pushAssigned" checked onchange="savePushPreferences()">
                                            <span class="toggle-slider"></span>
                                        </div>
                                        <div class="toggle-content">
                                            <span class="toggle-title">Ticket assigned to me</span>
                                            <span class="toggle-desc">When a ticket is assigned to you</span>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="push-test-section">
                                <button type="button" class="btn btn-outline" onclick="sendTestNotification()">
                                    <i class="fa-solid fa-paper-plane"></i> Send Test Notification
                                </button>
                            </div>
                        </div>
                        
                        <div class="callout callout-info push-info-callout">
                            <div class="callout-icon">
                                <i class="fa-solid fa-circle-info"></i>
                            </div>
                            <div class="callout-content">
                                <strong>How it works</strong>
                                <p>When enabled, you'll receive browser notifications for new tickets, customer replies, and important updates - even when this tab isn't active. Make sure to allow notifications when prompted by your browser.</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Email Notifications Card -->
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3><i class="fa-solid fa-envelope icon-info"></i>Email Notifications</h3>
                            <p>Configure when emails are sent to customers</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="toggle-list">
                            <label class="toggle-item">
                                <div class="toggle-switch">
                                    <input type="checkbox" id="emailNewTicket" <?php checked($settings['email_new_ticket'] ?? true); ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                                <div class="toggle-content">
                                    <span class="toggle-title">New ticket confirmation</span>
                                    <span class="toggle-desc">Send email to customer when ticket is created</span>
                                </div>
                            </label>
                            <label class="toggle-item">
                                <div class="toggle-switch">
                                    <input type="checkbox" id="emailAgentReply" <?php checked($settings['email_agent_reply'] ?? true); ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                                <div class="toggle-content">
                                    <span class="toggle-title">Agent reply notification</span>
                                    <span class="toggle-desc">Notify customer when an agent replies</span>
                                </div>
                            </label>
                            <label class="toggle-item">
                                <div class="toggle-switch">
                                    <input type="checkbox" id="emailResolved" <?php checked($settings['email_resolved'] ?? true); ?>>
                                    <span class="toggle-slider"></span>
                                </div>
                                <div class="toggle-content">
                                    <span class="toggle-title">Ticket resolved notification</span>
                                    <span class="toggle-desc">Notify customer when ticket is marked resolved</span>
                                </div>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Integrations Tab -->
            <div id="tab-integrations" class="tab-content">
                <!-- Embed Links Section -->
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3><i class="fa-solid fa-code icon-primary"></i>Embed Links</h3>
                            <p>Use these URLs to embed the support portal in HighLevel or other websites</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="embed-grid">
                            <div class="embed-card">
                                <div class="embed-card-header">
                                    <div class="embed-icon embed-icon-kb">
                                        <i class="fa-solid fa-book"></i>
                                    </div>
                                    <div class="embed-title">
                                        <h4>Knowledge Base</h4>
                                        <span>Public-facing KB for client portals</span>
                                    </div>
                                </div>
                                <div class="embed-card-body">
                                    <div class="copy-field">
                                        <input type="text" value="<?php echo esc_url(oversee_kb_url()); ?>" readonly id="kbEmbedUrl">
                                        <button type="button" class="copy-btn" onclick="copyField('kbEmbedUrl')" title="Copy URL">
                                            <i class="fa-solid fa-copy"></i>
                                        </button>
                                    </div>
                                    <details class="embed-details">
                                        <summary>
                                            <i class="fa-solid fa-chevron-right"></i>
                                            <span>Show iframe embed code</span>
                                        </summary>
                                        <div class="embed-code">
                                            <code>&lt;iframe src="<?php echo esc_url(oversee_kb_url()); ?>" width="100%" height="600" frameborder="0"&gt;&lt;/iframe&gt;</code>
                                            <button type="button" class="copy-code-btn" onclick="copyField('kbIframeCode')" title="Copy code">
                                                <i class="fa-solid fa-copy"></i>
                                            </button>
                                            <input type="hidden" id="kbIframeCode" value='<iframe src="<?php echo esc_url(oversee_kb_url()); ?>" width="100%" height="600" frameborder="0"></iframe>'>
                                        </div>
                                    </details>
                                </div>
                            </div>
                            
                            <div class="embed-card">
                                <div class="embed-card-header">
                                    <div class="embed-icon embed-icon-dashboard">
                                        <i class="fa-solid fa-gauge-high"></i>
                                    </div>
                                    <div class="embed-title">
                                        <h4>Admin Dashboard</h4>
                                        <span>Agent portal (requires WP login)</span>
                                    </div>
                                </div>
                                <div class="embed-card-body">
                                    <div class="copy-field">
                                        <input type="text" value="<?php echo esc_url(oversee_admin_url()); ?>" readonly id="dashboardEmbedUrl">
                                        <button type="button" class="copy-btn" onclick="copyField('dashboardEmbedUrl')" title="Copy URL">
                                            <i class="fa-solid fa-copy"></i>
                                        </button>
                                    </div>
                                    <details class="embed-details">
                                        <summary>
                                            <i class="fa-solid fa-chevron-right"></i>
                                            <span>Show iframe embed code</span>
                                        </summary>
                                        <div class="embed-code">
                                            <code>&lt;iframe src="<?php echo esc_url(oversee_admin_url()); ?>" width="100%" height="800" frameborder="0"&gt;&lt;/iframe&gt;</code>
                                            <button type="button" class="copy-code-btn" onclick="copyField('dashboardIframeCode')" title="Copy code">
                                                <i class="fa-solid fa-copy"></i>
                                            </button>
                                            <input type="hidden" id="dashboardIframeCode" value='<iframe src="<?php echo esc_url(oversee_admin_url()); ?>" width="100%" height="800" frameborder="0"></iframe>'>
                                        </div>
                                    </details>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- HighLevel Knowledge Base Sync -->
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3><i class="fa-solid fa-rotate icon-success"></i>HighLevel Knowledge Base Sync</h3>
                            <p>Import and sync articles from your HighLevel Knowledge Base export</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="callout callout-success">
                            <div class="callout-icon">
                                <i class="fa-solid fa-circle-info"></i>
                            </div>
                            <div class="callout-content">
                                <strong>How it works</strong>
                                <p>This syncs articles from your exported HighLevel Knowledge Base. Articles are imported with their categories and content intact.</p>
                            </div>
                        </div>
                        
                        <input type="hidden" id="kbSourceUrl" value="<?php echo esc_attr(get_option('oversee_static_kb_url', 'https://overseecrm.com/wp-content/uploads/knowledge-base')); ?>">
                        
                        <div class="sync-status-box" id="syncStatus">
                            <div class="sync-status-header">
                                <span id="syncMessage">Preparing...</span>
                                <span id="syncPercent">0%</span>
                            </div>
                            <div class="sync-progress-track">
                                <div id="syncProgressBar" class="sync-progress-bar"></div>
                            </div>
                        </div>
                        
                        <?php 
                        $last_sync = get_option('oversee_kb_last_sync');
                        $sync_stats = get_option('oversee_kb_sync_stats', []);
                        $kb = new Oversee_KB();
                        $article_count = $kb->get_total_articles();
                        $category_count = $kb->get_total_categories();
                        ?>
                        
                        <div class="sync-dashboard">
                            <div class="sync-stat-card">
                                <div class="sync-stat-number"><?php echo $article_count; ?></div>
                                <div class="sync-stat-label">Articles</div>
                            </div>
                            <div class="sync-stat-card">
                                <div class="sync-stat-number"><?php echo $category_count; ?></div>
                                <div class="sync-stat-label">Categories</div>
                            </div>
                            <div class="sync-stat-card">
                                <div class="sync-stat-number"><?php echo $last_sync ? date('M j', strtotime($last_sync)) : '—'; ?></div>
                                <div class="sync-stat-label">Last Sync</div>
                            </div>
                        </div>
                        
                        <div class="sync-actions">
                            <button type="button" class="btn btn-primary" onclick="startSync()" id="syncBtn">
                                <i class="fa-solid fa-sync"></i> Sync Now
                            </button>
                            <button type="button" class="btn btn-outline" onclick="clearKbCache()" id="cacheBtn">
                                <i class="fa-solid fa-broom"></i> Clear Cache
                            </button>
                            <button type="button" class="btn btn-outline btn-danger-outline" onclick="clearKbData()" id="clearBtn">
                                <i class="fa-solid fa-trash"></i> Clear All Data
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- HighLevel Workflow Notifications -->
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3><i class="fa-solid fa-bolt icon-warning"></i>HighLevel Workflow Notifications</h3>
                            <p>Send ticket events to HighLevel workflows in your sub-accounts</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="callout callout-warning">
                            <div class="callout-icon">
                                <i class="fa-solid fa-lightbulb"></i>
                            </div>
                            <div class="callout-content">
                                <strong>Use Case</strong>
                                <p>Trigger HighLevel workflows when tickets are created, updated, or resolved. Perfect for sending SMS/email notifications to clients or updating CRM records.</p>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <label class="form-label">HighLevel Workflow Webhook URL</label>
                            <input type="text" id="hlWorkflowUrl" class="form-input" value="<?php echo esc_attr(get_option('oversee_hl_workflow_url', '')); ?>" placeholder="https://services.leadconnectorhq.com/hooks/...">
                            <p class="form-hint">Paste your HighLevel Inbound Webhook URL from a workflow trigger</p>
                        </div>
                        
                        <div class="form-section">
                            <label class="form-label">Send notifications for:</label>
                            <?php $hl_events = get_option('oversee_hl_workflow_events', ['ticket_created']); ?>
                            <div class="toggle-list">
                                <label class="toggle-item">
                                    <div class="toggle-switch">
                                        <input type="checkbox" id="hlEventCreated" <?php checked(in_array('ticket_created', $hl_events)); ?>>
                                        <span class="toggle-slider"></span>
                                    </div>
                                    <div class="toggle-content">
                                        <span class="toggle-title">New ticket created</span>
                                        <span class="toggle-desc">When a customer submits a new support ticket</span>
                                    </div>
                                </label>
                                <label class="toggle-item">
                                    <div class="toggle-switch">
                                        <input type="checkbox" id="hlEventReplied" <?php checked(in_array('ticket_replied', $hl_events)); ?>>
                                        <span class="toggle-slider"></span>
                                    </div>
                                    <div class="toggle-content">
                                        <span class="toggle-title">Agent replied to ticket</span>
                                        <span class="toggle-desc">When a support agent responds to a ticket</span>
                                    </div>
                                </label>
                                <label class="toggle-item">
                                    <div class="toggle-switch">
                                        <input type="checkbox" id="hlEventResolved" <?php checked(in_array('ticket_resolved', $hl_events)); ?>>
                                        <span class="toggle-slider"></span>
                                    </div>
                                    <div class="toggle-content">
                                        <span class="toggle-title">Ticket resolved</span>
                                        <span class="toggle-desc">When a ticket is marked as resolved</span>
                                    </div>
                                </label>
                                <label class="toggle-item">
                                    <div class="toggle-switch">
                                        <input type="checkbox" id="hlEventCustomerReply" <?php checked(in_array('customer_replied', $hl_events)); ?>>
                                        <span class="toggle-slider"></span>
                                    </div>
                                    <div class="toggle-content">
                                        <span class="toggle-title">Customer replied</span>
                                        <span class="toggle-desc">When a customer adds a reply to their ticket</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        <div class="form-actions-inline">
                            <button type="button" class="btn btn-primary" onclick="saveHlWorkflow()">
                                <i class="fa-solid fa-save"></i> Save Settings
                            </button>
                            <button type="button" class="btn btn-outline" onclick="testHlWorkflow()">
                                <i class="fa-solid fa-paper-plane"></i> Send Test
                            </button>
                        </div>
                        
                        <details class="code-details">
                            <summary>
                                <i class="fa-solid fa-chevron-right"></i>
                                <span>View webhook payload format</span>
                            </summary>
                            <div class="code-preview">
<pre>{
  "event": "ticket_created",
  "ticket_id": 123,
  "ticket_number": "TKT-ABC123",
  "subject": "Help with setup",
  "customer_name": "John Doe",
  "customer_email": "john@example.com",
  "customer_phone": "+1234567890",
  "status": "new",
  "priority": "high",
  "assigned_agent": "Support Team",
  "created_at": "2026-01-08T10:30:00Z",
  "ticket_url": "https://..."
}</pre>
                            </div>
                        </details>
                    </div>
                </div>
                
                <!-- Incoming Webhooks -->
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3><i class="fa-solid fa-arrow-right-to-bracket icon-info"></i>Incoming Webhooks</h3>
                            <p>Create tickets from HighLevel workflows or external systems</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="callout callout-info">
                            <div class="callout-icon">
                                <i class="fa-solid fa-circle-info"></i>
                            </div>
                            <div class="callout-content">
                                <strong>Use Case</strong>
                                <p>Automatically create support tickets from HighLevel form submissions, workflow actions, or any system that can send HTTP POST requests.</p>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <label class="form-label">Your Webhook URL</label>
                            <div class="copy-field copy-field-lg">
                                <input type="text" value="<?php echo esc_url(rest_url('oversee/v1/webhook/incoming')); ?>" readonly id="incomingWebhookUrl" class="monospace">
                                <button type="button" class="copy-btn" onclick="copyField('incomingWebhookUrl')" title="Copy URL">
                                    <i class="fa-solid fa-copy"></i>
                                    <span>Copy</span>
                                </button>
                            </div>
                            <p class="form-hint">Use this URL in HighLevel's HTTP action or external webhooks</p>
                        </div>
                        
                        <div class="form-section">
                            <label class="form-label">Secret Key</label>
                            <div class="copy-field copy-field-lg">
                                <input type="text" id="webhookKey" value="<?php echo esc_attr($settings['webhook_key'] ?? wp_generate_password(24, false)); ?>" readonly class="monospace">
                                <button type="button" class="btn btn-outline btn-sm" onclick="regenerateWebhookKey()">
                                    <i class="fa-solid fa-rotate"></i> Regenerate
                                </button>
                            </div>
                            <p class="form-hint">Include this in the <code>X-Webhook-Secret</code> header for authentication</p>
                        </div>
                        
                        <details class="code-details">
                            <summary>
                                <i class="fa-solid fa-chevron-right"></i>
                                <span>View required payload format</span>
                            </summary>
                            <div class="code-preview">
<pre>POST /wp-json/oversee/v1/webhook/incoming
Headers:
  Content-Type: application/json
  X-Webhook-Secret: your_secret_key

Body:
{
  "email": "customer@example.com",  <span class="code-comment">// Required</span>
  "name": "Customer Name",          <span class="code-comment">// Optional</span>
  "subject": "Ticket Subject",      <span class="code-comment">// Optional</span>
  "description": "Message body...", <span class="code-comment">// Optional</span>
  "phone": "+1234567890",           <span class="code-comment">// Optional</span>
  "priority": "high"                <span class="code-comment">// Optional: "low" or "high"</span>
}</pre>
                            </div>
                        </details>
                    </div>
                </div>
            </div>
            
            <!-- Security Tab -->
            <div id="tab-security" class="tab-content">
                <div class="callout callout-info">
                    <div class="callout-icon">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <div class="callout-content">
                        <strong>Security Overview</strong>
                        <p>Authentication is handled by WordPress. Agents must have a WordPress user account with the appropriate role to access the support dashboard.</p>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3><i class="fa-solid fa-clock icon-primary"></i>Session Settings</h3>
                            <p>Configure session timeout and authentication behavior</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-section">
                            <label class="form-label">Session Timeout</label>
                            <p class="form-hint">Automatically log out agents after this period of inactivity</p>
                            <?php $current_timeout = $settings['session_timeout'] ?? 86400; ?>
                            <div class="timeout-options">
                                <label class="timeout-option <?php echo $current_timeout == 3600 ? 'selected' : ''; ?>">
                                    <input type="radio" name="sessionTimeout" value="3600" <?php checked($current_timeout, 3600); ?>>
                                    <div class="timeout-option-inner">
                                        <span class="timeout-value">1</span>
                                        <span class="timeout-unit">Hour</span>
                                    </div>
                                </label>
                                <label class="timeout-option <?php echo $current_timeout == 14400 ? 'selected' : ''; ?>">
                                    <input type="radio" name="sessionTimeout" value="14400" <?php checked($current_timeout, 14400); ?>>
                                    <div class="timeout-option-inner">
                                        <span class="timeout-value">4</span>
                                        <span class="timeout-unit">Hours</span>
                                    </div>
                                </label>
                                <label class="timeout-option <?php echo $current_timeout == 86400 ? 'selected' : ''; ?>">
                                    <input type="radio" name="sessionTimeout" value="86400" <?php checked($current_timeout, 86400); ?>>
                                    <div class="timeout-option-inner">
                                        <span class="timeout-value">24</span>
                                        <span class="timeout-unit">Hours</span>
                                    </div>
                                </label>
                                <label class="timeout-option <?php echo $current_timeout == 604800 ? 'selected' : ''; ?>">
                                    <input type="radio" name="sessionTimeout" value="604800" <?php checked($current_timeout, 604800); ?>>
                                    <div class="timeout-option-inner">
                                        <span class="timeout-value">7</span>
                                        <span class="timeout-unit">Days</span>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3><i class="fa-solid fa-desktop icon-success"></i>Active Sessions</h3>
                            <p>Monitor and manage currently logged in agents</p>
                        </div>
                        <button type="button" class="btn btn-outline btn-sm" onclick="loadSessions()">
                            <i class="fa-solid fa-rotate"></i> Refresh
                        </button>
                    </div>
                    <div class="card-body no-padding">
                        <div class="sessions-table-wrapper">
                            <table class="data-table sessions-table">
                                <thead>
                                    <tr>
                                        <th>Agent</th>
                                        <th>Device / Browser</th>
                                        <th>IP Address</th>
                                        <th>Last Activity</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="sessionsList">
                                    <tr>
                                        <td colspan="5">
                                            <div class="table-loading">
                                                <i class="fa-solid fa-spinner fa-spin"></i>
                                                <span>Loading sessions...</span>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Add Agent Modal -->
    <div class="modal" id="addAgentModal">
        <div class="modal-backdrop" onclick="closeModal('addAgentModal')"></div>
        <div class="modal-content modal-md">
            <div class="modal-header">
                <h3><i class="fa-solid fa-user-plus"></i> Add New Agent</h3>
                <button class="modal-close" onclick="closeModal('addAgentModal')"><i class="fa-solid fa-times"></i></button>
            </div>
            <form id="addAgentForm" onsubmit="addAgent(event)">
                <div class="modal-body">
                    <div class="form-section">
                        <label class="form-label">Select WordPress User</label>
                        <select id="wpUserId" class="form-select" required>
                            <option value="">Choose a user...</option>
                        </select>
                        <p class="form-hint">Only users not already agents are shown</p>
                    </div>
                    <div class="form-section">
                        <label class="form-label">Agent Role</label>
                        <div class="role-options">
                            <label class="role-option">
                                <input type="radio" name="agentRole" value="agent" checked>
                                <span class="role-card">
                                    <i class="fa-solid fa-headset"></i>
                                    <span class="role-name">Agent</span>
                                    <span class="role-desc">Can view and respond to tickets</span>
                                </span>
                            </label>
                            <label class="role-option">
                                <input type="radio" name="agentRole" value="admin">
                                <span class="role-card">
                                    <i class="fa-solid fa-user-shield"></i>
                                    <span class="role-name">Administrator</span>
                                    <span class="role-desc">Full access including settings</span>
                                </span>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal('addAgentModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Agent</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Notification Instructions Modal -->
    <div class="modal" id="notificationInstructionsModal">
        <div class="modal-backdrop" onclick="closeModal('notificationInstructionsModal')"></div>
        <div class="modal-content modal-md">
            <div class="modal-header">
                <h3><i class="fa-solid fa-bell"></i> Enable Notifications</h3>
                <button class="modal-close" onclick="closeModal('notificationInstructionsModal')"><i class="fa-solid fa-times"></i></button>
            </div>
            <div class="modal-body">
                <div class="notification-instructions">
                    <p class="instructions-intro">Notifications are currently blocked for this site. Follow these steps to enable them:</p>
                    
                    <div class="browser-instructions" id="chromeInstructions">
                        <div class="browser-header">
                            <i class="fa-brands fa-chrome"></i>
                            <span>Google Chrome</span>
                        </div>
                        <ol class="instructions-list">
                            <li>Click the <strong>lock icon</strong> (or info icon) in the address bar</li>
                            <li>Find <strong>"Notifications"</strong> in the dropdown</li>
                            <li>Change it from "Block" to <strong>"Allow"</strong></li>
                            <li>Refresh this page</li>
                        </ol>
                    </div>
                    
                    <div class="browser-instructions" id="firefoxInstructions">
                        <div class="browser-header">
                            <i class="fa-brands fa-firefox-browser"></i>
                            <span>Mozilla Firefox</span>
                        </div>
                        <ol class="instructions-list">
                            <li>Click the <strong>lock icon</strong> in the address bar</li>
                            <li>Click <strong>"Connection Secure"</strong></li>
                            <li>Click <strong>"More Information"</strong></li>
                            <li>Go to <strong>"Permissions"</strong> tab</li>
                            <li>Find "Send Notifications" and click <strong>"Allow"</strong></li>
                        </ol>
                    </div>
                    
                    <div class="browser-instructions" id="safariInstructions">
                        <div class="browser-header">
                            <i class="fa-brands fa-safari"></i>
                            <span>Safari</span>
                        </div>
                        <ol class="instructions-list">
                            <li>Go to <strong>Safari → Settings → Websites</strong></li>
                            <li>Click <strong>"Notifications"</strong> in the sidebar</li>
                            <li>Find this website and change to <strong>"Allow"</strong></li>
                        </ol>
                    </div>
                    
                    <div class="browser-instructions" id="edgeInstructions">
                        <div class="browser-header">
                            <i class="fa-brands fa-edge"></i>
                            <span>Microsoft Edge</span>
                        </div>
                        <ol class="instructions-list">
                            <li>Click the <strong>lock icon</strong> in the address bar</li>
                            <li>Click <strong>"Permissions for this site"</strong></li>
                            <li>Find "Notifications" and set to <strong>"Allow"</strong></li>
                            <li>Refresh this page</li>
                        </ol>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('notificationInstructionsModal')">Close</button>
                <button type="button" class="btn btn-primary" onclick="closeModal('notificationInstructionsModal'); location.reload();">
                    <i class="fa-solid fa-rotate"></i> Refresh Page
                </button>
            </div>
        </div>
    </div>
    
    <div class="toast" id="toast"><i class="fa-solid fa-check-circle"></i><span id="toastMessage"></span></div>
    
    <script>
        const WP_NONCE = '<?php echo wp_create_nonce('wp_rest'); ?>';
        const REST_URL = '<?php echo esc_url(rest_url('oversee/v1')); ?>';
        const colors = ['#f97316', '#3b82f6', '#8b5cf6', '#10b981', '#ef4444', '#6366f1'];
        
        document.addEventListener('DOMContentLoaded', function() {
            loadAgents();
            loadSessions();
            loadWPUsers();
            toggleAutoAssign();
            initTimeoutCards();
            checkPushNotificationStatus();
            showPermissionPromptIfNeeded();
        });
        
        function showTab(tab) {
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
            document.getElementById('tab-' + tab).classList.add('active');
            event.target.closest('.settings-tab').classList.add('active');
        }
        
        async function loadAgents() {
            try {
                const agents = await api('GET', '/agents');
                renderAgents(agents);
                updateAgentStats(agents);
            } catch (e) {
                document.getElementById('agentsList').innerHTML = '<tr><td colspan="6" class="table-empty">Failed to load agents</td></tr>';
            }
        }
        
        function renderAgents(agents) {
            const tbody = document.getElementById('agentsList');
            
            if (!agents || agents.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="table-empty">No agents yet. Click "Add Agent" to get started.</td></tr>';
                return;
            }
            
            tbody.innerHTML = agents.map((a, i) => {
                const initials = getInitials(a.display_name || a.email);
                const avgResponse = a.metrics?.avg_first_response_formatted || '-';
                const resolved = a.metrics?.resolved_week || 0;
                return `
                    <tr>
                        <td>
                            <div class="agent-cell">
                                <div class="agent-avatar avatar-color-${i % 6}">
                                    ${initials}
                                    <span class="avatar-status ${a.is_online ? 'online' : 'offline'}"></span>
                                </div>
                                <div class="agent-details">
                                    <span class="agent-name">${escapeHtml(a.display_name || 'No name')}</span>
                                    <span class="agent-email">${escapeHtml(a.email)}</span>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span class="role-badge ${a.is_admin ? 'admin' : 'agent'}">${a.is_admin ? 'Administrator' : 'Agent'}</span>
                        </td>
                        <td>
                            <span class="status-indicator ${a.is_online ? 'online' : 'offline'}">
                                <span class="status-dot"></span>
                                ${a.is_online ? 'Online' : 'Offline'}
                            </span>
                        </td>
                        <td>
                            <span class="ticket-count">${a.open_tickets || 0}</span>
                        </td>
                        <td>
                            <div class="performance-mini">
                                <span class="perf-item" title="Resolved this week"><i class="fa-solid fa-check"></i> ${resolved}</span>
                                <span class="perf-item" title="Avg response time"><i class="fa-solid fa-clock"></i> ${avgResponse}</span>
                            </div>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-icon" onclick="removeAgent(${a.id})" title="Remove agent">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');
        }
        
        function updateAgentStats(agents) {
            const total = agents.length;
            const online = agents.filter(a => a.is_online).length;
            const openTickets = agents.reduce((sum, a) => sum + (a.open_tickets || 0), 0);
            
            document.getElementById('totalAgentsCount').textContent = total;
            document.getElementById('onlineAgentsCount').textContent = online;
            document.getElementById('totalOpenTickets').textContent = openTickets;
            
            // Calculate average response time
            const agentsWithResponse = agents.filter(a => a.metrics?.avg_first_response_seconds > 0);
            if (agentsWithResponse.length > 0) {
                const avgSeconds = agentsWithResponse.reduce((sum, a) => sum + a.metrics.avg_first_response_seconds, 0) / agentsWithResponse.length;
                document.getElementById('avgResponseTime').textContent = formatDuration(avgSeconds);
            } else {
                document.getElementById('avgResponseTime').textContent = '-';
            }
        }
        
        function formatDuration(seconds) {
            if (!seconds || seconds < 60) return '<1m';
            if (seconds < 3600) return Math.round(seconds / 60) + 'm';
            if (seconds < 86400) return Math.round(seconds / 3600) + 'h';
            return Math.round(seconds / 86400) + 'd';
        }
        
        async function loadWPUsers() {
            try {
                const users = await api('GET', '/users/available');
                const select = document.getElementById('wpUserId');
                select.innerHTML = '<option value="">Select a user...</option>' + 
                    users.map(u => `<option value="${u.id}">${escapeHtml(u.display_name)} (${escapeHtml(u.email)})</option>`).join('');
            } catch (e) {
                console.error('Failed to load users');
            }
        }
        
        async function addAgent(event) {
            event.preventDefault();
            const userId = document.getElementById('wpUserId').value;
            const role = document.getElementById('agentRole').value;
            
            try {
                await api('POST', '/agents', { user_id: userId, role: role });
                closeModal('addAgentModal');
                showToast('Agent added!');
                loadAgents();
                loadWPUsers();
            } catch (e) {
                showToast('Failed to add agent', true);
            }
        }
        
        async function removeAgent(id) {
            if (!confirm('Remove this agent?')) return;
            
            try {
                await api('DELETE', '/agents/' + id);
                showToast('Agent removed');
                loadAgents();
            } catch (e) {
                showToast('Failed to remove agent', true);
            }
        }
        
        async function loadSessions() {
            try {
                const sessions = await api('GET', '/sessions');
                renderSessions(sessions);
            } catch (e) {
                document.getElementById('sessionsList').innerHTML = '<tr><td colspan="5" class="table-empty">Failed to load sessions</td></tr>';
            }
        }
        
        function renderSessions(sessions) {
            const tbody = document.getElementById('sessionsList');
            
            if (!sessions || sessions.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="table-empty">No active sessions</td></tr>';
                return;
            }
            
            tbody.innerHTML = sessions.map((s, i) => {
                const initials = getInitials(s.display_name || s.email);
                const color = colors[i % colors.length];
                const deviceIcon = getDeviceIcon(s.device || '');
                return `
                    <tr class="${s.is_current ? 'current-session' : ''}">
                        <td>
                            <div class="agent-cell">
                                <div class="agent-avatar avatar-color-${i % colors.length}">${initials}</div>
                                <div class="agent-details">
                                    <span class="agent-name">${escapeHtml(s.display_name || s.email)}</span>
                                    ${s.is_current ? '<span class="current-badge">Current Session</span>' : ''}
                                </div>
                            </div>
                        </td>
                        <td>
                            <div class="device-info">
                                <i class="fa-solid ${deviceIcon}"></i>
                                <span>${escapeHtml(s.device || 'Unknown device')}</span>
                            </div>
                        </td>
                        <td>
                            <span class="ip-address">${escapeHtml(s.ip_address || '-')}</span>
                        </td>
                        <td>
                            <span class="last-activity">${s.is_current ? 'Active now' : formatTimeAgo(s.last_activity)}</span>
                        </td>
                        <td>
                            ${s.is_current 
                                ? '' 
                                : '<button class="btn btn-outline btn-sm btn-danger-outline" onclick="revokeSession(\'' + s.id + '\', ' + s.user_id + ')"><i class="fa-solid fa-xmark"></i> Revoke</button>'}
                        </td>
                    </tr>
                `;
            }).join('');
        }
        
        function getDeviceIcon(device) {
            const d = device.toLowerCase();
            if (d.includes('mobile') || d.includes('iphone') || d.includes('android')) return 'fa-mobile-screen';
            if (d.includes('tablet') || d.includes('ipad')) return 'fa-tablet-screen-button';
            return 'fa-desktop';
        }
        
        async function revokeSession(sessionId, userId) {
            if (!confirm('Revoke this session? The user will be logged out.')) return;
            
            try {
                await api('DELETE', '/sessions/' + sessionId + '?user_id=' + userId);
                showToast('Session revoked');
                loadSessions();
            } catch (e) {
                showToast('Failed to revoke session', true);
            }
        }
        
        function toggleAutoAssign() {
            const isOn = document.getElementById('autoAssign').checked;
            const options = document.getElementById('assignmentOptions');
            if (isOn) {
                options.classList.remove('disabled-section');
            } else {
                options.classList.add('disabled-section');
            }
        }
        
        function updateAssignmentMethod(radio) {
            document.getElementById('assignmentMethod').value = radio.value;
            document.querySelectorAll('.method-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            radio.closest('.method-option').classList.add('selected');
        }
        
        function adjustMaxTickets(delta) {
            const input = document.getElementById('maxTickets');
            let val = parseInt(input.value) + delta;
            val = Math.max(1, Math.min(100, val));
            input.value = val;
        }
        
        async function saveSettings() {
            // Get session timeout from radio buttons
            const sessionTimeoutRadio = document.querySelector('input[name="sessionTimeout"]:checked');
            const sessionTimeout = sessionTimeoutRadio ? parseInt(sessionTimeoutRadio.value) : 86400;
            
            const settings = {
                portal_name: document.getElementById('portalName').value,
                support_email: document.getElementById('supportEmail').value,
                ticket_prefix: document.getElementById('ticketPrefix').value,
                default_priority: document.getElementById('defaultPriority').value,
                business_hours_enabled: document.getElementById('enableBusinessHours').checked,
                business_start: document.getElementById('businessStart').value,
                business_end: document.getElementById('businessEnd').value,
                auto_assign: document.getElementById('autoAssign').checked,
                assignment_method: document.getElementById('assignmentMethod').value,
                assign_online_only: document.getElementById('onlineOnly').checked,
                max_tickets_per_agent: parseInt(document.getElementById('maxTickets').value),
                email_new_ticket: document.getElementById('emailNewTicket').checked,
                email_agent_reply: document.getElementById('emailAgentReply').checked,
                email_resolved: document.getElementById('emailResolved').checked,
                session_timeout: sessionTimeout,
                webhook_key: document.getElementById('webhookKey').value
            };
            
            try {
                await api('POST', '/settings', settings);
                showToast('Settings saved!');
            } catch (e) {
                showToast('Failed to save settings', true);
            }
        }
        
        // ==========================================
        // Push Notification Functions
        // ==========================================
        
        function checkPushNotificationStatus() {
            const card = document.getElementById('pushNotificationCard');
            const icon = document.getElementById('pushCardIcon');
            const title = document.getElementById('pushCardTitle');
            const desc = document.getElementById('pushCardDesc');
            const actions = document.getElementById('pushCardActions');
            const options = document.getElementById('pushNotificationOptions');
            const promptBanner = document.getElementById('pushPermissionPrompt');
            
            if (!('Notification' in window)) {
                // Browser doesn't support notifications
                card.className = 'push-notification-card push-unsupported';
                icon.innerHTML = '<i class="fa-solid fa-circle-xmark"></i>';
                title.textContent = 'Not Supported';
                desc.textContent = 'Your browser does not support push notifications. Please use a modern browser like Chrome, Firefox, or Edge.';
                actions.innerHTML = '';
                promptBanner.classList.add('hidden');
                return;
            }
            
            const permission = Notification.permission;
            
            if (permission === 'granted') {
                // Notifications are enabled
                card.className = 'push-notification-card push-enabled';
                icon.innerHTML = '<i class="fa-solid fa-bell"></i>';
                title.textContent = 'Push Notifications Enabled';
                desc.textContent = 'You will receive browser notifications for new tickets and customer replies.';
                actions.innerHTML = '<button type="button" class="btn btn-outline" onclick="disablePushNotifications()"><i class="fa-solid fa-bell-slash"></i> Disable</button>';
                options.classList.remove('hidden');
                promptBanner.classList.add('hidden');
                loadPushPreferences();
            } else if (permission === 'denied') {
                // Notifications are blocked
                card.className = 'push-notification-card push-blocked';
                icon.innerHTML = '<i class="fa-solid fa-ban"></i>';
                title.textContent = 'Notifications Blocked';
                desc.textContent = 'You have blocked notifications for this site. To enable them, click the lock icon in your browser\'s address bar and allow notifications.';
                actions.innerHTML = `
                    <button type="button" class="btn btn-primary" onclick="showNotificationInstructions()">
                        <i class="fa-solid fa-circle-question"></i> How to Enable
                    </button>
                `;
                options.classList.add('hidden');
                promptBanner.classList.add('hidden');
            } else {
                // Permission not yet requested - show prompt banner
                card.className = 'push-notification-card push-disabled';
                icon.innerHTML = '<i class="fa-solid fa-bell-slash"></i>';
                title.textContent = 'Push Notifications Disabled';
                desc.textContent = 'Enable push notifications to receive instant alerts about new tickets and customer replies.';
                actions.innerHTML = `
                    <button type="button" class="btn btn-primary" onclick="triggerPushPermissionPrompt()">
                        <i class="fa-solid fa-bell"></i> Enable Notifications
                    </button>
                `;
                options.classList.add('hidden');
                // Show the prompt banner if not dismissed
                if (!localStorage.getItem('oversee_push_prompt_dismissed')) {
                    promptBanner.classList.remove('hidden');
                } else {
                    promptBanner.classList.add('hidden');
                }
            }
        }
        
        // Triggers the browser's native permission prompt
        async function triggerPushPermissionPrompt() {
            if (!('Notification' in window)) {
                showToast('Your browser does not support notifications', true);
                return;
            }
            
            // Hide the banner
            document.getElementById('pushPermissionPrompt').classList.add('hidden');
            
            try {
                // This will trigger the browser's native permission dialog
                const permission = await Notification.requestPermission();
                
                if (permission === 'granted') {
                    showToast('Push notifications enabled!');
                    // Save preference to server
                    await api('POST', '/settings/push-enabled', { enabled: true });
                    // Send a welcome notification
                    new Notification('Notifications Enabled! 🎉', {
                        body: 'You will now receive alerts for new tickets and replies.',
                        icon: '/wp-content/plugins/oversee-support/assets/img/icon-192.png'
                    });
                } else if (permission === 'denied') {
                    showToast('Notifications were blocked. You can enable them from your browser settings.', true);
                }
                
                checkPushNotificationStatus();
            } catch (e) {
                showToast('Failed to request notification permission', true);
                console.error('Push permission error:', e);
            }
        }
        
        function dismissPermissionPrompt() {
            localStorage.setItem('oversee_push_prompt_dismissed', 'true');
            document.getElementById('pushPermissionPrompt').classList.add('hidden');
        }
        
        async function requestPushPermission() {
            return triggerPushPermissionPrompt();
        }
        
        function disablePushNotifications() {
            // We can't programmatically revoke permission, but we can disable on our end
            api('POST', '/settings/push-enabled', { enabled: false }).then(() => {
                showToast('Push notifications disabled');
                document.getElementById('pushNotificationOptions').classList.add('hidden');
                checkPushNotificationStatus();
            }).catch(() => {
                showToast('Failed to disable notifications', true);
            });
        }
        
        async function savePushPreferences() {
            const prefs = {
                new_ticket: document.getElementById('pushNewTicket').checked,
                customer_reply: document.getElementById('pushCustomerReply').checked,
                assigned: document.getElementById('pushAssigned').checked
            };
            
            try {
                await api('POST', '/settings/push-preferences', prefs);
            } catch (e) {
                console.error('Failed to save push preferences');
            }
        }
        
        async function loadPushPreferences() {
            try {
                const prefs = await api('GET', '/settings/push-preferences');
                if (prefs) {
                    document.getElementById('pushNewTicket').checked = prefs.new_ticket !== false;
                    document.getElementById('pushCustomerReply').checked = prefs.customer_reply !== false;
                    document.getElementById('pushAssigned').checked = prefs.assigned !== false;
                }
            } catch (e) {
                // Use defaults
            }
        }
        
        function sendTestNotification() {
            if (Notification.permission !== 'granted') {
                showToast('Please enable notifications first', true);
                return;
            }
            
            new Notification('Test Notification', {
                body: 'This is a test notification from Oversee Support.',
                icon: '/wp-content/plugins/oversee-support/assets/img/icon-192.png',
                tag: 'test-notification'
            });
            
            showToast('Test notification sent!');
        }
        
        // Trigger browser permission prompt
        function triggerPushPermissionPrompt() {
            if (!('Notification' in window)) {
                showToast('Your browser does not support notifications', true);
                return;
            }
            
            // This will trigger the browser's native permission dialog
            Notification.requestPermission().then(permission => {
                if (permission === 'granted') {
                    showToast('Push notifications enabled!');
                    // Save preference to server
                    api('POST', '/settings/push-enabled', { enabled: true });
                    // Send welcome notification
                    new Notification('Notifications Enabled!', {
                        body: 'You will now receive alerts for new tickets and replies.',
                        icon: '/wp-content/plugins/oversee-support/assets/img/icon-192.png'
                    });
                    // Hide permission prompt banner
                    hidePermissionPrompt();
                } else if (permission === 'denied') {
                    showToast('Notifications blocked. See instructions to enable.', true);
                }
                checkPushNotificationStatus();
            }).catch(err => {
                console.error('Push permission error:', err);
                showToast('Failed to request permission', true);
            });
        }
        
        // Dismiss the permission prompt banner
        function dismissPermissionPrompt() {
            hidePermissionPrompt();
            // Store dismissal in localStorage so it doesn't show again this session
            localStorage.setItem('oversee_push_dismissed', 'true');
        }
        
        function hidePermissionPrompt() {
            const prompt = document.getElementById('pushPermissionPrompt');
            if (prompt) {
                prompt.classList.add('hidden');
            }
        }
        
        function showPermissionPromptIfNeeded() {
            const prompt = document.getElementById('pushPermissionPrompt');
            if (!prompt) return;
            
            // Check if browser supports notifications
            if (!('Notification' in window)) {
                prompt.classList.add('hidden');
                return;
            }
            
            // Check if already granted or denied
            if (Notification.permission !== 'default') {
                prompt.classList.add('hidden');
                return;
            }
            
            // Check if user dismissed it
            if (localStorage.getItem('oversee_push_dismissed') === 'true') {
                prompt.classList.add('hidden');
                return;
            }
            
            // Show the prompt
            prompt.classList.remove('hidden');
        }
        
        // Save push notification preferences
        async function savePushPreferences() {
            const prefs = {
                new_ticket: document.getElementById('pushNewTicket')?.checked ?? true,
                customer_reply: document.getElementById('pushCustomerReply')?.checked ?? true,
                assigned: document.getElementById('pushAssigned')?.checked ?? true
            };
            
            try {
                await api('POST', '/settings/push-preferences', prefs);
                showToast('Notification preferences saved');
            } catch (e) {
                showToast('Failed to save preferences', true);
            }
        }
        
        function showNotificationInstructions() {
            showModal('notificationInstructionsModal');
        }
        
        // Initialize timeout option selection
        function initTimeoutCards() {
            document.querySelectorAll('.timeout-option input[type="radio"]').forEach(radio => {
                radio.addEventListener('change', function() {
                    document.querySelectorAll('.timeout-option').forEach(card => {
                        card.classList.remove('selected');
                    });
                    if (this.checked) {
                        this.closest('.timeout-option').classList.add('selected');
                    }
                });
            });
        }
        
        function regenerateWebhookKey() {
            const key = 'whk_' + Math.random().toString(36).substr(2, 24);
            document.getElementById('webhookKey').value = key;
            showToast('New key generated - save to apply');
        }
        
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text);
            showToast('Copied to clipboard!');
        }
        
        function copyField(inputId) {
            const input = document.getElementById(inputId);
            if (input) {
                navigator.clipboard.writeText(input.value);
                showToast('Copied to clipboard!');
            }
        }
        
        // HighLevel Workflow Functions
        async function saveHlWorkflow() {
            const url = document.getElementById('hlWorkflowUrl').value.trim();
            const events = [];
            
            if (document.getElementById('hlEventCreated').checked) events.push('ticket_created');
            if (document.getElementById('hlEventReplied').checked) events.push('ticket_replied');
            if (document.getElementById('hlEventResolved').checked) events.push('ticket_resolved');
            if (document.getElementById('hlEventCustomerReply').checked) events.push('customer_replied');
            
            try {
                await api('POST', '/settings/hl-workflow', {
                    url: url,
                    events: events
                });
                showToast('HighLevel workflow settings saved!');
            } catch (e) {
                showToast('Failed to save workflow settings', true);
            }
        }
        
        async function testHlWorkflow() {
            const url = document.getElementById('hlWorkflowUrl').value.trim();
            
            if (!url) {
                showToast('Please enter a workflow URL first', true);
                return;
            }
            
            try {
                const result = await api('POST', '/settings/hl-workflow/test', { url: url });
                if (result.success) {
                    showToast('Test webhook sent successfully!');
                } else {
                    showToast('Test failed: ' + (result.message || 'Unknown error'), true);
                }
            } catch (e) {
                showToast('Failed to send test webhook', true);
            }
        }
        
        // API Helper
        async function api(method, endpoint, data = null) {
            const options = {
                method: method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': WP_NONCE
                }
            };
            if (data) options.body = JSON.stringify(data);
            const response = await fetch(REST_URL + endpoint, options);
            if (!response.ok) throw new Error('API Error');
            return response.json();
        }
        
        // Modal
        function showModal(id) { document.getElementById(id).classList.add('show'); }
        function closeModal(id) { document.getElementById(id).classList.remove('show'); }
        
        // Toast
        function showToast(msg, isError = false) {
            const toast = document.getElementById('toast');
            const icon = toast.querySelector('i');
            document.getElementById('toastMessage').textContent = msg;
            icon.className = isError ? 'fa-solid fa-exclamation-circle' : 'fa-solid fa-check-circle';
            toast.classList.toggle('error', isError);
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 3000);
        }
        
        // Utilities
        function getInitials(name) {
            if (!name) return '?';
            return name.split(' ').map(p => p[0]).join('').substring(0, 2).toUpperCase();
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatTimeAgo(dateStr) {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            const diff = Math.floor((new Date() - date) / 1000);
            if (diff < 60) return 'Just now';
            if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
            return Math.floor(diff / 86400) + 'd ago';
        }
        
        // KB Sync functions
        let syncPolling = null;
        
        async function startSync() {
            // Save URL first
            const url = document.getElementById('kbSourceUrl').value;
            await api('POST', '/settings', { static_kb_url: url });
            
            // Show progress
            document.getElementById('syncStatus').classList.add('visible');
            document.getElementById('syncBtn').disabled = true;
            document.getElementById('clearBtn').disabled = true;
            document.getElementById('syncMessage').textContent = 'Starting sync...';
            document.getElementById('syncPercent').textContent = '0%';
            updateProgressBar(0);
            
            // Start polling for progress
            syncPolling = setInterval(pollSyncProgress, 1000);
            
            // Start the sync
            try {
                const result = await api('POST', '/kb/sync');
                clearInterval(syncPolling);
                
                document.getElementById('syncMessage').textContent = 'Sync complete!';
                document.getElementById('syncPercent').textContent = '100%';
                updateProgressBar(100);
                
                showToast('KB sync completed successfully!');
                
                setTimeout(() => {
                    location.reload();
                }, 2000);
            } catch (e) {
                clearInterval(syncPolling);
                document.getElementById('syncMessage').textContent = 'Sync failed: ' + e.message;
                document.getElementById('syncBtn').disabled = false;
                document.getElementById('clearBtn').disabled = false;
                showToast('Sync failed', true);
            }
        }
        
        function updateProgressBar(percent) {
            const bar = document.getElementById('syncProgressBar');
            bar.style.setProperty('--progress', percent + '%');
            bar.style.width = percent + '%';
        }
        
        async function pollSyncProgress() {
            try {
                const progress = await api('GET', '/kb/sync/progress');
                if (progress && progress.status !== 'idle') {
                    document.getElementById('syncMessage').textContent = progress.message || progress.status;
                    document.getElementById('syncPercent').textContent = (progress.percent || 0) + '%';
                    updateProgressBar(progress.percent || 0);
                }
            } catch (e) {
                // Ignore polling errors
            }
        }
        
        async function clearKbData() {
            if (!confirm('This will delete ALL knowledge base articles and categories. Are you sure?')) {
                return;
            }
            
            document.getElementById('clearBtn').disabled = true;
            
            try {
                await api('POST', '/kb/sync/clear');
                showToast('All KB data cleared');
                setTimeout(() => location.reload(), 1000);
            } catch (e) {
                showToast('Failed to clear KB data', true);
                document.getElementById('clearBtn').disabled = false;
            }
        }
        
        async function clearKbCache() {
            document.getElementById('cacheBtn').disabled = true;
            
            try {
                await api('POST', '/kb/cache/clear');
                showToast('Cache cleared! Reloading...');
                setTimeout(() => location.reload(), 1000);
            } catch (e) {
                showToast('Failed to clear cache', true);
                document.getElementById('cacheBtn').disabled = false;
            }
        }
    </script>
</body>
</html>
