<?php

if (!defined('ABSPATH')) {
    exit;
}

class PlanningIndexCheckout
{
    private static $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('plugins_loaded', [$this, 'bootstrap']);
    }

    public function bootstrap(): void
    {
        if (!class_exists('PMPro_Subscription')) {
            if (!function_exists('pmpro_is_checkout')) {
                add_action('admin_notices', [$this, 'notice_pmpro_required']);
                return;
            }
        }

        PIC_CheckoutDetection::init();
        PIC_AssetEnqueue::init();
        PIC_Shortcode::init();
        PIC_REST_Router::init();
        PIC_Admin_SettingsPage::init();
        PIC_PmproHooks::init();
    }

    public static function activate(): void
    {
        if (!class_exists('PMPro_Subscription')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(
                esc_html__('Planning Index Checkout requires Paid Memberships Pro to be installed and active.', 'planningindex-checkout'),
                esc_html__('Plugin Activation Error', 'planningindex-checkout'),
                ['back_link' => true]
            );
        }

        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }

    public function notice_pmpro_required(): void
    {
        echo '<div class="notice notice-error"><p>'
            . esc_html__('Planning Index Checkout requires Paid Memberships Pro to be installed and active.', 'planningindex-checkout')
            . '</p></div>';
    }
}
