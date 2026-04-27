<?php
/**
 * Roles for the Oversee dashboard.
 *
 * Five custom roles cover the Oversee org chart plus the existing site
 * administrator role:
 *
 *   - oversee_client          (the customer; default after WC signup)
 *   - oversee_account_manager (assigned to a portfolio of clients)
 *   - oversee_specialist      (delivers work on a board)
 *   - oversee_contractor      (external delivery, board-scoped only)
 *   - oversee_admin           (Oversee staff with full operational access)
 *
 * The standard `administrator` role keeps superuser status; oversee_admin is
 * the day-to-day operational role for staff that should not have full WP
 * superuser capabilities.
 *
 * Roles install on plugin activation and on every plugins_loaded run if the
 * version stamp on the option store doesn't match — that lets us add caps
 * without forcing an admin to deactivate/reactivate.
 *
 * @package Oversee_Customer_Dashboard
 */

if (!defined('ABSPATH')) {
    exit;
}

class OCD_Roles {

    const VERSION_OPTION = 'ocd_roles_version';
    const VERSION        = '1.0.0';

    const ROLE_CLIENT          = 'oversee_client';
    const ROLE_ACCOUNT_MANAGER = 'oversee_account_manager';
    const ROLE_SPECIALIST      = 'oversee_specialist';
    const ROLE_CONTRACTOR      = 'oversee_contractor';
    const ROLE_ADMIN           = 'oversee_admin';

    /** Capability that gates the dashboard at /dashboard/. */
    const CAP_VIEW_DASHBOARD = 'access_oversee_dashboard';

    /** Capability that gates Oversee staff/admin operational pages. */
    const CAP_MANAGE_DASHBOARD = 'manage_oversee_dashboard';

    /** Capability for working on boards (specialists/contractors). */
    const CAP_WORK_BOARDS = 'work_oversee_boards';

    /** Capability for managing client portfolios (account managers, admins). */
    const CAP_MANAGE_CLIENTS = 'manage_oversee_clients';

    public static function init() {
        add_action('plugins_loaded', [__CLASS__, 'maybe_install'], 9);
    }

    public static function maybe_install() {
        if (get_option(self::VERSION_OPTION) === self::VERSION) {
            return;
        }
        self::install();
        update_option(self::VERSION_OPTION, self::VERSION);
    }

    public static function install() {
        // Client — read-only access to their own dashboard.
        self::ensure_role(self::ROLE_CLIENT, __('Oversee Client', 'oversee-customer-dashboard'), [
            'read'                   => true,
            self::CAP_VIEW_DASHBOARD => true,
        ]);

        // Specialist — works boards but does not manage clients/billing.
        self::ensure_role(self::ROLE_SPECIALIST, __('Oversee Specialist', 'oversee-customer-dashboard'), [
            'read'                   => true,
            self::CAP_VIEW_DASHBOARD => true,
            self::CAP_WORK_BOARDS    => true,
        ]);

        // Contractor — same as specialist but board-scoped (REST permission
        // callbacks add the board membership check on top of this cap).
        self::ensure_role(self::ROLE_CONTRACTOR, __('Oversee Contractor', 'oversee-customer-dashboard'), [
            'read'                   => true,
            self::CAP_VIEW_DASHBOARD => true,
            self::CAP_WORK_BOARDS    => true,
        ]);

        // Account Manager — manages a client portfolio across boards/billing.
        self::ensure_role(self::ROLE_ACCOUNT_MANAGER, __('Oversee Account Manager', 'oversee-customer-dashboard'), [
            'read'                   => true,
            self::CAP_VIEW_DASHBOARD => true,
            self::CAP_WORK_BOARDS    => true,
            self::CAP_MANAGE_CLIENTS => true,
        ]);

        // Oversee Admin — full operational access.
        self::ensure_role(self::ROLE_ADMIN, __('Oversee Admin', 'oversee-customer-dashboard'), [
            'read'                     => true,
            self::CAP_VIEW_DASHBOARD   => true,
            self::CAP_WORK_BOARDS      => true,
            self::CAP_MANAGE_CLIENTS   => true,
            self::CAP_MANAGE_DASHBOARD => true,
            'edit_posts'               => true,
            'edit_others_posts'        => true,
            'publish_posts'            => true,
            'edit_published_posts'     => true,
            'manage_woocommerce'       => true,
        ]);

        // Mirror the operational caps onto the WordPress administrator so an
        // emergency superuser can always get into the dashboard.
        $admin = get_role('administrator');
        if ($admin) {
            foreach ([self::CAP_VIEW_DASHBOARD, self::CAP_WORK_BOARDS, self::CAP_MANAGE_CLIENTS, self::CAP_MANAGE_DASHBOARD] as $cap) {
                $admin->add_cap($cap);
            }
        }

        // The standard WooCommerce customer role gets dashboard view access so
        // existing customers don't lose access when this plugin lights up.
        $customer = get_role('customer');
        if ($customer) {
            $customer->add_cap(self::CAP_VIEW_DASHBOARD);
        }
    }

    public static function uninstall_roles() {
        foreach ([self::ROLE_CLIENT, self::ROLE_SPECIALIST, self::ROLE_CONTRACTOR, self::ROLE_ACCOUNT_MANAGER, self::ROLE_ADMIN] as $role) {
            if (function_exists('remove_role') && get_role($role)) {
                remove_role($role);
            }
        }
        delete_option(self::VERSION_OPTION);
    }

    private static function ensure_role($slug, $label, $caps) {
        $existing = get_role($slug);
        if ($existing) {
            foreach ($caps as $cap => $grant) {
                if ($grant) {
                    $existing->add_cap($cap);
                } else {
                    $existing->remove_cap($cap);
                }
            }
            return;
        }
        if (function_exists('add_role')) {
            add_role($slug, $label, $caps);
        }
    }

    /**
     * Promote a WooCommerce customer to oversee_client without removing the
     * customer role (Subscriptions hooks key off "customer"). Used by the
     * woocommerce_created_customer hook.
     */
    public static function promote_to_client($user_id) {
        $user = get_userdata((int) $user_id);
        if (!$user) {
            return false;
        }
        if (!in_array(self::ROLE_CLIENT, (array) $user->roles, true)) {
            $user->add_role(self::ROLE_CLIENT);
        }
        $user->add_cap(self::CAP_VIEW_DASHBOARD);
        return true;
    }

    public static function is_oversee_staff($user_id = 0) {
        $user = $user_id ? get_userdata($user_id) : wp_get_current_user();
        if (!$user) {
            return false;
        }
        $staff_roles = [self::ROLE_ADMIN, self::ROLE_ACCOUNT_MANAGER, self::ROLE_SPECIALIST, self::ROLE_CONTRACTOR, 'administrator', 'shop_manager'];
        foreach ($staff_roles as $r) {
            if (in_array($r, (array) $user->roles, true)) {
                return true;
            }
        }
        return false;
    }
}
