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


use Exception;
use WC_Order;
use WC_Product;
use WooBordereauGenerator\Functions;
use WooBordereauGenerator\Admin\BordereauGeneratorAdmin;
use WooBordereauGenerator\Helpers;
use WP_Error;

class ColivraisonExpress extends \WooBordereauGenerator\Admin\Shipping\BordereauProvider implements \WooBordereauGenerator\Admin\Shipping\BordereauProviderInterface
{

    /**
     * @var
     */
    private $public_key;


    private $access_token;


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

        $this->formData = $formData;
        $this->public_key = get_option($provider['slug'].'_public_key');
        $this->access_token = get_option($provider['slug'].'_access_token');
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
        $orderId = $this->formData->get_order_number();

        // Prepare customer information
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

        // Handle phone numbers
        $phone = $this->data['phone'] ?? $params['billing']['phone'];
        $phone = str_replace(' ', '', str_replace('+213', '0', $phone));
        $phones = explode('/', $phone);
        $phone2 = count($phones) > 1 ? $phones[1] : '';
        $phone = $phones[0];

        // Handle address and shipping
        $address = $this->data['address'] ?? $params['billing']['address_1'];
        if (!$address) {
            $address = $this->data['commune'] ?? $params['billing']['city'];
        }

        $stop_desk = $this->formData->has_shipping_method('local_pickup') ||
            ($this->data['shipping_type'] ?? '') === 'stopdesk';


        if (isset($this->data['products'])) {
            $productString = $this->data['products'];
        } else {
            $products = $params['line_items'];
            $productString = Helpers::generate_products_string($products);

        }
        $orderItems = [];

        $wilayaID = $this->data['wilaya'] ?? $params['billing']['state'];
        $wilayaID = (int) str_replace('DZ-', '', $wilayaID);
        $wilayaName = $this->get_wilayas()['wilayas'][$wilayaID];


        $orderItems[] = [
            'client_name' => $fname . ' ' . $lname,
            'wilaya_name' => $wilayaName,
            'order_note' => $this->data['remarque'] ?? stripslashes(get_option('wc_bordreau_default-shipping-note')),
            'phone_st' => $phone,
            'phone_sc' => $phone2,
            'order_adress' => $address,
            'commune_name' => $this->data['commune'] ?? $params['billing']['city'],
            'product_name' => $productString,
            'qte' => 1,
            'product_price' => $this->data['price'] ?? $params['total'],
            'stopdesk_status' => $stop_desk,
            'order_ref' => $orderId,
            'order_brittle' => isset($this->data['is_fragile']) && $this->data['is_fragile'] == "true",
            'order_exchange' => ($this->data['operation_type'] ?? 1) == 2,
            'order_refund' => false,
        ];

        $requestData = [
            'orders' => $orderItems
        ];

        $response = $this->sendRequest('orders/bulk', $requestData);

        if (!$response || isset($response['error'])) {
            wp_send_json([
                'error' => true,
                'message' => __("An error has occurred. Please see log file", 'woo-bordereau-generator')
            ], 401);
        }

        return $response;
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
            // get the tracking

            if (isset($response['messages'][0]['tracking'])) {

                $trackingNumber = $response['messages'][0]['tracking'];

                update_post_meta($post_id, '_shipping_tracking_number', wc_clean($trackingNumber));
                update_post_meta($post_id, '_shipping_tracking_label',  '');
                update_post_meta($post_id, '_shipping_label',  $etq);
                update_post_meta($post_id, '_shipping_tracking_label_method', 'POST');
                update_post_meta($post_id, '_shipping_tracking_url', '');
                update_post_meta($post_id, '_shipping_tracking_method', $this->provider['slug']);

                $order = wc_get_order($post_id);

                if($order) {
                    $order->update_meta_data('_shipping_tracking_number',wc_clean($trackingNumber));
                    $order->update_meta_data('_shipping_tracking_label', '');
                    $order->update_meta_data('_shipping_label', $etq);
                    $order->update_meta_data('_shipping_tracking_label_method', 'POST');
                    $order->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
                    $order->save();

                    Helpers::make_note($order, wc_clean($trackingNumber), $this->provider['name']);

                }

                $current_tracking_data = [
                    'tracking_number' => wc_clean($trackingNumber),
                    'carrier_slug' => $this->provider['slug'],
                    'carrier_url' => '',
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

	            $nonce = wp_create_nonce('wp_rest');
	            $etq =  add_query_arg('_wpnonce', $nonce, $etq);

	            wp_send_json([
                    'message' => __("Tracking number has been successfully created", 'woo-bordereau-generator'),
                    'provider' => $this->provider['name'],
                    'tracking' => $current_tracking_data,
                    'label' => $etq
                ], 200);
            }



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
     * @param string $tracking_number
     * @return void
     */
    public function track($tracking_number, $post_id = false)
    {
        $url = "trackinghistory/bulk";
        $response = $this->sendRequest($url, [
            'tracking_numbers' => [$tracking_number]
        ]);

        if (is_array($response)) {
            $events = Helpers::sort_tracking($this->format_tracking($response[$tracking_number]));
            if (count($events)) {
                if (isset($events[0]['status'])) {
                    BordereauGeneratorAdmin::update_tracking_status($this->formData, $events[0]['status'], 'single_tracking');
                }
            }

            wp_send_json([
                'tracking' => $tracking_number,
                'events' => $events
            ]);
        }

        wp_send_json([
            'tracking' => $tracking_number,
            'events' => []
        ]);
    }


    private function reset_meta($post_id) {

        delete_post_meta($post_id, '_shipping_tracking_number');
        delete_post_meta($post_id, '_shipping_tracking_label');
        delete_post_meta($post_id, '_shipping_label');
        delete_post_meta($post_id, '_shipping_tracking_label_method');
        delete_post_meta($post_id, '_shipping_tracking_url');

        $order = wc_get_order($post_id);

        if($order) {
            $order->delete_meta_data('_shipping_tracking_number');
            $order->delete_meta_data('_shipping_tracking_label');
            $order->delete_meta_data('_shipping_label');
            $order->delete_meta_data('_shipping_tracking_label_method');
            $order->delete_meta_data('_shipping_tracking_method');
            $order->save();
        }
    }

    /**
     * Get the tracking information for the order
     *
     * @param string $tracking_number
     * @return void
     */
    public function track_detail($tracking_number)
    {

//        $url = $this->provider['api_url'] . "tracking/$tracking_number/events";
//
//        return $this->format_tracking($response_array);
    }


    /**
     * @param $wilaya_id
     * @param bool $hasStopDesk
     *
     * @return array
     */
    public function get_communes($wilaya_id, bool $hasStopDesk)
    {

        $path = __DIR__. '/data/colivraison_cities.json';

        $communesArray = json_decode(file_get_contents($path), true);


        $communes = array_filter($communesArray, function($item) use ($wilaya_id) {
            return $item['wilaya_mat'] == $wilaya_id;
        });

        $communesResult = [];

        foreach ($communes as $i => $item) {
            $communesResult[$i]['id'] = $item['nom'];
            $communesResult[$i]['name'] = $item['nom'];
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
        $result = [];
        if (is_array($response_array)) {
            foreach ($response_array as $key => $item) {
                $result[$key]['date'] = $item['date'];
                $result[$key]['status'] = $item['status'];
            }
            usort($result, [$this, 'sortByOrder']);
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

        $path = __DIR__. '/data/colivraison_wilaya.json';

        return json_decode(file_get_contents($path), true);
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
        $order_id = get_post_meta($post_id, '_shipping_3mexpress_order_id', true);

        $url = $this->provider['api_url'] . '/v1/orders?skip=0&take=100&id='. $order_id;
        $request = new \WooBordereauGenerator\Admin\Shipping\ThreeMExpressRequest($this->provider);

        $content = $request->get($url, false);

        $details = $this->format_tracking_detail($content);

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


    /**
     * @param $provider
     *
     * @return array
     */
    public function get_wilayas_by_provider($provider): array
    {
        $result = $this->get_wilaya_from_provider();

        foreach ($result["wilayas"] as $i => $wilaya) {
            $wilayasResult[$i]['name'] = $wilaya;
            $wilayasResult[$i]['id'] = $i;
        }

        usort($wilayasResult, function($a, $b) {
            return $a['id'] <=> $b['id'];
        });

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

        if ($tracking_number) {

            $curl = curl_init();

            $data = [
                'orders' => [$tracking_number]
            ];

            $url = $this->provider['api_url'] . "/customers/orders/multiple";


        }

        $this->reset_meta($post_id);
        return null;
    }


    /**
     * @since 2.2.0
     * @return void
     */
    public function get_slip()
    {
        $trackingNumber = get_post_meta($this->formData->get_id(), '_shipping_tracking_number', true);

        $data = [
            'order_ids' => [$trackingNumber]
        ];
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.colivraison.express/v2/generate-download-pdfx1',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array(
                'coliv-public-key: ' . $this->public_key,
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->access_token,
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        header("Content-type:application/pdf");
        echo $response;
        exit();
    }

    private function sendRequest($endpoint, $data)
    {
        $url = $this->provider['api_url'] . $endpoint;

        $request = wp_safe_remote_post(
            $url,
            [
                'headers' => [
                    'Authorization' => "Bearer " . $this->access_token,
                    'coliv-public-key' => $this->public_key,
                    'Content-Type: application/json',
                ],
                'body' => wp_json_encode($data),
                'method' => 'POST',
                'data_format' => 'body',
            ]
        );

        if (is_wp_error($request)) {
            return false;
        }

        $body = wp_remote_retrieve_body($request);
        if (empty($body)) {
            return false;
        }

        return json_decode($body, true);
    }

    private function sendGetRequest(string $endpoint)
    {
        $request = wp_safe_remote_get(
            $this->provider['api_url'] . $endpoint,
            [
                'headers' => [
                    'Authorization' => "Bearer " . $this->access_token,
                    'coliv-public-key' => $this->public_key,
                    'Content-Type: application/json',
                ],
            ]
        );

        if (is_wp_error($request)) {
            return false;
        }

        $body = wp_remote_retrieve_body($request);
        if (empty($body)) {
            return false;
        }

        return json_decode($body, true);
    }


}
