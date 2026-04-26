<?php
/**
 * Near Delivery API Request Handler.
 *
 * This class manages HTTP requests to the Near Delivery API,
 * handling authentication, caching, and response processing.
 *
 * @package    WC_Bordereau_Generator
 * @subpackage WC_Bordereau_Generator/admin/shipping
 */
namespace WooBordereauGenerator\Admin\Shipping;


use WooBordereauGenerator\Functions;
use WP_Error;

/**
 * Near Delivery API request handler class.
 *
 * Handles all HTTP communication with the Near Delivery API,
 * including authentication, GET/POST requests, and response processing.
 */
class NearDeliveryRequest
{

    /**
     * Provider configuration data.
     *
     * @var array
     */
    private array $provider;

    /**
     * Near Delivery API Key
     *
     * @var string
     */
    private string $api_key;

    /**
     * Near Delivery API Secret
     *
     * @var string
     */
    private string $api_secret;

    /**
     * HTTP method constant for GET requests.
     *
     * @var string
     */
    const GET = 'GET';

    /**
     * HTTP method constant for POST requests.
     *
     * @var string
     */
    const POST = 'POST';

    /**
     * HTTP method constant for PATCH requests.
     *
     * @var string
     */
    const PATCH = 'PATCH';

    /**
     * HTTP method constant for DELETE requests.
     *
     * @var string
     */
    const DELETE = 'DELETE';

    /**
     * Constructor.
     *
     * Initializes a new API request handler with the provider configuration.
     *
     * @param array $provider The provider configuration array.
     */
    public function __construct($provider)
    {
        $this->provider = $provider;
        $this->api_key = get_option($provider['slug'] . '_api_key');
        $this->api_secret = get_option($provider['slug'] . '_api_secret');
    }

    /**
     * Perform a GET request to the API.
     *
     * Fetches data from the API with optional caching to reduce API calls.
     * If caching is enabled, responses are stored in JSON files.
     *
     * @param string $url     The API endpoint URL.
     * @param bool   $cache   Whether to cache the response (default: true).
     * @param array  $data    Additional data for the request (default: empty).
     * @return mixed          The API response as an array.
     */
    public function get($url, bool $cache = true, array $data = []) {

        if($cache) {
            $cache_key = md5($url . date('W'));
            $path = Functions::get_path($cache_key.".json");

            if (file_exists($path)) {
                return json_decode(file_get_contents($path), true);
            } else {
                $response = $this->fetchRequest($url, $data);
                file_put_contents($path, json_encode($response));
                return $response;
            }
        } else {
            return $this->fetchRequest($url, $data);
        }
    }

    /**
     * Perform a POST, PATCH or DELETE request to the API.
     *
     * Sends data to the API using the specified HTTP method.
     * Handles authentication and processes the response.
     *
     * @param string $url     The API endpoint URL.
     * @param mixed  $data    The data to send to the API.
     * @param string $method  The HTTP method to use (default: POST).
     * @return mixed          The API response as an array.
     */
    public function post($url, $data, string $method = self::POST)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);

        if($method == self::POST) {
            curl_setopt($ch, CURLOPT_POST, 1);
        }
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ));

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

        // Set the authentication headers with API Key and API Secret
        $auth_headers = $this->getAuthHeaders();
        curl_setopt($ch, CURLOPT_HTTPHEADER, $auth_headers);

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

        if(isset($response['error']) && $response['error'] != 0) {
            wp_send_json([
                'error' => true,
                'message'=> $response['error']['message'] ?? 'Unknown error'
            ], $response['error']['code'] ?? 400);
        }

        return $response;
    }

    /**
     * Perform a DELETE request to the API.
     *
     * Convenience method to delete a resource.
     *
     * @param string $url     The API endpoint URL.
     * @return mixed          The API response as an array.
     */
    public function delete($url)
    {
        return $this->post($url, [], self::DELETE);
    }

    /**
     * Perform a PATCH request to the API.
     *
     * Convenience method to update a resource.
     *
     * @param string $url     The API endpoint URL.
     * @param array  $data    The data to send.
     * @return mixed          The API response as an array.
     */
    public function patch($url, $data)
    {
        return $this->post($url, $data, self::PATCH);
    }

    /**
     * Perform a GET request to fetch data from the API.
     *
     * Internal method that handles the actual HTTP request to the API.
     * Configures and executes the cURL request with appropriate headers.
     *
     * @param string $url     The API endpoint URL.
     * @param array  $data    Additional data for the request (default: empty).
     * @return mixed          The API response as an array.
     */
    public function fetchRequest($url, $data = [])
    {
        try {
            $curl = curl_init();

            // Set the authentication headers with API Key and API Secret
            $auth_headers = $this->getAuthHeaders();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_HTTPHEADER => $auth_headers,
            ));

            if (!empty($data)) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
            }

            $result = curl_exec($curl);

            $response = json_decode($result, true);

            if (isset($response['error'])) {
                wp_send_json([
                    'error' => true,
                    'message' => $response['error']['message'] ?? 'Unknown error'
                ], $response['error']['code'] ?? 400);

                new WP_Error($response['error']['code'] ?? 400, $response['error']['message'] ?? 'Unknown error');
            }

            curl_close($curl);

            return $response;
        } catch (\ErrorException $exception) {
            new WP_Error(400, $exception->getMessage());
        }
    }

    /**
     * Get the authentication headers with API Key and API Secret.
     *
     * Near Delivery API uses ApiKey and ApiSecret headers for authentication.
     *
     * @return array The authentication headers.
     */
    private function getAuthHeaders()
    {
        return [
            'Content-Type: application/json',
            'ApiKey: ' . $this->api_key,
            'ApiSecret: ' . $this->api_secret
        ];
    }

}
