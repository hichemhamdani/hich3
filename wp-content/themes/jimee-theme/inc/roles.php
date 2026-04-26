<?php
/**
 * Custom user roles — Jimee Cosmetics.
 *
 * - "Technique (Web Rocket)" : accès total (super admin sans être administrator)
 * - "Gérant Jimee"           : commandes, produits, stats, bannières homepage
 */

if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Register custom roles on theme activation.
 */
add_action( 'after_switch_theme', 'jimee_create_roles' );
function jimee_create_roles() {
    jimee_setup_roles();
}

/**
 * Also run on init if roles don't exist yet (covers manual updates).
 */
add_action( 'init', 'jimee_maybe_setup_roles', 5 );
function jimee_maybe_setup_roles() {
    if ( ! get_role( 'jimee_technique' ) || ! get_role( 'jimee_gerant' ) ) {
        jimee_setup_roles();
    }
}

/**
 * Create/update both roles.
 */
function jimee_setup_roles() {

    // ── 1. Technique (Web Rocket) — accès total ──
    $admin_role = get_role( 'administrator' );
    $tech_caps  = $admin_role ? $admin_role->capabilities : [];

    // Extra caps for full control
    $tech_caps['manage_woocommerce']     = true;
    $tech_caps['edit_theme_options']      = true;
    $tech_caps['install_plugins']         = true;
    $tech_caps['update_plugins']          = true;
    $tech_caps['delete_plugins']          = true;
    $tech_caps['install_themes']          = true;
    $tech_caps['update_themes']           = true;
    $tech_caps['edit_plugins']            = true;
    $tech_caps['edit_themes']             = true;
    $tech_caps['manage_options']          = true;
    $tech_caps['export']                  = true;
    $tech_caps['import']                  = true;

    remove_role( 'jimee_technique' );
    add_role( 'jimee_technique', 'Technique (Web Rocket)', $tech_caps );

    // ── 2. Gérant Jimee — gestion boutique ──
    $gerant_caps = [
        // Dashboard
        'read'                       => true,
        'edit_dashboard'             => true,

        // WooCommerce — commandes
        'manage_woocommerce'         => true,
        'view_woocommerce_reports'   => true,
        'edit_shop_orders'           => true,
        'read_shop_orders'           => true,
        'delete_shop_orders'         => false,
        'edit_others_shop_orders'    => true,
        'read_others_shop_orders'    => true,

        // WooCommerce — produits
        'edit_products'              => true,
        'edit_others_products'       => true,
        'publish_products'           => true,
        'read_products'              => true,
        'delete_products'            => true,
        'delete_others_products'     => true,
        'delete_published_products'  => true,
        'delete_product'             => true,
        'edit_product'               => true,
        'read_product'               => true,

        // WooCommerce — coupons
        'edit_shop_coupons'          => true,
        'read_shop_coupons'          => true,
        'edit_others_shop_coupons'   => true,
        'publish_shop_coupons'       => true,

        // Taxonomies produit (categories, marques, labels)
        'manage_product_terms'       => true,
        'edit_product_terms'         => true,
        'delete_product_terms'       => true,
        'assign_product_terms'       => true,
        'manage_categories'          => true,

        // Media (images produits, bannières)
        'upload_files'               => true,
        'edit_files'                 => true,

        // Pages (bannières homepage = page accueil)
        'edit_pages'                 => true,
        'edit_others_pages'          => true,
        'edit_published_pages'       => true,
        'read_pages'                 => true,

        // Posts (si besoin pour des actus/blog)
        'edit_posts'                 => true,
        'edit_others_posts'          => true,
        'edit_published_posts'       => true,
        'publish_posts'              => true,
        'read_posts'                 => true,

        // Users — créer et gérer des comptes gérant
        'list_users'                 => true,
        'create_users'               => true,
        'edit_users'                 => true,
        'delete_users'               => false,
        'promote_users'              => true,

        // Interdit
        'install_plugins'            => false,
        'update_plugins'             => false,
        'delete_plugins'             => false,
        'edit_plugins'               => false,
        'install_themes'             => false,
        'update_themes'              => false,
        'edit_themes'                => false,
        'switch_themes'              => false,
        'manage_options'             => false,
        'edit_theme_options'         => false,
        'export'                     => false,
        'import'                     => false,
        'remove_users'               => false,
    ];

    remove_role( 'jimee_gerant' );
    add_role( 'jimee_gerant', 'Gérant Jimee', $gerant_caps );
}

/* ============================================================
   ADMIN UI — Simplifier le menu pour le Gérant
   ============================================================ */

add_action( 'admin_menu', 'jimee_gerant_clean_menu', 999 );
function jimee_gerant_clean_menu() {
    if ( ! current_user_can( 'manage_options' ) && current_user_can( 'manage_woocommerce' ) ) {
        // Menus principaux inutiles
        remove_menu_page( 'tools.php' );              // Outils
        remove_menu_page( 'options-general.php' );     // Réglages
        remove_menu_page( 'themes.php' );              // Apparence
        remove_menu_page( 'plugins.php' );             // Extensions
        // users.php — garder pour le gérant (créer des comptes gérant)
        remove_menu_page( 'edit.php' );                // Articles (blog)
        remove_menu_page( 'edit-comments.php' );       // On le recrée renommé plus bas
        remove_menu_page( 'edit.php?post_type=page' ); // Pages

        // WooCommerce — virer le menu parent entier
        remove_menu_page( 'woocommerce' );
        remove_menu_page( 'wc-admin' );
        remove_menu_page( 'woocommerce-marketing' );

        // Recréer les menus WC comme top-level
        global $menu;
        $menu[25] = [ 'Produits', 'edit_products', 'edit.php?post_type=product', '', 'menu-top menu-icon-product', 'menu-posts-product', 'dashicons-archive' ];
        $menu[26] = [ 'Commandes', 'edit_shop_orders', 'edit.php?post_type=shop_order', '', 'menu-top menu-icon-shop_order', 'menu-posts-shop_order', 'dashicons-clipboard' ];
        $menu[27] = [ 'Clients', 'manage_woocommerce', 'users.php?role=customer', '', 'menu-top', 'menu-clients', 'dashicons-groups' ];
        $menu[28] = [ 'Coupons', 'edit_shop_coupons', 'edit.php?post_type=shop_coupon', '', 'menu-top', 'menu-coupons', 'dashicons-tickets-alt' ];
        $menu[29] = [ 'Avis clients', 'manage_woocommerce', 'edit-comments.php', '', 'menu-top', 'menu-avis', 'dashicons-star-filled' ];
        $menu[30] = [ 'Rapports', 'view_woocommerce_reports', 'admin.php?page=wc-admin&path=/analytics/overview', '', 'menu-top', 'menu-rapports', 'dashicons-chart-bar' ];

        // Paiements (technique, pas pour le gérant)
        remove_menu_page( 'wc-admin&path=/payments/overview' );
        remove_submenu_page( 'woocommerce', 'wc-admin&path=/payments/overview' );

        // Yoast SEO
        remove_menu_page( 'wpseo_dashboard' );
        remove_menu_page( 'wpseo_workouts' );

        // SiteGround
        remove_menu_page( 'sg-cachepress' );

        // Bordereau
        remove_menu_page( 'wc-bordereau-generator' );
    }
}

/* ============================================================
   REDIRECT — Si le gérant atterrit sur edit.php (Articles), renvoyer au dashboard
   ============================================================ */

add_action( 'admin_init', 'jimee_block_posts_screen' );
function jimee_block_posts_screen() {
    if ( current_user_can( 'manage_options' ) ) return;

    global $pagenow;
    if ( $pagenow === 'edit.php' && empty( $_GET['post_type'] ) ) {
        wp_safe_redirect( admin_url() );
        exit;
    }
    if ( $pagenow === 'post-new.php' && empty( $_GET['post_type'] ) ) {
        wp_safe_redirect( admin_url() );
        exit;
    }
}

/* ============================================================
   PRODUCT LIST — Colonnes simplifiées pour le Gérant
   ============================================================ */

add_filter( 'manage_edit-product_columns', 'jimee_gerant_product_columns', 999 );
function jimee_gerant_product_columns( $columns ) {
    if ( current_user_can( 'manage_options' ) ) return $columns;

    // Garder uniquement les colonnes utiles pour le gérant
    $keep = [ 'cb', 'thumb', 'name', 'jimee_photos', 'jimee_inci', 'jimee_usage', 'is_in_stock', 'price', 'product_cat', 'taxonomy-product_brand', 'taxonomy-product_label', 'date' ];
    foreach ( $columns as $key => $label ) {
        if ( ! in_array( $key, $keep, true ) ) {
            unset( $columns[ $key ] );
        }
    }
    return $columns;
}

/* ============================================================
   USERS — Limiter les rôles visibles pour le Gérant
   ============================================================ */

// Le gérant ne peut attribuer que Gérant Jimee ou Customer
add_filter( 'editable_roles', 'jimee_gerant_editable_roles' );
function jimee_gerant_editable_roles( $roles ) {
    if ( current_user_can( 'manage_options' ) ) return $roles;

    $allowed = [ 'jimee_gerant', 'customer' ];
    return array_intersect_key( $roles, array_flip( $allowed ) );
}

// Empêcher le gérant de modifier un admin ou technique
add_filter( 'map_meta_cap', 'jimee_gerant_protect_admins', 10, 4 );
function jimee_gerant_protect_admins( $caps, $cap, $user_id, $args ) {
    if ( in_array( $cap, [ 'edit_user', 'delete_user' ], true ) && ! empty( $args[0] ) ) {
        $target = get_userdata( $args[0] );
        if ( $target && ( in_array( 'administrator', $target->roles, true ) || in_array( 'jimee_technique', $target->roles, true ) ) ) {
            $caps[] = 'do_not_allow';
        }
    }
    return $caps;
}

/* ============================================================
   CATEGORIES — Bloquer la suppression pour le Gérant
   ============================================================ */

add_action( 'pre_delete_term', 'jimee_block_category_delete', 10, 2 );
function jimee_block_category_delete( $term_id, $taxonomy ) {
    if ( $taxonomy === 'product_cat' && ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Vous n\'avez pas la permission de supprimer des catégories. Contactez l\'équipe technique.' );
    }
}

// Masquer le lien "Supprimer" sur les catégories pour le gérant
add_filter( 'product_cat_row_actions', 'jimee_hide_cat_delete_link', 10, 2 );
function jimee_hide_cat_delete_link( $actions, $term ) {
    if ( ! current_user_can( 'manage_options' ) ) {
        unset( $actions['delete'] );
    }
    return $actions;
}

/* ============================================================
   FOOTER ADMIN — Masquer le texte WordPress
   ============================================================ */

add_filter( 'admin_footer_text', '__return_empty_string' );
add_filter( 'update_footer', '__return_empty_string', 11 );

/* ============================================================
   PROFIL — Simplifier pour le Gérant
   ============================================================ */

add_action( 'admin_head-profile.php', 'jimee_gerant_clean_profile' );
add_action( 'admin_head-user-edit.php', 'jimee_gerant_clean_profile' );
function jimee_gerant_clean_profile() {
    if ( current_user_can( 'manage_options' ) ) return;
    ?>
    <style>
    /* Masquer les sections inutiles */
    .user-rich-editing-wrap,
    .user-syntax-highlighting-wrap,
    .user-comment-shortcuts-wrap,
    .user-admin-bar-front-wrap,
    .user-language-wrap,
    .user-admin-color-wrap,
    .user-url-wrap,
    .user-description-wrap,
    .user-profile-picture,
    #your-profile > h2:first-of-type,
    #your-profile > table:first-of-type,
    .yoast-settings,
    #yoast-seo-section,
    h2#yoast-seo,
    .woocommerce-customer-features,
    #fieldset-billing,
    #fieldset-shipping,
    .user-nickname-wrap,
    .user-display-name-wrap,
    .user-user-login-wrap + .form-table .user-nickname-wrap,
    .user-facebook-wrap,
    .user-instagram-wrap,
    .user-linkedin-wrap,
    .user-myspace-wrap,
    .user-pinterest-wrap,
    .user-soundcloud-wrap,
    .user-tumblr-wrap,
    .user-twitter-wrap,
    .user-youtube-wrap,
    .user-wikipedia-wrap,
    #yoast-seo-section,
    tr[class*="user-"][class*="-wrap"]:has(input[id*="facebook"]),
    tr[class*="user-"][class*="-wrap"]:has(input[id*="twitter"]),
    tr[class*="user-"][class*="-wrap"]:has(input[id*="instagram"]),
    tr[class*="user-"][class*="-wrap"]:has(input[id*="linkedin"]),
    .application-passwords,
    #application-passwords-section,
    h2.application-passwords,
    #fieldset-billing,
    #fieldset-shipping,
    h2:has(+ #fieldset-billing),
    h2:has(+ #fieldset-shipping),
    .woocommerce-billing-fields,
    .woocommerce-shipping-fields { display: none !important; }


    /* Renommer visuellement la page */
    .wrap > h1 { font-size: 22px !important; font-weight: 300 !important; }
    .wrap > h1::after { content: none; }
    </style>
    <script>
    document.addEventListener('DOMContentLoaded', function(){
        var h1 = document.querySelector('.wrap > h1');
        if(h1) h1.textContent = 'Mon profil';

        document.querySelectorAll('#your-profile > h2').forEach(function(h){
            var t = h.textContent.toLowerCase();
            // Garder : Nom/Name → Identité, Gestion/Account → Mot de passe
            if(t.match(/nom|name/)) { h.textContent = 'Identité'; return; }
            if(t.match(/gestion|account|mot de passe/)) { h.textContent = 'Mot de passe'; return; }
            if(t.match(/contact/)) { h.textContent = 'Contact'; return; }
            // Masquer tout le reste (À propos, Mots de passe d'application, adresses, etc.)
            h.style.display = 'none';
            // Masquer aussi le table/div qui suit ce h2
            var next = h.nextElementSibling;
            while(next && next.tagName !== 'H2') {
                next.style.display = 'none';
                next = next.nextElementSibling;
            }
        });
    });
    </script>
    <?php
}

/* ============================================================
   LOGIN REDIRECT — Gérant → Commandes
   ============================================================ */

add_filter( 'login_redirect', 'jimee_gerant_login_redirect', 10, 3 );
function jimee_gerant_login_redirect( $redirect_to, $requested, $user ) {
    if ( ! is_wp_error( $user ) && in_array( 'jimee_gerant', (array) $user->roles, true ) ) {
        return admin_url( 'edit.php?post_type=shop_order' );
    }
    return $redirect_to;
}

// Bloquer l'accès direct aux pages WC settings (même si le menu est masqué)
add_action( 'admin_init', 'jimee_gerant_block_wc_settings' );
function jimee_gerant_block_wc_settings() {
    if ( current_user_can( 'manage_options' ) ) return;
    if ( ! current_user_can( 'manage_woocommerce' ) ) return;

    $screen = $_GET['page'] ?? '';
    $blocked = [ 'wc-settings', 'wc-status', 'wc-addons', 'wc-admin', 'wpseo_dashboard', 'wpseo_workouts', 'wc-bordereau-generator' ];
    if ( in_array( $screen, $blocked, true ) ) {
        wp_safe_redirect( admin_url() );
        exit;
    }
}

/* ============================================================
   ADMIN BAR — Simplifier pour le Gérant
   ============================================================ */

add_action( 'wp_before_admin_bar_render', 'jimee_gerant_clean_admin_bar' );
function jimee_gerant_clean_admin_bar() {
    if ( ! current_user_can( 'manage_options' ) && current_user_can( 'manage_woocommerce' ) ) {
        global $wp_admin_bar;
        $wp_admin_bar->remove_node( 'updates' );
        $wp_admin_bar->remove_node( 'comments' );
        $wp_admin_bar->remove_node( 'new-user' );
        $wp_admin_bar->remove_node( 'wp-logo' );
    }
}

/* ============================================================
   DASHBOARD — Widgets personnalisés pour le Gérant
   ============================================================ */

add_action( 'wp_dashboard_setup', 'jimee_gerant_dashboard', 20 );
function jimee_gerant_dashboard() {
    if ( ! current_user_can( 'manage_options' ) && current_user_can( 'manage_woocommerce' ) ) {
        // Supprimer les widgets par défaut inutiles
        remove_meta_box( 'dashboard_quick_press', 'dashboard', 'side' );
        remove_meta_box( 'dashboard_primary', 'dashboard', 'side' );
        remove_meta_box( 'dashboard_site_health', 'dashboard', 'normal' );
        remove_meta_box( 'wpseo-dashboard-overview', 'dashboard', 'normal' );

        // Ajouter un widget de bienvenue Jimee
        wp_add_dashboard_widget(
            'jimee_welcome',
            'Bienvenue sur Jimee Cosmetics',
            'jimee_welcome_widget'
        );
    }
}

function jimee_welcome_widget() {
    $orders_count = wc_orders_count( 'processing' );
    $products_count = wp_count_posts( 'product' )->publish;
    ?>
    <div style="font-family:'Poppins',sans-serif;line-height:1.6">
        <p style="font-size:14px;color:#555;margin-bottom:16px">
            Gerez vos commandes, produits et promotions depuis cet espace.
        </p>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
            <a href="<?php echo admin_url( 'edit.php?post_type=shop_order&post_status=wc-processing' ); ?>"
               style="display:block;padding:16px;background:#f8f6f3;border-radius:12px;text-align:center;text-decoration:none;color:#000">
                <strong style="font-size:24px;display:block"><?php echo $orders_count; ?></strong>
                <span style="font-size:12px;color:#777">Commandes en cours</span>
            </a>
            <a href="<?php echo admin_url( 'edit.php?post_type=product' ); ?>"
               style="display:block;padding:16px;background:#f8f6f3;border-radius:12px;text-align:center;text-decoration:none;color:#000">
                <strong style="font-size:24px;display:block"><?php echo $products_count; ?></strong>
                <span style="font-size:12px;color:#777">Produits en ligne</span>
            </a>
        </div>
        <p style="font-size:13px;color:#999">
            <a href="<?php echo admin_url( 'post.php?post=50088&action=edit' ); ?>" style="color:#D4AF37;font-weight:500">Modifier la page d'accueil</a>
            &nbsp;·&nbsp;
            <a href="<?php echo home_url( '/' ); ?>" style="color:#999" target="_blank">Voir le site</a>
        </p>
    </div>
    <?php
}
