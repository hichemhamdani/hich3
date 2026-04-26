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

use DateTime;
use Exception;
use WC_Order;
use WC_Product;
use WooBordereauGenerator\Functions;
use WooBordereauGenerator\Admin\BordereauGeneratorAdmin;
use WooBordereauGenerator\Helpers;
use WP_Error;

class UPSShipping extends BordereauProvider implements BordereauProviderInterface
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
        $this->api_key         = get_option($provider['slug'].'_api_key');
        $this->api_token        = get_option($provider['slug'].'_api_token');
        $this->provider = $provider;
        $this->data = $data;
    }


    /**
     * Generate tracking number from yalidine
     * @since 1.0.0
     * @return mixed|void
     */
    public function generate()
    {

        $params = $this->formData->get_data();
        $orderId = $this->formData->get_id();



        $store_country = get_option('woocommerce_default_country');

        $split_country = explode(":", $store_country);
        $country_code = isset($split_country[0]) ? $split_country[0] : '';
        $state_code = isset($split_country[1]) ? $split_country[1] : '';

        $wilayas = $this->get_wilayas();


        if (is_string($wilayas)) {
            $wilayas = json_decode($wilayas, true);
        }

        if (isset($this->data['wilaya'])) {
            $wilayaId = (int) str_replace("DZ-", "", $this->data['wilaya']);
            $wilaya = array_search($wilayaId, array_column($wilayas, 'id'));
        } else {
            $wilayaId = (int) str_replace("DZ-", "", $params['billing']['state']);
            $wilaya = array_search($wilayaId, array_column($wilayas, 'id'));
        }

        if ($wilaya === null) {
            wp_send_json([
                'error' => true,
                'message'=> __("Wilaya has not been selected", 'wc-bordereau-generator')
            ], 422);
            return;
        }

        // get the wilaya name and id
        $wilaya = $wilayas[$wilaya];

        if (isset($this->data['products'])) {
            $productString = $this->data['products'];
        } else {
            $products = $params['line_items'];
            $productString = Helpers::generate_products_string($products);
        }


        $communeName = stripslashes($this->data['commune'] ?? $params['billing']['city']);

        $lastName = $this->data['lname'] ?? $params['billing']['last_name'];
        $firstName = $this->data['fname'] ?? $params['billing']['first_name'];

        $name = $firstName. ' ' .$lastName;

        $ups_account = get_option($this->provider['slug'].'_account_number');

        $order = wc_get_order($orderId);

        $address  = $this->data['address'] ?? $params['billing']['address_1'];

        if (! $address) {
            $address = $communeName;
        }

        $payload = array(
            "ShipmentRequest" => array(
                "Request" => array(
                    "SubVersion" => "1801",
                    "RequestOption" => "nonvalidate",
                    "TransactionReference" => array(
                        "CustomerContext" => ""
                    )
                ),
                "Shipment" => array(
                    "PackageID" => $order->get_order_number(),
                    "Description" => get_option( 'woocommerce_store_name' ),
                    "Shipper" => array(
                        "Name" => get_option($this->provider['slug'].'_store_name'),
                        "Phone" => array(
                            "Number" => get_option($this->provider['slug'].'_phone_number'),
                        ),
                        "ShipperNumber" => $ups_account,
                        "Address" => array(
                            "AddressLine" => array(
                                get_option( 'woocommerce_store_address' ),
                                get_option( 'woocommerce_store_address_2' )
                            ),
                            "City" => get_option( 'woocommerce_store_city' ),
                            "State" => $state_code,
                            "PostalCode" =>  get_option( 'woocommerce_store_postcode' ),
                            "CountryCode" => $country_code
                        )
                    ),
                    "ShipTo" => array(
                        "Name" =>   Helpers::arabicToLatin($name),
                        "Phone" => array(
                            "Number" => $this->data['phone'] ?? $params['billing']['phone']
                        ),
                        "Address" => array(
                            "AddressLine" => Helpers::arabicToLatin( $address),
                            "City" => $communeName,
                            "State" => $wilaya['name'],
                            "CountryCode" => "DZ"
                        ),
                    ),
                    "ShipFrom" => array(
                        "Name" => get_option($this->provider['slug'].'_store_name'),
                        "Phone" => array(
                            "Number" => get_option($this->provider['slug'].'_phone_number')
                        ),
                        "Address" => array(
                            "AddressLine" => array(
                                get_option( 'woocommerce_store_address' ),
                                get_option( 'woocommerce_store_address_2' ),
                                get_option( 'woocommerce_store_city' )
                            ),
                            "City" => get_option( 'woocommerce_store_city' ),
                            "State" => $state_code,
                            "PostalCode" => get_option( 'woocommerce_store_postcode' ),
                            "CountryCode" => $country_code
                        )
                    ),
                    "PaymentInformation" => array(
                        "ShipmentCharge" => array(
                            "Type" => "01",
                            "BillShipper" => array(
                                "AccountNumber" => $ups_account
                            )
                        )
                    ),
                    "Service" => array(
                        "Code" => "65",
                    ),
                    "InvoiceLineTotal" => array(
                        "CurrencyCode" => "DZD",
                        "MonetaryValue" => $this->data['price'] ?? $params['total']
                    ),
                    "Package" => array(
                        "Description" => $productString,
                        "ReferenceNumber" => [
                            "Value" => 'COD/'.(int) ($this->data['price'] ?? $params['total']). 'DA'
                        ],
                        "Packaging" => array(
                            "Code" => "02",
                            "Description" => $productString
                        ),
                        "Dimensions" => array(
                            "UnitOfMeasurement" => array(
                                "Code" => "CM",
                                "Description" => "Centimeters"
                            ),
                            "Length" => "10",
                            "Width" => "10",
                            "Height" => "10"
                        ),
                        "PackageWeight" => array(
                            "UnitOfMeasurement" => array(
                                "Code" => "KGS",
                                "Description" => "Kilograms"
                            ),
                            "Weight" => "1"
                        )
                    ),
                    "ReferenceNumber" => [
                        "BarCodeIndicator" => 1,
                        "Value" => 'DZA 247 3-00'
                    ]
                ),
            )
        );

        try {

            $curl = curl_init();

            $request = new UPSRequest($this->provider);


            $query = array(
                "additionaladdressvalidation" => "string"
            );


            curl_setopt_array($curl, [
                CURLOPT_HTTPHEADER => [
                    "Authorization: Bearer ".$request->get_current_token(),
                    "Content-Type: application/json",
                ],
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_URL => $this->provider['api_url']."api/shipments/" . UPSRequest::VERSION . "/ship?" . http_build_query($query),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "POST",
            ]);

            $response = curl_exec($curl);
            $error = curl_error($curl);
            if ($error) {
                throw new \ErrorException($error);
            }

            curl_close($curl);

            $response_array = json_decode($response, true);

            if (isset($response_array['response']['errors']) && count($response_array['response']['errors'])) {
                wp_send_json([
                    'error' => true,
                    'message'=>  $response_array['response']['errors'][0]['message']
                ], 401);
            }

            return $response_array;
        } catch (\ErrorException $e) {
            new WP_Error($e->getMessage());
            wp_send_json([
                'error' => true,
                'message'=>  __("An error has occurred. Please see log file ", 'wc-bordereau-generator')
            ], 401);
        }
    }

    /**
     * Get Cities from specific State
     * @param int $wilaya_id
     * @param bool $hasStopDesk
     *
     * @return array
     */
    public function get_communes(int $wilaya_id, bool $hasStopDesk)
    {

        $path = Functions::get_path($this->provider['slug'].'_'.WC_BORDEREAU_GENERATOR_VERSION.'_city'.$wilaya_id.'.json');


        if (! file_exists($path)) {
            $content = file_get_contents('https://amineware.me/api/commune-noest/'.$wilaya_id);
            file_put_contents($path, $content);
        }

        $communes = json_decode(file_get_contents($path), true);

        if (! is_array($communes) && count($communes) == 0) {
            $content = file_get_contents('https://amineware.me/api/commune-noest/'.$wilaya_id);
            file_put_contents($path, $content);
            $communes = json_decode(file_get_contents($path), true);
        }

        $communesResult = [];

        foreach ($communes as $i => $item) {
            $communesResult[$i]['id'] = $item['nom'];
            $communesResult[$i]['name'] = $item['nom'];

        }

        return $communesResult;
    }

    /**
     * @param $post_id
     * @param array $response
     * @param bool $update
     * @throws Exception
     * @since 1.1.0
     */
    public function save($post_id, array $response, bool $update = false)
    {


        if (isset($response['ShipmentResponse']['Response']['ResponseStatus']['Description']) && $response['ShipmentResponse']['Response']['ResponseStatus']['Description'] == 'Success') {

            $trackingNumber = $response['ShipmentResponse']['ShipmentResults']['ShipmentIdentificationNumber'];
            $labelImage = $response['ShipmentResponse']['ShipmentResults']['PackageResults']['ShippingLabel']['GraphicImage'];
            $label = get_rest_url(null, 'woo-bordereau/v1/slip/print/'.$this->provider['slug'].'/'.$post_id);

            // label1Z9780R80426281030.gif
            $path = Functions::get_path('label'.$trackingNumber.'.gif');
            file_put_contents($path, file_get_contents('data:image/gif;base64,'.$labelImage));

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
                update_post_meta($post_id, '_shipping_tracking_label', wc_clean($label));
                update_post_meta($post_id, '_shipping_label', wc_clean($label));
                update_post_meta($post_id, '_shipping_tracking_url', 'https://'.$this->provider['url'].'/track?tracknum='. wc_clean($trackingNumber));
                update_post_meta($post_id, '_shipping_tracking_method', $this->provider['slug']);

                $order = wc_get_order($post_id);

                if($order) {
                    $order->update_meta_data('_shipping_tracking_number', wc_clean($trackingNumber));
                    $order->update_meta_data('_shipping_tracking_label', wc_clean($label));
                    $order->update_meta_data('_shipping_label', wc_clean($label));
                    $order->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
                    $order->save();
                }
            }

            $track = 'https://'.$this->provider['url'].'/track?tracknum='. wc_clean($trackingNumber);

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

            wp_send_json([
                'message' => __("Tracking number has been successfully created", 'wc-bordereau-generator'),
                'provider' => $this->provider['name'],
                'tracking' => $current_tracking_data,
                'label' => wc_clean($label)
            ], 200);

        }



    }

    /**
     * Fetch the tracking information
     * @param $tracking_number
     * @param null $post_id * @return void
     *@since 1.1.0
     */
    public function track($tracking_number, $post_id = null)
    {
        $curl = curl_init();

        $request = new UPSRequest($this->provider);

        $query = array(
            "locale" => "fr_FR",
            "returnSignature" => "false"
        );


        curl_setopt_array($curl, [
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer ".$request->get_current_token(),
                "Content-Type: application/json",
                "transId: ". Helpers::generateRandomString(),
                "transactionSrc: testing",
            ],
            CURLOPT_URL => $this->provider['api_url']."api/track/v1/details/" . $tracking_number . "?" . http_build_query($query),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "GET",
        ]);

        $response = curl_exec($curl);
        $error = curl_error($curl);

        wp_send_json([
            'tracking' => $tracking_number,
            'events' => Helpers::sort_tracking($this->format_tracking(json_decode($response, true)))
        ]);
    }

    /**
     * Fetch the tracking information
     * @param string $tracking_number
     * @return array
     *@since 1.1.0
     */
    public function track_detail($tracking_number)
    {
        $request = new YalidineRequest($this->provider);
        return $this->format_tracking($request->get($this->provider['api_url'] . "/histories?tracking=" . $tracking_number, false));
    }


    /**
     * @param $tracking_number
     * @since 1.2.0
     * @return void
     */
    public function detail($tracking_number, $post_id)
    {

        $request = new YalidineRequest($this->provider);
        $details = $this->format_tracking_detail($request->get($this->provider['api_url'] . "/parcels/" . $tracking_number, false));


        if (isset($details[0]['last_status'])) {
            BordereauGeneratorAdmin::update_tracking_status($this->formData, $details[0]['last_status'], 'single_tracking');
        }

        wp_send_json([
            'tracking' => $tracking_number,
            'detail' => $details
        ]);
    }


    /**
     * Format the response of the tracking
     * @param $response_array
     * @since 1.1.0
     * @return array
     */
    private function format_tracking($response_array): array
    {
        $result = [];

        $events = $response_array['trackResponse']['shipment'][0]['package'][0]['activity'] ?? [];

        foreach ($events as $key => $item) {

            $date = $item['date'].' '. $item['time'];
            $dateTime = DateTime::createFromFormat('Ymd His', $date);
            $result[$key]['date'] = $dateTime->format('Y-m-d H:i:s');
            $result[$key]['status'] = $item['status']['description'];

        }

        return $result;
    }

    /**
     * @param $response
     * @since 1.2.0
     * @return array
     */
    private function format_tracking_detail($response): array
    {
        $detail = [];

        foreach ($response['data'] as $i => $item) {
            $detail[$i]['address'] = $item['address'];
            $detail[$i]['phone'] = $item['contact_phone'];
            $detail[$i]['fname'] = $item['familyname'];
            $detail[$i]['lname'] = $item['firstname'];
            $detail[$i]['free'] = $item['freeshipping'];
            $detail[$i]['exchange'] = $item['has_exchange'];
            $detail[$i]['stopdesk'] = $item['is_stopdesk'];
            $detail[$i]['stopdesk_id'] = $item['stopdesk_id'];
            $detail[$i]['last_status'] = $item['last_status'];
            $detail[$i]['last_status_key'] = $item['last_status'];
            $detail[$i]['order_id'] = $item['order_id'];
            $detail[$i]['price'] = $item['price'];
            $detail[$i]['do_insurance'] = $item['do_insurance'];
            $detail[$i]['declared_value'] = $item['declared_value'];
            $detail[$i]['products'] = $item['product_list'];
            $detail[$i]['product_to_collect'] = $item['product_to_collect'];
            $detail[$i]['commune'] = $item['to_commune_name'];
            $detail[$i]['wilaya'] = $item['to_wilaya_id'];
        }

        return  $detail;
    }



    /**
     * @since 1.2.2
     * @return mixed
     */
    public function get_wilayas()
    {
        $path = Functions::get_path($this->provider['slug']."_wilaya".WC_BORDEREAU_GENERATOR_VERSION.".json");

        if (file_exists($path)) {
            $wilayas = json_decode(file_get_contents($path), true);
        } else {
            $response = $this->get_wilaya_from_provider();
            file_put_contents($path, json_encode($response));
            return $response;
        }

        return $wilayas;
    }

    /**
     * @param $provider
     *
     * @return array
     *@since 1.2.2
     */
    public function get_wilayas_by_provider($provider)
    {
        $path = Functions::get_path($this->provider['slug'].'_wilaya'.WC_BORDEREAU_GENERATOR_VERSION.'.json');

        $wilayasResult = [];

        if (file_exists($path)) {
            $result = json_decode(file_get_contents($path), true);

            if (is_string($result)) {
                $result = json_decode($result, true);
            }

            if ($result == null || count($result) == 0) {
                $result = $this->get_wilaya_from_provider();
            }

        } else {
            $response_array = $this->get_wilaya_from_provider();
            file_put_contents($path, json_encode($response_array));
            $result = $response_array;
        }



        if (isset($result)) {
            foreach ($result as $i => $wilaya) {
                $wilayasResult[ $i ]['name'] = $wilaya['name'];
                $wilayasResult[ $i ]['id']   = $wilaya['id'];
            }

            return $wilayasResult;
        }

        return  $wilayasResult;
    }

    /**
     * @return bool|mixed|string
     */
    private function get_wilaya_from_provider()
    {
        return file_get_contents('https://amineware.me/api/wilayas');

    }


    /**
     * Return the settings we need in the metabox
     * @param array $data
     * @param null $option
     * @return array
     * @since 1.2.4
     */
    public function get_settings(array $data, $option = null)
    {
        $provider = $this->provider;
        $order = $this->formData;
        $fields = $provider['fields'];

        $key = $fields['total']['choices'][0]['name'] ?? null;
        $recalculate = $fields['recalculate']['choices'][0]['name'] ?? null;

        $params = $order->get_data();
        $products = $params['line_items'];

        $productString = Helpers::generate_products_string($products);

        $total = get_option($key) ? get_option($key) : 'with-shipping';

        $recalculateCase = get_option($recalculate) ? get_option($recalculate) : 'recalculate-without-shipping';

        $cart_value = 0;

        if ($total == 'with-shipping') {
            $cart_value = (float) $order->get_subtotal() + $order->get_total_tax() + $order->get_total_fees() +  $order->get_total_shipping();
        } elseif ($total === 'without-shipping') {
            $cart_value =  ((float) $order->get_total() + $order->get_total_tax() + $order->get_total_fees()) - $order->get_total_shipping();
        }

        // Sync the price of the shipping and add the diff to the cart total

        if($recalculateCase == 'recalculate-with-shipping') {

            $shippingCost = (int) $order->get_shipping_total();

            $shippingCostFromProvider = $this->get_shipping_cost($data['state']);

            $shippingExtraCostFromProvider = array_values($this->get_extra_shipping_cost($data['city'], $data['state'])) ?? false;

            if($option == 'stopdesk') {
                $shippingCostFromProviderPrice = $shippingCostFromProvider['desk_fee'];
            } else {
                $shippingCostFromProviderPrice = $shippingCostFromProvider['home_fee'];
            }

            if ($shippingExtraCostFromProvider && isset($shippingExtraCostFromProvider[0]['commune'])) {

                $cart_value = $cart_value + ($shippingCost - ($shippingCostFromProviderPrice + $shippingExtraCostFromProvider[0]['commune']));
            } else {
                $cart_value = $cart_value + ($shippingCost - $shippingCostFromProviderPrice);
            }

        }

        $data = [];
        if ($key) {
            $data['total_calculate'] = get_option($key) ? get_option($key) : 'with-shipping';
            $data['type'] = $provider['type'];
            $data['total'] = $cart_value;
            $data['total_without_shipping'] = (float) $order->get_subtotal() + $order->get_total_tax() + $order->get_total_fees();
            $data['extra_commune'] = $shippingExtraCostFromProvider[0]['commune'] ?? 0;
            $data['shipping_fee'] = $shippingCostFromProviderPrice ?? 0;
            $data['order_number'] = $order->get_order_number();
            $data['products_string'] = $productString;
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
        $request = new YalidineRequest($this->provider);

        return $request->post($this->provider['api_url'] . "/parcels/". $tracking_number, [], YalidineRequest::DELETE);
    }



    /**
     * @since 2.2.0
     * @return void
     */
    public function get_slip()
    {

        $trackingNumber = get_post_meta($this->formData->get_id(), '_shipping_tracking_number', true);

        if(! $trackingNumber) {
            $trackingNumber = $this->formData->get_meta('_shipping_tracking_number');
        }

        $path = Functions::get_path('label'.$trackingNumber.'.gif');

        if (file_exists($path)) {
            $content = file_get_contents($path);

            $type = pathinfo($path, PATHINFO_EXTENSION);
            $labelImage = 'data:image/' . $type . ';base64,' . base64_encode($content);

            header('Content-Type: text/html; charset=utf-8');
            include_once('ups-label.php');
        }

        exit;
    }

    private function get_shipping_cost(?string $get_billing_state)
    {

        $path = Functions::get_path($this->provider['slug'].'_'.date('z') .'_deliveryfees.json');

        if (file_exists($path)) {
            $fees = json_decode(file_get_contents($path), true);
        } else {
            $response = $this->get_shipping_cost_from_provider();
            file_put_contents($path, json_encode($response));
            $fees =  $response;
        }

        $wilaya_id_to_search = str_replace('DZ-', '', $get_billing_state);
        $wilaya_id_to_search = (int)$wilaya_id_to_search;
        $matched_array = null;

        foreach ($fees['data'] as $item) {
            if ($item['wilaya_id'] == $wilaya_id_to_search) {
                $matched_array = $item;
                break;
            }
        }


        return $matched_array;
    }

    private function get_shipping_cost_from_provider()
    {
        $request = new YalidineRequest($this->provider);
        return $request->get($this->provider['api_url'].'/deliveryfees?page_size=1000');
    }

    private function get_extra_shipping_cost(string $city, string $state)
    {

        $response = $this->get_extra_fee($state);

        $found =  array_filter($response, function ($v, $k) use ($city) {
            return $v['name'] == $city;
        }, ARRAY_FILTER_USE_BOTH);


        return $found;
    }

    /**
     * @param string $state
     * @return bool|mixed|string
     */
    public function get_extra_fee_from_provider(string $state)
    {
        $wilaya = str_replace('DZ-', '', $state);


        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://amineware.me/api/cities-with-fee?token=1Q0cWtxFHLwMAffh8KeRzNC9QXiZju77&wilaya=' . $wilaya,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'X-API-ID: ' . $this->api_key,
                'X-API-TOKEN: ' . $this->api_token,
                "Content-Type: application/json"
            ),
        ));

        $result = curl_exec($curl);

        if ($result === false) {
            if (curl_errno($curl)) {
                $error_msg = curl_error($curl);

                new WP_Error($error_msg);

                wp_send_json([
                    'error' => true,
                    'message' => $error_msg
                ], 401);
            }
        }
        return $result;
    }

    private function get_extra_fee(string $state)
    {

        $path = Functions::get_path($this->provider['slug'].'_commune_with_fee_'.WC_BORDEREAU_GENERATOR_VERSION.'_'.$state.'.json');


        if (file_exists($path)) {
            $wilayas = json_decode(file_get_contents($path), true);
        } else {
            $response = $this->get_extra_fee_from_provider($state);
            file_put_contents($path, $response);
            return json_decode($response, true);
        }

        return $wilayas;
    }
}
