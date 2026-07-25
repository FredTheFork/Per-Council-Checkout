<?php

if (!defined('ABSPATH')) {
    exit;
}

class PIE_ProfileController
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
        $pie_bi = get_user_meta($user_id, PIE_META_BUSINESS, true);

        $source = (is_array($bi) && isset($bi['settings_updated_at'])) ? $bi : (is_array($pie_bi) ? $pie_bi : []);

        $template = get_user_meta($user_id, PIE_META_TEMPLATE, true);
        if (empty($template) && is_array($bi) && isset($bi['default_template'])) {
            $template = $bi['default_template'];
        }

        $enterprise_price = PIE_EnterpriseData::get_enterprise_price();

        $team_seats = intval(get_user_meta($user_id, PIE_META_TEAM_SEATS, true)) ?: 1;
        $is_team_owner = get_user_meta($user_id, PIE_META_TEAM_OWNER, true) === 'yes';

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
            'selectedTemplateId'=> $template ?: null,
            'monthlyCost'       => $enterprise_price,
            'totalDueToday'     => $enterprise_price,
            'teamSeats'         => $team_seats,
            'isTeamOwner'       => $is_team_owner,
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

        $pie_business = [
            'company_name'    => sanitize_text_field($body['companyName'] ?? ''),
            'email'           => sanitize_email($body['businessEmail'] ?? ''),
            'phone'           => sanitize_text_field($body['businessPhone'] ?? ''),
            'company_address' => sanitize_text_field($body['businessAddress'] ?? ''),
        ];

        update_user_meta($user_id, PIE_META_BUSINESS, $pie_business);

        $bi = get_user_meta($user_id, '_pi_business_info', true);
        if (is_array($bi) && isset($bi['settings_updated_at'])) {
            $bi['email'] = $pie_business['email'];
            $bi['phone'] = $pie_business['phone'];
            $bi['company_address'] = $pie_business['company_address'];
            $bi['company_name'] = $pie_business['company_name'];
            update_user_meta($user_id, '_pi_business_info', $bi);
        } else {
            $bi = array_merge(is_array($bi) ? $bi : [], $pie_business, ['source' => 'checkout']);
            update_user_meta($user_id, '_pi_business_info', $bi);
        }

        return new WP_REST_Response(['success' => true], 200);
    }
}
