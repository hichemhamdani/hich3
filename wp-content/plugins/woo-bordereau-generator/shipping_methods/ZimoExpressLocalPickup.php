<?php

namespace WooBordereauGenerator\Shippingmethods;

use WC_Shipping_Local_Pickup;

class ZimoExpressLocalPickup extends DefaultLocalPickup
{
    protected $address;
    protected $gps;
    protected $center_id;


    public function __construct($instance_id = 0)
    {

        $this->id = 'local_pickup_zimoexpress';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('Zimo Express Local Pickup', 'woo-bordereau-generator');
        $this->method_description = __('Zimo Express Local Pickup Shipping method', 'woo-bordereau-generator');
        $this->has_58_states = true;
        $this->has_stop_desks = true;
        $this->has_city_extra_free = true;
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
        $this->address = $this->get_option('address');
        $this->phone = $this->get_option('phone');
        $this->gps = $this->get_option('gps');
        $this->center_id = $this->get_option('center_id');
        $this->provider = 'zimoexpress';

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
                'default' => __('zimoexpress Local Pickup', 'woo-bordereau-generator'),
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
            'phone' => array(
                'title' => __('Phone', 'woo-bordereau-generator'),
                'type' => 'text',
                'description' => __('This will show the phone of the local pickup in the checkout.', 'woo-bordereau-generator'),
                'default' => '',
                'desc_tip' => true,
            ),
            'gps' => array(
                'title' => __('GPS', 'woo-bordereau-generator'),
                'type' => 'text',
                'description' => __('This will show the geolocation of the local pickup.', 'woo-bordereau-generator'),
                'default' => __('zimoexpress Local Pickup', 'woo-bordereau-generator'),
                'desc_tip' => true,
            ),
            'address' => array(
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
        );
    }

    /**
     * Calculate shipping cost with dynamic stop desk pricing
     * 
     * When a customer selects a specific stop desk, the price is updated
     * dynamically based on that stop desk's individual price from the API.
     *
     * @param array $package Package array
     * @return void
     * @since 4.17.0
     */
    public function calculate_shipping($package = array())
    {
        // Start with the default cost from settings
        $cost = $this->cost;

        // Get the selected center ID from POST data
        $billing_city = null;
        $post_data = array();
        if (isset($_POST['post_data'])) {
            parse_str($_POST['post_data'], $post_data);
        }

        if (!empty($post_data['billing_city'])) {
            $billing_city = sanitize_text_field($post_data['billing_city']);
        } elseif (!empty($_POST['billing_city'])) {
            $billing_city = sanitize_text_field($_POST['billing_city']);
        }

        // Look up the center price from cached API data
        if ($billing_city) {
            $centers = get_transient('zimoexpress_centers_raw_v3');

            if (!empty($centers) && is_array($centers)) {
                foreach ($centers as $center) {
                    if (isset($center['office_id']) && $center['office_id'] == $billing_city) {
                        if (isset($center['price']) && floatval($center['price']) > 0) {
                            $cost = floatval($center['price']);
                        }
                        break;
                    }
                }
            }
        }

        // Build the rate
        $rate = array(
            'id'      => $this->get_rate_id(),
            'label'   => $this->title,
            'cost'    => $cost,
            'package' => $package,
        );

        // Add the rate
        $this->add_rate($rate);
    }
}