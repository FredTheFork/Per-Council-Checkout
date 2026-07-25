<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * POST /planningindex/v1/checkout
 *
 * Saves the final trial checkout session data (councils, template,
 * business info, account credentials) so the PMPro hooks can pick
 * them up when the browser is redirected to the real PMPro checkout
 * page.
 *
 * The trial checkout processes at £0 — no Stripe redirect. PMPro
 * creates the account and grants the trial level for 14 days.
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

        // Price is always £0 for the trial
        $data['price'] = '0.00';
        $_SESSION[PIT_SESSION_KEY] = $data;

        // Build the PMPro checkout URL — the browser will redirect here
        // and the PmproHooks::restore_session() method will merge the
        // session data into $_REQUEST before PMPro processes checkout at £0.
        $checkout_url = self::build_pmpro_checkout_url($level_id);

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
            'trialDays'   => PIT_TRIAL_DAYS,
            'redirectUrl'  => $checkout_url,
        ], 200);
    }

    /**
     * Build the PMPro checkout URL for the configured trial level.
     */
    private static function build_pmpro_checkout_url(int $level_id): string
    {
        $base = '';
        if (function_exists('pmpro_url')) {
            $base = pmpro_url('checkout');
        }

        if (empty($base)) {
            $base = home_url('/membership-checkout/');
        }

        $args = [
            'level'       => $level_id,
            'pi_complete' => '1',
        ];

        return add_query_arg($args, $base);
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
