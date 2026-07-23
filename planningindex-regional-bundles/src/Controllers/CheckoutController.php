<?php

if (!defined('ABSPATH')) {
    exit;
}

class PIRB_Checkout_Controller
{
    public static function checkout(WP_REST_Request $request)
    {
        if (!session_id()) {
            session_start();
        }

        $data = isset($_SESSION[PIRB_SESSION_KEY]) ? (array) $_SESSION[PIRB_SESSION_KEY] : [];

        $region = $data['region'] ?? '';
        $councils = isset($data['councils']) && is_array($data['councils']) ? $data['councils'] : [];
        $price = isset($data['price']) ? floatval($data['price']) : 0.0;

        if (empty($region) || empty($councils) || $price <= 0) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Session data is incomplete. Please restart the checkout process.',
            ], 400);
        }

        $level_id = intval(get_option(PIRB_OPTION_LEVEL_ID, 59));
        if ($level_id === 0) {
            $level_id = 59;
        }

        $checkout_url = self::build_pmpro_checkout_url($level_id);

        return new WP_REST_Response([
            'success'     => true,
            'orderCode'   => 'PIRB-' . strtoupper(wp_generate_password(8, false)),
            'orderDate'   => gmdate('Y-m-d H:i:s'),
            'planName'    => 'Regional Bundle — ' . $region,
            'councilCount'=> count($councils),
            'monthlyCost' => $price,
            'totalDueToday' => $price,
            'redirectUrl' => $checkout_url,
        ], 200);
    }

    public static function verify_price(WP_REST_Request $request)
    {
        if (!session_id()) {
            session_start();
        }

        $data = isset($_SESSION[PIRB_SESSION_KEY]) ? (array) $_SESSION[PIRB_SESSION_KEY] : [];

        $region = $data['region'] ?? '';
        $councils = isset($data['councils']) && is_array($data['councils']) ? $data['councils'] : [];
        $price = isset($data['price']) ? floatval($data['price']) : 0.0;

        if (!empty($region)) {
            $price = PIRB_RegionData::price_for($region);
            $councils = PIRB_RegionData::councils_for($region);
        }

        return new WP_REST_Response([
            'success'      => $price > 0,
            'councilCount' => count($councils),
            'monthlyCost'  => $price,
            'totalDueToday'=> $price,
        ], 200);
    }

    private static function build_pmpro_checkout_url(int $level_id): string
    {
        $url = home_url('/membership-checkout/');
        $url = add_query_arg([
            'level'       => $level_id,
            'pmpro_level' => $level_id,
            'pirb_complete' => '1',
            'gateway'     => 'stripe',
        ], $url);
        return $url;
    }
}
