<?php
/**
 * Helper functions: product card renderer, star rating, etc.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Output BreadcrumbList JSON-LD schema.
 *
 * @param array $items Array of [ 'name' => string, 'url' => string ].
 *                     Last item is the current page (no url needed).
 */
function jimee_breadcrumb_jsonld( $items ) {
    if ( empty( $items ) ) return;

    $list_items = [];
    foreach ( $items as $i => $item ) {
        $entry = [
            '@type'    => 'ListItem',
            'position' => $i + 1,
            'name'     => $item['name'],
        ];
        if ( ! empty( $item['url'] ) ) {
            $entry['item'] = $item['url'];
        }
        $list_items[] = $entry;
    }

    $schema = [
        '@context'        => 'https://schema.org',
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $list_items,
    ];

    echo '<script type="application/ld+json">' . "\n";
    echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
    echo "\n" . '</script>' . "\n";
}

/**
 * Categories to exclude from menus/sliders.
 * Non catégorisé (984) + Bébé et Maman (718, 3230) + Santé (3231).
 */
function jimee_excluded_cats() {
    return [ 984, 718, 3230, 3231 ];
}

/**
 * Get 1-2 letter initials from a brand name.
 * "L'ORÉAL" → "LO", "NYX" → "NY", "A-DERMA" → "AD"
 */
function jimee_brand_initials( $name ) {
    $clean = preg_replace( '/[^A-Za-zÀ-ÿ\s]/', ' ', $name );
    $words = array_filter( explode( ' ', trim( $clean ) ), function( $w ) { return mb_strlen( $w ) > 0; } );

    if ( empty( $words ) ) return mb_strtoupper( mb_substr( $name, 0, 2 ) );

    if ( count( $words ) === 1 ) {
        return mb_strtoupper( mb_substr( $words[0], 0, 2 ) );
    }

    return mb_strtoupper( mb_substr( $words[0], 0, 1 ) . mb_substr( $words[1], 0, 1 ) );
}

/**
 * Render a single product card.
 * Used in archives, search, wishlist, homepage grids.
 */
function jimee_render_product_card( $product_id ) {
    $product = wc_get_product( $product_id );
    if ( ! $product ) return '';

    $brand_terms = wp_get_post_terms( $product_id, 'product_brand' );
    $brand       = ! empty( $brand_terms ) ? strtoupper( $brand_terms[0]->name ) : '';

    $img_url = get_the_post_thumbnail_url( $product_id, 'woocommerce_thumbnail' );
    if ( ! $img_url ) $img_url = wc_placeholder_img_src( 'woocommerce_thumbnail' );

    // Build srcset/sizes for responsive images
    $thumb_id   = get_post_thumbnail_id( $product_id );
    $img_srcset = $thumb_id ? wp_get_attachment_image_srcset( $thumb_id, 'woocommerce_thumbnail' ) : '';
    $img_sizes  = $thumb_id ? '(max-width: 767px) 50vw, (max-width: 1199px) 33vw, 25vw' : '';

    $in_stock  = $product->is_in_stock();
    $on_sale   = $product->is_on_sale();
    $featured  = $product->is_featured();
    $is_new    = ( time() - get_post_time( 'U', false, $product_id ) ) < 30 * DAY_IN_SECONDS;

    // Tag priority: promo > best-seller > new
    $tag = '';
    $tag_class = '';
    if ( $on_sale )      { $tag = 'Promo';       $tag_class = 'promo'; }
    elseif ( $featured ) { $tag = 'Best-seller';  $tag_class = 'best'; }
    elseif ( $is_new )   { $tag = 'Nouveau';      $tag_class = 'new'; }

    $price    = (float) $product->get_price();
    $reg      = (float) $product->get_regular_price();
    $sale     = (float) $product->get_sale_price();
    $rating   = (float) $product->get_average_rating();
    $reviews  = (int)   $product->get_review_count();
    $url      = get_permalink( $product_id );
    $title    = get_the_title( $product_id );

    $oos_class = $in_stock ? '' : ' out-of-stock';

    ob_start();
    ?>
    <a href="<?php echo esc_url( $url ); ?>" class="product-card<?php echo $oos_class; ?>">
        <div class="product-card-image">
            <img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $title ); ?>" loading="lazy" width="400" height="400"<?php if ( $img_srcset ) : ?> srcset="<?php echo esc_attr( $img_srcset ); ?>" sizes="<?php echo esc_attr( $img_sizes ); ?>"<?php endif; ?>>
            <?php if ( $tag ) : ?>
                <span class="product-tag <?php echo $tag_class; ?>"><?php echo $tag; ?></span>
            <?php endif; ?>
            <button class="wishlist-btn" data-product-id="<?php echo esc_attr( $product_id ); ?>" aria-label="Ajouter aux favoris">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
            </button>
            <?php if ( $in_stock ) : ?>
                <button class="cart-btn" data-add-to-cart="<?php echo esc_attr( $product_id ); ?>" aria-label="Ajouter au panier">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
                    <span class="cart-btn-plus">+</span>
                </button>
            <?php else : ?>
                <div class="out-of-stock-badge">Rupture de stock</div>
            <?php endif; ?>
        </div>
        <div class="product-info">
            <div class="product-brand"><?php echo esc_html( $brand ); ?></div>
            <div class="product-name"><?php echo esc_html( $title ); ?></div>
            <?php if ( $rating > 0 ) : ?>
            <div class="product-rating">
                <span class="stars"><?php echo jimee_render_stars( $rating ); ?></span>
                <span class="rating-count">(<?php echo $reviews; ?>)</span>
            </div>
            <?php endif; ?>
            <div class="product-price">
                <?php if ( $on_sale && $sale > 0 ) : ?>
                    <span class="price-current"><?php echo number_format( $sale, 0, ',', ' ' ); ?> DA</span>
                    <span class="price-old"><?php echo number_format( $reg, 0, ',', ' ' ); ?> DA</span>
                    <?php if ( $reg > 0 ) : ?>
                        <span class="price-badge">-<?php echo round( ( 1 - $sale / $reg ) * 100 ); ?>%</span>
                    <?php endif; ?>
                <?php else : ?>
                    <span class="price-current"><?php echo number_format( $price, 0, ',', ' ' ); ?> DA</span>
                <?php endif; ?>
            </div>
        </div>
    </a>
    <?php
    return ob_get_clean();
}

/**
 * Render star rating as text (★☆).
 */
function jimee_render_stars( $rating ) {
    $out = '';
    for ( $i = 0; $i < 5; $i++ ) {
        $out .= ( $i < round( $rating ) ) ? '★' : '☆';
    }
    return $out;
}

/**
 * Product benefits: full SVG icons keyed by label.
 * Clean Lucide-style line icons, stroke 1.5.
 */
function jimee_benefit_icons() {
    return [
        'Hydratant'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/></svg>',
        'Nourrissant'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 20A7 7 0 0 1 9.8 6.9C15.5 4.9 17 3.5 17 3.5s0 6-4.3 11.2"/><path d="M6.7 13.5c.8-1 2-2.2 3.8-3.5"/></svg>',
        'Anti-âge'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
        'Protection UV' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/></svg>',
        'Apaisant'      => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
        'Éclat'         => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
        'Purifiant'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/></svg>',
        'Réparateur'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>',
        'Fortifiant'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>',
        'Lissant'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>',
        'Volume'        => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20V10"/><path d="M18 20V4"/><path d="M6 20v-4"/></svg>',
        'Anti-taches'   => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>',
        'Protecteur'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>',
        'Exfoliant'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>',
        'Parfumé'       => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2h8l4 10H4L8 2z"/><path d="M12 12v6"/><path d="M8 22h8"/><path d="M10 18h4"/></svg>',
        'Doux'          => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M18 10h-4V6"/><path d="M14 10l7.4-7.4"/><path d="M20 14c0 5.523-4.477 8-10 8S2 19.523 2 14 4.477 2 10 2"/></svg>',
    ];
}

/**
 * Extract product benefits automatically from title + description + category.
 * Returns max 4 benefit labels.
 */
function jimee_get_product_benefits( $product ) {
    $text = mb_strtolower(
        $product->get_name() . ' ' .
        $product->get_short_description() . ' ' .
        $product->get_description()
    );

    $keyword_map = [
        'Hydratant'     => 'hydrat|moistur|aqua|hydra',
        'Nourrissant'   => 'nourri|nutritif|nutrition|beurre|huile',
        'Anti-âge'      => 'anti.?[aâ]ge|anti.?rid|retinol|lift|fermet|collag[eè]n|raffer',
        'Protection UV' => 'spf|solaire|uva|uvb|sun|ecran',
        'Apaisant'      => 'apais|calm|sensib|douceur|comfort|sooth',
        'Éclat'         => '[eé]clat|lumin|bright|glow|radian|teint|illumin',
        'Purifiant'     => 'purifi|nettoy|clean|d[eé]toxif|pore|matifi|s[eé]bum',
        'Réparateur'    => 'r[eé]par|cicatris|regenera|restor|renouvel',
        'Fortifiant'    => 'anti.?chute|fortifi|renforc|croissan|pousse',
        'Lissant'       => 'liss|smooth|soyeu|brillan|d[eé]m[eê]l',
        'Volume'        => 'volume|volumis|volumineu|densit|densifi|[eé]paissi',
        'Anti-taches'   => 'anti.?imperf|acn[eé]|bouton|anti.?tache|pigment',
        'Protecteur'    => 'prot[eè]g|prot[eé]ct|barri[eè]re|d[eé]fens',
        'Exfoliant'     => 'exfoli|gommag|peeling|scrub|acide|aha|bha|glycoli',
        'Parfumé'       => 'parfum|fragran|eau de|cologne|senteur',
        'Doux'          => 'b[eé]b[eé]|enfant|doux|gentle|tout.?petit',
    ];

    $found = [];
    foreach ( $keyword_map as $label => $pattern ) {
        if ( preg_match( '/(' . $pattern . ')/iu', $text ) ) {
            $found[] = $label;
        }
        if ( count( $found ) >= 4 ) break;
    }

    // Enrich text with category names and re-scan
    if ( count( $found ) < 2 ) {
        $cat_terms = wp_get_post_terms( $product->get_id(), 'product_cat' );
        $all_slugs = [];
        foreach ( $cat_terms as $ct ) {
            $text .= ' ' . mb_strtolower( $ct->name );
            $all_slugs[] = $ct->slug;
            $pid = $ct->parent;
            while ( $pid > 0 ) {
                $p = get_term( $pid, 'product_cat' );
                if ( $p && ! is_wp_error( $p ) ) { $all_slugs[] = $p->slug; $pid = $p->parent; }
                else break;
            }
        }
        foreach ( $keyword_map as $label => $pattern ) {
            if ( in_array( $label, $found ) ) continue;
            if ( preg_match( '/(' . $pattern . ')/iu', $text ) ) {
                $found[] = $label;
            }
            if ( count( $found ) >= 4 ) break;
        }
        // Category-based defaults
        if ( count( $found ) < 2 ) {
            $cat_defaults = [
                'cheveux' => [ 'Nourrissant', 'Fortifiant' ], 'visage' => [ 'Hydratant', 'Éclat' ],
                'corps' => [ 'Hydratant', 'Nourrissant' ], 'maquillage' => [ 'Éclat', 'Protecteur' ],
                'solaire' => [ 'Protection UV', 'Hydratant' ], 'hygiene' => [ 'Purifiant', 'Doux' ],
                'homme' => [ 'Purifiant', 'Hydratant' ], 'k-beauty' => [ 'Hydratant', 'Éclat' ],
                'bebe' => [ 'Doux', 'Apaisant' ], 'sante' => [ 'Protecteur', 'Réparateur' ],
            ];
            foreach ( array_unique( $all_slugs ) as $slug ) {
                foreach ( $cat_defaults as $key => $labels ) {
                    if ( strpos( $slug, $key ) !== false ) {
                        foreach ( $labels as $lbl ) {
                            if ( ! in_array( $lbl, $found ) ) $found[] = $lbl;
                            if ( count( $found ) >= 4 ) break 3;
                        }
                    }
                }
            }
        }
    }

    return array_slice( $found, 0, 4 );
}

/**
 * Render benefits as icon circles with labels.
 */
function jimee_render_benefits( $product ) {
    $labels = jimee_get_product_benefits( $product );
    if ( empty( $labels ) ) return '';

    $icons = jimee_benefit_icons();
    $html = '<div class="pd-benefits">';
    foreach ( $labels as $label ) {
        $svg = isset( $icons[ $label ] ) ? $icons[ $label ] : '';
        $html .= '<div class="pd-benefit">';
        $html .= '<div class="pd-benefit-icon">' . $svg . '</div>';
        $html .= '<span class="pd-benefit-label">' . esc_html( $label ) . '</span>';
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}

/**
 * Auto-generate usage tips based on product type/category.
 * Returns HTML string or empty.
 */
function jimee_get_usage_tips( $product ) {
    $text = mb_strtolower( $product->get_name() . ' ' . $product->get_short_description() );
    $cat_terms = wp_get_post_terms( $product->get_id(), 'product_cat' );
    $all_slugs = [];
    foreach ( $cat_terms as $ct ) {
        $all_slugs[] = $ct->slug;
        $text .= ' ' . mb_strtolower( $ct->name );
        $pid = $ct->parent;
        while ( $pid > 0 ) {
            $p = get_term( $pid, 'product_cat' );
            if ( $p && ! is_wp_error( $p ) ) { $all_slugs[] = $p->slug; $pid = $p->parent; }
            else break;
        }
    }

    $tips = [];

    // Detect product type and generate tips
    if ( preg_match( '/(shampoo|shampoing|shampo)/iu', $text ) ) {
        $tips = [
            'Mouiller les cheveux abondamment.',
            'Appliquer une noisette de shampoing sur le cuir chevelu.',
            'Masser délicatement en mouvements circulaires.',
            'Rincer soigneusement. Renouveler si nécessaire.',
        ];
    } elseif ( preg_match( '/(apr[eè]s.?shamp|conditioner|baume|masque capill)/iu', $text ) ) {
        $tips = [
            'Après le shampoing, essorer légèrement les cheveux.',
            'Appliquer sur les longueurs et pointes en évitant les racines.',
            'Laisser poser 2 à 5 minutes (10 min pour un soin profond).',
            'Rincer abondamment à l\'eau tiède.',
        ];
    } elseif ( preg_match( '/(spray|brume).*cheveu/iu', $text ) || ( preg_match( '/(spray|brume)/iu', $text ) && in_array( 'cheveux', $all_slugs ) ) ) {
        $tips = [
            'Vaporiser sur cheveux humides ou secs, à 20 cm de distance.',
            'Répartir uniformément sur les longueurs.',
            'Ne pas rincer. Coiffer comme d\'habitude.',
        ];
    } elseif ( preg_match( '/(s[eé]rum|essence).*visage/iu', $text ) || ( preg_match( '/(s[eé]rum|essence)/iu', $text ) && in_array( 'visage', $all_slugs ) ) ) {
        $tips = [
            'Appliquer matin et/ou soir sur peau propre et sèche.',
            'Déposer quelques gouttes au creux de la main.',
            'Tapoter délicatement sur le visage et le cou.',
            'Suivre avec votre crème hydratante habituelle.',
        ];
    } elseif ( preg_match( '/(cr[eè]me|moistur|hydrat).*visage/iu', $text ) || ( preg_match( '/(cr[eè]me hydrat|soin visage|cr[eè]me visage)/iu', $text ) ) ) {
        $tips = [
            'Appliquer matin et/ou soir après le sérum.',
            'Prélever une noisette de crème.',
            'Appliquer sur le visage et le cou en mouvements ascendants.',
            'Masser jusqu\'à pénétration complète.',
        ];
    } elseif ( preg_match( '/(nettoy|mousse|gel.*lavant|micellaire|d[eé]maquill)/iu', $text ) ) {
        $tips = [
            'Utiliser matin et/ou soir sur peau humide.',
            'Appliquer une petite quantité et faire mousser.',
            'Masser délicatement le visage en évitant le contour des yeux.',
            'Rincer à l\'eau claire et sécher en tamponnant.',
        ];
    } elseif ( preg_match( '/(solaire|spf|sun|ecran)/iu', $text ) ) {
        $tips = [
            'Appliquer généreusement 20 minutes avant l\'exposition.',
            'Renouveler toutes les 2 heures et après chaque baignade.',
            'Éviter le contour des yeux.',
            'Ne pas s\'exposer aux heures les plus chaudes (12h-16h).',
        ];
    } elseif ( preg_match( '/(parfum|eau de|cologne)/iu', $text ) ) {
        $tips = [
            'Vaporiser sur les points de pulsation : poignets, cou, derrière les oreilles.',
            'Tenir le flacon à 15-20 cm de la peau.',
            'Ne pas frotter après application.',
            'Conserver à l\'abri de la lumière et de la chaleur.',
        ];
    } elseif ( preg_match( '/(mascara|eye.?liner|fard|ombre)/iu', $text ) ) {
        $tips = [
            'Appliquer sur paupières propres et sèches.',
            'Utiliser un pinceau adapté pour un résultat optimal.',
            'Estomper si nécessaire pour un fini naturel.',
            'Retirer avec un démaquillant doux en fin de journée.',
        ];
    } elseif ( preg_match( '/(rouge.*l[eè]vr|lip|gloss|baume.*l[eè]vr)/iu', $text ) ) {
        $tips = [
            'Appliquer directement sur les lèvres propres et hydratées.',
            'Partir du centre vers les commissures.',
            'Pour plus de précision, utiliser un pinceau à lèvres.',
            'Retoucher au besoin au cours de la journée.',
        ];
    }

    // Fallback by category
    if ( empty( $tips ) ) {
        if ( in_array( 'cheveux', $all_slugs ) ) {
            $tips = [ 'Appliquer sur les cheveux selon les indications du produit.', 'Adapter la fréquence à votre type de cheveux.' ];
        } elseif ( in_array( 'visage', $all_slugs ) ) {
            $tips = [ 'Appliquer sur peau propre matin et/ou soir.', 'Éviter le contour des yeux sauf indication contraire.' ];
        } elseif ( in_array( 'corps', $all_slugs ) ) {
            $tips = [ 'Appliquer sur le corps après la douche.', 'Masser jusqu\'à pénétration complète.' ];
        }
    }

    if ( empty( $tips ) ) return '';

    $html = '<ol class="usage-steps">';
    foreach ( $tips as $i => $tip ) {
        $html .= '<li><span class="usage-step-num">' . ( $i + 1 ) . '</span>' . esc_html( $tip ) . '</li>';
    }
    $html .= '</ol>';
    return $html;
}

/**
 * Label config: slug → display name, CSS class, icon file.
 */
function jimee_label_config() {
    return [
        'bio'            => [ 'name' => 'Bio',            'class' => 'label-bio',            'icon' => 'label-bio.png' ],
        'vegan'          => [ 'name' => 'Vegan',          'class' => 'label-vegan',          'icon' => 'label-vegan.png' ],
        'local'          => [ 'name' => 'Produit local',  'class' => 'label-local',          'icon' => 'label-produit-local.png' ],
        'sans-alcool'    => [ 'name' => 'Sans alcool',    'class' => 'label-sans-alcool',    'icon' => 'label-sans-alcool.png' ],
        'sans-parfum'    => [ 'name' => 'Sans parfum',    'class' => 'label-sans-parfum',    'icon' => 'label-sans-parfum.png' ],
        'sans-sulfates'  => [ 'name' => 'Sans sulfates',  'class' => 'label-sans-sulfates',  'icon' => 'label-sans-sulfate.png' ],
    ];
}

/**
 * Render small label badges (for product cards).
 * Shows icon-only circles on cards.
 */
function jimee_render_label_badges( $slugs ) {
    $config = jimee_label_config();
    $html = '';
    foreach ( $slugs as $slug ) {
        if ( ! isset( $config[ $slug ] ) ) continue;
        $c = $config[ $slug ];
        $icon_url = JIMEE_URI . '/assets/icons/' . $c['icon'] . '?v=' . JIMEE_VERSION;
        $html .= '<span class="product-label ' . esc_attr( $c['class'] ) . '" title="' . esc_attr( $c['name'] ) . '">';
        $html .= '<img src="' . esc_url( $icon_url ) . '" alt="' . esc_attr( $c['name'] ) . '" width="18" height="18">';
        $html .= '</span>';
    }
    return $html;
}

/**
 * Render label icons for single product page (larger, with text).
 */
function jimee_render_label_icons( $slugs ) {
    $config = jimee_label_config();
    $html = '<div class="pd-labels">';
    foreach ( $slugs as $slug ) {
        if ( ! isset( $config[ $slug ] ) ) continue;
        $c = $config[ $slug ];
        $icon_url = JIMEE_URI . '/assets/icons/' . $c['icon'] . '?v=' . JIMEE_VERSION;
        $html .= '<div class="pd-label ' . esc_attr( $c['class'] ) . '">';
        $html .= '<img src="' . esc_url( $icon_url ) . '" alt="' . esc_attr( $c['name'] ) . '" width="28" height="28">';
        $html .= '<span>' . esc_html( $c['name'] ) . '</span>';
        $html .= '</div>';
    }
    $html .= '</div>';
    return $html;
}
