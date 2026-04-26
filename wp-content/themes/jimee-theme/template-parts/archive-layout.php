<?php
/**
 * Shared archive layout for categories and brands.
 * Used by taxonomy-product_cat.php and taxonomy-product_brand.php.
 *
 * Expected $args:
 *   'taxonomy'        => 'product_cat' | 'product_brand'
 *   'term'            => WP_Term object
 *   'show_subcats'    => bool
 *   'filter_taxonomy' => 'product_brand' | 'product_cat'
 *   'filter_label'    => 'Marque' | 'Catégorie'
 */

$taxonomy        = $args['taxonomy'] ?? 'product_cat';
$term            = $args['term'] ?? null;
$is_shop         = ! empty( $args['is_shop'] );
$term_id         = $term ? $term->term_id : 0;
$show_subcats    = $args['show_subcats'] ?? false;
$filter_taxonomy = $args['filter_taxonomy'] ?? 'product_brand';
$filter_label    = $args['filter_label'] ?? 'Marque';
$page_title      = $args['page_title'] ?? ( $term ? $term->name : 'Boutique' );

$parent_id   = $term ? ( $term->parent ?? 0 ) : 0;
$parent_term = $parent_id ? get_term( $parent_id, $taxonomy ) : null;

/* ── Subcategory pills ─────────────────────────── */
$subcategories = [];
if ( $show_subcats && $term ) {
    $pills_parent_id   = $parent_id ?: $term_id;
    $pills_parent_term = $parent_id ? $parent_term : $term;
    $subcategories     = get_terms([
        'taxonomy'   => $taxonomy,
        'parent'     => $pills_parent_id,
        'hide_empty' => true,
        'orderby'    => 'count',
        'order'      => 'DESC',
    ]);
    if ( is_wp_error( $subcategories ) ) $subcategories = [];
    $tous_url       = get_term_link( $pills_parent_term );
    $is_tous_active = ! $parent_id;
}

/* ── Tax query (shared) ────────────────────────── */
$tax_query = [];
if ( $term ) {
    $tax_query[] = [
        'taxonomy'         => $taxonomy,
        'field'            => 'term_id',
        'terms'            => $term_id,
        'include_children' => ( $taxonomy === 'product_cat' ),
    ];
}

/* ── Promo products ────────────────────────────── */
$on_sale_ids = wc_get_product_ids_on_sale();
$promo_query = null;
if ( ! empty( $on_sale_ids ) ) {
    $promo_args = [
        'post_type'      => 'product',
        'posts_per_page' => 10,
        'post_status'    => 'publish',
        'post__in'       => $on_sale_ids,
        'meta_query'     => [
            'stock_clause' => [ 'key' => '_stock_status' ],
        ],
        'orderby' => [ 'stock_clause' => 'ASC', 'date' => 'DESC' ],
    ];
    if ( ! empty( $tax_query ) ) $promo_args['tax_query'] = $tax_query;
    $promo_query = new WP_Query( $promo_args );
}
$has_promos = $promo_query && $promo_query->have_posts();

/* ── Initial products (12) ─────────────────────── */
$product_args = [
    'post_type'      => 'product',
    'posts_per_page' => 12,
    'post_status'    => 'publish',
    'meta_query'     => [
        'stock_clause' => [ 'key' => '_stock_status' ],
    ],
    'orderby' => [ 'stock_clause' => 'ASC', 'date' => 'DESC' ],
];
if ( ! empty( $tax_query ) ) $product_args['tax_query'] = $tax_query;
$product_query = new WP_Query( $product_args );
$total_products = $product_query->found_posts;

/* ── Cross-taxonomy filter terms ──────────────── */
$filter_args = [
    'taxonomy'   => $filter_taxonomy,
    'hide_empty' => true,
    'orderby'    => 'count',
    'order'      => 'DESC',
    'number'     => 30,
];
// On brand pages, show only top-level categories (not subcategories)
if ( $filter_taxonomy === 'product_cat' ) {
    $filter_args['parent']  = 0;
    $filter_args['exclude'] = jimee_excluded_cats();
}
$filter_terms = get_terms( $filter_args );
if ( is_wp_error( $filter_terms ) ) $filter_terms = [];
?>

<!-- Prefetch subcategory pages -->
<?php if ( $show_subcats ) : ?>
    <?php foreach ( $subcategories as $sc ) : ?>
        <?php if ( $sc->term_id !== $term_id ) : ?>
            <link rel="prefetch" href="<?php echo esc_url( get_term_link( $sc ) ); ?>">
        <?php endif; ?>
    <?php endforeach; ?>
    <?php if ( $parent_id ) : ?>
        <link rel="prefetch" href="<?php echo esc_url( $tous_url ); ?>">
    <?php endif; ?>
<?php endif; ?>

<div class="jimee-archive" data-term="<?php echo esc_attr( $term_id ); ?>" data-taxonomy="<?php echo esc_attr( $taxonomy ?: '' ); ?>" data-total="<?php echo esc_attr( $total_products ); ?>"<?php if ( $is_shop ) echo ' data-shop="1"'; ?>>

    <!-- HERO BLOCK -->
    <div class="hero-block">
        <nav class="breadcrumb" aria-label="Fil d'Ariane">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Accueil</a>
            <span class="sep">&rsaquo;</span>
            <?php if ( $is_shop ) : ?>
                <span class="current"><?php echo esc_html( $page_title ); ?></span>
            <?php elseif ( $taxonomy === 'product_brand' ) : ?>
                <a href="<?php echo esc_url( home_url( '/marques/' ) ); ?>">Marques</a>
                <span class="sep">&rsaquo;</span>
                <span class="current"><?php echo esc_html( $term->name ); ?></span>
            <?php elseif ( $taxonomy === 'product_label' ) : ?>
                <span>Labels</span>
                <span class="sep">&rsaquo;</span>
                <span class="current"><?php echo esc_html( $term->name ); ?></span>
            <?php else : ?>
                <?php if ( $parent_term ) : ?>
                    <a href="<?php echo esc_url( get_term_link( $parent_term ) ); ?>"><?php echo esc_html( $parent_term->name ); ?></a>
                    <span class="sep">&rsaquo;</span>
                <?php endif; ?>
                <span class="current"><?php echo esc_html( $term->name ); ?></span>
            <?php endif; ?>
        </nav>
        <?php
        // BreadcrumbList JSON-LD
        $bc_items = [ [ 'name' => 'Accueil', 'url' => home_url( '/' ) ] ];
        if ( $is_shop ) {
            $bc_items[] = [ 'name' => $page_title ];
        } elseif ( $taxonomy === 'product_brand' ) {
            $bc_items[] = [ 'name' => 'Marques', 'url' => home_url( '/marques/' ) ];
            $bc_items[] = [ 'name' => $term->name ];
        } elseif ( $taxonomy === 'product_label' ) {
            $bc_items[] = [ 'name' => 'Labels' ];
            $bc_items[] = [ 'name' => $term->name ];
        } else {
            if ( $parent_term ) {
                $bc_items[] = [ 'name' => $parent_term->name, 'url' => get_term_link( $parent_term ) ];
            }
            $bc_items[] = [ 'name' => $term->name ];
        }
        jimee_breadcrumb_jsonld( $bc_items );
        ?>

        <div class="category-hero">
            <h1><?php echo esc_html( $page_title ); ?></h1>
            <?php if ( $term && $term->description ) : ?>
                <p class="category-hero-desc"><?php echo esc_html( wp_strip_all_tags( $term->description ) ); ?></p>
            <?php elseif ( $is_shop ) : ?>
                <p class="category-hero-desc">Explorez notre catalogue de plus de 7 000 produits cosmétiques.</p>
            <?php endif; ?>
            <p class="category-hero-count"><?php echo esc_html( $total_products ); ?> produit<?php echo $total_products > 1 ? 's' : ''; ?></p>
        </div>

        <?php if ( $show_subcats && ! empty( $subcategories ) ) : ?>
        <div class="subcategory-pills">
            <div class="pills-track">
                <a href="<?php echo esc_url( $tous_url ); ?>" class="pill<?php echo $is_tous_active ? ' active' : ''; ?>">Tous</a>
                <?php foreach ( $subcategories as $sc ) : ?>
                    <a href="<?php echo esc_url( get_term_link( $sc ) ); ?>" class="pill<?php echo ( $sc->term_id === $term_id ) ? ' active' : ''; ?>"><?php echo esc_html( $sc->name ); ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- PROMO SECTION -->
    <?php if ( $has_promos ) : ?>
    <section class="promo-section visible" id="promoSection">
        <div class="promo-container">
            <div class="promo-header">
                <div class="promo-title">
                    <span class="promo-badge">Promos</span>
                    <h2>Promos du <em>moment</em></h2>
                </div>
                <div class="promo-nav">
                    <button class="promo-prev" aria-label="Précédent"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="m15 18-6-6 6-6"/></svg></button>
                    <button class="promo-next" aria-label="Suivant"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="m9 18 6-6-6-6"/></svg></button>
                </div>
            </div>
            <div class="promo-track" id="promoTrack">
                <?php while ( $promo_query->have_posts() ) : $promo_query->the_post();
                    $p  = wc_get_product( get_the_ID() );
                    if ( ! $p ) continue;
                    $reg  = (float) $p->get_regular_price();
                    $sale = (float) $p->get_sale_price();
                    $disc = $reg > 0 ? round( ( 1 - $sale / $reg ) * 100 ) : 0;
                    $brands_t = wp_get_post_terms( get_the_ID(), 'product_brand' );
                    $brand_name = ! empty( $brands_t ) ? $brands_t[0]->name : '';
                    $img = get_the_post_thumbnail_url( get_the_ID(), 'woocommerce_thumbnail' ) ?: wc_placeholder_img_src( 'woocommerce_thumbnail' );
                ?>
                <a href="<?php the_permalink(); ?>" class="promo-card">
                    <div class="promo-card-img">
                        <img src="<?php echo esc_url( $img ); ?>" alt="<?php the_title_attribute(); ?>" loading="lazy">
                        <div class="promo-card-badge">-<?php echo $disc; ?>%</div>
                        <button class="wishlist-btn" data-product-id="<?php echo esc_attr( get_the_ID() ); ?>" aria-label="Ajouter aux favoris">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                        </button>
                        <?php if ( $p->is_in_stock() ) : ?>
                        <button class="cart-btn" data-add-to-cart="<?php echo esc_attr( get_the_ID() ); ?>" aria-label="Ajouter au panier">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
                            <span class="cart-btn-plus">+</span>
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="promo-card-info">
                        <div class="promo-card-brand"><?php echo esc_html( strtoupper( $brand_name ) ); ?></div>
                        <div class="promo-card-name"><?php the_title(); ?></div>
                        <div class="promo-card-price">
                            <span class="price-new"><?php echo number_format( $sale, 0, ',', ' ' ); ?> DA</span>
                            <span class="price-old"><?php echo number_format( $reg, 0, ',', ' ' ); ?> DA</span>
                            <?php if ( $disc > 0 ) : ?>
                            <span class="price-discount">-<?php echo $disc; ?>%</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </a>
                <?php endwhile; wp_reset_postdata(); ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- TOOLBAR -->
    <div class="toolbar">
        <div class="toolbar-left">
            <button class="filter-btn" id="filterToggle">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/></svg>
                Filtres
            </button>
        </div>
        <div class="toolbar-right">
            <select class="sort-select" id="sortSelect">
                <option value="default">Trier par : Pertinence</option>
                <option value="price-asc">Prix croissant</option>
                <option value="price-desc">Prix décroissant</option>
                <option value="date">Nouveautés</option>
                <option value="popularity">Meilleures ventes</option>
            </select>
        </div>
    </div>

    <!-- FILTER DRAWER (lateral panel — full filters, opened by "Filtres" button) -->
    <div class="filter-drawer-overlay" id="filterDrawerOverlay"></div>
    <div class="filter-drawer" id="filterDrawer">
        <div class="filter-drawer-header">
            <span class="filter-drawer-title">Filtres</span>
            <button class="filter-drawer-close" id="filterDrawerClose" aria-label="Fermer">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="filter-drawer-body">
        <!-- SECTION: Marque / Catégorie -->
        <?php if ( ! empty( $filter_terms ) ) : ?>
        <div class="filter-drawer-section" id="filterSectionTax">
            <h3 class="filter-drawer-section-title"><?php echo esc_html( $filter_label ); ?></h3>
            <div class="filter-drawer-search">
                <input type="text" id="filterTaxSearch" placeholder="Rechercher une <?php echo esc_attr( strtolower( $filter_label ) ); ?>..." autocomplete="off">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:18px;height:18px;color:#999;flex-shrink:0"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </div>
            <div class="filter-drawer-list" id="filterTaxList" data-filter="cross">
                <?php foreach ( $filter_terms as $ft ) : ?>
                <label class="filter-checkbox" data-name="<?php echo esc_attr( strtolower( $ft->name ) ); ?>">
                    <input type="checkbox" value="<?php echo esc_attr( $ft->term_id ); ?>">
                    <span><?php echo esc_html( $ft->name ); ?></span>
                    <span class="filter-count"><?php echo $ft->count; ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- SECTION: Contenance -->
        <div class="filter-drawer-section" id="filterSectionSize">
            <h3 class="filter-drawer-section-title">Contenance</h3>
            <div class="filter-drawer-sizes" data-filter="size">
                <label class="filter-size-pill"><input type="checkbox" value="0-8"> 5 ml</label>
                <label class="filter-size-pill"><input type="checkbox" value="9-15"> 10 ml</label>
                <label class="filter-size-pill"><input type="checkbox" value="20-40"> 30 ml</label>
                <label class="filter-size-pill"><input type="checkbox" value="41-75"> 50 ml</label>
                <label class="filter-size-pill"><input type="checkbox" value="76-150"> 100 ml</label>
                <label class="filter-size-pill"><input type="checkbox" value="151-250"> 200 ml</label>
                <label class="filter-size-pill"><input type="checkbox" value="251-400"> 300 ml</label>
                <label class="filter-size-pill"><input type="checkbox" value="401-9999"> 500 ml+</label>
            </div>
        </div>

        <!-- SECTION: Labels (Bio, Vegan, Produit local, Sans alcool) — hidden on label archives -->
        <?php
        $label_filter_terms = get_terms([
            'taxonomy'   => 'product_label',
            'hide_empty' => true,
        ]);
        if ( $taxonomy !== 'product_label' && ! is_wp_error( $label_filter_terms ) && ! empty( $label_filter_terms ) ) : ?>
        <div class="filter-drawer-section" id="filterSectionLabels">
            <h3 class="filter-drawer-section-title">Labels</h3>
            <div class="filter-drawer-list" data-filter="labels">
                <?php foreach ( $label_filter_terms as $lt ) : ?>
                <label class="filter-checkbox" data-name="<?php echo esc_attr( strtolower( $lt->name ) ); ?>">
                    <input type="checkbox" value="<?php echo esc_attr( $lt->slug ); ?>">
                    <span><?php echo esc_html( $lt->name ); ?></span>
                    <span class="filter-count"><?php echo $lt->count; ?></span>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        </div><!-- /.filter-drawer-body -->

        <!-- APPLY / RESET -->
        <div class="filter-drawer-footer">
            <button class="filter-drawer-reset" id="filterReset">Réinitialiser</button>
            <button class="filter-drawer-apply" id="filterApply">Voir les résultats</button>
        </div>
    </div>

    <!-- PRODUCT GRID -->
    <div class="product-grid-wrapper">
        <div class="product-grid" id="productGrid">
            <?php if ( $product_query->have_posts() ) :
                while ( $product_query->have_posts() ) : $product_query->the_post();
                    echo jimee_render_product_card( get_the_ID() );
                endwhile;
                wp_reset_postdata();
            endif; ?>
        </div>
        <div class="load-trigger" id="loadTrigger"></div>
        <div class="loading-spinner" id="loadingSpinner">
            <div class="spinner"></div>
            <div class="loading-text">Chargement des produits...</div>
        </div>
        <div class="end-message" id="endMessage">Vous avez tout vu !</div>
    </div>

</div><!-- .jimee-archive -->
