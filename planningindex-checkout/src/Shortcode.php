<?php

if (!defined('ABSPATH')) {
    exit;
}

class PIC_Shortcode
{
    public static function init(): void
    {
        add_shortcode('planningindex_checkout', [self::class, 'render_shortcode']);
    }

    public static function render_shortcode($atts = []): string
    {
        return PIC_CheckoutDetection::render_root_div();
    }
}
