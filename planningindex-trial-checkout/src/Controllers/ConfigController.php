<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * GET /planningindex/v1/config
 *
 * Returns runtime configuration for the trial checkout.
 */
class PIT_Config_Controller
{
    public static function get_config(WP_REST_Request $request)
    {
        $level_id = intval(get_option(PIT_OPTION_LEVEL_ID, 0));
        $gateway = get_option('pmpro_gateway', 'stripe');

        $checkout_url = function_exists('pmpro_url') ? pmpro_url('checkout') : '';

        return new WP_REST_Response([
            'unitPrice' => 0,
            'minSelection' => PIT_MIN_SELECTION,
            'maxSelection' => PIT_MAX_SELECTION,
            'trialDays' => PIT_TRIAL_DAYS,
            'totalSteps' => PIT_TOTAL_STEPS,
            'checkoutUrl' => $checkout_url,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'gateway' => $gateway,
            'levelId' => $level_id,
            'requireBilling' => false,
            'isLoggedIn' => is_user_logged_in(),
        ], 200);
    }
}
