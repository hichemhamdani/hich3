<?php

namespace WooBordereauGenerator\Admin\Products;

class MdmProductSync {
	private static $instance = null;

	private $provider;
	private $api_key;
	private $store_id;
	private $is_fulfilement;
	private const DEFAULT_CATEGORY_ID = '6571f847a1672bea6ea84b49';

	// Action hooks
	const ACTION_FETCH_PAGE = 'mdm_fetch_products_page';
	const ACTION_CREATE_PRODUCT = 'mdm_create_product';
	const ACTION_CREATE_VARIATION = 'mdm_create_variation';
	const ACTION_SYNC_COMPLETE = 'mdm_sync_complete';

	/**
	 * Private constructor to prevent direct instantiation
	 */
	private function __construct() {
		// Empty constructor - configuration set via configure() method
	}

	/**
	 * Get singleton instance
	 */
	public static function get_instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register Action Scheduler hooks - call this during plugin initialization
	 */
	public static function register_hooks() {
		$instance = self::get_instance();

		add_action( self::ACTION_FETCH_PAGE, [ $instance, 'process_mdm_page' ], 10, 1 );
		add_action( self::ACTION_CREATE_PRODUCT, [ $instance, 'create_product_in_mdm' ], 10, 1 );
		add_action( self::ACTION_CREATE_VARIATION, [ $instance, 'create_variation_in_mdm' ], 10, 2 );
		add_action( self::ACTION_SYNC_COMPLETE, [ $instance, 'on_sync_complete' ], 10, 0 );
	}

	/**
	 * Configure the sync instance with API credentials and settings
	 */
	public function configure($provider, $api_key, $store_id, $is_fulfilement) {
		$this->provider = $provider;
		$this->api_key = $api_key;
		$this->store_id = $store_id;
		$this->is_fulfilement = $is_fulfilement;
	}

	/**
	 * Start the sync process - call this from your AJAX handler
	 */
	public function start_sync() {
		// Clear any pending sync actions first
		$this->clear_pending_actions();

		// Get total pages first
		$total_pages = $this->get_total_pages();

		if ( $total_pages === false ) {
			return [
				'success' => false,
				'message' => 'Failed to connect to MDM Express API'
			];
		}

		// Schedule fetching each page
		for ( $page = 1; $page <= $total_pages; $page++ ) {
			as_schedule_single_action(
				time() + ( $page * 5 ), // 5 seconds apart
				self::ACTION_FETCH_PAGE,
				[ 'page' => $page ],
				'mdm-sync'
			);
		}

		// Schedule missing products creation after all pages are fetched
		$delay_for_create = ( $total_pages * 5 ) + 30; // Wait for all fetches + 30s buffer

		as_schedule_single_action(
			time() + $delay_for_create,
			self::ACTION_SYNC_COMPLETE,
			[],
			'mdm-sync'
		);

		// Save sync status
		update_option( 'mdm_sync_status', [
			'started_at'  => current_time( 'mysql' ),
			'total_pages' => $total_pages,
			'status'      => 'running'
		]);

		return [
			'success' => true,
			'message' => sprintf( 'Sync started! Processing %d pages in background.', $total_pages )
		];
	}

	/**
	 * Get total pages from MDM Express API
	 */
	private function get_total_pages() {
		$url = $this->provider['api_url'] . "/products/search";

		$data = [
			'filters'    => new \stdClass(),
			'sortBy'     => [ 'name' => 'ASC' ],
			'fields'     => [ 'trackingId' ],
			'pagination' => [ 'page' => 1, 'perPage' => 1 ]
		];

		$request = wp_safe_remote_post( $url, [
			'headers'     => [
				'Content-Type' => 'application/json',
				'x-api-key'   => $this->api_key
			],
			'body'        => wp_json_encode( $data ),
			'timeout'     => 30
		]);

		if ( is_wp_error( $request ) ) {
			return false;
		}

		$json = json_decode( wp_remote_retrieve_body( $request ), true );

		return isset( $json['pagination']['totalPages'] )
			? (int) $json['pagination']['totalPages']
			: false;
	}

	/**
	 * Process a single page from MDM Express (Action Scheduler callback)
	 */
	public function process_mdm_page( $page ) {
		$url = $this->provider['api_url'] . "/products/search";

		$data = [
			'filters'    => new \stdClass(),
			'sortBy'     => [ 'name' => 'ASC' ],
			'fields'     => [ 'name', 'trackingId', 'variantDefinitions', 'isFulfilledByUs', 'sku' ],
			'pagination' => [ 'page' => $page, 'perPage' => 100 ]
		];

		$request = wp_safe_remote_post( $url, [
			'headers'     => [
				'Content-Type' => 'application/json',
				'x-api-key'   => $this->api_key
			],
			'body'        => wp_json_encode( $data ),
			'timeout'     => 60
		]);

		if ( is_wp_error( $request ) ) {
			error_log( "MDM sync page {$page} failed: " . $request->get_error_message() );
			return;
		}

		$json = json_decode( wp_remote_retrieve_body( $request ), true );

		if ( ! isset( $json['list'] ) ) {
			return;
		}

		// Match products by SKU
		foreach ( $json['list'] as $item ) {
			$product_id = wc_get_product_id_by_sku( $item['sku'] );

			if ( $product_id ) {
				$existing_id = get_post_meta( $product_id, '_mdm_product_id', true );

				if ( empty( $existing_id ) ) {
					update_post_meta( $product_id, '_mdm_product_id', $item['trackingId'] );
					error_log( "✅ Matched SKU {$item['sku']} -> {$item['trackingId']}" );
				}
			}
		}

		// Update progress
		$this->update_sync_progress( $page );
	}

	/**
	 * Called after all pages are fetched - schedule creation of missing products
	 */
	public function on_sync_complete() {
		// Get products without mdm_product_id
		$args = [
			'status'     => 'publish',
			'limit'      => -1,
			'type'       => [ 'simple', 'variable' ],
			'return'     => 'ids',
			'meta_query' => [
				'relation' => 'OR',
				[
					'key'     => '_mdm_product_id',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => '_mdm_product_id',
					'value'   => '',
					'compare' => '=',
				],
			],
		];

		$product_ids = wc_get_products( $args );

		if ( empty( $product_ids ) ) {
			$this->finish_sync( 'No new products to create' );
			return;
		}

		// Schedule each product creation
		$delay = 0;
		foreach ( $product_ids as $product_id ) {
			as_schedule_single_action(
				time() + $delay,
				self::ACTION_CREATE_PRODUCT,
				[ 'product_id' => $product_id ],
				'mdm-sync'
			);
			$delay += 2; // 2 seconds between each
		}

		update_option( 'mdm_sync_status', [
			'status'           => 'creating_products',
			'products_to_create' => count( $product_ids )
		]);
	}

	/**
	 * Create a single product in MDM Express (Action Scheduler callback)
	 */
	public function create_product_in_mdm( $product_id ) {
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return;
		}

		// Double check it still needs creating
		$existing_id = get_post_meta( $product_id, '_mdm_product_id', true );
		if ( ! empty( $existing_id ) ) {
			return;
		}

		$url = $this->provider['api_url'] . "/api/v2/products";

		$category_id = get_option( $this->provider['slug'] . '_category_id' );
		$category_id = ! empty( $category_id ) ? $category_id : self::DEFAULT_CATEGORY_ID;

		$max_dim = max( (float) $product->get_length(), (float) $product->get_width(), (float) $product->get_height() );
		if ( $max_dim <= 0 || $max_dim <= 30 ) {
			$size = 'small';
		} elseif ( $max_dim <= 60 ) {
			$size = 'medium';
		} elseif ( $max_dim <= 120 ) {
			$size = 'big';
		} else {
			$size = 'giant';
		}

		$data = [
			'storeId'                => $this->store_id,
			'name'                   => $product->get_name(),
			'description'            => $product->get_name(),
			'categoryId'             => $category_id,
			'isFulfilledByUs'        => $this->is_fulfilement,
			'isLeadBecomeOutOfStock'  => false,
			'pricing[selling]'       => $product->get_price(),
			'pricing[purchasing]'    => 0,
			'sku'                    => $product->get_sku() ?: 'WC-' . $product->get_id(),
			'creativeLink'           => '',
			'weight'                 => (int) ( $product->get_weight() ?: 1 ),
			'size'                   => $size,
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
		]);

		$response = json_decode( curl_exec( $curl ), true );
		curl_close( $curl );

		if ( isset( $response['trackingId'] ) ) {
			update_post_meta( $product_id, '_mdm_product_id', $response['trackingId'] );
			error_log( "✅ Created product {$product_id} -> {$response['trackingId']}" );

			// Schedule variations if variable product
			if ( $product->is_type( 'variable' ) ) {
				$this->schedule_variations( $product, $response['trackingId'] );
			}
		} else {
			error_log( "❌ Failed to create product {$product_id}: " . print_r( $response, true ) );
		}
	}

	/**
	 * Schedule variation creation for a variable product
	 */
	private function schedule_variations( $product, $parent_tracking_id ) {
		$variations = $product->get_available_variations();
		$delay = 0;

		foreach ( $variations as $variation ) {
			$variation_id = $variation['variation_id'];
			$existing_id = get_post_meta( $variation_id, '_mdm_variant_id', true );

			if ( empty( $existing_id ) ) {
				as_schedule_single_action(
					time() + $delay,
					self::ACTION_CREATE_VARIATION,
					[
						'variation_id'      => $variation_id,
						'parent_tracking_id' => $parent_tracking_id
					],
					'mdm-sync'
				);
				$delay += 1;
			}
		}
	}

	/**
	 * Create a single variation in MDM Express (Action Scheduler callback)
	 */
	public function create_variation_in_mdm( $variation_id, $parent_tracking_id ) {
		$variation = wc_get_product( $variation_id );

		if ( ! $variation ) {
			return;
		}

		// Double check
		$existing_id = get_post_meta( $variation_id, '_mdm_variant_id', true );
		if ( ! empty( $existing_id ) ) {
			return;
		}

		$parent = wc_get_product( $variation->get_parent_id() );
		$variation_name = $parent->get_name() . ' - ' . implode( ' ', $variation->get_variation_attributes() );

		$data = [
			'name'                => $variation_name,
			'pricing[selling]'    => $variation->get_price(),
			'pricing[purchasing]' => 0,
			'sku'                 => $variation->get_sku(),
			'weight'              => (int) $variation->get_weight(),
		];

		$url = $this->provider['api_url'] . sprintf( "/products/%s/variants", $parent_tracking_id );

		$request = wp_safe_remote_post( $url, [
			'headers'     => [
				'Content-Type' => 'application/json',
				'x-api-key'   => $this->api_key
			],
			'body'        => wp_json_encode( $data ),
			'timeout'     => 15
		]);

		if ( ! is_wp_error( $request ) ) {
			$response = json_decode( wp_remote_retrieve_body( $request ), true );

			if ( isset( $response['trackingId'] ) ) {
				update_post_meta( $variation_id, '_mdm_variant_id', $response['trackingId'] );
				error_log( "✅ Created variation {$variation_id} -> {$response['trackingId']}" );
			}
		}
	}

	/**
	 * Helper: Update sync progress
	 */
	private function update_sync_progress( $page ) {
		$status = get_option( 'mdm_sync_status', [] );
		$status['pages_processed'] = $page;
		update_option( 'mdm_sync_status', $status );
	}

	/**
	 * Helper: Mark sync as finished
	 */
	private function finish_sync( $message = '' ) {
		update_option( 'mdm_sync_status', [
			'status'      => 'completed',
			'completed_at' => current_time( 'mysql' ),
			'message'     => $message
		]);
	}

	/**
	 * Helper: Clear pending actions
	 */
	private function clear_pending_actions() {
		as_unschedule_all_actions( self::ACTION_FETCH_PAGE, [], 'mdm-sync' );
		as_unschedule_all_actions( self::ACTION_CREATE_PRODUCT, [], 'mdm-sync' );
		as_unschedule_all_actions( self::ACTION_CREATE_VARIATION, [], 'mdm-sync' );
		as_unschedule_all_actions( self::ACTION_SYNC_COMPLETE, [], 'mdm-sync' );
	}

	/**
	 * Get current sync status (for AJAX polling)
	 */
	public function get_sync_status() {
		$status = get_option( 'mdm_sync_status', [] );

		// Count pending actions
		$pending = as_get_scheduled_actions([
			'group'  => 'mdm-sync',
			'status' => ActionScheduler_Store::STATUS_PENDING,
		], 'ARRAY_A' );

		$status['pending_actions'] = count( $pending );

		return $status;
	}
}
