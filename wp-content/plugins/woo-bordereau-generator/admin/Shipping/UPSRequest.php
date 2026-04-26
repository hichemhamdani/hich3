<?php

namespace WooBordereauGenerator\Admin\Shipping;

use WooBordereauGenerator\Functions;
use WP_Error;

class UPSRequest
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
    const VERSION = "v1";


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
    public function post($url, $data, string $method = UPSRequest::POST) {

        if($this->checkQuota()) {

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);

            if($method == UPSRequest::POST) {
                curl_setopt($ch, CURLOPT_POST, 1);
            }

            if($method !== UPSRequest::DELETE) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }

            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'X-API-ID: '. get_option($this->provider['slug'].'_api_key'),
                'X-API-TOKEN: '. get_option($this->provider['slug'].'_api_token'),
                "Content-Type: application/json"
            ));

            // this function is called by curl for each header received
            curl_setopt($ch, CURLOPT_HEADERFUNCTION,
                function($ch, $header) use (&$headers)
                {
                    $len = strlen($header);
                    $header = explode(':', $header, 2);
                    if (count($header) < 2) // ignore invalid headers
                        return $len;

                    $headers[strtolower(trim($header[0]))][] = trim($header[1]);
                    return $len;
                }
            );

            $result = curl_exec($ch);
            $this->setQuota($headers);
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

        wp_send_json([
            'error' => true,
            'message'=> 'Too Many Requests'
        ], 429);

    }

    /**
     * Check the Quota for each request
     * @return bool
     */
    private function checkQuota()
    {
        return true;
    }

    private function setQuota($headers)
    {
        $upload_dir   = wp_upload_dir();
        $directory = $upload_dir['basedir'] . '/wc-bordereau-generator';
        if (!is_dir($directory)) {
            wp_mkdir_p($directory);
        }
        $filename = $this->provider['slug'].'_quota.json';

    }

    /**
     * @param $url
     * @return array
     */
    public function fetchRequest($url)
    {
        try {
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
                    'X-API-ID: ' . get_option($this->provider['slug'].'_api_key'),
                    'X-API-TOKEN: ' . get_option($this->provider['slug'].'_api_token'),
                    "Content-Type: application/json"
                ),
            ));

            // this function is called by curl for each header received
            curl_setopt($curl, CURLOPT_HEADERFUNCTION,
                function ($curl, $header) use (&$headers) {
                    $len = strlen($header);
                    $header = explode(':', $header, 2);
                    if (count($header) < 2) // ignore invalid headers
                        return $len;

                    $headers[strtolower(trim($header[0]))][] = trim($header[1]);
                    return $len;
                }
            );

            $result = curl_exec($curl);
            $response = json_decode($result, true);

            $this->setQuota($headers);

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

    public function request_new_token() {

        $ups_id = get_option($this->provider['slug'].'_client_id');
        $ups_secret = get_option($this->provider['slug'].'_client_secret');
        $ups_account = get_option($this->provider['slug'].'_account_number');

        $credentials = base64_encode("$ups_id:$ups_secret");
        $url = 'https://wwwcie.ups.com/security/v1/oauth/token';
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'accept: application/json',
            "x-merchant-id: $ups_account",
            "Authorization: Basic $credentials",
            'Content-Type: application/x-www-form-urlencoded',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        $response = curl_exec($ch);
        $response_array = json_decode($response, true);
        curl_close($ch);

        return $response_array;
    }


    // Function to update the token and its metadata in WordPress options
    function update_token_data($access_token, $expires_in, $issued_at) {
        update_option($this->provider['slug'].'_access_token', $access_token);
        update_option($this->provider['slug'].'_token_expires_in', $expires_in);
        update_option($this->provider['slug'].'_token_issued_at', $issued_at);
    }

    // Function to check if the current token is valid
    function is_token_valid() {
        $issued_at = get_option($this->provider['slug'].'_token_issued_at');
        $expires_in = get_option($this->provider['slug'].'_token_expires_in');
        $current_time = round(microtime(true) * 1000); // Current time in milliseconds

        // Check if the current time is within the token's valid period
        return ($issued_at + $expires_in * 1000) > $current_time;
    }

    // Function to get the current token, or request a new one if it's expired
    function get_current_token() {
        if ($this->is_token_valid()) {
            // Token is valid, return it
            return get_option($this->provider['slug'].'_access_token');
        } else {
            // Token is expired, request a new one and update the options
            $new_token_data = $this->request_new_token(); // Implement this function to handle token request logic
            $this->update_token_data($new_token_data['access_token'], $new_token_data['expires_in'], round(microtime(true) * 1000));

            return $new_token_data['access_token'];
        }
    }
}
