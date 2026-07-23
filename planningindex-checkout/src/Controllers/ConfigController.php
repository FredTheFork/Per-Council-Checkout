<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * GET /planningindex/v1/config
 *
 * Returns runtime configuration so the React app can adapt dynamically
 * without hardcoding values.
 */
class PIC_Config_Controller
{
    /**
     * @return WP_REST_Response
     */
    public static function get_config(WP_REST_Request $request)
    {
        $level_id = intval(get_option(PIC_OPTION_LEVEL_ID, 0));
        $gateway = get_option('pmpro_gateway', 'stripe');

        $require_billing = true;
        if (function_exists('pmpro_getLevel') && $level_id > 0) {
            $level = pmpro_getLevel($level_id);
            if ($level && floatval($level->initial_payment) == 0 && floatval($level->billing_amount) == 0) {
                $require_billing = false;
            }
        }

        $checkout_url = function_exists('pmpro_url') ? pmpro_url('checkout') : '';

        return new WP_REST_Response([
            'unitPrice' => PIC_UNIT_PRICE,
            'minSelection' => PIC_MIN_SELECTION,
            'totalSteps' => PIC_TOTAL_STEPS,
            'checkoutUrl' => $checkout_url,
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'gateway' => $gateway,
            'levelId' => $level_id,
            'requireBilling' => $require_billing,
            'isLoggedIn' => is_user_logged_in(),
        ], 200);
    }
}
