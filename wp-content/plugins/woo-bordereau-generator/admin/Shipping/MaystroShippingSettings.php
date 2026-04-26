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

class MaystroShippingSettings
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
        $this->api_id = get_option('maystro_delivery_store_id');
        $this->api_token = get_option('maystro_delivery_api_token');
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
     * Verify API credentials by attempting to fetch fees
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

            // Make a test request to wilayas endpoint
            $response = wp_safe_remote_get(
                $this->provider['api_url'] . '/base/wilayas/',
                [
                    'timeout' => 30,
                    'headers' => [
                        'Content-Type' => 'application/json; charset=utf-8',
                        'Authorization' => 'Token ' . $api_token,
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

            // Check for success - wilayas endpoint returns array of wilayas on success
            if ($httpCode === 200 && is_array($data)) {
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
                // Try to get error message from various response formats
                $errorMsg = $data['message'] 
                    ?? $data['detail']
                    ?? $data['error'] 
                    ?? $data['error_message'] 
                    ?? (is_string($data) ? $data : null)
                    ?? sprintf(__('Error (HTTP %d)', 'woo-bordereau-generator'), $httpCode);
                
                return [
                    'success' => false,
                    'message' => $errorMsg
                ];
            }
        } catch (\Exception $e) {
            error_log('Maystro check_auth Exception: ' . $e->getMessage());
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
     * @return array|false
     */
    public function bulk_track(array $codes)
    {

        $codesString = implode(',', array_values($codes));

        $url = $this->provider['api_url'] . '/get/orders?trackings=' . $codesString;

        $api_token = get_option($this->provider['slug'] . '_api_token');

        $request = wp_safe_remote_get(
            $url,
            array(
                'timeout' => 45,
                'headers' => array(
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Authorization' => "Token " . $api_token,
                ),
            ),
        );

        if (is_wp_error($request)) {
            return false;
        }

        $body = wp_remote_retrieve_body($request);
        $json = json_decode($body, true);

        return $this->format_tracking($json);
    }

    /**
     * Status Default
     * @return string[]
     */
    private function status(): array
    {

        return [
            "order_information_received_by_carrier" => "Colis En attente d'envoi",
            "picked" => "Colis reçu par la sociète de livraison",
            "accepted_by_carrier" => "Colis arrivé à la station régionale",
            "dispatched_to_driver" => "Colis En livraison",
            "livred" => "Colis Livré",
            "attempt_delivery" => "Tentative de livraison échouée",
            "return_asked" => "Retour initié",
            "return_in_transit" => "Retour en transit",
            "Return_received" => "Retour réceptionné par le vendeur",
            "encaissed" => "Commande encaissée",
            "payed" => "Paiement effectué",
            "prete_a_expedier" => "Prêt à expédier"
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

            foreach ($json['data'] as $key => &$entry) {
                $result[$entry['tracking']][$key] = $entry;
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
     * @return false|void
     */
    public function sync_products()
    {

        try {
            $url = $this->provider['api_url'] . "/stock/products/";

            $request = wp_safe_remote_get(
                $url,
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => "Token " . $this->api_token
                    ]
                ]
            );


            if (is_wp_error($request)) {
                return false;
            }

            $body = wp_remote_retrieve_body($request);
            $json = json_decode($body, true);

            if (isset($json['list'])) {
                if (isset($json['list']['results'])) {
                    $pages = ((int)$json['list']['count'] / 20) + 1;
                    $this->handle_products_in_provider($json);
                    $page = 2;

                    while ($page <= $pages) {

                        $url = $this->provider['api_url'] . "/stock/products/?page=" . $page;
                        $request = wp_safe_remote_get(
                            $url,
                            [
                                'headers' => [
                                    'Content-Type' => 'application/json',
                                    'Authorization' => "Token " . $this->api_token
                                ]
                            ]
                        );

                        if (is_wp_error($request)) {
                            return false;
                        }

                        $body = wp_remote_retrieve_body($request);
                        $json = json_decode($body, true);

                        $this->handle_products_in_provider($json);
                        $page++;
                    }
                }
            }


            $args = [
                'status' => 'publish',
                'orderby' => 'name',
                'order' => 'ASC',
                'limit' => -1,
            ];

            $all_products = wc_get_products($args);

            foreach ($all_products as $key => $product) {

                if ($product->get_type() == "variable") {

                    foreach ($product->get_available_variations() as $variation) {

                        $product_id = $product->get_id();
                        $variation_id = $variation['variation_id'];
                        $variation_attributes = $variation['attributes'];

                        $variation_name = $product->get_name() . ' - ';

                        foreach ($variation_attributes as $attribute_value) {
                            $variation_name .= urldecode($attribute_value) . ' ';
                        }

                        $id = get_post_meta($variation_id, 'maystro_delivery_product_id', true);

                        if (!$id) {

                            $data = [
                                'store_id' => $this->api_id,
                                'logistical_description' => $variation_name,
                                'product_id' => "$product_id:$variation_id"
                            ];

                            $request = wp_safe_remote_post(
                                $url,
                                [
                                    'headers' => [
                                        'Content-Type' => 'application/json',
                                        'Authorization' => "Token " . $this->api_token
                                    ],
                                    'body' => wp_json_encode($data),
                                    'method' => 'POST',
                                    'data_format' => 'body',
                                    'timeout' => 15
                                ]
                            );

                            if (!is_wp_error($request)) {
                                $body = wp_remote_retrieve_body($request);
                                $response = json_decode($body, true);
                                if (!isset($response['status_code'])) {
                                    update_post_meta($variation_id, 'maystro_delivery_product_id', $response['id'], true);
                                }
                            }

                        }
                    }
                } else {

                    $product_id = $product->get_id();
                    $product_name = $product->get_name();
                    $id = get_post_meta($product_id, 'maystro_delivery_product_id', true);

                    if (!$id) {

                        $data = [
                            'store_id' => $this->api_id,
                            'logistical_description' => $product_name,
                            'product_id' => "$product_id:0"
                        ];

                        $request = wp_safe_remote_post(
                            $url,
                            [
                                'headers' => [
                                    'Content-Type' => 'application/json',
                                    'Authorization' => "Token " . $this->api_token
                                ],
                                'body' => wp_json_encode($data),
                                'method' => 'POST',
                                'data_format' => 'body',
                                'timeout' => 15
                            ]
                        );

                        if (!is_wp_error($request)) {
                            $body = wp_remote_retrieve_body($request);
                            $response = json_decode($body, true);

                            if (!isset($response['status_code'])) {
                                add_post_meta($product_id, 'maystro_delivery_product_id', $response['id'], true);
                            }
                        }
                    }
                }
            }

            wp_send_json([
                'success' => true,
                'message' => __("Your products has been synced with Maystro delivery", 'woo-bordereau-generator')
            ], 200);

        } catch (\ErrorException $exception) {
            wp_send_json([
                'error' => true,
                'message' => $exception->getMessage()
            ], 500);
        }

    }

    /**
     * @param $json
     * @return void
     */
    private function handle_products_in_provider($json)
    {

        foreach ($json['list']['results'] as $item) {
            $product_array = explode(':', $item['product_id']);
            if (count($product_array) == 2) {
                if ($product_array[1] == 0) {
                    $product_id = $product_array[0];
                } else {
                    $product_id = $product_array[1];
                }

                $product = wc_get_product($product_id);

                if ($product) {

                    $id = get_post_meta($product_id, 'maystro_delivery_product_id', true);

                    if (!$id) {

                        update_post_meta($product_id, 'maystro_delivery_product_id', $item['id'], true);
                    }
                }

            } elseif (is_numeric($item['product_id'])) {

                $product = wc_get_product($item['product_id']);

                if ($product) {
                    $id = get_post_meta($item['product_id'], 'maystro_delivery_product_id', true);
                    if (!$id) {

                        update_post_meta($product->get_id(), 'maystro_delivery_product_id', $item['id'], true);
                    }
                }
            } elseif (is_string($item['product_id'])) {

                $product_id = wc_get_product_id_by_sku($item['product_id']);

                if ($product_id) {
                    $product = wc_get_product($product_id);

                    if ($product) {
                        $id = get_post_meta($item['product_id'], 'maystro_delivery_product_id', true);
                        if (!$id) {

                            update_post_meta($product_id, 'maystro_delivery_product_id', $item['id'], true);
                        }
                    }
                }
            }

        }
    }

    /**
     * @return false|void
     */
    public function get_products_by_name() {

        $query = null;

        if(isset($_POST['query'])) {
            $query = wc_clean($_POST['query']);
        }

        $path = Functions::get_path('maystro_products_'.date('z').'.json');

        if (!file_exists($path)) {
            $pages = 1;
            $page = 1;

            $products = [];

            while ($page <= $pages) {

                $url = $this->provider['api_url'] . "/stock/products/?page=" . $page;

                $request = wp_safe_remote_get(
                    $url,
                    [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'Authorization' => "Token " . $this->api_token
                        ]
                    ]
                );

                if (is_wp_error($request)) {
                    return false;
                }

                $body = wp_remote_retrieve_body($request);
                $json = json_decode($body, true);

                if (isset($json['list'])) {
                    $products  = array_merge($json['list']['results'], $products);
                    $pages = ((int)$json['list']['count'] / 20) + 1;
                }
                $page++;
            }

            file_put_contents($path, json_encode($products));
        }

        $products = json_decode(file_get_contents($path), true);

        if ($query) {
            $products = array_filter($products, function ($v, $k) use ($query) {
                return (strpos($v['logistical_description'], $query) !== FALSE);
            }, ARRAY_FILTER_USE_BOTH);
        }


        $result = [];

        foreach ($products as $key => $product) {
            $result[$key]['label'] = $product['logistical_description'];
            $result[$key]['value'] = $product['id'];
        }

        wp_send_json([
            'options' => array_values($result)
        ]);
    }

    /**
     * @return false|void
     */
    public function clear_products_cache() {

        $path = Functions::get_path('maystro_products_'.date('z').'.json');

        if(file_exists($path)) {
            unlink($path);
        }

        $pages = 1;
        $page = 1;

        $products = [];

        while ($page <= $pages) {

            $url = $this->provider['api_url'] . "/stock/products/?page=" . $page;

            $request = wp_safe_remote_get(
                $url,
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => "Token " . $this->api_token
                    ]
                ]
            );

            if (is_wp_error($request)) {
                return false;
            }

            $body = wp_remote_retrieve_body($request);
            $json = json_decode($body, true);

            if(isset($json['list'])) {
                $products  = array_merge($json['list']['results'], $products);
                $pages = ((int)$json['list']['count'] / 20) + 1;
            }



            $page++;
        }

        file_put_contents($path, json_encode($products));

        $result = [];

        foreach ($products as $key => $product) {
            $result[$key]['label'] = $product['logistical_description'];
            $result[$key]['value'] = $product['id'];
        }

        wp_send_json([
            'options' => array_values($result)
        ]);

    }

    /**
     *
     * Get Shipping classes
     * @since 1.6.5
     * @return void
     */
    public function import_shipping_class($flat_rate_label, $pickup_local_label,  $flat_rate_enabled, $pickup_local_enabled, $agency_import_enabled = null, $is_economic = null)
    {


        if($pickup_local_enabled) {
            if ($pickup_local_label) {
                if (get_option($this->provider['slug'] .'_pickup_local_label')) {
                    update_option($this->provider['slug'] .'_pickup_local_label', $pickup_local_label);
                } else {
                    add_option($this->provider['slug'] .'_pickup_local_label', $pickup_local_label);
                }
            }
        }


        if($flat_rate_enabled) {

            if ($flat_rate_label) {
                if (get_option($this->provider['slug'] .'_flat_rate_label')) {
                    update_option($this->provider['slug'] .'_flat_rate_label', $flat_rate_label);
                } else {
                    add_option($this->provider['slug'] .'_flat_rate_label', $flat_rate_label);
                }
            }
        }

        $url = $this->provider['api_url'] . "/base/wilayas/?country=1";

        $path = Functions::get_path($this->provider['slug'].'_fees.json');

        if (!file_exists($path)) {

            $request = wp_safe_remote_get(
                $url,
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => "Token " . $this->api_token
                    ]
                ]
            );

            if ( is_wp_error( $request ) ) {
                wp_send_json([
                    'error' => true,
                    'message'=>  "An error has been occurred"
                ], 401);
            }

            $body = wp_remote_retrieve_body( $request );

            $json = json_decode( $body, true );

            $communes = [];

            foreach ($json as $key => $item) {
                $communes[$key]['center_commune'] = $item['center_commune'];
                $communes[$key]['wilaya_id'] = $item['display_id'];
                $communes[$key]['wilaya'] = $item['name_lt'];
            }

            $result = [];

            if ($flat_rate_enabled) {

                foreach ($communes as $commune) {

                    $url = $this->provider['api_url']. '/base/delivery-prices/?'.http_build_query([
                            'commune' => $commune['center_commune'], 'delivery_type' => 1
                        ]);

                    $request = wp_safe_remote_get(
                        $url,
                        [
                            'headers' => [
                                'Content-Type' => 'application/json',
                                'Authorization' => "Token " . $this->api_token
                            ]
                        ]
                    );

                    $body = wp_remote_retrieve_body( $request );
                    $json = json_decode( $body, true );
                    $result[$commune['wilaya_id']]['name'] = $commune['wilaya'];
                    $result[$commune['wilaya_id']]['home'] = $json['delivery_price'];
                }
            }

            if ($pickup_local_enabled) {
                foreach ($communes as $commune) {

                    $url = $this->provider['api_url']. '/base/delivery-prices/?'.http_build_query([
                        'commune' => $commune['center_commune'], 'delivery_type' => 2
                    ]);

                    $request = wp_safe_remote_get(
                        $url,
                        [
                            'headers' => [
                                'Content-Type' => 'application/json',
                                'Authorization' => "Token " . $this->api_token
                            ]
                        ]
                    );

                    $body = wp_remote_retrieve_body( $request );

                    $json = json_decode( $body, true );

                    $result[$commune['wilaya_id']]['name'] = $commune['wilaya'];
                    $result[$commune['wilaya_id']]['desk'] = $json['delivery_price'];

                }
            }

            file_put_contents($path, json_encode($result));

        }


        $prices = json_decode(file_get_contents($path), true);

        if (get_option('shipping_zones_grouped') && get_option('shipping_zones_grouped') == 'yes') {
            $this->shipping_zone_grouped($prices, $flat_rate_label, $pickup_local_label,  $flat_rate_enabled, $pickup_local_enabled);
        } else {
            $this->shipping_zone_ungrouped($prices, $flat_rate_label, $pickup_local_label,  $flat_rate_enabled, $pickup_local_enabled);
        }


        wp_send_json_success(
            array(
                'message'   => 'success',
            )
        );
    }

    private function shipping_zone_grouped($prices, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled)
    {

    }

    private function shipping_zone_ungrouped($prices, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled)
    {

        // Set memory limit to -1 (unlimited) and set time limit to 0 (unlimited)
        ini_set('memory_limit', '-1');
		set_time_limit(0);

        $zones = [];

        $shipping_method_local_pickup = null;
        $shipping_method_flat_rate = null;

        $i = 1;
        foreach ($prices as $key => $wilaya) {

            $priceStopDesk = (int) $wilaya['desk'];
            $priceFlatRate = (int) $wilaya['home'];

            $wilaya_name = $wilaya['name'];


            // Get all shipping zones
            $zones = WC_Shipping_Zones::get_zones();

            // Iterate through the zones and find the one with the matching name
            $found_zone = null;
            foreach ($zones as $zone) {
                if ($zone['zone_locations'][0]->code == 'DZ:DZ-' . str_pad($key, 2, '0',STR_PAD_LEFT)) {
                    $found_zone = $zone;
                    break;
                }
            }

            if (!$found_zone) {
                $zone = new WC_Shipping_Zone();
                $zone->set_zone_name(ucfirst(Helpers::normalizeCityName($wilaya_name)));
                $zone->set_zone_order($key);
                $zone->add_location('DZ:DZ-' . str_pad($key, 2, '0', STR_PAD_LEFT), 'state');
                $zone->save();
            } else {
                $zone = WC_Shipping_Zones::get_zone($found_zone['id']);
            }

            if ($priceFlatRate && $flat_rate_enabled) {

                $flat_rate_name = 'flat_rate_maystro';
                if (is_hanout_enabled()) {
                    $flat_rate_name = 'flat_rate';
                }

                $instance_flat_rate = $zone->add_shipping_method($flat_rate_name);
                $shipping_method_flat_rate = WC_Shipping_Zones::get_shipping_method($instance_flat_rate);
                $shipping_method_configuration_flat_rate = [
                    'woocommerce_'.$flat_rate_name.'_title' => $flat_rate_label ?? __('Flat rate', 'woo-bordereau-generator'),
                    'woocommerce_'.$flat_rate_name.'_cost' => $priceFlatRate,
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

                $local_pickup_name = 'local_pickup_maystro';
                if (is_hanout_enabled()) {
                    $local_pickup_name = 'local_pickup';
                }

                $instance_local_pickup = $zone->add_shipping_method($local_pickup_name);
                $shipping_method_local_pickup = WC_Shipping_Zones::get_shipping_method($instance_local_pickup);
                $shipping_method_configuration_local_pickup = array(
                    'woocommerce_'.$local_pickup_name.'_title' => $pickup_local_label ?? __('Local Pickup', 'woo-bordereau-generator'),
                    'woocommerce_'.$local_pickup_name.'_cost' => $priceStopDesk,
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
    public function bulk_add_orders($order_ids) {


        $data = [];
        $orders = array();
        foreach ($order_ids as $id) {
            $order = wc_get_order($id);
            if ($order) {
                $orders[] = $order;
            }
        }

        $withConfirmation = get_option('maystro_delivery_confirmation') ? get_option('maystro_delivery_confirmation') : 'without-confirmation';
        $withConfirmation = $withConfirmation == 'with-confirmation';

        $wilayas = $this->get_wilayas();

        foreach ($orders as $order) {
            $params = $order->get_data();

            if($params['billing']['state']) {

                if(strpos($params['billing']['state'], 'DZ-') !== false) {

                    $wilayaId = (int) str_replace("DZ-", "", $params['billing']['state']);

                    $wilaya = $wilayas[$wilayaId - 1];

                    if ($wilaya === null) {
                        wp_send_json([
                            'error' => true,
                            'message' => __("Wilaya has not been selected", 'woo-bordereau-generator')
                        ], 422);
                    }
                } else {
                    $key = array_search($params['billing']['state'], array_column($wilayas, 1));
                    if($key != -1) {
                        $wilaya = $wilayas[$key];
                        $wilayaId = $wilaya[0];
                    }
                }

                $communeId = 0;

                if (isset($params['billing']['city'])) {

                    $commnes = $this->get_communes($wilayaId, false);

                    foreach ($commnes as $item) {

                        if ($item['name'] === $params['billing']['city']) {
                            $communeId = $item['id'];
                            break;
                        }
                    }

                }


                if($communeId !== 0) {
                    $products = [];
                    $i = 0;

                    // create the products
                    foreach ($order->get_items() as $item_key => $item) {
                        $url = $this->provider['api_url'] . "/stock/products/";

                        $item_data = $item->get_data();
                        $product_id = $item->get_product_id();
                        $product_variation_id = $item->get_variation_id();
                        $_id = $product_id;

                        if ($product_variation_id) {
                            $_id = $product_variation_id;
                        }

                        $id = get_post_meta($_id, 'maystro_delivery_product_id', true);

                        if (!$id) {

                            $data = [
                                'store_id' => $this->api_id,
                                'logistical_description' => $item_data['name'],
                                'product_id' => $product_id. ':'.$product_variation_id
                            ];


                            $request = wp_safe_remote_post(
                                $url,
                                [
                                    'headers' => [
                                        'Content-Type' => 'application/json',
                                        'Authorization' => "Token " . $this->api_token
                                    ],
                                    'body' => wp_json_encode($data),
                                    'method' => 'POST',
                                    'data_format' => 'body',
                                    'timeout' => 15
                                ]
                            );


                            $body = wp_remote_retrieve_body($request);
                            $response = json_decode($body, true);
                            if (isset($response['logistical_description'])) {
                                foreach ($response['logistical_description'] as $item) {
                                    if ($item == "A product with this logistical description already exists for this store.") {
                                        // duplicated
                                        // list all the products
                                    }
                                }
                            }

                            if (! is_wp_error($request)) {
                                $body = wp_remote_retrieve_body($request);
                                $response = json_decode($body, true);
                                if(! isset($response['status_code'])) {
                                    add_post_meta($_id, 'maystro_delivery_product_id', $response['id'], true);
                                    $id = $response['id'];
                                }
                            }
                        }



                        $products[$i]['product'] = $id ?: ($product_id . ':' . $product_variation_id);
                        $products[$i]['quantity'] = $item_data['quantity'];
                        $products[$i]['description'] = $item_data['name'];
                        if ($withConfirmation) {
                            $products[$i]['product_name'] = $item_data['name'];
                        }

                        $i++;
                    }

                    $stop_desk = 0;

                    $phone = $params['billing']['phone'];
                    $phone = str_replace(' ', '', str_replace('+213', '0', $phone));

                    if ($order->has_shipping_method('free_shipping')) {
						$free_shipping = 1;
					}

					if ($order->has_shipping_method('local_pickup')) {
						$stop_desk = 1;
					}
					
                    $data = [
                        "customer_name" =>  $params['billing']['last_name'] . ' ' . $params['billing']['first_name'],
                        "customer_phone" => $phone,
                        "destination_text" => $params['billing']['address_1'],
                        "commune" => $communeId,
                        "wilaya" => $wilayaId,
                        "delivery_type" => $stop_desk == 1 ? 2 : 1,
                        "external_id" => (string) $order->get_order_number(),
                        "details" => $products,
                        "total_price" => (int) $params['total'],
                        "note_to_driver" => "",
                        "express" => true,
                    ];

                    if ($withConfirmation) {
                        $data = [
                            "address" => $params['billing']['address_1'],
                            "phone" => $phone,
                            "first_name" => $params['billing']['last_name'],
                            "last_name" => $params['billing']['first_name'],
                            "total_price" => (int) $params['total'],
                            "wilaya_name" => $params['billing']['state'],
                            "commune_name" => $params['billing']['city'],
                            "line_items" => $products
                        ];
                    }

                    $url = $this->provider['api_url'] . "/orders";

                    $headers = [
                        'Content-Type' => 'application/json',
                        'Authorization' => "Token " . $this->api_token
                    ];

                    if ($withConfirmation) {
                        $url = "https://mdcall-api.maystro-delivery.com/api/confirmation/orders_2b_confirmed/webhook/";
                        $store_url = home_url();

                        // Remove http:// or https:// from the URL
                        $store_url_without_protocol = preg_replace('(https?://)', '', $store_url);
                        $headers = $headers + ['X-Custom-Webhook-Domain' => $store_url_without_protocol];
                    }

                    $request = wp_safe_remote_post(
                        $url,
                        [
                            'headers' => $headers,
                            'body' => wp_json_encode($data),
                            'method' => isset($this->data['is_update']) ? 'PUT' : 'POST',
                            'data_format' => 'body',
                            'timeout' => 15
                        ]
                    );

                    if (is_wp_error($request)) {
                        $error_message = $request->get_error_message(); // Retrieve the error message from WP_Error
                        wp_send_json([
                            'error' => true,
                            'message' => __("An error has occurred. Please see log file ", 'woo-bordereau-generator') . $error_message
                        ], 401);
                    }

                    $body = wp_remote_retrieve_body($request);

                    if (empty($body)) {
                        wp_send_json([
                            'error' => true,
                            'message' => __("An error has occurred. Please see log file ", 'woo-bordereau-generator')
                        ], 401);
                    }

                    $response = json_decode($body, true);

                    if (empty($response) || !is_array($response)) {
                        wp_send_json([
                            'error' => true,
                            'message' => __("An error has occurred. Please see log file ", 'woo-bordereau-generator')
                        ], 401);
                    }

                    // Handle old API error format (status_code)
                    if (isset($response['status_code']) && $response['status_code'] == 400) {
                        wp_send_json([
                            'error' => true,
                            'message' => $response[array_key_first($response)] ?? __("An error has occurred. Please see log file ", 'woo-bordereau-generator')
                        ], $response['status_code']);
                    }

                    // Handle old API error format (error key)
                    if (isset($response['error'])) {
                        $errorMsg = is_array($response['error']) ? ($response['error']['message'] ?? json_encode($response['error'])) : $response['error'];
                        wp_send_json([
                            'error' => true,
                            'message' => $errorMsg
                        ], 401);
                    }

                    // New API returns array format for create: [{id, tracking, success, errors, ...}]
                    $order_id = null;
                    $tracking_number = null;

                    if (isset($response[0])) {
                        $order_result = $response[0];

                        // Check for errors in new API format
                        if (!empty($order_result['errors'])) {
                            // Skip this order on error, continue to next
                            continue;
                        }

                        $order_id = $order_result['id'];
                        $tracking_number = $order_result['tracking'];
                    } elseif (isset($response['id'])) {
                        // Fallback for old API / confirmation flow (single object)
                        $order_id = $response['id'];
                        $tracking_number = $response['display_id'] ?? $response['tracking'] ?? $response['id'];
                    } else {
                        // Unknown response format, skip
                        continue;
                    }

                    $post_id = $order->get_id();

                    $etq = get_rest_url(null, 'woo-bordereau/v1/slip/print/'.$this->provider['slug'].'/'.$post_id);
                    $track = $this->provider['api_url'] . '/stores/history_order/' . $order_id;
                    update_post_meta($post_id, '_shipping_tracking_number', wc_clean($tracking_number));
                    update_post_meta($post_id, '_shipping_maystro_order_id', wc_clean($order_id));
                    update_post_meta($post_id, '_shipping_tracking_label', '');
                    update_post_meta($post_id, '_shipping_label', '');
                    update_post_meta($post_id, '_shipping_tracking_label_method', 'POST');
                    update_post_meta($post_id, '_shipping_tracking_url', $track);
                    update_post_meta($post_id, '_shipping_tracking_method', $this->provider['slug']);

                    $order = wc_get_order($post_id);

                    if($order) {
                        $order->update_meta_data('_shipping_tracking_number', wc_clean($tracking_number));
                        $order->update_meta_data('_shipping_maystro_order_id', wc_clean($order_id));
                        $order->update_meta_data('_shipping_tracking_label', '');
                        $order->update_meta_data('_shipping_label', '');
                        $order->update_meta_data('_shipping_tracking_label_method', 'POST');
                        $order->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
                        $order->save();

                        Helpers::make_note($order, $tracking_number, $this->provider['name']);

                    }
                }
            }
        }
    }

    private function get_wilayas()
    {
        $path = Functions::get_path('maystro_wilayas.json');

        if (!file_exists($path)) {
            $request = wp_safe_remote_get(
                $this->provider['api_url'] . '/base/wilayas/',
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Token ' . $this->api_token,
                    ],
                    'timeout' => 30,
                ]
            );

            if (!is_wp_error($request)) {
                $content = wp_remote_retrieve_body($request);
                file_put_contents($path, $content);
            }
        }

        $wilayas = json_decode(file_get_contents($path), true);

        return is_array($wilayas) ? $wilayas : [];
    }

    /**
     * @param $wilaya_id
     * @param bool $hasStopDesk
     *
     * @return array
     */
    public function get_communes($wilaya_id, bool $hasStopDesk)
    {

        $wilaya_id = (int) $wilaya_id;
        $path = Functions::get_path($wilaya_id . '_maystro_cities.json');

        if (!file_exists($path)) {
            $request = wp_safe_remote_get(
                $this->provider['api_url'] . '/base/communes/?wilaya=' . $wilaya_id,
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Token ' . $this->api_token,
                    ],
                    'timeout' => 30,
                ]
            );

            if (!is_wp_error($request)) {
                $content = wp_remote_retrieve_body($request);
                file_put_contents($path, $content);
            }
        }

        $communes = json_decode(file_get_contents($path), true);

        if ($communes == null) {
            $request = wp_safe_remote_get(
                $this->provider['api_url'] . '/base/communes/?wilaya=' . $wilaya_id,
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Token ' . $this->api_token,
                    ],
                    'timeout' => 30,
                ]
            );

            if (!is_wp_error($request)) {
                $content = wp_remote_retrieve_body($request);
                file_put_contents($path, $content);
                $communes = json_decode($content, true);
            }
        }

        $communesResult = [];

        if (is_array($communes)) {
            foreach ($communes as $i => $item) {
                $communesResult[$i]['id'] = $item['id'];
                $communesResult[$i]['name'] = $item['name'];
            }
        }

        return $communesResult;
    }

    public function get_status() {
        return [
            "Waiting Transit" => "Waiting Transit",
            "In Transit" => "In Transit",
            "Pending" => "Pending",
            "Out of Stock" => "Out of Stock",
            "Ready to ship" => "Ready to ship",
            "Assigned" => "Assigned",
            "Shipped" => "Shipped",
            "Alerted" => "Alerted",
            "Delivered" => "Delivered",
            "Postponed" => "Postponed",
            "Cancelled" => "Cancelled",
        ];
    }


}
