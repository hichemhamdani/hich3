<?php

namespace WooBordereauGenerator;

use Automattic\WooCommerce\Internal\DataStores\Orders\CustomOrdersTableController;
use Automattic\WooCommerce\Utilities\OrderUtil;
use Exception;
use WC_Order;
use WC_Product;
use WC_Shipping_Zones;

class Helpers {

    /**
     * @param $text
     * @param string $divider
     * @return string
     */
    public static function slugify($text, string $divider = '-')
    {
        // replace non letter or digits by divider
        $text = preg_replace('~[^\pL\d]+~u', $divider, $text);

        // transliterate
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);

        // remove unwanted characters
        $text = preg_replace('~[^-\w]+~', '', $text);

        // trim
        $text = trim($text, $divider);

        // remove duplicate divider
        $text = preg_replace('~-+~', $divider, $text);

        // lowercase
        $text = strtolower($text);

        if (empty($text)) {
            return 'n-a';
        }

        return $text;
    }

    /**
     * Get WooCommerce order by order number.
     *
     * @param string $order_number The order number.
     * @return WC_Order|bool The order object or false if not found.
     */
    public static function get_order_by_order_number($order_number) {
        // Sanitize the order number.
        $order_number = sanitize_text_field($order_number);


        // Get the order ID by searching the post title.
        $args = array(
            'numberposts' => 1,
            'post_type'   => 'shop_order',
            'meta_query'  => array(
                array(
                    'key'     => '_order_number',
                    'value'   => $order_number,
                    'compare' => '='
                ),
            ),
        );

        $orders = get_posts($args);

        // If an order is found, return the order object.
        if (!empty($orders)) {
            $order_id = $orders[0]->ID;
            return wc_get_order($order_id);
        }

        // Return false if no order is found.
        return false;
    }

    public static function get_order_id_by_sequential_order_number($order_number) {
        global $wpdb;

        // Sanitize the order number.
        $order_number = sanitize_text_field($order_number);

        // Query the database to find the post ID by the custom order number meta key.
        $query = $wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_order_number' AND meta_value = %s",
            $order_number
        );

        $order_id = $wpdb->get_var($query);

        // Return the order ID or false if not found.
        return $order_id ? intval($order_id) : false;
    }

    public static function get_order_ids_by_sequential_order_numbers($order_numbers) {
        global $wpdb;

        // Sanitize order numbers.
        $order_numbers = array_map('sanitize_text_field', $order_numbers);

        // Prepare the placeholders for the query.
        $placeholders = array_fill(0, count($order_numbers), '%s');

        // Query the database to find the post IDs by the custom order number meta key.
        $query = $wpdb->prepare(
            "SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_order_number' AND meta_value IN (" . implode(',', $placeholders) . ")",
            $order_numbers
        );

        $results = $wpdb->get_col($query);

        // Return the order IDs.
        return array_map('intval', $results);
    }

    public static function clean_phone_number($phone, $international = false) {
        // Remove non-digit characters
        $cleaned = preg_replace('/\D/', '', $phone);

        // If it starts with "213" (country code for Algeria), replace it with "0"
        if (substr($cleaned, 0, 3) === "213") {
            $cleaned = '0' . substr($cleaned, 3);
        }


        if ($international) {
            if (strpos($cleaned, "0") === 0) {
                $cleaned = "213" . substr($cleaned, 1);
            }
        }

        return $cleaned;
    }

    public static function make_note(\WC_Order $order, $trackingNumber, $providerName)
    {
        $current_user = wp_get_current_user();
        $display_name = 'Automatic';

        if ($current_user) {
            $display_name = $current_user->display_name;
        }

        $note = sprintf("Order has been attached to the shipping company %s tracking number : %s by %s.", $providerName, wc_clean($trackingNumber),  $display_name);
        $order->add_order_note($note, 1); // The '1' makes this note private (only visible to admins)
    }

    public static function generateTrackingNumber($prefix, $format) {
        // Validate inputs
        if (empty($prefix) || empty($format)) {
            throw new Exception('Prefix and format are required');
        }

        // Count how many random characters we need
        $formatParts = str_split($format);
        $randomCharsNeeded = count(array_filter($formatParts, function($char) {
            return $char === 'N';
        }));

        $maxAttempts = 100; // Prevent infinite loops
        $attempt = 0;

        do {
            $trackingNumber = '';
            $currentPrefix = $prefix;

            // Generate the tracking number based on format
            foreach ($formatParts as $char) {
                if ($char === 'P') {
                    // Add one character from the prefix
                    $trackingNumber .= !empty($currentPrefix) ? substr($currentPrefix, 0, 1) : '';
                    $currentPrefix = substr($currentPrefix, 1);
                } elseif ($char === 'N') {
                    // Generate random alphanumeric character
                    $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
                    $trackingNumber .= $chars[rand(0, strlen($chars) - 1)];
                } else {
                    // Keep other characters (like hyphen) as is
                    $trackingNumber .= $char;
                }
            }

            // Check if tracking number already exists
            $exists = self::trackingNumberExists($trackingNumber);

            $attempt++;

            // If we found a unique number or hit max attempts, break the loop
            if (!$exists || $attempt >= $maxAttempts) {
                break;
            }
        } while (true);

        // If we hit max attempts without finding a unique number
        if ($attempt >= $maxAttempts) {
            throw new Exception('Unable to generate unique tracking number after ' . $maxAttempts . ' attempts');
        }

        return $trackingNumber;
    }

    /**
     * Check if a tracking number already exists in WooCommerce orders
     *
     * @param string $trackingNumber
     * @return bool
     */
    private static function trackingNumberExists($trackingNumber) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT post_id 
        FROM {$wpdb->postmeta} 
        WHERE meta_key = '_shipping_tracking_number' 
        AND meta_value = %s 
        LIMIT 1",
            $trackingNumber
        );

        $exists = $wpdb->get_var($query);

        return !is_null($exists);
    }

    /**
     * Check if a tracking number already exists in WooCommerce orders
     *
     * @param string $str
     * @return bool
     */
    public static function isArabic($str)
    {
        return preg_match('/\p{Arabic}/u', $str);
    }

    public static function formatProductString($productString, $maxLength = 50)
    {
        // Split the string by commas (assuming products are comma-separated)
        $products = explode(',', $productString);

        // Format each product on a new line
        $formattedProducts = [];
        foreach ($products as $product) {
            $product = trim($product);
            // If a single product description is too long, wrap it
            if (strlen($product) > $maxLength) {
                $formattedProducts[] = wordwrap($product, $maxLength, "\n", true);
            } else {
                $formattedProducts[] = $product;
            }
        }

        // Join with line breaks
        return implode("\n", $formattedProducts);
    }

    public function map_new_state_to_old($stateId) {

        $wilayaId = "DZ". $stateId;

        $states =   array(
            'DZ49' => 'DZ1',
            'DZ50' => 'DZ1',
            'DZ51' => 'DZ7',
            'DZ52' => 'DZ8',
            'DZ53' => 'DZ11',
            'DZ54' => 'DZ11',
            'DZ55' => 'DZ30',
            'DZ56' => 'DZ33',
            'DZ57' => 'DZ39',
            'DZ58' => 'DZ47',
        );

        return (int) str_replace("DZ", "", $states[$wilayaId] ?? $stateId);
    }

    public static function map_old_city($city) {

        // Manually mapped cities
        $manual_mapping = [
            "Benacer Benchohra" => "Bennasser Benchohra",
            "El Fedjoudj Boughrara Sa" => "El Fedjoudj Boughrara Saoudi",
            "Azil Abedelkader" => "Abdelkader Azil",
            "Mechraa H.boumediene" => "Mechraa Houari Boumedienne",
            "Beni Yenni" => "Aït Yenni",
            "Beni Zikki" => "Beni Ziki",
            "Bains Romains" => "Bains Romaines",
            "Bologhine Ibnou Ziri" => "Bologhine Ibn Ziri",
            "Mohamed Belouzdad" => "Mohamed Belouizdad",
            "Beni Ouartilane" => "Beni Ouertilane",
            "Beni Oussine" => "Beni Ousine",
            "Sidi Sandel" => "Sidi Sandal",
            "Damiat" => "Damiat",
            "Medjebar" => "Medjbar",
            "Sidi Rabie" => "Sidi Rabe",
            "Tletat Ed Douair" => "Tleta Ed Douair",
            "Benabdelmalek Ramdane" => "Ben Abdelmalek Ramdane",
            "Tesmart" => "Tesmart",
            "Tamellalet" => "Tamellalt",
            "Bir Bouhouche" => "Bir Bouhouch",
            "Djeniane Bourzeg" => "Djenien Bourzeg",
            "Beni Dejllil" => "Boudjellil",
            "Beni K'sila" => "Aït Ksila",
            "Beni Mallikeche" => "Aït Mellikeche",
            "Benimaouche" => "Aït Maouche",
            "Dra El Caid" => "Draâ El Kaïd",
            "Tinebdar" => "Tinabdher",
            "Taourirt" => "Ath Mansour",
            "Beni Khaled" => "Beni Khellad",
            "Oued Chouly" => "Oued Lakhdar",
            "Djebel Aissa Mimoun" => "Aït Aïssa Mimoun",
            "Ouled Habbeba" => "Ouled Hbaba",
            "Sidi Dahou Zairs" => "Sidi Daho des Zairs",
            "Ain Hessania" => "El Hassania"
        ];

        if (isset($manual_mapping[$city])) {
            return $manual_mapping[$city];
        }

        // Define the new to old state mapping
        $new_to_old_mapping = array(
            'DZ49' => 'DZ1',
            'DZ50' => 'DZ1',
            'DZ51' => 'DZ7',
            'DZ52' => 'DZ8',
            'DZ53' => 'DZ11',
            'DZ54' => 'DZ11',
            'DZ55' => 'DZ30',
            'DZ56' => 'DZ33',
            'DZ-57' => 'DZ-39',
            'DZ-58' => 'DZ-47',
        );

        include 'oldcitites.php';
        /** @var array $cities */
        $old_states_cities = $cities;

        include 'newcities.php';
        $new_states_cities = $cities;

        // Loop through the new system's states and cities
        foreach ($new_states_cities as $state => $cities) {
            foreach ($cities as $newCity) {
                if (self::citiesAreSimilar($city, $newCity)) {
                    // If the state is in the mapping, get the mapped old state
                    if (isset($new_to_old_mapping[$state])) {
                        $old_state = $new_to_old_mapping[$state];
                    } else {
                        $old_state = $state;
                    }

                    // Check if a similar city exists in the old system
                    foreach ($old_states_cities[$old_state] as $oldCity) {
                        if (self::citiesAreSimilar($city, $oldCity)) {
                            return $oldCity;
                        }
                    }
                }
            }
        }

        // If the city is not found, return null or any default value
        return null;
    }

    public static function map_new_city_to_old_city() {

    }

    /**
     * @return mixed
     */
    public function get_cities($wilayaId) {

        if (yalidine_is_enabled()) {
            include 'cities/newyalidinecities.php';
        } elseif (is_zr_enabled()) {
            include 'cities/zr_express_cities.php';
        } elseif(is_3m_express_enabled()) {
            include 'cities/3m_express_cities.php';
        } elseif(is_maystro_delivery_enabled()) {
            include 'cities/maystro_delivery_cities.php';
        } else {
            include 'cities/newcities.php';
        }

        /** @var array $cities */
        return $cities['DZ-'.$wilayaId];
    }

    public static function get_all_cities() {

        if (yalidine_is_enabled()) {
            include 'cities/newyalidinecities.php';
        } elseif (is_zr_enabled()) {
            include 'cities/zr_express_cities.php';
        } elseif(is_3m_express_enabled()) {
            include 'cities/3m_express_cities.php';
        } elseif(is_maystro_delivery_enabled()) {
            include 'cities/maystro_delivery_cities.php';
        } else {
            include 'cities/newcities.php';
        }

        /** @var array $cities */
        return $cities;
    }

    public static function get_cities_by_provider($provider = 'default') {

        switch ($provider) {
            case 'yalidine':
            case 'guepex':
            case 'wecanservices':
            case 'speedmail':
            case 'easyandspeed':
                include 'cities/newyalidinecities.php';
                break;
            case 'elogistia':
                include 'cities/elogistia_cities.php';
                break;
            case 'zr_express':
            case 'zr_express_new':
            case 'zr_express_api':
                include 'cities/zr_express_cities.php';
                break;
            case '3m_express':
                include 'cities/3m_express_cities.php';
                break;
            case 'maystro_delivery':
                include 'cities/maystro_delivery_cities.php';
                break;
            case 'nord_ouest':
                include 'cities/nord_ouest_cities.php';
                break;
            default:
                include 'cities/newcities.php';
                break;
        }

        /** @var array $cities */
        return $cities;
    }


    public static function arabicToLatin($arabicText) {

        $arabicToLatin = [
            'أ' => 'a', 'ا' => 'a','لإ' => 'le', 'إ' => 'i', 'آ' => 'aa', 'ب' => 'b', 'پ' => 'p',
            'ت' => 't', 'ث' => 'th', 'ج' => 'j', 'چ' => 'ch', 'ح' => 'h', 'خ' => 'kh',
            'د' => 'd', 'ذ' => 'dh', 'ر' => 'r', 'ز' => 'z', 'س' => 's', 'ش' => 'sh',
            'ص' => 's', 'ض' => 'd', 'ط' => 't', 'ظ' => 'z', 'ع' => 'a', 'غ' => 'gh',
            'ف' => 'f', 'ق' => 'q', 'ك' => 'k', 'گ' => 'g', 'ل' => 'l', 'م' => 'm',
            'ن' => 'n', 'ه' => 'h', 'و' => 'w', 'ؤ' => 'u', 'ي' => 'y', 'ئ' => 'i',
            'ى' => 'a', 'ة' => 'h', 'ء' => ' '
        ];

        if (!preg_match('/[\x{0600}-\x{06FF}]/u', $arabicText)) {
            return $arabicText;
        }

            // Transliteration
        $latinText = "";
        for ($i = 0; $i < mb_strlen($arabicText); $i++) {
            $char = mb_substr($arabicText, $i, 1);
            if (array_key_exists($char, $arabicToLatin)) {
                // If the character is in the mapping array, replace it
                $latinText .= $arabicToLatin[$char];
            } else {
                // If the character is not in the mapping array, keep it as is
                $latinText .= $char;
            }
        }

        return $latinText;
    }
    public static function normalizeCityName($city) {
        $city = strtolower($city);
        $city = str_replace(array('ä', 'ï', 'ë', 'ö', 'ü', 'á', 'í', 'é', 'ó', 'ú', 'à', 'ì', 'è', 'ò', 'ù'),
            array('a', 'i', 'e', 'o', 'u', 'a', 'i', 'e', 'o', 'u', 'a', 'i', 'e', 'o', 'u'),
            $city);
        $city = str_replace(array('\'', '-', 'el ', 'al '), '', $city);
        return trim($city);
    }

    public static function citiesAreSimilar($city1, $city2, $exact = false) {
        $normalizedCity1 = self::normalizeCityName($city1);
        $normalizedCity2 = self::normalizeCityName($city2);

        if ($exact) {
           return  $normalizedCity1 ==  $normalizedCity2;
        }

        $distance = levenshtein($normalizedCity1, $normalizedCity2);
        return ($distance <= 2); // Allow up to 2 edits for a match, you can adjust this threshold
    }


    /**
     * Check if this is a shop_order page (edit or list)
     */
    public static function is_order_page() {
        return is_order_page();
    }

    /**
     * Helper function to get whether custom order tables are enabled or not.
     *
     * @return bool
     */
    public static function is_hpos_enabled() : bool {
        return is_callable( OrderUtil::class . '::' . 'custom_orders_table_usage_is_enabled' )
            && OrderUtil::custom_orders_table_usage_is_enabled();
    }


    /**
     * Check if the string is arabic
     * @param $string
     * @return false|int
     */
    static function is_arabic($string) {
        return preg_match('/[\x{0600}-\x{06FF}]/u', $string);
    }

    /**
     * Generate Slug
     * @param $title
     * @return string
     */
    static function generate_slug($title) {
        if (self::is_arabic($title)) {
            $slug = \Behat\Transliterator\Transliterator::transliterate($title, '-');
            return $slug;
        }

        return sanitize_title($title);
    }

    public static function get_screen_title() {

        if ( class_exists( CustomOrdersTableController::class ) && function_exists( 'wc_get_container' ) && \wc_get_container()->get( CustomOrdersTableController::class )->custom_orders_table_usage_is_enabled() ) {
            $screen_id = \wc_get_page_screen_id( 'shop-order' );
        } else {
            $screen_id = 'shop_order';
        }

        return $screen_id;
    }

    public static function check_if_selected($name, $value): string
    {
        return isset($_GET[$name]) && $_GET[$name] == $value ? 'selected="selected"' : '';
    }

    public static function sort_tracking($array) {

        if (is_array($array) && count($array)) {
            usort($array, function ($a, $b) {
                // Convert dates to timestamps for comparison
                $dateA = strtotime($a['date']);
                $dateB = strtotime($b['date']);

                // Sort from newest to oldest
                return $dateB - $dateA;
            });
        }


        return $array;
    }

	public static function convertToUnicode($string) {
		// Encode the string to JSON which converts non-ASCII to \uXXXX
		$encoded = json_encode($string);
		// Remove the surrounding quotes
		return trim($encoded, '"');
	}

	public static function convertArabicToUnicode($string) {
		$result = '';
		$convmap = array(0x80, 0xffff, 0, 0xffff);

		// Convert to numeric entities
		$encoded = mb_encode_numericentity($string, $convmap, 'UTF-8');

		// Replace numeric entities with Unicode escape sequences
		$result = preg_replace_callback('/&#(\d+);/', function($matches) {
			return sprintf('\\u%04x', $matches[1]);
		}, $encoded);

		return $result;
	}

    public static function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $randomString;
    }


    public static function generate_products_string($products, $onlyTitle = false) {
        if ($onlyTitle) {
            $formatString = "{title}";
        } else {
            $formatString = get_option("wc_bordreau_product-string-format", "{title} - {sku} x ({qty})");
        }

        $format = [];

        if (count($products)) {
            foreach ($products as $key => $p) {
                $item_qty = $p['quantity'];

                // Check if the product is a variation
                if (!empty($p['variation_id'])) {
                    // This is a variation of a product
                    $variation = wc_get_product($p['variation_id']);
                    $parent_product = wc_get_product($variation->get_parent_id());

                    $item_sku = $variation->get_sku();
                    $item_id = $variation->get_id();
                    $parent_id = $parent_product->get_id();
                    $item_name = $variation->get_name();

                    // Get the parent product name
                    $item_attributes = $variation->get_attribute_summary();
                    // Combine parent product name with variation details
                    $full_title = $item_name;
                } else {
                    // This is a simple product
                    $parent_product = wc_get_product($p['product_id']);

					if ($parent_product) {
						$item_sku = $parent_product->get_sku();
						$item_id = $parent_product->get_id();
						$parent_id = $item_id;
						$item_name = $parent_product->get_name();
						$item_attributes = "";
						$full_title = $item_name;
					}

                }

                // Define an associative array with placeholders and their corresponding values
                $placeholders = array(
                    '{title}' => $full_title ?? '',
                    '{sku}' => $item_sku ?? '',
                    '{qty}' => $item_qty ?? '',
                    '{id}' => $item_id ?? '',
                    '{attributes}' => $item_attributes ?? '',
                );

                // Add support for custom post meta
                preg_match_all('/{meta:(.*?)}/', $formatString, $meta_matches);
                foreach ($meta_matches[1] as $meta_key) {
                    // For variations, check both variation and parent product meta
                    if (!empty($p['variation_id'])) {
                        $meta_value = get_post_meta($item_id, $meta_key, true);
                        if (empty($meta_value)) {
                            $meta_value = get_post_meta($parent_id, $meta_key, true);
                        }
                    } else {
                        $meta_value = get_post_meta($item_id, $meta_key, true);
                    }
                    $placeholders["{meta:{$meta_key}}"] = $meta_value;
                }

                // Add support for taxonomies
                preg_match_all('/{taxonomy:(.*?)}/', $formatString, $taxonomy_matches);
                foreach ($taxonomy_matches[1] as $taxonomy) {
                    // Always use the parent product ID for taxonomy terms
                    $terms = wp_get_post_terms($parent_id, $taxonomy, array('fields' => 'names'));
					if (is_array($terms)) {
						$placeholders["{taxonomy:{$taxonomy}}"] = implode(', ', $terms);
					}
                }

                // Replace placeholders with actual values using strtr
                $format[$key] = strtr($formatString, $placeholders);
            }

            $productsString = implode(',', $format);
            $productsString = strip_tags($productsString);

            return preg_replace('/,$/m', '', $productsString);
        }

        return '';
    }

    public static function decode_jwt($token) {
        return json_decode(base64_decode(str_replace('_', '/', str_replace('-','+',explode('.', $token)[1]))));
    }

    /**
     * @param $string1
     * @param $string2
     * @return bool
     */
    public static function areSimilar($string1, $string2) {
        similar_text($string1, $string2, $percent);
        $distance = levenshtein($string1, $string2);

        // Define your thresholds
        $similarityThreshold = 90; // for percentage of similarity
        $distanceThreshold = 2; // for Levenshtein distance

        return $percent > $similarityThreshold || $distance <= $distanceThreshold;
    }


    /**
     * @param $str
     * @return bool
     */
    public static function isValid64base($str){
        if (base64_decode($str, true) !== false){
            return true;
        } else {
            return false;
        }
    }


    public static function get_order_shipping_method_class($order) {
        // Get the shipping methods used for this order
        $shipping_methods = $order->get_shipping_methods();

        foreach ($shipping_methods as $shipping_method) {
            // Get the shipping method ID (e.g., 'flat_rate_yalidine:12')
            $shipping_method_id = $shipping_method->get_method_id();

            // Split the ID to get the shipping method name
            $shipping_method_name = strtok($shipping_method_id, ':');

            // Get all available shipping methods
            $all_shipping_methods = WC()->shipping()->get_shipping_methods();

            if (isset($all_shipping_methods[$shipping_method_name])) {
                $method_class = get_class($all_shipping_methods[$shipping_method_name]);
                return new $method_class($shipping_method->get_instance_id());
            }
        }

        return false; // No shipping method found
    }

    /**
     * @return string|null
     */
    public static function get_first_available_shipping_method() {

        $shipping_zones = WC_Shipping_Zones::get_zones();

        if (is_array($shipping_zones)) {

            // Select a relevant zone (adjust if needed, here we take the first one)
            $zone = reset($shipping_zones);

            if ($zone) {
                // Get shipping methods within the selected zone
                $shipping_methods = $zone['shipping_methods'];

                $shipping_methods = array_filter($shipping_methods, function ($shipping_method) {
                    return $shipping_method->enabled;
                });

                // Get the first shipping method
                $first_method = reset($shipping_methods);


                // Access shipping method details
                $method_id   = $first_method->id;
                $instance_id = $first_method->instance_id;

                // Return necessary details
                return $method_id.':'.$instance_id;
            }
        }



        return null;
    }

    public static function get_available_shipping_method($state) {

        $shipping_zones = WC_Shipping_Zones::get_zones();

        $state = "DZ:".$state;

        // Find the relevant shipping zone for the given state
        foreach ($shipping_zones as $zone) {
            foreach ($zone['zone_locations'] as $location) {
                if ($location->code === $state) {
                    // Get shipping methods within the selected zone
                    $shipping_methods = $zone['shipping_methods'];

                    // Filter out disabled shipping methods
                    $enabled_shipping_methods = array_filter($shipping_methods, function ($shipping_method) {
                        return $shipping_method->enabled;
                    });

                    // Get the first shipping method
                    $first_method = reset($enabled_shipping_methods);

                    if ($first_method) {
                        // Access shipping method details
                        $method_id   = $first_method->id;
                        $instance_id = $first_method->instance_id;

                        // Return necessary details
                        return $method_id . ':' . $instance_id;
                    }
                }
            }
        }

        // If no shipping method is found for the given state, return null or handle the case appropriately
        return null;
    }

    public function get_extra_weight($order)
    {
        $shippingOverweight = 0;

        foreach ( $order->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            if ( $product ) {
                $product_weight = $product->get_weight();
                // Ensure the product has a weight before adding it
                if ( $product_weight ) {
                    $shippingOverweight += (float) $product_weight * (int) $item->get_quantity();
                }
            }
        }

        return $shippingOverweight;
    }

    public static function get_product_weight(WC_Order $order)
    {
        $total_weight = 0;

        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            if ($product) {
                if ($product->is_type('variation')) {
                    $weight = $product->get_weight();
                    if (empty($weight)) {
                        $parent_product = wc_get_product($product->get_parent_id());
                        $weight = $parent_product->get_weight();
                    }
                } else {
                    $weight = $product->get_weight();
                }

                // Ensure the product has a weight before adding it
                if ($weight) {
                    $total_weight += (float)$weight * (int)$item->get_quantity();
                }
            }
        }

        return $total_weight;
    }

    /**
     * Calculate billable weight for Yalidine using volumetric weight formula.
     *
     * Volumetric weight = width (cm) x height (cm) x length (cm) x 0.0002
     * Billable weight   = max(volumetric weight, actual weight)
     *
     * Accepts either a WC_Order or WC_Cart to iterate over products.
     *
     * @param WC_Order|\WC_Cart $source  Order or Cart instance
     * @return array{billable_weight: float, actual_weight: float, volumetric_weight: float, dimensions: array{length: float, width: float, height: float}}
     * @since 4.17.0
     */
    public static function get_billable_weight($source): array
    {
        $total_weight = 0.0;
        $dimensions = ['length' => 0.0, 'width' => 0.0, 'height' => 0.0];

        if ($source instanceof WC_Order) {
            foreach ($source->get_items() as $item) {
                $product = $item->get_product();
                if (!$product) continue;

                $weight = self::get_resolved_product_weight($product);
                $quantity = (int) $item->get_quantity();

                $total_weight += (float) $weight * $quantity;
                $dimensions['length'] += (float) $product->get_length() * $quantity;
                $dimensions['width']  += (float) $product->get_width()  * $quantity;
                $dimensions['height'] += (float) $product->get_height() * $quantity;
            }
        } elseif ($source instanceof \WC_Cart) {
            foreach ($source->get_cart_contents() as $cart_item) {
                $product = $cart_item['data'];
                if (!$product) continue;

                $weight = self::get_resolved_product_weight($product);
                $quantity = (int) $cart_item['quantity'];

                $total_weight += (float) $weight * $quantity;
                $dimensions['length'] += (float) $product->get_length() * $quantity;
                $dimensions['width']  += (float) $product->get_width()  * $quantity;
                $dimensions['height'] += (float) $product->get_height() * $quantity;
            }
        }

        // Volumetric weight = length x width x height x 0.0002
        $volumetric_weight = 0.0;
        if ($dimensions['length'] > 0 && $dimensions['width'] > 0 && $dimensions['height'] > 0) {
            $volumetric_weight = $dimensions['length'] * $dimensions['width'] * $dimensions['height'] * 0.0002;
        }

        // Billable weight = max(volumetric, actual)
        $billable_weight = max($volumetric_weight, $total_weight);

        return [
            'billable_weight'   => $billable_weight,
            'actual_weight'     => $total_weight,
            'volumetric_weight' => $volumetric_weight,
            'dimensions'        => $dimensions,
        ];
    }

    /**
     * Get product weight, resolving variation parent fallback.
     *
     * @param \WC_Product $product
     * @return float
     */
    private static function get_resolved_product_weight($product): float
    {
        if ($product->is_type('variation')) {
            $weight = $product->get_weight();
            if (empty($weight)) {
                $parent_product = wc_get_product($product->get_parent_id());
                $weight = $parent_product ? $parent_product->get_weight() : 0;
            }
        } else {
            $weight = $product->get_weight();
        }

        return (float) ($weight ?: 0);
    }

    public static function get_wilaya($wilaya_code, $wilayas, $search_key = 'wilaya_id') {

        if (preg_match('/^DZ-\d{2}$/', $wilaya_code)) {
            $wilayaId = (int)str_replace("DZ-", "", $wilaya_code);
            $wilayaKey = array_search($wilayaId, array_column($wilayas, $search_key));

            if ($wilayaKey !== false) {
                $wilaya = $wilayas[$wilayaKey];
            }
        }

        if (preg_match('/\d+/', $wilaya_code, $matches)) {
            $wilayaId = $matches[0];
            $wilayaKey = array_search($wilayaId, array_column($wilayas, $search_key));

            if ($wilayaKey !== false) {
                $wilaya = $wilayas[$wilayaKey];
            }
        }

        if(!$wilaya) {
            // If not found by ID or doesn't match the pattern, search by name
            foreach ($wilayas as $key => $item) {
                if (Helpers::slugify($item['wilaya_name']) === Helpers::slugify($wilaya_code)) {
                    $wilaya = $item;
                }
            }
        }

        return $wilaya;
    }

    public static function searchByName($data, $search, $searchKey, $maxDistance = 2) {
        $results = array();

        foreach ($data as $key => $item) {
            // Clean the strings: remove special characters and convert to lowercase
            $name = strtolower(str_replace(array('«', '»'), '', $item[$searchKey]));
            $searchTerm = strtolower($search);

            // Split into words
            $nameWords = explode(' ', $name);
            $searchWords = explode(' ', $searchTerm);

            $matchCount = 0;
            $totalWords = count($searchWords);

            foreach ($searchWords as $searchWord) {
                foreach ($nameWords as $nameWord) {
                    // Check for exact match first
                    if ($nameWord === $searchWord) {
                        $matchCount++;
                        break;
                    }

                    // Check for fuzzy match
                    $distance = levenshtein($nameWord, $searchWord);
                    if ($distance <= $maxDistance) {
                        $matchCount++;
                        break;
                    }
                }
            }

            // If most words match (allowing for partial matches)
            if ($matchCount >= ($totalWords * 0.7)) {
                $results[$key] = $item;
            }
        }

        return $results;
    }


	// Helper function to encode Arabic text
	public static function encodeArabicText($text) {
		if (self::isArabic($text)) {
			$chars = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);
			$encoded = '';
			foreach ($chars as $char) {
				if (preg_match('/\p{Arabic}/u', $char)) {
					if (function_exists('mb_ord')) {
						$encoded .= '\u' . str_pad(dechex(mb_ord($char)), 4, '0', STR_PAD_LEFT);
					} else {
						$encoded .= $char;
					}
				} else {
					$encoded .= $char;
				}
			}
			return $encoded;
		}
		return $text;
	}
}
