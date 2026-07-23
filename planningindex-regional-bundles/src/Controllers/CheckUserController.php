<?php

if (!defined('ABSPATH')) {
    exit;
}

class PIRB_CheckUser_Controller
{
    public static function check_user(WP_REST_Request $request)
    {
        $body = $request->get_json_params();
        $username = isset($body['username']) ? sanitize_text_field($body['username']) : '';
        $email = isset($body['email']) ? sanitize_email($body['email']) : '';

        $errors = [];

        if (!empty($username) && username_exists($username)) {
            $errors['username'] = 'This username is already taken.';
        }

        if (!empty($email) && email_exists($email)) {
            $errors['email'] = 'This email is already registered.';
        }

        return new WP_REST_Response([
            'valid'  => empty($errors),
            'errors' => $errors,
        ], 200);
    }
}
