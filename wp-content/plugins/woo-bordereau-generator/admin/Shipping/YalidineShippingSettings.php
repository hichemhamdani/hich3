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

use WC_Product;
use WC_Shipping_Zone;
use WC_Shipping_Zones;
use WooBordereauGenerator\Admin\BordereauGeneratorAdmin;
use WooBordereauGenerator\Admin\Partials\BordereauGeneratorProviders;
use WooBordereauGenerator\Frontend\BordereauGeneratorPublic;
use WooBordereauGenerator\Functions;
use WooBordereauGenerator\Helpers;
use WP_Error;
use WP_REST_Response;

class YalidineShippingSettings
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
     * @return array|array[]
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
                'type' => $filed['type'],
                'desc' => $filed['desc'],
                'css' => $filed['css'],
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
     * Verify API credentials by attempting to fetch wilayas
     * 
     * @param array $credentials Temporary credentials to test (keys match field 'value' from provider config)
     * @return array ['success' => bool, 'message' => string]
     */
    public function check_auth(array $credentials = []): array
    {
        try {
            // Get credentials from passed array or fall back to saved options
            $api_key = $credentials[$this->provider['slug'] . '_api_key'] ?? get_option($this->provider['slug'] . '_api_key');
            $api_token = $credentials[$this->provider['slug'] . '_api_token'] ?? get_option($this->provider['slug'] . '_api_token');

            if (empty($api_key) || empty($api_token)) {
                return [
                    'success' => false,
                    'message' => __('API Key and API Token are required', 'woo-bordereau-generator')
                ];
            }

            // Make a test request to wilayas endpoint
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => $this->provider['api_url'] . '/wilayas?page_size=1',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTPHEADER => [
                    'X-API-ID: ' . $api_key,
                    'X-API-TOKEN: ' . $api_token,
                    'Content-Type: application/json'
                ],
            ]);

            $response = curl_exec($curl);
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            $error = curl_error($curl);
            curl_close($curl);

            if ($error) {
                return [
                    'success' => false,
                    'message' => __('Connection error: ', 'woo-bordereau-generator') . $error
                ];
            }

            $data = json_decode($response, true);

            if ($httpCode === 200 && isset($data['data'])) {
                return [
                    'success' => true,
                    'message' => __('Credentials verified successfully', 'woo-bordereau-generator')
                ];
            } elseif ($httpCode === 401 || $httpCode === 403) {
                return [
                    'success' => false,
                    'message' => __('Invalid API credentials', 'woo-bordereau-generator')
                ];
            } else {
                $errorMsg = $data['error']['message'] ?? __('Unknown error occurred', 'woo-bordereau-generator');
                return [
                    'success' => false,
                    'message' => $errorMsg
                ];
            }
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Track the packages in bulk
     * @param array $codes
     *
     * @return array
     */
    public function bulk_track(array $codes): array
    {
        $request = new YalidineRequest($this->provider);


        return $this->format_tracking($request->get($this->provider['api_url'] . '/histories/?tracking=' . implode(',', array_values($codes)) . '&page_size=1000', false));
    }

    /**
     * @param $json
     *
     * @return array
     */
    private function format_tracking($json): array
    {

        if ($json) {
            $result = [];

            foreach ($json['data'] as $key => &$entry) {
                $result[$entry['tracking']][$key] = $entry;
            }

            $final = [];
            foreach ($result as $key => $value) {
                $final[$key] = array_values($value)[0]['status'];
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
                update_option($this->provider['slug'] . '_pickup_local_label', $pickup_local_label);
            }
        }

        if ($flat_rate_enabled) {
            if ($flat_rate_label) {
                update_option($this->provider['slug'] . '_flat_rate_label', $flat_rate_label);
            }
        }

        $all_results = $this->get_fees($is_economic);

        if ($agency_import_enabled) {
            update_option($this->provider['slug'] . '_agencies_import', true);
        }

        if (get_option('shipping_zones_grouped') && get_option('shipping_zones_grouped') == 'yes') {
            $this->shipping_zone_grouped($all_results, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled);
        } else {
            $this->shipping_zone_ungrouped($all_results, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled, $agency_import_enabled, $is_economic);
        }


        wp_send_json_success(
            array(
                'message' => 'success',
            )
        );
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
     * @param $agency_import_enabled
     * @param $is_economic
     * @return array
     */
    private function shipping_zone_ungrouped($response_array, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled, $agency_import_enabled, $is_economic)
    {


        // Set memory limit to -1 (unlimited) and set time limit to 0 (unlimited)
        ini_set('memory_limit', '-1');
		set_time_limit(0);

        $wilayas = $response_array;
        $zones = [];

        if ($agency_import_enabled) {
            // get agencies from Yalidine
            $url = $this->provider['api_url'] . "/centers?page_size=1000";
            $request = new YalidineRequest($this->provider);
            $agencie_rep = $request->get($url, false);
            $agencies = $agencie_rep['data'];

        }

        $shipping_method_local_pickup = null;
        $shipping_method_flat_rate = null;


        $i = 1;
        foreach ($wilayas as $key => $wilaya) {

            $wilaya_name = $wilaya['wilaya_name'];

            // Get all shipping zones
            $zones = WC_Shipping_Zones::get_zones();

            // Iterate through the zones and find the one with the matching name
            $found_zone = null;
            foreach ($zones as $zone) {

                if ($zone['zone_locations'][0]->code == 'DZ:DZ-' . str_pad($wilaya['wilaya_id'], 2, '0',STR_PAD_LEFT)) {
                    $found_zone = $zone;
                    break;
                }
            }

            if (!$found_zone) {
                $zone = new WC_Shipping_Zone();
                $zone->set_zone_name(ucfirst(Helpers::normalizeCityName($wilaya_name)));
                $zone->set_zone_order($key);
                $zone->add_location('DZ:DZ-' . str_pad($wilaya['wilaya_id'], 2, '0', STR_PAD_LEFT), 'state');
                $zone->save();
            } else {
                $zone = WC_Shipping_Zones::get_zone($found_zone['id']);
            }

            if (isset($wilaya['express_home']) && $flat_rate_enabled) {

                $flat_rate_name = 'flat_rate_yalidine';
                if (is_hanout_enabled()) {
                    $flat_rate_name = 'flat_rate';
                }

                $price_flat_rate = $wilaya['express_home'];
                if($price_flat_rate) {
                    if ($is_economic) {
                        $price_flat_rate = $wilaya['economic_home'];
                    }

                    $instance_flat_rate = $zone->add_shipping_method($flat_rate_name);
                    $shipping_method_flat_rate = WC_Shipping_Zones::get_shipping_method($instance_flat_rate);
                    $shipping_method_configuration_flat_rate = [
                        'woocommerce_'.$flat_rate_name.'_title' => $flat_rate_label ?? __('Flat rate', 'woo-bordereau-generator'),
                        'woocommerce_'.$flat_rate_name.'_cost' => $price_flat_rate,
                        'woocommerce_'.$flat_rate_name.'_type' => 'class',
                        'woocommerce_'.$flat_rate_name.'_provider' => $this->provider['slug'],
                        'instance_id' => $instance_flat_rate,
                    ];

                    if ($is_economic) {
                        $shipping_method_configuration_flat_rate['woocommerce_'.$flat_rate_name.'_is_economic'] = "yes";
                    } else {
                        $shipping_method_configuration_flat_rate['woocommerce_'.$flat_rate_name.'_is_economic'] = null;
                    }


//                print_r($shipping_method_configuration_flat_rate);

                    $_REQUEST['instance_id'] = $instance_flat_rate;
                    $shipping_method_flat_rate->set_post_data($shipping_method_configuration_flat_rate);
                    $shipping_method_flat_rate->process_admin_options();
                }

            }

            if (isset($wilaya['express_desk']) && $pickup_local_enabled) {
                if ($agency_import_enabled) {
                    $agenciesInWilaya = array_filter($agencies, function ($item) use ($wilaya) {
                        return $item['wilaya_id'] == $wilaya['wilaya_id'];
                    });

                    foreach ($agenciesInWilaya as $agency) {
                        $local_pickup_name = 'local_pickup_yalidine';
                        if (is_hanout_enabled()) {
                            $local_pickup_name = 'local_pickup';
                        }

                        $price_local_pickup = $wilaya['express_desk'];
                        if($price_local_pickup) {

                            if ($is_economic) {
                                $price_local_pickup = $wilaya['economic_desk'];
                            }

                            $name = sprintf("%s %s", $pickup_local_label ?? __('Local Pickup', 'woo-bordereau-generator'), $agency['name']);

                            $instance_local_pickup = $zone->add_shipping_method($local_pickup_name);
                            $shipping_method_local_pickup = WC_Shipping_Zones::get_shipping_method($instance_local_pickup);
                            $shipping_method_configuration_local_pickup = array(
                                'woocommerce_'.$local_pickup_name.'_title' => $name,
                                'woocommerce_'.$local_pickup_name.'_cost' => $price_local_pickup,
                                'woocommerce_'.$local_pickup_name.'_type' => 'class',
                                'woocommerce_'.$local_pickup_name.'_address' => $agency['address'],
                                'woocommerce_'.$local_pickup_name.'_gps' => $agency['gps'],
                                'woocommerce_'.$local_pickup_name.'_center_id' => (int) $agency['center_id'],
                                'woocommerce_'.$local_pickup_name.'_provider' => $this->provider['slug'],
                                'instance_id' => $instance_local_pickup,
                            );

                            if ($is_economic) {
                                $shipping_method_configuration_local_pickup['woocommerce_'.$local_pickup_name.'_is_economic'] = "yes";
                            } else {
                                $shipping_method_configuration_local_pickup['woocommerce_'.$local_pickup_name.'_is_economic'] = null;
                            }

                            $_REQUEST['instance_id'] = $instance_local_pickup;
                            $shipping_method_local_pickup->set_post_data($shipping_method_configuration_local_pickup);
                            $shipping_method_local_pickup->process_admin_options();
                        }

                    }
                } else {

                    $local_pickup_name = 'local_pickup_yalidine';

                    if (is_hanout_enabled()) {
                        $local_pickup_name = 'local_pickup';
                    }

                    $price_local_pickup = $wilaya['express_desk'];
                    if($price_local_pickup) {

                        if ($is_economic) {
                            $price_local_pickup = $wilaya['economic_desk'];
                        }

                        $instance_local_pickup = $zone->add_shipping_method($local_pickup_name);
                        $shipping_method_local_pickup = WC_Shipping_Zones::get_shipping_method($instance_local_pickup);
                        $shipping_method_configuration_local_pickup = array(
                            'woocommerce_'.$local_pickup_name.'_title' => $pickup_local_label ?? __('Local Pickup', 'woo-bordereau-generator'),
                            'woocommerce_'.$local_pickup_name.'_cost' => $price_local_pickup,
                            'woocommerce_'.$local_pickup_name.'_type' => 'class',
                            'woocommerce_'.$local_pickup_name.'_provider' => $this->provider['slug'],
                            'instance_id' => $instance_local_pickup,
                        );

                        if ($is_economic) {
                            $shipping_method_configuration_local_pickup['woocommerce_'.$local_pickup_name.'_is_economic'] = "yes";
                        } else {
                            $shipping_method_configuration_local_pickup['woocommerce_'.$local_pickup_name.'_is_economic'] = null;
                        }

                        $_REQUEST['instance_id'] = $instance_local_pickup;
                        $shipping_method_local_pickup->set_post_data($shipping_method_configuration_local_pickup);
                        $shipping_method_local_pickup->process_admin_options();
                    }

                }
            }
        }

        return [$shipping_method_flat_rate, $shipping_method_local_pickup];
    }


    /**
     * @param $order_ids
     * @return mixed|void
     */
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


        // get Ceneters


        $mapping = [];

        foreach ($orders as $cle => $order) {

            $shipping = new YalidineShipping($order, $this->provider);
            $fields = $this->provider['fields'];

            $key = $fields['total']['choices'][0]['name'] ?? null;
            $recalculate = $fields['recalculate']['choices'][0]['name'] ?? null;
            $overweight_checkout = $fields['overweight_checkout']['choices'][0]['name'] ?? null; // the fee is in the checkout
            $overweight = $fields['overweight']['choices'][0]['name'] ?? null; // the fee is in the checkout

            $stop_desk = 0;
            $center = null;

            if ($order->has_shipping_method('local_pickup') || $order->has_shipping_method('local_pickup_yalidine')) {
                $stop_desk = 1;
            }
            // check if it has center in the name and match it to the one in the array

            $total = get_option($key) ? get_option($key) : 'without-shipping';
            $recalculateCase = get_option($recalculate) ? get_option($recalculate) : 'recalculate-without-shipping';
            $overweightCheckoutCase = get_option($overweight_checkout) ? get_option($overweight_checkout) : 'recalculate-without-overweight';
            $overweightCase = get_option($overweight) ? get_option($overweight) : 'recalculate-without-overweight';
            $total_weight = 0;
            $cart_value = 0;

            if ($total == 'with-shipping') {
                $cart_value = (float)$order->get_subtotal() + $order->get_total_tax() + $order->get_total_fees() + $order->get_total_shipping() - $order->get_discount_total();
            } elseif ($total === 'without-shipping') {
                $cart_value = ((float)$order->get_subtotal() + $order->get_total_tax() + $order->get_total_fees() - $order->get_discount_total());
            }

            $wilayas = $shipping->get_wilayas();


            if (! isset($wilayas['data']) || ! is_array($wilayas['data'])) {
                set_transient('order_bulk_add_error', __('Unable to fetch wilayas from Yalidine please clear cache and try again', 'woo-bordereau-generator'), 45);
                $logger = \wc_get_logger();
                $logger->error('Bulk Upload Error: Unable to fetch wilayas from Yalidine please clear cache and try again' , array('source' => WC_BORDEREAU_POST_TYPE));
                continue; // Skip this order and move to the next one
            }


            if (str_contains($order->get_billing_state(),'DZ-')) {
                $wilayaId = (int)str_replace("DZ-", "", $order->get_billing_state());
                $wilayaIndex = array_search($wilayaId, array_column($wilayas['data'], 'id'));

                if($wilayaIndex == -1) {
                    set_transient('order_bulk_add_error', __('Unable to find the wilaya please check if its correct or add it manuelly in the order page', 'woo-bordereau-generator'), 45);
                    $logger = \wc_get_logger();
                    $logger->error('Bulk Upload Error: Unable to find the wilaya please check if its correct or add it manuelly in the order page' , array('source' => WC_BORDEREAU_POST_TYPE));
                    return;
                }
                // get the wilaya name and id
                $wilaya = $wilayas['data'][$wilayaIndex];
            } else {
                $wilaya = $order->get_billing_state();
                $wilayaIndex = array_search($wilaya, array_column($wilayas['data'], 'name'));
                if(!$wilayaIndex) {
                    set_transient('order_bulk_add_error', __('Unable to find the wilaya please check if its correct or add it manuelly in the order page', 'woo-bordereau-generator'), 45);
                    $logger = \wc_get_logger();
                    $logger->error('Bulk Upload Error: Unable to find the wilaya please check if its correct or add it manuelly in the order page' , array('source' => WC_BORDEREAU_POST_TYPE));
                    return;
                }

                $wilaya = $wilayas['data'][$wilayaIndex];
            }



            $commune = stripslashes($order->get_billing_city());

            if (is_numeric($commune)) {
                $communes = $shipping->get_communes($wilayaId, $stop_desk);

                if (is_array($communes)) {
                    $foundCommune = array_filter($communes, function ($w) use ($commune) {
                        return $w['id'] == $commune;
                    });
                    if (count($foundCommune)) {
                        $foundCommune = reset($foundCommune);
                        $commune = $foundCommune['name'];
                    } else {
                        $communes = $shipping->get_communes($wilayaId, false);
                        if (is_array($communes)) {
                            $foundCommune = array_filter($communes, function ($w) use ($commune) {
                                return $w['id'] == $commune;
                            });
                            if (count($foundCommune)) {
                                $commune = array_values($foundCommune)[0]['name'];
                            }
                        }
                    }
                }
            }

            // Sync the price of the shipping and add the diff to the cart total

            if ($recalculateCase == 'recalculate-with-shipping') {
                $shippingCost = (int) $order->get_shipping_total();
                $shippingCostFromProvider = $shipping->get_shipping_cost($wilaya['id']);
                $extra = $shipping->get_extra_shipping_cost($commune, $wilaya['id']);
                $shippingExtraCostFromProvider = false;
                if ($extra && is_array($extra)) {
                    $shippingExtraCostFromProvider = array_values($extra) ?? false;
                }

                if ($stop_desk) {
                    $shippingCostFromProviderPrice = $shippingCostFromProvider['desk_fee'];
                } else {
                    $shippingCostFromProviderPrice = $shippingCostFromProvider['home_fee'];
                }

                if ($shippingExtraCostFromProvider && isset($shippingExtraCostFromProvider[0]['commune'])) {
                    $cart_value = $cart_value + ($shippingCost - ($shippingCostFromProviderPrice + $shippingExtraCostFromProvider[0]['commune']));
                } else {
                    $cart_value = $cart_value + ($shippingCost - $shippingCostFromProviderPrice);
                }
            }

            $has_suppliment = false;
            $fees = $order->get_fees();

            foreach ( $fees as $item_id => $item_fee ) {
                // check if the suppliemnt fee
                if($item_fee->get_name() == __("Supplément commune", 'woo-bordereau-generator')) {
                    if ($total != 'with-shipping') {
                        $cart_value = $cart_value - $item_fee->get_total();
                    }

                    $has_suppliment = true;
                    break;
                }
            }

            $shippingOverweightPrice = 0;
            $weightData = Helpers::get_billable_weight($order);
            $shippingOverweight = 0;
            $dimensions = $weightData['dimensions'];

            if ($overweightCase == 'recalculate-with-overweight') {

                $shippingOverweight = $weightData['billable_weight'];

                foreach ( $fees as $item_id => $item_fee ) {
                    // check if the suppliemnt fee
                    if($item_fee->get_name() == __("Surpoids (+5KG)", 'woo-bordereau-generator')) {
                        if ($total != 'with-shipping') {
                            $cart_value = $cart_value - $item_fee->get_total();
                        }
                        $has_suppliment = true;
                        break;
                    }
                }
            }

            // check if commune suppliement is enabled if so reduce the price from sending
            if (! $has_suppliment) {

                // Todo should reduce the fee from the price so it calibrate
                $public = new BordereauGeneratorPublic(WC_BORDEREAU_POST_TYPE, WC_BORDEREAU_GENERATOR_VERSION);
                $result = $this->get_extra_fee($order->get_billing_state());


                if ($result) {
                    $city = $order->get_billing_city();
                    // check if the city is a number
                    if(is_numeric($city)) {
                        $found = array_filter($result, function ($item) use ($city) {
                            return $item['id'] == $city;
                        });

                    } else {
                        $found = array_filter($result, function ($item) use ($city) {
                            return Helpers::areSimilar($item['name'], stripslashes($city));
                        });
                    }

                    if (count($found)) {
                        $found = reset($found);
                        $cart_value = $cart_value - (int)$found['commune'];
                    }
                }

            }

            if (isset($this->data['products'])) {
                $productString = $this->data['products'];
            } else {
                $products = $order->get_items();
                $productString = Helpers::generate_products_string($products);
            }

            $free_shipping = 0;
            $declared_value = $cart_value - $order->get_shipping_total();


            if ($total == "with-shipping") {
                $free_shipping = 1;
                $cart_value = (int) $order->get_total();
                $declared_value = (int)($order->get_total() - $order->get_shipping_total());
            }



            $phone = $order->get_billing_phone();
            if ($order->get_meta('_billing_phone_2', true)) {
                $phone .= ',' . $order->get_meta('_billing_phone_2', true);
            }

            $mapping[$order->get_order_number()] = $order->get_id();

            $data[$order->get_order_number()] = [
                "order_id" => $order->get_order_number(),
                "firstname" => $order->get_billing_first_name(),
                "familyname" => $order->get_billing_last_name(),
                "contact_phone" => $phone,
                "address" => $order->get_billing_address_1(),
                "to_commune_name" => $commune,
                "to_wilaya_name" => $wilaya['name'],
                "product_list" => $productString,
                "price" => $cart_value,
                "freeshipping" => $free_shipping,
                "do_insurance" => false,
                "declared_value" => $declared_value,
                "is_stopdesk" => $stop_desk,
                "has_exchange" => false,
                "height"=> $dimensions['height'],
                "width" => $dimensions['width'],
                "length" => $dimensions['length'],
                "weight" => $shippingOverweight,
            ];




			$provid = $this->provider['slug'];

            if (in_array($provid, ['guepex', 'wecanservices'])) {
                $is_economic = get_option('economic_'.$provid.'_mode', 'disable_economic_'. $provid) == 'enable_economic_' . $provid;

                /**
                if (!$is_economic) {
                $shipping_method = Helpers::get_order_shipping_method_class($order);
                if ($shipping_method) {
                if (method_exists($shipping_method, 'get_is_economic')) {
                $is_economic = $shipping_method->get_is_economic();
                }
                }
                }
                 **/


                $data[$order->get_order_number()]['economic'] = $is_economic;
            }

            if ($stop_desk) {
                $center = (int) $order->get_meta('_billing_center_id', true);
                if (! $center) {
                    $center = (int) get_post_meta($order->get_id(), '_billing_center_id', true);
                }

                $data[$order->get_order_number()]['stopdesk_id'] = $center;
            }

            if (!$center && $stop_desk) {

                $found = false;
                // check if the name is an agence check in the centers list
                $name = $order->get_shipping_method();
                $centers = $this->get_all_centers();

                if(is_array($centers)) {
                    $result = array_filter($centers, function($agency) use ($name) {
                        $prefix = get_option($this->provider['slug'] . '_pickup_local_label');
                        $agencyName = sprintf("%s %s", $prefix, $agency['name']);
                        return trim($agencyName) === trim($name);
                    });
                    $found = !empty($result) ? reset($result) : null;
                }

                if ($found) {
                    $foundStop = $found;
                    $data[$order->get_order_number()]['stopdesk_id'] = $foundStop['id'];
                    $center = $foundStop['id'];
                    $commune = $foundStop['commune_id'];

                    if (is_numeric($commune)) {
                        $communes = $shipping->get_communes($wilayaId, $stop_desk);
                        if (is_array($communes)) {
                            $foundCommune = array_filter($communes, function ($w) use ($commune) {
                                return $w['id'] == $commune;
                            });
                            if (count($foundCommune)) {
                                $foundCommune = reset($foundCommune);
                                $commune = $foundCommune['name'];
                                $data[$order->get_order_number()]['to_commune_name'] = $commune;
                            }
                        }
                    } else {
                        $data[$order->get_order_number()]['to_commune_name'] = $foundStop['commune_id'];
                    }
                }
            }

            if (!$center && $stop_desk) {
                $centers = $this->get_all_centers();
                if (is_array($centers) && count($centers) > 0) {
                    // Step 1: Try to find a center in the selected commune
                    $result = array_filter($centers, function($agency) use ($commune) {
                        return $agency['commune_name'] == $commune || $agency['commune_id'] == $commune;
                    });
                    $found = !empty($result) ? reset($result) : null;

                    // Step 2: If no center in commune, filter by wilaya_id
                    if (!$found) {
                        $result = array_filter($centers, function($agency) use ($wilayaId) {
                            return $agency['wilaya_id'] == $wilayaId;
                        });
                        $found = !empty($result) ? reset($result) : null;
                    }

                    // Step 3: Set the center and update commune from center
                    if ($found && isset($found['id'])) {
                        $data[$order->get_order_number()]['stopdesk_id'] = $found['id'];
                        $center = $found['id'];
                        $data[$order->get_order_number()]['to_commune_name'] = $found['commune_name'];
                    }
                }
            }
        }

        try {
            $request = new YalidineRequest($this->provider);
            $response = $request->post($this->provider['api_url'] . "/parcels", array_values($data), 'POST', true);

            if (isset($response['error'])) {
                if (isset($response['error']['message'])) {
                    set_transient('order_bulk_add_error', $response['error']['message'], 45);
                    $logger = \wc_get_logger();
                    $logger->error('Bulk Upload Error: ' . $response['error']['message'], array('source' => WC_BORDEREAU_POST_TYPE));
                }
            }

            if ($response) {
                foreach ($response as $key => $item) {
                    $key = $mapping[$key];
                    $order = wc_get_order($key);
                    if ($order) {
                        $trackingNumber = $item['tracking'];
                        $label = $item['label'];

                        update_post_meta($order->get_id(), '_shipping_tracking_number', wc_clean($trackingNumber));
                        update_post_meta($order->get_id(), '_shipping_tracking_label', wc_clean($label));
                        update_post_meta($order->get_id(), '_shipping_label', wc_clean($label));
                        update_post_meta($order->get_id(), '_shipping_tracking_url', 'https://' . $this->provider['url'] . '/suivre-un-colis/?tracking=' . wc_clean($trackingNumber));
                        update_post_meta($order->get_id(), '_shipping_tracking_method', $this->provider['slug']);

                        $order->update_meta_data('_shipping_tracking_number', wc_clean($trackingNumber));
                        $order->update_meta_data('_shipping_label', wc_clean($label));
                        $order->update_meta_data('_shipping_tracking_label', wc_clean($label));
                        $order->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
                        $order->save();

                        Helpers::make_note($order, $trackingNumber, $this->provider['name']);

                    }
                }
            }
        } catch (\ErrorException $e) {
            set_transient('order_bulk_add_error', $e->getMessage(), 45);
        }
    }


    /**
     * @return string[]
     */
    public function get_status()
    {
        return [
            "Pas encore expédié" => "Pas encore expédié",
            "A vérifier" => "A vérifier",
            "En préparation" => "En préparation",
            "Pas encore ramassé" => "Pas encore ramassé",
            "Prêt à expédier" => "Prêt à expédier",
            "Ramassé" => "Ramassé",
            "Bloqué" => "Bloqué",
            "Débloqué" => "Débloqué",
            "Transfert" => "Transfert",
            "Expédié" => "Expédié",
            "Centre" => "Centre",
            "En localisation" => "En localisation",
            "Vers Wilaya" => "Vers Wilaya",
            "Reçu à Wilaya" => "Reçu à Wilaya",
            "En attente du client" => "En attente du client",
            "Sorti en livraison" => "Sorti en livraison",
            "En attente" => "En attente",
            "En alerte" => "En alerte",
            "Alerte résolue" => "Alerte résolue",
            "Tentative échouée" => "Tentative échouée",
            "Livré" => "Livré",
            "Echèc livraison" => "Echèc livraison",
            "Retour vers centre" => "Retour vers centre",
            "Retourné au centre" => "Retourné au centre",
            "Retour transfert" => "Retour transfert",
            "Retour groupé" => "Retour groupé",
            "Retour à retirer" => "Retour à retirer",
            "Retour vers vendeur" => "Retour vers vendeur",
            "Retourné au vendeur" => "Retourné au vendeur",
            "Echange échoué" => "Echange échoué",
        ];
    }


    /**
     * @return array
     * @since 4.0.0
     */
    public function get_all_centers()
    {
        $hasModePages = true;
        $currentPage = 1;
        $centers = [];

        while ($hasModePages === true) {

            $request = new YalidineRequest($this->provider);
            $json = $request->get($this->provider['api_url'] . '/centers?page=' . $currentPage . '&page_size=1000');
            $centers = array_merge($centers, $json['data'] ?? []);
            if (empty($json) || !isset($json['has_more'])) {
                $hasModePages = false;
            }

            $currentPage++;
        }

        $communesResult = [];

        foreach ($centers as $i => $item) {

            $communesResult[$i]['id'] = $item['center_id'];
            $communesResult[$i]['name'] = $item['name'];
            $communesResult[$i]['commune_id'] = $item['commune_id'];
            $communesResult[$i]['commune_name'] = $item['commune_name'];
            $communesResult[$i]['wilaya_id'] = $item['wilaya_id'];
        }



        return $communesResult;
    }


    /**
     * @param $wilaya_id
     * @param bool $hasStopDesk
     *
     * @return array
     * @since 4.0.0
     * Get Cities from specific State
     */
    public function get_communes($wilaya_id, bool $hasStopDesk)
    {

        $wilaya_id = str_replace('DZ-', '', $wilaya_id);

        $communes = [];

        $url = $this->provider['api_url'] . '/communes?wilaya_id=' . $wilaya_id;

        if ($hasStopDesk) {
            $url .= '&has_stop_desk=true';
        }

        try {
            $request = new YalidineRequest($this->provider);
            $response = $request->get($url);

            if(! $response) {
                $response = $request->get($url, false);
            }


            if ($response && isset($response['data'])) {
                foreach ($response['data'] as $i => $item) {
                    if ($item['is_deliverable'] == 1) {
                        $communes[$i]['id'] = $item['name'];
                        $communes[$i]['name'] = $item['name'];
                    }
                }
            }
        } catch (\ErrorException $exception) {

        }


        return $communes;
    }

    public function get_fees($is_economic = false, $wilaya_id = null)
    {
        $store_state = get_option($this->provider['slug'] . '_wilaya_from');

        if (empty($store_state)) {
            $store_state = (int) str_replace('DZ-', '', WC()->countries->get_base_state());
        }

        // Define states array for lookup
        $states_dz = array('Adrar' => '01 Adrar - أدرار', 'Chlef' => '02 Chlef - الشلف', 'Laghouat' => '03 Laghouat - الأغواط', 'Oum El Bouaghi' => '04 Oum El Bouaghi - أم البواقي', 'Batna' => '05 Batna - باتنة', 'Béjaïa' => '06 Béjaïa - بجاية', 'Biskra' => '07 Biskra - بسكرة', 'Bechar' => '08 Bechar - بشار', 'Blida' => '09 Blida - البليدة', 'Bouira' => '10 Bouira - البويرة', 'Tamanrasset' => '11 Tamanrasset - تمنراست ', 'Tébessa' => '12 Tébessa - تبسة ', 'Tlemcene' => '13 Tlemcene - تلمسان', 'Tiaret' => '14 Tiaret - تيارت', 'Tizi Ouzou' => '15 Tizi Ouzou - تيزي وزو', 'Alger' => '16 Alger - الجزائر', 'Djelfa' => '17 Djelfa - الجلفة', 'Jijel' => '18 Jijel - جيجل', 'Sétif' => '19 Sétif - سطيف', 'Saïda' => '20 Saïda - سعيدة', 'Skikda' => '21 Skikda - سكيكدة', 'Sidi Bel Abbès' => '22 Sidi Bel Abbès - سيدي بلعباس', 'Annaba' => '23 Annaba - عنابة', 'Guelma' => '24 Guelma - قالمة', 'Constantine' => '25 Constantine - قسنطينة', 'Médéa' => '26 Médéa - المدية', 'Mostaganem' => '27 Mostaganem - مستغانم', 'MSila' => '28 MSila - مسيلة', 'Mascara' => '29 Mascara - معسكر', 'Ouargla' => '30 Ouargla - ورقلة', 'Oran' => '31 Oran - وهران', 'El Bayadh' => '32 El Bayadh - البيض', 'Illizi' => '33 Illizi - إليزي ', 'Bordj Bou Arreridj' => '34 Bordj Bou Arreridj - برج بوعريريج', 'Boumerdès' => '35 Boumerdès - بومرداس', 'El Tarf' => '36 El Tarf - الطارف', 'Tindouf' => '37 Tindouf - تندوف', 'Tissemsilt' => '38 Tissemsilt - تيسمسيلت', 'Eloued' => '39 Eloued - الوادي', 'Khenchela' => '40 Khenchela - خنشلة', 'Souk Ahras' => '41 Souk Ahras - سوق أهراس', 'Tipaza' => '42 Tipaza - تيبازة', 'Mila' => '43 Mila - ميلة', 'Aïn Defla' => '44 Aïn Defla - عين الدفلى', 'Naâma' => '45 Naâma - النعامة', 'Aïn Témouchent' => '46 Aïn Témouchent - عين تموشنت', 'Ghardaïa' => '47 Ghardaïa - غرداية', 'Relizane' => '48 Relizane- غليزان', 'Timimoun' => '49 Timimoun - تيميمون', 'Bordj Baji Mokhtar' => '50 Bordj Baji Mokhtar - برج باجي مختار', 'Ouled Djellal' => '51 Ouled Djellal - أولاد جلال', 'Béni Abbès' => '52 Béni Abbès - بني عباس', 'Aïn Salah' => '53 Aïn Salah - عين صالح', 'In Guezzam' => '54 In Guezzam - عين قزام', 'Touggourt' => '55 Touggourt - تقرت', 'Djanet' => '56 Djanet - جانت', 'El MGhair' => '57 El MGhair - المغير', 'El Menia' => '58 El Menia - المنيعة');

        // Check and convert wilaya_id format
        if (!empty($wilaya_id)) {
            // Check if it's already in format 'DZ-01' or '01'
            if (preg_match('/^DZ-\d{2}$/', $wilaya_id)) {
                // Format is 'DZ-01', extract the numeric part
                $wilaya_id = (int) str_replace('DZ-', '', $wilaya_id);
            } elseif (preg_match('/^\d{1,2}$/', $wilaya_id)) {
                // Format is already numeric, ensure it's an integer
                $wilaya_id = (int) $wilaya_id;
            } else {
                // Try to find the ID from the states_dz array
                foreach ($states_dz as $state_name => $state_value) {
                    if ($state_name == $wilaya_id) {
                        // Extract the numeric ID from the beginning of the value
                        preg_match('/^(\d{1,2})/', $state_value, $matches);
                        if (!empty($matches[1])) {
                            $wilaya_id = (int) $matches[1];
                            break;
                        }
                    }
                }
            }
        }

        $path = Functions::get_path(sprintf("%s_fees_DZ%s%s.json",$this->provider['slug'], $store_state, ( $is_economic ? "0" : "1" )));
        if(!empty($wilaya_id)) {
            $path = Functions::get_path(sprintf("%s_fees_DZ%s%s%s.json", $this->provider['slug'], $store_state, ( $is_economic ? "0" : "1" ),$wilaya_id));
        }

	    if (file_exists($path)) {
		    $all_results = json_decode(file_get_contents($path), true);
		    if(count($all_results)) {
			    return $all_results;
		    }
	    }

	    $communes_extra = [];
	    $all_results = [];

	    // If a specific wilaya_id is provided, only fetch data for that wilaya
	    if (!empty($wilaya_id)) {
		    return $this->fetch_single_wilaya_with_rate_limit($wilaya_id, $store_state, $is_economic, $all_results, $communes_extra, $path);
	    } else {
		    // Process all wilayas with proper rate limiting and individual saves
		    return $this->fetch_all_wilayas_with_rate_limit($store_state, $is_economic);
	    }
    }

    /**
     * Fetch a single wilaya with rate limiting
     */
    private function fetch_single_wilaya_with_rate_limit($wilaya_id, $store_state, $is_economic, &$all_results, &$communes_extra, $path)
    {
        $url = $this->provider['api_url'] . "/fees?from_wilaya_id=" . $store_state . "&to_wilaya_id=" . $wilaya_id . "&page_size=1000";
        
        try {
            $request = new YalidineRequest($this->provider);
            $response_array = $request->get($url);

            if (!$this->is_valid_yalidine_response($response_array)) {
                error_log("Failed to fetch valid data for wilaya $wilaya_id: Invalid response");
                return [];
            }

            $this->process_wilaya_data($wilaya_id, $response_array, $all_results, $communes_extra, $is_economic);
            
            // Only cache if we have valid data
            if (!empty($all_results) && count($all_results) > 0) {
                file_put_contents($path, json_encode($all_results));
            }
            
            return $all_results;
        } catch (\ErrorException $exception) {
            error_log("Error processing wilaya $wilaya_id: " . $exception->getMessage());
            return [];
        }
    }

    /**
     * Fetch all wilayas with proper rate limiting and individual saves
     */
    private function fetch_all_wilayas_with_rate_limit($store_state, $is_economic)
    {
        $all_results = [];
        $communes_extra = [];
        $failed_requests = [];
        
        // Check if we have partial data and continue from where we left off
        $main_path = Functions::get_path(sprintf("%s_fees_DZ%s%s.json", $this->provider['slug'], $store_state, ($is_economic ? "0" : "1")));
        if (file_exists($main_path)) {
            $existing_data = json_decode(file_get_contents($main_path), true);
            if (is_array($existing_data)) {
                $all_results = $existing_data;
            }
        }

        // Process wilayas one by one with delays
        for ($wilaya_loop_id = 1; $wilaya_loop_id <= 58; $wilaya_loop_id++) {
            
            // Skip if we already have data for this wilaya
            if (isset($all_results[$wilaya_loop_id]) && !empty($all_results[$wilaya_loop_id])) {
                continue;
            }

            $url = $this->provider['api_url'] . "/fees?from_wilaya_id=" . $store_state . "&to_wilaya_id=" . $wilaya_loop_id . "&page_size=1000";

            try {
                $request = new YalidineRequest($this->provider);
                $response_array = $request->get($url);

                if (!$this->is_valid_yalidine_response($response_array)) {
                    $failed_requests[] = $wilaya_loop_id;
                    error_log("Failed to fetch valid data for wilaya $wilaya_loop_id: Invalid response");
                    continue;
                }

                $this->process_wilaya_data($wilaya_loop_id, $response_array, $all_results, $communes_extra, $is_economic);
                
                // Only cache after successful processing with valid data
                if (!empty($all_results) && count($all_results) > 0) {
                    file_put_contents($main_path, json_encode($all_results));
                }
                
                // Add delay between requests to respect rate limits
                // Wait 2 seconds between requests to be safe
                if ($wilaya_loop_id < 58) {
                    sleep(2);
                }

            } catch (\ErrorException $exception) {
                $failed_requests[] = $wilaya_loop_id;
                error_log("Error processing wilaya $wilaya_loop_id: " . $exception->getMessage());
                
                // On error, wait a bit longer before continuing
                sleep(5);
            }
        }

        // Retry failed requests with longer delays
        if (!empty($failed_requests)) {
            error_log("Retrying " . count($failed_requests) . " failed wilaya requests");
            
            foreach ($failed_requests as $retry_wilaya_id) {
                $url = $this->provider['api_url'] . "/fees?from_wilaya_id=" . $store_state . "&to_wilaya_id=" . $retry_wilaya_id . "&page_size=1000";

                try {
                    // Wait longer before retry
                    sleep(5);
                    
                    $request = new YalidineRequest($this->provider);
                    $response_array = $request->get($url);

                    if ($this->is_valid_yalidine_response($response_array)) {
                        $this->process_wilaya_data($retry_wilaya_id, $response_array, $all_results, $communes_extra, $is_economic);
                        
                        // Only cache after successful retry with valid data
                        if (!empty($all_results) && count($all_results) > 0) {
                            file_put_contents($main_path, json_encode($all_results));
                        }
                    } else {
                        error_log("Retry failed for wilaya $retry_wilaya_id: Invalid response");
                    }

                } catch (\ErrorException $exception) {
                    error_log("Retry failed for wilaya $retry_wilaya_id: " . $exception->getMessage());
                }
            }
        }

        return $all_results;
    }

    /**
     * Validate Yalidine API response before caching
     */
    private function is_valid_yalidine_response($response_array)
    {
        // Check if response is null or empty
        if ($response_array === null || empty($response_array)) {
            return false;
        }

        // Check if response has error
        if (isset($response_array['error']) && !empty($response_array['error'])) {
            return false;
        }

        // Check if required fields are present and not empty
        if (empty($response_array['to_wilaya_name'])) {
            return false;
        }

        // Check if per_commune data exists and is not empty
        if (!isset($response_array['per_commune']) || empty($response_array['per_commune'])) {
            return false;
        }

        // Check if per_commune is an array with valid data
        if (!is_array($response_array['per_commune'])) {
            return false;
        }

        // Validate that at least one commune has valid pricing data
        $has_valid_commune = false;
        foreach ($response_array['per_commune'] as $commune) {
            if (isset($commune['express_home']) && is_numeric($commune['express_home']) && $commune['express_home'] > 0) {
                $has_valid_commune = true;
                break;
            }
        }

        if (!$has_valid_commune) {
            return false;
        }

        return true;
    }

    private function process_wilaya_data($wilaya_id, $response_array, &$all_results, &$communes_extra, $is_economic)
    {
        $communes = $response_array['per_commune'];

        // Find the lowest express_home and express_desk prices
        $min_express_home = PHP_INT_MAX;
        $min_express_desk = PHP_INT_MAX;
        $min_economic_home = PHP_INT_MAX;
        $min_economic_desk = PHP_INT_MAX;

        foreach ($communes as $commune) {
            $min_express_home = min($min_express_home, $commune['express_home']);
            $min_express_desk = min($min_express_desk, $commune['express_desk']);
            $min_economic_home = min($min_economic_home, $commune['economic_home']);
            $min_economic_desk = min($min_economic_desk, $commune['economic_desk']);
        }

        // Calculate the differences and add extra_fee
        $result = [];

        foreach ($communes as $commune_id => $commune) {
            $result[$commune_id] = [
                'commune_id' => $commune['commune_id'],
                'commune_name' => $commune['commune_name'],
                'express_home' => $commune['express_home'],
                'express_desk' => $commune['express_desk'],
                'economic_home' => $commune['economic_home'],
                'economic_desk' => $commune['economic_desk'],
            ];

            if ($is_economic) {
                $communes_extra[$commune_id] = [
                    'extra_fee_home' => $commune['economic_home'] - $min_economic_home,
                    'extra_fee_desk' => $commune['economic_desk'] - $min_economic_desk,
                    'commune_id' => $commune['commune_id'],
                    'commune_name' => $commune['commune_name'],
                ];
            } else {
                $communes_extra[$commune_id] = [
                    'extra_fee_home' => $commune['express_home'] - $min_express_home,
                    'extra_fee_desk' => $commune['express_desk'] - $min_express_desk,
                    'commune_id' => $commune['commune_id'],
                    'commune_name' => $commune['commune_name'],
                ];
            }
        }

        $all_results[$wilaya_id]['wilaya_name'] = $response_array['to_wilaya_name'];
        $all_results[$wilaya_id]['wilaya_id'] = $wilaya_id;
        $all_results[$wilaya_id]['zone'] = $response_array['zone'];
        $all_results[$wilaya_id]['cod_percentage'] = $response_array['cod_percentage'];
        $all_results[$wilaya_id]['oversize_fee'] = $response_array['oversize_fee'];
        $all_results[$wilaya_id]['express_home'] = $min_express_home;
        $all_results[$wilaya_id]['economic_home'] = $min_economic_home;
        $all_results[$wilaya_id]['economic_desk'] = $min_economic_desk;
        $all_results[$wilaya_id]['express_desk'] = $min_express_desk;
        $all_results[$wilaya_id]['communes'] = $result;
        $all_results[$wilaya_id]['communes_extra'] = $communes_extra;
    }

    /**
     * @param $state
     * @return array|mixed|string
     */
    public function get_extra_fee($state)
    {
        $fees = $this->get_fees(false, $state);

        if (strpos($state, 'DZ-') !== false) {
            $state = (int) str_replace('DZ-', '', $state);

            $foundState = array_filter($fees, function($item) use($state) {
                return $item['wilaya_id'] === $state;
            });

            if ($foundState) {
                $foundState = reset($foundState);
                return $foundState['communes_extra'] ?? [];
            }
        }

        return [];
    }

    public function get_orders($queryData)
    {

        $mappingQueryData=[];

        $mappingQueryData['page'] = $queryData['page'] ?? 1;
        $mappingQueryData['page_size'] = $queryData['page_size'] ?? 10;

        $url = $this->provider['api_url'] . "/parcels?".http_build_query($mappingQueryData);

        $request = new YalidineRequest($this->provider);
        $response_array = $request->get($url);

        return [
            'items' => $this->mapping_order_results($response_array['data']),
            'total_data' => $response_array['total_data'],
            'current_page' => $queryData['page'],
        ];
    }

    private function mapping_order_results($data)
    {
        return array_map(function ($item) {
            return [
                'tracking' => $item['tracking'],
                'familyname' => $item['firstname'],
                'firstname' => $item['firstname'],
                'to_commune_name' => $item['to_commune_name'],
                'to_wilaya_name' => $item['to_wilaya_name'],
                'date' => $item['date_creation'],
                'contact_phone' => $item['contact_phone'],
                'city' => $item['to_commune_name'],
                'state' => $item['to_wilaya_name'],
                'last_status' => $item['last_status'],
                'date_last_status' => $item['date_last_status'] ?? '',
                'label' => $item['label'],
            ];
        }, $data);
    }

	public function handle_webhook($provider, $jsonData) {



		$provider_slug = $provider['slug'];

		$secret_key = get_option($provider_slug . '_webhook_token');

		error_log('Arrived 1221');


		// Check if secret key exists
		if (!$secret_key) {
			return new WP_REST_Response(
				['error' => 'Webhook not configured properly. Missing secret key.'],
				400
			);
		}

		error_log('Arrived 1232');

		// Save $_SERVER data to a file for debugging


		// Check if X_YALIDINE_SIGNATURE header exists
		if (!isset($_SERVER["HTTP_X_YALIDINE_SIGNATURE"]) && in_array($provider_slug, ['yalidine', 'guepex', 'wecanservices', 'yalitec', 'speedmail','easyandspeed'])) {
			return new WP_REST_Response(
				['error' => 'Invalid request. Missing signature header.'],
				400
			);
		}

		error_log('Arrived 1245');

		// Get the signature from the header
		$yalidine_signature = $_SERVER["HTTP_X_YALIDINE_SIGNATURE"];

		// Compute our own signature
		$computed_signature = hash_hmac("sha256", $jsonData, $secret_key);

		$logger = wc_get_logger();
		$logger->debug('Arrived 1241', array('source' => 'woo-bordereau-generator'));
		$logger->debug(sprintf('Arrived 1242 %s, %s', $computed_signature, $yalidine_signature), array('source' => 'woo-bordereau-generator'));

		// Verify signatures match
		if ($yalidine_signature !== $computed_signature) {
			return new WP_REST_Response(
				['error' => 'Invalid signature. Request rejected.'],
				403
			);
		}

		// If we get here, signature is valid - process the webhook
		$data = json_decode($jsonData, true);

		// Additional validation
		if (json_last_error() !== JSON_ERROR_NONE) {
			return new WP_REST_Response(
				['error' => 'Invalid JSON payload.'],
				400
			);
		}


		$type = $data['type'];
		$events = $data['events'];
		$updated = 0;

		foreach ($events as $event) {

			$theorder = null;
			$orderId = $event['data']['order_id'] ?? null;
			$tracking = $event['data']['tracking'];

			if ($orderId) {
				$theorder = wc_get_order($orderId);
			}

			if (!$theorder) {
				$args = array(
					'limit' => 1, // Assuming tracking numbers are unique; we're looking for one order
					'status' => 'any', // Or specify statuses if you're looking for something specific like 'completed'
					'meta_key' => '_shipping_tracking_number', // The meta key for the tracking number
					'meta_value' => $event['data']['tracking'], // The tracking number you're looking for
					'type' => 'shop_order', // Or 'shop_subscription' for subscriptions
					'return' => 'objects', // Return full order objects
				);

				// Perform the query
				$query = new \WC_Order_Query($args);
				$orders = $query->get_orders();

				// $orders will contain an array of order IDs that match the criteria
				if (!empty($orders)) {
					$theorder = $orders[0]; // Since we limited the query to 1, take the first result
				}
			}
			if ($theorder && $tracking) {

				$label = $event['data']['label'] ?? null;
				$itemStatus = $event['data']['status'];
				update_post_meta($theorder->get_id(), '_shipping_tracking_number', wc_clean($tracking));
				update_post_meta($theorder->get_id(), '_shipping_tracking_label', wc_clean($label));
				update_post_meta($theorder->get_id(), '_shipping_tracking_url', 'https://yalidine.com/suivre-un-colis/?tracking=' . wc_clean($tracking));
				update_post_meta($theorder->get_id(), '_shipping_tracking_method', $provider['slug']);

				switch ($type) {
					case 'parcel_status_updated':
						BordereauGeneratorAdmin::update_tracking_status($theorder, $itemStatus, 'webhook');

						(new BordereauGeneratorAdmin(WC_BORDEREAU_POST_TYPE, WC_BORDEREAU_GENERATOR_VERSION))
							->update_orders_status($theorder, $itemStatus);

						$updated++;
						break;
					case 'parcel_payment_updated':

						switch ($itemStatus) {
							case 'not-ready':
								$theorder->update_meta_data('_payment_status', 'not-ready');
								$theorder->save();
								$updated++;
								break;
							case 'ready':
								$theorder->update_meta_data('_payment_status', 'ready');
								$theorder->save();
								$updated++;
								break;
							case 'receivable':
								$theorder->update_meta_data('_payment_status', 'receivable');
								$theorder->save();
								$updated++;
								break;
							case 'payed':
								$theorder->update_meta_data('_payment_status', 'payed');
								$theorder->save();
								$updated++;
								break;
						}
				}
			}
		}

		return [
			'success' => true,
			'updated' => $updated
		];
	}
}
