<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * POST /planningindex/v1/checkout
 *
 * Creates a free 14-day trial account WITHOUT redirecting to the
 * PMPro checkout page. This endpoint programmatically:
 *   1. Creates (or reuses) the WordPress user account.
 *   2. Grants the configured PMPro trial membership level.
 *   3. Saves the selected councils, template, and business info.
 *
 * No card details are collected. The user gets immediate access for
 * 14 days, after which they must subscribe to a paid level.
 */
class PIT_Checkout_Controller
{
    public static function checkout(WP_REST_Request $request)
    {
        if (!session_id()) {
            session_start();
        }

        $data = isset($_SESSION[PIT_SESSION_KEY]) ? (array) $_SESSION[PIT_SESSION_KEY] : [];

        $councils = isset($data['councils']) && is_array($data['councils']) ? $data['councils'] : [];
        $template = isset($data['template']) ? $data['template'] : 'standard-planning';
        $business = isset($data['business']) && is_array($data['business']) ? $data['business'] : [];

        if (count($councils) < PIT_MIN_SELECTION) {
            return new WP_REST_Response([
                'success' => false,
                'message' => sprintf('Please select at least %d council.', PIT_MIN_SELECTION),
            ], 400);
        }

        if (count($councils) > PIT_MAX_SELECTION) {
            return new WP_REST_Response([
                'success' => false,
                'message' => sprintf('You can select up to %d councils for your free trial.', PIT_MAX_SELECTION),
            ], 400);
        }

        $level_id = intval(get_option(PIT_OPTION_LEVEL_ID, 0));
        if ($level_id === 0) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'No trial membership level is configured. Please contact support.',
            ], 500);
        }

        // ── Resolve or create the WordPress user ──────────────────────
        $user_id = 0;
        $is_new_user = false;

        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
        } else {
            $username = isset($data['username']) ? sanitize_user($data['username']) : '';
            $email     = isset($data['email']) ? sanitize_email($data['email']) : '';
            $password  = isset($data['password']) ? $data['password'] : '';

            if (empty($username) || empty($email) || empty($password)) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Account details are required to start your free trial.',
                ], 400);
            }

            if (username_exists($username)) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'That username is already taken. Please choose another.',
                ], 400);
            }

            if (email_exists($email)) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'That email address is already registered. Please log in instead.',
                ], 400);
            }

            $user_id = wp_create_user($username, $password, $email);
            if (is_wp_error($user_id)) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => $user_id->get_error_message(),
                ], 500);
            }

            $is_new_user = true;

            // Set display name
            wp_update_user([
                'ID'           => $user_id,
                'display_name' => $username,
            ]);

            // Log the user in immediately
            wp_set_current_user($user_id);
            wp_set_auth_cookie($user_id, true);
        }

        // ── Grant the PMPro trial membership level ────────────────────
        if (function_exists('pmpro_changeMembershipLevel')) {
            pmpro_changeMembershipLevel($level_id, $user_id);
        }

        // ── Save user meta: councils, template, business, trial markers ─
        update_user_meta($user_id, PIT_META_KEY, array_map('sanitize_text_field', $councils));
        update_user_meta($user_id, PIT_META_PRICE, '0.00');
        update_user_meta($user_id, PIT_META_TEMPLATE, sanitize_text_field($template));

        $expiration = time() + (PIT_TRIAL_DAYS * DAY_IN_SECONDS);
        update_user_meta($user_id, PIT_META_TRIAL_EXPIRATION, $expiration);
        update_user_meta($user_id, PIT_META_IS_TRIAL, 1);
        update_user_meta($user_id, PIT_META_TRIAL_EXPIRED, 0);

        // Business info
        if (!empty($business)) {
            update_user_meta($user_id, PIT_META_BUSINESS, $business);

            $business_info = get_user_meta($user_id, '_pi_business_info', true);
            if (!is_array($business_info)) {
                $business_info = [];
            }

            $field_map = [
                'pmpc_company_name'    => 'company_name',
                'pmpc_business_email'  => 'email',
                'pmpc_business_phone'  => 'phone',
                'pmpc_company_address' => 'company_address',
                'pmpc_website'         => 'website',
                'pmpc_vat_number'      => 'vat_number',
            ];

            foreach ($field_map as $checkout_key => $settings_key) {
                if (!empty($business[$checkout_key])) {
                    $business_info[$settings_key] = $business[$checkout_key];
                }
            }

            $business_info['default_template'] = $template;
            $business_info['source'] = 'checkout';
            update_user_meta($user_id, '_pi_business_info', $business_info);
        }

        // ── Clear the session ──────────────────────────────────────────
        unset($_SESSION[PIT_SESSION_KEY]);

        // ── Build the redirect URL ────────────────────────────────────
        $redirect_url = home_url('/membership-account/?pit_trial_started=1');

        $plan_name = 'Planning Index Free Trial';
        if (function_exists('pmpro_getLevel') && $level_id > 0) {
            $level = pmpro_getLevel($level_id);
            if ($level && !empty($level->name)) {
                $plan_name = $level->name;
            }
        }

        return new WP_REST_Response([
            'success'      => true,
            'orderCode'    => 'PIT-' . strtoupper(wp_generate_password(8, false)),
            'orderDate'    => date('j F Y'),
            'planName'      => $plan_name,
            'councilCount' => count($councils),
            'monthlyCost'  => 0,
            'totalDueToday'=> 0,
            'trialDays'    => PIT_TRIAL_DAYS,
            'redirectUrl'  => $redirect_url,
            'isNewUser'    => $is_new_user,
        ], 200);
    }

    /**
     * GET /checkout/verify-price
     *
     * Returns the current session's calculated price (£0 for trial)
     * and council count.
     */
    public static function verify_price(WP_REST_Request $request)
    {
        if (!session_id()) {
            session_start();
        }

        $data = isset($_SESSION[PIT_SESSION_KEY]) ? (array) $_SESSION[PIT_SESSION_KEY] : [];
        $councils = isset($data['councils']) && is_array($data['councils']) ? $data['councils'] : [];
        $count = count($councils);

        return new WP_REST_Response([
            'success'      => true,
            'councilCount' => $count,
            'monthlyCost'  => 0,
            'totalDueToday'=> 0,
            'unitPrice'    => 0,
            'trialDays'    => PIT_TRIAL_DAYS,
        ], 200);
    }
}
