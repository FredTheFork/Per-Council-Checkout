<?php

if (!defined('ABSPATH')) {
    exit;
}

class PIRB_Profile_Controller
{
    private static function require_login(): WP_REST_Response|false
    {
        if (!is_user_logged_in()) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'You must be logged in.',
            ], 401);
        }
        return false;
    }

    public static function get_profile(WP_REST_Request $request)
    {
        $err = self::require_login();
        if ($err) {
            return $err;
        }

        $user_id = get_current_user_id();
        $user = wp_get_current_user();

        $bi = get_user_meta($user_id, '_pi_business_info', true);
        $pirb_bi = get_user_meta($user_id, PIRB_META_BUSINESS, true);

        $source = (is_array($bi) && isset($bi['settings_updated_at'])) ? $bi : (is_array($pirb_bi) ? $pirb_bi : []);

        $councils = get_user_meta($user_id, PIRB_META_KEY, true);
        if (!is_array($councils)) {
            $councils = [];
        }

        $template = get_user_meta($user_id, PIRB_META_TEMPLATE, true);
        if (empty($template) && is_array($bi) && isset($bi['template'])) {
            $template = $bi['template'];
        }

        $monthly_cost = floatval(get_user_meta($user_id, PIRB_META_PRICE, true));
        if ($monthly_cost == 0 && is_array($bi) && isset($bi['monthly_cost'])) {
            $monthly_cost = floatval($bi['monthly_cost']);
        }

        return new WP_REST_Response([
            'id'                => strval($user_id),
            'username'          => $user->user_login,
            'fullName'          => $user->display_name ?: $user->user_login,
            'email'             => $user->user_email,
            'companyName'       => $source['company_name'] ?? '',
            'businessEmail'     => $source['email'] ?? '',
            'businessPhone'     => $source['phone'] ?? '',
            'businessAddress'   => $source['company_address'] ?? '',
            'website'           => $source['website'] ?? '',
            'vatNumber'         => $source['vat_number'] ?? '',
            'selectedCouncils'  => $councils,
            'selectedTemplateId'=> $template ?: null,
            'monthlyCost'       => $monthly_cost,
            'totalDueToday'     => $monthly_cost,
        ], 200);
    }

    public static function update_profile(WP_REST_Request $request)
    {
        $err = self::require_login();
        if ($err) {
            return $err;
        }

        $user_id = get_current_user_id();
        $body = $request->get_json_params();

        $pirb_business = [
            'company_name'    => sanitize_text_field($body['companyName'] ?? ''),
            'email'           => sanitize_email($body['businessEmail'] ?? ''),
            'phone'           => sanitize_text_field($body['businessPhone'] ?? ''),
            'company_address' => sanitize_text_field($body['businessAddress'] ?? ''),
        ];

        update_user_meta($user_id, PIRB_META_BUSINESS, $pirb_business);

        $bi = get_user_meta($user_id, '_pi_business_info', true);
        if (is_array($bi) && isset($bi['settings_updated_at'])) {
            // Only update checkout-specific fields, don't overwrite settings
            $bi['email'] = $pirb_business['email'];
            $bi['phone'] = $pirb_business['phone'];
            $bi['company_address'] = $pirb_business['company_address'];
            $bi['company_name'] = $pirb_business['company_name'];
            update_user_meta($user_id, '_pi_business_info', $bi);
        } else {
            $bi = array_merge(is_array($bi) ? $bi : [], $pirb_business, ['source' => 'checkout']);
            update_user_meta($user_id, '_pi_business_info', $bi);
        }

        return new WP_REST_Response(['success' => true], 200);
    }
}
