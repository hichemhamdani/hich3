<?php

namespace WooBordereauGenerator\Admin\Shipping;

/**
 * ZR Express 2.0 Settings Manager.
 *
 * This class handles the configuration settings for the ZR Express 2.0 shipping provider,
 * including API settings, shipping rates, zones management, and package tracking status.
 *
 * @package    WC_Bordereau_Generator
 * @subpackage WC_Bordereau_Generator/admin/shipping
 * @author     Boukraa Mohamed <bm2ilabs@gmail.com>
 */


use WC_Shipping_Zone;
use WC_Shipping_Zones;
use WooBordereauGenerator\Admin\BordereauGeneratorAdmin;
use WooBordereauGenerator\Admin\Products\ZRExpressTwoProductSync;
use WooBordereauGenerator\Functions;
use WooBordereauGenerator\Helpers;
use WP_Error;

/**
 * ZR Express 2.0 settings management class.
 *
 * Manages the settings and configuration for the ZR Express 2.0 shipping provider,
 * including API credentials, shipping zones, and bulk tracking.
 */
class ZRExpressTwoSettings {
	/**
	 * Provider configuration data.
	 *
	 * @var array
	 */
	private $provider;

	/**
	 * ZR Express API Key
	 *
	 * @var string|null
	 */
	protected $api_key;

	/**
	 * ZR Express Tenant ID
	 *
	 * @var string|null
	 */
	private $tenant_id;

	/**
	 * Stock type: local or fulfilled
	 *
	 * @var string
	 */
	private string $stock_type;

	/**
	 * Default status ID for new parcels
	 *
	 * @var string
	 */
	private string $default_status;

	/**
	 * Constructor.
	 *
	 * Initializes a new ZR Express 2.0 settings manager with the provider configuration.
	 *
	 * @param array $provider The provider configuration array.
	 */
	public function __construct( array $provider ) {

		$this->api_key  = get_option( $provider['slug'] . '_api_key' );
		$this->tenant_id = get_option( $provider['slug'] . '_tenant_id' );
		$this->stock_type = get_option( $provider['slug'] . '_stock_type' ) ?: 'local';
		$this->default_status = get_option( $provider['slug'] . '_default_status' ) ?: '8a948c66-1ab7-4433-aeb0-94219125d134';
		$this->provider       = $provider;
	}

	/**
	 * Get the provider settings configuration.
	 *
	 * Returns an array of settings fields for the ZR Express 2.0 provider
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
	 * Verify API credentials by attempting to fetch wilayas (territories)
	 *
	 * @param array $credentials Temporary credentials to test (keys match field 'value' from provider config)
	 * @return array ['success' => bool, 'message' => string]
	 */
	public function check_auth( array $credentials = [] ): array {
		try {
			// Get credentials from passed array or fall back to saved options
			$api_key   = $credentials[$this->provider['slug'] . '_api_key'] ?? $this->api_key;
			$tenant_id = $credentials[$this->provider['slug'] . '_tenant_id'] ?? $this->tenant_id;

			if ( empty( $api_key ) || empty( $tenant_id ) ) {
				return [
					'success' => false,
					'message' => __( 'API Key and Tenant ID are required', 'woo-bordereau-generator' )
				];
			}

			// Make a test request to delivery-pricing/rates endpoint
			$curl = curl_init();
			curl_setopt_array( $curl, [
				CURLOPT_URL            => $this->provider['api_url'] . '/delivery-pricing/rates',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_TIMEOUT        => 30,
				CURLOPT_HTTPGET        => true,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => false,
				CURLOPT_HTTPHEADER     => [
					'X-Tenant: ' . $tenant_id,
					'X-Api-Key: ' . $api_key,
					'Content-Type: application/json'
				],
			] );

			$response = curl_exec( $curl );
			$httpCode = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
			$error    = curl_error( $curl );
			curl_close( $curl );

			if ( $error ) {
				return [
					'success' => false,
					'message' => __( 'Connection error: ', 'woo-bordereau-generator' ) . $error
				];
			}

			$data = json_decode( $response, true );

			if ( $httpCode === 200 && isset( $data['rates'] ) ) {
				return [
					'success' => true,
					'message' => __( 'Credentials verified successfully', 'woo-bordereau-generator' )
				];
			} elseif ( $httpCode === 401 || $httpCode === 403 ) {
				return [
					'success' => false,
					'message' => __( 'Invalid API credentials', 'woo-bordereau-generator' )
				];
			} else {
				$errorMsg = $data['message'] ?? $data['title'] ?? __( 'Unknown error occurred', 'woo-bordereau-generator' );
				return [
					'success' => false,
					'message' => $errorMsg
				];
			}
		} catch ( \Exception $e ) {
			return [
				'success' => false,
				'message' => $e->getMessage()
			];
		}
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
			$parcel_id       = $order->get_meta( '_shipping_zrexpress_v2_parcel_id', true );

			if ( empty( $tracking_number ) || empty( $parcel_id ) ) {
				continue;
			}

			try {
				$url     = $this->provider['api_url'] . '/parcels/' . $parcel_id . '/state-history';
				$request = new ZRExpressTwoRequest( $this->provider );
				$content = $request->get( $url, false );

				if ( is_array( $content ) && ! empty( $content ) ) {
					$last_status = end( $content );

					if ( isset( $last_status['newState']['description'] ) ) {
						$tracking_data[ $order_id ] = [
							'tracking_number' => $tracking_number,
							'status'          => $last_status['newState']['description'],
							'date'            => $last_status['createdAt'] ?? '',
							'color'           => '#' . ($last_status['newState']['color'] ?? '000000')
						];

						if ( $update ) {
							$order->update_meta_data( '_latest_tracking_status', $last_status['newState']['description'] );
							$order->save();
						}
					}
				}
			} catch ( \Exception $e ) {
				error_log( 'ZR Express 2.0 bulk tracking error for order ' . $order_id . ': ' . $e->getMessage() );
			}
		}

		return $tracking_data;
	}

	/**
	 * Sync products with ZR Express 2.0 using Action Scheduler
	 *
	 * @return void
	 */
	public function sync_products() {
		$category_id = '2e2edec1-1be7-46c9-b403-ae66ee4f1b6f';
		$subcategory_id = '944b343c-9f7b-4e36-9921-4dc2c90a2add';

		$sync = \WooBordereauGenerator\Admin\Products\ZRExpressTwoProductSync::get_instance();
		$sync->configure($this->provider, $this->api_key, $this->tenant_id, $this->stock_type, $category_id, $subcategory_id);
		$result = $sync->start_sync();
		wp_send_json( $result );
	}

	/**
	 * Register webhook endpoint with ZR Express 2.0
	 *
	 * @param string $webhook_url The webhook URL to register
	 * @param string $webhook_secret Optional signing secret for webhook signature verification
	 * @return array Response with success status and message
	 */
	public function register_webhook( $webhook_url, $webhook_secret = '' ) {

		$url = $this->provider['api_url'] . '/webhooks/endpoints';

		$data = [
			'url' => $webhook_url,
			'description' => 'WooCommerce Bordereau Generator - ZR Express 2.0 webhook endpoint',
			'eventTypes' => [
				'parcel.state.updated',
				'parcel.state.situation.created',
				'parcel.isReturn.updated'
			],
		];

		$request = wp_safe_remote_post( $url, [
			'headers' => array_merge([
				'Content-Type' => 'application/json',
				'Accept' => 'application/json',
				'X-Api-Key' => $this->api_key,
				'X-Tenant' => $this->tenant_id
			]),
			'body' => wp_json_encode( $data ),
			'timeout' => 30
		]);

		if ( is_wp_error( $request ) ) {
			return [
				'success' => false,
				'message' => $request->get_error_message()
			];
		}

		$response_code = wp_remote_retrieve_response_code( $request );
		$response_body = wp_remote_retrieve_body( $request );
		$response = json_decode( $response_body, true );


		if ( $response_code >= 200 && $response_code < 300 ) {
			// Save webhook configuration
			update_option( $this->provider['slug'] . '_webhook_url', $webhook_url );

			// Save signing secret if provided by user
			if ( ! empty( $webhook_secret ) ) {
				update_option( $this->provider['slug'] . '_webhook_secret', $webhook_secret );
			}

			// Check if API returned signing secret (Svix usually returns 'secret' field)
			if ( isset( $response['secret'] ) && ! empty( $response['secret'] ) ) {
				update_option( $this->provider['slug'] . '_webhook_secret', $response['secret'] );
			}

			$message = __( 'Webhook registered successfully', 'woo-bordereau-generator' );

			// Add note about signing secret
			if ( isset( $response['secret'] ) ) {
				$message .= '. ' . __( 'Signing secret has been saved automatically', 'woo-bordereau-generator' );
			} elseif ( ! empty( $webhook_secret ) ) {
				$message .= '. ' . __( 'Your signing secret has been saved', 'woo-bordereau-generator' );
			} else {
				$message .= '. ' . __( 'Please add the signing secret from ZR Express dashboard for enhanced security', 'woo-bordereau-generator' );
			}

			return [
				'success' => true,
				'message' => $message,
				'data' => $response
			];
		}

		return [
			'success' => false,
			'message' => isset( $response['message'] )
				? $response['message']
				: sprintf( __( 'Failed to register webhook. HTTP Code: %d', 'woo-bordereau-generator' ), $response_code ),
			'response' => $response
		];
	}

	/**
	 * Verify Svix webhook signature
	 *
	 * @param string $body Raw request body
	 * @param array $headers Request headers
	 * @param string $secret Webhook signing secret (with or without whsec_ prefix)
	 * @return bool True if signature is valid
	 */
	private function verify_svix_signature( $body, $headers, $secret ) {
		// Get signature components from headers (try both svix- and webhook- prefixes)
		$signature = $headers['svix-signature'] ?? $headers['webhook-signature'] ?? '';
		$timestamp = $headers['svix-timestamp'] ?? $headers['webhook-timestamp'] ?? '';
		$msg_id = $headers['svix-id'] ?? $headers['webhook-id'] ?? '';

		if ( empty( $signature ) || empty( $timestamp ) || empty( $msg_id ) ) {
			error_log( 'ZR Express 2.0 webhook: Missing signature headers' );
			return false;
		}

		// Check timestamp to prevent replay attacks (within 5 minutes)
		$current_time = time();
		if ( abs( $current_time - (int) $timestamp ) > 300 ) {
			error_log( 'ZR Express 2.0 webhook: Timestamp too old or invalid' );
			return false;
		}

		// Construct the signed content: {msg_id}.{timestamp}.{body}
		$signed_content = $msg_id . '.' . $timestamp . '.' . $body;

		// Prepare the secret: Svix secrets start with whsec_ and the rest is base64-encoded
		$secret_key = $secret;
		if ( strpos( $secret, 'whsec_' ) === 0 ) {
			// Strip whsec_ prefix and base64-decode
			$secret_without_prefix = str_replace( 'whsec_', '', $secret );
			$decoded_secret = base64_decode( $secret_without_prefix, true );
			if ( $decoded_secret !== false ) {
				$secret_key = $decoded_secret;
			}
		}

		// Compute HMAC-SHA256
		$expected_signature = base64_encode( hash_hmac( 'sha256', $signed_content, $secret_key, true ) );

		// Extract signature from header (format: "v1,signature")
		$signature_parts = explode( ',', $signature );
		if ( count( $signature_parts ) !== 2 || $signature_parts[0] !== 'v1' ) {
			error_log( 'ZR Express 2.0 webhook: Invalid signature format' );
			return false;
		}

		$provided_signature = $signature_parts[1];

		// Compare signatures using timing-safe comparison
		return hash_equals( $expected_signature, $provided_signature );
	}

	/**
	 * Handle webhook from ZR Express 2.0
	 *
	 * Supports 3 event types:
	 * - parcel.state.updated: State change event
	 * - parcel.state.situation.created: Situation event (delivery issues)
	 * - parcel.isReturn.updated: Return event
	 *
	 * @param array $provider Provider configuration
	 * @param string $jsonData JSON webhook payload
	 * @param array $headers Request headers (optional, for signature verification)
	 * @return array Response array with success status
	 */
	public function handle_webhook( $provider, $jsonData, $headers = [] ) {
		// Verify signature if webhook secret is configured
		$webhook_secret = get_option( $provider['slug'] . '_webhook_secret' );

		if ( ! empty( $webhook_secret ) && ! empty( $headers ) ) {
			if ( ! $this->verify_svix_signature( $jsonData, $headers, $webhook_secret ) ) {
				error_log( 'ZR Express 2.0 webhook: Signature verification failed' );
				return [
					'success' => false,
					'message' => __('Invalid signature', 'woo-bordereau-generator')
				];
			}
		}

		$data = json_decode( $jsonData, true );

		if ( ! $data ) {
			return [
				'success' => false,
				'message' => __('Invalid JSON payload', 'woo-bordereau-generator')
			];
		}

		// Determine event type and extract data
		$event_type = null;
		$parcel_data = null;
		$tracking = null;
		$external_id = null;
		$status_text = null;

		// Check if EventType is at root level (real webhook format)
		if ( isset( $data['EventType'] ) ) {
			$event_type = $data['EventType'];
			$parcel_data = $data['Data'] ?? [];
			$tracking = $parcel_data['TrackingNumber'] ?? null;
			$external_id = $parcel_data['ExternalId'] ?? null;

			// Handle different event types
			switch ( $event_type ) {
				case 'parcel.state.updated':
					// Prefer Situation.Name when available (more specific than State)
					// e.g. State="attente_recuperation_fournisseur" but Situation="Commande annulée"
					if ( isset( $parcel_data['Situation'] ) && ! empty( $parcel_data['Situation']['Name'] ) ) {
						$situation = $parcel_data['Situation'];
						$status_text = $situation['Description'] ?? $situation['Name'];
					}
					// Fallback to State.Description / State.Name
					elseif ( isset( $parcel_data['State'] ) ) {
						$state = $parcel_data['State'];
						$status_text = $state['Description'] ?? $state['Name'] ?? null;
					}
					// Fallback: Data.NewStateName (from earlier examples)
					elseif ( isset( $parcel_data['NewStateName'] ) ) {
						$status_text = $parcel_data['NewStateName'];
					}
					break;

				case 'parcel.state.situation.created':
					// Has SituationName and SituationDescription in Data
					if ( isset( $parcel_data['SituationName'] ) ) {
						$status_text = $parcel_data['SituationName'];
						if ( ! empty( $parcel_data['SituationDescription'] ) ) {
							$status_text .= ' - ' . $parcel_data['SituationDescription'];
						}
					}
					break;

				case 'parcel.isReturn.updated':
					// Has IsReturn flag in Data
					if ( isset( $parcel_data['IsReturn'] ) ) {
						$status_text = $parcel_data['IsReturn']
							? __( 'Return initiated', 'woo-bordereau-generator' )
							: __( 'Return cancelled', 'woo-bordereau-generator' );
					}
					break;
			}
		}
		// Legacy format detection (from earlier examples)
		// Event 1: parcel.state.updated (has Data wrapper with NewStateName)
		elseif ( isset( $data['Data'] ) && isset( $data['Data']['NewStateName'] ) ) {
			$event_type = 'parcel.state.updated';
			$parcel_data = $data['Data'];
			$tracking = $parcel_data['TrackingNumber'] ?? null;
			$external_id = $parcel_data['ExternalId'] ?? null;
			$status_text = $parcel_data['NewStateName'];
		}
		// Event 2: parcel.state.situation.created (no Data wrapper, has SituationName)
		elseif ( isset( $data['SituationName'] ) ) {
			$event_type = 'parcel.state.situation.created';
			$parcel_data = $data;
			$tracking = $parcel_data['TrackingNumber'] ?? null;
			$external_id = $parcel_data['ExternalId'] ?? null;
			$status_text = $parcel_data['SituationName'] . ' - ' . ($parcel_data['SituationDescription'] ?? '');
		}
		// Event 3: parcel.isReturn.updated (has Data wrapper with IsReturn flag)
		elseif ( isset( $data['Data'] ) && isset( $data['Data']['IsReturn'] ) ) {
			$event_type = 'parcel.isReturn.updated';
			$parcel_data = $data['Data'];
			$tracking = $parcel_data['TrackingNumber'] ?? null;
			$external_id = $parcel_data['ExternalId'] ?? null;
			$status_text = $parcel_data['IsReturn'] ? __( 'Return initiated', 'woo-bordereau-generator' ) : __( 'Return cancelled', 'woo-bordereau-generator' );
		}

		if ( ! $event_type ) {
			return [
				'success' => false,
				'message' => __('Unknown webhook event type', 'woo-bordereau-generator')
			];
		}

		if ( ! $tracking && ! $external_id ) {
			return [
				'success' => false,
				'message' => __('Missing tracking number and external ID', 'woo-bordereau-generator')
			];
		}

		// Find the order
		$theorder = $this->find_order_by_tracking_or_external_id( $tracking, $external_id );

		if ( ! $theorder ) {
			return [
				'success' => false,
				'message' => __('Order not found for tracking: ', 'woo-bordereau-generator') . $tracking . __(' / external ID: ', 'woo-bordereau-generator') . $external_id
			];
		}

		// Update order meta with latest status
		if ( ! empty( $status_text ) ) {
			BordereauGeneratorAdmin::update_tracking_status( $theorder, $status_text, 'webhook' );
			$theorder->update_meta_data( '_zr_webhook_event_type', $event_type );
			$theorder->save();

			// Add order note
			$theorder->add_order_note(
				sprintf(
					__( 'ZR Express 2.0 Update: %s', 'woo-bordereau-generator' ),
					$status_text
				)
			);

			// Update order status based on ZR Express state (only for state updates)
			if ( $event_type === 'parcel.state.updated' ) {
				( new \WooBordereauGenerator\Admin\BordereauGeneratorAdmin( WC_BORDEREAU_POST_TYPE, WC_BORDEREAU_GENERATOR_VERSION ) )
					->update_orders_status( $theorder, $status_text );
			}

			return [
				'success' => true,
				'updated' => 1,
				'event_type' => $event_type,
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
	 * @param string|null $external_id External order ID
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
	 * Get products from warehouse by name with search/filtering
	 *
	 * @param string $search Search term for filtering products
	 * @param int $page Page number for pagination
	 * @return void Sends JSON response
	 */
	public function get_products_by_name( $search = '', $page = 1 ) {
		// Get query from POST if not provided
		if ( empty( $search ) && isset( $_POST['query'] ) ) {
			$search = wc_clean( $_POST['query'] );
		}

		$cache_file = \WooBordereauGenerator\Functions::get_path(
			$this->provider['slug'] . '_products_' . date( 'z' ) . '.json'
		);

		// Load from cache if exists
		if ( file_exists( $cache_file ) ) {
			$cached_products = json_decode( file_get_contents( $cache_file ), true );
		} else {
			// Fetch from API if not cached
			$cached_products = $this->fetch_all_warehouse_products();

			if ( $cached_products ) {
				file_put_contents( $cache_file, json_encode( $cached_products ) );
			} else {
				wp_send_json( [ 'options' => [] ] );
				return;
			}
		}

		// Filter by search term if provided
		if ( ! empty( $search ) ) {
			$search_lower = strtolower( $search );
			$cached_products = array_filter( $cached_products, function( $product ) use ( $search_lower ) {
				return strpos( strtolower( $product['label'] ), $search_lower ) !== false ||
				       strpos( strtolower( $product['sku'] ?? '' ), $search_lower ) !== false;
			} );
		}

		// Paginate results (20 per page)
		$per_page = 20;
		$offset = ( $page - 1 ) * $per_page;
		$paginated = array_slice( $cached_products, $offset, $per_page );

		wp_send_json( [
			'options' => array_values( $paginated )
		] );
	}

	/**
	 * Fetch all warehouse products from API
	 *
	 * @return array All products from warehouse
	 */
	private function fetch_all_warehouse_products() {
		$all_products = [];
		$page = 1;
		$total_pages = 1;

		do {
			$url = $this->provider['api_url'] . '/products/search';

			$data = [
				'pageSize' => 100,
				'pageNumber' => $page
			];

			$request = wp_safe_remote_post( $url, [
				'headers' => [
					'Content-Type' => 'application/json',
					'Accept' => 'application/json',
					'X-Api-Key' => $this->api_key,
					'X-Tenant' => $this->tenant_id
				],
				'body' => wp_json_encode( $data ),
				'timeout' => 60
			]);

			if ( is_wp_error( $request ) ) {
				error_log( 'ZR Express 2.0 product fetch error: ' . $request->get_error_message() );
				break;
			}

			$response = json_decode( wp_remote_retrieve_body( $request ), true );

			if ( isset( $response['items'] ) && is_array( $response['items'] ) ) {
				foreach ( $response['items'] as $item ) {
					$all_products[] = [
						'label' => $item['name'] ?? 'Unnamed Product',
						'value' => $item['id'] ?? '',
						'sku' => $item['sku'] ?? '',
						'price' => $item['basePrice'] ?? 0
					];
				}

				$total_pages = $response['totalPages'] ?? 1;
			} else {
				break;
			}

			$page++;
		} while ( $page <= $total_pages );

		return $all_products;
	}

	/**
	 * Clear the products cache and refresh from API
	 *
	 * @return void Sends JSON response with fresh products
	 */
	public function clear_products_cache() {
		// Delete all cache files for this provider
		$cache_pattern = \WooBordereauGenerator\Functions::get_path(
			$this->provider['slug'] . '_products_*.json'
		);

		$cache_dir = dirname( $cache_pattern );
		$cache_prefix = basename( str_replace( '*.json', '', $cache_pattern ) );

		if ( is_dir( $cache_dir ) ) {
			$files = glob( $cache_dir . '/' . $cache_prefix . '*.json' );
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					unlink( $file );
				}
			}
		}

		// Fetch fresh products
		$products = $this->fetch_all_warehouse_products();

		// Cache the fresh data
		if ( $products ) {
			$cache_file = \WooBordereauGenerator\Functions::get_path(
				$this->provider['slug'] . '_products_' . date( 'z' ) . '.json'
			);
			file_put_contents( $cache_file, json_encode( $products ) );
		}

		wp_send_json( [
			'options' => $products ?: []
		] );
	}

	/**
	 * Bulk add orders to ZR Express 2.0
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
		$wilayas = $this->get_wilayas();

		foreach ( $order_ids as $order_id ) {

			$order = wc_get_order( $order_id );


			if ( ! $order ) {
				continue;
			}

			$params = $order->get_data();

			$wilayaId = str_replace( "DZ-", "", $params['billing']['state'] );

			$wilaya_uuid = null;
			$wilaya_name = '';



			foreach ( $wilayas as $wilaya ) {

				if ( is_numeric($wilayaId)) {

					if ( (int) $wilaya['id'] == (int) $wilayaId ) {
						$wilaya_uuid = $wilaya['uuid'];
						$wilaya_name = $wilaya['name'];
						break;
					}
				} else {

					if ( Helpers::slugify($wilaya['name']) == Helpers::slugify($wilayaId) ) {
						$wilaya_uuid = $wilaya['uuid'];
						$wilaya_name = $wilaya['name'];
						break;
					}
				}
			}



			if ( $wilaya_uuid === null ) {
				continue; // Skip orders without valid wilaya
			}


			// Get commune
			$communeId = null;
			$commune_name = '';

			if ( isset( $params['billing']['city'] ) && ! empty( $params['billing']['city'] ) ) {
				$communes = $this->get_communes( $wilayaId );
				foreach ( $communes as $commune ) {
					if ( $commune['name'] == $params['billing']['city'] ) {
						$communeId = $commune['uuid'];
						$commune_name = $commune['name'];
						break;
					}
				}
			}

			// Build product string (simplified approach - inline products with stockType "none")
			$products_array = $params['line_items'];
			$productString = Helpers::generate_products_string( $products_array );


			$productStd = new \stdClass();
			$productStd->unitPrice = 0;
			$productStd->productName = $productString;
			$productStd->stockType = "none";
			$productStd->quantity = 1;
			$products = [ $productStd ];

			// Get customer info
			$phone = $params['billing']['phone'];

			if ($order->get_meta('_billing_phone_2', true)) {
				$phone_2 = $order->get_meta('_billing_phone_2', true);
			}

			$firstName = $params['billing']['first_name'];
			$lastName = $params['billing']['last_name'];

			$note = stripslashes( get_option( 'wc_bordreau_default-shipping-note' ) );

			// Determine delivery type
			$stop_desk = 0;
			if ( $order->has_shipping_method( 'local_pickup' ) || $order->has_shipping_method( 'local_pickup_zrexpress_v2' ) ) {
				$stop_desk = 1;
			}

			$orderId = (string) $order->get_order_number();
			// Build parcel payload
			$parcel = [
				'externalId' => $orderId,
				'customer' => [
					'customerId' => wp_generate_uuid4(),
					'name' => $firstName . ' ' . $lastName,
					'phone' => [
						'number1' => '+213' . Helpers::clean_phone_number( $phone ),
						'number2' => !empty($phone_2) ? '+213' . Helpers::clean_phone_number( $phone_2 ) : '',
					],
					'email' => $params['billing']['email'] ?: ''
				],
				'deliveryAddress' => [
					'street' => $params['billing']['address_1'] ?? '',
					'city' => $commune_name ?: $wilaya_name,
					'district' => $wilaya_name,
					'postalCode' => '',
					'country' => 'algeria',
					'cityTerritoryId' => $wilaya_uuid,
					'districtTerritoryId' => $communeId ?: $wilaya_uuid
				],
				'orderedProducts' => $products,
				'amount' => (float) $params['total'],
				'description' => empty($note) ? 'Order #'. $orderId : $note,
				'deliveryType' => $stop_desk ? 'pickup-point' : 'home',
				'stateId' => $this->default_status
			];

			if ($stop_desk) {
				// First try to get from meta
				$center = $order->get_meta('_billing_center_id', true);
				if (!$center) {
					$center = get_post_meta($order->get_id(), '_billing_center_id', true);
				}
			}

			// Check if shipping method name matches a center name (like Yalidine)
			if (!$center && $stop_desk) {
				$found = false;
				// check if the name is an agence check in the centers list
				$name = $order->get_shipping_method();
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
					$center = $found['id'];
					$matched_commune_name = $found['commune_name'];

					// Find commune UUID
					if (!empty($found['wilaya_code'])) {
						$communes = $this->get_communes($found['wilaya_code']);
						foreach ($communes as $c) {
							if ($c['name'] == $matched_commune_name) {
								// Update parcel delivery address with center's commune
								$parcel['deliveryAddress']['city'] = $matched_commune_name;
								$parcel['deliveryAddress']['districtTerritoryId'] = $c['uuid'];
								break;
							}
						}
					}
				}
			}

			// Fallback: Auto-select center if not provided (like Yalidine)
			if (!$center && $stop_desk) {
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
						$center = $found['id'];
						$matched_commune_name = $found['commune_name'];
						// Find commune UUID
						if (!empty($found['wilaya_code'])) {
							$communes = $this->get_communes($found['wilaya_code']);
							foreach ($communes as $c) {
								if ($c['name'] == $matched_commune_name) {
									$parcel['deliveryAddress']['city'] = $matched_commune_name;
									$parcel['deliveryAddress']['districtTerritoryId'] = $c['uuid'];
									break;
								}
							}
						}
					}
				}
			}

			if ($stop_desk && !empty($center)) {
				$parcel['hubId'] = $center;
			}

			$parcels[] = $parcel;
		}

		if ( empty( $parcels ) ) {
			return [
				'success' => false,
				'message' => __( 'No valid orders to process', 'woo-bordereau-generator' )
			];
		}


		// Send bulk request
		$url = $this->provider['api_url'] . '/parcels/bulk';

		$request = wp_safe_remote_post( $url, [
			'headers' => [
				'Content-Type' => 'application/json',
				'Accept' => 'application/json',
				'X-Api-Key' => $this->api_key,
				'X-Tenant' => $this->tenant_id
			],
			'body' => wp_json_encode( [ 'parcels' => $parcels ] ),
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

		if ( $response_code >= 200 && $response_code < 300 && isset( $response['successes'] ) ) {
			// Update order metadata for successful parcels
			foreach ( $response['successes'] as $success ) {
				$external_id = $success['externalId'];
				$tracking_number = $success['trackingNumber'];
				$parcel_id = $success['parcelId'];

				// Find order by order number
				$orders = wc_get_orders( [
					'limit' => 1,
					'orderby' => 'ID',
					'order' => 'DESC',
					'return' => 'ids',
					'meta_key' => '_order_number',
					'meta_value' => $external_id
				] );

				if ( empty( $orders ) ) {
					// Try by order ID directly
					if ( is_numeric( $external_id ) ) {
						$order = wc_get_order( $external_id );
						if ( $order ) {
							$orders = [ $order->get_id() ];
						}
					}
				}



				if ( ! empty( $orders ) ) {
					$order_id = $orders[0];

					$order = wc_get_order( $order_id );

					$label = get_rest_url(null, 'woo-bordereau/v1/slip/print/'.$this->provider['slug'].'/'.$order_id);

					update_post_meta($order_id, '_shipping_zrexpress_v2_parcel_id', wc_clean($parcel_id));
					update_post_meta($order_id, '_shipping_tracking_number', wc_clean($tracking_number));
					update_post_meta($order_id, '_shipping_tracking_label', wc_clean($label));
					update_post_meta($order_id, '_shipping_label', wc_clean($label));
					update_post_meta($order_id, '_shipping_tracking_url', 'https://zrexpress.app/tracking/'. wc_clean($tracking_number));
					update_post_meta($order_id, '_shipping_tracking_method', $this->provider['slug']);

					Helpers::make_note($order, $tracking_number, $this->provider['name']);

				}
			}

			return [
				'success' => true,
				'message' => sprintf(
					__( 'Successfully created %d parcels. Failed: %d', 'woo-bordereau-generator' ),
					$response['successCount'],
					$response['failureCount']
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
	 * Mark order as dispatched/confirmed in ZR Express 2.0
	 *
	 * This method is called when the order status changes to the configured "dispatched" status.
	 * It updates the parcel state to "commande_confirmee" in ZR Express.
	 *
	 * @param \WC_Order $order The WooCommerce order
	 * @return array|bool Response array on success, false on failure
	 */
	public function mark_as_dispatched( \WC_Order $order ) {
		// State ID for "commande_confirmee" (confirmed order)
		$confirmed_state_id = '18af0e67-10b6-4a2e-84ee-39e79069801f';

		// Get parcel ID from order meta
		$parcel_id = $order->get_meta( '_shipping_zrexpress_v2_parcel_id' );

		if ( ! $parcel_id ) {
			// Try post meta as fallback
			$parcel_id = get_post_meta( $order->get_id(), '_shipping_zrexpress_v2_parcel_id', true );
		}

		if ( ! $parcel_id ) {
			return false;
		}

		$url = $this->provider['api_url'] . '/parcels/' . $parcel_id . '/state';

		$body = [
			'parcelId'   => $parcel_id,
			'comment'    => '',
			'newStateId' => $confirmed_state_id
		];

		$request = wp_remote_request( $url, [
			'method'  => 'PATCH',
			'headers' => [
				'Content-Type' => 'application/json',
				'Accept'       => 'application/json',
				'X-Api-Key'    => $this->api_key,
				'X-Tenant'     => $this->tenant_id
			],
			'body'    => json_encode( $body ),
			'timeout' => 30
		] );

		if ( is_wp_error( $request ) ) {
			$logger = wc_get_logger();
			$logger->error( 'ZR Express 2.0 mark_as_dispatched error: ' . $request->get_error_message(), [ 'source' => 'woo-bordereau-generator' ] );
			return false;
		}

		$response_code = wp_remote_retrieve_response_code( $request );
		$response_body = wp_remote_retrieve_body( $request );
		$response = json_decode( $response_body, true );

		if ( $response_code >= 200 && $response_code < 300 ) {
			// Success - optionally log
			return [
				'success' => true,
				'data'    => $response
			];
		}

		// Log error
		$logger = wc_get_logger();
		$logger->error( sprintf(
			'ZR Express 2.0 mark_as_dispatched failed. Order: %d, Parcel: %s, HTTP Code: %d, Response: %s',
			$order->get_id(),
			$parcel_id,
			$response_code,
			$response_body
		), [ 'source' => 'woo-bordereau-generator' ] );

		return false;
	}

	/**
	 * Get wilayas (territories) from ZR Express 2.0
	 *
	 * @return array Array of wilayas with id, code, and name
	 */
	private function get_wilayas() {

		$path = Functions::get_path($this->provider['slug']. '_wilayas.json');

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
				$wilayasResult[$i]['code'] = $wilaya['code'];
			}

			return $wilayasResult;
		}

		return $wilayasResult;
	}

	/**
	 * Get communes for a specific wilaya
	 *
	 * @param int $wilaya_code Wilaya code
	 * @return array Array of communes
	 */
	public function get_communes( $wilaya_code, $hasStopDesk = false ) {

		$wilaya_id = str_replace('DZ-', '', $wilaya_code);

		// Get wilayas to find UUID
		$wilayas = $this->get_wilayas();


		$wilaya_uuid = null;
		foreach ($wilayas as $wilaya) {
			if ($wilaya['code'] == $wilaya_id || $wilaya['name'] == $wilaya_id) {
				$wilaya_uuid = $wilaya['uuid'];
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
				'uuid'          => $commune['id'],
				'code'          => $commune['code'],
				'has_stop_desk' => $has_stop_desk,
			];
		}

		return $communesResult;
	}

	public function get_status()
	{
		return $this->status();
	}

	private function status(): array
	{
		return [
			'commande_recue' => 'Commande reçue',
			'en_traitement' => 'Commande en traitement',
			'appel_confirmation' => 'Appel de confirmation',
			'commande_confirmee' => 'Commande confirmée',
			'en_preparation' => 'En préparation',
			'pret_a_expedier' => 'Prêt à expédier',
			'confirme_au_bureau' => 'Confirmé au bureau',
			'remboursement_reinjecte' => 'Remboursement Reinjecte',
			'dispatch' => 'Dispatch dans la même wilaya',
			'vers_wilaya' => 'Dispatch dans une autre wilaya',
			'en_livraison' => 'En livraison',
			'en_attente_dechange' => 'En attente d\'échange',
			'colis_recupere' => 'Colis récupéré',
			'attente_recuperation_fournisseur' => 'En attente récupération fournisseur',
			'reinjecte_dans_stock' => 'Réinjecté dans le stock',
			'recupere_par_fournisseur' => 'Récupéré par fournisseur',
			'sortie_en_livraison' => 'Sortie en livraison',
			'livre' => 'Livré',
			'encaisse' => 'Encaissé',
			'recouvert' => 'Recouvert',
		];
	}

	private function get_wilaya_from_provider() {

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
	 * Get supplier profile from ZR Express 2.0 API
	 *
	 * @return array|WP_Error Supplier profile data on success, WP_Error on failure
	 */
	private function get_supplier_profile() {
		$url = $this->provider['api_url'] . '/supplier/' . $this->tenant_id;

		$request = wp_safe_remote_post( $url, [
			'headers' => [
				'Content-Type' => 'application/json',
				'Accept' => 'application/json',
				'X-Api-Key' => $this->api_key,
				'X-Tenant' => $this->tenant_id
			],
			'body' => wp_json_encode( [
				'supplierId' => $this->tenant_id,
				'includeServices' => true
			] ),
			'timeout' => 30
		] );

		if ( is_wp_error( $request ) ) {
			return $request;
		}

		$response_code = wp_remote_retrieve_response_code( $request );
		$response_body = wp_remote_retrieve_body( $request );
		$response = json_decode( $response_body, true );

		if ( $response_code !== 200 ) {
			return new WP_Error(
				'supplier_api_error',
				sprintf( __( 'Supplier API returned status %d: %s', 'woo-bordereau-generator' ), $response_code, $response_body )
			);
		}

		return $response;
	}

	/**
	 * Get delivery rates from ZR Express 2.0 API
	 * Single endpoint that returns all rates (wilaya-level only)
	 *
	 * @return array|WP_Error Rates data on success, WP_Error on failure
	 */
	private function get_rates() {
		$url = $this->provider['api_url'] . '/delivery-pricing/rates';

		$request = wp_safe_remote_get( $url, [
			'headers' => [
				'Content-Type' => 'application/json',
				'Accept' => 'application/json',
				'X-Api-Key' => $this->api_key,
				'X-Tenant' => $this->tenant_id
			],
			'timeout' => 30
		] );

		if ( is_wp_error( $request ) ) {
			return $request;
		}

		$response_code = wp_remote_retrieve_response_code( $request );
		$response_body = wp_remote_retrieve_body( $request );
		$response = json_decode( $response_body, true );

		if ( $response_code !== 200 ) {
			return new WP_Error(
				'rates_api_error',
				sprintf( __( 'Rates API returned status %d: %s', 'woo-bordereau-generator' ), $response_code, $response_body )
			);
		}

		if ( ! isset( $response['rates'] ) || ! is_array( $response['rates'] ) ) {
			return new WP_Error(
				'rates_parse_error',
				__( 'Failed to parse rates response', 'woo-bordereau-generator' )
			);
		}

		return $response['rates'];
	}

	/**
	 * Import price list from ZR Express 2.0 API
	 * Uses the single /delivery-pricing/rates endpoint
	 *
	 * @return array Response with success status and data
	 */
	public function import_price_list() {
		// Get supplier profile to identify store's commune
		$supplier_profile = $this->get_supplier_profile();
		$store_commune_territory_id = null;
		$store_wilaya_code = null;

		if ( ! is_wp_error( $supplier_profile ) && isset( $supplier_profile['address'] ) ) {
			// Extract store's commune territory ID
			$store_commune_territory_id = $supplier_profile['address']['districtTerritoryId'] ?? null;

			// Extract wilaya code from postal code (first 2 digits)
			$postal_code = $supplier_profile['address']['postalCode'] ?? '';
			if ( ! empty( $postal_code ) ) {
				$store_wilaya_code = substr( $postal_code, 0, 2 );
			}
		}

		// Get rates from the single API endpoint
		$rates = $this->get_rates();

		if ( is_wp_error( $rates ) ) {
			return [
				'success' => false,
				'message' => __( 'Failed to fetch rates: ', 'woo-bordereau-generator' ) . $rates->get_error_message()
			];
		}

		if ( empty( $rates ) ) {
			return [
				'success' => false,
				'message' => __( 'No rates returned from API', 'woo-bordereau-generator' )
			];
		}

		// Transform the data for storage - wilaya-level rates and store's commune
		$transformed_prices = [];

		foreach ( $rates as $rate ) {
			$territory_level = $rate['toTerritoryLevel'] ?? '';
			$territory_id = $rate['toTerritoryId'] ?? null;
			$wilaya_code = $rate['toTerritoryCode'] ?? null;

			// Check if this is the store's commune (commune-level rate matching store location)
			if ( $territory_level === 'commune' &&
			     $store_commune_territory_id &&
			     $territory_id === $store_commune_territory_id &&
			     $store_wilaya_code ) {

				// Get delivery prices for store's commune
				$delivery_prices = $rate['deliveryPrices'] ?? [];
				if ( ! empty( $delivery_prices ) ) {
					// Initialize store's wilaya entry
					if ( ! isset( $transformed_prices[ $store_wilaya_code ] ) ) {
						$transformed_prices[ $store_wilaya_code ] = [];
					}

					// Map delivery prices by type for store's wilaya
					foreach ( $delivery_prices as $dp ) {
						$delivery_type = $dp['deliveryType'] ?? '';
						$price = $dp['price'] ?? 0;

						if ( $delivery_type && $price > 0 ) {
							$transformed_prices[ $store_wilaya_code ][ $delivery_type ] = $price;
						}
					}
				}

				continue; // Already processed, move to next rate
			}

			// Process wilaya-level rates
			if ( $territory_level !== 'wilaya' ) {
				continue;
			}

			// Get wilaya code
			if ( ! $wilaya_code ) {
				continue;
			}

			// Get delivery prices
			$delivery_prices = $rate['deliveryPrices'] ?? [];
			if ( empty( $delivery_prices ) ) {
				continue;
			}

			// Initialize wilaya entry (don't overwrite if store's commune already set it)
			if ( ! isset( $transformed_prices[ $wilaya_code ] ) ) {
				$transformed_prices[ $wilaya_code ] = [];
			}

			// Map delivery prices by type
			foreach ( $delivery_prices as $dp ) {
				$delivery_type = $dp['deliveryType'] ?? '';
				$price = $dp['price'] ?? 0;

				if ( $delivery_type && $price > 0 ) {
					// Only set if not already set by store's commune pricing
					if ( ! isset( $transformed_prices[ $wilaya_code ][ $delivery_type ] ) ) {
						$transformed_prices[ $wilaya_code ][ $delivery_type ] = $price;
					}
				}
			}
		}

		if ( empty( $transformed_prices ) ) {
			return [
				'success' => false,
				'message' => __( 'No wilaya-level rates found in API response', 'woo-bordereau-generator' )
			];
		}

		// Store in WordPress transient (expires in 7 days)
		$cache_data = [
			'imported_at' => time(),
			'prices' => $transformed_prices,
			'store_wilaya_code' => $store_wilaya_code
		];

		set_transient( $this->provider['slug'] . '_price_list_data', $cache_data, 7 * DAY_IN_SECONDS );

		$message = sprintf(
			__( 'Successfully imported rates for %d wilayas', 'woo-bordereau-generator' ),
			count( $transformed_prices )
		);

		if ( $store_wilaya_code && isset( $transformed_prices[ $store_wilaya_code ] ) ) {
			$message .= ' ' . sprintf(
				__( '(including store location: wilaya %s)', 'woo-bordereau-generator' ),
				$store_wilaya_code
			);
		}

		return [
			'success' => true,
			'message' => $message,
			'data' => [
				'destinations_count' => count( $transformed_prices ),
				'imported_at' => date( 'Y-m-d H:i:s' ),
				'store_wilaya_code' => $store_wilaya_code
			]
		];
	}

	/**
	 * Get shipping fees from cached price list
	 *
	 * @param int $from_wilaya_code From wilaya code
	 * @param int $to_wilaya_code To wilaya code
	 * @param string $delivery_type Delivery type: 'home', 'pickup-point', 'return'
	 * @return float|null Fee amount or null if not found
	 */
	public function get_fee( $from_wilaya_code, $to_wilaya_code, $delivery_type = 'home' ) {

		$cache_data = get_transient( $this->provider['slug'] . '_price_list_data' );

		if ( ! $cache_data || ! isset( $cache_data['prices'] ) ) {
			return null;
		}

		// Check if price exists for destination wilaya
		if ( ! isset( $cache_data['prices'][ $to_wilaya_code ] ) ) {
			return null;
		}

		$prices = $cache_data['prices'][ $to_wilaya_code ];

		// Return price for requested delivery type
		return $prices[ $delivery_type ] ?? null;
	}

	/**
	 * Get orders/parcels from ZR Express 2.0 API
	 *
	 * @param array $queryData Query parameters (page, page_size, status, query, range, etc.)
	 * @return array Orders data with items, total_data, current_page
	 */
	public function get_orders( $queryData ) {
		$page = $queryData['page'] ?? 1;
		$page_size = $queryData['page_size'] ?? 10;
		$status = $queryData['status'] ?? '';
		$search_query = $queryData['query'] ?? '';
		$date_range = $queryData['range'] ?? '';

		$url = $this->provider['api_url'] . '/parcels/search';

		// Build request body
		$request_body = [
			'pageSize'   => (int) $page_size,
			'pageNumber' => (int) $page,
			'orderBy'    => ['createdAt desc']
		];

		// Add search keyword if provided
		if ( ! empty( $search_query ) ) {
			$request_body['keyword'] = $search_query;
			$request_body['advancedSearch'] = [
				'fields'  => ['trackingNumber', 'externalId', 'customer.name', 'customer.phone.number1'],
				'keyword' => $search_query
			];
		}

		// Add status filter if provided
		if ( ! empty( $status ) ) {
			$request_body['advancedFilter'] = [
				'field'    => 'state.name',
				'operator' => 'eq',
				'value'    => $status
			];
		}

		// Add date range filter if provided (format: "YYYY-MM-DD to YYYY-MM-DD")
		if ( ! empty( $date_range ) && strpos( $date_range, ' to ' ) !== false ) {
			$dates = explode( ' to ', $date_range );
			if ( count( $dates ) === 2 ) {
				$start_date = trim( $dates[0] ) . 'T00:00:00Z';
				$end_date = trim( $dates[1] ) . 'T23:59:59Z';
				
				// If we already have a status filter, combine them
				if ( isset( $request_body['advancedFilter'] ) ) {
					$status_filter = $request_body['advancedFilter'];
					$request_body['advancedFilter'] = [
						'logic'   => 'and',
						'filters' => [
							$status_filter,
							[
								'logic'   => 'and',
								'filters' => [
									[
										'field'    => 'createdAt',
										'operator' => 'gte',
										'value'    => $start_date
									],
									[
										'field'    => 'createdAt',
										'operator' => 'lte',
										'value'    => $end_date
									]
								]
							]
						]
					];
				} else {
					$request_body['advancedFilter'] = [
						'logic'   => 'and',
						'filters' => [
							[
								'field'    => 'createdAt',
								'operator' => 'gte',
								'value'    => $start_date
							],
							[
								'field'    => 'createdAt',
								'operator' => 'lte',
								'value'    => $end_date
							]
						]
					];
				}
			}
		}

		$curl = curl_init();
		curl_setopt_array( $curl, [
			CURLOPT_URL            => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_POST           => true,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_POSTFIELDS     => json_encode( $request_body ),
			CURLOPT_HTTPHEADER     => [
				'X-Tenant: ' . $this->tenant_id,
				'X-Api-Key: ' . $this->api_key,
				'Content-Type: application/json'
			],
		] );

		$response = curl_exec( $curl );
		$httpCode = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
		$error    = curl_error( $curl );
		curl_close( $curl );

		if ( $error || $httpCode !== 200 ) {
			return [
				'items'        => [],
				'total_data'   => 0,
				'current_page' => $page
			];
		}

		$data = json_decode( $response, true );

		if ( ! isset( $data['items'] ) || ! is_array( $data['items'] ) ) {
			return [
				'items'        => [],
				'total_data'   => 0,
				'current_page' => $page
			];
		}

		return [
			'items'        => $this->mapping_order_results( $data['items'] ),
			'total_data'   => $data['totalCount'] ?? count( $data['items'] ),
			'current_page' => $page
		];
	}

	/**
	 * Map order results to standard format
	 * Based on ZR Express 2.0 API response structure
	 *
	 * @param array $data Raw order data from API
	 * @return array Mapped order data
	 */
	private function mapping_order_results( $data ) {
		if ( ! is_array( $data ) ) {
			return [];
		}

		return array_map( function ( $item ) {
			// Get customer info from nested 'customer' object
			$customer = $item['customer'] ?? [];
			$customerName = $customer['name'] ?? '';
			$nameParts = explode( ' ', $customerName, 2 );
			$firstName = $nameParts[0] ?? '';
			$lastName = $nameParts[1] ?? '';

			// Get phone from nested customer.phone object
			$phone = '';
			if ( isset( $customer['phone']['number1'] ) ) {
				$phone = $customer['phone']['number1'];
			}

			// Get delivery address info from nested 'deliveryAddress' object
			$address = $item['deliveryAddress'] ?? [];
			$city = $address['city'] ?? '';
			$district = $address['district'] ?? '';
			$hubName = $address['hubName'] ?? '';

			// Get status from nested 'state' object
			$state = $item['state'] ?? [];
			$status = $state['name'] ?? '';
			$statusColor = $state['color'] ?? '';

			// Get situation info if available
			$situation = $item['situation'] ?? [];
			$situationName = $situation['name'] ?? '';

			// Format dates
			$createdAt = $item['createdAt'] ?? '';
			$lastStateUpdateAt = $item['lastStateUpdateAt'] ?? $item['lastSituationUpdateAt'] ?? '';

			// Format created date for display
			if ( ! empty( $createdAt ) ) {
				$createdAt = date( 'Y-m-d H:i', strtotime( $createdAt ) );
			}
			if ( ! empty( $lastStateUpdateAt ) ) {
				$lastStateUpdateAt = date( 'Y-m-d H:i', strtotime( $lastStateUpdateAt ) );
			}

			// Build label URL
			$trackingNumber = $item['trackingNumber'] ?? '';
			$label = '';
			if ( ! empty( $trackingNumber ) ) {
				$label = get_rest_url( null, 'woo-bordereau/v1/slip/print/' . $this->provider['slug'] . '/' . $trackingNumber );
			}

			return [
				'tracking'         => $trackingNumber,
				'familyname'       => $lastName,
				'firstname'        => $firstName,
				'to_commune_name'  => $city,
				'to_wilaya_name'   => $district,
				'date'             => $createdAt,
				'contact_phone'    => $phone,
				'city'             => $city,
				'state'            => $district,
				'last_status'      => $status,
				'date_last_status' => $lastStateUpdateAt,
				'label'            => $label,
				'hub_name'         => $hubName,
				'situation'        => $situationName,
				'status_color'     => $statusColor,
				'amount'           => $item['amount'] ?? 0,
				'delivery_type'    => $item['deliveryType'] ?? '',
				'external_id'      => $item['externalId'] ?? '',
			];
		}, $data );
	}

	/**
	 * Get bulk labels PDF URL from ZR Express 2.0 API
	 * 
	 * @param array $tracking_numbers Array of tracking numbers
	 * @return array Response with success status and fileUrl or error message
	 */
	public function get_bulk_labels_url( $tracking_numbers ) {
		if ( empty( $tracking_numbers ) || ! is_array( $tracking_numbers ) ) {
			return [
				'success' => false,
				'message' => __( 'No tracking numbers provided', 'woo-bordereau-generator' )
			];
		}

		$url = $this->provider['api_url'] . '/parcels/labels/multiple/pdf';

		$curl = curl_init();
		curl_setopt_array( $curl, [
			CURLOPT_URL            => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT        => 60,
			CURLOPT_POST           => true,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_POSTFIELDS     => json_encode([
				'trackingNumbers' => array_values( $tracking_numbers )
			]),
			CURLOPT_HTTPHEADER     => [
				'X-Tenant: ' . $this->tenant_id,
				'X-Api-Key: ' . $this->api_key,
				'Content-Type: application/json'
			],
		] );

		$response = curl_exec( $curl );
		$httpCode = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
		$error    = curl_error( $curl );
		curl_close( $curl );

		if ( $error ) {
			return [
				'success' => false,
				'message' => __( 'Connection error: ', 'woo-bordereau-generator' ) . $error
			];
		}

		$data = json_decode( $response, true );

		if ( $httpCode === 200 && isset( $data['fileUrl'] ) ) {
			return [
				'success'                 => true,
				'fileUrl'                 => $data['fileUrl'],
				'failedTrackingNumbers'   => $data['failedTrackingNumbers'] ?? [],
				'message'                 => sprintf(
					__( 'Generated PDF for %d labels', 'woo-bordereau-generator' ),
					count( $tracking_numbers ) - count( $data['failedTrackingNumbers'] ?? [] )
				)
			];
		} else {
			$errorMsg = $data['message'] ?? $data['title'] ?? __( 'Failed to generate labels PDF', 'woo-bordereau-generator' );
			return [
				'success' => false,
				'message' => $errorMsg
			];
		}
	}

	/**
	 * Clear price list cache
	 *
	 * @return array Response with success status
	 */
	public function clear_price_cache() {
		delete_transient( $this->provider['slug'] . '_price_list_data' );

		return [
			'success' => true,
			'message' => __( 'Price list cache cleared successfully', 'woo-bordereau-generator' )
		];
	}

	/**
	 * Get price list cache info
	 *
	 * @return array Cache information
	 */
	public function get_price_cache_info() {

		$cache_data = get_transient( $this->provider['slug'] . '_price_list_data' );

		if ( ! $cache_data ) {
			return [
				'cached' => false,
				'message' => __( 'No rates cached', 'woo-bordereau-generator' )
			];
		}

		$destinations_count = isset( $cache_data['prices'] ) ? count( $cache_data['prices'] ) : 0;

		return [
			'cached' => true,
			'destinations_count' => $destinations_count,
			'imported_at' => isset( $cache_data['imported_at'] ) ? date( 'Y-m-d H:i:s', $cache_data['imported_at'] ) : 'Unknown'
		];
	}

	/**
	 * Import shipping classes to WooCommerce zones
	 *
	 * Similar to Yalitec's implementation - creates shipping zones for each wilaya
	 * and adds shipping methods with prices from the cached price list.
	 *
	 * @param string $flat_rate_label Label for flat rate shipping
	 * @param string $pickup_local_label Label for local pickup shipping
	 * @param bool $flat_rate_enabled Whether flat rate shipping is enabled
	 * @param bool $pickup_local_enabled Whether local pickup is enabled
	 * @param bool $centers_import_enabled Whether to import individual centers/hubs
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
		// Save labels
		if ( $pickup_local_enabled && $pickup_local_label ) {
			update_option( $this->provider['slug'] . '_pickup_local_label', $pickup_local_label );
		}

		if ( $flat_rate_enabled && $flat_rate_label ) {
			update_option( $this->provider['slug'] . '_flat_rate_label', $flat_rate_label );
		}

		// First, import the price list from API
		$price_import_result = $this->import_price_list();


		if ( ! $price_import_result['success'] ) {
			wp_send_json_error( [
				'message' => $price_import_result['message']
			] );
			return;
		}

		// Get the cached price list
		$cache_data = get_transient( $this->provider['slug'] . '_price_list_data' );

		if ( ! $cache_data || ! isset( $cache_data['prices'] ) ) {
			wp_send_json_error( [
				'message' => __( 'Failed to load price list from cache', 'woo-bordereau-generator' )
			] );
			return;
		}

		if ( $centers_import_enabled ) {
			update_option( $this->provider['slug'] . '_centers_import', true );
		}

		// Set memory and time limits
		ini_set( 'memory_limit', '-1' );
		set_time_limit( 0 );

		// Get wilayas data
		$wilayas = $this->get_wilayas();

		// Process each wilaya
		foreach ( $cache_data['prices'] as $wilaya_code => $prices ) {
			// Find wilaya info
			$wilaya_info = null;
			foreach ( $wilayas as $wilaya ) {
				if ( $wilaya['code'] == $wilaya_code ) {
					$wilaya_info = $wilaya;
					break;
				}
			}

			if ( ! $wilaya_info ) {
				continue; // Skip if wilaya not found
			}

			$home_price = $prices['home'] ?? 0;
			$pickup_price = $prices['pickup-point'] ?? 0;
			$wilaya_name = $wilaya_info['name'];

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

			// Add flat rate (home delivery) method
			if ( $home_price && $flat_rate_enabled && $home_price > 0 ) {
				$flat_rate_name = 'flat_rate_zrexpress_v2';
				if ( function_exists( 'is_hanout_enabled' ) && is_hanout_enabled() ) {
					$flat_rate_name = 'flat_rate';
				}

				$instance_flat_rate = $zone->add_shipping_method( $flat_rate_name );

				if ( $instance_flat_rate ) {
					$shipping_method_flat_rate = \WC_Shipping_Zones::get_shipping_method( $instance_flat_rate );

					if ( $shipping_method_flat_rate ) {
						$shipping_method_configuration_flat_rate = [
							'woocommerce_' . $flat_rate_name . '_title'         => $flat_rate_label ?? __( 'Flat rate', 'woo-bordereau-generator' ),
							'woocommerce_' . $flat_rate_name . '_cost'          => $home_price,
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
				}
			}

			// Add local pickup (pickup-point) method
			if ( $pickup_price && $pickup_local_enabled && $pickup_price > 0 ) {

				if ( $centers_import_enabled ) {
					// Import individual centers
					$centers = $this->get_centers_for_wilaya( $wilaya_code );

					foreach ( $centers as $center ) {
						$local_pickup_name = 'local_pickup_zrexpress_v2';
						if ( function_exists( 'is_hanout_enabled' ) && is_hanout_enabled() ) {
							$local_pickup_name = 'local_pickup';
						}

						$center_name = sprintf( "%s %s", $pickup_local_label ?? __( 'Local Pickup', 'woo-bordereau-generator' ), $center['name'] );

						$instance_local_pickup = $zone->add_shipping_method( $local_pickup_name );

						if ( $instance_local_pickup ) {
							$shipping_method_local_pickup = \WC_Shipping_Zones::get_shipping_method( $instance_local_pickup );

							if ( $shipping_method_local_pickup ) {
								$shipping_method_configuration_local_pickup = [
									'woocommerce_' . $local_pickup_name . '_title'     => $center_name,
									'woocommerce_' . $local_pickup_name . '_cost'      => $pickup_price,
									'woocommerce_' . $local_pickup_name . '_type'      => 'class',
									'woocommerce_' . $local_pickup_name . '_address'   => $center['address'] ?? '',
									'woocommerce_' . $local_pickup_name . '_center_id' => $center['id'],
									'woocommerce_' . $local_pickup_name . '_provider'  => $this->provider['slug'],
									'instance_id'                                      => $instance_local_pickup,
								];

								$_REQUEST['instance_id'] = $instance_local_pickup;
								$shipping_method_local_pickup->set_post_data( $shipping_method_configuration_local_pickup );
								$shipping_method_local_pickup->update_option( 'provider', $this->provider['slug'] );
								$shipping_method_local_pickup->process_admin_options();
							}
						}
					}
				} else {
					// Add single local pickup option per wilaya
					$local_pickup_name = 'local_pickup_zrexpress_v2';
					if ( function_exists( 'is_hanout_enabled' ) && is_hanout_enabled() ) {
						$local_pickup_name = 'local_pickup';
					}

					$instance_local_pickup = $zone->add_shipping_method( $local_pickup_name );

					if ( $instance_local_pickup ) {
						$shipping_method_local_pickup = \WC_Shipping_Zones::get_shipping_method( $instance_local_pickup );

						if ( $shipping_method_local_pickup ) {
							$shipping_method_configuration_local_pickup = [
								'woocommerce_' . $local_pickup_name . '_title'    => $pickup_local_label ?? __( 'Local Pickup', 'woo-bordereau-generator' ),
								'woocommerce_' . $local_pickup_name . '_cost'     => $pickup_price,
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
		}

		wp_send_json_success( [
			'message' => sprintf(
				__( 'Successfully imported shipping zones for %d wilayas', 'woo-bordereau-generator' ),
				count( $cache_data['prices'] )
			)
		] );
	}

	/**
	 * Get centers for a specific wilaya
	 *
	 * @param int $wilaya_code Wilaya code
	 * @return array Array of centers in the wilaya
	 */
	private function get_centers_for_wilaya( $wilaya_code ) {
		// Get all centers
		$centers = $this->get_all_centers();

		// Filter centers by wilaya
		$filtered_centers = [];
		foreach ( $centers as $center ) {
			// Check if center belongs to this wilaya
			if ( isset( $center['wilaya_code'] ) && $center['wilaya_code'] == $wilaya_code ) {
				$filtered_centers[] = $center;
			}
		}

		return $filtered_centers;
	}

	/**
	 * Get all centers from ZR Express 2.0
	 * Similar to Yalitec's get_all_centers method
	 *
	 * @return array Array of all centers
	 */
	public function get_all_centers() {

		$cache_file = Functions::get_path( $this->provider['slug'] . '_all_centers_' . WC_BORDEREAU_GENERATOR_VERSION . '_' . date( 'Y' ) . '_' . ( date( 'z' ) + 1 ) . '.json' );

		// Check cache first
		if ( file_exists( $cache_file ) ) {
			$cached_centers = json_decode( file_get_contents( $cache_file ), true );
			if ( $cached_centers ) {
				return $cached_centers;
			}
		}

		// Fetch from API
		$url = $this->provider['api_url'] . '/hubs/search';

		$data = [
			'pageSize' => 1000,
			'pageNumber' => 1,
			'includeServices' => true
		];

		$request = wp_safe_remote_post( $url, [
			'headers' => [
				'Content-Type' => 'application/json',
				'Accept' => 'application/json',
				'X-Api-Key' => $this->api_key,
				'X-Tenant' => $this->tenant_id
			],
			'body' => wp_json_encode( $data ),
			'timeout' => 60
		] );

		if ( is_wp_error( $request ) ) {
			error_log( 'ZR Express 2.0 centers fetch error: ' . $request->get_error_message() );
			return [];
		}

		$response = json_decode( wp_remote_retrieve_body( $request ), true );

		if ( ! isset( $response['items'] ) || ! is_array( $response['items'] ) ) {
			return [];
		}

		// Get wilayas to map territory IDs to wilaya codes and names
		$wilayas = $this->get_wilayas();
		$territory_to_wilaya = [];
		$wilaya_code_to_name = [];
		foreach ( $wilayas as $wilaya ) {
			$territory_to_wilaya[ $wilaya['uuid'] ] = $wilaya['code'];
			$wilaya_code_to_name[ $wilaya['code'] ] = $wilaya['name'];
		}

		// Transform centers - format similar to Yalitec
		$all_centers = [];
		foreach ( $response['items'] as $item ) {
			// Only include pickup points
			if ( ! isset( $item['isPickupPoint'] ) || ! $item['isPickupPoint'] ) {
				continue;
			}

			$city_territory_id = $item['address']['cityTerritoryId'] ?? null;
			$wilaya_code = $territory_to_wilaya[ $city_territory_id ] ?? null;
			$wilaya_name = $wilaya_code ? ( $wilaya_code_to_name[ $wilaya_code ] ?? '' ) : '';

			$address = $item['address']['street'] ?? '';
			if ( isset( $item['address']['district'] ) && $item['address']['district'] ) {
				$address .= ', ' . $item['address']['district'];
			}

			$all_centers[] = [
				'id'           => $item['id'] ?? '',
				'name'         => $item['name'] ?? 'Unnamed Center',
				'address'      => $address,
				'commune_name' => $item['address']['district'] ?? '',
				'wilaya_name'  => $wilaya_name,
				'wilaya_code'  => $wilaya_code
			];
		}

		// Cache the results
		file_put_contents( $cache_file, json_encode( $all_centers ) );

		return $all_centers;
	}
}
