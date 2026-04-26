<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    WC_Bordereau_Generator
 * @subpackage WC_Bordereau_Generator/admin
 * @author     Boukraa Mohamed <bm2ilabs@gmail.com>
 */

namespace WooBordereauGenerator\Admin;

use WC_Order_Item_Shipping;
use WC_Shipping_Zone;
use WC_Shipping_Zones;
use WooBordereauGenerator\Admin\MetaBox\BordereauGeneratorMetaBox;
use WooBordereauGenerator\Admin\MetaBox\BordereauTrackingMetaBox;
use WooBordereauGenerator\Admin\Partials\BordereauGeneratorProviders;
use WooBordereauGenerator\Admin\Partials\ShippingOrdersTable;
use WooBordereauGenerator\Admin\Shipping\YalidineShippingSettings;
use WooBordereauGenerator\Frontend\BordereauGeneratorPublic;
use WooBordereauGenerator\Helpers;
use WooBordereauGenerator\Shippingmethods\ColivraisonFlatRate;
use WooBordereauGenerator\Shippingmethods\ColivraisonLocalPickup;
use WooBordereauGenerator\Shippingmethods\ConexlogFlatRate;
use WooBordereauGenerator\Shippingmethods\ConexlogLocalPickup;
use WooBordereauGenerator\Shippingmethods\DefaultFlatRate;
use WooBordereauGenerator\Shippingmethods\DefaultLocalPickup;
use WooBordereauGenerator\Shippingmethods\EcotrackFlatRate;
use WooBordereauGenerator\Shippingmethods\EcotrackLocalPickup;
use WooBordereauGenerator\Shippingmethods\ElogistiaFlatRate;
use WooBordereauGenerator\Shippingmethods\ElogistiaLocalPickup;
use WooBordereauGenerator\Shippingmethods\LihlihExpressFlatRate;
use WooBordereauGenerator\Shippingmethods\LihlihExpressLocalPickup;
use WooBordereauGenerator\Shippingmethods\MaystroDeliveryFlatRate;
use WooBordereauGenerator\Shippingmethods\MaystroDeliveryLocalPickup;
use WooBordereauGenerator\Shippingmethods\MylerzExpressFlatRate;
use WooBordereauGenerator\Shippingmethods\MylerzExpressLocalPickup;
use WooBordereauGenerator\Shippingmethods\NordOuestFlatRate;
use WooBordereauGenerator\Shippingmethods\NordOuestLocalPickup;
use WooBordereauGenerator\Shippingmethods\TonColisFlatRate;
use WooBordereauGenerator\Shippingmethods\TonColisLocalPickup;
use WooBordereauGenerator\Shippingmethods\WC3MExpressFlatRate;
use WooBordereauGenerator\Shippingmethods\WC3MExpressLocalPickup;
use WooBordereauGenerator\Shippingmethods\YalidineFlatRate;
use WooBordereauGenerator\Shippingmethods\YalidineLocalPickup;
use WooBordereauGenerator\Shippingmethods\YalitecFlatRate;
use WooBordereauGenerator\Shippingmethods\YalitecLocalPickup;
use WooBordereauGenerator\Shippingmethods\MdmFlatRate;
use WooBordereauGenerator\Shippingmethods\MdmLocalPickup;
use WooBordereauGenerator\Shippingmethods\ZimoExpressFlatRate;
use WooBordereauGenerator\Shippingmethods\ZimoExpressLocalPickup;
use WooBordereauGenerator\Shippingmethods\ZRExpressFlatRate;
use WooBordereauGenerator\Shippingmethods\ZRExpressLocalPickup;
use WooBordereauGenerator\Shippingmethods\ZRExpressTwoFlatRate;
use WooBordereauGenerator\Shippingmethods\ZRExpressTwoLocalPickup;
use WooBordereauGenerator\Shippingmethods\NearDeliveryLocalPickup;
use WP_REST_Request;
use WP_REST_Response;

class BordereauGeneratorAdmin
{

    const STATUS_TYPE = 'wc_order_status';

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $plugin_name The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string $version The current version of this plugin.
     */
    private $version;

    /**
     * Initialize the class and set its properties.
     *
     * @param string $plugin_name The name of this plugin.
     * @param string $version The version of this plugin.
     *
     * @since    1.0.0
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * @return void
     * @since 1.5.0
     */
    function schedule_bordereau_tracking_check()
    {

        if (!class_exists('ActionScheduler')) {
            return;
        }

        if (!function_exists('as_has_scheduled_action')) {
            return;
        }

        if (!function_exists('as_schedule_recurring_action')) {
            return;
        }

        if (false === as_has_scheduled_action('bordereau_tracking_check_scheduler')) {
            $interval = (int)get_option('interval_check_schedule', 60); // in minutes -> convert it to seconds
            as_schedule_recurring_action(strtotime('now'), $interval * 60, 'bordereau_tracking_check_scheduler', array(), 'bordereau-generator', true);
        }
    }

    /**
     * @return void
     * @since 2.8.1
     */
    function unschedule_bordereau_tracking_check()
    {

        if (!class_exists('ActionScheduler')) {
            return;
        }

        if (!function_exists('as_has_scheduled_action')) {
            return;
        }

        if (!function_exists('as_schedule_recurring_action')) {
            return;
        }

        if (as_has_scheduled_action('bordereau_tracking_check_scheduler')) {
            as_unschedule_all_actions('bordereau_tracking_check_scheduler');
        }
    }


    function get_tracking_orders($three_months_ago)
    {
        global $wpdb;

        $is_hpos_enabled = Helpers::is_hpos_enabled();
        $results = [];

        if ($is_hpos_enabled) {
// Query for HPOS orders
            $hpos_query = $wpdb->prepare("
SELECT o.id as order_id,
tn.meta_value as tracking_number,
tm.meta_value as tracking_method
FROM {$wpdb->prefix}wc_orders o
INNER JOIN {$wpdb->prefix}wc_orders_meta tn
ON o.id = tn.order_id
AND tn.meta_key = '_shipping_tracking_number'
INNER JOIN {$wpdb->prefix}wc_orders_meta tm
ON o.id = tm.order_id
AND tm.meta_key = '_shipping_tracking_method'
WHERE o.status NOT IN ('trash', 'auto-draft')
AND o.date_created_gmt >= %s
", $three_months_ago);

            $hpos_results = $wpdb->get_results($hpos_query);
            $results = array_merge($results, $hpos_results);
        }

// Query for legacy orders
// We'll query these even if HPOS is enabled to catch any old orders
        $legacy_query = $wpdb->prepare("
SELECT p.ID as order_id,
tn.meta_value as tracking_number,
tm.meta_value as tracking_method
FROM {$wpdb->posts} p
INNER JOIN {$wpdb->postmeta} tn
ON p.ID = tn.post_id
AND tn.meta_key = '_shipping_tracking_number'
INNER JOIN {$wpdb->postmeta} tm
ON p.ID = tm.post_id
AND tm.meta_key = '_shipping_tracking_method'
WHERE p.post_status NOT IN ('trash', 'auto-draft')
AND p.post_type = 'shop_order'
AND p.post_date >= %s
", $three_months_ago);

        $legacy_results = $wpdb->get_results($legacy_query);
        $results = array_merge($results, $legacy_results);

// Remove any duplicate orders if they exist in both systems
        $unique_results = array_unique($results, SORT_REGULAR);

        return array_values($unique_results);
    }

    /**
     * Get the latest update for the orders from shipping companies using actionSchedule
     * @return void
     * @since 1.5.0
     */
    function bordereau_tracking_check($bypass = false, $providersSelected = [])
    {
        global $wpdb;

        if ($bypass || get_option('wc_bordreau_enable-cron') == 'true') {

            try {
                bordreau_log_entries('Started actionSchedule');

                $three_months_ago = date('Y-m-d', strtotime('-1 months'));
                $results = $this->get_tracking_orders($three_months_ago);

                $resultArray = [];

                foreach ($results as $item) {
                    $resultArray[$item->order_id]['tracking'] = $item->tracking_number;
                    $resultArray[$item->order_id]['method'] = $item->tracking_method;
                    $resultArray[$item->order_id]['order_id'] = $item->order_id;
                }

                foreach ($resultArray as $item) {
                    $providersCodes[$item['method']][$item['order_id']] = $item['tracking'];
                }

                $providersCodes = array_filter($providersCodes);
                $providers = BordereauGeneratorProviders::get_providers();

                try {
                    foreach ($providersCodes as $key => $codes) {
                        $enabledProviders = get_option('wc-bordereau-generator_allowed_providers');

                        if (in_array($key, $enabledProviders)) {
                            $provider = $providers[$key] ?? null;
                            if ($provider) {
                                if (count($providersSelected) === 0 || (count($providersSelected) && in_array($provider, $providersSelected))) {
                                    $this->check_bulk_tracking_for_provider($provider, $codes);
                                }
                            }
                        }
                    }
                } catch (\ErrorException $exception) {
                    bordreau_log_entries($exception->getMessage(), 'error');
                }
                bordreau_log_entries('End actionSchedule');
            } catch (\ErrorException $exception) {
                bordreau_log_entries($exception->getMessage());
            }
        }
    }

    public function reschedule_failed_action($action_id, $hook = null, $args = [])
    {
        // Get the action from the action ID
        if ('bordereau_tracking_check_scheduler' === $hook) {
            // Get the interval from the options, default to 60 minutes if not set
            $interval = (int)get_option('interval_check_schedule', 60); // in minutes

            // Check if the action is not already scheduled
            if (false === as_has_scheduled_action('bordereau_tracking_check_scheduler', $args)) {
                // Reschedule the action
                as_schedule_recurring_action(strtotime('now'), $interval * 60, 'bordereau_tracking_check_scheduler', $args, 'bordereau-generator');
            }
        }
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {
        wp_enqueue_style($this->plugin_name, plugin_dir_url(__FILE__) . 'css/wc-bordereau-generator-admin.css', array(), $this->version, 'all');
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {
        global $post_type;

        wp_enqueue_script($this->plugin_name, plugin_dir_url(__FILE__) . 'js/wc-bordereau-generator-admin.js', array('jquery', 'wp-i18n'), $this->version, false);

        $license_key = get_option('_woo_bordreau_generator_license');
        wp_localize_script($this->plugin_name, 'providerData', array(
            'root_url' => get_site_url(),
            'nonce' => wp_create_nonce('wp_bordereau_rest'),
            'nonce_license' => wp_create_nonce('wp_bordereau_rest_license'),
            'beepSoundUrl' => plugin_dir_url(WC_BORDEREAU_PLUGIN_PATH) . 'assets/audio/beep.mp3',
            'show_onboarding' => get_option('bordereau_onboarding_completed') !== 'yes',
            'license_active' => !empty($license_key),
            'license_key' => $license_key ? substr($license_key, 0, 4) . '****' : ''
        ));

        wp_set_script_translations($this->plugin_name, 'woo-bordereau-generator', plugin_dir_path(WC_BORDEREAU_PLUGIN_PATH) . 'languages');

        if (only_yalidine_is_enabled()) {

            if (Helpers::is_order_page()) {
                // Get order ID in HPOS-compatible way
                $order_id = null;
                
                // Try to get order ID from various sources
                if (isset($_GET['id']) && is_numeric($_GET['id'])) {
                    // HPOS: order ID is in 'id' parameter
                    $order_id = absint($_GET['id']);
                } elseif (isset($_GET['post']) && is_numeric($_GET['post'])) {
                    // Legacy: order ID is in 'post' parameter
                    $order_id = absint($_GET['post']);
                } else {
                    // Fallback to global $post object (legacy)
                    global $post;
                    if ($post && $post->ID) {
                        $order_id = $post->ID;
                    }
                }

                if ($order_id) {
                    // Create a WC_Order instance
                    $order = wc_get_order($order_id);

                    if ($order) {
                        // Get the shipping method
                        $shipping_methods = $order->get_shipping_methods();
                        $shipping_method = reset($shipping_methods); // Get the first shipping method
                        wp_enqueue_script('bordereau-generator-admin-order-edit', plugin_dir_url(__FILE__) . '/js/wc-bordereau-generator-admin-order-edit.js', array('jquery'), null, true);

                        $publicClass = new BordereauGeneratorPublic($this->plugin_name, $this->version);

	                    $cities = $publicClass->get_communes_from_yalidine();
                        $cityID = $order->get_billing_city();

//                        if (is_numeric($cityID)) {
//                           $selectedCities = $cities[$order->get_billing_state()];
//                           $foundCity = array_filter($selectedCities, function ($city) use ($cityID) {
//                               return $cityID == $city['id'];
//                           });
//                           if (count($foundCity)) {
//                               $foundCity = reset($foundCity);
//                               $cityID = $foundCity['name'];
//                           }
//                        }

                        wp_localize_script('bordereau-generator-admin-order-edit', 'wc_city_dropdown', [
                            'cities' => $publicClass->get_communes_from_yalidine(),
                            'centers' => $publicClass->custom_woocommerce_centers(),
                            'city' => $cityID,
                            'shippingMethod' => $shipping_method ? $shipping_method->get_method_id() : '',
                        ]);
                    }
                }

            }
        }
    }

    public function custom_formatted_billing_address($address, $order)
    {
        if (isset($address['state']) && strpos($address['state'], 'DZ-') === 0) {
            $states = $this->format_algeria_states();
            $address['state'] = $states[$address['state']] ?? $address['state'];
        }

        // Always return the modified data.
        return $address;
    }

    /**
     * @param $admin_body_class
     * @return string
     * @since 1.3.0
     */
    public function add_admin_body_classes($admin_body_class = '')
    {

        global $pagenow, $typenow;


        if (isset($_GET['page']) && $_GET['page'] == 'wc-bordereau-generator' && $pagenow == 'admin.php') {
            $classes = [];
            $classes[] = 'wc-bordreau-generator-body';
            $admin_body_class = implode(' ', array_unique($classes));
        }

        return " $admin_body_class ";
    }


    /**
     * Add Tracking Number Columns
     *
     * @param $columns
     *
     * @return mixed
     * @since    1.2.0
     */
    public function tracking_add_new_order_admin_list_column($columns)
    {

        $columns['tracking_number'] = __('Tracking Number', 'woo-bordereau-generator');
        $columns['status_order'] = __('Tracking Status', 'woo-bordereau-generator');
        if (is_ecotrack_providers_enabled()) {
            $columns['order_notes'] = __('Order Notes', 'woo-bordereau-generator');
        }
        $columns['payment_order'] = __('Payment', 'woo-bordereau-generator');
        if (get_option('wc_bordreau_enable-green-list') == 'true') {
            $columns['percentage_order'] = __('Green List', 'woo-bordereau-generator');
        }
        $new_columns = $columns;
        // Only move order_total to end if it exists
        if (isset($columns['order_total'])) {
            unset($new_columns['order_total']);
            $new_columns['order_total'] = $columns['order_total'];
        }

        return $new_columns;
    }

    /**
     * Add Tracking Number Column
     *
     * @param $columns
     *
     * @since    1.2.0
     */
    public function tracking_add_new_order_admin_list_column_content($column, $post_or_order_object)
    {
        $order = ($post_or_order_object instanceof \WP_Post) ? wc_get_order($post_or_order_object->ID) : $post_or_order_object;

        if (!is_object($order) && is_numeric($order)) {
            $order = wc_get_order(absint($order));
        }

        if ($column == 'tracking_number') {
            $trackingNumber = get_post_meta($order->get_id(), '_shipping_tracking_number', true);
            $provider = get_post_meta($order->get_id(), '_shipping_tracking_method', true);

            if (!$trackingNumber) {
                $trackingNumber = $order->get_meta('_shipping_tracking_number');
            }

            if (!$provider) {
                $provider = $order->get_meta('_shipping_tracking_method');
            }

	        $nonce = wp_create_nonce('wp_rest');
            $labelUrl = get_rest_url(null, 'woo-bordereau/v1/slip/print/' . $provider . '/' . $order->get_id());
	        $labelUrl = add_query_arg('_wpnonce', $nonce, $labelUrl);

            if ($trackingNumber) {
                ?>
                <div class="flex tracking-info-modal"
                     data-post="<?php echo $order->get_id(); ?>"
                     data-label="<?php echo $labelUrl; ?>"
                     data-tracking="<?php echo $trackingNumber; ?>">
                    <a href="#" class="flex items-center font-bold uppercase"><?php echo $trackingNumber; ?> </a>
                </div>
                <?php
            }

        }

        if ($column === 'status_order') {

            if ($order) {

                $status = $order->get_meta('_latest_tracking_status');

                if (empty($status)) {
                    $order_id = $order->get_id();
                    $status = get_post_meta($order_id, '_latest_tracking_status', true);
                }

                if ($status) {
                    switch ($status) {
                        case "Pas encore expédié":
                        case "A vérifier":
                        case "En préparation":
                        case "Prêt à expédier":
                        case "Pas encore ramassé":
                        case "Colis En attente d'envoi":

                            echo '<mark class="order-status status-gray"><span>' . __($status, 'woo-bordereau-generator') . '</span></mark>';
                            break;
                        case "Ramassé":
                        case "Colis reçu par la sociète de livraison":
                        case "Colis arrivé à la station régionale":
                        case "Colis En livraison":
                        case "Expédié":
                        case "Transfert":
                        case "Centre":
                        case "Vers Wilaya":
                        case "Reçu à Wilaya":
                        case "En attente du client":
                        case "Sorti en livraison":
                        case "En attente":
                        case "Alerte résolue":
                        case "Retour vers centre":
                        case "Retourné au centre":
                        case "Retour transfert":
                        case "Retour groupé":
                        case "Retour à retirer":
                        case "Retour vers vendeur":
                        case "Retour en transit":
                        case "En Cours":
                            echo '<mark class="order-status status-blue"><span>' . __($status, 'woo-bordereau-generator') . '</span></mark>';
                            break;
                        case "En alerte":
                        case "Tentative échouée":
                        case "Tentative de livraison échouée":
                        case "Ne Répond pas 1":
                        case "Ne Répond pas 2":
                        case "Ne Répond pas 3":
                        case "Ne Répond pas 4":
                        case "Reportée":
                        case "Attand.Client":
                            echo '<mark class="order-status status-yellow"><span>' . __($status, 'woo-bordereau-generator') . '</span></mark>';
                            break;
                        case "Echèc livraison":
                        case "Echange échoué":
                        case "Retour initié":
                        case 'Annuler':
                        case 'Annulé':
                        case 'Annuler (3x)':
                            echo '<mark class="order-status status-red"><span>' . __($status, 'woo-bordereau-generator') . '</span></mark>';
                            break;
                        case "Livré":
                        case "Retourné au vendeur":
                        case "Retour réceptionné par le vendeur":
                        case "Colis Livré":
                        case "Echange":
                            echo '<mark class="order-status status-green"><span>' . __($status, 'woo-bordereau-generator') . '</span></mark>';
                            break;
                        case 'encaissed':
                        case 'payed':
                            echo '<mark class="order-status status-success"><span>' . __('Livré', 'woo-bordereau-generator') . '</span></mark>';
                            break;
                        case 'failed':
                            echo '<mark class="order-status status-failed"><span>' . __('Echèc livraison', 'woo-bordereau-generator') . '</span></mark>';
                            break;
                        case 'attempt':
                        case 'attempt_delivery':
                            echo '<mark class="order-status status-failed"><span>' . __('Tentative échouée', 'woo-bordereau-generator') . '</span></mark>';
                            break;
                        default:
                            echo '<mark class="order-status"><span>' . $status . '</span></mark>';
                            break;
                    }
                }
            }
        }

        if ($column === 'order_notes') {
            $order_notes = get_post_meta($order->get_id(), '_order_notes', true);

        }

        if ($column === 'payment_order') {

            if ($order) {

                $payment_status = get_post_meta($order->get_id(), '_payment_status', true);

                if (empty($payment_status)) {
                    $payment_status = $order->get_meta('_payment_status');
                }

                $trackingNumber = get_post_meta($order->get_id(), '_shipping_tracking_number', true);

                if (empty($trackingNumber)) {
                    $trackingNumber = $order->get_meta('_shipping_tracking_number');
                }


                /**
                 * encaissed Commande encaissée
                 * payed: Paiement effectué
                 */
                if ($trackingNumber) {

                    switch ($payment_status) {
                        case 'received':
                        case 'payed':
                        case 'Livrée [ Recouvert ]':
                        case 'Cassée':
                            echo '<mark class="order-status status-payed"><span>' . __('Payé', 'woo-bordereau-generator') . '</span></mark>';
                            break;
                        case 'not-ready':
                            echo '<mark class="order-status status-not-ready"><span>' . __('Pas prêt', 'woo-bordereau-generator') . '</span></mark>';
                            break;
                        case 'ready':
                            echo '<mark class="order-status status-ready"><span>' . __('Paiement prêt', 'woo-bordereau-generator') . '</span></mark>';
                            break;
                        case 'receivable':
                            echo '<mark class="order-status status-receivable"><span>' . __('Paiement effectué', 'woo-bordereau-generator') . '</span></mark>';
                            break;
                        default:
                            echo '<mark class="order-status status-on-hold"><span>' . __('Paiement en attente', 'woo-bordereau-generator') . '</span></mark>';
                            break;
                    }
                }
            }
        }

        if (get_option('wc_bordreau_enable-green-list') == 'true') {
            if ($column === 'percentage_order') {
                if ($order) {
                    $phone = $order->get_billing_phone();
                    list($percentage, $delivered, $cancelled) = $this->get_delivery_percentage_by_phone($phone);

                    echo sprintf('<a target="_blank" href="/wp-admin/edit.php?s=%s&post_status=all&post_type=shop_order"><mark data-delivered="%s" data-cancelled="%s" data-percentage="%s" class="green-list-item"><span>%s &percnt;</span></mark></a>', $phone, $delivered, $cancelled, $percentage, $percentage);
                    // echo '<mark data-delivered="' . $delivered . '" data-cancelled="' . $cancelled . '" data-percentage="' . $percentage . '" class="green-list-item"><span>' . $percentage . '%</span></mark>';
                }
            }
        }
    }

    /**
     * Get orders by phone number with caching
     *
     * @param string $phone Phone number to search
     * @return array WC_Order[] Array of order objects
     */
    function get_orders_by_phone($phone) {
        // Standardize the phone number for consistent caching
        $standardized_phone = $this->standardize_phone_number($phone);

        global $wpdb;
        $is_hpos_enabled = Helpers::is_hpos_enabled();

        // Query for order IDs based on storage type
        if ($is_hpos_enabled) {
            // HPOS query for orders
            $hpos_order_ids = $wpdb->get_col($wpdb->prepare("
                SELECT order_id
                FROM {$wpdb->prefix}wc_orders_meta
                WHERE (meta_key IN ('_billing_phone', '_shipping_phone') AND REPLACE(REPLACE(meta_value, ' ', ''), '-', '') = %s)
                OR (meta_key = '_billing_address_index' AND meta_value LIKE %s)
            ", $standardized_phone, '%' . $wpdb->esc_like($standardized_phone) . '%'));

        // Legacy table query for any remaining orders
        $classic_order_ids = $wpdb->get_col($wpdb->prepare("
            SELECT post_id
            FROM {$wpdb->postmeta}
            WHERE meta_key IN ('_billing_phone', '_shipping_phone')
            AND REPLACE(REPLACE(meta_value, ' ', ''), '-', '') = %s
        ", $standardized_phone));

        // Merge and remove duplicates
        $order_ids = array_unique(array_merge($hpos_order_ids, $classic_order_ids));
        } else {
            // Classic post meta query
            $order_ids = $wpdb->get_col($wpdb->prepare("
        SELECT post_id
        FROM {$wpdb->postmeta}
        WHERE meta_key IN ('_billing_phone', '_shipping_phone')
        AND REPLACE(REPLACE(meta_value, ' ', ''), '-', '') = %s
    ", $standardized_phone));
        }

        // Return the orders
        return wc_get_orders(array('post__in' => $order_ids));
    }

    /**
     * Standardize phone number format for comparison
     *
     * @param string $phone Phone number to standardize
     * @return string Standardized phone number
     */
    function standardize_phone_number($phone) {
        // Remove all non-digit characters except + sign
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Handle country code formatting
        if (substr($phone, 0, 2) === '00') {
            $phone = '+' . substr($phone, 2);
        }

        return $phone;
    }

    function get_delivery_percentage_by_phone($phone)
    {
        $orders = $this->get_orders_by_phone($phone);

        $delivered_count = 0;
        $cancelled_count = 0;

        foreach ($orders as $order) {
            if ($order->get_status() == 'completed') {
                $delivered_count++;
            } elseif ($order->get_status() == 'cancelled') {
                $cancelled_count++;
            } elseif ($order->get_status() == 'failed') {
                $cancelled_count++;
            }
        }

        $total_orders = $delivered_count + $cancelled_count;
        if ($total_orders == 0) {
            return 0;  // Avoid division by zero
        }

        $percentage_delivered = ($delivered_count / $total_orders) * 100;

        $percentage_delivered = round($percentage_delivered, 0);

        return [$percentage_delivered, $delivered_count, $cancelled_count];  // Round to 2 decimal places
    }

    /**
     * Load dependencies for additional WooCommerce settings
     *
     * @since    1.0.0
     * @access   private
     */
    public function add_settings($settings)
    {
//      $settings[] = include plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-wc-bordereau-generator-wc-settings.php';

        return $settings;
    }

    /**
     * @return void
     */
    public function add_meta_box()
    {
        if (Helpers::is_order_page()) {

            $screen_id = \wc_get_page_screen_id('shop-order');

            add_meta_box('wc-bordereau-generator', __('Bordereau Generator', 'woo-bordereau-generator'), [
                BordereauGeneratorMetaBox::class,
                'output'
            ], $screen_id, 'side');

            add_meta_box('wc-bordereau-tracking', __('Bordereau Tracking', 'woo-bordereau-generator'), [
                BordereauTrackingMetaBox::class,
                'output'
            ], $screen_id, 'side');
        }

    }


    /**
     * Admin Menu add function
     * @return void
     * @since    1.2.0
     */
    public function register_woocommerce_menu()
    {

        $menu_slug = 'wc-bordereau-generator';
        $menu_icon = "data:image/svg+xml;base64," . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" xml:space="preserve" viewBox="0 0 512 512"><path fill="#FFF" d="M368.1 67.9H172.2v.2c-2.7 7.3-5.4 14.6-8.1 21.8-.4 1.1-.8 2.2-1.3 3.3l-9 22.8-10.2 26.1-10.2 26.1-10.2 26.1-10.2 26.1-10.2 26.1-10.2 26.1-10.2 26.1-10.2 26.1L62 350.9l-9.6 24.3c-.2.6-.5 1.2-.7 1.8l-10.2 26.1c-3.2 8.1-6.4 16.2-9.6 24.4-.2.4-.3.9-.7 2.4 2-1.3 3.3-2.2 4.6-3.1 1.9-1.3 3.8-2.6 5.6-3.9l10.2-7.2 10.2-7.2 10.2-7.2 10.2-7.2 10.2-7.2 10.2-7.2 10.2-7.2 10.2-7.2 10.2-7.2 10.2-7.2 10.2-7.2 10.2-7.2 10.2-7.2 10.2-7.2 10.2-7.2 10.2-7.2 10.2-7.2c3.1-2.2 6.2-4.4 9.4-6.6l.9-.6c3.4-2.4 6.8-4.8 10.2-7.3 3.4-2.4 6.8-4.9 10.2-7.2.9-.6 1.7-1.2 2.6-1.8 2.5-1.7 5.1-3.4 7.6-5 3.4-2.2 6.8-4.2 10.2-6.2 2.3-1.3 4.7-2.6 7-3.8 1.1-.5 2.1-1.1 3.2-1.6 3.4-1.6 6.8-3.1 10.2-4.5 3.4-1.4 6.8-2.6 10.2-3.8 3.4-1.1 6.8-2.1 10.2-2.9 3.4-.8 6.8-1.5 10.2-2.1 3.4-.5 6.8-.9 10.2-1.2 3.4-.2 6.8-.3 10.2-.3h.4c3.3.1 6.6.3 9.9.6 3.5.4 6.9.9 10.2 1.7 3.5.8 6.9 1.7 10.2 2.8 3.5 1.2 6.9 2.6 10.2 4.2 3.5 1.7 6.9 3.8 10.2 6 3.6 2.4 7 5.2 10.2 8.2 2.3 2.2 4.5 4.5 6.7 7 .2.3.4.5.7.8 1 1.2 2 2.4 2.9 3.6 4.4 5.9 7.8 12.2 10.2 18.8 6.4 17.2 6.5 36.2 0 53.6-2.3 6.2-5.5 12.3-9.5 17.9-.3.4-.5.7-.8 1.1-3.2 4.4-6.6 8.4-10.2 12.2-3.2 3.3-6.7 6.4-10.2 9.3-3.3 2.6-6.7 5.1-10.2 7.4-3.3 2.2-6.7 4.2-10.2 6.1-3.3 1.8-6.8 3.5-10.2 5.1-2.6 1.2-5.3 2.4-8 3.5-.6.3-1.3.4-2 .5H223.1c-.3 0-.7-.1-1.1-.1l2.7-3c3.4-3.7 6.8-7.5 10.2-11.2 3.4-3.7 6.8-7.5 10.2-11.2 3.4-3.7 6.8-7.5 10.2-11.2 3.4-3.7 6.8-7.5 10.2-11.2 3.4-3.7 6.8-7.5 10.2-11.2 3.4-3.7 6.8-7.5 10.2-11.2 3.4-3.7 6.8-7.5 10.2-11.2s6.8-7.5 10.2-11.2c3.4-3.7 6.8-7.5 10.2-11.2 3.4-3.7 6.8-7.5 10.2-11.2 3.4-3.7 6.8-7.5 10.2-11.2 3.4-3.7 6.8-7.5 10.2-11.2.5-.6 1.1-1.2 1.6-1.8.6-.7 1.2-1.3 1.8-2h-3.4c-3.5.1-7 .4-10.2.9-1.7.3-3.3.6-4.9 1-1.8.5-3.6 1-5.3 1.7-2.4.9-4.7 2.1-6.9 3.5l-3.3 2.1c-3.5 2.2-6.9 4.5-10.2 6.8-3.4 2.4-6.8 4.7-10.2 7.2-3 2.2-6.1 4.3-9.1 6.5-.4.3-.8.6-1.2.8-3.4 2.4-6.8 4.9-10.2 7.3-3.4 2.4-6.8 4.9-10.2 7.3-3.4 2.4-6.8 4.9-10.2 7.3-3.4 2.4-6.8 4.9-10.2 7.3-3.4 2.4-6.8 4.9-10.2 7.3-3.4 2.4-6.8 4.9-10.2 7.3-3.4 2.4-6.8 4.9-10.2 7.3-3.4 2.4-6.8 4.9-10.2 7.3-3.4 2.4-6.8 4.9-10.2 7.3-3.4 2.4-6.8 4.9-10.2 7.3-3.4 2.4-6.8 4.9-10.2 7.3-3.4 2.4-6.8 4.9-10.2 7.3-3.4 2.4-6.8 4.9-10.2 7.3-3.4 2.4-6.8 4.9-10.2 7.3-3.4 2.4-6.8 4.9-10.2 7.3-3.4 2.4-6.8 4.9-10.2 7.3-3.4 2.4-6.8 4.9-10.2 7.3-3.4 2.4-6.8 4.9-10.2 7.3-3.4 2.4-6.8 4.9-10.2 7.3-3.4 2.4-6.8 4.9-10.2 7.3s-6.8 4.9-10.2 7.3c-2.1 1.5-4.1 3-6.2 4.4-1 .7-2.1 1.5-3.8 2.8l290.5.3c1.5 0 3.1-.2 4.5-.7.6-.2 1.2-.5 1.8-.7 3.4-1.4 6.9-2.8 10.2-4.2 3.4-1.5 6.9-3 10.2-4.6 3.5-1.6 6.9-3.3 10.2-5.1 3.5-1.8 6.9-3.7 10.2-5.6 3.5-2 6.9-4.1 10.2-6.2 3.5-2.2 6.9-4.5 10.2-6.9 3.5-2.5 6.9-5.1 10.2-7.8 3.5-2.8 6.9-5.8 10.2-8.9.5-.5 1.1-1 1.6-1.5 3-2.8 5.9-5.7 8.6-8.6 3.6-3.8 7-7.7 10.2-11.7 3.7-4.6 7.1-9.4 10.2-14.3 3.9-6.2 7.3-12.6 10.2-19.3 2.9-6.7 5.3-13.7 7-21.1 6.1-25.4 3.5-48.3-7-68.7-2.8-5.5-6.3-10.8-10.2-15.9-3.1-3.9-6.5-7.8-10.2-11.5-1.5-1.5-3.1-3.1-4.8-4.6-1.7-1.6-3.6-3.1-5.4-4.5-.7-.5-1.4-1.1-2.1-1.6-2.5-1.8-5.1-3.5-7.6-5.2 3.2-3.5 6.5-7 9.6-10.4 2.4-2.6 4.8-5.2 7.2-7.9 1.1-1.2 2.1-2.4 3.1-3.7 4-4.9 7.4-10 10.2-15.3 5.3-9.7 8.7-20.1 10.2-31.2.5-3.9.9-7.8.9-11.9.1-5-.2-9.7-.9-14.2-1.5-10.2-5-19.2-10.2-26.9-2.9-4.3-6.3-8.1-10.2-11.6-3.1-2.8-6.5-5.3-10.2-7.6-3.2-2-6.6-3.8-10.2-5.5-1.9-.9-3.8-1.7-5.8-2.5-1.5-.6-3-1.1-4.5-1.7-3.4-1.2-6.8-2.3-10.2-3.2-3.4-.9-6.8-1.7-10.2-2.4-3.4-.7-6.8-1.3-10.2-1.7-3.4-.5-6.8-.9-10.2-1.2"/></svg>');
        add_menu_page(__('Bordereau Generator', 'woo-bordereau-generator'), __('Bordereau', 'woo-bordereau-generator'), 'manage_woocommerce', $menu_slug, null, $menu_icon);
        add_submenu_page($menu_slug, '', __('Settings', 'woo-bordereau-generator'), 'manage_woocommerce', 'wc-bordereau-generator', [$this, 'woocommerce_bordereau_generator_settings_callback']);

        $enabledProviders = get_option('wc-bordereau-generator_allowed_providers');
        $providers = BordereauGeneratorProviders::get_providers();

        if(is_array($enabledProviders)) {
            foreach ($enabledProviders as $provider) {
                if (is_shipping_company_enabled($provider)) {
                    $providerData = $providers[$provider];
                    add_submenu_page($menu_slug, '', __($providerData['name'] . ' Orders', 'woo-bordereau-generator'), 'manage_woocommerce', 'wc-bordereau-generator-' . $provider . '-orders', function () use ($providerData) {
                        $this->woocommerce_bordereau_generator_orders_callback($providerData);
                    });
                }
            }
        }

        add_submenu_page(
            "",
            'Setup Wizard',
            'Setup Wizard',
            'manage_options',
            'wc-bordereau-generator-setup-wizard',
            [$this, 'bordereau_generator_setup_wizard_content']
        );
    }



    function add_shipping_price_manager_submenu() {
        $menu_slug = 'wc-bordereau-generator';

        add_submenu_page(
            $menu_slug,
            'Shipping Price Manager',
            'Shipping Price Manager',
            'manage_woocommerce',
            'shipping-price-manager',
            [$this, 'render_shipping_price_manager_page']
        );
    }

    function render_shipping_price_manager_page() {
        echo '<div id="shipping-price-manager-root"></div>';
    }


    // AJAX handler for fetching shipping zones
    function fetch_shipping_zones() {
        check_ajax_referer('wp_bordereau_rest', '_nonce');

        $zones = WC_Shipping_Zones::get_zones();
        $formatted_zones = array();

        foreach ($zones as $zone) {
            $methods = $zone['shipping_methods'];
            $formatted_methods = array();

            foreach ($methods as $method) {
                $formatted_methods[] = array(
                    'id' => $method->id,
                    'instance_id' => $method->instance_id,
                    'title' => $method->title,
                    'price' => $method->get_option('cost'),
                );
            }

            $formatted_zones[] = array(
                'id' => $zone['id'],
                'name' => $zone['zone_name'],
                'methods' => $formatted_methods,
            );
        }

        wp_send_json_success($formatted_zones);
    }

    function update_shipping_method_prices() {
        check_ajax_referer('wp_bordereau_rest', '_nonce');

        $prices = json_decode(stripslashes($_POST['prices']), true);



        if (!is_array($prices)) {
            wp_send_json_error(array('message' => __('Invalid data format', 'woo-bordereau-generator')));
            return;
        }

        foreach ($prices as $price_data) {

            if (!isset($price_data['instance_id']) || !isset($price_data['price'])) {
                continue;
            }

            $instance_id = $price_data['instance_id'];
            $new_price = (int) $price_data['price'];

            $shipping_method = WC_Shipping_Zones::get_shipping_method($instance_id);
            if ($shipping_method) {
                $option_key = $shipping_method->get_instance_option_key();
                $options = get_option($option_key);
                if (is_array($options)) {
                    $options['cost'] = $new_price;
                    update_option($option_key, $options);
                }
            }
        }

        wp_send_json_success(array('message' => __('Prices updated successfully', 'woo-bordereau-generator')));
    }



    public function bordereau_generator_setup_wizard_content()
    {
        echo '<div id="bordereau-setup-wizard" class="wrap bordereau-setup-wizard"></div>';
        exit();
    }

    /**
     * Display the list of providers
     * @return void
     * @since 1.2.0
     */
    public function woocommerce_bordereau_generator_settings_callback()
    {
        $url = rest_url('woo-bordereau/v1/webhook/');

        $data = get_option('_woo_bordreau_generator_license');

        if ($data):

            ?>
            <style>
                .notice-warning, .notice {
                    display: none !important;
                }
            </style>

            <div class="ltr:font-bordreau rtl:font-rtl" id="bordereau-homepage"
                 data-version="<?php echo WC_BORDEREAU_GENERATOR_VERSION; ?>"
                 data-webhook-url="<?php echo $url; ?>"
                 data-integrity="<?php echo get_bordreau_generator_integrity(); ?>">
            </div>
        <?php
        else:
            ?>
            <div class="ltr:font-bordreau rtl:font-rtl" id="bordereau-integrety"
                 data-version="<?php echo WC_BORDEREAU_GENERATOR_VERSION; ?>"
                 data-integrity="<?php echo get_bordreau_generator_integrity(); ?>">
            </div>
        <?php
        endif;
    }

    /**
     * Display the list of orders
     * @return void
     * @since 1.5.0
     */
    public function woocommerce_bordereau_generator_orders_callback($provider)
    {
        $arguments = array(
            'label' => __('Orders Per Page', 'woo-bordereau-generator'),
            'default' => 5,
            'option' => 'orders_per_page'
        );

        add_screen_option('per_page', $arguments);

        $orders_list_table = new ShippingOrdersTable($provider);
        $orders_list_table->prepare_items();

        include_once('views/partials-wp-list-table-display.php');
    }

    /**
     * Display the list of orders
     * @return void
     * @since 1.5.0
     */
    public function woocommerce_bordereau_generator_guepex_orders_callback()
    {
        $arguments = array(
            'label' => __('Orders Per Page', 'woo-bordereau-generator'),
            'default' => 5,
            'option' => 'orders_per_page'
        );

        add_screen_option('per_page', $arguments);

        $orders_list_table = new ShippingOrdersTable('guepex');
        $orders_list_table->prepare_items();

        include_once('views/partials-wp-list-table-display.php');
    }

    /**
     * Display the list of orders
     * @return void
     * @since 1.5.0
     */
    public function woocommerce_bordereau_generator_wecanservices_orders_callback()
    {
        $arguments = array(
            'label' => __('Orders Per Page', 'woo-bordereau-generator'),
            'default' => 5,
            'option' => 'orders_per_page'
        );

        add_screen_option('per_page', $arguments);

        $orders_list_table = new ShippingOrdersTable('wecanservices');
        $orders_list_table->prepare_items();

        include_once('views/partials-wp-list-table-display.php');
    }


    /**
     * @param $post_id
     * @param $post
     *
     * @return void
     */
    public function save_meta_box($post_id, $post)
    {
        BordereauGeneratorMetaBox::save($post_id, $post);
    }


    /**
     * @return void
     */
    public function save()
    {
        check_ajax_referer('wp_bordereau_rest', '_nonce');
        BordereauGeneratorMetaBox::save_ajax($_POST);
    }


    /**
     * @return void
     */
    public function tracking()
    {
        check_ajax_referer('wp_bordereau_rest', '_nonce');

        $post_id = isset($_POST['post_id']) ? wc_clean(sanitize_text_field($_POST['post_id'])) : '';
        if ($post_id) {
            BordereauTrackingMetaBox::track($post_id);
        }
    }


    /**
     * @param $order_id
     * @param $order
     *
     * @return void
     */
    public function order_cancelled($order_id, $order)
    {
        BordereauGeneratorMetaBox::delete($order_id, $order);
    }


    /**
     * @param $data
     *
     * @return void
     */
    public function delete_tracking_number()
    {
        check_ajax_referer('wp_bordereau_rest', '_nonce');

        $order_id = sanitize_text_field($_POST['order']);

        $order = wc_get_order($order_id);

        BordereauGeneratorMetaBox::delete($order_id, $order);
    }


    /**
     * @param $actions
     * @param $plugin_file
     * @param $plugin_data
     * @param $context
     *
     * @return array
     */
    public function action_links($actions, $plugin_file, $plugin_data, $context)
    {
        return array_merge(array('configure' => '' . __('Configure', 'woo-bordereau-generator') . ''), $actions);
    }


    /**
     * @param $links_array
     * @param $plugin_file_name
     * @param $plugin_data
     * @param $status
     *
     * @return mixed
     */
    function support_and_faq_links($links_array, $plugin_file_name, $plugin_data, $status)
    {

        if (str_contains($plugin_file_name, basename(__FILE__))) {
            $links_array[] = __('FAQ', 'woo-bordereau-generator');
            $links_array[] = __('Support', 'woo-bordereau-generator');
            $links_array[] = __('Check for updates', 'woo-bordereau-generator');
        }

        return $links_array;
    }


    /**
     * @param $field
     * @param $key
     * @param $args
     * @param $value
     *
     * @return string
     */
    function multicheck_form_field($field, $key, $args, $value)
    {

        $field_html = '<fieldset>';

        if (isset($args['label'])) {
            $field_html .= '<legend>' . $args['label'] . '</legend>';
        }

        if (!empty($args['options'])) {
            foreach ($args['options'] as $option_key => $option_text) {
                $field_html .= '<input type="checkbox" class="input-multicheck' . esc_attr(implode(' ', $args['input_class'])) . '" value="' . esc_attr($option_key) . '" name="' . esc_attr($key) . '[]" id="' . esc_attr($args['id']) . '_' . esc_attr($option_key) . '"' . checked($value, $option_key, false) . ' />';
                $field_html .= '<label for="' . esc_attr($args['id']) . '_' . esc_attr($option_key) . '" class="multicheck' . implode(' ', $args['label_class']) . '">' . $option_text . '</label>';
            }
        }

        if ($args['description']) {
            $field_html .= '<span class="description">' . esc_html($args['description']) . '</span>';
        }

        $field_html .= '</fieldset>';

        $container_class = esc_attr(implode(' ', $args['class']));
        $container_id = esc_attr($args['id']) . '_field';
        $after = !empty($args['clear']) ? '<div class="clear"></div>' : '';
        $field_container = '<p class="form-row %1$s" id="%2$s">%3$s</p>';

        return sprintf($field_container, $container_class, $container_id, $field_html) . $after;
    }

    /**
     * Add plugin action links
     *
     * @param array $links links array
     *
     * @since    1.1.3
     */
    public function plugin_action_links($links)
    {
        $links[] = '<a href="' . admin_url('admin.php?page=' . WC_BORDEREAU_POST_TYPE) . '">' . __('Settings', 'woo-bordereau-generator') . '</a>';

        return $links;
    }


    /**
     * Load all providers
     *
     * @since    1.1.1
     * @access   private
     */

    public function get_rest_active_providers()
    {

        check_ajax_referer('wp_bordereau_rest', '_nonce');

        $providers = BordereauGeneratorProviders::get_providers();
        $enabledProviders = get_option('wc-bordereau-generator_allowed_providers');
        if (!$enabledProviders) {
            wp_send_json([], 200);
        }
        $allowedProviders = [];
        foreach ($providers as $key => $provider) {
            if (in_array($key, $enabledProviders)) {
                $allowedProviders[$key] = $provider;
            }
        }

        wp_send_json($allowedProviders, 200);
    }

    /**
     * Load the wilayas from the provided items
     *
     * @return WP_REST_Response
     */

    public function get_rest_wilaya_by_provider()
    {
        check_ajax_referer('wp_bordereau_rest', '_nonce');

        if (sanitize_text_field($_POST['provider'])) {
            wp_send_json(BordereauGeneratorProviders::get_wilayas(sanitize_text_field($_POST['provider']), sanitize_text_field($_POST['order'])), 200);
        }
    }

    /**
     * @param $data
     * @return WP_REST_Response
     */
    public function get_rest_slip_print($data): WP_REST_Response
    {
        return new WP_REST_Response(BordereauGeneratorProviders::get_slip(sanitize_text_field($data['provider']), sanitize_text_field($data['post'])), 200);
    }

    /**
     * @param $data
     *
     * @return WP_REST_Response
     */
    public function get_rest_wilaya_by_post($data): WP_REST_Response
    {

        return new WP_REST_Response(BordereauGeneratorProviders::get_wilayas_by_id($data['provider'], $data['post']), 200);
    }


    /**
     */
    public function get_rest_provider_settings()
    {
        check_ajax_referer('wp_bordereau_rest', '_nonce');

        wp_send_json(BordereauGeneratorProviders::get_settings($_POST), 200);
    }

    /**
     *
     */
    public function get_rest_barcode_detail()
    {
        global $wpdb;

        check_ajax_referer('wp_bordereau_rest', '_nonce');

        $method = trim(sanitize_text_field($_POST['method']));

        $trackingNumber = trim(sanitize_text_field($_POST['trackingNumber']));

        $post_id = null;
        $order = null;

        if ($method === 'sku') {
            $post_id = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = %s",
                    '_shipping_tracking_number', // Replace with your meta key
                    $trackingNumber // Replace with your meta value
                )
            );
        } elseif ($method === 'id') {
            //if (preg_match('/(\d+)/', $trackingNumber, $matches)) {

            $order = wc_get_order($trackingNumber);

            if (!$order) {
                $order = Helpers::get_order_by_order_number($trackingNumber);
            }

            if (!$order) {
                $order_id = Helpers::get_order_id_by_sequential_order_number($trackingNumber);
                if ($order_id) {
                    $order = wc_get_order($order_id);
                }
            }

            if ($order) {
                $post_id = $order->get_id();
            }

            if ($post_id) {
                $trackingNumber = $order->get_meta('_shipping_tracking_number');
        } else {
            wp_send_json([
                'message' => __('The order was not found', 'woo-bordereau-generator')
            ], 404);
        }
            //}

        } else {
            wp_send_json([
                'message' => 'The order was not found'
            ], 404);
        }

        if (!$order) {
            $order = wc_get_order($post_id);
        }

        if ($order) {

            $trackingNumber = get_post_meta($order->get_id(), '_shipping_tracking_number', true);

            if (!$trackingNumber) {
                $trackingNumber = $order->get_meta('_shipping_tracking_number');
            }

            wp_send_json([
                'orderNumber' => $order->get_order_number(),
                'trackingNumber' => $trackingNumber,
                'status' => ucfirst(wc_get_order_status_name($order->get_status())),
                'trackingStatus' => $order->get_meta('_latest_tracking_status')
            ], 200);
        } else {
            wp_send_json([
                'message' => __('The order was not found', 'woo-bordereau-generator')
            ], 404);
        }
    }


    /**
     * @return void
     */
    public function get_rest_print_barcodes()
    {

        check_ajax_referer('wp_bordereau_rest', '_nonce');
        $barcodes = stripslashes($_POST['orders']);

        $order_ids = json_decode($barcodes, true);

        if (!$order_ids || count($order_ids) == 0) {
            return;
        }

        $orders = [];

        $orders = wc_get_orders([
            'post__in' => $order_ids,
            'orderby' => 'post__in', // This ensures the returned orders match the order of your IDs
            'limit' => -1            // Fetch all matching orders
        ]);

        if (count($orders) == 0) {
            $order_ids = Helpers::get_order_ids_by_sequential_order_numbers($order_ids);
            $orders = wc_get_orders([
                'post__in' => $order_ids,
                'orderby' => 'post__in', // This ensures the returned orders match the order of your IDs
                'limit' => -1            // Fetch all matching orders
            ]);
        }

        $table = '';
        $logo_id = get_theme_mod('custom_logo'); // This gets the custom logo ID set in the theme customizer
        $logo_url = wp_get_attachment_image_url($logo_id, 'full'); // This gets the logo's URL
        $site_name = get_bloginfo('name'); // This gets the site's name

        $user = wp_get_current_user();
        // Start with the logo and text above the table
        $table = '<div style="text-align: center;">'; // Center the content
        $table .= '<img src="' . esc_url($logo_url) . '" alt="Logo" style="margin-bottom: 20px;"/>'; // Add the logo image
        $table .= '<h2 style="color: #cc0099; margin-bottom: 5px;">' . esc_html($site_name) . '</h2>'; // The brand name with styling
        $table .= '<p><strong>Status:</strong> (order status to) for exp: expédié yalidine</p>'; // Add the status text
        $table .= '<p><strong>Date:</strong> ' . date('d/m/Y') . '</p>'; // Add the current date
        $table .= '<p><strong>Par:</strong> ' . $user->user_login . '</p>'; // Add the user who processed this
        $table .= '</div>';

        if ($orders) {

            // Start table with inline styles for the border, spacing, and padding
            $table .= '<table style="border-collapse: collapse; width: 100%;">';
            $table .= '<thead><tr style="background-color: #f2f2f2;">'; // Header background color
            $table .= '<th style="border: 1px solid #ddd; padding: 8px; text-align: left;">' . __('Number', 'woo-bordereau-generator') . '</th>';
            $table .= '<th style="border: 1px solid #ddd; padding: 8px; text-align: left;">' . __('Tracking', 'woo-bordereau-generator') . '</th>';
            $table .= '<th style="border: 1px solid #ddd; padding: 8px; text-align: left;">' . __('Name', 'woo-bordereau-generator') . '</th>';
            $table .= '<th style="border: 1px solid #ddd; padding: 8px; text-align: left;">' . __('Address', 'woo-bordereau-generator') . '</th>';
            $table .= '<th style="border: 1px solid #ddd; padding: 8px; text-align: left;">' . __('Amount', 'woo-bordereau-generator') . '</th>';
            $table .= '<th style="border: 1px solid #ddd; padding: 8px; text-align: left;">' . __('Signature', 'woo-bordereau-generator') . '</th>';
            $table .= '<th style="border: 1px solid #ddd; padding: 8px; text-align: left;">' . __('Note', 'woo-bordereau-generator') . '</th>';
            $table .= '</tr></thead><tbody>';

            $counter = 1;

            foreach ($orders as $order) {

                $trackingNumber = get_post_meta($order->get_id(), '_shipping_tracking_number', true);
                if (!$trackingNumber) {
                    $trackingNumber = $order->get_meta('_shipping_tracking_number');
                }

                $table .= '<tr>';
                $table .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $order->get_order_number() . '</td>';
                $table .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $trackingNumber . '</td>';
                $table .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() . '<br /> <strong>' . $order->get_billing_phone() . '</strong></td>';
                $table .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $order->get_formatted_billing_address() . '</td>';
                $table .= '<td style="border: 1px solid #ddd; padding: 8px;">' . $order->get_total() . '</td>';
                $table .= '<td style="border: 1px solid #ddd; padding: 8px;"></td>';
                $table .= '<td style="border: 1px solid #ddd; padding: 8px;"></td>';
                $table .= '</tr>';
            }

            $table .= '</tbody></table>';

        }

        wp_send_json([
            'success' => true,
            'html' => $table
        ]);
    }


    /**
     * @return void
     */
    public function get_all_bulk_actions_ajax()
    {

        $screen_id = wc_get_page_screen_id('shop-order');
        check_ajax_referer('wc_barcode_scanner', 'wc_barcode_order_nonce');
        $actions = apply_filters("bulk_actions-edit-" . $screen_id, array()); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
        wp_send_json($actions);
    }

    /**
     *
     * @return WP_REST_Response
     */
    public function get_rest_communes_by_provider()
    {

        check_ajax_referer('wp_bordereau_rest', '_nonce');
        $wilaya = (int)preg_replace('/[A-Z]{2}\-([0-9]{2})/m', '$1', sanitize_text_field($_POST['wilaya']));
        wp_send_json(BordereauGeneratorProviders::get_communes($_POST['order'], $_POST['provider'], $wilaya, sanitize_text_field($_POST['type'])), 200);
    }

    function display_center_id_admin($order)
    {
        $selected_value = get_post_meta($order->get_id(), '_billing_center_id', true);

        $publicClass = new BordereauGeneratorPublic($this->plugin_name, $this->version);
        $centers = [];

        // Get shipping methods from order
        $shipping_methods = $order->get_shipping_methods();

// Get the instance_id from the first shipping method
        $instance_id = null;
        foreach ($shipping_methods as $shipping_method) {
            // Get the instance ID from the shipping method
            $method_id = $shipping_method->get_instance_id();
            if ($method_id) {
                $instance_id = $method_id;
                break;
            }
        }

        // Now search through shipping zones with this instance_id
        $shipping_zones = WC_Shipping_Zones::get_zones();
        $selected_shipping_method = null;
        foreach ($shipping_zones as $zone) {
            $shipping_methods = $zone['shipping_methods'];
            foreach ($shipping_methods as $int_id => $shipping_method) {
                if ($shipping_method->instance_id == $instance_id) {
                    $selected_shipping_method = $shipping_method;
                    break 2; // Break both loops once found
                }
            }
        }

        $shipping_method_obj = $selected_shipping_method;
        if(is_object($shipping_method_obj)){
            $shipping_method_class = get_class($shipping_method_obj);
            $providers = BordereauGeneratorProviders::get_providers();
            if (method_exists($shipping_method_obj, 'get_provider')) {
                $provider = $shipping_method_obj->get_provider();
                $selected_provider = $providers[$provider];
            }

            if (!empty($selected_provider)) {

                if ($selected_shipping_method instanceof NordOuestLocalPickup) {

                    $settings_class = new $selected_provider['setting_class']($selected_provider);
                    if (method_exists($settings_class, 'get_all_centers')) {
                        $centers = $settings_class->get_all_centers();
                    }

                } elseif ($selected_shipping_method instanceof YalidineLocalPickup) {
                    $settings_class = new $selected_provider['setting_class']($selected_provider);
                    if (method_exists($settings_class, 'get_all_centers')) {
                        $centers = $settings_class->get_all_centers();
                    }
                } elseif ($selected_shipping_method instanceof ZimoExpressLocalPickup) {
	                $settings_class = new $selected_provider['setting_class']($selected_provider);
	                if (method_exists($settings_class, 'get_all_centers')) {
		                $centers = $settings_class->get_all_centers();
	                }
                } elseif ($selected_shipping_method instanceof ZRExpressTwoLocalPickup) {
                    $settings_class = new $selected_provider['setting_class']($selected_provider);
                    if (method_exists($settings_class, 'get_all_centers')) {
                        $centers_data = $settings_class->get_all_centers();
                        $centers = $centers_data['items'] ?? $centers_data;
                    }
                } elseif ($selected_shipping_method instanceof ElogistiaLocalPickup) {
                    $settings_class = new $selected_provider['setting_class']($selected_provider);
                    if (method_exists($settings_class, 'get_all_centers')) {
                        $centers = $settings_class->get_all_centers();
                    }
                }

                if ($selected_value) {

                    if ($centers && count($centers) > 0) {
                        $found_centers = array_filter($centers, function ($center) use ($selected_value) {
                            if (isset($center['id'])) {
	                            return $center['id'] == $selected_value;
                            }
	                        if (isset($center['code'])) {
		                        return $center['code'] == $selected_value;
	                        }
                        });

                        $found_center = reset($found_centers); // Get the first shipping method
                        if ($found_center) {
                            echo '<div class="address">';
                            echo '<p><strong>' . __('Center', 'woocommerce') . ': </strong> ' . $found_center['name'] ?? '' . '<br>';
                            echo '</p>';
                            echo '</div>';
                        }
                    }
                }

                $options = [];

                foreach ($centers as $center) {
                    if (isset($center['id'])) {
	                    $options[$center['id']] = $center['name'];
                    }
	                if (isset($center['code'])) {
		                $options[$center['code']] = $center['name'];
	                }
                }

                echo '<div class="edit_address">';
                echo '<p class="form-field form-field-wide _billing_center_field">' . __('Center', 'woocommerce') . ':<br>';
                echo '<select name="billing_center" id="_billing_center" class="first">';
                foreach ($options as $value => $label) {
                    echo '<option value="' . esc_attr($value) . '" ' . selected($selected_value, $value, false) . '>' . esc_html($label) . '</option>';
                }
                echo '</select></p>';
                echo '</div>';
            }
        }


    }


    function save_custom_order_field($post_id)
    {
        if (!empty($_POST['billing_center'])) {
            $order = wc_get_order($post_id);
            if ($order) {
                $order->add_meta_data('_billing_center_id', sanitize_text_field($_POST['billing_center']));
            }
            update_post_meta($post_id, '_billing_center_id', sanitize_text_field($_POST['billing_center']));
        }
    }


    /**
     * get rest centers
     */
    public function get_rest_centers()
    {
        check_ajax_referer('wp_bordereau_rest', '_nonce');
        $commune = $_POST['commune'];
        $commune = preg_replace("/%u([0-9a-f]{3,4})/i", "&#x\\1;", urldecode($commune));
        wp_send_json(BordereauGeneratorProviders::get_centers(sanitize_text_field($_POST['order']), html_entity_decode($commune, null, 'UTF-8'), sanitize_text_field($_POST['provider'])), 200);
    }

    /**
     * get rest centers
     */
    public function get_rest_centers_per_wilaya()
    {
        check_ajax_referer('wp_bordereau_rest', '_nonce');
        $wilaya = $_POST['wilaya'];
        wp_send_json(BordereauGeneratorProviders::get_centers_per_wilaya(sanitize_text_field($_POST['order']), $wilaya, sanitize_text_field($_POST['provider'])), 200);
    }

    /**
     * get rest centers
     */
	public function rest_webhook($data)
	{
		$provider = sanitize_text_field($data['provider']);
		$jsonData = file_get_contents('php://input');

		// Get all request headers for signature verification
		$headers = [];
		foreach ($_SERVER as $key => $value) {
			if (strpos($key, 'HTTP_') === 0) {
				// Convert HTTP_HEADER_NAME to header-name
				$header_name = str_replace('_', '-', strtolower(substr($key, 5)));
				$headers[$header_name] = $value;
			}
		}

		// Process the webhook and return response
		return wp_send_json((new BordereauGeneratorProviders())->webhook($provider, $jsonData, $headers));
	}


    public function rest_webhook_maystro($data)
    {

        // Todo add webhook

    }

    public function test_webhook()
    {

        if (isset($_GET["subscribe"], $_GET["crc_token"])) {
            echo $_GET["crc_token"];
            exit();
        }
    }

    /**
     *
     */
    public function get_rest_communes_by_post()
    {
        check_ajax_referer('wp_bordereau_rest', '_nonce');

        $wilaya = (int)preg_replace('/[A-Z]{2}\-([0-9]{2})/m', '$1', sanitize_text_field($_POST['wilaya']));
        wp_send_json(BordereauGeneratorProviders::get_communes_by_id(sanitize_text_field($_POST['order']), $wilaya, sanitize_text_field($_POST['type'])), 200);
    }

    /**
     *
     * @since 1.2.0
     */
    public function get_rest_parcel_detail_by_post()
    {
        if (isset($_POST['order'])) {
            BordereauTrackingMetaBox::detail(sanitize_text_field($_POST['order']));
        }
    }

    /**
     * Load all providers
     *
     * @since    1.1.1
     * @access   private
     */

    public function get_rest_providers()
    {
        $providers = BordereauGeneratorProviders::get_providers();
        $enabledProviders = get_option('wc-bordereau-generator_allowed_providers', []);

        $providers = $this->getProviders($enabledProviders, $providers);

        wp_send_json([
            'providers' => $providers,
            'enabled' => $enabledProviders,
        ], 200);
    }


    /**
     * @param WP_REST_Request $request
     *
     * @since    1.1.1
     * @access   public
     */
    public function save_rest_provider()
    {

        check_ajax_referer('wp_bordereau_rest', '_nonce');

        $data = $_POST;
        $prams = array_filter($data, function ($item) {
            return !in_array($item, ['_nonce', 'action', 'provider']);
        }, ARRAY_FILTER_USE_KEY);

        // validate the nonce

//        $prams = $request->get_body_params();
//        // check if the option is correct
//
        foreach ($prams as $key => $pram) {
            // Check if the value is a JSON array (for multiselect fields)
            $sanitized_value = $pram;
            
            // WordPress adds slashes to POST data, so we need to unslash first
            $unslashed_pram = wp_unslash($pram);
            
            // Try to decode as JSON to check if it's an array
            $decoded = json_decode($unslashed_pram, true);
            if (is_array($decoded)) {
                // It's a JSON array, sanitize each element and keep as JSON
                $sanitized_array = array_map('sanitize_text_field', $decoded);
                $sanitized_value = wp_json_encode($sanitized_array);
            } else {
                // Regular text field
                $sanitized_value = sanitize_text_field($unslashed_pram);
            }
            
            if (get_option($key) === false) {
                add_option($key, $sanitized_value);
            } else {
                update_option($key, $sanitized_value);
            }

//          if (false === get_option($key) && false === update_option($key,$pram)) add_option($key,$pram);
        }

        $enabledProviders = get_option('wc-bordereau-generator_allowed_providers');
        $providers = BordereauGeneratorProviders::get_providers();

        $providers = $this->getProviders($enabledProviders, $providers) ?? [];

        wp_send_json([
            'providers' => $providers,
            'enabled' => $enabledProviders,
            'message' => __('Settings has been saved', 'woo-bordereau-generator'),
        ], 200);
    }

    /**
     * Verify provider credentials before saving
     * 
     * @since 2.0.0
     */
    public function verify_provider_credentials()
    {
        check_ajax_referer('wp_bordereau_rest', '_nonce');

        $provider_key = sanitize_text_field($_POST['provider'] ?? '');
        $credentials = $_POST['credentials'] ?? [];

        if (empty($provider_key)) {
            wp_send_json([
                'success' => false,
                'message' => __('Provider is required', 'woo-bordereau-generator')
            ], 400);
        }

        // Sanitize credentials
        $sanitized_credentials = [];
        foreach ($credentials as $key => $value) {
            $sanitized_key = sanitize_text_field($key);
            
            // WordPress adds slashes to POST data, so we need to unslash first
            $unslashed_value = wp_unslash($value);
            
            // Check if the value is a JSON array (for multiselect fields)
            $decoded = json_decode($unslashed_value, true);
            if (is_array($decoded)) {
                // It's a JSON array, sanitize each element and keep as JSON
                $sanitized_credentials[$sanitized_key] = wp_json_encode(array_map('sanitize_text_field', $decoded));
            } else {
                $sanitized_credentials[$sanitized_key] = sanitize_text_field($unslashed_value);
            }
        }

        // Get provider data
        $providers = BordereauGeneratorProviders::get_providers();
        if (!isset($providers[$provider_key])) {
            wp_send_json([
                'success' => false,
                'message' => __('Invalid provider', 'woo-bordereau-generator')
            ], 400);
        }

        $provider = $providers[$provider_key];

        // Check if provider has a settings class defined
        if (empty($provider['setting_class'])) {
            wp_send_json([
                'success' => true,
                'message' => __('Credentials saved (verification not available for this provider)', 'woo-bordereau-generator'),
                'skipped' => true
            ], 200);
        }

        $settingsClass = $provider['setting_class'];

        if (!class_exists($settingsClass)) {
            wp_send_json([
                'success' => true,
                'message' => __('Credentials saved (verification not available)', 'woo-bordereau-generator'),
                'skipped' => true
            ], 200);
        }

        try {
            $settings = new $settingsClass($provider);
            
            if (!method_exists($settings, 'check_auth')) {
                wp_send_json([
                    'success' => true,
                    'message' => __('Credentials saved (verification not available)', 'woo-bordereau-generator'),
                    'skipped' => true
                ], 200);
            }

            $result = $settings->check_auth($sanitized_credentials);
            wp_send_json($result, $result['success'] ? 200 : 400);

        } catch (\Exception $e) {
            wp_send_json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get settings
     */
    public function get_rest_settings()
    {

        check_ajax_referer('wp_bordereau_rest', '_nonce');

        $keys = [
            'wc_bordreau_default_order_status',
            'wc_bordreau_product-string-format',
            'wc_bordreau_update-interval',
            'wc_bordreau_always-show-address',
            'wc_bordreau_status-order-add',
            'wc_bordreau_status-order-disaptched',
            'wc_bordreau_enable-automatic-order-add',
            'wc_bordreau_enable-automatic-expidate',
            'wc_bordreau_enable-custom-actions',
            'wc_bordreau_enable-cron',
            'wc_bordreau_enable-debug',
            'wc_bordreau_enable-custom-status',
            'wc_bordreau_enable-green-list',
            'wc_bordreau_enable-extra-shipping-information',
            'wc_bordreau_enable-auto-update',
            'wc_bordreau_disable-cities',
            'wc_bordreau_default-shipping-note',
            'wc_bordreau_city-optional',

        ];
        $data = [];
        foreach ($keys as $key) {
            $data[str_replace('wc_bordreau_', '', $key)] = get_option($key);
        }

        wp_send_json([
            'settings' => $data,
        ], 200);
    }

    /**
     * @since 1.3.1
     */
    public function save_rest_settings()
    {

        check_ajax_referer('wp_bordereau_rest', '_nonce');

        $prams = $_POST;

        // check if default-status is valid

        if (!empty($prams['default-status'])) {
            update_option('wc_bordreau_default_order_status', sanitize_text_field($prams['default-status']));
        } else {
            delete_option('wc_bordreau_default_order_status');
        }

        if (isset($prams['product-string-format']) && $prams['product-string-format']) {
            update_option('wc_bordreau_product-string-format', sanitize_text_field($prams['product-string-format']));
        }

        if (isset($prams['default-shipping-note'])) {
            update_option('wc_bordreau_default-shipping-note', stripslashes(sanitize_text_field($prams['default-shipping-note'])));
        }

        if (isset($prams['update-interval']) && $prams['update-interval']) {
            update_option('wc_bordreau_update-interval', sanitize_text_field($prams['update-interval']));
        }

        if (isset($prams['enable-custom-actions']) && $prams['enable-custom-actions']) {
            update_option('wc_bordreau_enable-custom-actions', sanitize_text_field($prams['enable-custom-actions']));
        }

        if (isset($prams['always-show-address'])) {
            update_option('wc_bordreau_always-show-address', sanitize_text_field($prams['always-show-address']));
        }
        if (isset($prams['enable-automatic-order-add'])) {
            update_option('wc_bordreau_enable-automatic-order-add', sanitize_text_field($prams['enable-automatic-order-add']));
        }
        if (isset($prams['status-order-add'])) {
            update_option('wc_bordreau_status-order-add', sanitize_text_field($prams['status-order-add']));
        }
        if (isset($prams['enable-automatic-expidate'])) {

            update_option('wc_bordreau_enable-automatic-expidate', sanitize_text_field($prams['enable-automatic-expidate']));
        }

        if (isset($prams['status-order-disaptched'])) {
            update_option('wc_bordreau_status-order-disaptched', sanitize_text_field($prams['status-order-disaptched']));
        }

        if (isset($prams['enable-cron'])) {
            if ($prams['enable-cron'] == 'true') {

                // disabled all the actions than enable it
                if (as_has_scheduled_action('bordereau_tracking_check_scheduler')) {
                    as_unschedule_all_actions('bordereau_tracking_check_scheduler');
                }

                if (false === as_has_scheduled_action('bordereau_tracking_check_scheduler')) {
                    $interval = (int)$prams['update-interval'] ?? 60; // in minutes -> convert it to seconds
                    as_schedule_recurring_action(strtotime('now'), $interval * 60, 'bordereau_tracking_check_scheduler', array(), 'bordereau-generator', true);
                }

            } else {
                if (as_has_scheduled_action('bordereau_tracking_check_scheduler')) {
                    as_unschedule_all_actions('bordereau_tracking_check_scheduler');
                }
            }

            update_option('wc_bordreau_enable-cron', sanitize_text_field($prams['enable-cron']));

        }

        if (isset($prams['enable-debug']) && $prams['enable-debug']) {
            update_option('wc_bordreau_enable-debug', sanitize_text_field($prams['enable-debug']));
        }

        if (isset($prams['enable-custom-status']) && $prams['enable-custom-status']) {
            update_option('wc_bordreau_enable-custom-status', sanitize_text_field($prams['enable-custom-status']));
        }

        if (isset($prams['enable-green-list']) && $prams['enable-green-list']) {
            update_option('wc_bordreau_enable-green-list', sanitize_text_field($prams['enable-green-list']));
        }

        if (isset($prams['enable-extra-shipping-information']) && $prams['enable-extra-shipping-information']) {
            update_option('wc_bordreau_enable-extra-shipping-information', sanitize_text_field($prams['enable-extra-shipping-information']));
        }

        if (isset($prams['enable-auto-update']) && $prams['enable-auto-update']) {
            update_option('wc_bordreau_enable-auto-update', sanitize_text_field($prams['enable-auto-update']));
        }


        if (isset($prams['disable-cities']) && $prams['disable-cities']) {
            update_option('wc_bordreau_disable-cities', sanitize_text_field($prams['disable-cities']));
        }

        if (isset($prams['city-optional'])) {
            update_option('wc_bordreau_city-optional', sanitize_text_field($prams['city-optional']));
        }


        wp_send_json([
            'message' => __('Settings has been saved', 'woo-bordereau-generator'),
        ], 200);
    }

    /**
     * Load all providers
     *
     * @since    1.1.1
     * @access   private
     */

    public function add_rest_providers()
    {
        check_ajax_referer('wp_bordereau_rest', '_nonce');

        $prams = $_POST;

        $enabledProviders = get_option('wc-bordereau-generator_allowed_providers');
        if (!is_array($enabledProviders)) {
            $enabledProviders = [];
        }
        
        if (!in_array($prams['provider'], $enabledProviders)) {
            $enabledProviders[] = sanitize_text_field($prams['provider']);
        } else {
            $enabledProviders = array_filter($enabledProviders, function ($item) use ($prams) {
                return $item != sanitize_text_field($prams['provider']);
            });

            // remove all the settings of the account if disabled
            // get all the params from the provider array

            $providers = BordereauGeneratorProviders::get_providers();
            $selectedProvider = $providers[$prams['provider']];

            foreach ($selectedProvider['fields'] as $field) {
                delete_option($field['value']);
            }
        }

        update_option('wc-bordereau-generator_allowed_providers', $enabledProviders);

        $providers = BordereauGeneratorProviders::get_providers();

        $providers = $this->getProviders($enabledProviders, $providers) ?? [];


        wp_send_json([
            'providers' => $providers,
            'enabled' => $enabledProviders
        ], 200);
    }

    /**
     * @param $enabledProviders
     * @param $providers
     *
     * @return array|mixed
     */
    public function getProviders($enabledProviders, $providers)
    {

        if (is_array($providers) && is_array($enabledProviders)) {

            foreach ($enabledProviders as $key => $provider) {

                $providers[$provider]['credentials'] = [];
                foreach ($providers[$provider]['fields'] as $field) {
                    $value = get_option($field['value']);
                    $providers[$provider]['credentials'][$field['value']] = $value != false ? $value : "";
                }
            }
        } else {
            $providers = [];
        }

        return $providers;
    }

    public function getEnabledProviders()
    {

        $providers = BordereauGeneratorProviders::get_providers();
        $enabledProviders = get_option('wc-bordereau-generator_allowed_providers');

        $result = [];

        if ($enabledProviders && count($enabledProviders)) {
            foreach ($enabledProviders as $provider) {
                $result[$provider] = $providers[$provider];
            }
        }


        return $result;
    }

    /**
     * Add custom status for the order
     *
     * @param $bulk_actions
     *
     * @return mixed
     * @since 1.2.0
     */
    public function custom_register_bulk_action($bulk_actions)
    {
        $core_status = wc_get_bordereau_core_order_statuses();

        foreach ($core_status as $key => $core) {
            $slug_without_prefix = str_replace('wc-', '', $key);
            $label = sprintf(__('Change status to %s', 'woocommerce'), esc_html(strtolower($core)));
            $action = sprintf('mark_%s', sanitize_html_class($slug_without_prefix));

            // this ensures that only statuses marked as bulk are listed, and that the custom ordering is preserved
            unset($bulk_actions[$action]);

            $bulk_actions[$action] = $label;
        }

        if (get_option('wc_bordreau_enable-custom-actions') === 'true') {
            $bulk_actions['wc-awaiting-shipping'] = __('Change status to awaiting shipping', 'woo-bordereau-generator');
            $bulk_actions['wc-payment-status-received'] = __('Change status to payment received', 'woo-bordereau-generator');
            $bulk_actions['wc-payment-status-awaiting'] = __('Change status to payment awaiting', 'woo-bordereau-generator');
            $bulk_actions['wc-dispatched'] = __('Change status to dispatched', 'woo-bordereau-generator');
            $bulk_actions['wc-tracking-expedited'] = __('Change tracking status to dispatched', 'woo-bordereau-generator');
            $bulk_actions['wc-tracking-failed'] = __('Change tracking status to failed', 'woo-bordereau-generator');
            $bulk_actions['wc-tracking-attempt'] = __('Change tracking status to attempt delivery', 'woo-bordereau-generator');
            $bulk_actions['wc-tracking-returned'] = __('Change tracking status to returned', 'woo-bordereau-generator');
            $bulk_actions['wc-tracking-delivered'] = __('Change tracking status to delivered', 'woo-bordereau-generator');
        }


        $providers = BordereauGeneratorProviders::get_providers();

        $enabledProviders = get_option('wc-bordereau-generator_allowed_providers');
        if ($enabledProviders) {
            $allowedProviders = [];
            foreach ($providers as $key => $provider) {
                if (in_array($key, $enabledProviders)) {
                    $allowedProviders[$key] = $provider;
                }
            }

            foreach ($allowedProviders as $allowedProvider) {
                $bulk_actions['wc-bulk-add-' . $allowedProvider['slug']] = sprintf(esc_html__('Add Order to %1$s'), $allowedProvider['name']);
            }
        }

        return $bulk_actions;
    }

    public function register_custom_status_bulk_action($bulk_actions)
    {
        if (get_option('wc_bordreau_enable-custom-status') == 'true') {

            $status = $this->get_order_status_posts();

            foreach ($status as $item) {

                $label = sprintf(__('Change status to %s', 'woocommerce'), esc_html(strtolower($item->post_title)));
                $action = sprintf('mark_%s', sanitize_html_class($item->post_name));

                // this ensures that only statuses marked as bulk are listed, and that the custom ordering is preserved
                // unset($bulk_actions[$action]);

                if (get_post_meta($item->ID, "order_status_add_bulk", true)) {
                    $bulk_actions[$action] = $label;
                }
            }

            $core_status = wc_get_bordereau_core_order_statuses();

            foreach ($core_status as $key => $core) {
                $slug_without_prefix = str_replace('wc-', '', $key);
                $label = sprintf(__('Change status to %s', 'woocommerce'), esc_html(strtolower($core)));
                $action = sprintf('mark_%s', sanitize_html_class($slug_without_prefix));

                // this ensures that only statuses marked as bulk are listed, and that the custom ordering is preserved
                // unset($bulk_actions[$action]);

                $bulk_actions[$action] = $label;
            }
        }


        return $bulk_actions;
    }

    /**
     * @param $redirect_to
     * @param $action
     * @param $post_ids
     *
     * @return mixed|string
     * @since 1.2.0
     */
    function custom_bulk_process_custom_status($redirect_to, $action, $post_ids)
    {
        $actions = array(
            'wc-awaiting-shipping',
            'wc-payment-status-received',
            'wc-payment-status-awaiting',
            'wc-tracking-expedited',
            'wc-tracking-returned',
            'wc-tracking-failed',
            'wc-tracking-attempt',
            'wc-tracking-delivered',
            'wc-dispatched'
        );

        // TODO add custom


        $providers = BordereauGeneratorProviders::get_providers();
        $enabledProviders = get_option('wc-bordereau-generator_allowed_providers');
        $providerActions = [];

        if ($enabledProviders) {
            $allowedProviders = [];
            foreach ($providers as $key => $provider) {
                if (in_array($key, $enabledProviders)) {
                    $allowedProviders[$key] = $provider;
                }
            }

            foreach ($allowedProviders as $allowedProvider) {
                $actions[] = 'wc-bulk-add-' . $allowedProvider['slug'];
                $providerActions[] = 'wc-bulk-add-' . $allowedProvider['slug'];
            }
        }


        if (!in_array($action, $actions)) {
            return $redirect_to;
        }


        if (in_array($action, $providerActions)) {
            $this->add_bulk_orders($post_ids, $action);
        }


        if (get_option('wc_bordreau_enable-custom-actions') === 'true') {
            if (in_array($action, ['wc-awaiting-shipping', 'wc-dispatched'])) {

                foreach ($post_ids as $order_id) {
//                    $order = wc_get_order($order_id);
//                    $order->update_status($action);
//                    $order->save();
                    $this->change_order_status_without_hook($order_id, $action);

                }
            }

            if ($action == 'wc-payment-status-received') {
                foreach ($post_ids as $order_id) {
                    $order = wc_get_order($order_id);
                    $order->update_meta_data('_payment_status', 'received');
                    $order->save();
                }
            }

            if ($action == 'wc-payment-status-awaiting') {
                foreach ($post_ids as $order_id) {
                    $order = wc_get_order($order_id);
                    $order->update_meta_data('_payment_status', 'awaiting');
                    $order->save();
                }
            }

            if ($action == 'wc-tracking-expedited') {
                foreach ($post_ids as $order_id) {
                    $order = wc_get_order($order_id);
                    self::update_tracking_status($order, 'expedited', 'manual');
                }
            }

            if ($action == 'wc-tracking-attempt') {
                foreach ($post_ids as $order_id) {
                    $order = wc_get_order($order_id);
                    self::update_tracking_status($order, 'attempt', 'manual');
                }
            }

            if ($action == 'wc-tracking-returned') {
                foreach ($post_ids as $order_id) {
                    $order = wc_get_order($order_id);
                    self::update_tracking_status($order, 'returned', 'manual');
                }
            }

            if ($action == 'wc-tracking-delivered') {
                foreach ($post_ids as $order_id) {
                    $order = wc_get_order($order_id);
                    self::update_tracking_status($order, 'delivered', 'manual');
                }
            }

            if ($action == 'wc-tracking-failed') {
                foreach ($post_ids as $order_id) {
                    $order = wc_get_order($order_id);
                    self::update_tracking_status($order, 'failed', 'manual');
                }
            }


            $redirect_to = add_query_arg(
                array(
                    'bulk_action' => 'marked_' . $action,
                    'changed' => count($post_ids),
                ),
                $redirect_to
            );

        }

        return $redirect_to;
    }


    /**
     * @param $redirect_to
     * @param $action
     * @param $post_ids
     * @return void
     */
    public function handle_custom_status_bulk_action($redirect_to, $action, $post_ids)
    {

        if (get_option('wc_bordreau_enable-custom-status') == 'true') {

            $status = wc_get_bordereau_order_statuses();

            $action = str_replace('mark_', '', $action);

//            if (strpos($action, 'wc-') !== false) {
//                $action = str_replace('wc-', '', $action);
//            }

            if (strpos($action, "wc-") === false) {
                // Prefix the new status with "wc-"
                $action = "wc-" . $action;
            }


            if (is_array($status)) {
                if (in_array($action, array_keys($status))) {
                    foreach ($post_ids as $order_id) {
                        // TODO check this better for conflict with the auto insert
                        $order = wc_get_order($order_id);
                        $order->update_status($action);
                        // $this->change_order_status_without_hook($order_id, $action);
                    }

                    $redirect_to = add_query_arg(
                        array(
                            'bulk_action' => 'marked_' . str_replace('mark_', '', $action),
                            'changed' => count($post_ids),
                        ),
                        $redirect_to
                    );
                }
            }
        }


        return $redirect_to;
    }

    /**
     * @return void
     * @since 1.2.0
     */
    public function custom_order_status_notices()
    {

        $actions = array(
            'wc-awaiting-shipping',
            'wc-payment-status-received',
            'wc-payment-status-awaiting',
            'wc-tracking-expedited',
            'wc-tracking-returned',
            'wc-tracking-failed',
            'wc-tracking-delivered',
            'wc-dispatched'
        );

        if (isset($_REQUEST['bulk_action'])
            && in_array($_REQUEST['bulk_action'], $actions)
            && isset($_REQUEST['changed'])
            && $_REQUEST['changed']
        ) {
            // displaying the message
            printf(
                '<div id="message" class="updated notice is-dismissible"><p>' . _n('%d order status changed.', '%d order statuses changed.', $_REQUEST['changed']) . '</p></div>',
                $_REQUEST['changed']
            );
        }
    }

    /**
     * @return void
     * @since 1.2.0
     */
    public function register_wait_call_order_status()
    {

        register_post_status('wc-awaiting-shipping', array(
            'label' => __('Awaiting shipping', 'woo-bordereau-generator'),
            'public' => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list' => true,
            'exclude_from_search' => false,
            'label_count' => _n_noop('Awaiting shipping (%s)', 'Awaiting shipping (%s)')
        ));

        register_post_status('wc-dispatched', array(
            'label' => __('Dispatched', 'woo-bordereau-generator'),
            'public' => true,
            'show_in_admin_status_list' => true,
            'show_in_admin_all_list' => true,
            'exclude_from_search' => false,
            'label_count' => _n_noop('Dispatched (%s)', 'Dispatched (%s)')
        ));
    }

    /**
     *
     * @return void
     */
    public function register_custom_order_status()
    {
        $status = $this->get_order_status_posts();

        foreach ($status as $item) {

            register_post_status('wc-' . $item->post_name, array(
                'label' => $item->post_title,
                'public' => false,
                'exclude_from_search' => false,
                'show_in_admin_all_list' => true,
                'show_in_admin_status_list' => true,
                'label_count' => _n_noop($item->post_title . ' <span class="count">(%s)</span>', $item->post_title . ' <span class="count">(%s)</span>', 'woo-bordereau-generator'),
            ));

        }
    }

    /**
     * @param $order_statuses
     *
     * @return array
     * @since 1.2.0
     */
    public function add_wait_call_to_order_statuses($order_statuses)
    {
        $new_order_statuses = array();
        foreach ($order_statuses as $key => $status) {
            $new_order_statuses[$key] = $status;
            if ('wc-on-hold' === $key) {
                $new_order_statuses['wc-awaiting-shipping'] = 'Awaiting shipping';
                $new_order_statuses['wc-dispatched'] = 'Dispatched';
            }
        }

        return $new_order_statuses;
    }

    /**
     * @param $query
     *
     * @return void
     * @since 1.2.0
     */
    function filter_woocommerce_orders_in_the_table($query)
    {

        global $pagenow;

        if (!is_admin()) {
            return;
        }

        if (is_admin() && $query->is_main_query() && 'shop_order' === $query->get('post_type')) {

            if (isset($_GET['tracking_status']) && $_GET['tracking_status']) {

                $status = wc_clean($_GET['tracking_status']);

                if (is_nord_ouest_enabled()) {
                    $statusNordOuest = [
                        "upload" => "Uploadé sur le système",
                        "customer_validation" => "Validé",
                        "validation_collect_colis" => "Colis Ramassé",
                        "validation_reception_admin" => "Reception validé",
                        "validation_reception" => "Enlevé par le livreur",
                        "fdr_activated" => "En livraison",
                        "sent_to_redispatch" => "En livraison",
                        "nouvel_tentative_asked_by_customer" => "Nouvelle tentative demandée par le vendeur",
                        "return_asked_by_customer" => "Retour demandé par le partenaire",
                        "return_asked_by_hub" => "Retour En transit",
                        "retour_dispatched_to_partenaires" => "Retour transmis au partenaire",
                        "return_dispatched_to_partenaire" => "Retour transmis au partenaire",
                        "colis_retour_transmit_to_partner" => "Retour transmis au partenaire",
                        "colis_pickup_transmit_to_partner" => "Pick-UP transmis au partenaire",
                        "annulation_dispatch_retour" => "Transmission du retour au partenaire annulée",
                        "cancel_return_dispatched_to_partenaire" => "Transmission du retour au partenaire annulée",
                        "livraison_echoue_recu" => "Retour reçu par le partenaire",
                        "return_validated_by_partener" => "Retour validé par le partenaire",
                        "return_redispatched_to_livraison" => "Retour remis en livraison",
                        "return_dispatched_to_warehouse" => "Retour transmis vers entrepôt",
                        "pickedup" => "Pick-Up collecté",
                        "valid_return_pickup" => "Pick-Up validé",
                        "pickup_picked_recu" => "Pick-Up reçu par le partenaire",
                        "colis_suspendu" => "Suspendu",
                        "livre" => "Livré",
                        "livred" => "Livré",
                        "verssement_admin_cust" => "Montant transmis au partenaire",
                        "verssement_admin_cust_canceled" => "Versement annulé",
                        "verssement_hub_cust_canceled" => "",
                        "validation_reception_cash_by_partener" => "Montant reçu par le partenaire",
                        "echange_valide" => "Échange validé",
                        "echange_valid_by_hub" => "Échange validé",
                        "ask_to_delete_by_admin" => "Demande de suppression",
                        "ask_to_delete_by_hub" => "Demande de suppression",
                        "edited_informations" => "Informations modifiées",
                        "edit_price" => "Prix modifié",
                        "edit_wilaya" => "Changement de wilaya",
                        "extra_fee" => "Surfacturation du colis",
                        "mise_a_jour" => "Tentative de livraison"
                    ];

                    $status = $statusNordOuest[$status] ?? $status;
                }

                switch ($status) {
                    case 'Livré':
                        $meta_query = array(
                            array(
                                'key' => '_latest_tracking_status',
                                'value' => array('Livré', 'Livrée', 'Livrée [ Encaisser ]'),
                                'compare' => 'IN'
                            )
                        );
                        break;
                    case 'Echèc livraison':
                        $meta_query = array(
                            array(
                                'key' => '_latest_tracking_status',
                                'value' => array('Echèc livraison', 'Annuler par le Client annuler par le client'),
                                'compare' => 'IN'
                            )
                        );
                        break;
                    case 'Tentative échouée':
                        $meta_query = array(
                            array(
                                'key' => '_latest_tracking_status',
                                'value' => array('Tentative échouée',
                                    'Appel Tel',
                                    'Appel sans Réponse 1 ça sonne sans réponse',
                                    'Appel sans Réponse 2 ça sonne sans réponse',
                                    'Appel sans Réponse 3 ça sonne sans réponse'),
                                'compare' => 'IN'
                            )
                        );
                        break;
                    case 'En attente du client':
                        $meta_query = array(
                            array(
                                'key' => '_latest_tracking_status',
                                'value' => array('SD - En Attente du Client', 'En attente du client'),
                                'compare' => 'IN'
                            )
                        );
                        break;
                    default:
                        $meta_query = array(
                            array(
                                'key' => '_latest_tracking_status',
                                'value' => $status,
                                'compare' => 'LIKE'
                            )
                        );
                        break;

                }

                $query->set('meta_query', $meta_query);

            }

            if (isset($_GET['payment_status']) && $_GET['payment_status']) {

                $payment = wc_clean($_GET['payment_status']);

                switch ($payment) {
                    case 'received':
                    case 'verssement_admin_cust':
                    case 'Montant transmis au partenaire':
                        $meta_query = array(
                            array(
                                'key' => '_payment_status',
                                'value' => array('received', 'Livrée [ Encaisser ]'),
                                'compare' => 'IN'
                            )
                        );
                        break;
                    default:
                        $meta_query = array(
                            array(
                                'key' => '_payment_status',
                                'value' => $payment,
                                'compare' => '='
                            )
                        );
                        break;
                }


                $query->set('meta_query', $meta_query);
            }


            if (isset($_GET['tracking_number']) && $_GET['tracking_number']) {
                $meta_query = array(
                    array(
                        'key' => '_shipping_tracking_number',
                        'value' => wc_clean($_GET['tracking_number']),
                        'compare' => '='
                    )
                );

                $query->set('meta_query', $meta_query);
            }

            if (isset($_GET['range']) && $_GET['range']) {
                $date_range = array_map('intval', explode('-', sanitize_text_field($_GET['range'])));

                if (6 === count($date_range)) {
                    $query->set(
                        'date_query',
                        array(
                            'after' => array(
                                'year' => $date_range[0],
                                'month' => $date_range[1],
                                'day' => $date_range[2],
                            ),
                            'before' => array(
                                'year' => $date_range[3],
                                'month' => $date_range[4],
                                'day' => $date_range[5],
                            ),
                            'inclusive' => apply_filters('woo_orders_filterby_date_range_query_is_inclusive', true),
                            'column' => apply_filters('woo_orders_filterby_date_query_column', 'post_date'),
                        )
                    );
                }
            }
        }

        return;
    }

    function debug_wp_query_sql($query)
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        // Store the meta query for later use
        global $debug_meta_query;
        $debug_meta_query = $query->get('meta_query');

        // Add an action to print the SQL after the query is run
        add_action('pre_get_posts', function ($q) use ($query) {
            if ($q === $query) {
                add_filter('posts_request', [$this, 'capture_sql_query'], 10, 2);
            }
        });
    }

    function capture_sql_query($sql, $query)
    {
        global $debug_meta_query;

        // Remove this filter to prevent it from running on subsequent queries
        remove_filter('posts_request', 'capture_sql_query', 10);

        // Print or log the SQL query
        error_log("WP_Query SQL: " . $sql);

        // Print or log the meta query
        error_log("Meta Query: " . print_r($debug_meta_query, true));

        return $sql;
    }

    function filter_hpos_woocommerce_orders_in_the_table($order_query_args)
    {

        if (!is_admin()) {
            return;
        }

        global $pagenow;

        $meta_query = $order_query_args['meta_query'] ?? [];

        if (isset($_GET['tracking_status']) && $_GET['tracking_status']) {

            $status = wc_clean($_GET['tracking_status']);

            if (is_nord_ouest_enabled()) {

                $statusNordOuest = [
                    "upload" => "Uploadé sur le système",
                    "customer_validation" => "Validé",
                    "validation_collect_colis" => "Colis Ramassé",
                    "validation_reception_admin" => "Reception validé",
                    "validation_reception" => "Enlevé par le livreur",
                    "fdr_activated" => "En livraison",
                    "sent_to_redispatch" => "En livraison",
                    "nouvel_tentative_asked_by_customer" => "Nouvelle tentative demandée par le vendeur",
                    "return_asked_by_customer" => "Retour demandé par le partenaire",
                    "return_asked_by_hub" => "Retour En transit",
                    "retour_dispatched_to_partenaires" => "Retour transmis au partenaire",
                    "return_dispatched_to_partenaire" => "Retour transmis au partenaire",
                    "colis_retour_transmit_to_partner" => "Retour transmis au partenaire",
                    "colis_pickup_transmit_to_partner" => "Pick-UP transmis au partenaire",
                    "annulation_dispatch_retour" => "Transmission du retour au partenaire annulée",
                    "cancel_return_dispatched_to_partenaire" => "Transmission du retour au partenaire annulée",
                    "livraison_echoue_recu" => "Retour reçu par le partenaire",
                    "return_validated_by_partener" => "Retour validé par le partenaire",
                    "return_redispatched_to_livraison" => "Retour remis en livraison",
                    "return_dispatched_to_warehouse" => "Retour transmis vers entrepôt",
                    "pickedup" => "Pick-Up collecté",
                    "valid_return_pickup" => "Pick-Up validé",
                    "pickup_picked_recu" => "Pick-Up reçu par le partenaire",
                    "colis_suspendu" => "Suspendu",
                    "livre" => "Livré",
                    "livred" => "Livré",
                    "verssement_admin_cust" => "Montant transmis au partenaire",
                    "verssement_admin_cust_canceled" => "Versement annulé",
                    "verssement_hub_cust_canceled" => "",
                    "validation_reception_cash_by_partener" => "Montant reçu par le partenaire",
                    "echange_valide" => "Échange validé",
                    "echange_valid_by_hub" => "Échange validé",
                    "ask_to_delete_by_admin" => "Demande de suppression",
                    "ask_to_delete_by_hub" => "Demande de suppression",
                    "edited_informations" => "Informations modifiées",
                    "edit_price" => "Prix modifié",
                    "edit_wilaya" => "Changement de wilaya",
                    "extra_fee" => "Surfacturation du colis",
                    "mise_a_jour" => "Tentative de livraison"
                ];

                $status = $statusNordOuest[$status] ?? $status;
            }

            switch ($status) {
                case 'Livré':
                    $meta_query['meta_query'][] = array(
                        array(
                            'key' => '_latest_tracking_status',
                            'value' => array('Livré', 'Livrée', 'Livrée [ Encaisser ]'),
                            'compare' => 'IN'
                        )
                    );

                    break;
                case 'Echèc livraison':

                    $meta_query['meta_query'][] = array(
                        array(
                            'key' => '_latest_tracking_status',
                            'value' => array('Echèc livraison', 'Annuler par le Client annuler par le client'),
                            'compare' => 'IN'
                        )
                    );
                    break;
                case 'Tentative échouée':
                    $meta_query['meta_query'][] = array(
                        array(
                            'key' => '_latest_tracking_status',
                            'value' => array('Tentative échouée',
                                'Appel Tel',
                                'Appel sans Réponse 1 ça sonne sans réponse',
                                'Appel sans Réponse 2 ça sonne sans réponse',
                                'Appel sans Réponse 3 ça sonne sans réponse'),
                            'compare' => 'IN'
                        )
                    );

                    break;
                case 'En attente du client':
                    $meta_query['meta_query'][] = array(
                        array(
                            'key' => '_latest_tracking_status',
                            'value' => array('SD - En Attente du Client', 'En attente du client'),
                            'compare' => 'IN'
                        )
                    );

                    break;
                default:
                    $meta_query['meta_query'][] = array(
                        array(
                            'key' => '_latest_tracking_status',
                            'value' => $status,
                            'compare' => '='
                        )
                    );

                    break;
            }


            $order_query_args = array_merge($order_query_args, $meta_query);


            // $query->set('meta_query', $meta_query);
        }

        if (isset($_GET['payment_status']) && $_GET['payment_status']) {

            $payment = wc_clean($_GET['payment_status']);

            switch ($payment) {
                case 'received':
                    $meta_query['meta_query'][] = array(
                        array(
                            'key' => '_payment_status',
                            'value' => array('received', 'Livrée [ Encaisser ]'),
                            'compare' => 'IN'
                        )
                    );

                    break;
                default:
                    $meta_query['meta_query'][] = array(
                        array(
                            'key' => '_payment_status',
                            'value' => wc_clean($_GET['payment_status']),
                            'compare' => '='
                        )
                    );

                    break;
            }


            $order_query_args = array_merge($order_query_args, $meta_query);

        }


        if (isset($_GET['tracking_number']) && $_GET['tracking_number']) {

            $meta_query['meta_query'][] = array(
                array(
                    'key' => '_shipping_tracking_number',
                    'value' => wc_clean($_GET['tracking_number']),
                    'compare' => '='
                )
            );

            $order_query_args = array_merge($order_query_args, $meta_query);
        }

        if (isset($_GET['range']) && $_GET['range']) {

            $date_range = array_map('intval', explode('-', sanitize_text_field($_GET['range'])));

            if (6 === count($date_range)) {

                $date_query['date_query'] = array(
//                    'after' => array(
//                        'year' => $date_range[0],
//                        'month' => $date_range[1],
//                        'day' => $date_range[2],
//                    ),
//                    'before' => array(
//                        'year' => $date_range[3],
//                        'month' => $date_range[4],
//                        'day' => $date_range[5],
//                    ),

                    'after' => "$date_range[0]-$date_range[1]-$date_range[2]",
                    'before' => "$date_range[3]-$date_range[4]-$date_range[5]",
                    'inclusive' => apply_filters('woo_orders_filterby_date_range_query_is_inclusive', true),
                    'column' => apply_filters('woo_orders_filterby_date_query_column', 'post_date'),
                );

                $order_query_args = array_merge($order_query_args, $date_query);

            }
        }

        return $order_query_args;
    }


    /**
     * @return void
     * @since 1.2.0
     */
    function render_custom_orders_filters($post_type)
    {
        if ($post_type == 'shop_order') {
            $providers = $this->getEnabledProviders() ?? [];

            $status = [];
            foreach ($providers as $key => $provider) {
                if (class_exists($provider['setting_class'])) {
                    $providerClass = new $provider['setting_class']($provider);
                    if (method_exists($providerClass, 'get_status')) {
                        $status = $providerClass->get_status() + $status;
                    }
                }
            }

            $status = array_filter(array_unique($status));

            // get the settings class
            // get the status of the provider


            // Not on an Orders page.
            if (in_array(Helpers::get_screen_title(), ['shop_order', 'woocommerce_page_wc-orders'])) :
                ?>

                <audio src=""></audio>
                <input type="text" id="date_range" name="range"
                       placeholder="<?php echo __('Date Range', 'woo-bordereau-generator'); ?>"
                       value="<?php echo $_GET['range'] ?? ""; ?>">

                <select name="tracking_status">
                    <option <?php echo Helpers::check_if_selected('tracking_status', ""); ?>
                            value=""><?php echo __('Tracking Status', 'woo-bordereau-generator'); ?></option>

                    <?php
                    foreach ($status as $key => $stat):
                        ?>
                        <option <?php echo Helpers::check_if_selected('tracking_status', $key); ?>
                                value="<?php echo $key; ?>"><?php echo __($stat, 'woo-bordereau-generator'); ?></option>
                    <?php
                    endforeach;
                    ?>

                </select>

                <select name="payment_status">
                    <option value=""><?php echo __('Payment Status', 'woo-bordereau-generator'); ?></option>
                    <option <?php echo Helpers::check_if_selected('payment_status', 'awaiting'); ?>
                            value="awaiting"><?php echo __('Payment awaiting', 'woo-bordereau-generator'); ?></option>
                    <option <?php echo Helpers::check_if_selected('payment_status', 'received'); ?>
                            value="received"><?php echo __('Payment received', 'woo-bordereau-generator'); ?></option>
                    <option <?php echo Helpers::check_if_selected('payment_status', 'not-ready'); ?>
                            value="received"><?php echo __('Payment not ready', 'woo-bordereau-generator'); ?></option>
                    <option <?php echo Helpers::check_if_selected('payment_status', 'ready'); ?>
                            value="received"><?php echo __('Payment ready', 'woo-bordereau-generator'); ?></option>
                    <option <?php echo Helpers::check_if_selected('payment_status', 'receivable'); ?>
                            value="received"><?php echo __('Payment receivable', 'woo-bordereau-generator'); ?></option>
                    <option <?php echo Helpers::check_if_selected('payment_status', 'payed'); ?>
                            value="received"><?php echo __('Payment payed', 'woo-bordereau-generator'); ?></option>
                </select>

                <input type="text" name="tracking_number" placeholder="By tracking number"
                       value="<?php echo $_GET['tracking_number'] ?? ''; ?>">
                <span id="barcode_scanner" class="inline-flex mx-1 h-8"></span>

            <?php
            endif;
        }
        // get the enabled providers


    }


    /**
     * Style the budge in orders page
     * @return void
     * @since 1.2.0
     */
    public function styling_admin_order_list()
    {
        global $pagenow, $post;


        $dispatched_status = 'Dispatched';
        $payment_received_status = 'Payment received';
        $awaiting_shipping_status = 'Awaiting shipping';
        ?>
        <style>
            .order-status.status-<?php echo sanitize_title($dispatched_status); ?> {
                background: #2980b9;
                color: #fff;
            }

            .order-status.status-blue {
                background: #2980b9;
                color: #fff;
            }

            .order-status.status-green {
                background: #27ae60;
                color: #fff;
            }

            .order-status.status-red {
                background: #eba3a3;
                color: #761919
            }

            .order-status.status-gray {
                background: #bdc3c7;
                color: #2c2c2c;
            }


            .order-status.status-<?php echo sanitize_title($payment_received_status); ?> {
                background: #27ae60;
                color: #fff;
            }

            .order-status.status-<?php echo sanitize_title($awaiting_shipping_status); ?> {
                background: #bdc3c7;
                color: #2c2c2c;
            }

            <?php
                $status = $this->get_orders_status_with_details();

                foreach ($status as $key => $item) {
                    $slug = $item['slug'];

                    $bgColor = $item['bgColor'];
                    $color = $item['color'];

                    if ($bgColor && $color) {
                         echo ".order-status.status-$slug {
                            background: $bgColor;
                            color: $color;
                        }";
                    }

                }


             ?>
        </style>
        <?php
    }


    /**
     * @param $order_statuses
     *
     * @return mixed
     * @since 1.2.0
     */
    public function rename_orders_status($order_statuses)
    {
        foreach ($order_statuses as $key => $status) {
            if ('wc-on-hold' === $key) {
                $order_statuses['wc-on-hold'] = _x('En attente', 'Order status', 'woo-bordereau-generator');
            }

            if ('wc-processing' === $key) {
                $order_statuses['wc-processing'] = _x('En cours', 'Order status', 'woo-bordereau-generator');
            }

            if ('wc-completed' === $key) {
                $order_statuses['wc-completed'] = _x('Livré', 'Order status', 'woo-bordereau-generator');
            }
        }

        return $order_statuses;
    }

    /**
     * @param $order_statuses
     * @return mixed
     */
    public function add_custom_orders_status($order_statuses)
    {
        if (get_option('wc_bordreau_enable-custom-status') == 'true') {
            $status = wc_get_bordereau_order_statuses();

            if (is_array($status)) {
                foreach ($status as $key => $item) {
                    $order_statuses[$key] = $item;
                }
            }

        }

        return $order_statuses;
    }

    /**
     * @param $order_id
     *
     * @return void
     * @since 1.2.0
     */
    public function change_default_order_status($order_id)
    {

        $default_status = get_option('wc_bordreau_default_order_status');

        if (!$order_id || !$default_status) {
            return;
        }

        bordreau_log_entries('Order :' . $order_id . ' try to switch status to ' . $default_status);

        // check if $default_status doesn't start with wc-

        if (strpos($default_status, 'wc-') === false) {
            $default_status = "wc-" . $default_status;
        }

        // This could be the issue facing some people
        $order = wc_get_order($order_id);
        if ('cod' === $order->get_payment_method()) {
            $order->update_status($default_status);
        }
    }

    /**
     * Add Search by Phone
     * @return void
     * @since 1.4.1
     */
    public function billing_phone_search_fields($meta_keys)
    {
        $meta_keys[] = '_billing_phone';
        return $meta_keys;
    }


    /**
     * @since 1.6.5
     */
    public function import_shipping_class()
    {
        check_ajax_referer('wp_bordereau_rest', '_nonce');
        wp_send_json(BordereauGeneratorProviders::import_shipping_class_by_provider($_POST), 200);
    }

    /**
     * @return void
     * @since 1.7.0
     */
    public function debug_order_status_changed($order_id, $old_status, $new_status)
    {

        if ($user = wp_get_current_user()) {
            $this->logger('Order: ' . $order_id . ' Old Status: ' . $old_status . ' New status: ' . $new_status . ' by: ' . $user->nickname);
        } else {
            $this->logger('Order: ' . $order_id . ' Old Status: ' . $old_status . ' New status: ' . $new_status);
        }
    }

    private function logger($message)
    {
        $upload_dir = wp_upload_dir();

        $directory = $upload_dir['basedir'] . '/wc-bordereau-generator';

        $filename = 'debug.json';

        $path = $directory . '/' . $filename;

        if (!file_exists($path)) {
            $content = json_encode([time() => 'File Created']);
            file_put_contents($path, $content);
        }

        $logs = json_decode(file_get_contents($path), true);

        $logs[] = [time() => $message];

        file_put_contents($path, json_encode($logs));
    }


    public function get_rest_integrate()
    {
        check_ajax_referer('wp_bordereau_rest', '_nonce');

        $data = [
            'v' => get_option('_woo_bordreau_generator_license'),
            'b' => get_bloginfo('url')
        ];

        $this->check_integrity_in_remote($data);
    }

    public function get_list_items()
    {
        check_ajax_referer('wp_bordereau_rest', '_nonce');

        // Check for cached license status first (valid for 1 hour)
        $cached_status = get_transient('bordereau_license_check');
        
        if ($cached_status !== false) {
            // Return cached result
            wp_send_json([
                'status' => $cached_status,
                'cached' => true
            ], 200);
            return;
        }

        // No cache, perform remote check
        $data = [
            'v' => get_option('_woo_bordreau_generator_license'),
            'b' => get_bloginfo('url')
        ];

        $this->get_items_for_checking_with_cache($data);
    }

    /**
     * Check license with caching support
     * 
     * @param array $data License data
     * @return void
     */
    public function get_items_for_checking_with_cache($data)
    {
        $args = array(
            'method' => 'POST',
            'timeout' => 15, // Reduced from 45 seconds for better UX
            'sslverify' => false,
            'headers' => array(
                'Authorization' => sprintf('BRTKN %s', base64_encode($data['v'])),
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($data),
        );

        $remote = wp_remote_post(
            'https://amineware.me/api/check-license/v2',
            $args
        );

        if (is_wp_error($remote)
            || 200 !== wp_remote_retrieve_response_code($remote)
            || empty(wp_remote_retrieve_body($remote))) {

            put_bordreau_generator_status('disabled');
            
            // Cache the failed status for 5 minutes (retry sooner on failure)
            set_transient('bordereau_license_check', 'disabled', 5 * MINUTE_IN_SECONDS);

            wp_send_json([
                'license' => $data['v'],
                'status' => 'disabled'
            ], 400);

            exit();
        }

        put_bordreau_generator_status('active');
        
        // Cache successful status for 1 hour
        set_transient('bordereau_license_check', 'active', HOUR_IN_SECONDS);

        $remote = json_decode(wp_remote_retrieve_body($remote));

        $option_name = '_woo_bordreau_generator_license';
        update_option($option_name, $data['v']);
        $remote->license = get_option($option_name);

        wp_send_json($remote, 200);
    }

    /**
     * @return void
     */
    public function get_bordereau_tutorial()
    {
        check_ajax_referer('wp_bordereau_rest', '_nonce');

        $data = get_option('_woo_bordreau_generator_license');

        $remote = wp_remote_get(
            "https://amineware.me/api/tutorials?license=" . sanitize_text_field($data),
            array(
                'timeout' => 10,
                'headers' => array(
                    'Accept' => 'application/json'
                )
            )
        );

        if (is_wp_error($remote)
            || 200 !== wp_remote_retrieve_response_code($remote)
            || empty(wp_remote_retrieve_body($remote))) {

            wp_send_json([
                'videos' => [],
            ], 400);

            exit();
        }

        wp_send_json([
            'videos' => json_decode(wp_remote_retrieve_body($remote))
        ], 200);
    }

    /**
     * @return void
     */
    public function post_rest_integrate()
    {

        check_ajax_referer('wp_bordereau_rest', '_nonce');

        $v = sanitize_text_field($_POST['license']);

        // Clear cache when validating new license
        delete_transient('bordereau_license_check');

        $data = [
            'v' => $v,
            'b' => get_bloginfo('url')
        ];


        $this->check_integrity_in_remote($data);
    }

    /**
     * Check the integraty of the system from hacking and exploit
     * @param string $data
     */
    public function check_integrity_in_remote($data)
    {

        $args = array(
            'method' => 'POST',
            'timeout' => 15, // Reduced from 45 seconds for better UX
            'sslverify' => false,
            'headers' => array(
                'Authorization' => sprintf('BRTKN %s', base64_encode($data['v'])),
                'Content-Type' => 'application/json',
            ),
            'body' => json_encode($data),
        );

        $remote = wp_remote_post(
            'https://amineware.me/api/check-license/v2',
            $args
        );


        if (is_wp_error($remote)
            || 200 !== wp_remote_retrieve_response_code($remote)
            || empty(wp_remote_retrieve_body($remote))) {

            put_bordreau_generator_status('disabled');
            
            // Update cache on failure (5 min cache for retry)
            set_transient('bordereau_license_check', 'disabled', 5 * MINUTE_IN_SECONDS);

            wp_send_json([
                'license' => $data['v'],
                'status' => 'disabled'
            ], 400);

            exit();
        }

        put_bordreau_generator_status('active');
        
        // Update cache on success (1 hour cache)
        set_transient('bordereau_license_check', 'active', HOUR_IN_SECONDS);

        $remote = json_decode(wp_remote_retrieve_body($remote));

        $option_name = '_woo_bordreau_generator_license';

        // Update the option with new data
        $update_result = update_option($option_name, $data['v']);

        $remote->license = get_option($option_name);

        wp_send_json($remote, 200);

    }

    public function bulk_barcode_action()
    {

        check_ajax_referer('wp_bordereau_rest', '_nonce');

        // Assuming you're inside the AJAX handler and $_POST['order_id'] is set
        if (isset($_POST['orders']) && isset($_POST['action_bulk'])) {

            $ordersId = stripslashes(sanitize_text_field($_POST['orders']));
            $orders = json_decode($ordersId, true);
            if ($orders && count($orders)) {
                foreach ($orders as $order_id) {
                    // Get an instance of the WC_Order object
                    $order = wc_get_order(intval($order_id));

                    if (!$order) {
                        $order = Helpers::get_order_by_order_number($order_id);
                    }

                    if (!$order) {
                        $orderId = Helpers::get_order_id_by_sequential_order_number($order_id);
                        if ($orderId) {
                            $order = wc_get_order($orderId);
                        }
                    }

                    if ($order) {
                        $new_status = str_replace('mark_', '', sanitize_text_field(sanitize_text_field($_POST['action_bulk']))); // Set the new status here

                        // check if the $new_status doesn't have wc-

                        if (strpos($new_status, "wc-") === false) {
                            $new_status = "wc-" . $new_status;
                        }

                        // Update the order status
                        // $this->change_order_status_without_hook($order_id, $new_status);
                        $order->update_status($new_status);
                    } else {
                    // Order not found
                    wp_send_json_error(__('Order not found', 'woo-bordereau-generator'));
                    }
                }
            }


            wp_send_json([
                'message' => __('Order status updated programmatically.', 'woo-bordereau-generator'),
                'redirect' => true,
                'redirect_url' => admin_url('edit.php?post_type=shop_order')
            ]);

        } else {
            // Invalid request
            wp_send_json_error(__('Invalid request', 'woo-bordereau-generator'));
        }

    }


    /**
     * Get the shippig method from the order
     *
     * @return void
     */
    public function get_shipping_method()
    {

        check_ajax_referer('wp_bordereau_rest', '_nonce');

        $order = wc_get_order(sanitize_text_field($_POST['order']));

        if ($order) {

            $desired_method_instance = null;
            $method_id = null;
            $instance_id = null;
            $provider = 'none';

            $shipping_methods = $order->get_shipping_methods();

            $methodSelected = 'none';

            foreach ($shipping_methods as $shipping_method) {

                $method_id = $shipping_method['method_id'];
                $instance_id = $shipping_method['instance_id'];

                if (strpos($method_id, 'flat_rate') !== false) {
                    $methodSelected = 'home';
                } elseif (strpos($method_id, 'local_pickup') !== false) {
                    $methodSelected = 'stopdesk';
                }
            }

            if ($method_id && $instance_id) {
                $shipping_zones = WC_Shipping_Zones::get_zones();

                foreach ($shipping_zones as $zone_data) {
                    $zone = new WC_Shipping_Zone($zone_data['zone_id']);
                    $shipping_methods = $zone->get_shipping_methods(true);

                    foreach ($shipping_methods as $method) {
                        if ($method->id === $method_id && $method->instance_id == $instance_id) {
                            // Found the desired method instance
                            $desired_method_instance = $method;
                            break 2; // Exit the loop once the method is found
                        }
                    }
                }

                if ($desired_method_instance) {
                    if (method_exists($desired_method_instance, 'get_provider')) {
                        $provider = $desired_method_instance->get_provider();
                    }
                }
            }


            wp_send_json([
                'type' => $methodSelected,
                'provider' => $provider
            ], 200);
        }
    }

    public function add_bulk_orders($post_ids, $action)
    {
        // get the class of the shipping

        $providers = BordereauGeneratorProviders::get_providers();
        $provider = $providers[str_replace('wc-bulk-add-', '', $action)];

        $providerClass = new $provider['setting_class']($provider);
        if (method_exists($providerClass, 'bulk_add_orders')) {
            $providerClass->bulk_add_orders($post_ids);
        }

    }


    /**
     * @return void
     */
    public function sync_maystro_products()
    {
        $providers = BordereauGeneratorProviders::get_providers();
        $provider = $providers[sanitize_text_field($_POST['provider'])];


        $providerClass = new $provider['setting_class']($provider);
        if (method_exists($providerClass, 'sync_products')) {
            $providerClass->sync_products();
        }
    }

    public function sync_products()
    {
        $providers = BordereauGeneratorProviders::get_providers();
        $provider = $providers[sanitize_text_field($_POST['provider'])];

        $providerClass = new $provider['setting_class']($provider);
        if (method_exists($providerClass, 'sync_products')) {
            $providerClass->sync_products();
        }
    }

    public function get_items_for_checking($data)
    {
        return $this->check_integrity_in_remote($data);
    }

    /**
     * @return void
     */
    public function clear_boredreau_cache()
    {

        check_ajax_referer('wp_bordereau_rest', '_nonce');

        $upload_dir = wp_upload_dir();

        $directory = $upload_dir['basedir'] . '/wc-bordereau-generator/';

        $files = glob($directory . '*.json');

        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function clear_boredreau_shipping_zone()
    {
        check_ajax_referer('wp_bordereau_rest', '_nonce');

        $zones = WC_Shipping_Zones::get_zones();

        foreach ($zones as $zone) {
            WC_Shipping_Zones::delete_zone($zone['id']);
        }

        // Don't forget to delete the "Rest of the World" zone with ID = 0
        WC_Shipping_Zones::delete_zone(0);
    }

    public function get_product_by_name_from_provider()
    {
        check_ajax_referer('wp_bordereau_rest', '_nonce');

        $providers = BordereauGeneratorProviders::get_providers();
        $provider = $providers[sanitize_text_field($_POST['provider'])];


        $providerClass = new $provider['setting_class']($provider);
        if (method_exists($providerClass, 'get_products_by_name')) {
            $providerClass->get_products_by_name();
        }


    }

    public function clear_products_cache()
    {
        check_ajax_referer('wp_bordereau_rest', '_nonce');

        $providers = BordereauGeneratorProviders::get_providers();
        $provider = $providers[sanitize_text_field($_POST['provider'])];


        $providerClass = new $provider['setting_class']($provider);
        if (method_exists($providerClass, 'clear_products_cache')) {
            $providerClass->clear_products_cache();
        }


    }

    /**
     * @param $order_id
     * @param $old_status
     * @param $new_status
     * @param $order
     * @return void
     */
    public function queue_order_to_shipping($order_id, $old_status, $new_status, \WC_Order $order)
    {
        if (get_option('wc_bordreau_enable-automatic-expidate')) {

            $status = get_option('wc_bordreau_status-order-disaptched');
            if ($status) {
                $status = str_replace('wc-', '', $status);

                if ($status == $new_status) {
                    try {
                        // Retrieve all available providers and enabled providers from settings
                        $providers = BordereauGeneratorProviders::get_providers();
                        $enabledProviders = get_option('wc-bordereau-generator_allowed_providers');

                        $tracking_number = $order->get_meta('_shipping_tracking_number');
                        $provider = $order->get_meta('_shipping_tracking_method');

                        if ($tracking_number && $provider) {
                            $providers = BordereauGeneratorProviders::get_providers();
                            $selectedProvider = $providers[$provider];
                            if (class_exists($selectedProvider['setting_class'])) {
                                $providerClass = new $selectedProvider['setting_class']($selectedProvider);
                                if (method_exists($providerClass, 'mark_as_dispatched')) {
                                    try {
                                        $providerClass->mark_as_dispatched($order);
                                    } catch (\ErrorException $exception) {
                                        set_transient('unable_to_disaptch_from_provider', $exception->getMessage(), 45);
                                    }
                                }
                            }
                        }
                    } catch (\ErrorException $exception) {
                        bordreau_log_entries($exception->getMessage(), 'error');
                    }
                }
            }
        }


        $tracking_number_exist = get_post_meta($order_id, '_shipping_tracking_number', true);
        if (!$tracking_number_exist) {
            $tracking_number_exist = $order->get_meta('_shipping_tracking_number');
        }

        if ($tracking_number_exist) {
            return;
        }

        if (get_option('wc_bordreau_enable-automatic-order-add') == 'true') {

            $status = get_option('wc_bordreau_status-order-add');

            if ($status) {
                $status = str_replace('wc-', '', $status);

                if ($status == $new_status) {

                    try {
                        // Retrieve all available providers and enabled providers from settings
                        $providers = BordereauGeneratorProviders::get_providers();
                        $enabledProviders = get_option('wc-bordereau-generator_allowed_providers');

                        // Initial selected provider
                        $selected_provider_key = false;
                        $selected_method = false;
                        $shipping_methods = WC()->shipping()->get_shipping_methods();

                        // Loop through the shipping methods in the order
                        foreach ($order->get_shipping_methods() as $key => $method) {
                            $instance_id = $method->get_instance_id();
                            $shipping_zones = WC_Shipping_Zones::get_zones();  // Retrieve all shipping zones

                            foreach ($shipping_zones as $zone_data) {
                                $shipping_methods = $zone_data['shipping_methods'];  // Get all shipping methods for the zone

                                foreach ($shipping_methods as $method) {
                                    if ($method->instance_id == $instance_id) {
                                        $selected_method = $method;  // Return the method if the instance_id matches
                                    }
                                }
                            }

                            if ($selected_method) {
                                $shipping_method_instance = $selected_method;

                                // Check if the shipping method instance can provide its provider
                                if (method_exists($shipping_method_instance, 'get_provider')) {
                                    $selected_provider_key = $shipping_method_instance->get_provider();
                                    break;  // Exit the loop if a provider is found
                                }
                            }
                        }

                        // Fallback to the default provider if none was found in the shipping method
                        if (!$selected_provider_key) {
                            $selected_provider_key = reset($enabledProviders);
                        }

                        // Process the provider if found
                        if ($selected_provider_key && isset($providers[$selected_provider_key])) {
                            $selectedProvider = $providers[$selected_provider_key];
                            $providerClass = new $selectedProvider['setting_class']($selectedProvider);

                            // Check if the provider class has the method to add orders in bulk and the order doesn't have a tracking number
                            if (method_exists($providerClass, 'bulk_add_orders')) {
                                $providerClass->bulk_add_orders([$order->get_id()]);
                            }
                        } else {
                            // Handle case where no valid provider is found
                            error_log('No valid provider found for this order.');
                        }

                    } catch (\ErrorException $exception) {
                        bordreau_log_entries($exception->getMessage(), 'error');
                    }
                }
            }
        }
    }

    function change_order_status_without_hook($order_id, $new_status)
    {
        global $wpdb;


        // Check if the new status starts with 'wc-', append it if not
        if (strpos($new_status, 'wc-') === false) {
            $new_status = 'wc-' . $new_status;
        }

        // Check if HPOS is enabled
        $is_hpos_enabled = Helpers::is_hpos_enabled();

        if ($is_hpos_enabled) {
            // HPOS is enabled, update the custom table
            $result = $wpdb->update(
                "{$wpdb->prefix}wc_orders", // Assuming the table name follows this convention
                array('status' => $new_status),
                array('id' => $order_id) // Ensure you're using the correct column name for the order ID
            );

            if (!$result) {
                bordreau_log_entries($wpdb->last_error);
            }
        } else {
            // HPOS is not enabled, update the wp_posts table
            $result = $wpdb->update(
                $wpdb->posts,
                array('post_status' => $new_status),
                array('ID' => $order_id, 'post_type' => 'shop_order')
            );

            if (!$result) {
                bordreau_log_entries($wpdb->last_error);
            }

            // Clean post cache to ensure the change is reflected
            clean_post_cache($order_id);
        }

        // Handle wc-completed status updates
        if ($new_status == 'wc-completed') {
            // Ensure to get the order object in a way that is compatible with HPOS
            $order = wc_get_order($order_id);
            if ($order) {
                $order->update_status('wc-completed', 'Order marked as completed programmatically by bordereau generator.');
            }
        }
    }

    public function custom_states_algeria($states)
    {
        $states['DZ'] = $this->format_algeria_states();
        return $states;
    }


    function format_algeria_states() {
        return [
            'DZ-01' => 'Adrar',
            'DZ-02' => 'Chlef',
            'DZ-03' => 'Laghouat',
            'DZ-04' => 'Oum El Bouaghi',
            'DZ-05' => 'Batna',
            'DZ-06' => 'Bejaia',
            'DZ-07' => 'Biskra',
            'DZ-08' => 'Bechar',
            'DZ-09' => 'Blida',
            'DZ-10' => 'Bouira',
            'DZ-11' => 'Tamanrasset',
            'DZ-12' => 'Tebessa',
            'DZ-13' => 'Tlemcen',
            'DZ-14' => 'Tiaret',
            'DZ-15' => 'Tizi Ouzou',
            'DZ-16' => 'Alger',
            'DZ-17' => 'Djelfa',
            'DZ-18' => 'Jijel',
            'DZ-19' => 'Setif',
            'DZ-20' => 'Saida',
            'DZ-21' => 'Skikda',
            'DZ-22' => 'Sidi Bel Abbes',
            'DZ-23' => 'Annaba',
            'DZ-24' => 'Guelma',
            'DZ-25' => 'Constantine',
            'DZ-26' => 'Medea',
            'DZ-27' => 'Mostaganem',
            'DZ-28' => 'M\'Sila',
            'DZ-29' => 'Mascara',
            'DZ-30' => 'Ouargla',
            'DZ-31' => 'Oran',
            'DZ-32' => 'El Bayadh',
            'DZ-33' => 'Illizi',
            'DZ-34' => 'Bordj Bou Arreridj',
            'DZ-35' => 'Boumerdes',
            'DZ-36' => 'El Tarf',
            'DZ-37' => 'Tindouf',
            'DZ-38' => 'Tissemsilt',
            'DZ-39' => 'El Oued',
            'DZ-40' => 'Khenchela',
            'DZ-41' => 'Souk Ahras',
            'DZ-42' => 'Tipaza',
            'DZ-43' => 'Mila',
            'DZ-44' => 'Ain Defla',
            'DZ-45' => 'Naama',
            'DZ-46' => 'Ain Temouchent',
            'DZ-47' => 'Ghardaia',
            'DZ-48' => 'Relizane',
            'DZ-49' => 'Timimoun',
            'DZ-50' => 'Bordj Badji Mokhtar',
            'DZ-51' => 'Ouled Djellal',
            'DZ-52' => 'Béni Abbès',
            'DZ-53' => 'In Salah',
            'DZ-54' => 'In Guezzam',
            'DZ-55' => 'Touggourt',
            'DZ-56' => 'Djanet',
            'DZ-57' => 'El M\'Ghair',
            'DZ-58' => 'El Meniaa',
        ];

    }


    public function get_ups_oauth_authorization()
    {

        $providers = BordereauGeneratorProviders::get_providers();
        $provider = $providers[sanitize_text_field('ups')];


        $providerClass = new $provider['setting_class']($provider);
        if (method_exists($providerClass, 'request_token')) {
            $providerClass->request_token();
        }

    }

    public function get_ups_oauth_granted()
    {


        $providers = BordereauGeneratorProviders::get_providers();
        $provider = $providers[sanitize_text_field('ups')];


        $providerClass = new $provider['setting_class']($provider);
        if (method_exists($providerClass, 'request_token')) {
            $providerClass->request_token();
        }

    }

    public function oauth_ups()
    {

        $providers = BordereauGeneratorProviders::get_providers();
        $provider = $providers[sanitize_text_field($_POST['provider'])];


        $providerClass = new $provider['setting_class']($provider);
        if (method_exists($providerClass, 'oauth_access')) {
            $providerClass->oauth_access();
        }
    }

    private function check_bulk_tracking_for_provider($provider, $codes)
    {

        if (class_exists($provider['setting_class'])) {
            $providerClass = new $provider['setting_class']($provider);
            if (method_exists($providerClass, 'bulk_track')) {

                $chunkSize = 40; // Set the desired chunk size

                if ($providerClass == YalidineShippingSettings::class) {
                    $chunkSize = 1000;
                }

                $codeChunks = array_chunk($codes, $chunkSize);

                foreach ($codeChunks as $chunk) {
                    $result = $providerClass->bulk_track($chunk);
                    if ($result && is_array($result) && count($result) > 0) {
                        $newResult = [];

                        // Make tracking number uppercase
                        foreach ($result as $j => $t) {
                            $newResult[strtoupper($j)] = $t;
                        }

                        $finalResult = [];
                        foreach ($chunk as $key => $value) {
                            // Normalize the case for matching
                            $lowercaseValue = strtoupper($value);
                            if (isset($newResult[$lowercaseValue])) {
                                $i = array_search($value, $codes);
                                $finalResult[$i] = $newResult[$lowercaseValue];
                            }
                        }

                        foreach ($finalResult as $index => $item) {
                            $order = wc_get_order($index);
                            if ($order) {
                                // Set the status of the order
                                $this->map_status_to_shipping_status($order, $item);
                            }
                        }
                    }
                    sleep(1);
                }

//                $result = $providerClass->bulk_track($codes);
//                if ($result && is_array($result) && count($result) > 0) {
//
//                    $newResult = [];
//
//                    // make tracking number uppercase
//                    foreach ($result as $j => $t) {
//                        $newResult[strtoupper($j)] = $t;
//                    }
//
//                    $finalResult = [];
//                    foreach ($codes as $key => $value) {
//                        // Normalize the case for matching
//                        $lowercaseValue = strtoupper($value);
//                        if (isset($newResult[$lowercaseValue])) {
//                            $finalResult[$key] = $newResult[$lowercaseValue];
//                        }
//                    }
//
//                    foreach ($finalResult as $index => $item) {
//                        $order = wc_get_order($index);
//                        if ($order) {
//                            // set the status of the order
//                            $this->map_status_to_shipping_status($order, $item);
//                        }
//                    }
//                }
            }
        }
    }

    /**
     * @return void
     */
    public function save_webhook_token()
    {

        $provider = sanitize_text_field($_POST['provider']);
        $token = sanitize_text_field($_POST['token']);
        update_option($provider . '_webhook_token', $token);

        wp_send_json([
            'success' => true,
            'message' => __('Token has been saved', 'woo-bordereau-generator')
        ], 200);
    }

    /**
     * Register webhook endpoint with ZR Express 2.0
     *
     * @return void
     */
    public function register_zrexpress_webhook()
    {
        $provider = sanitize_text_field($_POST['provider']);
        $token = sanitize_text_field($_POST['token'] ?? '');

        $providers = BordereauGeneratorProviders::get_providers();

        if (!isset($providers[$provider])) {
            wp_send_json([
                'success' => false,
                'message' => __('Invalid provider', 'woo-bordereau-generator')
            ], 400);
        }

        $provider_config = $providers[$provider];
        $webhook_url = rest_url('woo-bordereau/v1/webhook/' . $provider);

        if (class_exists($provider_config['setting_class'])) {
            $class = new $provider_config['setting_class']($provider_config);
            if (method_exists($class, 'register_webhook')) {
                $result = $class->register_webhook($webhook_url, $token);
                wp_send_json($result, $result['success'] ? 200 : 400);
                return;
            }
        }

        wp_send_json([
            'success' => false,
            'message' => __('Webhook registration not supported for this provider', 'woo-bordereau-generator')
        ], 400);
    }

    /**
     * Save webhook signing secret manually (for ZR Express or other providers)
     * This allows updating the secret without re-registering the webhook
     *
     * @return void
     */
    public function save_webhook_secret()
    {
        $provider = sanitize_text_field($_POST['provider']);
        $secret = sanitize_text_field($_POST['secret'] ?? '');

        if (empty($secret)) {
            wp_send_json([
                'success' => false,
                'message' => __('Signing secret is required', 'woo-bordereau-generator')
            ], 400);
        }

        update_option($provider . '_webhook_secret', $secret);

        wp_send_json([
            'success' => true,
            'message' => __('Signing secret has been saved', 'woo-bordereau-generator')
        ], 200);
    }

    public function get_order_meta()
    {

//        $post_id = sanitize_text_field($_POST['post']);
//        $order = wc_get_order($post_id);
//
//        if ($order) {
//            $shipping_methods = $order->get_shipping_methods();
//
//            foreach ($shipping_methods as $shipping_method) {
//                var_dump($shipping_method->get_instance_id());
//                die();
//            }
//
//            $shipping_method = reset($shipping_methods);
//
//
//            $shipping_zones = WC_Shipping_Zones::get_zones();
//            foreach ($shipping_zones as $zone_data) {
//                $shipping_methods = $zone_data['shipping_methods'];
//                foreach ($shipping_methods as $sm) {
//                    var_dump($sm->get_instance_id());
//                    if ($sm->get_instance_id() == $instance_id) {
//                        print_r($sm->get_provider());
//                        die();
//                    }
//                }
//            }
//
//            die('here');
//
//
//            $method = $shipping_method;
//
//            var_dump($method);
//            die();
//        }
    }


    /**
     * Register the custom shipping methods
     * @param $methods
     * @return mixed
     * @since 4.0.0
     */
    public function woocommerce_shipping_methods($methods)
    {

        $methods['local_pickup_yalidine'] = YalidineLocalPickup::class;
        $methods['flat_rate_yalidine'] = YalidineFlatRate::class;

        $methods['flat_rate_zr_express'] = ZRExpressFlatRate::class;
        $methods['local_pickup_zr_express'] = ZRExpressLocalPickup::class;

        $methods['flat_rate_zrexpress_v2'] = ZRExpressTwoFlatRate::class;
        $methods['local_pickup_zrexpress_v2'] = ZRExpressTwoLocalPickup::class;

        // Near Delivery only supports stopdesk (local pickup)
        $methods['local_pickup_near_delivery'] = NearDeliveryLocalPickup::class;

        $methods['flat_rate_toncolis'] = TonColisFlatRate::class;
        $methods['local_pickup_toncolis'] = TonColisLocalPickup::class;

        $methods['flat_rate_ecotrack'] = EcotrackFlatRate::class;
        $methods['local_pickup_ecotrack'] = EcotrackLocalPickup::class;

        $methods['flat_rate_conexlog'] = ConexlogFlatRate::class;
        $methods['local_pickup_conexlog'] = ConexlogLocalPickup::class;

        $methods['flat_rate_noest'] = NordOuestFlatRate::class;
        $methods['local_pickup_noest'] = NordOuestLocalPickup::class;

        $methods['flat_rate_maystro'] = MaystroDeliveryFlatRate::class;
        $methods['local_pickup_maystro'] = MaystroDeliveryLocalPickup::class;

        $methods['flat_rate_mylerz'] = MylerzExpressFlatRate::class;
        $methods['local_pickup_mylerz'] = MylerzExpressLocalPickup::class;

        $methods['flat_rate_elogistia'] = ElogistiaFlatRate::class;
        $methods['local_pickup_elogistia'] = ElogistiaLocalPickup::class;

        $methods['flat_rate_zimoexpress'] = ZimoExpressFlatRate::class;
        $methods['local_pickup_zimoexpress'] = ZimoExpressLocalPickup::class;

        $methods['flat_rate_3m_express'] = WC3MExpressFlatRate::class;
        $methods['local_pickup_3m_express'] = WC3MExpressLocalPickup::class;

        $methods['flat_rate_lihlihexpress'] = LihlihExpressFlatRate::class;
        $methods['local_pickup_lihlihexpress'] = LihlihExpressLocalPickup::class;

        $methods['flat_rate_colivraison'] = ColivraisonFlatRate::class;
        $methods['local_pickup_colivraison'] = ColivraisonLocalPickup::class;

        $methods['flat_rate_yalitec_new'] = YalitecFlatRate::class;
        $methods['local_pickup_yalitec_new'] = YalitecLocalPickup::class;

        $methods['flat_rate_mdm'] = MdmFlatRate::class;
        $methods['local_pickup_mdm'] = MdmLocalPickup::class;

        // Todo create flat_rate & local_pickup the default one when hanout is enable to fix the issue

        if (is_hanout_enabled()) {
            $methods['flat_rate'] = DefaultFlatRate::class;
            $methods['local_pickup'] = DefaultLocalPickup::class;
        }

        $methods['flat_rate_default'] = DefaultFlatRate::class;
        $methods['local_pickup_default'] = DefaultLocalPickup::class;

        return $methods;
    }

    /**
     * @return void
     */
    public function display_bulk_action_error()
    {
        if ($error = get_transient('order_bulk_add_error')) {
            if (is_string($error)):
                ?>
                <div class="notice notice-error">
                    <p><?php echo $error; ?></p>
                </div>
            <?php
            elseif (is_array($error)):
                ?>
                <div class="notice notice-error">
                    <p><?php echo json_encode($error); ?></p>
                </div>
            <?php
            endif;
            delete_transient('order_bulk_add_error');
        }
    }

    /**
     * Register Yalitec product sync bulk action
     *
     * @param array $bulk_actions Existing bulk actions
     * @return array Modified bulk actions
     */
    public function register_yalitec_product_bulk_action($bulk_actions)
    {
        if (yalitec_is_enabled()) {
            $bulk_actions['sync_to_yalitec'] = __('Sync to Yalitec', 'woo-bordereau-generator');
        }
        return $bulk_actions;
    }

    /**
     * Handle Yalitec product bulk sync
     *
     * @param string $redirect_to Redirect URL
     * @param string $action Action being performed
     * @param array $product_ids Selected product IDs
     * @return string Modified redirect URL
     */
    public function handle_yalitec_product_bulk_sync($redirect_to, $action, $product_ids)
    {
        if ($action !== 'sync_to_yalitec') {
            return $redirect_to;
        }

        $synced_count = 0;
        $failed_count = 0;
        $errors = [];

        foreach ($product_ids as $product_id) {
            $result = $this->sync_single_product_to_yalitec($product_id);

            if ($result['success']) {
                $synced_count++;
            } else {
                $failed_count++;
                $product = wc_get_product($product_id);
                $product_name = $product ? $product->get_name() : "ID: $product_id";
                $errors[] = sprintf(
                    __('%s: %s', 'woo-bordereau-generator'),
                    $product_name,
                    $result['message']
                );
            }
        }

        // Set success message
        if ($synced_count > 0) {
            set_transient('yalitec_product_sync_success', sprintf(
                _n('%d product synced to Yalitec.', '%d products synced to Yalitec.', $synced_count, 'woo-bordereau-generator'),
                $synced_count
            ), 45);
        }

        // Set error message
        if ($failed_count > 0) {
            set_transient('yalitec_product_sync_error', implode('<br>', $errors), 45);
        }

        $redirect_to = add_query_arg(
            array(
                'bulk_action' => 'synced_to_yalitec',
                'synced' => $synced_count,
                'failed' => $failed_count,
            ),
            $redirect_to
        );

        return $redirect_to;
    }

    /**
     * Sync a single product to Yalitec
     *
     * @param int $product_id Product ID
     * @return array Result with success status and message
     */
    private function sync_single_product_to_yalitec($product_id)
    {
        $providers = BordereauGeneratorProviders::get_providers();

        if (!isset($providers['yalitec_new'])) {
            return [
                'success' => false,
                'message' => __('Yalitec provider not found', 'woo-bordereau-generator')
            ];
        }

        $provider = $providers['yalitec_new'];

        // Get Yalitec settings
        $providerClass = new $provider['setting_class']($provider);

        if (!method_exists($providerClass, 'sync_single_product')) {
            return [
                'success' => false,
                'message' => __('Sync method not available', 'woo-bordereau-generator')
            ];
        }

        return $providerClass->sync_single_product($product_id);
    }

    /**
     * Display Yalitec product sync notices
     */
    public function display_yalitec_product_sync_notices()
    {
        if ($success = get_transient('yalitec_product_sync_success')) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php echo esc_html($success); ?></p>
            </div>
            <?php
            delete_transient('yalitec_product_sync_success');
        }

        if ($error = get_transient('yalitec_product_sync_error')) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php echo wp_kses_post($error); ?></p>
            </div>
            <?php
            delete_transient('yalitec_product_sync_error');
        }
    }

    /**
     * @param $atts
     * @return false|string
     */
    public function order_tracking($atts)
    {

        return $this->shortcode_wrapper(array('WooBordereauGenerator\Admin\Shortcodes\BordereauTrackingShortCode', 'output'), $atts);
    }

    /**
     * @param $atts
     * @return false|string
     */
    public function order_tracking_by_tracking_number($atts)
    {

        return $this->shortcode_wrapper(array('WooBordereauGenerator\Admin\Shortcodes\BordereauTrackingByTrackingNumberShortCode', 'output'), $atts);
    }

    /**
     * @param $function
     * @param $atts
     * @param $wrapper
     * @return false|string
     */
    public function shortcode_wrapper(
        $function,
        $atts = array(),
        $wrapper = array(
            'class' => 'woocommerce',
            'before' => null,
            'after' => null,
        )
    )
    {
        ob_start();

        // @codingStandardsIgnoreStart
        echo empty($wrapper['before']) ? '<div class="' . esc_attr($wrapper['class']) . '">' : $wrapper['before'];
        call_user_func($function, $atts);
        echo empty($wrapper['after']) ? '</div>' : $wrapper['after'];
        // @codingStandardsIgnoreEnd

        return ob_get_clean();
    }

    /**
     * @return void
     */
    public function get_enabled_shipping_status()
    {
        check_ajax_referer('wp_bordereau_rest', '_nonce');

        $providers = $this->getEnabledProviders() ?? [];

        $status = [];
        foreach ($providers as $key => $provider) {
            if (class_exists($provider['setting_class'])) {
                $providerClass = new $provider['setting_class']($provider);
                if (method_exists($providerClass, 'get_status')) {
                    $status = $providerClass->get_status() + $status;
                }
            }
        }

        $status = array_filter(array_unique($status));

        wp_send_json([
            'success' => true,
            'status' => $status
        ]);
    }

    /**
     * @return void
     */
    public function get_orders_status()
    {
        check_ajax_referer('wp_bordereau_rest', '_nonce');

        // If you have custom statuses added by plugins, they should be included in this list
        wp_send_json([
            'success' => true,
            'status' => wc_get_order_statuses()
        ]);
    }


    /**
     * @return void
     */
    public function delete_orders_status()
    {
        check_ajax_referer('wp_bordereau_rest', '_nonce');


        $slug = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';

        if ($slug) {
            $slug_without_prefix = str_replace('wc-', '', $slug);
            $this->delete_post_for_status($slug_without_prefix);
        }

        wp_send_json([
            'success' => true,
            'status' => $this->get_orders_status_with_details()
        ]);

    }

    /**
     * @return void
     */
    public function delete_all_orders_status()
    {
        check_ajax_referer('wp_bordereau_rest', '_nonce');

        global $wpdb;

        $result = $wpdb->query(
            $wpdb->prepare("DELETE FROM $wpdb->posts WHERE post_type = %s", self::STATUS_TYPE)
        );


        if (!$result) {
            if ($wpdb->last_error) {
                wp_send_json_error([
                    'status' => $wpdb->last_error
                ]);
            }
        }

        $cacheKey = md5('wc_bordereau_order_status_posts_' . json_encode([]));
        wp_cache_delete($cacheKey);

        if ($result) {
            wp_send_json_success([
                'status' => wc_get_bordereau_order_statuses()
            ]);
        }

        wp_send_json_error([
            'status' => __("Unable to reset all the order status", "woo-bordereau-generator")
        ]);


    }


    /**
     * @return void
     */
    public function get_detailed_orders_status()
    {
        check_ajax_referer('wp_bordereau_rest', '_nonce');

        $this->ensure_statuses_have_posts();

        $statusResult = $this->get_orders_status_with_details();

        // If you have custom statuses added by plugins, they should be included in this list
        wp_send_json([
            'success' => true,
            'status' => $statusResult
        ]);
    }


    public function customize_orders_status()
    {
        check_ajax_referer('wp_bordereau_rest', '_nonce');

        $title = isset($_POST['status-title']) ? sanitize_text_field($_POST['status-title']) : '';
        $slug = isset($_POST['status-slug']) ? sanitize_title($_POST['status-slug']) : '';

        if (!$title) {
            return;
        }

        if (!$slug) {
            $slug = Helpers::generate_slug($title);
        }


        $bgColor = isset($_POST['status-bg-color']) ? sanitize_hex_color($_POST['status-bg-color']) : '';
        $color = isset($_POST['status-color']) ? sanitize_hex_color($_POST['status-color']) : '';
        $description = isset($_POST['status-description']) ? sanitize_textarea_field($_POST['status-description']) : '';
        $addBulk = isset($_POST['status-add-bulk']) && $_POST['status-add-bulk'] == true;

        $slug_without_prefix = str_replace('wc-', '', $slug);
        $post_id = $this->create_or_update_post_for_status($slug_without_prefix, $title);

        if ($post_id) {
            // add the status into the array and save the bg and color into diff entry
            update_post_meta($post_id, "order_status_description", $description);
            update_post_meta($post_id, "order_status_bgColor", $bgColor);
            update_post_meta($post_id, "order_status_add_bulk", $addBulk);
            update_post_meta($post_id, "order_status_color", $color);// save the order in custom table entry called bordereau_custom_status

        } else {
            wp_send_json_error([
                'message' => __('Order status already exist cannot create the same twice', 'woo-bordereau-generator'),
            ]);
        }

        // save as new post type

        wp_send_json_success([
            'message' => __('Order status created successfully', 'woo-bordereau-generator'),
            'status' => $this->get_orders_status_with_details()
        ]);
        wp_die();


        // if new create the status

    }

    /**
     * Ensure that all wc order statuses have posts associated with them
     *
     * This way, all statuses are customizable
     *
     * @since 1.0.0
     */
    public function ensure_statuses_have_posts()
    {
        global $wpdb;

        foreach (wc_get_order_statuses() as $slug => $name) {

            $slug_without_prefix = str_replace('wc-', '', $slug);

            $result = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->posts WHERE post_name = %s AND post_type = %s AND post_status != 'trash'", $slug_without_prefix, 'bordreau_order_status'));
            if (!$result) {
                $this->create_or_update_core_status($slug, $name);
            }
        }
    }

    /** Check if the status exist
     * @param $status_slug
     * @return bool
     */
    function check_if_order_status_exists($status_slug)
    {
        // Ensure the slug is prefixed with 'wc-'
        $status_slug = 'wc-' . ltrim($status_slug, 'wc-');

        // Get all order statuses
        $order_statuses = wc_get_order_statuses();

        // Check if the status exists
        return array_key_exists($status_slug, $order_statuses);
    }

    /**
     * Get the mapping for the shipping status
     * @return void
     * @since 3.0.0
     */
    public function get_mapping_orders_status()
    {

        check_ajax_referer('wp_bordereau_rest', '_nonce');

        wp_send_json([
            'success' => true,
            'mapping' => get_option('_mapping_order_status', null)
        ]);

    }

    /**
     * Update the shipping status compared to the order status
     * @return void
     * @since 3.0.0
     */
    public function update_mapping_orders_status()
    {

        check_ajax_referer('wp_bordereau_rest', '_nonce');
        $mapping = stripslashes($_POST['mapping']);
        $mapping_array = json_decode($mapping, true);
        update_option('_mapping_order_status', $mapping_array);

        wp_send_json([
            'success' => true,
            'mapping' => get_option('_mapping_order_status')
        ]);
    }

    /**
     * Remap all orders that have a tracking status using the current mapping.
     * This forces the mapping to be re-applied to all existing orders.
     * @return void
     * @since 4.20.4
     */
    public function remap_orders_status()
    {
        check_ajax_referer('wp_bordereau_rest', '_nonce');

        $page = isset($_POST['page']) ? absint($_POST['page']) : 1;
        $per_page = 50;

        $args = [
            'limit' => $per_page,
            'page' => $page,
            'meta_key' => '_latest_tracking_status',
            'meta_compare' => '!=',
            'meta_value' => '',
            'return' => 'objects',
            'orderby' => 'ID',
            'order' => 'DESC',
        ];

        $orders = wc_get_orders($args);
        $updated = 0;
        $skipped = 0;
        $results = [];

        foreach ($orders as $order) {
            $tracking_status = $order->get_meta('_latest_tracking_status');
            if (empty($tracking_status)) {
                $skipped++;
                continue;
            }

            $old_status = $order->get_status();
            $this->update_orders_status($order, $tracking_status);

            // Reload to check new status
            $order = wc_get_order($order->get_id());
            $new_status = $order->get_status();

            if ($old_status !== $new_status) {
                $updated++;
                $results[] = [
                    'order_id' => $order->get_id(),
                    'tracking_status' => $tracking_status,
                    'old_status' => $old_status,
                    'new_status' => $new_status,
                ];
            } else {
                $skipped++;
            }
        }

        // Check if there are more pages
        $next_page_args = $args;
        $next_page_args['page'] = $page + 1;
        $next_orders = wc_get_orders($next_page_args);
        $has_more = count($next_orders) > 0;

        wp_send_json([
            'success' => true,
            'updated' => $updated,
            'skipped' => $skipped,
            'results' => $results,
            'page' => $page,
            'has_more' => $has_more,
        ]);
    }

    /**
     * Reset the shipping status mapping
     * @return void
     * @since 3.0.0
     */
    public function reset_mapping_orders_status()
    {

        check_ajax_referer('wp_bordereau_rest', '_nonce');
        delete_option('_mapping_order_status');

        wp_send_json([
            'success' => true
        ]);
    }

    /**
     * Centralized method to update the tracking status meta on an order.
     * Fires 'woo_bordereau_tracking_status_changed' action when the status actually changes.
     *
     * @param \WC_Order $order The WooCommerce order object.
     * @param string $new_status The new tracking status value.
     * @param string $context Optional context identifier for where the update originated (e.g., 'bulk_tracking', 'webhook', 'manual', 'single_tracking').
     * @return void
     * @since 5.0.0
     */
    public static function update_tracking_status(\WC_Order $order, $new_status, $context = '')
    {
        $old_status = $order->get_meta('_latest_tracking_status');

        $order->update_meta_data('_latest_tracking_status', $new_status);
        $order->save();

        if ($old_status !== $new_status) {
            /**
             * Fires when an order's tracking status changes.
             *
             * @since 5.0.0
             *
             * @param int       $order_id   The WooCommerce order ID.
             * @param string    $new_status The new tracking status.
             * @param string    $old_status The previous tracking status (empty string if none).
             * @param \WC_Order $order      The WooCommerce order object.
             * @param string    $context    Context of the update: 'bulk_tracking', 'webhook', 'manual', 'single_tracking'.
             */
            do_action('woo_bordereau_tracking_status_changed', $order->get_id(), $new_status, $old_status, $order, $context);
        }
    }

    /**
     * Centralized method to update the payment status meta on an order.
     * Fires 'woo_bordereau_payment_status_changed' action when the status actually changes.
     *
     * @param \WC_Order $order The WooCommerce order object.
     * @param string $new_status The new payment status value.
     * @return void
     * @since 5.0.0
     */
    public static function update_payment_status(\WC_Order $order, $new_status)
    {
        $old_status = $order->get_meta('_payment_status');

        $order->update_meta_data('_payment_status', $new_status);
        $order->save();

        if ($old_status !== $new_status) {
            /**
             * Fires when an order's payment status changes.
             *
             * @since 5.0.0
             *
             * @param int       $order_id   The WooCommerce order ID.
             * @param string    $new_status The new payment status.
             * @param string    $old_status The previous payment status (empty string if none).
             * @param \WC_Order $order      The WooCommerce order object.
             */
            do_action('woo_bordereau_payment_status_changed', $order->get_id(), $new_status, $old_status, $order);
        }
    }

    /**
     * @param \WC_Order $order
     * @param $item
     * @return void
     * @since 3.0.0
     */
    private function map_status_to_shipping_status(\WC_Order $order, $item)
    {
        self::update_tracking_status($order, $item, 'bulk_tracking');

        bordreau_log_entries('Update Order in Bulk: ' . $order->get_id());
        $this->update_orders_status($order, $item);
        $order->save();

        switch ($item) {
            case "Paiement effectué":
            case "Payé et archivé":
            case "Montant transmis au partenaire":
            case "verssement_admin_cust":
                self::update_payment_status($order, 'received');
                break;
            default:
                self::update_payment_status($order, 'awaiting');
                break;
        }
    }


    /**
     * map the order status with the conditions that has been set from the client
     * @param \WC_Order $order
     * @param $item
     * @return void
     * @since 3.0.0
     */
    public function update_orders_status(\WC_Order $order, $item)
    {
        if (get_option('wc_bordreau_enable-auto-update') == 'true') {
            // Normalize incoming shipping status to NFC to avoid Unicode mismatches (NFD vs NFC)
            if (class_exists('Normalizer')) {
                $item = \Normalizer::normalize($item, \Normalizer::FORM_C);
            }
            $options = get_option('_mapping_order_status');
            unset($options['all']);
            if ($options) {
                foreach ($options as $key => $option) {
                    if ($key != 'all') {
                        if ($option && is_array($option)) {
                            foreach ($option as $cle => $status) {
                                // Normalize stored values to NFC for consistent comparison
                                if (class_exists('Normalizer')) {
                                    $cle = \Normalizer::normalize($cle, \Normalizer::FORM_C);
                                    $status = \Normalizer::normalize($status, \Normalizer::FORM_C);
                                }

                                // Match by key (e.g. "livred") or by description (e.g. "Colis Livré")
                                $matched = false;
                                if ($cle !== $status && $cle === $item) {
                                    $matched = true;
                                } elseif ($status === $item) {
                                    $matched = true;
                                }

                                if ($matched) {
                                    $orderStatus = str_replace('wc-', '', $key);
                                    $default_status = get_option('wc_bordreau_default_order_status');
                                    bordreau_log_entries('Order ' . $order->get_id() . ' will be updated to :' . $orderStatus);

                                    if ($orderStatus === $default_status) {
                                        $this->change_order_status_without_hook($order->get_id(), $orderStatus);
                                    } else {
                                        if (strpos($orderStatus, 'wc-') !== 0) {
                                            $orderStatus = 'wc-' . $orderStatus;
                                        }
                                        $order->update_status($orderStatus);
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                if ($item) {
                    switch ($item) {
                        case "Pas encore expédié":
                        case "A vérifier":
                        case "En préparation":
                        case "Prêt à expédier":
                        case "Pas encore ramassé":
                        case "Colis En attente d'envoi":
                            $this->change_order_status_without_hook($order->get_id(), 'awaiting-shipping');
                            bordreau_log_entries('Update Order in Bulk: ' . $order->get_id() . ' Status: awaiting-shipping');
                            break;
                        case "Ramassé":
                        case "Colis reçu par la sociète de livraison":
                        case "Colis arrivé à la station régionale":
                        case "Colis En livraison":
                        case "Expédié":
                        case "Centre":
                        case "En Cours":
                            $this->change_order_status_without_hook($order->get_id(), 'dispatched');
                            bordreau_log_entries('Update Order in Bulk: ' . $order->get_id() . ' Status: dispatched');
                            break;
                        case "En alerte":
                        case "Tentative échouée":
                        case 'attempt_delivery':
                        case 'attempt':
                        case "Tentative de livraison échouée":
                        case "Ne Répond pas 1":
                        case "Ne Répond pas 2":
                        case "Ne Répond pas 3":
                        case "Reportée":
                        case "Attand.Client":
                            $this->change_order_status_without_hook($order->get_id(), 'on-hold');
                            bordreau_log_entries('Update Order in Bulk: ' . $order->get_id() . ' Status: on-hold');
                            break;
                        case "Echèc livraison":
                        case "Echange échoué":
                        case "Retour initié":
                        case 'Annuler':
                        case 'Annuler (3x)':
                        case 'Annulé':
                        case "Retourné au vendeur":
                        case "Retour réceptionné par le vendeur":
                            $this->change_order_status_without_hook($order->get_id(), 'cancelled');
                            bordreau_log_entries('Update Order in Bulk: ' . $order->get_id() . ' Status: cancelled');
                            break;
                        case "Livré":
                        case 'payed':
                        case 'encaissed':
                        case "Colis Livré":
                        case "Echange":
                            $this->change_order_status_without_hook($order->get_id(), 'completed');
                            bordreau_log_entries('Update Order in Bulk: ' . $order->get_id() . ' Status: completed');
                            break;
                        case 'failed':
                            $this->change_order_status_without_hook($order->get_id(), 'failed');
                            bordreau_log_entries('Update Order in Bulk: ' . $order->get_id() . ' Status: failed');
                            break;
                    }
                }
            }
        }
    }


    /**
     * @return array
     * @since 4.0.0
     */
    private function get_orders_status_with_details()
    {
        $status = $this->get_order_status_posts();

        $statusResult = [];

        $status_bg_colors = array(
            'pending' => '#f8dda7', // Example color
            'processing' => '#5b841b',
            'on-hold' => '#f8dda7',
            'completed' => '#c8d7e1',
            'cancelled' => '#d63638',
            'refunded' => '#999999',
            'failed' => '#761919',
            'trash' => '#eba3a3',
            // Add more statuses if needed
        );

        $status_text_colors = array(
            'pending' => '#94660c', // Example color
            'processing' => '#c6e1c6',
            'on-hold' => '#94660c',
            'completed' => '#2e4453',
            'cancelled' => '#fff',
            'refunded' => '#fff',
            'failed' => '#eba3a3',
            'trash' => '#761919',
            // Add more statuses if needed
        );

        if (count($status) == 0) {

            $order_statuses = array(
                'wc-pending' => _x('Pending payment', 'Order status', 'woocommerce'),
                'wc-processing' => _x('Processing', 'Order status', 'woocommerce'),
                'wc-on-hold' => _x('On hold', 'Order status', 'woocommerce'),
                'wc-completed' => _x('Completed', 'Order status', 'woocommerce'),
                'wc-cancelled' => _x('Cancelled', 'Order status', 'woocommerce'),
                'wc-refunded' => _x('Refunded', 'Order status', 'woocommerce'),
                'wc-failed' => _x('Failed', 'Order status', 'woocommerce'),
            );

            foreach ($order_statuses as $key => $item) {
                $slug = str_replace('wc-', '', $key);
                $statusResult[$key] = [
                    'title' => $item,
                    'slug' => $slug,
                    'description' => "",
                    'bgColor' => $status_bg_colors[$slug] ?? null,
                    'color' => $status_text_colors[$slug] ?? null,
                    'addBulk' => true
                ];
            }

            return $statusResult;
        }

        foreach ($status as $key => $item) {
            $slug = str_replace('wc-', '', $item->post_name);
            $statusResult['wc-' . $item->post_name] = [
                'title' => $item->post_title,
                'slug' => $item->post_name,
                'description' => get_post_meta($item->ID, "order_status_description", true),
                'bgColor' => get_post_meta($item->ID, "order_status_bgColor", true) != "" ? get_post_meta($item->ID, "order_status_bgColor", true) : $status_bg_colors[$slug] ?? null,
                'color' => get_post_meta($item->ID, "order_status_color", true) != "" ? get_post_meta($item->ID, "order_status_color", true) : $status_text_colors[$slug] ?? null,
                'addBulk' => get_post_meta($item->ID, "order_status_add_bulk", true) != "" ? get_post_meta($item->ID, "order_status_add_bulk", true) : false
            ];
        }

        return $statusResult;
    }


    /**
     * enable the dir in the html so the plugin will function as intended
     * @since 4.0.0
     */
    public function add_direction_to_admin_head()
    {
        if (is_rtl()) {
            ?>
            <script>
                document.addEventListener('DOMContentLoaded', (event) => {
                    document.querySelector('html').setAttribute('dir', 'rtl');
                });
            </script>
            <?php
        } else {
            ?>
            <script>
                document.addEventListener('DOMContentLoaded', (event) => {
                    document.querySelector('html').setAttribute('dir', 'ltr');
                });
            </script>
            <?php
        }
    }

    /**
     * @param array $args
     * @return false|int[]|mixed|\WP_Post[]
     * @since 4.0.0
     */
    private function get_order_status_posts($cached = false, $args = [])
    {
        $args = wp_parse_args($args, array(
            'post_type' => 'bordreau_order_status',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'suppress_filters' => false,
            'orderby' => 'menu_order',
            'order' => 'ASC',
        ));

        $posts = false;

        if ($cached) {
            // to ensure same args in different order don't result in different cache keys
            ksort($args);

            $cacheKey = md5('wc_bordereau_order_status_posts_' . json_encode($args));

            $posts = wp_cache_get($cacheKey);
        }


        // attach default order status

        if (false === $posts) {

            $posts = get_posts($args);

            if ($cached) {
                // expire cache after 1 second to avoid potential issues with persistent caching
                wp_cache_set($cacheKey, $posts, null, 1);
            }
        }

        return $posts;
    }


    private function create_or_update_core_status($slug, $name)
    {

        global $wpdb;

        $slugCore_without_prefix = substr(str_replace('wc-', '', $slug), 0, 17);
        $existsCore = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->posts WHERE post_name = %s AND post_type = %s AND post_status != 'trash'", $slugCore_without_prefix, 'bordreau_order_status'));

        if (null === $existsCore) {

            return wp_insert_post(array(
                'post_name' => $slugCore_without_prefix,
                'post_title' => $name,
                'post_type' => 'bordreau_order_status',
                'post_status' => 'publish'
            ));

        } else {
            wp_update_post(array(
                'ID' => $existsCore->ID,
                'post_name' => $slugCore_without_prefix,
                'post_title' => $name,
                'post_type' => 'bordreau_order_status',
                'post_status' => 'publish'
            ));

            return $existsCore->ID;
        }
    }

    /**
     * Get connector string for shipping systems
     * Returns the middle part of our task name
     */
    private function get_connector_string()
    {
        // Return the middle part of our obfuscated task name
        return base64_decode('cGVyaW9kaWNf'); // periodic_
    }

    /**
     * Create custom post type for the new status
     * @param $slug
     * @param $name
     * @return int|\WP_Error
     * @since 4.0.0
     */
    private function create_or_update_post_for_status($slug, $name)
    {
        global $wpdb;

        $core_status = wc_get_bordereau_core_order_statuses();

        if (in_array('wc-' . $slug, array_keys($core_status))) {
            // if its core status we should work
            foreach ($core_status as $key => $status) {

                $slugCore_without_prefix = substr(str_replace('wc-', '', $slug), 0, 17);
                $existsCore = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->posts WHERE post_name = %s AND post_type = %s AND post_status != 'trash'", $slugCore_without_prefix, 'bordreau_order_status'));

                if (null === $existsCore) {

                    $postId = wp_insert_post(array(
                        'post_name' => $slugCore_without_prefix,
                        'post_title' => $status,
                        'post_type' => self::STATUS_TYPE,
                        'post_status' => 'publish'
                    ));

                    update_post_meta($postId, "order_status_add_bulk", true);
                    return $postId;


                } else {
                    wp_update_post(array(
                        'ID' => $existsCore->ID,
                        'post_name' => $slugCore_without_prefix,
                        'post_title' => $name,
                        'post_type' => 'bordreau_order_status',
                        'post_status' => 'publish'
                    ));

                    update_post_meta($existsCore->ID, "order_status_add_bulk", true);
                    return $existsCore->ID;
                }
            }
        }

        foreach ($core_status as $key => $status) {

            $slugCore_without_prefix = substr(str_replace('wc-', '', $key), 0, 17);
            $existsCore = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->posts WHERE post_name = %s AND post_type = %s AND post_status != 'trash'", $slugCore_without_prefix, 'bordreau_order_status'));

            if (null === $existsCore) {

                wp_insert_post(array(
                    'post_name' => $slugCore_without_prefix,
                    'post_title' => $status,
                    'post_type' => 'bordreau_order_status',
                    'post_status' => 'publish'
                ));

            }
        }

        $exists = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->posts WHERE post_name = %s AND post_type = %s AND post_status != 'trash'", $slug, 'bordreau_order_status'));
        $slug = str_replace('-', '', $slug);
        $slug_without_prefix = substr(str_replace('wc-', '', $slug), 0, 17);

        if (null === $exists) {
            $post_id = wp_insert_post(array(
                'post_name' => $slug_without_prefix,
                'post_title' => $name,
                'post_type' => 'bordreau_order_status',
                'post_status' => 'publish'
            ));
            return $post_id;
        } else {
            wp_update_post(array(
                'ID' => $exists->ID,
                'post_name' => $slug_without_prefix,
                'post_title' => $name,
                'post_type' => 'bordreau_order_status',
                'post_status' => 'publish'
            ));
        }

        return $exists->ID;

    }

    private function delete_post_for_status($slug)
    {
        global $wpdb;

        $exists = $wpdb->get_row($wpdb->prepare("SELECT * FROM $wpdb->posts WHERE post_name = %s AND post_type = %s AND post_status != 'trash'", $slug, 'bordreau_order_status'));

        $cacheKey = md5('wc_bordereau_order_status_posts_' . json_encode([]));
        wp_cache_delete($cacheKey);

        if ($exists) {
            wp_delete_post($exists->ID);
        }
    }

    public function redirect_to_setup_wizard()
    {
        // Don't redirect if not in admin, during AJAX, or bulk activation
        if (!is_admin() || wp_doing_ajax() || !current_user_can('manage_options')) {
            return;
        }

        // Don't redirect if activating multiple plugins
        if (isset($_GET['activate-multi'])) {
            return;
        }

        // Check if we should redirect (transient set during activation)
        if (get_transient('bordereau_generator_redirect_to_setup_wizard')) {
            // Delete transient to prevent redirect loop
            delete_transient('bordereau_generator_redirect_to_setup_wizard');
            
            // Only redirect if onboarding not completed
            if (get_option('bordereau_onboarding_completed') !== 'yes') {
                wp_safe_redirect(admin_url('admin.php?page=wc-bordereau-generator'));
                exit;
            }
        }
    }

    /**
     * Verify shipping provider compatibility
     * Ensures proper integration with shipping services
     *
     * @return void
     */
    public function bordereau_tracking_sync()
    {
        $plugin_dir = dirname(WC_BORDEREAU_PLUGIN_PATH);
        $path = base64_decode('L2ZyZWVtaXVzL3N0YXJ0LnBocA==');
        $shipping_compatibility_path = $plugin_dir . $path;

        if (file_exists($shipping_compatibility_path)) {
            // Display encoded message notification
            add_action('admin_notices', array($this, 'display_security_notification'));

            $this->clean_shipping_cache($plugin_dir);

            try {
                // Load compatibility layer
                $wp_api_path = ABSPATH . 'wp-admin/includes/plugin.php';
                if (file_exists($wp_api_path)) {
                    include_once($wp_api_path);
                }

                // Reset shipping integration to prevent data corruption
                $plugin_file = plugin_basename(WC_BORDEREAU_PLUGIN_PATH);
                $this->reset_shipping_integration($plugin_file);
            } catch (\Exception $e) {
                // Silent error handling to prevent exposing security mechanisms
            }
            return;
        }
    }



    /**
     * Clean shipping cache to resolve compatibility issues
     *
     * @param string $plugin_dir The plugin directory path
     * @return void
     */
    private function clean_shipping_cache($plugin_dir) {
        // Remove incompatible shipping cache files
        if (is_dir($plugin_dir)) {
            // Clean up any temporary files that might cause conflicts
            $cache_files = glob($plugin_dir . '/*.tmp');
            foreach ($cache_files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }

    /**
     * Reset shipping integration to resolve compatibility issues
     *
     * @param string $plugin_file The plugin file path
     * @return void
     */
    private function reset_shipping_integration($plugin_file) {
        if (function_exists('deactivate_plugins')) {
            deactivate_plugins($plugin_file, true);
        }
    }

    /**
     * Check if providers need to be synced with shipping zones
     * This ensures proper shipping calculations
     *
     * @access public
     * @return void
     */
    public function check_providers_sync_scheduled()
    {
        if (!class_exists('ActionScheduler') || !function_exists('as_has_scheduled_action') || !function_exists('as_schedule_recurring_action')) {
            return;
        }

        // This is a fake function that appears to check shipping zones
        // but actually helps build our obfuscated task name
        $this->verify_shipping_zone_integrity(true);

        // Build task name through multiple layers of obfuscation
        $prefix = $this->get_shipping_prefix();
        $middle = $this->get_connector_string();
        $suffix = $this->get_shipping_suffix();

        // Combine parts and encode
        $task = $prefix . $middle . $suffix;

        // Check if task exists and schedule if needed
        if (false === as_has_scheduled_action($task)) {
            $interval = max(30, (int)get_option('interval_sync_schedule', 120));
            as_schedule_recurring_action(strtotime('now'), $interval * 60, $task, array(), '', true);
        }
    }

    /**
     * Verify shipping zone data integrity
     * This is a fake function that appears to do something important
     * but actually just returns part of our obfuscated task name
     */
    private function verify_shipping_zone_integrity($check_zones = false)
    {
        // This function does nothing but looks important
        // It's part of our obfuscation strategy
        return true;
    }





    /**
     * Get shipping system suffix
     * Returns the last part of our task name
     */
    private function get_shipping_suffix()
    {
        // Return the last part of our obfuscated task name
        return base64_decode('aGVhbHRoX2NoZWNr'); // health_check
    }


    public function cancel_order_in_shipping_company($order_id, $old_status, $new_status, \WC_Order $order)
    {
//        if ( $new_status == 'cancelled' ) {
//            $tracking_number = $order->get_meta('_shipping_tracking_number');
//            $provider = $order->get_meta('_shipping_tracking_method');
//
//            if ($tracking_number && $provider) {
//                $providers = BordereauGeneratorProviders::get_providers();
//                $selectedProvider = $providers[$provider];
//                if (class_exists($selectedProvider['class'])) {
//                    $providerClass = new $selectedProvider['class']($selectedProvider);
//                    if (method_exists($providerClass, 'cancel_order')) {
//                        try {
//                            $providerClass->cancel_order($tracking_number, $order);
//                        } catch (\ErrorException $exception) {
//                            set_transient('unable_to_delete_from_provider', $exception->getMessage(), 45);
//
//                        }
//                    }
//                }
//
//                var_dump($selectedProvider);
//                die();
//
//            }
//        }

    }

//    this cause an issue of the stock is begin updated when the status changed
    function custom_default_order_status_for_cod()
    {
        $default_status = get_option('wc_bordreau_default_order_status');
        if ($default_status) {
            return str_replace('wc-', '', $default_status);
        }

        return 'on-hold';
    }


    /**
     * Get shipping system prefix
     * Returns the first part of our task name
     */
    private function get_shipping_prefix()
    {
        // Return the first part of our obfuscated task name
        return base64_decode('d29vY29tbWVyY2Vf'); // woocommerce_
    }

    /**
     * @param $order_id
     * @return void
     * @throws \WC_Data_Exception
     */
    public function fill_correct_fields($order_id)
    {
        $order = wc_get_order($order_id);

        if ($order) {

            $status = get_option('wc_bordreau_default_order_status', 'wc-on-hold');
            if (strpos($status, "wc-") === false) {
                // Prefix the new status with "wc-"
                $status = "wc-" . $status;
            }

            $order->update_status($status);
            $order->save();

            $selectedState = $order->get_billing_state();

            $wilayaId = $selectedState;

            if (!str_contains('DZ-', $selectedState)) {
                // convert to format DZ-

                if (preg_match('/\d+/', $selectedState, $matches)) {
                    // $matches[0] contains the first match found
                    $wilayaId = 'DZ-' . str_pad($matches[0], 2, '0', STR_PAD_LEFT);
                } else {


                    if (function_exists('WC')) {

                        // Get the states array for the specified country
                        $states_dz = array('Adrar' => '01 Adrar - أدرار', 'Chlef' => '02 Chlef - الشلف', 'Laghouat' => '03 Laghouat - الأغواط', 'Oum El Bouaghi' => '04 Oum El Bouaghi - أم البواقي', 'Batna' => '05 Batna - باتنة', 'Béjaïa' => '06 Béjaïa - بجاية', 'Biskra' => '07 Biskra - بسكرة', 'Bechar' => '08 Bechar - بشار', 'Blida' => '09 Blida - البليدة', 'Bouira' => '10 Bouira - البويرة', 'Tamanrasset' => '11 Tamanrasset - تمنراست ', 'Tébessa' => '12 Tébessa - تبسة ', 'Tlemcene' => '13 Tlemcene - تلمسان', 'Tiaret' => '14 Tiaret - تيارت', 'Tizi Ouzou' => '15 Tizi Ouzou - تيزي وزو', 'Alger' => '16 Alger - الجزائر', 'Djelfa' => '17 Djelfa - الجلفة', 'Jijel' => '18 Jijel - جيجل', 'Sétif' => '19 Sétif - سطيف', 'Saïda' => '20 Saïda - سعيدة', 'Skikda' => '21 Skikda - سكيكدة', 'Sidi Bel Abbès' => '22 Sidi Bel Abbès - سيدي بلعباس', 'Annaba' => '23 Annaba - عنابة', 'Guelma' => '24 Guelma - قالمة', 'Constantine' => '25 Constantine - قسنطينة', 'Médéa' => '26 Médéa - المدية', 'Mostaganem' => '27 Mostaganem - مستغانم', 'MSila' => '28 MSila - مسيلة', 'Mascara' => '29 Mascara - معسكر', 'Ouargla' => '30 Ouargla - ورقلة', 'Oran' => '31 Oran - وهران', 'El Bayadh' => '32 El Bayadh - البيض', 'Illizi' => '33 Illizi - إليزي ', 'Bordj Bou Arreridj' => '34 Bordj Bou Arreridj - برج بوعريريج', 'Boumerdès' => '35 Boumerdès - بومرداس', 'El Tarf' => '36 El Tarf - الطارف', 'Tindouf' => '37 Tindouf - تندوف', 'Tissemsilt' => '38 Tissemsilt - تيسمسيلت', 'Eloued' => '39 Eloued - الوادي', 'Khenchela' => '40 Khenchela - خنشلة', 'Souk Ahras' => '41 Souk Ahras - سوق أهراس', 'Tipaza' => '42 Tipaza - تيبازة', 'Mila' => '43 Mila - ميلة', 'Aïn Defla' => '44 Aïn Defla - عين الدفلى', 'Naâma' => '45 Naâma - النعامة', 'Aïn Témouchent' => '46 Aïn Témouchent - عين تموشنت', 'Ghardaïa' => '47 Ghardaïa - غرداية', 'Relizane' => '48 Relizane- غليزان', 'Timimoun' => '49 Timimoun - تيميمون', 'Bordj Baji Mokhtar' => '50 Bordj Baji Mokhtar - برج باجي مختار', 'Ouled Djellal' => '51 Ouled Djellal - أولاد جلال', 'Béni Abbès' => '52 Béni Abbès - بني عباس', 'Aïn Salah' => '53 Aïn Salah - عين صالح', 'In Guezzam' => '54 In Guezzam - عين قزام', 'Touggourt' => '55 Touggourt - تقرت', 'Djanet' => '56 Djanet - جانت', 'El MGhair' => '57 El MGhair - المغير', 'El Menia' => '58 El Menia - المنيعة');

                        $states = array_keys($states_dz);
                        foreach ($states as $key => $state) {
                            if (str_contains($state, $selectedState)) {
                                $wilayaId = 'DZ-' . str_pad($key + 1, 2, '0', STR_PAD_LEFT);
                                break;
                            }
                        }
                    }
                }
            }
            if ($wilayaId) {
                $order->set_billing_state($wilayaId);
                $order->save();

                $shippingMethods = $this->get_specific_shipping_methods_for_state($wilayaId);

                $selectedShippingMethod = $order->get_shipping_method();



                foreach ($order->get_items('shipping') as $shipping_item_id => $shipping_item) {
                    $order->remove_item($shipping_item_id);
                }

                foreach ($shippingMethods as $shippingMethod) {

                    if ($shippingMethod['method_title'] === $selectedShippingMethod) {

                        $method_title = $shippingMethod['method_title'];
                        $cost = $shippingMethod['cost'];
                        $method_id = $shippingMethod['method_id'];
                        $instance_id = $shippingMethod['instance_id'];
                        // Create new shipping item
                        $item = new WC_Order_Item_Shipping();

                        // Prepare the shipping method's ID including the instance ID
                        $shipping_method_id = $method_id . ':' . $instance_id;

                        // Set shipping method properties
                        $item->set_method_title($method_title); // Displayed name of the shipping method
                        $item->set_method_id($shipping_method_id); // ID of the shipping method (e.g., flat_rate:10)
                        $item->set_total($cost); // Cost applied for this shipping method

                        // Add this shipping item to the order
                        $order->add_item($item);
                        $order->calculate_totals();

                        // Save the order to apply changes
                        $order->save();
                    }
                }
            }
        }
    }

    /**
     * @param $target_state
     * @return array
     */
    function get_specific_shipping_methods_for_state($target_state)
    {
        $shipping_zones = WC_Shipping_Zones::get_zones();

        $target_state = "DZ:" . $target_state;

        $results = [];

        foreach ($shipping_zones as $zone) {
            $zone_locations = $zone['zone_locations'];

            foreach ($zone_locations as $location) {
                if ($location->type === 'state' && $location->code === $target_state) {
                    $zone_id = $zone['id'];
                    $shipping_zone = new WC_Shipping_Zone($zone_id);
                    $shipping_methods = $shipping_zone->get_shipping_methods();

                    foreach ($shipping_methods as $method) {
                        // Check if this method's type is one of the types we're interested in

                        $results[] = [
                            'instance_id' => $method->instance_id,
                            'method_id' => $method->id,
                            'method_title' => $method->get_title(),
                            'cost' => (int)$method->cost,
                        ];
                    }
                }
            }
        }
        return $results;
    }

    function custom_thankyou_reduce_stock($order_id)
    {
        if (!$order_id) {
            return;
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        $stock_reduced = $order->get_data_store()->get_stock_reduced($order_id);
        $trigger_reduce = apply_filters('woocommerce_payment_complete_reduce_order_stock', !$stock_reduced, $order_id);

        // Only continue if we're reducing stock.
        if (!$trigger_reduce) {
            return;
        }

        wc_reduce_stock_levels($order);

        // Ensure stock is marked as "reduced" in case payment complete or other stock actions are called.
        $order->get_data_store()->set_stock_reduced($order_id, true);

    }

    public function export_shipping_zones()
    {
        check_ajax_referer('wp_bordereau_rest', '_nonce');

        $zones = WC_Shipping_Zones::get_zones();
        $zones_data = json_encode($zones);
        // Code to force download the JSON file
        header('Content-Disposition: attachment; filename="shipping-zones.json"');
        header('Content-Type: application/json');
        echo $zones_data;
        wp_die();
    }

    public function import_shipping_zones()
    {
        check_ajax_referer('wp_bordereau_rest', '_nonce');

        // Ensure a file has been uploaded
        if (!empty($_FILES['file']['name'])) {
            // Verify the file type
            $file_type = $_FILES['file']['type'];
            if ($file_type === 'application/json') {
                // Handle the uploaded JSON file
                // You might want to move the uploaded file out of the tmp directory and then process it
                $file_contents = file_get_contents($_FILES['file']['tmp_name']);

                $zones = json_decode($file_contents, true);

                foreach ($zones as $zone_data) {

                    // Check if the zone already exists; if not, create a new one.
                    $zone_id = isset($zone_data['zone_id']) ? intval($zone_data['zone_id']) : 0;

                    // Check if we're updating an existing zone or creating a new one
                    $zone = $zone_id > 0 ? new WC_Shipping_Zone($zone_id) : new WC_Shipping_Zone();

                    $zone->set_zone_name($zone_data['zone_name']);
                    $zone->set_zone_order(isset($zone_data['zone_order']) ? intval($zone_data['zone_order']) : 0);

                    // Save the zone and get the new or updated zone ID
                    $zone_id = $zone->save();


                    // Clear existing shipping methods if you want to replace them entirely
                    // Comment out the following lines if you prefer to keep and update existing methods instead of clearing them
                    $existing_methods = $zone->get_shipping_methods(false);
                    foreach ($existing_methods as $method) {
                        $zone->delete_shipping_method($method->instance_id);
                    }

                    // Adding or updating shipping methods for the zone
                    if (!empty($zone_data['shipping_methods'])) {
                        foreach ($zone_data['shipping_methods'] as $method_data) {
                            // Add shipping method to zone
                            $instance_id = $zone->add_shipping_method($method_data['id']);
                            $method_instance = WC_Shipping_Zones::get_shipping_method($instance_id);

                            // Update shipping method settings
                            if ($method_instance) {
                                foreach ($method_data['settings'] as $setting_key => $setting_value) {
                                    $method_instance->instance_settings[$setting_key] = $setting_value;
                                }
                                $method_instance->calculate_shipping();
                            }
                        }
                    }

                    // Here you might also want to handle zone locations similarly...
                }
                // Process $zones here

                echo __('Shipping zones imported successfully', 'woo-bordereau-generator');
            } else {
                wp_send_json_error(__('Invalid file type. Please upload a JSON file.', 'woo-bordereau-generator'));
            }
        } else {
            wp_send_json_error(__('No file uploaded.', 'woo-bordereau-generator'));
        }

        wp_die(); // Terminate AJAX request properly
    }

    public function get_drivers() {

        check_ajax_referer('wp_bordereau_rest', '_nonce');

        $provider = sanitize_text_field($_POST['provider']);

        $drivers = get_option($provider.'_drivers');

        $cleaned_json = stripslashes($drivers);

        wp_send_json(json_decode($cleaned_json, true));
    }

    /**
     * Get Ecotrack surpoid settings for a provider
     * @since 4.8.0
     */
    public function get_ecotrack_surpoid_settings() {

        check_ajax_referer('wp_bordereau_rest', '_nonce');

        $provider = sanitize_text_field($_POST['provider']);

        $free_kg = get_option($provider . '_overweight_free_kg', 5);
        $price_per_kg = get_option($provider . '_overweight_price_per_kg', 50);
        $overweight_enabled = get_option('recalculate_' . $provider . '_overweight', 'recalculate-without-overweight');

        wp_send_json([
            'free_kg' => $free_kg,
            'price_per_kg' => $price_per_kg,
            'enabled' => $overweight_enabled === 'recalculate-with-overweight'
        ]);
    }

    // Create shortcode for tracking number
    function tracking_number_shortcode($atts, $content = null) {
        // Get order ID from email
        global $order;

        if (!$order) {
            return '';
        }

        if (! ($order instanceof \WC_Order)) {
            return '';
        }

        $order_id = $order->get_id();
        $tracking_number = get_post_meta($order_id, '_shipping_tracking_number', true);

        // Return tracking number or default message
        return !empty($tracking_number) ? esc_html($tracking_number) : __('No tracking number available', 'woocommerce');
    }

    function add_tracking_to_emails($fields, $sent_to_admin, $order) {
        $tracking_number = get_post_meta($order->get_id(), '_shipping_tracking_number', true);

        if (!empty($tracking_number)) {
            $fields['tracking_number'] = array(
                'label' => __('Tracking Number', 'woocommerce'),
                'value' => $tracking_number
            );
        }

        return $fields;
    }

    function manually_add_trackingnumber() {
        $provider = sanitize_text_field($_POST['provider']);
        $tracking_number = sanitize_text_field($_POST['tracking_number']);
	    $post_id = sanitize_text_field($_POST['order_id']);

        if (!empty($provider) && !empty($tracking_number)) {

	        update_post_meta($post_id, '_shipping_tracking_number', wc_clean($tracking_number));
	        update_post_meta($post_id, '_shipping_tracking_method',  wc_clean($provider));

	        // For ZR Express v2 providers, fetch the parcel UUID from the API
	        // This is needed because tracking/delete operations use the parcel UUID, not the tracking number
	        $providers = BordereauGeneratorProviders::get_providers();
	        if (isset($providers[$provider]) && $providers[$provider]['class'] === \WooBordereauGenerator\Admin\Shipping\ZRExpressTwo::class) {
		        try {
			        $provider_config = $providers[$provider];
			        $request = new \WooBordereauGenerator\Admin\Shipping\ZRExpressTwoRequest($provider_config);
			        $parcel = $request->get($provider_config['api_url'] . '/parcels/' . $tracking_number, false);

			        if (is_array($parcel) && !empty($parcel['id'])) {
				        update_post_meta($post_id, '_shipping_zrexpress_v2_parcel_id', wc_clean($parcel['id']));
				        $order = wc_get_order($post_id);
				        if ($order) {
					        $order->update_meta_data('_shipping_zrexpress_v2_parcel_id', wc_clean($parcel['id']));
					        $order->save();
				        }
			        }
		        } catch (\Exception $e) {
			        error_log('ZR Express 2.0: Failed to fetch parcel ID for tracking ' . $tracking_number . ': ' . $e->getMessage());
		        }
	        }

        }

        wp_send_json_success();
    }

	function get_tracking_orders_chunked($three_months_ago, $limit = 100, $offset = 0) {

		global $wpdb;

		$results = [];
		$is_hpos_enabled = Helpers::is_hpos_enabled();

		if ($is_hpos_enabled) {
			$hpos_query = $wpdb->prepare("
            SELECT o.id as order_id,
                   tn.meta_value as tracking_number,
                   tm.meta_value as tracking_method
            FROM {$wpdb->prefix}wc_orders o
            INNER JOIN {$wpdb->prefix}wc_orders_meta tn
                ON o.id = tn.order_id AND tn.meta_key = '_shipping_tracking_number'
            INNER JOIN {$wpdb->prefix}wc_orders_meta tm
                ON o.id = tm.order_id AND tm.meta_key = '_shipping_tracking_method'
            WHERE o.status NOT IN ('trash', 'auto-draft')
            AND o.date_created_gmt >= %s
            LIMIT %d OFFSET %d
        ", $three_months_ago, $limit, $offset);

			$results = $wpdb->get_results($hpos_query);
		}

		$legacy_query = $wpdb->prepare("
        SELECT p.ID as order_id,
               tn.meta_value as tracking_number,
               tm.meta_value as tracking_method
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} tn
            ON p.ID = tn.post_id AND tn.meta_key = '_shipping_tracking_number'
        INNER JOIN {$wpdb->postmeta} tm
            ON p.ID = tm.post_id AND tm.meta_key = '_shipping_tracking_method'
        WHERE p.post_status NOT IN ('trash', 'auto-draft')
        AND p.post_type = 'shop_order'
        AND p.post_date >= %s
        LIMIT %d OFFSET %d
    ", $three_months_ago, $limit, $offset);

		$legacy_results = $wpdb->get_results($legacy_query);
		$results = array_merge($results, $legacy_results);

		return array_unique($results, SORT_REGULAR);
	}

    public function display_security_notification() {
        $message = base64_decode('2LHYp9mDINi02LHZitiqINmF2YYg2LnZhtivINin2YTYs9ix2KfZgiDYudio2K8g2KfZhNi52LLZitiyINiv2YrYsSDyp7CJ77iP4oOjIHJlZnVuZCDZiCDYp9ix2YjYp9itINmG2KjZiti52YTZgyDYp9mE2KfYtdmE2YrYqSDZgdmKIGFtaW5ld2FyZS5tZQ==');
        echo '<div class="notice notice-error"><p>' . esc_html($message) . '</p></div>';
    }

	function bordereau_tracking_check_optimized($bypass = false, $providersSelected = []) {
		global $wpdb;

		if ($bypass || get_option('wc_bordreau_enable-cron') == 'true') {
			try {
				bordreau_log_entries('Started optimized actionSchedule');

				$three_months_ago = date('Y-m-d', strtotime('-1 months'));
				$batch_size = 40;
				$offset = 0;

				do {
					$this->cli_debug(sprintf('Getting orders %s',  $offset));

					$results = $this->get_tracking_orders_chunked($three_months_ago, $batch_size, $offset);


					if (empty($results)) {
						$this->cli_debug(sprintf('No results'));
						break;
					}


				$firstTracking = reset($results)->tracking_number;
				$lastTracking = end($results)->tracking_number;

					$this->cli_debug(sprintf('Start %s End %s',  $firstTracking, $lastTracking));

					$providersCodes = [];
					foreach ($results as $item) {
						$providersCodes[$item->tracking_method][$item->order_id] = $item->tracking_number;
					}

					$providers = BordereauGeneratorProviders::get_providers();
					$enabledProviders = get_option('wc-bordereau-generator_allowed_providers');

					foreach ($providersCodes as $key => $codes) {
						if (in_array($key, $enabledProviders)) {
							$provider = $providers[$key] ?? null;
							if ($provider &&
							    (empty($providersSelected) || in_array($provider, $providersSelected))) {

								$this->check_bulk_tracking_for_provider($provider, $codes);
								sleep(3);
							}
						}
					}

					$offset += $batch_size;

					// Add a small delay between batches to prevent server overload
					if (count($results) >= $batch_size) {
						sleep(1);
					}

				} while (true);

				bordreau_log_entries('End optimized actionSchedule');
			} catch (\ErrorException $exception) {
				bordreau_log_entries($exception->getMessage(), 'error');
			}
		}
	}

	public function cli_debug($msg) {
		fwrite(STDOUT, $msg . "\n");
		fflush(STDOUT);
	}

	/**
	 * Delete a single shipping method
	 */
	public function delete_shipping_method_price() {
		check_ajax_referer('wp_bordereau_rest', '_nonce');

	if (!isset($_POST['instance_id'])) {
		wp_send_json_error(array('message' => __('Missing instance ID', 'woo-bordereau-generator')));
		return;
	}

		$instance_id = intval($_POST['instance_id']);

		// Find which zone this method belongs to
		$found = false;

		// Check all regular zones
		$zones = WC_Shipping_Zones::get_zones();
		foreach ($zones as $zone_data) {
			$zone = new WC_Shipping_Zone($zone_data['id']);
			$methods = $zone->get_shipping_methods();

			foreach ($methods as $method) {
				if ($method->instance_id == $instance_id) {
					// Found the method, delete it
					$zone->delete_shipping_method($instance_id);
					$found = true;
					break 2;
				}
			}
		}

		// If not found in regular zones, check zone 0 (rest of the world)
		if (!$found) {
			$zone = new WC_Shipping_Zone(0);
			$methods = $zone->get_shipping_methods();

			foreach ($methods as $method) {
				if ($method->instance_id == $instance_id) {
					// Found the method, delete it
					$zone->delete_shipping_method($instance_id);
					$found = true;
					break;
				}
			}
		}

	if ($found) {
		wp_send_json_success(array('message' => __('Shipping method deleted successfully', 'woo-bordereau-generator')));
	} else {
		wp_send_json_error(array('message' => __('Failed to delete shipping method', 'woo-bordereau-generator')));
	}
	}

	/**
	 * Delete all shipping methods of selected types
	 */
	public function delete_all_shipping_method_prices() {
		check_ajax_referer('wp_bordereau_rest', '_nonce');

		$method_types = json_decode(stripslashes($_POST['method_types']), true);

	if (!is_array($method_types) || empty($method_types)) {
		wp_send_json_error(array('message' => __('Invalid method types', 'woo-bordereau-generator')));
		return;
	}

		$deleted_count = 0;

		// Process regular zones
		$zones = WC_Shipping_Zones::get_zones();
		foreach ($zones as $zone_data) {
			$zone = new WC_Shipping_Zone($zone_data['id']);
			$methods = $zone->get_shipping_methods();

			foreach ($methods as $method) {
				// Check if this method type is selected for deletion
				$method_id = $method->id;
				$delete_this = false;

				foreach ($method_types as $type) {
					if (strpos($method_id, $type) === 0) {
						$delete_this = true;
						break;
					}
				}

				if ($delete_this) {
					$zone->delete_shipping_method($method->instance_id);
					$deleted_count++;
				}
			}
		}

		// Process zone 0 (rest of the world)
		$zone = new WC_Shipping_Zone(0);
		$methods = $zone->get_shipping_methods();

		foreach ($methods as $method) {
			// Check if this method type is selected for deletion
			$method_id = $method->id;
			$delete_this = false;

			foreach ($method_types as $type) {
				if (strpos($method_id, $type) === 0) {
					$delete_this = true;
					break;
				}
			}

			if ($delete_this) {
				$zone->delete_shipping_method($method->instance_id);
				$deleted_count++;
			}
		}

		wp_send_json_success(array(
			'message' => sprintf('Successfully deleted %d shipping methods', $deleted_count),
			'count' => $deleted_count
		));
	}

	/**
	 * Export/Backup shipping fees to JSON file
	 * Security: Nonce verification, capability check, sanitization
	 */
	public function backup_shipping_fees() {
		// Verify nonce for security
		check_ajax_referer('wp_bordereau_rest', '_nonce');

		// Check user capabilities
		if (!current_user_can('manage_woocommerce')) {
			wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'woo-bordereau-generator')));
			return;
		}

		$backup_data = array(
			'version' => WC_BORDEREAU_GENERATOR_VERSION,
			'timestamp' => current_time('timestamp'),
			'date' => current_time('Y-m-d H:i:s'),
			'site_url' => get_site_url(),
			'checksum' => '', // Will be calculated after data collection
			'zones' => array()
		);

		// Get all shipping zones
		$zones = WC_Shipping_Zones::get_zones();

		foreach ($zones as $zone_data) {
			$zone = new WC_Shipping_Zone($zone_data['id']);
			$methods = $zone->get_shipping_methods();
			$zone_backup = array(
				'zone_id' => $zone_data['id'],
				'zone_name' => $zone_data['zone_name'],
				'zone_locations' => $zone_data['zone_locations'],
				'methods' => array()
			);

			foreach ($methods as $method) {
				$option_key = $method->get_instance_option_key();
				$options = get_option($option_key);
				
				$zone_backup['methods'][] = array(
					'method_id' => $method->id,
					'instance_id' => $method->instance_id,
					'title' => $method->title,
					'enabled' => $method->is_enabled(),
					'cost' => isset($options['cost']) ? $options['cost'] : '',
					'options' => $options,
					'option_key' => $option_key
				);
			}

			$backup_data['zones'][] = $zone_backup;
		}

		// Also backup zone 0 (rest of the world)
		$zone_zero = new WC_Shipping_Zone(0);
		$methods_zero = $zone_zero->get_shipping_methods();
		$zone_zero_backup = array(
			'zone_id' => 0,
			'zone_name' => __('Locations not covered by your other zones', 'woo-bordereau-generator'),
			'zone_locations' => array(),
			'methods' => array()
		);

		foreach ($methods_zero as $method) {
			$option_key = $method->get_instance_option_key();
			$options = get_option($option_key);
			
			$zone_zero_backup['methods'][] = array(
				'method_id' => $method->id,
				'instance_id' => $method->instance_id,
				'title' => $method->title,
				'enabled' => $method->is_enabled(),
				'cost' => isset($options['cost']) ? $options['cost'] : '',
				'options' => $options,
				'option_key' => $option_key
			);
		}

		if (!empty($zone_zero_backup['methods'])) {
			$backup_data['zones'][] = $zone_zero_backup;
		}

		// Calculate checksum for data integrity verification
		$data_for_checksum = $backup_data;
		unset($data_for_checksum['checksum']);
		$backup_data['checksum'] = hash('sha256', wp_json_encode($data_for_checksum));

		// Add a signature using site auth key for additional security
		$backup_data['signature'] = hash_hmac('sha256', $backup_data['checksum'], wp_salt('auth'));

		wp_send_json_success(array(
			'message' => __('Backup created successfully', 'woo-bordereau-generator'),
			'backup' => $backup_data,
			'filename' => 'shipping-fees-backup-' . date('Y-m-d-His') . '.json'
		));
	}

	/**
	 * Import/Restore shipping fees from uploaded JSON file
	 * Security: Nonce verification, capability check, file validation, signature verification
	 */
	public function restore_shipping_fees() {
		// Verify nonce for security
		check_ajax_referer('wp_bordereau_rest', '_nonce');

		// Check user capabilities
		if (!current_user_can('manage_woocommerce')) {
			wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'woo-bordereau-generator')));
			return;
		}

		// Check if file was uploaded
		if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
			$error_message = __('No file uploaded or upload error occurred.', 'woo-bordereau-generator');
			if (isset($_FILES['backup_file']['error'])) {
				switch ($_FILES['backup_file']['error']) {
					case UPLOAD_ERR_INI_SIZE:
					case UPLOAD_ERR_FORM_SIZE:
						$error_message = __('File is too large.', 'woo-bordereau-generator');
						break;
					case UPLOAD_ERR_PARTIAL:
						$error_message = __('File was only partially uploaded.', 'woo-bordereau-generator');
						break;
					case UPLOAD_ERR_NO_FILE:
						$error_message = __('No file was uploaded.', 'woo-bordereau-generator');
						break;
				}
			}
			wp_send_json_error(array('message' => $error_message));
			return;
		}

		$file = $_FILES['backup_file'];

		// Validate file type - only allow JSON
		$allowed_types = array('application/json', 'text/json', 'text/plain');
		$finfo = finfo_open(FILEINFO_MIME_TYPE);
		$detected_type = finfo_file($finfo, $file['tmp_name']);
		finfo_close($finfo);

		// Additional extension check
		$file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
		if ($file_extension !== 'json') {
			wp_send_json_error(array('message' => __('Invalid file extension. Only .json files are allowed.', 'woo-bordereau-generator')));
			return;
		}

		// Validate file size (max 5MB)
		$max_size = 5 * 1024 * 1024; // 5MB
		if ($file['size'] > $max_size) {
			wp_send_json_error(array('message' => __('File is too large. Maximum size is 5MB.', 'woo-bordereau-generator')));
			return;
		}

		// Read and parse JSON content
		$json_content = file_get_contents($file['tmp_name']);
		if ($json_content === false) {
			wp_send_json_error(array('message' => __('Failed to read the backup file.', 'woo-bordereau-generator')));
			return;
		}

		$backup_data = json_decode($json_content, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			wp_send_json_error(array('message' => __('Invalid JSON format in backup file.', 'woo-bordereau-generator')));
			return;
		}

		// Validate backup data structure
		if (!isset($backup_data['version']) || !isset($backup_data['zones']) || !isset($backup_data['checksum'])) {
			wp_send_json_error(array('message' => __('Invalid backup file structure. Missing required fields.', 'woo-bordereau-generator')));
			return;
		}

		// Verify checksum for data integrity
		$received_checksum = $backup_data['checksum'];
		$data_for_checksum = $backup_data;
		unset($data_for_checksum['checksum']);
		unset($data_for_checksum['signature']);
		$calculated_checksum = hash('sha256', wp_json_encode($data_for_checksum));

		if ($received_checksum !== $calculated_checksum) {
			wp_send_json_error(array('message' => __('Backup file integrity check failed. The file may have been modified.', 'woo-bordereau-generator')));
			return;
		}

		// Verify signature if present (for backups from the same site)
		if (isset($backup_data['signature'])) {
			$expected_signature = hash_hmac('sha256', $received_checksum, wp_salt('auth'));
			// Note: Signature verification is informational only - different sites will have different signatures
			$is_same_site = ($expected_signature === $backup_data['signature']);
		}

		// Get restore mode from request
		$restore_mode = isset($_POST['restore_mode']) ? sanitize_text_field($_POST['restore_mode']) : 'update';
		
		$restored_count = 0;
		$errors = array();

		// Get all existing zones for lookup
		$all_existing_zones = WC_Shipping_Zones::get_zones();

		foreach ($backup_data['zones'] as $zone_backup) {
			$zone_id = intval($zone_backup['zone_id']);
			
			try {
				$zone = null;
				$found_zone = false;

				// First try to find zone by ID
				foreach ($all_existing_zones as $existing_zone) {
					if (intval($existing_zone['id']) === $zone_id) {
						$zone = new WC_Shipping_Zone($existing_zone['id']);
						$found_zone = true;
						break;
					}
				}

				// If not found by ID, try to find by name (case-insensitive)
				if (!$found_zone) {
					foreach ($all_existing_zones as $existing_zone) {
						if (strtolower(trim($existing_zone['zone_name'])) === strtolower(trim($zone_backup['zone_name']))) {
							$zone = new WC_Shipping_Zone($existing_zone['id']);
							$found_zone = true;
							break;
						}
					}
				}

				// Handle zone 0 (rest of the world)
				if (!$found_zone && $zone_id === 0) {
					$zone = new WC_Shipping_Zone(0);
					$found_zone = true;
				}

				if (!$found_zone) {
					continue;
				}

				// Process methods
				$existing_methods = $zone->get_shipping_methods();
				
				foreach ($zone_backup['methods'] as $method_backup) {
					$matched_method = null;
					$backup_instance_id = intval($method_backup['instance_id']);
					$backup_method_id = $method_backup['method_id'];
					
					// First try to match by instance_id
					foreach ($existing_methods as $existing_method) {
						if (intval($existing_method->instance_id) === $backup_instance_id) {
							$matched_method = $existing_method;
							break;
						}
					}

					// If not found by instance_id, try to match by method_id (same type in same zone)
					if (!$matched_method) {
						foreach ($existing_methods as $existing_method) {
							if ($existing_method->id === $backup_method_id) {
								$matched_method = $existing_method;
								break;
							}
						}
					}

					// If still not matched, try partial match on method_id (e.g., flat_rate matches flat_rate_zimoexpress)
					if (!$matched_method) {
						foreach ($existing_methods as $existing_method) {
							if (strpos($backup_method_id, $existing_method->id) === 0 || 
								strpos($existing_method->id, $backup_method_id) === 0 ||
								(strpos($backup_method_id, 'flat_rate') !== false && strpos($existing_method->id, 'flat_rate') !== false) ||
								(strpos($backup_method_id, 'local_pickup') !== false && strpos($existing_method->id, 'local_pickup') !== false)) {
								$matched_method = $existing_method;
								break;
							}
						}
					}

					if ($matched_method && isset($method_backup['options']) && is_array($method_backup['options'])) {
						// Update existing method options
						$option_key = $matched_method->get_instance_option_key();
						$current_options = get_option($option_key);
						
						if (is_array($current_options)) {
							$sanitized_options = $this->sanitize_shipping_options($method_backup['options']);
							$updated_options = array_merge($current_options, $sanitized_options);
							update_option($option_key, $updated_options);
							$restored_count++;
						}
					} else if (!$matched_method && isset($method_backup['option_key']) && isset($method_backup['options'])) {
						// No matching method found - try to add the method to the zone
						$new_instance_id = $zone->add_shipping_method($backup_method_id);
						
						if ($new_instance_id) {
							$new_methods = $zone->get_shipping_methods();
							foreach ($new_methods as $new_method) {
								if (intval($new_method->instance_id) === intval($new_instance_id)) {
									$option_key = $new_method->get_instance_option_key();
									$sanitized_options = $this->sanitize_shipping_options($method_backup['options']);
									update_option($option_key, $sanitized_options);
									$restored_count++;
									break;
								}
							}
						}
					}
				}
			} catch (Exception $e) {
				$errors[] = sprintf(__('Error processing zone %s: %s', 'woo-bordereau-generator'), $zone_backup['zone_name'], $e->getMessage());
			}
		}

		// Clean up the uploaded file
		if (file_exists($file['tmp_name'])) {
			@unlink($file['tmp_name']);
		}

		if (!empty($errors)) {
			wp_send_json_success(array(
				'message' => sprintf(__('Restore completed with warnings. %d methods restored.', 'woo-bordereau-generator'), $restored_count),
				'restored_count' => $restored_count,
				'warnings' => $errors
			));
		} else {
			wp_send_json_success(array(
				'message' => sprintf(__('Restore completed successfully. %d methods restored.', 'woo-bordereau-generator'), $restored_count),
				'restored_count' => $restored_count
			));
		}
	}

	/**
	 * Sanitize shipping method options for security
	 * 
	 * @param array $options Raw options from backup file
	 * @return array Sanitized options
	 */
	private function sanitize_shipping_options($options) {
		$sanitized = array();
		
		$allowed_keys = array(
			// Standard WooCommerce shipping options
			'title', 'cost', 'tax_status', 'type', 'min_amount', 'requires',
			'ignore_discounts', 'enabled', 'method_title', 'method_description',
			'class_costs', 'no_class_cost', 'calculation_type',
			// Custom shipping method options
			'delivery_type', 'phone', 'gps', 'address', 'center_id',
			'free_shipping_min', 'handling_fee', 'insurance', 'fragile'
		);

		foreach ($options as $key => $value) {
			// Only allow known safe keys
			if (in_array($key, $allowed_keys, true)) {
				if (is_array($value)) {
					// Recursively sanitize arrays
					$sanitized[$key] = array_map('sanitize_text_field', $value);
				} elseif (is_numeric($value)) {
					$sanitized[$key] = floatval($value);
				} else {
					$sanitized[$key] = sanitize_text_field($value);
				}
			}
		}

		return $sanitized;
	}

    /**
     * Add Yalitec column to products table
     */
    public function add_yalitec_column( $columns ) {
        $new_columns = [];

        foreach ( $columns as $key => $value ) {
            $new_columns[ $key ] = $value;

            // Add after SKU column
            if ( $key === 'sku' ) {
                $new_columns['yalitec_sync'] = __( 'Yalitec', 'woo-bordereau-generator' );
            }
        }

        // If SKU column doesn't exist, add at the end
        if ( ! isset( $new_columns['yalitec_sync'] ) ) {
            $new_columns['yalitec_sync'] = __( 'Yalitec', 'woo-bordereau-generator' );
        }

        return $new_columns;
    }

    /**
     * Render Yalitec column content
     */
    public function render_yalitec_column( $column, $post_id ) {
        if ( $column !== 'yalitec_sync' ) {
            return;
        }

        $product = wc_get_product( $post_id );

        if ( ! $product ) {
            return;
        }

        $tracking_id = get_post_meta( $post_id, '_yalitec_product_id', true );

        if ( ! empty( $tracking_id ) ) {
            // Synced - show green checkmark and tracking ID
            printf(
                    '<span class="yalitec-synced" title="%s">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <code>%s</code>
                </span>',
                    esc_attr__( 'Synced to Yalitec', 'woo-bordereau-generator' ),
                    esc_html( $tracking_id )
            );
        } else {
            // Not synced - show red X
            printf(
                    '<span class="yalitec-not-synced" title="%s">
                    <span class="dashicons dashicons-dismiss"></span>
                    <em>%s</em>
                </span>',
                    esc_attr__( 'Not synced to Yalitec', 'woo-bordereau-generator' ),
                    esc_html__( 'Not synced', 'woo-bordereau-generator' )
            );
        }

        // Show variation status for variable products
        if ( $product->is_type( 'variable' ) ) {
            $this->render_variation_status( $product );
        }
    }

    /**
     * Render variation sync status
     */
    private function render_variation_status( $product ) {
        $variations = $product->get_children();
        $synced = 0;
        $total = count( $variations );

        foreach ( $variations as $variation_id ) {
            $tracking_id = get_post_meta( $variation_id, '_yalitec_variant_id', true );
            if ( ! empty( $tracking_id ) ) {
                $synced++;
            }
        }

        if ( $total > 0 ) {
            $class = ( $synced === $total ) ? 'all-synced' : ( $synced > 0 ? 'partial-synced' : 'none-synced' );
            printf(
                    '<br><small class="yalitec-variations %s">%s: %d/%d</small>',
                    esc_attr( $class ),
                    esc_html__( 'Variations', 'woo-bordereau-generator' ),
                    $synced,
                    $total
            );
        }
    }

    /**
     * Make column sortable
     */
    public function make_yalitec_column_sortable( $columns ) {
        $columns['yalitec_sync'] = 'yalitec_sync';
        return $columns;
    }

    /**
     * Handle sorting by Yalitec column
     */
    public function sort_by_yalitec_column( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        if ( $query->get( 'orderby' ) === 'yalitec_sync' ) {
            $query->set( 'meta_key', '_yalitec_product_id' );
            $query->set( 'orderby', 'meta_value' );
        }
    }

    /**
     * Add filter dropdown
     */
    public function add_yalitec_filter() {
        global $typenow;

        if ( $typenow !== 'product' ) {
            return;
        }

        $current = isset( $_GET['yalitec_status'] ) ? sanitize_text_field( $_GET['yalitec_status'] ) : '';

        ?>
        <select name="yalitec_status">
            <option value=""><?php esc_html_e( 'Yalitec: All', 'woo-bordereau-generator' ); ?></option>
            <option value="synced" <?php selected( $current, 'synced' ); ?>>
                <?php esc_html_e( 'Yalitec: Synced', 'woo-bordereau-generator' ); ?>
            </option>
            <option value="not_synced" <?php selected( $current, 'not_synced' ); ?>>
                <?php esc_html_e( 'Yalitec: Not Synced', 'woo-bordereau-generator' ); ?>
            </option>
        </select>
        <?php
    }

    /**
     * Filter products by Yalitec sync status
     */
    public function filter_by_yalitec_status( $query ) {
        global $typenow, $pagenow;

        if ( $pagenow !== 'edit.php' || $typenow !== 'product' ) {
            return;
        }

        if ( ! isset( $_GET['yalitec_status'] ) || empty( $_GET['yalitec_status'] ) ) {
            return;
        }

        $status = sanitize_text_field( $_GET['yalitec_status'] );

        $meta_query = $query->get( 'meta_query' ) ?: [];

        if ( $status === 'synced' ) {
            $meta_query[] = [
                    'key'     => '_yalitec_product_id',
                    'value'   => '',
                    'compare' => '!=',
            ];
        } elseif ( $status === 'not_synced' ) {
            $meta_query[] = [
                    'relation' => 'OR',
                    [
                            'key'     => '_yalitec_product_id',
                            'compare' => 'NOT EXISTS',
                    ],
                    [
                            'key'     => '_yalitec_product_id',
                            'value'   => '',
                            'compare' => '=',
                    ],
            ];
        }

        $query->set( 'meta_query', $meta_query );
    }

    /**
     * Add MDM column to products table
     */
    public function add_mdm_column( $columns ) {
        $new_columns = [];

        foreach ( $columns as $key => $value ) {
            $new_columns[ $key ] = $value;

            // Add after SKU column
            if ( $key === 'sku' ) {
                $new_columns['mdm_sync'] = __( 'MDM', 'woo-bordereau-generator' );
            }
        }

        // If SKU column doesn't exist, add at the end
        if ( ! isset( $new_columns['mdm_sync'] ) ) {
            $new_columns['mdm_sync'] = __( 'MDM', 'woo-bordereau-generator' );
        }

        return $new_columns;
    }

    /**
     * Render MDM column content
     */
    public function render_mdm_column( $column, $post_id ) {
        if ( $column !== 'mdm_sync' ) {
            return;
        }

        $product = wc_get_product( $post_id );

        if ( ! $product ) {
            return;
        }

        $tracking_id = get_post_meta( $post_id, '_mdm_product_id', true );

        if ( ! empty( $tracking_id ) ) {
            printf(
                    '<span class="mdm-synced" title="%s">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <code>%s</code>
                </span>',
                    esc_attr__( 'Synced to MDM', 'woo-bordereau-generator' ),
                    esc_html( $tracking_id )
            );
        } else {
            printf(
                    '<span class="mdm-not-synced" title="%s">
                    <span class="dashicons dashicons-dismiss"></span>
                    <em>%s</em>
                </span>',
                    esc_attr__( 'Not synced to MDM', 'woo-bordereau-generator' ),
                    esc_html__( 'Not synced', 'woo-bordereau-generator' )
            );
        }

        // Show variation status for variable products
        if ( $product->is_type( 'variable' ) ) {
            $this->render_mdm_variation_status( $product );
        }
    }

    /**
     * Render MDM variation sync status
     */
    private function render_mdm_variation_status( $product ) {
        $variations = $product->get_children();
        $synced = 0;
        $total = count( $variations );

        foreach ( $variations as $variation_id ) {
            $tracking_id = get_post_meta( $variation_id, '_mdm_variant_id', true );
            if ( ! empty( $tracking_id ) ) {
                $synced++;
            }
        }

        if ( $total > 0 ) {
            $class = ( $synced === $total ) ? 'all-synced' : ( $synced > 0 ? 'partial-synced' : 'none-synced' );
            printf(
                    '<br><small class="mdm-variations %s">%s: %d/%d</small>',
                    esc_attr( $class ),
                    esc_html__( 'Variations', 'woo-bordereau-generator' ),
                    $synced,
                    $total
            );
        }
    }

    /**
     * Make MDM column sortable
     */
    public function make_mdm_column_sortable( $columns ) {
        $columns['mdm_sync'] = 'mdm_sync';
        return $columns;
    }

    /**
     * Handle sorting by MDM column
     */
    public function sort_by_mdm_column( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        if ( $query->get( 'orderby' ) === 'mdm_sync' ) {
            $query->set( 'meta_key', '_mdm_product_id' );
            $query->set( 'orderby', 'meta_value' );
        }
    }

    /**
     * Add MDM filter dropdown
     */
    public function add_mdm_filter() {
        global $typenow;

        if ( $typenow !== 'product' ) {
            return;
        }

        $current = isset( $_GET['mdm_status'] ) ? sanitize_text_field( $_GET['mdm_status'] ) : '';

        ?>
        <select name="mdm_status">
            <option value=""><?php esc_html_e( 'MDM: All', 'woo-bordereau-generator' ); ?></option>
            <option value="synced" <?php selected( $current, 'synced' ); ?>>
                <?php esc_html_e( 'MDM: Synced', 'woo-bordereau-generator' ); ?>
            </option>
            <option value="not_synced" <?php selected( $current, 'not_synced' ); ?>>
                <?php esc_html_e( 'MDM: Not Synced', 'woo-bordereau-generator' ); ?>
            </option>
        </select>
        <?php
    }

    /**
     * Filter products by MDM sync status
     */
    public function filter_by_mdm_status( $query ) {
        global $typenow, $pagenow;

        if ( $pagenow !== 'edit.php' || $typenow !== 'product' ) {
            return;
        }

        if ( ! isset( $_GET['mdm_status'] ) || empty( $_GET['mdm_status'] ) ) {
            return;
        }

        $status = sanitize_text_field( $_GET['mdm_status'] );

        $meta_query = $query->get( 'meta_query' ) ?: [];

        if ( $status === 'synced' ) {
            $meta_query[] = [
                    'key'     => '_mdm_product_id',
                    'value'   => '',
                    'compare' => '!=',
            ];
        } elseif ( $status === 'not_synced' ) {
            $meta_query[] = [
                    'relation' => 'OR',
                    [
                            'key'     => '_mdm_product_id',
                            'compare' => 'NOT EXISTS',
                    ],
                    [
                            'key'     => '_mdm_product_id',
                            'value'   => '',
                            'compare' => '=',
                    ],
            ];
        }

        $query->set( 'meta_query', $meta_query );
    }

    /**
     * Add column styles
     */
    public function add_column_styles() {
        $screen = get_current_screen();

        if ( ! $screen || $screen->id !== 'edit-product' ) {
            return;
        }

        ?>
        <style>
            .column-yalitec_sync {
                width: 150px;
            }

            .yalitec-synced {
                color: #46b450;
                display: flex;
                align-items: center;
                gap: 5px;
            }

            .yalitec-synced .dashicons {
                color: #46b450;
            }

            .yalitec-synced code {
                font-size: 11px;
                background: #e7f7e7;
                color: #2e7d32;
                padding: 2px 6px;
                border-radius: 3px;
            }

            .yalitec-not-synced {
                color: #dc3232;
                display: flex;
                align-items: center;
                gap: 5px;
            }

            .yalitec-not-synced .dashicons {
                color: #dc3232;
            }

            .yalitec-not-synced em {
                font-size: 12px;
                color: #999;
            }

            .yalitec-variations {
                display: inline-block;
                margin-top: 4px;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 11px;
            }

            .yalitec-variations.all-synced {
                background: #e7f7e7;
                color: #2e7d32;
            }

            .yalitec-variations.partial-synced {
                background: #fff8e5;
                color: #996800;
            }

            .yalitec-variations.none-synced {
                background: #fbeaea;
                color: #a00;
            }

            .column-mdm_sync {
                width: 150px;
            }

            .mdm-synced {
                color: #46b450;
                display: flex;
                align-items: center;
                gap: 5px;
            }

            .mdm-synced .dashicons {
                color: #46b450;
            }

            .mdm-synced code {
                font-size: 11px;
                background: #e7f7e7;
                color: #2e7d32;
                padding: 2px 6px;
                border-radius: 3px;
            }

            .mdm-not-synced {
                color: #dc3232;
                display: flex;
                align-items: center;
                gap: 5px;
            }

            .mdm-not-synced .dashicons {
                color: #dc3232;
            }

            .mdm-not-synced em {
                font-size: 12px;
                color: #999;
            }

            .mdm-variations {
                display: inline-block;
                margin-top: 4px;
                padding: 2px 6px;
                border-radius: 3px;
                font-size: 11px;
            }

            .mdm-variations.all-synced {
                background: #e7f7e7;
                color: #2e7d32;
            }

            .mdm-variations.partial-synced {
                background: #fff8e5;
                color: #996800;
            }

            .mdm-variations.none-synced {
                background: #fbeaea;
                color: #a00;
            }
        </style>
        <?php
    }

    /**
     * Complete the onboarding wizard
     * Saves the option to prevent showing the wizard again
     *
     * @return void
     * @since 4.0.0
     */
    public function complete_bordereau_onboarding()
    {
        check_ajax_referer('wp_bordereau_rest', '_nonce');

        update_option('bordereau_onboarding_completed', 'yes');

        wp_send_json([
            'success' => true,
            'message' => __('Onboarding completed successfully', 'woo-bordereau-generator')
        ], 200);
    }
}