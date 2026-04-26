<?php

namespace WooBordereauGenerator\Admin\Shipping;

/**
 * ZimoExpress Settings Manager.
 *
 * This class handles the configuration settings for the ZimoExpress shipping provider,
 * including API settings, shipping rates, zones management, and package tracking status.
 *
 * @package    WC_Bordereau_Generator
 * @subpackage WC_Bordereau_Generator/admin/shipping
 * @author     Boukraa Mohamed <bm2ilabs@gmail.com>
 */


use WC_Shipping_Zone;
use WC_Shipping_Zones;
use WooBordereauGenerator\Admin\BordereauGeneratorAdmin;
use WooBordereauGenerator\Functions;
use WooBordereauGenerator\Helpers;
use WP_Error;

/**
 * ZimoExpress settings management class.
 *
 * Manages the settings and configuration for the ZimoExpress shipping provider,
 * including API credentials, shipping zones, and bulk tracking.
 */
class ZimoExpressSettings
{
    /**
     * Provider configuration data.
     *
     * @var array
     */
    private $provider;
    
    /**
     * ZimoExpress API username/ID.
     *
     * @var string|null
     */
    protected $api_id;
    
    /**
     * ZimoExpress API token/password.
     *
     * @var string|null
     */
    private $api_token;

    /**
     * Constructor.
     * 
     * Initializes a new ZimoExpress settings manager with the provider configuration.
     *
     * @param array $provider The provider configuration array.
     */
    public function __construct(array $provider)
    {
        $this->api_id = get_option('zimoexpress_username');
        $this->api_token = get_option('zimoexpress_password');
        $this->provider = $provider;
    }

    /**
     * Get the provider settings configuration.
     * 
     * Returns an array of settings fields for the ZimoExpress provider
     * that can be used to render the settings form.
     *
     * @return array Settings field configuration.
     */
    public function get_settings()
    {

        $fields = [
            array(
                'title' => __($this->provider['name'] . ' API Settings', 'woocommerce'),
                'type' => 'title',
                'id' => 'store_address',
            ),
        ];

        foreach ($this->provider['fields'] as $key => $filed) {
            $fields[$key] = array(
                'name' => __($filed['name'], 'woo-bordereau-generator'),
                'id' => $filed['value'],
                'type' => 'text',
                'desc' => $filed['desc'],
                'desc_tip' => true,
            );
        }

        $fields = $fields + [
                'section_end' => array(
                    'type' => 'sectionend',
                    'id' => $this->provider['slug'] . '_section_end'
                )
            ];

        return $fields;
    }


    /**
     * Track multiple packages in bulk.
     * 
     * Retrieves tracking status for multiple tracking numbers in a single
     * API request and formats the results.
     *
     * @param array $codes Array of tracking codes to check.
     * @return array|false Formatted tracking statuses or false on failure.
     */
    public function bulk_track(array $codes)
    {
        $url = $this->provider['api_url'] . '/api/v1/packages/status';
        $request = new \WooBordereauGenerator\Admin\Shipping\ZimoExpressRequest($this->provider);
        $content = $request->get($url, false, [
            'packages' => $codes,
        ]);

        return $this->format_tracking($content);
    }

    /**
     * Get default status labels.
     * 
     * Returns an array of status codes and their corresponding labels
     * for use in tracking display and status management.
     *
     * @return array Status codes and labels.
     */
    private function status(): array
    {

        return [
            "EN PREPARATION" => "EN PREPARATION",
            "PRÊT À EXPÉDIER" => "PRÊT À EXPÉDIER",
            "EXPEDIE" => "EXPEDIE",
            "VERS WILAYA ( SAC )" => "VERS WILAYA ( SAC )",
            "CENTRE ( HUB )" => "CENTRE ( HUB )",
            "TRANSFERT (SAC)" => "TRANSFERT (SAC)",
            "SORTIE EN LIVRAISON" => "SORTIE EN LIVRAISON",
            "LIVRÉ" => "LIVRÉ",
            "ÉCHEC DE LIVRAISON" => "ÉCHEC DE LIVRAISON",
            "ALERT" => "ALERT",
            "REPORTER (Date)" => "REPORTER (Date)",
            "EN ATTENTE" => "EN ATTENTE",
            "TENTATIVE ÉCHOUÉE 1" => "TENTATIVE ÉCHOUÉE 1",
            "TENTATIVE ÉCHOUÉE 2" => "TENTATIVE ÉCHOUÉE 2",
            "TENTATIVE ÉCHOUÉE 3" => "TENTATIVE ÉCHOUÉE 3",
            "RETOUR VERS CENTRE" => "RETOUR VERS CENTRE",
            "RETOURNEE AU CENTRE" => "RETOURNEE AU CENTRE",
            "RETOUR A RETIRER" => "RETOUR A RETIRER",
            "RETOUR VERS VENDEUR (SAC)" => "RETOUR VERS VENDEUR (SAC)",
            "RETOURNEE AU VENDEUR (SAC)" => "RETOURNEE AU VENDEUR (SAC)",
            "SOCIETE PARTENAIRE" => "SOCIETE PARTENAIRE",
            "REFUSÉ" => "REFUSÉ",
            "PICKUP" => "PICKUP",
            "En Dispatche" => "En Dispatche",
            "Dispatché" => "Dispatché",
            "WAREHOUSE EN PREPARATION" => "WAREHOUSE EN PREPARATION",
            "WAREHOUSE HORS STOCK" => "WAREHOUSE HORS STOCK",
            "WAREHOUSE PRET" => "WAREHOUSE PRET",
            "WAREHOUSE RETOURNÉE" => "WAREHOUSE RETOURNÉE",
            "WAREHOUSE DEMANDE ANNULATION" => "WAREHOUSE DEMANDE ANNULATION",
            "WAREHOUSE ANNULÉE" => "WAREHOUSE ANNULÉE",
            "WAREHOUSE RETOURNÉE ENDOMAGÉ" => "WAREHOUSE RETOURNÉE ENDOMAGÉ",
            "PAS ENCORE RAMASSÉ" => "PAS ENCORE RAMASSÉ",
            "DROPSHIP EN PREPARATION" => "DROPSHIP EN PREPARATION",
            "DROPSHIP PRET" => "DROPSHIP PRET",
            "ECHANGE A RETIRER" => "ECHANGE A RETIRER",
            "DROPSHIPS RETOURNEÉ" => "DROPSHIPS RETOURNEÉ",
            "DROPSHIPS RETOURNEÉ ENDOMMAGÉ" => "DROPSHIPS RETOURNEÉ ENDOMMAGÉ",
            "ECHANGE COLLECTÉ" => "ECHANGE COLLECTÉ",
            "AU CENTRE" => "AU CENTRE",
            "DROPSHIPS DEMANDE ANNULATION" => "DROPSHIPS DEMANDE ANNULATION",
            "DROPSHIPS ANNULÉE" => "DROPSHIPS ANNULÉE",
            "FRET EN PREPARATION" => "FRET EN PREPARATION",
            "FRET PRET" => "FRET PRET"
        ];
    }

    /**
     * Format tracking response data.
     * 
     * Processes the tracking API response to create an associative array
     * of tracking codes and their current statuses.
     *
     * @param array|null $json The tracking API response.
     * @return array Formatted tracking data (tracking code => status).
     */
    private function format_tracking($json)
    {
        if ($json) {

            $final = [];
            foreach ($json as $key => $value) {
                $final[$value['tracking_code']] = $value['status'];
            }

            return $final;
        }

        return [];
    }


    /**
     * Import shipping classes from ZimoExpress.
     * 
     * Configures WooCommerce shipping methods based on ZimoExpress data.
     * This is a placeholder method for future implementation.
     *
     * @param string $flat_rate_label       Label for flat rate shipping.
     * @param string $pickup_local_label    Label for local pickup shipping.
     * @param bool   $flat_rate_enabled     Whether flat rate shipping is enabled.
     * @param bool   $pickup_local_enabled  Whether local pickup is enabled.
     * @param bool   $agency_import_enabled Whether agency import is enabled (optional).
     * @param bool   $is_economic           Whether to use economic shipping rates (optional).
     * @return void
     * @since 1.6.5
     */
    public function import_shipping_class($flat_rate_label,
	    $pickup_local_label,
	    $flat_rate_enabled,
	    $pickup_local_enabled,
	    $agency_import_enabled = null,
	    $is_eccnomic = null,
	    $is_flexible = null,
	    $is_yalidine_price = null,
	    $is_maystro_price = null,
	    $is_zr_express_price = null,
	    $flexible_label = null,
	    $economic_label = null,
	    $zr_express_label = null,
	    $maystro_label = null,
	    $yalidine_label = null
    )
    {
	    if ($pickup_local_enabled) {
		    if ($pickup_local_label) {
			    update_option($this->provider['slug'] . '_pickup_local_label', $pickup_local_label);
		    }
	    }

	    if ($flat_rate_enabled) {
		    if ($flat_rate_label) {
			    update_option($this->provider['slug'] . '_flat_rate_label', $flat_rate_label);
		    }
	    }

	    if ($is_flexible) {
		    if ($flexible_label) {
			    update_option($this->provider['slug'] . '_flexible_label', $flexible_label);
		    }
	    }

	    if ($is_eccnomic) {
		    if ($economic_label) {
			    update_option($this->provider['slug'] . '_economic_label', $economic_label);
		    }
	    }

	    if ($is_maystro_price) {
		    if ($maystro_label) {
			    update_option($this->provider['slug'] . '_maystro_label', $maystro_label);
		    }
	    }

	    if ($is_zr_express_price) {
		    if ($zr_express_label) {
			    update_option($this->provider['slug'] . '_zr_express_label', $zr_express_label);
		    }
	    }

	    if ($is_yalidine_price) {
		    if ($yalidine_label) {
			    update_option($this->provider['slug'] . '_yalidine_label', $yalidine_label);
		    }
	    }

	    $all_results = $this->get_fees();

	    if ($agency_import_enabled) {
		    update_option($this->provider['slug'] . '_agencies_import', true);
	    }

	    if (get_option('shipping_zones_grouped') && get_option('shipping_zones_grouped') == 'yes') {
		    $this->shipping_zone_grouped($all_results, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled);
	    } else {
		    $this->shipping_zone_ungrouped($all_results,
			    $flat_rate_label,
			    $pickup_local_label,
			    $flat_rate_enabled,
			    $pickup_local_enabled,
			    $agency_import_enabled,
			    $is_eccnomic,
			    $is_flexible,
			    $is_zr_express_price,
			    $is_maystro_price,
			    $is_yalidine_price,
			    $flexible_label,
			    $economic_label,
			    $zr_express_label,
			    $maystro_label,
			    $yalidine_label
		    );
	    }


	    wp_send_json_success(
		    array(
			    'message' => 'success',
		    )
	    );
    }

    /**
     * Configure shipping zones with grouped pricing.
     * 
     * Creates or updates shipping zones with grouped pricing structure.
     * This is a placeholder method for future implementation.
     *
     * @param array  $livraison            Delivery pricing data.
     * @param string $flat_rate_label      Label for flat rate shipping.
     * @param string $pickup_local_label   Label for local pickup shipping.
     * @param bool   $flat_rate_enabled    Whether flat rate shipping is enabled.
     * @param bool   $pickup_local_enabled Whether local pickup is enabled.
     * @return void
     */
    private function shipping_zone_grouped($livraison, $flat_rate_label, $pickup_local_label, $flat_rate_enabled, $pickup_local_enabled)
    {

    }

    /**
     * Configure shipping zones with ungrouped pricing.
     * 
     * Creates or updates shipping zones based on individual wilaya (province) rates.
     * Configures both home delivery and pickup options per wilaya.
     *
     * @param array  $livraison            Delivery pricing data.
     * @param string $flat_rate_label      Label for flat rate shipping.
     * @param string $pickup_local_label   Label for local pickup shipping.
     * @param bool   $flat_rate_enabled    Whether flat rate shipping is enabled.
     * @param bool   $pickup_local_enabled Whether local pickup is enabled.
     * @return void
     */
    private function shipping_zone_ungrouped(
		$livraison,
		$flat_rate_label,
		$pickup_local_label,
		$flat_rate_enabled,
		$pickup_local_enabled,
		$agency_import_enabled,
		$is_economic,
		$is_flexible,
		$is_zr_express_price,
		$is_maystro_price,
		$is_yalidine_price,
	    $flexible_label,
		$economic_label,
		$zr_express_label,
		$maystro_label,
		$yalidine_label
    )
    {

        // Set memory limit to -1 (unlimited) and set time limit to 0 (unlimited)
        ini_set('memory_limit', '-1');
		set_time_limit(0);

        $zones = [];

        $shipping_method_local_pickup = null;
        $shipping_method_flat_rate = null;

        $i = 1;
        foreach ($livraison as $key => $wilaya) {

            $priceStopDesk = $wilaya['desk'] ?? 0;
            $priceFlatRate = $wilaya['home_express'] ?? 0;
            $flexibleRate = $wilaya['home_flexible'] ?? 0;
            $yalidineRate = $wilaya['yalidine_price'] ?? 0;
            $economicRate = $wilaya['economy_price'] ?? 0;
            $zrExpressRate = $wilaya['zr_express_price'] ?? 0;
            $maystroRate = $wilaya['maystro_price'] ?? 0;

            $wilaya_name = $wilaya['wilaya_name'];

            // Get all shipping zones
            $zones = WC_Shipping_Zones::get_zones();

            // Iterate through the zones and find the one with the matching name
            $found_zone = null;
            foreach ($zones as $zone) {
                if ($zone['zone_locations'][0]->code == 'DZ:DZ-' . str_pad($wilaya['wilaya_id'], 2, '0', STR_PAD_LEFT)) {
                    $found_zone = $zone;
                    break;
                }
            }

            if (!$found_zone) {
                $zone = new WC_Shipping_Zone();
                $zone->set_zone_name(ucfirst(Helpers::normalizeCityName($wilaya_name)));
                $zone->set_zone_order($key);
                $zone->add_location('DZ:DZ-' . str_pad($wilaya['wilaya_id'], 2, '0', STR_PAD_LEFT), 'state');
                $zone->save();
            } else {
                $zone = WC_Shipping_Zones::get_zone($found_zone['id']);
            }

            if ($priceFlatRate && $flat_rate_enabled && $priceFlatRate > 0) {

                $flat_rate_name = 'flat_rate_zimoexpress';
                if (is_hanout_enabled()) {
                    $flat_rate_name = 'flat_rate';
                }

                $instance_flat_rate = $zone->add_shipping_method($flat_rate_name);
                $shipping_method_flat_rate = WC_Shipping_Zones::get_shipping_method($instance_flat_rate);
                $shipping_method_configuration_flat_rate = [
                    'woocommerce_' . $flat_rate_name . '_title' => $flat_rate_label ?? __('Flat rate', 'woo-bordereau-generator'),
                    'woocommerce_' . $flat_rate_name . '_cost' => $priceFlatRate,
                    'woocommerce_' . $flat_rate_name . '_type' => 'class',
                    'woocommerce_' . $flat_rate_name . '_delivery_type' => 'Express',
                    'woocommerce_' . $flat_rate_name . '_provider' => $this->provider['slug'],
                    'instance_id' => $instance_flat_rate,
                    'method_id' => $this->provider['slug'] . '_flat_rate',
                ];

                if ($is_economic) {
                    $shipping_method_configuration_flat_rate['woocommerce_' . $flat_rate_name . '_is_economic'] = "yes";
                } else {
                    $shipping_method_configuration_flat_rate['woocommerce_' . $flat_rate_name . '_is_economic'] = null;
                }

                $_REQUEST['instance_id'] = $instance_flat_rate;
                $shipping_method_flat_rate->set_post_data($shipping_method_configuration_flat_rate);
                $shipping_method_flat_rate->update_option('provider', $this->provider['slug']);
                $shipping_method_flat_rate->process_admin_options();

            }

	        if ($is_flexible && $flexibleRate > 0) {

		        $flat_rate_name = 'flat_rate_zimoexpress';
		        if (is_hanout_enabled()) {
			        $flat_rate_name = 'flat_rate';
		        }

		        $instance_flexible_rate = $zone->add_shipping_method($flat_rate_name);
		        $shipping_method_flexible_rate = WC_Shipping_Zones::get_shipping_method($instance_flexible_rate);
		        $shipping_method_configuration_flexible_rate = [
			        'woocommerce_' . $flat_rate_name . '_title' => $flexible_label ?? __('Flexible rate', 'woo-bordereau-generator'),
			        'woocommerce_' . $flat_rate_name . '_cost' => $flexibleRate,
			        'woocommerce_' . $flat_rate_name . '_type' => 'class',
			        'woocommerce_' . $flat_rate_name . '_delivery_type' => 'Flexible',
			        'woocommerce_' . $flat_rate_name . '_provider' => $this->provider['slug'],
			        'instance_id' => $instance_flexible_rate,
			        'method_id' => $this->provider['slug'] . '_flat_rate_flexible',
		        ];

		        $_REQUEST['instance_id'] = $instance_flexible_rate;
		        $shipping_method_flexible_rate->set_post_data($shipping_method_configuration_flexible_rate);
		        $shipping_method_flexible_rate->update_option('provider', $this->provider['slug']);
		        $shipping_method_flexible_rate->process_admin_options();

	        }

	        if ($is_economic && $economicRate > 0) {

		        $flat_rate_name = 'flat_rate_zimoexpress';
		        if (is_hanout_enabled()) {
			        $flat_rate_name = 'flat_rate';
		        }

		        $instance_economic_rate = $zone->add_shipping_method($flat_rate_name);
		        $shipping_method_economic_rate = WC_Shipping_Zones::get_shipping_method($instance_economic_rate);
		        $shipping_method_configuration_economic_rate = [
			        'woocommerce_' . $flat_rate_name . '_title' => $economic_label ?? __('Economic rate', 'woo-bordereau-generator'),
			        'woocommerce_' . $flat_rate_name . '_cost' => $economicRate,
			        'woocommerce_' . $flat_rate_name . '_type' => 'class',
			        'woocommerce_' . $flat_rate_name . '_delivery_type' => 'Economy Express',
			        'woocommerce_' . $flat_rate_name . '_provider' => $this->provider['slug'],
			        'instance_id' => $instance_economic_rate,
			        'method_id' => $this->provider['slug'] . '_flat_rate_economic',
		        ];


		        $_REQUEST['instance_id'] = $instance_economic_rate;
		        $shipping_method_economic_rate->set_post_data($shipping_method_configuration_economic_rate);
		        $shipping_method_economic_rate->update_option('provider', $this->provider['slug']);
		        $shipping_method_economic_rate->process_admin_options();

	        }

	        if ($is_zr_express_price && $zrExpressRate > 0) {

		        $flat_rate_name = 'flat_rate_zimoexpress';
		        if (is_hanout_enabled()) {
			        $flat_rate_name = 'flat_rate';
		        }

		        $instance_zr_express_rate = $zone->add_shipping_method($flat_rate_name);
		        $shipping_method_zr_express_rate = WC_Shipping_Zones::get_shipping_method($instance_zr_express_rate);
		        $shipping_method_configuration_zr_express_rate = [
			        'woocommerce_' . $flat_rate_name . '_title' => $zr_express_label ?? __('ZR Express rate', 'woo-bordereau-generator'),
			        'woocommerce_' . $flat_rate_name . '_cost' => $zrExpressRate,
			        'woocommerce_' . $flat_rate_name . '_type' => 'class',
			        'woocommerce_' . $flat_rate_name . '_delivery_type' => 'ZR Express',
			        'woocommerce_' . $flat_rate_name . '_provider' => $this->provider['slug'],
			        'instance_id' => $instance_zr_express_rate,
			        'method_id' => $this->provider['slug'] . '_flat_rate_zr_express',
		        ];

		        $_REQUEST['instance_id'] = $instance_zr_express_rate;
		        $shipping_method_zr_express_rate->set_post_data($shipping_method_configuration_zr_express_rate);
		        $shipping_method_zr_express_rate->update_option('provider', $this->provider['slug']);
		        $shipping_method_zr_express_rate->process_admin_options();

	        }

	        if ($is_maystro_price && $maystroRate > 0) {

		        $flat_rate_name = 'flat_rate_zimoexpress';
		        if (is_hanout_enabled()) {
			        $flat_rate_name = 'flat_rate';
		        }

		        $instance_maystro_rate = $zone->add_shipping_method($flat_rate_name);
		        $shipping_method_maystro_rate = WC_Shipping_Zones::get_shipping_method($instance_maystro_rate);
		        $shipping_method_configuration_maystro_rate = [
			        'woocommerce_' . $flat_rate_name . '_title' => $maystro_label ?? __('Maystro rate', 'woo-bordereau-generator'),
			        'woocommerce_' . $flat_rate_name . '_cost' => $maystroRate,
			        'woocommerce_' . $flat_rate_name . '_type' => 'class',
			        'woocommerce_' . $flat_rate_name . '_delivery_type' => 'Maystro_ delivery',
			        'woocommerce_' . $flat_rate_name . '_provider' => $this->provider['slug'],
			        'instance_id' => $instance_maystro_rate,
			        'method_id' => $this->provider['slug'] . '_flat_rate_maystro',
		        ];

		        $_REQUEST['instance_id'] = $instance_maystro_rate;
		        $shipping_method_maystro_rate->set_post_data($shipping_method_configuration_maystro_rate);
		        $shipping_method_maystro_rate->update_option('provider', $this->provider['slug']);
		        $shipping_method_maystro_rate->process_admin_options();

	        }

	        if ($is_yalidine_price && $yalidineRate > 0) {

		        $flat_rate_name = 'flat_rate_zimoexpress';
		        if (is_hanout_enabled()) {
			        $flat_rate_name = 'flat_rate';
		        }

		        $instance_yalidine_rate = $zone->add_shipping_method($flat_rate_name);
		        $shipping_method_yalidine_rate = WC_Shipping_Zones::get_shipping_method($instance_yalidine_rate);
		        $shipping_method_configuration_yalidine_rate = [
			        'woocommerce_' . $flat_rate_name . '_title' => $yalidine_label ?? __('Yalidine rate', 'woo-bordereau-generator'),
			        'woocommerce_' . $flat_rate_name . '_cost' => $yalidineRate,
			        'woocommerce_' . $flat_rate_name . '_type' => 'class',
			        'woocommerce_' . $flat_rate_name . '_delivery_type' => 'Yalidine',
			        'woocommerce_' . $flat_rate_name . '_provider' => $this->provider['slug'],
			        'instance_id' => $instance_yalidine_rate,
			        'method_id' => $this->provider['slug'] . '_flat_rate_yalidine',
		        ];

		        $_REQUEST['instance_id'] = $instance_yalidine_rate;
		        $shipping_method_yalidine_rate->set_post_data($shipping_method_configuration_yalidine_rate);
		        $shipping_method_yalidine_rate->update_option('provider', $this->provider['slug']);
		        $shipping_method_yalidine_rate->process_admin_options();
	        }

            if ($priceStopDesk && $pickup_local_enabled && $priceStopDesk > 0) {
	            if ($agency_import_enabled) {
		            $agencies = $this->get_all_centers(true, true);

		            $agenciesInWilaya = array_filter($agencies, function ($item) use ($wilaya) {
			            return $item['wilaya_name'] == $wilaya['wilaya_name'];
		            });

		            foreach ($agenciesInWilaya as $agency) {

			            $local_pickup_name = 'local_pickup_zimoexpress';
			            if (is_hanout_enabled()) {
				            $local_pickup_name = 'local_pickup';
			            }

			            // Use individual stopdesk price when available, fallback to wilaya-wide price
			            $price_local_pickup = isset($agency['price']) && $agency['price'] > 0
				            ? $agency['price']
				            : $wilaya['desk'];

			            if($price_local_pickup) {

				            if ($is_economic) {
					            $price_local_pickup = $wilaya['desk'];
				            }

				            // Log if using fallback price
				            if (!isset($agency['price']) || $agency['price'] <= 0) {
					            error_log(sprintf(
						            'Zimo: Using fallback price for stopdesk %s (%s) - Agency price: %s, Wilaya desk price: %s',
						            $agency['code'] ?? 'N/A',
						            $agency['name'] ?? 'N/A',
						            isset($agency['price']) ? $agency['price'] : 'N/A',
						            $wilaya['desk']
					            ));
				            }

				            $name = sprintf("%s %s", $pickup_local_label ?? __('Local Pickup', 'woo-bordereau-generator'), $agency['name']);

				            $instance_local_pickup = $zone->add_shipping_method($local_pickup_name);
				            $shipping_method_local_pickup = WC_Shipping_Zones::get_shipping_method($instance_local_pickup);
				            $shipping_method_configuration_local_pickup = array(
					            'woocommerce_'.$local_pickup_name.'_title' => $name,
					            'woocommerce_'.$local_pickup_name.'_cost' => $price_local_pickup,
					            'woocommerce_'.$local_pickup_name.'_type' => 'class',
					            'woocommerce_'.$local_pickup_name.'_address' => $agency['address'],
					            'woocommerce_'.$local_pickup_name.'_center_id' => (int) $agency['code'],
					            'woocommerce_'.$local_pickup_name.'_provider' => $this->provider['slug'],
					            'instance_id' => $instance_local_pickup,
				            );

				            if ($is_economic) {
					            $shipping_method_configuration_local_pickup['woocommerce_'.$local_pickup_name.'_is_economic'] = "yes";
				            } else {
					            $shipping_method_configuration_local_pickup['woocommerce_'.$local_pickup_name.'_is_economic'] = null;
				            }

				            $_REQUEST['instance_id'] = $instance_local_pickup;
				            $shipping_method_local_pickup->set_post_data($shipping_method_configuration_local_pickup);
				            $shipping_method_local_pickup->process_admin_options();
			            }

		            }
	            } else {
		            $local_pickup_name = 'local_pickup_zimoexpress';
		            if (is_hanout_enabled()) {
			            $local_pickup_name = 'local_pickup';
		            }

		            $instance_local_pickup = $zone->add_shipping_method($local_pickup_name);
		            $shipping_method_local_pickup = WC_Shipping_Zones::get_shipping_method($instance_local_pickup);
		            $shipping_method_configuration_local_pickup = array(
			            'woocommerce_' . $local_pickup_name . '_title' => $pickup_local_label ?? __('Local Pickup', 'woo-bordereau-generator'),
			            'woocommerce_' . $local_pickup_name . '_cost' => $priceStopDesk,
			            'woocommerce_' . $local_pickup_name . '_type' => 'class',
			            'woocommerce_' . $local_pickup_name . '_provider' => $this->provider['slug'],
			            'instance_id' => $instance_local_pickup,
		            );

		            $_REQUEST['instance_id'] = $instance_local_pickup;
		            $shipping_method_local_pickup->set_post_data($shipping_method_configuration_local_pickup);
		            $shipping_method_local_pickup->update_option('provider', $this->provider['slug']);
		            $shipping_method_local_pickup->process_admin_options();
	            }
            }
        }

        return [$shipping_method_flat_rate, $shipping_method_local_pickup];
    }


    /**
     * Add orders to ZimoExpress in bulk.
     * 
     * Creates packages for multiple orders in a single API request.
     *
     * @param array $order_ids Array of order IDs to add.
     * @return mixed|void
     */
    public function bulk_add_orders($order_ids)
    {

        $data = [];

        $orders = array();
        foreach ($order_ids as $id) {
            $order = wc_get_order($id);
            if ($order) {
                $orders[] = $order;
            }
        }

	    $key = $fields['total']['choices'][0]['name'] ?? null;
	    $total = get_option($key) ? get_option($key) : 'without-shipping';

	    $wilayas = $this->get_wilayas();

        foreach ($orders as $order) {

	        $center = false;
            $params = $order->get_data();
	        $shipping = new ZimoExpress($order, $this->provider);

	        $wilayaId = (int)str_replace("DZ-", "", $params['billing']['state']);
            $wilaya = array_filter($wilayas['data'], function ($item) use ($wilayaId) {
                return $item['id'] == $wilayaId;
            });

            $wilaya = reset($wilaya);

            $commune = $params['billing']['city'];

            $products = $params['line_items'];
            $productString = Helpers::generate_products_string($products);

            $free_shipping = 0;
            $stop_desk = 0;

            if ($order->has_shipping_method('local_pickup')) {
                $stop_desk = 1;
            }

            if ($order->has_shipping_method('free_shipping')) {
                $free_shipping = 1;
            }

	        $delivery_type = 'express';

	        foreach ($order->get_shipping_methods() as $shipping_item_id => $shipping_item) {
		        $method_id = $shipping_item->get_method_id(); // e.g., 'flat_rate_zimoexpress'
		        $instance_id = $shipping_item->get_instance_id();

		        // Check if this is your custom shipping method
		        if ($method_id === 'flat_rate_zimoexpress') {
			        // Get the shipping method instance
			        $shipping_method = WC_Shipping_Zones::get_shipping_method($instance_id);

			        if ($shipping_method) {
				        // Get the delivery_type from the shipping method settings
				        $delivery_type = $shipping_method->get_option('delivery_type', 'express');
			        }
		        }
	        }

            $phone = explode('/', $params['billing']['phone']);



	        if ($total == 'with-shipping') {
		        $price = $params['total'];
	        } elseif ($total === 'without-shipping') {
		        $price = $params['total'] - $params['shipping_total'];
	        }

            $note = stripslashes(get_option('wc_bordreau_default-shipping-note'));

			$type = get_option($this->provider['slug']. '_shipping_method');

			if (!$type) {
				$type = 'ecommerce';
			}

	        $shippingOverweight = Helpers::get_product_weight($order);

			if ($shippingOverweight) {
				$shippingOverweight =(int) ceil($shippingOverweight);
				$shippingOverweight = 1000 * $shippingOverweight;
			} else {
				$shippingOverweight = 1000;
			}

	        $data = array(
                'name' => $productString,
                'client_last_name' => !empty($order->get_billing_last_name()) ? $order->get_billing_last_name() : $order->get_billing_first_name(),
                'client_first_name' => !empty($order->get_billing_first_name()) ? $order->get_billing_first_name() : $order->get_billing_last_name(),
                'client_phone' => str_replace('+213', '0', $phone[0]),
                'address' => $params['billing']['address_1'] ?? $commune,
                'commune' => $commune,
                'wilaya' => $wilaya['name'],
                'order_id' => $order->get_order_number(),
                "weight" => $shippingOverweight,
                'delivery_type' => $stop_desk ? 'Point relais' : $delivery_type,
                'price' => $price,
                'free_delivery' => $free_shipping,
                'can_be_opened' => 1,
                'observation' => $note,
                'type' => $type,
                'detail' =>
                    array(
                        'is_assured' => 0,
                        'declared_value' => (int)$price,
                    ),
            );

			if (isset($phone[1])) {
				$data['client_phone2'] = str_replace('+213', '0', $phone[1]);
			}

	        if ($stop_desk) {
		        $center = (int) $order->get_meta('_billing_center_id', true);
		        if (! $center) {
			        $center = (int) get_post_meta($order->get_id(), '_billing_center_id', true);
		        }

		        $data['office_id'] = $center;
	        }


	        if (!$center && $stop_desk) {

		        $found = false;
		        // check if the name is an agence check in the centers list
		        $name = $order->get_shipping_method();
		        $centers = $this->get_all_centers();

		        if(is_array($centers)) {
			        $result = array_filter($centers, function($agency) use ($name) {
				        $prefix = get_option($this->provider['slug'] . '_pickup_local_label');
				        $agencyName = sprintf("%s %s", $prefix, $agency['name']);
				        return trim($agencyName) === trim($name);
			        });
			        $found = !empty($result) ? reset($result) : null;
		        }

		        if ($found) {
			        $foundStop = $found;
			        $data[$order->get_order_number()]['stopdesk_id'] = $foundStop['id'];
			        $center = $foundStop['id'];
			        $commune = $foundStop['commune_id'];

			        if (is_numeric($commune)) {
				        $communes = $shipping->get_communes($wilayaId, $stop_desk);
				        $foundCommune = array_filter($communes, function ($w) use ($commune) {
					        return $w['id'] == $commune;
				        });
				        if (count($foundCommune)) {
					        $foundCommune = reset($foundCommune);
					        $commune = $foundCommune['name'];
					        $data['commune'] = $commune;
				        }
			        } else {
				        $data['commune'] = $foundStop['commune_id'];
			        }
		        }
	        }

	        if (!$center && $stop_desk) {
		        $centers = $this->get_all_centers();
		        if(is_array($centers)) {
			        $result = array_filter($centers, function($agency) use ($commune) {
				        return $agency['commune_id'] == $commune;
			        });

			        $found = !empty($result) ? reset($result) : null;

			        $data['office_id'] = $found['id'];
			        $center = $found['id'];
		        }
	        }


			if(empty($data['address'])) {
				$data['address'] = 'ville';
			}

            $url = $this->provider['api_url'] . '/api/v1/packages';
            $request = new \WooBordereauGenerator\Admin\Shipping\ZimoExpressRequest($this->provider);
            $content = $request->post($url, $data);

			if (isset($content['errors']) && count($content['errors'])) {
				set_transient('order_bulk_add_error', $content['message'], 45);
				$logger = \wc_get_logger();
				$logger->error('Bulk Upload Error: ' . $content['message'], array('source' => WC_BORDEREAU_POST_TYPE));
				return;
			}

            if ($content['statusCode'] == 400) {
                set_transient('order_bulk_add_error', $content['message'], 45);
                $logger = \wc_get_logger();
                $logger->error('Bulk Upload Error: ' . $content['message'], array('source' => WC_BORDEREAU_POST_TYPE));
                return;
            }

            $trackingNumber = $content['data']['tracking_code'];
            $label = $content['data']['bordereau'];
            $uuid = $content['data']['uuid'];

            update_post_meta($order->get_id(), '_shipping_tracking_number', wc_clean($trackingNumber)); // backwork compatibilite
            update_post_meta($order->get_id(), '_shipping_zimo_express_uuid', wc_clean($uuid)); // backwork compatibilite
            update_post_meta($order->get_id(), '_shipping_tracking_label', wc_clean($label));
            update_post_meta($order->get_id(), '_shipping_label', wc_clean($label));
            update_post_meta($order->get_id(), '_shipping_tracking_method', $this->provider['slug']);

            $order->update_meta_data('_shipping_tracking_number', wc_clean($trackingNumber));
            $order->update_meta_data('_shipping_tracking_label', wc_clean($label));
            $order->update_meta_data('_shipping_zimo_express_uuid', wc_clean($uuid));
            $order->update_meta_data('_shipping_label', wc_clean($label));
            $order->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
            $order->save();

            Helpers::make_note($order, $trackingNumber, $this->provider['name']);

        }
    }

    /**
     * Get wilayas (provinces) data.
     * 
     * Retrieves a list of wilayas from the ZimoExpress API or a cached file.
     *
     * @return array Wilayas data.
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
     * Get communes (cities) data for a wilaya.
     * 
     * Retrieves a list of communes for a given wilaya from the ZimoExpress API or a cached file.
     *
     * @param int  $wilaya_id    Wilaya ID.
     * @param bool $hasStopDesk Whether to include stop desk data.
     * @return array Communes data.
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
        
        // Get offices data using the cached raw centers (v3 API with 6h cache)
        $offices_data = ['data' => $this->get_all_centers_raw()];



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
        
        $communesResult = [];
        
        // Format the communes with stop desk information
        foreach ($communes as $i => $item) {
            $has_stop_desk = isset($communes_with_stopdesk[$item['id']]);
            
            // If hasStopDesk is enabled, only include communes that actually have stop desks
            if ($hasStopDesk && !$has_stop_desk) {
                continue;
            }
            
            $communesResult[] = [
                'id' => $item['name'],
                'name' => $item['name'],
                'has_stop_desk' => $has_stop_desk
            ];
        }
		
        return $communesResult;
    }


	/**
	 * Get communes (cities) data for a wilaya.
	 *
	 * Retrieves a list of communes for a given wilaya from the ZimoExpress API or a cached file.
	 *
	 * @param int  $wilaya_id    Wilaya ID.
	 * @param bool $hasStopDesk Whether to include stop desk data.
	 * @return array Communes data.
	 */
	public function get_all_communes()
	{

		// Get communes data with caching
		$communes_path = Functions::get_path('zimoexpress_communes.json');

		if (!file_exists($communes_path)) {
			$url = $this->provider['api_url'] . '/api/v1/helpers/communes';
			$request = new ZimoExpressRequest($this->provider);
			$response_array = $request->get($url);
			file_put_contents($communes_path, json_encode($response_array));
		}

		return json_decode(file_get_contents($communes_path), true);
	}

    /**
     * Get status labels.
     * 
     * Returns an array of status codes and their corresponding labels.
     *
     * @return array Status codes and labels.
     */
    public function get_status()
    {
        return $this->status();
    }

    /**
     * Get paginated orders from the Zimou Express API.
     *
     * @param array $queryData Query parameters from ShippingOrdersTable.
     * @return array { items: array, total_data: int, current_page: int }
     */
    public function get_orders(array $queryData): array
    {
        $page    = (int) ($queryData['page'] ?? 1);
        $perPage = (int) ($queryData['page_size'] ?? 15);

        $params = [
            'page'     => $page,
            'per_page' => $perPage,
        ];

        $url     = $this->provider['api_url'] . '/api/v3/packages?' . http_build_query($params);
        $request = new ZimoExpressRequest($this->provider);
        $json    = $request->get($url, false);

        if (! is_array($json) || ! isset($json['data'])) {
            return ['items' => [], 'total_data' => 0, 'current_page' => $page];
        }

        $items = array_map(function ($item) {
            $communeName = $item['commune']['name'] ?? '';
            $wilayaName  = $item['commune']['wilaya']['name'] ?? '';

            return [
                'tracking'         => $item['tracking_code'] ?? '',
                'familyname'       => $item['client_last_name'] ?? '',
                'firstname'        => $item['client_first_name'] ?? '',
                'to_commune_name'  => $communeName,
                'to_wilaya_name'   => $wilayaName,
                'date'             => isset($item['created_at']) ? date('Y-m-d H:i', strtotime($item['created_at'])) : '',
                'contact_phone'    => $item['client_phone'] ?? '',
                'city'             => $communeName,
                'state'            => $wilayaName,
                'last_status'      => $item['status_name'] ?? '',
                'date_last_status' => isset($item['updated_at']) ? date('Y-m-d H:i', strtotime($item['updated_at'])) : '',
                'label'            => $item['print_url'] ?? '',
            ];
        }, $json['data']);

        // Client-side filter by status if requested (API doesn't document filter params)
        if (! empty($queryData['last_status'])) {
            $statusFilter = $queryData['last_status'];
            $items = array_values(array_filter($items, function ($item) use ($statusFilter) {
                return stripos($item['last_status'], $statusFilter) !== false;
            }));
        }

        return [
            'items'        => $items,
            'total_data'   => (int) ($json['meta']['total'] ?? count($items)),
            'current_page' => (int) ($json['meta']['current_page'] ?? $page),
        ];
    }

	private function get_fees($wilaya_id = null) {

		$all_results = [];

		$path = Functions::get_path(sprintf("%s_fees_DZ.json",$this->provider['slug']));

		if(!empty($wilaya_id)) {
			$path = Functions::get_path(sprintf("%s_fees_DZ%s.json", $this->provider['slug'], $wilaya_id));
		}

		if (file_exists($path)) {
			$all_results = json_decode(file_get_contents($path), true);
		} else {
			$url = $this->provider['api_url'] . "/api2/v2/store-delivery-types";

			try {
				$request = new ZimoExpressRequest($this->provider);
				$response_array = $request->get($url, false);


				if (isset($response_array['data']) && is_array($response_array['data'])) {
					// Process the raw data
					$formatted_results = $this->format_fees($response_array['data']);

					// Save the formatted data to cache file
					file_put_contents($path, json_encode($formatted_results));
					
					$all_results = $formatted_results;
				} else {
					error_log('ZimoExpress API returned invalid data format for fees');
				}
			} catch (\ErrorException $exception) {
				error_log("Error fetching ZimoExpress fees: " . $exception->getMessage());
			}
		}

		return $all_results;
	}
	
	/**
	 * Format the raw fees data from API into the structure needed for shipping zones
	 * 
	 * @param array $raw_data The raw data from the API
	 * @param bool $is_flexible Whether to use economic (true) or express (false) rates
	 * @return array The formatted fees data
	 */
	private function format_fees($raw_data) {


		$formatted_data = [];
		
		// Group data by wilaya and collect all delivery types
		$wilaya_data = [];
		
		foreach ($raw_data as $delivery_type_data) {
			$delivery_name = $delivery_type_data['delivery_name'];
			
			// Process each price entry for this delivery type
			if (isset($delivery_type_data['prices']) && is_array($delivery_type_data['prices'])) {
				foreach ($delivery_type_data['prices'] as $price_entry) {
					$wilaya_id = $price_entry['wilaya_id'];
					$wilaya_name = $price_entry['wilaya_name'];
					$delivery_price = $price_entry['delivery_price'];
					
					// Skip entries with zero or negative price
					if ($delivery_price <= 0) {
						continue;
					}
					
					// Initialize wilaya data if not exists
					if (!isset($wilaya_data[$wilaya_id])) {
						$wilaya_data[$wilaya_id] = [
							'wilaya_id' => $wilaya_id,
							'wilaya_name' => $wilaya_name,
							'home_flexible' => 0,
							'home_express' => 0,
							'maystro_price' => 0,
							'zr_express_price' => 0,
							'economy_price' => 0,
							'yalidine_price' => 0,
							'desk' => 0,
						];
					}
					
					// Store prices for each delivery type
					if ($delivery_name === 'Flexible') {
						// Store Flexible price separately
						if ($delivery_price > $wilaya_data[$wilaya_id]['home_flexible']) {
							$wilaya_data[$wilaya_id]['home_flexible'] = $delivery_price;
						}
					}
					elseif ($delivery_name === 'Maystro_ delivery') {
						// Store Flexible price separately
						if ($delivery_price > $wilaya_data[$wilaya_id]['maystro_price']) {
							$wilaya_data[$wilaya_id]['maystro_price'] = $delivery_price;
						}
					}
					elseif ($delivery_name === 'ZR Express') {
						// Store Flexible price separately
						if ($delivery_price > $wilaya_data[$wilaya_id]['zr_express_price']) {
							$wilaya_data[$wilaya_id]['zr_express_price'] = $delivery_price;
						}
					}
					elseif ($delivery_name === 'Economy Express') {
						// Store Flexible price separately
						if ($delivery_price > $wilaya_data[$wilaya_id]['economy_price']) {
							$wilaya_data[$wilaya_id]['economy_price'] = $delivery_price;
						}
					}
					elseif ($delivery_name === 'Yalidine') {
						// Store Flexible price separately
						if ($delivery_price > $wilaya_data[$wilaya_id]['yalidine_price']) {
							$wilaya_data[$wilaya_id]['yalidine_price'] = $delivery_price;
						}
					}
					elseif ($delivery_name === 'Express') {
						// Store Express price separately
						if ($delivery_price > $wilaya_data[$wilaya_id]['home_express']) {
							$wilaya_data[$wilaya_id]['home_express'] = $delivery_price;
						}
					}
					else if ($delivery_name === 'Point relais') {
						// Take the highest price for desk/pickup
						if ($delivery_price > $wilaya_data[$wilaya_id]['desk']) {
							$wilaya_data[$wilaya_id]['desk'] = $delivery_price;
						}
					}
				}
			}
		}
		
		// Now set the home price based on is_economic flag
		foreach ($wilaya_data as $wilaya_id => &$data) {

			// For standard mode, use Express price if available, otherwise use Flexible
			$data['home_express'] = $data['home_express'] ?? 0;
			$data['maystro_price'] = $data['maystro_price'] ?? 0;
			$data['home_flexible'] = $data['home_flexible'] ?? 0;
			$data['zr_express_price'] = $data['zr_express_price'] ?? 0;
			$data['yalidine_price'] = $data['yalidine_price'] ?? 0;
			$data['economy_price'] = $data['economy_price'] ?? 0;

			
//			// Remove temporary fields
//			unset($data['home_flexible']);
//			unset($data['maystro_price']);
//			unset($data['zr_express_price']);
//			unset($data['yalidine_price']);
//			unset($data['economy_price']);
//			unset($data['home_express']);
		}
		
		// Convert to indexed array and filter out wilayas with no prices
		foreach ($wilaya_data as $data) {
			if ($data['home_express'] > 0 || $data['home_flexible'] > 0 || $data['home_maystro_price'] > 0 || $data['home_zr_express_price'] > 0 || $data['home_economy_price'] > 0 || $data['home_yalidine_price'] > 0 || $data['desk'] > 0) {
				$formatted_data[] = $data;
			}
		}
		
		// Sort by wilaya_id for consistency
		usort($formatted_data, function($a, $b) {
			return $a['wilaya_id'] - $b['wilaya_id'];
		});
		
		return $formatted_data;
	}

	/**
	 * Normalize partner company name for grouping/filtering
	 * Removes city suffixes and trailing single letters
	 * 
	 * @param string $partner_name Raw partner name from API
	 * @return string Normalized partner name
	 * @since 4.17.0
	 */
	public function normalize_partner_name($partner_name) {
		// List of known city suffixes to remove
		$city_suffixes = ['Alger', 'Oran', 'Constantine', 'Setif', 'Batna', 'Annaba', 'Blida', 'Tizi Ouzou', 'Bejaia'];
		
		$normalized = $partner_name;
		
		// Remove city suffixes
		foreach ($city_suffixes as $city) {
			$normalized = preg_replace('/\s+' . preg_quote($city, '/') . '$/i', '', $normalized);
		}
		
		// Remove trailing single letters (like "A" in "Yalidine Express A")
		$normalized = preg_replace('/\s+[A-Z]$/i', '', $normalized);
		
		// Clean up extra spaces
		$normalized = trim(preg_replace('/\s+/', ' ', $normalized));
		
		return $normalized;
	}

	/**
	 * Check if a center's partner is in the allowed list
	 * 
	 * @param string $partner_name Raw partner name from API
	 * @param array $allowed_partners List of allowed normalized partner names
	 * @return bool
	 * @since 4.17.0
	 */
	private function is_partner_allowed($partner_name, $allowed_partners) {
		if (empty($allowed_partners)) {
			return true; // If no filter set, allow all
		}
		
		$normalized = $this->normalize_partner_name($partner_name);
		
		foreach ($allowed_partners as $allowed) {
			// Use stripos for flexible matching
			if (stripos($normalized, $allowed) !== false || stripos($allowed, $normalized) !== false) {
				return true;
			}
		}
		
		return false;
	}

	/**
	 * Get list of available partner companies from cached centers data
	 * 
	 * @return array List of unique normalized partner names
	 * @since 4.17.0
	 */
	public function get_available_partners() {
		$cache_key = $this->provider['slug'] . '_partners_list_v3';
		$partners = get_transient($cache_key);
		
		if ($partners === false) {
			// Get raw centers data (unfiltered)
			$centers = $this->get_all_centers_raw();
			$partners = [];
			
			foreach ($centers as $center) {
				if (isset($center['partner_company_name'])) {
					$normalized = $this->normalize_partner_name($center['partner_company_name']);
					if (!empty($normalized) && !in_array($normalized, $partners)) {
						$partners[] = $normalized;
					}
				}
			}
			
			sort($partners);
			// Cache for 6 hours
			set_transient($cache_key, $partners, 6 * HOUR_IN_SECONDS);
		}
		
		return $partners;
	}

	/**
	 * Get raw centers data from API without filtering
	 * Uses 6-hour transient cache
	 * 
	 * @return array Raw centers data from API
	 * @since 4.17.0
	 */
	public function get_all_centers_raw() {
		$cache_key = $this->provider['slug'] . '_centers_raw_v3';
		$centers = get_transient($cache_key);
		
		if ($centers === false) {
			// Fetch fresh data from v3 API
			try {
				$request = new ZimoExpressRequest($this->provider);
				$response = $request->get($this->provider['api_url'] . '/api/v3/store-stopdesks');
				
				if (isset($response['data']) && is_array($response['data'])) {
					$centers = $response['data'];
					// Cache for 6 hours
					set_transient($cache_key, $centers, 6 * HOUR_IN_SECONDS);
				} else {
					return [];
				}
			} catch (\Exception $e) {
				error_log('ZimoExpress get_all_centers_raw error: ' . $e->getMessage());
				return [];
			}
		}
		
		return $centers;
	}

	/**
	 * Get all centers with caching and partner filtering
	 * Uses v3 API with 6-hour transient cache
	 *
	 * @param bool $apply_partner_filter Whether to apply partner filtering (default true)
	 * @param bool $use_simple_format Whether to use simple format "Partner - City" instead of "Partner - Address" (default false)
	 * @return array
	 * @since 4.0.0
	 */
	public function get_all_centers($apply_partner_filter = true, $use_simple_format = false)
	{
		// Get raw centers data (cached)
		$centers = $this->get_all_centers_raw();
		
		if (empty($centers)) {
			return [];
		}
		
		// Get allowed partners from settings
		$allowed_partners = [];
		if ($apply_partner_filter) {
			$allowed_partners = get_option($this->provider['slug'] . '_allowed_partners', []);
			if (is_string($allowed_partners)) {
				$allowed_partners = json_decode($allowed_partners, true) ?: [];
			}
		}
		
		// Format the centers data
		$communes = $this->get_all_communes();
		$communesResult = [];

		foreach ($centers as $i => $item) {
			// Filter by allowed partners
			if ($apply_partner_filter && !empty($allowed_partners)) {
				if (!$this->is_partner_allowed($item['partner_company_name'] ?? '', $allowed_partners)) {
					continue;
				}
			}

			$commune_id = $item['commune_id'];

			$found = array_filter($communes['data'] ?? [], function ($commune) use ($commune_id) {
				return $commune['id'] == $commune_id;
			});

			$found = reset($found);
			$commune_name = $found['name'] ?? '';
			$normalized_partner = $this->normalize_partner_name($item['partner_company_name'] ?? '');

			// Format name based on preference
			if ($use_simple_format) {
				// Simple format: "Partner - City Name"
				$display_name = sprintf("%s - %s", $normalized_partner, $commune_name);
			} else {
				// Detailed format: "Partner Company - Address"
				$display_name = sprintf("%s - %s", $item['partner_company_name'] ?? '', $item['address'] ?? '');
			}

			$communesResult[] = [
				'code' => $item['office_id'],
				'name' => $display_name,
				'partner' => $normalized_partner,
				'partner_raw' => $item['partner_company_name'] ?? '',
				'address' => $item['address'] ?? '',
				'commune_id' => $item['commune_id'],
				'commune_name' => $commune_name,
				'wilaya_id' => $found['wilaya_id'] ?? null,
				'wilaya_name' => $item['office_name'] ?? null,
				'price' => $item['price'] ?? 0,
				'return_price' => $item['return_price'] ?? 0,
				'cod_percentage' => $item['cod_percentage'] ?? 0,
			];
		}
		
		return $communesResult;
	}

	public function handle_webhook($provider, $jsonData) {

		$data = json_decode($jsonData, true);
		$theorder = null;
		$tracking = $data['package']['tracking_code'];

		if (isset($data['event'])) {
			$type = $data['event'];
			$orderId = $data['package']['order_id'] ?? null;

			if ($orderId) {
				$theorder = wc_get_order($orderId);
			}

			if (!$theorder) {

				$args = array(
					'limit' => 1,
					'status' => 'any',
					'meta_key' => '_shipping_tracking_number',
					'meta_value' => $tracking,
					'type' => 'shop_order',
					'return' => 'objects',
				);

				// Perform the query
				$query = new \WC_Order_Query($args);
				$orders = $query->get_orders();

				// $orders will contain an array of order IDs that match the criteria
				if (!empty($orders)) {
					$theorder = $orders[0]; // Since we limited the query to 1, take the first result
				}
			}

			if ($type === 'package.status.changed') {
				if (!$theorder || !($theorder instanceof \WC_Order)) {
					return [
						'success' => false,
						'message' => sprintf('Order not found for tracking code %s (order_id: %s)', $tracking, $orderId ?? 'N/A'),
					];
				}

				$itemStatus = $data['package']['status'];

				BordereauGeneratorAdmin::update_tracking_status($theorder, $itemStatus, 'webhook');

				(new BordereauGeneratorAdmin(WC_BORDEREAU_POST_TYPE, WC_BORDEREAU_GENERATOR_VERSION))
					->update_orders_status($theorder, $itemStatus);

				return [
					'success' => true,
					'updated' => 1
				];
			}
		}


	}
}
