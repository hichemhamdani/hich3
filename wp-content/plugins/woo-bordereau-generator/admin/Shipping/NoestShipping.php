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

class NoestShipping extends BordereauProvider implements BordereauProviderInterface
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
    private $user_guid;

    /**
     * @var NoestRequest
     */
    private $request;

    /**
     * @param WC_Order $formData
     * @param array $provider
     * @param array $data
     */
    public function __construct(WC_Order $formData, array $provider, array $data = [])
    {

        $this->formData     = $formData;
        $this->user_guid = get_option(sprintf('%s_express_user_guid', $provider['slug']));
        $this->api_token = get_option(sprintf('%s_express_api_token', $provider['slug']));
        $this->provider     = $provider;
        $this->data     = $data;
        $this->request = new NoestRequest($provider);
    }

    /**
     * Generate the tracking code
     * @since 1.0.0
     * Generate the tracking number for ecotrack
     * @return mixed|WP_Error
     * @throws Exception
     */
    public function generate()
    {

        $params = $this->formData->get_data();
        $orderId = $this->formData->get_order_number();
        $wilayas = $this->get_wilayas();

        $fields = $this->provider['fields'];
        $key = $fields['total']['choices'][0]['name'] ?? null;
        $overweight_checkout = $fields['overweight_checkout']['choices'][0]['name'] ?? null; // the fee is in the checkout
        $overweight = $fields['overweight']['choices'][0]['name'] ?? null; // the fee is in the checkout
        $canOpen = $fields['can_open']['choices'][0]['name'] ?? null;

        $overweightCase = get_option($overweight) ? get_option($overweight) : 'recalculate-without-overweight';
        $overweightCheckoutCase = get_option($overweight_checkout) ? get_option($overweight_checkout) : 'recalculate-without-overweight';
        $canOpenCase = get_option($canOpen) ? get_option($canOpen) : 'false';

        if (isset($this->data['wilaya'])) {
            $wilayaId = (int) str_replace("DZ-", "", $this->data['wilaya']);
            $wilaya = array_search($wilayaId, array_column($wilayas, 'wilaya_id'));
        } else {
            $wilayaId = (int) str_replace("DZ-", "", $params['billing']['state']);
            $wilaya = array_search($wilayaId, array_column($wilayas, 'wilaya_id'));
        }

        if ($wilaya === null) {
            wp_send_json([
                'error' => true,
                'message'=> __("Wilaya has not been selected", 'wc-bordereau-generator')
            ], 422);
        }

        if (isset($this->data['products'])) {
            $productString = nl2br($this->data['products']);
        } else {
            $products = $params['line_items'];

            $productString = "";

            foreach ($products as $p) {
                $productString .= $p['name']. ' x '. $p['quantity'].  ' ,';
            }

            $productString = preg_replace('/,$/m', '', $productString);
        }


        $productString = mb_strimwidth($productString, 0, 252, "..."); // issue of 255 char

        $phone  = '';
        $phone2  = '';

        if (isset($this->data['phone'])) {
            $phone = $this->data['phone'];
        } else {
            $phone = $params['billing']['phone'];
        }

        $phone = str_replace(' ', '', str_replace('+213', '0', $phone));

        $phones = explode('/', $phone);

        if (count($phones) > 1) {
            $phone2 = $phone[1];
            $phone = $phone[0];
        }

        $free_shipping = 0;
        $stop_desk = 0;
        $total_weight = 0;

        if ($this->formData->has_shipping_method('free_shipping') || $this->data['free']) {
            $free_shipping = 1;
        }

        if ($this->formData->has_shipping_method('local_pickup') || $this->formData->has_shipping_method('local_pickup_noest') || $this->data['shipping_type'] === 'stopdesk') {
            $stop_desk = 1;
        }

        $address = $this->data['address'] ?? $params['billing']['address_1'];

        if (!$address) {
            $address = $this->data['commune'] ?? $params['billing']['city'];
        }

        $canOpen = false;

        if (isset($this->data['open_parcel'])) {
            $canOpen = $this->data['open_parcel'];
        }

        $fragile = false;

        if (isset($this->data['is_fragile'])) {
            $fragile = $this->data['is_fragile'];
        }

        if ($this->data['remarque']) {
            $note = $this->data['remarque'];
        } else {
            $note = stripslashes(get_option('wc_bordreau_default-shipping-note'));
        }


	    if (isset($this->data['poids']) && $this->data['poids']) {
		    $total_weight = $this->data['poids'];
	    } else {
		    if ($overweightCase == 'recalculate-with-overweight') {
			    $total_weight = Helpers::get_product_weight($this->formData);
		    }

		    if ($total_weight == 0) {
			    $total_weight = 1;
			    if (isset($this->data['weight']) && $this->data['weight'] > 1) {
				    $total_weight = $this->data['weight'];
			    }
		    }
	    }


	    $orderNumber = $this->data['orderNumber'] ?? $orderId;
	    $orderNumber = str_pad($orderNumber, 6, "0");

        $data = [
            "api_token" => $this->api_token,
            "user_guid" => $this->user_guid,
            "reference" => $orderNumber,
            "client" => $this->data['fname'] . " " . $this->data['lname'] ?? $params['billing']['last_name'] . ' ' .  $params['billing']['first_name'],
            "phone" => $phone,
            "phone_2" => $this->data['phone_2'] ?? $phone2,
            "adresse" => $address,
            "commune" => $this->data['commune'] ?? $params['billing']['city'],
            "wilaya_id" => $wilayaId,
            "produit" => $productString,
            "montant" => $this->data['price'] ?? $params['total'],
            "remarque" => $note,
            "poids" => $total_weight,
            "fragile" => $fragile  ? 1 : 0,
            "can_open" => $canOpen ? 1 : 0,
            "stop_desk" => $stop_desk, // [ 0 = a domicile , 1 = STOP DESK ]
            "type_id" => $this->data['operation_type'] ?? 1, //  [ 1 = Livraison , 2 = Echange , 3 = PICKUP , 4 = Recouvrement ]
        ];


        if ($stop_desk) {
            $selected_value = get_post_meta($this->formData->get_id(), '_billing_center_id', true);
            $data['station_code'] = $this->data['center'] ?? $selected_value;
        }
        // stock if so stock = 1
        // quantite = Les quantités de chaque produit séparé par une virgule

        if (get_option('nord_ouest_avec_stock') === 'with-stock') {

            $data['stock'] = 1;
            $qty = [];
            $products = $params['line_items'];
            foreach ($products as $k => $p) {
                $qty[$k] = $p['quantity'];
            }

            $data['quantite'] = implode(",", $qty);
        }

        $url = $this->provider['api_url']. "/create/order";

        if (isset($this->data['is_update'])) {
            $data['tracking'] = $this->data['tracking_number'];
            $data['product'] = $productString;
	        unset($data['poids']);
            $url = $this->provider['api_url']. "/update/order";
        }



        if (isset($this->data['product_exchanged'])) {
            $data['product_exchanged'] = $this->data['product_exchanged'];
        }


        // Use the NoestRequest class for API requests
        $response = $this->request->post($url, $data);

        // check if the there is no error
        if (isset($response['error'])) {
            new WP_Error($response['error']['message']);

            wp_send_json([
                'error' => true,
                'message'=> __("An error has occurred. Please see log file ", 'wc-bordereau-generator')
            ], 401);
        } elseif (isset($response['success']) && $response['success'] === false) {
            new WP_Error($response['message']);

            wp_send_json([
                'error' => true,
                'message'=> $response['message']
            ], 401);
        } elseif (isset($response['errors'])) {
            new WP_Error(json_encode($response['errors']));

            wp_send_json([
                'error' => true,
                'message'=> __(json_encode($response['errors']), 'wc-bordereau-generator')
            ], 401);
        }

        if (isset($this->data['is_update'])) {
            $response['tracking'] = $this->data['tracking_number'];
        }

        return $response;
    }



    /**
     * Save the response to the postmeta
     * @param $post_id
     * @param array $response
     * @since 1.0.0
     * @throws Exception
     */
    public function save($post_id, array $response)
    {

	    $tracking = wc_clean($response['tracking']);
	    $track = 'https://suivi.ecotrack.dz/suivi/' . $tracking;
	    $etq = 'https://app.noest-dz.com/api/public/get/order/label?api_token='.$this->api_token.'&tracking='.$tracking;

	    update_post_meta($post_id, '_shipping_tracking_number', wc_clean($response['tracking']));
        update_post_meta($post_id, '_shipping_tracking_label', $etq);
        update_post_meta($post_id, '_shipping_label', $etq);
        update_post_meta($post_id, '_shipping_tracking_url', $track);
        update_post_meta($post_id, '_shipping_tracking_method', $this->provider['slug']);

        $order = wc_get_order($post_id);

        if($order) {
            $order->update_meta_data('_shipping_tracking_number', wc_clean($response['tracking']));
            $order->update_meta_data('_shipping_tracking_label', $etq);
            $order->update_meta_data('_shipping_label', $etq);
            $order->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
            $order->save();

            Helpers::make_note($order, $response['tracking'], $this->provider['name']);

        }

        $current_tracking_data = [
            'tracking_number' => wc_clean($response['tracking']),
            'carrier_slug'    => $this->provider['slug'],
            'carrier_url'     => $track,
            "carrier_name"    => $this->provider['name'],
            "carrier_type"    => "custom-carrier",
            "time"            => time()
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

	    $nonce = wp_create_nonce('wp_rest');
	    $etq =  add_query_arg('_wpnonce', $nonce, $etq);

	    wp_send_json([
            'message' => __("Tracking number has been successfully created", 'wc-bordereau-generator'),
            'provider' => $this->provider['name'],
            'tracking' => $current_tracking_data,
            'label' => $etq
        ], 200);
        return ;
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
        $url = $this->provider['api_url'] . "/get/trackings/info";

        $data = [
            'trackings' => [ $tracking_number ],
            'api_token' => $this->api_token,
            'user_guid' => $this->user_guid
        ];

        // Use the NoestRequest class for API requests
        $response = $this->request->post($url, $data);

        if (is_array($response)) {
            wp_send_json([
                'tracking' => $tracking_number,
                'events' => Helpers::sort_tracking($this->format_tracking($response[$tracking_number]))
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
     * @return array
     */
    public function track_detail($tracking_number)
    {
        $url = $this->provider['api_url'] . "/get/trackings/info";

        $data = [
            'trackings' => [ $tracking_number ],
            'api_token' => $this->api_token,
            'user_guid' => $this->user_guid
        ];

        // Use the NoestRequest class for API requests
        $response = $this->request->post($url, $data);

        return $this->format_tracking($response[$tracking_number]);
    }


    /**
     * @param int $wilaya_id
     * @param bool $hasStopDesk
     *
     * @return array
     */
    public function get_communes(int $wilaya_id, bool $hasStopDesk)
    {

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

        return [];
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
            if (isset($response_array['activity'])) {
                foreach ($response_array['activity'] as $key => $item) {
                    if ($item['event_key'] === 'attempt_delivery') {
                        $result[$key]['failed'] = true;
                    }

                    if ($item['event_key'] === 'livred') {
                        $result[$key]['delivered'] = true;
                        if (isset($result[$key]['failed'])) {
                            $result[$key]['failed'] = false;
                        }
                    }

                    $result[$key]['date'] = $item['date'] . " " . $item['time'];
                    $result[$key]['location'] = $item['scanLocation'];
                    $result[$key]['status'] = $status[$item['event_key']] ?? $item['eventd'];
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

        $detail = [];
        $status = $item['activity'];
        if (is_array($status)) {
            usort($status, function ($a, $b) {
                return $b['date'] <=> $a['date'];
            });
        }

        $item = $item['OrderInfo'];

        $detail[0]['address'] = $item['adresse'];
        $detail[0]['phone'] = $item['phone'];
        $detail[0]['phone_2'] = $item['phone_2'];
        $detail[0]['remarque'] = $item['remarque'];
        $detail[0]['fname'] = $item['client'];
        $detail[0]['free'] = false;
        $detail[0]['exchange'] = $item['type_id'] == 2;
        $detail[0]['stopdesk'] = false;
        $detail[0]['last_status'] = $status[0]['event'];
        $detail[0]['order_id'] = $item['reference'];
        $detail[0]['price'] = $item['montant'];
        $detail[0]['commune'] = $item['commune'];
        $detail[0]['wilaya'] = $item['wilaya_id'];

        return  $detail;
    }

    function sortByOrder($a, $b)
    {
        $t1 = strtotime($a['date']);
        $t2 = strtotime($b['date']);
        return $t2 - $t1 ;
    }

    /**
     * @return array
     */
    public function get_wilayas(): array
    {

        $path = Functions::get_path('nordouest_'.WC_BORDEREAU_GENERATOR_VERSION.'_wilaya.json');

        if (! file_exists($path)) {
            // Use the NoestRequest class for API requests
            $url = 'https://amineware.me/api/wilaya-noest';
            $content = $this->request->get($url, false);
            file_put_contents($path, json_encode($content));
        }

        $wilayas = json_decode(file_get_contents($path), true);

        if ($wilayas == null || count($wilayas) == 0) {
            return $this->get_wilaya_from_provider();
        }

        return  $wilayas;
    }


    /**
     * @return bool|mixed|string
     */
    private function get_wilaya_from_provider()
    {
        // Use the Noest API for wilayas
        $url = $this->provider['api_url'] . '/get/wilayas?api_token=' . $this->api_token . '&user_guid=' . $this->user_guid;
        return $this->request->get($url, false);
    }


    /**
     * @param $tracking_number
     * @param $post_id
     * @return void
     * @since 1.2.0
     */
    public function detail($tracking_number, $post_id)
    {
        $url = $this->provider['api_url'] . "/get/trackings/info";

        $data = [
            'trackings' => [ $tracking_number ],
            'api_token' => $this->api_token,
            'user_guid' => $this->user_guid
        ];

        // Use the NoestRequest class for API requests
        $response = $this->request->post($url, $data);

        if (is_array($response)) {
            $details = $this->format_tracking_detail($response[$tracking_number]);

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
     * @param $provider
     *
     * @return array
     */
    public function get_wilayas_by_provider($provider)
    {
        $path = Functions::get_path('nordouest_'.WC_BORDEREAU_GENERATOR_VERSION.'_wilaya.json');

        if (! file_exists($path)) {
            // Use the NoestRequest class for API requests
            $url = 'https://amineware.me/api/wilaya-noest';
            $content = $this->request->get($url, false);
            file_put_contents($path, json_encode($content));
        }

        $wilayas = json_decode(file_get_contents($path), true);

        $wilayasResult = [];

        foreach ($wilayas as $i => $wilaya) {
            $wilayasResult[ $i ]['name'] = $wilaya['name'];
            $wilayasResult[ $i ]['id']   = $wilaya['id'];
        }

        return $wilayasResult;
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

        $fields = $provider['fields'];
        $key = $fields['total']['choices'][0]['name'] ?? null;
        $overweight = $fields['overweight']['choices'][0]['name'] ?? null;
        $canOpen = $fields['can_open']['choices'][0]['name'] ?? null;
	    $overweight_checkout = $fields['overweight_checkout']['choices'][0]['name'] ?? null; // the fee is in the checkout


	    $overweightCase = get_option($overweight) ? get_option($overweight) : 'recalculate-without-overweight';
        $canOpenCase = get_option($canOpen) ? get_option($canOpen) : 'false';
	    $overweightCheckoutCase = get_option($overweight_checkout) ? get_option($overweight_checkout) : 'recalculate-without-overweight';

	    $params = $order->get_data();
        $products = $params['line_items'];

        $productString = Helpers::generate_products_string($products);

        // check if the name is an agence check in the centers list
        $AgencyName = $order->get_shipping_method();
        $centers = $this->get_centers($order->get_billing_state());
        $center = $order->get_meta('_billing_center_id', true);

        $found = array_filter($centers, function ($v, $k) use ($AgencyName) {
            return strtolower($v['name']) == strtolower(urldecode($AgencyName));
        }, ARRAY_FILTER_USE_BOTH);

        if ($found) {
            $center = reset($found);
        }

        if (is_array($center)) {
            $center = $center['id'];
        }

        $total = get_option($key) ? get_option($key) : 'with-shipping';

        $cart_value = 0;

        if ($total == 'with-shipping') {
            $cart_value = (float) $order->get_total();
        } elseif ($total === 'without-shipping') {
            $cart_value =  (float) $order->get_total() - $order->get_total_shipping();
        }


        $shippingOverweightPrice = 0;
        $shippingOverweight = 0;

        $total_weight = Helpers::get_product_weight($order);

        // check if the option of extra weight
        if ($overweightCase == 'recalculate-with-overweight') {

            if ($total_weight > $shippingOverweight) {
                $shippingOverweight = $total_weight;
            }

	        if ($overweightCheckoutCase == 'recalculate-with-overweight') {

		        if ( $shippingOverweight > 5 ) {
			        $zonePrice = 40;

			        $shippingOverweightPrice = max( 0, ceil( $shippingOverweight - 5 ) ) * $zonePrice;
//			        $cart_value              = $cart_value + $shippingOverweightPrice;
		        }
	        }
        }

        $can_open = $canOpenCase == 'true';

        $data = [];
        if ($key) {
            $data['total_calculate'] = get_option($key) ? get_option($key) : 'with-shipping';
            $data['type'] = $provider['type'];
            $data['total'] = $cart_value;
            $data['order_number'] = $order->get_order_number();
            $data['products_string'] = $productString;
            $data['total_weight'] = $total_weight;
            $data['total_without_shipping'] = $total_weight;
            $data['zone_fee'] = 40;
            $data['center_name'] = $center ?? null;
            $data['can_open'] = $can_open;
            $data['shipping_overweight_price'] = $shippingOverweightPrice;

        }
        return $data;
    }

    /**
     * @param $tracking_number
     * @param $post_id
     * @return void
     * @since 1.3.0
     */
    public function delete($tracking_number, $post_id)
    {

        // delete the package if exist
        $url = $this->provider['api_url'] . "/delete/order";

        $data = [
            'tracking' => $tracking_number,
            'api_token' => $this->api_token,
            'user_guid' => $this->user_guid
        ];

        // Use the NoestRequest class for API requests
        $response = $this->request->post($url, $data);

        return $response;
    }

    /**
     * @since 2.1.12
     * @return void
     */
    public function get_slip()
    {

        $tracking_number = get_post_meta($this->formData->get_id(), '_shipping_tracking_number', true);
        if(! $tracking_number) {
            $tracking_number = $this->formData->get_meta('_shipping_tracking_number');
        }

        header('Location: https://app.noest-dz.com/api/public/get/order/label?api_token='.$this->api_token.'&tracking='.$tracking_number);
        exit;
    }

	public function get_centers($wilaya)
	{
		$path = Functions::get_path($this->provider['slug'].'_centers_'.WC_BORDEREAU_GENERATOR_VERSION.'_'.date('Y').'_'. (date('z') + 1).'.json');

		if (! file_exists($path) || file_get_contents($path) == "null") {
			if(empty($this->api_token) && empty($this->user_guid)) {
				return [];
			}

			$url = $this->provider['api_url'] . '/desks?api_token=' . $this->api_token . '&user_guid=' .$this->user_guid;
			// Use the NoestRequest class for API requests
			$response = $this->request->get($url, false);
			file_put_contents($path, json_encode($response));
		}

		$json = json_decode(file_get_contents($path), true);
		$communesResult = [];
		if(is_array($json)) {

			$found = array_filter($json, function ($v, $k) use ($wilaya) {
				$id = (int) preg_replace('/([0-9]+)\w/', '$1', $v['code']);
				return $id == $wilaya;
			}, ARRAY_FILTER_USE_BOTH);

			foreach ($found as $i => $item) {
				$communesResult[$i]['id'] = $item['code'];
				$communesResult[$i]['name'] = $item['name'];
			}
		}




		return $communesResult;

	}
}
