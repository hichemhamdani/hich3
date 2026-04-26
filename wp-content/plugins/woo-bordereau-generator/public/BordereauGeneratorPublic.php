<?php

namespace WooBordereauGenerator\Frontend;

use GuzzleHttp\Exception\RequestException;
use WC_Order_Item_Fee;
use WC_Product;
use WC_Shipping_Zone;
use WC_Shipping_Zones;
use WooBordereauGenerator\Admin\Partials\BordereauGeneratorProviders;
use WooBordereauGenerator\Admin\Shipping\YalidineRequest;
use WooBordereauGenerator\Functions;
use WooBordereauGenerator\Helpers;
use WooBordereauGenerator\Shippingmethods\NordOuestLocalPickup;

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    WC_Bordereau_Generator
 * @subpackage WC_Bordereau_Generator/public
 * @author     Boukraa Mohamed <bm2ilabs@gmail.com>
 */
class BordereauGeneratorPublic
{

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $plugin_name The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $version The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @param string $plugin_name The name of the plugin.
     * @param string $version The version of this plugin.
     * @since    1.0.0
     */
    public function __construct($plugin_name, $version)
    {

        $this->plugin_name = $plugin_name;
        $this->version = $version;

    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/wc-bordereau-generator.css', array(), $this->version, 'all');
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {
        // Enqueue the script first
        wp_enqueue_script(
                $this->plugin_name,
                trailingslashit(plugin_dir_url(__FILE__)) . 'js/wc-bordereau-generator-updated.js',
                ['jquery'],
                filemtime(trailingslashit(plugin_dir_path(__FILE__)) . 'js/wc-bordereau-generator-updated.js'),
                true
        );

        // Always localize wcBordereauData to prevent JS errors
        $only_yalidine = only_yalidine_is_enabled();
        $show_address = get_option('wc_bordreau_always-show-address') == "true";
        $city_optional = get_option('wc_bordreau_city-optional') == "true";

        wp_localize_script($this->plugin_name, 'wcBordereauData', [
                'settings' => [
                        'always_show_address' => $show_address,
                        'only_yalidine' => $only_yalidine,
                        'city_optional' => $city_optional
                ],
                'i18n' => [
                        'address' => __('Address', 'woocommerce') ,
                        'center' => __('Bureau de livraison', 'woo-bordereau-generator'),
                ],
        ]);

        if (is_ecotrack_providers_enabled()) {
            wp_enqueue_script(
                    $this->plugin_name,
                    trailingslashit(plugin_dir_url(__FILE__)) . 'js/wc-bordereau-generator-ecotrack.js',
                    ['jquery'],
                    filemtime(trailingslashit(plugin_dir_path(__FILE__)) . 'js/wc-bordereau-generator-ecotrack.js'),
                    true
            );
        }

        if (is_checkout()) {

            // get all the cities for each shipping company is enabled
            wp_enqueue_script(
                    $this->plugin_name,
                    trailingslashit(plugin_dir_url(__FILE__)) . 'js/wc-bordereau-generator-checkout-public.js',
                    ['jquery', 'selectWoo'],
                    filemtime(trailingslashit(plugin_dir_path(__FILE__)) . 'js/wc-bordereau-generator-checkout-public.js'),
                    true
            );

        }

        wp_enqueue_script($this->plugin_name.'_ecotrack', false, array('jquery'), '1.0', true);
        wp_add_inline_script($this->plugin_name.'_ecotrack', $this->get_script());
    }

    /**
     * Get the JavaScript for toggling additional information
     */
    private function get_script()
    {
        return "
        jQuery(function($) {
            console.log('here')
            function toggleEcotrackInfo() {
                $('input[name^=\"shipping_method\"]').each(function() {
                    var \$radio = $(this);
                    var \$additionalInfo = \$radio.closest('li').find('.boredreau-additional-info');

                    if (\$radio.is(':checked') && \$radio.attr('id').includes('local_pickup')) {
                        \$additionalInfo.slideDown();
                    } else {
                        \$additionalInfo.slideUp();
                    }
                });
            }

            $(document).on('change', 'input[name^=\"shipping_method\"]', toggleEcotrackInfo);
            $(document).on('updated_checkout', toggleEcotrackInfo);

            // Initial check on page load
            toggleEcotrackInfo();
        });
        ";
    }

    /**
     * @return void
     * @since 4.0.0
     */
    public function add_cities_to_fragments($arr)
    {
        global $woocommerce;

        $state = $woocommerce->cart->get_customer()->get_billing_state();
        $city = $woocommerce->cart->get_customer()->get_billing_city();

        $city = str_replace("\\'", "'", $city);

        $customer_data = WC()->session->get('customer');


        if(show_yalidine_centers() || show_zimoexpress_centers() || show_elogistia_centers()) {
            $center_select = '<div class="woocommerce-input-wrapper"><select name="billing_center" id="billing_center" class="select select2" data-allow_clear="true" data-placeholder="' . __('Sélectionnez un bureau', 'woo-bordereau-generator') . '" tabindex="-1" aria-hidden="true"></select></div>';
            $arr['#billing_center_field .woocommerce-input-wrapper'] = $center_select;
        }

        // $arr['#billing_center_field .woocommerce-input-wrapper'] = "";

        if ($city) {
            $customer_data['city'] = $city;
            $customer_data['shipping_city'] = $city;
            WC()->session->set('customer', $customer_data);
        } elseif (isset($customer_data['city']) && $customer_data['city']) {
            $city = $customer_data['city'];
        }

        $communes = Helpers::get_cities_by_provider();

        if(!$state) {
            $state = 'DZ-16';
        }

        $communes = $communes[$state];

        $options = '<option value="">' . __('Select City', 'woo-bordereau-generator') . '</option>';
        foreach ($communes as $key => $commune) {
            if ($city && $city === $commune) {
                $options .= '<option selected value="' . $commune . '">' . $commune . '</option>';
            } else {
                $options .= '<option value="' . $commune . '">' . stripslashes($commune) . '</option>';
            }
        }

        $city_select = '<div class="woocommerce-input-wrapper"><select name="billing_city" id="billing_city" class="select select2" data-allow_clear="true" data-placeholder="' . esc_attr__('Select a City', 'woo-bordereau-generator') . '" tabindex="-1" aria-hidden="true">' . $options . '</select></div>';
        $arr['#billing_city_field .woocommerce-input-wrapper'] = $city_select;



        $chosen_methods = $_POST['shipping_method'] ?? WC()->session->get('chosen_shipping_methods');

        if(is_array($chosen_methods)) {
            $chosen_methods = array_filter($chosen_methods);
        }

        // get the default state

        if(!$state) {
            $store_base_location = get_option('woocommerce_default_country');
            if($store_base_location) {
                list($default_country, $default_state) = explode(':', $store_base_location);
                $state = $default_state;
            }
        }


        if(! $chosen_methods) {
            $first_shipping = Helpers::get_available_shipping_method($state);
            $chosen_methods[0] = $first_shipping;
        }

        if (is_array($chosen_methods) && !empty($chosen_methods)) {

            $chosen_method = $chosen_methods[0];
            if (empty($chosen_method)) {
                return $arr;
            }

            $method_id_parts = explode(':', $chosen_method);

            if (count($method_id_parts) == 2) {
                $main_method_id = $method_id_parts[0];
                $instance_id = (int) $method_id_parts[1];
            } else {

                $main_method = Helpers::get_first_available_shipping_method();
                $method_id_parts = explode(':', $main_method);

                if (count($method_id_parts) == 2) {
                    $main_method_id = $method_id_parts[0];
                    $instance_id = (int) $method_id_parts[1];
                }
            }

            $shipping_zones = WC_Shipping_Zones::get_zones();
            $selected_shipping_method = null;
            foreach ($shipping_zones as $zone) {
                $shipping_methods = $zone['shipping_methods'];
                foreach ($shipping_methods as $int_id => $shipping_method) {

                    // Check if the current method ID matches
                    if ($shipping_method->instance_id == $instance_id) {
                        $selected_shipping_method = $shipping_method; // Return the matching zone
                        break;
                    }
                }
            }

            if ($selected_shipping_method) {

                $shipping_method_obj = $selected_shipping_method;
                $shipping_method_class = get_class($shipping_method_obj);

                $providers = BordereauGeneratorProviders::get_providers();

                if (!method_exists($shipping_method_obj, 'get_provider')) {
                    $default = 'default';

                    if (only_yalidine_is_enabled()) {
                        $default = 'yalidine';
                    }

                    $communes = Helpers::get_cities_by_provider($default);
                    $communes = $communes[$state];

                    $options = '<option value="">' . __('Select City', 'woo-bordereau-generator') . '</option>';
                    foreach ($communes as $key => $commune) {
                        if ($city && $city === $commune) {
                            $options .= '<option selected value="' . $commune . '">' . $commune . '</option>';
                        } else {
                            $options .= '<option value="' . $commune . '">' . $commune . '</option>';
                        }
                    }

                    if (only_yalidine_is_enabled()) {

                        $enabledProviders = $this->get_enabled_providers();
                        $provider = reset($enabledProviders);

                        $selected_provider = $providers[$provider];

                        if ($selected_provider) {
                            if (isset($selected_provider['setting_class'])) {
                                $settings_class = new $selected_provider['setting_class']($selected_provider);
                                $stop_desk = false;
                                if (str_contains($main_method_id, 'local_pickup')) {
                                    $stop_desk = true;
                                }

                                if ($stop_desk) {

                                    if (method_exists($settings_class, 'get_communes')) {
                                        $stop_desk = false;
                                        if (str_contains($main_method_id, 'local_pickup')) {
                                            $stop_desk = true;
                                        }
                                        $communes = [];
                                        try {
                                            $communes = $settings_class->get_communes($state, $stop_desk);
                                        } catch (\ErrorException $exception) {
                                            $communes = Helpers::get_cities_by_provider($default);
                                        }

                                        if (count($communes)) {
                                            $options = '<option value="">' . __('Select City', 'woo-bordereau-generator') . '</option>';
                                            foreach ($communes as $commune) {

                                                $enabled = true;
                                                if (isset($commune['enabled'])) {
                                                    $enabled = $commune['enabled'];
                                                }

                                                $disabled = !$enabled ? 'disabled="disabled"' : "";
                                                if ($city && $city === $commune['id']) {
                                                    $options .= '<option ' . $disabled . ' selected value="' . $commune['id'] . '">' . $commune['name'] . '</option>';
                                                } else {
                                                    $options .= '<option ' . $disabled . ' value="' . $commune['id'] . '">' . $commune['name'] . '</option>';
                                                }
                                            }
                                            $city_select = '<div class="woocommerce-input-wrapper"><select name="billing_city" id="billing_city" class="select select2" data-allow_clear="true" data-placeholder="' . esc_attr__('Select a City', 'woo-bordereau-generator') . '" tabindex="-1" aria-hidden="true">' . $options . '</select></div>';
                                            $arr['#billing_city_field .woocommerce-input-wrapper'] = $city_select;
                                        }
                                    }

                                    if(show_yalidine_centers()) {

                                        if (method_exists($settings_class, 'get_all_centers')) {
                                            $centers = $settings_class->get_all_centers();
                                            $filtered_centers = array_filter($centers, function ($item) use ($city) {
                                                return $item['commune_id'] == $city;
                                            });
                                            if (count($filtered_centers)) {
                                                $options = '<option value="">' . __('Select agency', 'woo-bordereau-generator') . '</option>';
                                                foreach ($filtered_centers as $center) {

                                                    if ($city && $city === $center['id']) {
                                                        $options .= '<option selected value="' . $center['id'] . '">' . $center['name'] . '</option>';
                                                    } else {
                                                        $options .= '<option value="' . $center['id'] . '">' . $center['name'] . '</option>';
                                                    }
                                                }
                                                unset($arr['#billing_center_field']);

                                                $center_select = '<div class="woocommerce-input-wrapper"><select name="billing_center" id="billing_center" class="select select2" data-allow_clear="true" data-placeholder="' . __('Sélectionnez un bureau', 'woo-bordereau-generator') . '" tabindex="-1" aria-hidden="true">' . $options . '</select></div>';
                                                $arr['#billing_center_field .woocommerce-input-wrapper'] = $center_select;
                                            }
                                        } else {
                                            $center_select = '<div class="woocommerce-input-wrapper"><select name="billing_center" id="billing_center" class="select select2" data-allow_clear="true" data-placeholder="' . __('Sélectionnez un bureau', 'woo-bordereau-generator') . '" tabindex="-1" aria-hidden="true"></select></div>';
                                            $arr['#billing_center_field .woocommerce-input-wrapper'] = $center_select;
                                        }
                                    }

                                    if(show_zimoexpress_centers()) {

                                        if (method_exists($settings_class, 'get_all_centers')) {
                                            $centers = $settings_class->get_all_centers();
                                            $filtered_centers = array_filter($centers, function ($item) use ($city) {
                                                return $item['commune_id'] == $city;
                                            });
                                            if (count($filtered_centers)) {
                                                $options = '<option value="">' . __('Select agency', 'woo-bordereau-generator') . '</option>';
                                                foreach ($filtered_centers as $center) {

                                                    if ($city && $city === $center['id']) {
                                                        $options .= '<option selected value="' . $center['id'] . '">' . $center['name'] . '</option>';
                                                    } else {
                                                        $options .= '<option value="' . $center['id'] . '">' . $center['name'] . '</option>';
                                                    }
                                                }
                                                unset($arr['#billing_center_field']);

                                                $center_select = '<div class="woocommerce-input-wrapper"><select name="billing_center" id="billing_center" class="select select2" data-allow_clear="true" data-placeholder="' . __('Sélectionnez un bureau', 'woo-bordereau-generator') . '" tabindex="-1" aria-hidden="true">' . $options . '</select></div>';
                                                $arr['#billing_center_field .woocommerce-input-wrapper'] = $center_select;
                                            }
                                        } else {
                                            $center_select = '<div class="woocommerce-input-wrapper"><select name="billing_center" id="billing_center" class="select select2" data-allow_clear="true" data-placeholder="' . __('Sélectionnez un bureau', 'woo-bordereau-generator') . '" tabindex="-1" aria-hidden="true"></select></div>';
                                            $arr['#billing_center_field .woocommerce-input-wrapper'] = $center_select;
                                        }
                                    }

                                }
                            }
                        }

                        if(! show_yalidine_centers() || ! show_zimoexpress_centers()) {
                            unset($arr['#billing_center_field .woocommerce-input-wrapper']);
                        }
                        return $arr;
                    }


                    $city_select = '<div class="woocommerce-input-wrapper"><select name="billing_city" id="billing_city" class="select select2" data-allow_clear="true" data-placeholder="' . esc_attr__('Select a City', 'woo-bordereau-generator') . '" tabindex="-1" aria-hidden="true">' . $options . '</select></div>';
                    $arr['#billing_city_field .woocommerce-input-wrapper'] = $city_select;

                    if(! show_yalidine_centers()) {
                        unset($arr['#billing_center_field .woocommerce-input-wrapper']);
                    }

                    return $arr;
                }

                $provider_selected = $shipping_method_obj->get_provider();

                if (str_contains($main_method_id, 'flat_rate')) {
                    $class_name = 'flat_rate_class';
                } else {
                    $class_name = 'local_pickup_class';
                }

                $selected_provider = null;

                if ($provider_selected) {
                    $selected_provider = $providers[$provider_selected];
                } else {
                    foreach ($providers as $provider) {
                        if ($provider[$class_name] === $shipping_method_class) {
                            $selected_provider = $provider;
                            break;
                        }
                    }
                }

                // Now check if the provider has 48 states or not
                $has_58_state = $shipping_method_obj->get_has_58_states();
                $method = $shipping_method_obj->get_provider();

                $states = $this->get_states(!$has_58_state, $method);

                if (count($states)) {

                    $options = '<option value="">' . __('Select state', 'woo-bordereau-generator') . '</option>';
                    foreach ($states as $key => $item) {
                        if ($key === $state) {
                            $options .= '<option selected value="' . $key . '">' . $item . '</option>';
                        } else {
                            $options .= '<option value="' . $key . '">' . $item . '</option>';
                        }
                    }
                    $states_select = '<div class="woocommerce-input-wrapper"><select name="billing_state" id="billing_state" class="select select2" data-allow_clear="true" data-placeholder="' . __('Select a state', 'woo-bordereau-generator') . '" tabindex="-1" aria-hidden="true">' . $options . '</select></div>';
                    $arr['#billing_state_field .woocommerce-input-wrapper'] = $states_select;
                }


                if ($selected_provider) {
                    // get communes from the provider
                    if (isset($selected_provider['setting_class'])) {
                        $settings_class = new $selected_provider['setting_class']($selected_provider);

                        $stop_desk = false;
                        if (str_contains($main_method_id, 'local_pickup')) {
                            $stop_desk = true;
                        }

                        // Check if ZR Express v2 local pickup - populate city select with centers instead of communes
                        $zrexpressv2_centers_in_city = false;
                        $isZRExpressV2LocalPickup = $selected_shipping_method instanceof \WooBordereauGenerator\Shippingmethods\ZRExpressTwoLocalPickup;

                        if ($stop_desk && $isZRExpressV2LocalPickup && method_exists($settings_class, 'get_all_centers')) {

                            $centers = $settings_class->get_all_centers();

                            // Filter centers by wilaya
                            $stateCode = (int) str_replace('DZ-', '', $state);

                            $filtered_centers = array_filter($centers, function($center) use ($stateCode) {
                                return isset($center['wilaya_code']) && (int) $center['wilaya_code'] == $stateCode;
                            });


                            if (count($filtered_centers)) {

                                $zrexpressv2_centers_in_city = true;

                                $options = '<option value="">' . __('Select agency', 'woo-bordereau-generator') . '</option>';
                                foreach ($filtered_centers as $center) {
                                    if ($city && $city === $center['id']) {
                                        $options .= '<option selected value="' . $center['id'] . '">' . $center['name'] . '</option>';
                                    } else {
                                        $options .= '<option value="' . $center['id'] . '">' . $center['name'] . '</option>';
                                    }
                                }

                                $city_select = '<div class="woocommerce-input-wrapper"><select name="billing_city" id="billing_city" class="select select2" data-allow_clear="true" data-placeholder="' . __('Select agency', 'woo-bordereau-generator') . '" tabindex="-1" aria-hidden="true">' . $options . '</select></div>';

                                $arr['#billing_city_field .woocommerce-input-wrapper'] = $city_select;

                                // Hide the separate center field since centers are now in city field
                                unset($arr['#billing_center_field']);
                                unset($arr['#billing_center_field .woocommerce-input-wrapper']);

                            }
                        }

                        // Check if Nord Ouest local pickup - populate city select with centers instead of communes
                        $noest_centers_in_city = false;
                        $isNordOuestLocalPickup = $selected_shipping_method instanceof \WooBordereauGenerator\Shippingmethods\NordOuestLocalPickup;

                        if ($stop_desk && $isNordOuestLocalPickup && method_exists($settings_class, 'get_all_centers')) {

                            $centers = $settings_class->get_all_centers();

                            // Filter centers by wilaya
                            $stateCode = (int) str_replace('DZ-', '', $state);

                            $filtered_centers = array_filter($centers, function($center) use ($stateCode) {
                                $id = (int) preg_replace('/([0-9]+)\w/', '$1', $center['code']);
                                return $id == $stateCode;
                            });

                            if (count($filtered_centers)) {

                                $noest_centers_in_city = true;

                                $options = '<option value="">' . __('Select agency', 'woo-bordereau-generator') . '</option>';
                                foreach ($filtered_centers as $center) {
                                    if ($city && $city === $center['code']) {
                                        $options .= '<option selected value="' . $center['code'] . '">' . $center['name'] . '</option>';
                                    } else {
                                        $options .= '<option value="' . $center['code'] . '">' . $center['name'] . '</option>';
                                    }
                                }

                                $city_select = '<div class="woocommerce-input-wrapper"><select name="billing_city" id="billing_city" class="select select2" data-allow_clear="true" data-placeholder="' . __('Select agency', 'woo-bordereau-generator') . '" tabindex="-1" aria-hidden="true">' . $options . '</select></div>';

                                $arr['#billing_city_field .woocommerce-input-wrapper'] = $city_select;

                                // Hide the separate center field since centers are now in city field
                                unset($arr['#billing_center_field']);
                                unset($arr['#billing_center_field .woocommerce-input-wrapper']);

                            }
                        }

                        // Check if TonColis API local pickup - populate city select with centers
                        $toncolis_api_centers_in_city = false;
                        $isTonColisApiLocalPickup = $selected_shipping_method instanceof \WooBordereauGenerator\Shippingmethods\TonColisLocalPickup
                            && in_array(
                                $shipping_method_obj->get_option('provider'),
                                ['toncolis_api', 'ecom_dz_new', 'flashfr']
                            );

                        if ($stop_desk && $isTonColisApiLocalPickup && method_exists($settings_class, 'get_all_centers')) {

                            $centers    = $settings_class->get_all_centers();
                            $stateCode  = (int) str_replace('DZ-', '', $state);

                            $filtered_centers = array_filter($centers, function ($center) use ($stateCode) {
                                return (int) $center['wilaya_id'] === $stateCode;
                            });

                            if (count($filtered_centers)) {

                                $toncolis_api_centers_in_city = true;

                                $options = '<option value="">' . __('Select agency', 'woo-bordereau-generator') . '</option>';
                                foreach ($filtered_centers as $center) {
                                    $selected = ($city && $city === $center['id']) ? ' selected' : '';
                                    $options .= '<option value="' . esc_attr($center['id']) . '"' . $selected . '>'
                                        . esc_html($center['name'])
                                        . '</option>';
                                }

                                $city_select = '<div class="woocommerce-input-wrapper"><select name="billing_city" id="billing_city" class="select select2" data-allow_clear="true" data-placeholder="' . esc_attr__('Select agency', 'woo-bordereau-generator') . '" tabindex="-1" aria-hidden="true">' . $options . '</select></div>';

                                $arr['#billing_city_field .woocommerce-input-wrapper'] = $city_select;

                                // Centers are in the city field — hide the separate center field
                                unset($arr['#billing_center_field']);
                                unset($arr['#billing_center_field .woocommerce-input-wrapper']);
                            }
                        }

                        // Check if Yalidine local pickup - populate city select with centers instead of communes
                        $yalidine_centers_in_city = false;
                        $isYalidineLocalPickup = $selected_shipping_method instanceof \WooBordereauGenerator\Shippingmethods\YalidineLocalPickup;

                        if ($stop_desk && $isYalidineLocalPickup && method_exists($settings_class, 'get_all_centers')) {

                            $centers = $settings_class->get_all_centers();

                            // Filter centers by wilaya
                            $stateCode = (int) str_replace('DZ-', '', $state);

                            $filtered_centers = array_filter($centers, function($center) use ($stateCode) {
                                return isset($center['wilaya_id']) && (int)$center['wilaya_id'] == $stateCode;
                            });

                            if (count($filtered_centers)) {

                                $yalidine_centers_in_city = true;

                                $options = '<option value="">' . __('Select agency', 'woo-bordereau-generator') . '</option>';
                                foreach ($filtered_centers as $center) {
                                    if ($city && $city === $center['id']) {
                                        $options .= '<option selected value="' . $center['id'] . '">' . $center['name'] . '</option>';
                                    } else {
                                        $options .= '<option value="' . $center['id'] . '">' . $center['name'] . '</option>';
                                    }
                                }

                                $city_select = '<div class="woocommerce-input-wrapper"><select name="billing_city" id="billing_city" class="select select2" data-allow_clear="true" data-placeholder="' . __('Select agency', 'woo-bordereau-generator') . '" tabindex="-1" aria-hidden="true">' . $options . '</select></div>';

                                $arr['#billing_city_field .woocommerce-input-wrapper'] = $city_select;

                                // Hide the separate center field since centers are now in city field
                                unset($arr['#billing_center_field']);
                                unset($arr['#billing_center_field .woocommerce-input-wrapper']);

                            }
                        }

                        // Check if Zimo Express local pickup - populate city select with centers instead of communes
                        $zimo_centers_in_city = false;
                        $isZimoLocalPickup = $selected_shipping_method instanceof \WooBordereauGenerator\Shippingmethods\ZimoExpressLocalPickup;

                        if ($stop_desk && $isZimoLocalPickup && method_exists($settings_class, 'get_all_centers')) {

                            $centers = $settings_class->get_all_centers();

                            // Filter centers by wilaya (using commune_id prefix or wilaya matching)
                            $stateCode = (int) str_replace('DZ-', '', $state);

                            $filtered_centers = array_filter($centers, function($center) use ($stateCode) {
                                return isset($center['wilaya_id']) && (int)$center['wilaya_id'] == $stateCode;
                            });

                            // Sort by city name (commune_name)
                            usort($filtered_centers, function($a, $b) {
                                return strcasecmp($a['commune_name'] ?? '', $b['commune_name'] ?? '');
                            });

                            if (count($filtered_centers)) {

                                $zimo_centers_in_city = true;

                                // Group centers by city name
                                $grouped_centers = [];
                                foreach ($filtered_centers as $center) {
                                    $city_name = $center['commune_name'] ?? __('Unknown', 'woo-bordereau-generator');
                                    if (!isset($grouped_centers[$city_name])) {
                                        $grouped_centers[$city_name] = [];
                                    }
                                    $grouped_centers[$city_name][] = $center;
                                }

                                $options = '<option value="">' . __('Select agency', 'woo-bordereau-generator') . '</option>';
                                foreach ($grouped_centers as $city_name => $city_centers) {
                                    $options .= '<optgroup label="' . esc_attr($city_name) . '">';
                                    foreach ($city_centers as $center) {
                                        // Display as "partner - address"
                                        $display_name = $center['name'] ?? '';
                                        if (!empty($center['address'])) {
                                            $display_name .= ' - ' . $center['address'];
                                        }
                                        
                                        // Get the stop desk price for dynamic pricing
                                        $stopdesk_price = isset($center['price']) ? esc_attr($center['price']) : '0';

                                        if ($city && $city === $center['code']) {
                                            $options .= '<option selected value="' . esc_attr($center['code']) . '" data-price="' . $stopdesk_price . '" data-provider="zimoexpress">' . esc_html($display_name) . '</option>';
                                        } else {
                                            $options .= '<option value="' . esc_attr($center['code']) . '" data-price="' . $stopdesk_price . '" data-provider="zimoexpress">' . esc_html($display_name) . '</option>';
                                        }
                                    }
                                    $options .= '</optgroup>';
                                }

                                $city_select = '<div class="woocommerce-input-wrapper"><select name="billing_city" id="billing_city" class="select select2 zimo-stopdesk-select" data-allow_clear="true" data-placeholder="' . __('Select agency', 'woo-bordereau-generator') . '" tabindex="-1" aria-hidden="true">' . $options . '</select></div>';

                                $arr['#billing_city_field .woocommerce-input-wrapper'] = $city_select;

                                // Hide the separate center field since centers are now in city field
                                unset($arr['#billing_center_field']);
                                unset($arr['#billing_center_field .woocommerce-input-wrapper']);

                            }
                        }

                        // Check if Near Delivery local pickup - populate city select with buralists grouped by city
                        $near_delivery_centers_in_city = false;
                        $isNearDeliveryLocalPickup = $selected_shipping_method instanceof \WooBordereauGenerator\Shippingmethods\NearDeliveryLocalPickup;

                        if ($stop_desk && $isNearDeliveryLocalPickup && method_exists($settings_class, 'get_all_centers')) {

                            $centers = $settings_class->get_all_centers();
                            $stateCode = (int) str_replace('DZ-', '', $state);

                            // Filter by wilaya_code
                            $filtered_centers = array_filter($centers, function($center) use ($stateCode) {
                                return isset($center['wilaya_code']) && (int)$center['wilaya_code'] == $stateCode;
                            });

                            // Sort by commune name
                            usort($filtered_centers, function($a, $b) {
                                return strcasecmp($a['commune_name'] ?? '', $b['commune_name'] ?? '');
                            });

                            if (count($filtered_centers)) {

                                $near_delivery_centers_in_city = true;

                                // Group by commune name
                                $grouped_centers = [];
                                foreach ($filtered_centers as $center) {
                                    $city_name = $center['commune_name'] ?? __('Unknown', 'woo-bordereau-generator');
                                    if (!isset($grouped_centers[$city_name])) {
                                        $grouped_centers[$city_name] = [];
                                    }
                                    $grouped_centers[$city_name][] = $center;
                                }

                                // Build optgroups
                                $options = '<option value="">' . __('Select pickup point', 'woo-bordereau-generator') . '</option>';
                                foreach ($grouped_centers as $city_name => $city_centers) {
                                    $options .= '<optgroup label="' . esc_attr($city_name) . '">';
                                    foreach ($city_centers as $center) {
                                        $display_name = $center['center_address'] ?? '';
                                        if (!empty($center['phone_number'])) {
                                            $display_name .= ' (' . $center['phone_number'] . ')';
                                        }
                                        $selected = ($city && $city == $center['id']) ? 'selected' : '';
                                        $options .= '<option ' . $selected . ' value="' . esc_attr($center['id']) . '">' . esc_html($display_name) . '</option>';
                                    }
                                    $options .= '</optgroup>';
                                }

                                $city_select = '<div class="woocommerce-input-wrapper"><select name="billing_city" id="billing_city" class="select select2" data-allow_clear="true" data-placeholder="' . __('Select pickup point', 'woo-bordereau-generator') . '" tabindex="-1" aria-hidden="true">' . $options . '</select></div>';

                                $arr['#billing_city_field .woocommerce-input-wrapper'] = $city_select;

                                // Hide the separate center field since centers are now in city field
                                unset($arr['#billing_center_field']);
                                unset($arr['#billing_center_field .woocommerce-input-wrapper']);

                            }
                        }

                        // Check if Yalitec (yalitec_new) local pickup - populate city select with centers instead of communes
                        $yalitec_centers_in_city = false;
                        $isYalitecLocalPickup = $selected_shipping_method instanceof \WooBordereauGenerator\Shippingmethods\YalitecLocalPickup;

                        if ($stop_desk && $isYalitecLocalPickup && method_exists($settings_class, 'get_all_centers')) {

                            $centers = $settings_class->get_all_centers();


                            // Filter centers by wilaya
                            $stateCode = (int) str_replace('DZ-', '', $state);

                            $filtered_centers = array_filter($centers, function($center) use ($stateCode) {
                                return isset($center['wilaya_id']) && (int)$center['wilaya_id'] == $stateCode;
                            });


                            if (count($filtered_centers)) {

                                $yalitec_centers_in_city = true;

                                $options = '<option value="">' . __('Select agency', 'woo-bordereau-generator') . '</option>';
                                foreach ($filtered_centers as $center) {
                                    if ($city && $city === $center['id']) {
                                        $options .= '<option selected value="' . $center['id'] . '">' . $center['name'] . '</option>';
                                    } else {
                                        $options .= '<option value="' . $center['id'] . '">' . $center['name'] . '</option>';
                                    }
                                }

                                $city_select = '<div class="woocommerce-input-wrapper"><select name="billing_city" id="billing_city" class="select select2" data-allow_clear="true" data-placeholder="' . __('Select agency', 'woo-bordereau-generator') . '" tabindex="-1" aria-hidden="true">' . $options . '</select></div>';

                                $arr['#billing_city_field .woocommerce-input-wrapper'] = $city_select;

                                // Hide the separate center field since centers are now in city field
                                unset($arr['#billing_center_field']);
                                unset($arr['#billing_center_field .woocommerce-input-wrapper']);

                            }
                        }

                        // Check if MDM Express local pickup - populate city select with centers instead of communes
                        $mdm_centers_in_city = false;
                        $isMdmLocalPickup = $selected_shipping_method instanceof \WooBordereauGenerator\Shippingmethods\MdmLocalPickup;

                        if ($stop_desk && $isMdmLocalPickup && method_exists($settings_class, 'get_all_centers')) {

                            $centers = $settings_class->get_all_centers();

                            // Filter centers by wilaya
                            $stateCode = (int) str_replace('DZ-', '', $state);

                            $filtered_centers = array_filter($centers, function($center) use ($stateCode) {
                                return isset($center['wilaya_id']) && (int)$center['wilaya_id'] == $stateCode;
                            });

                            if (count($filtered_centers)) {

                                $mdm_centers_in_city = true;

                                $options = '<option value="">' . __('Select agency', 'woo-bordereau-generator') . '</option>';
                                foreach ($filtered_centers as $center) {
                                    if ($city && $city === $center['id']) {
                                        $options .= '<option selected value="' . $center['id'] . '">' . $center['name'] . '</option>';
                                    } else {
                                        $options .= '<option value="' . $center['id'] . '">' . $center['name'] . '</option>';
                                    }
                                }

                                $city_select = '<div class="woocommerce-input-wrapper"><select name="billing_city" id="billing_city" class="select select2" data-allow_clear="true" data-placeholder="' . __('Select agency', 'woo-bordereau-generator') . '" tabindex="-1" aria-hidden="true">' . $options . '</select></div>';

                                $arr['#billing_city_field .woocommerce-input-wrapper'] = $city_select;

                                // Hide the separate center field since centers are now in city field
                                unset($arr['#billing_center_field']);
                                unset($arr['#billing_center_field .woocommerce-input-wrapper']);

                            }
                        }

                        // Check if Elogistia local pickup - populate city select with centers instead of communes
                        $elogistia_centers_in_city = false;
                        $isElogistiaLocalPickup = $selected_shipping_method instanceof \WooBordereauGenerator\Shippingmethods\ElogistiaLocalPickup;



                        if ($stop_desk && $isElogistiaLocalPickup && method_exists($settings_class, 'get_all_centers')) {

                            $centers = $settings_class->get_all_centers();

                            // Filter centers by wilaya
                            $stateCode = (int) str_replace('DZ-', '', $state);

                            $filtered_centers = array_filter($centers, function($center) use ($stateCode) {
                                return isset($center['wilaya_id']) && (int)$center['wilaya_id'] == $stateCode;
                            });

                            if (count($filtered_centers)) {

                                $elogistia_centers_in_city = true;

                                $options = '<option value="">' . __('Select agency', 'woo-bordereau-generator') . '</option>';
                                foreach ($filtered_centers as $center) {
                                    if ($city && $city === $center['id']) {
                                        $options .= '<option selected value="' . $center['id'] . '">' . $center['name'] . '</option>';
                                    } else {
                                        $options .= '<option value="' . $center['id'] . '">' . $center['name'] . '</option>';
                                    }
                                }

                                $city_select = '<div class="woocommerce-input-wrapper"><select name="billing_city" id="billing_city" class="select select2" data-allow_clear="true" data-placeholder="' . __('Select agency', 'woo-bordereau-generator') . '" tabindex="-1" aria-hidden="true">' . $options . '</select></div>';

                                $arr['#billing_city_field .woocommerce-input-wrapper'] = $city_select;

                                // Hide the separate center field since centers are now in city field
                                unset($arr['#billing_center_field']);
                                unset($arr['#billing_center_field .woocommerce-input-wrapper']);

                            }
                        }

                        // Only fetch communes if not using centers in city field
                        if (!$zrexpressv2_centers_in_city && !$noest_centers_in_city && !$toncolis_api_centers_in_city && !$yalidine_centers_in_city && !$zimo_centers_in_city && !$yalitec_centers_in_city && !$mdm_centers_in_city && !$near_delivery_centers_in_city && !$elogistia_centers_in_city && method_exists($settings_class, 'get_communes')) {

                            $communes = [];
                            try {
                                $communes = $settings_class->get_communes($state, $stop_desk);
                            } catch (\ErrorException $exception) {
                            }


                            if (count($communes)) {
                                $options = '<option value="">' . __('Select City', 'woo-bordereau-generator') . '</option>';
                                foreach ($communes as $commune) {

                                    $enabled = true;
                                    if (isset($commune['enabled'])) {
                                        $enabled = $commune['enabled'];
                                    }
                                    $disabled = !$enabled ? 'disabled="disabled"' : "";

                                    if ($city && $city === $commune['id']) {
                                        $options .= '<option ' . $disabled . ' selected value="' . $commune['id'] . '">' . $commune['name'] . '</option>';
                                    } else {
                                        $options .= '<option ' . $disabled . ' value="' . $commune['id'] . '">' . $commune['name'] . '</option>';
                                    }
                                }

                                $city_select = '<div class="woocommerce-input-wrapper"><select name="billing_city" id="billing_city" class="select select2" data-allow_clear="true" data-placeholder="' . esc_attr__('Select a City', 'woo-bordereau-generator') . '" tabindex="-1" aria-hidden="true">' . $options . '</select></div>';
                                $arr['#billing_city_field .woocommerce-input-wrapper'] = $city_select;

                                if ($stop_desk && show_yalidine_centers()) {
                                    $has_stop_desks = $shipping_method_obj->get_has_stop_desks();
                                    if (method_exists($settings_class, 'get_all_centers')) {
                                        $centers = $settings_class->get_all_centers();

                                        $filtered_centers = array_filter($centers, function ($item) use ($city) {

                                            if (empty($city)) { return false; }

                                            if (is_numeric($city)) {
                                                return $item['commune_id'] == $city;
                                            }

                                            return $item['commune_name'] == $city;
                                        });
                                        if (count($filtered_centers)) {
                                            $options = '<option value="">' . __('Select agency', 'woo-bordereau-generator') . '</option>';
                                            foreach ($filtered_centers as $center) {

                                                if(isset($center['id'])) {
                                                    if ($city && $city === $center['id']) {
                                                        $options .= '<option selected value="' . $center['id'] . '">' . $center['name'] . '</option>';
                                                    } else {
                                                        $options .= '<option value="' . $center['id'] . '">' . $center['name'] . '</option>';
                                                    }
                                                }

                                            }

                                            unset($arr['#billing_center_field']);
                                            $center_select = '<div class="woocommerce-input-wrapper"><select name="billing_center" id="billing_center" class="select select2" data-allow_clear="true" data-placeholder="' . __('Sélectionnez un bureau', 'woo-bordereau-generator') . '" tabindex="-1" aria-hidden="true">' . $options . '</select></div>';
                                            $arr['#billing_center_field .woocommerce-input-wrapper'] = $center_select;
                                        }
                                    }
                                }

                            } else {
                                $communes = Helpers::get_cities_by_provider($provider_selected);
                                $communes = $communes[$state];

                                $options = '<option value="">' . __('Select City', 'woo-bordereau-generator') . '</option>';
                                foreach ($communes as $key => $commune) {
                                    if ($city && $city === $commune) {
                                        $options .= '<option selected value="' . $commune . '">' . $commune . '</option>';
                                    } else {
                                        $options .= '<option value="' . $commune . '">' . $commune . '</option>';
                                    }
                                }

                                $city_select = '<div class="woocommerce-input-wrapper"><select name="billing_city" id="billing_city" class="select select2" data-allow_clear="true" data-placeholder="' . esc_attr__('Select a City', 'woo-bordereau-generator') . '" tabindex="-1" aria-hidden="true">' . $options . '</select></div>';
                                $arr['#billing_city_field .woocommerce-input-wrapper'] = $city_select;
                            }
                        } else {

                            if (!$zrexpressv2_centers_in_city && !$noest_centers_in_city && !$toncolis_api_centers_in_city && !$yalidine_centers_in_city && !$zimo_centers_in_city && !$yalitec_centers_in_city && !$mdm_centers_in_city && !$near_delivery_centers_in_city && !$elogistia_centers_in_city) {
                                $communes = Helpers::get_cities_by_provider($provider_selected);
                                $communes = $communes[$state];

                                $options = '<option value="">' . __('Select City', 'woo-bordereau-generator') . '</option>';
                                foreach ($communes as $key => $commune) {
                                    if ($city && $city === $commune) {
                                        $options .= '<option selected value="' . $commune . '">' . $commune . '</option>';
                                    } else {
                                        $options .= '<option value="' . $commune . '">' . $commune . '</option>';
                                    }
                                }

                                $city_select = '<div class="woocommerce-input-wrapper"><select name="billing_city" id="billing_city" class="select select2" data-allow_clear="true" data-placeholder="' . esc_attr__('Select a City', 'woo-bordereau-generator') . '" tabindex="-1" aria-hidden="true">' . $options . '</select></div>';
                                $arr['#billing_city_field .woocommerce-input-wrapper'] = $city_select;
                            }

                        }

                        $noestResultCheck = $selected_shipping_method instanceof \WooBordereauGenerator\Shippingmethods\NordOuestLocalPickup;

                        // Skip center field for Nord Ouest - centers are now in city field
                        if ($stop_desk && show_nord_ouest_centers() && $noestResultCheck && !$noest_centers_in_city) {
                            $has_stop_desks = $shipping_method_obj->get_has_stop_desks();
                            if (method_exists($settings_class, 'get_all_centers')) {
                                $centers = $settings_class->get_all_centers();

                                $filtered_centers = array_filter($centers, function($center) use ($state) {
                                    $id = (int) preg_replace('/([0-9]+)\w/', '$1', $center['code']);
                                    $stateCode = (int) str_replace('DZ-', '', $state);
                                    return $id == $stateCode;
                                });

                                if (count($filtered_centers)) {
                                    $options = '<option value="">' . __('Select agency', 'woo-bordereau-generator') . '</option>';
                                    foreach ($filtered_centers as $center) {
                                        $options .= '<option value="' . $center['code'] . '">' . $center['name'] . '</option>';
                                    }

                                    unset($arr['#billing_center_field']);
                                    $center_select = '<div class="woocommerce-input-wrapper"><select name="billing_center" id="billing_center" class="select select2" data-allow_clear="true" data-placeholder="' . __('Sélectionnez un bureau', 'woo-bordereau-generator') . '" tabindex="-1" aria-hidden="true">' . $options . '</select></div>';
                                    $arr['#billing_center_field .woocommerce-input-wrapper'] = $center_select;
                                }
                            }
                        }

                        $zrexpressv2ResultCheck = $selected_shipping_method instanceof \WooBordereauGenerator\Shippingmethods\ZRExpressTwoLocalPickup;

                        // Skip center field for ZR Express v2 - centers are now in city field
                        // This block is disabled since $zrexpressv2_centers_in_city handles it

                        $zimoResultCheck = $selected_shipping_method instanceof \WooBordereauGenerator\Shippingmethods\ZimoExpressLocalPickup;


                        if ($stop_desk && show_zimoexpress_centers() && $zimoResultCheck) {
                            $has_stop_desks = $shipping_method_obj->get_has_stop_desks();
                            if (method_exists($settings_class, 'get_all_centers')) {
                                $centers = $settings_class->get_all_centers();

                                $filtered_centers = array_filter($centers, function ($item) use ($city) {
                                    return $item['commune_id'] == $city;
                                });

                                if (count($filtered_centers)) {
                                    $options = '<option value="">' . __('Select agency', 'woo-bordereau-generator') . '</option>';
                                    foreach ($filtered_centers as $center) {
                                        $options .= '<option value="' . $center['code'] . '">' . $center['name'] . '</option>';
                                    }

                                    unset($arr['#billing_center_field']);
                                    $center_select = '<div class="woocommerce-input-wrapper"><select name="billing_center" id="billing_center" class="select select2" data-allow_clear="true" data-placeholder="' . __('Sélectionnez un bureau', 'woo-bordereau-generator') . '" tabindex="-1" aria-hidden="true">' . $options . '</select></div>';
                                    $arr['#billing_center_field .woocommerce-input-wrapper'] = $center_select;
                                }
                            }
                        }


                        $yalitecResultCheck = $selected_shipping_method instanceof \WooBordereauGenerator\Shippingmethods\YalitecLocalPickup;


                        if ($stop_desk && show_yalitec_centers() && $yalitecResultCheck) {

                            $has_stop_desks = $shipping_method_obj->get_has_stop_desks();


                            if (method_exists($settings_class, 'get_all_centers')) {

                                $centers = $settings_class->get_all_centers();

                                $filtered_centers = array_filter($centers, function ($item) use ($city) {
                                    return $item['commune_name'] === $city;
                                });


                                if (count($filtered_centers)) {
                                    $options = '<option value="">' . __('Select agency', 'woo-bordereau-generator') . '</option>';
                                    foreach ($filtered_centers as $center) {
                                        $options .= '<option value="' . $center['code'] . '">' . $center['name'] . '</option>';
                                    }

                                    unset($arr['#billing_center_field']);
                                    $center_select = '<div class="woocommerce-input-wrapper"><select name="billing_center" id="billing_center" class="select select2" data-allow_clear="true" data-placeholder="' . __('Sélectionnez un bureau', 'woo-bordereau-generator') . '" tabindex="-1" aria-hidden="true">' . $options . '</select></div>';
                                    $arr['#billing_center_field .woocommerce-input-wrapper'] = $center_select;
                                }
                            }
                        }

                        $mdmResultCheck = $selected_shipping_method instanceof \WooBordereauGenerator\Shippingmethods\MdmLocalPickup;

                        if ($stop_desk && show_mdm_centers() && $mdmResultCheck) {

                            $has_stop_desks = $shipping_method_obj->get_has_stop_desks();

                            if (method_exists($settings_class, 'get_all_centers')) {

                                $centers = $settings_class->get_all_centers();

                                $filtered_centers = array_filter($centers, function ($item) use ($city) {
                                    return $item['commune_name'] === $city;
                                });

                                if (count($filtered_centers)) {
                                    $options = '<option value="">' . __('Select agency', 'woo-bordereau-generator') . '</option>';
                                    foreach ($filtered_centers as $center) {
                                        $options .= '<option value="' . $center['code'] . '">' . $center['name'] . '</option>';
                                    }

                                    unset($arr['#billing_center_field']);
                                    $center_select = '<div class="woocommerce-input-wrapper"><select name="billing_center" id="billing_center" class="select select2" data-allow_clear="true" data-placeholder="' . __('Sélectionnez un bureau', 'woo-bordereau-generator') . '" tabindex="-1" aria-hidden="true">' . $options . '</select></div>';
                                    $arr['#billing_center_field .woocommerce-input-wrapper'] = $center_select;
                                }
                            }
                        }
                    }
                }
            }
        }

        if(! show_yalidine_centers() && ! show_nord_ouest_centers() && ! show_toncolis_api_centers() && ! show_zimoexpress_centers() && ! show_yalitec_centers() && ! show_mdm_centers() && ! show_zrexpress_v2_centers() && ! show_elogistia_centers()) {
            unset($arr['#billing_center_field .woocommerce-input-wrapper']);
        }

        return $arr;
    }


    /**
     * Modify billing field
     * @param mixed $fields
     * @param mixed $country
     * @return mixed
     */
    public function wc_billing_fields($fields, $country)
    {
        $fields['billing_city']['type'] = 'select';

        return $fields;
    }


    /**
     * @param $fields
     * @return array
     */
    public function custom_checkout_field($fields)
    {
        /**
         * unset($fields['billing']['billing_city']);
         * unset($fields['shipping']['shipping_city']);
         * unset($fields['billing']['billing_postcode']);
         * unset($fields['shipping']['shipping_postcode']);
         * unset($fields['billing']['billing_company']);
         * unset($fields['shipping']['shipping_company']);
         * unset($fields['shipping']['shipping_email']);
         * unset($fields['billing']['billing_email']);
         * unset($fields['billing']['billing_address_1']);
         * unset($fields['shipping']['shipping_address_1']);
         **/

        // Check if city should be optional
        $city_optional = get_option('wc_bordreau_city-optional', 'false') === 'true';
        $city_required = !$city_optional;

        $fields['shipping']['shipping_city'] = array(
                'label' => __('City', 'woocommerce'),
                'placeholder' => $fields['shipping']['shipping_city']['placeholder'] ?? __('Select a City', 'woo-bordereau-generator'),
                'required' => $city_required,
                'class' => array('form-row-wide'),
                'clear' => true,
                'type' => 'select',
                'options' => array('' => __('Please select a city', 'woo-bordereau-generator')), // Placeholder option
                'priority' => 85, // Updated priority
        );

        $fields['billing']['billing_city'] = array(
                'label' => __('City', 'woocommerce'),
                'placeholder' => $fields['billing']['billing_city']['placeholder'] ?? __('Select a City', 'woo-bordereau-generator'),
                'required' => $city_required,
                'class' => array('form-row-wide'),
                'clear' => true,
                'type' => 'select',
                'options' => array('' => __('Please select a city', 'woo-bordereau-generator')), // Placeholder option
        );

//        if (show_yalidine_centers() || show_nord_ouest_centers()|| show_zimoexpress_centers() || show_zrexpress_v2_centers()) {
//            $fields['billing']['billing_center'] = array(
//                    'label' => __('Bureau de livraison', 'woo-bordereau-generator'),
//                    'placeholder' => _x('Select a Center', 'placeholder', 'woocommerce'),
//                    'required' => false,
//                    'class' => array('form-row-wide'),
//                    'clear' => true,
//                    'type' => 'select',
//                    'options' => array('' => __('Please select a center', 'woo-bordereau-generator')), // Placeholder option
//            );
//            $fields['billing']['billing_center']['priority'] = 84;
//        }

        $fields['billing']['billing_state']['priority'] = 81;
        $fields['billing']['billing_city']['priority'] = 82;

        $fields['shipping']['shipping_state']['priority'] = 81;
        $fields['shipping']['shipping_city']['priority'] = 82;

        // Make billing_address_1 not required for local pickup shipping methods
        $show_address = get_option('wc_bordreau_always-show-address') == "true";
        if (!$show_address) {
            $chosen_methods = WC()->session ? WC()->session->get('chosen_shipping_methods') : array();
            $chosen_method = !empty($chosen_methods) ? $chosen_methods[0] : '';
            
            if ($chosen_method && str_contains($chosen_method, 'local_pickup')) {
                $fields['billing']['billing_address_1']['required'] = false;
                // Remove validate-required from class array if present
                if (isset($fields['billing']['billing_address_1']['class'])) {
                    $fields['billing']['billing_address_1']['class'] = array_filter(
                        $fields['billing']['billing_address_1']['class'],
                        function($class) {
                            return $class !== 'validate-required';
                        }
                    );
                }
            }
        }

        return $fields;
    }

    /**
     * Modify shipping field
     * @param mixed $fields
     * @param mixed $country
     * @return mixed
     */
    public function wc_shipping_fields($fields, $country)
    {
        $fields['shipping_city']['type'] = 'select';

        return $fields;
    }

    /**
     * Implement places/city field
     * @param mixed $field
     * @param string $key
     * @param mixed $args
     * @param string $value
     * @return mixed
     */
    public function wc_form_field_city($field, $key, $args, $value)
    {
        // Do we need a clear div?
        if ((!empty($args['clear']))) {
            $after = '<div class="clear"></div>';
        } else {
            $after = '';
        }

        // Required markup
        if ($args['required']) {
            $args['class'][] = 'validate-required';
            $required = ' <abbr class="required" title="' . esc_attr__('required', 'woocommerce') . '">*</abbr>';
        } else {
            $required = '';
        }

        // Custom attribute handling
        $custom_attributes = array();

        if (!empty($args['custom_attributes']) && is_array($args['custom_attributes'])) {
            foreach ($args['custom_attributes'] as $attribute => $attribute_value) {
                $custom_attributes[] = esc_attr($attribute) . '="' . esc_attr($attribute_value) . '"';
            }
        }

        // Validate classes
        if (!empty($args['validate'])) {
            foreach ($args['validate'] as $validate) {
                $args['class'][] = 'validate-' . $validate;
            }
        }

        // field p and label
        $field = '<p class="form-row ' . esc_attr(implode(' ', $args['class'])) . '" id="' . esc_attr($args['id']) . '_field">';
        if ($args['label']) {
            $field .= '<label for="' . esc_attr($args['id']) . '" class="' . esc_attr(implode(' ', $args['label_class'])) . '">' . $args['label'] . $required . '</label>';
        }

        // Get Country
        $country_key = $key == 'billing_city' ? 'billing_country' : 'shipping_country';
        $current_cc = WC()->checkout->get_value($country_key);

        $state_key = $key == 'billing_city' ? 'billing_state' : 'shipping_state';
        $current_sc = WC()->checkout->get_value($state_key);

        // Get country places
        $places = $this->get_places($current_cc);

        if (is_array($places)) {

            $field .= '<select name="' . esc_attr($key) . '" id="' . esc_attr($args['id']) . '" class="city_select ' . esc_attr(implode(' ', $args['input_class'])) . '" ' . implode(' ', $custom_attributes) . ' placeholder="' . esc_attr($args['placeholder']) . '">';

            $field .= '<option value="">' . __('Select an option&hellip;', 'woocommerce') . '</option>';

            if ($current_sc && array_key_exists($current_sc, $places)) {
                $dropdown_places = $places[$current_sc];
            } else if (is_array($places) && isset($places[0])) {
                $dropdown_places = $places;
                sort($dropdown_places);
            } else {
                $dropdown_places = $places;
            }

            foreach ($dropdown_places as $city_name) {
                if (!is_array($city_name)) {
                    $field .= '<option value="' . esc_attr($city_name) . '" ' . selected($value, $city_name, false) . '>' . $city_name . '</option>';
                }
            }

            $field .= '</select>';

        } else {

            $field .= '<input type="text" class="input-text ' . esc_attr(implode(' ', $args['input_class'])) . '" value="' . esc_attr($value) . '"  placeholder="' . esc_attr($args['placeholder']) . '" name="' . esc_attr($key) . '" id="' . esc_attr($args['id']) . '" ' . implode(' ', $custom_attributes) . ' />';
        }

        // field description and close wrapper
        if ($args['description']) {
            $field .= '<span class="description">' . esc_attr($args['description']) . '</span>';
        }

        $field .= '</p>' . $after;

        return $field;
    }


    /**
     * @param $fields
     * @return array
     */
    public function custom_add_center_field($fields)
    {

        /**
         * unset($fields['billing']['billing_city']);
         * unset($fields['shipping']['shipping_city']);
         * unset($fields['billing']['billing_country']);
         * unset($fields['shipping']['shipping_country']);
         * unset($fields['billing']['billing_postcode']);
         * unset($fields['shipping']['shipping_postcode']);
         * unset($fields['billing']['billing_company']);
         * unset($fields['shipping']['shipping_company']);
         * unset($fields['shipping']['shipping_email']);
         * unset($fields['billing']['billing_email']);
         * unset($fields['billing']['billing_address_1']);
         * unset($fields['shipping']['shipping_address_1']);
         */

        $fields['shipping']['shipping_city'] = array(
                'label' => __('City', 'woo-bordereau-generator'),
                'placeholder' => _x('Select a City', 'placeholder', 'woocommerce'),
                'required' => true,
                'class' => array('form-row-wide'),
                'clear' => true,
                'type' => 'select',
                'options' => array('' => __('Please select a city', 'woo-bordereau-generator')), // Placeholder option
                'priority' => 81, // Updated priority
        );

        $fields['billing']['billing_city'] = array(
                'label' => __('City', 'woo-bordereau-generator'),
                'placeholder' => _x('Select a City', 'placeholder', 'woocommerce'),
                'required' => true,
                'class' => array('form-row-wide'),
                'clear' => true,
                'type' => 'select',
                'options' => array('' => __('Please select a city', 'woo-bordereau-generator')), // Placeholder option
                'priority' => 81, // Updated priority
        );

//        if (show_yalidine_centers() || show_nord_ouest_centers() || show_zimoexpress_centers() || show_yalitec_centers() || show_zrexpress_v2_centers()) {
//            $fields['billing']['billing_center'] = array(
//                    'label' => __('Bureau de livraison', 'woo-bordereau-generator'),
//                    'placeholder' => _x('Select a Center', 'placeholder', 'woocommerce'),
//                    'required' => false,
//                    'class' => array('form-row-wide'),
//                    'clear' => true,
//                    'type' => 'select',
//                    'options' => array('' => __('Please select a center', 'woo-bordereau-generator')), // Placeholder option
//                    'priority' => 82, // Updated priority
//            );
//        }

        return $fields;
    }


    /**
     * @param $order_id
     * @return void
     */
    function save_center_id($order_id)
    {

        if (!empty($_POST['billing_center'])) {

            $order = wc_get_order($order_id);

            if ($order) {
                $order->add_meta_data('_billing_center_id', sanitize_text_field($_POST['billing_center']));
            }

            update_post_meta($order_id, '_billing_center_id', sanitize_text_field($_POST['billing_center']));
        }
    }

    /**
     * Save the billing center
     * @param $order
     * @param $data
     * @return void
     */
    function save_billing_center($order, $data)
    {
        if (!empty($_POST['billing_center'])) {

            if ($order) {
                $order->add_meta_data('_billing_center_id', sanitize_text_field($_POST['billing_center']));
            }

            update_post_meta($order->get_id(), '_billing_center_id', sanitize_text_field($_POST['billing_center']));
        }

        // Handle ZR Express v2 local pickup - billing_city contains center ID, need to get actual city
        $chosen_methods = WC()->session->get('chosen_shipping_methods');
        if (is_array($chosen_methods) && !empty($chosen_methods[0]) && str_contains($chosen_methods[0], 'local_pickup_zrexpress_v2')) {
            $billing_city = isset($_POST['billing_city']) ? sanitize_text_field($_POST['billing_city']) : '';

            if ($billing_city && $order) {
                // billing_city contains the center ID, look up the center to get the commune name
                $providers = \WooBordereauGenerator\Admin\Partials\BordereauGeneratorProviders::get_providers();
                if (isset($providers['zrexpress_v2']) && isset($providers['zrexpress_v2']['setting_class'])) {
                    $settings_class = new $providers['zrexpress_v2']['setting_class']($providers['zrexpress_v2']);

                    if (method_exists($settings_class, 'get_all_centers')) {
                        $centers_data = $settings_class->get_all_centers();
                        $centers = $centers_data['items'] ?? $centers_data;

                        // Find the center by ID
                        $found_center = null;
                        foreach ($centers as $center) {
                            if ($center['id'] === $billing_city) {
                                $found_center = $center;
                                break;
                            }
                        }

                        if ($found_center) {
                            // Get the commune/city name from the center
                            $commune_name = $found_center['address']['district'] ?? ($found_center['commune_name'] ?? '');

                            if ($commune_name) {
                                // Update billing and shipping city with the actual commune name
                                $order->set_billing_city($commune_name);
                                $order->set_shipping_city($commune_name);
                            }

                            // Save center ID to meta
                            $order->add_meta_data('_billing_center_id', $billing_city, true);
                            $order->add_meta_data('_zrexpress_v2_center_name', $found_center['name'] ?? '', true);
                        }
                    }
                }
            }
        }

        // Handle generic local_pickup in hanout mode - detect provider from instance options
        // Multiple providers (Zimo Express, ZR Express v2, etc.) use generic local_pickup when hanout is enabled
        if (is_array($chosen_methods) && !empty($chosen_methods[0]) && str_contains($chosen_methods[0], 'local_pickup') && !str_contains($chosen_methods[0], 'local_pickup_')) {

            // Extract instance_id from "local_pickup:XX" format
            $method_parts = explode(':', $chosen_methods[0]);
            $instance_id = isset($method_parts[1]) ? (int) $method_parts[1] : 0;

            $provider_key = '';
            $shipping_method_instance = null;

            if ($instance_id) {
                $shipping_method_instance = \WC_Shipping_Zones::get_shipping_method($instance_id);
                if ($shipping_method_instance) {
                    $provider_key = $shipping_method_instance->get_option('provider', '');
                }
            }

            $providers = \WooBordereauGenerator\Admin\Partials\BordereauGeneratorProviders::get_providers();

            // --- Zimo Express hanout ---
            if ($provider_key === 'zimoexpress' && $order) {
                if (isset($providers['zimoexpress']['setting_class'])) {
                    $settings_class = new $providers['zimoexpress']['setting_class']($providers['zimoexpress']);

                    if (method_exists($settings_class, 'get_all_centers')) {
                        $centers = $settings_class->get_all_centers();
                        $found_center = null;

                        // Try 1: center_id from method options (agency import mode)
                        // When agency import is enabled, each local_pickup stores center_id in its options
                        $center_id = $shipping_method_instance ? $shipping_method_instance->get_option('center_id', '') : '';
                        if ($center_id) {
                            foreach ($centers as $center) {
                                if ($center['code'] == $center_id) {
                                    $found_center = $center;
                                    break;
                                }
                            }
                        }

                        // Try 2: billing_city contains center code (non-agency mode, dropdown at checkout)
                        if (!$found_center) {
                            $billing_city = isset($_POST['billing_city']) ? sanitize_text_field($_POST['billing_city']) : '';
                            if ($billing_city) {
                                foreach ($centers as $center) {
                                    if ($center['code'] == $billing_city) {
                                        $found_center = $center;
                                        break;
                                    }
                                }
                            }
                        }

                        // Try 3: match by shipping method title (agency import fallback)
                        // Title format: "[prefix] Partner - CityName"
                        if (!$found_center) {
                            $order_shipping_methods = $order->get_shipping_methods();
                            $shipping_method_title = '';
                            foreach ($order_shipping_methods as $sm) {
                                $shipping_method_title = $sm->get_method_title();
                                break;
                            }
                            if ($shipping_method_title) {
                                $prefix = get_option('zimoexpress_pickup_local_label', '');
                                foreach ($centers as $center) {
                                    $expectedName = trim(sprintf("%s %s", $prefix, $center['name']));
                                    if ($expectedName === trim($shipping_method_title)) {
                                        $found_center = $center;
                                        break;
                                    }
                                }
                            }
                        }

                        if ($found_center) {
                            $commune_name = $found_center['commune_name'] ?? '';
                            if ($commune_name) {
                                $order->set_billing_city($commune_name);
                                $order->set_shipping_city($commune_name);
                            }
                            $order->add_meta_data('_billing_center_id', $found_center['code'] ?? '', true);
                            $order->add_meta_data('_zimo_center_name', $found_center['name'] ?? '', true);
                        }
                    }
                }
            }

            // --- ZR Express v2 hanout (or fallback when provider is unknown for backward compat) ---
            elseif (($provider_key === 'zrexpress_v2' || !$provider_key) && $order) {
                if (isset($providers['zrexpress_v2']['setting_class'])) {
                    $settings_class = new $providers['zrexpress_v2']['setting_class']($providers['zrexpress_v2']);

                    if (method_exists($settings_class, 'get_all_centers')) {
                        $centers_data = $settings_class->get_all_centers();
                        $centers = $centers_data['items'] ?? $centers_data;
                        $prefix = get_option('zrexpress_v2_pickup_local_label');

                        // Get shipping method title from order
                        $order_shipping_methods = $order->get_shipping_methods();
                        $shipping_method_title = '';
                        foreach ($order_shipping_methods as $sm) {
                            $shipping_method_title = $sm->get_method_title();
                            break;
                        }

                        if ($shipping_method_title && $centers) {
                            // Find center by matching "[prefix] [center_name]" format
                            $found_center = null;
                            foreach ($centers as $center) {
                                $expectedName = sprintf("%s %s", $prefix, $center['name']);
                                if (trim($expectedName) === trim($shipping_method_title)) {
                                    $found_center = $center;
                                    break;
                                }
                            }

                            if ($found_center) {
                                // Get the commune/city name from the center
                                $commune_name = $found_center['address']['district'] ?? ($found_center['commune_name'] ?? '');

                                if ($commune_name) {
                                    $order->set_billing_city($commune_name);
                                    $order->set_shipping_city($commune_name);
                                }

                                // Save center ID to meta
                                $order->add_meta_data('_billing_center_id', $found_center['id'], true);
                                $order->add_meta_data('_zrexpress_v2_center_name', $found_center['name'] ?? '', true);
                            }
                        }
                    }
                }
            }
        }

        // Handle Nord Ouest local pickup - billing_city contains center code, need to get actual city
        if (is_array($chosen_methods) && !empty($chosen_methods[0]) && str_contains($chosen_methods[0], 'local_pickup_noest')) {
            $billing_city = isset($_POST['billing_city']) ? sanitize_text_field($_POST['billing_city']) : '';

            if ($billing_city && $order) {
                // billing_city contains the center code, look up the center to get the commune name
                $providers = \WooBordereauGenerator\Admin\Partials\BordereauGeneratorProviders::get_providers();
                if (isset($providers['nord_ouest']) && isset($providers['nord_ouest']['setting_class'])) {
                    $settings_class = new $providers['nord_ouest']['setting_class']($providers['nord_ouest']);

                    if (method_exists($settings_class, 'get_all_centers')) {
                        $centers = $settings_class->get_all_centers();

                        // Find the center by code
                        $found_center = null;
                        foreach ($centers as $center) {
                            if ($center['code'] === $billing_city) {
                                $found_center = $center;
                                break;
                            }
                        }

                        if ($found_center) {
                            // Get the commune/city name from the center
                            // Format: "Laghouat «Aflou»" - extract "Aflou" from between « »
                            $center_name = $found_center['name'] ?? '';
                            if (preg_match('/«(.+?)»/', $center_name, $matches)) {
                                $commune_name = $matches[1];
                            } else {
                                $commune_name = $center_name;
                            }

                            if ($commune_name) {
                                // Update billing and shipping city with the actual commune name
                                $order->set_billing_city($commune_name);
                                $order->set_shipping_city($commune_name);
                            }

                            // Save center code to meta
                            $order->add_meta_data('_billing_center_id', $billing_city, true);
                            $order->add_meta_data('_nord_ouest_center_name', $found_center['name'] ?? '', true);
                        }
                    }
                }
            }
        }

        // Handle Yalidine local pickup - billing_city contains center id, need to get actual city
        if (is_array($chosen_methods) && !empty($chosen_methods[0]) && str_contains($chosen_methods[0], 'local_pickup_yalidine')) {
            $billing_city = isset($_POST['billing_city']) ? sanitize_text_field($_POST['billing_city']) : '';

            if ($billing_city && $order) {
                // billing_city contains the center id, look up the center to get the commune name
                $providers = \WooBordereauGenerator\Admin\Partials\BordereauGeneratorProviders::get_providers();
                if (isset($providers['yalidine']) && isset($providers['yalidine']['setting_class'])) {
                    $settings_class = new $providers['yalidine']['setting_class']($providers['yalidine']);

                    if (method_exists($settings_class, 'get_all_centers')) {
                        $centers = $settings_class->get_all_centers();



                        // Find the center by id
                        $found_center = null;
                        foreach ($centers as $center) {

                            if ($center['id'] == $billing_city) {
                                $found_center = $center;
                                break;
                            }
                        }

                        if ($found_center) {

                            // Get the commune/city name from the center
                            $commune_name = $found_center['commune_name'] ?? '';

                            if ($commune_name) {
                                // Update billing and shipping city with the actual commune name
                                $order->set_billing_city($commune_name);
                                $order->set_shipping_city($commune_name);
                            }

                            // Save center id to meta
                            $order->add_meta_data('_billing_center_id', $billing_city, true);
                            $order->add_meta_data('_yalidine_center_name', $found_center['name'] ?? '', true);
                        }
                    }
                }
            }
        }

        // Handle Zimo Express local pickup - resolve city from center
        if (is_array($chosen_methods) && !empty($chosen_methods[0]) && str_contains($chosen_methods[0], 'local_pickup_zimoexpress') && $order) {
            $billing_city = isset($_POST['billing_city']) ? sanitize_text_field($_POST['billing_city']) : '';

            $providers = \WooBordereauGenerator\Admin\Partials\BordereauGeneratorProviders::get_providers();
            if (isset($providers['zimoexpress']) && isset($providers['zimoexpress']['setting_class'])) {
                $settings_class = new $providers['zimoexpress']['setting_class']($providers['zimoexpress']);

                if (method_exists($settings_class, 'get_all_centers')) {
                    $centers = $settings_class->get_all_centers();
                    $found_center = null;

                    // Try 1: billing_city contains center code (non-agency mode, dropdown at checkout)
                    if ($billing_city) {
                        foreach ($centers as $center) {
                            if ($center['code'] == $billing_city) {
                                $found_center = $center;
                                break;
                            }
                        }
                    }

                    // Try 2: center_id from shipping method instance options (agency import mode)
                    if (!$found_center) {
                        $method_parts = explode(':', $chosen_methods[0]);
                        $instance_id = isset($method_parts[1]) ? (int) $method_parts[1] : 0;
                        if ($instance_id) {
                            $shipping_method_instance = \WC_Shipping_Zones::get_shipping_method($instance_id);
                            if ($shipping_method_instance) {
                                $center_id = $shipping_method_instance->get_option('center_id', '');
                                if ($center_id) {
                                    foreach ($centers as $center) {
                                        if ($center['code'] == $center_id) {
                                            $found_center = $center;
                                            break;
                                        }
                                    }
                                }
                            }
                        }
                    }

                    if ($found_center) {
                        // Get the commune/city name from the center
                        $commune_name = $found_center['commune_name'] ?? '';

                        if ($commune_name) {
                            // Update billing and shipping city with the actual commune name
                            $order->set_billing_city($commune_name);
                            $order->set_shipping_city($commune_name);
                        }

                        // Save center code to meta
                        $order->add_meta_data('_billing_center_id', $found_center['code'] ?? '', true);
                        $order->add_meta_data('_zimo_center_name', $found_center['name'] ?? '', true);
                    }
                }
            }
        }

        // Handle Yalitec local pickup - billing_city contains center id, need to get actual city
        if (is_array($chosen_methods) && !empty($chosen_methods[0]) && str_contains($chosen_methods[0], 'local_pickup_yalitec_new')) {
            $billing_city = isset($_POST['billing_city']) ? sanitize_text_field($_POST['billing_city']) : '';

            if ($billing_city && $order) {
                // billing_city contains the center id, look up the center to get the commune name
                $providers = \WooBordereauGenerator\Admin\Partials\BordereauGeneratorProviders::get_providers();
                if (isset($providers['yalitec_new']) && isset($providers['yalitec_new']['setting_class'])) {
                    $settings_class = new $providers['yalitec_new']['setting_class']($providers['yalitec_new']);

                    if (method_exists($settings_class, 'get_all_centers')) {
                        $centers = $settings_class->get_all_centers();

                        // Find the center by id
                        $found_center = null;
                        foreach ($centers as $center) {
                            if ($center['id'] == $billing_city) {
                                $found_center = $center;
                                break;
                            }
                        }

                        if ($found_center) {
                            // Get the commune/city name from the center
                            $commune_name = $found_center['commune_name'] ?? '';

                            if ($commune_name) {
                                // Update billing and shipping city with the actual commune name
                                $order->set_billing_city($commune_name);
                                $order->set_shipping_city($commune_name);
                            }

                            // Save center id to meta
                            $order->add_meta_data('_billing_center_id', $billing_city, true);
                            $order->add_meta_data('_yalitec_center_name', $found_center['name'] ?? '', true);
                        }
                    }
                }
            }
        }

        // Handle MDM Express local pickup - billing_city contains center id, need to get actual city
        if (is_array($chosen_methods) && !empty($chosen_methods[0]) && str_contains($chosen_methods[0], 'local_pickup_mdm')) {
            $billing_city = isset($_POST['billing_city']) ? sanitize_text_field($_POST['billing_city']) : '';

            if ($billing_city && $order) {
                // billing_city contains the center id, look up the center to get the commune name
                $providers = \WooBordereauGenerator\Admin\Partials\BordereauGeneratorProviders::get_providers();
                if (isset($providers['mdm']) && isset($providers['mdm']['setting_class'])) {
                    $settings_class = new $providers['mdm']['setting_class']($providers['mdm']);

                    if (method_exists($settings_class, 'get_all_centers')) {
                        $centers = $settings_class->get_all_centers();

                        // Find the center by id
                        $found_center = null;
                        foreach ($centers as $center) {
                            if ($center['id'] == $billing_city) {
                                $found_center = $center;
                                break;
                            }
                        }

                        if ($found_center) {
                            $commune_name = $found_center['commune_name'] ?? '';

                            if ($commune_name) {
                                $order->set_billing_city($commune_name);
                                $order->set_shipping_city($commune_name);
                            }

                            $order->add_meta_data('_billing_center_id', $billing_city, true);
                            $order->add_meta_data('_mdm_center_name', $found_center['name'] ?? '', true);
                        }
                    }
                }
            }
        }

        // Handle Elogistia local pickup - billing_city contains center name, need to get actual city
        if (is_array($chosen_methods) && !empty($chosen_methods[0]) && str_contains($chosen_methods[0], 'local_pickup_elogistia')) {
            $billing_city = isset($_POST['billing_city']) ? sanitize_text_field($_POST['billing_city']) : '';

            if ($billing_city && $order) {
                // billing_city contains the center name (Nom du bureau), look up the center to get the commune name
                $providers = \WooBordereauGenerator\Admin\Partials\BordereauGeneratorProviders::get_providers();
                if (isset($providers['elogistia']) && isset($providers['elogistia']['setting_class'])) {
                    $settings_class = new $providers['elogistia']['setting_class']($providers['elogistia']);

                    if (method_exists($settings_class, 'get_all_centers')) {
                        $centers = $settings_class->get_all_centers();

                        // Find the center by id (which is the center name)
                        $found_center = null;
                        foreach ($centers as $center) {
                            if ($center['id'] == $billing_city || $center['name'] == $billing_city) {
                                $found_center = $center;
                                break;
                            }
                        }

                        if ($found_center) {
                            // Get the commune/city name from the center
                            $commune_name = $found_center['commune_name'] ?? '';

                            if ($commune_name) {
                                // Update billing and shipping city with the actual commune name
                                $order->set_billing_city($commune_name);
                                $order->set_shipping_city($commune_name);
                            }

                            // Save center name to meta
                            $order->add_meta_data('_billing_center_id', $billing_city, true);
                            $order->add_meta_data('_elogistia_center_name', $found_center['name'] ?? '', true);
                        }
                    }
                }
            }
        }

        // Handle TonColis API local pickup - billing_city contains center code, need to get actual city
        if (is_array($chosen_methods) && !empty($chosen_methods[0]) && str_contains($chosen_methods[0], 'local_pickup_toncolis') && $order) {
            $billing_city = isset($_POST['billing_city']) ? sanitize_text_field($_POST['billing_city']) : '';

            // Determine which toncolis_api provider slug is active
            $method_parts = explode(':', $chosen_methods[0]);
            $instance_id  = isset($method_parts[1]) ? (int) $method_parts[1] : 0;
            $provider_key = '';
            if ($instance_id) {
                $shipping_method_instance = \WC_Shipping_Zones::get_shipping_method($instance_id);
                if ($shipping_method_instance) {
                    $provider_key = $shipping_method_instance->get_option('provider', '');
                }
            }

            if (in_array($provider_key, ['toncolis_api', 'ecom_dz_new', 'flashfr'])) {
                $providers = \WooBordereauGenerator\Admin\Partials\BordereauGeneratorProviders::get_providers();
                if (isset($providers[$provider_key]['setting_class'])) {
                    $settings_class = new $providers[$provider_key]['setting_class']($providers[$provider_key]);

                    if (method_exists($settings_class, 'get_all_centers')) {
                        $centers = $settings_class->get_all_centers();

                        $found_center = null;
                        foreach ($centers as $center) {
                            if ($center['id'] == $billing_city) {
                                $found_center = $center;
                                break;
                            }
                        }

                        if ($found_center) {
                            $commune_name = $found_center['name'] ?? '';

                            if ($commune_name) {
                                $order->set_billing_city($commune_name);
                                $order->set_shipping_city($commune_name);
                            }

                            $order->add_meta_data('_billing_center_id', $billing_city, true);
                            $order->add_meta_data('_toncolis_api_center_name', $found_center['name'] ?? '', true);
                        }
                    }
                }
            }
        }

        // Handle EcoTrack local pickup - resolve commune and save it as center ID
        // Works for both dedicated method (local_pickup_ecotrack) and hanout generic local_pickup.
        if (is_array($chosen_methods) && !empty($chosen_methods[0]) && (str_contains($chosen_methods[0], 'local_pickup_ecotrack') || $chosen_methods[0] === 'local_pickup' || str_starts_with($chosen_methods[0], 'local_pickup:'))) {
            $billing_city = isset($_POST['billing_city']) ? sanitize_text_field($_POST['billing_city']) : '';

            if ($order) {
                $providers = \WooBordereauGenerator\Admin\Partials\BordereauGeneratorProviders::get_providers();
                $ecotrack_provider_key = null;
                $method_parts = explode(':', $chosen_methods[0]);
                $instance_id  = isset($method_parts[1]) ? (int) $method_parts[1] : 0;
                $is_dedicated_ecotrack = str_contains($chosen_methods[0], 'local_pickup_ecotrack');

                // First, try provider from shipping method instance options
                if ($instance_id) {
                    $shipping_method_instance = \WC_Shipping_Zones::get_shipping_method($instance_id);
                    if ($shipping_method_instance) {
                        $provider_key = $shipping_method_instance->get_option('provider', '');
                        if ($provider_key && isset($providers[$provider_key]) && (($providers[$provider_key]['type'] ?? '') === 'ecotrack')) {
                            $ecotrack_provider_key = $provider_key;
                        }
                    }
                }

                // Fallback only for dedicated local_pickup_ecotrack method
                if (!$ecotrack_provider_key && $is_dedicated_ecotrack) {
                    foreach ($providers as $key => $provider) {
                        if (($provider['type'] ?? '') === 'ecotrack' && isset($provider['setting_class'])) {
                            $ecotrack_provider_key = $key;
                            break;
                        }
                    }
                }

                if ($ecotrack_provider_key) {
                    $settings_class = new $providers[$ecotrack_provider_key]['setting_class']($providers[$ecotrack_provider_key]);

                    $found_commune = null;
                    $billing_state = isset($_POST['billing_state']) ? sanitize_text_field($_POST['billing_state']) : '';
                    if (empty($billing_state)) {
                        $billing_state = $order->get_billing_state();
                    }

                    if (method_exists($settings_class, 'get_communes')) {
                        $stop_desk_communes = $settings_class->get_communes($billing_state, true);

                        // Try 1: center_id stored on the shipping method instance (agency import mode)
                        if (!$found_commune && $instance_id) {
                            if (!isset($shipping_method_instance)) {
                                $shipping_method_instance = \WC_Shipping_Zones::get_shipping_method($instance_id);
                            }
                            if ($shipping_method_instance) {
                                $center_id = $shipping_method_instance->get_option('center_id', '');
                                if ($center_id) {
                                    foreach ($stop_desk_communes as $commune) {
                                        if ($commune['id'] === $center_id || $commune['name'] === $center_id) {
                                            $found_commune = $commune;
                                            break;
                                        }
                                    }
                                }
                            }
                        }

                        // Try 2: match shipping method instance title against "{prefix} {commune_name}"
                        // This is the most reliable source — the instance title was set during import
                        // and contains the commune name (e.g. "Point relais Bab Ezzouar")
                        if (!$found_commune && $instance_id) {
                            if (!isset($shipping_method_instance)) {
                                $shipping_method_instance = \WC_Shipping_Zones::get_shipping_method($instance_id);
                            }
                            if ($shipping_method_instance) {
                                $method_title = $shipping_method_instance->get_option('title', '');
                                if ($method_title) {
                                    $prefix = get_option($ecotrack_provider_key . '_pickup_local_label', '');
                                    foreach ($stop_desk_communes as $commune) {
                                        $expected = trim(sprintf('%s %s', $prefix, $commune['name']));
                                        if ($expected === trim($method_title)) {
                                            $found_commune = $commune;
                                            break;
                                        }
                                    }

                                    // Also try without prefix — title might be just the commune name
                                    if (!$found_commune) {
                                        foreach ($stop_desk_communes as $commune) {
                                            if (trim($commune['name']) === trim($method_title)) {
                                                $found_commune = $commune;
                                                break;
                                            }
                                        }
                                    }
                                }
                            }
                        }

                        // Try 3: billing_city matches a commune name directly (last resort)
                        if (!$found_commune && $billing_city) {
                            foreach ($stop_desk_communes as $commune) {
                                if ($commune['name'] === $billing_city) {
                                    $found_commune = $commune;
                                    break;
                                }
                            }
                        }
                    }

                    if ($found_commune) {
                        $commune_name = $found_commune['name'];

                        // Update billing and shipping city with the resolved commune name
                        $order->set_billing_city($commune_name);
                        $order->set_shipping_city($commune_name);

                        // Save commune name as center ID (EcoTrack uses commune name as the identifier)
                        $order->add_meta_data('_billing_center_id', $commune_name, true);
                        $order->add_meta_data('_ecotrack_commune_name', $commune_name, true);
                    } elseif ($billing_city) {
                        // Fallback: save whatever billing_city was submitted
                        $order->add_meta_data('_billing_center_id', $billing_city, true);
                    }
                }
            }
        }

        // Handle Near Delivery local pickup - billing_city contains buralist id, need to get actual city
        if (is_array($chosen_methods) && !empty($chosen_methods[0]) && str_contains($chosen_methods[0], 'local_pickup_near_delivery')) {
            $billing_city = isset($_POST['billing_city']) ? sanitize_text_field($_POST['billing_city']) : '';

            if ($billing_city && $order && is_numeric($billing_city)) {
                // billing_city contains the buralist id, look up the buralist to get the commune name
                $providers = \WooBordereauGenerator\Admin\Partials\BordereauGeneratorProviders::get_providers();
                if (isset($providers['near_delivery']) && isset($providers['near_delivery']['setting_class'])) {
                    $settings_class = new $providers['near_delivery']['setting_class']($providers['near_delivery']);

                    if (method_exists($settings_class, 'get_all_centers')) {
                        $centers = $settings_class->get_all_centers();

                        // Find the buralist by id
                        $found_center = null;
                        foreach ($centers as $center) {
                            if ($center['id'] == $billing_city) {
                                $found_center = $center;
                                break;
                            }
                        }

                        if ($found_center) {
                            // Get the commune/city name from the buralist
                            $commune_name = $found_center['commune_name'] ?? '';

                            if ($commune_name) {
                                // Update billing and shipping city with the actual commune name
                                $order->set_billing_city($commune_name);
                                $order->set_shipping_city($commune_name);
                            }

                            // Save buralist id and info to meta
                            $order->add_meta_data('_billing_center_id', $billing_city, true);
                            $order->add_meta_data('_near_delivery_buralist_id', $billing_city, true);
                            $order->add_meta_data('_near_delivery_buralist_name', $found_center['center_address'] ?? '', true);
                        }
                    }
                }
            }
        }
    }

    /**
     * Handle QuickFORM order center data
     * Called via woocommerce_thankyou and woocommerce_checkout_order_processed hooks
     * 
     * @param int $order_id Order ID
     * @return void
     */
    public function handle_quickform_order_center($order_id)
    {
        error_log("[QuickFORM Center] Starting handle_quickform_order_center for order_id: $order_id");

        $order = wc_get_order($order_id);
        if (!$order) {
            error_log("[QuickFORM Center] Order not found for order_id: $order_id");
            return;
        }

        // Get shipping methods from order (QuickFORM uses qf_shipping_local_pickup_XXX format)
        $order_shipping_methods = $order->get_shipping_methods();
        $is_local_pickup = false;
        $instance_id = null;
        $method_id = '';

        foreach ($order_shipping_methods as $shipping_method) {
            $method_id = $shipping_method->get_method_id();
            error_log("[QuickFORM Center] Checking shipping method_id: $method_id");
            // QuickFORM uses format: qf_shipping_local_pickup_zrexpress_v2, etc.
            if (strpos($method_id, 'local_pickup') !== false) {
                $is_local_pickup = true;
                // Get instance_id directly from the shipping item (not from method_id string)
                $instance_id = $shipping_method->get_instance_id();
                error_log("[QuickFORM Center] Found local_pickup, instance_id: $instance_id");
                break;
            }
        }

        if (!$is_local_pickup) {
            error_log("[QuickFORM Center] Not a local_pickup order, exiting");
            return;
        }

        $billing_city = $order->get_billing_city();
        error_log("[QuickFORM Center] billing_city (center name): $billing_city");

        if (empty($billing_city)) {
            error_log("[QuickFORM Center] billing_city is empty, exiting");
            return;
        }

        $provider_key = null;

        if ($instance_id) {
            // Get the shipping method instance from WooCommerce zones
            $shipping_zones = \WC_Shipping_Zones::get_zones();
            error_log("[QuickFORM Center] Searching in " . count($shipping_zones) . " shipping zones for instance_id: $instance_id");

            foreach ($shipping_zones as $zone) {
                $zone_shipping_methods = $zone['shipping_methods'];
                foreach ($zone_shipping_methods as $method) {
                    if ($method->instance_id == $instance_id) {
                        error_log("[QuickFORM Center] Found matching zone method, class: " . get_class($method));
                        if (method_exists($method, 'get_provider')) {
                            $provider_key = $method->get_provider();
                            error_log("[QuickFORM Center] Provider key from get_provider(): $provider_key");
                        } else {
                            error_log("[QuickFORM Center] Method does not have get_provider()");
                        }
                        break 2;
                    }
                }
            }
        } else {
            error_log("[QuickFORM Center] instance_id is null/empty");
        }

        // If we found a provider, fetch centers and match by name
        if (!$provider_key) {
            error_log("[QuickFORM Center] provider_key not found, exiting");
            return;
        }

        $providers = \WooBordereauGenerator\Admin\Partials\BordereauGeneratorProviders::get_providers();
        error_log("[QuickFORM Center] Available providers: " . implode(', ', array_keys($providers)));

        if (!isset($providers[$provider_key]) || !isset($providers[$provider_key]['setting_class'])) {
            error_log("[QuickFORM Center] Provider '$provider_key' not found or missing setting_class, exiting");
            return;
        }

        $settings_class = new $providers[$provider_key]['setting_class']($providers[$provider_key]);
        error_log("[QuickFORM Center] Created settings_class: " . get_class($settings_class));

        if (!method_exists($settings_class, 'get_all_centers')) {
            error_log("[QuickFORM Center] settings_class does not have get_all_centers(), exiting");
            return;
        }

        $centers_data = $settings_class->get_all_centers();
        $centers = $centers_data['items'] ?? $centers_data;
        error_log("[QuickFORM Center] Fetched " . count($centers) . " centers from provider");

        // Find the center by name (QuickFORM stores center name in billing_city)
        $found_center = null;
        foreach ($centers as $center) {
            $center_name = $center['name'] ?? '';
            if (trim($center_name) === trim($billing_city)) {
                $found_center = $center;
                error_log("[QuickFORM Center] Found matching center: " . $center_name);
                break;
            }
        }

        if (!$found_center) {
            error_log("[QuickFORM Center] No matching center found for: $billing_city");
            return;
        }

        // Get the commune/city name from the center based on provider structure
        $commune_name = '';
        $center_id = '';

        // Different providers have different center structures
        if (isset($found_center['commune_name'])) {
            $commune_name = $found_center['commune_name'];
        } elseif (isset($found_center['address']['district'])) {
            $commune_name = $found_center['address']['district'];
        }

        // Get center identifier (id or code depending on provider)
        if (isset($found_center['id'])) {
            $center_id = $found_center['id'];
        } elseif (isset($found_center['code'])) {
            $center_id = $found_center['code'];
        } elseif (isset($found_center['center_id'])) {
            $center_id = $found_center['center_id'];
        }

        error_log("[QuickFORM Center] Extracted commune_name: $commune_name, center_id: $center_id");

        if ($commune_name) {
            // Update billing and shipping city with the actual commune name
            $order->set_billing_city($commune_name);
            $order->set_shipping_city($commune_name);
            error_log("[QuickFORM Center] Updated billing/shipping city to: $commune_name");
        }

        // Save center id to meta
        if ($center_id) {
            $order->update_meta_data('_billing_center_id', $center_id);
            $order->update_meta_data('_quickform_center_name', $found_center['name'] ?? '');
            $order->update_meta_data('_quickform_provider', $provider_key);
            error_log("[QuickFORM Center] Saved meta data - center_id: $center_id, provider: $provider_key");
        }

        // Save the order
        $order->save();
        error_log("[QuickFORM Center] Order saved successfully for order_id: $order_id");
    }


    /**
     * Set default city if non has been set
     * @return void
     */
    function set_default_city_at_checkout()
    {
        $default_state = null;
        $city = WC()->customer->get_billing_city(); // Or ->get_shipping_city() based on your needs
        $store_base_location = get_option('woocommerce_default_country');
        if($store_base_location && strpos($store_base_location, ':') !== false) {
            list($default_country, $default_state) = explode(':', $store_base_location);
        }

        // If the city is not set or is empty, set a default city.
        if (empty($city) && $default_state) {
            WC()->customer->set_billing_state($default_state); // Replace with the actual city name you want to set as default.
            // Or use ->set_shipping_city() based on your needs
        } else {
            WC()->customer->set_billing_state('DZ-16');
        }
    }


    /**
     * @return void
     */
    public function custom_checkout_field_process()
    {
//        if (show_yalidine_centers() && empty($_POST['billing_center']) && str_contains($_POST['shipping_method'][0], 'local_pickup_yalidine')) {
//            wc_add_notice(__('Center field is required for local pickup.', 'woo-bordereau-generator'), 'error');
//        }
//
//        if (show_yalitec_centers() && empty($_POST['billing_center']) && str_contains($_POST['shipping_method'][0], 'local_pickup_yalidine')) {
//            wc_add_notice(__('Center field is required for local pickup.', 'woo-bordereau-generator'), 'error');
//        }
//
//        if (show_nord_ouest_centers() && empty($_POST['billing_center']) && str_contains($_POST['shipping_method'][0], 'local_pickup_noest')) {
//            wc_add_notice(__('Center field is required for local pickup.', 'woo-bordereau-generator'), 'error');
//        }
//
//        if (show_zrexpress_v2_centers() && empty($_POST['billing_center']) && str_contains($_POST['shipping_method'][0], 'local_pickup_zrexpress_v2')) {
//            wc_add_notice(__('Center field is required for local pickup.', 'woo-bordereau-generator'), 'error');
//        }
    }

    /**
     * Remove city validation errors for local_pickup when city_optional is enabled
     * 
     * @param array $data Checkout posted data
     * @param \WP_Error $errors Validation errors
     * @since 4.0.0
     */
    public function validate_city_for_local_pickup($data, $errors)
    {
        // Only apply when city_optional setting is enabled
        $city_optional = get_option('wc_bordreau_city-optional', 'false') === 'true';
        if (!$city_optional) {
            return;
        }

        // Check if local_pickup is selected
        $chosen_methods = WC()->session->get('chosen_shipping_methods');
        if (!is_array($chosen_methods) || empty($chosen_methods[0])) {
            return;
        }

        $chosen_shipping = $chosen_methods[0];

        // If local_pickup is selected, remove city validation errors
        if (strpos($chosen_shipping, 'local_pickup') === 0) {
            // Get all error codes
            $error_codes = $errors->get_error_codes();

            // Remove billing_city related errors
            foreach ($error_codes as $code) {
                if (strpos($code, 'billing_city') !== false) {
                    // WP_Error doesn't have a direct remove method, so we need to recreate without this error
                    $errors->remove($code);
                }
            }
        }
    }

    /**
     * @param $fields
     * @return array
     */
    public function change_state_and_city_order($fields)
    {
        $fields['state']['priority'] = 70;
        $fields['city']['priority'] = 80;
        /* translators: Translate it to the name of the State level territory division, e.g. "State", "Province",  "Department" */
        $fields['state']['label'] = __('State', 'woo-bordereau-generator');
        /* translators: Translate it to the name of the City level territory division, e.g. "City, "Municipality", "District" */
        $fields['city']['label'] = __('City', 'woo-bordereau-generator');

        return $fields;
    }

    /**
     * @param $cart
     * @return void
     */
    public function add_city_fee(\WC_Cart $cart)
    {

        $chosen_methods = WC()->session->get('chosen_shipping_methods');

        if (!empty($chosen_methods)) {
            $chosen_shipping_method = $chosen_methods[0]; // Assuming single shipping package

            if (str_contains($chosen_shipping_method, 'flat_rate')) {
                WC()->session->__unset('commune_supplement');
            }
        }

        $shipping_class_selected = 'flat_rate';

        $has_extra_5_kg = null;
        $has_extra_city_fee = null;
        $selected_shipping_provider = null;
        $selected_shipping_method = isset($_POST['shipping_method'][0]) ? sanitize_text_field($_POST['shipping_method'][0]) : '';


        if (isset($chosen_shipping_method) && $chosen_shipping_method != null) {

            $shipping_class_selected = explode(':', $chosen_shipping_method);
            $shipping_class_selected = reset($shipping_class_selected);
            $shipping_classes = WC()->shipping()->get_shipping_methods();

            foreach ($shipping_classes as $key => $shipping_class) {
                if ($shipping_class->id == $shipping_class_selected) {
                    $selected_class = $shipping_class;

                    if (method_exists($selected_class, 'get_has_city_fee')) {
                        $has_extra_city_fee = $selected_class->get_has_city_fee();
                    }

                    if (method_exists($selected_class, 'get_has_extra_weight')) {
                        $has_extra_5_kg = $selected_class->get_has_extra_weight();
                    }
                    if (method_exists($selected_class, 'get_provider')) {
                        $selected_shipping_provider = $selected_class->get_provider();
                    }

                    break;
                }
            }
        }

        if (is_admin() && !defined('DOING_AJAX')) return;

        $extra_city_fee_enabled = (yalidine_is_enabled() && in_array($selected_shipping_method, ['flat_rate', 'local_pickup', 'flat_rate_yalidine', 'local_pickup_yalidine'])) || only_yalidine_is_enabled();
        $extra_5_kg_enabled = yalidine_is_enabled() && in_array($selected_shipping_method, ['flat_rate', 'local_pickup', 'flat_rate_yalidine', 'local_pickup_yalidine']) || only_yalidine_is_enabled();

        $extra_5_kg_nord_enabled = is_nord_ouest_enabled() && in_array($selected_shipping_method, ['flat_rate', 'local_pickup', 'flat_rate_noest', 'local_pickup_noest']);
        $extra_5_kg_zr_enabled = is_zr_enabled() && in_array($selected_shipping_method, ['flat_rate', 'local_pickup', 'flat_rate_zr_express', 'local_pickup_zr_express']);

        if ($has_extra_city_fee != null) {
            $extra_city_fee_enabled = (yalidine_is_enabled() && $has_extra_city_fee) || only_yalidine_is_enabled();
        }

        if ($has_extra_5_kg != null) {
            $extra_5_kg_enabled = (yalidine_is_enabled() && $has_extra_5_kg) || only_yalidine_is_enabled();
            $extra_5_kg_nord_enabled = is_nord_ouest_enabled() && $has_extra_5_kg;
            $extra_5_kg_zr_enabled = is_zr_enabled() && $has_extra_5_kg;
        }

        // Todo check why the fee still persist even if i disabled it  - it persist and itsn't showing in the checkout
        if ($extra_city_fee_enabled && ((get_option('added_fee_yalidine_total') == 'with_added_fee_yalidine')
                                        || get_option('added_fee_guepex_total') == 'with_added_fee_guepex'
                                        || get_option('added_fee_wecanservices_total') == 'with_added_fee_wecanservices'
                                        || get_option('added_fee_speedmail_total') == 'with_added_fee_speedmail'
                                        || get_option('added_fee_easyandspeed_total') == 'with_added_fee_easyandspeed')
        ) {

            if (!wc()->session) {
                wc()->initialize_session();
            }

            // Handle both direct POST and AJAX fragment updates
            $post_data = array();
            if (isset($_POST['post_data'])) {
                parse_str($_POST['post_data'], $post_data);
            }

            // Try to get city from various sources
            $selected_city_id = '';
            if (!empty($post_data['billing_city'])) {
                $selected_city_id = sanitize_text_field($post_data['billing_city']);
            } elseif (isset($_POST['billing_city'])) {
                $selected_city_id = sanitize_text_field($_POST['billing_city']);
            } elseif (isset($_POST['city'])) {
                $selected_city_id = sanitize_text_field($_POST['city']);
            } elseif (WC()->customer) {
                $selected_city_id = WC()->customer->get_billing_city();
            }

            // Try to get state from various sources
            $selected_state = '';
            if (!empty($post_data['billing_state'])) {
                $selected_state = sanitize_text_field($post_data['billing_state']);
            } elseif (isset($_POST['billing_state'])) {
                $selected_state = sanitize_text_field($_POST['billing_state']);
            } elseif (isset($_POST['state'])) {
                $selected_state = sanitize_text_field($_POST['state']);
            } elseif (WC()->customer) {
                $selected_state = WC()->customer->get_billing_state();
            }

            // Get shipping method
            $selected_shipping_method = '';
            if (!empty($post_data['shipping_method'][0])) {
                $selected_shipping_method = sanitize_text_field($post_data['shipping_method'][0]);
            } elseif (isset($_POST['shipping_method'][0])) {
                $selected_shipping_method = sanitize_text_field($_POST['shipping_method'][0]);
            }

            if (strpos($selected_shipping_method, 'flat_rate') !== false) {
                if ($selected_shipping_provider) {
                    $providers = BordereauGeneratorProviders::get_providers();
                    $provider = $providers[$selected_shipping_provider];
                    if (class_exists($provider['setting_class'])) {
                        $providerClass = new $provider['setting_class']($provider);

                        if (method_exists($providerClass, 'get_extra_fee')) {
                            $communes = $providerClass->get_extra_fee($selected_state);

                            $matching_communes = array_filter($communes, function ($commune) use ($selected_city_id) {


                                if (is_numeric($selected_city_id)) {
                                    return $commune['commune_id'] == $selected_city_id;
                                } else {
                                    $selected_city_id = str_replace("\\'", "'", $selected_city_id);
                                    return  strcasecmp($commune['commune_name'], $selected_city_id) === 0;
                                }
                                return  strcasecmp($commune['commune_name'], $selected_city_id) === 0;
                            });


                            // Get the first matching commune (if any)
                            $foundCommune = reset($matching_communes);

                            if ($foundCommune) {
                                $fee = (int) $foundCommune['extra_fee_home'];
                                if ($fee) {
                                    wc()->session->set('supplement', $fee);
                                    $cart->add_fee(__('Supplément commune', 'woo-bordereau-generator'), $fee);
                                } else {
                                    wc()->session->__unset('supplement');
                                }
                            }
                        }
                    }

                } else {
                    $communes = $this->get_extra_fee($selected_state);


                    $matching_communes = array_filter($communes, function ($commune) use ($selected_city_id) {
                        if (is_numeric($selected_city_id)) {
                            return $commune['id'] == $selected_city_id;
                        } else {
                            $selected_city_id = str_replace("\\'", "'", $selected_city_id);
                            return $commune['name'] == $selected_city_id;
                        }
                        return Helpers::areSimilar($commune['name'], $selected_city_id);
                    });

                    // Get the first matching commune (if any)
                    $commune = reset($matching_communes);

                    if ($commune) {
                        $fee = (int) $commune['commune'];
                        if ($fee) {
                            wc()->session->set('supplement', $fee);
                            $cart->add_fee(__('Supplément commune', 'woo-bordereau-generator'), $fee);
                        } else {
                            wc()->session->__unset('supplement');
                        }
                    }
                }

            } else {
                wc()->session->__unset('supplement');
            }
        }

        $classic = false;


        if ($shipping_class_selected == 'flat_rate' || $shipping_class_selected == 'local_pickup') {
            $classic = true;
        }

        $shipping_method = false;

        if (! $classic) {
            if ($shipping_class_selected == 'flat_rate_yalidine' || $shipping_class_selected == 'local_pickup_yalidine') {
                $shipping_method = 'yalidine';
            } elseif ( $shipping_class_selected == 'flat_rate_noest' || $shipping_class_selected == 'local_pickup_noest') {
                $shipping_method = 'noest';
            } elseif ( $shipping_class_selected == 'flat_rate_zr_express' || $shipping_class_selected == 'local_pickup_zr_express') {
                $shipping_method = 'zr_express';
            } elseif ( $shipping_class_selected == 'flat_rate_ecotrack' || $shipping_class_selected == 'local_pickup_ecotrack') {
                $shipping_method = 'ecotrack';
            }
        }


        if ($shipping_method == 'yalidine' && $extra_5_kg_enabled && yalidine_is_enabled()
            && ((get_option('recalculate_yalidine_overweight_checkout') == 'recalculate-with-overweight')
                || get_option('recalculate_guepex_overweight_checkout') == 'recalculate-with-overweight'
                || get_option('recalculate_wecanservices_overweight_checkout') == 'recalculate-with-overweight'
                || get_option('recalculate_speedmail_overweight_checkout') == 'recalculate-with-overweight')
            || get_option('recalculate_easyandspeed_overweight_checkout') == 'recalculate-with-overweight') {
            $selected_state = isset($_POST['state']) ? sanitize_text_field($_POST['state']) : '';
            if (! $selected_state) {
                $selected_state = isset($_POST['billing_state']) ? sanitize_text_field($_POST['billing_state']) : '';
            }
            $weightData = Helpers::get_billable_weight($cart);
            $shippingOverweight = $weightData['billable_weight'];

            if ($shippingOverweight > 5) {
                // get Wilaya

                $wilayas = $this->get_wilayas();
                $pattern = "/DZ-[0-9]{2}/";

                if (preg_match($pattern, $selected_state)) {
                    $wilayaId = (int)str_replace("DZ-", "", $selected_state);

                } else {
                    $wilayaId = $selected_state;
                }

                if (is_int($wilayaId)) {
                    $wilaya = array_search($wilayaId, array_column($wilayas['data'], 'id'));
                } else {
                    $wilaya = array_search($wilayaId, array_column($wilayas['data'], 'name'));
                }

                if ($wilayaId) {
                    $zonePrice = 50;

                    if ($wilaya !== false) {
                        $wilayaData = $wilayas['data'][$wilaya];

                        if (in_array($wilayaData['zone'], [4, 5])) {
                            $zonePrice = 100;
                        }
                    }

                    $shippingOverweightPrice = max(0, ceil($shippingOverweight - 5)) * $zonePrice;
                    $cart->add_fee(__('Surpoids (+5KG)', 'woo-bordereau-generator'), $shippingOverweightPrice);
                    wc()->session->set('surpoids', $shippingOverweightPrice); // Set to false so new orders don't show the fee until it is set.
                }
            }

        } else if ($shipping_method == 'noest' && $extra_5_kg_nord_enabled && ((get_option('recalculate_nord_ouest_express_overweight_checkout') == 'recalculate-with-overweight'))) {

            $total_weight = 0;
            $shippingOverweight = 0;

            $shippingOverweight = $this->getOverweight($cart, $total_weight, $shippingOverweight);

            if ($shippingOverweight > 5) {
                $zonePrice = 40;
                $shippingOverweightPrice = max(0, ceil($shippingOverweight - 5)) * $zonePrice;

                $cart->add_fee(__('Surpoids (+5KG)', 'woo-bordereau-generator'), $shippingOverweightPrice);
                WC()->session->set('surpoids', $shippingOverweightPrice); // Set to false so new orders don't show the fee until it is set.
            }

        } else if ($shipping_method == 'zr_express' && $extra_5_kg_zr_enabled && ((get_option('recalculate_zr_express_overweight_checkout') == 'recalculate-with-overweight'))) {

            $total_weight = 0;
            $shippingOverweight = 0;

            $shippingOverweight = $this->getOverweight($cart, $total_weight, $shippingOverweight);

            if ($shippingOverweight > 5) {
                $zonePrice = 50;
                $shippingOverweightPrice = max(0, ceil($shippingOverweight - 5)) * $zonePrice;
                $cart->add_fee(__('Surpoids (+5KG)', 'woo-bordereau-generator'), $shippingOverweightPrice);
                WC()->session->set('surpoids', $shippingOverweightPrice); // Set to false so new orders don't show the fee until it is set.
            }

        } else if ($shipping_method == 'zrexpress_v2' && get_option('recalculate_zrexpress_v2_overweight_checkout') == 'recalculate-with-overweight') {

            $total_weight = 0;
            $shippingOverweight = 0;

            $shippingOverweight = $this->getOverweight($cart, $total_weight, $shippingOverweight);

            // Get configurable values from settings
            $freeKg = (float) get_option('zrexpress_v2_overweight_free_kg', 5);
            $pricePerKg = (float) get_option('zrexpress_v2_overweight_price_per_kg', 50);

            if ($shippingOverweight > $freeKg) {
                $shippingOverweightPrice = max(0, ceil($shippingOverweight - $freeKg)) * $pricePerKg;
                $feeLabel = sprintf(__('Surpoids (+%sKG)', 'woo-bordereau-generator'), $freeKg);
                $cart->add_fee($feeLabel, $shippingOverweightPrice);
                WC()->session->set('surpoids', $shippingOverweightPrice);
                WC()->session->set('surpoids_free_kg', $freeKg);
            } else {
                WC()->session->__unset('surpoids');
            }

        } else if ($shipping_method == 'ecotrack') {
            // Get the ecotrack provider slug from the shipping method instance
            $chosen_shipping = WC()->session->get('chosen_shipping_methods');
            $ecotrack_provider = null;

            if (!empty($chosen_shipping[0])) {
                // Extract provider from shipping method ID (e.g., flat_rate_ecotrack:1)
                $method_parts = explode(':', $chosen_shipping[0]);
                if (!empty($method_parts[1])) {
                    $instance_id = $method_parts[1];
                    $shipping_method_instance = \WC_Shipping_Zones::get_shipping_method($instance_id);
                    if ($shipping_method_instance) {
                        $ecotrack_provider = $shipping_method_instance->get_option('provider');
                    }
                }
            }

            // Check if overweight is enabled for this ecotrack provider
            if ($ecotrack_provider && get_option('recalculate_' . $ecotrack_provider . '_overweight_checkout') == 'recalculate-with-overweight') {
                $total_weight = 0;
                $shippingOverweight = 0;

                $shippingOverweight = $this->getOverweight($cart, $total_weight, $shippingOverweight);

                // Get configurable values from settings
                $freeKg = (float) get_option($ecotrack_provider . '_overweight_free_kg', 5);
                $pricePerKg = (float) get_option($ecotrack_provider . '_overweight_price_per_kg', 50);

                if ($shippingOverweight > $freeKg) {
                    $shippingOverweightPrice = max(0, ceil($shippingOverweight - $freeKg)) * $pricePerKg;
                    $feeLabel = sprintf(__('Surpoids (+%sKG)', 'woo-bordereau-generator'), $freeKg);
                    $cart->add_fee($feeLabel, $shippingOverweightPrice);
                    WC()->session->set('surpoids', $shippingOverweightPrice);
                    WC()->session->set('surpoids_free_kg', $freeKg);
                } else {
                    WC()->session->__unset('surpoids');
                }
            } else {
                WC()->session->__unset('surpoids');
            }

        } else {
            WC()->session->__unset('surpoids'); // Set to false so new orders don't show the fee until it is set.

        }
    }

    /**
     * @since 4.0.0
     * @param $order
     * @param $data
     * @return void
     */
    function add_fee_to_order($order, $data)
    {
        // Commune supplement fee is automatically copied from cart to order by WooCommerce
        // No need to manually add it here - doing so causes duplicates
        // Just clean up the session variable
        $fee = WC()->session->get('supplement');
        if ($fee) {
            WC()->session->__unset('supplement');
        }

        $order->calculate_totals();
        $order->save();

        foreach ($order->get_items('fee') as $item_id => $item_fee) {
            if ($item_fee->get_name() === __('Surpoids (+5KG)', 'woo-bordereau-generator')) {
                WC()->session->__unset('surpoids');
                return;
            }
        }


        $shippingOverweightPrice = WC()->session->get('surpoids');

        if ($shippingOverweightPrice) {

            $item_fee = new WC_Order_Item_Fee();
            $item_fee->set_name(__('Surpoids (+5KG)', 'woo-bordereau-generator'));
            $item_fee->set_amount($shippingOverweightPrice); // Fee amout
            $item_fee->set_tax_class(''); // default for ''
            $item_fee->set_tax_status('none'); // or 'none'
            $item_fee->set_total($shippingOverweightPrice); // Fee amount

            // Add Fee item to the order
            $order->add_item($item_fee);

            WC()->session->__unset('surpoids');
        }

        $order->calculate_totals();
        $order->save();

    }

    public function get_extra_fee_from_provider(string $state)
    {

        $wilaya = str_replace('DZ-', '', $state);

        $curl = curl_init();
        curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://amineware.me/api/cities-with-fee?token=1Q0cWtxFHLwMAffh8KeRzNC9QXiZju77&wilaya=' . $wilaya,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                        "Content-Type: application/json"
                ),
        ));

        $result = curl_exec($curl);

        if ($result === false) {
            if (curl_errno($curl)) {
                $error_msg = curl_error($curl);

                new \WP_Error($error_msg);

                wp_send_json([
                        'error' => true,
                        'message' => $error_msg
                ], 401);
            }
        }
        return $result;
    }

    public function get_extra_fee(string $state)
    {

        $upload_dir = wp_upload_dir();
        $directory = $upload_dir['basedir'] . '/wc-bordereau-generator';


        if (!is_dir($directory)) {
            wp_mkdir_p($directory);
        }


        $filename = 'yalidine_commune_with_fee_' . WC_BORDEREAU_GENERATOR_VERSION . '_' . $state . '.json';

        $path = $directory . '/' . $filename;

        if (file_exists($path)) {
            $wilayas = json_decode(file_get_contents($path), true);

        } else {

            $response = $this->get_extra_fee_from_provider($state);

            file_put_contents($path, $response);
            return json_decode($response, true);
        }

        return $wilayas;
    }


    /**
     * @param $order
     * @return void
     */
    public function display_order_tracking_number($order)
    {

        // if ( ! is_a( $order, 'WC_Order' ) ) {
        //     global $order;
        // }

        // // The Order ID
        // $order_id = $order->get_id();

        // $tracking_number = get_post_meta( $order_id, '_shipping_tracking_number', true );

        // if( $tracking_number ){
        //     echo '<p><strong>Tracking number: </strong>' . $tracking_number . '</p>';
        // }
    }


    /**
     * @return void
     */
    public function get_shipping_cities()
    {

        // list($method_id, $instance_id) = explode(':', $_POST['shipping_method']);
        // $zone = $this->get_shipping_zone_name_from_method_instance($instance_id);
        // list($wilaya, $provider) = explode(':', $zone);
        // print_r($provider);
        // die();
    }


    function get_shipping_zone_name_from_method_instance($instance_id)
    {
        $zones = WC_Shipping_Zones::get_zones();
        foreach ($zones as $zone_data) {
            $methods = $zone_data['shipping_methods'];
            foreach ($methods as $method) {
                if ($method->instance_id == $instance_id) {
                    return $zone_data['zone_name'];
                }
            }
        }

        // Also check for methods not within a specific zone (zone_id = 0)
        $zone = new WC_Shipping_Zone(0);
        $methods = $zone->get_shipping_methods();
        foreach ($methods as $method) {
            if ($method->instance_id == $instance_id) {
                return false;
            }
        }

        return false;
    }


    /**
     * Register the updated wilaya in Algeria.
     *
     * @since    1.1.1
     */
    public function custom_woocommerce_states($states)
    {
        // check if the states in Algeria are

        if (get_option('wc_bordreau_disable-cities') == "false") {
            $states['DZ'] = $this->get_states();
        }

        return apply_filters('bordreau_states_dz', $states);
    }

    public function custom_extra_woocommerce_states($states)
    {
        // check if the states in Algeria are


        $states['DZ'] = array(
                'DZ-01' => _x("01  Adrar أدرار", "states", "woo-bordereau-generator"),
                'DZ-02' => _x("02	Chlef  الشلف", "states", "woo-bordereau-generator"),
                'DZ-03' => _x("03	Laghouat	الأغواط", "states", "woo-bordereau-generator"),
                'DZ-04' => _x("04	Oum El Bouaghi	أم البواقي", "states", "woo-bordereau-generator"),
                'DZ-05' => _x("05	Batna	باتنة", "states", "woo-bordereau-generator"),
                'DZ-06' => _x("06	Béjaïa	بجاية", "states", "woo-bordereau-generator"),
                'DZ-07' => _x("07	Biskra	بسكرة", "states", "woo-bordereau-generator"),
                'DZ-08' => _x("08	Béchar	بشار", "states", "woo-bordereau-generator"),
                'DZ-09' => _x("09	Blida	البليدة", "states", "woo-bordereau-generator"),
                'DZ-10' => _x("10	Bouïra	البويرة	", "states", "woo-bordereau-generator"),
                'DZ-11' => _x("11	Tamanrasset	تمنراست", "states", "woo-bordereau-generator"),
                'DZ-12' => _x("12	Tébessa	تبسة", "states", "woo-bordereau-generator"),
                'DZ-13' => _x("13	Tlemcen	تلمسان", "states", "woo-bordereau-generator"),
                'DZ-14' => _x("14	Tiaret	تيارت", "states", "woo-bordereau-generator"),
                'DZ-15' => _x("15	Tizi Ouzou	تيزي وزو", "states", "woo-bordereau-generator"),
                'DZ-16' => _x("16	Alger	الجزائر", "states", "woo-bordereau-generator"),
                'DZ-17' => _x("17	Djelfa	الجلفة", "states", "woo-bordereau-generator"),
                'DZ-18' => _x("18	Jijel	جيجل", "states", "woo-bordereau-generator"),
                'DZ-19' => _x("19	Sétif	سطيف", "states", "woo-bordereau-generator"),
                'DZ-20' => _x("20	Saïda	سعيدة", "states", "woo-bordereau-generator"),
                'DZ-21' => _x("21	Skikda	سكيكدة", "states", "woo-bordereau-generator"),
                'DZ-22' => _x("22	Sidi Bel Abbès	سيدي بلعباس", "states", "woo-bordereau-generator"),
                'DZ-23' => _x("23	Annaba	عنابة", "states", "woo-bordereau-generator"),
                'DZ-24' => _x("24	Guelma	قالمة", "states", "woo-bordereau-generator"),
                'DZ-25' => _x("25	Constantine	قسنطينة", "states", "woo-bordereau-generator"),
                'DZ-26' => _x("26	Médéa	المدية", "states", "woo-bordereau-generator"),
                'DZ-27' => _x("27	Mostaganem	مستغانم", "states", "woo-bordereau-generator"),
                'DZ-28' => _x("28	M\'Sila	المسيلة", "states", "woo-bordereau-generator"),
                'DZ-29' => _x("29	Mascara	معسكر", "states", "woo-bordereau-generator"),
                'DZ-30' => _x("30	Ouargla	ورقلة", "states", "woo-bordereau-generator"),
                'DZ-31' => _x("31	Oran	وهران", "states", "woo-bordereau-generator"),
                'DZ-32' => _x("32	El Bayadh	البيض", "states", "woo-bordereau-generator"),
                'DZ-33' => _x("33	Illizi	اليزي", "states", "woo-bordereau-generator"),
                'DZ-34' => _x("34	Bordj Bou Arréridj	برج بوعريريج", "states", "woo-bordereau-generator"),
                'DZ-35' => _x("35	Boumerdès	بومرداس", "states", "woo-bordereau-generator"),
                'DZ-36' => _x("36	El Tarf	الطارف", "states", "woo-bordereau-generator"),
                'DZ-37' => _x("37	Tindouf	تندوف", "states", "woo-bordereau-generator"),
                'DZ-38' => _x("38	Tissemsilt	تسمسيلت", "states", "woo-bordereau-generator"),
                'DZ-39' => _x("39	El Oued	الوادي", "states", "woo-bordereau-generator"),
                'DZ-40' => _x("40	Khenchela	خنشلة", "states", "woo-bordereau-generator"),
                'DZ-41' => _x("41	Souk Ahras	سوق أهراس", "states", "woo-bordereau-generator"),
                'DZ-42' => _x("42	Tipaza	تيبازة", "states", "woo-bordereau-generator"),
                'DZ-43' => _x("43	Mila	ميلة", "states", "woo-bordereau-generator"),
                'DZ-44' => _x("44	Aïn Defla	عين الدفلى", "states", "woo-bordereau-generator"),
                'DZ-45' => _x("45	Naâma	النعامة", "states", "woo-bordereau-generator"),
                'DZ-46' => _x("46	Aïn Témouchent	عين تموشنت", "states", "woo-bordereau-generator"),
                'DZ-47' => _x("47	Ghardaïa	غرداية", "states", "woo-bordereau-generator"),
                'DZ-48' => _x("48	Relizane	غليزان", "states", "woo-bordereau-generator"),
                'DZ-49' => _x("49	Timimoun	تيميمون", "states", "woo-bordereau-generator"),
                'DZ-50' => _x("50	Bordj Baji Mokhtar	برج باجي مختار", "states", "woo-bordereau-generator"),
                'DZ-51' => _x("51	Ouled Djellal	أولاد جلال", "states", "woo-bordereau-generator"),
                'DZ-52' => _x("52	Béni Abbès	بني عباس", "states", "woo-bordereau-generator"),
                'DZ-53' => _x("53	In Salah	عين صالح", "states", "woo-bordereau-generator"),
                'DZ-54' => _x("54	In Guezzam	عين قزّام", "states", "woo-bordereau-generator"),
                'DZ-55' => _x("55	Touggourt	تقرت", "states", "woo-bordereau-generator"),
                'DZ-56' => _x("56	Djanet	جانت", "states", "woo-bordereau-generator"),
                'DZ-57' => _x("57	El M'Ghair	المغير", "states", "woo-bordereau-generator"),
                'DZ-58' => _x("58	El Menia	المنيعة", "states", "woo-bordereau-generator")
        );


        return apply_filters('bordreau_states_dz', $states);
    }


    /**
     * @return array[]|void
     */
    public function custom_woocommerce_cities()
    {
        if (get_option('wc_bordreau_disable-cities') == "false") {

            $cities = Helpers::get_all_cities();
            return apply_filters('bordreau_cities_dz', $cities);
        }
    }

    public function custom_shortcode_content($atts, $content = null)
    {
        return "<div id='bordereau-tracking'></div>";
    }

    /**
     * Add files
     * @param $order_id
     * @return void
     */
    function custom_woocommerce_order_details_table($order_id)
    {
        if (!$order_id) {
            return;
        }

        $tracking_number = get_post_meta($order_id, '_shipping_tracking_number', true);

        if ($tracking_number) {
            $provider = get_post_meta($order_id, '_shipping_tracking_method', true);

            $order = wc_get_order($order_id);

            if(! $provider) {
                $provider = $order->get_meta('_shipping_tracking_method');
            }

            $providers = BordereauGeneratorProviders::get_providers();
            $selectedProvider = $providers[$provider];

            $theorder = wc_get_order($order_id);

            if (class_exists($selectedProvider['class'])) {
                $class = new $selectedProvider['class']($theorder, $selectedProvider);
                try {
                    if (method_exists($class, 'track_detail')) {
                        $tracking = $class->track_detail($tracking_number);
                    }
                } catch (RequestException|\ErrorException $exception) {
                }

            }

            wc_get_template(
                    'order/order-detail.php',
                    array(
                            'order_id' => $order_id,
                            'tracking_number' => $tracking_number,
                            'selected_provider' => $selectedProvider,
                            'tracking' => $tracking
                    ),
                    '',
                    plugin_dir_path(WC_BORDEREAU_PLUGIN_PATH) . 'woocommerce/templates/'
            );
        }
    }


    /**
     * @return array
     * @since 2.9.0
     */
    public function get_communes_from_yalidine()
    {

        list($provider, $selectedProvider) = $this->get_enabled_providers();


        if (get_option($provider . '_api_key') && get_option($provider . '_api_token')) {

            $upload_dir = wp_upload_dir();

            $hasModePages = true;
            $currentPage = 1;
            $communes = [];
            while ($hasModePages === true) {
                $request = new YalidineRequest($selectedProvider);
                $json = $request->get($selectedProvider['api_url'] . '/communes?page=' . $currentPage . '&page_size=1000');

                $data = $json['data'] ?? [];

                if (count($data) == 0) {
                    $json = $request->get($selectedProvider['api_url'] . '/communes?page=' . $currentPage . '&page_size=1000');
                    $data = $json['data'] ?? [];
                }

                $communes = array_merge($communes,$data);
                if (! isset($json['has_more']) || !$json['has_more']) {
                    $hasModePages = false;
                }
                $currentPage++;
            }

            $communesResult = [];

            foreach ($communes as $i => $item) {
                $communesResult[$i]['id'] = $item['id'];
                $communesResult[$i]['name'] = $item['name'];
                $communesResult[$i]['wilaya_id'] = $item['wilaya_id'];
                $communesResult[$i]['has_stop_desk'] = $item['has_stop_desk'];
                $communesResult[$i]['is_deliverable'] = $item['is_deliverable'];
            }

            $groupedByWilaya = [];

            foreach ($communesResult as $item) {
                $key = sprintf('DZ-%02d', $item['wilaya_id']);
                if (!isset($groupedByWilaya[$key])) {
                    $groupedByWilaya[$key] = [];
                }
                $groupedByWilaya[$key][] = $item;
            }

            return $groupedByWilaya;
        }

        return [];

    }

    /**
     * @param $city
     * @param $state
     * @return mixed
     * @since 2.9.0
     */
    public function get_communes_from_yalidine_from_id($city, $state)
    {
        $communes = $this->get_communes_from_yalidine();

        if(isset($communes[$state])) {
            $found = array_filter($communes[$state], function ($v, $k) use ($city) {
                return $v['id'] == $city;
            }, ARRAY_FILTER_USE_BOTH);

            return array_values($found)[0]['name'] ?? $city;
        }

        return [];
    }

    public function custom_woocommerce_centers()
    {
        $hasModePages = true;
        $currentPage = 1;
        $centers = [];

        list($provider, $selectedProvider) = $this->get_enabled_providers();

        if (get_option($provider . '_api_key') && get_option($provider . '_api_token')) {

            while ($hasModePages === true) {

                $request = new YalidineRequest($selectedProvider);
                $json = $request->get($selectedProvider['api_url'] . '/centers?page=' . $currentPage . '&page_size=1000');

                $data = $json['data'] ?? [];

                if (count($data) == 0) {
                    $json = $request->get($selectedProvider['api_url'] . '/centers?page=' . $currentPage . '&page_size=1000');
                    $data = $json['data'] ?? [];
                }

                $centers = array_merge($centers, $data);
                if (! isset($json['has_more']) || !$json['has_more']) {
                    $hasModePages = false;
                }

                $currentPage++;
            }

            $communesResult = [];

            foreach ($centers as $i => $item) {
                $communesResult[$i]['id'] = $item['center_id'];
                $communesResult[$i]['name'] = $item['name'];
                $communesResult[$i]['commune_id'] = $item['commune_id'];
            }

            return $communesResult;
        }

        return [];
    }

    /**
     * @return array
     */
    public function get_enabled_providers(): array
    {
        $enabledProviders = get_option('wc-bordereau-generator_allowed_providers');
        $provider = 'yalidine';

        if (is_array($enabledProviders)) {

            if (in_array('guepex', $enabledProviders)) {
                $provider = 'guepex';
            }

            if (in_array('wecanservices', $enabledProviders)) {
                $provider = 'wecanservices';
            }

            if (in_array('speedmail', $enabledProviders)) {
                $provider = 'speedmail';
            }

            if (in_array('easyandspeed', $enabledProviders)) {
                $provider = 'easyandspeed';
            }

            if (in_array('guepex', $enabledProviders) && in_array('yalidine', $enabledProviders)) {
                $provider = 'yalidine';
            }
        }

        $providers = BordereauGeneratorProviders::get_providers();
        $selectedProvider = $providers[$provider];
        return array($provider, $selectedProvider);
    }

    /**
     * @param \WC_Cart $cart
     * @param float $total_weight
     * @param int $shippingOverweight
     * @return float|int
     */
    /**
     * Hide flat_rate_yalidine when cart weight >= 20kg and overweight is enabled.
     *
     * Hooked on `woocommerce_package_rates` (priority 20).
     *
     * @param array $rates   Available shipping rates for the package.
     * @param array $package The shipping package.
     * @return array Filtered rates.
     */
    public function filter_yalidine_rates_by_weight( array $rates, array $package ): array
    {
        // Only apply when Yalidine is enabled and the overweight checkout option is active
        if ( ! yalidine_is_enabled() ) {
            return $rates;
        }

        if ( get_option( 'recalculate_yalidine_overweight_checkout' ) !== 'recalculate-with-overweight' ) {
            return $rates;
        }

        // Calculate total cart weight
        $total_weight = 0.0;
        if ( WC()->cart ) {
            foreach ( WC()->cart->get_cart_contents() as $cart_item ) {
                $product = $cart_item['data'];
                $weight  = $product->get_weight();

                if ( $product->is_type( 'variation' ) && empty( $weight ) ) {
                    $parent  = wc_get_product( $product->get_parent_id() );
                    $weight  = $parent ? $parent->get_weight() : 0;
                }

                $total_weight += (float) $weight * (int) $cart_item['quantity'];
            }
        }

        // Only hide flat_rate_yalidine when weight is 20kg or above
        if ( $total_weight < 20 ) {
            return $rates;
        }

        foreach ( $rates as $rate_key => $rate ) {
            if ( $rate->method_id === 'flat_rate_yalidine' ) {
                unset( $rates[ $rate_key ] );
            }
        }

        return $rates;
    }

    /**
     * Add cart weight to shipping package so the shipping rate cache
     * invalidates whenever weight crosses the 20kg threshold.
     *
     * Hooked on `woocommerce_shipping_packages` (priority 10).
     *
     * @param array $packages Shipping packages.
     * @return array
     */
    public function add_weight_to_shipping_package( array $packages ): array
    {
        if ( ! yalidine_is_enabled() ) {
            return $packages;
        }

        if ( get_option( 'recalculate_yalidine_overweight_checkout' ) !== 'recalculate-with-overweight' ) {
            return $packages;
        }

        // Calculate total cart weight
        $total_weight = 0.0;
        if ( WC()->cart ) {
            foreach ( WC()->cart->get_cart_contents() as $cart_item ) {
                $product = $cart_item['data'];
                $weight  = $product->get_weight();

                if ( $product->is_type( 'variation' ) && empty( $weight ) ) {
                    $parent  = wc_get_product( $product->get_parent_id() );
                    $weight  = $parent ? $parent->get_weight() : 0;
                }

                $total_weight += (float) $weight * (int) $cart_item['quantity'];
            }
        }

        // Add a flag to the package that changes based on the 20kg threshold.
        // This makes WooCommerce generate a different package hash, busting the cache.
        $over_limit = $total_weight >= 20 ? 'yes' : 'no';

        foreach ( $packages as &$package ) {
            $package['yalidine_over_20kg'] = $over_limit;
        }

        return $packages;
    }

    public function getOverweight(\WC_Cart $cart, float $total_weight, int $shippingOverweight, array &$dimensions = null)
    {
        foreach ($cart->get_cart_contents() as $cart_item) {
            $product = $cart_item['data'];

            if ($product->is_type('variation')) {
                $weight = $product->get_weight();
                if (empty($weight)) {
                    $parent_product = wc_get_product($product->get_parent_id());
                    $weight = $parent_product->get_weight();
                }
            } else {
                $weight = $product->get_weight();
            }

            $total_weight += (float)$weight * (int)$cart_item['quantity'];

            // Accumulate dimensions if reference provided
            if ($dimensions !== null) {
                $dimensions['length'] += (float) $product->get_length() * (int) $cart_item['quantity'];
                $dimensions['width']  += (float) $product->get_width()  * (int) $cart_item['quantity'];
                $dimensions['height'] += (float) $product->get_height() * (int) $cart_item['quantity'];
            }
        }


        if ($total_weight > $shippingOverweight) {
            $shippingOverweight = $total_weight;
        }
        return $shippingOverweight;
    }

    private function get_cities()
    {

    }

    private function get_wilayas()
    {
        $providers = BordereauGeneratorProviders::get_providers();

        $enabledProviders = get_option('wc-bordereau-generator_allowed_providers');
        $provider = 'yalidine';

        if (is_array($enabledProviders)) {

            if (in_array('guepex', $enabledProviders)) {
                $provider = 'guepex';
            }

            if (in_array('wecanservices', $enabledProviders)) {
                $provider = 'wecanservices';
            }

            if (in_array('speedmail', $enabledProviders)) {
                $provider = 'speedmail';
            }

            if (in_array('easyandspeed', $enabledProviders)) {
                $provider = 'easyandspeed';
            }

            if (in_array('guepex', $enabledProviders) && in_array('yalidine', $enabledProviders)) {
                $provider = 'yalidine';
            }
        }

        $selectedProvider = $providers[$provider];

        $path = Functions::get_path($selectedProvider['slug'] . "_wilaya" . WC_BORDEREAU_GENERATOR_VERSION . ".json");

        if (file_exists($path)) {
            $wilayas = json_decode(file_get_contents($path), true);
        } else {
            $response = $this->get_wilaya_from_provider();
            file_put_contents($path, json_encode($response));
            return $response;
        }

        return $wilayas;
    }

    private function get_wilaya_from_provider()
    {
        $providers = BordereauGeneratorProviders::get_providers();
        $enabledProviders = get_option('wc-bordereau-generator_allowed_providers');
        $provider = 'yalidine';

        if (is_array($enabledProviders)) {

            if (in_array('guepex', $enabledProviders)) {
                $provider = 'guepex';
            }

            if (in_array('wecanservices', $enabledProviders)) {
                $provider = 'wecanservices';
            }

            if (in_array('speedmail', $enabledProviders)) {
                $provider = 'speedmail';
            }

            if (in_array('easyandspeed', $enabledProviders)) {
                $provider = 'easyandspeed';
            }

            if (in_array('guepex', $enabledProviders) && in_array('yalidine', $enabledProviders)) {
                $provider = 'yalidine';
            }
        }

        $selectedProvider = $providers[$provider];

        $request = new YalidineRequest($selectedProvider);
        return $request->get($selectedProvider['api_url'] . '/wilayas?page_size=1000');
    }


    /**
     * Getting States depending on the shipping company
     * @return array
     * @since 4.0.0
     */
    private function get_states(bool $has_48_states = false, $method = null)
    {

        $states = array(
                'DZ-01' => _x("01  Adrar أدرار", "states", "woo-bordereau-generator"),
                'DZ-02' => _x("02	Chlef  الشلف", "states", "woo-bordereau-generator"),
                'DZ-03' => _x("03	Laghouat	الأغواط", "states", "woo-bordereau-generator"),
                'DZ-04' => _x("04	Oum El Bouaghi	أم البواقي", "states", "woo-bordereau-generator"),
                'DZ-05' => _x("05	Batna	باتنة", "states", "woo-bordereau-generator"),
                'DZ-06' => _x("06	Béjaïa	بجاية", "states", "woo-bordereau-generator"),
                'DZ-07' => _x("07	Biskra	بسكرة", "states", "woo-bordereau-generator"),
                'DZ-08' => _x("08	Béchar	بشار", "states", "woo-bordereau-generator"),
                'DZ-09' => _x("09	Blida	البليدة", "states", "woo-bordereau-generator"),
                'DZ-10' => _x("10	Bouïra	البويرة	", "states", "woo-bordereau-generator"),
                'DZ-11' => _x("11	Tamanrasset	تمنراست", "states", "woo-bordereau-generator"),
                'DZ-12' => _x("12	Tébessa	تبسة", "states", "woo-bordereau-generator"),
                'DZ-13' => _x("13	Tlemcen	تلمسان", "states", "woo-bordereau-generator"),
                'DZ-14' => _x("14	Tiaret	تيارت", "states", "woo-bordereau-generator"),
                'DZ-15' => _x("15	Tizi Ouzou	تيزي وزو", "states", "woo-bordereau-generator"),
                'DZ-16' => _x("16	Alger	الجزائر", "states", "woo-bordereau-generator"),
                'DZ-17' => _x("17	Djelfa	الجلفة", "states", "woo-bordereau-generator"),
                'DZ-18' => _x("18	Jijel	جيجل", "states", "woo-bordereau-generator"),
                'DZ-19' => _x("19	Sétif	سطيف", "states", "woo-bordereau-generator"),
                'DZ-20' => _x("20	Saïda	سعيدة", "states", "woo-bordereau-generator"),
                'DZ-21' => _x("21	Skikda	سكيكدة", "states", "woo-bordereau-generator"),
                'DZ-22' => _x("22	Sidi Bel Abbès	سيدي بلعباس", "states", "woo-bordereau-generator"),
                'DZ-23' => _x("23	Annaba	عنابة", "states", "woo-bordereau-generator"),
                'DZ-24' => _x("24	Guelma	قالمة", "states", "woo-bordereau-generator"),
                'DZ-25' => _x("25	Constantine	قسنطينة", "states", "woo-bordereau-generator"),
                'DZ-26' => _x("26	Médéa	المدية", "states", "woo-bordereau-generator"),
                'DZ-27' => _x("27	Mostaganem	مستغانم", "states", "woo-bordereau-generator"),
                'DZ-28' => _x("28	M\'Sila	المسيلة", "states", "woo-bordereau-generator"),
                'DZ-29' => _x("29	Mascara	معسكر", "states", "woo-bordereau-generator"),
                'DZ-30' => _x("30	Ouargla	ورقلة", "states", "woo-bordereau-generator"),
                'DZ-31' => _x("31	Oran	وهران", "states", "woo-bordereau-generator"),
                'DZ-32' => _x("32	El Bayadh	البيض", "states", "woo-bordereau-generator"),
                'DZ-33' => _x("33	Illizi	اليزي", "states", "woo-bordereau-generator"),
                'DZ-34' => _x("34	Bordj Bou Arréridj	برج بوعريريج", "states", "woo-bordereau-generator"),
                'DZ-35' => _x("35	Boumerdès	بومرداس", "states", "woo-bordereau-generator"),
                'DZ-36' => _x("36	El Tarf	الطارف", "states", "woo-bordereau-generator"),
                'DZ-37' => _x("37	Tindouf	تندوف", "states", "woo-bordereau-generator"),
                'DZ-38' => _x("38	Tissemsilt	تسمسيلت", "states", "woo-bordereau-generator"),
                'DZ-39' => _x("39	El Oued	الوادي", "states", "woo-bordereau-generator"),
                'DZ-40' => _x("40	Khenchela	خنشلة", "states", "woo-bordereau-generator"),
                'DZ-41' => _x("41	Souk Ahras	سوق أهراس", "states", "woo-bordereau-generator"),
                'DZ-42' => _x("42	Tipaza	تيبازة", "states", "woo-bordereau-generator"),
                'DZ-43' => _x("43	Mila	ميلة", "states", "woo-bordereau-generator"),
                'DZ-44' => _x("44	Aïn Defla	عين الدفلى", "states", "woo-bordereau-generator"),
                'DZ-45' => _x("45	Naâma	النعامة", "states", "woo-bordereau-generator"),
                'DZ-46' => _x("46	Aïn Témouchent	عين تموشنت", "states", "woo-bordereau-generator"),
                'DZ-47' => _x("47	Ghardaïa	غرداية", "states", "woo-bordereau-generator"),
                'DZ-48' => _x("48	Relizane	غليزان", "states", "woo-bordereau-generator"),
                'DZ-49' => _x("49	Timimoun	تيميمون", "states", "woo-bordereau-generator"),
                'DZ-50' => _x("50	Bordj Baji Mokhtar	برج باجي مختار", "states", "woo-bordereau-generator"),
                'DZ-51' => _x("51	Ouled Djellal	أولاد جلال", "states", "woo-bordereau-generator"),
                'DZ-52' => _x("52	Béni Abbès	بني عباس", "states", "woo-bordereau-generator"),
                'DZ-53' => _x("53	In Salah	عين صالح", "states", "woo-bordereau-generator"),
                'DZ-54' => _x("54	In Guezzam	عين قزّام", "states", "woo-bordereau-generator"),
                'DZ-55' => _x("55	Touggourt	تقرت", "states", "woo-bordereau-generator"),
                'DZ-56' => _x("56	Djanet	جانت", "states", "woo-bordereau-generator"),
                'DZ-57' => _x("57	El M'Ghair	المغير", "states", "woo-bordereau-generator"),
                'DZ-58' => _x("58	El Menia	المنيعة", "states", "woo-bordereau-generator")
        );

        return $states;
    }

    public function custom_select_cities()
    {

    }

    function add_custom_shipping_info_fragment($fragments) {

        if (get_option('wc_bordreau_enable-extra-shipping-information') == 'true') {
            // Get available shipping methods
            $available_methods = WC()->shipping()->get_packages()[0]['rates'];

            // Start output buffering
            ob_start();

            // Open the shipping methods list
            echo '<ul id="shipping_method" class="woocommerce-shipping-methods">';

            // Loop through each shipping method
            foreach ($available_methods as $method) {
                $method_id = $method->get_id();
                $method_label = $method->get_label();
                $shipping_method_instance = $this->get_shipping_method_instance($method_id);

                // Get the shipping cost and format it
                $cost = $method->get_cost();
                $formatted_cost = wc_price($cost);

                // Output the shipping method HTML
                echo '<li>';
                echo '<input type="radio" name="shipping_method[0]" data-index="0" id="shipping_method_0_' . esc_attr($method_id) . '" value="' . esc_attr($method_id) . '" class="shipping_method" ' . checked($method->id, WC()->session->get('chosen_shipping_methods')[0], false) . '>';
                echo '<label for="shipping_method_0_' . esc_attr($method_id) . '">' . $method_label . ' - ' . $formatted_cost . '</label>';

                // Add custom information for local pickup methods
                if (str_contains($method_id, 'local_pickup')) {
                    if (method_exists($shipping_method_instance, 'get_option')) {
                        $address = $shipping_method_instance->get_option('address');
                        $phone = $shipping_method_instance->get_option('phone');
                        $maps = $shipping_method_instance->get_option('maps');
                        $gps = $shipping_method_instance->get_option('gps');

                        if (!empty($address) || !empty($phone) || !empty($maps) || !empty($gps)) {
                            echo '<div class="boredreau-additional-info" style="" data-method-id="' . esc_attr($method_id) . '">';

                            if (!empty($address)) {
                                echo '<p><strong>' . __('Adresse', 'woo-bordereau-generator') . '</strong> ' . esc_html($address);
                                if (!empty($gps)) {
                                    $valid_coordinates = $this->validate_gps_coordinates($gps);
                                    if ($valid_coordinates) {
                                        echo ' <a href="#" class="map-icon" data-map="' . esc_attr($valid_coordinates) . '">';
                                        echo __('Voir sur le map', 'woo-bordereau-generator');
                                        echo '</a>';
                                    } else {
                                        echo ' <a target="_blank" href="'.$gps.'">';
                                        echo __('Voir sur le map', 'woo-bordereau-generator');
                                        echo '</a>';
                                    }
                                }
                                echo '</p>';
                            }

                            if (!empty($phone)) {
                                echo '<p><strong>' . __('Téléphone', 'woo-bordereau-generator') . '</strong> ' . esc_html($phone) . '</p>';
                            }

                            if (!empty($maps)) {
                                echo '<p><strong>' . __('Adresse', 'woo-bordereau-generator') . '</strong> ' . esc_html($address);
                                if (!empty($maps)) {
                                    echo ' <a target="_blank" href="'.$maps.'">';
                                    echo __('Voir sur le map', 'woo-bordereau-generator');
                                    echo '</a>';
                                }
                                echo '</p>';
                            }

                            echo '</div>';
                        }
                    }
                }

                echo '</li>';
            }

            // Close the shipping methods list
            echo '</ul>';

            // Get the buffered content
            $shipping_methods_html = ob_get_clean();

            // Add the modified shipping methods HTML to the fragments
            $fragments['.woocommerce-shipping-methods'] = $shipping_methods_html;

        }

        return $fragments;
    }


    function validate_gps_coordinates($gps_string) {
        // Remove any whitespace
        $gps_string = preg_replace('/\s+/', '', $gps_string);

        // Match the pattern: two numbers (positive or negative) with decimal points, separated by a comma
        if (preg_match('/^(-?\d+(\.\d+)?),(-?\d+(\.\d+)?)$/', $gps_string, $matches)) {
            $lat = floatval($matches[1]);
            $lng = floatval($matches[3]);

            // Check if the values are within valid ranges
            if ($lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                return $lat . ',' . $lng;
            }
        }

        return false;
    }

    function get_shipping_method_instance($method_id) {
        $shipping_zones = WC_Shipping_Zones::get_zones();
        foreach ($shipping_zones as $zone_id => $zone) {
            $shipping_methods = $zone['shipping_methods'];
            foreach ($shipping_methods as $shipping_method) {
                if ($shipping_method->id . ':' . $shipping_method->instance_id === $method_id) {
                    return $shipping_method;
                }
            }
        }

        // Check methods not tied to a specific zone
        $shipping_methods = WC_Shipping_Zones::get_zone(0)->get_shipping_methods();
        foreach ($shipping_methods as $shipping_method) {
            if ($shipping_method->id . ':' . $shipping_method->instance_id === $method_id) {
                return $shipping_method;
            }
        }

        return null;
    }

    public function add_map_popup_script()
    {
        ?>
        <script>
            jQuery(document).ready(function($) {
                // Create popup element
                var $popup = $('<div id="map-popup" style="display:none; position:fixed; top:50%; left:50%; transform:translate(-50%, -50%); z-index:9999; background:white; padding:20px; border-radius:5px; box-shadow:0 0 10px rgba(0,0,0,0.5);"><button id="close-popup" style="position:absolute; top:5px; right:5px;">X</button><iframe width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy"></iframe></div>').appendTo('body');

                // Handle map icon click
                $(document).on('click', '.map-icon', function() {
                    var mapData = $(this).data('map');
                    if (mapData) {
                        var iframeSrc = getMapSource(mapData);
                        $('#map-popup iframe').attr('src', iframeSrc);
                        $('#map-popup').show();
                    }
                });

                // Handle close button click
                $('#close-popup').on('click', function() {
                    $('#map-popup').hide();
                });

                // Function to determine the correct map source
                function getMapSource(mapData) {
                    if (isValidCoordinates(mapData)) {
                        return 'https://maps.google.com/maps?q=' + mapData + '&hl=es;z=14&output=embed';
                    } else if (isValidGoogleMapsUrl(mapData)) {
                        return mapData.replace('/maps/place/', '/maps?q=').replace('/maps/', '/maps?q=') + '&output=embed';
                    } else {
                        console.error('Invalid map data provided');
                        return '';
                    }
                }

                // Function to validate coordinates
                function isValidCoordinates(str) {
                    var regex = /^[-+]?([1-8]?\d(\.\d+)?|90(\.0+)?),\s*[-+]?(180(\.0+)?|((1[0-7]\d)|([1-9]?\d))(\.\d+)?)$/;
                    return regex.test(str);
                }

                // Function to validate Google Maps URL
                function isValidGoogleMapsUrl(str) {
                    return str.startsWith('https://maps.app.goo.gl/') || str.startsWith('https://www.google.com/maps/');
                }
            });
        </script>
        <?php
    }

    /**
     * Toggle billing city field based on shipping method selection
     * Hides city field for local_pickup when city_optional setting is enabled
     * 
     * @since 4.0.0
     */
    public function toggle_billing_city_script()
    {
        // Only run on checkout pages
        if (!is_checkout() && !is_wc_endpoint_url('order-pay')) {
            return;
        }

        // Only apply when city_optional setting is enabled
        $city_optional = get_option('wc_bordreau_city-optional', 'false') === 'true';
        if (!$city_optional) {
            return;
        }
        ?>
        <script type="text/javascript">
        jQuery(function($) {
            function toggleBillingCity() {
                var shippingMethod = $('input[name^="shipping_method"]:checked').val();
                
                // If no radio is checked, try getting from select or hidden input
                if (!shippingMethod) {
                    var $shippingInput = $('input[name^="shipping_method"]');
                    if ($shippingInput.length) {
                        shippingMethod = $shippingInput.val();
                    }
                }
                
                if (shippingMethod && shippingMethod.indexOf('local_pickup') === 0) {
                    // Hide billing city for local pickup (city is determined by the agency/center)
                    $('#billing_city_field').hide();
                    $('#billing_city_field').removeClass('validate-required');
                    $('#billing_city').removeAttr('required').removeAttr('aria-required');
                } else {
                    // Show and require billing city for other methods (like flat_rate)
                    $('#billing_city_field').show();
                    $('#billing_city_field').addClass('validate-required');
                    $('#billing_city').attr('required', 'required').attr('aria-required', 'true');
                }
            }
            
            // Run on page load
            toggleBillingCity();
            
            // Run when shipping method changes
            $(document.body).on('change', 'input[name^="shipping_method"]', function() {
                toggleBillingCity();
            });
            
            // Run after checkout update (WooCommerce AJAX updates)
            $(document.body).on('updated_checkout', function() {
                toggleBillingCity();
            });
        });
        </script>
        <?php
    }
}
