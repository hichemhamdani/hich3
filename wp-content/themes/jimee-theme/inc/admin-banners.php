<?php
/**
 * Admin — Page d'options "Bannières Homepage".
 * Accessible au rôle Gérant Jimee (manage_woocommerce) + Technique.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
   REGISTER OPTIONS PAGE
   ============================================================ */

add_action( 'admin_menu', 'jimee_banners_menu' );
function jimee_banners_menu() {
    add_menu_page(
        'Bannières Homepage',
        'Bannières',
        'manage_woocommerce',
        'jimee-banners',
        'jimee_banners_page',
        'dashicons-images-alt2',
        58 // After WooCommerce
    );
}

/* ============================================================
   REGISTER SETTINGS
   ============================================================ */

add_action( 'admin_init', 'jimee_banners_register' );
function jimee_banners_register() {
    register_setting( 'jimee_banners', 'jimee_announcements' );
    register_setting( 'jimee_banners', 'jimee_hero_slides' );
    register_setting( 'jimee_banners', 'jimee_promo_banner' );
    register_setting( 'jimee_banners', 'jimee_double_banners' );
}

/* ============================================================
   DEFAULTS
   ============================================================ */

function jimee_default_announcements() {
    return [
        '-15% sur votre première commande avec le code JIMEE15',
        'Échantillons offerts dans chaque commande',
        'Livraison offerte dès 10 000 DA d\'achat',
        'Nouveau : découvrez notre gamme K-Beauty',
    ];
}

function jimee_default_hero_slides() {
    return [
        [
            'eyebrow' => 'Nouvelle collection',
            'title'   => 'Révélez votre <em>éclat</em> naturel',
            'desc'    => 'Découvrez nos soins formulés avec des ingrédients naturels pour sublimer votre beauté au quotidien.',
            'cta'     => 'Explorer la collection',
            'link'    => '/categorie-produit/visage/',
            'image'   => '',
        ],
        [
            'eyebrow' => 'Exclusivité',
            'title'   => 'La beauté <em>sans compromis</em>',
            'desc'    => 'Des formules haute performance, testées dermatologiquement, pour tous les types de peau.',
            'cta'     => 'Découvrir',
            'link'    => '/marques/',
            'image'   => '',
        ],
        [
            'eyebrow' => 'Tendances',
            'title'   => 'Le maquillage qui vous <em>ressemble</em>',
            'desc'    => 'Textures fondantes, couleurs vibrantes et tenue longue durée. La beauté réinventée.',
            'cta'     => 'Voir la sélection',
            'link'    => '/categorie-produit/maquillage/',
            'image'   => '',
        ],
    ];
}

function jimee_default_promo_banner() {
    return [
        'eyebrow' => 'Offre spéciale',
        'title'   => 'Le Bon Plan <em>Jimee</em>',
        'desc'    => 'Profitez de nos promotions exclusives sur une sélection de produits.',
        'cta'     => 'Voir les offres',
        'link'    => '/le-bon-plan-jimee/',
    ];
}

function jimee_default_double_banners() {
    return [
        [
            'eyebrow' => 'Pour Lui',
            'title'   => 'L\'univers <em>Homme</em>',
            'cta'     => 'Découvrir',
            'link'    => '/categorie-produit/homme/',
            'image'   => '',
        ],
        [
            'eyebrow' => 'Tendance',
            'title'   => 'La <em>K-Beauty</em> coréenne',
            'cta'     => 'Explorer',
            'link'    => '/categorie-produit/k-beauty/',
            'image'   => '',
        ],
    ];
}

/* ============================================================
   GET VALUES (with defaults fallback)
   ============================================================ */

function jimee_get_announcements() {
    $v = get_option( 'jimee_announcements' );
    return ! empty( $v ) ? array_filter( (array) $v ) : jimee_default_announcements();
}

function jimee_get_hero_slides() {
    $v = get_option( 'jimee_hero_slides' );
    return ! empty( $v ) ? (array) $v : jimee_default_hero_slides();
}

function jimee_get_promo_banner() {
    $v = get_option( 'jimee_promo_banner' );
    return ! empty( $v ) ? (array) $v : jimee_default_promo_banner();
}

function jimee_get_double_banners() {
    $v = get_option( 'jimee_double_banners' );
    return ! empty( $v ) ? (array) $v : jimee_default_double_banners();
}

/* ============================================================
   ADMIN PAGE RENDER
   ============================================================ */

function jimee_banners_page() {
    if ( ! current_user_can( 'manage_woocommerce' ) ) {
        wp_die( 'Accès refusé.' );
    }

    $announcements   = jimee_get_announcements();
    $hero_slides     = jimee_get_hero_slides();
    $promo           = jimee_get_promo_banner();
    $double          = jimee_get_double_banners();

    // Ensure we have at least 2 double banners
    while ( count( $double ) < 2 ) $double[] = [ 'eyebrow' => '', 'title' => '', 'cta' => '', 'link' => '', 'image' => '' ];
    ?>
    <div class="wrap jimee-banners-wrap">
        <h1 style="font-size:28px;font-weight:300;margin-bottom:24px">
            Bannières <strong>Homepage</strong>
        </h1>

        <?php settings_errors(); ?>

        <form method="post" action="options.php">
            <?php settings_fields( 'jimee_banners' ); ?>

            <!-- ── ANNONCES ── -->
            <div class="jimee-admin-card">
                <h2>Barre d'annonces</h2>
                <p class="description">Messages qui défilent en haut du site. Un par ligne.</p>
                <div id="jimee-announcements">
                    <?php foreach ( $announcements as $i => $msg ) : ?>
                    <div class="jimee-ann-row">
                        <input type="text" name="jimee_announcements[]" value="<?php echo esc_attr( $msg ); ?>" class="large-text" placeholder="Message d'annonce...">
                        <button type="button" class="button jimee-remove-row" title="Supprimer">&times;</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button" onclick="jimeeAddAnnouncement()">+ Ajouter un message</button>
            </div>

            <!-- ── HERO SLIDER ── -->
            <div class="jimee-admin-card">
                <h2>Hero Slider</h2>
                <p class="description">Les grandes bannières en haut de la page d'accueil.<br><span class="jimee-img-hint">Image : 1440 &times; 720 px minimum, format JPG ou WebP, paysage.</span></p>
                <div id="jimee-hero-slides">
                    <?php foreach ( $hero_slides as $i => $slide ) : ?>
                    <div class="jimee-slide-card">
                        <div class="jimee-slide-header">
                            <strong>Slide <?php echo $i + 1; ?></strong>
                            <button type="button" class="button-link jimee-remove-slide" style="color:#8B0000">&times; Supprimer</button>
                        </div>
                        <div class="jimee-fields-grid">
                            <div>
                                <label>Sur-titre</label>
                                <input type="text" name="jimee_hero_slides[<?php echo $i; ?>][eyebrow]" value="<?php echo esc_attr( $slide['eyebrow'] ?? '' ); ?>" class="regular-text">
                            </div>
                            <div>
                                <label>Titre <small>(utilisez &lt;em&gt; pour l'italique)</small></label>
                                <input type="text" name="jimee_hero_slides[<?php echo $i; ?>][title]" value="<?php echo esc_attr( $slide['title'] ?? '' ); ?>" class="large-text">
                            </div>
                            <div style="grid-column:1/-1">
                                <label>Description</label>
                                <textarea name="jimee_hero_slides[<?php echo $i; ?>][desc]" class="large-text" rows="2"><?php echo esc_textarea( $slide['desc'] ?? '' ); ?></textarea>
                            </div>
                            <div>
                                <label>Texte du bouton</label>
                                <input type="text" name="jimee_hero_slides[<?php echo $i; ?>][cta]" value="<?php echo esc_attr( $slide['cta'] ?? '' ); ?>" class="regular-text">
                            </div>
                            <div>
                                <label>Lien du bouton</label>
                                <input type="text" name="jimee_hero_slides[<?php echo $i; ?>][link]" value="<?php echo esc_attr( $slide['link'] ?? '' ); ?>" class="regular-text" placeholder="/categorie-produit/visage/">
                            </div>
                            <div style="grid-column:1/-1">
                                <label>Image de fond</label>
                                <div class="jimee-image-field">
                                    <input type="hidden" name="jimee_hero_slides[<?php echo $i; ?>][image]" value="<?php echo esc_attr( $slide['image'] ?? '' ); ?>" class="jimee-image-id">
                                    <?php $img_url = ! empty( $slide['image'] ) ? wp_get_attachment_image_url( $slide['image'], 'medium' ) : ''; ?>
                                    <div class="jimee-image-preview" <?php if ( ! $img_url ) echo 'style="display:none"'; ?>>
                                        <img src="<?php echo esc_url( $img_url ); ?>" alt="">
                                        <button type="button" class="jimee-image-remove">&times;</button>
                                    </div>
                                    <button type="button" class="button jimee-image-upload">Choisir une image</button>
                                    <span class="jimee-img-specs">1440 &times; 720 px &middot; JPG / WebP &middot; Paysage</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="button" onclick="jimeeAddSlide()">+ Ajouter un slide</button>
            </div>

            <!-- ── PROMO BANNER ── -->
            <div class="jimee-admin-card">
                <h2>Bannière Promo</h2>
                <p class="description">La bannière "Le Bon Plan Jimee" entre les produits. Pas d'image, uniquement du texte.</p>
                <div class="jimee-fields-grid">
                    <div>
                        <label>Sur-titre</label>
                        <input type="text" name="jimee_promo_banner[eyebrow]" value="<?php echo esc_attr( $promo['eyebrow'] ?? '' ); ?>" class="regular-text">
                    </div>
                    <div>
                        <label>Titre <small>(utilisez &lt;em&gt; pour l'italique)</small></label>
                        <input type="text" name="jimee_promo_banner[title]" value="<?php echo esc_attr( $promo['title'] ?? '' ); ?>" class="large-text">
                    </div>
                    <div style="grid-column:1/-1">
                        <label>Description</label>
                        <textarea name="jimee_promo_banner[desc]" class="large-text" rows="2"><?php echo esc_textarea( $promo['desc'] ?? '' ); ?></textarea>
                    </div>
                    <div>
                        <label>Texte du bouton</label>
                        <input type="text" name="jimee_promo_banner[cta]" value="<?php echo esc_attr( $promo['cta'] ?? '' ); ?>" class="regular-text">
                    </div>
                    <div>
                        <label>Lien</label>
                        <input type="text" name="jimee_promo_banner[link]" value="<?php echo esc_attr( $promo['link'] ?? '' ); ?>" class="regular-text">
                    </div>
                </div>
            </div>

            <!-- ── DOUBLE BANNER ── -->
            <div class="jimee-admin-card">
                <h2>Double Bannière</h2>
                <p class="description">Les deux cartes côte à côte (ex: Homme + K-Beauty).<br><span class="jimee-img-hint">Image : 720 &times; 480 px minimum, format JPG ou WebP, paysage.</span></p>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
                    <?php foreach ( $double as $j => $card ) : ?>
                    <div class="jimee-slide-card">
                        <strong>Carte <?php echo $j + 1; ?></strong>
                        <div class="jimee-fields-grid" style="grid-template-columns:1fr">
                            <div>
                                <label>Sur-titre</label>
                                <input type="text" name="jimee_double_banners[<?php echo $j; ?>][eyebrow]" value="<?php echo esc_attr( $card['eyebrow'] ?? '' ); ?>" class="regular-text">
                            </div>
                            <div>
                                <label>Titre</label>
                                <input type="text" name="jimee_double_banners[<?php echo $j; ?>][title]" value="<?php echo esc_attr( $card['title'] ?? '' ); ?>" class="regular-text">
                            </div>
                            <div>
                                <label>Texte du bouton</label>
                                <input type="text" name="jimee_double_banners[<?php echo $j; ?>][cta]" value="<?php echo esc_attr( $card['cta'] ?? '' ); ?>" class="regular-text">
                            </div>
                            <div>
                                <label>Lien</label>
                                <input type="text" name="jimee_double_banners[<?php echo $j; ?>][link]" value="<?php echo esc_attr( $card['link'] ?? '' ); ?>" class="regular-text">
                            </div>
                            <div>
                                <label>Image de fond</label>
                                <div class="jimee-image-field">
                                    <input type="hidden" name="jimee_double_banners[<?php echo $j; ?>][image]" value="<?php echo esc_attr( $card['image'] ?? '' ); ?>" class="jimee-image-id">
                                    <?php $dimg = ! empty( $card['image'] ) ? wp_get_attachment_image_url( $card['image'], 'medium' ) : ''; ?>
                                    <div class="jimee-image-preview" <?php if ( ! $dimg ) echo 'style="display:none"'; ?>>
                                        <img src="<?php echo esc_url( $dimg ); ?>" alt="">
                                        <button type="button" class="jimee-image-remove">&times;</button>
                                    </div>
                                    <button type="button" class="button jimee-image-upload">Choisir une image</button>
                                    <span class="jimee-img-specs">720 &times; 480 px &middot; JPG / WebP &middot; Paysage</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php submit_button( 'Enregistrer les bannières' ); ?>
        </form>
    </div>

    <style>
    .jimee-banners-wrap { max-width: 960px; }
    .jimee-admin-card {
        background: #fff; border: 1px solid #ddd; border-radius: 12px;
        padding: 24px 28px; margin-bottom: 20px;
    }
    .jimee-admin-card h2 { font-size: 18px; font-weight: 600; margin: 0 0 4px; padding: 0; }
    .jimee-admin-card .description { margin-bottom: 16px; }
    .jimee-fields-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px 16px; margin-top: 12px; }
    .jimee-fields-grid label { display: block; font-size: 12px; font-weight: 600; margin-bottom: 4px; }
    .jimee-fields-grid small { font-weight: 400; color: #999; }
    .jimee-slide-card {
        background: #f9f9f9; border: 1px solid #e8e4df; border-radius: 8px;
        padding: 16px 20px; margin-bottom: 12px;
    }
    .jimee-slide-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
    .jimee-ann-row { display: flex; gap: 8px; margin-bottom: 8px; }
    .jimee-ann-row input { flex: 1; }
    .jimee-remove-row {
        width: 36px; height: 36px; font-size: 18px; color: #999; border-color: #ddd;
        display: flex; align-items: center; justify-content: center; padding: 0;
    }
    .jimee-remove-row:hover { color: #8B0000; border-color: #8B0000; }

    /* Image uploader */
    .jimee-image-field { display: flex; align-items: center; gap: 12px; }
    .jimee-image-preview { position: relative; width: 120px; height: 70px; border-radius: 8px; overflow: hidden; }
    .jimee-image-preview img { width: 100%; height: 100%; object-fit: cover; }
    .jimee-image-remove {
        position: absolute; top: 4px; right: 4px;
        width: 22px; height: 22px; border-radius: 50%;
        background: rgba(0,0,0,.6); color: #fff; border: none; cursor: pointer;
        font-size: 14px; display: flex; align-items: center; justify-content: center;
    }
    .jimee-img-specs {
        display: block; font-size: 11px; color: #999; margin-top: 6px;
    }
    .jimee-img-hint {
        display: inline-block; font-size: 12px; color: #999; margin-top: 4px;
        background: #f9f9f9; padding: 4px 10px; border-radius: 4px; border: 1px dashed #ddd;
    }
    </style>

    <script>
    jQuery(function($){
        /* Media uploader */
        $(document).on('click', '.jimee-image-upload', function(e){
            e.preventDefault();
            var field = $(this).closest('.jimee-image-field');
            var frame = wp.media({ title: 'Choisir une image', multiple: false, library: { type: 'image' } });
            frame.on('select', function(){
                var att = frame.state().get('selection').first().toJSON();
                field.find('.jimee-image-id').val(att.id);
                var url = att.sizes && att.sizes.medium ? att.sizes.medium.url : att.url;
                field.find('.jimee-image-preview img').attr('src', url);
                field.find('.jimee-image-preview').show();
            });
            frame.open();
        });
        $(document).on('click', '.jimee-image-remove', function(e){
            e.preventDefault();
            var field = $(this).closest('.jimee-image-field');
            field.find('.jimee-image-id').val('');
            field.find('.jimee-image-preview').hide();
        });

        /* Remove rows */
        $(document).on('click', '.jimee-remove-row', function(){ $(this).closest('.jimee-ann-row').remove(); });
        $(document).on('click', '.jimee-remove-slide', function(){ $(this).closest('.jimee-slide-card').remove(); });
    });

    function jimeeAddAnnouncement(){
        var html = '<div class="jimee-ann-row">'
            + '<input type="text" name="jimee_announcements[]" value="" class="large-text" placeholder="Message d\'annonce...">'
            + '<button type="button" class="button jimee-remove-row" title="Supprimer">&times;</button></div>';
        document.getElementById('jimee-announcements').insertAdjacentHTML('beforeend', html);
    }

    function jimeeAddSlide(){
        var container = document.getElementById('jimee-hero-slides');
        var idx = container.querySelectorAll('.jimee-slide-card').length;
        var html = '<div class="jimee-slide-card">'
            + '<div class="jimee-slide-header"><strong>Slide ' + (idx+1) + '</strong>'
            + '<button type="button" class="button-link jimee-remove-slide" style="color:#8B0000">&times; Supprimer</button></div>'
            + '<div class="jimee-fields-grid">'
            + '<div><label>Sur-titre</label><input type="text" name="jimee_hero_slides['+idx+'][eyebrow]" class="regular-text"></div>'
            + '<div><label>Titre</label><input type="text" name="jimee_hero_slides['+idx+'][title]" class="large-text"></div>'
            + '<div style="grid-column:1/-1"><label>Description</label><textarea name="jimee_hero_slides['+idx+'][desc]" class="large-text" rows="2"></textarea></div>'
            + '<div><label>Texte du bouton</label><input type="text" name="jimee_hero_slides['+idx+'][cta]" class="regular-text"></div>'
            + '<div><label>Lien du bouton</label><input type="text" name="jimee_hero_slides['+idx+'][link]" class="regular-text" placeholder="/categorie-produit/visage/"></div>'
            + '<div style="grid-column:1/-1"><label>Image de fond</label>'
            + '<div class="jimee-image-field"><input type="hidden" name="jimee_hero_slides['+idx+'][image]" value="" class="jimee-image-id">'
            + '<div class="jimee-image-preview" style="display:none"><img src="" alt=""><button type="button" class="jimee-image-remove">&times;</button></div>'
            + '<button type="button" class="button jimee-image-upload">Choisir une image</button></div></div>'
            + '</div></div>';
        container.insertAdjacentHTML('beforeend', html);
    }
    </script>
    <?php
}

/* ============================================================
   ENQUEUE WP MEDIA on our admin page
   ============================================================ */

add_action( 'admin_enqueue_scripts', 'jimee_banners_admin_scripts' );
function jimee_banners_admin_scripts( $hook ) {
    if ( $hook !== 'toplevel_page_jimee-banners' ) return;
    wp_enqueue_media();
}
