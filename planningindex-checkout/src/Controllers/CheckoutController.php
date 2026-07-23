<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * POST /planningindex/v1/checkout
 *
 * Reads the saved session data (councils, template, business info,
 * account credentials) and persists it to the user's meta so PMPro's
 * checkout processing can pick it up. Returns an order reference that
 * the React app displays on the confirmation screen.
 */
class PIC_Checkout_Controller
{
    /**
     * @return WP_REST_Response
     */
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

        // Persist councils, price, template, and business info onto the user
        // so PMPro's checkout processing and the confirmation page can read them.
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            update_user_meta($user_id, PIC_META_KEY, $councils);
            update_user_meta($user_id, PIC_META_PRICE, $price);
            update_user_meta($user_id, PIC_META_TEMPLATE, $template);

            if (!empty($business)) {
                update_user_meta($user_id, PIC_META_BUSINESS, $business);
            }
        }

        // Build an order reference for display. Use PMPro's MemberOrder if
        // available; otherwise generate a standalone reference.
        $order_code = '';
        $order_date = date('j F Y');

        if (class_exists('MemberOrder')) {
            $order = new MemberOrder();
            $order->user_id = get_current_user_id();
            $order->membership_id = $level_id;
            $order->subtotal = $price;
            $order->total = $price;
            $order->status = 'success';
            $order->saveOrder();

            if (!empty($order->code)) {
                $order_code = $order->code;
                $order_date = date('j F Y', strtotime($order->timestamp));
            }
        }

        if (empty($order_code)) {
            $order_code = 'PIC-' . strtoupper(wp_generate_password(8, false));
        }

        // Clear the session so a fresh checkout starts clean.
        unset($_SESSION[PIC_SESSION_KEY]);

        $plan_name = 'Planning Index Subscription';
        if (function_exists('pmpro_getLevel') && $level_id > 0) {
            $level = pmpro_getLevel($level_id);
            if ($level && !empty($level->name)) {
                $plan_name = $level->name;
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'orderCode' => $order_code,
            'orderDate' => $order_date,
            'planName' => $plan_name,
            'councilCount' => count($councils),
            'monthlyCost' => $price,
            'totalDueToday' => $price,
        ], 200);
    }
}
