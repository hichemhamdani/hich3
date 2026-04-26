

<?php

use WooBordereauGenerator\Admin\Partials\BordereauGeneratorProviders;

$providers = BordereauGeneratorProviders::get_providers();

$providersOptions = [];

foreach ($providers as $key => $provider) {
    $providersOptions[$key] = $provider['name'];
}

$prefix = 'wc-bordereau-generator_';


// $settings = array(

//         array(
//             'name' => __( 'General Configuration', 'woo-bordereau-generator' ),
//             'type' => 'title',
//             'id'   => $prefix . 'general_config_settings'
//         ),
//         array(
//             'title'   => __( 'Select Specific Providers', 'woocommerce' ),
//             'desc'    => '',
//             'id'      => $prefix .'allowed_providers',
//             'css'     => 'min-width: 350px;',
//             'default' => '',
//             'options' => $providersOptions,
//             'type'    => 'multiselect',
//         ),

//         'section_end' => array(
//             'type' => 'sectionend',
//             'id' => $prefix . 'general_config_settings'
//         )
//     );
$GLOBALS['hide_save_button'] = true;
$url = rest_url('woo-bordereau/v1/webhook/yalidine');

?>

<div id="bordereau-provider-list"
     data-version="<?php echo WC_BORDEREAU_GENERATOR_VERSION ;?>"
     data-webhook-url="<?php echo $url; ?>"
     data-nonce="<?php echo wp_create_nonce('wc_bordereau_providers_list_update' ) ?>">
</div>
