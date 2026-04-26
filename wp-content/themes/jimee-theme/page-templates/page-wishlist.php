<?php
/**
 * Template Name: Wishlist
 * localStorage-based wishlist — same product cards as archive.
 */

get_header();
?>

<div class="wishlist-hero">
    <div class="wishlist-eyebrow">Mes favoris</div>
    <h1>Ma liste de <em>souhaits</em></h1>
</div>

<div class="wishlist-grid-wrapper">
    <div class="product-grid" id="wishlistGrid"></div>
    <div class="wishlist-empty" id="wishlistEmpty">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/></svg>
        <h2>Votre liste est vide</h2>
        <p>Ajoutez des produits à vos favoris en cliquant sur le coeur.</p>
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Explorer la boutique</a>
    </div>
    <div class="loading-spinner" id="wishlistSpinner" style="display:none">
        <div class="spinner"></div>
        <div class="loading-text">Chargement de vos favoris...</div>
    </div>
</div>

<script>
(function() {
    'use strict';

    var grid    = document.getElementById('wishlistGrid');
    var empty   = document.getElementById('wishlistEmpty');
    var spinner = document.getElementById('wishlistSpinner');

    function loadWishlist() {
        if (!window.JimeeWishlist) return;
        var items = JimeeWishlist.getItems();

        if (items.length === 0) {
            empty.style.display = '';
            grid.style.display = 'none';
            return;
        }

        empty.style.display = 'none';
        spinner.style.display = 'block';

        var data = new FormData();
        data.append('action', 'jimee_get_wishlist');
        items.forEach(function(id) { data.append('product_ids[]', id); });

        fetch('<?php echo admin_url( "admin-ajax.php" ); ?>', { method: 'POST', body: data })
            .then(function(r) { return r.json(); })
            .then(function(res) {
                spinner.style.display = 'none';
                if (res.success && res.data.html) {
                    grid.innerHTML = res.data.html;
                    grid.style.display = '';
                    JimeeWishlist.updateUI();
                } else {
                    empty.style.display = '';
                    grid.style.display = 'none';
                }
            })
            .catch(function() {
                spinner.style.display = 'none';
                empty.style.display = '';
            });
    }

    document.addEventListener('DOMContentLoaded', loadWishlist);
})();
</script>

<?php get_footer(); ?>
