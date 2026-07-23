<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * POST /planningindex/v1/checkout
 *
 * Saves the final checkout session data (councils, price, template,
 * business info, account credentials) so the PMPro hooks can pick
 * them up when the browser is redirected to the real PMPro checkout
 * page for card collection.
 *
 * Returns a redirectUrl that the React app navigates to via
 * window.location.href.
 */
class PIC_Checkout_Controller
{
    public static function checkout(WP_REST_Request $request)
    {
        if (!session_id()) {
            session_start();
        }

        $data = isset($_SESSION[PIC_SESSION_KEY]) ? (array) $_SESSION[PIC_SESSION_KEY] : [];

        $councils = isset($data['councils']) && is_array($data['councils']) ? $data['councils'] : [];
        $template = isset($data['template']) ? $data['template'] : 'standard-planning';
        $business = isset($data['business']) && is_array($data['business']) ? $data['business'] : [];

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
                'message' => 'No membership level is configured. Please contact support.',
            ], 500);
        }

        $price = count($councils) * PIC_UNIT_PRICE;

        // Ensure price is stored in the session so the PMPro hooks see it
        $data['price'] = number_format($price, 2, '.', '');
        $_SESSION[PIC_SESSION_KEY] = $data;

        // Build the PMPro checkout URL — the browser will redirect here
        // and the PmproHooks::restore_session() method will merge the
        // session data into $_REQUEST before PMPro processes checkout.
        $checkout_url = self::build_pmpro_checkout_url($level_id);

        $plan_name = 'Planning Index Subscription';
        if (function_exists('pmpro_getLevel') && $level_id > 0) {
            $level = pmpro_getLevel($level_id);
            if ($level && !empty($level->name)) {
                $plan_name = $level->name;
            }
        }

        return new WP_REST_Response([
            'success'      => true,
            'orderCode'    => 'PIC-' . strtoupper(wp_generate_password(8, false)),
            'orderDate'    => date('j F Y'),
            'planName'      => $plan_name,
            'councilCount' => count($councils),
            'monthlyCost'  => $price,
            'totalDueToday'=> $price,
            'redirectUrl'  => $checkout_url,
        ], 200);
    }

    /**
     * Build the PMPro checkout URL for the configured per-council level.
     *
     * Adds the level parameter and a pi_complete flag so CheckoutDetection
     * knows to let the real PMPro checkout page render (not the React app).
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

        // Preserve the gateway if one is configured
        $gateway = get_option('pmpro_gateway', '');
        if (!empty($gateway)) {
            $args['gateway'] = $gateway;
        }

        return add_query_arg($args, $base);
    }

    /**
     * GET /checkout/verify-price
     *
     * Returns the current session's calculated price and council count
     * so the React app can display a server-sourced price confirmation
     * on the review step before submitting.
     */
    public static function verify_price(WP_REST_Request $request)
    {
        if (!session_id()) {
            session_start();
        }

        $data = isset($_SESSION[PIC_SESSION_KEY]) ? (array) $_SESSION[PIC_SESSION_KEY] : [];
        $councils = isset($data['councils']) && is_array($data['councils']) ? $data['councils'] : [];
        $count = count($councils);
        $price = isset($data['price']) ? floatval($data['price']) : ($count * PIC_UNIT_PRICE);

        return new WP_REST_Response([
            'success'      => true,
            'councilCount' => $count,
            'monthlyCost'  => $price,
            'totalDueToday'=> $price,
            'unitPrice'    => PIC_UNIT_PRICE,
        ], 200);
    }
}
