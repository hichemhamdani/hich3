<?php
/**
 * Template Name: CGV
 */

get_header();
?>

<div class="legal-hero">
    <div class="legal-eyebrow">Conditions légales</div>
    <h1>Conditions générales de <em>vente</em></h1>
    <p class="legal-meta">Dernière mise à jour : avril 2026</p>
</div>

<div class="legal-content">
    <div class="legal-card legal-section">
        <?php
        while ( have_posts() ) : the_post();
            the_content();
        endwhile;
        ?>
    </div>
</div>

<?php get_footer(); ?>
