<?php
namespace WooBordereauGenerator;

use TCPDF;

class MYPDF extends TCPDF
{
    //Page header
    public function Header() {
        // Logo
        $image_file = $this->get_custom_logo_relative_path();

        $fixedWidth = PDF_HEADER_LOGO_WIDTH; // Example value, adjust to your needs

        list($originalWidth, $originalHeight) = getimagesize($image_file);

// Calculate the height while maintaining the aspect ratio
        $height = ($fixedWidth * $originalHeight) / $originalWidth;

        $this->Image($image_file, 10, 10, $fixedWidth, $height, 'JPG', '', 'T', false, 300, '', false, false, 0, false, false, false);
        // Set font
        $this->SetFont('helvetica', 'B', 20);
        // Title
    }


    function get_custom_logo_relative_path() {
        $custom_logo_id = get_theme_mod('custom_logo');
        $image = wp_get_attachment_image_src($custom_logo_id, 'full');

        if ($image) {
            // Get the relative path from the absolute URL
            $relative_path = str_replace(home_url(), '', $image[0]);
            return $relative_path;
        } else {
            return '';
        }
    }
}
