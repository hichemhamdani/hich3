<?php

/**
 * ZimoExpress shipping provider integration for WooCommerce Bordereau Generator.
 * 
 * This class handles the integration with ZimoExpress shipping services,
 * providing functionality for generating shipping labels, tracking packages,
 * managing shipping data, and interacting with the ZimoExpress API.
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
 * ZimoExpress shipping provider implementation.
 * 
 * Extends the BordereauProvider class and implements BordereauProviderInterface
 * to provide ZimoExpress-specific shipping functionality for the WooCommerce
 * Bordereau Generator plugin.
 */
class Yalitec extends \WooBordereauGenerator\Admin\Shipping\BordereauProvider implements \WooBordereauGenerator\Admin\Shipping\BordereauProviderInterface
{

    /**
     * The ZimoExpress API token.
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
     * The ZimoExpress API username/ID.
     * 
     * @var string|null
     */
    private $store_id;
	private bool $is_fulfilement;
	private bool $confirmation;

	/**
     * Constructor.
     * 
     * Initializes a new ZimoExpress provider instance with order data,
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
    }

    /**
     * Generate the tracking code.
     * 
     * Creates a shipping label and tracking number for the order by
     * formatting order data and sending it to the ZimoExpress API.
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

		$communeId = 0;
		if (isset($this->data['commune'])) {
			$communes = $this->get_communes($wilayaId, false);
			$key = array_search($this->data['commune'], array_column($communes, "name"));

			if ($key) {
				$communeId = $communes[$key]['code'];
			}
		}

		if (isset($params['billing']['city']) && empty($communeId)) {
			$commnes = $this->get_communes($wilayaId, false);
			$key = array_search($params['billing']['city'], array_column($commnes, "name"));
			if ($key) {
				$communeId = $commnes[$key]['code'];
			}
		}

		$products = [];
		$i = 0;

		if(isset($_POST['advance']) && isset($_POST['product_id']) && isset($_POST['product_qty']) && $_POST['product_price']) {
			$products[$i]['product_id'] = $_POST['product_id'];
			$products[$i]['quantity'] = ((int) $_POST['product_qty']) == 0 ? 1 : (int) $_POST['product_qty'];
			$products[$i]['price'] = (int) $_POST['product_price'];
		} else {
			// create the products
			foreach ($this->formData->get_items() as $item_key => $item) {

				$item_data = $item->get_data();
				$product_id = $item->get_product_id();
				$product_variation_id = $item->get_variation_id();
				$_id = $product_id;

				/** @var \WC_Product_Simple $product */
				$product = wc_get_product($_id);

			$product_tracking_id = get_post_meta( $_id, '_yalitec_product_id', true );
			if ( empty( $product_tracking_id ) ) {
				// Backward compatibility: check legacy meta key without underscore prefix
				$product_tracking_id = get_post_meta( $_id, 'yalitec_product_id', true );
				if ( ! empty( $product_tracking_id ) ) {
					// Migrate to the correct meta key
					update_post_meta( $_id, '_yalitec_product_id', $product_tracking_id );
				}
			}


			if ( empty( $product_tracking_id ) ) {


				$url = $this->provider['api_url'] . "/products";

					$data = [
						'storeId' => $this->store_id,
						'name' => $product->get_name(),
						'description' => $product->get_name(),
						'categoryId' => '65f1be1037fcc18aaaffd516',
						'isFulfilledByUs' => $this->is_fulfilement,
						'pricing[selling]' =>  $product->get_price(),
						'pricing[purchasing]' =>  0,
						'sku' => $product->get_sku(),
						'images' => [],
						'creativeLink' => '',
						'weight' => $product->get_weight(),
						'size' => $product->get_length(),
						'landingPageLink' => $product->get_permalink(),
					];

					$curl = curl_init();

					curl_setopt_array($curl, array(
						CURLOPT_URL => $url,
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_ENCODING => '',
						CURLOPT_MAXREDIRS => 10,
						CURLOPT_TIMEOUT => 0,
						CURLOPT_FOLLOWLOCATION => true,
						CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
						CURLOPT_CUSTOMREQUEST => 'POST',
						CURLOPT_POSTFIELDS => $data,
						CURLOPT_HTTPHEADER => array(
							'X-API-KEY: ' . $this->api_key,
						),
					));

					$response = curl_exec($curl);
					curl_close($curl);

					$response = json_decode($response, true);

					if (isset($response['trackingId'])) {
						update_post_meta( $product->get_id(), '_yalitec_product_id', $response['trackingId'] );
						$product_tracking_id = $response['trackingId'];
					}
				} else {

					$params = [
						'sortBy' => [
							'createdAt' => 'DESC',
						],
						'filters' => [
							'countryId' => ['DZA'],
							'storeId'   => [$this->store_id],
							'search'    => $product_tracking_id,
						],
						'pagination' => [
							'page' => 1,
						],
					];

					$url = $this->provider['api_url'] .'/products/search';

					$request = wp_safe_remote_post(

						$url,
						[
							'headers'     => [
								'Content-Type' => 'application/json',
								'X-API-KEY'    => $this->api_key
							],
							'body'        => wp_json_encode( $params ),
							'method'      => 'POST',
							'data_format' => 'body',
							'timeout'     => 15
						]
					);

					if ( ! is_wp_error( $request ) ) {

						$body     = wp_remote_retrieve_body( $request );
						$response = json_decode( $body, true );


						if ( empty($response['list']) ) {

							// check if the product exist in yalitec
							$curl = curl_init();

							$params = [
								'sortBy' => [
									'createdAt' => 'DESC',
								],
								'filters' => [
									'countryId' => ['DZA'],
									'storeId'   => [$this->store_id],
									'search'    => $product->get_sku(),
								],
								'pagination' => [
									'page' => 1,
								],
							];

							$url = $this->provider['api_url'] .'/products/search';

							$request = wp_safe_remote_post(

								$url,
								[
									'headers'     => [
										'Content-Type' => 'application/json',
										'X-API-KEY'    => $this->api_key
									],
									'body'        => wp_json_encode( $params ),
									'method'      => 'POST',
									'data_format' => 'body',
									'timeout'     => 15
								]
							);

							if ( ! is_wp_error( $request ) ) {
								$body     = wp_remote_retrieve_body( $request );
								$response = json_decode( $body, true );

								if(!empty($response['list'])) {

									$itemFound = reset($response['list']);
									if($itemFound) {
										$product_tracking_id = $itemFound['trackingId'];
										update_post_meta( $product->get_id(), '_yalitec_product_id', $product_tracking_id );
									}
								}
							}
						}
					}
				}

			if ($product_variation_id) {

				// Read variant tracking ID using the correct meta key
				$variation_tracking_id = get_post_meta( $product_variation_id, '_yalitec_variant_id', true );
				if ( empty( $variation_tracking_id ) ) {
					// Backward compatibility: check legacy meta key
					$variation_tracking_id = get_post_meta( $product_variation_id, 'yalitec_product_id', true );
					if ( ! empty( $variation_tracking_id ) ) {
						// Migrate to the correct meta key
						update_post_meta( $product_variation_id, '_yalitec_variant_id', $variation_tracking_id );
					}
				}

				/** @var \WC_Product_Variation $variation */
				$variation = wc_get_product( $product_variation_id );

				$variation_name = $variation->get_name();
				$variation_sku  = $variation->get_sku();


				if ( empty($variation_tracking_id) && ! empty( $product_tracking_id ) ) {

					// FIRST: Search for existing variant by SKU in Yalitec before creating a new one
					$variants_url = $this->provider['api_url'] . sprintf( "/products/%s/variants", $product_tracking_id );

					$search_request = wp_safe_remote_request(
						$variants_url,
						[
							'headers' => [
								'Content-Type' => 'application/json',
								'X-API-KEY'    => $this->api_key
							],
							'method'  => 'GET',
							'timeout' => 15
						]
					);

					if ( ! is_wp_error( $search_request ) ) {
						$search_body     = wp_remote_retrieve_body( $search_request );
						$search_response = json_decode( $search_body, true );

						// Check the variants list for a matching SKU
						// If multiple variants share the same SKU (duplicates), prefer the one with pricing data
						$variants_list = $search_response['list'] ?? $search_response ?? [];
						$best_match = null;
						if ( is_array( $variants_list ) ) {
							foreach ( $variants_list as $existing_variant ) {
								if ( isset( $existing_variant['sku'] ) && $existing_variant['sku'] === $variation_sku && ! empty( $existing_variant['trackingId'] ) ) {
									if ( $best_match === null ) {
										$best_match = $existing_variant;
									} elseif ( ! empty( $existing_variant['pricing'] ) && empty( $best_match['pricing'] ) ) {
										// Prefer variant with pricing data (the real/original one)
										$best_match = $existing_variant;
									}
								}
							}
						}
						if ( $best_match ) {
							$variation_tracking_id = $best_match['trackingId'];
							update_post_meta( $product_variation_id, '_yalitec_variant_id', $variation_tracking_id );
						}
					}
				}

				// Only create a new variant if we truly couldn't find one
				if ( empty($variation_tracking_id) && ! empty( $product_tracking_id ) ) {

					$data = [
						'name'                => $variation_name,
						'pricing[selling]'    => $variation->get_price(),
						'pricing[purchasing]' => 0,
						'sku'                 => $variation_sku,
						'weight'              => (int) $variation->get_weight() ?? 0,
					];

					$url = $this->provider['api_url'] . sprintf( "/products/%s/variants", $product_tracking_id );

					$request = wp_safe_remote_post(
						$url,
						[
							'headers'     => [
								'Content-Type' => 'application/json',
								'X-API-KEY'    => $this->api_key
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

						if ( isset( $response['trackingId'] ) ) {
							update_post_meta( $product_variation_id, '_yalitec_variant_id', $response['trackingId'] );
							$variation_tracking_id = $response['trackingId'];
						}
					}
				}
			}

				$products[$i]['trackingId'] = $product_tracking_id;
				$products[$i]['quantity'] = $item_data['quantity'];
				$products[$i]['price'] = (int)$item_data['total'];
				if (!empty($product_variation_id)) {
					$products[$i]['variantId'] = $variation_tracking_id;
				}

				$i++;
			}
		}

		$phone = $this->data['phone'] ?? $params['billing']['phone'];
		$phone = str_replace(' ', '', str_replace('+213', '0', $phone));

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

		if ($this->formData->has_shipping_method('local_pickup') && $this->data['shipping_type'] === 'stopdesk' ||  $this->formData->has_shipping_method('local_pickup_yalidine')) {
			$stop_desk = 1;
		}

		if ($this->data['shipping_type'] === 'stopdesk') {
			$stop_desk = 1;
		} else {
			$stop_desk = 0;
		}

		if ($this->data['from_wilaya']) {
			$from_wilaya = (int) str_replace('DZ-', '', $this->data['from_wilaya']);
		}

		$data = [
			// Yalitec Payload
			'storeId' => $this->store_id,
			'client' => [
				'firstName' => $this->data['fname'],
				'lastName' => $this->data['lname'],
				'phones' => [
					$phone
				]
			],
			'products' => $products,
//			'productsToCollect' => [
//				[
//					'trackingId' => 'string',
//					'quantity' => 0,
//					'variantId' => 'string'
//				]
//			],
//			'paymentMethod' => 'cod',
			'totalProductsPrice' => (int) ($this->data['price'] ?? $params['total']),
			'weight' => 0,
			'callCenter' => $this->confirmation ? 'yalitec' : 'in-house',
			'shipping' => [
				'company' => 'yalidine',
				'type' => 'express',
//				'stopdeskOfficeId' => 'string',
				'destination' => [
					'cityId' => $this->data['commune'] ?? $params['billing']['city'],
					'streetAddress' => $this->data['address'] ?? $params['billing']['address_1'] ?? stripslashes($params['billing']['city'])
				],
				'stopdesk' => (bool)$stop_desk,
				'freeShipping' => (bool)$free_shipping
			],
			'notes' => $note
		];

		if (isset($this->data['is_update'])) {
			if ($this->confirmation) {
				$url = $this->provider['api_url'] . "/orders/" . $this->data['tracking_number'];
			} else {
				$url = $this->provider['api_url'] . "/orders/confirmed/" . $this->data['tracking_number'];
			}
		} else {
			if ($this->confirmation) {
				$url = $this->provider['api_url'] . "/orders";
			} else {
				 $url = $this->provider['api_url'] . "/orders/confirmed";
			}
		}

		// The /orders/confirmed endpoint requires cityId as a code (e.g. "DZA020201")
		// while /orders expects the city name. Convert only for the confirmed path.
		if (!$this->confirmation && !empty($communeId)) {
			$data['shipping']['destination']['cityId'] = $communeId;
		}

		$request = wp_safe_remote_post(
			$url,
			[
				'headers' => [
					'Content-Type' => 'application/json',
					'X-API-KEY'    => $this->api_key
				],
				'body' => wp_json_encode($data),
				'method' => isset($this->data['is_update']) ? 'PATCH' : 'POST',
				'data_format' => 'body',
			]
		);

		$body = wp_remote_retrieve_body($request);
		$response = json_decode($body, true);

		// Handle PRODUCT_NOT_FOUND by re-creating the product/variant and retrying
		if (
			isset($response['error']['code']) &&
			$response['error']['code'] === 'ORDERS.PRODUCT_NOT_FOUND' &&
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

					$stored_product_tracking = get_post_meta($product_id, '_yalitec_product_id', true);
					$stored_variant_tracking = $product_variation_id ? get_post_meta($product_variation_id, '_yalitec_variant_id', true) : '';

					$is_variant_issue = ($stored_variant_tracking === $failed_tracking_id);
					$is_product_issue = ($stored_product_tracking === $failed_tracking_id);

					if (!$is_variant_issue && !$is_product_issue) {
						continue;
					}

					/** @var \WC_Product_Simple $product */
					$product = wc_get_product($product_id);

					// If the main product tracking ID is the one that failed, or if we need to recreate the parent first
					if ($is_product_issue || $is_variant_issue) {
						// Delete old meta so it gets recreated
						delete_post_meta($product_id, '_yalitec_product_id');

						// Recreate the product in Yalitec
						$create_url = $this->provider['api_url'] . "/products";
						$create_data = [
							'storeId'              => $this->store_id,
							'name'                 => $product->get_name(),
							'description'           => $product->get_name(),
							'categoryId'           => '65f1be1037fcc18aaaffd516',
							'isFulfilledByUs'      => $this->is_fulfilement,
							'pricing[selling]'     => $product->get_price(),
							'pricing[purchasing]'  => 0,
							'sku'                  => $product->get_sku(),
							'images'               => [],
							'creativeLink'         => '',
							'weight'               => $product->get_weight(),
							'size'                 => $product->get_length(),
							'landingPageLink'      => $product->get_permalink(),
						];

						$curl = curl_init();
						curl_setopt_array($curl, [
							CURLOPT_URL            => $create_url,
							CURLOPT_RETURNTRANSFER => true,
							CURLOPT_ENCODING       => '',
							CURLOPT_MAXREDIRS      => 10,
							CURLOPT_TIMEOUT        => 0,
							CURLOPT_FOLLOWLOCATION => true,
							CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
							CURLOPT_CUSTOMREQUEST  => 'POST',
							CURLOPT_POSTFIELDS     => $create_data,
							CURLOPT_HTTPHEADER     => [
								'X-API-KEY: ' . $this->api_key,
							],
						]);

						$create_response = curl_exec($curl);
						curl_close($curl);
						$create_response = json_decode($create_response, true);

						if (!isset($create_response['trackingId'])) {
							// Product creation failed, return original error
							break;
						}

						$new_product_tracking_id = $create_response['trackingId'];
						add_post_meta($product_id, '_yalitec_product_id', $new_product_tracking_id, true);

						// Update the products array with the new tracking ID
						foreach ($products as &$p) {
							if (isset($p['trackingId']) && $p['trackingId'] === $failed_tracking_id && !$is_variant_issue) {
								$p['trackingId'] = $new_product_tracking_id;
							}
							// If it's a variant issue, update the parent trackingId too since we recreated the product
							if (isset($p['trackingId']) && $p['trackingId'] === $stored_product_tracking) {
								$p['trackingId'] = $new_product_tracking_id;
							}
						}
						unset($p);
					}

					// If the failed tracking ID was a variant, recreate it under the new product
					if ($is_variant_issue && $product_variation_id) {
						delete_post_meta($product_variation_id, '_yalitec_variant_id');

						$new_product_tracking_id = get_post_meta($product_id, '_yalitec_product_id', true);

						/** @var \WC_Product_Variation $variation */
						$variation = wc_get_product($product_variation_id);

						$variant_data = [
							'name'                => $variation->get_name(),
							'pricing[selling]'    => $variation->get_price(),
							'pricing[purchasing]' => 0,
							'sku'                 => $variation->get_sku(),
							'weight'              => (int) $variation->get_weight() ?? 0,
						];

						$variant_url = $this->provider['api_url'] . sprintf("/products/%s/variants", $new_product_tracking_id);

						$variant_request = wp_safe_remote_post(
							$variant_url,
							[
								'headers'     => [
									'Content-Type' => 'application/json',
									'X-API-KEY'    => $this->api_key,
								],
								'body'        => wp_json_encode($variant_data),
								'method'      => 'POST',
								'data_format' => 'body',
								'timeout'     => 15,
							]
						);

						if (!is_wp_error($variant_request)) {
							$variant_body = wp_remote_retrieve_body($variant_request);
							$variant_response = json_decode($variant_body, true);

							if (isset($variant_response['trackingId'])) {
								add_post_meta($product_variation_id, '_yalitec_variant_id', $variant_response['trackingId'], true);

								// Update the products array with the new variant ID
								foreach ($products as &$p) {
									if (isset($p['variantId']) && $p['variantId'] === $failed_tracking_id) {
										$p['variantId'] = $variant_response['trackingId'];
									}
								}
								unset($p);
							}
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
							'X-API-KEY'    => $this->api_key,
						],
						'body'        => wp_json_encode($data),
						'method'      => isset($this->data['is_update']) ? 'PATCH' : 'POST',
						'data_format' => 'body',
					]
				);

				$body = wp_remote_retrieve_body($retry_request);
				$response = json_decode($body, true);
			}
		}

		// --- Standard error handling below (keep as-is) ---

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
        update_post_meta($post_id, '_shipping_tracking_url', 'https://'.$this->provider['url'].'/suivre-un-colis/?tracking='. wc_clean($trackingNumber));
        update_post_meta($post_id, '_shipping_tracking_method', $this->provider['slug']);

        if($order) {
            $order->update_meta_data('_shipping_tracking_number', wc_clean($trackingNumber));
            $order->update_meta_data('_shipping_tracking_label', wc_clean($label));
            $order->update_meta_data('_shipping_label', wc_clean($label));
            $order->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
            $order->save();

            Helpers::make_note($order, $trackingNumber, $this->provider['name']);
        }

        $track = $this->provider['url'].'/suivre-un-colis/?tracking='. wc_clean($trackingNumber);

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
        delete_post_meta($post_id, '_shipping_zimoexpress_order_id');
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

//        $url = $this->provider['api_url'] . "tracking/$tracking_number/events";
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

		// Get communes data with caching
		$communes_path = Functions::get_path($this->provider['slug'].'_communes.json');

		if (!file_exists($communes_path)) {
			$url = $this->provider['api_url'] . '/geo/cities?stateId%5C[%5C]='.$wilayaId.'&perPage=2000';
			$request = new YalitecRequest($this->provider);
			$response_array = $request->get($url);

			file_put_contents($communes_path, json_encode($response_array));
		}
		$communes_data = json_decode(file_get_contents($communes_path), true);

		// Filter communes by wilaya_id
		$communes = [];
		if (isset($communes_data) && is_array($communes_data)) {
			$communes = array_filter($communes_data, function ($item) use ($wilayaId) {
				return $item['stateId'] === $wilayaId;
			});
		}

		$centeres_path = Functions::get_path($this->provider['slug'].'_centers_'.WC_BORDEREAU_GENERATOR_VERSION.'_'.date('Y').'_'. (date('z') + 1).'.json');

		if (!file_exists($centeres_path)) {
			$url = $this->provider['api_url'] . '/shipping/companies/yalidine/offices?country\[\]=DZA&perPage=1000';
			$request = new YalitecRequest($this->provider);
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
				'has_stop_desk' => $has_stop_desk, // Use actual value, not hardcoded true
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
		    'reimbursed' => 'Reimbursed',
		    'lost' => 'Lost',
		    'out-of-stock' => 'Out of Stock',
		    'stock-ready' => 'Stock Ready',
		    'sending-to-call-center' => 'Sending to Call Center',
		    'confirmation-blocked' => 'Confirmation Blocked',
		    'sending-to-shipping' => 'Sending to Shipping',
		    'shipping-blocked' => 'Shipping Blocked',
		    'sending-to-warehouse' => 'Sending to Warehouse',
		    'pending' => 'Pending',
		    'confirmed' => 'Confirmed',
		    'call-later' => 'Call Later',
		    'not-answer' => 'Not Answered',
		    'cancelled' => 'Cancelled',
		    'preparing' => 'Preparing',
		    'packaged' => 'Packaged',
		    'dispatched' => 'Dispatched',
		    'to-destination-state' => 'To Destination State',
		    'in-destination-state' => 'In Destination State',
		    'changing-office' => 'Changing Office',
		    'in-target-office' => 'In Target Office',
		    'postponed' => 'Postponed',
		    'out-for-delivery' => 'Out for Delivery',
		    'delivery-attempt-failed' => 'Delivery Attempt Failed',
		    'delivery-failed' => 'Delivery Failed',
		    'waiting-for-client' => 'Waiting for Client',
		    'hold' => 'Hold',
		    'delivered' => 'Delivered',
		    'delivered-partially' => 'Delivered Partially',
		    'returning-to-office' => 'Returning to Office',
		    'returned-to-office' => 'Returned to Office',
		    'return-grouped' => 'Return Grouped',
		    'return-ready' => 'Return Ready',
		    'returning' => 'Returning',
		    'returned-partially' => 'Returned Partially',
		    'returned' => 'Returned',
		    'exchange-pending' => 'Exchange Pending',
		    'exchange-collected' => 'Exchange Collected',
		    'exchange-in-route' => 'Exchange In Route',
		    'exchange-ready' => 'Exchange Ready',
		    'exchange-returned' => 'Exchange Returned',
		    'exchange-failed' => 'Exchange Failed',
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
//                $result[$key]['location'] = $item['reason'];
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

        $detail[0]['address'] = $item['shippingInfo']['startingAddress']['streetAddress'];
        $detail[0]['phone'] = $item['client']['phones'][0];
        $detail[0]['fname'] = $item['client']['firstName'];
        $detail[0]['lname'] = $item['client']['lastName'];
        $detail[0]['free'] = $item['fees']['freeShipping'];
        $detail[0]['exchange'] = false;
        $detail[0]['stopdesk'] = false;
        $detail[0]['last_status'] = isset($status[0]) ? $status[0]['status'] : '';
        $detail[0]['price'] = $item['fees']['totalToPay'];
        $detail[0]['commune'] = $item['shippingInfo']['startingAddress']['cityName'];
        $detail[0]['wilaya'] = $item['shippingInfo']['startingAddress']['stateName'];
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

            $request = new \WooBordereauGenerator\Admin\Shipping\YalitecRequest($this->provider);
            $content = $request->get($this->provider['api_url'] . '/geo/states?perPage=100');

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
        $url = $this->provider['api_url'] . '/orders/'. $tracking_number;
        $request = new \WooBordereauGenerator\Admin\Shipping\YalitecRequest($this->provider);

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
     * Delete a shipment.
     * 
     * Cancels a shipment with ZimoExpress and removes all related
     * shipping metadata from the order.
     *
     * @param string $tracking_number  The tracking number to delete.
     * @param int    $post_id          The order ID.
     * @return null
     * @since 1.3.0
     */
    public function delete($tracking_number, $post_id)
    {

        if ($tracking_number)
		{
            $url = $this->provider['api_url'] . "/orders/" . $tracking_number;

            $request = new \WooBordereauGenerator\Admin\Shipping\YalitecRequest($this->provider);
            $request->post($url, [], \WooBordereauGenerator\Admin\Shipping\YalitecRequest::DELETE);
        }

        $this->reset_meta($post_id);

        return null;
    }


    /**
     * Get the shipping label.
     * 
     * Retrieves the shipping label URL from order metadata and
     * redirects the browser to display or download it.
     *
     * @return void  Redirects to the label URL.
     * @since 2.2.0
     */
    public function get_slip()
    {
        $tracking = $this->formData->get_meta('_shipping_tracking_number', true);

	    $data = [
		    'format'   => 'A4',
		    'orders' => [$tracking],
	    ];

	    $url = $this->provider['api_url'] . "/shipping/companies/parcels/slips";

	    $request = wp_safe_remote_post(

		    $url,
		    [
			    'headers'     => [
				    'Content-Type' => 'application/json',
				    'X-API-KEY'    => $this->api_key
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

		    header('Location: '. $this->provider['api_url'] . "/shipping/companies/parcels/slips/" . $response['fileId']);
		    exit;
	    }
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

        $url = $this->provider['api_url'] . "/orders/".$tracking_number."/status-history";
        $request = new \WooBordereauGenerator\Admin\Shipping\YalitecRequest($this->provider);
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
	 *
	 * @param $commune
	 * @return array
	 */
	public function get_centers($commune): array
	{

		// Create cache path using date-based versioning to refresh daily
		$centeres_path = Functions::get_path($this->provider['slug'].'_centers_'.WC_BORDEREAU_GENERATOR_VERSION.'_'.date('Y').'_'. (date('z') + 1).'.json');

		if (!file_exists($centeres_path)) {

			$url = $this->provider['api_url'] . '/shipping/companies/yalidine/offices?country\[\]=DZA&perPage=1000';
			$request = new YalitecRequest($this->provider);
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
