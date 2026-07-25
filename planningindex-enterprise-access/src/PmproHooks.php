<?php

if (!defined('ABSPATH')) {
    exit;
}

class PIE_PmproHooks
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

    private static function get_enterprise_price(): float
    {
        return PIE_EnterpriseData::get_enterprise_price();
    }

    private static function is_enterprise_checkout(): bool
    {
        $configured_level = intval(get_option(PIE_OPTION_LEVEL_ID, 0));

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
            if ($request_level === 60) {
                return true;
            }
        }

        global $pmpro_level;
        if (is_object($pmpro_level) && isset($pmpro_level->id)) {
            $level_id = intval($pmpro_level->id);
            if ($configured_level > 0 && $level_id === $configured_level) {
                return true;
            }
            if ($level_id === 60) {
                return true;
            }
        }

        return false;
    }

    public static function registration_checks($ok)
    {
        if (!self::is_enterprise_checkout()) {
            return $ok;
        }

        // Enterprise always passes — no council/region selection to validate
        return $ok;
    }

    public static function checkout_level_price($level)
    {
        if (!self::is_enterprise_checkout()) {
            return $level;
        }

        $price = self::get_enterprise_price();
        if ($price > 0) {
            $level->initial_payment = $price;
            $level->billing_amount = $price;
        }

        return $level;
    }

    public static function before_processing(): void
    {
        if (!self::is_enterprise_checkout()) {
            return;
        }

        $price = self::get_enterprise_price();
        if ($price > 0) {
            $_REQUEST['initial_payment'] = $price;
            $_REQUEST['amount'] = $price;
            $_REQUEST['payment_amount'] = $price;
        }
    }

    public static function before_payment($morder): void
    {
        if (!self::is_enterprise_checkout()) {
            return;
        }

        $price = self::get_enterprise_price();
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
        if (!self::is_enterprise_checkout()) {
            return;
        }

        if (!session_id()) {
            session_start();
        }

        $session = isset($_SESSION[PIE_SESSION_KEY]) ? (array) $_SESSION[PIE_SESSION_KEY] : [];

        $template = isset($_REQUEST['pmpe_default_template']) ? sanitize_text_field($_REQUEST['pmpe_default_template']) : '';
        if (empty($template) && isset($session['template'])) {
            $template = $session['template'];
        }

        // Team seats
        $seats = isset($_REQUEST['pmpe_team_seats']) ? max(1, min(5, intval($_REQUEST['pmpe_team_seats']))) : 1;
        if ($seats === 1 && isset($session['team_seats'])) {
            $seats = max(1, min(5, intval($session['team_seats'])));
        }

        update_user_meta($user_id, PIE_META_TEAM_OWNER, 'yes');
        update_user_meta($user_id, PIE_META_TEAM_SEATS, $seats);
        update_user_meta($user_id, PIE_META_TEAM_MEMBERS, [$user_id]);

        // Grant full Enterprise access — all UK councils
        $councils = PIE_EnterpriseData::all_councils();
        update_user_meta($user_id, PIE_META_REGION_KEY, 'Enterprise Access');
        update_user_meta($user_id, PIE_META_ALLOWED_KEY, $councils);
        update_user_meta($user_id, PIE_META_SELECTED_KEY, $councils);

        // Settings precedence: if _pi_business_info has settings_updated_at, don't override
        $existing_info = get_user_meta($user_id, '_pi_business_info', true);
        $settings_saved = is_array($existing_info) && !empty($existing_info['settings_updated_at']);

        if (!$settings_saved) {
            if (!empty($template)) {
                update_user_meta($user_id, PIE_META_TEMPLATE, $template);

                $business = get_user_meta($user_id, '_pi_business_info', true) ?: [];
                if (!is_array($business)) {
                    $business = [];
                }
                $business['default_template'] = $template;
                $business['source'] = 'checkout';
                update_user_meta($user_id, '_pi_business_info', $business);
            }

            // Business info from checkout form
            $checkout_business_info = [];
            $business_fields = ['pmpe_company_name', 'pmpe_business_email', 'pmpe_business_phone', 'pmpe_company_address', 'pmpe_website', 'pmpe_vat_number'];

            foreach ($business_fields as $field) {
                if (!empty($_REQUEST[$field])) {
                    $checkout_business_info[$field] = sanitize_text_field($_REQUEST[$field]);
                } elseif (isset($session['business'][$field])) {
                    $checkout_business_info[$field] = sanitize_text_field($session['business'][$field]);
                }
            }

            if (!empty($checkout_business_info)) {
                update_user_meta($user_id, PIE_META_BUSINESS, $checkout_business_info);

                $business_info = get_user_meta($user_id, '_pi_business_info', true) ?: [];
                if (!is_array($business_info)) {
                    $business_info = [];
                }

                $field_map = [
                    'pmpe_company_name'    => 'company_name',
                    'pmpe_business_email'  => 'email',
                    'pmpe_business_phone'  => 'phone',
                    'pmpe_company_address' => 'company_address',
                    'pmpe_website'         => 'website',
                ];

                foreach ($field_map as $checkout_key => $settings_key) {
                    if (!empty($checkout_business_info[$checkout_key])) {
                        $business_info[$settings_key] = $checkout_business_info[$checkout_key];
                    }
                }

                $business_info['source'] = 'checkout';
                update_user_meta($user_id, '_pi_business_info', $business_info);
            }
        }

        // Order note
        if (is_object($morder)) {
            $summary = wp_json_encode([
                'region'   => 'Enterprise Access',
                'councils' => $councils,
                'template' => $template,
                'seats'    => $seats,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (isset($morder->notes)) {
                $morder->notes .= "\nEnterpriseBundle: {$summary}";
            } else {
                $morder->notes = "EnterpriseBundle: {$summary}";
            }

            if (method_exists($morder, 'save')) {
                $morder->save();
            }
        }

        // Clear session
        unset($_SESSION[PIE_SESSION_KEY]);

        error_log("[PIE] Enterprise checkout complete - User #{$user_id} granted {$seats} seats, " . count($councils) . " councils");
    }

    public static function stripe_subscription_array($params, $order)
    {
        if (!self::is_enterprise_checkout()) {
            return $params;
        }

        $price = self::get_enterprise_price();
        if ($price > 0 && isset($params['items'][0]['price_data']['unit_amount'])) {
            $params['items'][0]['price_data']['unit_amount'] = intval($price * 100);
        }

        return $params;
    }

    public static function stripe_payment_intent_amount($amount, $order)
    {
        if (!self::is_enterprise_checkout()) {
            return $amount;
        }

        $price = self::get_enterprise_price();
        if ($price > 0) {
            return intval($price * 100);
        }

        return $amount;
    }

    public static function stripe_payment_intent_array($intent_array, $order)
    {
        if (!self::is_enterprise_checkout()) {
            return $intent_array;
        }

        $price = self::get_enterprise_price();
        if ($price > 0 && isset($intent_array['amount'])) {
            $intent_array['amount'] = intval($price * 100);
        }

        return $intent_array;
    }

    public static function inject_hidden_fields(): void
    {
        if (!self::is_enterprise_checkout()) {
            return;
        }

        if (!session_id()) {
            session_start();
        }

        $session = isset($_SESSION[PIE_SESSION_KEY]) ? (array) $_SESSION[PIE_SESSION_KEY] : [];

        $template = isset($_REQUEST['pmpe_default_template']) ? sanitize_text_field($_REQUEST['pmpe_default_template']) : '';
        if (empty($template) && isset($session['template'])) {
            $template = $session['template'];
        }

        $seats = isset($_REQUEST['pmpe_team_seats']) ? intval($_REQUEST['pmpe_team_seats']) : 0;
        if ($seats === 0 && isset($session['team_seats'])) {
            $seats = intval($session['team_seats']);
        }

        echo "\n<!-- Enterprise hidden fields -->\n";
        if (!empty($template)) {
            echo '<input type="hidden" name="pmpe_default_template" value="' . esc_attr($template) . '" />' . "\n";
        }
        if ($seats > 0) {
            echo '<input type="hidden" name="pmpe_team_seats" value="' . esc_attr($seats) . '" />' . "\n";
        }
    }

    public static function prepopulate_billing($morder = null): void
    {
        if (!self::is_enterprise_checkout()) {
            return;
        }

        if (!session_id()) {
            session_start();
        }

        $session = isset($_SESSION[PIE_SESSION_KEY]) ? (array) $_SESSION[PIE_SESSION_KEY] : [];
        $business = isset($session['business']) && is_array($session['business']) ? $session['business'] : [];

        if ($morder && is_object($morder) && isset($morder->billing)) {
            if (!empty($business['pmpe_business_phone'])) {
                $morder->billing->phone = sanitize_text_field($business['pmpe_business_phone']);
            }
            if (!empty($business['pmpe_company_address'])) {
                $morder->billing->address1 = sanitize_text_field($business['pmpe_company_address']);
            }
            if (!empty($business['pmpe_business_email'])) {
                $morder->billing->email = sanitize_email($business['pmpe_business_email']);
                $morder->Email = sanitize_email($business['pmpe_business_email']);
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
        if (!self::is_enterprise_checkout()) {
            return;
        }

        if (!session_id()) {
            session_start();
        }

        $session = isset($_SESSION[PIE_SESSION_KEY]) ? (array) $_SESSION[PIE_SESSION_KEY] : [];

        $user_id = get_current_user_id();
        if ($user_id > 0 && self::should_use_settings($user_id)) {
            unset($session['template'], $session['business']);
        }

        if (empty($session) && !empty($_POST)) {
            $session = [];
            if (isset($_POST['pmpe_default_template'])) {
                $session['template'] = sanitize_text_field($_POST['pmpe_default_template']);
            }
            if (isset($_POST['pmpe_team_seats'])) {
                $session['team_seats'] = intval($_POST['pmpe_team_seats']);
            }
            foreach (['pmpe_company_name', 'pmpe_business_email', 'pmpe_business_phone', 'pmpe_company_address', 'pmpe_website', 'pmpe_vat_number'] as $field) {
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

        if (isset($session['template']) && !isset($_REQUEST['pmpe_default_template'])) {
            $_REQUEST['pmpe_default_template'] = $session['template'];
        }
        if (isset($session['team_seats']) && !isset($_REQUEST['pmpe_team_seats'])) {
            $_REQUEST['pmpe_team_seats'] = $session['team_seats'];
        }
        if (isset($session['business']) && is_array($session['business'])) {
            foreach ($session['business'] as $key => $value) {
                if (!isset($_REQUEST[$key])) {
                    $_REQUEST[$key] = $value;
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
            unset($_SESSION[PIE_SESSION_KEY]);
        }
    }

    public static function load_custom_template(): void
    {
        if (!isset($_REQUEST['pie_complete']) || intval($_REQUEST['pie_complete']) !== 1) {
            return;
        }

        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && (isset($_POST['submit-checkout']) || isset($_POST['pmpro_submit']) || isset($_POST['javascriptok']))
        ) {
            return;
        }

        $custom = get_stylesheet_directory() . '/pages/checkoutent.php';
        if (file_exists($custom)) {
            require $custom;
            exit;
        }

        $plugin_template = PIE_PLUGIN_DIR . 'pages/checkout.php';
        if (file_exists($plugin_template)) {
            require $plugin_template;
            exit;
        }
    }

    public static function skip_account_fields_for_logged_in($skip)
    {
        if (is_user_logged_in() && self::is_enterprise_checkout()) {
            return true;
        }
        return $skip;
    }

    public static function handle_stripe_success(): void
    {
        if (!isset($_GET['pie_stripe_success']) || intval($_GET['pie_stripe_success']) !== 1) {
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
                    $transient_key = $meta['pie_session_key'] ?? ($body['client_reference_id'] ?? '');
                    if (!empty($transient_key)) {
                        $session_meta = get_transient($transient_key);
                    }
                    if (empty($session_meta)) {
                        $session_meta = [
                            'level_id' => intval($meta['pie_level_id'] ?? 60),
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

        $template  = $session_meta['template'] ?? '';
        $business  = $session_meta['business'] ?? [];
        $account   = $session_meta['account'] ?? [];
        $level_id  = intval($session_meta['level_id'] ?? 60);
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
            // Grant full Enterprise access
            $councils = PIE_EnterpriseData::all_councils();
            update_user_meta($user_id, PIE_META_REGION_KEY, 'Enterprise Access');
            update_user_meta($user_id, PIE_META_ALLOWED_KEY, $councils);
            update_user_meta($user_id, PIE_META_SELECTED_KEY, $councils);

            // Team owner setup
            update_user_meta($user_id, PIE_META_TEAM_OWNER, 'yes');
            update_user_meta($user_id, PIE_META_TEAM_SEATS, 1);
            update_user_meta($user_id, PIE_META_TEAM_MEMBERS, [$user_id]);

            if (!empty($template)) {
                update_user_meta($user_id, PIE_META_TEMPLATE, $template);
            }

            $pi_business = [];
            if (!empty($business)) {
                $field_map = [
                    'pmpe_company_name'    => 'company_name',
                    'pmpe_business_email'  => 'email',
                    'pmpe_business_phone'  => 'phone',
                    'pmpe_company_address' => 'company_address',
                    'pmpe_website'         => 'website',
                ];
                foreach ($field_map as $bk => $pk) {
                    if (isset($business[$bk]) && !empty($business[$bk])) {
                        $pi_business[$pk] = $business[$bk];
                    }
                }
                $pi_business['source'] = 'checkout';
                if (!empty($template)) {
                    $pi_business['default_template'] = $template;
                }
                update_user_meta($user_id, '_pi_business_info', $pi_business);
            }

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
        unset($_SESSION[PIE_SESSION_KEY]);

        wp_safe_redirect(home_url('/membership-account/'));
        exit;
    }
}
