<?php

namespace WooBordereauGenerator\Admin\Shipping;

use WooBordereauGenerator\Functions;
use WooBordereauGenerator\Helpers;
use WP_Error;

class ThreeMExpressRequest
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
    public function post($url, $data, string $method = ThreeMExpressRequest::POST)
    {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);

        if($method == ThreeMExpressRequest::POST) {
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
                'authority: express-backend-main-k6wb36zmaa-ew.a.run.app',
                'accept: application/json, text/plain, */*',
                'accept-language: en-US,en;q=0.9,fr;q=0.8',
                'authorization: '. $this->getToken(),
                'cache-control: no-cache',
                'content-type: application/json',
                'dnt: 1',
                'pragma: no-cache',
                'sec-ch-ua: "Not_A Brand";v="8", "Chromium";v="120", "Google Chrome";v="120"',
                'sec-ch-ua-mobile: ?0',
                'sec-ch-ua-platform: "macOS"',
                'sec-fetch-dest: empty',
                'sec-fetch-mode: cors',
                'sec-fetch-site: cross-site',
                'user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
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
                'authority: express-backend-main-k6wb36zmaa-ew.a.run.app',
                'accept: application/json, text/plain, */*',
                'accept-language: en-US,en;q=0.9,fr;q=0.8',
                'authorization: ' . $this->getToken(),
                'cache-control: no-cache',
                'dnt: 1',
                'pragma: no-cache',
                'sec-ch-ua: "Not_A Brand";v="8", "Chromium";v="120", "Google Chrome";v="120"',
                'sec-ch-ua-mobile: ?0',
                'sec-ch-ua-platform: "macOS"',
                'sec-fetch-dest: empty',
                'sec-fetch-mode: cors',
                'sec-fetch-site: cross-site',
                'user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
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

            $path = Functions::get_path('3mexpress_token.json');

            if (! file_exists($path)) {
                $token = $this->fetchToken();
                file_put_contents($path, $token);
            }

            $token = json_decode(file_get_contents($path), true);

            // check if the token is expired
            $tokenObj  = Helpers::decode_jwt($token['authToken']);

            if ($tokenObj->exp <= time()) {
                $token = $this->fetchToken();
                file_put_contents($path, $token);
                $token = json_decode(file_get_contents($path), true);
            }

            return $token['authToken'];

        } catch (\ErrorException $exception) {
            error_log($exception->getMessage());
        }

    }

    private function fetchToken()
    {
        $curl = curl_init();

        $username = get_option('3mexpress_username');

        if (strpos($username, "0") === 0) {
            $username = "213" . substr($username, 1);
        }

        $data = [
            'username' => $username,
            'password' => get_option('3mexpress_password')
        ];

        curl_setopt_array($curl, array(
            CURLOPT_URL => $this->provider['api_url'].'/v1/users/auth/customers/sign-in',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => array(
                'authority: express-backend-main-k6wb36zmaa-ew.a.run.app',
                'accept: application/json, text/plain, */*',
                'accept-language: en-US,en;q=0.9,fr;q=0.8',
                'cache-control: no-cache',
                'content-type: application/json',
                'dnt: 1',
                'pragma: no-cache',
                'sec-ch-ua: "Not_A Brand";v="8", "Chromium";v="120", "Google Chrome";v="120"',
                'sec-ch-ua-mobile: ?0',
                'sec-ch-ua-platform: "macOS"',
                'sec-fetch-dest: empty',
                'sec-fetch-mode: cors',
                'sec-fetch-site: cross-site',
                'user-agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);

        return $response;
    }

    public function getUserInfo() {
        try {

            $path = Functions::get_path('3mexpress_token.json');

            if (! file_exists($path)) {
                $token = $this->fetchToken();
                file_put_contents($path, $token);
            }

            $token = json_decode(file_get_contents($path), true);

            // check if the token is expired
            $tokenObj  = Helpers::decode_jwt($token['authToken']);

            if ($tokenObj->exp <= time()) {
                $token = $this->fetchToken();
                file_put_contents($path, $token);
                $token = json_decode(file_get_contents($path), true);
            }

            return $token;

        } catch (\ErrorException $exception) {
            error_log($exception->getMessage());
        }
    }
}
