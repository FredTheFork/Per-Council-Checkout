<?php

if (!defined('ABSPATH')) {
    exit;
}

class PIRB_Admin_SettingsPage
{
    public static function init(): void
    {
        add_action('admin_menu', [__CLASS__, 'add_menu']);
        add_action('admin_notices', [__CLASS__, 'notice_level_not_configured']);
    }

    public static function add_menu(): void
    {
        add_submenu_page(
            'pmpro-dashboard',
            'PI Regional Bundles',
            'PI Regional Bundles',
            'manage_options',
            'pirb-settings',
            [__CLASS__, 'render_page']
        );
    }

    public static function notice_level_not_configured(): void
    {
        if (intval(get_option(PIRB_OPTION_LEVEL_ID, 0)) === 0) {
            echo '<div class="notice notice-warning"><p><strong>Planning Index — Regional Bundles Checkout</strong> is not configured. Please <a href="' . esc_url(admin_url('admin.php?page=pirb-settings')) . '">set the membership level</a>.</p></div>';
        }
    }

    public static function render_page(): void
    {
        if (isset($_POST['pirb_admin_settings']) && check_admin_referer('pirb_admin_settings')) {
            $level_id = intval($_POST['pirb_level_id']);
            update_option(PIRB_OPTION_LEVEL_ID, $level_id);
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
        }

        $current_level = intval(get_option(PIRB_OPTION_LEVEL_ID, 59));
        $build_ready = self::is_build_ready();

        ?>
        <div class="wrap">
            <h1>Planning Index — Regional Bundles Checkout</h1>
            <p>React-based checkout for regional bundles. Configure the PMPro membership level below.</p>

            <form method="post" action="">
                <?php wp_nonce_field('pirb_admin_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Membership Level</th>
                        <td>
                            <select name="pirb_level_id">
                                <option value="0">— Select a Level —</option>
                                <?php
                                if (function_exists('pmpro_getAllLevels')) {
                                    $levels = pmpro_getAllLevels(true, true);
                                    if (is_array($levels)) {
                                        foreach ($levels as $level) {
                                            echo '<option value="' . esc_attr($level->id) . '"' . selected($current_level, intval($level->id), false) . '>' . esc_html($level->name) . ' (ID: ' . esc_html($level->id) . ')</option>';
                                        }
                                    }
                                }
                                ?>
                            </select>
                            <p class="description">Select the PMPro level for regional bundles checkout (default: 59).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Pricing Model</th>
                        <td>
                            <p class="description">Each regional bundle has a flat monthly price. The price is determined by the selected region, not by individual council count.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Build Status</th>
                        <td>
                            <?php if ($build_ready): ?>
                                <span style="color: #46b450; font-weight: bold;">Build is ready</span>
                                <p class="description">Compiled assets found in the build/ directory.</p>
                            <?php else: ?>
                                <span style="color: #dc3232; font-weight: bold;">Build not found</span>
                                <p class="description">Run <code>npm run build</code> in the react/ directory to compile assets.</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="pirb_admin_settings" class="button-primary" value="Save Settings" />
                </p>
            </form>
        </div>
        <?php
    }

    private static function is_build_ready(): bool
    {
        $js_files = glob(PIRB_PLUGIN_DIR . 'build/assets/*.js');
        if (empty($js_files)) {
            return false;
        }

        $manifest = PIRB_PLUGIN_DIR . 'build/.vite/manifest.json';
        if (file_exists($manifest)) {
            return true;
        }

        $alt_manifest = PIRB_PLUGIN_DIR . 'build/manifest.json';
        if (file_exists($alt_manifest)) {
            return true;
        }

        return false;
    }
}
