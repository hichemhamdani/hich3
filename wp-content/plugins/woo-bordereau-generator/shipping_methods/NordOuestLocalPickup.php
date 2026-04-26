<?php

namespace WooBordereauGenerator\Shippingmethods;

use WC_Shipping_Local_Pickup;

class NordOuestLocalPickup extends DefaultLocalPickup
{
    public function __construct($instance_id = 0)
    {
        $this->id = 'local_pickup_noest';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('Nord et Ouest Express Local Pickup', 'woo-bordereau-generator');
        $this->method_description = __('Local Pickup for Nord et Ouest Express Shipping method', 'woo-bordereau-generator');
        $this->has_58_states = true;
        $this->has_stop_desks = true;
        $this->has_city_extra_free = false;
        $this->has_extra_weight_calcule = true;

        $this->supports = array(
            'shipping-zones',
            'instance-settings',
            'instance-settings-modal',
            'shipping-classes', // Enable support for shipping classes
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
        $this->provider = $this->get_option('provider', 'nord_ouest');
        $this->address = $this->get_option('address');
        $this->gps = $this->get_option('gps');
        $this->phone = $this->get_option('phone');
        $this->email = $this->get_option('email');
        $this->maps = $this->get_option('maps');

        // Save settings in admin
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields()
    {
        // Get all shipping classes
        $shipping_classes = WC()->shipping->get_shipping_classes();

        // Add shipping class cost fields
        $shipping_class_costs = array();
        foreach ($shipping_classes as $shipping_class) {
            $shipping_class_costs['class_cost_' . $shipping_class->term_id] = array(
                'title'       => sprintf(__('"%s" Shipping Class Cost', 'woo-bordereau-generator'), esc_html($shipping_class->name)),
                'type'        => 'text',
                'placeholder' => __('N/A', 'woo-bordereau-generator'),
                'description' => __('Enter a cost (excl. tax) for this shipping class, or leave blank to disable.', 'woo-bordereau-generator'),
                'default'     => '',
                'desc_tip'    => true,
            );
        }

        $this->instance_form_fields = array(
                'title' => array(
                    'title' => __('Title', 'woo-bordereau-generator'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'woo-bordereau-generator'),
                    'default' => __('Local Pickup', 'woo-bordereau-generator'),
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
                'no_class_cost' => array(
                    'title'       => __('No Shipping Class Cost', 'woo-bordereau-generator'),
                    'type'        => 'text',
                    'placeholder' => __('N/A', 'woo-bordereau-generator'),
                    'description' => __('Enter a cost (excl. tax) for orders without a shipping class.', 'woo-bordereau-generator'),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'calculation_type' => array(
                    'title'       => __('Calculation Type', 'woo-bordereau-generator'),
                    'type'        => 'select',
                    'description' => __('Choose how shipping class costs are calculated.', 'woo-bordereau-generator'),
                    'default'     => 'per_class',
                    'options'     => array(
                        'per_class' => __('Per Class: Cost for each shipping class', 'woo-bordereau-generator'),
                        'per_order' => __('Per Order: Highest shipping class cost', 'woo-bordereau-generator'),
                    ),
                    'desc_tip'    => true,
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
                'email' => array(
                    'title' => __('Email', 'woo-bordereau-generator'),
                    'type' => 'text',
                    'description' => __('This will show the email of the local pickup in the checkout.', 'woo-bordereau-generator'),
                    'default' => '',
                    'desc_tip' => true,
                ),
            ) + $shipping_class_costs; // Merge shipping class cost fields
    }

    public function calculate_shipping($package = array())
    {
        $rate = array(
            'id' => $this->get_rate_id(),
            'label' => $this->title,
            'cost' => $this->cost,
            'package' => $package,
        );

        // Get the calculation type
        $calculation_type = $this->get_option('calculation_type', 'per_class');

        // Check for shipping classes
        $shipping_classes = WC()->shipping->get_shipping_classes();
        $found_shipping_class = false;
        $shipping_class_costs = array();

        foreach ($package['contents'] as $item_id => $values) {
            $product = $values['data'];
            $shipping_class_id = $product->get_shipping_class_id();

            if ($shipping_class_id) {
                $shipping_class_cost = $this->get_option('class_cost_' . $shipping_class_id, '');

                if ($shipping_class_cost !== '') {
                    $shipping_class_costs[] = floatval($shipping_class_cost);
                    $found_shipping_class = true;
                }
            }
        }

        // Calculate shipping cost based on the calculation type
        if ($found_shipping_class) {
            if ($calculation_type === 'per_order') {
                // Use the highest shipping class cost
                $rate['cost'] += max($shipping_class_costs);
            } else {
                // Use the sum of all shipping class costs (default: per_class)
                $rate['cost'] += array_sum($shipping_class_costs);
            }
        } else {
            // If no shipping class was found, use the no class cost
            $no_class_cost = $this->get_option('no_class_cost', '');
            if ($no_class_cost !== '') {
                $rate['cost'] += floatval($no_class_cost);
            }
        }

        // Double shipping price if cart subtotal meets the trigger threshold
        $double_trigger = get_option('nord_ouest_double_shipping_trigger', 0);
        if (!empty($double_trigger) && floatval($double_trigger) > 0) {
            $cart_subtotal = WC()->cart ? WC()->cart->get_subtotal() : 0;
            if ($cart_subtotal >= floatval($double_trigger)) {
                $rate['cost'] = $rate['cost'] * 2;
            }
        }

        // Register the rate
        $this->add_rate($rate);
    }

}