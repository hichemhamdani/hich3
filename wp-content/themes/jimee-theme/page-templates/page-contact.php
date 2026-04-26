<?php
/**
 * Template Name: Contact
 */

get_header();
?>

<div class="contact-hero">
    <div class="legal-eyebrow">Besoin d'aide ?</div>
    <h1>Contactez-<em>nous</em></h1>
    <p style="font-size:14px;color:rgba(255,255,255,.6);margin-top:8px;max-width:500px;margin-left:auto;margin-right:auto">
        Notre équipe est disponible du dimanche au jeudi, de 10h à 20h.
    </p>
</div>

<!-- Contact cards -->
<div class="contact-cards">
    <div class="contact-card">
        <div class="contact-card-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
        </div>
        <h3>Adresse</h3>
        <p>02, Rue Allaoua AEK "La Croix", Kouba, Alger</p>
    </div>
    <div class="contact-card">
        <div class="contact-card-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/></svg>
        </div>
        <h3>Téléphone</h3>
        <p><a href="tel:+213550922274">0 550 922 274</a></p>
    </div>
    <div class="contact-card">
        <div class="contact-card-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        </div>
        <h3>Email</h3>
        <p><a href="mailto:contact@jimeecosmetics.com">contact@jimeecosmetics.com</a></p>
    </div>
</div>

<!-- Contact form + info -->
<div class="contact-section">
    <div>
        <h2 style="font-size:22px;font-weight:300;margin-bottom:24px">Envoyez-nous un <em style="font-weight:600;font-style:italic">message</em></h2>
        <form class="contact-form" id="jimeeContactForm">
            <?php wp_nonce_field( 'jimee_contact', 'jimee_contact_nonce' ); ?>
            <div>
                <label>Nom complet</label>
                <input type="text" name="name" placeholder="Votre nom" required>
            </div>
            <div>
                <label>Email</label>
                <input type="email" name="email" placeholder="votre@email.com" required>
            </div>
            <div>
                <label>Telephone</label>
                <input type="tel" name="phone" placeholder="0557 XX XX XX">
            </div>
            <div>
                <label>Sujet</label>
                <select name="subject" required>
                    <option value="">Choisir un sujet</option>
                    <option value="commande">Question sur une commande</option>
                    <option value="produit">Renseignement produit</option>
                    <option value="retour">Retour / echange</option>
                    <option value="partenariat">Partenariat</option>
                    <option value="autre">Autre</option>
                </select>
            </div>
            <div>
                <label>Message</label>
                <textarea name="message" placeholder="Votre message..." required></textarea>
            </div>
            <div class="contact-form-notice" id="contactNotice" style="display:none;padding:12px 16px;border-radius:12px;font-size:13px;margin-bottom:8px"></div>
            <button type="submit">Envoyer le message</button>
        </form>
        <script>
        document.getElementById('jimeeContactForm').addEventListener('submit', function(e){
            e.preventDefault();
            var form = this;
            var btn = form.querySelector('button[type="submit"]');
            var notice = document.getElementById('contactNotice');
            var origText = btn.textContent;
            btn.textContent = 'Envoi en cours...';
            btn.disabled = true;

            var data = new FormData(form);
            data.append('action', 'jimee_contact');
            data.append('security', form.querySelector('[name="jimee_contact_nonce"]').value);

            fetch('<?php echo admin_url("admin-ajax.php"); ?>', { method: 'POST', body: data })
                .then(function(r){ return r.json(); })
                .then(function(res){
                    notice.style.display = 'block';
                    if(res.success){
                        notice.style.background = '#f0f9f0';
                        notice.style.color = '#2E7D32';
                        notice.textContent = 'Message envoye avec succes !';
                        form.reset();
                    } else {
                        notice.style.background = '#fff5f5';
                        notice.style.color = '#8B0000';
                        notice.textContent = res.data || 'Erreur lors de l\'envoi.';
                    }
                    btn.textContent = origText;
                    btn.disabled = false;
                })
                .catch(function(){
                    notice.style.display = 'block';
                    notice.style.background = '#fff5f5';
                    notice.style.color = '#8B0000';
                    notice.textContent = 'Erreur de connexion.';
                    btn.textContent = origText;
                    btn.disabled = false;
                });
        });
        </script>
    </div>
    <div class="contact-info">
        <div class="contact-info-card">
            <h3>Horaires d'ouverture</h3>
            <table style="width:100%;font-size:14px;color:#555">
                <tr><td style="padding:6px 0">Dimanche — Jeudi</td><td style="text-align:right;font-weight:500">10h — 20h</td></tr>
                <tr><td style="padding:6px 0">Vendredi</td><td style="text-align:right;font-weight:500;color:var(--bordeaux)">Fermé</td></tr>
                <tr><td style="padding:6px 0">Samedi</td><td style="text-align:right;font-weight:500">10h — 20h</td></tr>
            </table>
        </div>
        <div class="contact-info-card">
            <h3>Réseaux sociaux</h3>
            <div style="display:flex;gap:12px;margin-top:12px">
                <a href="https://www.instagram.com/jimeecosmeticsshop" target="_blank" rel="noopener" style="width:40px;height:40px;border-radius:50%;background:var(--black);display:flex;align-items:center;justify-content:center;transition:var(--transition)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5" style="width:18px;height:18px"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="5"/><circle cx="17.5" cy="6.5" r="1.5" fill="white" stroke="none"/></svg>
                </a>
                <a href="https://www.tiktok.com/@jimeecosmetics" target="_blank" rel="noopener" style="width:40px;height:40px;border-radius:50%;background:var(--black);display:flex;align-items:center;justify-content:center;transition:var(--transition)">
                    <svg viewBox="0 0 24 24" fill="white" style="width:18px;height:18px"><path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1 0-5.78 2.92 2.92 0 0 1 .88.13V9.4a6.84 6.84 0 0 0-1-.05A6.33 6.33 0 0 0 3 15.57 6.33 6.33 0 0 0 9.37 22a6.33 6.33 0 0 0 6.38-6.2V9.06a8.16 8.16 0 0 0 3.84.96V6.69z"/></svg>
                </a>
                <a href="https://www.facebook.com/jimmycosmetics" target="_blank" rel="noopener" style="width:40px;height:40px;border-radius:50%;background:var(--black);display:flex;align-items:center;justify-content:center;transition:var(--transition)">
                    <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="1.5" style="width:18px;height:18px"><path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/></svg>
                </a>
            </div>
        </div>
    </div>
</div>

<?php get_footer(); ?>
