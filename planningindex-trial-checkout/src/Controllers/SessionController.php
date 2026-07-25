<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Session state endpoints for the trial checkout.
 */
class PIT_Session_Controller
{
    private static function ensure_session(): void
    {
        if (!session_id()) {
            session_start();
        }
    }

    public static function get_session(WP_REST_Request $request)
    {
        self::ensure_session();

        $data = isset($_SESSION[PIT_SESSION_KEY]) ? (array) $_SESSION[PIT_SESSION_KEY] : [];

        return new WP_REST_Response(['data' => $data], 200);
    }

    public static function save_session(WP_REST_Request $request)
    {
        self::ensure_session();

        $step = intval($request->get_param('step') ?? 0);
        if (!$step || $step >= 4) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Invalid step',
            ], 400);
        }

        $data = isset($_SESSION[PIT_SESSION_KEY]) ? (array) $_SESSION[PIT_SESSION_KEY] : [];

        switch ($step) {
            case 1:
                $councils = array_map('sanitize_text_field', (array) ($request->get_param('councils') ?? []));
                $count = count($councils);
                if ($count < PIT_MIN_SELECTION) {
                    return new WP_REST_Response([
                        'success' => false,
                        'message' => sprintf('Please select at least %d council.', PIT_MIN_SELECTION),
                    ], 400);
                }
                if ($count > PIT_MAX_SELECTION) {
                    return new WP_REST_Response([
                        'success' => false,
                        'message' => sprintf('You can select up to %d councils for your free trial.', PIT_MAX_SELECTION),
                    ], 400);
                }
                $data['councils'] = $councils;
                // Price is always £0 for the trial
                $data['price'] = 0;
                break;

            case 2:
                $data['template'] = sanitize_text_field($request->get_param('template') ?? 'professional');
                $business = [];
                $fields = [
                    'pmpc_company_name',
                    'pmpc_business_email',
                    'pmpc_business_phone',
                    'pmpc_company_address',
                    'pmpc_website',
                    'pmpc_vat_number',
                ];
                $body = $request->get_json_params() ?: [];
                $business_input = isset($body['business']) && is_array($body['business']) ? $body['business'] : $body;
                foreach ($fields as $f) {
                    if (isset($business_input[$f])) {
                        $business[$f] = sanitize_text_field($business_input[$f]);
                    }
                }
                $data['business'] = $business;
                break;

            case 3:
                if (!is_user_logged_in()) {
                    $data['username'] = sanitize_user($request->get_param('username') ?? '');
                    $data['password'] = $request->get_param('password') ?? '';
                    $data['email'] = sanitize_email($request->get_param('email') ?? '');
                }
                break;
        }

        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            if (PIT_PmproHooks::should_use_settings($user_id)) {
                unset($data['business'], $data['template']);
            }
        }

        $_SESSION[PIT_SESSION_KEY] = $data;

        return new WP_REST_Response([
            'success' => true,
            'step' => $step + 1,
        ], 200);
    }

    public static function clear_session(WP_REST_Request $request)
    {
        self::ensure_session();
        unset($_SESSION[PIT_SESSION_KEY]);

        return new WP_REST_Response(['success' => true], 200);
    }
}
