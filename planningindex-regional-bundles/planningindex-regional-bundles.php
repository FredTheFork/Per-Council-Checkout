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

/**
 * Nuclear CSS injection — reads the Vite manifest to find the CSS file
 * path, then injects it inline via a <style> tag at wp_head priority 1,
 * wp_head priority 999, and wp_footer priority 1. This ensures the CSS
 * loads even when WordPress's standard enqueue pipeline is bypassed.
 */
function pirb_inject_checkout_css_nuclear()
{
    $manifest_path = PIRB_PLUGIN_DIR . 'build/.vite/manifest.json';
    $css_url = '';

    if (file_exists($manifest_path)) {
        $manifest = json_decode(file_get_contents($manifest_path), true);
        if (is_array($manifest)) {
            foreach ($manifest as $entry) {
                if (isset($entry['css']) && is_array($entry['css']) && !empty($entry['css'])) {
                    $css_url = PIRB_PLUGIN_URL . 'build/' . $entry['css'][0];
                    break;
                }
            }
        }
    }

    if (empty($css_url)) {
        $css_files = glob(PIRB_PLUGIN_DIR . 'build/assets/*.css');
        if (!empty($css_files) && isset($css_files[0])) {
            $css_url = PIRB_PLUGIN_URL . 'build/assets/' . basename($css_files[0]);
        }
    }

    if (empty($css_url)) {
        return;
    }

    $css_content = file_get_contents(PIRB_PLUGIN_DIR . str_replace(PIRB_PLUGIN_URL, '', $css_url));
    if (empty($css_content)) {
        return;
    }

    echo '<style id="pirb-checkout-css-nuclear">' . $css_content . '</style>' . "\n";
}

add_action('wp_head', 'pirb_inject_checkout_css_nuclear', 1);
add_action('wp_head', 'pirb_inject_checkout_css_nuclear', 999);
add_action('wp_footer', 'pirb_inject_checkout_css_nuclear', 1);
