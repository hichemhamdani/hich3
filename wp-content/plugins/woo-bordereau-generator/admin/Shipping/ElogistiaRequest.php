<?php

namespace WooBordereauGenerator\Admin\Shipping;

use WooBordereauGenerator\Functions;
use WP_Error;

class ElogistiaRequest
{

    private $provider;

    public function __construct($provider)
    {
        $this->provider = $provider;
    }

    const GET = 'GET';
    const POST = 'POST';
    const PATCH = 'PATCH';
    const DELETE = 'DELETE';

    /**
     * @param $url
     * @param bool $cache
     * @return mixed
     */
    public function get($url, bool $cache = true) {


        if($cache) {
            $cache_key = md5($url . date('W'));
            $path = Functions::get_path($cache_key.".json");

            if (file_exists($path)) {
                return json_decode(file_get_contents($path), true);
            } else {
                $response = $this->fetchRequest($url);
                file_put_contents($path, json_encode($response));
                return $response;
            }
        } else {

            return $this->fetchRequest($url);
        }
    }

    /**
     * @param $url
     * @param $data
     * @param string $method
     * @return mixed
     */
    public function post($url, $data, string $method = ElogistiaRequest::POST)
    {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);

        if($method == ElogistiaRequest::POST) {
            curl_setopt($ch, CURLOPT_POST, 1);
        }

        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ));

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json"
        ));

        $result = curl_exec($ch);

        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            new WP_Error($error_msg);
            wp_send_json([
                'error' => true,
                'message'=>  $error_msg
            ], 401);
        }

        curl_close($ch);

        $response = json_decode($result, true);

        if(isset($response['error'])) {

            wp_send_json([
                'error' => true,
                'message'=> json_encode($response)
            ], 401);
        }

        return $response;

    }


    public function fetchRequest($url)
    {

        try {

            if (strpos($url, 'key=') === false) {
                // The key parameter is not present, so we'll add it
                $key_value = get_option($this->provider['slug'].'_api_token');  // Replace with your actual key value
                $url .= (parse_url($url, PHP_URL_QUERY) ? '&' : '?') . 'key=' . $key_value;
            }

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => array(
                    "Content-Type: application/json"
                ),
            ));

            $result = curl_exec($curl);

            $response = json_decode($result, true);

            if (isset($response['error'])) {
                wp_send_json([
                    'error' => true,
                    'message' => $response['error']['message']
                ], $response['error']['code']);

                new WP_Error($response['error']['code'], $response['error']['message']);
            }

            return $response;
        } catch (\ErrorException $exception) {
            new WP_Error(400, $exception->getMessage());
        }
    }
}
