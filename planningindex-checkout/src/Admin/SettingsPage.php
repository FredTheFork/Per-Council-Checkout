<?php

if (!defined('ABSPATH')) {
    exit;
}

class PIC_Admin_SettingsPage
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
            'Planning Index Checkout Settings',
            'PI Checkout',
            'manage_options',
            'pic-settings',
            [self::class, 'render_page']
        );
    }

    public static function notice_level_not_configured(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        $level_id = intval(get_option(PIC_OPTION_LEVEL_ID, 0));
        if ($level_id === 0) {
            echo '<div class="notice notice-warning"><p>'
                . '<strong>Planning Index Checkout:</strong> '
                . sprintf(
                    esc_html__('No membership level configured. %sConfigure it now%s.', 'planningindex-checkout'),
                    '<a href="' . esc_url(admin_url('admin.php?page=pic-settings')) . '">',
                    '</a>'
                )
                . '</p></div>';
        }
    }

    public static function render_page(): void
    {
        if (isset($_POST['pic_save']) && check_admin_referer('pic_admin_settings')) {
            update_option(PIC_OPTION_LEVEL_ID, intval($_POST['pic_level_id']));
            echo '<div class="notice notice-success"><p>Settings saved successfully.</p></div>';
        }

        $levels = function_exists('pmpro_getAllLevels') ? pmpro_getAllLevels(true, true) : [];
        $current_level = intval(get_option(PIC_OPTION_LEVEL_ID, 0));
        $build_ready = self::is_build_ready();
        ?>
        <div class="wrap">
            <h1>Planning Index Checkout Settings</h1>
            <p>Configure the per-council checkout system. This plugin replaces the legacy PMPro Per Council Selector with a modern React-based checkout wizard.</p>

            <form method="post">
                <?php wp_nonce_field('pic_admin_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="pic_level_id">Per Council Level</label>
                        </th>
                        <td>
                            <select name="pic_level_id" id="pic_level_id">
                                <option value="0">-- Select Level --</option>
                                <?php foreach ($levels as $level): ?>
                                    <option value="<?php echo intval($level->id); ?>" <?php selected($current_level, $level->id); ?>>
                                        <?php echo esc_html($level->name) . ' (ID: ' . $level->id . ')'; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Select the membership level that uses per-council pricing (£<?php echo PIC_UNIT_PRICE; ?>/council).</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Configuration</th>
                        <td>
                            <p><strong>Unit Price:</strong> £<?php echo PIC_UNIT_PRICE; ?> per council</p>
                            <p><strong>Minimum Selection:</strong> <?php echo PIC_MIN_SELECTION; ?> councils</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Build Status</th>
                        <td>
                            <?php if ($build_ready): ?>
                                <p style="color:#46b450;font-weight:600;">✓ React build detected — checkout is ready.</p>
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
                    <input type="submit" name="pic_save" class="button button-primary" value="Save Settings">
                </p>
            </form>
        </div>
        <?php
    }

    private static function is_build_ready(): bool
    {
        $build_dir = PIC_PLUGIN_DIR . 'build/';
        if (!is_dir($build_dir)) {
            return false;
        }

        $has_js = glob($build_dir . 'assets/*.js') !== false && count(glob($build_dir . 'assets/*.js')) > 0;
        $has_manifest = file_exists($build_dir . '.vite/manifest.json') || file_exists($build_dir . 'manifest.json');

        return $has_js && $has_manifest;
    }
}
