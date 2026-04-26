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
class ZimoExpress extends \WooBordereauGenerator\Admin\Shipping\BordereauProvider implements \WooBordereauGenerator\Admin\Shipping\BordereauProviderInterface
{

    /**
     * The ZimoExpress API token.
     * 
     * @var string
     */
    private string $api_token;


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
    private $api_id;

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
        $this->api_id = get_option('zimoexpress_username');
        $this->api_token = get_option('zimoexpress_password');
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
            $wilayaId = (int) str_replace("DZ-", "", $this->data['wilaya']);


            $wilaya = array_filter($wilayas['data'], function ($item) use ($wilayaId){
                return $item['id'] == $wilayaId;
            });

            $wilaya = reset($wilaya);

        } else {
            $wilayaId = (int) str_replace("DZ-", "", $params['billing']['state']);
            $wilaya = array_filter($wilayas['data'], function ($item) use ($wilayaId){
                return $item['id'] == $wilayaId;
            });

            $wilaya = reset($wilaya);
        }

        $commune = $this->data['commune'] ?? $params['billing']['city'];


        if (isset($this->data['products'])) {
            $productString = $this->data['products'];
        } else {
            $products = $params['line_items'];
            $productString = Helpers::generate_products_string($products);
        }

        $free_shipping = 0;
        $stop_desk = 0;

        if ($this->formData->has_shipping_method('local_pickup') && $this->data['shipping_type'] === 'stopdesk') {
            $stop_desk = 1;
        }

        if ($this->data['shipping_type'] === 'stopdesk') {
            $stop_desk = 1;
        }

        if ($this->data['free'] == "true") {
            $free_shipping = 1;
        }
        if ($this->formData->has_shipping_method('free_shipping')) {
            $free_shipping = 1;
        }

        $phone = explode('/', $this->data['phone'] ?? $params['billing']['phone']);
        $price = $this->data['price'] ?? $params['total'] - $params['shipping_total'];
        $name = "";

        if ($this->data['lname'] || $this->data['fname']) {
            $name = $this->data['fname'] . ' ' . $this->data['lname'];
        } elseif ($params['billing']['first_name'] || $params['billing']['last_name']) {
            $name = $params['billing']['first_name'] . ' ' . $params['billing']['last_name'];
        }

        $request = new \WooBordereauGenerator\Admin\Shipping\ThreeMExpressRequest($this->provider);
        $userInfo = $request->getUserInfo();

        $userCommuneId = $userInfo['stores'][0]['municipality']['id'];

        if ($this->data['remarque']) {
            $note = $this->data['remarque'];
        } else {
            $note = stripslashes(get_option('wc_bordreau_default-shipping-note'));
        }

        $data = array (
            'name' => $productString,
            'client_last_name' => !empty($this->data['lname']) ? $this->data['lname'] : $this->data['fname'],
            'client_first_name' => !empty($this->data['fname']) ? $this->data['fname'] : $this->data['lname'],
            'client_phone' => $this->data['phone'],
            'client_phone2' => $this->data['phone_2'],
            'address' => $this->data['address'] ?? $params['billing']['address_1'],
            'commune' => $commune,
            'wilaya' => $wilaya['name'],
            'order_id' => $orderId,
            "weight" => 1000,
            'delivery_type' => $stop_desk ? 'Point relais': 'express',
            'price' => $price,
            'free_delivery' => $free_shipping,
            'can_be_opened' => 1,
            'observation' => $note,
            'type' => $this->data['operation_type'],
            'detail' =>
                array (
                    'is_assured' => $this->data['do_insurance'],
                    'declared_value' => (int) $this->data['declared_value'],
                ),
        );

		if ($stop_desk) {
			$data['office_id'] = $this->data['center'];
		}

        $url = $this->provider['api_url'] . '/api/v1/packages';

//        if (isset($this->data['is_update'])) {
//            $orderProviderId = get_post_meta($orderId, '_shipping_zimoexpress_order_id', true);
//            $url = $this->provider['api_url'] . "/customers/orders/" . $orderProviderId;
//            unset($data['operationType']);
//        } else {
//            $url = $this->provider['api_url'] . '/customers/orders';
//        }

        $request = new \WooBordereauGenerator\Admin\Shipping\ZimoExpressRequest($this->provider);
        $method = \WooBordereauGenerator\Admin\Shipping\ZimoExpressRequest::POST;

        if (isset($this->data['is_update'])) {
            $method = \WooBordereauGenerator\Admin\Shipping\ZimoExpressRequest::PATCH;
        }

        	
		if(empty($data['address'])) {
			$data['address'] = 'ville';
		}

        $content = $request->post($url, $data, $method);



        if (isset($content['statusCode']) && $content['statusCode']) {
            wp_send_json([
                'error' => true,
                'message' => $content['message']
            ], 401);
        }
        //

        if (is_wp_error($request)) {
            wp_send_json([
                'error' => true,
                'message' => __("An error has occurred. Please see log file ", 'woo-bordereau-generator')
            ], 401);
        }


        if (isset($this->data['is_update'])) {
            $content['id'] = $orderProviderId ?? null;
        }


        return $content;
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

        $trackingNumber = $response['data']['tracking_code'];
        $label = $response['data']['bordereau'];
        $uuid = $response['data']['uuid'];

        if ($update) {
            $trackingNumber = get_post_meta($post_id, '_shipping_tracking_number', true);
            if(! $trackingNumber) {
                $trackingNumber = $order->get_meta('_shipping_tracking_number');
            }

            $label = get_post_meta($post_id, '_shipping_tracking_label', true);

            if(! $label) {
                $label = $order->get_meta('_shipping_tracking_label');
            }

        } else {

            update_post_meta($post_id, '_shipping_tracking_number', wc_clean($trackingNumber)); // backwork compatibilite
            update_post_meta($post_id, '_shipping_zimo_express_uuid', wc_clean($uuid)); // backwork compatibilite
            update_post_meta($post_id, '_shipping_tracking_label', wc_clean($label));
            update_post_meta($post_id, '_shipping_label', wc_clean($label));
            update_post_meta($post_id, '_shipping_tracking_url', 'https://'.$this->provider['url'].'/suivre-un-colis/?tracking='. wc_clean($trackingNumber));
            update_post_meta($post_id, '_shipping_tracking_method', $this->provider['slug']);

            if($order) {
                $order->update_meta_data('_shipping_tracking_number', wc_clean($trackingNumber));
                $order->update_meta_data('_shipping_tracking_label', wc_clean($label));
                $order->update_meta_data('_shipping_zimo_express_uuid', wc_clean($uuid));
                $order->update_meta_data('_shipping_label', wc_clean($label));
                $order->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
                $order->save();

                Helpers::make_note($order, $trackingNumber, $this->provider['name']);
            }
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
        $wilaya_id = (int)str_replace('DZ-', '', $wilaya_id);
        
        // Get communes data with caching
        $communes_path = Functions::get_path('zimoexpress_communes.json');
        if (!file_exists($communes_path)) {
            $url = $this->provider['api_url'] . '/api/v1/helpers/communes';
            $request = new ZimoExpressRequest($this->provider);
            $response_array = $request->get($url);
            file_put_contents($communes_path, json_encode($response_array));
        }
        $communes_data = json_decode(file_get_contents($communes_path), true);
        
        // Get offices data with caching
        $offices_path = Functions::get_path('zimoexpress_offices.json');
        if (!file_exists($offices_path)) {
            $url = $this->provider['api_url'] . '/api/v1/helpers/offices';
            $request = new ZimoExpressRequest($this->provider);
            $offices_response = $request->get($url);
            file_put_contents($offices_path, json_encode($offices_response));
        }
        $offices_data = json_decode(file_get_contents($offices_path), true);
        
        // Create a lookup of commune IDs that have stop desks
        $communes_with_stopdesk = [];
        if (isset($offices_data['data']) && is_array($offices_data['data'])) {
            foreach ($offices_data['data'] as $office) {
                if (isset($office['commune_id'])) {
                    $communes_with_stopdesk[$office['commune_id']] = true;
                }
            }
        }
        
        // Filter communes by wilaya_id
        $communes = [];
        if (isset($communes_data['data']) && is_array($communes_data['data'])) {
            $communes = array_filter($communes_data['data'], function ($item) use ($wilaya_id) {
                return $item['wilaya_id'] == $wilaya_id;
            });
        }
        
        // Format the communes with stop desk information
        $communesResult = [];
        foreach ($communes as $i => $item) {
            $has_stop_desk = isset($communes_with_stopdesk[$item['id']]);
            
            // If hasStopDesk is enabled, only include communes that actually have stop desks
            if ($hasStopDesk && !$has_stop_desk) {
                continue;
            }
            
            $communesResult[] = [
                'id' => $item['name'],
                'name' => $item['name'],
                'code' => $item['id'],
                'has_stop_desk' => $has_stop_desk
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


                if ($item['status'] === 'RETOURNEE AU VENDEUR (SAC)') {
                    $result[$key]['failed'] = true;
                }

                if (strpos($item['status'], 'LIVRÉ') !== false) {
                    $result[$key]['delivered'] = true;
                    if (isset($result[$key]['failed'])) {
                        $result[$key]['failed'] = false;
                    }
                }

                $result[$key]['date'] = null;
                $result[$key]['location'] = null;
                $result[$key]['status'] = $item['status'];
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

        $detail[0]['address'] = $item['destination_text'];
        $detail[0]['phone'] = $item['customer_phone'];
        $detail[0]['fname'] = $item['customer_name'];
        $detail[0]['free'] = false;
        $detail[0]['exchange'] = false;
        $detail[0]['stopdesk'] = false;
        $detail[0]['last_status'] = isset($status[0]) ? $status[0]['status'] : '';
        $detail[0]['order_id'] = $item['external_order_id'];
        $detail[0]['price'] = $item['product_price'] + $item['price'];
        $detail[0]['commune'] = $item['commune_name'];
        $detail[0]['wilaya'] = $wilayaId ?? $item['wilaya'];
        $detail[0]['product_id'] = isset($item['products']) && count($item['products']) ? $item['products'][0]['product_id'] : null;
        $detail[0]['product_qty'] = isset($item['products']) && count($item['products']) ? $item['products'][0]['quantity'] : null;
        $detail[0]['product_name'] = isset($item['products']) && count($item['products']) ? $item['products'][0]['logistical_description'] : null;

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

        $path = Functions::get_path('zimoexpress_wilaya.json');

        if (!file_exists($path)) {

            $request = new \WooBordereauGenerator\Admin\Shipping\ZimoExpressRequest($this->provider);
            $content = $request->get($this->provider['api_url'] . '/api/v1/helpers/wilayas');

            file_put_contents($path, json_encode($content));
        }

        $wilayas = json_decode(file_get_contents($path), true);

        return $wilayas;
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
        $url = $this->provider['api_url'] . '/api/v1/packages/status';
        $request = new \WooBordereauGenerator\Admin\Shipping\ZimoExpressRequest($this->provider);

        $content = $request->get($url, false, [
            'packages' => [$tracking_number]
        ]);


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

        $path = Functions::get_path('zimoexpress_wilaya.json');

        if (!file_exists($path)) {
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
            foreach ($result['data'] as $i => $wilaya) {
                $wilayasResult[$i]['name'] = $wilaya['name'];
                $wilayasResult[$i]['id'] = $wilaya['id'];
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

        if ($tracking_number) {

            $data = [
                'tracking_codes' => [$tracking_number]
            ];

            $url = $this->provider['api_url'] . "/api/v1/packages/bulk";

            $request = new \WooBordereauGenerator\Admin\Shipping\ZimoExpressRequest($this->provider);
            $request->post($url, $data, \WooBordereauGenerator\Admin\Shipping\ZimoExpressRequest::DELETE);
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

        $label = $this->formData->get_meta('_shipping_tracking_label', true);

        if (! $label) {
            $label = $this->formData->get_meta('_shipping_label', true);
        }

        header('Location: '. $label);
        exit;
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

        $url = $this->provider['api_url'] . "/api/v1/packages/status";


        $request = new \WooBordereauGenerator\Admin\Shipping\ZimoExpressRequest($this->provider);

        $content = $request->get($url, false, [
            'packages' => [$tracking_number]
        ]);


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
		$commune = str_replace('&quot;', "'", $commune);

		// Create cache path using date-based versioning to refresh daily
		$cache_path = Functions::get_path($this->provider['slug'].'_centers_'.WC_BORDEREAU_GENERATOR_VERSION.'_'.date('Y').'_'. (date('z') + 1).'.json');
		
		if (!file_exists($cache_path)) {
			// Fetch fresh data from API
			try {
				$request = new ZimoExpressRequest($this->provider);
				$response = $request->get($this->provider['api_url'] . '/api/v1/helpers/offices');

				if (isset($response['data']) && is_array($response['data'])) {
					// Cache the data
					file_put_contents($cache_path, json_encode($response['data']));
				} else {
					// Return empty array if API response is invalid
					return [];
				}
			} catch (\Exception $e) {
				// Log error and return empty array
				error_log('ZimoExpress get_centers error: ' . $e->getMessage());
				return [];
			}
		}
		
		// Load centers from cache
		$centers = json_decode(file_get_contents($cache_path), true);

		$communes = $this->get_communes($_POST['wilaya'], true);


		// Filter centers by commune
		$filtered_centers = array_filter($centers, function ($item) use ($commune, $communes) {

			$found = array_filter($communes, function ($c) use ($commune) {
				return $c['name'] == $commune;
			});

			$found = reset($found);
			return $item['commune_id'] == $found['code'];
		});
		
		// Format the results
		$communesResult = [];
		foreach ($filtered_centers as $i => $item) {
			$communesResult[] = [
				'id' => $item['id'],
				'name' => sprintf('%s (%s)', $item['partner_company_name'], $item['address']),
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
