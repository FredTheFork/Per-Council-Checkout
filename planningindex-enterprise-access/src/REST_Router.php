<?php

if (!defined('ABSPATH')) {
    exit;
}

class PIE_REST_Router
{
    public static function init(): void
    {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes(): void
    {
        $namespace = PIE_REST_NAMESPACE;

        register_rest_route($namespace, '/templates', [
            'methods'  => 'GET',
            'callback' => ['PIE_TemplatesController', 'get_templates'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/check-user', [
            'methods'  => 'POST',
            'callback' => ['PIE_CheckUserController', 'check'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/session', [
            'methods'  => 'GET',
            'callback' => ['PIE_SessionController', 'get_session'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/session', [
            'methods'  => 'POST',
            'callback' => ['PIE_SessionController', 'save_session'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/session', [
            'methods'  => 'DELETE',
            'callback' => ['PIE_SessionController', 'clear_session'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/profile', [
            'methods'  => 'GET',
            'callback' => ['PIE_ProfileController', 'get_profile'],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]);

        register_rest_route($namespace, '/profile', [
            'methods'  => 'POST',
            'callback' => ['PIE_ProfileController', 'update_profile'],
            'permission_callback' => function () {
                return is_user_logged_in();
            },
        ]);

        register_rest_route($namespace, '/login', [
            'methods'  => 'POST',
            'callback' => ['PIE_LoginController', 'login'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/checkout', [
            'methods'  => 'POST',
            'callback' => ['PIE_CheckoutController', 'process'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/checkout/verify-price', [
            'methods'  => 'GET',
            'callback' => ['PIE_CheckoutController', 'verify_price'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/config', [
            'methods'  => 'GET',
            'callback' => ['PIE_ConfigController', 'get_config'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($namespace, '/stripe-session', [
            'methods'  => 'POST',
            'callback' => ['PIE_StripeSessionController', 'create'],
            'permission_callback' => '__return_true',
        ]);
    }
}
