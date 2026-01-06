<?php
/**
 * Submit Ticket Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$portal_title = oversee_get_portal_title();
$success = isset($_GET['success']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit a Ticket - <?php echo esc_html($portal_title); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo esc_url(OVERSEE_PLUGIN_URL . 'assets/css/public.css?v=2.0.0'); ?>">
</head>
<body>
    <div class="page-wrapper">
        <!-- Header -->
        <header class="site-header">
            <div class="header-inner">
                <nav class="header-nav">
                    <a href="<?php echo esc_url(oversee_kb_url()); ?>">
                        <i class="fa-solid fa-book"></i>
                        <span>Knowledge Base</span>
                    </a>
                    <a href="<?php echo esc_url(oversee_support_url('tickets')); ?>">
                        <i class="fa-solid fa-ticket"></i>
                        <span>My Tickets</span>
                    </a>
                    <a href="<?php echo esc_url(oversee_support_url('zoom')); ?>">
                        <i class="fa-solid fa-video"></i>
                        <span>Live Support</span>
                    </a>
                </nav>
                <div class="header-actions">
                    <a href="<?php echo esc_url(oversee_support_url('submit')); ?>" class="btn btn-primary active">
                        <i class="fa-solid fa-plus"></i>
                        <span>Submit Ticket</span>
                    </a>
                </div>
            </div>
        </header>

        <!-- Breadcrumb -->
        <nav class="breadcrumb">
            <div class="breadcrumb-inner">
                <a href="<?php echo esc_url(oversee_kb_url()); ?>">
                    <i class="fa-solid fa-home"></i> Help Center
                </a>
                <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
                <span class="current">Submit a Ticket</span>
            </div>
        </nav>

        <!-- Main Content -->
        <main class="main-content">
            <div class="form-section">
                <div class="form-card">
                    <?php if ($success): ?>
                    <div class="empty-state" style="padding: 40px 20px;">
                        <div style="width:64px;height:64px;background:#d1fae5;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
                            <i class="fa-solid fa-check" style="font-size:28px;color:#059669;"></i>
                        </div>
                        <h3>Ticket Submitted Successfully!</h3>
                        <p style="margin-bottom: 24px;">We've received your request and will respond shortly. A confirmation has been sent to your email.</p>
                        <div style="display:flex;gap:12px;justify-content:center;">
                            <a href="<?php echo esc_url(oversee_support_url('tickets')); ?>" class="btn btn-primary">View My Tickets</a>
                            <a href="<?php echo esc_url(oversee_kb_url()); ?>" class="btn btn-secondary">Browse Knowledge Base</a>
                        </div>
                    </div>
                    <?php else: ?>
                    <h1>Submit a Support Request</h1>
                    <p>Fill out the form below and our team will get back to you as soon as possible.</p>
                    
                    <div id="errorAlert" class="alert alert-error" style="display: none;"></div>
                    
                    <form id="ticketForm" onsubmit="submitTicket(event)">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="name">Your Name <span class="required">*</span></label>
                                <input type="text" id="name" name="name" class="form-control" required placeholder="John Doe">
                            </div>
                            <div class="form-group">
                                <label for="email">Email Address <span class="required">*</span></label>
                                <input type="email" id="email" name="email" class="form-control" required placeholder="john@example.com">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="subject">Subject <span class="required">*</span></label>
                            <input type="text" id="subject" name="subject" class="form-control" required placeholder="Brief description of your issue">
                        </div>
                        
                        <div class="form-group">
                            <label for="priority">Priority</label>
                            <select id="priority" name="priority" class="form-control">
                                <option value="normal">Normal</option>
                                <option value="high">High - Urgent issue</option>
                                <option value="low">Low - Not urgent</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="message">Message <span class="required">*</span></label>
                            <textarea id="message" name="message" class="form-control" required placeholder="Please describe your issue in detail. Include any relevant information that might help us assist you faster."></textarea>
                        </div>
                        
                        <div class="form-group">
                            <label>Attachments (optional)</label>
                            <div class="file-upload" onclick="document.getElementById('attachments').click()">
                                <i class="fa-solid fa-cloud-upload"></i>
                                <p id="fileLabel">Click to upload files or drag and drop</p>
                                <input type="file" id="attachments" name="attachments[]" multiple style="display:none" onchange="updateFileLabel(this)">
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                <i class="fa-solid fa-paper-plane"></i> Submit Ticket
                            </button>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </main>

        <!-- Footer -->
        <footer class="site-footer">
            <p>Powered by <a href="https://overseecrm.com" target="_blank">OverseeCRM</a></p>
        </footer>
    </div>

    <script>
        async function submitTicket(e) {
            e.preventDefault();
            
            const btn = document.getElementById('submitBtn');
            const errorAlert = document.getElementById('errorAlert');
            
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';
            errorAlert.style.display = 'none';
            
            const formData = {
                customer_name: document.getElementById('name').value,
                customer_email: document.getElementById('email').value,
                subject: document.getElementById('subject').value,
                description: document.getElementById('message').value,
                priority: document.getElementById('priority').value
            };
            
            try {
                const response = await fetch('<?php echo esc_url(rest_url('oversee/v1/tickets/public')); ?>', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(formData)
                });
                
                const result = await response.json();
                
                if (!response.ok) {
                    throw new Error(result.message || 'Failed to submit ticket');
                }
                
                window.location.href = '<?php echo esc_url(oversee_support_url('submit')); ?>?success=1';
                
            } catch (error) {
                errorAlert.textContent = error.message;
                errorAlert.style.display = 'block';
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Submit Ticket';
            }
        }
        
        function updateFileLabel(input) {
            const label = document.getElementById('fileLabel');
            if (input.files.length > 0) {
                const names = Array.from(input.files).map(f => f.name).join(', ');
                label.textContent = names;
            } else {
                label.textContent = 'Click to upload files or drag and drop';
            }
        }
    </script>
</body>
</html>
