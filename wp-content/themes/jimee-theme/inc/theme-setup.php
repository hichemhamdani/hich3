<?php
/**
 * Theme setup: supports, menus, image sizes.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'after_setup_theme', 'jimee_theme_setup' );
function jimee_theme_setup() {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', [ 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption' ] );
    add_theme_support( 'woocommerce' );
    // Custom gallery — disable WC gallery features (removes PhotoSwipe overlay)
    // add_theme_support( 'wc-product-gallery-zoom' );
    // add_theme_support( 'wc-product-gallery-lightbox' );
    // add_theme_support( 'wc-product-gallery-slider' );

    register_nav_menus([
        'primary'  => 'Menu principal',
        'mobile'   => 'Menu mobile',
        'footer'   => 'Menu footer',
    ]);

    add_image_size( 'jimee-card', 400, 400, true );
    add_image_size( 'jimee-hero', 1440, 720, true );
}

/**
 * Register custom taxonomies (previously managed by JetEngine).
 */
add_action( 'init', 'jimee_register_taxonomies' );
function jimee_register_taxonomies() {
    // product_brand is registered by WooCommerce — no need to register here.
    // We just hide the old marque_pharma from admin if it still exists.
    add_filter( 'woocommerce_taxonomy_args_product_brand', function( $args ) {
        $args['show_admin_column'] = true;
        $args['rewrite'] = [ 'slug' => 'brand', 'with_front' => false ];
        return $args;
    });

    // Labels produit (Bio, Vegan, etc.)
    register_taxonomy( 'product_label', 'product', [
        'labels' => [
            'name'          => 'Labels',
            'singular_name' => 'Label',
            'search_items'  => 'Rechercher un label',
            'all_items'     => 'Tous les labels',
            'edit_item'     => 'Modifier le label',
            'update_item'   => 'Mettre à jour le label',
            'add_new_item'  => 'Ajouter un label',
            'new_item_name' => 'Nom du nouveau label',
            'menu_name'     => 'Labels',
        ],
        'hierarchical'      => false,
        'public'            => true,
        'show_ui'           => true,
        'show_admin_column' => true,
        'show_in_rest'      => true,
        'query_var'         => true,
        'rewrite'           => [ 'slug' => 'label', 'with_front' => false ],
    ]);
}

/**
 * Create default product_label terms on theme activation.
 */
add_action( 'init', 'jimee_create_default_labels', 20 );
function jimee_create_default_labels() {
    $defaults = [
        'bio'         => 'Bio',
        'vegan'       => 'Vegan',
        'local'       => 'Produit local',
        'sans-alcool' => 'Sans alcool',
    ];
    foreach ( $defaults as $slug => $name ) {
        if ( ! term_exists( $slug, 'product_label' ) ) {
            wp_insert_term( $name, 'product_label', [ 'slug' => $slug ] );
        }
    }
}
