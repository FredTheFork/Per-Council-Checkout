<?php

if (!defined('ABSPATH')) {
    exit;
}

class PIE_AssetEnqueue
{
    public static function init(): void
    {
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('wp_head', [__CLASS__, 'nuclear_inject_config'], 1);
        add_action('wp_head', [__CLASS__, 'nuclear_inject_config'], 999);
        add_filter('script_loader_tag', [__CLASS__, 'add_module_type'], 10, 3);
    }

    public static function enqueue_assets(): void
    {
        $is_wizard_page = PIE_CheckoutDetection::is_checkout_page();
        $is_complete_page = !empty($_REQUEST['pie_complete']) && self::is_enterprise_level();

        if (!$is_wizard_page && !$is_complete_page) {
            return;
        }

        $manifest = self::read_manifest();
        $js_url = '';
        $css_url = '';

        if ($manifest && isset($manifest['index.html']['file'])) {
            $js_url = PIE_PLUGIN_URL . 'build/' . $manifest['index.html']['file'];
            if (isset($manifest['index.html']['css']) && is_array($manifest['index.html']['css'])) {
                $css_url = PIE_PLUGIN_URL . 'build/' . $manifest['index.html']['css'][0];
            }
        }

        if (empty($js_url)) {
            $js_files = glob(PIE_PLUGIN_DIR . 'build/assets/*.js');
            if (!empty($js_files)) {
                $js_url = PIE_PLUGIN_URL . 'build/assets/' . basename($js_files[0]);
            }
        }

        if (empty($css_url)) {
            $css_files = glob(PIE_PLUGIN_DIR . 'build/assets/*.css');
            if (!empty($css_files)) {
                $css_url = PIE_PLUGIN_URL . 'build/assets/' . basename($css_files[0]);
            }
        }

        if ($is_wizard_page && !empty($js_url)) {
            wp_enqueue_script('pie-checkout-js', $js_url, [], PIE_VERSION, true);
        }

        if (!empty($css_url)) {
            wp_enqueue_style('pie-checkout-css', $css_url, [], PIE_VERSION);
        }
    }

    private static function is_enterprise_level(): bool
    {
        $configured_level = intval(get_option(PIE_OPTION_LEVEL_ID, 0));
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

    public static function read_manifest(): ?array
    {
        $manifest_path = PIE_PLUGIN_DIR . 'build/.vite/manifest.json';
        if (file_exists($manifest_path)) {
            $raw = file_get_contents($manifest_path);
            $data = json_decode($raw, true);
            if (is_array($data)) {
                return $data;
            }
        }

        $alt_path = PIE_PLUGIN_DIR . 'build/manifest.json';
        if (file_exists($alt_path)) {
            $raw = file_get_contents($alt_path);
            $data = json_decode($raw, true);
            if (is_array($data)) {
                return $data;
            }
        }

        return null;
    }

    public static function build_config(): array
    {
        $level_id = intval(get_option(PIE_OPTION_LEVEL_ID, 60));

        $user_id = get_current_user_id();
        $is_logged_in = $user_id > 0;

        $user_name = '';
        $user_email = '';
        $user_current_template = '';

        if ($is_logged_in) {
            $user = wp_get_current_user();
            $user_name = $user->display_name ?: $user->user_login;
            $user_email = $user->user_email;
            $user_current_template = get_user_meta($user_id, PIE_META_TEMPLATE, true);
            if (empty($user_current_template)) {
                $bi = get_user_meta($user_id, '_pi_business_info', true);
                if (is_array($bi) && isset($bi['default_template'])) {
                    $user_current_template = $bi['default_template'];
                }
            }
        }

        $gateway = 'stripe';
        if (function_exists('pmpro_getOption')) {
            $gw = pmpro_getOption('gateway');
            if (!empty($gw)) {
                $gateway = $gw;
            }
        }

        $require_billing = true;
        $enterprise_price = PIE_EnterpriseData::get_enterprise_price();
        if ($level_id > 0 && function_exists('pmpro_getLevel')) {
            $level = pmpro_getLevel($level_id);
            if ($level && isset($level->initial_payment) && floatval($level->initial_payment) == 0 && (!isset($level->billing_amount) || floatval($level->billing_amount) == 0)) {
                $require_billing = false;
            }
        }

        return [
            'apiBase'            => esc_url_raw(rest_url(PIE_REST_NAMESPACE)),
            'nonce'              => wp_create_nonce('wp_rest'),
            'checkoutUrl'        => esc_url_raw(home_url('/membership-checkout/')),
            'checkoutNonce'      => wp_create_nonce('pie_checkout'),
            'ajaxUrl'            => esc_url_raw(admin_url('admin-ajax.php')),
            'isLoggedIn'         => $is_logged_in,
            'userId'             => $user_id,
            'userName'           => $user_name,
            'userEmail'          => $user_email,
            'userCurrentTemplate'=> $user_current_template,
            'levelId'            => $level_id,
            'gateway'            => $gateway,
            'requireBilling'     => $require_billing,
            'enterprisePrice'    => $enterprise_price,
            'totalSteps'         => PIE_TOTAL_STEPS,
            'strings'            => [
                'productType' => 'Enterprise Access',
            ],
        ];
    }

    public static function nuclear_inject_config(): void
    {
        $should_inject = false;

        if (PIE_CheckoutDetection::is_checkout_page()) {
            $should_inject = true;
        }

        if (!$should_inject && !empty($_REQUEST['pie_complete'])) {
            $should_inject = true;
        }

        if (!$should_inject && PIE_CheckoutDetection::has_checkout_shortcode()) {
            $should_inject = true;
        }

        if (!$should_inject && (isset($_REQUEST['level']) || isset($_REQUEST['pmpro_level']) || isset($_GET['pmpro_level']))) {
            $should_inject = true;
        }

        if (!$should_inject) {
            return;
        }

        $config = self::build_config();

        echo '<script id="pie-config-nuclear">' . "\n";
        echo 'window.PlanningIndexEnterpriseAccess = ' . wp_json_encode($config) . ';' . "\n";
        echo '</script>' . "\n";
    }

    public static function add_module_type(string $tag, string $handle, string $src): string
    {
        if ($handle === 'pie-checkout-js' && !empty($src)) {
            return '<script type="module" src="' . esc_url($src) . '"></script>';
        }
        return $tag;
    }
}
