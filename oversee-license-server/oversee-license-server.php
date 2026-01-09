<?php
/**
 * Plugin Name: Oversee License Server
 * Description: Sell and manage licenses for Oversee Helpdesk. Handles Stripe subscriptions and license validation.
 * Version: 1.0.0
 * Author: Oversee Agency
 * Author URI: https://overseeagency.com
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('OLS_VERSION', '1.0.0');
define('OLS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('OLS_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main License Server Class
 */
class Oversee_License_Server {
    
    private static $instance = null;
    
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        register_activation_hook(__FILE__, [$this, 'activate']);
        add_action('init', [$this, 'init']);
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('rest_api_init', [$this, 'register_api']);
    }
    
    /**
     * Plugin activation - create tables
     */
    public function activate() {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Licenses table
        $sql = "CREATE TABLE {$wpdb->prefix}oversee_licenses (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            license_key VARCHAR(255) NOT NULL,
            customer_email VARCHAR(255) NOT NULL,
            customer_name VARCHAR(255),
            stripe_customer_id VARCHAR(255),
            stripe_subscription_id VARCHAR(255),
            status ENUM('active', 'expired', 'cancelled', 'payment_failed') DEFAULT 'active',
            plan VARCHAR(50) DEFAULT 'monthly',
            site_url VARCHAR(500),
            site_name VARCHAR(255),
            activated_at DATETIME,
            expires_at DATETIME,
            last_verified_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY license_key (license_key),
            KEY customer_email (customer_email),
            KEY stripe_subscription_id (stripe_subscription_id),
            KEY status (status)
        ) $charset;";
        dbDelta($sql);
        
        // License log table
        $sql = "CREATE TABLE {$wpdb->prefix}oversee_license_log (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            license_id BIGINT UNSIGNED NOT NULL,
            event VARCHAR(50) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45),
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            KEY license_id (license_id)
        ) $charset;";
        dbDelta($sql);
        
        flush_rewrite_rules();
    }
    
    /**
     * Initialize
     */
    public function init() {
        // Nothing needed here for now
    }
    
    /**
     * Admin menu
     */
    public function admin_menu() {
        add_menu_page(
            'Oversee Licenses',
            'Oversee Licenses',
            'manage_options',
            'oversee-licenses',
            [$this, 'render_dashboard'],
            'dashicons-admin-network',
            30
        );
        
        add_submenu_page(
            'oversee-licenses',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'oversee-licenses',
            [$this, 'render_dashboard']
        );
        
        add_submenu_page(
            'oversee-licenses',
            'All Licenses',
            'All Licenses',
            'manage_options',
            'oversee-licenses-list',
            [$this, 'render_licenses']
        );
        
        add_submenu_page(
            'oversee-licenses',
            'Add License',
            'Add License',
            'manage_options',
            'oversee-licenses-add',
            [$this, 'render_add_license']
        );
        
        add_submenu_page(
            'oversee-licenses',
            'Settings',
            'Settings',
            'manage_options',
            'oversee-licenses-settings',
            [$this, 'render_settings']
        );
    }
    
    /**
     * Register REST API endpoints
     */
    public function register_api() {
        // Validate license endpoint (called by customer plugins)
        register_rest_route('oversee-license/v1', '/validate', [
            'methods' => 'POST',
            'callback' => [$this, 'api_validate_license'],
            'permission_callback' => '__return_true',
        ]);
        
        // Activate license endpoint
        register_rest_route('oversee-license/v1', '/activate', [
            'methods' => 'POST',
            'callback' => [$this, 'api_activate_license'],
            'permission_callback' => '__return_true',
        ]);
        
        // Deactivate license endpoint
        register_rest_route('oversee-license/v1', '/deactivate', [
            'methods' => 'POST',
            'callback' => [$this, 'api_deactivate_license'],
            'permission_callback' => '__return_true',
        ]);
        
        // Stripe webhook
        register_rest_route('oversee-license/v1', '/webhook/stripe', [
            'methods' => 'POST',
            'callback' => [$this, 'api_stripe_webhook'],
            'permission_callback' => '__return_true',
        ]);
    }
    
    /**
     * Generate a license key
     */
    public static function generate_license_key() {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $segments = [];
        for ($i = 0; $i < 4; $i++) {
            $segment = '';
            for ($j = 0; $j < 4; $j++) {
                $segment .= $chars[random_int(0, strlen($chars) - 1)];
            }
            $segments[] = $segment;
        }
        return 'OVS-' . implode('-', $segments);
    }
    
    /**
     * Log license event
     */
    private function log_event($license_id, $event, $details = '') {
        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'oversee_license_log',
            [
                'license_id' => $license_id,
                'event' => $event,
                'details' => $details,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'created_at' => current_time('mysql'),
            ]
        );
    }
    
    /**
     * API: Validate license
     * STRICT: License must be activated AND requesting site must match
     */
    public function api_validate_license($request) {
        $license_key = sanitize_text_field($request->get_param('license_key'));
        $site_url = esc_url_raw($request->get_param('site_url'));
        
        if (empty($license_key)) {
            return new WP_REST_Response([
                'valid' => false,
                'error' => 'missing_license_key',
                'message' => 'License key is required.',
            ], 400);
        }
        
        if (empty($site_url)) {
            return new WP_REST_Response([
                'valid' => false,
                'error' => 'missing_site_url',
                'message' => 'Site URL is required.',
            ], 400);
        }
        
        global $wpdb;
        $license = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}oversee_licenses WHERE license_key = %s",
            $license_key
        ));
        
        if (!$license) {
            return new WP_REST_Response([
                'valid' => false,
                'error' => 'invalid_license',
                'message' => 'License key not found.',
            ], 404);
        }
        
        // Check if license is active
        if ($license->status !== 'active') {
            $this->log_event($license->id, 'validation_failed', "Status: {$license->status}");
            
            $messages = [
                'expired' => 'Your license has expired. Please renew your subscription at OverseeAgency.com',
                'cancelled' => 'Your subscription has been cancelled. Please resubscribe at OverseeAgency.com',
                'payment_failed' => 'Payment failed. Please update your payment method at OverseeAgency.com',
            ];
            
            return new WP_REST_Response([
                'valid' => false,
                'error' => 'license_' . $license->status,
                'message' => $messages[$license->status] ?? 'License is not active.',
                'status' => $license->status,
                'renew_url' => home_url('/pricing'),
            ], 403);
        }
        
        // Check expiration date
        if ($license->expires_at && strtotime($license->expires_at) < time()) {
            $wpdb->update(
                $wpdb->prefix . 'oversee_licenses',
                ['status' => 'expired'],
                ['id' => $license->id]
            );
            $this->log_event($license->id, 'expired', 'License expired');
            
            return new WP_REST_Response([
                'valid' => false,
                'error' => 'license_expired',
                'message' => 'Your license has expired. Please renew your subscription.',
                'status' => 'expired',
                'renew_url' => home_url('/pricing'),
            ], 403);
        }
        
        // STRICT: License MUST be activated on a site first
        if (empty($license->site_url)) {
            $this->log_event($license->id, 'validation_failed', 'License not activated on any site');
            return new WP_REST_Response([
                'valid' => false,
                'error' => 'not_activated',
                'message' => 'This license has not been activated. Please activate it first.',
            ], 403);
        }
        
        // STRICT: Site must match exactly (normalize www)
        $registered_host = strtolower(preg_replace('/^www\./', '', parse_url($license->site_url, PHP_URL_HOST)));
        $requesting_host = strtolower(preg_replace('/^www\./', '', parse_url($site_url, PHP_URL_HOST)));
        
        if ($registered_host !== $requesting_host) {
            $this->log_event($license->id, 'site_mismatch', "Registered: {$registered_host}, Requested: {$requesting_host}");
            
            return new WP_REST_Response([
                'valid' => false,
                'error' => 'site_mismatch',
                'message' => "This license is active on {$registered_host}. Deactivate it there first to use on this site.",
                'registered_site' => $registered_host,
            ], 403);
        }
        
        // Update last verified time
        $wpdb->update(
            $wpdb->prefix . 'oversee_licenses',
            ['last_verified_at' => current_time('mysql')],
            ['id' => $license->id]
        );
        
        $this->log_event($license->id, 'validated', $site_url);
        
        return new WP_REST_Response([
            'valid' => true,
            'status' => 'active',
            'plan' => $license->plan,
            'expires_at' => $license->expires_at,
            'customer_email' => $license->customer_email,
            'message' => 'License is valid.',
        ], 200);
    }
    
    /**
     * API: Activate license on a site
     */
    public function api_activate_license($request) {
        $license_key = sanitize_text_field($request->get_param('license_key'));
        $site_url = esc_url_raw($request->get_param('site_url'));
        $site_name = sanitize_text_field($request->get_param('site_name'));
        
        if (empty($license_key) || empty($site_url)) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'missing_params',
                'message' => 'License key and site URL are required.',
            ], 400);
        }
        
        global $wpdb;
        $license = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}oversee_licenses WHERE license_key = %s",
            $license_key
        ));
        
        if (!$license) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'invalid_license',
                'message' => 'License key not found.',
            ], 404);
        }
        
        if ($license->status !== 'active') {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'license_inactive',
                'message' => 'License is not active. Status: ' . $license->status,
            ], 403);
        }
        
        // Check if already activated on another site
        if ($license->site_url && !empty($license->site_url)) {
            $registered_host = parse_url($license->site_url, PHP_URL_HOST);
            $requesting_host = parse_url($site_url, PHP_URL_HOST);
            
            if ($registered_host !== $requesting_host) {
                return new WP_REST_Response([
                    'success' => false,
                    'error' => 'already_activated',
                    'message' => 'This license is already activated on another website: ' . $registered_host,
                    'registered_site' => $registered_host,
                ], 403);
            }
        }
        
        // Activate the license
        $wpdb->update(
            $wpdb->prefix . 'oversee_licenses',
            [
                'site_url' => $site_url,
                'site_name' => $site_name,
                'activated_at' => current_time('mysql'),
                'last_verified_at' => current_time('mysql'),
            ],
            ['id' => $license->id]
        );
        
        $this->log_event($license->id, 'activated', "Site: {$site_url}");
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'License activated successfully.',
            'plan' => $license->plan,
            'expires_at' => $license->expires_at,
        ], 200);
    }
    
    /**
     * API: Deactivate license
     */
    public function api_deactivate_license($request) {
        $license_key = sanitize_text_field($request->get_param('license_key'));
        $site_url = esc_url_raw($request->get_param('site_url'));
        
        if (empty($license_key)) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'missing_license_key',
                'message' => 'License key is required.',
            ], 400);
        }
        
        global $wpdb;
        $license = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}oversee_licenses WHERE license_key = %s",
            $license_key
        ));
        
        if (!$license) {
            return new WP_REST_Response([
                'success' => false,
                'error' => 'invalid_license',
                'message' => 'License key not found.',
            ], 404);
        }
        
        // Clear the site URL
        $wpdb->update(
            $wpdb->prefix . 'oversee_licenses',
            [
                'site_url' => null,
                'site_name' => null,
            ],
            ['id' => $license->id]
        );
        
        $this->log_event($license->id, 'deactivated', "Site: {$site_url}");
        
        return new WP_REST_Response([
            'success' => true,
            'message' => 'License deactivated. You can now activate it on another site.',
        ], 200);
    }
    
    /**
     * API: Stripe webhook handler
     */
    public function api_stripe_webhook($request) {
        $payload = $request->get_body();
        $sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $webhook_secret = get_option('oversee_stripe_webhook_secret', '');
        
        // Verify webhook signature if secret is set
        if (!empty($webhook_secret)) {
            $elements = explode(',', $sig_header);
            $timestamp = null;
            $signature = null;
            
            foreach ($elements as $element) {
                $parts = explode('=', $element, 2);
                if (count($parts) === 2) {
                    if ($parts[0] === 't') $timestamp = $parts[1];
                    if ($parts[0] === 'v1') $signature = $parts[1];
                }
            }
            
            if ($timestamp && $signature) {
                $signed_payload = "{$timestamp}.{$payload}";
                $expected = hash_hmac('sha256', $signed_payload, $webhook_secret);
                
                if (!hash_equals($expected, $signature)) {
                    return new WP_REST_Response(['error' => 'Invalid signature'], 400);
                }
                
                // Check timestamp (reject if older than 5 minutes)
                if (abs(time() - intval($timestamp)) > 300) {
                    return new WP_REST_Response(['error' => 'Timestamp too old'], 400);
                }
            }
        }
        
        $event = json_decode($payload, true);
        
        if (!$event || !isset($event['type'])) {
            return new WP_REST_Response(['error' => 'Invalid payload'], 400);
        }
        
        global $wpdb;
        $table = $wpdb->prefix . 'oversee_licenses';
        
        switch ($event['type']) {
            case 'checkout.session.completed':
                // New subscription created via checkout
                $session = $event['data']['object'];
                $customer_email = $session['customer_email'] ?? $session['customer_details']['email'] ?? '';
                $customer_name = $session['customer_details']['name'] ?? '';
                $subscription_id = $session['subscription'] ?? '';
                $customer_id = $session['customer'] ?? '';
                
                if ($customer_email && $subscription_id) {
                    // Generate new license
                    $license_key = self::generate_license_key();
                    
                    // Calculate expiration (1 month from now for monthly)
                    $expires_at = date('Y-m-d H:i:s', strtotime('+1 month'));
                    
                    $wpdb->insert($table, [
                        'license_key' => $license_key,
                        'customer_email' => $customer_email,
                        'customer_name' => $customer_name,
                        'stripe_customer_id' => $customer_id,
                        'stripe_subscription_id' => $subscription_id,
                        'status' => 'active',
                        'plan' => 'monthly',
                        'expires_at' => $expires_at,
                        'created_at' => current_time('mysql'),
                    ]);
                    
                    $license_id = $wpdb->insert_id;
                    $this->log_event($license_id, 'created', "Stripe checkout: {$subscription_id}");
                    
                    // Send license key email
                    $this->send_license_email($customer_email, $customer_name, $license_key);
                }
                break;
                
            case 'invoice.paid':
                // Subscription renewed
                $invoice = $event['data']['object'];
                $subscription_id = $invoice['subscription'] ?? '';
                
                if ($subscription_id) {
                    $license = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$table} WHERE stripe_subscription_id = %s",
                        $subscription_id
                    ));
                    
                    if ($license) {
                        // Extend expiration by 1 month
                        $new_expires = date('Y-m-d H:i:s', strtotime('+1 month'));
                        
                        $wpdb->update($table,
                            [
                                'status' => 'active',
                                'expires_at' => $new_expires,
                            ],
                            ['id' => $license->id]
                        );
                        
                        $this->log_event($license->id, 'renewed', "Extended to: {$new_expires}");
                    }
                }
                break;
                
            case 'invoice.payment_failed':
                // Payment failed - disable license
                $invoice = $event['data']['object'];
                $subscription_id = $invoice['subscription'] ?? '';
                
                if ($subscription_id) {
                    $license = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$table} WHERE stripe_subscription_id = %s",
                        $subscription_id
                    ));
                    
                    if ($license) {
                        $wpdb->update($table,
                            ['status' => 'payment_failed'],
                            ['id' => $license->id]
                        );
                        
                        $this->log_event($license->id, 'payment_failed', 'Invoice payment failed');
                        
                        // Notify customer
                        $this->send_payment_failed_email($license->customer_email, $license->customer_name);
                    }
                }
                break;
                
            case 'customer.subscription.deleted':
                // Subscription cancelled
                $subscription = $event['data']['object'];
                $subscription_id = $subscription['id'] ?? '';
                
                if ($subscription_id) {
                    $license = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$table} WHERE stripe_subscription_id = %s",
                        $subscription_id
                    ));
                    
                    if ($license) {
                        $wpdb->update($table,
                            ['status' => 'cancelled'],
                            ['id' => $license->id]
                        );
                        
                        $this->log_event($license->id, 'cancelled', 'Subscription deleted');
                    }
                }
                break;
                
            case 'customer.subscription.updated':
                // Check for cancellation schedule
                $subscription = $event['data']['object'];
                $subscription_id = $subscription['id'] ?? '';
                
                if ($subscription_id && !empty($subscription['cancel_at_period_end'])) {
                    $license = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$table} WHERE stripe_subscription_id = %s",
                        $subscription_id
                    ));
                    
                    if ($license) {
                        $this->log_event($license->id, 'cancellation_scheduled', 'Will cancel at period end');
                    }
                }
                break;
        }
        
        return new WP_REST_Response(['received' => true], 200);
    }
    
    /**
     * Send license key email
     */
    private function send_license_email($email, $name, $license_key) {
        $subject = 'Your Oversee Helpdesk License Key';
        
        $message = "Hi {$name},\n\n";
        $message .= "Thank you for purchasing Oversee Helpdesk!\n\n";
        $message .= "Your license key is:\n\n";
        $message .= "{$license_key}\n\n";
        $message .= "To activate:\n";
        $message .= "1. Install the Oversee Helpdesk plugin on your WordPress site\n";
        $message .= "2. Go to Oversee Helpdesk → License in your WordPress admin\n";
        $message .= "3. Enter the license key above\n";
        $message .= "4. Click Activate\n\n";
        $message .= "Need help? Contact us at support@overseeagency.com\n\n";
        $message .= "Thanks,\nThe Oversee Agency Team";
        
        wp_mail($email, $subject, $message);
    }
    
    /**
     * Send payment failed email
     */
    private function send_payment_failed_email($email, $name) {
        $subject = 'Action Required: Payment Failed for Oversee Helpdesk';
        
        $message = "Hi {$name},\n\n";
        $message .= "We were unable to process your payment for Oversee Helpdesk.\n\n";
        $message .= "Your license has been temporarily disabled. Your helpdesk will not function until payment is resolved.\n\n";
        $message .= "Please update your payment method here:\n";
        $message .= home_url('/account') . "\n\n";
        $message .= "If you have questions, contact us at support@overseeagency.com\n\n";
        $message .= "Thanks,\nThe Oversee Agency Team";
        
        wp_mail($email, $subject, $message);
    }
    
    /**
     * Render dashboard
     */
    public function render_dashboard() {
        global $wpdb;
        $table = $wpdb->prefix . 'oversee_licenses';
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $active = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'active'");
        $expired = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'expired'");
        $failed = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'payment_failed'");
        $cancelled = $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'cancelled'");
        
        $recent = $wpdb->get_results("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT 5");
        ?>
        <div class="wrap">
            <h1>📊 License Server Dashboard</h1>
            
            <div style="display: grid; grid-template-columns: repeat(5, 1fr); gap: 15px; margin: 20px 0;">
                <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center;">
                    <div style="font-size: 36px; font-weight: bold; color: #333;"><?php echo esc_html($total); ?></div>
                    <div style="color: #666;">Total Licenses</div>
                </div>
                <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center;">
                    <div style="font-size: 36px; font-weight: bold; color: #22c55e;"><?php echo esc_html($active); ?></div>
                    <div style="color: #666;">Active</div>
                </div>
                <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center;">
                    <div style="font-size: 36px; font-weight: bold; color: #f59e0b;"><?php echo esc_html($expired); ?></div>
                    <div style="color: #666;">Expired</div>
                </div>
                <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center;">
                    <div style="font-size: 36px; font-weight: bold; color: #ef4444;"><?php echo esc_html($failed); ?></div>
                    <div style="color: #666;">Payment Failed</div>
                </div>
                <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center;">
                    <div style="font-size: 36px; font-weight: bold; color: #6b7280;"><?php echo esc_html($cancelled); ?></div>
                    <div style="color: #666;">Cancelled</div>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">
                <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0;">Recent Licenses</h2>
                    <?php if ($recent): ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>License Key</th>
                                <th>Customer</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent as $license): ?>
                            <tr>
                                <td><code><?php echo esc_html($license->license_key); ?></code></td>
                                <td><?php echo esc_html($license->customer_email); ?></td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'active' => '#22c55e',
                                        'expired' => '#f59e0b',
                                        'payment_failed' => '#ef4444',
                                        'cancelled' => '#6b7280',
                                    ];
                                    $color = $status_colors[$license->status] ?? '#6b7280';
                                    ?>
                                    <span style="color: <?php echo $color; ?>; font-weight: bold;">
                                        <?php echo esc_html(ucfirst(str_replace('_', ' ', $license->status))); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <p>No licenses yet.</p>
                    <?php endif; ?>
                </div>
                
                <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h2 style="margin-top: 0;">Quick Setup</h2>
                    <ol>
                        <li>
                            <strong>Configure Stripe</strong><br>
                            <a href="<?php echo admin_url('admin.php?page=oversee-licenses-settings'); ?>">Go to Settings →</a>
                        </li>
                        <li style="margin-top: 10px;">
                            <strong>Set up Stripe Webhook</strong><br>
                            URL: <code><?php echo esc_url(rest_url('oversee-license/v1/webhook/stripe')); ?></code><br>
                            <small>Events: checkout.session.completed, invoice.paid, invoice.payment_failed, customer.subscription.deleted</small>
                        </li>
                        <li style="margin-top: 10px;">
                            <strong>Create a Stripe Product</strong><br>
                            <a href="https://dashboard.stripe.com/products" target="_blank">Go to Stripe Products →</a>
                        </li>
                        <li style="margin-top: 10px;">
                            <strong>Add Buy Button to your site</strong><br>
                            Use Stripe's checkout link or payment links
                        </li>
                    </ol>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render licenses list
     */
    public function render_licenses() {
        global $wpdb;
        $table = $wpdb->prefix . 'oversee_licenses';
        
        // Handle status change
        if (isset($_GET['action']) && isset($_GET['license_id']) && isset($_GET['_wpnonce'])) {
            if (wp_verify_nonce($_GET['_wpnonce'], 'license_action')) {
                $license_id = intval($_GET['license_id']);
                $action = sanitize_text_field($_GET['action']);
                
                if ($action === 'activate') {
                    $wpdb->update($table, ['status' => 'active'], ['id' => $license_id]);
                } elseif ($action === 'deactivate') {
                    $wpdb->update($table, ['status' => 'expired'], ['id' => $license_id]);
                } elseif ($action === 'delete') {
                    $wpdb->delete($table, ['id' => $license_id]);
                }
                
                echo '<div class="notice notice-success"><p>License updated.</p></div>';
            }
        }
        
        // Get filter
        $status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        
        $where = '';
        if ($status_filter) {
            $where = $wpdb->prepare(" WHERE status = %s", $status_filter);
        }
        
        $licenses = $wpdb->get_results("SELECT * FROM {$table} {$where} ORDER BY created_at DESC");
        ?>
        <div class="wrap">
            <h1>All Licenses</h1>
            
            <div style="margin: 15px 0;">
                <a href="<?php echo admin_url('admin.php?page=oversee-licenses-list'); ?>" class="button <?php echo !$status_filter ? 'button-primary' : ''; ?>">All</a>
                <a href="<?php echo admin_url('admin.php?page=oversee-licenses-list&status=active'); ?>" class="button <?php echo $status_filter === 'active' ? 'button-primary' : ''; ?>">Active</a>
                <a href="<?php echo admin_url('admin.php?page=oversee-licenses-list&status=expired'); ?>" class="button <?php echo $status_filter === 'expired' ? 'button-primary' : ''; ?>">Expired</a>
                <a href="<?php echo admin_url('admin.php?page=oversee-licenses-list&status=payment_failed'); ?>" class="button <?php echo $status_filter === 'payment_failed' ? 'button-primary' : ''; ?>">Payment Failed</a>
                <a href="<?php echo admin_url('admin.php?page=oversee-licenses-list&status=cancelled'); ?>" class="button <?php echo $status_filter === 'cancelled' ? 'button-primary' : ''; ?>">Cancelled</a>
            </div>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>License Key</th>
                        <th>Customer</th>
                        <th>Site</th>
                        <th>Status</th>
                        <th>Expires</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($licenses): ?>
                        <?php foreach ($licenses as $license): ?>
                        <tr>
                            <td><code><?php echo esc_html($license->license_key); ?></code></td>
                            <td>
                                <?php echo esc_html($license->customer_name ?: '-'); ?><br>
                                <small><?php echo esc_html($license->customer_email); ?></small>
                            </td>
                            <td>
                                <?php if ($license->site_url): ?>
                                    <a href="<?php echo esc_url($license->site_url); ?>" target="_blank">
                                        <?php echo esc_html(parse_url($license->site_url, PHP_URL_HOST)); ?>
                                    </a>
                                <?php else: ?>
                                    <em>Not activated</em>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $status_colors = [
                                    'active' => '#22c55e',
                                    'expired' => '#f59e0b',
                                    'payment_failed' => '#ef4444',
                                    'cancelled' => '#6b7280',
                                ];
                                $color = $status_colors[$license->status] ?? '#6b7280';
                                ?>
                                <span style="color: <?php echo $color; ?>; font-weight: bold;">
                                    ● <?php echo esc_html(ucfirst(str_replace('_', ' ', $license->status))); ?>
                                </span>
                            </td>
                            <td>
                                <?php echo $license->expires_at ? esc_html(date('M j, Y', strtotime($license->expires_at))) : '-'; ?>
                            </td>
                            <td>
                                <?php echo esc_html(date('M j, Y', strtotime($license->created_at))); ?>
                            </td>
                            <td>
                                <?php if ($license->status !== 'active'): ?>
                                    <a href="<?php echo wp_nonce_url(admin_url("admin.php?page=oversee-licenses-list&action=activate&license_id={$license->id}"), 'license_action'); ?>" class="button button-small">Activate</a>
                                <?php else: ?>
                                    <a href="<?php echo wp_nonce_url(admin_url("admin.php?page=oversee-licenses-list&action=deactivate&license_id={$license->id}"), 'license_action'); ?>" class="button button-small">Deactivate</a>
                                <?php endif; ?>
                                <a href="<?php echo wp_nonce_url(admin_url("admin.php?page=oversee-licenses-list&action=delete&license_id={$license->id}"), 'license_action'); ?>" class="button button-small" style="color: #ef4444;" onclick="return confirm('Delete this license?');">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">No licenses found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    /**
     * Render add license form
     */
    public function render_add_license() {
        global $wpdb;
        
        // Handle form submission
        if (isset($_POST['add_license_nonce']) && wp_verify_nonce($_POST['add_license_nonce'], 'add_license')) {
            $email = sanitize_email($_POST['customer_email']);
            $name = sanitize_text_field($_POST['customer_name']);
            $plan = sanitize_text_field($_POST['plan']);
            $months = intval($_POST['months']) ?: 1;
            
            if ($email) {
                $license_key = self::generate_license_key();
                $expires_at = date('Y-m-d H:i:s', strtotime("+{$months} months"));
                
                $wpdb->insert($wpdb->prefix . 'oversee_licenses', [
                    'license_key' => $license_key,
                    'customer_email' => $email,
                    'customer_name' => $name,
                    'status' => 'active',
                    'plan' => $plan,
                    'expires_at' => $expires_at,
                    'created_at' => current_time('mysql'),
                ]);
                
                echo '<div class="notice notice-success"><p>License created: <strong>' . esc_html($license_key) . '</strong></p></div>';
                
                // Optionally send email
                if (!empty($_POST['send_email'])) {
                    $this->send_license_email($email, $name, $license_key);
                    echo '<div class="notice notice-info"><p>Email sent to customer.</p></div>';
                }
            }
        }
        ?>
        <div class="wrap">
            <h1>Add New License</h1>
            
            <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); max-width: 600px;">
                <form method="post">
                    <?php wp_nonce_field('add_license', 'add_license_nonce'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><label for="customer_email">Customer Email *</label></th>
                            <td><input type="email" name="customer_email" id="customer_email" class="regular-text" required></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="customer_name">Customer Name</label></th>
                            <td><input type="text" name="customer_name" id="customer_name" class="regular-text"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="plan">Plan</label></th>
                            <td>
                                <select name="plan" id="plan">
                                    <option value="monthly">Monthly</option>
                                    <option value="yearly">Yearly</option>
                                    <option value="lifetime">Lifetime</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="months">Valid For</label></th>
                            <td>
                                <input type="number" name="months" id="months" value="1" min="1" max="120" style="width: 80px;"> months
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Send Email</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="send_email" value="1" checked>
                                    Send license key to customer via email
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button('Create License'); ?>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings page
     */
    public function render_settings() {
        // Handle form submission
        if (isset($_POST['settings_nonce']) && wp_verify_nonce($_POST['settings_nonce'], 'save_settings')) {
            update_option('oversee_stripe_secret_key', sanitize_text_field($_POST['stripe_secret_key']));
            update_option('oversee_stripe_publishable_key', sanitize_text_field($_POST['stripe_publishable_key']));
            update_option('oversee_stripe_webhook_secret', sanitize_text_field($_POST['stripe_webhook_secret']));
            echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
        }
        
        $stripe_secret = get_option('oversee_stripe_secret_key', '');
        $stripe_publishable = get_option('oversee_stripe_publishable_key', '');
        $stripe_webhook = get_option('oversee_stripe_webhook_secret', '');
        ?>
        <div class="wrap">
            <h1>License Server Settings</h1>
            
            <form method="post">
                <?php wp_nonce_field('save_settings', 'settings_nonce'); ?>
                
                <div style="background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); max-width: 800px; margin-bottom: 20px;">
                    <h2 style="margin-top: 0;">💳 Stripe Configuration</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Publishable Key</th>
                            <td>
                                <input type="text" name="stripe_publishable_key" value="<?php echo esc_attr($stripe_publishable); ?>" class="regular-text" placeholder="pk_live_...">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Secret Key</th>
                            <td>
                                <input type="password" name="stripe_secret_key" value="<?php echo esc_attr($stripe_secret); ?>" class="regular-text" placeholder="sk_live_...">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">Webhook Secret</th>
                            <td>
                                <input type="password" name="stripe_webhook_secret" value="<?php echo esc_attr($stripe_webhook); ?>" class="regular-text" placeholder="whsec_...">
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div style="background: #f0f9ff; padding: 20px; border-radius: 8px; border: 1px solid #bae6fd; max-width: 800px;">
                    <h3 style="margin-top: 0;">🔗 Webhook URL</h3>
                    <p>Add this webhook URL to your Stripe Dashboard:</p>
                    <code style="background: #fff; padding: 10px; display: block; margin: 10px 0; border-radius: 4px;">
                        <?php echo esc_url(rest_url('oversee-license/v1/webhook/stripe')); ?>
                    </code>
                    <p><strong>Events to enable:</strong></p>
                    <ul style="margin-left: 20px;">
                        <li>checkout.session.completed</li>
                        <li>invoice.paid</li>
                        <li>invoice.payment_failed</li>
                        <li>customer.subscription.deleted</li>
                        <li>customer.subscription.updated</li>
                    </ul>
                </div>
                
                <?php submit_button('Save Settings'); ?>
            </form>
        </div>
        <?php
    }
}

// Initialize
Oversee_License_Server::instance();

/**
 * Plugin Update Server
 * Handles update checks for Oversee plugins
 */
class Oversee_Update_Server {
    
    private static $plugins = [
        'oversee-helpdesk' => [
            'version' => '2.1.1',
            'slug' => 'oversee-helpdesk',
            'name' => 'Oversee Helpdesk',
            'author' => 'Oversee Agency',
            'requires' => '5.8',
            'tested' => '6.4',
            'requires_php' => '7.4',
            'download_url' => 'https://overseeagency.com/plugins/oversee-helpdesk.zip',
            'changelog' => 'https://overseeagency.com/changelog/oversee-helpdesk/',
            'banner_low' => 'https://overseeagency.com/assets/banner-772x250.png',
            'banner_high' => 'https://overseeagency.com/assets/banner-1544x500.png',
        ]
    ];
    
    public static function init() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }
    
    public static function register_routes() {
        register_rest_route('oversee/v1', '/update-check', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'check_update'],
            'permission_callback' => '__return_true'
        ]);
        
        register_rest_route('oversee/v1', '/plugin-info', [
            'methods' => 'POST', 
            'callback' => [__CLASS__, 'plugin_info'],
            'permission_callback' => '__return_true'
        ]);
    }
    
    public static function check_update($request) {
        $slug = sanitize_text_field($request->get_param('slug'));
        $current_version = sanitize_text_field($request->get_param('version'));
        
        if (!isset(self::$plugins[$slug])) {
            return new WP_Error('not_found', 'Plugin not found', ['status' => 404]);
        }
        
        $plugin = self::$plugins[$slug];
        
        if (version_compare($current_version, $plugin['version'], '<')) {
            return rest_ensure_response([
                'update_available' => true,
                'version' => $plugin['version'],
                'download_url' => $plugin['download_url'],
                'changelog' => $plugin['changelog']
            ]);
        }
        
        return rest_ensure_response(['update_available' => false]);
    }
    
    public static function plugin_info($request) {
        $slug = sanitize_text_field($request->get_param('slug'));
        
        if (!isset(self::$plugins[$slug])) {
            return new WP_Error('not_found', 'Plugin not found', ['status' => 404]);
        }
        
        $plugin = self::$plugins[$slug];
        
        return rest_ensure_response([
            'name' => $plugin['name'],
            'slug' => $plugin['slug'],
            'version' => $plugin['version'],
            'author' => $plugin['author'],
            'requires' => $plugin['requires'],
            'tested' => $plugin['tested'],
            'requires_php' => $plugin['requires_php'],
            'download_link' => $plugin['download_url'],
            'sections' => [
                'changelog' => '<p>Visit <a href="' . $plugin['changelog'] . '">changelog</a></p>'
            ],
            'banners' => [
                'low' => $plugin['banner_low'],
                'high' => $plugin['banner_high']
            ]
        ]);
    }
    
    public static function update_plugin_version($slug, $version) {
        if (isset(self::$plugins[$slug])) {
            self::$plugins[$slug]['version'] = $version;
        }
    }
}

Oversee_Update_Server::init();

/**
 * Plugin Update Server
 */
add_action('rest_api_init', function() {
    register_rest_route('oversee/v1', '/update-check', [
        'methods' => 'POST',
        'callback' => function($request) {
            $slug = sanitize_text_field($request->get_param('slug'));
            $version = sanitize_text_field($request->get_param('version'));
            
            if ($slug === 'oversee-helpdesk' && version_compare($version, '2.1.2', '<')) {
                return ['update_available' => true, 'version' => '2.1.2', 'download_url' => 'https://overseeagency.com/plugins/oversee-helpdesk.zip', 'changelog' => 'https://overseeagency.com/changelog/'];
            }
            
            return ['update_available' => false];
        },
        'permission_callback' => '__return_true'
    ]);
    
    register_rest_route('oversee/v1', '/plugin-info', [
        'methods' => 'POST',
        'callback' => function($request) {
            return [
                'name' => 'Oversee Helpdesk',
                'slug' => 'oversee-helpdesk',
                'version' => '2.1.2',
                'author' => 'Oversee Agency',
                'requires' => '5.8',
                'tested' => '6.4',
                'requires_php' => '7.4',
                'download_link' => 'https://overseeagency.com/plugins/oversee-helpdesk.zip',
                'sections' => ['changelog' => '<p>Version 2.1.2 - Bug fixes</p>']
            ];
        },
        'permission_callback' => '__return_true'
    ]);
});
