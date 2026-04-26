<?php
/**
 * Homepage — 12 sections dynamiques.
 */

get_header();

/* ── Category icons ─────────────────────── */
$home_cats = get_terms([
    'taxonomy'   => 'product_cat',
    'parent'     => 0,
    'hide_empty' => true,
    'orderby'    => 'meta_value_num',
    'meta_key'   => 'order',
    'order'      => 'ASC',
    'exclude'    => jimee_excluded_cats(),
]);
if ( is_wp_error( $home_cats ) ) $home_cats = [];

/* ── Sélection du mois (featured products) ─ */
$best_query = new WP_Query([
    'post_type'      => 'product',
    'posts_per_page' => 8,
    'post_status'    => 'publish',
    'orderby'        => 'rand',
    'tax_query'      => [[
        'taxonomy' => 'product_visibility',
        'field'    => 'name',
        'terms'    => 'featured',
    ]],
    'meta_query'     => [[ 'key' => '_stock_status', 'value' => 'instock' ]],
]);

/* ── New arrivals (last 30 days) ────────── */
$new_query = new WP_Query([
    'post_type'      => 'product',
    'posts_per_page' => 4,
    'post_status'    => 'publish',
    'orderby'        => 'date',
    'order'          => 'DESC',
    'meta_query'     => [[ 'key' => '_stock_status', 'value' => 'instock' ]],
    'date_query'     => [[ 'after' => '30 days ago' ]],
]);
// Fallback if no recent products
if ( ! $new_query->have_posts() ) {
    $new_query = new WP_Query([
        'post_type'      => 'product',
        'posts_per_page' => 4,
        'post_status'    => 'publish',
        'orderby'        => 'date',
        'order'          => 'DESC',
        'meta_query'     => [[ 'key' => '_stock_status', 'value' => 'instock' ]],
    ]);
}

/* ── Brands for slider (curated) ─────────── */
$curated_brand_ids = [ 3503, 3495, 1570, 3657, 1540, 3683, 3715, 3477, 1550, 3354, 3364, 3716 ];
// L'Oréal, Kérastase, Filorga, SKIN 1004, Avène, Touché, Dior, Huda Beauty, CeraVe, Burberry, Caudalie, Chanel
$home_brands = get_terms([
    'taxonomy'   => 'product_brand',
    'include'    => $curated_brand_ids,
    'orderby'    => 'include',
    'hide_empty' => false,
]);
if ( is_wp_error( $home_brands ) ) $home_brands = [];

/* ── Promo products ─────────────────────── */
$on_sale_ids = wc_get_product_ids_on_sale();
$has_sale = ! empty( $on_sale_ids );
?>

<!-- ═══════ 1. HERO SLIDER ═══════ -->
<?php
$img_base    = JIMEE_URI . '/assets/img/';
$hero_slides = jimee_get_hero_slides();
?>
<div class="hero-wrapper">
    <div class="hero" id="hero">
        <div class="hero-slides">
            <?php foreach ( $hero_slides as $i => $slide ) :
                $slide_img = ! empty( $slide['image'] ) ? wp_get_attachment_image_url( $slide['image'], 'full' ) : '';
                $slide_style = $slide_img ? 'background-image:url(' . esc_url( $slide_img ) . ')' : '';
                $heading_tag = $i === 0 ? 'h1' : 'h2';
            ?>
            <div class="hero-slide<?php echo $i === 0 ? ' active' : ''; ?>">
                <div class="hero-content">
                    <div class="hero-eyebrow"><?php echo esc_html( $slide['eyebrow'] ?? '' ); ?></div>
                    <<?php echo $heading_tag; ?> class="hero-title"><?php echo wp_kses( $slide['title'] ?? '', [ 'em' => [] ] ); ?></<?php echo $heading_tag; ?>>
                    <p class="hero-desc"><?php echo esc_html( $slide['desc'] ?? '' ); ?></p>
                    <?php if ( ! empty( $slide['link'] ) ) : ?>
                    <a href="<?php echo esc_url( home_url( $slide['link'] ) ); ?>" class="hero-cta"><?php echo esc_html( $slide['cta'] ?? 'Découvrir' ); ?> <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
                    <?php endif; ?>
                </div>
                <div class="hero-image"<?php if ( $slide_style ) echo ' style="' . esc_attr( $slide_style ) . '"'; ?>></div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="hero-dots">
            <?php foreach ( $hero_slides as $i => $s ) : ?>
            <button class="hero-dot<?php echo $i === 0 ? ' active' : ''; ?>"></button>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ═══════ 2. CATEGORIES (infinite scroll + fade) ═══════ -->
<?php
$cat_photos = [
    'visage'      => 'masque-visage-routine-skincare.jpg',
    'cheveux'     => 'soin-cheveux-routine-capillaire.jpg',
    'solaire'     => 'protection-solaire-ete-piscine.jpg',
    'corps'       => 'spa-hammam-detente-bien-etre.jpg',
    'homme'       => 'soin-visage-homme-serum-huile.jpg',
    'maquillage'  => 'peau-glowy-maquillage-naturel.jpg',
    'hygiene'     => 'lavage-mains-mousse-savon-hygiene.jpg',
    'k-beauty'    => 'tocobo-cotton-airy-sun-stick-spf50.jpg',
    'coffrets-cadeaux' => 'gucci-flora-eau-de-parfum.jpg',
    'accesoires'  => 'accessoires-beaute-outils-coiffure.webp',
];

// Build category items array (to duplicate for infinite scroll)
$cat_items = [];
if ( ! empty( $home_cats ) ) {
    foreach ( $home_cats as $cat ) {
        if ( in_array( $cat->slug, [ 'uncategorized', 'non-classe', 'non-categorise' ], true ) ) continue;
        $cat_items[] = $cat;
    }
}

if ( ! empty( $cat_items ) ) : ?>
<section class="section reveal">
    <div class="section-header">
        <div class="section-eyebrow">Explorer par catégorie</div>
        <h2 class="section-title">Nos <em>catégories</em></h2>
    </div>
    <div class="categories-wrapper">
        <div class="categories-track">
            <?php
            // Render twice for seamless infinite loop
            for ( $loop = 0; $loop < 2; $loop++ ) :
                foreach ( $cat_items as $cat ) :
                    $photo = isset( $cat_photos[ $cat->slug ] ) ? $img_base . $cat_photos[ $cat->slug ] : '';
            ?>
            <a href="<?php echo esc_url( get_term_link( $cat ) ); ?>" class="category-circle">
                <div class="category-circle-img">
                    <?php if ( $photo ) : ?>
                        <img src="<?php echo esc_url( $photo ); ?>" alt="<?php echo esc_attr( $cat->name ); ?>" loading="lazy">
                    <?php else : ?>
                        <div style="width:100%;height:100%;background:var(--warm-bg);display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:600;color:#999"><?php echo esc_html( mb_substr( $cat->name, 0, 3 ) ); ?></div>
                    <?php endif; ?>
                </div>
                <div class="category-circle-name"><?php echo esc_html( $cat->name ); ?></div>
            </a>
            <?php endforeach; endfor; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- ═══════ 3. BEST-SELLERS ═══════ -->
<?php if ( $best_query->have_posts() ) : ?>
<section class="section reveal">
    <div class="section-header" style="text-align:center">
        <div class="section-eyebrow">Nos coups de cœur</div>
        <h2 class="section-title">Sélection du <em>mois</em></h2>
    </div>
    <div class="product-grid" id="bestSellers">
        <?php while ( $best_query->have_posts() ) : $best_query->the_post();
            echo jimee_render_product_card( get_the_ID() );
        endwhile; wp_reset_postdata(); ?>
    </div>
</section>
<?php endif; ?>

<!-- ═══════ 4. PROMO BANNER ═══════ -->
<?php if ( $has_sale ) :
    $promo = jimee_get_promo_banner();
?>
<div class="section reveal" style="padding-top:0">
    <div class="promo-banner">
        <div class="promo-eyebrow"><?php echo esc_html( $promo['eyebrow'] ?? '' ); ?></div>
        <h2 class="promo-title-hp"><?php echo wp_kses( $promo['title'] ?? '', [ 'em' => [] ] ); ?></h2>
        <p class="promo-desc-hp"><?php echo esc_html( $promo['desc'] ?? '' ); ?></p>
        <a href="<?php echo esc_url( home_url( $promo['link'] ?? '/le-bon-plan-jimee/' ) ); ?>" class="promo-cta-hp"><?php echo esc_html( $promo['cta'] ?? 'Voir les offres' ); ?> <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
    </div>
</div>
<?php endif; ?>

<!-- ═══════ 5. NOUVEAUTES ═══════ -->
<?php if ( $new_query->have_posts() ) : ?>
<section class="section reveal">
    <div class="section-header" style="text-align:center">
        <div class="section-eyebrow" >Fraîchement arrivés</div>
        <h2 class="section-title">Les <em>nouveautés</em></h2>
    </div>
    <div class="product-grid">
        <?php while ( $new_query->have_posts() ) : $new_query->the_post();
            echo jimee_render_product_card( get_the_ID() );
        endwhile; wp_reset_postdata(); ?>
    </div>
</section>
<?php endif; ?>

<!-- ═══════ 6. DOUBLE BANNER ═══════ -->
<?php
$double_banners = jimee_get_double_banners();
$double_gradients = [
    'linear-gradient(160deg,rgba(245,230,211,.7),rgba(212,184,152,.8))',
    'linear-gradient(160deg,rgba(232,216,232,.7),rgba(192,168,196,.8))',
];
?>
<div class="double-banner reveal">
    <?php foreach ( $double_banners as $di => $dcard ) :
        $dimg_url = ! empty( $dcard['image'] ) ? wp_get_attachment_image_url( $dcard['image'], 'large' ) : '';
        $gradient = $double_gradients[ $di ] ?? $double_gradients[0];
        $bg_style = $dimg_url
            ? $gradient . ',url(' . esc_url( $dimg_url ) . ') center/cover'
            : $gradient;
    ?>
    <div class="double-card" style="background:<?php echo $bg_style; ?>">
        <div class="double-card-eyebrow"><?php echo esc_html( $dcard['eyebrow'] ?? '' ); ?></div>
        <h3 class="double-card-title"><?php echo wp_kses( $dcard['title'] ?? '', [ 'em' => [] ] ); ?></h3>
        <a href="<?php echo esc_url( home_url( $dcard['link'] ?? '/' ) ); ?>" class="double-card-cta"><?php echo esc_html( $dcard['cta'] ?? 'Découvrir' ); ?></a>
    </div>
    <?php endforeach; ?>
</div>

<!-- ═══════ 7. MARQUES SLIDER ═══════ -->
<?php if ( ! empty( $home_brands ) ) : ?>
<section class="section reveal">
    <div class="section-header" style="display:flex;align-items:flex-end;justify-content:space-between;text-align:left">
        <div>
            <div class="section-eyebrow" style="text-align:left">Nos partenaires</div>
            <h2 class="section-title">Nos <em>marques</em></h2>
        </div>
        <a href="<?php echo esc_url( home_url( '/marques/' ) ); ?>" class="section-link">Voir toutes les marques <svg viewBox="0 0 24 24" width="14" height="14" stroke="currentColor" stroke-width="2" fill="none"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></a>
    </div>
    <div class="brands-slider">
        <?php foreach ( $home_brands as $brand ) :
            $logo_id = get_term_meta( $brand->term_id, 'pharma_logo_square', true );
            $brand_img = $logo_id ? wp_get_attachment_image_url( $logo_id, 'thumbnail' ) : '';
            $desc = $brand->description ? wp_trim_words( wp_strip_all_tags( $brand->description ), 12 ) : '';
        ?>
        <a href="<?php echo esc_url( get_term_link( $brand ) ); ?>" class="brand-card">
            <div class="brand-card-img">
                <?php if ( $brand_img ) : ?>
                    <img src="<?php echo esc_url( $brand_img ); ?>" alt="<?php echo esc_attr( $brand->name ); ?>" loading="lazy">
                <?php else : ?>
                    <div class="brand-card-initials"><?php echo esc_html( $brand->name ); ?></div>
                <?php endif; ?>
            </div>
            <div class="brand-card-info">
                <div class="brand-card-name"><?php echo esc_html( $brand->name ); ?></div>
                <?php if ( $desc ) : ?><div class="brand-card-desc"><?php echo esc_html( $desc ); ?></div><?php endif; ?>
                <span class="brand-card-link">Explorer <svg viewBox="0 0 24 24"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg></span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<!-- ═══════ 8. REASSURANCE ═══════ -->
<section class="section reveal">
    <div class="reassurance-grid">
        <div class="reassurance-card">
            <div class="reassurance-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 3v5a2 2 0 0 1-2 2h-1"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg>
            </div>
            <div class="reassurance-title">Livraison offerte</div>
            <div class="reassurance-desc">Dès 10 000 DA d'achat partout en Algérie</div>
        </div>
        <div class="reassurance-card">
            <div class="reassurance-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </div>
            <div class="reassurance-title">Paiement sécurisé</div>
            <div class="reassurance-desc">Transactions cryptées CIB / Dahabia</div>
        </div>
        <div class="reassurance-card">
            <div class="reassurance-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/></svg>
            </div>
            <div class="reassurance-title">Authenticité garantie</div>
            <div class="reassurance-desc">100% produits originaux certifiés</div>
        </div>
        <div class="reassurance-card">
            <div class="reassurance-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5" rx="1"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg>
            </div>
            <div class="reassurance-title">Échantillons offerts</div>
            <div class="reassurance-desc">Des surprises dans chaque commande</div>
        </div>
    </div>
</section>

<!-- ═══════ 9. NEWSLETTER ═══════ -->
<div class="newsletter-section reveal">
    <div class="newsletter-card">
        <h2 class="newsletter-title">Rejoignez la <em>communauté</em></h2>
        <p class="newsletter-desc">Recevez en avant-première nos offres exclusives, conseils beauté et nouveautés.</p>
        <form class="newsletter-form" id="newsletterForm">
            <input type="email" placeholder="Votre adresse e-mail" required>
            <button type="submit">S'inscrire</button>
        </form>
    </div>
</div>

<!-- JSON-LD LocalBusiness Schema -->
<script type="application/ld+json">
<?php
echo wp_json_encode( [
    '@context'    => 'https://schema.org',
    '@type'       => 'CosmeticsStore',
    'name'        => 'Jimee Cosmetics',
    'url'         => 'https://jimeecosmetics.com',
    'logo'        => content_url( '/uploads/logo-header-retina-600.png' ),
    'image'       => content_url( '/uploads/logo-header-retina-600.png' ),
    'description' => 'Boutique en ligne multi-marques de cosmétiques en Algérie. Soins visage, cheveux, corps, maquillage et plus.',
    'telephone'   => '+213550922274',
    'email'       => 'contact@jimeecosmetics.com',
    'priceRange'  => '$$',
    'currenciesAccepted' => 'DZD',
    'address'     => [
        '@type'           => 'PostalAddress',
        'streetAddress'   => '02, Rue Allaoua AEK "La Croix"',
        'addressLocality' => 'Kouba',
        'addressRegion'   => 'Alger',
        'addressCountry'  => 'DZ',
    ],
    'geo' => [
        '@type'     => 'GeoCoordinates',
        'latitude'  => 36.7246,
        'longitude' => 3.0522,
    ],
    'openingHoursSpecification' => [
        [
            '@type'     => 'OpeningHoursSpecification',
            'dayOfWeek' => [ 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Saturday' ],
            'opens'     => '10:00',
            'closes'    => '20:00',
        ],
    ],
    'sameAs' => [
        'https://www.instagram.com/jimeecosmeticsshop',
        'https://www.tiktok.com/@jimeecosmetics',
        'https://www.facebook.com/jimmycosmetics',
    ],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
?>
</script>

<?php get_footer(); ?>
