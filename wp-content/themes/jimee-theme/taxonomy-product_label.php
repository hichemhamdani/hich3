<?php
/**
 * Product label archive (Bio, Vegan, Produit local, Sans alcool).
 * Reuses the shared archive layout.
 */

get_header();

get_template_part( 'template-parts/archive-layout', null, [
    'taxonomy'        => 'product_label',
    'term'            => get_queried_object(),
    'show_subcats'    => false,
    'filter_taxonomy' => 'product_brand',
    'filter_label'    => 'Marque',
]);

get_footer();
