<?php

if (!defined('ABSPATH')) {
    exit;
}

class PIT_Admin_SettingsPage
{
    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'add_menu']);
        add_action('admin_notices', [self::class, 'notice_level_not_configured']);
    }

    public static function add_menu(): void
    {
        add_submenu_page(
            'pmpro-dashboard',
            'Planning Index Trial Checkout Settings',
            'PI Trial Checkout',
            'manage_options',
            'pit-settings',
            [self::class, 'render_page']
        );
    }

    public static function notice_level_not_configured(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $level_id = intval(get_option(PIT_OPTION_LEVEL_ID, 0));
        if ($level_id === 0) {
            echo '<div class="notice notice-warning"><p>'
                . '<strong>Planning Index Trial Checkout:</strong> '
                . sprintf(
                    esc_html__('No trial membership level configured. %sConfigure it now%s.', 'planningindex-trial-checkout'),
                    '<a href="' . esc_url(admin_url('admin.php?page=pit-settings')) . '">',
                    '</a>'
                )
                . '</p></div>';
        }
    }

    public static function render_page(): void
    {
        if (isset($_POST['pit_save']) && check_admin_referer('pit_admin_settings')) {
            update_option(PIT_OPTION_LEVEL_ID, intval($_POST['pit_level_id']));
            echo '<div class="notice notice-success"><p>Settings saved successfully.</p></div>';
        }

        $levels = function_exists('pmpro_getAllLevels') ? pmpro_getAllLevels(true, true) : [];
        $current_level = intval(get_option(PIT_OPTION_LEVEL_ID, 0));
        $build_ready = self::is_build_ready();
        ?>
        <div class="wrap">
            <h1>Planning Index Trial Checkout Settings</h1>
            <p>Configure the free trial checkout system. This plugin provides a 14-day free trial with up to <?php echo PIT_MAX_SELECTION; ?> councils at no cost. After the trial expires, users must subscribe to the paid per-council plan.</p>

            <form method="post">
                <?php wp_nonce_field('pit_admin_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="pit_level_id">Free Trial Level</label>
                        </th>
                        <td>
                            <select name="pit_level_id" id="pit_level_id">
                                <option value="0">-- Select Level --</option>
                                <?php foreach ($levels as $level): ?>
                                    <option value="<?php echo intval($level->id); ?>" <?php selected($current_level, $level->id); ?>>
                                        <?php echo esc_html($level->name) . ' (ID: ' . $level->id . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Select the membership level to use for free trials. This level will be forced to £0 for <?php echo PIT_TRIAL_DAYS; ?> days.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Configuration</th>
                        <td>
                            <p><strong>Trial Duration:</strong> <?php echo PIT_TRIAL_DAYS; ?> days</p>
                            <p><strong>Minimum Councils:</strong> <?php echo PIT_MIN_SELECTION; ?> council</p>
                            <p><strong>Maximum Councils:</strong> <?php echo PIT_MAX_SELECTION; ?> councils</p>
                            <p><strong>Price During Trial:</strong> £0.00</p>
                            <p><strong>Price After Trial:</strong> £<?php echo PIT_UNIT_PRICE; ?>/council/month (paid per-council level)</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Build Status</th>
                        <td>
                            <?php if ($build_ready): ?>
                                <p style="color:#46b450;font-weight:600;">✓ React build detected — trial checkout is ready.</p>
                            <?php else: ?>
                                <p style="color:#dc3232;font-weight:600;">✗ No React build found.</p>
                                <p class="description">
                                    Run <code>npm install && npm run build</code> in the
                                    <code>react/</code> subdirectory of this plugin, then reload this page.
                                </p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <input type="submit" name="pit_save" class="button button-primary" value="Save Settings">
                </p>
            </form>
        </div>
        <?php
    }

    private static function is_build_ready(): bool
    {
        $build_dir = PIT_PLUGIN_DIR . 'build/';
        if (!is_dir($build_dir)) {
            return false;
        }

        $has_js = glob($build_dir . 'assets/*.js') !== false && count(glob($build_dir . 'assets/*.js')) > 0;
        $has_manifest = file_exists($build_dir . '.vite/manifest.json') || file_exists($build_dir . 'manifest.json');

        return $has_js && $has_manifest;
    }
}
