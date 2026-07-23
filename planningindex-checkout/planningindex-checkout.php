<?php
/**
 * Plugin Name: Planning Index Checkout
 * Description: React-based multi-step checkout wizard for per-council PMPro subscriptions on planningindex.co.uk.
 * Version: 1.0.0
 * Author: Planning Index
 * Text Domain: planningindex-checkout
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PIC_VERSION', '1.0.0');
define('PIC_PLUGIN_FILE', __FILE__);
define('PIC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PIC_PLUGIN_URL', plugin_dir_url(__FILE__));

define('PIC_UNIT_PRICE', 3);
define('PIC_MIN_SELECTION', 3);
define('PIC_META_KEY', 'pmpc_selected_councils');
define('PIC_META_PRICE', 'pmpc_calculated_price');
define('PIC_META_TEMPLATE', 'pmpc_default_template');
define('PIC_META_BUSINESS', 'pmpc_business_info');
define('PIC_TOTAL_STEPS', 4);
define('PIC_SESSION_KEY', 'pmpc_checkout_session');
define('PIC_OPTION_LEVEL_ID', 'pmpc_per_council_level_id');
define('PIC_REST_NAMESPACE', 'planningindex/v1');

require_once PIC_PLUGIN_DIR . 'src/Plugin.php';
require_once PIC_PLUGIN_DIR . 'src/CheckoutDetection.php';
require_once PIC_PLUGIN_DIR . 'src/AssetEnqueue.php';
require_once PIC_PLUGIN_DIR . 'src/Shortcode.php';
require_once PIC_PLUGIN_DIR . 'src/CouncilData.php';
require_once PIC_PLUGIN_DIR . 'src/Controllers/CouncilsController.php';
require_once PIC_PLUGIN_DIR . 'src/Controllers/TemplatesController.php';
require_once PIC_PLUGIN_DIR . 'src/Controllers/CheckUserController.php';
require_once PIC_PLUGIN_DIR . 'src/Controllers/SessionController.php';
require_once PIC_PLUGIN_DIR . 'src/Controllers/ProfileController.php';
require_once PIC_PLUGIN_DIR . 'src/Controllers/ConfigController.php';
require_once PIC_PLUGIN_DIR . 'src/Controllers/LoginController.php';
require_once PIC_PLUGIN_DIR . 'src/Controllers/CheckoutController.php';
require_once PIC_PLUGIN_DIR . 'src/REST_Router.php';
require_once PIC_PLUGIN_DIR . 'src/Admin/SettingsPage.php';
require_once PIC_PLUGIN_DIR . 'src/PmproHooks.php';

register_activation_hook(__FILE__, ['PlanningIndexCheckout', 'activate']);
register_deactivation_hook(__FILE__, ['PlanningIndexCheckout', 'deactivate']);

PlanningIndexCheckout::instance();

/**
 * Nuclear CSS injection — guarantees the React checkout CSS is present on
 * the page even when wp_enqueue_scripts fires before PMPro globals are
 * initialized (common for logged-out users). Mirrors the approach used by
 * the trial, regional-bundle, and enterprise checkout plugins.
 */
function pic_inject_checkout_css_nuclear(): void
{
    $should_inject = false;

    // Inject on the React wizard page
    if (class_exists('PIC_CheckoutDetection') && PIC_CheckoutDetection::is_checkout_page()) {
        $should_inject = true;
    }

    // Inject on the pi_complete PMPro checkout page for our level
    if (!$should_inject && !empty($_REQUEST['pi_complete'])) {
        $configured_level = intval(get_option(PIC_OPTION_LEVEL_ID, 0));
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

    // Inject on any page with our shortcode
    if (!$should_inject && class_exists('PIC_CheckoutDetection') && PIC_CheckoutDetection::has_checkout_shortcode()) {
        $should_inject = true;
    }

    if (!$should_inject) {
        return;
    }

    $manifest = PIC_PLUGIN_DIR . 'build/.vite/manifest.json';
    $css_file = null;

    if (file_exists($manifest)) {
        $m = json_decode(file_get_contents($manifest), true);
        if (is_array($m) && isset($m['index.html']['css'][0])) {
            $css_file = $m['index.html']['css'][0];
        }
    }

    // Fallback: scan build/assets for the hashed CSS
    if (!$css_file) {
        $assets_dir = PIC_PLUGIN_DIR . 'build/assets/';
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
        $css_path = PIC_PLUGIN_DIR . 'build/' . $css_file;
        if (file_exists($css_path)) {
            echo '<style id="pic-checkout-css-nuclear">' . file_get_contents($css_path) . '</style>' . "\n";
        }
    }
}

// Priority 1: inject before any other head content
add_action('wp_head', 'pic_inject_checkout_css_nuclear', 1);
// Priority 999: catch themes that flush after wp_head
add_action('wp_head', 'pic_inject_checkout_css_nuclear', 999);
// wp_footer fallback: if CSS still not on the page, inject it here
add_action('wp_footer', 'pic_inject_checkout_css_nuclear', 1);
