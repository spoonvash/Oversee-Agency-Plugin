/**
 * Oversee UX Utilities
 * Production-grade UX helpers for toasts, validation, skeletons, and error handling
 * Version: 2.4.0
 */

(function(window) {
    'use strict';

    // ==========================================================================
    // TOAST NOTIFICATIONS
    // ==========================================================================

    const Toast = {
        container: null,

        init() {
            if (this.container) return;
            this.container = document.createElement('div');
            this.container.className = 'toast-container';
            this.container.setAttribute('aria-live', 'polite');
            this.container.setAttribute('aria-atomic', 'true');
            document.body.appendChild(this.container);
        },

        show(message, type = 'info', options = {}) {
            this.init();

            const {
                title = this.getDefaultTitle(type),
                duration = 5000,
                dismissible = true
            } = options;

            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
                <i class="toast-icon fa-solid ${this.getIcon(type)}"></i>
                <div class="toast-content">
                    ${title ? `<div class="toast-title">${this.escapeHtml(title)}</div>` : ''}
                    <div class="toast-message">${this.escapeHtml(message)}</div>
                </div>
                ${dismissible ? '<button class="toast-close" aria-label="Dismiss"><i class="fa-solid fa-times"></i></button>' : ''}
            `;

            if (dismissible) {
                toast.querySelector('.toast-close').addEventListener('click', () => this.dismiss(toast));
            }

            this.container.appendChild(toast);

            if (duration > 0) {
                setTimeout(() => this.dismiss(toast), duration);
            }

            return toast;
        },

        success(message, options = {}) {
            return this.show(message, 'success', options);
        },

        error(message, options = {}) {
            return this.show(message, 'error', { duration: 8000, ...options });
        },

        warning(message, options = {}) {
            return this.show(message, 'warning', options);
        },

        info(message, options = {}) {
            return this.show(message, 'info', options);
        },

        dismiss(toast) {
            if (!toast || toast.classList.contains('toast-out')) return;
            toast.classList.add('toast-out');
            setTimeout(() => toast.remove(), 300);
        },

        dismissAll() {
            if (!this.container) return;
            this.container.querySelectorAll('.toast').forEach(t => this.dismiss(t));
        },

        getIcon(type) {
            const icons = {
                success: 'fa-check-circle',
                error: 'fa-exclamation-circle',
                warning: 'fa-exclamation-triangle',
                info: 'fa-info-circle'
            };
            return icons[type] || icons.info;
        },

        getDefaultTitle(type) {
            const titles = {
                success: 'Success',
                error: 'Error',
                warning: 'Warning',
                info: ''
            };
            return titles[type] || '';
        },

        escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // ==========================================================================
    // FORM VALIDATION
    // ==========================================================================

    const FormValidator = {
        validators: {
            required: (value) => ({
                valid: value.trim().length > 0,
                message: 'This field is required'
            }),

            email: (value) => ({
                valid: !value || /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value),
                message: 'Please enter a valid email address'
            }),

            minLength: (value, min) => ({
                valid: !value || value.length >= min,
                message: `Must be at least ${min} characters`
            }),

            maxLength: (value, max) => ({
                valid: !value || value.length <= max,
                message: `Must be no more than ${max} characters`
            }),

            pattern: (value, pattern, message) => ({
                valid: !value || new RegExp(pattern).test(value),
                message: message || 'Invalid format'
            })
        },

        validate(field, rules) {
            const value = field.value;
            const errors = [];

            for (const rule of rules) {
                let result;

                if (typeof rule === 'string') {
                    result = this.validators[rule]?.(value);
                } else if (typeof rule === 'object') {
                    const { type, ...params } = rule;
                    result = this.validators[type]?.(value, ...Object.values(params));
                }

                if (result && !result.valid) {
                    errors.push(result.message);
                }
            }

            return errors;
        },

        showError(field, message) {
            this.clearError(field);

            const group = field.closest('.form-group');
            if (!group) return;

            group.classList.add('has-error');
            group.classList.remove('has-success');

            const error = document.createElement('div');
            error.className = 'field-error';
            error.innerHTML = `<i class="fa-solid fa-exclamation-circle"></i> ${this.escapeHtml(message)}`;

            // Insert after field or field wrapper
            const insertAfter = field.closest('.form-control-wrapper') || field;
            insertAfter.parentNode.insertBefore(error, insertAfter.nextSibling);

            // Set aria attributes
            field.setAttribute('aria-invalid', 'true');
            field.setAttribute('aria-describedby', `${field.id}-error`);
            error.id = `${field.id}-error`;
        },

        showSuccess(field) {
            this.clearError(field);

            const group = field.closest('.form-group');
            if (!group) return;

            group.classList.add('has-success');
            field.setAttribute('aria-invalid', 'false');
        },

        clearError(field) {
            const group = field.closest('.form-group');
            if (!group) return;

            group.classList.remove('has-error', 'has-success');
            group.querySelectorAll('.field-error').forEach(e => e.remove());
            field.removeAttribute('aria-invalid');
            field.removeAttribute('aria-describedby');
        },

        clearAllErrors(form) {
            form.querySelectorAll('.form-group').forEach(group => {
                group.classList.remove('has-error', 'has-success');
                group.querySelectorAll('.field-error').forEach(e => e.remove());
            });
        },

        validateField(field, rules) {
            const errors = this.validate(field, rules);

            if (errors.length > 0) {
                this.showError(field, errors[0]);
                return false;
            } else if (field.value.trim()) {
                this.showSuccess(field);
            } else {
                this.clearError(field);
            }

            return true;
        },

        setupRealTimeValidation(field, rules) {
            const validate = () => this.validateField(field, rules);

            field.addEventListener('blur', validate);
            field.addEventListener('input', debounce(() => {
                if (field.closest('.form-group')?.classList.contains('has-error')) {
                    validate();
                }
            }, 300));
        },

        escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    // ==========================================================================
    // SKELETON LOADERS
    // ==========================================================================

    const Skeleton = {
        ticketRow() {
            return `
                <div class="skeleton-ticket-row">
                    <div class="skeleton-info">
                        <div class="skeleton skeleton-text short"></div>
                        <div class="skeleton skeleton-text long"></div>
                        <div class="skeleton skeleton-text medium"></div>
                    </div>
                    <div class="skeleton skeleton-badge"></div>
                </div>
            `;
        },

        ticketList(count = 5) {
            return Array(count).fill(this.ticketRow()).join('');
        },

        categoryCard() {
            return `
                <div class="skeleton-category">
                    <div class="skeleton skeleton-icon"></div>
                    <div class="skeleton-content">
                        <div class="skeleton skeleton-text medium"></div>
                        <div class="skeleton skeleton-text short"></div>
                    </div>
                </div>
            `;
        },

        categoryGrid(count = 6) {
            return Array(count).fill(this.categoryCard()).join('');
        },

        chatMessage(incoming = false) {
            const className = incoming ? 'incoming' : 'outgoing';
            return `
                <div class="skeleton-message ${className}">
                    <div class="skeleton skeleton-text short"></div>
                    <div class="skeleton skeleton-text long"></div>
                    <div class="skeleton skeleton-text medium"></div>
                </div>
            `;
        },

        chatMessages(count = 5) {
            return Array(count).fill(0).map((_, i) =>
                this.chatMessage(i % 2 === 1)
            ).join('');
        },

        articleCard() {
            return `
                <div class="skeleton-card">
                    <div class="skeleton skeleton-title"></div>
                    <div class="skeleton skeleton-text long"></div>
                    <div class="skeleton skeleton-text medium"></div>
                    <div class="skeleton skeleton-text long"></div>
                </div>
            `;
        }
    };

    // ==========================================================================
    // EMPTY STATES
    // ==========================================================================

    const EmptyState = {
        render(options = {}) {
            const {
                icon = 'fa-inbox',
                title = 'Nothing here yet',
                message = '',
                action = null,
                tip = null
            } = options;

            let html = `
                <div class="empty-state-enhanced">
                    <div class="empty-icon">
                        <i class="fa-solid ${icon}"></i>
                    </div>
                    <h3>${escapeHtml(title)}</h3>
                    ${message ? `<p>${escapeHtml(message)}</p>` : ''}
            `;

            if (action) {
                html += `
                    <div class="empty-actions">
                        <a href="${escapeHtml(action.url)}" class="btn btn-primary">
                            ${action.icon ? `<i class="fa-solid ${action.icon}"></i>` : ''}
                            ${escapeHtml(action.label)}
                        </a>
                    </div>
                `;
            }

            if (tip) {
                html += `
                    <div class="empty-tip">
                        <p>${tip}</p>
                    </div>
                `;
            }

            html += '</div>';
            return html;
        },

        noTickets(submitUrl = '/support/submit') {
            return this.render({
                icon: 'fa-ticket',
                title: 'No Tickets Found',
                message: 'You haven\'t submitted any support tickets yet. We\'re here to help when you need us!',
                action: {
                    url: submitUrl,
                    icon: 'fa-plus',
                    label: 'Submit Your First Ticket'
                },
                tip: 'Tip: Check our <a href="/support">Knowledge Base</a> for quick answers to common questions.'
            });
        },

        noResults(query = '') {
            return this.render({
                icon: 'fa-search',
                title: 'No Results Found',
                message: query ? `We couldn't find anything matching "${escapeHtml(query)}".` : 'Try adjusting your search or filters.',
                tip: 'Tip: Try using different keywords or check for typos.'
            });
        },

        noCategories() {
            return this.render({
                icon: 'fa-folder-open',
                title: 'No Categories Yet',
                message: 'Knowledge base categories will appear here once they\'re added.'
            });
        },

        noArticles() {
            return this.render({
                icon: 'fa-file-lines',
                title: 'No Articles Yet',
                message: 'Articles in this category will appear here soon.'
            });
        },

        error(message = 'Something went wrong') {
            return this.render({
                icon: 'fa-exclamation-circle',
                title: 'Oops!',
                message: message,
                tip: 'Please try refreshing the page or <a href="/support/submit">contact support</a> if the problem persists.'
            });
        }
    };

    // ==========================================================================
    // API ERROR HANDLER
    // ==========================================================================

    const ApiError = {
        messages: {
            400: 'Invalid request. Please check your input and try again.',
            401: 'You need to be logged in to perform this action.',
            403: 'You don\'t have permission to perform this action.',
            404: 'The requested resource was not found.',
            422: 'Please check your input and try again.',
            429: 'Too many requests. Please wait a moment and try again.',
            500: 'Something went wrong on our end. Please try again later.',
            502: 'Service temporarily unavailable. Please try again later.',
            503: 'Service temporarily unavailable. Please try again later.',
            default: 'An unexpected error occurred. Please try again.'
        },

        handle(error, options = {}) {
            const { showToast = true, logError = true } = options;

            let message = this.messages.default;
            let status = null;

            if (error instanceof Response) {
                status = error.status;
                message = this.messages[status] || this.messages.default;
            } else if (error?.message) {
                message = error.message;
            } else if (typeof error === 'string') {
                message = error;
            }

            if (logError) {
                console.error('[API Error]', { status, message, error });
            }

            if (showToast) {
                Toast.error(message);
            }

            return { status, message };
        },

        async parseResponse(response) {
            if (response.ok) {
                try {
                    return await response.json();
                } catch {
                    return null;
                }
            }

            let errorMessage;

            try {
                const data = await response.json();
                errorMessage = data.message || data.error || this.messages[response.status];
            } catch {
                errorMessage = this.messages[response.status] || this.messages.default;
            }

            const error = new Error(errorMessage);
            error.status = response.status;
            throw error;
        }
    };

    // ==========================================================================
    // UTILITIES
    // ==========================================================================

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }

    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function formatDate(dateString, options = {}) {
        const date = new Date(dateString);
        const defaults = { month: 'short', day: 'numeric', year: 'numeric' };
        return date.toLocaleDateString('en-US', { ...defaults, ...options });
    }

    function formatRelativeTime(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diff = now - date;

        const minute = 60 * 1000;
        const hour = 60 * minute;
        const day = 24 * hour;
        const week = 7 * day;

        if (diff < minute) return 'Just now';
        if (diff < hour) return `${Math.floor(diff / minute)}m ago`;
        if (diff < day) return `${Math.floor(diff / hour)}h ago`;
        if (diff < week) return `${Math.floor(diff / day)}d ago`;

        return formatDate(dateString);
    }

    // ==========================================================================
    // LOADING STATES
    // ==========================================================================

    const Loading = {
        overlay: null,

        show(text = 'Loading...') {
            if (!this.overlay) {
                this.overlay = document.createElement('div');
                this.overlay.className = 'loading-overlay';
                this.overlay.innerHTML = `
                    <div class="loading-spinner"></div>
                    <div class="loading-text">${escapeHtml(text)}</div>
                `;
                document.body.appendChild(this.overlay);
            } else {
                this.overlay.querySelector('.loading-text').textContent = text;
            }

            // Force reflow
            this.overlay.offsetHeight;
            this.overlay.classList.add('active');
        },

        hide() {
            if (this.overlay) {
                this.overlay.classList.remove('active');
            }
        },

        button(btn, loading = true, loadingText = 'Loading...') {
            if (loading) {
                btn.disabled = true;
                btn.dataset.originalHtml = btn.innerHTML;
                btn.innerHTML = `<i class="fa-solid fa-spinner fa-spin"></i> ${loadingText}`;
            } else {
                btn.disabled = false;
                if (btn.dataset.originalHtml) {
                    btn.innerHTML = btn.dataset.originalHtml;
                    delete btn.dataset.originalHtml;
                }
            }
        }
    };

    // ==========================================================================
    // EXPORT
    // ==========================================================================

    window.OverseeUX = {
        Toast,
        FormValidator,
        Skeleton,
        EmptyState,
        ApiError,
        Loading,
        debounce,
        escapeHtml,
        formatDate,
        formatRelativeTime
    };

})(window);
