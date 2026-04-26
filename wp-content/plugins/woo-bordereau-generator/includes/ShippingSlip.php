<?php

namespace WooBordereauGenerator;

use TCPDF;

class ShippingSlip extends TCPDF {
    public function Header() {
        $this->SetY(10);
    }
}