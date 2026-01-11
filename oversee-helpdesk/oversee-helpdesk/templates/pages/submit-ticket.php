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
    const { Toast, FormValidator, Loading, ApiError, escapeHtml } = window.OverseeUX || {};

    let selectedFiles = [];
    const form = document.getElementById('ticketForm');
    const fileUploadArea = document.getElementById('fileUploadArea');
    const fileInput = document.getElementById('attachments');
    const filePreview = document.getElementById('filePreview');
    const submitBtn = document.getElementById('submitBtn');

    if (!form) return;

    // Field references
    const fields = {
        name: document.getElementById('name'),
        email: document.getElementById('email'),
        subject: document.getElementById('subject'),
        message: document.getElementById('message')
    };

    // Validation rules
    const validationRules = {
        name: ['required', { type: 'minLength', min: 2 }],
        email: ['required', 'email'],
        subject: ['required', { type: 'minLength', min: 5 }, { type: 'maxLength', max: 200 }],
        message: ['required', { type: 'minLength', min: 20 }]
    };

    // Setup real-time validation
    if (FormValidator) {
        Object.entries(fields).forEach(([name, field]) => {
            if (field && validationRules[name]) {
                FormValidator.setupRealTimeValidation(field, validationRules[name]);
            }
        });

        // Add character counter for message
        if (fields.message) {
            const counter = document.createElement('span');
            counter.className = 'char-counter';
            fields.message.parentNode.appendChild(counter);

            const updateCounter = () => {
                const len = fields.message.value.length;
                const min = 20;
                counter.textContent = len < min ? `${min - len} more characters needed` : `${len} characters`;
                counter.className = 'char-counter' + (len < min ? ' warning' : '');
            };

            fields.message.addEventListener('input', updateCounter);
            updateCounter();
        }
    }

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
            if (file.size > 10 * 1024 * 1024) {
                if (Toast) {
                    Toast.warning(`File "${file.name}" is too large. Maximum size is 10MB.`);
                } else {
                    alert('File too large: ' + file.name);
                }
                return;
            }
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
            const safeName = escapeHtmlLocal(f.name);
            if (f.type.startsWith('image/')) {
                return `<div class="preview-item image"><img src="${f.preview}" alt=""><button type="button" onclick="removeFile(${i})"><i class="fa-solid fa-times"></i></button><span class="preview-name">${safeName}</span></div>`;
            }
            return `<div class="preview-item file"><i class="fa-solid fa-file"></i><span>${safeName}</span><button type="button" onclick="removeFile(${i})"><i class="fa-solid fa-times"></i></button></div>`;
        }).join('');
    }

    window.removeFile = function(i) { selectedFiles.splice(i, 1); updatePreview(); };

    // Validate all fields
    function validateForm() {
        if (!FormValidator) return true;

        let isValid = true;
        FormValidator.clearAllErrors(form);

        Object.entries(fields).forEach(([name, field]) => {
            if (field && validationRules[name]) {
                const fieldValid = FormValidator.validateField(field, validationRules[name]);
                if (!fieldValid) isValid = false;
            }
        });

        return isValid;
    }

    form.addEventListener('submit', async function(e) {
        e.preventDefault();

        // Validate before submit
        if (!validateForm()) {
            if (Toast) {
                Toast.error('Please fix the errors in the form before submitting.');
            }
            // Focus first error field
            const firstError = form.querySelector('.form-group.has-error .form-control');
            if (firstError) firstError.focus();
            return;
        }

        const errorAlert = document.getElementById('errorAlert');

        // Show loading state
        if (Loading) {
            Loading.button(submitBtn, true, 'Submitting...');
        } else {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';
        }
        errorAlert.classList.add('hidden');

        let description = fields.message.value;
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
            } catch (err) {
                console.error('File upload error:', err);
            }
        }

        if (attachments.length) description += '\n[ATTACHMENTS:' + JSON.stringify(attachments) + ']';

        try {
            const res = await fetch('<?php echo esc_url(rest_url('oversee/v1/tickets/public')); ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    customer_name: fields.name.value,
                    customer_email: fields.email.value,
                    subject: fields.subject.value,
                    description: description,
                    priority: document.getElementById('priority').value,
                    form_token: document.getElementById('form_token').value,
                    website_url: document.getElementById('website_url').value
                })
            });

            const result = await res.json();

            if (!res.ok) {
                throw new Error(result.message || 'Failed to submit ticket. Please try again.');
            }

            localStorage.setItem('oversee_customer_email', fields.email.value);

            if (Toast) {
                Toast.success('Ticket submitted successfully!');
            }

            window.location.href = '<?php echo esc_url(oversee_support_url('submit')); ?>?success=1';

        } catch (err) {
            const message = err.message || 'An unexpected error occurred. Please try again.';

            if (Toast) {
                Toast.error(message);
            }

            errorAlert.innerHTML = `<i class="fa-solid fa-exclamation-circle"></i> ${escapeHtmlLocal(message)}`;
            errorAlert.classList.remove('hidden');

            // Reset button
            if (Loading) {
                Loading.button(submitBtn, false);
            } else {
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> Submit Ticket';
            }

            // Scroll to error
            errorAlert.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });

    function escapeHtmlLocal(t) {
        if (escapeHtml) return escapeHtml(t);
        const d = document.createElement('div');
        d.textContent = t;
        return d.innerHTML;
    }
})();
</script>
