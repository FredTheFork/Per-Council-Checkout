<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * PMPro integration hooks for the per-council checkout.
 *
 * These hooks fire during PMPro's checkout processing. The React app
 * saves all wizard data to a PHP session via the REST API, then the
 * CheckoutController populates $_REQUEST and redirects the browser
 * to the real PMPro checkout page where these hooks take over.
 *
 * Ported from the legacy pmpro-per-council plugin and adapted to use
 * the PIC_ constant namespace.
 */
class PIC_PmproHooks
{
    public static function init(): void
    {
        // Settings-precedence helper
        add_filter('pic_should_use_settings', [self::class, 'should_use_settings'], 10, 1);

        // Validation: ensure councils + price are present and correct
        add_filter('pmpro_registration_checks', [self::class, 'registration_checks'], 10, 1);

        // Override the checkout level price with the per-council dynamic price
        add_filter('pmpro_checkout_level', [self::class, 'checkout_level_price'], 20, 1);

        // Safety net: set request price fields before processing and before payment
        add_action('pmpro_checkout_before_processing', [self::class, 'before_processing']);
        add_action('pmpro_checkout_before_payment', [self::class, 'before_payment'], 10, 1);

        // Save user meta after successful checkout
        add_action('pmpro_after_checkout', [self::class, 'after_checkout'], 10, 2);

        // Stripe-specific price overrides
        add_filter('pmpro_stripe_create_subscription_array', [self::class, 'stripe_subscription_array'], 20, 2);
        add_filter('pmpro_stripe_payment_intent_amount', [self::class, 'stripe_payment_intent_amount'], 20, 2);
        add_filter('pmpro_stripe_create_payment_intent_array', [self::class, 'stripe_payment_intent_array'], 20, 2);

        // Restore session data into $_REQUEST on the PMPro checkout page load
        add_action('pmpro_checkout_preheader', [self::class, 'restore_session'], 5);

        // Inject hidden custom fields (councils, price, template) into PMPro's checkout form
        add_action('pmpro_checkout_after_billing_fields', [self::class, 'inject_hidden_fields'], 10);

        // Pre-populate billing fields from session data
        add_filter('pmpro_checkout_order', [self::class, 'prepopulate_billing'], 10, 1);
    }

    // ── Settings precedence ──────────────────────────────────────────

    /**
     * Returns true if the user has saved Settings (business info) that
     * should take precedence over checkout-entered data.
     */
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
     * Resolve the dynamic per-council price from the request or user meta.
     */
    public static function get_price_from_request(): float
    {
        if (is_user_logged_in()) {
            $stored = get_user_meta(get_current_user_id(), PIC_META_PRICE, true);
            if ($stored && floatval($stored) > 0) {
                return floatval($stored);
            }
        }

        if (!empty($_REQUEST['pmpc_calculated_price'])) {
            return floatval(sanitize_text_field(wp_unslash($_REQUEST['pmpc_calculated_price'])));
        }

        // Defensive fallback: check the session directly so the Stripe
        // filters never see zero when the request param is missing.
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (!empty($_SESSION[PIC_SESSION_KEY]['price'])) {
            return floatval($_SESSION[PIC_SESSION_KEY]['price']);
        }

        return 0.0;
    }

    /**
     * Returns true when the current request is for the configured
     * per-council membership level.
     */
    public static function is_per_council_checkout(): bool
    {
        $configured_level = intval(get_option(PIC_OPTION_LEVEL_ID, 0));
        if ($configured_level === 0) {
            return false;
        }

        $current_level = 0;

        if (isset($_REQUEST['level'])) {
            $current_level = intval($_REQUEST['level']);
        } elseif (isset($_REQUEST['pmpro_level'])) {
            $current_level = intval($_REQUEST['pmpro_level']);
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
        if (!self::is_per_council_checkout()) {
            return $ok;
        }

        if (empty($_REQUEST['pmpc_councils'])) {
            pmpro_setMessage(
                sprintf('Please select at least %d councils.', PIC_MIN_SELECTION),
                'pmpro_error'
            );
            return false;
        }

        $selected = array_map('sanitize_text_field', (array) $_REQUEST['pmpc_councils']);
        $count = count($selected);

        if ($count < PIC_MIN_SELECTION) {
            pmpro_setMessage(
                sprintf('Please select at least %d councils.', PIC_MIN_SELECTION),
                'pmpro_error'
            );
            return false;
        }

        $expected_price = $count * PIC_UNIT_PRICE;
        $posted_price = isset($_REQUEST['pmpc_calculated_price'])
            ? floatval($_REQUEST['pmpc_calculated_price'])
            : 0;

        if (abs($posted_price - $expected_price) > 0.01) {
            pmpro_setMessage(
                'Price validation failed. Please reselect your councils and try again.',
                'pmpro_error'
            );
            return false;
        }

        $_REQUEST['pmpc_councils'] = $selected;
        $_REQUEST['pmpc_calculated_price'] = number_format($expected_price, 2, '.', '');

        return $ok;
    }

    // ── Level price override ─────────────────────────────────────────

    public static function checkout_level_price($level)
    {
        $configured_level = intval(get_option(PIC_OPTION_LEVEL_ID, 0));

        if (empty($configured_level) || intval($level->id) !== $configured_level) {
            return $level;
        }

        $dynamic_price = 0.0;

        if (!empty($_REQUEST['pmpc_calculated_price'])) {
            $dynamic_price = floatval(sanitize_text_field(wp_unslash($_REQUEST['pmpc_calculated_price'])));
        }

        if ($dynamic_price <= 0) {
            return $level;
        }

        $level->initial_payment = $dynamic_price;
        $level->billing_amount = $dynamic_price;

        return $level;
    }

    // ── Before processing / before payment ───────────────────────────

    public static function before_processing(): void
    {
        if (!self::is_per_council_checkout()) {
            return;
        }

        $price = self::get_price_from_request();
        if ($price <= 0) {
            return;
        }

        $_REQUEST['initial_payment'] = $price;
        $_REQUEST['amount'] = $price;
        $_REQUEST['payment_amount'] = $price;
    }

    public static function before_payment($morder): void
    {
        if (!self::is_per_council_checkout()) {
            return;
        }

        $price = self::get_price_from_request();
        if ($price <= 0) {
            return;
        }

        $morder->initial_payment = $price;
        $morder->payment_amount = $price;
        $morder->subtotal = $price;
        $morder->total = $price;
        $morder->billing_amount = $price;

        if (!empty($morder->membership_id) && empty($morder->membership_level)) {
            if (function_exists('pmpro_getLevel')) {
                $lvl = pmpro_getLevel($morder->membership_id);
                if ($lvl && is_object($lvl)) {
                    $morder->membership_level = $lvl;
                }
            }
        }

        $configured_level = intval(get_option(PIC_OPTION_LEVEL_ID, 0));
        if (
            !empty($configured_level)
            && !empty($morder->membership_level)
            && is_object($morder->membership_level)
            && intval($morder->membership_level->id) === $configured_level
        ) {
            $morder->membership_level->initial_payment = $price;
            $morder->membership_level->billing_amount = $price;
        }
    }

    // ── After checkout: save user meta ──────────────────────────────

    public static function after_checkout($user_id, $morder): void
    {
        $use_settings = self::should_use_settings($user_id);

        // Councils are always subscription-specific
        if (!empty($_REQUEST['pmpc_councils'])) {
            $councils = array_map('sanitize_text_field', (array) $_REQUEST['pmpc_councils']);
            update_user_meta($user_id, PIC_META_KEY, $councils);
        }

        // Price is always subscription-specific
        if (!empty($_REQUEST['pmpc_calculated_price'])) {
            $price = floatval(sanitize_text_field(wp_unslash($_REQUEST['pmpc_calculated_price'])));
            update_user_meta($user_id, PIC_META_PRICE, number_format($price, 2, '.', ''));
        }

        // Template — save to PMPC meta always; only merge into _pi_business_info when no Settings exist
        if (!empty($_REQUEST['pmpc_default_template'])) {
            $template = sanitize_text_field($_REQUEST['pmpc_default_template']);
            update_user_meta($user_id, PIC_META_TEMPLATE, $template);

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
            update_user_meta($user_id, PIC_META_BUSINESS, $checkout_business_info);

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

        // Order note
        if (!empty($morder)) {
            $councils = isset($_REQUEST['pmpc_councils']) ? $_REQUEST['pmpc_councils'] : [];
            $summary = wp_json_encode(['councils' => $councils], JSON_UNESCAPED_UNICODE);
            $morder->notes = "PerCouncilSelected: $summary";
            if (method_exists($morder, 'save')) {
                $morder->save();
            }
        }
    }

    // ── Stripe-specific price overrides ─────────────────────────────

    public static function stripe_subscription_array($params, $order)
    {
        if (!self::is_per_council_checkout()) {
            return $params;
        }
        $price = self::get_price_from_request();
        if ($price > 0 && isset($params['items'][0]['price_data']['unit_amount'])) {
            $params['items'][0]['price_data']['unit_amount'] = intval($price * 100);
        }
        return $params;
    }

    public static function stripe_payment_intent_amount($amount, $order)
    {
        if (!self::is_per_council_checkout()) {
            return $amount;
        }
        $price = self::get_price_from_request();
        if ($price > 0) {
            return intval($price * 100);
        }
        return $amount;
    }

    public static function stripe_payment_intent_array($intent_array, $order)
    {
        if (!self::is_per_council_checkout()) {
            return $intent_array;
        }
        $price = self::get_price_from_request();
        if ($price > 0) {
            $intent_array['amount'] = intval($price * 100);
        }
        return $intent_array;
    }

    // ── Hidden custom field injection ─────────────────────────────────

    /**
     * Inject hidden inputs for councils, price, and template into PMPro's
     * checkout form so they survive the POST and are available to
     * after_checkout and the Stripe filters.
     */
    public static function inject_hidden_fields(): void
    {
        if (!self::is_per_council_checkout()) {
            return;
        }

        if (!session_id()) {
            session_start();
        }

        $data = isset($_SESSION[PIC_SESSION_KEY]) ? (array) $_SESSION[PIC_SESSION_KEY] : [];

        $councils = isset($data['councils']) && is_array($data['councils']) ? $data['councils'] : [];
        $price = isset($data['price']) ? $data['price'] : '';
        $template = isset($data['template']) ? $data['template'] : 'standard-planning';

        // Also check $_REQUEST for values (from the form POST)
        if (empty($councils) && !empty($_REQUEST['pmpc_councils'])) {
            $councils = (array) $_REQUEST['pmpc_councils'];
        }
        if (empty($price) && !empty($_REQUEST['pmpc_calculated_price'])) {
            $price = sanitize_text_field(wp_unslash($_REQUEST['pmpc_calculated_price']));
        }
        if (empty($template) && !empty($_REQUEST['pmpc_default_template'])) {
            $template = sanitize_text_field($_REQUEST['pmpc_default_template']);
        }

        echo '<!-- Per-council hidden fields -->';
        foreach ($councils as $council) {
            printf(
                '<input type="hidden" name="pmpc_councils[]" value="%s" />',
                esc_attr(sanitize_text_field($council))
            );
        }
        printf(
            '<input type="hidden" name="pmpc_calculated_price" value="%s" />',
            esc_attr($price)
        );
        printf(
            '<input type="hidden" name="pmpc_default_template" value="%s" />',
            esc_attr($template)
        );
    }

    // ── Billing field pre-population ─────────────────────────────────

    /**
     * Pre-populate the PMPro order's billing fields from the session so
     * the user does not have to re-enter business address, phone, etc.
     */
    public static function prepopulate_billing($morder)
    {
        if (!self::is_per_council_checkout()) {
            return $morder;
        }

        if (!session_id()) {
            session_start();
        }

        $data = isset($_SESSION[PIC_SESSION_KEY]) ? (array) $_SESSION[PIC_SESSION_KEY] : [];
        $business = isset($data['business']) && is_array($data['business']) ? $data['business'] : [];

        // Also check $_REQUEST (from the form POST)
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

        // Pre-populate account email from session for new users
        if (!is_user_logged_in() && isset($data['email'])) {
            $morder->Email = $data['email'];
            $morder->billing->email = $data['email'];
        }

        return $morder;
    }

    // ── Session restore on PMPro checkout page ───────────────────────

    /**
     * When the browser lands on the real PMPro checkout page (redirected
     * from the React app), restore the saved session data into $_REQUEST
     * so all the hooks above see the councils, price, template, etc.
     *
     * If this is the actual POST that submits checkout (submit-checkout),
     * merge session data then clear it so PMPro processes a real checkout.
     */
    public static function restore_session(): void
    {
        if (!self::is_per_council_checkout()) {
            return;
        }

        if (!session_id()) {
            session_start();
        }

        // Settings-precedence: strip business/template from session if user has Settings
        $user_id = get_current_user_id();
        if ($user_id > 0 && self::should_use_settings($user_id)) {
            if (isset($_SESSION[PIC_SESSION_KEY]['business'])) {
                unset($_SESSION[PIC_SESSION_KEY]['business']);
            }
            if (isset($_SESSION[PIC_SESSION_KEY]['template'])) {
                unset($_SESSION[PIC_SESSION_KEY]['template']);
            }
        }

        $data = isset($_SESSION[PIC_SESSION_KEY]) ? (array) $_SESSION[PIC_SESSION_KEY] : [];
        if (empty($data)) {
            return;
        }

        // Merge session data into $_REQUEST for all hooks to see
        $_REQUEST = array_merge($_REQUEST, $data);

        // If this is the final POST (submit-checkout), populate PMPro-specific fields and clear session
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && (isset($_POST['submit-checkout']) || isset($_POST['pmpro_submit']) || isset($_POST['javascriptok']))
        ) {
            if (isset($data['councils'])) {
                $_REQUEST['pmpc_councils'] = $data['councils'];
            }
            if (isset($data['price'])) {
                $_REQUEST['pmpc_calculated_price'] = $data['price'];
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

            unset($_SESSION[PIC_SESSION_KEY]);
        }
    }
}
