/**
 * Oversee Admin Utilities
 * Version: 2.5.0 - Dark Mode Support
 */

/**
 * Theme Manager - Handles light/dark mode switching
 */
const OverseeTheme = {
    mode: 'auto',

    init(settingsMode) {
        this.mode = settingsMode || 'auto';
        let theme = 'light';

        if (this.mode === 'dark_only') {
            theme = 'dark';
        } else if (this.mode === 'light_only') {
            theme = 'light';
        } else if (this.mode === 'auto') {
            theme = this.getSystemTheme();
        } else if (this.mode === 'user_choice') {
            theme = this.getSavedTheme() || this.getSystemTheme();
        }

        this.apply(theme);
        this.watchSystemChanges();
        this.updateToggleIcon();
    },

    getSavedTheme() {
        return localStorage.getItem('oversee_theme');
    },

    getSystemTheme() {
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    },

    apply(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        if (this.mode === 'user_choice') {
            localStorage.setItem('oversee_theme', theme);
        }
        this.updateToggleIcon();
    },

    toggle() {
        if (this.mode !== 'user_choice') return;
        const current = document.documentElement.getAttribute('data-theme');
        this.apply(current === 'dark' ? 'light' : 'dark');
    },

    watchSystemChanges() {
        window.matchMedia('(prefers-color-scheme: dark)')
            .addEventListener('change', (e) => {
                if (this.mode === 'auto') {
                    this.apply(e.matches ? 'dark' : 'light');
                }
            });
    },

    updateToggleIcon() {
        const toggle = document.querySelector('.theme-toggle');
        if (!toggle) return;
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        const icon = toggle.querySelector('.theme-icon');
        if (icon) {
            icon.innerHTML = isDark ? '<i class="fa-solid fa-sun"></i>' : '<i class="fa-solid fa-moon"></i>';
        }
    },

    getCurrentTheme() {
        return document.documentElement.getAttribute('data-theme') || 'light';
    }
};

// Initialize theme immediately to prevent flash
(function() {
    const mode = window.overseeThemeMode || 'auto';
    let theme = 'light';
    if (mode === 'dark_only') {
        theme = 'dark';
    } else if (mode === 'auto' || mode === 'user_choice') {
        const saved = localStorage.getItem('oversee_theme');
        if (mode === 'user_choice' && saved) {
            theme = saved;
        } else if (window.matchMedia('(prefers-color-scheme: dark)').matches) {
            theme = 'dark';
        }
    }
    document.documentElement.setAttribute('data-theme', theme);
})();

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    OverseeTheme.init(window.overseeThemeMode || 'auto');
});

/**
 * Show toast notification
 */
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.textContent = message;
    
    container.appendChild(toast);
    
    // Remove after 4 seconds
    setTimeout(() => {
        toast.style.animation = 'slideIn 0.3s ease reverse';
        setTimeout(() => toast.remove(), 300);
    }, 4000);
}

/**
 * Open modal
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
    }
}

/**
 * Close modal
 */
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('open');
        document.body.style.overflow = '';
    }
}

/**
 * Escape HTML to prevent XSS
 */
function escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Format date for display
 */
function formatDate(dateStr) {
    if (!dateStr) return '';
    
    const date = new Date(dateStr);
    const now = new Date();
    const diff = now - date;
    
    // Less than 1 minute
    if (diff < 60000) {
        return 'Just now';
    }
    
    // Less than 1 hour
    if (diff < 3600000) {
        const mins = Math.floor(diff / 60000);
        return `${mins} min${mins > 1 ? 's' : ''} ago`;
    }
    
    // Less than 24 hours
    if (diff < 86400000) {
        const hours = Math.floor(diff / 3600000);
        return `${hours} hour${hours > 1 ? 's' : ''} ago`;
    }
    
    // Less than 7 days
    if (diff < 604800000) {
        const days = Math.floor(diff / 86400000);
        return `${days} day${days > 1 ? 's' : ''} ago`;
    }
    
    // Otherwise show date
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: date.getFullYear() !== now.getFullYear() ? 'numeric' : undefined
    });
}

/**
 * Format date and time
 */
function formatDateTime(dateStr) {
    if (!dateStr) return '';
    
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit'
    });
}

/**
 * Debounce function
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Toggle mobile sidebar
 */
function toggleMobileSidebar() {
    document.querySelector('.admin-sidebar')?.classList.toggle('open');
    document.querySelector('.sidebar-overlay')?.classList.toggle('open');
}

/**
 * Copy text to clipboard
 */
async function copyToClipboard(elementId) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    try {
        await navigator.clipboard.writeText(element.value);
        showToast('Copied to clipboard');
    } catch (err) {
        // Fallback for older browsers
        element.select();
        document.execCommand('copy');
        showToast('Copied to clipboard');
    }
}

/**
 * Confirm action with dialog
 */
function confirmAction(message) {
    return window.confirm(message);
}

/**
 * Format number with commas
 */
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

/**
 * Truncate text
 */
function truncate(text, length = 100) {
    if (!text || text.length <= length) return text;
    return text.substring(0, length) + '...';
}

/**
 * Parse URL parameters
 */
function getUrlParams() {
    const params = {};
    const searchParams = new URLSearchParams(window.location.search);
    for (const [key, value] of searchParams) {
        params[key] = value;
    }
    return params;
}

/**
 * Update URL without reload
 */
function updateUrl(params, replace = false) {
    const url = new URL(window.location.href);
    
    Object.entries(params).forEach(([key, value]) => {
        if (value === null || value === undefined || value === '') {
            url.searchParams.delete(key);
        } else {
            url.searchParams.set(key, value);
        }
    });
    
    if (replace) {
        history.replaceState({}, '', url.toString());
    } else {
        history.pushState({}, '', url.toString());
    }
}

/**
 * Load script dynamically
 */
function loadScript(src) {
    return new Promise((resolve, reject) => {
        const script = document.createElement('script');
        script.src = src;
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    });
}

/**
 * Check if element is in viewport
 */
function isInViewport(element) {
    const rect = element.getBoundingClientRect();
    return (
        rect.top >= 0 &&
        rect.left >= 0 &&
        rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) &&
        rect.right <= (window.innerWidth || document.documentElement.clientWidth)
    );
}

/**
 * Smooth scroll to element
 */
function scrollToElement(element, offset = 0) {
    const top = element.getBoundingClientRect().top + window.pageYOffset - offset;
    window.scrollTo({ top, behavior: 'smooth' });
}

/**
 * Handle keyboard shortcuts
 */
document.addEventListener('keydown', function(e) {
    // Escape to close modals
    if (e.key === 'Escape') {
        const openModal = document.querySelector('.modal.open');
        if (openModal) {
            openModal.classList.remove('open');
            document.body.style.overflow = '';
        }
    }
    
    // Ctrl/Cmd + K for search (if search exists)
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
        const searchInput = document.getElementById('ticketSearch');
        if (searchInput) {
            e.preventDefault();
            searchInput.focus();
        }
    }
});

/**
 * Close mobile sidebar on outside click
 */
document.addEventListener('click', function(e) {
    const sidebar = document.querySelector('.admin-sidebar');
    const hamburger = document.querySelector('.hamburger-btn');
    
    if (sidebar && sidebar.classList.contains('open')) {
        if (!sidebar.contains(e.target) && !hamburger?.contains(e.target)) {
            sidebar.classList.remove('open');
            document.querySelector('.sidebar-overlay')?.classList.remove('open');
        }
    }
});

/**
 * Handle browser back/forward
 */
window.addEventListener('popstate', function(e) {
    // Reload tickets if on tickets page
    if (window.location.pathname.includes('/tickets') && typeof loadTickets === 'function') {
        const params = getUrlParams();
        if (params.id) {
            loadTicketDetail(params.id);
        }
    }
});

/**
 * Initialize on DOM ready
 */
document.addEventListener('DOMContentLoaded', function() {
    // Add click ripple effect to buttons (optional)
    // document.querySelectorAll('.btn').forEach(btn => {
    //     btn.addEventListener('click', createRipple);
    // });
    
    // Set up any global event listeners
    console.log('Oversee Admin initialized');
});
