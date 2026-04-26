<?php

namespace WooBordereauGenerator\Admin\Shipping;

use WooBordereauGenerator\Functions;

class PicoSolutionNewRequest
{
    private $provider;
    private $cookies = [];

    public function __construct($provider)
    {
        $this->provider = $provider;
    }

    const GET = 'GET';
    const POST = 'POST';

    /**
     * @param $url
     * @param bool $cache
     * @return mixed
     */
    public function get($url, bool $cache = true)
    {
        if ($cache) {
            $cache_key = md5($url . date('W'));
            $path = Functions::get_path($cache_key . ".json");

            if (file_exists($path)) {
                return json_decode(file_get_contents($path), true);
            } else {
                $response = $this->request($url, [], self::GET);
                file_put_contents($path, json_encode($response));
                return $response;
            }
        } else {
            return $this->request($url, [], self::GET);
        }
    }

    /**
     * @param $url
     * @param $data
     * @param string $method
     * @return mixed
     */
    public function post($url, $data = [], string $method = self::POST)
    {
        return $this->request($url, $data, $method);
    }

    /**
     * Make a curl request with SSL verification bypassed
     * @param string $url
     * @param array $data
     * @param string $method
     * @return mixed
     */
    public function request(string $url, array $data = [], string $method = self::POST)
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HEADER => true,
        ]);

        if ($method == self::POST && !empty($data)) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        // Set cookies if available
        if (!empty($this->cookies)) {
            curl_setopt($ch, CURLOPT_COOKIE, $this->buildCookieString());
        }

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            $error_msg = curl_error($ch);
            curl_close($ch);
            return ['error' => true, 'message' => $error_msg];
        }

        // Parse headers and body
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $header_size);
        $body = substr($response, $header_size);

        // Extract cookies from response
        $this->parseCookies($headers);

        curl_close($ch);

        return $body;
    }

    /**
     * Auth to the provider
     * @return bool
     */
    public function auth()
    {
        $api_key = get_option($this->provider['slug'] . '_username');
        $api_token = get_option($this->provider['slug'] . '_password');

        $data = [
            'WD_ACTION_' => 'AJAXEXECUTE',
            'EXECUTEPROCCHAMPS' => 'ServeurAPI.API_Connecte',
            'WD_CONTEXTE_' => $this->provider['extra']['context'],
            'PA1' => $api_key,
            'PA2' => $api_token,
            'PA3' => $this->provider['extra']['pa3']
        ];

        $response = $this->request($this->provider['login_url'], $data, self::POST);

        return !empty($this->cookies);
    }

    /**
     * Parse cookies from response headers
     * @param string $headers
     */
    private function parseCookies(string $headers)
    {
        preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $headers, $matches);

        foreach ($matches[1] as $cookie) {
            $parts = explode('=', $cookie, 2);
            if (count($parts) == 2) {
                $this->cookies[trim($parts[0])] = trim($parts[1]);
            }
        }
    }

    /**
     * Build cookie string for request
     * @return string
     */
    private function buildCookieString(): string
    {
        $cookieStrings = [];
        foreach ($this->cookies as $name => $value) {
            $cookieStrings[] = $name . '=' . $value;
        }
        return implode('; ', $cookieStrings);
    }

    /**
     * Get stored cookies
     * @return array
     */
    public function getCookies(): array
    {
        return $this->cookies;
    }

    /**
     * Clear stored cookies
     */
    public function clearCookies()
    {
        $this->cookies = [];
    }

    /**
     * Download a file to a local path
     * @param string $url
     * @param string $destination
     * @return bool
     */
    public function downloadFile(string $url, string $destination): bool
    {
        $ch = curl_init();

        $fp = fopen($destination, 'w');

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_TIMEOUT => 60,
        ]);

        // Set cookies if available
        if (!empty($this->cookies)) {
            curl_setopt($ch, CURLOPT_COOKIE, $this->buildCookieString());
        }

        $result = curl_exec($ch);

        curl_close($ch);
        fclose($fp);

        return $result !== false;
    }
}
