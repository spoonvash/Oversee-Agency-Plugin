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
        self::register_sessions_routes();
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
                             WHERE assigned_to = a.wp_user_id AND status != 'resolved') as open_tickets,
                            (SELECT COUNT(*) FROM {$wpdb->prefix}oversee_tickets 
                             WHERE assigned_to = a.wp_user_id AND status = 'resolved' 
                             AND resolved_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as resolved_this_week,
                            (SELECT COUNT(*) FROM {$wpdb->prefix}oversee_ticket_replies 
                             WHERE author_id = a.wp_user_id AND author_type = 'agent' 
                             AND is_internal_note = 0
                             AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)) as replies_this_week
                     FROM {$wpdb->prefix}oversee_agents a
                     LEFT JOIN {$wpdb->users} u ON a.wp_user_id = u.ID
                     ORDER BY a.is_online DESC, u.display_name ASC"
                );
                
                // Format response with calculated metrics
                $result = [];
                foreach ($agents as $agent) {
                    $avg_frt = (int) ($agent->avg_first_response_seconds ?? 0);
                    
                    $result[] = [
                        'id' => $agent->id,
                        'wp_user_id' => $agent->wp_user_id,
                        'email' => $agent->email,
                        'display_name' => $agent->display_name ?: $agent->email,
                        'role' => $agent->role,
                        'is_online' => (bool) $agent->is_online,
                        'last_seen' => $agent->last_seen,
                        'open_tickets' => (int) $agent->open_tickets,
                        'metrics' => [
                            'resolved_total' => (int) ($agent->tickets_resolved_total ?? 0),
                            'resolved_week' => (int) $agent->resolved_this_week,
                            'resolved_month' => (int) ($agent->tickets_resolved_month ?? 0),
                            'responses_total' => (int) ($agent->responses_total ?? 0),
                            'responses_week' => (int) $agent->replies_this_week,
                            'responses_month' => (int) ($agent->responses_month ?? 0),
                            'avg_first_response_seconds' => $avg_frt,
                            'avg_first_response_formatted' => self::format_duration($avg_frt),
                            'first_responses_count' => (int) ($agent->first_responses_count ?? 0)
                        ]
                    ];
                }
                
                return new WP_REST_Response($result);
            },
            'permission_callback' => [__CLASS__, 'agent_permission']
        ]);
        
        // Get team metrics summary
        register_rest_route('oversee/v1', '/dashboard/team-metrics', [
            'methods' => 'GET',
            'callback' => function($request) {
                global $wpdb;
                
                $period = $request->get_param('period') ?: 'week';
                
                // Calculate date range based on period
                switch ($period) {
                    case 'today':
                        $date_filter = "DATE(created_at) = CURDATE()";
                        $resolved_filter = "DATE(resolved_at) = CURDATE()";
                        break;
                    case 'month':
                        $date_filter = "created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                        $resolved_filter = "resolved_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                        break;
                    case 'week':
                    default:
                        $date_filter = "created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                        $resolved_filter = "resolved_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                        break;
                }
                
                // Overall team metrics
                $metrics = [
                    'tickets_created' => (int) $wpdb->get_var(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}oversee_tickets WHERE {$date_filter}"
                    ),
                    'tickets_resolved' => (int) $wpdb->get_var(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}oversee_tickets 
                         WHERE status = 'resolved' AND {$resolved_filter}"
                    ),
                    'total_responses' => (int) $wpdb->get_var(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}oversee_ticket_replies 
                         WHERE author_type = 'agent' AND is_internal_note = 0 AND {$date_filter}"
                    ),
                    'avg_first_response_seconds' => (int) $wpdb->get_var(
                        "SELECT AVG(first_response_seconds) FROM {$wpdb->prefix}oversee_tickets 
                         WHERE first_response_seconds IS NOT NULL AND {$date_filter}"
                    ) ?: 0,
                    'tickets_without_response' => (int) $wpdb->get_var(
                        "SELECT COUNT(*) FROM {$wpdb->prefix}oversee_tickets 
                         WHERE first_response_at IS NULL AND status != 'resolved'"
                    )
                ];
                
                $metrics['avg_first_response_formatted'] = self::format_duration($metrics['avg_first_response_seconds']);
                
                // Resolution rate
                $metrics['resolution_rate'] = $metrics['tickets_created'] > 0 
                    ? round(($metrics['tickets_resolved'] / $metrics['tickets_created']) * 100, 1) 
                    : 0;
                
                // Agent leaderboard
                $leaderboard = $wpdb->get_results(
                    "SELECT 
                        a.wp_user_id,
                        u.display_name,
                        a.is_online,
                        COUNT(DISTINCT CASE WHEN t.status = 'resolved' AND t.{$resolved_filter} THEN t.id END) as resolved,
                        COUNT(DISTINCT r.id) as responses,
                        AVG(t.first_response_seconds) as avg_frt
                     FROM {$wpdb->prefix}oversee_agents a
                     LEFT JOIN {$wpdb->users} u ON a.wp_user_id = u.ID
                     LEFT JOIN {$wpdb->prefix}oversee_tickets t ON t.assigned_to = a.wp_user_id
                     LEFT JOIN {$wpdb->prefix}oversee_ticket_replies r ON r.author_id = a.wp_user_id 
                        AND r.author_type = 'agent' AND r.is_internal_note = 0 AND r.{$date_filter}
                     WHERE a.wp_user_id IS NOT NULL
                     GROUP BY a.wp_user_id, u.display_name, a.is_online
                     ORDER BY resolved DESC, responses DESC
                     LIMIT 10"
                );
                
                $metrics['leaderboard'] = array_map(function($row) {
                    return [
                        'wp_user_id' => $row->wp_user_id,
                        'display_name' => $row->display_name,
                        'is_online' => (bool) $row->is_online,
                        'resolved' => (int) $row->resolved,
                        'responses' => (int) $row->responses,
                        'avg_frt_formatted' => self::format_duration((int) $row->avg_frt)
                    ];
                }, $leaderboard);
                
                return new WP_REST_Response($metrics);
            },
            'permission_callback' => [__CLASS__, 'agent_permission']
        ]);
    }
    
    /**
     * Format duration in seconds to human readable string
     */
    private static function format_duration($seconds) {
        if ($seconds <= 0) {
            return '-';
        }
        
        if ($seconds < 60) {
            return $seconds . 's';
        }
        
        if ($seconds < 3600) {
            $mins = floor($seconds / 60);
            return $mins . 'm';
        }
        
        if ($seconds < 86400) {
            $hours = floor($seconds / 3600);
            $mins = floor(($seconds % 3600) / 60);
            return $mins > 0 ? "{$hours}h {$mins}m" : "{$hours}h";
        }
        
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        return $hours > 0 ? "{$days}d {$hours}h" : "{$days}d";
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
        
        // Ticket stats
        register_rest_route('oversee/v1', '/tickets/stats', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_ticket_stats'],
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
            'methods' => ['PUT', 'PATCH'],
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
        
        // Admin file upload (authenticated)
        register_rest_route('oversee/v1', '/upload', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'upload_attachment'],
            'permission_callback' => [__CLASS__, 'agent_permission']
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
        
        // Get single public ticket by ID (for polling/refresh)
        register_rest_route('oversee/v1', '/tickets/public/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_single_public_ticket'],
            'permission_callback' => '__return_true'
        ]);
        
        // Public reply to ticket (no auth required, uses access token)
        register_rest_route('oversee/v1', '/tickets/public/(?P<token>[a-f0-9]+)/reply', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'add_public_reply'],
            'permission_callback' => '__return_true'
        ]);
        
        // Public reply to ticket via email verification (no auth required)
        register_rest_route('oversee/v1', '/tickets/public/reply', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'add_public_reply_by_email'],
            'permission_callback' => '__return_true'
        ]);
        
        // Public file upload for ticket attachments (no auth required)
        register_rest_route('oversee/v1', '/tickets/public/upload', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'upload_public_attachment'],
            'permission_callback' => '__return_true'
        ]);
        
        // Form token endpoint for spam protection (no auth required)
        register_rest_route('oversee/v1', '/form-token', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'generate_form_token'],
            'permission_callback' => '__return_true'
        ]);
        
        // Debug endpoint to check table structure (no auth required)
        register_rest_route('oversee/v1', '/debug/tickets', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'debug_tickets_table'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    /**
     * Debug endpoint to check table status
     */
    public static function debug_tickets_table($request) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'oversee_tickets';
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        $result = [
            'table_name' => $table_name,
            'table_exists' => $table_exists,
            'wp_prefix' => $wpdb->prefix,
        ];
        
        if ($table_exists) {
            // Get table structure
            $columns = $wpdb->get_results("DESCRIBE {$table_name}");
            $result['columns'] = array_map(function($col) {
                return [
                    'name' => $col->Field,
                    'type' => $col->Type,
                    'null' => $col->Null,
                    'key' => $col->Key,
                    'default' => $col->Default
                ];
            }, $columns);
            
            // Count rows
            $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");
            $result['row_count'] = intval($count);
            
            // Try a test insert
            $test_number = 'TEST-' . bin2hex(random_bytes(4));
            $test_token = bin2hex(random_bytes(32));
            
            $wpdb->suppress_errors(true);
            $test_result = $wpdb->insert($table_name, [
                'ticket_number' => $test_number,
                'subject' => 'Debug Test Ticket',
                'description' => 'This is a test',
                'status' => 'new',
                'priority' => 'low',
                'customer_name' => 'Debug User',
                'customer_email' => 'debug@test.com',
                'customer_phone' => '',
                'access_token' => $test_token,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            ], ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);
            
            $insert_error = $wpdb->last_error;
            $insert_query = $wpdb->last_query;
            $wpdb->suppress_errors(false);
            
            if ($test_result !== false) {
                $test_id = $wpdb->insert_id;
                // Delete the test row
                $wpdb->delete($table_name, ['id' => $test_id], ['%d']);
                $result['insert_test'] = 'SUCCESS';
                $result['test_insert_id'] = $test_id;
            } else {
                $result['insert_test'] = 'FAILED';
                $result['insert_error'] = $insert_error;
                $result['insert_query'] = $insert_query;
            }
        } else {
            $result['suggestion'] = 'Table does not exist. Deactivate and reactivate the plugin.';
        }
        
        return new WP_REST_Response($result);
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
     * Get single public ticket with replies (for polling)
     */
    public static function get_single_public_ticket($request) {
        global $wpdb;
        
        $id = intval($request->get_param('id'));
        $email = sanitize_email($request->get_param('email'));
        
        if (empty($email) || !is_email($email)) {
            return new WP_Error('invalid_email', 'Please provide a valid email address.', ['status' => 400]);
        }
        
        // Get ticket (verify email ownership)
        $ticket = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}oversee_tickets WHERE id = %d AND customer_email = %s",
            $id, $email
        ), ARRAY_A);
        
        if (!$ticket) {
            return new WP_Error('not_found', 'Ticket not found.', ['status' => 404]);
        }
        
        // Get replies (exclude internal notes)
        $replies = $wpdb->get_results($wpdb->prepare(
            "SELECT id, message, author_type, author_name, created_at
             FROM {$wpdb->prefix}oversee_ticket_replies
             WHERE ticket_id = %d AND is_internal_note = 0
             ORDER BY created_at ASC",
            $id
        ), ARRAY_A);
        
        $ticket['replies'] = $replies ?: [];
        
        return new WP_REST_Response($ticket);
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
        
        // Trigger HighLevel workflow for customer reply
        $agent_name = '';
        if ($ticket->assigned_to) {
            $agent = get_userdata($ticket->assigned_to);
            $agent_name = $agent ? $agent->display_name : '';
        }
        self::trigger_hl_workflow('customer_replied', [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'customer_name' => $ticket->customer_name,
            'customer_email' => $ticket->customer_email,
            'customer_phone' => $ticket->customer_phone,
            'status' => $ticket->status,
            'priority' => $ticket->priority,
            'assigned_agent' => $agent_name,
            'created_at' => $ticket->created_at
        ], $message);
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'Reply added successfully'
        ]);
    }
    
    /**
     * Add a reply to a ticket via email verification (public)
     */
    public static function add_public_reply_by_email($request) {
        global $wpdb;
        
        $ticket_id = intval($request->get_param('ticket_id'));
        $email = sanitize_email($request->get_param('email'));
        $message = wp_kses_post($request->get_param('message'));
        
        if (empty($email) || !is_email($email)) {
            return new WP_Error('invalid_email', 'Please provide a valid email address.', ['status' => 400]);
        }
        
        if (empty($message)) {
            return new WP_Error('missing_message', 'Please provide a message.', ['status' => 400]);
        }
        
        // Find ticket by ID and verify email ownership
        $ticket = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}oversee_tickets WHERE id = %d AND customer_email = %s",
            $ticket_id, $email
        ));
        
        if (!$ticket) {
            return new WP_Error('not_found', 'Ticket not found or email does not match.', ['status' => 404]);
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
        
        // Update ticket status and timestamp - reopen if resolved
        $new_status = $ticket->status;
        if ($ticket->status === 'resolved' || $ticket->status === 'closed') {
            $new_status = 'open';
        } elseif ($ticket->status === 'new') {
            $new_status = 'open';
        }
        
        $wpdb->update(
            $wpdb->prefix . 'oversee_tickets',
            [
                'status' => $new_status,
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
            'message' => 'Reply added successfully',
            'new_status' => $new_status
        ]);
    }
    
    /**
     * Create public ticket with spam protection
     */
    public static function create_public_ticket($request) {
        global $wpdb;
        
        // Get input
        $customer_email = sanitize_email($request->get_param('customer_email'));
        $customer_name = sanitize_text_field($request->get_param('customer_name') ?: '');
        $customer_phone = sanitize_text_field($request->get_param('customer_phone') ?: '');
        $subject = sanitize_text_field($request->get_param('subject'));
        $description = sanitize_textarea_field($request->get_param('description'));
        $priority_input = $request->get_param('priority');
        
        // Honeypot check
        if (!empty($request->get_param('website_url'))) {
            return new WP_REST_Response(['success' => true, 'message' => 'Ticket submitted.'], 200);
        }
        
        // Validate
        if (empty($customer_email) || !is_email($customer_email)) {
            return new WP_Error('invalid_email', 'Please provide a valid email address.', ['status' => 400]);
        }
        if (empty($subject)) {
            return new WP_Error('invalid_subject', 'Please provide a subject.', ['status' => 400]);
        }
        if (empty($description)) {
            return new WP_Error('invalid_description', 'Please provide a message.', ['status' => 400]);
        }
        
        // Normalize priority
        $priority = ($priority_input === 'high') ? 'high' : 'low';
        
        // Generate IDs
        $ticket_number = 'TKT-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        $access_token = substr(md5(uniqid(mt_rand(), true)) . md5(uniqid(mt_rand(), true)), 0, 64);
        $now = current_time('mysql');
        
        $table = $wpdb->prefix . 'oversee_tickets';
        
        // Insert ticket
        $result = $wpdb->insert(
            $table,
            array(
                'ticket_number' => $ticket_number,
                'subject' => $subject,
                'description' => $description,
                'status' => 'new',
                'priority' => $priority,
                'customer_name' => $customer_name,
                'customer_email' => $customer_email,
                'customer_phone' => $customer_phone,
                'access_token' => $access_token,
                'created_at' => $now,
                'updated_at' => $now
            ),
            array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s')
        );
        
        if ($result === false) {
            return new WP_Error('create_failed', 'Database error: ' . $wpdb->last_error, ['status' => 500]);
        }
        
        $ticket_id = $wpdb->insert_id;
        
        // Auto-assignment (non-critical)
        $settings = get_option('oversee_settings', []);
        if (!empty($settings['auto_assign'])) {
            $agent_id = self::get_next_available_agent();
            if ($agent_id) {
                $wpdb->update($table, ['assigned_to' => $agent_id], ['id' => $ticket_id], ['%d'], ['%d']);
            }
        }
        
        return new WP_REST_Response([
            'success' => true,
            'ticket_id' => $ticket_id,
            'ticket_number' => $ticket_number,
            'access_token' => $access_token,
            'message' => 'Ticket created successfully'
        ], 201);
    }
    
    /**
     * Generate form token for time-based validation
     */
    public static function generate_form_token($request) {
        $token = bin2hex(random_bytes(16));
        set_transient('oversee_form_' . $token, time(), HOUR_IN_SECONDS);
        
        return new WP_REST_Response([
            'token' => $token
        ], 200);
    }
    
    /**
     * Upload attachment (authenticated - for admin use)
     */
    public static function upload_attachment($request) {
        // Check if file was uploaded via $_FILES
        if (empty($_FILES['file'])) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'No file uploaded',
                'debug' => 'FILES array is empty'
            ], 400);
        }
        
        $file = $_FILES['file'];
        
        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $error_messages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the upload',
            ];
            $error_msg = $error_messages[$file['error']] ?? 'Unknown upload error';
            return new WP_REST_Response([
                'success' => false,
                'error' => $error_msg
            ], 400);
        }
        
        // Validate file size (10MB max)
        $max_size = 10 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'File is too large. Maximum size is 10MB.'
            ], 400);
        }
        
        // Validate file type
        $allowed_types = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp',
            'application/pdf',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain', 'text/csv',
            'application/zip', 'application/x-zip-compressed'
        ];
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'File type not allowed: ' . $mime_type
            ], 400);
        }
        
        // Use WordPress upload directory
        $upload_dir = wp_upload_dir();
        $oversee_dir = $upload_dir['basedir'] . '/oversee-attachments';
        
        // Create directory if it doesn't exist
        if (!file_exists($oversee_dir)) {
            wp_mkdir_p($oversee_dir);
            file_put_contents($oversee_dir . '/index.php', '<?php // Silence is golden');
        }
        
        // Generate unique filename preserving extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = wp_generate_uuid4() . '.' . $ext;
        $filepath = $oversee_dir . '/' . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'Failed to save file'
            ], 500);
        }
        
        // Generate URL
        $url = $upload_dir['baseurl'] . '/oversee-attachments/' . $filename;
        
        return new WP_REST_Response([
            'success' => true,
            'url' => $url,
            'name' => $file['name'],
            'type' => strpos($mime_type, 'image/') === 0 ? 'image' : 'file',
            'mime' => $mime_type
        ], 200);
    }
    
    /**
     * Upload attachment for public ticket
     */
    public static function upload_public_attachment($request) {
        // Check if file was uploaded
        if (empty($_FILES['file'])) {
            return new WP_Error('no_file', 'No file uploaded.', ['status' => 400]);
        }
        
        $file = $_FILES['file'];
        
        // Validate file size (10MB max)
        $max_size = 10 * 1024 * 1024;
        if ($file['size'] > $max_size) {
            return new WP_Error('file_too_large', 'File is too large. Maximum size is 10MB.', ['status' => 400]);
        }
        
        // Validate file type
        $allowed_types = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf',
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain', 'text/csv',
            'application/zip', 'application/x-zip-compressed'
        ];
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime_type = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mime_type, $allowed_types)) {
            return new WP_Error('invalid_type', 'File type not allowed.', ['status' => 400]);
        }
        
        // Include WordPress file handling
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        
        // Upload to WordPress uploads directory
        $upload_dir = wp_upload_dir();
        $oversee_dir = $upload_dir['basedir'] . '/oversee-attachments';
        
        // Create directory if it doesn't exist
        if (!file_exists($oversee_dir)) {
            wp_mkdir_p($oversee_dir);
            // Add index.php to prevent directory listing
            file_put_contents($oversee_dir . '/index.php', '<?php // Silence is golden');
        }
        
        // Generate unique filename
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = md5(uniqid(mt_rand(), true)) . '.' . $ext;
        $filepath = $oversee_dir . '/' . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            return new WP_Error('upload_failed', 'Failed to save file.', ['status' => 500]);
        }
        
        // Generate URL
        $url = $upload_dir['baseurl'] . '/oversee-attachments/' . $filename;
        
        return new WP_REST_Response([
            'success' => true,
            'url' => $url,
            'name' => $file['name'],
            'type' => strpos($mime_type, 'image/') === 0 ? 'image' : 'file'
        ], 200);
    }
    
    /**
     * Get next available agent for auto-assignment
     */
    private static function get_next_available_agent() {
        global $wpdb;
        
        $settings = get_option('oversee_settings', []);
        $skip_offline = !empty($settings['assign_online_only']);
        $assignment_method = $settings['assignment_method'] ?? 'round_robin';
        $max_tickets = (int) ($settings['max_tickets_per_agent'] ?? 20);
        
        $where = [];
        if ($skip_offline) {
            $where[] = 'a.is_online = 1';
        }
        
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        
        if ($assignment_method === 'least_busy') {
            // Assign to agent with fewest open tickets
            $agent = $wpdb->get_row(
                "SELECT a.wp_user_id, 
                        COUNT(t.id) as open_tickets
                 FROM {$wpdb->prefix}oversee_agents a
                 LEFT JOIN {$wpdb->prefix}oversee_tickets t 
                    ON a.wp_user_id = t.assigned_to AND t.status IN ('new', 'open', 'pending')
                 {$where_clause}
                 GROUP BY a.wp_user_id
                 HAVING open_tickets < {$max_tickets}
                 ORDER BY open_tickets ASC
                 LIMIT 1"
            );
        } else if ($assignment_method === 'random') {
            // Random assignment
            $agent = $wpdb->get_row(
                "SELECT a.wp_user_id,
                        COUNT(t.id) as open_tickets
                 FROM {$wpdb->prefix}oversee_agents a
                 LEFT JOIN {$wpdb->prefix}oversee_tickets t 
                    ON a.wp_user_id = t.assigned_to AND t.status IN ('new', 'open', 'pending')
                 {$where_clause}
                 GROUP BY a.wp_user_id
                 HAVING open_tickets < {$max_tickets}
                 ORDER BY RAND()
                 LIMIT 1"
            );
        } else {
            // Round-robin: get agent who hasn't been assigned recently
            $agent = $wpdb->get_row(
                "SELECT a.wp_user_id,
                        COUNT(t2.id) as open_tickets
                 FROM {$wpdb->prefix}oversee_agents a
                 LEFT JOIN (
                    SELECT assigned_to, MAX(created_at) as last_assigned
                    FROM {$wpdb->prefix}oversee_tickets
                    WHERE assigned_to IS NOT NULL
                    GROUP BY assigned_to
                 ) t ON a.wp_user_id = t.assigned_to
                 LEFT JOIN {$wpdb->prefix}oversee_tickets t2 
                    ON a.wp_user_id = t2.assigned_to AND t2.status IN ('new', 'open', 'pending')
                 {$where_clause}
                 GROUP BY a.wp_user_id
                 HAVING open_tickets < {$max_tickets}
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
     * Get ticket statistics
     */
    public static function get_ticket_stats($request) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'oversee_tickets';
        
        // Get counts by status
        $new = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'new'");
        $open = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'open'");
        $pending = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'pending'");
        $resolved = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'resolved'");
        
        // Get high priority count (non-resolved)
        $high_priority = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE priority = 'high' AND status NOT IN ('resolved', 'closed')");
        
        // Get total
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        
        return new WP_REST_Response([
            'new' => $new,
            'open' => $open,
            'pending' => $pending,
            'resolved' => $resolved,
            'high_priority' => $high_priority,
            'total' => $total
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
        $is_being_resolved = false;
        
        if ($request->has_param('status')) {
            $status = $request->get_param('status');
            if (in_array($status, ['new', 'open', 'pending', 'resolved', 'closed'])) {
                $updates['status'] = $status;
                $formats[] = '%s';
                
                if ($status === 'resolved' && $ticket->status !== 'resolved') {
                    $updates['resolved_at'] = current_time('mysql');
                    $formats[] = '%s';
                    $is_being_resolved = true;
                }
            }
        }
        
        if ($request->has_param('priority')) {
            $priority = $request->get_param('priority');
            if (in_array($priority, ['low', 'normal', 'high'])) {
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
            return new WP_REST_Response(['message' => 'No changes made']);
        }
        
        // Always update timestamp
        $updates['updated_at'] = current_time('mysql');
        $formats[] = '%s';
        
        $result = $wpdb->update(
            $wpdb->prefix . 'oversee_tickets',
            $updates,
            ['id' => $id],
            $formats,
            ['%d']
        );
        
        // Update agent metrics if ticket was resolved
        if ($is_being_resolved) {
            $resolver_id = get_current_user_id();
            // If ticket was assigned, credit the assignee; otherwise credit current user
            if (!empty($ticket->assigned_to)) {
                $resolver_id = $ticket->assigned_to;
            }
            self::update_agent_resolved_metrics($resolver_id);
            
            // Trigger HighLevel workflow for resolved ticket
            $agent_name = '';
            $resolver = get_userdata($resolver_id);
            if ($resolver) {
                $agent_name = $resolver->display_name;
            }
            self::trigger_hl_workflow('ticket_resolved', [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'customer_name' => $ticket->customer_name,
                'customer_email' => $ticket->customer_email,
                'customer_phone' => $ticket->customer_phone,
                'status' => 'resolved',
                'priority' => $ticket->priority,
                'assigned_agent' => $agent_name,
                'created_at' => $ticket->created_at
            ]);
        }
        
        // Trigger webhook
        Oversee_Webhooks::trigger('ticket_updated', [
            'ticket_id' => $id,
            'updates' => $updates,
            'resolved' => $is_being_resolved
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
        $now = current_time('mysql');
        
        // Insert the reply
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
        
        $reply_id = $wpdb->insert_id;
        
        // Track first response time if this is the first agent reply
        $is_first_response = empty($ticket->first_response_at);
        $first_response_seconds = 0;
        
        if ($is_first_response) {
            // Calculate seconds since ticket creation
            $created_time = strtotime($ticket->created_at);
            $now_time = time();
            $first_response_seconds = $now_time - $created_time;
            
            // Update ticket with first response data
            $wpdb->update(
                $wpdb->prefix . 'oversee_tickets',
                [
                    'first_response_at' => $now,
                    'first_response_by' => $user->ID,
                    'first_response_seconds' => $first_response_seconds
                ],
                ['id' => $ticket_id],
                ['%s', '%d', '%d'],
                ['%d']
            );
        }
        
        // Update agent metrics
        self::update_agent_metrics($user->ID, $is_first_response, $first_response_seconds);
        
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
            'reply_id' => $reply_id,
            'agent' => $user->display_name,
            'is_first_response' => $is_first_response,
            'first_response_seconds' => $first_response_seconds
        ]);
        
        // Trigger HighLevel workflow webhook
        self::trigger_hl_workflow('ticket_replied', [
            'id' => $ticket->id,
            'ticket_number' => $ticket->ticket_number,
            'subject' => $ticket->subject,
            'customer_name' => $ticket->customer_name,
            'customer_email' => $ticket->customer_email,
            'customer_phone' => $ticket->customer_phone,
            'status' => $new_status,
            'priority' => $ticket->priority,
            'assigned_agent' => $user->display_name,
            'created_at' => $ticket->created_at
        ], $message);
        
        return new WP_REST_Response([
            'id' => $reply_id,
            'message' => 'Reply added successfully',
            'is_first_response' => $is_first_response
        ], 201);
    }
    
    /**
     * Update agent metrics after a reply
     */
    private static function update_agent_metrics($user_id, $is_first_response, $first_response_seconds = 0) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'oversee_agents';
        
        // Get current agent record
        $agent = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE wp_user_id = %d",
            $user_id
        ));
        
        if (!$agent) {
            return;
        }
        
        // Increment response counters
        $updates = [
            'responses_total' => ($agent->responses_total ?? 0) + 1,
            'responses_week' => ($agent->responses_week ?? 0) + 1,
            'responses_month' => ($agent->responses_month ?? 0) + 1,
            'metrics_last_updated' => current_time('mysql')
        ];
        
        // If this is a first response, update first response metrics
        if ($is_first_response && $first_response_seconds > 0) {
            $current_count = (int) ($agent->first_responses_count ?? 0);
            $current_avg = (int) ($agent->avg_first_response_seconds ?? 0);
            
            // Calculate new rolling average
            $new_count = $current_count + 1;
            $new_avg = (($current_avg * $current_count) + $first_response_seconds) / $new_count;
            
            $updates['first_responses_count'] = $new_count;
            $updates['avg_first_response_seconds'] = (int) $new_avg;
        }
        
        $wpdb->update($table, $updates, ['wp_user_id' => $user_id]);
    }
    
    /**
     * Update agent metrics when a ticket is resolved
     */
    public static function update_agent_resolved_metrics($user_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'oversee_agents';
        
        $agent = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE wp_user_id = %d",
            $user_id
        ));
        
        if (!$agent) {
            return;
        }
        
        $wpdb->update(
            $table,
            [
                'tickets_resolved_total' => ($agent->tickets_resolved_total ?? 0) + 1,
                'tickets_resolved_week' => ($agent->tickets_resolved_week ?? 0) + 1,
                'tickets_resolved_month' => ($agent->tickets_resolved_month ?? 0) + 1,
                'metrics_last_updated' => current_time('mysql')
            ],
            ['wp_user_id' => $user_id]
        );
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
                
                // Update WordPress user data
                $wp_updates = [];
                if ($request->has_param('display_name')) {
                    $wp_updates['display_name'] = sanitize_text_field($request->get_param('display_name'));
                }
                if ($request->has_param('first_name')) {
                    $wp_updates['first_name'] = sanitize_text_field($request->get_param('first_name'));
                }
                if ($request->has_param('last_name')) {
                    $wp_updates['last_name'] = sanitize_text_field($request->get_param('last_name'));
                }
                if (!empty($wp_updates)) {
                    $wp_updates['ID'] = $user_id;
                    wp_update_user($wp_updates);
                }
                
                // Update agent table
                $agent_updates = [];
                $formats = [];
                
                if ($request->has_param('first_name')) {
                    $agent_updates['first_name'] = sanitize_text_field($request->get_param('first_name'));
                    $formats[] = '%s';
                }
                
                if ($request->has_param('last_name')) {
                    $agent_updates['last_name'] = sanitize_text_field($request->get_param('last_name'));
                    $formats[] = '%s';
                }
                
                if ($request->has_param('calendar_link')) {
                    $calendar_link = esc_url_raw($request->get_param('calendar_link'));
                    $agent_updates['calendar_link'] = $calendar_link;
                    $formats[] = '%s';
                    // Also save to user meta for profile page
                    update_user_meta($user_id, 'oversee_calendar_link', $calendar_link);
                }
                
                if ($request->has_param('zoom_link')) {
                    $zoom_link = esc_url_raw($request->get_param('zoom_link'));
                    $agent_updates['zoom_link'] = $zoom_link;
                    $formats[] = '%s';
                    // Also save to user meta for profile page
                    update_user_meta($user_id, 'oversee_zoom_link', $zoom_link);
                }
                
                if ($request->has_param('calendar_embed')) {
                    // Allow iframe embeds but sanitize
                    $calendar_embed = $request->get_param('calendar_embed');
                    // Only allow specific iframe patterns (Calendly, Cal.com, etc.)
                    if (!empty($calendar_embed)) {
                        $allowed_domains = ['calendly.com', 'cal.com', 'hubspot.com', 'acuityscheduling.com', 'tidycal.com', 'savvycal.com', 'link.overseecrm.com', 'overseecrm.com'];
                        $is_allowed = false;
                        foreach ($allowed_domains as $domain) {
                            if (strpos($calendar_embed, $domain) !== false) {
                                $is_allowed = true;
                                break;
                            }
                        }
                        if ($is_allowed) {
                            $agent_updates['calendar_embed'] = $calendar_embed;
                            $formats[] = '%s';
                            update_user_meta($user_id, 'oversee_calendar_embed', $calendar_embed);
                        }
                    } else {
                        $agent_updates['calendar_embed'] = '';
                        $formats[] = '%s';
                        delete_user_meta($user_id, 'oversee_calendar_embed');
                    }
                }
                
                if ($request->has_param('notification_preferences')) {
                    $agent_updates['notification_preferences'] = wp_json_encode($request->get_param('notification_preferences'));
                    $formats[] = '%s';
                }
                
                // Live Support visibility toggle
                if ($request->has_param('show_on_live_support')) {
                    $agent_updates['show_on_live_support'] = $request->get_param('show_on_live_support') ? 1 : 0;
                    $formats[] = '%d';
                }
                
                if (!empty($agent_updates)) {
                    $wpdb->update(
                        $wpdb->prefix . 'oversee_agents',
                        $agent_updates,
                        ['wp_user_id' => $user_id],
                        $formats,
                        ['%d']
                    );
                }
                
                return new WP_REST_Response(['message' => 'Profile updated', 'success' => true]);
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
                // Get unified settings
                $saved = get_option('oversee_settings', []);
                
                // Define defaults
                $defaults = [
                    // General
                    'portal_name' => 'Help Center',
                    'portal_title' => 'Help Center',
                    'support_email' => get_option('admin_email'),
                    'ticket_prefix' => 'TKT',
                    'default_priority' => 'low',
                    'primary_color' => '#f97316',
                    'secondary_color' => '#1e293b',
                    'logo_url' => '',
                    
                    // Business Hours
                    'business_hours_enabled' => false,
                    'business_start' => '09:00',
                    'business_end' => '17:00',
                    
                    // Assignment
                    'auto_assign' => false,
                    'assignment_method' => 'round_robin',
                    'assign_online_only' => true,
                    'max_tickets_per_agent' => 20,
                    
                    // KB
                    'enable_remote_kb' => true,
                    'kb_cache_duration' => HOUR_IN_SECONDS,
                    'static_kb_url' => '',
                    
                    // Ticket form
                    'require_phone' => false,
                    'show_priority' => false,
                    'allow_attachments' => true,
                    
                    // Email Notifications
                    'email_from_address' => get_option('admin_email'),
                    'email_from_name' => 'Support Team',
                    'email_new_ticket' => true,
                    'email_agent_reply' => true,
                    'email_resolved' => true,
                    
                    // Push
                    'push_enabled' => true,
                    
                    // Webhooks
                    'webhook_url' => '',
                    'webhook_events' => [],
                    'webhook_key' => '',
                    
                    // Security
                    'session_timeout' => DAY_IN_SECONDS,
                    'max_login_attempts' => 5,
                    'lockout_duration' => 900,
                ];
                
                // Merge saved with defaults
                $settings = array_merge($defaults, $saved);
                
                // Add computed values
                $settings['incoming_webhook_url'] = rest_url('oversee/v1/webhook/incoming');
                $settings['hl_secret'] = get_option('oversee_hl_secret', '');
                
                return new WP_REST_Response($settings);
            },
            'permission_callback' => [__CLASS__, 'admin_permission']
        ]);
        
        // Update settings
        register_rest_route('oversee/v1', '/settings', [
            'methods' => ['PUT', 'POST'],
            'callback' => function($request) {
                $input = $request->get_json_params();
                
                // Get existing settings
                $settings = get_option('oversee_settings', []);
                
                // Allowed setting keys
                $allowed = [
                    'portal_name', 'portal_title', 'support_email',
                    'ticket_prefix', 'default_priority',
                    'business_hours_enabled', 'business_start', 'business_end',
                    'auto_assign', 'assignment_method', 'assign_online_only', 'max_tickets_per_agent',
                    'email_new_ticket', 'email_agent_reply', 'email_resolved',
                    'session_timeout', 'webhook_key',
                    'primary_color', 'secondary_color', 'logo_url',
                    'enable_remote_kb', 'kb_cache_duration', 'static_kb_url',
                    'require_phone', 'show_priority', 'allow_attachments',
                    'email_from_address', 'email_from_name',
                    'push_enabled', 'webhook_url', 'webhook_events',
                    'max_login_attempts', 'lockout_duration'
                ];
                
                // Update settings with new values
                foreach ($input as $key => $value) {
                    if (in_array($key, $allowed)) {
                        $settings[$key] = $value;
                    }
                }
                
                // Save all settings
                update_option('oversee_settings', $settings);
                
                return new WP_REST_Response([
                    'message' => 'Settings updated',
                    'settings' => $settings
                ]);
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
        
        // Save HighLevel workflow settings
        register_rest_route('oversee/v1', '/settings/hl-workflow', [
            'methods' => 'POST',
            'callback' => function($request) {
                $url = esc_url_raw($request->get_param('url'));
                $events = $request->get_param('events');
                
                if (!is_array($events)) {
                    $events = [];
                }
                
                // Validate events
                $allowed_events = ['ticket_created', 'ticket_replied', 'ticket_resolved', 'customer_replied'];
                $events = array_intersect($events, $allowed_events);
                
                update_option('oversee_hl_workflow_url', $url);
                update_option('oversee_hl_workflow_events', $events);
                
                return new WP_REST_Response(['message' => 'HighLevel workflow settings saved']);
            },
            'permission_callback' => [__CLASS__, 'admin_permission']
        ]);
        
        // Save push notification preference per user
        register_rest_route('oversee/v1', '/settings/push-enabled', [
            'methods' => 'POST',
            'callback' => function($request) {
                $user_id = get_current_user_id();
                $enabled = (bool) $request->get_param('enabled');
                
                update_user_meta($user_id, 'oversee_push_enabled', $enabled);
                
                return new WP_REST_Response([
                    'message' => $enabled ? 'Push notifications enabled' : 'Push notifications disabled',
                    'enabled' => $enabled
                ]);
            },
            'permission_callback' => [__CLASS__, 'agent_permission']
        ]);
        
        // Save push notification preferences
        register_rest_route('oversee/v1', '/settings/push-preferences', [
            'methods' => 'POST',
            'callback' => function($request) {
                $user_id = get_current_user_id();
                
                $prefs = [
                    'new_ticket' => (bool) $request->get_param('new_ticket'),
                    'customer_reply' => (bool) $request->get_param('customer_reply'),
                    'assigned' => (bool) $request->get_param('assigned')
                ];
                
                update_user_meta($user_id, 'oversee_push_preferences', $prefs);
                
                return new WP_REST_Response([
                    'message' => 'Push preferences saved',
                    'preferences' => $prefs
                ]);
            },
            'permission_callback' => [__CLASS__, 'agent_permission']
        ]);
        
        // Get push notification preferences
        register_rest_route('oversee/v1', '/settings/push-preferences', [
            'methods' => 'GET',
            'callback' => function($request) {
                $user_id = get_current_user_id();
                $prefs = get_user_meta($user_id, 'oversee_push_preferences', true);
                
                if (empty($prefs)) {
                    $prefs = [
                        'new_ticket' => true,
                        'customer_reply' => true,
                        'assigned' => true
                    ];
                }
                
                return new WP_REST_Response($prefs);
            },
            'permission_callback' => [__CLASS__, 'agent_permission']
        ]);
        
        // Save push notification preferences
        register_rest_route('oversee/v1', '/settings/push-preferences', [
            'methods' => 'POST',
            'callback' => function($request) {
                $user_id = get_current_user_id();
                $prefs = [
                    'new_ticket' => (bool) $request->get_param('new_ticket'),
                    'customer_reply' => (bool) $request->get_param('customer_reply'),
                    'assigned' => (bool) $request->get_param('assigned')
                ];
                
                update_user_meta($user_id, 'oversee_push_preferences', $prefs);
                
                return new WP_REST_Response([
                    'message' => 'Preferences saved',
                    'preferences' => $prefs
                ]);
            },
            'permission_callback' => [__CLASS__, 'agent_permission']
        ]);
        
        // Test HighLevel workflow
        register_rest_route('oversee/v1', '/settings/hl-workflow/test', [
            'methods' => 'POST',
            'callback' => function($request) {
                $url = esc_url_raw($request->get_param('url'));
                
                if (empty($url)) {
                    return new WP_Error('missing_url', 'Workflow URL is required', ['status' => 400]);
                }
                
                // Send test payload
                $test_payload = [
                    'event' => 'test',
                    'ticket_id' => 0,
                    'ticket_number' => 'TEST-123',
                    'subject' => 'Test Webhook from Oversee Support',
                    'customer_name' => 'Test Customer',
                    'customer_email' => 'test@example.com',
                    'customer_phone' => '+1234567890',
                    'status' => 'new',
                    'priority' => 'low',
                    'assigned_agent' => 'Test Agent',
                    'created_at' => current_time('c'),
                    'ticket_url' => home_url('/support/admin/tickets?id=0'),
                    'message' => 'This is a test webhook to verify your HighLevel workflow integration is working correctly.'
                ];
                
                $response = wp_remote_post($url, [
                    'headers' => ['Content-Type' => 'application/json'],
                    'body' => json_encode($test_payload),
                    'timeout' => 15
                ]);
                
                if (is_wp_error($response)) {
                    return new WP_REST_Response([
                        'success' => false,
                        'message' => $response->get_error_message()
                    ]);
                }
                
                $code = wp_remote_retrieve_response_code($response);
                
                if ($code >= 200 && $code < 300) {
                    return new WP_REST_Response(['success' => true]);
                } else {
                    return new WP_REST_Response([
                        'success' => false,
                        'message' => 'Received HTTP ' . $code . ' response'
                    ]);
                }
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
        // Get VAPID public key
        register_rest_route('oversee/v1', '/push/vapid-key', [
            'methods' => 'GET',
            'callback' => function($request) {
                $keys = Oversee_Push::get_vapid_keys();
                
                if (is_wp_error($keys)) {
                    return new WP_REST_Response([
                        'publicKey' => null,
                        'error' => $keys->get_error_message()
                    ]);
                }
                
                return new WP_REST_Response(['publicKey' => $keys['public']]);
            },
            'permission_callback' => '__return_true'
        ]);
        
        // Get push notification status for current user
        register_rest_route('oversee/v1', '/push/status', [
            'methods' => 'GET',
            'callback' => function($request) {
                global $wpdb;
                
                $user_id = get_current_user_id();
                $enabled = get_option('oversee_push_enabled', true);
                
                $subscription = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}oversee_push_subscriptions WHERE user_id = %d LIMIT 1",
                    $user_id
                ));
                
                $keys = Oversee_Push::get_vapid_keys();
                $keys_available = !is_wp_error($keys);
                
                return new WP_REST_Response([
                    'enabled' => (bool) $enabled,
                    'subscribed' => !empty($subscription),
                    'keys_available' => $keys_available,
                    'public_key' => $keys_available ? $keys['public'] : null
                ]);
            },
            'permission_callback' => [__CLASS__, 'agent_permission']
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
     * Sessions routes
     */
    private static function register_sessions_routes() {
        // Get all active sessions
        register_rest_route('oversee/v1', '/sessions', [
            'methods' => 'GET',
            'callback' => function($request) {
                global $wpdb;
                
                $current_user_id = get_current_user_id();
                $current_session = wp_get_session_token();
                
                // Get sessions from WordPress user meta (wp_session_tokens)
                $sessions = [];
                
                // Get all agents
                $agents = $wpdb->get_results(
                    "SELECT a.*, u.display_name, u.user_email as email 
                     FROM {$wpdb->prefix}oversee_agents a 
                     LEFT JOIN {$wpdb->users} u ON a.wp_user_id = u.ID 
                     ORDER BY a.last_seen DESC"
                );
                
                foreach ($agents as $agent) {
                    // Get WordPress sessions for this user
                    $user_sessions = get_user_meta($agent->wp_user_id, 'session_tokens', true);
                    
                    if (!empty($user_sessions) && is_array($user_sessions)) {
                        foreach ($user_sessions as $token => $session_data) {
                            // Check if session is still valid
                            if (isset($session_data['expiration']) && $session_data['expiration'] > time()) {
                                $sessions[] = [
                                    'id' => substr(hash('sha256', $token), 0, 16),
                                    'user_id' => $agent->wp_user_id,
                                    'display_name' => $agent->display_name,
                                    'email' => $agent->email,
                                    'device' => self::parse_user_agent($session_data['ua'] ?? ''),
                                    'ip_address' => $session_data['ip'] ?? 'Unknown',
                                    'last_activity' => isset($session_data['login']) ? date('Y-m-d H:i:s', $session_data['login']) : null,
                                    'is_current' => ($agent->wp_user_id == $current_user_id && hash('sha256', $current_session) === hash('sha256', $token))
                                ];
                            }
                        }
                    }
                }
                
                // Sort by is_current first, then by last_activity
                usort($sessions, function($a, $b) {
                    if ($a['is_current']) return -1;
                    if ($b['is_current']) return 1;
                    return 0;
                });
                
                return new WP_REST_Response($sessions);
            },
            'permission_callback' => [__CLASS__, 'admin_permission']
        ]);
        
        // Revoke a session
        register_rest_route('oversee/v1', '/sessions/(?P<id>[a-f0-9]+)', [
            'methods' => 'DELETE',
            'callback' => function($request) {
                $session_id = $request->get_param('id');
                
                // Get all agents and find the session
                global $wpdb;
                $agents = $wpdb->get_col("SELECT wp_user_id FROM {$wpdb->prefix}oversee_agents");
                
                foreach ($agents as $user_id) {
                    $sessions = get_user_meta($user_id, 'session_tokens', true);
                    
                    if (!empty($sessions) && is_array($sessions)) {
                        foreach ($sessions as $token => $data) {
                            if (substr(hash('sha256', $token), 0, 16) === $session_id) {
                                // Remove this session
                                unset($sessions[$token]);
                                update_user_meta($user_id, 'session_tokens', $sessions);
                                
                                return new WP_REST_Response(['message' => 'Session revoked']);
                            }
                        }
                    }
                }
                
                return new WP_Error('not_found', 'Session not found', ['status' => 404]);
            },
            'permission_callback' => [__CLASS__, 'admin_permission']
        ]);
        
        // Revoke all sessions for a user (except current)
        register_rest_route('oversee/v1', '/sessions/revoke-all/(?P<user_id>\d+)', [
            'methods' => 'POST',
            'callback' => function($request) {
                $target_user_id = (int) $request->get_param('user_id');
                $current_user_id = get_current_user_id();
                $current_session = wp_get_session_token();
                
                $sessions = get_user_meta($target_user_id, 'session_tokens', true);
                
                if (empty($sessions) || !is_array($sessions)) {
                    return new WP_REST_Response(['message' => 'No sessions found', 'revoked' => 0]);
                }
                
                $revoked = 0;
                foreach ($sessions as $token => $data) {
                    // Don't revoke current session if it belongs to current user
                    if ($target_user_id == $current_user_id && $token === $current_session) {
                        continue;
                    }
                    unset($sessions[$token]);
                    $revoked++;
                }
                
                update_user_meta($target_user_id, 'session_tokens', $sessions);
                
                return new WP_REST_Response(['message' => 'Sessions revoked', 'revoked' => $revoked]);
            },
            'permission_callback' => [__CLASS__, 'admin_permission']
        ]);
    }
    
    /**
     * Parse user agent string to get device/browser info
     */
    private static function parse_user_agent($ua) {
        if (empty($ua)) return 'Unknown Device';
        
        $device = 'Desktop';
        $browser = 'Unknown';
        
        // Detect device
        if (preg_match('/Mobile|Android|iPhone|iPad/i', $ua)) {
            if (preg_match('/iPad/i', $ua)) {
                $device = 'iPad';
            } elseif (preg_match('/iPhone/i', $ua)) {
                $device = 'iPhone';
            } elseif (preg_match('/Android/i', $ua)) {
                $device = 'Android';
            } else {
                $device = 'Mobile';
            }
        } elseif (preg_match('/Macintosh/i', $ua)) {
            $device = 'Mac';
        } elseif (preg_match('/Windows/i', $ua)) {
            $device = 'Windows';
        } elseif (preg_match('/Linux/i', $ua)) {
            $device = 'Linux';
        }
        
        // Detect browser
        if (preg_match('/Chrome\/[\d.]+/i', $ua) && !preg_match('/Edge|Edg/i', $ua)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Firefox\/[\d.]+/i', $ua)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Safari\/[\d.]+/i', $ua) && !preg_match('/Chrome/i', $ua)) {
            $browser = 'Safari';
        } elseif (preg_match('/Edge|Edg/i', $ua)) {
            $browser = 'Edge';
        }
        
        return $device . ' • ' . $browser;
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
        $settings = get_option('oversee_settings', []);
        
        if (empty($settings['auto_assign'])) {
            return;
        }
        
        global $wpdb;
        
        $skip_offline = !empty($settings['assign_online_only']);
        $assignment_method = $settings['assignment_method'] ?? 'round_robin';
        $max_tickets = (int) ($settings['max_tickets_per_agent'] ?? 20);
        
        // Get available agents based on settings
        $where = "a.wp_user_id IS NOT NULL";
        if ($skip_offline) {
            $where .= " AND a.is_online = 1";
        }
        
        if ($assignment_method === 'least_busy') {
            // Assign to agent with fewest open tickets
            $query = "SELECT a.wp_user_id, 
                            (SELECT COUNT(*) FROM {$wpdb->prefix}oversee_tickets 
                             WHERE assigned_to = a.wp_user_id AND status IN ('new', 'open', 'pending')) as ticket_count
                      FROM {$wpdb->prefix}oversee_agents a
                      WHERE {$where}
                      HAVING ticket_count < {$max_tickets}
                      ORDER BY ticket_count ASC
                      LIMIT 1";
        } else if ($assignment_method === 'random') {
            // Random assignment
            $query = "SELECT a.wp_user_id,
                            (SELECT COUNT(*) FROM {$wpdb->prefix}oversee_tickets 
                             WHERE assigned_to = a.wp_user_id AND status IN ('new', 'open', 'pending')) as ticket_count
                      FROM {$wpdb->prefix}oversee_agents a
                      WHERE {$where}
                      HAVING ticket_count < {$max_tickets}
                      ORDER BY RAND()
                      LIMIT 1";
        } else {
            // Round robin - get agent with oldest last assignment
            $query = "SELECT a.wp_user_id,
                            (SELECT COUNT(*) FROM {$wpdb->prefix}oversee_tickets 
                             WHERE assigned_to = a.wp_user_id AND status IN ('new', 'open', 'pending')) as ticket_count
                      FROM {$wpdb->prefix}oversee_agents a
                      LEFT JOIN (
                        SELECT assigned_to, MAX(created_at) as last_assigned
                        FROM {$wpdb->prefix}oversee_tickets
                        WHERE assigned_to IS NOT NULL
                        GROUP BY assigned_to
                      ) t ON a.wp_user_id = t.assigned_to
                      WHERE {$where}
                      HAVING ticket_count < {$max_tickets}
                      ORDER BY t.last_assigned IS NULL DESC, t.last_assigned ASC
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
            
            Oversee_Logger::info('Auto-assigned ticket', [
                'ticket_id' => $ticket_id,
                'agent_id' => $agent_id,
                'method' => $assignment_method
            ]);
        }
    }
    
    /**
     * Permission callbacks
     */
    public static function agent_permission() {
        // Must have agent capability AND valid license
        if (!current_user_can('oversee_view_dashboard')) {
            return false;
        }
        return self::check_license_for_api();
    }
    
    public static function admin_permission() {
        // Must have admin capability AND valid license
        if (!current_user_can('oversee_manage_settings')) {
            return false;
        }
        return self::check_license_for_api();
    }
    
    public static function respond_permission() {
        // Must have respond capability AND valid license
        if (!current_user_can('oversee_respond_tickets')) {
            return false;
        }
        return self::check_license_for_api();
    }
    
    /**
     * Check license for API access
     * Returns true if licensed or in grace period
     * Returns WP_Error if unlicensed
     */
    private static function check_license_for_api() {
        // Check if licensed
        if (Oversee_License::is_licensed()) {
            return true;
        }
        
        // Check grace period
        $status = Oversee_License::get_status();
        if (!empty($status['in_grace_period']) || $status['status'] === 'grace_period') {
            return true;
        }
        
        // Not licensed - return error
        return new WP_Error(
            'license_required',
            'A valid license is required to access this endpoint.',
            ['status' => 403]
        );
    }
    
    /**
     * Trigger HighLevel workflow webhook for ticket events
     * 
     * @param string $event Event type: ticket_created, ticket_replied, ticket_resolved, customer_replied
     * @param array $ticket_data Ticket data array
     * @param string $message Optional message content for reply events
     */
    public static function trigger_hl_workflow($event, $ticket_data, $message = '') {
        $url = get_option('oversee_hl_workflow_url', '');
        $enabled_events = get_option('oversee_hl_workflow_events', []);
        
        // Check if URL is configured and event is enabled
        if (empty($url) || !in_array($event, $enabled_events)) {
            return;
        }
        
        // Build payload
        $payload = [
            'event' => $event,
            'ticket_id' => $ticket_data['id'] ?? 0,
            'ticket_number' => $ticket_data['ticket_number'] ?? '',
            'subject' => $ticket_data['subject'] ?? '',
            'customer_name' => $ticket_data['customer_name'] ?? '',
            'customer_email' => $ticket_data['customer_email'] ?? '',
            'customer_phone' => $ticket_data['customer_phone'] ?? '',
            'status' => $ticket_data['status'] ?? 'new',
            'priority' => $ticket_data['priority'] ?? 'low',
            'assigned_agent' => $ticket_data['assigned_agent'] ?? '',
            'created_at' => $ticket_data['created_at'] ?? current_time('c'),
            'updated_at' => current_time('c'),
            'ticket_url' => home_url('/support/admin/tickets?id=' . ($ticket_data['id'] ?? 0))
        ];
        
        // Add message for reply events
        if (!empty($message)) {
            // Strip HTML and limit length
            $payload['message'] = wp_trim_words(wp_strip_all_tags($message), 100);
        }
        
        // Add description for new tickets
        if ($event === 'ticket_created' && !empty($ticket_data['description'])) {
            $payload['description'] = wp_trim_words(wp_strip_all_tags($ticket_data['description']), 100);
        }
        
        // Send webhook asynchronously (non-blocking)
        wp_remote_post($url, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($payload),
            'timeout' => 5,
            'blocking' => false // Don't wait for response
        ]);
        
        // Log the webhook trigger
        Oversee_Logger::debug('HL workflow webhook triggered', [
            'event' => $event,
            'ticket_id' => $payload['ticket_id']
        ]);
    }
}
