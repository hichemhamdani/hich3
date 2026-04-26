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

use WC_Shipping_Zone;
use WC_Shipping_Zones;
use WooBordereauGenerator\Functions;
use WooBordereauGenerator\Helpers;
use WP_Error;

class ThreeMExpressSettings
{
    /**
     * @var array
     */
    private $provider;
    /**
     * @var false|mixed|null
     */
    protected $api_id;
    /**
     * @var false|mixed|null
     */
    private $api_token;

    public function __construct(array $provider)
    {
        $this->api_id = get_option('3mexpress_username');
        $this->api_token = get_option('3mexpress_password');
        $this->provider = $provider;
    }

    /**
     * @return array[]
     */
    public function get_settings()
    {

        $fields = [
            array(
                'title' => __($this->provider['name'] . ' API Settings', 'woocommerce'),
                'type' => 'title',
                'id' => 'store_address',
            ),
        ];

        foreach ($this->provider['fields'] as $key => $filed) {
            $fields[$key] = array(
                'name' => __($filed['name'], 'woo-bordereau-generator'),
                'id' => $filed['value'],
                'type' => 'text',
                'desc' => $filed['desc'],
                'desc_tip' => true,
            );
        }

        $fields = $fields + [
                'section_end' => array(
                    'type' => 'sectionend',
                    'id' => $this->provider['slug'] . '_section_end'
                )
            ];

        return $fields;
    }


    /**
     * Track the packages in bulk
     * @param array $codes
     *
     * @return array|false
     */
    public function bulk_track(array $codes)
    {

        $hasMore = true;

        $trackingResult = [];
        while ($hasMore) {
            $url = $this->provider['api_url'] . '/v1/orders?skip=0&take=1000';

            $request = new ThreeMExpressRequest($this->provider);
            $content = $request->get($url);

            if (count($content["data"]) < 1000) {
                $trackingResult = $content['data'] + $trackingResult;
                $hasMore = false;
            }
        }

        return $this->format_tracking($trackingResult);
    }

    /**
     * Status Default
     * @return string[]
     */
    private function status(): array
    {

        return [
            "PLACED" => "Placed",
            "PENDING_RECEPTION" => "Pending reception",
            "AWAITING_TRANSIT" => "Awaiting transit",
            "IN_TRANSIT" => "In transit",
            "OUT_FOR_DELIVERY" => "Out for delivery",
            "READY_FOR_DELIVERY" => "Ready for delivery",
            "RETURNED" => "Returned",
            "IN_RETURN" => "In return",
            "DELIVERED" => "Delivered",
            "RETURN_COLLECTED" => "Return collected",
        ];
    }

    private function format_tracking($json)
    {
        if ($json) {
            $result = [];

            foreach ($json as $key => &$entry) {
                $result[$entry['trackingId']][$key] = $entry;
            }

            $final = [];
            foreach ($result as $key => $value) {
                $final[$key] = $this->status()[array_values($value)[0]['status']] ?? null;
            }
            return $final;
        }

        return [];
    }


    /**
     *
     * Get Shipping classes
     * @return void
     * @since 1.6.5
     */
    public function import_shipping_class($flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled, $agency_import_enabled = null, $is_economic = null)
    {

        if ($pickup_local_enabled) {

            if ($pickup_local_label) {
                if (get_option($this->provider['slug'] . '_pickup_local_label')) {
                    update_option($this->provider['slug'] . '_pickup_local_label', $pickup_local_label);
                } else {
                    add_option($this->provider['slug'] . '_pickup_local_label', $pickup_local_label);
                }
            }
        }


        if ($flat_rate_enabled) {
            if ($flat_rate_label) {
                if (get_option($this->provider['slug'] . '_flat_rate_label')) {
                    update_option($this->provider['slug'] . '_flat_rate_label', $flat_rate_label);
                } else {
                    add_option($this->provider['slug'] . '_flat_rate_label', $flat_rate_label);
                }
            }
        }


        $request = new ThreeMExpressRequest($this->provider);
        $userInfo = $request->getUserInfo();


        $userWilayaId = $userInfo['stores'][0]['municipality']['provinceId'] ?? null;

        if ($userWilayaId) {

            $wilayas = $this->get_wilayas();

            $prices = [];
            $types = ['STOPDESK', 'DOORSTEP'];

            $path = Functions::get_path('3mexpress_fees.json');

            if (!file_exists($path)) {

                foreach ($types as $type) {
                    foreach ($wilayas as $wilaya) {
                        $data = [
                            'source' => $userWilayaId,
                            'destination' => $wilaya['id'],
                            "weight" => 7,
                            "volume" => 0.01,
                            "serviceType" => "ECO",
                            "deliveryType" => $type,
                            "profile" => "default",
                            "operationType" => "DELIVERY"
                        ];

                        $content = $request->post($this->provider['api_url'] . '/customers/orders/pricing', $data);
                        $prices[$wilaya['id']][$type]['price'] = $content['totalPrice'];
                        $prices[$wilaya['id']][$type]['wilaya'] = $wilaya['name_latin'];
                        $prices[$wilaya['id']][$type]['wilaya_id'] = $wilaya['id'];

                    }
                }

                file_put_contents($path, json_encode($prices));
            }

            $prices = json_decode(file_get_contents($path), true);


            if (get_option('shipping_zones_grouped') && get_option('shipping_zones_grouped') == 'yes') {
                $this->shipping_zone_grouped($prices, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled);
            } else {
                $this->shipping_zone_ungrouped($prices, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled);
            }

            wp_send_json_success(
                array(
                    'message' => 'success',
                )
            );
        }
    }

    private function shipping_zone_grouped($livraison, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled)
    {

    }

    private function shipping_zone_ungrouped($livraison, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled)
    {

        // Set memory limit to -1 (unlimited) and set time limit to 0 (unlimited)
        ini_set('memory_limit', '-1');
		set_time_limit(0);

        $zones = [];

        $shipping_method_local_pickup = null;
        $shipping_method_flat_rate = null;

        $i = 1;
        foreach ($livraison as $key => $wilaya) {

            $priceStopDesk = $wilaya['STOPDESK'];
            $priceFlatRate = $wilaya['DOORSTEP'];

            $wilaya_name = $priceFlatRate['wilaya'];

            // Get all shipping zones
            $zones = WC_Shipping_Zones::get_zones();

            // Iterate through the zones and find the one with the matching name
            $found_zone = null;
            foreach ($zones as $zone) {
                if ($zone['zone_locations'][0]->code == 'DZ:DZ-' . str_pad($priceFlatRate['wilaya_id'], 2, '0',STR_PAD_LEFT)) {
                    $found_zone = $zone;
                    break;
                }
            }

            if (!$found_zone) {
                $zone = new WC_Shipping_Zone();
                $zone->set_zone_name(ucfirst(Helpers::normalizeCityName($wilaya_name)));
                $zone->set_zone_order($key);
                $zone->add_location('DZ:DZ-' . str_pad($priceFlatRate['wilaya_id'], 2, '0', STR_PAD_LEFT), 'state');
                $zone->save();
            } else {
                $zone = WC_Shipping_Zones::get_zone($found_zone['id']);
            }

            if ($priceFlatRate && $flat_rate_enabled) {

                $flat_rate_name = 'flat_rate_3m_express';
                if (is_hanout_enabled()) {
                    $flat_rate_name = 'flat_rate';
                }

                $instance_flat_rate = $zone->add_shipping_method($flat_rate_name);
                $shipping_method_flat_rate = WC_Shipping_Zones::get_shipping_method($instance_flat_rate);
                $shipping_method_configuration_flat_rate = [
                    'woocommerce_'.$flat_rate_name.'_title' => $flat_rate_label ?? __('Flat rate', 'woo-bordereau-generator'),
                    'woocommerce_'.$flat_rate_name.'_cost' => $priceFlatRate['price'],
                    'woocommerce_'.$flat_rate_name.'_type' => 'class',
                    'woocommerce_'.$flat_rate_name.'_provider' => $this->provider['slug'],
                    'instance_id' => $instance_flat_rate,
                    'method_id' => $this->provider['slug'] . '_flat_rate',
                ];

                $_REQUEST['instance_id'] = $instance_flat_rate;
                $shipping_method_flat_rate->set_post_data($shipping_method_configuration_flat_rate);
                $shipping_method_flat_rate->update_option('provider', $this->provider['slug']);
                $shipping_method_flat_rate->process_admin_options();

            }

            if ($priceStopDesk && $pickup_local_enabled) {

                $local_pickup_name = 'local_pickup_3m_express';
                if (is_hanout_enabled()) {
                    $local_pickup_name = 'local_pickup';
                }

                $instance_local_pickup = $zone->add_shipping_method($local_pickup_name);
                $shipping_method_local_pickup = WC_Shipping_Zones::get_shipping_method($instance_local_pickup);
                $shipping_method_configuration_local_pickup = array(
                    'woocommerce_'.$local_pickup_name.'_title' => $pickup_local_label ?? __('Local Pickup', 'woo-bordereau-generator'),
                    'woocommerce_'.$local_pickup_name.'_cost' => $priceStopDesk['price'],
                    'woocommerce_'.$local_pickup_name.'_type' => 'class',
                    'woocommerce_'.$local_pickup_name.'_provider' => $this->provider['slug'],
                    'instance_id' => $instance_local_pickup,
                );

                $_REQUEST['instance_id'] = $instance_local_pickup;
                $shipping_method_local_pickup->set_post_data($shipping_method_configuration_local_pickup);
                $shipping_method_local_pickup->update_option('provider', $this->provider['slug']);
                $shipping_method_local_pickup->process_admin_options();
            }
        }

        return [$shipping_method_flat_rate, $shipping_method_local_pickup];
    }


    /**
     * @param $order_ids
     * @return mixed|void
     */
    public function bulk_add_orders($order_ids)
    {

        $data = [];

        $orders = array();
        foreach ($order_ids as $id) {
            $order = wc_get_order($id);
            if ($order) {
                $orders[] = $order;
            }
        }

        $wilayas = $this->get_wilayas();

        foreach ($orders as $order) {

            $params = $order->get_data();

            $wilayaId = (int)str_replace("DZ-", "", $params['billing']['state']);
            $wilaya = array_filter($wilayas, function ($item) use ($wilayaId) {
                return $item['id'] == $wilayaId;
            });

            $wilaya = reset($wilaya);

            $commune = $params['billing']['city'];
            $communeId = null;


            if (is_numeric($commune)) {
                $communeId = $commune;
            } else {
                $communefound = array_filter($wilaya['municipalities'], function ($itm) use ($commune) {
                    return $itm['name_latin'] == $commune || str_replace('-', ' ', $itm['name_latin']) == $commune;
                });

                if (count($communefound)) {
                    $communefound = reset($communefound);
                    $communeId = $communefound['id'];
                }
            }

            $products = $params['line_items'];
            $productString = Helpers::generate_products_string($products);

            $stop_desk = 0;

            if ($order->has_shipping_method('local_pickup')) {
                $stop_desk = 1;
            }


            $phone = explode('/', $params['billing']['phone']);
            $price = $params['total'];

            $name = $params['billing']['first_name'] . ' ' . $params['billing']['last_name'];

            $request = new ThreeMExpressRequest($this->provider);
            $userInfo = $request->getUserInfo();

            $userCommuneId = $userInfo['stores'][0]['municipality']['id'];

            $data = array(
                'parcelReferenceId' => ' ',
                'targetFullName' => $name,
                'targetPhoneNumberPrimary' => Helpers::clean_phone_number($phone[0], true),
                'targetPhoneNumberSecondary' => isset($phone[1]) ? Helpers::clean_phone_number($phone[1], true) : "",
                'targetMunicipalityId' => $communeId,
                'targetAdrress' => $stop_desk ? $commune : $params['billing']['address_1'],
                'operationType' => 'DELIVERY',
                'deliveryType' => $stop_desk ? 'STOPDESK' : 'DOORSTEP',
                'parcelDescription' => $productString,
                'totalPostTarget' => (int)$price,
                'initiatorMunicipalityId' => $userCommuneId,
                'paiedBy' => 'TARGET',
                'parcelWeight' => 7,
                'parcelVolume' => 0.01,
                'parcelFragile' => false,
                'parcelOpenable' => false,
                'parcelTryable' => false,
                'parcelAmount' => (int)$price,
                'secondaryParcelDescription' => 'Desc',
                'secondaryParcelWeight' => NULL,
                'secondaryParcelVolume' => NULL,
                'secondaryParcelOpenable' => true,
                'secondaryParcelTryable' => true,
                'secondaryParcelAmount' => 0,
            );

            $url = $this->provider['api_url'] . '/customers/orders';
            $request = new ThreeMExpressRequest($this->provider);
            $content = $request->post($url, $data);

            if ($content['statusCode'] == 400) {
                set_transient('order_bulk_add_error', $content['message'], 45);
                $logger = \wc_get_logger();
                $logger->error('Bulk Upload Error: ' . $content['message'], array('source' => WC_BORDEREAU_POST_TYPE));
                return;
            }

            if ($content && isset($content['id'])) {

                $orderId = $content['id'];
                $url = $this->provider['api_url'] . '/v1/orders?skip=0&take=1&status=PLACED&id=' . $orderId;
                $request = new ThreeMExpressRequest($this->provider);
                $content = $request->get($url);
                $orderFound = $content['data'];

                if (count($orderFound)) {
                    $post_id = $order->get_id();
                    $orderFound = reset($orderFound);
                    $track = $this->provider['api_url'] . '/tracking/' . $orderId . '/events';

                    update_post_meta($post_id, '_shipping_tracking_number', wc_clean($orderFound['trackingId']));
                    update_post_meta($post_id, '_shipping_tracking_method',  $this->provider['slug']);
                    update_post_meta($post_id, '_shipping_3mexpress_order_id', $orderId);
                    update_post_meta($post_id, '_shipping_tracking_label', $this->provider['api_url'] . '/order-slips?orderIds[]=' . $orderId);
                    update_post_meta($post_id, '_shipping_label', $this->provider['api_url'] . '/order-slips?orderIds[]=' . $orderId);
                    update_post_meta($post_id, '_shipping_tracking_label_method', 'GET');
                    update_post_meta($post_id, '_shipping_tracking_url', $track);

                    $order->update_meta_data('_shipping_tracking_number', wc_clean($orderFound['trackingId']));
                    $order->update_meta_data('_shipping_tracking_label', $this->provider['api_url'] . '/order-slips?orderIds[]=' . $orderId);
                    $order->update_meta_data('_shipping_label', $this->provider['api_url'] . '/order-slips?orderIds[]=' . $orderId);
                    $order->update_meta_data('_shipping_tracking_label_method', 'GET');
                    $order->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
                    $order->update_meta_data('_shipping_3mexpress_order_id', $orderId);
                    $order->save();

                    Helpers::make_note($order, wc_clean($orderFound['trackingId']), $this->provider['name']);

                }
            }
        }
    }

    public function get_wilayas(): array
    {

        $path = Functions::get_path('3mexpress_wilaya.json');

        if (!file_exists($path)) {

            $request = new ThreeMExpressRequest($this->provider);
            $content = $request->get($this->provider['api_url'] . '/geo/provinces');

            file_put_contents($path, json_encode($content));
        }

        $wilayas = json_decode(file_get_contents($path), true);

        return $wilayas;
    }

    /**
     * @param int $wilaya_id
     * @param bool $hasStopDesk
     *
     * @return array
     */
    public function get_communes($wilaya_id, bool $hasStopDesk)
    {

        $wilaya_id = (int) str_replace('DZ-', '',$wilaya_id);

        $path = Functions::get_path('3mexpress_wilaya.json');

        if (!file_exists($path)) {

            $request = new ThreeMExpressRequest($this->provider);
            $content = $request->get($this->provider['api_url'] . '/geo/provinces');

            file_put_contents($path, json_encode($content));
        }

        $wilayas = json_decode(file_get_contents($path), true);
        $communes = [];

        foreach ($wilayas as $wilaya) {
            if ($wilaya['id'] == $wilaya_id) {
                $communes = $wilaya['municipalities'];
            }
        }

        $communesResult = [];

        foreach ($communes as $i => $item) {
            $communesResult[$i]['id'] = $item['id'];
            $communesResult[$i]['name'] = $item['name_latin'];
            $communesResult[$i]['enabled'] = is_array($item['servingStopdesk']);
        }

        return $communesResult;
    }

    public function get_status()
    {
        return $this->status();
    }


}
