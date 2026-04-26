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

class YalidineShipping extends BordereauProvider implements BordereauProviderInterface
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
        $wilayas = $this->get_wilayas();

        if (isset($this->data['wilaya'])) {
            $wilayaId = (int) str_replace("DZ-", "", $this->data['wilaya']);
            $wilaya = array_search($wilayaId, array_column($wilayas['data'], 'id'));
        } else {
            $wilayaId = (int) str_replace("DZ-", "", $params['billing']['state']);

            $wilaya = array_search($wilayaId, array_column($wilayas['data'], 'id'));
        }

        if ($wilaya === null) {
            wp_send_json([
                'error' => true,
                'message'=> __("Wilaya has not been selected", 'wc-bordereau-generator')
            ], 422);
            return;
        }

        $orderId = $this->formData->get_order_number();

        if (isset($this->data['order_number'])) {
            $orderId = $this->data['order_number'];
        }


        // get the wilaya name and id
        $wilaya = $wilayas['data'][$wilaya];

        if (isset($this->data['products'])) {
            $productString = $this->data['products'];
        } else {
            $products = $params['line_items'];
            $productString = Helpers::generate_products_string($products);
        }


        $free_shipping = 0;
        $stop_desk = 0;


        if ($this->data['free'] == "true") {
            $free_shipping = 1;
        }
        if ($this->formData->has_shipping_method('free_shipping')) {
            $free_shipping = 1;
        }

        if ($this->formData->has_shipping_method('local_pickup') && $this->data['shipping_type'] === 'stopdesk' ||  $this->formData->has_shipping_method('local_pickup_yalidine')) {
            $stop_desk = 1;
        }

        if ($this->data['shipping_type'] === 'stopdesk') {
            $stop_desk = 1;
        } else {
	        $stop_desk = 0;
        }

        $communeName = stripslashes($this->data['commune'] ?? $params['billing']['city']);

        $phone = $this->data['phone'] ?? $params['billing']['phone'];

        if ($this->data['phone_2']) {
            $phone = $phone.','.$this->data['phone_2'];
        }

        if ($this->data['remarque']) {
            $note = $this->data['remarque'];
        } else {
            $note = stripslashes(get_option('wc_bordreau_default-shipping-note'));
        }

		if ($this->data['from_wilaya']) {
			$from_wilaya = (int) str_replace('DZ-', '', $this->data['from_wilaya']);
		}


        $data = [
            [
                "order_id" => $orderId,
                "firstname" => $this->data['lname'] ?? $params['billing']['last_name'],
                "familyname" => $this->data['fname'] ?? $params['billing']['first_name'],
                "contact_phone" => $phone,
                "address" => $this->data['address'] ?? $params['billing']['address_1'],
                "to_commune_name" => $communeName,
                "to_wilaya_name" => $wilaya['name'],
                "product_list" => $productString. ' ' . $note,
                "price" => $this->data['price'] ?? $params['total'] - $params['shipping_total'],
                "freeshipping" => $free_shipping,
                "do_insurance" => $this->data['do_insurance'],
                "declared_value" => (int) $this->data['declared_value'],
                "is_stopdesk" => $stop_desk,
                "has_exchange" => isset($this->data['operation_type']) && $this->data['operation_type'] == 2,
            ]
        ];

		if (isset($this->data['hauteur']) && $this->data['hauteur']) {
			$data[0]['height'] = $this->data['hauteur'];
		}

		if (isset($this->data['largeur']) && $this->data['largeur']) {
			$data[0]['width'] = $this->data['largeur'];
		}

		if (isset($this->data['longeur']) && $this->data['longeur']) {
			$data[0]['length'] = $this->data['longeur'];
		}

		if (isset($this->data['poids']) && $this->data['poids']) {
			$data[0]['weight'] = $this->data['poids'];
		}

		if (isset($from_wilaya)) {
			$wilaya = array_search($from_wilaya, array_column($wilayas['data'], 'id'));
			$data[0]['from_wilaya_name'] = $wilayas['data'][$wilaya]['name'] ?? '';
		}

        if ( isset($this->data['product_exchanged'])) {
            if (isset($this->data['operation_type']) && $this->data['operation_type'] == 2) {
                $data[0]['product_to_collect'] = $this->data['product_exchanged'];
            }
        }

        if (! isset($this->data['is_update']) && in_array($this->provider['slug'], ['guepex', 'wecanservices']) && isset($this->data['economic']) && $this->data['economic'] === "true") {
            $data[0]["economic"] = $this->data['economic'];
        }

        if ($stop_desk && isset($this->data['center'])) {
            $data[0][ "stopdesk_id"]  = $this->data['center'];
        }

        // Fallback: Auto-select center if not provided
        if ($stop_desk && !isset($this->data['center'])) {
            $centers = $this->get_all_centers();
            if (is_array($centers) && count($centers) > 0) {
                // Step 1: Try to find a center in the selected commune
                $result = array_filter($centers, function($agency) use ($communeName) {
                    return $agency['commune_name'] == $communeName;
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
                    $data[0]['stopdesk_id'] = $found['id'];
                    $data[0]['to_commune_name'] = $found['commune_name'];
                }
            }
        }

        // order_id, firstname, familyname, contact_phone, address, to_commune_name, to_wilaya_name, product_list, price, freeshipping, is_stopdesk, has_exchange

        $request = new YalidineRequest($this->provider);

        try {
            $trackingNumber = get_post_meta($orderId, '_shipping_tracking_number', true);
            if(! $trackingNumber) {
                $trackingNumber = $this->formData->get_meta('_shipping_tracking_number');
            }

	        if (isset($this->data['is_update']) || $trackingNumber) {

		        $url = $this->provider['api_url'] . "/parcels/". $this->data['tracking_number'];
		        $data = $data[0];

		        $response = $request->post($url, $data, YalidineRequest::PATCH);
	        } else {
		        $url = $this->provider['api_url'] . "/parcels";
		        $response = $request->post($url, $data);
	        }

// Handle the specific error format where it's an array with order_id as key
	        if (is_array($response)) {
		        foreach ($response as $order_id => $order_data) {
			        if (isset($order_data['success']) && $order_data['success'] === false) {
				        $errorMessage = $order_data['message'] ?? 'Unknown error occurred';

				        // Log the error (optional)
				        new WP_Error('yalidine_error', $errorMessage);

				        // Send JSON response with error
				        wp_send_json([
					        'error' => true,
					        'message' => $errorMessage,
					        'order_id' => $order_data['order_id'] ?? $order_id
				        ], 400);
				        exit;
			        }
		        }
	        }

// Check for the old error format as well
	        if (isset($response['error'])) {
		        $errorMessage = $response['error']['message'];

		        new WP_Error('yalidine_error', $errorMessage);

		        wp_send_json([
			        'error' => true,
			        'message' => $errorMessage
		        ], 400);
		        exit;
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
     * Get Cities from specific State
     * @param int $wilaya_id
     * @param bool $hasStopDesk
     *
     * @return array
     */
    public function get_communes(int $wilaya_id, bool $hasStopDesk)
    {

        $communes = [];

        $url = $this->provider['api_url'].'/communes?wilaya_id='. $wilaya_id;

        if ($hasStopDesk) {
            $url .= '&has_stop_desk=true';
        }

        $request = new YalidineRequest($this->provider);

        $response = $request->get($url);

        if(! $response) {
            $response = $request->get($url, false);
        }

        if ($response) {
            foreach ($response['data'] as $i => $item) {
                if ($item['is_deliverable'] == 1) {
                    $communes[$i]['id'] = $item['id'];
                    $communes[$i]['name'] = $item['name'];
                }
            }
        }

        return $communes;
    }

    /**
     * Save the response to the postmeta
     * @param $post_id
     * @param array $response
     * @param bool $update
     * @throws Exception
     * @since 1.1.0
     */
    public function save($post_id, array $response, bool $update = false)
    {
        $order = wc_get_order($post_id);
        $order_number = $order->get_order_number();

        if(isset($response[$order_number])) {
            $trackingNumber = $response[$order_number]['tracking'];
            $label = $response[$order_number]['label'];
        } else {
            $trackingNumber = $response['tracking'];
            $label = $response['label'];
        }

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
            update_post_meta($post_id, '_shipping_tracking_number', wc_clean($trackingNumber)); // backwork compatibilite

            if($order) {
                $order->update_meta_data('_shipping_tracking_number', wc_clean($trackingNumber));
                $order->update_meta_data('_shipping_tracking_label', wc_clean($label));
                $order->update_meta_data('_shipping_label', wc_clean($label));
                $order->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
                $order->save();

                Helpers::make_note($order, $trackingNumber, $this->provider['name']);

            }

            update_post_meta($post_id, '_shipping_tracking_label', wc_clean($label));
            update_post_meta($post_id, '_shipping_label', wc_clean($label));
            update_post_meta($post_id, '_shipping_tracking_url', 'https://'.$this->provider['url'].'/suivre-un-colis/?tracking='. wc_clean($trackingNumber));
            update_post_meta($post_id, '_shipping_tracking_method', $this->provider['slug']);
        }

        $track = $this->provider['url'].'.com/suivre-un-colis/?tracking='. wc_clean($trackingNumber);
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

    /**
     * Fetch the tracking information
     * @param $tracking_number
     * @param null $post_id * @return void
     *@since 1.1.0
     */
    public function track($tracking_number, $post_id = null)
    {
        $request = new YalidineRequest($this->provider);
        wp_send_json([
            'tracking' => $tracking_number,
            'events' => Helpers::sort_tracking($this->format_tracking($request->get($this->provider['api_url'] . "/histories?tracking=" . $tracking_number, false)))
        ]);
    }

    /**
     * Get the tracking information for the order
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
     * Get the tracking information for the order
     * @param $tracking_number
     * @param $post_id
     * @return void
     * @since 1.2.0
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

        foreach ($response_array['data'] as $key => $item) {

            if ($item['status'] === 'Echèc livraison') {
                $result[$key]['failed'] = true;
            }

            if (strpos($item['status'], 'Livré') !== false) {
                $result[$key]['delivered'] = true;
                if (isset($result[$key]['failed'])) {
                    $result[$key]['failed'] = false;
                }
            }

            $result[$key]['date'] = $item['date_status'];
            $result[$key]['commune'] = $item['commune_name'];
            $result[$key]['wilaya'] = $item['wilaya_name'];
            $result[$key]['status'] = $item['status'];
            $result[$key]['exception'] = $item['reason'];
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
            // Only cache if response is valid and not null/empty
            if ($response !== null && !empty($response) && !isset($response['error'])) {
                file_put_contents($path, json_encode($response));
            }
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
            // Only cache if response is valid and not null/empty
            if ($response_array !== null && !empty($response_array) && !isset($response_array['error'])) {
                file_put_contents($path, json_encode($response_array));
            }
            $result = $response_array;
        }



        if (isset($result['data'])) {
            foreach ($result['data'] as $i => $wilaya) {
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
        $request = new YalidineRequest($this->provider);
        return $request->get($this->provider['api_url'].'/wilayas?page_size=1000');
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
        $overweight = $fields['overweight']['choices'][0]['name'] ?? null;
        $overweightFrontend = $fields['overweight_checkout']['choices'][0]['name'] ?? null;
        $costSuppliment = $fields['added_fee']['choices'][0]['name'] ?? null;

        $params = $order->get_data();
        $products = $params['line_items'];

        $weightData = Helpers::get_billable_weight($order);
        $total_weight = $weightData['actual_weight'];
        $dimensions = $weightData['dimensions'];

        $productString = Helpers::generate_products_string($products);

        $total = get_option($key) ? get_option($key) : 'with-shipping';
        $recalculateCase = get_option($recalculate) ? get_option($recalculate) : 'recalculate-without-shipping';
        $overweightCase = get_option($overweight) ? get_option($overweight) : 'recalculate-without-overweight';
        $overweightFrontendCase = get_option($overweightFrontend) ? get_option($overweightFrontend) : 'recalculate-without-overweight';
        $costSupplimentCase = get_option($costSuppliment) ? get_option($costSuppliment) : 'without_added_fee_' . $this->provider['slug'];

        $cart_value = 0;

        if ($total == 'with-shipping') {
            $cart_value = (float) $order->get_total();
        } elseif ($total === 'without-shipping') {
            $fees = $order->get_fees();
            $foundFee = null;
            $foundSurpoidsFee = null;
            // Loop through the fees
            foreach ($fees as $fee) {
                // Check if the current fee name matches the one we're looking for
                if ($fee->get_name() == __('Supplément commune', 'woo-bordereau-generator')) {
                    // Fee found, you can return true or the fee object itself
                    $foundFee = $fee;
                }

                if ($fee->get_name() == __('Surpoids (+5KG)', 'woo-bordereau-generator')) {
                    // Fee found, you can return true or the fee object itself
                    $foundSurpoidsFee = $fee;
                }
            }


            if ($foundFee || $foundSurpoidsFee) {
                $feeAmount = 0;
                if ($foundFee) {
                    $feeAmount = $feeAmount + (int) $foundFee->get_amount();
                }

                if ($foundSurpoidsFee) {
                    $feeAmount = $feeAmount + (int) $foundSurpoidsFee->get_amount();
                }

                $cart_value = ((float) $order->get_total())
                    - ($order->get_total_shipping() + $feeAmount);

            } else {
                $cart_value = (float)$order->get_subtotal() + $order->get_total_tax() + $order->get_total_fees() - $order->get_discount_total();

            }
        }

        // Sync the price of the shipping and add the diff to the cart total

        if($recalculateCase == 'recalculate-with-shipping') {

            // check if the price of the
            $shippingCost = (int) $order->get_shipping_total();
            $shippingCostFromProvider = $this->get_shipping_cost($data['state']);
            $shippingExtraCostFromProvider = $this->get_extra_shipping_cost($data['city'], $data['state']) ?? false;

            if($option == 'stopdesk') {
                $shippingCostFromProviderPrice = $shippingCostFromProvider['express_desk'];
            } else {
                $shippingCostFromProviderPrice = $shippingCostFromProvider['express_home'];
            }

            if ($shippingExtraCostFromProvider && isset($shippingExtraCostFromProvider['extra_fee_home'])) {
                $cart_value = $cart_value + ($shippingCost - ($shippingCostFromProviderPrice + $shippingExtraCostFromProvider['extra_fee_home']));
            } else {
                $cart_value = $cart_value + ($shippingCost - $shippingCostFromProviderPrice);
            }
        }


        $shippingOverweightPrice = 0;
        $shippingOverweight = 0;
        $zonePrice = 0;


        if ($overweightCase == 'recalculate-with-overweight') {

            $shippingOverweight = $weightData['billable_weight'];

            // get Wilaya

            $wilayas = $this->get_wilayas();

            $pattern = "/DZ-[0-9]{2}/";

            if (!empty($data['state'])) {
                $wilaya_selected = $data['state'];
            } else {
                $wilaya_selected = $params['billing']['state'];
            }

            if (preg_match($pattern, $wilaya_selected)) {
                $wilayaId = (int) str_replace("DZ-", "", $wilaya_selected);

            } else {
                $wilayaId = $params['billing']['state'];
            }

            if (is_int($wilayaId)) {
                $wilaya = array_search($wilayaId, array_column($wilayas['data'], 'id'));
            } else {
                $wilaya = array_search($wilayaId, array_column($wilayas['data'], 'name'));
            }

            $zonePrice = 50;

            if ($wilaya !== false) {
                $wilayaData = $wilayas['data'][$wilaya];

                if (in_array($wilayaData['zone'], [4,5])) {
                    $zonePrice = 100;
                }
            }

            $shippingOverweightPrice = max(0, ceil($shippingOverweight - 5)) * $zonePrice;
            if ($overweightFrontendCase === 'recalculate-without-overweight') {
                $cart_value = $cart_value - $shippingOverweightPrice;
            }
        }

        if ($total == 'with-shipping') {
            $cart_value = $order->get_total();
        }

        $is_economic = false;

        $shipping_method = Helpers::get_order_shipping_method_class($order);

        if ($shipping_method) {
            if (method_exists($shipping_method, 'get_is_economic')) {
                $is_economic = $shipping_method->get_is_economic();
            }
        }

        // check if the name is an agence check in the centers list
        $AgencyName = $order->get_shipping_method();
        $centers = $this->get_all_centers();
        $center = $order->get_meta('_billing_center_id', true);

        $found = array_filter($centers, function ($v, $k) use ($AgencyName) {
            return strtolower($v['name']) == strtolower(urldecode($AgencyName));
        }, ARRAY_FILTER_USE_BOTH);

        if ($found) {
            $center = reset($found);
        }

        if (is_array($center)) {
            $center = $center['id'];
        }

        $data = [];
        if ($key) {
            $data['total_calculate'] = get_option($key) ? get_option($key) : 'with-shipping';
            $data['type'] = $provider['type'];
            $data['total'] = $cart_value;
            $data['is_free_shipping'] = $total == 'with-shipping' || $order->has_shipping_method('free_shipping');
            $data['total_without_shipping'] = (float) $order->get_subtotal() + $order->get_total_tax() + $order->get_total_fees();
            $data['extra_commune'] = $shippingExtraCostFromProvider[0]['commune'] ?? 0;
            $data['shipping_fee'] = $shippingCostFromProviderPrice ?? 0;
            $data['order_number'] = $order->get_order_number();
            $data['products_string'] = $productString;
            $data['shipping_overweight_price'] = $shippingOverweightPrice;
            $data['center_name'] = $center ?? null;
            $data['total_weight'] = $total_weight;
            $data['dimensions'] = $dimensions;
            $data['zone_fee'] = $zonePrice;
            $data['is_economic'] = $is_economic;

        }
        return $data;
    }

    /**
     * @param $tracking_number
     * @param $post_id
     * @return string
     * @since 1.3.0
     */
    public function delete($tracking_number, $post_id)
    {
        $request = new YalidineRequest($this->provider);
        return $request->post($this->provider['api_url'] . "/parcels/". $tracking_number, [], YalidineRequest::DELETE);
    }

    /**
     *
     * @param $commune
     * @return array
     */
    public function get_centers($commune): array
    {
        $commune = str_replace('&quot;', "'", $commune);
        $path = Functions::get_path($this->provider['slug'].'_centers_'.WC_BORDEREAU_GENERATOR_VERSION.'_'.date('Y').'_'. (date('z') + 1).'.json');

        if (! file_exists($path)) {

            $hasModePages = true;
            $currentPage = 1;
            while ($hasModePages === true) {

                $request = new YalidineRequest($this->provider);

                $json = $request->get($this->provider['api_url'].'/centers?page=' . $currentPage."&page_size=1000" );

                if (file_exists($path)) {
                    $communes = json_decode(file_get_contents($path), true);
                    $result = array_merge($communes, $json['data']);
                    // Only cache if result is valid and not null/empty
                    if ($result !== null && !empty($result)) {
                        file_put_contents($path, json_encode($result));
                    }
                } else {
                    // Only cache if json data is valid and not null/empty
                    if ($json !== null && !empty($json) && isset($json['data']) && !empty($json['data'])) {
                        file_put_contents($path, json_encode($json['data']));
                    }
                }

                if (! $json['has_more']) {
                    $hasModePages = false;
                }

                $currentPage++;
            }
        }

        $communes = json_decode(file_get_contents($path), true);
        $communesResult = [];
        $found = array_filter($communes, function ($v, $k) use ($commune) {
            return strtolower($v['commune_name']) == strtolower(urldecode($commune));
        }, ARRAY_FILTER_USE_BOTH);

        foreach ($found as $i => $item) {
            $communesResult[$i]['id'] = $item['center_id'];
            $communesResult[$i]['name'] = $item['name'];
        }

        return $communesResult;
    }

    /**
     *
     * @param $commune
     * @return array
     */
    public function get_all_centers(): array
    {
        $path = Functions::get_path($this->provider['slug'].'_centers_'.WC_BORDEREAU_GENERATOR_VERSION.'_'.date('Y').'_'. (date('z') + 1).'.json');

        if (! file_exists($path)) {

            $hasModePages = true;
            $currentPage = 1;
            while ($hasModePages === true) {

                $request = new YalidineRequest($this->provider);

                $json = $request->get($this->provider['api_url'].'/centers?page=' . $currentPage."&page_size=1000" );

                if (file_exists($path)) {
                    $communes = json_decode(file_get_contents($path), true);
                    $result = array_merge($communes, $json['data']);
                    // Only cache if result is valid and not null/empty
                    if ($result !== null && !empty($result)) {
                        file_put_contents($path, json_encode($result));
                    }
                } else {
                    // Only cache if json data is valid and not null/empty
                    if ($json !== null && !empty($json) && isset($json['data']) && !empty($json['data'])) {
                        file_put_contents($path, json_encode($json['data']));
                    }
                }

                if (! $json['has_more']) {
                    $hasModePages = false;
                }

                $currentPage++;
            }
        }

        $communes = json_decode(file_get_contents($path), true);
        $communesResult = [];

        foreach ($communes as $i => $item) {
            $communesResult[$i]['id'] = $item['center_id'];
            $communesResult[$i]['name'] = $item['name'];
            $communesResult[$i]['commune_name'] = $item['commune_name'];
            $communesResult[$i]['wilaya_name'] = $item['wilaya_name'];
            $communesResult[$i]['wilaya_id'] = $item['wilaya_id'];
        }

        return $communesResult;
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

    public function get_shipping_cost(?string $get_billing_state)
    {
        $fees = $this->get_shipping_cost_from_provider();


        $wilaya_id_to_search = str_replace('DZ-', '', $get_billing_state);
        $wilaya_id_to_search = (int)$wilaya_id_to_search;
        $matched_array = null;

        foreach ($fees as $item) {
            if ($item['wilaya_id'] == $wilaya_id_to_search) {
                $matched_array = $item;
                break;
            }
        }

        return $matched_array;
    }

    private function get_shipping_cost_from_provider()
    {
        $request = new YalidineShippingSettings($this->provider);
        return $request->get_fees();
    }

    public function get_extra_shipping_cost(string $city, string $state)
    {
        $request = new YalidineShippingSettings($this->provider);
        $response = $request->get_extra_fee($state);

        $foundCity =  array_filter($response, function ($v, $k) use ($city) {
            return $v['commune_name'] == $city;
        }, ARRAY_FILTER_USE_BOTH);

        return reset($foundCity);
    }

    /**
     * @param string $state
     * @return bool|mixed|string
     */
    public function get_extra_fee_from_provider(string $state)
    {
        $wilaya = str_replace('DZ-', '', $state);

        $url = 'https://amineware.me/api/cities-with-fee?token=1Q0cWtxFHLwMAffh8KeRzNC9QXiZju77&wilaya=' . $wilaya;

        if ($this->provider['slug'] == 'guepex') {
            $url = 'https://amineware.me/api/guepex-cities-with-fee?token=1Q0cWtxFHLwMAffh8KeRzNC9QXiZju77&wilaya=' . $wilaya;
        }

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
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

    public function cancel_order($tracking_number, WC_Order $order)
    {
        try {
            $deleted = $this->delete($tracking_number, $order->get_id());
            $deleted_array = json_decode($deleted, true);

            if(isset($deleted_array['success']) && $deleted_array['success']) {
                delete_post_meta($order->get_id(), '_shipping_tracking_label');
                delete_post_meta($order->get_id(), '_shipping_label');
                delete_post_meta($order->get_id(), '_shipping_tracking_url');
                delete_post_meta($order->get_id(), '_shipping_tracking_method');

                $order->delete_meta_data('_shipping_tracking_number');
                $order->delete_meta_data('_shipping_tracking_label');
                $order->delete_meta_data('_shipping_label');
                $order->delete_meta_data('_shipping_tracking_method');
            }

        } catch (\ErrorException $exception) {

        }
    }
}
