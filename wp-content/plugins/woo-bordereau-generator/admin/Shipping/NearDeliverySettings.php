<?php

namespace WooBordereauGenerator\Admin\Shipping;

/**
 * Near Delivery Settings Manager.
 *
 * This class handles the configuration settings for the Near Delivery shipping provider,
 * including API settings, shipping rates, zones management, and package tracking status.
 *
 * @package    WC_Bordereau_Generator
 * @subpackage WC_Bordereau_Generator/admin/shipping
 * @author     Boukraa Mohamed <bm2ilabs@gmail.com>
 */


use WC_Shipping_Zone;
use WC_Shipping_Zones;
use WooBordereauGenerator\Admin\BordereauGeneratorAdmin;
use WooBordereauGenerator\Functions;
use WooBordereauGenerator\Helpers;
use WP_Error;

/**
 * Near Delivery settings management class.
 *
 * Manages the settings and configuration for the Near Delivery shipping provider,
 * including API credentials, shipping zones, and bulk tracking.
 */
class NearDeliverySettings {
	/**
	 * Provider configuration data.
	 *
	 * @var array
	 */
	private $provider;

	/**
	 * Near Delivery API Key
	 *
	 * @var string|null
	 */
	protected $api_key;

	/**
	 * Near Delivery API Secret
	 *
	 * @var string|null
	 */
	private $api_secret;

	/**
	 * Constructor.
	 *
	 * Initializes a new Near Delivery settings manager with the provider configuration.
	 *
	 * @param array $provider The provider configuration array.
	 */
	public function __construct( array $provider ) {

		$this->api_key  = get_option( $provider['slug'] . '_api_key' );
		$this->api_secret = get_option( $provider['slug'] . '_api_secret' );
		$this->provider = $provider;
	}

	/**
	 * Get the provider settings configuration.
	 *
	 * Returns an array of settings fields for the Near Delivery provider
	 * that can be used to render the settings form.
	 *
	 * @return array Settings field configuration.
	 */
	public function get_settings() {

		$fields = [
			array(
				'title' => __( $this->provider['name'] . ' API Settings', 'woocommerce' ),
				'type'  => 'title',
				'id'    => 'store_address',
			),
		];

		foreach ( $this->provider['fields'] as $key => $filed ) {
			$fields[ $key ] = array(
				'name'     => __( $filed['name'], 'woo-bordereau-generator' ),
				'id'       => $filed['value'],
				'type'     => 'text',
				'desc'     => $filed['desc'],
				'desc_tip' => true,
			);
		}

		$fields[] = array(
			'type' => 'sectionend',
			'id'   => 'store_address',
		);

		return $fields;
	}


	/**
	 * Bulk tracking functionality.
	 *
	 * Handles bulk tracking operations for multiple orders.
	 * Retrieves tracking status for all orders with tracking numbers.
	 *
	 * @return array Tracking results for all orders.
	 */
	public function bulk_tracking() {

		$order_ids = $_POST['ids'] ?? [];
		$update    = $_POST['update'] ?? false;

		if ( empty( $order_ids ) ) {
			return [];
		}

		$tracking_data = [];

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				continue;
			}

			$tracking_number = $order->get_meta( '_shipping_tracking_number', true );

			if ( empty( $tracking_number ) ) {
				continue;
			}

			try {
				// Near Delivery API: GET /sender/parcels/{tracking_number}
				$url = $this->provider['api_url'] . '/sender/parcels/' . $tracking_number;

				$request = wp_safe_remote_get($url, [
					'headers' => [
						'Content-Type' => 'application/json',
						'ApiKey' => $this->api_key,
						'ApiSecret' => $this->api_secret
					],
					'timeout' => 30
				]);

				if ( is_wp_error( $request ) ) {
					continue;
				}

				$body = wp_remote_retrieve_body( $request );
				$response = json_decode( $body, true );

				// Near Delivery API returns: { "status": true, "message": "...", "data": {...} }
				if ( isset( $response['status'] ) && $response['status'] === true && isset( $response['data'] ) ) {
					$parcelData = $response['data'];
					$current_status = $parcelData['current_status'] ?? '';

					if ( ! empty( $current_status ) ) {
						$tracking_data[ $order_id ] = [
							'tracking_number' => $tracking_number,
							'status'          => $current_status,
							'date'            => $parcelData['paid_at'] ?? '',
							'color'           => $this->get_status_color($current_status)
						];

						if ( $update ) {
							$order->update_meta_data( '_latest_tracking_status', $current_status );
							$order->save();
						}
					}
				}
			} catch ( \Exception $e ) {
				error_log( 'Near Delivery bulk tracking error for order ' . $order_id . ': ' . $e->getMessage() );
			}
		}

		return $tracking_data;
	}

	/**
	 * Get status color for display
	 *
	 * @param string $status Status code
	 * @return string Hex color code
	 */
	private function get_status_color($status) {
		$colors = [
			'PENDING' => '#FFA500',
			'PICKED_UP' => '#3498DB',
			'IN_TRANSIT' => '#9B59B6',
			'OUT_FOR_DELIVERY' => '#2ECC71',
			'DELIVERED' => '#27AE60',
			'RETURNED' => '#E74C3C',
			'RETURN_INITIALIZED' => '#E74C3C',
			'CANCELLED' => '#95A5A6',
		];

		return $colors[$status] ?? '#000000';
	}

	/**
	 * Handle webhook from Near Delivery
	 *
	 * @param array $provider Provider configuration
	 * @param string $jsonData JSON webhook payload
	 * @param array $headers Request headers (optional, for signature verification)
	 * @return array Response array with success status
	 */
	public function handle_webhook( $provider, $jsonData, $headers = [] ) {
		$data = json_decode( $jsonData, true );

		if ( ! $data ) {
			return [
				'success' => false,
				'message' => __('Invalid JSON payload', 'woo-bordereau-generator')
			];
		}

		// Extract tracking number and status from webhook data
		$tracking = $data['tracking_number'] ?? null;
		$external_id = $data['reference'] ?? null;
		$status_text = $data['status'] ?? $data['event_type'] ?? null;

		if ( ! $tracking && ! $external_id ) {
			return [
				'success' => false,
				'message' => __('Missing tracking number and reference', 'woo-bordereau-generator')
			];
		}

		// Find the order
		$theorder = $this->find_order_by_tracking_or_external_id( $tracking, $external_id );

		if ( ! $theorder ) {
			return [
				'success' => false,
				'message' => __('Order not found for tracking: ', 'woo-bordereau-generator') . $tracking . __(' / reference: ', 'woo-bordereau-generator') . $external_id
			];
		}

		// Update order meta with latest status
		if ( ! empty( $status_text ) ) {
			BordereauGeneratorAdmin::update_tracking_status( $theorder, $status_text, 'webhook' );

			// Add order note
			$theorder->add_order_note(
				sprintf(
					__( 'Near Delivery Update: %s', 'woo-bordereau-generator' ),
					$status_text
				)
			);

			// Update order status based on Near Delivery state
			( new \WooBordereauGenerator\Admin\BordereauGeneratorAdmin( WC_BORDEREAU_POST_TYPE, WC_BORDEREAU_GENERATOR_VERSION ) )
				->update_orders_status( $theorder, $status_text );

			return [
				'success' => true,
				'updated' => 1,
				'order_id' => $theorder->get_id(),
				'status' => $status_text,
				'tracking' => $tracking
			];
		}

		return [
			'success' => false,
			'message' => __('No status update in webhook data', 'woo-bordereau-generator')
		];
	}

	/**
	 * Find order by tracking number or external ID
	 *
	 * @param string|null $tracking Tracking number
	 * @param string|null $external_id External order ID (reference)
	 * @return \WC_Order|false Order object or false if not found
	 */
	private function find_order_by_tracking_or_external_id( $tracking, $external_id ) {
		// Try to find order by external ID first (if provided)
		if ( ! empty( $external_id ) ) {
			// Try direct order lookup if external_id is numeric
			if ( is_numeric( $external_id ) ) {
				$order = wc_get_order( $external_id );
				if ( $order ) {
					return $order;
				}
			}

			// Search by order number
			$args = array(
				'limit'      => -1,
				'status'     => 'any',
				'orderby'    => 'ID',
				'order'      => 'DESC',
				'type'       => 'shop_order',
				'return'     => 'objects',
			);

			$query  = new \WC_Order_Query( $args );
			$orders = $query->get_orders();

			foreach ( $orders as $order ) {
				if ( $order->get_order_number() == $external_id ) {
					return $order;
				}
			}
		}

		// If not found by external ID, search by tracking number in post meta
		if ( ! empty( $tracking ) ) {
			$args = array(
				'limit'      => 1,
				'status'     => 'any',
				'meta_key'   => '_shipping_tracking_number',
				'meta_value' => $tracking,
				'type'       => 'shop_order',
				'return'     => 'objects',
			);

			$query  = new \WC_Order_Query( $args );
			$orders = $query->get_orders();

			if ( ! empty( $orders ) ) {
				return $orders[0];
			}
		}

		return false;
	}

	/**
	 * Get wilayas (provinces) from Near Delivery or local data
	 *
	 * @return array Array of wilayas with id, code, and name
	 */
	private function get_wilayas() {

		$path = Functions::get_path($this->provider['slug']. '_wilayas.json');

		$wilayasResult = [];

		if (file_exists($path)) {
			$result = json_decode(file_get_contents($path), true);

			if ($result == null || count($result) == 0) {
				$result = $this->get_wilayas_from_api();
			}
		} else {
			$result = $this->get_wilayas_from_api();
			if (!empty($result)) {
				file_put_contents($path, json_encode($result));
			}
		}

		if (isset($result)) {
			foreach ($result as $i => $wilaya) {
				$wilayasResult[$i]['name'] = $wilaya['name'];
				$wilayasResult[$i]['id'] = $wilaya['id'];
				$wilayasResult[$i]['code'] = $wilaya['code'] ?? $wilaya['id'];
			}

			return $wilayasResult;
		}

		return $wilayasResult;
	}

	/**
	 * Get wilayas from Near Delivery API
	 *
	 * @return array Array of wilayas
	 */
	private function get_wilayas_from_api() {

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

			// Extract unique wilayas from centers
			if (is_array($response)) {
				$wilayas = [];
				foreach ($response['data'] as $wilaya) {
					$wilaya_id = $wilaya['wilaya_code'];
					$wilaya_name = $wilaya['wilaya_name'];
					$wilayas[$wilaya_id] = [
						'id' => $wilaya_id,
						'code' => $wilaya_id,
						'name' => $wilaya_name
					];

				}

				return array_values($wilayas);
			}
		}

		return [];
	}

	/**
	 * Get default Algeria wilayas
	 *
	 * @return array Array of default wilayas
	 */


	/**
	 * Get communes for a specific wilaya
	 *
	 * @param int $wilaya_code Wilaya code
	 * @param bool $hasStopDesk Filter by centers availability
	 * @return array Array of communes
	 */
	public function get_communes( $wilaya_code, $hasStopDesk = false ) {

		$wilaya_id = (int) str_replace('DZ-', '', $wilaya_code);

		// Get communes data with caching
		$communes_path = Functions::get_path($this->provider['slug'].'_communes_'.$wilaya_id.'.json');

		if (!file_exists($communes_path)) {
			// Near Delivery may not have a communes endpoint, use local data
			$communes_data = [];
			file_put_contents($communes_path, json_encode($communes_data));
		}

		$communes_data = json_decode(file_get_contents($communes_path), true);

		// Format the communes
		$communesResult = [];

		foreach ($communes_data as $commune) {
			$has_stop_desk = isset($commune['has_center']) && $commune['has_center'];

			if ($hasStopDesk && !$has_stop_desk) {
				continue;
			}

			$communesResult[] = [
				'id'            => $commune['id'] ?? $commune['name'],
				'name'          => $commune['name'],
				'code'          => $commune['code'] ?? null,
				'has_stop_desk' => $has_stop_desk,
			];
		}

		return $communesResult;
	}

	/**
	 * Bulk add orders to Near Delivery
	 *
	 * @param array $order_ids Array of order IDs to create parcels for
	 * @return array Response with success/failure counts and details
	 */
	public function bulk_add_orders( $order_ids ) {

		if ( empty( $order_ids ) ) {
			return [
				'success' => false,
				'message' => __( 'No orders provided', 'woo-bordereau-generator' )
			];
		}

		$parcels = [];
		$wilayas_data = $this->get_wilayas_with_communes();
		$sender_center_id = get_option($this->provider['slug'] . '_sender_center_id');

		if ( empty( $sender_center_id ) ) {
			return [
				'success' => false,
				'message' => __( 'Sender Center ID is not configured', 'woo-bordereau-generator' )
			];
		}

		foreach ( $order_ids as $order_id ) {

			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				continue;
			}

			$params = $order->get_data();

			// Get wilaya code
			$wilayaCode = str_pad(str_replace( "DZ-", "", $params['billing']['state'] ), 2, '0', STR_PAD_LEFT);

			if ( empty($wilayaCode) ) {
				continue; // Skip orders without valid wilaya
			}

			// Get commune code
			$communeCode = null;

			if ( isset( $params['billing']['city'] ) && ! empty( $params['billing']['city'] ) ) {
				foreach ($wilayas_data as $wilaya) {
					if ($wilaya['wilaya_code'] == $wilayaCode && isset($wilaya['communes'])) {
						foreach ($wilaya['communes'] as $commune) {
							if (strcasecmp($commune['name'], $params['billing']['city']) === 0) {
								$communeCode = $commune['code'];
								break 2;
							}
						}
					}
				}
			}

			// Build product string
			$products_array = $params['line_items'];
			$productString = Helpers::generate_products_string( $products_array );

			// Get customer info
			$phone = Helpers::clean_phone_number($params['billing']['phone']);
			$phone_2 = null;

			if ($order->get_meta('_billing_phone_2', true)) {
				$phone_2 = Helpers::clean_phone_number($order->get_meta('_billing_phone_2', true));
			}

			$firstName = $params['billing']['first_name'];
			$lastName = $params['billing']['last_name'];

			// Determine payment type: 0 = COD, 1 = Standard (pre-paid)
			$payment_method = $order->get_payment_method();
			$is_cod = ($payment_method === 'cod' || $payment_method === 'cheque' || $payment_method === 'bacs');
			$payment_preference = $is_cod ? 0 : 1;

			// Determine delivery type: 1 = Center Pickup, 2 = Home Delivery
			$stop_desk = false;
			if ( $order->has_shipping_method( 'local_pickup' ) || $order->has_shipping_method( 'local_pickup_near_delivery' ) ) {
				$stop_desk = true;
			}

			$orderId = (string) $order->get_order_number();

			// Calculate order total based on settings (with or without shipping)
			$total_setting_key = $this->provider['slug'] . '_total';
			$total_setting = get_option($total_setting_key, 'with-shipping');

			if ($total_setting === 'without-shipping') {
				// Calculate total without shipping
				$order_total = (float) $order->get_total() - (float) $order->get_total_shipping();
			} else {
				// Default: with shipping (full order total)
				$order_total = (float) $order->get_total();
			}

			// Build parcel payload for Near Delivery API
			$parcel = [
				'declared_contents' => mb_substr($productString, 0, 180),
				'recipient_name' => $firstName . ' ' . $lastName,
				'recipient_phone' => $phone,
				'recipient_address' => $params['billing']['address_1'] ?? '',
				'recipient_wilaya' => $wilayaCode,
				'payment_preference' => $payment_preference, // 0 = COD, 1 = Standard (pre-paid)
				'pickup_location_type' => $stop_desk ? 1 : 2, // 1 = Center Pickup, 2 = Home Delivery
				'parcel_fees' => $order_total,
				'reference' => $orderId,
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
			if (!empty($params['billing']['email'])) {
				$parcel['recipient_email'] = $params['billing']['email'];
			}

			// Add buralist_id if stop desk
			if ($stop_desk) {
				$center = $order->get_meta('_billing_center_id', true);
				if (! $center) {
					$center = get_post_meta($order->get_id(), '_billing_center_id', true);
				}
				if ($center) {
					$parcel['buralist_id'] = (int) $center;
				}
			}

			$parcels[] = $parcel;
		}

		if ( empty( $parcels ) ) {
			return [
				'success' => false,
				'message' => __( 'No valid orders to process', 'woo-bordereau-generator' )
			];
		}

		// Send bulk request with common/parcels structure
		$url = $this->provider['api_url'] . '/sender/parcels/bulk-create';

		$requestBody = [
			'common' => [
				'sender_center_id' => (int) $sender_center_id
			],
			'parcels' => $parcels
		];

		$request = wp_safe_remote_post( $url, [
			'headers' => [
				'Content-Type' => 'application/json',
				'Accept' => 'application/json',
				'ApiKey' => $this->api_key,
				'ApiSecret' => $this->api_secret
			],
			'body' => wp_json_encode( $requestBody ),
			'timeout' => 60
		] );

		if ( is_wp_error( $request ) ) {
			return [
				'success' => false,
				'message' => $request->get_error_message()
			];
		}

		$response_code = wp_remote_retrieve_response_code( $request );
		$response_body = wp_remote_retrieve_body( $request );
		$response = json_decode( $response_body, true );

		// Near Delivery API returns: { "status": true, "message": "...", "data": [...] }
		if ( $response_code >= 200 && $response_code < 300 && isset( $response['status'] ) && $response['status'] === true && isset( $response['data'] ) ) {
			// Update order metadata for successful parcels
			foreach ( $response['data'] as $createdParcel ) {
				$external_id = $createdParcel['reference'] ?? null;
				$tracking_number = $createdParcel['tracking_number'] ?? null;
				$parcel_id = $createdParcel['parcel_id'] ?? null;

				if (!$external_id) continue;

				// Find order by order number
				$order = null;

				// Try by order ID directly first
				if ( is_numeric( $external_id ) ) {
					$order = wc_get_order( $external_id );
				}

				if ( ! $order ) {
					// Search by order number in provided order_ids
					foreach ($order_ids as $oid) {
						$temp_order = wc_get_order($oid);
						if ($temp_order && $temp_order->get_order_number() == $external_id) {
							$order = $temp_order;
							break;
						}
					}
				}

				if ( $order ) {
					$order_id = $order->get_id();

					$label = get_rest_url(null, 'woo-bordereau/v1/slip/print/'.$this->provider['slug'].'/'.$order_id);

					update_post_meta($order_id, '_shipping_near_delivery_parcel_id', wc_clean($parcel_id));
					update_post_meta($order_id, '_shipping_tracking_number', wc_clean($tracking_number));
					update_post_meta($order_id, '_shipping_tracking_label', wc_clean($label));
					update_post_meta($order_id, '_shipping_label', wc_clean($label));
					update_post_meta($order_id, '_shipping_tracking_url', 'https://neardelivery.app/tracking/'. wc_clean($tracking_number));
					update_post_meta($order_id, '_shipping_tracking_method', $this->provider['slug']);

					$order->update_meta_data('_shipping_near_delivery_parcel_id', wc_clean($parcel_id));
					$order->update_meta_data('_shipping_tracking_number', wc_clean($tracking_number));
					$order->update_meta_data('_shipping_tracking_label', wc_clean($label));
					$order->update_meta_data('_shipping_label', wc_clean($label));
					$order->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
					$order->save();

					Helpers::make_note($order, $tracking_number, $this->provider['name']);
				}
			}

			$success_count = count($response['data']);

			return [
				'success' => true,
				'message' => sprintf(
					__( 'Successfully created %d parcels', 'woo-bordereau-generator' ),
					$success_count
				),
				'data' => $response
			];
		}

		return [
			'success' => false,
			'message' => isset( $response['message'] )
				? $response['message']
				: sprintf( __( 'Failed to create parcels. HTTP Code: %d', 'woo-bordereau-generator' ), $response_code ),
			'response' => $response
		];
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
	 * Get all buralists from Near Delivery
	 *
	 * @return array Array of all buralists
	 */
	public function get_all_centers() {

		$cache_file = Functions::get_path( $this->provider['slug'] . '_buralists_' . WC_BORDEREAU_GENERATOR_VERSION . '_' . date( 'Y' ) . '_' . ( date( 'z' ) + 1 ) . '.json' );

		// Check cache first
		if ( file_exists( $cache_file ) ) {
			$cached_buralists = json_decode( file_get_contents( $cache_file ), true );
			if ( $cached_buralists ) {
				return $this->format_buralists( $cached_buralists );
			}
		}

		// Fetch from API - use /sender/buralists endpoint
		$url = $this->provider['api_url'] . '/sender/buralists';

		$request = wp_safe_remote_get( $url, [
			'headers' => [
				'Content-Type' => 'application/json',
				'ApiKey' => $this->api_key,
				'ApiSecret' => $this->api_secret
			],
			'timeout' => 60
		] );

		if ( is_wp_error( $request ) ) {
			error_log( 'Near Delivery buralists fetch error: ' . $request->get_error_message() );
			return [];
		}

		$response = json_decode( wp_remote_retrieve_body( $request ), true );

		// Near Delivery API returns: { "status": true, "data": [...] }
		if ( ! isset( $response['status'] ) || $response['status'] !== true || ! isset( $response['data'] ) ) {
			return [];
		}

		$buralists_data = $response['data'];

		// Cache the raw buralists data
		file_put_contents( $cache_file, json_encode( $buralists_data ) );

		return $this->format_buralists( $buralists_data );
	}

	/**
	 * Format buralists data for display
	 * Shows: commune name, username, and phone number
	 *
	 * @param array $buralists Raw buralists data from API
	 * @return array Formatted buralists array
	 */
	private function format_buralists( array $buralists ) {
		// Get wilayas with communes to get commune names
		$wilayas_data = $this->get_wilayas_with_communes();
		$commune_names = [];
		foreach ( $wilayas_data as $wilaya ) {
			if ( isset( $wilaya['communes'] ) ) {
				foreach ( $wilaya['communes'] as $commune ) {
					$commune_names[ $commune['code'] ] = $commune['name'];
				}
			}
		}

		$formatted = [];
		foreach ( $buralists as $item ) {
			$commune_code = $item['commune_code'] ?? '';
			$commune_name = $commune_names[ $commune_code ] ?? '';

			$formatted[] = [
				'id'           => $item['id'] ?? '',
				'name'         => $commune_name . ' - ' . ( $item['center_address'] ?? '' ) . ' (' . ( $item['phone_number'] ?? '' ) . ')',
				'username'     => $item['username'] ?? '',
				'phone_number' => $item['phone_number'] ?? '',
				'commune_name' => $commune_name,
				'commune_code' => $commune_code,
				'center_address' => $item['center_address'],
				'wilaya_code'  => $item['wilaya_code'] ?? '',
				'center_id'    => $item['center_id'] ?? ''
			];
		}

		return $formatted;
	}

	/**
	 * Import shipping classes to WooCommerce zones
	 *
	 * @param string $flat_rate_label Label for flat rate shipping
	 * @param string $pickup_local_label Label for local pickup shipping
	 * @param bool $flat_rate_enabled Whether flat rate shipping is enabled
	 * @param bool $pickup_local_enabled Whether local pickup is enabled
	 * @param bool $centers_import_enabled Whether to import individual centers
	 *
	 * @return void
	 */
	public function import_shipping_class(
		$flat_rate_label,
		$pickup_local_label,
		$flat_rate_enabled,
		$pickup_local_enabled,
		$centers_import_enabled = null
	) {
		// Save label for local pickup (Near Delivery only supports stopdesk)
		if ( $pickup_local_enabled && $pickup_local_label ) {
			update_option( $this->provider['slug'] . '_pickup_local_label', $pickup_local_label );
		}

		// Get wilayas and fees
		$wilayas = $this->get_wilayas();
		$fees = $this->get_fees();

		// Get sender wilaya from settings (16, 25, or 31)
		$sender_wilaya = get_option( $this->provider['slug'] . '_sender_wilaya', '16' );

		// Index fees by to_wilaya for quick lookup
		$fees_by_destination = [];
		foreach ( $fees as $fee ) {
			if ( $fee['from_wilaya'] == $sender_wilaya ) {
				$fees_by_destination[ $fee['to_wilaya'] ] = $fee;
			}
		}

		// Set memory and time limits
		ini_set( 'memory_limit', '-1' );
		set_time_limit( 0 );

		// Process each wilaya
		foreach ( $wilayas as $wilaya ) {

			$wilaya_code = $wilaya['code'] ?? $wilaya['id'];
			$wilaya_code_padded = str_pad( $wilaya_code, 2, '0', STR_PAD_LEFT );
			$wilaya_name = $wilaya['name'];

			// Get shipping fee for this wilaya
			$shipping_fee = isset( $fees_by_destination[ $wilaya_code_padded ] )
				? (float) $fees_by_destination[ $wilaya_code_padded ]['b2b_fee']
				: 0;

			// Get or create shipping zone
			$zones = \WC_Shipping_Zones::get_zones();
			$found_zone = null;

			foreach ( $zones as $zone ) {
				if ( isset( $zone['zone_locations'][0] ) &&
				     $zone['zone_locations'][0]->code == 'DZ:DZ-' . str_pad( $wilaya_code, 2, '0', STR_PAD_LEFT ) ) {
					$found_zone = $zone;
					break;
				}
			}

			if ( ! $found_zone ) {
				$zone = new \WC_Shipping_Zone();
				$zone->set_zone_name( ucfirst( Helpers::normalizeCityName( $wilaya_name ) ) );
				$zone->set_zone_order( $wilaya_code );
				$zone->add_location( 'DZ:DZ-' . str_pad( $wilaya_code, 2, '0', STR_PAD_LEFT ), 'state' );
				$zone->save();
			} else {
				$zone = \WC_Shipping_Zones::get_zone( $found_zone['id'] );
			}

			// Add local pickup (stopdesk) method - Near Delivery only supports stopdesk
			if ( $pickup_local_enabled ) {
				$local_pickup_name = 'local_pickup_near_delivery';

				$instance_local_pickup = $zone->add_shipping_method( $local_pickup_name );

				if ( $instance_local_pickup ) {
					$shipping_method_local_pickup = \WC_Shipping_Zones::get_shipping_method( $instance_local_pickup );

					if ( $shipping_method_local_pickup ) {
						$shipping_method_configuration_local_pickup = [
							'woocommerce_' . $local_pickup_name . '_title'    => $pickup_local_label ?? __( 'Near Delivery Pickup', 'woo-bordereau-generator' ),
							'woocommerce_' . $local_pickup_name . '_cost'     => $shipping_fee,
							'woocommerce_' . $local_pickup_name . '_type'     => 'class',
							'woocommerce_' . $local_pickup_name . '_provider' => $this->provider['slug'],
							'instance_id'                                     => $instance_local_pickup,
						];

						$_REQUEST['instance_id'] = $instance_local_pickup;
						$shipping_method_local_pickup->set_post_data( $shipping_method_configuration_local_pickup );
						$shipping_method_local_pickup->update_option( 'provider', $this->provider['slug'] );
						$shipping_method_local_pickup->process_admin_options();
					}
				}
			}
		}

		wp_send_json_success( [
			'message' => sprintf(
				__( 'Successfully imported shipping zones for %d wilayas', 'woo-bordereau-generator' ),
				count( $wilayas )
			)
		] );
	}

	/**
	 * Get delivery fees from Near Delivery API
	 *
	 * @return array Array of delivery fees
	 */
	public function get_fees() {

		$cache_file = Functions::get_path( $this->provider['slug'] . '_fees_' . date( 'Y' ) . '_' . ( date( 'z' ) + 1 ) . '.json' );

		// Check cache first
		if ( file_exists( $cache_file ) ) {
			$cached_fees = json_decode( file_get_contents( $cache_file ), true );
			if ( $cached_fees ) {
				return $cached_fees;
			}
		}

		// Fetch from API with pagination
		$all_fees = [];
		$page = 1;
		$size = 150;

		do {
			$url = $this->provider['api_url'] . '/sender/delivery-fees?page=' . $page . '&size=' . $size;

			$request = wp_safe_remote_get( $url, [
				'headers' => [
					'Content-Type' => 'application/json',
					'ApiKey' => $this->api_key,
					'ApiSecret' => $this->api_secret
				],
				'timeout' => 60
			] );

			if ( is_wp_error( $request ) ) {
				error_log( 'Near Delivery fees fetch error: ' . $request->get_error_message() );
				break;
			}

			$response = json_decode( wp_remote_retrieve_body( $request ), true );

			// Near Delivery API returns: { "status": true, "data": [...] }
			if ( ! isset( $response['status'] ) || $response['status'] !== true || ! isset( $response['data'] ) ) {
				break;
			}

			$fees_data = $response['data'];
			$all_fees = array_merge( $all_fees, $fees_data );

			// If we got less than the page size, we've reached the end
			if ( count( $fees_data ) < $size ) {
				break;
			}

			$page++;
		} while ( true );

		// Cache the results
		if ( ! empty( $all_fees ) ) {
			file_put_contents( $cache_file, json_encode( $all_fees ) );
		}

		return $all_fees;
	}

	/**
	 * Get delivery fee for a specific wilaya
	 *
	 * @param string $to_wilaya Destination wilaya code
	 * @param string|null $from_wilaya Source wilaya code (optional, uses sender's wilaya setting if not provided)
	 * @return array|null Fee data or null if not found
	 */
	public function get_fee_for_wilaya( $to_wilaya, $from_wilaya = null ) {

		$fees = $this->get_fees();

		// Pad destination wilaya code with leading zeros
		$to_wilaya = str_pad( str_replace( 'DZ-', '', $to_wilaya ), 2, '0', STR_PAD_LEFT );

		// Use sender_wilaya setting if from_wilaya not provided
		if ( ! $from_wilaya ) {
			$from_wilaya = get_option( $this->provider['slug'] . '_sender_wilaya', '16' );
		} else {
			$from_wilaya = str_pad( str_replace( 'DZ-', '', $from_wilaya ), 2, '0', STR_PAD_LEFT );
		}

		foreach ( $fees as $fee ) {
			// Filter by both from_wilaya and to_wilaya
			if ( $fee['from_wilaya'] == $from_wilaya && $fee['to_wilaya'] == $to_wilaya ) {
				return [
					'id'             => $fee['id'],
					'from_wilaya'    => $fee['from_wilaya'],
					'to_wilaya'      => $fee['to_wilaya'],
					'zone'           => $fee['zone'],
					'delivery_delay' => $fee['delivery_delay'],
					'b2b_fee'        => (float) $fee['b2b_fee'],
					'return_fee'     => (float) $fee['return_fee']
				];
			}
		}

		return null;
	}

	/**
	 * Get all fees filtered by sender wilaya
	 *
	 * @return array Array of fees filtered by sender wilaya
	 */
	public function get_fees_by_sender() {
		$fees = $this->get_fees();
		$sender_wilaya = get_option( $this->provider['slug'] . '_sender_wilaya', '16' );

		return array_filter( $fees, function( $fee ) use ( $sender_wilaya ) {
			return $fee['from_wilaya'] == $sender_wilaya;
		} );
	}
}
