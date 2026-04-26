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

class TonColisSettings
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
    public function import_shipping_class($flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled, $agency_import_enabled = null, $is_economic = null)
    {

        $cookiesJar = $this->auth();
        $client = new Client(['cookies' => true]);
        $wilayas = $this->get_wilayas();

        if ($flat_rate_enabled) {

            if ($flat_rate_label) {
                if (get_option($this->provider['slug'] . '_flat_rate_label')) {
                    update_option($this->provider['slug'] . '_flat_rate_label', $flat_rate_label);
                } else {
                    add_option($this->provider['slug'] . '_flat_rate_label', $flat_rate_label);
                }
            }
        }

        if ($pickup_local_enabled) {

            if ($pickup_local_label) {

                if (get_option($this->provider['slug'] . '_pickup_local_label')) {
                    update_option($this->provider['slug'] . '_pickup_local_label', $pickup_local_label);
                } else {
                    add_option($this->provider['slug'] . '_pickup_local_label', $pickup_local_label);
                }
            }
        }

        if ($wilayas) {

            $url = $this->provider['api_url'] . 'FR/Colis.awp';

            $response = $client->request('POST', $url, [
                'cookies' => $cookiesJar,
                'form_params' => [
                    'WD_ACTION_' => 'AJAXEXECUTE',
                    'EXECUTEPROC' => 'PAGE_Colis.Chargement_Commune',
                    'WD_CONTEXTE_' => 'A17',
                    'PA1' => 1,
                    'PA2' => '0'
                ]
            ]);

            $response = $response->getBody()->getContents();
            $jsonHome = json_decode($response, true);


            $url = $this->provider['api_url'] . 'FR/Colis.awp';

            $response = $client->request('POST', $url, [
                'cookies' => $cookiesJar,
                'form_params' => [
                    'WD_ACTION_' => 'AJAXEXECUTE',
                    'EXECUTEPROC' => 'PAGE_Colis.Chargement_Commune',
                    'WD_CONTEXTE_' => 'A17',
                    'PA1' => 1,
                    'PA2' => '1'
                ]
            ]);

            $response = $response->getBody()->getContents();
            $jsonStopDesk = json_decode($response, true);

            $pricesHome = explode(";", $jsonHome['Tarif']);
            $pricesStopDesk = explode(";", $jsonStopDesk['Tarif']);

            foreach ($wilayas as $key => $wilaya) {
                $wilayas[$key]['home'] = $pricesHome[$key];
                $wilayas[$key]['desk'] = $pricesStopDesk[$key];
            }

            // file_put_contents($path,  json_encode($prices));
        }

        if (get_option('shipping_zones_grouped') && get_option('shipping_zones_grouped') == 'yes') {
            $this->shipping_zone_grouped($wilayas, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled);
        } else {
            $this->shipping_zone_ungrouped($wilayas, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled);
        }


        wp_send_json_success(
            array(
                'message' => 'success',
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
        try {
            $api_key = get_option($this->provider['slug'] . '_username');
            $api_token = get_option($this->provider['slug'] . '_password');
            $client = new Client(['cookies' => true]);
            $jar = new CookieJar;
            $client->request('POST', $this->provider['login_url'], [

                'form_params' => [
                    'WD_ACTION_' => 'AJAXEXECUTE',
                    'EXECUTEPROCCHAMPS' => 'ServeurAPI.API_Connecte',
                    'WD_CONTEXTE_' => $this->provider['extra']['context'],
                    'PA1' => $api_key,
                    'PA2' => $api_token,
                    'PA3' => $this->provider['extra']['pa3']
                ],
                'cookies' => $jar
            ]);


            return $jar;
        } catch (\Exception $e) {
            set_transient('order_bulk_add_error', $e->getMessage(), 45);
            $logger = \wc_get_logger();
            $logger->error('Bulk Upload Error: ' . $e->getMessage(), array('source' => WC_BORDEREAU_POST_TYPE));
        }
    }

    private function get_wilayas()
    {

        $path = Functions::get_path($this->provider['slug'] . '_wilaya_v2.json');

        if (file_exists($path)) {
            $response = json_decode(file_get_contents($path), true);
            if (count(array_filter($response))) {
                return $response;
            }
        }

        $wilayasResult = [];

        $cookiesJar = $this->auth();

        $client = new Client(['cookies' => true]);

        $response = $client->request('GET', $this->provider['api_url'] . 'FR/Colis.awp', [
            'cookies' => $cookiesJar
        ]);

        $dom = new DOMDocument();

        $dom->loadHTML($response->getBody()->getContents());

        $sel = $dom->getElementById("A17");

        $optionTags = $sel->getElementsByTagName('option');

        foreach ($optionTags as $key => $tag) {
            if ($tag->nodeValue != 'Wilaya') {
                $wilayasResult[$key]['id'] = $tag->getAttribute('value');
                $wilayasResult[$key]['name'] = $tag->nodeValue;
            }
        }

        file_put_contents($path, json_encode($wilayasResult));

        return $wilayasResult;
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


        // Set memory limit to -1 (unlimited) and set time limit to 0 (unlimited)
        ini_set('memory_limit', '-1');
		set_time_limit(0);

        $wilayas = $response_array;
        $i = 0;
        foreach ($wilayas as $key => $wilaya) {

            $found = null;

            // Get all shipping zones
            $zones = WC_Shipping_Zones::get_zones();

            // Iterate through the zones and find the one with the matching name
            $found_zone = null;
            foreach ($zones as $zone) {

                if ($zone['zone_locations'][0]->code === 'DZ:DZ-' . str_pad($wilaya['id'], 2, '0', STR_PAD_LEFT)) {
                    $found_zone = $zone;
                    break;
                }
            }


            if (!$found_zone) {
                $zone = new WC_Shipping_Zone();
                $zone->set_zone_name(ucfirst(Helpers::normalizeCityName($wilaya['name'])));
                $zone->set_zone_order($i);
                $zone->add_location('DZ:DZ-' . str_pad($wilaya['id'], 2, '0', STR_PAD_LEFT), 'state');
                $zone->save();
            } else {
                $zone = WC_Shipping_Zones::get_zone($found_zone['id']);
            }

            if (isset($wilaya['home']) && $flat_rate_enabled && $wilaya['home']) {
                $flat_rate_name = 'flat_rate_zr_express';
                if (is_hanout_enabled()) {
                    $flat_rate_name = 'flat_rate';
                }
                $instance_flat_rate = $zone->add_shipping_method($flat_rate_name);
                $shipping_method_flat_rate = WC_Shipping_Zones::get_shipping_method($instance_flat_rate);

                $shipping_method_configuration_flat_rate = [
                    'woocommerce_'.$flat_rate_name.'_title' => $flat_rate_label != '' ? $flat_rate_label : __('Flat rate', 'wc-bordereau-generator'),
                    'woocommerce_'.$flat_rate_name.'_cost' => $wilaya['home'],
                    'woocommerce_'.$flat_rate_name.'_provider' => $this->provider['slug'],
                    'instance_id' => $instance_flat_rate,
                ];

                $_REQUEST['instance_id'] = $instance_flat_rate;
                $shipping_method_flat_rate->set_post_data($shipping_method_configuration_flat_rate);
                $shipping_method_flat_rate->process_admin_options();
            }

            if (isset($wilaya['desk']) && $pickup_local_enabled && $wilaya['desk']) {
                $local_pickup_name = 'local_pickup_zr_express';
                if (is_hanout_enabled()) {
                    $local_pickup_name = 'local_pickup';
                }
                $instance_local_pickup = $zone->add_shipping_method($local_pickup_name);
                $shipping_method_local_pickup = WC_Shipping_Zones::get_shipping_method($instance_local_pickup);

                $shipping_method_configuration_local_pickup = array(
                    'woocommerce_'.$local_pickup_name.'_title' => $pickup_local_label != '' ? $pickup_local_label : __('Local Pickup', 'wc-bordereau-generator'),
                    'woocommerce_'.$local_pickup_name.'_cost' => $wilaya['desk'],
                    'woocommerce_'.$local_pickup_name.'_provider' => $this->provider['slug'],
                );

                $_REQUEST['instance_id'] = $instance_local_pickup;
                $shipping_method_local_pickup->set_post_data($shipping_method_configuration_local_pickup);
                $shipping_method_local_pickup->process_admin_options();

            }

            $i++;
        }
    }

    public function bulk_track($codes)
    {

        $cookiesJar = $this->auth();
        $tmpFile = tempnam(sys_get_temp_dir(), 'download_');
        $resource = fopen($tmpFile, 'w');

        $client = new Client(['cookies' => true]);

        $startDate = new DateTime();
        $startDate->sub(new DateInterval('P1M'));

        // Date one month from now
        $endDate = new DateTime();

        $result = [];

        $response = $client->request('POST', $this->provider['api_url'] . 'FR/Exporter.awp', [
            'cookies' => $cookiesJar,
            'form_params' => [
                'WD_ACTION_' => 'AJAXEXECUTE',
                'EXECUTEPROC' => 'PAGE_Exporter.Chargement',
                'WD_CONTEXTE_' => 'A6',
                'PA1' => $startDate->format('Ymd'),
                'PA2' => $endDate->format('Ymd'),
            ],
        ]);

        $filePath = (string)$response->getBody();

        $client->request('GET', $this->provider['api_url'] . 'FR/Excel.awp?P1=' . $filePath, [
            'cookies' => $cookiesJar,
            'sink' => $resource
        ]);


        if ($xls = SimpleXLS::parse($tmpFile)) {
            $rows = $xls->rows();

            foreach ($rows as $key => $value) {
                $code = $value[1];
                if (in_array($code, $codes)) {
                    $status = $value[11];
                    $result[$code] = $status;
                }

            };
        } else {
            new \WP_Error(500, SimpleXLS::parseError());
        }


        unlink($tmpFile);
        return $result;
    }

	function encodeArabicJson($data) {
		$data = reset($data);

		// Create the new structure with encoded Arabic text
		$encodedData = [
			'IDColis' => $data['IDColis'],
			'HUB_Depart' => $data['HUB_Depart'],
			'HUB_Attache' => (string)$data['HUB_Attache'],
			'DateA' => $data['DateA'],
			'Date_Creation' => str_replace(['T', '.'], '', $data['Date_Creation']),
			'TypeColis' => $data['TypeColis'],
			'NomTypeColis' => '',
			'TypeLivraison' => $data['TypeLivraison'],
			'NomTypeLivraison' => '',
			'Tracking' => $data['Tracking'],
			'IDUtilisateur' => $data['IDUtilisateur'],
			'IDEntreprise' => $data['IDEntreprise'],
			'IDSituation' => $data['IDSituation'],
			'Situation' => $data['Situation'],
			'Commentaire' => $data['Commentaire'],
			'Client' => Helpers::encodeArabicText($data['Client']),
			'MobileA' => $data['MobileA'],
			'MobileB' => $data['MobileB'],
			'Adresse' =>  Helpers::encodeArabicText($data['Adresse']),
			'IDWilaya' => $data['IDWilaya'],
			'Wilaya' => '',
			'Commune' => $data['Commune'],
			'Total' => $data['Total'],
			'Note' => $data['Note'],
			'Date_Receptionne' => $data['Date_Receptionne'],
			'Date_Livree' => $data['Date_Livree'],
			'DateA_Action' => $data['DateA_Action'],
			'DateH_Action' => str_replace(['T', '.'], '', $data['DateH_Action']),
			'IDLivreur' => $data['IDLivreur'],
			'IDLivreurEX' => $data['IDLivreurEX'],
			'Poids' => $data['Poids'],
			'AnnulerRecouvert' => $data['AnnulerRecouvert'],
			'TProduit' =>  Helpers::encodeArabicText($data['TProduit']),
			'Produit' => [],
			'Qtn' => 0,
			'Excel' => $data['Excel'],
			'id_Externe' => $data['id_Externe']
		];

		$jsonString = json_encode([$encodedData],
			JSON_UNESCAPED_UNICODE |
			JSON_UNESCAPED_SLASHES |
			JSON_PRESERVE_ZERO_FRACTION
		);

		// Remove any remaining double escapes if they exist
		$jsonString = str_replace('\\\\u', '\u', $jsonString);

		return $jsonString;
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
        $overweight_checkout = $fields['overweight_checkout']['choices'][0]['name'] ?? null; // the fee is in the checkout
        $overweight = $fields['overweight']['choices'][0]['name'] ?? null; // the fee is in the checkout

        $overweightCase = get_option($overweight) ? get_option($overweight) : 'recalculate-without-overweight';
        $overweightCheckoutCase = get_option($overweight_checkout) ? get_option($overweight_checkout) : 'recalculate-without-overweight';
        $total = get_option($key) ? get_option($key) : 'without-shipping';

        foreach ($orders as $order) {

            $params = $order->get_data();
            $orderId = $order->get_id();
            $products = $order->get_items();
            $productString = Helpers::generate_products_string($products);

            $stop_desk = 0;

            if ($order->has_shipping_method('local_pickup') || $order->has_shipping_method('local_pickup_zr_express')) {
                $stop_desk = 1;
            }

            $url = $this->provider['api_url'] . 'FR/Colis.awp';

            if ($order->get_meta('_is_stock-desk')) {
                $order->update_meta_data('_is_stock-desk', $stop_desk);
                $order->save();
            } else {
                $order->add_meta_data('_is_stock-desk', $stop_desk);
                $order->save();
            }

            $pattern = "/DZ-[0-9]{2}/";

            $wilaya = $params['billing']['state'];

            if (preg_match($pattern, $wilaya)) {
                $wilayaId = (int) str_replace("DZ-", "", $wilaya);

            } else {

                $patternSecond = "/^(\d+)/"; // Regular expression to match a number at the beginning of the string

                if (preg_match($patternSecond, $wilaya, $matches)) {
                    $wilayaId = $matches[1]; // The first captured group, which is the number

                } else {


					if(is_hanout_enabled()) {

						$states_dz = array( 'Adrar' => '01 Adrar - أدرار', 'Chlef' => '02 Chlef - الشلف', 'Laghouat' => '03 Laghouat - الأغواط', 'Oum El Bouaghi' => '04 Oum El Bouaghi - أم البواقي', 'Batna' => '05 Batna - باتنة', 'Béjaïa' => '06 Béjaïa - بجاية', 'Biskra' => '07 Biskra - بسكرة', 'Bechar' => '08 Bechar - بشار', 'Blida' => '09 Blida - البليدة', 'Bouira' => '10 Bouira - البويرة', 'Tamanrasset' => '11 Tamanrasset - تمنراست ', 'Tébessa' => '12 Tébessa - تبسة ', 'Tlemcene' => '13 Tlemcene - تلمسان', 'Tiaret' => '14 Tiaret - تيارت', 'Tizi Ouzou' => '15 Tizi Ouzou - تيزي وزو', 'Alger' => '16 Alger - الجزائر', 'Djelfa' => '17 Djelfa - الجلفة', 'Jijel' => '18 Jijel - جيجل', 'Sétif' => '19 Sétif - سطيف', 'Saïda' => '20 Saïda - سعيدة', 'Skikda' => '21 Skikda - سكيكدة', 'Sidi Bel Abbès' => '22 Sidi Bel Abbès - سيدي بلعباس', 'Annaba' => '23 Annaba - عنابة', 'Guelma' => '24 Guelma - قالمة', 'Constantine' => '25 Constantine - قسنطينة', 'Médéa' => '26 Médéa - المدية', 'Mostaganem' => '27 Mostaganem - مستغانم', 'MSila' => '28 MSila - مسيلة', 'Mascara' => '29 Mascara - معسكر', 'Ouargla' => '30 Ouargla - ورقلة', 'Oran' => '31 Oran - وهران', 'El Bayadh' => '32 El Bayadh - البيض', 'Illizi' => '33 Illizi - إليزي ', 'Bordj Bou Arreridj' => '34 Bordj Bou Arreridj - برج بوعريريج', 'Boumerdès' => '35 Boumerdès - بومرداس', 'El Tarf' => '36 El Tarf - الطارف', 'Tindouf' => '37 Tindouf - تندوف', 'Tissemsilt' => '38 Tissemsilt - تيسمسيلت', 'Eloued' => '39 Eloued - الوادي', 'Khenchela' => '40 Khenchela - خنشلة', 'Souk Ahras' => '41 Souk Ahras - سوق أهراس', 'Tipaza' => '42 Tipaza - تيبازة', 'Mila' => '43 Mila - ميلة', 'Aïn Defla' => '44 Aïn Defla - عين الدفلى', 'Naâma' => '45 Naâma - النعامة', 'Aïn Témouchent' => '46 Aïn Témouchent - عين تموشنت', 'Ghardaïa' => '47 Ghardaïa - غرداية', 'Relizane' => '48 Relizane- غليزان', 'Timimoun' => '49 Timimoun - تيميمون', 'Bordj Baji Mokhtar' => '50 Bordj Baji Mokhtar - برج باجي مختار', 'Ouled Djellal' => '51 Ouled Djellal - أولاد جلال', 'Béni Abbès' => '52 Béni Abbès - بني عباس', 'Aïn Salah' => '53 Aïn Salah - عين صالح', 'In Guezzam' => '54 In Guezzam - عين قزام', 'Touggourt' => '55 Touggourt - تقرت', 'Djanet' => '56 Djanet - جانت', 'El MGhair' => '57 El MGhair - المغير', 'El Menia' => '58 El Menia - المنيعة', );

						$transformed_states = array();

						foreach ($states_dz as $state => $value) {
							$code = substr($value, 0, 2);
							$transformed_states[$state] = "DZ-" . $code;
						}

						$wilayaId = (int) str_replace("DZ-", "", $transformed_states[$wilaya]);

					} else {

						$wilayas = $this->get_wilayas();

						$found = array_values(array_filter($wilayas, function ($w) use ($wilaya) {
							return Helpers::slugify($w['name']) == Helpers::slugify($wilaya);
						}))[0] ?? null;

						if ($found) {
							$wilayaId = $found['id'];
						}
					}
                }
            }

            $phone = explode('/', $params['billing']['phone']);

            $cart_value = 0;
            $total_weight = 0;

            if ($total == 'with-shipping') {
                $cart_value = (float) $order->get_total();
            } elseif ($total === 'without-shipping') {
                $cart_value =  ((float) $order->get_total()) - $order->get_total_shipping();
            }

            if ($overweightCase == 'recalculate-with-overweight') {

                foreach ( $order->get_items() as $item_id => $item ) {
                    $product = $item->get_product();
                    if ( $product ) {
                        $product_weight = $product->get_weight();
                        // Ensure the product has a weight before adding it
                        if ( $product_weight ) {
                            $total_weight += (float) $product_weight * (int) $item->get_quantity();
                        }
                    }
                }

                if ($overweightCheckoutCase == 'recalculate-with-overweight') {

                    $fees = $order->get_fees();

                    $found = false;
                    foreach ( $fees as $item_id => $item_fee ) {
                        // check if the suppliemnt fee
                        if($item_fee->get_name() == __("Surpoids (+5KG)", 'woo-bordereau-generator')) {
                            $found = true;
                            if ($total != 'with-shipping') {
                                $cart_value = $cart_value;
                            }
                            $has_suppliment = true;
                            break;
                        }
                    }

                    if (! $found) {
                        if ($total_weight > 5) {
                            $zonePrice = 50;
                            $shippingOverweightPrice = max(0, ceil($total_weight - 5)) * $zonePrice;
                            $cart_value = $cart_value + $shippingOverweightPrice;
                        }
                    }

                } else {
                    if ($total_weight > 5) {
                        $zonePrice = 50;
                        $shippingOverweightPrice = max(0, ceil($total_weight - 5)) * $zonePrice;
                        $cart_value = $cart_value + $shippingOverweightPrice;
                    }
                }
            }

            $name = $params['billing']['first_name'] . ' ' . $params['billing']['last_name'];

            $date = new DateTime();
            $formattedDate = $date->format('YmdHis');  // Gives YYYYMMDDHHMMSS

            // For milliseconds
            $milliseconds = round(microtime(true) * 1000) % 1000;  // Gives the current milliseconds

            // Combine both to get the desired format
            $creationDate = $formattedDate . str_pad($milliseconds, 3, '0', STR_PAD_LEFT);

            $note = stripslashes(get_option('wc_bordreau_default-shipping-note'));

            if(!isset($phone[1]) && $order->get_meta('_billing_phone_2')) {
                $phone[1] = $order->get_meta('_billing_phone_2');
            }


            $data = [
                [
                    "IDColis" => 0,
                    "TypeColis" => 0,
                    "TypeLivraison" => $stop_desk ? 1 : 0,
                    "Date_Creation" => $creationDate,
                    "Client" => $name,
                    "MobileA" => Helpers::clean_phone_number($phone[0]),
                    "MobileB" => isset($phone[1]) ? Helpers::clean_phone_number($phone[1]) : "",
                    "Adresse" => $params['billing']['address_1'] != '' ? $params['billing']['address_1'] : $params['billing']['city'],
                    "IDWilaya" => (int) $wilayaId,
                    "Commune" => $params['billing']['city'],
                    "Total" => (int) $cart_value,
                    "Note" => $note ?? 'Commande #' . $orderId,
                    "IDLivreur" => 0,
                    "IDLivreurEX" => 0,
                    "Poids" => $total_weight,
                    "AnnulerRecouvert" => 0,
                    "TProduit" => $productString,
                    "id_Externe" => $order->get_order_number()
                ]
            ];


            $cookiesJar = $this->auth();
            $client = new Client(['cookies' => true]);
            $response = $client->request('POST', $url, [
                'cookies' => $cookiesJar,
                'form_params' => [
                    'WD_ACTION_' => 'AJAXEXECUTE',
                    'EXECUTEPROCCHAMPS' => 'PAGE_Colis.Chargement',
                    'WD_CONTEXTE_' => 'A58',
                    'PA1' => json_encode($data)
                ],
                'verify' => false,
	            'headers' => [
		            'accept' => '*/*',
		            'accept-language' => 'en-US,en;q=0.9',
		            'content-type' => 'application/x-www-form-urlencoded',
		            'origin' => 'https://zr.express',
		            'priority' => 'u=1, i',
		            'referer' => 'https://zr.express/ZR_WEB/FR/Colis.awp',
		            'sec-ch-ua' => '"Not)A;Brand";v="8", "Chromium";v="138", "Brave";v="138"',
		            'sec-ch-ua-mobile' => '?0',
		            'sec-ch-ua-platform' => '"macOS"',
		            'sec-fetch-dest' => 'empty',
		            'sec-fetch-mode' => 'cors',
		            'sec-fetch-site' => 'same-origin',
		            'sec-gpc' => '1',
		            'user-agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/138.0.0.0 Safari/537.36',
	            ]
            ]);

            if ($response->getStatusCode() == 200) {

                $result = $response->getBody()->getContents();
                // Load the XML string
                $xml = simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA);

                // Convert the SimpleXMLElement object to a JSON string
                $jsonString = json_encode($xml);

                // Convert the JSON string back to an associative array
                $array = json_decode($jsonString, true);

                // The JSON string is contained within the RESULTAT node
                $jsonData = $array['RESULTAT'];

                // Decode the JSON data to get the array
                $dataArray = json_decode($jsonData, true);


                if($dataArray['Colis'][0]['MessageRetour'] != "Good") {
                    set_transient('order_bulk_add_error', $dataArray['Colis'][0]['MessageRetour'], 45);
                    $logger = \wc_get_logger();
                    $logger->error('Bulk Upload Error: ' . $dataArray['Colis'][0]['MessageRetour'], array('source' => WC_BORDEREAU_POST_TYPE));

                    return;
                }

                $trackingNumber = $dataArray['Colis'][0]['Tracking'];

                $url = $this->provider['api_url'].'FR/Liste.awp?P1=1';

                $response = $client->request('POST', $url, [
                    'cookies' => $cookiesJar,
                    'form_params' => [
                        'WD_ACTION_' => 'AJAXEXECUTE',
                        'EXECUTEPROCCHAMPS' => 'ServeurAPI.Chargement_G',
                        'WD_CONTEXTE_' => '',
                        'PA1' => 'liste',
                        'PA2' => '1',
                        'PA3' => '',
                        'PA4' => ''
                    ],
                    'verify' => false,
                ]);

                $meta = [];

                if ($response->getStatusCode() == 200) {

                    // Load the XML string
                    $xml = simplexml_load_string($response->getBody()->getContents(), 'SimpleXMLElement', LIBXML_NOCDATA);

                    // Convert the SimpleXMLElement object to a JSON string
                    $jsonString = json_encode($xml);

                    // Convert the JSON string back to an associative array
                    $array = json_decode($jsonString, true);

                    // The JSON string is contained within the RESULTAT node
                    $jsonData = $array['RESULTAT'];

                    // Decode the JSON data to get the array
                    $dataArray = json_decode($jsonData, true);

                    $meta = array_filter($dataArray['Colis'], function($item) use ($trackingNumber) {
                        return $item['Tracking'] == $trackingNumber;
                    });

                }

                if($trackingNumber) {

                    $post_id = $order->get_id();
                    $etq = get_rest_url( null, 'woo-bordereau/v1/slip/print/'.$this->provider['slug'].'/'. $post_id );

                    update_post_meta($post_id, '_shipping_tracking_number', wc_clean($trackingNumber));
                    update_post_meta($post_id, '_shipping_tracking_label', wc_clean($etq));
                    update_post_meta($post_id, '_shipping_label', wc_clean($etq));
                    update_post_meta($post_id, '_shipping_tracking_url', $this->provider['api_url'].'FR/ColisClient.awp?P1='.$trackingNumber);
                    update_post_meta($post_id, '_shipping_tracking_method', $this->provider['slug']);
                    update_post_meta($post_id, '_shipping_tracking_meta', $meta);

                    $order = wc_get_order($post_id);

                    if($order) {
                        $order->update_meta_data('_shipping_tracking_number', wc_clean($trackingNumber));
                        $order->update_meta_data('_shipping_tracking_label', wc_clean($etq));
                        $order->update_meta_data('_shipping_label', wc_clean($etq));
                        $order->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
                        $order->update_meta_data('_shipping_tracking_meta', $meta);

                        $order->save();

                        Helpers::make_note($order, $trackingNumber, $this->provider['name']);

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
    public function get_communes($wilaya_id, bool $hasStopDesk)
    {

        $path = Functions::get_path($this->provider['slug'] . '_' . $wilaya_id . '_communes_' . ($hasStopDesk ? 'stopdesk' : 'home') . 'v2.json');

        if (file_exists($path)) {
            return json_decode(file_get_contents($path), true);
        }

        $url = $this->provider['api_url'] . 'FR/Colis.awp';

        $communes = [];

        $cookiesJar = $this->auth();

        $client = new Client(['cookies' => true]);

        if (str_contains($wilaya_id, 'DZ-')) {
            $wilaya_id = (int) str_replace('DZ-', '', $wilaya_id);
        }

        $response = $client->request('POST', $url, [

            'cookies' => $cookiesJar,
            'form_params' => [
                'WD_ACTION_' => 'AJAXEXECUTE',
                'EXECUTEPROC' => 'PAGE_Colis.Chargement_Commune',
                'WD_CONTEXTE_' => 'A17',
                'PA1' => $wilaya_id,
                'PA2' => $hasStopDesk ? '1' : '0'
            ]
        ]);



        $response = $response->getBody()->getContents();
        $json = json_decode($response, true);


        if (isset($json["Liste"])) {
            if ($hasStopDesk) {

                foreach ($json["Liste"] as $key => $item) {
                    $communes[$key]['id'] = $item['Bureau'];
                    $communes[$key]['name'] = $item['Bureau'];
                }
            } else {
                foreach ($json["Liste"] as $key => $item) {
                    $communes[$key]['id'] = $item['Commune'];
                    $communes[$key]['name'] = $item['Commune'];
                }
            }
        }

        file_put_contents($path, json_encode($communes));

        return $communes;
    }

    public function get_status()
    {

        return [
            "En Préparation" => "En Préparation",
            "En Préparation - Confirmé" => "En Préparation - Confirmé",
            "En Préparation - Annuler" => "En Préparation - Annuler",
            "En Préparation - Ne Réponde pas" => "En Préparation - Ne Réponde pas",
            "En Préparation - Reportée" => "En Préparation - Reportée",
            "En Préparation - Message Envoyé" => "En Préparation - Message Envoyé",
            "En Traitement - Prêt à Expédie" => "En Traitement - Prêt à Expédie",
            "Au Bureau" => "Au Bureau",
            "SD - Appel sans Réponse 1" => "SD - Appel sans Réponse 1",
            "SD - Appel sans Réponse 2" => "SD - Appel sans Réponse 2",
            "SD - Appel sans Réponse 3" => "SD - Appel sans Réponse 3",
            "SD - Reporté" => "SD - Reporté",
            "SD - Annuler par le Client" => "SD - Annuler par le Client",
            "SD - Annuler 3x" => "SD - Annuler 3x",
            "SD - En Attente du Client" => "SD - En Attente du Client",
            "Sortir en livraison" => "Sortir en livraison",
            "En livraison" => "En livraison",
            "Appel sans Réponse 1" => "Appel sans Réponse 1",
            "Appel sans Réponse 2" => "Appel sans Réponse 2",
            "Appel sans Réponse 3" => "Appel sans Réponse 3",
            "Reporté" => "Reporté",
            "Annuler par le Client" => "Annuler par le Client",
            "Attente Information" => "Attente Information",
            "Retour Livreur" => "Retour Livreur",
            "Livrée" => "Livrée",
            "Livrée [ Encaisser ]" => "Livrée [ Encaisser ]",
            "Livrée [ Recouvert ]" => "Livrée [ Recouvert ]",
            "Dispatcher" => "Dispatcher",
            "Retour Navette" => "Retour Navette",
            "Retour Client" => "Retour Client",
            "Supprimée" => "Supprimée",
            "Restaurée" => "Restaurée",
            "A Relancé" => "A Relancé",
            "Retour de Dispatche" => "Retour de Dispatche",
            "Retour Stock" => "Retour Stock",
        ];

    }


}
