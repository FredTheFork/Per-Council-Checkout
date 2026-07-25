<?php

if (!defined('ABSPATH')) {
    exit;
}

class PIT_Shortcode
{
    public static function init(): void
    {
        add_shortcode('planningindex_trial_checkout', [self::class, 'render_shortcode']);
    }

    public static function render_shortcode($atts = []): string
    {
        return PIT_CheckoutDetection::render_root_div();
    }
}
