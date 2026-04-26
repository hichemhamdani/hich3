<?php

namespace WooBordereauGenerator\Admin\Shipping;

use DateTime;
use DOMDocument;
use DOMXPath;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Psr7\Request;
use WC_Order;
use WC_Product;
use WooBordereauGenerator\Functions;
use WooBordereauGenerator\Admin\BordereauGeneratorAdmin;
use WooBordereauGenerator\Helpers;
use WP_Error;

class TonColis extends BordereauProvider implements BordereauProviderInterface
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

        $products = [];
        $i = 0;

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

        $wilaya = $this->data['wilaya'] ?? $params['billing']['state'];
        $wilaya = (int) str_replace('DZ-', '', $wilaya);
        $phone = explode('/', $this->data['phone'] ?? $params['billing']['phone']);
        $price = $this->data['price'] ?? $params['total'] - $params['shipping_total'];
        $name = "";

        if ($this->data['lname'] || $this->data['fname']) {
            $name = $this->data['fname'] . ' ' . $this->data['lname'];
        } elseif ($params['billing']['first_name'] || $params['billing']['last_name']) {
            $name = $params['billing']['first_name'] . ' ' . $params['billing']['last_name'];
        }

        $date = new DateTime();
        $formattedDate = $date->format('YmdHis');  // Gives YYYYMMDDHHMMSS

        // For milliseconds
        $milliseconds = round(microtime(true) * 1000) % 1000;  // Gives the current milliseconds

        // Combine both to get the desired format
        $creationDate = $formattedDate . str_pad($milliseconds, 3, '0', STR_PAD_LEFT);

        if ($this->data['remarque']) {
            $note = $this->data['remarque'];
        } else {
            $note = stripslashes(get_option('wc_bordreau_default-shipping-note'));
        }

        if (! $note) {
            $note = 'Commande #' . $orderId;
        }


        $data = [
            [
                "IDColis" => 0,
                "TypeColis" => (isset($this->data['operation_type']) && $this->data['operation_type'] == 2) ? 1 : 0,
                "TypeLivraison" => $stop_desk ? 1 : 0,
                "Date_Creation" => $creationDate,
                "Client" => $name,
                "MobileA" => Helpers::clean_phone_number($phone[0]),
                "MobileB" => isset($phone[1]) ? Helpers::clean_phone_number($phone[1]) : "",
                "Adresse" => $this->data['address'] ?? $params['billing']['address_1'],
                "IDWilaya" => (int) $wilaya,
                "Commune" => $this->data['commune'] ?? $params['billing']['city'],
                "Total" => (int) $price,
                "Note" => $note,
                "IDLivreur" => 0,
                "IDLivreurEX" => 0,
                "Poids" => 0,
                "AnnulerRecouvert" => 0,
                "TProduit" => $productString,
                "id_Externe" => $this->formData->get_order_number()
            ]
        ];

        $cookiesJar = $this->auth();

        $client = new Client(['cookies' => true]);

        if (isset($this->data['is_update']) && $this->data['is_update']) {
            $url = $this->provider['api_url'].'FR/Liste.awp?P1=1';
            $response = $client->request('POST', $url, [
                'cookies' => $cookiesJar,
                'form_params' => [
                    'WD_ACTION_' => 'AJAXEXECUTE',
                    'EXECUTEPROCCHAMPS' => 'PAGE_Liste.update',
                    'WD_CONTEXTE_' => 'A42',
                    'PA1' => $name,
                    'PA2' =>  Helpers::clean_phone_number($phone[0]),
                    'PA3' => $this->data['phone_2'] ?: (isset($phone[1]) ? Helpers::clean_phone_number($phone[1]) : ""),
                    'PA4' => $this->data['address'] ?? $params['billing']['address_1'],
                    'PA5' => $note,
                    'PA6' => $stop_desk ? 1 : 0,
                    'PA7' => (isset($this->data['operation_type']) && $this->data['operation_type'] == 2) ? 1 : 0,
                    'PA8' => (int) $wilaya,
                    'PA9' => $this->data['commune'] ?? $params['billing']['city'],
                    'PA10' => (int) $price,
                    'PA11' => $this->data['tracking_number']
                ],
                'verify' => false,
            ]);

        } else {
            $url = $this->provider['api_url'].'FR/Colis.awp';
            $response = $client->request('POST', $url, [
                'cookies' => $cookiesJar,
                'form_params' => [
                    'WD_ACTION_' => 'AJAXEXECUTE',
                    'EXECUTEPROCCHAMPS' => 'PAGE_Colis.Chargement',
                    'WD_CONTEXTE_' => 'A58',
                    'PA1' => json_encode($data)
                ],
                'verify' => false,
            ]);
        }

        if ($response->getStatusCode() == 200) {
            if (isset($this->data['is_update']) && $this->data['is_update']) {
                $trackingNumber = $this->data['tracking_number'];
            } else {
                // Load the XML string
                $xml = simplexml_load_string($response->getBody()->getContents(), 'SimpleXMLElement', LIBXML_NOCDATA);

                // Convert the SimpleXMLElement object to a JSON string
                $jsonString = json_encode($xml);

                // Convert the JSON string back to an associative array
                $array = json_decode($jsonString, true);

                // The JSON string is contained within the RESULTAT node
                $jsonData = $array['RESULTAT'];

                // Decode the JSON data to get the array
                $dataArray = json_decode($jsonData, true);

                if($dataArray['Colis'][0]['MessageRetour'] != "Good") {

                    set_transient('order_bulk_add_error', $dataArray['Colis'][0]['MessageRetour'], 45);
                    $logger = \wc_get_logger();
                    $logger->error('Bulk Upload Error: ' . $dataArray['Colis'][0]['MessageRetour'], array('source' => WC_BORDEREAU_POST_TYPE));

                    wp_send_json([
                        'error' => true,
                        'message'=>  $dataArray['Colis'][0]['MessageRetour']
                    ], 401);
                }

                $trackingNumber = $dataArray['Colis'][0]['Tracking'] ?? null;
            }

            $url = $this->provider['api_url'].'FR/Liste.awp?P1=1';

            $client = new Client(['cookies' => true]);

            $response = $client->request('POST', $url, [
                'cookies' => $cookiesJar,
                'form_params' => [
                    'WD_ACTION_' => 'AJAXEXECUTE',
                    'EXECUTEPROCCHAMPS' => 'ServeurAPI.Chargement_G',
                    'WD_CONTEXTE_' => '',
                    'PA1' => 'liste',
                    'PA2' => '1',
                    'PA3' => '',
                    'PA4' => ''
                ],
                'verify' => false,
            ]);

            if ($response->getStatusCode() == 200) {

                // Load the XML string
                $xml = simplexml_load_string($response->getBody()->getContents(), 'SimpleXMLElement', LIBXML_NOCDATA);

                // Convert the SimpleXMLElement object to a JSON string
                $jsonString = json_encode($xml);

                // Convert the JSON string back to an associative array
                $array = json_decode($jsonString, true);

                // The JSON string is contained within the RESULTAT node
                $jsonData = $array['RESULTAT'];

                // Decode the JSON data to get the array
                $dataArray = json_decode($jsonData, true);

                $items = $dataArray['Colis'];

                $dataArray = array_filter($items, function($itm) use ($trackingNumber) {
                    return $itm['Tracking'] == $trackingNumber;
                });
            }

            return $dataArray;

        }

        wp_send_json([
            'error' => true,
            'message'=>  __("An error has occurred. Please contact the developer ", 'wc-bordereau-generator')
        ], 401);

        return [];
    }

    public function save($post_id, array $response, bool $update = false)
    {
        $meta = $response;


        if(isset($response['Colis'])) {
            $response = $response['Colis'];
        }

        $response = reset($response);
        $trackingNumber = $response['Tracking'];
        $etq = get_rest_url( null, 'woo-bordereau/v1/slip/print/'.$this->provider['slug'].'/'.$post_id );

        update_post_meta($post_id, '_shipping_tracking_number', wc_clean($trackingNumber));
        update_post_meta($post_id, '_shipping_tracking_label', wc_clean($etq));
        update_post_meta($post_id, '_shipping_label', wc_clean($etq));
        update_post_meta($post_id, '_shipping_tracking_url', $this->provider['api_url'].'FR/ColisClient.awp?P1='.$trackingNumber);
        update_post_meta($post_id, '_shipping_tracking_method', $this->provider['slug']);
        update_post_meta($post_id, '_shipping_tracking_meta', $meta);

        $order = wc_get_order($post_id);

        if($order) {
            $order->update_meta_data('_shipping_tracking_number', wc_clean($trackingNumber));
            $order->update_meta_data('_shipping_tracking_label', wc_clean($etq));
            $order->update_meta_data('_shipping_label', wc_clean($etq));
            $order->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
            $order->update_meta_data('_shipping_tracking_meta', $meta);

            $order->save();

            Helpers::make_note($order, $trackingNumber, $this->provider['name']);

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
            'message' => __("Tracking number has been successfully created", 'wc-bordereau-generator'),
            'provider' => $this->provider['name'],
            'tracking' => $current_tracking_data,
            'label' => wc_clean($etq)
        ], 200);
    }

    public function track($tracking_number, $post_id = null)
    {
        $cookiesJar = $this->auth();

        $client = new Client(['cookies' => true]);

        $response = $client->request('POST', $this->provider['api_url'].'FR/Liste.awp', [
            'cookies' => $cookiesJar,
            'verify' => false,
            'form_params' => [
                'WD_ACTION_' => 'AJAXEXECUTE',
                'EXECUTEPROCCHAMPS' => 'PAGE_Liste.Chargement_Hitorique',
                'WD_CONTEXTE_' => 'A26',
                'PA1' => $tracking_number
            ]
        ]);

        $response_body = (string) $response->getBody();

        $dom = new DOMDocument();
        $dom->loadXML($response_body);
        $json = $dom->getElementsByTagName('RESULTAT')->item(0)->nodeValue;
        $data = json_decode($json, true);

        wp_send_json([
            'tracking' => $tracking_number,
            'events' => Helpers::sort_tracking($this->format_tracking($data))
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

        $response = $client->request('POST', $this->provider['api_url'].'FR/Liste.awp', [
            'cookies' => $cookiesJar,
            'verify' => false,
            'form_params' => [
                'WD_ACTION_' => 'AJAXEXECUTE',
                'EXECUTEPROCCHAMPS' => 'PAGE_Liste.Chargement_Hitorique',
                'WD_CONTEXTE_' => 'A26',
                'PA1' => $tracking_number
            ]
        ]);

        $response_body = (string) $response->getBody();

        $dom = new DOMDocument();
        $dom->loadXML($response_body);
        $json = $dom->getElementsByTagName('RESULTAT')->item(0)->nodeValue;
        $data = json_decode($json, true);

        return $this->format_tracking($data);
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

        $path = Functions::get_path($this->provider['slug'].'_wilaya_v2.json');

        if (file_exists($path)) {
            $response =  json_decode(file_get_contents($path), true);
            if (count(array_filter($response))) {
                return $response;
            }
        }

        $wilayasResult = [];

        $cookiesJar = $this->auth();

        $client = new Client(['cookies' => true]);

        $response = $client->request('GET', $this->provider['api_url'].'Nouveau-Colis.awp', [
            'cookies' => $cookiesJar
        ]);

        $dom = new DOMDocument();

        $dom->loadHTML($response->getBody()->getContents());

        $sel = $dom->getElementById("A7");

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

        $path = Functions::get_path($this->provider['slug'].'_'.$wilaya_id.'_communes_'. ($hasStopDesk ? 'stopdesk' : 'home') .'v2.json');


        if (file_exists($path)) {
            return json_decode(file_get_contents($path), true);
        }

        $url = $this->provider['api_url'].'FR/Colis.awp';

        $communes = [];

        $cookiesJar = $this->auth();

        $client = new Client(['cookies' => true]);


        $response = $client->request('POST', $url, [

            'cookies' => $cookiesJar,
            'form_params' => [
                'WD_ACTION_' => 'AJAXEXECUTE',
                'EXECUTEPROC' => 'PAGE_Colis.Chargement_Commune',
                'WD_CONTEXTE_' => 'A17',
                'PA1' => $wilaya_id,
                'PA2' => $hasStopDesk ? '1' : '0'
            ]
        ]);

        $response = $response->getBody()->getContents();
        $json = json_decode($response, true);


        if ($hasStopDesk) {
            foreach ($json["Liste"] as $key => $item) {
                $communes[$key]['id'] = $item['Bureau'];
                $communes[$key]['name'] = $item['Bureau'];
            }
        } else {
            foreach ($json["Liste"] as $key => $item) {
                $communes[$key]['id'] = $item['Commune'];
                $communes[$key]['name'] = $item['Commune'];
            }
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
        $overweight = $fields['overweight']['choices'][0]['name'] ?? null;
        $overweightFrontend = $fields['overweight_checkout']['choices'][0]['name'] ?? null;

        $overweightCase = get_option($overweight) ? get_option($overweight) : 'recalculate-without-overweight';
        $overweightFrontendCase = get_option($overweightFrontend) ? get_option($overweightFrontend) : 'recalculate-without-overweight';

        $params = $order->get_data();
        $products = $params['line_items'];

        $productString = Helpers::generate_products_string($products);
        $total = get_option($key) ? get_option($key) : 'with-shipping';

        $cart_value = 0;
        $shippingOverweightPrice = 0;
        $shippingOverweight = 0;

        if ($total == 'with-shipping') {
            $cart_value = (float) $order->get_subtotal() + $order->get_total_tax() +  $order->get_total_shipping();
        } elseif ($total === 'without-shipping') {
            $cart_value =  ((float) $order->get_total() + $order->get_total_tax()) - $order->get_total_shipping();
        }

        $total_weight = 0;
        foreach ( $products as $p) {
            $product = new WC_Product($p['product_id']);

            $total_weight += (float) $product->get_weight() * (int) $p['quantity'];
        }

        // check if the option of extra weight
        if ($overweightCase == 'recalculate-with-overweight') {

            if ($total_weight > $shippingOverweight) {
                $shippingOverweight = $total_weight;
            }

			if ($overweightFrontendCase == 'recalculate-with-overweight') {
				if ($shippingOverweight > 5) {
					$zonePrice = 50;

					$shippingOverweightPrice = max(0, ceil($shippingOverweight - 5)) * $zonePrice;
					$cart_value = $cart_value + $shippingOverweightPrice;
				}
			}

        }

        $data = [];
        if ($key) {
            $data['total_calculate'] = get_option($key) ? get_option($key) : 'with-shipping';
            $data['type'] = $provider['type'];
            $data['total'] = $cart_value;
            $data['order_number'] = $order->get_order_number();
            $data['products_string'] = $productString;
            $data['total_weight'] = $total_weight;
            $data['total_without_shipping'] = $total_weight;
            $data['zone_fee'] = 50;
            $data['shipping_overweight_price'] = $shippingOverweightPrice;
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

        try {
            $client = new Client(['cookies' => true]);

            $cookiesJar = new CookieJar;

            $options = [
                'form_params' => [
                    'WD_ACTION_' => 'AJAXEXECUTE',
                    'EXECUTEPROCCHAMPS' => 'Conenxion.ServeurConnexion',
                    'WD_CONTEXTE_' => $this->provider['extra']['context'],
                    'PA1' => $this->api_key,
                    'PA2' => $this->api_token,
                ],
                'cookies' => $cookiesJar
            ];

            $request = new Request('POST', $this->provider['login_url']);

            $client->sendAsync($request, $options)->wait();

            return $cookiesJar;
        } catch (ClientException|ServerException|BadResponseException $e) {

            $req = $e->getRequest();
            $resp = $e->getResponse();
            error_log($e->getMessage());
        }

    }

	function encodeArabicJson($data) {
		$data = reset($data);


		// Create the new structure with encoded Arabic text
		$encodedData = [
			'IDColis' => $data['IDColis'],
			'HUB_Depart' => $data['HUB_Depart'],
			'HUB_Attache' => (string)$data['HUB_Attache'],
			'DateA' => $data['DateA'],
			'Date_Creation' => str_replace(['T', '.'], '', $data['Date_Creation']),
			'TypeColis' => $data['TypeColis'],
			'NomTypeColis' => '',
			'TypeLivraison' => $data['TypeLivraison'],
			'NomTypeLivraison' => '',
			'Tracking' => $data['Tracking'],
			'IDUtilisateur' => $data['IDUtilisateur'],
			'IDEntreprise' => $data['IDEntreprise'],
			'IDSituation' => $data['IDSituation'],
			'Situation' => $data['Situation'],
			'Commentaire' => $data['Commentaire'],
			'Client' =>  Helpers::encodeArabicText($data['Client']),
			'MobileA' => $data['MobileA'],
			'MobileB' => $data['MobileB'],
			'Adresse' =>  Helpers::encodeArabicText($data['Adresse']),
			'IDWilaya' => $data['IDWilaya'],
			'Wilaya' => '',
			'Commune' => $data['Commune'],
			'Total' => $data['Total'],
			'Note' => $data['Note'],
			'Date_Receptionne' => $data['Date_Receptionne'],
			'Date_Livree' => $data['Date_Livree'],
			'DateA_Action' => $data['DateA_Action'],
			'DateH_Action' => str_replace(['T', '.'], '', $data['DateH_Action']),
			'IDLivreur' => $data['IDLivreur'],
			'IDLivreurEX' => $data['IDLivreurEX'],
			'Poids' => $data['Poids'],
			'AnnulerRecouvert' => $data['AnnulerRecouvert'],
			'TProduit' =>  Helpers::encodeArabicText($data['TProduit']),
			'Produit' => [],
			'Qtn' => 0,
			'Excel' => $data['Excel'],
			'id_Externe' => $data['id_Externe']
		];

		$jsonString = json_encode([$encodedData],
			JSON_UNESCAPED_UNICODE |
			JSON_UNESCAPED_SLASHES |
			JSON_PRESERVE_ZERO_FRACTION
		);

		// Remove any remaining double escapes if they exist
		$jsonString = str_replace('\\\\u', '\u', $jsonString);

		return $jsonString;
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

        $meta = get_post_meta($this->formData->get_id(), '_shipping_tracking_meta', true);

        if (!$meta) {
            $meta = $this->formData->get_meta('_shipping_tracking_meta');
        }

        if (is_string($meta)) {
            $meta  = json_decode($meta, true);
        }

        $cookiesJar = $this->auth();

        $client = new Client(['cookies' => true]);

        $url = $this->provider['api_url']. 'FR/Liste.awp?P1=1';

        $response = $client->request('POST', $url, [
            'cookies' => $cookiesJar,
            'form_params' => [
                'WD_ACTION_' => 'AJAXEXECUTE',
                'EXECUTEPROC' => 'ProcéduresLocal.Crée_PDF',
                'WD_CONTEXTE_' => 'A2',
                'PA1' => json_encode([reset($meta)]),
                'PA2' => 'A13'
            ],
            'verify' => false,
        ]);

        if ($response->getStatusCode() == 200) {
            $id = $response->getBody()->getContents();
            $url = $this->provider['api_url']."FR/PDF.awp?P1=".wc_clean($id);
            update_post_meta($this->formData->get_id(), '_shipping_tracking_label', $url);
            update_post_meta($this->formData->get_id(), '_shipping_label', $url);
            header('Location: '. $url);
            exit;
        }
    }

    private function format_tracking($response_body)
    {
        $response = [];
        $i = 0;
        if (isset($response_body['h'])) {
            foreach ($response_body['h'] as $ad) {
                $response[$i]['status'] = $ad['Situation'];
                $date = new DateTime( $ad['Date_Creation']);
                $response[$i]['date'] = $date->format('Y-m-d H:i:s');
                $i++;
            }
        }

        
        return $response;
    }

    /**
     * @param $tracking_number
     * @param $post_id
     * @return string
     * @since 1.3.0
     */
    public function delete($tracking_number, $post_id)
    {

        $meta = get_post_meta($post_id, '_shipping_tracking_meta');
        $order = wc_get_order($post_id);
        if(! $meta) {
            $meta = $order->get_meta('_shipping_tracking_meta');
        }

        if (is_string($meta)) {
            $meta  = json_decode($meta, true);
        }


        $timestamp = microtime(true);

        // Format the date and time as YYYYMMDDHHMMSS
        $formattedDateTime = date('YmdHis', $timestamp);

        // Extract milliseconds
        $milliseconds = floor(($timestamp - floor($timestamp)) * 1000);

        // Ensure milliseconds are three digits by padding
        $millisecondsFormatted = str_pad($milliseconds, 3, '0', STR_PAD_LEFT);

        // Concatenate the formatted date and milliseconds
        $fullDateTime = $formattedDateTime . $millisecondsFormatted;

        $metaDelete = '[{"IDColis":0,"HUB_Depart":"","HUB_Attache":"","DateA":"'.$meta[0]['DateA'].'","Date_Creation":"'.$meta[0]['Date_Creation'].'","TypeColis":0,"NomTypeColis":"","TypeLivraison":0,"NomTypeLivraison":"","Tracking":"'.$tracking_number.'","IDUtilisateur":0,"IDEntreprise":0,"IDSituation":0,"Situation":"","Commentaire":"","Client":"","MobileA":"","MobileB":"","Adresse":"","IDWilaya":0,"Wilaya":"","Commune":"","Total":0,"Note":"","Date_Receptionne":"'.$meta[0]['Date_Receptionne'].'","Date_Livree":"'.$meta[0]['Date_Livree'].'","DateA_Action":"'.$meta[0]['DateA_Action'].'","DateH_Action":"'.$fullDateTime.'","IDLivreur":0,"IDLivreurEX":0,"Poids":0,"AnnulerRecouvert":0,"TProduit":"","Produit":[],"Qtn":0,"Excel":"","id_Externe":""}]';

        $cookiesJar = $this->auth();
        $client = new Client(['cookies' => true]);
        $url = $this->provider['api_url']. 'FR/Liste.awp?P1=1';
        $client->request('POST', $url, [
            'cookies' => $cookiesJar,
            'form_params' => [
                'WD_ACTION_' => 'AJAXEXECUTE',
                'EXECUTEPROCCHAMPS' => 'ServeurAPI.Supprimer_Colis',
                'WD_CONTEXTE_' => 'A18',
                'PA1' => $metaDelete,
            ],
            'verify' => false,
        ]);
    }


}
