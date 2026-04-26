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
use WC_Order;
use WC_Product;
use WooBordereauGenerator\Functions;
use WooBordereauGenerator\Admin\BordereauGeneratorAdmin;
use WooBordereauGenerator\Helpers;
use WP_Error;

class MaystroShipping extends BordereauProvider implements BordereauProviderInterface
{

    /**
     * @var
     */
    private $api_token;


    /**
     * @var WC_Order
     */
    protected $formData;

    /**
     * @var array
     */
    protected $provider;

    /**
     * @var array
     */
    private $data;

    /**
     * @var false|mixed|null
     */
    private $api_id;

    /**
     * @param WC_Order $formData
     * @param array $provider
     * @param array $data
     */
    public function __construct(WC_Order $formData, array $provider, array $data = [])
    {

        $this->formData = $formData;
        $this->api_id = get_option('maystro_delivery_store_id');
        $this->api_token = get_option('maystro_delivery_api_token');
        $this->provider = $provider;
        $this->data = $data;
    }

    /**
     * Generate the tracking code
     * @return mixed|WP_Error
     * @throws Exception
     * @since 1.0.0
     * Generate the tracking number for ecotrack
     */
    public function generate()
    {

        $params = $this->formData->get_data();

        $orderId = $this->formData->get_id();

        $wilayas = $this->get_wilayas();
	    $wilayaObj = null;

        if (isset($this->data['wilaya'])) {
            $wilayaId = (int)str_replace("DZ-", "", $this->data['wilaya']);
            $wilaya = array_search($wilayaId, array_column($wilayas, 'code'));
			$wilayaObj = $wilayas[$wilaya];

        } else {
            $wilayaId = (int)str_replace("DZ-", "", $params['billing']['state']);
            $wilaya = array_search($wilayaId, array_column($wilayas, 'code'));
	        $wilayaObj = $wilayas[$wilaya];
        }


        if ($wilaya === null) {
            wp_send_json([
                'error' => true,
                'message' => __("Wilaya has not been selected", 'woo-bordereau-generator')
            ], 422);
        }

//        $communeId = 0;
//        if (isset($this->data['commune'])) {
//
//            $commnes = $this->get_communes($wilayaId, false);
//
//            $key = array_search($this->data['commune'], array_column($commnes, "name"));
//
//            if ($key !== false) {
//                $communeId = (int)$commnes[$key]['id'];
//            }
//        }
//
//        if (isset($params['billing']['city']) && empty($communeId)) {
//            $commnes = $this->get_communes($wilayaId, false);
//            $key = array_search($params['billing']['city'], array_column($commnes, "name"));
//            if ($key) {
//                $communeId = (int) $commnes[$key]['id'];
//            }
//        }



        $details = [];
        $i = 0;

        if(isset($_POST['advance']) && isset($_POST['product_id']) && isset($_POST['product_qty'])) {
            $details[$i]['product'] = $_POST['product_id'];
            $details[$i]['quantity'] = ((int) $_POST['product_qty']) == 0 ? 1 : (int) $_POST['product_qty'];
        } else {
            // build the product details - new API supports inline product creation
            foreach ($this->formData->get_items() as $item_key => $item) {

                $item_data = $item->get_data();
                $product_id = $item->get_product_id();
                $product_variation_id = $item->get_variation_id();
                $_id = $product_id;

                if ($product_variation_id) {
                    $_id = $product_variation_id;
                }

                $id = get_post_meta($_id, 'maystro_delivery_product_id', true);

                // Use existing product ID or external product ID for inline creation
                $details[$i]['product'] = $id ?: ($product_id . ':' . $product_variation_id);
                $details[$i]['quantity'] = $item_data['quantity'];
                $details[$i]['description'] = $item_data['name'];

                $i++;
            }
        }

        $stop_desk = 0;
		
		if ($this->formData->has_shipping_method('free_shipping') || $this->data['free']) {
			$free_shipping = 1;
		}

		if ($this->formData->has_shipping_method('local_pickup') || $this->data['shipping_type'] === 'stopdesk') {
			$stop_desk = 1;
		}


        $phone = $this->data['phone'] ?? $params['billing']['phone'];
        $phone = str_replace(' ', '', str_replace('+213', '0', $phone));


        if ($this->data['remarque']) {
            $note = $this->data['remarque'];
        } else {
            $note = stripslashes(get_option('wc_bordreau_default-shipping-note'));
        }

        $data = [
            "customer_name" => $this->data['fname'] . " " . $this->data['lname'] ?? $params['billing']['last_name'] . ' ' . $params['billing']['first_name'],
            "customer_phone" => $phone,
            "destination_text" => $this->data['address'] ?? $params['billing']['address_1'] ?? stripslashes($params['billing']['city']),
            "commune" => $this->data['commune'],
            "wilaya" => $wilayaObj['name_lt'],
            "external_id" => (string) $orderId,
            "details" => $details,
			"delivery_type" => $stop_desk == 1 ? 2 : 1,
            "total_price" => (int) ($this->data['price'] ?? $params['total']),
            "note_to_driver" => $note,
            "express" => true,
        ];

        if (isset($this->data['is_update'])) {
            $url = $this->provider['api_url'] . "/stores/orders/" . $this->data['tracking_number'];
        } else {
            $url = $this->provider['api_url'] . "/orders";
        }



        $request = wp_safe_remote_post(
            $url,
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => "Token " . $this->api_token
                ],
                'body' => wp_json_encode($data),
                'method' => isset($this->data['is_update']) ? 'PUT' : 'POST',
                'data_format' => 'body',
            ]
        );

        $body = wp_remote_retrieve_body($request);
	    $response = json_decode($body, true);


        if (empty($body)) {
            wp_send_json([
                'error' => true,
                'message' => __("An error has occurred. Please see log file ", 'woo-bordereau-generator')
            ], 401);
        }

        $response = json_decode($body, true);

        // Handle old API error format
        if (isset($response['status_code']) && $response['status_code'] == 400) {
            wp_send_json([
                'error' => true,
                'message' => $response[array_key_first($response)] ?? __("An error has occurred. Please see log file ", 'woo-bordereau-generator')
            ], $response['status_code']);
        }

        if (empty($response) || !is_array($response)) {
            wp_send_json([
                'error' => true,
                'message' => __("An error has occurred. Please see log file ", 'woo-bordereau-generator')
            ], 401);
        }

        // Handle old API error format
        if (isset($response['error'])) {
            $errorMsg = is_array($response['error']) ? ($response['error']['message'] ?? json_encode($response['error'])) : $response['error'];
            wp_send_json([
                'error' => true,
                'message' => $errorMsg
            ], 401);
        }

        // Handle update (old API format - returns single object)
        if (isset($this->data['is_update'])) {
            $response['tracking'] = $this->data['tracking_number'];
            return $response;
        }

        // New API returns array format for create
        if (isset($response[0])) {
            $order_result = $response[0];

            // Check for errors in new API format
            if (!empty($order_result['errors'])) {
                $error_codes = $this->map_error_codes($order_result['errors']);
                wp_send_json([
                    'error' => true,
                    'message' => implode(', ', $error_codes)
                ], 400);
            }

            // Map new API response to expected format
            return [
                'id' => $order_result['id'],
                'display_id' => $order_result['tracking'],
                'tracking' => $order_result['tracking'],
                'delivery_price' => $order_result['delivery_price'] ?? null,
            ];
        }

        // Fallback for old API format (single object with id/display_id)
        return $response;
    }



    /**
     * Return the settings we need in the metabox
     * @return array
     *@since 1.2.4
     */
    public function get_settings(array $data, $option = null)
    {

        $provider = $this->provider;
        $order = $this->formData;
        $params = $order->get_data();
        $products = $params['line_items'];
        $productString = Helpers::generate_products_string($products);
        $fields = $provider['fields'];
        $key = $fields['total']['choices'][0]['name'] ?? null;

        $total = get_option($key) ? get_option($key) : 'with-shipping';

        $cart_value = 0;

        if ($total == 'with-shipping') {
            $cart_value = (float) $order->get_total();
        } elseif ($total === 'without-shipping') {
            $cart_value =  (float) $order->get_total() - $order->get_total_shipping();
        }

        $data = [];
        if ($key) {
            $data['total_calculate'] = get_option($key) ? get_option($key) : 'with-shipping';
            $data['type'] = $provider['type'];
            $data['total'] = $cart_value;
            $data['order_number'] = $order->get_order_number();
            $data['products_string'] = $productString;
        }
        return $data;
    }


    /**
     * Save the response to the postmeta
     * @param $post_id
     * @param array $response
     * @throws Exception
     * @since 1.0.0
     */
    public function save($post_id, array $response)
    {
        $etq = get_rest_url(null, 'woo-bordereau/v1/slip/print/'.$this->provider['slug'].'/'.$post_id);

        try {
            $track = $this->provider['api_url'] . '/stores/history_order/' . $response['id'];

            update_post_meta($post_id, '_shipping_tracking_number', wc_clean($response['display_id']));
            update_post_meta($post_id, '_shipping_maystro_order_id', wc_clean($response['id']));
            update_post_meta($post_id, '_shipping_tracking_label', '');
            update_post_meta($post_id, '_shipping_label', '');
            update_post_meta($post_id, '_shipping_tracking_label_method', 'POST');
            update_post_meta($post_id, '_shipping_tracking_url', $track);
            update_post_meta($post_id, '_shipping_tracking_method', $this->provider['slug']);

            $order = wc_get_order($post_id);

            if($order) {
                $order->update_meta_data('_shipping_maystro_order_id', wc_clean($response['id']));
                $order->update_meta_data('_shipping_tracking_number', wc_clean($response['display_id']));
                $order->update_meta_data('_shipping_tracking_label', '');
                $order->update_meta_data('_shipping_label', '');
                $order->update_meta_data('_shipping_tracking_label_method', 'POST');
                $order->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
                $order->save();

                Helpers::make_note($order, $response['display_id'], $this->provider['name']);

            }

            $current_tracking_data = [
                'tracking_number' => wc_clean($response['display_id']),
                'carrier_slug' => $this->provider['slug'],
                'carrier_url' => $track,
                "carrier_name" => $this->provider['name'],
                "carrier_type" => "custom-carrier",
                "time" => time()
            ];

            $item_tracking_data[] = $current_tracking_data;

            $order = new WC_Order($post_id);

            $order_items = $order->get_items();

            // check if the extension tracking is enabled
            if (in_array('woo-orders-tracking/woo-orders-tracking.php', apply_filters('active_plugins', get_option('active_plugins')))) {
                foreach ($order_items as $item) {
                    wc_update_order_item_meta($item->get_id(), '_vi_wot_order_item_tracking_data', json_encode($item_tracking_data));
                }
            }

            wp_send_json([
                'message' => __("Tracking number has been successfully created", 'woo-bordereau-generator'),
                'provider' => $this->provider['name'],
                'tracking' => $current_tracking_data,
                'label' => $etq
            ], 200);
        } catch (\ErrorException $exception) {
            wp_send_json([
                'message' => $exception->getMessage(),
                'provider' => $this->provider['name'],
            ], 401);
        }


    }

    /**
     * Get the trackinng information for the order
     *
     * @param $tracking_number
     * @param null $post_id
     * @return void
     */
    public function track($tracking_number, $post_id = null)
    {

        $url = $this->provider['api_url'] . "/stores/history_order/" . $tracking_number;

        $request = wp_safe_remote_get(
            $url,
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => "Token " . $this->api_token
                ],
            ]
        );


        $body = wp_remote_retrieve_body($request);

        $response_array = json_decode($body, true);


        if (is_array($response_array)) {
            wp_send_json([
                'tracking' => $tracking_number,
                'events' => Helpers::sort_tracking($this->format_tracking($response_array))
            ]);
        } else {
            wp_send_json([
                'tracking' => $tracking_number,
                'events' => []
            ]);
        }
    }


    /**
     * Get the trackinng information for the order
     *
     * @param string $tracking_number
     * @return void
     */
    public function track_detail($tracking_number)
    {

        $url = $this->provider['api_url'] . "/stores/history_order/" . $tracking_number;

        $request = wp_safe_remote_get(
            $url,
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => "Token " . $this->api_token
                ],
            ]
        );


        $body = wp_remote_retrieve_body($request);

        $response_array = json_decode($body, true);

        return $this->format_tracking($response_array);
    }


    /**
     * @param int $wilaya_id
     * @param bool $hasStopDesk
     *
     * @return array
     */
    public function get_communes(int $wilaya_id, bool $hasStopDesk)
    {

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

        $communesResult = [];

        if (is_array($communes)) {
            foreach ($communes as $i => $item) {
                $communesResult[$i]['id'] = $item['id'];
                $communesResult[$i]['name'] = $item['name'];
            }
        }

        return $communesResult;
    }


    /**
     * Format the response
     *
     * @param array $response_array
     * @return array
     */
    private function format_tracking($response_array)
    {

        $status = $this->status();

        $result = [];

        if (is_array($response_array)) {

            if(isset($response_array['activity'])) {
                foreach ($response_array['activity'] as $key => $item) {
                    if ($item['status'] === 'attempt_delivery') {
                        $result[$key]['failed'] = true;
                    }

                    if ($item['status'] === 'livred') {
                        $result[$key]['delivered'] = true;
                        if (isset($result[$key]['failed'])) {
                            $result[$key]['failed'] = false;
                        }
                    }

                    $result[$key]['date'] = $item['date'] . " " . $item['time'];
                    $result[$key]['location'] = $item['scanLocation'];
                    $result[$key]['status'] = $status[$item['status']] ?? $item['status'];
                }
                usort($result, [$this, 'sortByOrder']);
            }

        }

        $last_status = $result[0]['status'] ?? '';

        if ($last_status) {
            BordereauGeneratorAdmin::update_tracking_status($this->formData, $last_status, 'single_tracking');
        }


        return $result;
    }


    /**
     * @param array $item
     * @return array
     */
    private function format_tracking_detail(array $item)
    {


        $status = $this->get_status();

        $detail = [];

        $wilayaId = 0;

        foreach ($this->get_wilayas() as $wilaya) {
            if ($wilaya[1] === $item['wilaya']) {
                $wilayaId = $wilaya[0];
                break;
            }
        }

        $detail[0]['address'] = $item['destination_text'];
        $detail[0]['phone'] = $item['customer_phone'];
        $detail[0]['fname'] = $item['customer_name'];
        $detail[0]['free'] = false;
        $detail[0]['exchange'] = false;
        $detail[0]['stopdesk'] = false;
        $detail[0]['last_status'] = isset($status[0]) ? $status[0]['status'] : '';
        $detail[0]['order_id'] = $item['external_order_id'];
        $detail[0]['price'] = $item['product_price'] + $item['price'];
        $detail[0]['commune'] = $item['commune_name'];
        $detail[0]['wilaya'] = $wilayaId ?? $item['wilaya'];
        $detail[0]['product_id'] = isset($item['products']) && count($item['products']) ? $item['products'][0]['product_id'] : null;
        $detail[0]['product_qty'] = isset($item['products']) && count($item['products']) ? $item['products'][0]['quantity'] : null;
        $detail[0]['product_name'] = isset($item['products']) && count($item['products']) ? $item['products'][0]['logistical_description'] : null;

        return $detail;
    }

    function sortByOrder($a, $b)
    {
        $t1 = strtotime($a['date']);
        $t2 = strtotime($b['date']);
        return $t2 - $t1;
    }

    /**
     * @return array
     */
    public function get_wilayas(): array
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
     *
     * @return array
     */
    private function get_wilaya_from_provider(): array
    {

        return $this->get_wilayas();
    }


    /**
     * @param $tracking_number
     * @param $post_id
     * @return void
     * @since 1.2.0
     */
    public function detail($tracking_number, $post_id)
    {
        $tracking_number = get_post_meta($post_id, '_shipping_maystro_order_id', true);

        $url = $this->provider['api_url'] . "/stores/orders/" . $tracking_number;

        $request = wp_safe_remote_get(
            $url,
            [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => "Token " . $this->api_token
                ],
            ]
        );

        $body = wp_remote_retrieve_body($request);

        $response_array = json_decode($body, true);

        $details = $this->format_tracking_detail($response_array);

        if (isset($details['activity'])) {
            if (isset($details[0]['last_status'])) {
                BordereauGeneratorAdmin::update_tracking_status($this->formData, $details[0]['last_status'], 'single_tracking');
            }
        }


        wp_send_json([
            'tracking' => $tracking_number,
            'detail' => $details
        ]);
    }

    private function status()
    {
        return [
            "CREATED" => "Colis En attente d'envoi",
            "PICK_UP_REQUESTED" => "Ramassage demandé",
            "IN_PROCESS" => "Colis en cours de traitement",
            "WAITING_TRANSIT" => "En attente de transit",
            "IN_TRANSIT_TO_BE_SHIPPED" => "En transit pour livraison",
            "IN_TRANSIT_TO_BE_RETURNED" => "En transit pour retour",
            "PENDING" => "En attente",
            "OUT_OF_STOCK" => "Rupture de stock",
            "READY_TO_SHIP" => "Prêt à expédier",
            "ASSIGNED" => "Assigné au livreur",
            "SHIPPED" => "Colis expédié",
            "ALERTED" => "Colis en alerte",
            "DELIVERED" => "Colis livré",
            "POSTPONED" => "Colis reporté",
            "ABORTED" => "Colis annulé",
            "READY_TO_RETURN" => "Prêt pour retour",
            "TAKEN_BY_STORE" => "Récupéré par le vendeur",
            "NOT_RECEIVED" => "Non reçu",
            "order_information_received_by_carrier" => "Colis En attente d'envoi",
            "picked" => "Colis reçu par la société de livraison",
            "accepted_by_carrier" => "Colis arrivé à la station régionale",
            "dispatched_to_driver" => "Colis en livraison",
            "livred" => "Colis livré",
            "attempt_delivery" => "Tentative de livraison échouée",
            "return_asked" => "Retour initié",
            "return_in_transit" => "Retour en transit",
            "Return_received" => "Retour réceptionné par le vendeur",
            "encaissed" => "Commande encaissée",
            "payed" => "Paiement effectué",
            "prete_a_expedier" => "Prêt à expédier",
        ];
    }

    /**
     * @param $provider
     *
     * @return array
     */
    public function get_wilayas_by_provider($provider): array
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

        $wilayasResult = [];

        if (file_exists($path)) {
            $result = json_decode(file_get_contents($path), true);

            if ($result == null || count($result) == 0) {
                $result = $this->get_wilaya_from_provider();
            }
        } else {
            $response_array = $this->get_wilaya_from_provider();

            if (isset($response_array['error'])) {
                wp_send_json([
                    'error' => true,
                    'message' => $response_array['error']['message']
                ], 401);
            } else {
                file_put_contents($path, json_encode($response_array));
                $result = $response_array;
            }
        }


        if (isset($result)) {
            foreach ($result as $i => $wilaya) {
	            $wilayasResult[$i]['name'] = $wilaya['name_lt'];
	            $wilayasResult[$i]['id'] = $wilaya['display_id'];
            }

            return $wilayasResult;
        }

        return $wilayasResult;
    }


    /**
     * @param $tracking_number
     * @param $post_id
     * @return void
     * @since 1.3.0
     */
    public function delete($tracking_number, $post_id)
    {

        $tracking_number = get_post_meta($post_id, '_shipping_maystro_order_id', true);

        $data = [
            'status' => 50,
            'abort_reason' => 21,
        ];

        $url = $this->provider['api_url'] . "/shared/status/" . $tracking_number;

        $request = wp_safe_remote_request(
            $url,
            [
                'method' => 'PATCH',
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Token ' . $this->api_token,
                ],
                'body' => wp_json_encode($data),
                'data_format' => 'body',
                'timeout' => 30,
            ]
        );

        if (is_wp_error($request)) {
            wp_send_json([
                'error' => true,
                'message' => $request->get_error_message()
            ], 401);
        }

        $body = wp_remote_retrieve_body($request);
        $response_array = json_decode($body, true);

        if (isset($response_array['error'])) {
            wp_send_json([
                'error' => true,
                'message' => $response_array['error']['message']
            ], 401);
        } elseif (isset($response_array['errors'])) {
            wp_send_json([
                'error' => true,
                'message' => $response_array['message']
            ], 401);
        }

        return $response_array;
    }

    /**
     * Map new API error codes to human-readable messages
     * @param array $error_codes
     * @return array
     */
    private function map_error_codes(array $error_codes): array
    {
        $map = [
            10 => __("Le prix de la commande n'est pas valide ou n'a pas été défini.", 'woo-bordereau-generator'),
            20 => __("L'adresse de la commande n'est pas définie.", 'woo-bordereau-generator'),
            30 => __("Les informations client (nom/téléphone) ne sont pas valides ou n'ont pas été définies.", 'woo-bordereau-generator'),
            40 => __("La wilaya ou la commune n'existent pas ou n'ont pas été définies.", 'woo-bordereau-generator'),
            45 => __("Le type de livraison n'existe pas pour la commune indiquée.", 'woo-bordereau-generator'),
            50 => __("Aucun produit dans 'details' n'a été spécifié ou ils ne sont pas valides.", 'woo-bordereau-generator'),
            55 => __("Commande dupliquée (même nom/téléphone dans les dernières 24 heures).", 'woo-bordereau-generator'),
            60 => __("Erreur inattendue, veuillez contacter le support.", 'woo-bordereau-generator'),
            70 => __("Les frais de livraison n'ont pas pu être détectés, veuillez contacter le support.", 'woo-bordereau-generator'),
            80 => __("La liste de commandes est vide.", 'woo-bordereau-generator'),
            90 => __("Le nombre maximum de commandes a été dépassé (plus de 1000).", 'woo-bordereau-generator'),
            100 => __("L'ID externe de la commande dépasse la limite de 100 caractères.", 'woo-bordereau-generator'),
            101 => __("Points insuffisants pour payer les frais de livraison.", 'woo-bordereau-generator'),
        ];

        $messages = [];
        foreach ($error_codes as $code) {
            $messages[] = $map[$code] ?? sprintf(__("Erreur code %d", 'woo-bordereau-generator'), $code);
        }
        return $messages;
    }

    /**
     * @return string[]
     */
    private function get_status(): array
    {
        return [
            "4" => "CREATED",
            "5" => "PICK_UP_REQUESTED",
            "6" => "IN_PROCESS",
            "8" => "WAITING_TRANSIT",
            "9" => "IN_TRANSIT_TO_BE_SHIPPED",
            "10" => "IN_TRANSIT_TO_BE_RETURNED",
            "11" => "PENDING",
            "12" => "OUT_OF_STOCK",
            "15" => "READY_TO_SHIP",
            "22" => "ASSIGNED",
            "31" => "SHIPPED",
            "32" => "ALERTED",
            "41" => "DELIVERED",
            "42" => "POSTPONED",
            "50" => "ABORTED",
            "51" => "READY_TO_RETURN",
            "52" => "TAKEN_BY_STORE",
            "53" => "NOT_RECEIVED",
        ];
    }

    /**
     * @since 2.2.0
     * @return void
     */
    public function get_slip()
    {

        $tracking_number = get_post_meta($this->formData->get_id(), '_shipping_tracking_number', true);
        if(! $tracking_number) {
            $tracking_number = $this->formData->get_meta('_shipping_tracking_number');
        }

        $tracking_number = wc_clean($tracking_number);

        $request = wp_safe_remote_post(
            $this->provider['api_url'] . '/starter_bordureau/',
            [
                'headers' => [
                    'Content-Type' => 'application/json;charset=UTF-8',
                    'Authorization' => 'Token ' . $this->api_token,
                    'Accept' => 'application/json, text/plain, */*',
                ],
                'body' => wp_json_encode(['orders_ids' => [$tracking_number]]),
                'data_format' => 'body',
                'timeout' => 60,
            ]
        );

        if (is_wp_error($request)) {
            wp_send_json([
                'error' => true,
                'message' => __("Unable to retrieve shipping slip", 'woo-bordereau-generator')
            ], 500);
        }

        $response = wp_remote_retrieve_body($request);

        header('Content-type: application/pdf');
        header("Content-disposition: inline;filename=\"{$tracking_number}.pdf\"");
        header('Content-Transfer-Encoding: binary');
        header('Accept-Ranges: bytes');

        echo $response;
        exit();
    }


}
