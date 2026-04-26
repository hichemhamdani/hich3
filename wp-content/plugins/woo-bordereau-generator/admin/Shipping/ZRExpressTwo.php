<?php

/**
 * ZR Express 2.0 shipping provider integration for WooCommerce Bordereau Generator.
 *
 * This class handles the integration with ZR Express 2.0 shipping services,
 * providing functionality for generating shipping labels, tracking packages,
 * managing shipping data, and interacting with the ZR Express API.
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
 * ZR Express 2.0 shipping provider implementation.
 *
 * Extends the BordereauProvider class and implements BordereauProviderInterface
 * to provide ZR Express 2.0-specific shipping functionality for the WooCommerce
 * Bordereau Generator plugin.
 */
class ZRExpressTwo extends \WooBordereauGenerator\Admin\Shipping\BordereauProvider implements \WooBordereauGenerator\Admin\Shipping\BordereauProviderInterface
{

	/**
	 * The ZR Express API Key.
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * The ZR Express Tenant ID.
	 *
	 * @var string
	 */
	private string $tenant_id;

	/**
	 * Stock type: 'local' or 'fulfilled'
	 *
	 * @var string
	 */
	private string $stock_type;

	/**
	 * Default product category ID (UUID)
	 *
	 * @var string
	 */
	private string $category_id;

	/**
	 * Default product subcategory ID (UUID)
	 *
	 * @var string
	 */
	private string $subcategory_id;

	/**
	 * Default status ID for new parcels
	 *
	 * @var string
	 */
	private string $default_status;

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
	 * Initializes a new ZR Express 2.0 provider instance with order data,
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
		$this->tenant_id = get_option( $provider['slug'] . '_tenant_id' );
		$this->stock_type = get_option( $provider['slug'] . '_stock_type' ) ?: 'local';
		$this->default_status = get_option( $provider['slug'] . '_default_status' ) ?: '8a948c66-1ab7-4433-aeb0-94219125d134';
		$this->category_id = '2e2edec1-1be7-46c9-b403-ae66ee4f1b6f';
		$this->subcategory_id = '944b343c-9f7b-4e36-9921-4dc2c90a2add';
		$this->provider = $provider;
		$this->data = $data;
	}

	/**
	 * Create or get existing customer in ZR Express 2.0
	 *
	 * @param array $customer_data Customer data (name, phone, email)
	 * @param array $address_data Address data (street, city, district, etc.)
	 * @return string|null Customer UUID or null on failure
	 */
	private function create_or_get_customer($customer_data, $address_data)
	{
		$order_id = $this->formData->get_id();

		// Check if customer was already created for this order
		$existing_customer_id = get_post_meta($order_id, '_zrexpress_v2_customer_id', true);

		if (!empty($existing_customer_id)) {
			return $existing_customer_id;
		}

		$url = $this->provider['api_url'] . '/customers';

		$data = [
			'name' => $customer_data['name'],
			'phone' => [
				'number1' => $customer_data['phone'],
				'number2' => $customer_data['phone2'] ?? '',
				'number3' => ''
			],
			'timeSlot' => 'morning',
			'instruction' => '',
			'deliveryPreference' => 'home',
			'addresses' => [
				[
					'street' => $address_data['street'],
					'city' => $address_data['city'],
					'district' => $address_data['district'],
					'postalCode' => $address_data['postalCode'] ?? '',
					'country' => 'algeria',
					'cityTerritoryId' => $address_data['cityTerritoryId'],
					'districtTerritoryId' => $address_data['districtTerritoryId'],
					'isPrimary' => true
				]
			]
		];

		$request = wp_safe_remote_post($url, [
			'headers' => [
				'Content-Type' => 'application/json',
				'Accept' => 'application/json',
				'X-Api-Key' => $this->api_key,
				'X-Tenant' => $this->tenant_id
			],
			'body' => wp_json_encode($data),
			'timeout' => 30
		]);

		if (is_wp_error($request)) {
			error_log('ZR Express 2.0 customer creation error: ' . $request->get_error_message());
			return null;
		}

		$response_code = wp_remote_retrieve_response_code($request);
		$response_body = wp_remote_retrieve_body($request);
		$response = json_decode($response_body, true);

		if ($response_code >= 200 && $response_code < 300 && isset($response['id'])) {
			$customer_id = $response['id'];

			// Cache customer ID in order meta
			update_post_meta($order_id, '_zrexpress_v2_customer_id', $customer_id);

			return $customer_id;
		}

		error_log('ZR Express 2.0 customer creation failed: ' . $response_body);
		return null;
	}

	/**
	 * Generate the tracking code.
	 *
	 * Creates a shipping label and tracking number for the order by
	 * formatting order data and sending it to the ZR Express API.
	 *
	 * @return mixed|WP_Error Returns the API response or WP_Error on failure.
	 * @throws Exception If an error occurs during the API request.
	 * @since 1.0.0
	 */
	public function generate()
	{
		$params = $this->formData->get_data();

		$orderId = $this->formData->get_order_number();

		$wilayas = $this->get_wilayas();

		if (isset($this->data['wilaya'])) {
			$wilayaId = (int)str_replace("DZ-", "", $this->data['wilaya']);
		} else {
			$wilayaId = (int)str_replace("DZ-", "", $params['billing']['state']);
		}

		// Find wilaya UUID by code
		$wilaya_uuid = null;
		foreach ($wilayas as $wilaya) {
			if ($wilaya['code'] == $wilayaId) {
				$wilaya_uuid = $wilaya['id'];
				$wilaya_name = $wilaya['name'];
				break;
			}
		}

		if ($wilaya_uuid === null) {
			wp_send_json([
				'error' => true,
				'message' => __("Wilaya has not been selected", 'woo-bordereau-generator')
			], 422);
		}

		$communeId = null;

		if (isset($this->data['commune'])) {
			$communes = $this->get_communes($wilayaId, false);
			foreach ($communes as $commune) {
				if ($commune['name'] == $this->data['commune']) {
					$communeId = $commune['uuid'];
					$commune_name = $commune['name'];

					break;
				}
			}
		}

		if (isset($params['billing']['city']) && empty($communeId)) {
			$communes = $this->get_communes($wilayaId, false);
			foreach ($communes as $commune) {
				if ($commune['name'] == $params['billing']['city']) {
					$communeId = $commune['uuid'];
					$commune_name = $commune['name'];
					break;
				}
			}
		}


		if (isset($this->data['products'])) {
			$productString = $this->data['products'];
		} else {
			$products = $params['line_items'];
			$productString = Helpers::generate_products_string($products);
		}

		$productStd = new \stdClass();
		$productStd->unitPrice = 0;
		$productStd->productName = $productString;
		$productStd->stockType = "none";
		$productStd->quantity = 1;
		$products = [
			$productStd
		];

		// "orderedProducts": [
		//        {
		//            "unitPrice": 0,
		//            "quantity": 1,
		//            "productName": "test product",
		//            "stockType": "none"
		//        }
		//    ],
		$i = 0;

//		// Determine which mode to use
//		$advance = get_option($this->provider['slug'] . '_advance');
//		$is_manual_selection = isset($_POST['advance']) && $_POST['advance'] === 'true';
//		$is_local_stock = $this->stock_type === 'local';
//		$is_fulfilled_stock = $this->stock_type === 'fulfilled';
//
//		// MODE 1: Manual Selection from Warehouse (Local Stock with Advance)
//		if ($is_local_stock && $is_manual_selection && isset($_POST['product_id']) && isset($_POST['product_qty'])) {
//			$products[$i]['productId'] = $_POST['product_id'];
//			$products[$i]['quantity'] = ((int) $_POST['product_qty']) == 0 ? 1 : (int) $_POST['product_qty'];
//			$products[$i]['unitPrice'] = isset($_POST['product_price']) ? (float) $_POST['product_price'] : 0;
//			$products[$i]['stockType'] = 'local';
//		}
//		// MODE 2: Auto-Sync from Order (Local Stock without Advance)
//		else if ($is_local_stock) {
//			foreach ($this->formData->get_items() as $item_key => $item) {
//				$item_data = $item->get_data();
//				$product_id = $item->get_product_id();
//				$product_variation_id = $item->get_variation_id();
//				$_id = $product_variation_id ?: $product_id;
//
//				/** @var \WC_Product_Simple $product */
//				$product = wc_get_product($_id);
//
//				$product_tracking_id = get_post_meta( $_id, 'zrexpress_v2_product_id', true );
//
//				// Sync product to warehouse if not already synced
//				if ( empty( $product_tracking_id ) ) {
//
//					$url = $this->provider['api_url'] . "/products";
//
//					$data_product = [
//						'name' => $product->get_name(),
//						'sku' => $product->get_sku(),
//						'basePrice' => (float) $product->get_price(),
//						'purchasePrice' => 0,
//						'length' => (float) ($product->get_length() ?: 0),
//						'width' => (float) ($product->get_width() ?: 0),
//						'height' => (float) ($product->get_height() ?: 0),
//						'weight' => (float) ($product->get_weight() ?: 0),
//						'localStock' => (int) ($product->get_stock_quantity() ?: 0),
//						'categoryId' => $this->category_id,
//						'subCategoryId' => $this->subcategory_id,
//					];
//
//					$request = wp_safe_remote_post(
//						$url,
//						[
//							'headers'     => [
//								'Content-Type' => 'application/json',
//								'X-Api-Key'    => $this->api_key,
//								'X-Tenant'     => $this->tenant_id
//							],
//							'body'        => wp_json_encode( $data_product ),
//							'method'      => 'POST',
//							'data_format' => 'body',
//							'timeout'     => 15
//						]
//					);
//
//					if ( ! is_wp_error( $request ) ) {
//						$body     = wp_remote_retrieve_body( $request );
//						$response = json_decode( $body, true );
//
//						if (isset($response['id'])) {
//							add_post_meta( $_id, 'zrexpress_v2_product_id', $response['id'], true );
//							$product_tracking_id = $response['id'];
//						}
//					}
//				}
//
//				$products[$i]['productId'] = $product_tracking_id;
//				$products[$i]['quantity'] = $item_data['quantity'];
//				$products[$i]['unitPrice'] = (float) ($item_data['total'] / $item_data['quantity']);
//				$products[$i]['productName'] = $product->get_name();
//				$products[$i]['productSku'] = $product->get_sku();
//				$products[$i]['length'] = (float) ($product->get_length() ?: 0);
//				$products[$i]['width'] = (float) ($product->get_width() ?: 0);
//				$products[$i]['height'] = (float) ($product->get_height() ?: 0);
//				$products[$i]['weight'] = (float) ($product->get_weight() ?: 1);
//				$products[$i]['stockType'] = 'local';
//
//				$i++;
//			}
//		}
//		// MODE 3: Fulfilled Stock (Inline Products, No Sync)
//		else if ($is_fulfilled_stock) {
//			foreach ($this->formData->get_items() as $item_key => $item) {
//
//				$item_data = $item->get_data();
//				$product_id = $item->get_product_id();
//				$product_variation_id = $item->get_variation_id();
//				$_id = $product_variation_id ?: $product_id;
//
//				/** @var \WC_Product_Simple $product */
//				$product = wc_get_product($_id);
//
//				// No productId - inline product data
//				$products[$i]['productName'] = $product->get_name();
//				$products[$i]['unitPrice'] = (float) ($item_data['total'] / $item_data['quantity']);
//				$products[$i]['quantity'] = $item_data['quantity'];
//				$products[$i]['stockType'] = 'none';
//				$products[$i]['weight'] = (float) ($product->get_weight() ?: 1);
//				$products[$i]['length'] = (float) ($product->get_length() ?: 0);
//				$products[$i]['width'] = (float) ($product->get_width() ?: 0);
//				$products[$i]['height'] = (float) ($product->get_height() ?: 0);
//
//				$i++;
//			}
//		}

		$phone = $this->data['phone'] ?? $params['billing']['phone'];
		$phone = str_replace(' ', '', str_replace('+213', '0', $phone));

		$phone_2 = isset($this->data['phone_2']) && !empty($this->data['phone_2'])
			? str_replace(' ', '', str_replace('+213', '0', $this->data['phone_2']))
			: '';

		if ($this->data['remarque']) {
			$note = $this->data['remarque'];
		} else {
			$note = stripslashes(get_option('wc_bordreau_default-shipping-note'));
		}

		$free_shipping = 0;
		$stop_desk = 0;


		if ($this->data['free'] == "true") {
			$free_shipping = 1;
		}
		if ($this->formData->has_shipping_method('free_shipping')) {
			$free_shipping = 1;
		}

		if ($this->formData->has_shipping_method('local_pickup') && $this->data['shipping_type'] === 'stopdesk') {
			$stop_desk = 1;
		}

		if ($this->data['shipping_type'] === 'stopdesk') {
			$stop_desk = 1;
		} else {
			$stop_desk = 0;
		}

		// Check if shipping method name matches a center name (like Yalidine)
		// Format: "[prefix] [center_name]" e.g. "livraison au bureau Hub Alger"
		if ($stop_desk && empty($this->data['center'])) {
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
				// Update commune from center's commune_name
				$commune_name = $found['commune_name'];
				// Find commune UUID for the matched commune
				if (!empty($found['wilaya_code'])) {
					$communes = $this->get_communes($found['wilaya_code'], false);
					foreach ($communes as $commune) {
						if ($commune['name'] == $commune_name) {
							$communeId = $commune['uuid'];
							break;
						}
					}
				}
			}
		}

		// Fallback: Auto-select center if not provided (like Yalidine)
		if ($stop_desk && empty($this->data['center'])) {
			$centers = $this->get_all_centers();
			if (is_array($centers) && count($centers) > 0) {
				// Step 1: Try to find a center in the selected commune
				$result = array_filter($centers, function($agency) use ($commune_name) {
					return $agency['commune_name'] == $commune_name;
				});
				$found = !empty($result) ? reset($result) : null;

				// Step 2: If no center in commune, filter by wilaya_code
				if (!$found) {
					$result = array_filter($centers, function($agency) use ($wilayaId) {
						return $agency['wilaya_code'] == $wilayaId;
					});
					$found = !empty($result) ? reset($result) : null;
				}

				// Step 3: Set the center and update commune
				if ($found && isset($found['id'])) {
					$this->data['center'] = $found['id'];
					$commune_name = $found['commune_name'];
					// Find commune UUID
					if (!empty($found['wilaya_code'])) {
						$communes = $this->get_communes($found['wilaya_code'], false);
						foreach ($communes as $commune) {
							if ($commune['name'] == $commune_name) {
								$communeId = $commune['uuid'];
								break;
							}
						}
					}
				}
			}
		}

		$firstName = $this->data['fname'] ?? $params['billing']['first_name'];
		$lastName = $this->data['lname'] ?? $params['billing']['last_name'];

		// For fulfilled stock, create customer first
//		if ($is_fulfilled_stock) {
//			$customer_id = $this->create_or_get_customer(
//				[
//					'name' => $firstName . ' ' . $lastName,
//					'phone' => '+213' . ltrim($phone, '0'),
//					'phone2' => !empty($phone_2) ? '+213' . ltrim($phone_2, '0') : '',
//					'email' => $params['billing']['email'] ?: ''
//				],
//				[
//					'street' => $this->data['address'] ?? $params['billing']['address_1'] ?? '',
//					'city' => $city_name ?? '',
//					'district' => $commune_name ?? '',
//					'postalCode' => '',
//					'cityTerritoryId' => $wilaya_uuid,
//					'districtTerritoryId' => $communeId ?? $wilaya_uuid
//				]
//			);
//
//			// If customer creation failed, return error
//			if (!$customer_id) {
//				wp_send_json([
//					'error' => true,
//					'message' => __("Failed to create customer", 'woo-bordereau-generator')
//				], 422);
//			}
//		} else {
//			$customer_id = $this->formData->get_customer_id() ?: wp_generate_uuid4();
//		}

		$orderId = $this->formData->get_order_number();

		if (isset($this->data['order_number'])) {
			$orderId = $this->data['order_number'];
		}

		$data = [
			// ZR Express 2.0 Payload
			'customer' => [
				'customerId' => wp_generate_uuid4(),
				'name' => $firstName . ' ' . $lastName,
				'phone' => [
					'number1' => '+213' . Helpers::clean_phone_number( $phone ),
					'number2' => !empty($phone_2) ? '+213' . Helpers::clean_phone_number( $phone_2 ) : ''
				],
				'email' => $params['billing']['email'] ?: ''
			],
			'deliveryAddress' => [
				'street' => $this->data['address'] ?? $params['billing']['address_1'] ?? '',
				'city' => $commune_name ?? '',
				'district' => $wilaya_name ?? '',
				'postalCode' => '',
				'country' => 'algeria',
				'cityTerritoryId' => $wilaya_uuid,
				'districtTerritoryId' => $communeId ?? $wilaya_uuid
			],
			'orderedProducts' => $products,
			'amount' => (float) ($this->data['price'] ?? $params['total']),
			'description' => empty($note) ? 'Order #'.$orderId : $note,
			'deliveryType' => $stop_desk ? 'pickup-point' : 'home',
			'externalId' => $orderId,
			'stateId' => $this->default_status
		];

		if($stop_desk) {
			$data['hubId'] = $this->data['center'];
		}



		if (isset($this->data['is_update'])) {

			$method = 'POST';
			// Get parcel ID for update
			wp_send_json([
				'error' => true,
				'message' => __("Cannot Update the Order please delete it and redo it again", 'woo-bordereau-generator')
			], 401);
			exit();

		} else {
			$url = $this->provider['api_url'] . "/parcels";
			$method = 'POST';
		}


		$request = wp_safe_remote_post(
			$url,
			[
				'headers' => [
					'Content-Type' => 'application/json',
					'X-Api-Key'    => $this->api_key,
					'X-Tenant'     => $this->tenant_id
				],
				'body' => wp_json_encode($data),
				'method' => $method,
				'data_format' => 'body',
				'timeout' => 30
			]
		);

		$body = wp_remote_retrieve_body($request);


		$response = json_decode($body, true);


		if (!isset($response['id'])) {
			wp_send_json([
				'error' => true,
				'message' => $response['detail'] ?? __("An error has occurred. Please see log file ", 'woo-bordereau-generator')
			], 400);
		}

		$parcel_id = $response['id'];

		// CRITICAL: Fetch parcel details to get tracking number
		$detail_url = $this->provider['api_url'] . '/parcels/' . $parcel_id;
		$detail_request = wp_safe_remote_get(
			$detail_url,
			[
				'headers' => [
					'Content-Type' => 'application/json',
					'X-Api-Key'    => $this->api_key,
					'X-Tenant'     => $this->tenant_id
				],
				'timeout' => 15
			]
		);

		if (is_wp_error($detail_request)) {
			wp_send_json([
				'error' => true,
				'message' => __("Failed to retrieve tracking number", 'woo-bordereau-generator')
			], 401);
		}

		$detail_body = wp_remote_retrieve_body($detail_request);
		$parcel_details = json_decode($detail_body, true);

		if (!isset($parcel_details['trackingNumber'])) {
			wp_send_json([
				'error' => true,
				'message' => __("Failed to retrieve tracking number", 'woo-bordereau-generator')
			], 401);
		}

		return [
			'id' => $parcel_id,
			'tracking' => $parcel_details['trackingNumber'],
			'parcel' => $parcel_details
		];
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

		update_post_meta($post_id, '_shipping_zrexpress_v2_parcel_id', wc_clean($parcel_id));
		update_post_meta($post_id, '_shipping_tracking_number', wc_clean($trackingNumber));
		update_post_meta($post_id, '_shipping_tracking_label', wc_clean($label));
		update_post_meta($post_id, '_shipping_label', wc_clean($label));
		update_post_meta($post_id, '_shipping_tracking_url', 'https://zrexpress.app/tracking/'. wc_clean($trackingNumber));
		update_post_meta($post_id, '_shipping_tracking_method', $this->provider['slug']);

		if($order) {
			$order->update_meta_data('_shipping_zrexpress_v2_parcel_id', wc_clean($parcel_id));
			$order->update_meta_data('_shipping_tracking_number', wc_clean($trackingNumber));
			$order->update_meta_data('_shipping_tracking_label', wc_clean($label));
			$order->update_meta_data('_shipping_label', wc_clean($label));
			$order->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
			$order->save();

			Helpers::make_note($order, $trackingNumber, $this->provider['name']);
		}

		$track = 'https://zrexpress.app/tracking/'. wc_clean($trackingNumber);

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
		delete_post_meta($post_id, '_shipping_zrexpress_v2_parcel_id');
		delete_post_meta($post_id, '_shipping_tracking_label');
		delete_post_meta($post_id, '_shipping_label');
		delete_post_meta($post_id, '_shipping_tracking_label_method');
		delete_post_meta($post_id, '_shipping_tracking_url');

		$order = wc_get_order($post_id);

		if($order) {
			$order->delete_meta_data('_shipping_tracking_number');
			$order->delete_meta_data('_shipping_zrexpress_v2_parcel_id');
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
	 * This method is a placeholder for detailed tracking functionality.
	 * Currently commented out as implementation may be pending.
	 *
	 * @param string $tracking_number  The tracking number to get details for.
	 * @return void
	 */
	public function track_detail($tracking_number)
	{
		$tracking_id = $this->formData->get_meta('_shipping_zrexpress_v2_parcel_id', true);

		if (empty($tracking_id)) {
			return [];
		}

		$url = $this->provider['api_url'] . "/parcels/" . $tracking_id . "/state-history";
		$request = new ZRExpressTwoRequest($this->provider);
		$content = $request->get($url, false);

		if (is_array($content)) {
			return $this->format_tracking($content);
		}

		return [];
	}


	/**
	 * Get communes (municipalities) for a wilaya (province).
	 *
	 * Retrieves a list of communes for the specified wilaya,
	 * either from cache file or by fetching from the API.
	 *
	 * @param int  $wilaya_id    The wilaya (province) ID.
	 * @param bool $hasStopDesk  Whether stop desk delivery is available.
	 *
	 * @return array  Array of commune data.
	 */
	public function get_communes($wilaya_id, bool $hasStopDesk)
	{
		$wilaya_id = (int) str_replace('DZ-', '', $wilaya_id);

		// Get wilayas to find UUID
		$wilayas = $this->get_wilayas();


		$wilaya_uuid = null;
		foreach ($wilayas as $wilaya) {

			if ($wilaya['code'] == $wilaya_id) {
				$wilaya_uuid = $wilaya['id'];
				break;
			}
		}

		if (!$wilaya_uuid) {
			return [];
		}

		// Get communes data with caching
		$communes_path = Functions::get_path($this->provider['slug'].'_communes_'.$wilaya_uuid.'.json');

		if (!file_exists($communes_path)) {

			$request = new ZRExpressTwoRequest($this->provider);

			$url = $this->provider['api_url'] . '/territories/search';
			$response = $request->post($url, [
				'pageSize' => 1000,
				'pageNumber' => 1,
				"advancedFilter" => [
					"field" => "parentId",
					"operator" =>  "eq",
					"value" => $wilaya_uuid
				]
			]);

			file_put_contents($communes_path, json_encode($response["items"]));
		}

		$communes_data = json_decode(file_get_contents($communes_path), true);


		// If hasStopDesk, filter by pickup point availability
		if ($hasStopDesk) {
			$communes_data = array_filter($communes_data, function($item) {
				return isset($item['delivery']['hasPickupPoint']) && $item['delivery']['hasPickupPoint'] === true;
			});
		}

		// Format the communes
		$communesResult = [];

		foreach ($communes_data as $commune) {

			$has_stop_desk = isset($commune['delivery']['hasPickupPoint']) && $commune['delivery']['hasPickupPoint'];

			$communesResult[] = [
				'id'            => $commune['name'],
				'name'          => $commune['name'],
				'code'          => $commune['code'],
				'uuid'          => $commune['id'],
				'has_stop_desk' => $has_stop_desk,
			];
		}

		return $communesResult;
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

				if (isset($item['newState']['name'])) {
					if ($item['newState']['name'] === 'retournee' || $item['newState']['name'] === 'returned') {
						$result[$key]['failed'] = true;
					}

					if (strpos($item['newState']['name'], 'livree') !== false || strpos($item['newState']['name'], 'delivered') !== false) {
						$result[$key]['delivered'] = true;
					}
				}

				$result[$key]['date'] = $item['createdAt'] ?? '';

				// Use situation name when available (more specific than state)
				$situations = $item['situations'] ?? [];
				if ( ! empty( $situations ) ) {
					$last_situation = end( $situations );
					$result[$key]['status'] = $last_situation['situationName'] ?? $item['newState']['description'] ?? $item['newState']['name'] ?? '';
				} else {
					$result[$key]['status'] = $item['newState']['description'] ?? $item['newState']['name'] ?? '';
				}

				if (isset($item['newState']['color'])) {
					$result[$key]['color'] = '#' . $item['newState']['color'];
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
	 * @param array $item  A tracking response item.
	 * @return array  Formatted tracking details.
	 */
	private function format_tracking_detail(array $item)
	{
		$detail = [];

		$detail[0]['address'] = $item['deliveryAddress']['street'] ?? '';
		$detail[0]['phone'] = $item['customer']['phone']['number1'] ?? '';
		$detail[0]['fname'] = explode(' ', $item['customer']['name'] ?? '')[0] ?? '';
		$detail[0]['lname'] = explode(' ', $item['customer']['name'] ?? '', 2)[1] ?? '';
		$detail[0]['free'] = false; // ZR Express doesn't expose this in API
		$detail[0]['exchange'] = $item['isExchanged'] ?? false;
		$detail[0]['stopdesk'] = $item['deliveryType'] === 'hub';
		// Prefer situation over state when available (more specific)
		if ( ! empty( $item['situation']['name'] ) ) {
			$detail[0]['last_status'] = $item['situation']['description'] ?? $item['situation']['name'];
			$detail[0]['situation'] = $item['situation']['name'];
		} else {
			$detail[0]['last_status'] = $item['state']['description'] ?? '';
		}
		$detail[0]['price'] = $item['amount'] ?? 0;
		$detail[0]['commune'] = $item['deliveryAddress']['district'] ?? '';
		$detail[0]['wilaya'] = $item['deliveryAddress']['city'] ?? '';
		$detail[0]['product_id'] = isset($item['orderedProducts']) && count($item['orderedProducts']) ? $item['orderedProducts'][0]['productId'] : null;
		$detail[0]['product_qty'] = isset($item['orderedProducts']) && count($item['orderedProducts']) ? $item['orderedProducts'][0]['quantity'] : null;
		$detail[0]['product_name'] = isset($item['orderedProducts']) && count($item['orderedProducts']) ? $item['orderedProducts'][0]['productName'] : null;

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
	 * Retrieves a list of wilayas from cache or from the API,
	 * storing the results for future use.
	 *
	 * @return array  Array of wilaya data.
	 */
	public function get_wilayas(): array
	{
		$path = Functions::get_path($this->provider['slug'] .'_wilayas.json');

		if (!file_exists($path)) {

			$request = new \WooBordereauGenerator\Admin\Shipping\ZRExpressTwoRequest($this->provider);
			$content = $request->post($this->provider['api_url'] . '/territories/search', [
				'pageSize' => 100,
				'pageNumber' => 1,
				"advancedFilter" => [
					"field" => "level",
					"operator" => "eq",
					"value"=> "wilaya"
				],
				'orderBy' => [
					'code'
				]
			]);


			file_put_contents($path, json_encode($content['items']));
		}

		return json_decode(file_get_contents($path), true);
	}

	/**
	 * Get wilayas from the provider.
	 *
	 * Internal method to fetch wilaya data directly from the API provider.
	 *
	 * @return array  Array of wilaya data.
	 */
	private function get_wilaya_from_provider(): array
	{
		return $this->get_wilayas();
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
		$url = $this->provider['api_url'] . '/parcels/'. $tracking_number;
		$request = new \WooBordereauGenerator\Admin\Shipping\ZRExpressTwoRequest($this->provider);

		$content = $request->get($url, false);

		$details = $this->format_tracking_detail($content);

		if (isset($details[0]['last_status'])) {
			// Prefer situation name for status update (more specific than state)
			$tracking_status = $details[0]['situation'] ?? $details[0]['last_status'];
			BordereauGeneratorAdmin::update_tracking_status($this->formData, $tracking_status, 'single_tracking');
		}

		wp_send_json([
			'tracking' => $tracking_number,
			'detail' => $details
		]);
	}


	/**
	 * Get wilayas by provider.
	 *
	 * Retrieves wilayas (provinces) for a specific provider, handling
	 * caching and error scenarios.
	 *
	 * @param array $provider  The provider configuration.
	 * @return array  Array of formatted wilaya data.
	 */
	public function get_wilayas_by_provider($provider): array
	{
		$path = Functions::get_path($this->provider['slug']. '_wilayas.json');

		if (! file_exists($path)) {
			$content = $this->get_wilayas();

			file_put_contents($path, json_encode($content));
		}

		$wilayasResult = [];

		if (file_exists($path)) {
			$result = json_decode(file_get_contents($path), true);

			if ($result == null || count($result) == 0) {
				$result = $this->get_wilaya_from_provider();
			}
		} else {
			$response_array = $this->get_wilaya_from_provider();

			if (isset($response_array['error'])) {
				wp_send_json([
					'error' => true,
					'message' => $response_array['error']['message']
				], 401);
			} else {
				file_put_contents($path, json_encode($response_array));
				$result = $response_array;
			}
		}

		if (isset($result)) {

			foreach ($result as $i => $wilaya) {
				$wilayasResult[$i]['name'] = $wilaya['name'];
				$wilayasResult[$i]['id'] = $wilaya['postalCode'];
				$wilayasResult[$i]['uuid'] = $wilaya['id'];
			}

			return $wilayasResult;
		}

		return $wilayasResult;
	}


	/**
	 * Delete a shipment.
	 *
	 * Cancels a shipment with ZR Express and removes all related
	 * shipping metadata from the order.
	 *
	 * @param string $tracking_number  The tracking number to delete.
	 * @param int    $post_id          The order ID.
	 * @return null
	 * @since 1.3.0
	 */
	public function delete($tracking_number, $post_id)
	{
		// IMPORTANT: ZR Express DELETE uses parcel ID, not tracking number
		$parcel_id = get_post_meta($post_id, '_shipping_zrexpress_v2_parcel_id', true);

		if ($parcel_id)
		{
			$url = $this->provider['api_url'] . "/parcels/" . $parcel_id;

			$request = new \WooBordereauGenerator\Admin\Shipping\ZRExpressTwoRequest($this->provider);
			$request->delete($url);
		}

		$this->reset_meta($post_id);

		return null;
	}


	/**
	 * Get the shipping label.
	 *
	 * Retrieves the shipping label URL from ZR Express API and
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

		$data = [
			'trackingNumbers' => [$tracking],
		];

		$url = $this->provider['api_url'] . "/parcels/labels/individual";

		$request = wp_safe_remote_post(
			$url,
			[
				'headers'     => [
					'Content-Type' => 'application/json',
					'X-Api-Key'    => $this->api_key,
					'X-Tenant'     => $this->tenant_id
				],
				'body'        => wp_json_encode( $data ),
				'method'      => 'POST',
				'data_format' => 'body',
				'timeout'     => 15
			]
		);

		if ( ! is_wp_error( $request ) ) {

			$body     = wp_remote_retrieve_body( $request );

			$response = json_decode( $body, true );

			if (isset($response['parcelLabelFiles'][0]['fileUrl'])) {
				header('Location: '. $response['parcelLabelFiles'][0]['fileUrl']);
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

		$tracking_id = $this->formData->get_meta('_shipping_zrexpress_v2_parcel_id', true);

		$url = $this->provider['api_url'] . "/parcels/".$tracking_id."/state-history";
		$request = new \WooBordereauGenerator\Admin\Shipping\ZRExpressTwoRequest($this->provider);

		$content = $request->get($url, false);

		if (is_array($content)) {
			$events = $this->format_tracking($content);
			wp_send_json([
				'tracking' => $tracking_number,
				'events' => $events
			]);
		}

		wp_send_json([
			'tracking' => $tracking_number,
			'events' => []
		]);
	}

	/**
	 * Get centers (hubs) for a commune
	 *
	 * @param $commune
	 * @return array
	 */
	public function get_centers($commune): array
	{
		// Create cache path using date-based versioning to refresh daily
		$centers_path = Functions::get_path($this->provider['slug'].'_centers_'.WC_BORDEREAU_GENERATOR_VERSION.'_'.date('Y').'_'. (date('z') + 1).'.json');

		if (!file_exists($centers_path)) {

			$url = $this->provider['api_url'] . '/hubs/search';
			$request = new ZRExpressTwoRequest($this->provider);
			$hubs = $request->post($url, [
				'pageSize' => 1000,
				'pageNumber' => 1,
				'includeServices' => true
			]);

			file_put_contents($centers_path, json_encode($hubs));
		}

		$hubs_data = json_decode(file_get_contents($centers_path), true);

		$wilayaCode = (int) str_replace('DZ-','', $_POST['wilaya']);


		// Filter centers by commune (district)
		$filtered_centers = array_filter($hubs_data['items'] ?? [], function ($item) use ($wilayaCode) {
			$code = substr($item['address']['postalCode'], 0, 2);
			return (int) $code == (int) $wilayaCode;
		});

		// Format the results
		$centersResult = [];
		foreach ($filtered_centers as $i => $item) {
			$centersResult[] = [
				'id' => $item['id'],
				'name' => $item['name'],
				'commune_name' => $item['address']['district'] ?? '',
				'wilaya_name' => $item['address']['city'] ?? ''
			];
		}

		return $centersResult;
	}

	/**
	 * Get all centers (hubs) from the API with caching
	 *
	 * @return array Array of all pickup point centers
	 */
	public function get_all_centers(): array
	{
		$cache_file = Functions::get_path($this->provider['slug'] . '_all_centers_' . WC_BORDEREAU_GENERATOR_VERSION . '_' . date('Y') . '_' . (date('z') + 1) . '.json');

		if (file_exists($cache_file)) {
			$cached_centers = json_decode(file_get_contents($cache_file), true);
			if ($cached_centers) {
				return $cached_centers;
			}
		}

		// Fetch from API
		$url = $this->provider['api_url'] . '/hubs/search';
		$request = new ZRExpressTwoRequest($this->provider);
		$hubs = $request->post($url, [
			'pageSize' => 1000,
			'pageNumber' => 1,
			'includeServices' => true
		]);

		// Get wilayas to map territory IDs
		$wilayas = $this->get_wilayas();
		$territory_to_wilaya = [];
		$wilaya_code_to_name = [];
		foreach ($wilayas as $wilaya) {
			$territory_to_wilaya[$wilaya['id']] = $wilaya['code'];
			$wilaya_code_to_name[$wilaya['code']] = $wilaya['name'];
		}

		$all_centers = [];
		foreach ($hubs['items'] ?? [] as $item) {
			if (!isset($item['isPickupPoint']) || !$item['isPickupPoint']) {
				continue;
			}

			$city_territory_id = $item['address']['cityTerritoryId'] ?? null;
			$wilaya_code = $territory_to_wilaya[$city_territory_id] ?? null;
			$wilaya_name = $wilaya_code ? ($wilaya_code_to_name[$wilaya_code] ?? '') : '';

			$all_centers[] = [
				'id'           => $item['id'] ?? '',
				'name'         => $item['name'] ?? '',
				'commune_name' => $item['address']['district'] ?? '',
				'wilaya_name'  => $wilaya_name,
				'wilaya_code'  => $wilaya_code
			];
		}

		file_put_contents($cache_file, json_encode($all_centers));
		return $all_centers;
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
