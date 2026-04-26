<?php

namespace WooBordereauGenerator\Shippingmethods;

use WC_Shipping_Local_Pickup;

class DefaultLocalPickup extends WC_Shipping_Local_Pickup
{
    protected $provider;
    protected $has_58_states;
    protected $has_stop_desks;
    protected $has_city_extra_free;
    protected $has_extra_weight_calcule;
    protected $address;
    protected $gps;
    protected $maps;
    protected $phone;
    protected $email;

    public function __construct($instance_id = 0) {

        if (is_hanout_enabled()) {
            $this->id = 'local_pickup';
        } else {
            $this->id = 'local_pickup_default';
        }
        $this->instance_id = absint($instance_id);
        $this->method_title = __('Local Pickup', 'woo-bordereau-generator');
        $this->method_description = __('Local Pickup Shipping method', 'woo-bordereau-generator');
        $this->has_58_states = true;
        $this->has_stop_desks = false;
        $this->has_extra_weight_calcule = false;
        $this->has_city_extra_free = false;

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

        $this->title                                = $this->get_option( 'title');
        $this->cost                                 = $this->get_option( 'cost');
        $this->provider                             = $this->get_option( 'provider');
        $this->has_58_states                        = (bool) $this->get_option( 'has_58', 'true' );

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
                'default'     => __( 'Local Pickup', 'woo-bordereau-generator' ),
                'desc_tip'    => true,
            ),
            'cost'       => array(
                'title'       => __( 'Cost', 'woo-bordereau-generator' ),
                'type'        => 'text',
                'placeholder' => '0',
                'description' => __( 'Cost for local Pickup.', 'woo-bordereau-generator' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'provider'       => array(
                'title'       => __( 'Shipping Company', 'woo-bordereau-generator' ),
                'type'        => 'hidden',
                'placeholder' => '0',
                'description' => __( 'Name of Shipping company.', 'woo-bordereau-generator' ),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'has_58'       => array(
                'title'   => __( 'Has 58 States ?', 'woocommerce' ),
                'type'    => 'select',
                'class'   => 'wc-enhanced-select',
                'default' => 'class',
                'options' => array(
                    'true' => __( 'Yes', 'woo-bordereau-generator' ),
                    'false' => __( 'No', 'woo-bordereau-generator' ),
                ),
            ),
        );
    }

    /**
     * @since 4.0.0
     * @return true
     */
    public function get_has_58_states()
    {
        return $this->has_58_states;
    }

    /**
     * Check if it has stop desks
     * @return false
     * @since 4.0.0
     */
    public function get_has_stop_desks()
    {
        return $this->has_stop_desks;
    }

    /**
     * @since 4.0.0
     * @return true
     */
    public function get_has_city_fee()
    {
        return $this->has_city_extra_free;
    }

    /**
     * @since 4.0.0
     * @return true
     */
    public function get_has_extra_weight()
    {
        return $this->has_extra_weight_calcule;
    }


    /**
     * Get the provider
     * @since 4.0.0
     * @return mixed
     */
    public function get_provider()
    {
        return $this->provider;
    }

    /**
     * @return mixed
     */
    public function get_addres()
    {
        return $this->address;
    }

    /**
     * @return mixed
     */
    public function get_phone()
    {
        return $this->phone;
    }

    /**
     * @return mixed
     */
    public function get_maps()
    {
        return $this->maps;
    }

    /**
     * @return mixed
     */
    public function get_gps()
    {
        return $this->gps;
    }

}