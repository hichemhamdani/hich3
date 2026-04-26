<?php

namespace WooBordereauGenerator\Admin\Shipping;

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


use DateTime;
use DateTimeZone;
use WC_Shipping_Zone;
use WC_Shipping_Zones;
use WooBordereauGenerator\Functions;
use WooBordereauGenerator\Helpers;
use WP_Error;

class MylerzExpressSettings
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
        $url = $this->provider['api_url'] . '/api/packages/GetPackageListStatus';
        $request = new \WooBordereauGenerator\Admin\Shipping\MylerzExpressRequest($this->provider);
        $content = $request->post($url, $codes);

        return $this->format_tracking($content);
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
        $result = [];

        if (isset($json['Value'])) {
            foreach ($json['Value'] as $entry) {
                $result[$entry['BarCode']] = $entry['Status'];
            }
            return $result;
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


//        $request = new \WooBordereauGenerator\Admin\Shipping\MylerzExpressRequest($this->provider);
//        $wilayas = $this->get_wilayas();
//
//        $warehouses = $this->get_warehouses();
//
//        if (!empty($warehouses)) {
//            $warehouse = reset($warehouses);
//
//            $prices = [];
//            $types = ['CTD', 'CTC'];
//
//            $path = Functions::get_path('mylerz_fees.json');
//
//            if (!file_exists($path)) {
//
//                foreach ($types as $type) {
//                    foreach ($wilayas as $wilaya) {
//
//                        $data = [
//                            "CODValue" => 0,
//                            "WarehouseName" => $warehouse['Name'],
//                            "CustomerZoneCode" => $wilaya['Code'],
//                            "PackageWeight" => 0,
//                            "IsFulfillment" => false,
//                            "PackageServiceTypeCode" => $type,
//                            "PackageServiceCode" => "ND",
//                            "PaymentTypeCode" => "COD",
//                            "ServiceCategoryCode" => "DELIVERY"
//                        ];
//
//                        $content = $request->post($this->provider['api_url'] . '/api/packages/GetExpectedCharges', $data);
//
//                        $prices[$wilaya['ID']][$type]['price'] = $content['ShippingFees'];
//                        $prices[$wilaya['ID']][$type]['wilaya'] = $wilaya['EnName'];
//                        $prices[$wilaya['ID']][$type]['wilaya_id'] = $wilaya['ID'];
//
//                    }
//                }
//
//                file_put_contents($path, json_encode($prices));
//            }
//
//            $prices = json_decode(file_get_contents($path), true);
//
//
//            if (get_option('shipping_zones_grouped') && get_option('shipping_zones_grouped') == 'yes') {
//                $this->shipping_zone_grouped($prices, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled);
//            } else {
//                $this->shipping_zone_ungrouped($prices, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled);
//            }
//
//            wp_send_json_success(
//                array(
//                    'message' => 'success',
//                )
//            );
//        }

            wp_send_json_error(
                array(
                    'message' => 'Cette function n\est pas intege',
                )
            );

    }

    private function shipping_zone_grouped($livraison, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled)
    {

    }

    private function shipping_zone_ungrouped($livraison, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled)
    {

        $zones = [];

        $shipping_method_local_pickup = null;
        $shipping_method_flat_rate = null;

        $i = 1;
        foreach ($livraison as $key => $wilaya) {

            $priceStopDesk = $wilaya['CTC'];
            $priceFlatRate = $wilaya['CTD'];

            $wilaya_name = $priceFlatRate['wilaya'];

            // Get all shipping zones
            $zones = WC_Shipping_Zones::get_zones();

            // Iterate through the zones and find the one with the matching name
            $found_zone = null;
            foreach ($zones as $zone) {
                if ($zone['zone_locations'][0]->code == 'DZ:'. $priceFlatRate['wilaya_id']) {
                    $found_zone = $zone;
                    break;
                }
            }

            if (!$found_zone) {
                $zone = new WC_Shipping_Zone();
                $zone->set_zone_name(ucfirst(Helpers::normalizeCityName($wilaya_name)));
                $zone->set_zone_order($key);
                $zone->add_location('DZ:'. $priceFlatRate['wilaya_id'], 'state');
                $zone->save();
            } else {
                $zone = WC_Shipping_Zones::get_zone($found_zone['id']);
            }

            if ($priceFlatRate && $flat_rate_enabled) {

                $flat_rate_name = 'flat_rate_mylerz_express';
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

                $local_pickup_name = 'local_pickup_mylerz_express';
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

            $wilayaId = $params['billing']['state'];
            $wilaya = array_filter($wilayas, function ($item) use ($wilayaId) {
                return $item['ID'] == $wilayaId;
            });


            $wilaya = reset($wilaya);
            $wilayaId = $wilaya['Code'] ?? $wilayaId;

            $commune = $params['billing']['city'];

            $products = $params['line_items'];
            $productString = Helpers::generate_products_string($products);

            $stop_desk = 0;

            if ($order->has_shipping_method('local_pickup')) {
                $stop_desk = 1;
            }

            $phone = explode('/', $params['billing']['phone']);
            $price = $params['total'];

            $name = $params['billing']['first_name'] . ' ' . $params['billing']['last_name'];
            $note = stripslashes(get_option('wc_bordreau_default-shipping-note'));
            $date = new DateTime("now", new DateTimeZone("UTC"));

            $productsArr = [];

            foreach ($products as $k => $productItem) {
                $product = $productItem->get_product();
                $item = new \stdClass();
                $item->PieceNo = $k;
                $item->SpecialNotes = $productItem->get_name();
                $item->Weight = $product->get_weight();
                $productsArr = [$item];
            }

            $total_weight = 0;

            foreach ( $order->get_items() as $item_id => $item ) {
                $product = $item->get_product();
                if ( $product ) {
                    $product_weight = $product->get_weight();
                    // Ensure the product has a weight before adding it
                    if ( $product_weight ) {
                        $total_weight += (float) $product_weight * (int) $item->get_quantity();
                    }
                }
            }


            $data = [
                    [
                        'Package_Serial' => $order->get_id(),
                        'Description' => $productString,
                        'Total_Weight' => $total_weight,
                        'Service_Type' => $stop_desk ? 'CTC' : 'CTD',
                        'Service' => 'ND',
                        'Service_Category' => 'DELIVERY',
                        'Payment_Type' => 'COD',
                        'COD_Value' => (int) $price,
                        'Special_Notes' => $note,
                        'Customer_Name' => $name,
                        'Mobile_No' => Helpers::clean_phone_number($phone[0], true),
                        'Street' => $stop_desk ? $commune : $params['billing']['address_1'],
                        'City' => $wilayaId,
                        'Neighborhood' => $commune,
                        'District' => $commune,
                        'Country' => 'Algeria',
                        'Currency' => 'DZD',
                        'Pieces' => $productsArr,
                        'PickupDueDate' => $date->format("Y-m-d\TH:i:s.v\Z"),
                        'ValueOfGoods' => (int) $price,
                        'AllowToOpenPackage' => true,
                        'Mobile_No2' => isset($phone[1]) ? Helpers::clean_phone_number($phone[1], true) : "",
                    ],
            ];
            

            $url = $this->provider['api_url'] . '/api/Orders/AddOrders';
            $request = new \WooBordereauGenerator\Admin\Shipping\MylerzExpressRequest($this->provider);
            $content = $request->post($url, $data);


            if (isset($content['Value']['ErrorCode'])) {
                set_transient('order_bulk_add_error', $content['Value']['ErrorCode'], 45);
                $logger = \wc_get_logger();
                $logger->error('Bulk Upload Error: ' . $content['Value']['ErrorCode'], array('source' => WC_BORDEREAU_POST_TYPE));
                return;
            }

            if (isset($content['Value']['Packages'][0]['BarCode'])) {

                $post_id = $order->get_id();
                $track = $this->provider['api_url'] . '/tracking/' . $post_id . '/events';
                $trackingNumber = $content['Value']['Packages'][0]['BarCode'];

                update_post_meta($post_id, '_shipping_tracking_number', wc_clean($trackingNumber));
                update_post_meta($post_id, '_shipping_tracking_label', $this->provider['api_url'] . '/order-slips?orderIds[]=' . $trackingNumber);
                update_post_meta($post_id, '_shipping_label', $this->provider['api_url'] . '/order-slips?orderIds[]=' . $trackingNumber);
                update_post_meta($post_id, '_shipping_tracking_label_method', 'GET');
                update_post_meta($post_id, '_shipping_tracking_url', $track);
                update_post_meta($post_id, '_shipping_tracking_method', $this->provider['slug']);

                $order->update_meta_data('_shipping_tracking_number', wc_clean($trackingNumber));
                $order->update_meta_data('_shipping_tracking_label', $this->provider['api_url'] . '/order-slips?orderIds[]=' . $trackingNumber);
                $order->update_meta_data('_shipping_label', $this->provider['api_url'] . '/order-slips?orderIds[]=' . $trackingNumber);
                $order->update_meta_data('_shipping_tracking_label_method', 'GET');
                $order->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
                $order->save();

                Helpers::make_note($order, wc_clean($trackingNumber), $this->provider['name']);
            }
        }
    }

    public function get_wilayas(): array
    {

        $path = Functions::get_path('mylerz_wilaya.json');

        if (!file_exists($path)) {

            $request = new \WooBordereauGenerator\Admin\Shipping\MylerzExpressRequest($this->provider);
            $content = $request->get($this->provider['api_url'] . '/api/packages/GetCityZoneList');

            file_put_contents($path, json_encode($content));
        }

        $data = json_decode(file_get_contents($path), true);

        $wilayas = $data['Value'];
        $mapping = $this->city_mapping();

        foreach ($wilayas as $key => $wilaya) {
            $wilayas[$key] = $wilaya;
            $wilayas[$key]['ID'] = $mapping[$wilaya['Code']];
        }

        return $wilayas;
    }

    public function get_warehouses(): array
    {

        $path = Functions::get_path('mylerz_warehouses.json');

        if (!file_exists($path)) {

            $request = new \WooBordereauGenerator\Admin\Shipping\MylerzExpressRequest($this->provider);
            $content = $request->get($this->provider['api_url'] . '/api/Orders/GetWarehouses');

            file_put_contents($path, json_encode($content));
        }

        $data = json_decode(file_get_contents($path), true);

        return $data['Value'];
    }

    /**
     * @param int $wilaya_id
     * @param bool $hasStopDesk
     *
     * @return array
     */
    public function get_communes($wilaya_id, bool $hasStopDesk)
    {

        $reversed_city_mapping = $this->reversed_city_mapping();


        $wilaya_id = $reversed_city_mapping[$wilaya_id] ?? $wilaya_id;

        $path = Functions::get_path('mylerz_wilaya.json');

        if (!file_exists($path)) {

            $request = new \WooBordereauGenerator\Admin\Shipping\MylerzExpressRequest($this->provider);
            $content = $request->get($this->provider['api_url'] . '/api/packages/GetCityZoneList');

            file_put_contents($path, json_encode($content));
        }

        $wilayas = json_decode(file_get_contents($path), true);
        $communes = [];

        foreach ($wilayas['Value'] as $wilaya) {
            if ($wilaya['Code'] == $wilaya_id) {
                $communes = $wilaya['Zones'];
            }
        }

        $communesResult = [];

        foreach ($communes as $i => $item) {
            $communesResult[$i]['id'] = $item['Code'];
            $communesResult[$i]['name'] = $item['EnName'];
        }


        return $communesResult;
    }

    public function get_status()
    {
        return $this->status();
    }

    /**
     * @return string[]
     */
    private function city_mapping()
    {
        return array(
            'BLD' => 'DZ-09',
            'ALG' => 'DZ-16',
            'BRD' => 'DZ-35',
            'TPZ' => 'DZ-42',
            'Adra' => 'DZ-01',
            'Chle' => 'DZ-02',
            'Laghoua' => 'DZ-03',
            'Oum El Bouagh' => 'DZ-04',
            'Batn' => 'DZ-05',
            'Bejai' => 'DZ-06',
            'Biskr' => 'DZ-07',
            'Becha' => 'DZ-08',
            'Bouir' => 'DZ-10',
            'Tamanghasse' => 'DZ-11',
            'Tebess' => 'DZ-12',
            'Tlemce' => 'DZ-13',
            'Tiare' => 'DZ-14',
            'Tizi Ouzo' => 'DZ-15',
            'Djelf' => 'DZ-17',
            'Jije' => 'DZ-18',
            'Seti' => 'DZ-19',
            'Said' => 'DZ-20',
            'Skikd' => 'DZ-21',
            'Sidi Bel Abbe' => 'DZ-22',
            'Annab' => 'DZ-23',
            'Guelm' => 'DZ-24',
            'Constantin' => 'DZ-25',
            'Mede' => 'DZ-26',
            'Mostagane' => 'DZ-27',
            'M\'Sil' => 'DZ-28',
            'Mascar' => 'DZ-29',
            'Ouargl' => 'DZ-30',
            'Ora' => 'DZ-31',
            'El Bayad' => 'DZ-32',
            'Illiz' => 'DZ-33',
            'Bordj Bou Arrerid' => 'DZ-34',
            'El Tar' => 'DZ-36',
            'Tindou' => 'DZ-37',
            'Tissemsil' => 'DZ-38',
            'El Oue' => 'DZ-39',
            'Khenchel' => 'DZ-40',
            'Souk Ahra' => 'DZ-41',
            'Mil' => 'DZ-43',
            'Ain Defl' => 'DZ-44',
            'Naam' => 'DZ-45',
            'Ain Temouchen' => 'DZ-46',
            'Ghardai' => 'DZ-47',
            'Relizan' => 'DZ-48',
            'El M\'Ghai' => 'DZ-57',
            'El Menia' => 'DZ-58',
            'Ouled Djella' => 'DZ-51',
            'Bordj Badji Mokhta' => 'DZ-50',
            'Beni Abbe' => 'DZ-52',
            'Timimou' => 'DZ-49',
            'Touggour' => 'DZ-55',
            'Djane' => 'DZ-56',
            'In Sala' => 'DZ-53',
            'In Guezza' => 'DZ-54'
        );
    }

    /**
     * @return string[]
     */
    private function reversed_city_mapping()
    {
        return array(
            'DZ-09' => 'BLD',
            'DZ-16' => 'ALG',
            'DZ-35' => 'BRD',
            'DZ-42' => 'TPZ',
            'DZ-01' => 'Adra',
            'DZ-02' => 'Chle',
            'DZ-03' => 'Laghoua',
            'DZ-04' => 'Oum El Bouagh',
            'DZ-05' => 'Batn',
            'DZ-06' => 'Bejai',
            'DZ-07' => 'Biskr',
            'DZ-08' => 'Becha',
            'DZ-10' => 'Bouir',
            'DZ-11' => 'Tamanghasse',
            'DZ-12' => 'Tebess',
            'DZ-13' => 'Tlemce',
            'DZ-14' => 'Tiare',
            'DZ-15' => 'Tizi Ouzo',
            'DZ-17' => 'Djelf',
            'DZ-18' => 'Jije',
            'DZ-19' => 'Seti',
            'DZ-20' => 'Said',
            'DZ-21' => 'Skikd',
            'DZ-22' => 'Sidi Bel Abbe',
            'DZ-23' => 'Annab',
            'DZ-24' => 'Guelm',
            'DZ-25' => 'Constantin',
            'DZ-26' => 'Mede',
            'DZ-27' => 'Mostagane',
            'DZ-28' => 'M\'Sil',
            'DZ-29' => 'Mascar',
            'DZ-30' => 'Ouargl',
            'DZ-31' => 'Ora',
            'DZ-32' => 'El Bayad',
            'DZ-33' => 'Illiz',
            'DZ-34' => 'Bordj Bou Arrerid',
            'DZ-36' => 'El Tar',
            'DZ-37' => 'Tindou',
            'DZ-38' => 'Tissemsil',
            'DZ-39' => 'El Oue',
            'DZ-40' => 'Khenchel',
            'DZ-41' => 'Souk Ahra',
            'DZ-43' => 'Mil',
            'DZ-44' => 'Ain Defl',
            'DZ-45' => 'Naam',
            'DZ-46' => 'Ain Temouchen',
            'DZ-47' => 'Ghardai',
            'DZ-48' => 'Relizan',
            'DZ-57' => 'El M\'Ghai',
            'DZ-58' => 'El Menia',
            'DZ-51' => 'Ouled Djella',
            'DZ-50' => 'Bordj Badji Mokhta',
            'DZ-52' => 'Beni Abbe',
            'DZ-49' => 'Timimou',
            'DZ-55' => 'Touggour',
            'DZ-56' => 'Djane',
            'DZ-53' => 'In Sala',
            'DZ-54' => 'In Guezza'
        );
    }


    public function get_orders($queryData)
    {

        $mappingQueryData=[];

        $mappingQueryData['per_page'] = $queryData['page_size'] ?? 10;
        $mappingQueryData['page'] = $queryData['page'] ?? 1;


        $url = $this->provider['api_url'] . '/api/packages';
        $request = new \WooBordereauGenerator\Admin\Shipping\MylerzExpressRequest($this->provider);

        $content = $request->post($url, []);

        return $this->format_tracking($content);

    }


}
