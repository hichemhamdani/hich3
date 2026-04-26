<?php
/**
 * Template Name: Panier
 * Custom cart page with Jimee hero + WooCommerce cart.
 */

get_header();
?>

<div class="cart-hero">
    <div class="cart-hero-inner">
        <div class="cart-hero-text">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="28" height="28">
                <path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/>
                <line x1="3" y1="6" x2="21" y2="6"/>
                <path d="M16 10a4 4 0 01-8 0"/>
            </svg>
            <h1>Mon <em>Panier</em></h1>
        </div>
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="cart-hero-link">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="16" height="16"><polyline points="15 18 9 12 15 6"/></svg>
            Continuer mes achats
        </a>
    </div>
</div>

<div class="cart-page-wrapper">
    <?php
    while ( have_posts() ) : the_post();
        the_content();
    endwhile;
    ?>
</div>

<?php get_footer(); ?>
