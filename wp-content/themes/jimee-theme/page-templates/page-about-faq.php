<?php
/**
 * Template Name: À propos & FAQ
 * About section + FAQ accordion.
 */

get_header();
$img = get_template_directory_uri() . '/assets/img/';
?>

<!-- ABOUT HERO — immersive -->
<div class="about-hero">
    <div class="about-hero-overlay"></div>
    <div class="about-hero-content">
        <span class="about-eyebrow">Notre histoire</span>
        <h1>Toutes choses Cosmé,<br><em>tout en un seul endroit</em></h1>
        <p class="about-hero-sub">Depuis Kouba au coeur d'Alger, Jimee Cosmetics rend la beauté accessible à toutes et tous en Algérie.</p>
    </div>
</div>

<!-- STORY — text + image -->
<section class="about-story">
    <div class="about-story-text">
        <span class="about-eyebrow dark">Qui sommes-nous</span>
        <h2>Née d'une <em>passion</em></h2>
        <p>Jimee Cosmetics, c'est avant tout l'histoire d'une passion pour la beauté. Depuis notre boutique au 02 Rue Alaoua Abd El Kader à Kouba, nous sélectionnons avec soin les meilleures marques internationales pour les rendre accessibles en Algérie.</p>
        <p>Notre équipe teste et valide chaque produit avant de le proposer. Du soin coréen K-Beauty aux marques premium occidentales, nous croyons que chaque personne mérite d'accéder aux meilleurs cosmétiques — sans compromis sur la qualité, à des prix justes.</p>
    </div>
    <div class="about-story-img">
        <img src="<?php echo $img; ?>rare-beauty-collection-maquillage.jpg" alt="Collection beauté Jimee Cosmetics" loading="lazy">
    </div>
</section>

<!-- STATS BAR -->
<section class="about-stats">
    <div class="about-stat">
        <span class="about-stat-number">146K+</span>
        <span class="about-stat-label">Abonnés sur Instagram</span>
    </div>
    <div class="about-stat-divider"></div>
    <div class="about-stat">
        <span class="about-stat-number">7 000+</span>
        <span class="about-stat-label">Produits au catalogue</span>
    </div>
    <div class="about-stat-divider"></div>
    <div class="about-stat">
        <span class="about-stat-number">100+</span>
        <span class="about-stat-label">Marques internationales</span>
    </div>
    <div class="about-stat-divider"></div>
    <div class="about-stat">
        <span class="about-stat-number">100K+</span>
        <span class="about-stat-label">Fans sur Facebook</span>
    </div>
</section>

<!-- MISSION — image + text (reversed) -->
<section class="about-story reverse">
    <div class="about-story-text">
        <span class="about-eyebrow dark">Notre mission</span>
        <h2>La beauté <em>sans frontières</em></h2>
        <p>En Algérie, accéder aux dernières tendances beauté n'a pas toujours été simple. Jimee Cosmetics change la donne : nous sourçons directement auprès des distributeurs agréés pour garantir l'authenticité de chaque produit.</p>
        <p>Livraison rapide via Yalidine sur tout le territoire national, paiement sécurisé par CIB et Dahabia, et un service client disponible et attentif du dimanche au samedi. La beauté, livrée chez vous.</p>
    </div>
    <div class="about-story-img">
        <img src="<?php echo $img; ?>serum-goutte-pipette-soin-visage.jpg" alt="Sérum soin visage" loading="lazy">
    </div>
</section>

<!-- VALUES -->
<section class="about-values-section">
    <div class="about-values-header">
        <span class="about-eyebrow dark">Pourquoi Jimee</span>
        <h2>Ce qui nous <em>distingue</em></h2>
    </div>
    <div class="about-values">
        <div class="about-value">
            <div class="about-value-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </div>
            <h3>100% Authentique</h3>
            <p>Tous nos produits sont certifiés originaux, sourcés directement auprès des distributeurs agréés.</p>
        </div>
        <div class="about-value">
            <div class="about-value-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            </div>
            <h3>Conseil expert</h3>
            <p>Notre équipe vous accompagne pour trouver les produits adaptés à votre type de peau et vos besoins.</p>
        </div>
        <div class="about-value">
            <div class="about-value-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            </div>
            <h3>+7 000 produits</h3>
            <p>Un catalogue riche de plus de 100 marques internationales, du soin quotidien au maquillage premium.</p>
        </div>
        <div class="about-value">
            <div class="about-value-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="3" width="15" height="13" rx="2"/><path d="M16 8h4a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-4"/></svg>
            </div>
            <h3>Livraison nationale</h3>
            <p>Expédition via Yalidine sur les 58 wilayas. Livraison gratuite à partir de 10 000 DA.</p>
        </div>
    </div>
</section>

<!-- GALLERY MOSAIC -->
<section class="about-gallery">
    <div class="about-gallery-item tall">
        <img src="<?php echo $img; ?>peau-glowy-maquillage-naturel.jpg" alt="Maquillage naturel" loading="lazy">
    </div>
    <div class="about-gallery-item">
        <img src="<?php echo $img; ?>soin-cheveux-routine-capillaire.jpg" alt="Routine capillaire" loading="lazy">
    </div>
    <div class="about-gallery-item">
        <img src="<?php echo $img; ?>masque-visage-routine-skincare.jpg" alt="Routine skincare" loading="lazy">
    </div>
    <div class="about-gallery-item tall">
        <img src="<?php echo $img; ?>nyx-smoosh-lip-balm-collection-coloree.jpg" alt="Collection maquillage" loading="lazy">
    </div>
    <div class="about-gallery-item">
        <img src="<?php echo $img; ?>protection-solaire-ete-piscine.jpg" alt="Protection solaire" loading="lazy">
    </div>
    <div class="about-gallery-item">
        <img src="<?php echo $img; ?>medicube-pdrn-pink-collagen-capsule-cream.jpg" alt="K-Beauty soin" loading="lazy">
    </div>
</section>

<!-- BOUTIQUE + SOCIAL -->
<section class="about-boutique">
    <div class="about-boutique-inner">
        <div class="about-boutique-info">
            <span class="about-eyebrow" style="color:var(--gold)">Notre boutique</span>
            <h2>Venez nous <em>rencontrer</em></h2>
            <div class="about-boutique-details">
                <div class="about-boutique-detail">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="20" height="20"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
                    <div>
                        <strong>Adresse</strong>
                        <p>02 Rue Alaoua Abd El Kader<br>La Croix, Kouba — Alger</p>
                    </div>
                </div>
                <div class="about-boutique-detail">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="20" height="20"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    <div>
                        <strong>Horaires</strong>
                        <p>Dimanche — Samedi<br>10h00 — 20h00</p>
                    </div>
                </div>
                <div class="about-boutique-detail">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="20" height="20"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.362 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.338 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
                    <div>
                        <strong>Téléphone</strong>
                        <p>+213 550 92 22 74</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="about-boutique-social">
            <h3>Rejoignez la communauté</h3>
            <p>Plus de 146 000 passionné(e)s de beauté nous suivent déjà.</p>
            <div class="about-social-links">
                <a href="https://www.instagram.com/jimeecosmeticsshop/" target="_blank" rel="noopener" class="about-social-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="22" height="22"><rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/></svg>
                    <span>@jimeecosmeticsshop</span>
                </a>
                <a href="https://www.facebook.com/jimmycosmetics" target="_blank" rel="noopener" class="about-social-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="22" height="22"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                    <span>Jimmy Cosmetics</span>
                </a>
                <a href="https://www.tiktok.com/@jimeecosmetics" target="_blank" rel="noopener" class="about-social-link">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="22" height="22"><path d="M9 12a4 4 0 1 0 4 4V4a5 5 0 0 0 5 5"/></svg>
                    <span>TikTok</span>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- FAQ SECTION -->
<div class="faq-hero" style="margin-top:0" id="faq">
    <div class="legal-eyebrow">Centre d'aide</div>
    <h1>Questions <em>fréquentes</em></h1>
    <p style="font-size:14px;color:rgba(255,255,255,.6);margin-top:8px;max-width:500px;margin-left:auto;margin-right:auto">
        Trouvez rapidement les réponses à vos questions.
    </p>
    <div class="faq-search">
        <input type="text" id="faqSearch" placeholder="Rechercher une question...">
        <button type="button" class="search-circle" style="width:36px;height:36px">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;color:#fff"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
        </button>
    </div>
</div>

<!-- Category pills -->
<div class="faq-cat-pills">
    <button class="faq-cat-pill active" data-cat="all">Toutes</button>
    <button class="faq-cat-pill" data-cat="commandes">Commandes</button>
    <button class="faq-cat-pill" data-cat="livraison">Livraison</button>
    <button class="faq-cat-pill" data-cat="paiement">Paiement</button>
    <button class="faq-cat-pill" data-cat="retours">Retours</button>
    <button class="faq-cat-pill" data-cat="compte">Compte</button>
    <button class="faq-cat-pill" data-cat="produits">Produits</button>
</div>

<div class="faq-content">

    <!-- Commandes -->
    <div class="faq-section" data-cat="commandes">
        <h2 class="faq-section-title">Commandes</h2>
        <div class="faq-accordion">
            <div class="faq-accordion-header">Comment passer une commande ? <span class="faq-accordion-icon">+</span></div>
            <div class="faq-accordion-body"><div class="faq-accordion-body-inner">Parcourez notre catalogue, ajoutez les produits souhaités à votre panier, puis cliquez sur "Passer commande". Remplissez vos informations de livraison et choisissez votre mode de paiement. Vous recevrez un email de confirmation dès que votre commande sera validée.</div></div>
        </div>
        <div class="faq-accordion">
            <div class="faq-accordion-header">Puis-je modifier ou annuler ma commande ? <span class="faq-accordion-icon">+</span></div>
            <div class="faq-accordion-body"><div class="faq-accordion-body-inner">Vous pouvez modifier ou annuler votre commande tant qu'elle n'a pas été expédiée. Contactez-nous rapidement par téléphone au 0557 02 28 28 ou par email à contact@jimeecosmetics.com.</div></div>
        </div>
        <div class="faq-accordion">
            <div class="faq-accordion-header">Comment suivre ma commande ? <span class="faq-accordion-icon">+</span></div>
            <div class="faq-accordion-body"><div class="faq-accordion-body-inner">Une fois votre commande expédiée, vous recevrez un email avec le numéro de suivi Yalidine. Vous pourrez suivre votre colis en temps réel sur le site de Yalidine ou depuis votre espace client.</div></div>
        </div>
    </div>

    <!-- Livraison -->
    <div class="faq-section" data-cat="livraison" id="livraison">
        <h2 class="faq-section-title">Livraison</h2>
        <div class="faq-accordion">
            <div class="faq-accordion-header">Quels sont les délais de livraison ? <span class="faq-accordion-icon">+</span></div>
            <div class="faq-accordion-body"><div class="faq-accordion-body-inner">La livraison est assurée par Yalidine sur tout le territoire national. Comptez 2 à 5 jours ouvrables selon votre wilaya. Les commandes passées avant 14h sont généralement expédiées le jour même.</div></div>
        </div>
        <div class="faq-accordion">
            <div class="faq-accordion-header">La livraison est-elle gratuite ? <span class="faq-accordion-icon">+</span></div>
            <div class="faq-accordion-body"><div class="faq-accordion-body-inner">La livraison est offerte pour toute commande supérieure à 10 000 DA. En dessous de ce montant, les frais de livraison sont calculés selon votre localisation.</div></div>
        </div>
    </div>

    <!-- Paiement -->
    <div class="faq-section" data-cat="paiement">
        <h2 class="faq-section-title">Paiement</h2>
        <div class="faq-accordion">
            <div class="faq-accordion-header">Quels modes de paiement acceptez-vous ? <span class="faq-accordion-icon">+</span></div>
            <div class="faq-accordion-body"><div class="faq-accordion-body-inner">Nous acceptons les paiements par carte CIB et Dahabia (Edahabia). Le paiement est 100% sécurisé via la plateforme de paiement certifiée.</div></div>
        </div>
        <div class="faq-accordion">
            <div class="faq-accordion-header">Le paiement en ligne est-il sécurisé ? <span class="faq-accordion-icon">+</span></div>
            <div class="faq-accordion-body"><div class="faq-accordion-body-inner">Absolument. Toutes les transactions sont chiffrées via le protocole SSL. Vos données bancaires ne sont jamais stockées sur nos serveurs.</div></div>
        </div>
    </div>

    <!-- Retours -->
    <div class="faq-section" data-cat="retours" id="retours">
        <h2 class="faq-section-title">Retours & échanges</h2>
        <div class="faq-accordion">
            <div class="faq-accordion-header">Quelle est votre politique de retour ? <span class="faq-accordion-icon">+</span></div>
            <div class="faq-accordion-body"><div class="faq-accordion-body-inner">Vous disposez de 14 jours après réception pour retourner un produit non ouvert et dans son emballage d'origine. Contactez notre service client pour initier un retour.</div></div>
        </div>
        <div class="faq-accordion">
            <div class="faq-accordion-header">Comment procéder à un échange ? <span class="faq-accordion-icon">+</span></div>
            <div class="faq-accordion-body"><div class="faq-accordion-body-inner">Contactez-nous par téléphone ou email avec votre numéro de commande. Nous organiserons l'échange du produit dans les plus brefs délais.</div></div>
        </div>
    </div>

    <!-- Compte -->
    <div class="faq-section" data-cat="compte">
        <h2 class="faq-section-title">Mon compte</h2>
        <div class="faq-accordion">
            <div class="faq-accordion-header">Comment créer un compte ? <span class="faq-accordion-icon">+</span></div>
            <div class="faq-accordion-body"><div class="faq-accordion-body-inner">Cliquez sur l'icône de profil en haut du site, puis sur "Créer un compte". Remplissez le formulaire avec votre email et un mot de passe. Vous pouvez aussi créer un compte lors de votre première commande.</div></div>
        </div>
        <div class="faq-accordion">
            <div class="faq-accordion-header">J'ai oublié mon mot de passe <span class="faq-accordion-icon">+</span></div>
            <div class="faq-accordion-body"><div class="faq-accordion-body-inner">Cliquez sur "Mot de passe oublié" sur la page de connexion. Entrez votre adresse email et vous recevrez un lien pour réinitialiser votre mot de passe.</div></div>
        </div>
    </div>

    <!-- Produits -->
    <div class="faq-section" data-cat="produits">
        <h2 class="faq-section-title">Produits</h2>
        <div class="faq-accordion">
            <div class="faq-accordion-header">Vos produits sont-ils authentiques ? <span class="faq-accordion-icon">+</span></div>
            <div class="faq-accordion-body"><div class="faq-accordion-body-inner">Oui, 100%. Tous nos produits sont sourcés directement auprès des distributeurs officiels et agréés. Nous garantissons l'authenticité de chaque produit vendu sur jimeecosmetics.com.</div></div>
        </div>
        <div class="faq-accordion">
            <div class="faq-accordion-header">Un produit est en rupture de stock, que faire ? <span class="faq-accordion-icon">+</span></div>
            <div class="faq-accordion-body"><div class="faq-accordion-body-inner">Contactez-nous pour savoir quand le produit sera de nouveau disponible. Nous pouvons aussi vous suggérer des alternatives similaires parmi notre catalogue de plus de 7 000 produits.</div></div>
        </div>
        <div class="faq-accordion">
            <div class="faq-accordion-header">Proposez-vous des échantillons ? <span class="faq-accordion-icon">+</span></div>
            <div class="faq-accordion-body"><div class="faq-accordion-body-inner">Oui ! Des échantillons gratuits sont glissés dans chaque commande pour vous permettre de découvrir de nouveaux produits.</div></div>
        </div>
    </div>

    <div class="faq-empty" id="faqEmpty">Aucune question ne correspond à votre recherche.</div>

    <div class="faq-cta">
        <h3>Vous n'avez pas trouvé votre réponse ?</h3>
        <p>Notre équipe est disponible du dimanche au jeudi, de 9h à 18h.</p>
        <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Nous contacter</a>
    </div>
</div>

<script>
(function() {
    'use strict';

    /* Accordion toggle */
    document.querySelectorAll('.faq-accordion-header').forEach(function(h) {
        h.addEventListener('click', function() {
            h.parentElement.classList.toggle('open');
        });
    });

    /* Category pills */
    document.querySelectorAll('.faq-cat-pill').forEach(function(pill) {
        pill.addEventListener('click', function() {
            document.querySelector('.faq-cat-pill.active')?.classList.remove('active');
            pill.classList.add('active');
            var cat = pill.dataset.cat;
            document.querySelectorAll('.faq-section').forEach(function(s) {
                s.style.display = (cat === 'all' || s.dataset.cat === cat) ? '' : 'none';
            });
            document.getElementById('faqEmpty').classList.remove('active');
        });
    });

    /* Search filter */
    var searchInput = document.getElementById('faqSearch');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            var q = this.value.toLowerCase().trim();
            var visible = 0;
            document.querySelectorAll('.faq-accordion').forEach(function(a) {
                var text = a.textContent.toLowerCase();
                var match = !q || text.indexOf(q) > -1;
                a.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            document.querySelectorAll('.faq-section').forEach(function(s) {
                var hasVisible = s.querySelector('.faq-accordion[style=""], .faq-accordion:not([style])');
                s.style.display = hasVisible ? '' : 'none';
            });
            var emptyEl = document.getElementById('faqEmpty');
            if (emptyEl) emptyEl.classList.toggle('active', visible === 0 && q.length > 0);
        });
    }
})();
</script>

<?php get_footer(); ?>
