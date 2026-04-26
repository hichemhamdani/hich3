<?php
/**
 * Order tracking form
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/order/form-tracking.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 7.0.1
 */

defined( 'ABSPATH' ) || exit;

global $post;
?>

<form action="<?php echo esc_url( get_permalink( $post->ID ) ); ?>" method="post" class="woocommerce-form woocommerce-form-track-order track_order">

    <?php
    /**
     * Action hook fired at the beginning of the form-tracking form.
     *
     * @since 6.5.0
     */
    do_action( 'woocommerce_order_tracking_form_start' );
    ?>

    <p class="form-row"><label for="tracking_number"><?php esc_html_e( 'Tracking Number', 'woocommerce' ); ?></label> <input class="input-text" type="text" name="tracking_number" id="tracking_number" value="<?php echo isset( $_REQUEST['tracking_number'] ) ? esc_attr( wp_unslash( $_REQUEST['tracking_number'] ) ) : ''; ?>" placeholder="<?php esc_attr_e( 'Tracking number from the shipping company.', 'woocommerce' ); ?>" /></p><?php // @codingStandardsIgnoreLine ?>
    <div class="clear"></div>

    <?php
    /**
     * Action hook fired in the middle of the form-tracking form (before the submit button).
     *
     * @since 6.5.0
     */
    do_action( 'woocommerce_order_tracking_form' );
    ?>

    <p class="form-row"><button type="submit" class="button<?php echo esc_attr( wc_wp_theme_get_element_class_name( 'button' ) ? ' ' . wc_wp_theme_get_element_class_name( 'button' ) : '' ); ?>" name="track" value="<?php esc_attr_e( 'Track', 'woocommerce' ); ?>"><?php esc_html_e( 'Track', 'woocommerce' ); ?></button></p>
    <?php wp_nonce_field( 'woocommerce-order_tracking', 'woocommerce-order-tracking-nonce' ); ?>

    <?php
    /**
     * Action hook fired at the end of the form-tracking form (after the submit button).
     *
     * @since 6.5.0
     */
    do_action( 'woocommerce_order_tracking_form_end' );
    ?>

</form>
