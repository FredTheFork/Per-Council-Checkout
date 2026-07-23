<?php

if (!defined('ABSPATH')) {
    exit;
}

class PIC_AssetEnqueue
{
    public static function init(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
        add_filter('script_loader_tag', [self::class, 'add_module_type'], 10, 3);
    }

    public static function enqueue_assets(): void
    {
        // Enqueue on the React wizard page (normal checkout detection) OR
        // on the PMPro checkout page after the wizard POSTs back with
        // pi_complete=1. In the latter case the React JS isn't needed
        // (PMPro renders its own form), but the CSS must still load so
        // the PMPro checkout page inherits the wizard's styling.
        $is_wizard_page = PIC_CheckoutDetection::is_checkout_page();
        $is_pi_complete = !empty($_REQUEST['pi_complete']) && self::is_per_council_level();

        if (!$is_wizard_page && !$is_pi_complete) {
            return;
        }

        $manifest = self::read_manifest();
        if ($manifest === null) {
            return;
        }

        $js_file = $manifest['js'] ?? null;
        $css_file = $manifest['css'] ?? null;

        // Only load the React JS on the wizard page, not on the
        // pi_complete PMPro checkout page (PMPro renders its own form).
        if ($is_wizard_page && $js_file && file_exists(PIC_PLUGIN_DIR . 'build/' . $js_file)) {
            $js_url = PIC_PLUGIN_URL . 'build/' . $js_file;
            $version = filemtime(PIC_PLUGIN_DIR . 'build/' . $js_file);

            $deps = [];
            $gateway = get_option('pmpro_gateway');
            if ($gateway === 'stripe') {
                $deps[] = 'jquery';
            }

            wp_enqueue_script('pic-checkout-js', $js_url, $deps, $version, true);
            self::inject_config();
        }

        // Always load CSS on both wizard and pi_complete pages.
        if ($css_file && file_exists(PIC_PLUGIN_DIR . 'build/' . $css_file)) {
            $css_url = PIC_PLUGIN_URL . 'build/' . $css_file;
            $version = filemtime(PIC_PLUGIN_DIR . 'build/' . $css_file);

            wp_enqueue_style('pic-checkout-css', $css_url, [], $version);
        }

        // Also inject the config on pi_complete pages so the PmproHooks
        // have access to the level ID and gateway via window.PlanningIndexCheckout.
        if ($is_pi_complete && !$is_wizard_page) {
            self::inject_config_inline();
        }
    }

    /**
     * Check if the current request is for the configured per-council level,
     * regardless of pi_complete. Used to decide whether to enqueue CSS
     * on the PMPro checkout page after the wizard redirects.
     */
    private static function is_per_council_level(): bool
    {
        $configured_level = intval(get_option(PIC_OPTION_LEVEL_ID, 0));
        if ($configured_level === 0) {
            return false;
        }

        $current_level = 0;
        if (isset($_REQUEST['pmpro_level'])) {
            $current_level = intval($_REQUEST['pmpro_level']);
        } elseif (isset($_REQUEST['level'])) {
            $current_level = intval($_REQUEST['level']);
        }

        return $current_level === $configured_level;
    }

    private static function read_manifest(): ?array
    {
        // Vite 5 places the manifest at build/.vite/manifest.json by default
        $manifest_paths = [
            PIC_PLUGIN_DIR . 'build/.vite/manifest.json',
            PIC_PLUGIN_DIR . 'build/manifest.json',
        ];

        foreach ($manifest_paths as $manifest_path) {
            if (file_exists($manifest_path)) {
                $manifest = json_decode(file_get_contents($manifest_path), true);
                if (is_array($manifest) && isset($manifest['index.html'])) {
                    $entry = $manifest['index.html'];
                    $js = $entry['file'] ?? null;
                    $css = $entry['css'][0] ?? null;
                    return ['js' => $js, 'css' => $css];
                }
            }
        }

        // Fallback: scan the assets directory for hashed files
        $assets_dir = PIC_PLUGIN_DIR . 'build/assets/';
        if (!is_dir($assets_dir)) {
            return null;
        }

        $js = null;
        $css = null;

        foreach (glob($assets_dir . '*.js') as $file) {
            if (strpos(basename($file), 'index') === 0) {
                $js = 'assets/' . basename($file);
                break;
            }
        }

        foreach (glob($assets_dir . '*.css') as $file) {
            if (strpos(basename($file), 'index') === 0) {
                $css = 'assets/' . basename($file);
                break;
            }
        }

        if ($js === null && $css === null) {
            return null;
        }

        return ['js' => $js, 'css' => $css];
    }

    private static function inject_config(): void
    {
        $user_current_template = 'standard-planning';
        $user_name = '';
        $user_email = '';

        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $business_info = get_user_meta($user_id, '_pi_business_info', true);
            if (is_array($business_info) && !empty($business_info['default_template'])) {
                $user_current_template = $business_info['default_template'];
            }
            $user = wp_get_current_user();
            $user_name = $user->display_name;
            $user_email = $user->user_email;
        }

        $gateway = get_option('pmpro_gateway', 'stripe');
        $level_id = intval(get_option(PIC_OPTION_LEVEL_ID, 0));

        $require_billing = true;
        if (function_exists('pmpro_getLevel') && $level_id > 0) {
            $level = pmpro_getLevel($level_id);
            if ($level && floatval($level->initial_payment) == 0 && floatval($level->billing_amount) == 0) {
                $require_billing = false;
            }
        }

        $checkout_url = '';
        if (function_exists('pmpro_url')) {
            $checkout_url = pmpro_url('checkout');
        }
        if (empty($checkout_url)) {
            $checkout_url = home_url('/membership-checkout/');
        }

        $config = [
            'apiBase' => esc_url_raw(rest_url(PIC_REST_NAMESPACE)),
            'nonce' => wp_create_nonce('wp_rest'),
            'checkoutUrl' => esc_url_raw($checkout_url),
            'checkoutNonce' => function_exists('wp_create_nonce') ? wp_create_nonce('pmpro_checkout_nonce') : '',
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'isLoggedIn' => is_user_logged_in(),
            'userId' => get_current_user_id(),
            'userName' => $user_name,
            'userEmail' => $user_email,
            'userCurrentTemplate' => $user_current_template,
            'unitPrice' => PIC_UNIT_PRICE,
            'minSelection' => PIC_MIN_SELECTION,
            'levelId' => $level_id,
            'gateway' => $gateway,
            'requireBilling' => $require_billing,
            'strings' => [
                'selectMinCouncils' => sprintf('Please select at least %d councils to continue.', PIC_MIN_SELECTION),
                'usernameRequired' => 'Please enter a username.',
                'passwordRequired' => 'Please enter a password with at least 8 characters.',
                'passwordMismatch' => 'Passwords do not match.',
                'emailRequired' => 'Please enter a valid email address.',
                'emailMismatch' => 'Email addresses do not match.',
                'processing' => 'Processing your subscription...',
                'continue' => 'Continue',
                'completeSubscription' => 'Complete Subscription',
                'perMonth' => '/month',
                'loadingTemplates' => 'Loading templates...',
                'templateLoadError' => 'Unable to load templates. Using defaults.',
            ],
        ];

        $js = 'window.PlanningIndexCheckout = ' . wp_json_encode($config) . ';';
        wp_add_inline_script('pic-checkout-js', $js, 'before');
    }

    /**
     * Output the config as a direct inline <script> tag instead of via
     * wp_add_inline_script. Used on pi_complete pages where the React JS
     * bundle isn't enqueued but the config is still needed.
     */
    private static function inject_config_inline(): void
    {
        $user_current_template = 'standard-planning';
        $user_name = '';
        $user_email = '';

        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $business_info = get_user_meta($user_id, '_pi_business_info', true);
            if (is_array($business_info) && !empty($business_info['default_template'])) {
                $user_current_template = $business_info['default_template'];
            }
            $user = wp_get_current_user();
            $user_name = $user->display_name;
            $user_email = $user->user_email;
        }

        $gateway = get_option('pmpro_gateway', 'stripe');
        $level_id = intval(get_option(PIC_OPTION_LEVEL_ID, 0));

        $checkout_url = '';
        if (function_exists('pmpro_url')) {
            $checkout_url = pmpro_url('checkout');
        }
        if (empty($checkout_url)) {
            $checkout_url = home_url('/membership-checkout/');
        }

        $config = [
            'apiBase' => esc_url_raw(rest_url(PIC_REST_NAMESPACE)),
            'nonce' => wp_create_nonce('wp_rest'),
            'checkoutUrl' => esc_url_raw($checkout_url),
            'checkoutNonce' => function_exists('wp_create_nonce') ? wp_create_nonce('pmpro_checkout_nonce') : '',
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'isLoggedIn' => is_user_logged_in(),
            'userId' => get_current_user_id(),
            'userName' => $user_name,
            'userEmail' => $user_email,
            'userCurrentTemplate' => $user_current_template,
            'unitPrice' => PIC_UNIT_PRICE,
            'minSelection' => PIC_MIN_SELECTION,
            'levelId' => $level_id,
            'gateway' => $gateway,
            'requireBilling' => true,
            'strings' => [],
        ];

        echo '<script>window.PlanningIndexCheckout = ' . wp_json_encode($config) . ';</script>' . "\n";
    }

    public static function add_module_type($tag, $handle, $src): string
    {
        if ($handle === 'pic-checkout-js') {
            return '<script type="module" src="' . esc_url($src) . '"></script>';
        }
        return $tag;
    }
}
