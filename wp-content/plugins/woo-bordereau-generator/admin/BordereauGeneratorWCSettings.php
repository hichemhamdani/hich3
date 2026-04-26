<?php
/**
 * Extends the WC_Settings_Page class
 *
 * @link        https://amineware.com
 * @since       1.0.0
 *
 * @package     WC_Bordereau_Generator
 * @subpackage  WC_Bordereau_Generator/admin
 *
 */
namespace WooBordereauGenerator\Admin;

use WC_Admin_Settings;
use WC_Settings_Page;
use WooBordereauGenerator\Admin\Partials\BordereauGeneratorProviders;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if ( ! class_exists( 'BordereauGeneratorWCSettings' ) ) {

    /**
     * Settings class
     *
     * @since 1.0.0
     */
    class BordereauGeneratorWCSettings extends WC_Settings_Page {

        /**
         * Constructor
         * @since  1.0
         */
        public function __construct() {

            parent::__construct();
            $this->id    = 'wc-bordereau-generator';
            $this->label = __( 'Bordereau Generator', 'wc-bordereau-generator' );

            // Define all hooks instead of inheriting from parent
            add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_page' ), 20 );
            add_action( 'woocommerce_sections_' . $this->id, array( $this, 'output_sections' ) );
            add_action( 'woocommerce_settings_' . $this->id, array( $this, 'output' ) );
            add_action( 'woocommerce_settings_save_' . $this->id, array( $this, 'save' ) );
	        add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 15);
        }

		public function admin_enqueue_scripts() {
			wp_enqueue_style( 'dashicons' );
			wp_enqueue_style( 'wp-color-picker' );
			wp_enqueue_style( 'woocommerce_admin_styles' );
			wp_enqueue_media();
			wp_enqueue_script( 'jquery' );
			wp_enqueue_script( 'wc-enhanced-select' );
		}


        /**
         * Get sections.
         *
         * @return array
         */
        public function get_sections() {

            $sections = array(
                '' => __( 'Settings', 'wc-bordereau-generator' ),
            );

            $providers = BordereauGeneratorProviders::get_providers();

            $enabledProviders = get_option($this->id.'_allowed_providers');

            foreach ($providers as $key => $provider) {
                if(is_array($enabledProviders) && in_array($key, $enabledProviders)) {
                    $sections[$provider['slug']] = $provider['name'];
                }
            }

            return apply_filters( 'woocommerce_get_sections_' . $this->id, $sections );
        }


        /**
         * Get settings array
         *
         * @return array
         */
        public function get_settings() {

            global $current_section;
            $settings = array();

            $providers = BordereauGeneratorProviders::get_providers();
            $enabledProviders = get_option($this->id.'_allowed_providers');

            if($current_section !== '') {
                foreach ($providers as $key => $provider) {
                    if($current_section == $key) {
                        if(class_exists($provider['setting_class'])) {
                            $providerClass = new $provider['setting_class']($provider);
                            $settings = $providerClass->get_settings();
                        }
                    }
                }
            } else {
                include 'Partials/wc-bordereau-generator-settings-main.php';
            }
            return apply_filters( 'woocommerce_get_settings_' . $this->id, $settings, $current_section );
        }

        /**
         * Output the settings
         */
        public function output() {
            global $current_section;

	        $settings = [];
            $providers = BordereauGeneratorProviders::get_providers();
            $enabledProviders = get_option('wc_bordereau_allowed_providers');

            if($current_section !== '') {
                foreach ($providers as $key => $provider) {
//                    if(in_array($key, $enabledProviders)) {
                        if($current_section == $key) {
                            if(class_exists($provider['setting_class'])) {
                                $providerClass = new $provider['setting_class']($provider);
                                $settings = $providerClass->get_settings();
                                WC_Admin_Settings::output_fields( $settings );
                            }
                        }
//                    }

                }
            } else {
                include 'Partials/wc-bordereau-generator-settings-main.php';
                WC_Admin_Settings::output_fields( $settings );
            }
        }

        /**
         * Save settings
         *
         * @since 1.0
         */
        public function save() {
            $settings = $this->get_settings();
            WC_Admin_Settings::save_fields( $settings );
        }
    }
}

return new BordereauGeneratorWCSettings();
