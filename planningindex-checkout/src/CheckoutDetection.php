<?php

if (!defined('ABSPATH')) {
    exit;
}

class PIC_CheckoutDetection
{
    private static $cached_result = null;

    public static function init(): void
    {
        add_action('template_redirect', [self::class, 'maybe_render_react_checkout'], 5);
        add_filter('the_content', [self::class, 'filter_checkout_content'], 20);
    }

    public static function is_checkout_page(): bool
    {
        return self::is_per_council_checkout();
    }

    public static function is_per_council_checkout(): bool
    {
        if (self::$cached_result !== null) {
            return self::$cached_result;
        }

        // When pi_complete is set, the wizard has already finished and the
        // user has been redirected to the PMPro checkout page for card
        // collection. Let the standard PMPro checkout render so the ported
        // hooks in PmproHooks.php can process the real checkout.
        if (!empty($_REQUEST['pi_complete'])) {
            self::$cached_result = false;
            return false;
        }

        $configured_level = intval(get_option(PIC_OPTION_LEVEL_ID, 0));

        // If the page uses the [planningindex_checkout] shortcode, it is
        // always a checkout page regardless of PMPro state or login status.
        if (self::has_checkout_shortcode()) {
            self::$cached_result = true;
            return true;
        }

        // If level is in the URL, treat it as a checkout page even when
        // PIC_OPTION_LEVEL_ID is not yet saved in admin settings. The React
        // app handles levelId=0 gracefully via URL param fallback.
        if ($configured_level === 0) {
            if (isset($_REQUEST['level']) || isset($_REQUEST['pmpro_level']) || isset($_GET['pmpro_level'])) {
                self::$cached_result = true;
                return true;
            }
            self::$cached_result = false;
            return false;
        }

        if (!function_exists('pmpro_is_checkout') || !pmpro_is_checkout()) {
            $request_uri = isset($_SERVER['REQUEST_URI']) ? parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) : '';
            if ($request_uri) {
                $checkout_patterns = ['/membership/checkout', '/checkout', '/register'];
                $matched = false;
                foreach ($checkout_patterns as $pattern) {
                    if (strpos($request_uri, $pattern) !== false) {
                        $matched = true;
                        break;
                    }
                }
                if (!$matched && !isset($_GET['level']) && !isset($_REQUEST['level']) && !isset($_REQUEST['pmpro_level'])) {
                    self::$cached_result = false;
                    return false;
                }
            } else {
                self::$cached_result = false;
                return false;
            }
        }

        if (empty($_REQUEST['level']) && empty($_REQUEST['pmpro_level']) && empty($_GET['pmpro_level'])) {
            if (!isset($GLOBALS['pmpro_level']->id)) {
                // Do NOT cache false here. PMPro globals may not be
                // initialized yet for logged-out users at template_redirect
                // priority 5. A later call (e.g. wp_enqueue_scripts) may
                // find the globals set and correctly detect the checkout.
                return false;
            }
        }

        $current_level = 0;

        if (isset($_REQUEST['pmpro_level'])) {
            $current_level = intval($_REQUEST['pmpro_level']);
        } elseif (isset($_REQUEST['level'])) {
            $current_level = intval($_REQUEST['level']);
        } elseif (isset($_GET['pmpro_level'])) {
            $current_level = intval($_GET['pmpro_level']);
        } elseif (isset($GLOBALS['pmpro_level']->id)) {
            $current_level = intval($GLOBALS['pmpro_level']->id);
        }

        if ($current_level === 0) {
            // Same as above: do not cache false when the level simply
            // couldn't be resolved yet.
            return false;
        }

        self::$cached_result = ($current_level === $configured_level);
        return self::$cached_result;
    }

    /**
     * Check whether the current post content contains the
     * [planningindex_checkout] shortcode.
         */
    public static function has_checkout_shortcode(): bool
    {
        $post = get_post();
        if (!$post || empty($post->post_content)) {
            return false;
        }
        return strpos($post->post_content, 'planningindex_checkout') !== false;
    }

    public static function maybe_render_react_checkout(): void
    {
        if (!self::is_per_council_checkout()) {
            return;
        }

        // Never render the React wizard when the wizard is already complete.
        if (!empty($_REQUEST['pi_complete'])) {
            return;
        }

        if (isset($_GET['confirm']) || isset($_GET['review'])) {
            return;
        }

        $is_real_load = (
            $_SERVER['REQUEST_METHOD'] === 'GET' ||
            ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($_POST['submit-checkout']))
        );

        if (!$is_real_load) {
            return;
        }

        ob_start();
        get_header();
        echo self::render_root_div();
        get_footer();

        $output = ob_get_clean();
        echo $output;
        exit;
    }

    public static function render_root_div(): string
    {
        return '<div id="planning-checkout-root">'
            . '<div style="display:flex;align-items:center;justify-content:center;min-height:60vh;font-family:system-ui,sans-serif;color:#64748b;">'
            . '<div style="text-center">'
            . '<div style="width:40px;height:40px;border:3px solid #e2e8f0;border-top-color:#2563eb;border-radius:50%;animation:pic-spin 0.8s linear infinite;margin:0 auto 16px;"></div>'
            . '<p style="font-size:14px;">Loading checkout...</p>'
            . '</div>'
            . '</div>'
            . '<style>@keyframes pic-spin{to{transform:rotate(360deg)}}</style>'
            . '</div>';
    }

    public static function filter_checkout_content($content): string
    {
        if (!self::is_per_council_checkout()) {
            return $content;
        }

        // Do not replace content when the wizard is complete — PMPro's own
        // checkout form must render so the ported hooks can fire.
        if (!empty($_REQUEST['pi_complete'])) {
            return $content;
        }

        return self::render_root_div();
    }
}
