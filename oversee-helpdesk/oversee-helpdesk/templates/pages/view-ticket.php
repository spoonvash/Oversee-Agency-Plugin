<?php
/**
 * View Ticket Page - Content Only
 * All CSS is in /assets/css/public.css
 */

if (!defined('ABSPATH')) {
    exit;
}

global $oversee_vars;
$ticket_number = $oversee_vars['ticket'] ?? '';
$email = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';

if (empty($email) && isset($_COOKIE['oversee_customer_email'])) {
    $email = sanitize_email($_COOKIE['oversee_customer_email']);
}

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

function oversee_parse_attachments_tpl($message) {
    $attachments = [];
    $clean = $message ?: '';
    $marker = '[ATTACHMENTS:';
    $pos = strpos($clean, $marker);
    if ($pos !== false) {
        $json_start = $pos + strlen($marker);
        $remaining = substr($clean, $json_start);
        $count = 0;
        $end = -1;
        for ($i = 0; $i < strlen($remaining); $i++) {
            if ($remaining[$i] === '[') $count++;
            if ($remaining[$i] === ']') { $count--; if ($count === 0) { $end = $i + 1; break; } }
        }
        if ($end > 0) {
            $parsed = json_decode(substr($remaining, 0, $end), true);
            if (is_array($parsed)) {
                $attachments = $parsed;
                $clean = substr($clean, 0, $pos) . substr($remaining, $end + 1);
            }
        }
    }
    return ['message' => trim($clean), 'attachments' => $attachments];
}
?>

<nav class="breadcrumb">
    <div class="breadcrumb-inner">
        <a href="<?php echo esc_url(oversee_kb_url()); ?>"><i class="fa-solid fa-home"></i> Help Center</a>
        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
        <a href="<?php echo esc_url(oversee_support_url('tickets')); ?>">My Tickets</a>
        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
        <span class="current">#<?php echo esc_html($ticket_number); ?></span>
    </div>
</nav>

<section class="ticket-view-section">
    <?php if (!$ticket): ?>
    <div class="empty-state">
        <i class="fa-solid fa-ticket"></i>
        <h3>Ticket Not Found</h3>
        <p>We couldn't find this ticket. Please verify the ticket number and email address.</p>
        <a href="<?php echo esc_url(oversee_support_url('tickets')); ?>" class="btn btn-primary"><i class="fa-solid fa-arrow-left"></i> Back to My Tickets</a>
    </div>
    <?php else: ?>
    
    <div class="ticket-layout">
        <div class="ticket-sidebar">
            <div class="ticket-info-card">
                <h1><?php echo esc_html($ticket['subject']); ?></h1>
                <div class="ticket-details">
                    <div class="detail-row"><span class="detail-label">Ticket Number</span><span class="detail-value ticket-num"><?php echo esc_html($ticket['ticket_number']); ?></span></div>
                    <div class="detail-row"><span class="detail-label">Status</span><span class="status-badge <?php echo esc_attr($ticket['status']); ?>"><?php echo esc_html($ticket['status']); ?></span></div>
                    <div class="detail-row"><span class="detail-label">Priority</span><span class="priority-badge <?php echo esc_attr($ticket['priority']); ?>"><?php echo esc_html($ticket['priority']); ?></span></div>
                    <div class="detail-row"><span class="detail-label">Created</span><span class="detail-value"><?php echo date('M j, Y', strtotime($ticket['created_at'])); ?></span></div>
                    <div class="detail-row"><span class="detail-label">Last Updated</span><span class="detail-value"><?php echo date('M j, Y', strtotime($ticket['updated_at'] ?? $ticket['created_at'])); ?></span></div>
                </div>
                <?php if ($ticket['status'] === 'resolved'): ?>
                <div class="resolved-notice"><i class="fa-solid fa-check-circle"></i><span>This ticket has been resolved. Reply below to reopen.</span></div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="ticket-main">
            <div class="conversation-card">
                <div class="conversation-header"><h2><i class="fa-solid fa-comments"></i> Conversation</h2></div>
                
                <div class="chat-messages" id="messagesList">
                    <?php 
                    $parsed = oversee_parse_attachments_tpl($ticket['description']);
                    $msg_text = $parsed['message'];
                    ?>
                    <div class="chat-message chat-message--outgoing">
                        <span class="chat-message__author"><i class="fa-solid fa-user"></i> You</span>
                        <span class="chat-message__time"><?php echo date('M j, g:i A', strtotime($ticket['created_at'])); ?></span>
                        <?php if ($msg_text): ?><p class="chat-message__text"><?php echo nl2br(esc_html($msg_text)); ?></p><?php endif; ?>
                        <?php if (!empty($parsed['attachments'])): ?>
                        <div class="chat-message__attachments">
                            <?php foreach ($parsed['attachments'] as $att): ?>
                                <?php if (($att['type'] ?? '') === 'image' && !empty($att['url'])): ?>
                                    <img src="<?php echo esc_url($att['url']); ?>" alt="<?php echo esc_attr($att['name'] ?? 'Image'); ?>" class="chat-attachment-img" data-lightbox>
                                <?php elseif (!empty($att['url'])): ?>
                                    <a href="<?php echo esc_url($att['url']); ?>" target="_blank" class="chat-attachment-file"><i class="fa-solid fa-file"></i> <?php echo esc_html($att['name'] ?? 'File'); ?></a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php foreach ($messages as $msg): 
                        $parsed = oversee_parse_attachments_tpl($msg['message']);
                        $msg_text = $parsed['message'];
                        $is_agent = $msg['author_type'] === 'agent';
                    ?>
                    <div class="chat-message <?php echo $is_agent ? 'chat-message--incoming' : 'chat-message--outgoing'; ?>">
                        <span class="chat-message__author"><?php echo $is_agent ? '<i class="fa-solid fa-headset"></i> Support' : '<i class="fa-solid fa-user"></i> You'; ?></span>
                        <span class="chat-message__time"><?php echo date('M j, g:i A', strtotime($msg['created_at'])); ?></span>
                        <?php if ($msg_text): ?><p class="chat-message__text"><?php echo nl2br(esc_html($msg_text)); ?></p><?php endif; ?>
                        <?php if (!empty($parsed['attachments'])): ?>
                        <div class="chat-message__attachments">
                            <?php foreach ($parsed['attachments'] as $att): ?>
                                <?php if (($att['type'] ?? '') === 'image' && !empty($att['url'])): ?>
                                    <img src="<?php echo esc_url($att['url']); ?>" alt="<?php echo esc_attr($att['name'] ?? 'Image'); ?>" class="chat-attachment-img" data-lightbox>
                                <?php elseif (!empty($att['url'])): ?>
                                    <a href="<?php echo esc_url($att['url']); ?>" target="_blank" class="chat-attachment-file"><i class="fa-solid fa-file"></i> <?php echo esc_html($att['name'] ?? 'File'); ?></a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="chat-composer">
                    <form id="replyForm">
                        <div class="chat-composer__toolbar">
                            <button type="button" class="chat-composer__btn" id="addImageBtn" title="Add Image"><i class="fa-solid fa-image"></i></button>
                            <button type="button" class="chat-composer__btn" id="addFileBtn" title="Attach File"><i class="fa-solid fa-paperclip"></i></button>
                            <input type="file" id="imageInput" accept="image/*" multiple class="sr-only">
                            <input type="file" id="fileInput" multiple class="sr-only">
                        </div>
                        <div id="attachmentPreview" class="chat-composer__attachments"></div>
                        <div class="chat-composer__input">
                            <textarea id="replyMessage" placeholder="Type a message..." rows="1"></textarea>
                            <button type="submit" class="chat-composer__send" id="replyBtn"><i class="fa-solid fa-arrow-up"></i></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</section>

<div class="lightbox" id="lightbox">
    <button class="lightbox-close"><i class="fa-solid fa-times"></i></button>
    <img src="" alt="Preview" id="lightboxImg">
</div>

<div class="notification-banner" id="notificationBanner">
    <i class="fa-solid fa-bell"></i>
    <span id="notificationText">New reply from support!</span>
    <button id="dismissNotification"><i class="fa-solid fa-times"></i></button>
</div>

<?php if ($ticket): ?>
<script>
(function() {
    const ticketId = <?php echo intval($ticket['id']); ?>;
    const customerEmail = '<?php echo esc_js($email); ?>';
    let pendingFiles = [];
    let justSentReply = false;
    let lastMessageCount = <?php echo count($messages) + 1; ?>;
    
    localStorage.setItem('oversee_customer_email', customerEmail);
    
    const lightbox = document.getElementById('lightbox');
    const lightboxImg = document.getElementById('lightboxImg');
    const messagesList = document.getElementById('messagesList');
    const replyForm = document.getElementById('replyForm');
    const replyMessage = document.getElementById('replyMessage');
    const replyBtn = document.getElementById('replyBtn');
    const attachmentPreview = document.getElementById('attachmentPreview');
    const imageInput = document.getElementById('imageInput');
    const fileInput = document.getElementById('fileInput');
    const notificationBanner = document.getElementById('notificationBanner');
    
    // Lightbox
    document.querySelectorAll('[data-lightbox]').forEach(img => {
        img.addEventListener('click', () => openLightbox(img.src));
    });
    lightbox?.addEventListener('click', closeLightbox);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeLightbox(); });
    
    function openLightbox(src) {
        lightboxImg.src = src;
        lightbox.classList.add('active');
        document.body.style.overflow = 'hidden';
    }
    function closeLightbox() {
        lightbox.classList.remove('active');
        document.body.style.overflow = '';
    }
    
    // File handling
    document.getElementById('addImageBtn')?.addEventListener('click', () => imageInput.click());
    document.getElementById('addFileBtn')?.addEventListener('click', () => fileInput.click());
    imageInput?.addEventListener('change', e => handleFiles(e.target));
    fileInput?.addEventListener('change', e => handleFiles(e.target));
    
    function handleFiles(input) {
        Array.from(input.files).forEach(file => {
            if (file.size > 10 * 1024 * 1024) { alert('File too large: ' + file.name); return; }
            const reader = new FileReader();
            reader.onload = e => {
                pendingFiles.push({ file, name: file.name, type: file.type.startsWith('image/') ? 'image' : 'file', dataUrl: e.target.result });
                updatePreview();
            };
            reader.readAsDataURL(file);
        });
        input.value = '';
    }
    
    function updatePreview() {
        if (!pendingFiles.length) { attachmentPreview.style.display = 'none'; attachmentPreview.innerHTML = ''; return; }
        attachmentPreview.style.display = 'flex';
        attachmentPreview.innerHTML = pendingFiles.map((f, i) => 
            f.type === 'image' 
                ? '<div class="attachment-item image-item"><img src="' + f.dataUrl + '" alt=""><button type="button" data-remove="' + i + '"><i class="fa-solid fa-times"></i></button></div>'
                : '<div class="attachment-item file-item"><i class="fa-solid fa-file"></i><span>' + escapeHtml(f.name) + '</span><button type="button" data-remove="' + i + '"><i class="fa-solid fa-times"></i></button></div>'
        ).join('');
        attachmentPreview.querySelectorAll('[data-remove]').forEach(btn => {
            btn.addEventListener('click', () => { pendingFiles.splice(parseInt(btn.dataset.remove), 1); updatePreview(); });
        });
    }
    
    // Submit reply
    replyForm?.addEventListener('submit', async function(e) {
        e.preventDefault();
        const message = replyMessage.value.trim();
        if (!message && !pendingFiles.length) { alert('Please enter a message or attach a file.'); return; }
        
        replyBtn.disabled = true;
        replyBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';
        justSentReply = true;
        
        try {
            let attachments = [];
            for (const f of pendingFiles) {
                try {
                    const fd = new FormData();
                    fd.append('file', f.file);
                    fd.append('ticket_id', ticketId);
                    const res = await fetch('<?php echo esc_url(rest_url('oversee/v1/tickets/public/upload')); ?>', { method: 'POST', body: fd });
                    if (res.ok) {
                        const data = await res.json();
                        if (data.url) attachments.push({ name: f.name, url: data.url, type: f.type });
                    }
                } catch (err) {}
            }
            
            let fullMessage = message;
            if (attachments.length) fullMessage += '\n[ATTACHMENTS:' + JSON.stringify(attachments) + ']';
            
            const res = await fetch('<?php echo esc_url(rest_url('oversee/v1/tickets/public/reply')); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ ticket_id: ticketId, email: customerEmail, message: fullMessage })
            });
            if (!res.ok) throw new Error('Failed to send reply');
            
            // Add to UI
            const newMsg = document.createElement('div');
            newMsg.className = 'chat-message chat-message--outgoing';
            let attHtml = attachments.length ? '<div class="chat-message__attachments">' + attachments.map(a => 
                a.type === 'image' ? '<img src="' + a.url + '" alt="" class="chat-attachment-img" data-lightbox>' : '<a href="' + a.url + '" target="_blank" class="chat-attachment-file"><i class="fa-solid fa-file"></i> ' + escapeHtml(a.name) + '</a>'
            ).join('') + '</div>' : '';
            newMsg.innerHTML = '<span class="chat-message__author"><i class="fa-solid fa-user"></i> You</span><span class="chat-message__time">Just now</span>' + (message ? '<p class="chat-message__text">' + escapeHtml(message).replace(/\n/g, '<br>') + '</p>' : '') + attHtml;
            messagesList.appendChild(newMsg);
            messagesList.scrollTop = messagesList.scrollHeight;
            
            // Setup lightbox for new images
            newMsg.querySelectorAll('[data-lightbox]').forEach(img => { img.addEventListener('click', () => openLightbox(img.src)); });
            
            lastMessageCount++;
            setTimeout(() => { justSentReply = false; }, 10000);
            
            replyMessage.value = '';
            pendingFiles = [];
            updatePreview();
        } catch (err) {
            alert('Failed to send reply. Please try again.');
            justSentReply = false;
        } finally {
            replyBtn.disabled = false;
            replyBtn.innerHTML = '<i class="fa-solid fa-arrow-up"></i>';
        }
    });
    
    // Polling
    setInterval(async () => {
        try {
            const res = await fetch('<?php echo esc_url(rest_url('oversee/v1/tickets/public/' . $ticket['id'])); ?>?email=' + encodeURIComponent(customerEmail));
            if (!res.ok) return;
            const data = await res.json();
            const newCount = (data.replies?.length || 0) + 1;
            if (newCount > lastMessageCount) {
                const replies = data.replies || [];
                const latest = replies[replies.length - 1];
                if (!justSentReply && latest && latest.author_type === 'agent') {
                    notificationBanner.classList.add('show');
                    setTimeout(() => notificationBanner.classList.remove('show'), 10000);
                }
                lastMessageCount = newCount;
            }
        } catch (err) {}
    }, 5000);
    
    document.getElementById('dismissNotification')?.addEventListener('click', () => notificationBanner.classList.remove('show'));
    
    // Scroll to bottom on load
    if (messagesList) messagesList.scrollTop = messagesList.scrollHeight;
    
    function escapeHtml(t) { if (!t) return ''; const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
})();
</script>
<?php endif; ?>
