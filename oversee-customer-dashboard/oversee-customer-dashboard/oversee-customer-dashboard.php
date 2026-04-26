<?php
/**
 * Plugin Name: Oversee Customer Dashboard
 * Plugin URI: https://overseeagency.com/plugins/oversee-customer-dashboard
 * Description: Customer-facing dashboard and Oversee Agency admin portal. Integrates HighLevel/LeadConnector CRM and WooCommerce Subscriptions to surface contacts, opportunities, orders, subscriptions and ticket links in one place.
 * Version: 1.0.0
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

define('OCD_VERSION', '1.0.0');
define('OCD_FILE', __FILE__);
define('OCD_DIR', plugin_dir_path(__FILE__));
define('OCD_URL', plugin_dir_url(__FILE__));
define('OCD_INCLUDES', OCD_DIR . 'includes');
define('OCD_TEMPLATES', OCD_DIR . 'templates');

require_once OCD_INCLUDES . '/class-ocd-settings.php';
require_once OCD_INCLUDES . '/class-ocd-highlevel.php';
require_once OCD_INCLUDES . '/class-ocd-woocommerce.php';
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
        OCD_REST_API::init();
        OCD_Shortcodes::init();
        OCD_Admin::init();
        OCD_Assets::init();
    }

    public function activate() {
        OCD_Settings::install_defaults();
        flush_rewrite_rules();
    }
}

Oversee_Customer_Dashboard::instance();
