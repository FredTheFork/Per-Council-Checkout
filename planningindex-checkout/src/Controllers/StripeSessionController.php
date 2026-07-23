<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * POST /planningindex/v1/stripe-session
 *
 * Creates a Stripe Checkout Session directly (bypassing PMPro's checkout
 * page entirely) and returns the Stripe-hosted URL.
 */
class PIC_StripeSession_Controller
{
    private static function debug(string $msg, $data = null): void
    {
        $log = '[PIC Stripe] ' . $msg;
        if ($data !== null) {
            $log .= ': ' . (is_scalar($data) ? (string) $data : wp_json_encode($data));
        }
        if (function_exists('error_log')) {
            error_log($log);
        }
    }

    public static function create_session(WP_REST_Request $request)
    {
        self::debug('create_session called');

        if (!session_id()) {
            session_start();
        }

        // ── 1. Gather session data ───────────────────────────────────
        $data = isset($_SESSION[PIC_SESSION_KEY]) ? (array) $_SESSION[PIC_SESSION_KEY] : [];
        self::debug('session data from $_SESSION', $data);

        $councils = isset($data['councils']) && is_array($data['councils']) ? $data['councils'] : [];
        $template = isset($data['template']) ? $data['template'] : 'standard-planning';
        $business = isset($data['business']) && is_array($data['business']) ? $data['business'] : [];
        $account  = [
            'username' => $data['username'] ?? '',
            'email'    => $data['email'] ?? '',
            'password' => $data['password'] ?? '',
        ];

        // Also accept data from the request body (React app sends it directly)
        $body = $request->get_json_params();
        self::debug('request body from React', $body);

        if (is_array($body)) {
            if (!empty($body['councils']) && is_array($body['councils'])) {
                $councils = $body['councils'];
            }
            if (!empty($body['templateId'])) {
                $template = sanitize_text_field($body['templateId']);
            }
            if (!empty($body['businessInfo']) && is_array($body['businessInfo'])) {
                $business = [
                    'pmpc_company_name'    => $body['businessInfo']['companyName'] ?? '',
                    'pmpc_business_email'  => $body['businessInfo']['businessEmail'] ?? '',
                    'pmpc_business_phone'  => $body['businessInfo']['businessPhone'] ?? '',
                    'pmpc_company_address' => $body['businessInfo']['businessAddress'] ?? '',
                ];
            }
            if (!empty($body['accountInfo']) && is_array($body['accountInfo'])) {
                $account['username'] = $body['accountInfo']['username'] ?? $account['username'];
                $account['email']    = $body['accountInfo']['email'] ?? $account['email'];
                $account['password'] = $body['accountInfo']['password'] ?? $account['password'];
            }
        }

        self::debug('resolved councils', $councils);
        self::debug('resolved business', $business);
        self::debug('resolved account', ['username' => $account['username'], 'email' => $account['email'], 'has_password' => !empty($account['password'])]);
        self::debug('is_user_logged_in', is_user_logged_in() ? 'yes (' . get_current_user_id() . ')' : 'no');

        // ── 2. Validate ──────────────────────────────────────────────
        if (count($councils) < PIC_MIN_SELECTION) {
            self::debug('VALIDATION FAIL: not enough councils', count($councils));
            return new WP_REST_Response([
                'success' => false,
                'message' => sprintf('Please select at least %d councils.', PIC_MIN_SELECTION),
            ], 400);
        }

        $level_id = intval(get_option(PIC_OPTION_LEVEL_ID, 0));
        self::debug('level_id from option', $level_id);
        if ($level_id === 0) {
            self::debug('VALIDATION FAIL: no level configured');
            return new WP_REST_Response([
                'success' => false,
                'message' => 'No membership level is configured.',
            ], 500);
        }

        // For logged-out users, require account credentials
        if (!is_user_logged_in()) {
            if (empty($account['email']) || empty($account['username'])) {
                self::debug('VALIDATION FAIL: missing account credentials');
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Please complete your account information before subscribing.',
                ], 400);
            }

            if (email_exists($account['email'])) {
                self::debug('VALIDATION FAIL: email already in use', $account['email']);
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'That email address is already in use. Please log in, or use a different email address.',
                ], 409);
            }
            if (username_exists($account['username'])) {
                self::debug('VALIDATION FAIL: username already taken', $account['username']);
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'That username is already taken. Please choose a different username.',
                ], 409);
            }
        }

        $price = count($councils) * PIC_UNIT_PRICE;
        $price_cents = intval(round($price * 100));
        self::debug('price', $price);
        self::debug('price_cents', $price_cents);

        // ── 3. Get Stripe keys from PMPro ────────────────────────────
        $secret_key = '';
        if (function_exists('pmpro_getOption')) {
            $secret_key = pmpro_getOption('stripe_secretkey');
        }
        if (empty($secret_key)) {
            $secret_key = get_option('pmpro_stripe_secretkey', '');
        }
        // Also check the common option name without pmpro_ prefix
        if (empty($secret_key)) {
            $secret_key = get_option('stripe_secretkey', '');
        }

        self::debug('secret_key found', $secret_key ? 'yes (' . substr($secret_key, 0, 7) . '...)' : 'NO');

        if (empty($secret_key)) {
            self::debug('ERROR: no Stripe secret key found in any option');
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Stripe is not configured. Please contact support.',
            ], 500);
        }

        // ── 4. Build success/cancel URLs ──────────────────────────────
        $success_url = home_url('/membership-account/');
        $success_url = add_query_arg([
            'pic_stripe_success' => '1',
            'session_id'         => '{CHECKOUT_SESSION_ID}',
        ], $success_url);

        $cancel_url = home_url('/membership-checkout/');
        $cancel_url = add_query_arg([
            'level'       => $level_id,
            'pmpro_level' => $level_id,
            'pic_cancel'  => '1',
        ], $cancel_url);

        self::debug('success_url', $success_url);
        self::debug('cancel_url', $cancel_url);

        // ── 5. Build customer data ────────────────────────────────────
        $customer_email = '';

        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $customer_email = $user->user_email;
            self::debug('customer_email (logged in)', $customer_email);
        } else {
            $customer_email = $account['email'];
            self::debug('customer_email (logged out)', $customer_email);
        }

        // ── 6. Store session metadata for fulfillment ─────────────────
        $session_meta = [
            'councils'  => $councils,
            'template'  => $template,
            'business'  => $business,
            'account'   => $account,
            'level_id'  => $level_id,
            'price'     => number_format($price, 2, '.', ''),
            'logged_in' => is_user_logged_in(),
            'user_id'   => is_user_logged_in() ? get_current_user_id() : 0,
        ];

        $session_key = 'pic_stripe_' . wp_generate_password(20, false);
        set_transient($session_key, $session_meta, 3600);
        self::debug('transient stored', $session_key);

        // ── 7. Create Stripe Checkout Session via API ─────────────────
        $payload = [
            'mode'                => 'subscription',
            'line_items[0][price_data][currency]'     => 'gbp',
            'line_items[0][price_data][unit_amount]'  => (string) $price_cents,
            'line_items[0][price_data][recurring][interval]' => 'month',
            'line_items[0][price_data][product_data][name]' => 'Planning Index — ' . count($councils) . ' Council' . (count($councils) !== 1 ? 's' : ''),
            'line_items[0][price_data][product_data][description]' => 'Per-council subscription at £' . PIC_UNIT_PRICE . '/council/month',
            'line_items[0][quantity]'                => '1',
            'success_url'        => $success_url,
            'cancel_url'         => $cancel_url,
            'client_reference_id' => $session_key,
        ];

        if (!empty($customer_email)) {
            $payload['customer_email'] = $customer_email;
        }

        $form_body = http_build_query($payload, '', '&');
        self::debug('Stripe API form body', $form_body);

        $response = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', [
            'headers' => [
                'Authorization'  => 'Bearer ' . $secret_key,
                'Content-Type'   => 'application/x-www-form-urlencoded',
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

        $code = wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);
        $body = json_decode($raw_body, true);

        self::debug('Stripe response code', $code);
        self::debug('Stripe response body', $raw_body);

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

        // ── 8. Return the Stripe Checkout URL ──────────────────────────
        self::debug('SUCCESS: returning Stripe URL', $body['url']);
        return new WP_REST_Response([
            'success'     => true,
            'stripeUrl'   => $body['url'],
            'sessionId'   => $body['id'] ?? '',
        ], 200);
    }
}
