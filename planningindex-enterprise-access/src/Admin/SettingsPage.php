<?php

if (!defined('ABSPATH')) {
    exit;
}

class PIE_Admin_SettingsPage
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
            'PI Enterprise Access',
            'PI Enterprise Access',
            'manage_options',
            'pie-settings',
            [__CLASS__, 'render_page']
        );
    }

    public static function notice_level_not_configured(): void
    {
        if (intval(get_option(PIE_OPTION_LEVEL_ID, 0)) === 0) {
            echo '<div class="notice notice-warning"><p><strong>Planning Index — Enterprise Access Checkout</strong> is not configured. Please <a href="' . esc_url(admin_url('admin.php?page=pie-settings')) . '">set the membership level</a>.</p></div>';
        }
    }

    public static function render_page(): void
    {
        if (isset($_POST['pie_admin_settings']) && check_admin_referer('pie_admin_settings')) {
            $level_id = intval($_POST['pie_level_id']);
            update_option(PIE_OPTION_LEVEL_ID, $level_id);
            echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
        }

        $current_level = intval(get_option(PIE_OPTION_LEVEL_ID, 60));
        $build_ready = self::is_build_ready();
        $council_count = count(PIE_EnterpriseData::all_councils());
        $enterprise_price = PIE_EnterpriseData::get_enterprise_price();

        ?>
        <div class="wrap">
            <h1>Planning Index — Enterprise Access Checkout</h1>
            <p>React-based checkout for Enterprise access. Grants access to all UK councils.</p>

            <form method="post" action="">
                <?php wp_nonce_field('pie_admin_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">Membership Level</th>
                        <td>
                            <select name="pie_level_id">
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
                            <p class="description">Select the PMPro level for Enterprise access checkout (default: 60).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Enterprise Price</th>
                        <td>
                            <p class="description">
                                <?php if ($enterprise_price > 0): ?>
                                    <strong>£<?php echo esc_html(number_format($enterprise_price, 2)); ?>/month</strong> — pulled from the selected level's initial payment.
                                <?php else: ?>
                                    Not configured — set the level's initial payment in PMPro.
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Council Coverage</th>
                        <td>
                            <p class="description"><strong><?php echo esc_html($council_count); ?> UK councils</strong> — Enterprise grants access to all councils automatically.</p>
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
                    <input type="submit" name="pie_admin_settings" class="button-primary" value="Save Settings" />
                </p>
            </form>
        </div>
        <?php
    }

    private static function is_build_ready(): bool
    {
        $js_files = glob(PIE_PLUGIN_DIR . 'build/assets/*.js');
        if (empty($js_files)) {
            return false;
        }

        $manifest = PIE_PLUGIN_DIR . 'build/.vite/manifest.json';
        if (file_exists($manifest)) {
            return true;
        }

        $alt_manifest = PIE_PLUGIN_DIR . 'build/manifest.json';
        if (file_exists($alt_manifest)) {
            return true;
        }

        return false;
    }
}
