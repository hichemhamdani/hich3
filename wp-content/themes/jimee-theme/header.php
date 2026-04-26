<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<!-- ANNOUNCEMENT BAR -->
<div class="announcement-bar">
    <div class="announcement-track">
        <?php
        $messages = jimee_get_announcements();
        // Duplicate for infinite marquee
        foreach ( array_merge( $messages, $messages ) as $msg ) {
            echo '<span>' . esc_html( $msg ) . '</span>';
        }
        ?>
    </div>
</div>

<!-- HEADER WRAPPER (sticky) -->
<div class="header-sticky-wrap" id="headerWrap">
<!-- HEADER -->
<header class="header" id="header">
    <div class="header-main">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="logo">
            <img src="<?php echo esc_url( JIMEE_URI . '/assets/img/logo-jimee-cosmetics-noir.png' ); ?>" alt="Jimee Cosmetics" class="logo-img" width="97" height="40">
        </a>

        <div class="search-wrapper" id="searchWrapper">
            <form action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get" class="search-bar" id="searchBar">
                <input type="text" name="s" id="headerSearchInput" placeholder="Rechercher un produit, une marque..." autocomplete="off">
                <input type="hidden" name="post_type" value="product">
                <button type="submit" class="search-circle">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </button>
            </form>
            <div class="search-dropdown" id="searchDropdown">
                <div class="search-dropdown-suggestions" id="searchSuggestions">
                    <div class="search-dropdown-title">Recherches populaires</div>
                    <a href="<?php echo esc_url( home_url( '/?s=sérum+vitamine+c&post_type=product' ) ); ?>" class="search-dropdown-item">Sérum vitamine C</a>
                    <a href="<?php echo esc_url( home_url( '/?s=crème+hydratante&post_type=product' ) ); ?>" class="search-dropdown-item">Crème hydratante</a>
                    <a href="<?php echo esc_url( home_url( '/?s=huile+rose+musquée&post_type=product' ) ); ?>" class="search-dropdown-item">Huile de rose musquée</a>
                    <a href="<?php echo esc_url( home_url( '/?s=masque+cheveux&post_type=product' ) ); ?>" class="search-dropdown-item">Masque cheveux</a>
                    <a href="<?php echo esc_url( home_url( '/?s=spf+50&post_type=product' ) ); ?>" class="search-dropdown-item">SPF 50</a>
                    <a href="<?php echo esc_url( home_url( '/?s=niacinamide&post_type=product' ) ); ?>" class="search-dropdown-item">Niacinamide</a>
                </div>
                <div class="search-dropdown-results" id="searchResults" style="display:none"></div>
                <a href="#" class="search-dropdown-all" id="searchAllLink" style="display:none">Voir tous les résultats</a>
            </div>
        </div>

        <div class="header-actions">
            <a href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : '#' ); ?>" class="header-action-btn" aria-label="Compte">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </a>
            <a href="<?php echo esc_url( home_url( '/wishlist/' ) ); ?>" class="header-action-btn wishlist-header-btn" aria-label="Wishlist">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                <span class="wishlist-count" id="wishlistCount">0</span>
            </a>
            <button class="header-action-btn cart-toggle-btn" id="cartToggleBtn" aria-label="Panier">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
                <span class="cart-count" id="cartCount"><?php echo function_exists( 'WC' ) && WC()->cart ? WC()->cart->get_cart_contents_count() : '0'; ?></span>
            </button>
        </div>
    </div>

</header>

<!-- CATEGORY CHIPS SLIDER (outside sticky header to avoid Safari text rasterization) -->
<div class="category-slider" id="categorySlider">
    <div class="chips">
        <button class="menu-toggle" id="menuToggle" aria-label="Menu">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <?php
        $categories = get_terms([
            'taxonomy'   => 'product_cat',
            'parent'     => 0,
            'hide_empty' => true,
            'orderby'    => 'meta_value_num',
            'meta_key'   => 'order',
            'order'      => 'ASC',
            'exclude'    => jimee_excluded_cats(),
        ]);

        $current_term_id = 0;
        if ( is_product_category() ) {
            $current_term_id = get_queried_object_id();
        }

        if ( ! is_wp_error( $categories ) && ! empty( $categories ) ) {
            foreach ( $categories as $cat ) {
                if ( in_array( $cat->slug, [ 'uncategorized', 'non-classe', 'non-categorise' ], true ) ) continue;

                $active = ( $cat->term_id === $current_term_id ) ? ' active' : '';
                printf(
                    '<a href="%s" class="chip%s">%s</a>',
                    esc_url( get_term_link( $cat ) ),
                    $active,
                    esc_html( $cat->name )
                );
            }
        }
        ?>
        <a href="<?php echo esc_url( home_url( '/marques/' ) ); ?>" class="chip<?php echo is_page( 'marques' ) ? ' active' : ''; ?>">Marques</a>
        <a href="<?php echo esc_url( home_url( '/label/bio/' ) ); ?>" class="chip chip-bio"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px;margin-right:4px"><path d="M11 20A7 7 0 0 1 9.8 6.9C15.5 4.9 17 3.5 17 3.5s0 6-4.3 11.2"/><path d="M6.7 13.5c.8-1 2-2.2 3.8-3.5"/></svg>Bio</a>
        <a href="<?php echo esc_url( home_url( '/le-bon-plan-jimee/' ) ); ?>" class="chip chip-promo">Le Bon Plan Jimee</a>
    </div>
</div>
</div><!-- /.header-sticky-wrap -->

<?php get_template_part( 'template-parts/mobile-menu' ); ?>
<?php get_template_part( 'template-parts/side-cart' ); ?>
<?php get_template_part( 'template-parts/search-overlay' ); ?>

<main id="main-content">
