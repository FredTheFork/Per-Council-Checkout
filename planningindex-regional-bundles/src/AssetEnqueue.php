<?php

if (!defined('ABSPATH')) {
    exit;
}

class PIRB_AssetEnqueue
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
        if (!PIRB_CheckoutDetection::is_checkout_page()) {
            return;
        }

        $manifest = self::read_manifest();
        $js_url = '';
        $css_url = '';

        if ($manifest && isset($manifest['src/main.tsx']['file'])) {
            $js_url = PIRB_PLUGIN_URL . 'build/' . $manifest['src/main.tsx']['file'];
            if (isset($manifest['src/main.tsx']['css']) && is_array($manifest['src/main.tsx']['css'])) {
                $css_url = PIRB_PLUGIN_URL . 'build/' . $manifest['src/main.tsx']['css'][0];
            }
        }

        if (empty($js_url)) {
            $js_files = glob(PIRB_PLUGIN_DIR . 'build/assets/*.js');
            if (!empty($js_files)) {
                $js_url = PIRB_PLUGIN_URL . 'build/assets/' . basename($js_files[0]);
            }
        }

        if (empty($css_url)) {
            $css_files = glob(PIRB_PLUGIN_DIR . 'build/assets/*.css');
            if (!empty($css_files)) {
                $css_url = PIRB_PLUGIN_URL . 'build/assets/' . basename($css_files[0]);
            }
        }

        if (!empty($js_url)) {
            wp_enqueue_script('pirb-checkout-js', $js_url, [], PIRB_VERSION, true);
        }

        if (!empty($css_url)) {
            wp_enqueue_style('pirb-checkout-css', $css_url, [], PIRB_VERSION);
        }
    }

    public static function read_manifest(): ?array
    {
        $manifest_path = PIRB_PLUGIN_DIR . 'build/.vite/manifest.json';
        if (file_exists($manifest_path)) {
            $raw = file_get_contents($manifest_path);
            $data = json_decode($raw, true);
            if (is_array($data)) {
                return $data;
            }
        }

        $alt_path = PIRB_PLUGIN_DIR . 'build/manifest.json';
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
        $level_id = intval(get_option(PIRB_OPTION_LEVEL_ID, 59));

        $regions = [];
        foreach (PIRB_RegionData::all() as $name => $bundle) {
            $regions[] = [
                'id'       => $name,
                'name'     => $name,
                'price'    => floatval($bundle['price'] ?? 0),
                'councils' => $bundle['councils'] ?? [],
            ];
        }

        $user_id = get_current_user_id();
        $is_logged_in = $user_id > 0;

        $user_name = '';
        $user_email = '';
        $user_current_template = '';

        if ($is_logged_in) {
            $user = wp_get_current_user();
            $user_name = $user->display_name ?: $user->user_login;
            $user_email = $user->user_email;
            $user_current_template = get_user_meta($user_id, PIRB_META_TEMPLATE, true);
            if (empty($user_current_template)) {
                $bi = get_user_meta($user_id, '_pi_business_info', true);
                if (is_array($bi) && isset($bi['template'])) {
                    $user_current_template = $bi['template'];
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
        if ($level_id > 0 && function_exists('pmpro_getLevel')) {
            $level = pmpro_getLevel($level_id);
            if ($level && isset($level->initial_payment) && floatval($level->initial_payment) == 0 && (!isset($level->billing_amount) || floatval($level->billing_amount) == 0)) {
                $require_billing = false;
            }
        }

        return [
            'apiBase'            => esc_url_raw(rest_url(PIRB_REST_NAMESPACE)),
            'nonce'              => wp_create_nonce('wp_rest'),
            'checkoutUrl'        => esc_url_raw(home_url('/membership-checkout/')),
            'checkoutNonce'      => wp_create_nonce('pirb_checkout'),
            'ajaxUrl'            => esc_url_raw(admin_url('admin-ajax.php')),
            'isLoggedIn'         => $is_logged_in,
            'userId'             => $user_id,
            'userName'           => $user_name,
            'userEmail'          => $user_email,
            'userCurrentTemplate'=> $user_current_template,
            'levelId'            => $level_id,
            'gateway'            => $gateway,
            'requireBilling'     => $require_billing,
            'totalSteps'         => PIRB_TOTAL_STEPS,
            'regions'            => $regions,
            'strings'            => [
                'productType' => 'Regional Bundle',
            ],
        ];
    }

    public static function nuclear_inject_config(): void
    {
        if (!PIRB_CheckoutDetection::is_checkout_page()) {
            return;
        }

        $config = self::build_config();

        echo '<script id="pirb-config-nuclear">' . "\n";
        echo 'window.PlanningIndexRegionalBundles = ' . wp_json_encode($config) . ';' . "\n";
        echo '</script>' . "\n";
    }

    public static function add_module_type(string $tag, string $handle, string $src): string
    {
        if ($handle === 'pirb-checkout-js' && !empty($src)) {
            return '<script type="module" src="' . esc_url($src) . '"></script>';
        }
        return $tag;
    }
}
