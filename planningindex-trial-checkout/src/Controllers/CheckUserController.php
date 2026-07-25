<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * POST /planningindex/v1/check-user
 *
 * Checks username and email availability.
 */
class PIT_CheckUser_Controller
{
    public static function check_user(WP_REST_Request $request)
    {
        $username = sanitize_user($request->get_param('username') ?? '');
        $email = sanitize_email($request->get_param('email') ?? '');

        $valid = true;
        $errors = [];

        if ($username && username_exists($username)) {
            $valid = false;
            $errors['username'] = 'This username is already taken.';
        }

        if ($email && email_exists($email)) {
            $valid = false;
            $errors['email'] = 'This email is already registered.';
        }

        return new WP_REST_Response([
            'valid' => $valid,
            'errors' => $errors,
        ], 200);
    }
}
