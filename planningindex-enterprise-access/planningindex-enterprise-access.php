<?php
/**
 * Plugin Name: Planning Index — Enterprise Access Checkout
 * Plugin URI: https://planningindex.co.uk
 * Description: React-based enterprise access checkout for Paid Memberships Pro — grants access to all UK councils.
 * Version: 1.0.0
 * Author: Planning Index
 * License: GPL-2.0-or-later
 *
 * @package PlanningIndexEnterpriseAccess
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PIE_VERSION', '1.0.0');
define('PIE_PLUGIN_FILE', __FILE__);
define('PIE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PIE_PLUGIN_URL', plugin_dir_url(__FILE__));

define('PIE_META_REGION_KEY', 'pmrb_region_bundle');
define('PIE_META_ALLOWED_KEY', 'pmrb_allowed_councils');
define('PIE_META_SELECTED_KEY', 'pmpc_selected_councils');
define('PIE_META_TEMPLATE', 'pmpe_default_template');
define('PIE_META_BUSINESS', 'pmpe_business_info');
define('PIE_META_TEAM_SEATS', 'pmpe_team_seats');
define('PIE_META_TEAM_OWNER', 'pmpe_is_team_owner');
define('PIE_META_TEAM_MEMBERS', 'pmpe_team_members');

define('PIE_TOTAL_STEPS', 4);
define('PIE_SESSION_KEY', 'pie_checkout_session');
define('PIE_OPTION_LEVEL_ID', 'pmpe_enterprise_checkout_level_id');
define('PIE_REST_NAMESPACE', 'planningindex-enterprise/v1');

require_once PIE_PLUGIN_DIR . 'src/Plugin.php';
require_once PIE_PLUGIN_DIR . 'src/CheckoutDetection.php';
require_once PIE_PLUGIN_DIR . 'src/AssetEnqueue.php';
require_once PIE_PLUGIN_DIR . 'src/Shortcode.php';
require_once PIE_PLUGIN_DIR . 'src/EnterpriseData.php';
require_once PIE_PLUGIN_DIR . 'src/Controllers/TemplatesController.php';
require_once PIE_PLUGIN_DIR . 'src/Controllers/CheckUserController.php';
require_once PIE_PLUGIN_DIR . 'src/Controllers/SessionController.php';
require_once PIE_PLUGIN_DIR . 'src/Controllers/ProfileController.php';
require_once PIE_PLUGIN_DIR . 'src/Controllers/LoginController.php';
require_once PIE_PLUGIN_DIR . 'src/Controllers/CheckoutController.php';
require_once PIE_PLUGIN_DIR . 'src/Controllers/ConfigController.php';
require_once PIE_PLUGIN_DIR . 'src/Controllers/StripeSessionController.php';
require_once PIE_PLUGIN_DIR . 'src/REST_Router.php';
require_once PIE_PLUGIN_DIR . 'src/Admin/SettingsPage.php';
require_once PIE_PLUGIN_DIR . 'src/PmproHooks.php';

register_activation_hook(__FILE__, ['PlanningIndexEnterpriseAccess', 'activate']);
register_deactivation_hook(__FILE__, ['PlanningIndexEnterpriseAccess', 'deactivate']);

add_action('plugins_loaded', ['PlanningIndexEnterpriseAccess', 'bootstrap']);

function pie_inject_checkout_css_nuclear(): void
{
    $should_inject = false;

    if (class_exists('PIE_CheckoutDetection') && PIE_CheckoutDetection::is_checkout_page()) {
        $should_inject = true;
    }

    if (!$should_inject && !empty($_REQUEST['pie_complete'])) {
        $configured_level = intval(get_option(PIE_OPTION_LEVEL_ID, 0));
        if ($configured_level > 0) {
            $current_level = 0;
            if (isset($_REQUEST['pmpro_level'])) {
                $current_level = intval($_REQUEST['pmpro_level']);
            } elseif (isset($_REQUEST['level'])) {
                $current_level = intval($_REQUEST['level']);
            }
            if ($current_level === $configured_level) {
                $should_inject = true;
            }
        }
    }

    if (!$should_inject && class_exists('PIE_CheckoutDetection') && PIE_CheckoutDetection::has_checkout_shortcode()) {
        $should_inject = true;
    }

    if (!$should_inject) {
        return;
    }

    $manifest = PIE_PLUGIN_DIR . 'build/.vite/manifest.json';
    $css_file = null;

    if (file_exists($manifest)) {
        $m = json_decode(file_get_contents($manifest), true);
        if (is_array($m) && isset($m['index.html']['css'][0])) {
            $css_file = $m['index.html']['css'][0];
        }
    }

    if (!$css_file) {
        $assets_dir = PIE_PLUGIN_DIR . 'build/assets/';
        if (is_dir($assets_dir)) {
            foreach (glob($assets_dir . '*.css') as $f) {
                if (strpos(basename($f), 'index') === 0) {
                    $css_file = 'assets/' . basename($f);
                    break;
                }
            }
        }
    }

    if ($css_file) {
        $css_path = PIE_PLUGIN_DIR . 'build/' . $css_file;
        if (file_exists($css_path)) {
            echo '<style id="pie-checkout-css-nuclear">' . file_get_contents($css_path) . '</style>' . "\n";
        }
    }
}

add_action('wp_head', 'pie_inject_checkout_css_nuclear', 1);
add_action('wp_head', 'pie_inject_checkout_css_nuclear', 999);
add_action('wp_footer', 'pie_inject_checkout_css_nuclear', 1);
