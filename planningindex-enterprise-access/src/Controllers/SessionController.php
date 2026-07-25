<?php

if (!defined('ABSPATH')) {
    exit;
}

class PIE_SessionController
{
    public static function get_session(WP_REST_Request $request)
    {
        if (!session_id()) {
            session_start();
        }

        $data = isset($_SESSION[PIE_SESSION_KEY]) ? (array) $_SESSION[PIE_SESSION_KEY] : [];

        $user_id = get_current_user_id();
        if ($user_id > 0 && PIE_PmproHooks::should_use_settings($user_id)) {
            unset($data['template'], $data['business']);
        }

        return new WP_REST_Response(['data' => $data], 200);
    }

    public static function save_session(WP_REST_Request $request)
    {
        if (!session_id()) {
            session_start();
        }

        $body = $request->get_json_params();
        $step = isset($body['step']) ? intval($body['step']) : 0;

        if (!isset($_SESSION[PIE_SESSION_KEY]) || !is_array($_SESSION[PIE_SESSION_KEY])) {
            $_SESSION[PIE_SESSION_KEY] = [];
        }

        $session = &$_SESSION[PIE_SESSION_KEY];

        // Step 1: Enterprise benefits — nothing to save
        if ($step === 1) {
            // No data to persist for enterprise benefits step
        }

        if ($step === 2) {
            if (isset($body['template'])) {
                $session['template'] = sanitize_text_field($body['template']);
            }
            if (isset($body['business']) && is_array($body['business'])) {
                $session['business'] = [];
                foreach (['pmpe_company_name', 'pmpe_business_email', 'pmpe_business_phone', 'pmpe_company_address', 'pmpe_website', 'pmpe_vat_number'] as $field) {
                    if (isset($body['business'][$field])) {
                        $session['business'][$field] = sanitize_text_field($body['business'][$field]);
                    }
                }
            }
        }

        if ($step === 3) {
            if (isset($body['username'])) {
                $session['username'] = sanitize_text_field($body['username']);
            }
            if (isset($body['password'])) {
                $session['password'] = $body['password'];
            }
            if (isset($body['email'])) {
                $session['email'] = sanitize_email($body['email']);
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'step'    => $step + 1,
        ], 200);
    }

    public static function clear_session(WP_REST_Request $request)
    {
        if (!session_id()) {
            session_start();
        }
        unset($_SESSION[PIE_SESSION_KEY]);
        return new WP_REST_Response(['success' => true], 200);
    }
}
