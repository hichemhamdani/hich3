<?php
/**
 * ZimoExpress API Request Handler.
 * 
 * This class manages HTTP requests to the ZimoExpress API,
 * handling authentication, caching, and response processing.
 * 
 * @package    WC_Bordereau_Generator
 * @subpackage WC_Bordereau_Generator/admin/shipping
 */
namespace WooBordereauGenerator\Admin\Shipping;


use WooBordereauGenerator\Functions;
use WP_Error;

/**
 * ZimoExpress API request handler class.
 * 
 * Handles all HTTP communication with the ZimoExpress API,
 * including authentication, GET/POST requests, and response processing.
 */
class ZimoExpressRequest
{

    /**
     * Provider configuration data.
     *
     * @var array
     */
    private array $provider;

    /**
     * HTTP method constant for GET requests.
     * s
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
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        ));

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

        // Set the authentication header with API token
        $auth_header = $this->getAuthHeader();
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json",
            $auth_header
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

        if(isset($response['error']) && $response['error'] != 0) {
            wp_send_json([
                'error' => true,
                'message'=> $response['error']['message']
            ], $response['error']['code']);
        }

        return $response;
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
            
            // Set the authentication header with API token
            $auth_header = $this->getAuthHeader();
            
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
                    "Content-Type: application/json",
                    $auth_header,
                ),
            ));

            if (!empty($data)) {
                curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
            }

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

    /**
     * Get the authentication header with API token.
     *
     * @return string The authentication header.
     */
    private function getAuthHeader()
    {
        $api_token = $this->getSecureApiToken();
        return 'Authorization: Bearer ' . $api_token;
    }

    /**
     * Get the API token from storage or login if needed.
     * 
     * Retrieves the API token from WordPress options.
     * If no token exists, tries to log in using username/password
     * and saves the obtained token.
     *
     * @return string The API token.
     */
    public function getSecureApiToken()
    {

        try {
            // Try to get the API token from WordPress options
            $api_token = get_option('zimoexpress_api_token');
            
            // If no token is found, try to login and get one
            if (empty($api_token)) {
                $username = get_option('zimoexpress_username');
                $password = get_option('zimoexpress_password');
                
                // Check if we have credentials to attempt login
                if (!empty($username) && !empty($password)) {
                    // Try to get a token via login
                    $token = $this->loginAndGetToken();
                    
                    // If we got a token, save it for future use
                    if (!empty($token)) {
                        update_option('zimoexpress_api_token', $token);
                        $api_token = $token;
                    }
                } else {
                    error_log('ZimoExpress credentials not found. Please set username and password in the settings.');
                }
            }
            
            return $api_token ?: '';

        } catch (\ErrorException $exception) {
            error_log($exception->getMessage());
            return '';
        }
    }
    
    /**
     * Login to ZimoExpress API v1 and get a token.
     *
     * @return string The authentication token or empty string on failure.
     */
    private function loginAndGetToken()
    {
        try {
            $data = [
                'email' => get_option('zimoexpress_username'),
                'password' => get_option('zimoexpress_password')
            ];

            $curl = curl_init();

            curl_setopt_array($curl, array(
                CURLOPT_URL => $this->provider['api_url'].'/login',
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
                ),
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ));

            $response = curl_exec($curl);
            curl_close($curl);
            
            if (!empty($response)) {
                // Extract token from the response - assuming it's a JSON string
                $token = str_replace('"', '', $response);
                return $token;
            }
            
            return '';
        } catch (\ErrorException $exception) {
            error_log('ZimoExpress login failed: ' . $exception->getMessage());
            return '';
        }

    }

}
