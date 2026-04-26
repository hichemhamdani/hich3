<?php
/**
 * Thankyou page — Jimee Cosmetics
 * Clean, card-based confirmation with order summary.
 *
 * @version 8.1.0
 * @var WC_Order $order
 */

if ( ! defined( 'ABSPATH' ) ) exit;
?>

<div class="woocommerce-order jimee-thankyou">

<?php if ( $order ) :
    do_action( 'woocommerce_before_thankyou', $order->get_id() );

    if ( $order->has_status( 'failed' ) ) : ?>
        <div class="jimee-ty-card jimee-ty-error">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="28" height="28"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
            <div>
                <h3>Paiement échoué</h3>
                <p>Votre transaction a été refusée. Veuillez réessayer.</p>
                <a href="<?php echo esc_url( $order->get_checkout_payment_url() ); ?>" class="jimee-ty-btn">Réessayer le paiement</a>
            </div>
        </div>
    <?php else : ?>

        <!-- Success -->
        <div class="jimee-ty-card jimee-ty-success">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="36" height="36"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
            <div>
                <h3>Commande confirmée</h3>
                <p>Merci pour votre commande ! Vous recevrez un appel de confirmation sous peu.</p>
            </div>
        </div>

        <!-- Order overview -->
        <div class="jimee-ty-card jimee-ty-overview">
            <div class="jimee-ty-overview-grid">
                <div class="jimee-ty-overview-item">
                    <span class="jimee-ty-label">N° commande</span>
                    <strong><?php echo $order->get_order_number(); ?></strong>
                </div>
                <div class="jimee-ty-overview-item">
                    <span class="jimee-ty-label">Date</span>
                    <strong><?php echo wc_format_datetime( $order->get_date_created() ); ?></strong>
                </div>
                <div class="jimee-ty-overview-item">
                    <span class="jimee-ty-label">Total</span>
                    <strong><?php echo $order->get_formatted_order_total(); ?></strong>
                </div>
                <div class="jimee-ty-overview-item">
                    <span class="jimee-ty-label">Paiement</span>
                    <strong><?php echo wp_kses_post( $order->get_payment_method_title() ); ?></strong>
                </div>
            </div>
        </div>

        <!-- Order details table -->
        <div class="jimee-ty-card">
            <h3 class="jimee-ty-section-title">Détails de la commande</h3>
            <div class="jimee-ty-products">
                <?php foreach ( $order->get_items() as $item ) :
                    $product = $item->get_product();
                    $img_url = $product ? get_the_post_thumbnail_url( $product->get_id(), 'thumbnail' ) : '';
                    if ( ! $img_url ) $img_url = wc_placeholder_img_src( 'thumbnail' );
                ?>
                <div class="jimee-ty-product">
                    <img src="<?php echo esc_url( $img_url ); ?>" alt="" width="56" height="56" loading="lazy">
                    <div class="jimee-ty-product-info">
                        <span class="jimee-ty-product-name"><?php echo esc_html( $item->get_name() ); ?></span>
                        <span class="jimee-ty-product-qty">&times;<?php echo $item->get_quantity(); ?></span>
                    </div>
                    <span class="jimee-ty-product-price"><?php echo $order->get_formatted_line_subtotal( $item ); ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="jimee-ty-totals">
                <div class="jimee-ty-totals-row">
                    <span>Sous-total</span>
                    <span><?php echo wc_price( $order->get_subtotal() ); ?></span>
                </div>
                <?php if ( $order->get_shipping_total() > 0 ) : ?>
                <div class="jimee-ty-totals-row">
                    <span>Expédition</span>
                    <span><?php echo wc_price( $order->get_shipping_total() ); ?> <small>via <?php echo esc_html( $order->get_shipping_method() ); ?></small></span>
                </div>
                <?php elseif ( $order->get_shipping_method() ) : ?>
                <div class="jimee-ty-totals-row">
                    <span>Expédition</span>
                    <span>Gratuit <small>via <?php echo esc_html( $order->get_shipping_method() ); ?></small></span>
                </div>
                <?php endif; ?>
                <?php foreach ( $order->get_coupons() as $coupon ) : ?>
                <div class="jimee-ty-totals-row jimee-ty-discount">
                    <span>Coupon : <?php echo esc_html( strtoupper( $coupon->get_code() ) ); ?></span>
                    <span>-<?php echo wc_price( $coupon->get_discount() ); ?></span>
                </div>
                <?php endforeach; ?>
                <div class="jimee-ty-totals-total">
                    <span>Total</span>
                    <span><?php echo $order->get_formatted_order_total(); ?></span>
                </div>
            </div>
        </div>

        <!-- Billing address -->
        <div class="jimee-ty-card">
            <h3 class="jimee-ty-section-title">Adresse de livraison</h3>
            <address class="jimee-ty-address">
                <?php echo wp_kses_post( $order->get_formatted_billing_address() ); ?>
                <?php if ( $order->get_billing_phone() ) : ?>
                    <br><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="14" height="14"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
                    <?php echo esc_html( $order->get_billing_phone() ); ?>
                <?php endif; ?>
            </address>
        </div>

    <?php endif; ?>

    <?php do_action( 'woocommerce_thankyou', $order->get_id() ); ?>

<?php else : ?>
    <div class="jimee-ty-card jimee-ty-success">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="36" height="36"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        <div>
            <h3>Merci pour votre commande</h3>
        </div>
    </div>
<?php endif; ?>

</div>
