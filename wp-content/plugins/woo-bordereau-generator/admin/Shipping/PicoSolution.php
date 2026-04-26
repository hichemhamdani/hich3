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

class PicoSolution extends BordereauProvider implements BordereauProviderInterface
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
        $this->api_key    = get_option($provider['slug'].'_username');
        $this->api_token    = get_option($provider['slug'].'_password');
    }


    public function generate()
    {

        $params = $this->formData->get_data();
        $orderId = $this->formData->get_id();

        if (isset($this->data['products'])) {
            $productString = $this->data['products'];
        } else {
            $products = $params['line_items'];

            $productString = "";

            foreach ($products as $p) {
                $productString .= $p['name']. ' x '. $p['quantity'].  ' ,';
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

        $url = $this->provider['api_url'].'FR/Nouveau.awp?P1=1';

        if ($stop_desk) {
            $url = $this->provider['api_url'].'FR/Nouveau.awp?P1=2';
        }

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
        $price = number_format((float)$price, 2, ',', ' '); // 5 000,00 Da
        $name = "";

        if ($this->data['lname'] || $this->data['fname']) {
            $name = $this->data['fname'] . ' ' . $this->data['lname'];
        } elseif ($params['billing']['first_name'] || $params['billing']['last_name']) {
            $name = $params['billing']['first_name'] . ' ' . $params['billing']['last_name'];
        }

        // Domicile
        $data = [
            'WD_JSON_PROPRIETE_' => '',
            'WD_BUTTON_CLICK_' => 'A23',
            'WD_ACTION_' => '',
            'M30' => '',
            'A8' => $name, // name
            'A2' => $phone[0], // mobile 1
            'A3' => $phone[1], // mobile 2
            'A5' => $this->data['address'] ?? $params['billing']['address_1'], // address
            'A4' => $wilaya, // wilaya
            'A21' => $this->data['commune'] ?? $params['billing']['city'], // commune
            'A6' => 'Commande #' . $orderId, // note
            'A12' => $orderId, // order number
            'A9' => $productString, // products
            'A10' => '1', // qty products
            'A15' => '1',
            'A17' => '1',
            'A20' => '1',
            'A33' => '1',
            'A41' => '1',
            'A27' => $this->data['operation_type'] == 2 ? '1' : '',
            'A30' => '0', // shipping cost domicile
            'A31' => '0', // shipping cost stop desk
            'A13' => $price // price of the order
        ];


        $cookiesJar = $this->auth();

        $client = new Client(['cookies' => true]);

        $response = $client->request('POST', $url, [
            'cookies' => $cookiesJar,
            'form_params' => $data,
            'verify' => false,
        ]);


        $response_body = (string)$response->getBody();

        $re = '/NSPCS\.NSTypes\.CDescriptionTableau\(DECLARATIONAPI_StColis\.m_oDescriptionStatique,\[0\],2,0\),(.*)\);NSPCS.NSValues.DeclareVariableServeur/mU';

        preg_match($re, $response_body, $matches);

        $json = json_decode($matches[1], true);

        $result = json_decode(array_values($json)[0]['m_sJSON'], true);

        foreach ($result as $item) {
            if ($item['NoteClient'] === 'Commande #'.$orderId) {
                return $item;
            }
        }

        wp_send_json([
            'error' => true,
            'message'=>  __("An error has occurred. Please contact the developer ", 'woo-bordereau-generator')
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
            update_post_meta($post_id, '_shipping_tracking_url', $this->provider['api_url'].'FR/ColisClient.awp?P1='.$trackingNumber);
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

        $track = $this->provider['api_url'].'FR/ColisClient.awp?P1='.$trackingNumber;

        $current_tracking_data = [
            'tracking_number' => wc_clean($trackingNumber),
            'carrier_slug' => $this->provider['slug'],
            'carrier_url' => $track,
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



	    $nonce = wp_create_nonce('wp_rest');
	    $etq =  add_query_arg('_wpnonce', $nonce, $etq);

	    wp_send_json([
            'message' => __("Tracking number has been successfully created", 'woo-bordereau-generator'),
            'provider' => $this->provider['name'],
            'tracking' => $current_tracking_data,
            'label' => wc_clean($etq)
        ], 200);
    }

    public function track($tracking_number, $post_id = null)
    {
        $cookiesJar = $this->auth();

        $client = new Client(['cookies' => true]);

        $response = $client->request('GET', $this->provider['api_url'].'FR/ColisClient.awp?P1='.$tracking_number, [
            'cookies' => $cookiesJar,
            'verify' => false,
        ]);

        $response_body = (string)$response->getBody()->getContents();

        wp_send_json([
            'tracking' => $tracking_number,
            'events' => $this->format_tracking($response_body)
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
        $cookiesJar = $this->auth();

        $client = new Client(['cookies' => true]);

        $response = $client->request('GET', $this->provider['api_url'].'FR/ColisClient.awp?P1='.$tracking_number, [
            'cookies' => $cookiesJar,
            'verify' => false,
        ]);

        $response_body = (string) $response->getBody()->getContents();

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

        $cookiesJar = $this->auth();

        $client = new Client(['cookies' => true]);

        $response = $client->request('GET', $this->provider['api_url'].'FR/ColisClient.awp?P1='.$tracking_number, [
            'cookies' => $cookiesJar,
            'verify' => false,
        ]);

        $response_body = (string)$response->getBody()->getContents();

        $dom = new DOMDocument();

        $dom->loadHTML($response_body);
        $xpath = new DOMXPath($dom);
        $ls_ads = $xpath->query("//table[@id='ctzA3']/tr");
        $last_status = '';
        foreach ($ls_ads as $ad) {
            $re = '/[0-9]{2}\/[0-9]{2}\/[0-9]{4}\s[0-9]{2}\:[0-9]{2}/m';
            $str = $ad->nodeValue;
            $result = preg_replace($re, '', $str);
            $re = '/\[(.*)\]/m';
            $result = preg_replace($re, '', $result);
            $last_status = trim(str_replace(' ', '', $result));
        }

        if ($last_status) {
            BordereauGeneratorAdmin::update_tracking_status($this->formData, $last_status, 'single_tracking');
        }


        $wilaya = null;

        if ($dom->getElementById("c10-A2")->nodeValue) {
            $wilaya = trim(preg_replace('/(\((.*)\))/m', '', explode(':', $dom->getElementById("c10-A2")->nodeValue)[1]));
        }

        $wilayas  = $this->get_wilayas();

        foreach ($wilayas as $item) {
            if ($item['name'] == $wilaya) {
                $wilaya = $item['id'];
                break;
            }
        }

        $item = [];
        $detail = [];

        $detail[0]['address'] = explode(':', $dom->getElementById("c9-A2")->nodeValue)[1] ?? null;
        $detail[0]['phone'] = explode(':', $dom->getElementById("c7-A2")->nodeValue)[1] ?? null;
        $detail[0]['fname'] = explode(':', $dom->getElementById("c6-A2")->nodeValue)[1] ?? null;
        $detail[0]['free'] = false;
        $detail[0]['exchange'] = false;
        $detail[0]['stopdesk'] = $this->formData->get_meta('_is_stock-desk') == 'yes';
        $detail[0]['last_status'] = $last_status;
        $detail[0]['last_status_key'] = $last_status;
        $detail[0]['order_id'] = isset(explode(':', $dom->getElementById("c17-A2")->nodeValue)[1])  ? (int) str_replace('Commande #', '', explode(':', $dom->getElementById("c17-A2")->nodeValue)[1]) : null;
        $detail[0]['price'] = (int) explode(':', $dom->getElementById("c13-A2")->nodeValue)[1] ?? null;
        $detail[0]['commune'] = explode(':', $dom->getElementById("A1_6")->nodeValue)[1] ?? null;
        $detail[0]['wilaya'] = $wilaya;


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


    /**
     * @return array
     * @throws GuzzleException
     */
    private function get_wilayas()
    {

        $path = Functions::get_path($this->provider['slug'].'_wilaya.json');


        if (file_exists($path)) {
            $response =  json_decode(file_get_contents($path), true);
            if (count(array_filter($response))) {
                return $response;
            }
        }

        $wilayasResult = [];

        $cookiesJar = $this->auth();

        $client = new Client(['cookies' => true]);

        $response = $client->request('GET', $this->provider['api_url'].'FR/Nouveau.awp?P1=1', [
            'cookies' => $cookiesJar
        ]);

        $dom = new DOMDocument();

        $dom->loadHTML($response->getBody()->getContents());

        $sel = $dom->getElementById("A4");

        $optionTags = $sel->getElementsByTagName('option');

        foreach ($optionTags as $key => $tag) {
            if ($tag->nodeValue != 'Wilaya') {
                $wilayasResult[$key]['id'] = $tag->getAttribute('value');
                $wilayasResult[$key]['name'] = $tag->nodeValue;
            }
        }

        file_put_contents($path, json_encode($wilayasResult));

        return $wilayasResult;
    }

    /**
     * @since 2.1.0
     * @param $provider
     * @return array|mixed|void
     * @throws GuzzleException
     */
    public function get_wilayas_by_provider($provider)
    {

        $path = Functions::get_path($this->provider['slug'].'_wilaya.json');


        if (file_exists($path)) {
            return json_decode(file_get_contents($path), true);
        }

        $wilayasResult = [];

        $cookiesJar = $this->auth();

        $client = new Client(['cookies' => true]);

        $response = $client->request('GET', $this->provider['api_url'].'FR/Nouveau.awp?P1=1', [
            'cookies' => $cookiesJar
        ]);

        $dom = new DOMDocument();

        $dom->loadHTML($response->getBody()->getContents());

        $sel = $dom->getElementById("A4");

        $optionTags = $sel->getElementsByTagName('option');

        foreach ($optionTags as $key => $tag) {
            if ($tag->nodeValue != 'Wilaya') {
                $wilayasResult[$key]['id'] = $tag->getAttribute('value');
                $wilayasResult[$key]['name'] = $tag->nodeValue;
            }
        }

        file_put_contents($path, json_encode($wilayasResult));

        return $wilayasResult;
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

        $path = Functions::get_path($this->provider['slug'].'_'.$wilaya_id.'_communes_'. ($hasStopDesk ? 'stopdesk' : 'home') .'.json');

        if (file_exists($path)) {
            return json_decode(file_get_contents($path), true);
        }

        $url = $this->provider['api_url'].'FR/Nouveau.awp?P1=1';

        if ($hasStopDesk) {
            $url = $this->provider['api_url'].'FR/Nouveau.awp?P1=2';
        }

        $communes = [];

        $cookiesJar = $this->auth();

        $client = new Client(['cookies' => true]);

        $response = $client->request('POST', $url, [

            'cookies' => $cookiesJar,
            'form_params' => [
                'WD_ACTION_' => 'AJAXPAGE',
                'EXECUTE' => '47',
                'WD_CONTEXTE_' => 'A4',
                'A4' => $wilaya_id,
            ]
        ]);

        $xmlDoc = new DOMDocument();
        $xmlDoc->loadXML($response->getBody()->getContents());

        $el = $xmlDoc->getElementsByTagName('OPTION');

        foreach (range(0, $el->count() - 1) as $key => $item) {
            $communes[$key]['id'] = $el->item($item)->nodeValue;
            $communes[$key]['name'] = $el->item($item)->nodeValue;
        }

        file_put_contents($path, json_encode($communes));

        return $communes;
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
     * Auth to ZR Express
     * @return array|CookieJar
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    private function auth()
    {

        $client = new Client(['cookies' => true]);

        $jar = new CookieJar;

        $client->request('POST', $this->provider['login_url'], [
            'form_params' => [
                'WD_JSON_PROPRIETE_' => '',
                'WD_BUTTON_CLICK_' => 'A7',
                'WD_ACTION_' => '',
                'A2' => $this->api_key,
                'A3' => $this->api_token
            ],
            'cookies' => $jar
        ]);

        return $jar;
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

        $url = $this->provider['api_url'].'FR/En-Expedition.awp';

        $trackingNumber = get_post_meta($this->formData->get_id(), '_shipping_tracking_number', true);
        if(! $trackingNumber) {
            $trackingNumber = $this->formData->get_meta('_shipping_tracking_number');
        }

        $tracking_number = wc_clean($trackingNumber);

        $jar = $this->auth();

        $cookies = [
            [
                'Name'     => 'id',
                'Value'    => $tracking_number,
                'Domain'   => 'zrexpress.fr',
                'Path'     => '/',
                'Expires'  => false,
                'Max-Age'  => false,
                'Secure'   => false,
                'Discard'  => false,
                'HttpOnly' => false
            ],
            [
                'Name'     => 'uListe',
                'Value'    => $tracking_number,
                'Domain'   => 'zrexpress.fr',
                'Path'     => '/',
                'Expires'  => false,
                'Max-Age'  => false,
                'Secure'   => false,
                'Discard'  => false,
                'HttpOnly' => false
            ],
            [
                'Name'     => 'uListeNb',
                'Value'    => '1',
                'Domain'   => 'zrexpress.fr',
                'Path'     => '/',
                'Expires'  => false,
                'Max-Age'  => false,
                'Secure'   => false,
                'Discard'  => false,
                'HttpOnly' => false
            ],[
                'Name'     => 'GU',
                'Value'    => '00000000-0000-0000-0000-000000000000',
                'Domain'   => 'zrexpress.fr',
                'Path'     => '/',
                'Expires'  => false,
                'Max-Age'  => false,
                'Secure'   => false,
                'Discard'  => false,
                'HttpOnly' => false
            ],
            [
                'Name'     => 'XD',
                'Value'    => 'ZR',
                'Domain'   => 'zrexpress.fr',
                'Path'     => '/',
                'Expires'  => false,
                'Max-Age'  => false,
                'Secure'   => false,
                'Discard'  => false,
                'HttpOnly' => false
            ],
        ];

        // Add cookies to the jar
        foreach ($cookies as $cookie) {
            $jar->setCookie(new \GuzzleHttp\Cookie\SetCookie($cookie));
        }


        $client = new Client(['cookies' => true]);

        $cookieString = '';
        $cookiesArray = $jar->toArray();

        foreach ($cookiesArray as $cookie) {
            $cookieString .= $cookie['Name'] . '=' . $cookie['Value'] . '; ';
        }

// remove trailing '; '
        $cookieString = rtrim($cookieString, '; ');


        $response = $client->request('POST', $url, [
//            'cookies' => $jar,
            'headers' => [
                'authority' => 'zrexpress.fr',
                'accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                'accept-language' => 'en-US,en;q=0.9,fr;q=0.8',
                'cache-control' => 'max-age=0',
                'content-type' => 'application/x-www-form-urlencoded',
                'cookie' => $cookieString,
                'dnt' => '1',
                'origin' => 'https://zrexpress.fr',
                'referer' => 'https://zrexpress.fr/EXPRESS_WEB/FR/En-Expedition.awp',
                'sec-ch-ua' => '"Not.A/Brand";v="8", "Chromium";v="114", "Google Chrome";v="114"',
                'sec-ch-ua-mobile' => '?0',
                'sec-ch-ua-platform' => '"macOS"',
                'sec-fetch-dest' => 'document',
                'sec-fetch-mode' => 'navigate',
                'sec-fetch-site' => 'same-origin',
                'sec-fetch-user' => '?1',
                'upgrade-insecure-requests' => '1',
                'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/114.0.0.0 Safari/537.36'

            ],
            'form_params' => [
                'WD_BUTTON_CLICK_' => 'A8',
                'WD_ACTION_' => 'MENU_SUBMIT'
            ]
        ]);


        header('Content-type: application/pdf');
        header("Content-disposition: inline;filename=\"{$tracking_number}.pdf\"");
        header('Content-Transfer-Encoding: binary');
        header('Accept-Ranges: bytes');

        echo $response->getBody()->getContents();
        exit();

    }

    private function format_tracking($response_body)
    {

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($response_body);
        $xpath = new DOMXPath($dom);
        $ls_ads = $xpath->query("//table[@id='ctzA3']/tr");

        $response = [];

        $i = 0;
        foreach ($ls_ads as $ad) {


            $re = '/[0-9]{2}\/[0-9]{2}\/[0-9]{4}\s[0-9]{2}\:[0-9]{2}/m';
            $str = $ad->nodeValue;
            $time = $ad->firstChild->nodeValue;
            $result = preg_replace($re, '', $str);
            $re = '/\[(.*)\]/m';
            $result = preg_replace($re, '', $result);
            $status = trim(str_replace(' ', '', $result));

            $response[$i]['status'] = $status;
            $date = DateTime::createFromFormat('d/m/Y H:i', $time);
            $response[$i]['date'] = $date->format('Y-m-d H:i:s');

            $i++;
        }
        return $response;
    }


}
