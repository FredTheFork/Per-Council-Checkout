<?php

if (!defined('ABSPATH')) {
    exit;
}

class PIE_StripeSessionController
{
    private static function debug(string $msg, $data = null): void
    {
        $log = '[PIE Stripe] ' . $msg;
        if ($data !== null) {
            $log .= ': ' . (is_scalar($data) ? (string) $data : wp_json_encode($data));
        }
        if (function_exists('error_log')) {
            error_log($log);
        }
    }

    public static function create(WP_REST_Request $request)
    {
        self::debug('create called');

        if (!session_id()) {
            session_start();
        }

        $data = isset($_SESSION[PIE_SESSION_KEY]) ? (array) $_SESSION[PIE_SESSION_KEY] : [];
        self::debug('session data', $data);

        $template  = $data['template'] ?? 'standard-planning';
        $business  = isset($data['business']) && is_array($data['business']) ? $data['business'] : [];
        $account   = [
            'username' => $data['username'] ?? '',
            'email'    => $data['email'] ?? '',
            'password' => $data['password'] ?? '',
        ];

        $body = $request->get_json_params();
        self::debug('request body', $body);

        if (is_array($body)) {
            if (!empty($body['templateId'])) {
                $template = sanitize_text_field($body['templateId']);
            }
            if (!empty($body['businessInfo']) && is_array($body['businessInfo'])) {
                $business = [
                    'pmpe_company_name'    => $body['businessInfo']['companyName'] ?? '',
                    'pmpe_business_email'  => $body['businessInfo']['businessEmail'] ?? '',
                    'pmpe_business_phone'  => $body['businessInfo']['businessPhone'] ?? '',
                    'pmpe_company_address' => $body['businessInfo']['businessAddress'] ?? '',
                ];
            }
            if (!empty($body['accountInfo']) && is_array($body['accountInfo'])) {
                $account['username'] = $body['accountInfo']['username'] ?? $account['username'];
                $account['email']    = $body['accountInfo']['email'] ?? $account['email'];
                $account['password'] = $body['accountInfo']['password'] ?? $account['password'];
            }
        }

        $price = PIE_EnterpriseData::get_enterprise_price();
        self::debug('enterprise price', $price);

        if ($price <= 0) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Unable to determine enterprise price. Please contact support.',
            ], 400);
        }

        $level_id = intval(get_option(PIE_OPTION_LEVEL_ID, 0));
        if ($level_id === 0) {
            $level_id = 60;
        }
        self::debug('level_id', $level_id);

        if (!is_user_logged_in()) {
            if (empty($account['email']) || empty($account['username'])) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Please complete your account information before subscribing.',
                ], 400);
            }
            if (email_exists($account['email'])) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'That email address is already in use. Please log in, or use a different email address.',
                ], 409);
            }
            if (username_exists($account['username'])) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'That username is already taken. Please choose a different username.',
                ], 409);
            }
        }

        $price_cents = intval(round($price * 100));
        self::debug('price_cents', $price_cents);

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

        self::debug('secret_key found', $secret_key ? 'yes (' . substr($secret_key, 0, 7) . '...)' : 'NO');

        if (empty($secret_key)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Stripe is not configured. Please contact support.',
            ], 500);
        }

        $session_key = 'pie_stripe_' . wp_generate_password(20, false);

        $success_url = home_url('/membership-account/');
        $success_url = add_query_arg([
            'pie_stripe_success' => '1',
            'session_id'          => '{CHECKOUT_SESSION_ID}',
            'pie_key'             => $session_key,
        ], $success_url);

        $cancel_url = home_url('/membership-checkout/');
        $cancel_url = add_query_arg([
            'level'       => $level_id,
            'pmpro_level' => $level_id,
            'pie_cancel' => '1',
        ], $cancel_url);

        self::debug('success_url', $success_url);
        self::debug('cancel_url', $cancel_url);

        $customer_email = '';
        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $customer_email = $user->user_email;
        } else {
            $customer_email = $account['email'];
        }
        self::debug('customer_email', $customer_email);

        $session_meta = [
            'template'  => $template,
            'business'  => $business,
            'account'   => $account,
            'level_id'  => $level_id,
            'price'     => number_format($price, 2, '.', ''),
            'logged_in' => is_user_logged_in(),
            'user_id'   => is_user_logged_in() ? get_current_user_id() : 0,
        ];

        set_transient($session_key, $session_meta, 86400);
        self::debug('transient stored', $session_key);

        $product_name = 'Planning Index Enterprise Access — All UK Councils';
        $product_desc = 'Enterprise subscription at £' . number_format($price, 2) . '/month';

        $payload = [
            'mode'                => 'subscription',
            'line_items[0][price_data][currency]'                  => 'gbp',
            'line_items[0][price_data][unit_amount]'               => (string) $price_cents,
            'line_items[0][price_data][recurring][interval]'       => 'month',
            'line_items[0][price_data][product_data][name]'        => $product_name,
            'line_items[0][price_data][product_data][description]' => $product_desc,
            'line_items[0][quantity]'                              => '1',
            'success_url'                                          => $success_url,
            'cancel_url'                                           => $cancel_url,
            'client_reference_id'                                 => $session_key,
            'metadata[pie_session_key]'                           => $session_key,
            'metadata[pie_level_id]'                              => (string) $level_id,
            'metadata[pie_price]'                                 => (string) $price,
            'subscription_data[metadata][pie_session_key]'       => $session_key,
            'subscription_data[metadata][pie_level_id]'          => (string) $level_id,
        ];

        if (!empty($customer_email)) {
            $payload['customer_email'] = $customer_email;
        }

        $form_body = http_build_query($payload, '', '&');
        self::debug('Stripe API payload (truncated)', substr($form_body, 0, 300));

        $response = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $secret_key,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'body'    => $form_body,
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            self::debug('wp_remote_post ERROR', $response->get_error_message());
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Unable to connect to Stripe: ' . $response->get_error_message(),
            ], 502);
        }

        $code     = wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);
        $body     = json_decode($raw_body, true);

        self::debug('Stripe response code', $code);

        if ($code < 200 || $code >= 300 || empty($body['url'])) {
            $error_msg = 'Stripe session creation failed.';
            if (!empty($body['error']['message'])) {
                $error_msg = $body['error']['message'];
            }
            self::debug('ERROR: Stripe returned error', $error_msg);
            return new WP_REST_Response([
                'success' => false,
                'message' => $error_msg,
            ], 502);
        }

        self::debug('SUCCESS: returning Stripe URL', $body['url']);
        return new WP_REST_Response([
            'success'   => true,
            'stripeUrl' => $body['url'],
            'sessionId' => $body['id'] ?? '',
        ], 200);
    }
}
