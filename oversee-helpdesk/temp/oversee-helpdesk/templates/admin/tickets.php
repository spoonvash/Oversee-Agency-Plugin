<?php
/**
 * Admin Tickets Page
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
$branding = Oversee_Branding::get_for_context('admin');
$favicon = Oversee_Branding::get('favicon_url');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets - <?php echo esc_html($branding['company_name'] ?: oversee_get_portal_title()); ?></title>
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
            <h2>Tickets</h2>
            <div class="topbar-right">
                <button class="btn btn-primary" onclick="showModal('newTicketModal')">
                    <i class="fa-solid fa-plus"></i> New Ticket
                </button>
            </div>
        </div>
        
        <div class="content-wrapper">
            <!-- Stats Row -->
            <div class="stats-row">
                <div class="stat-card high">
                    <div><div class="stat-value" id="statHigh">0</div><div class="stat-label">High Priority</div></div>
                </div>
                <div class="stat-card new">
                    <div><div class="stat-value" id="statNew">0</div><div class="stat-label">New</div></div>
                </div>
                <div class="stat-card open">
                    <div><div class="stat-value" id="statOpen">0</div><div class="stat-label">Open</div></div>
                </div>
                <div class="stat-card resolved">
                    <div><div class="stat-value" id="statResolved">0</div><div class="stat-label">Resolved</div></div>
                </div>
            </div>
            
            <!-- Ticket Container -->
            <div class="ticket-container" id="ticketContainer">
                <!-- Ticket List Panel -->
                <div class="ticket-list-panel">
                    <!-- Filters -->
                    <div class="ticket-filters">
                        <div class="search-box">
                            <i class="fa-solid fa-search"></i>
                            <input type="text" id="searchInput" placeholder="Search tickets..." onkeyup="handleSearch(event)">
                        </div>
                        <select class="filter-select" id="statusFilter" onchange="loadTickets()">
                            <option value="">All Status</option>
                            <option value="new">New</option>
                            <option value="open">Open</option>
                            <option value="pending">Pending</option>
                            <option value="resolved">Resolved</option>
                        </select>
                    </div>
                    
                    <!-- Bulk Actions -->
                    <div class="bulk-actions" id="bulkActions">
                        <label class="bulk-select-all checkbox-wrapper">
                            <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                            Select All
                        </label>
                        <span class="bulk-count" id="selectedCount">0 selected</span>
                        <div class="bulk-dropdown">
                            <button class="bulk-dropdown-btn" onclick="toggleDropdown()">
                                Actions <i class="fa-solid fa-chevron-down"></i>
                            </button>
                            <div class="bulk-dropdown-menu" id="bulkMenu">
                                <div class="bulk-dropdown-item" onclick="bulkAction('open')"><i class="fa-solid fa-folder-open"></i> Mark Open</div>
                                <div class="bulk-dropdown-item" onclick="bulkAction('pending')"><i class="fa-solid fa-clock"></i> Mark Pending</div>
                                <div class="bulk-dropdown-item" onclick="bulkAction('resolved')"><i class="fa-solid fa-check"></i> Mark Resolved</div>
                                <div class="bulk-divider"></div>
                                <div class="bulk-dropdown-item" onclick="bulkAction('high')"><i class="fa-solid fa-flag"></i> Set High Priority</div>
                                <div class="bulk-dropdown-item" onclick="bulkAction('low')"><i class="fa-solid fa-flag"></i> Set Low Priority</div>
                                <div class="bulk-divider"></div>
                                <div class="bulk-dropdown-item danger" onclick="bulkAction('delete')"><i class="fa-solid fa-trash"></i> Delete</div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Ticket List -->
                    <div class="ticket-list" id="ticketList">
                        <div class="loading-state">
                            <i class="fa-solid fa-spinner fa-spin"></i>
                            <span>Loading tickets...</span>
                        </div>
                    </div>
                </div>
                
                <!-- Ticket Detail Panel -->
                <div class="ticket-detail-panel" id="ticketDetailPanel">
                    <div class="no-ticket-selected">
                        <i class="fa-solid fa-ticket"></i>
                        <p>Select a ticket to view details</p>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- New Ticket Modal -->
    <div class="modal" id="newTicketModal">
        <div class="modal-backdrop" onclick="closeModal('newTicketModal')"></div>
        <div class="modal-content">
            <div class="modal-header">
                <h3>Create New Ticket</h3>
                <button class="modal-close" onclick="closeModal('newTicketModal')"><i class="fa-solid fa-times"></i></button>
            </div>
            <form id="newTicketForm" onsubmit="createTicket(event)">
                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Customer Name</label>
                            <input type="text" id="customerName" name="customer_name">
                        </div>
                        <div class="form-group">
                            <label>Customer Email *</label>
                            <input type="email" id="customerEmail" name="customer_email" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Subject *</label>
                        <input type="text" id="ticketSubject" name="subject" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea id="ticketDescription" name="description" rows="4"></textarea>
                    </div>
                    <div class="form-group">
                        <label>Priority</label>
                        <select id="ticketPriority" name="priority">
                            <option value="normal">Normal</option>
                            <option value="high">High</option>
                            <option value="low">Low</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('newTicketModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Create Ticket</button>
                </div>
            </form>
        </div>
    </div>
    
    <div class="toast" id="toast"><i class="fa-solid fa-check-circle"></i><span id="toastMessage"></span></div>
    
    <!-- Notification Banner for New Tickets -->
    <div class="notification-banner" id="notificationBanner" onclick="dismissNotification()">
        <div class="notification-content">
            <div class="notification-icon"><i class="fa-solid fa-bell"></i></div>
            <div class="notification-text">
                <strong id="notificationTitle">New Ticket</strong>
                <span id="notificationBody">You have new tickets</span>
            </div>
            <button class="notification-close" onclick="dismissNotification(); event.stopPropagation();"><i class="fa-solid fa-times"></i></button>
        </div>
    </div>
    
    <script>
        const WP_NONCE = '<?php echo wp_create_nonce('wp_rest'); ?>';
        const REST_URL = '<?php echo esc_url(rest_url('oversee/v1')); ?>';
        const UPLOAD_URL = '<?php echo esc_url(rest_url('oversee/v1/upload')); ?>';
        const AGENT_ID = <?php echo $agent ? intval($agent->id) : 'null'; ?>;
        const DEBUG_MODE = true; // Enable detailed logging
        
        // Debug logger
        function debugLog(...args) {
            if (DEBUG_MODE) {
                console.log('[Oversee Debug]', ...args);
            }
        }
        
        // Test upload endpoint
        async function testUploadEndpoint() {
            debugLog('Testing upload endpoint:', UPLOAD_URL);
            try {
                const testBlob = new Blob(['test'], { type: 'text/plain' });
                const formData = new FormData();
                formData.append('file', testBlob, 'test.txt');
                
                const response = await fetch(UPLOAD_URL, {
                    method: 'POST',
                    headers: { 'X-WP-Nonce': WP_NONCE },
                    body: formData
                });
                
                const result = await response.json();
                debugLog('Upload test result:', response.status, result);
                
                if (result.success) {
                    console.log('✅ Upload endpoint working!');
                    return true;
                } else {
                    console.error('❌ Upload failed:', result.error || result.message);
                    return false;
                }
            } catch (e) {
                console.error('❌ Upload endpoint error:', e);
                return false;
            }
        }
        
        // Agent profile for calendar/zoom links
        const agentProfile = {
            name: '<?php echo esc_js($current_user->display_name); ?>',
            calendarLink: '<?php echo esc_js(get_user_meta($current_user->ID, 'oversee_calendar_link', true)); ?>',
            zoomLink: '<?php echo esc_js(get_user_meta($current_user->ID, 'oversee_zoom_link', true)); ?>'
        };
        
        let selectedTickets = new Set();
        let currentTicket = null;
        let agents = [];
        let searchTimeout = null;
        let pollInterval = null;
        let notificationsEnabled = false;
        
        // Simple counters for detecting new items
        let initialLoadComplete = false;
        let lastTotalTickets = 0;
        let lastReplyCount = 0;
        
        // Notification audio - initialized on first user click
        let notificationAudio = null;
        
        // ============================================================
        // NOTIFICATION SYSTEM - SIMPLE AND BULLETPROOF
        // ============================================================
        
        // Initialize sound (called on first click)
        function initSound() {
            if (notificationAudio) return;
            try {
                // Simple beep sound
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioCtx.createOscillator();
                const gainNode = audioCtx.createGain();
                oscillator.connect(gainNode);
                gainNode.connect(audioCtx.destination);
                oscillator.frequency.value = 800;
                gainNode.gain.value = 0.3;
                oscillator.start();
                oscillator.stop(audioCtx.currentTime + 0.2);
                notificationAudio = audioCtx;
                console.log('[Notifications] Sound initialized');
            } catch (e) {
                console.log('[Notifications] Sound init failed:', e);
            }
        }
        
        // Play notification sound
        function playSound() {
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const oscillator = audioCtx.createOscillator();
                const gainNode = audioCtx.createGain();
                oscillator.connect(gainNode);
                gainNode.connect(audioCtx.destination);
                oscillator.type = 'sine';
                oscillator.frequency.setValueAtTime(880, audioCtx.currentTime);
                oscillator.frequency.setValueAtTime(988, audioCtx.currentTime + 0.1);
                oscillator.frequency.setValueAtTime(1047, audioCtx.currentTime + 0.2);
                gainNode.gain.setValueAtTime(0.3, audioCtx.currentTime);
                gainNode.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.5);
                oscillator.start(audioCtx.currentTime);
                oscillator.stop(audioCtx.currentTime + 0.5);
            } catch (e) {
                console.log('[Notifications] Sound failed:', e);
            }
        }
        
        // Show the notification banner - ALWAYS VISIBLE
        function showNotificationBanner(title, message) {
            console.log('[Notifications] SHOWING BANNER:', title, message);
            
            const banner = document.getElementById('notificationBanner');
            const titleEl = document.getElementById('notificationTitle');
            const bodyEl = document.getElementById('notificationBody');
            
            if (!banner || !titleEl || !bodyEl) {
                console.error('[Notifications] Banner elements not found!');
                alert(title + '\n' + message); // Fallback
                return;
            }
            
            titleEl.textContent = title;
            bodyEl.textContent = message;
            banner.classList.add('show');
            
            // Flash browser title
            flashBrowserTitle(title);
            
            // Browser notification (if permitted)
            if (notificationsEnabled && 'Notification' in window) {
                try {
                    new Notification(title, { body: message });
                } catch (e) {}
            }
            
            // Auto-hide after 10 seconds
            setTimeout(() => {
                banner.classList.remove('show');
            }, 10000);
        }
        
        // Dismiss notification
        function dismissNotification() {
            const banner = document.getElementById('notificationBanner');
            if (banner) banner.classList.remove('show');
            stopTitleFlash();
        }
        
        // Flash browser title
        let titleFlashInterval = null;
        const pageTitle = 'Tickets - Oversee';
        
        function flashBrowserTitle(msg) {
            stopTitleFlash();
            let show = true;
            titleFlashInterval = setInterval(() => {
                document.title = show ? '🔔 ' + msg : pageTitle;
                show = !show;
            }, 1000);
            
            // Auto-stop after 30 seconds
            setTimeout(stopTitleFlash, 30000);
        }
        
        function stopTitleFlash() {
            if (titleFlashInterval) {
                clearInterval(titleFlashInterval);
                titleFlashInterval = null;
                document.title = pageTitle;
            }
        }
        
        // Stop flash when window gets focus
        window.addEventListener('focus', stopTitleFlash);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) stopTitleFlash();
        });
        
        // ============================================================
        // POLLING SYSTEM - CHECKS FOR NEW TICKETS AND REPLIES
        // ============================================================
        
        function startPolling() {
            console.log('[Polling] Started - checking every 5 seconds');
            
            pollInterval = setInterval(async () => {
                try {
                    // Get current stats
                    const stats = await api('GET', '/tickets/stats');
                    const currentTotal = (stats.total || 0);
                    
                    // Update UI
                    document.getElementById('statHigh').textContent = stats.high_priority || 0;
                    document.getElementById('statNew').textContent = stats.new || 0;
                    document.getElementById('statOpen').textContent = stats.open || 0;
                    document.getElementById('statResolved').textContent = stats.resolved || 0;
                    
                    // Check for new tickets (only after initial load)
                    if (initialLoadComplete && currentTotal > lastTotalTickets) {
                        const newCount = currentTotal - lastTotalTickets;
                        console.log('[Polling] NEW TICKETS DETECTED:', newCount);
                        showNotificationBanner(
                            '🎫 New Ticket!',
                            newCount === 1 ? 'A new support ticket has arrived!' : newCount + ' new tickets have arrived!'
                        );
                        loadTickets(); // Refresh list
                    }
                    lastTotalTickets = currentTotal;
                    
                    // Check for new replies on current ticket
                    if (currentTicket) {
                        const ticket = await api('GET', '/tickets/' + currentTicket);
                        const replyCount = (ticket.replies || []).length;
                        
                        if (lastReplyCount > 0 && replyCount > lastReplyCount) {
                            const latestReply = ticket.replies[ticket.replies.length - 1];
                            // Only notify for customer replies (not our own)
                            if (latestReply && latestReply.author_type === 'customer') {
                                console.log('[Polling] NEW CUSTOMER REPLY DETECTED');
                                showNotificationBanner(
                                    '💬 New Reply!',
                                    (ticket.customer_name || 'Customer') + ' replied to #' + ticket.ticket_number
                                );
                            }
                            // Refresh conversation
                            const conversationArea = document.getElementById('conversationArea');
                            if (conversationArea) {
                                conversationArea.innerHTML = renderConversation(ticket);
                                conversationArea.scrollTop = conversationArea.scrollHeight;
                            }
                        }
                        lastReplyCount = replyCount;
                    }
                    
                    // Update sidebar badge
                    const openCount = (stats.new || 0) + (stats.open || 0);
                    const badge = document.getElementById('ticketBadge');
                    if (badge) {
                        badge.textContent = openCount;
                        badge.style.display = openCount > 0 ? '' : 'none';
                    }
                    
                } catch (e) {
                    console.error('[Polling] Error:', e);
                }
            }, 5000);
        }
        
        // ============================================================
        // INITIALIZATION
        // ============================================================
        
        document.addEventListener('DOMContentLoaded', async function() {
            console.log('[Init] Page loaded');
            
            // Enable sound on first interaction
            document.addEventListener('click', initSound, { once: true });
            document.addEventListener('keydown', initSound, { once: true });
            
            // Request notification permission
            if ('Notification' in window) {
                if (Notification.permission === 'default') {
                    Notification.requestPermission().then(p => {
                        notificationsEnabled = (p === 'granted');
                        console.log('[Init] Notification permission:', p);
                    });
                } else {
                    notificationsEnabled = (Notification.permission === 'granted');
                }
            }
            
            // Load initial data
            await loadStats();
            await loadTickets();
            loadAgents();
            loadCannedResponses();
            
            // Get initial ticket count
            try {
                const stats = await api('GET', '/tickets/stats');
                lastTotalTickets = stats.total || 0;
                console.log('[Init] Initial ticket count:', lastTotalTickets);
            } catch (e) {}
            
            // Mark initial load complete - notifications will fire after this
            initialLoadComplete = true;
            console.log('[Init] Initial load complete - notifications enabled');
            
            // Check URL for ticket ID
            const urlParams = new URLSearchParams(window.location.search);
            const ticketId = urlParams.get('id');
            if (ticketId) {
                setTimeout(() => selectTicket(parseInt(ticketId)), 500);
            }
            
            // Close dropdown on outside click
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.bulk-dropdown')) {
                    document.getElementById('bulkMenu').classList.remove('show');
                }
            });
            
            // Start polling
            startPolling();
        });
        
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
        
        // Load Stats
        async function loadStats() {
            try {
                const stats = await api('GET', '/tickets/stats');
                document.getElementById('statHigh').textContent = stats.high_priority || 0;
                document.getElementById('statNew').textContent = stats.new || 0;
                document.getElementById('statOpen').textContent = stats.open || 0;
                document.getElementById('statResolved').textContent = stats.resolved || 0;
                
                // Update sidebar badge
                const openCount = (stats.new || 0) + (stats.open || 0);
                const badge = document.getElementById('ticketBadge');
                if (badge) {
                    badge.textContent = openCount;
                    badge.style.display = openCount > 0 ? '' : 'none';
                }
            } catch (e) {
                console.error('Failed to load stats', e);
            }
        }
        
        // Load Tickets
        async function loadTickets() {
            const container = document.getElementById('ticketList');
            container.innerHTML = '<div class="loading-state"><i class="fa-solid fa-spinner fa-spin"></i><span>Loading...</span></div>';
            
            try {
                const status = document.getElementById('statusFilter').value;
                const search = document.getElementById('searchInput').value;
                
                let url = '/tickets?per_page=50';
                if (status) url += '&status=' + status;
                if (search) url += '&search=' + encodeURIComponent(search);
                
                const data = await api('GET', url);
                renderTicketList(data.tickets || data);
            } catch (e) {
                container.innerHTML = '<div class="empty-state"><i class="fa-solid fa-exclamation-circle"></i><h4>Error loading tickets</h4></div>';
            }
        }
        
        // Load Agents
        async function loadAgents() {
            try {
                agents = await api('GET', '/agents');
            } catch (e) {
                agents = [];
            }
        }
        
        // Render Ticket List
        function renderTicketList(tickets) {
            const container = document.getElementById('ticketList');
            
            if (!tickets || tickets.length === 0) {
                container.innerHTML = '<div class="empty-state"><i class="fa-solid fa-inbox"></i><h4>No tickets found</h4><p>Create a new ticket to get started</p></div>';
                return;
            }
            
            container.innerHTML = tickets.map(t => `
                <div class="ticket-item ${currentTicket?.id === t.id ? 'active' : ''} ${selectedTickets.has(t.id) ? 'selected' : ''}" 
                     data-id="${t.id}" onclick="selectTicket(${t.id})">
                    <label class="ticket-checkbox checkbox-wrapper" onclick="event.stopPropagation()">
                        <input type="checkbox" ${selectedTickets.has(t.id) ? 'checked' : ''} onchange="toggleSelect(${t.id}, this.checked, event)">
                    </label>
                    <div class="ticket-content">
                        <div class="ticket-subject">${escapeHtml(t.subject)}</div>
                        <div class="ticket-preview">${escapeHtml(t.description || '').substring(0, 80)}</div>
                        <div class="ticket-meta">
                            <span class="priority ${t.priority}"></span>
                            <span>${escapeHtml(t.customer_name || t.customer_email)}</span>
                            <span>•</span>
                            <span>${formatTimeAgo(t.updated_at || t.created_at)}</span>
                        </div>
                    </div>
                    <span class="ticket-status status-badge ${t.status}">${t.status}</span>
                </div>
            `).join('');
            
            updateBulkBar();
        }
        
        // Select Ticket
        async function selectTicket(id) {
            document.querySelectorAll('.ticket-item').forEach(el => el.classList.remove('active'));
            document.querySelector(`.ticket-item[data-id="${id}"]`)?.classList.add('active');
            
            const panel = document.getElementById('ticketDetailPanel');
            panel.innerHTML = '<div class="loading-state"><i class="fa-solid fa-spinner fa-spin"></i></div>';
            
            // Mobile: show detail panel
            document.getElementById('ticketContainer').classList.add('showing-detail');
            
            try {
                const ticket = await api('GET', '/tickets/' + id);
                currentTicket = ticket.id;
                lastReplyCount = (ticket.replies || []).length; // Track reply count for this ticket
                renderTicketDetail(ticket);
                history.replaceState({}, '', '?id=' + id);
            } catch (e) {
                panel.innerHTML = '<div class="empty-state"><i class="fa-solid fa-exclamation-circle"></i><h4>Failed to load ticket</h4></div>';
            }
        }
        
        // Render Ticket Detail
        function renderTicketDetail(ticket) {
            const panel = document.getElementById('ticketDetailPanel');
            
            const agentOptions = agents.map(a => 
                `<option value="${a.id}" ${ticket.assigned_to == a.id ? 'selected' : ''}>${a.is_online ? '🟢' : '⚫'} ${escapeHtml(a.display_name || a.email)}</option>`
            ).join('');
            
            panel.innerHTML = `
                <div class="ticket-detail-header">
                    <button class="btn btn-secondary btn-sm mobile-back" onclick="backToList()"><i class="fa-solid fa-arrow-left"></i></button>
                    <div>
                        <h3>${escapeHtml(ticket.subject)}</h3>
                        <span>#${ticket.ticket_number} • ${escapeHtml(ticket.customer_name || 'Customer')} • ${escapeHtml(ticket.customer_email)}${ticket.customer_phone ? ' • ' + escapeHtml(ticket.customer_phone) : ''}</span>
                    </div>
                </div>
                
                <div class="ticket-properties">
                    <div class="property-group">
                        <label>Status</label>
                        <select onchange="updateTicket(${ticket.id}, 'status', this.value)">
                            <option value="new" ${ticket.status === 'new' ? 'selected' : ''}>New</option>
                            <option value="open" ${ticket.status === 'open' ? 'selected' : ''}>Open</option>
                            <option value="pending" ${ticket.status === 'pending' ? 'selected' : ''}>Pending</option>
                            <option value="resolved" ${ticket.status === 'resolved' ? 'selected' : ''}>Resolved</option>
                        </select>
                    </div>
                    <div class="property-group">
                        <label>Priority</label>
                        <select onchange="updateTicket(${ticket.id}, 'priority', this.value)">
                            <option value="low" ${ticket.priority === 'low' ? 'selected' : ''}>Low</option>
                            <option value="normal" ${ticket.priority === 'normal' ? 'selected' : ''}>Normal</option>
                            <option value="high" ${ticket.priority === 'high' ? 'selected' : ''}>High</option>
                        </select>
                    </div>
                    <div class="property-group">
                        <label>Agent</label>
                        <select onchange="updateTicket(${ticket.id}, 'assigned_to', this.value)">
                            <option value="">Unassigned</option>
                            ${agentOptions}
                        </select>
                    </div>
                </div>
                
                <div class="conversation-area" id="conversationArea">
                    ${renderConversation(ticket)}
                </div>
                
                <div class="reply-box">
                    <div class="reply-tabs">
                        <button class="reply-tab active" onclick="setReplyMode('reply', this)">Reply</button>
                        <button class="reply-tab" onclick="setReplyMode('note', this)">Note</button>
                    </div>
                    <div class="reply-editor">
                        <div class="editor-toolbar">
                            <button class="toolbar-btn" onclick="formatText('bold')" title="Bold"><i class="fa-solid fa-bold"></i></button>
                            <button class="toolbar-btn" onclick="formatText('italic')" title="Italic"><i class="fa-solid fa-italic"></i></button>
                            <button class="toolbar-btn" onclick="formatText('underline')" title="Underline"><i class="fa-solid fa-underline"></i></button>
                            <div class="toolbar-divider"></div>
                            <button class="toolbar-btn" onclick="formatText('ul')" title="Bullet List"><i class="fa-solid fa-list-ul"></i></button>
                            <button class="toolbar-btn" onclick="formatText('ol')" title="Numbered List"><i class="fa-solid fa-list-ol"></i></button>
                            <div class="toolbar-divider"></div>
                            <button class="toolbar-btn" onclick="insertLink()" title="Insert Link"><i class="fa-solid fa-link"></i></button>
                            <button class="toolbar-btn" onclick="document.getElementById('imageInput').click()" title="Attach Image"><i class="fa-solid fa-image"></i></button>
                            <button class="toolbar-btn" onclick="document.getElementById('attachmentInput').click()" title="Attach File"><i class="fa-solid fa-paperclip"></i></button>
                            <input type="file" id="imageInput" class="sr-only" multiple accept="image/*" onchange="handleAttachment(this)">
                            <input type="file" id="attachmentInput" class="sr-only" multiple onchange="handleAttachment(this)">
                            <div class="toolbar-divider"></div>
                            <button class="toolbar-btn" onclick="showCannedResponses()" title="Canned Responses"><i class="fa-solid fa-comment-dots"></i></button>
                            <div class="toolbar-divider"></div>
                            <button class="toolbar-btn-link calendar" onclick="insertCalendarLink()" title="Insert Calendar Link"><i class="fa-solid fa-calendar"></i> Calendar</button>
                            <button class="toolbar-btn-link zoom" onclick="insertZoomLink()" title="Insert Zoom Link"><i class="fa-solid fa-video"></i> Zoom</button>
                        </div>
                        <div id="attachmentPreview" class="attachment-preview"></div>
                        <textarea class="reply-textarea" id="replyText" placeholder="Type your reply..."></textarea>
                        <div class="reply-footer">
                            <div class="reply-options">
                                <label class="checkbox-wrapper"><input type="checkbox" id="setPending"></label>
                                <span>Set Pending after send</span>
                            </div>
                            <div class="reply-actions">
                                <button class="btn btn-secondary" onclick="resolveTicket(${ticket.id})">Resolve</button>
                                <button class="btn btn-primary" onclick="sendReply(${ticket.id})"><i class="fa-solid fa-paper-plane"></i> Send</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        // Parse attachments from message
        function parseMessageAttachments(message) {
            let cleanMessage = message || '';
            let attachments = [];
            
            // Check for [ATTACHMENTS:[...]] JSON format
            // The JSON is an array, so we look for [ATTACHMENTS: followed by a JSON array
            const startMarker = '[ATTACHMENTS:';
            const startIdx = cleanMessage.indexOf(startMarker);
            
            if (startIdx !== -1) {
                // Find the JSON part - it starts with [ after the marker
                const jsonStart = startIdx + startMarker.length;
                const remaining = cleanMessage.substring(jsonStart);
                
                // Find matching closing brackets - JSON array ends with ]]
                // (one ] for array, one ] for ATTACHMENTS block)
                let bracketCount = 0;
                let jsonEnd = -1;
                
                for (let i = 0; i < remaining.length; i++) {
                    if (remaining[i] === '[') bracketCount++;
                    if (remaining[i] === ']') {
                        bracketCount--;
                        if (bracketCount === 0) {
                            jsonEnd = i + 1;
                            break;
                        }
                    }
                }
                
                if (jsonEnd > 0) {
                    const jsonStr = remaining.substring(0, jsonEnd);
                    try {
                        attachments = JSON.parse(jsonStr);
                        // Remove the entire ATTACHMENTS block from message
                        cleanMessage = cleanMessage.substring(0, startIdx) + cleanMessage.substring(jsonStart + jsonEnd + 1);
                        cleanMessage = cleanMessage.trim();
                    } catch (e) {
                        console.error('Failed to parse attachments:', e, jsonStr);
                    }
                }
            }
            
            // Remove old format (---\nAttachments:...)
            if (cleanMessage.includes('---\nAttachments:')) {
                cleanMessage = cleanMessage.split('---\nAttachments:')[0].trim();
            }
            
            // Also handle "Attachments:\n" at start
            if (cleanMessage.startsWith('Attachments:')) {
                cleanMessage = '';
            }
            
            return { message: cleanMessage, attachments: attachments };
        }
        
        // Render attachments HTML
        function renderAttachmentsHtml(attachments) {
            if (!attachments || attachments.length === 0) return '';
            
            let html = '<div class="message-attachments">';
            attachments.forEach(att => {
                if (att.type === 'image' && att.url) {
                    html += `<div class="attachment-image" onclick="openLightbox('${escapeHtml(att.url)}')"><img src="${escapeHtml(att.url)}" alt="${escapeHtml(att.name || 'Image')}"></div>`;
                } else if (att.url) {
                    html += `<a href="${escapeHtml(att.url)}" target="_blank" class="attachment-file"><i class="fa-solid fa-file"></i> ${escapeHtml(att.name || 'File')}</a>`;
                }
            });
            html += '</div>';
            return html;
        }
        
        // Render Conversation
        function renderConversation(ticket) {
            const messages = ticket.replies || [];
            
            // Parse initial description for attachments
            const initialParsed = parseMessageAttachments(ticket.description);
            
            // Add initial description as first message
            let html = `
                <div class="message message-customer">
                    <div class="message-bubble">${escapeHtml(initialParsed.message || 'No description provided')}${renderAttachmentsHtml(initialParsed.attachments)}</div>
                    <div class="message-meta"><strong>${escapeHtml(ticket.customer_name || 'Customer')}</strong> • ${formatDateTime(ticket.created_at)}</div>
                </div>
            `;
            
            messages.forEach(m => {
                const isCustomer = m.author_type === 'customer';
                const isNote = m.is_internal || m.author_type === 'note';
                const parsed = parseMessageAttachments(m.message);
                
                html += `
                    <div class="message ${isCustomer ? 'message-customer' : 'message-agent'} ${isNote ? 'internal' : ''}">
                        <div class="message-bubble">
                            ${isNote ? '<i class="fa-solid fa-lock"></i> <strong>Internal Note:</strong> ' : ''}
                            ${escapeHtml(parsed.message)}${renderAttachmentsHtml(parsed.attachments)}
                        </div>
                        <div class="message-meta"><strong>${escapeHtml(m.author_name || (isCustomer ? 'Customer' : 'Agent'))}</strong> • ${formatDateTime(m.created_at)}</div>
                    </div>
                `;
            });
            
            return html;
        }
        
        // Reply Mode
        let replyMode = 'reply';
        function setReplyMode(mode, btn) {
            replyMode = mode;
            document.querySelectorAll('.reply-tab').forEach(t => t.classList.remove('active'));
            btn.classList.add('active');
            document.getElementById('replyText').placeholder = mode === 'note' ? 'Add an internal note...' : 'Type your reply...';
        }
        
        // Send Reply with file upload - ROBUST VERSION
        async function sendReply(ticketId) {
            debugLog('sendReply called for ticket:', ticketId);
            
            let text = document.getElementById('replyText').value.trim();
            debugLog('Message text:', text);
            debugLog('Pending attachments:', pendingAttachments.length);
            
            if (!text && pendingAttachments.length === 0) {
                showToast('Please enter a message or attach a file', true);
                return;
            }
            
            const btn = document.querySelector('.reply-actions .btn-primary');
            if (!btn) {
                console.error('Send button not found!');
                return;
            }
            
            const originalText = btn.innerHTML;
            btn.disabled = true;
            
            try {
                // Upload files first if any
                let uploadedAttachments = [];
                
                if (pendingAttachments.length > 0) {
                    debugLog('Starting file uploads...');
                    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Uploading...';
                    
                    for (let i = 0; i < pendingAttachments.length; i++) {
                        const file = pendingAttachments[i];
                        debugLog(`Uploading file ${i+1}/${pendingAttachments.length}:`, file.name, file.type, file.size);
                        btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> Uploading ${i+1}/${pendingAttachments.length}...`;
                        
                        try {
                            const formData = new FormData();
                            formData.append('file', file);
                            
                            debugLog('Upload URL:', UPLOAD_URL);
                            debugLog('Nonce:', WP_NONCE ? 'present' : 'MISSING');
                            
                            const uploadRes = await fetch(UPLOAD_URL, {
                                method: 'POST',
                                headers: {
                                    'X-WP-Nonce': WP_NONCE
                                },
                                body: formData,
                                credentials: 'same-origin'
                            });
                            
                            debugLog('Upload response status:', uploadRes.status);
                            
                            const uploadText = await uploadRes.text();
                            debugLog('Upload response raw:', uploadText);
                            
                            let uploadData;
                            try {
                                uploadData = JSON.parse(uploadText);
                            } catch (parseErr) {
                                console.error('Failed to parse upload response:', parseErr);
                                showToast('Server error during upload', true);
                                continue;
                            }
                            
                            debugLog('Upload response parsed:', uploadData);
                            
                            if (uploadData.success && uploadData.url) {
                                uploadedAttachments.push({
                                    name: uploadData.name || file.name,
                                    url: uploadData.url,
                                    type: uploadData.type || (file.type.startsWith('image/') ? 'image' : 'file')
                                });
                                debugLog('✅ File uploaded:', uploadData.url);
                            } else if (uploadData.url) {
                                // Some responses might not have success but have URL
                                uploadedAttachments.push({
                                    name: uploadData.name || file.name,
                                    url: uploadData.url,
                                    type: uploadData.type || (file.type.startsWith('image/') ? 'image' : 'file')
                                });
                                debugLog('✅ File uploaded (alt format):', uploadData.url);
                            } else {
                                const errorMsg = uploadData.error || uploadData.message || 'Upload failed';
                                console.error('❌ Upload failed:', errorMsg, uploadData);
                                showToast('Failed to upload ' + file.name + ': ' + errorMsg, true);
                            }
                        } catch (uploadErr) {
                            console.error('❌ Upload exception for ' + file.name + ':', uploadErr);
                            showToast('Error uploading ' + file.name + ': ' + uploadErr.message, true);
                        }
                    }
                    
                    debugLog('Upload complete. Successful:', uploadedAttachments.length);
                }
                
                // Build message with attachment metadata
                let finalMessage = text || '';
                if (uploadedAttachments.length > 0) {
                    const attachmentJson = JSON.stringify(uploadedAttachments);
                    finalMessage = finalMessage.trim();
                    if (finalMessage) {
                        finalMessage += '\n';
                    }
                    finalMessage += '[ATTACHMENTS:' + attachmentJson + ']';
                    debugLog('Final message with attachments:', finalMessage);
                }
                
                if (!finalMessage.trim()) {
                    showToast('No content to send', true);
                    return;
                }
                
                btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';
                debugLog('Sending message via API...');
                
                // Send the reply or note
                if (replyMode === 'note') {
                    await api('POST', '/tickets/' + ticketId + '/note', { message: finalMessage });
                } else {
                    await api('POST', '/tickets/' + ticketId + '/reply', { message: finalMessage });
                }
                debugLog('✅ Message sent successfully');
                
                // Update status if checkbox checked
                const setPendingCheckbox = document.getElementById('setPending');
                if (setPendingCheckbox && setPendingCheckbox.checked) {
                    await api('PATCH', '/tickets/' + ticketId, { status: 'pending' });
                }
                
                // Clear form
                document.getElementById('replyText').value = '';
                pendingAttachments = [];
                updateAttachmentPreview();
                
                showToast(replyMode === 'note' ? 'Note added!' : 'Reply sent!');
                
                // Refresh the ticket view
                await selectTicket(ticketId);
                loadStats();
                
            } catch (e) {
                console.error('❌ Send reply error:', e);
                showToast('Failed to send: ' + (e.message || 'Unknown error'), true);
            } finally {
                btn.disabled = false;
                btn.innerHTML = originalText;
            }
        }
        
        // Resolve Ticket
        async function resolveTicket(ticketId) {
            try {
                await api('PATCH', '/tickets/' + ticketId, { status: 'resolved' });
                showToast('Ticket resolved!');
                selectTicket(ticketId);
                loadTickets();
                loadStats();
            } catch (e) {
                showToast('Failed to resolve', true);
            }
        }
        
        // Update Ticket
        async function updateTicket(ticketId, field, value) {
            try {
                const data = {};
                data[field] = value;
                await api('PATCH', '/tickets/' + ticketId, data);
                showToast('Updated!');
                loadTickets();
                loadStats();
            } catch (e) {
                showToast('Update failed', true);
            }
        }
        
        // Insert Calendar Link
        function insertCalendarLink() {
            if (!agentProfile.calendarLink) {
                showToast('Set your calendar link in profile', true);
                return;
            }
            const textarea = document.getElementById('replyText');
            const text = `\n\nYou can book a time here:\n${agentProfile.calendarLink}\n\nLooking forward to speaking with you!`;
            textarea.value += text;
            textarea.focus();
            showToast('Calendar link inserted!');
        }
        
        // Insert Zoom Link
        function insertZoomLink() {
            if (!agentProfile.zoomLink) {
                showToast('Set your Zoom link in profile', true);
                return;
            }
            const textarea = document.getElementById('replyText');
            const text = `\n\nHere's my Zoom meeting link:\n${agentProfile.zoomLink}\n\nJoin whenever you're ready!`;
            textarea.value += text;
            textarea.focus();
            showToast('Zoom link inserted!');
        }
        
        // Text Formatting Functions
        function formatText(type) {
            const textarea = document.getElementById('replyText');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selectedText = textarea.value.substring(start, end);
            let newText = '';
            let cursorOffset = 0;
            
            switch(type) {
                case 'bold':
                    newText = `**${selectedText || 'bold text'}**`;
                    cursorOffset = selectedText ? 0 : -2;
                    break;
                case 'italic':
                    newText = `*${selectedText || 'italic text'}*`;
                    cursorOffset = selectedText ? 0 : -1;
                    break;
                case 'underline':
                    newText = `_${selectedText || 'underlined text'}_`;
                    cursorOffset = selectedText ? 0 : -1;
                    break;
                case 'ul':
                    if (selectedText) {
                        newText = selectedText.split('\n').map(line => `• ${line}`).join('\n');
                    } else {
                        newText = '\n• Item 1\n• Item 2\n• Item 3';
                    }
                    break;
                case 'ol':
                    if (selectedText) {
                        newText = selectedText.split('\n').map((line, i) => `${i+1}. ${line}`).join('\n');
                    } else {
                        newText = '\n1. First item\n2. Second item\n3. Third item';
                    }
                    break;
            }
            
            textarea.value = textarea.value.substring(0, start) + newText + textarea.value.substring(end);
            textarea.focus();
            const newPos = start + newText.length + cursorOffset;
            textarea.setSelectionRange(newPos, newPos);
        }
        
        // Insert Link
        function insertLink() {
            const url = prompt('Enter URL:', 'https://');
            if (!url) return;
            
            const textarea = document.getElementById('replyText');
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const selectedText = textarea.value.substring(start, end) || 'Link text';
            
            const linkText = `[${selectedText}](${url})`;
            textarea.value = textarea.value.substring(0, start) + linkText + textarea.value.substring(end);
            textarea.focus();
        }
        
        // Attachment handling
        let pendingAttachments = [];
        
        function handleAttachment(input) {
            const files = Array.from(input.files);
            if (files.length === 0) return;
            
            files.forEach(file => {
                if (file.size > 10 * 1024 * 1024) {
                    showToast(`File ${file.name} is too large (max 10MB)`, true);
                    return;
                }
                pendingAttachments.push(file);
            });
            
            updateAttachmentPreview();
            input.value = '';
        }
        
        function updateAttachmentPreview() {
            const preview = document.getElementById('attachmentPreview');
            if (!preview) return;
            
            if (pendingAttachments.length === 0) {
                preview.innerHTML = '';
                preview.style.display = 'none';
                return;
            }
            
            preview.style.display = 'flex';
            preview.innerHTML = pendingAttachments.map((file, i) => {
                // Show image preview for images
                if (file.type.startsWith('image/')) {
                    const url = URL.createObjectURL(file);
                    return `
                        <div class="attachment-item attachment-image-preview">
                            <img src="${url}" alt="${escapeHtml(file.name)}" style="width:60px;height:60px;object-fit:cover;border-radius:4px;">
                            <button type="button" onclick="removeAttachment(${i})" title="Remove"><i class="fa-solid fa-times"></i></button>
                        </div>
                    `;
                }
                return `
                    <div class="attachment-item">
                        <i class="fa-solid fa-file"></i>
                        <span>${escapeHtml(file.name)}</span>
                        <button type="button" onclick="removeAttachment(${i})" title="Remove"><i class="fa-solid fa-times"></i></button>
                    </div>
                `;
            }).join('');
        }
        
        function removeAttachment(index) {
            pendingAttachments.splice(index, 1);
            updateAttachmentPreview();
        }
        
        // Canned Responses
        let cannedResponses = [];
        
        async function loadCannedResponses() {
            try {
                cannedResponses = await api('GET', '/canned-responses');
            } catch (e) {
                cannedResponses = [];
            }
        }
        
        function showCannedResponses() {
            if (cannedResponses.length === 0) {
                showToast('No canned responses available', true);
                return;
            }
            
            // Create dropdown
            const existing = document.getElementById('cannedDropdown');
            if (existing) {
                existing.remove();
                return;
            }
            
            const dropdown = document.createElement('div');
            dropdown.id = 'cannedDropdown';
            dropdown.className = 'canned-dropdown';
            dropdown.innerHTML = `
                <div class="canned-dropdown-header">
                    <strong>Canned Responses</strong>
                    <button onclick="this.closest('.canned-dropdown').remove()"><i class="fa-solid fa-times"></i></button>
                </div>
                <div class="canned-dropdown-list">
                    ${cannedResponses.map(r => `
                        <div class="canned-item" onclick="insertCannedResponse(${r.id})">
                            <strong>${escapeHtml(r.title)}</strong>
                            <p>${escapeHtml(r.content.substring(0, 60))}...</p>
                        </div>
                    `).join('')}
                </div>
            `;
            
            document.querySelector('.reply-editor').appendChild(dropdown);
        }
        
        function insertCannedResponse(id) {
            const response = cannedResponses.find(r => r.id === id);
            if (!response) return;
            
            const textarea = document.getElementById('replyText');
            textarea.value = response.content;
            textarea.focus();
            
            document.getElementById('cannedDropdown')?.remove();
            showToast('Response inserted!');
        }
        
        // Bulk Selection
        function toggleSelect(id, checked, event) {
            event.stopPropagation();
            if (checked) {
                selectedTickets.add(id);
            } else {
                selectedTickets.delete(id);
            }
            updateBulkBar();
            document.querySelector(`.ticket-item[data-id="${id}"]`)?.classList.toggle('selected', checked);
        }
        
        function toggleSelectAll() {
            const checkAll = document.getElementById('selectAll').checked;
            document.querySelectorAll('.ticket-checkbox input').forEach(cb => {
                cb.checked = checkAll;
                const id = parseInt(cb.closest('.ticket-item').dataset.id);
                if (checkAll) selectedTickets.add(id);
                else selectedTickets.delete(id);
                cb.closest('.ticket-item').classList.toggle('selected', checkAll);
            });
            updateBulkBar();
        }
        
        function updateBulkBar() {
            const count = selectedTickets.size;
            document.getElementById('selectedCount').textContent = count + ' selected';
            document.getElementById('bulkActions').classList.toggle('show', count > 0);
        }
        
        function toggleDropdown() {
            document.getElementById('bulkMenu').classList.toggle('show');
        }
        
        async function bulkAction(action) {
            if (selectedTickets.size === 0) return;
            
            const ids = Array.from(selectedTickets);
            document.getElementById('bulkMenu').classList.remove('show');
            
            try {
                if (action === 'delete') {
                    if (!confirm('Delete ' + ids.length + ' ticket(s)?')) return;
                    for (const id of ids) {
                        await api('DELETE', '/tickets/' + id);
                    }
                } else if (['open', 'pending', 'resolved', 'new'].includes(action)) {
                    for (const id of ids) {
                        await api('PATCH', '/tickets/' + id, { status: action });
                    }
                } else if (action === 'high' || action === 'low') {
                    for (const id of ids) {
                        await api('PATCH', '/tickets/' + id, { priority: action });
                    }
                }
                
                selectedTickets.clear();
                showToast('Bulk action completed!');
                loadTickets();
                loadStats();
            } catch (e) {
                showToast('Bulk action failed', true);
            }
        }
        
        // Create Ticket
        async function createTicket(event) {
            event.preventDefault();
            
            try {
                const data = {
                    customer_name: document.getElementById('customerName').value,
                    customer_email: document.getElementById('customerEmail').value,
                    subject: document.getElementById('ticketSubject').value,
                    description: document.getElementById('ticketDescription').value,
                    priority: document.getElementById('ticketPriority').value
                };
                
                const ticket = await api('POST', '/tickets', data);
                closeModal('newTicketModal');
                document.getElementById('newTicketForm').reset();
                showToast('Ticket created!');
                loadTickets();
                loadStats();
                if (ticket.id) selectTicket(ticket.id);
            } catch (e) {
                showToast('Failed to create ticket', true);
            }
        }
        
        // Search
        function handleSearch(event) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(loadTickets, 300);
        }
        
        // Back to list (mobile)
        function backToList() {
            document.getElementById('ticketContainer').classList.remove('showing-detail');
            currentTicket = null;
        }
        
        // Modal
        function showModal(id) {
            document.getElementById(id).classList.add('show');
        }
        
        function closeModal(id) {
            document.getElementById(id).classList.remove('show');
        }
        
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
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function formatTimeAgo(dateStr) {
            if (!dateStr) return '';
            const date = new Date(dateStr);
            const now = new Date();
            const diff = Math.floor((now - date) / 1000);
            if (diff < 60) return 'Just now';
            if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
            if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
            return date.toLocaleDateString();
        }
        
        function formatDateTime(dateStr) {
            if (!dateStr) return '';
            const d = new Date(dateStr);
            return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' }) + ', ' + 
                   d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
        }
        
        // Lightbox for images
        function openLightbox(src) {
            document.getElementById('lightboxImg').src = src;
            document.getElementById('lightbox').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeLightbox() {
            document.getElementById('lightbox').classList.remove('active');
            document.body.style.overflow = '';
        }
        
        // Close lightbox on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeLightbox();
        });
    </script>
    
    <!-- Lightbox Modal -->
    <div class="lightbox-overlay" id="lightbox" onclick="closeLightbox()">
        <button class="lightbox-close" onclick="closeLightbox()"><i class="fa-solid fa-times"></i></button>
        <img src="" alt="Preview" id="lightboxImg" onclick="event.stopPropagation()">
    </div>
    
    <style>
        .lightbox-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.92);
            z-index: 99999;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .lightbox-overlay.active {
            display: flex;
        }
        .lightbox-overlay img {
            max-width: 90%;
            max-height: 90%;
            border-radius: 8px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.5);
        }
        .lightbox-close {
            position: absolute;
            top: 20px;
            right: 20px;
            width: 48px;
            height: 48px;
            background: rgba(255,255,255,0.1);
            border: none;
            border-radius: 50%;
            color: #fff;
            font-size: 20px;
            cursor: pointer;
            transition: background 0.2s;
        }
        .lightbox-close:hover {
            background: rgba(255,255,255,0.2);
        }
    </style>
</body>
</html>
