<?php
/**
 * Custom checkout template for regional bundles checkout.
 * Loaded when pirb_complete=1 is present in the URL query.
 * This template renders the PMPro checkout form with the regional bundles
 * hidden fields already injected by PIRB_PmproHooks::inject_hidden_fields().
 */

if (!defined('ABSPATH')) {
    exit;
}

get_header();
?>

<div id="primary" class="content-area">
    <main id="main" class="site-main">
        <div class="pirb-checkout-wrap">
            <?php
            // PMPro will render its checkout form here. The hidden fields
            // injected by PIRB_PmproHooks::inject_hidden_fields() will be
            // included in the form, carrying the selected region's councils,
            // price, and template data.
            if (function_exists('pmpro_wp')) {
                pmpro_wp();
            }
            ?>
        </div>
    </main>
</div>

<?php
get_footer();
