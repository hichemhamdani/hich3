<!-- MOBILE MENU OVERLAY -->
<div class="mobile-menu-overlay" id="mobileMenuOverlay"></div>
<div class="mobile-menu" id="mobileMenu">
    <div class="mobile-menu-header">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="logo"><img src="<?php echo esc_url( JIMEE_URI . '/assets/img/logo-jimee-cosmetics-noir.png' ); ?>" alt="Jimee Cosmetics" class="logo-img" width="58" height="24"></a>
        <button class="mobile-menu-close" id="mobileMenuClose" aria-label="Fermer le menu">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>

    <div class="mobile-search-wrapper">
        <div class="mobile-search">
            <form action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get" class="mobile-search-form">
                <input type="text" name="s" id="mobileSearchInput" placeholder="Rechercher..." autocomplete="off">
                <input type="hidden" name="post_type" value="product">
                <button type="submit" class="search-circle">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </button>
            </form>
        </div>
        <div class="mobile-search-results" id="mobileSearchResults" style="display:none"></div>
    </div>

    <!-- Quick access — story bubbles -->
    <div class="mobile-quick-access">
        <a href="<?php echo esc_url( get_term_link( 3229, 'product_cat' ) ); ?>" class="mobile-quick-bubble">
            <div class="mobile-quick-ring">
                <div class="mobile-quick-inner" style="background-image:url('<?php echo esc_url( JIMEE_URI . '/assets/img/caudalie-premier-cru-collection-anti-age.jpg' ); ?>')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5" rx="1"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg>
                </div>
            </div>
            <span>Coffrets</span>
        </a>
        <a href="<?php echo esc_url( home_url( '/le-bon-plan-jimee/' ) ); ?>" class="mobile-quick-bubble mobile-quick-bubble--promo">
            <div class="mobile-quick-ring">
                <div class="mobile-quick-inner" style="background-image:url('<?php echo esc_url( JIMEE_URI . '/assets/img/rare-beauty-collection-maquillage.jpg' ); ?>')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                </div>
            </div>
            <span>Bon Plan</span>
        </a>
        <a href="<?php echo esc_url( home_url( '/label/bio/' ) ); ?>" class="mobile-quick-bubble mobile-quick-bubble--bio">
            <div class="mobile-quick-ring">
                <div class="mobile-quick-inner" style="background-image:url('<?php echo esc_url( JIMEE_URI . '/assets/img/masque-visage-argile-soin-naturel.jpg' ); ?>')">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M11 20A7 7 0 0 1 9.8 6.9C15.5 4.9 17 3.5 17 3.5s0 6-4.3 11.2"/><path d="M6.7 13.5c.8-1 2-2.2 3.8-3.5"/></svg>
                </div>
            </div>
            <span>Bio</span>
        </a>
    </div>

    <nav class="mobile-nav" id="mobileNav">
        <?php
        $cat_icons = [
            'visage'       => '<circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><circle cx="9" cy="9" r="1" fill="currentColor"/><circle cx="15" cy="9" r="1" fill="currentColor"/>',
            'cheveux'      => '<path d="M12 2C6 2 4 7 4 10c0 4 2 6 2 10h12c0-4 2-6 2-10 0-3-2-8-8-8z"/>',
            'corps'        => '<path d="M12 2a4 4 0 0 0-4 4v2h8V6a4 4 0 0 0-4-4zM6 10v10a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V10H6z"/>',
            'maquillage'   => '<path d="M20 7l-8 4-8-4 8-4 8 4z"/><path d="M4 7v6l8 4 8-4V7"/>',
            'k-beauty'     => '<path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z"/>',
            'hygiene'      => '<path d="M12 22c4-4 8-7 8-12a8 8 0 1 0-16 0c0 5 4 8 8 12z"/>',
            'homme'        => '<circle cx="12" cy="7" r="5"/><path d="M3 21v-2a7 7 0 0 1 7-7h4a7 7 0 0 1 7 7v2"/>',
            'solaire'      => '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>',
            'coffrets-cadeaux' => '<polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5" rx="1"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>',
            'accesoires'   => '<circle cx="12" cy="12" r="10"/>',
        ];
        $default_icon = '<circle cx="12" cy="12" r="10"/>';
        $chevron_svg = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg>';

        $top_cats = get_terms([
            'taxonomy'   => 'product_cat',
            'parent'     => 0,
            'hide_empty' => true,
            'orderby'    => 'meta_value_num',
            'meta_key'   => 'order',
            'order'      => 'ASC',
            'exclude'    => jimee_excluded_cats(),
        ]);

        if ( ! is_wp_error( $top_cats ) ) :
            foreach ( $top_cats as $cat ) :
                if ( in_array( $cat->slug, [ 'uncategorized', 'non-classe', 'non-categorise' ], true ) ) continue;
                $icon = $cat_icons[ $cat->slug ] ?? $default_icon;
                $subcats = get_terms([ 'taxonomy' => 'product_cat', 'parent' => $cat->term_id, 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ]);
                $has_sub = ! is_wp_error( $subcats ) && ! empty( $subcats );
        ?>
        <div class="mobile-nav-item">
            <a href="<?php echo esc_url( get_term_link( $cat ) ); ?>" class="mobile-nav-link">
                <div class="mobile-nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><?php echo $icon; ?></svg>
                </div>
                <span class="mobile-nav-text"><?php echo esc_html( $cat->name ); ?></span>
            </a>
            <?php if ( $has_sub ) : ?>
            <button class="mobile-nav-chevron" data-submenu="sub-<?php echo esc_attr( $cat->slug ); ?>"><?php echo $chevron_svg; ?></button>
            <?php endif; ?>
        </div>
        <?php if ( $has_sub ) : ?>
        <div class="mobile-submenu" id="sub-<?php echo esc_attr( $cat->slug ); ?>">
            <button class="mobile-submenu-back" data-close="sub-<?php echo esc_attr( $cat->slug ); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                Retour
            </button>
            <a href="<?php echo esc_url( get_term_link( $cat ) ); ?>" class="mobile-submenu-title">Tout <?php echo esc_html( $cat->name ); ?></a>
            <?php foreach ( $subcats as $sc ) :
                $sub_subcats = get_terms([ 'taxonomy' => 'product_cat', 'parent' => $sc->term_id, 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ]);
                $has_sub_sub = ! is_wp_error( $sub_subcats ) && ! empty( $sub_subcats );
            ?>
            <?php if ( $has_sub_sub ) : ?>
            <div class="mobile-submenu-row">
                <a href="<?php echo esc_url( get_term_link( $sc ) ); ?>" class="mobile-submenu-item"><?php echo esc_html( $sc->name ); ?></a>
                <button class="mobile-submenu-chevron" data-submenu="sub-<?php echo esc_attr( $sc->slug ); ?>"><?php echo $chevron_svg; ?></button>
            </div>
            <?php else : ?>
            <a href="<?php echo esc_url( get_term_link( $sc ) ); ?>" class="mobile-submenu-item"><?php echo esc_html( $sc->name ); ?></a>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
        <?php // Level 3 submenus ?>
        <?php foreach ( $subcats as $sc ) :
            $sub_subcats = get_terms([ 'taxonomy' => 'product_cat', 'parent' => $sc->term_id, 'hide_empty' => true, 'orderby' => 'count', 'order' => 'DESC' ]);
            if ( is_wp_error( $sub_subcats ) || empty( $sub_subcats ) ) continue;
        ?>
        <div class="mobile-submenu" id="sub-<?php echo esc_attr( $sc->slug ); ?>">
            <button class="mobile-submenu-back" data-close="sub-<?php echo esc_attr( $sc->slug ); ?>">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg>
                <?php echo esc_html( $cat->name ); ?>
            </button>
            <a href="<?php echo esc_url( get_term_link( $sc ) ); ?>" class="mobile-submenu-title">Tout <?php echo esc_html( $sc->name ); ?></a>
            <?php foreach ( $sub_subcats as $ssc ) : ?>
            <a href="<?php echo esc_url( get_term_link( $ssc ) ); ?>" class="mobile-submenu-item"><?php echo esc_html( $ssc->name ); ?></a>
            <?php endforeach; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
        <?php endforeach; endif; ?>

        <!-- Marques -->
        <div class="mobile-nav-item">
            <a href="<?php echo esc_url( home_url( '/marques/' ) ); ?>" class="mobile-nav-link">
                <div class="mobile-nav-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                </div>
                <span class="mobile-nav-text">Marques</span>
            </a>
        </div>
    </nav>

    <div class="mobile-menu-footer">
        <a href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '#' ); ?>" class="mobile-footer-btn primary">Mon compte</a>
        <a href="<?php echo esc_url( home_url( '/a-propos/' ) ); ?>" class="mobile-footer-btn secondary">FAQ</a>
    </div>
</div>
