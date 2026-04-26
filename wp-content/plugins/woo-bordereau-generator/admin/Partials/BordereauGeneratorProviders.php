<?php


/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    WC_Bordereau_Generator
 * @subpackage WC_Bordereau_Generator/admin/partials
 * @author     Boukraa Mohamed <bm2ilabs@gmail.com>
 */

namespace WooBordereauGenerator\Admin\Partials;

use WooBordereauGenerator\Admin\BordereauGeneratorAdmin;
use WooBordereauGenerator\Admin\Shipping\ColivraisonExpress;
use WooBordereauGenerator\Admin\Shipping\ColivraisonExpressSettings;
use WooBordereauGenerator\Admin\Shipping\ConexlogShipping;
use WooBordereauGenerator\Admin\Shipping\ConexlogShippingSettings;
use WooBordereauGenerator\Admin\Shipping\ElogistiaShipping;
use WooBordereauGenerator\Admin\Shipping\ElogistiaShippingSettings;
use WooBordereauGenerator\Admin\Shipping\InternalShipping;
use WooBordereauGenerator\Admin\Shipping\InternalShippingSettings;
use WooBordereauGenerator\Admin\Shipping\LihlihExpress;
use WooBordereauGenerator\Admin\Shipping\LihlihExpressSettings;
use WooBordereauGenerator\Admin\Shipping\MylerzExpress;
use WooBordereauGenerator\Admin\Shipping\MylerzExpressSettings;
use WooBordereauGenerator\Admin\Shipping\NoestShippingSettings;
use WooBordereauGenerator\Admin\Shipping\PicoSolution;
use WooBordereauGenerator\Admin\Shipping\PicoSolutionAPI;
use WooBordereauGenerator\Admin\Shipping\PicoSolutionAPISettings;
use WooBordereauGenerator\Admin\Shipping\PicoSolutionNew;
use WooBordereauGenerator\Admin\Shipping\PicoSolutionSettings;
use WooBordereauGenerator\Admin\Shipping\PicoSolutionSettingsNew;
use WooBordereauGenerator\Admin\Shipping\ThreeMExpress;
use WooBordereauGenerator\Admin\Shipping\ThreeMExpressSettings;
use WooBordereauGenerator\Admin\Shipping\TonColis;
use WooBordereauGenerator\Admin\Shipping\TonColisAPI;
use WooBordereauGenerator\Admin\Shipping\TonColisAPISettings;
use WooBordereauGenerator\Admin\Shipping\TonColisSettings;
use WooBordereauGenerator\Admin\Shipping\UPSShipping;
use WooBordereauGenerator\Admin\Shipping\UPSShippingSettings;
use WooBordereauGenerator\Admin\Shipping\Yalitec;
use WooBordereauGenerator\Admin\Shipping\YalitecSettings;
use WooBordereauGenerator\Admin\Shipping\Mdm;
use WooBordereauGenerator\Admin\Shipping\MdmSettings;
use WooBordereauGenerator\Shippingmethods\MdmFlatRate;
use WooBordereauGenerator\Shippingmethods\MdmLocalPickup;
use WooBordereauGenerator\Admin\Shipping\ZimoExpress;
use WooBordereauGenerator\Admin\Shipping\ZimoExpressSettings;
use WooBordereauGenerator\Admin\Shipping\ZRExpressTwo;
use WooBordereauGenerator\Admin\Shipping\ZRExpressTwoSettings;
use WooBordereauGenerator\Admin\Shipping\NearDelivery;
use WooBordereauGenerator\Admin\Shipping\NearDeliverySettings;
use WooBordereauGenerator\Shippingmethods\ColivraisonFlatRate;
use WooBordereauGenerator\Shippingmethods\ColivraisonLocalPickup;
use WooBordereauGenerator\Shippingmethods\ConexlogFlatRate;
use WooBordereauGenerator\Shippingmethods\ConexlogLocalPickup;
use WooBordereauGenerator\Shippingmethods\DefaultFlatRate;
use WooBordereauGenerator\Shippingmethods\DefaultLocalPickup;
use WooBordereauGenerator\Shippingmethods\EcotrackFlatRate;
use WooBordereauGenerator\Shippingmethods\EcotrackLocalPickup;
use WooBordereauGenerator\Shippingmethods\ElogistiaFlatRate;
use WooBordereauGenerator\Shippingmethods\ElogistiaLocalPickup;
use WooBordereauGenerator\Shippingmethods\InternalShippingFlatRate;
use WooBordereauGenerator\Shippingmethods\InternalShippingLocalPickup;
use WooBordereauGenerator\Shippingmethods\LihlihExpressFlatRate;
use WooBordereauGenerator\Shippingmethods\LihlihExpressLocalPickup;
use WooBordereauGenerator\Shippingmethods\MaystroDeliveryFlatRate;
use WooBordereauGenerator\Shippingmethods\MaystroDeliveryLocalPickup;
use WooBordereauGenerator\Shippingmethods\MylerzExpressFlatRate;
use WooBordereauGenerator\Shippingmethods\MylerzExpressLocalPickup;
use WooBordereauGenerator\Shippingmethods\NordOuestFlatRate;
use WooBordereauGenerator\Shippingmethods\NordOuestLocalPickup;
use WooBordereauGenerator\Shippingmethods\TonColisFlatRate;
use WooBordereauGenerator\Shippingmethods\TonColisLocalPickup;
use WooBordereauGenerator\Shippingmethods\WC3MExpressFlatRate;
use WooBordereauGenerator\Shippingmethods\WC3MExpressLocalPickup;
use WooBordereauGenerator\Shippingmethods\YalidineFlatRate;
use WooBordereauGenerator\Shippingmethods\YalidineLocalPickup;
use WooBordereauGenerator\Shippingmethods\YalitecLocalPickup;
use WooBordereauGenerator\Shippingmethods\ZimoExpressFlatRate;
use WooBordereauGenerator\Shippingmethods\ZimoExpressLocalPickup;
use WooBordereauGenerator\Shippingmethods\ZRExpressFlatRate;
use WooBordereauGenerator\Shippingmethods\ZRExpressLocalPickup;
use WooBordereauGenerator\Shippingmethods\ZRExpressTwoFlatRate;
use WooBordereauGenerator\Shippingmethods\ZRExpressTwoLocalPickup;
use WooBordereauGenerator\Shippingmethods\NearDeliveryLocalPickup;
use WP_REST_Request;
use WC_Order;
use WooBordereauGenerator\Admin\Shipping\MaystroShippingSettings;
use WooBordereauGenerator\Admin\Shipping\EcoTrackShipping;
use WooBordereauGenerator\Admin\Shipping\EcoTrackShippingSettings;
use WooBordereauGenerator\Admin\Shipping\MaystroShipping;
use WooBordereauGenerator\Admin\Shipping\NoestShipping;
use WooBordereauGenerator\Admin\Shipping\YalidineShipping;
use WooBordereauGenerator\Admin\Shipping\YalidineShippingSettings;
use WP_REST_Response;

class BordereauGeneratorProviders
{
    /**
     * Build default action buttons configuration for providers
     * Actions define the buttons shown in the provider card (import, settings, webhook, sync, etc.)
     *
     * @param array $overrides Override specific actions - can be boolean (enable/disable) or array (full config)
     * @return array Array of action configurations
     * @since 2.5.0
     */
    private static function build_default_actions(array $overrides = []): array
    {
        $defaults = [
            'import' => [
                'id' => 'import',
                'label' => __('Prices', 'woo-bordereau-generator'),
                'icon' => 'download',
                'enabled' => true,
                'handler' => 'openImportModal',
                'order' => 10,
            ],
            'settings' => [
                'id' => 'settings',
                'label' => __('Settings', 'woo-bordereau-generator'),
                'icon' => 'cog',
                'enabled' => true,
                'handler' => 'openModal',
                'order' => 20,
            ],
            'webhook' => [
                'id' => 'webhook',
                'label' => __('Webhook', 'woo-bordereau-generator'),
                'icon' => 'link',
                'enabled' => false,
                'handler' => 'handleWebhook',
                'order' => 30,
            ],
            'sync' => [
                'id' => 'sync',
                'label' => __('Sync', 'woo-bordereau-generator'),
                'icon' => 'refresh',
                'enabled' => false,
                'handler' => 'handleSyncProducts',
                'order' => 40,
            ],
            'oauth' => [
                'id' => 'oauth',
                'label' => __('Authorize', 'woo-bordereau-generator'),
                'icon' => 'key',
                'enabled' => false,
                'handler' => 'handleAuthorization',
                'order' => 50,
            ],
        ];

        // Process overrides
        foreach ($overrides as $key => $value) {
            if (is_bool($value)) {
                // Simple enable/disable
                if (isset($defaults[$key])) {
                    $defaults[$key]['enabled'] = $value;
                }
            } elseif (is_array($value)) {
                // Merge with existing or add new action
                if (isset($defaults[$key])) {
                    $defaults[$key] = array_merge($defaults[$key], $value);
                } else {
                    // Custom action - ensure required fields
                    $defaults[$key] = array_merge([
                        'id' => $key,
                        'label' => ucfirst($key),
                        'icon' => 'cog',
                        'enabled' => true,
                        'handler' => 'handle' . ucfirst($key),
                        'order' => 100,
                    ], $value);
                }
            }
        }

        // Filter enabled actions and sort by order
        $actions = array_filter($defaults, fn($action) => $action['enabled']);
        usort($actions, fn($a, $b) => ($a['order'] ?? 99) - ($b['order'] ?? 99));

        return array_values($actions);
    }

    /**
     * Build default import options configuration for providers
     * Import options define the fields shown in the import popup (flat rate, local pickup, etc.)
     *
     * @param array $overrides Override specific options
     * @return array Array of import option configurations
     * @since 2.5.0
     */
    private static function build_default_import_options(array $overrides = []): array
    {
        $defaults = [
            'flat_rate' => [
                'id' => 'flat_rate',
                'enabled' => true,
                'label' => __('Flat Rate Shipping', 'woo-bordereau-generator'),
                'description' => __('Import flat rate shipping prices', 'woo-bordereau-generator'),
                'default' => true,
                'has_label_input' => true,
                'label_placeholder' => __('e.g., Home Delivery', 'woo-bordereau-generator'),
                'order' => 10,
            ],
            'local_pickup' => [
                'id' => 'local_pickup',
                'enabled' => true,
                'label' => __('Local Pickup', 'woo-bordereau-generator'),
                'description' => __('Import local pickup (stopdesk) shipping prices', 'woo-bordereau-generator'),
                'default' => true,
                'has_label_input' => true,
                'label_placeholder' => __('e.g., Pickup from Store', 'woo-bordereau-generator'),
                'order' => 20,
            ],
            'agency_import' => [
                'id' => 'agency_import',
                'enabled' => false,
                'label' => __('Import by Agency', 'woo-bordereau-generator'),
                'description' => __('Create separate shipping zones for each agency', 'woo-bordereau-generator'),
                'default' => false,
                'order' => 30,
            ],
            'economic_rates' => [
                'id' => 'economic_rates',
                'enabled' => false,
                'label' => __('Economic Rates', 'woo-bordereau-generator'),
                'description' => __('Import economic shipping rates', 'woo-bordereau-generator'),
                'default' => false,
                'has_label_input' => true,
                'label_placeholder' => __('e.g., Economic Delivery', 'woo-bordereau-generator'),
                'order' => 40,
            ],
            'flexible_rates' => [
                'id' => 'flexible_rates',
                'enabled' => false,
                'label' => __('Flexible Rates', 'woo-bordereau-generator'),
                'description' => __('Import flexible shipping rates', 'woo-bordereau-generator'),
                'default' => false,
                'has_label_input' => true,
                'label_placeholder' => __('e.g., Flexible Delivery', 'woo-bordereau-generator'),
                'order' => 50,
            ],
            'zr_express_rates' => [
                'id' => 'zr_express_rates',
                'enabled' => false,
                'label' => __('ZR Express Rates', 'woo-bordereau-generator'),
                'description' => __('Import ZR Express shipping rates', 'woo-bordereau-generator'),
                'default' => false,
                'has_label_input' => true,
                'label_placeholder' => __('e.g., ZR Express Delivery', 'woo-bordereau-generator'),
                'order' => 60,
            ],
            'maystro_rates' => [
                'id' => 'maystro_rates',
                'enabled' => false,
                'label' => __('Maystro Rates', 'woo-bordereau-generator'),
                'description' => __('Import Maystro shipping rates', 'woo-bordereau-generator'),
                'default' => false,
                'has_label_input' => true,
                'label_placeholder' => __('e.g., Maystro Delivery', 'woo-bordereau-generator'),
                'order' => 70,
            ],
            'yalidine_rates' => [
                'id' => 'yalidine_rates',
                'enabled' => false,
                'label' => __('Yalidine Rates', 'woo-bordereau-generator'),
                'description' => __('Import Yalidine shipping rates', 'woo-bordereau-generator'),
                'default' => false,
                'has_label_input' => true,
                'label_placeholder' => __('e.g., Yalidine Delivery', 'woo-bordereau-generator'),
                'order' => 80,
            ],
        ];

        // Process overrides
        foreach ($overrides as $key => $value) {
            if (is_bool($value)) {
                // Simple enable/disable
                if (isset($defaults[$key])) {
                    $defaults[$key]['enabled'] = $value;
                }
            } elseif (is_array($value)) {
                // Merge with existing or add new option
                if (isset($defaults[$key])) {
                    $defaults[$key] = array_merge($defaults[$key], $value);
                } else {
                    // Custom option - ensure required fields
                    $defaults[$key] = array_merge([
                        'id' => $key,
                        'enabled' => true,
                        'label' => ucfirst(str_replace('_', ' ', $key)),
                        'description' => '',
                        'default' => false,
                        'order' => 100,
                    ], $value);
                }
            }
        }

        // Filter enabled options and sort by order
        $options = array_filter($defaults, fn($option) => $option['enabled']);
        usort($options, fn($a, $b) => ($a['order'] ?? 99) - ($b['order'] ?? 99));

        // Return as associative array keyed by id
        $result = [];
        foreach ($options as $option) {
            $result[$option['id']] = $option;
        }

        return $result;
    }

    /**
     * @param $type
     * @param null $provider
     * @param null $name
     * @param null $domain
     * @param null $api
     * @param null $login
     * @return array
     */

    public static function get_settings_from_provider($type, $provider = null, $name = null, $domain = null, $api = null, $login = null, $extra = [])
    {

        $algeria_states = [
            [
                'value' => 'DZ-01',
                'name' => 'algeria_state',
                'text' => '01 Adrar أدرار'
            ],
            [
                'value' => 'DZ-02',
                'name' => 'algeria_state',
                'text' => '02 Chlef الشلف'
            ],
            [
                'value' => 'DZ-03',
                'name' => 'algeria_state',
                'text' => '03 Laghouat الأغواط'
            ],
            [
                'value' => 'DZ-04',
                'name' => 'algeria_state',
                'text' => '04 Oum El Bouaghi أم البواقي'
            ],
            [
                'value' => 'DZ-05',
                'name' => 'algeria_state',
                'text' => '05 Batna باتنة'
            ],
            [
                'value' => 'DZ-06',
                'name' => 'algeria_state',
                'text' => '06 Béjaïa بجاية'
            ],
            [
                'value' => 'DZ-07',
                'name' => 'algeria_state',
                'text' => '07 Biskra بسكرة'
            ],
            [
                'value' => 'DZ-08',
                'name' => 'algeria_state',
                'text' => '08 Béchar بشار'
            ],
            [
                'value' => 'DZ-09',
                'name' => 'algeria_state',
                'text' => '09 Blida البليدة'
            ],
            [
                'value' => 'DZ-10',
                'name' => 'algeria_state',
                'text' => '10 Bouïra البويرة'
            ],
            [
                'value' => 'DZ-11',
                'name' => 'algeria_state',
                'text' => '11 Tamanrasset تمنراست'
            ],
            [
                'value' => 'DZ-12',
                'name' => 'algeria_state',
                'text' => '12 Tébessa تبسة'
            ],
            [
                'value' => 'DZ-13',
                'name' => 'algeria_state',
                'text' => '13 Tlemcen تلمسان'
            ],
            [
                'value' => 'DZ-14',
                'name' => 'algeria_state',
                'text' => '14 Tiaret تيارت'
            ],
            [
                'value' => 'DZ-15',
                'name' => 'algeria_state',
                'text' => '15 Tizi Ouzou تيزي وزو'
            ],
            [
                'value' => 'DZ-16',
                'name' => 'algeria_state',
                'text' => '16 Alger الجزائر'
            ],
            [
                'value' => 'DZ-17',
                'name' => 'algeria_state',
                'text' => '17 Djelfa الجلفة'
            ],
            [
                'value' => 'DZ-18',
                'name' => 'algeria_state',
                'text' => '18 Jijel جيجل'
            ],
            [
                'value' => 'DZ-19',
                'name' => 'algeria_state',
                'text' => '19 Sétif سطيف'
            ],
            [
                'value' => 'DZ-20',
                'name' => 'algeria_state',
                'text' => '20 Saïda سعيدة'
            ],
            [
                'value' => 'DZ-21',
                'name' => 'algeria_state',
                'text' => '21 Skikda سكيكدة'
            ],
            [
                'value' => 'DZ-22',
                'name' => 'algeria_state',
                'text' => '22 Sidi Bel Abbès سيدي بلعباس'
            ],
            [
                'value' => 'DZ-23',
                'name' => 'algeria_state',
                'text' => '23 Annaba عنابة'
            ],
            [
                'value' => 'DZ-24',
                'name' => 'algeria_state',
                'text' => '24 Guelma قالمة'
            ],
            [
                'value' => 'DZ-25',
                'name' => 'algeria_state',
                'text' => '25 Constantine قسنطينة'
            ],
            [
                'value' => 'DZ-26',
                'name' => 'algeria_state',
                'text' => '26 Médéa المدية'
            ],
            [
                'value' => 'DZ-27',
                'name' => 'algeria_state',
                'text' => '27 Mostaganem مستغانم'
            ],
            [
                'value' => 'DZ-28',
                'name' => 'algeria_state',
                'text' => '28 M\'Sila المسيلة'
            ],
            [
                'value' => 'DZ-29',
                'name' => 'algeria_state',
                'text' => '29 Mascara معسكر'
            ],
            [
                'value' => 'DZ-30',
                'name' => 'algeria_state',
                'text' => '30 Ouargla ورقلة'
            ],
            [
                'value' => 'DZ-31',
                'name' => 'algeria_state',
                'text' => '31 Oran وهران'
            ],
            [
                'value' => 'DZ-32',
                'name' => 'algeria_state',
                'text' => '32 El Bayadh البيض'
            ],
            [
                'value' => 'DZ-33',
                'name' => 'algeria_state',
                'text' => '33 Illizi اليزي'
            ],
            [
                'value' => 'DZ-34',
                'name' => 'algeria_state',
                'text' => '34 Bordj Bou Arréridj برج بوعريريج'
            ],
            [
                'value' => 'DZ-35',
                'name' => 'algeria_state',
                'text' => '35 Boumerdès بومرداس'
            ],
            [
                'value' => 'DZ-36',
                'name' => 'algeria_state',
                'text' => '36 El Tarf الطارف'
            ],
            [
                'value' => 'DZ-37',
                'name' => 'algeria_state',
                'text' => '37 Tindouf تندوف'
            ],
            [
                'value' => 'DZ-38',
                'name' => 'algeria_state',
                'text' => '38 Tissemsilt تسمسيلت'
            ],
            [
                'value' => 'DZ-39',
                'name' => 'algeria_state',
                'text' => '39 El Oued الوادي'
            ],
            [
                'value' => 'DZ-40',
                'name' => 'algeria_state',
                'text' => '40 Khenchela خنشلة'
            ],
            [
                'value' => 'DZ-41',
                'name' => 'algeria_state',
                'text' => '41 Souk Ahras سوق أهراس'
            ],
            [
                'value' => 'DZ-42',
                'name' => 'algeria_state',
                'text' => '42 Tipaza تيبازة'
            ],
            [
                'value' => 'DZ-43',
                'name' => 'algeria_state',
                'text' => '43 Mila ميلة'
            ],
            [
                'value' => 'DZ-44',
                'name' => 'algeria_state',
                'text' => '44 Aïn Defla عين الدفلى'
            ],
            [
                'value' => 'DZ-45',
                'name' => 'algeria_state',
                'text' => '45 Naâma النعامة'
            ],
            [
                'value' => 'DZ-46',
                'name' => 'algeria_state',
                'text' => '46 Aïn Témouchent عين تموشنت'
            ],
            [
                'value' => 'DZ-47',
                'name' => 'algeria_state',
                'text' => '47 Ghardaïa غرداية'
            ],
            [
                'value' => 'DZ-48',
                'name' => 'algeria_state',
                'text' => '48 Relizane غليزان'
            ],
            [
                'value' => 'DZ-49',
                'name' => 'algeria_state',
                'text' => '49 Timimoun تيميمون'
            ],
            [
                'value' => 'DZ-50',
                'name' => 'algeria_state',
                'text' => '50 Bordj Baji Mokhtar برج باجي مختار'
            ],
            [
                'value' => 'DZ-51',
                'name' => 'algeria_state',
                'text' => '51 Ouled Djellal أولاد جلال'
            ],
            [
                'value' => 'DZ-52',
                'name' => 'algeria_state',
                'text' => '52 Béni Abbès بني عباس'
            ],
            [
                'value' => 'DZ-53',
                'name' => 'algeria_state',
                'text' => '53 In Salah عين صالح'
            ],
            [
                'value' => 'DZ-54',
                'name' => 'algeria_state',
                'text' => '54 In Guezzam عين قزّام'
            ],
            [
                'value' => 'DZ-55',
                'name' => 'algeria_state',
                'text' => '55 Touggourt تقرت'
            ],
            [
                'value' => 'DZ-56',
                'name' => 'algeria_state',
                'text' => '56 Djanet جانت'
            ],
            [
                'value' => 'DZ-57',
                'name' => 'algeria_state',
                'text' => '57 El M\'Ghair المغير'
            ],
            [
                'value' => 'DZ-58',
                'name' => 'algeria_state',
                'text' => '58 El Menia المنيعة'
            ]
        ];

        $store_state = WC()->countries->get_base_state();

        if (empty($store_state)) {
            $algeria_states[0]['selected'] = true;
        } else {
            foreach ($algeria_states as &$state) {
                if ($state['value'] === $store_state) {
                    $state['selected'] = true;
                    break; // Exit the loop once we've found the matching state
                }
            }
        }

        // If you need to use a variable for the 'name' field:
        array_walk($algeria_states, function(&$item) use ($provider) {
            $item['value'] = (int) str_replace('DZ-','', $item['value']);
            $item['name'] = $provider . '_wilaya_from';
        });

        switch ($type) {
            case 'procolis':
                return [
                    'url' => $domain,
                    'api_url' => $api,
                    'login_url' => $login,
                    'type' => 'custom',
                    'slug' => $provider,
                    'image' => plugin_dir_url(dirname(__FILE__)) . '/img/delivery/' . $provider . '.png',
                    'name' => __($name, 'woo-bordereau-generator'),
                    'class' => PicoSolution::class,
                    'setting_class' => PicoSolutionSettings::class,
                    'flat_rate_class' => ZRExpressFlatRate::class,
                    'local_pickup_class' => ZRExpressLocalPickup::class,
                    // Backend-driven UI configuration
                    'actions' => self::build_default_actions([
                        'import' => true,
                        'settings' => true,
                        'webhook' => false,
                        'sync' => false,
                    ]),
                    'import_options' => self::build_default_import_options([
                        'flat_rate' => true,
                        'local_pickup' => true,
                        'agency_import' => false,
                    ]),
                    'fields' => [
                        'username' => [
                            'secret' => true,
                            'name' => __('Username', 'woo-bordereau-generator'),
                            'value' => $provider . '_username',
                            'desc' => __('Votre Nom d\'utilisateur pour acceder au platform ', 'woo-bordereau-generator') . $name
                        ],
                        'password' => [
                            'name' => __('Password', 'woo-bordereau-generator'),
                            'value' => $provider . '_password',
                            'secret' => true,
                            'desc' => __('Votre Mot de pass pour acceder au platform ', 'woo-bordereau-generator') . $name
                        ],
                        'total' => [
                            'type' => 'radio-group',
                            'value' => $provider . '_total',
                            'name' => __('Comment calculer le cout total ?', 'woo-bordereau-generator'),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'with-shipping',
                                    'name' => $provider . '_total',
                                    'checked' => true,
                                    'text' => __('Avec Livraison', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'value' => 'without-shipping',
                                    'name' => $provider . '_total',
                                    'text' => __('Sans Livraison', 'woo-bordereau-generator')
                                ]
                            ]
                        ]
                    ]
                ];
            case '3m':
                return [
                    'url' => $domain,
                    'api_url' => $api,
                    'login_url' => $login,
                    'type' => 'custom',
                    'slug' => $provider,
                    'image' => plugin_dir_url(dirname(__FILE__)) . '/img/delivery/' . $provider . '.png',
                    'name' => __($name, 'woo-bordereau-generator'),
                    'class' => ThreeMExpress::class,
                    'setting_class' => ThreeMExpressSettings::class,
                    'flat_rate_class' => WC3MExpressFlatRate::class,
                    'local_pickup_class' => WC3MExpressLocalPickup::class,
                    // Backend-driven UI configuration
                    'actions' => self::build_default_actions([
                        'import' => true,
                        'settings' => true,
                        'webhook' => false,
                        'sync' => false,
                    ]),
                    'import_options' => self::build_default_import_options([
                        'flat_rate' => true,
                        'local_pickup' => true,
                        'agency_import' => false,
                    ]),
                    'fields' => [
                        'username' => [
                            'secret' => true,
                            'name' => __('Username', 'woo-bordereau-generator'),
                            'value' => $provider . '_username',
                            'desc' => __('Votre Nom d\'utilisateur pour acceder au platform ', 'woo-bordereau-generator') . $name
                        ],
                        'password' => [
                            'name' => __('Password', 'woo-bordereau-generator'),
                            'value' => $provider . '_password',
                            'secret' => true,
                            'desc' => __('Votre Mot de pass pour acceder au platform ', 'woo-bordereau-generator') . $name
                        ],
                        'total' => [
                            'type' => 'radio-group',
                            'value' => $provider . '_total',
                            'name' => __('Comment calculer le cout total ?', 'woo-bordereau-generator'),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'with-shipping',
                                    'name' => $provider . '_total',
                                    'checked' => true,
                                    'text' => __('Avec Livraison', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'value' => 'without-shipping',
                                    'name' => $provider . '_total',
                                    'text' => __('Sans Livraison', 'woo-bordereau-generator')
                                ]
                            ]
                        ]
                    ]
                ];
            case 'mylerz':
                return [
                    'url' => $domain,
                    'api_url' => $api,
                    'login_url' => $login,
                    'type' => 'custom',
                    'slug' => $provider,
                    'image' => plugin_dir_url(dirname(__FILE__)) . '/img/delivery/' . $provider . '.png',
                    'name' => __($name, 'woo-bordereau-generator'),
                    'class' => MylerzExpress::class,
                    'setting_class' => MylerzExpressSettings::class,
                    'flat_rate_class' => MylerzExpressFlatRate::class,
                    'local_pickup_class' => MylerzExpressLocalPickup::class,
                    // Backend-driven UI configuration
                    'actions' => self::build_default_actions([
                        'import' => false, // mylerz has import disabled via options
                        'settings' => true,
                        'webhook' => false,
                        'sync' => false,
                    ]),
                    'import_options' => self::build_default_import_options([
                        'flat_rate' => true,
                        'local_pickup' => true,
                        'agency_import' => false,
                    ]),
                    'fields' => [
                        'username' => [
                            'secret' => true,
                            'name' => __('Username', 'woo-bordereau-generator'),
                            'value' => $provider . '_username',
                            'desc' => __('Votre Nom d\'utilisateur pour acceder au platform ', 'woo-bordereau-generator') . $name
                        ],
                        'password' => [
                            'name' => __('Password', 'woo-bordereau-generator'),
                            'value' => $provider . '_password',
                            'secret' => true,
                            'desc' => __('Votre Mot de pass pour acceder au platform ', 'woo-bordereau-generator') . $name
                        ],
                        'total' => [
                            'type' => 'radio-group',
                            'value' => $provider . '_total',
                            'name' => __('Comment calculer le cout total ?', 'woo-bordereau-generator'),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'with-shipping',
                                    'name' => $provider . '_total',
                                    'checked' => true,
                                    'text' => __('Avec Livraison', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'value' => 'without-shipping',
                                    'name' => $provider . '_total',
                                    'text' => __('Sans Livraison', 'woo-bordereau-generator')
                                ]
                            ]
                        ]
                    ],

                    'options' => [
                        'import' => false
                    ]
                ];
            case 'procolis_api':
                return [
                    'url' => $domain,
                    'api_url' => $api,
                    'type' => 'custom',
                    'slug' => $provider,
                    'image' => plugin_dir_url(dirname(__FILE__)) . '/img/delivery/' . $provider . '.png',
                    'name' => __($name, 'woo-bordereau-generator'),
                    'class' => PicoSolutionAPI::class,
                    'setting_class' => PicoSolutionAPISettings::class,
                    'flat_rate_class' => ZRExpressFlatRate::class,
                    'local_pickup_class' => ZRExpressLocalPickup::class,
                    // Backend-driven UI configuration
                    'actions' => self::build_default_actions([
                        'import' => true,
                        'settings' => true,
                        'webhook' => false,
                        'sync' => false,
                    ]),
                    'import_options' => self::build_default_import_options([
                        'flat_rate' => true,
                        'local_pickup' => true,
                        'agency_import' => false,
                    ]),
                    'fields' => [
                        'token' => [
                            'secret' => true,
                            'name' => __('API Token', 'woo-bordereau-generator'),
                            'value' => $provider . '_token',
                            'desc' => __('Votre API Key pour acceder au platform ', 'woo-bordereau-generator') . $name
                        ],
                        'key' => [
                            'name' => __('API Clé', 'woo-bordereau-generator'),
                            'value' => $provider . '_key',
                            'secret' => true,
                            'desc' => __('Votre API Key pour acceder au platform ', 'woo-bordereau-generator') . $name
                        ],

                        'total' => [
                            'type' => 'radio-group',
                            'value' => $provider . '_total',
                            'name' => __('Comment calculer le cout total ?', 'woo-bordereau-generator'),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'with-shipping',
                                    'name' => $provider . '_total',
                                    'checked' => true,
                                    'text' => __('Avec Livraison', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'value' => 'without-shipping',
                                    'name' => $provider . '_total',
                                    'text' => __('Sans Livraison', 'woo-bordereau-generator')
                                ]
                            ]
                        ]
                    ]
                ];
            case 'toncolis_api':
                return [
                    'url' => $domain,
                    'api_url' => $api,
                    'type' => 'custom',
                    'slug' => $provider,
                    'image' => plugin_dir_url(dirname(__FILE__)) . '/img/delivery/' . $provider . '.png',
                    'name' => __($name, 'woo-bordereau-generator'),
                    'class' => TonColisAPI::class,
                    'setting_class' => TonColisAPISettings::class,
                    'flat_rate_class' => TonColisFlatRate::class,
                    'local_pickup_class' => TonColisLocalPickup::class,
                    // Backend-driven UI configuration
                    'actions' => self::build_default_actions([
                        'import' => true,
                        'settings' => true,
                        'webhook' => true,
                        'sync' => false,
                    ]),
                    'import_options' => self::build_default_import_options([
                        'flat_rate' => true,
                        'local_pickup' => true,
                        'agency_import' => true,
                    ]),
                    'fields' => [
                        'key' => [
                            'name' => __('API Clé', 'woo-bordereau-generator'),
                            'value' => $provider . '_key',
                            'secret' => true,
                            'desc' => __('Votre API Key pour acceder au platform ', 'woo-bordereau-generator') . $name
                        ],
                        'token' => [
                            'secret' => true,
                            'name' => __('API Token', 'woo-bordereau-generator'),
                            'value' => $provider . '_token',
                            'desc' => __('Votre API Key pour acceder au platform ', 'woo-bordereau-generator') . $name
                        ],
                        'webhook_token' => [
                            'name' => __('Webhook Token', 'woo-bordereau-generator'),
                            'value' => $provider . '_webhook_token',
                            'secret' => true,
                            'desc' => sprintf(__('Votre Webhook Token de %s pour la vérification des signatures (optionnel mais recommandé). URL du Webhook: %s', 'woo-bordereau-generator'), $name, rest_url('woo-bordereau/v1/webhook/' . $provider))
                        ],
                        'total' => [
                            'type' => 'radio-group',
                            'value' => $provider . '_total',
                            'name' => __('Comment calculer le cout total ?', 'woo-bordereau-generator'),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'with-shipping',
                                    'name' => $provider . '_total',
                                    'checked' => true,
                                    'text' => __('Avec Livraison', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'value' => 'without-shipping',
                                    'name' => $provider . '_total',
                                    'text' => __('Sans Livraison', 'woo-bordereau-generator')
                                ]
                            ]
                        ],
                        'center' => [
                            'type' => 'radio-group',
                            'value' => $provider . '_show_center',
                            'name' => __('Afficher les bureaux (Stop-desk) ?', 'woo-bordereau-generator'),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'show_centers',
                                    'name' =>  $provider . '_show_center',
                                    'checked' => true,
                                    'text' => __('Afficher les bureaux', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'value' => 'hide_centers',
                                    'name' =>  $provider . '_show_center',
                                    'text' => __('Masquer les bureaux', 'woo-bordereau-generator')
                                ]
                            ]
                        ]
                    ]
                ];
				case 'toncolis':
                return [
                    'url' => $domain,
                    'api_url' => $api,
                    'type' => 'custom',
                    'slug' => $provider,
                    'image' => plugin_dir_url(dirname(__FILE__)) . '/img/delivery/' . $provider . '.png',
                    'name' => __($name, 'woo-bordereau-generator'),
                    'class' => TonColis::class,
                    'setting_class' => TonColisSettings::class,
                    'flat_rate_class' => TonColisFlatRate::class,
                    'local_pickup_class' => TonColisLocalPickup::class,
                    // Backend-driven UI configuration
                    'actions' => self::build_default_actions([
                        'import' => true,
                        'settings' => true,
                        'webhook' => false,
                        'sync' => false,
                    ]),
                    'import_options' => self::build_default_import_options([
                        'flat_rate' => true,
                        'local_pickup' => true,
                        'agency_import' => false,
                    ]),
                    'fields' => [
 	                    'username' => [
		                    'secret' => true,
		                    'name' => __('Username', 'woo-bordereau-generator'),
		                    'value' => $provider . '_username',
		                    'desc' => __('Votre Nom d\'utilisateur pour acceder au platform ', 'woo-bordereau-generator') . $name
	                    ],
	                    'password' => [
		                    'name' => __('Password', 'woo-bordereau-generator'),
		                    'value' => $provider . '_password',
		                    'secret' => true,
		                    'desc' => __('Votre Mot de pass pour acceder au platform ', 'woo-bordereau-generator') . $name
	                    ],
                        'total' => [
                            'type' => 'radio-group',
                            'value' => $provider . '_total',
                            'name' => __('Comment calculer le cout total ?', 'woo-bordereau-generator'),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'with-shipping',
                                    'name' => $provider . '_total',
                                    'checked' => true,
                                    'text' => __('Avec Livraison', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'value' => 'without-shipping',
                                    'name' => $provider . '_total',
                                    'text' => __('Sans Livraison', 'woo-bordereau-generator')
                                ]
                            ]
                        ]
                    ]
                ];
            case 'lihlihexpress':
                $algeria_states = [
                    [
                        'value' => 'DZ-02',
                        'name' => 'algeria_state',
                        'text' => 'CHLEF'
                    ],
                    [
                        'value' => 'DZ-03',
                        'name' => 'algeria_state',
                        'text' => 'LAGHOUAT'
                    ],
                    [
                        'value' => 'DZ-04',
                        'name' => 'algeria_state',
                        'text' => 'OUM EL BOUAGHI'
                    ],
                    [
                        'value' => 'DZ-05',
                        'name' => 'algeria_state',
                        'text' => 'BATNA'
                    ],
                    [
                        'value' => 'DZ-06',
                        'name' => 'algeria_state',
                        'text' => 'BEJAIA'
                    ],
                    [
                        'value' => 'DZ-07',
                        'name' => 'algeria_state',
                        'text' => 'BISKRA'
                    ],
                    [
                        'value' => 'DZ-09',
                        'name' => 'algeria_state',
                        'text' => 'BLIDA'
                    ],
                    [
                        'value' => 'DZ-10',
                        'name' => 'algeria_state',
                        'text' => 'BOUIRA'
                    ],
                    [
                        'value' => 'DZ-12',
                        'name' => 'algeria_state',
                        'text' => 'TEBESSA'
                    ],
                    [
                        'value' => 'DZ-13',
                        'name' => 'algeria_state',
                        'text' => 'TLEMCEN'
                    ],
                    [
                        'value' => 'DZ-14',
                        'name' => 'algeria_state',
                        'text' => 'TIARET'
                    ],
                    [
                        'value' => 'DZ-15',
                        'name' => 'algeria_state',
                        'text' => 'TIZI OUZOU'
                    ],
                    [
                        'value' => 'DZ-16',
                        'name' => 'algeria_state',
                        'text' => 'ALGER'
                    ],
                    [
                        'value' => 'DZ-17',
                        'name' => 'algeria_state',
                        'text' => 'DJELFA'
                    ],
                    [
                        'value' => 'DZ-18',
                        'name' => 'algeria_state',
                        'text' => 'JIJEL'
                    ],
                    [
                        'value' => 'DZ-19',
                        'name' => 'algeria_state',
                        'text' => 'SETIF'
                    ],
                    [
                        'value' => 'DZ-20',
                        'name' => 'algeria_state',
                        'text' => 'SAIDA'
                    ],
                    [
                        'value' => 'DZ-21',
                        'name' => 'algeria_state',
                        'text' => 'SKIKDA'
                    ],
                    [
                        'value' => 'DZ-22',
                        'name' => 'algeria_state',
                        'text' => 'SIDI BEL ABBES'
                    ],
                    [
                        'value' => 'DZ-23',
                        'name' => 'algeria_state',
                        'text' => 'ANNABA'
                    ],
                    [
                        'value' => 'DZ-24',
                        'name' => 'algeria_state',
                        'text' => 'GUELMA'
                    ],
                    [
                        'value' => 'DZ-25',
                        'name' => 'algeria_state',
                        'text' => 'CONSTANTINE'
                    ],
                    [
                        'value' => 'DZ-26',
                        'name' => 'algeria_state',
                        'text' => 'MEDEA'
                    ],
                    [
                        'value' => 'DZ-27',
                        'name' => 'algeria_state',
                        'text' => 'MOSTAGANEM'
                    ],
                    [
                        'value' => 'DZ-28',
                        'name' => 'algeria_state',
                        'text' => "M'SILA"
                    ],
                    [
                        'value' => 'DZ-29',
                        'name' => 'algeria_state',
                        'text' => 'MASCARA'
                    ],
                    [
                        'value' => 'DZ-30',
                        'name' => 'algeria_state',
                        'text' => 'OUARGLA'
                    ],
                    [
                        'value' => 'DZ-31',
                        'name' => 'algeria_state',
                        'text' => 'ORAN'
                    ],
                    [
                        'value' => 'DZ-32',
                        'name' => 'algeria_state',
                        'text' => 'EL BAYADH'
                    ],
                    [
                        'value' => 'DZ-34',
                        'name' => 'algeria_state',
                        'text' => 'BORDJ BOU ARRERIDJ'
                    ],
                    [
                        'value' => 'DZ-35',
                        'name' => 'algeria_state',
                        'text' => 'BOUMERDES'
                    ],
                    [
                        'value' => 'DZ-36',
                        'name' => 'algeria_state',
                        'text' => 'EL-TARF'
                    ],
                    [
                        'value' => 'DZ-38',
                        'name' => 'algeria_state',
                        'text' => 'TISSEMSILT'
                    ],
                    [
                        'value' => 'DZ-39',
                        'name' => 'algeria_state',
                        'text' => 'EL-OUED'
                    ],
                    [
                        'value' => 'DZ-40',
                        'name' => 'algeria_state',
                        'text' => 'KHENCHELA'
                    ],
                    [
                        'value' => 'DZ-41',
                        'name' => 'algeria_state',
                        'text' => 'SOUK AHRAS'
                    ],
                    [
                        'value' => 'DZ-42',
                        'name' => 'algeria_state',
                        'text' => 'TIPAZA'
                    ],
                    [
                        'value' => 'DZ-43',
                        'name' => 'algeria_state',
                        'text' => 'MILA'
                    ],
                    [
                        'value' => 'DZ-44',
                        'name' => 'algeria_state',
                        'text' => 'AIN-DEFLA'
                    ],
                    [
                        'value' => 'DZ-45',
                        'name' => 'algeria_state',
                        'text' => 'NAAMA'
                    ],
                    [
                        'value' => 'DZ-46',
                        'name' => 'algeria_state',
                        'text' => 'AIN-TEMOUCHENT'
                    ],
                    [
                        'value' => 'DZ-47',
                        'name' => 'algeria_state',
                        'text' => 'GHARDAIA'
                    ],
                    [
                        'value' => 'DZ-48',
                        'name' => 'algeria_state',
                        'text' => 'RELIZANE'
                    ],
                    [
                        'value' => 'DZ-51',
                        'name' => 'algeria_state',
                        'text' => 'OULED DJELLAL'
                    ],
                    [
                        'value' => 'DZ-55',
                        'name' => 'algeria_state',
                        'text' => 'TOUGGOURT'
                    ]
                ];
                return [
                    'url' => $domain,
                    'api_url' => $api,
                    'type' => 'custom',
                    'slug' => $provider,
                    'image' => plugin_dir_url(dirname(__FILE__)) . '/img/delivery/' . $provider . '.png',
                    'name' => __($name, 'woo-bordereau-generator'),
                    'class' => LihlihExpress::class,
                    'setting_class' => LihlihExpressSettings::class,
                    'flat_rate_class' => LihlihExpressFlatRate::class,
                    'local_pickup_class' => LihlihExpressLocalPickup::class,
                    // Backend-driven UI configuration
                    'actions' => self::build_default_actions([
                        'import' => true,
                        'settings' => true,
                        'webhook' => false,
                        'sync' => false,
                    ]),
                    'import_options' => self::build_default_import_options([
                        'flat_rate' => true,
                        'local_pickup' => true,
                        'agency_import' => false,
                    ]),
                    'fields' => [
                        'token' => [
                            'secret' => true,
                            'name' => __('API Token', 'woo-bordereau-generator'),
                            'value' =>  $provider .'_token',
                            'desc' =>__('Votre API Key pour acceder au platform ', 'woo-bordereau-generator') . $name
                        ],
                        'total' => [
                            'type' => 'radio-group',
                            'value' => $provider .'_total',
                            'name' => __('Comment calculer le cout total ?', 'woo-bordereau-generator'),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'with-shipping',
                                    'name' => 'lihlihexpress_total',
                                    'checked' => true,
                                    'text' => __('Avec Livraison', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'value' => 'without-shipping',
                                    'name' =>  'lihlihexpress_total',
                                    'text' => __('Sans Livraison', 'woo-bordereau-generator')
                                ]
                            ]
                        ],
                        'pack' => [
                            'name' => __('Pack', 'woo-bordereau-generator'),
                            'value' => $provider .'_pack',
                            'type' => 'select',
                            'css' => '',
                            'desc' => sprintf(__("Votre Pack", 'woo-bordereau-generator'), $name),
                            'choices' => [
                                [
                                    'value' => '',
                                    'name' => 'package_type',
                                    'text' => __('Choisissez un pack', 'woo-bordereau-generator')
                                ],
                                [
                                    'value' => 'START',
                                    'name' => 'package_type',
                                    'text' => 'START'
                                ],
                                [
                                    'value' => 'PACK - 1KG',
                                    'name' => 'package_type',
                                    'text' => 'PACK - 1KG'
                                ],
                                [
                                    'value' => 'EXCEPTIONNEL',
                                    'name' => 'package_type',
                                    'text' => 'EXCEPTIONNEL'
                                ],
                                [
                                    'value' => 'Pack Classique',
                                    'name' => 'package_type',
                                    'text' => 'Pack Classique'
                                ],
                                [
                                    'value' => 'MEUBLE',
                                    'name' => 'package_type',
                                    'text' => 'MEUBLE'
                                ],
                                [
                                    'value' => 'DECORATION',
                                    'name' => 'package_type',
                                    'text' => 'DECORATION'
                                ],
                                [
                                    'value' => 'EXCEPTIONNEL PRO',
                                    'name' => 'package_type',
                                    'text' => 'EXCEPTIONNEL PRO'
                                ],
                                [
                                    'value' => 'TRANSPORT',
                                    'name' => 'package_type',
                                    'text' => 'TRANSPORT'
                                ],
                                [
                                    'value' => 'Str',
                                    'name' => 'package_type',
                                    'text' => 'Str'
                                ],
                                [
                                    'value' => 'MEDIAZ',
                                    'name' => 'package_type',
                                    'text' => 'MEDIAZ'
                                ],
                                [
                                    'value' => 'Pack 15 KG',
                                    'name' => 'package_type',
                                    'text' => 'Pack 15 KG'
                                ],
                                [
                                    'value' => 'Pack  100',
                                    'name' => 'package_type',
                                    'text' => 'Pack  100'
                                ],
                                [
                                    'value' => 'Pack Huile',
                                    'name' => 'package_type',
                                    'text' => 'Pack Huile'
                                ],
                                [
                                    'value' => 'BBON',
                                    'name' => 'package_type',
                                    'text' => 'BBON'
                                ]
                            ]
                        ],
	                        'wilaya_from' => [
		                        'name' => __('Wilaya From', 'woo-bordereau-generator'),
                            'value' => $provider . '_wilaya_from',
                            'type' => 'select',
                            'css' => '',
                            'desc' => sprintf(__("Votre Wilaya", 'woo-bordereau-generator'), $name),
                            'choices' => $algeria_states
                        ],
                    ]
                ];
            case 'procolis_new':
                return [
                    'url' => $domain,
                    'api_url' => $api,
                    'login_url' => $login,
                    'extra' => $extra,
                    'type' => 'custom',
                    'slug' => $provider,
                    'image' => plugin_dir_url(dirname(__FILE__)) . '/img/delivery/' . $provider . '.png',
                    'name' => __($name, 'woo-bordereau-generator'),
                    'class' => PicoSolutionNew::class,
                    'setting_class' => PicoSolutionSettingsNew::class,
                    'flat_rate_class' => ZRExpressFlatRate::class,
                    'local_pickup_class' => ZRExpressLocalPickup::class,
                    // Backend-driven UI configuration
                    'actions' => self::build_default_actions([
                        'import' => true,
                        'settings' => true,
                        'webhook' => false,
                        'sync' => false,
                    ]),
                    'import_options' => self::build_default_import_options([
                        'flat_rate' => true,
                        'local_pickup' => true,
                        'agency_import' => false,
                    ]),
                    'fields' => [
                        'username' => [
                            'secret' => true,
                            'name' => __('Username', 'woo-bordereau-generator'),
                            'value' => $provider . '_username',
                            'desc' => __('Votre Nom d\'utilisateur pour acceder au platform ', 'woo-bordereau-generator') . $name
                        ],
                        'password' => [
                            'name' => __('Password', 'woo-bordereau-generator'),
                            'value' => $provider . '_password',
                            'secret' => true,
                            'desc' => __('Votre Mot de pass pour acceder au platform ', 'woo-bordereau-generator') . $name
                        ],

                        'total' => [
                            'type' => 'radio-group',
                            'value' => $provider . '_total',
                            'name' => __('Comment calculer le cout total ?', 'woo-bordereau-generator'),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'with-shipping',
                                    'name' => $provider . '_total',
                                    'checked' => true,
                                    'text' => __('Avec Livraison', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'value' => 'without-shipping',
                                    'name' => $provider . '_total',
                                    'text' => __('Sans Livraison', 'woo-bordereau-generator')
                                ]
                            ]
                        ],
                        'overweight' => [
                            'type' => 'radio-group',
                            'value' => 'recalculate_zr_express_overweight',
                            'name' => sprintf(
                                __(
                                    "Voulez-vous activer le calcule le Surpoids (+5KG) pour %s ?",
                                    'Overweight calculation option for a specific provider',
                                    'woo-bordereau-generator'
                                ),
                                $name
                            ),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'recalculate-with-overweight',
                                    'name' => 'recalculate_zr_express_overweight',
                                    'text' => __('Oui', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'checked' => true,
                                    'value' => 'recalculate-without-overweight',
                                    'name' => 'recalculate_zr_express_overweight',
                                    'text' => __('Non', 'woo-bordereau-generator')
                                ]
                            ]
                        ],
                        'overweight_checkout' => [
                            'type' => 'radio-group',
                            'value' => 'recalculate_zr_express_overweight_checkout',
                            'name' => sprintf(__("Voulez-vous activer le calcule le Surpoids (+5KG) pour %s  au formulaire de checkout?", 'woo-bordereau-generator'), $name),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'recalculate-with-overweight',
                                    'name' => 'recalculate_zr_express_overweight_checkout',
                                    'text' => __('Oui', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'checked' => true,
                                    'value' => 'recalculate-without-overweight',
                                    'name' => 'recalculate_zr_express_overweight_checkout',
                                    'text' => __('Non', 'woo-bordereau-generator')
                                ]
                            ]
                        ],
                    ]
                ];
            case 'nordouest':
                return [
                    'url' => 'https://app.noest-dz.com/',
                    'api_url' => 'https://app.noest-dz.com/api/public',
                    'type' => 'ecotrack',
                    'slug' => $provider,
                    'image' => plugin_dir_url(dirname(__FILE__)) . '/img/delivery/nord_ouest.png',
                    'name' => $name,
                    'class' => NoestShipping::class,
                    'setting_class' => NoestShippingSettings::class,
                    'flat_rate_class' => NordOuestFlatRate::class,
                    'local_pickup_class' => NordOuestLocalPickup::class,
                    // Backend-driven UI configuration
                    'actions' => self::build_default_actions([
                        'import' => true,
                        'settings' => true,
                        'webhook' => true,
                        'sync' => false,
                    ]),
                    'import_options' => self::build_default_import_options([
                        'flat_rate' => true,
                        'local_pickup' => true,
                        'agency_import' => true,
                    ]),
                    'fields' => [
                        'api_token' => [
                            'name' => __('API Token', 'woo-bordereau-generator'),
                            'secret' => true,
                            'value' => sprintf('%s_express_api_token', $provider),
                            'desc' => __('Votre API Token de Nord et ouest express voir dans votre espace à Configuration API', 'woo-bordereau-generator')
                        ],
                        'guid' => [
                            'secret' => true,
                            'name' => 'Guid Token',
                            'value' => sprintf('%s_express_user_guid', $provider),
                            'desc' => __('Votre Guid Token de Nord et ouest express voir dans votre espace à Configuration API', 'woo-bordereau-generator')
                        ],
                        'total' => [
                            'type' => 'radio-group',
                            'value' => 'nord_ouest_total',
                            'name' => __('Comment calculer le cout total ?', 'woo-bordereau-generator'),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'with-shipping',
                                    'name' => 'nord_ouest_total',
                                    'checked' => true,
                                    'text' => __('Avec Livraison', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'value' => 'without-shipping',
                                    'name' => 'nord_ouest_total',
                                    'text' => __('Sans Livraison', 'woo-bordereau-generator')
                                ]
                            ]
                        ],
                        'center' => [
                            'type' => 'radio-group',
                            'value' => 'nord_ouest_show_center',
                            'name' => __('Vous voulez afficher les centers?', 'woo-bordereau-generator'),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'show_centers',
                                    'name' =>  'nord_ouest_show_center',
                                    'text' => __('Oui', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'value' => 'hide_centers',
                                    'name' =>  'nord_ouest_show_center',
                                    'checked' => true,
                                    'text' => __('Non', 'woo-bordereau-generator')
                                ]
                            ]
                        ],
                        'first_center' => [
                            'type' => 'radio-group',
                            'value' => 'nord_ouest_first_center',
                            'name' => __('Vous voulez selectionne le premier center dans l\'ajoute authomatique ?', 'woo-bordereau-generator'),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'yes',
                                    'name' =>  'nord_ouest_first_center',
                                    'text' => __('Oui', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'value' => 'non',
                                    'name' =>  'nord_ouest_first_center',
                                    'checked' => true,
                                    'text' => __('Non', 'woo-bordereau-generator')
                                ]
                            ]
                        ],
                        'overweight' => [
                            'type' => 'radio-group',
                            'value' => 'recalculate_nord_ouest_express_overweight',
                            'name' => sprintf(__("Voulez-vous activer le calcule le Surpoids (+5KG) pour %s ?", 'woo-bordereau-generator'), $name),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'recalculate-with-overweight',
                                    'name' => 'recalculate_nord_ouest_express_overweight',
                                    'text' => __('Oui', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'checked' => true,
                                    'value' => 'recalculate-without-overweight',
                                    'name' => 'recalculate_nord_ouest_express_overweight',
                                    'text' => __('Non', 'woo-bordereau-generator')
                                ]
                            ]
                        ],
                        'overweight_checkout' => [
                            'type' => 'radio-group',
                            'value' => 'recalculate_nord_ouest_express_overweight_checkout',
                            'name' => sprintf(__("Voulez-vous activer le calcule le Surpoids (+5KG) pour %s  au formulaire de checkout?", 'woo-bordereau-generator'), $name),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'recalculate-with-overweight',
                                    'name' => 'recalculate_nord_ouest_express_overweight_checkout',
                                    'text' => __('Oui', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'checked' => true,
                                    'value' => 'recalculate-without-overweight',
                                    'name' => 'recalculate_nord_ouest_express_overweight_checkout',
                                    'text' => __('Non', 'woo-bordereau-generator')
                                ]
                            ]
                        ],
                        'stock' => [
                            'type' => 'radio-group',
                            'value' => 'nord_ouest_avec_stock',
                            'name' => __('Vous avez un stock avec Nordouest ?', 'woo-bordereau-generator'),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'with-stock',
                                    'name' => 'nord_ouest_avec_stock',
                                    'checked' => true,
                                    'text' => __('Oui', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'value' => 'without-stock',
                                    'name' => 'nord_ouest_avec_stock',
                                    'text' => __('Non', 'woo-bordereau-generator')
                                ]
                            ]
                        ],
                        'can_open' => [
                            'type' => 'radio-group',
                            'value' => 'package_nord_ouest_express_can_open',
                            'name' => sprintf(__("Voulez-vous activer les colis fragil par default pour %s ?", 'woo-bordereau-generator'), $name),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'true',
                                    'name' => 'package_nord_ouest_express_can_open',
                                    'text' => __('Oui', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'checked' => true,
                                    'value' => 'false',
                                    'name' => 'package_nord_ouest_express_can_open',
                                    'text' => __('Non', 'woo-bordereau-generator')
                                ]
                            ]
                        ],
                        'double_shipping_trigger' => [
                            'type' => 'number',
                            'value' => 'nord_ouest_double_shipping_trigger',
                            'name' => __('Double Shipping Price Trigger', 'woo-bordereau-generator'),
                            'desc' => __('Montant du sous-total du panier (en DZD) à partir duquel les frais de livraison seront doublés. Laissez vide ou 0 pour désactiver.', 'woo-bordereau-generator'),
                        ],
                    ]
                ];
            case 'maystro_delivery':
                return [
                    'url' => 'https://maystro-delivery.com/',
                    'api_url' => 'https://orders-management.maystro-delivery.com/api',
                    'type' => 'custom',
                    'image' => plugin_dir_url(dirname(__FILE__)) . 'img/delivery/maystro_delivery.png',
                    'slug' => 'maystro_delivery',
                    'name' => __('Maystro Delivery', 'woo-bordereau-generator'),
                    'class' => MaystroShipping::class,
                    'setting_class' => MaystroShippingSettings::class,
                    'flat_rate_class' => MaystroDeliveryFlatRate::class,
                    'local_pickup_class' => MaystroDeliveryLocalPickup::class,
                    // Backend-driven UI configuration
                    'actions' => self::build_default_actions([
                        'import' => true,
                        'settings' => true,
                        'webhook' => false,
                        'sync' => true,
                    ]),
                    'import_options' => self::build_default_import_options([
                        'flat_rate' => true,
                        'local_pickup' => true,
                        'agency_import' => false,
                    ]),
                    'fields' => [
                        'store_id' => [
                            'secret' => true,
                            'name' => 'Store ID',
                            'value' => 'maystro_delivery_store_id',
                            'desc' => __('Votre API ID de Maystro Delivery voir avec le service commercial', 'woo-bordereau-generator')
                        ],
                        'api_token' => [
                            'name' => __('API Token', 'woo-bordereau-generator'),
                            'value' => 'maystro_delivery_api_token',
                            'secret' => true,
                            'desc' => __('Votre API Token de Maystro Delivery voir avec le service commercial', 'woo-bordereau-generator')
                        ],
                        'total' => [
                            'type' => 'radio-group',
                            'value' => 'maystro_delivery_total',
                            'name' => __('Comment calculer le cout total ?', 'woo-bordereau-generator'),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'with-shipping',
                                    'name' => 'maystro_delivery_total',
                                    'checked' => true,
                                    'text' => __('Avec Livraison', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'value' => 'without-shipping',
                                    'name' => 'maystro_delivery_total',
                                    'text' => __('Sans Livraison', 'woo-bordereau-generator')
                                ]
                            ]
                        ],
                        'advance' => [
                            'type' => 'radio-group',
                            'value' => 'maystro_delivery_advance',
                            'name' => __('Vous voulez geré les produits dans la commande ?', 'woo-bordereau-generator'),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'with-advance-sync',
                                    'name' => 'maystro_delivery_advance',
                                    'checked' => true,
                                    'text' => __('Oui', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'value' => 'without-advance-sync',
                                    'name' => 'maystro_delivery_advance',
                                    'text' => __('Non', 'woo-bordereau-generator')
                                ]
                            ]
                        ],
                        'confirmation' => [
                            'type' => 'radio-group',
                            'value' => 'maystro_delivery_confirmation',
                            'name' => __('Vous voulez active la confirmation ?', 'woo-bordereau-generator'),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'with-confirmation',
                                    'name' => 'maystro_delivery_confirmation',
                                    'checked' => true,
                                    'text' => __('Oui', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'value' => 'without-confirmation',
                                    'name' => 'maystro_delivery_confirmation',
                                    'text' => __('Non', 'woo-bordereau-generator')
                                ]
                            ]
                        ],
                    ]
                ];
            case 'yalidine':

                $fields = [
                    'url' => $domain,
                    'api_url' => $api,
                    'type' => 'custom',
                    'name' => __($name, 'woo-bordereau-generator'),
                    'slug' => $provider,
                    'image' => plugin_dir_url(dirname(__FILE__)) . '/img/delivery/' . $provider . '.png',
                    'class' => YalidineShipping::class,
                    'setting_class' => YalidineShippingSettings::class,
                    'flat_rate_class' => YalidineFlatRate::class,
                    'local_pickup_class' => YalidineLocalPickup::class,
                    // Backend-driven UI configuration
                    'actions' => self::build_default_actions([
                        'import' => true,
                        'settings' => true,
                        'webhook' => true,
                        'sync' => false,
                    ]),
                    'import_options' => self::build_default_import_options([
                        'flat_rate' => true,
                        'local_pickup' => true,
                        'agency_import' => true,
                        // guepex and wecanservices have economic rates
                        'economic_rates' => in_array($provider, ['guepex', 'wecanservices']),
                    ]),
                    'extra' => [
                        "do_insurance" => 'boolean',
                        "declared_value" => 'number',
                        "height" => 'number',
                        "width" => 'number',
                        "length" => 'number',
                        "weight" => 'number',
                    ],
                    'fields' => [
                        'wilaya_from' => [
                            'name' => __('Wilaya From', 'woo-bordereau-generator'),
                            'value' => $provider . '_wilaya_from',
                            'type' => 'select',
                            'css' => '',
                            'desc' => sprintf(__("Votre Wilaya", 'woo-bordereau-generator'), $name),
                            'choices' => $algeria_states
                        ],
                        'api_key' => [
                            'name' => 'API ID',
                            'value' => $provider . '_api_key',
                            'type' => 'text',
                            'css' => '',
                            'secret' => true,
                            'desc' => sprintf(__("Votre API ID de %s voir dans espace Développement > Tableau de bord", 'woo-bordereau-generator'), $name)
                        ],
                        'api_token' => [
                            'name' => __('API Token', 'woo-bordereau-generator'),
                            'value' => $provider . '_api_token',
                            'type' => 'text',
                            'css' => '',
                            'secret' => true,
                            'desc' => sprintf(__("Votre API Token de %s voir dans espace Développement > Tableau de bord", 'woo-bordereau-generator'), $name)
                        ],
                        'webhook_token' => [
                            'name' => __('Webhook Token', 'woo-bordereau-generator'),
                            'value' => $provider . '_webhook_token',
                            'type' => 'text',
                            'css' => '',
                            'secret' => true,
                            'desc' => sprintf(__("Votre Webhook Token de %s voir dans espace Développement > Gérer les Webhooks", 'woo-bordereau-generator'), $name)
                        ],
                        'total' => [
                            'type' => 'radio-group',
                            'value' => $provider . '_total',
                            'name' => __('Comment calculer le cout total ?', 'woo-bordereau-generator'),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'with-shipping',
                                    'name' => $provider . '_total',
                                    'checked' => true,
                                    'text' => __('Avec Livraison (Livraison gratuit sera activé)', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'value' => 'without-shipping',
                                    'name' => $provider . '_total',
                                    'text' => __('Sans Livraison', 'woo-bordereau-generator')
                                ]
                            ]
                        ],
                        'center' => [
                            'type' => 'radio-group',
                            'value' => $provider . '_show_center',
                            'name' => __('Vous voulez afficher les centers?', 'woo-bordereau-generator'),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'show_centers',
                                    'name' => $provider . '_show_center',
                                    'text' => __('Oui', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'value' => 'hide_centers',
                                    'name' => $provider . '_show_center',
                                    'checked' => true,
                                    'text' => __('Non', 'woo-bordereau-generator')
                                ]
                            ]
                        ],
                        'recalculate' => [
                            'type' => 'radio-group',
                            'value' => 'recalculate_' . $provider . '_total',
                            'name' => sprintf(__("Voulez-vous activer la synchronisation des prix avec %s pour calculer la différence?", 'woo-bordereau-generator'), $name),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'recalculate-with-shipping',
                                    'name' => 'recalculate_' . $provider . '_total',
                                    'text' => __('Oui', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'checked' => true,
                                    'value' => 'recalculate-without-shipping',
                                    'name' => 'recalculate_' . $provider . '_total',
                                    'text' => __('Non', 'woo-bordereau-generator')
                                ]
                            ]
                        ],
                        'overweight' => [
                            'type' => 'radio-group',
                            'value' => 'recalculate_' . $provider . '_overweight',
                            'name' => sprintf(__("Voulez-vous activer le calcule le Surpoids (+5KG) pour %s ?", 'woo-bordereau-generator'), $name),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'recalculate-with-overweight',
                                    'name' => 'recalculate_' . $provider . '_overweight',
                                    'text' => __('Oui', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'checked' => true,
                                    'value' => 'recalculate-without-overweight',
                                    'name' => 'recalculate_' . $provider . '_overweight',
                                    'text' => __('Non', 'woo-bordereau-generator')
                                ]
                            ]
                        ],
                        'overweight_checkout' => [
                            'type' => 'radio-group',
                            'value' => 'recalculate_' . $provider . '_overweight_checkout',
                            'name' => sprintf(__("Voulez-vous activer le calcule le Surpoids (+5KG) pour %s  au formulaire de checkout?", 'woo-bordereau-generator'), $name),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'recalculate-with-overweight',
                                    'name' => 'recalculate_' . $provider . '_overweight_checkout',
                                    'text' => __('Oui', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'checked' => true,
                                    'value' => 'recalculate-without-overweight',
                                    'name' => 'recalculate_' . $provider . '_overweight_checkout',
                                    'text' => __('Non', 'woo-bordereau-generator')
                                ]
                            ]
                        ],
                        'added_fee' => [
                            'type' => 'radio-group',
                            'value' => 'added_fee_' . $provider . '_total',
                            'name' => __('Ajouter le frais des communes suppliments ?', 'woo-bordereau-generator'),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'with_added_fee_' . $provider,
                                    'name' => 'added_fee_' . $provider . '_total',
                                    'text' => __('Calcule avec commune suppliment', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'checked' => true,
                                    'value' => 'without_added_fee_' . $provider,
                                    'name' => 'added_fee_' . $provider . '_total',
                                    'text' => __('Calcule sans commune suppliment', 'woo-bordereau-generator')
                                ]
                            ]
                        ],

                    ]
                ];

                if (in_array($provider, ['guepex', 'wecanservices'])) {
                    $fields['fields']['economic'] = [
                        'type' => 'radio-group',
                        'value' => 'economic_'.$provider.'_mode',
                        'name' => __('Active mode economic ?', 'woo-bordereau-generator'),
                        'choices' => [
                            [
                                'type' => 'radio',
                                'value' => 'enable_economic_'. $provider,
                                'name' => 'economic_'.$provider.'_mode',
                                'text' => __('Oui', 'woo-bordereau-generator')
                            ],
                            [
                                'type' => 'radio',
                                'checked' => true,
                                'value' => 'disable_economic_' . $provider,
                                'name' => 'economic_'.$provider.'_mode',
                                'text' => __('Non', 'woo-bordereau-generator')
                            ]
                        ]
                    ];
                }
                return  $fields;
            case 'ups':
                return [
                    'url' => $domain,
                    'api_url' => $api,
                    'type' => 'custom',
                    'name' => __($name, 'woo-bordereau-generator'),
                    'slug' => $provider,
                    'image' => plugin_dir_url(dirname(__FILE__)) . '/img/delivery/' . $provider . '.png',
                    'class' => UPSShipping::class,
                    'setting_class' => UPSShippingSettings::class,
                    'flat_rate_class' => DefaultFlatRate::class,
                    'local_pickup_class' => DefaultLocalPickup::class,
                    // Backend-driven UI configuration
                    'actions' => self::build_default_actions([
                        'import' => true,
                        'settings' => true,
                        'webhook' => false,
                        'sync' => false,
                    ]),
                    'import_options' => self::build_default_import_options([
                        'flat_rate' => true,
                        'local_pickup' => true,
                        'agency_import' => false,
                    ]),
                    'extra' => [
                        "do_insurance" => 'boolean',
                        "declared_value" => 'number',
                        "height" => 'number',
                        "width" => 'number',
                        "length" => 'number',
                        "weight" => 'number',
                    ],
                    'fields' => [
                        'client_id' => [
                            'name' => __('Client ID', 'woo-bordereau-generator'),
                            'value' => $provider . '_client_id',
                            'type' => 'text',
                            'css' => '',
                            'secret' => true,
                            'desc' => sprintf(__('Votre Client ID de %s sera visible apres cree une application voir le video formation', 'woo-bordereau-generator',),$name)
                        ],
                        'client_secret' => [
                            'name' => __('Client Secret', 'woo-bordereau-generator'),
                            'value' => $provider . '_client_secret',
                            'type' => 'text',
                            'css' => '',
                            'desc' => sprintf(__('Votre Client Secret de %s sera visible apres cree une application voir le video formation', 'woo-bordereau-generator',),$name)
                        ],
                        'account_number' => [
                            'name' => __('Account Number', 'woo-bordereau-generator'),
                            'value' => $provider . '_account_number',
                            'type' => 'text',
                            'css' => '',
                            'desc' => sprintf(__('Votre Number de compte pour %s', 'woo-bordereau-generator',),$name)
                        ],
                        'store_name' => [
                            'name' => __('Store Name', 'woo-bordereau-generator'),
                            'value' => $provider . '_store_name',
                            'type' => 'text',
                            'css' => '',
                            'desc' => sprintf(__('Votre Store name sur %s', 'woo-bordereau-generator',),$name)
                        ],
                        'phone_number' => [
                            'name' => __('Phone Number', 'woo-bordereau-generator'),
                            'value' => $provider . '_phone_number',
                            'type' => 'text',
                            'css' => '',
                            'desc' => sprintf(__('Votre Phone Number de compte pour %s', 'woo-bordereau-generator',),$name)
                        ],

                        'total' => [
                            'type' => 'radio-group',
                            'value' => $provider . '_total',
                            'name' => __('Comment calculer le cout total ?', 'woo-bordereau-generator'),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'with-shipping',
                                    'name' => $provider . '_total',
                                    'checked' => true,
                                    'text' => __('Avec Livraison', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'value' => 'without-shipping',
                                    'name' => $provider . '_total',
                                    'text' => __('Sans Livraison', 'woo-bordereau-generator')
                                ]
                            ]
                        ],
                    ]
                ];
            case 'elogistia':
                return [
                    'url' => $domain ?? 'https://elogistia.com',
                    'api_url' => $api ?? 'https://api.elogistia.com',
                    'type' => 'custom',
                    'slug' => $provider,
                    'image' => plugin_dir_url(dirname(__FILE__)) . '/img/delivery/' . $provider . '.png',
                    'name' => __($name, 'woo-bordereau-generator'),
                    'class' => ElogistiaShipping::class,
                    'setting_class' => ElogistiaShippingSettings::class,
                    'local_pickup_class' => ElogistiaLocalPickup::class,
                    'flat_rate_class' => ElogistiaFlatRate::class,
                    // Backend-driven UI configuration
                    'actions' => self::build_default_actions([
                        'import' => true,
                        'settings' => true,
                        'webhook' => false,
                        'sync' => false,
                    ]),
                    'import_options' => self::build_default_import_options([
                        'flat_rate' => true,
                        'local_pickup' => true,
                        'agency_import' => false,
                    ]),
                    'fields' => [
                        'api_token' => [
                            'name' => __('API Token', 'woo-bordereau-generator'),
                            'secret' => true,
                            'value' => $provider . '_api_token',
                            'desc' => sprintf(__('Votre API Token de %s voir dans votre espace à Configuration API', 'woo-bordereau-generator',), $name)
                        ],
                        'total' => [
                            'type' => 'radio-group',
                            'value' => $provider . '_total',
                            'name' => __('Comment calculer le cout total ?', 'woo-bordereau-generator'),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'with-shipping',
                                    'name' => $provider . '_total',
                                    'checked' => true,
                                    'text' => __('Avec Livraison', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'value' => 'without-shipping',
                                    'name' => $provider . '_total',
                                    'text' => __('Sans Livraison', 'woo-bordereau-generator')
                                ]
                            ]
                        ],
                        'bordereau_size' => [
                            'type' => 'select',
                            'value' => $provider . '_bordereau_size',
                            'name' => __('Sélectionner la taille de bordereau', 'woo-bordereau-generator'),
                            'choices' => [
                                [
                                    'value' => '15x20',
                                    'selected' => true,
                                    'name' => $provider . '_bordereau_size',
                                    'text' => '15*20 CM'
                                ],
                                [
                                    'value' => '10x15',
                                    'name' => $provider . '_bordereau_size',
                                    'text' => '10*15 CM'
                                ],
                                [
                                    'value' => '10x10',
                                    'name' => $provider . '_bordereau_size',
                                    'text' => '10*10 CM'
                                ],
                            ]
                        ],
                        'center' => [
                            'type' => 'radio-group',
                            'value' => $provider . '_show_center',
                            'name' => __('Voulez-vous afficher les centers?', 'woo-bordereau-generator'),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'show_centers',
                                    'name' => $provider . '_show_center',
                                    'text' => __('Oui', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'value' => 'hide_centers',
                                    'name' => $provider . '_show_center',
                                    'checked' => true,
                                    'text' => __('Non', 'woo-bordereau-generator')
                                ]
                            ]
                        ]
                    ]
                ];
            case 'conexlog':
                return [
                    'url' => $domain ?? 'https://app.conexlog-dz.com',
                    'api_url' => $api ?? 'https://app.conexlog-dz.com/api/v1/',
                    'type' => 'custom',
                    'slug' => $provider,
                    'image' => plugin_dir_url(dirname(__FILE__)) . '/img/delivery/' . $provider . '.png',
                    'name' => __($name, 'woo-bordereau-generator'),
                    'class' => ConexlogShipping::class,
                    'setting_class' => ConexlogShippingSettings::class,
                    'local_pickup_class' => ConexlogLocalPickup::class,
                    'flat_rate_class' => ConexlogFlatRate::class,
                    // Backend-driven UI configuration
                    'actions' => self::build_default_actions([
                        'import' => true,
                        'settings' => true,
                        'webhook' => false,
                        'sync' => false,
                    ]),
                    'import_options' => self::build_default_import_options([
                        'flat_rate' => true,
                        'local_pickup' => true,
                        'agency_import' => false,
                    ]),
                    'fields' => [
                        'api_token' => [
                            'name' => __('API Token', 'woo-bordereau-generator'),
                            'secret' => true,
                            'value' => $provider . '_api_token',
                            'desc' => sprintf(__('Votre API Token de %s voir dans votre espace à Configuration API', 'woo-bordereau-generator',), $name)
                        ],
                        'total' => [
                            'type' => 'radio-group',
                            'value' => $provider . '_total',
                            'name' => __('Comment calculer le cout total ?', 'woo-bordereau-generator'),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'with-shipping',
                                    'name' => $provider . '_total',
                                    'checked' => true,
                                    'text' => __('Avec Livraison', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'value' => 'without-shipping',
                                    'name' => $provider . '_total',
                                    'text' => __('Sans Livraison', 'woo-bordereau-generator')
                                ]
                            ]
                        ],
                    ]
                ];
            case 'zimoexpress':
                return [
                    'url' => $domain,
                    'api_url' => $api,
                    'type' => 'custom',
                    'slug' => $provider,
                    'image' => plugin_dir_url(dirname(__FILE__)) . '/img/delivery/' . $provider . '.png',
                    'name' => __($name, 'woo-bordereau-generator'),
                    'class' => ZimoExpress::class,
                    'setting_class' => ZimoExpressSettings::class,
                    'local_pickup_class' => ZimoExpressLocalPickup::class,
                    'flat_rate_class' => ZimoExpressFlatRate::class,
                    // Backend-driven UI configuration
                    'actions' => self::build_default_actions([
                        'import' => true,
                        'settings' => true,
                        'webhook' => true,
                        'sync' => false,
                    ]),
                    'import_options' => self::build_default_import_options([
                        'flat_rate' => true,
                        'local_pickup' => true,
                        'agency_import' => true,
                        'flexible_rates' => true,
                        'economic_rates' => true,
                        'zr_express_rates' => true,
                        'maystro_rates' => true,
                        'yalidine_rates' => true,
                    ]),
                    'fields' => [
//                        'username' => [
//                            'secret' => true,
//                            'name' => __('Username', 'woo-bordereau-generator'),
//                            'value' => $provider . '_username',
//                            'desc' => __(__('Votre Nom d\'utilisateur pour acceder au platform ', 'woo-bordereau-generator'), 'woo-bordereau-generator') . $name
//                        ],
//                        'password' => [
//                            'name' => __('Password', 'woo-bordereau-generator'),
//                            'value' => $provider . '_password',
//                            'secret' => true,
//                            'desc' => __(__('Votre Mot de pass pour acceder au platform ', 'woo-bordereau-generator'), 'woo-bordereau-generator') . $name
//                        ],
 	                    'api_token' => [
		                    'name' => __('API Token', 'woo-bordereau-generator'),
		                    'value' => $provider . '_api_token',
		                    'secret' => true,
		                    'desc' => __('Votre API Token de platform ', 'woo-bordereau-generator') . $name
	                    ],
//	                    'shipping_method' => [
//		                    'type' => 'radio-group',
//		                    'value' => $provider . '_shipping_method',
//		                    'name' => __('Selectionne la method de livraison', 'woo-bordereau-generator'),
//		                    'choices' => [
//			                    [
//				                    'type' => 'radio',
//				                    'value' => 'ecommerce',
//				                    'name' => $provider . '_shipping_method',
//				                    'checked' => true,
//				                    'text' => __('Ecommerce', 'woo-bordereau-generator')
//			                    ],
//			                    [
//				                    'type' => 'radio',
//				                    'value' => 'dropship',
//				                    'name' => $provider . '_shipping_method',
//				                    'checked' => true,
//				                    'text' => __('Dropshipping', 'woo-bordereau-generator')
//			                    ], [
//				                    'type' => 'radio',
//				                    'value' => 'warehouse',
//				                    'name' => $provider . '_shipping_method',
//				                    'checked' => true,
//				                    'text' => __('Warehouse', 'woo-bordereau-generator')
//			                    ]
//		                    ]
//	                    ],
	                    'center' => [
		                    'type' => 'radio-group',
		                    'value' => $provider .'_show_center',
		                    'name' => __('Vous voulez afficher les centers?', 'woo-bordereau-generator'),
		                    'choices' => [
			                    [
				                    'type' => 'radio',
				                    'value' => 'show_centers',
				                    'name' =>  $provider .'_show_center',
				                    'text' => __('Oui', 'woo-bordereau-generator')
			                    ],
			                    [
				                    'type' => 'radio',
				                    'value' => 'hide_centers',
				                    'name' =>  $provider .'_show_center',
				                    'checked' => true,
				                    'text' => __('Non', 'woo-bordereau-generator')
			                    ]
		                    ]
	                    ],
	                    'allowed_partners' => [
		                    'type' => 'multiselect',
		                    'value' => $provider . '_allowed_partners',
		                    'name' => __('Point Relais Partners', 'woo-bordereau-generator'),
		                    'desc' => __('Select which delivery partners to show at checkout. Leave empty for all partners.', 'woo-bordereau-generator'),
		                    'dynamic_choices' => 'get_zimo_partners',
		                    'choices' => [
			                    ['value' => 'ZR Express', 'text' => 'ZR Express'],
			                    ['value' => 'Maystro Delivery', 'text' => 'Maystro Delivery'],
			                    ['value' => 'Noest Express', 'text' => 'Noest Express'],
			                    ['value' => 'Yalidine Express', 'text' => 'Yalidine Express'],
			                    ['value' => 'DHD Express', 'text' => 'DHD Express'],
			                    ['value' => 'World Express', 'text' => 'World Express'],
			                    ['value' => 'Partenaire Point Relais', 'text' => 'Partenaire Point Relais'],
			                    ['value' => 'Anderson', 'text' => 'Anderson'],
		                    ]
	                    ],
                        'total' => [
                            'type' => 'radio-group',
                            'value' => $provider . '_total',
                            'name' => __('Comment calculer le cout total ?', 'woo-bordereau-generator'),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'with-shipping',
                                    'name' => $provider . '_total',
                                    'checked' => true,
                                    'text' => __('Avec Livraison', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'value' => 'without-shipping',
                                    'name' => $provider . '_total',
                                    'text' => __('Sans Livraison', 'woo-bordereau-generator')
                                ]
                            ]
                        ]
                    ]
                ];
            case 'internal':
                return [
                    'url' => $domain,
                    'api_url' => $api,
                    'type' => 'internal',
                    'slug' => 'internal',
                    'image' => plugin_dir_url(dirname(__FILE__)) . '/img/delivery/internal.png',
                    'name' => __('Internal Shipping', 'woo-bordereau-generator'),
                    'class' => InternalShipping::class,
                    'setting_class' => InternalShippingSettings::class,
                    'local_pickup_class' => InternalShippingLocalPickup::class,
                    'flat_rate_class' => InternalShippingFlatRate::class,
                    // Backend-driven UI configuration
                    'actions' => self::build_default_actions([
                        'import' => false, // internal has import disabled via options
                        'settings' => true,
                        'webhook' => false,
                        'sync' => false,
                    ]),
                    'import_options' => self::build_default_import_options([
                        'flat_rate' => true,
                        'local_pickup' => true,
                        'agency_import' => false,
                    ]),
                    'fields' => [
                        'generate_tracking_number' => [
                            'type' => 'radio-group',
                            'value' => $provider.'_tracking_number',
                            'name' => __('Vous voulez générer un numéro de suivi ?', 'woo-bordereau-generator'),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'with-tracking-number',
                                    'name' => $provider.'_tracking_number',
                                    'checked' => true,
                                    'text' => __('Avec Numéro de suivi', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'value' => 'without-tracking-number',
                                    'name' => $provider.'_tracking_number',
                                    'text' => __('Sans Numéro de suivi', 'woo-bordereau-generator')
                                ]
                            ]
                        ],
                        'prefix' => [
                            'name' => __('Préfixe', 'woo-bordereau-generator'),
                            'secret' => false,
                            'value' => $provider. '_tracking_number_prefix',
                            'desc' => __('Votre Préfixe pour generater le numéro de suivi', 'woo-bordereau-generator'),
                        ],
                        'tracking_number_format' => [
                            'name' => __('Format du numéro de suivi', 'woo-bordereau-generator'),
                            'secret' => false,
                            'value' => $provider. '_tracking_number_format',
                            'desc' => __('Format du numéro de suivi par example PPNNNNNN ou bien PP-NNNNNN P = Préfixe, N = Numéro', 'woo-bordereau-generator'),
                        ],
                        'drivers' => [
                            'name' => __('Les livreurs', 'woo-bordereau-generator'),
                            'secret' => false,
                            'value' => $provider.'_drivers',
                            'desc' => __('La liste des livreurs', 'woo-bordereau-generator'),
                            'type' => 'drivers-repeater',
                            'css' => '',
                        ]
                    ],
                    'options' => [
                        'import' => false
                    ]
                ];
            case 'colivraison':
                    return [
                        'url' => $domain ?? 'https://beta.colivraison.express',
                        'api_url' => $api ?? 'https://api.colivraison.express/v2/',
                        'type' => 'custom',
                        'slug' => $provider,
                        'image' => plugin_dir_url(dirname(__FILE__)) . '/img/delivery/' . $provider . '.jpeg',
                        'name' => __($name, 'woo-bordereau-generator'),
                        'class' => ColivraisonExpress::class,
                        'setting_class' => ColivraisonExpressSettings::class,
                        'local_pickup_class' => ColivraisonLocalPickup::class,
                        'flat_rate_class' => ColivraisonFlatRate::class,
                        // Backend-driven UI configuration
                        'actions' => self::build_default_actions([
                            'import' => true,
                            'settings' => true,
                            'webhook' => false,
                            'sync' => false,
                        ]),
                        'import_options' => self::build_default_import_options([
                            'flat_rate' => true,
                            'local_pickup' => true,
                            'agency_import' => false,
                        ]),
                        'fields' => [
                            'public_key' => [
                                'name' => __('Public Key', 'woo-bordereau-generator'),
                                'secret' => true,
                                'value' => $provider . '_public_key',
                                'desc' => sprintf(__('Votre Public Key de %s voir dans votre espace à Configuration API', 'woo-bordereau-generator',), $name)
                            ],
                            'access_token' => [
                                'name' => __('Access Token', 'woo-bordereau-generator'),
                                'secret' => true,
                                'value' => $provider . '_access_token',
                                'desc' => sprintf(__('Votre Access Token de %s voir dans votre espace à Configuration API', 'woo-bordereau-generator',), $name)
                            ],
                            'total' => [
                                'type' => 'radio-group',
                                'value' => $provider . '_total',
                                'name' => __('Comment calculer le cout total ?', 'woo-bordereau-generator'),
                                'choices' => [
                                    [
                                        'type' => 'radio',
                                        'value' => 'with-shipping',
                                        'name' => $provider . '_total',
                                        'checked' => true,
                                        'text' => __('Avec Livraison', 'woo-bordereau-generator')
                                    ],
                                    [
                                        'type' => 'radio',
                                        'value' => 'without-shipping',
                                        'name' => $provider . '_total',
                                        'text' => __('Sans Livraison', 'woo-bordereau-generator')
                                    ]
                                ]
                            ],
                        ]
                    ];
				case 'yalitec':
                    return [
                        'url' => $domain ?? 'https://yalitec.com',
                        'api_url' => $api ?? 'https://yal-eco-manager-api.link/api',
                        'type' => 'custom',
                        'slug' => $provider,
                        'image' => plugin_dir_url(dirname(__FILE__)) . '/img/delivery/' . $provider . '.jpeg',
                        'name' => __($name, 'woo-bordereau-generator'),
                        'class' => Yalitec::class,
                        'setting_class' => YalitecSettings::class,
                        'local_pickup_class' => YalitecLocalPickup::class,
                        'flat_rate_class' => YalidineFlatRate::class,
                        // Backend-driven UI configuration
                        'actions' => self::build_default_actions([
                            'import' => true,
                            'settings' => true,
                            'webhook' => $provider !== 'yalitec_new', // yalitec_new has sync instead of webhook
                            'sync' => $provider === 'yalitec_new',
                        ]),
                        'import_options' => self::build_default_import_options([
                            'flat_rate' => true,
                            'local_pickup' => true,
                            'agency_import' => true,
                        ]),
                        'fields' => [
	                        'wilaya_from' => [
		                        'name' => 'Wilaya From',
		                        'value' => $provider . '_wilaya_from',
		                        'type' => 'select',
		                        'css' => '',
		                        'desc' => sprintf(__("Votre Wilaya", 'woo-bordereau-generator'), $name),
		                        'choices' => $algeria_states
	                        ],
                            'api_key' => [
                                'name' => __('API Key', 'woo-bordereau-generator'),
                                'secret' => true,
                                'value' => $provider . '_api_key',
                                'desc' => sprintf(__('Votre API Key de %s voir dans votre espace à Configuration API', 'woo-bordereau-generator',), $name)
                            ],
                            'store_id' => [
                                'name' => __('Store ID', 'woo-bordereau-generator'),
                                'secret' => true,
                                'value' => $provider . '_store_id',
                                'desc' => sprintf(__('Votre Store ID de %s voir dans votre espace à Configuration API', 'woo-bordereau-generator',), $name)
                            ],

	                        'center' => [
		                        'type' => 'radio-group',
		                        'value' => $provider . '_show_center',
		                        'name' => __('Vous voulez afficher les centers?', 'woo-bordereau-generator'),
		                        'choices' => [
			                        [
				                        'type' => 'radio',
				                        'value' => 'show_centers',
				                        'name' =>  $provider . '_show_center',
				                        'text' => __('Oui', 'woo-bordereau-generator')
			                        ],
			                        [
				                        'type' => 'radio',
				                        'value' => 'hide_centers',
				                        'name' =>  $provider .'_show_center',
				                        'checked' => true,
				                        'text' => __('Non', 'woo-bordereau-generator')
			                        ]
		                        ]
	                        ],

                            'advance' => [
	                            'type' => 'radio-group',
	                            'value' =>  $provider .'_advance',
	                            'name' => __('Vous voulez geré les produits dans la commande ?', 'woo-bordereau-generator'),
	                            'choices' => [
		                            [
			                            'type' => 'radio',
			                            'value' => 'with-advance-sync',
			                            'name' => $provider .'_advance',
			                            'checked' => true,
			                            'text' => __('Oui', 'woo-bordereau-generator')
		                            ],
		                            [
			                            'type' => 'radio',
			                            'value' => 'without-advance-sync',
			                            'name' => $provider .'_advance',
			                            'text' => __('Non', 'woo-bordereau-generator')
		                            ]
	                            ]
                            ],
                            'fulfilled' => [
	                            'type' => 'radio-group',
	                            'value' => $provider .'_fulfilled',
	                            'name' => __('Vous geré le fulfilemt avec Yalitec ?', 'woo-bordereau-generator'),
	                            'choices' => [
		                            [
			                            'type' => 'radio',
			                            'value' => 'yes',
			                            'name' => $provider .'_fulfilled',
			                            'checked' => true,
			                            'text' => __('Oui', 'woo-bordereau-generator')
		                            ],
		                            [
			                            'type' => 'radio',
			                            'value' => 'no',
			                            'name' => $provider .'_fulfilled',
			                            'text' => __('Non', 'woo-bordereau-generator')
		                            ]
	                            ]
                            ],
                            'confirmation' => [
	                            'type' => 'radio-group',
	                            'value' =>  $provider.'_confirmation',
	                            'name' => __('Vous voulez active la confirmation ?', 'woo-bordereau-generator'),
	                            'choices' => [
		                            [
			                            'type' => 'radio',
			                            'value' => 'with-confirmation',
			                            'name' => $provider.'_confirmation',
			                            'checked' => true,
			                            'text' => __('Oui', 'woo-bordereau-generator')
		                            ],
		                            [
			                            'type' => 'radio',
			                            'value' => 'without-confirmation',
			                            'name' => $provider.'_confirmation',
			                            'text' => __('Non', 'woo-bordereau-generator')
		                            ]
	                            ]
                            ],
                            'total' => [
                                'type' => 'radio-group',
                                'value' => $provider . '_total',
                                'name' => __('Comment calculer le cout total ?', 'woo-bordereau-generator'),
                                'choices' => [
                                    [
                                        'type' => 'radio',
                                        'value' => 'with-shipping',
                                        'name' => $provider . '_total',
                                        'checked' => true,
                                        'text' => __('Avec Livraison', 'woo-bordereau-generator')
                                    ],
                                    [
                                        'type' => 'radio',
                                        'value' => 'without-shipping',
                                        'name' => $provider . '_total',
                                        'text' => __('Sans Livraison', 'woo-bordereau-generator')
                                    ]
                                ]
                            ],
                        ]
                    ];
			case 'mdm':
                    return [
                        'url' => $domain ?? 'https://mdm.express',
                        'api_url' => $api ?? 'https://api.mdm.express',
                        'type' => 'custom',
                        'slug' => $provider,
                        'image' => plugin_dir_url(dirname(__FILE__)) . '/img/delivery/' . $provider . '.png',
                        'name' => __($name, 'woo-bordereau-generator'),
                        'class' => Mdm::class,
                        'setting_class' => MdmSettings::class,
                        'local_pickup_class' => MdmLocalPickup::class,
                        'flat_rate_class' => MdmFlatRate::class,
                        // Backend-driven UI configuration
                        'actions' => self::build_default_actions([
                            'import' => true,
                            'settings' => true,
                            'webhook' => false,
                            'sync' => true,
                        ]),
                        'import_options' => self::build_default_import_options([
                            'flat_rate' => true,
                            'local_pickup' => true,
                            'agency_import' => true,
                        ]),
                        'fields' => [
	                        'wilaya_from' => [
		                        'name' => 'Wilaya From',
		                        'value' => $provider . '_wilaya_from',
		                        'type' => 'select',
		                        'css' => '',
		                        'desc' => sprintf(__("Votre Wilaya", 'woo-bordereau-generator'), $name),
		                        'choices' => $algeria_states
	                        ],
                            'api_key' => [
                                'name' => __('API Key', 'woo-bordereau-generator'),
                                'secret' => true,
                                'value' => $provider . '_api_key',
                                'desc' => sprintf(__('Votre API Key de %s voir dans votre espace à Configuration API', 'woo-bordereau-generator',), $name)
                            ],
                            'store_id' => [
                                'name' => __('Store ID', 'woo-bordereau-generator'),
                                'secret' => true,
                                'value' => $provider . '_store_id',
                                'desc' => sprintf(__('Votre Store ID de %s voir dans votre espace à Configuration API', 'woo-bordereau-generator',), $name)
                            ],

	                        'center' => [
		                        'type' => 'radio-group',
		                        'value' => $provider . '_show_center',
		                        'name' => __('Vous voulez afficher les centers?', 'woo-bordereau-generator'),
		                        'choices' => [
			                        [
				                        'type' => 'radio',
				                        'value' => 'show_centers',
				                        'name' =>  $provider . '_show_center',
				                        'text' => __('Oui', 'woo-bordereau-generator')
			                        ],
			                        [
				                        'type' => 'radio',
				                        'value' => 'hide_centers',
				                        'name' =>  $provider .'_show_center',
				                        'checked' => true,
				                        'text' => __('Non', 'woo-bordereau-generator')
			                        ]
		                        ]
	                        ],

                            'advance' => [
	                            'type' => 'radio-group',
	                            'value' =>  $provider .'_advance',
	                            'name' => __('Vous voulez geré les produits dans la commande ?', 'woo-bordereau-generator'),
	                            'choices' => [
		                            [
			                            'type' => 'radio',
			                            'value' => 'with-advance-sync',
			                            'name' => $provider .'_advance',
			                            'checked' => true,
			                            'text' => __('Oui', 'woo-bordereau-generator')
		                            ],
		                            [
			                            'type' => 'radio',
			                            'value' => 'without-advance-sync',
			                            'name' => $provider .'_advance',
			                            'text' => __('Non', 'woo-bordereau-generator')
		                            ]
	                            ]
                            ],
                            'fulfilled' => [
	                            'type' => 'radio-group',
	                            'value' => $provider .'_fulfilled',
	                            'name' => __('Vous geré le fulfilment avec MDM Express ?', 'woo-bordereau-generator'),
	                            'choices' => [
		                            [
			                            'type' => 'radio',
			                            'value' => 'yes',
			                            'name' => $provider .'_fulfilled',
			                            'checked' => true,
			                            'text' => __('Oui', 'woo-bordereau-generator')
		                            ],
		                            [
			                            'type' => 'radio',
			                            'value' => 'no',
			                            'name' => $provider .'_fulfilled',
			                            'text' => __('Non', 'woo-bordereau-generator')
		                            ]
	                            ]
                            ],
                            'confirmation' => [
	                            'type' => 'radio-group',
	                            'value' =>  $provider.'_confirmation',
	                            'name' => __('Vous voulez activer la confirmation ?', 'woo-bordereau-generator'),
	                            'choices' => [
		                            [
			                            'type' => 'radio',
			                            'value' => 'with-confirmation',
			                            'name' => $provider.'_confirmation',
			                            'checked' => true,
			                            'text' => __('Oui', 'woo-bordereau-generator')
		                            ],
		                            [
			                            'type' => 'radio',
			                            'value' => 'without-confirmation',
			                            'name' => $provider.'_confirmation',
			                            'text' => __('Non', 'woo-bordereau-generator')
		                            ]
	                            ]
                            ],
                            'total' => [
                                'type' => 'radio-group',
                                'value' => $provider . '_total',
                                'name' => __('Comment calculer le cout total ?', 'woo-bordereau-generator'),
                                'choices' => [
                                    [
                                        'type' => 'radio',
                                        'value' => 'with-shipping',
                                        'name' => $provider . '_total',
                                        'checked' => true,
                                        'text' => __('Avec Livraison', 'woo-bordereau-generator')
                                    ],
                                    [
                                        'type' => 'radio',
                                        'value' => 'without-shipping',
                                        'name' => $provider . '_total',
                                        'text' => __('Sans Livraison', 'woo-bordereau-generator')
                                    ]
                                ]
                            ],
                        ]
                    ];
			case 'zrexpress_v2':
                    return [
                        'url' => $domain ?? 'https://zrexpress.app',
                        'api_url' => $api ?? 'https://api.zrexpress.app/api/v1',
                        'type' => 'custom',
                        'slug' => $provider,
                        'image' => plugin_dir_url(dirname(__FILE__)) . '/img/delivery/' . $provider . '.png',
                        'name' => __($name, 'woo-bordereau-generator'),
                        'class' => ZRExpressTwo::class,
                        'setting_class' => ZRExpressTwoSettings::class,
                        'local_pickup_class' => ZRExpressTwoLocalPickup::class,
                        'flat_rate_class' => ZRExpressTwoFlatRate::class,
                        // Backend-driven UI configuration
                        'actions' => self::build_default_actions([
                            'import' => true,
                            'settings' => true,
                            'webhook' => true,
                            'sync' => true,
                        ]),
                        'import_options' => self::build_default_import_options([
                            'flat_rate' => true,
                            'local_pickup' => true,
                            'agency_import' => true,
                        ]),
                        'fields' => [
                            'api_key' => [
                                'name' => __('API Key', 'woo-bordereau-generator'),
                                'secret' => true,
                                'value' => $provider . '_api_key',
                                'desc' => sprintf(__('Your ZR Express X-Api-Key from dashboard', 'woo-bordereau-generator',), $name)
                            ],
                            'tenant_id' => [
                                'name' => __('Tenant ID', 'woo-bordereau-generator'),
                                'secret' => true,
                                'value' => $provider . '_tenant_id',
                                'desc' => sprintf(__('Your ZR Express X-Tenant identifier', 'woo-bordereau-generator',), $name)
                            ],
                            'stock_type' => [
                                'type' => 'radio-group',
                                'value' => $provider . '_stock_type',
                                'name' => __('Stock Management', 'woo-bordereau-generator'),
                                'choices' => [
                                    [
                                        'type' => 'radio',
                                        'value' => 'local',
                                        'name' => $provider . '_stock_type',
                                        'checked' => true,
                                        'text' => __('Local Stock', 'woo-bordereau-generator')
                                    ],
                                    [
                                        'type' => 'radio',
                                        'value' => 'fulfilled',
                                        'name' => $provider . '_stock_type',
                                        'text' => __('Fulfilled by ZR Express', 'woo-bordereau-generator')
                                    ]
                                ]
                            ],
                            'default_status' => [
                                'type' => 'select',
                                'value' => $provider . '_default_status',
                                'name' => __('Default Status', 'woo-bordereau-generator'),
                                'desc' => __('Select the default status for new parcels', 'woo-bordereau-generator'),
                                'choices' => [
                                    [
                                        'value' => '8a948c66-1ab7-4433-aeb0-94219125d134',
                                        'text' => __('Prêt à expédier', 'woo-bordereau-generator'),
                                        'selected' => true
                                    ],
                                    [
                                        'value' => '8ce66a55-5001-436a-97ec-86daecffcca8',
                                        'text' => __('Draft', 'woo-bordereau-generator')
                                    ]
                                ]
                            ],
                            'overweight' => [
                                'type' => 'radio-group',
                                'value' => 'recalculate_' . $provider . '_overweight',
                                'name' => __('Enable overweight calculation (+5KG)?', 'woo-bordereau-generator'),
                                'choices' => [
                                    [
                                        'type' => 'radio',
                                        'value' => 'recalculate-with-overweight',
                                        'name' => 'recalculate_' . $provider . '_overweight',
                                        'text' => __('Yes', 'woo-bordereau-generator')
                                    ],
                                    [
                                        'type' => 'radio',
                                        'value' => 'recalculate-without-overweight',
                                        'name' => 'recalculate_' . $provider . '_overweight',
                                        'checked' => true,
                                        'text' => __('No', 'woo-bordereau-generator')
                                    ]
                                ]
                            ],
                            'overweight_free_kg' => [
                                'name' => __('Maximum free weight (KG)', 'woo-bordereau-generator'),
                                'value' => $provider . '_overweight_free_kg',
                                'desc' => __('Maximum free weight before calculating overweight (default: 5 KG)', 'woo-bordereau-generator'),
                                'default' => '5',
                                'type' => 'number'
                            ],
                            'overweight_price_per_kg' => [
                                'name' => __('Price per extra KG (DA)', 'woo-bordereau-generator'),
                                'value' => $provider . '_overweight_price_per_kg',
                                'desc' => __('Price in DA for each KG above free weight (default: 50 DA)', 'woo-bordereau-generator'),
                                'default' => '50',
                                'type' => 'number'
                            ],
                            'overweight_checkout' => [
                                'type' => 'radio-group',
                                'value' => 'recalculate_' . $provider . '_overweight_checkout',
                                'name' => __('Enable overweight calculation at checkout?', 'woo-bordereau-generator'),
                                'choices' => [
                                    [
                                        'type' => 'radio',
                                        'value' => 'recalculate-with-overweight',
                                        'name' => 'recalculate_' . $provider . '_overweight_checkout',
                                        'text' => __('Yes', 'woo-bordereau-generator')
                                    ],
                                    [
                                        'type' => 'radio',
                                        'value' => 'recalculate-without-overweight',
                                        'name' => 'recalculate_' . $provider . '_overweight_checkout',
                                        'checked' => true,
                                        'text' => __('No', 'woo-bordereau-generator')
                                    ]
                                ]
                            ],
                            'total' => [
                                'type' => 'radio-group',
                                'value' => $provider . '_total',
                                'name' => __('Calculate Total', 'woo-bordereau-generator'),
                                'choices' => [
                                    [
                                        'type' => 'radio',
                                        'value' => 'with-shipping',
                                        'name' => $provider . '_total',
                                        'checked' => true,
                                        'text' => __('With Shipping', 'woo-bordereau-generator')
                                    ],
                                    [
                                        'type' => 'radio',
                                        'value' => 'without-shipping',
                                        'name' => $provider . '_total',
                                        'text' => __('Without Shipping', 'woo-bordereau-generator')
                                    ]
                                ]
                            ],
                            'advance' => [
                                'type' => 'radio-group',
                                'value' => $provider . '_advance',
                                'name' => __('Product Selection Mode (Local Stock Only)', 'woo-bordereau-generator'),
                                'desc' => __('Choose how products are handled when using local stock type', 'woo-bordereau-generator'),
                                'choices' => [
                                    [
                                        'type' => 'radio',
                                        'value' => 'without-advance-sync',
                                        'name' => $provider . '_advance',
                                        'checked' => true,
                                        'text' => __('Automatic Sync from Order', 'woo-bordereau-generator')
                                    ],
                                    [
                                        'type' => 'radio',
                                        'value' => 'with-advance-sync',
                                        'name' => $provider . '_advance',
                                        'text' => __('Manual Selection from Warehouse', 'woo-bordereau-generator')
                                    ]
                                ]
                            ],
                            'center' => [
                                'type' => 'radio-group',
                                'value' => $provider . '_show_center',
                                'name' => __('Vous voulez afficher les centers?', 'woo-bordereau-generator'),
                                'choices' => [
                                    [
                                        'type' => 'radio',
                                        'value' => 'show_centers',
                                        'name' => $provider . '_show_center',
                                        'text' => __('Oui', 'woo-bordereau-generator')
                                    ],
                                    [
                                        'type' => 'radio',
                                        'value' => 'hide_centers',
                                        'name' => $provider . '_show_center',
                                        'checked' => true,
                                        'text' => __('Non', 'woo-bordereau-generator')
                                    ]
                                ]
                            ],
                            'webhook_secret' => [
                                'name' => __('Webhook Signing Secret', 'woo-bordereau-generator'),
                                'secret' => true,
                                'value' => $provider . '_webhook_secret',
                                'desc' => __('Webhook signing secret from ZR Express for signature verification (optional but recommended)', 'woo-bordereau-generator'),
                                'default' => ''
                            ],
                        ]
                    ];
			case 'near_delivery':
					// Get custom API URL from settings, default to staging for now
					$saved_api_url = get_option($provider . '_api_url');
					$default_api_url = 'https://api.staging.neardelivery.app/api/v1';
                    return [
                        'url' => $domain ?? 'https://neardelivery.app',
                        'api_url' => !empty($saved_api_url) ? $saved_api_url : ($api ?? $default_api_url),
                        'type' => 'custom',
                        'slug' => $provider,
                        'image' => plugin_dir_url(dirname(__FILE__)) . '/img/delivery/' . $provider . '.png',
                        'name' => __($name, 'woo-bordereau-generator'),
                        'class' => NearDelivery::class,
                        'setting_class' => NearDeliverySettings::class,
                        'local_pickup_class' => NearDeliveryLocalPickup::class,
                        // Backend-driven UI configuration
                        // Near Delivery only supports stopdesk (local pickup), no flat rate
                        'actions' => self::build_default_actions([
                            'import' => true,
                            'settings' => true,
                            'webhook' => false,
                            'sync' => false,
                        ]),
                        'import_options' => self::build_default_import_options([
                            'flat_rate' => false,
                            'local_pickup' => true,
                            'agency_import' => false,
                        ]),
                        'fields' => [
                            'api_key' => [
                                'name' => __('API Key', 'woo-bordereau-generator'),
                                'secret' => true,
                                'value' => $provider . '_api_key',
                                'desc' => sprintf(__('Your Near Delivery ApiKey from dashboard', 'woo-bordereau-generator',), $name)
                            ],
                            'api_secret' => [
                                'name' => __('API Secret', 'woo-bordereau-generator'),
                                'secret' => true,
                                'value' => $provider . '_api_secret',
                                'desc' => sprintf(__('Your Near Delivery ApiSecret from dashboard', 'woo-bordereau-generator',), $name)
                            ],
                            'api_url' => [
                                'name' => __('API URL', 'woo-bordereau-generator'),
                                'value' => $provider . '_api_url',
                                'default' => $default_api_url,
                                'desc' => __('Near Delivery API URL (staging: api.staging.neardelivery.app, production: api.neardelivery.app)', 'woo-bordereau-generator')
                            ],
                            'sender_center_id' => [
                                'name' => __('Sender Center ID', 'woo-bordereau-generator'),
                                'value' => $provider . '_sender_center_id',
                                'desc' => __('Your sender center ID from Near Delivery (regional center type=1)', 'woo-bordereau-generator')
                            ],
                            'sender_wilaya' => [
                                'type' => 'select',
                                'name' => __('Sender Wilaya (Starting Point)', 'woo-bordereau-generator'),
                                'value' => $provider . '_sender_wilaya',
                                'desc' => __('Select your starting wilaya for shipping fees calculation', 'woo-bordereau-generator'),
                                'choices' => [
                                    [
                                        'value' => '16',
                                        'text' => '16 - Alger الجزائر'
                                    ],
                                    [
                                        'value' => '25',
                                        'text' => '25 - Constantine قسنطينة'
                                    ],
                                    [
                                        'value' => '31',
                                        'text' => '31 - Oran وهران'
                                    ]
                                ]
                            ],
                            'total' => [
                                'type' => 'radio-group',
                                'value' => $provider . '_total',
                                'name' => __('Calculate Total', 'woo-bordereau-generator'),
                                'choices' => [
                                    [
                                        'type' => 'radio',
                                        'value' => 'with-shipping',
                                        'name' => $provider . '_total',
                                        'checked' => true,
                                        'text' => __('With Shipping', 'woo-bordereau-generator')
                                    ],
                                    [
                                        'type' => 'radio',
                                        'value' => 'without-shipping',
                                        'name' => $provider . '_total',
                                        'text' => __('Without Shipping', 'woo-bordereau-generator')
                                    ]
                                ]
                            ],
                            'center' => [
                                'type' => 'radio-group',
                                'value' => $provider . '_show_center',
                                'name' => __('Voulez-vous afficher les centers?', 'woo-bordereau-generator'),
                                'choices' => [
                                    [
                                        'type' => 'radio',
                                        'value' => 'show_centers',
                                        'name' => $provider . '_show_center',
                                        'text' => __('Oui', 'woo-bordereau-generator')
                                    ],
                                    [
                                        'type' => 'radio',
                                        'value' => 'hide_centers',
                                        'name' => $provider . '_show_center',
                                        'checked' => true,
                                        'text' => __('Non', 'woo-bordereau-generator')
                                    ]
                                ]
                            ],
                        ]
                    ];
            default:
                return [
                    'url' => $domain ?? 'https://' . $provider . '.ecotrack.dz',
                    'api_url' => $api ?? 'https://' . $provider . '.ecotrack.dz/api/v1',
                    'type' => 'ecotrack',
                    'slug' => $provider,
                    'image' => plugin_dir_url(dirname(__FILE__)) . '/img/delivery/' . $provider . '.png',
                    'name' => __($name, 'woo-bordereau-generator'),
                    'class' => EcoTrackShipping::class,
                    'setting_class' => EcoTrackShippingSettings::class,
                    'local_pickup_class' => EcotrackLocalPickup::class,
                    'flat_rate_class' => EcotrackFlatRate::class,
                    // Backend-driven UI configuration
                    'actions' => self::build_default_actions([
                        'import' => true,
                        'settings' => true,
                        'webhook' => false,
                        'sync' => false,
                    ]),
                    'import_options' => self::build_default_import_options([
                        'flat_rate' => true,
                        'local_pickup' => true,
                        'agency_import' => true,
                    ]),
                    'fields' => [
                        'api_token' => [
                            'name' => __('API Token', 'woo-bordereau-generator'),
                            'secret' => true,
                            'value' => $provider . '_api_token',
                            'desc' => sprintf(__('Votre API Token de %s voir dans votre espace à Configuration API', 'woo-bordereau-generator',), $name)
                        ],
                        'total' => [
                            'type' => 'radio-group',
                            'value' => $provider . '_total',
                            'name' => __('Comment calculer le cout total ?', 'woo-bordereau-generator'),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'with-shipping',
                                    'name' => $provider . '_total',
                                    'checked' => true,
                                    'text' => __('Avec Livraison', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'value' => 'without-shipping',
                                    'name' => $provider . '_total',
                                    'text' => __('Sans Livraison', 'woo-bordereau-generator')
                                ]
                            ]
                        ],
                        'stock' => [
                            'type' => 'radio-group',
                            'value' => $provider .'_avec_stock',
                            'name' => sprintf(__('Vous avez un stock avec %s ?', 'woo-bordereau-generator'), $name),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'with-stock',
                                    'name' => $provider .'_avec_stock',
                                    'text' => __('Oui', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'value' => 'without-stock',
                                    'checked' => true,
                                    'name' => $provider .'_avec_stock',
                                    'text' => __('Non', 'woo-bordereau-generator')
                                ]
                            ]
                        ],
                        'overweight' => [
                            'type' => 'radio-group',
                            'value' => 'recalculate_' . $provider . '_overweight',
                            'name' => sprintf(__("Voulez-vous activer le calcule le Surpoids pour %s ?", 'woo-bordereau-generator'), $name),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'recalculate-with-overweight',
                                    'name' => 'recalculate_' . $provider . '_overweight',
                                    'text' => __('Oui', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'checked' => true,
                                    'value' => 'recalculate-without-overweight',
                                    'name' => 'recalculate_' . $provider . '_overweight',
                                    'text' => __('Non', 'woo-bordereau-generator')
                                ]
                            ]
                        ],
                        'overweight_free_kg' => [
                            'name' => __('Poids gratuit maximum (KG)', 'woo-bordereau-generator'),
                            'value' => $provider . '_overweight_free_kg',
                            'desc' => __('Le poids maximum gratuit avant de calculer le surpoids (par défaut: 5 KG)', 'woo-bordereau-generator'),
                            'default' => '5',
                            'type' => 'number'
                        ],
                        'overweight_price_per_kg' => [
                            'name' => __('Prix par KG supplémentaire (DA)', 'woo-bordereau-generator'),
                            'value' => $provider . '_overweight_price_per_kg',
                            'desc' => __('Le prix en DA pour chaque KG au-dessus du poids gratuit (par défaut: 50 DA)', 'woo-bordereau-generator'),
                            'default' => '50',
                            'type' => 'number'
                        ],
                        'overweight_checkout' => [
                            'type' => 'radio-group',
                            'value' => 'recalculate_' . $provider . '_overweight_checkout',
                            'name' => sprintf(__("Voulez-vous activer le calcule le Surpoids pour %s au formulaire de checkout?", 'woo-bordereau-generator'), $name),
                            'choices' => [
                                [
                                    'type' => 'radio',
                                    'value' => 'recalculate-with-overweight',
                                    'name' => 'recalculate_' . $provider . '_overweight_checkout',
                                    'text' => __('Oui', 'woo-bordereau-generator')
                                ],
                                [
                                    'type' => 'radio',
                                    'checked' => true,
                                    'value' => 'recalculate-without-overweight',
                                    'name' => 'recalculate_' . $provider . '_overweight_checkout',
                                    'text' => __('Non', 'woo-bordereau-generator')
                                ]
                            ]
                        ]
                    ]
                ];
        }
    }

    /**
     * Mark a provider as deprecated
     * @param array $provider Provider settings array
     * @return array Provider settings with deprecated flag
     */
    public static function mark_as_deprecated(array $provider): array
    {
        $provider['deprecated'] = true;
        return $provider;
    }

    public static function get_providers(): array
    {
        return apply_filters(
            'wc_bordereau_tracking_get_providers',
            [
                //'zr_express_new' => self::mark_as_deprecated(self::get_settings_from_provider('procolis_new', 'zr_express_new', 'ZR Express', 'https://zrexpress.com', 'https://zr.express/ZR_WEB/', 'https://zr.express/ZR_WEB/FR/Connexion/ZRexpress.awp', ['context' => 'A4', 'pa3' => '1'])),
                'msm_go' => self::get_settings_from_provider('ecotrack', 'msm_go', 'MSM Go', 'https://msmgo.ecotrack.dz', 'https://msmgo.ecotrack.dz/api/v1'),
                'wecanservices' => self::get_settings_from_provider('yalidine', 'wecanservices', 'WecanServices', 'https://wecanservices.me', 'https://api.wecanservices.me/v1'),
                'yalidine' => self::get_settings_from_provider('yalidine', 'yalidine', 'Yalidine', 'https://yalidine.app', 'https://api.yalidine.app/v1'),
                'rj360express' => self::get_settings_from_provider('ecotrack', 'rj360express', 'Rj 360 Express'),
                'maystro_delivery' => self::get_settings_from_provider('maystro_delivery'),
                'guepex' => self::get_settings_from_provider('yalidine', 'guepex', 'Guepex', 'https://guepex.app', 'https://api.guepex.app/v1'),
                'speedmail' => self::get_settings_from_provider('yalidine', 'speedmail', 'SpeedMail', 'https://speedmail-dz.app', 'https://api.speedmail-dz.app/v1'),
                'yalitec' => self::get_settings_from_provider('yalidine', 'yalitec', 'Yalitec', 'https://yalitec.me', 'https://api.yalitec.me/v1'),
                'yalitec_new' => self::get_settings_from_provider('yalitec', 'yalitec_new', 'Yalitec Fullfilment'),
                'mdm' => self::get_settings_from_provider('mdm', 'mdm', 'MDM Express'),
                'easyandspeed' => self::get_settings_from_provider('yalidine', 'easyandspeed', 'Easy & Speed', 'https://easyandspeed.app', 'https://api.easyandspeed.app/v1'),
                'ups_conexlog' => self::get_settings_from_provider('conexlog', 'ups_conexlog', 'UPS - CONEXLOG', 'https://app.conexlog-dz.com', 'https://app.conexlog-dz.com/api/v1'),
                'zimoexpress' => self::get_settings_from_provider('zimoexpress', 'zimoexpress', 'Zimou Express', 'https://zimouexpress.com', 'https://zimou.express'),
                'colivraison' => self::get_settings_from_provider('colivraison', 'colivraison', 'Colivraison Express', 'https://beta.colivraison.express', 'https://api.colivraison.express/v2/'),
                //'zr_express_api' => self::mark_as_deprecated(self::get_settings_from_provider('procolis_api', 'zr_express_api', 'ZR Express API', 'https://zrexpress.com', 'https://procolis.com/api_v1', '')),
                'zrexpress_v2' => self::get_settings_from_provider('zrexpress_v2', 'zrexpress_v2', 'ZR Express 2.0'),
                'near_delivery' => self::get_settings_from_provider('near_delivery', 'near_delivery', 'Near Delivery', 'https://neardelivery.app', 'https://api.staging.neardelivery.app/api/v1'),
                //'ecom_dz' => self::mark_as_deprecated(self::get_settings_from_provider('procolis_new', 'ecom_dz', 'E-Com Delivery', 'https://ecom-dz.com', 'https://ecom-dz.com/ECOM-DZ_WEB/', 'https://ecom-dz.com/ECOM-DZ_WEB/FR/ecap1.awp', ['context' => 'M3', 'pa3' => '6'])),
                'ecom_dz_new' => self::get_settings_from_provider('toncolis_api', 'ecom_dz_new', 'E-Com Delivery New', 'https://ecom-dz.net/', 'https://ecom-dz.net/Api_v1/'),
//                'ecom_dz_scrap' => self::get_settings_from_provider('toncolis', 'ecom_dz_scrap', 'E-Com Delivery New2', 'https://ecom-dz.net/', 'https://ecom-dz.net/EC_COLIS_WEB/FR/Fournisseur_A/', 'https://ecom-dz.net/EC_COLIS_WEB/FR/Connexion/Ecom.awp', ['context' => 'A13']),
                'abex_dz' => self::get_settings_from_provider('procolis_new', 'abex_dz', 'ABEX Express', 'https://abex-dz.com', 'https://abex-dz.com/ABEX-DZ_WEB/', 'https://abex-dz.com/ABEX-DZ_WEB/FR/sdg9213.awp', ['context' => 'M3', 'pa3' => '8']),
                'lihlihexpress' => self::get_settings_from_provider('lihlihexpress', 'lihlihexpress', 'Lihlih Express', 'https://lihlihexpress.com', 'https://lihlihexpress.com/api/v1/'),
                'colireli' => self::get_settings_from_provider('procolis_new', 'colireli', 'Coli Reli', 'https:/colireli.net', 'https://colireli.net/COLIRELI_WEB/', 'https://colireli.net/COLIRELI_WEB/FR/Colireli.awp', ['context' => 'M3', 'pa3' => '12']),
                'stalgerie' => self::get_settings_from_provider('procolis', 'stalgerie', 'STAlgerie', 'https://stalgerie.com', 'https://stalgerie.com/STA_WEB/', 'https://stalgerie.com/STA_WEB/FR/ConnexionST.awp'),
                'win_delivery' => self::get_settings_from_provider('procolis_new', 'win_delivery', 'Win Delivery', 'https://win-delivery.fr', 'https://win-delivery.fr/WIN-DELIVERY_WEB/', 'https://win-delivery.fr/WIN-DELIVERY_WEB/FR/wfd69.awp', ['context' => 'M3', 'pa3' => '9']),
                'esend_dz' => self::get_settings_from_provider('procolis_new', 'esend_dz', 'E-SEND', 'https://esend-dz.com', 'https://esend-dz.com/ESEND_WEB/', 'https://esend-dz.com/ESEND_WEB/FR/Esend.awp', ['context' => 'M3', 'pa3' => '14']),
                'allolivraison' => self::mark_as_deprecated(self::get_settings_from_provider('procolis_new', 'allolivraison', 'Allo Livraison', 'https://allolivraison.com', 'https://allolivraison.com/ALLOLIVRAISON_WEB/', 'https://allolivraison.com/ALLOLIVRAISON_WEB/FR/Allo.awp', ['context' => 'M3', 'pa3' => '18'])),
                'flashdz' => self::mark_as_deprecated(self::get_settings_from_provider('procolis_new', 'flashdz', 'Flash Delivery', 'https://flashdz.fr', 'https://flashdz.com/FLASH_WEB/', 'https://flashdz.com/FLASH_WEB/FR/Flashdz.awp', ['context' => 'M3', 'pa3' => '3'])),
                'flashfr' => self::get_settings_from_provider('toncolis_api', 'flashfr', 'Flash Delivery New', 'https://flashdz.fr/', 'https://flashdz.fr/Api_v1/'),
                'godyma' => self::get_settings_from_provider('procolis_new', 'godyma', 'GODYMA Express', 'https://godyma-app.com/', 'https://godyma-app.com/GODYMA_WEB/', 'https://godyma-app.com/GODYMA_WEB/FR/godyma-2.awp', ['context' => 'M3', 'pa3' => '20']),
                'rushdeliverydz' => self::get_settings_from_provider('procolis_new', 'rushdeliverydz', 'Rush Delivery DZ', 'https://www.rushdeliverydz.com', 'https://rushdeliverydz.com/RUSHDELIVERYDZ_WEB/', 'https://rushdeliverydz.com/RUSHDELIVERYDZ_WEB/FR/a9sd3.awp', ['context' => 'M3', 'pa3' => '4']),
                'leoparexpress' => self::get_settings_from_provider('procolis_new', 'leoparexpress', 'Léopard express', 'https://leoparexpress.fr', 'https://leoparexpress.fr/LEOPAREXPRESS_WEB/', 'https://leoparexpress.fr/LEOPAREXPRESS_WEB/FR/sdbity3.awp', ['context' => 'M3', 'pa3' => '11']),
                'nord_ouest' => self::get_settings_from_provider('nordouest', 'nord_ouest', 'Noest'),
                'colilogexpress' => self::get_settings_from_provider('procolis_new', 'colilogexpress', 'Colis Log', 'https://colilogexpress.com/new', 'https://colilogexpress.com/COLILOGEXPRESS_WEB/', 'https://colilogexpress.com/COLILOGEXPRESS_WEB/FR/dsf9633.awp', ['context' => 'M3', 'pa3' => '15']),
                'rex' => self::get_settings_from_provider('ecotrack', 'rex', 'Rex Livraison'),
                'ecomexpress' => self::get_settings_from_provider('ecotrack', 'ecomexpress', 'Ecom Express'),
                'elguidedelivery' => self::get_settings_from_provider('ecotrack', 'elguidedelivery', 'Elguide delivery'),
                'lynx' => self::get_settings_from_provider('ecotrack', 'lynx', 'Lynx Express'),
                'ovred' => self::get_settings_from_provider('ecotrack', 'ovred', 'OVRED'),
                'fzdelivery' => self::get_settings_from_provider('ecotrack', 'fzdelivery', 'FZ Delivery'),
                'yesexpress' => self::get_settings_from_provider('ecotrack', 'yesexpress', 'Yes Express'),
                'rsexpress' => self::get_settings_from_provider('ecotrack', 'rsexpress', 'RSExpress'),
                'fasthorse' => self::get_settings_from_provider('ecotrack', 'fasthorse', 'FastHores Express'),
                'rblivraison' => self::get_settings_from_provider('ecotrack', 'rblivraison', 'RB Livraison'),
                'magicexpress' => self::get_settings_from_provider('ecotrack', 'magicexpress', 'Magic express'),
                'tawseel' => self::get_settings_from_provider('ecotrack', 'tawseel', 'Tawseel Premium'),
                'dhd' => self::get_settings_from_provider('ecotrack', 'dhd', 'DHD Livraison', 'https://platform.dhd-dz.com', 'https://platform.dhd-dz.com/api/v1'),
                'dhd-new' => self::get_settings_from_provider('ecotrack', 'dhd-new', 'DHD Livraison New', 'https://platform.dhd-dz.com', 'https://platform.dhd-dz.com/api/v1'),
                //'expediachrono' => self::mark_as_deprecated(self::get_settings_from_provider('ecotrack', 'expediachrono', 'Expedia Chrono')),
                'expediachrono2' => self::get_settings_from_provider('ecotrack', 'expediachrono2', 'Expedia Chrono New'),
                'allolivraison_new' => self::get_settings_from_provider('ecotrack', 'allolivraison_new', 'Allo Livraison New', 'https://allolivraison.net', 'https://allolivraison.ecotrack.dz/api/v1'),
                'casperdelivery' => self::get_settings_from_provider('ecotrack', 'casperdelivery', 'Casper Delivery'),
                'delivromail' => self::get_settings_from_provider('ecotrack', 'delivromail', 'Delivro Mail'),
                'champion' => self::get_settings_from_provider('ecotrack', 'champion', 'Champion Lak Logistics'),
                'speeddelivery' => self::get_settings_from_provider('ecotrack', 'speeddelivery', 'Speed Delivery'),
                'bipdelivery' => self::get_settings_from_provider('ecotrack', 'bipdelivery', 'Bipdelivery'),
                'elogistia' => self::get_settings_from_provider('elogistia', 'elogistia', 'Elogistia'),
                'swiftly' => self::get_settings_from_provider('ecotrack', 'swiftly', 'Swiftly'),
                'omexpress' => self::get_settings_from_provider('ecotrack', 'omexpress', 'OM Express'),
                'sendbox' => self::get_settings_from_provider('ecotrack', 'sendbox', 'Sendbox Express'),
                'jaguar' => self::get_settings_from_provider('ecotrack', 'jaguar', 'Jaguar Express'),
                'atlaexpress' => self::get_settings_from_provider('ecotrack', 'atlaexpress', 'Atla Express'),
                'samex' => self::get_settings_from_provider('ecotrack', 'samex', 'Samex'),
                'areex' => self::get_settings_from_provider('ecotrack', 'areex', 'Areex'),
                'prest' => self::mark_as_deprecated(self::get_settings_from_provider('ecotrack', 'prest', 'Prest')),
                'prestexpress' => self::get_settings_from_provider('ecotrack', 'prestexpress', 'Prest New'),
                'rocket' => self::get_settings_from_provider('ecotrack', 'rocket', 'Rocket Delivery'),
                'easyexpress' => self::get_settings_from_provider('ecotrack', 'easyexpress', 'Easy Express'),
                'worldexpress' => self::mark_as_deprecated(self::get_settings_from_provider('ecotrack', 'worldexpress', 'WorldExpress')),
                'world-express' => self::get_settings_from_provider('ecotrack', 'world-express', 'WorldExpress New'),
                'bacexpress' => self::get_settings_from_provider('ecotrack', 'bacexpress', 'BA Consult'),
                'happiness' => self::get_settings_from_provider('ecotrack', 'happiness', 'Happiness line'),
                'packers' => self::get_settings_from_provider('ecotrack', 'packers', 'Packers'),
                'dzchrono' => self::get_settings_from_provider('ecotrack', 'dzchrono', 'Dz Chrono Delivery'),
                '48hr' => self::get_settings_from_provider('ecotrack', '48hr', '48Hr Livraison'),
                'powerdelivery' => self::get_settings_from_provider('ecotrack', 'powerdelivery', 'Power Delivery Express'),
                'canasta' => self::get_settings_from_provider('ecotrack', 'canasta', 'Canasta express'),
                'amexpress' => self::get_settings_from_provider('ecotrack', 'amexpress', 'AM EXPRESS'),
                'oksbox' => self::get_settings_from_provider('ecotrack', 'oksbox', 'OKs BOX'),
                'itgv' => self::get_settings_from_provider('ecotrack', 'itgv', 'ITGV'),
                'navexdelivery' => self::get_settings_from_provider('ecotrack', 'navexdelivery', 'Navex Delivery'),
                'moumen' => self::get_settings_from_provider('ecotrack', 'moumen', 'Hayla-Foorshop'),
                'mono' => self::get_settings_from_provider('ecotrack', 'mono', 'Mono Hub'),
                'diplomatico' => self::get_settings_from_provider('ecotrack', 'diplomatico', 'Diplomatico Delivery'),
                'younexexpress' => self::get_settings_from_provider('ecotrack', 'younexexpress', 'Younex express'),
                'anderson' => self::get_settings_from_provider('ecotrack', 'anderson', 'Anderson Delivery', 'https://anderson-ecommerce.ecotrack.dz/', 'https://anderson-ecommerce.ecotrack.dz/api/v1'),
                'adsil' => self::get_settings_from_provider('ecotrack', 'adsil', 'Adsil'),
                'hhdexpress' => self::get_settings_from_provider('ecotrack', 'hhdexpress', 'HDD Express'),
                'weewee' => self::get_settings_from_provider('ecotrack', 'weewee', 'Weewee Delivery'),
                'khoudhajtek' => self::get_settings_from_provider('ecotrack', 'khoudhajtek', 'Khoud Hajtek'),
                'golivri' => self::get_settings_from_provider('ecotrack', 'golivri', 'GOLIVRI'),
                'rihalexpress' => self::get_settings_from_provider('ecotrack', 'rihalexpress', 'Rihal Express'),
                'tawsil' => self::get_settings_from_provider('ecotrack', 'tawsil', 'Tawsil Star'),
                'dch' => self::get_settings_from_provider('ecotrack', 'dch', 'DCH Express'),
                'hhexpress' => self::get_settings_from_provider('ecotrack', 'hhexpress', 'H&H Express'),
                'coyoteexpress' => self::get_settings_from_provider('ecotrack', 'coyoteexpressdz', 'Coyote express'),
                'alena' => self::get_settings_from_provider('ecotrack', 'alena', 'Alena Express'),
                'salvadelivery' => self::get_settings_from_provider('ecotrack', 'salvadelivery', 'Salva Delivery'),
                'sirafex' => self::get_settings_from_provider('ecotrack', 'sirafex', 'Sirafex'),
                'eagles' => self::get_settings_from_provider('ecotrack', 'eagles', 'Eagles Delivery'),
                'distazero' => self::get_settings_from_provider('ecotrack', 'distazero', 'Distazero'),
                'imir' => self::get_settings_from_provider('ecotrack', 'imir', 'Imir Logistics'),
                'swift' => self::get_settings_from_provider('ecotrack', 'swift', 'Swift Express'),
                'albahdja' => self::get_settings_from_provider('ecotrack', 'albahdja', 'Al bahdja Express'),
                's4logistic' => self::get_settings_from_provider('ecotrack', 's4logistic', 'S4 LOGISTIC'),
                'bringme' => self::get_settings_from_provider('ecotrack', 'bringme', 'Bringme'),
                'mehari' => self::get_settings_from_provider('ecotrack', 'mehari', 'Mehari'),
                'honortracking' => self::get_settings_from_provider('ecotrack', 'honortracking', 'Honor Tracking'),
                'antilope' => self::get_settings_from_provider('ecotrack', 'antilope', 'antilope'),
                'servitec' => self::get_settings_from_provider('ecotrack', 'servitec', 'Servitec'),
                'mazaya' => self::get_settings_from_provider('ecotrack', 'mazaya', 'Mazaya logistics'),
                'e-logistica' => self::get_settings_from_provider('ecotrack', 'e-logistica', 'E-Logistica'),
                'fret' => self::get_settings_from_provider('ecotrack', 'fret', 'FRET.Direct'),
                'colexexpress' => self::get_settings_from_provider('ecotrack', 'colexexpress', 'COLEX express'),
                '3mexpress' => self::get_settings_from_provider('3m', '3mexpress', '3M Express', 'https://www.3m.express/', 'https://elmarto.3m.express'),
                'mylerz' => self::get_settings_from_provider('mylerz', 'mylerz', 'Mylerz', 'https://algeria.mylerz.com/', 'https://integration.algeria.mylerz.net'),
                'csexpress' => self::get_settings_from_provider('ecotrack', 'csexpress', 'CS Express'),
                'tsl' => self::get_settings_from_provider('ecotrack', 'tsl', 'TSL Express'),
                'negmar31' => self::get_settings_from_provider('ecotrack', 'negmar31', 'Negmar Express Oran'),
                'negmar' => self::get_settings_from_provider('ecotrack', 'negmar', 'Negmar Express'),
//                'zr_express' => self::get_settings_from_provider('procolis', 'zr_express', 'ZR Express Old', 'https://zrexpress.fr', 'https://zrexpress.fr/EXPRESS_WEB/', 'https://zrexpress.fr/EXPRESS_WEB/FR/ConnexionZR.awp'),
                'ups' => self::get_settings_from_provider('ups', 'ups', 'UPS', 'https://ups.com', 'https://onlinetools.ups.com/'),
                'toncolis_api' => self::get_settings_from_provider('toncolis_api', 'toncolis_api', 'TonColis', 'https://zrexpress.com', 'https://TonColis.Com/Api_v1'),
                'internal' => self::get_settings_from_provider('internal', 'internal', 'Internal Shipping', '', ''),
                'zr_express_backup' => self::mark_as_deprecated(self::get_settings_from_provider('procolis_new', 'zr_express_new', 'ZR Express Backup', 'https://zrexpress.com', 'https://procolis.com/PROCLIENT_WEB/', 'https://procolis.com/PROCLIENT_WEB/FR/Connexion/ZREXPRESS.awp', ['context' => 'A4', 'pa3' => '1'])),

            ]
        );
    }

    /**
     * @param $provider
     * @param $post_id
     *
     * @return array
     * @since 1.2.0
     */

    public static function get_wilayas($provider, $post_id): array
    {
        global $theorder;

        if (!is_object($theorder)) {
            $theorder = wc_get_order($post_id);
        }

        $theorder = $theorder instanceof WC_Order ? $theorder : null;

        $wilayasResult = [];

        return self::extracted($provider, $wilayasResult, $theorder);
    }


    /**
     * @param $provider
     * @param $post_id
     * @return array
     * @since 1.2.0
     */
    public static function get_wilayas_by_id($provider, $post_id): array
    {

        global $theorder;

        $wilayasResult = [];

        if (!is_object($theorder)) {
            $theorder = wc_get_order($post_id);
        }

        $theorder = $theorder instanceof WC_Order ? $theorder : null;

        $result = self::extracted($provider, $wilayasResult, $theorder);


        return $result;
    }

    /**
     * Get the settings for each provider (shipping, fields,)
     * @param $provider
     * @param $post_id
     * @param null $option
     * @return array
     * @since 1.2.4
     */
    public static function get_settings(array $data): array
    {

        global $theorder;

        $post_id = sanitize_text_field($data['order']);
        $provider = sanitize_text_field($data['provider']);
        $option = sanitize_text_field($data['type']) ?? null;

        if (!is_object($theorder)) {
            $theorder = wc_get_order($post_id);
        }

        $theorder = $theorder instanceof WC_Order ? $theorder : null;

        if ($theorder) {
            $providers = BordereauGeneratorProviders::get_providers();
            $selectedProvider = $providers[$provider];
            if (class_exists($selectedProvider['class'])) {
                $class = new $selectedProvider['class']($theorder, $selectedProvider);
                return $class->get_settings($data, $option);
            }
        }

        return [];
    }

    /**
     * Get the settings for each provider (shipping, fields,)
     * @param $provider
     * @param $post_id
     *
     * @return array
     * @since 1.2.4
     */
    public static function get_slip($provider, $post_id): array
    {
        global $theorder;

        if (!is_object($theorder)) {
            $theorder = wc_get_order($post_id);
        }

        $theorder = $theorder instanceof WC_Order ? $theorder : null;

        if ($theorder) {
            $providers = BordereauGeneratorProviders::get_providers();

            $selectedProvider = $providers[$provider];


            if (class_exists($selectedProvider['class'])) {
                $class = new $selectedProvider['class']($theorder, $selectedProvider);
                return $class->get_slip();
            }
        }

        return [];
    }

    /**
     * @param $post_id
     * @param $provider
     * @param $wilaya
     * @param $type
     * @return array
     */
    public static function get_communes($post_id, $provider, $wilaya, $type): array
    {
        global $theorder;

        if (!is_object($theorder)) {
            $theorder = wc_get_order($post_id);
        }

        $theorder = $theorder instanceof WC_Order ? $theorder : null;

        if ($theorder) {
            $providers = BordereauGeneratorProviders::get_providers();

            $selectedProvider = $providers[$provider];

            if (class_exists($selectedProvider['class'])) {
                $class = new $selectedProvider['class']($theorder, $selectedProvider);
                return $class->get_communes($wilaya, $type == 'stopdesk');
            }
        }

        return [];
    }

    /**
     * @param $post_id
     * @param $commune
     * @return array
     */
    public static function get_centers($post_id, $commune, $provider): array
    {

        global $theorder;

        if (!is_object($theorder)) {
            $theorder = wc_get_order($post_id);
        }

        $theorder = $theorder instanceof WC_Order ? $theorder : null;

        if ($theorder) {
            $providers = BordereauGeneratorProviders::get_providers();
            $selectedProvider = $providers[$provider];
            if (class_exists($selectedProvider['class'])) {
                $class = new $selectedProvider['class']($theorder, $selectedProvider);
                return $class->get_centers($commune);
            }
        }

        return [];
    }

    public static function get_centers_per_wilaya($post_id, $wilaya, $provider): array
    {
        global $theorder;

        if (!is_object($theorder)) {
            $theorder = wc_get_order($post_id);
        }

        $theorder = $theorder instanceof WC_Order ? $theorder : null;

        if ($theorder) {
            $providers = BordereauGeneratorProviders::get_providers();
            $selectedProvider = $providers[$provider];
            if (class_exists($selectedProvider['class'])) {
                $wilaya = (int) str_replace('DZ-','', $wilaya);
                $class = new $selectedProvider['class']($theorder, $selectedProvider);
                return $class->get_centers($wilaya);
            }
        }

        return [];
    }


    /**
     * @param $post_id
     * @param $wilaya
     * @param $type
     * @return mixed|void
     */
    public static function get_communes_by_id($post_id, $wilaya, $type)
    {

        global $theorder;

        if (!is_object($theorder)) {
            $theorder = wc_get_order($post_id);
        }

        $theorder = $theorder instanceof WC_Order ? $theorder : null;

        $provider = get_post_meta($post_id, '_shipping_tracking_method', true);

        if(! $provider) {
            $provider = $theorder->get_meta('_shipping_tracking_method');
        }


        if ($theorder && $provider) {

            $providers = BordereauGeneratorProviders::get_providers();

            $selectedProvider = $providers[$provider];

            if (class_exists($selectedProvider['class'])) {
                $class = new $selectedProvider['class']($theorder, $selectedProvider);
                return $class->get_communes($wilaya, $type == 'stopdesk');
            }
        }
    }

    /**
     * @param mixed $provider
     * @param array $wilayasResult
     * @param null $theorder
     *
     * @return array
     */
    public static function extracted(string $provider, array $wilayasResult, $theorder = null): array
    {
        $providers = self::get_providers();
        $selectedProvider = $providers[$provider];
        if (class_exists($selectedProvider['class'])) {
            $class = new $selectedProvider['class']($theorder, $selectedProvider);
            $wilayasResult = $class->get_wilayas_by_provider($provider);
        }

        return $wilayasResult;
    }


    /**
     *
     * @param array $data
     * @param null $theorder
     * @return array
     */
    public static function import_shipping_class_by_provider($data, $theorder = null): array
    {

        $flat_rate_label = sanitize_text_field($data['flat_rate_label']);
        $pickup_local_label = sanitize_text_field($data['pickup_local_label']);
        $flexible_label = sanitize_text_field($data['flexible_label'] ?? '');
        $economic_label = sanitize_text_field($data['economic_label'] ?? '');
        $zr_express_label = sanitize_text_field($data['zr_express_label'] ?? '');
        $maystro_label = sanitize_text_field($data['maystro_label'] ?? '');
        $yalidine_label = sanitize_text_field($data['yalidine_label'] ?? '');


        if (mb_strlen($flat_rate_label) == 0) {
            $flat_rate_label = __('Flat rate', 'woocommerce');
        }

        if (mb_strlen($pickup_local_label) == 0) {
            $pickup_local_label = __('Local Pickup', 'woocommerce');
        }

		if (mb_strlen($flexible_label) == 0) {
			$flexible_label = __('Flexible rate', 'woocommerce');
        }

		if (mb_strlen($economic_label) == 0) {
			$economic_label = __('Economic rate', 'woocommerce');
        }

		if (mb_strlen($zr_express_label) == 0) {
			$zr_express_label = __('ZR Express rate', 'woocommerce');
        }

		if (mb_strlen($maystro_label) == 0) {
			$maystro_label = __('Maystro rate', 'woocommerce');
        }

		if (mb_strlen($yalidine_label) == 0) {
			$yalidine_label = __('Yalidine rate', 'woocommerce');
        }

        $flat_rate_enabled = sanitize_text_field($data['flat_rate_enabled']) == "true";
        $pickup_local_enabled = sanitize_text_field($data['pickup_local_enabled']) == "true";
        $agency_import_enabled = isset($data['import_by_agency']) ? sanitize_text_field($data['import_by_agency']) == "true" : null;

        $is_economic = isset($data['is_economic']) ? sanitize_text_field($data['is_economic']) == "true" : null;
        $is_flexible = isset($data['is_flexible']) ? sanitize_text_field($data['is_flexible']) == "true" : null;
        $is_yalidine_price = isset($data['is_yalidine_price']) ? sanitize_text_field($data['is_yalidine_price']) == "true" : null;
        $is_maystro_price = isset($data['is_maystro_price']) ? sanitize_text_field($data['is_maystro_price']) == "true" : null;
        $is_zr_express_price = isset($data['is_zr_express_price']) ? sanitize_text_field($data['is_zr_express_price']) == "true" : null;

        $provider = sanitize_text_field($data['provider']);

        $providers = self::get_providers();
        $selectedProvider = $providers[$provider];

        $shipping_class = [];

        if (class_exists($selectedProvider['setting_class'])) {
            $class = new $selectedProvider['setting_class']($selectedProvider);
			if ($provider == 'zimoexpress') {
				$shipping_class = $class->import_shipping_class($flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled, $agency_import_enabled, $is_economic, $is_flexible, $is_yalidine_price, $is_maystro_price, $is_zr_express_price, $flexible_label, $economic_label, $zr_express_label, $maystro_label, $yalidine_label);
			} else {
				$shipping_class = $class->import_shipping_class($flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled, $agency_import_enabled, $is_economic);
			}
        }

        return $shipping_class;
    }


    /**
     * @param $provider
     * @param $data
     *
     * @return WP_REST_Response
     */
    public function webhook($provider, $data, $headers = [])
    {
	    error_log('Webhook called');

		if ($provider) {

			$providers = BordereauGeneratorProviders::get_providers();

			// Check if provider exists
			if (!isset($providers[$provider])) {
				return new WP_REST_Response(
					['error' => 'Invalid provider.'],
					400
				);
			}

			$provider_class = $providers[$provider];

			if (class_exists($provider_class['setting_class'])) {
				$class = new $provider_class['setting_class']($provider_class);
				if (method_exists($class, 'handle_webhook')) {
					return $class->handle_webhook($provider_class, $data, $headers);
				}
			}
		}
    }

    private function change_order_status_without_hook($get_id, $orderStatus)
    {
        // Check if the new status starts with 'wc-', append it if not
        if (strpos($orderStatus, 'wc-') === false) {
            $orderStatus = 'wc-' . $orderStatus;
        }

        // Update the post directly in the database
        wp_update_post(array(
            'ID' => $get_id,
            'post_status' => $orderStatus
        ));
    }
}