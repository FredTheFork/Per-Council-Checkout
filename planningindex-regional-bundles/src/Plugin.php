<?php

if (!defined('ABSPATH')) {
    exit;
}

class PlanningIndexRegionalBundles
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

        PIRB_CheckoutDetection::init();
        PIRB_AssetEnqueue::init();
        PIRB_Shortcode::init();
        PIRB_REST_Router::init();
        PIRB_PmproHooks::init();
        PIRB_Admin_SettingsPage::init();
    }

    public static function activate(): void
    {
        if (!class_exists('PMPro_Subscription')) {
            deactivate_plugins(plugin_basename(PIRB_PLUGIN_FILE));
            wp_die('Planning Index — Regional Bundles Checkout requires Paid Memberships Pro to be installed and active.');
        }

        if (get_option(PIRB_OPTION_LEVEL_ID, 0) == 0) {
            update_option(PIRB_OPTION_LEVEL_ID, 59);
        }
    }

    public static function deactivate(): void
    {
    }

    public static function notice_pmpro_required(): void
    {
        echo '<div class="notice notice-error"><p><strong>Planning Index — Regional Bundles Checkout</strong> requires Paid Memberships Pro to be installed and active.</p></div>';
    }
}
