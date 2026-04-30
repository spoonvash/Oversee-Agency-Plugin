<?php
/**
 * Plugin Name: Oversee Dashboard
 * Plugin URI: https://overseeagency.com/plugins/oversee-dashboard
 * Description: Assembly-style WordPress-native client + admin dashboard for Oversee Agency. Roles, CPTs (project_board, service_template, intake_form_template, contract_template, client_record), wp_oversee_* tables, HighLevel SSO, WooCommerce-driven service provisioning, and a React SPA mounted at /dashboard/. Compatibility wrapper preserves the legacy `oversee-customer-dashboard` slug.
 * Version: 2.0.0
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

define('OCD_VERSION', '2.0.0');
define('OCD_FILE', __FILE__);
define('OCD_DIR', plugin_dir_path(__FILE__));
define('OCD_URL', plugin_dir_url(__FILE__));
define('OCD_INCLUDES', OCD_DIR . 'includes');
define('OCD_TEMPLATES', OCD_DIR . 'templates');

require_once OCD_INCLUDES . '/class-ocd-settings.php';
require_once OCD_INCLUDES . '/class-ocd-roles.php';
require_once OCD_INCLUDES . '/class-ocd-schema.php';
require_once OCD_INCLUDES . '/class-ocd-board-schema.php';
require_once OCD_INCLUDES . '/class-ocd-cpt.php';
require_once OCD_INCLUDES . '/class-ocd-instruction-media.php';
require_once OCD_INCLUDES . '/class-ocd-highlevel.php';
require_once OCD_INCLUDES . '/class-ocd-highlevel-sso.php';
require_once OCD_INCLUDES . '/class-ocd-woocommerce.php';
require_once OCD_INCLUDES . '/class-ocd-messaging.php';
require_once OCD_INCLUDES . '/class-ocd-projects.php';
require_once OCD_INCLUDES . '/class-ocd-tasks.php';
require_once OCD_INCLUDES . '/class-ocd-entitlements.php';
require_once OCD_INCLUDES . '/class-ocd-customer-crm.php';
require_once OCD_INCLUDES . '/class-ocd-store.php';
require_once OCD_INCLUDES . '/class-ocd-commerce.php';
require_once OCD_INCLUDES . '/class-ocd-project-files.php';
require_once OCD_INCLUDES . '/class-ocd-billing.php';
require_once OCD_INCLUDES . '/class-ocd-pending-actions.php';
require_once OCD_INCLUDES . '/class-ocd-woocommerce-hooks.php';
require_once OCD_INCLUDES . '/class-ocd-boards.php';
require_once OCD_INCLUDES . '/class-ocd-signup-hooks.php';
require_once OCD_INCLUDES . '/class-ocd-rest-api.php';
require_once OCD_INCLUDES . '/class-ocd-boards-rest.php';
require_once OCD_INCLUDES . '/class-ocd-shortcodes.php';
require_once OCD_INCLUDES . '/class-ocd-admin.php';
require_once OCD_INCLUDES . '/class-ocd-assets.php';
require_once OCD_INCLUDES . '/class-ocd-page-template.php';
// Spec-aligned (2.x) layer — additive, lives alongside legacy ocd_* code.
require_once OCD_INCLUDES . '/class-oversee-schema.php';
require_once OCD_INCLUDES . '/class-oversee-cpt.php';
require_once OCD_INCLUDES . '/class-oversee-rest-api.php';
require_once OCD_INCLUDES . '/class-oversee-signup-hooks.php';

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
        OCD_Roles::init();
        OCD_Schema::maybe_install();
        OCD_Board_Schema::maybe_install();
        Oversee_Schema::maybe_install();
        OCD_CPT::init();
        Oversee_CPT::init();
        OCD_REST_API::init();
        Oversee_REST_API::init();
        OCD_Boards_REST::init();
        OCD_Shortcodes::init();
        OCD_Admin::init();
        OCD_Assets::init();
        OCD_Page_Template::init();
        if (class_exists('OCD_WooCommerce_Hooks')) {
            OCD_WooCommerce_Hooks::init();
        }
        if (class_exists('OCD_Signup_Hooks')) {
            OCD_Signup_Hooks::init();
        }
        if (class_exists('Oversee_Signup_Hooks')) {
            Oversee_Signup_Hooks::init();
        }
    }

    public function activate() {
        OCD_Settings::install_defaults();
        OCD_Roles::install();
        OCD_Schema::install();
        OCD_Board_Schema::install();
        Oversee_Schema::install();
        update_option(OCD_Schema::DB_VERSION_OPTION, OCD_Schema::DB_VERSION);
        update_option(OCD_Board_Schema::DB_VERSION_OPTION, OCD_Board_Schema::DB_VERSION);
        update_option(Oversee_Schema::DB_VERSION_OPTION, Oversee_Schema::DB_VERSION);
        update_option(OCD_Roles::VERSION_OPTION, OCD_Roles::VERSION);

        // Make sure the customer/subscriber/admin roles can see the dashboard.
        foreach (['customer', 'subscriber', 'administrator', 'shop_manager'] as $role) {
            OCD_WooCommerce_Hooks::add_capability_to_role($role);
        }
        // Auto-assign the full-bleed template to the /dashboard page so the
        // child theme's header/footer/nav doesn't wrap the SPA shell.
        OCD_Page_Template::force_assign_on_activation();
        flush_rewrite_rules();
    }
}

Oversee_Customer_Dashboard::instance();
