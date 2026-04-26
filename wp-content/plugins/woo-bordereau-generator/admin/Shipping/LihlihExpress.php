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
use WooBordereauGenerator\Functions;
use WooBordereauGenerator\Admin\BordereauGeneratorAdmin;
use WooBordereauGenerator\Helpers;
use WP_Error;

class LihlihExpress extends \WooBordereauGenerator\Admin\Shipping\BordereauProvider implements \WooBordereauGenerator\Admin\Shipping\BordereauProviderInterface
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
        $this->api_token = get_option('lihlihexpress_token');
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

        if (isset($this->data['wilaya'])) {
            $wilayaId = (int) str_replace("DZ-", "", $this->data['wilaya']);
        } else {
            $wilayaId = (int) str_replace("DZ-", "", $params['billing']['state']);
        }

        $commune = $this->data['commune'] ?? $params['billing']['city'];
        $communesArray = $this->get_communes($wilayaId, false);

        $communeFound = array_filter($communesArray, function ($v, $k) use ($commune) {
            return $v['name'] == $commune;
        }, ARRAY_FILTER_USE_BOTH);

        $commune = reset($communeFound);

        if (isset($this->data['products'])) {
            $productString = $this->data['products'];
        } else {
            $products = $params['line_items'];
            $productString = Helpers::generate_products_string($products);
        }

        $free_shipping = 0;
        $stop_desk = 0;

        if ($this->formData->has_shipping_method('local_pickup') && $this->data['shipping_type'] === 'stopdesk') {
            $stop_desk = 1;
        }

        if ($this->data['shipping_type'] === 'stopdesk') {
            $stop_desk = 1;
        }

        if ($this->data['free'] == "true") {
            $free_shipping = 1;
        }
        if ($this->formData->has_shipping_method('free_shipping')) {
            $free_shipping = 1;
        }

        $price = $this->data['price'] ?? $params['total'] - $params['shipping_total'];


        $data = array (
            'product' => $productString,
            'lastname' => !empty($this->data['lname']) ? $this->data['lname'] : $this->data['fname'],
            'firstname' => !empty($this->data['fname']) ? $this->data['fname'] : $this->data['lname'],
            'phone' => $this->data['phone'],
            'address' => $this->data['address'] ?? $params['billing']['address_1'],
            'city' => $commune['id'],
            'wilaya' => $wilayaId,
            'deliveryMethod' => $stop_desk ? 1: 0,
            'price' => $price,
            'freeDelivery' => $free_shipping,
            'exchange' => $this->data['operation_type'] == 2 ? 1 : 0,
        );

        $url = $this->provider['api_url'] . 'packages/create';

        if ($this->data['is_update']) {
            $tracking_number = get_post_meta($this->formData->get_id(), '_shipping_tracking_number', true);
            $url = $this->provider['api_url'] . 'packages/update/' . $tracking_number;
        }

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $this->data['is_update'] ? 'PUT' : 'POST',
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: ' . "Bearer ".$this->api_token
            ),
        ));

        $body = curl_exec($curl);
        curl_close($curl);

        $content = json_decode($body, true);

        // Handle validation errors
        if (isset($content['success']) && $content['success'] === false) {
            $error_messages = [];

            if (isset($content['data'])) {
                foreach ($content['data'] as $field => $errors) {
                    if (is_array($errors) && !empty($errors)) {
                        $error_messages[] = $errors[0];
                    }
                }
            }

            wp_send_json([
                'error' => true,
                'message' => implode(' ', $error_messages)
            ], 422);
        }

        // Handle other errors
        if (isset($content['statusCode']) && $content['statusCode']) {
            wp_send_json([
                'error' => true,
                'message' => $content['message']
            ], 401);
        }

        return $content;
    }


    /**
     * Save the response to the postmeta
     * @param $post_id
     * @param array $response
     * @throws Exception
     * @since 1.0.0
     */
    public function save($post_id, array $response, bool $update = false)
    {
        $order = wc_get_order($post_id);
        $trackingNumber = $response['data']['tracking'];
        $label = $response['data']['tracking']; // Since there's no bordereau in response

        if ($update) {
            $trackingNumber = get_post_meta($post_id, '_shipping_tracking_number', true);
            if (!$trackingNumber) {
                $trackingNumber = $order->get_meta('_shipping_tracking_number');
            }

            $label = get_post_meta($post_id, '_shipping_tracking_label', true);
            if (!$label) {
                $label = $order->get_meta('_shipping_tracking_label');
            }
        } else {
            update_post_meta($post_id, '_shipping_tracking_number', wc_clean($trackingNumber));
            update_post_meta($post_id, '_shipping_tracking_label', wc_clean($label));
            update_post_meta($post_id, '_shipping_label', wc_clean($label));
            update_post_meta($post_id, '_shipping_tracking_url', 'https://' . $this->provider['url'] . '/suivre-un-colis/?tracking=' . wc_clean($trackingNumber));
            update_post_meta($post_id, '_shipping_tracking_method', $this->provider['slug']);

            if ($order) {
                $order->update_meta_data('_shipping_tracking_number', wc_clean($trackingNumber));
                $order->update_meta_data('_shipping_tracking_label', wc_clean($label));
                $order->update_meta_data('_shipping_label', wc_clean($label));
                $order->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
                $order->save();

                Helpers::make_note($order, $trackingNumber, $this->provider['name']);
            }
        }

        $track = $this->provider['url'] . '/suivre-un-colis/?tracking=' . wc_clean($trackingNumber);
        $current_tracking_data = [
            'tracking_number' => wc_clean($trackingNumber),
            'carrier_slug' => $this->provider['slug'],
            'carrier_url' => $track,
            "carrier_name" => $this->provider['name'],
            "carrier_type" => "custom-carrier",
            "time" => time()
        ];

// Check if the woo-order-tracking is enabled
        if (in_array('woo-orders-tracking/woo-orders-tracking.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            $item_tracking_data[] = $current_tracking_data;
            $order = new WC_Order($post_id);
            $order_items = $order->get_items();
            foreach ($order_items as $item) {
                wc_update_order_item_meta($item->get_id(), '_vi_wot_order_item_tracking_data', json_encode($item_tracking_data));
            }
        }

        wp_send_json([
            'message' => __("Tracking number has been successfully created", 'wc-bordereau-generator'),
            'provider' => $this->provider['name'],
            'tracking' => $current_tracking_data,
            'label' => wc_clean($label),
            'delivery_details' => [
                'method' => $response['data']['deliveryMethod'],
                'price' => $response['data']['deliveryPrice'],
                'status' => $response['data']['status'],
                'created_at' => $response['data']['createdAt'],
                'client' => $response['data']['client']
            ]
        ], 200);

    }

    /**
     * Get the trackinng information for the order
     *
     * @param string $tracking_number
     * @return void
     */
    public function track($tracking_number, $post_id = false)
    {

        $url = $this->provider['api_url'] . "packages/history/". $tracking_number;

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded',
                'Authorization: ' . "Bearer ".$this->api_token
            ),
        ));

        $body = curl_exec($curl);
        curl_close($curl);

        $content = json_decode($body, true);

        if (is_array($content)) {

            $events = $this->format_tracking($content);

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
        delete_post_meta($post_id, '_shipping_zimoexpress_order_id');
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

        $path = Functions::get_path('lihlihexpress_wilaya_' . date('Y-m-d') . '.json');

        if (!file_exists($path)) {
            $response = $this->get_wilayas();
            file_put_contents($path, json_encode($response['data']));
        }

        $data = json_decode(file_get_contents($path), true);

        $communes = array_filter($data, function($item) use ($wilaya_id) {
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

            foreach ($response_array['data'] as $key => $item) {
                $result[$key]['date'] = $item['date'] . ' ' . $item['time'];
                $result[$key]['location'] = null;
                $result[$key]['status'] = $item['action'];
            }
        }


        $last_status = end($result)['status'] ?? '';

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

        $path = Functions::get_path('lihlihexpress_wilaya_' . date('Y-m-d') . '.json');

        if (file_exists($path)) {
            $wilayas = json_decode(file_get_contents($path), true);



            if ($wilayas == null || count($wilayas) == 0) {
                return $this->get_wilaya_from_provider();
            }
        } else {
            $response = $this->get_wilaya_from_provider();

            if (empty($response) && ! is_array($response)) {
                return [];
            }
            file_put_contents($path, json_encode($response['data']));

            return $response['data'];
        }

        return $wilayas;
    }

    /**
     *
     * @return array
     */
    private function get_wilaya_from_provider()
    {

        if ($this->api_token) {

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
        $url = $this->provider['api_url'] . '/packages/status';
        $request = new \WooBordereauGenerator\Admin\Shipping\ZimoExpressRequest($this->provider);

        $content = $request->get($url, false, [
            'packages' => [$tracking_number]
        ]);


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
        $path = Functions::get_path('lihlihexpress_wilaya_' . date('Y-m-d') . '.json');

        if (!file_exists($path)) {
            $content = $this->get_wilayas();
            file_put_contents($path, json_encode($content));
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
                $wilayasResult[$i]['name'] = $wilaya['wilaya_name'];
                $wilayasResult[$i]['id'] = $wilaya['wilaya_id'];
            }

            // Sort array by ID in ascending order
            usort($wilayasResult, function($a, $b) {
                return $a['id'] - $b['id'];
            });

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

        if ($tracking_number) {

            $data = [
                'tracking_codes' => [$tracking_number]
            ];

            $url = $this->provider['api_url'] . "/packages/bulk";

            $request = new \WooBordereauGenerator\Admin\Shipping\ZimoExpressRequest($this->provider);
            $request->post($url, $data, \WooBordereauGenerator\Admin\Shipping\ZimoExpressRequest::DELETE);
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

        $label = $this->formData->get_meta('_shipping_tracking_label', true);

        if (! $label) {
            $label = $this->formData->get_meta('_shipping_label', true);
        }

        header('Location: '. $label);
        exit;
    }


}
