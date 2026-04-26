<?php

namespace WooBordereauGenerator\Shippingmethods;

class DefaultFlatRate extends \WC_Shipping_Flat_Rate
{
    protected $provider;
    protected $has_58_states;
    protected $has_stop_desks;
    protected $has_city_extra_free;
    protected $has_extra_weight_calcule;

    public function __construct($instance_id = 0) {


        if (is_hanout_enabled()) {
            $this->id = 'flat_rate';
        } else {
            $this->id = 'flat_rate_default';
        }

        $this->instance_id = absint($instance_id);
        $this->method_title = __('Flat Rate', 'woo-bordereau-generator');
        $this->method_description = __('Flat Rate Shipping method', 'woo-bordereau-generator');

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
        $this->title                                = $this->get_option( 'title' );
        $this->cost                                 = $this->get_option( 'cost' );
        $this->provider                             = $this->get_option( 'provider');
        $this->tax_status                           = $this->get_option( 'tax_status' );
        $this->cost                                 = $this->get_option( 'cost' );
        $this->type                                 = $this->get_option( 'type', 'class' );
        $this->has_58_states                        = (bool) $this->get_option( 'has_58', 'true' );

        // Save settings in admin
        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    public function init_form_fields()
    {
        $cost_desc = __( 'Enter a cost (excl. tax) or sum, e.g. <code>10.00 * [qty]</code>.', 'woocommerce' ) . '<br/><br/>' . __( 'Use <code>[qty]</code> for the number of items, <br/><code>[cost]</code> for the total cost of items, and <code>[fee percent="10" min_fee="20" max_fee=""]</code> for percentage based fees.', 'woocommerce' );

        $settings = array(
            'title'      => array(
                'title'       => __( 'Method title', 'woocommerce' ),
                'type'        => 'text',
                'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                'default'     => __( 'Flat rate', 'woocommerce' ),
                'desc_tip'    => true,
            ),
            'tax_status' => array(
                'title'   => __( 'Tax status', 'woocommerce' ),
                'type'    => 'select',
                'class'   => 'wc-enhanced-select',
                'default' => 'taxable',
                'options' => array(
                    'taxable' => __( 'Taxable', 'woocommerce' ),
                    'none'    => _x( 'None', 'Tax status', 'woocommerce' ),
                ),
            ),
            'cost'       => array(
                'title'             => __( 'Cost', 'woocommerce' ),
                'type'              => 'text',
                'placeholder'       => '',
                'description'       => $cost_desc,
                'default'           => '0',
                'desc_tip'          => true,
                'sanitize_callback' => array( $this, 'sanitize_cost' ),
            ),

            'provider'       => array(
                'title'             => __( 'Shipping Provider', 'woocommerce' ),
                'type'              => 'hidden',
                'placeholder'       => '',
                'desc_tip'          => false,
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

        $shipping_classes = WC()->shipping()->get_shipping_classes();

        if ( ! empty( $shipping_classes ) ) {
            $settings['class_costs'] = array(
                'title'       => __( 'Shipping class costs', 'woocommerce' ),
                'type'        => 'title',
                'default'     => '',
                /* translators: %s: URL for link. */
                'description' => sprintf( __( 'These costs can optionally be added based on the <a href="%s">product shipping class</a>.', 'woocommerce' ), admin_url( 'admin.php?page=wc-settings&tab=shipping&section=classes' ) ),
            );
            foreach ( $shipping_classes as $shipping_class ) {
                if ( ! isset( $shipping_class->term_id ) ) {
                    continue;
                }
                $settings[ 'class_cost_' . $shipping_class->term_id ] = array(
                    /* translators: %s: shipping class name */
                    'title'             => sprintf( __( '"%s" shipping class cost', 'woocommerce' ), esc_html( $shipping_class->name ) ),
                    'type'              => 'text',
                    'placeholder'       => __( 'N/A', 'woocommerce' ),
                    'description'       => $cost_desc,
                    'default'           => $this->get_option( 'class_cost_' . $shipping_class->slug ), // Before 2.5.0, we used slug here which caused issues with long setting names.
                    'desc_tip'          => true,
                    'sanitize_callback' => array( $this, 'sanitize_cost' ),
                );
            }

            $settings['no_class_cost'] = array(
                'title'             => __( 'No shipping class cost', 'woocommerce' ),
                'type'              => 'text',
                'placeholder'       => __( 'N/A', 'woocommerce' ),
                'description'       => $cost_desc,
                'default'           => '',
                'desc_tip'          => true,
                'sanitize_callback' => array( $this, 'sanitize_cost' ),
            );

            $settings['type'] = array(
                'title'   => __( 'Calculation type', 'woocommerce' ),
                'type'    => 'select',
                'class'   => 'wc-enhanced-select',
                'default' => 'class',
                'options' => array(
                    'class' => __( 'Per class: Charge shipping for each shipping class individually', 'woocommerce' ),
                    'order' => __( 'Per order: Charge shipping for the most expensive shipping class', 'woocommerce' ),
                ),
            );
        }

        $this->instance_form_fields = $settings;
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
     * @since 4.0.0
     */
    public function set_has_58_states($value)
    {
        $this->has_58_states = $value;
        return $this;
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
}