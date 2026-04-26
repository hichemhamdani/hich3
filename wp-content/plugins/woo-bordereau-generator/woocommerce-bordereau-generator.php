<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://amineware.com
 * @since             1.0.0
 * @package           wc-bordereau-generator
 *
 * @wordpress-plugin
 * Plugin Name:       Bordereau Generator
 * Plugin URI:        https://amineware.me
 * Description:       Generate sliip automatically using Yalidine and EcoTrack API.
 * Version:           4.20.8
 * Author:            Boukraa Mohamed
 * Author URI:        https://amineware.me
 * Text Domain:       woo-bordereau-generator
 * Domain Path:       /languages
 * Requires at least: 5.8
 * Tested up to: 9.0
 * Requires PHP: 7.4
 */


// If this file is called directly, abort.
if (! defined('WPINC')) {
    die;
}
require_once __DIR__ . '/vendor/autoload.php';

use WooBordereauGenerator\Admin\Partials\BordereauGeneratorProviders;
use WooBordereauGenerator\BordereauGenerator;
use WooBordereauGenerator\BordereauGeneratorActivator;
use WooBordereauGenerator\BordereauGeneratorDeactivate;
use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */

const WC_BORDEREAU_GENERATOR_VERSION = '4.20.8';
const WC_BORDEREAU_PLUGIN_PATH = __FILE__;
const WC_BORDEREAU_POST_TYPE = 'wc-bordereau-generator';

if (!function_exists('is_plugin_active')) {
    include_once(ABSPATH . '/wp-admin/includes/plugin.php');
}

/**
* Check for the existence of WooCommerce and any other requirements
*/
function wc_bordereau_generator_check_requirements()
{
    if (is_plugin_active('woocommerce/woocommerce.php')) {
        return true;
    } else {
        add_action('admin_notices', 'wc_bordereau_generator_missing_wc_notice');
        return false;
    }
}

function wc_bordereau_generator_update_check()
{
    $current_version = get_option('wc_bordereau_generator_version', '4.0.10');

    $new_version = WC_BORDEREAU_GENERATOR_VERSION;

    if (version_compare($current_version, $new_version, '<')) {
        // Future update scripts or fixes go here
        // Update the stored version to the new version after applying any fixes
        update_option('wc_bordereau_generator_version', $new_version);
    }
}

function custom_notification () {
    // add_action( 'admin_notices', 'custom_woocommerce_order_admin_notice' );
}

function custom_woocommerce_order_admin_notice() {
    global $pagenow, $typenow;

//    if (is_zr_enabled()) {
//
//        if ( 'edit.php' === $pagenow && 'shop_order' === $typenow ) {
//            // Your custom notice message
//            $message = 'ZR Express a ajouté une nouvelle couche de protection, rendant la méthode actuelle invalide. Veuillez activer l\'API ZR Express.';
//
//            echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
//        }
//    }
}

/**
* Display a message advising WooCommerce is required
*/
function wc_bordereau_generator_missing_wc_notice()
{
    $class = 'notice notice-error';
    $message = __('Bordereau Generator requires WooCommerce to be installed and active.', 'woo-bordereau-generator');
    printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
}

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-p3k-galactica-activator.php
 */
function activate_wc_bordereau_generator()
{
    BordereauGeneratorActivator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-p3k-galactica-deactivator.php
 */
function deactivate_wc_bordereau_generator()
{
    BordereauGeneratorDeactivate::deactivate();
}

add_action('plugins_loaded', 'wc_bordereau_generator_check_requirements');
add_action('plugins_loaded', 'custom_notification');
add_action('plugins_loaded', 'wc_bordereau_generator_update_check');
add_action('upgrader_process_complete', 'wc_bordereau_generator_upgrader_process_complete', 10, 2);

register_activation_hook(__FILE__, 'activate_wc_bordereau_generator');
register_deactivation_hook(__FILE__, 'deactivate_wc_bordereau_generator');


function wc_bordereau_generator_upgrader_process_complete($upgrader_object, $options) {
    // Check if it's an update to our plugin
    if ($options['action'] == 'update' && $options['type'] == 'plugin' ) {
        // Ensure our plugin is in the list of updated plugins
        if (isset($options['plugins']) && is_array($options['plugins'])) {
            $plugin_path = plugin_basename(__FILE__); // Path to main plugin file
            if (in_array($plugin_path, $options['plugins'])) {
                // Perform actions after your plugin has been updated

                global $wpdb;

                // Example: Update wp_posts table
                // (Adjust your query as needed)
                $wpdb->query("
                    UPDATE {$wpdb->prefix}posts
                    SET post_status = CONCAT('wc-', post_status)
                    WHERE post_type = 'shop_order'
                    AND post_status NOT LIKE 'wc-%'
                    AND CHAR_LENGTH(CONCAT('wc-', post_status)) <= 20
                ");

                // Check for and update wp_wc_orders table if it exists
                $table_name = $wpdb->prefix . 'wc_orders';
                if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") == $table_name) {
                    $wpdb->query("
                        UPDATE {$table_name}
                        SET status = CONCAT('wc-', status)
                        WHERE status NOT LIKE 'wc-%'
                        AND CHAR_LENGTH(CONCAT('wc-', status)) <= 20
                    ");
                }

                // Update plugin version or perform other actions
                update_option('my_plugin_version', WC_BORDEREAU_GENERATOR_VERSION);
            }
        }
    }
}
/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_wc_bordereau_generator()
{
    // prevent the extension showing in create post
    if (wc_bordereau_generator_check_requirements()) {
        $plugin = new BordereauGenerator();
        $plugin->run();
    }
}

/**
 * @return bool
 */

if (! function_exists('yalidine_is_enabled')) {
    function yalidine_is_enabled() {
        $enabledProviders = get_option('wc-bordereau-generator_allowed_providers');
        if(is_array($enabledProviders)) {
            $yalidine = ['speedmail', 'yalidine', 'guepex', 'easyandspeed', 'yalitec', 'wecanservices'];
            $common = array_intersect($yalidine, $enabledProviders);
            return count($common) > 0;
        }
    }
}

if (! function_exists('yalitec_is_enabled')) {
    function yalitec_is_enabled() {
        $enabledProviders = get_option('wc-bordereau-generator_allowed_providers');
        if(is_array($enabledProviders)) {
            $yalidine = ['yalitec_new'];
            $common = array_intersect($yalidine, $enabledProviders);
            return count($common) > 0;
        }
    }
}

if (! function_exists('mdm_is_enabled')) {
    function mdm_is_enabled() {
        $enabledProviders = get_option('wc-bordereau-generator_allowed_providers');
        if(is_array($enabledProviders)) {
            $mdm = ['mdm'];
            $common = array_intersect($mdm, $enabledProviders);
            return count($common) > 0;
        }
    }
}

if (! function_exists('commune_supplement_enabled')) {

    function commune_supplement_enabled($provider = null)
    {
        if ($provider) {
            return yalidine_is_enabled() &&
                (get_option('added_fee_yalidine_total') == 'with_added_fee_' . $provider);
        }
        return yalidine_is_enabled() &&
            (get_option('added_fee_yalidine_total') == 'with_added_fee_yalidine') ||
            (get_option('added_fee_guepex_total') == 'with_added_fee_guepex') ||
            (get_option('added_fee_wecanservices_total') == 'with_added_fee_wecanservices') ||
            (get_option('added_fee_yalitec_total') == 'with_added_fee_yalitec') ||
            (get_option('added_fee_easyandspeed_total') == 'with_added_fee_easyandspeed') ||
            (get_option('added_fee_speedmail_total') == 'with_added_fee_speedmail') ||
            (get_option('added_fee_mdm_total') == 'with_added_fee_mdm');
    }
}

/**
 * @return bool
 */

if (! function_exists('only_yalidine_is_enabled')) {

    function only_yalidine_is_enabled()
    {
        $enabledProviders = get_option('wc-bordereau-generator_allowed_providers');
        $options = [
            'yalidine_show_center',
            'guepex_show_center',
            'wecanservices_show_center',
            'speedmail_show_center',
            'easyandspeed_show_center',
	        'zimoexpress_show_center',
            'elogistia_show_center'
        ];

        $show_center = false; // Default to not showing

        foreach ($options as $option_name) {
            if (get_option($option_name) !== 'hide_centers') {
                $show_center = true;
                break; // Exit the loop once a match is found
            }
        }

        $existing = false;

        if(is_array($enabledProviders)) {

            $differentValues = array_filter($enabledProviders, function ($value) {
                return ! in_array($value, ['yalidine', 'guepex', 'speedmail', 'easyandspeed', 'zimoexpress', 'wecanservices', 'elogistia']);
            });

            $existing = empty($differentValues);
        }

        return $existing && $show_center;
    }
}

if (! function_exists('only_noest_is_enabled')) {

    function only_noest_is_enabled()
    {
        $enabledProviders = get_option('wc-bordereau-generator_allowed_providers');
        $options = [
            'nord_ouest_show_center',
        ];

        $show_center = false; // Default to not showing

        foreach ($options as $option_name) {
            if (get_option($option_name) !== 'hide_centers') {
                $show_center = true;
                break; // Exit the loop once a match is found
            }
        }

        $existing = false;

        if(is_array($enabledProviders)) {

            $differentValues = array_filter($enabledProviders, function ($value) {
                return ! in_array($value, ['nord_ouest', 'nordouest']);
            });

            $existing = empty($differentValues);
        }

        return $existing && $show_center;
    }
}

if (!function_exists('is_shipping_company_enabled')) {

    function is_shipping_company_enabled($shipping)
    {
        $enabledProviders = get_option('wc-bordereau-generator_allowed_providers');

        return is_array($enabledProviders) && in_array($shipping, $enabledProviders);
    }
}


if (! function_exists('show_yalidine_centers')) {

    function show_yalidine_centers() {

        $options = [
            'yalidine_show_center',
            'guepex_show_center',
            'wecanservices_show_center',
            'speedmail_show_center',
            'easyandspeed_show_center',
            'yalitec_show_center'
        ];

        $show_center = false; // Default to not showing

        foreach ($options as $option_name) {
            if (get_option($option_name) && get_option($option_name) !== 'hide_centers') {
                $show_center = true;
                break; // Exit the loop once a match is found
            }
        }

        return $show_center;
    }
}

if (! function_exists('show_yalitec_centers')) {

	function show_yalitec_centers() {

		$options = [
			'yalitec_new_show_center',
		];

		$show_center = false; // Default to not showing

		foreach ($options as $option_name) {
			if (get_option($option_name) && get_option($option_name) !== 'hide_centers') {
				$show_center = true;
				break; // Exit the loop once a match is found
			}
		}

		return $show_center;
	}
}

if (! function_exists('show_mdm_centers')) {

	function show_mdm_centers() {

		$options = [
			'mdm_show_center',
		];

		$show_center = false;

		foreach ($options as $option_name) {
			if (get_option($option_name) && get_option($option_name) !== 'hide_centers') {
				$show_center = true;
				break;
			}
		}

		return $show_center;
	}
}

if (! function_exists('show_nord_ouest_centers')) {

    function show_nord_ouest_centers() {

        $options = [
            'nord_ouest_show_center',
        ];

        $show_center = false; // Default to not showing

        foreach ($options as $option_name) {
            if (get_option($option_name) && get_option($option_name) !== 'hide_centers') {
                $show_center = true;
                break; // Exit the loop once a match is found
            }
        }

        return $show_center;
    }
}

if (! function_exists('show_toncolis_api_centers')) {

    function show_toncolis_api_centers() {
        // Covers toncolis_api, ecom_dz_new, flashfr — all use the same provider type
        $slugs = ['toncolis_api', 'ecom_dz_new', 'flashfr'];
        foreach ($slugs as $slug) {
            $val = get_option($slug . '_show_center');
            if ($val && $val !== 'hide_centers') {
                return true;
            }
        }
        return false;
    }
}

if (! function_exists('show_zimoexpress_centers')) {

	function show_zimoexpress_centers() {

		$options = [
			'zimoexpress_show_center',
		];

		$show_center = false; // Default to not showing

		foreach ($options as $option_name) {
			if (get_option($option_name) && get_option($option_name) !== 'hide_centers') {
				$show_center = true;
				break; // Exit the loop once a match is found
			}
		}

		return $show_center;
	}
}

if (! function_exists('show_zimoexpress_centers')) {

    function show_zimoexpress_centers() {

        $options = [
            'zimoexpress_show_center',
        ];

        $show_center = false; // Default to not showing

        foreach ($options as $option_name) {
            if (get_option($option_name) && get_option($option_name) !== 'hide_centers') {
                $show_center = true;
                break; // Exit the loop once a match is found
            }
        }

        return $show_center;
    }
}

if (! function_exists('show_zrexpress_v2_centers')) {

	function show_zrexpress_v2_centers() {

		$options = [
			'zrexpress_v2_show_center',
		];

		$show_center = false; // Default to not showing

		foreach ($options as $option_name) {
			if (get_option($option_name) && get_option($option_name) !== 'hide_centers') {
				$show_center = true;
				break; // Exit the loop once a match is found
			}
		}

		return $show_center;
	}
}

if (! function_exists('show_near_delivery_centers')) {

	function show_near_delivery_centers() {
		$show_center = get_option('near_delivery_show_center');
		if ($show_center && $show_center === 'show_centers') {
			return true;
		}
		return false;
	}
}

if (! function_exists('show_elogistia_centers')) {

	function show_elogistia_centers() {

		$options = [
			'elogistia_show_center',
		];

		$show_center = false; // Default to not showing

		foreach ($options as $option_name) {
			if (get_option($option_name) && get_option($option_name) !== 'hide_centers') {
				$show_center = true;
				break; // Exit the loop once a match is found
			}
		}

		return $show_center;
	}
}

if (!function_exists('is_zr_enabled')) {
    function is_zr_enabled() {
        $enabledProviders = get_option('wc-bordereau-generator_allowed_providers');
        if (is_array($enabledProviders)) {
            return in_array('zr_express_new', $enabledProviders) || in_array('zr_express', $enabledProviders);
        }
    }
}

if (!function_exists('is_ecotrack_providers_enabled')) {
    function is_ecotrack_providers_enabled() {
        $enabledProviders = get_option('wc-bordereau-generator_allowed_providers');
        if (is_array($enabledProviders)) {
            $providers = BordereauGeneratorProviders::get_providers();
            $ecotrack = false;
            foreach ($providers as $provider) {
                if (in_array($provider['slug'], $enabledProviders)) {
                    if ($provider['type'] == 'ecotrack') {
                        $ecotrack = true;
                    }
                }
            }

            return $ecotrack;
        }

        return false;

    }
}

if (!function_exists('is_maystro_delivery_enabled')) {

    function is_maystro_delivery_enabled() {
        $enabledProviders = get_option('wc-bordereau-generator_allowed_providers');
        if (is_array($enabledProviders)) {
            return in_array('maystro_delivery', $enabledProviders);
        }
    }

}

if (!function_exists('is_3m_express_enabled')) {
    function is_3m_express_enabled() {
        $enabledProviders = get_option('wc-bordereau-generator_allowed_providers');
        if (is_array($enabledProviders)) {
            return in_array('3m_express', $enabledProviders);
        }
    }
}


if (!function_exists('is_nord_ouest_enabled')) {
    function is_nord_ouest_enabled() {
        $enabledProviders = get_option('wc-bordereau-generator_allowed_providers');
        if (is_array($enabledProviders)) {
            return in_array('nord_ouest', $enabledProviders);
        }
    }
}

if (! function_exists('wc_get_bordereau_order_statuses')) {

    function wc_get_bordereau_order_statuses() {

        global $wpdb;

        $core_statuses = wc_get_bordereau_core_order_statuses();

        foreach ($core_statuses as $key => $status) {

            $slugCore_without_prefix = substr(str_replace('wc-', '', $key), 0, 17);
            $existsCore = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->posts WHERE post_name = %s AND post_type = %s AND post_status != 'trash'", $slugCore_without_prefix, 'bordreau_order_status'));

            if (null === $existsCore) {

                $postId = wp_insert_post(array(
                    'post_name' => $slugCore_without_prefix,
                    'post_title' => $status,
                    'post_type' => 'bordreau_order_status',
                    'post_status' => 'publish'
                ));

                update_post_meta($postId, "order_status_add_bulk", true);

                return $postId;
            }
        }

        $order_statuses = [];

        $result = $wpdb->get_results($wpdb->prepare("SELECT * FROM $wpdb->posts WHERE post_type = %s AND post_status != 'trash'", 'bordreau_order_status'));

        foreach ($result as $item) {
            $slug = $item->post_name;

            $slug = str_replace('wc-', '', $slug);

            $order_statuses['wc-' . $slug] = $item->post_title;
        }

        return $order_statuses;
    }
}

if (! function_exists('wc_get_bordereau_core_order_statuses')) {

    function wc_get_bordereau_core_order_statuses() {

        return array(
            'wc-pending'                => _x( 'Pending payment', 'Order status', 'woocommerce' ),
            'wc-processing'             => _x( 'Processing', 'Order status', 'woocommerce' ),
            'wc-on-hold'                => _x( 'On hold', 'Order status', 'woocommerce' ),
            'wc-completed'              => _x( 'Completed', 'Order status', 'woocommerce' ),
            'wc-cancelled'              => _x( 'Cancelled', 'Order status', 'woocommerce' ),
            'wc-refunded'               => _x( 'Refunded', 'Order status', 'woocommerce' ),
            'wc-failed'                 => _x( 'Failed', 'Order status', 'woocommerce' ),
            'wc-dispatched'             => _x( 'Dispatched', 'Order status', 'woo-bordereau-generator' ),
            'wc-awaiting-shipping'      => _x( 'Awaiting shipping', 'Order status', 'woo-bordereau-generator' ),
        );
    }
}


/**
 * @since 1.5.0
 * @return false|mixed|null
 */
function get_bordreau_generator_integrity()
{
    return get_option(base64_decode('X3dvb19ib3JkcmVhdV9nZW5lcmF0b3JfbGljZW5zZQ=='));
}

/**
 * @return false|mixed|null
 */
function get_bordreau_generator_status()
{
    return get_option(base64_decode('X3dvb19ib3JkcmVhdV9nZW5lcmF0b3JfbGljZW5zZV9zdGF0dXM='), 'disabled');
}

function is_hanout_enabled() {
    // The slug of the theme you want to check.
    $theme_slug = ['hanout', 'codtheme', 'jude', 'hostazi.com', 'matjerna.com', 'judev2-premium'];

    // Get the current theme object.
    $current_theme = wp_get_theme();

    if ( in_array( 'wooecom-premium/wooecom-premium.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        return true;
    }

    if ( in_array( 'codplugin/codplugin.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        return true;
    }

    return in_array($current_theme->get_stylesheet(), $theme_slug)  || in_array($current_theme->get('TextDomain'), $theme_slug);
}

function is_quickform_enabled() {
    if ( in_array( 'QuickFORM/quickform.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        return true;
    }
    return false;
}

function is_tasheel_enabled() {
    if ( in_array( 'tasheel/tasheel.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
        return true;
    }
}
/**
 * @param $status
 * @return void
 */
function put_bordreau_generator_status($status)
{
    update_option(base64_decode('X3dvb19ib3JkcmVhdV9nZW5lcmF0b3JfbGljZW5zZV9zdGF0dXM='), $status);
}

PucFactory::buildUpdateChecker(
    base64_decode('aHR0cHM6Ly9hbWluZXdhcmUubWUvd29vLWNvbW1lcmNlL3VwZGF0ZT9hY3Rpb249Z2V0X21ldGFkYXRhJnNsdWc9d29vLWJvcmRlcmVhdS1nZW5lcmF0b3ImbGljZW5zZT0='). get_bordreau_generator_integrity(). '&key='. base64_encode(get_site_url()). '&version='.WC_BORDEREAU_GENERATOR_VERSION,     //Metadata URL.
    __FILE__,
    'wc-bordereau-generator',
    12,
    'wc_bordereau_generator_update_checker_option'
);

if (! class_exists('ActionScheduler', false) || ! ActionScheduler::is_initialized()) {
    require_once(dirname(__FILE__) . '/vendor/woocommerce/action-scheduler/classes/abstracts/ActionScheduler.php');
    ActionScheduler::init(dirname(__FILE__) . '/vendor/woocommerce/action-scheduler/action-scheduler.php');
}

/**
 * Check if this is a shop_order page (edit or list)
 */
if (! function_exists('is_order_page')) {

    function is_order_page() {

        if ( !function_exists( 'get_current_screen' ) ) {
            require_once ABSPATH . '/wp-admin/includes/screen.php';
        }

        $screen = get_current_screen();
        if ( ! is_null( $screen ) && in_array( $screen->id, array( 'shop_order', 'edit-shop_order', 'woocommerce_page_wc-orders' ) ) ) {
            return true;
        } else {
            return false;
        }
    }
}

function one_page_product_checkout_is_enabled() {
    // Include the plugin.php file if it's not already included
    if ( !function_exists('is_plugin_active') ) {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }

// Check if a certain plugin is active
    if (is_plugin_active('one-page-product-checkout/woo-one-page-checkout.php')) {
        return true;
    }

    return false;
}

/**
 * Log in Woocommerce Log Reader
 * @param $type
 * @param $message
 * @return void
 */
function bordreau_log_entries($message, $type = null) {

    if (get_option('wc_bordreau_enable-debug') == 'true') {

        $logger = wc_get_logger();

        switch ($type) {
            case 'notice':
                $logger->notice($message, array('source' => WC_BORDEREAU_POST_TYPE));
                break;
            case 'warning':
                $logger->warning($message, array('source' => WC_BORDEREAU_POST_TYPE));
                break;
            case 'error':
                $logger->error($message, array('source' => WC_BORDEREAU_POST_TYPE));
                break;
            default:
                $logger->info($message, array('source' => WC_BORDEREAU_POST_TYPE));
                break;
        }
    }
}


add_action('plugins_loaded', 'bordereau_cli_init_function');

function bordereau_cli_init_function() {
    if (defined('WP_CLI') && WP_CLI) {
        WP_CLI::add_command('bordreau bulk_tracking', 'WooBordereauGenerator\Cli\BordreauBulkTrackingCli', [
            'shortdesc'=>'Bulk Tracking all orders and change the tracking status',
            'synopsis'=>[
                [
                    'type'=>'flag',
                    'name'=>'provider',
                    'description'=>'Select the provider you want to check',
                    'optional'=>true
                ]
            ]
        ]);
    }

    run_wc_bordereau_generator();
}
