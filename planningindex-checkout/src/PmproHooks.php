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

        // Load our custom checkout template on the pi_complete page so the
        // user sees our styled multi-step form instead of PMPro's default.
        add_action('pmpro_checkout_preheader', [self::class, 'load_custom_template'], 10);

        // Inject hidden custom fields (councils, price, template) into PMPro's checkout form
        add_action('pmpro_checkout_after_billing_fields', [self::class, 'inject_hidden_fields'], 10);

        // Pre-populate billing fields from session data
        add_filter('pmpro_checkout_order', [self::class, 'prepopulate_billing'], 10, 1);

        // Hide account creation fields for logged-in users
        add_filter('pmpro_checkout_skip_account_fields', [self::class, 'skip_account_fields_for_logged_in'], 10, 1);

        // Handle Stripe Checkout Session success redirect
        add_action('template_redirect', [self::class, 'handle_stripe_success'], 5);
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

        // If the session is empty, the data may have arrived via the hidden
        // form POST from the React wizard. Merge POST data into the session
        // so all downstream hooks (inject_hidden_fields, Stripe filters) see
        // a consistent data source.
        if (empty($data) && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $posted_councils = isset($_POST['pmpc_councils']) ? (array) $_POST['pmpc_councils'] : [];
            $posted_price = isset($_POST['pmpc_calculated_price']) ? sanitize_text_field(wp_unslash($_POST['pmpc_calculated_price'])) : '';
            $posted_template = isset($_POST['pmpc_default_template']) ? sanitize_text_field($_POST['pmpc_default_template']) : '';

            if (!empty($posted_councils)) {
                $data['councils'] = array_map('sanitize_text_field', $posted_councils);
            }
            if (!empty($posted_price)) {
                $data['price'] = $posted_price;
            }
            if (!empty($posted_template)) {
                $data['template'] = $posted_template;
            }

            // Business info from POST
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

            // Account credentials for logged-out users
            if (!is_user_logged_in() && !empty($_POST['username'])) {
                $data['username'] = sanitize_user($_POST['username']);
                $data['password'] = $_POST['password'] ?? '';
                $data['email'] = sanitize_email($_POST['bemail'] ?? '');
            }

            if (!empty($data)) {
                $_SESSION[PIC_SESSION_KEY] = $data;
            }
        }

        if (empty($data)) {
            return;
        }

        // Map session keys to the PMPro request keys that all hooks
        // (registration_checks, checkout_level_price, inject_hidden_fields,
        // Stripe filters) read from. Do this on every request so the price
        // override and hidden fields are present when the browser lands on
        // the PMPro checkout page via GET redirect from the React wizard.
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

        // For logged-out users, pre-populate account credentials so PMPro
        // can create the account during checkout processing.
        if (!is_user_logged_in() && isset($data['username'])) {
            $_REQUEST['username']      = $data['username'];
            $_REQUEST['password']      = $data['password'];
            $_REQUEST['password2']     = $data['password'];
            $_REQUEST['bemail']         = $data['email'];
            $_REQUEST['bconfirmemail']  = $data['email'];
        }

        // Clear the session only on the final PMPro form POST so the data
        // persists across the GET redirect → page render → form submit cycle.
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && (isset($_POST['submit-checkout']) || isset($_POST['pmpro_submit']) || isset($_POST['javascriptok']))
        ) {
            unset($_SESSION[PIC_SESSION_KEY]);
        }
    }

    /**
     * Load our custom checkout template when the React wizard redirects
     * to the PMPro checkout page with pi_complete=1. This replaces PMPro's
     * default checkout template with our styled multi-step form so the
     * user sees a consistent, branded experience instead of PMPro's
     * plain account-creation form.
     */
    public static function load_custom_template(): void
    {
        if (!self::is_per_council_checkout()) {
            return;
        }

        if (empty($_REQUEST['pi_complete'])) {
            return;
        }

        // Don't intercept the actual form submission — let PMPro process it
        if (
            $_SERVER['REQUEST_METHOD'] === 'POST'
            && (isset($_POST['submit-checkout']) || isset($_POST['pmpro_submit']) || isset($_POST['javascriptok']))
        ) {
            return;
        }

        $template_path = get_stylesheet_directory() . '/pages/checkout.php';
        if (!file_exists($template_path)) {
            // Fallback to the plugin's own pages directory
            $template_path = PIC_PLUGIN_DIR . 'pages/checkout.php';
        }

        if (!file_exists($template_path)) {
            return;
        }

        // Render the template and exit — replaces PMPro's default checkout page
        require $template_path;
        exit;
    }

    /**
     * For logged-in users, tell PMPro to skip the account creation fields
     * entirely. The user already has an account; they should never see
     * username/password/email fields during checkout.
     */
    public static function skip_account_fields_for_logged_in($skip): bool
    {
        if (!self::is_per_council_checkout()) {
            return $skip;
        }

        if (is_user_logged_in()) {
            return true;
        }

        return $skip;
    }

    /**
     * Handle the Stripe Checkout Session success redirect.
     *
     * When Stripe redirects back to /membership-account/?pic_stripe_success=1
     * &session_id=..., we retrieve the session from Stripe, verify payment,
     * create the WP user (if not logged in), save user meta, and grant the
     * PMPro membership level.
     */
    public static function handle_stripe_success()
    {
        if (!isset($_GET['pic_stripe_success']) || !isset($_GET['session_id'])) {
            return;
        }

        $session_id = sanitize_text_field($_GET['session_id']);
        if (empty($session_id)) {
            return;
        }

        $secret_key = '';
        if (function_exists('pmpro_getOption')) {
            $secret_key = pmpro_getOption('stripe_secretkey');
        }
        if (empty($secret_key)) {
            $secret_key = get_option('pmpro_stripe_secretkey', '');
        }
        if (empty($secret_key)) {
            return;
        }

        $response = wp_remote_get('https://api.stripe.com/v1/checkout/sessions/' . $session_id, [
            'headers' => ['Authorization' => 'Bearer ' . $secret_key],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            return;
        }

        $session = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($session) || $session['payment_status'] !== 'paid') {
            return;
        }

        $ref_key = $session['client_reference_id'] ?? '';
        $meta = $ref_key ? get_transient($ref_key) : false;
        if (!$meta || !is_array($meta)) {
            return;
        }

        $level_id   = intval($meta['level_id']);
        $councils   = $meta['councils'];
        $template   = $meta['template'];
        $business   = $meta['business'];
        $account    = $meta['account'];
        $price      = $meta['price'];

        // Create or retrieve WP user
        $user_id = 0;
        if (!empty($meta['user_id']) && $meta['user_id'] > 0) {
            $user_id = intval($meta['user_id']);
        } else {
            $email = $account['email'] ?? ($session['customer_details']['email'] ?? '');
            $username = $account['username'] ?? '';

            if (empty($email)) {
                return;
            }

            $existing = get_user_by('email', $email);
            if ($existing) {
                $user_id = $existing->ID;
            } else {
                if (empty($username)) {
                    $username = sanitize_user(current(explode('@', $email)));
                }
                if (username_exists($username)) {
                    $username .= wp_rand(100, 999);
                }

                $user_id = wp_create_user($username, $account['password'] ?? wp_generate_password(), $email);
                if (is_wp_error($user_id)) {
                    return;
                }
            }
        }

        if ($user_id > 0) {
            // Save user meta
            update_user_meta($user_id, '_pi_selected_councils', $councils);
            update_user_meta($user_id, '_pi_selected_template', $template);
            update_user_meta($user_id, '_pi_monthly_cost', $price);

            if (!empty($business)) {
                $business_info = [
                    'company_name'    => $business['pmpc_company_name'] ?? '',
                    'business_email'  => $business['pmpc_business_email'] ?? '',
                    'business_phone'  => $business['pmpc_business_phone'] ?? '',
                    'company_address' => $business['pmpc_company_address'] ?? '',
                ];
                update_user_meta($user_id, '_pi_business_info', $business_info);
            }

            // Grant PMPro membership level
            if (function_exists('pmpro_changeMembershipLevel')) {
                pmpro_changeMembershipLevel($level_id, $user_id);

                // Create a PMPro order record
                if (class_exists('MemberOrder')) {
                    $order = new MemberOrder();
                    $order->user_id = $user_id;
                    $order->membership_id = $level_id;
                    $order->InitialPayment = $price;
                    $order->PaymentAmount = $price;
                    $order->BillingPeriod = 'Month';
                    $order->BillingFrequency = 1;
                    $order->gateway = 'stripe';
                    $order->status = 'success';
                    $order->saveOrder();

                    if (!empty($session['customer'])) {
                        update_user_meta($user_id, 'pmpro_stripe_customer_id', $session['customer']);
                    }
                    if (!empty($session['subscription'])) {
                        $order->subscription_transaction_id = $session['subscription'];
                        $order->updateOrder();
                    }
                }

                do_action('pmpro_after_checkout', $user_id);
            }

            // Log in the user if they weren't already
            if (!is_user_logged_in()) {
                wp_set_current_user($user_id);
                wp_set_auth_cookie($user_id, true);
            }
        }

        // Clean up transient
        delete_transient($ref_key);

        // Clear checkout session
        if (session_id() && isset($_SESSION[PIC_SESSION_KEY])) {
            unset($_SESSION[PIC_SESSION_KEY]);
        }
    }
}
