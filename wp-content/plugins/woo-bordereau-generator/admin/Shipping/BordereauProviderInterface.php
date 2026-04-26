<?php
namespace WooBordereauGenerator\Admin\Shipping;

interface BordereauProviderInterface
{
    public function generate();
    public function save($post_id, array $response);
    public function track($tracking_number, $post_id = null);
}
