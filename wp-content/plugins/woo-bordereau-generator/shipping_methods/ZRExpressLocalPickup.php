<?php

namespace WooBordereauGenerator\Shippingmethods;

use WC_Shipping_Local_Pickup;

class ZRExpressLocalPickup extends DefaultLocalPickup
{

    public function __construct($instance_id = 0)
    {

        $this->id = 'local_pickup_zr_express';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('ZR Express Local Pickup', 'woo-bordereau-generator');
        $this->method_description = __('ZR Express Local Pickup Shipping method', 'woo-bordereau-generator');
        $this->has_58_states = true;
        $this->has_stop_desks = false;
        $this->has_extra_weight_calcule = true;
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
        $this->provider = $this->get_option('provider', 'zr_express_new');
        $this->address = $this->get_option('address');
        $this->gps = $this->get_option('gps');
        $this->phone = $this->get_option('phone');
        $this->maps = $this->get_option('maps');

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
                'default' => __('ZR Express Local Pickup', 'woo-bordereau-generator'),
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
            'provider' => array(
                'title' => __('Shipping Company', 'woo-bordereau-generator'),
                'type' => 'hidden',
                'placeholder' => '0',
                'description' => __('Slug of Shipping company.', 'woo-bordereau-generator'),
                'default' => '',
                'desc_tip' => true,
            ),
            'gps' => array(
                'title' => __('GPS', 'woo-bordereau-generator'),
                'type' => 'text',
                'description' => __('This will show the geolocation lat,lng of the local pickup.', 'woo-bordereau-generator'),
                'desc_tip' => true,
                'default' => '',
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
                'title' => __('Phone', 'woo-bordereau-generator'),
                'type' => 'text',
                'description' => __('This will show the phone of the local pickup in the checkout.', 'woo-bordereau-generator'),
                'default' => '',
                'desc_tip' => true,
            ),
        );
    }
}