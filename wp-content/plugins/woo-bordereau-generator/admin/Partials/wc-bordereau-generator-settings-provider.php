<?php

use WooBordereauGenerator\Admin\Partials\BordereauGeneratorProviders;

$providers = BordereauGeneratorProviders::get_providers();

$prefix = 'wc_bordereau_';

$settings = array(
    array(
        'name' => __( 'General Configuration', 'woo-bordereau-generator' ),
        'type' => 'title',
        'id'   => $prefix . 'general_config_settings'
    ),
    array(
        'name' => __( 'General Configuration', 'woo-bordereau-generator' ),
        'type' => 'title',
        'id'   => $prefix . 'general_config_settings'
    ),
    array(
        'name' => __( 'General Configuration', 'woo-bordereau-generator' ),
        'type' => 'title',
        'id'   => $prefix . 'general_config_settings'
    ),
);

?>


