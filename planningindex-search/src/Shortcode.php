<?php
/**
 * Shortcode handler for [planning_index_search].
 * Outputs the React mount point with an inline loading spinner.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PIS_Shortcode
{
    public static function init(): void
    {
        add_shortcode('planning_index_search', [__CLASS__, 'render_shortcode']);
    }

    public static function render_shortcode($atts = []): string
    {
        nocache_headers();

        ob_start();
        ?>
        <div id="pi-search-root">
            <div style="display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:60vh;font-family:Inter,system-ui,-apple-system,sans-serif;">
                <div style="width:48px;height:48px;border:4px solid #e2e8f0;border-top-color:#1b2534;border-radius:50%;animation:pis-spin 0.8s linear infinite;margin-bottom:20px;"></div>
                <p style="color:#1b2534;font-size:1.125rem;font-weight:600;margin:0;">Loading Planning Index Search…</p>
                <p style="color:#64748b;font-size:0.875rem;margin-top:8px;">Preparing your search experience</p>
            </div>
            <style>@keyframes pis-spin{to{transform:rotate(360deg)}}</style>
        </div>
        <?php
        return ob_get_clean();
    }
}
