<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    WC_Bordereau_Generator
 * @subpackage WC_Bordereau_Generator/admin/metabox
 * @author     Boukraa Mohamed <bm2ilabs@gmail.com>
 */

namespace WooBordereauGenerator\Admin\MetaBox;

use ErrorException;
use WC_Order;
use WooBordereauGenerator\Admin\Partials\BordereauGeneratorProviders;

class BordereauGeneratorMetaBox
{

    /**
     * Output the page
     * @param $post
     * @return void
     */
    public static function output($post)
    {
        global $theorder;

        if (! is_object($theorder)) {
            $theorder = wc_get_order($post->ID);
        }
        $theorder = $theorder instanceof WC_Order ? $theorder : null;

        $provider = get_post_meta($theorder->get_id(), '_shipping_tracking_method', true);

        if(! $provider) {
            $provider = $theorder->get_meta('_shipping_tracking_method');
        }
        $tracking_number = get_post_meta($theorder->get_id(), '_shipping_tracking_number', true);
        if(! $tracking_number) {
            $tracking_number = $theorder->get_meta('_shipping_tracking_number');
        }

	    $nonce = wp_create_nonce('wp_rest');
	    $base_url = get_rest_url(null, 'woo-bordereau/v1/slip/print/'.$provider.'/'.$theorder->get_id());
	    $url =  add_query_arg('_wpnonce', $nonce, $base_url);
        ?>
        <div id="bordereau_selector"
             data-post="<?php echo $theorder->get_id(); ?>"
             data-tracking="<?php echo $tracking_number; ?>"
             data-label-method="<?php echo get_post_meta($theorder->get_id(), '_shipping_tracking_label_method', true); ?>"
             data-url="<?php echo get_post_meta($theorder->get_id(), '_shipping_tracking_url', true); ?>"
             data-advance="<?php echo get_option('maystro_delivery_advance') ? get_option('maystro_delivery_advance') : 'without-advance-sync'; ?>"
             data-label="<?php echo $url; ?>"
             data-method="<?php echo $provider; ?>">
        </div>
        <?php
    }

    /**
     * Save the settings
     * @param $post_id
     * @param $post
     * @return void
     */
    static function save($post_id, $post)
    {
        global $wpdb, $theorder;

        if (! is_object($theorder)) {
            $theorder = wc_get_order($post->ID);
        }

        if (isset($_POST['wc_service_shipping'])) {
            // save the data

            $provider = get_post_meta($post->ID, '_shipping_tracking_method', true);
            if(! $provider) {
                $provider = $theorder->get_meta('_shipping_tracking_method');
            }

            $tracking_number = get_post_meta($post->ID, '_shipping_tracking_number', true);

            if(! $tracking_number) {
                $tracking_number = $theorder->get_meta('_shipping_tracking_number');
            }


            // to prevent from duplication
            if (! $tracking_number || $provider != wc_clean($_POST[ 'wc_service_shipping'])) {
                update_post_meta($post_id, '_shipping_tracking_method', wc_clean(sanitize_text_field($_POST[ 'wc_service_shipping'])));
                update_post_meta($post_id, '_shipping_tracking_number', null);
                update_post_meta($post_id, '_shipping_tracking_label', null);

                $theorder = $theorder instanceof WC_Order ? $theorder : null;

                $providers = BordereauGeneratorProviders::get_providers();
                $selectedProvider = $providers[sanitize_text_field($_POST['wc_service_shipping'])] ?? null;
                if ($selectedProvider) {
                    // Check if the class exist
                    if (class_exists($selectedProvider['class'])) {
                        $class = new $selectedProvider['class']($theorder, $selectedProvider);
                        $response = $class->generate();
                        if (is_array($response)) {
                            $result = $class->save($post_id, $response);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param array $data
     * @return void
     */
    static function save_ajax(array $data)
    {
        global $wpdb, $theorder;

        if (! $data['post_id']) {
            die();
        }

        if (! is_object($theorder)) {
            $theorder = wc_get_order(sanitize_text_field($data['post_id']));
        }

        if ($data['provider']) {
            // save the data
            $post_id = sanitize_text_field($data['post_id']);
            $provider = sanitize_text_field($data['provider']);

            // to prevent from duplication
//          if(! get_post_meta($post_id, '_shipping_tracking_number', true) || (isset($data['is_update']) && $data['is_update']) ) {

            $provider_meta = get_post_meta($post_id, '_shipping_tracking_method', true);

            if(! $provider_meta) {
                $provider_meta = $theorder->get_meta('_shipping_tracking_method');
            }
                // check if the provider has been changed so we don't ereas the current one
            if ($provider_meta != $provider) {
                update_post_meta($post_id, '_shipping_tracking_number', null);
                update_post_meta($post_id, '_shipping_tracking_label', null);
            }
                update_post_meta($post_id, '_shipping_tracking_method', $provider);

                $theorder = $theorder instanceof WC_Order ? $theorder : null;

                $providers = BordereauGeneratorProviders::get_providers();
                $selectedProvider = $providers[$provider] ?? null;
            if ($selectedProvider) {
                // Check if the class exist
                if (class_exists($selectedProvider['class'])) {
                    $class = new $selectedProvider['class']($theorder, $selectedProvider, $data);
                    $response = $class->generate();
                    if (is_array($response)) {
                        $class->save($post_id, $response, $data['is_update'] ?? false);
                    }
                }
            }
//          }
        }
    }

    /**
     * @param $post_id
     * @param $post
     * @return void
     */
    static function delete($post_id, $post)
    {

        global $wpdb, $theorder;

        if (! is_object($theorder)) {
            $theorder = wc_get_order($post->ID);
        }


        $provider = get_post_meta($post_id, '_shipping_tracking_method', true);
        $trackingNumber = get_post_meta($post_id, '_shipping_tracking_number', true);

        if(! $trackingNumber) {
            $trackingNumber = $theorder->get_meta('_shipping_tracking_number');
        }

        if(! $provider) {
            $provider = $theorder->get_meta('_shipping_tracking_method');
        }

        if ($provider) {
            $providers = BordereauGeneratorProviders::get_providers();
            $selectedProvider = $providers[$provider] ?? null;

            if ($selectedProvider) {
                if (class_exists($selectedProvider['class'])) {

                    $class = new $selectedProvider['class']($theorder, $selectedProvider);

                    try {
                        $class->delete($trackingNumber, $post->ID);
                    } catch (ErrorException $error_exception) {

                    }



                    $order = wc_get_order($post->ID);
                    $old_tracking_status = '';

                    if ($order) {
                        $old_tracking_status = $order->get_meta('_latest_tracking_status');
                    }

                    delete_post_meta($post_id, '_shipping_tracking_method');
                    delete_post_meta($post_id, '_shipping_tracking_label');
                    delete_post_meta($post_id, '_shipping_tracking_number');
                    delete_post_meta($post_id, '_latest_tracking_status');
                    delete_post_meta($post_id, '_latest_tracking_status');
                    delete_post_meta($post_id, '_shipping_tracking_meta');
                    delete_post_meta($post_id, '_shipping_maystro_order_id');

                    if ($order) {
                        $order->delete_meta_data('_shipping_tracking_number');
                        $order->delete_meta_data('_shipping_tracking_label');
                        $order->delete_meta_data('_shipping_label');
                        $order->delete_meta_data('_shipping_tracking_method');
                        $order->delete_meta_data('_shipping_tracking_meta');
                        $order->delete_meta_data('_shipping_maystro_order_id');
                        $order->delete_meta_data('_latest_tracking_status');
                        $order->save();

                        if (!empty($old_tracking_status)) {
                            /**
                             * Fires when an order's tracking status is removed (tracking deleted).
                             *
                             * @since 5.0.0
                             *
                             * @param int       $order_id          The WooCommerce order ID.
                             * @param string    $old_tracking_status The tracking status before deletion.
                             * @param \WC_Order $order             The WooCommerce order object.
                             */
                            do_action('woo_bordereau_tracking_status_removed', $order->get_id(), $old_tracking_status, $order);
                        }
                    }

                    wp_send_json([
                        'success' => true,
                        'message'=>  __("Tracking number has been deleted successfully", 'woo-bordereau-generator')
                    ], 200);
                }
            }
        }
    }
}
