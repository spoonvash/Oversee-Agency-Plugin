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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tickets - <?php echo esc_html(oversee_get_portal_title()); ?></title>
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
    
    <script>
        const WP_NONCE = '<?php echo wp_create_nonce('wp_rest'); ?>';
        const REST_URL = '<?php echo esc_url(rest_url('oversee/v1')); ?>';
        const AGENT_ID = <?php echo $agent ? intval($agent->id) : 'null'; ?>;
        
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
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadStats();
            loadTickets();
            loadAgents();
            
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
            } catch (e) {
                console.error('Failed to load stats');
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
                currentTicket = ticket;
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
                            <button class="toolbar-btn" title="Bold"><i class="fa-solid fa-bold"></i></button>
                            <button class="toolbar-btn" title="Italic"><i class="fa-solid fa-italic"></i></button>
                            <button class="toolbar-btn" title="List"><i class="fa-solid fa-list-ul"></i></button>
                            <div class="toolbar-divider"></div>
                            <button class="toolbar-btn highlight" title="Image"><i class="fa-solid fa-image"></i></button>
                            <button class="toolbar-btn" title="Attachment"><i class="fa-solid fa-paperclip"></i></button>
                            <div class="toolbar-divider"></div>
                            <button class="toolbar-btn" title="Canned Response"><i class="fa-solid fa-comment-dots"></i></button>
                            <div class="toolbar-divider"></div>
                            <button class="toolbar-btn-link calendar" onclick="insertCalendarLink()" title="Send Calendar Link"><i class="fa-solid fa-calendar"></i> Calendar</button>
                            <button class="toolbar-btn-link zoom" onclick="insertZoomLink()" title="Send Zoom Link"><i class="fa-solid fa-video"></i> Zoom</button>
                        </div>
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
        
        // Render Conversation
        function renderConversation(ticket) {
            const messages = ticket.replies || [];
            
            // Add initial description as first message
            let html = `
                <div class="message message-customer">
                    <div class="message-bubble">${escapeHtml(ticket.description || 'No description provided')}</div>
                    <div class="message-meta"><strong>${escapeHtml(ticket.customer_name || 'Customer')}</strong> • ${formatDateTime(ticket.created_at)}</div>
                </div>
            `;
            
            messages.forEach(m => {
                const isCustomer = m.author_type === 'customer';
                const isNote = m.is_internal || m.author_type === 'note';
                
                html += `
                    <div class="message ${isCustomer ? 'message-customer' : 'message-agent'} ${isNote ? 'internal' : ''}">
                        <div class="message-bubble">
                            ${isNote ? '<i class="fa-solid fa-lock"></i> <strong>Internal Note:</strong> ' : ''}
                            ${escapeHtml(m.message)}
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
        
        // Send Reply
        async function sendReply(ticketId) {
            const text = document.getElementById('replyText').value.trim();
            if (!text) return;
            
            try {
                await api('POST', '/tickets/' + ticketId + '/replies', {
                    message: text,
                    is_internal: replyMode === 'note'
                });
                
                // Update status if checkbox checked
                if (document.getElementById('setPending').checked) {
                    await api('PATCH', '/tickets/' + ticketId, { status: 'pending' });
                }
                
                document.getElementById('replyText').value = '';
                showToast(replyMode === 'note' ? 'Note added!' : 'Reply sent!');
                selectTicket(ticketId);
                loadStats();
            } catch (e) {
                showToast('Failed to send', true);
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
    </script>
</body>
</html>
