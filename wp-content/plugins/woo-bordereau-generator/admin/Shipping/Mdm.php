<?php

/**
 * MDM Express shipping provider integration for WooCommerce Bordereau Generator.
 * 
 * This class handles the integration with MDM Express shipping services,
 * providing functionality for generating shipping labels, tracking parcels,
 * managing shipping data, and interacting with the MDM Express API.
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

/**
 * MDM Express shipping provider implementation.
 * 
 * Extends the BordereauProvider class and implements BordereauProviderInterface
 * to provide MDM Express-specific shipping functionality for the WooCommerce
 * Bordereau Generator plugin.
 */
class Mdm extends \WooBordereauGenerator\Admin\Shipping\BordereauProvider implements \WooBordereauGenerator\Admin\Shipping\BordereauProviderInterface
{

    /**
     * The MDM Express API key.
     * 
     * @var string
     */
    private string $api_key;


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
     * The MDM Express store ID.
     * 
     * @var string|null
     */
    private $store_id;
	private bool $is_fulfilement;
	private bool $confirmation;

	/**
	 * Default MDM product category ID.
	 */
	private const DEFAULT_CATEGORY_ID = '6571f847a1672bea6ea84b49';

	/**
     * Constructor.
     * 
     * Initializes a new MDM Express provider instance with order data,
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
	    $this->store_id = get_option( $provider['slug'] . '_store_id' );
	    $this->is_fulfilement = get_option( $provider['slug'] . '_fulfilled' ) === 'yes';
	    $this->confirmation = get_option( $provider['slug'] . '_confirmation' ) === 'with-confirmation';
	    $this->provider = $provider;
        $this->data = $data;

		// Auto-detect store ID if a seller ID was entered by mistake
		if ( ! empty( $this->api_key ) && ( empty( $this->store_id ) || str_starts_with( $this->store_id, 'SLR-' ) ) ) {
			$url     = $provider['api_url'] . '/api/stores';
			$request = new MdmRequest( $provider );
			$result  = $request->get( $url );

			if ( ! empty( $result['list'] ) ) {
				foreach ( $result['list'] as $store ) {
					if ( isset( $store['country']['id'] ) && $store['country']['id'] === 'DZA' ) {
						$this->store_id = $store['trackingId'];
						update_option( $provider['slug'] . '_store_id', $this->store_id );
						break;
					}
				}
			}
		}
    }

	/**
	 * Get the MDM category ID from settings or use default.
	 *
	 * @return string
	 */
	private function get_category_id(): string
	{
		$category_id = get_option( $this->provider['slug'] . '_category_id' );
		return ! empty( $category_id ) ? $category_id : self::DEFAULT_CATEGORY_ID;
	}

	/**
	 * Map WooCommerce product dimensions to MDM size values.
	 * MDM accepts: small, medium, big, giant
	 *
	 * @param WC_Product $product
	 * @return string
	 */
	private function get_product_size( $product ): string
	{
		$length = (float) $product->get_length();
		$width  = (float) $product->get_width();
		$height = (float) $product->get_height();
		$max_dim = max( $length, $width, $height );

		if ( $max_dim <= 0 || $max_dim <= 30 ) {
			return 'small';
		} elseif ( $max_dim <= 60 ) {
			return 'medium';
		} elseif ( $max_dim <= 120 ) {
			return 'big';
		}
		return 'giant';
	}

	/**
	 * Create a product in MDM Express.
	 *
	 * @param WC_Product $product  The WooCommerce product.
	 * @return string|false  The MDM product trackingId or false on failure.
	 */
	private function create_mdm_product( $product )
	{
		$url = $this->provider['api_url'] . "/api/v2/products";

		$data = [
			'storeId'                => $this->store_id,
			'name'                   => $product->get_name(),
			'description'            => $product->get_name(),
			'categoryId'             => $this->get_category_id(),
			'isFulfilledByUs'        => $this->is_fulfilement,
			'isLeadBecomeOutOfStock'  => false,
			'pricing[selling]'       => $product->get_price(),
			'pricing[purchasing]'    => 0,
			'sku'                    => $product->get_sku() ?: 'WC-' . $product->get_id(),
			'creativeLink'           => '',
			'weight'                 => (int) ( $product->get_weight() ?: 1 ),
			'size'                   => $this->get_product_size( $product ),
			'landingPageLink'        => $product->get_permalink(),
		];

		$curl = curl_init();
		curl_setopt_array( $curl, [
			CURLOPT_URL            => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING       => '',
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST  => 'POST',
			CURLOPT_POSTFIELDS     => $data,
			CURLOPT_HTTPHEADER     => [
				'x-api-key: ' . $this->api_key,
			],
		]);

		$response = curl_exec( $curl );
		curl_close( $curl );

		$response = json_decode( $response, true );

		if ( isset( $response['trackingId'] ) ) {
			return $response['trackingId'];
		}

		error_log( 'MDM product creation failed for: ' . $product->get_name() . ' | Response: ' . wp_json_encode( $response ) );

		return false;
	}

	/**
	 * Create a product variant in MDM Express.
	 *
	 * @param string     $parent_tracking_id  The parent product trackingId.
	 * @param WC_Product $variation           The WooCommerce variation product.
	 * @return string|false  The MDM variant trackingId or false on failure.
	 */
	private function create_mdm_variant( string $parent_tracking_id, $variation )
	{
		$data = [
			'name'    => $variation->get_name(),
			'pricing' => [
				'selling'    => (float) $variation->get_price(),
				'purchasing' => 0,
			],
			'sku'    => $variation->get_sku() ?: 'WC-VAR-' . $variation->get_id(),
			'weight' => (int) ( $variation->get_weight() ?: 0 ),
		];

		$url = $this->provider['api_url'] . sprintf( "/api/v2/products/%s/variants", $parent_tracking_id );

		$request = wp_safe_remote_post(
			$url,
			[
				'headers'     => [
					'Content-Type' => 'application/json',
					'x-api-key'    => $this->api_key,
				],
				'body'        => wp_json_encode( $data ),
				'method'      => 'POST',
				'data_format' => 'body',
				'timeout'     => 15,
			]
		);

		if ( ! is_wp_error( $request ) ) {
			$body     = wp_remote_retrieve_body( $request );
			$response = json_decode( $body, true );

			if ( isset( $response['trackingId'] ) ) {
				return $response['trackingId'];
			}
		}

		return false;
	}

	/**
	 * Search for an existing product in MDM by trackingId or SKU.
	 *
	 * @param string $search  The search term (trackingId or SKU).
	 * @return string|false  The MDM product trackingId or false if not found.
	 */
	private function search_mdm_product( string $search )
	{
		$params_search = [
			'sortBy'     => [ 'createdAt' => 'DESC' ],
			'filters'    => [
				'countryId' => ['DZA'],
				'storeId'   => [ $this->store_id ],
				'search'    => $search,
			],
			'pagination' => [ 'page' => 1 ],
		];

		$url = $this->provider['api_url'] . '/api/v2/products/search';

		$request = wp_safe_remote_post(
			$url,
			[
				'headers'     => [
					'Content-Type' => 'application/json',
					'x-api-key'    => $this->api_key,
				],
				'body'        => wp_json_encode( $params_search ),
				'method'      => 'POST',
				'data_format' => 'body',
				'timeout'     => 15,
			]
		);

		if ( ! is_wp_error( $request ) ) {
			$body     = wp_remote_retrieve_body( $request );
			$response = json_decode( $body, true );

			if ( ! empty( $response['list'] ) ) {
				$item = reset( $response['list'] );
				if ( $item && isset( $item['trackingId'] ) ) {
					return $item['trackingId'];
				}
			}
		}

		return false;
	}

    /**
     * Generate the tracking code.
     * 
     * Creates a shipping parcel and tracking number for the order by
     * formatting order data and sending it to the MDM Express API.
     * 
     * @return mixed|WP_Error Returns the API response or WP_Error on failure.
     * @throws Exception If an error occurs during the API request.
     * @since 1.0.0
     */
	public function generate()
	{
		$params = $this->formData->get_data();

		$orderId = $this->formData->get_id();

		$wilayas = $this->get_wilayas();

		if (isset($this->data['wilaya'])) {
			$wilayaId = (int)str_replace("DZ-", "", $this->data['wilaya']);
			$wilaya = array_search($wilayaId, array_column($wilayas, 'wilaya_id'));
		} else {
			$wilayaId = (int)str_replace("DZ-", "", $params['billing']['state']);
			$wilaya = array_search($wilayaId, array_column($wilayas, 'wilaya_id'));
		}

		if ($wilaya === null) {
			wp_send_json([
				'error' => true,
				'message' => __("Wilaya has not been selected", 'woo-bordereau-generator')
			], 422);
		}

		// Resolve commune name to MDM city ID (e.g. "Alger Centre" → "DZA161601")
		$communeId = '';
		if (isset($this->data['commune'])) {
			$communes = $this->get_communes($wilayaId, false);
			$key = array_search($this->data['commune'], array_column($communes, "name"));

			if ($key !== false) {
				$communeId = $communes[$key]['code'];
			}
		}

		if (isset($params['billing']['city']) && empty($communeId)) {
			$commnes = $this->get_communes($wilayaId, false);
			$key = array_search($params['billing']['city'], array_column($commnes, "name"));
			if ($key !== false) {
				$communeId = $commnes[$key]['code'];
			}
		}

		$products = [];
		$i = 0;

		if(isset($_POST['advance']) && isset($_POST['product_id']) && isset($_POST['product_qty']) && $_POST['product_price']) {
			$products[$i]['trackingId'] = $_POST['product_id'];
			$products[$i]['quantity'] = ((int) $_POST['product_qty']) == 0 ? 1 : (int) $_POST['product_qty'];
			$products[$i]['price'] = (int) $_POST['product_price'];
		} else {
			// Create or find products in MDM for each order item
			foreach ($this->formData->get_items() as $item_key => $item) {

				$item_data = $item->get_data();
				$product_id = $item->get_product_id();
				$product_variation_id = $item->get_variation_id();
				$_id = $product_id;

				/** @var \WC_Product_Simple $product */
				$product = wc_get_product($_id);

				$product_tracking_id = get_post_meta( $_id, '_mdm_product_id', true );

				if ( empty( $product_tracking_id ) ) {
					// Try to find existing product in MDM by SKU first
					$sku = $product->get_sku();
					if ( ! empty( $sku ) ) {
						$product_tracking_id = $this->search_mdm_product( $sku );
						if ( $product_tracking_id ) {
							update_post_meta( $product->get_id(), '_mdm_product_id', $product_tracking_id );
						}
					}

					// If still not found, create it in MDM
					if ( empty( $product_tracking_id ) ) {
						$product_tracking_id = $this->create_mdm_product( $product );
						if ( $product_tracking_id ) {
							add_post_meta( $product->get_id(), '_mdm_product_id', $product_tracking_id, true );
						}
					}
				} else {
					// Verify the stored trackingId still exists in MDM
					$found = $this->search_mdm_product( $product_tracking_id );
					if ( ! $found ) {
						// Product was deleted from MDM, try by SKU
						$sku = $product->get_sku();
						if ( ! empty( $sku ) ) {
							$found = $this->search_mdm_product( $sku );
						}
						if ( $found ) {
							$product_tracking_id = $found;
							update_post_meta( $product->get_id(), '_mdm_product_id', $product_tracking_id );
						} else {
							// Recreate the product in MDM
							delete_post_meta( $product->get_id(), '_mdm_product_id' );
							$product_tracking_id = $this->create_mdm_product( $product );
							if ( $product_tracking_id ) {
								add_post_meta( $product->get_id(), '_mdm_product_id', $product_tracking_id, true );
							}
						}
					}
				}

				// Handle variations
				if ($product_variation_id && ! empty( $product_tracking_id ) ) {

					$variation_tracking_id = get_post_meta( $product_variation_id, '_mdm_variant_id', true );

					/** @var \WC_Product_Variation $variation */
					$variation = wc_get_product( $product_variation_id );

					if ( empty($variation_tracking_id) ) {
						$variation_tracking_id = $this->create_mdm_variant( $product_tracking_id, $variation );
						if ( $variation_tracking_id ) {
							add_post_meta( $product_variation_id, '_mdm_variant_id', $variation_tracking_id, true );
						}
					}
				}

				// Bail out if we couldn't get a valid product trackingId
				if ( empty( $product_tracking_id ) ) {
					wp_send_json([
						'error'   => true,
						'message' => sprintf(
							__( 'Failed to create product "%s" (ID: %d) in MDM Express. Please check your API key and store settings.', 'woo-bordereau-generator' ),
							$product->get_name(),
							$product->get_id()
						),
					], 422);
				}

				$products[$i]['trackingId'] = (string) $product_tracking_id;
				$products[$i]['quantity'] = $item_data['quantity'];
				$products[$i]['price'] = (int)$item_data['total'];
				if (!empty($product_variation_id) && !empty($variation_tracking_id)) {
					$products[$i]['variantId'] = (string) $variation_tracking_id;
				}

				$i++;
			}
		}

		$phone = $this->data['phone'] ?? $params['billing']['phone'];
		$phone = str_replace(' ', '', str_replace('+213', '0', $phone));

		$phone2 = '';
		if (isset($this->data['phone2'])) {
			$phone2 = str_replace(' ', '', str_replace('+213', '0', $this->data['phone2']));
		}

		if (!empty($this->data['remarque'])) {
			$note = $this->data['remarque'];
		} else {
			$note = stripslashes(get_option('wc_bordreau_default-shipping-note'));
		}

		$free_shipping = false;
		$stop_desk = false;
		$stop_desk_id = '';

		if ($this->data['free'] == "true") {
			$free_shipping = true;
		}
		if ($this->formData->has_shipping_method('free_shipping')) {
			$free_shipping = true;
		}

		if ($this->formData->has_shipping_method('local_pickup') && $this->data['shipping_type'] === 'stopdesk' || $this->formData->has_shipping_method('local_pickup_yalidine')) {
			$stop_desk = true;
		}

		if ($this->data['shipping_type'] === 'stopdesk') {
			$stop_desk = true;
		} else {
			$stop_desk = false;
		}

		if (isset($this->data['center']) && !empty($this->data['center'])) {
			$stop_desk_id = $this->data['center'];
		}

		// MDM Orders API Payload
		// Uses orders endpoint with confirmed=true, which auto-creates a parcel.
		// Orders endpoint uses: totalPrice (not productsPrice), freeShipping (not isShippingFree)
		$data = [
			'storeId' => $this->store_id,
			'paymentMethod' => 'cash',
			'client' => [
				'firstName' => $this->data['fname'],
				'lastName' => $this->data['lname'],
				'phone' => $phone,
				'phone2' => $phone2,
			],
			'products' => $products,
			'totalPrice' => (int) ($this->data['price'] ?? $params['total']),
			'freeShipping' => $free_shipping,
			'isStopDesk' => $stop_desk,
			'destination' => [
				'cityId' => $communeId,
				'streetAddress' => $this->data['address'] ?? $params['billing']['address_1'] ?? stripslashes($params['billing']['city']),
			],
			'notes' => $note,
			'confirmed' => !$this->confirmation,
			'confirmedByUs' => !$this->confirmation,
			'fragile' => isset($this->data['fragile']) ? (bool) $this->data['fragile'] : false,
			'openable' => isset($this->data['openable']) ? (bool) $this->data['openable'] : false,
			'weight' => isset($this->data['weight']) ? (int) $this->data['weight'] : 0,
		];

		if ($stop_desk && !empty($stop_desk_id)) {
			$data['stopDeskId'] = $stop_desk_id;
		}

		if (isset($this->data['is_update'])) {
			// Updates go to the parcels endpoint (orders don't support PATCH the same way)
			// Convert orders-style fields to parcels-style fields
			$url = $this->provider['api_url'] . "/api/v2/shipping/parcels/" . $this->data['tracking_number'];
			$method = 'PATCH';

			// Parcels endpoint uses different field names than orders endpoint
			if (isset($data['totalPrice'])) {
				$data['productsPrice'] = $data['totalPrice'];
				unset($data['totalPrice']);
			}
			if (isset($data['freeShipping'])) {
				$data['isShippingFree'] = $data['freeShipping'];
				unset($data['freeShipping']);
			}
			// Parcels endpoint doesn't need these order-specific fields
			unset($data['storeId'], $data['paymentMethod'], $data['confirmed'], $data['confirmedByUs']);
		} else {
			$url = $this->provider['api_url'] . "/api/v2/orders";
			$method = 'POST';
		}

		$request = wp_safe_remote_post(
			$url,
			[
				'headers' => [
					'Content-Type' => 'application/json',
					'x-api-key'    => $this->api_key
				],
				'body' => wp_json_encode($data),
				'method' => $method,
				'data_format' => 'body',
				'timeout' => 30,
			]
		);

		$body = wp_remote_retrieve_body($request);
		$response = json_decode($body, true);

		// For new orders, fetch the parcel ID from the order detail
		if ( ! isset($this->data['is_update']) && isset($response['trackingId']) && strpos($response['trackingId'], 'ORD-') === 0 ) {
			$mdmOrderId = $response['trackingId'];

			// Store the MDM order ID for reference
			update_post_meta($orderId, '_mdm_order_id', $mdmOrderId);

			// Fetch order detail to get the linked parcel trackingId
			$order_request = new MdmRequest($this->provider);
			$order_detail  = $order_request->get(
				$this->provider['api_url'] . '/api/v2/orders/' . $mdmOrderId,
				false
			);

			if ( is_array($order_detail) && ! empty($order_detail['relatives']) ) {
				foreach ( $order_detail['relatives'] as $relative ) {
					if ( $relative['type'] === 'parcel' && ! empty($relative['data']['trackingId']) ) {
						$response['trackingId'] = $relative['data']['trackingId'];
						break;
					}
				}
			}
		}

		// Handle PRODUCT_NOT_FOUND by re-creating the product/variant and retrying
		if (
			isset($response['error']['code']) &&
			$response['error']['code'] === 'PARCELS.PRODUCT_NOT_FOUND' &&
			!isset($this->data['_retry_attempt'])
		) {
			$failed_tracking_id = null;

			// Extract the failed tracking ID from the error message
			if (preg_match('/product\s+(\S+)\s+not found/i', $response['error']['message'], $matches)) {
				$failed_tracking_id = trim($matches[1]);
			}

			if ($failed_tracking_id) {
				// Find the WooCommerce product/variation that has this tracking ID and recreate it
				foreach ($this->formData->get_items() as $item_key => $item) {
					$product_id = $item->get_product_id();
					$product_variation_id = $item->get_variation_id();

					$stored_product_tracking = get_post_meta($product_id, '_mdm_product_id', true);
					$stored_variant_tracking = $product_variation_id ? get_post_meta($product_variation_id, '_mdm_variant_id', true) : '';

					$is_variant_issue = ($stored_variant_tracking === $failed_tracking_id);
					$is_product_issue = ($stored_product_tracking === $failed_tracking_id);

					if (!$is_variant_issue && !$is_product_issue) {
						continue;
					}

					/** @var \WC_Product_Simple $product */
					$product = wc_get_product($product_id);

					if ($is_product_issue || $is_variant_issue) {
						delete_post_meta($product_id, '_mdm_product_id');

						$new_product_tracking_id = $this->create_mdm_product( $product );

						if ( ! $new_product_tracking_id ) {
							break;
						}

						add_post_meta($product_id, '_mdm_product_id', $new_product_tracking_id, true);

						foreach ($products as &$p) {
							if (isset($p['trackingId']) && $p['trackingId'] === $failed_tracking_id && !$is_variant_issue) {
								$p['trackingId'] = $new_product_tracking_id;
							}
							if (isset($p['trackingId']) && $p['trackingId'] === $stored_product_tracking) {
								$p['trackingId'] = $new_product_tracking_id;
							}
						}
						unset($p);
					}

					if ($is_variant_issue && $product_variation_id) {
						delete_post_meta($product_variation_id, '_mdm_variant_id');

						$new_product_tracking_id = get_post_meta($product_id, '_mdm_product_id', true);

						/** @var \WC_Product_Variation $variation */
						$variation = wc_get_product($product_variation_id);

						$new_variant_id = $this->create_mdm_variant( $new_product_tracking_id, $variation );
						if ( $new_variant_id ) {
							add_post_meta($product_variation_id, '_mdm_variant_id', $new_variant_id, true);

							foreach ($products as &$p) {
								if (isset($p['variantId']) && $p['variantId'] === $failed_tracking_id) {
									$p['variantId'] = $new_variant_id;
								}
							}
							unset($p);
						}
					}

					break; // We found and handled the problematic product
				}

				// Rebuild the order data with updated products and retry
				$data['products'] = $products;
				$this->data['_retry_attempt'] = true;

				$retry_request = wp_safe_remote_post(
					$url,
					[
						'headers'     => [
							'Content-Type' => 'application/json',
							'x-api-key'    => $this->api_key,
						],
						'body'        => wp_json_encode($data),
						'method'      => $method,
						'data_format' => 'body',
						'timeout'     => 30,
					]
				);

				$body = wp_remote_retrieve_body($retry_request);
				$response = json_decode($body, true);

				// Resolve parcel ID from retried order response
				if ( ! isset($this->data['is_update']) && isset($response['trackingId']) && strpos($response['trackingId'], 'ORD-') === 0 ) {
					$retryOrderId = $response['trackingId'];
					update_post_meta($orderId, '_mdm_order_id', $retryOrderId);

					$retry_order_request = new MdmRequest($this->provider);
					$retry_order_detail  = $retry_order_request->get(
						$this->provider['api_url'] . '/api/v2/orders/' . $retryOrderId,
						false
					);

					if ( is_array($retry_order_detail) && ! empty($retry_order_detail['relatives']) ) {
						foreach ( $retry_order_detail['relatives'] as $relative ) {
							if ( $relative['type'] === 'parcel' && ! empty($relative['data']['trackingId']) ) {
								$response['trackingId'] = $relative['data']['trackingId'];
								break;
							}
						}
					}
				}
			}
		}

		// --- Standard error handling below ---

		if (!empty($response['error'])) {
			wp_send_json([
				'error'   => true,
				'message' => $response['error']['message'] ?? __("An error has occurred. Please see log file ", 'woo-bordereau-generator'),
			], $response['status'] ?? 400);
		}

		if (isset($response['status_code']) && $response['status_code'] == 400) {
			wp_send_json([
				'error'   => true,
				'message' => $response[array_key_first($response)] ?? __("An error has occurred. Please see log file ", 'woo-bordereau-generator'),
			], $response['status_code']);
		}

		if (empty($response) || !is_array($response)) {
			wp_send_json([
				'error'   => true,
				'message' => __("An error has occurred. Please see log file ", 'woo-bordereau-generator'),
			], 401);
		}

		if (isset($response['error'])) {
			wp_send_json([
				'error'   => true,
				'message' => __("An error has occurred. Please see log file ", 'woo-bordereau-generator'),
			], 401);
		}

		if (isset($this->data['is_update'])) {
			$response['tracking'] = $this->data['tracking_number'];
		}

		return $response;
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

        $trackingNumber = $response['trackingId'];

	    $label = get_rest_url(null, 'woo-bordereau/v1/slip/print/'.$this->provider['slug'].'/'.$post_id);

        update_post_meta($post_id, '_shipping_tracking_number', wc_clean($trackingNumber));
        update_post_meta($post_id, '_shipping_tracking_label', wc_clean($label));
        update_post_meta($post_id, '_shipping_label', wc_clean($label));
        update_post_meta($post_id, '_shipping_tracking_url', 'https://app.mdm.express/en/track/' . wc_clean($trackingNumber));
        update_post_meta($post_id, '_shipping_tracking_method', $this->provider['slug']);

        if($order) {
            $order->update_meta_data('_shipping_tracking_number', wc_clean($trackingNumber));
            $order->update_meta_data('_shipping_tracking_label', wc_clean($label));
            $order->update_meta_data('_shipping_label', wc_clean($label));
            $order->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
            $order->save();

            Helpers::make_note($order, $trackingNumber, $this->provider['name']);
        }

        $track = 'https://app.mdm.express/en/track/' . wc_clean($trackingNumber);

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
     * Remove shipping metadata from an order.
     * 
     * Deletes all shipping-related metadata from both postmeta and
     * order meta tables for the specified order.
     * 
     * @param int $post_id  The order ID.
     */
    private function reset_meta($post_id) {

        delete_post_meta($post_id, '_shipping_tracking_number');
        delete_post_meta($post_id, '_shipping_mdm_parcel_id');
        delete_post_meta($post_id, '_shipping_tracking_label');
        delete_post_meta($post_id, '_shipping_label');
        delete_post_meta($post_id, '_shipping_tracking_label_method');
        delete_post_meta($post_id, '_shipping_tracking_url');
        delete_post_meta($post_id, '_mdm_order_id');

        $order = wc_get_order($post_id);

        if($order) {
            $order->delete_meta_data('_shipping_tracking_number');
            $order->delete_meta_data('_shipping_tracking_label');
            $order->delete_meta_data('_shipping_label');
            $order->delete_meta_data('_shipping_tracking_label_method');
            $order->delete_meta_data('_shipping_tracking_method');
            $order->delete_meta_data('_mdm_order_id');
            $order->save();
        }
    }

    /**
     * Get detailed tracking information.
     * 
     * This method is a placeholder for detailed tracking functionality.
     *
     * @param string $tracking_number  The tracking number to get details for.
     * @return void
     */
    public function track_detail($tracking_number)
    {

//        $url = $this->provider['api_url'] . "/api/v2/shipping/parcels/$tracking_number/status-history";
//
//        return $this->format_tracking($response_array);
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
		$wilayaId = sprintf('DZA%02d', $wilaya_id);

		// Get communes data with per-state caching
		$communes_path = Functions::get_path($this->provider['slug'].'_communes_'.$wilayaId.'.json');

		if (!file_exists($communes_path)) {
			$url = $this->provider['api_url'] . '/api/geo/cities?stateId[]='.$wilayaId.'&perPage=2000';
			$request = new MdmRequest($this->provider);
			$response_array = $request->get($url);

			file_put_contents($communes_path, json_encode($response_array));
		}
		$communes_data = json_decode(file_get_contents($communes_path), true);

		// The API already returns only cities for the requested state
		$communes = [];
		if (isset($communes_data) && is_array($communes_data)) {
			$communes = $communes_data;
		}

		$centeres_path = Functions::get_path($this->provider['slug'].'_centers_'.WC_BORDEREAU_GENERATOR_VERSION.'_'.date('Y').'_'. (date('z') + 1).'.json');

		if (!file_exists($centeres_path)) {
			$url = $this->provider['api_url'] . '/api/v2/shipping/companies/offices?countryId=DZA&perPage=1000';
			$request = new MdmRequest($this->provider);
			$stopDesks = $request->get($url);

			file_put_contents($centeres_path, json_encode($stopDesks));
		}

		$stopDesksData = json_decode(file_get_contents($centeres_path), true);

		// Extract the list ONCE before the loop
		$stopDesksList = $stopDesksData['list'] ?? [];

		// Pre-index stop desks by cityCode for faster lookup
		$stopDesksByCityCode = [];
		foreach ($stopDesksList as $desk) {
			$cityCode = $desk['address']['cityCode'] ?? null;
			if ($cityCode) {
				$stopDesksByCityCode[$cityCode] = true;
			}
		}

		// Format the communes with stop desk information
		$communesResult = [];

		foreach ($communes as $commune) {
			$has_stop_desk = isset($stopDesksByCityCode[$commune['code']]);

			// If hasStopDesk is enabled, only include communes that actually have stop desks
			if ($hasStopDesk && !$has_stop_desk) {
				continue;
			}

			$communesResult[] = [
				'id'            => $commune['name'],
				'name'          => $commune['name'],
				'code'          => $commune['id'],
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

	    $orderStatuses = [
		    'out-of-stock' => 'Out of Stock',
		    'preparing' => 'Preparing',
		    'received-by-mdm' => 'Received by MDM',
		    'packaged' => 'Packaged',
		    'dispatched' => 'Dispatched',
		    'received' => 'Received',
		    'in-transit' => 'In Transit',
		    'ready-for-delivery' => 'Ready for Delivery',
		    'out-for-delivery' => 'Out for Delivery',
		    'postponed' => 'Postponed',
		    'delivered' => 'Delivered',
		    'delivered-partially' => 'Delivered Partially',
		    'delivery-attempt-failed' => 'Delivery Attempt Failed',
		    'delivery-failed' => 'Delivery Failed',
		    'returning' => 'Returning',
		    'return-ready' => 'Return Ready',
		    'return-received' => 'Return Received',
		    'return-grouped' => 'Return Grouped',
		    'returned' => 'Returned',
		    'refund-pending' => 'Refund Pending',
		    'refund-collected' => 'Refund Collected',
		    'exchange-pending' => 'Exchange Pending',
		    'exchange-failed' => 'Exchange Failed',
		    'exchange-collected' => 'Exchange Collected',
		    'refunded' => 'Refunded',
		    'refunded-partially' => 'Refunded Partially',
		    'lost' => 'Lost',
	    ];

        $result = [];

        if (is_array($response_array)) {
            foreach ($response_array['statusHistory'] as $key => $item) {

                if ($item['status'] === 'returned') {
                    $result[$key]['failed'] = true;
                }

                if (strpos($item['status'], 'delivered') !== false) {
                    $result[$key]['delivered'] = true;
                }

                $result[$key]['date'] = $item['date'];
                $result[$key]['status'] = $orderStatuses[$item['status']] ?? $item['status'];
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

        $wilayaId = 0;

        foreach ($this->get_wilayas() as $wilaya) {
            if ($wilaya[1] === $item['wilaya']) {
                $wilayaId = $wilaya[0];
                break;
            }
        }

        $detail[0]['address'] = $item['destination']['streetAddress'] ?? '';
        $detail[0]['phone'] = $item['client']['phone'] ?? '';
        $detail[0]['fname'] = $item['client']['firstName'] ?? '';
        $detail[0]['lname'] = $item['client']['lastName'] ?? '';
        $detail[0]['free'] = $item['isShippingFree'] ?? false;
        $detail[0]['exchange'] = false;
        $detail[0]['stopdesk'] = $item['isStopDesk'] ?? false;
        $detail[0]['last_status'] = $item['status'] ?? '';
        $detail[0]['price'] = $item['productsPrice'] ?? 0;
        $detail[0]['commune'] = $item['destination']['cityName'] ?? '';
        $detail[0]['wilaya'] = $item['destination']['stateName'] ?? '';
        $detail[0]['product_id'] = isset($item['products']) && count($item['products']) ? $item['products'][0]['trackingId'] : null;
        $detail[0]['product_qty'] = isset($item['products']) && count($item['products']) ? $item['products'][0]['quantity'] : null;
        $detail[0]['product_name'] = isset($item['products']) && count($item['products']) ? $item['products'][0]['name'] : null;

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
        $path = Functions::get_path($this->provider['slug'] .'_wilaya.json');

        if (!file_exists($path)) {

            $request = new \WooBordereauGenerator\Admin\Shipping\MdmRequest($this->provider);
            $content = $request->get($this->provider['api_url'] . '/api/geo/states?countryId[]=DZA&perPage=100');

            file_put_contents($path, json_encode($content));
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
     * Get detailed parcel information.
     * 
     * Fetches and formats detailed parcel information for a tracking number.
     *
     * @param string $tracking_number  The tracking number.
     * @param int    $post_id          The order ID.
     * @return void  Outputs JSON response with tracking details.
     * @since 1.2.0
     */
    public function detail($tracking_number, $post_id)
    {
        $url = $this->provider['api_url'] . '/api/v2/shipping/parcels/'. $tracking_number;
        $request = new \WooBordereauGenerator\Admin\Shipping\MdmRequest($this->provider);

        $content = $request->get($url, false);

        $details = $this->format_tracking_detail($content);

        if (isset($details['activity'])) {
            if (isset($details[0]['last_status'])) {
                BordereauGeneratorAdmin::update_tracking_status($this->formData, $details[0]['last_status'], 'single_tracking');
            }
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
        $path = Functions::get_path($this->provider['slug']. '_wilaya.json');
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
                $wilayasResult[$i]['id'] = $wilaya['code'];
            }

            return $wilayasResult;
        }

        return $wilayasResult;
    }


    /**
     * Delete (archive) a parcel.
     * 
     * Archives a parcel with MDM Express (MDM does not support DELETE,
     * uses POST to archive endpoint instead) and removes all related
     * shipping metadata from the order.
     *
     * @param string $tracking_number  The tracking number to archive.
     * @param int    $post_id          The order ID.
     * @return null
     * @since 1.3.0
     */
    public function delete($tracking_number, $post_id)
    {

        if ($tracking_number)
		{
            $url = $this->provider['api_url'] . "/api/v2/shipping/parcels/" . $tracking_number . "/archive";

            $request = new \WooBordereauGenerator\Admin\Shipping\MdmRequest($this->provider);
            $request->post($url, [], \WooBordereauGenerator\Admin\Shipping\MdmRequest::POST);
        }

        $this->reset_meta($post_id);

        return null;
    }


    /**
     * Get the shipping label (parcel slip).
     * 
     * Retrieves the parcel slip by posting to the prints endpoint,
     * then redirects to download the generated file.
     *
     * @return void  Redirects to the label URL.
     * @since 2.2.0
     */
    public function get_slip()
    {
        $tracking = $this->formData->get_meta('_shipping_tracking_number', true);

        if ( empty( $tracking ) ) {
            wp_die(
                __( 'No tracking number found for this order.', 'woo-bordereau-generator' ),
                __( 'Label Error', 'woo-bordereau-generator' ),
                [ 'response' => 404, 'back_link' => true ]
            );
        }

        // First, check if the parcel has been assigned to a carrier (has shippingId).
        // MDM's print endpoint returns a 500 error if the parcel hasn't been processed yet.
        $parcel_url = $this->provider['api_url'] . '/api/v2/shipping/parcels/' . $tracking;
        $parcel_request = new \WooBordereauGenerator\Admin\Shipping\MdmRequest($this->provider);
        $parcel = $parcel_request->get($parcel_url, false);

        if ( is_array( $parcel ) && empty( $parcel['shippingId'] ) ) {
            $status = $parcel['status'] ?? 'unknown';
            wp_die(
                sprintf(
                    __( 'The label is not available yet. The parcel (status: %s) has not been assigned to a carrier. Please wait for MDM Express to process it and try again.', 'woo-bordereau-generator' ),
                    '<strong>' . esc_html( $status ) . '</strong>'
                ),
                __( 'Label Not Ready', 'woo-bordereau-generator' ),
                [ 'response' => 503, 'back_link' => true ]
            );
        }

	    $data = [
		    'parcelIds' => [$tracking],
	    ];

	    $url = $this->provider['api_url'] . "/api/prints/parcel-slips";

	    $request = wp_safe_remote_post(
		    $url,
		    [
			    'headers'     => [
				    'Content-Type' => 'application/json',
				    'x-api-key'    => $this->api_key
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

		    if ( ! empty( $response['fileId'] ) ) {
			    header('Location: '. $this->provider['api_url'] . "/api/prints/files/" . $response['fileId']);
			    exit;
		    }

		    // Handle API errors — parse message from various possible response structures
		    $error_message = $response['message'] 
		        ?? $response['error']['message'] 
		        ?? $response['error'] 
		        ?? null;

		    // Hide raw internal MDM errors from the user
		    if ( $error_message && strpos( $error_message, 'Cannot read properties' ) !== false ) {
		        $error_message = null;
		    }

		    $error_message = $error_message ?: __( 'Label generation failed. The parcel may still be processing by the carrier. Please try again later.', 'woo-bordereau-generator' );

		    wp_die(
			    esc_html( $error_message ),
			    __( 'Label Error', 'woo-bordereau-generator' ),
			    [ 'response' => 503, 'back_link' => true ]
		    );
	    }

	    wp_die(
		    __( 'Could not connect to MDM Express to retrieve the label.', 'woo-bordereau-generator' ),
		    __( 'Connection Error', 'woo-bordereau-generator' ),
		    [ 'response' => 503, 'back_link' => true ]
	    );
    }


    /**
     * Get tracking information for an order.
     * 
     * Retrieves the current status and tracking events for a parcel.
     *
     * @param string $tracking_number  The tracking number.
     * @param int|false $post_id       The order ID (optional).
     * @return void  Outputs JSON response with tracking events.
     */
    public function track($tracking_number, $post_id = false)
    {

        $url = $this->provider['api_url'] . "/api/v2/shipping/parcels/".$tracking_number."/status-history";
        $request = new \WooBordereauGenerator\Admin\Shipping\MdmRequest($this->provider);
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
	 * Get stop desk centers/offices.
	 *
	 * @param $commune
	 * @return array
	 */
	public function get_centers($commune): array
	{

		// Create cache path using date-based versioning to refresh daily
		$centeres_path = Functions::get_path($this->provider['slug'].'_centers_'.WC_BORDEREAU_GENERATOR_VERSION.'_'.date('Y').'_'. (date('z') + 1).'.json');

		if (!file_exists($centeres_path)) {

			$url = $this->provider['api_url'] . '/api/v2/shipping/companies/offices?countryId=DZA&perPage=1000';
			$request = new MdmRequest($this->provider);
			$stopDesks = $request->get($url);

			file_put_contents($centeres_path, json_encode($stopDesks));
		}

		$stopDesks = json_decode(file_get_contents($centeres_path), true);

		
		// Filter centers by commune
		$filtered_centers = array_filter($stopDesks['list'], function ($item) use ($commune) {

			return isset($item['address']['cityName']) && strtolower(urldecode($item['address']['cityName'])) == strtolower(urldecode($commune));
		});

		// Format the results
		$communesResult = [];
		foreach ($filtered_centers as $i => $item) {
			$communesResult[] = [
				'id' => $item['id'],
				'name' => $item['name'],
				'commune_name' => $item['address']['cityName'],
				'wilaya_name' => $item['address']['stateName']
			];
		}
		
		return $communesResult;
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
		$overweight = $fields['overweight']['choices'][0]['name'] ?? null;
		$canOpen = $fields['can_open']['choices'][0]['name'] ?? null;
		$overweightCase = get_option($overweight) ? get_option($overweight) : 'recalculate-without-overweight';
		$canOpenCase = get_option($canOpen) ? get_option($canOpen) : 'false';

		$params = $order->get_data();
		$products = $params['line_items'];

		$productString = Helpers::generate_products_string($products);

		// check if the name is an agence check in the centers list
		$AgencyName = $order->get_shipping_method();
		$centers = $this->get_centers($order->get_billing_state());
		$center = $order->get_meta('_billing_center_id', true);

		if (! is_numeric($center)) {
			$found = array_filter($centers, function ($v, $k) use ($AgencyName) {
				return strtolower($v['name']) == strtolower(urldecode($AgencyName));
			}, ARRAY_FILTER_USE_BOTH);

			if ($found) {
				$center = reset($found);
			}

			if (is_array($center)) {
				$center = $center['id'];
			}
		}

		$total = get_option($key) ? get_option($key) : 'with-shipping';

		$cart_value = 0;

		if ($total == 'with-shipping') {
			$cart_value = (float) $order->get_total();
		} elseif ($total === 'without-shipping') {
			$cart_value =  (float) $order->get_total() - $order->get_total_shipping();
		}


		$shippingOverweightPrice = 0;
		$shippingOverweight = 0;

		$total_weight = Helpers::get_product_weight($order);

		// check if the option of extra weight
		if ($overweightCase == 'recalculate-with-overweight') {

			if ($total_weight > $shippingOverweight) {
				$shippingOverweight = $total_weight;
			}

			if ($shippingOverweight > 5) {
				$zonePrice = 50;

				$shippingOverweightPrice = max(0, ceil($shippingOverweight - 5)) * $zonePrice;
				$cart_value = $cart_value + $shippingOverweightPrice;
			}
		}

		$can_open = $canOpenCase == 'true';

		$data = [];
		if ($key) {
			$data['total_calculate'] = get_option($key) ? get_option($key) : 'with-shipping';
			$data['type'] = $provider['type'];
			$data['total'] = $cart_value;
			$data['order_number'] = $order->get_order_number();
			$data['products_string'] = $productString;
			$data['total_weight'] = $total_weight;
			$data['total_without_shipping'] = $total_weight;
			$data['zone_fee'] = 40;
			$data['center_name'] =  (int) $center ?? null;
			$data['can_open'] = $can_open;
			$data['commune'] = (int) $order->get_billing_city();

			$data['shipping_overweight_price'] = $shippingOverweightPrice;

		}
		return $data;
	}
}
