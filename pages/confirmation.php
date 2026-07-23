<?php
/**
 * Template: Confirmation Page
 * Planning Index Post-Checkout Confirmation
 * Version: 4.0.0 - Unified Design System
 *
 * Features: Confetti animation, print-friendly receipt, social proof,
 * "What happens next?" timeline, Open Graph meta tags
 */

if (!defined('ABSPATH')) exit;

if (function_exists('do_blocks')) {
    echo do_blocks('<!-- wp:template-part {"slug":"header","theme":"' . get_stylesheet() . '"} /-->');
}

global $current_user, $wpdb;

// Get order/subscription info
$pmpro_level = null;
$subscription_id = '';
$order_date = '';
$amount = '';
$plan_name = '';

if (function_exists('pmpro_getMembershipLevelForUser')) {
    $pmpro_level = pmpro_getMembershipLevelForUser(get_current_user_id());
}

if ($pmpro_level) {
    $plan_name = $pmpro_level->name;
}

// Try to get the most recent order
if (function_exists('pmpro_getLastMemberOrder')) {
    $order = pmpro_getLastMemberOrder(get_current_user_id());
    if ($order && is_object($order)) {
        $subscription_id = $order->code;
        $order_date = date('j F Y', strtotime($order->timestamp));
        $amount = $order->total ? pmpro_formatPrice($order->total) : '';
    }
}

if (empty($order_date)) {
    $order_date = date('j F Y');
}

// Get selected councils if available
$selected_councils = [];
if (is_user_logged_in()) {
    $stored = get_user_meta(get_current_user_id(), 'pmpc_selected_councils', true);
    if (is_array($stored)) {
        $selected_councils = $stored;
    }
}

// Determine checkout type
$checkout_type = 'per-council';
if ($pmpro_level) {
    if (strpos($pmpro_level->name, 'Trial') !== false || strpos($pmpro_level->name, 'trial') !== false) {
        $checkout_type = 'trial';
    } elseif (strpos($pmpro_level->name, 'Enterprise') !== false || strpos($pmpro_level->name, 'enterprise') !== false) {
        $checkout_type = 'enterprise';
    } elseif (strpos($pmpro_level->name, 'Regional') !== false || strpos($pmpro_level->name, 'regional') !== false) {
        $checkout_type = 'regional';
    }
}

// Enqueue assets
if (function_exists('pi_checkout_core_asset_url')) {
    wp_enqueue_style('pi-checkout-tokens', pi_checkout_core_asset_url('pi-checkout-tokens.min.css'), [], '1.0.0');
    wp_enqueue_style('pi-confirmation', pi_checkout_core_asset_url('pi-confirmation.min.css'), ['pi-checkout-tokens'], '1.0.0');
    wp_enqueue_script('pi-confirmation', pi_checkout_core_asset_url('pi-confirmation.min.js'), [], '1.0.0', true);
} else {
    wp_enqueue_style('pi-checkout-tokens', get_stylesheet_directory_uri() . '/assets/pi-checkout-tokens.css', [], '2.0.0');
    wp_enqueue_style('pi-confirmation', get_stylesheet_directory_uri() . '/assets/pi-confirmation.css', ['pi-checkout-tokens'], '2.0.0');
}
wp_enqueue_script('pi-confirmation', get_stylesheet_directory_uri() . '/assets/pi-confirmation.js', ['jquery'], '2.0.0', true);

// Open Graph meta tags
$og_title = 'I just joined Planning Index!';
$og_description = 'Access planning applications from councils across the UK. Join me and thousands of planning professionals.';
$og_url = home_url();
$og_image = get_stylesheet_directory_uri() . '/assets/og-image.png';
?>
<!-- Open Graph Meta Tags -->
<meta property="og:title" content="<?= esc_attr($og_title) ?>" />
<meta property="og:description" content="<?= esc_attr($og_description) ?>" />
<meta property="og:url" content="<?= esc_url($og_url) ?>" />
<meta property="og:image" content="<?= esc_url($og_image) ?>" />
<meta property="og:type" content="website" />
<meta property="og:site_name" content="Planning Index" />
<meta name="twitter:card" content="summary_large_image" />
<meta name="twitter:title" content="<?= esc_attr($og_title) ?>" />
<meta name="twitter:description" content="<?= esc_attr($og_description) ?>" />
<meta name="twitter:image" content="<?= esc_url($og_image) ?>" />

<!-- Confetti Canvas -->
<canvas id="pi-confetti-canvas" aria-hidden="true"></canvas>

<a href="#pi-main-content" class="pi-skip-link">Skip to main content</a>

<div class="pi-checkout-body">
<div class="pi-confirmation-page">
    <div class="pi-confirmation-content">

        <!-- ===== Success Hero ===== -->
        <div class="pi-confirmation-hero">
            <div class="pi-confirmation-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                    <polyline points="20 6 9 17 4 12" />
                </svg>
            </div>
            <h1>Welcome to Planning Index!</h1>
            <p>Your subscription is active. You can now access planning applications from councils across the UK.</p>
        </div>

        <main id="pi-main-content">

        <!-- ===== Receipt Card ===== -->
        <div class="pi-receipt-card">
            <div class="pi-receipt-header">
                <h2>Order Confirmation</h2>
                <p>Thank you for your purchase, <?= esc_html($current_user->display_name ?: $current_user->user_login ?: 'Valued Customer') ?>!</p>
            </div>
            <div class="pi-receipt-body">
                <div class="pi-receipt-row">
                    <span class="pi-receipt-row-label">Order Number:</span>
                    <span class="pi-receipt-row-value"><?= esc_html($subscription_id ?: 'N/A') ?></span>
                </div>
                <div class="pi-receipt-row">
                    <span class="pi-receipt-row-label">Date:</span>
                    <span class="pi-receipt-row-value"><?= esc_html($order_date) ?></span>
                </div>
                <div class="pi-receipt-row">
                    <span class="pi-receipt-row-label">Plan:</span>
                    <span class="pi-receipt-row-value"><?= esc_html($plan_name ?: 'Planning Index Subscription') ?></span>
                </div>
                <?php if (!empty($selected_councils)): ?>
                <div class="pi-receipt-row">
                    <span class="pi-receipt-row-label">Councils:</span>
                    <span class="pi-receipt-row-value"><?= count($selected_councils) ?> council<?= count($selected_councils) !== 1 ? 's' : '' ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($amount) && $checkout_type !== 'trial'): ?>
                <div class="pi-receipt-row">
                    <span class="pi-receipt-row-label">Billing Cycle:</span>
                    <span class="pi-receipt-row-value">Monthly</span>
                </div>
                <div class="pi-receipt-total">
                    <span>Total:</span>
                    <span class="pi-receipt-total-amount"><?= esc_html($amount) ?></span>
                </div>
                <?php elseif ($checkout_type === 'trial'): ?>
                <div class="pi-receipt-total">
                    <span>Total:</span>
                    <span class="pi-receipt-total-amount">Free Trial</span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ===== Social Proof ===== -->
        <div class="pi-social-proof-section" role="region" aria-label="Community">
            <div class="pi-social-proof-number" data-target="5000">0</div>
            <div class="pi-social-proof-text">Planning professionals trust Planning Index for their daily workflow</div>
        </div>

        <!-- ===== What Happens Next Timeline ===== -->
        <div class="pi-next-steps">
            <h2>What Happens Next?</h2>
            <div class="pi-timeline">
                <div class="pi-timeline-item">
                    <div class="pi-timeline-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <div class="pi-timeline-content">
                        <strong>Access Your Dashboard</strong>
                        <p>Log in to your Planning Index dashboard to see your selected councils and planning applications.</p>
                        <span class="pi-timeline-time">Available now</span>
                    </div>
                </div>
                <div class="pi-timeline-item">
                    <div class="pi-timeline-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    </div>
                    <div class="pi-timeline-content">
                        <strong>Customise Your Templates</strong>
                        <p>Set up your proposal letter templates with your business information and branding.</p>
                        <span class="pi-timeline-time">Available now</span>
                    </div>
                </div>
                <div class="pi-timeline-item">
                    <div class="pi-timeline-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    </div>
                    <div class="pi-timeline-content">
                        <strong>Start Receiving Applications</strong>
                        <p>New planning applications from your selected councils will appear in your dashboard automatically.</p>
                        <span class="pi-timeline-time">Within 24 hours</span>
                    </div>
                </div>
                <div class="pi-timeline-item">
                    <div class="pi-timeline-icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    </div>
                    <div class="pi-timeline-content">
                        <strong>Email Notifications</strong>
                        <p>You'll receive email alerts when new applications are published in your selected councils.</p>
                        <span class="pi-timeline-time">Within 24 hours</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ===== Action Buttons ===== -->
        <div class="pi-confirmation-actions">
            <a href="<?= esc_url(home_url('/dashboard/')) ?>" class="pi-btn pi-btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
                Go to Dashboard
            </a>
            <button type="button" id="pi_print_receipt" class="pi-print-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Print Receipt
            </button>
        </div>

        </main>

    </div>
</div>
</div>
