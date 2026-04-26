<?php

namespace WooBordereauGenerator\Admin\Shipping;

use DateTime;
use DOMDocument;
use DOMXPath;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use WC_Order;
use WC_Product;
use WooBordereauGenerator\Functions;
use WooBordereauGenerator\Admin\BordereauGeneratorAdmin;
use WooBordereauGenerator\Helpers;
use WP_Error;

class TonColisAPI extends BordereauProvider implements BordereauProviderInterface
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
        try {
            $orderData = $this->formData->get_data();
            $orderId = $this->formData->get_id();
            $trackingNumber = $this->formData->get_meta('_shipping_tracking_number');

            // Extract and validate products data
            $productString = $this->generateProductString($orderData['line_items']);

            // Handle shipping type and stop desk settings
            $stopDesk = $this->determineStopDesk();

            // Update stop desk meta
            $this->updateStopDeskMeta($stopDesk);

            // Process order details
            $orderDetails = $this->prepareOrderDetails($orderData);

            // Prepare base colis data
            $colisData = [
                "NomComplet" => $orderDetails['name'],
                "Mobile_1" => $orderDetails['phones'][0],
                "Mobile_2" => $orderDetails['phones'][1] ?? '',
                "Adresse" => $orderDetails['address'],
                "Wilaya" => $orderDetails['wilaya'],
                "Commune" => $orderDetails['commune'],
                "Article" => $productString,
                "Ref_Article" => "",
                "NoteFournisseur" => $orderDetails['note'],
                "Total" => $orderDetails['price'],
                "ID_Externe" => $this->formData->get_order_number(),
                "Source" => "website"
            ];

            // Add create-specific fields if no tracking number exists
            $colisData["Echange"] = $this->getOperationType();
            $colisData["Stopdesk"] = $stopDesk;

            // CodeStopdesk is required when Stopdesk=1
            if ($stopDesk) {
                $center = $this->data['center'] ?? get_post_meta($this->formData->get_id(), '_billing_center_id', true);
                if ($center) {
                    $colisData["CodeStopdesk"] = $center;
                }
            }

            // Structure the request data based on operation type
            $requestData = [
                "Colis" => $trackingNumber ? $colisData : [$colisData]
            ];

            return $this->sendApiRequest($requestData, $trackingNumber);

        } catch (Exception $e) {
            wp_send_json([
                'error' => true,
                'message' => __("An error occurred: " . $e->getMessage(), 'wc-bordereau-generator')
            ], 401);
        }
    }

    private function getOperationType()
    {
        // Convert to 0/1 format as per documentation
        return (isset($this->data['operation_type']) && $this->data['operation_type'] == 2) ? 1 : 0;
    }

    private function generateProductString($lineItems)
    {
        if (isset($this->data['products'])) {
            return $this->data['products'];
        }

        $products = [];
        $this->totalQuantity = 0;

        foreach ($lineItems as $item) {
            $products[] = sprintf('%s x %d', $item['name'], $item['quantity']);
            $this->totalQuantity += $item['quantity'];
        }

        return implode(', ', $products);
    }

    private function determineStopDesk()
    {
        if (isset($this->data['shipping_type']) && $this->data['shipping_type'] === 'stopdesk') {
            return 1;
        }

        if ($this->formData->has_shipping_method('local_pickup') &&
            isset($this->data['shipping_type']) &&
            $this->data['shipping_type'] === 'stopdesk') {
            return 1;
        }

        return 0;
    }

    private function updateStopDeskMeta($stopDesk)
    {
        $metaExists = $this->formData->get_meta('_is_stock-desk');
        $metaMethod = $metaExists ? 'update_meta_data' : 'add_meta_data';

        $this->formData->$metaMethod('_is_stock-desk', $stopDesk);
        $this->formData->save();
    }

    private function prepareOrderDetails($orderData)
    {
        return [
            'name' => $this->getName($orderData),
            'phones' => $this->getPhones($orderData),
            'address' => $this->data['address'] ?? $orderData['billing']['address_1'],
            'wilaya' => $this->processWilaya($orderData),
            'commune' => $this->data['commune'] ?? $orderData['billing']['city'],
            'price' => $this->data['price'] ?? ($orderData['total'] - $orderData['shipping_total']),
            'quantity' => $this->totalQuantity ?? 1,
            'note' => $this->getOrderNote($orderData)
        ];
    }

    private function getName($orderData)
    {
        if (!empty($this->data['fname']) || !empty($this->data['lname'])) {
            return trim($this->data['fname'] . ' ' . $this->data['lname']);
        }

        return trim($orderData['billing']['first_name'] . ' ' . $orderData['billing']['last_name']);
    }

    private function getPhones($orderData)
    {
        $phone = $this->data['phone'] ?? $orderData['billing']['phone'];
        return explode('/', $phone);
    }

    private function processWilaya($orderData)
    {
        $wilaya = $this->data['wilaya'] ?? $orderData['billing']['state'];
        return (int) str_replace('DZ-', '', $wilaya);
    }

    private function getOrderNote($orderData)
    {
        if (!empty($this->data['remarque'])) {
            return $this->data['remarque'];
        }

        $note = stripslashes(get_option('wc_bordreau_default-shipping-note'));
        return $note ?: 'Commande #' . $this->formData->get_id();
    }

    private function sendApiRequest($data, $tracking = null)
    {
        $client = new Client();
        $method = $tracking ? 'PUT' : 'POST';
        $endpoint = $this->provider['api_url'] . '/Colis';

        if ($tracking) {
            $endpoint .= '/' . $tracking;
        }

        try {
            $response = $client->request($method, $endpoint, [
                'body' => json_encode($data),
                'verify' => false,
                'headers' => [
                    'token' => $this->api_token,
                    'key' => $this->api_key,
                    'Content-Type' => 'application/json'
                ]
            ]);

            $responseArray = json_decode((string)$response->getBody(), true);

            if ($tracking) {
                return $responseArray['Colis'] ?? null;
            }

            return isset($responseArray['Colis'][0]) ? $responseArray['Colis'][0] : null;

        } catch (RequestException $e) {
            throw new Exception(
                sprintf(
                    __("API request failed: %s", 'wc-bordereau-generator'),
                    $e->getMessage()
                )
            );
        }
    }

    public function save($post_id, array $response, bool $update = false)
    {
        $trackingNumber = $response['Tracking'];

        $current_tracking_data = [];

        $etq = get_rest_url( null, 'woo-bordereau/v1/slip/print/'.$this->provider['slug'].'/'.$post_id );

        $label = $response['label'];

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

            update_post_meta($post_id, '_shipping_tracking_label', $label);

        } else {

            update_post_meta($post_id, '_shipping_tracking_number', wc_clean($trackingNumber));
            update_post_meta($post_id, '_shipping_tracking_label', wc_clean($etq));
            update_post_meta($post_id, '_shipping_label', $label);
            update_post_meta($post_id, '_shipping_tracking_method', $this->provider['slug']);

            $order = wc_get_order($post_id);

            if($order) {
                $order->update_meta_data('_shipping_tracking_number', wc_clean($trackingNumber));
                $order->update_meta_data('_shipping_tracking_label', wc_clean($etq));
                $order->update_meta_data('_shipping_label', $label);
                $order->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
                $order->save();
            }
        }

        $current_tracking_data = [
            'tracking_number' => wc_clean($trackingNumber),
            'carrier_slug' => $this->provider['slug'],
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
        $url = $this->provider['api_url'].'/Colis/Tracking/'. $tracking_number;

        $client = new Client();

        $response = $client->request('GET', $url, [
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
        // docs: POST /Colis/Liste — {"Colis":[{"Tracking":"..."}]}
        $url = $this->provider['api_url'] . '/Colis/Liste';

        $client = new Client();

        $data = [
            "Colis" => [
                ["Tracking" => $tracking_number]
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

        $response_body  = (string) $response->getBody();
        $response_array = json_decode($response_body, true);

        $detailColis = $response_array['Colis'][0] ?? [];

        // Use Avancement as the human-readable last status (matches /Historique)
        $last_status = $detailColis['Avancement'] ?? ($detailColis['Situation'] ?? null);

        $detail = [];
        $detail[0]['address']        = $detailColis['Adresse']       ?? null;
        $detail[0]['phone']          = $detailColis['Mobile_1']       ?? null;
        $detail[0]['fname']          = $detailColis['NomComplet']     ?? null;
        $detail[0]['free']           = false;
        $detail[0]['exchange']       = ($detailColis['Echange'] ?? 0) == 1;
        $detail[0]['stopdesk']       = ($detailColis['Stopdesk'] ?? 0) == 1;
        $detail[0]['last_status']    = $last_status;
        $detail[0]['last_status_key'] = $detailColis['Situation']     ?? $last_status;
        $detail[0]['order_id']       = $detailColis['ID_Externe']     ?? null;
        $detail[0]['price']          = (int) ($detailColis['Total']   ?? 0);
        $detail[0]['commune']        = $detailColis['Commune_Bureau'] ?? null;
        $detail[0]['wilaya']         = $detailColis['IDWilaya']       ?? null;

        if ($last_status) {
            BordereauGeneratorAdmin::update_tracking_status($this->formData, $last_status, 'single_tracking');
        }

        wp_send_json([
            'tracking' => $tracking_number,
            'detail'   => $detail
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
        $detail[0]['last_status'] = $item[0]['status'];
        $detail[0]['last_status_key'] = $item[0]['status'];
        $detail[0]['order_id'] = $item[0]['reference'];
        $detail[0]['price'] = $item[0]['montant'];
        $detail[0]['commune'] = $item[0]['commune'];
        $detail[0]['wilaya'] = $item[0]['wilaya_id'];

        return  $detail;
    }


    /**
     * @return array|mixed|null
     */
    public function get_wilayas()
    {

        $path = Functions::get_path($this->provider['slug'].'_'.WC_BORDEREAU_GENERATOR_VERSION.'_wilayas.json');

        if (! file_exists($path)) {
            $content = $this->get_wilaya_from_provider();
            file_put_contents($path, $content);
        }

        $wilayas = json_decode(file_get_contents($path), true);

        if ($wilayas == null || count($wilayas) == 0) {
            return $this->get_wilaya_from_provider();
        }

        return  $wilayas;
    }


    /**
     * @return array|mixed|null
     */
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
        // Use native GET /Commune/{wilaya_id} endpoint — response: {"Commune":[{"Commune":"...","Wilaya":N,"Code_postal":N,"Active":true}]}
        // Cache per wilaya per day to avoid hammering the API
        $path = Functions::get_path(
            $this->provider['slug'] . '_communes_' . $wilaya_id . '_' . date('Y-m-d') . '.json'
        );

        if (! file_exists($path)) {
            $url  = $this->provider['api_url'] . '/Commune/' . $wilaya_id;
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => 'GET',
                CURLOPT_HTTPHEADER     => [
                    'Key: '   . $this->api_key,
                    'Token: ' . $this->api_token,
                ],
            ]);
            $response = curl_exec($curl);
            curl_close($curl);

            $decoded = json_decode($response, true);
            $items   = $decoded['Commune'] ?? [];
            file_put_contents($path, json_encode($items));
        }

        $communes = json_decode(file_get_contents($path), true);

        if (! is_array($communes) || count($communes) === 0) {
            return [];
        }

        $communesResult = [];
        foreach ($communes as $i => $item) {
            if (! ($item['Active'] ?? true)) {
                continue; // skip inactive communes
            }
            $communesResult[$i]['id']   = $item['Commune'];
            $communesResult[$i]['name'] = $item['Commune'];
        }

        return $communesResult;
    }

    /**
     * Get stop-desk centers for a given wilaya from /Stopdesk endpoint.
     * Response shape: { "Commune": [{ "Code": "1A", "Libelle": "Adrar", "Ville": 1, "Adresse": "..." }] }
     *
     * @param int|string $wilaya  Wilaya number or "DZ-01" formatted string
     * @return array              Array of ['id' => Code, 'name' => Libelle, 'address' => Adresse]
     * @since 4.20.0
     */
    public function get_centers($wilaya): array
    {
        $wilaya = (int) str_replace('DZ-', '', $wilaya);

        if (empty($this->api_key) || empty($this->api_token)) {
            return [];
        }

        // Use /Stopdesk/{wilaya} to get only centers for this wilaya directly from the API
        // Daily cache keyed per wilaya
        $path = Functions::get_path(
            $this->provider['slug'] . '_centers_' . $wilaya . '_' . date('Y-m-d') . '.json'
        );

        if (! file_exists($path) || file_get_contents($path) === 'null') {
            $url  = $this->provider['api_url'] . '/Stopdesk/' . $wilaya;
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => 'GET',
                CURLOPT_HTTPHEADER     => [
                    'Key: '   . $this->api_key,
                    'Token: ' . $this->api_token,
                ],
            ]);
            $response = curl_exec($curl);
            curl_close($curl);

            $decoded  = json_decode($response, true);
            $communes = $decoded['Commune'] ?? [];
            file_put_contents($path, json_encode($communes));
        }

        $json = json_decode(file_get_contents($path), true);

        if (! is_array($json)) {
            return [];
        }

        $result = [];
        foreach ($json as $item) {
            $result[] = [
                'id'      => $item['Code'],
                'name'    => $item['Libelle'],
                'address' => $item['Adresse'],
            ];
        }

        return $result;
    }

    /**
     * @param $tracking_number
     * @param $post_id
     * @return void
     * @since 1.3.0
     */
    public function delete($tracking_number, $post_id)
    {
        // delete the package if exist — docs: PUT /Api_v1/Supprimer
        $url = $this->provider['api_url'] . '/Supprimer';

        $data = [
            "Colis" => [
                [
                    "Tracking" => $tracking_number
                ]
            ]
        ];


        $request = wp_safe_remote_post(
            $url,
            [
                'headers'     => [
                    'Content-Type'  => 'application/json',
                    'token' => $this->api_token,
                    'key' => $this->api_key,
                ],
                'body'        => wp_json_encode($data),
                'method'      => 'PUT',
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

        return json_decode($body, true);
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

        // Detect stop-desk center from shipping method name — mirrors NoestShipping::get_settings()
        $AgencyName = $order->get_shipping_method();
        $centers    = $this->get_centers($order->get_billing_state());
        $center     = $order->get_meta('_billing_center_id', true);

        $found = array_filter($centers, function ($v) use ($AgencyName) {
            return strtolower($v['name']) === strtolower(urldecode($AgencyName));
        });

        if ($found) {
            $center = reset($found)['id'];
        }

        $data = [];
        if ($key) {
            $data['total_calculate'] = get_option($key) ? get_option($key) : 'with-shipping';
            $data['type'] = $provider['type'];
            $data['total'] = $cart_value;
            $data['order_number'] = $order->get_order_number();
            $data['products_string'] = $productString;
            $data['center_name'] = $center ?? null;
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


    /**
     * @param $response_array
     * @return array
     */
    private function format_tracking($response_array)
    {

        $result = [];
		$key = 0;

	    array_map(function ($item) use (&$result, &$key) {
		    $result[$key]['date'] = $item['Date_Creation_HD'];
		    $result[$key]['commune'] = $item['Bureau'];
		    $result[$key]['wilaya'] = $item['Ville'];
		    $result[$key]['status'] = $item['Avancement'];
		    $result[$key]['exception'] = $item['ServiceClient'];
		    $key++;
			return $result;
		}, $response_array['Historique']);

        return $result;

    }

    private function get_wilaya_from_provider()
    {
        if ($this->api_token && $this->api_key) {

            $url = $this->provider['api_url'].'/Tarification';

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
                    'Key: ' . $this->api_key,
                    'Token: ' . $this->api_token
                ),
            ));

            $response = curl_exec($curl);
            curl_close($curl);


            if (empty($response)) {
                wp_send_json([
                    'error' => true,
                    'message'=> __("An error has occurred. Please see log file ", 'woo-bordereau-generator')
                ], 401);
            }

            $result = [];
            $prices = json_decode($response, true);
            foreach ($prices['Wilaya'] as $key => $item) {
                $result[$key]['id'] = $item['ID'];
                $result[$key]['name'] = $item['Libellé'];
            }

            return $result;
        }

        return null;


    }


}
