<?php
namespace WooBordereauGenerator\Admin\Shipping;

use WooBordereauGenerator\Functions;
use WooBordereauGenerator\Helpers;
use WP_Error;

class MylerzExpressRequest
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
    public function post($url, $data, string $method = MylerzExpressRequest::POST)
    {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);

        if($method == MylerzExpressRequest::POST) {
            curl_setopt($ch, CURLOPT_POST, 1);
        } else {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }


        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'Accept: application/json',
                'authorization: Bearer '. $this->getToken(),
            ),
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
                'message'=> $response['error']['message']
            ], $response['error']['code']);
        }

        return $response;
    }

    public function auth() {
        $token = $this->getToken();
        return Helpers::decode_jwt($token);
    }


    public function fetchRequest($url)
    {

        try {

            $curl = curl_init();

            $header = array(
                'Accept: application/json',
                'authorization: Bearer ' . $this->getToken(),
            );

            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => $header,
            ));

            $result = curl_exec($curl);
            curl_close($curl);
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

    public function getToken($expired = false)
    {
        try {

            $path = Functions::get_path('mylerz_token.json');

            if (! file_exists($path)) {
                $token = $this->fetchToken();
                file_put_contents($path, $token);
            }

            $token = json_decode(file_get_contents($path), true);

            // check if the token is expired

            if (strtotime($token['.expires']) <= time()) {
                $token = $this->fetchToken();
                file_put_contents($path, $token);
                $token = json_decode(file_get_contents($path), true);
            }

            return $token['access_token'];

        } catch (\ErrorException $exception) {
            error_log($exception->getMessage());
        }

    }

    private function fetchToken()
    {
        $data = [
            'grant_type' => 'password',
            'username' => get_option('mylerz_username'),
            'password' => get_option('mylerz_password')
        ];

        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->provider['api_url'].'/token',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return $response;
    }
}
