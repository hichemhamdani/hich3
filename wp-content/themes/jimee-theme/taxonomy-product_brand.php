<?php
/**
 * Brand archive — same design as category archive.
 * No subcategory pills (brands are flat).
 * Filter by category instead of brand.
 */

get_header();

get_template_part( 'template-parts/archive-layout', null, [
    'taxonomy'        => 'product_brand',
    'term'            => get_queried_object(),
    'show_subcats'    => false,
    'filter_taxonomy' => 'product_cat',
    'filter_label'    => 'Catégorie',
]);

get_footer();
