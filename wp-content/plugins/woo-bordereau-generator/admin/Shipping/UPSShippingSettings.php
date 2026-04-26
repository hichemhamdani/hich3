<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    WC_Bordereau_Generator
 * @subpackage WC_Bordereau_Generator/admin/shipping
 * @author     Boukraa Mohamed <bm2ilabs@gmail.com>
 */

namespace WooBordereauGenerator\Admin\Shipping;

use Exception;
use UPS\OAuthAuthCode\Request\DefaultApi;
use UPS\OAuthClientCredentials\Configuration;
use WC_Shipping_Zone;
use WC_Shipping_Zones;
use WP_Error;

class UPSShippingSettings
{

    /**
     * @var array
     */
    private $provider;

    public function __construct(array $provider)
    {
        $this->provider = $provider;
    }

    /**
     * Track the packages in bulk
     * @param array $codes
     *
     * @return array
     */
    public function bulk_track(array $codes)
    {

    }


    /**
     * @param $json
     *
     * @return array
     */
    private function format_tracking($json)
    {

    }


    /**
     *
     * Get Shipping classes
     * @since 1.6.5
     * @return void
     */
    public function import_shipping_class($flat_rate_label, $pickup_local_label,  $flat_rate_enabled, $pickup_local_enabled, $agency_import_enabled = null, $is_economic = null)
    {


    }

    /**
     * @param $response_array
     * @return void
     */
    private function shipping_zone_grouped($response_array)
    {

    }

    /**
     * @param $response_array
     * @param $flat_rate_label
     * @param $pickup_local_label
     * @param $flat_rate_enabled
     * @param $pickup_local_enabled
     * @return array
     */
    private function shipping_zone_ungrouped($response_array, $flat_rate_label, $pickup_local_label,  $flat_rate_enabled, $pickup_local_enabled)
    {

    }


    /**
     * @param $order_ids
     * @return mixed|void
     */
    public function bulk_add_orders($order_ids) {

    }



}
