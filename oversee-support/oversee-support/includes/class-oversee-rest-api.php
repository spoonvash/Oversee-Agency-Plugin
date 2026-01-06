<?php
/**
 * REST API
 * 
 * Registers all REST API endpoints for the support system.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Oversee_REST_API {
    
    /**
     * Initialize REST API
     */
    public static function init() {
        self::register_auth_routes();
        self::register_dashboard_routes();
        self::register_ticket_routes();
        self::register_agent_routes();
        self::register_settings_routes();
        self::register_canned_routes();
        self::register_push_routes();
        self::register_webhook_routes();
    }
    
    /**
     * Auth routes
     */
    private static function register_auth_routes() {
        // Get current user
        register_rest_route('oversee/v1', '/auth/me', [
            'methods' => 'GET',
            'callback' => function($request) {
                $user = wp_get_current_user();
                $agent = Oversee_Auth::get_current_agent();
                
                return new WP_REST_Response([
                    'id' => $user->ID,
                    'email' => $user->user_email,
                    'name' => $user->display_name,
                    'first_name' => $user->first_name,
                    'last_name' => $user->last_name,
                    'role' => $agent ? $agent->role : 'agent',
                    'is_admin' => current_user_can('oversee_manage_settings'),
                    'agent' => $agent
                ]);
            },
            'permission_callback' => [__CLASS__, 'agent_permission']
        ]);
        
        // Logout (invalidate token)
        register_rest_route('oversee/v1', '/auth/logout', [
            'methods' => 'POST',
            'callback' => function($request) {
                $auth_header = $request->get_header('Authorization');
                if ($auth_header && preg_match('/^Bearer\s+(\S+)$/i', $auth_header, $matches)) {
                    Oversee_Iframe_Auth::invalidate_token($matches[1]);
                }
                
                return new WP_REST_Response(['success' => true, 'message' => 'Logged out']);
            },
            'permission_callback' => [__CLASS__, 'agent_permission']
        ]);
    }
    
    /**
     * Dashboard routes
     */
    private static function register_dashboard_routes() {
        // Get stats
        register_rest_route('oversee/v1', '/dashboard/stats', [
            'methods' => 'GET',
            'callback' => function($request) {
                global $wpdb;
                
                $stats = [
                    'high_priority' => (int) $wpdb->get_var(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}oversee_tickets 
                         WHERE priority = 'high' AND status != 'resolved'"
                    ),
                    'new' => (int) $wpdb->get_var(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}oversee_tickets WHERE status = 'new'"
                    ),
                    'open' => (int) $wpdb->get_var(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}oversee_tickets WHERE status = 'open'"
                    ),
                    'pending' => (int) $wpdb->get_var(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}oversee_tickets WHERE status = 'pending'"
                    ),
                    'resolved_today' => (int) $wpdb->get_var(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}oversee_tickets 
                         WHERE status = 'resolved' AND DATE(resolved_at) = CURDATE()"
                    ),
                    'resolved_week' => (int) $wpdb->get_var(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}oversee_tickets 
                         WHERE status = 'resolved' AND resolved_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)"
                    ),
                    'total' => (int) $wpdb->get_var(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}oversee_tickets"
                    )
                ];
                
                return new WP_REST_Response($stats);
            },
            'permission_callback' => [__CLASS__, 'agent_permission']
        ]);
        
        // Get recent tickets
        register_rest_route('oversee/v1', '/dashboard/recent-tickets', [
            'methods' => 'GET',
            'callback' => function($request) {
                global $wpdb;
                
                $limit = min(20, max(1, (int) ($request->get_param('limit') ?? 10)));
                
                $tickets = $wpdb->get_results($wpdb->prepare(
                    "SELECT id, ticket_number, subject, status, priority, customer_name, customer_email, created_at, updated_at
                     FROM {$wpdb->prefix}oversee_tickets 
                     ORDER BY updated_at DESC
                     LIMIT %d",
                    $limit
                ));
                
                return new WP_REST_Response($tickets);
            },
            'permission_callback' => [__CLASS__, 'agent_permission']
        ]);
        
        // Get team status
        register_rest_route('oversee/v1', '/dashboard/team-status', [
            'methods' => 'GET',
            'callback' => function($request) {
                global $wpdb;
                
                $agents = $wpdb->get_results(
                    "SELECT a.*, u.display_name,
                            (SELECT COUNT(*) FROM {$wpdb->prefix}oversee_tickets 
                             WHERE assigned_to = a.wp_user_id AND status != 'resolved') as open_tickets
                     FROM {$wpdb->prefix}oversee_agents a
                     LEFT JOIN {$wpdb->users} u ON a.wp_user_id = u.ID
                     ORDER BY a.is_online DESC, u.display_name ASC"
                );
                
                return new WP_REST_Response($agents);
            },
            'permission_callback' => [__CLASS__, 'agent_permission']
        ]);
    }
    
    /**
     * Ticket routes
     */
    private static function register_ticket_routes() {
        // List tickets
        register_rest_route('oversee/v1', '/tickets', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_tickets'],
            'permission_callback' => [__CLASS__, 'agent_permission']
        ]);
        
        // Create ticket
        register_rest_route('oversee/v1', '/tickets', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'create_ticket'],
            'permission_callback' => [__CLASS__, 'agent_permission']
        ]);
        
        // Get single ticket
        register_rest_route('oversee/v1', '/tickets/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_ticket'],
            'permission_callback' => [__CLASS__, 'agent_permission']
        ]);
        
        // Update ticket
        register_rest_route('oversee/v1', '/tickets/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [__CLASS__, 'update_ticket'],
            'permission_callback' => [__CLASS__, 'agent_permission']
        ]);
        
        // Delete ticket
        register_rest_route('oversee/v1', '/tickets/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [__CLASS__, 'delete_ticket'],
            'permission_callback' => [__CLASS__, 'admin_permission']
        ]);
        
        // Bulk actions
        register_rest_route('oversee/v1', '/tickets/bulk', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'bulk_ticket_action'],
            'permission_callback' => [__CLASS__, 'agent_permission']
        ]);
        
        // Add reply
        register_rest_route('oversee/v1', '/tickets/(?P<id>\d+)/reply', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'add_ticket_reply'],
            'permission_callback' => [__CLASS__, 'respond_permission']
        ]);
        
        // Add internal note
        register_rest_route('oversee/v1', '/tickets/(?P<id>\d+)/note', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'add_ticket_note'],
            'permission_callback' => [__CLASS__, 'respond_permission']
        ]);
        
        // Public ticket submission (no auth required)
        register_rest_route('oversee/v1', '/tickets/public', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'create_public_ticket'],
            'permission_callback' => '__return_true'
        ]);
        
        // Public ticket lookup by email (no auth required)
        register_rest_route('oversee/v1', '/tickets/public', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_public_tickets'],
            'permission_callback' => '__return_true'
        ]);
        
        // Public reply to ticket (no auth required, uses access token)
        register_rest_route('oversee/v1', '/tickets/public/(?P<token>[a-f0-9]+)/reply', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'add_public_reply'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    /**
     * Get tickets for a customer by email (public)
     */
    public static function get_public_tickets($request) {
        global $wpdb;
        
        $email = sanitize_email($request->get_param('email'));
        
        if (empty($email) || !is_email($email)) {
            return new WP_Error('invalid_email', 'Please provide a valid email address.', ['status' => 400]);
        }
        
        $tickets = $wpdb->get_results($wpdb->prepare(
            "SELECT id, ticket_number, subject, status, priority, access_token, created_at, updated_at
             FROM {$wpdb->prefix}oversee_tickets
             WHERE customer_email = %s
             ORDER BY created_at DESC",
            $email
        ), ARRAY_A);
        
        return new WP_REST_Response([
            'tickets' => $tickets ?: []
        ]);
    }
    
    /**
     * Add a reply to a ticket via access token (public)
     */
    public static function add_public_reply($request) {
        global $wpdb;
        
        $token = sanitize_text_field($request->get_param('token'));
        $message = wp_kses_post($request->get_param('message'));
        
        if (empty($message)) {
            return new WP_Error('missing_message', 'Please provide a message.', ['status' => 400]);
        }
        
        // Find ticket by access token
        $ticket = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}oversee_tickets WHERE access_token = %s",
            $token
        ));
        
        if (!$ticket) {
            return new WP_Error('not_found', 'Ticket not found.', ['status' => 404]);
        }
        
        if ($ticket->status === 'resolved') {
            return new WP_Error('ticket_resolved', 'This ticket has been resolved.', ['status' => 400]);
        }
        
        // Add reply
        $result = $wpdb->insert(
            $wpdb->prefix . 'oversee_ticket_replies',
            [
                'ticket_id' => $ticket->id,
                'message' => $message,
                'is_internal_note' => 0,
                'author_type' => 'customer',
                'author_name' => $ticket->customer_name,
                'author_email' => $ticket->customer_email,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%d', '%s', '%s', '%s', '%s']
        );
        
        if ($result === false) {
            return new WP_Error('reply_failed', 'Failed to add reply.', ['status' => 500]);
        }
        
        // Update ticket status and timestamp
        $wpdb->update(
            $wpdb->prefix . 'oversee_tickets',
            [
                'status' => $ticket->status === 'new' ? 'open' : $ticket->status,
                'updated_at' => current_time('mysql')
            ],
            ['id' => $ticket->id],
            ['%s', '%s'],
            ['%d']
        );
        
        // Fire webhook
        do_action('oversee_ticket_reply', $ticket->id, [
            'ticket_number' => $ticket->ticket_number,
            'author_type' => 'customer',
            'message' => $message
        ]);
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Reply added successfully'
        ]);
    }
    
    /**
     * Create a public ticket (no auth required)
     */
    public static function create_public_ticket($request) {
        global $wpdb;
        
        $customer_email = sanitize_email($request->get_param('customer_email'));
        $customer_name = sanitize_text_field($request->get_param('customer_name') ?? '');
        $customer_phone = sanitize_text_field($request->get_param('customer_phone') ?? '');
        $subject = sanitize_text_field($request->get_param('subject'));
        $description = wp_kses_post($request->get_param('description'));
        $priority = sanitize_text_field($request->get_param('priority') ?? 'low');
        
        // Validate required fields
        if (empty($customer_email) || !is_email($customer_email)) {
            return new WP_Error('invalid_email', 'Please provide a valid email address.', ['status' => 400]);
        }
        
        if (empty($subject)) {
            return new WP_Error('missing_subject', 'Please provide a subject.', ['status' => 400]);
        }
        
        if (empty($description)) {
            return new WP_Error('missing_description', 'Please provide a description.', ['status' => 400]);
        }
        
        // Validate priority
        if (!in_array($priority, ['low', 'high'])) {
            $priority = 'low';
        }
        
        // Generate ticket number
        $ticket_number = 'TKT-' . strtoupper(substr(uniqid(), -8));
        
        // Generate access token for customer
        $access_token = bin2hex(random_bytes(32));
        
        // Get agent for auto-assignment
        $assigned_to = null;
        if (get_option('oversee_auto_assign_enabled', true)) {
            $assigned_to = self::get_next_available_agent();
        }
        
        // Create ticket
        $result = $wpdb->insert(
            $wpdb->prefix . 'oversee_tickets',
            [
                'ticket_number' => $ticket_number,
                'subject' => $subject,
                'description' => $description,
                'status' => 'new',
                'priority' => $priority,
                'customer_name' => $customer_name,
                'customer_email' => $customer_email,
                'customer_phone' => $customer_phone,
                'assigned_to' => $assigned_to,
                'access_token' => $access_token,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s']
        );
        
        if ($result === false) {
            Oversee_Logger::error('Failed to create public ticket', ['error' => $wpdb->last_error]);
            return new WP_Error('create_failed', 'Failed to create ticket. Please try again.', ['status' => 500]);
        }
        
        $ticket_id = $wpdb->insert_id;
        
        // Log the creation
        Oversee_Logger::info('Public ticket created', [
            'ticket_id' => $ticket_id,
            'ticket_number' => $ticket_number,
            'customer_email' => $customer_email
        ]);
        
        // Fire webhook if configured
        do_action('oversee_ticket_created', $ticket_id, [
            'ticket_number' => $ticket_number,
            'subject' => $subject,
            'customer_email' => $customer_email,
            'priority' => $priority,
            'assigned_to' => $assigned_to
        ]);
        
        return new WP_REST_Response([
            'success' => true,
            'ticket_id' => $ticket_id,
            'ticket_number' => $ticket_number,
            'access_token' => $access_token,
            'message' => 'Ticket created successfully'
        ], 201);
    }
    
    /**
     * Get next available agent for auto-assignment
     */
    private static function get_next_available_agent() {
        global $wpdb;
        
        $skip_offline = get_option('oversee_skip_offline_agents', true);
        $balance_workload = get_option('oversee_balance_by_workload', false);
        
        $where = [];
        if ($skip_offline) {
            $where[] = 'a.is_online = 1';
        }
        
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        if ($balance_workload) {
            // Assign to agent with fewest open tickets
            $agent = $wpdb->get_row(
                "SELECT a.wp_user_id, 
                        COUNT(t.id) as open_tickets
                 FROM {$wpdb->prefix}oversee_agents a
                 LEFT JOIN {$wpdb->prefix}oversee_tickets t 
                    ON a.wp_user_id = t.assigned_to AND t.status IN ('new', 'open', 'pending')
                 {$where_clause}
                 GROUP BY a.wp_user_id
                 ORDER BY open_tickets ASC
                 LIMIT 1"
            );
        } else {
            // Round-robin: get agent who hasn't been assigned recently
            // MySQL doesn't support NULLS FIRST, so use ISNULL() or IS NULL ordering
            $agent = $wpdb->get_row(
                "SELECT a.wp_user_id
                 FROM {$wpdb->prefix}oversee_agents a
                 LEFT JOIN (
                    SELECT assigned_to, MAX(created_at) as last_assigned
                    FROM {$wpdb->prefix}oversee_tickets
                    WHERE assigned_to IS NOT NULL
                    GROUP BY assigned_to
                 ) t ON a.wp_user_id = t.assigned_to
                 {$where_clause}
                 ORDER BY t.last_assigned IS NULL DESC, t.last_assigned ASC
                 LIMIT 1"
            );
        }
        
        return $agent ? $agent->wp_user_id : null;
    }
    
    /**
     * Get tickets
     */
    public static function get_tickets($request) {
        global $wpdb;
        
        $status = $request->get_param('status');
        $priority = $request->get_param('priority');
        $assigned_to = $request->get_param('assigned_to');
        $search = $request->get_param('search');
        $page = max(1, (int) ($request->get_param('page') ?? 1));
        $per_page = min(100, max(1, (int) ($request->get_param('per_page') ?? 20)));
        
        $where = ['1=1'];
        $params = [];
        
        if ($status) {
            $where[] = 't.status = %s';
            $params[] = $status;
        }
        
        if ($priority) {
            $where[] = 't.priority = %s';
            $params[] = $priority;
        }
        
        if ($assigned_to !== null) {
            if ($assigned_to === 'unassigned' || $assigned_to === '0') {
                $where[] = 't.assigned_to IS NULL';
            } else {
                $where[] = 't.assigned_to = %d';
                $params[] = (int) $assigned_to;
            }
        }
        
        if (!empty($search)) {
            $where[] = '(t.subject LIKE %s OR t.customer_name LIKE %s OR t.customer_email LIKE %s OR t.ticket_number LIKE %s)';
            $like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        
        $where_clause = implode(' AND ', $where);
        $offset = ($page - 1) * $per_page;
        
        $query = "SELECT t.*, 
                         a.first_name as agent_first_name, 
                         a.last_name as agent_last_name,
                         (SELECT COUNT(*) FROM {$wpdb->prefix}oversee_ticket_replies WHERE ticket_id = t.id) as reply_count
                  FROM {$wpdb->prefix}oversee_tickets t
                  LEFT JOIN {$wpdb->prefix}oversee_agents a ON t.assigned_to = a.wp_user_id
                  WHERE {$where_clause}
                  ORDER BY t.updated_at DESC
                  LIMIT %d OFFSET %d";
        
        $params[] = $per_page;
        $params[] = $offset;
        
        $tickets = $wpdb->get_results(
            !empty($params) ? $wpdb->prepare($query, $params) : $query
        );
        
        // Get total
        $count_query = "SELECT COUNT(*) FROM {$wpdb->prefix}oversee_tickets t WHERE {$where_clause}";
        $count_params = array_slice($params, 0, -2);
        
        $total = (int) $wpdb->get_var(
            !empty($count_params) ? $wpdb->prepare($count_query, $count_params) : $count_query
        );
        
        return new WP_REST_Response([
            'tickets' => $tickets,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page)
        ]);
    }
    
    /**
     * Get single ticket with replies
     */
    public static function get_ticket($request) {
        global $wpdb;
        
        $id = (int) $request->get_param('id');
        
        $ticket = $wpdb->get_row($wpdb->prepare(
            "SELECT t.*, 
                    a.first_name as agent_first_name, 
                    a.last_name as agent_last_name,
                    a.email as agent_email
             FROM {$wpdb->prefix}oversee_tickets t
             LEFT JOIN {$wpdb->prefix}oversee_agents a ON t.assigned_to = a.wp_user_id
             WHERE t.id = %d",
            $id
        ));
        
        if (!$ticket) {
            return new WP_Error('not_found', 'Ticket not found', ['status' => 404]);
        }
        
        // Get replies
        $replies = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}oversee_ticket_replies 
             WHERE ticket_id = %d 
             ORDER BY created_at ASC",
            $id
        ));
        
        $ticket->replies = $replies;
        
        return new WP_REST_Response($ticket);
    }
    
    /**
     * Create ticket
     */
    public static function create_ticket($request) {
        global $wpdb;
        
        $subject = sanitize_text_field($request->get_param('subject'));
        $description = wp_kses_post($request->get_param('description'));
        $customer_name = sanitize_text_field($request->get_param('customer_name'));
        $customer_email = sanitize_email($request->get_param('customer_email'));
        $customer_phone = sanitize_text_field($request->get_param('customer_phone'));
        $priority = in_array($request->get_param('priority'), ['low', 'high']) ? $request->get_param('priority') : 'low';
        
        if (empty($subject) || empty($customer_email)) {
            return new WP_Error('missing_fields', 'Subject and email are required', ['status' => 400]);
        }
        
        // Generate ticket number
        $ticket_number = 'TKT-' . strtoupper(substr(md5(uniqid()), 0, 8));
        
        // Generate access token
        $access_token = bin2hex(random_bytes(32));
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'oversee_tickets',
            [
                'ticket_number' => $ticket_number,
                'subject' => $subject,
                'description' => $description,
                'customer_name' => $customer_name,
                'customer_email' => $customer_email,
                'customer_phone' => $customer_phone,
                'priority' => $priority,
                'status' => 'new',
                'access_token' => $access_token
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create ticket', ['status' => 500]);
        }
        
        $ticket_id = $wpdb->insert_id;
        
        // Auto-assign if enabled
        self::maybe_auto_assign($ticket_id);
        
        // Trigger webhook
        Oversee_Webhooks::trigger('ticket_created', [
            'ticket_id' => $ticket_id,
            'ticket_number' => $ticket_number
        ]);
        
        return new WP_REST_Response([
            'id' => $ticket_id,
            'ticket_number' => $ticket_number,
            'access_token' => $access_token,
            'message' => 'Ticket created successfully'
        ], 201);
    }
    
    /**
     * Update ticket
     */
    public static function update_ticket($request) {
        global $wpdb;
        
        $id = (int) $request->get_param('id');
        
        // Check exists
        $ticket = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}oversee_tickets WHERE id = %d",
            $id
        ));
        
        if (!$ticket) {
            return new WP_Error('not_found', 'Ticket not found', ['status' => 404]);
        }
        
        $updates = [];
        $formats = [];
        
        if ($request->has_param('status')) {
            $status = $request->get_param('status');
            if (in_array($status, ['new', 'open', 'pending', 'resolved'])) {
                $updates['status'] = $status;
                $formats[] = '%s';
                
                if ($status === 'resolved' && $ticket->status !== 'resolved') {
                    $updates['resolved_at'] = current_time('mysql');
                    $formats[] = '%s';
                }
            }
        }
        
        if ($request->has_param('priority')) {
            $priority = $request->get_param('priority');
            if (in_array($priority, ['low', 'high'])) {
                $updates['priority'] = $priority;
                $formats[] = '%s';
            }
        }
        
        if ($request->has_param('assigned_to')) {
            $assigned_to = $request->get_param('assigned_to');
            $updates['assigned_to'] = $assigned_to === null || $assigned_to === '' ? null : (int) $assigned_to;
            $formats[] = $assigned_to === null || $assigned_to === '' ? null : '%d';
        }
        
        if (empty($updates)) {
            return new WP_Error('no_updates', 'No fields to update', ['status' => 400]);
        }
        
        $result = $wpdb->update(
            $wpdb->prefix . 'oversee_tickets',
            $updates,
            ['id' => $id],
            $formats,
            ['%d']
        );
        
        // Trigger webhook
        Oversee_Webhooks::trigger('ticket_updated', [
            'ticket_id' => $id,
            'updates' => $updates
        ]);
        
        return new WP_REST_Response([
            'id' => $id,
            'message' => 'Ticket updated successfully'
        ]);
    }
    
    /**
     * Delete ticket
     */
    public static function delete_ticket($request) {
        global $wpdb;
        
        $id = (int) $request->get_param('id');
        
        // Delete replies first
        $wpdb->delete(
            $wpdb->prefix . 'oversee_ticket_replies',
            ['ticket_id' => $id],
            ['%d']
        );
        
        // Delete ticket
        $result = $wpdb->delete(
            $wpdb->prefix . 'oversee_tickets',
            ['id' => $id],
            ['%d']
        );
        
        if ($result === 0) {
            return new WP_Error('not_found', 'Ticket not found', ['status' => 404]);
        }
        
        return new WP_REST_Response(['message' => 'Ticket deleted']);
    }
    
    /**
     * Bulk ticket actions
     */
    public static function bulk_ticket_action($request) {
        global $wpdb;
        
        $action = $request->get_param('action');
        $ticket_ids = $request->get_param('ticket_ids');
        $value = $request->get_param('value');
        
        if (empty($ticket_ids) || !is_array($ticket_ids)) {
            return new WP_Error('invalid_ids', 'No tickets selected', ['status' => 400]);
        }
        
        // Sanitize IDs
        $ticket_ids = array_map('intval', $ticket_ids);
        $ids_string = implode(',', $ticket_ids);
        
        $updated = 0;
        
        switch ($action) {
            case 'resolve':
                $updated = $wpdb->query(
                    "UPDATE {$wpdb->prefix}oversee_tickets 
                     SET status = 'resolved', resolved_at = NOW() 
                     WHERE id IN ({$ids_string})"
                );
                break;
                
            case 'open':
                $updated = $wpdb->query(
                    "UPDATE {$wpdb->prefix}oversee_tickets 
                     SET status = 'open' 
                     WHERE id IN ({$ids_string})"
                );
                break;
                
            case 'pending':
                $updated = $wpdb->query(
                    "UPDATE {$wpdb->prefix}oversee_tickets 
                     SET status = 'pending' 
                     WHERE id IN ({$ids_string})"
                );
                break;
                
            case 'assign':
                $agent_id = $value !== null ? (int) $value : null;
                if ($agent_id) {
                    $updated = $wpdb->query($wpdb->prepare(
                        "UPDATE {$wpdb->prefix}oversee_tickets 
                         SET assigned_to = %d 
                         WHERE id IN ({$ids_string})",
                        $agent_id
                    ));
                } else {
                    $updated = $wpdb->query(
                        "UPDATE {$wpdb->prefix}oversee_tickets 
                         SET assigned_to = NULL 
                         WHERE id IN ({$ids_string})"
                    );
                }
                break;
                
            case 'priority_high':
                $updated = $wpdb->query(
                    "UPDATE {$wpdb->prefix}oversee_tickets 
                     SET priority = 'high' 
                     WHERE id IN ({$ids_string})"
                );
                break;
                
            case 'priority_low':
                $updated = $wpdb->query(
                    "UPDATE {$wpdb->prefix}oversee_tickets 
                     SET priority = 'low' 
                     WHERE id IN ({$ids_string})"
                );
                break;
                
            case 'delete':
                if (!current_user_can('oversee_manage_settings')) {
                    return new WP_Error('forbidden', 'You cannot delete tickets', ['status' => 403]);
                }
                // Delete replies first
                $wpdb->query("DELETE FROM {$wpdb->prefix}oversee_ticket_replies WHERE ticket_id IN ({$ids_string})");
                $updated = $wpdb->query("DELETE FROM {$wpdb->prefix}oversee_tickets WHERE id IN ({$ids_string})");
                break;
                
            default:
                return new WP_Error('invalid_action', 'Invalid action', ['status' => 400]);
        }
        
        return new WP_REST_Response([
            'success' => true,
            'updated' => $updated,
            'action' => $action
        ]);
    }
    
    /**
     * Add ticket reply
     */
    public static function add_ticket_reply($request) {
        global $wpdb;
        
        $ticket_id = (int) $request->get_param('id');
        $message = wp_kses_post($request->get_param('message'));
        $set_pending = (bool) $request->get_param('set_pending');
        
        if (empty($message)) {
            return new WP_Error('empty_message', 'Message cannot be empty', ['status' => 400]);
        }
        
        // Check ticket exists
        $ticket = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}oversee_tickets WHERE id = %d",
            $ticket_id
        ));
        
        if (!$ticket) {
            return new WP_Error('not_found', 'Ticket not found', ['status' => 404]);
        }
        
        $user = wp_get_current_user();
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'oversee_ticket_replies',
            [
                'ticket_id' => $ticket_id,
                'message' => $message,
                'is_internal_note' => 0,
                'author_type' => 'agent',
                'author_id' => $user->ID,
                'author_name' => $user->display_name,
                'author_email' => $user->user_email
            ],
            ['%d', '%s', '%d', '%s', '%d', '%s', '%s']
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to add reply', ['status' => 500]);
        }
        
        // Update ticket status
        $new_status = $set_pending ? 'pending' : 'open';
        $wpdb->update(
            $wpdb->prefix . 'oversee_tickets',
            ['status' => $new_status],
            ['id' => $ticket_id],
            ['%s'],
            ['%d']
        );
        
        // Trigger webhook
        Oversee_Webhooks::trigger('reply_added', [
            'ticket_id' => $ticket_id,
            'reply_id' => $wpdb->insert_id,
            'agent' => $user->display_name
        ]);
        
        return new WP_REST_Response([
            'id' => $wpdb->insert_id,
            'message' => 'Reply added successfully'
        ], 201);
    }
    
    /**
     * Add internal note
     */
    public static function add_ticket_note($request) {
        global $wpdb;
        
        $ticket_id = (int) $request->get_param('id');
        $message = wp_kses_post($request->get_param('message'));
        
        if (empty($message)) {
            return new WP_Error('empty_message', 'Note cannot be empty', ['status' => 400]);
        }
        
        $user = wp_get_current_user();
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'oversee_ticket_replies',
            [
                'ticket_id' => $ticket_id,
                'message' => $message,
                'is_internal_note' => 1,
                'author_type' => 'agent',
                'author_id' => $user->ID,
                'author_name' => $user->display_name,
                'author_email' => $user->user_email
            ],
            ['%d', '%s', '%d', '%s', '%d', '%s', '%s']
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to add note', ['status' => 500]);
        }
        
        return new WP_REST_Response([
            'id' => $wpdb->insert_id,
            'message' => 'Note added successfully'
        ], 201);
    }
    
    /**
     * Agent routes
     */
    private static function register_agent_routes() {
        // List agents
        register_rest_route('oversee/v1', '/agents', [
            'methods' => 'GET',
            'callback' => function($request) {
                global $wpdb;
                
                $agents = $wpdb->get_results(
                    "SELECT a.*, u.display_name, u.user_email as wp_email,
                            (SELECT COUNT(*) FROM {$wpdb->prefix}oversee_tickets 
                             WHERE assigned_to = a.wp_user_id AND status != 'resolved') as open_tickets
                     FROM {$wpdb->prefix}oversee_agents a
                     LEFT JOIN {$wpdb->users} u ON a.wp_user_id = u.ID
                     ORDER BY u.display_name ASC"
                );
                
                return new WP_REST_Response($agents);
            },
            'permission_callback' => [__CLASS__, 'admin_permission']
        ]);
        
        // Get own profile
        register_rest_route('oversee/v1', '/agent/profile', [
            'methods' => 'GET',
            'callback' => function($request) {
                $agent = Oversee_Auth::get_current_agent();
                $user = wp_get_current_user();
                
                return new WP_REST_Response([
                    'agent' => $agent,
                    'user' => [
                        'id' => $user->ID,
                        'email' => $user->user_email,
                        'display_name' => $user->display_name,
                        'first_name' => $user->first_name,
                        'last_name' => $user->last_name
                    ]
                ]);
            },
            'permission_callback' => [__CLASS__, 'agent_permission']
        ]);
        
        // Update own profile
        register_rest_route('oversee/v1', '/agent/profile', [
            'methods' => 'PUT',
            'callback' => function($request) {
                global $wpdb;
                
                $user_id = get_current_user_id();
                $agent = Oversee_Auth::get_current_agent();
                
                if (!$agent) {
                    Oversee_Auth::ensure_agent_record($user_id);
                    $agent = Oversee_Auth::get_current_agent();
                }
                
                $updates = [];
                $formats = [];
                
                if ($request->has_param('first_name')) {
                    $updates['first_name'] = sanitize_text_field($request->get_param('first_name'));
                    $formats[] = '%s';
                }
                
                if ($request->has_param('last_name')) {
                    $updates['last_name'] = sanitize_text_field($request->get_param('last_name'));
                    $formats[] = '%s';
                }
                
                if ($request->has_param('calendar_link')) {
                    $updates['calendar_link'] = esc_url_raw($request->get_param('calendar_link'));
                    $formats[] = '%s';
                }
                
                if ($request->has_param('zoom_link')) {
                    $updates['zoom_link'] = esc_url_raw($request->get_param('zoom_link'));
                    $formats[] = '%s';
                }
                
                if ($request->has_param('notification_preferences')) {
                    $updates['notification_preferences'] = wp_json_encode($request->get_param('notification_preferences'));
                    $formats[] = '%s';
                }
                
                if (!empty($updates)) {
                    $wpdb->update(
                        $wpdb->prefix . 'oversee_agents',
                        $updates,
                        ['wp_user_id' => $user_id],
                        $formats,
                        ['%d']
                    );
                }
                
                return new WP_REST_Response(['message' => 'Profile updated']);
            },
            'permission_callback' => [__CLASS__, 'agent_permission']
        ]);
        
        // Set online/offline status
        register_rest_route('oversee/v1', '/agent/status', [
            'methods' => 'POST',
            'callback' => function($request) {
                global $wpdb;
                
                $is_online = (bool) $request->get_param('is_online');
                $user_id = get_current_user_id();
                
                Oversee_Auth::ensure_agent_record($user_id);
                
                $wpdb->update(
                    $wpdb->prefix . 'oversee_agents',
                    [
                        'is_online' => $is_online ? 1 : 0,
                        'last_seen' => current_time('mysql')
                    ],
                    ['wp_user_id' => $user_id],
                    ['%d', '%s'],
                    ['%d']
                );
                
                return new WP_REST_Response([
                    'success' => true,
                    'is_online' => $is_online
                ]);
            },
            'permission_callback' => [__CLASS__, 'agent_permission']
        ]);
    }
    
    /**
     * Settings routes
     */
    private static function register_settings_routes() {
        // Get settings
        register_rest_route('oversee/v1', '/settings', [
            'methods' => 'GET',
            'callback' => function($request) {
                $settings = [
                    // General
                    'portal_title' => get_option('oversee_portal_title', 'Help Center'),
                    'primary_color' => get_option('oversee_primary_color', '#f97316'),
                    'secondary_color' => get_option('oversee_secondary_color', '#1e293b'),
                    'logo_url' => get_option('oversee_logo_url', ''),
                    
                    // KB
                    'enable_remote_kb' => get_option('oversee_enable_remote_kb', true),
                    'kb_cache_duration' => get_option('oversee_kb_cache_duration', HOUR_IN_SECONDS),
                    
                    // Assignment
                    'auto_assign_enabled' => get_option('oversee_auto_assign_enabled', true),
                    'skip_offline_agents' => get_option('oversee_skip_offline_agents', true),
                    'balance_by_workload' => get_option('oversee_balance_by_workload', false),
                    
                    // Ticket form
                    'require_phone' => get_option('oversee_require_phone', false),
                    'show_priority' => get_option('oversee_show_priority', false),
                    'allow_attachments' => get_option('oversee_allow_attachments', true),
                    
                    // Email
                    'email_from_address' => get_option('oversee_email_from_address', get_option('admin_email')),
                    'email_from_name' => get_option('oversee_email_from_name', 'Support Team'),
                    'email_on_new_ticket' => get_option('oversee_email_on_new_ticket', true),
                    'email_on_reply' => get_option('oversee_email_on_reply', true),
                    
                    // Push
                    'push_enabled' => get_option('oversee_push_enabled', true),
                    
                    // Webhooks
                    'webhook_url' => get_option('oversee_webhook_url', ''),
                    'webhook_events' => get_option('oversee_webhook_events', []),
                    'incoming_webhook_url' => rest_url('oversee/v1/webhook/incoming'),
                    
                    // Security
                    'hl_secret' => get_option('oversee_hl_secret', ''),
                    'session_timeout' => get_option('oversee_session_timeout', DAY_IN_SECONDS),
                    'max_login_attempts' => get_option('oversee_max_login_attempts', 5),
                    'lockout_duration' => get_option('oversee_lockout_duration', 900),
                ];
                
                return new WP_REST_Response($settings);
            },
            'permission_callback' => [__CLASS__, 'admin_permission']
        ]);
        
        // Update settings
        register_rest_route('oversee/v1', '/settings', [
            'methods' => 'PUT',
            'callback' => function($request) {
                $settings = $request->get_json_params();
                
                $allowed = [
                    'portal_title', 'primary_color', 'secondary_color', 'logo_url',
                    'enable_remote_kb', 'kb_cache_duration',
                    'auto_assign_enabled', 'skip_offline_agents', 'balance_by_workload',
                    'require_phone', 'show_priority', 'allow_attachments',
                    'email_from_address', 'email_from_name', 'email_on_new_ticket', 'email_on_reply',
                    'push_enabled',
                    'webhook_url', 'webhook_events',
                    'session_timeout', 'max_login_attempts', 'lockout_duration'
                ];
                
                foreach ($settings as $key => $value) {
                    if (in_array($key, $allowed)) {
                        update_option('oversee_' . $key, $value);
                    }
                }
                
                return new WP_REST_Response(['message' => 'Settings updated']);
            },
            'permission_callback' => [__CLASS__, 'admin_permission']
        ]);
        
        // Clear KB cache
        register_rest_route('oversee/v1', '/settings/clear-kb-cache', [
            'methods' => 'POST',
            'callback' => function($request) {
                $client = new Oversee_KB_Client();
                $client->clear_cache();
                return new WP_REST_Response(['message' => 'KB cache cleared']);
            },
            'permission_callback' => [__CLASS__, 'admin_permission']
        ]);
        
        // Regenerate HL secret
        register_rest_route('oversee/v1', '/settings/regenerate-hl-secret', [
            'methods' => 'POST',
            'callback' => function($request) {
                $new_secret = bin2hex(random_bytes(16));
                update_option('oversee_hl_secret', $new_secret);
                return new WP_REST_Response([
                    'message' => 'Secret regenerated',
                    'secret' => $new_secret
                ]);
            },
            'permission_callback' => [__CLASS__, 'admin_permission']
        ]);
    }
    
    /**
     * Canned responses routes
     */
    private static function register_canned_routes() {
        register_rest_route('oversee/v1', '/canned-responses', [
            'methods' => 'GET',
            'callback' => function($request) {
                global $wpdb;
                $responses = $wpdb->get_results(
                    "SELECT * FROM {$wpdb->prefix}oversee_canned_responses ORDER BY title ASC"
                );
                return new WP_REST_Response($responses);
            },
            'permission_callback' => [__CLASS__, 'agent_permission']
        ]);
        
        register_rest_route('oversee/v1', '/canned-responses', [
            'methods' => 'POST',
            'callback' => function($request) {
                global $wpdb;
                
                $title = sanitize_text_field($request->get_param('title'));
                $content = wp_kses_post($request->get_param('content'));
                $shortcut = sanitize_text_field($request->get_param('shortcut'));
                
                if (empty($title) || empty($content)) {
                    return new WP_Error('missing_fields', 'Title and content are required', ['status' => 400]);
                }
                
                $wpdb->insert(
                    $wpdb->prefix . 'oversee_canned_responses',
                    [
                        'title' => $title,
                        'content' => $content,
                        'shortcut' => $shortcut,
                        'created_by' => get_current_user_id()
                    ],
                    ['%s', '%s', '%s', '%d']
                );
                
                return new WP_REST_Response(['id' => $wpdb->insert_id], 201);
            },
            'permission_callback' => [__CLASS__, 'admin_permission']
        ]);
        
        register_rest_route('oversee/v1', '/canned-responses/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => function($request) {
                global $wpdb;
                
                $id = (int) $request->get_param('id');
                $title = sanitize_text_field($request->get_param('title'));
                $content = wp_kses_post($request->get_param('content'));
                $shortcut = sanitize_text_field($request->get_param('shortcut'));
                
                $wpdb->update(
                    $wpdb->prefix . 'oversee_canned_responses',
                    ['title' => $title, 'content' => $content, 'shortcut' => $shortcut],
                    ['id' => $id],
                    ['%s', '%s', '%s'],
                    ['%d']
                );
                
                return new WP_REST_Response(['message' => 'Updated']);
            },
            'permission_callback' => [__CLASS__, 'admin_permission']
        ]);
        
        register_rest_route('oversee/v1', '/canned-responses/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => function($request) {
                global $wpdb;
                $id = (int) $request->get_param('id');
                $wpdb->delete($wpdb->prefix . 'oversee_canned_responses', ['id' => $id], ['%d']);
                return new WP_REST_Response(['message' => 'Deleted']);
            },
            'permission_callback' => [__CLASS__, 'admin_permission']
        ]);
    }
    
    /**
     * Push notification routes
     */
    private static function register_push_routes() {
        register_rest_route('oversee/v1', '/push/vapid-key', [
            'methods' => 'GET',
            'callback' => function($request) {
                $public_key = get_option('oversee_vapid_public_key', '');
                return new WP_REST_Response(['publicKey' => $public_key]);
            },
            'permission_callback' => '__return_true'
        ]);
        
        register_rest_route('oversee/v1', '/push/subscribe', [
            'methods' => 'POST',
            'callback' => function($request) {
                global $wpdb;
                
                $endpoint = $request->get_param('endpoint');
                $p256dh = $request->get_param('p256dh');
                $auth = $request->get_param('auth');
                
                if (empty($endpoint) || empty($p256dh) || empty($auth)) {
                    return new WP_Error('missing_fields', 'Missing subscription data', ['status' => 400]);
                }
                
                // Delete existing subscription for this endpoint
                $wpdb->delete(
                    $wpdb->prefix . 'oversee_push_subscriptions',
                    ['endpoint' => $endpoint],
                    ['%s']
                );
                
                // Insert new subscription
                $wpdb->insert(
                    $wpdb->prefix . 'oversee_push_subscriptions',
                    [
                        'endpoint' => $endpoint,
                        'p256dh_key' => $p256dh,
                        'auth_key' => $auth,
                        'user_id' => get_current_user_id(),
                        'user_type' => 'agent'
                    ],
                    ['%s', '%s', '%s', '%d', '%s']
                );
                
                return new WP_REST_Response(['message' => 'Subscribed']);
            },
            'permission_callback' => [__CLASS__, 'agent_permission']
        ]);
        
        register_rest_route('oversee/v1', '/push/unsubscribe', [
            'methods' => 'DELETE',
            'callback' => function($request) {
                global $wpdb;
                
                $endpoint = $request->get_param('endpoint');
                
                $wpdb->delete(
                    $wpdb->prefix . 'oversee_push_subscriptions',
                    ['endpoint' => $endpoint],
                    ['%s']
                );
                
                return new WP_REST_Response(['message' => 'Unsubscribed']);
            },
            'permission_callback' => [__CLASS__, 'agent_permission']
        ]);
    }
    
    /**
     * Webhook routes
     */
    private static function register_webhook_routes() {
        register_rest_route('oversee/v1', '/webhook/incoming', [
            'methods' => 'POST',
            'callback' => function($request) {
                $secret = $request->get_header('X-Webhook-Secret');
                $stored_secret = get_option('oversee_incoming_webhook_secret');
                
                if (empty($stored_secret) || !hash_equals($stored_secret, $secret ?? '')) {
                    return new WP_Error('invalid_secret', 'Invalid webhook secret', ['status' => 401]);
                }
                
                $data = $request->get_json_params();
                
                // Create ticket from webhook
                $subject = isset($data['subject']) ? sanitize_text_field($data['subject']) : 'New Ticket';
                $description = isset($data['description']) ? wp_kses_post($data['description']) : '';
                $email = isset($data['email']) ? sanitize_email($data['email']) : '';
                $name = isset($data['name']) ? sanitize_text_field($data['name']) : '';
                
                if (empty($email)) {
                    return new WP_Error('missing_email', 'Email is required', ['status' => 400]);
                }
                
                global $wpdb;
                
                $ticket_number = 'TKT-' . strtoupper(substr(md5(uniqid()), 0, 8));
                $access_token = bin2hex(random_bytes(32));
                
                $wpdb->insert(
                    $wpdb->prefix . 'oversee_tickets',
                    [
                        'ticket_number' => $ticket_number,
                        'subject' => $subject,
                        'description' => $description,
                        'customer_email' => $email,
                        'customer_name' => $name,
                        'status' => 'new',
                        'priority' => 'low',
                        'access_token' => $access_token
                    ],
                    ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
                );
                
                return new WP_REST_Response([
                    'success' => true,
                    'ticket_id' => $wpdb->insert_id,
                    'ticket_number' => $ticket_number
                ], 201);
            },
            'permission_callback' => '__return_true'
        ]);
    }
    
    /**
     * Maybe auto-assign ticket
     */
    private static function maybe_auto_assign($ticket_id) {
        if (!get_option('oversee_auto_assign_enabled', true)) {
            return;
        }
        
        global $wpdb;
        
        $skip_offline = get_option('oversee_skip_offline_agents', true);
        $balance_workload = get_option('oversee_balance_by_workload', false);
        
        // Get available agents
        $where = "a.wp_user_id IS NOT NULL";
        if ($skip_offline) {
            $where .= " AND a.is_online = 1";
        }
        
        if ($balance_workload) {
            $query = "SELECT a.wp_user_id, 
                            (SELECT COUNT(*) FROM {$wpdb->prefix}oversee_tickets 
                             WHERE assigned_to = a.wp_user_id AND status != 'resolved') as ticket_count
                      FROM {$wpdb->prefix}oversee_agents a
                      WHERE {$where}
                      ORDER BY ticket_count ASC
                      LIMIT 1";
        } else {
            // Round robin - get agent with oldest last assignment
            $query = "SELECT a.wp_user_id
                      FROM {$wpdb->prefix}oversee_agents a
                      WHERE {$where}
                      ORDER BY a.last_seen ASC
                      LIMIT 1";
        }
        
        $agent_id = $wpdb->get_var($query);
        
        if ($agent_id) {
            $wpdb->update(
                $wpdb->prefix . 'oversee_tickets',
                ['assigned_to' => $agent_id],
                ['id' => $ticket_id],
                ['%d'],
                ['%d']
            );
        }
    }
    
    /**
     * Permission callbacks
     */
    public static function agent_permission() {
        return current_user_can('oversee_view_dashboard');
    }
    
    public static function admin_permission() {
        return current_user_can('oversee_manage_settings');
    }
    
    public static function respond_permission() {
        return current_user_can('oversee_respond_tickets');
    }
}
