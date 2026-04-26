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


use WC_Shipping_Zone;
use WC_Shipping_Zones;
use WooBordereauGenerator\Functions;
use WooBordereauGenerator\Helpers;

class LihlihExpressSettings
{
    /**
     * @var array
     */
    private $provider;

    /**
     * @var false|mixed|null
     */
    private $api_token;
    /**
     * @var false|mixed|null
     */
    private $pack;


    /**
     * @var false|mixed|null
     */
    private $wilaya_from;

    public function __construct(array $provider)
    {
        $this->api_token = get_option($provider['slug'].'_token');
        $this->pack = get_option($provider['slug'].'_pack');
        $this->wilaya_from = get_option($provider['slug'].'_wilaya_from');
        if(!$this->wilaya_from) {
            $this->wilaya_from = 'DZ-16';
        }
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
        $url = $this->provider['api_url'] . '/packages/status';
        $request = new \WooBordereauGenerator\Admin\Shipping\ZimoExpressRequest($this->provider);
        $content = $request->get($url, false, [
            'packages' => $codes,
        ]);

        return $this->format_tracking($content);
    }

    /**
     * Status Default
     * @return string[]
     */
    private function status(): array
    {

        return [
            "Ajouté" => "Ajouté",
            "Ramassag" => "Ramassage",
            "En livraison" => "En livraison",
            "Retourné" => "Retourné",
            "En Retour" => "En Retour",
            "Livré Encaissé" => "Livré Encaissé",
            "Payé" => "Payé",
            "Tentative" => "Tentative",
            "Correction de livraison" => "Correction de livraison",
            "Correction de retour" => "Correction de retour",
            "Correction d'echange" => "Correction d'echange",
            "Verification de poids" => "Verification de poids",
            "Annulé" => "Annulé",
            "attente d'echange" => "attente d'echange",
            "Echange En Retour" => "Echange En Retour",
            "Echange Retourné" => "Echange Retourné",
            "Livré Non Encaissé" => "Livré Non Encaissé"
        ];
    }

    private function format_tracking($json)
    {
        if ($json) {

            $final = [];
            foreach ($json as $key => $value) {
                $final[$value['tracking_code']] = $value['status'];
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
        $wilayas = $this->get_wilayas();

        $prices = [];
        $path = Functions::get_path('lihlihexpress_fees.json');

        if(empty($this->pack)) {
            $this->pack = 'START';
        }

        $zones = $this->getZones();

        if (!file_exists($path)) {

            $pricing = [];
            $page = 1;
            $per_page = 100;

            while ($page <= 5) {
                $url = $this->provider['api_url'] . 'tarif?page=' . $page . '&per_page=' . $per_page;
                // Make the API request
                $request = wp_safe_remote_get(
                    $url,
                    array(
                        'timeout' => 45,
                        'headers' => array(
                            'Content-Type' => 'application/json; charset=utf-8',
                            'Authorization'      => "Bearer ". $this->api_token,
                        ),
                    ),
                );

                $body = wp_remote_retrieve_body($request);
                $json = json_decode($body, true);

                foreach ($json['data'] as $key => $item) {
                    if ($item['pack'] == $this->pack) {
                        $pricing[] = $item;
                    }
                }

                $page++;
            }

            file_put_contents($path, json_encode($pricing));
        }

        $pricing = json_decode(file_get_contents($path), true);

        $wilayaForm = null;
        foreach($wilayas as $wilaya) {
            $wilayaFromId = (int) str_replace('DZ-', '', $this->wilaya_from);
            if ($wilaya['wilaya_id'] == $wilayaFromId) {
                $wilayaForm = $wilaya;
                break;
            }
        }

        $zoneFrom = $this->getWilayaZone($wilayaForm['wilaya_name'], $zones);
        if (!$zoneFrom) {
            $zoneFrom = 'ZONE';
        }

        $prices = $this->getZoneFeesByStart($zoneFrom, $pricing);

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

    function getZoneFeesByStart(string $zoneStart, array $fees): array {
        return array_filter($fees, function($fee) use ($zoneStart) {
            return $fee['zone_start'] === $zoneStart;
        });
    }

    function getWilayaZone(string $wilayaName, array $zones): string {
        foreach ($zones as $zoneName => $wilayas) {
            if (in_array($wilayaName, $wilayas)) {
                return $zoneName;
            }
        }
        return false;
    }

    private function shipping_zone_grouped($livraison, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled)
    {

    }

    private function shipping_zone_ungrouped($livraison, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled)
    {

        // Set memory limit to -1 (unlimited) and set time limit to 0 (unlimited)
        ini_set('memory_limit', '-1');
		set_time_limit(0);

        $zones = $this->getZones();
        $wilayas = $this->get_wilayas();
        $prices = [];

        foreach($livraison as $key => $zone) {

            $distinationZone = $zone['zone_destination'];
            $zoneFound = $zones[$distinationZone];
            if($zoneFound) {
                foreach ($zoneFound as $wilaya) {
                    foreach ($wilayas as $i) {
                        if ($i['wilaya_name'] === $wilaya) {
                            $prices[$i['wilaya_id']]['home'] = $zone['delivery_price'];
                            $prices[$i['wilaya_id']]['stop'] = $zone['stopdesk_price'];
                            $prices[$i['wilaya_id']]['name'] = $i['wilaya_name'];
                            $prices[$i['wilaya_id']]['id'] = $i['wilaya_id'];
                        }
                    }
                }
            }
        }

        $shipping_method_local_pickup = null;
        $shipping_method_flat_rate = null;

        $i = 1;
        foreach ($prices as $key => $wilaya) {


            $priceStopDesk = $wilaya['stop'];
            $priceFlatRate = $wilaya['home'];
            $wilaya_name = $priceFlatRate['name'];

            // Get all shipping zones
            $zones = WC_Shipping_Zones::get_zones();

            // Iterate through the zones and find the one with the matching name
            $found_zone = null;
            foreach ($zones as $zone) {
                if ($zone['zone_locations'][0]->code == 'DZ:DZ-' . str_pad($wilaya['id'], 2, '0', STR_PAD_LEFT)) {
                    $found_zone = $zone;
                    break;
                }
            }

            if (!$found_zone) {
                $zone = new WC_Shipping_Zone();
                $zone->set_zone_name(ucfirst(Helpers::normalizeCityName($wilaya_name)));
                $zone->set_zone_order($key);
                $zone->add_location('DZ:DZ-' . str_pad($wilaya['id'], 2, '0', STR_PAD_LEFT), 'state');
                $zone->save();
            } else {
                $zone = WC_Shipping_Zones::get_zone($found_zone['id']);
            }

            if ($priceFlatRate && $flat_rate_enabled) {

                $flat_rate_name = 'flat_rate_lihlihexpress';
                if (is_hanout_enabled()) {
                    $flat_rate_name = 'flat_rate';
                }

                $instance_flat_rate = $zone->add_shipping_method($flat_rate_name);
                $shipping_method_flat_rate = WC_Shipping_Zones::get_shipping_method($instance_flat_rate);
                $shipping_method_configuration_flat_rate = [
                    'woocommerce_' . $flat_rate_name . '_title' => $flat_rate_label ?? __('Flat rate', 'woo-bordereau-generator'),
                    'woocommerce_' . $flat_rate_name . '_cost' => $priceFlatRate,
                    'woocommerce_' . $flat_rate_name . '_type' => 'class',
                    'woocommerce_' . $flat_rate_name . '_provider' => $this->provider['slug'],
                    'instance_id' => $instance_flat_rate,
                    'method_id' => $this->provider['slug'] . '_flat_rate',
                ];

                $_REQUEST['instance_id'] = $instance_flat_rate;
                $shipping_method_flat_rate->set_post_data($shipping_method_configuration_flat_rate);
                $shipping_method_flat_rate->update_option('provider', $this->provider['slug']);
                $shipping_method_flat_rate->process_admin_options();

            }

            if ($priceStopDesk && $pickup_local_enabled) {

                $local_pickup_name = 'local_pickup_lihlihexpress';
                if (is_hanout_enabled()) {
                    $local_pickup_name = 'local_pickup';
                }

                $instance_local_pickup = $zone->add_shipping_method($local_pickup_name);
                $shipping_method_local_pickup = WC_Shipping_Zones::get_shipping_method($instance_local_pickup);
                $shipping_method_configuration_local_pickup = array(
                    'woocommerce_' . $local_pickup_name . '_title' => $pickup_local_label ?? __('Local Pickup', 'woo-bordereau-generator'),
                    'woocommerce_' . $local_pickup_name . '_cost' => $priceStopDesk,
                    'woocommerce_' . $local_pickup_name . '_type' => 'class',
                    'woocommerce_' . $local_pickup_name . '_provider' => $this->provider['slug'],
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
                return $item['wilaya_id'] == $wilayaId;
            });

            $wilaya = reset($wilaya);

            $commune = $params['billing']['city'];

            $communesArray = $this->get_communes($wilayaId, false);

            $communeFound = array_filter($communesArray, function ($v, $k) use ($commune) {
                return $v['name'] == $commune;
            }, ARRAY_FILTER_USE_BOTH);

            $commune = reset($communeFound);


            $products = $params['line_items'];
            $productString = Helpers::generate_products_string($products);

            $free_shipping = 0;
            $stop_desk = 0;

            if ($order->has_shipping_method('local_pickup')) {
                $stop_desk = 1;
            }

            if ($order->has_shipping_method('free_shipping')) {
                $free_shipping = 1;
            }

            $phone = explode('/', $params['billing']['phone']);
            $price = $params['total'];

            $data = array (
                'product' => $productString,
                'lastname' => !empty($order->get_billing_last_name()) ? $order->get_billing_last_name() : $order->get_billing_first_name(),
                'firstname' => !empty($order->get_billing_first_name()) ? $order->get_billing_first_name() : $order->get_billing_last_name(),
                'phone' =>  implode('-', $phone),
                'address' => $params['billing']['address_1'] ?? '',
                'city' => $commune['id'],
                'wilaya' => $wilayaId,
                'deliveryMethod' => $stop_desk ? 1: 0,
                'price' => $price,
                'freeDelivery' => $free_shipping,
                'exchange' => 0,
            );

            $url = $this->provider['api_url'] . 'packages/create';

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => http_build_query($data),
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/x-www-form-urlencoded',
                    'Authorization: ' . "Bearer ".$this->api_token
                ),
            ));

            $body = curl_exec($curl);
            curl_close($curl);

            $content = json_decode($body, true);

            if (isset($content['success']) && $content['success'] === false) {
                $error_messages = [];

                if (isset($content['data'])) {
                    foreach ($content['data'] as $field => $errors) {
                        if (is_array($errors) && !empty($errors)) {
                            $error_messages[] = $errors[0];
                        }
                    }
                }

                set_transient('order_bulk_add_error', implode(' ', $error_messages), 45);
                $logger = \wc_get_logger();
                $logger->error('Bulk Upload Error: ' . implode(' ', $error_messages), array('source' => WC_BORDEREAU_POST_TYPE));
                return;
            }

            if ($content['statusCode'] == 400) {
                set_transient('order_bulk_add_error', $content['message'], 45);
                $logger = \wc_get_logger();
                $logger->error('Bulk Upload Error: ' . $content['message'], array('source' => WC_BORDEREAU_POST_TYPE));
                return;
            }

            $trackingNumber = $content['data']['tracking'];
            $label = $content['data']['tracking']; // Using tracking as label since no bordereau

            update_post_meta($order->get_id(), '_shipping_tracking_number', wc_clean($trackingNumber));
            update_post_meta($order->get_id(), '_shipping_tracking_label', wc_clean($label));
            update_post_meta($order->get_id(), '_shipping_label', wc_clean($label));
            update_post_meta($order->get_id(), '_shipping_tracking_method', $this->provider['slug']);

            $order->update_meta_data('_shipping_tracking_number', wc_clean($trackingNumber));
            $order->update_meta_data('_shipping_tracking_label', wc_clean($label));
            $order->update_meta_data('_shipping_label', wc_clean($label));
            $order->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
            $order->save();

            Helpers::make_note($order, $trackingNumber, $this->provider['name']);

        }
    }

    public function get_wilayas(): array
    {

        $path = Functions::get_path('lihlihexpress_wilaya_' . date('Y-m-d') . '.json');

        if (!file_exists($path)) {

            $request = wp_safe_remote_get(
                $this->provider['api_url'] . 'wilayas?per_page=100&page=1',
                array(
                    'timeout'     => 45,
                    'headers'     => array(
                        'Content-Type' => 'application/json; charset=utf-8',
                        'Authorization'      => "Authorization: Bearer ".$this->api_token,
                    ),
                ),
            );

            if (is_wp_error($request)) {

                wp_send_json([
                    'error' => true,
                    'message'=> __("An error has occurred. Please see log file ", 'woo-bordereau-generator')
                ], 401);
            }

            $body = wp_remote_retrieve_body($request);

            if (empty($body)) {
                wp_send_json([
                    'error' => true,
                    'message'=> __("An error has occurred. Please see log file ", 'woo-bordereau-generator')
                ], 401);
            }

            $content = json_decode($body, true);

            file_put_contents($path, json_encode($content['data']));
        }

        return json_decode(file_get_contents($path), true);

    }

    /**
     * @param int $wilaya_id
     * @param bool $hasStopDesk
     *
     * @return array
     */
    public function get_communes($wilaya_id, bool $hasStopDesk)
    {

        $path = Functions::get_path('lihlihexpress_wilaya_' . date('Y-m-d') . '.json');

        if (!file_exists($path)) {
            $this->get_wilayas();
        }
        $data = json_decode(file_get_contents($path), true);
        $communes = array_filter($data, function($item) use ($wilaya_id) {
            $wilaya_id = (int) str_replace('DZ-', '', $wilaya_id);
            return $item['wilaya_id'] == $wilaya_id;
        });

        $communes = reset($communes);
        $communesResult = [];

        foreach ($communes['cities'] as $i => $item) {

            $communesResult[$i]['id'] = $item['city_id'];
            $communesResult[$i]['name'] = $item['city_name'];
        }

        return $communesResult;
    }

    public function get_status()
    {
        return $this->status();
    }

    /**
     * @return array
     */
    public function getZones(): array
    {
        $page = 1;
        $per_page = 100;

        $pathZones = Functions::get_path('lihlihexpress_zones.json');

        if (!file_exists($pathZones)) {

            $url = $this->provider['api_url'] . 'zones?page=' . $page . '&per_page=' . $per_page;
            // Make the API request
            $request = wp_safe_remote_get(
                $url,
                array(
                    'timeout' => 45,
                    'headers' => array(
                        'Content-Type' => 'application/json; charset=utf-8',
                        'Authorization' => "Bearer " . $this->api_token,
                    ),
                ),
            );

            $body = wp_remote_retrieve_body($request);
            $json = json_decode($body, true);
            $zones = [];

            foreach ($json['data'] as $key => $item) {

                $rr = [];
                foreach ($item['agencies_covered'] as $region) {
                    $rr[] = $region['wilayas_covered'];
                }

                $zones[$item['zone_name']] = array_unique($rr);
            }

            file_put_contents($pathZones, json_encode($zones));
        }

        return json_decode(file_get_contents($pathZones), true);
    }


}
