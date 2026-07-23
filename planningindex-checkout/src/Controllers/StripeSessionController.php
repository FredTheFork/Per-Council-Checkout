<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * POST /planningindex/v1/stripe-session
 *
 * Creates a Stripe Checkout Session directly (bypassing PMPro's checkout
 * page entirely) and returns the Stripe-hosted URL. The React app redirects
 * the browser to this URL so the user goes straight to checkout.stripe.com
 * without ever seeing PMPro's account-creation form.
 *
 * After payment, Stripe redirects back to /membership-account/?pic_stripe_success=1
 * &session_id=... where PmproHooks::handle_stripe_success() fulfills the order:
 * creates the WP user (if needed), saves user meta, and grants the PMPro level.
 */
class PIC_StripeSession_Controller
{
    /**
     * Create a Stripe Checkout Session and return its URL.
     */
    public static function create_session(WP_REST_Request $request)
    {
        if (!session_id()) {
            session_start();
        }

        // ── 1. Gather session data ───────────────────────────────────
        $data = isset($_SESSION[PIC_SESSION_KEY]) ? (array) $_SESSION[PIC_SESSION_KEY] : [];

        $councils = isset($data['councils']) && is_array($data['councils']) ? $data['councils'] : [];
        $template = isset($data['template']) ? $data['template'] : 'standard-planning';
        $business = isset($data['business']) && is_array($data['business']) ? $data['business'] : [];
        $account  = [
            'username' => $data['username'] ?? '',
            'email'    => $data['email'] ?? '',
            'password' => $data['password'] ?? '',
        ];

        // Also accept data from the request body (React app may send it directly)
        $body = $request->get_json_params();
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

        // ── 2. Validate ──────────────────────────────────────────────
        if (count($councils) < PIC_MIN_SELECTION) {
            return new WP_REST_Response([
                'success' => false,
                'message' => sprintf('Please select at least %d councils.', PIC_MIN_SELECTION),
            ], 400);
        }

        $level_id = intval(get_option(PIC_OPTION_LEVEL_ID, 0));
        if ($level_id === 0) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'No membership level is configured.',
            ], 500);
        }

        // For logged-out users, require account credentials
        if (!is_user_logged_in()) {
            if (empty($account['email']) || empty($account['username'])) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Please complete your account information before subscribing.',
                ], 400);
            }

            // Validate email/username availability
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

        $price = count($councils) * PIC_UNIT_PRICE;
        $price_cents = intval(round($price * 100));

        // ── 3. Get Stripe keys from PMPro ────────────────────────────
        $secret_key = '';
        if (function_exists('pmpro_getOption')) {
            $secret_key = pmpro_getOption('stripe_secretkey');
        }
        if (empty($secret_key)) {
            $secret_key = get_option('pmpro_stripe_secretkey', '');
        }
        if (empty($secret_key)) {
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

        // ── 5. Build customer data ────────────────────────────────────
        $customer_email = '';
        $customer_data  = [];

        if (is_user_logged_in()) {
            $user = wp_get_current_user();
            $customer_email = $user->user_email;
            $customer_data['metadata'] = [
                'wp_user_id' => $user->ID,
                'source'      => 'pic_checkout',
            ];
        } else {
            $customer_email = $account['email'];
            $customer_data['metadata'] = [
                'wp_username' => $account['username'],
                'wp_email'    => $account['email'],
                'source'      => 'pic_checkout',
            ];
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

        // Store in a transient so the success handler can retrieve it
        $session_key = 'pic_stripe_' . wp_generate_password(20, false);
        set_transient($session_key, $session_meta, 3600); // 1 hour TTL

        // ── 7. Create Stripe Checkout Session via API ─────────────────
        $payload = [
            'mode'               => 'subscription',
            'line_items'         => [[
                'price_data' => [
                    'currency'     => 'gbp',
                    'unit_amount'  => $price_cents,
                    'recurring'    => ['interval' => 'month'],
                    'product_data' => [
                        'name'        => 'Planning Index — ' . count($councils) . ' Council' . (count($councils) !== 1 ? 's' : ''),
                        'description' => 'Per-council subscription at £' . PIC_UNIT_PRICE . '/council/month',
                    ],
                ],
                'quantity' => 1,
            ]],
            'success_url'        => $success_url,
            'cancel_url'         => $cancel_url,
            'client_reference_id' => $session_key,
        ];

        if (!empty($customer_email)) {
            $payload['customer_email'] = $customer_email;
        }
        if (!empty($customer_data['metadata'])) {
            $payload['metadata'] = $customer_data['metadata'];
        }

        $response = wp_remote_post('https://api.stripe.com/v1/checkout/sessions', [
            'headers' => [
                'Authorization'  => 'Bearer ' . $secret_key,
                'Content-Type'   => 'application/x-www-form-urlencoded',
            ],
            'body'    => self::build_form_body($payload),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Unable to connect to Stripe. Please try again.',
            ], 502);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code < 200 || $code >= 300 || empty($body['url'])) {
            $error_msg = 'Stripe session creation failed.';
            if (!empty($body['error']['message'])) {
                $error_msg = $body['error']['message'];
            }
            return new WP_REST_Response([
                'success' => false,
                'message' => $error_msg,
            ], 502);
        }

        // ── 8. Return the Stripe Checkout URL ──────────────────────────
        return new WP_REST_Response([
            'success'     => true,
            'stripeUrl'   => $body['url'],
            'sessionId'   => $body['id'] ?? '',
        ], 200);
    }

    /**
     * Build a URL-encoded form body for Stripe API from a nested array.
     * Stripe expects flat keys with bracket notation for nested objects.
     */
    private static function build_form_body(array $data, string $prefix = ''): string
    {
        $parts = [];
        foreach ($data as $key => $value) {
            $field_key = $prefix === '' ? $key : $prefix . '[' . $key . ']';
            if (is_array($value)) {
                $parts[] = self::build_form_body($value, $field_key);
            } else {
                $parts[] = urlencode($field_key) . '=' . urlencode((string) $value);
            }
        }
        return implode('&', $parts);
    }
}
