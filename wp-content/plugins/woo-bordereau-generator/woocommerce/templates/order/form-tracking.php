<?php
/**
 * Order tracking form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/order/form-tracking.php.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 7.0.1
 */

defined( 'ABSPATH' ) || exit;

global $post;
?>

<style>
.bdt-tracking-wrap {
    min-height: 60vh;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 2rem 1rem;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
}
.bdt-tracking-card {
    background: #fff;
    border-radius: 24px;
    box-shadow: 0 8px 48px rgba(0,0,0,0.08), 0 1px 4px rgba(0,0,0,0.04);
    padding: 3rem 2.5rem 2.5rem;
    width: 100%;
    max-width: 580px;
    text-align: center;
}
.bdt-tracking-card h1 {
    font-size: clamp(2.8rem, 7vw, 4.2rem) !important;
    font-weight: 900 !important;
    line-height: 1.05 !important;
    letter-spacing: -0.03em !important;
    color: #111 !important;
    margin: 0 0 1rem !important;
    padding: 0 !important;
    border: none !important;
}
.bdt-tracking-card .bdt-subtitle {
    font-size: 0.95rem;
    color: #999;
    margin: 0 0 2rem;
    line-height: 1.5;
}
.bdt-field-group {
    margin-bottom: 0.875rem;
    text-align: left;
    width: 100%;
}
.bdt-field-group label {
    display: none !important;
}
.bdt-field-group input[type="text"] {
    width: 100% !important;
    border: 1.5px solid #e5e5e5 !important;
    border-radius: 14px !important;
    padding: 1rem 1.25rem !important;
    font-size: 1rem !important;
    color: #111 !important;
    background: #fff !important;
    box-shadow: none !important;
    outline: none !important;
    box-sizing: border-box !important;
    transition: border-color 0.2s ease !important;
    -webkit-appearance: none !important;
}
.bdt-field-group input[type="text"]:focus {
    border-color: #111 !important;
    box-shadow: none !important;
}
.bdt-field-group input[type="text"]::placeholder {
    color: #bbb !important;
}
.bdt-submit-btn {
    width: 100%;
    background: #111 !important;
    color: #fff !important;
    border: none !important;
    border-radius: 100px !important;
    padding: 1rem 2rem !important;
    font-size: 1rem !important;
    font-weight: 700 !important;
    letter-spacing: -0.01em !important;
    cursor: pointer !important;
    margin-top: 0.5rem !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 0.5rem !important;
    transition: background 0.2s ease, transform 0.1s ease !important;
    box-shadow: none !important;
    text-decoration: none !important;
}
.bdt-submit-btn:hover {
    background: #333 !important;
    transform: translateY(-1px) !important;
    color: #fff !important;
}
.bdt-submit-btn:active {
    transform: translateY(0) !important;
}
</style>

<div class="bdt-tracking-wrap">
    <div class="bdt-tracking-card">
        <h1><?php esc_html_e( 'Suivre ma commande', 'woo-bordereau-generator' ); ?></h1>
        <p class="bdt-subtitle"><?php esc_html_e( 'Saisissez votre numéro de commande et votre téléphone pour suivre votre colis.', 'woo-bordereau-generator' ); ?></p>

        <form action="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" method="post" class="woocommerce-form woocommerce-form-track-order track_order">

            <?php do_action( 'woocommerce_order_tracking_form_start' ); ?>

            <div class="bdt-field-group">
                <label for="orderid"><?php esc_html_e( 'Order ID', 'woocommerce' ); ?></label>
                <input class="input-text" type="text" name="orderid" id="orderid"
                    value="<?php echo isset( $_REQUEST['orderid'] ) ? esc_attr( wp_unslash( $_REQUEST['orderid'] ) ) : ''; ?>"
                    placeholder="<?php esc_attr_e( 'N° de commande', 'woo-bordereau-generator' ); ?>" />
            </div>

            <div class="bdt-field-group">
                <label for="order_phone"><?php esc_html_e( 'Billing phone', 'woocommerce' ); ?></label>
                <input class="input-text" type="text" name="order_phone" id="order_phone"
                    value="<?php echo isset( $_REQUEST['order_phone'] ) ? esc_attr( wp_unslash( $_REQUEST['order_phone'] ) ) : ''; ?>"
                    placeholder="<?php esc_attr_e( 'Ex : 0661 77 11 66', 'woo-bordereau-generator' ); ?>" />
            </div>

            <?php do_action( 'woocommerce_order_tracking_form' ); ?>

            <button type="submit" class="bdt-submit-btn" name="track" value="Track">
                <span>🔍</span>
                <span><?php esc_html_e( 'Suivre ma commande', 'woo-bordereau-generator' ); ?></span>
            </button>

            <?php wp_nonce_field( 'woocommerce-order_tracking', 'woocommerce-order-tracking-nonce' ); ?>

            <?php do_action( 'woocommerce_order_tracking_form_end' ); ?>

        </form>
    </div>
</div>
