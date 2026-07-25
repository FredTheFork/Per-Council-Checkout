<?php
/**
 * Custom checkout template for enterprise access checkout.
 * Loaded when pie_complete=1 is present in the URL query.
 * This template renders the PMPro checkout form with the enterprise
 * hidden fields already injected by PIE_PmproHooks::inject_hidden_fields().
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        <div class="pie-checkout-wrap">
            <?php
            if (function_exists('pmpro_wp')) {
                pmpro_wp();
            }
            ?>
        </div>
    </main>
</div>

<?php
get_footer();
