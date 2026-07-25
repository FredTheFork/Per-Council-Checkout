<?php

if (!defined('ABSPATH')) {
    exit;
}

class PIE_Shortcode
{
    public static function init(): void
    {
        add_shortcode('enterprise_checkout', [__CLASS__, 'render']);
    }

    public static function render($atts = []): string
    {
        return '<div id="pmpe-checkout-root"></div>';
    }
}
