<?php

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\GuzzleException;
use WooBordereauGenerator\Admin\Shipping\ElogistiaRequest;
use WooBordereauGenerator\Functions;

class PicoSolutionRequest
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
                'message'=> $response['error']['message']
            ], $response['error']['code']);
        }

        return $response;

    }


    public function fetchRequest($url)
    {
        try {

            $cookiesJar = $this->auth();

            $client = new Client(['cookies' => true]);

            return $client->request('GET', $url, [
                'cookies' => $cookiesJar
            ]);

        } catch (\ErrorException $exception) {
            new WP_Error(400, $exception->getMessage());
        }
    }

    /**
     * Auth to ZR Express
     * @return CookieJar
     * @throws GuzzleException
     */
    private function auth()
    {
        try {
            $api_key = get_option($this->provider['slug'] . '_username');
            $api_token = get_option($this->provider['slug'] . '_password');
            $client = new Client(['cookies' => true]);
            $jar = new CookieJar;
            $client->request('POST', $this->provider['login_url'], [

                'form_params' => [
                    'WD_ACTION_' => 'AJAXEXECUTE',
                    'EXECUTEPROCCHAMPS' => 'ServeurAPI.API_Connecte',
                    'WD_CONTEXTE_' => $this->provider['extra']['context'],
                    'PA1' => $api_key,
                    'PA2' => $api_token,
                    'PA3' => $this->provider['extra']['pa3']
                ],
                'cookies' => $jar
            ]);

            return $jar;
        } catch (\Exception $e) {
            set_transient('order_bulk_add_error', $e->getMessage(), 45);
            $logger = \wc_get_logger();
            $logger->error('Bulk Upload Error: ' . $e->getMessage(), array('source' => WC_BORDEREAU_POST_TYPE));
        }
    }
}
