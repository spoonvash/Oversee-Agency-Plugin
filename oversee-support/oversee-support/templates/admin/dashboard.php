<?php
/**
 * Admin Dashboard Page
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!is_user_logged_in() || !current_user_can('oversee_view_dashboard')) {
    wp_safe_redirect(oversee_admin_url('login'));
    exit;
}

Oversee_Auth::ensure_agent_record();

$current_user = wp_get_current_user();
$first_name = $current_user->first_name ?: explode(' ', $current_user->display_name)[0];

// Get time-based greeting
$hour = (int) current_time('G');
if ($hour < 12) {
    $greeting = 'Good morning';
} elseif ($hour < 17) {
    $greeting = 'Good afternoon';
} else {
    $greeting = 'Good evening';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo esc_html(oversee_get_portal_title()); ?></title>
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
            <h2>Dashboard</h2>
            <div class="topbar-right">
                <button class="btn btn-secondary"><i class="fa-solid fa-bell"></i></button>
                <a href="<?php echo esc_url(oversee_admin_url('logout')); ?>" class="btn btn-secondary">
                    <i class="fa-solid fa-right-from-bracket"></i> Logout
                </a>
            </div>
        </div>
        
        <div class="content">
            <!-- Welcome Banner -->
            <div class="welcome-banner">
                <div>
                    <h3><?php echo esc_html($greeting); ?>, <?php echo esc_html($first_name); ?>! 👋</h3>
                    <p id="welcomeMessage">Loading your dashboard...</p>
                </div>
                <a href="<?php echo esc_url(oversee_admin_url('tickets')); ?>" class="btn">
                    <i class="fa-solid fa-ticket"></i> View Tickets
                </a>
            </div>
            
            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card high">
                    <div class="icon"><i class="fa-solid fa-fire"></i></div>
                    <div class="value" id="statHigh">-</div>
                    <div class="label">High Priority</div>
                    <div class="change up" id="statHighChange"></div>
                </div>
                <div class="stat-card new">
                    <div class="icon"><i class="fa-solid fa-inbox"></i></div>
                    <div class="value" id="statNew">-</div>
                    <div class="label">New Tickets</div>
                    <div class="change" id="statNewChange"></div>
                </div>
                <div class="stat-card open">
                    <div class="icon"><i class="fa-solid fa-folder-open"></i></div>
                    <div class="value" id="statOpen">-</div>
                    <div class="label">Open Tickets</div>
                    <div class="change" id="statOpenChange"></div>
                </div>
                <div class="stat-card resolved">
                    <div class="icon"><i class="fa-solid fa-check-circle"></i></div>
                    <div class="value" id="statResolved">-</div>
                    <div class="label">Resolved This Week</div>
                    <div class="change up" id="statResolvedChange"></div>
                </div>
            </div>
            
            <!-- Dashboard Grid -->
            <div class="dashboard-grid">
                <!-- Recent Tickets -->
                <div class="card">
                    <div class="card-header">
                        <h3>Recent Tickets</h3>
                        <a href="<?php echo esc_url(oversee_admin_url('tickets')); ?>">View All →</a>
                    </div>
                    <div class="card-body" id="recentTickets">
                        <div class="loading-state">
                            <i class="fa-solid fa-spinner fa-spin"></i>
                            <span>Loading tickets...</span>
                        </div>
                    </div>
                </div>
                
                <!-- Team Status -->
                <div class="card">
                    <div class="card-header">
                        <h3>Team Status</h3>
                        <a href="<?php echo esc_url(oversee_admin_url('settings')); ?>">Manage →</a>
                    </div>
                    <div class="card-body" id="teamStatus">
                        <div class="loading-state">
                            <i class="fa-solid fa-spinner fa-spin"></i>
                            <span>Loading team...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <div class="toast" id="toast"><i class="fa-solid fa-check-circle"></i><span id="toastMessage"></span></div>
    
    <script src="<?php echo esc_url(OVERSEE_PLUGIN_URL . 'assets/js/api-client.js'); ?>"></script>
    <script>
        overseeAPI.setNonce('<?php echo wp_create_nonce('wp_rest'); ?>');
        
        const colors = ['#f97316', '#3b82f6', '#8b5cf6', '#10b981', '#ef4444', '#6366f1'];
        
        async function loadDashboard() {
            await Promise.all([loadStats(), loadRecentTickets(), loadTeamStatus()]);
        }
        
        async function loadStats() {
            try {
                const stats = await overseeAPI.get('/dashboard/stats');
                
                document.getElementById('statHigh').textContent = stats.high_priority || 0;
                document.getElementById('statNew').textContent = stats.new || 0;
                document.getElementById('statOpen').textContent = stats.open || 0;
                document.getElementById('statResolved').textContent = stats.resolved_week || 0;
                
                // Update welcome message
                const newCount = stats.new || 0;
                document.getElementById('welcomeMessage').textContent = newCount > 0 
                    ? `You have ${newCount} new ticket${newCount !== 1 ? 's' : ''} waiting for your attention`
                    : 'All caught up! No new tickets at the moment.';
                    
            } catch (e) {
                console.error('Failed to load stats:', e);
            }
        }
        
        async function loadRecentTickets() {
            const container = document.getElementById('recentTickets');
            try {
                const tickets = await overseeAPI.get('/dashboard/recent-tickets?limit=5');
                
                if (!tickets || tickets.length === 0) {
                    container.innerHTML = '<div class="empty-state"><i class="fa-solid fa-ticket"></i><h4>No tickets yet</h4><p>New tickets will appear here</p></div>';
                    return;
                }
                
                container.innerHTML = tickets.map(t => `
                    <div class="ticket-list-item" onclick="window.location='<?php echo esc_url(oversee_admin_url('tickets')); ?>?id=${t.id}'" style="cursor:pointer">
                        <span class="priority-dot ${t.priority}"></span>
                        <div class="info">
                            <div class="subject">${escapeHtml(t.subject)}</div>
                            <div class="meta">${escapeHtml(t.customer_name || t.customer_email)} • ${formatTimeAgo(t.created_at)}</div>
                        </div>
                        <span class="status ${t.status}">${t.status}</span>
                    </div>
                `).join('');
            } catch (e) {
                container.innerHTML = '<div class="empty-state"><i class="fa-solid fa-exclamation-circle"></i><p>Failed to load tickets</p></div>';
            }
        }
        
        async function loadTeamStatus() {
            const container = document.getElementById('teamStatus');
            try {
                const team = await overseeAPI.get('/dashboard/team-status');
                
                if (!team || team.length === 0) {
                    container.innerHTML = '<div class="empty-state"><i class="fa-solid fa-users"></i><h4>No team members</h4><p>Add agents in settings</p></div>';
                    return;
                }
                
                container.innerHTML = team.map((m, i) => {
                    const initials = getInitials(m.display_name || m.email);
                    const color = colors[i % colors.length];
                    return `
                        <div class="team-member">
                            <div class="avatar" style="background:${color}">
                                ${initials}
                                <span class="status-dot ${m.is_online ? 'online' : 'offline'}"></span>
                            </div>
                            <div class="info">
                                <div class="name">${escapeHtml(m.display_name || m.email)}</div>
                                <div class="tickets">${m.open_tickets || 0} open tickets</div>
                            </div>
                            <span class="status-badge ${m.is_online ? 'online' : 'offline'}">${m.is_online ? 'Online' : 'Offline'}</span>
                        </div>
                    `;
                }).join('');
            } catch (e) {
                container.innerHTML = '<div class="empty-state"><i class="fa-solid fa-exclamation-circle"></i><p>Failed to load team</p></div>';
            }
        }
        
        function getInitials(name) {
            if (!name) return '?';
            return name.split(' ').map(p => p[0]).join('').substring(0, 2).toUpperCase();
        }
        
        function formatTimeAgo(dateStr) {
            const date = new Date(dateStr);
            const now = new Date();
            const diff = Math.floor((now - date) / 1000);
            
            if (diff < 60) return 'Just now';
            if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
            if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
            if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';
            return date.toLocaleDateString();
        }
        
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
        
        function showToast(msg) {
            const toast = document.getElementById('toast');
            document.getElementById('toastMessage').textContent = msg;
            toast.classList.add('show');
            setTimeout(() => toast.classList.remove('show'), 3000);
        }
        
        document.addEventListener('DOMContentLoaded', loadDashboard);
    </script>
</body>
</html>
