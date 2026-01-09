/**
 * Oversee Support Public JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
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
