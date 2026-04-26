<?php

namespace WooBordereauGenerator\Admin\Shipping;

use DateTime;
use DOMDocument;
use DOMXPath;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\GuzzleException;
use WC_Order;
use WC_Product;
use WooBordereauGenerator\Functions;
use WooBordereauGenerator\Admin\BordereauGeneratorAdmin;
use WooBordereauGenerator\Helpers;
use WP_Error;

class PicoSolutionAPI extends BordereauProvider implements BordereauProviderInterface
{
    /**
     * @var string
     */
    private string $api_key;

    /**
     * @var string
     */
    private string $api_token;


    /**
     * @var WC_Order
     */
    protected $formData;

    /**
     * @var array
     */
    protected $provider;

    /**
     * @var array|mixed
     */
    private $data;


    public function __construct(WC_Order $formData, array $provider, $data = [])
    {
        $this->formData = $formData;
        $this->provider = $provider;
        $this->data = $data;
        $this->api_key    = get_option($provider['slug'].'_key');
        $this->api_token    = get_option($provider['slug'].'_token');
    }


    public function generate()
    {

        $params = $this->formData->get_data();
        $orderId = $this->formData->get_id();


        $qtn = 1;

        if (isset($this->data['products'])) {
            $productString = $this->data['products'];
        } else {
            $products = $params['line_items'];

            $productString = "";

            foreach ($products as $p) {
                $productString .= $p['name']. ' x '. $p['quantity'].  ' ,';
                $qtn = $p['quantity'];
            }

            $productString = preg_replace('/,$/m', '', $productString);
        }

        $stop_desk = 0;

        if ($this->formData->has_shipping_method('local_pickup') && $this->data['shipping_type'] === 'stopdesk') {
            $stop_desk = 1;
        }

        if ($this->data['shipping_type'] === 'stopdesk') {
            $stop_desk = 1;
        }

        $url = $this->provider['api_url'].'/add_colis';

        if ($this->formData->get_meta('_is_stock-desk')) {
            $this->formData->update_meta_data('_is_stock-desk', $stop_desk);
            $this->formData->save();
        } else {
            $this->formData->add_meta_data('_is_stock-desk', $stop_desk);
            $this->formData->save();
        }

        $wilaya = $this->data['wilaya'] ?? $params['billing']['state'];
        $wilaya = (int) str_replace('DZ-', '', $wilaya);
        $phone = explode('/', $this->data['phone'] ?? $params['billing']['phone']);
        $price = $this->data['price'] ?? $params['total'] - $params['shipping_total'];
        // $price = number_format((float)$price, 2, ',', ' '); // 5 000,00 Da
        $name = "";

        if ($this->data['lname'] || $this->data['fname']) {
            $name = $this->data['fname'] . ' ' . $this->data['lname'];
        } elseif ($params['billing']['first_name'] || $params['billing']['last_name']) {
            $name = $params['billing']['first_name'] . ' ' . $params['billing']['last_name'];
        }

        if ($this->data['remarque']) {
            $note = $this->data['remarque'];
        } else {
            $note = stripslashes(get_option('wc_bordreau_default-shipping-note'));
        }

        if (! $note) {
            $note = 'Commande #' . $orderId;
        }
        // Domicile
        $data = [
            "Colis" => [
                [
                    "TypeLivraison" => $stop_desk, // Domicile : 0 & Stopdesk : 1
                    "TypeColis" => isset($this->data['operation_type']) && $this->data['operation_type'] == 2 ? "1" : "0", // Echange : 1
                    "Client" => $name,
                    "Confrimee" => "",
                    "MobileA" => $phone[0],
                    "MobileB" => $phone[1] ?? '',
                    "Adresse" => $this->data['address'] ?? $params['billing']['address_1'],
                    "IDWilaya" => $wilaya,
                    "Commune" => $this->data['commune'] ?? $params['billing']['city'],
                    "Total" => $price,
                    "Qtn" => $qtn,
                    "Note" => $note,
                    "TProduit" =>  $productString,
                    "id_Externe" => $this->formData->get_order_number() ,  // Votre ID ou Tracking
                    "Source" => "website"
                ]
            ]
        ];

        $client = new Client();

        $response = $client->request('POST', $url, [
            'body' => json_encode($data),
            'verify' => false,
            'headers' => [
                'token' => $this->api_token,
                'key' => $this->api_key,
                'Content-Type' => 'application/json'
            ]
        ]);

        $response_body = (string)$response->getBody();
        $response_array = json_decode($response_body, true);


        if(isset($response_array['Colis'][0]) && $response_array['Colis'][0]['MessageRetour'] === 'Good') {
            return  $response_array['Colis'][0];
        }
        wp_send_json([
            'error' => true,
            'message'=>  __("An error has occurred. Please contact the developer ", 'wc-bordereau-generator')
        ], 401);

        return [];
    }

    public function save($post_id, array $response, bool $update = false)
    {

        $trackingNumber = $response['Tracking'];

        $etq = get_rest_url( null, 'woo-bordereau/v1/slip/print/'.$this->provider['slug'].'/'.$post_id );

        $order = wc_get_order($post_id);

        $provider = get_post_meta($post_id, '_shipping_tracking_method', true);

        if(! $provider) {
            $provider = $order->get_meta('_shipping_tracking_method');
        }

        if ($update && $provider == $this->provider['slug']) {
            $trackingNumber = get_post_meta($post_id, '_shipping_tracking_number', true);
            if(! $trackingNumber) {
                $trackingNumber = $order->get_meta('_shipping_tracking_number');
            }

            $label = get_post_meta($post_id, '_shipping_tracking_label', true);

            if(! $label) {
                $label = $order->get_meta('_shipping_tracking_label');
            }

        } else {
            update_post_meta($post_id, '_shipping_tracking_number', wc_clean($trackingNumber));
            update_post_meta($post_id, '_shipping_tracking_label', wc_clean($etq));
            update_post_meta($post_id, '_shipping_label', wc_clean($etq));

//            update_post_meta($post_id, '_shipping_tracking_url', $this->provider['api_url'].'FR/ColisClient.awp?P1='.$trackingNumber);
            update_post_meta($post_id, '_shipping_tracking_method', $this->provider['slug']);

            $order = wc_get_order($post_id);

            if($order) {
                $order->update_meta_data('_shipping_tracking_number', wc_clean($trackingNumber));
                $order->update_meta_data('_shipping_tracking_label', wc_clean($etq));
                $order->update_meta_data('_shipping_label', wc_clean($etq));
                $order->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
                $order->save();
            }
        }

//        $track = $this->provider['api_url'].'FR/ColisClient.awp?P1='.$trackingNumber;

        $current_tracking_data = [
            'tracking_number' => wc_clean($trackingNumber),
            'carrier_slug' => $this->provider['slug'],
//            'carrier_url' => $track,
            'carrier_url' => "",
            "carrier_name" => $this->provider['name'],
            "carrier_type" => "custom-carrier",
            "time" => time()
        ];

        // check if the woo-order-tracking is enabled

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
            'label' => wc_clean($etq)
        ], 200);
    }

    public function track($tracking_number, $post_id = null)
    {

        $url = $this->provider['api_url'].'/lire';

        $client = new Client();

        $data = [
            "Colis" => [
                [
                    "Tracking" => $tracking_number
                ]
            ]
        ];


        $response = $client->request('POST', $url, [
            'body' => json_encode($data),
            'verify' => false,
            'headers' => [
                'token' => $this->api_token,
                'key' => $this->api_key,
                'Content-Type' => 'application/json'
            ]
        ]);

        $response_body = (string)$response->getBody();
        $response_array = json_decode($response_body, true);

        $result = $this->format_tracking($response_array);

        wp_send_json([
            'tracking' => $tracking_number,
            'events' => $result
        ]);


    }


    /**
     * Fetch the tracking information
     * @param string $tracking_number
     * @return array
     * @throws GuzzleException
     * @since 1.1.0
     */
    public function track_detail($tracking_number)
    {
//        $cookiesJar = $this->auth();
//
//        $client = new Client(['cookies' => true]);
//
//        $response = $client->request('GET', $this->provider['api_url'].'FR/ColisClient.awp?P1='.$tracking_number, [
//            'cookies' => $cookiesJar,
//            'verify' => false,
//        ]);

//        $response_body = (string) $response->getBody()->getContents();

        $response_body = [];

        return $this->format_tracking($response_body);
    }

    /**
     * @param $tracking_number
     * @param $post_id
     * @return array
     * @throws GuzzleException
     * @since 2.1.0
     */
    public function detail($tracking_number, $post_id)
    {
        $url = $this->provider['api_url'].'/lire';

        $client = new Client();

        $data = [
            "Colis" => [
                [
                    "Tracking" => $tracking_number
                ]
            ]
        ];

        $response = $client->request('POST', $url, [
            'body' => json_encode($data),
            'verify' => false,
            'headers' => [
                'token' => $this->api_token,
                'key' => $this->api_key,
                'Content-Type' => 'application/json'
            ]
        ]);

        $response_body = (string)$response->getBody();
        $response_array = json_decode($response_body, true);

        $detailColis = $response_array['Colis'][0];

        $last_status = $detailColis['Situation'] ?? null;

        $detail = [];

        $detail[0]['address'] = $detailColis['Adresse'] ?? null;
        $detail[0]['phone'] = $detailColis['MobileA'] ?? null;
        $detail[0]['fname'] = $detailColis['Client'] ?? null;
        $detail[0]['free'] = false;
        $detail[0]['exchange'] = $detailColis['TypeColis'] == "1";
        $detail[0]['stopdesk'] = $detailColis['TypeLivraison'] == "1" ?? null;
        $detail[0]['last_status'] = $last_status;
        $detail[0]['last_status_key'] =  $last_status;
        $detail[0]['order_id'] =  $detailColis['id_Externe'] ?? null;
        $detail[0]['price'] = (int) $detailColis['Total'] ?? null;
        $detail[0]['commune'] = $detailColis['Commune'] ?? null;
        $detail[0]['wilaya'] = $detailColis['Wilaya'] ?? null;

        if ($last_status) {
            BordereauGeneratorAdmin::update_tracking_status($this->formData, $last_status, 'single_tracking');
        }

        wp_send_json([
            'tracking' => $tracking_number,
            'detail' => $detail
        ]);
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


    public function get_wilayas()
    {

        $path = Functions::get_path($this->provider['slug'].'_wilaya_api.json');

        if (! file_exists($path)) {
            $content = json_decode(file_get_contents('https://amineware.me/api/wilayas'), true);
            file_put_contents($path, json_encode($content));
        }

        return json_decode(file_get_contents($path), true);
    }



    public function get_wilayas_by_provider() {
        return $this->get_wilayas();
    }


    /**
     * Get Cities from specific State
     * @param int $wilaya_id
     * @param bool $hasStopDesk
     *
     * @return array
     * @throws GuzzleException
     */
    public function get_communes(int $wilaya_id, bool $hasStopDesk)
    {

        $path = Functions::get_path($this->provider['slug'].'_cities.json');

        if (! file_exists($path)) {
            $content = file_get_contents('https://amineware.me/api/cities');
            file_put_contents($path, $content);
        }

        $communes = json_decode(file_get_contents($path), true);

        if (! is_array($communes) && count($communes) == 0) {
            $content = file_get_contents('https://amineware.me/api/cities');
            file_put_contents($path, $content);
            $communes = json_decode(file_get_contents($path), true);
        }

        $communesResult = [];



        $found = array_filter($communes, function ($v, $k) use ($wilaya_id) {
            return (int) $v['wilayas'] == $wilaya_id;
        }, ARRAY_FILTER_USE_BOTH);

        foreach ($found as $i => $item) {
            $communesResult[$i]['id'] = $item['communes'];
            $communesResult[$i]['name'] = $item['communes'];
        }

        return $communesResult;
    }

    /**
     * @return array
     */
    public function get_settings(array $data, $option = null)
    {

        $provider = $this->provider;

        $order = $this->formData;

        $fields = $provider['fields'];
        $key = $fields['total']['choices'][0]['name'] ?? null;

        $params = $order->get_data();
        $products = $params['line_items'];

        $productString = Helpers::generate_products_string($products);

        $total = get_option($key) ? get_option($key) : 'with-shipping';

        $cart_value = 0;

        if ($total == 'with-shipping') {
            $cart_value = (float) $order->get_total();
        } elseif ($total === 'without-shipping') {
            $cart_value =  ((float) $order->get_total() + $order->get_total_tax()) - $order->get_total_shipping();
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
     * @since 2.1.0
     * Get Upload file
     * @return string
     */
    private function get_path($filename)
    {
        $upload_dir   = wp_upload_dir();

        $directory = $upload_dir['basedir'] . '/wc-bordereau-generator';

        if (!is_dir($directory)) {
            wp_mkdir_p($directory);
        }

        return $directory . '/' . $filename;
    }

    public function get_slip() {



//        header('Content-type: application/pdf');
//        header("Content-disposition: inline;filename=\"{$tracking_number}.pdf\"");
//        header('Content-Transfer-Encoding: binary');
//        header('Accept-Ranges: bytes');
//
//        echo $response->getBody()->getContents();
//        exit();

    }

    private function format_tracking($response_array)
    {

        $result = [];

        return $result;

    }


}
