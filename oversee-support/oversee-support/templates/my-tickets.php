<?php
/**
 * My Tickets Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$portal_title = oversee_get_portal_title();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Tickets - <?php echo esc_html($portal_title); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo esc_url(OVERSEE_PLUGIN_URL . 'assets/css/public.css?v=2.0.0'); ?>">
</head>
<body>
    <div class="page-wrapper">
        <header class="site-header">
            <div class="header-inner">
                <nav class="header-nav">
                    <a href="<?php echo esc_url(oversee_kb_url()); ?>"><i class="fa-solid fa-book"></i><span>Knowledge Base</span></a>
                    <a href="<?php echo esc_url(oversee_support_url('tickets')); ?>" class="active"><i class="fa-solid fa-ticket"></i><span>My Tickets</span></a>
                    <a href="<?php echo esc_url(oversee_support_url('zoom')); ?>"><i class="fa-solid fa-video"></i><span>Live Support</span></a>
                </nav>
                <div class="header-actions">
                    <a href="<?php echo esc_url(oversee_support_url('submit')); ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i><span>Submit Ticket</span></a>
                </div>
            </div>
        </header>

        <nav class="breadcrumb">
            <div class="breadcrumb-inner">
                <a href="<?php echo esc_url(oversee_kb_url()); ?>"><i class="fa-solid fa-home"></i> Help Center</a>
                <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                <span class="current">My Tickets</span>
            </div>
        </nav>

        <main class="main-content">
            <section class="tickets-section">
                <div class="form-card" id="lookupForm">
                    <h1>My Support Tickets</h1>
                    <p>Enter your email address to view your support tickets.</p>
                    <form onsubmit="lookupTickets(event)">
                        <div class="form-group">
                            <label for="email">Email Address</label>
                            <input type="email" id="email" class="form-control" required placeholder="john@example.com">
                        </div>
                        <button type="submit" class="btn btn-primary" id="lookupBtn"><i class="fa-solid fa-search"></i> Find My Tickets</button>
                    </form>
                </div>
                
                <div class="tickets-card" id="ticketsCard" style="display: none;">
                    <div class="tickets-header">
                        <h1>Your Tickets</h1>
                        <button class="btn btn-sm btn-secondary" onclick="changeLookup()"><i class="fa-solid fa-arrow-left"></i> Change Email</button>
                    </div>
                    <div id="ticketsContent"></div>
                </div>
            </section>
        </main>

        <footer class="site-footer">
            <p>Powered by <a href="https://overseecrm.com" target="_blank">OverseeCRM</a></p>
        </footer>
    </div>

    <script>
        let currentEmail = '';
        
        async function lookupTickets(e) {
            e.preventDefault();
            const email = document.getElementById('email').value;
            const btn = document.getElementById('lookupBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Loading...';
            
            try {
                const response = await fetch('<?php echo esc_url(rest_url('oversee/v1/tickets/public')); ?>?email=' + encodeURIComponent(email));
                const tickets = await response.json();
                currentEmail = email;
                document.getElementById('lookupForm').style.display = 'none';
                document.getElementById('ticketsCard').style.display = 'block';
                
                if (!tickets || tickets.length === 0) {
                    document.getElementById('ticketsContent').innerHTML = '<div style="text-align:center;padding:60px 24px;"><i class="fa-solid fa-inbox" style="font-size:48px;opacity:0.3;margin-bottom:16px;"></i><h3>No tickets found</h3><p style="color:var(--text-muted);margin-bottom:24px;">No tickets found for ' + escapeHtml(email) + '</p><a href="<?php echo esc_url(oversee_support_url('submit')); ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> Submit a Ticket</a></div>';
                } else {
                    document.getElementById('ticketsContent').innerHTML = '<table class="tickets-table"><thead><tr><th>Ticket</th><th>Subject</th><th>Status</th><th>Date</th></tr></thead><tbody>' + tickets.map(t => '<tr onclick="viewTicket(\'' + t.ticket_number + '\')" style="cursor:pointer;"><td><span class="ticket-link">#' + t.ticket_number + '</span></td><td>' + escapeHtml(t.subject) + '</td><td><span class="status-badge ' + t.status + '">' + t.status + '</span></td><td>' + formatDate(t.created_at) + '</td></tr>').join('') + '</tbody></table>';
                }
            } catch (error) {
                alert('Failed to load tickets. Please try again.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-search"></i> Find My Tickets';
            }
        }
        
        function viewTicket(ticketNumber) {
            window.location.href = '<?php echo esc_url(oversee_support_url('ticket')); ?>/' + ticketNumber + '?email=' + encodeURIComponent(currentEmail);
        }
        
        function changeLookup() {
            document.getElementById('lookupForm').style.display = 'block';
            document.getElementById('ticketsCard').style.display = 'none';
        }
        
        function formatDate(dateStr) { return new Date(dateStr).toLocaleDateString(); }
        function escapeHtml(text) { if (!text) return ''; const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
    </script>
</body>
</html>
