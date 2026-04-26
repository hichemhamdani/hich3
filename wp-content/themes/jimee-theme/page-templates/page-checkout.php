<?php
/**
 * Template Name: Commande
 * Custom checkout page with Jimee hero + WooCommerce checkout.
 */

get_header();
?>

<div class="checkout-hero">
    <div class="checkout-hero-inner">
        <div class="checkout-hero-text">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="28" height="28">
                <path d="M9 11V6a3 3 0 116 0v5"/>
                <rect x="3" y="11" width="18" height="11" rx="2"/>
                <line x1="12" y1="15" x2="12" y2="18"/>
            </svg>
            <h1>Finaliser ma <em>Commande</em></h1>
        </div>
        <a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="checkout-hero-link">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="16" height="16"><polyline points="15 18 9 12 15 6"/></svg>
            Retour au panier
        </a>
    </div>
</div>

<div class="checkout-page-wrapper">
    <?php echo do_shortcode( '[woocommerce_checkout]' ); ?>
</div>

<?php get_footer(); ?>
