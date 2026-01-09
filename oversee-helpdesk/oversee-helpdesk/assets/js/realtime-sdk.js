/**
 * Oversee Real-time SDK
 * 
 * Client-side library for real-time updates via Server-Sent Events.
 * Provides event handling, presence tracking, and optimistic updates.
 * 
 * @version 1.0.0
 */

class OverseeRealtime {
    constructor(options = {}) {
        this.endpoint = options.endpoint || '/wp-json/oversee/v1/events/stream';
        this.pollEndpoint = options.pollEndpoint || '/wp-json/oversee/v1/events/poll';
        this.token = options.token || '';
        this.channels = options.channels || ['_global'];
        this.reconnectDelay = options.reconnectDelay || 1000;
        this.maxReconnectDelay = options.maxReconnectDelay || 30000;
        this.heartbeatInterval = options.heartbeatInterval || 30000;

        this.eventSource = null;
        this.listeners = new Map();
        this.presenceCallbacks = new Map();
        this.lastEventId = null;
        this.reconnectAttempts = 0;
        this.connected = false;
        this.heartbeatTimer = null;
        this.useSSE = typeof EventSource !== 'undefined';

        // Auto-connect if token provided
        if (this.token) {
            this.connect();
        }
    }

    // =========================================================================
    // Connection Management
    // =========================================================================

    /**
     * Connect to the SSE stream
     */
    connect() {
        if (this.connected) {
            return;
        }

        if (this.useSSE) {
            this.connectSSE();
        } else {
            this.startPolling();
        }
    }

    /**
     * Connect using Server-Sent Events
     */
    connectSSE() {
        const url = new URL(this.endpoint, window.location.origin);
        url.searchParams.set('channels', this.channels.join(','));
        
        if (this.lastEventId) {
            url.searchParams.set('last_event_id', this.lastEventId);
        }

        // Add auth header via query param (SSE doesn't support custom headers)
        if (this.token) {
            url.searchParams.set('_token', this.token);
        }

        this.eventSource = new EventSource(url.toString());

        this.eventSource.onopen = () => {
            this.connected = true;
            this.reconnectAttempts = 0;
            this.emit('connected', { channels: this.channels });
            this.startHeartbeat();
        };

        this.eventSource.onerror = (error) => {
            this.connected = false;
            this.eventSource.close();
            this.emit('error', error);
            this.scheduleReconnect();
        };

        // Listen for all event types
        this.eventSource.onmessage = (event) => {
            this.handleMessage('message', event);
        };

        // Specific event handlers
        const eventTypes = [
            'connected',
            'heartbeat',
            'reconnect',
            'ticket.created',
            'ticket.updated',
            'ticket.assigned',
            'reply.added',
            'agent.status_changed',
            'typing.started',
            'typing.stopped',
        ];

        eventTypes.forEach(type => {
            this.eventSource.addEventListener(type, (event) => {
                this.handleMessage(type, event);
            });
        });
    }

    /**
     * Start polling fallback
     */
    startPolling() {
        this.pollTimer = setInterval(() => {
            this.poll();
        }, 3000);

        this.poll();
        this.connected = true;
        this.emit('connected', { channels: this.channels, mode: 'polling' });
    }

    /**
     * Poll for new events
     */
    async poll() {
        try {
            const url = new URL(this.pollEndpoint, window.location.origin);
            url.searchParams.set('channels', this.channels.join(','));
            
            if (this.lastEventId) {
                url.searchParams.set('last_event_id', this.lastEventId);
            }

            const response = await fetch(url.toString(), {
                headers: {
                    'Authorization': `Bearer ${this.token}`,
                },
            });

            if (!response.ok) {
                throw new Error(`Poll failed: ${response.status}`);
            }

            const data = await response.json();

            if (data.events) {
                data.events.forEach(event => {
                    this.handleMessage(event.event, { data: JSON.stringify(event.data), lastEventId: event.id });
                });
            }
        } catch (error) {
            this.emit('error', error);
        }
    }

    /**
     * Disconnect from the stream
     */
    disconnect() {
        this.connected = false;

        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }

        if (this.pollTimer) {
            clearInterval(this.pollTimer);
            this.pollTimer = null;
        }

        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
            this.heartbeatTimer = null;
        }

        this.emit('disconnected');
    }

    /**
     * Schedule a reconnection attempt
     */
    scheduleReconnect() {
        const delay = Math.min(
            this.reconnectDelay * Math.pow(2, this.reconnectAttempts),
            this.maxReconnectDelay
        );

        this.reconnectAttempts++;

        setTimeout(() => {
            if (!this.connected) {
                this.emit('reconnecting', { attempt: this.reconnectAttempts, delay });
                this.connect();
            }
        }, delay);
    }

    // =========================================================================
    // Event Handling
    // =========================================================================

    /**
     * Handle incoming message
     */
    handleMessage(type, event) {
        let data;
        
        try {
            data = JSON.parse(event.data);
        } catch (e) {
            data = event.data;
        }

        // Update last event ID
        if (event.lastEventId) {
            this.lastEventId = event.lastEventId;
        }

        // Handle reconnect request
        if (type === 'reconnect') {
            this.disconnect();
            this.connect();
            return;
        }

        // Emit to listeners
        this.emit(type, data);

        // Emit to channel-specific listeners
        if (data.ticket_id) {
            this.emit(`ticket.${data.ticket_id}`, { type, data });
        }
    }

    /**
     * Subscribe to an event type
     * 
     * @param {string} event - Event name or pattern
     * @param {Function} callback - Handler function
     * @returns {Function} Unsubscribe function
     */
    on(event, callback) {
        if (!this.listeners.has(event)) {
            this.listeners.set(event, new Set());
        }
        
        this.listeners.get(event).add(callback);

        // Return unsubscribe function
        return () => {
            this.listeners.get(event).delete(callback);
        };
    }

    /**
     * Subscribe to a channel
     * 
     * @param {string} channel - Channel name
     * @param {Function} callback - Handler function
     */
    subscribe(channel, callback) {
        // Add channel to subscriptions
        if (!this.channels.includes(channel)) {
            this.channels.push(channel);
            
            // Reconnect with new channel
            if (this.connected) {
                this.disconnect();
                this.connect();
            }
        }

        return this.on(channel, callback);
    }

    /**
     * Unsubscribe from a channel
     */
    unsubscribe(channel) {
        this.channels = this.channels.filter(c => c !== channel);
        this.listeners.delete(channel);
    }

    /**
     * Emit an event to listeners
     */
    emit(event, data) {
        const listeners = this.listeners.get(event);
        
        if (listeners) {
            listeners.forEach(callback => {
                try {
                    callback(data);
                } catch (error) {
                    console.error(`Error in listener for ${event}:`, error);
                }
            });
        }

        // Also emit to wildcard listeners
        const wildcardListeners = this.listeners.get('*');
        if (wildcardListeners) {
            wildcardListeners.forEach(callback => {
                try {
                    callback({ event, data });
                } catch (error) {
                    console.error('Error in wildcard listener:', error);
                }
            });
        }
    }

    // =========================================================================
    // Presence
    // =========================================================================

    /**
     * Track presence on a channel
     * 
     * @param {string} channel - Presence channel name
     * @param {Object} callbacks - { onJoin, onLeave, onUpdate }
     */
    presence(channel, callbacks = {}) {
        this.presenceCallbacks.set(channel, callbacks);

        this.on('agent.status_changed', (data) => {
            if (data.status === 'online' && callbacks.onJoin) {
                callbacks.onJoin(data);
            } else if (data.status === 'offline' && callbacks.onLeave) {
                callbacks.onLeave(data);
            } else if (callbacks.onUpdate) {
                callbacks.onUpdate(data);
            }
        });

        // Fetch initial presence
        this.fetchPresence(channel);
    }

    /**
     * Fetch current presence state
     */
    async fetchPresence(channel) {
        try {
            const response = await fetch('/wp-json/oversee/v1/presence/agents', {
                headers: {
                    'Authorization': `Bearer ${this.token}`,
                },
            });

            if (response.ok) {
                const data = await response.json();
                const callbacks = this.presenceCallbacks.get(channel);
                
                if (callbacks && callbacks.onSync) {
                    callbacks.onSync(data.agents);
                }
            }
        } catch (error) {
            console.error('Failed to fetch presence:', error);
        }
    }

    // =========================================================================
    // Typing Indicators
    // =========================================================================

    /**
     * Send typing indicator
     * 
     * @param {number} ticketId - Ticket ID
     */
    async startTyping(ticketId) {
        try {
            await fetch('/wp-json/oversee/v1/events/typing/start', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${this.token}`,
                },
                body: JSON.stringify({ ticket_id: ticketId }),
            });
        } catch (error) {
            console.error('Failed to send typing indicator:', error);
        }
    }

    /**
     * Stop typing indicator
     */
    async stopTyping(ticketId) {
        try {
            await fetch('/wp-json/oversee/v1/events/typing/stop', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${this.token}`,
                },
                body: JSON.stringify({ ticket_id: ticketId }),
            });
        } catch (error) {
            console.error('Failed to stop typing indicator:', error);
        }
    }

    /**
     * Create a debounced typing handler
     * 
     * @param {number} ticketId - Ticket ID
     * @param {number} delay - Debounce delay in ms
     */
    typingHandler(ticketId, delay = 1000) {
        let timeout = null;
        let isTyping = false;

        return () => {
            if (!isTyping) {
                isTyping = true;
                this.startTyping(ticketId);
            }

            clearTimeout(timeout);
            timeout = setTimeout(() => {
                isTyping = false;
                this.stopTyping(ticketId);
            }, delay);
        };
    }

    // =========================================================================
    // Optimistic Updates
    // =========================================================================

    /**
     * Perform an optimistic update
     * 
     * @param {Object} options - { action, optimisticData, onSuccess, onError, rollback }
     */
    async optimistic(options) {
        const { action, optimisticData, onSuccess, onError, rollback } = options;

        // Apply optimistic update immediately
        if (optimisticData) {
            this.emit('optimistic', optimisticData);
        }

        try {
            const result = await action();
            
            if (onSuccess) {
                onSuccess(result);
            }

            return result;
        } catch (error) {
            // Rollback optimistic update
            if (rollback) {
                rollback(error);
            }

            this.emit('optimistic.failed', { error, data: optimisticData });

            if (onError) {
                onError(error);
            }

            throw error;
        }
    }

    // =========================================================================
    // Heartbeat
    // =========================================================================

    /**
     * Start heartbeat timer
     */
    startHeartbeat() {
        if (this.heartbeatTimer) {
            clearInterval(this.heartbeatTimer);
        }

        this.heartbeatTimer = setInterval(() => {
            this.sendHeartbeat();
        }, this.heartbeatInterval);
    }

    /**
     * Send heartbeat to server
     */
    async sendHeartbeat() {
        try {
            await fetch('/wp-json/oversee/v1/events/heartbeat', {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${this.token}`,
                },
            });
        } catch (error) {
            // Heartbeat failed, might be disconnected
            this.emit('heartbeat.failed', error);
        }
    }

    // =========================================================================
    // Push Notifications
    // =========================================================================

    /**
     * Request push notification permission and subscribe
     */
    async subscribeToPush() {
        if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
            throw new Error('Push notifications not supported');
        }

        // Get VAPID public key from server
        const keyResponse = await fetch('/wp-json/oversee/v1/push/public-key', {
            headers: {
                'Authorization': `Bearer ${this.token}`,
            },
        });

        if (!keyResponse.ok) {
            throw new Error('Failed to get VAPID key');
        }

        const { public_key: vapidPublicKey } = await keyResponse.json();

        // Request permission
        const permission = await Notification.requestPermission();
        
        if (permission !== 'granted') {
            throw new Error('Notification permission denied');
        }

        // Get service worker registration
        const registration = await navigator.serviceWorker.ready;

        // Subscribe to push
        const subscription = await registration.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: this.urlBase64ToUint8Array(vapidPublicKey),
        });

        // Send subscription to server
        const response = await fetch('/wp-json/oversee/v1/push/subscribe', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${this.token}`,
            },
            body: JSON.stringify(subscription.toJSON()),
        });

        if (!response.ok) {
            throw new Error('Failed to save subscription');
        }

        return subscription;
    }

    /**
     * Unsubscribe from push notifications
     */
    async unsubscribeFromPush() {
        const registration = await navigator.serviceWorker.ready;
        const subscription = await registration.pushManager.getSubscription();

        if (subscription) {
            await subscription.unsubscribe();

            // Notify server
            await fetch('/wp-json/oversee/v1/push/unsubscribe', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${this.token}`,
                },
                body: JSON.stringify({ endpoint: subscription.endpoint }),
            });
        }
    }

    /**
     * Convert VAPID key to Uint8Array
     */
    urlBase64ToUint8Array(base64String) {
        const padding = '='.repeat((4 - base64String.length % 4) % 4);
        const base64 = (base64String + padding)
            .replace(/-/g, '+')
            .replace(/_/g, '/');

        const rawData = window.atob(base64);
        const outputArray = new Uint8Array(rawData.length);

        for (let i = 0; i < rawData.length; ++i) {
            outputArray[i] = rawData.charCodeAt(i);
        }

        return outputArray;
    }
}

// Export for module systems
if (typeof module !== 'undefined' && module.exports) {
    module.exports = OverseeRealtime;
}

// Also attach to window for non-module usage
if (typeof window !== 'undefined') {
    window.OverseeRealtime = OverseeRealtime;
}
