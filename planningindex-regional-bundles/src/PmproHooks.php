<?php

if (!defined('ABSPATH')) {
    exit;
}

class PIRB_PmproHooks
{
    public static function init(): void
    {
        add_filter('pmpro_registration_checks', [__CLASS__, 'registration_checks'], 10, 1);
        add_filter('pmpro_checkout_level', [__CLASS__, 'checkout_level_price'], 10, 1);
        add_action('pmpro_checkout_before_processing', [__CLASS__, 'before_processing'], 10);
        add_action('pmpro_checkout_before_payment', [__CLASS__, 'before_payment'], 10, 1);
        add_action('pmpro_after_checkout', [__CLASS__, 'after_checkout'], 10, 2);
        add_filter('pmpro_stripe_create_subscription_array', [__CLASS__, 'stripe_subscription_array'], 10, 2);
        add_filter('pmpro_stripe_payment_intent_amount', [__CLASS__, 'stripe_payment_intent_amount'], 10, 2);
        add_filter('pmpro_stripe_create_payment_intent_array', [__CLASS__, 'stripe_payment_intent_array'], 10, 2);
        add_action('pmpro_checkout_preheader', [__CLASS__, 'inject_hidden_fields'], 10);
        add_action('pmpro_checkout_preheader', [__CLASS__, 'prepopulate_billing'], 10);
        add_action('pmpro_checkout_preheader', [__CLASS__, 'restore_session'], 10);
        add_action('pmpro_checkout_preheader', [__CLASS__, 'load_custom_template'], 10);
        add_filter('pmpro_checkout_skip_account_fields', [__CLASS__, 'skip_account_fields_for_logged_in'], 10, 1);
        add_action('template_redirect', [__CLASS__, 'handle_stripe_success'], 10);
    }

    public static function should_use_settings(int $user_id): bool
    {
        if ($user_id <= 0) {
            return false;
        }
        $bi = get_user_meta($user_id, '_pi_business_info', true);
        return is_array($bi) && isset($bi['settings_updated_at']) && !empty($bi['settings_updated_at']);
    }

    private static function get_price_from_request(): float
    {
        $user_id = get_current_user_id();
        if ($user_id > 0) {
            $saved = floatval(get_user_meta($user_id, PIRB_META_PRICE, true));
            if ($saved > 0) {
                return $saved;
            }
        }

        if (isset($_REQUEST['pmpc_calculated_price'])) {
            return floatval($_REQUEST['pmpc_calculated_price']);
        }

        if (!session_id()) {
            session_start();
        }
        if (isset($_SESSION[PIRB_SESSION_KEY]['price'])) {
            return floatval($_SESSION[PIRB_SESSION_KEY]['price']);
        }

        return 0.0;
    }

    private static function is_regional_bundles_checkout(): bool
    {
        $configured_level = intval(get_option(PIRB_OPTION_LEVEL_ID, 0));

        $request_level = 0;
        if (isset($_REQUEST['pmpro_level'])) {
            $request_level = intval($_REQUEST['pmpro_level']);
        } elseif (isset($_REQUEST['level'])) {
            $request_level = intval($_REQUEST['level']);
        } elseif (isset($_GET['pmpro_level'])) {
            $request_level = intval($_GET['pmpro_level']);
        }

        if ($request_level > 0) {
            if ($configured_level > 0 && $request_level === $configured_level) {
                return true;
            }
            if ($request_level === 59) {
                return true;
            }
        }

        global $pmpro_level;
        if (is_object($pmpro_level) && isset($pmpro_level->id)) {
            $level_id = intval($pmpro_level->id);
            if ($configured_level > 0 && $level_id === $configured_level) {
                return true;
            }
            if ($level_id === 59) {
                return true;
            }
        }

        return false;
    }

    public static function registration_checks($ok)
    {
        if (!self::is_regional_bundles_checkout()) {
            return $ok;
        }

        $councils = isset($_REQUEST['pmpc_councils']) ? (array) $_REQUEST['pmpc_councils'] : [];
        if (empty($councils)) {
            if (!session_id()) {
                session_start();
            }
            $councils = isset($_SESSION[PIRB_SESSION_KEY]['councils']) ? (array) $_SESSION[PIRB_SESSION_KEY]['councils'] : [];
        }

        if (empty($councils)) {
            pmpro_setMessage('Please select a regional bundle to continue.', 'pmpro_error');
            return false;
        }

        return $ok;
    }

    public static function checkout_level_price($level)
    {
        if (!self::is_regional_bundles_checkout()) {
            return $level;
        }

        $price = self::get_price_from_request();
        if ($price > 0) {
            $level->initial_payment = $price;
            $level->billing_amount = $price;
        }

        return $level;
    }

    public static function before_processing(): void
    {
        if (!self::is_regional_bundles_checkout()) {
            return;
        }

        $price = self::get_price_from_request();
        if ($price > 0) {
            $_REQUEST['initial_payment'] = $price;
            $_REQUEST['amount'] = $price;
            $_REQUEST['payment_amount'] = $price;
        }
    }

    public static function before_payment($morder): void
    {
        if (!self::is_regional_bundles_checkout()) {
            return;
        }

        $price = self::get_price_from_request();
        if ($price <= 0) {
            return;
        }

        $morder->initial_payment = $price;
        $morder->payment_amount = $price;
        $morder->subtotal = $price;
        $morder->total = $price;
        $morder->billing_amount = $price;

        if (isset($morder->membership_level)) {
            $morder->membership_level->initial_payment = $price;
            $morder->membership_level->billing_amount = $price;
        }
    }

    public static function after_checkout($user_id, $morder): void
    {
        if (!self::is_regional_bundles_checkout()) {
            return;
        }

        if (!session_id()) {
            session_start();
        }

        $session = isset($_SESSION[PIRB_SESSION_KEY]) ? (array) $_SESSION[PIRB_SESSION_KEY] : [];

        $councils = isset($_REQUEST['pmpc_councils']) ? (array) $_REQUEST['pmpc_councils'] : [];
        if (empty($councils) && isset($session['councils'])) {
            $councils = (array) $session['councils'];
        }

        $price = self::get_price_from_request();
        $template = isset($_REQUEST['pmpc_default_template']) ? sanitize_text_field($_REQUEST['pmpc_default_template']) : '';
        if (empty($template) && isset($session['template'])) {
            $template = $session['template'];
        }

        if (!empty($councils)) {
            update_user_meta($user_id, PIRB_META_KEY, array_map('sanitize_text_field', $councils));
            update_user_meta($user_id, PIRB_META_ALLOWED, array_map('sanitize_text_field', $councils));
        }

        if ($price > 0) {
            update_user_meta($user_id, PIRB_META_PRICE, $price);
        }

        if (!empty($template)) {
            update_user_meta($user_id, PIRB_META_TEMPLATE, $template);

            $bi = get_user_meta($user_id, '_pi_business_info', true);
            if (!is_array($bi) || !isset($bi['settings_updated_at'])) {
                if (!is_array($bi)) {
                    $bi = [];
                }
                $bi['template'] = $template;
                update_user_meta($user_id, '_pi_business_info', $bi);
            }
        }

        $business_fields = [
            'pmpc_company_name'    => 'company_name',
            'pmpc_business_email'  => 'email',
            'pmpc_business_phone'  => 'phone',
            'pmpc_company_address' => 'company_address',
            'pmpc_website'        => 'website',
            'pmpc_vat_number'     => 'vat_number',
        ];

        $pirb_business = [];
        $pi_business = get_user_meta($user_id, '_pi_business_info', true);
        $has_settings = is_array($pi_business) && isset($pi_business['settings_updated_at']);

        foreach ($business_fields as $request_key => $meta_key) {
            $value = isset($_REQUEST[$request_key]) ? sanitize_text_field($_REQUEST[$request_key]) : '';
            if (empty($value) && isset($session['business'][$request_key])) {
                $value = sanitize_text_field($session['business'][$request_key]);
            }
            if (!empty($value)) {
                $pirb_business[$meta_key] = $value;
            }
        }

        if (!empty($pirb_business)) {
            update_user_meta($user_id, PIRB_META_BUSINESS, $pirb_business);

            if (!$has_settings) {
                if (!is_array($pi_business)) {
                    $pi_business = [];
                }
                foreach ($pirb_business as $k => $v) {
                    $pi_business[$k] = $v;
                }
                $pi_business['source'] = 'checkout';
                update_user_meta($user_id, '_pi_business_info', $pi_business);
            }
        }

        $order_note = [
            'region'   => $session['region'] ?? '',
            'councils' => $councils,
            'price'    => $price,
            'template' => $template,
        ];
        if (isset($morder) && is_object($morder)) {
            $morder->notes = 'RegionalBundlesSelected: ' . wp_json_encode($order_note);
        }
    }

    public static function stripe_subscription_array($params, $order)
    {
        if (!self::is_regional_bundles_checkout()) {
            return $params;
        }

        $price = self::get_price_from_request();
        if ($price > 0 && isset($params['items'][0]['price_data']['unit_amount'])) {
            $params['items'][0]['price_data']['unit_amount'] = intval($price * 100);
        }

        return $params;
    }

    public static function stripe_payment_intent_amount($amount, $order)
    {
        if (!self::is_regional_bundles_checkout()) {
            return $amount;
        }

        $price = self::get_price_from_request();
        if ($price > 0) {
            return intval($price * 100);
        }

        return $amount;
    }

    public static function stripe_payment_intent_array($intent_array, $order)
    {
        if (!self::is_regional_bundles_checkout()) {
            return $intent_array;
        }

        $price = self::get_price_from_request();
        if ($price > 0 && isset($intent_array['amount'])) {
            $intent_array['amount'] = intval($price * 100);
        }

        return $intent_array;
    }

    public static function inject_hidden_fields(): void
    {
        if (!self::is_regional_bundles_checkout()) {
            return;
        }

        if (!session_id()) {
            session_start();
        }

        $session = isset($_SESSION[PIRB_SESSION_KEY]) ? (array) $_SESSION[PIRB_SESSION_KEY] : [];

        $councils = isset($_REQUEST['pmpc_councils']) ? (array) $_REQUEST['pmpc_councils'] : [];
        if (empty($councils) && isset($session['councils'])) {
            $councils = (array) $session['councils'];
        }

        $price = isset($_REQUEST['pmpc_calculated_price']) ? floatval($_REQUEST['pmpc_calculated_price']) : 0.0;
        if ($price <= 0 && isset($session['price'])) {
            $price = floatval($session['price']);
        }

        $template = isset($_REQUEST['pmpc_default_template']) ? sanitize_text_field($_REQUEST['pmpc_default_template']) : '';
        if (empty($template) && isset($session['template'])) {
            $template = $session['template'];
        }

        echo "\n<!-- Regional Bundles hidden fields -->\n";
        foreach ($councils as $council) {
            echo '<input type="hidden" name="pmpc_councils[]" value="' . esc_attr(sanitize_text_field($council)) . '" />' . "\n";
        }
        if ($price > 0) {
            echo '<input type="hidden" name="pmpc_calculated_price" value="' . esc_attr($price) . '" />' . "\n";
        }
        if (!empty($template)) {
            echo '<input type="hidden" name="pmpc_default_template" value="' . esc_attr($template) . '" />' . "\n";
        }
    }

    public static function prepopulate_billing($morder = null): void
    {
        if (!self::is_regional_bundles_checkout()) {
            return;
        }

        if (!session_id()) {
            session_start();
        }

        $session = isset($_SESSION[PIRB_SESSION_KEY]) ? (array) $_SESSION[PIRB_SESSION_KEY] : [];
        $business = isset($session['business']) && is_array($session['business']) ? $session['business'] : [];

        if ($morder && is_object($morder) && isset($morder->billing)) {
            if (!empty($business['pirb_business_phone'])) {
                $morder->billing->phone = sanitize_text_field($business['pirb_business_phone']);
            }
            if (!empty($business['pirb_company_address'])) {
                $morder->billing->address1 = sanitize_text_field($business['pirb_company_address']);
            }
            if (!empty($business['pirb_business_email'])) {
                $morder->billing->email = sanitize_email($business['pirb_business_email']);
                $morder->Email = sanitize_email($business['pirb_business_email']);
            }
        }

        if (!is_user_logged_in() && !empty($session['email'])) {
            $_REQUEST['bemail'] = sanitize_email($session['email']);
            $_REQUEST['bconfirmemail'] = sanitize_email($session['email']);
            if (!empty($session['username'])) {
                $_REQUEST['username'] = sanitize_text_field($session['username']);
            }
            if (!empty($session['password'])) {
                $_REQUEST['password'] = $session['password'];
                $_REQUEST['password2'] = $session['password'];
            }
        }
    }

    public static function restore_session(): void
    {
        if (!self::is_regional_bundles_checkout()) {
            return;
        }

        if (!session_id()) {
            session_start();
        }

        $session = isset($_SESSION[PIRB_SESSION_KEY]) ? (array) $_SESSION[PIRB_SESSION_KEY] : [];

        $user_id = get_current_user_id();
        if ($user_id > 0 && self::should_use_settings($user_id)) {
            unset($session['template'], $session['business']);
        }

        if (empty($session) && !empty($_POST)) {
            $session = [];
            if (isset($_POST['pmpc_councils'])) {
                $session['councils'] = (array) $_POST['pmpc_councils'];
            }
            if (isset($_POST['pmpc_calculated_price'])) {
                $session['price'] = floatval($_POST['pmpc_calculated_price']);
            }
            if (isset($_POST['pmpc_default_template'])) {
                $session['template'] = sanitize_text_field($_POST['pmpc_default_template']);
            }
            foreach (['pmpc_company_name', 'pmpc_business_email', 'pmpc_business_phone', 'pmpc_company_address', 'pmpc_website', 'pmpc_vat_number'] as $field) {
                if (isset($_POST[$field])) {
                    $session['business'][$field] = sanitize_text_field($_POST[$field]);
                }
            }
            if (isset($_POST['username'])) {
                $session['username'] = sanitize_text_field($_POST['username']);
            }
            if (isset($_POST['password'])) {
                $session['password'] = $_POST['password'];
            }
            if (isset($_POST['bemail'])) {
                $session['email'] = sanitize_email($_POST['bemail']);
            }
        }

        if (empty($session)) {
            return;
        }

        if (isset($session['councils']) && is_array($session['councils']) && !isset($_REQUEST['pmpc_councils'])) {
            $_REQUEST['pmpc_councils'] = $session['councils'];
        }
        if (isset($session['price']) && !isset($_REQUEST['pmpc_calculated_price'])) {
            $_REQUEST['pmpc_calculated_price'] = $session['price'];
        }
        if (isset($session['template']) && !isset($_REQUEST['pmpc_default_template'])) {
            $_REQUEST['pmpc_default_template'] = $session['template'];
        }
        if (isset($session['business']) && is_array($session['business'])) {
            $field_map = [
                'pirb_company_name'    => 'pmpc_company_name',
                'pirb_business_email'  => 'pmpc_business_email',
                'pirb_business_phone'  => 'pmpc_business_phone',
                'pirb_company_address' => 'pmpc_company_address',
                'pirb_website'         => 'pmpc_website',
                'pirb_vat_number'      => 'pmpc_vat_number',
            ];
            foreach ($field_map as $session_key => $request_key) {
                if (isset($session['business'][$session_key]) && !isset($_REQUEST[$request_key])) {
                    $_REQUEST[$request_key] = $session['business'][$session_key];
                }
            }
        }

        if (!is_user_logged_in()) {
            if (isset($session['username']) && !isset($_REQUEST['username'])) {
                $_REQUEST['username'] = $session['username'];
            }
            if (isset($session['password']) && !isset($_REQUEST['password'])) {
                $_REQUEST['password'] = $session['password'];
                $_REQUEST['password2'] = $session['password'];
            }
            if (isset($session['email']) && !isset($_REQUEST['bemail'])) {
                $_REQUEST['bemail'] = $session['email'];
                $_REQUEST['bconfirmemail'] = $session['email'];
            }
        }

        $is_final_post = isset($_POST['submit-checkout']) || isset($_POST['pmpro_submit']) || isset($_REQUEST['javascriptok']);
        if ($is_final_post) {
            unset($_SESSION[PIRB_SESSION_KEY]);
        }
    }

    public static function load_custom_template(): void
    {
        if (!isset($_REQUEST['pirb_complete']) || intval($_REQUEST['pirb_complete']) !== 1) {
            return;
        }

        $custom = get_stylesheet_directory() . '/pages/checkout.php';
        if (file_exists($custom)) {
            require $custom;
            exit;
        }

        $plugin_template = PIRB_PLUGIN_DIR . 'pages/checkout.php';
        if (file_exists($plugin_template)) {
            require $plugin_template;
            exit;
        }
    }

    public static function skip_account_fields_for_logged_in($skip)
    {
        if (is_user_logged_in() && self::is_regional_bundles_checkout()) {
            return true;
        }
        return $skip;
    }

    public static function handle_stripe_success(): void
    {
        if (!isset($_GET['pirb_stripe_success']) || intval($_GET['pirb_stripe_success']) !== 1) {
            return;
        }

        $session_id = isset($_GET['session_id']) ? sanitize_text_field($_GET['session_id']) : '';
        if (empty($session_id)) {
            return;
        }

        $session_meta = null;
        $transient_key = '';

        if (isset($_GET['client_reference_id'])) {
            $transient_key = sanitize_text_field($_GET['client_reference_id']);
        }

        if (!empty($transient_key)) {
            $session_meta = get_transient($transient_key);
        }

        $secret_key = '';
        if (function_exists('pmpro_getOption')) {
            $secret_key = pmpro_getOption('stripe_secretkey');
        }
        if (empty($secret_key)) {
            $secret_key = get_option('pmpro_stripe_secretkey', '');
        }
        if (empty($secret_key)) {
            $secret_key = get_option('stripe_secretkey', '');
        }
        if (empty($secret_key)) {
            global $wpdb;
            $row = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE %s AND option_value LIKE %s LIMIT 1",
                    '%stripe%secret%',
                    '%sk_%'
                )
            );
            if (!empty($row) && is_string($row) && strpos($row, 'sk_') === 0) {
                $secret_key = $row;
            }
        }

        if (empty($secret_key)) {
            wp_safe_redirect(home_url('/membership-account/'));
            exit;
        }

        if (empty($session_meta) && !empty($session_id)) {
            $response = wp_remote_get('https://api.stripe.com/v1/checkout/sessions/' . $session_id, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $secret_key,
                ],
                'timeout' => 30,
            ]);

            if (!is_wp_error($response)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (is_array($body)) {
                    $meta = isset($body['metadata']) ? $body['metadata'] : [];
                    $transient_key = $meta['pirb_session_key'] ?? ($body['client_reference_id'] ?? '');
                    if (!empty($transient_key)) {
                        $session_meta = get_transient($transient_key);
                    }
                    if (empty($session_meta)) {
                        $session_meta = [
                            'region'   => $meta['pirb_region'] ?? '',
                            'level_id' => intval($meta['pirb_level_id'] ?? 59),
                            'councils' => [],
                            'template' => '',
                            'business' => [],
                            'account'  => [],
                            'price'    => 0,
                            'logged_in' => false,
                            'user_id'  => 0,
                        ];
                    }
                }
            }
        }

        if (empty($session_meta) || !is_array($session_meta)) {
            wp_safe_redirect(home_url('/membership-account/'));
            exit;
        }

        $region    = $session_meta['region'] ?? '';
        $councils  = $session_meta['councils'] ?? [];
        $template  = $session_meta['template'] ?? '';
        $business  = $session_meta['business'] ?? [];
        $account   = $session_meta['account'] ?? [];
        $level_id  = intval($session_meta['level_id'] ?? 59);
        $price     = floatval($session_meta['price'] ?? 0);
        $logged_in = $session_meta['logged_in'] ?? false;
        $existing_user_id = intval($session_meta['user_id'] ?? 0);

        $user_id = 0;

        if ($logged_in && $existing_user_id > 0) {
            $user_id = $existing_user_id;
        } else {
            $email = $account['email'] ?? '';
            $username = $account['username'] ?? '';
            $password = $account['password'] ?? '';

            if (!empty($email)) {
                $existing = get_user_by('email', $email);
                if ($existing) {
                    $user_id = $existing->ID;
                }
            }

            if ($user_id === 0 && !empty($username) && !empty($email) && !empty($password)) {
                $user_id = wp_create_user($username, $password, $email);
                if (is_wp_error($user_id)) {
                    $user_id = 0;
                }
            }
        }

        if ($user_id > 0) {
            if (!empty($councils)) {
                update_user_meta($user_id, PIRB_META_KEY, array_map('sanitize_text_field', $councils));
                update_user_meta($user_id, PIRB_META_ALLOWED, array_map('sanitize_text_field', $councils));
            }
            if (!empty($template)) {
                update_user_meta($user_id, PIRB_META_TEMPLATE, $template);
            }
            if ($price > 0) {
                update_user_meta($user_id, PIRB_META_PRICE, $price);
            }

            $pi_business = [];
            if (!empty($business)) {
                $field_map = [
                    'pirb_company_name'    => 'company_name',
                    'pirb_business_email'  => 'email',
                    'pirb_business_phone'  => 'phone',
                    'pirb_company_address' => 'company_address',
                    'pirb_website'         => 'website',
                    'pirb_vat_number'      => 'vat_number',
                ];
                foreach ($field_map as $bk => $pk) {
                    if (isset($business[$bk]) && !empty($business[$bk])) {
                        $pi_business[$pk] = $business[$bk];
                    }
                }
                $pi_business['source'] = 'checkout';
                update_user_meta($user_id, '_pi_business_info', $pi_business);
            }

            update_user_meta($user_id, '_pi_selected_councils', $councils);
            update_user_meta($user_id, '_pi_selected_template', $template);
            update_user_meta($user_id, '_pi_monthly_cost', $price);

            if (function_exists('pmpro_changeMembershipLevel')) {
                pmpro_changeMembershipLevel($user_id, $level_id);
            }

            if (class_exists('MemberOrder')) {
                $morder = new MemberOrder();
                $morder->user_id = $user_id;
                $morder->membership_id = $level_id;
                $morder->InitialPayment = $price;
                $morder->PaymentAmount = $price;
                $morder->BillingPeriod = 'Month';
                $morder->BillingFrequency = 1;
                $morder->gateway = 'stripe';
                $morder->status = 'success';
                $morder->saveOrder();

                if (!empty($session_id)) {
                    update_user_meta($user_id, 'pmpro_stripe_customer_id', $session_id);
                    $morder->subscription_transaction_id = $session_id;
                }
            }

            do_action('pmpro_after_checkout', $user_id);

            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id, true);
        }

        if (!empty($transient_key)) {
            delete_transient($transient_key);
        }

        if (!session_id()) {
            session_start();
        }
        unset($_SESSION[PIRB_SESSION_KEY]);

        wp_safe_redirect(home_url('/membership-account/'));
        exit;
    }
}
