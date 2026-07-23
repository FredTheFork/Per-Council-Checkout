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
