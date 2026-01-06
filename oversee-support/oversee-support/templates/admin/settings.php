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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - <?php echo esc_html(oversee_get_portal_title()); ?></title>
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
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3>Support Agents</h3>
                            <p>Manage who can access the support dashboard</p>
                        </div>
                        <button class="btn btn-primary btn-sm" onclick="showModal('addAgentModal')">
                            <i class="fa-solid fa-plus"></i> Add Agent
                        </button>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Agent</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Open Tickets</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="agentsList">
                                <tr>
                                    <td colspan="5">
                                        <div class="loading-state"><i class="fa-solid fa-spinner fa-spin"></i></div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Assignment Tab -->
            <div id="tab-assignment" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3>Auto-Assignment</h3>
                            <p>Automatically assign new tickets to agents</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="option-row">
                            <div class="option-info">
                                <h5>Enable auto-assignment</h5>
                                <p>Automatically assign incoming tickets to available agents</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" id="autoAssign" <?php checked($settings['auto_assign'] ?? false); ?> onchange="toggleAutoAssign()">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div id="assignmentOptions">
                            <div class="option-row">
                                <div class="option-info">
                                    <h5>Assignment method</h5>
                                    <p>How tickets are distributed among agents</p>
                                </div>
                                <select id="assignmentMethod">
                                    <option value="round_robin" <?php selected($settings['assignment_method'] ?? 'round_robin', 'round_robin'); ?>>Round Robin</option>
                                    <option value="least_busy" <?php selected($settings['assignment_method'] ?? 'round_robin', 'least_busy'); ?>>Least Busy</option>
                                    <option value="random" <?php selected($settings['assignment_method'] ?? 'round_robin', 'random'); ?>>Random</option>
                                </select>
                            </div>
                            
                            <div class="option-row">
                                <div class="option-info">
                                    <h5>Only assign to online agents</h5>
                                    <p>Skip agents who are marked as offline</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" id="onlineOnly" <?php checked($settings['assign_online_only'] ?? true); ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            
                            <div class="option-row">
                                <div class="option-info">
                                    <h5>Max tickets per agent</h5>
                                    <p>Don't assign more than this many open tickets</p>
                                </div>
                                <input type="number" id="maxTickets" value="<?php echo esc_attr($settings['max_tickets_per_agent'] ?? 20); ?>" min="1" max="100" style="width: 80px;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Notifications Tab -->
            <div id="tab-notifications" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3>Email Notifications</h3>
                            <p>Configure when emails are sent</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="option-row">
                            <div class="option-info">
                                <h5>New ticket confirmation</h5>
                                <p>Send email to customer when ticket is created</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" id="emailNewTicket" <?php checked($settings['email_new_ticket'] ?? true); ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="option-row">
                            <div class="option-info">
                                <h5>Agent reply notification</h5>
                                <p>Notify customer when an agent replies</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" id="emailAgentReply" <?php checked($settings['email_agent_reply'] ?? true); ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="option-row">
                            <div class="option-info">
                                <h5>Ticket resolved notification</h5>
                                <p>Notify customer when ticket is marked resolved</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" id="emailResolved" <?php checked($settings['email_resolved'] ?? true); ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Integrations Tab -->
            <div id="tab-integrations" class="tab-content">
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3>Incoming Webhooks</h3>
                            <p>Create tickets from external systems</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-group">
                            <label>Your Webhook URL</label>
                            <div class="form-row">
                                <input type="text" value="<?php echo esc_url(rest_url('oversee/v1/webhook/incoming')); ?>" readonly style="background: #f9fafb;">
                                <button class="btn btn-secondary" onclick="copyToClipboard(this.previousElementSibling.value)"><i class="fa-solid fa-copy"></i></button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>Secret Key</label>
                            <div class="form-row">
                                <input type="text" id="webhookKey" value="<?php echo esc_attr($settings['webhook_key'] ?? wp_generate_password(24, false)); ?>" readonly style="background: #f9fafb; font-family: monospace;">
                                <button class="btn btn-secondary" onclick="regenerateWebhookKey()"><i class="fa-solid fa-rotate"></i> Regenerate</button>
                            </div>
                            <p class="form-help">Include this in the X-Webhook-Key header</p>
                        </div>
                    </div>
                </div>
            
                
                <div class="card" style="margin-top: 20px;">
                    <div class="card-header">
                        <div>
                            <h3>Knowledge Base Sync</h3>
                            <p>Import articles from static HTML files</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="info-banner" id="kbSyncStatus" style="margin-bottom:15px;display:none;"><i class="fa-solid fa-circle-info"></i><p id="kbSyncMessage">Last sync: Never</p></div>
                        
                        <!-- Progress Container -->
                        <div id="kbProgressContainer" style="display:none; margin-bottom: 20px; padding: 16px; background: #f8fafc; border-radius: 8px; border: 1px solid #e2e8f0;">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                <span id="kbProgressPhase" style="font-weight: 600; font-size: 13px; color: #1e293b;">Initializing...</span>
                                <span id="kbProgressCount" style="font-weight: 700; font-size: 14px; color: #ea580c; font-family: monospace;">0%</span>
                            </div>
                            <div style="background: #e2e8f0; border-radius: 6px; height: 10px; overflow: hidden; margin-bottom: 10px;">
                                <div id="kbProgressBar" style="background: linear-gradient(90deg, #ea580c 0%, #f97316 100%); height: 100%; width: 0%; transition: width 0.3s ease;"></div>
                            </div>
                            <div id="kbProgressMessage" style="font-size: 12px; color: #64748b; margin-bottom: 6px;"></div>
                            <div id="kbProgressStats" style="font-size: 11px; color: #94a3b8; font-family: monospace;"></div>
                        </div>
                        
                        <div class="form-group">
                            <label>Source URL</label>
                            <input type="text" id="kbSourceUrl" value="<?php echo esc_attr(get_option('oversee_static_kb_url', 'https://overseecrm.com/wp-content/uploads/knowledge-base')); ?>" placeholder="https://example.com/knowledge-base">
                            <p class="form-help">URL where your static HTML knowledge base files are hosted</p>
                        </div>
                        <div class="form-row" style="gap:10px;">
                            <button class="btn btn-primary" id="syncKbBtn" onclick="syncKnowledgeBase()"><i class="fa-solid fa-sync"></i> Sync Now</button>
                            <button class="btn btn-secondary" id="clearKbBtn" onclick="clearKbData(event)"><i class="fa-solid fa-trash"></i> Clear All KB Data</button>
                        </div>
                        <div id="kbSyncResult" style="margin-top:15px;display:none;"></div>
                    </div>
                </div>
            </div>

            <!-- Security Tab -->
            <div id="tab-security" class="tab-content">
                <div class="info-box warning-box">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <p>Authentication is handled by WordPress. Agents must have a WordPress user account with the appropriate role to access the dashboard.</p>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3>Login Settings</h3>
                            <p>Configure authentication behavior</p>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="option-row">
                            <div class="option-info">
                                <h5>Session timeout</h5>
                                <p>Automatically log out after inactivity</p>
                            </div>
                            <select id="sessionTimeout">
                                <option value="3600" <?php selected($settings['session_timeout'] ?? 86400, 3600); ?>>1 hour</option>
                                <option value="14400" <?php selected($settings['session_timeout'] ?? 86400, 14400); ?>>4 hours</option>
                                <option value="86400" <?php selected($settings['session_timeout'] ?? 86400, 86400); ?>>24 hours</option>
                                <option value="604800" <?php selected($settings['session_timeout'] ?? 86400, 604800); ?>>7 days</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <div>
                            <h3>Active Sessions</h3>
                            <p>Currently logged in agents</p>
                        </div>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <table>
                            <thead>
                                <tr>
                                    <th>Agent</th>
                                    <th>Device</th>
                                    <th>Last Activity</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="sessionsList">
                                <tr>
                                    <td colspan="4">
                                        <div class="loading-state"><i class="fa-solid fa-spinner fa-spin"></i></div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Add Agent Modal -->
    <div class="modal" id="addAgentModal">
        <div class="modal-backdrop" onclick="closeModal('addAgentModal')"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Agent</h3>
                <button class="modal-close" onclick="closeModal('addAgentModal')"><i class="fa-solid fa-times"></i></button>
            </div>
            <form id="addAgentForm" onsubmit="addAgent(event)">
                <div class="modal-body">
                    <div class="form-group">
                        <label>Select WordPress User</label>
                        <select id="wpUserId" required>
                            <option value="">Select a user...</option>
                        </select>
                        <p class="form-help">Only users not already agents are shown</p>
                    </div>
                    <div class="form-group">
                        <label>Role</label>
                        <select id="agentRole">
                            <option value="agent">Agent</option>
                            <option value="admin">Administrator</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('addAgentModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Add Agent</button>
                </div>
            </form>
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
            loadKbSyncStatus();
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
            } catch (e) {
                document.getElementById('agentsList').innerHTML = '<tr><td colspan="5">Failed to load agents</td></tr>';
            }
        }
        
        function renderAgents(agents) {
            const tbody = document.getElementById('agentsList');
            
            if (!agents || agents.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:40px;">No agents yet. Add your first agent above.</td></tr>';
                return;
            }
            
            tbody.innerHTML = agents.map((a, i) => {
                const initials = getInitials(a.display_name || a.email);
                const color = colors[i % colors.length];
                return `
                    <tr>
                        <td>
                            <div class="agent-info">
                                <div class="avatar" style="background:${color}">
                                    ${initials}
                                    <span class="status-dot ${a.is_online ? 'online' : 'offline'}"></span>
                                </div>
                                <div>
                                    <div class="agent-name">${escapeHtml(a.display_name || 'No name')}</div>
                                    <div class="agent-email">${escapeHtml(a.email)}</div>
                                </div>
                            </div>
                        </td>
                        <td>${a.is_admin ? 'Administrator' : 'Agent'}</td>
                        <td><span class="status-badge ${a.is_online ? 'online' : 'offline'}">${a.is_online ? 'Online' : 'Offline'}</span></td>
                        <td>${a.open_tickets || 0}</td>
                        <td>
                            <button class="btn btn-secondary btn-sm btn-danger" onclick="removeAgent(${a.id})">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');
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
                document.getElementById('sessionsList').innerHTML = '<tr><td colspan="4">Failed to load sessions</td></tr>';
            }
        }
        
        function renderSessions(sessions) {
            const tbody = document.getElementById('sessionsList');
            
            if (!sessions || sessions.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;padding:20px;">No active sessions</td></tr>';
                return;
            }
            
            tbody.innerHTML = sessions.map((s, i) => {
                const initials = getInitials(s.display_name || s.email);
                const color = colors[i % colors.length];
                return `
                    <tr>
                        <td>
                            <div class="agent-info">
                                <div class="avatar" style="background:${color}">${initials}</div>
                                <div class="agent-name">${escapeHtml(s.display_name || s.email)}</div>
                            </div>
                        </td>
                        <td>${escapeHtml(s.device || 'Unknown')}</td>
                        <td>${s.is_current ? 'Just now' : formatTimeAgo(s.last_activity)}</td>
                        <td>
                            ${s.is_current 
                                ? '<span style="color:#10b981;font-size:11px;font-weight:500;">Current</span>' 
                                : '<button class="btn btn-secondary btn-sm btn-danger" onclick="revokeSession(\'' + s.id + '\')">Revoke</button>'}
                        </td>
                    </tr>
                `;
            }).join('');
        }
        
        function toggleAutoAssign() {
            const isOn = document.getElementById('autoAssign').checked;
            const options = document.getElementById('assignmentOptions');
            options.style.opacity = isOn ? '1' : '0.5';
            options.style.pointerEvents = isOn ? 'auto' : 'none';
        }
        
        async function saveSettings() {
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
                session_timeout: parseInt(document.getElementById('sessionTimeout').value),
                webhook_key: document.getElementById('webhookKey').value
            };
            
            try {
                await api('POST', '/settings', settings);
                showToast('Settings saved!');
            } catch (e) {
                showToast('Failed to save settings', true);
            }
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

        // ==========================================
        // KB SYNC FUNCTIONS WITH LIVE PROGRESS
        // ==========================================
        
        var kbSyncPolling = null;
        
        function syncKnowledgeBase() {
            var btn = document.getElementById("syncKbBtn");
            var progressContainer = document.getElementById("kbProgressContainer");
            var progressBar = document.getElementById("kbProgressBar");
            var progressPhase = document.getElementById("kbProgressPhase");
            var progressCount = document.getElementById("kbProgressCount");
            var progressMessage = document.getElementById("kbProgressMessage");
            var progressStats = document.getElementById("kbProgressStats");
            var result = document.getElementById("kbSyncResult");
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Starting...';
            progressContainer.style.display = "block";
            progressBar.style.width = "0%";
            progressPhase.textContent = "Initializing...";
            progressCount.textContent = "";
            progressMessage.textContent = "";
            progressStats.textContent = "";
            result.style.display = "none";
            
            var url = document.getElementById("kbSourceUrl").value;
            fetch(REST_URL + "/settings", {
                method: "PUT",
                headers: {"Content-Type": "application/json", "X-WP-Nonce": WP_NONCE},
                body: JSON.stringify({oversee_static_kb_url: url})
            });
            
            kbSyncPolling = setInterval(pollProgress, 500);
            
            fetch(REST_URL + "/kb/sync", {method: "POST", headers: {"X-WP-Nonce": WP_NONCE}})
            .then(function(r) { return r.json(); })
            .then(function(d) {
                clearInterval(kbSyncPolling);
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-sync"></i> Sync Now';
                
                if (d.success && d.stats) {
                    var s = d.stats;
                    progressBar.style.width = "100%";
                    progressPhase.textContent = "✓ Complete!";
                    progressCount.textContent = "";
                    
                    var added = s.articles_added || 0;
                    var updated = s.articles_updated || 0;
                    var failed = s.articles_failed || 0;
                    var total = s.total_discovered || (added + updated);
                    
                    progressMessage.textContent = added + " added, " + updated + " updated" + (failed > 0 ? ", " + failed + " failed" : "");
                    progressStats.textContent = "";
                    
                    result.innerHTML = '<div class="success-box"><i class="fa-solid fa-check-circle"></i> Sync complete! Imported ' + (added + updated) + ' of ' + total + ' articles.</div>';
                    result.style.display = "block";
                } else {
                    result.innerHTML = '<div class="error-box"><i class="fa-solid fa-times-circle"></i> Sync failed. Check console for details.</div>';
                    result.style.display = "block";
                }
                loadKbSyncStatus();
            })
            .catch(function(err) {
                clearInterval(kbSyncPolling);
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-sync"></i> Sync Now';
                result.innerHTML = '<div class="error-box"><i class="fa-solid fa-times-circle"></i> Error: ' + (err.message || 'Request failed') + '</div>';
                result.style.display = "block";
            });
        }
        
        function pollProgress() {
            fetch(REST_URL + "/kb/sync/progress", {headers: {"X-WP-Nonce": WP_NONCE}})
            .then(function(r) { return r.json(); })
            .then(function(p) {
                if (!p || p.status === 'idle') return;
                
                var progressBar = document.getElementById("kbProgressBar");
                var progressPhase = document.getElementById("kbProgressPhase");
                var progressCount = document.getElementById("kbProgressCount");
                var progressMessage = document.getElementById("kbProgressMessage");
                var progressStats = document.getElementById("kbProgressStats");
                var btn = document.getElementById("syncKbBtn");
                
                var percent = p.percent || 0;
                progressBar.style.width = percent + "%";
                
                var phaseLabels = {
                    'discovering': '🔍 Discovering Articles',
                    'discovered': '✓ Discovery Complete',
                    'categories': '📁 Creating Categories',
                    'articles': '📄 Importing Articles',
                    'retrying': '🔄 Retrying Failed',
                    'finalizing': '⚡ Finalizing',
                    'complete': '✓ Complete!',
                    'error': '❌ Error'
                };
                progressPhase.textContent = phaseLabels[p.status] || p.status;
                
                if (p.current && p.total) {
                    progressCount.textContent = p.current + " / " + p.total;
                } else {
                    progressCount.textContent = percent + "%";
                }
                
                progressMessage.textContent = p.message || "";
                
                if (p.stats) {
                    var s = p.stats;
                    var statsText = "";
                    if (s.articles_added > 0) statsText += "Added: " + s.articles_added;
                    if (s.articles_updated > 0) statsText += (statsText ? " | " : "") + "Updated: " + s.articles_updated;
                    if (s.articles_failed > 0) statsText += (statsText ? " | " : "") + "Failed: " + s.articles_failed;
                    if (s.total_discovered > 0) statsText += (statsText ? " | " : "") + "Total: " + s.total_discovered;
                    progressStats.textContent = statsText;
                }
                
                if (p.current && p.total && p.status === 'articles') {
                    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> ' + p.current + '/' + p.total;
                }
            })
            .catch(function() {});
        }
        
        function clearKbData(e) {
            if (!confirm("Delete ALL KB articles and categories? This cannot be undone.")) return;
            
            var btn = document.getElementById('clearKbBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Clearing...';
            
            fetch(REST_URL + "/kb/sync/clear", {method: "POST", headers: {"X-WP-Nonce": WP_NONCE}})
            .then(function(r) { return r.json(); })
            .then(function() {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-trash"></i> Clear All KB Data';
                document.getElementById("kbSyncResult").innerHTML = '<div class="success-box"><i class="fa-solid fa-check-circle"></i> All KB data cleared.</div>';
                document.getElementById("kbSyncResult").style.display = "block";
                document.getElementById("kbProgressContainer").style.display = "none";
                loadKbSyncStatus();
            })
            .catch(function() {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-trash"></i> Clear All KB Data';
                alert("Failed to clear KB data");
            });
        }
        
        function loadKbSyncStatus() {
            fetch(REST_URL + "/kb/sync/status", {headers: {"X-WP-Nonce": WP_NONCE}})
            .then(function(r) { return r.json(); })
            .then(function(d) {
                var el = document.getElementById("kbSyncStatus");
                var msg = document.getElementById("kbSyncMessage");
                if (d.last_sync) {
                    el.style.display = "flex";
                    msg.innerHTML = "Last sync: " + d.last_sync + " | Articles: " + (d.total_articles || 0) + " | Categories: " + (d.total_categories || 0);
                } else {
                    el.style.display = "none";
                }
            })
            .catch(function() {});
        }
    </script>
</body>
</html>