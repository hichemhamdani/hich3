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

use WC_Order;
use WC_Shipping_Zone;
use WC_Shipping_Zones;
use WooBordereauGenerator\Functions;
use WooBordereauGenerator\Helpers;
use WP_Error;

class EcoTrackShippingSettings
{
    /**
     * @var array
     */
    private $provider;
    private $api_token;

    /**
     * @var EcoTrackRequest
     */
    private $request;

    public function __construct(array $provider)
    {
        $this->provider = $provider;
        $this->api_token = get_option($provider['slug'] . '_api_token');

        // Initialize the request handler with rate limiting
        $this->provider['api_token'] = $this->api_token;
        $this->request = new EcoTrackRequest($this->provider);
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
                $this->provider['api_url'] . '/get/wilayas',
                [
                    'timeout' => 30,
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $api_token,
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

            // EcoTrack returns array directly like [{"wilaya_id":1,...}, ...] or {"data": [...]}
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
        $allResults = [];

        $status = $this->status();
        $page = 1;
        $url = $this->provider['api_url'] . '/get/orders?api_token=' . $this->api_token . '&trackings=' .implode(',' ,$codes). '&page=' . $page;

        // Make the API request using the rate-limited request handler
        $json = $this->request->get($url);

        // Check for rate limit error
        if (isset($json['error']) && $json['error'] === true) {
            // Log the error
            if (function_exists('\wc_get_logger')) {
                $logger = \wc_get_logger();
                $logger->error('EcoTrack API Error: ' . ($json['message'] ?? 'Unknown error'), array('source' => 'ecotrack-api'));
            }
        }

        if (isset($json['data']) && is_array($json['data'])) {
            foreach ($json['data'] as $item) {
                if (isset($item['tracking']) && isset($item['status'])) {
                    $allResults[$item['tracking']] = $status[$item['status']] ?? $item['status'];
                }
            }
        }


        foreach (range(2, $json['last_page']) as $page) {
            // Create the URL for this chunk
            $url = $this->provider['api_url'] . '/get/orders?api_token=' . $this->api_token . '&trackings=' .implode(',' ,$codes). '&page=' . $page;

            // Make the API request using the rate-limited request handler
            $page_json = $this->request->get($url);

            // Check for rate limit error
            if (isset($page_json['error']) && $page_json['error'] === true) {
                // Log the error
                if (function_exists('\wc_get_logger')) {
                    $logger = \wc_get_logger();
                    $logger->error('EcoTrack API Error: ' . ($page_json['message'] ?? 'Unknown error'), array('source' => 'ecotrack-api'));
                }
                continue; // Skip this chunk if there's an error
            }

            $json = $page_json;

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
        return $this->get_status();
    }

    /**
     * Get all available EcoTrack statuses for conditions mapping
     *
     * @return array
     */
    public function get_status()
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
            "en_ramassage" => "En ramassage",
            "en_preparation" => "En préparation",
            "vers_hub" => "Vers hub",
            "suspendu" => "Suspendu",
            "retour_chez_livreur" => "Retour chez livreur",
            "payé_et_archivé" => "Payé et archivé",
            "encaissé_non_payé" => "Encaissé non payé",
            "retour_reçu" => "Retour reçu",
            "annule" => "Annulé",
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
        $url = $this->provider['api_url'] . "/get/fees";

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

        // Make the API request using the rate-limited request handler
        $json = $this->request->get($url);

        // Check for rate limit error
        if (isset($json['error']) && $json['error'] === true) {
            wp_send_json([
                'error' => true,
                'message' => $json['message'] ?? "An error has been occurred"
            ], 429); // Use 429 status code for rate limit errors
        }

        if (get_option('shipping_zones_grouped') && get_option('shipping_zones_grouped') == 'yes') {
            $this->shipping_zone_grouped($json['livraison'], $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled);
        } else {
            $this->shipping_zone_ungrouped($json['livraison'], $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled, $agency_import_enabled);
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

    private function shipping_zone_ungrouped($wilayas,
	    $flat_rate_label,
	    $pickup_local_label,
	    $flat_rate_enabled,
	    $pickup_local_enabled,
	    $centers_import_enabled = null
    )
    {

        // Set memory limit to -1 (unlimited) and set time limit to 0 (unlimited)
        ini_set('memory_limit', '-1');
		set_time_limit(0);

        $zones = [];
        $wilayasData = $this->get_wilayas();

        $i = 1;
        foreach ($wilayas as $key => $wilaya) {
			

            try {
                $searchWilayaId = $wilaya['wilaya_id'];
	            $wilayaCode = 'DZ-' . str_pad($wilaya['wilaya_id'], 2, '0',STR_PAD_LEFT);

                $priceStopDesk = (int) $wilaya['tarif_stopdesk'] ?? null;
                $priceFlatRate = (int) $wilaya['tarif'] ?? null;

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


                if ($priceFlatRate && $flat_rate_enabled) {

                    $flat_rate_name = 'flat_rate_ecotrack';
                    if (is_hanout_enabled()) {
                        $flat_rate_name = 'flat_rate';
                    }

                    $instance_flat_rate = $zone->add_shipping_method($flat_rate_name);
                    $shipping_method_flat_rate = WC_Shipping_Zones::get_shipping_method($instance_flat_rate);

                    $shipping_method_configuration_flat_rate = [
                        'woocommerce_'.$flat_rate_name.'_title'                 => $flat_rate_label ?? __('Flat rate', 'wc-bordereau-generator'),
                        'woocommerce_'.$flat_rate_name.'_cost'                  => $priceFlatRate,
                        'woocommerce_'.$flat_rate_name.'_type'                  => 'class',
                        'woocommerce_'.$flat_rate_name.'_provider'              => $this->provider['slug'],
                        'instance_id' => $instance_flat_rate,
                    ];

                    $_REQUEST['instance_id'] = $instance_flat_rate;
                    $shipping_method_flat_rate->set_post_data($shipping_method_configuration_flat_rate);
                    $shipping_method_flat_rate->process_admin_options();
                }

	            if ( $priceStopDesk && $pickup_local_enabled && $priceStopDesk > 0 ) {



		            if ( $centers_import_enabled ) {
			            // Import individual centers
			            $centers = $this->get_communes( $wilayaCode, true );

			            foreach ( $centers as $center ) {

				            $local_pickup_name = 'local_pickup_ecotrack';

				            if ( function_exists( 'is_hanout_enabled' ) && is_hanout_enabled() ) {
					            $local_pickup_name = 'local_pickup';
				            }

				            $center_name = sprintf( "%s %s", $pickup_local_label ?? __( 'Local Pickup', 'woo-bordereau-generator' ), $center['name'] );

				            $instance_local_pickup = $zone->add_shipping_method( $local_pickup_name );

				            if ( $instance_local_pickup ) {
					            $shipping_method_local_pickup = \WC_Shipping_Zones::get_shipping_method( $instance_local_pickup );

					            if ( $shipping_method_local_pickup ) {
						            $shipping_method_configuration_local_pickup = [
							            'woocommerce_' . $local_pickup_name . '_title'     => $center_name,
							            'woocommerce_' . $local_pickup_name . '_cost'      => $priceStopDesk,
							            'woocommerce_' . $local_pickup_name . '_type'      => 'class',
							            'woocommerce_' . $local_pickup_name . '_address'   => $center['address'] ?? '',
							            'woocommerce_' . $local_pickup_name . '_center_id' => $center['id'],
							            'woocommerce_' . $local_pickup_name . '_provider'  => $this->provider['slug'],
							            'instance_id'                                      => $instance_local_pickup,
						            ];

						            $_REQUEST['instance_id'] = $instance_local_pickup;
						            $shipping_method_local_pickup->set_post_data( $shipping_method_configuration_local_pickup );
						            $shipping_method_local_pickup->update_option( 'provider', $this->provider['slug'] );
						            $shipping_method_local_pickup->process_admin_options();
					            }
				            }
			            }
		            } else {
			            $local_pickup_name = 'local_pickup_ecotrack';
			            if (is_hanout_enabled()) {
				            $local_pickup_name = 'local_pickup';
			            }

			            $instance_local_pickup = $zone->add_shipping_method($local_pickup_name);
			            $shipping_method_local_pickup = WC_Shipping_Zones::get_shipping_method($instance_local_pickup);
			            $shipping_method_configuration_local_pickup = array(
				            'woocommerce_'.$local_pickup_name.'_title'               => $pickup_local_label ?? __('Local Pickup', 'wc-bordereau-generator'),
				            'woocommerce_'.$local_pickup_name.'_cost'                => $priceStopDesk,
				            'woocommerce_'.$local_pickup_name.'_type'                => 'class',
				            'woocommerce_'.$local_pickup_name.'_provider'            => $this->provider['slug'],
			            );

			            $_REQUEST['instance_id'] = $instance_local_pickup;
			            $shipping_method_local_pickup->set_post_data($shipping_method_configuration_local_pickup);
			            $shipping_method_local_pickup->process_admin_options();
		            }
	            }

            } catch (\ErrorException $exception) {
                wp_send_json([
                    'error' => true,
                    'message' => $exception->getMessage()
                ], 401);
            }
        }
    }


    /**
     * @param $order_ids
     * @return mixed|void
     */
    public function bulk_add_orders($order_ids)
    {
        $orders = array();
        foreach ($order_ids as $id) {
            $order = wc_get_order($id);
            if ($order) {
                $orders[] = $order;
            }
        }

        $wilayas = $this->get_wilayas();

        // Map reference (order number) => WC_Order for later meta updates
        $order_map = [];
        // Indexed payload array for the batch endpoint
        $batch_payloads = [];

        foreach ($orders as $order) {

            $params = $order->get_data();
            $orderId = $order->get_order_number();
            $wilaya = null;

            $wilayaCode = $params['billing']['state'];
            $wilaya = Helpers::get_wilaya($wilayaCode, $wilayas);

            if (! $wilaya) {
                foreach ($wilayas as $key => $item) {
                    if (Helpers::areSimilar(Helpers::slugify($item['wilaya_name']), Helpers::slugify($wilayaCode))) {
                        $wilaya = $item;
                    }
                }
            }

            if (! $wilaya) {
                foreach ($wilayas as $key => $item) {
                    if (Helpers::areSimilar(Helpers::slugify($item['wilaya_name']), Helpers::slugify($wilayaCode))) {
                        $wilaya = $item;
                    }
                }
            }

            if ($wilaya === null) {
                continue;
            }

            $products = $order->get_items();
            $productString = Helpers::generate_products_string($products);
            $productString = mb_strimwidth($productString, 0, 252, "..."); // issue of 255 char
            $phone2 = '';

            $phone = $params['billing']['phone'];
            $phone = str_replace(' ', '', str_replace('+213', '0', $phone));
            $phones = explode('/', $phone);

            if (count($phones) > 1) {
                $phone2 = $phones[1];
                $phone = $phones[0];
            }

            if (empty($phone2)) {
                $phone2 = $order->get_meta('_billing_phone_2') ?? null;
            }

            $free_shipping = 0;
            $stop_desk = 0;

            if ($order->has_shipping_method('free_shipping')) {
                $free_shipping = 1;
            }

            if ($order->has_shipping_method('local_pickup') || $order->has_shipping_method('local_pickup_ecotrack')) {
                $stop_desk = 1;
            }

            $fname = !empty($params['billing']['first_name']) ? $params['billing']['first_name'] : '';
            $lname = !empty($params['billing']['last_name']) ? $params['billing']['last_name'] : '';

            $full_name = trim($fname . ' ' . $lname);

            // If both are empty, use whichever is available
            if (empty($full_name)) {
                $full_name = !empty($params['billing']['first_name']) ? $params['billing']['first_name'] : $params['billing']['last_name'];
            }

            if (is_hanout_enabled()) {
                $full_name = $fname;
            }

            // Resolve commune for the order
            $commune_name = str_replace('\\', '', $params['billing']['city']);

            // For stop-desk orders, try to match commune from shipping method name
            if ($stop_desk) {
                $matched_commune = null;

                // Step 1: Try to get commune from order meta (selected at checkout)
                $center_id = $order->get_meta('_billing_center_id', true);
                if (!$center_id) {
                    $center_id = get_post_meta($order->get_id(), '_billing_center_id', true);
                }

                if ($center_id) {
                    $matched_commune = $center_id;
                }

                // Step 2: Match shipping method name against communes list
                if (!$matched_commune) {
                    $shipping_method_name = $order->get_shipping_method();
                    $wilayaCode = 'DZ-' . str_pad($wilaya['wilaya_id'], 2, '0', STR_PAD_LEFT);
                    $stop_desk_communes = $this->get_communes($wilayaCode, true);

                    if (is_array($stop_desk_communes)) {
                        $prefix = get_option($this->provider['slug'] . '_pickup_local_label');
                        $result = array_filter($stop_desk_communes, function ($c) use ($shipping_method_name, $prefix) {
                            $expected_name = sprintf("%s %s", $prefix, $c['name']);
                            return trim($expected_name) === trim($shipping_method_name);
                        });
                        $found = !empty($result) ? reset($result) : null;

                        if ($found) {
                            $matched_commune = $found['name'];
                        }
                    }
                }

                // Step 3: Fallback - find first commune with stop-desk in this wilaya
                if (!$matched_commune) {
                    $wilayaCode = 'DZ-' . str_pad($wilaya['wilaya_id'], 2, '0', STR_PAD_LEFT);
                    $stop_desk_communes = $this->get_communes($wilayaCode, true);

                    if (is_array($stop_desk_communes) && count($stop_desk_communes) > 0) {
                        // Try to match by billing city first
                        $result = array_filter($stop_desk_communes, function ($c) use ($commune_name) {
                            return Helpers::slugify($c['name']) === Helpers::slugify($commune_name);
                        });
                        $found = !empty($result) ? reset($result) : null;

                        // If no match by billing city, pick the first available
                        if (!$found) {
                            $found = reset($stop_desk_communes);
                        }

                        if ($found) {
                            $matched_commune = $found['name'];
                        }
                    }
                }

                if ($matched_commune) {
                    $commune_name = $matched_commune;
                }
            }

            $order_data = [
                "reference" => (string) $orderId,
                "nom_client" => $full_name,
                "telephone" => $phone,
                "telephone_2" => $phone2 ?? '',
                "adresse" => !empty($params['billing']['address_1']) ? $params['billing']['address_1'] : $params['billing']['city'],
                "commune" => $commune_name,
                "code_postal" => '',
                "code_wilaya" => (string) $wilaya['wilaya_id'],
                "produit" => $productString,
                "montant" => (string) $params['total'],
                "boutique" => get_the_title(get_option('woocommerce_shop_page_id')),
                "remarque" => "",
                "produit_a_recuperer" => "",
                "stop_desk" => $stop_desk, // [ 0 = a domicile , 1 = STOP DESK ]
                "type" => "1", //  [ 1 = Livraison , 2 = Echange , 3 = PICKUP , 4 = Recouvrement ]
            ];

            // Calculate and add weight if surpoid is enabled
            $overweightEnabled = get_option('recalculate_' . $this->provider['slug'] . '_overweight', 'recalculate-without-overweight');

            if ($overweightEnabled === 'recalculate-with-overweight') {
                $total_weight = $this->calculateOrderWeight($order);
                if ($total_weight > 0) {
                    $order_data['weight'] = (string) $total_weight;
                }
            }

            if (get_option($this->provider['slug'] . '_avec_stock') === 'with-stock') {

                $order_data['stock'] = 1;
                $qty = [];
                $line_items = $params['line_items'];
                foreach ($line_items as $k => $p) {
                    $qty[$k] = $p['quantity'];
                }

                $order_data['quantite'] = implode(",", $qty);

                $skus = [];
                foreach ($line_items as $k => $p) {
                    $product = wc_get_product($p->get_product_id());
                    $item_sku = $product->get_sku();
                    $skus[$k] = $item_sku;
                }

                $order_data['produit'] = implode(",", $skus);
            }

            $order_map[(string) $orderId] = $order;
            $batch_payloads[] = $order_data;
        }

        if (empty($batch_payloads)) {
            return;
        }

        // Send in batches of 100 (API limit)
        $chunks = array_chunk($batch_payloads, 100);
        $url = $this->provider['api_url'] . "/create/orders";

        foreach ($chunks as $chunk) {
            // Build the numbered-object format required by the API: {"orders": {"0": {...}, "1": {...}}}
            $orders_payload = [];
            foreach (array_values($chunk) as $index => $order_data) {
                $orders_payload[(string) $index] = $order_data;
            }

            $response = $this->request->post($url, ['orders' => $orders_payload]);

            // Check for rate limit / transport error
            if (isset($response['error']) && $response['error'] === true) {
                $error_message = $response['message'] ?? "Rate limit exceeded. Please try again later.";
                set_transient('order_bulk_add_error', $error_message, 45);
                $logger = wc_get_logger();
                $logger->error('Bulk Upload Error (Rate Limit): ' . $error_message, array('source' => WC_BORDEREAU_POST_TYPE));
                continue; // Skip this chunk
            }

            // Process individual results from the batch response
            // Response format: {"results": {"DEMO852": {"telephone": [...errors]}, "DEMO853": {"success": true, "tracking": "ECT..."}}}
            if (isset($response['results']) && is_array($response['results'])) {
                foreach ($response['results'] as $reference => $result) {

                    if (isset($result['success']) && $result['success'] === true && isset($result['tracking'])) {
                        // Success: store tracking meta
                        $order = $order_map[(string) $reference] ?? null;
                        if (! $order) {
                            continue;
                        }

                        $tracking = $result['tracking'];
                        $etqNew = $this->provider['api_url'] . '/get/order/label/?api_token=' . $this->api_token . '&tracking=' . $tracking;
                        $track = 'https://suivi.ecotrack.dz/suivi/' . $tracking;

                        update_post_meta($order->get_id(), '_shipping_tracking_number', wc_clean($tracking));
                        update_post_meta($order->get_id(), '_shipping_tracking_label', $etqNew);
                        update_post_meta($order->get_id(), '_shipping_label', $etqNew);
                        update_post_meta($order->get_id(), '_shipping_tracking_url', $track);
                        update_post_meta($order->get_id(), '_shipping_tracking_method', $this->provider['slug']);

                        $order->update_meta_data('_shipping_tracking_number', wc_clean($tracking));
                        $order->update_meta_data('_shipping_tracking_label', $etqNew);
                        $order->update_meta_data('_shipping_label', $etqNew);
                        $order->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
                        $order->save();

                        Helpers::make_note($order, $tracking, $this->provider['name']);

                    } else {
                        // Validation errors for this specific order
                        $logger = wc_get_logger();
                        $error_parts = [];
                        foreach ($result as $field => $messages) {
                            if (is_array($messages)) {
                                $error_parts[] = $field . ': ' . implode(', ', $messages);
                            }
                        }
                        $error_message = sprintf('[%s] %s', $reference, implode(' | ', $error_parts));
                        set_transient('order_bulk_add_error', $error_message, 45);
                        $logger->error('Bulk Upload Error: ' . $error_message, array('source' => WC_BORDEREAU_POST_TYPE));
                    }
                }
            }
        }
    }

    private function get_wilayas()
    {
        $path = Functions::get_path('ecotrack_' . $this->provider['slug'] . '_wilaya.json');

        if (file_exists($path)) {
            $wilayas = json_decode(file_get_contents($path), true);

            if ($wilayas == null) {
                return $this->get_wilaya_from_provider();
            }

        } else {
            $response = $this->get_wilaya_from_provider();

            if (empty($response)) {
                $response = file_get_contents('https://amineware.me/api/wilaya-noest');
                $result = [];
                $response_array = json_decode($response, true);
                foreach ($response_array as $key => $value) {
                    // {"wilaya_id":1,"wilaya_name":"Adrar"}
                    $result[$key]['wilaya_id'] = $value['id'];
                    $result[$key]['wilaya_name'] = $value['name'];
                }

                file_put_contents($path, json_encode($result));
            } else {
                file_put_contents($path, $response);
            }

            if (is_array($response)) {
                return $response;
            }

            return json_decode($response, true);
        }

        return $wilayas;
    }

    /**
     * @param \WC_Order $order
     * @return void
     */
    public function mark_as_dispatched(\WC_Order $order)
    {
        $tracking_number = $order->get_meta('_shipping_tracking_number');

        if ($tracking_number && $this->api_token) {
            $url = $this->provider['api_url'] . '/api/v1/valid/order?tracking=' . $tracking_number;
            $this->request->post($url);
        }
    }

    /**
     * Get orders from the API
     *
     * @param array $queryData
     * @return array
     */
    public function get_orders($queryData)
    {
        $mappingQueryData = [];
        $mappingQueryData['per_page'] = $queryData['page_size'] ?? 10;
        $mappingQueryData['page'] = $queryData['page'] ?? 1;

        $url = $this->provider['api_url'] . '/get/orders?' . \http_build_query($mappingQueryData);
        $json = $this->request->get($url);

        // Check for rate limit error
        if (isset($json['error']) && $json['error'] === true) {
            wp_send_json([
                'error' => true,
                'message' => $json['message'] ?? "Rate limit exceeded. Please try again later."
            ], 429); // Use 429 status code for rate limit errors
        }

        return [
            'items' => $this->mapping_order_results($json['data']),
            'total_data' => $json['total'],
            'current_page' => $json['current_page'],
        ];
    }

    private function mapping_order_results($data)
    {
        return array_map(function ($item) {
            return [
                'tracking' => $item['tracking'],
                'familyname' => $item['client'],
                'firstname' => '',
                'to_commune_name' => $this->get_to_commune_name($item['commune_id'], $item['wilaya_id'], $item['stop_desk']),
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

    private function get_to_commune_name($commune_id)
    {
        $communes = $this->get_communes_from_provider();
        return $communes[$commune_id]['nom'] ?? $commune_id;
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

    /**
     * Get wilaya data from provider API
     *
     * @return array|null
     */
    private function get_wilaya_from_provider()
    {
        if ($this->api_token) {
            $url = $this->provider['api_url'] . '/get/wilayas';
            $json = $this->request->get($url);

            // Check for rate limit error
            if (isset($json['error']) && $json['error'] === true) {
                wp_send_json([
                    'error' => true,
                    'message' => $json['message'] ?? __("Rate limit exceeded. Please try again later.", 'wc-bordereau-generator')
                ], 429); // Use 429 status code for rate limit errors
            }

            return $json;
        }

        return null;
    }

    private function get_communes_from_provider()
    {
        $path = Functions::get_path(sprintf("%s_communes", $this->provider['slug']));

        if (!file_exists($path)) {

            $url = $this->provider['api_url'] . '/get/communes';
            $json = $this->request->get($url);

            // Check for rate limit error
            if (isset($json['error']) && $json['error'] === true) {
                wp_send_json([
                    'error' => true,
                    'message' => $json['message'] ?? __("Rate limit exceeded. Please try again later.", 'wc-bordereau-generator')
                ], 429); // Use 429 status code for rate limit errors
            }

            // Convert JSON to string for file storage
            $body = json_encode($json);

            file_put_contents($path, $body);

        }

        return json_decode(file_get_contents($path), true);
    }

    /**
	 * @param int $wilaya_id
	 * @param bool $hasStopDesk
	 *
	 * @return array
	 */
	public function get_communes($wilaya_id, bool $hasStopDesk): array
	{
		$path = Functions::get_path($this->provider['slug'].'_communes_v2.json');

        $wilaya_id = str_replace('DZ-', '', $wilaya_id);



		if (! file_exists($path)) {
			// Use the EcoTrackRequest class for API requests
			$url = $this->provider['api_url'] . '/get/communes';
			$response = $this->request->get($url);

			// Check for rate limit error
			if (isset($response['error']) && $response['error'] === true) {
				return [];
            }

			// Convert response to JSON string for file storage
			$body = json_encode($response);
			file_put_contents($path, $body);
		}

		$communes = json_decode(file_get_contents($path), true);

		$communesResult = [];

		$found = array_filter($communes, function ($v, $k) use ($wilaya_id) {
			return (int) $v['wilaya_id'] == $wilaya_id;
		}, ARRAY_FILTER_USE_BOTH);



		foreach ($found as $i => $item) {
			if($hasStopDesk) {
				if($item['has_stop_desk'] == 1) {
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

	/**
	 * Calculate the total weight of an order from its products
	 * @param \WC_Order $order
	 * @return float Total weight in KG
	 * @since 4.8.0
	 */
	private function calculateOrderWeight(\WC_Order $order): float
	{
		$total_weight = 0;

		foreach ($order->get_items() as $item) {
			$product = $item->get_product();
			if ($product && $product->get_weight()) {
				$total_weight += (float) $product->get_weight() * (int) $item->get_quantity();
			}
		}

		return $total_weight;
	}
}
