<?php

if (!defined('ABSPATH')) {
    exit;
}

class PlanningIndexEnterpriseAccess
{
    private static $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public static function bootstrap(): void
    {
        if (!class_exists('PMPro_Subscription')) {
            add_action('admin_notices', [__CLASS__, 'notice_pmpro_required']);
            return;
        }

        PIE_CheckoutDetection::init();
        PIE_AssetEnqueue::init();
        PIE_Shortcode::init();
        PIE_REST_Router::init();
        PIE_PmproHooks::init();
        PIE_Admin_SettingsPage::init();
    }

    public static function activate(): void
    {
        if (!class_exists('PMPro_Subscription')) {
            deactivate_plugins(plugin_basename(PIE_PLUGIN_FILE));
            wp_die('Planning Index — Enterprise Access Checkout requires Paid Memberships Pro to be installed and active.');
        }

        if (get_option(PIE_OPTION_LEVEL_ID, 0) == 0) {
            update_option(PIE_OPTION_LEVEL_ID, 60);
        }
    }

    public static function deactivate(): void
    {
    }

    public static function notice_pmpro_required(): void
    {
        echo '<div class="notice notice-error"><p><strong>Planning Index — Enterprise Access Checkout</strong> requires Paid Memberships Pro to be installed and active.</p></div>';
    }
}
