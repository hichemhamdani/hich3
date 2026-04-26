<?php
/**
 * Default page template.
 * Legal pages (politique, CGV) get the legal-card design automatically.
 */

get_header();

$is_legal = in_array( get_the_ID(), [ 3, 24 ], true )
         || in_array( get_post_field( 'post_name' ), [ 'politique-de-confidentialite', 'conditions-de-vente', 'conditions-generales-vente' ], true );

if ( $is_legal ) : while ( have_posts() ) : the_post(); ?>

    <div class="legal-hero">
        <div class="legal-eyebrow">Jimee Cosmetics</div>
        <h1><?php the_title(); ?></h1>
        <div class="legal-meta">Derniere mise a jour : avril 2026</div>
    </div>

    <div class="legal-content">
        <div class="legal-card legal-section">
            <?php the_content(); ?>
        </div>
    </div>

<?php endwhile; else : ?>

<div class="container" style="padding-top:40px;padding-bottom:80px">
    <?php while ( have_posts() ) : the_post(); ?>
        <h1 style="font-size:clamp(28px,4vw,40px);font-weight:300;margin-bottom:24px"><?php the_title(); ?></h1>
        <div style="font-size:14px;line-height:1.8;color:#555"><?php the_content(); ?></div>
    <?php endwhile; ?>
</div>

<?php endif;

get_footer();
