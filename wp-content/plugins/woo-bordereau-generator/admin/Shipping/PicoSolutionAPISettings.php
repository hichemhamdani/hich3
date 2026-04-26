<?php

namespace WooBordereauGenerator\Admin\Shipping;

use DateTime;
use DOMDocument;
use DOMXPath;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\GuzzleException;
use Shuchkin\SimpleXLS;
use WC_Order;
use WC_Product;
use WC_Shipping_Zone;
use WC_Shipping_Zones;
use WooBordereauGenerator\Functions;
use WooBordereauGenerator\Helpers;
use WP_Error;

class PicoSolutionAPISettings
{
    /**
     * @var string
     */
    private string $api_key;

    /**
     * @var string
     */
    private string $api_token;


    /**
     * @var WC_Order
     */
    protected $formData;

    /**
     * @var array
     */
    protected $provider;

    /**
     * @var array|mixed
     */
    private $data;

    public function __construct($provider)
    {
        $this->provider = $provider;
        $this->api_key    = get_option($provider['slug'].'_key');
        $this->api_token    = get_option($provider['slug'].'_token');
    }


    /**
     *
     * Get Shipping classes
     * @return void
     * @throws GuzzleException
     * @since 1.6.5
     */
    public function import_shipping_class($flat_rate_label, $pickup_local_label,  $flat_rate_enabled, $pickup_local_enabled, $agency_import_enabled = null, $is_economic = null)
    {
        $url = $this->provider['api_url'].'/tarification';

        $data = [];

        $client = new Client();

        $response = $client->request('POST', $url, [
            'body' => '{}',
            'verify' => false,
            'headers' => [
                'token' => $this->api_token,
                'key' => $this->api_key,
                'Content-Type' => 'application/json'
            ]
        ]);

        $response_body = (string)$response->getBody();
        $prices = json_decode($response_body, true);

        if($flat_rate_enabled) {

            if ($flat_rate_label) {
                if (get_option($this->provider['slug'].'_flat_rate_label')) {
                    update_option($this->provider['slug'].'_flat_rate_label', $flat_rate_label);
                } else {
                    add_option($this->provider['slug'].'_flat_rate_label', $flat_rate_label);
                }
            }
        }

        if($pickup_local_enabled) {

            if ($pickup_local_label) {

                if (get_option($this->provider['slug'].'_pickup_local_label')) {
                    update_option($this->provider['slug'].'_pickup_local_label', $pickup_local_label);
                } else {
                    add_option($this->provider['slug'].'_pickup_local_label', $pickup_local_label);
                }
            }
        }

        if (get_option('shipping_zones_grouped') && get_option('shipping_zones_grouped') == 'yes') {
            $this->shipping_zone_grouped($prices, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled);
        } else {
            $this->shipping_zone_ungrouped($prices, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled);
        }


        wp_send_json_success(
            array(
                'message'   => 'success',
            )
        );

    }


    private function shipping_zone_grouped($response_array, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled)
    {

    }

    public function get_wilayas()
    {

        $path = Functions::get_path($this->provider['slug'].'_'.WC_BORDEREAU_GENERATOR_VERSION.'_wilayas.json');

        if (! file_exists($path)) {
            $content = file_get_contents('https://amineware.me/api/wilayas');
            file_put_contents($path, $content);
        }

        $wilayas = json_decode(file_get_contents($path), true);

        if ($wilayas == null || count($wilayas) == 0) {
            return $this->get_wilaya_from_provider();
        }

        return  $wilayas;
    }

    private function get_wilaya_from_provider()
    {
        return file_get_contents('https://amineware.me/api/wilayas');
    }

    /**
     * @param $response_array
     * @return void
     */
    private function shipping_zone_ungrouped($response_array, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled)
    {

        // Set memory limit to -1 (unlimited) and set time limit to 0 (unlimited)
        ini_set('memory_limit', '-1');
		set_time_limit(0);

        $wilayas = $this->get_wilayas();


        $i = 0;

        foreach ($response_array as $key => $wilaya) {

            // Get all shipping zones
            $zones = WC_Shipping_Zones::get_zones();

            // Iterate through the zones and find the one with the matching name
            $found_zone = null;
            foreach ($zones as $zone) {
                if ($zone['zone_locations'][0]->code === 'DZ:DZ-' . str_pad($wilayas[$key]['id'], 2, '0', STR_PAD_LEFT)) {
                    $found_zone = $zone;
                    break;
                }
            }

            if (! $found_zone) {

                $zone = new WC_Shipping_Zone();

                $zone->set_zone_name(ucfirst(Helpers::normalizeCityName($wilayas[$key]['name'])));
                $zone->set_zone_order($i);
                $zone->add_location('DZ:DZ-'. str_pad($wilaya['IDWilaya'], 2, '0', STR_PAD_LEFT), 'state');
                $zone->save();
            } else {
                $zone = WC_Shipping_Zones::get_zone( $found_zone['id'] );
            }


            if (isset($wilaya['IDWilaya']) && $flat_rate_enabled) {

                $flat_rate_name = 'flat_rate_zr_express';
                if (is_hanout_enabled()) {
                    $flat_rate_name = 'flat_rate';
                }

                $instance_flat_rate = $zone->add_shipping_method($flat_rate_name);
                $shipping_method_flat_rate = WC_Shipping_Zones::get_shipping_method($instance_flat_rate);

                $shipping_method_configuration_flat_rate = [
                    'woocommerce_'.$flat_rate_name.'_title'             => $flat_rate_label != '' ? $flat_rate_label : __('Flat rate', 'wc-bordereau-generator'),
                    'woocommerce_'.$flat_rate_name.'_tax_status'        => 'none',
                    'woocommerce_'.$flat_rate_name.'_cost'              => $wilaya['Domicile'],
                    'woocommerce_'.$flat_rate_name.'_type'              => 'class',
                    'woocommerce_'.$flat_rate_name.'_provider'          => $this->provider['slug'],
                    'instance_id'                                       => $instance_flat_rate,
                ];

                $_REQUEST['instance_id'] = $instance_flat_rate;
                $shipping_method_flat_rate->set_post_data($shipping_method_configuration_flat_rate);
                $shipping_method_flat_rate->update_option('provider', $this->provider['slug']);
                $shipping_method_flat_rate->process_admin_options();
            }

            if (isset($wilaya['Stopdesk']) && $pickup_local_enabled) {

                $local_pickup_name = 'local_pickup_zr_express';
                if (is_hanout_enabled()) {
                    $local_pickup_name = 'local_pickup';
                }

                $instance_local_pickup = $zone->add_shipping_method($local_pickup_name);
                $shipping_method_local_pickup = WC_Shipping_Zones::get_shipping_method($instance_local_pickup);

                $shipping_method_configuration_local_pickup = array(
                    'woocommerce_'.$local_pickup_name.'_title'                     => $pickup_local_label != '' ? $pickup_local_label : __('Local Pickup', 'wc-bordereau-generator'),
                    'woocommerce_'.$local_pickup_name.'_tax_status'                => 'none',
                    'woocommerce_'.$local_pickup_name.'_cost'                      => $wilaya['Stopdesk'],
                    'woocommerce_'.$local_pickup_name.'_type'                      => 'class',
                    'woocommerce_'.$local_pickup_name.'_provider'             => $this->provider['slug'],
                );

                $_REQUEST['instance_id'] = $instance_local_pickup;
                $shipping_method_local_pickup->set_post_data($shipping_method_configuration_local_pickup);
                $shipping_method_local_pickup->update_option('provider', $this->provider['slug']);
                $shipping_method_local_pickup->process_admin_options();
            }

            $i++;
        }
    }

    public function bulk_track($codes)
    {
        $codesResult = [];
        foreach ($codes as $key => $code) {
            $codesResult[] = ["Tracking" => $code];
        }

        $data = [
            "Colis" => $codesResult
        ];

        $client = new Client();

        $url = $this->provider['api_url'].'/lire';

        $response = $client->request('POST', $url, [
            'body' => json_encode($data),
            'verify' => false,
            'headers' => [
                'token' => $this->api_token,
                'key' => $this->api_key,
                'Content-Type' => 'application/json'
            ]
        ]);

        $result = (string) $response->getBody();

        $response_array = json_decode($result, true);

        $resultArray = [];

        foreach ($response_array["Colis"] as $coli) {
            $resultArray[$coli['Tracking']] = $coli['Situation'];
        }


        return $resultArray;
    }

    /**
     * @param $order_ids
     * @return mixed|void
     */
    public function bulk_add_orders($order_ids)
    {

        $orders = array();
        foreach ($order_ids as $id) {
            $order = wc_get_order($id);
            if ($order) {
                $orders[] = $order;
            }
        }

        $fields = $this->provider['fields'];
        $key = $fields['total']['choices'][0]['name'] ?? null;
        $total = get_option($key) ? get_option($key) : 'with-shipping';



        $colis = [];

        foreach ($orders as $order) {

            $params = $order->get_data();
            $orderId = $order->get_id();

            $qtn = 1;

            $products = $params['line_items'];
            $productString = Helpers::generate_products_string($products);

            $stop_desk = 0;

            if ($order->has_shipping_method('local_pickup')) {
                $stop_desk = 1;
            }

            $order->update_meta_data('_is_stock-desk', $stop_desk);
            $order->save();


            $pattern = "/DZ-[0-9]{2}/";

            $wilaya = $params['billing']['state'];

            if (preg_match($pattern, $wilaya)) {
                $wilayaId = (int) str_replace("DZ-", "", $wilaya);

            } else {
                $patternSecond = "/^(\d+)/"; // Regular expression to match a number at the beginning of the string

                if (preg_match($patternSecond, $wilaya, $matches)) {
                    $wilayaId = $matches[1]; // The first captured group, which is the number

                } else {
                    $wilayas = $this->get_wilayas();

                    $found = array_values(array_filter($wilayas, function ($w) use ($wilaya) {
                        return Helpers::citiesAreSimilar($w['name'], $wilaya);
                    }))[0] ?? null;

                    if ($found) {
                        $wilayaId = $found['id'];
                    }
                }
            }

            $phone = explode('/', $params['billing']['phone']);

            $price = (float) $order->get_total();

            if ($total == 'with-shipping') {
                $price = (float) $order->get_subtotal() + $order->get_total_tax() +  $order->get_total_shipping();
            } elseif ($total === 'without-shipping') {
                $price =  ((float) $order->get_total() + $order->get_total_tax()) - $order->get_total_shipping();
            }


            $name = $params['billing']['first_name'] . ' ' . $params['billing']['last_name'];

            $colis[] =  [
                "TypeLivraison" => $stop_desk, // Domicile : 0 & Stopdesk : 1
                "TypeColis" => "0", // Echange : 1
                "Client" => $name,
                "Confrimee" => "",
                "MobileA" => $phone[0],
                "MobileB" => $phone[1] ?? '',
                "Adresse" => $params['billing']['address_1'],
                "IDWilaya" => $wilayaId,
                "Commune" => $params['billing']['city'],
                "Total" => $price,
                "Qtn" => $qtn,
                "Note" => 'Commande #' . $orderId,
                "TProduit" =>  $productString,
                "id_Externe" => $order->get_order_number() ,  // Votre ID ou Tracking
                "Source" => "website"
            ];
            // Domicile
        }


        $data = [
            "Colis" => $colis
        ];

        $client = new Client();

        $url = $this->provider['api_url'].'/add_colis';

        $response = $client->request('POST', $url, [
            'body' => json_encode($data),
            'verify' => false,
            'headers' => [
                'token' => $this->api_token,
                'key' => $this->api_key,
                'Content-Type' => 'application/json'
            ]
        ]);

        $response_body = (string)$response->getBody();
        $response_array = json_decode($response_body, true);

        $parcels = $response_array['Colis'];
        foreach ($parcels as $key => $parcel) {
            $order = $orders[$key];
            if ($order->get_order_number() === $parcel['id_Externe']) {
                $trackingNumber = $parcel['Tracking'];
                $post_id = $order->get_id();

                $etq = get_rest_url( null, 'woo-bordereau/v1/slip/print/'.$this->provider['slug'].'/'.$post_id );

                update_post_meta($post_id, '_shipping_tracking_number', wc_clean($trackingNumber));
                update_post_meta($post_id, '_shipping_tracking_label', wc_clean($etq));
                update_post_meta($post_id, '_shipping_label', wc_clean($etq));

//            update_post_meta($post_id, '_shipping_tracking_url', $this->provider['api_url'].'FR/ColisClient.awp?P1='.$trackingNumber);
                update_post_meta($post_id, '_shipping_tracking_method', $this->provider['slug']);

                $order = wc_get_order($post_id);

                if($order) {
                    $order->update_meta_data('_shipping_tracking_number', wc_clean($trackingNumber));
                    $order->update_meta_data('_shipping_tracking_label', wc_clean($etq));
                    $order->update_meta_data('_shipping_label', wc_clean($etq));
                    $order->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
                    $order->save();
                }
            }


        }
    }
}
