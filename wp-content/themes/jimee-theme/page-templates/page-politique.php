<?php
/**
 * Template Name: Politique de confidentialité
 */

get_header();
?>

<div class="legal-hero">
    <div class="legal-eyebrow">Vos données</div>
    <h1>Politique de <em>confidentialité</em></h1>
    <p class="legal-meta">Dernière mise à jour : avril 2026</p>
</div>

<div class="legal-content">
    <div class="legal-card legal-section">
        <?php
        // Use WordPress page content if available
        while ( have_posts() ) : the_post();
            the_content();
        endwhile;
        ?>
    </div>
</div>

<?php get_footer(); ?>
