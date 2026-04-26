<?php
/**
 * Checkout Form — Jimee Cosmetics
 * 4-section layout with inline coupon & login bar
 *
 * @version 9.4.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// WC notices (errors/success) — keep them
wc_print_notices();

if ( ! $checkout->is_registration_enabled() && $checkout->is_registration_required() && ! is_user_logged_in() ) {
    echo '<p class="jimee-checkout-login-required">' . esc_html__( 'Vous devez être connecté pour passer commande.', 'jimee' ) . '</p>';
    return;
}
?>

<?php if ( ! is_user_logged_in() ) : ?>
<div class="jimee-checkout-auth">
    <div class="jimee-auth-row">
        <button type="button" class="jimee-auth-option" data-target="login">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="22" height="22"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <span class="jimee-auth-option-text">
                <span class="jimee-auth-option-label">Déjà client ?</span>
                <span class="jimee-auth-option-action">Se connecter</span>
            </span>
            <svg class="jimee-auth-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
    </div>
    <div class="jimee-auth-divider"></div>
    <div class="jimee-auth-row">
        <button type="button" class="jimee-auth-option" data-target="register">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="22" height="22"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>
            <span class="jimee-auth-option-text">
                <span class="jimee-auth-option-label">Nouveau ?</span>
                <span class="jimee-auth-option-action">Créer un compte</span>
            </span>
            <svg class="jimee-auth-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="9 18 15 12 9 6"/></svg>
        </button>
    </div>

    <!-- Login form (hidden by default) -->
    <div class="jimee-auth-panel" id="jimee-login-panel" style="display:none">
        <form method="post" class="jimee-inline-login">
            <div class="jimee-login-fields">
                <div class="jimee-login-field">
                    <label for="jimee-login-user">Email ou téléphone</label>
                    <input type="text" id="jimee-login-user" name="username" autocomplete="username">
                </div>
                <div class="jimee-login-field">
                    <label for="jimee-login-pass">Mot de passe</label>
                    <input type="password" id="jimee-login-pass" name="password" autocomplete="current-password">
                </div>
                <button type="submit" name="login" value="1" class="jimee-login-btn">Se connecter</button>
            </div>
            <a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" class="jimee-forgot-link">Mot de passe oublié ?</a>
            <?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
        </form>
    </div>

    <!-- Register panel (hidden by default) -->
    <div class="jimee-auth-panel" id="jimee-register-panel" style="display:none">
        <p class="jimee-register-info">Créez un compte pour suivre vos commandes et profiter d'une expérience personnalisée.</p>
        <p class="jimee-register-note">Remplissez vos coordonnées ci-dessous, puis cochez "Créer un compte" avant de confirmer votre commande.</p>
    </div>
</div>
<?php else :
    $user = wp_get_current_user();
?>
<div class="jimee-checkout-auth jimee-checkout-auth--logged-in">
    <div class="jimee-checkout-auth-inner">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="20" height="20"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        <span>Bonjour <strong><?php echo esc_html( $user->first_name ?: $user->display_name ); ?></strong></span>
        <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'dashboard' ) ); ?>" class="jimee-auth-account-link">Mon compte</a>
    </div>
</div>
<?php endif; ?>

<form name="checkout" method="post" class="checkout woocommerce-checkout" action="<?php echo esc_url( wc_get_checkout_url() ); ?>" enctype="multipart/form-data">

    <div class="jimee-checkout-grid">

        <!-- ═══ LEFT COLUMN ═══ -->
        <div class="jimee-checkout-left">

            <?php if ( $checkout->get_checkout_fields() ) : ?>

                <?php do_action( 'woocommerce_checkout_before_customer_details' ); ?>

                <!-- 1. Coordonnées -->
                <div class="jimee-checkout-card" id="jimee-section-1">
                    <div class="jimee-section-header">
                        <span class="jimee-section-num">1</span>
                        <h3>Coordonnées</h3>
                    </div>
                    <div class="jimee-section-body">
                        <?php
                        $fields = $checkout->get_checkout_fields( 'billing' );
                        $coord_keys = ['billing_first_name','billing_last_name','billing_phone','billing_email'];
                        foreach ( $coord_keys as $key ) {
                            if ( isset( $fields[ $key ] ) ) {
                                woocommerce_form_field( $key, $fields[ $key ], $checkout->get_value( $key ) );
                            }
                        }
                        ?>
                    </div>
                </div>

                <!-- 2. Adresse de livraison -->
                <div class="jimee-checkout-card" id="jimee-section-2">
                    <div class="jimee-section-header">
                        <span class="jimee-section-num">2</span>
                        <h3>Adresse de livraison</h3>
                    </div>
                    <div class="jimee-section-body">
                        <?php
                        $addr_keys = ['billing_address_1','billing_state','billing_city'];
                        foreach ( $addr_keys as $key ) {
                            if ( isset( $fields[ $key ] ) ) {
                                woocommerce_form_field( $key, $fields[ $key ], $checkout->get_value( $key ) );
                            }
                        }
                        // Order notes
                        if ( isset( $checkout->get_checkout_fields( 'order' )['order_comments'] ) ) {
                            $order_fields = $checkout->get_checkout_fields( 'order' );
                            woocommerce_form_field( 'order_comments', $order_fields['order_comments'], $checkout->get_value( 'order_comments' ) );
                        }
                        ?>
                    </div>
                </div>

                <?php do_action( 'woocommerce_checkout_after_customer_details' ); ?>

            <?php endif; ?>

            <!-- 3. Livraison -->
            <div class="jimee-checkout-card" id="jimee-section-3">
                <div class="jimee-section-header">
                    <span class="jimee-section-num">3</span>
                    <h3>Livraison</h3>
                </div>
                <div class="jimee-section-body">
                    <div id="jimee-shipping-methods">
                        <?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>
                            <?php wc_cart_totals_shipping_html(); ?>
                        <?php else : ?>
                            <p class="jimee-shipping-note">Sélectionnez votre wilaya pour voir les tarifs</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 4. Paiement -->
            <div class="jimee-checkout-card" id="jimee-section-4">
                <div class="jimee-section-header">
                    <span class="jimee-section-num">4</span>
                    <h3>Paiement</h3>
                </div>
                <div class="jimee-section-body">
                    <?php do_action( 'woocommerce_checkout_before_order_review' ); ?>
                    <div id="order_review" class="woocommerce-checkout-review-order">
                        <?php do_action( 'woocommerce_checkout_order_review' ); ?>
                    </div>
                    <?php do_action( 'woocommerce_checkout_after_order_review' ); ?>
                </div>
            </div>

            <!-- Reassurance -->
            <div class="jimee-checkout-reassurance">
                <div class="jimee-reassurance-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="20" height="20"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                    <span>Paiement 100% sécurisé</span>
                </div>
                <div class="jimee-reassurance-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="20" height="20"><path d="M5 18H3a2 2 0 01-2-2v-3a2 2 0 012-2h2m14 0h2a2 2 0 012 2v3a2 2 0 01-2 2h-2"/><rect x="5" y="6" width="14" height="12" rx="2"/><path d="M12 2v4m-3 4h6"/></svg>
                    <span>Livraison partout en Algérie</span>
                </div>
                <div class="jimee-reassurance-item">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="20" height="20"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                    <span>Produits 100% authentiques</span>
                </div>
            </div>

        </div>

        <!-- ═══ RIGHT COLUMN: Récapitulatif ═══ -->
        <div class="jimee-checkout-right">
            <div class="jimee-checkout-review">

                <!-- Mobile toggle -->
                <button type="button" class="jimee-review-toggle" aria-expanded="false">
                    <span class="jimee-review-toggle-label">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="20" height="20"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
                        Récapitulatif
                        <span class="jimee-review-count">(<?php echo WC()->cart->get_cart_contents_count(); ?> article<?php echo WC()->cart->get_cart_contents_count() > 1 ? 's' : ''; ?>)</span>
                    </span>
                    <span class="jimee-review-toggle-total"><?php echo WC()->cart->get_total(); ?></span>
                    <svg class="jimee-review-chevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><polyline points="6 9 12 15 18 9"/></svg>
                </button>

                <div class="jimee-review-content">
                    <h3 class="jimee-review-title-desktop">Récapitulatif</h3>

                    <!-- Cart items with thumbnails -->
                    <div class="jimee-review-items">
                        <?php foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) :
                            $product = $cart_item['data'];
                            $img_url = get_the_post_thumbnail_url( $cart_item['product_id'], 'thumbnail' );
                            if ( ! $img_url ) $img_url = wc_placeholder_img_src( 'thumbnail' );
                        ?>
                        <div class="jimee-review-item">
                            <img src="<?php echo esc_url( $img_url ); ?>" alt="" width="56" height="56" loading="lazy">
                            <div class="jimee-review-item-info">
                                <span class="jimee-review-item-name"><?php echo esc_html( $product->get_name() ); ?></span>
                                <span class="jimee-review-item-qty">&times;<?php echo $cart_item['quantity']; ?></span>
                            </div>
                            <span class="jimee-review-item-price"><?php echo WC()->cart->get_product_subtotal( $product, $cart_item['quantity'] ); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Totals -->
                    <div class="jimee-review-totals">
                        <div class="jimee-review-row">
                            <span>Sous-total</span>
                            <span><?php echo WC()->cart->get_cart_subtotal(); ?></span>
                        </div>
                        <?php if ( WC()->cart->needs_shipping() && WC()->cart->show_shipping() ) : ?>
                        <div class="jimee-review-row">
                            <span>Livraison</span>
                            <span><?php echo WC()->cart->get_cart_shipping_total(); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php foreach ( WC()->cart->get_coupons() as $code => $coupon ) : ?>
                        <div class="jimee-review-row jimee-review-discount">
                            <span>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="14" height="14"><path d="M20.59 13.41l-7.17 7.17a2 2 0 01-2.83 0L2 12V2h10l8.59 8.59a2 2 0 010 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>
                                <?php echo esc_html( strtoupper( $code ) ); ?>
                                <a href="<?php echo esc_url( add_query_arg( 'remove_coupon', rawurlencode( $code ), wc_get_checkout_url() ) ); ?>" class="jimee-coupon-remove" title="Retirer">&times;</a>
                            </span>
                            <span>-<?php echo wc_cart_totals_coupon_totals_html( $coupon ); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Coupon input -->
                    <?php if ( wc_coupons_enabled() ) : ?>
                    <div class="jimee-coupon-form">
                        <div class="jimee-coupon-input-wrap">
                            <input type="text" id="jimee-coupon-code" placeholder="Code promo" autocomplete="off">
                            <button type="button" id="jimee-apply-coupon" class="jimee-coupon-btn">OK</button>
                        </div>
                        <div class="jimee-coupon-msg" id="jimee-coupon-msg"></div>
                    </div>
                    <?php endif; ?>

                    <!-- Total -->
                    <div class="jimee-review-total">
                        <span>Total</span>
                        <span><?php echo WC()->cart->get_total(); ?></span>
                    </div>

                    <!-- Edit cart link -->
                    <a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="jimee-review-edit">Modifier le panier</a>
                </div>
            </div>
        </div>

    </div>

</form>

<!-- Mobile sticky CTA -->
<div class="jimee-mobile-cta">
    <div class="jimee-mobile-cta-inner">
        <div class="jimee-mobile-cta-total">
            <span>Total</span>
            <strong><?php echo WC()->cart->get_total(); ?></strong>
        </div>
        <button type="button" class="jimee-mobile-cta-btn" id="jimee-mobile-submit">Confirmer la commande</button>
    </div>
</div>
