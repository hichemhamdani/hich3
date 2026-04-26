<?php

namespace WooBordereauGenerator\Admin\Shortcodes;

use WC_Shortcodes;

class BordereauTrackingShortCode
{
    /**
     * Normalize an Algerian phone number to local format (0XXXXXXXXX).
     * Strips spaces and converts +213XXXXXXXXX or 213XXXXXXXXX to 0XXXXXXXXX.
     *
     * @param string $phone
     * @return string
     */
    private static function normalize_phone( $phone ) {
        // Remove all spaces and dashes
        $phone = preg_replace('/[\s\-]/', '', $phone);
        // +213XXXXXXXXX → 0XXXXXXXXX
        if ( substr( $phone, 0, 4 ) === '+213' ) {
            $phone = '0' . substr( $phone, 4 );
        }
        // 213XXXXXXXXX → 0XXXXXXXXX (without +)
        elseif ( substr( $phone, 0, 3 ) === '213' && strlen( $phone ) >= 12 ) {
            $phone = '0' . substr( $phone, 3 );
        }
        return strtolower( $phone );
    }

    /**
     * Get the shortcode content.
     *
     * @param array $atts Shortcode attributes.
     * @return string
     */
    public static function get( $atts ) {
        return WC_Shortcodes::shortcode_wrapper( array( __CLASS__, 'output' ), $atts );
    }

    /**
     * Output the shortcode.
     *
     * @param array $atts Shortcode attributes.
     */
    public static function output( $atts ) {
        // Check cart class is loaded or abort.
        if ( is_null( WC()->cart ) ) {
            return;
        }

        $atts = shortcode_atts( array(), $atts, 'bordreau_tracking' );



        $nonce_value = wc_get_var( $_REQUEST['woocommerce-order-tracking-nonce'], wc_get_var( $_REQUEST['_wpnonce'], '' ) ); // @codingStandardsIgnoreLine.

        if ( isset( $_REQUEST['orderid'] ) && wp_verify_nonce( $nonce_value, 'woocommerce-order_tracking' ) ) { // WPCS: input var ok.

            $order_id    = empty( $_REQUEST['orderid'] ) ? 0 : ltrim( wc_clean( wp_unslash( $_REQUEST['orderid'] ) ), '#' ); // WPCS: input var ok.
            $order_phone = empty( $_REQUEST['order_phone'] ) ? '' : sanitize_text_field(wc_clean( wp_unslash( $_REQUEST['order_phone'] ) )); // WPCS: input var ok.

            if ( ! $order_id ) {
                wc_print_notice( __( 'Please enter a valid order ID', 'woocommerce' ), 'error' );
            } elseif ( ! $order_phone ) {
                wc_print_notice( __( 'Please enter a valid email address', 'woocommerce' ), 'error' );
            } else {
                $order = wc_get_order( apply_filters( 'woocommerce_shortcode_order_tracking_order_id', $order_id ) );

                if ( $order && $order->get_id() && is_a( $order, 'WC_Order' ) && self::normalize_phone( $order->get_billing_phone() ) === self::normalize_phone( $order_phone ) ) {
                    do_action( 'woocommerce_track_order', $order->get_id() );

                    wc_get_template(
                        'order/tracking.php',
                        array(
                            'order' => $order,
                        ),
                        plugin_dir_path(WC_BORDEREAU_PLUGIN_PATH) . 'woocommerce/templates/'
                    );
                    return;
                } else {
                    wc_print_notice( __( 'Sorry, the order could not be found. Please contact us if you are having difficulty finding your order details.', 'woocommerce' ), 'error' );
                }
            }
        }

        wc_get_template(
            'order/form-tracking.php',
            array(

            ),
            plugin_dir_path(WC_BORDEREAU_PLUGIN_PATH) . 'woocommerce/templates/',
            plugin_dir_path(WC_BORDEREAU_PLUGIN_PATH) . 'woocommerce/templates/'
        );
    }
}