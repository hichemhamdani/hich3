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
use WooBordereauGenerator\Admin\BordereauGeneratorAdmin;
use WooBordereauGenerator\Helpers;
use WP_Error;

class TonColisAPISettings
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
    public function import_shipping_class($flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled, $agency_import_enabled = null, $is_economic = null)
    {
        $url = $this->provider['api_url'] . '/Tarification';

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_HTTPHEADER     => [
                'Key: '   . $this->api_key,
                'Token: ' . $this->api_token,
            ],
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
        $prices = json_decode($response, true);

        if ($flat_rate_enabled && $flat_rate_label) {
            update_option($this->provider['slug'] . '_flat_rate_label', $flat_rate_label);
        }

        if ($pickup_local_enabled && $pickup_local_label) {
            update_option($this->provider['slug'] . '_pickup_local_label', $pickup_local_label);
        }

        if ($agency_import_enabled) {
            update_option($this->provider['slug'] . '_agencies_import', true);
        }

        if (get_option('shipping_zones_grouped') && get_option('shipping_zones_grouped') == 'yes') {
            $this->shipping_zone_grouped($prices['Wilaya'] ?? [], $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled);
        } else {
            $this->shipping_zone_ungrouped($prices['Wilaya'] ?? [], $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled, $agency_import_enabled);
        }

        wp_send_json_success(['message' => 'success']);
    }

    /**
     * Get all stop-desk centers from /Stopdesk (all wilayas).
     * Cached daily. Used for import and public checkout dropdown.
     *
     * @return array  Array of ['id' => Code, 'name' => Libelle, 'address' => Adresse, 'code' => Code, 'wilaya_id' => Ville]
     */
    public function get_all_centers(): array
    {
        $path = Functions::get_path(
            $this->provider['slug'] . '_all_centers_' . WC_BORDEREAU_GENERATOR_VERSION . '_' . date('Y-m-d') . '.json'
        );

        if (! file_exists($path) || file_get_contents($path) === 'null') {
            $url  = $this->provider['api_url'] . '/Stopdesk';
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL            => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING       => '',
                CURLOPT_MAXREDIRS      => 10,
                CURLOPT_TIMEOUT        => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST  => 'GET',
                CURLOPT_HTTPHEADER     => [
                    'Key: '   . $this->api_key,
                    'Token: ' . $this->api_token,
                ],
            ]);
            $response = curl_exec($curl);
            curl_close($curl);

            $decoded  = json_decode($response, true);
            $communes = $decoded['Commune'] ?? [];
            file_put_contents($path, json_encode($communes));
        }

        $json = json_decode(file_get_contents($path), true);

        if (! is_array($json)) {
            return [];
        }

        // Normalise to the standard shape used by other providers
        $result = [];
        foreach ($json as $item) {
            $result[] = [
                'id'       => $item['Code'],
                'code'     => $item['Code'],
                'name'     => $item['Libelle'],
                'address'  => $item['Adresse'],
                'wilaya_id' => (int) $item['Ville'],
            ];
        }

        return $result;
    }


    private function shipping_zone_grouped($response_array, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled)
    {

    }

    public function get_wilayas()
    {
        $path = Functions::get_path($this->provider['slug'].'_'.WC_BORDEREAU_GENERATOR_VERSION.'_wilayas.json');

        if (! file_exists($path)) {
            $content = $this->get_wilaya_from_provider();
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

        if ($this->api_token && $this->api_key) {

            $url = $this->provider['api_url'].'/Tarification';

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    'Key: ' . $this->api_key,
                    'Token: ' . $this->api_token
                ),
            ));

            $response = curl_exec($curl);
            curl_close($curl);


            if (empty($response)) {
                wp_send_json([
                    'error' => true,
                    'message'=> __("An error has occurred. Please see log file ", 'woo-bordereau-generator')
                ], 401);
            }

            $result = [];
            $prices = json_decode($response, true);
            foreach ($prices['Wilaya'] as $key => $item) {
                $result[$key]['id'] = $item['ID'];
                $result[$key]['name'] = $item['Libellé'];
            }

            return $result;
        }

        return null;
    }

    /**
     * @param $response_array
     * @param $flat_rate_label
     * @param $pickup_local_label
     * @param $flat_rate_enabled
     * @param $pickup_local_enabled
     * @return void
     */
    private function shipping_zone_ungrouped($response_array, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled, $agency_import_enabled = false)
    {
        ini_set('memory_limit', '-1');
        set_time_limit(0);

        // Pre-load all centers once when doing agency import
        $all_centers = [];
        if ($agency_import_enabled) {
            $all_centers = $this->get_all_centers();
        }

        $i = 0;

        foreach ($response_array as $key => $wilaya) {

            // Find or create the WC shipping zone for this wilaya
            $zones      = WC_Shipping_Zones::get_zones();
            $found_zone = null;
            foreach ($zones as $zone) {
                if (isset($zone['zone_locations'][0]) &&
                    $zone['zone_locations'][0]->code === 'DZ:DZ-' . str_pad($wilaya['ID'], 2, '0', STR_PAD_LEFT)) {
                    $found_zone = $zone;
                    break;
                }
            }

            if (! $found_zone) {
                $zone = new WC_Shipping_Zone();
                $zone->set_zone_name(ucfirst(Helpers::normalizeCityName($wilaya['Libellé'])));
                $zone->set_zone_order($i);
                $zone->add_location('DZ:DZ-' . str_pad($wilaya['ID'], 2, '0', STR_PAD_LEFT), 'state');
                $zone->save();
            } else {
                $zone = WC_Shipping_Zones::get_zone($found_zone['id']);
            }

            // --- Flat rate (home delivery) ---
            if (isset($wilaya['Tarfi_Domicle']) && $wilaya['Tarfi_Domicle'] && $flat_rate_enabled) {

                $flat_rate_name = is_hanout_enabled() ? 'flat_rate' : 'flat_rate_toncolis';

                $instance_flat_rate           = $zone->add_shipping_method($flat_rate_name);
                $shipping_method_flat_rate    = WC_Shipping_Zones::get_shipping_method($instance_flat_rate);

                $config_flat = [
                    'woocommerce_' . $flat_rate_name . '_title'    => $flat_rate_label ?: __('Flat rate', 'wc-bordereau-generator'),
                    'woocommerce_' . $flat_rate_name . '_tax_status' => 'none',
                    'woocommerce_' . $flat_rate_name . '_cost'     => $wilaya['Tarfi_Domicle'],
                    'woocommerce_' . $flat_rate_name . '_type'     => 'class',
                    'woocommerce_' . $flat_rate_name . '_provider' => $this->provider['slug'],
                    'instance_id'                                  => $instance_flat_rate,
                ];

                $_REQUEST['instance_id'] = $instance_flat_rate;
                $shipping_method_flat_rate->set_post_data($config_flat);
                $shipping_method_flat_rate->update_option('provider', $this->provider['slug']);
                $shipping_method_flat_rate->process_admin_options();
            }

            // --- Stop-desk (local pickup) ---
            if (isset($wilaya['Tarfi_Stopdesk']) && $wilaya['Tarfi_Stopdesk'] && $pickup_local_enabled) {

                if ($agency_import_enabled && ! empty($all_centers)) {
                    // One shipping method per stop-desk center in this wilaya
                    $centers_in_wilaya = array_filter($all_centers, function ($c) use ($wilaya) {
                        return (int) $c['wilaya_id'] === (int) $wilaya['ID'];
                    });

                    foreach ($centers_in_wilaya as $center) {
                        $local_pickup_name = is_hanout_enabled() ? 'local_pickup' : 'local_pickup_toncolis';

                        $agency_title = trim(sprintf('%s %s', $pickup_local_label, $center['name']));

                        $instance = $zone->add_shipping_method($local_pickup_name);
                        $method   = WC_Shipping_Zones::get_shipping_method($instance);

                        $config = [
                            'woocommerce_' . $local_pickup_name . '_title'     => $agency_title,
                            'woocommerce_' . $local_pickup_name . '_tax_status' => 'none',
                            'woocommerce_' . $local_pickup_name . '_cost'      => $wilaya['Tarfi_Stopdesk'],
                            'woocommerce_' . $local_pickup_name . '_type'      => 'class',
                            'woocommerce_' . $local_pickup_name . '_provider'  => $this->provider['slug'],
                            'woocommerce_' . $local_pickup_name . '_address'   => $center['address'],
                            'woocommerce_' . $local_pickup_name . '_center_id' => $center['code'],
                            'instance_id'                                      => $instance,
                        ];

                        $_REQUEST['instance_id'] = $instance;
                        $method->set_post_data($config);
                        $method->update_option('provider', $this->provider['slug']);
                        $method->process_admin_options();
                    }
                } else {
                    // Single stop-desk method for the whole wilaya
                    $local_pickup_name = is_hanout_enabled() ? 'local_pickup' : 'local_pickup_toncolis';

                    $instance             = $zone->add_shipping_method($local_pickup_name);
                    $method               = WC_Shipping_Zones::get_shipping_method($instance);

                    $config = [
                        'woocommerce_' . $local_pickup_name . '_title'     => $pickup_local_label ?: __('Local Pickup', 'wc-bordereau-generator'),
                        'woocommerce_' . $local_pickup_name . '_tax_status' => 'none',
                        'woocommerce_' . $local_pickup_name . '_cost'      => $wilaya['Tarfi_Stopdesk'],
                        'woocommerce_' . $local_pickup_name . '_type'      => 'class',
                        'woocommerce_' . $local_pickup_name . '_provider'  => $this->provider['slug'],
                    ];

                    $_REQUEST['instance_id'] = $instance;
                    $method->set_post_data($config);
                    $method->update_option('provider', $this->provider['slug']);
                    $method->process_admin_options();
                }
            }

            $i++;
        }
    }

    public function bulk_track($codes)
    {
        $codesResult = [];
        foreach ($codes as $code) {
            $codesResult[] = ["Tracking" => $code];
        }

        $data = ["Colis" => $codesResult];

        $client = new Client();

        // docs: POST /Colis/Liste
        $url = $this->provider['api_url'] . '/Colis/Liste';

        $response = $client->request('POST', $url, [
            'body'    => json_encode($data),
            'verify'  => false,
            'headers' => [
                'token'        => $this->api_token,
                'key'          => $this->api_key,
                'Content-Type' => 'application/json'
            ]
        ]);

        $response_array = json_decode((string) $response->getBody(), true);

        $resultArray = [];
        foreach ($response_array['Colis'] ?? [] as $coli) {
            // Avancement is the human-readable status; Situation is the internal state key
            $resultArray[$coli['Tracking']] = $coli['Avancement'] ?? $coli['Situation'] ?? '';
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

        foreach ($orders as $ii => $order) {

            $params = $order->get_data();
            $orderId = $order->get_id();

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

            $note = stripslashes(get_option('wc_bordreau_default-shipping-note', null));

            $colis[$ii] =  [
                'Stopdesk' => $stop_desk,
                "TypeLivraison" => $stop_desk, // Domicile : 0 & Stopdesk : 1
                "Echange" => "0", // Echange : 1
                "NomComplet" => $name,
                "Ref_Article" => "",
                "Mobile_1" => $phone[0],
                "Mobile_2" => $phone[1] ?? '',
                "Adresse" => $params['billing']['address_1'],
                "Wilaya" => $wilayaId,
                "Commune" => $params['billing']['city'],
                "Total" => $price,
                "NoteFournisseur" => $note ?? 'Commande #' . $orderId,
                "Article" =>  $productString,
                "ID_Externe" => $order->get_order_number() ,  // Votre ID ou Tracking
                "Source" => "website"
            ];
        }

        $data = [
            "Colis" => $colis
        ];

        $client = new Client();

        $url = $this->provider['api_url'].'/Colis';

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

            $order = null;
            foreach ($orders as $orderData) {
                if ($orderData->get_order_number() == $parcel['ID_Externe']) {
                    $order = $orderData;
                    break;
                }
            }

            if ($order) {

                $trackingNumber = $parcel['Tracking'];
                $post_id = $order->get_id();

                $etq = wc_clean($parcel['label']);

                update_post_meta($post_id, '_shipping_tracking_number', wc_clean($trackingNumber));
                update_post_meta($post_id, '_shipping_tracking_label', wc_clean($etq));
                update_post_meta($post_id, '_shipping_label', wc_clean($etq));
                update_post_meta($post_id, '_shipping_tracking_method', $this->provider['slug']);

                $order->update_meta_data('_shipping_tracking_number', wc_clean($trackingNumber));
                $order->update_meta_data('_shipping_tracking_label', wc_clean($etq));
                $order->update_meta_data('_shipping_label', wc_clean($etq));
                $order->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
                $order->save();

                Helpers::make_note($order, $trackingNumber, $this->provider['name']);
            }
        }
    }

    /**
     * Get a paginated list of orders from the TonColis API.
     *
     * Uses GET /Colis which returns paginated results.
     * The response contains: Nb_Colis, Nb_Page, Current_Page, Colis[].
     *
     * @param array $queryData Query parameters (page, page_size, tracking, last_status, date_creation).
     * @return array{items: array, total_data: int, current_page: int}
     */
    public function get_orders(array $queryData): array
    {
        $page = $queryData['page'] ?? 1;

        $params = ['Page' => $page];

        // Tracking search
        if (! empty($queryData['tracking'])) {
            $params['Tracking'] = $queryData['tracking'];
        }

        $url = $this->provider['api_url'] . '/Colis?' . http_build_query($params);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'GET',
            CURLOPT_HTTPHEADER     => [
                'Key: '   . $this->api_key,
                'Token: ' . $this->api_token,
            ],
        ]);

        $response = curl_exec($curl);
        curl_close($curl);

        $decoded = json_decode($response, true);

        if (! is_array($decoded) || ! isset($decoded['Colis'])) {
            return ['items' => [], 'total_data' => 0, 'current_page' => 1];
        }

        $wilayaJsonPath = __DIR__ . '/data/colivraison_wilaya.json';
        $wilayaMap = [];
        if (file_exists($wilayaJsonPath)) {
            $wilayaData = json_decode(file_get_contents($wilayaJsonPath), true);
            $wilayaMap = $wilayaData['wilayas'] ?? [];
        }

        $items = array_map(function ($coli) use ($wilayaMap) {
            $wilayaName = $wilayaMap[(string) $coli['IDWilaya']] ?? ('Wilaya ' . $coli['IDWilaya']);

            return [
                'tracking'         => $coli['Tracking'] ?? '',
                'familyname'       => $coli['NomComplet'] ?? '',
                'firstname'        => '',
                'to_commune_name'  => $coli['Commune_Bureau'] ?? '',
                'to_wilaya_name'   => $wilayaName,
                'date'             => $coli['Date_Création'] ?? $coli['DateA'] ?? '',
                'contact_phone'    => $coli['Mobile_1'] ?? '',
                'city'             => $coli['Commune_Bureau'] ?? '',
                'state'            => $wilayaName,
                'last_status'      => $coli['Avancement'] ?? '',
                'date_last_status' => $coli['Date_ActionA'] ?? $coli['Date_Action'] ?? '',
                'label'            => $coli['label'] ?? '',
            ];
        }, $decoded['Colis']);

        // Client-side filter by status if requested (API doesn't support status filter)
        if (! empty($queryData['last_status'])) {
            $statusFilter = $queryData['last_status'];
            $items = array_values(array_filter($items, function ($item) use ($statusFilter) {
                return $item['last_status'] === $statusFilter;
            }));
        }

        return [
            'items'        => $items,
            'total_data'   => (int) ($decoded['Nb_Colis'] ?? count($items)),
            'current_page' => (int) ($decoded['Current_Page'] ?? $page),
        ];
    }

    public function get_status()
    {
        return [
            "En Préparation" => "En Préparation",
            "En Traitement" => "En Traitement",
            "Au Bureau" => "Au Bureau",
            "Sortir en livraison" => "Sortir en livraison",
            "En livraison" => "En livraison",
            "Dispatcher" => "Dispatcher",
            "Retour Fournisseur" => "Retour Fournisseur",
            "Récupérer" => "Récupérer",
            "Perdu" => "Perdu",
            "EnCours" => "EnCours",
            "Ne Réponde pas #1" => "Ne Réponde pas #1",
            "Ne Réponde pas #2" => "Ne Réponde pas #2",
            "Ne Réponde pas #3" => "Ne Réponde pas #3",
            "Annuler" => "Annuler",
            "Annuler x3" => "Annuler x3",
            "Attend Information" => "Attend Information",
            "Reporté" => "Reporté",
            "Reporté Commune Erronée" => "Reporté Commune Erronée",
            "Reporté Wilaya Erronée" => "Reporté Wilaya Erronée",
            "BIZ" => "BIZ",
            "Appel Tel" => "Appel Tel",
            "SMS Envoyé" => "SMS Envoyé",
            "Recouvert" => "Recouvert"
        ];
    }

    /**
     * @param \WC_Order $order
     * @return void
     */
    public function mark_as_dispatched(\WC_Order $order)
    {
        $tracking_number = $order->get_meta('_shipping_tracking_number');

        if ($tracking_number) {

            $data = [
                'Colis' => [
                    [
                        "Tracking" => $tracking_number
                    ]
                ]
            ];

            // docs: PUT /Api_v1/aExpédier
            $url = $this->provider['api_url'] . '/aExpédier';

            wp_safe_remote_post(
                $url,
                [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'token' => $this->api_token,
                        'key' => $this->api_key,
                    ],
                    'body' => wp_json_encode($data),
                    'method' => 'PUT',
                    'data_format' => 'body',
                ]
            );

        }
    }

    /**
     * Handle webhook from TonColis/E-Com Delivery API
     *
     * Webhook payload structure:
     * {
     *   "Source": "TonCOLIS",
     *   "Nom": "Livraison_Callback",
     *   "id": "IDTest",
     *   "occurred_at": "2026-01-26T22:39:40.100",
     *   "data": {
     *     "Tracking": "TrackingTest",
     *     "Situation": "EnCours",
     *     "IDSituation": 1,
     *     "Avancement": "En Préparation",
     *     "IDAvancement": 1
     *   }
     * }
     *
     * @param array $provider Provider configuration
     * @param string $jsonData JSON webhook payload
     * @param array $headers Request headers (optional, for signature verification)
     * @return array Response array with success status
     */
    public function handle_webhook($provider, $jsonData, $headers = [])
    {
        $provider_slug = $provider['slug'];
        $webhook_token = get_option($provider_slug . '_webhook_token');

        // Verify webhook token if configured
        if (!empty($webhook_token)) {
            // Check for token in headers (Source header or custom token header)
            $received_token = null;
            
            // Check various possible header locations
            if (isset($_SERVER['HTTP_X_WEBHOOK_TOKEN'])) {
                $received_token = $_SERVER['HTTP_X_WEBHOOK_TOKEN'];
            } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
                $received_token = str_replace('Bearer ', '', $_SERVER['HTTP_AUTHORIZATION']);
            } elseif (isset($_SERVER['HTTP_TOKEN'])) {
                $received_token = $_SERVER['HTTP_TOKEN'];
            }

            // If token verification is required and doesn't match, reject
            if ($received_token !== null && $received_token !== $webhook_token) {
                error_log($provider['name'] . ' webhook: Token verification failed');
                return [
                    'success' => false,
                    'message' => __('Invalid webhook token', 'woo-bordereau-generator')
                ];
            }
        }

        // Parse JSON data
        $data = json_decode($jsonData, true);

        if (!$data || json_last_error() !== JSON_ERROR_NONE) {
            return [
                'success' => false,
                'message' => __('Invalid JSON payload', 'woo-bordereau-generator')
            ];
        }

        // Validate webhook structure
        if (!isset($data['data']) || !isset($data['data']['Tracking'])) {
            return [
                'success' => false,
                'message' => __('Missing required fields in webhook payload', 'woo-bordereau-generator')
            ];
        }

        $tracking = $data['data']['Tracking'];
        $situation = $data['data']['Situation'] ?? null;
        $avancement = $data['data']['Avancement'] ?? null;
        $id_situation = $data['data']['IDSituation'] ?? null;
        $id_avancement = $data['data']['IDAvancement'] ?? null;
        $occurred_at = $data['occurred_at'] ?? null;
        $external_id = $data['id'] ?? null;

        // Determine the status text (prefer Avancement as it's more descriptive)
        $status_text = $avancement ?: $situation;

        if (empty($tracking)) {
            return [
                'success' => false,
                'message' => __('Missing tracking number in webhook payload', 'woo-bordereau-generator')
            ];
        }

        // Find the order by tracking number or external ID
        $theorder = $this->find_order_by_tracking_or_external_id($tracking, $external_id);

        if (!$theorder) {
            return [
                'success' => false,
                'message' => __('Order not found for tracking: ', 'woo-bordereau-generator') . $tracking
            ];
        }

        // Update order meta with latest status
        if (!empty($status_text)) {
            $order_id = $theorder->get_id();

            // Update post meta (for compatibility)
            update_post_meta($order_id, '_shipping_tracking_number', wc_clean($tracking));
            update_post_meta($order_id, '_shipping_tracking_method', $provider_slug);

            // Update tracking status via centralized method (fires action hook)
            BordereauGeneratorAdmin::update_tracking_status($theorder, $status_text, 'webhook');

            // Update additional webhook meta (HPOS compatible)
            $theorder->update_meta_data('_webhook_situation', $situation);
            $theorder->update_meta_data('_webhook_avancement', $avancement);
            $theorder->update_meta_data('_webhook_id_situation', $id_situation);
            $theorder->update_meta_data('_webhook_id_avancement', $id_avancement);
            $theorder->update_meta_data('_webhook_last_update', $occurred_at);
            $theorder->save();

            // Add order note
            $theorder->add_order_note(
                sprintf(
                    __('%s Update: %s (Situation: %s)', 'woo-bordereau-generator'),
                    $provider['name'],
                    $avancement ?: '-',
                    $situation ?: '-'
                )
            );

            // Update WooCommerce order status based on delivery status
            (new \WooBordereauGenerator\Admin\BordereauGeneratorAdmin(WC_BORDEREAU_POST_TYPE, WC_BORDEREAU_GENERATOR_VERSION))
                ->update_orders_status($theorder, $status_text);

            return [
                'success' => true,
                'updated' => 1,
                'order_id' => $order_id,
                'tracking' => $tracking,
                'status' => $status_text,
                'situation' => $situation,
                'avancement' => $avancement
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
    private function find_order_by_tracking_or_external_id($tracking, $external_id)
    {
        // Try to find order by external ID first (if provided and numeric)
        if (!empty($external_id) && is_numeric($external_id)) {
            $order = wc_get_order($external_id);
            if ($order) {
                return $order;
            }
        }

        // Search by tracking number in meta
        if (!empty($tracking)) {
            $args = array(
                'limit' => 1,
                'status' => 'any',
                'meta_key' => '_shipping_tracking_number',
                'meta_value' => $tracking,
                'type' => 'shop_order',
                'return' => 'objects',
            );

            $query = new \WC_Order_Query($args);
            $orders = $query->get_orders();

            if (!empty($orders)) {
                return $orders[0];
            }
        }

        // If external_id is not numeric, try to match it as order number
        if (!empty($external_id)) {
            // Search all orders and compare order numbers
            $args = array(
                'limit' => -1,
                'status' => 'any',
                'orderby' => 'ID',
                'order' => 'DESC',
                'type' => 'shop_order',
                'return' => 'objects',
            );

            $query = new \WC_Order_Query($args);
            $orders = $query->get_orders();

            foreach ($orders as $order) {
                if ($order->get_order_number() == $external_id) {
                    return $order;
                }
            }
        }

        return false;
    }
}
