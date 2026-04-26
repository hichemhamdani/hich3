<?php
/**
 * Template: Le Bon Plan Jimee — produits en promo.
 */

get_header();

$on_sale_ids = wc_get_product_ids_on_sale();
$has_promos  = ! empty( $on_sale_ids );

if ( $has_promos ) {
    $paged = max( 1, get_query_var( 'paged' ) );
    $promo_query = new WP_Query([
        'post_type'      => 'product',
        'posts_per_page' => 24,
        'paged'          => $paged,
        'post_status'    => 'publish',
        'post__in'       => $on_sale_ids,
        'meta_query'     => [
            'stock_clause' => [ 'key' => '_stock_status' ],
        ],
        'orderby' => [ 'stock_clause' => 'ASC', 'date' => 'DESC' ],
    ]);
    $total = $promo_query->found_posts;
}
?>

<div class="bon-plan-page">

    <!-- HERO -->
    <div class="bon-plan-hero">
        <nav class="breadcrumb" aria-label="Fil d'Ariane">
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Accueil</a>
            <span class="sep">&rsaquo;</span>
            <span class="current">Le Bon Plan Jimee</span>
        </nav>
        <div class="bon-plan-hero-content">
            <span class="bon-plan-eyebrow">Offre spéciale</span>
            <h1>Le Bon Plan <em>Jimee</em></h1>
            <?php if ( $has_promos ) : ?>
                <p class="bon-plan-desc">Nos meilleures offres du moment sur une sélection de produits. Profitez-en avant qu'il ne soit trop tard !</p>
                <p class="bon-plan-count"><?php echo $total; ?> produit<?php echo $total > 1 ? 's' : ''; ?> en promotion</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if ( $has_promos ) : ?>

        <!-- PRODUCT GRID -->
        <div class="bon-plan-grid-wrapper" data-total="<?php echo $total; ?>">
            <div class="product-grid" id="bonPlanGrid">
                <?php while ( $promo_query->have_posts() ) : $promo_query->the_post();
                    echo jimee_render_product_card( get_the_ID() );
                endwhile;
                wp_reset_postdata(); ?>
            </div>

            <div class="load-trigger" id="loadTrigger"></div>
            <div class="loading-spinner" id="loadingSpinner"><div class="spinner"></div></div>
            <p class="end-message" id="endMessage">Vous avez tout vu !</p>
        </div>

    <?php else : ?>

        <!-- EMPTY STATE -->
        <div class="bon-plan-empty">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                <line x1="7" y1="7" x2="7.01" y2="7"/>
            </svg>
            <h2>Pas de promotions pour le moment</h2>
            <p>Nos offres spéciales arrivent bientôt ! Abonnez-vous à notre newsletter pour être prévenu(e) dès le lancement.</p>
            <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="bon-plan-back">Explorer la boutique <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
        </div>

    <?php endif; ?>

</div>

<script>
(function(){
    var grid    = document.getElementById('bonPlanGrid');
    var trigger = document.getElementById('loadTrigger');
    var spinner = document.getElementById('loadingSpinner');
    var endMsg  = document.getElementById('endMessage');
    var wrapper = document.querySelector('.bon-plan-grid-wrapper');
    if (!grid || !wrapper) return;

    var TOTAL   = parseInt(wrapper.dataset.total, 10);
    var PER     = 12;
    var offset  = grid.children.length;
    var loading = false;

    function loadMore() {
        if (loading || offset >= TOTAL) return;
        loading = true;
        if (spinner) spinner.classList.add('active');

        var data = new FormData();
        data.append('action', 'jimee_load_bonplan');
        data.append('nonce', '<?php echo wp_create_nonce("jimee_bonplan"); ?>');
        data.append('offset', offset);
        data.append('per_page', PER);

        fetch('<?php echo admin_url("admin-ajax.php"); ?>', { method: 'POST', body: data })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (spinner) spinner.classList.remove('active');
                loading = false;
                if (res.success && res.data.html) {
                    var temp = document.createElement('div');
                    temp.innerHTML = res.data.html;
                    var cards = temp.children;
                    var delay = 0;
                    while (cards.length > 0) {
                        var card = cards[0];
                        card.style.animationDelay = delay * 60 + 'ms';
                        grid.appendChild(card);
                        delay++;
                    }
                    offset = grid.children.length;
                    if (window.JimeeWishlist) window.JimeeWishlist.updateUI();
                }
                if (!res.data.has_more || offset >= TOTAL) {
                    if (endMsg) endMsg.classList.add('active');
                    obs.disconnect();
                }
                if (res.data.total !== undefined) TOTAL = res.data.total;
            })
            .catch(function() {
                if (spinner) spinner.classList.remove('active');
                loading = false;
            });
    }

    var obs = new IntersectionObserver(function(entries) {
        if (entries[0].isIntersecting) loadMore();
    }, { rootMargin: '300px' });

    if (trigger && offset < TOTAL) {
        obs.observe(trigger);
    } else if (endMsg && offset >= TOTAL) {
        endMsg.classList.add('active');
    }
})();
</script>
<?php get_footer(); ?>
