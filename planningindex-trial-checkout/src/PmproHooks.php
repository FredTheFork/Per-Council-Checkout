<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * PMPro integration hooks for the free trial checkout.
 *
 * The trial checkout creates a PMPro membership at £0 for 14 days.
 * No Stripe payment is processed — the user gets full access during
 * the trial, then must renew at the paid per-council level when it
 * expires.
 */
class PIT_PmproHooks
{
    public static function init(): void
    {
        // Settings-precedence helper
        add_filter('pit_should_use_settings', [self::class, 'should_use_settings'], 10, 1);

        // Validation: ensure councils are present (price is always £0 for trial)
        add_filter('pmpro_registration_checks', [self::class, 'registration_checks'], 10, 1);

        // Override the checkout level price to force £0 for the trial
        add_filter('pmpro_checkout_level', [self::class, 'checkout_level_price'], 20, 1);

        // Safety net: set request price fields to £0 before processing
        add_action('pmpro_checkout_before_processing', [self::class, 'before_processing']);
        add_action('pmpro_checkout_before_payment', [self::class, 'before_payment'], 10, 1);

        // Save user meta after successful checkout — including trial markers
        add_action('pmpro_after_checkout', [self::class, 'after_checkout'], 10, 2);

        // Restore session data into $_REQUEST on the PMPro checkout page load
        add_action('pmpro_checkout_preheader', [self::class, 'restore_session'], 5);

        // Load our custom checkout template on the pi_complete page
        add_action('pmpro_checkout_preheader', [self::class, 'load_custom_template'], 10);

        // Inject hidden custom fields (councils, template) into PMPro's checkout form
        add_action('pmpro_checkout_after_billing_fields', [self::class, 'inject_hidden_fields'], 10);

        // Pre-populate billing fields from session data
        add_filter('pmpro_checkout_order', [self::class, 'prepopulate_billing'], 10, 1);

        // Hide account creation fields for logged-in users
        add_filter('pmpro_checkout_skip_account_fields', [self::class, 'skip_account_fields_for_logged_in'], 10, 1);

        // Trial expiration check — runs on every page load for trial users
        add_action('init', [self::class, 'check_trial_expiration'], 20);

        // Restrict access for expired trial users
        add_filter('pmpro_has_membership_access_filter', [self::class, 'restrict_expired_trial'], 10, 3);
    }

    // ── Settings precedence ──────────────────────────────────────────

    public static function should_use_settings(int $user_id): bool
    {
        if ($user_id <= 0) {
            return false;
        }
        $settings_data = get_user_meta($user_id, '_pi_business_info', true);
        if (!is_array($settings_data)) {
            return false;
        }
        return !empty($settings_data['settings_updated_at']);
    }

    // ── Price helpers ────────────────────────────────────────────────

    /**
     * Returns true when the current request is for the configured trial level.
     */
    public static function is_trial_checkout(): bool
    {
        $configured_level = intval(get_option(PIT_OPTION_LEVEL_ID, 0));
        if ($configured_level === 0) {
            return false;
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
            return false;
        }

        return $current_level === $configured_level;
    }

    // ── Validation ───────────────────────────────────────────────────

    public static function registration_checks($ok)
    {
        if (!self::is_trial_checkout()) {
            return $ok;
        }

        if (empty($_REQUEST['pmpc_councils'])) {
            pmpro_setMessage(
                sprintf('Please select at least %d council.', PIT_MIN_SELECTION),
                'pmpro_error'
            );
            return false;
        }

        $selected = array_map('sanitize_text_field', (array) $_REQUEST['pmpc_councils']);
        $count = count($selected);

        if ($count < PIT_MIN_SELECTION) {
            pmpro_setMessage(
                sprintf('Please select at least %d council.', PIT_MIN_SELECTION),
                'pmpro_error'
            );
            return false;
        }

        if ($count > PIT_MAX_SELECTION) {
            pmpro_setMessage(
                sprintf('You can select up to %d councils for your free trial.', PIT_MAX_SELECTION),
                'pmpro_error'
            );
            return false;
        }

        // Price is always £0 for the trial — force it
        $_REQUEST['pmpc_councils'] = $selected;
        $_REQUEST['pmpc_calculated_price'] = '0.00';

        return $ok;
    }

    // ── Level price override — force £0 ──────────────────────────────

    public static function checkout_level_price($level)
    {
        $configured_level = intval(get_option(PIT_OPTION_LEVEL_ID, 0));

        if (empty($configured_level) || intval($level->id) !== $configured_level) {
            return $level;
        }

        // Force the trial level to £0 — no payment during the trial
        $level->initial_payment = 0;
        $level->billing_amount = 0;
        $level->trial_amount = 0;
        $level->trial_limit = PIT_TRIAL_DAYS;
        $level->trial_period = 'Day';
        $level->trial_frequency = 1;

        return $level;
    }

    // ── Before processing / before payment — force £0 ───────────────

    public static function before_processing(): void
    {
        if (!self::is_trial_checkout()) {
            return;
        }

        $_REQUEST['initial_payment'] = 0;
        $_REQUEST['amount'] = 0;
        $_REQUEST['payment_amount'] = 0;
        $_REQUEST['pmpc_calculated_price'] = '0.00';
    }

    public static function before_payment($morder): void
    {
        if (!self::is_trial_checkout()) {
            return;
        }

        $morder->initial_payment = 0;
        $morder->payment_amount = 0;
        $morder->subtotal = 0;
        $morder->total = 0;
        $morder->billing_amount = 0;

        if (!empty($morder->membership_id) && empty($morder->membership_level)) {
            if (function_exists('pmpro_getLevel')) {
                $lvl = pmpro_getLevel($morder->membership_id);
                if ($lvl && is_object($lvl)) {
                    $morder->membership_level = $lvl;
                }
            }
        }

        $configured_level = intval(get_option(PIT_OPTION_LEVEL_ID, 0));
        if (
            !empty($configured_level)
            && !empty($morder->membership_level)
            && is_object($morder->membership_level)
            && intval($morder->membership_level->id) === $configured_level
        ) {
            $morder->membership_level->initial_payment = 0;
            $morder->membership_level->billing_amount = 0;
        }
    }

    // ── After checkout: save user meta + trial markers ───────────────

    public static function after_checkout($user_id, $morder): void
    {
        $use_settings = self::should_use_settings($user_id);

        // Councils are always subscription-specific
        if (!empty($_REQUEST['pmpc_councils'])) {
            $councils = array_map('sanitize_text_field', (array) $_REQUEST['pmpc_councils']);
            update_user_meta($user_id, PIT_META_KEY, $councils);
        }

        // Price is always £0 for the trial
        update_user_meta($user_id, PIT_META_PRICE, '0.00');

        // Template — save to meta always; only merge into _pi_business_info when no Settings exist
        if (!empty($_REQUEST['pmpc_default_template'])) {
            $template = sanitize_text_field($_REQUEST['pmpc_default_template']);
            update_user_meta($user_id, PIT_META_TEMPLATE, $template);

            if (!$use_settings) {
                $business_info = get_user_meta($user_id, '_pi_business_info', true);
                if (!is_array($business_info)) {
                    $business_info = [];
                }
                $business_info['default_template'] = $template;
                $business_info['source'] = 'checkout';
                update_user_meta($user_id, '_pi_business_info', $business_info);
            }
        }

        // Business info from checkout
        $checkout_business_info = [];
        $business_fields = [
            'pmpc_company_name',
            'pmpc_business_email',
            'pmpc_business_phone',
            'pmpc_company_address',
            'pmpc_website',
            'pmpc_vat_number',
        ];

        foreach ($business_fields as $field) {
            if (!empty($_REQUEST[$field])) {
                $checkout_business_info[$field] = sanitize_text_field($_REQUEST[$field]);
            }
        }

        if (!empty($checkout_business_info)) {
            update_user_meta($user_id, PIT_META_BUSINESS, $checkout_business_info);

            if (!$use_settings) {
                $business_info = get_user_meta($user_id, '_pi_business_info', true);
                if (!is_array($business_info)) {
                    $business_info = [];
                }

                $field_map = [
                    'pmpc_company_name'    => 'company_name',
                    'pmpc_business_email'  => 'email',
                    'pmpc_business_phone'  => 'phone',
                    'pmpc_company_address' => 'company_address',
                    'pmpc_website'         => 'website',
                    'pmpc_vat_number'      => 'vat_number',
                ];

                foreach ($field_map as $checkout_key => $settings_key) {
                    if (!empty($checkout_business_info[$checkout_key])) {
                        $business_info[$settings_key] = $checkout_business_info[$checkout_key];
                    }
                }

                $business_info['source'] = 'checkout';
                update_user_meta($user_id, '_pi_business_info', $business_info);
            }
        }

        // ── Trial-specific meta ──────────────────────────────────────
        // Set the trial expiration to 14 days from now
        $expiration = time() + (PIT_TRIAL_DAYS * DAY_IN_SECONDS);
        update_user_meta($user_id, PIT_META_TRIAL_EXPIRATION, $expiration);
        update_user_meta($user_id, PIT_META_IS_TRIAL, 1);
        update_user_meta($user_id, PIT_META_TRIAL_EXPIRED, 0);

        // Order note
        if (!empty($morder)) {
            $councils = isset($_REQUEST['pmpc_councils']) ? $_REQUEST['pmpc_councils'] : [];
            $summary = wp_json_encode(['councils' => $councils], JSON_UNESCAPED_UNICODE);
            $expiry_date = date('j F Y', $expiration);
            $morder->notes = "Free Trial | Councils: $summary | Expires: $expiry_date";
            if (method_exists($morder, 'save')) {
                $morder->save();
            }
        }
    }

    // ── Hidden custom field injection ─────────────────────────────────

    public static function inject_hidden_fields(): void
    {
        if (!self::is_trial_checkout()) {
            return;
        }

        if (!session_id()) {
            session_start();
        }

        $data = isset($_SESSION[PIT_SESSION_KEY]) ? (array) $_SESSION[PIT_SESSION_KEY] : [];

        $councils = isset($data['councils']) && is_array($data['councils']) ? $data['councils'] : [];
        $template = isset($data['template']) ? $data['template'] : 'standard-planning';

        if (empty($councils) && !empty($_REQUEST['pmpc_councils'])) {
            $councils = (array) $_REQUEST['pmpc_councils'];
        }
        if (empty($template) && !empty($_REQUEST['pmpc_default_template'])) {
            $template = sanitize_text_field($_REQUEST['pmpc_default_template']);
        }

        echo '<!-- Trial checkout hidden fields -->';
        foreach ($councils as $council) {
            printf(
                '<input type="hidden" name="pmpc_councils[]" value="%s" />',
                esc_attr(sanitize_text_field($council))
            );
        }
        printf(
            '<input type="hidden" name="pmpc_calculated_price" value="0.00" />'
        );
        printf(
            '<input type="hidden" name="pmpc_default_template" value="%s" />',
            esc_attr($template)
        );
    }

    // ── Billing field pre-population ─────────────────────────────────

    public static function prepopulate_billing($morder)
    {
        if (!self::is_trial_checkout()) {
            return $morder;
        }

        if (!session_id()) {
            session_start();
        }

        $data = isset($_SESSION[PIT_SESSION_KEY]) ? (array) $_SESSION[PIT_SESSION_KEY] : [];
        $business = isset($data['business']) && is_array($data['business']) ? $data['business'] : [];

        if (empty($business)) {
            $business_fields = ['pmpc_company_name', 'pmpc_business_email', 'pmpc_business_phone', 'pmpc_company_address'];
            foreach ($business_fields as $f) {
                if (!empty($_REQUEST[$f])) {
                    $business[$f] = sanitize_text_field(wp_unslash($_REQUEST[$f]));
                }
            }
        }

        if (!empty($business['pmpc_business_phone'])) {
            $morder->billing->phone = $business['pmpc_business_phone'];
        }
        if (!empty($business['pmpc_company_address'])) {
            $morder->billing->address1 = $business['pmpc_company_address'];
        }
        if (!empty($business['pmpc_business_email'])) {
            $morder->Email = $business['pmpc_business_email'];
            $morder->billing->email = $business['pmpc_business_email'];
        }

        if (!is_user_logged_in() && isset($data['email'])) {
            $morder->Email = $data['email'];
            $morder->billing->email = $data['email'];
        }

        return $morder;
    }

    // ── Session restore on PMPro checkout page ───────────────────────

    public static function restore_session(): void
    {
        if (!self::is_trial_checkout()) {
            return;
        }

        if (!session_id()) {
            session_start();
        }

        $user_id = get_current_user_id();
        if ($user_id > 0 && self::should_use_settings($user_id)) {
            if (isset($_SESSION[PIT_SESSION_KEY]['business'])) {
                unset($_SESSION[PIT_SESSION_KEY]['business']);
            }
            if (isset($_SESSION[PIT_SESSION_KEY]['template'])) {
                unset($_SESSION[PIT_SESSION_KEY]['template']);
            }
        }

        $data = isset($_SESSION[PIT_SESSION_KEY]) ? (array) $_SESSION[PIT_SESSION_KEY] : [];

        if (empty($data) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $posted_councils = isset($_POST['pmpc_councils']) ? (array) $_POST['pmpc_councils'] : [];
            $posted_template = isset($_POST['pmpc_default_template']) ? sanitize_text_field($_POST['pmpc_default_template']) : '';

            if (!empty($posted_councils)) {
                $data['councils'] = array_map('sanitize_text_field', $posted_councils);
            }
            if (!empty($posted_template)) {
                $data['template'] = $posted_template;
            }

            $business = [];
            $business_fields = ['pmpc_company_name', 'pmpc_business_email', 'pmpc_business_phone', 'pmpc_company_address'];
            foreach ($business_fields as $f) {
                if (!empty($_POST[$f])) {
                    $business[$f] = sanitize_text_field(wp_unslash($_POST[$f]));
                }
            }
            if (!empty($business)) {
                $data['business'] = $business;
            }

            if (!is_user_logged_in() && !empty($_POST['username'])) {
                $data['username'] = sanitize_user($_POST['username']);
                $data['password'] = $_POST['password'] ?? '';
                $data['email'] = sanitize_email($_POST['bemail'] ?? '');
            }

            if (!empty($data)) {
                $_SESSION[PIT_SESSION_KEY] = $data;
            }
        }

        if (empty($data)) {
            return;
        }

        // Force price to £0 for the trial
        $data['price'] = 0;
        $_REQUEST['pmpc_calculated_price'] = '0.00';

        if (isset($data['councils'])) {
            $_REQUEST['pmpc_councils'] = $data['councils'];
        }
        if (isset($data['template'])) {
            $_REQUEST['pmpc_default_template'] = $data['template'];
        }
        if (!empty($data['business'])) {
            foreach ($data['business'] as $k => $v) {
                $_REQUEST[$k] = $v;
            }
        }

        if (!is_user_logged_in() && isset($data['username'])) {
            $_REQUEST['username']      = $data['username'];
            $_REQUEST['password']      = $data['password'];
            $_REQUEST['password2']     = $data['password'];
            $_REQUEST['bemail']         = $data['email'];
            $_REQUEST['bconfirmemail']  = $data['email'];
        }

        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && (isset($_POST['submit-checkout']) || isset($_POST['pmpro_submit']) || isset($_POST['javascriptok']))
        ) {
            unset($_SESSION[PIT_SESSION_KEY]);
        }
    }

    public static function load_custom_template(): void
    {
        if (!self::is_trial_checkout()) {
            return;
        }

        if (empty($_REQUEST['pi_complete'])) {
            return;
        }

        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && (isset($_POST['submit-checkout']) || isset($_POST['pmpro_submit']) || isset($_POST['javascriptok']))
        ) {
            return;
        }

        $template_path = get_stylesheet_directory() . '/pages/checkout-trial.php';
        if (!file_exists($template_path)) {
            $template_path = PIT_PLUGIN_DIR . 'pages/checkout.php';
        }

        if (!file_exists($template_path)) {
            return;
        }

        require $template_path;
        exit;
    }

    public static function skip_account_fields_for_logged_in($skip): bool
    {
        if (!self::is_trial_checkout()) {
            return $skip;
        }

        if (is_user_logged_in()) {
            return true;
        }

        return $skip;
    }

    // ── Trial expiration and access management ───────────────────────

    /**
     * Check if the current user's trial has expired. If so, cancel their
     * membership level and mark them as expired.
     */
    public static function check_trial_expiration(): void
    {
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();

        $is_trial = get_user_meta($user_id, PIT_META_IS_TRIAL, true);
        if (!$is_trial) {
            return;
        }

        $already_expired = get_user_meta($user_id, PIT_META_TRIAL_EXPIRED, true);
        if ($already_expired) {
            return;
        }

        $expiration = get_user_meta($user_id, PIT_META_TRIAL_EXPIRATION, true);
        if (empty($expiration)) {
            return;
        }

        if (time() < intval($expiration)) {
            return;
        }

        // Trial has expired — cancel the membership level
        $level_id = intval(get_option(PIT_OPTION_LEVEL_ID, 0));
        if ($level_id > 0 && function_exists('pmpro_changeMembershipLevel')) {
            pmpro_changeMembershipLevel(0, $user_id);
        }

        update_user_meta($user_id, PIT_META_TRIAL_EXPIRED, 1);
    }

    /**
     * Restrict access for expired trial users. They can only access the
     * checkout page, the account page, and the cancel-trial action.
     */
    public static function restrict_expired_trial($access, $post, $user)
    {
        // PMPro passes $access as a boolean (true) when access is allowed.
        // Normalize to the array shape PMPro expects so we never return a
        // raw bool from a function that must return an array.
        if (!is_array($access)) {
            $access = [
                'allowed' => (bool) $access,
                'message' => '',
                'redirect' => '',
            ];
        }

        if (!is_user_logged_in()) {
            return $access;
        }

        $user_id = get_current_user_id();
        $is_trial = get_user_meta($user_id, PIT_META_IS_TRIAL, true);
        $expired = get_user_meta($user_id, PIT_META_TRIAL_EXPIRED, true);

        if (!$is_trial || !$expired) {
            return $access;
        }

        // Allow access to checkout, account, and cancel pages
        $request_uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        $allowed_patterns = ['/membership-checkout', '/checkout', '/account', '/cancel-trial', '/wp-admin'];

        foreach ($allowed_patterns as $pattern) {
            if (strpos($request_uri, $pattern) !== false) {
                return $access;
            }
        }

        // Block access — redirect to the expired trial notice
        $access['allowed'] = false;
        $access['message'] = 'Your free trial has ended. Please subscribe to continue accessing Planning Index.';
        $access['redirect'] = home_url('/membership-account/?pit_trial_expired=1');

        return $access;
    }
}
