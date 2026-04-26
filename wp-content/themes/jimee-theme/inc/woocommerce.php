<?php
/**
 * WooCommerce hooks & customizations.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Disable Yoast breadcrumb JSON-LD (we output our own via jimee_breadcrumb_jsonld)
add_filter( 'wpseo_schema_graph_pieces', function( $pieces ) {
    return array_filter( $pieces, function( $piece ) {
        return ! ( $piece instanceof \Yoast\WP\SEO\Generators\Schema\Breadcrumb );
    });
}, 99 );

// Noindex WooCommerce utility pages (checkout, cart, account)
add_filter( 'wpseo_robots', 'jimee_noindex_wc_pages' );
function jimee_noindex_wc_pages( $robots ) {
    if ( is_checkout() || is_cart() || is_account_page() ) {
        return 'noindex, nofollow';
    }
    return $robots;
}

// Add custom classes to all WC form fields
add_filter( 'woocommerce_form_field_args', 'jimee_wc_form_field_args', 10, 3 );
function jimee_wc_form_field_args( $args, $key, $value ) {
    $args['input_class'][] = 'jimee-input';
    $args['label_class'][] = 'jimee-label';
    return $args;
}

// French labels for account navigation
add_filter( 'woocommerce_account_menu_items', 'jimee_account_menu_items' );
function jimee_account_menu_items( $items ) {
    return [
        'dashboard'       => 'Tableau de bord',
        'orders'          => 'Mes commandes',
        'edit-address'    => 'Mes adresses',
        'edit-account'    => 'Mon compte',
        'customer-logout' => 'Déconnexion',
    ];
}

// Newsletter subscription — sends email to admin
add_action( 'wp_ajax_jimee_newsletter', 'jimee_newsletter_handler' );
add_action( 'wp_ajax_nopriv_jimee_newsletter', 'jimee_newsletter_handler' );
function jimee_newsletter_handler() {
    $email = sanitize_email( $_POST['email'] ?? '' );
    if ( ! is_email( $email ) ) {
        wp_send_json_error( 'Email invalide' );
    }
    $to = get_option( 'admin_email', 'contact@jimeecosmetics.com' );
    $subject = 'Nouvelle inscription newsletter — Jimee Cosmetics';
    $body = "Nouvelle inscription newsletter :\n\nEmail : $email\nDate : " . date_i18n( 'j F Y à H:i' ) . "\n";
    $headers = [ 'From: Jimee Cosmetics <contact@jimeecosmetics.com>' ];
    wp_mail( $to, $subject, $body, $headers );
    wp_send_json_success( 'Inscrit' );
}

// Remove cross-sells from cart page (we show them on product page instead)
remove_action( 'woocommerce_cart_collaterals', 'woocommerce_cross_sell_display' );

// French checkout button text
add_filter( 'woocommerce_order_button_text', function() {
    return 'Confirmer la commande';
});

// Add icon class suffix to account nav items
add_filter( 'woocommerce_account_menu_item_classes', 'jimee_account_nav_classes', 10, 2 );
function jimee_account_nav_classes( $classes, $endpoint ) {
    $classes[] = 'jimee-nav-' . $endpoint;
    return $classes;
}

// Force our templates over WooCommerce defaults
add_filter( 'template_include', 'jimee_force_custom_templates', 999 );
function jimee_force_custom_templates( $template ) {
    // Search
    if ( is_search() ) {
        $custom = get_template_directory() . '/search.php';
        if ( file_exists( $custom ) ) return $custom;
    }
    // Shop page (Boutique)
    if ( is_shop() ) {
        $custom = get_template_directory() . '/page-shop.php';
        if ( file_exists( $custom ) ) return $custom;
    }
    // Brand taxonomy archive — check multiple ways
    if ( is_tax( 'product_brand' ) ) {
        $custom = get_template_directory() . '/taxonomy-product_brand.php';
        if ( file_exists( $custom ) ) return $custom;
    }
    // Also catch via queried object
    $obj = get_queried_object();
    if ( $obj && isset( $obj->taxonomy ) && $obj->taxonomy === 'product_brand' ) {
        $custom = get_template_directory() . '/taxonomy-product_brand.php';
        if ( file_exists( $custom ) ) return $custom;
    }
    // Product label archive (Bio, Vegan, etc.)
    if ( is_tax( 'product_label' ) ) {
        $custom = get_template_directory() . '/taxonomy-product_label.php';
        if ( file_exists( $custom ) ) return $custom;
    }
    // Product category archive
    if ( is_product_category() ) {
        $custom = get_template_directory() . '/taxonomy-product_cat.php';
        if ( file_exists( $custom ) ) return $custom;
    }
    return $template;
}

// Unregister product_brand from WC product taxonomies so WC_Template_Loader ignores it
add_filter( 'woocommerce_get_product_taxonomies', 'jimee_remove_brand_from_wc_taxonomies' );
function jimee_remove_brand_from_wc_taxonomies( $taxonomies ) {
    return array_diff( $taxonomies, [ 'product_brand' ] );
}

// Nuclear: override template at priority 99999 — AFTER everything else
add_filter( 'template_include', 'jimee_absolute_brand_override', 99999 );
function jimee_absolute_brand_override( $template ) {
    if ( is_tax( 'product_brand' ) ) {
        return get_template_directory() . '/taxonomy-product_brand.php';
    }
    return $template;
}

// Remove WooCommerce default CSS (we handle all styling)
add_filter( 'woocommerce_enqueue_styles', 'jimee_dequeue_wc_styles' );
function jimee_dequeue_wc_styles( $styles ) {
    unset( $styles['woocommerce-layout'] );
    unset( $styles['woocommerce-general'] );
    unset( $styles['woocommerce-smallscreen'] );
    return $styles;
}

// Remove WC Blocks CSS that forces blue borders on notices
add_action( 'wp_print_styles', 'jimee_dequeue_wc_blocks_css', 999 );
add_action( 'wp_enqueue_scripts', 'jimee_dequeue_wc_blocks_css', 999 );
function jimee_dequeue_wc_blocks_css() {
    wp_dequeue_style( 'wc-blocks-style-store-notices' );
    wp_dequeue_style( 'wc-blocks-style' );
    wp_dequeue_style( 'wc-blocks-packages-style' );
}
// Nuclear: block store-notices CSS from HTML output entirely
add_filter( 'style_loader_tag', 'jimee_block_wc_notices_css', 10, 2 );
function jimee_block_wc_notices_css( $tag, $handle ) {
    if ( in_array( $handle, [ 'wc-blocks-style-store-notices', 'wc-blocks-style', 'wc-blocks-packages-style' ], true ) ) {
        return '';
    }
    return $tag;
}
// Also inject nuclear override inline at wp_head — loads after everything
add_action( 'wp_head', 'jimee_notice_override_inline', 999 );
function jimee_notice_override_inline() {
    echo '<style id="jimee-notice-fix">.wc-block-components-notice-banner,.wc-block-components-notice-banner.is-error,.wc-block-components-notice-banner.is-success,.wc-block-components-notice-banner.is-info,.wc-block-components-notice-banner.is-warning{background:var(--warm-bg,#F8F6F3)!important;border:1.5px solid #e8e5e0!important;border-radius:16px!important;color:#555!important;box-shadow:none!important;outline:none!important;max-width:600px;font-family:Poppins,sans-serif!important;font-size:13px!important;padding:14px 24px!important}.wc-block-components-notice-banner>svg,.wc-block-components-notice-banner>.wc-block-components-button{display:none!important}.wc-block-components-notice-banner:focus-visible{outline:none!important}</style>' . "\n";
}

// Price format: "1 500 DA" (space thousands, 0 decimals)
add_filter( 'woocommerce_currency_symbol', function( $symbol, $currency ) {
    return $currency === 'DZD' ? 'DA' : $symbol;
}, 10, 2 );

// "Retour à la boutique" → redirect to homepage + French label
add_filter( 'woocommerce_return_to_shop_redirect', function() {
    return home_url( '/' );
});
add_filter( 'woocommerce_return_to_shop_text', function() {
    return 'Continuer mes achats';
});

// Shorten coupon label in cart totals ("Code promo : xxx" → "Réduction")
add_filter( 'woocommerce_cart_totals_coupon_label', function( $label, $coupon ) {
    return 'Réduction';
}, 10, 2 );

// Breadcrumb separator (SVG chevron)
add_filter( 'woocommerce_breadcrumb_defaults', function( $defaults ) {
    $defaults['delimiter'] = ' <svg style="width:14px;height:14px;vertical-align:middle;margin:0 6px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg> ';
    return $defaults;
});

// Register side cart + header count as WC cart fragments
add_filter( 'woocommerce_add_to_cart_fragments', 'jimee_cart_fragments' );
function jimee_cart_fragments( $fragments ) {
    // Side cart inner content
    ob_start();
    jimee_render_side_cart_content();
    $fragments['#cartContent'] = ob_get_clean();

    // Cart item count in side cart header
    $count = WC()->cart->get_cart_contents_count();
    $fragments['#cartItemCount'] = '<span id="cartItemCount">' . $count . '</span>';

    // Cart count badge in header
    $fragments['#cartCount'] = '<span class="cart-count" id="cartCount">' . $count . '</span>';

    return $fragments;
}

/**
 * Render side cart inner HTML (reusable by fragment + template).
 */
function jimee_render_side_cart_content() {
    if ( ! WC()->cart || WC()->cart->is_empty() ) : ?>
        <div id="cartContent">
            <div class="cart-empty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                <p>Votre panier est vide</p>
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Continuer mes achats</a>
            </div>
        </div>
    <?php else : ?>
        <div id="cartContent">
            <div class="cart-items">
                <?php foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) :
                    $product = $cart_item['data'];
                    $img_url = get_the_post_thumbnail_url( $cart_item['product_id'], 'thumbnail' );
                    if ( ! $img_url ) $img_url = wc_placeholder_img_src( 'thumbnail' );
                    $brand_terms = wp_get_post_terms( $cart_item['product_id'], 'product_brand' );
                    $brand = ! empty( $brand_terms ) ? $brand_terms[0]->name : '';
                    $remove_url = wc_get_cart_remove_url( $cart_item_key );
                ?>
                <div class="cart-item" data-key="<?php echo esc_attr( $cart_item_key ); ?>">
                    <div class="cart-item-img">
                        <img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $product->get_name() ); ?>" width="80" height="80">
                    </div>
                    <div class="cart-item-info">
                        <?php if ( $brand ) : ?><div class="cart-item-brand"><?php echo esc_html( strtoupper( $brand ) ); ?></div><?php endif; ?>
                        <div class="cart-item-name"><?php echo esc_html( $product->get_name() ); ?></div>
                        <div class="cart-item-price"><?php echo number_format( (float) $product->get_price(), 0, ',', ' ' ); ?> DA</div>
                        <div class="cart-item-bottom">
                            <div class="qty-selector">
                                <button onclick="jimeeCartQty('<?php echo esc_attr( $cart_item_key ); ?>', -1)">&#8722;</button>
                                <span><?php echo $cart_item['quantity']; ?></span>
                                <button onclick="jimeeCartQty('<?php echo esc_attr( $cart_item_key ); ?>', 1)">+</button>
                            </div>
                            <a href="<?php echo esc_url( $remove_url ); ?>" class="cart-item-remove" onclick="event.preventDefault();jimeeCartRemove('<?php echo esc_attr( $cart_item_key ); ?>', this)">Supprimer</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="cart-footer">
                <div class="cart-total">
                    <span>Total</span>
                    <span><?php echo WC()->cart->get_cart_total(); ?></span>
                </div>
                <a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="cart-checkout">Passer commande</a>
                <button class="cart-continue" onclick="window.toggleCart()">Continuer mes achats</button>
                <a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="cart-view-full">Voir mon panier</a>
            </div>
        </div>
    <?php endif;
}

/* ============================================================
   CHECKOUT — Algeria-specific field customization
   ============================================================ */

/**
 * 58 wilayas d'Algérie (code officiel)
 */
function jimee_get_wilayas() {
    return [
        '01' => '01 - Adrar',
        '02' => '02 - Chlef',
        '03' => '03 - Laghouat',
        '04' => '04 - Oum El Bouaghi',
        '05' => '05 - Batna',
        '06' => '06 - Béjaïa',
        '07' => '07 - Biskra',
        '08' => '08 - Béchar',
        '09' => '09 - Blida',
        '10' => '10 - Bouira',
        '11' => '11 - Tamanrasset',
        '12' => '12 - Tébessa',
        '13' => '13 - Tlemcen',
        '14' => '14 - Tiaret',
        '15' => '15 - Tizi Ouzou',
        '16' => '16 - Alger',
        '17' => '17 - Djelfa',
        '18' => '18 - Jijel',
        '19' => '19 - Sétif',
        '20' => '20 - Saïda',
        '21' => '21 - Skikda',
        '22' => '22 - Sidi Bel Abbès',
        '23' => '23 - Annaba',
        '24' => '24 - Guelma',
        '25' => '25 - Constantine',
        '26' => '26 - Médéa',
        '27' => '27 - Mostaganem',
        '28' => '28 - M\'Sila',
        '29' => '29 - Mascara',
        '30' => '30 - Ouargla',
        '31' => '31 - Oran',
        '32' => '32 - El Bayadh',
        '33' => '33 - Illizi',
        '34' => '34 - Bordj Bou Arréridj',
        '35' => '35 - Boumerdès',
        '36' => '36 - El Tarf',
        '37' => '37 - Tindouf',
        '38' => '38 - Tissemsilt',
        '39' => '39 - El Oued',
        '40' => '40 - Khenchela',
        '41' => '41 - Souk Ahras',
        '42' => '42 - Tipaza',
        '43' => '43 - Mila',
        '44' => '44 - Aïn Defla',
        '45' => '45 - Naâma',
        '46' => '46 - Aïn Témouchent',
        '47' => '47 - Ghardaïa',
        '48' => '48 - Relizane',
        '49' => '49 - El M\'Ghair',
        '50' => '50 - El Meniaa',
        '51' => '51 - Ouled Djellal',
        '52' => '52 - Bordj Badji Mokhtar',
        '53' => '53 - Béni Abbès',
        '54' => '54 - Timimoun',
        '55' => '55 - Touggourt',
        '56' => '56 - Djanet',
        '57' => '57 - In Salah',
        '58' => '58 - In Guezzam',
    ];
}

/**
 * Override billing fields: reorder, add wilaya dropdown, phone required, email optional
 */
add_filter( 'woocommerce_checkout_fields', 'jimee_checkout_fields' );
function jimee_checkout_fields( $fields ) {

    // ── Remove unwanted fields ──
    unset( $fields['billing']['billing_company'] );
    unset( $fields['billing']['billing_country'] );
    unset( $fields['billing']['billing_address_2'] );

    // ── Coordonnées ──
    $fields['billing']['billing_first_name']['priority'] = 10;
    $fields['billing']['billing_first_name']['label']    = 'Prénom';
    $fields['billing']['billing_first_name']['placeholder'] = 'Votre prénom';

    $fields['billing']['billing_last_name']['priority'] = 20;
    $fields['billing']['billing_last_name']['label']    = 'Nom';
    $fields['billing']['billing_last_name']['placeholder'] = 'Votre nom';

    $fields['billing']['billing_phone']['priority']    = 30;
    $fields['billing']['billing_phone']['label']       = 'Téléphone';
    $fields['billing']['billing_phone']['placeholder'] = '0X XX XX XX XX';
    $fields['billing']['billing_phone']['required']    = true;
    $fields['billing']['billing_phone']['class']       = ['form-row-wide'];

    $fields['billing']['billing_email']['priority']    = 40;
    $fields['billing']['billing_email']['label']       = 'Email';
    $fields['billing']['billing_email']['placeholder'] = 'exemple@email.com (optionnel)';
    $fields['billing']['billing_email']['required']    = false;
    $fields['billing']['billing_email']['class']       = ['form-row-wide'];

    // ── Adresse ──
    $fields['billing']['billing_address_1']['priority']    = 50;
    $fields['billing']['billing_address_1']['label']       = 'Adresse';
    $fields['billing']['billing_address_1']['placeholder'] = 'Numéro et nom de rue';
    $fields['billing']['billing_address_1']['required']    = false;

    // Wilaya — Bordereau manages the DZ-XX state codes and city select
    // We only set label/priority, let Bordereau handle options
    if ( isset( $fields['billing']['billing_state'] ) ) {
        $fields['billing']['billing_state']['label']    = 'Wilaya';
        $fields['billing']['billing_state']['priority'] = 60;
        $fields['billing']['billing_state']['class']    = ['form-row-wide'];
    }

    if ( isset( $fields['billing']['billing_city'] ) ) {
        $fields['billing']['billing_city']['priority'] = 70;
        $fields['billing']['billing_city']['label']    = 'Commune';
    }

    // Supprimer code postal (inutile en Algérie)
    unset( $fields['billing']['billing_postcode'] );

    // Notes
    if ( isset( $fields['order']['order_comments'] ) ) {
        $fields['order']['order_comments']['label']       = 'Notes de livraison';
        $fields['order']['order_comments']['placeholder'] = 'Instructions spéciales, étage, digicode... (optionnel)';
    }

    return $fields;
}

// Force country to Algeria
add_filter( 'default_checkout_billing_country', function() { return 'DZ'; } );
add_filter( 'default_checkout_shipping_country', function() { return 'DZ'; } );
add_filter( 'woocommerce_countries_allowed_countries', function() {
    return [ 'DZ' => 'Algérie' ];
});

// Force DZ country in posted checkout data (billing_country field is hidden)
add_filter( 'woocommerce_checkout_posted_data', function( $data ) {
    $data['billing_country']  = 'DZ';
    $data['shipping_country'] = 'DZ';
    return $data;
});

// Clean Bordereau state labels: keep DZ-XX keys but use "01 - Adrar" format (no Arabic)
add_filter( 'woocommerce_states', function( $states ) {
    if ( ! isset( $states['DZ'] ) ) return $states;
    $clean = jimee_get_wilayas(); // '01' => '01 - Adrar', etc.
    $cleaned = [];
    foreach ( $states['DZ'] as $key => $label ) {
        // DZ-01 → 01
        $num = str_replace( 'DZ-', '', $key );
        $cleaned[ $key ] = isset( $clean[ $num ] ) ? $clean[ $num ] : $label;
    }
    $states['DZ'] = $cleaned;
    return $states;
}, 99999 );

// Override DZ locale so WC JS uses our labels instead of defaults
add_filter( 'woocommerce_get_country_locale', function( $locale ) {
    $locale['DZ'] = [
        'state' => [
            'label'    => 'Wilaya',
            'required' => true,
        ],
        'city' => [
            'label'       => 'Commune',
            'placeholder' => 'Votre commune',
            'required'    => true,
        ],
        'address_1' => [
            'label'       => 'Adresse',
            'placeholder' => 'Numéro et nom de rue',
            'required'    => true,
        ],
        'postcode' => [
            'hidden'   => true,
            'required' => false,
        ],
    ];
    return $locale;
});

// Fix city label after Bordereau plugin overrides (priority 99999 > Bordereau's 9999)
add_filter( 'woocommerce_checkout_fields', function( $fields ) {
    if ( isset( $fields['billing']['billing_city'] ) ) {
        $fields['billing']['billing_city']['label']       = 'Commune';
        $fields['billing']['billing_city']['placeholder'] = 'Sélectionner une commune';
    }
    return $fields;
}, 99999 );

// Translate Bordereau plugin strings to French
add_filter( 'gettext', function( $translated, $text, $domain ) {
    if ( $domain === 'woo-bordereau-generator' ) {
        $map = [
            'Select a City'    => 'Sélectionner une commune',
            'Select City'      => 'Sélectionner une commune',
            'Select a state'   => 'Sélectionner une wilaya',
            'Select agency'    => 'Sélectionner une agence',
            'Select pickup point' => 'Sélectionner un point relais',
            'Bureau de livraison' => 'Bureau de livraison',
            'Address'          => 'Adresse',
        ];
        if ( isset( $map[ $text ] ) ) return $map[ $text ];
    }
    return $translated;
}, 10, 3 );

// Hide WooCommerce default login/coupon notices at top — we handle them inline
// Also remove "additional information" from shipping section (order_comments already in section 2)
// Remove default order details table from thankyou (we render our own)
add_action( 'init', function() {
    remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_login_form', 10 );
    remove_action( 'woocommerce_before_checkout_form', 'woocommerce_checkout_coupon_form', 10 );
    remove_action( 'woocommerce_checkout_shipping', 'woocommerce_checkout_payment', 20 );
    remove_action( 'woocommerce_thankyou', 'woocommerce_order_details_table', 10 );
});
// Remove "Informations complémentaires" section from shipping (we render order_comments in section 2)
add_action( 'woocommerce_checkout_init', function() {
    remove_action( 'woocommerce_checkout_after_customer_details', 'woocommerce_checkout_additional_information_fields', 10 );
});

// Deduplicate WC notices (prevents same message appearing twice)
add_filter( 'woocommerce_notice_types', function( $types ) { return $types; } );
add_action( 'woocommerce_before_checkout_form', function() {
    $notices = WC()->session->get( 'wc_notices', [] );
    foreach ( $notices as $type => &$msgs ) {
        $seen = [];
        $msgs = array_filter( $msgs, function( $n ) use ( &$seen ) {
            $key = is_array( $n ) ? $n['notice'] : $n;
            if ( isset( $seen[ $key ] ) ) return false;
            $seen[ $key ] = true;
            return true;
        });
    }
    WC()->session->set( 'wc_notices', $notices );
}, 1 );

// Recap totals HTML (reusable for initial render + fragment)
function jimee_recap_totals_html() {
    $html  = '<div class="jimee-review-totals">';
    $html .= '<div class="jimee-review-row"><span>Sous-total</span><span>' . WC()->cart->get_cart_subtotal() . '</span></div>';
    if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) {
        $html .= '<div class="jimee-review-row"><span>Livraison</span><span>' . WC()->cart->get_cart_shipping_total() . '</span></div>';
    }
    foreach ( WC()->cart->get_coupons() as $code => $coupon ) {
        $html .= '<div class="jimee-review-row jimee-review-discount"><span>';
        $html .= '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="14" height="14"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg> ';
        $html .= esc_html( strtoupper( $code ) );
        $html .= ' <a href="' . esc_url( add_query_arg( 'remove_coupon', rawurlencode( $code ), wc_get_checkout_url() ) ) . '" class="jimee-coupon-remove" title="Retirer">&times;</a>';
        $html .= '</span><span>-' . wc_price( WC()->cart->get_coupon_discount_amount( $code ) ) . '</span></div>';
    }
    $html .= '</div>';
    $html .= '<div class="jimee-review-total"><span>Total</span><span>' . WC()->cart->get_total() . '</span></div>';
    return $html;
}

// Register fragments for section 3 shipping + recap totals (updates via AJAX)
// Sync chosen shipping method from POST before fragments are generated
add_action( 'woocommerce_checkout_update_order_review', function( $post_data ) {
    parse_str( $post_data, $data );
    if ( ! empty( $data['shipping_method'] ) && is_array( $data['shipping_method'] ) ) {
        WC()->session->set( 'chosen_shipping_methods', array_map( 'sanitize_text_field', $data['shipping_method'] ) );
    }
}, 5 );

add_filter( 'woocommerce_update_order_review_fragments', function( $fragments ) {
    // Section 3: shipping methods
    ob_start();
    if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) {
        wc_cart_totals_shipping_html();
    } else {
        echo '<p class="jimee-shipping-note">Sélectionnez votre wilaya pour voir les tarifs</p>';
    }
    $fragments['#jimee-shipping-methods'] = '<div id="jimee-shipping-methods">' . ob_get_clean() . '</div>';

    // Recap: totals (subtotal, shipping, coupons, total)
    $fragments['#jimee-recap-totals'] = '<div id="jimee-recap-totals">' . jimee_recap_totals_html() . '</div>';

    // Mobile CTA total
    $fragments['.jimee-mobile-cta-total strong'] = '<strong>' . WC()->cart->get_total() . '</strong>';

    // Mobile toggle total
    $fragments['.jimee-review-toggle-total'] = '<span class="jimee-review-toggle-total">' . WC()->cart->get_total() . '</span>';

    return $fragments;
});

// Allow checkout without email (WC normally requires it)
add_filter( 'woocommerce_checkout_required_field_names', function( $fields ) {
    return array_diff( $fields, ['billing_email'] );
});

/* ── AJAX coupon handler for inline checkout form ── */
add_action( 'wp_ajax_jimee_apply_coupon', 'jimee_apply_coupon_handler' );
add_action( 'wp_ajax_nopriv_jimee_apply_coupon', 'jimee_apply_coupon_handler' );
function jimee_apply_coupon_handler() {
    check_ajax_referer( 'jimee_coupon', 'security' );
    $code = sanitize_text_field( $_POST['coupon_code'] ?? '' );
    if ( empty( $code ) ) {
        wp_send_json_error( 'Veuillez entrer un code promo.' );
    }
    $result = WC()->cart->apply_coupon( $code );
    if ( $result ) {
        wp_send_json_success();
    } else {
        $errors = wc_get_notices( 'error' );
        $msg = ! empty( $errors ) ? wp_strip_all_tags( $errors[0]['notice'] ?? 'Code promo invalide.' ) : 'Code promo invalide.';
        wc_clear_notices();
        wp_send_json_error( $msg );
    }
}

/* ── Email Branding ── */

// Custom email styles
add_filter( 'woocommerce_email_styles', function( $css ) {
    $css .= '
    /* Jimee Email Branding */
    #wrapper { max-width: 600px; margin: 0 auto; }

    #template_header_image { padding: 32px 0 0; text-align: center; }
    #template_header_image img { max-width: 180px !important; height: auto !important; }

    #template_header { background: #000 !important; border-radius: 12px 12px 0 0; }
    #header_wrapper { padding: 24px 48px !important; }
    #template_header h1 {
        color: #fff !important; font-family: "Poppins", Helvetica, Arial, sans-serif !important;
        font-size: 22px !important; font-weight: 300 !important; letter-spacing: 0 !important;
        line-height: 1.4 !important; margin: 0 !important; text-align: left !important;
    }

    #template_container { border: none !important; border-radius: 12px !important; overflow: hidden; box-shadow: 0 2px 20px rgba(0,0,0,.06); }
    #template_body { background: #fff !important; }
    #body_content { padding: 0 !important; }
    #body_content_inner { font-family: "Poppins", Helvetica, Arial, sans-serif !important; font-size: 14px !important; color: #333 !important; line-height: 1.7 !important; }
    #body_content_inner p { margin: 0 0 14px !important; }
    #body_content_inner h2 { color: #000 !important; font-family: "Poppins", Helvetica, Arial, sans-serif !important; font-size: 18px !important; font-weight: 600 !important; border-bottom: 2px solid #000 !important; padding-bottom: 10px !important; margin: 28px 0 16px !important; }
    #body_content_inner h3 { color: #000 !important; font-family: "Poppins", Helvetica, Arial, sans-serif !important; font-size: 16px !important; font-weight: 600 !important; }

    /* Remove double border on table wrappers */
    #body_content_inner > div { border: none !important; padding: 0 !important; }
    #body_content_inner table { border: none !important; border-collapse: separate !important; border-spacing: 0 !important; border-radius: 10px !important; overflow: hidden !important; }

    /* Order table */
    .td { padding: 14px 18px !important; font-family: "Poppins", Helvetica, Arial, sans-serif !important; font-size: 13px !important; border: none !important; border-bottom: 1px solid rgba(255,255,255,.1) !important; white-space: nowrap !important; }
    thead .td { background: #222 !important; font-weight: 600 !important; font-size: 11px !important; text-transform: uppercase !important; letter-spacing: 1px !important; color: #aaa !important; }
    thead .td:first-child { white-space: normal !important; width: 60% !important; }
    tbody .td { background: #2a2a2a !important; color: #ddd !important; }
    tbody .td:first-child { white-space: normal !important; }
    tfoot .td { background: #1a1a1a !important; color: #ccc !important; font-size: 13px !important; border-bottom: 1px solid rgba(255,255,255,.06) !important; }
    tfoot tr:last-child .td { font-size: 16px !important; font-weight: 700 !important; color: #fff !important; border-bottom: none !important; }

    /* Addresses */
    .address { font-family: "Poppins", Helvetica, Arial, sans-serif !important; font-size: 13px !important; line-height: 1.8 !important; color: #555 !important; background: #f8f6f3 !important; padding: 16px 20px !important; border-radius: 10px !important; }

    /* Footer */
    #template_footer { background: transparent !important; }
    #template_footer #credit { padding: 24px 0 !important; text-align: center !important; }
    #template_footer #credit p { font-family: "Poppins", Helvetica, Arial, sans-serif !important; font-size: 11px !important; color: #999 !important; line-height: 1.8 !important; margin: 0 !important; }

    /* Links */
    a { color: #000 !important; font-weight: 500; }
    ';
    return $css;
});

// Translate email strings
add_filter( 'woocommerce_email_heading_processing_order', function() { return 'Merci pour votre commande'; } );

// Remove brackets from order heading in emails: [Order #%s] → Commande n°%s
add_filter( 'gettext', function( $translated, $text, $domain ) {
    if ( $domain === 'woocommerce' ) {
        if ( $text === '[Order #%s]' ) return 'Commande n°%s';
        if ( strpos( $translated, '[Commande' ) !== false ) return str_replace( ['[', ']'], '', $translated );
    }
    return $translated;
}, 5, 3 );

// Remove "Payez en cash à la livraison" from emails (COD gateway instructions)
add_action( 'init', function() {
    $gateways = WC()->payment_gateways();
    if ( isset( $gateways->payment_gateways['cod'] ) ) {
        remove_action( 'woocommerce_email_before_order_table', [ $gateways->payment_gateways['cod'], 'email_instructions' ], 10 );
    }
}, 99 );
add_filter( 'gettext', function( $translated, $text, $domain ) {
    if ( $domain !== 'woocommerce' ) return $translated;
    $map = [
        'Billing address'  => 'Adresse de livraison',
        'Shipping address' => 'Adresse de livraison',
    ];
    return $map[ $text ] ?? $translated;
}, 20, 3 );

// Remove "Payez en cash" and "Merci d'utiliser..." from emails
add_filter( 'woocommerce_email_footer_text', function( $text ) {
    return str_replace( '{site_title}', get_bloginfo( 'name' ), $text );
});
add_filter( 'woocommerce_cod_process_payment_order_status', function() { return 'processing'; } );
add_action( 'woocommerce_email_customer_details', function( $order, $sent_to_admin, $plain_text, $email ) {
    // Remove phone and email from billing address in emails
    remove_filter( 'woocommerce_order_formatted_billing_address', '__return_empty_array' );
}, 5, 4 );

// Hide phone/email from email billing address display
add_filter( 'woocommerce_order_formatted_billing_address', function( $address, $order ) {
    if ( doing_filter( 'woocommerce_email_customer_details' ) || did_action( 'woocommerce_email_customer_details' ) ) {
        unset( $address['phone'] );
        unset( $address['email'] );
    }
    return $address;
}, 20, 2 );

/* ============================================================
   BORDEREAU — Traduction statuts + tracking
   ============================================================ */

// Traduire les statuts Bordereau en français
add_filter( 'gettext', function( $translated, $text, $domain ) {
    if ( $domain !== 'woo-bordereau-generator' ) return $translated;
    $map = [
        'Dispatched'        => 'Expédié',
        'Awaiting shipping' => 'En attente d\'expédition',
    ];
    return $map[ $text ] ?? $translated;
}, 10, 3 );

// Aussi overrider les labels dans wc_get_order_statuses()
add_filter( 'wc_order_statuses', function( $statuses ) {
    if ( isset( $statuses['wc-dispatched'] ) )        $statuses['wc-dispatched']        = 'Expédié';
    if ( isset( $statuses['wc-awaiting-shipping'] ) )  $statuses['wc-awaiting-shipping']  = 'En attente d\'expédition';
    if ( isset( $statuses['wc-completed'] ) )          $statuses['wc-completed']          = 'Livré';
    return $statuses;
});
