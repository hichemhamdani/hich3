<?php

/**
 * Near Delivery shipping provider integration for WooCommerce Bordereau Generator.
 *
 * This class handles the integration with Near Delivery shipping services,
 * providing functionality for generating shipping labels, tracking packages,
 * managing shipping data, and interacting with the Near Delivery API.
 *
 * @package    WC_Bordereau_Generator
 * @subpackage WC_Bordereau_Generator/admin/shipping
 * @author     Boukraa Mohamed <bm2ilabs@gmail.com>
 */
namespace WooBordereauGenerator\Admin\Shipping;


use Exception;
use stdClass;
use WC_Order;
use WC_Product;
use WooBordereauGenerator\Functions;
use WooBordereauGenerator\Admin\BordereauGeneratorAdmin;
use WooBordereauGenerator\Helpers;
use WP_Error;

/**
 * Near Delivery shipping provider implementation.
 *
 * Extends the BordereauProvider class and implements BordereauProviderInterface
 * to provide Near Delivery-specific shipping functionality for the WooCommerce
 * Bordereau Generator plugin.
 */
class NearDelivery extends \WooBordereauGenerator\Admin\Shipping\BordereauProvider implements \WooBordereauGenerator\Admin\Shipping\BordereauProviderInterface
{

	/**
	 * The Near Delivery API Key.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * The Near Delivery API Secret.
	 *
	 * @var string
	 */
	private string $api_secret;

	/**
	 * Order data for shipment generation.
	 *
	 * @var WC_Order
	 */
	protected $formData;

	/**
	 * Provider configuration data.
	 *
	 * @var array
	 */
	protected $provider;

	/**
	 * Additional shipment data.
	 *
	 * @var array
	 */
	private $data;

	/**
	 * Constructor.
	 *
	 * Initializes a new Near Delivery provider instance with order data,
	 * provider configuration, and additional shipping data.
	 *
	 * @param WC_Order $formData  The WooCommerce order object.
	 * @param array    $provider  The provider configuration array.
	 * @param array    $data      Additional shipment data (optional).
	 */
	public function __construct(WC_Order $formData, array $provider, array $data = [])
	{

		$this->formData = $formData;
		$this->api_key  = get_option( $provider['slug'] . '_api_key' );
		$this->api_secret = get_option( $provider['slug'] . '_api_secret' );
		$this->provider = $provider;
		$this->data = $data;
	}

	/**
	 * Generate the tracking code.
	 *
	 * Creates a shipping label and tracking number for the order by
	 * formatting order data and sending it to the Near Delivery API.
	 *
	 * @return mixed|WP_Error Returns the API response or WP_Error on failure.
	 * @throws Exception If an error occurs during the API request.
	 * @since 1.0.0
	 */
	public function generate()
	{
		$params = $this->formData->get_data();

		$orderId = $this->formData->get_order_number();

		// Get wilaya code (format: "16" for Alger, "15" for Tizi Ouzou, etc.)
		if (isset($this->data['wilaya'])) {
			$wilayaCode = str_replace("DZ-", "", $this->data['wilaya']);
		} else {
			$wilayaCode = str_replace("DZ-", "", $params['billing']['state']);
		}

		// Pad wilaya code with leading zero if needed (e.g., "5" -> "05")
		$wilayaCode = str_pad($wilayaCode, 2, '0', STR_PAD_LEFT);

		if (empty($wilayaCode)) {
			wp_send_json([
				'error' => true,
				'message' => __("Wilaya has not been selected", 'woo-bordereau-generator')
			], 422);
		}

		// Get commune code from the wilayas API response
		$communeCode = null;

		if (isset($this->data['commune'])) {
			// If commune is provided, try to find its code
			$communeCode = $this->get_commune_code($wilayaCode, $this->data['commune']);
		}

		if (isset($params['billing']['city']) && empty($communeCode)) {
			$communeCode = $this->get_commune_code($wilayaCode, $params['billing']['city']);
		}

		// Build product string
		if (isset($this->data['products'])) {
			$productString = $this->data['products'];
		} else {
			$products = $params['line_items'];
			$productString = Helpers::generate_products_string($products);
		}

		$phone = $this->data['phone'] ?? $params['billing']['phone'];
		$phone = Helpers::clean_phone_number($phone);

		$phone_2 = isset($this->data['phone_2']) && !empty($this->data['phone_2'])
			? Helpers::clean_phone_number($this->data['phone_2'])
			: null;

		// Determine payment preference: 1 = COD (Standard Payment), 2 = Free shipping
		$free_shipping = false;
		if (isset($this->data['free']) && $this->data['free'] == "true") {
			$free_shipping = true;
		}
		// Determine payment type: 0 = COD, 1 = Standard (pre-paid)
		$payment_method = $this->formData->get_payment_method();
		$is_cod = ($payment_method === 'cod' || $payment_method === 'cheque' || $payment_method === 'bacs');
		$payment_preference = $is_cod ? 0 : 1;

		// Determine pickup location type: 1 = Center Pickup, 2 = Home Delivery
		$stop_desk = false;
		if ($this->formData->has_shipping_method('local_pickup') ||
			$this->formData->has_shipping_method('local_pickup_near_delivery') ||
			(isset($this->data['shipping_type']) && $this->data['shipping_type'] === 'stopdesk')) {
			$stop_desk = true;
		}

		$firstName = $this->data['fname'] ?? $params['billing']['first_name'];
		$lastName = $this->data['lname'] ?? $params['billing']['last_name'];

		if (isset($this->data['order_number'])) {
			$orderId = $this->data['order_number'];
		}

		// Get sender center ID
		$sender_center_id = get_option($this->provider['slug'] . '_sender_center_id');

		if (empty($sender_center_id)) {
			wp_send_json([
				'error' => true,
				'message' => __("Sender Center ID is not configured. Please set it in the Near Delivery settings.", 'woo-bordereau-generator')
			], 400);
		}

		// Calculate order total based on settings (with or without shipping)
		$total_setting_key = $this->provider['slug'] . '_total';
		$total_setting = get_option($total_setting_key, 'with-shipping');

		if (isset($this->data['price']) && !empty($this->data['price'])) {
			// Use manual price if provided
			$order_total = (float) $this->data['price'];
		} elseif ($total_setting === 'without-shipping') {
			// Calculate total without shipping
			$order_total = (float) $this->formData->get_total() - (float) $this->formData->get_total_shipping();
		} else {
			// Default: with shipping (full order total)
			$order_total = (float) $this->formData->get_total();
		}

		$total_weight = Helpers::get_product_weight($this->formData);


		// Build Near Delivery API payload
		$parcel = [
			'declared_contents' => mb_substr($productString, 0, 180),
			'recipient_name' => $firstName . ' ' . $lastName,
			'recipient_phone' => $phone,
			'recipient_address' => $this->data['address'] ?? $params['billing']['address_1'] ?? '',
			'recipient_wilaya' => $wilayaCode,
			'payment_preference' => $payment_preference, // 0 = COD, 1 = Standard (pre-paid)
			'pickup_location_type' => $stop_desk ? 1 : 2, // 1 = Center Pickup, 2 = Home Delivery
			'parcel_fees' => $order_total,
			'reference' => (string) $orderId,
			'weight' => isset($this->data['poids']) && $this->data['poids'] ? (float) $this->data['poids'] : 1,
			'length' => isset($this->data['longeur']) && $this->data['longeur'] ? (float) $this->data['longeur'] : 1,
			'width' => isset($this->data['largeur']) && $this->data['largeur'] ? (float) $this->data['largeur'] : 1,
			'height' => isset($this->data['hauteur']) && $this->data['hauteur'] ? (float) $this->data['hauteur'] : 1,
		];

		// Add commune code if available
		if (!empty($communeCode)) {
			$parcel['recipient_commune'] = $communeCode;
		}

		// Add secondary phone if provided
		if (!empty($phone_2)) {
			$parcel['recipient_phone_2'] = $phone_2;
		}

		// Add email if available
		$email = $params['billing']['email'] ?? null;
		if (!empty($email)) {
			$parcel['recipient_email'] = $email;
		}

		// Add buralist_id (center) if stop desk delivery
		if ($stop_desk && isset($this->data['center']) && !empty($this->data['center'])) {
			$parcel['buralist_id'] = (int) $this->data['center'];

			// Save center to order meta
			$order = wc_get_order($orderId);
			if ($order) {
				$order->update_meta_data('_billing_center_id', (int) $this->data['center']);
				$order->save();
			}
		}

		if (isset($this->data['is_update'])) {
			wp_send_json([
				'error' => true,
				'message' => __("Cannot Update the Order please delete it and redo it again", 'woo-bordereau-generator')
			], 401);
			exit();
		}

		$url = $this->provider['api_url'] . "/sender/parcels/bulk-create";


		// Build the request body with common and parcels structure
		$requestBody = [
			'common' => [
				'sender_center_id' => (int) $sender_center_id
			],
			'parcels' => [$parcel]
		];

		$request = wp_safe_remote_post(
			$url,
			[
				'headers' => [
					'Content-Type' => 'application/json',
					'Accept' => 'application/json',
					'ApiKey' => $this->api_key,
					'ApiSecret' => $this->api_secret
				],
				'body' => wp_json_encode($requestBody),
				'method' => 'POST',
				'data_format' => 'body',
				'timeout' => 30
			]
		);

		if (is_wp_error($request)) {
			wp_send_json([
				'error' => true,
				'message' => $request->get_error_message()
			], 400);
		}

		$body = wp_remote_retrieve_body($request);

		$response = json_decode($body, true);
		$response_code = wp_remote_retrieve_response_code($request);

		if ($response_code < 200 || $response_code >= 300) {
			$error_message = $response['message'] ?? $response['detail'] ?? __("An error has occurred. Please see log file", 'woo-bordereau-generator');
			wp_send_json([
				'error' => true,
				'message' => $error_message
			], 400);
		}

		// Near Delivery API returns: { "status": true, "message": "...", "data": [...] }
		if (!isset($response['status']) || $response['status'] !== true) {
			$error_message = $response['message'] ?? __("Failed to create parcel", 'woo-bordereau-generator');
			wp_send_json([
				'error' => true,
				'message' => $error_message
			], 400);
		}

		// Get the created parcel from the data array
		if (!isset($response['data']) || empty($response['data'])) {
			wp_send_json([
				'error' => true,
				'message' => __("No parcel data in response", 'woo-bordereau-generator')
			], 400);
		}

		$createdParcel = $response['data'][0];
		$tracking_number = $createdParcel['tracking_number'] ?? null;
		$parcel_id = $createdParcel['parcel_id'] ?? null;

		if (!$tracking_number) {
			wp_send_json([
				'error' => true,
				'message' => __("Failed to retrieve tracking number", 'woo-bordereau-generator')
			], 401);
		}

		return [
			'id' => $parcel_id,
			'tracking' => $tracking_number,
			'parcel' => $createdParcel
		];
	}

	/**
	 * Get commune code from wilaya data
	 *
	 * @param string $wilayaCode Wilaya code
	 * @param string $communeName Commune name
	 * @return string|null Commune code or null if not found
	 */
	private function get_commune_code($wilayaCode, $communeName)
	{
		$wilayas_data = $this->get_wilayas_with_communes();

		foreach ($wilayas_data as $wilaya) {
			if ($wilaya['wilaya_code'] == $wilayaCode && isset($wilaya['communes'])) {
				foreach ($wilaya['communes'] as $commune) {
					if (strcasecmp($commune['name'], $communeName) === 0) {
						return $commune['code'];
					}
				}
			}
		}

		return null;
	}

	/**
	 * Get wilayas with their communes from the API
	 *
	 * @return array Wilayas with communes
	 */
	private function get_wilayas_with_communes()
	{
		$path = Functions::get_path($this->provider['slug'] . '_wilayas_communes.json');

		if (file_exists($path)) {
			$data = json_decode(file_get_contents($path), true);
			if (!empty($data)) {
				return $data;
			}
		}

		// Fetch from API
		$url = $this->provider['api_url'] . '/sender/wilayas';

		$request = wp_safe_remote_get($url, [
			'headers' => [
				'Content-Type' => 'application/json',
				'ApiKey' => $this->api_key,
				'ApiSecret' => $this->api_secret
			],
			'timeout' => 30
		]);

		if (!is_wp_error($request)) {
			$body = wp_remote_retrieve_body($request);
			$response = json_decode($body, true);

			if (isset($response['status']) && $response['status'] === true && isset($response['data'])) {
				file_put_contents($path, json_encode($response['data']));
				return $response['data'];
			}
		}

		return [];
	}


	/**
	 * Save the response to the postmeta.
	 *
	 * Saves tracking information, label data, and other shipping details
	 * to the order's meta data after a successful shipping label generation.
	 *
	 * @param int   $post_id   The order ID.
	 * @param array $response  The API response data.
	 * @param bool  $update    Whether this is an update to an existing shipment.
	 * @throws Exception If an error occurs during metadata saving.
	 * @since 1.0.0
	 */
	public function save($post_id, array $response, bool $update = false)
	{
		$order = wc_get_order($post_id);

		$parcel_id = $response['id'];
		$trackingNumber = $response['tracking'];

		$label = get_rest_url(null, 'woo-bordereau/v1/slip/print/'.$this->provider['slug'].'/'.$post_id);

		update_post_meta($post_id, '_shipping_near_delivery_parcel_id', wc_clean($parcel_id));
		update_post_meta($post_id, '_shipping_tracking_number', wc_clean($trackingNumber));
		update_post_meta($post_id, '_shipping_tracking_label', wc_clean($label));
		update_post_meta($post_id, '_shipping_label', wc_clean($label));
		update_post_meta($post_id, '_shipping_tracking_url', 'https://neardelivery.app/tracking/'. wc_clean($trackingNumber));
		update_post_meta($post_id, '_shipping_tracking_method', $this->provider['slug']);

		if($order) {
			$order->update_meta_data('_shipping_near_delivery_parcel_id', wc_clean($parcel_id));
			$order->update_meta_data('_shipping_tracking_number', wc_clean($trackingNumber));
			$order->update_meta_data('_shipping_tracking_label', wc_clean($label));
			$order->update_meta_data('_shipping_label', wc_clean($label));
			$order->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
			$order->save();

			Helpers::make_note($order, $trackingNumber, $this->provider['name']);
		}

		$track = 'https://neardelivery.app/tracking/'. wc_clean($trackingNumber);

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
		$url =  add_query_arg('_wpnonce', $nonce, $label);

		wp_send_json([
			'message' => __("Tracking number has been successfully created", 'wc-bordereau-generator'),
			'provider' => $this->provider['name'],
			'tracking' => $current_tracking_data,
			'label' => wc_clean($url)
		], 200);

	}

	/**
	 * Remove shipping metadata from an order.
	 *
	 * Deletes all shipping-related metadata from both postmeta and
	 * order meta tables for the specified order.
	 *
	 * @param int $post_id  The order ID.
	 */
	private function reset_meta($post_id) {

		delete_post_meta($post_id, '_shipping_tracking_number');
		delete_post_meta($post_id, '_shipping_near_delivery_parcel_id');
		delete_post_meta($post_id, '_shipping_tracking_label');
		delete_post_meta($post_id, '_shipping_label');
		delete_post_meta($post_id, '_shipping_tracking_label_method');
		delete_post_meta($post_id, '_shipping_tracking_url');

		$order = wc_get_order($post_id);

		if($order) {
			$order->delete_meta_data('_shipping_tracking_number');
			$order->delete_meta_data('_shipping_near_delivery_parcel_id');
			$order->delete_meta_data('_shipping_tracking_label');
			$order->delete_meta_data('_shipping_label');
			$order->delete_meta_data('_shipping_tracking_label_method');
			$order->delete_meta_data('_shipping_tracking_method');
			$order->save();
		}
	}

	/**
	 * Get detailed tracking information.
	 *
	 * @param string $tracking_number  The tracking number to get details for.
	 * @return void
	 */
	public function track_detail($tracking_number)
	{
		// Placeholder for track detail implementation
	}


	/**
	 * Get communes (municipalities) for a wilaya (province).
	 *
	 * Retrieves a list of communes for the specified wilaya from the
	 * Near Delivery API wilayas endpoint which includes communes.
	 *
	 * @param int  $wilaya_id    The wilaya (province) ID/code.
	 * @param bool $hasStopDesk  Whether stop desk delivery is available.
	 *
	 * @return array  Array of commune data.
	 */
	public function get_communes($wilaya_id, bool $hasStopDesk)
	{
		$wilaya_code = str_pad(str_replace('DZ-', '', $wilaya_id), 2, '0', STR_PAD_LEFT);

		// Get wilayas with communes from API
		$wilayas_data = $this->get_wilayas_with_communes();

		// Find the wilaya and get its communes
		$communes_data = [];
		foreach ($wilayas_data as $wilaya) {
			if ($wilaya['wilaya_code'] == $wilaya_code && isset($wilaya['communes'])) {
				$communes_data = $wilaya['communes'];
				break;
			}
		}

		// Format the communes
		$communesResult = [];

		foreach ($communes_data as $commune) {
			// Check if this commune has centers available (for stop desk)
			$has_stop_desk = $this->commune_has_center($commune['code'] ?? $commune['name']);

			// If hasStopDesk filter is enabled, only return communes with centers
			if ($hasStopDesk && !$has_stop_desk) {
				continue;
			}

			$communesResult[] = [
				'id'            => $commune['code'] ?? $commune['name'],
				'name'          => $commune['name'],
				'code'          => $commune['code'] ?? null,
				'has_stop_desk' => $has_stop_desk,
			];
		}

		return $communesResult;
	}

	/**
	 * Check if a commune has a buralist available
	 *
	 * @param string $commune_code Commune code
	 * @return bool Whether the commune has a buralist
	 */
	private function commune_has_center($commune_code)
	{
		$buralists = $this->get_all_buralists_cached();

		foreach ($buralists as $buralist) {
			if (($buralist['commune_code'] ?? '') == $commune_code) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get all buralists (pickup points) with caching
	 *
	 * @return array Array of all buralists
	 */
	private function get_all_buralists_cached()
	{
		$cache_path = Functions::get_path($this->provider['slug'] . '_buralists_' . date('Y_W') . '.json');

		if (file_exists($cache_path)) {
			$data = json_decode(file_get_contents($cache_path), true);
			if (!empty($data)) {
				return $data;
			}
		}

		// Fetch from API
		$url = $this->provider['api_url'] . '/sender/buralists';

		$request = wp_safe_remote_get($url, [
			'headers' => [
				'Content-Type' => 'application/json',
				'ApiKey' => $this->api_key,
				'ApiSecret' => $this->api_secret
			],
			'timeout' => 30
		]);

		if (!is_wp_error($request)) {
			$body = wp_remote_retrieve_body($request);
			$response = json_decode($body, true);

			if (isset($response['status']) && $response['status'] === true && isset($response['data'])) {
				file_put_contents($cache_path, json_encode($response['data']));
				return $response['data'];
			}
		}

		return [];
	}

	/**
	 * Format tracking response data.
	 *
	 * Processes the tracking API response to extract status information
	 * and formats it for display to the user.
	 *
	 * @param array $response_array  The tracking API response.
	 * @return array  Formatted tracking details.
	 */
	private function format_tracking($response_array)
	{
		$result = [];

		if (is_array($response_array)) {
			foreach ($response_array as $key => $item) {
				$status_name = $item['status'] ?? $item['event_type'] ?? '';

				if ($status_name === 'RETURNED' || $status_name === 'RETURN_INITIALIZED') {
					$result[$key]['failed'] = true;
				}

				if ($status_name === 'DELIVERED') {
					$result[$key]['delivered'] = true;
				}

				$result[$key]['date'] = $item['timestamp'] ?? $item['created_at'] ?? '';
				$result[$key]['status'] = $item['description'] ?? $status_name;

				if (isset($item['color'])) {
					$result[$key]['color'] = '#' . $item['color'];
				}
			}
		}

		$last_status = end($result)['status'] ?? '';

		if ($last_status) {
			BordereauGeneratorAdmin::update_tracking_status($this->formData, $last_status, 'single_tracking');
		}

		return $result;
	}


	/**
	 * Format detailed tracking information.
	 *
	 * Processes a tracking response item to extract detailed delivery
	 * information including address, contact details, and product info.
	 *
	 * @param array $item  A tracking response item from Near Delivery API.
	 * @return array  Formatted tracking details.
	 */
	private function format_tracking_detail(array $item)
	{
		$detail = [];

		// Handle recipient data which may be nested
		$recipient = $item['recipient'] ?? $item;

		$recipientName = $recipient['name'] ?? $item['recipient_name'] ?? '';
		$nameParts = explode(' ', $recipientName, 2);

		$detail[0]['address'] = $recipient['address'] ?? $item['recipient_address'] ?? '';
		$detail[0]['phone'] = $recipient['phone'] ?? $item['recipient_phone'] ?? '';
		$detail[0]['fname'] = $nameParts[0] ?? '';
		$detail[0]['lname'] = $nameParts[1] ?? '';
		$detail[0]['email'] = $recipient['email'] ?? $item['recipient_email'] ?? '';

		// Payment preference: "Standard Payment" = COD, check for free shipping
		$paymentPref = $item['payment_preference'] ?? '';
		$detail[0]['free'] = (stripos($paymentPref, 'free') !== false) || $paymentPref == 2;

		$detail[0]['exchange'] = false;

		// Pickup location type: 1 = Center Pickup, 2 = Home Delivery
		$pickupType = $item['pickup_location_type'] ?? 2;
		$detail[0]['stopdesk'] = ($pickupType == 1 || strtolower($item['pickup_location_type_label'] ?? '') === 'center pickup');

		$detail[0]['last_status'] = $item['current_status'] ?? $item['status'] ?? '';
		$detail[0]['price'] = $item['parcel_fees'] ?? 0;
		$detail[0]['delivery_fees'] = $item['delivery_fees'] ?? 0;

		// Get commune and wilaya from recipient or item
		$detail[0]['commune'] = $recipient['commune'] ?? $item['recipient_commune'] ?? '';
		$detail[0]['wilaya'] = $recipient['wilaya'] ?? $item['recipient_wilaya'] ?? '';

		$detail[0]['product_name'] = $item['declared_contents'] ?? null;
		$detail[0]['reference'] = $item['reference'] ?? '';
		$detail[0]['tracking_number'] = $item['tracking_number'] ?? '';

		// Pickup location details if available
		if (isset($item['pickup_location_details'])) {
			$detail[0]['pickup_center'] = $item['pickup_location_details']['center'] ?? null;
		}

		return $detail;
	}

	/**
	 * Sort tracking events by date.
	 *
	 * Comparison function for sorting tracking events in reverse
	 * chronological order (newest first).
	 *
	 * @param array $a  First tracking event.
	 * @param array $b  Second tracking event.
	 * @return int  Comparison result (-1, 0, or 1).
	 */
	function sortByOrder($a, $b)
	{
		$t1 = strtotime($a['date']);
		$t2 = strtotime($b['date']);
		return $t2 - $t1;
	}

	/**
	 * Get all wilayas (provinces).
	 *
	 * Retrieves a list of wilayas from the Near Delivery API /sender/wilayas endpoint.
	 * This endpoint returns wilayas with their communes.
	 *
	 * @return array  Array of wilaya data.
	 */
	public function get_wilayas(): array
	{
		$wilayas_data = $this->get_wilayas_with_communes();

		// Extract just the wilaya info without communes
		$wilayas = [];
		foreach ($wilayas_data as $wilaya) {
			$wilayas[] = [
				'id' => $wilaya['wilaya_code'],
				'code' => $wilaya['wilaya_code'],
				'name' => $wilaya['wilaya_name']
			];
		}

		return $wilayas;
	}

	/**
	 * Get wilayas by provider.
	 *
	 * Retrieves wilayas (provinces) for a specific provider.
	 *
	 * @param array $provider  The provider configuration.
	 * @return array  Array of formatted wilaya data.
	 */
	public function get_wilayas_by_provider($provider): array
	{
		$wilayas = $this->get_wilayas();
		$wilayasResult = [];

		foreach ($wilayas as $i => $wilaya) {
			$wilayasResult[$i]['name'] = $wilaya['name'];
			$wilayasResult[$i]['id'] = $wilaya['code'] ?? $wilaya['id'];
			$wilayasResult[$i]['uuid'] = $wilaya['id'];
		}

		return $wilayasResult;
	}


	/**
	 * Get detailed package information.
	 *
	 * Fetches and formats detailed package information for a tracking number.
	 *
	 * @param string $tracking_number  The tracking number.
	 * @param int    $post_id          The order ID.
	 * @return void  Outputs JSON response with tracking details.
	 * @since 1.2.0
	 */
	public function detail($tracking_number, $post_id)
	{
		$url = $this->provider['api_url'] . '/sender/parcels/' . $tracking_number;

		$request = wp_safe_remote_get($url, [
			'headers' => [
				'Content-Type' => 'application/json',
				'ApiKey' => $this->api_key,
				'ApiSecret' => $this->api_secret
			],
			'timeout' => 30
		]);

		if (is_wp_error($request)) {
			wp_send_json([
				'tracking' => $tracking_number,
				'detail' => [],
				'error' => $request->get_error_message()
			]);
			return;
		}

		$body = wp_remote_retrieve_body($request);
		$response = json_decode($body, true);

		// Near Delivery API returns: { "status": true, "message": "Parcel found", "data": {...} }
		if (!isset($response['status']) || $response['status'] !== true || !isset($response['data'])) {
			wp_send_json([
				'tracking' => $tracking_number,
				'detail' => []
			]);
			return;
		}

		$parcelData = $response['data'];
		$details = $this->format_tracking_detail($parcelData);

		if (isset($details[0]['last_status'])) {
			BordereauGeneratorAdmin::update_tracking_status($this->formData, $details[0]['last_status'], 'single_tracking');
		}

		wp_send_json([
			'tracking' => $tracking_number,
			'detail' => $details
		]);
	}


	/**
	 * Delete a shipment.
	 *
	 * Cancels a shipment with Near Delivery and removes all related
	 * shipping metadata from the order.
	 *
	 * @param string $tracking_number  The tracking number to delete.
	 * @param int    $post_id          The order ID.
	 * @return null
	 * @since 1.3.0
	 */
	public function delete($tracking_number, $post_id)
	{
		// Near Delivery DELETE uses parcel ID or tracking number
		$parcel_id = get_post_meta($post_id, '_shipping_near_delivery_parcel_id', true);

		$identifier = $parcel_id ?: $tracking_number;

		if (!empty($identifier)) {
			$url = $this->provider['api_url'] . "/sender/parcels/" . $identifier;

			$request = wp_safe_remote_request($url, [
				'method' => 'DELETE',
				'headers' => [
					'Content-Type' => 'application/json',
					'ApiKey' => $this->api_key,
					'ApiSecret' => $this->api_secret
				],
				'timeout' => 30
			]);

			if (is_wp_error($request)) {
				error_log('Near Delivery delete error: ' . $request->get_error_message());
			}
		}

		$this->reset_meta($post_id);

		return null;
	}


	/**
	 * Get the shipping label.
	 *
	 * Retrieves the shipping label URL from Near Delivery API and
	 * redirects the browser to display or download it.
	 *
	 * @return void  Redirects to the label URL.
	 * @since 2.2.0
	 */
	public function get_slip()
	{
		$tracking = $this->formData->get_meta('_shipping_tracking_number', true);

		if (empty($tracking)) {
			return [];
		}

		$url = $this->provider['api_url'] . "/sender/parcels/" . $tracking . "/bordereau";

		$request = wp_safe_remote_get(
			$url,
			[
				'headers'     => [
					'Content-Type' => 'application/json',
					'ApiKey'    => $this->api_key,
					'ApiSecret'     => $this->api_secret
				],
				'timeout'     => 15
			]
		);

		if ( ! is_wp_error( $request ) ) {
			$content_type = wp_remote_retrieve_header($request, 'content-type');

			// If it returns PDF directly
			if (strpos($content_type, 'application/pdf') !== false) {
				$body = wp_remote_retrieve_body($request);
				header('Content-Type: application/pdf');
				header('Content-Disposition: inline; filename="bordereau_' . $tracking . '.pdf"');
				echo $body;
				exit;
			}

			// If it returns a URL
			$body = wp_remote_retrieve_body($request);
			$response = json_decode($body, true);

			if (isset($response['url'])) {
				header('Location: '. $response['url']);
				exit;
			}
		}

		return [];
	}


	/**
	 * Get tracking information for an order.
	 *
	 * Retrieves the current status and tracking events for a package.
	 *
	 * @param string $tracking_number  The tracking number.
	 * @param int|false $post_id       The order ID (optional).
	 * @return void  Outputs JSON response with tracking events.
	 */
	public function track($tracking_number, $post_id = false)
	{
		// First try to get the parcel details to get current status
		$url = $this->provider['api_url'] . '/sender/parcels/' . $tracking_number;

		$request = wp_safe_remote_get($url, [
			'headers' => [
				'Content-Type' => 'application/json',
				'ApiKey' => $this->api_key,
				'ApiSecret' => $this->api_secret
			],
			'timeout' => 30
		]);

		if (is_wp_error($request)) {
			wp_send_json([
				'tracking' => $tracking_number,
				'events' => [],
				'error' => $request->get_error_message()
			]);
			return;
		}

		$body = wp_remote_retrieve_body($request);
		$response = json_decode($body, true);

		// Near Delivery API returns: { "status": true, "message": "...", "data": {...} }
		if (!isset($response['status']) || $response['status'] !== true || !isset($response['data'])) {
			wp_send_json([
				'tracking' => $tracking_number,
				'events' => []
			]);
			return;
		}

		$parcelData = $response['data'];

		// Build events from current status
		$events = [];
		$current_status = $parcelData['current_status'] ?? '';

		if (!empty($current_status)) {
			$events[] = [
				'date' => $parcelData['paid_at'] ?? date('Y-m-d H:i:s'),
				'status' => $current_status,
				'delivered' => ($current_status === 'DELIVERED'),
				'failed' => in_array($current_status, ['RETURNED', 'RETURN_INITIALIZED', 'CANCELLED'])
			];

			// Update order meta if we have a post_id
			if ($post_id && $this->formData) {
				BordereauGeneratorAdmin::update_tracking_status($this->formData, $current_status, 'single_tracking');
			}
		}

		wp_send_json([
			'tracking' => $tracking_number,
			'events' => $events,
			'current_status' => $current_status,
			'parcel_data' => [
				'parcel_id' => $parcelData['parcel_id'] ?? null,
				'delivery_type' => $parcelData['delivery_type'] ?? null,
				'parcel_fees' => $parcelData['parcel_fees'] ?? null,
				'delivery_fees' => $parcelData['delivery_fees'] ?? null
			]
		]);
	}

	/**
	 * Get buralists (pickup points) for a commune/wilaya
	 *
	 * @param $commune
	 * @return array
	 */
	public function get_centers($commune): array
	{
		// Get all buralists from cache
		$buralists_data = $this->get_all_buralists_cached();

		// Get wilaya code from POST data
		$wilayaCode = str_pad(str_replace('DZ-', '', $_POST['wilaya'] ?? ''), 2, '0', STR_PAD_LEFT);

		// Get commune from POST data or parameter
		$communeFilter = $_POST['commune'] ?? $commune ?? '';
		$communeFilter = urldecode($communeFilter);

		// Get wilayas with communes to get commune names and codes
		$wilayas_data = $this->get_wilayas_with_communes();
		$commune_names = [];
		$commune_name_to_code = [];
		foreach ($wilayas_data as $wilaya) {
			if (isset($wilaya['communes'])) {
				foreach ($wilaya['communes'] as $comm) {
					$commune_names[$comm['code']] = $comm['name'];
					$commune_name_to_code[strtolower($comm['name'])] = $comm['code'];
				}
			}
		}

		// Try to get commune code from commune name if needed
		$communeCode = '';
		if (!empty($communeFilter)) {
			// Check if it's already a code or if we need to look it up by name
			if (isset($commune_names[$communeFilter])) {
				$communeCode = $communeFilter;
			} else {
				$communeCode = $commune_name_to_code[strtolower($communeFilter)] ?? '';
			}
		}

		// Filter buralists by wilaya code and commune code
		$filtered_buralists = array_filter($buralists_data ?? [], function ($item) use ($wilayaCode, $communeCode) {
			$matchesWilaya = ($item['wilaya_code'] ?? '') == $wilayaCode;

			// If commune filter is provided, also filter by commune
			if (!empty($communeCode)) {
				return $matchesWilaya && ($item['commune_code'] ?? '') == $communeCode;
			}

			return $matchesWilaya;
		});

		// Format the results - show commune name, username, and phone number
		$centersResult = [];
		foreach ($filtered_buralists as $item) {
			$item_commune_code = $item['commune_code'] ?? '';
			$commune_name = $commune_names[$item_commune_code] ?? '';

			$centersResult[] = [
				'id' => $item['id'],
				'name' => $commune_name . ' - ' . ($item['center_address'] ?? '') . ' (' . ($item['phone_number'] ?? '') . ')',
				'username' => $item['username'] ?? '',
				'phone_number' => $item['phone_number'] ?? '',
				'center_address' => $item['center_address'] ?? '',
				'commune_name' => $commune_name,
				'commune_code' => $item_commune_code,
				'wilaya_code' => $item['wilaya_code'] ?? '',
				'center_id' => $item['center_id'] ?? ''
			];
		}

		return $centersResult;
	}

	/**
	 * Return the settings we need in the metabox
	 * @return array
	 *@since 1.2.4
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
			$cart_value =  (float) $order->get_total() - $order->get_total_shipping();
		}

		$total_weight = Helpers::get_product_weight($order);

		$data = [];
		if ($key) {
			$data['total_calculate'] = get_option($key) ? get_option($key) : 'with-shipping';
			$data['type'] = $provider['type'];
			$data['total'] = $cart_value;
			$data['order_number'] = $order->get_order_number();
			$data['products_string'] = $productString;
			$data['total_weight'] = $total_weight;

		}
		return $data;
	}
}
