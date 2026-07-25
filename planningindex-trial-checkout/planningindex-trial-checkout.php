<?php
/**
 * Plugin Name: Planning Index Trial Checkout
 * Description: React-based multi-step checkout wizard for free 14-day trial PMPro subscriptions on planningindex.co.uk.
 * Version: 1.0.0
 * Author: Planning Index
 * Text Domain: planningindex-trial-checkout
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PIT_VERSION', '1.0.0');
define('PIT_PLUGIN_FILE', __FILE__);
define('PIT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PIT_PLUGIN_URL', plugin_dir_url(__FILE__));

define('PIT_UNIT_PRICE', 3);
define('PIT_MIN_SELECTION', 1);
define('PIT_MAX_SELECTION', 5);
define('PIT_TRIAL_DAYS', 14);
define('PIT_META_KEY', 'pmpc_selected_councils');
define('PIT_META_PRICE', 'pmpc_calculated_price');
define('PIT_META_TEMPLATE', 'pmpc_default_template');
define('PIT_META_BUSINESS', 'pmpc_business_info');
define('PIT_META_TRIAL_EXPIRATION', 'pmpc_trial_expiration');
define('PIT_META_IS_TRIAL', 'pmpc_is_trial_user');
define('PIT_META_TRIAL_EXPIRED', 'pmpc_trial_expired');
define('PIT_TOTAL_STEPS', 4);
define('PIT_SESSION_KEY', 'pmpc_trial_checkout_session');
define('PIT_OPTION_LEVEL_ID', 'pmpc_trial_level_id');
define('PIT_REST_NAMESPACE', 'planningindex/v1');

require_once PIT_PLUGIN_DIR . 'src/Plugin.php';
require_once PIT_PLUGIN_DIR . 'src/CheckoutDetection.php';
require_once PIT_PLUGIN_DIR . 'src/AssetEnqueue.php';
require_once PIT_PLUGIN_DIR . 'src/Shortcode.php';
require_once PIT_PLUGIN_DIR . 'src/CouncilData.php';
require_once PIT_PLUGIN_DIR . 'src/Controllers/CouncilsController.php';
require_once PIT_PLUGIN_DIR . 'src/Controllers/TemplatesController.php';
require_once PIT_PLUGIN_DIR . 'src/Controllers/CheckUserController.php';
require_once PIT_PLUGIN_DIR . 'src/Controllers/SessionController.php';
require_once PIT_PLUGIN_DIR . 'src/Controllers/ProfileController.php';
require_once PIT_PLUGIN_DIR . 'src/Controllers/ConfigController.php';
require_once PIT_PLUGIN_DIR . 'src/Controllers/LoginController.php';
require_once PIT_PLUGIN_DIR . 'src/Controllers/CheckoutController.php';
require_once PIT_PLUGIN_DIR . 'src/REST_Router.php';
require_once PIT_PLUGIN_DIR . 'src/Admin/SettingsPage.php';
require_once PIT_PLUGIN_DIR . 'src/PmproHooks.php';

register_activation_hook(__FILE__, ['PlanningIndexTrialCheckout', 'activate']);
register_deactivation_hook(__FILE__, ['PlanningIndexTrialCheckout', 'deactivate']);

PlanningIndexTrialCheckout::instance();

/**
 * Nuclear CSS injection — guarantees the React checkout CSS is present on
 * the page even when wp_enqueue_scripts fires before PMPro globals are
 * initialized (common for logged-out users).
 */
function pit_inject_checkout_css_nuclear(): void
{
    $should_inject = false;

    if (class_exists('PIT_CheckoutDetection') && PIT_CheckoutDetection::is_checkout_page()) {
        $should_inject = true;
    }

    if (!$should_inject && !empty($_REQUEST['pi_complete'])) {
        $configured_level = intval(get_option(PIT_OPTION_LEVEL_ID, 0));
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

    if (!$should_inject && class_exists('PIT_CheckoutDetection') && PIT_CheckoutDetection::has_checkout_shortcode()) {
        $should_inject = true;
    }

    if (!$should_inject) {
        return;
    }

    $manifest = PIT_PLUGIN_DIR . 'build/.vite/manifest.json';
    $css_file = null;

    if (file_exists($manifest)) {
        $m = json_decode(file_get_contents($manifest), true);
        if (is_array($m) && isset($m['index.html']['css'][0])) {
            $css_file = $m['index.html']['css'][0];
        }
    }

    if (!$css_file) {
        $assets_dir = PIT_PLUGIN_DIR . 'build/assets/';
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
        $css_path = PIT_PLUGIN_DIR . 'build/' . $css_file;
        if (file_exists($css_path)) {
            echo '<style id="pit-checkout-css-nuclear">' . file_get_contents($css_path) . '</style>' . "\n";
        }
    }
}

add_action('wp_head', 'pit_inject_checkout_css_nuclear', 1);
add_action('wp_head', 'pit_inject_checkout_css_nuclear', 999);
add_action('wp_footer', 'pit_inject_checkout_css_nuclear', 1);
