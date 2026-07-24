<?php
/**
 * Plugin Name: Planning Index — Regional Bundles Checkout
 * Plugin URI: https://planningindex.co.uk
 * Description: React-based regional bundles checkout for Paid Memberships Pro (level 59).
 * Version: 1.0.0
 * Author: Planning Index
 * License: GPL-2.0-or-later
 *
 * @package PlanningIndexRegionalBundles
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PIRB_VERSION', '1.0.0');
define('PIRB_PLUGIN_FILE', __FILE__);
define('PIRB_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PIRB_PLUGIN_URL', plugin_dir_url(__FILE__));

define('PIRB_META_KEY', 'pmpc_selected_councils');
define('PIRB_META_ALLOWED', 'pmrb_allowed_councils');
define('PIRB_META_PRICE', 'pmrb_calculated_price');
define('PIRB_META_TEMPLATE', 'pmrb_default_template');
define('PIRB_META_BUSINESS', 'pmrb_business_info');

define('PIRB_TOTAL_STEPS', 4);
define('PIRB_SESSION_KEY', 'pirb_checkout_session');
define('PIRB_OPTION_LEVEL_ID', 'pirb_level_id');
define('PIRB_REST_NAMESPACE', 'planningindex-regional/v1');

require_once PIRB_PLUGIN_DIR . 'includes/region-bundles.php';
require_once PIRB_PLUGIN_DIR . 'src/Plugin.php';
require_once PIRB_PLUGIN_DIR . 'src/CheckoutDetection.php';
require_once PIRB_PLUGIN_DIR . 'src/AssetEnqueue.php';
require_once PIRB_PLUGIN_DIR . 'src/Shortcode.php';
require_once PIRB_PLUGIN_DIR . 'src/RegionData.php';
require_once PIRB_PLUGIN_DIR . 'src/Controllers/RegionsController.php';
require_once PIRB_PLUGIN_DIR . 'src/Controllers/TemplatesController.php';
require_once PIRB_PLUGIN_DIR . 'src/Controllers/CheckUserController.php';
require_once PIRB_PLUGIN_DIR . 'src/Controllers/SessionController.php';
require_once PIRB_PLUGIN_DIR . 'src/Controllers/ProfileController.php';
require_once PIRB_PLUGIN_DIR . 'src/Controllers/LoginController.php';
require_once PIRB_PLUGIN_DIR . 'src/Controllers/CheckoutController.php';
require_once PIRB_PLUGIN_DIR . 'src/Controllers/ConfigController.php';
require_once PIRB_PLUGIN_DIR . 'src/Controllers/StripeSessionController.php';
require_once PIRB_PLUGIN_DIR . 'src/REST_Router.php';
require_once PIRB_PLUGIN_DIR . 'src/Admin/SettingsPage.php';
require_once PIRB_PLUGIN_DIR . 'src/PmproHooks.php';

register_activation_hook(__FILE__, ['PlanningIndexRegionalBundles', 'activate']);
register_deactivation_hook(__FILE__, ['PlanningIndexRegionalBundles', 'deactivate']);

add_action('plugins_loaded', ['PlanningIndexRegionalBundles', 'bootstrap']);

function pirb_inject_checkout_css_nuclear(): void
{
    $should_inject = false;

    if (class_exists('PIRB_CheckoutDetection') && PIRB_CheckoutDetection::is_checkout_page()) {
        $should_inject = true;
    }

    if (!$should_inject && !empty($_REQUEST['pirb_complete'])) {
        $configured_level = intval(get_option(PIRB_OPTION_LEVEL_ID, 0));
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

    if (!$should_inject && class_exists('PIRB_CheckoutDetection') && PIRB_CheckoutDetection::has_checkout_shortcode()) {
        $should_inject = true;
    }

    if (!$should_inject) {
        return;
    }

    $manifest = PIRB_PLUGIN_DIR . 'build/.vite/manifest.json';
    $css_file = null;

    if (file_exists($manifest)) {
        $m = json_decode(file_get_contents($manifest), true);
        if (is_array($m) && isset($m['index.html']['css'][0])) {
            $css_file = $m['index.html']['css'][0];
        }
    }

    if (!$css_file) {
        $assets_dir = PIRB_PLUGIN_DIR . 'build/assets/';
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
        $css_path = PIRB_PLUGIN_DIR . 'build/' . $css_file;
        if (file_exists($css_path)) {
            echo '<style id="pirb-checkout-css-nuclear">' . file_get_contents($css_path) . '</style>' . "\n";
        }
    }
}

add_action('wp_head', 'pirb_inject_checkout_css_nuclear', 1);
add_action('wp_head', 'pirb_inject_checkout_css_nuclear', 999);
add_action('wp_footer', 'pirb_inject_checkout_css_nuclear', 1);
