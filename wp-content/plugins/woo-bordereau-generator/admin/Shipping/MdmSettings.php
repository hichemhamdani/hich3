<?php

namespace WooBordereauGenerator\Admin\Shipping;

/**
 * MDM Express Settings Manager.
 *
 * This class handles the configuration settings for the MDM Express shipping provider,
 * including API settings, shipping rates, zones management, and package tracking status.
 *
 * @package    WC_Bordereau_Generator
 * @subpackage WC_Bordereau_Generator/admin/shipping
 * @author     Boukraa Mohamed <bm2ilabs@gmail.com>
 */


use WC_Shipping_Zone;
use WC_Shipping_Zones;
use WooBordereauGenerator\Admin\BordereauGeneratorAdmin;
use WooBordereauGenerator\Admin\Products\MdmProductSync;
use WooBordereauGenerator\Functions;
use WooBordereauGenerator\Helpers;
use WP_Error;

/**
 * MDM Express settings management class.
 *
 * Manages the settings and configuration for the MDM Express shipping provider,
 * including API credentials, shipping zones, and bulk tracking.
 */
class MdmSettings {
	/**
	 * Provider configuration data.
	 *
	 * @var array
	 */
	private $provider;

	/**
	 * MDM Express API key.
	 *
	 * @var string|null
	 */
	protected $api_key;

	/**
	 * MDM Express store ID.
	 *
	 * @var string|null
	 */
	private $store_id;

	/**
	 * @var bool
	 */
	private bool $is_fulfilement;
	private bool $confirmation;

	/**
	 * Default MDM product category ID.
	 */
	private const DEFAULT_CATEGORY_ID = '6571f847a1672bea6ea84b49';

	/**
	 * Constructor.
	 *
	 * Initializes a new MDM Express settings manager with the provider configuration.
	 *
	 * @param array $provider The provider configuration array.
	 */
	public function __construct( array $provider ) {

		$this->api_key        = get_option( $provider['slug'] . '_api_key' );
		$this->store_id       = get_option( $provider['slug'] . '_store_id' );
		$this->is_fulfilement = get_option( $provider['slug'] . '_fulfilled' ) === 'yes';
		$this->confirmation   = get_option( $provider['slug'] . '_confirmation' ) === 'with-confirmation';
		$this->provider       = $provider;

		// Auto-detect store ID if a seller ID was entered by mistake
		if ( ! empty( $this->api_key ) && ( empty( $this->store_id ) || str_starts_with( $this->store_id, 'SLR-' ) ) ) {
			$this->auto_detect_store_id();
		}
	}

	/**
	 * Auto-detect the DZA store ID from the API if not set or if seller ID was entered.
	 */
	private function auto_detect_store_id(): void
	{
		$url     = $this->provider['api_url'] . '/api/stores';
		$request = new MdmRequest( $this->provider );
		$result  = $request->get( $url );

		if ( ! empty( $result['list'] ) ) {
			foreach ( $result['list'] as $store ) {
				if ( isset( $store['country']['id'] ) && $store['country']['id'] === 'DZA' ) {
					$this->store_id = $store['trackingId'];
					update_option( $this->provider['slug'] . '_store_id', $this->store_id );
					break;
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
	 *
	 * @param \WC_Product $product
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
	 * Get the provider settings configuration.
	 *
	 * Returns an array of settings fields for the MDM Express provider
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
	 * MDM Express doesn't have a bulk status endpoint, so we iterate
	 * and call the status-history endpoint for each tracking ID.
	 *
	 * @param array $codes Array of tracking codes to check.
	 *
	 * @return array|false Formatted tracking statuses or false on failure.
	 */
	public function bulk_track( array $codes ) {
		$request = new MdmRequest( $this->provider );
		$final   = [];

		foreach ( $codes as $trackingId ) {
			$url     = $this->provider['api_url'] . '/api/v2/shipping/parcels/' . $trackingId . '/status-history';
			$content = $request->get( $url, false );

			if ( $content && is_array( $content ) ) {
				// Get the latest status from the status history
				$latest_status = null;
				if ( ! empty( $content ) ) {
					// Status history is typically sorted, get the last entry
					$last_entry    = end( $content );
					$latest_status = $last_entry['status'] ?? null;
				}
				if ( $latest_status ) {
					$final[ $trackingId ] = $latest_status;
				}
			}
		}

		return $final;
	}

	/**
	 * Get default status labels for MDM Express.
	 *
	 * Returns an array of status codes and their corresponding labels
	 * for use in tracking display and status management.
	 *
	 * @return array Status codes and labels.
	 */
	private function status(): array {

		return [
			'out-of-stock'            => 'Out of Stock',
			'preparing'               => 'Preparing',
			'received-by-mdm'        => 'Received by MDM',
			'packaged'                => 'Packaged',
			'dispatched'              => 'Dispatched',
			'received'                => 'Received',
			'in-transit'              => 'In Transit',
			'ready-for-delivery'      => 'Ready for Delivery',
			'out-for-delivery'        => 'Out for Delivery',
			'postponed'               => 'Postponed',
			'delivered'               => 'Delivered',
			'delivered-partially'     => 'Delivered Partially',
			'delivery-attempt-failed' => 'Delivery Attempt Failed',
			'delivery-failed'         => 'Delivery Failed',
			'returning'               => 'Returning',
			'return-ready'            => 'Return Ready',
			'return-received'         => 'Return Received',
			'return-grouped'          => 'Return Grouped',
			'returned'                => 'Returned',
			'refund-pending'          => 'Refund Pending',
			'refund-collected'        => 'Refund Collected',
			'exchange-pending'        => 'Exchange Pending',
			'exchange-failed'         => 'Exchange Failed',
			'exchange-collected'      => 'Exchange Collected',
			'refunded'                => 'Refunded',
			'refunded-partially'      => 'Refunded Partially',
			'lost'                    => 'Lost',
		];
	}


	/**
	 * Import shipping classes from MDM Express.
	 *
	 * Configures WooCommerce shipping methods based on MDM Express data.
	 *
	 * @param string $flat_rate_label Label for flat rate shipping.
	 * @param string $pickup_local_label Label for local pickup shipping.
	 * @param bool $flat_rate_enabled Whether flat rate shipping is enabled.
	 * @param bool $pickup_local_enabled Whether local pickup is enabled.
	 * @param bool $agency_import_enabled Whether agency import is enabled (optional).
	 *
	 * @return void
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

		// Delete cached fees file to force fresh API call
		$fees_cache = Functions::get_path( sprintf( "%s_fees_DZ.json", $this->provider['slug'] ) );
		if ( file_exists( $fees_cache ) ) {
			unlink( $fees_cache );
		}

		$all_results = $this->get_fees();

		if ( empty( $all_results ) ) {
			wp_send_json( [
				'error'   => true,
				'message' => __( 'Could not fetch delivery fees from MDM Express. Please check your API key.', 'woo-bordereau-generator' ),
			], 400 );
		}

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
	 * Placeholder method for future implementation.
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
	 * @param bool $agency_import_enabled Whether agency import is enabled.
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

				$flat_rate_name = 'flat_rate_mdm';
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
						return $item['wilaya_name'] == ( $wilaya['state_name'] ?? $wilaya['wilaya_name'] ?? '' );
					} );

					foreach ( $agenciesInWilaya as $agency ) {

						$local_pickup_name = 'local_pickup_mdm';
						if ( is_hanout_enabled() ) {
							$local_pickup_name = 'local_pickup';
						}

						$price_local_pickup = $wilaya['stopdesk_fee'];

						if ( $price_local_pickup ) {


							$name = sprintf( "%s %s", $pickup_local_label ?? __( 'Local Pickup', 'woo-bordereau-generator' ), $agency['name'] );

							$instance_local_pickup                      = $zone->add_shipping_method( $local_pickup_name );
							$shipping_method_local_pickup               = WC_Shipping_Zones::get_shipping_method( $instance_local_pickup );
							$shipping_method_configuration_local_pickup = array(
								'woocommerce_' . $local_pickup_name . '_title'     => $name,
								'woocommerce_' . $local_pickup_name . '_cost'      => $price_local_pickup,
								'woocommerce_' . $local_pickup_name . '_type'      => 'class',
								'woocommerce_' . $local_pickup_name . '_address'   => $agency['address'] ?? '',
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
					$local_pickup_name = 'local_pickup_mdm';

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
	 * Add orders to MDM Express in bulk.
	 *
	 * Creates parcels for multiple orders via the MDM Express parcels API.
	 *
	 * @param array $order_ids Array of order IDs to add.
	 *
	 * @return mixed|void
	 */
	public function bulk_add_orders( $order_ids ) {

		$orders = array();
		foreach ( $order_ids as $id ) {
			$order = wc_get_order( $id );
			if ( $order ) {
				$orders[] = $order;
			}
		}

		$total = get_option( 'mdm_total' ) ?: 'without-shipping';

		$wilayas   = $this->get_wilayas();
		$note      = stripslashes( get_option( 'wc_bordreau_default-shipping-note' ) );

		// ── Phase 1: Ensure all products exist in MDM, build bulk payload ──

		$bulk_orders   = []; // flat order objects for the bulk endpoint
		$order_map     = []; // index => WC_Order, to map results back

		foreach ( $orders as $order ) {

			$params  = $order->get_data();
			$orderId = $order->get_id();

			$wilayaId = (int) str_replace( "DZ-", "", $params['billing']['state'] );
			$wilaya   = array_search( $wilayaId, array_column( $wilayas, 'wilaya_id' ) );

			if ( $wilaya === null ) {
				wp_send_json( [
					'error'   => true,
					'message' => __( "Wilaya has not been selected", 'woo-bordereau-generator' )
				], 422 );
			}

			// Resolve wilaya name for bulk endpoint (uses names, not IDs)
			$wilayaName = $wilayas[ $wilaya ]['name'] ?? '';
			$communeName = $params['billing']['city'] ?? '';

			// Resolve commune name from cache
			$commnes = $this->get_communes( $wilayaId, false );
			$key     = array_search( $params['billing']['city'], array_column( $commnes, "name" ) );
			if ( $key !== false ) {
				$communeName = $commnes[ $key ]['name'];
			}

			// Ensure products/variants exist in MDM
			$productIds = [];
			$quantities = [];

			foreach ( $order->get_items() as $item_key => $item ) {

				$item_data            = $item->get_data();
				$product_id           = $item->get_product_id();
				$product_variation_id = $item->get_variation_id();

				/** @var \WC_Product_Simple $product */
				$product = wc_get_product( $product_id );

				$product_tracking_id = get_post_meta( $product_id, '_mdm_product_id', true );

				if ( empty( $product_tracking_id ) ) {

					$url = $this->provider['api_url'] . "/api/v2/products";

					$product_data = [
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

					curl_setopt_array( $curl, array(
						CURLOPT_URL            => $url,
						CURLOPT_RETURNTRANSFER => true,
						CURLOPT_ENCODING       => '',
						CURLOPT_MAXREDIRS      => 10,
						CURLOPT_TIMEOUT        => 0,
						CURLOPT_FOLLOWLOCATION => true,
						CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
						CURLOPT_CUSTOMREQUEST  => 'POST',
						CURLOPT_POSTFIELDS     => $product_data,
						CURLOPT_HTTPHEADER     => array(
							'x-api-key: ' . $this->api_key,
						),
					) );

					$response = curl_exec( $curl );
					curl_close( $curl );

					$response = json_decode( $response, true );

					if ( isset( $response['trackingId'] ) ) {
						add_post_meta( $product->get_id(), '_mdm_product_id', $response['trackingId'], true );
						$product_tracking_id = $response['trackingId'];
					}
				}

				$variation_tracking_id = null;

				if ( $product_variation_id ) {
					$variation_tracking_id = get_post_meta( $product_variation_id, '_mdm_variant_id', true );
					/** @var \WC_Product_Variation $variation */
					$variation = wc_get_product( $product_variation_id );
					if ( empty( $variation_tracking_id ) ) {
						$variant_data = [
							'name'                => $variation->get_name(),
							'pricing[selling]'    => $variation->get_price(),
							'pricing[purchasing]' => 0,
							'sku'                 => $variation->get_sku(),
							'weight'              => (int) $variation->get_weight() ?? 0,
						];

						$url     = $this->provider['api_url'] . sprintf( "/api/v2/products/%s/variants", $product_tracking_id );
						$request = wp_safe_remote_post(
							$url,
							[
								'headers'     => [
									'Content-Type' => 'application/json',
									'x-api-key'   => $this->api_key
								],
								'body'        => wp_json_encode( $variant_data ),
								'method'      => 'POST',
								'data_format' => 'body',
								'timeout'     => 15
							]
						);

						if ( ! is_wp_error( $request ) ) {
							$body     = wp_remote_retrieve_body( $request );
							$response = json_decode( $body, true );

							if ( isset( $response['trackingId'] ) ) {
								add_post_meta( $product_variation_id, '_mdm_variant_id', $response['trackingId'], true );
								$variation_tracking_id = $response['trackingId'];
							}
						}
					}
				}

				// Bulk endpoint productId[] should use variant ID when available
				$productIds[] = ! empty( $variation_tracking_id ) ? $variation_tracking_id : $product_tracking_id;
				$quantities[] = (int) $item_data['quantity'];
			}

			$phone = $params['billing']['phone'];
			$phone = str_replace( ' ', '', str_replace( '+213', '0', $phone ) );

			$free_shipping = false;
			$stop_desk     = false;

			if ( $order->has_shipping_method( 'free_shipping' ) ) {
				$free_shipping = true;
			}

			if ( $order->has_shipping_method( 'local_pickup' ) || $order->has_shipping_method( 'local_pickup_mdm' ) ) {
				$stop_desk = true;
			}

			// Respect the total calculation setting (with/without shipping)
			if ( $total === 'without-shipping' ) {
				$totalPrice = (int) ( (float) $order->get_total() - (float) $order->get_total_shipping() );
			} else {
				$totalPrice = (int) $order->get_total();
			}

			$clientName = trim(
				( ! empty( $order->get_billing_first_name() ) ? $order->get_billing_first_name() : $order->get_billing_last_name() )
				. ' ' .
				( ! empty( $order->get_billing_last_name() ) ? $order->get_billing_last_name() : '' )
			);

			// Build the flat order object for the bulk endpoint
			$bulk_order = [
				'clientName'    => $clientName,
				'clientPhone'   => $phone,
				'state'         => $wilayaName,
				'city'          => $communeName,
				'address'       => $this->data['address'] ?? $params['billing']['address_1'] ?? stripslashes( $params['billing']['city'] ),
				'productId'     => $productIds,
				'quantity'      => $quantities,
				'totalPrice'    => $totalPrice,
				'freeShipping'  => $free_shipping,
				'isStopDesk'    => $stop_desk,
				'paymentMethod' => 'cash',
				'weight'        => (int) Helpers::get_product_weight( $order ),
				'fragile'       => false,
				'openable'      => false,
			];

			// Resolve stop desk center
			if ( $stop_desk ) {
				$center = $order->get_meta( '_billing_center_id', true );
				if ( ! $center ) {
					$center = get_post_meta( $order->get_id(), '_billing_center_id', true );
				}

				if ( ! $center ) {
					$name    = $order->get_shipping_method();
					$centers = $this->get_all_centers();

					if ( is_array( $centers ) ) {
						$result = array_filter( $centers, function ( $agency ) use ( $name ) {
							$prefix     = get_option( $this->provider['slug'] . '_pickup_local_label' );
							$agencyName = sprintf( "%s %s", $prefix, $agency['name'] );
							return trim( $agencyName ) === trim( $name );
						} );
						$found = ! empty( $result ) ? reset( $result ) : null;

						if ( $found ) {
							$center      = $found['id'];
							$communeName = $found['commune_name'] ?? $communeName;
							$bulk_order['city'] = $communeName;
						}
					}
				}

				if ( ! $center ) {
					$centers = $this->get_all_centers();
					if ( is_array( $centers ) ) {
						$result = array_filter( $centers, function ( $agency ) use ( $communeName ) {
							return ( $agency['commune_name'] ?? '' ) == $communeName;
						} );
						$found = ! empty( $result ) ? reset( $result ) : null;
						if ( $found ) {
							$center = $found['id'];
						}
					}
				}

				if ( $center ) {
					$bulk_order['stopDeskId'] = (string) $center;
				} else {
					$bulk_order['isStopDesk'] = false;
				}
			}

			$bulk_orders[] = $bulk_order;
			$order_map[]   = $order;
		}

		if ( empty( $bulk_orders ) ) {
			return;
		}

		// ── Phase 2: Send single bulk request ──

		$payload = [
			'storeId'       => $this->store_id,
			'confirmed'     => ! $this->confirmation,
			'confirmedByUs' => ! $this->confirmation,
			'orders'        => $bulk_orders,
		];

		$url = $this->provider['api_url'] . '/api/v2/orders/bulk';

		$request = wp_safe_remote_post(
			$url,
			[
				'headers'     => [
					'Content-Type' => 'application/json',
					'x-api-key'   => $this->api_key
				],
				'body'        => wp_json_encode( $payload ),
				'method'      => 'POST',
				'data_format' => 'body',
				'timeout'     => 60,
			]
		);

		if ( is_wp_error( $request ) ) {
			error_log( 'MDM Express: WP_Error during bulk order creation: ' . $request->get_error_message() );
			wp_send_json( [
				'error'   => true,
				'message' => __( "An error has occurred. Please see log file ", 'woo-bordereau-generator' )
			], 401 );
		}

		$body = wp_remote_retrieve_body( $request );

		if ( empty( $body ) ) {
			error_log( 'MDM Express: Empty response body for bulk order creation' );
			wp_send_json( [
				'error'   => true,
				'message' => __( "An error has occurred. Please see log file ", 'woo-bordereau-generator' )
			], 401 );
		}

		$response = json_decode( $body, true );

		if ( empty( $response ) || ! is_array( $response ) ) {
			error_log( 'MDM Express: Invalid JSON response for bulk: ' . $body );
			wp_send_json( [
				'error'   => true,
				'message' => __( "An error has occurred. Please see log file ", 'woo-bordereau-generator' )
			], 401 );
		}

		if ( isset( $response['error'] ) ) {
			$error_message = is_array( $response['error'] )
				? ( $response['error']['message'] ?? wp_json_encode( $response['error'] ) )
				: $response['error'];
			error_log( 'MDM Express API Error for bulk: ' . $error_message );
			wp_send_json( [
				'error'   => true,
				'message' => $error_message
			], 401 );
		}

		$results = $response['results'] ?? [];

		// ── Phase 3: Process results — resolve parcel IDs, save meta ──

		foreach ( $results as $result ) {

			// Bulk response index is 1-based
			$idx   = ( $result['index'] ?? 0 ) - 1;
			$order = $order_map[ $idx ] ?? null;

			if ( ! $order || empty( $result['success'] ) || empty( $result['trackingId'] ) ) {
				if ( $order ) {
					error_log( 'MDM Express: Bulk order failed for WC order #' . $order->get_id() . ': ' . wp_json_encode( $result ) );
				}
				continue;
			}

			$orderId    = $order->get_id();
			$mdmOrderId = $result['trackingId'];

			// Resolve the parcel trackingId (PRC-xxx) from the order detail
			$trackingNumber       = $mdmOrderId;
			$order_detail_request = new MdmRequest( $this->provider );
			$order_detail         = $order_detail_request->get(
				$this->provider['api_url'] . '/api/v2/orders/' . $mdmOrderId,
				false
			);

			if ( is_array( $order_detail ) && ! empty( $order_detail['relatives'] ) ) {
				foreach ( $order_detail['relatives'] as $relative ) {
					if ( $relative['type'] === 'parcel' && ! empty( $relative['data']['trackingId'] ) ) {
						$trackingNumber = $relative['data']['trackingId'];
						break;
					}
				}
			}

			$label       = get_rest_url( null, 'woo-bordereau/v1/slip/print/' . $this->provider['slug'] . '/' . $orderId );
			$trackingUrl = 'https://app.mdm.express/en/track/' . wc_clean( $trackingNumber );

			update_post_meta( $orderId, '_shipping_tracking_number', wc_clean( $trackingNumber ) );
			update_post_meta( $orderId, '_shipping_tracking_label', wc_clean( $label ) );
			update_post_meta( $orderId, '_shipping_label', wc_clean( $label ) );
			update_post_meta( $orderId, '_shipping_tracking_url', $trackingUrl );
			update_post_meta( $orderId, '_shipping_tracking_method', $this->provider['slug'] );
			update_post_meta( $orderId, '_mdm_order_id', wc_clean( $mdmOrderId ) );

			$order->update_meta_data( '_shipping_tracking_number', wc_clean( $trackingNumber ) );
			$order->update_meta_data( '_shipping_tracking_label', wc_clean( $label ) );
			$order->update_meta_data( '_shipping_label', wc_clean( $label ) );
			$order->update_meta_data( '_shipping_tracking_url', $trackingUrl );
			$order->update_meta_data( '_shipping_tracking_method', $this->provider['slug'] );
			$order->update_meta_data( '_mdm_order_id', wc_clean( $mdmOrderId ) );
			$order->save();

			Helpers::make_note( $order, $trackingNumber, $this->provider['name'] );
		}
	}

	/**
	 * Get wilayas (provinces) data.
	 *
	 * Retrieves a list of wilayas from the MDM Express API or a cached file.
	 *
	 * @return array Wilayas data.
	 */
	public function get_wilayas(): array {
		$path = Functions::get_path( $this->provider['slug'] . '_wilaya.json' );

		if ( ! file_exists( $path ) ) {

			$request = new MdmRequest( $this->provider );
			$content = $request->get( $this->provider['api_url'] . '/api/geo/states?countryId[]=DZA&perPage=100' );

			file_put_contents( $path, json_encode( $content ) );
		}

		return json_decode( file_get_contents( $path ), true );
	}

	/**
	 * Get communes (cities) data for a wilaya.
	 *
	 * Retrieves a list of communes for a given wilaya from the MDM Express API or a cached file.
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
		$communes_path = Functions::get_path( $this->provider['slug'] . '_communes_' . $wilayaId . '.json' );

		if ( ! file_exists( $communes_path ) ) {
			$url            = $this->provider['api_url'] . '/api/geo/cities?stateId[]=' . $wilayaId . '&perPage=500';
			$request        = new MdmRequest( $this->provider );
			$response_array = $request->get( $url );

			file_put_contents( $communes_path, json_encode( $response_array ) );
		}
		$communes_data = json_decode( file_get_contents( $communes_path ), true );


		// Filter communes by wilaya_id
		$communes = [];
		if ( isset( $communes_data ) && is_array( $communes_data ) ) {
			$communes = array_filter( $communes_data, function ( $item ) use ( $wilayaId ) {
				return ( $item['stateId'] ?? '' ) === $wilayaId;
			} );
		}

		$centeres_path = Functions::get_path( $this->provider['slug'] . '_centers_' . WC_BORDEREAU_GENERATOR_VERSION . '_' . date( 'Y' ) . '_' . ( date( 'z' ) + 1 ) . '.json' );

		if ( ! file_exists( $centeres_path ) ) {

			$url       = $this->provider['api_url'] . '/api/v2/shipping/companies/offices?countryId=DZA';
			$request   = new MdmRequest( $this->provider );
			$stopDesks = $request->get( $url );

			file_put_contents( $centeres_path, json_encode( $stopDesks ) );
		}

		$stopDesks = json_decode( file_get_contents( $centeres_path ), true );


		// Format the communes with stop desk information
		$stopDesksList  = $stopDesks['list'] ?? [];
		$communesResult = [];

		foreach ( $communes as $commune ) {
			// Match by cityId
			$matchedStopDesks = array_filter( $stopDesksList, function ( $item ) use ( $commune ) {
				return ( $item['address']['cityId'] ?? '' ) === $commune['id'];
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
	 * Get all communes data.
	 *
	 * Retrieves a list of all communes from the MDM Express API or a cached file.
	 *
	 * @return array Communes data.
	 */
	public function get_all_communes() {

		// Get communes data with caching
		$communes_path = Functions::get_path( $this->provider['slug'] . '_all_communes.json' );

		if ( ! file_exists( $communes_path ) ) {
			$url            = $this->provider['api_url'] . '/api/geo/cities?countryId[]=DZA&perPage=2000';
			$request        = new MdmRequest( $this->provider );
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

	/**
	 * Get a paginated list of parcels from the MDM Express API.
	 *
	 * Uses POST /api/v2/shipping/parcels/search which accepts
	 * filters, sorting, and pagination in the request body.
	 *
	 * @param array $queryData Query parameters (page, page_size, tracking, last_status, date_creation).
	 * @return array{items: array, total_data: int, current_page: int}
	 */
	public function get_orders( array $queryData ): array {

		$page     = (int) ( $queryData['page'] ?? 1 );
		$perPage  = (int) ( $queryData['page_size'] ?? 15 );

		$body = [
			'pagination' => [
				'page'    => $page,
				'perPage' => $perPage,
			],
			'sortBy' => [
				'createdAt' => 'DESC',
			],
		];

		$filters = [];

		// Tracking search
		if ( ! empty( $queryData['tracking'] ) ) {
			$filters['trackingId'] = [ $queryData['tracking'] ];
		}

		// Status filter
		if ( ! empty( $queryData['last_status'] ) ) {
			$filters['status'] = [ $queryData['last_status'] ];
		}

		// Date range filter  (format from table: "YYYY-MM-DD,YYYY-MM-DD")
		if ( ! empty( $queryData['date_creation'] ) ) {
			$dates = explode( ',', $queryData['date_creation'] );
			if ( count( $dates ) === 2 ) {
				$filters['createdAt'] = [
					'start' => $dates[0] . 'T00:00:00.000Z',
					'end'   => $dates[1] . 'T23:59:59.999Z',
				];
			}
		}

		if ( ! empty( $filters ) ) {
			$body['filters'] = $filters;
		}

		$url     = $this->provider['api_url'] . '/api/v2/shipping/parcels/search';
		$request = new MdmRequest( $this->provider );
		$result  = $request->post( $url, $body );

		if ( ! is_array( $result ) || ! isset( $result['list'] ) ) {
			return [ 'items' => [], 'total_data' => 0, 'current_page' => $page ];
		}

	$items = array_map( function ( $parcel ) {
		return [
			'tracking'         => $parcel['trackingId'] ?? '',
			'familyname'       => $parcel['client']['lastName'] ?? '',
			'firstname'        => $parcel['client']['firstName'] ?? '',
			'to_commune_name'  => $parcel['destinationAddress']['cityName'] ?? '',
			'to_wilaya_name'   => $parcel['destinationAddress']['stateName'] ?? '',
			'date'             => isset( $parcel['createdAt'] ) ? date( 'Y-m-d H:i', strtotime( $parcel['createdAt'] ) ) : '',
			'contact_phone'    => $parcel['client']['phone'] ?? '',
			'city'             => $parcel['destinationAddress']['cityName'] ?? '',
			'state'            => $parcel['destinationAddress']['stateName'] ?? '',
			'last_status'      => $parcel['status'] ?? '',
			'date_last_status' => isset( $parcel['statusDate'] ) ? date( 'Y-m-d H:i', strtotime( $parcel['statusDate'] ) ) : '',
			'label'            => '',
		];
	}, $result['list'] );

		return [
			'items'        => $items,
			'total_data'   => (int) ( $result['totalCount'] ?? count( $items ) ),
			'current_page' => $page,
		];
	}

	public function get_status() {
		return $this->status();
	}

	/**
	 * Get shipping fees from MDM Express.
	 *
	 * Fetches delivery fees by iterating all 58 wilayas and calling
	 * the calculate-fees endpoint for each to get home and stopdesk fees.
	 *
	 * @param int|null $wilaya_id Optional specific wilaya ID.
	 *
	 * @return array Shipping fees per state.
	 */
	private function get_fees( $wilaya_id = null ) {

		$all_results = [];

		$path = Functions::get_path( sprintf( "%s_fees_DZ.json", $this->provider['slug'] ) );

		if ( ! empty( $wilaya_id ) ) {
			$path = Functions::get_path( sprintf( "%s_fees_DZ%s.json", $this->provider['slug'], $wilaya_id ) );
		}

		if ( file_exists( $path ) ) {
			$all_results = json_decode( file_get_contents( $path ), true );
		} else {

			try {
				$request = new MdmRequest( $this->provider );

				// Get seller tracking ID from auth/me
				$me_url  = $this->provider['api_url'] . '/api/auth/me';
				$me_data = $request->get( $me_url, false );

				if ( ! $me_data || ! isset( $me_data['trackingId'] ) ) {
					error_log( 'MDM Express: Could not retrieve seller tracking ID from auth/me' );

					return $all_results;
				}

				$sellerId = $me_data['trackingId'];

				// Fetch all delivery fees in a single call via seller service-fees endpoint
				$fees_url  = $this->provider['api_url'] . '/api/sellers/' . $sellerId . '/service-fees?countryId=DZA';
				$fees_data = $request->get( $fees_url, false );

				if ( ! $fees_data || ! isset( $fees_data['shipping']['deliveryFees'] ) ) {
					error_log( 'MDM Express: Could not retrieve delivery fees from service-fees endpoint' );

					return $all_results;
				}

				$shippingRates = [];

				foreach ( $fees_data['shipping']['deliveryFees'] as $fee ) {
					$stateCode   = (int) ( $fee['state']['code'] ?? 0 );
					$stateName   = $fee['state']['name'] ?? '';
					$homeFee     = $fee['home'] ?? 0;
					$stopdeskFee = $fee['stopdesk'] ?? 0;

					if ( ! $stateCode ) {
						continue;
					}

					$shippingRates[ $stateCode ] = [
						'state_name'   => $stateName,
						'state_code'   => $stateCode,
						'home_fee'     => $homeFee,
						'stopdesk_fee' => $stopdeskFee,
					];
				}

				// Save the formatted data to cache file
				file_put_contents( $path, json_encode( $shippingRates ) );

				$all_results = $shippingRates;

			} catch ( \Exception $exception ) {
				error_log( "Error fetching MDM Express fees: " . $exception->getMessage() );
			}
		}

		return $all_results;
	}

	/**
	 * Get all centers (offices) with caching.
	 *
	 * Retrieves all MDM Express office locations.
	 *
	 * @return array
	 */
	public function get_all_centers() {

		$centeres_path = Functions::get_path( $this->provider['slug'] . '_centers_' . WC_BORDEREAU_GENERATOR_VERSION . '_' . date( 'Y' ) . '_' . ( date( 'z' ) + 1 ) . '.json' );

		if ( ! file_exists( $centeres_path ) ) {

			$url       = $this->provider['api_url'] . '/api/v2/shipping/companies/offices?countryId=DZA';
			$request   = new MdmRequest( $this->provider );
			$stopDesks = $request->get( $url );

			file_put_contents( $centeres_path, json_encode( $stopDesks ) );
		}

		$stopDesks = json_decode( file_get_contents( $centeres_path ), true );

		// Format the results
		$communesResult = [];
		$list           = $stopDesks['list'] ?? [];
		foreach ( $list as $i => $item ) {
			// Extract wilaya_id from stateId (format: DZA01 -> 1)
			$wilaya_id = null;
			if ( isset( $item['address']['stateId'] ) ) {
				$wilaya_id = (int) preg_replace( '/^DZA/', '', $item['address']['stateId'] );
			}

			$communesResult[] = [
				'id'           => $item['id'],
				'name'         => $item['name'],
				'commune_name' => $item['address']['cityName'] ?? '',
				'wilaya_name'  => $item['address']['stateName'] ?? '',
				'wilaya_id'    => $wilaya_id,
				'address'      => $item['address']['streetAddress'] ?? '',
			];
		}

		return $communesResult;
	}

	/**
	 * Handle incoming webhook.
	 *
	 * MDM Express doesn't use webhooks, so this method returns null.
	 *
	 * @param mixed $provider Provider data.
	 * @param string $jsonData JSON-encoded webhook payload.
	 *
	 * @return null
	 */
	public function handle_webhook( $provider, $jsonData ) {
		return null;
	}

	/**
	 * Sync all products to MDM Express.
	 *
	 * Delegates to MdmProductSync for batch product synchronization.
	 *
	 * @return void
	 */
	public function sync_products() {
		$sync = MdmProductSync::get_instance();
		$sync->configure( $this->provider, $this->api_key, $this->store_id, $this->is_fulfilement );
		$result = $sync->start_sync();
		wp_send_json( $result );
	}

	/**
	 * Sync a single product to MDM Express.
	 * Searches by SKU first, creates if not found.
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return array Result with success status and message.
	 */
	public function sync_single_product( $product_id ) {
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return [
				'success' => false,
				'message' => __( 'Product not found', 'woo-bordereau-generator' )
			];
		}

		$sku = $product->get_sku();
		if ( empty( $sku ) ) {
			return [
				'success' => false,
				'message' => __( 'Product has no SKU', 'woo-bordereau-generator' )
			];
		}

		// Search for product by SKU in MDM Express
		$mdm_product = $this->search_product_by_sku( $sku );

		if ( $mdm_product ) {
			// Product found, update meta
			update_post_meta( $product_id, '_mdm_product_id', $mdm_product['trackingId'] );

			return [
				'success' => true,
				'message' => __( 'Matched and synced', 'woo-bordereau-generator' )
			];
		}

		// Product not found, create it
		$result = $this->create_product_in_mdm( $product );

		if ( $result['success'] ) {
			update_post_meta( $product_id, '_mdm_product_id', $result['trackingId'] );

			// Handle variations for variable products
			if ( $product->is_type( 'variable' ) && isset( $result['trackingId'] ) ) {
				$this->create_variations_for_product( $product, $result['trackingId'] );
			}

			return [
				'success' => true,
				'message' => __( 'Created in MDM Express', 'woo-bordereau-generator' )
			];
		}

		return $result;
	}

	/**
	 * Search for a product in MDM Express by SKU.
	 *
	 * @param string $sku Product SKU.
	 *
	 * @return array|null Product data if found, null otherwise.
	 */
	private function search_product_by_sku( $sku ) {
		$url = $this->provider['api_url'] . "/api/v2/products/search";

		$data = [
			'filters'    => [
				'sku' => [ $sku ]
			],
			'fields'     => [ 'trackingId', 'sku', 'name' ],
			'pagination' => [ 'page' => 1, 'perPage' => 1 ]
		];

		$request = wp_safe_remote_post( $url, [
			'headers' => [
				'Content-Type' => 'application/json',
				'x-api-key'   => $this->api_key
			],
			'body'    => wp_json_encode( $data ),
			'timeout' => 30
		] );

		if ( is_wp_error( $request ) ) {
			return null;
		}

		$response = json_decode( wp_remote_retrieve_body( $request ), true );

		if ( isset( $response['list'] ) && ! empty( $response['list'] ) ) {
			return $response['list'][0];
		}

		return null;
	}

	/**
	 * Create a product in MDM Express.
	 *
	 * @param \WC_Product $product Product object.
	 *
	 * @return array Result with success status and message/trackingId.
	 */
	private function create_product_in_mdm( $product ) {
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
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_CUSTOMREQUEST  => 'POST',
			CURLOPT_POSTFIELDS     => $data,
			CURLOPT_HTTPHEADER     => [ 'x-api-key: ' . $this->api_key ],
		] );

		$response  = json_decode( curl_exec( $curl ), true );
		$http_code = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
		curl_close( $curl );

		if ( isset( $response['trackingId'] ) ) {
			return [
				'success'    => true,
				'trackingId' => $response['trackingId']
			];
		}

		return [
			'success' => false,
			'message' => isset( $response['message'] )
				? $response['message']
				: sprintf( __( 'API error (code: %d)', 'woo-bordereau-generator' ), $http_code )
		];
	}

	/**
	 * Create variations for a variable product in MDM Express.
	 *
	 * @param \WC_Product $product Variable product.
	 * @param string $parent_tracking_id Parent product tracking ID in MDM Express.
	 */
	private function create_variations_for_product( $product, $parent_tracking_id ) {
		if ( ! $product->is_type( 'variable' ) ) {
			return;
		}

		$variations = $product->get_available_variations();

		foreach ( $variations as $variation_data ) {
			$variation_id = $variation_data['variation_id'];
			$variation    = wc_get_product( $variation_id );

			if ( ! $variation ) {
				continue;
			}

			// Check if already synced
			$existing_id = get_post_meta( $variation_id, '_mdm_variant_id', true );
			if ( ! empty( $existing_id ) ) {
				continue;
			}

			// Create variation in MDM Express
			$parent         = wc_get_product( $variation->get_parent_id() );
			$variation_name = $parent->get_name() . ' - ' . implode( ' ', $variation->get_variation_attributes() );

			$data = [
				'name'                => $variation_name,
				'pricing[selling]'    => $variation->get_price(),
				'pricing[purchasing]' => 0,
				'sku'                 => $variation->get_sku(),
				'weight'              => (int) $variation->get_weight(),
			];

			$url = $this->provider['api_url'] . sprintf( "/api/v2/products/%s/variants", $parent_tracking_id );

			$request = wp_safe_remote_post( $url, [
				'headers' => [
					'Content-Type' => 'application/json',
					'x-api-key'   => $this->api_key
				],
				'body'    => wp_json_encode( $data ),
				'timeout' => 15
			] );

			if ( ! is_wp_error( $request ) ) {
				$response = json_decode( wp_remote_retrieve_body( $request ), true );

				if ( isset( $response['trackingId'] ) ) {
					update_post_meta( $variation_id, '_mdm_variant_id', $response['trackingId'] );
				}
			}
		}
	}
}
