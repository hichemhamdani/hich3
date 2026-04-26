<?php

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

namespace WooBordereauGenerator\Admin\Shipping;

use WC_Shipping_Zone;
use WC_Shipping_Zones;
use WooBordereauGenerator\Functions;
use WooBordereauGenerator\Helpers;
use WP_Error;

class ElogistiaShippingSettings
{

    private string $api_key;


    /**
     * @var array
     */
    private $provider;

    public function __construct(array $provider)
    {
        $this->provider = $provider;
        $this->api_key         = get_option($provider['slug'].'_api_token');

    }

    /**
     * @return array|array[]
     */
    public function get_settings()
    {

        $fields = [
            array(
                'title' => __($this->provider['name']. ' API Settings', 'woocommerce'),
                'type'  => 'title',
                'id'    => 'store_address',
            ),
        ];

        foreach ($this->provider['fields'] as $key => $filed) {
            $fields[$key] = array(
                'name'     => __($filed['name'], 'woo-bordereau-generator'),
                'id'       => $filed['value'],
                'type'     => $filed['type'],
                'desc' => $filed['desc'],
                'css'      => $filed['css'],
                'desc_tip' => true,
            );
        }

        $fields = $fields + [
            'section_end' => array(
                'type' => 'sectionend',
                'id' => $this->provider['slug']. '_section_end'
            )
        ];

        return $fields;
    }

    /**
     * Track the packages in bulk
     * @param array $codes
     *
     * @return array
     */
    public function bulk_track(array $codes): array
    {
        $request = new ElogistiaRequest($this->provider);
        return $this->format_tracking($request->get($this->provider['api_url'].'/getTracking/?apiKey='.$this->api_key.'&tracking='. implode(',', array_values($codes)), false));
    }


    /**
     * @param $json
     *
     * @return array
     */
    private function format_tracking($json): array
    {
        if ($json && isset($json['data'])) {
            $result = [];

            if (is_array($json['data']) && count($json['data'])) {
                foreach ($json['data'] as $key => &$entry) {
                    $result[$entry['tracking']][$key] = $entry;
                }
                $final = [];
                foreach ($result as $key => $value) {
                    $final[$key] = array_values($value)[0]['status'];
                }
                return $final;
            }
        }

        return [];
    }


    /**
     *
     * Get Shipping classes
     * @since 1.6.5
     * @return void
     */
    public function import_shipping_class($flat_rate_label, $pickup_local_label,  $flat_rate_enabled, $pickup_local_enabled, $agency_import_enabled = null, $is_economic = null)
    {

        $url = $this->provider['api_url'] . "/getShippingCost?key=".$this->api_key;

        if($pickup_local_enabled) {
            if ($pickup_local_label) {
                if (get_option($this->provider['slug'].'_pickup_local_label')) {
                    update_option($this->provider['slug'].'_pickup_local_label', $pickup_local_label);
                } else {
                    add_option($this->provider['slug'].'_pickup_local_label', $pickup_local_label);
                }
            }
        }

        if($flat_rate_enabled) {
            if ($flat_rate_label) {
                if (get_option($this->provider['slug'].'_flat_rate_label')) {
                    update_option($this->provider['slug'].'_flat_rate_label', $flat_rate_label);
                } else {
                    add_option($this->provider['slug'].'_flat_rate_label', $flat_rate_label);
                }
            }
        }


        $request = new ElogistiaRequest($this->provider);

        $response_array = $request->get($url);



        if (get_option('shipping_zones_grouped') && get_option('shipping_zones_grouped') == 'yes') {

            $this->shipping_zone_grouped($response_array, $flat_rate_label, $pickup_local_label,  $flat_rate_enabled, $pickup_local_enabled);
        } else {
            $this->shipping_zone_ungrouped($response_array, $flat_rate_label, $pickup_local_label,  $flat_rate_enabled, $pickup_local_enabled);
        }


        wp_send_json_success(
            array(
                'message'   => 'success',
            )
        );
    }

    /**
     * @param $response_array
     * @return void
     */
    private function shipping_zone_grouped($response_array)
    {

    }

    /**
     * @param $response_array
     * @param $flat_rate_label
     * @param $pickup_local_label
     * @return array
     */
    private function shipping_zone_ungrouped($response_array, $flat_rate_label, $pickup_local_label,  $flat_rate_enabled, $pickup_local_enabled)
    {

        // Set memory limit to -1 (unlimited) and set time limit to 0 (unlimited)
        ini_set('memory_limit', '-1');
		set_time_limit(0);

        $wilayas = $response_array['body'];
        $zones = [];


        $i = 1;
        foreach ($wilayas as $key => $wilaya) {
            $wilaya_name = $wilaya['wilayaLabel'];


            // Get all shipping zones
            $zones = WC_Shipping_Zones::get_zones();

            // Iterate through the zones and find the one with the matching name
            $found_zone = null;
            foreach ($zones as $zone) {
                if ($zone['zone_locations'][0]->code == 'DZ:DZ-' . str_pad($wilaya['wilayaID'], 2, '0',STR_PAD_LEFT)) {
                    $found_zone = $zone;
                    break;
                }
            }

            if (! $found_zone) {

                $zone = new WC_Shipping_Zone();
                $zone->set_zone_name(ucfirst(Helpers::normalizeCityName($wilaya_name)));
                $zone->set_zone_order($key);
                $zone->add_location('DZ:DZ-'.str_pad($wilaya['wilayaID'], 2, '0', STR_PAD_LEFT), 'state');
                $zone->save();
            } else {
                $zone = WC_Shipping_Zones::get_zone( $found_zone['id'] );
            }

            if (isset($wilaya['home']) && $flat_rate_enabled) {

                $flat_rate_name = 'flat_rate_elogistia';
                if (is_hanout_enabled()) {
                    $flat_rate_name = 'flat_rate';
                }

                $instance_flat_rate = $zone->add_shipping_method($flat_rate_name);
                $shipping_method_flat_rate = WC_Shipping_Zones::get_shipping_method($instance_flat_rate);
                $shipping_method_configuration_flat_rate = [
                    'woocommerce_'.$flat_rate_name.'_title'             => $flat_rate_label ?? __('Flat rate', 'wc-bordereau-generator'),
                    'woocommerce_'.$flat_rate_name.'_cost'              => $wilaya['home'],
                    'woocommerce_'.$flat_rate_name.'_type'              => 'class',
                    'woocommerce_'.$flat_rate_name.'_provider'          => $this->provider['slug'],
                    'instance_id'                                       => $instance_flat_rate,
                ];

                $_REQUEST['instance_id'] = $instance_flat_rate;
                $shipping_method_flat_rate->set_post_data($shipping_method_configuration_flat_rate);
                $shipping_method_flat_rate->process_admin_options();

            }

            if (isset($wilaya['stopdesk']) && $pickup_local_enabled) {

                $local_pickup_name = 'local_pickup_elogistia';
                if (is_hanout_enabled()) {
                    $local_pickup_name = 'local_pickup';
                }

                $instance_local_pickup = $zone->add_shipping_method($local_pickup_name);
                $shipping_method_local_pickup = WC_Shipping_Zones::get_shipping_method($instance_local_pickup);
                $shipping_method_configuration_local_pickup = array(
                    'woocommerce_'.$local_pickup_name.'_title'                  => $pickup_local_label ?? __('Local Pickup', 'wc-bordereau-generator'),
                    'woocommerce_'.$local_pickup_name.'_cost'                   => $wilaya['stopdesk'],
                    'woocommerce_'.$local_pickup_name.'_type'                   => 'class',
                    'woocommerce_'.$local_pickup_name.'_provider'               => $this->provider['slug'],
                    'instance_id'                                               => $shipping_method_local_pickup,
                );

                $_REQUEST['instance_id'] = $instance_local_pickup;
                $shipping_method_local_pickup->set_post_data($shipping_method_configuration_local_pickup);
                $shipping_method_local_pickup->process_admin_options();
            }
        }

        return [$shipping_method_flat_rate, $shipping_method_local_pickup];
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

        foreach ($orders as $orderObj) {

            $providerClassShipping = new ElogistiaShipping($orderObj, $this->provider);
            $wilayas = $providerClassShipping->get_wilayas();

            $wilayaId = (int) str_replace("DZ-", "", $orderObj->get_billing_state());
            $wilaya = array_search($wilayaId, array_column($wilayas['body'], 'Id'));

            // get the wilaya name and id
            $wilaya = $wilayas['body'][$wilaya];

            $products = $orderObj->get_items();
            $productString = Helpers::generate_products_string($products);

            $free_shipping = 0;
            $stop_desk = 1;

            if ($orderObj->has_shipping_method('free_shipping')) {
                $free_shipping = 1;
            }

            if ($orderObj->has_shipping_method('local_pickup')) {
                $stop_desk = 2;
            }

            $params = $orderObj->get_data();

            $name = $params['billing']['last_name'];

            if(empty($name)) {
                $name = $params['billing']['first_name'];
            }

            $address = $params['billing']['address_1'];

            if(empty($address)) {
                $address = stripslashes($params['billing']['city']);
            }

            $data = [
                "apiKey" => $this->api_key,
                "IdCommande" => $orderObj->get_order_number(),
                "name" => $name,
                "firstname" => $params['billing']['first_name'],
                "phone" => $orderObj->get_billing_phone(),
                "address" => $address,
                "commune" => stripslashes($params['billing']['city']),
                "wilaya" => $wilaya['Id'],
                "mail" => get_option('woocommerce_store_email'),
                "remarque" => stripslashes(get_option('wc_bordreau_default-shipping-note')),
                "product" => $productString,
                "fraisDeLivraison" => $params['shipping_total'],
                "price" => $this->data['price'] ?? $params['total'] - $params['shipping_total'],
                "stop_desk" => $stop_desk,
                "modeDeLivraison" => 1,
                "exchangeName" => '',
            ];
        }

        $request = new ElogistiaRequest($this->provider);

        try {

            $query_string = http_build_query($data);
            $url = $this->provider['api_url'] . "/insertCommande?". $query_string;

            $response = $request->post($url, $data);

            // check if the there is no error
            if (isset($response['error'])) {
                new WP_Error($response['error']['message']);
                wp_send_json([
                    'error' => true,
                    'message'=>  $response['error']['message']
                ], 401);
            }

            if(isset($response['success'])) {

                $trackingNumber = $response['success'];
                $label = $this->provider['api_url'].'/printBordereau_15x20/?apiKey='.$this->api_key.'&tracking='.$trackingNumber;
                update_post_meta($orderObj->get_id(), '_shipping_tracking_number', wc_clean($trackingNumber));
                update_post_meta($orderObj->get_id(), '_shipping_tracking_label', wc_clean($label));
                update_post_meta($orderObj->get_id(), '_shipping_label', wc_clean($label));
                update_post_meta($orderObj->get_id(), '_shipping_tracking_url', 'https://'.$this->provider['domain'].'/suivre-un-colis/?tracking='. wc_clean($trackingNumber));
                update_post_meta($orderObj->get_id(), '_shipping_tracking_method', $this->provider['slug']);

                if($orderObj) {
                    $orderObj->update_meta_data('_shipping_tracking_number', wc_clean($trackingNumber));
                    $orderObj->update_meta_data('_shipping_tracking_label', wc_clean($label));
                    $orderObj->update_meta_data('_shipping_label', wc_clean($label));
                    $orderObj->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
                    $orderObj->save();

                    Helpers::make_note($orderObj, $trackingNumber, $this->provider['name']);

                }
            }

        } catch (\ErrorException $e) {
            new WP_Error($e->getMessage());
            wp_send_json([
                'error' => true,
                'message'=>  __("An error has occurred. Please see log file ", 'wc-bordereau-generator')
            ], 401);
        }

    }

    public function get_status() {
        return [
            "Brouillon  " => "Brouillon  ",
            "À Ramasser" => "À Ramasser",
            "Ramassage à relancer" => "Ramassage à relancer",
            "À remettre" => "À remettre",
            "En cours ramassage" => "En cours ramassage",
            "Ramassée" => "Ramassée",
            "Réceptionnée" => "Réceptionnée",
            "À Éxpédier" => "À Éxpédier",
            "En transit" => "En transit",
            "En hub" => "En hub",
            "En cours livraison" => "En cours livraison",
            "Livraison à relancer" => "Livraison à relancer",
            "Livrée" => "Livrée",
            "Livrée &amp; réglée" => "Livrée &amp; réglée",
            "Perdue" => "Perdue",
            "Cassée" => "Cassée",
            "Suspendue" => "Suspendue",
            "Annulée" => "Annulée",
            "Retour reçu" => "Retour reçu",
            "Retour non reçu" => "Retour non reçu",
            "Retour en transit" => "Retour en transit",
            "Retour remis" => "Retour remis",
        ];
    }

    /**
     * Get all centers/agencies with caching
     *
     * @return array
     * @since 4.0.0
     */
    public function get_all_centers()
    {
        $centers_path = Functions::get_path($this->provider['slug'] . '_centers_' . WC_BORDEREAU_GENERATOR_VERSION . '_' . date('Y') . '_' . (date('z') + 1) . '.json');

        if (!file_exists($centers_path)) {
            // Fetch all agencies from all wilayas (type=2 for stopdesk agencies)
            $url = $this->provider['api_url'] . '/getAgences/?key=' . $this->api_key . '&type=2';
            $request = new ElogistiaRequest($this->provider);
            $response = $request->get($url);

            if ($response && isset($response['body']) && is_array($response['body'])) {
                file_put_contents($centers_path, json_encode($response['body']));
            } else {
                return [];
            }
        }

        if (!file_exists($centers_path)) {
            return [];
        }

        $agencies = json_decode(file_get_contents($centers_path), true);

        if (!$agencies || !is_array($agencies)) {
            return [];
        }

        // Format the results to match the expected structure
        $centersResult = [];
        foreach ($agencies as $i => $item) {
            $centersResult[] = [
                'id'           => $item['Nom du bureau'] ?? '',
                'name'         => $item['Nom du bureau'] ?? '',
                'commune_name' => $item['Commune'] ?? '',
                'wilaya_id'    => (int) ($item['Code wilaya'] ?? 0),
                'address'      => $item['Adresse'] ?? ''
            ];
        }

        return $centersResult;
    }

    /**
     * Get centers for a specific wilaya
     *
     * @param int $wilaya_id
     * @return array
     * @since 4.0.0
     */
    public function get_centers_by_wilaya($wilaya_id)
    {
        $all_centers = $this->get_all_centers();

        return array_filter($all_centers, function ($center) use ($wilaya_id) {
            return (int) $center['wilaya_id'] === (int) $wilaya_id;
        });
    }

    /**
     * Get Cities from specific State
     * @param $wilaya_id
     * @param bool $hasStopDesk
     *
     * @return array
     */
    public function get_communes($wilaya_id, bool $hasStopDesk)
    {

        $communes = [];

       $url = $this->provider['api_url'].'/getMunicipalities?key='. $this->api_key . '&wilaya='. $wilaya_id;
        // $url = $this->provider['api_url'].'/getMunicipalities?key='. $this->api_key;

        $request = new ElogistiaRequest($this->provider);
        $response = $request->get($url);

        if ($response) {

            $communesResult = [];

            $found = array_filter($response['body'], function ($v, $k) use ($wilaya_id) {
                return (int) $v['wilaya'] == $wilaya_id;
            }, ARRAY_FILTER_USE_BOTH);

            foreach ($found as $i => $item) {
                $communesResult[$i]['id'] = $item['name'];
                $communesResult[$i]['name'] = $item['name'];
            }


            return $communesResult;
        }

        return $communes;
    }
}
