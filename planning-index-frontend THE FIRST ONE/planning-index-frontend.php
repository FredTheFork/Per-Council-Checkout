<?php
/**
 * Plugin Name: Planning Index Frontend THE FIRST ONE
 * Description: Search UI and display grid for planning_app posts with PMPro integration.
 * Version: 0.1
 * Author: You
 *
 * NOTE: This plugin has been superseded by "Planning Index Search" (planningindex-search).
 * If the new plugin is active, this plugin becomes a no-op to prevent conflicts.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Reversible guard: if the new Planning Index Search plugin is active,
// bail early so none of our hooks, shortcodes, or assets load.
if ( in_array( 'planningindex-search/planningindex-search.php', (array) get_option( 'active_plugins', [] ), true ) ) {
    return;
}

// Also check for network-activated plugins on multisite
if ( is_multisite() ) {
    $network_plugins = (array) get_site_option( 'active_sitewide_plugins', [] );
    if ( isset( $network_plugins['planningindex-search/planningindex-search.php'] ) ) {
        return;
    }
}

require_once __DIR__ . '/includes/frontend.php';
require_once __DIR__ . '/includes/cleanup.php';
require_once __DIR__ . '/includes/rest-filters.php';
require_once __DIR__ . '/includes/query-filters.php';
require_once __DIR__ . '/includes/user-apps-rest.php';
require_once __DIR__ . '/includes/rest-fields.php';
require_once __DIR__ . '/includes/apps-rest.php';
