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

use DOMDocument;
use DOMXPath;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Request;
use HTTP_Request2;
use HTTP_Request2_Exception;
use WC_Shipping_Zone;
use WC_Shipping_Zones;
use WooBordereauGenerator\Functions;
use WooBordereauGenerator\Helpers;
use WP_Error;

class NoestShippingSettings
{

    /**
     * @var array
     */
    private $provider;
    private $api_token;
    private $user_guid;
    
    /**
     * @var NoestRequest
     */
    private $request;

    public function __construct(array $provider)
    {
        $this->provider = $provider;
        $this->user_guid = get_option(sprintf('%s_express_user_guid', $provider['slug']));
        $this->api_token = get_option(sprintf('%s_express_api_token', $provider['slug']));
        $this->request = new NoestRequest($provider);
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
     * Verify API credentials by attempting to fetch wilayas
     *
     * @param array $credentials Temporary credentials to test (keys match field 'value' from provider config)
     * @return array ['success' => bool, 'message' => string]
     */
    public function check_auth(array $credentials = []): array
    {
        try {
            // Get credentials from passed array or fall back to saved options
            $api_token = $credentials[$this->provider['slug'] . '_express_api_token'] ?? $this->api_token;
            $user_guid = $credentials[$this->provider['slug'] . '_express_user_guid'] ?? $this->user_guid;

            if (empty($api_token) || empty($user_guid)) {
                return [
                    'success' => false,
                    'message' => __('API Token and User GUID are required', 'woo-bordereau-generator')
                ];
            }

            // Make a test request to wilayas endpoint
            $url = $this->provider['api_url'] . '/get/wilayas?api_token=' . $api_token . '&user_guid=' . $user_guid;
            
            $response = wp_safe_remote_get($url, [
                'timeout' => 30,
                'headers' => [
                    'Content-Type' => 'application/json'
                ],
            ]);

            if (is_wp_error($response)) {
                return [
                    'success' => false,
                    'message' => __('Connection error: ', 'woo-bordereau-generator') . $response->get_error_message()
                ];
            }

            $httpCode = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            // Noest returns array directly
            if ($httpCode === 200 && is_array($data) && !isset($data['error'])) {
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
        $url = $this->provider['api_url'] . "/get/trackings/info";

        $data = [
            'trackings' => $codes,
            'api_token' => $this->api_token,
            'user_guid' => $this->user_guid
        ];

        $response = $this->request->post($url, $data);

        return $this->format_tracking($response);

    }

    /**
     * Bulk add orders using the Noest bulk API endpoint
     * @param $order_ids
     * @return array Results with passed and failed orders
     */
    public function bulk_add_orders($order_ids)
    {
        $wcOrders = [];
        $ordersPayload = [];
        $orderIndexMap = []; // Maps API index to WC order

        foreach ($order_ids as $id) {
            $order = wc_get_order($id);
            if ($order) {
                $wcOrders[] = $order;
            }
        }

        if (empty($wcOrders)) {
            return;
        }

        $fields = $this->provider['fields'];
        $key = $fields['total']['choices'][0]['name'] ?? null;
        $overweight_checkout = $fields['overweight_checkout']['choices'][0]['name'] ?? null;
        $overweight = $fields['overweight']['choices'][0]['name'] ?? null;
        $canOpen = $fields['can_open']['choices'][0]['name'] ?? null;

        $overweightCase = get_option($overweight) ? get_option($overweight) : 'recalculate-without-overweight';
        $overweightCheckoutCase = get_option($overweight_checkout) ? get_option($overweight_checkout) : 'recalculate-without-overweight';
        $canOpenCase = get_option($canOpen) ? get_option($canOpen) : 'false';

        $total = get_option($key) ? get_option($key) : 'without-shipping';

        $wilayas = $this->get_wilayas();

        if (!is_array($wilayas)) {
            set_transient('order_bulk_add_error', __('Unable to fetch wilayas endpoint please clear the cache', 'woo-bordereau-generator'), 45);
            $logger = \wc_get_logger();
            $logger->error('Bulk Upload Error: Unable to fetch wilayas endpoint please clear the cache', array('source' => WC_BORDEREAU_POST_TYPE));
            return;
        }

        $index = 0;
        foreach ($wcOrders as $order) {
            $shipping = new NoestShipping($order, $this->provider);
            $params = $order->get_data();
            $orderId = $order->get_order_number();

            $wilayaId = (int)str_replace("DZ-", "", $params['billing']['state']);
            $wilaya = array_search($wilayaId, array_column($wilayas, 'wilaya_id'));

            if ($wilaya === null) {
                $logger = \wc_get_logger();
                $logger->error('Bulk Upload Error: Wilaya not found for order ' . $orderId, array('source' => WC_BORDEREAU_POST_TYPE));
                continue;
            }

            $total_weight = 0;
            $products = $params['line_items'];
            $productString = Helpers::generate_products_string($products);
            $productString = mb_strimwidth($productString, 0, 252, "...");

            $phone2 = '';
            $phone = $params['billing']['phone'];
            $phone = str_replace(' ', '', str_replace('+213', '0', $phone));
            $phones = explode('/', $phone);

            if (count($phones) > 1) {
                $phone2 = $phones[1];
                $phone = $phones[0];
            }

            $stop_desk = 0;
            $cart_value = 0;

            if ($total == 'with-shipping') {
                $cart_value = (float) $order->get_total();
            } elseif ($total === 'without-shipping') {
                $cart_value = (float) ($order->get_total() - floatval($order->get_shipping_total()));
            }

            if ($order->has_shipping_method('local_pickup') || $order->has_shipping_method('local_pickup_noest')) {
                $stop_desk = 1;
            }

            $address = $params['billing']['address_1'];
            if (!$address) {
                $address = $params['billing']['city'];
            }

            $note = stripslashes(get_option('wc_bordreau_default-shipping-note'));
            $can_open = $canOpenCase == 'true';

            if ($overweightCase == 'recalculate-with-overweight') {
                $total_weight = Helpers::get_product_weight($order);

                if ($overweightCheckoutCase == 'recalculate-with-overweight') {
                    $fees = $order->get_fees();
                    $found = false;

                    foreach ($fees as $item_id => $item_fee) {
                        if ($item_fee->get_name() == __("Surpoids (+5KG)", 'woo-bordereau-generator')) {
                            $found = true;
                            if ($total != 'with-shipping') {
                                $cart_value = $cart_value - $item_fee->get_total();
                            }
                            break;
                        }
                    }

                    if (!$found && $total_weight > 5) {
                        $zonePrice = 40;
                        $shippingOverweightPrice = max(0, ceil($total_weight - 5)) * $zonePrice;
                        $cart_value = $cart_value + $shippingOverweightPrice;
                    }
                } else {
                    if ($total_weight > 5) {
                        $zonePrice = 40;
                        $shippingOverweightPrice = max(0, ceil($total_weight - 5)) * $zonePrice;
                        $cart_value = $cart_value + $shippingOverweightPrice;
                    }
                }
            }

            $fname = $params['billing']['first_name'];
            $lname = $params['billing']['last_name'];
            $full_name = $fname . ' ' . $lname;

            if (is_hanout_enabled()) {
                $full_name = $fname;
            }

            $commune = str_replace('\\', '', $params['billing']['city']);

            if ($total_weight == 0) {
                $total_weight = 1;
            }

	        $orderNumber = $orderId;
	        $orderNumber = str_pad($orderNumber, 6, "0");

            // Build order data for bulk API (without api_token - it's in header now)
            $orderData = [
                "reference" => $orderNumber,
                "client" => $full_name,
                "phone" => $phone,
                "phone_2" => $phone2,
                "adresse" => $address,
                "commune" => $commune,
                "wilaya_id" => $wilayaId,
                "produit" => $productString,
                "montant" => $cart_value,
                "remarque" => $note,
                "poids" => $total_weight,
                "can_open" => $can_open ? 1 : 0,
                "stop_desk" => $stop_desk,
                "type_id" => 1,
            ];

            // Handle stop_desk station_code
            if ($stop_desk) {
                $selected_value = get_post_meta($order->get_id(), '_billing_center_id', true);

                if ($selected_value) {
                    $orderData['station_code'] = $selected_value;
                } else {
                    $station_code = $this->resolveStationCode($order, $shipping, $wilayaId, $commune, $stop_desk);
                    if ($station_code) {
                        $orderData['station_code'] = $station_code;
                    }
                }
            }

            // Handle stock option
            if (get_option('nord_ouest_avec_stock') === 'with-stock') {
                $orderData['stock'] = 1;
                $qty = [];
                foreach ($products as $k => $p) {
                    $qty[$k] = $p['quantity'];
                }
                $orderData['quantite'] = implode(",", $qty);
            }

            $ordersPayload[] = $orderData;
            $orderIndexMap[$index] = $order;
            $index++;
        }

        if (empty($ordersPayload)) {
            return;
        }

        // Use bulk API endpoint: POST /api/public/create/orders
        $url = $this->provider['api_url'] . "/create/orders";

        // Build bulk request payload
        $bulkPayload = [
            "user_guid" => $this->user_guid,
            "orders" => $ordersPayload
        ];

        $response = $this->request->post($url, $bulkPayload, NoestRequest::POST, true);

        // Handle bulk API response format
        $this->processBulkResponse($response, $orderIndexMap);

        return $response;
    }

    /**
     * Resolve station code for stop desk orders
     * @param \WC_Order $order
     * @param NoestShipping $shipping
     * @param int $wilayaId
     * @param string $commune
     * @param int $stop_desk
     * @return string|null
     */
    private function resolveStationCode($order, $shipping, $wilayaId, $commune, $stop_desk)
    {
        $name = $order->get_shipping_method();
        $centers = $this->get_all_centers();

        if (is_array($centers)) {
            $result = array_filter($centers, function($agency) use ($name) {
                $prefix = get_option($this->provider['slug'] . '_pickup_local_label');
                $agencyName = sprintf("%s %s", $prefix, $agency['name']);
                return trim($agencyName) === trim($name);
            });
            $found = !empty($result) ? reset($result) : null;

            if ($found) {
                return $found['id'];
            }
        }

        $first_center = get_option('nord_ouest_first_center');
        if ($first_center == 'yes') {
            $firstCenter = $this->get_first_center($order);
            if ($firstCenter) {
                return $firstCenter['id'];
            }
        } else {
            $centers = $this->get_centers_for_order($order);
            if (is_array($centers) && count($centers)) {
                $center = reset($centers);
                if (count($centers) == 1) {
                    return $center['id'];
                } else {
                    $results = Helpers::searchByName($centers, $commune, 'name');
                    if (count($results)) {
                        $center = reset($results);
                        return $center['id'];
                    }
                    return $center['id'];
                }
            }
        }

        return null;
    }

    /**
     * Process bulk API response and update orders
     * @param array $response
     * @param array $orderIndexMap
     */
    private function processBulkResponse($response, $orderIndexMap)
    {
        if (!is_array($response)) {
            set_transient('order_bulk_add_error', __('Invalid API response', 'woo-bordereau-generator'), 45);
            return;
        }

        // Handle passed orders
        if (isset($response['passed']) && is_array($response['passed'])) {
            foreach ($response['passed'] as $index => $result) {
                if (isset($orderIndexMap[$index]) && isset($result['tracking'])) {
                    $order = $orderIndexMap[$index];
                    $tracking = wc_clean($result['tracking']);

                    $track = 'https://suivi.ecotrack.dz/suivi/' . $tracking;
                    $etq = 'https://app.noest-dz.com/api/public/get/order/label?api_token=' . $this->api_token . '&tracking=' . $tracking;

                    update_post_meta($order->get_id(), '_shipping_tracking_number', $tracking);
                    update_post_meta($order->get_id(), '_shipping_tracking_label', $etq);
                    update_post_meta($order->get_id(), '_shipping_label', $etq);
                    update_post_meta($order->get_id(), '_shipping_tracking_url', $track);
                    update_post_meta($order->get_id(), '_shipping_tracking_method', $this->provider['slug']);

                    $order->update_meta_data('_shipping_tracking_number', $tracking);
                    $order->update_meta_data('_shipping_tracking_label', $etq);
                    $order->update_meta_data('_shipping_label', $etq);
                    $order->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
                    $order->save();

                    Helpers::make_note($order, $tracking, $this->provider['name']);
                }
            }
        }

        // Handle failed orders
        if (isset($response['failed']) && is_array($response['failed']) && !empty($response['failed'])) {
            $logger = \wc_get_logger();
            foreach ($response['failed'] as $index => $errors) {
                $orderRef = isset($orderIndexMap[$index]) ? $orderIndexMap[$index]->get_order_number() : $index;
                $errorMsg = is_array($errors) ? json_encode($errors) : $errors;
                $logger->error('Bulk Upload Error for order ' . $orderRef . ': ' . $errorMsg, array('source' => WC_BORDEREAU_POST_TYPE));
                set_transient('order_bulk_add_error', __('Some orders failed: ', 'woo-bordereau-generator') . $errorMsg, 45);
            }
        }
    }

    /**
     * @return string[]
     */
    private function status()
    {

        return [
            "upload" => "Uploadé sur le système",
            "customer_validation" => "Validé",
            "validation_collect_colis" => "Colis Ramassé",
            "validation_reception_admin" => "Reception validé",
            "validation_reception" => "Enlevé par le livreur",
            "fdr_activated" => "En livraison",
            "sent_to_redispatch" => "En livraison",
            "nouvel_tentative_asked_by_customer" => "Nouvelle tentative demandée par le vendeur",
            "return_asked_by_customer" => "Retour demandé par le partenaire",
            "return_asked_by_hub" => "Retour En transit",
            "retour_dispatched_to_partenaires" => "Retour transmis au partenaire",
            "return_dispatched_to_partenaire" => "Retour transmis au partenaire",
            "colis_retour_transmit_to_partner" => "Retour transmis au partenaire",
            "colis_pickup_transmit_to_partner" => "Pick-UP transmis au partenaire",
            "annulation_dispatch_retour" => "Transmission du retour au partenaire annulée",
            "cancel_return_dispatched_to_partenaire" => "Transmission du retour au partenaire annulée",
            "livraison_echoue_recu" => "Retour reçu par le partenaire",
            "return_validated_by_partener" => "Retour validé par le partenaire",
            "return_redispatched_to_livraison" => "Retour remis en livraison",
            "return_dispatched_to_warehouse" => "Retour transmis vers entrepôt",
            "pickedup" => "Pick-Up collecté",
            "valid_return_pickup" => "Pick-Up validé",
            "pickup_picked_recu" => "Pick-Up reçu par le partenaire",
            "colis_suspendu" => "Suspendu",
            "livre" => "Livré",
            "livred" => "Livré",
            "verssement_admin_cust" => "Montant transmis au partenaire",
            "verssement_admin_cust_canceled" => "Versement annulé",
            "verssement_hub_cust_canceled" => "",
            "validation_reception_cash_by_partener" => "Montant reçu par le partenaire",
            "echange_valide" => "Échange validé",
            "echange_valid_by_hub" => "Échange validé",
            "ask_to_delete_by_admin" => "Demande de suppression",
            "ask_to_delete_by_hub" => "Demande de suppression",
            "edited_informations" => "Informations modifiées",
            "edit_price" => "Prix modifié",
            "edit_wilaya" => "Changement de wilaya",
            "extra_fee" => "Surfacturation du colis",
            "mise_a_jour" => "Tentative de livraison"
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
            foreach ($json as $tracking => $status) {
                if(isset($status['activity'])) {
                    $events = $status['activity'];
                    $last_event = end($events);
                    $result[$tracking] = $last_event['event'];
                }
            }

            return $result;
        }

        return [];
    }

    /**
     *
     * Get Shipping classes
     * @return void
     * @throws GuzzleException
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

        if ($agency_import_enabled) {
            update_option($this->provider['slug'] . '_agencies_import', true);
        }

        $path = Functions::get_path('nordouest_prices_v2.json');

        if (file_exists($path)) {
            $prices = json_decode(file_get_contents($path), true);
        } else {

            $url = $this->provider['api_url'] . "/fees?api_token=".$this->api_token. "&user_guid=" . $this->user_guid;

            $result = $this->request->get($url);
            $prices = $result['tarifs']['delivery'] ?? [];
            file_put_contents($path, json_encode($prices));
        }


        if (get_option('shipping_zones_grouped') && get_option('shipping_zones_grouped') == 'yes') {
            $this->shipping_zone_grouped($prices, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled);
        } else {
            $this->shipping_zone_ungrouped($prices, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled, $agency_import_enabled);
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

    private function shipping_zone_ungrouped($prices, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled, $agency_import_enabled)
    {

        // Set memory limit to -1 (unlimited) and set time limit to 0 (unlimited)
        ini_set('memory_limit', '-1');
		set_time_limit(0);
        $zones = [];

        $wilayasData = $this->get_wilayas();


        if ($agency_import_enabled) {
            $centers = $this->get_all_centers();
            if (! $centers) {
                $centers = [];
            }
        }

        $i = 1;
        foreach ($prices as $key => $price) {


            $searchWilayaId = $price['wilaya_id'];
            $priceStopDesk = (int)$price['tarif_stopdesk'] ?? null;
            $priceFlatRate = (int) $price['tarif'] ?? null;

            $foundWilaya = array_values(array_filter($wilayasData, function ($w) use ($searchWilayaId) {
                return $w['id'] == $searchWilayaId; // string vs int keep it ==
            }))[0] ?? null;

            if (!$foundWilaya) {
                return;
            }

            // Get all shipping zones
            $zones = WC_Shipping_Zones::get_zones();

            // Iterate through the zones and find the one with the matching name
            $found_zone = null;
            foreach ($zones as $zone) {
                if (isset($zone['zone_locations'])) {
                    if ($zone['zone_locations'][0]->code === 'DZ:DZ-' . str_pad($foundWilaya['id'], 2, '0', STR_PAD_LEFT)) {
                        $found_zone = $zone;
                        break;
                    }
                }
            }

            if (!$found_zone) {
                $zone = new WC_Shipping_Zone();
                $zone->set_zone_name(ucfirst(Helpers::normalizeCityName($foundWilaya['name'])));
                $zone->set_zone_order($key);
                $zone->add_location('DZ:DZ-' . str_pad($foundWilaya['id'], 2, '0', STR_PAD_LEFT), 'state');
                $zone->save();
            } else {
                $zone = WC_Shipping_Zones::get_zone($found_zone['id']);
            }


            if ($priceFlatRate && $flat_rate_enabled) {
                $flat_rate_name = 'flat_rate_noest';
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
                ];

                $_REQUEST['instance_id'] = $instance_flat_rate;
                $shipping_method_flat_rate->set_post_data($shipping_method_configuration_flat_rate);
                $shipping_method_flat_rate->process_admin_options();
            }

            if ($priceStopDesk && $pickup_local_enabled) {

                if ($agency_import_enabled) {
                    $agenciesInWilaya = array_filter($centers, function ($item) use ($price) {
                        $id = (int) preg_replace('/([0-9]+)\w/', '$1', $item['code']);
                        return $id == $price['wilaya_id'];
                    });

                    foreach ($agenciesInWilaya as $agency) {
                        $local_pickup_name = 'local_pickup_yalidine';
                        if (is_hanout_enabled()) {
                            $local_pickup_name = 'local_pickup';
                        }

                        $name = sprintf("%s %s", $pickup_local_label, $agency['name']);

                        $instance_local_pickup = $zone->add_shipping_method($local_pickup_name);
                        $shipping_method_local_pickup = WC_Shipping_Zones::get_shipping_method($instance_local_pickup);
                        $shipping_method_configuration_local_pickup = array(
                            'woocommerce_'.$local_pickup_name.'_title' => $name,
                            'woocommerce_'.$local_pickup_name.'_cost' => $priceStopDesk,
                            'woocommerce_'.$local_pickup_name.'_type' => 'class',
                            'woocommerce_'.$local_pickup_name.'_address' => $agency['address'],
                            'woocommerce_'.$local_pickup_name.'phone' => implode(',',array_filter($agency['phones'])),
                            'woocommerce_'.$local_pickup_name.'_gps' => $agency['map'],
                            'woocommerce_'.$local_pickup_name.'_center_id' => $agency['code'],
                            'woocommerce_'.$local_pickup_name.'_email' => $agency['email'],
                            'woocommerce_'.$local_pickup_name.'_provider' => $this->provider['slug'],
                            'instance_id' => $instance_local_pickup,
                        );

                        $_REQUEST['instance_id'] = $instance_local_pickup;
                        $shipping_method_local_pickup->set_post_data($shipping_method_configuration_local_pickup);
                        $shipping_method_local_pickup->process_admin_options();
                    }
                } else {
                    $local_pickup_name = 'local_pickup_noest';
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
                    );

                    $_REQUEST['instance_id'] = $instance_local_pickup;
                    $shipping_method_local_pickup->set_post_data($shipping_method_configuration_local_pickup);
                    $shipping_method_local_pickup->process_admin_options();
                }
            }
        }

        return [$shipping_method_flat_rate, $shipping_method_local_pickup];
    }


    private function get_wilayas()
    {

        $path = Functions::get_path('nordouest_' . WC_BORDEREAU_GENERATOR_VERSION . '_wilaya.json');

        if (!file_exists($path)) {
            $content = $this->request->get('https://amineware.me/api/wilaya-noest', false);
            file_put_contents($path, json_encode($content));
        }

        $wilayas = json_decode(file_get_contents($path), true);

        if ($wilayas == null || count($wilayas) == 0) {
            return $this->get_wilaya_from_provider();
        }

        return $wilayas;
    }

    private function get_wilaya_from_provider()
    {
        $url = $this->provider['api_url'] . '/get/wilayas?api_token=' . $this->api_token . '&user_guid=' . $this->user_guid;
        return $this->request->get($url, false);
    }


    public function get_status()
    {
        return $this->status();
    }

    /**
     * Get paginated orders from the NOEST API.
     *
     * @param array $queryData Query parameters from ShippingOrdersTable.
     * @return array { items: array, total_data: int, current_page: int }
     */
    public function get_orders(array $queryData): array
    {
        $page    = (int) ($queryData['page'] ?? 1);
        $perPage = (int) ($queryData['page_size'] ?? 15);

        $params = [
            'per_page'  => $perPage,
            'page'      => $page,
            'api_token' => $this->api_token,
            'user_guid' => $this->user_guid,
        ];

        $url  = $this->provider['api_url'] . '/get/orders?' . http_build_query($params);
        $json = $this->request->get($url, false);

        if (! is_array($json) || ! isset($json['data'])) {
            return ['items' => [], 'total_data' => 0, 'current_page' => $page];
        }

        $wilayaMap = $this->get_wilaya_map();
        $statuses  = $this->status();

        $items = array_map(function ($item) use ($wilayaMap, $statuses) {
            $wilayaName = $wilayaMap[(int) ($item['wilaya_id'] ?? 0)] ?? ('Wilaya ' . ($item['wilaya_id'] ?? ''));
            $statusLabel = $statuses[$item['status'] ?? ''] ?? ($item['status'] ?? '');

            return [
                'tracking'         => $item['tracking'] ?? '',
                'familyname'       => $item['client'] ?? '',
                'firstname'        => '',
                'to_commune_name'  => $item['commune'] ?? '',
                'to_wilaya_name'   => $wilayaName,
                'date'             => $item['created_at'] ?? '',
                'contact_phone'    => ! empty($item['phone_2'])
                    ? sprintf('%s/%s', $item['phone'], $item['phone_2'])
                    : ($item['phone'] ?? ''),
                'city'             => $item['commune'] ?? '',
                'state'            => $wilayaName,
                'last_status'      => $statusLabel,
                'date_last_status' => $item['date_last_status'] ?? '',
                'label'            => sprintf(
                    '%s/get/order/label?tracking=%s&api_token=%s',
                    $this->provider['api_url'],
                    $item['tracking'] ?? '',
                    $this->api_token
                ),
            ];
        }, $json['data']);

        return [
            'items'        => $items,
            'total_data'   => (int) ($json['total'] ?? count($items)),
            'current_page' => (int) ($json['current_page'] ?? $page),
        ];
    }

    /**
     * Build a wilaya_id => wilaya_name map from the cached wilayas file.
     */
    private function get_wilaya_map(): array
    {
        $path = Functions::get_path(sprintf('%s_wilayas', $this->provider['slug']));

        if (! file_exists($path)) {
            $wilayas = $this->get_wilaya_from_provider();
            if (is_array($wilayas)) {
                file_put_contents($path, json_encode($wilayas));
            } else {
                return [];
            }
        }

        $wilayas = json_decode(file_get_contents($path), true);
        $map     = [];

        if (is_array($wilayas)) {
            foreach ($wilayas as $w) {
                if (isset($w['wilaya_id'], $w['wilaya_name'])) {
                    $map[(int) $w['wilaya_id']] = $w['wilaya_name'];
                } elseif (isset($w['code'], $w['nom'])) {
                    $map[(int) $w['code']] = $w['nom'];
                }
            }
        }

        return $map;
    }

    public function get_communes($wilaya_id) {

	    if ( ! is_numeric( $wilaya_id ) ) {
		    $wilaya_id = str_replace( 'DZ-', '', $wilaya_id );
	    }

	    $path = __DIR__ . '/data/noest_cities.json';

	    if ( ! file_exists( $path ) ) {
		    $content = $this->request->get( 'https://amineware.me/api/commune-noest/', false );
		    file_put_contents( $path, json_encode( $content ) );
	    }

	    $communes = json_decode( file_get_contents( $path ), true );

	    if ( ! is_array( $communes ) || count( $communes ) == 0 ) {
		    $content = $this->request->get( 'https://amineware.me/api/commune-noest/', false );
		    file_put_contents( $path, json_encode( $content ) );
		    $communes = json_decode( file_get_contents( $path ), true );
	    }
	    $communesResult = [];

	    if ( is_array( $communes ) && count( $communes ) ) {
		    foreach ( $communes as $i => $item ) {
			    if ( $item['wilaya_id'] == $wilaya_id ) {
				    $communesResult[ $i ]['id']      = $item['nom'];
				    $communesResult[ $i ]['name']    = $item['nom'];
				    $communesResult[ $i ]['enabled'] = $item['is_active'];
			    }
		    }

		    return $communesResult;
	    }
    }

    /**
     * Mark a single order as dispatched/validated
     * @param \WC_Order $order
     * @return array|null
     */
    public function mark_as_dispatched(\WC_Order $order)
    {
        $tracking_number = $order->get_meta('_shipping_tracking_number');

        if ($tracking_number) {
            // Use bulk validation endpoint with single tracking
            return $this->bulk_validate_orders([$tracking_number]);
        }

        return null;
    }

    /**
     * Bulk validate orders using the Noest bulk validation API endpoint
     * @param array $trackings Array of tracking numbers
     * @return array Results with passed and failed orders
     */
    public function bulk_validate_orders(array $trackings)
    {
        if (empty($trackings)) {
            return ['success' => false, 'message' => __('No tracking numbers provided', 'woo-bordereau-generator')];
        }

        // Use bulk validation endpoint: POST /api/public/valid/orders
        $url = $this->provider['api_url'] . "/valid/orders";

        // Build bulk validation payload per API docs
        $payload = [
            "user_guid" => $this->user_guid,
            "trackings" => $trackings
        ];

        $response = $this->request->post($url, $payload, NoestRequest::POST, true);

        // Log results
        $this->processValidationResponse($response, $trackings);

        return $response;
    }

    /**
     * Bulk validate orders by order IDs
     * @param array $order_ids Array of WooCommerce order IDs
     * @return array Results with passed and failed orders
     */
    public function bulk_validate_orders_by_ids(array $order_ids)
    {
        $trackings = [];

        foreach ($order_ids as $id) {
            $order = wc_get_order($id);
            if ($order) {
                $tracking = $order->get_meta('_shipping_tracking_number');
                if ($tracking) {
                    $trackings[] = $tracking;
                }
            }
        }

        if (empty($trackings)) {
            return ['success' => false, 'message' => __('No tracking numbers found for provided orders', 'woo-bordereau-generator')];
        }

        return $this->bulk_validate_orders($trackings);
    }

    /**
     * Process bulk validation API response
     * @param array $response
     * @param array $trackings
     */
    private function processValidationResponse($response, $trackings)
    {
        if (!is_array($response)) {
            set_transient('order_bulk_validation_error', __('Invalid API response', 'woo-bordereau-generator'), 45);
            $logger = \wc_get_logger();
            $logger->error('Bulk Validation Error: Invalid API response', array('source' => WC_BORDEREAU_POST_TYPE));
            return;
        }

        // Log passed validations
        if (isset($response['passed']) && is_array($response['passed'])) {
            $passedCount = count($response['passed']);
            if ($passedCount > 0) {
                $logger = \wc_get_logger();
                $logger->info('Bulk Validation Success: ' . $passedCount . ' orders validated', array('source' => WC_BORDEREAU_POST_TYPE));
            }
        }

        // Log failed validations
        if (isset($response['failed']) && is_array($response['failed']) && !empty($response['failed'])) {
            $logger = \wc_get_logger();
            foreach ($response['failed'] as $tracking => $errors) {
                $errorMsg = is_array($errors) ? json_encode($errors) : $errors;
                $logger->error('Bulk Validation Error for tracking ' . $tracking . ': ' . $errorMsg, array('source' => WC_BORDEREAU_POST_TYPE));
            }
            set_transient('order_bulk_validation_error', __('Some orders failed validation', 'woo-bordereau-generator'), 45);
        }
    }

    public function get_all_centers()
    {

        $path = Functions::get_path($this->provider['slug'].'_centers_'.WC_BORDEREAU_GENERATOR_VERSION.'_'.date('Y').'_'. (date('z') + 1).'.json');

        if (! file_exists($path)) {

            $url = $this->provider['api_url'] . '/desks?api_token=' . $this->api_token . '&user_guid=' .$this->user_guid;
            // Make the API request
            $response = $this->request->get($url, false);

            // Retrieve the response body and decode it
            $json = $response;
            file_put_contents($path, json_encode($json));
        }

        $centers = json_decode(file_get_contents($path), true);
        foreach ($centers as $key =>$center) {
            $centers[$key] = $center;
            $centers[$key]['id'] = $center['code'];
        }

        return $centers;
    }


    private function get_first_center($order)
    {
        $array = $this->get_centers_for_order($order);
        return reset($array) ?? null;
    }

    private function get_centers_for_order($order)
    {
        $nordouest = new NoestShipping($order, $this->provider);
        return $nordouest->get_centers(str_replace('DZ-', '', $order->get_billing_state()));
    }



}
