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

use WC_Order;
use WooBordereauGenerator\Admin\Partials\BordereauGeneratorProviders;

class BordereauTrackingMetaBox
{

	/**
	 * @param $post
	 *
	 * @return void
	 */
    public static function output( $post ) {

        global $theorder;

        if ( ! is_object( $theorder ) ) {
            $theorder = wc_get_order( $post->ID );
        }

        $theorder = $theorder instanceof WC_Order ? $theorder : null;

        $trackingNumber = get_post_meta($theorder->get_id(), '_shipping_tracking_number', true);
        if(! $trackingNumber) {
            $trackingNumber = $theorder->get_meta('_shipping_tracking_number');
        }
        ?>

        <div id="bordereau_tracking"
             data-license="<?php echo get_bordreau_generator_integrity(); ?>"
             data-tracking="<?php echo $trackingNumber; ?>"
             data-post="<?php echo $theorder->get_id(); ?>">
        </div>
        <?php

    }

	/**
	 * @param $post_id
	 * @param $provider
	 * @param $tracking_number
	 *
	 * @return void
	 */
    static function track($post_id) {
	    global $wpdb, $theorder;

	    if ( ! is_object( $theorder ) ) {
		    $theorder = wc_get_order( $post_id );
	    }


	    $provider = get_post_meta($post_id, '_shipping_tracking_method', true);
	    $tracking_number = get_post_meta($post_id, '_shipping_tracking_number', true);

        if(! $tracking_number) {
            $tracking_number = $theorder->get_meta('_shipping_tracking_number');
        }

        if(! $provider) {
            $provider = $theorder->get_meta('_shipping_tracking_method');
        }

	    if ($provider && $tracking_number) {

		    $theorder = $theorder instanceof WC_Order ? $theorder : null;

		    $providers = BordereauGeneratorProviders::get_providers();
		    $selectedProvider = $providers[$provider] ?? null;

		    if($selectedProvider) {
			    // Check if the class exist
			    if(class_exists($selectedProvider['class'])) {
				    $class = new $selectedProvider['class']($theorder, $selectedProvider);
                    $class->track($tracking_number, $post_id);
			    }
		    }
        }
    }


    /**
     * @param $post_id
     * @return void
     */
    static function detail($post_id) {

	    global $wpdb, $theorder;

	    if ( ! is_object( $theorder ) ) {
		    $theorder = wc_get_order( $post_id );
	    }

	    $provider = get_post_meta($post_id, '_shipping_tracking_method', true);
	    $tracking_number = get_post_meta($post_id, '_shipping_tracking_number', true);

        if(! $tracking_number) {
            $tracking_number = $theorder->get_meta('_shipping_tracking_number');
        }

        if(! $provider) {
            $provider = $theorder->get_meta('_shipping_tracking_method');
        }

	    if ($provider && $tracking_number) {

		    $theorder = $theorder instanceof WC_Order ? $theorder : null;

		    $providers = BordereauGeneratorProviders::get_providers();
		    $selectedProvider = $providers[$provider] ?? null;

		    if($selectedProvider) {
			    // Check if the class exist
			    if(class_exists($selectedProvider['class'])) {
				    $class = new $selectedProvider['class']($theorder, $selectedProvider);
				    $class->detail($tracking_number, $post_id);
			    }
		    }
	    }


    }
}
