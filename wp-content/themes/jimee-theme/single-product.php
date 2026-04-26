<?php
/**
 * Single product page.
 * Replaces WooCommerce default single-product.php.
 */

get_header();

while ( have_posts() ) : the_post();

global $product;
if ( ! $product || ! is_a( $product, 'WC_Product' ) ) {
    $product = wc_get_product( get_the_ID() );
}
if ( ! $product ) { get_footer(); return; }

$product_id = $product->get_id();
$brand_terms = wp_get_post_terms( $product_id, 'product_brand' );
$brand       = ! empty( $brand_terms ) ? $brand_terms[0]->name : '';
$brand_link  = ! empty( $brand_terms ) ? get_term_link( $brand_terms[0] ) : '';
$brand_desc  = ! empty( $brand_terms ) ? term_description( $brand_terms[0]->term_id ) : '';

$label_terms = wp_get_post_terms( $product_id, 'product_label', [ 'fields' => 'slugs' ] );
if ( is_wp_error( $label_terms ) ) $label_terms = [];
$has_labels = ! empty( $label_terms );

$cats = wp_get_post_terms( $product_id, 'product_cat' );
$main_cat = ! empty( $cats ) ? $cats[0] : null;

$images    = $product->get_gallery_image_ids();
$main_img  = get_the_post_thumbnail_url( $product_id, 'large' );
if ( ! $main_img ) $main_img = wc_placeholder_img_src( 'large' );

$in_stock   = $product->is_in_stock();
$stock_qty  = $product->get_stock_quantity();
$featured   = $product->is_featured();
$is_new     = ( time() - get_post_time( 'U', false, $product_id ) ) < 30 * DAY_IN_SECONDS;
$price      = (float) $product->get_price();
$reg        = (float) $product->get_regular_price();
$sale       = (float) $product->get_sale_price();
// Variable products: fallback to meta
if ( ! $reg && $product->is_type( 'variable' ) ) {
    $reg  = (float) get_post_meta( $product_id, '_regular_price', true );
    $sale = (float) get_post_meta( $product_id, '_sale_price', true );
}
$on_sale = ( $sale > 0 && $reg > 0 && $sale < $reg );
$rating     = (float) $product->get_average_rating();
$reviews    = (int)   $product->get_review_count();
$short_desc = $product->get_short_description();
$long_desc  = $product->get_description();

// Brand logo
$brand_logo = '';
if ( ! empty( $brand_terms ) ) {
    $brand_thumb_id = get_term_meta( $brand_terms[0]->term_id, 'thumbnail_id', true );
    if ( $brand_thumb_id ) {
        $brand_logo = wp_get_attachment_image_url( $brand_thumb_id, 'medium' );
    }
}
?>

<!-- BREADCRUMB -->
<div class="product-hero-wrapper">
    <nav class="breadcrumb" aria-label="Fil d'Ariane">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Accueil</a>
        <span class="sep"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></span>
        <?php if ( $main_cat ) : ?>
            <a href="<?php echo esc_url( get_term_link( $main_cat ) ); ?>"><?php echo esc_html( $main_cat->name ); ?></a>
            <span class="sep"><svg width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></span>
        <?php endif; ?>
        <span class="current"><?php the_title(); ?></span>
    </nav>
    <?php
    $bc_items = [ [ 'name' => 'Accueil', 'url' => home_url( '/' ) ] ];
    if ( $main_cat ) {
        $bc_items[] = [ 'name' => $main_cat->name, 'url' => get_term_link( $main_cat ) ];
    }
    $bc_items[] = [ 'name' => get_the_title() ];
    jimee_breadcrumb_jsonld( $bc_items );
    ?>

    <!-- PRODUCT HERO -->
    <div class="product-hero">
        <!-- GALLERY -->
        <div class="gallery">
            <div class="gallery-main" id="galleryMain">
                <!-- Tags -->
                <div class="gallery-tags">
                    <?php if ( $on_sale ) : ?><span class="gallery-tag promo">Promo</span><?php endif; ?>
                    <?php if ( $featured ) : ?><span class="gallery-tag best">Best-seller</span><?php endif; ?>
                    <?php if ( $is_new ) : ?><span class="gallery-tag new">Nouveau</span><?php endif; ?>
                </div>
                <img src="<?php echo esc_url( $main_img ); ?>" alt="<?php the_title_attribute(); ?>" id="mainImage">
            </div>
            <?php if ( ! empty( $images ) ) : ?>
            <div class="gallery-thumbs">
                <div class="gallery-thumb active" onclick="changeImage(this, '<?php echo esc_url( $main_img ); ?>')">
                    <img src="<?php echo esc_url( get_the_post_thumbnail_url( $product_id, 'thumbnail' ) ); ?>" alt="<?php the_title_attribute(); ?>">
                </div>
                <?php foreach ( $images as $img_id ) :
                    $thumb_url = wp_get_attachment_image_url( $img_id, 'thumbnail' );
                    $full_url  = wp_get_attachment_image_url( $img_id, 'large' );
                    if ( ! $thumb_url ) continue;
                ?>
                <div class="gallery-thumb" onclick="changeImage(this, '<?php echo esc_url( $full_url ); ?>')">
                    <img src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( get_post_meta( $img_id, '_wp_attachment_image_alt', true ) ); ?>">
                </div>
                <?php endforeach; ?>
            </div>
            <!-- Gallery dots (mobile swipe) -->
            <div class="gallery-dots">
                <button class="gallery-dot active" data-index="0" aria-label="Image 1"></button>
                <?php $dot_i = 1; foreach ( $images as $img_id ) :
                    if ( ! wp_get_attachment_image_url( $img_id, 'thumbnail' ) ) continue;
                ?>
                <button class="gallery-dot" data-index="<?php echo $dot_i; ?>" aria-label="Image <?php echo $dot_i + 1; ?>"></button>
                <?php $dot_i++; endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- PRODUCT DETAIL -->
        <div class="product-detail">
            <?php if ( $brand ) : ?>
                <a href="<?php echo esc_url( $brand_link ); ?>" class="pd-brand"><?php echo esc_html( strtoupper( $brand ) ); ?></a>
            <?php endif; ?>

            <h1 class="pd-name"><?php the_title(); ?></h1>

            <?php if ( $rating > 0 ) : ?>
            <div class="pd-rating">
                <span class="pd-stars"><?php echo jimee_render_stars( $rating ); ?></span>
                <span class="pd-rating-num"><?php echo number_format( $rating, 1 ); ?></span>
                <a href="#reviews" class="pd-rating-link"><?php echo $reviews; ?> avis</a>
            </div>
            <?php endif; ?>

            <div class="pd-price">
                <?php if ( $on_sale && $sale > 0 ) : ?>
                    <span class="pd-price-current"><?php echo number_format( $sale, 0, ',', ' ' ); ?> DA</span>
                    <span class="pd-price-old"><?php echo number_format( $reg, 0, ',', ' ' ); ?> DA</span>
                    <?php if ( $reg > 0 ) : ?>
                        <span class="pd-price-badge">-<?php echo round( ( 1 - $sale / $reg ) * 100 ); ?>%</span>
                    <?php endif; ?>
                <?php else : ?>
                    <span class="pd-price-current"><?php echo number_format( $price, 0, ',', ' ' ); ?> DA</span>
                <?php endif; ?>
            </div>

            <?php // Labels (static if 4 or less, infinite scroll if 5+)
            if ( $has_labels ) :
                $label_config = jimee_label_config();
                $label_html = '';
                $label_count = 0;
                foreach ( $label_terms as $slug ) :
                    if ( ! isset( $label_config[ $slug ] ) ) continue;
                    $lc = $label_config[ $slug ];
                    $icon_url = JIMEE_URI . '/assets/icons/' . $lc['icon'] . '?v=' . JIMEE_VERSION;
                    $label_html .= '<div class="pd-label-card"><img class="pd-label-icon" src="' . esc_url( $icon_url ) . '" alt="' . esc_attr( $lc['name'] ) . '"></div>';
                    $label_count++;
                endforeach;
                $needs_slider = $label_count > 4;
            ?>
                <div class="pd-labels-wrapper <?php echo $needs_slider ? 'is-slider' : 'is-static'; ?>">
                    <div class="pd-labels-slider">
                        <?php echo $label_html; ?>
                        <?php if ( $needs_slider ) echo $label_html; ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php
            // ── Product attributes as pills ──
            $raw_attrs = get_post_meta( $product_id, '_product_attributes', true );
            if ( is_array( $raw_attrs ) ) :
                foreach ( $raw_attrs as $key => $attr_data ) :
                    // Skip variation attributes (handled by swatches below)
                    if ( ! empty( $attr_data['is_variation'] ) ) continue;
                    $attr_label = '';
                    $values = array();
                    if ( ! empty( $attr_data['is_taxonomy'] ) ) {
                        $tax_name = $attr_data['name'];
                        $terms = wp_get_post_terms( $product_id, $tax_name );
                        if ( ! empty( $terms ) && ! is_wp_error( $terms ) ) {
                            $attr_label = wc_attribute_label( $tax_name );
                            $values = wp_list_pluck( $terms, 'name' );
                        }
                    } else {
                        if ( ! empty( $attr_data['value'] ) ) {
                            $attr_label = $attr_data['name'];
                            $values = array_map( 'trim', preg_split( '/[|,]/', $attr_data['value'] ) );
                        }
                    }
                    if ( empty( $values ) ) continue;
                    $attr_label = ucfirst( strtolower( trim( $attr_label ) ) );
                    $first_val = $values[0];
            ?>
                <div class="pd-contenance">
                    <div class="pd-contenance-label"><?php echo esc_html( $attr_label ); ?> <span>&mdash; <?php echo esc_html( $first_val ); ?></span></div>
                    <?php if ( count( $values ) > 1 ) : ?>
                    <div class="pd-contenance-options">
                        <?php foreach ( $values as $i => $val ) : ?>
                            <button class="pd-contenance-pill<?php echo $i === 0 ? ' active' : ''; ?>" onclick="this.parentElement.querySelectorAll('.pd-contenance-pill').forEach(function(p){p.classList.remove('active')});this.classList.add('active');this.closest('.pd-contenance').querySelector('.pd-contenance-label span').textContent='— <?php echo esc_js( $val ); ?>'"><?php echo esc_html( $val ); ?></button>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; endif; ?>

            <?php // ── Variable product: shade/color swatches ──
            if ( $product->is_type( 'variable' ) ) :
                $variations = $product->get_available_variations();
                $var_attrs  = $product->get_variation_attributes();
                foreach ( $var_attrs as $tax => $opts ) :
                    $is_color = ( strpos( $tax, 'teinte' ) !== false || strpos( $tax, 'couleur' ) !== false );
            ?>
                <div class="pd-variations" data-attribute="attribute_<?php echo esc_attr( $tax ); ?>">
                    <div class="pd-teinte-label"><?php echo esc_html( wc_attribute_label( $tax ) ); ?> <span id="varLabel">&mdash;</span></div>
                    <div class="pd-swatches">
                        <?php foreach ( $opts as $opt ) :
                            $t = get_term_by( 'slug', $opt, $tax );
                            $tname = $t ? $t->name : $opt;
                            $hex   = $t ? get_term_meta( $t->term_id, 'color_hex', true ) : '';
                            $vd    = null;
                            foreach ( $variations as $v ) {
                                if ( ( $v['attributes'][ 'attribute_' . $tax ] ?? '' ) === $opt ) { $vd = $v; break; }
                            }
                            if ( ! $vd ) continue;
                        ?>
                        <button class="pd-swatch<?php echo ( $is_color && $hex ) ? ' pd-swatch-color' : ''; ?>"
                            data-vid="<?php echo esc_attr( $vd['variation_id'] ); ?>"
                            data-val="<?php echo esc_attr( $opt ); ?>"
                            data-name="<?php echo esc_attr( $tname ); ?>"
                            data-img="<?php echo esc_url( $vd['image']['url'] ?? '' ); ?>"
                            data-price="<?php echo esc_attr( $vd['display_price'] ); ?>"
                            data-reg="<?php echo esc_attr( $vd['display_regular_price'] ); ?>"
                            <?php if ( $is_color && $hex ) : ?>style="background:<?php echo esc_attr( $hex ); ?>"<?php endif; ?>
                            title="<?php echo esc_attr( $tname ); ?>">
                            <?php if ( ! $is_color || ! $hex ) echo esc_html( $tname ); ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; endif; ?>

            <?php // Stock urgency — only show when stock is genuinely low (≤5), hide default bulk value (12)
            if ( $in_stock && $stock_qty !== null && $stock_qty > 0 && $stock_qty <= 10 ) : ?>
                <div class="stock-urgency">
                    <div class="stock-urgency-text"><span class="stock-urgency-dot"></span> Plus que <?php echo (int) $stock_qty; ?> en stock</div>
                    <div class="stock-bar"><div class="stock-bar-fill" style="width:<?php echo min( 100, max( 10, $stock_qty * 20 ) ); ?>%"></div></div>
                </div>
            <?php elseif ( $in_stock ) : ?>
                <div class="stock-urgency stock-ok">
                    <div class="stock-urgency-text"><span class="stock-urgency-dot"></span> En stock</div>
                    <div class="stock-bar"><div class="stock-bar-fill" style="width:100%"></div></div>
                </div>
            <?php endif; ?>

            <?php if ( $short_desc ) : ?>
                <div class="pd-desc"><?php echo wp_kses_post( $short_desc ); ?></div>
            <?php endif; ?>

            <?php // ── BUNDLE CROSS-SELLS ──
            $crosssell_ids = $product->get_cross_sell_ids();
            if ( ! empty( $crosssell_ids ) ) : ?>
            <div class="pd-bundle" id="pdBundle">
                <div class="pd-bundle-header">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
                    Compl&eacute;tez votre routine
                </div>
                <div class="pd-bundle-item">
                    <?php
                    $bundle_main_id = $product_id;
                    if ( $product->is_type( 'variable' ) ) {
                        $variations = $product->get_available_variations();
                        if ( ! empty( $variations ) ) $bundle_main_id = $variations[0]['variation_id'];
                    }
                    ?>
                    <input type="checkbox" class="pd-bundle-check" checked disabled data-price="<?php echo esc_attr( $price ); ?>" data-id="<?php echo esc_attr( $bundle_main_id ); ?>" data-parent-id="<?php echo esc_attr( $product_id ); ?>" data-type="<?php echo $product->is_type('variable') ? 'variation' : 'simple'; ?>">
                    <div class="pd-bundle-thumb"><?php echo get_the_post_thumbnail( $product_id, 'thumbnail' ); ?></div>
                    <div class="pd-bundle-info">
                        <div class="pd-bundle-brand"><?php echo esc_html( $brand ); ?></div>
                        <div class="pd-bundle-name"><?php the_title(); ?></div>
                    </div>
                    <div class="pd-bundle-price"><?php echo number_format( $price, 0, ',', ' ' ); ?> DA</div>
                </div>
                <?php foreach ( array_slice( $crosssell_ids, 0, 3 ) as $cs_id ) :
                    $cs = wc_get_product( $cs_id );
                    if ( ! $cs || ! $cs->is_in_stock() ) continue;
                    $cs_brand_terms = wp_get_post_terms( $cs_id, 'product_brand' );
                    $cs_brand = ! empty( $cs_brand_terms ) ? $cs_brand_terms[0]->name : '';
                    $cs_price = (float) $cs->get_price();
                ?>
                <div class="pd-bundle-item">
                    <input type="checkbox" class="pd-bundle-check" checked data-price="<?php echo esc_attr( $cs_price ); ?>" data-id="<?php echo esc_attr( $cs_id ); ?>" onchange="updateBundleTotal()">
                    <div class="pd-bundle-thumb"><?php echo get_the_post_thumbnail( $cs_id, 'thumbnail' ); ?></div>
                    <div class="pd-bundle-info">
                        <div class="pd-bundle-brand"><?php echo esc_html( $cs_brand ); ?></div>
                        <div class="pd-bundle-name"><?php echo esc_html( $cs->get_name() ); ?></div>
                    </div>
                    <div class="pd-bundle-price"><?php echo number_format( $cs_price, 0, ',', ' ' ); ?> DA</div>
                </div>
                <?php endforeach; ?>
                <div class="pd-bundle-footer">
                    <div>
                        <div class="pd-bundle-total-label">Total pour les produits s&eacute;lectionn&eacute;s</div>
                        <div class="pd-bundle-total-price" id="bundleTotal"></div>
                    </div>
                    <button class="pd-bundle-add" id="bundleAddBtn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:16px;height:16px"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
                        Ajouter la s&eacute;lection
                    </button>
                </div>
            </div>
            <?php endif; ?>

            <!-- CTA CAPSULE -->
            <?php if ( $in_stock ) : ?>
                <div class="cta-capsule" id="ctaCapsule">
                    <div class="cta-qty">
                        <button type="button" class="cta-qty-btn qty-minus" aria-label="Moins">&#8722;</button>
                        <span class="cta-qty-val" id="qtyVal">1</span>
                        <button type="button" class="cta-qty-btn qty-plus" aria-label="Plus">+</button>
                    </div>
                    <div class="cta-atc" id="mainAddToCart" data-product-id="<?php echo esc_attr( $product_id ); ?>" data-product-type="<?php echo esc_attr( $product->get_type() ); ?>">
                        <span class="cta-atc-main">Ajouter au panier</span>
                    </div>
                    <div class="cta-wish pd-wishlist-btn" data-product-id="<?php echo esc_attr( $product_id ); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
                    </div>
                </div>
            <?php else : ?>
                <div class="bis-container">
                    <div class="bis-badge">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="18" height="18"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                        Rupture de stock
                    </div>
                    <div class="bis-card">
                        <h4 class="bis-title">Soyez le premier informé !</h4>
                        <p class="bis-desc">Recevez un email dès que ce produit sera de retour en stock.</p>

                        <div class="bis-form" id="bisForm">
                            <div class="bis-input-row">
                                <input type="email" id="bisEmail" placeholder="Votre adresse email" value="<?php echo is_user_logged_in() ? esc_attr( wp_get_current_user()->user_email ) : ''; ?>" required>
                                <button class="bis-btn" id="bisSubscribe" data-product-id="<?php echo esc_attr( $product_id ); ?>">
                                    Me notifier
                                </button>
                            </div>
                            <?php if ( ! is_user_logged_in() ) : ?>
                            <label class="bis-create-account">
                                <input type="checkbox" id="bisCreateAccount">
                                <span>Creer un compte pour suivre mes notifications</span>
                            </label>
                            <div class="bis-account-fields" id="bisAccountFields" style="display:none">
                                <div class="bis-field-row">
                                    <input type="text" id="bisFirstName" placeholder="Prenom">
                                    <input type="text" id="bisLastName" placeholder="Nom">
                                </div>
                                <input type="tel" id="bisPhone" placeholder="0X XX XX XX XX">
                                <input type="password" id="bisPassword" placeholder="Mot de passe (min. 8 caracteres)">
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="bis-notice" id="bisNotice" style="display:none"></div>
                    </div>
                </div>
                <?php wp_nonce_field( 'jimee_stock_notify', 'bis_nonce' ); ?>
                <script>
                (function(){
                    var cb = document.getElementById('bisCreateAccount');
                    var fields = document.getElementById('bisAccountFields');
                    if(cb && fields) {
                        cb.addEventListener('change', function(){ fields.style.display = this.checked ? 'flex' : 'none'; });
                    }

                    document.getElementById('bisSubscribe').addEventListener('click', function(){
                        var btn = this;
                        var productId = btn.dataset.productId;
                        var email = document.getElementById('bisEmail').value.trim();
                        var createAccount = cb ? cb.checked : false;
                        var notice = document.getElementById('bisNotice');

                        if(!email || email.indexOf('@') === -1) {
                            notice.style.display = 'block';
                            notice.className = 'bis-notice bis-notice--error';
                            notice.textContent = 'Veuillez entrer un email valide.';
                            return;
                        }

                        if(createAccount) {
                            var pw = document.getElementById('bisPassword').value;
                            if(!pw || pw.length < 8) {
                                notice.style.display = 'block';
                                notice.className = 'bis-notice bis-notice--error';
                                notice.textContent = 'Le mot de passe doit contenir au moins 8 caracteres.';
                                return;
                            }
                        }

                        btn.disabled = true;
                        btn.textContent = 'Inscription...';

                        var data = new FormData();
                        data.append('action', 'jimee_stock_notify');
                        data.append('security', document.querySelector('[name="bis_nonce"]').value);
                        data.append('product_id', productId);
                        data.append('email', email);
                        if(createAccount) {
                            data.append('create_account', '1');
                            data.append('first_name', (document.getElementById('bisFirstName') || {}).value || '');
                            data.append('last_name', (document.getElementById('bisLastName') || {}).value || '');
                            data.append('phone', (document.getElementById('bisPhone') || {}).value || '');
                            data.append('password', document.getElementById('bisPassword').value);
                        }

                        fetch('<?php echo admin_url("admin-ajax.php"); ?>', { method:'POST', body:data })
                            .then(function(r){ return r.json(); })
                            .then(function(res){
                                notice.style.display = 'block';
                                if(res.success){
                                    notice.className = 'bis-notice bis-notice--success';
                                    notice.textContent = res.data;
                                    btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><polyline points="20 6 9 17 4 12"/></svg> Inscrit !';
                                    var form = document.getElementById('bisForm');
                                    if(form) form.style.display = 'none';
                                } else {
                                    notice.className = 'bis-notice bis-notice--error';
                                    notice.textContent = res.data;
                                    btn.disabled = false;
                                    btn.textContent = 'Me notifier';
                                }
                            });
                    });
                })();
                </script>
            <?php endif; ?>

            <!-- REASSURANCE -->
            <div class="pd-reassurance">
                <div class="pd-reassurance-track">
                    <div class="pd-reassurance-item"><div class="pd-reassurance-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 3v5a2 2 0 0 1-2 2h-1"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></div><span>Livraison 2-5 jours</span></div>
                    <div class="pd-reassurance-item"><div class="pd-reassurance-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg></div><span>100% authentique</span></div>
                    <div class="pd-reassurance-item"><div class="pd-reassurance-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div><span>Paiement CIB / Dahabia</span></div>
                    <div class="pd-reassurance-item"><div class="pd-reassurance-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4l3 3v5a2 2 0 0 1-2 2h-1"/><circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/></svg></div><span>Livraison 2-5 jours</span></div>
                    <div class="pd-reassurance-item"><div class="pd-reassurance-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg></div><span>100% authentique</span></div>
                    <div class="pd-reassurance-item"><div class="pd-reassurance-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div><span>Paiement CIB / Dahabia</span></div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- TABS -->
<?php
// Clean up description
$clean_desc = $long_desc;
if ( $clean_desc ) {
    $clean_desc = preg_replace( '/(<p>)\s*<strong>[A-ZÀÂÉÈÊËÏÎÔÙÛÜÇ0-9\s\+\-\.\,\/\(\)]+<\/strong>\s*/u', '$1', $clean_desc );
    $clean_desc = preg_replace( '/^(\s*<p>\s*[A-ZÀÂÉÈÊËÏÎÔÙÛÜÇ0-9\s\+\-\.\,\/\(\)]{10,}\s*<\/p>\s*)+/u', '', $clean_desc );
    $clean_desc = preg_replace_callback( '/<p>\s*([a-zàâéèêëïîôùûüç])/u', function( $m ) {
        return '<p>' . mb_strtoupper( $m[1] );
    }, $clean_desc, 1 );
    $clean_desc = preg_replace( '/^(\s*<p>\s*<\/p>\s*)+/i', '', $clean_desc );
    $clean_desc = trim( $clean_desc );
}
$usage_tips = jimee_get_usage_tips( $product );

// Extract INCI list: prefer custom meta, fallback to regex
$inci_content = '';
$custom_inci = get_post_meta( $product_id, '_jimee_ingredients', true );
if ( $custom_inci ) {
    $inci_content = '<p style="font-size:13px;color:#777;line-height:1.8">' . esc_html( $custom_inci ) . '</p>';
} elseif ( $clean_desc && preg_match( '/((?:aqua|water|snail|centella|niacinamide|glycerin|butylene|dimethicone)[a-z0-9\s,\.\(\)\/\-\*%\[\]\+:]+)/i', strip_tags( $clean_desc ), $inci_match ) ) {
    $inci_content = '<p style="font-size:13px;color:#777;line-height:1.8">' . esc_html( trim( $inci_match[0] ) ) . '</p>';
}

// Usage tips: prefer custom meta, fallback to helper
$custom_usage = get_post_meta( $product_id, '_jimee_usage', true );
if ( $custom_usage ) $usage_tips = wp_kses_post( $custom_usage );

// Build tabs array
$tabs = array();
if ( $clean_desc ) $tabs['desc'] = array( 'label' => 'Description', 'content' => wp_kses_post( $clean_desc ) );
if ( $inci_content ) $tabs['ingredients'] = array( 'label' => 'Ingr&eacute;dients', 'content' => $inci_content );
if ( $usage_tips ) $tabs['usage'] = array( 'label' => "Conseils d'utilisation", 'content' => $usage_tips );
$tabs['reviews'] = array( 'label' => 'Avis (' . $reviews . ')', 'content' => '' );

if ( ! empty( $tabs ) ) :
$first = true;
?>
<div class="tabs-section" style="max-width:1440px;margin:40px auto 0;padding:0 32px">
    <div class="tabs-nav">
        <?php foreach ( $tabs as $key => $tab ) : ?>
            <button class="tab-btn<?php echo $first ? ' active' : ''; ?>" onclick="jimeeTab('<?php echo $key; ?>')"><?php echo esc_html( $tab['label'] ); ?></button>
        <?php $first = false; endforeach; ?>
    </div>
    <?php $first = true; foreach ( $tabs as $key => $tab ) : ?>
    <div class="tab-panel<?php echo $first ? ' active' : ''; ?>" id="tab-<?php echo $key; ?>">
        <?php if ( $key === 'reviews' ) : ?>
            <?php if ( $reviews > 0 ) : ?>
                <?php comments_template(); ?>
            <?php else : ?>
                <div class="reviews-empty">
                    <div class="reviews-empty-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div>
                    <h3 class="reviews-empty-title">Aucun avis pour le moment</h3>
                    <?php if ( ! is_user_logged_in() ) : ?>
                        <p class="reviews-empty-text"><a href="<?php echo esc_url( wc_get_page_permalink( 'myaccount' ) ); ?>" class="reviews-empty-link">Connectez-vous</a> pour laisser un avis.</p>
                    <?php elseif ( ! wc_customer_bought_product( '', get_current_user_id(), $product->get_id() ) ) : ?>
                        <p class="reviews-empty-text">Achetez ce produit pour pouvoir laisser un avis.</p>
                    <?php else : ?>
                        <p class="reviews-empty-text">Soyez la premi&egrave;re personne &agrave; partager votre exp&eacute;rience.</p>
                        <a href="#review_form" class="reviews-empty-cta">Donner mon avis</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php else : ?>
            <div class="tab-content"><?php echo $tab['content']; ?></div>
        <?php endif; ?>
    </div>
    <?php $first = false; endforeach; ?>
</div>
<?php endif; ?>

<!-- COMPLETEZ VOTRE ROUTINE (same category products) -->
<?php
$routine_ids = array();
if ( $main_cat ) {
    $routine_args = array(
        'post_type'      => 'product',
        'post_status'    => 'publish',
        'posts_per_page' => 10,
        'post__not_in'   => array( $product_id ),
        'tax_query'      => array( array( 'taxonomy' => 'product_cat', 'field' => 'term_id', 'terms' => $main_cat->term_id ) ),
        'meta_key'       => '_thumbnail_id',
        'fields'         => 'ids',
        'orderby'        => 'rand',
    );
    $routine_ids = get_posts( $routine_args );
}
if ( ! empty( $routine_ids ) ) :
?>
<div class="scroll-section reveal">
    <div class="section-header">
        <div class="section-eyebrow">Soin complet</div>
        <h2 class="section-title">Compl&eacute;tez votre <em>routine</em></h2>
    </div>
    <div class="scroll-track-wrapper">
        <button class="scroll-arrow left hidden" data-dir="-1" aria-label="Pr&eacute;c&eacute;dent"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button>
        <div class="scroll-track">
            <?php foreach ( $routine_ids as $uid ) :
                echo jimee_render_product_card( $uid );
            endforeach; ?>
        </div>
        <button class="scroll-arrow right" data-dir="1" aria-label="Suivant"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></button>
    </div>
</div>
<?php endif; ?>

<!-- BLOC MARQUE DARK -->
<?php if ( $brand && $brand_desc ) : ?>
<div class="brand-block-dark reveal">
    <div class="brand-block-card">
        <?php if ( $brand_logo ) : ?>
        <div class="brand-block-img">
            <img src="<?php echo esc_url( $brand_logo ); ?>" alt="<?php echo esc_attr( $brand ); ?>" style="mix-blend-mode:multiply">
        </div>
        <?php endif; ?>
        <div class="brand-block-info">
            <div class="brand-block-name"><?php echo esc_html( $brand ); ?></div>
            <p class="brand-block-desc"><?php echo wp_kses_post( wp_trim_words( wp_strip_all_tags( $brand_desc ), 50 ) ); ?></p>
            <a href="<?php echo esc_url( $brand_link ); ?>" class="brand-block-cta">D&eacute;couvrir la marque <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14M12 5l7 7-7 7"/></svg></a>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- VOUS AIMEREZ AUSSI (related products) -->
<?php
$related_ids = wc_get_related_products( $product_id, 8 );
if ( ! empty( $related_ids ) ) :
?>
<div class="scroll-section reveal">
    <div class="section-header">
        <div class="section-eyebrow">Recommandations</div>
        <h2 class="section-title">Vous aimerez <em>aussi</em></h2>
    </div>
    <div class="scroll-track-wrapper">
        <button class="scroll-arrow left hidden" data-dir="-1" aria-label="Pr&eacute;c&eacute;dent"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button>
        <div class="scroll-track">
            <?php foreach ( $related_ids as $rid ) :
                echo jimee_render_product_card( $rid );
            endforeach; ?>
        </div>
        <button class="scroll-arrow right" data-dir="1" aria-label="Suivant"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></button>
    </div>
</div>
<?php endif; ?>

<!-- EXPLORER PAR CATÉGORIE -->
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
    'accesoires'  => 'accessoires-beaute-outils-coiffure.webp',
];
$img_base = JIMEE_URI . '/assets/img/';
$sp_cats = get_terms( array( 'taxonomy' => 'product_cat', 'parent' => 0, 'hide_empty' => true, 'exclude' => jimee_excluded_cats(), 'number' => 10 ) );
if ( ! empty( $sp_cats ) && ! is_wp_error( $sp_cats ) ) : ?>
<section class="cat-explore reveal">
    <div class="section-header" style="padding:0 32px;text-align:center;margin-bottom:32px">
        <div class="section-eyebrow">Explorer par cat&eacute;gorie</div>
        <h2 class="section-title">Nos <em>cat&eacute;gories</em></h2>
    </div>
    <div class="categories-wrapper">
        <div class="categories-track">
            <?php for ( $loop = 0; $loop < 2; $loop++ ) :
                foreach ( $sp_cats as $spc ) :
                    $photo = isset( $cat_photos[ $spc->slug ] ) ? $img_base . $cat_photos[ $spc->slug ] : '';
            ?>
            <a href="<?php echo esc_url( get_term_link( $spc ) ); ?>" class="category-circle">
                <div class="category-circle-img">
                    <?php if ( $photo ) : ?>
                        <img src="<?php echo esc_url( $photo ); ?>" alt="<?php echo esc_attr( $spc->name ); ?>" loading="lazy">
                    <?php else : ?>
                        <div style="width:100%;height:100%;background:var(--warm-bg);display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:600;color:#999"><?php echo esc_html( mb_substr( $spc->name, 0, 3 ) ); ?></div>
                    <?php endif; ?>
                </div>
                <div class="category-circle-name"><?php echo esc_html( $spc->name ); ?></div>
            </a>
            <?php endforeach; endfor; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<!-- STICKY ADD TO CART BAR -->
<?php if ( $in_stock ) : ?>
<div class="sticky-atc" id="stickyAtc">
    <div class="sticky-atc-inner">
        <div class="sticky-atc-info">
            <span class="sticky-atc-name"><?php the_title(); ?></span>
            <span class="sticky-atc-price">
                <?php if ( $on_sale && $sale > 0 ) : ?>
                    <?php echo number_format( $sale, 0, ',', ' ' ); ?> DA
                <?php else : ?>
                    <?php echo number_format( $price, 0, ',', ' ' ); ?> DA
                <?php endif; ?>
            </span>
        </div>
        <button class="sticky-atc-btn" data-product-id="<?php echo esc_attr( $product_id ); ?>">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 0 1-8 0"/></svg>
            Ajouter au panier
        </button>
    </div>
</div>
<?php endif; ?>

<!-- JSON-LD Product Schema -->
<script type="application/ld+json">
<?php
$schema = array(
    '@context'    => 'https://schema.org',
    '@type'       => 'Product',
    'name'        => get_the_title(),
    'image'       => $main_img,
    'description' => wp_strip_all_tags( $short_desc ?: $long_desc ),
    'sku'         => $product->get_sku() ?: (string) $product_id,
    'url'         => get_permalink(),
    'offers'      => array(
        '@type'           => 'Offer',
        'price'           => $price,
        'priceCurrency'   => 'DZD',
        'availability'    => $in_stock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
        'seller'          => array(
            '@type' => 'Organization',
            'name'  => 'Jimee Cosmetics',
        ),
    ),
);
if ( $brand ) {
    $schema['brand'] = array( '@type' => 'Brand', 'name' => $brand );
}
if ( $main_cat ) {
    $schema['category'] = $main_cat->name;
}
if ( $rating > 0 && $reviews > 0 ) {
    $schema['aggregateRating'] = array(
        '@type'       => 'AggregateRating',
        'ratingValue' => number_format( $rating, 1 ),
        'reviewCount' => $reviews,
        'bestRating'  => '5',
        'worstRating' => '1',
    );
}
if ( ! empty( $images ) ) {
    $schema['image'] = array( $main_img );
    foreach ( $images as $gid ) {
        $gurl = wp_get_attachment_image_url( $gid, 'large' );
        if ( $gurl ) $schema['image'][] = $gurl;
    }
}
echo wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );
?>
</script>

<?php endwhile; get_footer(); ?>
