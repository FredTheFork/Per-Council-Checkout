<?php
/**
 * Template: Checkout - Multi-Step Trial Wizard
 * Planning Index Free Trial Implementation
 * Version: 4.0.0 - Unified Design System
 *
 * 4-Step Free Trial Checkout:
 * 1. Council Selection (max 5)
 * 2. Template Preferences
 * 3. Account Creation
 * 4. Business Info & Confirmation
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
global $bemail, $bconfirmemail;

if (!session_id()) {
    @session_start();
}

$data = isset($_SESSION[PMPC_TRIAL_SESSION_KEY]) ? (array) $_SESSION[PMPC_TRIAL_SESSION_KEY] : [];

$selected_councils = !empty($data['councils']) ? (array) $data['councils'] : [];
if (empty($selected_councils) && is_user_logged_in()) {
    $stored = get_user_meta(get_current_user_id(), 'pmpc_selected_councils', true);
    if (is_array($stored)) {
        $selected_councils = $stored;
    }
}

if (empty($pmpro_level) || !is_object($pmpro_level) || empty($pmpro_level->id)) {
    $level_id = isset($_REQUEST['pmpro_level']) ? intval($_REQUEST['pmpro_level'])
              : (isset($_REQUEST['level']) ? intval($_REQUEST['level']) : PMPC_TRIAL_LEVEL_ID);

    if ($level_id > 0 && function_exists('pmpro_getLevel')) {
        $pmpro_level = pmpro_getLevel($level_id);
    }

    if (empty($pmpro_level) || !is_object($pmpro_level)) {
        $pmpro_level = new stdClass();
        $pmpro_level->id = PMPC_TRIAL_LEVEL_ID;
        $pmpro_level->name = __('Free Trial', 'paid-memberships-pro');
    }
}

$default_gateway = get_option('pmpro_gateway', '');
$pmpro_checkout_gateway_class = 'pmpro_section pmpro_checkout_gateway-trial';

$current_step = isset($_GET['step']) ? max(1, min(4, intval($_GET['step']))) : 1;

if ($current_step === 3 && is_user_logged_in()) {
    $current_step = 4;
}

$steps = [
    1 => ['title' => 'Select Councils', 'icon' => 'map-pin', 'desc' => 'Choose up to 5 areas'],
    2 => ['title' => 'Template', 'icon' => 'file-text', 'desc' => 'Pick your letter style'],
    3 => ['title' => 'Account', 'icon' => 'user', 'desc' => 'Create your account'],
    4 => ['title' => 'Confirm', 'icon' => 'check-circle', 'desc' => 'Start your trial'],
];

$checkout_url = pmpro_url('checkout');
$councils = function_exists('pmpc_trial_get_all_councils') ? pmpc_trial_get_all_councils() : [];
$council_count = count($selected_councils);
$total_display_steps = is_user_logged_in() ? 3 : 4;

// Enqueue unified assets
if (function_exists('pi_checkout_core_asset_url')) {
    wp_enqueue_style('pi-checkout-tokens', pi_checkout_core_asset_url('pi-checkout-tokens.min.css'), [], '1.0.0');
    wp_enqueue_style('pi-checkout-base', pi_checkout_core_asset_url('pi-checkout-base.min.css'), ['pi-checkout-tokens'], '1.0.0');
    wp_enqueue_style('pi-checkout-trial', pi_checkout_core_asset_url('pi-checkout-trial.min.css'), ['pi-checkout-base'], '1.0.0');
    wp_enqueue_script('pi-checkout', pi_checkout_core_asset_url('pi-checkout.min.js'), ['jquery'], '1.0.0', true);
} else {
    wp_enqueue_style('pi-checkout-tokens', get_stylesheet_directory_uri() . '/assets/pi-checkout-tokens.css', [], '2.0.0');
    wp_enqueue_style('pi-checkout-base', get_stylesheet_directory_uri() . '/assets/pi-checkout-base.css', ['pi-checkout-tokens'], '2.0.0');
    wp_enqueue_style('pi-checkout-trial', get_stylesheet_directory_uri() . '/assets/pi-checkout-trial.css', ['pi-checkout-base'], '2.0.0');
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
<style><?= file_get_contents($_pi_asset_path . 'pi-checkout-trial.min.css') ?></style>
<script><?= file_get_contents($_pi_asset_path . 'pi-checkout.min.js') ?></script>
<a href="#pi-main-content" class="pi-skip-link">Skip to main content</a>

<div id="pmpro_checkout_wrapper" class="pmpro-checkout-wrapper pi-wizard-checkout pmpc-wizard-checkout pi-checkout-body pi-checkout-trial">

    <!-- Trust Badge Bar -->
    <div class="pi-trust-bar" role="region" aria-label="Secure checkout">
        <div class="pi-trust-item">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
            Secure Checkout
        </div>
        <div class="pi-trust-divider"></div>
        <div class="pi-trust-item">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
            Free Trial — No Payment Required
        </div>
    </div>

    <!-- Trial Banner -->
    <div class="pi-trial-banner" role="region" aria-label="Trial information">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v4M12 18v4M4.93 4.93l2.83 2.83M16.24 16.24l2.83 2.83M2 12h4M18 12h4M4.93 19.07l2.83-2.83M16.24 7.76l2.83-2.83"/></svg>
        <div class="pi-trial-banner-text">
            <strong>2 Week Free Trial</strong>
            <span>Try Planning Index free for 14 days — No payment required — Access up to 5 councils</span>
        </div>
    </div>

    <!-- Progress Header -->
    <div class="pmpc-progress-header pi-progress-container" role="progressbar" aria-valuenow="<?= esc_attr($current_step) ?>" aria-valuemin="1" aria-valuemax="<?= esc_attr($total_display_steps) ?>" aria-label="Checkout progress">
        <div class="pmpc-progress-container pi-progress-track">
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
                <div class="pmpc-progress-step pi-progress-step <?= $is_active ? 'active' : ($is_completed ? 'completed' : '') ?>"
                    data-step="<?= $s['num'] ?>"
                    <?= $is_active ? 'aria-current="step"' : '' ?>>
                    <div class="pmpc-progress-circle pi-progress-circle">
                        <span><?= $step_index ?></span>
                    </div>
                    <div class="pmpc-progress-info pi-progress-label">
                        <span class="pmpc-progress-title"><?= esc_html($s['title']) ?></span>
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
            <input type="hidden" id="pi_current_step" name="pmpc_current_step" value="<?= $current_step ?>" />
            <input type="hidden" id="pi_selected_councils" name="pi_selected_councils" value="<?= esc_attr(implode(',', $selected_councils)) ?>" />

            <?php if ($pmpro_msg): ?>
                <div role="alert" id="pmpro_message" class="pmpro_message <?= esc_attr($pmpro_msgt) ?>">
                    <?= wp_kses_post(apply_filters('pmpro_checkout_message', $pmpro_msg, $pmpro_msgt)) ?>
                </div>
            <?php else: ?>
                <div id="pmpro_message" class="pmpro_message" style="display:none;"></div>
            <?php endif; ?>

            <!-- ═══════════════════════════════════════════════════════════════
                 STEP 1: COUNCIL SELECTION (Max 5)
                 ═══════════════════════════════════════════════════════════════ -->
            <div class="pmpc-step-content pi-step-content" data-step="1" <?= $current_step !== 1 ? 'style="display:none;"' : '' ?>>
                <div class="pmpc-step-card pi-step-card">
                    <div class="pmpc-step-card-header pi-step-card-header">
                        <div class="pmpc-step-badge pi-checkout-badge">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            Step 1 of <?= $total_display_steps ?>
                        </div>
                        <h2>Select Up to 5 Councils</h2>
                        <p>Choose up to <strong>5 councils</strong> to try during your free trial. You can add more after subscribing.</p>
                    </div>

                    <div class="pmpc-step-card-body pi-step-card-body">
                        <div class="pmpc-benefits-grid pi-benefits-grid">
                            <div class="pmpc-benefit-item pi-benefit-card">
                                <div class="pmpc-benefit-icon pi-benefit-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                </div>
                                <div class="pmpc-benefit-text pi-benefit-content">
                                    <strong>14 Days Free</strong>
                                    <span>Full access to all features, no payment needed</span>
                                </div>
                            </div>
                            <div class="pmpc-benefit-item pi-benefit-card">
                                <div class="pmpc-benefit-icon pi-benefit-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                </div>
                                <div class="pmpc-benefit-text pi-benefit-content">
                                    <strong>Up to 5 Councils</strong>
                                    <span>Test with the areas that matter most to you</span>
                                </div>
                            </div>
                            <div class="pmpc-benefit-item pi-benefit-card">
                                <div class="pmpc-benefit-icon pi-benefit-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                                </div>
                                <div class="pmpc-benefit-text pi-benefit-content">
                                    <strong>No Obligation</strong>
                                    <span>Cancel anytime during the trial at no cost</span>
                                </div>
                            </div>
                        </div>

                        <div class="pmpc-search-container pi-search-container">
                            <svg class="pmpc-search-icon pi-search-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                            <input type="text" id="pmpc_council_search" class="pmpc-search-input pi-search-input" placeholder="Search councils by name..." autocomplete="off" aria-label="Search councils">
                        </div>

                        <div class="pmpc-selection-header">
                            <span class="pmpc-selection-label">Available Councils</span>
                            <span class="pmpc-selection-counter <?= $council_count >= 1 ? 'valid' : '' ?>" aria-live="polite">
                                <strong id="pi_selected_count"><?= $council_count ?></strong> / 5 selected
                            </span>
                        </div>

                        <div id="pmpc_council_grid" class="pmpc-council-grid pi-council-grid" role="group" aria-label="Council selection grid">
                            <?php
                            $sorted_councils = $councils;
                            if (is_array($sorted_councils)) {
                                usort($sorted_councils, function($a, $b) {
                                    $na = is_array($a) ? ($a['name'] ?? $a) : $a;
                                    $nb = is_array($b) ? ($b['name'] ?? $b) : $b;
                                    return strcmp($na, $nb);
                                });
                            }
                            foreach ($sorted_councils as $c):
                                $council_id   = is_array($c) ? ($c['id']   ?? $c['name'] ?? $c) : $c;
                                $council_name = is_array($c) ? ($c['name'] ?? $c) : $c;
                                $is_selected  = in_array($council_id, $selected_councils);
                            ?>
                                <div class="pmpc-council-item pi-council-item <?= $is_selected ? 'selected' : '' ?>"
                                    data-council-id="<?= esc_attr($council_id) ?>"
                                    data-council-name="<?= esc_attr($council_name) ?>"
                                    tabindex="0"
                                    role="checkbox"
                                    aria-checked="<?= $is_selected ? 'true' : 'false' ?>">
                                    <div class="pmpc-council-checkbox pi-council-checkbox"></div>
                                    <span class="pmpc-council-name pi-council-name"><?= esc_html($council_name) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <select id="pmpc_councils" name="pmpc_councils[]" multiple="multiple" style="display:none !important;">
                            <?php foreach ($councils as $c):
                                $opt_id = is_array($c) ? ($c['id'] ?? $c['name'] ?? $c) : $c;
                                $opt_name = is_array($c) ? ($c['name'] ?? $c) : $c;
                            ?>
                                <option value="<?= esc_attr($opt_id) ?>" <?= in_array($opt_id, $selected_councils) ? 'selected' : '' ?>>
                                    <?= esc_html($opt_name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div class="pmpc-selected-summary">
                            <div class="pmpc-selected-header">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                <span>Your Selected Councils</span>
                            </div>
                            <div id="pmpc_selected_tags" class="pmpc-selected-tags">
                                <?php if (empty($selected_councils)): ?>
                                    <p class="pmpc-empty-selection">Click on councils above to select them (max 5)</p>
                                <?php else: ?>
                                    <?php foreach ($selected_councils as $c): ?>
                                        <span class="pmpc-tag" data-council="<?= esc_attr($c) ?>">
                                            <?= esc_html($c) ?>
                                            <button type="button" class="pmpc-tag-remove" aria-label="Remove <?= esc_attr($c) ?>">&times;</button>
                                        </span>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="pmpc-price-display pi-price-display" aria-live="polite">
                            <div class="pmpc-price-info pi-price-info">
                                <span class="pmpc-price-label pi-price-label">Trial Price</span>
                                <span class="pmpc-price-amount pi-price-amount" id="pi_price_amount">Free</span>
                                <span class="pmpc-price-period pi-price-period">for 14 days</span>
                            </div>
                            <div class="pmpc-cancel-note">
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                                No payment required
                            </div>
                        </div>

                        <div id="pi_step1_error" class="pmpc-error-message pi-step-error" aria-live="polite"></div>
                    </div>

                    <div class="pmpc-nav-buttons pi-nav-buttons">
                        <button type="button" id="pi_btn_back" class="pmpc-btn-back pi-btn pi-btn-back" style="visibility:hidden;" aria-label="Go back">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                            Back
                        </button>
                        <button type="button" id="pi_btn_next" class="pmpc-btn-primary pi-btn pi-btn-primary">
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
            <div class="pmpc-step-content pi-step-content" data-step="2" <?= $current_step !== 2 ? 'style="display:none;"' : '' ?>>
                <div class="pmpc-step-card pi-step-card">
                    <div class="pmpc-step-card-header pi-step-card-header">
                        <div class="pmpc-step-badge pi-checkout-badge">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            Step 2 of <?= $total_display_steps ?>
                        </div>
                        <h2>Choose Your Proposal Template</h2>
                        <p>Select how your proposal letters will look during your trial. You can change this anytime in Settings.</p>
                    </div>

                    <div class="pmpc-step-card-body pi-step-card-body">
                        <div id="pi_template_loading" class="pmpc-template-loading">
                            <div class="pi-processing-spinner"></div>
                            <p>Loading templates...</p>
                        </div>
                        <div id="pi_template_grid" class="pmpc-template-grid pi-template-grid" style="display:none;"></div>
                        <input type="hidden" id="pi_default_template" name="pmpc_default_template" value="<?= esc_attr(!empty($data['template']) ? $data['template'] : 'basic') ?>">

                        <div id="pi_template_preview_container" class="pmpc-template-preview-container pi-template-preview-container" style="display:none;">
                            <div class="pmpc-preview-header">
                                <h3>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    Live Preview
                                </h3>
                                <span id="pi_preview_template_name" class="pmpc-preview-badge">Basic</span>
                            </div>
                            <div id="pi_template_preview" class="pmpc-template-preview-document pi-template-preview"></div>
                        </div>

                        <div class="pmpc-preference-note pi-help">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                            <span>Your selected template will be saved to your account. You can change it anytime in <strong>Settings &rarr; Templates</strong> after checkout.</span>
                        </div>

                        <div id="pi_step2_error" class="pmpc-error-message pi-step-error" aria-live="polite"></div>
                    </div>

                    <div class="pmpc-nav-buttons pi-nav-buttons">
                        <button type="button" id="pi_btn_back" class="pmpc-btn-back pi-btn pi-btn-back" aria-label="Go back">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                            Back
                        </button>
                        <button type="button" id="pi_btn_next" class="pmpc-btn-primary pi-btn pi-btn-primary">
                            <span class="pi-btn-loading-spinner"></span>
                            <span id="pi_btn_next_text"><?= is_user_logged_in() ? 'Continue to Confirm' : 'Continue to Account' ?></span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════════════
                 STEP 3: ACCOUNT CREATION
                 ═══════════════════════════════════════════════════════════════ -->
            <div class="pmpc-step-content pi-step-content" data-step="3" <?= $current_step !== 3 ? 'style="display:none;"' : '' ?>>
                <div class="pmpc-step-card pi-step-card">
                    <div class="pmpc-step-card-header pi-step-card-header">
                        <div class="pmpc-step-badge pi-checkout-badge">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            Step 3 of 4
                        </div>
                        <h2>Create Your Account</h2>
                        <p>Set up your account to access your trial dashboard and manage your councils.</p>
                    </div>

                    <div class="pmpc-step-card-body pi-step-card-body">
                        <?php if (!empty($current_user->ID)): ?>
                            <div class="pmpc-logged-in-notice" role="status">
                                <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                <div>
                                    <strong>Welcome back, <?= esc_html($current_user->display_name ?: $current_user->user_login) ?>!</strong>
                                    <p>You're already logged in. Continue to start your free trial, or <a href="<?= wp_logout_url(esc_url_raw($_SERVER['REQUEST_URI'])) ?>">log out</a> to use a different account.</p>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="pmpc-form-grid">
                                <div class="pmpc-form-field pi-form-group">
                                    <label for="username" class="pi-form-label">Username <span class="required pi-required">*</span></label>
                                    <input type="text" id="username" name="username" value="<?= esc_attr($data['username'] ?? $username ?? '') ?>" autocomplete="username" placeholder="Choose a unique username" required>
                                </div>

                                <div class="pmpc-form-row pi-form-row">
                                    <div class="pmpc-form-field pi-form-group">
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
                                    <div class="pmpc-form-field pi-form-group">
                                        <label for="password2" class="pi-form-label">Confirm Password <span class="required pi-required">*</span></label>
                                        <input type="password" id="password2" name="password2" autocomplete="new-password" placeholder="Re-enter password" required>
                                    </div>
                                </div>

                                <div class="pmpc-form-row pi-form-row">
                                    <div class="pmpc-form-field pi-form-group">
                                        <label for="bemail" class="pi-form-label">Email Address <span class="required pi-required">*</span></label>
                                        <input type="email" id="bemail" name="bemail" value="<?= esc_attr($data['email'] ?? $bemail ?? '') ?>" placeholder="your@email.com" autocomplete="email" required aria-describedby="pi-email-check">
                                        <div class="pi-email-check" id="pi-email-check" aria-live="polite"></div>
                                    </div>
                                    <div class="pmpc-form-field pi-form-group">
                                        <label for="bconfirmemail" class="pi-form-label">Confirm Email <span class="required pi-required">*</span></label>
                                        <input type="email" id="bconfirmemail" name="bconfirmemail" value="<?= esc_attr($data['email'] ?? $bconfirmemail ?? '') ?>" placeholder="Re-enter email" autocomplete="email" required>
                                    </div>
                                </div>
                            </div>

                            <div class="pi-honeypot" aria-hidden="true">
                                <label for="fullname">Full Name</label>
                                <input id="fullname" name="fullname" type="text" value="" autocomplete="off" tabindex="-1" />
                            </div>

                            <div class="pmpc-login-prompt">
                                Already have an account? <a href="<?= esc_url(wp_login_url(add_query_arg('pmpro_level', $pmpro_level->id, pmpro_url('checkout')))) ?>">Log in here</a>
                            </div>
                        <?php endif; ?>

                        <div id="pi_step3_error" class="pmpc-error-message pi-step-error" aria-live="polite"></div>
                    </div>

                    <div class="pmpc-nav-buttons pi-nav-buttons">
                        <button type="button" id="pi_btn_back" class="pmpc-btn-back pi-btn pi-btn-back" aria-label="Go back">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                            Back
                        </button>
                        <button type="button" id="pi_btn_next" class="pmpc-btn-primary pi-btn pi-btn-primary">
                            <span class="pi-btn-loading-spinner"></span>
                            <span id="pi_btn_next_text">Continue to Confirm</span>
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                        </button>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════════════════════
                 STEP 4: BUSINESS INFO & CONFIRMATION (No Payment)
                 ═══════════════════════════════════════════════════════════════ -->
            <div class="pmpc-step-content pi-step-content" data-step="4" <?= $current_step !== 4 ? 'style="display:none;"' : '' ?>>
                <div class="pmpc-step-card pi-step-card">
                    <div class="pmpc-step-card-header pi-step-card-header">
                        <div class="pmpc-step-badge pi-checkout-badge">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                            Step <?= $total_display_steps ?> of <?= $total_display_steps ?>
                        </div>
                        <h2>Start Your Free Trial</h2>
                        <p>Add your business details (optional) and confirm to start your 14-day free trial. No payment required!</p>
                    </div>

                    <div class="pmpc-step-card-body pi-step-card-body">
                        <div class="pmpc-order-summary pi-order-summary">
                            <h3>
                                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                                Trial Summary
                            </h3>
                            <div class="pmpc-summary-row pi-order-line-item">
                                <span>Selected Councils:</span>
                                <strong id="pmpc_summary_councils"><?= $council_count ?> council<?= $council_count !== 1 ? 's' : '' ?></strong>
                            </div>
                            <div class="pmpc-summary-row pi-order-line-item">
                                <span>Trial Duration:</span>
                                <strong>14 days</strong>
                            </div>
                            <div class="pmpc-summary-row pmpc-summary-total pi-order-total">
                                <span>Total Due Today:</span>
                                <strong class="pi-order-total-amount">Free</strong>
                            </div>
                        </div>

                        <div class="pmpc-business-section pi-toggle-section">
                            <button type="button" class="pmpc-section-header pi-toggle-header" id="pi_business_toggle" aria-expanded="false" aria-controls="pi_business_fields">
                                <h3>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                                    Business Information
                                    <span class="pmpc-optional-badge">Optional</span>
                                </h3>
                                <svg class="pmpc-toggle-icon pi-toggle-icon" xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 12 15 18 9"/></svg>
                            </button>

                            <div class="pmpc-business-fields pi-toggle-fields" id="pi_business_fields">
                                <p class="pmpc-field-hint">This information will appear on your proposal letters. You can update it anytime from your account settings.</p>
                                <div class="pmpc-form-grid">
                                    <div class="pmpc-form-field pi-form-group">
                                        <label for="pmpc_company_name" class="pi-form-label">Company Name</label>
                                        <input type="text" id="pmpc_company_name" name="pmpc_company_name" value="<?= esc_attr($data['business']['pmpc_company_name'] ?? '') ?>" placeholder="Your Company Ltd" autocomplete="organization">
                                    </div>
                                    <div class="pmpc-form-row pi-form-row">
                                        <div class="pmpc-form-field pi-form-group">
                                            <label for="pmpc_business_email" class="pi-form-label">Business Email</label>
                                            <input type="email" id="pmpc_business_email" name="pmpc_business_email" value="<?= esc_attr($data['business']['pmpc_business_email'] ?? '') ?>" placeholder="contact@company.com" autocomplete="email">
                                        </div>
                                        <div class="pmpc-form-field pi-form-group">
                                            <label for="pmpc_business_phone" class="pi-form-label">Business Phone</label>
                                            <input type="tel" id="pmpc_business_phone" name="pmpc_business_phone" value="<?= esc_attr($data['business']['pmpc_business_phone'] ?? '') ?>" placeholder="+44 123 456 7890" autocomplete="tel">
                                        </div>
                                    </div>
                                    <div class="pmpc-form-field pi-form-group">
                                        <label for="pmpc_company_address" class="pi-form-label">Business Address</label>
                                        <textarea id="pmpc_company_address" name="pmpc_company_address" rows="3" placeholder="123 Business Street&#10;City, Postcode" autocomplete="street-address"><?= esc_textarea($data['business']['pmpc_company_address'] ?? '') ?></textarea>
                                    </div>
                                    <div class="pmpc-form-row pi-form-row">
                                        <div class="pmpc-form-field pi-form-group">
                                            <label for="pmpc_website" class="pi-form-label">Website</label>
                                            <input type="url" id="pmpc_website" name="pmpc_website" value="<?= esc_url($data['business']['pmpc_website'] ?? '') ?>" placeholder="https://www.yourcompany.com" autocomplete="url">
                                        </div>
                                        <div class="pmpc-form-field pi-form-group">
                                            <label for="pmpc_vat_number" class="pi-form-label">VAT Number</label>
                                            <input type="text" id="pmpc_vat_number" name="pmpc_vat_number" value="<?= esc_attr($data['business']['pmpc_vat_number'] ?? '') ?>" placeholder="GB123456789">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Trust Signals -->
                        <div class="pi-trust-signals" aria-label="Trust guarantees">
                            <div class="pi-trust-signal">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                                No payment required for trial
                            </div>
                            <div class="pi-trust-signal">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                                Cancel anytime, no obligation
                            </div>
                            <div class="pi-trust-signal">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                Your data is secure
                            </div>
                            <div class="pi-trust-signal">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                Trusted by 5,000+ professionals
                            </div>
                        </div>

                        <div id="pi_step4_error" class="pmpc-error-message pi-step-error" aria-live="polite"></div>
                    </div>

                    <div class="pmpc-nav-buttons pi-nav-buttons">
                        <button type="button" id="pi_btn_back" class="pmpc-btn-back pi-btn pi-btn-back" aria-label="Go back">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                            Back
                        </button>
                        <span id="pmpro_submit_span">
                            <input type="hidden" name="submit-checkout" value="1" />
                            <input type="hidden" name="confirm" value="1" />
                            <button type="submit" id="pmpro_btn-submit" class="pmpc-btn-primary pmpc-btn-submit pi-btn pi-btn-primary">
                                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                                Start Free Trial
                                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                            </button>
                        </span>
                        <div id="pi_processing_message" class="pmpc-processing pi-processing-message">
                            <div class="pi-processing-spinner"></div>
                            Setting up your trial...
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
    type: 'trial',
    totalSteps: 4,
    prefix: 'pmpc',
    checkoutUrl: '<?= esc_js($checkout_url) ?>',
    ajaxUrl: '<?= esc_js(admin_url('admin-ajax.php')) ?>',
    restUrl: '<?= esc_js(rest_url('pi/v1')) ?>',
    nonce: '<?= wp_create_nonce('pi_checkout_nonce') ?>',
    restNonce: '<?= wp_create_nonce('wp_rest') ?>',
    isLoggedIn: <?= is_user_logged_in() ? 'true' : 'false' ?>,
    price: 0,
    minSelection: <?= intval(PMPC_TRIAL_MIN) ?>,
    maxSelection: <?= intval(PMPC_TRIAL_MAX) ?>,
    selectedCouncils: <?= json_encode(array_values($selected_councils)) ?>,
    templates: {},
    strings: {
        completeSubscription: 'Start Free Trial',
        perMonth: '/month',
        selectMinCouncils: 'Please select at least <?= intval(PMPC_TRIAL_MIN) ?> council to continue.',
        maxCouncils: 'Maximum <?= intval(PMPC_TRIAL_MAX) ?> councils during your free trial.'
    }
};
</script>
