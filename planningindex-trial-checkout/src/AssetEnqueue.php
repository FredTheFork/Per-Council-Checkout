<?php

if (!defined('ABSPATH')) {
    exit;
}

class PIT_AssetEnqueue
{
    public static function init(): void
    {
        add_action('wp_enqueue_scripts', [self::class, 'enqueue_assets']);
        add_filter('script_loader_tag', [self::class, 'add_module_type'], 10, 3);
        add_action('wp_head', [self::class, 'nuclear_inject_config'], 1);
    }

    public static function enqueue_assets(): void
    {
        $is_wizard_page = PIT_CheckoutDetection::is_checkout_page();
        $is_pi_complete = !empty($_REQUEST['pi_complete']) && self::is_trial_level();

        if (!$is_wizard_page && !$is_pi_complete) {
            return;
        }

        $manifest = self::read_manifest();
        if ($manifest === null) {
            return;
        }

        $js_file = $manifest['js'] ?? null;
        $css_file = $manifest['css'] ?? null;

        if ($is_wizard_page && $js_file && file_exists(PIT_PLUGIN_DIR . 'build/' . $js_file)) {
            $js_url = PIT_PLUGIN_URL . 'build/' . $js_file;
            $version = filemtime(PIT_PLUGIN_DIR . 'build/' . $js_file);

            wp_enqueue_script('pit-checkout-js', $js_url, [], $version, true);
            self::inject_config();
        }

        if ($css_file && file_exists(PIT_PLUGIN_DIR . 'build/' . $css_file)) {
            $css_url = PIT_PLUGIN_URL . 'build/' . $css_file;
            $version = filemtime(PIT_PLUGIN_DIR . 'build/' . $css_file);

            wp_enqueue_style('pit-checkout-css', $css_url, [], $version);
        }

        if ($is_pi_complete && !$is_wizard_page) {
            self::inject_config_inline();
        }
    }

    private static function is_trial_level(): bool
    {
        $configured_level = intval(get_option(PIT_OPTION_LEVEL_ID, 0));
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
        $manifest_paths = [
            PIT_PLUGIN_DIR . 'build/.vite/manifest.json',
            PIT_PLUGIN_DIR . 'build/manifest.json',
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

        $assets_dir = PIT_PLUGIN_DIR . 'build/assets/';
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
    }

    private static function build_config(): array
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
        $level_id = intval(get_option(PIT_OPTION_LEVEL_ID, 0));

        if ($level_id === 0) {
            if (isset($_REQUEST['pmpro_level'])) {
                $level_id = intval($_REQUEST['pmpro_level']);
            } elseif (isset($_REQUEST['level'])) {
                $level_id = intval($_REQUEST['level']);
            } elseif (isset($_GET['pmpro_level'])) {
                $level_id = intval($_GET['pmpro_level']);
            }
        }

        $checkout_url = '';
        if (function_exists('pmpro_url')) {
            $checkout_url = pmpro_url('checkout');
        }
        if (empty($checkout_url)) {
            $checkout_url = home_url('/membership-checkout/');
        }

        return [
            'apiBase' => esc_url_raw(rest_url(PIT_REST_NAMESPACE)),
            'nonce' => wp_create_nonce('wp_rest'),
            'checkoutUrl' => esc_url_raw($checkout_url),
            'checkoutNonce' => function_exists('wp_create_nonce') ? wp_create_nonce('pmpro_checkout_nonce') : '',
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'isLoggedIn' => is_user_logged_in(),
            'userId' => get_current_user_id(),
            'userName' => $user_name,
            'userEmail' => $user_email,
            'userCurrentTemplate' => $user_current_template,
            'unitPrice' => PIT_UNIT_PRICE,
            'minSelection' => PIT_MIN_SELECTION,
            'maxSelection' => PIT_MAX_SELECTION,
            'trialDays' => PIT_TRIAL_DAYS,
            'levelId' => $level_id,
            'gateway' => $gateway,
            'requireBilling' => false,
            'strings' => [
                'selectMinCouncils' => sprintf('Please select at least %d council to continue.', PIT_MIN_SELECTION),
                'selectMaxCouncils' => sprintf('You can select up to %d councils for your free trial.', PIT_MAX_SELECTION),
                'usernameRequired' => 'Please enter a username.',
                'passwordRequired' => 'Please enter a password with at least 8 characters.',
                'passwordMismatch' => 'Passwords do not match.',
                'emailRequired' => 'Please enter a valid email address.',
                'emailMismatch' => 'Email addresses do not match.',
                'processing' => 'Starting your free trial...',
                'continue' => 'Continue',
                'startTrial' => 'Start 2 Week Free Trial',
                'perMonth' => '/month',
                'loadingTemplates' => 'Loading templates...',
                'templateLoadError' => 'Unable to load templates. Using defaults.',
            ],
        ];
    }

    public static function nuclear_inject_config(): void
    {
        $should_inject = false;

        if (class_exists('PIT_CheckoutDetection') && PIT_CheckoutDetection::is_checkout_page()) {
            $should_inject = true;
        }

        if (!$should_inject && !empty($_REQUEST['pi_complete'])) {
            $should_inject = true;
        }

        if (!$should_inject && class_exists('PIT_CheckoutDetection') && PIT_CheckoutDetection::has_checkout_shortcode()) {
            $should_inject = true;
        }

        if (!$should_inject && (isset($_REQUEST['level']) || isset($_REQUEST['pmpro_level']) || isset($_GET['pmpro_level']))) {
            $should_inject = true;
        }

        if (!$should_inject) {
            return;
        }

        $config = self::build_config();
        echo '<script id="pit-config-nuclear">window.PlanningIndexTrialCheckout = ' . wp_json_encode($config) . ';</script>' . "\n";
    }

    private static function inject_config_inline(): void
    {
        $config = self::build_config();
        echo '<script id="pit-config-inline">window.PlanningIndexTrialCheckout = ' . wp_json_encode($config) . ';</script>' . "\n";
    }

    public static function add_module_type($tag, $handle, $src): string
    {
        if ($handle !== 'pit-checkout-js') {
            return $tag;
        }

        $inline_before = '';
        if (preg_match_all('#<script(?![^>]*\bsrc=)[^>]*>(.*?)</script>#s', $tag, $m)) {
            foreach ($m[0] as $inline_tag) {
                $inline_before .= $inline_tag . "\n";
            }
        }

        return $inline_before . '<script type="module" src="' . esc_url($src) . '"></script>';
    }
}
