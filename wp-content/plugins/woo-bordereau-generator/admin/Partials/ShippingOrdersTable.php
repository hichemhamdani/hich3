<?php

namespace WooBordereauGenerator\Admin\Partials;

use WooBordereauGenerator\PDFMerger;

if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

class ShippingOrdersTable extends \WP_List_Table {

    protected array $provider;

    public function __construct($provider, $args = array())
    {
        parent::__construct($args);
        $this->provider = $provider;

    }

    /**
     * @return array
     */
    function get_columns()
    {
        return [
            "cb"                    => "<input type='checkbox'/>",
            'tracking'              => __('Tracking', 'woo-bordereau-generator'),
            'last_status'           => __('Status', 'woo-bordereau-generator'),
            'name'                  => __('Name', 'woo-bordereau-generator'),
            'phone'                 => __('Phone', 'woo-bordereau-generator'),
            'city'                  => __('City', 'woo-bordereau-generator'),
            'state'                 => __('State', 'woo-bordereau-generator'),
            'date_last_status'      => __('Latest Update', 'woo-bordereau-generator'),
            'action'                => __('Action', 'woo-bordereau-generator')
        ];
    }


    public function no_items() {
        _e( 'No orders available.', 'woo-bordereau-generator' );
    }

    /**
     * Add extra markup in the toolbars before or after the list
     * @param string $which, helps you decide if you add the markup after (bottom) or before (top) the list
     */
    function extra_tablenav( $which ) {
        if ( $which == "top" ){
            //The code that goes before the table is here
            include "views/table_list_orders_header.php";
        }
        if ( $which == "bottom" ){
            //The code that goes after the table is there
            include "views/table_list_orders_footer.php";
        }
    }

    /**
     * Prepare the items for the table to process
     *
     * @return Void
     */
    public function prepare_items()
    {
        $columns = $this->get_columns();

        $hidden = $this->get_hidden_columns();

        $sortable = $this->get_sortable_columns();

        $this->process_bulk_action();

        $perPage = 10;
        $currentPage = $this->get_pagenum();

        list($data, $total_page) = $this->table_data($perPage, $currentPage);

//        usort( $data, array( &$this, 'sort_data' ) );

        $totalItems = $total_page;

        $this->set_pagination_args( array(
            'total_items' => $totalItems,
            'per_page'    => $perPage
        ) );


        $this->_column_headers = array($columns, $hidden, $sortable);
        $this->items = $data;
    }

    /**
     * Define which columns are hidden
     *
     * @return array
     */
    public function get_hidden_columns(): array
    {
        return array();
    }

    /**
     * Define the sortable columns
     *
     * @return array
     */
    public function get_sortable_columns(): array
    {
        return [
			'tracking' => [ 'tracking', true ],
			'last_status' => [ 'last_status' , true ],
			'date_last_status' => [ 'date_last_status' , true ]
        ];
    }

    /**
     * Get the table data
     *
     * @return array
     */
    private function table_data($perPage, $currentPage): ?array
    {

		$data = [];
        $queryData = [
			"page_size" => $perPage,
			"page" => $currentPage,
        ];

        // get settings page and then function get_orders if found then pass the arguments to the function


	    if (isset($_GET['range']) && $_GET['range']) {
		    $date_range = array_map('intval',explode( '-', sanitize_text_field($_GET['range'])));

		    if (6 === count($date_range)) {

			    $queryData["date_creation"] = $date_range[0].'-'.$date_range[1].'-'.$date_range[2].','.$date_range[3].'-'.$date_range[4].'-'.$date_range[5];
		    }
	    }

		if (isset($_GET['status']) && $_GET['status'] ) {
			$queryData['last_status'] = sanitize_text_field($_GET['status']);
		}

		if (isset($_GET['query']) && $_GET['query']
//		    && isset($_GET['type']) && $_GET['type']
		) {

			$query = mb_strtolower(sanitize_text_field($_GET['query']));
		    $queryData["tracking"] = $query;
		}

		if (isset($_GET['orderby'])) {
			switch ($_GET['orderby']) {
				case "tracking":
					$queryData['order_by'] = 'tracking';
					break;
				case "last_status":
					$queryData['order_by'] = 'last_status';
					break;
				case "date_last_status":
					$queryData['order_by'] = "date_last_status";
					break;

			}
		} else {
            $queryData['order_by'] = 'date_creation';
        }

		if (isset($_GET['order'])) {
			if ($_GET['order'] === 'asc') {
				$queryData['asc'] = 'asc';
			}

			if ($_GET['order'] === 'desc') {
				$queryData['desc'] = 'desc';
			}
		}


        $class = new $this->provider['setting_class']($this->provider);
        if (method_exists($class, 'get_orders')) {
            $data = $class->get_orders($queryData);

            $result = [];
            if (is_array($data)) {
                foreach ($data['items'] as $key => $item) {
                    $result[$key]['tracking'] = $item['tracking'];
                    $result[$key]['last_status'] = $item['last_status'];
                    $result[$key]['name'] = $item['familyname']. ' ' . $item['firstname'];
                    $result[$key]['phone'] = $item['contact_phone'];
                    $result[$key]['city'] = $item['to_commune_name'];
                    $result[$key]['state'] = $item['to_wilaya_name'];
                    $result[$key]['date_last_status'] = $item['date_last_status'];
                    $result[$key]['label'] = $item['label'];
                }

                return [$result, $data['total_data']];
            }
        }

		return null;
    }


    public function get_bulk_actions() {

        $actions = array(
            'print' => __( 'Print Labels', 'woo-bordereau-generator' ),
        );

        return $actions;

    }

    public function process_bulk_action() {
        // Check user capabilities first
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Sorry, you are not allowed to access this page.'));
        }

        // Security check - handle both GET and POST nonces
        $nonce_verified = false;
        
        if (isset($_POST['_wpnonce']) && !empty($_POST['_wpnonce'])) {
            $nonce = filter_input(INPUT_POST, '_wpnonce', FILTER_SANITIZE_STRING);
            $action = 'bulk-' . $this->_args['plural'];
            if (wp_verify_nonce($nonce, $action)) {
                $nonce_verified = true;
            }
        } elseif (isset($_GET['_wpnonce']) && !empty($_GET['_wpnonce'])) {
            $nonce = filter_input(INPUT_GET, '_wpnonce', FILTER_SANITIZE_STRING);
            if (wp_verify_nonce($nonce, 'bulk-' . $this->_args['plural'])) {
                $nonce_verified = true;
            }
        }

        $action = $this->current_action();

        // Only process if we have an action
        if (!$action) {
            return;
        }

        // For certain actions, require nonce verification
        if (in_array($action, ['print', 'delete']) && !$nonce_verified) {
            wp_die('Nope! Security check failed!');
        }

        switch ($action) {
            case 'print':
                // Get selected tracking numbers
                $tracking_numbers = isset($_REQUEST['tracking']) ? (array) $_REQUEST['tracking'] : [];
                
                if (empty($tracking_numbers)) {
                    return;
                }

                // Check if provider supports bulk labels via API
                $class = new $this->provider['setting_class']($this->provider);
                
                if (method_exists($class, 'get_bulk_labels_url')) {
                    // Use provider's native bulk labels API
                    $result = $class->get_bulk_labels_url($tracking_numbers);
                    
                    if ($result['success'] && !empty($result['fileUrl'])) {
                        // Redirect to the PDF URL
                        wp_redirect($result['fileUrl']);
                        exit;
                    } else {
                        // Show error message
                        wp_die(
                            esc_html($result['message'] ?? __('Failed to generate bulk labels', 'woo-bordereau-generator')),
                            __('Error', 'woo-bordereau-generator'),
                            ['back_link' => true]
                        );
                    }
                } else {
                    // Fallback: feature not available for this provider
                    wp_die(
                        __('Bulk print is not available for this provider', 'woo-bordereau-generator'),
                        __('Feature Not Available', 'woo-bordereau-generator'),
                        ['back_link' => true]
                    );
                }
                break;

            default:
                // do nothing or something else
                return;
                break;
        }

        return;
    }


    /**
     * Get status display name from provider's status mapping
     *
     * @param string $status_key Status key from API
     * @return string Display name
     */
    private function get_status_display_name( $status_key ) {
        // Try to get status mapping from provider
        $class = new $this->provider['setting_class']($this->provider);
        
        if (method_exists($class, 'get_status')) {
            $statuses = $class->get_status();
            
            // Try exact match first
            if (isset($statuses[$status_key])) {
                return $statuses[$status_key];
            }
            
            // Try to find by value (reverse lookup)
            $found = array_search($status_key, $statuses);
            if ($found !== false) {
                return $status_key; // Already display name
            }
            
            // Try lowercase key match
            $lower_key = strtolower(str_replace([' ', '-', '_'], '_', $status_key));
            foreach ($statuses as $key => $value) {
                if (strtolower($key) === $lower_key) {
                    return $value;
                }
            }
        }
        
        // Return original if no mapping found
        return $status_key;
    }

    /**
     * Get status color class based on status type
     *
     * @param string $status Status text
     * @return string CSS classes for the status badge
     */
    private function get_status_color_class( $status ) {
        $status_lower = strtolower($status);
        
        // Success statuses (green)
        $success_keywords = ['livré', 'delivered', 'livre', 'encaissé', 'encaisse', 'recouvert'];
        foreach ($success_keywords as $keyword) {
            if (strpos($status_lower, $keyword) !== false) {
                return 'tw-bg-lime-200 tw-text-lime-900';
            }
        }
        
        // Warning statuses (yellow)
        $warning_keywords = ['retour', 'return', 'alerte', 'alert', 'attente', 'waiting', 'échec', 'echoue', 'failed'];
        foreach ($warning_keywords as $keyword) {
            if (strpos($status_lower, $keyword) !== false) {
                return 'tw-bg-yellow-200 tw-text-yellow-900';
            }
        }
        
        // Error statuses (red)
        $error_keywords = ['annulé', 'cancelled', 'refusé', 'refused', 'perdu', 'lost'];
        foreach ($error_keywords as $keyword) {
            if (strpos($status_lower, $keyword) !== false) {
                return 'tw-bg-red-200 tw-text-red-900';
            }
        }
        
        // Info statuses (blue)
        $info_keywords = ['en cours', 'processing', 'livraison', 'delivery', 'dispatch', 'transit'];
        foreach ($info_keywords as $keyword) {
            if (strpos($status_lower, $keyword) !== false) {
                return 'tw-bg-blue-200 tw-text-blue-900';
            }
        }
        
        // Default (gray)
        return 'tw-bg-gray-200 tw-text-gray-900';
    }

    /**
     * Define what data to show on each column of the table
     *
     * @param  array $item        Data
     * @param  String $column_name - Current column name
     *
     * @return Mixed
     */
    public function column_default( $item, $column_name )
    {
        switch( $column_name ) {
            case 'tracking':
                return '<div class="my-2"><span class="py-2 my-2 font-bold">'.strtoupper($item['tracking']).'</span></div>';
            case 'last_status':
                $status_display = $this->get_status_display_name($item[$column_name]);
                $color_class = $this->get_status_color_class($status_display);
                return '<div class="my-2"><span class="py-2 my-2 px-4 ' . $color_class . ' font-semibold rounded-sm text-xs">' . esc_html($status_display) . '</span></div>';
            case 'name':
            case 'city':
            case 'state':
            case 'date_last_status':
                return '<div class="my-2"><span class="py-2 my-2">'. esc_html($item[ $column_name ]). '</span></div>';
            case 'action':
                return '<a href="'. esc_url($item['label']). '" target="_blank" class="button wc-action-button wc-action-button-zu-printer px-0 flex items-center justify-center"><span class="dashicons dashicons-printer"></span></a>';
	        case 'phone':
				return '<a href="tel:'.esc_attr($item[ $column_name ]).'">'.esc_html($item[ $column_name ]).'</a>';
	        default:
                return print_r( $item, true ) ;
        }
    }

    /**
     * Render the bulk edit checkbox
     *
     * @param array $item
     *
     * @return string
     */
    function column_cb( $item )
    {
        return sprintf(
            '<div class="my-2"><input form="bulk-actions-form" type="checkbox" name="tracking[]" value="%s" /></div>', $item['tracking']
        );
    }
}
