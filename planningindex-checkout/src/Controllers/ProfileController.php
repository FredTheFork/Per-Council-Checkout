<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * User profile endpoints.
 *
 * GET  /planningindex/v1/profile  — returns logged-in user's profile data
 * POST /planningindex/v1/profile  — updates business info, respecting
 *                                   the settings-precedence logic
 */
class PIC_Profile_Controller
{
    /**
     * Permission callback: requires a logged-in user.
     *
     * @return bool|WP_Error
     */
    public static function require_login()
    {
        if (!is_user_logged_in()) {
            return new WP_Error(
                'pic_not_authenticated',
                __('You must be logged in to access this resource.', 'planningindex-checkout'),
                ['status' => 401]
            );
        }
        return true;
    }

    /**
     * GET /profile — returns the logged-in user's profile data.
     *
     * @return WP_REST_Response
     */
    public static function get_profile(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        $user = wp_get_current_user();

        $business_info = get_user_meta($user_id, '_pi_business_info', true);
        if (!is_array($business_info)) {
            $business_info = [];
        }

        $councils = get_user_meta($user_id, PIC_META_KEY, true);
        if (!is_array($councils)) {
            $councils = [];
        }

        $template = $business_info['default_template']
            ?? get_user_meta($user_id, PIC_META_TEMPLATE, true)
            ?? null;

        $price = get_user_meta($user_id, PIC_META_PRICE, true);

        return new WP_REST_Response([
            'id' => (string) $user_id,
            'username' => $user->user_login,
            'fullName' => $user->display_name,
            'email' => $user->user_email,
            'companyName' => $business_info['company_name'] ?? '',
            'businessEmail' => $business_info['email'] ?? '',
            'businessPhone' => $business_info['phone'] ?? '',
            'businessAddress' => $business_info['company_address'] ?? '',
            'website' => $business_info['website'] ?? '',
            'vatNumber' => $business_info['vat_number'] ?? '',
            'selectedCouncils' => $councils,
            'selectedTemplateId' => $template,
            'monthlyCost' => count($councils) * PIC_UNIT_PRICE,
            'totalDueToday' => count($councils) * PIC_UNIT_PRICE,
        ], 200);
    }

    /**
     * POST /profile — updates business info on the user's profile,
     * respecting the settings-precedence logic from the existing plugin.
     *
     * @return WP_REST_Response
     */
    public static function update_profile(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();

        $company_name = sanitize_text_field($request->get_param('companyName') ?? '');
        $business_email = sanitize_email($request->get_param('businessEmail') ?? '');
        $business_phone = sanitize_text_field($request->get_param('businessPhone') ?? '');
        $business_address = sanitize_text_field($request->get_param('businessAddress') ?? '');
        $website = sanitize_text_field($request->get_param('website') ?? '');
        $vat_number = sanitize_text_field($request->get_param('vatNumber') ?? '');

        $business_info = get_user_meta($user_id, '_pi_business_info', true);
        if (!is_array($business_info)) {
            $business_info = [];
        }

        $has_settings = !empty($business_info['settings_updated_at']);

        if ($has_settings) {
            // Settings exist — only update PMPC meta, do not touch _pi_business_info.
            $checkout_business = [];
            if ($company_name) $checkout_business['pmpc_company_name'] = $company_name;
            if ($business_email) $checkout_business['pmpc_business_email'] = $business_email;
            if ($business_phone) $checkout_business['pmpc_business_phone'] = $business_phone;
            if ($business_address) $checkout_business['pmpc_company_address'] = $business_address;
            if ($website) $checkout_business['pmpc_website'] = $website;
            if ($vat_number) $checkout_business['pmpc_vat_number'] = $vat_number;

            if (!empty($checkout_business)) {
                update_user_meta($user_id, PIC_META_BUSINESS, $checkout_business);
            }
        } else {
            // No settings saved yet — merge into _pi_business_info.
            if ($company_name) $business_info['company_name'] = $company_name;
            if ($business_email) $business_info['email'] = $business_email;
            if ($business_phone) $business_info['phone'] = $business_phone;
            if ($business_address) $business_info['company_address'] = $business_address;
            if ($website) $business_info['website'] = $website;
            if ($vat_number) $business_info['vat_number'] = $vat_number;

            $business_info['source'] = 'checkout';
            update_user_meta($user_id, '_pi_business_info', $business_info);

            // Also save to PMPC meta for reference.
            $checkout_business = [];
            if ($company_name) $checkout_business['pmpc_company_name'] = $company_name;
            if ($business_email) $checkout_business['pmpc_business_email'] = $business_email;
            if ($business_phone) $checkout_business['pmpc_business_phone'] = $business_phone;
            if ($business_address) $checkout_business['pmpc_company_address'] = $business_address;
            if ($website) $checkout_business['pmpc_website'] = $website;
            if ($vat_number) $checkout_business['pmpc_vat_number'] = $vat_number;

            if (!empty($checkout_business)) {
                update_user_meta($user_id, PIC_META_BUSINESS, $checkout_business);
            }
        }

        return new WP_REST_Response(['success' => true], 200);
    }
}
