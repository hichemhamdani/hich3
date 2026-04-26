<?php

namespace WooBordereauGenerator\Shippingmethods;

use WC_Shipping_Local_Pickup;

class YalidineLocalPickup extends DefaultLocalPickup
{
    protected $address;
    protected $gps;
    protected $maps;
    protected $center_id;
    protected $phone;
    protected $is_economic;


    public function __construct($instance_id = 0)
    {

        $this->id = 'local_pickup_yalidine';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('Yalidine Local Pickup', 'woo-bordereau-generator');
        $this->method_description = __('Yalidine Local Pickup Shipping method', 'woo-bordereau-generator');
        $this->has_58_states = true;
        $this->has_stop_desks = true;
        $this->has_city_extra_free = false;
        $this->has_extra_weight_calcule = true;
        $this->is_economic = false;

        $this->supports = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
        );


        $this->init();
    }

    function init()
    {
        // Load the settings API
        $this->init_form_fields();
        $this->init_settings();

        $this->title = $this->get_option('title');
        $this->cost = $this->get_option('cost');
        $this->address = $this->get_option('address');
        $this->gps = $this->get_option('gps');
        $this->phone = $this->get_option('phone');
        $this->maps = $this->get_option('maps');
        $this->center_id = $this->get_option('center_id');
        $this->provider = $this->get_option('provider');
        $this->is_economic = $this->get_option('is_economic') == "yes";

        // Save settings in admin
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields()
    {
        $this->instance_form_fields = array(
            'title' => array(
                'title' => __('Title', 'woo-bordereau-generator'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woo-bordereau-generator'),
                'default' => __('Yalidine Local Pickup', 'woo-bordereau-generator'),
                'desc_tip' => true,
            ),
            'cost' => array(
                'title' => __('Cost', 'woo-bordereau-generator'),
                'type' => 'text',
                'placeholder' => '0',
                'description' => __('Cost for local Pickup.', 'woo-bordereau-generator'),
                'default' => '',
                'desc_tip' => true,
            ),
            'gps' => array(
                'title' => __('GPS', 'woo-bordereau-generator'),
                'type' => 'text',
                'description' => __('This will show the geolocation of the local pickup.', 'woo-bordereau-generator'),
                'default' => __('Yalidine Local Pickup', 'woo-bordereau-generator'),
                'desc_tip' => true,
            ),
            'address' => array(
                'title' => __('Address', 'woo-bordereau-generator'),
                'type' => 'text',
                'description' => __('This will show the address of the local pickup in the checkout.', 'woo-bordereau-generator'),
                'default' => '',
                'desc_tip' => true,
            ),
            'maps' => array(
                'title' => __('Maps', 'woo-bordereau-generator'),
                'type' => 'text',
                'description' => __('This will show the link maps of the local pickup in the checkout.', 'woo-bordereau-generator'),
                'default' => '',
                'desc_tip' => true,
            ),
            'phone' => array(
                'title' => __('Address', 'woo-bordereau-generator'),
                'type' => 'text',
                'description' => __('This will show the address of the local pickup in the checkout.', 'woo-bordereau-generator'),
                'default' => '',
                'desc_tip' => true,
            ),
            'center_id' => array(
                'title' => __('Center ID', 'woo-bordereau-generator'),
                'type' => 'number',
                'description' => __('This will save the center ID for the agency.', 'woo-bordereau-generator'),
                'default' => 0,
                'desc_tip' => true,
            ),
            'provider' => array(
                'title' => __('Shipping Company', 'woo-bordereau-generator'),
                'type' => 'text',
                'placeholder' => '0',
                'description' => __('Slug of Shipping company.', 'woo-bordereau-generator'),
                'default' => '',
                'desc_tip' => true,
            ),
            'is_economic' => array(
                'title' => __('Is Economic?', 'woo-bordereau-generator'),
                'type' => 'checkbox',
                'placeholder' => '0',
                'description' => __('Is Economic Shipping company?.', 'woo-bordereau-generator'),
                'default' => false,
                'desc_tip' => true,
            ),
        );
    }

    /**
     * Get the provider
     * @since 4.0.0
     * @return bool
     */
    public function get_is_economic()
    {
        return $this->is_economic;
    }

    /**
     * @since 4.0.0
     */
    public function set_is_economic($value)
    {
        $this->is_economic = $value;
        return $this;
    }
}