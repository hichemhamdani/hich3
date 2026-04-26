<?php

namespace WooBordereauGenerator\Shippingmethods;

class LihlihExpressFlatRate extends DefaultFlatRate
{
    public function __construct($instance_id = 0) {

        $this->id = 'flat_rate_lihlihexpress';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('Lihlih Express Flat Rate', 'woo-bordereau-generator');
        $this->method_description = __('Lihlih Express Flat Rate Shipping method', 'woo-bordereau-generator');
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

    function init() {

        // Load the settings API
        $this->init_form_fields();
        $this->init_settings();

        $this->title                = $this->get_option( 'title' );
        $this->cost                 = $this->get_option( 'cost' );
        $this->type                 = $this->get_option( 'type', 'class' );
        $this->provider             = $this->get_option( 'provider', 'lihlihexpress' );

        // Save settings in admin
        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    public function init_form_fields()
    {
        $this->instance_form_fields = array(
            'title'      => array(
                'title'       => __( 'Title', 'woo-bordereau-generator' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'woo-bordereau-generator' ),
                'default'     => __( 'Lihlih Express Flat Rate', 'woo-bordereau-generator' ),
                'desc_tip'    => true,
            ),
            'cost'       => array(
                'title'       => __( 'Cost', 'woo-bordereau-generator' ),
                'type'        => 'text',
                'placeholder' => '0',
                'description' => __( 'Cost for flat rate.', 'woo-bordereau-generator' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
        );
    }
}