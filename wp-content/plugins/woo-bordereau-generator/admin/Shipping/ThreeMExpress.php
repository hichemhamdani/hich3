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

class ThreeMExpress extends BordereauProvider implements BordereauProviderInterface
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
        $this->api_id = get_option('3mexpress_username');
        $this->api_token = get_option('3mexpress_password');
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

        if (isset($this->data['wilaya'])) {
            $wilayaId = (int) str_replace("DZ-", "", $this->data['wilaya']);

            $wilaya = array_filter($wilayas, function ($item) use ($wilayaId){
                return $item['id'] == $wilayaId;
            });

            $wilaya = reset($wilaya);

        } else {
            $wilayaId = (int) str_replace("DZ-", "", $params['billing']['state']);
            $wilaya = array_filter($wilayas, function ($item) use ($wilayaId){
                return $item['id'] == $wilayaId;
            });

            $wilaya = reset($wilaya);
        }

        $commune = $this->data['commune'] ?? $params['billing']['city'];
        $communeId = null;

        $communefound = array_filter($wilaya['municipalities'], function($itm) use ($commune) {
            return $itm['name_latin'] == $commune;
        });

        if (count($communefound)) {
            $communefound = reset($communefound);
            $communeId = $communefound['id'];
        }

        if (isset($this->data['products'])) {
            $productString = $this->data['products'];
        } else {
            $products = $params['line_items'];
            $productString = Helpers::generate_products_string($products);
        }

        $stop_desk = 0;

        if ($this->formData->has_shipping_method('local_pickup') && $this->data['shipping_type'] === 'stopdesk') {
            $stop_desk = 1;
        }

        if ($this->data['shipping_type'] === 'stopdesk') {
            $stop_desk = 1;
        }

        $phone = explode('/', $this->data['phone'] ?? $params['billing']['phone']);
        $price = $this->data['price'] ?? $params['total'] - $params['shipping_total'];
        $name = "";

        if ($this->data['lname'] || $this->data['fname']) {
            $name = $this->data['fname'] . ' ' . $this->data['lname'];
        } elseif ($params['billing']['first_name'] || $params['billing']['last_name']) {
            $name = $params['billing']['first_name'] . ' ' . $params['billing']['last_name'];
        }

        $request = new ThreeMExpressRequest($this->provider);
        $userInfo = $request->getUserInfo();

        $userCommuneId = $userInfo['stores'][0]['municipality']['id'];

        if ($this->data['remarque']) {
            $note = $this->data['remarque'];
        } else {
            $note = stripslashes(get_option('wc_bordreau_default-shipping-note'));
        }

        $data = array (
            'parcelReferenceId' => ' ',
            'targetFullName' => $name,
            'targetPhoneNumberPrimary' => Helpers::clean_phone_number($phone[0], true),
            'targetPhoneNumberSecondary' => isset($phone[1]) ? Helpers::clean_phone_number($phone[1], true) : "",
            'targetMunicipalityId' => $communeId,
            'targetAdrress' => $this->data['address'] ?? $params['billing']['address_1'],
            'operationType' => 'DELIVERY',
            'deliveryType' => $stop_desk ? 'STOPDESK': 'DOORSTEP',
            'parcelDescription' => $productString,
            'totalPostTarget' => (int) $price,
            'initiatorMunicipalityId' => $userCommuneId,
            'paiedBy' => 'TARGET',
            'parcelWeight' => 7,
            'parcelVolume' => 0.01,
            'parcelFragile' => $this->data['is_fragile'] === 'true' ?? false,
            'parcelOpenable' => $this->data['open_parcel'] === 'true' ?? false,
            'parcelTryable' => $this->data['try_product'] === 'true' ?? false,
            'parcelAmount' => (int) $price,
            'secondaryParcelDescription' => 'Desc',
            'secondaryParcelWeight' => NULL,
            'secondaryParcelVolume' => NULL,
            'secondaryParcelOpenable' => true,
            'secondaryParcelTryable' => true,
            'secondaryParcelAmount' => 0,
        );


        if (isset($this->data['is_update'])) {
            $orderProviderId = get_post_meta($orderId, '_shipping_3mexpress_order_id', true);
            $url = $this->provider['api_url'] . "/customers/orders/" . $orderProviderId;
            unset($data['operationType']);
        } else {
            $url = $this->provider['api_url'] . '/customers/orders';
        }

        $request = new ThreeMExpressRequest($this->provider);
        $method = ThreeMExpressRequest::POST;

        if (isset($this->data['is_update'])) {
            $method = ThreeMExpressRequest::PATCH;
        }

        $content = $request->post($url, $data, $method);


        if (isset($content['statusCode']) && $content['statusCode']) {
            wp_send_json([
                'error' => true,
                'message' => $content['message']
            ], 401);
        }
        //

        if (is_wp_error($request)) {
            wp_send_json([
                'error' => true,
                'message' => __("An error has occurred. Please see log file ", 'woo-bordereau-generator')
            ], 401);
        }


        if (isset($this->data['is_update'])) {
            $content['id'] = $orderProviderId ?? null;
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
    public function save($post_id, array $response)
    {
        $etq = get_rest_url(null, 'woo-bordereau/v1/slip/print/'.$this->provider['slug'].'/'.$post_id);

        try {
            // get the tracking


            if (isset($response['id'])) {

                $orderId = $response['id'];

                $url = $this->provider['api_url'] . '/v1/orders?skip=0&take=100&status=PLACED&id='. $orderId;
                $request = new ThreeMExpressRequest($this->provider);
                $content = $request->get($url);

                $orderFound = $content['data'];

                if (count($orderFound)) {

                    $orderFound = reset($orderFound);
                    $track = $this->provider['api_url'].'/tracking/' . $orderId . '/events';

                    update_post_meta($post_id, '_shipping_tracking_number', wc_clean($orderFound['trackingId']));
                    update_post_meta($post_id, '_shipping_3mexpress_order_id', $orderId);
                    update_post_meta($post_id, '_shipping_tracking_label',  $this->provider['api_url'].'/order-slips?orderIds[]='.$orderId);
                    update_post_meta($post_id, '_shipping_label',  $this->provider['api_url'].'/order-slips?orderIds[]='.$orderId);
                    update_post_meta($post_id, '_shipping_tracking_label_method', 'GET');
                    update_post_meta($post_id, '_shipping_tracking_url', $track);

                    $order = wc_get_order($post_id);

                    if($order) {
                        $order->update_meta_data('_shipping_tracking_number', wc_clean($orderFound['trackingId']));
                        $order->update_meta_data('_shipping_tracking_label', $this->provider['api_url'].'/order-slips?orderIds[]='.$orderId);
                        $order->update_meta_data('_shipping_label', $this->provider['api_url'].'/order-slips?orderIds[]='.$orderId);
                        $order->update_meta_data('_shipping_tracking_label_method', 'GET');
                        $order->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
                        $order->update_meta_data('_shipping_3mexpress_order_id', $orderId);
                        $order->save();

                        Helpers::make_note($order, wc_clean($orderFound['trackingId']), $this->provider['name']);

                    }

                    $current_tracking_data = [
                        'tracking_number' => wc_clean($orderFound['trackingId']),
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
                }
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
        $order_id = get_post_meta($post_id, '_shipping_3mexpress_order_id', true);

        $url = $this->provider['api_url'] . "/tracking/{$order_id}/events";

        $request = new ThreeMExpressRequest($this->provider);

        $content = $request->get($url, false);

        if (is_array($content)) {

            $events = Helpers::sort_tracking($this->format_tracking($content));


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
        delete_post_meta($post_id, '_shipping_3mexpress_order_id');
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
     * @param int $wilaya_id
     * @param bool $hasStopDesk
     *
     * @return array
     */
    public function get_communes(int $wilaya_id, bool $hasStopDesk)
    {

        $path = Functions::get_path('3mexpress_wilaya.json');

        if (!file_exists($path)) {
            $content = $this->get_wilayas();
            file_put_contents($path, json_encode($content));
        }

        $wilayas = json_decode(file_get_contents($path), true);

        $wilaya = array_filter($wilayas, function($item) use ($wilaya_id) {
            return $item['id'] == $wilaya_id;
        });

        $wilaya = reset($wilaya);

        $communesResult = [];

        foreach ($wilaya['municipalities'] as $i => $item) {
            $communesResult[$i]['id'] = $item['id'];
            $communesResult[$i]['name'] = $item['name_latin'];
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
                if ($item['type'] === 'RETURNED') {
                    $result[$key]['failed'] = true;
                }

                if ($item['type'] === 'DELIVERED') {
                    $result[$key]['delivered'] = true;
                    if (isset($result[$key]['failed'])) {
                        $result[$key]['failed'] = false;
                    }
                }

                $result[$key]['date'] = $item['time'];
                $result[$key]['location'] = $item['municipalityLocation']['name_latin'] ?? null;
                $result[$key]['status'] = $item['description'];
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

        $path = Functions::get_path('3mexpress_wilaya.json');

        if (!file_exists($path)) {

            $request = new ThreeMExpressRequest($this->provider);
            $content = $request->get($this->provider['api_url'] . '/geo/provinces');

            file_put_contents($path, json_encode($content));
        }

        $wilayas = json_decode(file_get_contents($path), true);

        return $wilayas;
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
        $request = new ThreeMExpressRequest($this->provider);

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

        $path = Functions::get_path('3mexpress_wilaya.json');

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
                $wilayasResult[$i]['name'] = $wilaya['name_latin'];
                $wilayasResult[$i]['id'] = $wilaya['id'];
            }

            usort($wilayasResult, function($a, $b) {
                return $a['id'] <=> $b['id'];
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

        $tracking_number = get_post_meta($post_id, '_shipping_3mexpress_order_id', true);

        if ($tracking_number) {

            // delete the package if exist

            $curl = curl_init();

            $data = [
                'orders' => [$tracking_number]
            ];

            $url = $this->provider['api_url'] . "/customers/orders/multiple";

            $request = new ThreeMExpressRequest($this->provider);

            $request->post($url, $data, ThreeMExpressRequest::DELETE);
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

        $id = wc_clean(get_post_meta($this->formData->get_id(), '_shipping_3mexpress_order_id', true));

        $url = $this->provider['api_url']. '/orders/order-slips?orderIds[]=' . $id;

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'authority: express-backend-main-k6wb36zmaa-ew.a.run.app',
                'accept: application/json, text/plain, */*',
                'accept-language: en-US,en;q=0.9,fr;q=0.8',
                'authorization: ' . (new ThreeMExpressRequest($this->provider))->getToken(),
                'cache-control: no-cache',
                'dnt: 1',
                'origin: https://express-frontend-merchant-main-k6wb36zmaa-ew.a.run.app',
                'pragma: no-cache',
                'referer: https://express-frontend-merchant-main-k6wb36zmaa-ew.a.run.app/',
                'sec-ch-ua: "Not_A Brand";v="8", "Chromium";v="120", "Google Chrome";v="120"',
                'sec-ch-ua-mobile: ?0',
                'sec-ch-ua-platform: "macOS"',
                'sec-fetch-dest: empty',
                'sec-fetch-mode: cors',
                'sec-fetch-site: cross-site',
                'user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        header('Content-Type: text/html; charset=utf-8');
        echo $response;
        exit();
    }


}
