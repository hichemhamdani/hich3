<?php

namespace WooBordereauGenerator\Admin\Shipping;

use WooBordereauGenerator\Functions;
use WP_Error;

class NoestRequest
{
    private $provider;
    private $api_token;

    public function __construct($provider)
    {
        $this->provider = $provider;
        $this->api_token = get_option(sprintf('%s_express_api_token', $provider['slug']));
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
     * @param bool $silence
     * @return mixed
     */
    public function post($url, $data, string $method = NoestRequest::POST, $silence = false) {

        if($this->checkQuota()) {
            $ch = curl_init();
            $headers = []; // Initialize headers array

            // Build base cURL options array
            $httpHeaders = ['Content-Type: application/json'];
            
            // Add Bearer token if available
            if ($this->api_token) {
                $httpHeaders[] = 'Authorization: Bearer ' . $this->api_token;
            }
            
            $curlOptions = [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTPHEADER => $httpHeaders,
                CURLOPT_HEADERFUNCTION => function($ch, $header) use (&$headers) {
                    $len = strlen($header);
                    $header = explode(':', $header, 2);
                    if (count($header) < 2) { // ignore invalid headers
                        return $len;
                    }
                    $headers[strtolower(trim($header[0]))][] = trim($header[1]);
                    return $len;
                }
            ];

            // Handle different HTTP methods
            if ($method === NoestRequest::POST) {
                $curlOptions[CURLOPT_POST] = true;
            }

            if ($method !== NoestRequest::DELETE && !empty($data)) {
                $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data);
            }

            $curlOptions[CURLOPT_CUSTOMREQUEST] = $method;

            // Set all options at once
            curl_setopt_array($ch, $curlOptions);

            // Execute request
            $result = curl_exec($ch);

            // Check for cURL errors
            if (!$silence && curl_errno($ch)) {
                $error_msg = curl_error($ch);
                curl_close($ch);
                wp_send_json([
                    'error' => true,
                    'message' => $error_msg
                ], 401);
            }

            // Set quota from headers
            $this->setQuota($headers);

            // Close cURL resource
            curl_close($ch);

            // Decode response
            $response = json_decode($result, true);

            // Handle API errors
            if (!$silence && isset($response['error'])) {
                wp_send_json([
                    'error' => true,
                    'message' => $response['error']['message']
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
        $upload_dir = wp_upload_dir();
        $directory = $upload_dir['basedir'] . '/wc-bordereau-generator';
        $filename = $directory . '/' . $this->provider['slug'] . '_quota.json';

        if (!file_exists($filename)) {
            // No quota data, assume quota is available
            return true;
        }

        $quotaData = json_decode(file_get_contents($filename), true);

        if (isset($quotaData['x-ratelimit-remaining']) && $quotaData['x-ratelimit-remaining']['remaining'] <= 0) {
            // Quota is depleted, check reset time
            if (time() < $quotaData['x-ratelimit-remaining']['reset_time']) {
                // If the retry-after header was provided, use it
                if (isset($quotaData['retry-after'])) {
                    sleep($quotaData['retry-after']['seconds']);
                } else {
                    // Otherwise wait until reset time
                    sleep($quotaData['x-ratelimit-remaining']['reset_time'] - time());
                }
            }
            // Quota should be reset now
        }

        return true;
    }

    /**
     * Set quota information from response headers
     * @param array $headers
     */
    private function setQuota($headers)
    {
        $quota = [];
        
        // Process rate limit headers
        if (isset($headers['x-ratelimit-limit'])) {
            $quota['x-ratelimit-limit'] = [
                'limit' => (int)$headers['x-ratelimit-limit'][0],
            ];
        }
        
        if (isset($headers['x-ratelimit-remaining'])) {
            $quota['x-ratelimit-remaining'] = [
                'remaining' => (int)$headers['x-ratelimit-remaining'][0],
                'reset_time' => null // To be determined based on reset header
            ];
        }
        
        if (isset($headers['x-ratelimit-reset'])) {
            $reset_time = (int)$headers['x-ratelimit-reset'][0];
            if (isset($quota['x-ratelimit-remaining'])) {
                $quota['x-ratelimit-remaining']['reset_time'] = $reset_time;
            }
        }
        
        if (isset($headers['retry-after'])) {
            $quota['retry-after'] = [
                'seconds' => (int)$headers['retry-after'][0]
            ];
        }

        $upload_dir = wp_upload_dir();
        $directory = $upload_dir['basedir'] . '/wc-bordereau-generator';
        if (!is_dir($directory)) {
            wp_mkdir_p($directory);
        }
        $filename = $directory . '/' . $this->provider['slug'] . '_quota.json';
        file_put_contents($filename, json_encode($quota));
    }

    /**
     * @param $url
     * @param array $data
     * @return array
     */
    public function fetchRequest($url, array $data = [])
    {
        if($this->checkQuota()) {
            try {
                $curl = curl_init();
                $headers = []; // Initialize headers array

                // For GET requests, append data to URL if exists
                if (!empty($data)) {
                    $url .= (parse_url($url, PHP_URL_QUERY) ? '&' : '?') . http_build_query($data);
                }

                // Set the header function BEFORE executing the request
                curl_setopt_array($curl, array(
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 5,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_HEADER => true, // Keep this true to get headers
                    CURLOPT_SSL_VERIFYHOST => false,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => 'GET',
                    CURLOPT_HTTPHEADER => array(
                        "Content-Type: application/json"
                    ),
                    CURLOPT_HEADERFUNCTION => function($curl, $header) use (&$headers) {
                        $len = strlen($header);
                        $header = explode(':', $header, 2);
                        if (count($header) < 2) // ignore invalid headers
                            return $len;

                        $headers[strtolower(trim($header[0]))][] = trim($header[1]);
                        return $len;
                    }
                ));

                // Execute the request
                $result = curl_exec($curl);

                // Get header size
                $header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
                // Separate the headers and body
                $header = substr($result, 0, $header_size);
                $body = substr($result, $header_size);

                // Close curl resource
                curl_close($curl);

                // After curl_exec
                $this->setQuota($headers);

                // Decode only the body part
                $response = json_decode($body, true);

                if (isset($response['error'])) {
                    new WP_Error($response['error']['code'], $response['error']['message']);
                    throw new \ErrorException("Ajax Error: " . $response['error']['message']);
                }

                return $response;
            } catch (\ErrorException $exception) {
                new WP_Error(400, $exception->getMessage());
            }
        }

        wp_send_json([
            'error' => true,
            'message'=> 'Too Many Requests'
        ], 429);
    }
}
