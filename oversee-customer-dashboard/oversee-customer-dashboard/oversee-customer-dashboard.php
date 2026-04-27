<?php
/**
 * Plugin Name: Oversee Customer Dashboard
 * Plugin URI: https://overseeagency.com/plugins/oversee-customer-dashboard
 * Description: Customer-facing dashboard and Oversee Agency admin portal. Integrates HighLevel/LeadConnector CRM and WooCommerce Subscriptions to surface contacts, opportunities, orders, subscriptions and ticket links in one place.
 * Version: 1.2.0
 * Author: Oversee Agency
 * Author URI: https://overseeagency.com
 * License: GPL v2 or later
 * Text Domain: oversee-customer-dashboard
 * Requires at least: 5.8
 * Tested up to: 6.7
 * Requires PHP: 7.4
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

define('OCD_VERSION', '1.2.0');
define('OCD_FILE', __FILE__);
define('OCD_DIR', plugin_dir_path(__FILE__));
define('OCD_URL', plugin_dir_url(__FILE__));
define('OCD_INCLUDES', OCD_DIR . 'includes');
define('OCD_TEMPLATES', OCD_DIR . 'templates');

require_once OCD_INCLUDES . '/class-ocd-settings.php';
require_once OCD_INCLUDES . '/class-ocd-schema.php';
require_once OCD_INCLUDES . '/class-ocd-instruction-media.php';
require_once OCD_INCLUDES . '/class-ocd-highlevel.php';
require_once OCD_INCLUDES . '/class-ocd-woocommerce.php';
require_once OCD_INCLUDES . '/class-ocd-messaging.php';
require_once OCD_INCLUDES . '/class-ocd-projects.php';
require_once OCD_INCLUDES . '/class-ocd-tasks.php';
require_once OCD_INCLUDES . '/class-ocd-entitlements.php';
require_once OCD_INCLUDES . '/class-ocd-customer-crm.php';
require_once OCD_INCLUDES . '/class-ocd-store.php';
require_once OCD_INCLUDES . '/class-ocd-project-files.php';
require_once OCD_INCLUDES . '/class-ocd-billing.php';
require_once OCD_INCLUDES . '/class-ocd-pending-actions.php';
require_once OCD_INCLUDES . '/class-ocd-woocommerce-hooks.php';
require_once OCD_INCLUDES . '/class-ocd-rest-api.php';
require_once OCD_INCLUDES . '/class-ocd-shortcodes.php';
require_once OCD_INCLUDES . '/class-ocd-admin.php';
require_once OCD_INCLUDES . '/class-ocd-assets.php';

final class Oversee_Customer_Dashboard {

    private static $instance = null;

    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('plugins_loaded', [$this, 'init']);
        register_activation_hook(__FILE__, [$this, 'activate']);
    }

    public function init() {
        load_plugin_textdomain('oversee-customer-dashboard', false, dirname(plugin_basename(__FILE__)) . '/languages');

        OCD_Settings::init();
        OCD_Schema::maybe_install();
        OCD_REST_API::init();
        OCD_Shortcodes::init();
        OCD_Admin::init();
        OCD_Assets::init();
        if (class_exists('OCD_WooCommerce_Hooks')) {
            OCD_WooCommerce_Hooks::init();
        }
    }

    public function activate() {
        OCD_Settings::install_defaults();
        OCD_Schema::install();
        update_option(OCD_Schema::DB_VERSION_OPTION, OCD_Schema::DB_VERSION);
        // Make sure the customer/subscriber/admin roles can see the dashboard.
        foreach (['customer', 'subscriber', 'administrator', 'shop_manager'] as $role) {
            OCD_WooCommerce_Hooks::add_capability_to_role($role);
        }
        flush_rewrite_rules();
    }
}

Oversee_Customer_Dashboard::instance();
