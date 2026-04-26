<?php

namespace WooBordereauGenerator\Admin\Shortcodes;

use WC_Shortcodes;

class BordereauTrackingByTrackingNumberShortCode
{
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

        if ( isset( $_REQUEST['tracking_number'] ) && wp_verify_nonce( $nonce_value, 'woocommerce-order_tracking' ) ) { // WPCS: input var ok.

            $order_id = empty( $_REQUEST['tracking_number'] ) ? 0 : ltrim( wc_clean( wp_unslash( $_REQUEST['tracking_number'] ) ), '#' ); // WPCS: input var ok.

            if ( ! $order_id ) {
                wc_print_notice( __( 'Please enter a valid Tracking number', 'woocommerce' ), 'error' );
            } else {

                $args = array(
                    'post_type' => 'shop_order',
                    'post_status' => 'any',
                    'posts_per_page' => -1,
                    'meta_query' => array(
                        array(
                            'key' => '_shipping_tracking_number',
                            'value' => $order_id,
                            'compare' => '='
                        )
                    )
                );

                $posts = get_posts($args);

                if ( count($posts)) {

                    $post = reset($posts);
                    $order = wc_get_order($post->ID);

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
            'order/form-tracking-only-code.php',
            array(

            ),
            plugin_dir_path(WC_BORDEREAU_PLUGIN_PATH) . 'woocommerce/templates/',
            plugin_dir_path(WC_BORDEREAU_PLUGIN_PATH) . 'woocommerce/templates/'
        );
    }
}