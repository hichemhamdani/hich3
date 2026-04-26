<?php

namespace WooBordereauGenerator\Admin\Shipping;

use DateInterval;
use DateTime;
use DOMDocument;
use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use Shuchkin\SimpleXLS;
use WC_Shipping_Zone;
use WC_Shipping_Zones;
use WooBordereauGenerator\Functions;
use WooBordereauGenerator\Helpers;

class PicoSolutionSettings
{
    /**
     * @var array
     */
    private $provider;

    public function __construct(array $provider)
    {
        $this->provider = $provider;
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

        $cookiesJar = $this->auth();

        $client = new Client(['cookies' => true]);

        $wilayas = $this->get_wilayas();

        $prices = [];

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


        if($wilayas) {

            foreach ($wilayas as $wilaya) {

                $response = $client->request('POST', $this->provider['api_url'].'FR/Nouveau.awp?P1=1', [
                    'cookies' => $cookiesJar,
                    'form_params' => [
                        'WD_ACTION_' => 'AJAXPAGE',
                        'EXECUTE' => '47',
                        'WD_CONTEXTE_' => 'A4',
                        'A4' => $wilaya['id'],
                    ]
                ]);

                $result = (string) $response->getBody();

                $xmlDoc = new DOMDocument();
                $xmlDoc->loadXML($result);

                // Save the communes in meanwhile
                $elCom = $xmlDoc->getElementsByTagName('OPTION');

                $communes = [];

                foreach (range(0, $elCom->count() - 1) as $key => $item) {
                    $communes[$key]['id'] = $elCom->item($item)->nodeValue;
                    $communes[$key]['name'] = $elCom->item($item)->nodeValue;
                }

                $pathCommune = Functions::get_path($this->provider['slug'].'_'.$wilaya['id'].'_communes_home.json');

                if (!file_exists($pathCommune)) {
                    file_put_contents($pathCommune, json_encode($communes));
                }

                $el = $xmlDoc->getElementsByTagName('CHAMP');

                foreach ($el as $item) {
                    if($item->getAttribute('ALIAS') == 'A30') {
                        if($item->textContent !== '0,00 Da') {
                            $price = $item->textContent;
                            $price = str_replace(",", ".", str_replace(" ", "", $price));
                            if((int) $price > 1) {
                                $prices[$wilaya['name']]['home'] = (int) $price;
                            }

                        }
                    }

                    if($item->getAttribute('ALIAS') == 'A31') {
                        if($item->textContent !== '0,00 Da') {
                            $price = $item->textContent;
                            $price = str_replace(",", ".", str_replace(" ", "", $price));
                            if((int) $price > 1) {
                                $prices[$wilaya['name']]['desk'] = (int)$price;
                            }
                        }
                    }
                }
            }


            // file_put_contents($path,  json_encode($prices));
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

    /**
     * Auth to ZR Express
     * @return CookieJar
     * @throws GuzzleException
     */
    private function auth()
    {
        $api_key         = get_option($this->provider['slug'].'_username');
        $api_token        = get_option($this->provider['slug'].'_password');

        $client = new Client(['cookies' => true]);

        $jar = new CookieJar;

        $client->request('POST', $this->provider['login_url'], [
            'form_params' => [
                'WD_JSON_PROPRIETE_' => '',
                'WD_BUTTON_CLICK_' => 'A7',
                'WD_ACTION_' => '',
                'A2' => $api_key,
                'A3' => $api_token
            ],
            'cookies' => $jar
        ]);

        return $jar;
    }

    private function get_wilayas()
    {
        $filename = $this->provider['slug'].'_wilaya.json';

        $path = Functions::get_path($filename);

        if (file_exists($path)) {
            return json_decode(file_get_contents($path), true);
        }

        $wilayasResult = [];

        $cookiesJar = $this->auth();

        $client = new Client(['cookies' => true]);

        $response = $client->request('GET', $this->provider['api_url'].'FR/Nouveau.awp?P1=1', [
            'cookies' => $cookiesJar
        ]);

        $dom = new DOMDocument();

        $dom->loadHTML($response->getBody()->getContents());

        $sel = $dom->getElementById("A4");

        $optionTags = $sel->getElementsByTagName('option');

        foreach ($optionTags as $key => $tag) {
            if ($tag->nodeValue != 'Wilaya') {
                $wilayasResult[$key]['id'] = $tag->getAttribute('value');
                $wilayasResult[$key]['name'] = $tag->nodeValue;
            }
        }

        file_put_contents($path, json_encode($wilayasResult));

        return  $wilayasResult;


    }



    private function shipping_zone_grouped($response_array, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled)
    {

    }

    /**
     * @param $response_array
     * @return void
     */
    private function shipping_zone_ungrouped($response_array, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled)
    {

        $wilayas = $response_array;


        $i = 0;

        foreach ($wilayas as $key => $wilaya) {

            $found = null;

            foreach (WC()->countries->get_states( 'DZ' ) as $cle => $item) {

                if(strpos($item, $key) !== false) {
                    $found = $cle;
                    break;
                }
            }

            if($found) {

                // Get all shipping zones
                $zones = WC_Shipping_Zones::get_zones();

                // Iterate through the zones and find the one with the matching name
                $found_zone = null;
                foreach ($zones as $zone) {
                    if ($zone['zone_name'] === $key) {
                        $found_zone = $zone;
                        break;
                    }
                }

                if (! $found_zone) {

                    $zone = new WC_Shipping_Zone();

                    $zone->set_zone_name($key);
                    $zone->set_zone_order($i);
                    $zone->add_location('DZ:'.$found, 'state');
                    $zone->save();
                } else {
                    $zone = WC_Shipping_Zones::get_zone( $found_zone['id'] );
                }


                if (isset($wilaya['home']) && $flat_rate_enabled) {

                    $instance_flat_rate = $zone->add_shipping_method('flat_rate');
                    $shipping_method_flat_rate = WC_Shipping_Zones::get_shipping_method($instance_flat_rate);

                    $shipping_method_configuration_flat_rate = [
                        'woocommerce_flat_rate_title'         => $flat_rate_label != '' ? $flat_rate_label : __('Flat rate', 'woo-bordereau-generator'),
                        'woocommerce_flat_rate_tax_status'    => 'none',
                        'woocommerce_flat_rate_cost'          => $wilaya['home'],
                        'woocommerce_flat_rate_type'          => 'class',
                        'instance_id'                         => $instance_flat_rate,
                    ];

                    $_REQUEST['instance_id'] = $instance_flat_rate;
                    $shipping_method_flat_rate->set_post_data($shipping_method_configuration_flat_rate);
                    $shipping_method_flat_rate->update_option('provider', $this->provider['slug']);
                    $shipping_method_flat_rate->process_admin_options();
                }


                if (isset($wilaya['desk']) && $pickup_local_enabled) {
                    $instance_local_pickup = $zone->add_shipping_method('local_pickup');
                    $shipping_method_local_pickup = WC_Shipping_Zones::get_shipping_method($instance_local_pickup);

                    $shipping_method_configuration_local_pickup = array(
                        'woocommerce_local_pickup_title'         => $pickup_local_label != '' ? $pickup_local_label : __('Local Pickup', 'woo-bordereau-generator'),
                        'woocommerce_local_pickup_tax_status'    => 'none',
                        'woocommerce_local_pickup_cost'          => $wilaya['desk'],
                        'woocommerce_local_pickup_type'          => 'class',
                    );

                    $_REQUEST['instance_id'] = $instance_local_pickup;
                    $shipping_method_local_pickup->set_post_data($shipping_method_configuration_local_pickup);
                    $shipping_method_local_pickup->update_option('provider', $this->provider['slug']);
                    $shipping_method_local_pickup->process_admin_options();
                }
            }

            $i++;
        }
    }

    public function bulk_track($codes){


        $cookiesJar = $this->auth();
        $tmpFile = tempnam(sys_get_temp_dir(), 'download_');
        $resource = fopen($tmpFile, 'w');

        $client = new Client(['cookies' => true]);

        $startDate = new DateTime();
        $startDate->sub(new DateInterval('P1M'));

        // Date one month from now
        $endDate = new DateTime();


        $result = [];
        // Format dates to 'd/m/Y' format
        $intervalString = $startDate->format('d/m/Y') . '+-+' . $endDate->format('d/m/Y');

        $client->request('POST', $this->provider['api_url'].'FR/Export-All.awp', [
            'cookies' => $cookiesJar,
            'form_params' => [
                'WD_JSON_PROPRIETE_' => '',
                'WD_BUTTON_CLICK_' => 'A19',
                'WD_ACTION_' => 'MENU_SUBMIT',
                'M30' => '',
                'A3' => $intervalString,
                'A6' => '',
                'A8' => '',
                'A8_DEB' => '1',
                '_A8_OCC' => '0',
                'A8_DATA' => ',0;1;2;3;4;5;6;7;8;9;10;11;12;13;14,',
                'A8_SEL' => ''
            ],
            'sink' => $resource
        ]);


        if ( $xls = SimpleXLS::parse($tmpFile) ) {
            $rows = $xls->rows();
            foreach($rows as $key => $value) {
                $code = $value[1];
                if (in_array($code, $codes)) {
                    $status =  $value[12];
                    $result[$code] = $status;
                }

            };
        } else {
            new \WP_Error(500, SimpleXLS::parseError());
        }

        unlink($tmpFile);
        return $result;
    }

    /**
     * @param $order_ids
     * @return mixed|void
     */
    public function bulk_add_orders($order_ids) {

        $data = [];
        $orders = array();
        foreach ($order_ids as $id) {
            $order = wc_get_order($id);
            if ($order) {
                $orders[] = $order;
            }
        }

        $wilayas = $this->get_wilayas();

        foreach ($orders as $order) {

            $params = $order->get_data();

            $orderId = $order->get_id();

            $products = $order->get_items();

            $productString = Helpers::generate_products_string($products);

            $stop_desk = 0;


            if ($order->has_shipping_method('local_pickup') && $this->data['shipping_type'] === 'stopdesk') {
                $stop_desk = 1;
            }

            $url = $this->provider['api_url'].'FR/Nouveau.awp?P1=1';

            if ($stop_desk) {
                $url = $this->provider['api_url'].'FR/Nouveau.awp?P1=2';
            }

            if ($order->get_meta('_is_stock-desk')) {
                $order->update_meta_data('_is_stock-desk', $stop_desk);
                $order->save();
            } else {
                $order->add_meta_data('_is_stock-desk', $stop_desk);
                $order->save();
            }

            $wilaya = $params['billing']['state'];
            $wilaya = (int) str_replace('DZ-', '', $wilaya);
            $phone = explode('/', $params['billing']['phone']);

            $fields = $this->provider['fields'];
            $key = $fields['total']['choices'][0]['name'] ?? null;
            $total = get_option($key) ? get_option($key) : 'with-shipping';

            $cart_value = 0;

            if ($total == 'with-shipping') {
                $cart_value = (float) $order->get_subtotal() + $order->get_total_tax() +  $order->get_total_shipping();
            } elseif ($total === 'without-shipping') {
                $cart_value =  ((float) $order->get_total() + $order->get_total_tax()) - $order->get_total_shipping();
            }

            $name = $params['billing']['first_name'] . ' ' . $params['billing']['last_name'];

            // Domicile
            $data = [
                'WD_JSON_PROPRIETE_' => '',
                'WD_BUTTON_CLICK_' => 'A23',
                'WD_ACTION_' => '',
                'M30' => '',
                'A8' => $name, // name
                'A2' => $phone[0], // mobile 1
                'A3' => $phone[1] ?? '', // mobile 2
                'A5' => $params['billing']['address_1'], // address
                'A4' => $wilaya, // wilaya
                'A21' => $params['billing']['city'], // commune
                'A6' => 'Commande #' . $orderId, // note
                'A12' => $orderId, // order number
                'A9' => $productString, // products
                'A10' => '1', // qty products
                'A15' => '1',
                'A17' => '1',
                'A20' => '1',
                'A33' => '1',
                'A41' => '1',
                'A27' =>  '',
                'A30' => '0', // shipping cost domicile
                'A31' => '0', // shipping cost stop desk
                'A13' => $cart_value // price of the order
            ];


            $cookiesJar = $this->auth();

            $client = new Client(['cookies' => true]);

            $response = $client->request('POST', $url, [
                'cookies' => $cookiesJar,
                'form_params' => $data,
                'verify' => false,
            ]);

            $response_body = (string) $response->getBody();

            $re = '/NSPCS\.NSTypes\.CDescriptionTableau\(DECLARATIONAPI_StColis\.m_oDescriptionStatique,\[0\],2,0\),(.*)\);NSPCS.NSValues.DeclareVariableServeur/mU';

            preg_match($re, $response_body, $matches);

            if (isset($matches[1])) {

                $json = json_decode($matches[1], true);

                $result = json_decode(array_values($json)[0]['m_sJSON'], true);

                foreach ($result as $item) {

                    if ($item['NoteClient'] === 'Commande #'.$orderId) {
                        $trackingNumber = $item['Tracking'];
                        $post_id = $order->get_id();
                        $etq = get_rest_url( null, 'woo-bordereau/v1/slip/print/'.$this->provider['slug'].'/'. $post_id );

                        update_post_meta($post_id, '_shipping_tracking_number', wc_clean($trackingNumber));
                        update_post_meta($post_id, '_shipping_tracking_label', wc_clean($etq));
                        update_post_meta($post_id, '_shipping_label', wc_clean($etq));
                        update_post_meta($post_id, '_shipping_tracking_url', $this->provider['api_url'].'FR/ColisClient.awp?P1='.$trackingNumber);
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

    }


    /**
     * Get Cities from specific State
     * @param int $wilaya_id
     * @param bool $hasStopDesk
     *
     * @return array
     * @throws GuzzleException
     */
    public function get_communes(int $wilaya_id, bool $hasStopDesk)
    {

        $path = Functions::get_path($this->provider['slug'].'_'.$wilaya_id.'_communes_'. ($hasStopDesk ? 'stopdesk' : 'home') .'.json');

        if (file_exists($path)) {
            return json_decode(file_get_contents($path), true);
        }

        $url = $this->provider['api_url'].'FR/Nouveau.awp?P1=1';

        if ($hasStopDesk) {
            $url = $this->provider['api_url'].'FR/Nouveau.awp?P1=2';
        }

        $communes = [];

        $cookiesJar = $this->auth();

        $client = new Client(['cookies' => true]);

        $response = $client->request('POST', $url, [

            'cookies' => $cookiesJar,
            'form_params' => [
                'WD_ACTION_' => 'AJAXPAGE',
                'EXECUTE' => '47',
                'WD_CONTEXTE_' => 'A4',
                'A4' => $wilaya_id,
            ]
        ]);

        $xmlDoc = new DOMDocument();
        $xmlDoc->loadXML($response->getBody()->getContents());

        $el = $xmlDoc->getElementsByTagName('OPTION');

        foreach (range(0, $el->count() - 1) as $key => $item) {
            $communes[$key]['id'] = $el->item($item)->nodeValue;
            $communes[$key]['name'] = $el->item($item)->nodeValue;
        }

        file_put_contents($path, json_encode($communes));

        return $communes;
    }


}
