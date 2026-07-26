<?php
/**
 * Asset enqueuing, manifest reading, config injection, and
 * SiteGround Optimizer exclusion filters.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PIS_AssetEnqueue
{
    private static $instance = null;

    public static function init(): void
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
    }

    private function __construct()
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_filter('script_loader_tag', [$this, 'add_module_type'], 10, 3);
        add_action('wp_head', [$this, 'nuclear_inject_config'], 1);
        add_action('wp_head', [$this, 'enqueue_google_fonts'], 2);

        // SiteGround Optimizer exclusion filters
        add_filter('sgo_javascript_combine_exclude', [$this, 'sgo_exclude']);
        add_filter('sgo_js_minify_exclude', [$this, 'sgo_exclude']);
        add_filter('sgo_js_async_combine_exclude', [$this, 'sgo_exclude']);
        add_filter('sgo_js_lazy_load_exclude', [$this, 'sgo_exclude']);
        add_filter('sgo_defer_render_blocking_js_exclude', [$this, 'sgo_exclude']);
    }

    /**
     * Enqueue compiled React JS and CSS from the build/ directory.
     * Only loads on pages containing the [planning_index_search] shortcode.
     */
    public function enqueue_assets(): void
    {
        if (!pis_page_has_search_shortcode()) {
            return;
        }

        $manifest = pis_read_vite_manifest();

        if ($manifest && !empty($manifest['index.html']['file'])) {
            $js_relative = $manifest['index.html']['file'];
            $js_file = PIS_PLUGIN_DIR . 'build/' . ltrim($js_relative, '/');

            if (file_exists($js_file)) {
                $js_url = PIS_PLUGIN_URL . 'build/' . ltrim($js_relative, '/');
                $version = (string) filemtime($js_file);

                wp_enqueue_script(
                    'pis-search-js',
                    $js_url,
                    [],
                    $version,
                    true
                );
            }

            // CSS is also enqueued here as a fallback (nuclear injection is the primary method)
            if (!empty($manifest['index.html']['css'])) {
                $css_relative = $manifest['index.html']['css'][0];
                $css_file = PIS_PLUGIN_DIR . 'build/' . ltrim($css_relative, '/');

                if (file_exists($css_file)) {
                    $css_url = PIS_PLUGIN_URL . 'build/' . ltrim($css_relative, '/');
                    $css_version = (string) filemtime($css_file);

                    wp_enqueue_style(
                        'pis-search-css',
                        $css_url,
                        [],
                        $css_version
                    );
                }
            }
        } else {
            // Fallback: scan build/assets/ for index files
            $this->enqueue_from_assets_scan();
        }

        // Enqueue Mapbox GL JS and CSS (version 3.0.1, matching legacy plugin)
        wp_enqueue_style(
            'mapbox-gl-css',
            'https://api.mapbox.com/mapbox-gl-js/v3.0.1/mapbox-gl.css',
            [],
            '3.0.1'
        );

        wp_enqueue_script(
            'mapbox-gl-js',
            'https://api.mapbox.com/mapbox-gl-js/v3.0.1/mapbox-gl.js',
            [],
            '3.0.1',
            true
        );
    }

    /**
     * Fallback: scan build/assets/ for compiled JS and CSS files
     * when the Vite manifest is not available.
     */
    private function enqueue_from_assets_scan(): void
    {
        $assets_dir = PIS_PLUGIN_DIR . 'build/assets/';
        if (!is_dir($assets_dir)) {
            return;
        }

        $js_files = glob($assets_dir . 'index-*.js');
        if (!empty($js_files) && file_exists($js_files[0])) {
            $js_relative = 'assets/' . basename($js_files[0]);
            $js_url = PIS_PLUGIN_URL . 'build/' . $js_relative;
            $version = (string) filemtime($js_files[0]);

            wp_enqueue_script(
                'pis-search-js',
                $js_url,
                [],
                $version,
                true
            );
        }

        $css_files = glob($assets_dir . 'index-*.css');
        if (!empty($css_files) && file_exists($css_files[0])) {
            $css_relative = 'assets/' . basename($css_files[0]);
            $css_url = PIS_PLUGIN_URL . 'build/' . $css_relative;
            $css_version = (string) filemtime($css_files[0]);

            wp_enqueue_style(
                'pis-search-css',
                $css_url,
                [],
                $css_version
            );
        }
    }

    /**
     * Convert the pis-search-js script tag to type="module"
     * so the browser treats it as an ES module (required for Vite output).
     */
    public function add_module_type(string $tag, string $handle, string $src): string
    {
        if ($handle !== 'pis-search-js') {
            return $tag;
        }

        return '<script type="module" src="' . esc_url($src) . '"></script>' . "\n";
    }

    /**
     * Inject the window.PlanningIndexSearch config object as an inline script
     * in the page head, before the React bundle loads.
     */
    public function nuclear_inject_config(): void
    {
        if (!pis_page_has_search_shortcode()) {
            return;
        }

        $config = $this->build_config();

        echo '<script id="pis-config-nuclear">' . "\n";
        echo 'window.PlanningIndexSearch = ' . wp_json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';' . "\n";
        echo '</script>' . "\n";
    }

    /**
     * Build the config object the React app reads on startup.
     */
    private function build_config(): array
    {
        $user_id = get_current_user_id();
        $is_admin = $user_id > 0 && current_user_can('manage_options');

        $mapbox_token = '';
        if (defined('PI_MAPBOX_TOKEN')) {
            $mapbox_token = PI_MAPBOX_TOKEN;
        } else {
            $mapbox_token = (string) get_option('pi_mapbox_token', '');
        }

        // Derive allowed authorities from pmpc_selected_councils user meta
        $allowed_authorities = [];
        if ($user_id > 0 && !$is_admin) {
            $selected_councils = get_user_meta($user_id, 'pmpc_selected_councils', true);
            if (!empty($selected_councils) && is_array($selected_councils)) {
                foreach ($selected_councils as $name) {
                    $term = get_term_by('name', trim($name), 'authority');
                    if ($term && !is_wp_error($term)) {
                        $allowed_authorities[] = (int) $term->term_id;
                    }
                }
            }
        }

        return [
            'restBase'           => rest_url('pi/v1/apps'),
            'restRoot'           => rest_url('pi/v1'),
            'nonce'              => wp_create_nonce('wp_rest'),
            'mapboxToken'        => $mapbox_token,
            'isLoggedIn'         => is_user_logged_in(),
            'userId'             => $user_id,
            'isAdmin'            => $is_admin,
            'allowedAuthorities' => array_values(array_unique($allowed_authorities)),
            'pluginUrl'          => PIS_PLUGIN_URL,
            'version'            => PIS_VERSION,
        ];
    }

    /**
     * Enqueue Google Fonts for Inter and Plus Jakarta Sans.
     */
    public function enqueue_google_fonts(): void
    {
        if (!pis_page_has_search_shortcode()) {
            return;
        }

        wp_enqueue_style(
            'pis-google-fonts',
            'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap',
            [],
            null
        );
    }

    /**
     * SiteGround Optimizer exclusion callback.
     * Adds the pis-search-js handle to the exclusion list so SG
     * does not combine, minify, defer, or lazy-load the React bundle.
     */
    public function sgo_exclude($exclude): array
    {
        if (!is_array($exclude)) {
            $exclude = [];
        }
        $exclude[] = 'pis-search-js';
        return $exclude;
    }
}
