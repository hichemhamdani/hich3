<?php
/**
 * Template Name: Toutes les marques
 * All brands A-Z listing.
 */

get_header();

$brands = get_terms([
    'taxonomy'   => 'product_brand',
    'hide_empty' => true,
    'orderby'    => 'name',
    'order'      => 'ASC',
]);

// Group by first letter
$grouped = [];
if ( ! is_wp_error( $brands ) ) {
    foreach ( $brands as $brand ) {
        $letter = mb_strtoupper( mb_substr( $brand->name, 0, 1 ) );
        if ( is_numeric( $letter ) ) $letter = '#';
        $grouped[ $letter ][] = $brand;
    }
}
// Sort letters A-Z, then # at the end
ksort( $grouped );
if ( isset( $grouped['#'] ) ) {
    $hash = $grouped['#'];
    unset( $grouped['#'] );
    $grouped['#'] = $hash;
}
$letters = array_keys( $grouped );
$total = is_wp_error( $brands ) ? 0 : count( $brands );
?>

<div class="brands-hero">
    <nav class="breadcrumb" aria-label="Fil d'Ariane" style="color:rgba(255,255,255,.4);justify-content:center">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="color:rgba(255,255,255,.4)">Accueil</a>
        <span class="sep" style="color:rgba(255,255,255,.6)">&rsaquo;</span>
        <span style="color:rgba(255,255,255,.7);font-weight:500">Marques</span>
    </nav>
    <h1>Toutes nos <em>marques</em></h1>
    <p class="brands-hero-count"><?php echo $total; ?> marque<?php echo $total > 1 ? 's' : ''; ?></p>
    <div class="faq-search" style="margin-top:20px">
        <input type="text" id="brandSearch" placeholder="Rechercher une marque..." style="color:#fff">
        <button type="button" class="search-circle" style="width:36px;height:36px">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;color:#fff"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </button>
    </div>
</div>

<!-- Marques phares -->
<?php
$featured_brands = get_terms([
    'taxonomy'   => 'product_brand',
    'hide_empty' => true,
    'orderby'    => 'count',
    'order'      => 'DESC',
    'number'     => 10,
]);
if ( ! is_wp_error( $featured_brands ) && ! empty( $featured_brands ) ) : ?>
<section style="max-width:1440px;margin:0 auto;padding:24px 32px 0">
    <h2 style="font-size:18px;font-weight:300;margin-bottom:16px">Marques <em style="font-weight:600;font-style:italic">phares</em></h2>
    <div class="brands-slider">
        <?php foreach ( $featured_brands as $fb ) :
            $fb_logo_id = get_term_meta( $fb->term_id, 'pharma_logo_square', true );
            $fb_img = $fb_logo_id ? wp_get_attachment_image_url( $fb_logo_id, 'thumbnail' ) : '';
            $fb_desc = $fb->description ? wp_trim_words( wp_strip_all_tags( $fb->description ), 12 ) : '';
        ?>
        <a href="<?php echo esc_url( get_term_link( $fb ) ); ?>" class="brand-card">
            <div class="brand-card-img">
                <?php if ( $fb_img ) : ?>
                    <img src="<?php echo esc_url( $fb_img ); ?>" alt="<?php echo esc_attr( $fb->name ); ?>" loading="lazy">
                <?php else : ?>
                    <div class="brand-card-initials"><?php echo esc_html( jimee_brand_initials( $fb->name ) ); ?></div>
                <?php endif; ?>
            </div>
            <div class="brand-card-info">
                <div class="brand-card-name"><?php echo esc_html( $fb->name ); ?></div>
                <?php if ( $fb_desc ) : ?><div class="brand-card-desc"><?php echo esc_html( $fb_desc ); ?></div><?php endif; ?>
                <span class="brand-card-link">Explorer <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- Alphabet navigation -->
<div class="brands-alpha-nav">
    <?php foreach ( $letters as $letter ) : ?>
        <a href="#letter-<?php echo esc_attr( $letter ); ?>" class="brands-alpha-link"><?php echo esc_html( $letter ); ?></a>
    <?php endforeach; ?>
</div>

<!-- Brand listings by letter -->
<?php foreach ( $grouped as $letter => $letter_brands ) : ?>
<div class="brands-letter-section" id="letter-<?php echo esc_attr( $letter ); ?>">
    <div class="brands-letter"><?php echo esc_html( $letter ); ?></div>
    <div class="brands-grid">
        <?php foreach ( $letter_brands as $brand ) :
            $logo_id = get_term_meta( $brand->term_id, 'pharma_logo_square', true );
            $img_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'thumbnail' ) : '';
        ?>
        <a href="<?php echo esc_url( get_term_link( $brand ) ); ?>" class="brand-card-az" data-brand="<?php echo esc_attr( strtolower( $brand->name ) ); ?>">
            <div class="brand-card-az-img">
                <?php if ( $img_url ) : ?>
                    <img src="<?php echo esc_url( $img_url ); ?>" alt="<?php echo esc_attr( $brand->name ); ?>" loading="lazy">
                <?php else : ?>
                    <div class="brand-card-initials"><?php echo esc_html( jimee_brand_initials( $brand->name ) ); ?></div>
                <?php endif; ?>
            </div>
            <div class="brand-card-az-info">
                <div class="brand-card-az-name"><?php echo esc_html( $brand->name ); ?></div>
                <div class="brand-card-az-count"><?php echo $brand->count; ?> produit<?php echo $brand->count > 1 ? 's' : ''; ?></div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endforeach; ?>

<script>
(function() {
    var input = document.getElementById('brandSearch');
    if (!input) return;
    input.addEventListener('input', function() {
        var q = this.value.toLowerCase().trim();
        document.querySelectorAll('.brand-card-az').forEach(function(card) {
            var name = card.getAttribute('data-brand') || '';
            card.style.display = (!q || name.indexOf(q) > -1) ? '' : 'none';
        });
        document.querySelectorAll('.brands-letter-section').forEach(function(sec) {
            var hasVisible = sec.querySelector('.brand-card-az:not([style*="display: none"])');
            sec.style.display = hasVisible ? '' : 'none';
        });
    });
})();
</script>

<?php get_footer(); ?>
