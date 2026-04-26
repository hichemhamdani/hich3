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

class ConexlogShippingSettings
{

    /**
     * @var array
     */
    private $provider;
    private $api_token;

    public function __construct(array $provider)
    {
        $this->provider = $provider;
        $this->api_token = get_option($provider['slug'] . '_api_token');
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
                'name' => __($filed['name'], 'wc-bordereau-generator'),
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
     * Verify API credentials by attempting to fetch wilayas
     *
     * @param array $credentials Temporary credentials to test (keys match field 'value' from provider config)
     * @return array ['success' => bool, 'message' => string]
     */
    public function check_auth(array $credentials = []): array
    {
        try {
            // Get API token from passed credentials or fall back to saved option
            $api_token = $credentials[$this->provider['slug'] . '_api_token'] ?? $this->api_token;

            if (empty($api_token)) {
                return [
                    'success' => false,
                    'message' => __('API Token is required', 'woo-bordereau-generator')
                ];
            }

            // Make a test request to wilayas endpoint (same as get_wilaya_from_provider)
            $response = wp_safe_remote_get(
                $this->provider['api_url'] . '/get/wilayas?api_token=' . $api_token,
                [
                    'timeout' => 30,
                    'headers' => [
                        'Content-Type' => 'application/json; charset=utf-8',
                    ],
                ]
            );

            if (is_wp_error($response)) {
                return [
                    'success' => false,
                    'message' => __('Connection error: ', 'woo-bordereau-generator') . $response->get_error_message()
                ];
            }

            $httpCode = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            // Conexlog returns array directly like [{"wilaya_id":1,...}, ...]
            if ($httpCode === 200 && is_array($data) && !isset($data['error'])) {
                return [
                    'success' => true,
                    'message' => __('Credentials verified successfully', 'woo-bordereau-generator')
                ];
            } elseif ($httpCode === 401 || $httpCode === 403 || (isset($data['success']) && $data['success'] === false)) {
                return [
                    'success' => false,
                    'message' => $data['message'] ?? __('Invalid API credentials', 'woo-bordereau-generator')
                ];
            } else {
                $errorMsg = $data['message'] ?? $data['error'] ?? __('Unknown error occurred', 'woo-bordereau-generator');
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

        $currentDate = date('Y-m-d'); // Current date
        $startDate = date('Y-m-d', strtotime('-15 days')); // 15 days before the current date


        // Your existing code
        $api_token = get_option($this->provider['slug'] . '_api_token');

        $allResults = [];

        $status = $this->status();
        $page = 1;
        $url = $this->provider['api_url'] . '/get/orders?api_token=' . $api_token . '&start_date=' . $startDate . '&end_date=' . $currentDate . '&page=' . $page;

        // Make the API request
        $request = wp_safe_remote_get(
            $url,
            array(
                'timeout' => 45,
                'headers' => array(
                    'Content-Type' => 'application/json; charset=utf-8',
                ),
            ),
        );


        // Retrieve the response body and decode it
        $body = wp_remote_retrieve_body($request);
        $json = json_decode($body, true);

        if (isset($json['data']) && is_array($json['data'])) {
            foreach ($json['data'] as $item) {
                if (isset($item['tracking']) && isset($item['status'])) {
                    $allResults[$item['tracking']] = $status[$item['status']] ?? $item['status'];
                }
            }
        }


        foreach (range(2, $json['last_page']) as $page) {
            // Create the URL for this chunk
            $url = $this->provider['api_url'] . '/get/orders?api_token=' . $api_token . '&start_date=' . $startDate . '&end_date=' . $currentDate . '&page=' . $page;

            // Make the API request
            $request = wp_safe_remote_get(
                $url,
                array(
                    'timeout' => 45,
                    'headers' => array(
                        'Content-Type' => 'application/json; charset=utf-8',
                    ),
                ),
            );

            // Check for errors
            if (is_wp_error($request)) {
                continue; // Skip this chunk if there's an error
            }

            // Retrieve the response body and decode it
            $body = wp_remote_retrieve_body($request);
            $json = json_decode($body, true);

            if (isset($json['data']) && is_array($json['data'])) {
                foreach ($json['data'] as $item) {
                    if (isset($item['tracking']) && isset($item['status'])) {
                        $allResults[$item['tracking']] = $status[$item['status']] ?? $item['status'];
                    }
                }
            }
        }

        return $allResults;

    }

    /**
     * @return string[]
     */
    private function status()
    {

        return [
            "order_information_received_by_carrier" => "Colis En attente d'envoi",
            "picked" => "Colis reçu par la sociète de livraison",
            "accepted_by_carrier" => "Colis arrivé à la station régionale",
            "dispatched_to_driver" => "Colis En livraison",
            "livred" => "Colis Livré",
            "livré_non_encaissé" => "Colis Livré non encaissé",
            "vers_wilaya" => "Vers Wilaya",
            "en_hub" => "En hub",
            "en_livraison" => "En livraison",
            "attempt_delivery" => "Tentative de livraison échouée",
            "return_asked" => "Retour initié",
            "retour_en_traitement" => "Retour en traitement",
            "return_in_transit" => "Retour en transit",
            "Return_received" => "Retour réceptionné par le vendeur",
            "encaissed" => "Commande encaissée",
            "payed" => "Paiement effectué",
            "prete_a_expedier" => "Prêt à expédier",
            "payé_et_archivé" => "Payé et archivé",
            "encaissé_non_payé" => "Encaissé non payé",
            "retour_reçu" => "Colis retourné",
        ];
    }


    /**
     * @param $json
     * @return array
     */
    private function format_tracking($json)
    {
        if ($json) {
            $result = [];
            if (isset($json['data'])) {

                foreach ($json['data'] as $key => &$entry) {
                    $result[$entry['tracking']][$key] = $entry;
                }

                $final = [];
                foreach ($result as $key => $value) {
                    $final[$key] = $this->status()[array_values($value)[0]['status']] ?? null;
                }
                return $final;
            }

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

        $url = $this->provider['api_url'] . "/get/fees?api_token=".$this->api_token;

        $request = wp_safe_remote_get(
            $url,
            array(
                'timeout' => 45,
                'headers' => array(
                    'Content-Type' => 'application/json; charset=utf-8',
                ),
            ),
        );

        if (is_wp_error($request)) {
            wp_send_json([
                'error' => true,
                'message' => "An error has been occurred"
            ], 401);
        }

        $body = wp_remote_retrieve_body($request);
        $json = json_decode($body, true);


        if (get_option('shipping_zones_grouped') && get_option('shipping_zones_grouped') == 'yes') {
            $this->shipping_zone_grouped($json['livraison'], $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled);
        } else {
            $this->shipping_zone_ungrouped($json['livraison'], $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled);
        }


        wp_send_json_success(
            array(
                'message' => 'success',
            )
        );
    }

    private function shipping_zone_grouped($json, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled)
    {
    }

    private function shipping_zone_ungrouped($wilayas, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled)
    {

        // Set memory limit to -1 (unlimited) and set time limit to 0 (unlimited)
        ini_set('memory_limit', '-1');
		set_time_limit(0);

        $zones = [];
        $wilayasData = $this->get_wilayas();

        $i = 1;
        foreach ($wilayas as $key => $wilaya) {

            $searchWilayaId = $wilaya['wilaya_id'];

            $foundWilaya = array_values(array_filter($wilayasData, function ($w) use ($searchWilayaId) {
                return $w['wilaya_id'] === $searchWilayaId;
            }))[0] ?? null;

            // Get all shipping zones
            $zones = WC_Shipping_Zones::get_zones();

            // Iterate through the zones and find the one with the matching name
            $found_zone = null;
            foreach ($zones as $zone) {
                if (isset($zone['zone_locations'])) {
                    if ($zone['zone_locations'][0]->code == 'DZ:DZ-' . str_pad($wilaya['wilaya_id'], 2, '0',STR_PAD_LEFT)) {
                        $found_zone = $zone;
                        break;
                    }
                }
            }

            if (!$found_zone) {
                $zone = new WC_Shipping_Zone();
                $zone->set_zone_name(Helpers::normalizeCityName($foundWilaya['wilaya_name']));
                $zone->set_zone_order($key);
                $zone->add_location('DZ:DZ-' . str_pad($wilaya['wilaya_id'], 2, '0', STR_PAD_LEFT), 'state');
                $zone->save();
            } else {
                $zone = WC_Shipping_Zones::get_zone($found_zone['id']);
            }

            if (isset($wilaya['tarif']) && $flat_rate_enabled) {

                $flat_rate_name = 'flat_rate_conexlog';
                if (is_hanout_enabled()) {
                    $flat_rate_name = 'flat_rate';
                }

                $instance_flat_rate = $zone->add_shipping_method($flat_rate_name);
                $shipping_method_flat_rate = WC_Shipping_Zones::get_shipping_method($instance_flat_rate);

                $shipping_method_configuration_flat_rate = [
                    'woocommerce_'.$flat_rate_name.'_title'              => $flat_rate_label ?? __('Flat rate', 'wc-bordereau-generator'),
                    'woocommerce_'.$flat_rate_name.'_cost'               => $wilaya['tarif'],
                    'woocommerce_'.$flat_rate_name.'_type'               => 'class',
                    'woocommerce_'.$flat_rate_name.'_provider'           => $this->provider['slug'],
                    'instance_id' => $instance_flat_rate,
                ];

                $_REQUEST['instance_id'] = $instance_flat_rate;
                $shipping_method_flat_rate->set_post_data($shipping_method_configuration_flat_rate);
                $shipping_method_flat_rate->process_admin_options();
            }

            if (isset($wilaya['tarif_stopdesk']) && $pickup_local_enabled) {
                $local_pickup_name = 'local_pickup_conexlog';
                if (is_hanout_enabled()) {
                    $local_pickup_name = 'local_pickup';
                }

                $instance_local_pickup = $zone->add_shipping_method($local_pickup_name);
                $shipping_method_local_pickup = WC_Shipping_Zones::get_shipping_method($instance_local_pickup);
                $shipping_method_configuration_local_pickup = array(
                    'woocommerce_'.$local_pickup_name.'_title'               => $pickup_local_label ?? __('Local Pickup', 'wc-bordereau-generator'),
                    'woocommerce_'.$local_pickup_name.'_cost'                => $wilaya['tarif_stopdesk'],
                    'woocommerce_'.$local_pickup_name.'_type'                => 'class',
                    'woocommerce_'.$local_pickup_name.'_provider'            => $this->provider['slug'],
                );

                $_REQUEST['instance_id'] = $instance_local_pickup;
                $shipping_method_local_pickup->set_post_data($shipping_method_configuration_local_pickup);
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
            $orderId = $order->get_order_number();

            $wilayaCode = $params['billing']['state'];
            $wilaya = Helpers::get_wilaya($wilayaCode, $wilayas);

            if ($wilaya !== null) {

                $products = $order->get_items();
                $productString = Helpers::generate_products_string($products);
                $productString = mb_strimwidth($productString, 0, 252, "..."); // issue of 255 char
                $phone2 = '';

                $phone = $params['billing']['phone'];
                $phone = str_replace(' ', '', str_replace('+213', '0', $phone));
                $phones = explode('/', $phone);

                if (count($phones) > 1) {
                    $phone2 = $phone[1];
                    $phone = $phone[0];
                }

                $free_shipping = 0;
                $stop_desk = 0;

                if ($order->has_shipping_method('free_shipping')) {
                    $free_shipping = 1;
                }

                if ($order->has_shipping_method('local_pickup')) {
                    $stop_desk = 1;
                }

                $fname = !empty($params['billing']['first_name']) ? $params['billing']['first_name'] : $params['billing']['last_name'];
                $lname = !empty($params['billing']['last_name']) ? $params['billing']['last_name'] : $params['billing']['first_name'];

                $full_name = $fname . ' ' . $lname;

                if (is_hanout_enabled()) {
                    $full_name = $fname;
                }

                $note = stripslashes(get_option('wc_bordreau_default-shipping-note'));
                $wilayaId = $wilaya['wilaya_id'];

                $data = [
                    "reference" => $orderId,
                    "nom_client" => $full_name,
                    "telephone" => $phone,
                    "telephone_2" => $phone2,
                    "adresse" => !empty($params['billing']['address_1']) ? $params['billing']['address_1'] : $params['billing']['city'],
                    "commune" => str_replace('\\', '', $params['billing']['city']),
                    "code_wilaya" => $wilayaId,
                    "produit" => $productString,
                    "montant" => $params['total'],
                    "boutique" => get_the_title(get_option('woocommerce_shop_page_id')),
                    "remarque" => $note,
                    "stop_desk" => $stop_desk, // [ 0 = a domicile , 1 = STOP DESK ]
                    "type" => 1, //  [ 1 = Livraison , 2 = Echange , 3 = PICKUP , 4 = Recouvrement ]
                ];


                $url = $this->provider['api_url'] . "/create/order?api_token=". $this->api_token;

                $request = wp_safe_remote_post(
                    $url,
                    [
                        'headers' => [
                            'Content-Type' => 'application/json',
                        ],
                        'body' => wp_json_encode($data),
                        'method' => 'POST',
                        'data_format' => 'body',
                    ]
                );

                $body = wp_remote_retrieve_body($request);
                $response = json_decode($body, true);

                $etqNew = $this->provider['api_url'] . '/get/order/label/?api_token=' . $this->api_token . '&tracking=' . $response['tracking'];
                $track = 'https://suivi.ecotrack.dz/suivi/' . $response['tracking'];
                update_post_meta($order->get_id(), '_shipping_tracking_number', wc_clean($response['tracking']));
                update_post_meta($order->get_id(), '_shipping_tracking_label', $etqNew);
                update_post_meta($order->get_id(), '_shipping_label', $etqNew);
                update_post_meta($order->get_id(), '_shipping_tracking_url', $track);
                update_post_meta($order->get_id(), '_shipping_tracking_method', $this->provider['slug']);

                $order->update_meta_data('_shipping_tracking_number', wc_clean($response['tracking']));
                $order->update_meta_data('_shipping_tracking_label', $etqNew);
                $order->update_meta_data('_shipping_label', $etqNew);
                $order->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
                $order->save();

                Helpers::make_note($order, $response['tracking'], $this->provider['name']);
            }
        }
    }

    /**
     * Get Wilayas
     * @return array|mixed|null
     */
    private function get_wilayas()
    {
        $path = Functions::get_path('ups_conexlog_' . $this->provider['slug'] . '_wilaya.json');

        if (file_exists($path)) {

            $wilayas = json_decode(file_get_contents($path), true);

            if ($wilayas == null || count($wilayas) == 0) {
                return $this->get_wilaya_from_provider();
            }
        } else {
            $response = $this->get_wilaya_from_provider();

            file_put_contents($path, $response);
            if (is_array($response)) {
                return $response;
            }

            return json_decode($response, true);
        }

        return $wilayas;
    }

    private function get_wilaya_from_provider()
    {
        if ($this->api_token) {

            $request = wp_safe_remote_get(
                $this->provider['api_url'] . '/get/wilayas?api_token='.$this->api_token,
                array(
                    'timeout' => 45,
                    'headers' => array(
                        'Content-Type' => 'application/json; charset=utf-8',
                    ),
                ),
            );

            if (is_wp_error($request)) {

                wp_send_json([
                    'error' => true,
                    'message' => __("An error has occurred. Please see log file ", 'wc-bordereau-generator')
                ], 401);
            }

            $body = wp_remote_retrieve_body($request);

            if (empty($body)) {
                wp_send_json([
                    'error' => true,
                    'message' => __("An error has occurred. Please see log file ", 'wc-bordereau-generator')
                ], 401);
            }

            return json_decode($body, true);
        }
    }

    public function get_status()
    {
        return $this->status();
    }

    /**
     * @param int $wilaya_id
     * @param bool $hasStopDesk
     *
     * @return array
     */
    public function get_communes($wilaya_id, bool $hasStopDesk): array
    {
        $path = Functions::get_path('ups_conexlog_cities_v2.json');

        if (! file_exists($path)) {
            $content = file_get_contents($this->provider['api_url'].'/get/communes?api_token=' . $this->api_token);
            file_put_contents($path, $content);
        }

        $communes = json_decode(file_get_contents($path), true);

        $communesResult = [];

        if (! is_numeric($wilaya_id)) {
            $wilaya_id = (int) str_replace('DZ-', '', $wilaya_id);
        }

        $found = [];

        if (is_array($communes) && count($communes) > 0) {
            $found = array_filter($communes, function ($v, $k) use ($wilaya_id) {
                return (int) $v['wilaya_id'] == $wilaya_id;
            }, ARRAY_FILTER_USE_BOTH);
        }


        foreach ($found as $i => $item) {
            if($hasStopDesk) {
                if($item['has_stop_desk']) {
                    $communesResult[$i]['id'] = $item['nom'];
                    $communesResult[$i]['name'] = $item['nom'];
                }
            } else {
                $communesResult[$i]['id'] = $item['nom'];
                $communesResult[$i]['name'] = $item['nom'];
            }
        }

        return $communesResult;
    }


    public function get_orders($queryData)
    {

        $mappingQueryData=[];

        $mappingQueryData['per_page'] = $queryData['page_size'] ?? 10;
        $mappingQueryData['page'] = $queryData['page'] ?? 1;

        $request = wp_safe_remote_get(
            $this->provider['api_url'] . '/get/orders?'.http_build_query($mappingQueryData),
            array(
                'timeout' => 45,
                'headers' => array(
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Authorization' => "Authorization: Bearer " . $this->api_token,
                ),
            ),
        );

        if (is_wp_error($request)) {
            wp_send_json([
                'error' => true,
                'message' => __("An error has occurred. Please see log file ", 'wc-bordereau-generator')
            ], 401);
        }

        $body = wp_remote_retrieve_body($request);

        if (empty($body)) {
            wp_send_json([
                'error' => true,
                'message' => __("An error has occurred. Please see log file ", 'wc-bordereau-generator')
            ], 401);
        }

        $response_array = json_decode($body, true);

        return [
            'items' => $this->mapping_order_results($response_array['data']),
            'total_data' => $response_array['total'],
            'current_page' => $response_array['current_page'],
        ];
    }

    private function mapping_order_results($data)
    {
        return array_map(function ($item) {
            return [
                'tracking' => $item['tracking'],
                'familyname' => $item['client'],
                'firstname' => '',
                'to_commune_name' => $item['commune'],
                'to_wilaya_name' => $this->get_to_wilaya_name($item['wilaya_id']),
                'date' => $item['created_at'],
                'contact_phone' => $item['phone_2'] != null ? sprintf("%s/%s", $item['phone'], $item['phone_2']) : $item['phone'],
                'city' => '',
                'state' => '',
                'last_status' => $item['status'],
                'date_last_status' => $item['date_last_status'] ?? '',
                'label' => sprintf("%s/get/order/label?tracking=%s&api_token=%s", $this->provider['api_url'], $item['tracking'], $this->api_token),
            ];
        }, $data);
    }

    private function get_to_wilaya_name($wilaya_id)
    {

        $path = Functions::get_path(sprintf("%s_wilayas", $this->provider['slug']));

        if (!file_exists($path)) {
            $wilaya = $this->get_wilaya_from_provider();
            file_put_contents($path, json_encode($wilaya));
        }

        $wilayas = json_decode(file_get_contents($path), true);


        foreach ($wilayas as $key => $wilaya) {
            if ($wilaya['wilaya_id'] == $wilaya_id) {
                return $wilaya['wilaya_name'];
            }
        }

        return $wilaya_id;

    }

}
