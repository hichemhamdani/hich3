<?php
/**
 * 404 Error page.
 */

get_header();
?>
<div class="page-404">
    <div class="page-404-number">404</div>
    <h1>Page introuvable</h1>
    <p>La page que vous cherchez n'existe pas ou a été déplacée. Pas de panique, retournez à l'accueil ou explorez nos produits.</p>
    <div class="page-404-actions">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Retour à l'accueil</a>
        <a href="<?php echo esc_url( function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'shop' ) : home_url( '/' ) ); ?>">Explorer la boutique</a>
    </div>
    <form action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get" style="margin-top:40px;max-width:400px;margin-left:auto;margin-right:auto">
        <div class="search-bar" style="display:flex;width:100%;max-width:100%">
            <input type="text" name="s" placeholder="Rechercher un produit..." style="flex:1;background:none;border:none;outline:none;font-size:14px;padding:8px 0">
            <input type="hidden" name="post_type" value="product">
            <button type="submit" class="search-circle" style="width:36px;height:36px;border-radius:50%;background:var(--black);display:flex;align-items:center;justify-content:center;border:none">
                <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" style="width:16px;height:16px"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </button>
        </div>
    </form>
</div>
<?php
get_footer();
