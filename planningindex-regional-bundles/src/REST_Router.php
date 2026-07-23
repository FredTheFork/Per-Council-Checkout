<?php

if (!defined('ABSPATH')) {
    exit;
}

class PIRB_REST_Router
{
    public static function init(): void
    {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes(): void
    {
        $ns = PIRB_REST_NAMESPACE;

        register_rest_route($ns, '/regions', [
            'methods'             => 'GET',
            'callback'            => ['PIRB_Regions_Controller', 'get_regions'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/templates', [
            'methods'             => 'GET',
            'callback'            => ['PIRB_Templates_Controller', 'get_templates'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/check-user', [
            'methods'             => 'POST',
            'callback'            => ['PIRB_CheckUser_Controller', 'check_user'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/session', [
            [
                'methods'             => 'GET',
                'callback'            => ['PIRB_Session_Controller', 'get_session'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'             => 'POST',
                'callback'            => ['PIRB_Session_Controller', 'save_session'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'             => 'DELETE',
                'callback'            => ['PIRB_Session_Controller', 'clear_session'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route($ns, '/profile', [
            [
                'methods'             => 'GET',
                'callback'            => ['PIRB_Profile_Controller', 'get_profile'],
                'permission_callback' => '__return_true',
            ],
            [
                'methods'             => 'POST',
                'callback'            => ['PIRB_Profile_Controller', 'update_profile'],
                'permission_callback' => '__return_true',
            ],
        ]);

        register_rest_route($ns, '/login', [
            'methods'             => 'POST',
            'callback'            => ['PIRB_Login_Controller', 'login'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/checkout/verify-price', [
            'methods'             => 'GET',
            'callback'            => ['PIRB_Checkout_Controller', 'verify_price'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/checkout', [
            'methods'             => 'POST',
            'callback'            => ['PIRB_Checkout_Controller', 'checkout'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/stripe-session', [
            'methods'             => 'POST',
            'callback'            => ['PIRB_StripeSession_Controller', 'create_session'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($ns, '/config', [
            'methods'             => 'GET',
            'callback'            => ['PIRB_Config_Controller', 'get_config'],
            'permission_callback' => '__return_true',
        ]);
    }
}
