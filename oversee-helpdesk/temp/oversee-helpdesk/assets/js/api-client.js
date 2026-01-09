/**
 * Oversee API Client
 * Handles authentication and API requests
 */

class OverseeAPIClient {
    constructor() {
        this.baseUrl = '/wp-json/oversee/v1';
        this.token = localStorage.getItem('oversee_token');
        this.nonce = ''; // Will be set from page
    }
    
    /**
     * Set the WordPress nonce for cookie auth
     */
    setNonce(nonce) {
        this.nonce = nonce;
    }
    
    /**
     * Make an API request
     */
    async request(endpoint, options = {}) {
        const url = this.baseUrl + endpoint;
        
        const headers = {
            'Content-Type': 'application/json',
            ...options.headers
        };
        
        // Always send nonce for WordPress cookie auth
        if (this.nonce) {
            headers['X-WP-Nonce'] = this.nonce;
        }
        
        // Also send token if available (for iframe auth)
        if (this.token) {
            headers['Authorization'] = 'Bearer ' + this.token;
        }
        
        try {
            const response = await fetch(url, {
                ...options,
                headers,
                credentials: 'same-origin'
            });
            
            // Handle 401 - clear invalid token but don't redirect
            if (response.status === 401) {
                localStorage.removeItem('oversee_token');
                this.token = null;
                console.error('API authentication failed - token cleared');
                throw new Error('Authentication failed');
            }
            
            // Handle other errors
            if (!response.ok) {
                const error = await response.json().catch(() => ({ message: 'Request failed' }));
                throw new Error(error.message || 'Request failed');
            }
            
            // Return JSON if there is content
            const text = await response.text();
            return text ? JSON.parse(text) : {};
            
        } catch (error) {
            console.error('API Error:', error);
            throw error;
        }
    }
    
    /**
     * Handle authentication error
     */
    handleAuthError() {
        // Clear stored token
        localStorage.removeItem('oversee_token');
        localStorage.removeItem('oversee_user');
        
        // Check if we're in iframe (HighLevel)
        const isIframe = window.self !== window.top;
        
        if (isIframe) {
            // In iframe - show message to refresh
            this.showReauthMessage();
        } else {
            // Direct access - redirect to login
            const redirect = encodeURIComponent(window.location.pathname + window.location.search);
            window.location.href = '/support/admin/login?expired=1&redirect=' + redirect;
        }
        
        return null;
    }
    
    /**
     * Show re-authentication message for iframe users
     */
    showReauthMessage() {
        // Remove existing overlay if any
        const existing = document.querySelector('.reauth-overlay');
        if (existing) existing.remove();
        
        // Create overlay
        const overlay = document.createElement('div');
        overlay.className = 'reauth-overlay';
        overlay.innerHTML = `
            <div class="reauth-modal">
                <div class="reauth-icon">🔒</div>
                <h2>Session Expired</h2>
                <p>Your session has expired. Please refresh the page to continue.</p>
                <button onclick="window.location.reload()" class="btn btn-primary">
                    Refresh Page
                </button>
            </div>
        `;
        
        // Add styles if not already present
        if (!document.querySelector('#reauth-styles')) {
            const style = document.createElement('style');
            style.id = 'reauth-styles';
            style.textContent = `
                .reauth-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0,0,0,0.7);
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    z-index: 10000;
                }
                .reauth-modal {
                    background: white;
                    padding: 40px;
                    border-radius: 12px;
                    text-align: center;
                    max-width: 400px;
                    margin: 20px;
                }
                .reauth-icon {
                    font-size: 48px;
                    margin-bottom: 16px;
                }
                .reauth-modal h2 {
                    margin: 0 0 8px;
                    font-size: 20px;
                }
                .reauth-modal p {
                    color: #6b7280;
                    margin: 0 0 24px;
                }
            `;
            document.head.appendChild(style);
        }
        
        document.body.appendChild(overlay);
    }
    
    /**
     * GET request
     */
    get(endpoint) {
        return this.request(endpoint, { method: 'GET' });
    }
    
    /**
     * POST request
     */
    post(endpoint, data) {
        return this.request(endpoint, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    }
    
    /**
     * PUT request
     */
    put(endpoint, data) {
        return this.request(endpoint, {
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }
    
    /**
     * DELETE request
     */
    delete(endpoint) {
        return this.request(endpoint, { method: 'DELETE' });
    }
    
    /**
     * Set auth token
     */
    setToken(token) {
        this.token = token;
        localStorage.setItem('oversee_token', token);
    }
    
    /**
     * Clear auth token
     */
    clearToken() {
        this.token = null;
        localStorage.removeItem('oversee_token');
        localStorage.removeItem('oversee_user');
    }
    
    /**
     * Check if authenticated
     */
    isAuthenticated() {
        return !!this.token;
    }
    
    /**
     * Get stored user info
     */
    getUser() {
        const userStr = localStorage.getItem('oversee_user');
        return userStr ? JSON.parse(userStr) : null;
    }
}

// Create global instance
window.overseeAPI = new OverseeAPIClient();
