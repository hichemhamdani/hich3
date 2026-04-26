<?php
/**
 * Product category archive.
 */

get_header();

get_template_part( 'template-parts/archive-layout', null, [
    'taxonomy'        => 'product_cat',
    'term'            => get_queried_object(),
    'show_subcats'    => true,
    'filter_taxonomy' => 'product_brand',
    'filter_label'    => 'Marque',
]);

get_footer();
