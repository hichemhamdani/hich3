<?php
/**
 * Search results — same product cards and grid as archive pages.
 */

get_header();

$search_query = get_search_query();

// Re-query to force products only, with stock-first ordering
$search_args = [
    'post_type'      => 'product',
    'posts_per_page' => 24,
    'post_status'    => 'publish',
    's'              => $search_query,
    'meta_query'     => [
        'stock_clause' => [ 'key' => '_stock_status' ],
    ],
    'orderby' => [ 'stock_clause' => 'ASC', 'relevance' => 'DESC' ],
];

$search = new WP_Query( $search_args );
$total  = $search->found_posts;
?>

<!-- HERO -->
<div class="search-hero">
    <nav class="breadcrumb" aria-label="Fil d'Ariane" style="color:rgba(255,255,255,.4)">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="color:rgba(255,255,255,.4)">Accueil</a>
        <span class="sep" style="color:rgba(255,255,255,.6)">&rsaquo;</span>
        <span style="color:rgba(255,255,255,.7);font-weight:500">Recherche</span>
    </nav>
    <h1>Résultats pour « <?php echo esc_html( $search_query ); ?> »</h1>
    <p class="search-hero-count"><?php echo esc_html( $total ); ?> résultat<?php echo $total > 1 ? 's' : ''; ?></p>
</div>

<?php if ( $search->have_posts() ) : ?>

<!-- TOOLBAR (sort only, no filters on search) -->
<div class="toolbar">
    <div class="toolbar-left">
        <span style="font-size:13px;color:#999"><?php echo esc_html( $total ); ?> produit<?php echo $total > 1 ? 's' : ''; ?> trouvé<?php echo $total > 1 ? 's' : ''; ?></span>
    </div>
    <div class="toolbar-right">
        <select class="sort-select" id="searchSort" onchange="window.location.href='<?php echo esc_url( home_url( '/?s=' . urlencode( $search_query ) . '&post_type=product' ) ); ?>&orderby='+this.value">
            <option value="relevance">Trier par : Pertinence</option>
            <option value="price-asc">Prix croissant</option>
            <option value="price-desc">Prix décroissant</option>
            <option value="date">Nouveautés</option>
        </select>
    </div>
</div>

<!-- PRODUCT GRID -->
<div class="product-grid-wrapper">
    <div class="product-grid" id="productGrid">
        <?php while ( $search->have_posts() ) : $search->the_post();
            echo jimee_render_product_card( get_the_ID() );
        endwhile;
        wp_reset_postdata(); ?>
    </div>

    <?php if ( $total > 24 ) : ?>
    <div id="searchLoadMore" style="text-align:center;padding:32px 0">
        <button onclick="jimeeSearchLoadMore(this)" data-offset="24" data-query="<?php echo esc_attr( $search_query ); ?>" data-total="<?php echo esc_attr( $total ); ?>" style="display:inline-block;padding:14px 32px;border-radius:var(--radius-pill);background:var(--black);color:var(--white);font-size:13px;font-weight:500;cursor:pointer;border:none">
            Voir plus de résultats
        </button>
    </div>
    <script>
    function jimeeSearchLoadMore(btn) {
        var offset = parseInt(btn.dataset.offset, 10);
        var total = parseInt(btn.dataset.total, 10);
        var query = btn.dataset.query;
        btn.textContent = 'Chargement...';
        btn.disabled = true;
        var fd = new FormData();
        fd.append('action', 'jimee_search_load_more');
        fd.append('s', query);
        fd.append('offset', offset);
        fd.append('nonce', '<?php echo wp_create_nonce("jimee_search"); ?>');
        fetch('<?php echo admin_url("admin-ajax.php"); ?>', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success && data.data.html) {
                document.getElementById('productGrid').insertAdjacentHTML('beforeend', data.data.html);
                var newOffset = offset + 24;
                if (newOffset >= total) {
                    btn.parentNode.style.display = 'none';
                } else {
                    btn.dataset.offset = newOffset;
                    btn.textContent = 'Voir plus de résultats';
                    btn.disabled = false;
                }
                if (window.JimeeWishlist) JimeeWishlist.updateUI();
            }
        });
    }
    </script>
    <?php endif; ?>
</div>

<?php else : ?>

<!-- EMPTY STATE -->
<div class="search-empty">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:48px;height:48px;color:#ccc;margin-bottom:16px"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <h2>Aucun résultat</h2>
    <p>Nous n'avons trouvé aucun produit pour « <?php echo esc_html( $search_query ); ?> ».</p>
    <p style="color:#999;font-size:13px;margin-bottom:24px">Vérifiez l'orthographe ou essayez un terme plus général.</p>

    <!-- Search again -->
    <form action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get" style="max-width:400px;margin:0 auto 32px">
        <div class="search-bar" style="width:100%;max-width:100%">
            <input type="text" name="s" value="<?php echo esc_attr( $search_query ); ?>" placeholder="Rechercher un produit..." style="flex:1;background:none;border:none;outline:none;font-size:14px;padding:8px 0">
            <input type="hidden" name="post_type" value="product">
            <button type="submit" class="search-circle">
                <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" style="width:16px;height:16px"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </button>
        </div>
    </form>

    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="display:inline-block;padding:12px 28px;border-radius:var(--radius-pill);background:var(--black);color:var(--white);font-size:13px;font-weight:500">
        Retour à l'accueil
    </a>
</div>

<?php endif; ?>

<?php get_footer(); ?>
