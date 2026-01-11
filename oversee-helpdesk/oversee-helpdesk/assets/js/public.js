/**
 * Oversee Support Public JavaScript
 * Version: 2.5.0 - Dark Mode Support
 */

/**
 * Theme Manager - Handles light/dark mode switching
 */
const OverseeTheme = {
    // Theme mode from settings: 'light_only', 'dark_only', 'auto', 'user_choice'
    mode: 'auto',

    init(settingsMode) {
        this.mode = settingsMode || 'auto';

        // Determine which theme to apply
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

document.addEventListener('DOMContentLoaded', function() {
    // Initialize theme manager
    OverseeTheme.init(window.overseeThemeMode || 'auto');

    // Initialize search autofocus
    initSearchAutofocus();

    // Initialize mobile menu if needed
    initMobileNav();
});

/**
 * Autofocus search on home page
 */
function initSearchAutofocus() {
    const searchInput = document.querySelector('.kb-header:not(.kb-header-compact) .search-input-wrapper input');
    if (searchInput) {
        // Don't autofocus on mobile
        if (window.innerWidth > 768) {
            searchInput.focus();
        }
    }
}

/**
 * Initialize mobile navigation
 */
function initMobileNav() {
    // Could add mobile sidebar toggle here if needed
}

/**
 * Smooth scroll to element
 */
function scrollToElement(selector) {
    const element = document.querySelector(selector);
    if (element) {
        element.scrollIntoView({ behavior: 'smooth' });
    }
}

/**
 * Format date for display
 */
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
    });
}

/**
 * Escape HTML
 */
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Copy text to clipboard
 */
async function copyToClipboard(text) {
    try {
        await navigator.clipboard.writeText(text);
        return true;
    } catch (err) {
        // Fallback for older browsers
        const textarea = document.createElement('textarea');
        textarea.value = text;
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();
        const success = document.execCommand('copy');
        document.body.removeChild(textarea);
        return success;
    }
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
 * Live search functionality (optional enhancement)
 */
function initLiveSearch() {
    const searchInput = document.querySelector('.search-input-wrapper input');
    const searchResults = document.createElement('div');
    searchResults.className = 'live-search-results';
    
    if (!searchInput) return;
    
    searchInput.parentElement.appendChild(searchResults);
    
    const performSearch = debounce(async function(query) {
        if (query.length < 2) {
            searchResults.style.display = 'none';
            return;
        }
        
        try {
            const response = await fetch(`/wp-json/oversee/v1/kb/search?q=${encodeURIComponent(query)}`);
            const data = await response.json();
            
            if (data.results && data.results.length > 0) {
                searchResults.innerHTML = data.results.slice(0, 5).map(result => `
                    <a href="/support/kb/${result.category_slug}/${result.slug}" class="search-result">
                        <i class="fa-solid fa-file-lines"></i>
                        <span>${escapeHtml(result.title)}</span>
                    </a>
                `).join('');
                searchResults.style.display = 'block';
            } else {
                searchResults.style.display = 'none';
            }
        } catch (error) {
            console.error('Search error:', error);
        }
    }, 300);
    
    searchInput.addEventListener('input', function() {
        performSearch(this.value);
    });
    
    // Hide results when clicking outside
    document.addEventListener('click', function(e) {
        if (!searchInput.parentElement.contains(e.target)) {
            searchResults.style.display = 'none';
        }
    });
}
