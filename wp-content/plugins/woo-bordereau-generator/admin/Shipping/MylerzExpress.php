<?php
namespace WooBordereauGenerator\Admin\Shipping;

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    WC_Bordereau_Generator
 * @subpackage WC_Bordereau_Generator/admin/shipping
 * @author     Boukraa Mohamed <bm2ilabs@gmail.com>
 */


use DateTime;
use DateTimeZone;
use Exception;
use WC_Order;
use WC_Product;
use WooBordereauGenerator\Functions;
use WooBordereauGenerator\Admin\BordereauGeneratorAdmin;
use WooBordereauGenerator\Helpers;
use WP_Error;

class MylerzExpress extends \WooBordereauGenerator\Admin\Shipping\BordereauProvider implements \WooBordereauGenerator\Admin\Shipping\BordereauProviderInterface
{

    /**
     * @var
     */
    private $api_token;


    /**
     * @var WC_Order
     */
    protected $formData;

    /**
     * @var array
     */
    protected $provider;

    /**
     * @var array
     */
    private $data;

    /**
     * @var false|mixed|null
     */
    private $api_id;

    /**
     * @param WC_Order $formData
     * @param array $provider
     * @param array $data
     */
    public function __construct(WC_Order $formData, array $provider, array $data = [])
    {

        $this->formData = $formData;
        $this->api_id = get_option('mylerz_username');
        $this->api_token = get_option('mylerz_password');
        $this->provider = $provider;
        $this->data = $data;
    }

    /**
     * Generate the tracking code
     * @return mixed|WP_Error
     * @throws Exception
     * @since 1.0.0
     * Generate the tracking number for ecotrack
     */
    public function generate()
    {

        $params = $this->formData->get_data();
        $orderId = $this->formData->get_id();

       $wilayas = $this->get_wilayas();

        if (isset($this->data['wilaya'])) {
            $wilayaId = (int) str_replace("DZ-", "", $this->data['wilaya']);

            $wilaya = array_filter($wilayas, function ($item) use ($wilayaId){
                return $item['ID'] == $wilayaId;
            });

            $wilaya = reset($wilaya);

        } else {
            $wilayaId = $params['billing']['state'];
            $wilaya = array_filter($wilayas, function ($item) use ($wilayaId) {
                return $item['ID'] == $wilayaId;
            });


            $wilaya = reset($wilaya);
        }


        $wilayaId = $wilaya['Code'] ?? $wilayaId;

        $commune = $this->data['commune'] ?? $params['billing']['city'];

        if (isset($this->data['products'])) {
            $productString = $this->data['products'];
        } else {
            $products = $params['line_items'];
            $productString = Helpers::generate_products_string($products);
        }


        $stop_desk = 0;

        if ($this->formData->has_shipping_method('local_pickup') && $this->data['shipping_type'] === 'stopdesk') {
            $stop_desk = 1;
        }

        if ($this->data['shipping_type'] === 'stopdesk') {
            $stop_desk = 1;
        }

        $phone = explode('/', $this->data['phone'] ?? $params['billing']['phone']);
        $price = $this->data['price'] ?? $params['total'] - $params['shipping_total'];


        $name = "";

        if ($this->data['lname'] || $this->data['fname']) {
            $name = $this->data['fname'] . ' ' . $this->data['lname'];
        } elseif ($params['billing']['first_name'] || $params['billing']['last_name']) {
            $name = $params['billing']['first_name'] . ' ' . $params['billing']['last_name'];
        }


        if ($this->data['remarque']) {
            $note = $this->data['remarque'];
        } else {
            $note = stripslashes(get_option('wc_bordreau_default-shipping-note'));
        }

        $date = new DateTime("now", new DateTimeZone("UTC"));

        $productsArr = [];

        foreach ($this->formData->get_items() as $k => $productItem) {
            $product = $productItem->get_product();
            $item = new \stdClass();
            $item->PieceNo = $k;
            $item->SpecialNotes = $productItem->get_name();
            $item->Weight = $product->get_weight();
            $productsArr = [$item];
        }

        $total_weight = 0;

        foreach ( $this->formData->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            if ( $product ) {
                $product_weight = $product->get_weight();
                // Ensure the product has a weight before adding it
                if ( $product_weight ) {
                    $total_weight += (float) $product_weight * (int) $item->get_quantity();
                }
            }
        }

        $data = [
            [
                'Package_Serial' => $this->formData->get_id(),
                "Reference" =>  $this->formData->get_order_number(),
                'Description' => $productString,
                'Total_Weight' => $total_weight,
                'Service_Type' => $stop_desk ? 'CTC' : 'CTD',
                'Service' => 'ND',
                'Service_Category' => 'DELIVERY',
                'Payment_Type' => 'COD',
                'COD_Value' => (int) $price,
                'Special_Notes' => $note,
                'Customer_Name' => $name,
                'Mobile_No' => Helpers::clean_phone_number($phone[0], true),
                'Street' => $stop_desk ? $commune : $params['billing']['address_1'],
                'City' => $wilayaId,
                'Neighborhood' => $commune,
                'District' => $commune,
                'Country' => 'Algeria',
                'Currency' => 'DZD',
                'Pieces' => $productsArr,
                'PickupDueDate' => $date->format("Y-m-d\TH:i:s.v\Z"),
                'ValueOfGoods' => (int) $price,
                'AllowToOpenPackage' => true,
                'Mobile_No2' => isset($phone[1]) ? Helpers::clean_phone_number($phone[1], true) : "",
            ],
        ];

        $url = $this->provider['api_url'] . '/api/Orders/AddOrders';

        if (isset($this->data['is_update'])) {

            $trackingNumber = get_post_meta($orderId, '_shipping_tracking_number', true);
            if(! $trackingNumber) {
                $trackingNumber = $this->formData->get_meta('_shipping_tracking_number');
            }

            $data[0]['Barcode'] = $trackingNumber;
            $url = $this->provider['api_url'] . '/api/packages/EditPackage';
        }

        $request = new \WooBordereauGenerator\Admin\Shipping\MylerzExpressRequest($this->provider);
        $content = $request->post($url, $data);

        if (isset($content['Value']['ErrorCode'])) {
            wp_send_json([
                'error' => true,
                'message' =>  $content['Value']['ErrorCode']
            ], 401);
        }

        return $content;
    }


    /**
     * Save the response to the postmeta
     * @param $post_id
     * @param array $response
     * @throws Exception
     * @since 1.0.0
     */
    public function save($post_id, array $content)
    {
        $etq = get_rest_url(null, 'woo-bordereau/v1/slip/print/'.$this->provider['slug'].'/'.$post_id);

        try {
            // get the tracking


            if (isset($content['Value']['Packages'][0]['BarCode'])) {

                $order = $this->formData;

                $post_id = $this->formData->get_id();
                $track = $this->provider['api_url'] . '/tracking/' . $post_id . '/events';
                $trackingNumber = $content['Value']['Packages'][0]['BarCode'];

                update_post_meta($post_id, '_shipping_tracking_number', wc_clean($trackingNumber));
                update_post_meta($post_id, '_shipping_tracking_label', $this->provider['api_url'] . '/order-slips?orderIds[]=' . $trackingNumber);
                update_post_meta($post_id, '_shipping_label', $this->provider['api_url'] . '/order-slips?orderIds[]=' . $trackingNumber);
                update_post_meta($post_id, '_shipping_tracking_label_method', 'GET');
                update_post_meta($post_id, '_shipping_tracking_url', $track);

                $order->update_meta_data('_shipping_tracking_number', wc_clean($trackingNumber));
                $order->update_meta_data('_shipping_tracking_label', $this->provider['api_url'] . '/order-slips?orderIds[]=' . $trackingNumber);
                $order->update_meta_data('_shipping_label', $this->provider['api_url'] . '/order-slips?orderIds[]=' . $trackingNumber);
                $order->update_meta_data('_shipping_tracking_label_method', 'GET');
                $order->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
                $order->save();

                Helpers::make_note($order, wc_clean($trackingNumber), $this->provider['name']);

                $current_tracking_data = [
                    'tracking_number' => wc_clean($trackingNumber),
                    'carrier_slug' => $this->provider['slug'],
                    'carrier_url' => $track,
                    "carrier_name" => $this->provider['name'],
                    "carrier_type" => "custom-carrier",
                    "time" => time()
                ];

                $item_tracking_data[] = $current_tracking_data;
                $order = new WC_Order($post_id);

                $order_items = $order->get_items();

                // check if the extension tracking is enabled
                if (in_array('woo-orders-tracking/woo-orders-tracking.php', apply_filters('active_plugins', get_option('active_plugins')))) {
                    foreach ($order_items as $item) {
                        wc_update_order_item_meta($item->get_id(), '_vi_wot_order_item_tracking_data', json_encode($item_tracking_data));
                    }
                }

                wp_send_json([
                    'message' => __("Tracking number has been successfully created", 'woo-bordereau-generator'),
                    'provider' => $this->provider['name'],
                    'tracking' => $current_tracking_data,
                    'label' => $etq
                ], 200);
            }

        } catch (\ErrorException $exception) {
            wp_send_json([
                'message' => $exception->getMessage(),
                'provider' => $this->provider['name'],
            ], 401);
        }


    }

    /**
     * Get the trackinng information for the order
     *
     * @param string $tracking_number
     * @return void
     */
    public function track($tracking_number, $post_id = false)
    {
        $tracking_number = get_post_meta($post_id, '_shipping_tracking_number', true);
        $url = $this->provider['api_url'] . "/api/packages/TrackPackages";
        $request = new \WooBordereauGenerator\Admin\Shipping\MylerzExpressRequest($this->provider);

        $data = [
          [
              "Barcode" => $tracking_number
          ]
        ];

        $content = $request->post($url, $data);


        if (is_array($content)) {

            $events = Helpers::sort_tracking($this->format_tracking($content));

            if (count($events)) {
                if (isset($events[0]['status'])) {
                    BordereauGeneratorAdmin::update_tracking_status($this->formData, $events[0]['status'], 'single_tracking');
                }
            }


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


    private function reset_meta($post_id) {

        delete_post_meta($post_id, '_shipping_tracking_number');
        delete_post_meta($post_id, '_shipping_tracking_method');
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
     * Get the tracking information for the order
     *
     * @param string $tracking_number
     * @return void
     */
    public function track_detail($tracking_number)
    {

//        $url = $this->provider['api_url'] . "tracking/$tracking_number/events";
//
//        return $this->format_tracking($response_array);
    }


    /**
     * @param $wilaya_id
     * @param bool $hasStopDesk
     *
     * @return array
     */
    public function get_communes($wilaya_id, bool $hasStopDesk)
    {

        $wilaya_id = $_POST['wilaya'];

        if (isset($_POST['wilaya'])) {

            $path = Functions::get_path('mylerz_wilaya.json');

            if (!file_exists($path)) {
                $content = $this->get_wilayas();
                file_put_contents($path, json_encode($content));
            }

            $wilayas = json_decode(file_get_contents($path), true);

            $wilaya = array_filter($wilayas['Value'], function($item) use ($wilaya_id) {
                return $item['Code'] == str_replace('DZ-', '', $wilaya_id);
            });


            $wilaya = reset($wilaya);

            $communesResult = [];

            foreach ($wilaya['Zones'] as $i => $item) {
                $communesResult[$i]['id'] = $item['Code'];
                $communesResult[$i]['name'] = $item['EnName'];
            }

            return $communesResult;
        }
    }


    /**
     * Format the response
     *
     * @param array $response_array
     * @return array
     */
    private function format_tracking($response_array)
    {

        $result = [];

        if (is_array($response_array)) {

            $trackingDetail = reset($response_array['Value']);


            foreach ($trackingDetail['TrackLog'] as $key => $item) {
                $result[$key]['date'] = $item['ChangedDate'];
                $result[$key]['location'] = null;
                $result[$key]['status'] = $item['StatusEnName'];
            }

            usort($result, [$this, 'sortByOrder']);

        }


        $last_status = $result[0]['status'] ?? '';

        if ($last_status) {
            BordereauGeneratorAdmin::update_tracking_status($this->formData, $last_status, 'single_tracking');
        }

        return $result;
    }


    /**
     * @param array $item
     * @return array
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

    function sortByOrder($a, $b)
    {
        $t1 = strtotime($a['date']);
        $t2 = strtotime($b['date']);
        return $t2 - $t1;
    }

    /**
     * @return array
     */
    public function get_wilayas(): array
    {

        $path = Functions::get_path('mylerz_wilaya.json');

        if (!file_exists($path)) {

            $request = new \WooBordereauGenerator\Admin\Shipping\MylerzExpressRequest($this->provider);
            $content = $request->get($this->provider['api_url'] . '/api/packages/GetCityZoneList');


            file_put_contents($path, json_encode($content));
        }

        $wilayas = json_decode(file_get_contents($path), true);

        return $wilayas;
    }

    /**
     *
     * @return array
     */
    private function get_wilaya_from_provider(): array
    {

        return $this->get_wilayas();
    }


    /**
     * @param $tracking_number
     * @param $post_id
     * @return void
     * @since 1.2.0
     */
    public function detail($tracking_number, $post_id)
    {

        $url = $this->provider['api_url'] . '/api/packages/GetPackageDetails?AWB=' . $tracking_number;
        $request = new \WooBordereauGenerator\Admin\Shipping\MylerzExpressRequest($this->provider);

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
     * @param $provider
     *
     * @return array
     */
    public function get_wilayas_by_provider($provider): array
    {

        $path = Functions::get_path('mylerz_wilaya.json');


        if (!file_exists($path)) {
            $content = $this->get_wilayas();
            file_put_contents($path, json_encode($content));
        }

        $wilayasResult = [];

        if (file_exists($path)) {
            $result = json_decode(file_get_contents($path), true);

            if (! isset($result['Value']) && $result == null || count($result) == 0) {
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

        if (isset($result['Value'])) {
            foreach ($result['Value'] as $i => $wilaya) {
                $wilayasResult[$i]['name'] = $wilaya['EnName'];
                $wilayasResult[$i]['id'] = $wilaya['Code'];
            }


            return $wilayasResult;
        }


        return $wilayasResult;
    }


    /**
     * @param $tracking_number
     * @param $post_id
     * @return void
     * @since 1.3.0
     */
    public function delete($tracking_number, $post_id)
    {

        if ($tracking_number) {

            // delete the package if exist

            $data = [
                [
                    'Barcode' => $tracking_number
                ]
            ];

            $url = $this->provider['api_url'] . "/api/packages/CancelPackage";
            $request = new \WooBordereauGenerator\Admin\Shipping\MylerzExpressRequest($this->provider);
            $content = $request->post($url, $data);
            if ($content && isset($content['Value']) && $content['Value'] === $tracking_number) {
                $this->reset_meta($post_id);
            }
        }

        return null;
    }


    /**
     * @since 2.2.0
     * @return void
     */
    public function get_slip()
    {

        $tracking_number = get_post_meta($this->formData->get_id(), '_shipping_tracking_number', true);

        $url = $this->provider['api_url'] . "/api/packages/GetAWB";
        $request = new \WooBordereauGenerator\Admin\Shipping\MylerzExpressRequest($this->provider);

        $data = [
            "Barcode" => $tracking_number
        ];

        $content = $request->post($url, $data);

        $response = "";

        if (isset($content['Value'])) {
            $response = $content['Value'];
        }

        header("Content-type:application/pdf");
        echo base64_decode($response);
        exit();
    }


}
