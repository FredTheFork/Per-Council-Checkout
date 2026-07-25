<?php

if (!defined('ABSPATH')) {
    exit;
}

class PIE_ConfigController
{
    public static function get_config(WP_REST_Request $request)
    {
        $level_id = intval(get_option(PIE_OPTION_LEVEL_ID, 60));

        $gateway = 'stripe';
        if (function_exists('pmpro_getOption')) {
            $gw = pmpro_getOption('gateway');
            if (!empty($gw)) {
                $gateway = $gw;
            }
        }

        $require_billing = true;
        $enterprise_price = PIE_EnterpriseData::get_enterprise_price();
        if ($level_id > 0 && function_exists('pmpro_getLevel')) {
            $level = pmpro_getLevel($level_id);
            if ($level && isset($level->initial_payment) && floatval($level->initial_payment) == 0 && (!isset($level->billing_amount) || floatval($level->billing_amount) == 0)) {
                $require_billing = false;
            }
        }

        return new WP_REST_Response([
            'levelId'        => $level_id,
            'gateway'        => $gateway,
            'requireBilling' => $require_billing,
            'checkoutUrl'    => esc_url_raw(home_url('/membership-checkout/')),
            'ajaxUrl'        => esc_url_raw(admin_url('admin-ajax.php')),
            'totalSteps'     => PIE_TOTAL_STEPS,
            'isLoggedIn'     => is_user_logged_in(),
            'enterprisePrice'=> $enterprise_price,
        ], 200);
    }
}
