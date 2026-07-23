<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * POST /planningindex/v1/login
 *
 * Authenticates a user against WordPress credentials and returns
 * the profile data in the same shape as GET /profile, so the React
 * app receives the full account info on success.
 */
class PIC_Login_Controller
{
    /**
     * @return WP_REST_Response
     */
    public static function login(WP_REST_Request $request)
    {
        $identifier = sanitize_text_field($request->get_param('login') ?? '');
        $password = $request->get_param('password') ?? '';

        if (empty($identifier) || empty($password)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Please enter both your username/email and password.',
            ], 400);
        }

        // Determine if the identifier is an email or a username.
        if (strpos($identifier, '@') !== false) {
            $user = get_user_by('email', $identifier);
            $login = $user ? $user->user_login : $identifier;
        } else {
            $login = $identifier;
        }

        $credentials = [
            'user_login'    => $login,
            'user_password' => $password,
            'remember'      => false,
        ];

        $user = wp_signon($credentials, is_ssl());

        if (is_wp_error($user)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Invalid login details. Please check your credentials and try again.',
            ], 401);
        }

        // Re-set the current user so subsequent helper calls see the session.
        wp_set_current_user($user->ID);

        return new WP_REST_Response(self::build_profile_response($user->ID), 200);
    }

    /**
     * Builds the profile response object identical to ProfileController::get_profile.
     *
     * @return array<string, mixed>
     */
    private static function build_profile_response(int $user_id): array
    {
        $user = get_userdata($user_id);

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

        return [
            'id'                => (string) $user_id,
            'username'          => $user->user_login,
            'fullName'          => $user->display_name,
            'email'             => $user->user_email,
            'companyName'       => $business_info['company_name'] ?? '',
            'businessEmail'     => $business_info['email'] ?? '',
            'businessPhone'     => $business_info['phone'] ?? '',
            'businessAddress'   => $business_info['company_address'] ?? '',
            'website'           => $business_info['website'] ?? '',
            'vatNumber'         => $business_info['vat_number'] ?? '',
            'selectedCouncils'  => $councils,
            'selectedTemplateId' => $template,
            'monthlyCost'       => count($councils) * PIC_UNIT_PRICE,
            'totalDueToday'     => count($councils) * PIC_UNIT_PRICE,
        ];
    }
}
