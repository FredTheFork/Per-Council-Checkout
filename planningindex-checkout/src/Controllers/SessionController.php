<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Session state endpoints.
 *
 * GET    /planningindex/v1/session  — retrieve saved session data
 * POST   /planningindex/v1/session  — save checkout step data
 * DELETE /planningindex/v1/session  — clear session after checkout
 *
 * Mirrors the legacy pmpc_save_step AJAX handler and pmpc_multi_step_handler
 * session logic, respecting the settings-precedence rule.
 */
class PIC_Session_Controller
{
    /**
     * Ensures a PHP session is started.
     */
    private static function ensure_session(): void
    {
        if (!session_id()) {
            session_start();
        }
    }

    /**
     * GET /session — retrieve saved session data.
     *
     * @return WP_REST_Response
     */
    public static function get_session(WP_REST_Request $request)
    {
        self::ensure_session();

        $data = isset($_SESSION[PIC_SESSION_KEY]) ? (array) $_SESSION[PIC_SESSION_KEY] : [];

        return new WP_REST_Response(['data' => $data], 200);
    }

    /**
     * POST /session — save checkout step data.
     *
     * Mirrors the legacy pmpc_save_step switch logic.
     *
     * @return WP_REST_Response
     */
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

        $data = isset($_SESSION[PIC_SESSION_KEY]) ? (array) $_SESSION[PIC_SESSION_KEY] : [];

        switch ($step) {
            case 1:
                $councils = array_map('sanitize_text_field', (array) ($request->get_param('councils') ?? []));
                $count = count($councils);
                if ($count < PIC_MIN_SELECTION) {
                    return new WP_REST_Response([
                        'success' => false,
                        'message' => sprintf('Please select at least %d councils.', PIC_MIN_SELECTION),
                    ], 400);
                }
                $data['councils'] = $councils;
                $data['price'] = $count * PIC_UNIT_PRICE;
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
                foreach ($fields as $f) {
                    if (isset($body[$f])) {
                        $business[$f] = sanitize_text_field($body[$f]);
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

        // Settings-precedence: if logged-in user already has saved settings,
        // strip business and template so checkout data never overrides them.
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            if (PIC_PmproHooks::should_use_settings($user_id)) {
                unset($data['business'], $data['template']);
            }
        }

        $_SESSION[PIC_SESSION_KEY] = $data;

        return new WP_REST_Response([
            'success' => true,
            'step' => $step + 1,
        ], 200);
    }

    /**
     * DELETE /session — clear the session after successful checkout.
     *
     * @return WP_REST_Response
     */
    public static function clear_session(WP_REST_Request $request)
    {
        self::ensure_session();
        unset($_SESSION[PIC_SESSION_KEY]);

        return new WP_REST_Response(['success' => true], 200);
    }
}
