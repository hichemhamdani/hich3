<?php

namespace WooBordereauGenerator;

class Functions
{
    public static function get_path($filename): string
    {
        $upload_dir   = wp_upload_dir();

        $directory = $upload_dir['basedir'] . '/wc-bordereau-generator';

        if (!is_dir($directory)) {
            wp_mkdir_p($directory);
        }

        return $directory . '/' . $filename;
    }
}