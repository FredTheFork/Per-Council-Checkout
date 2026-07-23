<?php

if (!defined('ABSPATH')) {
    exit;
}

class PIRB_CheckoutDetection
{
    public static function init(): void
    {
        add_filter('the_content', [__CLASS__, 'filter_checkout_content'], 10);
        add_filter('pmpro_checkout_preheader', [__CLASS__, 'maybe_render_react_checkout'], 10);
    }

    public static function is_checkout_page(): bool
    {
        return self::is_regional_bundles_checkout() || self::has_checkout_shortcode();
    }

    public static function is_regional_bundles_checkout(): bool
    {
        $configured_level = intval(get_option(PIRB_OPTION_LEVEL_ID, 0));

        $request_level = 0;
        if (isset($_REQUEST['pmpro_level'])) {
            $request_level = intval($_REQUEST['pmpro_level']);
        } elseif (isset($_REQUEST['level'])) {
            $request_level = intval($_REQUEST['level']);
        } elseif (isset($_GET['pmpro_level'])) {
            $request_level = intval($_GET['pmpro_level']);
        }

        if ($request_level > 0) {
            if ($configured_level > 0 && $request_level === $configured_level) {
                return true;
            }
            if ($request_level === 59) {
                return true;
            }
        }

        global $pmpro_level;
        if (is_object($pmpro_level) && isset($pmpro_level->id)) {
            $level_id = intval($pmpro_level->id);
            if ($configured_level > 0 && $level_id === $configured_level) {
                return true;
            }
            if ($level_id === 59) {
                return true;
            }
        }

        return false;
    }

    public static function has_checkout_shortcode(): bool
    {
        global $post;
        if (!$post) {
            return false;
        }
        return has_shortcode($post->post_content, 'regional_bundles_checkout');
    }

    public static function maybe_render_react_checkout(): void
    {
        if (!self::is_regional_bundles_checkout()) {
            return;
        }
        self::render_root_div();
    }

    public static function filter_checkout_content($content)
    {
        if (!self::is_checkout_page()) {
            return $content;
        }
        return self::render_root_div(true) . $content;
    }

    public static function render_root_div(bool $return = false): string
    {
        $html = '<div id="pirb-checkout-root"></div>';
        if ($return) {
            return $html;
        }
        echo $html;
        return $html;
    }
}
