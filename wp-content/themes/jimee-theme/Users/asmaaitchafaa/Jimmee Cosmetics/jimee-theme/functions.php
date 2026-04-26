<?php
/**
 * Jimee Cosmetics Theme — functions.php
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'JIMEE_VERSION', '2.5.5' );
define( 'JIMEE_DIR', get_template_directory() );
define( 'JIMEE_URI', get_template_directory_uri() );

require_once JIMEE_DIR . '/inc/theme-setup.php';
require_once JIMEE_DIR . '/inc/enqueue.php';
require_once JIMEE_DIR . '/inc/helpers.php';
require_once JIMEE_DIR . '/inc/woocommerce.php';
require_once JIMEE_DIR . '/inc/shipping-yalidine.php';
require_once JIMEE_DIR . '/inc/ajax-handlers.php';
