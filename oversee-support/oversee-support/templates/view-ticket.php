<?php
/**
 * View Ticket Page
 */

if (!defined('ABSPATH')) {
    exit;
}

global $oversee_vars;
$ticket_number = $oversee_vars['ticket_number'] ?? '';
$email = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';
$portal_title = oversee_get_portal_title();

$ticket = null;
$messages = [];
if (!empty($ticket_number) && !empty($email)) {
    global $wpdb;
    $ticket = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}oversee_tickets WHERE ticket_number = %s AND customer_email = %s",
        $ticket_number, $email
    ), ARRAY_A);
    
    if ($ticket) {
        $messages = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}oversee_ticket_replies WHERE ticket_id = %d AND is_internal_note = 0 ORDER BY created_at ASC",
            $ticket['id']
        ), ARRAY_A);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #<?php echo esc_html($ticket_number); ?> - <?php echo esc_html($portal_title); ?></title>
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
                <a href="<?php echo esc_url(oversee_support_url('tickets')); ?>">My Tickets</a>
                <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                <span class="current">#<?php echo esc_html($ticket_number); ?></span>
            </div>
        </nav>

        <main class="main-content">
            <section class="ticket-view-section">
                <?php if (!$ticket): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-ticket"></i>
                    <h3>Ticket Not Found</h3>
                    <p>We couldn't find this ticket. Please verify the ticket number and email address.</p>
                    <a href="<?php echo esc_url(oversee_support_url('tickets')); ?>" class="btn btn-primary" style="margin-top:16px;"><i class="fa-solid fa-arrow-left"></i> Back to My Tickets</a>
                </div>
                <?php else: ?>
                
                <div class="ticket-header-card">
                    <h1><?php echo esc_html($ticket['subject']); ?></h1>
                    <div class="ticket-info-grid">
                        <div class="info-item"><label>Ticket Number</label><span>#<?php echo esc_html($ticket['ticket_number']); ?></span></div>
                        <div class="info-item"><label>Status</label><span class="status-badge <?php echo esc_attr($ticket['status']); ?>"><?php echo esc_html($ticket['status']); ?></span></div>
                        <div class="info-item"><label>Priority</label><span class="priority-badge <?php echo esc_attr($ticket['priority']); ?>"><?php echo esc_html($ticket['priority']); ?></span></div>
                        <div class="info-item"><label>Created</label><span><?php echo date('M j, Y', strtotime($ticket['created_at'])); ?></span></div>
                    </div>
                </div>
                
                <?php if ($ticket['status'] === 'resolved'): ?>
                <div class="resolved-notice"><i class="fa-solid fa-check-circle"></i> This ticket has been resolved. You can reply below to reopen it.</div>
                <?php endif; ?>
                
                <div class="conversation-card">
                    <div class="messages-list" id="messagesList">
                        <div class="message customer">
                            <div class="message-header"><strong><?php echo esc_html($ticket['customer_name'] ?: 'Customer'); ?></strong><span><?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?></span></div>
                            <div class="message-body"><?php echo nl2br(esc_html($ticket['description'])); ?></div>
                        </div>
                        <?php foreach ($messages as $msg): ?>
                        <div class="message <?php echo $msg['author_type'] === 'agent' ? 'agent' : 'customer'; ?>">
                            <div class="message-header"><strong><?php echo $msg['author_type'] === 'agent' ? 'Support Team' : esc_html($ticket['customer_name'] ?: 'Customer'); ?></strong><span><?php echo date('M j, Y g:i A', strtotime($msg['created_at'])); ?></span></div>
                            <div class="message-body"><?php echo nl2br(esc_html($msg['message'])); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="reply-form">
                        <form onsubmit="submitReply(event)">
                            <textarea id="replyMessage" placeholder="Type your reply here..." required></textarea>
                            <button type="submit" class="btn btn-primary" id="replyBtn"><i class="fa-solid fa-paper-plane"></i> Send Reply</button>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </section>
        </main>

        <footer class="site-footer">
            <p>Powered by <a href="https://overseecrm.com" target="_blank">OverseeCRM</a></p>
        </footer>
    </div>

    <?php if ($ticket): ?>
    <script>
        const ticketId = <?php echo intval($ticket['id']); ?>;
        const customerEmail = '<?php echo esc_js($email); ?>';
        
        async function submitReply(e) {
            e.preventDefault();
            const message = document.getElementById('replyMessage').value.trim();
            if (!message) return;
            
            const btn = document.getElementById('replyBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending...';
            
            try {
                const response = await fetch('<?php echo esc_url(rest_url('oversee/v1/tickets/public/reply')); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ ticket_id: ticketId, email: customerEmail, message: message })
                });
                
                if (!response.ok) throw new Error('Failed to send reply');
                
                const messagesList = document.getElementById('messagesList');
                const newMessage = document.createElement('div');
                newMessage.className = 'message customer';
                newMessage.innerHTML = '<div class="message-header"><strong>You</strong><span>Just now</span></div><div class="message-body">' + escapeHtml(message).replace(/\n/g, '<br>') + '</div>';
                messagesList.appendChild(newMessage);
                messagesList.scrollTop = messagesList.scrollHeight;
                document.getElementById('replyMessage').value = '';
            } catch (error) {
                alert('Failed to send reply. Please try again.');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Send Reply';
            }
        }
        
        function escapeHtml(text) { const div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
        document.addEventListener('DOMContentLoaded', function() { const m = document.getElementById('messagesList'); if (m) m.scrollTop = m.scrollHeight; });
    </script>
    <?php endif; ?>
</body>
</html>
