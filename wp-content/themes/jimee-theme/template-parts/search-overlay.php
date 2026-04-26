<!-- SEARCH OVERLAY -->
<div class="search-overlay" id="searchOverlay">
    <button class="search-overlay-close" id="searchOverlayClose" aria-label="Fermer la recherche">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
    </button>
    <form action="<?php echo esc_url( home_url( '/' ) ); ?>" method="get" class="search-overlay-form">
        <div class="search-overlay-input">
            <input type="text" name="s" placeholder="Que recherchez-vous ?" id="searchInput" autocomplete="off">
            <input type="hidden" name="post_type" value="product">
            <button type="submit" class="search-circle">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            </button>
        </div>
    </form>
    <div class="search-overlay-results" id="searchOverlayResults" style="display:none"></div>
    <a href="#" class="search-overlay-all" id="searchOverlayAllLink" style="display:none">Voir tous les résultats</a>
    <div class="search-suggestions" id="searchOverlaySuggestions">
        <h4>Recherches populaires</h4>
        <?php
        $suggestions = [
            'Sérum vitamine C', 'Crème hydratante', 'Huile de rose musquée',
            'Masque cheveux', 'SPF 50', 'Niacinamide',
        ];
        foreach ( $suggestions as $s ) {
            printf(
                '<a href="%s" class="search-suggestion-item">%s</a>',
                esc_url( home_url( '/?s=' . urlencode( $s ) . '&post_type=product' ) ),
                esc_html( $s )
            );
        }
        ?>
    </div>
</div>
