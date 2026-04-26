<?php
/**
 * Enqueue styles & scripts — conditional per page type.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'wp_enqueue_scripts', 'jimee_enqueue_assets' );
function jimee_enqueue_assets() {
    $v = JIMEE_VERSION;

    // Google Fonts — Poppins
    wp_enqueue_style( 'jimee-poppins', 'https://fonts.googleapis.com/css2?family=Poppins:ital,wght@0,300;0,400;0,500;0,600;0,700;1,400;1,600;1,700&display=swap', [], null );

    // Theme base
    wp_enqueue_style( 'jimee-base', get_stylesheet_uri(), [ 'jimee-poppins' ], $v );

    // Shared components (header, footer, cards, buttons, pills, modals)
    wp_enqueue_style( 'jimee-components', JIMEE_URI . '/assets/css/components.css', [ 'jimee-base' ], $v );

    // Header JS (sticky, mobile menu, cart drawer, search overlay)
    wp_enqueue_script( 'jimee-header', JIMEE_URI . '/assets/js/header.js', [], $v, true );

    // Wishlist JS (global — heart buttons on product cards everywhere)
    wp_enqueue_script( 'jimee-wishlist', JIMEE_URI . '/assets/js/wishlist.js', [], $v, true );

    // Homepage + pages that use homepage components (brands slider, etc.)
    if ( is_front_page() || is_page( 'marques' ) ) {
        wp_enqueue_style( 'jimee-homepage', JIMEE_URI . '/assets/css/homepage.css', [ 'jimee-components' ], $v );
    }
    if ( is_front_page() ) {
        wp_enqueue_script( 'jimee-homepage', JIMEE_URI . '/assets/js/homepage.js', [], $v, true );
    }

    // Product archives (categories + brands + shop) + search
    if ( is_product_category() || is_tax( 'product_brand' ) || is_tax( 'product_label' ) || is_shop() || is_search() ) {
        wp_enqueue_style( 'jimee-archive', JIMEE_URI . '/assets/css/archive.css', [ 'jimee-components' ], $v );
        wp_enqueue_script( 'jimee-archive', JIMEE_URI . '/assets/js/archive.js', [], $v, true );
        wp_localize_script( 'jimee-archive', 'jimeeArchive', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'jimee_load_products' ),
        ]);
    }

    // Single product
    if ( is_product() ) {
        wp_enqueue_style( 'jimee-product', JIMEE_URI . '/assets/css/product.css', [ 'jimee-components' ], $v );
        wp_enqueue_script( 'jimee-product', JIMEE_URI . '/assets/js/product.js', [], $v, true );
        wp_localize_script( 'jimee-product', 'jimeeProduct', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'wc_ajax'  => WC_AJAX::get_endpoint( '%%endpoint%%' ),
            'nonce'    => wp_create_nonce( 'jimee_add_to_cart' ),
        ]);
    }

    // WooCommerce pages (account, checkout, cart)
    if ( is_account_page() || is_checkout() || is_cart() || is_wc_endpoint_url() || is_order_received_page() ) {
        wp_enqueue_style( 'jimee-woocommerce', JIMEE_URI . '/assets/css/woocommerce.css', [ 'jimee-components' ], $v );
    }

    // Checkout-specific JS
    if ( is_checkout() && ! is_order_received_page() ) {
        wp_enqueue_script( 'jimee-checkout', JIMEE_URI . '/assets/js/checkout.js', [ 'jquery' ], $v, true );
        wp_localize_script( 'jimee-checkout', 'jimeeCheckout', [ 'ajax_url' => admin_url( 'admin-ajax.php' ), 'coupon_nonce' => wp_create_nonce( 'jimee_coupon' ) ] );
    }

    // Bon Plan page (needs archive grid + spinner styles)
    if ( is_page( 'le-bon-plan-jimee' ) ) {
        wp_enqueue_style( 'jimee-archive', JIMEE_URI . '/assets/css/archive.css', [ 'jimee-components' ], $v );
    }

    // Static pages (contact, legal, FAQ, about, wishlist, 404, marques)
    if ( is_page() || is_404() ) {
        wp_enqueue_style( 'jimee-pages', JIMEE_URI . '/assets/css/pages.css', [ 'jimee-components' ], $v );
    }
}

// Preconnect to Google Fonts for performance
add_action( 'wp_head', 'jimee_preconnect', 1 );
function jimee_preconnect() {
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
}
