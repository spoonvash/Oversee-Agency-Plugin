<?php
/**
 * WP-Admin: settings page and admin dashboard menu.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OCD_Admin {

    public static function init() {
        add_action('admin_menu', [__CLASS__, 'menu']);
    }

    public static function menu() {
        add_menu_page(
            __('Oversee Dashboard', 'oversee-customer-dashboard'),
            __('Oversee Dashboard', 'oversee-customer-dashboard'),
            'manage_options',
            'oversee-customer-dashboard',
            [__CLASS__, 'render_admin_page'],
            'dashicons-businessman',
            30
        );
        add_submenu_page(
            'oversee-customer-dashboard',
            __('Settings', 'oversee-customer-dashboard'),
            __('Settings', 'oversee-customer-dashboard'),
            'manage_options',
            'oversee-customer-dashboard-settings',
            [__CLASS__, 'render_settings_page']
        );
    }

    public static function render_admin_page() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');
        OCD_Assets::enqueue_frontend();
        echo '<div class="wrap"><h1>' . esc_html__('Oversee Customer Dashboard', 'oversee-customer-dashboard') . '</h1>';
        include OCD_TEMPLATES . '/admin-dashboard.php';
        echo '</div>';
    }

    public static function render_settings_page() {
        if (!current_user_can('manage_options')) wp_die('Forbidden');

        if (isset($_POST['ocd_save_settings']) && check_admin_referer('ocd_save_settings_nonce')) {
            $values = isset($_POST[OCD_Settings::OPTION_KEY]) && is_array($_POST[OCD_Settings::OPTION_KEY]) ? $_POST[OCD_Settings::OPTION_KEY] : [];
            $clean  = OCD_Settings::sanitize($values);
            update_option(OCD_Settings::OPTION_KEY, $clean);
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'oversee-customer-dashboard') . '</p></div>';
        }

        $opts   = get_option(OCD_Settings::OPTION_KEY, OCD_Settings::defaults());
        $status = OCD_Settings::connection_status();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Oversee Customer Dashboard — Settings', 'oversee-customer-dashboard'); ?></h1>
            <p><?php esc_html_e('Tokens and secrets are best stored as environment variables (or wp-config constants) rather than database options. The plugin reads these env vars first and falls back to the values below if unset.', 'oversee-customer-dashboard'); ?></p>

            <table class="widefat" style="max-width:680px;margin-bottom:20px;">
                <thead><tr><th><?php esc_html_e('Connection', 'oversee-customer-dashboard'); ?></th><th><?php esc_html_e('Status', 'oversee-customer-dashboard'); ?></th></tr></thead>
                <tbody>
                    <tr><td>HighLevel CRM</td><td><?php echo $status['highlevel'] ? '<strong style="color:#0a7c2f">Configured</strong>' : '<em>Not configured</em>'; ?></td></tr>
                    <tr><td>WooCommerce</td><td><?php echo $status['woocommerce'] ? '<strong style="color:#0a7c2f">Configured</strong>' : '<em>Not configured</em>'; ?></td></tr>
                </tbody>
            </table>

            <form method="post">
                <?php wp_nonce_field('ocd_save_settings_nonce'); ?>
                <h2><?php esc_html_e('HighLevel / LeadConnector', 'oversee-customer-dashboard'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label>Base URL</label></th>
                        <td><input type="url" name="<?php echo esc_attr(OCD_Settings::OPTION_KEY); ?>[highlevel_base_url]" value="<?php echo esc_attr($opts['highlevel_base_url']); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label>API Version</label></th>
                        <td><input type="text" name="<?php echo esc_attr(OCD_Settings::OPTION_KEY); ?>[highlevel_api_version]" value="<?php echo esc_attr($opts['highlevel_api_version']); ?>" class="regular-text" /></td>
                    </tr>
                    <tr>
                        <th><label>API Token (Bearer)</label></th>
                        <td>
                            <input type="password" name="<?php echo esc_attr(OCD_Settings::OPTION_KEY); ?>[highlevel_token]" value="<?php echo esc_attr($opts['highlevel_token']); ?>" class="regular-text" autocomplete="new-password" />
                            <p class="description">Env var: <code>HIGHLEVEL_API_TOKEN</code> (preferred)</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Location ID</label></th>
                        <td>
                            <input type="text" name="<?php echo esc_attr(OCD_Settings::OPTION_KEY); ?>[highlevel_location_id]" value="<?php echo esc_attr($opts['highlevel_location_id']); ?>" class="regular-text" />
                            <p class="description">Env var: <code>HIGHLEVEL_LOCATION_ID</code></p>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('WooCommerce', 'oversee-customer-dashboard'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label>WP Base URL</label></th>
                        <td>
                            <input type="url" name="<?php echo esc_attr(OCD_Settings::OPTION_KEY); ?>[wp_base_url]" value="<?php echo esc_attr($opts['wp_base_url']); ?>" class="regular-text" placeholder="https://overseeagency.com" />
                            <p class="description">Env var: <code>OVERSEE_WP_BASE_URL</code>. Leave matching this site to talk to local WooCommerce.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Consumer Key</label></th>
                        <td><input type="text" name="<?php echo esc_attr(OCD_Settings::OPTION_KEY); ?>[woo_consumer_key]" value="<?php echo esc_attr($opts['woo_consumer_key']); ?>" class="regular-text" />
                            <p class="description">Env var: <code>WOOCOMMERCE_CONSUMER_KEY</code></p>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Consumer Secret</label></th>
                        <td><input type="password" name="<?php echo esc_attr(OCD_Settings::OPTION_KEY); ?>[woo_consumer_secret]" value="<?php echo esc_attr($opts['woo_consumer_secret']); ?>" class="regular-text" autocomplete="new-password" />
                            <p class="description">Env var: <code>WOOCOMMERCE_CONSUMER_SECRET</code></p>
                        </td>
                    </tr>
                </table>

                <h2><?php esc_html_e('Behavior', 'oversee-customer-dashboard'); ?></h2>
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label>Allow Oversee staff admin view</label></th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr(OCD_Settings::OPTION_KEY); ?>[allow_admin_for_oversee_staff]" value="1" <?php checked($opts['allow_admin_for_oversee_staff']); ?> /> <?php esc_html_e('Users with manage_woocommerce or manage_options can see the admin dashboard.', 'oversee-customer-dashboard'); ?></label></td>
                    </tr>
                </table>

                <p><button type="submit" name="ocd_save_settings" class="button button-primary"><?php esc_html_e('Save Settings', 'oversee-customer-dashboard'); ?></button></p>
            </form>

            <h2><?php esc_html_e('Shortcodes', 'oversee-customer-dashboard'); ?></h2>
            <p><code>[oversee_customer_dashboard]</code> — customer-facing portal.</p>
            <p><code>[oversee_admin_dashboard]</code> — Oversee staff admin dashboard (requires manage_woocommerce).</p>
        </div>
        <?php
    }
}
