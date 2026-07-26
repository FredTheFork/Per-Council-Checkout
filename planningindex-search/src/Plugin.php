<?php
/**
 * Main plugin singleton class.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PlanningIndexSearch
{
    private static $instance = null;

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('plugins_loaded', [$this, 'bootstrap']);
    }

    public function bootstrap(): void
    {
        PIS_Shortcode::init();
        PIS_AssetEnqueue::init();
    }

    public static function activate(): void
    {
        flush_rewrite_rules();
    }

    public static function deactivate(): void
    {
        flush_rewrite_rules();
    }
}
