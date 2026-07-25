<?php

if (!defined('ABSPATH')) {
    exit;
}

class PIE_LoginController
{
    public static function login(WP_REST_Request $request)
    {
        $body = $request->get_json_params();
        $identifier = isset($body['login']) ? sanitize_text_field($body['login']) : '';
        $password = isset($body['password']) ? $body['password'] : '';

        if (empty($identifier) || empty($password)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Please enter your username/email and password.',
            ], 400);
        }

        if (strpos($identifier, '@') !== false) {
            $user = get_user_by('email', $identifier);
        } else {
            $user = get_user_by('login', $identifier);
        }

        if (!$user) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Invalid login details.',
            ], 401);
        }

        $creds = [
            'user_login'    => $user->user_login,
            'user_password' => $password,
            'remember'      => true,
        ];

        $result = wp_signon($creds, is_ssl());

        if (is_wp_error($result)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Invalid login details. Please check your password and try again.',
            ], 401);
        }

        return new WP_REST_Response(self::build_profile_response($result->ID), 200);
    }

    private static function build_profile_response(int $user_id): array
    {
        $user = get_userdata($user_id);

        $template = get_user_meta($user_id, PIE_META_TEMPLATE, true);
        if (empty($template)) {
            $bi = get_user_meta($user_id, '_pi_business_info', true);
            if (is_array($bi) && isset($bi['default_template'])) {
                $template = $bi['default_template'];
            }
        }

        $enterprise_price = PIE_EnterpriseData::get_enterprise_price();
        $team_seats = intval(get_user_meta($user_id, PIE_META_TEAM_SEATS, true)) ?: 1;

        return [
            'success'             => true,
            'id'                  => strval($user_id),
            'username'            => $user->user_login,
            'fullName'            => $user->display_name ?: $user->user_login,
            'email'               => $user->user_email,
            'companyName'         => '',
            'businessEmail'       => $user->user_email,
            'businessPhone'       => '',
            'businessAddress'     => '',
            'selectedTemplateId'  => $template ?: null,
            'monthlyCost'         => $enterprise_price,
            'totalDueToday'       => $enterprise_price,
            'teamSeats'           => $team_seats,
        ];
    }
}
