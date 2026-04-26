<?php
/**
 * Jimee Cosmetics Theme — functions.php
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'JIMEE_VERSION', '2.9.6' );
define( 'JIMEE_DIR', get_template_directory() );
define( 'JIMEE_URI', get_template_directory_uri() );

require_once JIMEE_DIR . '/inc/theme-setup.php';
require_once JIMEE_DIR . '/inc/enqueue.php';
require_once JIMEE_DIR . '/inc/helpers.php';
require_once JIMEE_DIR . '/inc/woocommerce.php';
// Shipping handled by woo-bordereau-generator plugin (Yalidine API)
// require_once JIMEE_DIR . '/inc/shipping-yalidine.php';
require_once JIMEE_DIR . '/inc/ajax-handlers.php';
require_once JIMEE_DIR . '/inc/login-style.php';
require_once JIMEE_DIR . '/inc/roles.php';
require_once JIMEE_DIR . '/inc/admin-banners.php';
require_once JIMEE_DIR . '/inc/admin-product.php';
require_once JIMEE_DIR . '/inc/back-in-stock.php';
require_once JIMEE_DIR . '/inc/coming-soon.php';
