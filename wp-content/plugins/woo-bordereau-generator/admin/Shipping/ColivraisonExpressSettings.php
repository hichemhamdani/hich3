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


use WC_Shipping_Zone;
use WC_Shipping_Zones;
use WooBordereauGenerator\Functions;
use WooBordereauGenerator\Helpers;
use WP_Error;

class ColivraisonExpressSettings
{
    /**
     * @var array
     */
    private $provider;
    /**
     * @var false|mixed|null
     */
    protected $public_key;
    /**
     * @var false|mixed|null
     */
    private $access_token;

    public function __construct(array $provider)
    {
        $this->public_key = get_option($provider['slug'].'_public_key');
        $this->access_token = get_option($provider['slug'].'_access_token');
        $this->provider = $provider;
    }


    public function get_settings()
    {
        return [];
    }


    /**
     * Track the packages in bulk
     * @param array $codes
     *
     * @return array|false
     */
    public function bulk_track(array $codes)
    {

        $response = $this->sendRequest('tracking/bulk', [
            'tracking_numbers' => $codes
        ]);

        return $this->format_tracking($response);
    }

    /**
     * Status Default
     * @return string[]
     */
    private function status(): array
    {

        return [
            "PLACED" => "Placed",
            "PENDING_RECEPTION" => "Pending reception",
            "AWAITING_TRANSIT" => "Awaiting transit",
            "IN_TRANSIT" => "In transit",
            "OUT_FOR_DELIVERY" => "Out for delivery",
            "READY_FOR_DELIVERY" => "Ready for delivery",
            "RETURNED" => "Returned",
            "IN_RETURN" => "In return",
            "DELIVERED" => "Delivered",
            "RETURN_COLLECTED" => "Return collected",
        ];
    }

    /**
     * format tracking data
     * @param $json
     * @return array
     */
    private function format_tracking($json)
    {
        if ($json) {
            $result = [];
            foreach ($json as $key => $entry) {
                $result[$key] = $entry['status'];
            }
            return $result;
        }

        return [];
    }


    /**
     *
     * Get Shipping classes
     * @return void
     * @since 1.6.5
     */
    public function import_shipping_class($flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled, $agency_import_enabled = null, $is_economic = null)
    {

        if ($pickup_local_enabled) {

            if ($pickup_local_label) {
                if (get_option($this->provider['slug'] . '_pickup_local_label')) {
                    update_option($this->provider['slug'] . '_pickup_local_label', $pickup_local_label);
                } else {
                    add_option($this->provider['slug'] . '_pickup_local_label', $pickup_local_label);
                }
            }
        }


        if ($flat_rate_enabled) {
            if ($flat_rate_label) {
                if (get_option($this->provider['slug'] . '_flat_rate_label')) {
                    update_option($this->provider['slug'] . '_flat_rate_label', $flat_rate_label);
                } else {
                    add_option($this->provider['slug'] . '_flat_rate_label', $flat_rate_label);
                }
            }
        }


        $path = Functions::get_path($this->provider['slug'].'_'.WC_BORDEREAU_GENERATOR_VERSION.'_fees.json');

        if (!file_exists($path)) {

            $prices = $this->sendGetRequest('getpricing');

            file_put_contents($path, json_encode($prices));
        }

        $prices = json_decode(file_get_contents($path), true);


        if (get_option('shipping_zones_grouped') && get_option('shipping_zones_grouped') == 'yes') {
            $this->shipping_zone_grouped($prices, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled);
        } else {
            $this->shipping_zone_ungrouped($prices, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled);
        }

        wp_send_json_success(
            array(
                'message' => 'success',
            )
        );
    }

    private function shipping_zone_grouped($livraison, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled)
    {

    }

    private function shipping_zone_ungrouped($livraison, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled)
    {

        // Set memory limit to -1 (unlimited) and set time limit to 0 (unlimited)
        ini_set('memory_limit', '-1');
		set_time_limit(0);

        $zones = [];

        $shipping_method_local_pickup = null;
        $shipping_method_flat_rate = null;

        $i = 1;
        foreach ($livraison as $key => $wilaya) {

            $priceFlatRate = $wilaya['delivery_price'];
            $wilaya_name = $wilaya['wilaya'];


            // Get all shipping zones
            $zones = WC_Shipping_Zones::get_zones();

            // Iterate through the zones and find the one with the matching name
            $found_zone = null;
            foreach ($zones as $zone) {
                if ($zone['zone_locations'][0]->code == 'DZ:DZ-' . str_pad($wilaya['wilaya_mat'], 2, '0',STR_PAD_LEFT)) {
                    $found_zone = $zone;
                    break;
                }
            }


            if (!$found_zone) {
                $zone = new WC_Shipping_Zone();
                $zone->set_zone_name(ucfirst(Helpers::normalizeCityName($wilaya_name)));
                $zone->set_zone_order($key);
                $zone->add_location('DZ:DZ-' . str_pad($wilaya['wilaya_mat'], 2, '0', STR_PAD_LEFT), 'state');
                $zone->save();
            } else {
                $zone = WC_Shipping_Zones::get_zone($found_zone['id']);
            }

            if ($priceFlatRate && $flat_rate_enabled) {

                $flat_rate_name = 'flat_rate_colivraison';
                if (is_hanout_enabled()) {
                    $flat_rate_name = 'flat_rate';
                }

                $instance_flat_rate = $zone->add_shipping_method($flat_rate_name);

                $shipping_method_flat_rate = WC_Shipping_Zones::get_shipping_method($instance_flat_rate);

                $shipping_method_configuration_flat_rate = [
                    'woocommerce_'.$flat_rate_name.'_title' => $flat_rate_label ?? __('Flat rate', 'woo-bordereau-generator'),
                    'woocommerce_'.$flat_rate_name.'_cost' => $priceFlatRate,
                    'woocommerce_'.$flat_rate_name.'_type' => 'class',
                    'woocommerce_'.$flat_rate_name.'_provider' => $this->provider['slug'],
                    'instance_id' => $instance_flat_rate,
                    'method_id' => $this->provider['slug'] . '_flat_rate',
                ];


                $_REQUEST['instance_id'] = $instance_flat_rate;
                $shipping_method_flat_rate->set_post_data($shipping_method_configuration_flat_rate);
                $shipping_method_flat_rate->update_option('provider', $this->provider['slug']);
                $shipping_method_flat_rate->process_admin_options();
            }
        }

        return [$shipping_method_flat_rate, $shipping_method_local_pickup];
    }


    /**
     * @param $order_ids
     * @return mixed|void
     */
    public function bulk_add_orders($order_ids)
    {
        try {
            $orderItems = [];
            $wilayas = $this->get_wilayas();
            $total = get_option('wc_bordreau_total_calculation', 'with-shipping');
            $orderMapping = []; // Store mapping between order ref and order ID

            foreach ($order_ids as $key => $orderId) {
                $order = wc_get_order($orderId);
                if (!$order) {
                    continue;
                }

                $params = $order->get_data();

                // Prepare customer information
                $lname = $params['billing']['last_name'];
                $fname = $params['billing']['first_name'];

                if (!$lname) {
                    $fnameArray = explode(' ', trim($fname));
                    if (count($fnameArray) > 1) {
                        $lname = $fnameArray[1];
                        $fname = $fnameArray[0];
                    } else {
                        $lname = $params['billing']['first_name'];
                    }
                }

                // Handle phone numbers
                $phone = $params['billing']['phone'];
                $phone = str_replace(' ', '', str_replace('+213', '0', $phone));
                $phones = explode('/', $phone);
                $phone2 = count($phones) > 1 ? $phones[1] : '';
                $phone = $phones[0];

                // Handle address and shipping
                $address = $params['billing']['address_1'];
                if (!$address) {
                    $address = $params['billing']['city'];
                }

                $stop_desk = $order->has_shipping_method('local_pickup');

                // Prepare products information
                $products = $params['line_items'];
                $productString = Helpers::generate_products_string($products);

                // Calculate cart value based on settings
                $cart_value = 0;
                if ($total === 'with-shipping') {
                    $cart_value = (float) $order->get_total();
                } elseif ($total === 'without-shipping') {
                    $cart_value = ((float) $order->get_total()) - $order->get_total_shipping();
                }

                // Get wilaya information
                $wilayaID = $params['billing']['state'];
                $wilayaID = (int) str_replace('DZ-', '', $wilayaID);
                $wilayaName = $this->get_wilayas()['wilayas'][$wilayaID];

                $orderMapping[$orderId] = $orderId; // Store mapping

                $orderItems[] = [
                    'client_name' => $fname . ' ' . $lname,
                    'wilaya_name' => $wilayaName,
                    'order_note' => stripslashes(get_option('wc_bordreau_default-shipping-note')),
                    'phone_st' => $phone,
                    'phone_sc' => $phone2,
                    'order_adress' => $address,
                    'commune_name' => $params['billing']['city'],
                    'product_name' => mb_strimwidth($productString, 0, 252, "..."),
                    'qte' => 1,
                    'product_price' => (string) $cart_value,
                    'stopdesk_status' => $stop_desk,
                    'order_ref' => $order->get_order_number(),
                    'order_brittle' => false,
                    'order_exchange' => false,
                    'order_refund' => false,
                ];
            }

            // Send bulk request
            $requestData = [
                'orders' => $orderItems
            ];

            $response = $this->sendRequest('orders/bulk', $requestData);
            $response_data = [];

            // Process successful orders and update their metadata
            $successful_orders = [];
            if (isset($response['messages']) && is_array($response['messages'])) {
                foreach ($response['messages'] as $index => $orderResponse) {
                    $order_ref = $orderItems[$index]['order_ref']; // Get order ref from original request
                    $original_order_id = $orderMapping[$order_ref]; // Get original order ID

                    if (! isset($orderResponse['tracking'])) {
                        continue;
                    }


                    // Save order data if there's a tracking number
                    if (isset($orderResponse['tracking'])) {
                        $trackingNumber = wc_clean($orderResponse['tracking']);
                        $etq = get_rest_url(null, 'woo-bordereau/v1/slip/print/'.$this->provider['slug'].'/'.$original_order_id);
                        $this->save_order_data($original_order_id, $trackingNumber, $etq);
                    }

                    // Store response for each order
                    $response_data[$original_order_id] = [
                        'success' => $orderResponse['success'] ?? false,
                        'tracking' => $orderResponse['tracking'] ?? null,
                        'label' => isset($orderResponse['tracking']) ? $etq : null,
                        'message' => $orderResponse['message'] ?? null
                    ];
                }
            }

            return [
                'message' => __("Orders have been successfully processed", 'woo-bordereau-generator'),
                'provider' => $this->provider['name'],
                'orders' => $response_data
            ];

        } catch (\Exception $e) {
            set_transient('order_bulk_add_error',$e->getMessage(), 45);
            $logger = wc_get_logger();
            $logger->error('Bulk Upload Error: ' . $e->getMessage(), array('source' => 'colivraison-orders'));

            return [
                'error' => true,
                'message' => $e->getMessage(),
                'provider' => $this->provider['name']
            ];
        }
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

    public function get_status()
    {
        return $this->status();
    }

    /**
     * Send a request to the API
     * @param string $endpoint
     * @param $data
     * @return mixed
     */
    private function sendRequest(string $endpoint, $data)
    {
        $url = $this->provider['api_url'] . $endpoint;

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $this->access_token,
                'coliv-public-key: ' . $this->public_key,
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        if ($response === false) {
            // Handle cURL error
            error_log('cURL error: ' . curl_error($curl));
            curl_close($curl);
            return false;
        }

        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if (empty($response)) {
            return false;
        }

        $result = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Handle JSON decode error
            error_log('JSON decode error: ' . json_last_error_msg());
            return false;
        }

        return $result;
    }

    private function sendGetRequest(string $endpoint)
    {
        $url = $this->provider['api_url'] . $endpoint;

        $request = wp_safe_remote_get(
            $url,
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

    /**
     * @throws \Exception
     */
    private function save_order_data($order_id,string $trackingNumber, string $etq)
    {

        // Update post meta
        update_post_meta($order_id, '_shipping_tracking_number', $trackingNumber);
        update_post_meta($order_id, '_shipping_tracking_label', '');
        update_post_meta($order_id, '_shipping_label', $etq);
        update_post_meta($order_id, '_shipping_tracking_label_method', 'POST');
        update_post_meta($order_id, '_shipping_tracking_url', '');
        update_post_meta($order_id, '_shipping_tracking_method', $this->provider['slug']);

        // Update WooCommerce order
        $order = wc_get_order($order_id);
        if ($order) {
            $order->update_meta_data('_shipping_tracking_number', $trackingNumber);
            $order->update_meta_data('_shipping_tracking_label', '');
            $order->update_meta_data('_shipping_label', $etq);
            $order->update_meta_data('_shipping_tracking_label_method', 'POST');
            $order->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
            $order->save();

            // Add tracking note
            $order->add_order_note(sprintf(
                __('Order has been registered with %s. Tracking number: %s', 'woo-bordereau-generator'),
                $this->provider['name'],
                $trackingNumber
            ));

            // Handle tracking extension if active
            if (in_array('woo-orders-tracking/woo-orders-tracking.php', apply_filters('active_plugins', get_option('active_plugins')))) {
                $tracking_data = [[
                    'tracking_number' => $trackingNumber,
                    'carrier_slug' => $this->provider['slug'],
                    'carrier_url' => '',
                    "carrier_name" => $this->provider['name'],
                    "carrier_type" => "custom-carrier",
                    "time" => time()
                ]];

                foreach ($order->get_items() as $item) {
                    wc_update_order_item_meta($item->get_id(), '_vi_wot_order_item_tracking_data', json_encode($tracking_data));
                }
            }
        }
    }
}
