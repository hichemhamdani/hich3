<?php

namespace WooBordereauGenerator\Admin\Shipping;

/**
 * Yalitec Settings Manager.
 *
 * This class handles the configuration settings for the Yalitec shipping provider,
 * including API settings, shipping rates, zones management, and package tracking status.
 *
 * @package    WC_Bordereau_Generator
 * @subpackage WC_Bordereau_Generator/admin/shipping
 * @author     Boukraa Mohamed <bm2ilabs@gmail.com>
 */


use WC_Shipping_Zone;
use WC_Shipping_Zones;
use WooBordereauGenerator\Admin\BordereauGeneratorAdmin;
use WooBordereauGenerator\Admin\Products\YalitecProductSync;
use WooBordereauGenerator\Functions;
use WooBordereauGenerator\Helpers;
use WP_Error;

/**
 * Yalitec settings management class.
 *
 * Manages the settings and configuration for the Yalitec shipping provider,
 * including API credentials, shipping zones, and bulk tracking.
 */
class YalitecSettings {
	/**
	 * Provider configuration data.
	 *
	 * @var array
	 */
	private $provider;

	/**
	 * Yalitec API username/ID.
	 *
	 * @var string|null
	 */
	protected $api_key;

	/**
	 * Yalitec API token/password.
	 *
	 * @var string|null
	 */
	private $store_id;
	/**
	 * @var false|mixed|null
	 */
	private bool $is_fulfilement;
	private bool $confirmation;

	/**
	 * Constructor.
	 *
	 * Initializes a new Yalitec settings manager with the provider configuration.
	 *
	 * @param array $provider The provider configuration array.
	 */
	public function __construct( array $provider ) {

		$this->api_key  = get_option( $provider['slug'] . '_api_key' );
		$this->store_id = get_option( $provider['slug'] . '_store_id' );
		$this->is_fulfilement = (bool) get_option( $provider['slug'] . '_fulfilled' ) == 'yes';
		$this->confirmation = get_option( $provider['slug'] . '_confirmation' ) === 'with-confirmation';
		$this->provider       = $provider;
	}

	/**
	 * Get the provider settings configuration.
	 *
	 * Returns an array of settings fields for the Yalitec provider
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

		$fields = $fields + [
				'section_end' => array(
					'type' => 'sectionend',
					'id'   => $this->provider['slug'] . '_section_end'
				)
			];

		return $fields;
	}


	/**
	 * Track multiple packages in bulk.
	 *
	 * Retrieves tracking status for multiple tracking numbers in a single
	 * API request and formats the results.
	 *
	 * @param array $codes Array of tracking codes to check.
	 *
	 * @return array|false Formatted tracking statuses or false on failure.
	 */
	public function bulk_track( array $codes ) {
		$url     = $this->provider['api_url'] . '/api/v1/packages/status';
		$request = new \WooBordereauGenerator\Admin\Shipping\YalitecRequest( $this->provider );
		$content = $request->get( $url, false, [
			'packages' => $codes,
		] );

		return $this->format_tracking( $content );
	}

	/**
	 * Get default status labels.
	 *
	 * Returns an array of status codes and their corresponding labels
	 * for use in tracking display and status management.
	 *
	 * @return array Status codes and labels.
	 */
	private function status(): array {

		return [
			'reimbursed'              => 'Reimbursed',
			'lost'                    => 'Lost',
			'out-of-stock'            => 'Out of Stock',
			'stock-ready'             => 'Stock Ready',
			'sending-to-call-center'  => 'Sending to Call Center',
			'confirmation-blocked'    => 'Confirmation Blocked',
			'sending-to-shipping'     => 'Sending to Shipping',
			'shipping-blocked'        => 'Shipping Blocked',
			'sending-to-warehouse'    => 'Sending to Warehouse',
			'pending'                 => 'Pending',
			'confirmed'               => 'Confirmed',
			'call-later'              => 'Call Later',
			'not-answer'              => 'Not Answered',
			'cancelled'               => 'Cancelled',
			'preparing'               => 'Preparing',
			'packaged'                => 'Packaged',
			'dispatched'              => 'Dispatched',
			'to-destination-state'    => 'To Destination State',
			'in-destination-state'    => 'In Destination State',
			'changing-office'         => 'Changing Office',
			'in-target-office'        => 'In Target Office',
			'postponed'               => 'Postponed',
			'out-for-delivery'        => 'Out for Delivery',
			'delivery-attempt-failed' => 'Delivery Attempt Failed',
			'delivery-failed'         => 'Delivery Failed',
			'waiting-for-client'      => 'Waiting for Client',
			'hold'                    => 'Hold',
			'delivered'               => 'Delivered',
			'delivered-partially'     => 'Delivered Partially',
			'returning-to-office'     => 'Returning to Office',
			'returned-to-office'      => 'Returned to Office',
			'return-grouped'          => 'Return Grouped',
			'return-ready'            => 'Return Ready',
			'returning'               => 'Returning',
			'returned-partially'      => 'Returned Partially',
			'returned'                => 'Returned',
			'exchange-pending'        => 'Exchange Pending',
			'exchange-collected'      => 'Exchange Collected',
			'exchange-in-route'       => 'Exchange In Route',
			'exchange-ready'          => 'Exchange Ready',
			'exchange-returned'       => 'Exchange Returned',
			'exchange-failed'         => 'Exchange Failed',
		];
	}

	/**
	 * Format tracking response data.
	 *
	 * Processes the tracking API response to create an associative array
	 * of tracking codes and their current statuses.
	 *
	 * @param array|null $json The tracking API response.
	 *
	 * @return array Formatted tracking data (tracking code => status).
	 */
	private function format_tracking( $json ) {
		if ( $json ) {

			$final = [];
			foreach ( $json as $key => $value ) {
				$final[ $value['tracking_code'] ] = $value['status'];
			}

			return $final;
		}

		return [];
	}


	/**
	 * Import shipping classes from Yalitec.
	 *
	 * Configures WooCommerce shipping methods based on Yalitec data.
	 * This is a placeholder method for future implementation.
	 *
	 * @param string $flat_rate_label Label for flat rate shipping.
	 * @param string $pickup_local_label Label for local pickup shipping.
	 * @param bool $flat_rate_enabled Whether flat rate shipping is enabled.
	 * @param bool $pickup_local_enabled Whether local pickup is enabled.
	 * @param bool $agency_import_enabled Whether agency import is enabled (optional).
	 * @param bool $is_economic Whether to use economic shipping rates (optional).
	 *
	 * @return void
	 * @since 1.6.5
	 */
	public function import_shipping_class(
		$flat_rate_label,
		$pickup_local_label,
		$flat_rate_enabled,
		$pickup_local_enabled,
		$agency_import_enabled = null
	) {
		if ( $pickup_local_enabled ) {
			if ( $pickup_local_label ) {
				update_option( $this->provider['slug'] . '_pickup_local_label', $pickup_local_label );
			}
		}

		if ( $flat_rate_enabled ) {
			if ( $flat_rate_label ) {
				update_option( $this->provider['slug'] . '_flat_rate_label', $flat_rate_label );
			}
		}

		$all_results = $this->get_fees();

		if ( $agency_import_enabled ) {
			update_option( $this->provider['slug'] . '_agencies_import', true );
		}

		if ( get_option( 'shipping_zones_grouped' ) && get_option( 'shipping_zones_grouped' ) == 'yes' ) {
			$this->shipping_zone_grouped( $all_results, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled );
		} else {
			$this->shipping_zone_ungrouped( $all_results,
				$flat_rate_label,
				$pickup_local_label,
				$flat_rate_enabled,
				$pickup_local_enabled,
				$agency_import_enabled,
			);
		}


		wp_send_json_success(
			array(
				'message' => 'success',
			)
		);
	}

	/**
	 * Configure shipping zones with grouped pricing.
	 *
	 * Creates or updates shipping zones with grouped pricing structure.
	 * This is a placeholder method for future implementation.
	 *
	 * @param array $livraison Delivery pricing data.
	 * @param string $flat_rate_label Label for flat rate shipping.
	 * @param string $pickup_local_label Label for local pickup shipping.
	 * @param bool $flat_rate_enabled Whether flat rate shipping is enabled.
	 * @param bool $pickup_local_enabled Whether local pickup is enabled.
	 *
	 * @return void
	 */
	private function shipping_zone_grouped( $livraison, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled ) {

	}

	/**
	 * Configure shipping zones with ungrouped pricing.
	 *
	 * Creates or updates shipping zones based on individual wilaya (province) rates.
	 * Configures both home delivery and pickup options per wilaya.
	 *
	 * @param array $livraison Delivery pricing data.
	 * @param string $flat_rate_label Label for flat rate shipping.
	 * @param string $pickup_local_label Label for local pickup shipping.
	 * @param bool $flat_rate_enabled Whether flat rate shipping is enabled.
	 * @param bool $pickup_local_enabled Whether local pickup is enabled.
	 *
	 * @return void
	 */
	private function shipping_zone_ungrouped(
		$livraison,
		$flat_rate_label,
		$pickup_local_label,
		$flat_rate_enabled,
		$pickup_local_enabled,
		$agency_import_enabled
	) {

		// Set memory limit to -1 (unlimited) and set time limit to 0 (unlimited)
		ini_set( 'memory_limit', '-1' );
		set_time_limit( 0 );

		$zones = [];

		$shipping_method_local_pickup = null;
		$shipping_method_flat_rate    = null;

		$i = 1;
		foreach ( $livraison as $key => $wilaya ) {

			$priceStopDesk = $wilaya['stopdesk_fee'] ?? 0;
			$priceFlatRate = $wilaya['home_fee'] ?? 0;
			$wilaya_name   = $wilaya['state_name'];

			// Get all shipping zones
			$zones = WC_Shipping_Zones::get_zones();

			// Iterate through the zones and find the one with the matching name
			$found_zone = null;
			foreach ( $zones as $zone ) {
				if ( $zone['zone_locations'][0]->code == 'DZ:DZ-' . str_pad( $wilaya['state_code'], 2, '0', STR_PAD_LEFT ) ) {
					$found_zone = $zone;
					break;
				}
			}

			if ( ! $found_zone ) {
				$zone = new WC_Shipping_Zone();
				$zone->set_zone_name( ucfirst( Helpers::normalizeCityName( $wilaya_name ) ) );
				$zone->set_zone_order( $key );
				$zone->add_location( 'DZ:DZ-' . str_pad( $wilaya['state_code'], 2, '0', STR_PAD_LEFT ), 'state' );
				$zone->save();
			} else {
				$zone = WC_Shipping_Zones::get_zone( $found_zone['id'] );
			}

			if ( $priceFlatRate && $flat_rate_enabled && $priceFlatRate > 0 ) {

				$flat_rate_name = 'flat_rate_yalitec_new';
				if ( is_hanout_enabled() ) {
					$flat_rate_name = 'flat_rate';
				}

				$instance_flat_rate                      = $zone->add_shipping_method( $flat_rate_name );
				$shipping_method_flat_rate               = WC_Shipping_Zones::get_shipping_method( $instance_flat_rate );
				$shipping_method_configuration_flat_rate = [
					'woocommerce_' . $flat_rate_name . '_title'         => $flat_rate_label ?? __( 'Flat rate', 'woo-bordereau-generator' ),
					'woocommerce_' . $flat_rate_name . '_cost'          => $priceFlatRate,
					'woocommerce_' . $flat_rate_name . '_type'          => 'class',
					'woocommerce_' . $flat_rate_name . '_delivery_type' => 'Express',
					'woocommerce_' . $flat_rate_name . '_provider'      => $this->provider['slug'],
					'instance_id'                                       => $instance_flat_rate,
					'method_id'                                         => $this->provider['slug'] . '_flat_rate',
				];


				$_REQUEST['instance_id'] = $instance_flat_rate;
				$shipping_method_flat_rate->set_post_data( $shipping_method_configuration_flat_rate );
				$shipping_method_flat_rate->update_option( 'provider', $this->provider['slug'] );
				$shipping_method_flat_rate->process_admin_options();

			}

			if ( $priceStopDesk && $pickup_local_enabled && $priceStopDesk > 0 ) {

				if ( $agency_import_enabled ) {

					$agencies = $this->get_all_centers();

					$agenciesInWilaya = array_filter( $agencies, function ( $item ) use ( $wilaya ) {
						return $item['wilaya_name'] == $wilaya['wilaya_name'];
					} );

					foreach ( $agenciesInWilaya as $agency ) {

						$local_pickup_name = 'local_pickup_yalitec_new';
						if ( is_hanout_enabled() ) {
							$local_pickup_name = 'local_pickup';
						}

						$price_local_pickup = $wilaya['desk'];

						if ( $price_local_pickup ) {


							$name = sprintf( "%s %s", $pickup_local_label ?? __( 'Local Pickup', 'woo-bordereau-generator' ), $agency['name'] );

							$instance_local_pickup                      = $zone->add_shipping_method( $local_pickup_name );
							$shipping_method_local_pickup               = WC_Shipping_Zones::get_shipping_method( $instance_local_pickup );
							$shipping_method_configuration_local_pickup = array(
								'woocommerce_' . $local_pickup_name . '_title'     => $name,
								'woocommerce_' . $local_pickup_name . '_cost'      => $price_local_pickup,
								'woocommerce_' . $local_pickup_name . '_type'      => 'class',
								'woocommerce_' . $local_pickup_name . '_address'   => $agency['address'],
								'woocommerce_' . $local_pickup_name . '_center_id' => (int) $agency['id'],
								'woocommerce_' . $local_pickup_name . '_provider'  => $this->provider['slug'],
								'instance_id'                                      => $instance_local_pickup,
							);

							$_REQUEST['instance_id'] = $instance_local_pickup;
							$shipping_method_local_pickup->set_post_data( $shipping_method_configuration_local_pickup );
							$shipping_method_local_pickup->process_admin_options();
						}

					}
				} else {
					$local_pickup_name = 'local_pickup_yalitec_new';

					if ( is_hanout_enabled() ) {
						$local_pickup_name = 'local_pickup';
					}

					$instance_local_pickup                      = $zone->add_shipping_method( $local_pickup_name );
					$shipping_method_local_pickup               = WC_Shipping_Zones::get_shipping_method( $instance_local_pickup );
					$shipping_method_configuration_local_pickup = array(
						'woocommerce_' . $local_pickup_name . '_title'    => $pickup_local_label ?? __( 'Local Pickup', 'woo-bordereau-generator' ),
						'woocommerce_' . $local_pickup_name . '_cost'     => $priceStopDesk,
						'woocommerce_' . $local_pickup_name . '_type'     => 'class',
						'woocommerce_' . $local_pickup_name . '_provider' => $this->provider['slug'],
						'instance_id'                                     => $instance_local_pickup,
					);

					$_REQUEST['instance_id'] = $instance_local_pickup;
					$shipping_method_local_pickup->set_post_data( $shipping_method_configuration_local_pickup );
					$shipping_method_local_pickup->update_option( 'provider', $this->provider['slug'] );
					$shipping_method_local_pickup->process_admin_options();
				}
			}
		}

		return [ $shipping_method_flat_rate, $shipping_method_local_pickup ];
	}


	/**
	 * Add orders to Yalitec in bulk.
	 *
	 * Creates packages for multiple orders in a single API request.
	 *
	 * @param array $order_ids Array of order IDs to add.
	 *
	 * @return mixed|void
	 */
	public function bulk_add_orders( $order_ids ) {

		$data = [];

		$orders = array();
		foreach ( $order_ids as $id ) {
			$order = wc_get_order( $id );
			if ( $order ) {
				$orders[] = $order;
			}
		}

		$key   = $fields['total']['choices'][0]['name'] ?? null;
		$total = get_option( $key ) ? get_option( $key ) : 'without-shipping';

		$wilayas = $this->get_wilayas();
		$shipping = new Yalitec($order, $this->provider);

		foreach ( $orders as $order ) {

			// YALITEC

			$params = $order->get_data();
			$orderId = $order->get_id();

			$wilayaId = (int) str_replace( "DZ-", "", $params['billing']['state'] );
			$wilaya   = array_search( $wilayaId, array_column( $wilayas, 'wilaya_id' ) );


			if ( $wilaya === null ) {
				wp_send_json( [
					'error'   => true,
					'message' => __( "Wilaya has not been selected", 'woo-bordereau-generator' )
				], 422 );
			}

			$communeId = 0;
			$commueName = null;

			if ( isset( $params['billing']['city'] ) && empty( $communeId ) ) {
				$commnes = $this->get_communes( $wilayaId, false );
				$key     = array_search( $params['billing']['city'], array_column( $commnes, "name" ) );
				if ( $key ) {
					$communeId = $commnes[ $key ]['code'];
				}
			}

			$products = [];
			$i        = 0;


			// create the products
			foreach ( $order->get_items() as $item_key => $item ) {

				$item_data            = $item->get_data();
				$product_id           = $item->get_product_id();
				$product_variation_id = $item->get_variation_id();
				$_id                  = $product_id;

				/** @var \WC_Product_Simple $product */
				$product = wc_get_product( $_id );

			$product_tracking_id = get_post_meta( $_id, '_yalitec_product_id', true );
			if ( empty( $product_tracking_id ) ) {
				// Backward compatibility: check legacy meta key without underscore prefix
				$product_tracking_id = get_post_meta( $_id, 'yalitec_product_id', true );
				if ( ! empty( $product_tracking_id ) ) {
					update_post_meta( $_id, '_yalitec_product_id', $product_tracking_id );
				}
			}

			if ( empty( $product_tracking_id ) ) {

				// Search Yalitec by SKU before creating a new product
				$search_params = [
					'filters' => [
						'countryId' => ['DZA'],
						'storeId'   => [$this->store_id],
						'search'    => $product->get_sku(),
					],
					'pagination' => [ 'page' => 1 ],
				];
				$search_request = wp_safe_remote_post(
					$this->provider['api_url'] . '/products/search',
					[
						'headers'     => [
							'Content-Type' => 'application/json',
							'X-API-KEY'    => $this->api_key
						],
						'body'        => wp_json_encode( $search_params ),
						'method'      => 'POST',
						'data_format' => 'body',
						'timeout'     => 15
					]
				);
				if ( ! is_wp_error( $search_request ) ) {
					$search_body     = wp_remote_retrieve_body( $search_request );
					$search_response = json_decode( $search_body, true );
					if ( ! empty( $search_response['list'] ) ) {
						$found_product = reset( $search_response['list'] );
						if ( $found_product && ! empty( $found_product['trackingId'] ) ) {
							$product_tracking_id = $found_product['trackingId'];
							update_post_meta( $product->get_id(), '_yalitec_product_id', $product_tracking_id );
						}
					}
				}
			}

			if ( empty( $product_tracking_id ) ) {

				$url = $this->provider['api_url'] . "/products";

					$data = [
						'storeId'             => $this->store_id,
						'name'                => $product->get_name(),
						'description'         => $product->get_name(),
						'categoryId'          => '65f1be1037fcc18aaaffd516',
						'isFulfilledByUs'     => $this->is_fulfilement,
						'pricing[selling]'    => $product->get_price(),
						'pricing[purchasing]' => 0,
						'sku'                 => $product->get_sku(),
						'images'              => [],
						'creativeLink'        => '',
						'weight'              => $product->get_weight(),
						'size'                => $product->get_length(),
						'landingPageLink'     => $product->get_permalink(),
					];

					$curl = curl_init();

					curl_setopt_array( $curl, array(
						CURLOPT_URL            => $url,
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_ENCODING       => '',
						CURLOPT_MAXREDIRS      => 10,
						CURLOPT_TIMEOUT        => 0,
						CURLOPT_FOLLOWLOCATION => true,
						CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
						CURLOPT_CUSTOMREQUEST  => 'POST',
						CURLOPT_POSTFIELDS     => $data,
						CURLOPT_HTTPHEADER     => array(
							'X-API-KEY: ' . $this->api_key,
						),
					) );

					$response = curl_exec( $curl );
					curl_close( $curl );

					$response = json_decode( $response, true );

					if ( isset( $response['trackingId'] ) ) {
						add_post_meta( $product->get_id(), '_yalitec_product_id', $response['trackingId'], true );
						$product_tracking_id = $response['trackingId'];
					}
				}


			if ( $product_variation_id ) {
				$variation_tracking_id = get_post_meta( $product_variation_id, '_yalitec_variant_id', true );
				if ( empty( $variation_tracking_id ) ) {
					// Backward compatibility: check legacy meta key
					$variation_tracking_id = get_post_meta( $product_variation_id, 'yalitec_product_id', true );
					if ( ! empty( $variation_tracking_id ) ) {
						update_post_meta( $product_variation_id, '_yalitec_variant_id', $variation_tracking_id );
					}
				}

				/** @var \WC_Product_Variation $variation */
				$variation      = wc_get_product( $product_variation_id );
				$variation_name = $variation->get_name();
				$variation_sku  = $variation->get_sku();

				if ( empty( $variation_tracking_id ) && ! empty( $product_tracking_id ) ) {

					// FIRST: Search for existing variant by SKU in Yalitec before creating a new one
					$variants_url   = $this->provider['api_url'] . sprintf( "/products/%s/variants", $product_tracking_id );
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

						$variants_list = $search_response['list'] ?? $search_response ?? [];
						$best_match    = null;
						if ( is_array( $variants_list ) ) {
							foreach ( $variants_list as $existing_variant ) {
								if ( isset( $existing_variant['sku'] ) && $existing_variant['sku'] === $variation_sku && ! empty( $existing_variant['trackingId'] ) ) {
									if ( $best_match === null ) {
										$best_match = $existing_variant;
									} elseif ( ! empty( $existing_variant['pricing'] ) && empty( $best_match['pricing'] ) ) {
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
				if ( empty( $variation_tracking_id ) && ! empty( $product_tracking_id ) ) {
					$data = [
						'name'                => $variation_name,
						'pricing[selling]'    => $variation->get_price(),
						'pricing[purchasing]' => 0,
						'sku'                 => $variation_sku,
						'weight'              => (int) $variation->get_weight() ?? 0,
					];

					$url     = $this->provider['api_url'] . sprintf( "/products/%s/variants", $product_tracking_id );
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

				$products[ $i ]['trackingId'] = $product_tracking_id;
				$products[ $i ]['quantity']   = $item_data['quantity'];
				$products[ $i ]['price']      = (int) $item_data['total'];
				if ( ! empty( $product_variation_id ) ) {
					$products[ $i ]['variantId'] = $variation_tracking_id;
				}

				$i ++;
			}


			$phone = $params['billing']['phone'];
			$phone = str_replace( ' ', '', str_replace( '+213', '0', $phone ) );

			$note = stripslashes( get_option( 'wc_bordreau_default-shipping-note' ) );

			$free_shipping = 0;
			$stop_desk     = 0;


			if ( $order->has_shipping_method( 'free_shipping' ) ) {
				$free_shipping = 1;
			}

			if ( $order->has_shipping_method( 'local_pickup' ) || $order->has_shipping_method( 'local_pickup_yalitec_new' ) ) {
				$stop_desk = 1;
			}


			$data = [
				// Yalitec Payload
				'storeId'            => $this->store_id,
				'client'             => [
					'firstName' => ! empty( $order->get_billing_first_name() ) ? $order->get_billing_first_name() : $order->get_billing_last_name(),
					'lastName'  => ! empty( $order->get_billing_last_name() ) ? $order->get_billing_last_name() : $order->get_billing_first_name(),
					'phones'    => [
						$phone
					]
				],
				'products'           => $products,
//			'productsToCollect' => [
//				[
//					'trackingId' => 'string',
//					'quantity' => 0,
//					'variantId' => 'string'
//				]
//			],
//			'paymentMethod' => 'cod',
				'totalProductsPrice' => (int) ( $this->data['price'] ?? $params['total'] ),
				'weight'             => 0,
				'callCenter'         => $this->confirmation ? 'yalitec' : 'in-house',
				'shipping'           => [
					'company'      => 'yalidine',
					'type'         => 'express',
					'destination'  => [
						'cityId'        => $params['billing']['city'],
						'streetAddress' => $this->data['address'] ?? $params['billing']['address_1'] ?? stripslashes( $params['billing']['city'] )
					],
					'stopdesk'     => (bool) $stop_desk,
					'freeShipping' => (bool) $free_shipping
				],
				'notes'              => $note
			];

			if ( $stop_desk ) {
				$center = (int) $order->get_meta( '_billing_center_id', true );
				if ( ! $center ) {
					$center = (int) get_post_meta( $order->get_id(), '_billing_center_id', true );
				}

				$data['shipping']['stopdeskOfficeId'] = $center;
			}


			if ( ! $center && $stop_desk ) {

				$found = false;
				// check if the name is an agence check in the centers list
				$name    = $order->get_shipping_method();
				$centers = $this->get_all_centers();

				if ( is_array( $centers ) ) {
					$result = array_filter( $centers, function ( $agency ) use ( $name ) {
						$prefix     = get_option( $this->provider['slug'] . '_pickup_local_label' );
						$agencyName = sprintf( "%s %s", $prefix, $agency['name'] );

						return trim( $agencyName ) === trim( $name );
					} );
					$found  = ! empty( $result ) ? reset( $result ) : null;
				}

				if ( $found ) {
					$foundStop                                         = $found;
					$data['shipping']['stopdeskOfficeId'] = $foundStop['id'];
					$center                                            = $foundStop['id'];
					$commune                                           = $foundStop['commune_id'];

					if ( is_numeric( $commune ) ) {
						$communes     = $shipping->get_communes( $wilayaId, $stop_desk );
						$foundCommune = array_filter( $communes, function ( $w ) use ( $commune ) {
							return $w['id'] == $commune;
						} );
						if ( count( $foundCommune ) ) {
							$foundCommune    = reset( $foundCommune );
							$commune         = $foundCommune['name'];
							$data['shipping']['destination']['cityId'] = $commune;
						}
					} else {
						$data['shipping']['destination']['cityId'] = $foundStop['commune_id'];
					}
				}
			}

			if ( ! $center && $stop_desk ) {
				$centers = $this->get_all_centers();
				if ( is_array( $centers ) ) {
					$result = array_filter( $centers, function ( $agency ) use ( $commune ) {
						return $agency['commune_id'] == $commune;
					} );

					$found = ! empty( $result ) ? reset( $result ) : null;

					$data['shipping']['stopdeskOfficeId'] = $found['id'];
					$center            = $found['id'];
				}
			}

			if ( isset( $this->data['is_update'] ) ) {
				if ( $this->confirmation ) {
					$url = $this->provider['api_url'] . "/orders/" . $this->data['tracking_number'];
				} else {
					$url = $this->provider['api_url'] . "/orders/confirmed/" . $this->data['tracking_number'];
				}
			} else {
				if ( $this->confirmation ) {
					$url = $this->provider['api_url'] . "/orders";
				} else {
					$url = $this->provider['api_url'] . "/orders/confirmed";
				}
			}


			$data['shipping']['stopdeskOfficeId'] = (string) $data['shipping']['stopdeskOfficeId'];

			$request = wp_safe_remote_post(
				$url,
				[
					'headers'     => [
						'Content-Type' => 'application/json',
						'X-API-KEY'    => $this->api_key
					],
					'body'        => wp_json_encode( $data ),
					'method'      => isset( $this->data['is_update'] ) ? 'PATCH' : 'POST',
					'data_format' => 'body',
				]
			);

			if ( is_wp_error( $request ) ) {
				wp_send_json( [
					'error'   => true,
					'message' => __( "An error has occurred. Please see log file ", 'woo-bordereau-generator' )
				], 401 );
			}

			$body = wp_remote_retrieve_body( $request );


			if ( empty( $body ) ) {
				wp_send_json( [
					'error'   => true,
					'message' => __( "An error has occurred. Please see log file ", 'woo-bordereau-generator' )
				], 401 );
			}

			$response = json_decode( $body, true );

			if ( empty( $response ) || ! is_array( $response ) ) {
				wp_send_json( [
					'error'   => true,
					'message' => __( "An error has occurred. Please see log file ", 'woo-bordereau-generator' )
				], 401 );
			}

			// check if the there is no error
			if ( isset( $response['error'] ) ) {
				$error_message = is_array( $response['error'] )
					? ( $response['error']['message'] ?? wp_json_encode( $response['error'] ) )
					: $response['error'];
				error_log( 'Yalitec API Error: ' . $error_message );
				wp_send_json( [
					'error'   => true,
					'message' => __( "An error has occurred. Please see log file ", 'woo-bordereau-generator' )
				], 401 );
			}


			if ( isset( $this->data['is_update'] ) ) {
				$response['tracking'] = $this->data['tracking_number'];
			}

			$label = get_rest_url(null, 'woo-bordereau/v1/slip/print/'.$this->provider['slug'].'/'.$orderId);

			$trackingNumber = $response['trackingId'];

			update_post_meta( $order->get_id(), '_shipping_tracking_number', wc_clean( $trackingNumber ) ); // backwork compatibilite
			update_post_meta( $order->get_id(), '_shipping_tracking_label', wc_clean( $label ) );
			update_post_meta( $order->get_id(), '_shipping_label', wc_clean( $label ) );
			update_post_meta( $order->get_id(), '_shipping_tracking_method', $this->provider['slug'] );

			$order->update_meta_data( '_shipping_tracking_number', wc_clean( $trackingNumber ) );
			$order->update_meta_data( '_shipping_tracking_label', wc_clean( $label ) );
			$order->update_meta_data( '_shipping_label', wc_clean( $label ) );
			$order->update_meta_data( '_shipping_tracking_method', $this->provider['slug'] );
			$order->save();

			Helpers::make_note( $order, $trackingNumber, $this->provider['name'] );

		}
	}

	/**
	 * Get wilayas (provinces) data.
	 *
	 * Retrieves a list of wilayas from the Yalitec API or a cached file.
	 *
	 * @return array Wilayas data.
	 */
	public function get_wilayas(): array {
		$path = Functions::get_path( $this->provider['slug'] . '_wilaya.json' );

		if ( ! file_exists( $path ) ) {

			$request = new \WooBordereauGenerator\Admin\Shipping\YalitecRequest( $this->provider );
			$content = $request->get( $this->provider['api_url'] . '/geo/states?perPage=100' );

			file_put_contents( $path, json_encode( $content ) );
		}

		return json_decode( file_get_contents( $path ), true );
	}

	/**
	 * Get communes (cities) data for a wilaya.
	 *
	 * Retrieves a list of communes for a given wilaya from the Yalitec API or a cached file.
	 *
	 * @param int $wilaya_id Wilaya ID.
	 * @param bool $hasStopDesk Whether to include stop desk data.
	 *
	 * @return array Communes data.
	 */
	public function get_communes( $wilaya_id, bool $hasStopDesk ) {

		$wilaya_id = (int) str_replace( 'DZ-', '', $wilaya_id );

		$wilayaId = sprintf( 'DZA%02d', $wilaya_id );

		// Get communes data with caching
		$communes_path = Functions::get_path( $this->provider['slug'] . '_communes.json' );

		if ( ! file_exists( $communes_path ) ) {
			$url            = $this->provider['api_url'] . '/geo/cities?stateId%5C[%5C]=' . $wilayaId . '&perPage=2000';
			$request        = new YalitecRequest( $this->provider );
			$response_array = $request->get( $url );

			file_put_contents( $communes_path, json_encode( $response_array ) );
		}
		$communes_data = json_decode( file_get_contents( $communes_path ), true );


		// Filter communes by wilaya_id
		$communes = [];
		if ( isset( $communes_data ) && is_array( $communes_data ) ) {
			$communes = array_filter( $communes_data, function ( $item ) use ( $wilayaId ) {
				return $item['stateId'] === $wilayaId;
			} );
		}

		$centeres_path = Functions::get_path( $this->provider['slug'] . '_centers_' . WC_BORDEREAU_GENERATOR_VERSION . '_' . date( 'Y' ) . '_' . ( date( 'z' ) + 1 ) . '.json' );

		if ( ! file_exists( $centeres_path ) ) {

			$url       = $this->provider['api_url'] . '/shipping/companies/yalidine/offices?country\[\]=DZA&perPage=1000';
			$request   = new YalitecRequest( $this->provider );
			$stopDesks = $request->get( $url );

			file_put_contents( $centeres_path, json_encode( $stopDesks ) );
		}

		$stopDesks = json_decode( file_get_contents( $centeres_path ), true );


		// Format the communes with stop desk information
		$stopDesksList  = $stopDesks['list'] ?? [];
		$communesResult = [];

		foreach ( $communes as $commune ) {
			// Match by cityId (more reliable than name comparison)
			$matchedStopDesks = array_filter( $stopDesksList, function ( $item ) use ( $commune ) {
				return $item['address']['cityId'] === $commune['id'];
			} );

			$has_stop_desk = count( $matchedStopDesks ) > 0;

			// If hasStopDesk filter is enabled, only include communes that have stop desks
			if ( $hasStopDesk && ! $has_stop_desk ) {
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
	 * Get communes (cities) data for a wilaya.
	 *
	 * Retrieves a list of communes for a given wilaya from the Yalitec API or a cached file.
	 *
	 * @param int $wilaya_id Wilaya ID.
	 * @param bool $hasStopDesk Whether to include stop desk data.
	 *
	 * @return array Communes data.
	 */
	public function get_all_communes() {

		// Get communes data with caching
		$communes_path = Functions::get_path( 'zimoexpress_communes.json' );

		if ( ! file_exists( $communes_path ) ) {
			$url            = $this->provider['api_url'] . '/api/v1/helpers/communes';
			$request        = new YalitecRequest( $this->provider );
			$response_array = $request->get( $url );
			file_put_contents( $communes_path, json_encode( $response_array ) );
		}

		return json_decode( file_get_contents( $communes_path ), true );
	}

	/**
	 * Get status labels.
	 *
	 * Returns an array of status codes and their corresponding labels.
	 *
	 * @return array Status codes and labels.
	 */
	public function get_status() {
		return $this->status();
	}

	private function get_fees( $wilaya_id = null ) {

		$all_results = [];


		$store_state = get_option( $this->provider['slug'] . '_wilaya_from' );

		if ( empty( $store_state ) ) {
			$store_state = (int) str_replace( 'DZ-', '', WC()->countries->get_base_state() );
		}

		//  GET /api/shipping/companies/{company}/pricing/zones
		//  GET /api/shipping/companies/{company}/pricing/zones/standard/states

		$wilayaId = sprintf( 'DZA%02d', $store_state );

		$path = Functions::get_path( sprintf( "%s_fees_DZ_%s.json", $this->provider['slug'], $wilayaId ) );

		if ( ! empty( $wilaya_id ) ) {
			$path = Functions::get_path( sprintf( "%s_fees_DZ%s.json", $this->provider['slug'], $wilaya_id ) );
		}

		if ( file_exists( $path ) ) {
			$all_results = json_decode( file_get_contents( $path ), true );
		} else {

			$url = $this->provider['api_url'] . "/shipping/companies/yalidine/pricing/zones/standard/states?startingState=" . $wilayaId;

			try {
				$request        = new YalitecRequest( $this->provider );
				$response_array = $request->get( $url, false );

				$zones = $response_array['attributedStates'];

				$url            = $this->provider['api_url'] . "/shipping/companies/yalidine/pricing/zones?country=DZA";
				$request        = new YalitecRequest( $this->provider );
				$response_array = $request->get( $url, false );

				$fees = $response_array['list'];

				if ( count( $zones ) && count( $fees ) ) {

					$zonePricing = [];
					foreach ( $fees as $zone ) {
						$zonePricing[ $zone['id'] ] = [
							'name'     => $zone['name'],
							'home'     => $zone['specs']['express']['pricing']['home'],
							'stopdesk' => $zone['specs']['express']['pricing']['stopdesk'],
							'delay'    => $zone['specs']['express']['daysOfDelay'],
						];
					}

					$shippingRates = [];
					foreach ( $zones as $zoneGroup ) {

						$zoneId  = $zoneGroup['zone']['id'];
						$pricing = $zonePricing[ $zoneId ] ?? null;


						if ( ! $pricing ) {
							continue;
						}

						foreach ( $zoneGroup['states'] as $state ) {
							$stateCode                   = (int) $state['code'];
							$shippingRates[ $stateCode ] = [
								'state_name'   => $state['name'],
								'state_code'   => $state['code'],
								'zone_name'    => $pricing['name'],
								'home_fee'     => $pricing['home'],
								'stopdesk_fee' => $pricing['stopdesk'],
								'delay_days'   => $pricing['delay'],
							];
						}
					}

					// Save the formatted data to cache file
					file_put_contents( $path, json_encode( $shippingRates ) );

					$all_results = $shippingRates;
				} else {
					error_log( 'Yalitec API returned invalid data format for fees' );
				}
			} catch ( \ErrorException $exception ) {
				error_log( "Error fetching Yalitec fees: " . $exception->getMessage() );
			}
		}

		return $all_results;
	}

	/**
	 * Get all centers with caching
	 *
	 * @return array
	 * @since 4.0.0
	 */
	public function get_all_centers() {

		$centeres_path = Functions::get_path( $this->provider['slug'] . '_centers_' . WC_BORDEREAU_GENERATOR_VERSION . '_' . date( 'Y' ) . '_' . ( date( 'z' ) + 1 ) . '.json' );

		if ( ! file_exists( $centeres_path ) ) {

			$url       = $this->provider['api_url'] . '/shipping/companies/yalidine/offices?country\[\]=DZA&perPage=1000';
			$request   = new YalitecRequest( $this->provider );
			$stopDesks = $request->get( $url );

			file_put_contents( $centeres_path, json_encode( $stopDesks ) );
		}

		$stopDesks = json_decode( file_get_contents( $centeres_path ), true );

		// Format the results
		$communesResult = [];
		foreach ( $stopDesks['list'] as $i => $item ) {
			// Extract wilaya_id from stateId (format: DZA01 -> 1)
			$wilaya_id = null;
			if ( isset( $item['address']['stateId'] ) ) {
				$wilaya_id = (int) preg_replace( '/^DZA/', '', $item['address']['stateId'] );
			}

			$communesResult[] = [
				'id'           => $item['id'],
				'name'         => $item['name'],
				'commune_name' => $item['address']['cityName'],
				'wilaya_name'  => $item['address']['stateName'],
				'wilaya_id'    => $wilaya_id
			];
		}

		return $communesResult;
	}

	public function handle_webhook( $provider, $jsonData ) {

		$data     = json_decode( $jsonData, true );
		$theorder = null;
		$tracking = $data['package']['tracking_code'];

		if ( isset( $data['event'] ) ) {
			$type    = $data['event'];
			$orderId = $data['package']['order_id'] ?? null;

			if ( $orderId ) {
				$theorder = wc_get_order( $orderId );
			}

			if ( ! $theorder ) {

				$args = array(
					'limit'      => 1,
					'status'     => 'any',
					'meta_key'   => '_shipping_tracking_number',
					'meta_value' => $tracking,
					'type'       => 'shop_order',
					'return'     => 'objects',
				);

				// Perform the query
				$query  = new \WC_Order_Query( $args );
				$orders = $query->get_orders();

				// $orders will contain an array of order IDs that match the criteria
				if ( ! empty( $orders ) ) {
					$theorder = $orders[0]; // Since we limited the query to 1, take the first result
				}
			}

			if ( $type === 'package.status.changed' ) {
				$itemStatus = $data['package']['status'];

				BordereauGeneratorAdmin::update_tracking_status( $theorder, $itemStatus, 'webhook' );

				( new BordereauGeneratorAdmin( WC_BORDEREAU_POST_TYPE, WC_BORDEREAU_GENERATOR_VERSION ) )
					->update_orders_status( $theorder, $itemStatus );

				return [
					'success' => true,
					'updated' => 1
				];
			}
		}
	}


	/**
	 * @return false|void
	 */
//	public function sync_products() {
//
//		try {
//			$url = $this->provider['api_url'] . "/products/search";
//
//			$data = [
//				'filters'    => [
//					'categoryId' => [ '65f1be1037fcc18aaaffd516' ],
//				],
//				'sortBy'     => [
//					'name' => 'ASC',
//				],
//				'fields'     => [
//					'name',
//					'trackingId',
//					'variantDefinitions',
//					'isFulfilledByUs',
//					'sku'
//				],
//				'pagination' => [
//					'page'    => 0,
//					'perPage' => 1000
//				]
//			];
//
//			$request = wp_safe_remote_post(
//				$url,
//				[
//					'headers'     => [
//						'Content-Type' => 'application/json',
//						'X-API-KEY'    => $this->api_key
//					],
//					'body'        => wp_json_encode( $data ),
//					'method'      => 'POST',
//					'data_format' => 'body',
//					'timeout'     => 90
//				]
//			);
//
//
//			if ( is_wp_error( $request ) ) {
//				return false;
//			}
//
//			$body = wp_remote_retrieve_body( $request );
//			$json = json_decode( $body, true );
//
//
//			if ( isset( $json['list'] ) ) {
//
//				$pages = (int) $json['pagination']['totalPages'];
//				$this->handle_products_in_provider( $json );
//				$page = 2;
//
//
//				while ( $page <= $pages ) {
//
//					$url = $this->provider['api_url'] . "/products/search";
//
//					$data = [
//						'filters'    => [
//							'categoryId' => [ '65f1be1037fcc18aaaffd516' ],
//						],
//						'sortBy'     => [
//							'name' => 'ASC',
//						],
//						'fields'     => [
//							'name',
//							'trackingId',
//							'variantDefinitions',
//							'isFulfilledByUs',
//							'sku'
//						],
//						'pagination' => [
//							'page'    => $page,
//							'perPage' => 1000
//						]
//					];
//
//					$request = wp_safe_remote_post(
//						$url,
//						[
//							'headers'     => [
//								'Content-Type' => 'application/json',
//								'X-API-KEY'    => $this->api_key
//							],
//							'body'        => wp_json_encode( $data ),
//							'method'      => 'POST',
//							'data_format' => 'body',
//							'timeout'     => 90
//						]
//					);
//
//
//					if ( is_wp_error( $request ) ) {
//						return false;
//					}
//
//					$body = wp_remote_retrieve_body( $request );
//					$json = json_decode( $body, true );
//
//					$this->handle_products_in_provider( $json );
//
//					$page ++;
//				}
//			}
//
//			$args = [
//				'status'     => 'publish',
//				'orderby'    => 'name',
//				'order'      => 'ASC',
//				'limit'      => -1,
//				'type'       => ['simple', 'variable'],
//				'meta_query' => [
//					'relation' => 'OR',
//					[
//						'key'     => 'yalitec_product_id',
//						'compare' => 'NOT EXISTS',
//					],
//					[
//						'key'     => 'yalitec_product_id',
//						'value'   => '',
//						'compare' => '=',
//					],
//				],
//			];
//
//			$all_products = wc_get_products( $args );
//
//			foreach ( $all_products as $key => $product ) {
//
//				$product_id          = $product->get_id();
//				$product_tracking_id = get_post_meta( $product_id, 'yalitec_product_id', true );
//
//				if ( empty( $product_tracking_id ) ) {
//
//					/** @var \WC_Product_Simple $product */
//
//					$url = $this->provider['api_url'] . "/products";
//
//					$data = [
//						'storeId'             => $this->store_id,
//						'name'                => $product->get_name(),
//						'description'         => $product->get_name(),
//						'categoryId'          => '65f1be1037fcc18aaaffd516',
//						'isFulfilledByUs'     => $this->is_fulfilement,
//						'pricing[selling]'    => $product->get_price(),
//						'pricing[purchasing]' => 0,
//						'sku'                 => $product->get_sku(),
//						'images'              => [],
//						'creativeLink'        => '',
//						'weight'              => $product->get_weight(),
//						'size'                => $product->get_length(),
//						'landingPageLink'     => $product->get_permalink(),
//					];
//
//
//					$curl = curl_init();
//
//					curl_setopt_array( $curl, array(
//						CURLOPT_URL            => $url,
//						CURLOPT_RETURNTRANSFER => true,
//						CURLOPT_ENCODING       => '',
//						CURLOPT_MAXREDIRS      => 10,
//						CURLOPT_TIMEOUT        => 0,
//						CURLOPT_FOLLOWLOCATION => true,
//						CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
//						CURLOPT_CUSTOMREQUEST  => 'POST',
//						CURLOPT_POSTFIELDS     => $data,
//						CURLOPT_HTTPHEADER     => array(
//							'X-API-KEY: ' . $this->api_key,
//						),
//					) );
//
//					$response = curl_exec( $curl );
//					$response = json_decode( $response, true );
//
//					curl_close( $curl );
//
//					if ( isset( $response['trackingId'] ) ) {
//						update_post_meta( $product->get_id(), 'yalitec_product_id', $response['trackingId'] );
//						$product_tracking_id = $response['trackingId'];
//					}
//
//				}
//
//				if ( $product->get_type() == "variable" ) {
//
//					foreach ( $product->get_available_variations() as $variation ) {
//
//
//						$variation_id         = $variation['variation_id'];
//						$variation_attributes = $variation['attributes'];
//
//						$variation_name = $product->get_name() . ' - ';
//
//						foreach ( $variation_attributes as $attribute_value ) {
//							$variation_name .= urldecode( $attribute_value ) . ' ';
//						}
//
//						$id = get_post_meta( $variation_id, 'yalitec_product_id', true );
//
//
//						if ( ! $id ) {
//
//							$data = [
//								'name'                => $variation_name,
//								'pricing[selling]'    => $variation['display_price'],
//								'pricing[purchasing]' => 0,
//								'sku'                 => $variation['sku'],
//								'weight'              => (int) $variation['weight'] ?? 0,
//							];
//
//
//							$url = $this->provider['api_url'] . sprintf( "/products/%s/variants", $product_tracking_id );
//
//							$request = wp_safe_remote_post(
//								$url,
//								[
//									'headers'     => [
//										'Content-Type' => 'application/json',
//										'X-API-KEY'    => $this->api_key
//									],
//									'body'        => wp_json_encode( $data ),
//									'method'      => 'POST',
//									'data_format' => 'body',
//									'timeout'     => 15
//								]
//							);
//
//							if ( ! is_wp_error( $request ) ) {
//								$body     = wp_remote_retrieve_body( $request );
//								$response = json_decode( $body, true );
//
//								if ( ! isset( $response['status_code'] ) ) {
//									if ( isset( $response['trackingId'] ) ) {
//										add_post_meta( $product_id, 'yalitec_product_id', $response['trackingId'], true );
//									}
//								}
//							}
//						}
//					}
//				}
//			}
//
//			wp_send_json( [
//				'success' => true,
//				'message' => __( "Your products has been synced with Yalitec", 'woo-bordereau-generator' )
//			], 200 );
//
//		} catch ( \ErrorException $exception ) {
//			wp_send_json( [
//				'error'   => true,
//				'message' => $exception->getMessage()
//			], 500 );
//		}
//
//	}
//
//	private function handle_products_in_provider( $json ) {
//
//		foreach ( $json['list'] as $item ) {
//
//			$product_id = wc_get_product_id_by_sku( $item['sku'] );
//
//			if ( $product_id ) {
//
//				$product = wc_get_product( $product_id );
//
//				if ( $product ) {
//
//					$id = get_post_meta( $product_id, 'yalitec_product_id', true );
//
//					if ( empty( $id ) ) {
//						update_post_meta( $product_id, 'yalitec_product_id', $item['trackingId'] );
//					}
//				}
//			}
//		}
//	}

	public function sync_products() {
		$sync = YalitecProductSync::get_instance();
		$sync->configure($this->provider, $this->api_key, $this->store_id, $this->is_fulfilement);
		$result = $sync->start_sync();
		wp_send_json( $result );
	}

	/**
	 * Sync a single product to Yalitec
	 * Searches by SKU first, creates if not found
	 *
	 * @param int $product_id Product ID
	 * @return array Result with success status and message
	 */
	public function sync_single_product($product_id) {
		$product = wc_get_product($product_id);

		if (!$product) {
			return [
				'success' => false,
				'message' => __('Product not found', 'woo-bordereau-generator')
			];
		}

		$sku = $product->get_sku();
		if (empty($sku)) {
			return [
				'success' => false,
				'message' => __('Product has no SKU', 'woo-bordereau-generator')
			];
		}

		// Search for product by SKU in Yalitec
		$yalitec_product = $this->search_product_by_sku($sku);

		if ($yalitec_product) {
			// Product found, update meta
			update_post_meta($product_id, '_yalitec_product_id', $yalitec_product['trackingId']);
			return [
				'success' => true,
				'message' => __('Matched and synced', 'woo-bordereau-generator')
			];
		}

		// Product not found, create it
		$result = $this->create_product_in_yalitec($product);

		if ($result['success']) {
			update_post_meta($product_id, '_yalitec_product_id', $result['trackingId']);

			// Handle variations for variable products
			if ($product->is_type('variable') && isset($result['trackingId'])) {
				$this->create_variations_for_product($product, $result['trackingId']);
			}

			return [
				'success' => true,
				'message' => __('Created in Yalitec', 'woo-bordereau-generator')
			];
		}

		return $result;
	}

	/**
	 * Search for a product in Yalitec by SKU
	 *
	 * @param string $sku Product SKU
	 * @return array|null Product data if found, null otherwise
	 */
	private function search_product_by_sku($sku) {
		$url = $this->provider['api_url'] . "/products/search";

		$data = [
			'filters' => [
				'sku' => [$sku]
			],
			'fields' => ['trackingId', 'sku', 'name'],
			'pagination' => ['page' => 1, 'perPage' => 1]
		];

		$request = wp_safe_remote_post($url, [
			'headers' => [
				'Content-Type' => 'application/json',
				'X-API-KEY' => $this->api_key
			],
			'body' => wp_json_encode($data),
			'timeout' => 30
		]);

		if (is_wp_error($request)) {
			return null;
		}

		$response = json_decode(wp_remote_retrieve_body($request), true);

		if (isset($response['list']) && !empty($response['list'])) {
			return $response['list'][0];
		}

		return null;
	}

	/**
	 * Create a product in Yalitec
	 *
	 * @param WC_Product $product Product object
	 * @return array Result with success status and message/trackingId
	 */
	private function create_product_in_yalitec($product) {
		$url = $this->provider['api_url'] . "/products";

		$data = [
			'storeId' => $this->store_id,
			'name' => $product->get_name(),
			'description' => $product->get_name(),
			'categoryId' => '65f1be1037fcc18aaaffd516',
			'isFulfilledByUs' => $this->is_fulfilement,
			'pricing[selling]' => $product->get_price(),
			'pricing[purchasing]' => 0,
			'sku' => $product->get_sku(),
			'images' => [],
			'creativeLink' => '',
			'weight' => $product->get_weight(),
			'size' => $product->get_length(),
			'landingPageLink' => $product->get_permalink(),
		];

		$curl = curl_init();
		curl_setopt_array($curl, [
			CURLOPT_URL => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => $data,
			CURLOPT_HTTPHEADER => ['X-API-KEY: ' . $this->api_key],
		]);

		$response = json_decode(curl_exec($curl), true);
		$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);

		if (isset($response['trackingId'])) {
			return [
				'success' => true,
				'trackingId' => $response['trackingId']
			];
		}

		return [
			'success' => false,
			'message' => isset($response['message'])
				? $response['message']
				: sprintf(__('API error (code: %d)', 'woo-bordereau-generator'), $http_code)
		];
	}

	/**
	 * Create variations for a variable product in Yalitec
	 *
	 * @param WC_Product $product Variable product
	 * @param string $parent_tracking_id Parent product tracking ID in Yalitec
	 */
	private function create_variations_for_product($product, $parent_tracking_id) {
		if (!$product->is_type('variable')) {
			return;
		}

		$variations = $product->get_available_variations();

		foreach ($variations as $variation_data) {
			$variation_id = $variation_data['variation_id'];
			$variation = wc_get_product($variation_id);

			if (!$variation) {
				continue;
			}

			// Check if already synced
			$existing_id = get_post_meta($variation_id, '_yalitec_variant_id', true);
			if (!empty($existing_id)) {
				continue;
			}

			// Create variation in Yalitec
			$parent = wc_get_product($variation->get_parent_id());
			$variation_name = $parent->get_name() . ' - ' . implode(' ', $variation->get_variation_attributes());

			$data = [
				'name' => $variation_name,
				'pricing[selling]' => $variation->get_price(),
				'pricing[purchasing]' => 0,
				'sku' => $variation->get_sku(),
				'weight' => (int) $variation->get_weight(),
			];

			$url = $this->provider['api_url'] . sprintf("/products/%s/variants", $parent_tracking_id);

			$request = wp_safe_remote_post($url, [
				'headers' => [
					'Content-Type' => 'application/json',
					'X-API-KEY' => $this->api_key
				],
				'body' => wp_json_encode($data),
				'timeout' => 15
			]);

			if (!is_wp_error($request)) {
				$response = json_decode(wp_remote_retrieve_body($request), true);

				if (isset($response['trackingId'])) {
					update_post_meta($variation_id, '_yalitec_variant_id', $response['trackingId']);
				}
			}
		}
	}
}
