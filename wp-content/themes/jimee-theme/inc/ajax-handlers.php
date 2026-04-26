<?php
/**
 * AJAX endpoints for product loading (infinite scroll, wishlist).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ─────────────────────────────────────────────
   Load products (archive infinite scroll)
   Supports both product_cat and product_brand taxonomies.
   ───────────────────────────────────────────── */
add_action( 'wp_ajax_jimee_load_products', 'jimee_load_products_ajax' );
add_action( 'wp_ajax_nopriv_jimee_load_products', 'jimee_load_products_ajax' );

function jimee_load_products_ajax() {
    check_ajax_referer( 'jimee_load_products', 'nonce' );

    $term_id  = intval( $_POST['term_id'] ?? 0 );
    $taxonomy = sanitize_text_field( $_POST['taxonomy'] ?? 'product_cat' );
    $offset   = intval( $_POST['offset'] ?? 0 );
    $per_page = intval( $_POST['per_page'] ?? 8 );
    $orderby  = sanitize_text_field( $_POST['orderby'] ?? 'default' );
    $filters  = array_map( 'intval', $_POST['filters'] ?? [] );
    $min_p    = floatval( $_POST['min_price'] ?? 0 );
    $max_p    = floatval( $_POST['max_price'] ?? 0 );

    // Validate taxonomy
    if ( $taxonomy && ! in_array( $taxonomy, [ 'product_cat', 'product_brand', 'product_label' ], true ) ) {
        $taxonomy = 'product_cat';
    }

    $args = [
        'post_type'      => 'product',
        'posts_per_page' => $per_page,
        'offset'         => $offset,
        'post_status'    => 'publish',
        'meta_query' => [
            'stock_clause' => [ 'key' => '_stock_status' ],
        ],
        'orderby' => [ 'stock_clause' => 'ASC', 'date' => 'DESC' ],
    ];

    // Add tax_query only if we have a specific term (not shop page)
    if ( $term_id > 0 && $taxonomy ) {
        $args['tax_query'] = [
            'relation' => 'AND',
            [
                'taxonomy'         => $taxonomy,
                'field'            => 'term_id',
                'terms'            => $term_id,
                'include_children' => ( $taxonomy === 'product_cat' ),
            ],
        ];
    } else {
        $args['tax_query'] = [ 'relation' => 'AND' ];
    }

    // Cross-taxonomy filter (brands on category pages, categories on brand/label pages)
    if ( ! empty( $filters ) ) {
        $cross_map = [
            'product_cat'   => 'product_brand',
            'product_brand' => 'product_cat',
            'product_label' => 'product_brand',
        ];
        $filter_tax = $cross_map[ $taxonomy ] ?? 'product_brand';
        $args['tax_query'][] = [
            'taxonomy' => $filter_tax,
            'field'    => 'term_id',
            'terms'    => $filters,
        ];
    }

    // Labels filter (Bio, Vegan)
    $labels = array_map( 'sanitize_text_field', $_POST['labels'] ?? [] );
    if ( ! empty( $labels ) ) {
        $args['tax_query'][] = [
            'taxonomy' => 'product_label',
            'field'    => 'slug',
            'terms'    => $labels,
        ];
    }

    // Contenance size ranges filter (e.g. ["0-8","41-75"])
    $size_ranges_raw = sanitize_text_field( $_POST['size_ranges'] ?? '' );
    if ( $size_ranges_raw ) {
        $size_ranges = json_decode( $size_ranges_raw, true );
        if ( ! empty( $size_ranges ) && is_array( $size_ranges ) ) {
            // Get all pa_contenance terms, extract numeric ml/g values, find matching term IDs
            $all_sizes = get_terms([ 'taxonomy' => 'pa_contenance', 'hide_empty' => true ]);
            $matching_term_ids = [];
            foreach ( $all_sizes as $size_term ) {
                // Extract numeric value from term name (e.g. "50 ml" → 50, "100 g" → 100)
                if ( preg_match( '/^([\d.]+)/', $size_term->name, $m ) ) {
                    $val = (float) $m[1];
                    foreach ( $size_ranges as $range ) {
                        $parts = explode( '-', $range );
                        if ( count( $parts ) === 2 && $val >= (float) $parts[0] && $val <= (float) $parts[1] ) {
                            $matching_term_ids[] = $size_term->term_id;
                            break;
                        }
                    }
                }
            }
            if ( ! empty( $matching_term_ids ) ) {
                $args['tax_query'][] = [
                    'taxonomy' => 'pa_contenance',
                    'field'    => 'term_id',
                    'terms'    => $matching_term_ids,
                ];
            }
        }
    }

    // Sort
    switch ( $orderby ) {
        case 'price-asc':
            $args['meta_query']['price_clause'] = [ 'key' => '_price', 'type' => 'NUMERIC' ];
            $args['orderby'] = [ 'stock_clause' => 'ASC', 'price_clause' => 'ASC' ];
            break;
        case 'price-desc':
            $args['meta_query']['price_clause'] = [ 'key' => '_price', 'type' => 'NUMERIC' ];
            $args['orderby'] = [ 'stock_clause' => 'ASC', 'price_clause' => 'DESC' ];
            break;
        case 'date':
            $args['orderby'] = [ 'stock_clause' => 'ASC', 'date' => 'DESC' ];
            break;
        case 'popularity':
            $args['meta_query']['sales_clause'] = [ 'key' => 'total_sales', 'type' => 'NUMERIC' ];
            $args['orderby'] = [ 'stock_clause' => 'ASC', 'sales_clause' => 'DESC' ];
            break;
    }

    $query = new WP_Query( $args );
    $html  = '';

    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $html .= jimee_render_product_card( get_the_ID() );
        }
        wp_reset_postdata();
    }

    wp_send_json_success([
        'html'     => $html,
        'has_more' => ( $offset + $per_page ) < $query->found_posts,
        'total'    => $query->found_posts,
    ]);
}

/* ─────────────────────────────────────────────
   Load Bon Plan products (infinite scroll)
   ───────────────────────────────────────────── */
add_action( 'wp_ajax_jimee_load_bonplan', 'jimee_load_bonplan_ajax' );
add_action( 'wp_ajax_nopriv_jimee_load_bonplan', 'jimee_load_bonplan_ajax' );

function jimee_load_bonplan_ajax() {
    check_ajax_referer( 'jimee_bonplan', 'nonce' );

    $offset   = intval( $_POST['offset'] ?? 0 );
    $per_page = intval( $_POST['per_page'] ?? 12 );

    $on_sale_ids = wc_get_product_ids_on_sale();
    if ( empty( $on_sale_ids ) ) {
        wp_send_json_success([ 'html' => '', 'has_more' => false, 'total' => 0 ]);
    }

    $query = new WP_Query([
        'post_type'      => 'product',
        'posts_per_page' => $per_page,
        'offset'         => $offset,
        'post_status'    => 'publish',
        'post__in'       => $on_sale_ids,
        'meta_query'     => [
            'stock_clause' => [ 'key' => '_stock_status' ],
        ],
        'orderby' => [ 'stock_clause' => 'ASC', 'date' => 'DESC' ],
    ]);

    $html = '';
    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $html .= jimee_render_product_card( get_the_ID() );
        }
        wp_reset_postdata();
    }

    wp_send_json_success([
        'html'     => $html,
        'has_more' => ( $offset + $per_page ) < $query->found_posts,
        'total'    => $query->found_posts,
    ]);
}

/* ─────────────────────────────────────────────
   Search load more (AJAX)
   ───────────────────────────────────────────── */
add_action( 'wp_ajax_jimee_search_load_more', 'jimee_search_load_more' );
add_action( 'wp_ajax_nopriv_jimee_search_load_more', 'jimee_search_load_more' );

function jimee_search_load_more() {
    check_ajax_referer( 'jimee_search', 'nonce' );
    $s      = sanitize_text_field( $_POST['s'] ?? '' );
    $offset = absint( $_POST['offset'] ?? 0 );

    $query = new WP_Query([
        'post_type'      => 'product',
        'posts_per_page' => 24,
        'offset'         => $offset,
        'post_status'    => 'publish',
        's'              => $s,
        'meta_query'     => [
            'stock_clause' => [ 'key' => '_stock_status' ],
        ],
        'orderby' => [ 'stock_clause' => 'ASC', 'relevance' => 'DESC' ],
    ]);

    $html = '';
    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $html .= jimee_render_product_card( get_the_ID() );
        }
        wp_reset_postdata();
    }

    wp_send_json_success([ 'html' => $html ]);
}

/* ─────────────────────────────────────────────
   Live search (AJAX dropdown under search bar)
   ───────────────────────────────────────────── */
add_action( 'wp_ajax_jimee_live_search', 'jimee_live_search_ajax' );
add_action( 'wp_ajax_nopriv_jimee_live_search', 'jimee_live_search_ajax' );

function jimee_live_search_ajax() {
    $q = sanitize_text_field( $_POST['q'] ?? '' );
    if ( strlen( $q ) < 2 ) {
        wp_send_json_success([ 'html' => '', 'count' => 0 ]);
        return;
    }

    $query = new WP_Query([
        'post_type'      => 'product',
        'posts_per_page' => 4,
        'post_status'    => 'publish',
        's'              => $q,
        'meta_query'     => [
            [ 'key' => '_stock_status', 'value' => 'instock' ],
        ],
    ]);

    $html = '';
    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $product = wc_get_product( get_the_ID() );
            if ( ! $product ) continue;
            $img = get_the_post_thumbnail_url( get_the_ID(), 'thumbnail' ) ?: wc_placeholder_img_src( 'thumbnail' );
            $brand_terms = wp_get_post_terms( get_the_ID(), 'product_brand' );
            $brand = ! empty( $brand_terms ) ? $brand_terms[0]->name : '';
            $price = (float) $product->get_price();

            $html .= '<a href="' . esc_url( get_permalink() ) . '" class="search-result-item">';
            $html .= '<img src="' . esc_url( $img ) . '" alt="' . esc_attr( get_the_title() ) . '" width="48" height="48">';
            $html .= '<div class="search-result-info">';
            if ( $brand ) $html .= '<span class="search-result-brand">' . esc_html( strtoupper( $brand ) ) . '</span>';
            $html .= '<span class="search-result-name">' . esc_html( get_the_title() ) . '</span>';
            $html .= '</div>';
            $html .= '<span class="search-result-price">' . number_format( $price, 0, ',', ' ' ) . ' DA</span>';
            $html .= '</a>';
        }
        wp_reset_postdata();
    }

    wp_send_json_success([
        'html'  => $html,
        'count' => $query->found_posts,
        'url'   => home_url( '/?s=' . urlencode( $q ) . '&post_type=product' ),
    ]);
}

/* ─────────────────────────────────────────────
   Get wishlist products (from localStorage IDs)
   ───────────────────────────────────────────── */
add_action( 'wp_ajax_jimee_get_wishlist', 'jimee_get_wishlist_ajax' );
add_action( 'wp_ajax_nopriv_jimee_get_wishlist', 'jimee_get_wishlist_ajax' );

function jimee_get_wishlist_ajax() {
    $ids = array_map( 'intval', $_POST['product_ids'] ?? [] );
    $ids = array_filter( $ids );

    if ( empty( $ids ) ) {
        wp_send_json_success([ 'html' => '', 'count' => 0 ]);
        return;
    }

    $query = new WP_Query([
        'post_type'      => 'product',
        'post__in'       => $ids,
        'posts_per_page' => count( $ids ),
        'orderby'        => 'post__in',
        'post_status'    => 'publish',
    ]);

    $html = '';
    while ( $query->have_posts() ) {
        $query->the_post();
        $html .= jimee_render_product_card( get_the_ID() );
    }
    wp_reset_postdata();

    wp_send_json_success([
        'html'  => $html,
        'count' => $query->found_posts,
    ]);
}

/* ============================================================
   SIDE CART — Update quantity
   ============================================================ */
add_action( 'wp_ajax_jimee_update_cart_qty', 'jimee_update_cart_qty' );
add_action( 'wp_ajax_nopriv_jimee_update_cart_qty', 'jimee_update_cart_qty' );
function jimee_update_cart_qty() {
    $key = sanitize_text_field( $_POST['cart_item_key'] ?? '' );
    $qty = absint( $_POST['quantity'] ?? 0 );

    if ( ! $key || ! WC()->cart->get_cart_item( $key ) ) {
        wp_send_json_error( 'Item introuvable.' );
    }

    if ( $qty < 1 ) {
        WC()->cart->remove_cart_item( $key );
    } else {
        WC()->cart->set_quantity( $key, $qty );
    }

    WC()->cart->calculate_totals();

    ob_start();
    if ( function_exists( 'jimee_render_side_cart_content' ) ) {
        jimee_render_side_cart_content();
    }
    $cart_html = ob_get_clean();

    wp_send_json_success( [
        'cart_html'  => $cart_html,
        'count'      => WC()->cart->get_cart_contents_count(),
        'total'      => WC()->cart->get_cart_total(),
    ] );
}

/* ============================================================
   CONTACT FORM
   ============================================================ */

add_action( 'wp_ajax_jimee_contact', 'jimee_contact_handler' );
add_action( 'wp_ajax_nopriv_jimee_contact', 'jimee_contact_handler' );
function jimee_contact_handler() {
    check_ajax_referer( 'jimee_contact', 'security' );

    $name    = sanitize_text_field( $_POST['name'] ?? '' );
    $email   = sanitize_email( $_POST['email'] ?? '' );
    $phone   = sanitize_text_field( $_POST['phone'] ?? '' );
    $subject = sanitize_text_field( $_POST['subject'] ?? '' );
    $message = sanitize_textarea_field( $_POST['message'] ?? '' );

    if ( empty( $name ) || empty( $email ) || empty( $message ) ) {
        wp_send_json_error( 'Veuillez remplir tous les champs obligatoires.' );
    }
    if ( ! is_email( $email ) ) {
        wp_send_json_error( 'Adresse email invalide.' );
    }

    $subjects = [
        'commande'    => 'Question sur une commande',
        'produit'     => 'Renseignement produit',
        'retour'      => 'Retour / échange',
        'partenariat' => 'Partenariat',
        'autre'       => 'Autre',
    ];
    $subject_label = $subjects[ $subject ] ?? $subject;

    $to           = get_option( 'admin_email', 'contact@jimeecosmetics.com' );
    $mail_subject = 'Nouveau message contact — ' . $subject_label;
    $date         = date_i18n( 'j F Y à H:i' );
    $logo_url     = get_template_directory_uri() . '/assets/img/logo-jimee-cosmetics-noir.png';
    $site_url     = home_url( '/' );

    $phone_row = '';
    if ( $phone ) {
        $phone_row = '<tr><td style="padding:12px 16px;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#999;width:120px;vertical-align:top">Téléphone</td><td style="padding:12px 16px;font-size:14px;color:#000">' . esc_html( $phone ) . '</td></tr>';
    }

    $body = '<!DOCTYPE html><html><head><meta charset="utf-8"></head><body style="margin:0;padding:0;background:#F8F6F3;font-family:Poppins,Helvetica,Arial,sans-serif">
    <div style="max-width:560px;margin:0 auto;padding:32px 16px">

        <!-- Logo -->
        <div style="text-align:center;margin-bottom:24px">
            <a href="' . esc_url( $site_url ) . '"><img src="' . esc_url( $logo_url ) . '" alt="Jimee Cosmetics" style="height:36px;width:auto"></a>
        </div>

        <!-- Card -->
        <div style="background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.06)">

            <!-- Header -->
            <div style="background:#000;padding:24px 28px">
                <h1 style="margin:0;font-size:18px;font-weight:300;color:#fff;font-family:Poppins,Helvetica,Arial,sans-serif">Nouveau message <strong>contact</strong></h1>
                <p style="margin:6px 0 0;font-size:12px;color:rgba(255,255,255,.5)">' . esc_html( $date ) . '</p>
            </div>

            <!-- Infos -->
            <table style="width:100%;border-collapse:collapse;margin:0">
                <tr style="border-bottom:1px solid #f0ede8">
                    <td style="padding:12px 16px;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#999;width:120px;vertical-align:top">Nom</td>
                    <td style="padding:12px 16px;font-size:14px;color:#000;font-weight:500">' . esc_html( $name ) . '</td>
                </tr>
                <tr style="border-bottom:1px solid #f0ede8">
                    <td style="padding:12px 16px;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#999;width:120px;vertical-align:top">Email</td>
                    <td style="padding:12px 16px;font-size:14px;color:#000"><a href="mailto:' . esc_attr( $email ) . '" style="color:#000;text-decoration:none">' . esc_html( $email ) . '</a></td>
                </tr>
                ' . $phone_row . '
                <tr style="border-bottom:1px solid #f0ede8">
                    <td style="padding:12px 16px;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#999;width:120px;vertical-align:top">Sujet</td>
                    <td style="padding:12px 16px;font-size:14px;color:#000">' . esc_html( $subject_label ) . '</td>
                </tr>
            </table>

            <!-- Message -->
            <div style="padding:20px 28px 28px">
                <div style="font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;color:#999;margin-bottom:10px">Message</div>
                <div style="background:#F8F6F3;border-radius:12px;padding:20px;font-size:14px;color:#333;line-height:1.8">' . nl2br( esc_html( $message ) ) . '</div>
            </div>

            <!-- CTA -->
            <div style="padding:0 28px 28px;text-align:center">
                <a href="mailto:' . esc_attr( $email ) . '" style="display:inline-block;padding:12px 32px;background:#000;color:#fff;border-radius:999px;font-size:13px;font-weight:600;text-decoration:none;font-family:Poppins,Helvetica,Arial,sans-serif">Répondre à ' . esc_html( $name ) . '</a>
            </div>
        </div>

        <!-- Footer -->
        <div style="text-align:center;padding:24px 0;font-size:11px;color:#999">
            Jimee Cosmetics — Kouba, Alger
        </div>
    </div>
    </body></html>';

    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: Jimee Cosmetics <contact@jimeecosmetics.com>',
        'Reply-To: ' . $name . ' <' . $email . '>',
    ];

    wp_mail( $to, $mail_subject, $body, $headers );
    wp_send_json_success( 'Message envoyé avec succès.' );
}
