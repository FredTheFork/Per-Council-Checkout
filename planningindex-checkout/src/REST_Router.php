<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registers all REST API routes under the planningindex/v1 namespace.
 */
class PIC_REST_Router
{
    public static function init(): void
    {
        add_action('rest_api_init', [self::class, 'register_routes']);
    }

    public static function register_routes(): void
    {
        $namespace = PIC_REST_NAMESPACE;

        // Councils
        register_rest_route($namespace, '/councils', [
            'methods' => 'GET',
            'callback' => [PIC_Councils_Controller::class, 'get_councils'],
            'permission_callback' => '__return_true',
        ]);

        // Templates
        register_rest_route($namespace, '/templates', [
            'methods' => 'GET',
            'callback' => [PIC_Templates_Controller::class, 'get_templates'],
            'permission_callback' => '__return_true',
        ]);

        // User validation
        register_rest_route($namespace, '/check-user', [
            'methods' => 'POST',
            'callback' => [PIC_CheckUser_Controller::class, 'check_user'],
            'permission_callback' => '__return_true',
        ]);

        // Session state
        register_rest_route($namespace, '/session', [
            'methods' => 'GET',
            'callback' => [PIC_Session_Controller::class, 'get_session'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($namespace, '/session', [
            'methods' => 'POST',
            'callback' => [PIC_Session_Controller::class, 'save_session'],
            'permission_callback' => '__return_true',
        ]);
        register_rest_route($namespace, '/session', [
            'methods' => 'DELETE',
            'callback' => [PIC_Session_Controller::class, 'clear_session'],
            'permission_callback' => '__return_true',
        ]);

        // User profile
        register_rest_route($namespace, '/profile', [
            'methods' => 'GET',
            'callback' => [PIC_Profile_Controller::class, 'get_profile'],
            'permission_callback' => [PIC_Profile_Controller::class, 'require_login'],
        ]);
        register_rest_route($namespace, '/profile', [
            'methods' => 'POST',
            'callback' => [PIC_Profile_Controller::class, 'update_profile'],
            'permission_callback' => [PIC_Profile_Controller::class, 'require_login'],
        ]);

        // Login
        register_rest_route($namespace, '/login', [
            'methods' => 'POST',
            'callback' => [PIC_Login_Controller::class, 'login'],
            'permission_callback' => '__return_true',
        ]);

        // Price verification (before final submit)
        register_rest_route($namespace, '/checkout/verify-price', [
            'methods' => 'GET',
            'callback' => [PIC_Checkout_Controller::class, 'verify_price'],
            'permission_callback' => '__return_true',
        ]);

        // Checkout processing
        register_rest_route($namespace, '/checkout', [
            'methods' => 'POST',
            'callback' => [PIC_Checkout_Controller::class, 'checkout'],
            'permission_callback' => '__return_true',
        ]);

        // Configuration
        register_rest_route($namespace, '/config', [
            'methods' => 'GET',
            'callback' => [PIC_Config_Controller::class, 'get_config'],
            'permission_callback' => '__return_true',
        ]);
    }
}
