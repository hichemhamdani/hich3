<?php

namespace WooBordereauGenerator\Admin\Products;

class ZRExpressTwoProductSync {
	private static $instance = null;

	private $provider;
	private $api_key;
	private $tenant_id;
	private $stock_type;
	private $category_id;
	private $subcategory_id;

	// Action hooks
	const ACTION_FETCH_PAGE = 'zrexpress_v2_fetch_products_page';
	const ACTION_CREATE_PRODUCT = 'zrexpress_v2_create_product';
	const ACTION_SYNC_COMPLETE = 'zrexpress_v2_sync_complete';

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

		add_action( self::ACTION_FETCH_PAGE, [ $instance, 'process_zrexpress_page' ], 10, 1 );
		add_action( self::ACTION_CREATE_PRODUCT, [ $instance, 'create_product_in_zrexpress' ], 10, 1 );
		add_action( self::ACTION_SYNC_COMPLETE, [ $instance, 'on_sync_complete' ], 10, 0 );
	}

	/**
	 * Configure the sync instance with API credentials and settings
	 */
	public function configure($provider, $api_key, $tenant_id, $stock_type, $category_id, $subcategory_id) {
		$this->provider = $provider;
		$this->api_key = $api_key;
		$this->tenant_id = $tenant_id;
		$this->stock_type = $stock_type;
		$this->category_id = $category_id;
		$this->subcategory_id = $subcategory_id;
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
				'message' => 'Failed to connect to ZR Express 2.0 API'
			];
		}

		// Schedule fetching each page
		for ( $page = 1; $page <= $total_pages; $page++ ) {
			as_schedule_single_action(
				time() + ( $page * 5 ), // 5 seconds apart
				self::ACTION_FETCH_PAGE,
				[ 'page' => $page ],
				'zrexpress-v2-sync'
			);
		}

		// Schedule missing products creation after all pages are fetched
		$delay_for_create = ( $total_pages * 5 ) + 30; // Wait for all fetches + 30s buffer

		as_schedule_single_action(
			time() + $delay_for_create,
			self::ACTION_SYNC_COMPLETE,
			[],
			'zrexpress-v2-sync'
		);

		// Save sync status
		update_option( 'zrexpress_v2_sync_status', [
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
	 * Get total pages from ZR Express 2.0 API
	 */
	private function get_total_pages() {
		$url = $this->provider['api_url'] . "/products/search";

		$data = [
			'pageSize' => 1,
			'pageNumber' => 1
		];

		$request = wp_safe_remote_post( $url, [
			'headers'     => [
				'Content-Type' => 'application/json',
				'X-Api-Key'    => $this->api_key,
				'X-Tenant'     => $this->tenant_id
			],
			'body'        => wp_json_encode( $data ),
			'timeout'     => 30
		]);

		if ( is_wp_error( $request ) ) {
			return false;
		}

		$json = json_decode( wp_remote_retrieve_body( $request ), true );

		return isset( $json['totalPages'] )
			? (int) $json['totalPages']
			: false;
	}

	/**
	 * Process a single page from ZR Express 2.0 (Action Scheduler callback)
	 */
	public function process_zrexpress_page( $page ) {
		$url = $this->provider['api_url'] . "/products/search";

		$data = [
			'pageSize' => 100,
			'pageNumber' => $page
		];

		$request = wp_safe_remote_post( $url, [
			'headers'     => [
				'Content-Type' => 'application/json',
				'X-Api-Key'    => $this->api_key,
				'X-Tenant'     => $this->tenant_id
			],
			'body'        => wp_json_encode( $data ),
			'timeout'     => 60
		]);

		if ( is_wp_error( $request ) ) {
			error_log( "ZR Express 2.0 sync page {$page} failed: " . $request->get_error_message() );
			return;
		}

		$json = json_decode( wp_remote_retrieve_body( $request ), true );

		if ( ! isset( $json['items'] ) ) {
			return;
		}

		// Match products by SKU
		foreach ( $json['items'] as $item ) {
			if ( empty( $item['sku'] ) ) {
				continue;
			}

			$product_id = wc_get_product_id_by_sku( $item['sku'] );

			if ( $product_id ) {
				$existing_id = get_post_meta( $product_id, 'zrexpress_v2_product_id', true );

				if ( empty( $existing_id ) ) {
					update_post_meta( $product_id, 'zrexpress_v2_product_id', $item['id'] );
					error_log( "✅ Matched SKU {$item['sku']} -> {$item['id']}" );
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
		// Get products without zrexpress_v2_product_id
		$args = [
			'status'     => 'publish',
			'limit'      => -1,
			'type'       => [ 'simple', 'variable' ],
			'return'     => 'ids',
			'meta_query' => [
				'relation' => 'OR',
				[
					'key'     => 'zrexpress_v2_product_id',
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => 'zrexpress_v2_product_id',
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
				'zrexpress-v2-sync'
			);
			$delay += 2; // 2 seconds between each
		}

		update_option( 'zrexpress_v2_sync_status', [
			'status'           => 'creating_products',
			'products_to_create' => count( $product_ids )
		]);
	}

	/**
	 * Create a single product in ZR Express 2.0 (Action Scheduler callback)
	 * NOTE: For variable products, each variation is created as a SEPARATE product (no parent-child)
	 */
	public function create_product_in_zrexpress( $product_id ) {
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return;
		}

		// Handle variable products - create each variation separately
		if ( $product->is_type( 'variable' ) ) {
			$variations = $product->get_available_variations();

			foreach ( $variations as $variation_data ) {
				$variation_id = $variation_data['variation_id'];
				$variation = wc_get_product( $variation_id );

				if ( ! $variation ) {
					continue;
				}

				// Check if variation already has ID
				$existing_id = get_post_meta( $variation_id, 'zrexpress_v2_product_id', true );
				if ( ! empty( $existing_id ) ) {
					continue;
				}

				// Create variation as separate product
				$this->create_single_product( $variation, $variation_id, $product->get_name() );
			}
		} else {
			// Simple product
			$existing_id = get_post_meta( $product_id, 'zrexpress_v2_product_id', true );
			if ( empty( $existing_id ) ) {
				$this->create_single_product( $product, $product_id );
			}
		}
	}

	/**
	 * Create a single product (helper method)
	 */
	private function create_single_product( $product, $product_id, $parent_name = null ) {
		$url = $this->provider['api_url'] . "/products";

		// Build product name
		$product_name = $product->get_name();
		if ( $parent_name && $product->is_type( 'variation' ) ) {
			$attributes = $product->get_variation_attributes();
			$attr_string = implode( ' - ', array_values( $attributes ) );
			$product_name = $parent_name . ' - ' . $attr_string;
		}

		$data = [
			'name' => $product_name,
			'sku' => $product->get_sku() ?: 'SKU-' . $product_id,
			'basePrice' => (float) $product->get_price(),
			'purchasePrice' => 0,
			'length' => (float) ( $product->get_length() ?: 0 ),
			'width' => (float) ( $product->get_width() ?: 0 ),
			'height' => (float) ( $product->get_height() ?: 0 ),
			'weight' => (float) ( $product->get_weight() ?: 0 ),
			'localStock' => (int) ( $product->get_stock_quantity() ?: 0 ),
			'categoryId' => $this->category_id,
			'subCategoryId' => $this->subcategory_id,
		];

		$request = wp_safe_remote_post( $url, [
			'headers'     => [
				'Content-Type' => 'application/json',
				'X-Api-Key'    => $this->api_key,
				'X-Tenant'     => $this->tenant_id
			],
			'body'        => wp_json_encode( $data ),
			'timeout'     => 30
		]);

		if ( is_wp_error( $request ) ) {
			error_log( "❌ Failed to create product {$product_id}: " . $request->get_error_message() );
			return;
		}

		$response = json_decode( wp_remote_retrieve_body( $request ), true );

		if ( isset( $response['id'] ) ) {
			update_post_meta( $product_id, 'zrexpress_v2_product_id', $response['id'] );
			error_log( "✅ Created product {$product_id} -> {$response['id']}" );
		} else {
			error_log( "❌ Failed to create product {$product_id}: " . print_r( $response, true ) );
		}
	}

	/**
	 * Helper: Update sync progress
	 */
	private function update_sync_progress( $page ) {
		$status = get_option( 'zrexpress_v2_sync_status', [] );
		$status['pages_processed'] = $page;
		update_option( 'zrexpress_v2_sync_status', $status );
	}

	/**
	 * Helper: Mark sync as finished
	 */
	private function finish_sync( $message = '' ) {
		update_option( 'zrexpress_v2_sync_status', [
			'status'      => 'completed',
			'completed_at' => current_time( 'mysql' ),
			'message'     => $message
		]);
	}

	/**
	 * Helper: Clear pending actions
	 */
	private function clear_pending_actions() {
		as_unschedule_all_actions( self::ACTION_FETCH_PAGE, [], 'zrexpress-v2-sync' );
		as_unschedule_all_actions( self::ACTION_CREATE_PRODUCT, [], 'zrexpress-v2-sync' );
		as_unschedule_all_actions( self::ACTION_SYNC_COMPLETE, [], 'zrexpress-v2-sync' );
	}

	/**
	 * Get current sync status (for AJAX polling)
	 */
	public function get_sync_status() {
		$status = get_option( 'zrexpress_v2_sync_status', [] );

		// Count pending actions
		$pending = as_get_scheduled_actions([
			'group'  => 'zrexpress-v2-sync',
			'status' => ActionScheduler_Store::STATUS_PENDING,
		], 'ARRAY_A' );

		$status['pending_actions'] = count( $pending );

		return $status;
	}
}
