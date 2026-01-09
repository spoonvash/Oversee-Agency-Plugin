<?php
/**
 * Submit Ticket Page - Content Only
 * All CSS is in /assets/css/public.css
 */

if (!defined('ABSPATH')) {
    exit;
}

$success = isset($_GET['success']);
?>

<nav class="breadcrumb">
    <div class="breadcrumb-inner">
        <a href="<?php echo esc_url(oversee_kb_url()); ?>"><i class="fa-solid fa-home"></i> Help Center</a>
        <span class="separator"><i class="fa-solid fa-chevron-right"></i></span>
        <span class="current">Submit a Ticket</span>
    </div>
</nav>

<section class="form-section">
    <div class="form-card">
        <?php if ($success): ?>
        <div class="success-state">
            <div class="success-icon"><i class="fa-solid fa-check"></i></div>
            <h3>Ticket Submitted Successfully!</h3>
            <p class="success-message">We've received your request and will respond shortly.</p>
            <div class="success-actions">
                <a href="<?php echo esc_url(oversee_support_url('tickets')); ?>" class="btn btn-primary">View My Tickets</a>
                <a href="<?php echo esc_url(oversee_kb_url()); ?>" class="btn btn-secondary">Browse Knowledge Base</a>
            </div>
        </div>
        <?php else: ?>
        <h1>Submit a Support Request</h1>
        <p>Fill out the form below and our team will get back to you as soon as possible.</p>
        
        <div id="errorAlert" class="alert alert-error hidden"></div>
        
        <form id="ticketForm">
            <div class="form-honeypot" aria-hidden="true">
                <input type="text" name="website_url" id="website_url" tabindex="-1" autocomplete="off">
            </div>
            <input type="hidden" name="form_token" id="form_token" value="">
            
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
                    <option value="low" selected>Normal</option>
                    <option value="high">High - Urgent issue</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="message">Message <span class="required">*</span></label>
                <textarea id="message" name="message" class="form-control" required placeholder="Please describe your issue in detail." rows="5"></textarea>
            </div>
            
            <div class="form-group">
                <label>Attachments (optional)</label>
                <div class="file-upload" id="fileUploadArea">
                    <i class="fa-solid fa-cloud-upload"></i>
                    <p>Click to upload files or drag and drop</p>
                    <span class="file-hint">Images, documents, screenshots (max 10MB each)</span>
                    <input type="file" id="attachments" name="attachments[]" multiple class="sr-only" accept="image/*,.pdf,.doc,.docx,.txt,.zip">
                </div>
                <div id="filePreview" class="file-preview"></div>
            </div>
            
            <div class="form-actions">
                <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                    <i class="fa-solid fa-paper-plane"></i> Submit Ticket
                </button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</section>

<script>
(function() {
    let selectedFiles = [];
    const form = document.getElementById('ticketForm');
    const fileUploadArea = document.getElementById('fileUploadArea');
    const fileInput = document.getElementById('attachments');
    const filePreview = document.getElementById('filePreview');
    
    if (!form) return;
    
    // Get form token
    fetch('<?php echo esc_url(rest_url('oversee/v1/form-token')); ?>').then(r => r.json()).then(d => {
        if (d.token) document.getElementById('form_token').value = d.token;
    }).catch(() => {});

    // File upload handling
    fileUploadArea?.addEventListener('click', () => fileInput.click());
    fileInput?.addEventListener('change', handleFileSelect);
    
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(evt => {
        fileUploadArea?.addEventListener(evt, e => { e.preventDefault(); e.stopPropagation(); }, false);
    });
    fileUploadArea?.addEventListener('dragover', () => fileUploadArea.classList.add('dragover'));
    fileUploadArea?.addEventListener('dragleave', () => fileUploadArea.classList.remove('dragover'));
    fileUploadArea?.addEventListener('drop', e => {
        fileUploadArea.classList.remove('dragover');
        fileInput.files = e.dataTransfer.files;
        handleFileSelect({ target: fileInput });
    });

    function handleFileSelect(e) {
        const files = Array.from(e.target.files);
        files.forEach(file => {
            if (file.size > 10 * 1024 * 1024) { alert('File too large: ' + file.name); return; }
            const reader = new FileReader();
            reader.onload = ev => {
                selectedFiles.push({ file, name: file.name, type: file.type, preview: ev.target.result });
                updatePreview();
            };
            reader.readAsDataURL(file);
        });
        e.target.value = '';
    }

    function updatePreview() {
        if (!selectedFiles.length) { filePreview.innerHTML = ''; return; }
        filePreview.innerHTML = selectedFiles.map((f, i) => {
            if (f.type.startsWith('image/')) {
                return '<div class="preview-item image"><img src="' + f.preview + '" alt=""><button type="button" onclick="removeFile(' + i + ')"><i class="fa-solid fa-times"></i></button><span class="preview-name">' + escapeHtml(f.name) + '</span></div>';
            }
            return '<div class="preview-item file"><i class="fa-solid fa-file"></i><span>' + escapeHtml(f.name) + '</span><button type="button" onclick="removeFile(' + i + ')"><i class="fa-solid fa-times"></i></button></div>';
        }).join('');
    }

    window.removeFile = function(i) { selectedFiles.splice(i, 1); updatePreview(); };

    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        const btn = document.getElementById('submitBtn');
        const errorAlert = document.getElementById('errorAlert');
        
        btn.disabled = true;
        btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';
        errorAlert.classList.add('hidden');
        
        let description = document.getElementById('message').value;
        let attachments = [];
        
        // Upload files
        for (const f of selectedFiles) {
            try {
                const fd = new FormData();
                fd.append('file', f.file);
                const res = await fetch('<?php echo esc_url(rest_url('oversee/v1/tickets/public/upload')); ?>', { method: 'POST', body: fd });
                if (res.ok) {
                    const data = await res.json();
                    if (data.url) attachments.push({ name: f.name, url: data.url, type: f.type.startsWith('image/') ? 'image' : 'file' });
                }
            } catch (err) {}
        }
        
        if (attachments.length) description += '\n[ATTACHMENTS:' + JSON.stringify(attachments) + ']';
        
        try {
            const res = await fetch('<?php echo esc_url(rest_url('oversee/v1/tickets/public')); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    customer_name: document.getElementById('name').value,
                    customer_email: document.getElementById('email').value,
                    subject: document.getElementById('subject').value,
                    description: description,
                    priority: document.getElementById('priority').value,
                    form_token: document.getElementById('form_token').value,
                    website_url: document.getElementById('website_url').value
                })
            });
            const result = await res.json();
            if (!res.ok) throw new Error(result.message || 'Failed to submit ticket');
            localStorage.setItem('oversee_customer_email', document.getElementById('email').value);
            window.location.href = '<?php echo esc_url(oversee_support_url('submit')); ?>?success=1';
        } catch (err) {
            errorAlert.textContent = err.message;
            errorAlert.classList.remove('hidden');
            btn.disabled = false;
            btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Submit Ticket';
        }
    });

    function escapeHtml(t) { const d = document.createElement('div'); d.textContent = t; return d.innerHTML; }
})();
</script>
