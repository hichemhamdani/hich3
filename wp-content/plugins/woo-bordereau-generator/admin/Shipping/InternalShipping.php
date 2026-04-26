<?php

namespace WooBordereauGenerator\Admin\Shipping;

use Exception;
use WC_Order;
use WooBordereauGenerator\Helpers;
use WP_Error;

class InternalShipping extends \WooBordereauGenerator\Admin\Shipping\BordereauProvider implements \WooBordereauGenerator\Admin\Shipping\BordereauProviderInterface
{

    /**
     * @var WC_Order
     */
    protected $formData;

    /**
     * @var array
     */
    protected $provider;

    /**
     * @var array|mixed
     */
    private $data;

    public function __construct(WC_Order $formData, array $provider, $data = [])
    {
        $this->formData = $formData;
        $this->provider = $provider;
        $this->data = $data;
    }


    /**
     * Generate tracking number from yalidine
     * @since 1.0.0
     * @return mixed|void
     */
    public function generate()
    {
        $params = $this->formData->get_data();

        return $this->data;
    }

    /**
     * Save the response to the postmeta
     * @param $post_id
     * @param array $response
     * @param bool $update
     * @throws Exception
     * @since 1.1.0
     */
    public function save($post_id, array $response, bool $update = false)
    {

        $order = wc_get_order($post_id);

        $provider = get_post_meta($post_id, '_shipping_tracking_method', true);

        if(! $provider) {
            $provider = $order->get_meta('_shipping_tracking_method');
        }

        $trackingNumber = '';
        $label = '';


        if(get_option($this->provider['slug'].'_tracking_number') == 'with-tracking-number') {
            $prefix = get_option($this->provider['slug'].'_tracking_number_prefix');
            $format = get_option($this->provider['slug'].'_tracking_number_format');
            $trackingNumber = Helpers::generateTrackingNumber($prefix, $format);
        }

        if ($update && $provider == $this->provider['slug']) {
            $trackingNumber = get_post_meta($post_id, '_shipping_tracking_number', true);
            if(! $trackingNumber) {
                $trackingNumber = $order->get_meta('_shipping_tracking_number');
            }

            $label = get_post_meta($post_id, '_shipping_tracking_label', true);

            if(! $label) {
                $label = $order->get_meta('_shipping_tracking_label');
            }
            update_post_meta($post_id, '_shipping_tracking_driver', wc_clean($response['driver'])); // backwork compatibilite
            $order->update_meta_data('_shipping_tracking_driver', wc_clean($response['driver']));


        } else {
            update_post_meta($post_id, '_shipping_tracking_number', wc_clean($trackingNumber)); // backwork compatibilite
            update_post_meta($post_id, '_shipping_tracking_driver', wc_clean($response['driver'])); // backwork compatibilite

            if($order) {
                $order->update_meta_data('_shipping_tracking_number', wc_clean($trackingNumber));
                $order->update_meta_data('_shipping_tracking_driver', wc_clean($response['driver']));
                $order->update_meta_data('_shipping_tracking_label', wc_clean($label));
                $order->update_meta_data('_shipping_label', wc_clean($label));
                $order->update_meta_data('_shipping_tracking_method', $this->provider['slug']);
                $order->save();

                Helpers::make_note($order, $trackingNumber, $this->provider['name']);

            }

            update_post_meta($post_id, '_shipping_tracking_label', wc_clean($label));
            update_post_meta($post_id, '_shipping_label', wc_clean($label));
            update_post_meta($post_id, '_shipping_tracking_url', 'https://'.$this->provider['url'].'/suivre-un-colis/?tracking='. wc_clean($trackingNumber));
            update_post_meta($post_id, '_shipping_tracking_method', $this->provider['slug']);
        }
        $current_tracking_data = [
            'tracking_number' => wc_clean($trackingNumber),
            'carrier_slug' => $this->provider['slug'],
            'carrier_url' => '',
            'driver' => (int) wc_clean($response['driver']),
            "carrier_name" => $this->provider['name'],
            "carrier_type" => "custom-carrier",
            "time" => time()
        ];

        // check if the woo-order-tracking is enabled

        if (in_array('woo-orders-tracking/woo-orders-tracking.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            $item_tracking_data[] = $current_tracking_data;
            $order = new WC_Order($post_id);
            $order_items = $order->get_items();
            foreach ($order_items as $item) {
                wc_update_order_item_meta($item->get_id(), '_vi_wot_order_item_tracking_data', json_encode($item_tracking_data));
            }
        }

        wp_send_json([
            'message' => __("Tracking number has been successfully created", 'wc-bordereau-generator'),
            'provider' => $this->provider['name'],
            'tracking' => $current_tracking_data,
            'label' => wc_clean($label)
        ], 200);
    }

    /**
     * Get Cities from specific State
     * @param int $wilaya_id
     * @param bool $hasStopDesk
     *
     * @return array
     */
    public function get_communes(int $wilaya_id, bool $hasStopDesk)
    {

        $communes = [];

        include plugin_dir_path(WC_BORDEREAU_PLUGIN_PATH) . 'includes/cities/newcities.php';

        /** @var array $cities */
        $citiesCommune = $cities[$_POST['wilaya']];

        foreach ($citiesCommune as $key => $item) {
            $communes[$key]['id'] = $item;
            $communes[$key]['name'] = $item;
        }

        return $communes;
    }

    /**
     * Fetch the tracking information
     * @param $tracking_number
     * @param null $post_id * @return void
     *@since 1.1.0
     */
    public function track($tracking_number, $post_id = null)
    {

    }

    /**
     * Get the tracking information for the order
     * @param string $tracking_number
     * @return array
     *@since 1.1.0
     */
    public function track_detail($tracking_number)
    {

    }


    /**
     * Get the tracking information for the order
     * @param $tracking_number
     * @param $post_id
     * @return void
     * @since 1.2.0
     */
    public function detail($tracking_number, $post_id)
    {

    }


    /**
     * Format the response of the tracking
     * @param $response_array
     * @since 1.1.0
     * @return array
     */
    private function format_tracking($response_array): array
    {
        return  [];
    }

    /**
     * @param $response
     * @since 1.2.0
     * @return array
     */
    private function format_tracking_detail($response): array
    {
        return [];
    }


    /**
     * @since 1.2.2
     * @return mixed
     */
    public function get_wilayas()
    {
        return [];
    }

    /**
     * @param $provider
     *
     * @return array
     *@since 1.2.2
     */
    public function get_wilayas_by_provider()
    {
        return [
            'DZ-01' => [
                'name' => 'Adrar',
                'id' => '01'
            ],
            'DZ-02' => [
                'name' => 'Chlef',
                'id' => '02'
            ],
            'DZ-03' => [
                'name' => 'Laghouat',
                'id' => '03'
            ],
            'DZ-04' => [
                'name' => 'Oum El Bouaghi',
                'id' => '04'
            ],
            'DZ-05' => [
                'name' => 'Batna',
                'id' => '05'
            ],
            'DZ-06' => [
                'name' => 'Béjaïa',
                'id' => '06'
            ],
            'DZ-07' => [
                'name' => 'Biskra',
                'id' => '07'
            ],
            'DZ-08' => [
                'name' => 'Béchar',
                'id' => '08'
            ],
            'DZ-09' => [
                'name' => 'Blida',
                'id' => '09'
            ],
            'DZ-10' => [
                'name' => 'Bouira',
                'id' => '10'
            ],
            'DZ-11' => [
                'name' => 'Tamanrasset',
                'id' => '11'
            ],
            'DZ-12' => [
                'name' => 'Tébessa',
                'id' => '12'
            ],
            'DZ-13' => [
                'name' => 'Tlemcen',
                'id' => '13'
            ],
            'DZ-14' => [
                'name' => 'Tiaret',
                'id' => '14'
            ],
            'DZ-15' => [
                'name' => 'Tizi Ouzou',
                'id' => '15'
            ],
            'DZ-16' => [
                'name' => 'Alger',
                'id' => '16'
            ],
            'DZ-17' => [
                'name' => 'Djelfa',
                'id' => '17'
            ],
            'DZ-18' => [
                'name' => 'Jijel',
                'id' => '18'
            ],
            'DZ-19' => [
                'name' => 'Sétif',
                'id' => '19'
            ],
            'DZ-20' => [
                'name' => 'Saïda',
                'id' => '20'
            ],
            'DZ-21' => [
                'name' => 'Skikda',
                'id' => '21'
            ],
            'DZ-22' => [
                'name' => 'Sidi Bel Abbès',
                'id' => '22'
            ],
            'DZ-23' => [
                'name' => 'Annaba',
                'id' => '23'
            ],
            'DZ-24' => [
                'name' => 'Guelma',
                'id' => '24'
            ],
            'DZ-25' => [
                'name' => 'Constantine',
                'id' => '25'
            ],
            'DZ-26' => [
                'name' => 'Médéa',
                'id' => '26'
            ],
            'DZ-27' => [
                'name' => 'Mostaganem',
                'id' => '27'
            ],
            'DZ-28' => [
                'name' => 'M\'Sila',
                'id' => '28'
            ],
            'DZ-29' => [
                'name' => 'Mascara',
                'id' => '29'
            ],
            'DZ-30' => [
                'name' => 'Ouargla',
                'id' => '30'
            ],
            'DZ-31' => [
                'name' => 'Oran',
                'id' => '31'
            ],
            'DZ-32' => [
                'name' => 'El Bayadh',
                'id' => '32'
            ],
            'DZ-33' => [
                'name' => 'Illizi',
                'id' => '33'
            ],
            'DZ-34' => [
                'name' => 'Bordj Bou Arréridj',
                'id' => '34'
            ],
            'DZ-35' => [
                'name' => 'Boumerdès',
                'id' => '35'
            ],
            'DZ-36' => [
                'name' => 'El Tarf',
                'id' => '36'
            ],
            'DZ-37' => [
                'name' => 'Tindouf',
                'id' => '37'
            ],
            'DZ-38' => [
                'name' => 'Tissemsilt',
                'id' => '38'
            ],
            'DZ-39' => [
                'name' => 'El Oued',
                'id' => '39'
            ],
            'DZ-40' => [
                'name' => 'Khenchela',
                'id' => '40'
            ],
            'DZ-41' => [
                'name' => 'Souk Ahras',
                'id' => '41'
            ],
            'DZ-42' => [
                'name' => 'Tipaza',
                'id' => '42'
            ],
            'DZ-43' => [
                'name' => 'Mila',
                'id' => '43'
            ],
            'DZ-44' => [
                'name' => 'Aïn Defla',
                'id' => '44'
            ],
            'DZ-45' => [
                'name' => 'Naâma',
                'id' => '45'
            ],
            'DZ-46' => [
                'name' => 'Aïn Témouchent',
                'id' => '46'
            ],
            'DZ-47' => [
                'name' => 'Ghardaïa',
                'id' => '47'
            ],
            'DZ-48' => [
                'name' => 'Relizane',
                'id' => '48'
            ],
            'DZ-49' => [
                'name' => 'Timimoun',
                'id' => '49'
            ],
            'DZ-50' => [
                'name' => 'Bordj Badji Mokhtar',
                'id' => '50'
            ],
            'DZ-51' => [
                'name' => 'Ouled Djellal',
                'id' => '51'
            ],
            'DZ-52' => [
                'name' => 'Béni Abbès',
                'id' => '52'
            ],
            'DZ-53' => [
                'name' => 'In Salah',
                'id' => '53'
            ],
            'DZ-54' => [
                'name' => 'In Guezzam',
                'id' => '54'
            ],
            'DZ-55' => [
                'name' => 'Touggourt',
                'id' => '55'
            ],
            'DZ-56' => [
                'name' => 'Djanet',
                'id' => '56'
            ],
            'DZ-57' => [
                'name' => 'El MGhair',
                'id' => '57'
            ],
            'DZ-58' => [
                'name' => 'El Menia',
                'id' => '58'
            ],
        ];
    }

    /**
     * @return bool|mixed|string
     */
    private function get_wilaya_from_provider()
    {
        return [];
    }


    /**
     * Return the settings we need in the metabox
     * @param array $data
     * @param null $option
     * @return array
     * @since 1.2.4
     */
    public function get_settings(array $data, $option = null)
    {
        $provider = $this->provider;
        $order = $this->formData;

        $params = $order->get_data();
        $products = $params['line_items'];

        $cart_value = (float) $order->get_total();

        $productString = Helpers::generate_products_string($products);

        $data['total_calculate'] = 'with-shipping';
        $data['type'] = $provider['type'];
        $data['total'] = $cart_value;
        $data['driver'] = (int) $order->get_meta('_shipping_tracking_driver');
        $data['is_free_shipping'] =  $order->has_shipping_method('free_shipping');
        $data['shipping_fee'] = $shippingCostFromProviderPrice ?? 0;
        $data['order_number'] = $order->get_order_number();
        $data['products_string'] = $productString;

        return $data;
    }

    /**
     * @param $tracking_number
     * @param $post_id
     * @return void
     * @since 1.3.0
     */
    public function delete($tracking_number, $post_id)
    {

    }


    /**
     * @return WP_Error
     *@since 2.2.0
     */
    function get_slip() {
        // Get order ID from request
        $order_id = $this->formData->get_id();

//        // Set proper headers
//        header('Content-Type: application/pdf');
//        header('Content-Disposition: inline; filename="shipping-slip-' . $order_id . '.pdf"');

        try {

            $trackingNumber = $this->formData->get_meta('_shipping_tracking_number');

            $params = $this->formData->get_data();
            $products = $params['line_items'];

            $cart_value = (float) $this->formData->get_total();

            $productString = Helpers::generate_products_string($products);

            $note = stripslashes(get_option('wc_bordreau_default-shipping-note'));

            // Test with Latin content
            $shippingData = array(
                'tracking_number' => $trackingNumber,
                'sender_name' => get_option('woocommerce_store_name', get_bloginfo('name')),
                'sender_address' => get_option('woocommerce_store_address') . ', ' . get_option('woocommerce_store_city'),
                'sender_phone' => get_option('woocommerce_store_phone', ''),
                'recipient_name' => $this->formData->get_billing_first_name() . ' ' . $this->formData->get_billing_last_name(),
                'recipient_address' => $this->formData->get_billing_address_1(),
                'recipient_phone' => $this->formData->get_billing_phone(),
                'recipient_city' => $this->formData->get_billing_city(),
                'recipient_state' => $this->get_wilayas_by_provider()[$this->formData->get_billing_state()]['name'],
                'recipient_state_id' => str_replace('DZ-', '', $this->formData->get_billing_state()),
                'description' => $productString,
                'amount' => $cart_value,
                'note' => $note
            );

            header('Content-Type: text/html; charset=utf-8');
            echo $this->generate_shipping_slip_html($shippingData);

//            $pdf = $this->generateShippingSlip($shippingData);
//            $pdf->Output('bon_livraison.pdf', 'I');
            exit;

        } catch (Exception $e) {
            // Log error
            error_log('PDF Generation Error: ' . $e->getMessage());

            // Return proper REST error
            return new WP_Error(
                'pdf_generation_failed',
                'Failed to generate PDF',
                array('status' => 500)
            );
        }
    }

    function formatProductString($productString, $maxLength = 50) {
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

    public function cancel_order($tracking_number, WC_Order $order)
    {
        try {
            delete_post_meta($order->get_id(), '_shipping_tracking_label');
            delete_post_meta($order->get_id(), '_shipping_label');
            delete_post_meta($order->get_id(), '_shipping_tracking_url');
            delete_post_meta($order->get_id(), '_shipping_tracking_method');

            $order->delete_meta_data('_shipping_tracking_number');
            $order->delete_meta_data('_shipping_tracking_label');
            $order->delete_meta_data('_shipping_label');
            $order->delete_meta_data('_shipping_tracking_method');

        } catch (\ErrorException $exception) {

        }
    }

//    function generateShippingSlip($data) {
//
//        $pdf = new ShippingSlip('P', 'mm', array(105, 148), true, 'UTF-8', false);
//
//        $pdf->SetMargins(5, 5, 5);
//        $pdf->SetAutoPageBreak(false);
//        $pdf->AddPage();
//
//        // Centered Barcode with adjusted position
//        $barcodeWidth = 90;
//        $pageWidth = $pdf->GetPageWidth();
//        $startX = ($pageWidth - $barcodeWidth) / 2;
//
//        $pdf->write1DBarcode(
//            $data['tracking_number'],
//            'C128',
//            $startX,
//            5,
//            $barcodeWidth,
//            15,
//            0.8,
//            array(
//                'position' => 'C',
//                'align' => 'C',
//                'stretch' => true,
//                'fitwidth' => true
//            )
//        );
//
//        // Tracking Number
//        $pdf->SetFont('helvetica', 'L', 16);
//        $pdf->Cell(0, 40, $data['tracking_number'], 0, 1, 'C');
//
//
//        $pdf->Line(0, 30, 120, 30);
//
//        // Sender and Recipient headers
//        $pdf->SetY(35);
//        $pdf->SetFont('helvetica', 'B', 10);
//        $pdf->Cell(47.5, 7, 'Expéditeur:', 0, 0, 'L');
//        $pdf->Cell(47.5, 7, 'Destinataire:', 0, 1, 'L');
//
//        // Sender details
//        $pdf->SetFont(Helpers::isArabic($data['sender_name']) ? 'aealarabiya' : 'helvetica', '', 10);
//        $x = $pdf->GetX();
//        $y = $pdf->GetY();
//        $pdf->MultiCell(47.5, 5, $data['sender_name'] . "\n" . $data['sender_address'] . "\n" . $data['sender_phone'], 0, 'L');
//
//        // Recipient details
//        $pdf->SetXY($x + 47.5, $y);
//        $pdf->SetFont(Helpers::isArabic($data['recipient_name']) ? 'aealarabiya' : 'helvetica', '', 10);
//        $pdf->MultiCell(47.5, 5, $data['recipient_name'] . "\n" . $data['recipient_address'] . "\n" . $data['recipient_phone'], 0, 'L');
//
//        // Details section
//        $pdf->Ln(5);
//        $pdf->SetFont('helvetica', 'B', 10);
//        $pdf->Cell(0, 7, 'Description du contenu:', 0, 1, 'L');
//
//        $pdf->SetFont(Helpers::isArabic($data['description']) ? 'aealarabiya' : 'helvetica', '', 10);
//        // Use MultiCell instead of Cell for text wrapping
//        $pdf->MultiCell(0, 5, Helpers::formatProductString($data['description']), 0, 'L');
//
//        $pdf->Ln(5);
//        $pdf->SetFont('helvetica', 'B', 10);
//        $pdf->Cell(0, 0, 'Recouvrement:', 0, 1, 'L');
//        // Amount
//        $pdf->SetFont('helvetica', 'B', 14);
//        $pdf->Cell(0, 7, $data['amount'] . ' DA', 0, 1, 'L');
//
//        // Big number with black background
//        $pdf->SetY(85);
//        $pdf->SetX(70);
//        $pdf->SetFillColor(0, 0, 0);
//        $pdf->SetTextColor(255, 255, 255);
//        $pdf->SetFont('helvetica', 'L', 40);
//        $pdf->Cell(30, 30, $data['recipient_state_id'], 0, 1, 'C', true);  // true enables background filling
//
//        // City name under the number
//        $pdf->SetY($pdf->GetY());
//        $pdf->SetX(64);
//        $pdf->SetTextColor(0, 0, 0);  // Reset text color to black
//        $pdf->SetFont('helvetica', 'B', 14);
//        $pdf->Cell(25, 5, $data['recipient_state'], 0, 1, 'C');
//
//        // City name under the number
//        $pdf->SetY($pdf->GetY() - 1 );
//        $pdf->SetX(63);
//        $pdf->SetTextColor(0, 0, 0);  // Reset text color to black
//        $pdf->SetFont('helvetica', 'L', 12);
//        $pdf->Cell(25, 5, $data['recipient_city'], 0, 1, 'C');
//
//        // Bottom border
//        $pdf->Line(0, 130, 120, 130);
//
//        // Note at bottom
//        $pdf->SetY(132);
//        $pdf->SetFont('helvetica', '', 8);
//        $pdf->MultiCell(0, 4, $data['note'], 0, 'L');
//
//        return $pdf;
//    }

    function generate_shipping_slip_html($data) {

        $logo_url = get_theme_mod('custom_logo') ? wp_get_attachment_image_src(get_theme_mod('custom_logo'), 'full')[0] : '';


        $html = <<<HTML
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Bon de Livraison - {$data['tracking_number']}</title>
        <style>
            @media print {
                @page {
                    size: A6;
                    margin: 0;
                }
                body {
                    margin: 5mm;
                }
                .no-print {
                    display: none;
                }
            }
            
            body {
                font-family: Arial, sans-serif;
                width: 105mm;
                height: 148mm;
                margin: 0 auto;
                position: relative;
                padding: 5mm;
            }
             .logo {
                text-align: center;
                margin-bottom: 10px;
            }
            
            .logo img {
                max-width: 60mm;
                max-height: 20mm;
                object-fit: contain;
            }
            
            .tracking-number {
                text-align: center;
                font-size: 16px;
                margin: 40px 0 10px 0;
            }
            
            .barcode {
                text-align: center;
                margin: 0 auto;
                width: 90mm;
            }
            
            .barcode img {
                width: 100%;
                height: 60px;
            }
            
            .horizontal-line {
                border-top: 1px solid black;
                margin: 10px 0;
                width: 100%;
            }
            
            .info-container {
                display: flex;
                justify-content: space-between;
                margin: 10px 0;
            }
            
            .sender, .recipient {
                width: 47%;
            }
            
            .section-title {
                font-weight: bold;
                font-size: 10px;
                margin-bottom: 5px;
            }
            
            .details {
                font-size: 10px;
                line-height: 1.3;
            }
            
            .description {
                margin: 20px 0;
            }
            
            .amount-section {
                margin: 10px 0;
            }
            
            .amount {
                font-size: 14px;
                font-weight: bold;
            }
            
            .location-box {
                position: absolute;
                right: 5mm;
                bottom: 30mm;
                text-align: center;
            }
            
            .state-number {
                background: black;
                color: white;
                padding: 15px;
                font-size: 40px;
                font-weight: lighter;
                width: 30mm;
                text-align: center;
                margin-bottom: 5px;
            }
            
            .state-name {
                font-size: 14px;
                font-weight: bold;
            }
            
            .city-name {
                font-size: 12px;
            }
            
            .note {
                position: absolute;
                bottom: 5mm;
                font-size: 8px;
                width: calc(100% - 10mm);
            }
        </style>
        <script>
            window.onload = function() {
                window.print();
            }
        </script>
    </head>
    <body>
        <div class="logo">
            <img src="{$logo_url}" alt="Store Logo">
        </div>
        <div class="tracking-number">{$data['tracking_number']}</div>
        
        <div class="barcode">
            <img src="https://bwipjs-api.metafloor.com/?bcid=code128&text={$data['tracking_number']}&scale=2&includetext=false&height=60" alt="barcode">
        </div>
        
        <div class="horizontal-line"></div>
        
        <div class="info-container">
            <div class="sender">
                <div class="section-title">Expéditeur:</div>
                <div class="details">
                    {$data['sender_name']}<br>
                    {$data['sender_address']}<br>
                    {$data['sender_phone']}
                </div>
            </div>
            
            <div class="recipient">
                <div class="section-title">Destinataire:</div>
                <div class="details">
                    {$data['recipient_name']}<br>
                    {$data['recipient_address']}<br>
                    {$data['recipient_phone']}
                </div>
            </div>
        </div>
        
        <div class="description">
            <div class="section-title">Description du contenu:</div>
            <div class="details">{$data['description']}</div>
        </div>
        
        <div class="amount-section">
            <div class="section-title">Recouvrement:</div>
            <div class="amount">{$data['amount']} DA</div>
        </div>
        
        <div class="location-box">
            <div class="state-number">{$data['recipient_state_id']}</div>
            <div class="state-name">{$data['recipient_state']}</div>
            <div class="city-name">{$data['recipient_city']}</div>
        </div>
        
        <div class="horizontal-line" style="position: absolute; bottom: 25mm;"></div>
        
        <div class="note">{$data['note']}</div>
    </body>
    </html>
    HTML;

        return $html;
    }

}