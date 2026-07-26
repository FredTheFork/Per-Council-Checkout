<?php
/**
 * Plugin Name: Planning Index Search
 * Description: Modern React-based search UI for planning applications, replacing the legacy planning-index-frontend plugin.
 * Version: 1.0.0
 * Author: Planning Index
 * Text Domain: planningindex-search
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PIS_VERSION', '1.0.0');
define('PIS_PLUGIN_FILE', __FILE__);
define('PIS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PIS_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PIS_REST_NAMESPACE', 'planningindex/v1');

require_once PIS_PLUGIN_DIR . 'src/Plugin.php';
require_once PIS_PLUGIN_DIR . 'src/Shortcode.php';
require_once PIS_PLUGIN_DIR . 'src/AssetEnqueue.php';

register_activation_hook(__FILE__, ['PlanningIndexSearch', 'activate']);
register_deactivation_hook(__FILE__, ['PlanningIndexSearch', 'deactivate']);

PlanningIndexSearch::instance();

/**
 * Nuclear CSS injection — outputs compiled CSS as an inline <style> tag
 * at three hook priorities to bypass SiteGround Optimizer and theme
 * caching interference.
 */
function pis_inject_search_css_nuclear()
{
    if (!pis_page_has_search_shortcode()) {
        return;
    }

    $manifest = pis_read_vite_manifest();
    if (!$manifest || empty($manifest['index.html']['css'])) {
        return;
    }

    $css_relative = $manifest['index.html']['css'][0];
    $css_file = PIS_PLUGIN_DIR . 'build/' . ltrim($css_relative, '/');

    if (!file_exists($css_file)) {
        return;
    }

    $css_content = file_get_contents($css_file);
    if ($css_content === false) {
        return;
    }

    echo '<style id="pis-search-css-nuclear">' . "\n" . $css_content . "\n</style>\n";
}
add_action('wp_head', 'pis_inject_search_css_nuclear', 1);
add_action('wp_head', 'pis_inject_search_css_nuclear', 999);
add_action('wp_footer', 'pis_inject_search_css_nuclear', 1);

/**
 * Check if the current page/post contains the [planning_index_search] shortcode.
 */
function pis_page_has_search_shortcode()
{
    if (!is_singular()) {
        return false;
    }

    $post = get_queried_object();
    if (!$post || empty($post->post_content)) {
        return false;
    }

    return has_shortcode($post->post_content, 'planning_index_search');
}

/**
 * Read the Vite manifest file.
 * Checks build/.vite/manifest.json first, then build/manifest.json as fallback.
 *
 * @return array|null Manifest data or null if not found.
 */
function pis_read_vite_manifest()
{
    $manifest_paths = [
        PIS_PLUGIN_DIR . 'build/.vite/manifest.json',
        PIS_PLUGIN_DIR . 'build/manifest.json',
    ];

    foreach ($manifest_paths as $path) {
        if (file_exists($path)) {
            $content = file_get_contents($path);
            if ($content !== false) {
                $data = json_decode($content, true);
                if (is_array($data)) {
                    return $data;
                }
            }
        }
    }

    return null;
}
