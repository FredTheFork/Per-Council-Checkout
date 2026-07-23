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
        if (!PIC_CheckoutDetection::is_checkout_page()) {
            return;
        }

        $manifest = self::read_manifest();
        if ($manifest === null) {
            return;
        }

        $js_file = $manifest['js'] ?? null;
        $css_file = $manifest['css'] ?? null;

        if ($js_file && file_exists(PIC_PLUGIN_DIR . 'build/' . $js_file)) {
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

        if ($css_file && file_exists(PIC_PLUGIN_DIR . 'build/' . $css_file)) {
            $css_url = PIC_PLUGIN_URL . 'build/' . $css_file;
            $version = filemtime(PIC_PLUGIN_DIR . 'build/' . $css_file);

            wp_enqueue_style('pic-checkout-css', $css_url, [], $version);
        }
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

        $config = [
            'apiBase' => esc_url_raw(rest_url(PIC_REST_NAMESPACE)),
            'nonce' => wp_create_nonce('wp_rest'),
            'checkoutUrl' => function_exists('pmpro_url') ? pmpro_url('checkout') : '',
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

    public static function add_module_type($tag, $handle, $src): string
    {
        if ($handle === 'pic-checkout-js') {
            return '<script type="module" src="' . esc_url($src) . '"></script>';
        }
        return $tag;
    }
}
