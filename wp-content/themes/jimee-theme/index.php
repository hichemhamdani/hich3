<?php
/**
 * Fallback template (required by WordPress).
 * In practice, more specific templates handle all page types.
 */

get_header();
?>
<main class="container" style="padding-top:40px;padding-bottom:80px">
    <?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
        <article>
            <h1><?php the_title(); ?></h1>
            <div><?php the_content(); ?></div>
        </article>
    <?php endwhile; else : ?>
        <p>Aucun contenu trouvé.</p>
    <?php endif; ?>
</main>
<?php
get_footer();
