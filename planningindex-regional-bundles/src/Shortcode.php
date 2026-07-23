<?php

if (!defined('ABSPATH')) {
    exit;
}

class PIRB_Shortcode
{
    public static function init(): void
    {
        add_shortcode('regional_bundles_checkout', [__CLASS__, 'render']);
    }

    public static function render($atts = []): string
    {
        return '<div id="pirb-checkout-root"></div>';
    }
}
