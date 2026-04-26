<?php
/**
 * Shop page (Boutique) — uses same archive layout as categories.
 */

get_header();

get_template_part( 'template-parts/archive-layout', null, [
    'taxonomy'        => null,
    'term'            => null,
    'show_subcats'    => false,
    'filter_taxonomy' => 'product_brand',
    'filter_label'    => 'Marque',
    'page_title'      => 'Tous nos produits',
    'is_shop'         => true,
]);

get_footer();
