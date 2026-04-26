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

class ElogistiaShipping extends BordereauProvider implements BordereauProviderInterface
{

    /**
     * @var string
     */
    private string $api_key;


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
        $this->api_key         = get_option($provider['slug'].'_api_token');
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
        $wilayas = $this->get_wilayas();

        if (isset($this->data['wilaya'])) {
            $wilayaId = (int) str_replace("DZ-", "", $this->data['wilaya']);
            $wilaya = array_search($wilayaId, array_column($wilayas['body'], 'Id'));
        } else {
            $wilayaId = (int) str_replace("DZ-", "", $params['billing']['state']);
            $wilaya = array_search($wilayaId, array_column($wilayas['body'], 'Id'));
        }

        if ($wilaya === null) {
            wp_send_json([
                'error' => true,
                'message'=> __("Wilaya has not been selected", 'wc-bordereau-generator')
            ], 422);
            return;
        }


        // get the wilaya name and id
        $wilaya = $wilayas['body'][$wilaya];

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


        $stop_desk = 1;

        if ($this->formData->has_shipping_method('local_pickup') && $this->data['shipping_type'] === 'stopdesk') {
            $stop_desk = 2;
        }

        if ($this->data['shipping_type'] === 'stopdesk') {
            $stop_desk = 2;
        }

        $commune_name = stripslashes($this->data['commune'] ?? $params['billing']['city']);

        // Check if shipping method name matches a center name
        // Format: "[prefix] [center_name]" e.g. "Elogistia Local Pickup Agence Alger"
        if ($stop_desk == 2 && empty($this->data['center'])) {
            $found = false;
            // check if the name is an agence check in the centers list
            $name = $this->formData->get_shipping_method();
            $centers = $this->get_all_centers();

            if (is_array($centers)) {
                $result = array_filter($centers, function($agency) use ($name) {
                    $prefix = get_option($this->provider['slug'] . '_pickup_local_label');
                    $agencyName = sprintf("%s %s", $prefix, $agency['name']);
                    return trim($agencyName) === trim($name);
                });
                $found = !empty($result) ? reset($result) : null;
            }

            if ($found) {
                $this->data['center'] = $found['id'];
                $commune_name = $found['commune_name'];
            }
        }

        // Fallback: Auto-select center if not provided
        if ($stop_desk == 2 && empty($this->data['center'])) {
            $centers = $this->get_all_centers();
            if (is_array($centers) && count($centers) > 0) {
                // Step 1: Try to find a center in the selected commune
                $result = array_filter($centers, function($agency) use ($commune_name) {
                    return $agency['commune_name'] == $commune_name;
                });
                $found = !empty($result) ? reset($result) : null;

                // Step 2: If no center in commune, filter by wilaya_id
                if (!$found) {
                    $result = array_filter($centers, function($agency) use ($wilayaId) {
                        return $agency['wilaya_id'] == $wilayaId;
                    });
                    $found = !empty($result) ? reset($result) : null;
                }

                // Step 3: Set the center and update commune
                if ($found && isset($found['id'])) {
                    $this->data['center'] = $found['id'];
                    $commune_name = $found['commune_name'];
                }
            }
        }

        // If center is provided in the form data, use its commune
        if ($stop_desk == 2 && !empty($this->data['center'])) {
            $centers = $this->get_all_centers();
            if (is_array($centers)) {
                $result = array_filter($centers, function($agency) {
                    return $agency['id'] == $this->data['center'] || $agency['name'] == $this->data['center'];
                });
                $found = !empty($result) ? reset($result) : null;
                if ($found) {
                    $commune_name = $found['commune_name'];
                }
            }
        }

        $modeDeLivraison = 1;

        if (isset($this->data['product_exchanged'])) {
            if (isset($this->data['operation_type']) && $this->data['operation_type'] == 2) {
                $modeDeLivraison = 4;
            }
        }

        $exchangeName = '';

        if (isset($this->data['product_exchanged'])) {
            if (isset($this->data['operation_type']) && $this->data['operation_type'] == 2) {
                $exchangeName = $this->data['product_exchanged'];
            }
        }

        $phone = $this->data['phone'] ?? $params['billing']['phone'];
        $phone = Helpers::clean_phone_number($phone);

        if ($this->data['remarque']) {
            $note = $this->data['remarque'];
        } else {
            $note = stripslashes(get_option('wc_bordreau_default-shipping-note'));
        }

        $data = [
            "apiKey" => $this->api_key,
            "IdCommande" => $orderId,
            "name" => $this->data['lname'] ?? $params['billing']['last_name'],
            "firstname" => $this->data['fname'] ?? $params['billing']['first_name'],
            "phone" => $phone,
            "address" => $this->data['address'] ?? $params['billing']['address_1'] ?? $params['billing']['city'] ,
            "commune" => $commune_name,
            "wilaya" => $wilaya['Id'],
            "mail" => get_option('woocommerce_store_email'),
            "remarque" => $note,
            "product" => $productString,
            "fraisDeLivraison" => $params['shipping_total'],
            "price" => $this->data['price'] ?? $params['total'] - $params['shipping_total'], // todo extrac the price calculation to extanal function depending of the user choice
            "stop_desk" => $stop_desk,
            "modeDeLivraison" => $modeDeLivraison,
            "exchangeName" => $exchangeName,
        ];

        $request = new ElogistiaRequest($this->provider);

        try {

            $query_string = http_build_query($data);

            $url = $this->provider['api_url'] . "/insertCommande?". $query_string;

            $response = $request->post($url, $data);

            // check if the there is no error
            if (isset($response['error'])) {
                new WP_Error($response['error']['message']);
                wp_send_json([
                    'error' => true,
                    'message'=>  $response['error']['message']
                ], 401);
            }

            return $response;
        } catch (\ErrorException $e) {
            new WP_Error($e->getMessage());
            wp_send_json([
                'error' => true,
                'message'=>  __("An error has occurred. Please see log file ", 'wc-bordereau-generator')
            ], 401);
        }
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

        if(isset($response['success'])) {
            $trackingNumber = $response['success'];
            $label = $this->provider['api_url'].'/printBordereau_15x20/?apiKey='.$this->api_key.'&tracking='.$trackingNumber;

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
                update_post_meta($post_id, '_shipping_tracking_url', 'https://'.$this->provider['domain'].'/suivre-un-colis/?tracking='. wc_clean($trackingNumber));
                update_post_meta($post_id, '_shipping_tracking_method', $this->provider['slug']);


                $order = wc_get_order($post_id);

                if($order) {
                    $order->update_meta_data('_shipping_tracking_number', wc_clean($trackingNumber));
                    $order->update_meta_data('_shipping_tracking_label', wc_clean($label));
                    $order->update_meta_data('_shipping_label', wc_clean($label));
                    $order->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
                    $order->save();

                    Helpers::make_note($order, $trackingNumber, $this->provider['name']);

                }
            }

            $track = 'https://'.$this->provider['domain'].'.com/suivre-un-colis/?tracking='. wc_clean($trackingNumber);
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


        wp_send_json([
            'message' => __("Unable to create order", 'wc-bordereau-generator'),
            'provider' => $this->provider['name'],
        ], 400);

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

        $communes = [];

//        $url = $this->provider['api_url'].'/getMunicipalities?key='. $this->api_key . '&wilaya='. $wilaya_id;
        $url = $this->provider['api_url'].'/getMunicipalities?key='. $this->api_key;

        $request = new ElogistiaRequest($this->provider);
        $response = $request->get($url);


        if ($response) {

            $communesResult = [];

            $found = array_filter($response['body'], function ($v, $k) use ($wilaya_id) {
                return (int) $v['wilaya'] == $wilaya_id;
            }, ARRAY_FILTER_USE_BOTH);

            foreach ($found as $i => $item) {
                $communesResult[$i]['id'] = $item['name'];
                $communesResult[$i]['name'] = $item['name'];
            }


            return $communesResult;
        }

        return $communes;
    }

    /**
     * Fetch the tracking information
     * @param $tracking_number
     * @param null $post_id * @return void
     *@since 1.1.0
     */
    public function track($tracking_number, $post_id = null)
    {
        $request = new ElogistiaRequest($this->provider);
        $url = $this->provider['api_url'] . "/getTracking?tracking=" . $tracking_number. '&apiKey='.$this->api_key;
        $response = $request->get($url);

        wp_send_json([
            'tracking' => $tracking_number,
            'events' => Helpers::sort_tracking($this->format_tracking($response, false))
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

        $request = new ElogistiaRequest($this->provider);
        $url = $this->provider['api_url'] . "/getOrders/?tracking=".$tracking_number. "&apiKey=". $this->api_key;

        $response = $request->get($url , false);

        $found = array_filter($response['body'], function ($v, $k) use ($tracking_number) {
            return (int) $v['Tracking'] == $tracking_number;
        }, ARRAY_FILTER_USE_BOTH);


        $details = $this->format_tracking_detail($response);


        if (isset($details[0]['last_status'])) {
            BordereauGeneratorAdmin::update_tracking_status($this->formData, $details[0]['last_status'], 'single_tracking');
        }

        wp_send_json([
            'tracking' => $tracking_number,
            'detail' => []
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

        foreach ($response_array['body'] as $key => $item) {
            if ($item['Statut'] === 'Echèc livraison') {
                $result[$key]['failed'] = true;
            }

            if ($item['Statut'] === 'Livré') {
                $result[$key]['delivered'] = true;
                if (isset($result[$key]['failed'])) {
                    $result[$key]['failed'] = false;
                }
            }

            $result[$key]['date'] = $item['Date'];
            $result[$key]['status'] = $item['Statut'];
            $result[$key]['exception'] = $item['Log'];
        }


        $last_status = $result[0]['status'] ?? '';

        if ($last_status) {
            BordereauGeneratorAdmin::update_tracking_status($this->formData, $last_status, 'single_tracking');
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

            if ($result == null || count($result) == 0) {
                $result = $this->get_wilaya_from_provider();
            }

        } else {
            $response_array = $this->get_wilaya_from_provider();
            file_put_contents($path, json_encode($response_array));
            $result = $response_array;
        }



        if (isset($result['body'])) {
            foreach ($result['body'] as $i => $wilaya) {
                $wilayasResult[ $i ]['name'] = $wilaya['wilaya'];
                $wilayasResult[ $i ]['id']   = $wilaya['Id'];
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
        $request = new ElogistiaRequest($this->provider);
        return $request->get($this->provider['api_url'].'/getWilayas?key='. $this->api_key);
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

        $params = $order->get_data();
        $products = $params['line_items'];

        $productString = Helpers::generate_products_string($products);


        $total = get_option($key) ? get_option($key) : 'with-shipping';

        $cart_value = 0;

        if ($total == 'with-shipping') {
            $cart_value = (float) $order->get_total();
        } elseif ($total === 'without-shipping') {
            $cart_value =  ((float) $order->get_total() + $order->get_total_tax() + $order->get_total_fees()) - $order->get_total_shipping();
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
     * @since 2.2.0
     * @return void
     */
    public function get_slip()
    {
        $selectedSize = get_option($this->provider['slug'].'_bordereau_size', '15x20');

        switch ($selectedSize) {
            case '10x15':
                $size = 'printBordereau_10x15';
                break;
            case '10x10':
                $size = 'printBordereau_10x10';
                break;
            default:
                $size = 'printBordereau_15x20';
                break;
        }


        // https://api.elogistia.com/printBordereau_15x20/?apiKey=afaec076c2c1b10a56791b42778f63d4&tracking=L-553BTW
        //
        //https://api.elogistia.com/printBordereau_10x15/?apiKey=afaec076c2c1b10a56791b42778f63d4&tracking=L-553BTW

        $trackingNumber = get_post_meta($this->formData->get_id(), '_shipping_tracking_number', true);

        if(! $trackingNumber) {
            $trackingNumber = $this->formData->get_meta('_shipping_tracking_number');
        }

        header('Location: '. $this->provider['api_url'].'/'.$size.'/?apiKey='.$this->api_key.'&tracking='.$trackingNumber);
        exit;
    }

    /**
     * @param string|null $get_billing_state
     * @return mixed|null
     */
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
        $request = new ElogistiaRequest($this->provider);
        return $request->get($this->provider['api_url'].'/getShippingCost?key='.$this->api_key);
    }

    /**
     * Get all centers/agencies with caching
     *
     * @return array
     * @since 4.0.0
     */
    public function get_all_centers()
    {
        $centers_path = Functions::get_path($this->provider['slug'] . '_centers_' . WC_BORDEREAU_GENERATOR_VERSION . '_' . date('Y') . '_' . (date('z') + 1) . '.json');

        if (!file_exists($centers_path)) {
            // Fetch all agencies from all wilayas (type=2 for stopdesk agencies)
            $url = $this->provider['api_url'] . '/getAgences/?key=' . $this->api_key . '&type=2';
            $request = new ElogistiaRequest($this->provider);
            $response = $request->get($url);

            if ($response && isset($response['body']) && is_array($response['body'])) {
                file_put_contents($centers_path, json_encode($response['body']));
            } else {
                return [];
            }
        }

        if (!file_exists($centers_path)) {
            return [];
        }

        $agencies = json_decode(file_get_contents($centers_path), true);

        if (!$agencies || !is_array($agencies)) {
            return [];
        }

        // Format the results to match the expected structure
        $centersResult = [];
        foreach ($agencies as $i => $item) {
            $centersResult[] = [
                'id'           => $item['Nom du bureau'] ?? '',
                'name'         => $item['Nom du bureau'] ?? '',
                'commune_name' => $item['Commune'] ?? '',
                'wilaya_id'    => (int) ($item['Code wilaya'] ?? 0),
                'address'      => $item['Adresse'] ?? ''
            ];
        }

        return $centersResult;
    }

}
