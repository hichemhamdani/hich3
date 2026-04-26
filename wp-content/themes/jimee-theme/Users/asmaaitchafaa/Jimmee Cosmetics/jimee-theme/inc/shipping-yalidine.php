<?php
/**
 * Yalidine Shipping Method — tarifs par wilaya.
 * Domicile + Point relais + Retrait boutique (Alger only).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

add_action( 'woocommerce_shipping_init', 'jimee_yalidine_shipping_init' );
function jimee_yalidine_shipping_init() {

    class WC_Shipping_Yalidine extends WC_Shipping_Method {

        /** Grille tarifaire : code wilaya => [ domicile, point_relais ] */
        private $tarifs = [
            '01' => [1400, 1000], // Adrar
            '02' => [ 700,  550], // Chlef
            '03' => [ 900,  650], // Laghouat
            '04' => [ 700,  550], // Oum El Bouaghi
            '05' => [ 700,  550], // Batna
            '06' => [ 700,  550], // Béjaïa
            '07' => [ 900,  650], // Biskra
            '08' => [1400, 1000], // Béchar
            '09' => [ 600,  500], // Blida
            '10' => [ 700,  550], // Bouira
            '11' => [1600, 1400], // Tamanrasset
            '12' => [ 900,  650], // Tébessa
            '13' => [ 700,  550], // Tlemcen
            '14' => [ 700,  550], // Tiaret
            '15' => [ 700,  550], // Tizi Ouzou
            '16' => [ 500,  400], // Alger
            '17' => [ 900,  650], // Djelfa
            '18' => [ 700,  550], // Jijel
            '19' => [ 700,  550], // Sétif
            '20' => [ 700,  550], // Saïda
            '21' => [ 700,  550], // Skikda
            '22' => [ 700,  550], // Sidi Bel Abbès
            '23' => [ 700,  550], // Annaba
            '24' => [ 700,  550], // Guelma
            '25' => [ 700,  550], // Constantine
            '26' => [ 700,  550], // Médéa
            '27' => [ 700,  550], // Mostaganem
            '28' => [ 700,  550], // M'Sila
            '29' => [ 700,  550], // Mascara
            '30' => [ 900,  650], // Ouargla
            '31' => [ 700,  550], // Oran
            '32' => [1400, 1000], // El Bayadh
            '33' => [1600, 1400], // Illizi
            '34' => [ 700,  550], // Bordj Bou Arréridj
            '35' => [ 600,  500], // Boumerdès
            '36' => [ 700,  550], // El Tarf
            '37' => [1600, 1400], // Tindouf
            '38' => [ 700,  550], // Tissemsilt
            '39' => [ 900,  650], // El Oued
            '40' => [ 700,  550], // Khenchela
            '41' => [ 700,  550], // Souk Ahras
            '42' => [ 600,  500], // Tipaza
            '43' => [ 700,  550], // Mila
            '44' => [ 700,  550], // Aïn Defla
            '45' => [1400, 1000], // Naâma
            '46' => [ 700,  550], // Aïn Témouchent
            '47' => [ 900,  650], // Ghardaïa
            '48' => [ 700,  550], // Relizane
            '49' => [ 900,  650], // El M'Ghair
            '50' => [ 900,  650], // El Meniaa
            '51' => [ 900,  650], // Ouled Djellal
            '52' => [1600, 1000], // Bordj Badji Mokhtar
            '53' => [1500, 1000], // Béni Abbès
            '54' => [1400, 1000], // Timimoun
            '55' => [ 900,  650], // Touggourt
            '56' => [1800, 1400], // Djanet
            '57' => [1600, 1400], // In Salah
            '58' => [1800, 1400], // In Guezzam
        ];

        public function __construct( $instance_id = 0 ) {
            $this->id                 = 'yalidine';
            $this->instance_id        = absint( $instance_id );
            $this->method_title       = 'Yalidine';
            $this->method_description = 'Livraison Yalidine — tarifs par wilaya (domicile + point relais).';
            $this->supports           = [ 'shipping-zones', 'instance-settings' ];
            $this->title              = 'Yalidine';
            $this->enabled            = 'yes';

            $this->init();
        }

        public function init() {
            $this->init_form_fields();
            $this->init_settings();
            add_action( 'woocommerce_update_options_shipping_' . $this->id, [ $this, 'process_admin_options' ] );
        }

        public function init_form_fields() {
            $this->instance_form_fields = [
                'title' => [
                    'title'   => 'Titre',
                    'type'    => 'text',
                    'default' => 'Yalidine',
                ],
            ];
        }

        /**
         * Calculate shipping — adds 2 or 3 rates based on selected wilaya.
         */
        public function calculate_shipping( $package = [] ) {
            $state = $package['destination']['state'] ?? '';

            // Fallback: try billing_state from POST (WC sends it during checkout update)
            if ( empty( $state ) && ! empty( $_POST['billing_state'] ) ) {
                $state = sanitize_text_field( $_POST['billing_state'] );
            }
            // Also check post_data (WC AJAX serialized form)
            if ( empty( $state ) && ! empty( $_POST['post_data'] ) ) {
                parse_str( $_POST['post_data'], $post_data );
                $state = sanitize_text_field( $post_data['billing_state'] ?? '' );
            }

            // Bordereau uses DZ-XX format, our tarifs use XX — strip prefix
            $state = str_replace( 'DZ-', '', $state );

            if ( empty( $state ) || ! isset( $this->tarifs[ $state ] ) ) {
                // No wilaya selected yet — show placeholder
                $this->add_rate([
                    'id'    => $this->get_rate_id( 'pending' ),
                    'label' => 'Sélectionnez votre wilaya pour voir les tarifs',
                    'cost'  => 0,
                ]);
                return;
            }

            list( $domicile, $relais ) = $this->tarifs[ $state ];

            // 1. Livraison à domicile
            $this->add_rate([
                'id'    => $this->get_rate_id( 'domicile' ),
                'label' => 'Livraison à domicile',
                'cost'  => $domicile,
                'meta_data' => [ 'delai' => '2-5 jours' ],
            ]);

            // 2. Point relais Yalidine
            $this->add_rate([
                'id'    => $this->get_rate_id( 'relais' ),
                'label' => 'Point relais Yalidine',
                'cost'  => $relais,
                'meta_data' => [ 'delai' => '2-4 jours' ],
            ]);

            // 3. Retrait en boutique (Alger only)
            if ( $state === '16' ) {
                $this->add_rate([
                    'id'    => $this->get_rate_id( 'boutique' ),
                    'label' => 'Retrait en boutique (Kouba, Alger)',
                    'cost'  => 0,
                    'meta_data' => [ 'delai' => 'Panier réservé 24h' ],
                ]);
            }
        }
    }
}

// Register the shipping method
add_filter( 'woocommerce_shipping_methods', 'jimee_register_yalidine_shipping' );
function jimee_register_yalidine_shipping( $methods ) {
    $methods['yalidine'] = 'WC_Shipping_Yalidine';
    return $methods;
}

// Force shipping recalculation when billing_state changes
add_action( 'woocommerce_checkout_update_order_review', 'jimee_update_shipping_on_state_change' );
function jimee_update_shipping_on_state_change( $post_data ) {
    parse_str( $post_data, $data );
    if ( ! empty( $data['billing_state'] ) ) {
        WC()->customer->set_billing_state( sanitize_text_field( $data['billing_state'] ) );
        WC()->customer->set_shipping_state( sanitize_text_field( $data['billing_state'] ) );
    }
}

// Copy billing state to shipping state (we only use billing)
add_filter( 'woocommerce_shipping_packages', 'jimee_sync_billing_to_shipping_state' );
function jimee_sync_billing_to_shipping_state( $packages ) {
    if ( ! empty( $_POST['post_data'] ) ) {
        parse_str( $_POST['post_data'], $data );
        if ( ! empty( $data['billing_state'] ) ) {
            foreach ( $packages as &$pkg ) {
                $pkg['destination']['state'] = sanitize_text_field( $data['billing_state'] );
            }
        }
    }
    return $packages;
}
