<?php

namespace WooBordereauGenerator\Admin\Shipping;


use WC_Product;
use WooBordereauGenerator\Helpers;

abstract class BordereauProvider
{
    protected $settings;
    protected $provider;
    protected $formData;

    /**
     * Return the settings we need in the metabox
     * @return array
     * @since 1.2.4
     */
    public function get_settings(array $data, $option = null)
    {

        $provider = $this->provider;

        $order = $this->formData;

        $fields = $provider['fields'];
        $key = $fields['total']['choices'][0]['name'] ?? null;

        $params = $order->get_data();
        $products = $params['line_items'];

        $productString = Helpers::generate_products_string($products);

        $total = get_option($key) ? get_option($key) : 'with-shipping';

        $cart_value = 0;

        if ($total == 'with-shipping') {
            $cart_value = (float)$order->get_subtotal() + $order->get_total_shipping();

        } elseif ($total === 'without-shipping') {
            $cart_value = (float)$order->get_total() - $order->get_total_shipping();
        }

        $data = [];
        if ($key) {
            $data['total_calculate'] = get_option($key) ? get_option($key) : 'with-shipping';
            $data['type'] = $provider['type'];
            $data['total'] = $cart_value;
            $data['products_string'] = $productString;

        }
        return $data;
    }

}
