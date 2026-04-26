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

class ConexlogShipping extends \WooBordereauGenerator\Admin\Shipping\BordereauProvider implements \WooBordereauGenerator\Admin\Shipping\BordereauProviderInterface
{

    /**
     * @var
     */
    private $api_token;

    /**
     * @var
     */
    private $company_name;

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
     * @param WC_Order $formData
     * @param array $provider
     * @param array $data
     */
    public function __construct(WC_Order $formData, array $provider, array $data = [])
    {

        $this->formData     = $formData;
        $this->api_token    = get_option($provider['slug'].'_api_token');
        $this->provider     = $provider;
        $this->data     = $data;
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
                'message'=> __("Wilaya has not been selected", 'woo-bordereau-generator')
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

        if ($this->formData->has_shipping_method('free_shipping') || $this->data['free']) {
            $free_shipping = 1;
        }

        if ($this->formData->has_shipping_method('local_pickup') || $this->data['shipping_type'] === 'stopdesk') {
            $stop_desk = 1;
        }

        $address = $this->data['address'] ?? $params['billing']['address_1'];

        if ($stop_desk) {
            $address = $this->data['commune'] ?? $params['billing']['city'];
        }


        $lname = $this->data['lname'] ?? $params['billing']['last_name'];
        $fname = $this->data['fname'] ?? $params['billing']['first_name'];

        if (!$lname) {
            $fnameArray = explode(' ', trim($fname));
            if (count($fnameArray) > 1) {
                $lname = $fnameArray[1];
                $fname = $fnameArray[0];
            } else {
                $lname = $this->data['fname'] ?? $params['billing']['first_name'];
            }
        }

        if ($this->data['remarque']) {
            $note = $this->data['remarque'];
        } else {
            $note = stripslashes(get_option('wc_bordreau_default-shipping-note'));
        }

        $data = [
            "reference" => $this->data['orderNumber'] ?? $orderId,
            "nom_client" => $fname . ' ' . $lname,
            "telephone" => $phone,
            "telephone_2" => $phone2,
            "adresse" => $address,
            "commune" => str_replace('\\', '', $this->data['commune']) ?? str_replace('\\', '', $params['billing']['city']),
            "code_wilaya" => $wilayaId,
            "produit" => $productString,
            "montant" => $this->data['price'] ?? $params['total'],
            "boutique" => get_the_title(get_option('woocommerce_shop_page_id')),
            "remarque" => $note,
            "stop_desk" => $stop_desk, // [ 0 = a domicile , 1 = STOP DESK ]
            "type" => $this->data['operation_type'] ?? 1, //  [ 1 = Livraison , 2 = Echange , 3 = PICKUP , 4 = Recouvrement ]
        ];


        $fragile = $this->data['is_fragile'] === 'true' ?? false;
        $canOpen = $this->data['open_parcel'] === 'true' ?? false;
        if ($fragile) {
            $data['fragile'] = $this->data['is_fragile'] === 'true' ?? false;
        }
        if ($canOpen) {
            $data['can_open'] = $this->data['open_parcel'] === 'true' ?? false;
        }
        // $data['gps_link'] = 'https://goo.gl/maps/wpeVpf4U6Q7qbwBb6';

        if (isset($this->data['is_update'])) {
            $url = $this->provider['api_url']. "/update/order?api_token=". $this->api_token;
            $data = $data + ['tracking' => $this->data['tracking_number']];
        } else {
            $url = $this->provider['api_url'] . "/create/order?api_token=". $this->api_token;
        }

        if (isset($this->data['product_exchanged'])) {
            $data = $data + ['produit_a_recupere' => $this->data['product_exchanged']];
        }

        $request = wp_safe_remote_post(
            $url,
            [
                'headers'     => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => "Bearer ".$this->api_token
                ],
                'body'        => wp_json_encode($data),
                'method'      => 'POST',
                'data_format' => 'body',
            ]
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

        $response = json_decode($body, true);


        if (empty($response) || ! is_array($response)) {
            wp_send_json([
                'error' => true,
                'message'=> __("An error has occurred. Please see log file ", 'woo-bordereau-generator')
            ], 401);
        }


        if (isset($response['error'])) {
            new WP_Error($response['error']['message']);

            wp_send_json([
                'error' => true,
                'message'=> __("An error has occurred. Please see log file ", 'woo-bordereau-generator')
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
                'message'=> __(json_encode($response['errors']), 'woo-bordereau-generator')
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
        $etq = get_rest_url(null, 'woo-bordereau/v1/slip/print/'.$this->provider['slug'].'/'.$post_id);

        $etqNew = $this->provider['api_url']. '/get/order/label/?api_token=' . $this->api_token . '&tracking=' .$response['tracking'];

        $track = '';

        update_post_meta($post_id, '_shipping_tracking_number', wc_clean($response['tracking']));
        update_post_meta($post_id, '_shipping_tracking_label', $etqNew);
        update_post_meta($post_id, '_shipping_label', $etqNew);
        update_post_meta($post_id, '_shipping_tracking_url', $track);
        update_post_meta($post_id, '_shipping_tracking_method', $this->provider['slug']);

        $order = wc_get_order($post_id);

        if($order) {
            $order->update_meta_data('_shipping_tracking_number', wc_clean($response['tracking']));
            $order->update_meta_data('_shipping_tracking_label', $etqNew);
            $order->update_meta_data('_shipping_label', $etqNew);
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
            'message' => __("Tracking number has been successfully created", 'woo-bordereau-generator'),
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

        $url = $this->provider['api_url']. "/get/tracking/info?api_token={$this->api_token}&tracking={$tracking_number}";

        $request = wp_safe_remote_get(
            $url,
            array(
                'headers'     => array(
                    'Content-Type' => 'application/json; charset=utf-8',
                    'Authorization'      => "Bearer ".$this->api_token,
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

        $response = json_decode($body, true);


        if (empty($response) || ! is_array($response)) {
            wp_send_json([
                'error' => true,
                'message'=> __("An error has occurred. Please see log file ", 'woo-bordereau-generator')
            ], 401);
        }


        if (isset($response['error'])) {
            new WP_Error($response['error']['message']);

            wp_send_json([
                'error' => true,
                'message'=> __("An error has occurred. Please see log file ", 'woo-bordereau-generator')
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
                'message'=> __(json_encode($response['errors']), 'woo-bordereau-generator')
            ], 401);
        }


        if (is_array($response)) {
            wp_send_json([
                'tracking' => $tracking_number,
                'events' => Helpers::sort_tracking($this->format_tracking($response))
            ]);
        }

        wp_send_json([
            'tracking' => $tracking_number,
            'events' => []
        ]);
    }

    /**
     * Get the trackinng information for the order
     *
     * @param string $tracking_number
     * @return array
     */
    public function track_detail($tracking_number)
    {
        try {
            $url = $this->provider['api_url']. "/get/tracking/info?tracking={$tracking_number}&api_token={$this->api_token}";

            $request = wp_safe_remote_get(
                $url,
                array(
                    'headers'     => array(
                        'Content-Type' => 'application/json; charset=utf-8',
                    ),
                ),
            );

            $body = wp_remote_retrieve_body($request);
            $response = json_decode($body, true);

            return $this->format_tracking($response);

        } catch (\ErrorException $exception) {
            return [];
        }
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


        $found = array_filter($communes, function ($v, $k) use ($wilaya_id) {
            return (int) $v['wilaya_id'] == $wilaya_id;
        }, ARRAY_FILTER_USE_BOTH);


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
                $result[$key]['location'] = $item['scanLocation'] ?? '';
                $result[$key]['status'] = $status[$item['status']] ?? $item['status'];
            }
            usort($result, [$this, 'sortByOrder']);
        }


        $last_status = $result[0]['status'] ?? '';

        if ($last_status) {
            BordereauGeneratorAdmin::update_tracking_status($this->formData, $last_status, 'single_tracking');
        }

        return $result;
    }


    /**
     * Format the tracking responses
     * @param array $item
     * @return array
     */
    private function format_tracking_detail(array $item)
    {

        $detail = [];
        $item = $item['data'];

        $detail[0]['address'] = $item[0]['adresse'];
        $detail[0]['phone'] = $item[0]['phone'];
        $detail[0]['fname'] = $item[0]['client'];
        $detail[0]['free'] = false;
        $detail[0]['exchange'] = $item[0]['type_id'] == 2;
        $detail[0]['stopdesk'] = false;
        $detail[0]['last_status'] = $this->status()[$item[0]['status']] ?? $item[0]['status'];
        $detail[0]['last_status_key'] = $item[0]['status'];
        $detail[0]['order_id'] = $item[0]['reference'];
        $detail[0]['price'] = $item[0]['montant'];
        $detail[0]['commune'] = $item[0]['commune'];
        $detail[0]['wilaya'] = $item[0]['wilaya_id'];



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

        $path = Functions::get_path('ecotrack_'.$this->provider['slug'].'_wilaya.json');

        if (file_exists($path)) {
            $wilayas = json_decode(file_get_contents($path), true);

            if ($wilayas == null || count($wilayas) == 0) {
                return $this->get_wilaya_from_provider();
            }
        } else {
            $response = $this->get_wilaya_from_provider();
            file_put_contents($path, $response);
            return json_decode($response, true);
        }

        return  $wilayas;
    }


    /**
     * Get Wilaya from the provider
     * @since 1.0.0
     * @return mixed
     */
    private function get_wilaya_from_provider()
    {

        if ($this->api_token) {

            $request = wp_safe_remote_get(
                $this->provider['api_url'] . '/get/wilayas?api_token='.$this->api_token,
                array(
                    'timeout'     => 45,
                    'headers'     => array(
                        'Content-Type' => 'application/json; charset=utf-8',
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

            return json_decode($body, true);
        }

        wp_send_json([
            'error' => true,
            'message'=> __("There is no token has been set", 'woo-bordereau-generator')
        ], 401);
    }


    /**
     * @param $tracking_number
     * @param $post_id
     * @return void
     * @since 1.2.0
     */
    public function detail($tracking_number, $post_id)
    {

        if ($this->api_token) {
            $url = $this->provider['api_url'] . "/get/orders?tracking=" . $tracking_number. "&api_token=".$this->api_token;

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
                    'message' => __("An error has occurred. Please see log file ", 'woo-bordereau-generator')
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

            $details = $this->format_tracking_detail($response);

            if (isset($details[0]['last_status'])) {
                BordereauGeneratorAdmin::update_tracking_status($this->formData, $details[0]['last_status'], 'single_tracking');
            }


            wp_send_json([
                'tracking' => $tracking_number,
                'detail' => $details
            ]);
        } else {
            wp_send_json([
                'error' => true,
                'message'=> __("There is no token has been set", 'woo-bordereau-generator')
            ], 401);
        }


    }

    private function status()
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
     * @param $provider
     *
     * @return array
     */
    public function get_wilayas_by_provider($provider)
    {

        $path = Functions::get_path('ecotrack_' . $provider . '_wilaya.json');

        if (file_exists($path)) {
            $result = json_decode(file_get_contents($path), true);

            if ($result == null || count($result) == 0) {
                $result = $this->get_wilaya_from_provider();
            }
        } else {
            $response = $this->get_wilaya_from_provider();
            file_put_contents($path, $response);
            if (is_string($response)) {
                $result = json_decode($response, true);
            } elseif (is_array($response)) {
                $result = $response;
            } else {
                $result = [];
            }
        }

        if ($result) {
            foreach ($result as $i => $wilaya) {
                $wilayasResult[ $i ]['name'] = $wilaya['wilaya_name'];
                $wilayasResult[ $i ]['id']   = $wilaya['wilaya_id'];
            }

            return $wilayasResult;
        }

        return [];

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
     * @param $tracking_number
     * @since 1.3.0
     * @return void
     */
    public function delete($tracking_number, $post_id)
    {

        $curl = curl_init();

        $url = $this->provider['api_url'] . "/delete/order?tracking=". $tracking_number. "&api_token=".$this->api_token;

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
        ));

        $authorization = "Authorization: Bearer ".$this->api_token; // Prepare the authorisation token
        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json' , $authorization )); // Inject the token into the header

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);

        $response_json = curl_exec($curl);
        $response_array = json_decode($response_json, true);

        curl_close($curl);

        if (isset($response_array['error'])) {
            wp_send_json([
                'error' => true,
                'message'=>  $response_array['error']['message']
            ], 401);
        } elseif (isset($response_array['errors'])) {
            wp_send_json([
                'error' => true,
                'message'=>  $response_array['message']
            ], 401);
        }

        return json_decode($response_json, true);
    }

    /**
     * @since 2.2.0
     * @return void
     */
    public function get_slip()
    {
        $tracking_number = get_post_meta($this->formData->get_id(), '_shipping_tracking_number', true);

        if(!$tracking_number) {
            $tracking_number = $this->formData->get_meta('_shipping_tracking_number');
        }

        header('Location: '. $this->provider['api_url']. '/get/order/label/?api_token=' . $this->api_token . '&tracking=' .$tracking_number);
        exit;
    }
}
