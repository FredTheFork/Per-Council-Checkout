<?php
/**
 * Template: Checkout - Multi-Step Enterprise Wizard
 * Planning Index Enterprise Implementation (Level 60)
 * Version: 4.0.0 - Unified Design System
 *
 * 4-Step Enterprise Checkout:
 * 1. Enterprise Overview & Team Seats
 * 2. Template Preferences
 * 3. Account Creation
 * 4. Business Info & Payment
 */

if (!defined('ABSPATH')) exit;

if (function_exists('do_blocks')) {
    echo do_blocks('<!-- wp:template-part {"slug":"header","theme":"' . get_stylesheet() . '"} /-->');
}

global $gateway, $pmpro_review, $skip_account_fields, $pmpro_paypal_token, $wpdb, $current_user;
global $pmpro_msg, $pmpro_msgt, $pmpro_requirebilling, $pmpro_level, $pmpro_show_discount_code;
global $pmpro_error_fields, $pmpro_default_country;
global $discount_code, $username, $password, $password2, $bfirstname, $blastname;
global $baddress1, $baddress2, $bcity, $bstate, $bzipcode, $bcountry, $bphone;
global $bemail, $bconfirmemail, $CardType, $AccountNumber, $ExpirationMonth, $ExpirationYear;

if (!session_id()) {
    @session_start();
}

$data = isset($_SESSION[PMPE_SESSION_KEY]) ? (array) $_SESSION[PMPE_SESSION_KEY] : [];

if (empty($pmpro_level) || !is_object($pmpro_level) || empty($pmpro_level->id)) {
    $level_id = isset($_REQUEST['pmpro_level']) ? intval($_REQUEST['pmpro_level'])
              : (isset($_REQUEST['level']) ? intval($_REQUEST['level']) : PMPE_LEVEL_ID);

    if ($level_id > 0 && function_exists('pmpro_getLevel')) {
        $pmpro_level = pmpro_getLevel($level_id);
    }

    if (empty($pmpro_level) || !is_object($pmpro_level)) {
        $pmpro_level = new stdClass();
        $pmpro_level->id = PMPE_LEVEL_ID;
        $pmpro_level->name = __('Enterprise', 'paid-memberships-pro');
    }
}

$default_gateway = get_option('pmpro_gateway', '');
$pmpro_checkout_gateway_class = 'pmpro_section pmpro_checkout_gateway-enterprise';

$current_step = isset($_GET['step']) ? max(1, min(4, intval($_GET['step']))) : 1;

if ($current_step === 3 && is_user_logged_in()) {
    $current_step = 4;
}

$steps = [
    1 => ['title' => 'Enterprise', 'icon' => 'building', 'desc' => 'Overview & team seats'],
    2 => ['title' => 'Template', 'icon' => 'file-text', 'desc' => 'Pick your letter style'],
    3 => ['title' => 'Account', 'icon' => 'user', 'desc' => 'Create your account'],
    4 => ['title' => 'Payment', 'icon' => 'credit-card', 'desc' => 'Complete checkout'],
];

$checkout_url = pmpro_url('checkout');
$enterprise_price = PMPE_PRICE;
$total_display_steps = is_user_logged_in() ? 3 : 4;

// Enqueue unified assets
if (function_exists('pi_checkout_core_asset_url')) {
    wp_enqueue_style('pi-checkout-tokens', pi_checkout_core_asset_url('pi-checkout-tokens.min.css'), [], '1.0.0');
    wp_enqueue_style('pi-checkout-base', pi_checkout_core_asset_url('pi-checkout-base.min.css'), ['pi-checkout-tokens'], '1.0.0');
    wp_enqueue_style('pi-checkout-enterprise', pi_checkout_core_asset_url('pi-checkout-enterprise.min.css'), ['pi-checkout-base'], '1.0.0');
    wp_enqueue_script('pi-checkout', pi_checkout_core_asset_url('pi-checkout.min.js'), ['jquery'], '1.0.0', true);
} else {
    wp_enqueue_style('pi-checkout-tokens', get_stylesheet_directory_uri() . '/assets/pi-checkout-tokens.css', [], '2.0.0');
    wp_enqueue_style('pi-checkout-base', get_stylesheet_directory_uri() . '/assets/pi-checkout-base.css', ['pi-checkout-tokens'], '2.0.0');
    wp_enqueue_style('pi-checkout-enterprise', get_stylesheet_directory_uri() . '/assets/pi-checkout-enterprise.css', ['pi-checkout-base'], '2.0.0');
    wp_enqueue_script('pi-checkout', get_stylesheet_directory_uri() . '/assets/pi-checkout.js', ['jquery'], '2.0.0', true);
}

// Inline CSS + JS directly — external asset URLs return HTML (404) causing MIME type rejection.
// Using file_get_contents with filesystem paths guarantees content is embedded in the page.
$_pi_asset_path = function_exists('pi_checkout_core_asset_path')
    ? pi_checkout_core_asset_path('')
    : get_stylesheet_directory() . '/assets/';
?>
<style><?= file_get_contents($_pi_asset_path . 'pi-checkout-tokens.min.css') ?></style>
<style><?= file_get_contents($_pi_asset_path . 'pi-checkout-base.min.css') ?></style>
<style><?= file_get_contents($_pi_asset_path . 'pi-checkout-enterprise.min.css') ?></style>
<script><?= file_get_contents($_pi_asset_path . 'pi-checkout.min.js') ?></script>
<a href="#pi-main-content" class="pi-skip-link">Skip to main content</a>

<div id="pmpro_checkout_wrapper" class="pmpro-checkout-wrapper pi-wizard-checkout pmpe-wizard-checkout pi-checkout-body pi-checkout-enterprise">

    <!-- Trust Badge Bar -->
    <div class="pi-trust-bar" role="region" aria-label="Secure checkout">
        <div class="pi-trust-item">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Secure Checkout
        </div>
        <div class="pi-trust-divider"></div>
        <div class="pi-payment-icons" aria-label="Accepted payment methods">
            <span class="pi-payment-badge visa">VISA</span>
            <span class="pi-payment-badge mastercard">MC</span>
            <span class="pi-payment-badge amex">AMEX</span>
        </div>
    </div>

    <!-- Progress Header -->
    <div class="pmpe-progress-header pi-progress-container" role="progressbar" aria-valuenow="<?= esc_attr($current_step) ?>" aria-valuemin="1" aria-valuemax="<?= esc_attr($total_display_steps) ?>" aria-label="Checkout progress">
        <div class="pmpe-progress-container pi-progress-track">
            <div class="pi-progress-fill" style="width: <?= (($current_step - 1) / ($total_display_steps - 1)) * 100 ?>%"></div>
            <?php
            $display_steps = [];
            foreach ($steps as $num => $step) {
                if ($num === 3 && is_user_logged_in()) continue;
                $display_steps[] = $step + ['num' => $num];
            }

            $step_index = 0;
            foreach ($display_steps as $i => $s):
                $step_index++;
                $is_active   = $s['num'] === $current_step;
                $is_completed = $s['num'] < $current_step;
            ?>
                <div class="pmpe-progress-step pi-progress-step <?= $is_active ? 'active' : ($is_completed ? 'completed' : '') ?>"
                    data-step="<?= $s['num'] ?>"
                    <?= $is_active ? 'aria-current="step"' : '' ?>>
                    <div class="pmpe-progress-circle pi-progress-circle">
                        <span><?= $step_index ?></span>
                    </div>
                    <div class="pmpe-progress-info pi-progress-label">
                        <span class="pmpe-progress-title"><?= esc_html($s['title']) ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <main id="pi-main-content" class="pi-checkout-content">
    <section id="pmpro_level-<?= intval($pmpro_level->id) ?>" class="<?= esc_attr($pmpro_checkout_gateway_class) ?>">

        <form id="pmpro_form" class="pmpro_form" action="<?= esc_url($checkout_url) ?>" method="post">

            <input type="hidden" id="pmpro_level" name="pmpro_level" value="<?= esc_attr($pmpro_level->id) ?>" />
            <input type="hidden" id="level" name="level" value="<?= esc_attr($pmpro_level->id) ?>" />
            <input type="hidden" id="checkjavascript" name="checkjavascript" value="1" />
            <input type="hidden" id="pi_current_step" name="pmpe_current_step" value="<?= $current_step ?>" />

            <?php if ($pmpro_msg): ?>
                <div role="alert" id="pmpro_message" class="pmpro_message <?= esc_attr($pmpro_msgt) ?>">
                    <?= wp_kses_post(apply_filters('pmpro_checkout_message', $pmpro_msg, $pmpro_msgt)) ?>
                </div>
            <?php else: ?>
                <div id="pmpro_message" class="pmpro_message" style="display:none;"></div>
            <?php endif; ?>

            <!-- ═══════════════════════════════════════════════════════════════
                 STEP 1: ENTERPRISE OVERVIEW & TEAM SEATS
                 ═══════════════════════════════════════════════════════════════ -->
            <div class="pmpe-step-content pi-step-content" data-step="1" <?= $current_step !== 1 ? 'style="display:none;"' : '' ?>>
                <div class="pmpe-step-card pi-step-card">
                    <div class="pmpe-step-card-header pi-step-card-header">
                        <div class="pmpe-step-badge pi-checkout-badge">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M5 21V7l8-4v18M19 21V11l-6-4"/></svg>
                            Enterprise Plan
                        </div>
                        <h2>Enterprise Access — All Councils</h2>
                        <p>Get unrestricted access to every UK council's planning applications, plus team seats for your whole organisation.</p>
                    </div>

                    <div class="pmpe-step-card-body pi-step-card-body">
                        <!-- Enterprise Features Grid -->
                        <div class="pi-enterprise-features">
                            <div class="pi-enterprise-feature">
                                <div class="pi-enterprise-feature-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                </div>
                                <div>
                                    <strong>All UK Councils</strong>
                                    <span>Access every council — no limits</span>
                                </div>
                            </div>
                            <div class="pi-enterprise-feature">
                                <div class="pi-enterprise-feature-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                </div>
                                <div>
                                    <strong>Team Seats</strong>
                                    <span>Add team members to share access</span>
                                </div>
                            </div>
                            <div class="pi-enterprise-feature">
                                <div class="pi-enterprise-feature-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v20M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>
                                </div>
                                <div>
                                    <strong>Priority Support</strong>
                                    <span>Dedicated account manager</span>
                                </div>
                            </div>
                            <div class="pi-enterprise-feature">
                                <div class="pi-enterprise-feature-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                </div>
                                <div>
                                    <strong>Custom Branding</strong>
                                    <span>White-label your proposal letters</span>
                                </div>
                            </div>
                        </div>

                        <!-- Team Seats Section (FIXED: sibling, not nested) -->
                        <div class="pi-team-seats-section">
                            <h3>
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                Select Team Seats
                            </h3>
                            <div class="pi-team-seats-options">
                                <label class="pi-team-seats-option <?= (!isset($data['team_seats']) || $data['team_seats'] == 1) ? 'selected' : '' ?>">
                                    <input type="radio" name="pmpe_team_seats" value="1" <?= (!isset($data['team_seats']) || $data['team_seats'] == 1) ? 'checked' : '' ?>>
                                    <span class="pi-team-seats-number">1</span>
                                    <span class="pi-team-seats-label">Just me</span>
                                </label>
                                <label class="pi-team-seats-option <?= ($data['team_seats'] ?? 0) == 3 ? 'selected' : '' ?>">
                                    <input type="radio" name="pmpe_team_seats" value="3" <?= ($data['team_seats'] ?? 0) == 3 ? 'checked' : '' ?>>
                                    <span class="pi-team-seats-number">3</span>
                                    <span class="pi-team-seats-label">Small team</span>
                                </label>
                                <label class="pi-team-seats-option <?= ($data['team_seats'] ?? 0) == 5 ? 'selected' : '' ?>">
                                    <input type="radio" name="pmpe_team_seats" value="5" <?= ($data['team_seats'] ?? 0) == 5 ? 'checked' : '' ?>>
                                    <span class="pi-team-seats-number">5</span>
                                    <span class="pi-team-seats-label">Medium team</span>
                                </label>
                                <label class="pi-team-seats-option <?= ($data['team_seats'] ?? 0) == 10 ? 'selected' : '' ?>">
                                    <input type="radio" name="pmpe_team_seats" value="10" <?= ($data['team_seats'] ?? 0) == 10 ? 'checked' : '' ?>>
                                    <span class="pi-team-seats-number">10</span>
                                    <span class="pi-team-seats-label">Large team</span>
                                </label>
                            </div>
                        </div>

                        <!-- Price Display -->
                        <div class="pmpe-price-display pi-price-display" aria-live="polite">
                            <div class="pmpe-price-info pi-price-info">
                                <span class="pmpe-price-label pi-price-label">Enterprise Price</span>
                                <span class="pmpe-price-amount pi-price-amount" id="pi_price_amount">&pound;<?= number_format($enterprise_price, 2) ?></span>
                                <span class="pmpe-price-period pi-price-period">per month</span>
                            </div>
                            <div class="pmpe-cancel-note">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                Cancel anytime
                            </div>
                        </div>

                        <input type="hidden" id="pmpe_calculated_price" name="pmpe_calculated_price" class="pmpro_alter_price" value="<?= number_format($enterprise_price, 2, '.', '') ?>">

                        <div id="pi_step1_error" class="pmpe-error-message pi-step-error" aria-live="polite"></div>
                    </div>

                    <div class="pmpe-nav-buttons pi-nav-buttons">
                        <button type="button" id="pi_btn_back" class="pmpe-btn-back pi-btn pi-btn-back" style="visibility:hidden;" aria-label="Go back">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                            Back
                        </button>
                        <button type="button" id="pi_btn_next" class="pmpe-btn-primary pi-btn pi-btn-primary">
                            <span class="pi-btn-loading-spinner"></span>
                            <span id="pi_btn_next_text">Continue to Templates</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════════════
                 STEP 2: TEMPLATE PREFERENCES
                 ═══════════════════════════════════════════════════════════════ -->
            <div class="pmpe-step-content pi-step-content" data-step="2" <?= $current_step !== 2 ? 'style="display:none;"' : '' ?>>
                <div class="pmpe-step-card pi-step-card">
                    <div class="pmpe-step-card-header pi-step-card-header">
                        <div class="pmpe-step-badge pi-checkout-badge">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            Step 2 of <?= $total_display_steps ?>
                        </div>
                        <h2>Choose Your Proposal Template</h2>
                        <p>Select how your proposal letters will look. Your team can customise individual templates after checkout.</p>
                    </div>

                    <div class="pmpe-step-card-body pi-step-card-body">
                        <div id="pi_template_loading" class="pmpe-template-loading">
                            <div class="pi-processing-spinner"></div>
                            <p>Loading templates...</p>
                        </div>
                        <div id="pi_template_grid" class="pmpe-template-grid pi-template-grid" style="display:none;"></div>
                        <input type="hidden" id="pi_default_template" name="pmpe_default_template" value="<?= esc_attr(!empty($data['template']) ? $data['template'] : 'professional') ?>">

                        <div id="pi_template_preview_container" class="pmpe-template-preview-container pi-template-preview-container" style="display:none;">
                            <div class="pmpe-preview-header">
                                <h3>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    Live Preview
                                </h3>
                                <span id="pi_preview_template_name" class="pmpe-preview-badge">Professional</span>
                            </div>
                            <div id="pi_template_preview" class="pmpe-template-preview-document pi-template-preview"></div>
                        </div>

                        <div class="pmpe-preference-note pi-help">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                            <span>Your selected template will be saved to your account. You can change it anytime in <strong>Settings &rarr; Templates</strong> after checkout.</span>
                        </div>

                        <div id="pi_step2_error" class="pmpe-error-message pi-step-error" aria-live="polite"></div>
                    </div>

                    <div class="pmpe-nav-buttons pi-nav-buttons">
                        <button type="button" id="pi_btn_back" class="pmpe-btn-back pi-btn pi-btn-back" aria-label="Go back">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                            Back
                        </button>
                        <button type="button" id="pi_btn_next" class="pmpe-btn-primary pi-btn pi-btn-primary">
                            <span class="pi-btn-loading-spinner"></span>
                            <span id="pi_btn_next_text"><?= is_user_logged_in() ? 'Continue to Payment' : 'Continue to Account' ?></span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════════════
                 STEP 3: ACCOUNT CREATION
                 ═══════════════════════════════════════════════════════════════ -->
            <div class="pmpe-step-content pi-step-content" data-step="3" <?= $current_step !== 3 ? 'style="display:none;"' : '' ?>>
                <div class="pmpe-step-card pi-step-card">
                    <div class="pmpe-step-card-header pi-step-card-header">
                        <div class="pmpe-step-badge pi-checkout-badge">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            Step 3 of 4
                        </div>
                        <h2>Create Your Account</h2>
                        <p>Set up your enterprise account to manage your team and access all councils.</p>
                    </div>

                    <div class="pmpe-step-card-body pi-step-card-body">
                        <?php if (!empty($current_user->ID)): ?>
                            <div class="pmpe-logged-in-notice" role="status">
                                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                <div>
                                    <strong>Welcome back, <?= esc_html($current_user->display_name ?: $current_user->user_login) ?>!</strong>
                                    <p>You're already logged in. Continue to complete your enterprise subscription, or <a href="<?= wp_logout_url(esc_url_raw($_SERVER['REQUEST_URI'])) ?>">log out</a> to use a different account.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="pmpe-form-grid">
                                <div class="pmpe-form-field pi-form-group">
                                    <label for="username" class="pi-form-label">Username <span class="required pi-required">*</span></label>
                                    <input type="text" id="username" name="username" value="<?= esc_attr($data['username'] ?? $username ?? '') ?>" autocomplete="username" placeholder="Choose a unique username" required>
                                </div>

                                <div class="pmpe-form-row pi-form-row">
                                    <div class="pmpe-form-field pi-form-group">
                                        <label for="password" class="pi-form-label">Password <span class="required pi-required">*</span></label>
                                        <input type="password" id="password" name="password" autocomplete="new-password" placeholder="At least 8 characters" required aria-describedby="pi-password-requirements">
                                        <div class="pi-password-strength">
                                            <div class="pi-strength-bar"><div class="pi-strength-fill"></div></div>
                                            <span class="pi-strength-label"></span>
                                            <ul class="pi-password-requirements" id="pi-password-requirements">
                                                <li data-req="length">8+ characters</li>
                                                <li data-req="uppercase">Uppercase letter</li>
                                                <li data-req="number">Number</li>
                                                <li data-req="symbol">Symbol</li>
                                            </ul>
                                        </div>
                                    </div>
                                    <div class="pmpe-form-field pi-form-group">
                                        <label for="password2" class="pi-form-label">Confirm Password <span class="required pi-required">*</span></label>
                                        <input type="password" id="password2" name="password2" autocomplete="new-password" placeholder="Re-enter password" required>
                                    </div>
                                </div>

                                <div class="pmpe-form-row pi-form-row">
                                    <div class="pmpe-form-field pi-form-group">
                                        <label for="bemail" class="pi-form-label">Email Address <span class="required pi-required">*</span></label>
                                        <input type="email" id="bemail" name="bemail" value="<?= esc_attr($data['email'] ?? $bemail ?? '') ?>" placeholder="your@email.com" autocomplete="email" required aria-describedby="pi-email-check">
                                        <div class="pi-email-check" id="pi-email-check" aria-live="polite"></div>
                                    </div>
                                    <div class="pmpe-form-field pi-form-group">
                                        <label for="bconfirmemail" class="pi-form-label">Confirm Email <span class="required pi-required">*</span></label>
                                        <input type="email" id="bconfirmemail" name="bconfirmemail" value="<?= esc_attr($data['email'] ?? $bconfirmemail ?? '') ?>" placeholder="Re-enter email" autocomplete="email" required>
                                    </div>
                                </div>
                            </div>

                            <div class="pi-honeypot" aria-hidden="true">
                                <label for="fullname">Full Name</label>
                                <input id="fullname" name="fullname" type="text" value="" autocomplete="off" tabindex="-1" />
                            </div>

                            <div class="pmpe-login-prompt">
                                Already have an account? <a href="<?= esc_url(wp_login_url(add_query_arg('pmpro_level', $pmpro_level->id, pmpro_url('checkout')))) ?>">Log in here</a>
                            </div>
                        <?php endif; ?>

                        <div id="pi_step3_error" class="pmpe-error-message pi-step-error" aria-live="polite"></div>
                    </div>

                    <div class="pmpe-nav-buttons pi-nav-buttons">
                        <button type="button" id="pi_btn_back" class="pmpe-btn-back pi-btn pi-btn-back" aria-label="Go back">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                            Back
                        </button>
                        <button type="button" id="pi_btn_next" class="pmpe-btn-primary pi-btn pi-btn-primary">
                            <span class="pi-btn-loading-spinner"></span>
                            <span id="pi_btn_next_text">Continue to Payment</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════════════
                 STEP 4: BUSINESS INFO & PAYMENT
                 ═══════════════════════════════════════════════════════════════ -->
            <div class="pmpe-step-content pi-step-content" data-step="4" <?= $current_step !== 4 ? 'style="display:none;"' : '' ?>>
                <div class="pmpe-step-card pi-step-card">
                    <div class="pmpe-step-card-header pi-step-card-header">
                        <div class="pmpe-step-badge pi-checkout-badge">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                            Step <?= $total_display_steps ?> of <?= $total_display_steps ?>
                        </div>
                        <h2>Complete Enterprise Subscription</h2>
                        <p>Add your business details and enter payment information to activate your enterprise plan.</p>
                    </div>

                    <div class="pmpe-step-card-body pi-step-card-body">
                        <div class="pmpe-order-summary pi-order-summary">
                            <h3>
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                                Order Summary
                            </h3>
                            <div class="pmpe-summary-row pi-order-line-item">
                                <span>Plan:</span>
                                <strong>Enterprise — All Councils</strong>
                            </div>
                            <div class="pmpe-summary-row pi-order-line-item">
                                <span>Team Seats:</span>
                                <strong id="pmpe_summary_seats"><?= $data['team_seats'] ?? 1 ?> seat<?= ($data['team_seats'] ?? 1) != 1 ? 's' : '' ?></strong>
                            </div>
                            <div class="pmpe-summary-row pi-order-line-item">
                                <span>Monthly Cost:</span>
                                <strong>&pound;<?= number_format($enterprise_price, 2) ?>/month</strong>
                            </div>
                            <div class="pmpe-summary-row pmpe-summary-total pi-order-total">
                                <span>Total Due Today:</span>
                                <strong class="pi-order-total-amount" id="pmpe_summary_total">&pound;<?= number_format($enterprise_price, 2) ?></strong>
                            </div>
                        </div>

                        <!-- Business Info Toggle -->
                        <div class="pmpe-business-section pi-toggle-section">
                            <button type="button" class="pmpe-section-header pi-toggle-header" id="pi_business_toggle" aria-expanded="false" aria-controls="pi_business_fields">
                                <h3>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                                    Business Information
                                    <span class="pmpe-optional-badge">Optional</span>
                                </h3>
                                <svg class="pmpe-toggle-icon pi-toggle-icon" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>

                            <div class="pmpe-business-fields pi-toggle-fields" id="pi_business_fields">
                                <p class="pmpe-field-hint">This information will appear on your proposal letters. You can update it anytime from your account settings.</p>
                                <div class="pmpe-form-grid">
                                    <div class="pmpe-form-field pi-form-group">
                                        <label for="pmpe_company_name" class="pi-form-label">Company Name</label>
                                        <input type="text" id="pmpe_company_name" name="pmpe_company_name" value="<?= esc_attr($data['business']['pmpe_company_name'] ?? '') ?>" placeholder="Your Company Ltd" autocomplete="organization">
                                    </div>
                                    <div class="pmpe-form-row pi-form-row">
                                        <div class="pmpe-form-field pi-form-group">
                                            <label for="pmpe_business_email" class="pi-form-label">Business Email</label>
                                            <input type="email" id="pmpe_business_email" name="pmpe_business_email" value="<?= esc_attr($data['business']['pmpe_business_email'] ?? '') ?>" placeholder="contact@company.com" autocomplete="email">
                                        </div>
                                        <div class="pmpe-form-field pi-form-group">
                                            <label for="pmpe_business_phone" class="pi-form-label">Business Phone</label>
                                            <input type="tel" id="pmpe_business_phone" name="pmpe_business_phone" value="<?= esc_attr($data['business']['pmpe_business_phone'] ?? '') ?>" placeholder="+44 123 456 7890" autocomplete="tel">
                                        </div>
                                    </div>
                                    <div class="pmpe-form-field pi-form-group">
                                        <label for="pmpe_company_address" class="pi-form-label">Business Address</label>
                                        <textarea id="pmpe_company_address" name="pmpe_company_address" rows="3" placeholder="123 Business Street&#10;City, Postcode" autocomplete="street-address"><?= esc_textarea($data['business']['pmpe_company_address'] ?? '') ?></textarea>
                                    </div>
                                    <div class="pmpe-form-row pi-form-row">
                                        <div class="pmpe-form-field pi-form-group">
                                            <label for="pmpe_website" class="pi-form-label">Website</label>
                                            <input type="url" id="pmpe_website" name="pmpe_website" value="<?= esc_url($data['business']['pmpe_website'] ?? '') ?>" placeholder="https://www.yourcompany.com" autocomplete="url">
                                        </div>
                                        <div class="pmpe-form-field pi-form-group">
                                            <label for="pmpe_vat_number" class="pi-form-label">VAT Number</label>
                                            <input type="text" id="pmpe_vat_number" name="pmpe_vat_number" value="<?= esc_attr($data['business']['pmpe_vat_number'] ?? '') ?>" placeholder="GB123456789">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Team Info Toggle (separate from business info) -->
                        <div class="pmpe-team-section pi-toggle-section">
                            <button type="button" class="pmpe-section-header pi-toggle-header" id="pi_team_toggle" aria-expanded="false" aria-controls="pi_team_fields">
                                <h3>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                    Team Member Details
                                    <span class="pmpe-optional-badge">Optional</span>
                                </h3>
                                <svg class="pmpe-toggle-icon pi-toggle-icon" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>

                            <div class="pmpe-team-fields pi-toggle-fields" id="pi_team_fields">
                                <p class="pmpe-field-hint">Add your team members' email addresses. They'll receive an invitation to join your enterprise plan.</p>
                                <div class="pmpe-form-field pi-form-group">
                                    <label for="pmpe_team_emails" class="pi-form-label">Team Member Emails</label>
                                    <textarea id="pmpe_team_emails" name="pmpe_team_emails" rows="4" placeholder="member1@company.com&#10;member2@company.com"><?= esc_textarea($data['team_emails'] ?? '') ?></textarea>
                                </div>
                            </div>
                        </div>

                        <?php if (apply_filters('pmpro_include_billing_address_fields', true) && $pmpro_requirebilling): ?>
                            <div class="pmpe-billing-section pi-billing-section">
                                <h3>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                    Billing Address
                                </h3>
                                <div class="pmpe-form-grid">
                                    <div class="pmpe-form-row pi-form-row">
                                        <div class="pmpe-form-field pi-form-group">
                                            <label for="bfirstname" class="pi-form-label">First Name <span class="required pi-required">*</span></label>
                                            <input id="bfirstname" name="bfirstname" type="text" value="<?= esc_attr($bfirstname ?? '') ?>" autocomplete="given-name" placeholder="First name" required>
                                        </div>
                                        <div class="pmpe-form-field pi-form-group">
                                            <label for="blastname" class="pi-form-label">Last Name <span class="required pi-required">*</span></label>
                                            <input id="blastname" name="blastname" type="text" value="<?= esc_attr($blastname ?? '') ?>" autocomplete="family-name" placeholder="Last name" required>
                                        </div>
                                    </div>
                                    <div class="pmpe-form-field pi-form-group">
                                        <label for="baddress1" class="pi-form-label">Address <span class="required pi-required">*</span></label>
                                        <input id="baddress1" name="baddress1" type="text" value="<?= esc_attr($baddress1 ?? '') ?>" autocomplete="street-address" placeholder="Street address" required>
                                    </div>
                                    <div class="pmpe-form-row pi-form-row">
                                        <div class="pmpe-form-field pi-form-group">
                                            <label for="bcity" class="pi-form-label">City <span class="pi-required">*</span></label>
                                            <input id="bcity" name="bcity" type="text" value="<?= esc_attr($bcity ?? '') ?>" autocomplete="address-level2" placeholder="City" required>
                                        </div>
                                        <div class="pmpe-form-field pi-form-group">
                                            <label for="bzipcode" class="pi-form-label">Postcode <span class="required pi-required">*</span></label>
                                            <input id="bzipcode" name="bzipcode" type="text" value="<?= esc_attr($bzipcode ?? '') ?>" autocomplete="postal-code" placeholder="Postcode" required>
                                        </div>
                                    </div>
                                    <div class="pmpe-form-field pi-form-group">
                                        <label for="bphone" class="pi-form-label">Phone <span class="required pi-required">*</span></label>
                                        <input id="bphone" name="bphone" type="tel" value="<?= esc_attr($bphone ?? '') ?>" autocomplete="tel" placeholder="Phone number" required>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php
                            do_action('pmpro_checkout_boxes', $pmpro_level);
                            do_action('pmpro_checkout_after_billing_fields');
                        ?>

                        <?php if (apply_filters('pmpro_include_payment_information_fields', true) && $pmpro_requirebilling): ?>
                            <div class="pmpe-payment-section">
                                <h3>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                                    Payment Details
                                </h3>
                                <div class="pmpe-form-grid">
                                    <input type="hidden" id="CardType" name="CardType" value="<?= esc_attr($CardType ?? '') ?>" />
                                    <div class="pmpe-form-field pi-form-group">
                                        <label for="AccountNumber" class="pi-form-label">Card Number <span class="required pi-required">*</span></label>
                                        <div class="pi-card-input-wrapper">
                                            <input id="AccountNumber" name="AccountNumber" type="text" value="" autocomplete="cc-number" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" required data-mask="card-number">
                                            <div class="pi-card-brand-indicator" aria-hidden="true"></div>
                                        </div>
                                    </div>
                                    <div class="pmpe-form-row pi-form-row">
                                        <div class="pmpe-form-field pi-form-group">
                                            <label class="pi-form-label">Expiration Date <span class="required pi-required">*</span></label>
                                            <div class="pmpe-expiry-fields">
                                                <select id="ExpirationMonth" name="ExpirationMonth" required autocomplete="cc-exp-month">
                                                    <option value="">MM</option>
                                                    <?php for ($i = 1; $i <= 12; $i++): ?>
                                                        <?php $month = str_pad($i, 2, '0', STR_PAD_LEFT); ?>
                                                        <option value="<?= $month ?>" <?= selected($ExpirationMonth ?? '', $month, false) ?>><?= $month ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                                <span>/</span>
                                                <select id="ExpirationYear" name="ExpirationYear" required autocomplete="cc-exp-year">
                                                    <option value="">YY</option>
                                                    <?php
                                                    $num_years = apply_filters('pmpro_num_expiration_years', 10);
                                                    for ($i = date('Y'); $i < date('Y') + $num_years; $i++):
                                                    ?>
                                                        <option value="<?= $i ?>" <?= selected($ExpirationYear ?? '', $i, false) ?>><?= substr($i, -2) ?></option>
                                                    <?php endfor; ?>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="pmpe-form-field pi-form-group">
                                            <label for="CVV" class="pi-form-label">Security Code <span class="required pi-required">*</span></label>
                                            <input id="CVV" name="CVV" type="text" maxlength="4" placeholder="CVV" autocomplete="cc-csc" required data-mask="cvv">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php do_action('pmpro_checkout_after_payment_information_fields', $pmpro_level); ?>

                        <!-- Trust Signals -->
                        <div class="pi-trust-signals" aria-label="Trust guarantees">
                            <div class="pi-trust-signal">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                30-day money-back guarantee
                            </div>
                            <div class="pi-trust-signal">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                                Cancel anytime, no hassle
                            </div>
                            <div class="pi-trust-signal">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                SSL encrypted payment
                            </div>
                            <div class="pi-trust-signal">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                Dedicated enterprise support
                            </div>
                        </div>

                        <div id="pi_step4_error" class="pmpe-error-message pi-step-error" aria-live="polite"></div>
                    </div>

                    <div class="pmpe-nav-buttons pi-nav-buttons">
                        <button type="button" id="pi_btn_back" class="pmpe-btn-back pi-btn pi-btn-back" aria-label="Go back">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                            Back
                        </button>
                        <span id="pmpro_submit_span">
                            <input type="hidden" name="submit-checkout" value="1" />
                            <input type="hidden" name="confirm" value="1" />
                            <input type="hidden" name="gateway" value="<?= esc_attr($gateway ?: 'stripe') ?>" />
                            <button type="submit" id="pmpro_btn-submit" class="pmpe-btn-primary pmpe-btn-submit pi-btn pi-btn-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                Complete Enterprise Subscription
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                            </button>
                        </span>
                        <div id="pi_processing_message" class="pmpe-processing pi-processing-message">
                            <div class="pi-processing-spinner"></div>
                            Processing your subscription...
                        </div>
                    </div>
                </div>
            </div>

            <?php
                do_action('pmpro_checkout_before_submit_button', $pmpro_level);
                wp_nonce_field('pmpro_checkout_nonce', 'pmpro_checkout_nonce');
            ?>

        </form>

        <?php do_action('pmpro_checkout_after_form', $pmpro_level); ?>

    </section>
    </main>

</div>

<script>
window.piCheckoutConfig = {
    type: 'enterprise',
    totalSteps: 4,
    prefix: 'pmpe',
    checkoutUrl: '<?= esc_js($checkout_url) ?>',
    ajaxUrl: '<?= esc_js(admin_url('admin-ajax.php')) ?>',
    restUrl: '<?= esc_js(rest_url('pi/v1')) ?>',
    nonce: '<?= wp_create_nonce('pi_checkout_nonce') ?>',
    restNonce: '<?= wp_create_nonce('wp_rest') ?>',
    isLoggedIn: <?= is_user_logged_in() ? 'true' : 'false' ?>,
    price: <?= floatval($enterprise_price) ?>,
    minSelection: 0,
    maxSelection: 0,
    selectedCouncils: [],
    templates: {},
    strings: {
        completeSubscription: 'Complete Enterprise Subscription',
        perMonth: '/month'
    }
};
</script>
