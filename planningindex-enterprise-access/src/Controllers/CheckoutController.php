<?php

if (!defined('ABSPATH')) {
    exit;
}

class PIE_CheckoutController
{
    public static function process(WP_REST_Request $request)
    {
        if (!session_id()) {
            session_start();
        }

        $data = isset($_SESSION[PIE_SESSION_KEY]) ? (array) $_SESSION[PIE_SESSION_KEY] : [];

        $template = $data['template'] ?? 'standard-planning';
        $price = PIE_EnterpriseData::get_enterprise_price();

        if ($price <= 0) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Unable to determine enterprise price. Please contact support.',
            ], 400);
        }

        $level_id = intval(get_option(PIE_OPTION_LEVEL_ID, 60));
        if ($level_id === 0) {
            $level_id = 60;
        }

        $checkout_url = self::build_pmpro_checkout_url($level_id);

        return new WP_REST_Response([
            'success'       => true,
            'orderCode'     => 'PIE-' . strtoupper(wp_generate_password(8, false)),
            'orderDate'     => gmdate('Y-m-d H:i:s'),
            'planName'      => 'Enterprise Access — All UK Councils',
            'councilCount'  => count(PIE_EnterpriseData::all_councils()),
            'monthlyCost'   => $price,
            'totalDueToday' => $price,
            'redirectUrl'   => $checkout_url,
        ], 200);
    }

    public static function verify_price(WP_REST_Request $request)
    {
        $price = PIE_EnterpriseData::get_enterprise_price();

        return new WP_REST_Response([
            'success'       => $price > 0,
            'councilCount'  => count(PIE_EnterpriseData::all_councils()),
            'monthlyCost'   => $price,
            'totalDueToday' => $price,
        ], 200);
    }

    private static function build_pmpro_checkout_url(int $level_id): string
    {
        $url = home_url('/membership-checkout/');
        $url = add_query_arg([
            'level'       => $level_id,
            'pmpro_level' => $level_id,
            'pie_complete' => '1',
            'gateway'     => 'stripe',
        ], $url);
        return $url;
    }
}
